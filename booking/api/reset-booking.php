<?php
session_start();

unset(
    $_SESSION['booking_client'],
    $_SESSION['booking_services'],
    $_SESSION['booking_schedule'],
    $_SESSION['booking_price_snapshot'],
    $_SESSION['booking_nonce'],
    $_SESSION['confirm_attempts']
);

echo json_encode(['success' => true]);
