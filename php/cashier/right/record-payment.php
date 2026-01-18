<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$transaction_id = intval($data['transaction_id'] ?? 0);
$amount         = floatval($data['amount'] ?? 0);
$method         = $data['payment_method'] ?? 'cash';

if ($transaction_id <= 0 || $amount <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit;
}

$conn->begin_transaction();

try {
    // Lock row
    $stmt = $conn->prepare("
        SELECT total_amount, amount_paid
        FROM spa_transactions
        WHERE id = ? FOR UPDATE
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if (!$tx) {
        throw new Exception("Transaction not found");
    }

    $newPaid = $tx['amount_paid'] + $amount;
    $balance = max(0, $tx['total_amount'] - $newPaid);

    if ($balance == 0) {
        $status = 'paid';
        $payStatus = 'paid';
    } else {
        $status = 'locked';
        $payStatus = 'partial';
    }

    // Insert payment
    $stmt = $conn->prepare("
        INSERT INTO payments (transaction_id, amount, payment_method)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("ids", $transaction_id, $amount, $method);
    $stmt->execute();

    // Update transaction
    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET amount_paid = ?,
            balance = ?,
            payment_status = ?,
            status = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "ddssi",
        $newPaid,
        $balance,
        $payStatus,
        $status,
        $transaction_id
    );
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "payment_status" => $payStatus,
        "balance" => $balance
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
