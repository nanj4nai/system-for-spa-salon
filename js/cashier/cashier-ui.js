// cashier-ui.js
window.showToast = function (message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;

    toast.className = `
        fixed bottom-6 right-6 z-50
        px-4 py-3 rounded-lg shadow-lg text-sm text-white
        ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}
    `;

    toast.classList.remove('hidden');

    setTimeout(() => toast.classList.add('hidden'), 2500);
};
function resetTransactionUIState() {
    // Reset buttons
    ["addServiceBtn", "addExtraProductBtn"].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.disabled = false;
        el.classList.remove("opacity-50", "opacity-40", "cursor-not-allowed");
    });

    // Reset service cards
    document.querySelectorAll(".service-card").forEach(card => {
        card.classList.remove(
            "card-locked",
            "pointer-events-none",
            "opacity-50"
        );
    });


    // Reset extra product buttons
    document
        .querySelectorAll("#extraProductList button")
        .forEach(btn => {
            btn.disabled = false;
            btn.classList.remove("opacity-40", "pointer-events-none");
        });

    // Reset payment method buttons
    document
        .querySelectorAll(".payment-method-btn")
        .forEach(btn => {
            btn.disabled = false;
            btn.classList.remove("opacity-50", "cursor-not-allowed");
        });

    // Reset pay button
    const payBtn = document.getElementById("payBtn");
    if (payBtn) {
        payBtn.disabled = false;
        payBtn.classList.remove("opacity-50");
    }
}


// ================================
// CENTER LOCKING
// ================================
function lockCenterUI(reason = "Transaction locked") {
    // Disable add buttons
    const addServiceBtn = document.getElementById("addServiceBtn");
    const addExtraBtn = document.getElementById("addExtraProductBtn");

    if (addServiceBtn) {
        addServiceBtn.disabled = true;
        addServiceBtn.classList.add("opacity-50", "cursor-not-allowed");
    }

    if (addExtraBtn) {
        addExtraBtn.disabled = true;
        addExtraBtn.classList.add("opacity-50", "cursor-not-allowed");
    }

    showToast(reason);
}

function unlockCenterUI() {
    const addServiceBtn = document.getElementById("addServiceBtn");
    const addExtraBtn = document.getElementById("addExtraProductBtn");

    if (addServiceBtn) {
        addServiceBtn.disabled = false;
        addServiceBtn.classList.remove("opacity-50", "cursor-not-allowed");
    }

    if (addExtraBtn) {
        addExtraBtn.disabled = false;
        addExtraBtn.classList.remove("opacity-50", "cursor-not-allowed");
    }

    document.querySelectorAll(".service-card").forEach(card => {
        card.classList.remove("card-locked", "pointer-events-none");
    });
}

// ================================
// TRANSACTION LOCK GUARD
// ================================
function canLockTransaction() {
    const hasServices =
        document.querySelectorAll(".service-card").length > 0;

    const extraBox = document.getElementById("extraProductList");
    const hasExtraProducts =
        extraBox &&
        !extraBox.textContent.includes("No extra products");

    return hasServices || hasExtraProducts;
}

// ================================
// TRANSACTION UI LOCKING
// ================================

function lockTransactionEditing(reason = "Transaction locked") {
    ["addServiceBtn", "addExtraProductBtn"].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.disabled = true;
        el.classList.add("opacity-50");
    });

    document
        .querySelectorAll("[data-remove-service], [data-remove-product]")
        .forEach(btn =>
            btn.classList.add("pointer-events-none", "opacity-40")
        );

    showToast(reason, "info");
} function applyTransactionLockState(transaction) {

    // 🔒 HARD LOCK (final, from DB)
    const isHardLocked =
        transaction.status === "locked" ||
        transaction.payment_status === "paid";

    // 🟡 SOFT LOCK (during payment flow only)
    const isSoftLocked = CashierState.pendingPayment === true;

    // Only HARD lock affects this flag
    CashierState.transactionLocked = isHardLocked;

    if (isHardLocked || isSoftLocked) {

        // 🔒 lock editing + payment UI
        lockTransactionEditing();
        lockPaymentMethodUI();
        lockPaymentUI();

        // 🔒 disable extra product actions
        document
            .querySelectorAll("#extraProductList button")
            .forEach(btn => {
                btn.disabled = true;
                btn.classList.add("opacity-40", "pointer-events-none");
            });

        // ❗ Only close modals on HARD lock
        if (isHardLocked) {
            closeAllServiceModals();
            closePaymentModal();
        }

    } else {

        // 🔓 fully editable
        unlockTransactionEditing();
        unlockPaymentMethodUI();
        unlockPaymentUI();

        document
            .querySelectorAll("#extraProductList button")
            .forEach(btn => {
                btn.disabled = false;
                btn.classList.remove("opacity-40", "pointer-events-none");
            });
    }
}


function lockPaymentMethodUI() {
    document
        .querySelectorAll(".payment-method-btn")
        .forEach(btn => {
            btn.disabled = true;
            btn.classList.add("opacity-50", "cursor-not-allowed");
        });
}

function unlockPaymentMethodUI() {
    if (CashierState.transactionLocked) return; // 🔒 safety
    document
        .querySelectorAll(".payment-method-btn")
        .forEach(btn => {
            btn.disabled = false;
            btn.classList.remove("opacity-50", "cursor-not-allowed");
        });
}


function closeAllServiceModals() {
    [
        "serviceModal",
        "removeServiceModal",
        "paymentModal"
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add("hidden");
    });

    editingAppointmentServiceId = null;
    pendingRemoveAppointmentServiceId = null;
}

function unlockTransactionEditing() {
    ["addServiceBtn", "addExtraProductBtn"].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.disabled = false;
        el.classList.remove("opacity-50");
    });

    document
        .querySelectorAll("[data-remove-service], [data-remove-product]")
        .forEach(btn =>
            btn.classList.remove("pointer-events-none", "opacity-40")
        );
}
function togglePaymentExtraFields() {
    const method = CashierState.selectedPaymentMethod || "cash";
    const onlineBox = document.getElementById("onlinePaymentFields");

    if (method === "cash") {
        onlineBox.classList.add("hidden");
    } else {
        onlineBox.classList.remove("hidden");
    }
}

function closePaymentModal() {
    const modal = document.getElementById("paymentModal");
    if (!modal) return;

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function lockPOSContainer() {
    const pos = document.getElementById("posContainer");
    if (!pos) return;
    pos.classList.add("opacity-50", "pointer-events-none");
}

function unlockPOSContainer() {
    const pos = document.getElementById("posContainer");
    if (!pos) return;
    pos.classList.remove("opacity-50", "pointer-events-none");
}
