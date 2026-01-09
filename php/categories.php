<?php
session_start();
header("Content-Type: application/json");

// Block requests if not logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once "db.php";

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ======== FETCH CATEGORIES ========
    case "fetch":
        $result = $conn->query("SELECT * FROM product_categories ORDER BY name ASC");
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        break;

    // ======== ADD / UPDATE CATEGORY ========
    case "save":
        $id = $_POST["id"] ?? null;
        $name = trim($_POST["name"]);

        if ($id == "" || $id == null) {
            // Insert new
            $stmt = $conn->prepare("INSERT INTO product_categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
        } else {
            // Update existing
            $stmt = $conn->prepare("UPDATE product_categories SET name=? WHERE id=?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
        }

        echo json_encode(["success" => true]);
        break;

    // ======== DELETE CATEGORY ========
    case "delete":
        $id = intval($_POST["id"]);

        // Delete only if no product is using it OR set NULL
        $conn->query("UPDATE products SET category_id=NULL WHERE category_id=$id");
        $conn->query("DELETE FROM product_categories WHERE id=$id");

        echo json_encode(["success" => true]);
        break;

    default:
        echo json_encode(["error" => "Invalid action"]);
}
