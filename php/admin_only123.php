<?php
require_once "db.php";

/* ===== ENSURE STAFF ROLES EXIST ===== */
$conn->query("INSERT IGNORE INTO staff_roles (name) VALUES ('Owner')");
$conn->query("INSERT IGNORE INTO staff_roles (name) VALUES ('Front Desk Cashier')");

/* ===== CREATE EMPLOYEES ===== */



// Admin user (NO employee_id)
$adminPass = password_hash("admin123", PASSWORD_DEFAULT);
$conn->query("
    INSERT IGNORE INTO users (username, password, role, employee_id)
    VALUES (
        'admin',
        '$adminPass',
        'admin',
        NULL
    )
");

echo 'Seed users and employees created successfully';