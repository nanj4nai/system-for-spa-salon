<?php
session_start();
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['services']) || !is_array($data['services'])) {
    echo json_encode([
        "success" => false,
        "message" => "Please select at least one service."
    ]);
    exit;
}

$services = [];

foreach ($data['services'] as $s) {
    if (empty($s['service_id']) || empty($s['variant_id'])) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid service selection. Please reselect services."
        ]);
        exit;
    }

    $services[] = [
        "service_id" => (int) $s['service_id'],
        "variant_id" => (int) $s['variant_id'],

        // Optional UX snapshot (NOT authoritative)
        "display_name" => $s['name'] ?? null,
        "selected_price" => isset($s['price'])
            ? (float) $s['price']
            : null
    ];
}

/*
|--------------------------------------------------------------------------
| Persist selection
|--------------------------------------------------------------------------
| Prices will be recalculated later (step 3 / cashier)
*/
$_SESSION['booking_services'] = $services;

/*
|--------------------------------------------------------------------------
| Reset dependent state
|--------------------------------------------------------------------------
*/
unset($_SESSION['booking_price_snapshot']);
unset($_SESSION['booking_nonce']);
unset($_SESSION['confirm_attempts']);

/*
|--------------------------------------------------------------------------
| Schedule invalidation
|--------------------------------------------------------------------------
*/
if (!empty($data['reset_schedule'])) {
    unset($_SESSION['booking_schedule']);
    $_SESSION['schedule_invalidated'] = true;
}

echo json_encode([
    "success" => true
]);
