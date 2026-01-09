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

$appointment_id = intval($data['appointment_id'] ?? 0);
$service_id     = intval($data['service_id'] ?? 0);
$employee_id    = intval($data['staff_id'] ?? 0);
$variant_id     = intval($data['variant_id'] ?? 0);

$quantity = 1;
if ($quantity < 1) $quantity = 1;

if (!$appointment_id || !$service_id || !$employee_id) {
    echo json_encode(["success" => false, "error" => "Missing required fields"]);
    exit;
}

$conn->begin_transaction();

try {
    /* =====================
       VALIDATE APPOINTMENT
    ===================== */
    $stmt = $conn->prepare("
        SELECT id FROM appointments
        WHERE id = ? AND status = 'checked_in'
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception("Invalid or inactive appointment");
    }

    /* =====================
       VALIDATE SERVICE
    ===================== */
    $stmt = $conn->prepare("SELECT id FROM services WHERE id = ?");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception("Service not found");
    }

    /* =====================
       VALIDATE VARIANT (OPTIONAL)
    ===================== */
    if ($variant_id) {
        $stmt = $conn->prepare("
            SELECT id FROM service_variants
            WHERE id = ? AND service_id = ?
        ");
        $stmt->bind_param("ii", $variant_id, $service_id);
        $stmt->execute();

        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception("Invalid service variant");
        }
    } else {
        $variant_id = null;
    }

    /* =====================
        DUPLICATE CHECK
     ===================== */
    $stmt = $conn->prepare("
            SELECT id
            FROM appointment_services
            WHERE appointment_id = ?
            AND service_id = ?
            AND (
                (variant_id IS NULL AND ? IS NULL)
                OR variant_id = ?
            )
        ");
    $stmt->bind_param(
        "iiii",
        $appointment_id,
        $service_id,
        $variant_id,
        $variant_id
    );
    $stmt->execute();

    if ($stmt->get_result()->fetch_assoc()) {
        throw new Exception(
            $variant_id
                ? "This service variant is already added"
                : "This service is already added"
        );
    }

    /* =====================
       VALIDATE STAFF
    ===================== */
    $stmt = $conn->prepare("
        SELECT id FROM employees
        WHERE id = ? AND is_active = 1
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();

    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception("Staff not available");
    }

    /* =====================
       INSERT APPOINTMENT SERVICE
    ===================== */
    $stmt = $conn->prepare("
        INSERT INTO appointment_services
        (appointment_id, service_id, employee_id, variant_id, quantity)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iiiii",
        $appointment_id,
        $service_id,
        $employee_id,
        $variant_id,
        $quantity
    );
    $stmt->execute();

    $appointment_service_id = $stmt->insert_id;

    /* =====================
   AUTO-ATTACH DEFAULT PRODUCT USAGE
===================== */
    $stmt = $conn->prepare("
    SELECT
        sp.product_id,
        sp.quantity AS default_qty,
        p.unit
    FROM service_products sp
    JOIN products p ON p.id = sp.product_id
    WHERE sp.service_id = ?
");
    $stmt->bind_param("i", $service_id);
    $stmt->execute();

    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $usedQty = floatval($row['default_qty']) * $quantity;
        $unit    = $row['unit'];
        $productId = $row['product_id'];

        // Check stock FIRST
        $stmtCheck = $conn->prepare("
            SELECT stock FROM products WHERE id = ?
        ");
        $stmtCheck->bind_param("i", $productId);
        $stmtCheck->execute();

        $currentStock = $stmtCheck->get_result()->fetch_assoc()['stock'];

        if ($currentStock < $usedQty) {
            throw new Exception("Not enough stock for product");
        }

        // Insert usage
        $stmt2 = $conn->prepare("
            INSERT INTO appointment_service_products
            (appointment_service_id, product_id, quantity_used, unit)
            VALUES (?, ?, ?, ?)
        ");
        $stmt2->bind_param(
            "iids",
            $appointment_service_id,
            $productId,
            $usedQty,
            $unit
        );
        $stmt2->execute();

        // Deduct inventory
        $stmt3 = $conn->prepare("
            UPDATE products
            SET stock = stock - ?
            WHERE id = ?
        ");
        $stmt3->bind_param("di", $usedQty, $productId);
        $stmt3->execute();
    }


    /* =====================
       GET TRANSACTION ID
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
        throw new Exception("Transaction not found for appointment");
    }

    $transaction_id = $txn['id'];

    /* =====================
       GET SERVICE PRICE
    ===================== */
    $stmt = $conn->prepare("
        SELECT
            COALESCE(sv.price, s.base_price) AS price
        FROM services s
        LEFT JOIN service_variants sv ON sv.id = ?
        WHERE s.id = ?
    ");
    $stmt->bind_param("ii", $variant_id, $service_id);
    $stmt->execute();

    $priceRow = $stmt->get_result()->fetch_assoc();
    if (!$priceRow) {
        throw new Exception("Failed to determine service price");
    }

    $unit_price = floatval($priceRow['price']);
    $total_price = $unit_price * $quantity;

    /* =====================
       INSERT TRANSACTION SERVICE
    ===================== */
    $stmt = $conn->prepare("
    INSERT INTO spa_transaction_services
    (
    transaction_id,
    service_id,
    employee_id,
    appointment_service_id,
    quantity,
    unit_price,
    total_price
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iiiiddi",
        $transaction_id,
        $service_id,
        $employee_id,
        $appointment_service_id,
        $quantity,
        $unit_price,
        $total_price
    );
    $stmt->execute();

    // 🔥 recalc totals
    recalcTransaction($conn, $transaction_id);

    $conn->commit();

    echo json_encode([
        "success" => true,
        "appointment_service_id" => $appointment_service_id
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
