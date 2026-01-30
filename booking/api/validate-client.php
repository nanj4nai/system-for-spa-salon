<?php
session_start();
header("Content-Type: application/json");

require_once "../../php/db.php";

// -----------------------------
// BASIC RATE LIMIT (SESSION)
// -----------------------------
$_SESSION['booking_attempts'] = ($_SESSION['booking_attempts'] ?? 0) + 1;

if ($_SESSION['booking_attempts'] > 10) {
    echo json_encode([
        "success" => false,
        "message" => "Too many attempts. Please try again later."
    ]);
    exit;
}

// -----------------------------
// HONEYPOT (BOT DETECTION)
// -----------------------------
if (!empty($_POST['website'] ?? '')) {
    echo json_encode(["success" => true]);
    exit;
}

// -----------------------------
// INPUT VALIDATION
// -----------------------------
$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$contact  = trim($_POST['contact_number'] ?? '');

if ($fullName === '' || $email === '' || $contact === '') {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required."
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid email address."
    ]);
    exit;
}

// ✅ PHONE VALIDATION (NOW IN CORRECT PLACE)
if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $contact)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid contact number."
    ]);
    exit;
}

// -----------------------------
// STORE TEMP DATA IN SESSION
// -----------------------------
$_SESSION['booking_client'] = [
    "full_name" => $fullName,
    "email" => $email,
    "contact_number" => $contact
];

// reset attempts on success
$_SESSION['booking_attempts'] = 0;

echo json_encode(["success" => true]);
