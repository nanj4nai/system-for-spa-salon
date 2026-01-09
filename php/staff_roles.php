<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// LIST
if ($action === "list") {
    $res = $conn->query("SELECT * FROM staff_roles ORDER BY name ASC");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

// SAVE (ADD / EDIT)
if ($action === "save") {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);

    if ($id) {
        $stmt = $conn->prepare(
            "UPDATE staff_roles SET name=?, description=? WHERE id=?"
        );
        $stmt->bind_param("ssi", $name, $desc, $id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO staff_roles(name, description) VALUES(?,?)"
        );
        $stmt->bind_param("ss", $name, $desc);
    }

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    exit;
}

// DELETE
if ($action === "delete") {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("DELETE FROM staff_roles WHERE id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
}
