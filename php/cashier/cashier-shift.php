<?php
session_start();
header("Content-Type: application/json");

require_once "../db.php";

/* ==========================
   AUTH CHECK
========================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

/* ==========================
   CHECK OPEN SHIFT
========================== */
if ($action === 'status') {

    $res = $conn->query("
        SELECT id, opened_at 
        FROM cashier_shifts 
        WHERE status = 'open'
        LIMIT 1
    ");

    if ($res->num_rows > 0) {
        $shift = $res->fetch_assoc();
        echo json_encode([
            "success" => true,
            "open" => true,
            "shift" => $shift
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "open" => false
        ]);
    }
    exit;
}

/* ==========================
   OPEN SHIFT
========================== */
if ($action === 'open') {

    $opening_cash = floatval($_POST['opening_cash'] ?? 0);

    // Prevent double shift
    $check = $conn->query("
        SELECT id FROM cashier_shifts 
        WHERE status = 'open' 
        LIMIT 1
    ");

    if ($check->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "error" => "A shift is already open."
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO cashier_shifts 
        (user_id, opened_at, opening_cash, status)
        VALUES (?, NOW(), ?, 'open')
    ");
    $stmt->bind_param("id", $user_id, $opening_cash);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Failed to open shift"
        ]);
    }
    exit;
}

/* ==========================
   CLOSE SHIFT
========================== */
if ($action === 'close') {

    $closing_cash = floatval($_POST['closing_cash'] ?? 0);

    // Find open shift for THIS cashier
    $stmt = $conn->prepare("
        SELECT id FROM cashier_shifts
        WHERE status = 'open' AND user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "error" => "No open shift found."
        ]);
        exit;
    }

    $shift = $res->fetch_assoc();

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET closed_at = NOW(),
            closing_cash = ?,
            status = 'closed'
        WHERE id = ?
    ");
    $stmt->bind_param("di", $closing_cash, $shift['id']);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Failed to close shift"
        ]);
    }
    exit;
}


echo json_encode([
    "success" => false,
    "error" => "Invalid action"
]);
