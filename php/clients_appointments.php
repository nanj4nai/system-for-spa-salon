<?php
session_start();
require_once "db.php";
header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "GET") {
    $q = $_GET['q'] ?? '';
    $q = $conn->real_escape_string($q);

    $sql = $sql = "
    SELECT id, full_name, contact_number, email FROM clients WHERE full_name LIKE '%$q%' OR contact_number LIKE '%$q%' OR email LIKE '%$q%' ORDER BY full_name LIMIT 10";
    $res = $conn->query($sql);
    $clients = [];
    while ($row = $res->fetch_assoc()) {
        $clients[] = $row;
    }
    echo json_encode($clients);
    exit;
}

if ($method === "POST") {
    // Insert new client
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data["full_name"])) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Full name is required"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO clients (full_name, contact_number, email, address, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssss",
        $data["full_name"],
        $data["contact_number"],
        $data["email"],
        $data["address"],
        $data["notes"]
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    echo json_encode(["success" => true, "id" => $newId]);
    exit;
}

http_response_code(405);
echo json_encode(["error" => "Method not allowed"]);
