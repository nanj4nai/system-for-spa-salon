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
// =========================
// VAT SETTINGS
// =========================
$vat_rate = 0;

$set = $conn->query("
    SELECT vat_rate
    FROM settings
    ORDER BY id ASC
    LIMIT 1
");

if ($set && $set->num_rows > 0) {
    $vat_rate = (float)$set->fetch_assoc()['vat_rate']; // e.g. 12.00
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
        t.include_vat,
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
$include_vat    = (int)$transaction['include_vat'];
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
    $services[] = $row;
}

$product_usage_total = 0;

foreach ($services as $s) {
    if (!empty($s['products_used'])) {
        foreach ($s['products_used'] as $p) {
            $product_usage_total += (float)$p['total_price'];
        }
    }
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
    $services_total += (float)$s['total_price']; // service only
}

$extra_products_total = 0;
foreach ($products as $p) {
    $extra_products_total += (float)$p['total_price'];
}

$products_total = $product_usage_total + $extra_products_total;

$subtotal = $services_total + $products_total;
if ($include_vat === 1) {
    $vat_amount = round(($subtotal * $vat_rate) / 100, 2);
    $grand_total = round($subtotal + $vat_amount, 2);
} else {
    $vat_amount = 0;
    $grand_total = round($subtotal, 2);
}

echo json_encode([
    "success" => true,
    "transaction" => $transaction,
    "services" => $services,
    "products" => $products,
    "totals" => [
        "services_total" => $services_total,
        "consumables_total" => $product_usage_total,
        "extra_products_total" => $extra_products_total,
        "products_total" => $products_total,
        "subtotal" => $subtotal,
        "vat_rate" => $include_vat ? $vat_rate : 0,
        "vat_amount" => $vat_amount,
        "grand_total" => $grand_total,
        "include_vat" => $include_vat
    ]

]);
