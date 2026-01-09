<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data['appointment_service_id'] ?? 0);

if (!$id) {
    echo json_encode(["success" => false, "error" => "Missing ID"]);
    exit;
}


$conn->begin_transaction();

try {
    /* =====================
       GET APPOINTMENT ID
    ===================== */
    $stmt = $conn->prepare("
        SELECT appointment_id
        FROM appointment_services
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        throw new Exception("Service not found");
    }

    $appointment_id = $row['appointment_id'];

    /* =====================
       RESTORE PRODUCT STOCK
    ===================== */
    $stmt = $conn->prepare("
        SELECT product_id, quantity_used
        FROM appointment_service_products
        WHERE appointment_service_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $u = $conn->prepare("
            UPDATE products
            SET stock = stock + ?
            WHERE id = ?
        ");
        $u->bind_param("di", $row['quantity_used'], $row['product_id']);
        $u->execute();
    }
    /* =====================
       CHECK TRANSACTION PAYMENT STATUS
    ===================== */
    $stmt = $conn->prepare("
    SELECT payment_status
    FROM spa_transactions
    WHERE appointment_id = ?
    LIMIT 1
");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if ($tx && $tx['payment_status'] === 'paid') {
        throw new Exception("Cannot remove services from a paid transaction");
    }


    /* =====================
       DELETE PRODUCT USAGE
    ===================== */
    $stmt = $conn->prepare("
        DELETE FROM appointment_service_products
        WHERE appointment_service_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    /* =====================
       DELETE TRANSACTION SERVICE
    ===================== */
    $stmt = $conn->prepare("
        DELETE FROM spa_transaction_services
        WHERE appointment_service_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    /* =====================
       DELETE APPOINTMENT SERVICE
    ===================== */
    $stmt = $conn->prepare("
        DELETE FROM appointment_services
        WHERE id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    /* =====================
       RECALCULATE TRANSACTION
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

    if ($txn) {
        recalcTransaction($conn, $txn['id']);
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
