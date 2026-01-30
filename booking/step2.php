<?php
session_start();

require_once "../php/db.php";
if (empty($_SESSION['booking_client'])) {
    header("Location: index.php");
    exit;
}
$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();

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
    <title>Select Services</title>
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
    <script>
        window.APP_SETTINGS = {
            vatRate: <?= (float)($settings['vat_rate'] ?? 12.00) ?>
        };
    </script>
</head>

<body class="font-poppins bg-gray-50 min-h-screen">
    <div
        id="scheduleResetWarning"
        class="hidden mb-4 rounded-xl border border-yellow-300
           bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
        <strong>Heads up:</strong>
        You changed your services. Your previously selected date and time
        will be reset when you continue.
    </div>
    <main class="max-w-6xl mx-auto px-4 py-6 pb-28">

        <!-- Progress -->
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold">
                    2
                </div>

                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        class="h-2 bg-indigo-600 rounded-full transition-all duration-300"
                        style="width: calc(100% / 6 * 2);">
                    </div>
                </div>

                <span class="text-xs sm:text-sm text-gray-500 whitespace-nowrap">
                    Step 2 of 6
                </span>
            </div>

            <h2 class="text-xl sm:text-2xl font-semibold mt-4 text-gray-800">
                Choose your services
            </h2>
            <p class="text-sm text-gray-500">
                Select one or more services. Prices shown are service prices.
            </p>
        </div>


        <!-- Services Grid -->
        <div
            id="servicesGrid"
            class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        </div>

    </main>


    <!-- Booking Summary -->
    <aside
        id="bookingSummary"
        class="
        hidden
        mt-8

        bg-white border rounded-2xl shadow-sm
        p-5 space-y-5 p-5 pb-24 space-y-5

        lg:mt-0
        lg:fixed lg:right-6 lg:top-28 lg:w-96
        lg:pb-5 
        lg:shadow-lg
    ">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">
                Booking Summary
            </h3>
            <span class="text-xs text-gray-400">
                Summary
            </span>
        </div>

        <!-- Selected services -->
        <ul
            id="summaryItems"
            class="space-y-3 text-sm text-gray-700"></ul>

        <!-- Divider -->
        <div class="border-t"></div>

        <!-- Price breakdown -->
        <div class="space-y-3 text-sm">

            <div class="flex justify-between text-gray-600">
                <span>Subtotal</span>
                <span id="summarySubtotal">₱0.00</span>
            </div>

            <div class="flex justify-between text-gray-600">
                <span>
                    VAT
                    <span class="text-xs text-gray-400">
                        (<?= $settings['vat_rate'] ?? 12 ?>%)
                    </span>
                </span>
                <span id="summaryVat">₱0.00</span>
            </div>

            <!-- VAT info label stays -->
            <label class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2">
                <span class="text-gray-700">
                    VAT Included
                </span>
                <input
                    type="checkbox"
                    checked
                    disabled
                    class="accent-indigo-600 w-4 h-4 opacity-60 cursor-not-allowed">
            </label>

            <div class="flex justify-between items-center pt-2">
                <span class="font-semibold text-gray-800">
                    Total
                </span>
                <span id="summaryTotal" class="text-2xl font-bold text-indigo-600">
                    ₱0.00
                </span>
            </div>
        </div>

        <!-- Mobile hint -->
        <p class="text-xs text-gray-400 text-center pt-2 lg:hidden">
            Prices are based on selected services
        </p>
    </aside>
    <!-- Sticky Actions -->
    <div class="fixed bottom-0 inset-x-0 bg-white border-t shadow-lg z-50">
        <div
            class="
            max-w-6xl mx-auto px-4 py-3
            flex flex-col gap-3
            sm:flex-row sm:items-center sm:justify-between
        ">

            <!-- Back -->
            <a href="api/cancel-booking.php"
                onclick="allowLeave = true"
                class="text-sm text-gray-500 hover:text-gray-700">
                ← Back
            </a>

            <!-- Continue -->
            <button
                id="continueBtn"
                class="
                w-full
                sm:w-auto sm:min-w-[160px]

                bg-indigo-600 text-white
                px-6 py-3 rounded-xl font-semibold
                hover:bg-indigo-700 transition
                disabled:opacity-50 disabled:cursor-not-allowed
            ">
                Continue →
            </button>
        </div>
    </div>

    <script src="js/step2.js"></script>
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