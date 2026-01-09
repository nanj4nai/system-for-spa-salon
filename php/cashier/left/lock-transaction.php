<?php
session_start();
header("Content-Type: application/json");
require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$transaction_id = intval($data['transaction_id'] ?? 0);

if (!$transaction_id) {
    echo json_encode(["success" => false, "error" => "Invalid transaction"]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE spa_transactions
    SET status = 'locked'
    WHERE id = ? AND status = 'editing'
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();

echo json_encode(["success" => true]);
