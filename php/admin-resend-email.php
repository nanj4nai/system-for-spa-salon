<?php
session_start();
header("Content-Type: application/json");

require_once "db.php";
require_once "company_settings.php";
require_once "mail.php";

if (
    empty($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment']);
    exit;
}

/* Fetch appointment snapshot */
$stmt = $conn->prepare("
    SELECT
        a.payment_verified,
        a.payment_rejection_reason,
        a.last_email_sent_at,
        c.email,
        c.full_name,
        t.balance_due
    FROM appointments a
    JOIN clients c ON c.id = a.client_id
    JOIN spa_transactions t ON t.appointment_id = a.id
    WHERE a.id = ?
");

$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
    exit;
}

if (!empty($row['last_email_sent_at'])) {
    $last = strtotime($row['last_email_sent_at']);
    $now  = time();

    if (($now - $last) < 60) {
        $remaining = 60 - ($now - $last);
        echo json_encode([
            'success' => false,
            'message' => "Please wait {$remaining}s before resending."
        ]);
        exit;
    }
}


/* Decide which email to resend */
if ((int)$row['payment_verified'] === 1) {

    // ✅ RESEND APPROVAL EMAIL
    $subject = "Payment Approved – Appointment #{$appointmentId}";

    if ($row['balance_due'] > 0) {
        $message = '
        <div style="background:#f4f6f8;padding:24px;font-family:Arial,Helvetica,sans-serif;">
        <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:6px;padding:24px;">

            <h2 style="margin:0 0 12px;color:#111;">Payment Approved</h2>

            <p style="margin:0 0 16px;color:#333;">
            Hi <strong>' . htmlspecialchars($row['full_name']) . '</strong>,
            </p>

            <p style="margin:0 0 16px;color:#333;">
            Your payment has been <strong style="color:#0a7d00;">verified and approved</strong>.
            </p>

            <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:16px;margin:16px 0;">
                <p style="margin:0 0 6px;color:#555;font-size:13px;">Remaining Balance</p>
                <p style="margin:0;font-size:22px;font-weight:bold;">
                    ₱' . number_format($row['balance_due'], 2) . '
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
        </div>';
    } else {
        $message = '
        <div style="background:#f4f6f8;padding:24px;font-family:Arial,Helvetica,sans-serif;">
        <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:6px;padding:24px;">

            <h2 style="margin:0 0 12px;color:#111;">Appointment Confirmed</h2>

            <p style="margin:0 0 16px;color:#333;">
            Hi <strong>' . htmlspecialchars($row['full_name']) . '</strong>,
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
        </div>';
    }
} elseif (!empty($row['payment_rejection_reason'])) {

    // ❌ RESEND REJECTION EMAIL
    $subject = "Payment Rejected – Appointment #{$appointmentId}";

    $message = '
    <div style="background:#f4f6f8;padding:24px;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:6px;padding:24px;">

        <h2 style="margin:0 0 12px;color:#b91c1c;">Payment Review Update</h2>

        <p style="margin:0 0 16px;color:#333;">
        Hi <strong>' . htmlspecialchars($row['full_name']) . '</strong>,
        </p>

        <p style="margin:0 0 16px;color:#333;">
        We reviewed your payment submission, but unfortunately it could not be verified at this time.
        </p>

        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:4px;padding:16px;margin:16px 0;">
            <p style="margin:0 0 8px;font-size:14px;color:#7f1d1d;font-weight:bold;">
                Reason for rejection:
            </p>
            <p style="margin:0;font-size:14px;color:#7f1d1d;">
                ' . nl2br(htmlspecialchars($row['payment_rejection_reason'])) . '
            </p>
        </div>

        <p style="margin:0 0 24px;color:#333;">
        You may re-upload your payment proof or correct the details from your booking page.
        </p>

        <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

        <p style="margin:0;color:#555;font-size:13px;">
        Thank you for your understanding,<br>
        <strong>' . htmlspecialchars($company_name) . '</strong>
        </p>

    </div>
    </div>';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No email available to resend'
    ]);
    exit;
}

/* Send */
if (!sendMail($row['email'], $row['full_name'], $subject, $message)) {
    error_log("Failed to resend email for appointment #{$appointmentId}");
    echo json_encode(['success' => false, 'message' => 'Email failed']);
    exit;
}
$stmt = $conn->prepare("
    UPDATE appointments
    SET
        last_email_sent_at = NOW(),
        last_email_type = ?
    WHERE id = ?
");
$emailType = ((int)$row['payment_verified'] === 1) ? 'approved' : 'rejected';
$stmt->bind_param("si", $emailType, $appointmentId);
$stmt->execute();


echo json_encode(['success' => true]);
