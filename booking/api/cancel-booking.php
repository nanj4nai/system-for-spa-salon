<?php
session_start();

/*
 | Booking cancellation = full reset
 | (except maybe flash messages later)
 */

unset(
    $_SESSION['booking_client'],
    $_SESSION['booking_services'],
    $_SESSION['booking_schedule']
);

// Optional: regenerate session ID for safety
session_regenerate_id(true);

header("Location: ../index.php");
exit;
