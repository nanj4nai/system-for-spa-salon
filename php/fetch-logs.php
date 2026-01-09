<?php
require_once "db.php";

session_start();
if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$search = $_GET['search'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;

$where = [];

// Escape input for MySQLi
$searchEsc = $conn->real_escape_string($search);
$actionEsc = $conn->real_escape_string($actionFilter);
$startEsc = $conn->real_escape_string($startDate);
$endEsc = $conn->real_escape_string($endDate);

// Search
if ($search) {
    $where[] = "(u.username LIKE '%$searchEsc%' OR l.action LIKE '%$searchEsc%' OR l.description LIKE '%$searchEsc%')";
}

// Action filter
if ($actionFilter) {
    $where[] = "l.action = '$actionEsc'";
}

// Date range
if ($startDate) {
    $where[] = "l.created_at >= '$startEsc 00:00:00'";
}
if ($endDate) {
    $where[] = "l.created_at <= '$endEsc 23:59:59'";
}

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Total count (for pagination)
$totalResult = $conn->query("SELECT COUNT(*) as total FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id $whereSql");
$totalRow = $totalResult->fetch_assoc();
$total = $totalRow['total'];
$totalPages = $limit > 0 ? ceil($total / $limit) : 1;

// Limit clause
$limitSql = $limit > 0 ? "LIMIT $limit OFFSET $offset" : "";

// Fetch logs
$logsResult = $conn->query("
    SELECT l.id, u.username, l.action, l.description, l.created_at
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    $whereSql
    ORDER BY l.created_at DESC
    $limitSql
");

$logs = [];
while ($row = $logsResult->fetch_assoc()) {
    $logs[] = $row;
}

echo json_encode([
    "logs" => $logs,
    "totalPages" => $totalPages,
    "currentPage" => $page
]);
