<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

/* ================================
   AUTHORIZATION
================================ */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

/* ================================
   INPUT
================================ */
$data = json_decode(file_get_contents("php://input"), true);

$transaction_id  = intval($data['transaction_id'] ?? 0);
$amount          = floatval($data['amount'] ?? 0);
$method          = $data['payment_method'] ?? 'cash';
$reference       = $data['reference_number'] ?? null;
$remarks         = $data['remarks'] ?? null;
$mark_receivable = intval($data['mark_receivable'] ?? 0);

if ($transaction_id <= 0 || $amount <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit;
}

/* ================================
   DB TRANSACTION
================================ */
$conn->begin_transaction();

try {
    // 🔒 Lock transaction row
    $stmt = $conn->prepare("
        SELECT client_id, total_amount, amount_paid
        FROM spa_transactions
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if (!$tx) {
        throw new Exception("Transaction not found");
    }

    $client_id   = (int)$tx['client_id'];
    $totalAmount = (float)$tx['total_amount'];

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM payments
        WHERE transaction_id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $alreadyPaid = (float)$stmt->get_result()->fetch_row()[0];


    $remaining = max(0, $totalAmount - $alreadyPaid);

    if ($remaining <= 0) {
        throw new Exception("Transaction is already fully paid");
    }

    /* ================================
       BUSINESS RULES
       ================================ */
    $method = strtolower(trim($data['payment_method'] ?? 'cash'));

    if ($method !== 'cash' && round($amount, 2) !== round($remaining, 2)) {
        throw new Exception("Non-cash payments must be exact amount");
    }

    if ($method !== 'cash' && empty($reference)) {
        throw new Exception("Reference number is required");
    }

    /* ================================
       CALCULATIONS
    ================================ */
    if ($method === 'cash' && $amount > $remaining) {
        // allow overpayment, change is implied
        $amountApplied = $remaining;
    } else {
        $amountApplied = $amount;
    }

    $newPaid = $alreadyPaid + $amountApplied;
    $balance = max(0, $totalAmount - $newPaid);

    $paymentStatus = ($balance == 0) ? 'paid' : 'partial';

    if ($paymentStatus === 'partial' && $method === 'cash' && $mark_receivable !== 1) {
        throw new Exception(
            "Partial cash payments must be marked as account receivable"
        );
    }

    /* ================================
       INSERT PAYMENT
    ================================ */
    $receiptNumber = generateReceiptNumber($conn);

    $stmt = $conn->prepare("
        INSERT INTO payments
            (
                transaction_id,
                amount,
                payment_method,
                receipt_number,
                reference_number,
                remarks
            )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "idssss",
        $transaction_id,
        $amountApplied,
        $method,
        $receiptNumber,
        $reference,
        $remarks
    );

    $stmt->execute();

    /* ================================
       UPDATE TRANSACTION
    ================================ */
    $isReceivable  = ($paymentStatus === 'partial' && $mark_receivable) ? 1 : 0;
    $hasReceivable = $isReceivable;

    $status = ($paymentStatus === 'paid' || $isReceivable)
        ? 'finalized'
        : 'editing';

    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET
            amount_paid    = ?,
            balance        = ?,
            balance_due    = ?,
            payment_status = ?,
            is_receivable  = ?,
            has_receivable = ?,
            status         = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "dddsiisi",
        $newPaid,
        $balance,
        $balance,
        $paymentStatus,
        $isReceivable,
        $hasReceivable,
        $status,
        $transaction_id
    );
    $stmt->execute();


    /* ================================
    APPLY STAFF COMMISSIONS
    ================================ */
    if ($status === 'finalized') {

        $stmt = $conn->prepare("
            SELECT
                sts.id AS sts_id,
                sts.total_price,
                COALESCE(sc.commission_percent, s.default_commission_percent, 0) AS commission_percent
            FROM spa_transaction_services sts
            JOIN services s ON s.id = sts.service_id
            LEFT JOIN staff_commissions sc
                ON sc.employee_id = sts.employee_id
            AND sc.service_id = sts.service_id
            WHERE sts.transaction_id = ?
            AND sts.commission_amount = 0
        ");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $update = $conn->prepare("
            UPDATE spa_transaction_services
            SET commission_amount = ?
            WHERE id = ?
        ");

        while ($row = $res->fetch_assoc()) {
            $commissionAmount = round(
                $row['total_price'] * ($row['commission_percent'] / 100),
                2
            );

            $update->bind_param("di", $commissionAmount, $row['sts_id']);
            $update->execute();
        }
    }

    /* ================================
        ACCOUNTS RECEIVABLE (PAY LATER)
        =============================== */
    if ($isReceivable) {
        $stmt = $conn->prepare("
                INSERT INTO accounts_receivable
                    (client_id, transaction_id, amount, balance, status, ar_type)
                VALUES (?, ?, ?, ?, 'open', 'pay_later')
                ON DUPLICATE KEY UPDATE
                    balance = VALUES(balance),
                    status  = 'open',
                    ar_type = 'pay_later'
            ");
        $stmt->bind_param(
            "iidd",
            $client_id,
            $transaction_id,
            $totalAmount,
            $balance
        );
        $stmt->execute();
    }


    // ✅ COMMIT ONLY ONCE EVERYTHING SUCCEEDS
    $conn->commit();

    echo json_encode([
        "success" => true,
        "receipt_number" => $receiptNumber,
        "payment_status" => $paymentStatus,
        "balance" => $balance,
        "is_receivable" => $isReceivable,
        "reference" => $reference
    ]);
} catch (Throwable $e) {
    $conn->rollback();

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
