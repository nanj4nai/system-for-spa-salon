<?php
session_start();
require_once "db.php";
require_once "company_settings.php";

$settings = $conn->query("SELECT invoice_prefix FROM settings LIMIT 1")->fetch_assoc();

header("Content-Type: application/json");

if (
    empty($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (empty($_POST['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}

$appointmentId = (int)$_POST['appointment_id'];
$adminId = $_SESSION['user_id'];

$conn->begin_transaction();

try {

    /* Lock transaction */
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            c.email,
            c.full_name
        FROM spa_transactions t
        JOIN clients c ON c.id = t.client_id
        WHERE t.appointment_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if (!$txn) {
        throw new Exception("Transaction not found.");
    }

    if ($txn['status'] !== 'pending_verification') {
        throw new Exception("Transaction already processed.");
    }


    /* 1. Verify payment */
    $stmt = $conn->prepare("
        UPDATE appointments
        SET
            payment_verified = 1,
            payment_verified_by = ?,
            payment_verified_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $adminId, $appointmentId);
    $stmt->execute();

    /* 2. Update transaction status */
    $paymentStatus = ($txn['balance_due'] > 0) ? 'partial' : 'paid';
    $txnStatus     = 'editing';

    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET
            payment_status = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssi", $paymentStatus, $txnStatus, $txn['id']);
    $stmt->execute();

    /* 3. Generate receipt for booking payment */
    $receiptNo = ($settings['invoice_prefix'] ?? 'SPA')
        . '-' . date('Ymd')
        . '-' . strtoupper(bin2hex(random_bytes(3)));

    $stmt = $conn->prepare("
    UPDATE payments
    SET
        receipt_number = ?,
        remarks = 'Payment verified'
    WHERE transaction_id = ?
      AND receipt_number IS NULL
    LIMIT 1
");
    $stmt->bind_param("si", $receiptNo, $txn['id']);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception("Receipt already generated or payment record missing.");
    }


    /* 3. Confirm appointment */
    $stmt = $conn->prepare("
        UPDATE appointments
        SET status = 'confirmed'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();

    /* 4. Create Accounts Receivable if partial */
    if ($txn['balance_due'] > 0) {
        $stmt = $conn->prepare("
            UPDATE accounts_receivable
            SET status = 'open'
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("i", $txn['id']);
        $stmt->execute();
    }


    /* 5. Activity log */
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, description)
        VALUES (?, 'approve_payment', ?)
    ");
    $desc = "Approved booking payment for appointment #{$appointmentId}";
    $stmt->bind_param("is", $adminId, $desc);
    $stmt->execute();

    $conn->commit();


    require_once "mail.php";

    $subject = "Payment Approved – Appointment #{$appointmentId}";

    if ($txn['balance_due'] > 0) {
        $message = '
            <div style="background:#f4f6f8;padding:24px;font-family:Arial,Helvetica,sans-serif;">
            <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:6px;padding:24px;">

                <h2 style="margin:0 0 12px;color:#111;">Payment Approved</h2>

                <p style="margin:0 0 16px;color:#333;">
                Hi <strong>' . htmlspecialchars($txn['full_name']) . '</strong>,
                </p>

                <p style="margin:0 0 16px;color:#333;">
                Your payment has been <strong style="color:#0a7d00;">verified and approved</strong>.
                </p>

                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:16px;margin:16px 0;">
                <p style="margin:0 0 6px;color:#555;font-size:13px;">
                    Remaining Balance
                </p>
                <p style="margin:0;font-size:22px;font-weight:bold;color:#111;">
                    ₱' . number_format($txn['balance_due'], 2) . '
                </p>
                </div>

                <p style="margin:0 0 24px;color:#333;">
                Please settle the remaining balance on or before your appointment date.
                </p>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

                <p style="margin:0;color:#555;font-size:13px;">
                Thank you,<br>
                <strong>' . htmlspecialchars($company_name) . '</strong>
                </p>

            </div>
            </div>
            ';
    } else {
        $message = '
            <div style="background:#f4f6f8;padding:24px;font-family:Arial,Helvetica,sans-serif;">
            <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:6px;padding:24px;">

                <h2 style="margin:0 0 12px;color:#111;">Appointment Confirmed</h2>

                <p style="margin:0 0 16px;color:#333;">
                Hi <strong>' . htmlspecialchars($txn['full_name']) . '</strong>,
                </p>

                <p style="margin:0 0 16px;color:#333;">
                Your payment has been <strong style="color:#0a7d00;">successfully verified</strong>.
                </p>

                <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:4px;padding:16px;margin:16px 0;">
                <p style="margin:0;font-size:15px;color:#065f46;font-weight:bold;">
                    Your appointment is now confirmed.
                </p>
                </div>

                <p style="margin:0 0 24px;color:#333;">
                We look forward to serving you!
                </p>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

                <p style="margin:0;color:#555;font-size:13px;">
                Warm regards,<br>
                <strong>' . htmlspecialchars($company_name) . '</strong>
                </p>

            </div>
            </div>
            ';
    }

    if (!sendMail(
        $txn['email'],
        $txn['full_name'],
        $subject,
        $message
    )) {
        error_log("Failed to send approval email for appointment #{$appointmentId}");
    }


    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
