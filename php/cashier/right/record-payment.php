<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

// ================================
// AUTHORIZATION
// ================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized"
    ]);
    exit;
}

// ================================
// INPUT
// ================================
$data = json_decode(file_get_contents("php://input"), true);

$transaction_id = intval($data['transaction_id'] ?? 0);
$amount         = floatval($data['amount'] ?? 0);
$method         = $data['payment_method'] ?? 'cash';
$reference      = $data['reference_number'] ?? null;
$remarks        = $data['remarks'] ?? null;

if ($transaction_id <= 0 || $amount <= 0) {
    echo json_encode([
        "success" => false,
        "error" => "Invalid input"
    ]);
    exit;
}

// ================================
// DB TRANSACTION
// ================================
$conn->begin_transaction();

try {
    // 🔒 Lock transaction row
    $stmt = $conn->prepare("
        SELECT total_amount, amount_paid
        FROM spa_transactions
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if (!$tx) {
        throw new Exception("Transaction not found");
    }

    $totalAmount  = floatval($tx['total_amount']);
    $alreadyPaid  = floatval($tx['amount_paid']);
    $remaining    = max(0, $totalAmount - $alreadyPaid);

    // ================================
    // BUSINESS RULES
    // ================================
    // Non-cash must be exact
    if ($method !== 'cash' && $amount != $remaining) {
        throw new Exception("Non-cash payments must be exact amount");
    }

    // Reference required for non-cash
    if ($method !== 'cash' && empty($reference)) {
        throw new Exception("Reference number is required");
    }

    // ================================
    // CALCULATIONS
    // ================================
    $newPaid = $alreadyPaid + $amount;
    $balance = max(0, $totalAmount - $newPaid);

    $paymentStatus = ($balance == 0)
        ? 'paid'
        : 'partial';

    // ================================
    // INSERT PAYMENT
    // ================================
    $stmt = $conn->prepare("
        INSERT INTO payments (
            transaction_id,
            amount,
            payment_method,
            receipt_number
        ) VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "idss",
        $transaction_id,
        $amount,
        $method,
        $reference
    );
    $stmt->execute();

    // ================================
    // UPDATE TRANSACTION
    // ================================
    $stmt = $conn->prepare("
        UPDATE spa_transactions
        SET amount_paid = ?,
            balance = ?,
            payment_status = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        "ddsi",
        $newPaid,
        $balance,
        $paymentStatus,
        $transaction_id
    );
    $stmt->execute();

    $conn->commit();

    // ================================
    // RESPONSE
    // ================================
    echo json_encode([
        "success" => true,
        "payment_status" => $paymentStatus,
        "balance" => $balance
    ]);
} catch (Throwable $e) {
    $conn->rollback();

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
