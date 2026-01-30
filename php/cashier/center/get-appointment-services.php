<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

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
        aps.id,
        aps.quantity,
        aps.service_id,
        aps.employee_id,
        aps.variant_id,
        s.name AS service_name,
        sv.name AS variant_name,
        COALESCE(e.full_name, 'Unassigned') AS staff_name
    FROM appointment_services aps
    JOIN services s ON s.id = aps.service_id
    LEFT JOIN employees e ON e.id = aps.employee_id
    LEFT JOIN service_variants sv ON sv.id = aps.variant_id
    WHERE aps.appointment_id = ?
    ORDER BY aps.id ASC
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();

$res = $stmt->get_result();
$services = [];

while ($row = $res->fetch_assoc()) {
    $stmt2 = $conn->prepare("
            SELECT
                asp.id,
                p.name,
                p.price,
                p.product_type,
                asp.quantity_used,
                asp.unit
            FROM appointment_service_products asp
            JOIN products p ON p.id = asp.product_id
            WHERE asp.appointment_service_id = ?
        ");
    $stmt2->bind_param("i", $row['id']);
    $stmt2->execute();

    $res2 = $stmt2->get_result();
    $row['products'] = [];

    while ($p = $res2->fetch_assoc()) {
        $row['products'][] = $p;
    }

    $services[] = $row;
}

echo json_encode([
    "success" => true,
    "services" => $services
]);
