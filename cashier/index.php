<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit;
}

require_once "../php/company_settings.php";

/* Safe defaults (prevents warnings) */
$company_name = $company_name ?? 'Wellness Spa';
$company_logo = $company_logo ?? '../images/lap-logo.png';
$username     = $_SESSION['username'] ?? 'Cashier';
?>


<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8" />
    <title>Cashier — Wellness Spa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>

</head>

<body class="h-full font-sans bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-100">

    <!-- ================= NO SHIFT OVERLAY ================= -->
    <div id="noShiftOverlay"
        class="fixed inset-0 z-50 flex items-center justify-center
            bg-gray-900/80 backdrop-blur-sm hidden">

        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 w-full max-w-md text-center shadow-xl">
            <h2 class="text-2xl font-semibold mb-2">No Active Shift</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                You must open a shift before using the cashier system.
            </p>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">Opening Cash</label>
                <input id="openingCash" type="number" placeholder="0.00"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300
                    dark:border-gray-600 dark:bg-gray-700 outline-none">
            </div>

            <button id="openShiftBtn"
                class="w-full bg-green-500 hover:bg-green-600
                   text-white py-3 rounded-xl font-semibold">
                Open Shift
            </button>
        </div>
    </div>

    <?php include 'cashier-navbar.php'; ?>

    <!-- ================= MAIN POS ================= -->
    <div id="posContainer"
        class="flex h-[calc(100vh-3.5rem)] gap-4 p-4 opacity-50 pointer-events-none">

        <!-- ===== LEFT ===== -->
        <?php include 'cashier-left-appointments.php'; ?>

        <!-- ===== CENTER ===== -->
        <main class="flex-1 bg-white dark:bg-gray-800 rounded-xl p-6 overflow-y-auto shadow-sm">
            <h2 class="text-lg font-semibold mb-1">Service & Treatment Details</h2>
            <p class="text-xs text-gray-400 mb-4">
                Add services and assign staff for this client
            </p>

            <div class="panel-tip hidden text-xs mb-4
                bg-blue-50 dark:bg-gray-700
                text-blue-700 dark:text-blue-300
                p-3 rounded-lg flex items-start gap-2">

                <i data-lucide="clipboard-list" class="w-4 h-4 mt-0.5"></i>
                <span>
                    2. Add services and assign staff for the selected client.
                </span>
            </div>

            <div id="activeContext"
                class="mb-3 text-xs px-3 py-2 rounded-lg
            bg-blue-100 text-blue-700
            dark:bg-blue-900/40 dark:text-blue-300 hidden">
            </div>
            <button
                id="addServiceBtn"
                disabled
                class="mt-4 text-sm bg-blue-500 text-white px-4 py-2 rounded opacity-50">
                + Add Service
            </button>
            <div id="clientInfo"
                class="mb-4 p-4 bg-white dark:bg-gray-800 rounded-xl shadow text-sm text-gray-500">
                Select an appointment to begin
            </div>

            <div id="serviceList" class="space-y-3"></div>

            <!-- EXTRA PRODUCTS -->
            <div class="mt-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold">Extra Products</h3>
                    <button
                        id="addExtraProductBtn"
                        class="text-xs bg-emerald-500 text-white px-3 py-1 rounded">
                        + Add Product
                    </button>
                </div>

                <div id="extraProductList" class="space-y-2 text-sm text-gray-600">
                    <div class="text-xs italic text-gray-400">
                        No extra products added
                    </div>
                </div>
            </div>


        </main>

        <!-- ===== RIGHT ===== -->
        <aside class="w-80 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm">

            <h2 class="font-semibold mb-1">Transaction & Payment</h2>
            <p class="text-xs text-gray-400 mb-4">
                Review total and proceed to payment
            </p>
            <div class="panel-tip hidden text-xs mb-4
                bg-green-50 dark:bg-gray-700
                text-green-700 dark:text-green-300
                p-3 rounded-lg flex items-start gap-2">

                <i data-lucide="credit-card" class="w-4 h-4 mt-0.5"></i>
                <span>
                    3. Review totals and complete the payment here.
                </span>
            </div>
            <div id="paymentBreakdown" class="space-y-3 text-sm">

                <!-- Services -->
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                        Services
                    </div>
                    <div id="serviceBreakdown" class="space-y-1"></div>
                </div>

                <!-- Extra Products -->
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                        Extra Products
                    </div>
                    <div id="productBreakdown" class="space-y-1"></div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700">

                <!-- Totals -->
                <div class="space-y-1">

                    <div class="flex justify-between text-xs">
                        <span>Services</span>
                        <span id="servicesTotal">₱0.00</span>
                    </div>

                    <div class="flex justify-between text-xs">
                        <span>Consumables</span>
                        <span id="consumablesTotal">₱0.00</span>
                    </div>

                    <div class="flex justify-between text-xs">
                        <span>Extra Products</span>
                        <span id="extraProductsTotal">₱0.00</span>
                    </div>

                    <hr class="border-gray-200 dark:border-gray-700">

                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span id="subtotalAmount">₱0.00</span>
                    </div>

                    <div class="flex justify-between text-xs">
                        <span>VAT (<span id="vatRateLabel">0</span>%)</span>
                        <span id="vatAmount">₱0.00</span>
                    </div>

                    <div class="flex justify-between font-semibold text-lg">
                        <span>Total</span>
                        <span id="transactionTotal">₱0.00</span>
                    </div>
                </div>
            </div>

            <button
                id="payBtn"
                disabled
                class="mt-6 w-full bg-green-500 text-white py-3 rounded-xl font-semibold opacity-50">
                Proceed to Payment
            </button>
        </aside>
    </div>

    <!-- MODALS & TOASTS -->
    <?php include 'left-modals.php'; ?>

    <!-- ADD SERVICE MODAL -->
    <div id="serviceModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Add Service</h3>

            <div class="space-y-4">
                <!-- Service -->
                <div>
                    <label class="text-xs text-gray-500">Service</label>
                    <select id="serviceSelect"
                        class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                        <option value="">Select service</option>
                    </select>
                </div>

                <!-- Variant (hidden by default) -->
                <div id="variantWrapper" class="hidden">
                    <label class="text-xs text-gray-500">Variant</label>
                    <select id="variantSelect"
                        class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                    </select>
                </div>
                <!-- Service Products Preview -->
                <div id="serviceProductsPreview"
                    class="hidden rounded-lg border bg-gray-50 dark:bg-gray-700 p-3 text-xs space-y-1">
                </div>
                <!-- Staff -->
                <div>
                    <label class="text-xs text-gray-500">Staff</label>
                    <select id="staffSelect"
                        class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                        <option value="">Select staff</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button id="cancelServiceBtn"
                    class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                    Cancel
                </button>
                <button id="confirmAddServiceBtn"
                    class="px-4 py-2 text-sm rounded bg-blue-600 text-white">
                    Add Service
                </button>
            </div>
        </div>
    </div>

    <!-- ADD EXTRA PRODUCT MODAL -->
    <div id="extraProductModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-md">
            <h3 class="text-lg font-semibold mb-4">Add Extra Product</h3>

            <div class="space-y-4">
                <!-- Product -->
                <div>
                    <label class="text-xs text-gray-500">Product</label>
                    <select id="extraProductSelect"
                        class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                    </select>
                </div>

                <!-- Quantity wrapper -->
                <div id="extraQtyWrapper">
                    <label id="extraQtyLabel" class="text-xs text-gray-500">Quantity</label>
                    <input id="extraProductQty"
                        type="number"
                        min="0.01"
                        step="0.01"
                        value="1"
                        class="w-full px-3 py-2 rounded-lg border dark:bg-gray-700">
                </div>

                <!-- Info -->
                <div id="extraProductInfo"
                    class="text-xs text-gray-500 hidden">
                </div>
                <p class="text-[11px] text-gray-400 mt-1">
                    ! Some products are disabled because they are already included in a service
                </p>
                <p id="extraProductHint"
                    class="text-[11px] text-gray-400 mt-1">
                    Add a service first to enable extra products
                </p>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button id="cancelExtraProductBtn"
                    class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                    Cancel
                </button>
                <button id="confirmExtraProductBtn"
                    class="px-4 py-2 text-sm rounded bg-emerald-600 text-white">
                    Add Product
                </button>
            </div>
        </div>
    </div>

    <!-- LOCK TRANSACTION MODAL -->
    <div id="lockTransactionModal"
        class="fixed inset-0 z-50 hidden items-center justify-center
           bg-black/50 backdrop-blur-sm">

        <div class="bg-white dark:bg-gray-800 rounded-2xl
                w-full max-w-lg shadow-xl
                p-6 flex flex-col">

            <!-- Header -->
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Review & Confirm Transaction
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Please review the transaction details before proceeding to payment.
                </p>
            </div>

            <!-- Client / Transaction -->
            <div class="mb-4 text-sm">
                <div class="font-medium text-gray-800 dark:text-gray-200">
                    <span id="lockClientName">Client Name</span>
                </div>
                <div class="text-xs text-gray-500">
                    Transaction #: <span id="lockTransactionNumber"></span>
                </div>
            </div>

            <!-- Items (scrollable) -->
            <div class="flex-1 overflow-y-auto pr-1 text-sm space-y-4">

                <!-- Services -->
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                        Services
                    </div>
                    <div id="lockServiceSummary" class="space-y-1"></div>
                </div>

                <!-- Extra Products -->
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400 mb-1">
                        Extra Products
                    </div>
                    <div id="lockProductSummary" class="space-y-1"></div>
                </div>
            </div>

            <!-- Totals -->
            <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                <div class="flex justify-between text-sm font-medium">
                    <span>Total Amount</span>
                    <span id="lockTotalAmount">₱0.00</span>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Once confirmed, services and products can no longer be edited.
                </p>
            </div>

            <!-- Countdown -->
            <div id="lockCountdown"
                class="text-xs text-gray-500 text-center mt-3">
                Preparing payment…
            </div>

            <!-- Actions -->
            <div class="mt-4 flex justify-end gap-2">
                <button id="cancelLockBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-gray-200 dark:bg-gray-600
                       hover:bg-gray-300 dark:hover:bg-gray-500">
                    Back
                </button>

                <button id="confirmLockBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-emerald-600 hover:bg-emerald-700
                       text-white font-medium">
                    Confirm & Proceed
                </button>
            </div>
        </div>
    </div>

    <!-- REMOVE EXTRA PRODUCT MODAL -->
    <div id="removeExtraProductModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

            <h3 class="text-lg font-semibold mb-2 text-red-600">
                Remove Product
            </h3>

            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                You are about to remove:
            </p>

            <div class="mb-3 px-3 py-2 rounded bg-gray-100 dark:bg-gray-700">
                <span id="removeExtraProductName"
                    class="font-medium text-sm">
                    —
                </span>
            </div>

            <p class="text-xs text-red-500 mb-4">
                This cannot be undone. The product will be removed from the transaction.
            </p>

            <div class="flex justify-end gap-2">
                <button id="cancelRemoveExtraProductBtn"
                    class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                    Cancel
                </button>

                <button id="confirmRemoveExtraProductBtn"
                    class="px-4 py-2 text-sm rounded bg-red-600 text-white">
                    Remove
                </button>
            </div>
        </div>
    </div>


    <!-- REMOVE SERVICE MODAL -->
    <div id="removeServiceModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

            <h3 class="text-lg font-semibold mb-2 text-red-600">
                Remove Service
            </h3>

            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                You are about to remove:
            </p>

            <div class="mb-3 px-3 py-2 rounded bg-gray-100 dark:bg-gray-700">
                <span id="removeServiceName"
                    class="font-medium text-sm">
                    —
                </span>
            </div>

            <p class="text-xs text-red-500 mb-4">
                This cannot be undone. All related product usage will be removed.
            </p>

            <div class="flex justify-end gap-2">
                <button id="cancelRemoveServiceBtn"
                    class="px-4 py-2 text-sm rounded bg-gray-200 dark:bg-gray-600">
                    Cancel
                </button>

                <button id="confirmRemoveServiceBtn"
                    class="px-4 py-2 text-sm rounded bg-red-600 text-white">
                    Remove
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast"
        class="fixed bottom-6 right-6 z-50 hidden
            bg-gray-900 text-white text-sm
            px-4 py-3 rounded-lg shadow-lg">
    </div>

    <!-- ================= JS STATE CONTROLLER ================= -->
    <script src="../js/cashier/cashier-state.js"></script>
    <script src="../js/cashier/cashier-ui.js"></script>
    <script src="../js/cashier/cashier-uinicon.js"></script>
    <script src="../js/cashier/cashier-shift.js"></script>
    <script src="../js/cashier/cashier-left-appointments.js"></script>
    <script src="../js/cashier/cashier-mid-services.js"></script>
    <script src="../js/cashier/cashier-mid-extra-products.js"></script>
    <script src="../js/cashier/cashier-left-transaction.js"></script>

</body>

</html>