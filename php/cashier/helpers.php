<?php

function getOpenShiftId(mysqli $conn, int $user_id = null)
{

    if ($user_id !== null) {
        $stmt = $conn->prepare("
            SELECT id
            FROM cashier_shifts
            WHERE status = 'open' AND user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = $conn->query("
            SELECT id
            FROM cashier_shifts
            WHERE status = 'open'
            LIMIT 1
        ");
    }

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int)$row['id'];
    }

    return null;
}

function recalcTransaction(mysqli $conn, int $transaction_id)
{
    // =====================
    // LOAD VAT SETTINGS
    // =====================
    $stmt = $conn->prepare("
        SELECT include_vat
        FROM spa_transactions
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if (!$tx) return;

    $includeVat = (int)$tx['include_vat'];

    // global VAT rate (settings table)
    $vatRate = 0.12;

    // =====================
    // SERVICES TOTAL
    // =====================
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_price), 0)
        FROM spa_transaction_services
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $serviceTotal = (float)$stmt->get_result()->fetch_row()[0];

    // =====================
    // CONSUMABLE TOTAL
    // =====================
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN p.unit_per_item > 0
                THEN (asp.quantity_used / p.unit_per_item) * p.price
                ELSE 0
            END
        ), 0)
        FROM spa_transaction_services ts
        JOIN appointment_services aps ON aps.id = ts.appointment_service_id
        JOIN appointment_service_products asp ON asp.appointment_service_id = aps.id
        JOIN products p ON p.id = asp.product_id
        WHERE ts.transaction_id = ?
        AND p.product_type = 'consumable'
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $consumableTotal = (float)$stmt->get_result()->fetch_row()[0];

    // =====================
    // EXTRA PRODUCTS
    // =====================
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_price), 0)
        FROM product_sales
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $extraProductTotal = (float)$stmt->get_result()->fetch_row()[0];

    // =====================
    // SUBTOTAL
    // =====================
    $subtotal = $serviceTotal + $consumableTotal + $extraProductTotal;

    // =====================
    // VAT
    // =====================
    $vatAmount = $includeVat
        ? round($subtotal * $vatRate, 2)
        : 0;

    $grandTotal = $subtotal + $vatAmount;

    // =====================
    // UPDATE TRANSACTION
    // =====================
    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET
            total_amount = ?,
            balance = GREATEST(? - amount_paid, 0),
            payment_status = CASE
                WHEN amount_paid = 0 THEN 'unpaid'
                WHEN amount_paid >= ? THEN 'paid'
                ELSE 'partial'
            END
        WHERE id = ?
    ");
    $stmt->bind_param(
        "dddi",
        $grandTotal,
        $grandTotal,
        $grandTotal,
        $transaction_id
    );
    $stmt->execute();
}


function getServiceProductUsage(mysqli $conn, int $transaction_id): array
{
    $map = [];

    $stmt = $conn->prepare("
        SELECT
            aps.id AS appointment_service_id,
            p.name,
            p.unit,
            p.unit_per_item,
            p.price AS pack_price,
            asp.quantity_used
        FROM spa_transaction_services ts
        JOIN appointment_services aps ON aps.id = ts.appointment_service_id
        JOIN appointment_service_products asp ON asp.appointment_service_id = aps.id
        JOIN products p ON p.id = asp.product_id
        WHERE ts.transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $ratio = 0;
        if ((float)$row['unit_per_item'] > 0) {
            $ratio = $row['quantity_used'] / $row['unit_per_item'];
        }

        $total_price = round($ratio * $row['pack_price'], 2);

        $map[$row['appointment_service_id']][] = [
            "name" => $row['name'],
            "unit" => $row['unit'],
            "quantity_used" => (float)$row['quantity_used'],
            "total_price" => $total_price
        ];
    }

    return $map;
}
