<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

require_once "db.php";

$action = isset($_POST['category_id']) && $_POST['category_id'] !== ''
    ? 'update'
    : ($_GET['action'] ?? 'create');

$response = ["success" => false, "message" => "Something went wrong."];

switch ($action) {
    case 'create':
        $name = $_POST['name'] ?? '';
        $stmt = $conn->prepare("INSERT INTO service_categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $response = [
                "success" => true,
                "message" => "Category added successfully!",
                "id" => $conn->insert_id
            ];
        }
        $stmt->close();
        break;

    case 'update':
        $id = $_POST['category_id'];
        $name = $_POST['name'] ?? '';
        $stmt = $conn->prepare("UPDATE service_categories SET name=? WHERE id=?");
        $stmt->bind_param("si", $name, $id);
        if ($stmt->execute()) {
            $response = [
                "success" => true,
                "message" => "Category updated successfully!",
                "id" => $id,
                "name" => $name
            ];
        }
        $stmt->close();
        break;

    case 'delete':
        $id = $_GET['id'] ?? 0;
        // Prevent deletion if services exist
        $stmt = $conn->prepare("SELECT COUNT(*) FROM services WHERE category_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $response = ["success" => false, "message" => "Cannot delete category because services are using it."];
        } else {
            $stmt = $conn->prepare("DELETE FROM service_categories WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) $response = ["success" => true, "message" => "Category deleted successfully!"];
            $stmt->close();
        }
        break;
}

echo json_encode($response);
