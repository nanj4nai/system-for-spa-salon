<?php
session_start();
header("Content-Type: application/json");
require_once "../../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$appointment_id = intval($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    echo json_encode(["success" => false, "error" => "Missing appointment"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        aep.id,
        p.name,
        p.product_type,
        aep.quantity,
        aep.unit,
        aep.unit_price,
        aep.total_price
    FROM appointment_extra_products aep
    JOIN products p ON p.id = aep.product_id
    WHERE aep.appointment_id = ?
    ORDER BY aep.id ASC
");
$stmt->bind_param("i", $appointment_id);
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
