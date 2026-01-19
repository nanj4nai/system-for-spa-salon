<?php
session_start();
header("Content-Type: application/json");

require_once "../../../db.php";
require_once "../../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

/* =====================
   READ + VALIDATE JSON
===================== */
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Invalid JSON payload"
    ]);
    exit;
}

/* =====================
   FIELD VALIDATION
===================== */
if (!isset($data['appointment_id'])) {
    throwError("Missing appointment_id");
}

if (!isset($data['product_id'])) {
    throwError("Missing product_id");
}

if (!isset($data['quantity'])) {
    throwError("Missing quantity");
}

$appointment_id = (int) $data['appointment_id'];
$product_id     = (int) $data['product_id'];
$quantity       = (float) $data['quantity'];

if ($appointment_id <= 0) {
    throwError("Invalid appointment_id");
}

if ($product_id <= 0) {
    throwError("Invalid product_id");
}

if ($quantity <= 0) {
    throwError("Quantity must be greater than zero");
}

/* =====================
   TRANSACTION
===================== */
$conn->begin_transaction();

try {
    /* =====================
       LOAD PRODUCT
    ===================== */
    $stmt = $conn->prepare("
        SELECT stock, unit, price, product_type
        FROM products
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $product = $stmt->get_result()->fetch_assoc();
    if (!$product) {
        throw new Exception("Product not found");
    }

    $productType = $product['product_type'];
    $unit        = $product['unit'];
    $price       = (float) $product['price'];
    $stock       = (float) $product['stock'];

    /* =====================
       BUSINESS RULES
    ===================== */
    if ($productType === 'reusable') {
        if ($quantity > $stock) {
            throw new Exception("Not enough reusable items available");
        }
    } else {
        if ($quantity > $stock) {
            throw new Exception("Not enough stock");
        }
    }

    $unit_price  = $price;
    $total_price = $unit_price * $quantity;

    /* =====================
       CHECK SERVICE USAGE
    ===================== */
    $stmt = $conn->prepare("
        SELECT 1
        FROM appointment_service_products asp
        JOIN appointment_services aps
            ON aps.id = asp.appointment_service_id
        WHERE aps.appointment_id = ?
        AND asp.product_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $appointment_id, $product_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Product already used by a service");
    }

    /* =====================
       SERVICE EXISTENCE
    ===================== */
    $stmt = $conn->prepare("
        SELECT COUNT(*) total
        FROM appointment_services
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

    if ($stmt->get_result()->fetch_assoc()['total'] == 0) {
        throw new Exception("Add a service before adding extra products");
    }

    /* =====================
       INSERT EXTRA PRODUCT
    ===================== */
    $stmt = $conn->prepare("
        INSERT INTO appointment_extra_products
        (appointment_id, product_id, quantity, unit, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iidsdd",
        $appointment_id,
        $product_id,
        $quantity,
        $unit,
        $unit_price,
        $total_price
    );
    $stmt->execute();

    /* =====================
       TRANSACTION
    ===================== */
    $stmt = $conn->prepare("
        SELECT id FROM spa_transactions
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

    $stmt = $conn->prepare("
        INSERT INTO product_sales
        (transaction_id, product_id, quantity, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iiidd",
        $transaction_id,
        $product_id,
        $quantity,
        $unit_price,
        $total_price
    );
    $stmt->execute();

    recalcTransaction($conn, $transaction_id);

    /* =====================
       STOCK DEDUCTION
    ===================== */
    if ($productType !== 'reusable') {
        $stmt = $conn->prepare("
            UPDATE products
            SET stock = stock - ?
            WHERE id = ?
        ");
        $stmt->bind_param("di", $quantity, $product_id);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => $e->getMessage()
    ]);
}

/* =====================
   HELPER
===================== */
function throwError($message)
{
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => $message
    ]);
    exit;
}
