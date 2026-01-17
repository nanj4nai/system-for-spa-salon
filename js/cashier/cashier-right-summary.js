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
        if (d.payment_status === "paid" && d.payment_method) {
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

function renderPaymentOption(method, active) {
    const isActive = method === active;
    return `
        <button
            data-method="${method}"
            class="payment-method-btn px-3 py-1 rounded-full border
                ${isActive
            ? "bg-emerald-500 text-white border-emerald-500"
            : "border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"}">
            ${formatPaymentMethod(method)}
        </button>
    `;
}

function attachPaymentMethodHandlers(transactionId) {
    document.querySelectorAll(".payment-method-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            const method = btn.dataset.method;

            // Store locally for now (used on payment screen)
            CashierState.selectedPaymentMethod = method;

            // Re-render UI
            document.querySelectorAll(".payment-method-btn")
                .forEach(b => b.classList.remove("bg-emerald-500", "text-white"));

            btn.classList.add("bg-emerald-500", "text-white");
        });
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
