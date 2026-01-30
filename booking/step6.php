<?php
session_start();
require_once "../php/db.php";

/* Guard */
if (empty($_SESSION['booking_submitted']['appointment_id'])) {
    header("Location: index.php");
    exit;
}

$appointmentId = (int) $_SESSION['booking_submitted']['appointment_id'];


/* Load latest transaction */
$stmt = $conn->prepare("
    SELECT
        t.transaction_number,
        t.total_amount,
        t.amount_paid,
        t.balance_due,
        t.payment_status,
        t.status,
        t.created_at
    FROM spa_transactions t
    WHERE t.appointment_id = ?
      AND t.transaction_type = 'online_booking'
    ORDER BY t.created_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    header("Location: index.php");
    exit;
}


/* Load settings */
$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();

$spaName     = $settings['spa_name'] ?? 'My Wellness Spa';
$spaContact  = $settings['contact_number'] ?? '';
$email       = $settings['email'] ?? '';
$logoPath    = $settings['logo_path'] ?? null;

$logoUrl = $logoPath ? '../' . ltrim($logoPath, '/') : null;


$isPartial = ($transaction['payment_status'] === 'partial');
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment Submitted</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

<body class="font-poppins bg-gray-50 min-h-screen">

    <main class="max-w-4xl mx-auto px-4 py-10">

        <!-- Progress -->
        <div class="mb-8">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold">
                    6
                </div>
                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-2 bg-indigo-600 rounded-full w-full"></div>
                </div>
                <span class="text-xs sm:text-sm text-gray-500 whitespace-nowrap">
                    Step 6 of 6
                </span>
            </div>
        </div>

        <!-- Success Card -->
        <section class="bg-white rounded-3xl border shadow-sm p-8 text-center space-y-6">

            <!-- Icon -->
            <div class="mx-auto w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
                <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <!-- Title -->
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">
                    Payment Submitted Successfully
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Your booking is now pending verification
                </p>
            </div>

            <!-- Summary -->
            <div class="max-w-md mx-auto bg-gray-50 border rounded-2xl p-5 text-sm text-gray-700 space-y-3 text-left">

                <div class="flex justify-between">
                    <span>Total Booking</span>
                    <span class="font-medium">
                        ₱<?= number_format($transaction['total_amount'], 2) ?>
                    </span>
                </div>

                <div class="flex justify-between">
                    <span>Amount Paid</span>
                    <span class="font-semibold text-indigo-600">
                        ₱<?= number_format($transaction['amount_paid'], 2) ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span>Reference</span>
                    <span class="font-mono text-sm">
                        <?= htmlspecialchars($transaction['transaction_number']) ?>
                    </span>
                </div>

                <?php if ($isPartial): ?>
                    <div class="flex justify-between border-t pt-3">
                        <span>Remaining Balance</span>
                        <span class="font-semibold text-red-600">
                            ₱<?= number_format($transaction['balance_due'], 2) ?>
                        </span>
                    </div>

                    <div class="mt-2 text-xs text-orange-600 text-center">
                        This balance is recorded and tracked under your booking.
                    </div>
                <?php else: ?>
                    <div class="border-t pt-3 text-center font-medium text-green-700">
                        Paid in full ✓
                    </div>
                <?php endif; ?>


                <!-- Next Steps -->
                <div class="max-w-md mx-auto bg-indigo-50 border border-indigo-200 rounded-2xl p-5 text-sm text-indigo-800 text-left">
                    <p class="font-semibold mb-2">What happens next?</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Our team will carefully review and verify your payment.</li>
                        <li>You will receive a confirmation once your payment has been approved.</li>
                        <li>If any additional information is needed, we will contact you promptly.</li>
                        <li>If you have questions about your booking or its status, you are always welcome to contact us for assistance.</li>
                        <?php if ($isPartial): ?>
                            <li>The remaining balance will be payable at the spa on your appointment date.</li>
                        <?php endif; ?>
                    </ul>
                </div>


                <!-- Footer -->
                <p class="text-xs text-gray-400">
                    You may safely close this page.
                </p>

        </section>

    </main>

</body>

</html>
<?php unset($_SESSION['booking_submitted']); ?>