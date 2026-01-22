// ================================
// PAYMENT METHOD SUMMARY (SMART)
// ================================

async function loadPaymentMethodSummary(transactionId) {
    const box = document.getElementById("lockPaymentMethodSummary");
    if (!box) return;

    box.innerHTML = `
        <span class="text-gray-600 dark:text-gray-400">Payment Method</span>
        <span class="text-gray-400 italic">Loading…</span>
    `;

    try {
        const res = await fetch(
            `../php/cashier/right/get-payment-method.php?transaction_id=${transactionId}`,
            { cache: "no-store" }
        );

        const d = await res.json();
        if (!d.success) throw new Error();

        // ======================
        // FULLY PAID → LOCKED
        // ======================
        if (
            (d.payment_status === "paid" ||
                d.status === "locked" ||
                d.is_receivable == 1) &&
            d.payment_method
        ) {
            box.innerHTML = `
                <span class="text-gray-600 dark:text-gray-400">
                    Payment Method
                </span>
                <span class="text-gray-800 dark:text-gray-200 font-medium">
                    ${formatPaymentMethod(d.payment_method)}
                </span>
            `;
            return;
        }

        // ======================
        // UNPAID / PARTIAL → SELECTABLE
        // ======================
        box.innerHTML = `
            <span class="text-gray-600 dark:text-gray-400 text-xs">
                Payment Method
            </span>

            <div class="grid grid-cols-2 gap-2 text-xs w-full">
                ${renderPaymentOption("cash", d.payment_method)}
                ${renderPaymentOption("gcash", d.payment_method)}
                ${renderPaymentOption("card", d.payment_method)}
                ${renderPaymentOption("other", d.payment_method)}
            </div>
        `;
        attachPaymentMethodHandlers(transactionId);

    } catch (err) {
        box.innerHTML = `
            <span class="text-gray-600 dark:text-gray-400">
                Payment Method
            </span>
            <span class="text-red-500">Error</span>
        `;
    }
}

// ================================
// UI HELPERS
// ================================

function renderPaymentOption(method, dbMethod) {
    const isActive =
        method === CashierState.selectedPaymentMethod ||
        method === dbMethod;
    const disabled = CashierState.transactionLocked ? "disabled" : "";
    return `
<button
    type="button"
    ${disabled}
    data-method="${method}"
    class="payment-method-btn px-3 py-1 rounded-full border
                ${isActive
            ? "bg-emerald-500 text-white border-emerald-500"
            : "border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"}">
            ${formatPaymentMethod(method)}
        </button>
    `;
}

function attachPaymentMethodHandlers() {
    document.querySelectorAll(".payment-method-btn").forEach(btn => {
        btn.onclick = () => {

            const confirmBtn = document.getElementById("confirmLockBtn");
            if (confirmBtn && CashierState.lockCountdownFinished) {
                confirmBtn.disabled = false;
                confirmBtn.classList.remove("opacity-50");
            }


            if (CashierState.transactionLocked) {
                showToast("Payment method is locked", "info");
                return;
            }

            const method = btn.dataset.method;
            CashierState.selectedPaymentMethod = method;

            document.querySelectorAll(".payment-method-btn")
                .forEach(b =>
                    b.classList.remove(
                        "bg-emerald-500",
                        "text-white",
                        "border-emerald-500"
                    )
                );

            btn.classList.add(
                "bg-emerald-500",
                "text-white",
                "border-emerald-500"
            );
        };
    });
}



// ================================
// LABEL FORMATTER
// ================================
function formatPaymentMethod(method) {
    switch (method) {
        case "cash": return "Cash";
        case "gcash": return "GCash";
        case "card": return "Card";
        case "other": return "Other";
        default: return method;
    }
}

function renderPaymentReceipts(payments = []) {
    const box = document.getElementById("paymentReceiptList");
    if (!box) return;

    if (!payments.length) {
        box.innerHTML = `
            <div class="text-xs italic text-gray-400">
                No payments recorded
            </div>
        `;
        return;
    }

    box.innerHTML = payments.map(p => `
        <div class="flex justify-between items-center text-xs border-t pt-1">
            <div>
                <div class="font-medium">
                    ${formatPaymentMethod(p.payment_method)}
                    · ₱${Number(p.amount).toFixed(2)}
                </div>
                <div class="text-[11px] text-gray-400">
                    ${new Date(p.payment_date).toLocaleString()}
                </div>
            </div>

            ${p.receipt_number
            ? `
                <button
                    class="text-emerald-600 hover:underline text-xs"
                    onclick="openReceiptByNumber('${p.receipt_number}')">
                    ${p.receipt_number}
                </button>
                `
            : `<span class="text-gray-300 italic">No receipt</span>`
        }
        </div>
    `).join("");
}
