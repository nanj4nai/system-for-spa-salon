<?php
session_start();
require_once "../php/db.php";

/* ---------------------------
   Guards
---------------------------- */
if (
    empty($_SESSION['booking_client']) ||
    empty($_SESSION['booking_services']) ||
    empty($_SESSION['booking_schedule']) ||
    empty($_SESSION['booking_price_snapshot']) ||
    empty($_SESSION['booking_nonce'])
) {
    header("Location: index.php?restart=1");
    exit;
}


// ⏱️ Enforce expiration
if (time() > $_SESSION['booking_price_snapshot']['expires_at']) {
    unset($_SESSION['booking_price_snapshot']);
    unset($_SESSION['booking_nonce']);
    unset($_SESSION['confirm_attempts']);

    header("Location: step4.php?expired=1");
    exit;
}

// Load settings


$settingsResult = $conn->query("SELECT * FROM settings LIMIT 1");
$settings = $settingsResult ? $settingsResult->fetch_assoc() : [];

$gcashNumber = $settings['gcash_number'] ?? null;
$gcashQrPath = $settings['gcash_qr_path'] ?? null;

$client   = $_SESSION['booking_client'];
$snapshot = $_SESSION['booking_price_snapshot'];
$nonce    = $_SESSION['booking_nonce'];

if (!isset($snapshot['expires_at'])) {
    header("Location: step4.php?expired=1");
    exit;
}
$expiresAt = $snapshot['expires_at'];
$remainingSeconds = max(0, $expiresAt - time());

$spaName = $settings['spa_name'] ?? 'My Wellness Spa';
$logoPath = $settings['logo_path'] ?? null;
$spaContact = $settings['contact_number'] ?? '';
$email = $settings['email'] ?? '';
$logoUrl = null;
if (!empty($logoPath) || !empty($gcashQrPath)) {
    $logoUrl = '../' . ltrim($logoPath, '/');
    $gcashQrURL = $gcashQrPath ? '../' . ltrim($gcashQrPath, '/') : null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($logoUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($logoUrl) ?>?v=<?= time() ?>">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

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

<body class="font-poppins bg-gray-50 min-h-screen" data-remaining="<?= (int)$remainingSeconds ?>">


    <main class="max-w-6xl mx-auto px-4 py-10">
        <div class="text-center lg:text-left mb-8">
            <!-- Progress -->
            <div class="mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold">
                        5
                    </div>

                    <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div
                            class="h-2 bg-indigo-600 rounded-full transition-all duration-300"
                            style="width: calc(100% / 6 * 5);">
                        </div>
                    </div>

                    <span class="text-xs sm:text-sm text-gray-500 whitespace-nowrap">
                        Step 5 of 6
                    </span>
                </div>
            </div>
            <h2 class="text-2xl font-semibold text-gray-800">Secure Payment</h2>

            <p class="text-sm text-gray-500 mt-1">
                Complete your booking to reserve your schedule
            </p>
        </div>

        <div class="grid gap-8 lg:grid-cols-2">
            <!-- LEFT -->
            <section class="space-y-6 lg:sticky lg:top-10">
                <!-- Amount Card -->
                <div class="bg-white rounded-2xl border shadow-sm p-6 mb-6 text-center">
                    <p class="text-sm text-gray-500">Total Amount</p>
                    <p class="text-3xl font-bold text-indigo-600 mt-1">
                        ₱<?= number_format($snapshot['total'], 2) ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        Price is locked for 30 minutes
                    </p>
                </div>
                <div
                    class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-6 text-center">
                    <p class="text-sm text-indigo-700 font-medium">
                        Time remaining to complete payment
                    </p>
                    <p
                        id="countdown"
                        class="text-2xl font-bold text-indigo-600 mt-1">
                        --:--
                    </p>
                    <p class="text-xs text-indigo-500 mt-1">
                        Booking will expire automatically when time runs out
                    </p>
                </div>


                <!-- Payment Method -->
                <div class="bg-white rounded-2xl border shadow-sm p-6 mb-6">
                    <h3 class="font-semibold text-gray-800 mb-3">
                        Pay via GCash
                    </h3>

                    <?php if ($gcashNumber): ?>
                        <button
                            type="button"
                            id="toggleGcash"
                            class="w-full flex items-center justify-between px-4 py-3 rounded-xl border bg-gray-50 hover:bg-gray-100 transition text-sm font-medium text-indigo-600">
                            <span>Show payment details</span>
                            <span id="toggleIcon">+</span>
                        </button>

                        <div id="gcashDetails" class="hidden mt-4 space-y-4">
                            <div class="text-sm text-gray-700 text-center">
                                <p class="font-medium">GCash Number</p>
                                <p class="text-lg font-semibold tracking-wide">
                                    <?= htmlspecialchars($gcashNumber) ?>
                                </p>
                            </div>

                            <?php if ($gcashQrURL): ?>
                                <div class="flex justify-center">
                                    <img
                                        src="<?= htmlspecialchars($gcashQrURL) ?>"
                                        alt="GCash QR"
                                        id="gcashQrThumb"
                                        class="w-44 rounded-xl border cursor-zoom-in hover:opacity-90 transition">
                                </div>
                            <?php endif; ?>

                            <p class="text-xs text-gray-500 text-center">
                                Please ensure the details are correct before sending payment.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm">
                            Online payment is currently unavailable.
                            Please contact the business directly.
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- RIGHT -->
            <section class="space-y-6">
                <!-- Payment Form -->
                <form id="paymentForm" enctype="multipart/form-data"
                    class="bg-white rounded-2xl border shadow-sm p-6 space-y-5">

                    <input type="hidden" name="booking_nonce" value="<?= htmlspecialchars($nonce) ?>">

                    <div>
                        <label class="text-sm font-medium text-gray-700">Amount Paid</label>
                        <input
                            type="number"
                            name="amount_paid"
                            step="0.01"
                            min="0"
                            placeholder="₱0.00"
                            class="w-full mt-1 px-4 py-3 rounded-xl border focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Minimum down payment:
                        <strong>₱<?= number_format($snapshot['total'] * 0.30, 2) ?></strong>
                        (30% of total). You may also pay the full amount.
                    </p>
                    <!-- Remaining Balance Preview -->
                    <div
                        id="balancePreview"
                        class="mt-3 hidden bg-gray-50 border border-gray-200
                        rounded-xl px-4 py-3 text-sm text-gray-700 text-center">
                        Remaining balance:
                        <strong id="remainingBalance" class="text-indigo-600">₱0.00</strong>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">GCash Reference Number</label>
                        <input
                            type="text"
                            name="payment_reference"
                            placeholder="Enter reference number"
                            class="w-full mt-1 px-4 py-3 rounded-xl border focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Upload Proof of Payment</label>
                        <input
                            type="file"
                            name="payment_proof"
                            accept="image/*"
                            class="w-full mt-2 text-sm">
                    </div>
                    <div class="space-y-3 text-sm text-gray-600">



                        <div class="bg-gray-50 border rounded-xl p-4 text-xs text-gray-600 leading-relaxed">
                            <p class="font-semibold text-gray-800 mb-2">
                                Payment Terms & Conditions
                            </p>
                            <ul class="list-disc list-inside space-y-1">
                                <li>Our team is available to assist with any questions or concerns regarding your payment.</li>
                                <li>You may contact us before, during, or after submitting your payment if clarification is needed.</li>
                                <li>All submitted payments will be reviewed and verified by our staff.</li>
                                <li>Payment verification is processed manually; please allow sufficient time for confirmation.</li>
                                <li>Incomplete, incorrect, or unverified payments may result in delays.</li>
                                <li>Please submit this form only after payment has been successfully sent.</li>
                                <li>Bookings may be cancelled if payment cannot be verified within the allotted time.</li>
                            </ul>
                        </div>
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            id="confirmPayment"
                            name="confirm_payment"
                            value="1"
                            class="mt-1 accent-indigo-600">
                        <span>
                            I confirm that I have <strong>successfully sent the payment</strong>
                            using the details provided above.
                        </span>
                    </label>
                    <?php $gcashReady = !empty($gcashNumber); ?>
                    <div id="errorBox"
                        class="hidden mb-4 rounded-xl border border-red-300 bg-red-50 p-4 text-red-700">
                        <p id="errorMessage" class="text-sm"></p>

                        <div id="errorActions" class="mt-3 hidden">
                            <button id="restartBookingBtn"
                                class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                                Start Over
                            </button>
                        </div>
                    </div>
                    <button
                        id="submitBtn"
                        type="submit"
                        disabled
                        class="w-full py-4 rounded-xl font-semibold text-lg transition
                                bg-gray-300 text-gray-500 cursor-not-allowed">
                        Submit Payment
                    </button>

                    <p
                        id="submitHint"
                        class="
                                mt-3
                                text-sm sm:text-xs
                                text-indigo-700
                                bg-indigo-50
                                border border-indigo-200
                                rounded-xl
                                px-4 py-3 sm:px-3 sm:py-2
                                text-center
                                leading-relaxed
                                transition-all duration-200
                            ">
                        Please complete all fields and confirm your payment to continue
                    </p>

                </form>

            </section>
        </div>
        <!-- Help Box -->
        <p class="text-xs text-gray-400 text-center mt-6">
            Payments are reviewed by our staff before confirmation.
        </p>
        <div class="mt-6 bg-gray-100 rounded-xl p-4 text-center text-sm text-gray-600">
            <p class="font-medium">Need help?</p>
            <p class="mt-1">
                Contact us at
                <strong><?= htmlspecialchars($spaContact) ?></strong>
                <?php if (!empty($email)): ?>
                    or <strong><?= htmlspecialchars($email) ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </main>
    <!-- QR Zoom Modal -->
    <div
        id="qrModal"
        class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">
        <img
            id="qrModalImg"
            src=""
            alt="GCash QR Zoomed"
            class="max-w-[90vw] max-h-[90vh] rounded-xl shadow-lg cursor-zoom-out">
    </div>


    <script src="js/step5.js" defer></script>
    <script>
        window.TOTAL_AMOUNT = <?= json_encode((float)$snapshot['total']) ?>;
    </script>

    <?php if (!$gcashReady): ?>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                document.getElementById("submitBtn").disabled = true;
            });
        </script>
    <?php endif; ?>
</body>

</html>