<?php
session_start();
header("Content-Type: application/json");

require_once "../../../db.php";
require_once "../../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);

if (!$id) {
    echo json_encode(["success" => false, "error" => "Missing ID"]);
    exit;
}

$conn->begin_transaction();

try {
    /* =====================
       LOAD EXTRA PRODUCT
    ===================== */
    $stmt = $conn->prepare("
        SELECT
            aep.product_id,
            aep.quantity,
            aep.appointment_id,
            p.product_type
        FROM appointment_extra_products aep
        JOIN products p ON p.id = aep.product_id
        WHERE aep.id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        throw new Exception("Record not found");
    }

    $product_id   = $row['product_id'];
    $quantity     = $row['quantity'];
    $appointment_id = $row['appointment_id'];
    $productType  = $row['product_type'];

    /* =====================
       RESTORE STOCK (IF NOT REUSABLE)
    ===================== */
    if ($productType !== 'reusable') {
        $stmt = $conn->prepare("
            UPDATE products
            SET stock = stock + ?
            WHERE id = ?
        ");
        $stmt->bind_param("di", $quantity, $product_id);
        $stmt->execute();
    }

    /* =====================
       DELETE EXTRA PRODUCT
    ===================== */
    $stmt = $conn->prepare("
        DELETE FROM appointment_extra_products
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    /* =====================
       GET TRANSACTION
    ===================== */
    $stmt = $conn->prepare("
        SELECT id
        FROM spa_transactions
        WHERE appointment_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();

    if (!$txn) {
        throw new Exception("Transaction not found");
    }

    $transaction_id = $txn['id'];

    /* =====================
       DELETE PRODUCT SALE
    ===================== */
    $stmt = $conn->prepare("
        DELETE FROM product_sales
        WHERE transaction_id = ?
          AND product_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $transaction_id, $product_id);
    $stmt->execute();

    /* =====================
       RECALCULATE TOTALS
    ===================== */
    recalcTransaction($conn, $transaction_id);

    $conn->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
