<?php
header("Content-Type: application/json");
session_start();
require_once "../../php/db.php";

/* ===============================
   Rate limiting
=============================== */
$_SESSION['confirm_attempts'] = ($_SESSION['confirm_attempts'] ?? 0) + 1;
if ($_SESSION['confirm_attempts'] > 5) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts.']);
    exit;
}

/* ===============================
   Guards
=============================== */
if (
    empty($_SESSION['booking_client']) ||
    empty($_SESSION['booking_services']) ||
    empty($_SESSION['booking_schedule']) ||
    empty($_SESSION['booking_price_snapshot'])
) {
    echo json_encode(['success' => false, 'message' => 'Booking session expired.']);
    exit;
}

$client   = $_SESSION['booking_client'];          // ✅ single source
$snapshot = $_SESSION['booking_price_snapshot'];

if (time() > $snapshot['expires_at']) {
    echo json_encode(['success' => false, 'message' => 'Payment session expired.']);
    exit;
}

if (
    empty($_POST['booking_nonce']) ||
    $_POST['booking_nonce'] !== ($_SESSION['booking_nonce'] ?? null)
) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment request.']);
    exit;
}

/* ===============================
   Required fields
=============================== */
if (
    empty($_POST['confirm_payment']) ||
    empty($_POST['amount_paid']) ||
    empty($_POST['payment_reference']) ||
    empty($_FILES['payment_proof'])
) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

/* ===============================
   Amount validation
=============================== */
$amountPaid = (float) $_POST['amount_paid'];
$totalDue   = (float) $snapshot['total'];
$minDown    = round($totalDue * 0.30, 2);

if ($amountPaid < $minDown || $amountPaid > $totalDue) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment amount.']);
    exit;
}

$paymentType = ($amountPaid >= $totalDue) ? 'full' : 'partial';
$balanceDue  = round($totalDue - $amountPaid, 2);

/* ===============================
   Upload proof (secure)
=============================== */
$file = $_FILES['payment_proof'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload failed.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

if (!isset($allowed[$mime])) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    exit;
}

$baseDir = dirname(__DIR__, 2) . '/secure_uploads/payments';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0700, true);
}

$proofName = 'pay_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
move_uploaded_file($file['tmp_name'], $baseDir . '/' . $proofName);

/* ===============================
   DB TRANSACTION (CRITICAL)
=============================== */
$conn->begin_transaction();

try {

    $sched = $_SESSION['booking_schedule'];   // ✅ only schedule here

    $clientSession = $_SESSION['booking_client'];
    $contact = trim($clientSession['contact_number'] ?? '');
    $email   = trim($clientSession['email'] ?? '');

    $contact = $contact !== '' ? $contact : null;
    $email   = $email !== '' ? $email : null;


    /* -------- Find or Create Client -------- */

    $where = [];
    $params = [];
    $types  = '';

    if ($contact !== null) {
        $where[] = 'contact_number = ?';
        $params[] = $contact;
        $types .= 's';
    }

    if ($email !== null) {
        $where[] = 'email = ?';
        $params[] = $email;
        $types .= 's';
    }
    $clientId = null;

    if ($where) {
        $sql = "SELECT id FROM clients WHERE " . implode(' OR ', $where) . " LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $clientId = (int)$row['id'];
        }
    }
    if (!$clientId) {
        $stmt = $conn->prepare("
        INSERT INTO clients (full_name, email, contact_number)
        VALUES (?, ?, ?)
    ");
        $stmt->bind_param(
            "sss",
            $clientSession['full_name'],
            $email,
            $contact
        );
        $stmt->execute();
        $clientId = $stmt->insert_id;
    }


    /* -------- Appointment -------- */
    $stmt = $conn->prepare("
        INSERT INTO appointments
        (client_id, appointment_date, start_time, end_time, status, source)
        VALUES (?, ?, ?, ?, 'pending', 'online')
    ");
    $stmt->bind_param(
        "isss",
        $clientId,
        $sched['date'],
        $sched['start_time'],
        $sched['end_time']
    );
    $stmt->execute();
    $appointmentId = $stmt->insert_id;

    /* -------- Appointment Services -------- */
    foreach ($_SESSION['booking_services'] as $s) {

        $employeeId = $sched['staff_by_variant'][$s['variant_id']] ?? null;
        $quantity   = $s['quantity'] ?? 1;

        $stmt = $conn->prepare("
        INSERT INTO appointment_services
        (appointment_id, service_id, variant_id, employee_id, quantity)
        VALUES (?, ?, ?, ?, ?)
    ");

        $stmt->bind_param(
            "iiiii",
            $appointmentId,
            $s['service_id'],
            $s['variant_id'],
            $employeeId,
            $quantity
        );

        $stmt->execute();
    }


    /* -------- Transaction -------- */
    $txnNo = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));
    $paymentStatus = ($paymentType === 'full') ? 'paid' : 'partial';

    $stmt = $conn->prepare("
        INSERT INTO spa_transactions
        (transaction_number, client_id, appointment_id,
         total_amount, amount_paid, balance_due,
         payment_status, status, transaction_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification', 'online_booking')
    ");
    $stmt->bind_param(
        "siiddds",
        $txnNo,
        $clientId,
        $appointmentId,
        $totalDue,
        $amountPaid,
        $balanceDue,
        $paymentStatus
    );
    $stmt->execute();
    $transactionId = $stmt->insert_id;

    /* -------- Payment -------- */
    $stmt = $conn->prepare("
        INSERT INTO payments
        (transaction_id, amount, payment_method, reference_number, remarks)
        VALUES (?, ?, 'gcash', ?, 'Pending verification')
    ");
    $stmt->bind_param("ids", $transactionId, $amountPaid, $_POST['payment_reference']);
    $stmt->execute();

    /* -------- Accounts Receivable -------- */
    if ($paymentType === 'partial') {
        $stmt = $conn->prepare("
        INSERT INTO accounts_receivable
        (client_id, transaction_id, amount, balance, status, ar_type, remarks)
        VALUES (?, ?, ?, ?, 'open', 'online_tracking', 'from online booking payment')
    ");
        $stmt->bind_param(
            "iidd",
            $clientId,
            $transactionId,
            $balanceDue,
            $balanceDue
        );
        $stmt->execute();

        // 🔥 FLAG TRANSACTION AS RECEIVABLE
        $stmt = $conn->prepare("
            UPDATE spa_transactions
            SET
                has_receivable = 1,
                is_receivable = 1
            WHERE id = ?
        ");
        $stmt->bind_param("i", $transactionId);
        $stmt->execute();
    }

    /* -------- Link proof -------- */
    $stmt = $conn->prepare("
        UPDATE appointments
        SET payment_reference = ?, payment_proof = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $_POST['payment_reference'], $proofName, $appointmentId);
    $stmt->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}

/* ===============================
   Cleanup + success
=============================== */
unset(
    $_SESSION['booking_client'],
    $_SESSION['booking_services'],
    $_SESSION['booking_schedule'],
    $_SESSION['booking_price_snapshot'],
    $_SESSION['booking_nonce'],
    $_SESSION['confirm_attempts']
);

$_SESSION['booking_submitted'] = [
    'appointment_id' => $appointmentId,
    'submitted_at' => time()
];


echo json_encode([
    'success' => true,
    'message' => 'Payment submitted. Pending verification.'
]);
