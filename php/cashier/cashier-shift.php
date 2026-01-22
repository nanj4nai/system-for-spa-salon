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
   SHIFT SUMMARY (CASHIER)
========================== */
if ($action === 'summary') {

    // Get current open or pending shift
    $stmt = $conn->prepare("
        SELECT id, opened_at, opening_cash
        FROM cashier_shifts
        WHERE user_id = ?
          AND status IN ('open','pending_close')
        ORDER BY opened_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();

    if (!$shift) {
        echo json_encode([
            "success" => false,
            "error" => "No active shift"
        ]);
        exit;
    }

    $shift_id = $shift['id'];

    /* --- Totals --- */
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_transactions,
            SUM(total_amount) AS gross_sales,
            SUM(amount_paid) AS total_paid
        FROM spa_transactions
        WHERE shift_id = ?
          AND status != 'cancelled'
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();

    /* --- Payment breakdown --- */
    $stmt = $conn->prepare("
        SELECT
            p.payment_method,
            SUM(p.amount) AS total
        FROM payments p
        JOIN spa_transactions t ON t.id = p.transaction_id
        WHERE t.shift_id = ?
        GROUP BY p.payment_method
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();

    $payments = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payments[] = $row;
    }

    /* --- Transactions list --- */
    $stmt = $conn->prepare("
        SELECT
            t.transaction_number,
            c.full_name AS client,
            t.total_amount,
            t.amount_paid,
            t.payment_status,
            t.transaction_type,
            t.created_at
        FROM spa_transactions t
        JOIN clients c ON c.id = t.client_id
        WHERE t.shift_id = ?
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "success" => true,
        "shift" => $shift,
        "totals" => $totals,
        "payments" => $payments,
        "transactions" => $transactions
    ]);
    exit;
}
/* ==========================
   CAN CLOSE SHIFT?
========================== */
if ($action === 'can_close') {

    // Get open shift
    $stmt = $conn->prepare("
        SELECT id, opened_at, opening_cash
        FROM cashier_shifts
        WHERE user_id = ?
          AND status = 'open'
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $shift = $stmt->get_result()->fetch_assoc();

    if (!$shift) {
        echo json_encode([
            "success" => false,
            "error" => "No open shift"
        ]);
        exit;
    }

    $shift_id = $shift['id'];

    // 🔎 Check unsettled transactions
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unsettled
        FROM spa_transactions
        WHERE shift_id = ?
          AND status NOT IN ('cancelled', 'locked')
          AND (
                payment_status = 'unpaid'
             OR (payment_status = 'partial' AND is_receivable = 0)
          )
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row['unsettled'] > 0) {
        echo json_encode([
            "success" => true,
            "can_close" => false,
            "unsettled" => (int)$row['unsettled']
        ]);
        exit;
    }

    /* ==========================
       BUILD SUMMARY (IMPORTANT)
    ========================== */

    // Totals
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_transactions,
            SUM(total_amount) AS gross_sales,
            SUM(amount_paid) AS total_paid
        FROM spa_transactions
        WHERE shift_id = ?
          AND status != 'cancelled'
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();

    // Payment breakdown
    $stmt = $conn->prepare("
        SELECT
            p.payment_method,
            SUM(p.amount) AS total
        FROM payments p
        JOIN spa_transactions t ON t.id = p.transaction_id
        WHERE t.shift_id = ?
        GROUP BY p.payment_method
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();

    $payments = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $payments[] = $row;
    }

    echo json_encode([
        "success" => true,
        "can_close" => true,
        "unsettled" => 0,
        "summary" => [
            "totals" => [
                "total_transactions" => (int)($totals['total_transactions'] ?? 0),
                "gross_sales"        => (float)($totals['gross_sales'] ?? 0),
                "total_paid"         => (float)($totals['total_paid'] ?? 0),
            ],
            "payments" => $payments
        ]
    ]);
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
