// ================================
// CASHIER — TRANSACTIONS
// Reacts to appointment events
// ================================

// Listen when appointment is checked in
document.addEventListener("appointment:checkedIn", (e) => {
    const { appointmentId, financial } = e.detail;

    CashierState.activeAppointmentId = appointmentId;
    CashierState.activeTransactionId = financial.transaction_id;

    loadTransaction(financial.transaction_id);

    // 🔒 LOCKED TRANSACTION
    if (financial.status === "locked") {
        lockCenterUI("Transaction locked — ready for payment");
        unlockPaymentUI();
        return;
    }

    // 💰 PAID
    if (financial.payment_status === "paid") {
        lockCenterUI("Already paid");
        lockPaymentUI("Fully paid");
        return;
    }

    // ✏️ DEFAULT: editable
    unlockCenterUI();
    unlockPaymentUI();
});

// ================================
// LOCK TRANSACTION FOR PAYMENT
// ================================
document.getElementById("payBtn").addEventListener("click", async () => {
    if (!CashierState.activeTransactionId) return;

    if (!canLockTransaction()) {
        showToast("Add at least one service or product before proceeding", "error");
        return;
    }

    const res = await fetch(
        `../php/cashier/left/get-transaction-details.php?transaction_id=${CashierState.activeTransactionId}`,
        { cache: "no-store" }
    );

    const d = await res.json();
    if (!d.success) {
        showToast("Failed to load transaction summary", "error");
        return;
    }

    populateLockSummary(d);
    startLockCountdown(5);

    const modal = document.getElementById("lockTransactionModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
});


document.getElementById("cancelLockBtn").addEventListener("click", () => {
    const modal = document.getElementById("lockTransactionModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
});

document.getElementById("confirmLockBtn").addEventListener("click", async () => {
    const res = await fetch("../php/cashier/left/lock-transaction.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            transaction_id: CashierState.activeTransactionId
        })
    });

    const d = await res.json();
    if (!d.success) {
        showToast(d.error || "Failed to lock transaction", "error");
        return;
    }

    document.getElementById("lockTransactionModal").classList.add("hidden");

    lockCenterUI("Ready for payment");
    unlockPaymentUI();
});



// ================================
// CREATE TRANSACTION
// ================================
function createTransactionFromAppointment(appointmentId) {
    fetch("../php/cashier/left/get-or-create-transaction.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `appointment_id=${appointmentId}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error || "Failed to create transaction", "error");
                return;
            }

            CashierState.activeAppointmentId = appointmentId;
            CashierState.activeTransactionId = d.transaction_id;

            loadTransaction(d.transaction_id);
            unlockPaymentUI();

            showToast("Transaction started");
        });
}

// ================================
// LOAD TRANSACTION
// ================================
function loadTransaction(transactionId) {
    fetch(
        `../php/cashier/left/get-transaction-details.php?transaction_id=${transactionId}`,
        { cache: "no-store" }
    )
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            renderClientInfo(d.transaction);
            renderServiceList(d.services);
            renderPaymentBreakdown(d);

            enableServiceActions();

            if (window.setActiveContext) {
                setActiveContext(`Currently working on: ${d.transaction.full_name}`);
            }

            loadAppointmentServices?.();
            loadExtraProducts?.();
        });
}


// ================================
// UI HELPERS
// ================================
function renderClientInfo(txn) {
    document.getElementById("clientInfo").innerHTML = `
        <div class="font-medium">${txn.full_name}</div>
        <div>${txn.contact_number || "No contact"}</div>
        <div class="text-xs text-gray-400 mt-1">
            ${txn.transaction_number}
        </div>
    `;
}

function renderServiceList(services) {
    const list = document.getElementById("serviceList");

    if (!services.length) {
        list.innerHTML = `
            <div class="text-sm text-gray-400">
                No services added yet
            </div>
        `;
        return;
    }

    list.innerHTML = services.map(s => `
        <div class="p-3 bg-white dark:bg-gray-800 rounded-lg shadow flex justify-between">
            <div>
                <div class="font-medium">${s.service_name}</div>
                <div class="text-xs text-gray-400">Qty: ${s.quantity}</div>
            </div>
            <div class="font-semibold">₱${s.total_price}</div>
        </div>
    `).join("");
}


// ================================
// PAYMENT UI CONTROL
// ================================
function lockPaymentUI(reason = "") {
    const payBtn = document.getElementById("payBtn");
    payBtn.disabled = true;
    payBtn.classList.add("opacity-50");

    if (reason) {
        showToast(reason);
    }
}

function unlockPaymentUI() {
    const payBtn = document.getElementById("payBtn");
    payBtn.disabled = false;
    payBtn.classList.remove("opacity-50");
}

function enableServiceActions() {
    const addServiceBtn = document.getElementById("addServiceBtn");
    addServiceBtn.disabled = false;
    addServiceBtn.classList.remove("opacity-50");
}

// ================================
// OPTIONAL: Click appointment card
// ================================
document.addEventListener("appointment:loadTransaction", async (e) => {
    const { appointmentId } = e.detail;

    // clear old UI immediately
    resetExtraProductsUI();

    const res = await fetch(
        "../php/cashier/left/appointment-financial-status.php?appointment_id=" + appointmentId
    );
    const fin = await res.json();

    if (!fin.success) {
        showToast("Failed to load transaction", "error");
        return;
    }

    document.dispatchEvent(
        new CustomEvent("appointment:checkedIn", {
            detail: { appointmentId, financial: fin }
        })
    );
});

function resetExtraProductsUI() {
    const box = document.getElementById("extraProductList");
    if (box) {
        box.innerHTML = `<div class="text-xs italic text-gray-400">
            No extra products added
        </div>`;
    }
}

function renderPaymentBreakdown(data) {
    const serviceBox = document.getElementById("serviceBreakdown");
    const productBox = document.getElementById("productBreakdown");
    const vatBtn = document.getElementById("toggleVatBtn");

    serviceBox.innerHTML = "";
    productBox.innerHTML = "";

    // ======================
    // SERVICES
    // ======================
    if (data.services?.length) {
        data.services.forEach(s => {
            serviceBox.innerHTML += `
                <div class="text-xs">
                    <div class="flex justify-between font-medium">
                        <span>${s.service_name}</span>
                        <span>₱${Number(s.total_price).toFixed(2)}</span>
                    </div>

                    ${s.products_used?.length ? `
                        <div class="ml-3 mt-1 space-y-0.5 text-[11px] text-gray-500">
                            ${s.products_used.map(p => `
                                <div class="flex justify-between">
                                    <span>• ${p.name} (${p.quantity_used}${p.unit})</span>
                                    <span>₱${Number(p.total_price).toFixed(2)}</span>
                                </div>
                            `).join("")}
                        </div>
                    ` : ""}
                </div>
            `;
        });
    } else {
        serviceBox.innerHTML =
            `<div class="text-xs italic text-gray-400">No services</div>`;
    }

    // ======================
    // EXTRA PRODUCTS
    // ======================
    if (data.products?.length) {
        data.products.forEach(p => {
            productBox.innerHTML += `
                <div class="flex justify-between text-xs">
                    <span>${p.name} × ${p.quantity}${p.unit}</span>
                    <span>₱${Number(p.total_price).toFixed(2)}</span>
                </div>
            `;
        });
    } else {
        productBox.innerHTML =
            `<div class="text-xs italic text-gray-400">No extra products</div>`;
    }

    if (vatBtn) {
        const enabled = data.totals.include_vat == 1;
        vatBtn.dataset.enabled = enabled ? "1" : "0";
        vatBtn.textContent = enabled ? "ON" : "OFF";
    }

    // ======================
    // TOTALS
    // ======================
    document.getElementById("servicesTotal").textContent =
        `₱${Number(data.totals.services_total).toFixed(2)}`;

    document.getElementById("consumablesTotal").textContent =
        `₱${Number(data.totals.consumables_total).toFixed(2)}`;

    document.getElementById("extraProductsTotal").textContent =
        `₱${Number(data.totals.extra_products_total).toFixed(2)}`;

    document.getElementById("subtotalAmount").textContent =
        `₱${Number(data.totals.subtotal).toFixed(2)}`;

    document.getElementById("vatRateLabel").textContent =
        Number(data.totals.vat_rate).toFixed(2);

    document.getElementById("vatAmount").textContent =
        `₱${Number(data.totals.vat_amount).toFixed(2)}`;

    document.getElementById("transactionTotal").textContent =
        `₱${Number(data.totals.grand_total).toFixed(2)}`;
}

function populateLockSummary(data) {
    document.getElementById("lockClientName").textContent =
        data.transaction.full_name;

    document.getElementById("lockTransactionNumber").textContent =
        data.transaction.transaction_number;

    const serviceBox = document.getElementById("lockServiceSummary");
    const productBox = document.getElementById("lockProductSummary");

    serviceBox.innerHTML = "";
    productBox.innerHTML = "";

    if (data.services.length) {
        data.services.forEach(s => {
            serviceBox.innerHTML += `
                <div class="flex justify-between">
                    <span>${s.service_name}</span>
                    <span>₱${Number(s.total_price).toFixed(2)}</span>
                </div>
            `;
        });
    } else {
        serviceBox.innerHTML = `<div class="italic text-gray-400">None</div>`;
    }

    if (data.products.length) {
        data.products.forEach(p => {
            productBox.innerHTML += `
                <div class="flex justify-between">
                    <span>${p.name} × ${p.quantity}${p.unit}</span>
                    <span>₱${Number(p.total_price).toFixed(2)}</span>
                </div>
            `;
        });
    } else {
        productBox.innerHTML = `<div class="italic text-gray-400">None</div>`;
    }

    document.getElementById("lockTotalAmount").textContent =
        `₱${Number(data.totals.grand_total).toFixed(2)}`;
}

function startLockCountdown(seconds = 5) {
    const btn = document.getElementById("confirmLockBtn");
    const label = document.getElementById("lockCountdown");

    let remaining = seconds;
    btn.disabled = true;
    btn.classList.add("opacity-50");

    label.textContent = `Lock available in ${remaining}s`;

    const timer = setInterval(() => {
        remaining--;
        label.textContent = `Lock available in ${remaining}s`;

        if (remaining <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            btn.classList.remove("opacity-50");
            label.textContent = "You may now proceed";
        }
    }, 1000);
}

document.getElementById("toggleVatBtn").addEventListener("click", async () => {
    if (!CashierState.activeTransactionId) return;

    const btn = document.getElementById("toggleVatBtn");
    const includeVat = btn.dataset.enabled !== "1";

    const res = await fetch("../php/cashier/left/toggle-vat.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            transaction_id: CashierState.activeTransactionId,
            include_vat: includeVat ? 1 : 0
        })
    });

    const d = await res.json();
    if (!d.success) {
        showToast(d.error || "Failed to update VAT", "error");
        return;
    }

    btn.dataset.enabled = includeVat ? "1" : "0";
    btn.textContent = includeVat ? "ON" : "OFF";

    // reload totals
    loadTransaction(CashierState.activeTransactionId);
});
