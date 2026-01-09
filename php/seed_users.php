<?php
require_once "db.php";

/* ===== ENSURE STAFF ROLES EXIST ===== */
$conn->query("INSERT IGNORE INTO staff_roles (name) VALUES ('Owner')");
$conn->query("INSERT IGNORE INTO staff_roles (name) VALUES ('Front Desk Cashier')");

/* ===== CREATE EMPLOYEES ===== */

// Optional reference employee (NOT linked to admin user)
$conn->query("
    INSERT IGNORE INTO employees (full_name, staff_role_id, is_active)
    VALUES (
        'System Administrator',
        (SELECT id FROM staff_roles WHERE name = 'Owner' LIMIT 1),
        1
    )
");

// Cashier employee (REQUIRED)
$conn->query("
    INSERT IGNORE INTO employees (full_name, staff_role_id, is_active)
    VALUES (
        'Front Desk Cashier',
        (SELECT id FROM staff_roles WHERE name = 'Front Desk Cashier' LIMIT 1),
        1
    )
");

/* ===== CREATE USERS ===== */

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

// Cashier user (MUST have employee)
$cashierPass = password_hash("cashier123", PASSWORD_DEFAULT);
$conn->query("
    INSERT IGNORE INTO users (username, password, role, employee_id)
    VALUES (
        'cashier',
        '$cashierPass',
        'cashier',
        (SELECT id FROM employees WHERE full_name = 'Front Desk Cashier' LIMIT 1)
    )
");

echo 'Seed users and employees created successfully';
