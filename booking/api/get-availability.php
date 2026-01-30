<?php
date_default_timezone_set("Asia/Manila");
session_start();
header("Content-Type: application/json");
require_once "../../php/db.php";

/* -----------------------------
   Validate input
----------------------------- */
$date    = $_GET['date'] ?? null;
$staffId = $_GET['staff_id'] ?? null;

if (!$date || empty($_SESSION['booking_services'])) {
    echo json_encode(["slots" => []]);
    exit;
}

/* -----------------------------
   1. Compute TOTAL duration
----------------------------- */
$variantIds = array_filter(
    array_column($_SESSION['booking_services'], 'variant_id'),
    fn($v) => !empty($v)
);

if (!$variantIds) {
    echo json_encode(["slots" => []]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($variantIds), '?'));

$sql = "
    SELECT SUM(duration_minutes) AS total_minutes
    FROM service_variants
    WHERE id IN ($placeholders)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat("i", count($variantIds)), ...$variantIds);
$stmt->execute();

$totalMinutes = (int) ($stmt->get_result()->fetch_assoc()['total_minutes'] ?? 0);

if ($totalMinutes <= 0) {
    echo json_encode(["slots" => []]);
    exit;
}

/* -----------------------------
   2. Business hours
----------------------------- */
$OPEN_TIME  = "10:00:00";
$CLOSE_TIME = "20:00:00";
$INTERVAL   = 30; // minutes

/* -----------------------------
   3. Determine candidate staff
----------------------------- */
$staffIds = [];

if (!empty($staffId)) {
    // Specific staff chosen
    $staffIds[] = (int) $staffId;
} else {
    // Any available staff
    $res = $conn->query("
        SELECT id
        FROM employees
        WHERE is_active = 1
    ");

    while ($row = $res->fetch_assoc()) {
        $staffIds[] = (int) $row['id'];
    }
}

if (!$staffIds) {
    echo json_encode(["slots" => []]);
    exit;
}

/* -----------------------------
   4. Preload appointments per staff
----------------------------- */
$blockedByStaff = [];

$staffPlaceholders = implode(',', array_fill(0, count($staffIds), '?'));

$sql = "
    SELECT
        aps.employee_id,
        a.start_time,
        a.end_time
    FROM appointments a
    JOIN appointment_services aps
        ON aps.appointment_id = a.id
    WHERE a.appointment_date = ?
      AND a.status NOT IN ('cancelled','no_show')
      AND aps.employee_id IN ($staffPlaceholders)
";

$params = array_merge([$date], $staffIds);
$types  = "s" . str_repeat("i", count($staffIds));

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $blockedByStaff[$row['employee_id']][] = [
        "start" => $row['start_time'],
        "end"   => $row['end_time']
    ];
}


/* -----------------------------
   4.5 Time context
----------------------------- */
$today = date("Y-m-d");
$isToday = ($date === $today);
$nowTs = time();

/* -----------------------------
   5. Generate slots
----------------------------- */
$slots = [];

$cursor = strtotime("$date $OPEN_TIME");
$endDay = strtotime("$date $CLOSE_TIME");

while (($cursor + $totalMinutes * 60) <= $endDay) {

    if ($isToday) {
        $slotTs = strtotime("$date " . date("H:i:s", $cursor));
        if ($slotTs <= $nowTs) {
            $cursor += $INTERVAL * 60;
            continue;
        }
    }

    $slotStart = date("H:i:s", $cursor);
    $slotEnd   = date("H:i:s", $cursor + $totalMinutes * 60);

    $available = false;

    foreach ($staffIds as $sid) {
        $blocked = $blockedByStaff[$sid] ?? [];

        if (isSlotAvailable($slotStart, $slotEnd, $blocked)) {
            $available = true;
            break;
        }
    }

    if ($available) {
        $slots[] = [
            "label" => date("g:i A", $cursor),
            "value" => $slotStart
        ];
    }

    $cursor += $INTERVAL * 60;
}

/* -----------------------------
   Output
----------------------------- */
echo json_encode(["slots" => $slots]);
exit;

/* =====================================================
   Helpers
===================================================== */
function isSlotAvailable($start, $end, $blocked)
{
    foreach ($blocked as $b) {
        if ($start < $b['end'] && $end > $b['start']) {
            return false;
        }
    }
    return true;
}
