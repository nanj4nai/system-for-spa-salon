<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";
require_once "cashier/helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$action = $_POST['action'] ?? '';

/* ==========================
   LIST CASHIERS (NO OPEN GATE)
========================== */
if ($action === 'list_cashiers') {

    $res = $conn->query("
        SELECT u.id, u.username
        FROM users u
        WHERE u.role = 'cashier'
          AND NOT EXISTS (
              SELECT 1
              FROM cashier_shifts cs
              WHERE cs.user_id = u.id
                AND cs.status IN ('open', 'pending_open')
          )
        ORDER BY u.username
    ");

    echo json_encode([
        "success" => true,
        "cashiers" => $res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}



/* ==========================
   ADMIN OPEN SHIFT
========================== */

if ($action === 'open_shift') {

    $cashier_id = intval($_POST['cashier_id'] ?? 0);
    $admin_id   = $_SESSION['user_id'];

    if ($cashier_id <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid cashier"]);
        exit;
    }

    // 🔒 Ensure cashier has NO active/open shift
    $stmt = $conn->prepare("
        SELECT id
        FROM cashier_shifts
        WHERE user_id = ?
          AND status = 'open'
        LIMIT 1
    ");
    $stmt->bind_param("i", $cashier_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode([
            "success" => false,
            "error" => "Cashier already has an open shift"
        ]);
        exit;
    }

    // ✅ Open gate (NO CASH YET)
    $stmt = $conn->prepare("
        INSERT INTO cashier_shifts (
            user_id,
            opened_at,
            status,
            approval_status,
            approved_by,
            approved_at
        ) VALUES (
            ?, NOW(), 'pending_open', 'approved', ?, NOW()
        )
    ");
    $stmt->bind_param("ii", $cashier_id, $admin_id);
    $stmt->execute();

    echo json_encode(["success" => true]);
    exit;
}

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
   FETCH PENDING OPEN SHIFTS
========================== */
if ($action === 'list_pending_open') {

    $res = $conn->query("
        SELECT 
            cs.id,
            cs.opened_at,
            cs.status,
            u.username
        FROM cashier_shifts cs
        JOIN users u ON cs.user_id = u.id
        WHERE cs.status = 'pending_open'
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
            cs.status,
            u.username,

            COUNT(ar.id) AS ar_count,
            COALESCE(SUM(ar.balance), 0) AS ar_balance

        FROM cashier_shifts cs
        JOIN users u ON cs.user_id = u.id

        LEFT JOIN spa_transactions t
            ON t.shift_id = cs.id

        LEFT JOIN accounts_receivable ar
            ON ar.transaction_id = t.id
        AND ar.status = 'open'
        AND ar.ar_type = 'pay_later'

        WHERE cs.status = 'closed'

        GROUP BY cs.id
        ORDER BY cs.closed_at DESC
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
            cs.closing_cash,
            cs.status,
            u.username,

            COUNT(ar.id) AS ar_count,
            COALESCE(SUM(ar.balance), 0) AS ar_balance

        FROM cashier_shifts cs
        JOIN users u ON cs.user_id = u.id

        LEFT JOIN spa_transactions t
            ON t.shift_id = cs.id

        LEFT JOIN accounts_receivable ar
            ON ar.transaction_id = t.id
        AND ar.status = 'open'
        AND ar.ar_type = 'pay_later'

        WHERE cs.status = 'open'
          AND cs.is_active = 1

        GROUP BY cs.id
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
            u.username,

            COUNT(ar.id) AS ar_count,
            COALESCE(SUM(ar.balance), 0) AS ar_balance

        FROM cashier_shifts cs
        JOIN users u ON cs.user_id = u.id

        LEFT JOIN spa_transactions t
            ON t.shift_id = cs.id

        LEFT JOIN accounts_receivable ar
            ON ar.transaction_id = t.id
           AND ar.status = 'open'
           AND ar.ar_type = 'pay_later'   -- ✅ THIS IS THE FIX

        WHERE cs.status = 'pending_close'
        GROUP BY cs.id
        ORDER BY cs.opened_at ASC
    ");

    echo json_encode([
        "success" => true,
        "shifts" => $res->fetch_all(MYSQLI_ASSOC)
    ]);
    exit;
}

if ($action === 'cancel_gate') {

    $shift_id = intval($_POST['shift_id']);

    $stmt = $conn->prepare("
        DELETE FROM cashier_shifts
        WHERE id = ?
          AND status = 'pending_open'
    ");
    $stmt->bind_param("i", $shift_id);
    $stmt->execute();

    echo json_encode(["success" => true]);
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

            /* SALES */
            (
                SELECT COALESCE(SUM(t.total_amount), 0)
                FROM spa_transactions t
                WHERE t.shift_id = cs.id
            ) AS gross_sales,

            /* TOTAL COLLECTED */
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                JOIN spa_transactions t ON t.id = p.transaction_id
                WHERE t.shift_id = cs.id
            ) AS total_collected,

            /* CASH ONLY */
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                JOIN spa_transactions t ON t.id = p.transaction_id
                WHERE t.shift_id = cs.id
                AND p.payment_method = 'cash'
            ) AS cash_collected,

            /* PAY-LATER ONLY (EXCLUDES online_tracking) */
            (
                SELECT COALESCE(SUM(ar.balance), 0)
                FROM accounts_receivable ar
                JOIN spa_transactions t ON t.id = ar.transaction_id
                WHERE t.shift_id = cs.id
                AND ar.ar_type = 'pay_later'
                AND ar.status = 'open'
            ) AS pay_later_balance

        FROM cashier_shifts cs
        WHERE cs.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $shift_id);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    $expectedCash = (float)$summary['opening_cash'] + (float)$summary['cash_collected'];
    $variance     = (float)$summary['closing_cash'] - $expectedCash;

    echo json_encode([
        "success" => true,
        "summary" => [
            "opening_cash"      => (float)$summary['opening_cash'],
            "closing_cash"      => (float)$summary['closing_cash'],
            "gross_sales"       => (float)$summary['gross_sales'],
            "total_collected"   => (float)$summary['total_collected'],
            "cash_collected"    => (float)$summary['cash_collected'],
            "pay_later_balance" => (float)$summary['pay_later_balance'],
            "expected_cash"     => $expectedCash,
            "variance"          => $variance
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
   REJECT SHIFT (ADMIN)
========================== */
if ($action === 'reject') {

    $shift_id = intval($_POST['shift_id']);

    $stmt = $conn->prepare("
        UPDATE cashier_shifts
        SET
            status = 'open',
            is_active = 1,
            closing_cash = NULL,
            approval_status = 'approved',
            approved_by = NULL,
            approved_at = NULL
        WHERE id = ?
          AND status = 'pending_close'
    ");

    $stmt->bind_param("i", $shift_id);
    $stmt->execute();

    echo json_encode([
        'success' => $stmt->affected_rows === 1
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
            ts.id,
            ts.appointment_service_id,
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

    // SERVICE CONSUMABLES (grouped by appointment_service_id)
    $consumables = $conn->query("
            SELECT
                asp.appointment_service_id,
                p.name AS product_name,
                asp.quantity_used,
                asp.unit,
                p.price AS container_price,
                p.unit_per_item,
                (p.price / p.unit_per_item) AS unit_price,
                (asp.quantity_used * (p.price / p.unit_per_item)) AS total_price
            FROM appointment_service_products asp
            JOIN products p ON p.id = asp.product_id
            WHERE asp.appointment_service_id IN (
                SELECT appointment_service_id
                FROM spa_transaction_services
                WHERE transaction_id = $transaction_id
                AND appointment_service_id IS NOT NULL
            )
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
    SELECT
        payment_method,
        amount,
        payment_date,
        receipt_number
    FROM payments
    WHERE transaction_id = $transaction_id
    ORDER BY payment_date ASC
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "success" => true,
        "transaction" => $transaction,
        "services" => $services,
        "service_consumables" => $consumables, // 👈 ADD THIS
        "products" => $products,
        "payments" => $payments,
        "receivable" => $ar,
        "ar_payments" => $arPayments
    ]);
    exit;
}
if ($action === 'apply_ar_payment') {

    $receivable_id = intval($_POST['receivable_id']);
    $amount = floatval($_POST['amount']);
    $remarks = trim($_POST['remarks'] ?? '');
    $method = $_POST['payment_method'] ?? 'cash';
    $reference = trim($_POST['reference'] ?? '');

    if ($amount <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid payment amount"]);
        exit;
    }

    if ($method !== 'cash' && $reference === '') {
        echo json_encode(["success" => false, "error" => "Reference is required for non-cash payments"]);
        exit;
    }

    $conn->begin_transaction();

    try {
        // 🔒 Lock receivable
        $stmt = $conn->prepare("
            SELECT id, transaction_id, balance
            FROM accounts_receivable
            WHERE id = ? AND status = 'open'
            FOR UPDATE
        ");
        $stmt->bind_param("i", $receivable_id);
        $stmt->execute();
        $ar = $stmt->get_result()->fetch_assoc();

        if (!$ar) {
            throw new Exception("Receivable not found or closed");
        }

        if ($amount > $ar['balance']) {
            throw new Exception("Payment exceeds balance");
        }

        // 1️⃣ Insert AR payment history
        $stmt = $conn->prepare("
            INSERT INTO ar_payments (receivable_id, amount, remarks)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("ids", $receivable_id, $amount, $remarks);
        $stmt->execute();

        // 2️⃣ Generate receipt
        $receiptNumber = generateReceiptNumber($conn);

        // 3️⃣ Insert real payment record
        $stmt = $conn->prepare("
            INSERT INTO payments
                (transaction_id, amount, payment_method, receipt_number, remarks, reference_number)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "idssss",
            $ar['transaction_id'],
            $amount,
            $method,
            $receiptNumber,
            $remarks,
            $reference
        );
        $stmt->execute();

        // 4️⃣ Update receivable
        $newBalance = $ar['balance'] - $amount;
        $newStatus = $newBalance <= 0 ? 'paid' : 'open';

        $stmt = $conn->prepare("
            UPDATE accounts_receivable
            SET balance = ?, status = ?
            WHERE id = ?
        ");
        $stmt->bind_param("dsi", $newBalance, $newStatus, $receivable_id);
        $stmt->execute();

        // 5️⃣ Update transaction
        $paymentStatus = $newBalance <= 0 ? 'paid' : 'partial';

        $stmt = $conn->prepare("
            UPDATE spa_transactions
            SET payment_status = ?
            WHERE id = ?
        ");
        $stmt->bind_param("si", $paymentStatus, $ar['transaction_id']);
        $stmt->execute();

        $conn->commit();

        echo json_encode([
            "success" => true,
            "receipt_number" => $receiptNumber
        ]);
    } catch (Throwable $e) {
        $conn->rollback();

        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
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
