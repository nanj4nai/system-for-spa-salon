<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* =======================
   LIST CLIENTS
======================= */
if ($action === 'list') {
    $res = $conn->query("
        SELECT 
            id,
            full_name,
            contact_number,
            email,
            address,
            notes,
            created_at,
            DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') AS created_at_formatted
        FROM clients
        ORDER BY created_at DESC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* =======================
   CLIENT VISIT HISTORY
======================= */
if ($action === 'visits') {
    $clientId = intval($_GET['id'] ?? 0);

    if ($clientId <= 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(a.appointment_date, '%M %d, %Y') AS appointment_date,
            a.status,
            COALESCE(SUM(
                CASE 
                    WHEN sv.price IS NOT NULL THEN sv.price
                    ELSE s.base_price
                END
            ), 0) AS total_amount,
            COALESCE(
                GROUP_CONCAT(DISTINCT s.name SEPARATOR ', '),
                '—'
            ) AS services
        FROM appointments a
        LEFT JOIN appointment_services aps 
            ON aps.appointment_id = a.id
        LEFT JOIN services s 
            ON s.id = aps.service_id
        LEFT JOIN service_variants sv 
            ON sv.id = aps.variant_id
        WHERE a.client_id = ?
        AND a.status IN ('checked_in', 'completed')
        GROUP BY a.id
        ORDER BY a.appointment_date DESC
    ");

    $stmt->bind_param("i", $clientId);
    $stmt->execute();

    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* =======================
   ADD / EDIT CLIENT
======================= */
$id      = intval($_POST['id'] ?? 0);
$name    = trim($_POST['full_name'] ?? '');
$contact = trim($_POST['contact_number'] ?? '');
$email   = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$notes   = trim($_POST['notes'] ?? '');

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Client name is required']);
    exit;
}

if ($id > 0) {
    // UPDATE
    $stmt = $conn->prepare("
        UPDATE clients
        SET full_name = ?, contact_number = ?, email = ?, address = ?, notes = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $name, $contact, $email, $address, $notes, $id);
} else {
    // INSERT
    $stmt = $conn->prepare("
        INSERT INTO clients (full_name, contact_number, email, address, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $name, $contact, $email, $address, $notes);
}

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
    exit;
}

echo json_encode(['success' => true]);
