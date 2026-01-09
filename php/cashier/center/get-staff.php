<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        e.id,
        e.full_name,
        r.name AS role_name
    FROM employees e
    JOIN staff_roles r ON r.id = e.staff_role_id
    WHERE e.is_active = 1
    ORDER BY e.full_name ASC
");
$stmt->execute();

$res = $stmt->get_result();
$staff = [];

while ($row = $res->fetch_assoc()) {
    $staff[] = $row;
}

echo json_encode([
    "success" => true,
    "staff" => $staff
]);
