<?php
session_start();
header("Content-Type: application/json");

require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$appointment_id = intval($_POST['appointment_id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($appointment_id <= 0 || !in_array($status, ['no_show', 'cancelled'])) {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
    exit;
}

// Fetch appointment
$stmt = $conn->prepare("
    SELECT status, appointment_date
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

// Safety rules
if ($app['appointment_date'] !== date('Y-m-d')) {
    echo json_encode(["success" => false, "error" => "Only today's appointments allowed"]);
    exit;
}

if ($app['status'] !== 'confirmed') {
    echo json_encode(["success" => false, "error" => "Appointment cannot be updated"]);
    exit;
}

// Update
$stmt = $conn->prepare("
    UPDATE appointments
    SET status = ?,
        status_updated_at = NOW(),
        status_updated_by = ?
    WHERE id = ?
");
$stmt->bind_param("sii", $status, $_SESSION['user_id'], $appointment_id);
$stmt->execute();

echo json_encode(["success" => true]);
