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
        <aside
            class="w-80 bg-white dark:bg-gray-800 rounded-xl
           border border-gray-200 dark:border-gray-700
           p-4 shadow-sm flex flex-col">

            <!-- HEADER -->
            <div class="mb-4">
                <h2 class="font-semibold">Transaction & Payment</h2>
                <p class="text-xs text-gray-400">
                    Review totals and complete payment
                </p>
            </div>

            <!-- BREAKDOWN -->
            <div id="paymentBreakdown" class="flex-1 space-y-4 text-sm overflow-y-auto">

                <!-- SERVICES -->
                <div>
                    <div class="text-xs font-semibold uppercase text-gray-400 mb-1">
                        Services
                    </div>
                    <div
                        id="serviceBreakdown"
                        class="space-y-1 rounded-lg border
                       border-gray-200 dark:border-gray-700
                       p-2 bg-gray-50 dark:bg-gray-900">
                    </div>
                </div>

                <!-- EXTRA PRODUCTS -->
                <div>
                    <div class="text-xs font-semibold uppercase text-gray-400 mb-1">
                        Extra Products
                    </div>
                    <div
                        id="productBreakdown"
                        class="space-y-1 rounded-lg border
                       border-gray-200 dark:border-gray-700
                       p-2 bg-gray-50 dark:bg-gray-900">
                    </div>
                </div>

                <!-- SUMMARY CARD -->
                <div
                    class="rounded-xl border border-gray-200 dark:border-gray-700
                   p-3 space-y-2 bg-white dark:bg-gray-800">

                    <!-- LINE ITEMS -->
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

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-2"></div>

                    <!-- VAT -->
                    <div class="flex items-center justify-between text-xs">
                        <span>Include VAT</span>
                        <button
                            id="toggleVatBtn"
                            class="px-2 py-1 rounded-md border text-[11px]
                           border-gray-300 dark:border-gray-600">
                            ON
                        </button>
                    </div>

                    <div class="flex justify-between text-xs">
                        <span>VAT (<span id="vatRateLabel">0</span>%)</span>
                        <span id="vatAmount">₱0.00</span>
                    </div>

                    <div class="border-t border-gray-200 dark:border-gray-700 pt-2"></div>

                    <!-- SUBTOTAL -->
                    <div class="flex justify-between font-medium">
                        <span>Subtotal</span>
                        <span id="subtotalAmount">₱0.00</span>
                    </div>

                    <!-- PAYMENT METHOD (PROMINENT) -->
                    <div
                        id="lockPaymentMethodSummary"
                        class="mt-2 pt-2 border-t
                       border-gray-200 dark:border-gray-700
                       flex items-start justify-between gap-3">

                        <span class="text-xs text-gray-500">
                            Payment Method
                        </span>

                        <!-- JS injects here -->
                        <span class="text-gray-400 italic text-xs">
                            Loading…
                        </span>
                    </div>

                    <!-- TOTAL -->
                    <div
                        class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-600
                       flex justify-between text-lg font-semibold text-emerald-600">
                        <span>Total</span>
                        <span id="transactionTotal">₱0.00</span>
                    </div>
                </div>
            </div>

            <!-- ACTION -->
            <button
                id="payBtn"
                disabled
                class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700
               text-white py-3 rounded-xl font-semibold opacity-50">
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

            <!-- HEADER -->
            <div class="mb-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Review & Confirm Transaction
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Please confirm the details before locking this transaction.
                </p>
            </div>

            <!-- CUSTOMER CARD -->
            <div class="mb-4 rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                <div class="text-sm font-medium text-gray-800 dark:text-gray-200"
                    id="lockClientName">
                    Client Name
                </div>
                <div class="text-xs text-gray-500 mt-0.5">
                    Transaction #: <span id="lockTransactionNumber"></span>
                </div>
            </div>

            <!-- ITEMS (RECEIPT STYLE) -->
            <div class="flex-1 overflow-y-auto space-y-4 text-sm">

                <!-- SERVICES -->
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">
                        Services
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-2"
                        id="lockServiceSummary">
                        <!-- injected -->
                    </div>
                </div>

                <!-- EXTRA PRODUCTS -->
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">
                        Extra Products
                    </div>

                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-2"
                        id="lockProductSummary">
                        <!-- injected -->
                    </div>
                </div>
            </div>

            <!-- PAYMENT SUMMARY -->
            <div class="mt-5 rounded-lg bg-gray-50 dark:bg-gray-900 p-4 text-sm space-y-2">

                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Services</span>
                    <span id="lockServicesTotal">₱0.00</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Consumables</span>
                    <span id="lockConsumablesTotal">₱0.00</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Extra Products</span>
                    <span id="lockExtraProductsTotal">₱0.00</span>
                </div>

                <div class="flex justify-between text-xs text-gray-500">
                    <span>VAT (<span id="lockVatRate">12.00</span>%)</span>
                    <span id="lockVatAmount">₱0.00</span>
                </div>

                <div class="border-t border-gray-300 dark:border-gray-700 pt-2 flex justify-between font-medium">
                    <span>Subtotal</span>
                    <span id="lockSubtotal">₱0.00</span>
                </div>

                <!-- FINAL TOTAL -->
                <div class="flex justify-between text-lg font-semibold text-emerald-600">
                    <span>Total</span>
                    <span id="lockGrandTotal">₱0.00</span>
                </div>
            </div>

            <!-- NOTICE -->
            <p class="text-xs text-gray-500 text-center mt-3">
                Once confirmed, this transaction will be locked and ready for payment.
            </p>

            <!-- COUNTDOWN -->
            <div id="lockCountdown"
                class="text-xs text-gray-500 text-center mt-2">
                Preparing payment…
            </div>

            <!-- ACTIONS -->
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
                    Confirm & Lock
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

    <script src="../js/cashier/cashier-state.js"></script>
    <script src="../js/cashier/cashier-ui.js"></script>
    <script src="../js/cashier/cashier-uinicon.js"></script>
    <script src="../js/cashier/cashier-shift.js"></script>

    <!-- MUST be before left-transaction -->
    <script src="../js/cashier/cashier-right-summary.js"></script>

    <script src="../js/cashier/cashier-left-appointments.js"></script>
    <script src="../js/cashier/cashier-mid-services.js"></script>
    <script src="../js/cashier/cashier-mid-extra-products.js"></script>
    <script src="../js/cashier/cashier-left-transaction.js"></script>



</body>

</html>