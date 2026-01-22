<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

/* ================================
   AUTH
================================ */
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['cashier', 'admin'])
) {
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized"
    ]);
    exit;
}

/* ================================
   INPUT
================================ */
$receiptNumber = trim($_GET['receipt_number'] ?? '');

if ($receiptNumber === '') {
    echo json_encode([
        "success" => false,
        "error" => "Invalid receipt number"
    ]);
    exit;
}

/* ================================
   FETCH RECEIPT + TRANSACTION
================================ */
$stmt = $conn->prepare("
    SELECT
        p.id               AS payment_id,
        p.amount           AS receipt_amount,
        p.payment_method,
        p.receipt_number,
        p.payment_date,

        t.id               AS transaction_id,
        t.total_amount,
        t.is_receivable
    FROM payments p
    JOIN spa_transactions t
        ON t.id = p.transaction_id
    WHERE p.receipt_number = ?
    LIMIT 1
");
$stmt->bind_param("s", $receiptNumber);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "error" => "Receipt not found"
    ]);
    exit;
}

$row = $res->fetch_assoc();

/* ================================
   AUTHORITATIVE CALCULATIONS
================================ */

/*
  Receipt rules:
  - receipt_amount = THIS payment only
  - cumulativePaid = total paid up to THIS receipt (historical)
  - balance = remaining balance AFTER this receipt
*/

$total = round((float)$row['total_amount'], 2);
$receiptPaid = round((float)$row['receipt_amount'], 2);

/* ================================
   CALCULATE CUMULATIVE PAID
   (safe for same-second payments)
================================ */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0)
    FROM payments
    WHERE transaction_id = ?
      AND (
           payment_date < ?
        OR (payment_date = ? AND id <= ?)
      )
");
$stmt->bind_param(
    "issi",
    $row['transaction_id'],
    $row['payment_date'],
    $row['payment_date'],
    $row['payment_id']
);
$stmt->execute();
$cumulativePaid = round((float)$stmt->get_result()->fetch_row()[0], 2);

/* ================================
   FINAL BALANCE AFTER THIS RECEIPT
================================ */
$balance = max(0, round($total - $cumulativePaid, 2));

/* ================================
   RESPONSE (UI-READY)
================================ */
echo json_encode([
    "success" => true,

    // receipt display
    "receipt" => $row['receipt_number'],
    "total"   => $total,
    "paid"    => $receiptPaid,
    "balance" => $balance,
    "method"  => $row['payment_method'],

    // metadata (future-proof)
    "meta" => [
        "payment_date"   => $row['payment_date'],
        "transaction_id" => (int)$row['transaction_id'],
        "is_receivable"  => (int)$row['is_receivable']
    ]
]);
