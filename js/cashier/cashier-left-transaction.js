// ================================
// CASHIER — TRANSACTIONS
// Reacts to appointment events
// ================================
let lockCountdownTimer = null;
// Listen when appointment is checked in
document.addEventListener("appointment:checkedIn", (e) => {
    const { appointmentId, financial } = e.detail;

    CashierState.activeAppointmentId = appointmentId;
    CashierState.activeTransactionId = financial.transaction_id;
    CashierState.lockCountdownFinished = false;

    loadTransaction(financial.transaction_id);
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
    CashierState.lockCountdownFinished = false;

    const btn = document.getElementById("confirmLockBtn");
    if (btn) {
        btn.disabled = true;
        btn.classList.add("opacity-50");
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

    // 🔥 STOP COUNTDOWN
    if (lockCountdownTimer) {
        clearInterval(lockCountdownTimer);
        lockCountdownTimer = null;
    }

    CashierState.lockCountdownFinished = false;
});



document.getElementById("confirmLockBtn")
    .addEventListener("click", async () => {
        CashierState.lockCountdownFinished = false;

        if (!CashierState.selectedPaymentMethod) {
            showToast("Please select a payment method", "error");
            return;
        }
        const res = await fetch("../php/cashier/left/lock-transaction.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                transaction_id: CashierState.activeTransactionId,
                payment_method: CashierState.selectedPaymentMethod
            })
        });

        const d = await res.json();
        if (!d.success) {
            showToast(d.error || "Failed to lock transaction", "error");
            return;
        }

        CashierState.transactionLocked = true; // 🔒 SINGLE SOURCE

        document.getElementById("lockTransactionModal").classList.add("hidden");

        lockTransactionEditing("Transaction locked — proceed to payment");
        lockPaymentMethodUI(); // 👈 NEW
        openPaymentModal();
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

            applyTransactionLockState(d.transaction);

            if (CashierState.transactionLocked && d.transaction.payment_method) {
                CashierState.selectedPaymentMethod = d.transaction.payment_method;
            } else {
                CashierState.selectedPaymentMethod = null;
            }

            renderClientInfo(d.transaction);
            renderServiceList(d.services);
            renderPaymentBreakdown(d);

            loadPaymentMethodSummary?.(d.transaction.transaction_id);

            loadAppointmentServices?.();
            loadExtraProducts?.();

            if (window.setActiveContext) {
                setActiveContext(`Currently working on: ${d.transaction.full_name}`);
            }
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
    // Client info
    document.getElementById("lockClientName").textContent =
        data.transaction.full_name;

    document.getElementById("lockTransactionNumber").textContent =
        data.transaction.transaction_number;

    const serviceBox = document.getElementById("lockServiceSummary");
    const productBox = document.getElementById("lockProductSummary");

    serviceBox.innerHTML = "";
    productBox.innerHTML = "";

    // ======================
    // SERVICES (detailed)
    // ======================
    if (data.services.length) {
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
            `<div class="italic text-gray-400">No services</div>`;
    }

    // ======================
    // EXTRA PRODUCTS
    // ======================
    if (data.products.length) {
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
            `<div class="italic text-gray-400">No extra products</div>`;
    }

    // ======================
    // PAYMENT TOTALS
    // ======================
    document.getElementById("lockServicesTotal").textContent =
        `₱${Number(data.totals.services_total).toFixed(2)}`;

    document.getElementById("lockConsumablesTotal").textContent =
        `₱${Number(data.totals.consumables_total).toFixed(2)}`;

    document.getElementById("lockExtraProductsTotal").textContent =
        `₱${Number(data.totals.extra_products_total).toFixed(2)}`;

    document.getElementById("lockSubtotal").textContent =
        `₱${Number(data.totals.subtotal).toFixed(2)}`;

    document.getElementById("lockVatRate").textContent =
        Number(data.totals.vat_rate).toFixed(2);

    document.getElementById("lockVatAmount").textContent =
        `₱${Number(data.totals.vat_amount).toFixed(2)}`;

    document.getElementById("lockGrandTotal").textContent =
        `₱${Number(data.totals.grand_total).toFixed(2)}`;
    // ======================
    // PAYMENT METHOD (READ-ONLY)
    // ======================
    loadPaymentMethodSummary(data.transaction.transaction_id);
    // ======================
    // PAYMENT METHOD (SELECTED)
    // ======================
    const methodLabel = document.getElementById("lockPaymentMethodLabel");

    if (methodLabel) {
        if (CashierState.selectedPaymentMethod) {
            methodLabel.textContent =
                formatPaymentMethod(CashierState.selectedPaymentMethod);
            methodLabel.classList.remove("text-red-500");
        } else {
            methodLabel.textContent = "Not selected";
            methodLabel.classList.add("text-red-500");
        }
    }


}

function startLockCountdown(seconds = 5) {
    const btn = document.getElementById("confirmLockBtn");
    const label = document.getElementById("lockCountdown");

    // 🔥 CLEAR OLD TIMER FIRST
    if (lockCountdownTimer) {
        clearInterval(lockCountdownTimer);
        lockCountdownTimer = null;
    }

    let remaining = seconds;
    CashierState.lockCountdownFinished = false;

    btn.disabled = true;
    btn.classList.add("opacity-50");

    label.textContent = `Lock available in ${remaining}s`;
    label.className = "text-center text-xs text-gray-400";

    lockCountdownTimer = setInterval(() => {
        remaining--;
        label.textContent = `Lock available in ${remaining}s`;

        if (remaining <= 0) {
            clearInterval(lockCountdownTimer);
            lockCountdownTimer = null;

            CashierState.lockCountdownFinished = true;

            if (CashierState.selectedPaymentMethod) {
                btn.disabled = false;
                btn.classList.remove("opacity-50");
                label.textContent = "You may now proceed";
                label.className = "text-center text-xs text-green-600";
            } else {
                btn.disabled = true;
                btn.classList.add("opacity-50");
                label.textContent = "Select a payment method to continue";
                label.className =
                    "text-center text-sm font-medium text-red-500 animate-pulse";
            }
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
