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

// Get transaction payment status
$stmt = $conn->prepare("
    SELECT payment_status
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

// Get latest payment (if any)
$stmt = $conn->prepare("
    SELECT payment_method
    FROM payments
    WHERE transaction_id = ?
    ORDER BY payment_date DESC
    LIMIT 1
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$res = $stmt->get_result();

$payment_method = null;
if ($row = $res->fetch_assoc()) {
    $payment_method = $row['payment_method'];
}

echo json_encode([
    "success" => true,
    "payment_status" => $tx['payment_status'], // unpaid | partial | paid
    "payment_method" => $payment_method
]);
