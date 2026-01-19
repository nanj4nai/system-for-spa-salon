<?php
session_start();
header("Content-Type: application/json");

require_once "../db.php";

/* ==========================
   AUTH CHECK
========================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

/* ==========================
   SHIFT STATUS
========================== */
if ($action === 'status') {

    $stmt = $conn->prepare("
        SELECT id, status, opened_at 
        FROM cashier_shifts
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(["success" => true, "status" => "none"]);
        exit;
    }

    $shift = $res->fetch_assoc();

    echo json_encode([
        "success" => true,
        "status" => $shift['status'],
        "shift"  => $shift
    ]);
    exit;
}

/* ==========================
   OPEN SHIFT
========================== */
if ($action === 'open') {

    $opening_cash = floatval($_POST['opening_cash'] ?? 0);

    // Prevent multiple open shifts
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

    echo json_encode(["success" => $stmt->execute()]);
    exit;
}

/* ==========================
   REQUEST CLOSE SHIFT
========================== */
if ($action === 'request_close') {

    $closing_cash = floatval($_POST['closing_cash'] ?? 0);

    $stmt = $conn->prepare("
        SELECT id FROM cashier_shifts
        WHERE user_id = ? AND status = 'open'
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
        SET closing_cash = ?,
            status = 'pending_close'
        WHERE id = ?
    ");
    $stmt->bind_param("di", $closing_cash, $shift['id']);

    echo json_encode([
        "success" => $stmt->execute(),
        "pending" => true
    ]);
    exit;
}

echo json_encode([
    "success" => false,
    "error" => "Invalid action"
]);
