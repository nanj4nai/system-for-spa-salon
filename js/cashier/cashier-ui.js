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
