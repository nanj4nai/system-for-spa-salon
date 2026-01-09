<?php
session_start();
header("Content-Type: application/json");
require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(["success" => true, "clients" => []]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, full_name, contact_number
    FROM clients
    WHERE full_name LIKE CONCAT('%', ?, '%')
       OR contact_number LIKE CONCAT('%', ?, '%')
    ORDER BY full_name
    LIMIT 10
");
$stmt->bind_param("ss", $q, $q);
$stmt->execute();

$res = $stmt->get_result();
$clients = [];

while ($row = $res->fetch_assoc()) {
    $clients[] = $row;
}

echo json_encode([
    "success" => true,
    "clients" => $clients
]);
