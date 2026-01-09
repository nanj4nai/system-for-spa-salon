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
    // SERVICES TOTAL
    // =====================
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_price), 0) AS total
        FROM spa_transaction_services
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $serviceTotal = (float)$stmt->get_result()->fetch_assoc()['total'];

    // =====================
    // PRODUCTS TOTAL
    // =====================
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_price), 0) AS total
        FROM product_sales
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $productTotal = (float)$stmt->get_result()->fetch_assoc()['total'];

    $grandTotal = $serviceTotal + $productTotal;

    // =====================
    // UPDATE TRANSACTION (FIXED)
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
