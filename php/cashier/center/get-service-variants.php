<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

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
    SELECT id, name, price
    FROM service_variants
    WHERE service_id = ?
    ORDER BY price ASC
");
$stmt->bind_param("i", $service_id);
$stmt->execute();

$res = $stmt->get_result();
$variants = [];

while ($row = $res->fetch_assoc()) {
    $variants[] = $row;
}

echo json_encode([
    "success" => true,
    "variants" => $variants
]);
