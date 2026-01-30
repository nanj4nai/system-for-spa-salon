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
    <title>Cashier — <?= htmlspecialchars($company_name) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="<?= htmlspecialchars('../' . $company_logo) ?>?v=<?= time() ?>" />
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
    <style>
        .card-locked {
            opacity: 0.55;
            filter: grayscale(20%);
            pointer-events: none;
        }

        @media print {

            /* 🔒 Hide everything except receipt */
            body * {
                visibility: hidden;
            }

            #receiptPaper,
            #receiptPaper * {
                visibility: visible;
            }

            /* 🧾 THERMAL PAPER SETTINGS */
            @page {
                size: 80mm auto;
                /* change to 58mm if needed */
                margin: 0;
            }

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                width: 80mm;
                background: white;
            }

            /* 🚫 Disable layout centering */
            body {
                display: block !important;
                min-height: auto !important;
            }

            /* 🧾 Receipt positioning */
            #receiptPaper {
                position: relative;
                left: 0;
                top: 0;
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 auto;
                padding: 4mm;
                box-shadow: none !important;
            }

            /* 🚫 Hide buttons */
            .print\:hidden {
                display: none !important;
            }
        }
    </style>
</head>

<body class="h-full font-sans bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-100">

    <!-- ================= NO SHIFT OVERLAY ================= -->
    <div id="noShiftOverlay" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 backdrop-blur-sm hidden">

        <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 w-full max-w-md text-center shadow-xl">
            <!-- BLOCKED MESSAGE -->
            <div id="blockedMessage" class="hidden">
                <h2 class="text-2xl font-semibold mb-2">Shift Not Opened</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Waiting for admin to open your shift gate.
                </p>
            </div>

            <!-- AWAITING OPEN (OPENING CASH) -->
            <div id="awaitingOpenSection" class="hidden">
                <h2 class="text-2xl font-semibold mb-2">Start Shift</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Enter opening cash to begin your shift.
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
                    Start Shift
                </button>
            </div>

            <!-- LOGOUT -->
            <button id="logoutBtn"
                class="mt-4 w-full bg-gray-300 hover:bg-gray-400
           text-gray-800 py-2 rounded-xl font-medium">
                Log out
            </button>

        </div>
    </div>

    <!-- ================= WAITING FOR ADMIN OVERLAY ================= -->
    <div id="pendingApprovalOverlay"
        class="fixed inset-0 z-50 hidden flex items-center justify-center
            bg-gray-900/60 backdrop-blur-sm">

        <!-- ================= SHIFT SUMMARY MODAL ================= -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl
                w-full max-w-4xl max-h-[85vh]
                shadow-xl flex flex-col overflow-hidden">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-xl font-semibold">
                    Shift Pending Admin Approval
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    This shift is locked. Review-only access.
                </p>
            </div>

            <!-- BODY (SCROLLABLE) -->
            <div id="shiftSummaryView"
                class="flex-1 overflow-y-auto p-6 space-y-6 text-sm">

                <div class="text-center text-gray-400 italic">
                    Loading shift summary…
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700
                    text-xs text-gray-500 text-center">
                Waiting for admin approval. Editing and payments are disabled.
            </div>
        </div>
    </div>


    <?php include 'cashier-navbar.php'; ?>

    <!-- ================= MAIN POS ================= -->

    <div id="possContainer" class="flex h-[calc(100vh-3.5rem)] gap-4 p-4">
        <!-- ===== LEFT ===== -->

        <?php include 'cashier-left-appointments.php'; ?>
        <div id="posContainer"
            class="flex flex-1 gap-4 transition-all">

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
            <aside class="w-80 bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm flex flex-col">

                <!-- HEADER -->
                <div class="mb-4">
                    <h2 class="font-semibold">Transaction & Payment</h2>
                    <p class="text-xs text-gray-400">
                        Review totals and complete payment
                    </p>
                </div>
                <!-- TIPS -->
                <div class="panel-tip hidden mb-3 p-3 rounded-lg
            bg-teal-50 dark:bg-teal-900/30
            text-[11px] text-teal-700 dark:text-teal-300
            border border-teal-200 dark:border-teal-800">

                    💡<strong>3.</strong>
                    Review services and products carefully before payment.
                    VAT and payment method can still be adjusted.
                </div>


                <!-- BREAKDOWN -->
                <div id="paymentBreakdown" class="flex-1 space-y-4 text-sm overflow-y-auto">
                    <div class="panel-tip hidden text-[11px] text-gray-400 mb-2 pl-1">
                        Tip: Items listed here come from the active transaction.
                    </div>


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
                            <span>Extra Products</span>
                            <span id="extraProductsTotal">₱0.00</span>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 pt-2"></div>

                        <div class="panel-tip hidden text-[11px] text-gray-400 mb-1 text-left">
                            Tip: Toggle VAT if the customer requests VAT-inclusive pricing.
                        </div>

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
                        <!-- PAYMENT STATUS -->
                        <div
                            class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700
                                space-y-1 text-xs">
                            <div
                                id="paymentReferenceRow"
                                class="flex justify-between hidden">
                                <span class="text-gray-500">Reference</span>
                                <span
                                    id="paymentReferenceLabel"
                                    class="font-mono text-[11px]">
                                    —
                                </span>
                            </div>

                            <div id="paymentReceiptList" class="mt-2 space-y-1"></div>
                        </div>
                        <div
                            id="paymentStatusBox"
                            class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700
                                space-y-1 text-xs">
                            <div class="panel-tip hidden text-[11px] text-gray-400 text-center">
                                Tip: Full payment or Mark as Account Receivable is required before closing the transaction.
                            </div>

                            <div class="flex justify-between">
                                <span class="text-gray-500">Payment Status</span>
                                <span
                                    id="paymentStatusLabel"
                                    class="font-semibold text-gray-400">
                                    UNPAID
                                </span>
                            </div>

                            <div class="flex justify-between">
                                <span class="text-gray-500">Amount Paid</span>
                                <span id="amountPaidLabel">₱0.00</span>
                            </div>

                            <div class="flex justify-between">
                                <span class="text-gray-500">Balance</span>
                                <span id="balanceLabel">₱0.00</span>
                            </div>
                            <div
                                id="balanceHelper"
                                class="text-[11px] text-gray-400 text-right">
                            </div>


                        </div>

                        <!-- TOTAL -->
                        <div
                            class="mt-3 pt-3 border-t border-gray-300 dark:border-gray-600
                                flex justify-between text-sm font-medium text-gray-500">
                            <span>Original Total</span>
                            <span id="transactionTotal">₱0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Change</span>
                            <span id="changeLabel">₱0.00</span>
                        </div>
                    </div>
                </div>
                <div class="panel-tip hidden text-[11px] text-gray-400 mt-2 mb-3 text-center">
                    Tip: The payment button activates once a valid method is selected.
                </div>

                <!-- ACTION -->
                <button data-mutation
                    id="payBtn"
                    disabled
                    class="mt-4 w-full bg-emerald-600 hover:bg-emerald-700
               text-white py-3 rounded-xl font-semibold opacity-50">
                    Proceed to Payment
                </button>
            </aside>
        </div>
    </div>

    <!-- MODALS & TOASTS -->
    <?php include 'left-modals.php'; ?>
    <?php include 'mid-modals.php'; ?>

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
                    Review & Confirm Payment
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
                <div class="flex justify-between text-xs">
                    <span>Already Paid</span>
                    <span id="lockPaidTotal">₱0.00</span>
                </div>

                <div class="flex justify-between text-xs font-semibold text-amber-600">
                    <span>Balance Due</span>
                    <span id="lockBalanceDue">₱0.00</span>
                </div>

                <!-- PAYMENT METHOD SUMMARY -->
                <div class="mt-3 pt-2 border-t border-gray-200 dark:border-gray-700
            flex justify-between text-sm">
                    <span class="text-gray-500">Payment Method</span>
                    <span id="lockPaymentMethodLabel"
                        class="font-medium text-gray-800 dark:text-gray-200">
                        —
                    </span>
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

    <!-- PAYMENT MODAL -->
    <div id="paymentModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">

        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-sm">

            <h3 class="text-lg font-semibold mb-1">
                Receive Payment
            </h3>

            <p class="text-xs text-gray-500 mb-4">
                Enter amount received from client
            </p>
            <!-- ONLINE PAYMENT DETAILS (HIDDEN BY DEFAULT) -->
            <div id="onlinePaymentFields" class="hidden mb-3">

                <label class="text-xs text-gray-500">
                    Reference / Card Number
                </label>
                <input
                    id="paymentReferenceInput"
                    type="text"
                    class="w-full mt-1 px-3 py-2 rounded-lg border
               border-gray-300 dark:border-gray-600
               dark:bg-gray-700"
                    placeholder="Reference no. / Last 4 digits">

            </div>

            <!-- REMARKS -->
            <div class="mb-3">
                <label class="text-xs text-gray-500">
                    Remarks (optional)
                </label>
                <textarea
                    id="paymentRemarksInput"
                    rows="2"
                    class="w-full mt-1 px-3 py-2 rounded-lg border
               border-gray-300 dark:border-gray-600
               dark:bg-gray-700"
                    placeholder="Notes from cashier..."></textarea>
            </div>


            <!-- TOTAL -->
            <div class="mb-3 text-sm">
                <div class="flex justify-between">
                    <span>Total Due</span>
                    <span id="paymentTotal" class="font-semibold">
                        ₱0.00
                    </span>
                </div>
            </div>

            <!-- CASH INPUT -->
            <div class="mb-3">
                <label for="cashReceivedInput" class="text-xs text-gray-500">
                    Amount Received
                </label>
                <input
                    id="cashReceivedInput"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full mt-1 px-3 py-2 rounded-lg border
                       border-gray-300 dark:border-gray-600
                       dark:bg-gray-700"
                    placeholder="0.00">
            </div>
            <div id="receivableOption" class="hidden mt-3">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" id="markAsReceivable" />
                    <span>
                        Mark remaining balance as
                        <strong>Account Receivable</strong>
                    </span>
                </label>

                <p class="text-xs text-gray-400 mt-1">
                    Client will pay the remaining balance later.
                </p>
            </div>


            <!-- CHANGE -->
            <div class="text-sm mb-4">
                <div class="flex justify-between">
                    <span id="paymentCalcLabel">Change</span>
                    <span id="paymentCalcValue" class="font-medium">
                        ₱0.00
                    </span>
                </div>
            </div>


            <!-- ACTIONS -->
            <div class="flex justify-end gap-2">
                <button
                    id="cancelPaymentBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-gray-200 dark:bg-gray-600">
                    Cancel
                </button>

                <button
                    id="confirmPaymentBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-emerald-600 text-white">
                    Confirm Payment
                </button>
            </div>
        </div>
    </div>

    <!-- CLOSE SHIFT MODAL -->
    <div id="closeShiftModal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center
           bg-black/50 backdrop-blur-sm">

        <div class="bg-white dark:bg-gray-800 rounded-2xl
        w-full max-w-lg shadow-xl p-6">

            <h3 class="text-lg font-semibold mb-1">
                Close Shift
            </h3>
            <p class="text-xs text-gray-500 mb-4">
                Enter closing cash and optional remarks
            </p>
            <!-- SHIFT SUMMARY -->
            <div class="mb-4 border rounded-lg p-3 bg-gray-50 dark:bg-gray-700 text-sm">
                <div class="flex justify-between mb-1">
                    <span class="text-gray-500">Transactions</span>
                    <span id="sumTransactions">0</span>
                </div>

                <div class="flex justify-between mb-1">
                    <span class="text-gray-500">Gross Sales</span>
                    <span id="sumGross">₱0.00</span>
                </div>

                <div class="flex justify-between mb-1">
                    <span class="text-gray-500">Total Paid</span>
                    <span id="sumPaid">₱0.00</span>
                </div>
                <hr class="my-2">

                <div class="flex justify-between mb-1">
                    <span class="text-gray-500">Expected Cash</span>
                    <span id="sumExpectedCash">₱0.00</span>
                </div>

                <div class="flex justify-between mb-1">
                    <span class="text-gray-500">Declared Cash</span>
                    <span id="sumDeclaredCash">₱0.00</span>
                </div>

                <div class="flex justify-between font-medium">
                    <span class="text-gray-600">Variance</span>
                    <span id="sumVariance" class="text-gray-600">₱0.00</span>
                </div>


                <hr class="my-2">

                <div id="paymentBreakdowned" class="space-y-1 text-xs text-gray-600"></div>
            </div>

            <!-- Closing Cash -->
            <div class="mb-3">
                <label class="text-xs text-gray-500">Closing Cash</label>
                <input
                    id="closeShiftCash"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full mt-1 px-3 py-2 rounded-lg border
                       border-gray-300 dark:border-gray-600
                       dark:bg-gray-700"
                    placeholder="0.00">
            </div>

            <!-- Remarks -->
            <div class="mb-4">
                <label class="text-xs text-gray-500">
                    Remarks (optional)
                </label>
                <textarea
                    id="closeShiftRemarks"
                    rows="3"
                    class="w-full mt-1 px-3 py-2 rounded-lg border
                       border-gray-300 dark:border-gray-600
                       dark:bg-gray-700"
                    placeholder="Notes for admin..."></textarea>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2">
                <button
                    id="cancelCloseShiftBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-gray-200 dark:bg-gray-600">
                    Cancel
                </button>

                <button
                    id="confirmCloseShiftBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-red-600 text-white">
                    Submit for Approval
                </button>
            </div>
        </div>
    </div>

    <!-- FINAL CONFIRM CLOSE SHIFT MODAL -->
    <div id="finalConfirmShiftModal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center
            bg-black/50 backdrop-blur-sm">

        <div class="bg-white dark:bg-gray-800 rounded-2xl
                w-full max-w-md shadow-xl p-6">

            <h3 class="text-lg font-semibold mb-2">
                Confirm Shift Submission
            </h3>

            <p class="text-sm text-gray-500 mb-4">
                Please review the final amounts before submitting for approval.
            </p>

            <div class="border rounded-lg p-3 bg-gray-50 dark:bg-gray-700 text-sm mb-4">
                <div class="flex justify-between mb-1">
                    <span>Expected Cash</span>
                    <span id="finalExpectedCash">₱0.00</span>
                </div>

                <div class="flex justify-between mb-1">
                    <span>Declared Cash</span>
                    <span id="finalDeclaredCash">₱0.00</span>
                </div>

                <div class="flex justify-between font-semibold">
                    <span>Variance</span>
                    <span id="finalVariance">₱0.00</span>
                </div>
            </div>

            <p class="text-xs text-red-500 mb-4">
                This action will lock the shift. You won’t be able to edit
                transactions unless the admin rejects this request.
            </p>

            <div class="flex justify-end gap-2">
                <button
                    id="cancelFinalConfirmBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-gray-200 dark:bg-gray-600">
                    Go Back
                </button>

                <button
                    id="confirmFinalSubmitBtn"
                    class="px-4 py-2 text-sm rounded
                       bg-red-600 text-white">
                    Confirm & Submit
                </button>
            </div>
        </div>
    </div>

    <!-- THERMAL RECEIPT MODAL -->
    <div id="receiptModal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">

        <div id="receiptPaper"
            class="bg-white text-black p-4 text-xs"
            style="width: 280px; font-family: monospace;">

            <div class="text-center mb-2">
                <div class="font-bold">MY WELLNESS SPA</div>
                <div>Official Receipt</div>
            </div>

            <hr>

            <div class="mt-2">
                <div>Receipt #: <span id="rReceiptNo"></span></div>
                <div>Date: <span id="rDate"></span></div>
                <div>Cashier: <span id="rCashier"></span></div>
            </div>

            <hr class="my-2">

            <div id="rItems"></div>

            <hr class="my-2">

            <div class="flex justify-between">
                <span>TOTAL</span>
                <span id="rTotal"></span>
            </div>

            <div class="flex justify-between">
                <span>PAID</span>
                <span id="rPaid"></span>
            </div>

            <div class="flex justify-between">
                <span>BALANCE</span>
                <span id="rBalance"></span>
            </div>

            <div class="mt-2">
                Method: <span id="rMethod"></span>
            </div>

            <hr class="my-2">

            <div class="text-center text-[10px]">
                Thank you for your visit!
            </div>

            <div class="flex gap-2 mt-3 print:hidden">
                <button onclick="printReceipt()"
                    class="flex-1 bg-black text-white py-1 rounded">
                    Print
                </button>
                <button onclick="closeReceiptModal()"
                    class="flex-1 bg-gray-300 py-1 rounded">
                    Close
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
    <script src="../js/cashier/cashier-shift-close.js"></script>

    <!-- MUST be before left-transaction -->
    <script src="../js/cashier/cashier-right-payment.js"></script>
    <script src="../js/cashier/cashier-right-summary.js"></script>

    <script src="../js/cashier/cashier-left-appointments.js"></script>
    <script src="../js/cashier/cashier-mid-services.js"></script>
    <script src="../js/cashier/cashier-mid-extra-products.js"></script>
    <script src="../js/cashier/cashier-left-transaction.js"></script>



</body>

</html>