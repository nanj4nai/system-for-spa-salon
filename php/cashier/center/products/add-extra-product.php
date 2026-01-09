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

$appointment_id = intval($data['appointment_id'] ?? 0);
$product_id     = intval($data['product_id'] ?? 0);
$quantity       = floatval($data['quantity'] ?? 0);

if (!$appointment_id || !$product_id || $quantity <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid data"]);
    exit;
}

$conn->begin_transaction();

try {
    /* =====================
       LOAD PRODUCT
    ===================== */
    $stmt = $conn->prepare("
        SELECT
            stock,
            unit,
            price,
            product_type
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
    $price       = floatval($product['price']);
    $stock       = floatval($product['stock']);

    /* =====================
       APPLY BUSINESS RULES
    ===================== */
    if ($productType === 'reusable') {
        // towels, tools, etc.
        $quantity    = 1;
        $unit_price  = 0;
        $total_price = 0;
    } else {
        // consumable OR one_time
        if ($stock < $quantity) {
            throw new Exception("Not enough stock");
        }

        $unit_price  = $price;
        $total_price = $unit_price * $quantity;
    }

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

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM appointment_services
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()["total"];

    if ($count == 0) {
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

    // get transaction
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

    // insert sale
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

    // 🔥 NOW recalc
    recalcTransaction($conn, $transaction_id);

    /* =====================
       DEDUCT STOCK (IF NEEDED)
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
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
