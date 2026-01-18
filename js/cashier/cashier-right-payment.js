// ================================
// CASHIER — PAYMENTS
// ================================

function openPaymentModal() {
    const modal = document.getElementById("paymentModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    document.getElementById("paymentTotal").textContent =
        document.getElementById("transactionTotal").textContent;

    document.getElementById("cashReceivedInput").value = "";
    document.getElementById("changeAmount").textContent = "₱0.00";
}

document.getElementById("cashReceivedInput")
    ?.addEventListener("input", () => {

        const received = Number(
            document.getElementById("cashReceivedInput").value
        );

        const total = Number(
            document.getElementById("transactionTotal")
                .textContent.replace("₱", "")
        );

        document.getElementById("changeAmount").textContent =
            `₱${Math.max(0, received - total).toFixed(2)}`;
    });

document.getElementById("cancelPaymentBtn")
    ?.addEventListener("click", () => {
        document.getElementById("paymentModal").classList.add("hidden");
    });

document.getElementById("confirmPaymentBtn")
    ?.addEventListener("click", async () => {

        const received = Number(
            document.getElementById("cashReceivedInput").value
        );

        const total = Number(
            document.getElementById("transactionTotal")
                .textContent.replace("₱", "")
        );

        if (received <= 0) {
            showToast("Enter payment amount", "error");
            return;
        }

        const method = CashierState.selectedPaymentMethod || "cash";

        const res = await fetch("../php/cashier/right/record-payment.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                transaction_id: CashierState.activeTransactionId,
                amount: received,
                payment_method: method
            })
        });

        const d = await res.json();
        if (!d.success) {
            showToast(d.error || "Payment failed", "error");
            return;
        }

        document.getElementById("paymentModal").classList.add("hidden");

        loadTransaction(CashierState.activeTransactionId);

        if (d.payment_status === "paid") {
            lockTransactionEditing("Payment completed");
            lockPaymentUI("Fully paid");
            showToast("Payment completed", "success");
        } else {
            showToast("Partial payment recorded", "info");
        }
    });

