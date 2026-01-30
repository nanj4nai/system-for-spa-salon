<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once "php/db.php";
require_once "php/company_settings.php";
$username = $_SESSION["username"];
$role = $_SESSION["role"];

/* ===============================
   Fetch pending payments
=============================== */
$sql = "
SELECT
    a.id AS appointment_id,
    c.full_name AS client_name,
    t.id AS transaction_id,
    t.transaction_number,
    t.total_amount,
    t.amount_paid,
    t.balance_due,
    t.payment_status,
    t.created_at,
    a.payment_reference,
    a.payment_proof
FROM appointments a
JOIN clients c ON c.id = a.client_id
JOIN spa_transactions t ON t.appointment_id = a.id
WHERE
    a.source = 'online'
    AND a.payment_verified = 0
    AND t.status = 'pending_verification'
ORDER BY t.created_at ASC
";


$result = $conn->query($sql);
$pendingPayments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

/* ===============================
   Fetch approved bookings
=============================== */
$sqlApproved = "
SELECT
    a.id AS appointment_id,
    c.full_name AS client_name,
    t.total_amount,
    t.amount_paid,
    t.balance_due,
    t.payment_status,
    a.payment_reference,
    t.created_at,
    a.last_email_sent_at
FROM appointments a
JOIN clients c ON c.id = a.client_id
JOIN spa_transactions t ON t.appointment_id = a.id
WHERE
    a.payment_verified = 1
    AND a.status = 'confirmed'
ORDER BY a.payment_verified_at DESC
";
$approved = ($conn->query($sqlApproved))?->fetch_all(MYSQLI_ASSOC) ?? [];

/* ===============================
   Fetch rejected bookings
=============================== */
$sqlRejected = "
SELECT
    a.id AS appointment_id,
    c.full_name AS client_name,
    t.total_amount,
    t.amount_paid,
    t.balance_due,
    a.payment_reference,
    t.created_at,
    a.payment_rejection_reason,
    a.last_email_sent_at
FROM appointments a
JOIN clients c ON c.id = a.client_id
JOIN spa_transactions t ON t.appointment_id = a.id
WHERE
    a.payment_rejection_reason IS NOT NULL
ORDER BY a.payment_rejected_at DESC
";
$rejected = ($conn->query($sqlRejected))?->fetch_all(MYSQLI_ASSOC) ?? [];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> – Shift Approvals</title>

    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            darkMode: "class"
        };
    </script>

    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        html {
            visibility: hidden;
        }

        .tab-btn.active {
            background-color: rgb(79 70 229);
            color: white;
            box-shadow: 0 4px 14px rgba(79, 70, 229, .35);
        }

        .dark .tab-btn.active {
            background-color: rgb(99 102 241);
            /* indigo-500 */
            color: white;
            box-shadow: 0 4px 14px rgba(99, 102, 241, .45);
        }
    </style>
</head>

<body class="flex bg-gray-100 dark:bg-[#121212] transition-all">

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="w-64 h-screen fixed top-0 left-0 bg-gradient-to-b from-[#d6f0ec] to-[#f9e8ff]
           dark:from-gray-900 dark:to-gray-800 text-gray-800 dark:text-gray-200 shadow-lg
           transform -translate-x-full md:translate-x-0 transition-all duration-300">

        <div class="p-6 flex items-center gap-3">
            <i data-lucide="clipboard-check" class="w-7 h-7 text-indigo-700 dark:text-indigo-300"></i>
            <h1 class="text-xl font-bold text-indigo-900 dark:text-indigo-200">
                Shift Approvals
            </h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1">
            <p class="text-xs opacity-70 mb-3">
                Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)
            </p>

            <a href="dashboard.php"
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-teal-200 dark:hover:bg-teal-800 transition">
                <i data-lucide="home" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="admin-payment-approvals.php"
                class="flex items-center gap-3 p-3 rounded-xl bg-indigo-200 dark:bg-indigo-800 transition">
                <i data-lucide="clipboard-clock" class="w-5 h-5"></i> Booking Approvals
            </a>
            <a href="admin-shift-approvals.php"
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-orange-200 dark:hover:bg-orange-800 transition">
                <i data-lucide="clipboard-check" class="w-5 h-5"></i> Shift Approvals
            </a>
            <a href="php/logout.php"
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-200 dark:hover:bg-red-800 mt-3 transition">
                <i data-lucide="log-out" class="w-5 h-5"></i> Logout
            </a>
        </nav>

        <div class="p-4 mt-8">
            <button id="darkModeToggle"
                class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-200 dark:bg-gray-700
                   hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                <i data-lucide="moon" class="w-5 h-5 dark:hidden"></i>
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                Dark Mode
            </button>
        </div>
    </aside>
    <!-- Success Toast -->
    <div id="successToast"
        class="fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-all duration-300 z-50">
        User saved successfully!
    </div>

    <!-- MOBILE MENU -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg transition">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <!-- MAIN -->
    <main class="flex-1 md:ml-64 p-6 text-gray-800 dark:text-gray-200">

        <!-- HEADER -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow border-l-4 border-indigo-500">
            <h2 class="text-2xl font-semibold">Payment Verifications</h2>
            <p class="text-sm opacity-70 mt-1">
                Review booking payments (full or partial)
            </p>
        </div>
        <div class="flex gap-2 mt-6">
            <button
                type="button"
                class="tab-btn active px-4 py-2 rounded-lg
               bg-gray-200 dark:bg-gray-700
               hover:bg-gray-300 dark:hover:bg-gray-600
               transition"
                data-tab="pending">
                Pending
            </button>

            <button
                type="button"
                class="tab-btn px-4 py-2 rounded-lg
               bg-gray-200 dark:bg-gray-700
               hover:bg-gray-300 dark:hover:bg-gray-600
               transition"
                data-tab="approved">
                Approved
            </button>

            <button
                type="button"
                class="tab-btn px-4 py-2 rounded-lg
               bg-gray-200 dark:bg-gray-700
               hover:bg-gray-300 dark:hover:bg-gray-600
               transition"
                data-tab="rejected">
                Rejected
            </button>
        </div>

        <!-- PAYMENT LIST -->
        <div id="paymentList" class="mt-6 space-y-4">

            <!-- ================= PENDING ================= -->
            <div id="tab-pending" class="tab-content space-y-4">
                <div id="pendingList" class="space-y-4">

                    <?php if (empty($pendingPayments)): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center opacity-70">
                            No pending payments to review.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($pendingPayments as $p): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow border">

                            <span class="inline-block mb-3 px-3 py-1 rounded-full text-xs font-medium
                            <?= $p['payment_status'] === 'partial'
                                ? 'bg-yellow-100 text-yellow-800'
                                : 'bg-green-100 text-green-800' ?>">
                                <?= strtoupper($p['payment_status']) ?> PAYMENT
                            </span>

                            <div class="flex flex-col lg:flex-row justify-between gap-6 items-start">
                                <?php include __DIR__ . "/partials/left-info.php"; ?>

                                <div class="flex gap-2 items-start">
                                    <button
                                        data-proof-src="php/view-payment-proof.php?file=<?= urlencode($p['payment_proof']) ?>"
                                        class="viewProofBtn px-3 py-1.5 text-sm bg-indigo-600 text-white rounded">
                                        View Proof
                                    </button>

                                    <button
                                        data-appointment-id="<?= (int)$p['appointment_id'] ?>"
                                        class="approveBtn px-3 py-1.5 text-sm bg-indigo-600 text-white rounded">
                                        Approve
                                    </button>

                                    <button
                                        data-appointment-id="<?= (int)$p['appointment_id'] ?>"
                                        class="rejectBtn px-3 py-1.5 text-sm bg-indigo-600 text-white rounded">
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ================= APPROVED ================= -->
            <div id="tab-approved" class="tab-content hidden space-y-4">
                <?php foreach ($approved as $p): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow flex justify-between gap-6">

                        <?php /* LEFT INFO */ ?>
                        <?php include "partials/left-info.php"; ?>

                        <div class="flex flex-col items-end gap-1">
                            <button
                                data-appointment-id="<?= $p['appointment_id'] ?>"
                                data-last-sent="<?= $p['last_email_sent_at'] ?? '' ?>"
                                class="resendBtn px-4 py-2 bg-blue-600 text-white rounded">
                                Resend Email
                            </button>

                            <?php if (!empty($p['last_email_sent_at'])): ?>
                                <span class="text-xs opacity-60">
                                    Last sent:
                                    <?= date("M d, Y h:i A", strtotime($p['last_email_sent_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>


            <!-- ================= REJECTED ================= -->
            <div id="tab-rejected" class="tab-content hidden space-y-4">
                <?php foreach ($rejected as $p): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow flex justify-between gap-6">

                        <div>
                            <?php /* LEFT INFO */ ?>
                            <?php include "partials/left-info.php"; ?>

                            <div class="mt-3 bg-red-100 text-red-700 p-3 rounded text-sm">
                                <?= nl2br(htmlspecialchars($p['payment_rejection_reason'])) ?>
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-1">
                            <button
                                data-appointment-id="<?= $p['appointment_id'] ?>"
                                data-last-sent="<?= $p['last_email_sent_at'] ?? '' ?>"
                                class="resendBtn px-4 py-2 bg-blue-600 text-white rounded">
                                Resend Email
                            </button>

                            <?php if (!empty($p['last_email_sent_at'])): ?>
                                <span class="text-xs opacity-60">
                                    Last sent:
                                    <?= date("M d, Y h:i A", strtotime($p['last_email_sent_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </main>

    <!-- PROOF PREVIEW MODAL -->
    <div id="proofModal"
        class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">

        <div class="relative bg-white dark:bg-gray-900 rounded-xl p-4 max-w-[90vw] max-h-[90vh] shadow-xl">

            <!-- CLOSE BUTTON -->
            <button id="closeProofModal"
                class="absolute -top-3 -right-3 bg-red-600 text-white rounded-full p-2 hover:bg-red-700">
                <i data-lucide="x"></i>
            </button>

            <!-- IMAGE -->
            <img id="proofImage"
                src=""
                alt="Payment Proof"
                class="max-w-full max-h-[80vh] rounded-lg cursor-zoom-out">
        </div>
    </div>
    <!-- REJECT REASON MODAL -->
    <div id="rejectModal"
        class="fixed inset-0 bg-black/70 hidden items-center justify-center z-50">

        <div class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-md shadow-xl">
            <h3 class="text-lg font-semibold mb-2 text-red-600">
                Reject Payment
            </h3>

            <p class="text-sm opacity-70 mb-3">
                Please provide a reason for rejecting this payment.
            </p>

            <textarea id="rejectReason"
                rows="4"
                class="w-full rounded-lg border px-3 py-2 text-sm
                   bg-white dark:bg-gray-800 dark:border-gray-700
                   focus:outline-none focus:ring-2 focus:ring-red-500"
                placeholder="e.g. Reference number does not match, unclear proof, wrong amount..."></textarea>

            <div class="flex justify-end gap-3 mt-4">
                <button id="cancelReject"
                    class="px-4 py-2 rounded-lg bg-gray-300 dark:bg-gray-700 hover:bg-gray-400 dark:hover:bg-gray-600">
                    Cancel
                </button>

                <button id="confirmReject"
                    class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                    Confirm Reject
                </button>
            </div>
        </div>
    </div>
    <!-- CONFIRM MODAL -->
    <div id="confirmModal"
        class="fixed inset-0 hidden items-center justify-center bg-black/60 z-50">
        <div class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-md shadow-xl">
            <h3 id="confirmTitle"
                class="text-lg font-semibold mb-2 text-gray-900 dark:text-gray-100">
                Confirm Action
            </h3>

            <p id="confirmMessage"
                class="text-sm opacity-80 mb-4 text-gray-700 dark:text-gray-300"></p>

            <div class="flex justify-end gap-3">
                <button id="confirmCancel"
                    class="px-4 py-2 rounded-lg bg-gray-300 dark:bg-gray-700">
                    Cancel
                </button>

                <button id="confirmOk"
                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white">
                    Confirm
                </button>
            </div>
        </div>
    </div>
    <!-- ALERT MODAL -->
    <div id="alertModal"
        class="fixed inset-0 hidden items-center justify-center bg-black/60 z-50">
        <div class="bg-white dark:bg-gray-900 rounded-xl p-6 w-full max-w-sm shadow-xl">
            <h3 class="text-lg font-semibold mb-2 text-red-600">
                Notice
            </h3>

            <p id="alertMessage"
                class="text-sm opacity-80 mb-4 text-gray-700 dark:text-gray-300"></p>

            <div class="flex justify-end">
                <button id="alertOk"
                    class="px-4 py-2 rounded-lg bg-indigo-600 text-white">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- GLOBAL LOADING OVERLAY -->
    <div id="loadingOverlay"
        class="fixed inset-0 z-[9999] hidden items-center justify-center
            bg-black/50 backdrop-blur-sm">

        <div class="bg-white dark:bg-gray-900 rounded-xl px-6 py-5
                flex items-center gap-3 shadow-xl">
            <svg class="animate-spin h-6 w-6 text-indigo-600"
                xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10"
                    stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                Processing…
            </span>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="js/appointment-approve.js" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {

                    // activate button
                    document.querySelectorAll('.tab-btn')
                        .forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    // hide all tabs
                    document.querySelectorAll('.tab-content')
                        .forEach(c => c.classList.add('hidden'));

                    // show target tab (SAFE)
                    const target = document.getElementById('tab-' + btn.dataset.tab);
                    if (target) {
                        target.classList.remove('hidden');
                    } else {
                        console.warn('Tab not found:', 'tab-' + btn.dataset.tab);
                    }
                });
            });
        });
    </script>

</body>

</html>