<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$transaction_id = intval($_GET['transaction_id'] ?? 0);
if ($transaction_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid transaction"]);
    exit;
}

/* ================================
   LOAD TRANSACTION STATE
================================ */
$stmt = $conn->prepare("
    SELECT
        payment_status,
        status,
        is_receivable
    FROM spa_transactions
    WHERE id = ?
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();

if (!$tx) {
    echo json_encode(["success" => false, "error" => "Transaction not found"]);
    exit;
}

/* ================================
   LOAD LAST PAYMENT METHOD
================================ */
$stmt = $conn->prepare("
    SELECT payment_method
    FROM payments
    WHERE transaction_id = ?
    ORDER BY payment_date DESC, id DESC
    LIMIT 1
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$res = $stmt->get_result();

$payment_method = null;
if ($row = $res->fetch_assoc()) {
    $payment_method = $row['payment_method'];
}

/* ================================
   RESPONSE
================================ */
echo json_encode([
    "success"        => true,
    "payment_status" => $tx['payment_status'], // unpaid | partial | paid
    "status"        => $tx['status'],         // editing | locked
    "is_receivable" => (int)$tx['is_receivable'],
    "payment_method" => $payment_method
]);
