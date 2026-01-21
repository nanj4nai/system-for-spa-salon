<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once "php/company_settings.php";
$username = $_SESSION["username"];
$role = $_SESSION["role"];
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

            <a href="admin-shift-approvals.php"
                class="flex items-center gap-3 p-3 rounded-xl bg-indigo-200 dark:bg-indigo-800 transition">
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

    <!-- MOBILE TOGGLE -->
    <button id="sidebarToggle"
        class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-indigo-400 shadow-lg">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <main class="flex-1 md:ml-64 p-6 transition-all">

        <!-- HEADER -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md 
            border-l-4 border-indigo-500 dark:border-indigo-300 mb-6">
            <h1 class="text-2xl font-semibold text-indigo-900 dark:text-indigo-200">
                Shift Management
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Review, approve, and reconcile cashier shifts
            </p>
        </div>

        <!-- TABS -->
        <div class="flex gap-2 mb-6">
            <button class="tab-btn active px-4 py-2 rounded-xl text-sm" data-tab="pending">
                Pending Requests
            </button>
            <button class="tab-btn px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm" data-tab="active">
                Active Shifts
            </button>
            <button class="tab-btn px-4 py-2 rounded-xl bg-gray-200 dark:bg-gray-700 text-sm" data-tab="closed">
                Closed Shifts
            </button>
        </div>

        <!-- TABLE -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow 
            border border-indigo-200 dark:border-indigo-700 p-6">
            <table class="w-full text-sm">
                <thead class="text-left border-b">
                    <tr id="tableHead"></tr>
                </thead>
                <tbody id="shiftTable">
                    <tr>
                        <td colspan="6" class="py-6 text-center text-gray-400">
                            Loading shifts…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SHIFT DETAILS MODAL -->
        <div id="shiftModal"
            class="fixed inset-0 hidden bg-black/60 backdrop-blur-sm 
           flex items-center justify-center z-50">

            <div class="bg-white dark:bg-gray-800 w-full max-w-4xl 
            rounded-2xl shadow-xl p-6 relative">
                <!-- CLOSE -->
                <button onclick="closeModal()"
                    class="absolute top-4 right-4 text-gray-500 hover:text-red-500">
                    ✕
                </button>

                <h2 class="text-xl font-semibold mb-4">Shift Details</h2>

                <!-- SUMMARY -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm mb-6">
                    <div>
                        <p class="text-gray-400">Opening Cash</p>
                        <p id="sumOpening" class="font-semibold">—</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Cash Sales</p>
                        <p id="sumCashSales" class="font-semibold">—</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Expected Cash</p>
                        <p id="sumExpected" class="font-semibold">—</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Declared Cash</p>
                        <p id="sumClosing" class="font-semibold">—</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Variance</p>
                        <p id="sumVariance" class="font-semibold">—</p>
                    </div>
                </div>

                <!-- TRANSACTIONS -->
                <h3 class="font-semibold mb-2">Transactions</h3>
                <div class="max-h-64 overflow-y-auto border rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 dark:bg-gray-700 sticky top-0">
                            <tr>
                                <th class="p-2">#</th>
                                <th>Client</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="transactionTable">
                            <tr>
                                <td colspan="5" class="p-4 text-center text-gray-400">
                                    Loading…
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="arModal"
            class="fixed inset-0 hidden bg-black/50 flex items-center justify-center z-50">

            <div class="bg-white dark:bg-gray-800 w-full max-w-sm rounded-xl p-6">

                <h3 class="text-lg font-semibold mb-2">
                    Mark as Accounts Receivable
                </h3>

                <p class="text-sm text-gray-500 mb-4">
                    Client: <span id="arClientName" class="font-semibold"></span>
                </p>

                <p class="text-sm mb-4">
                    Outstanding Balance:
                    <span id="arAmount" class="font-semibold text-red-600"></span>
                </p>

                <div class="mb-3">
                    <label class="text-xs text-gray-500">Remarks</label>
                    <textarea id="arRemarks"
                        class="w-full mt-1 px-3 py-2 border rounded"
                        placeholder="Reason for balance..."></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button onclick="closeARModal()"
                        class="px-4 py-2 text-sm bg-gray-200 rounded">
                        Cancel
                    </button>

                    <button onclick="confirmAR()"
                        class="px-4 py-2 text-sm bg-orange-600 hover:bg-orange-700 
           text-white rounded-xl shadow">
                        Confirm A/R
                    </button>
                </div>
            </div>
        </div>
        <!-- TRANSACTION DETAILS MODAL -->
        <div id="transactionModal"
            class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center">

            <div class="bg-white dark:bg-gray-800 w-full max-w-5xl rounded-2xl shadow-xl p-6 relative">

                <!-- CLOSE -->
                <button onclick="closeTransactionModal()"
                    class="absolute top-4 right-4 text-gray-400 hover:text-red-500 text-xl">
                    ✕
                </button>

                <!-- TITLE -->
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold text-indigo-900 dark:text-indigo-200">
                        Transaction Details
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Complete breakdown of services, products, and payments
                    </p>
                </div>

                <!-- SUMMARY CARDS -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 text-sm">

                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4">
                        <p class="text-gray-400">Transaction #</p>
                        <p id="txNumber" class="font-semibold"></p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4">
                        <p class="text-gray-400">Client</p>
                        <p id="txClient" class="font-semibold"></p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4">
                        <p class="text-gray-400">Status</p>
                        <p id="txStatus"
                            class="inline-block px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-700">
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-4">
                        <p class="text-gray-400">Balance</p>
                        <p id="txBalance"
                            class="font-semibold text-red-600"></p>
                    </div>
                </div>

                <!-- SERVICES -->
                <div class="mb-6">
                    <h3 class="font-semibold mb-2">Services</h3>
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="p-2 text-left">Service</th>
                                    <th class="p-2 text-left">Staff</th>
                                    <th class="p-2 text-center">Qty</th>
                                    <th class="p-2 text-right">Price</th>
                                    <th class="p-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="txServices" class="divide-y"></tbody>
                        </table>
                    </div>
                </div>

                <!-- PRODUCTS -->
                <div class="mb-6">
                    <h3 class="font-semibold mb-2">Products</h3>
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="p-2 text-left">Product</th>
                                    <th class="p-2 text-center">Qty</th>
                                    <th class="p-2 text-right">Price</th>
                                    <th class="p-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="txProducts" class="divide-y"></tbody>
                        </table>
                    </div>
                </div>

                <!-- PAYMENTS -->
                <div>
                    <h3 class="font-semibold mb-2">Payments</h3>
                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="p-2 text-left">Method</th>
                                    <th class="p-2 text-right">Amount</th>
                                    <th class="p-2 text-right">Date</th>
                                </tr>
                            </thead>
                            <tbody id="txPayments" class="divide-y"></tbody>
                        </table>
                    </div>
                </div>
                <!-- ACCOUNTS RECEIVABLE -->
                <div id="txARSection" class="mt-6 hidden">
                    <h3 class="font-semibold mb-2 text-orange-700">
                        Accounts Receivable
                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="bg-orange-50 rounded-xl p-4">
                            <p class="text-gray-500">Amount</p>
                            <p id="arAmountView" class="font-semibold"></p>
                        </div>

                        <div class="bg-orange-50 rounded-xl p-4">
                            <p class="text-gray-500">Balance</p>
                            <p id="arBalanceView" class="font-semibold"></p>
                        </div>

                        <div class="bg-orange-50 rounded-xl p-4">
                            <p class="text-gray-500">Status</p>
                            <p id="arStatusView" class="font-semibold"></p>
                        </div>

                        <div class="bg-orange-50 rounded-xl p-4">
                            <p class="text-gray-500">Remarks</p>
                            <p id="arRemarksView" class="text-xs"></p>
                        </div>
                    </div>
                    <button
                        id="payARBtn"
                        onclick="openARPaymentModal()"
                        class="mt-3 px-3 py-2 text-sm bg-green-600 hover:bg-green-700
           text-white rounded-lg">
                        Apply A/R Payment
                    </button>
                </div>
                <!-- A/R PAYMENT HISTORY -->
                <div id="txARPaymentsSection" class="mt-6 hidden">
                    <h3 class="font-semibold mb-2 text-orange-700">
                        A/R Payment History
                    </h3>

                    <div class="border rounded-xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-orange-100">
                                <tr>
                                    <th class="p-2 text-right">Amount</th>
                                    <th class="p-2 text-right">Date</th>
                                    <th class="p-2 text-left">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="txARPayments" class="divide-y"></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
        <div id="arPaymentModal"
            class="fixed inset-0 z-50 hidden bg-black/60 flex items-center justify-center">

            <div class="bg-white rounded-xl p-6 w-full max-w-sm">
                <h3 class="font-semibold mb-4">Apply A/R Payment</h3>

                <p class="text-sm mb-2">
                    Balance:
                    <span id="arPayBalance" class="font-semibold"></span>
                </p>

                <input id="arPayAmount"
                    type="number"
                    step="0.01"
                    class="w-full border rounded p-2 mb-2"
                    placeholder="Payment amount">

                <textarea id="arPayRemarks"
                    class="w-full border rounded p-2 mb-4"
                    placeholder="Remarks (optional)"></textarea>

                <div class="flex justify-end gap-2">
                    <button onclick="closeARPaymentModal()"
                        class="px-3 py-2 text-sm bg-gray-200 rounded">
                        Cancel
                    </button>
                    <button onclick="confirmARPayment()"
                        class="px-3 py-2 text-sm bg-green-600 text-white rounded">
                        Apply Payment
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="js/admin-shift-approvals.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.documentElement.style.visibility = "visible";
            lucide.createIcons();

            const sidebar = document.getElementById("sidebar");
            const sidebarToggle = document.getElementById("sidebarToggle");
            const darkToggle = document.getElementById("darkModeToggle");

            if (localStorage.getItem("theme") === "dark") {
                document.documentElement.classList.add("dark");
            }

            sidebarToggle.onclick = () => sidebar.classList.toggle("-translate-x-full");

            darkToggle.onclick = () => {
                document.documentElement.classList.toggle("dark");
                localStorage.setItem(
                    "theme",
                    document.documentElement.classList.contains("dark") ? "dark" : "light"
                );
                lucide.createIcons();
            };
        });
    </script>

</body>

</html>