<?php
session_start();
header("Content-Type: application/json");
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$transaction_id = intval($_POST['transaction_id'] ?? 0);
$service_id = intval($_POST['service_id'] ?? 0);
$employee_id = intval($_POST['employee_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);
$unit_price = floatval($_POST['unit_price'] ?? 0);

if (!$transaction_id || !$service_id || !$employee_id || $unit_price <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit;
}

/* Get commission percent */
$stmt = $conn->prepare("
    SELECT COALESCE(sc.commission_percent, s.default_commission_percent) AS commission
    FROM services s
    LEFT JOIN staff_commissions sc
        ON sc.service_id = s.id AND sc.employee_id = ?
    WHERE s.id = ?
");
$stmt->bind_param("ii", $employee_id, $service_id);
$stmt->execute();
$commission_percent = $stmt->get_result()->fetch_assoc()['commission'] ?? 0;

/* Compute */
$total_price = $unit_price * $quantity;
$commission_amount = ($commission_percent / 100) * $total_price;

/* Insert service line */
$stmt = $conn->prepare("
    INSERT INTO spa_transaction_services
        (transaction_id, service_id, employee_id, quantity, unit_price, total_price, commission_amount)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "iiiiddd",
    $transaction_id,
    $service_id,
    $employee_id,
    $quantity,
    $unit_price,
    $total_price,
    $commission_amount
);
$stmt->execute();

/* Update transaction total */
$conn->query("
    UPDATE spa_transactions
    SET total_amount = (
        SELECT IFNULL(SUM(total_price),0)
        FROM spa_transaction_services
        WHERE transaction_id = $transaction_id
    ),
    balance = total_amount - amount_paid
    WHERE id = $transaction_id
");

echo json_encode(["success" => true]);
