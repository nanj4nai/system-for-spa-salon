<?php
session_start();
header("Content-Type: application/json");

require_once "../../db.php";
require_once "../helpers.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$transaction_id = intval($_GET['transaction_id'] ?? 0);

if ($transaction_id <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid transaction"]);
    exit;
}

/* 🔥 Recalculate transaction */
recalcTransaction($conn, $transaction_id);
$serviceProductsByService = getServiceProductUsage($conn, $transaction_id);


/* =========================
   TRANSACTION + CLIENT
========================= */
$stmt = $conn->prepare("
    SELECT 
        t.id AS transaction_id,
        t.transaction_number,
        t.payment_status,
        t.appointment_id,
        c.full_name,
        c.contact_number
    FROM spa_transactions t
    JOIN clients c ON c.id = t.client_id
    WHERE t.id = ?
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Transaction not found"]);
    exit;
}

$transaction = $res->fetch_assoc();
$appointment_id = (int)$transaction['appointment_id'];

/* =========================
   SERVICES (Transaction-based)
========================= */
$stmt = $conn->prepare("
    SELECT 
        ts.id,
        ts.appointment_service_id,
        s.name AS service_name,
        ts.quantity,
        ts.unit_price,
        ts.total_price
    FROM spa_transaction_services ts
    JOIN services s ON s.id = ts.service_id
    WHERE ts.transaction_id = ?
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();

$res = $stmt->get_result();
$services = [];

while ($row = $res->fetch_assoc()) {
    $asid = $row['appointment_service_id'];

    $row['products_used'] =
        $serviceProductsByService[$asid] ?? [];

    // 🔥 add product totals into service total
    foreach ($row['products_used'] as $p) {
        $row['total_price'] += $p['total_price'];
    }

    $services[] = $row;
}


/* =========================
   EXTRA PRODUCTS (Appointment-based)
========================= */
$products = [];

if ($appointment_id > 0) {
    $stmt = $conn->prepare("
        SELECT
            p.name,
            aep.quantity,
            aep.unit,
            aep.unit_price,
            aep.total_price
        FROM appointment_extra_products aep
        JOIN products p ON p.id = aep.product_id
        WHERE aep.appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* =========================
   TOTALS
========================= */
$services_total = 0;
foreach ($services as $s) {
    $services_total += (float)$s['total_price'];
}

$products_total = 0;
foreach ($products as $p) {
    $products_total += (float)$p['total_price'];
}

$subtotal = $services_total + $products_total;
$grand_total = $subtotal;

echo json_encode([
    "success" => true,
    "transaction" => $transaction,
    "services" => $services,
    "products" => $products,
    "totals" => [
        "services_total" => $services_total,
        "products_total" => $products_total,
        "subtotal" => $subtotal,
        "grand_total" => $grand_total
    ]
]);
