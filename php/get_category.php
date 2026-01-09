<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
    exit;
}

require_once "db.php";

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM service_categories WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$result = $stmt->get_result();
$category = $result->fetch_assoc();

if ($category) echo json_encode($category);
else echo json_encode(["success"=>false,"message"=>"Category not found"]);

$stmt->close();
