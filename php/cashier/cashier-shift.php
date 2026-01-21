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
        ORDER BY opened_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode([
            "success" => true,
            "status"  => "none"
        ]);
        exit;
    }

    $shift = $res->fetch_assoc();

    echo json_encode([
        "success" => true,
        "status"  => $shift['status'],
        "shift"   => [
            "id" => $shift['id'],
            "status" => $shift['status'],
            "approval_status" => $shift['approval_status'] ?? null,
            "remarks" => $shift['remarks'] ?? null,
            "approved_at" => $shift['approved_at'] ?? null
        ]
    ]);

    exit;
}

/* ==========================
   OPEN SHIFT
========================== */
if ($action === 'open') {

    $opening_cash = floatval($_POST['opening_cash'] ?? 0);

    // Prevent open OR pending shift for THIS user
    $check = $conn->prepare("
        SELECT id 
        FROM cashier_shifts 
        WHERE user_id = ?
          AND status IN ('open','pending_close')
        LIMIT 1
    ");
    $check->bind_param("i", $user_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "error"   => "You already have an active or pending shift."
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
        echo json_encode(["success" => true]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Failed to open shift."
        ]);
    }

    exit;
}

/* ==========================
   REQUEST CLOSE SHIFT
========================== */
if ($action === 'request_close') {

    $closing_cash = floatval($_POST['closing_cash'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? null);

    $stmt = $conn->prepare("
        SELECT id 
        FROM cashier_shifts
        WHERE user_id = ?
          AND status = 'open'
          AND is_active = 1
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
        SET
            closing_cash = ?,
            remarks = ?,
            status = 'pending_close'
        WHERE id = ?
    ");
    $stmt->bind_param("dsi", $closing_cash, $remarks, $shift['id']);

    echo json_encode([
        "success" => $stmt->execute()
    ]);
    exit;
}

echo json_encode([
    "success" => false,
    "error"   => "Invalid action"
]);
