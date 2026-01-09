<?php
session_start();
header("Content-Type: application/json");

require_once "../db.php";
require_once "helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$client_id = intval($_POST['client_id'] ?? 0);
if ($client_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid client"]);
    exit;
}

$shift_id = getOpenShiftId($conn, $_SESSION['user_id']);
if (!$shift_id) {
    echo json_encode(["success" => false, "error" => "No open shift"]);
    exit;
}

$conn->begin_transaction();

try {
    /* ======================
       1. Create appointment
    ====================== */
    $stmt = $conn->prepare("
        INSERT INTO appointments
        (client_id, appointment_date, start_time, end_time, status)
        VALUES (?, CURDATE(), CURTIME(), ADDTIME(CURTIME(),'01:00:00'), 'confirmed')
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $appointment_id = $stmt->insert_id;

    /* ======================
       2. Create transaction
    ====================== */
    $txn = 'TXN-' . date('Ymd-His') . '-' . rand(100, 999);

    $stmt = $conn->prepare("
        INSERT INTO spa_transactions
        (transaction_number, client_id, appointment_id, shift_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("siii", $txn, $client_id, $appointment_id, $shift_id);
    $stmt->execute();
    $transaction_id = $stmt->insert_id; // ✅ CORRECT PLACE

    $conn->commit();

    echo json_encode([
        "success" => true,
        "appointment_id" => $appointment_id,
        "transaction_id" => $transaction_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => "Failed to create walk-in"
    ]);
}
