<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
function generateReceiptNumber(mysqli $conn): string
{
    // Get prefix from settings
    $res = $conn->query("
        SELECT invoice_prefix
        FROM settings
        ORDER BY id ASC
        LIMIT 1
    ");

    $prefix = $res && $res->num_rows
        ? $res->fetch_assoc()['invoice_prefix']
        : 'SPA';

    $year = date('Y');

    // Lock receipt generation (critical)
    $stmt = $conn->prepare("
        SELECT
            MAX(
                CAST(
                    SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED
                )
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
    $alreadyPaid = (float)$tx['amount_paid'];

    $remaining = max(0, $totalAmount - $alreadyPaid);

    /* ================================
       BUSINESS RULES
    ================================ */
    if ($method !== 'cash' && $amount != $remaining) {
        throw new Exception("Non-cash payments must be exact amount");
    }

    if ($method !== 'cash' && empty($reference)) {
        throw new Exception("Reference number is required");
    }

    /* ================================
       CALCULATIONS
    ================================ */
    $newPaid = $alreadyPaid + $amount;
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
        (transaction_id, amount, payment_method, receipt_number)
    VALUES (?, ?, ?, ?)
");
    $stmt->bind_param("idss", $transaction_id, $amount, $method, $receiptNumber);
    $stmt->execute();


    /* ================================
       UPDATE TRANSACTION
    ================================ */
    $isReceivable  = ($paymentStatus === 'partial' && $mark_receivable) ? 1 : 0;
    $hasReceivable = $isReceivable;

    $status = ($paymentStatus === 'paid' || $isReceivable)
        ? 'locked'
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
       ACCOUNTS RECEIVABLE
    ================================ */
    if ($isReceivable) {
        $stmt = $conn->prepare("
            INSERT INTO accounts_receivable
                (client_id, transaction_id, amount, balance, status)
            VALUES (?, ?, ?, ?, 'open')
            ON DUPLICATE KEY UPDATE
                balance = VALUES(balance),
                status  = 'open'
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
        "is_receivable" => $isReceivable
    ]);
} catch (Throwable $e) {
    $conn->rollback();

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
