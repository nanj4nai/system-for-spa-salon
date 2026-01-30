<?php
session_start();
require_once "../../php/db.php";

header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

/* ---------------------------
   Rate limiting
---------------------------- */
$_SESSION['payment_attempts'] = ($_SESSION['payment_attempts'] ?? 0) + 1;

if ($_SESSION['payment_attempts'] > 5) {
    echo json_encode([
        'success' => false,
        'message' => 'Too many attempts. Please refresh the page.'
    ]);
    exit;
}

/* ---------------------------
   Guards
---------------------------- */
if (
    empty($_SESSION['booking_client']) ||
    empty($_SESSION['booking_schedule']) ||
    empty($_SESSION['booking_price_snapshot']) ||
    empty($_SESSION['booking_nonce'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'Payment session expired.'
    ]);
    exit;
}

$snapshot = $_SESSION['booking_price_snapshot'];

/* ---------------------------
   Expiration check
---------------------------- */
if (
    empty($snapshot['expires_at']) ||
    time() > $snapshot['expires_at']
) {
    echo json_encode([
        'success' => false,
        'message' => 'Payment window expired.'
    ]);
    exit;
}

/* ---------------------------
   Validate nonce (anti-replay)
---------------------------- */
$inputNonce = $_POST['booking_nonce'] ?? '';

if (!hash_equals($_SESSION['booking_nonce'], $inputNonce)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment session.'
    ]);
    exit;
}

/* ---------------------------
   TODO: Save payment here
   - validate amount
   - validate reference
   - store proof image
   - create transaction row
---------------------------- */

/* ---------------------------
   Cleanup after successful submit
---------------------------- */
unset($_SESSION['booking_nonce']);
unset($_SESSION['payment_attempts']);

/* ---------------------------
   Done
---------------------------- */
echo json_encode([
    'success' => true
]);
exit;
