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
        SELECT
            id,
            status,
            active_user_id,
            approval_status,
            remarks,
            approved_at
        FROM cashier_shifts
        WHERE user_id = ?
          AND status IN ('pending_open','open','pending_close')
        ORDER BY opened_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        echo json_encode([
            "success"  => true,
            "ui_state" => "blocked",
            "reason"   => "Shift gate not opened by admin"
        ]);
        exit;
    }

    $shift = $res->fetch_assoc();

    /* 🔐 HARD LOCK — another terminal */
    if (
        $shift['active_user_id'] !== null &&
        (int)$shift['active_user_id'] !== (int)$user_id
    ) {
        echo json_encode([
            "success"  => true,
            "ui_state" => "blocked",
            "reason"   => "Shift already active on another terminal"
        ]);
        exit;
    }

    /* 🟦 ADMIN OPENED → WAIT FOR OPENING CASH */
    if ($shift['status'] === 'pending_open') {
        echo json_encode([
            "success"  => true,
            "ui_state" => "awaiting_open",
            "shift"    => [
                "id" => $shift['id']
            ]
        ]);
        exit;
    }

    /* 🟩 ACTIVE SHIFT */
    if ($shift['status'] === 'open') {
        echo json_encode([
            "success"  => true,
            "ui_state" => "open",
            "shift"    => $shift
        ]);
        exit;
    }

    /* 🟨 PENDING CLOSE */
    if ($shift['status'] === 'pending_close') {
        echo json_encode([
            "success"  => true,
            "ui_state" => "pending_close",
            "shift"    => $shift
        ]);
        exit;
    }

    echo json_encode([
        "success"  => true,
        "ui_state" => "blocked"
    ]);
    exit;
}
/* ==========================
   OPEN SHIFT (CASHIER START)
========================== */

if ($action === 'open') {

    $opening_cash = floatval($_POST['opening_cash'] ?? 0);

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET
            opening_cash = ?,
            status = 'open',
            approval_status = 'approved',
            is_active = 1,
            active_user_id = ?
        WHERE user_id = ?
          AND status = 'pending_open'
          AND active_user_id IS NULL
        LIMIT 1
    ");

    $stmt->bind_param("dii", $opening_cash, $user_id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows === 1) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Shift already claimed or not available"
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

    /* --- Accounts Receivable --- */
    $stmt = $conn->prepare("
        SELECT
            SUM(ar.balance) AS total_receivable
        FROM accounts_receivable ar
        JOIN spa_transactions t ON t.id = ar.transaction_id
        WHERE t.shift_id = ?
        AND ar.status = 'open'
        AND ar.ar_type = 'pay_later'
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $receivable = $stmt->get_result()->fetch_assoc();

    /* --- Staff Commissions --- */
    $stmt = $conn->prepare("
    SELECT
        e.full_name,
        SUM(sts.commission_amount) AS total_commission
    FROM spa_transaction_services sts
    JOIN employees e ON e.id = sts.employee_id
    JOIN spa_transactions t ON t.id = sts.transaction_id
    WHERE t.shift_id = ?
    GROUP BY e.id
");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();

    $commissions = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $commissions[] = $row;
    }

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
        "transactions" => $transactions,
        "receivable" => (float)($receivable['total_receivable'] ?? 0),
        "commissions" => $commissions
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
        AND status != 'finalized'
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
