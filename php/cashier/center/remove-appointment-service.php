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
       FETCH SERVICE + SOURCE
    ===================== */
    $stmt = $conn->prepare("
        SELECT 
            aps.appointment_id,
            a.source
        FROM appointment_services aps
        JOIN appointments a ON a.id = aps.appointment_id
        WHERE aps.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        throw new Exception("Service not found");
    }

    // 🔒 HARD RULE: block online services
    if ($row['source'] === 'online') {
        throw new Exception("Online booking services cannot be removed");
    }

    $appointment_id = $row['appointment_id'];

    /* =====================
       CHECK TRANSACTION STATUS
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

    while ($p = $res->fetch_assoc()) {
        $u = $conn->prepare("
            UPDATE products
            SET stock = stock + ?
            WHERE id = ?
        ");
        $u->bind_param("di", $p['quantity_used'], $p['product_id']);
        $u->execute();
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
