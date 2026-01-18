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

    // Lock existing service cards
    document.querySelectorAll(".service-card").forEach(card => {
        card.classList.add("opacity-50", "pointer-events-none");
    });

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
        card.classList.remove("opacity-50", "pointer-events-none");
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
}

function applyTransactionLockState(transaction) {
    const isLocked =
        transaction.status === "locked" ||
        transaction.payment_status === "paid";

    CashierState.transactionLocked = isLocked;

    if (isLocked) {
        // 🔒 hard lock everything
        lockTransactionEditing();
        lockPaymentMethodUI();
        lockPaymentUI();

        // prevent modals from staying open
        closeAllServiceModals();
        closePaymentModal();
    } else {
        // 🔓 editable
        unlockTransactionEditing();
        unlockPaymentMethodUI();
        unlockPaymentUI();
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

function closePaymentModal() {
    const modal = document.getElementById("paymentModal");
    if (!modal) return;

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}
