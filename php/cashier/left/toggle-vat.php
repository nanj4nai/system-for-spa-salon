<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$transaction_id = intval($data['transaction_id'] ?? 0);
$include_vat    = isset($data['include_vat']) ? (int)$data['include_vat'] : -1;

if (!$transaction_id || !in_array($include_vat, [0, 1], true)) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit;
}

// prevent VAT change on locked / paid
$stmt = $conn->prepare("
    SELECT status
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

if ($tx['status'] !== 'editing') {
    echo json_encode(["success" => false, "error" => "Transaction already locked"]);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET include_vat = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $include_vat, $transaction_id);
    $stmt->execute();

    recalcTransaction($conn, $transaction_id);

    $conn->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
