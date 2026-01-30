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
    FROM appointments
    WHERE appointment_date = CURDATE()
    AND status IN ('confirmed', 'checked_in', 'completed')
")->fetch_assoc()['cnt'] ?? 0;

// --------------------- UPCOMING BOOKINGS ---------------------
$upcoming_bookings = $conn->query("
    SELECT COUNT(*) AS cnt
    FROM appointments
    WHERE appointment_date > CURDATE()
    AND appointment_date <= DATE_ADD(CURDATE(), INTERVAL $due_in DAY)
    AND status IN ('confirmed', 'pending')
")->fetch_assoc()['cnt'] ?? 0;

// --------------------- UPCOMING APPOINTMENTS LIST ---------------------
$due_soon = [];

$result = $conn->query("
    SELECT
        a.id,
        a.appointment_date,
        a.start_time,
        c.full_name AS client_name,
        (
            SELECT s.name
            FROM appointment_services aps
            JOIN services s ON s.id = aps.service_id
            WHERE aps.appointment_id = a.id
            LIMIT 1
        ) AS service_name
    FROM appointments a
    JOIN clients c ON c.id = a.client_id
    WHERE a.appointment_date >= CURDATE()
    AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL $due_in DAY)
    AND a.status IN ('confirmed', 'pending')
    ORDER BY a.appointment_date ASC, a.start_time ASC
");

while ($row = $result->fetch_assoc()) {
    $due_soon[] = [
        "client_name" => $row["client_name"],
        "service_name" => $row["service_name"] ?? "—",
        "appointment_time" =>
        $row["appointment_date"] . " " . $row["start_time"]
    ];
}


// --------------------- BOOKING TRENDS (last 7 days) ---------------------
$booking_trends = [];

$trend_result = $conn->query("
    SELECT
        appointment_date AS day,
        COUNT(*) AS cnt
    FROM appointments
    WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY appointment_date
    ORDER BY appointment_date
");

while ($t = $trend_result->fetch_assoc()) {
    $booking_trends[] = [
        "date" => date("M j", strtotime($t['day'])),
        "count" => (int)$t['cnt']
    ];
}

// --------------------- SERVICE CATEGORY DISTRIBUTION ---------------------
$service_distribution = [];

$dist_result = $conn->query("
    SELECT
        sc.name AS category,
        COUNT(aps.id) AS cnt
    FROM appointment_services aps
    JOIN services s ON s.id = aps.service_id
    LEFT JOIN service_categories sc ON sc.id = s.category_id
    JOIN appointments a ON a.id = aps.appointment_id
    WHERE a.status IN ('completed', 'confirmed')
    GROUP BY sc.name
");

while ($s = $dist_result->fetch_assoc()) {
    $service_distribution[] = [
        "category" => $s['category'] ?? "Uncategorized",
        "count" => (int)$s['cnt']
    ];
}

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
