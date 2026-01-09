<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

require_once "db.php";

// Use POST first, fallback to GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$response = ["success" => false, "message" => "Something went wrong."];
$conn->begin_transaction();
try {
    switch ($action) {

        // CREATE OR UPDATE SERVICE WITH VARIANTS
        case 'create_or_update':
            $service_id = $_POST['service_id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $category_id = $_POST['category_id'] ?? null;
            $base_price = floatval($_POST['base_price'] ?? 0);
            $default_commission_percent = floatval($_POST['default_commission_percent'] ?? 0);
            $description = $_POST['description'] ?? '';

            // Prevent duplicate service names in the same category
            $checkStmt = $conn->prepare(
                "SELECT id FROM services WHERE name=? AND category_id=? " . ($service_id ? "AND id != ?" : "")
            );
            if ($service_id) {
                $checkStmt->bind_param("sii", $name, $category_id, $service_id);
            } else {
                $checkStmt->bind_param("si", $name, $category_id);
            }
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                echo json_encode(["success" => false, "message" => "A service with this name already exists in this category."]);
                $checkStmt->close();
                exit;
            }
            $checkStmt->close();

            // Insert or update service
            if ($service_id) {
                $stmt = $conn->prepare(
                    "UPDATE services SET name=?, category_id=?, base_price=?, default_commission_percent=?, description=? WHERE id=?"
                );
                $stmt->bind_param("siidsi", $name, $category_id, $base_price, $default_commission_percent, $description, $service_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO services (name, category_id, base_price, default_commission_percent, description) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("siids", $name, $category_id, $base_price, $default_commission_percent, $description);
                $stmt->execute();
                $service_id = $conn->insert_id;
                $stmt->close();
            }

            // DELETE REMOVED VARIANTS
            $deleted_variant_ids = $_POST['deleted_variant_ids'] ?? [];

            if (!empty($deleted_variant_ids)) {
                $stmt = $conn->prepare(
                    "DELETE FROM service_variants WHERE id=? AND service_id=?"
                );
                foreach ($deleted_variant_ids as $vid) {
                    $stmt->bind_param("ii", $vid, $service_id);
                    $stmt->execute();
                }
                $stmt->close();
            }


            // Handle variants
            $variant_names = $_POST['variant_name'] ?? [];
            $durations = $_POST['duration_minutes'] ?? [];
            $prices = $_POST['price'] ?? [];
            $variant_ids = $_POST['variant_id'] ?? [];

            $seenVariants = [];

            foreach ($variant_names as $i => $v_name) {
                $v_name = trim($v_name);
                if (!$v_name) continue;

                // Prevent duplicates in the same form submission
                if (in_array(strtolower($v_name), $seenVariants)) {
                    echo json_encode(["success" => false, "message" => "Duplicate variant name '{$v_name}' in form submission."]);
                    exit;
                }
                $seenVariants[] = strtolower($v_name);

                $v_id = $variant_ids[$i] ?? null;

                // Only check database if it's a new variant
                if (!$v_id) {
                    $checkStmt = $conn->prepare("SELECT id FROM service_variants WHERE service_id=? AND name=?");
                    $checkStmt->bind_param("is", $service_id, $v_name);
                    $checkStmt->execute();
                    $checkStmt->store_result();
                    if ($checkStmt->num_rows > 0) {
                        echo json_encode(["success" => false, "message" => "A variant with name '{$v_name}' already exists for this service."]);
                        $checkStmt->close();
                        exit;
                    }
                    $checkStmt->close();
                }

                $duration = intval($durations[$i] ?? 0);
                $price = floatval($prices[$i] ?? 0);

                if ($v_id) {
                    // Update existing variant
                    $stmt = $conn->prepare("UPDATE service_variants SET name=?, duration_minutes=?, price=? WHERE id=? AND service_id=?");
                    $stmt->bind_param("sidii", $v_name, $duration, $price, $v_id, $service_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Insert new variant
                    $stmt = $conn->prepare("INSERT INTO service_variants (service_id, name, duration_minutes, price) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isid", $service_id, $v_name, $duration, $price);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // ===== HANDLE SERVICE PRODUCTS =====
            $service_products = json_decode($_POST['service_products'] ?? '[]', true);

            // Clear existing service products
            $stmt = $conn->prepare(
                "DELETE FROM service_products WHERE service_id=?"
            );
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            $stmt->close();

            // Insert updated service products
            if (!empty($service_products)) {
                $stmt = $conn->prepare("
        INSERT INTO service_products (service_id, product_id, quantity)
        VALUES (?, ?, ?)
    ");

                foreach ($service_products as $sp) {
                    $product_id = intval($sp['product_id']);
                    $qty = floatval($sp['quantity']);

                    if ($product_id <= 0 || $qty <= 0) continue;

                    // Fetch product behavior
                    $prodStmt = $conn->prepare("
            SELECT product_type, stock 
            FROM products 
            WHERE id=?
        ");
                    $prodStmt->bind_param("i", $product_id);
                    $prodStmt->execute();
                    $product = $prodStmt->get_result()->fetch_assoc();
                    $prodStmt->close();

                    if (!$product) continue;

                    // ---- VALIDATION RULES ----
                    if ($product['product_type'] === 'reusable' && floor($qty) != $qty) {
                        echo json_encode([
                            "success" => false,
                            "message" => "Reusable products must use whole quantities."
                        ]);
                        exit;
                    }

                    if ($product['product_type'] === 'one_time' && $qty != 1) {
                        echo json_encode([
                            "success" => false,
                            "message" => "One-time products must have quantity of 1."
                        ]);
                        exit;
                    }

                    if ($product['product_type'] === 'consumable' && $qty > $product['stock']) {
                        echo json_encode([
                            "success" => false,
                            "message" => "Not enough stock for one of the consumable products."
                        ]);
                        exit;
                    }
                    // ---- INSERT ----
                    $stmt->bind_param("iid", $service_id, $product_id, $qty);
                    $stmt->execute();
                }
                $stmt->close();
            }
            $response = ["success" => true, "message" => "Service saved successfully!", "id" => $service_id];
            break;

        // DELETE SERVICE
        case 'delete_service':
            $id = $_GET['id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ["success" => true, "message" => "Service deleted successfully!"];
            }
            $stmt->close();
            break;

        // DELETE VARIANT ONLY
        case 'delete_variant':
            $id = $_GET['id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM service_variants WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $response = ["success" => true, "message" => "Variant deleted successfully!"];
            }
            $stmt->close();
            break;

        default:
            $response = ["success" => false, "message" => "Invalid action"];
            break;
    }
    $conn->commit();
    $response = [
        "success" => true,
        "message" => "Service saved successfully!",
        "id" => $service_id
    ];
} catch (Exception $e) {
    $conn->rollback();
    $response = [
        "success" => false,
        "message" => $e->getMessage()
    ];
}

echo json_encode($response);
exit;
