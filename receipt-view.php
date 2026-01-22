<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['admin', 'cashier'])
) {
    die("Unauthorized");
}

$receiptNumber = $_GET['receipt_number'] ?? '';
if (!$receiptNumber) {
    die("Invalid receipt");
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Receipt <?= htmlspecialchars($receiptNumber) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: #111;
        }

        @media print {

            /* 🧾 Thermal paper size */
            @page {
                size: 80mm auto;
                margin: 0;
            }

            /* 🔥 Kill ALL screen layout */
            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                width: 80mm !important;
                height: auto !important;
                min-height: auto !important;
                background: white !important;
                display: block !important;
            }

            /* 🚫 Remove Tailwind layout effects */
            body {
                align-items: unset !important;
                justify-content: unset !important;
            }

            /* 🧾 Receipt positioning */
            #receiptPaper {
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 !important;
                padding: 4mm !important;
                box-shadow: none !important;
            }

            /* 🚫 Hide buttons */
            .print\:hidden {
                display: none !important;
            }
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen">

    <div id="receiptPaper"
        class="bg-white text-black p-4 text-xs"
        style="width:280px;font-family:monospace">

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
            <button onclick="window.print()"
                class="flex-1 bg-black text-white py-1 rounded">
                Print
            </button>
            <button onclick="window.close()"
                class="flex-1 bg-gray-300 py-1 rounded">
                Close
            </button>
        </div>
    </div>

    <script>
        fetch(
                "php/cashier/right/get-receipt.php?receipt_number=<?= urlencode($receiptNumber) ?>", {
                    credentials: "same-origin"
                }
            )
            .then(r => r.json())
            .then(d => {
                if (!d.success) {
                    alert(d.error || "Receipt not found");
                    return;
                }

                document.getElementById("rReceiptNo").textContent = d.receipt;
                document.getElementById("rDate").textContent =
                    new Date(d.meta.payment_date).toLocaleString();
                document.getElementById("rCashier").textContent =
                    "<?= htmlspecialchars($_SESSION['username'] ?? '—') ?>";

                document.getElementById("rTotal").textContent =
                    "₱" + d.total.toFixed(2);
                document.getElementById("rPaid").textContent =
                    "₱" + d.paid.toFixed(2);
                document.getElementById("rBalance").textContent =
                    "₱" + d.balance.toFixed(2);
                document.getElementById("rMethod").textContent = d.method;

                // Single-line payment receipt (correct accounting)
                document.getElementById("rItems").innerHTML = `
        <div class="flex justify-between">
            <span>Payment</span>
            <span>₱${d.paid.toFixed(2)}</span>
        </div>
    `;
            });
    </script>

</body>

</html>