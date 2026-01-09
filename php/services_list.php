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
        s.id AS service_id,
        s.name AS service_name,
        s.base_price,

        v.id AS variant_id,
        v.name AS variant_name,
        v.duration_minutes,
        v.price AS variant_price,

        p.id AS product_id,
        p.name AS product_name,
        sp.quantity
    FROM services s
    LEFT JOIN service_variants v ON v.service_id = s.id
    LEFT JOIN service_products sp ON sp.service_id = s.id
    LEFT JOIN products p ON p.id = sp.product_id
    ORDER BY s.name, v.price
";

$result = $conn->query($sql);

$services = [];

while ($row = $result->fetch_assoc()) {
    $sid = $row["service_id"];

    if (!isset($services[$sid])) {
        $services[$sid] = [
            "id" => $sid,
            "name" => $row["service_name"],
            "base_price" => $row["base_price"],
            "variants" => [],
            "products" => []
        ];
    }

    if ($row["variant_id"]) {
        $services[$sid]["variants"][$row["variant_id"]] = [
            "id" => $row["variant_id"],
            "name" => $row["variant_name"],
            "duration" => $row["duration_minutes"],
            "price" => $row["variant_price"]
        ];
    }

    if ($row["product_id"]) {
        $services[$sid]["products"][$row["product_id"]] = [
            "id" => $row["product_id"],
            "name" => $row["product_name"],
            "quantity" => $row["quantity"]
        ];
    }
}
foreach ($services as &$s) {
    $s["variants"] = array_values($s["variants"]);
    $s["products"] = array_values($s["products"]);
}

echo json_encode(array_values($services));
