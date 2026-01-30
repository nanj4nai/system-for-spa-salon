<?php
header("Content-Type: application/json");
require_once "../../php/db.php";

/*
|--------------------------------------------------------------------------
| Fetch assignable staff
|--------------------------------------------------------------------------
| - Only active employees
| - Joined with roles
| - Sorted nicely
*/

$sql = "
    SELECT
        e.id,
        e.full_name,
        r.name AS role
    FROM employees e
    JOIN staff_roles r ON r.id = e.staff_role_id
    WHERE e.is_active = 1
    ORDER BY r.name, e.full_name
";

$result = $conn->query($sql);

$staff = [];

while ($row = $result->fetch_assoc()) {
    $staff[] = [
        "id"   => (int) $row['id'],
        "name" => $row['full_name'],
        "role" => $row['role']
    ];
}

echo json_encode($staff);
exit;
