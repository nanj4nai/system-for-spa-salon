<?php
/* ===========================
   FETCH SPA NAME + LOGO
=========================== */
include 'db.php';

// Default fallbacks
$company_name = "Wellness Spa";
$company_logo = "images/lap-logo.JPG";

// Expect $conn to already exist
if (isset($conn)) {
    $settings = $conn->query(
        "SELECT spa_name, logo_path FROM settings LIMIT 1"
    );

    if ($settings && $settings->num_rows > 0) {
        $s = $settings->fetch_assoc();

        if (!empty($s['spa_name'])) {
            $company_name = $s['spa_name'];
        }

        if (!empty($s['logo_path'])) {
            $company_logo = $s['logo_path'];
        }
    }
}
