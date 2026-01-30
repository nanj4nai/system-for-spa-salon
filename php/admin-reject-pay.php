<?php
session_start();
require_once "db.php";
require_once "company_settings.php";
require_once "mail.php";

header("Content-Type: application/json");

if (
    empty($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if (!$appointmentId || !$reason) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$adminId = $_SESSION['user_id'];

$conn->begin_transaction();

try {
    /* ---------------------------
       Fetch client info
    ---------------------------- */
    $stmt = $conn->prepare("
        SELECT 
            a.id AS appointment_id,
            c.email,
            c.full_name
        FROM appointments a
        JOIN clients c ON c.id = a.client_id
        WHERE a.id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();

    if (!$client) {
        throw new Exception("Client not found.");
    }

    /* ---------------------------
       Lock transaction + guard
    ---------------------------- */
    $stmt = $conn->prepare("
        SELECT *
        FROM spa_transactions
        WHERE appointment_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if (!$txn) {
        throw new Exception("Transaction not found.");
    }

    if ($txn['status'] !== 'pending_verification') {
        throw new Exception("Only pending bookings can be rejected.");
    }

    /* ---------------------------
       Mark appointment as rejected + cancelled
    ---------------------------- */
    $stmt = $conn->prepare("
        UPDATE appointments
        SET
            status = 'cancelled',
            payment_verified = 0,
            payment_rejection_reason = ?,
            payment_rejected_at = NOW(),
            payment_rejected_by = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $reason, $adminId, $appointmentId);
    $stmt->execute();

    /* ---------------------------
       Cancel transaction
    ---------------------------- */
    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET status = 'cancelled'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $txn['id']);
    $stmt->execute();

    /* ---------------------------
       Activity log
    ---------------------------- */
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, action, description)
        VALUES (?, 'reject_payment', ?)
    ");
    $desc = "Rejected booking payment for appointment #{$appointmentId}: {$reason}";
    $stmt->bind_param("is", $adminId, $desc);
    $stmt->execute();

    $conn->commit();
    
    /* 4. Notify client via email */
    $subject = "Payment Rejected – Appointment #{$appointmentId}";

    $message = '
        <div style="background:#f4f6f8;padding:24px;font-family:Arial,Helvetica,sans-serif;">
        <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:6px;padding:24px;">

            <h2 style="margin:0 0 12px;color:#b91c1c;">Payment Review Update</h2>

            <p style="margin:0 0 16px;color:#333;">
            Hi <strong>' . htmlspecialchars($client['full_name']) . '</strong>,
            </p>

            <p style="margin:0 0 16px;color:#333;">
            We reviewed your payment submission, but unfortunately it could not be verified at this time.
            </p>

            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:4px;padding:16px;margin:16px 0;">
            <p style="margin:0 0 8px;font-size:14px;color:#7f1d1d;font-weight:bold;">
                Reason for rejection:
            </p>
            <p style="margin:0;font-size:14px;color:#7f1d1d;">
                ' . nl2br(htmlspecialchars($reason)) . '
            </p>
            </div>

            <p style="margin:0 0 24px;color:#333;">
            You can book again to re-upload your payment proof or correct the details.
            </p>

            <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">

            <p style="margin:0;color:#555;font-size:13px;">
            Thank you for your understanding,<br>
            <strong>' . htmlspecialchars($company_name) . '</strong>
            </p>

        </div>
        </div>
        ';

    if (!sendMail(
        $client['email'],
        $client['full_name'],
        $subject,
        $message
    )) {
        error_log("Failed to send rejection email for appointment #{$appointmentId}");
    }


    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
