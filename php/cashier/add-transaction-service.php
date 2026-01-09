<?php
session_start();
header("Content-Type: application/json");
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$transaction_id = intval($_POST['transaction_id'] ?? 0);
$service_id     = intval($_POST['service_id'] ?? 0);
$employee_id    = intval($_POST['employee_id'] ?? 0);
$qty            = max(1, intval($_POST['quantity'] ?? 1));

if (!$transaction_id || !$service_id || !$employee_id) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit;
}

// Get price
$stmt = $conn->prepare("SELECT base_price FROM services WHERE id=?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$price = $stmt->get_result()->fetch_assoc()['base_price'];

$total = $price * $qty;

$conn->begin_transaction();

try {
    // Insert service
    $stmt = $conn->prepare("
        INSERT INTO spa_transaction_services
        (transaction_id, service_id, employee_id, quantity, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iiiidd",
        $transaction_id,
        $service_id,
        $employee_id,
        $qty,
        $price,
        $total
    );
    $stmt->execute();

    // Update total
    $conn->query("
        UPDATE spa_transactions
        SET total_amount = total_amount + $total
        WHERE id = $transaction_id
    ");

    $conn->commit();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "error" => "Failed to add service"]);
}
