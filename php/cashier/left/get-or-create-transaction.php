<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$appointment_id = intval($_POST['appointment_id'] ?? 0);
if ($appointment_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid appointment"]);
    exit;
}

// Validate appointment
$stmt = $conn->prepare("
    SELECT id, client_id, status, appointment_date
    FROM appointments
    WHERE id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    echo json_encode(["success" => false, "error" => "Appointment not found"]);
    exit;
}

if ($app['appointment_date'] !== date('Y-m-d')) {
    echo json_encode([
        "success" => false,
        "error" => "Appointment is not scheduled for today"
    ]);
    exit;
}

if ($app['status'] !== 'checked_in') {
    echo json_encode([
        "success" => false,
        "error" => "Client not checked in"
    ]);
    exit;
}

// Get open shift
$shift_id = getOpenShiftId($conn, $_SESSION['user_id']);
if (!$shift_id) {
    echo json_encode(["success" => false, "error" => "No open shift"]);
    exit;
}

// Check existing transaction
$stmt = $conn->prepare("
    SELECT id
    FROM spa_transactions
    WHERE appointment_id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    echo json_encode([
        "success" => true,
        "existing" => true,
        "transaction_id" => $existing['id']
    ]);
    exit;
}
// Create transaction
$txn = 'TXN-' . date('Ymd-His') . '-' . rand(100, 999);

$stmt = $conn->prepare("
    INSERT INTO spa_transactions
    (transaction_number, client_id, appointment_id, shift_id)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param(
    "siii",
    $txn,
    $app['client_id'],
    $appointment_id,
    $shift_id
);
$stmt->execute();

echo json_encode([
    "success" => true,
    "existing" => false,
    "transaction_id" => $stmt->insert_id
]);
