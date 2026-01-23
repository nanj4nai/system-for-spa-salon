<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

require_once "db.php";

/* =========================
   CATEGORIES
========================= */
$categories = $conn->query("
    SELECT * FROM service_categories
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

/* =========================
   ALL PRODUCTS (MODALS)
========================= */
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

/* =========================
   SERVICES
========================= */
$services_result = $conn->query("
    SELECT * FROM services
    ORDER BY created_at DESC
");

$services = [];

while ($service = $services_result->fetch_assoc()) {

    /* =========================
       VARIANTS
    ========================= */
    $variant_stmt = $conn->prepare("
        SELECT *
        FROM service_variants
        WHERE service_id = ?
        ORDER BY created_at ASC
    ");
    $variant_stmt->bind_param("i", $service['id']);
    $variant_stmt->execute();
    $variants = $variant_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $variant_stmt->close();

    /* =========================
       VARIANT PRODUCT ESTIMATES (NEW)
    ========================= */
    foreach ($variants as &$variant) {
        $estimate_stmt = $conn->prepare("
            SELECT
                svpe.product_id,
                svpe.estimated_quantity,
                svpe.unit,
                p.name,
                p.price,
                p.unit_per_item,
                p.product_type
            FROM service_variant_product_estimates svpe
            JOIN products p ON p.id = svpe.product_id
            WHERE svpe.service_variant_id = ?
        ");
        $estimate_stmt->bind_param("i", $variant['id']);
        $estimate_stmt->execute();
        $variant['estimates'] =
            $estimate_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $estimate_stmt->close();
    }
    unset($variant); // important (PHP reference safety)

    /* =========================
       SERVICE PRODUCTS (BASE)
    ========================= */
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
    $serviceProducts =
        $product_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $product_stmt->close();

    /* =========================
       ASSEMBLE SERVICE
    ========================= */
    $service['variants'] = $variants;
    $service['products'] = $serviceProducts;

    $services[] = $service;
}

/* =========================
   RESPONSE
========================= */
echo json_encode([
    "success" => true,
    "categories" => $categories,
    "services" => $services,
    "products" => $allProducts
]);
