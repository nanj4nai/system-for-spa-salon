<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

require_once "db.php";

// Get categories
$categories = $conn->query("
    SELECT * FROM service_categories 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Get ALL products (for modal selector)
$allProducts = $conn->query("
    SELECT 
        id,
        name,
        stock,
        price, 
        unit,
        product_type,
        unit_per_item
    FROM products
    ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

// Get services
$services_result = $conn->query("
    SELECT * FROM services 
    ORDER BY created_at DESC
");

$services = [];

while ($service = $services_result->fetch_assoc()) {

    // Get variants
    $variant_stmt = $conn->prepare("
        SELECT * FROM service_variants 
        WHERE service_id=? 
        ORDER BY created_at ASC
    ");
    $variant_stmt->bind_param("i", $service['id']);
    $variant_stmt->execute();
    $variants = $variant_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $variant_stmt->close();

    // Get service products
    $product_stmt = $conn->prepare("
        SELECT 
            sp.product_id,
            sp.quantity,
            p.name,
            p.price,
            p.stock,
            p.unit,
            p.product_type,
            p.unit_per_item
        FROM service_products sp
        JOIN products p ON p.id = sp.product_id
        WHERE sp.service_id = ?
    ");
    $product_stmt->bind_param("i", $service['id']);
    $product_stmt->execute();
    $serviceProducts = $product_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $product_stmt->close();

    $service['variants'] = $variants;
    $service['products'] = $serviceProducts;

    $services[] = $service;
}

echo json_encode([
    "success" => true,
    "categories" => $categories,
    "services" => $services,
    "products" => $allProducts
]);
