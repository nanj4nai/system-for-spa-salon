<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

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
            cs.remarks,
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
   FETCH close SHIFTS
========================== */
if ($action === 'list_closed') {
    $res = $conn->query("
        SELECT 
            cs.id,
            cs.opened_at,
            cs.closed_at,
            cs.opening_cash,
            cs.closing_cash,
            u.username
        FROM cashier_shifts cs
        JOIN users u ON cs.user_id = u.id
        WHERE cs.status = 'closed'
        ORDER BY cs.closed_at DESC
        LIMIT 50
    ");

    echo json_encode([
        "success" => true,
        "shifts" => $res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}

/* ==========================
   FETCH active SHIFTS
========================== */
if ($action === 'list_active') {
    $res = $conn->query("
        SELECT 
            cs.id,
            cs.opened_at,
            cs.opening_cash,
            u.username
        FROM cashier_shifts cs
        JOIN users u ON cs.user_id = u.id
        WHERE cs.status = 'open'
        ORDER BY cs.opened_at ASC
    ");

    echo json_encode([
        "success" => true,
        "shifts" => $res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}


/* ==========================
   FETCH PENDING SHIFTS
========================== */
if ($action === 'list_pending') {
    $res = $conn->query("
        SELECT 
            cs.id,
            cs.opened_at,
            cs.opening_cash,
            cs.closing_cash,
            cs.remarks,
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

    $shift_id = intval($_POST['shift_id']);
    $admin_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET
            status = 'closed',
            is_active = 0,
            approval_status = 'approved',
            closed_at = NOW(),
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ?
          AND status = 'pending_close'
          AND is_active = 1
    ");

    $stmt->bind_param("ii", $admin_id, $shift_id);
    $stmt->execute();

    echo json_encode([
        "success" => $stmt->affected_rows === 1
    ]);
    exit;
}

/* ==========================
   force close SHIFT
========================== */
if ($action === 'force_close') {
    $shift_id = intval($_POST['shift_id'] ?? 0);
    $admin_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET status = 'closed',
            closed_at = NOW(),
            is_active = 0,
            remarks = 'Force closed by admin',
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ? AND status != 'closed'
    ");
    $stmt->bind_param("ii", $admin_id, $shift_id);

    echo json_encode([
        "success" => $stmt->execute()
    ]);
    exit;
}
/* ==========================
   suimmary
========================== */

if ($action === 'summary') {
    $shift_id = intval($_POST['shift_id']);

    $stmt = $conn->prepare("
        SELECT
            cs.opening_cash,
            cs.closing_cash,
            COALESCE(SUM(
                CASE
                    WHEN p.payment_method = 'cash'
                     AND t.transaction_type = 'walkin'
                    THEN p.amount
                    ELSE 0
                END
            ), 0) AS cash_sales
        FROM cashier_shifts cs
        LEFT JOIN spa_transactions t ON t.shift_id = cs.id
        LEFT JOIN payments p ON p.transaction_id = t.id
        WHERE cs.id = ?
        GROUP BY cs.id
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    $expected = $summary['opening_cash'] + $summary['cash_sales'];
    $variance = $summary['closing_cash'] - $expected;

    echo json_encode([
        "success" => true,
        "summary" => [
            "opening_cash" => $summary['opening_cash'],
            "cash_sales" => $summary['cash_sales'],
            "expected_cash" => $expected,
            "closing_cash" => $summary['closing_cash'],
            "variance" => $variance
        ]
    ]);
    exit;
}


/* ==========================
   FETCH SHIFT TRANSACTIONS
========================== */
if ($action === 'transactions') {

    $shift_id = intval($_POST['shift_id']);

    $stmt = $conn->prepare("
        SELECT
            t.id,
            t.transaction_number,
            t.transaction_type,
            t.total_amount,

            COALESCE(SUM(p.amount), 0) AS total_paid,

            (t.total_amount - COALESCE(SUM(p.amount), 0)) AS balance_due,

            c.full_name AS client_name,

            t.created_at
        FROM spa_transactions t
        LEFT JOIN payments p ON p.transaction_id = t.id
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.shift_id = ?
        GROUP BY t.id
        ORDER BY t.created_at ASC
    ");

    $stmt->bind_param("i", $shift_id);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "transactions" => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}

/* ==========================
   REJECT SHIFT
========================== */
if ($action === 'reject') {

    $shift_id = intval($_POST['shift_id']);

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET
            status = 'open',
            is_active = 1,
            closing_cash = NULL,
            approval_status = NULL,
            approved_by = NULL,
            approved_at = NULL
        WHERE id = ?
          AND status = 'pending_close'
    ");

    $stmt->bind_param("i", $shift_id);
    $stmt->execute();

    echo json_encode([
        "success" => $stmt->affected_rows === 1
    ]);
    exit;
}

if ($action === 'transaction_details') {

    $transaction_id = intval($_POST['transaction_id']);

    // MAIN TRANSACTION
    $stmt = $conn->prepare("
        SELECT
            t.transaction_number,
            t.payment_status,
            t.balance_due,
            c.full_name AS client_name
        FROM spa_transactions t
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();

    // ACCOUNTS RECEIVABLE (if any)
    $arStmt = $conn->prepare("
    SELECT
        id,
        amount,
        balance,
        status,
        remarks,
        created_at
    FROM accounts_receivable
    WHERE transaction_id = ?
    LIMIT 1
");
    $arStmt->bind_param("i", $transaction_id);
    $arStmt->execute();
    $ar = $arStmt->get_result()->fetch_assoc();

    $arPayments = [];

    if ($ar) {
        $stmt = $conn->prepare("
        SELECT
            amount,
            payment_date,
            remarks
        FROM ar_payments
        WHERE receivable_id = ?
        ORDER BY payment_date ASC
    ");
        $stmt->bind_param("i", $ar['id']);
        $stmt->execute();
        $arPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }


    // SERVICES
    $services = $conn->query("
        SELECT
            s.name AS service_name,
            e.full_name AS staff_name,
            ts.quantity,
            ts.unit_price,
            ts.total_price
        FROM spa_transaction_services ts
        JOIN services s ON s.id = ts.service_id
        JOIN employees e ON e.id = ts.employee_id
        WHERE ts.transaction_id = $transaction_id
    ")->fetch_all(MYSQLI_ASSOC);

    // PRODUCTS
    $products = $conn->query("
        SELECT
            p.name AS product_name,
            ps.quantity,
            ps.unit_price,
            ps.total_price
        FROM product_sales ps
        JOIN products p ON p.id = ps.product_id
        WHERE ps.transaction_id = $transaction_id
    ")->fetch_all(MYSQLI_ASSOC);

    // PAYMENTS
    $payments = $conn->query("
        SELECT payment_method, amount, payment_date
        FROM payments
        WHERE transaction_id = $transaction_id
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "success" => true,
        "transaction" => $transaction,
        "services" => $services,
        "products" => $products,
        "payments" => $payments,
        "receivable" => $ar,
        "ar_payments" => $arPayments   // 👈 ADD THIS
    ]);
    exit;
}

if ($action === 'apply_ar_payment') {

    $receivable_id = intval($_POST['receivable_id']);
    $amount = floatval($_POST['amount']);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($amount <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid payment amount"]);
        exit;
    }

    // 1. Fetch receivable
    $stmt = $conn->prepare("
        SELECT
            ar.id,
            ar.transaction_id,
            ar.balance
        FROM accounts_receivable ar
        WHERE ar.id = ?
          AND ar.status = 'open'
    ");
    $stmt->bind_param("i", $receivable_id);
    $stmt->execute();
    $ar = $stmt->get_result()->fetch_assoc();

    if (!$ar) {
        echo json_encode(["success" => false, "error" => "Receivable not found or already paid"]);
        exit;
    }

    if ($amount > $ar['balance']) {
        echo json_encode(["success" => false, "error" => "Payment exceeds balance"]);
        exit;
    }

    // 2. Insert A/R payment
    $stmt = $conn->prepare("
        INSERT INTO ar_payments (receivable_id, amount, remarks)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("ids", $receivable_id, $amount, $remarks);
    $stmt->execute();

    // 3. Update receivable balance
    $newBalance = $ar['balance'] - $amount;
    $newStatus = $newBalance <= 0 ? 'paid' : 'open';

    $stmt = $conn->prepare("
        UPDATE accounts_receivable
        SET balance = ?, status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("dsi", $newBalance, $newStatus, $receivable_id);
    $stmt->execute();

    // 4. If fully paid → update transaction
    if ($newBalance <= 0) {
        $stmt = $conn->prepare("
            UPDATE spa_transactions
            SET payment_status = 'paid'
            WHERE id = ?
        ");
        $stmt->bind_param("i", $ar['transaction_id']);
        $stmt->execute();
    }

    echo json_encode(["success" => true]);
    exit;
}

if ($action === 'mark_ar') {

    $transaction_id = intval($_POST['transaction_id']);
    $remarks = trim($_POST['remarks'] ?? '');

    // 🔒 0. Prevent double A/R
    $stmt = $conn->prepare("
        SELECT 1 FROM accounts_receivable WHERE transaction_id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "error" => "Transaction already marked as A/R"
        ]);
        exit;
    }

    // 1. Get transaction + balance
    $stmt = $conn->prepare("
        SELECT
            t.client_id,
            t.total_amount,
            COALESCE(SUM(p.amount), 0) AS total_paid
        FROM spa_transactions t
        LEFT JOIN payments p ON p.transaction_id = t.id
        WHERE t.id = ?
        GROUP BY t.id
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if (!$tx || !$tx['client_id']) {
        echo json_encode(["success" => false, "error" => "Client required for A/R"]);
        exit;
    }

    $balance = $tx['total_amount'] - $tx['total_paid'];

    if ($balance <= 0) {
        echo json_encode(["success" => false, "error" => "No balance to mark as A/R"]);
        exit;
    }

    // 2. Insert A/R record
    $stmt = $conn->prepare("
        INSERT INTO accounts_receivable
            (client_id, transaction_id, amount, balance, remarks)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iidds",
        $tx['client_id'],
        $transaction_id,
        $balance,
        $balance,
        $remarks
    );
    $stmt->execute();

    // 3. Update transaction flags
    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET
            payment_status = 'partial',
            is_receivable = 1,
            has_receivable = 1
        WHERE id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();

    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["success" => false, "error" => "Invalid action"]);
