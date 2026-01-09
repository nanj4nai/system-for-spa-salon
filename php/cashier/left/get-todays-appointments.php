<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

/* =====================
   FETCH TODAY'S APPOINTMENTS
===================== */
$sql = "
    SELECT
        a.id,
        a.start_time,
        a.status,
        a.source,
        c.full_name AS client_name,

        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS services,
        COUNT(DISTINCT asv.id) AS service_count,

        t.id AS transaction_id,
        t.payment_status

    FROM appointments a
    JOIN clients c ON c.id = a.client_id

    LEFT JOIN appointment_services asv
        ON asv.appointment_id = a.id
    LEFT JOIN services s
        ON s.id = asv.service_id

    LEFT JOIN spa_transactions t
        ON t.appointment_id = a.id

    WHERE
        a.appointment_date = CURDATE()
        AND a.status IN ('confirmed', 'checked_in')

    GROUP BY a.id
    ORDER BY
        a.status = 'checked_in' DESC,
        a.start_time ASC
";

$res = $conn->query($sql);

$appointments = [];

while ($row = $res->fetch_assoc()) {
    $appointments[] = $row;
}

echo json_encode([
    "success" => true,
    "appointments" => $appointments
]);
