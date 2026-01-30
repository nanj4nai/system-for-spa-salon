<?php

function getOpenShiftId(mysqli $conn, int $user_id): ?int
{
    $stmt = $conn->prepare("
        SELECT id
        FROM cashier_shifts
        WHERE user_id = ?
          AND status = 'open'
          AND approval_status = 'approved'
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    return $row ? (int)$row['id'] : null;
}


function recalcTransaction(mysqli $conn, int $transaction_id)
{
    // =====================
    // LOAD TRANSACTION
    // =====================
    $stmt = $conn->prepare("
        SELECT appointment_id, include_vat, amount_paid
        FROM spa_transactions
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tx) return;

    $appointment_id = (int)$tx['appointment_id'];
    $includeVat     = (int)$tx['include_vat'];
    $amountPaid     = (float)$tx['amount_paid'];

    // =====================
    // SEED TRANSACTION SERVICES (ONLY IF APPOINTMENT HAS SERVICES)
    // =====================
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM spa_transaction_services 
        WHERE transaction_id = ?
    ");
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->bind_result($existingCount);
    $stmt->fetch();
    $stmt->close();

    if ($existingCount === 0) {

        // Check if appointment has services at all
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM appointment_services 
            WHERE appointment_id = ?
        ");
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $stmt->bind_result($apptServiceCount);
        $stmt->fetch();
        $stmt->close();

        if ($apptServiceCount > 0) {
            $stmt = $conn->prepare("
                INSERT INTO spa_transaction_services
                (
                    transaction_id,
                    appointment_service_id,
                    service_id,
                    employee_id,
                    quantity,
                    unit_price,
                    total_price
                )
                SELECT
                    ?,
                    aps.id,
                    aps.service_id,
                    aps.employee_id,
                    1,
                    COALESCE(v.price, s.base_price),
                    COALESCE(v.price, s.base_price)
                FROM appointment_services aps
                JOIN services s ON s.id = aps.service_id
                LEFT JOIN service_variants v ON v.id = aps.variant_id
                WHERE aps.appointment_id = ?
            ");
            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $stmt->bind_param("ii", $transaction_id, $appointment_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // =====================
    // TOTALS
    // =====================
    $vatRate = 0.12;

    // SERVICES
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_price), 0)
        FROM spa_transaction_services
        WHERE transaction_id = ?
    ");
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->bind_result($serviceTotal);
    $stmt->fetch();
    $stmt->close();

    // EXTRA PRODUCTS
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_price), 0)
        FROM product_sales
        WHERE transaction_id = ?
    ");
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $stmt->bind_result($extraProductTotal);
    $stmt->fetch();
    $stmt->close();

    $subtotal  = (float)$serviceTotal + (float)$extraProductTotal;
    $vatAmount = $includeVat ? round($subtotal * $vatRate, 2) : 0;
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
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param(
        "dddi",
        $grandTotal,
        $grandTotal,
        $grandTotal,
        $transaction_id
    );
    $stmt->execute();
    $stmt->close();
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

function generateReceiptNumber(mysqli $conn): string
{
    // 🔒 Must be called inside a transaction

    $res = $conn->query("
        SELECT invoice_prefix
        FROM settings
        ORDER BY id ASC
        LIMIT 1
    ");

    $prefix = ($res && $res->num_rows)
        ? $res->fetch_assoc()['invoice_prefix']
        : 'SPA';

    $year = date('Y');

    $stmt = $conn->prepare("
        SELECT
            COALESCE(
                MAX(
                    CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)
                ), 0
            ) AS last_seq
        FROM payments
        WHERE receipt_number LIKE CONCAT(?, '-', ?, '-%')
        FOR UPDATE
    ");

    $stmt->bind_param("ss", $prefix, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $nextSeq = ((int)$row['last_seq']) + 1;

    return sprintf(
        "%s-%s-%06d",
        $prefix,
        $year,
        $nextSeq
    );
}
