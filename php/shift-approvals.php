<?php
session_start();
header("Content-Type: application/json");
require_once "../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$action = $_POST['action'] ?? '';

/* ==========================
   FETCH PENDING SHIFTS
========================== */
if ($action === 'list') {

    $res = $conn->query("
        SELECT 
            cs.id,
            cs.opened_at,
            cs.opening_cash,
            cs.closing_cash,
            cs.status,
            u.username
        FROM cashier_shifts cs
        JOIN users u ON cs.user_id = u.id
        WHERE cs.status = 'pending_close'
        ORDER BY cs.opened_at ASC
    ");

    echo json_encode([
        "success" => true,
        "shifts" => $res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}

/* ==========================
   APPROVE SHIFT
========================== */
if ($action === 'approve') {

    $shift_id = intval($_POST['shift_id'] ?? 0);
    $admin_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET status = 'closed',
            closed_at = NOW(),
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ? AND status = 'pending_close'
    ");
    $stmt->bind_param("ii", $admin_id, $shift_id);

    echo json_encode(["success" => $stmt->execute()]);
    exit;
}

/* ==========================
   REJECT SHIFT
========================== */
if ($action === 'reject') {

    $shift_id = intval($_POST['shift_id'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET status = 'open'
        WHERE id = ? AND status = 'pending_close'
    ");
    $stmt->bind_param("i", $shift_id);

    echo json_encode(["success" => $stmt->execute()]);
    exit;
}

echo json_encode(["success" => false, "error" => "Invalid action"]);
