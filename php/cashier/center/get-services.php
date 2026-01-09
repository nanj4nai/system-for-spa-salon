<?php
session_start();
header("Content-Type: application/json");
require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false]);
    exit;
}

$res = $conn->query("
    SELECT id, name, base_price
    FROM services
    ORDER BY name
");

$services = [];
while ($row = $res->fetch_assoc()) {
    $services[] = $row;
}

echo json_encode([
    "success" => true,
    "services" => $services
]);
