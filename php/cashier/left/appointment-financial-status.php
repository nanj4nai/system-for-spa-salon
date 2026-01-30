<?php
session_start();
header("Content-Type: application/json");
require_once "../../db.php";
require_once "../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$appointment_id = intval($_GET['appointment_id'] ?? 0);
if ($appointment_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid appointment"]);
    exit;
}

/* =====================
   FIND TRANSACTION ID
===================== */
$stmt = $conn->prepare("
    SELECT id
    FROM spa_transactions
    WHERE appointment_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode([
        "success" => true,
        "has_transaction" => false,
        "payment_status" => "unpaid",
        "amount_paid" => 0,
        "total_amount" => 0,
        "refundable" => false
    ]);
    exit;
}

$transaction_id = (int)$row['id'];

/* =====================
   ALWAYS RECALC
===================== */
recalcTransaction($conn, $transaction_id);

/* =====================
   LOAD FRESH TOTALS
===================== */
$stmt = $conn->prepare("
    SELECT
        status,
        total_amount,
        amount_paid,
        payment_status,
        is_receivable
    FROM spa_transactions
    WHERE id = ?
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

$refundable = $txn['amount_paid'] > 0 && $txn['payment_status'] !== 'refunded';

echo json_encode([
    "success" => true,
    "has_transaction" => true,
    "transaction_id" => $transaction_id,
    "status" => $txn['status'],
    "payment_status" => $txn['payment_status'],
    "is_receivable" => (int)$txn['is_receivable'],
    "amount_paid" => (float)$txn['amount_paid'],
    "total_amount" => (float)$txn['total_amount'],
    "refundable" => $refundable
]);
