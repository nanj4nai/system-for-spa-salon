<?php
session_start();
require_once "../php/db.php";

if (empty($_SESSION['booking_client']) || empty($_SESSION['booking_services'])) {
    header("Location: index.php");
    exit;
}

$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();

$VAT_RATE = ((float)($settings['vat_rate'] ?? 12)) / 100;

/* ---------------------------
   Build price snapshot (AUTHORITATIVE)
--------------------------- */
$variantIds = array_column($_SESSION['booking_services'], 'variant_id');
$variantIds = array_map('intval', $variantIds);

if (!$variantIds) {
    header("Location: step2.php?error=invalid_services");
    exit;
}

$placeholders = implode(',', array_fill(0, count($variantIds), '?'));

$sql = "
SELECT
    sv.id AS variant_id,
    sv.name AS variant_name,
    sv.price AS variant_price,
    s.base_price AS service_price
FROM service_variants sv
JOIN services s ON s.id = sv.service_id
WHERE sv.id IN ($placeholders)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat('i', count($variantIds)), ...$variantIds);
$stmt->execute();
$res = $stmt->get_result();

$priceMap = [];
while ($row = $res->fetch_assoc()) {
    $priceMap[(int)$row['variant_id']] = [
        'name'  => $row['variant_name'],
        'price' => $row['variant_price'] > 0
            ? (float)$row['variant_price']
            : (float)$row['service_price']
    ];
}

$snapshotServices = [];
$subtotal = 0;

foreach ($_SESSION['booking_services'] as $s) {
    $vid = (int)$s['variant_id'];
    if (!isset($priceMap[$vid])) {
        header("Location: step2.php?error=price_mismatch");
        exit;
    }

    $price = $priceMap[$vid]['price'];
    $subtotal += $price;

    $snapshotServices[] = [
        'service_id' => (int)$s['service_id'],
        'variant_id' => $vid,
        'name'       => $priceMap[$vid]['name'],
        'price'      => round($price, 2)
    ];
}

$vat   = round($subtotal * $VAT_RATE, 2);
$total = round($subtotal + $vat, 2);

/* ---------------------------
   Lock snapshot (30 minutes)
--------------------------- */
$_SESSION['booking_price_snapshot'] = [
    'services'   => $snapshotServices,
    'subtotal'   => round($subtotal, 2),
    'vat'        => $vat,
    'total'      => $total,
    'vat_rate'   => $VAT_RATE,
    'locked_at'  => time(),
    'expires_at' => time() + (30 * 60)
];

$spaName = $settings['spa_name'] ?? 'My Wellness Spa';
$logoPath = $settings['logo_path'] ?? null;
$spaContact = $settings['contact_number'] ?? '';

$logoUrl = null;
if (!empty($logoPath)) {
    $logoUrl = '../' . ltrim($logoPath, '/');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Schedule Appointment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($logoUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($logoUrl) ?>?v=<?= time() ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
</head>

<body class="font-poppins bg-gray-50 min-h-screen">

    <main class="max-w-6xl mx-auto px-4 py-6 pb-28">

        <!-- Progress -->
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold">
                    3
                </div>

                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        class="h-2 bg-indigo-600 rounded-full transition-all duration-300"
                        style="width: calc(100% / 6 * 3);">
                    </div>
                </div>

                <span class="text-xs sm:text-sm text-gray-500 whitespace-nowrap">
                    Step 3 of 6
                </span>
            </div>

            <h2 class="text-xl sm:text-2xl font-semibold mt-4 text-gray-800">
                Choose date & time
            </h2>
            <p class="text-sm text-gray-500">
                Select your preferred therapist and schedule.
            </p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">

            <!-- Scheduler -->
            <section class="lg:col-span-2 space-y-6">

                <!-- Therapist -->
                <div class="bg-white rounded-2xl border p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">
                        Staff (optional)
                    </h3>
                    <p class="text-xs text-gray-500 mb-2">
                        Leave blank to auto-assign any available staff.
                    </p>

                    <div class="space-y-4">
                        <?php foreach ($_SESSION['booking_price_snapshot']['services'] as $s): ?>
                            <div class="bg-gray-50 border rounded-xl p-4">
                                <p class="font-medium text-gray-800">
                                    <?= htmlspecialchars($s['name']) ?>
                                </p>

                                <select
                                    class="staff-select w-full mt-2 rounded-xl border px-4 py-2"
                                    data-variant="<?= $s['variant_id'] ?>">
                                    <option value="">Any available staff</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>

                <!-- Date -->
                <div class="bg-white rounded-2xl border p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">
                        Date
                    </h3>

                    <input
                        type="date"
                        id="datePicker"
                        class="w-full rounded-xl border px-4 py-3 focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Time Slots -->
                <div class="bg-white rounded-2xl border p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">
                        Available Times
                    </h3>

                    <div
                        id="timeSlots"
                        class="grid grid-cols-3 sm:grid-cols-4 gap-3 text-sm">
                        <!-- slots injected by JS -->
                    </div>
                </div>

            </section>

            <!-- Summary -->
            <aside class="hidden lg:block bg-white rounded-2xl border p-5 h-fit sticky top-28 space-y-4">
                <h3 class="font-semibold text-gray-800">
                    Booking Summary
                </h3>

                <!-- Services -->
                <div>
                    <p class="text-xs uppercase text-gray-400 mb-2">Services</p>

                    <!-- Service list -->
                    <ul class="text-sm text-gray-600 space-y-2">
                        <?php foreach ($_SESSION['booking_price_snapshot']['services'] as $s): ?>
                            <li class="flex justify-between">
                                <span><?= htmlspecialchars($s['name']) ?></span>
                                <span>₱<?= number_format($s['price'], 2) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Price breakdown -->
                    <div class="border-t mt-3 pt-3 space-y-1 text-sm">
                        <div class="flex justify-between text-gray-500">
                            <span>Subtotal</span>
                            <span>
                                ₱<?= number_format($_SESSION['booking_price_snapshot']['subtotal'], 2) ?>
                            </span>
                        </div>

                        <div class="flex justify-between text-gray-500">
                            <span>
                                VAT (<?= (int)($settings['vat_rate'] ?? 12) ?>%)
                            </span>
                            <span>
                                ₱<?= number_format($_SESSION['booking_price_snapshot']['vat'], 2) ?>
                            </span>
                        </div>

                        <div class="flex justify-between font-semibold text-gray-800 pt-2">
                            <span>Total (VAT included)</span>
                            <span>
                                ₱<?= number_format($_SESSION['booking_price_snapshot']['total'], 2) ?>
                            </span>
                        </div>
                    </div>
                </div>


                <div class="border-t pt-4 space-y-3 text-sm">

                    <!-- Staff -->
                    <div>
                        <p class="text-xs uppercase text-gray-400 mb-2">Staff per Service</p>
                        <ul id="summaryStaffList" class="space-y-1 text-sm text-gray-700">
                            <?php foreach ($_SESSION['booking_price_snapshot']['services'] as $s): ?>
                                <li
                                    class="flex justify-between"
                                    data-variant="<?= (int)$s['variant_id'] ?>">
                                    <span class="truncate"><?= htmlspecialchars($s['name']) ?></span>
                                    <span class="text-gray-500">Any available</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>


                    <!-- Date -->
                    <div class="flex justify-between">
                        <span class="text-gray-500">Date</span>
                        <span id="summaryDate" class="font-medium text-gray-800">
                            —
                        </span>
                    </div>

                    <!-- Time -->
                    <div class="flex justify-between">
                        <span class="text-gray-500">Time</span>
                        <span id="summaryTime" class="font-medium text-gray-800">
                            —
                        </span>
                    </div>

                </div>
            </aside>
        </div>
    </main>

    <!-- Sticky Actions -->
    <div class="fixed bottom-0 inset-x-0 bg-white border-t shadow-lg z-50">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-col sm:flex-row gap-3 justify-between">

            <a href="step2.php?edit=1"
                onclick="allowLeave = true"
                class="text-sm text-gray-500 hover:text-gray-700">
                ← Back
            </a>

            <button
                id="continueBtn"
                disabled
                class="relative w-full sm:w-auto sm:min-w-[160px]
           bg-indigo-600 text-white px-6 py-3 rounded-xl font-semibold
           disabled:opacity-50 flex items-center justify-center gap-2">

                <span id="continueText">Continue →</span>

                <!-- Spinner -->
                <svg
                    id="continueSpinner"
                    class="hidden w-5 h-5 animate-spin text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24">
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4">
                    </circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                    </path>
                </svg>
            </button>
        </div>
    </div>
    <!-- Mobile Summary Toggle -->
    <button
        id="mobileSummaryToggle"
        class="lg:hidden fixed bottom-[76px] inset-x-0 mx-4
           bg-white border rounded-xl shadow-lg
           px-4 py-3 flex items-center justify-between z-40">

        <span class="text-sm font-medium text-gray-700">
            View booking summary
        </span>

        <span class="text-indigo-600 font-semibold text-sm">
            ₱<?= number_format(
                    $_SESSION['booking_price_snapshot']['total'],
                    2
                ) ?>
        </span>
    </button>
    <!-- Mobile Summary Drawer -->
    <div
        id="mobileSummaryDrawer"
        class="lg:hidden fixed inset-x-0 bottom-0
           bg-white rounded-t-2xl shadow-2xl border-t
           translate-y-full transition-transform duration-300
           z-50 max-h-[85vh] overflow-y-auto">

        <div class="p-5 space-y-4">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">
                    Booking Summary
                </h3>
                <button
                    id="closeMobileSummary"
                    class="text-gray-400 hover:text-gray-600 text-sm">
                    Close ✕
                </button>
            </div>

            <!-- Services -->
            <div>
                <p class="text-xs uppercase text-gray-400 mb-2">Services</p>
                <ul class="text-sm text-gray-600 space-y-2">
                    <?php foreach ($_SESSION['booking_price_snapshot']['services'] as $s): ?>
                        <li class="flex justify-between">
                            <span><?= htmlspecialchars($s['name']) ?></span>
                            <span>₱<?= number_format($s['price'], 2) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Price breakdown -->
                <div class="border-t mt-3 pt-3 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span>Subtotal</span>
                        <span>
                            ₱<?= number_format($_SESSION['booking_price_snapshot']['subtotal'], 2) ?>
                        </span>
                    </div>

                    <div class="flex justify-between text-gray-500">
                        <span>
                            VAT (<?= (int)($settings['vat_rate'] ?? 12) ?>%)
                        </span>
                        <span>
                            ₱<?= number_format($_SESSION['booking_price_snapshot']['vat'], 2) ?>
                        </span>
                    </div>

                    <div class="flex justify-between font-semibold text-gray-800 pt-2">
                        <span>Total (VAT included)</span>
                        <span>
                            ₱<?= number_format($_SESSION['booking_price_snapshot']['total'], 2) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Staff -->
            <div>
                <p class="text-xs uppercase text-gray-400 mb-2">
                    Staff per Service
                </p>
                <ul id="mobileSummaryStaffList" class="space-y-1 text-sm text-gray-700">
                    <?php foreach ($_SESSION['booking_price_snapshot']['services'] as $s): ?>
                        <li
                            class="flex justify-between"
                            data-variant="<?= (int)$s['variant_id'] ?>">
                            <span class="truncate"><?= htmlspecialchars($s['name']) ?></span>
                            <span class="text-gray-500">Any available</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Date & Time -->
            <div class="border-t pt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Date</span>
                    <span id="mobileSummaryDate" class="font-medium">—</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Time</span>
                    <span id="mobileSummaryTime" class="font-medium">—</span>
                </div>
            </div>

        </div>
    </div>

    <!-- Page Loader -->
    <div
        id="pageLoader"
        class="hidden fixed inset-0 z-[9999]
           bg-white/80 backdrop-blur-sm
           flex items-center justify-center">

        <div class="flex flex-col items-center gap-4">
            <svg
                class="w-10 h-10 animate-spin text-indigo-600"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24">
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4">
                </circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                </path>
            </svg>

            <p class="text-sm text-gray-600">
                Saving your schedule…
            </p>
        </div>
    </div>

    <script src="js/step3.js"></script>
    <script>
        let allowLeave = false;

        window.addEventListener("beforeunload", (e) => {
            if (allowLeave) return;

            e.preventDefault();
            e.returnValue = ""; // REQUIRED for browser confirmation
        });
    </script>
</body>

</html>