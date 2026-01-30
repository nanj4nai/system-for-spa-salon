<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false]);
    exit;
}

require_once "db.php";

$sql = "
SELECT
    a.id AS appointment_id,
    c.full_name AS client_name,
    t.total_amount,
    t.amount_paid,
    t.balance_due,
    t.payment_status,
    a.payment_reference,
    a.payment_proof,
    t.created_at
FROM appointments a
JOIN clients c ON c.id = a.client_id
JOIN spa_transactions t ON t.appointment_id = a.id
WHERE
    a.source = 'online'
    AND a.payment_verified = 0
    AND t.status = 'pending_verification'
ORDER BY t.created_at ASC
";

$res = $conn->query($sql);

echo json_encode([
    'success' => true,
    'items' => $res ? $res->fetch_all(MYSQLI_ASSOC) : []
]);
