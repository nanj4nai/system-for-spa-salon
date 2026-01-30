<?php
session_start();
header("Content-Type: application/json");
require_once "../../php/db.php";

/* -------------------------------------------------
   Parse input
------------------------------------------------- */
$data = json_decode(file_get_contents("php://input"), true);

$date  = $data['date'] ?? null;
$time  = $data['time'] ?? null;
$staffByVariant = $data['staff_by_variant'] ?? null;

/* -------------------------------------------------
   Basic validation
------------------------------------------------- */
if (
    !$date ||
    !$time ||
    empty($_SESSION['booking_services']) ||
    !is_array($staffByVariant)
) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid schedule data."
    ]);
    exit;
}

/* -------------------------------------------------
   Validate variant ownership
------------------------------------------------- */
$validVariantIds = array_column($_SESSION['booking_services'], 'variant_id');

foreach ($staffByVariant as $variantId => $employeeId) {
    if (!in_array((int)$variantId, $validVariantIds, true)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid staff assignment."
        ]);
        exit;
    }
}

/* -------------------------------------------------
   Compute TOTAL duration (min/max window)
------------------------------------------------- */
$variantIds = array_filter($validVariantIds);

$placeholders = implode(',', array_fill(0, count($variantIds), '?'));

$sql = "
    SELECT SUM(duration_minutes) AS total_minutes
    FROM service_variants
    WHERE id IN ($placeholders)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat("i", count($variantIds)), ...$variantIds);
$stmt->execute();

$totalMinutes = (int) (
    $stmt->get_result()->fetch_assoc()['total_minutes'] ?? 0
);

if ($totalMinutes <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid service duration."
    ]);
    exit;
}

/* -------------------------------------------------
   Compute start / end time
------------------------------------------------- */
$startTimestamp = strtotime("$date $time");

if (!$startTimestamp) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid date or time."
    ]);
    exit;
}
if ($startTimestamp < time()) {
    echo json_encode([
        "success" => false,
        "message" => "Selected time has already passed."
    ]);
    exit;
}


$endTimestamp = $startTimestamp + ($totalMinutes * 60);

$startTime = date("H:i:s", $startTimestamp);
$endTime   = date("H:i:s", $endTimestamp);

/* -------------------------------------------------
   Normalize staff map (variant_id => employee_id|null)
------------------------------------------------- */
$normalizedStaff = [];

foreach ($staffByVariant as $variantId => $employeeId) {
    $normalizedStaff[(int)$variantId] =
        $employeeId !== null ? (int)$employeeId : null;
}

/* -------------------------------------------------
   Save to session (SOURCE OF TRUTH)
------------------------------------------------- */
$_SESSION['booking_schedule'] = [
    'date'              => $date,
    'start_time'        => $startTime,
    'end_time'          => $endTime,
    'duration_minutes' => $totalMinutes,
    'staff_by_variant' => $normalizedStaff
];

/* -------------------------------------------------
   Done
------------------------------------------------- */
echo json_encode([
    "success" => true
]);
exit;
