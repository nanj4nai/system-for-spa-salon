<?php
require_once "db.php";

header("Content-Type: application/json");

$due_in = isset($_GET['due_in']) ? intval($_GET['due_in']) : 7;

// --------------------- TOTAL SERVICES ---------------------
$total_services = $conn->query("SELECT COUNT(*) AS cnt FROM services")
    ->fetch_assoc()['cnt'] ?? 0;


// --------------------- ACTIVE BOOKINGS (Today) ---------------------
$active_bookings = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM spa_transactions
    WHERE DATE(created_at) = CURDATE()
")->fetch_assoc()['cnt'] ?? 0;


// --------------------- UPCOMING BOOKINGS ---------------------
$upcoming_bookings = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM spa_transactions
    WHERE DATE(created_at) > CURDATE()
    AND DATE(created_at) <= DATE_ADD(CURDATE(), INTERVAL $due_in DAY)
")->fetch_assoc()['cnt'] ?? 0;


// --------------------- UPCOMING APPOINTMENTS LIST ---------------------
$due_soon = [];
$result = $conn->query("
    SELECT 
        spa_transactions.id,
        clients.full_name AS client_name,
        spa_transactions.created_at AS appointment_time,
        (
            SELECT services.name 
            FROM spa_transaction_services 
            JOIN services ON services.id = spa_transaction_services.service_id
            WHERE spa_transaction_services.transaction_id = spa_transactions.id
            LIMIT 1
        ) AS service_name
    FROM spa_transactions
    JOIN clients ON clients.id = spa_transactions.client_id
    WHERE DATE(spa_transactions.created_at) >= CURDATE()
    AND DATE(spa_transactions.created_at) <= DATE_ADD(CURDATE(), INTERVAL $due_in DAY)
    ORDER BY spa_transactions.created_at ASC
");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $due_soon[] = $row;
    }
}


// --------------------- BOOKING TRENDS (last 7 days) ---------------------
$booking_trends = [];
$trend_result = $conn->query("
    SELECT 
        DATE(created_at) AS day,
        COUNT(*) AS cnt
    FROM spa_transactions
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at)
");

while ($t = $trend_result->fetch_assoc()) {
    $booking_trends[] = [
        "date" => date("M j", strtotime($t['day'])),
        "count" => intval($t['cnt'])
    ];
}


// --------------------- SERVICE CATEGORY DISTRIBUTION ---------------------
$service_distribution = [];
$dist_result = $conn->query("
    SELECT 
        service_categories.name AS category,
        COUNT(services.id) AS cnt
    FROM services
    LEFT JOIN service_categories ON service_categories.id = services.category_id
    GROUP BY service_categories.name
");

while ($s = $dist_result->fetch_assoc()) {
    $service_distribution[] = [
        "category" => $s['category'] ?? "Uncategorized",
        "count" => intval($s['cnt'])
    ];
}


// --------------------- RETURN JSON ---------------------
echo json_encode([
    "total_services"       => $total_services,
    "active_bookings"      => $active_bookings,
    "upcoming_bookings"    => $upcoming_bookings,
    "due_soon"             => $due_soon,
    "booking_trends"       => $booking_trends,
    "service_distribution" => $service_distribution
]);
