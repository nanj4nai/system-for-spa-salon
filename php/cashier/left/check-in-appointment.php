<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$appointment_id = intval($_POST['appointment_id'] ?? 0);
if ($appointment_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid appointment"]);
    exit;
}

// Get appointment
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

// Safety checks
if ($app['appointment_date'] !== date('Y-m-d')) {
    echo json_encode(["success" => false, "error" => "Not today's appointment"]);
    exit;
}

if ($app['status'] !== 'confirmed') {
    echo json_encode(["success" => false, "error" => "Appointment not confirmable"]);
    exit;
}

// Check in
$stmt = $conn->prepare("
    UPDATE appointments
    SET
        status = 'checked_in',
        checked_in_at = NOW(),
        checked_in_by = ?,
        status_updated_at = NOW(),
        status_updated_by = ?
    WHERE id = ?
");
$stmt->bind_param(
    "iii",
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $appointment_id
);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "error" => "Failed to check in appointment"
    ]);
    exit;
}

/* OPTIONAL BUT HIGHLY RECOMMENDED */
$stmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action, description)
    VALUES (?, 'appointment_checkin', ?)
");
$desc = "Checked in appointment ID {$appointment_id}";
$stmt->bind_param("is", $_SESSION['user_id'], $desc);
$stmt->execute();

echo json_encode(["success" => true]);
