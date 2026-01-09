<?php
session_start();
error_log("SESSION: " . json_encode($_SESSION));
error_log("POST: " . json_encode($_POST));
error_log("FILES: " . json_encode($_FILES));

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once "db.php";

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper: handle image upload
function uploadImage($file)
{
    if (!isset($file) || $file["error"] !== 0) {
        return null;
    }

    $ext = pathinfo($file["name"], PATHINFO_EXTENSION);
    $filename = "product_" . time() . "." . $ext;
    $target = "../uploads/products/" . $filename;

    if (!is_dir("../uploads/products")) {
        mkdir("../uploads/products", 0777, true);
    }

    move_uploaded_file($file["tmp_name"], $target);

    return "uploads/products/" . $filename;
}

// Helper: check if category exists
function categoryExists($conn, $category_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM product_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $count = 0;
    $stmt->bind_result($count);
    if ($stmt->fetch()) {
        $stmt->close();
        return $count > 0;
    }
    $stmt->close();
    return false;
}

switch ($action) {

    // ======== FETCH PRODUCTS ========
    case "fetch":
        $query = "
            SELECT p.*, c.name AS category, c.id AS category_id
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.id
            ORDER BY p.id DESC
        ";
        $result = $conn->query($query);
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    // ======== SAVE PRODUCT (ADD/EDIT) ========
    case "save":
        $id = intval($_POST["id"] ?? 0);
        $name = trim($_POST["name"] ?? '');
        $category_id = intval($_POST["category_id"] ?? 0);
        $stock = floatval($_POST["stock"] ?? 0);
        $price = floatval($_POST["price"] ?? 0);
        $product_type = $_POST["product_type"] ?? "consumable";
        $unit = $_POST["unit"] ?? "pcs";

        $unit_per_item = isset($_POST["unit_per_item"]) && $_POST["unit_per_item"] !== ""
            ? intval($_POST["unit_per_item"])
            : null;

        if ($product_type === "reusable") {
            $unit = "pcs";
            $unit_per_item = null;
        }

        if ($product_type !== "consumable") {
            $unit_per_item = null;
        }
        if ($stock < 0) {
            echo json_encode(["error" => "Stock cannot be negative"]);
            exit;
        }

        if (!$name) {
            echo json_encode(["error" => "Product name required"]);
            exit;
        }

        if (!$category_id || !categoryExists($conn, $category_id)) {
            echo json_encode(["error" => "Invalid category"]);
            exit;
        }
        
        // ---- Duplicate check (same name + same category) ----
        if ($id === 0) {
            // Creating new product
            $dupStmt = $conn->prepare("
                SELECT id 
                FROM products 
                WHERE LOWER(name) = LOWER(?) 
                AND category_id = ?
                LIMIT 1
            ");
            $dupStmt->bind_param("si", $name, $category_id);
        } else {
            // Editing existing product (exclude itself)
            $dupStmt = $conn->prepare("
                SELECT id 
                FROM products 
                WHERE LOWER(name) = LOWER(?) 
                AND category_id = ?
                AND id != ?
                LIMIT 1
            ");
            $dupStmt->bind_param("sii", $name, $category_id, $id);
        }

        $dupStmt->execute();
        $dupStmt->store_result();

        if ($dupStmt->num_rows > 0) {
            $dupStmt->close();
            echo json_encode([
                "error" => "A product with this name already exists in the selected category"
            ]);
            exit;
        }

        $dupStmt->close();


        $imagePath = uploadImage($_FILES["image"] ?? null);

        if ($id === 0) {
            // INSERT
            $stmt = $conn->prepare("
                INSERT INTO products 
                (name, category_id, stock, price, image, product_type, unit, unit_per_item)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "siidsssi",
                $name,
                $category_id,
                $stock,
                $price,
                $imagePath,
                $product_type,
                $unit,
                $unit_per_item
            );

            $stmt->execute();
            $stmt->close();
        } else {
            // UPDATE (single query, image-safe)
            $stmt = $conn->prepare("
                    UPDATE products 
                    SET name=?, category_id=?, stock=?, price=?,
                        image = COALESCE(?, image),
                        product_type=?, unit=?, unit_per_item=?
                    WHERE id=?
                ");

            $stmt->bind_param(
                "siidsssii",
                $name,
                $category_id,
                $stock,
                $price,
                $imagePath,      // NULL keeps old image
                $product_type,
                $unit,
                $unit_per_item,
                $id
            );

            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(["success" => true]);
        break;

    // ======== DELETE PRODUCT ========
    case "delete":
        $id = intval($_POST["id"]);
        $stmt = $conn->prepare("DELETE FROM products WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(["success" => true]);
        break;

    default:
        echo json_encode(["error" => "Invalid action"]);
}
