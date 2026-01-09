<?php
require_once "db.php";

session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Optional: allow filtering by search, startDate, endDate to only get relevant actions
$search = $_GET['search'] ?? '';
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';

$where = [];

// Escape input for MySQLi
$searchEsc = $conn->real_escape_string($search);
$startEsc = $conn->real_escape_string($startDate);
$endEsc = $conn->real_escape_string($endDate);

// Filters
if ($search) {
    $where[] = "(u.username LIKE '%$searchEsc%' OR l.action LIKE '%$searchEsc%' OR l.description LIKE '%$searchEsc%')";
}
if ($startDate) {
    $where[] = "l.created_at >= '$startEsc 00:00:00'";
}
if ($endDate) {
    $where[] = "l.created_at <= '$endEsc 23:59:59'";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch unique actions
$result = $conn->query("
    SELECT DISTINCT l.action
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    $whereSql
    ORDER BY l.action ASC
");

$actions = [];
while ($row = $result->fetch_assoc()) {
    $actions[] = $row['action'];
}

echo json_encode([
    "actions" => $actions
]);
