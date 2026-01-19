// ================================
// CASHIER — PAYMENTS
// ================================

const EWALLET_METHODS = ["gcash", "paymaya", "grabpay", "shopeepay"];
const NON_CASH_METHODS = [...EWALLET_METHODS, "card"];

function openPaymentModal() {
    const modal = document.getElementById("paymentModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    const confirmBtn = document.getElementById("confirmPaymentBtn");
    confirmBtn.disabled = true;
    confirmBtn.classList.add("opacity-50");

    const total = Number(
        document.getElementById("transactionTotal").textContent.replace("₱", "")
    );

    document.getElementById("paymentTotal").textContent = `₱${total.toFixed(2)}`;

    const input = document.getElementById("cashReceivedInput");
    const label = document.querySelector("label[for='cashReceivedInput']");
    const refLabel = document.querySelector("#onlinePaymentFields label");
    const refInput = document.getElementById("paymentReferenceInput");

    const method = CashierState.selectedPaymentMethod || "cash";

    // 🔹 Reference label setup
    if (EWALLET_METHODS.includes(method)) {
        refLabel.textContent = "E-Wallet Reference No.";
        refInput.placeholder = "e.g. 8934729834";
    } else if (method === "card") {
        refLabel.textContent = "Card Auth / Last 4 Digits";
        refInput.placeholder = "e.g. 491273 or 1234";
    }

    // 🔹 Amount behavior
    if (method !== "cash") {
        input.value = total.toFixed(2);
        label.textContent = "Amount Charged";

        // ✅ enable confirm immediately
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

document.getElementById("cashReceivedInput")
    ?.addEventListener("input", () => {

        const received = Number(cashReceivedInput.value);
        const total = Number(
            document.getElementById("transactionTotal").textContent.replace("₱", "")
        );

        const method = CashierState.selectedPaymentMethod || "cash";
        const label = document.getElementById("paymentCalcLabel");
        const value = document.getElementById("paymentCalcValue");
        const confirmBtn = document.getElementById("confirmPaymentBtn");

        // 🔒 Non-cash: always enabled, amount is fixed
        if (NON_CASH_METHODS.includes(method)) {
            confirmBtn.disabled = false;
            confirmBtn.classList.remove("opacity-50");
            label.textContent = "Change";
            value.textContent = "₱0.00";
            return;
        }

        // 💵 Cash logic
        if (!received || received <= 0) {
            label.textContent = "Change";
            value.textContent = "₱0.00";
            confirmBtn.disabled = true;
            confirmBtn.classList.add("opacity-50");
            return;
        }

        confirmBtn.disabled = false;
        confirmBtn.classList.remove("opacity-50");

        if (received < total) {
            label.textContent = "Balance Due";
            value.textContent = `₱${(total - received).toFixed(2)}`;
        } else {
            label.textContent = "Change";
            value.textContent = `₱${(received - total).toFixed(2)}`;
        }
    });


document.getElementById("cancelPaymentBtn")
    ?.addEventListener("click", () => {

        document.getElementById("paymentModal").classList.add("hidden");

        // 🔓 RESTORE EDITABLE STATE
        CashierState.pendingPayment = false;

        unlockTransactionEditing();
        unlockPaymentMethodUI();
        unlockPaymentUI();

        showToast("Payment cancelled — transaction still editable", "info");
    });

document.getElementById("confirmPaymentBtn")
    ?.addEventListener("click", async () => {

        const received = Number(cashReceivedInput.value);
        const total = Number(
            document.getElementById("transactionTotal").textContent.replace("₱", "")
        );

        const method = CashierState.selectedPaymentMethod || "cash";
        const reference = paymentReferenceInput.value.trim();
        const remarks = paymentRemarksInput.value.trim();

        if (received <= 0) {
            showToast("Enter payment amount", "error");
            return;
        }

        // 🔒 Non-cash must be exact
        if (NON_CASH_METHODS.includes(method) && received !== total) {
            showToast("Non-cash payments must be exact amount", "error");
            return;
        }

        // 🔒 Reference required
        if (NON_CASH_METHODS.includes(method) && !reference) {
            showToast("Reference number is required", "error");
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
                remarks: remarks || null
            })
        });

        const d = await res.json();
        if (!d.success) {
            showToast(d.error || "Payment failed", "error");
            return;
        }

        paymentModal.classList.add("hidden");

        if (d.payment_status === "paid") {
            await fetch("../php/cashier/left/lock-transaction.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    transaction_id: CashierState.activeTransactionId,
                    payment_method: method
                })
            });

            CashierState.transactionLocked = true;
            loadTransaction(CashierState.activeTransactionId);
            refreshAppointmentsRealtime();

            showToast("Payment completed", "success");
        } else {
            loadTransaction(CashierState.activeTransactionId);
            refreshAppointmentsRealtime();

            showToast("Partial payment recorded", "info");
        }
    });



