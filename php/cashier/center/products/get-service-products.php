<?php
session_start();
header("Content-Type: application/json");

require_once "../../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$service_id = intval($_GET['service_id'] ?? 0);
if (!$service_id) {
    echo json_encode(["success" => false, "error" => "Missing service_id"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        sp.quantity AS default_qty,
        p.unit,
        p.stock
    FROM service_products sp
    JOIN products p ON p.id = sp.product_id
    WHERE sp.service_id = ?
");
$stmt->bind_param("i", $service_id);
$stmt->execute();

$res = $stmt->get_result();
$products = [];

while ($row = $res->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode([
    "success" => true,
    "products" => $products
]);
