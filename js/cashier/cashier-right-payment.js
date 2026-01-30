// ================================
// CASHIER — PAYMENTS (FINAL)
// ================================

const EWALLET_METHODS = ["gcash", "paymaya", "grabpay", "shopeepay"];
const NON_CASH_METHODS = [...EWALLET_METHODS, "card"];

const receivableBox = document.getElementById("receivableOption");
const receivableCheckbox = document.getElementById("markAsReceivable");

// ================================
// OPEN PAYMENT MODAL
// ================================

function openPaymentModal() {
    if (CashierState.balanceDue === undefined) {
        showToast("Payment balance not ready. Please try again.", "error");
        return;
    }
    const modal = document.getElementById("paymentModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    const confirmBtn = document.getElementById("confirmPaymentBtn");
    confirmBtn.disabled = true;
    confirmBtn.classList.add("opacity-50");

    // reset receivable UI + state
    receivableBox?.classList.add("hidden");
    if (receivableCheckbox) receivableCheckbox.checked = false;

    CashierState.pendingPayment = false;

    const balance = Number(CashierState.balanceDue ?? 0);

    document.getElementById("paymentTotal").textContent =
        `₱${balance.toFixed(2)}`;


    const input = document.getElementById("cashReceivedInput");
    const label = document.querySelector("label[for='cashReceivedInput']");
    const refLabel = document.querySelector("#onlinePaymentFields label");
    const refInput = document.getElementById("paymentReferenceInput");
    const remarksInput = document.getElementById("paymentRemarksInput");

    // 🔥 RESET STALE DATA
    if (refInput) refInput.value = "";
    if (remarksInput) remarksInput.value = "";

    const method = CashierState.selectedPaymentMethod || "cash";

    // Reference label
    if (EWALLET_METHODS.includes(method)) {
        refLabel.textContent = "E-Wallet Reference No.";
        refInput.placeholder = "e.g. 8934729834";
    } else if (method === "card") {
        refLabel.textContent = "Card Auth / Last 4 Digits";
        refInput.placeholder = "e.g. 491273 or 1234";
    }

    // Amount behavior
    if (method !== "cash") {
        input.value = balance.toFixed(2);
        label.textContent = "Amount Charged";

        confirmBtn.disabled = false;
        confirmBtn.classList.remove("opacity-50");
    } else {
        input.value = "";
        label.textContent = "Amount Received";
    }

    document.getElementById("paymentCalcLabel").textContent = "Change";
    document.getElementById("paymentCalcValue").textContent = "₱0.00";

    togglePaymentExtraFields();
}

// ================================
// CASH INPUT HANDLER
// ================================

document.getElementById("cashReceivedInput")?.addEventListener("input", () => {
    const received = Number(cashReceivedInput.value);

    const total = Number(CashierState.balanceDue ?? 0);


    const method = CashierState.selectedPaymentMethod || "cash";
    const label = document.getElementById("paymentCalcLabel");
    const value = document.getElementById("paymentCalcValue");
    const confirmBtn = document.getElementById("confirmPaymentBtn");

    // reset receivable UI
    receivableBox?.classList.add("hidden");
    if (receivableCheckbox) receivableCheckbox.checked = false;

    // Non-cash
    if (NON_CASH_METHODS.includes(method)) {
        confirmBtn.disabled = false;
        confirmBtn.classList.remove("opacity-50");
        label.textContent = "Change";
        value.textContent = "₱0.00";
        return;
    }

    // Invalid
    if (!received || received <= 0) {
        label.textContent = "Change";
        value.textContent = "₱0.00";
        confirmBtn.disabled = true;
        confirmBtn.classList.add("opacity-50");
        return;
    }

    confirmBtn.disabled = false;
    confirmBtn.classList.remove("opacity-50");

    // Partial cash
    if (received < total) {
        label.textContent = "Balance Due";
        value.textContent = `₱${(total - received).toFixed(2)}`;

        receivableBox?.classList.remove("hidden");
    } else {
        label.textContent = "Change";
        value.textContent = `₱${(received - total).toFixed(2)}`;
    }
});

// ================================
// RECEIVABLE CHECKBOX HANDLER
// ================================

receivableCheckbox?.addEventListener("change", () => {
    if (receivableCheckbox.checked) {
        // 🔒 Immediate soft-lock
        CashierState.pendingPayment = true;

        lockTransactionEditing(
            "Marked as Account Receivable — transaction locked"
        );
        lockPaymentMethodUI();
        lockPaymentUI();

        showToast(
            "This transaction will be saved as Account Receivable and cannot be edited",
            "info"
        );
    } else {
        // allow undo BEFORE confirm
        if (!CashierState.transactionLocked) {
            CashierState.pendingPayment = false;

            unlockTransactionEditing();
            unlockPaymentMethodUI();
            unlockPaymentUI();

            showToast(
                "Receivable unchecked — transaction is editable again",
                "info"
            );
        }
    }
});

// ================================
// CANCEL PAYMENT
// ================================

document.getElementById("cancelPaymentBtn")?.addEventListener("click", () => {
    closePaymentModal();

    CashierState.pendingPayment = false;

    unlockTransactionEditing();
    unlockPaymentMethodUI();
    unlockPaymentUI();

    showToast("Payment cancelled — transaction still editable", "info");
});

// ================================
// CONFIRM PAYMENT
// ================================

document.getElementById("confirmPaymentBtn")?.addEventListener("click", async () => {
    const received = Number(cashReceivedInput.value);

    const total = Number(CashierState.balanceDue ?? 0);

    const method = CashierState.selectedPaymentMethod || "cash";
    const reference = paymentReferenceInput.value.trim();
    const remarks = paymentRemarksInput.value.trim();

    const markReceivable =
        method === "cash" &&
        received < total &&
        receivableCheckbox?.checked === true;

    if (received <= 0) {
        showToast("Enter payment amount", "error");
        return;
    }

    if (NON_CASH_METHODS.includes(method) && received !== total) {
        showToast("Non-cash payments must be exact amount", "error");
        return;
    }

    if (NON_CASH_METHODS.includes(method) && !reference) {
        showToast("Reference number is required", "error");
        return;
    }

    // ⚠️ Strong warning
    if (method === "cash" && received < total && !markReceivable) {
        showToast(
            "⚠️ Partial cash payment detected. Please mark as Account Receivable to proceed.",
            "error"
        );

        receivableBox?.classList.remove("hidden");
        receivableCheckbox?.focus();
        return;
    }

    const res = await fetch("../php/cashier/right/record-payment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            transaction_id: CashierState.activeTransactionId,
            amount: received,
            payment_method: method,
            reference_number: method === "cash" ? null : reference,
            remarks: remarks || null,
            mark_receivable: markReceivable ? 1 : 0
        })
    });

    const d = await res.json();
    if (!d.success) {
        showToast(d.error || "Payment failed", "error");
        return;
    }

    closePaymentModal();

    // 🔒 HARD LOCK AFTER SUCCESS
    CashierState.transactionLocked = true;

    loadTransaction(CashierState.activeTransactionId);
    refreshAppointmentsRealtime();

    openReceiptModal({
        receipt: d.receipt_number,
        balanceBeforePayment: total,
        paid: received,
        balance: d.balance,
        method: method
    });

    showToast("Payment completed", "success");

});

function openReceiptModal(data) {

    const totalBefore =
        data.balanceBeforePayment ?? data.total ?? 0;

    const paid =
        data.paid ?? 0;

    const balanceAfter =
        data.balanceAfterPayment ?? data.balance ?? 0;

    document.getElementById("rReceiptNo").textContent = data.receipt;
    document.getElementById("rDate").textContent =
        new Date().toLocaleString();

    document.getElementById("rCashier").textContent =
        CashierState.currentUser || "Cashier";

    document.getElementById("rTotal").textContent =
        `₱${Number(totalBefore).toFixed(2)}`;

    document.getElementById("rPaid").textContent =
        `₱${Number(paid).toFixed(2)}`;

    document.getElementById("rBalance").textContent =
        `₱${Number(balanceAfter).toFixed(2)}`;

    document.getElementById("rMethod").textContent =
        (data.method || "").toUpperCase();

    const itemsBox = document.getElementById("rItems");

    const paymentLabel =
        balanceAfter > 0 ? "PARTIAL PAYMENT" : "FULL PAYMENT";

    itemsBox.innerHTML = `
        <div class="flex justify-between font-semibold">
            <span>${paymentLabel}</span>
            <span>₱${Number(paid).toFixed(2)}</span>
        </div>

        ${balanceAfter > 0 ? `
            <div class="text-[10px] mt-1 text-gray-600">
                Remaining balance recorded as Account Receivable
            </div>
        ` : ""}
    `;

    document.getElementById("receiptModal")
        .classList.remove("hidden");
}


function closeReceiptModal() {
    document.getElementById("receiptModal").classList.add("hidden");
}

function printReceipt() {
    window.print();
}

async function openReceiptByNumber(receiptNumber) {
    const res = await fetch(
        `../php/cashier/right/get-receipt.php?receipt_number=${receiptNumber}`,
        { cache: "no-store" }
    );

    const d = await res.json();
    if (!d.success) {
        showToast("Failed to load receipt", "error");
        return;
    }

    openReceiptModal(d);
}
