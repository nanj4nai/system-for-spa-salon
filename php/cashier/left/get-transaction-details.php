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

/* =========================
   VAT SETTINGS
========================= */
$vat_rate = 0;
$set = $conn->query("
    SELECT vat_rate
    FROM settings
    ORDER BY id ASC
    LIMIT 1
");
if ($set && $set->num_rows > 0) {
    $vat_rate = (float)$set->fetch_assoc()['vat_rate'];
}

/* =========================
   RECALCULATE TRANSACTION
========================= */
recalcTransaction($conn, $transaction_id);
$serviceProductsByService = getServiceProductUsage($conn, $transaction_id);

/* =========================
   TRANSACTION + CLIENT
========================= */
$stmt = $conn->prepare("
    SELECT 
        t.id AS transaction_id,
        t.transaction_number,
        t.status,
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
$include_vat = (int)$transaction['include_vat'];

/* =========================
   SERVICES
========================= */
$stmt = $conn->prepare("
    SELECT
        ts.id,
        ts.appointment_service_id,

        s.name AS service_name,

        aps.employee_id,
        emp.full_name AS staff_name,

        aps.variant_id,
        v.name AS variant_name,

        a.source AS appointment_source,

        COALESCE(ts.unit_price, s.base_price) AS unit_price,
        (ts.unit_price IS NOT NULL) AS has_variant_price,
        COALESCE(ts.unit_price, s.base_price) AS total_price

    FROM spa_transaction_services ts

    JOIN appointment_services aps
        ON aps.id = ts.appointment_service_id

    JOIN services s
        ON s.id = ts.service_id

    LEFT JOIN employees emp
        ON emp.id = aps.employee_id

    LEFT JOIN service_variants v
        ON v.id = aps.variant_id

    JOIN appointments a
        ON a.id = aps.appointment_id

    WHERE ts.transaction_id = ?
");

$stmt->bind_param("i", $transaction_id);
$stmt->execute();

$services = [];
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $asid = $row['appointment_service_id'];

    // Attach products
    $row['products_used'] = $serviceProductsByService[$asid] ?? [];

    // ✅ SIMPLE + CORRECT RULE
    $row['is_booking_service'] = ($row['appointment_source'] === 'online');

    $services[] = $row;
}

/* =========================
   PRODUCT USAGE TOTAL
========================= */
$product_usage_total = 0;
foreach ($services as $s) {
    foreach ($s['products_used'] as $p) {
        $product_usage_total += (float)$p['total_price'];
    }
}

/* =========================
   EXTRA PRODUCTS
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

// Services ARE billable
$services_total = array_sum(array_column($services, 'total_price'));

// Consumables are NOT billable
$consumables_total = $product_usage_total;

// Extra products ARE billable
$extra_products_total = array_sum(array_column($products, 'total_price'));

// Only billable items go into subtotal
$billable_subtotal = $services_total + $extra_products_total;

// VAT applies only on billable subtotal
if ($include_vat && $billable_subtotal > 0) {
    $vat_amount = round(($billable_subtotal * $vat_rate) / 100, 2);
    $grand_total = round($billable_subtotal + $vat_amount, 2);
} else {
    $vat_amount = 0;
    $grand_total = round($billable_subtotal, 2);
}

/* =========================
   PAYMENTS (AUTHORITATIVE)
========================= */
$stmt = $conn->prepare("
    SELECT
        SUM(amount) AS total_paid,
        MAX(receipt_number) AS last_reference,
        MAX(payment_method) AS last_method
    FROM payments
    WHERE transaction_id = ?
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();

$total_paid = (float)($pay['total_paid'] ?? 0);
$change_amount = max(0, $total_paid - $grand_total);

/* =========================
   PAYMENTS (DETAILED LIST)
========================= */
$stmt = $conn->prepare("
    SELECT
        id,
        amount,
        payment_method,
        receipt_number,
        payment_date
    FROM payments
    WHERE transaction_id = ?
    ORDER BY payment_date ASC
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


/* =========================
   PAYMENT STATUS
========================= */
if ($total_paid <= 0) {
    $payment_status = "unpaid";
} elseif ($total_paid + 0.01 < $grand_total) {
    $payment_status = "partial";
} else {
    $payment_status = "paid";
}

$transaction['balance_due'] = max(
    0,
    round($grand_total - $total_paid, 2)
);


/* =========================
   ATTACH PAYMENT DATA
========================= */
$transaction['amount_paid'] = round($total_paid, 2);
$transaction['change_amount'] = round($change_amount, 2);
$transaction['payment_status'] = $payment_status;
$transaction['reference_number'] = $pay['last_reference'] ?? null;
$transaction['payment_method'] = $pay['last_method'] ?? null;

/* =========================
   RESPONSE
========================= */
echo json_encode([
    "success" => true,
    "transaction" => $transaction,
    "services" => $services,
    "products" => $products,
    "payments" => $payments,
    "totals" => [
        "services_total" => round($services_total, 2),
        "consumables_total" => round($consumables_total, 2), // informational only
        "extra_products_total" => round($extra_products_total, 2),
        "products_total" => round($extra_products_total, 2), // billable products
        "subtotal" => round($billable_subtotal, 2),
        "vat_rate" => ($include_vat && $billable_subtotal > 0) ? $vat_rate : 0,
        "vat_amount" => round($vat_amount, 2),
        "grand_total" => round($grand_total, 2),
        "include_vat" => $include_vat
    ]

]);
