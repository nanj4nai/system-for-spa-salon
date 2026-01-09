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
    p.id,
    p.name,
    p.product_type,
    p.stock,
    p.unit,
    p.unit_per_item,
    p.price,

    CASE
        WHEN asp.product_id IS NOT NULL THEN 1
        ELSE 0
    END AS used_in_service

FROM products p

LEFT JOIN (
    SELECT DISTINCT asp.product_id
    FROM appointment_services aps
    JOIN appointment_service_products asp
        ON asp.appointment_service_id = aps.id
    WHERE aps.appointment_id = ?
) asp ON asp.product_id = p.id

LEFT JOIN appointment_extra_products aep
    ON aep.product_id = p.id
    AND aep.appointment_id = ?

WHERE aep.id IS NULL
ORDER BY p.name ASC

");

$stmt->bind_param("ii", $appointment_id, $appointment_id);
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
