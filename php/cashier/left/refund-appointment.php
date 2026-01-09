<?php
session_start();
header("Content-Type: application/json");
require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$appointment_id = intval($data['appointment_id'] ?? 0);
$status = $data['status'] ?? '';
$refund_type = $data['refund_type'] ?? 'none';
$refund_amount = floatval($data['refund_amount'] ?? 0);

if (!$appointment_id || !in_array($status, ['cancelled', 'no_show'])) {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Update appointment status
    $stmt = $conn->prepare("
        UPDATE appointments
        SET status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("si", $status, $appointment_id);
    $stmt->execute();

    // 2. Fetch transaction
    $stmt = $conn->prepare("
        SELECT id, amount_paid
        FROM spa_transactions
        WHERE appointment_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if ($refund_type !== 'none') {

        if (!$txn || $txn['amount_paid'] <= 0) {
            throw new Exception("No refundable amount");
        }

        if ($refund_type === 'partial' && $refund_amount <= 0) {
            throw new Exception("Invalid refund amount");
        }

        $refund = ($refund_type === 'full')
            ? $txn['amount_paid']
            : min($refund_amount, $txn['amount_paid']);

        // record refund
        $stmt = $conn->prepare("
            INSERT INTO payments
            (transaction_id, amount, payment_method, receipt_number)
            VALUES (?, ?, 'refund', ?)
        ");
        $receipt = 'RF-' . date('YmdHis');
        $neg = -abs($refund);
        $stmt->bind_param("ids", $txn['id'], $neg, $receipt);
        $stmt->execute();

        // update totals
        $stmt = $conn->prepare("
            UPDATE spa_transactions
            SET amount_paid = amount_paid - ?
            WHERE id = ?
        ");
        $stmt->bind_param("di", $refund, $txn['id']);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
