<?php
header("Content-Type: application/json");
require_once "../../php/db.php";

$sql = "
SELECT
    s.id AS service_id,
    s.name AS service_name,
    s.description,
    s.base_price AS service_price,

    sv.id AS variant_id,
    sv.name AS variant_name,
    sv.duration_minutes,
    sv.price AS variant_price
FROM services s
LEFT JOIN service_variants sv
    ON sv.service_id = s.id
ORDER BY s.name, sv.price
";

$result = $conn->query($sql);

$services = [];

while ($row = $result->fetch_assoc()) {
    $sid = $row['service_id'];
    $vid = $row['variant_id'];

    // Initialize service
    if (!isset($services[$sid])) {
        $services[$sid] = [
            "id" => $sid,
            "name" => $row['service_name'],
            "description" => $row['description'],
            "price" => (float) $row['service_price'], // fallback price
            "variants" => []
        ];
    }

    // Add variant if exists
    if ($vid) {
        $services[$sid]['variants'][$vid] = [
            "id" => $vid,
            "name" => $row['variant_name'],
            "duration" => (int) $row['duration_minutes'],
            "price" => (float) $row['variant_price']
        ];
    }
}

// Normalize variants to arrays
foreach ($services as &$service) {
    $service['variants'] = array_values($service['variants']);
}

echo json_encode(array_values($services));
