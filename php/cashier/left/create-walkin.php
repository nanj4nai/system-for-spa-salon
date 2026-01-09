<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$name = trim($_POST['name'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$client_id = intval($_POST['client_id'] ?? 0);

if ($name === '') {
    echo json_encode(["success" => false, "error" => "Client name required"]);
    exit;
}

$shift_id = getOpenShiftId($conn, $_SESSION['user_id']);
if (!$shift_id) {
    echo json_encode(["success" => false, "error" => "No open shift"]);
    exit;
}

$conn->begin_transaction();

try {
    /* =====================
       RESOLVE CLIENT
    ===================== */

    // 1️⃣ If client_id explicitly provided
    if ($client_id > 0) {
        $stmt = $conn->prepare("SELECT id FROM clients WHERE id = ?");
        $stmt->bind_param("i", $client_id);
        $stmt->execute();

        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception("Client not found");
        }
    }

    // 2️⃣ Match by contact
    if (!$client_id && $contact !== '') {
        $stmt = $conn->prepare("
            SELECT id FROM clients
            WHERE contact_number = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $contact);
        $stmt->execute();

        if ($row = $stmt->get_result()->fetch_assoc()) {
            $client_id = $row['id'];
        }
    }

    // 3️⃣ Match by exact name
    if (!$client_id) {
        $stmt = $conn->prepare("
            SELECT id FROM clients
            WHERE full_name = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $name);
        $stmt->execute();

        if ($row = $stmt->get_result()->fetch_assoc()) {
            $client_id = $row['id'];
        }
    }

    // 4️⃣ Create new client if still none
    if (!$client_id) {
        $stmt = $conn->prepare("
            INSERT INTO clients (full_name, contact_number)
            VALUES (?, ?)
        ");
        $stmt->bind_param("ss", $name, $contact);
        $stmt->execute();
        $client_id = $stmt->insert_id;
    }

    /* =====================
       PREVENT DUPLICATE APPOINTMENT
    ===================== */
    $stmt = $conn->prepare("
        SELECT id FROM appointments
        WHERE client_id = ?
          AND appointment_date = CURDATE()
          AND status IN ('confirmed', 'checked_in')
        LIMIT 1
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();

    if ($stmt->get_result()->fetch_assoc()) {
        throw new Exception("Client already has an active appointment today");
    }

    /* =====================
       CREATE APPOINTMENT
    ===================== */
    $stmt = $conn->prepare("
        INSERT INTO appointments
        (client_id, appointment_date, start_time, end_time, status, source)
        VALUES (?, CURDATE(), CURTIME(), ADDTIME(CURTIME(),'01:00:00'), 'checked_in', 'cashier')
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $appointment_id = $stmt->insert_id;

    /* =====================
       CREATE TRANSACTION
    ===================== */
    $txn = 'TXN-' . date('Ymd-His') . '-' . rand(100, 999);
    $stmt = $conn->prepare("
        INSERT INTO spa_transactions
        (transaction_number, client_id, appointment_id, shift_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("siii", $txn, $client_id, $appointment_id, $shift_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "appointment_id" => $appointment_id,
        "transaction_id" => $stmt->insert_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
