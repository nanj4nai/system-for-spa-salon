<?php
session_start();
header("Content-Type: application/json");
require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(["success" => false, "error" => "Missing service ID"]);
    exit;
}

/* =========================
   BASE SERVICE INFO
========================= */
$stmt = $conn->prepare("
    SELECT
        aps.id,
        aps.service_id,
        aps.employee_id,
        aps.variant_id,
        aps.quantity,
        t.status AS transaction_status
    FROM appointment_services aps
    JOIN spa_transactions t ON t.appointment_id = aps.appointment_id
    WHERE aps.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();

$service = $stmt->get_result()->fetch_assoc();
if (!$service) {
    echo json_encode(["success" => false, "error" => "Service not found"]);
    exit;
}

/* =========================
   SERVICE PRODUCTS (DEFAULT)
========================= */
$stmt = $conn->prepare("
    SELECT
        sp.product_id,
        p.name,
        p.product_type,
        p.unit,
        p.unit_per_item,
        p.price,
        sp.quantity AS default_qty
    FROM service_products sp
    JOIN products p ON p.id = sp.product_id
    WHERE sp.service_id = ?
");
$stmt->bind_param("i", $service['service_id']);
$stmt->execute();

$res = $stmt->get_result();
$products = [];

while ($p = $res->fetch_assoc()) {

    // Load actual usage if exists
    $stmt2 = $conn->prepare("
        SELECT
            quantity_used
        FROM appointment_service_products
        WHERE appointment_service_id = ?
          AND product_id = ?
    ");
    $stmt2->bind_param("ii", $service['id'], $p['product_id']);
    $stmt2->execute();

    $used = $stmt2->get_result()->fetch_assoc();

    $p['quantity_used'] = $used['quantity_used'] ?? null;

    $products[] = $p;
}

$service['products'] = $products;

echo json_encode([
    "success" => true,
    "service" => $service,
    "transaction_status" => $service['transaction_status']
]);
