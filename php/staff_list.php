<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$sql = "
    SELECT
        e.id,
        e.full_name,
        r.name AS role_name
    FROM employees e
    JOIN staff_roles r ON r.id = e.staff_role_id
    WHERE e.is_active = 1
    ORDER BY r.name, e.full_name
";

$result = $conn->query($sql);

echo json_encode(
    $result->fetch_all(MYSQLI_ASSOC)
);
