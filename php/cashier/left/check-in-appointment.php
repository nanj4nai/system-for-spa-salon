<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

/* =====================
   AUTH
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

/* =====================
   INPUT
===================== */
$appointment_id = intval($_POST['appointment_id'] ?? 0);
if ($appointment_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid appointment"]);
    exit;
}

/* =====================
   FETCH + VALIDATE (DB-SIDE DATE CHECK)
===================== */
$stmt = $conn->prepare("
    SELECT
        status
    FROM appointments
    WHERE id = ?
      AND appointment_date = CURDATE()
    LIMIT 1
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    echo json_encode([
        "success" => false,
        "error" => "Appointment not found or not scheduled for today"
    ]);
    exit;
}

/* =====================
   STATUS GUARDS
===================== */

// already checked in
if ($app['status'] === 'checked_in') {
    echo json_encode([
        "success" => false,
        "error" => "Appointment already checked in"
    ]);
    exit;
}

// must be confirmed
if ($app['status'] !== 'confirmed') {
    echo json_encode([
        "success" => false,
        "error" => "Only confirmed appointments can be checked in"
    ]);
    exit;
}

/* =====================
   CHECK-IN (SAFE UPDATE)
===================== */
$stmt = $conn->prepare("
    UPDATE appointments
    SET
        status = 'checked_in',
        checked_in_at = NOW(),
        checked_in_by = ?,
        status_updated_at = NOW(),
        status_updated_by = ?
    WHERE id = ?
      AND status = 'confirmed'
");

$userId = $_SESSION['user_id'];
$stmt->bind_param("iii", $userId, $userId, $appointment_id);
$stmt->execute();

/* 🔒 CRITICAL: ensure something actually changed */
if ($stmt->affected_rows !== 1) {
    echo json_encode([
        "success" => false,
        "error" => "Check-in failed or appointment already processed"
    ]);
    exit;
}

/* =====================
   ACTIVITY LOG (NON-BLOCKING)
===================== */
$log = $conn->prepare("
    INSERT INTO activity_logs (user_id, action, description)
    VALUES (?, 'appointment_checkin', ?)
");
$desc = "Checked in appointment ID {$appointment_id}";
$log->bind_param("is", $userId, $desc);
$log->execute();

/* =====================
   DONE
===================== */
echo json_encode(["success" => true]);
