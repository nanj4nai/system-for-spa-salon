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

$appointment_service_id = intval($data['appointment_service_id'] ?? 0);
$product_id             = intval($data['product_id'] ?? 0);
$new_qty                = floatval($data['quantity_used'] ?? -1);

if ($appointment_service_id <= 0 || $product_id <= 0 || $new_qty < 0) {
    echo json_encode(["success" => false, "error" => "Invalid input"]);
    exit;
}

$conn->begin_transaction();

try {
    /* =====================
       CHECK TRANSACTION STATUS
    ===================== */
    $stmt = $conn->prepare("
        SELECT t.status
        FROM spa_transactions t
        JOIN appointment_services aps ON aps.appointment_id = t.appointment_id
        WHERE aps.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $appointment_service_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();

    if ($tx && $tx['status'] === 'locked') {
        throw new Exception("Product usage is locked for this transaction");
    }

    /* =====================
       LOAD CURRENT USAGE
    ===================== */
    $stmt = $conn->prepare("
        SELECT quantity_used
        FROM appointment_service_products
        WHERE appointment_service_id = ?
          AND product_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $appointment_service_id, $product_id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        throw new Exception("Product not linked to this service");
    }

    $old_qty = floatval($row['quantity_used']);
    $delta   = $new_qty - $old_qty;

    /* =====================
       ADJUST STOCK (DELTA)
    ===================== */
    /* =====================
   ADJUST BY PRODUCT TYPE
===================== */
    if ($delta != 0) {

        $stmt = $conn->prepare("
        SELECT stock, product_type, price, unit_per_item
        FROM products
        WHERE id = ?
        LIMIT 1
    ");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new Exception("Product not found");
        }

        switch ($product['product_type']) {

            case 'consumable':
                // stock = ml / mg remaining
                if ($delta > 0 && $product['stock'] < $delta) {
                    throw new Exception("Not enough stock");
                }

                $stmt = $conn->prepare("
                UPDATE products
                SET stock = stock - ?
                WHERE id = ?
            ");
                $stmt->bind_param("di", $delta, $product_id);
                $stmt->execute();
                break;

            case 'one_time':
                // NO stock deduction
                // price handled elsewhere (flat / per use)
                break;

            case 'reusable':
                // NO stock deduction
                // usage is informational only
                break;
        }
    }

    /* =====================
       UPDATE USAGE
    ===================== */
    $stmt = $conn->prepare("
        UPDATE appointment_service_products
        SET quantity_used = ?
        WHERE appointment_service_id = ?
          AND product_id = ?
        LIMIT 1
    ");
    $stmt->bind_param(
        "dii",
        $new_qty,
        $appointment_service_id,
        $product_id
    );
    $stmt->execute();

    /* =====================
       RECALC TRANSACTION
    ===================== */
    $stmt = $conn->prepare("
        SELECT t.id
        FROM spa_transactions t
        JOIN appointment_services aps ON aps.appointment_id = t.appointment_id
        WHERE aps.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $appointment_service_id);
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
