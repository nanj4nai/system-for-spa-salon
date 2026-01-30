<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

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
   FETCH + VALIDATE
===================== */
$stmt = $conn->prepare("
    SELECT status
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

if ($app['status'] === 'checked_in') {
    echo json_encode([
        "success" => false,
        "error" => "Appointment already checked in"
    ]);
    exit;
}

if ($app['status'] !== 'confirmed') {
    echo json_encode([
        "success" => false,
        "error" => "Only confirmed appointments can be checked in"
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$shift_id = getOpenShiftId($conn, $userId);

if (!$shift_id) {
    echo json_encode(["success" => false, "error" => "No open shift"]);
    exit;
}


/* =====================
   CHECK-IN
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


$stmt->bind_param("iii", $userId, $userId, $appointment_id);
$stmt->execute();

if ($stmt->affected_rows !== 1) {
    echo json_encode([
        "success" => false,
        "error" => "Check-in failed"
    ]);
    exit;
}

$transactionId = null;
/* =====================
   ENSURE TRANSACTION (MANDATORY)
===================== */
$stmt = $conn->prepare("
    SELECT id
    FROM spa_transactions
    WHERE appointment_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if ($txn) {
    $transactionId = (int)$txn['id'];

    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET shift_id = ?
        WHERE id = ? AND shift_id IS NULL
    ");
    $stmt->bind_param("ii", $shift_id, $transactionId);
    $stmt->execute();
} else {
    // 🆕 CREATE transaction WITH shift ownership
    $txnNumber = 'TXN-' . date('Ymd-His') . '-' . rand(100, 999);

    $stmt = $conn->prepare("
    INSERT INTO spa_transactions (
        transaction_number,
        client_id,
        appointment_id,
        shift_id,
        transaction_type,
        status,
        include_vat,
        is_receivable,
        payment_status,
        amount_paid
    )
    SELECT
        ?,
        a.client_id,
        a.id,
        ?,
        'walkin',
        'editing',
        1,
        0,
        'unpaid',
        0.00
    FROM appointments a
    WHERE a.id = ?
");
    $stmt->bind_param("sii", $txnNumber, $shift_id, $appointment_id);
    $stmt->execute();

    $transactionId = $stmt->insert_id;
}

/* =====================
   🔥 SEED SERVICES + TOTALS
===================== */
if ($transactionId) {
    recalcTransaction($conn, $transactionId);
}

/* =====================
   ACTIVITY LOG
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
