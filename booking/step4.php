<?php
session_start();
require_once "../php/db.php";

/* -------------------------------------------------
   Guard: must complete steps 1–3
------------------------------------------------- */
if (
    empty($_SESSION['booking_client']) ||
    empty($_SESSION['booking_price_snapshot']) ||
    empty($_SESSION['booking_schedule'])
) {
    header("Location: index.php");
    exit;
}

/* -------------------------------------------------
   Create booking nonce (ONE TIME)
------------------------------------------------- */
if (empty($_SESSION['booking_nonce'])) {
    $_SESSION['booking_nonce'] = bin2hex(random_bytes(16));
}

$expired = isset($_GET['expired']);

$client   = $_SESSION['booking_client'];
$services = $_SESSION['booking_services'];
$schedule = $_SESSION['booking_schedule'];
$snapshot = $_SESSION['booking_price_snapshot'];
$services = $snapshot['services'];


$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
$spaName = $settings['spa_name'] ?? 'My Wellness Spa';
$logoPath = $settings['logo_path'] ?? null;
$spaContact = $settings['contact_number'] ?? '';

$logoUrl = null;
if (!empty($logoPath)) {
    $logoUrl = '../' . ltrim($logoPath, '/');
}
/* -------------------------------------------------
   Resolve staff per service
------------------------------------------------- */
$staffByVariant = $schedule['staff_by_variant'] ?? [];

/*
  Collect unique employee IDs to resolve names in ONE query
*/
$employeeNames = [];

$employeeIds = array_filter(array_unique($staffByVariant));
if ($employeeIds) {
    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $types = str_repeat('i', count($employeeIds));

    $stmt = $conn->prepare("
        SELECT id, full_name
        FROM employees
        WHERE id IN ($placeholders)
    ");
    $stmt->bind_param($types, ...$employeeIds);
    $stmt->execute();

    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $employeeNames[$row['id']] = $row['full_name'];
    }
}


/* -------------------------------------------------
   Totals (server-side = source of truth)
------------------------------------------------- */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Review & Confirm Booking</title>
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

    <main class="max-w-5xl mx-auto px-4 py-6 pb-28">

        <!-- Progress -->
        <div class="mb-6">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold">
                    4
                </div>

                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                        class="h-2 bg-indigo-600 rounded-full transition-all duration-300"
                        style="width: calc(100% / 6 * 4);">
                    </div>
                </div>

                <span class="text-xs sm:text-sm text-gray-500 whitespace-nowrap">
                    Step 4 of 6
                </span>
            </div>

            <h2 class="text-xl sm:text-2xl font-semibold mt-4 text-gray-800">
                Review & Confirm
            </h2>
            <p class="text-sm text-gray-500">
                Please review your booking details before confirming.
            </p>

            <?php if ($expired): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-4 text-sm">
                    Your payment session expired. Please review and confirm your booking again.
                </div>
            <?php endif; ?>
        </div>

        <div class="grid md:grid-cols-2 gap-6">


            <!-- LEFT COLUMN -->
            <section class="space-y-6">

                <!-- Client Info -->
                <div class="bg-white rounded-2xl border p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">
                        Client Information
                    </h3>

                    <div class="space-y-1 text-sm text-gray-700">
                        <p><?= htmlspecialchars($client['full_name']) ?></p>
                        <p><?= htmlspecialchars($client['email']) ?></p>
                        <p><?= htmlspecialchars($client['contact_number']) ?></p>
                    </div>
                </div>

                <!-- Schedule -->
                <div class="bg-white rounded-2xl border p-5">
                    <h3 class="font-semibold text-gray-800 mb-3">
                        Schedule
                    </h3>

                    <div class="space-y-2 text-sm text-gray-700">
                        <div class="space-y-2">
                            <p class="text-xs uppercase tracking-wide text-gray-400">
                                Staff per Service
                            </p>

                            <ul class="divide-y rounded-xl border bg-gray-50 text-sm">
                                <?php foreach ($services as $s):
                                    $variantId = $s['variant_id'];
                                    $empId = $staffByVariant[$variantId] ?? null;
                                    $empName = $empId && isset($employeeNames[$empId])
                                        ? $employeeNames[$empId]
                                        : "Any available";
                                ?>
                                    <li class="flex items-center justify-between px-4 py-3">
                                        <span class="font-medium text-gray-800 truncate">
                                            <?= htmlspecialchars($s['name']) ?>
                                        </span>

                                        <span class="text-gray-600 text-right">
                                            <?= htmlspecialchars($empName) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <p>
                            <span class="text-gray-500">Date:</span>
                            <?= date("M d, Y", strtotime($schedule['date'])) ?>
                        </p>

                        <p>
                            <span class="text-gray-500">Time:</span>
                            <?= date("g:i A", strtotime($schedule['start_time'])) ?>
                            –
                            <?= date("g:i A", strtotime($schedule['end_time'])) ?>
                        </p>
                    </div>
                </div>

            </section>

            <!-- RIGHT COLUMN -->
            <aside class="bg-white rounded-2xl border p-5 space-y-5 h-fit">

                <h3 class="font-semibold text-gray-800">
                    Booking Summary
                </h3>

                <!-- Services -->
                <ul class="space-y-2 text-sm text-gray-700">
                    <?php foreach ($services as $s): ?>
                        <li class="flex justify-between">
                            <span class="truncate"><?= htmlspecialchars($s['name']) ?></span>
                            <span>₱<?= number_format($s['price'], 2) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Totals -->
                <div class="border-t pt-4 space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>
                            ₱<?= number_format($snapshot['subtotal'], 2) ?>
                        </span>
                    </div>

                    <div class="flex justify-between text-gray-600">
                        <span>
                            VAT (<?= (int)($snapshot['vat_rate'] * 100) ?>%)
                        </span>
                        <span>
                            ₱<?= number_format($snapshot['vat'], 2) ?>
                        </span>
                    </div>

                    <div class="flex justify-between font-semibold text-lg text-indigo-600 pt-2">
                        <span>Total (VAT included)</span>
                        <span>
                            ₱<?= number_format($snapshot['total'], 2) ?>
                        </span>
                    </div>
                </div>

                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-sm text-indigo-900">
                    <p class="font-semibold mb-1">Before you continue</p>
                    <ul class="list-disc list-inside space-y-1 text-indigo-800">
                        <li>You’ll proceed to the payment step (GCash / e-wallet).</li>
                        <li>Your booking price will be <strong>locked</strong>.</li>
                        <li>Changes require going back before payment.</li>
                        <li>Payments are reviewed by our staff for security.</li>
                    </ul>
                </div>
                <div class="border rounded-xl p-4 space-y-3 text-sm">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            id="finalConfirmCheck"
                            class="mt-1 accent-indigo-600">
                        <span class="text-gray-700">
                            I confirm that all booking details above are
                            <strong>correct</strong> and I understand that I will
                            proceed to the <strong>payment step</strong>.
                        </span>
                    </label>

                    <p class="text-xs text-gray-500">
                        You won’t be able to edit your booking once payment begins.
                    </p>
                </div>


            </aside>
        </div>
    </main>

    <!-- Sticky Actions -->
    <div class="fixed bottom-0 inset-x-0 bg-white border-t shadow-lg z-50">
        <div class="max-w-5xl mx-auto px-4 py-3 flex flex-col gap-2">

            <div class="flex justify-between items-center">
                <a href="step3.php"
                    class="text-sm text-gray-500 hover:text-gray-700">
                    ← Back
                </a>

                <button
                    id="confirmBtn"
                    disabled
                    class="bg-gray-300 text-gray-500 px-6 py-3 rounded-xl font-semibold
                       cursor-not-allowed transition">
                    Confirm Booking (5s)
                </button>
            </div>

            <!-- Hint -->
            <p
                id="confirmHint"
                class="text-xs text-gray-500 text-right">
                Please check the confirmation box above to continue
            </p>
        </div>
    </div>

    <div id="confirmModal"
        class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">

        <div class="bg-white rounded-2xl max-w-sm w-full p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">
                Proceed to Payment?
            </h3>

            <p class="text-sm text-gray-600">
                You are about to proceed to the payment step.
                Your booking details and price will be locked.
            </p>

            <div class="flex gap-3 justify-end pt-4">
                <button
                    id="cancelConfirm"
                    class="px-4 py-2 rounded-lg text-gray-600 hover:bg-gray-100">
                    Cancel
                </button>

                <button
                    id="confirmProceed"
                    class="px-5 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                    Yes, Continue
                </button>
            </div>
        </div>
    </div>
    <script>
        const confirmBtn = document.getElementById("confirmBtn");
        const modal = document.getElementById("confirmModal");
        const cancelBtn = document.getElementById("cancelConfirm");
        const proceedBtn = document.getElementById("confirmProceed");
        const confirmCheck = document.getElementById("finalConfirmCheck");
        const hint = document.getElementById("confirmHint");

        let countdown = 5;
        let countdownDone = false;

        // Initial countdown lock
        const timer = setInterval(() => {
            countdown--;
            confirmBtn.textContent = `Confirm Booking (${countdown}s)`;

            if (countdown <= 0) {
                clearInterval(timer);
                countdownDone = true;
                confirmBtn.textContent = "Confirm Booking";
                updateConfirmState();
            }
        }, 1000);

        function updateConfirmState() {
            if (countdownDone && confirmCheck.checked) {
                confirmBtn.disabled = false;
                confirmBtn.classList.remove("bg-gray-300", "text-gray-500", "cursor-not-allowed");
                confirmBtn.classList.add("bg-indigo-600", "text-white", "hover:bg-indigo-700");

                // hide hint
                hint.classList.add("hidden");
            } else {
                confirmBtn.disabled = true;
                confirmBtn.classList.add("bg-gray-300", "text-gray-500", "cursor-not-allowed");
                confirmBtn.classList.remove("bg-indigo-600", "text-white", "hover:bg-indigo-700");

                // show hint only after countdown
                if (countdownDone) {
                    hint.classList.remove("hidden");
                }
            }
        }

        confirmCheck.addEventListener("change", updateConfirmState);

        // Open modal
        confirmBtn.addEventListener("click", () => {
            modal.classList.remove("hidden");
            modal.classList.add("flex");
        });

        cancelBtn.addEventListener("click", () => {
            modal.classList.add("hidden");
            modal.classList.remove("flex");
        });

        // Proceed to payment
        proceedBtn.addEventListener("click", async () => {
            proceedBtn.disabled = true;
            proceedBtn.textContent = "Processing…";

            try {
                window.location.href = "step5.php";

            } catch (err) {
                alert(err.message);
                proceedBtn.disabled = false;
                proceedBtn.textContent = "Yes, Continue";
            }
        });
    </script>
</body>

</html>