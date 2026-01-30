// ================================
// CASHIER — TRANSACTIONS
// Reacts to appointment events
// ================================
let lockCountdownTimer = null;
let lastLoadedTransactionId = null;
// Listen when appointment is checked in
document.addEventListener("appointment:checkedIn", (e) => {
    const { appointmentId, financial } = e.detail;

    CashierState.activeAppointmentId = appointmentId;
    CashierState.activeTransactionId = financial.transaction_id;

    CashierState.transactionLocked = false;
    CashierState.pendingPayment = false;
    CashierState.lockCountdownFinished = false;

    loadTransaction(financial.transaction_id);
});

// ================================
// LOCK TRANSACTION FOR PAYMENT
// ================================
document.getElementById("payBtn").addEventListener("click", async () => {

    if (!CashierState.activeTransactionId) return;

    const res = await fetch(
        `../php/cashier/left/get-transaction-details.php?transaction_id=${CashierState.activeTransactionId}`,
        { cache: "no-store" }
    );

    const d = await res.json();
    if (!d.success) {
        showToast("Failed to load transaction", "error");
        return;
    }

    const txn = d.transaction;

    if (txn.payment_status === "paid") {
        showToast("Transaction already fully paid", "error");
        return;
    }

    if (txn.status === "pending_verification") {
        showToast("Online payment pending verification", "error");
        return;
    }

    if (!canLockTransaction()) {
        showToast("Add at least one service or product before proceeding", "error");
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
    .addEventListener("click", () => {

        CashierState.lockCountdownFinished = false;

        if (!CashierState.selectedPaymentMethod) {
            showToast("Please select a payment method", "error");
            return;
        }

        // 🔒 SOFT LOCK ONLY (UI state)
        CashierState.pendingPayment = true;

        document.getElementById("lockTransactionModal").classList.add("hidden");

        lockTransactionEditing("Proceeding to payment");
        lockPaymentMethodUI();
        lockPaymentUI();

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
            // 🔥 EXIT "no shift / disabled" mode
            unlockPOSContainer();

            // 🔥 RESET UI FROM PREVIOUS TRANSACTION
            resetTransactionUIState();

            // 1️⃣ Render transaction data
            renderClientInfo(d.transaction);
            renderServiceList(d.services);
            renderTransactionBreakdown(d);
            renderPaymentBreakdown(d);

            loadAppointmentServices?.();
            loadExtraProducts?.();

            // 🔒 APPLY CURRENT STATE
            applyTransactionLockState(d.transaction);
            updateAddServiceButtonState();

            // 🔥 THIS WAS MISSING
            loadPaymentMethodSummary(transactionId);

            // ✅ SHOW TOAST ONLY WHEN:
            // - new transaction loaded
            // - transaction is NOT locked
            if (
                d.transaction.transaction_id !== lastLoadedTransactionId &&
                !CashierState.transactionLocked
            ) {
                showToast("Transaction ready — you may proceed", "success");
            }

            lastLoadedTransactionId = d.transaction.transaction_id;

            CashierState.selectedPaymentMethod = null;

            if (window.setActiveContext) {
                setActiveContext(
                    `Currently working on: ${d.transaction.full_name}`
                );
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

    if (!services?.length) {
        list.innerHTML = `
            <div class="text-sm text-gray-400">
                No services added yet
            </div>
        `;
        return;
    }

    list.innerHTML = services.map(s => {
        const isBookingService = s.is_booking_service === true;
        const locked = CashierState.transactionLocked;

        return `
            <div
                class="service-card p-3 rounded-lg shadow
                       bg-white dark:bg-gray-800 space-y-1"
                data-appointment-service-id="${s.appointment_service_id}"
            >

                <!-- HEADER -->
                <div class="flex justify-between items-start gap-3">
                    <div>
                        <div class="font-medium">${s.service_name}</div>

                        ${s.variant_name ? `
                            <div class="text-xs text-indigo-500">
                                Variant: ${s.variant_name}
                            </div>
                        ` : ""}

                        <div class="text-xs text-gray-500">
                            Staff: ${s.staff_name || "Unassigned"}
                        </div>

                        ${isBookingService ? `
                            <div class="text-[11px] text-blue-500 italic">
                                Online booking service
                            </div>
                        ` : ""}
                    </div>

                    <div class="text-sm font-semibold whitespace-nowrap">
                        ₱${Number(s.total_price).toFixed(2)}
                    </div>
                </div>

                <!-- PRODUCTS USED -->
                ${s.products_used?.length ? `
                    <div class="ml-3 mt-1 space-y-0.5 text-[11px] text-gray-500">
                        ${s.products_used.map(p => `
                            <div>
                                • ${p.name} (${p.quantity_used}${p.unit})
                            </div>
                        `).join("")}
                    </div>
                ` : `
                    <div class="ml-3 mt-1 text-[11px] italic text-gray-400">
                        No products used
                    </div>
                `}

                <!-- ACTIONS -->
                    ${!locked ? `
                        <div class="flex gap-2 pt-2">

                            ${isBookingService ? `
                                <!-- ONLINE SERVICE -->
                                <button data-mutation
                                    class="text-xs px-2 py-1 rounded
                                        bg-emerald-500 text-white"
                                    data-action="edit-usage"
                                    data-id="${s.appointment_service_id}"
                                >
                                    Edit product usage
                                </button>

                                <button data-mutation
                                    class="text-xs px-2 py-1 rounded
                                        bg-gray-200 text-gray-600 cursor-default"
                                    disabled
                                    title="Online booking services cannot be removed"
                                >
                                    Locked
                                </button>
                            ` : `
                                <!-- CASHIER-ADDED SERVICE -->
                                <button
                                    data-mutation
                                    class="text-xs px-2 py-1 rounded
                                        bg-gray-200 text-gray-800
                                        hover:bg-gray-300
                                        dark:bg-gray-700 dark:text-gray-200
                                        dark:hover:bg-gray-600
                                        transition"
                                    data-action="edit-service"
                                    data-id="${s.appointment_service_id}"
                                >
                                    Edit
                                </button>


                                <button data-mutation
                                    class="text-xs px-2 py-1 rounded bg-red-500 text-white"
                                    data-action="remove-service"
                                    data-id="${s.appointment_service_id}"
                                >
                                    Remove
                                </button>
                            `}
                        </div>
                    ` : ""}

            </div>
        `;
    }).join("");

    if (CashierState.transactionLocked) {
        list.querySelectorAll(".service-card").forEach(card => {
            card.classList.add("opacity-50", "pointer-events-none");
        });
    }
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

function renderTransactionBreakdown(data) {
    const serviceBox = document.getElementById("serviceBreakdown");
    const productBox = document.getElementById("productBreakdown");
    const vatBtn = document.getElementById("toggleVatBtn");
    if (!serviceBox || !productBox) return;

    serviceBox.innerHTML = "";
    productBox.innerHTML = "";

    let servicesTotal = 0;

    // SERVICES + CONSUMABLES (NO PRICES)
    if (data.services?.length) {
        data.services.forEach(s => {
            const price = Number(s.total_price) || 0;
            servicesTotal += price;

            serviceBox.innerHTML += `
            <div class="text-xs space-y-0.5">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-medium">${s.service_name}</div>

                        ${s.has_variant_price ? `
                            <div class="text-xs text-gray-500">
                                Variant price
                            </div>
                        ` : ""}
                    </div>

                    <div class="font-semibold">
                        ₱${price.toFixed(2)}
                    </div>
                </div>

                ${s.products_used?.length ? `
                    <div class="ml-3 mt-1 space-y-0.5 text-[11px] text-gray-500">
                        ${s.products_used.map(p => `
                            <div>
                                • ${p.name} (${p.quantity_used}${p.unit})
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


    // EXTRA PRODUCTS (still billable)
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

    // VAT toggle stays (for extra products)
    if (vatBtn) {
        const enabled = data.totals.include_vat == 1;
        vatBtn.dataset.enabled = enabled ? "1" : "0";
        vatBtn.textContent = enabled ? "ON" : "OFF";
    }

    // 🚫 DO NOT show service / consumable totals
    document.getElementById("servicesTotal").textContent =
        `₱${servicesTotal.toFixed(2)}`;

    // ✅ Extra products still count
    document.getElementById("extraProductsTotal").textContent =
        `₱${Number(data.totals.extra_products_total).toFixed(2)}`;

    document.getElementById("subtotalAmount").textContent =
        `₱${Number(data.totals.subtotal).toFixed(2)}`;

    document.getElementById("vatAmount").textContent =
        `₱${Number(data.totals.vat_amount).toFixed(2)}`;

    document.getElementById("transactionTotal").textContent =
        `₱${Number(data.totals.grand_total).toFixed(2)}`;
}

function renderPaymentBreakdown(d) {

    // ⛔ do not render during shift / locked UI
    if (
        document.getElementById('closeShiftModal')?.classList.contains('hidden') === false ||
        document.getElementById('noShiftOverlay')?.classList.contains('hidden') === false
    ) {
        return;
    }

    const txn = d.transaction;
    const isReceivable = txn.is_receivable == 1;
    // 🔒 HARD BUSINESS RULES
    const lockReason =
        txn.payment_status === "paid"
            ? "Transaction already fully paid"
            : txn.status === "pending_verification"
                ? "Awaiting online payment verification"
                : null;

    if (lockReason) {
        lockPaymentUI(lockReason);
    }

    const amountPaidEl = document.getElementById("amountPaidLabel");
    const balanceEl = document.getElementById("balanceLabel");
    const statusLabel = document.getElementById("paymentStatusLabel");
    const balanceText = document.getElementById("balanceTextLabel");
    const vatRateEl = document.getElementById("vatRateLabel");
    const vatAmountEl = document.getElementById("vatAmount");


    // ⛔ Hard guard
    if (!amountPaidEl || !balanceEl || !statusLabel) return;

    const total = Number(
        d.totals?.grand_total ??
        txn.total_amount ??
        0
    );

    const paid = Number(txn.amount_paid ?? 0);
    const balance = Number(
        txn.balance_due ??
        Math.max(0, total - paid)
    );

    CashierState.balanceDue = balance;
    const isOnlineBooking = paid > 0;

    amountPaidEl.textContent = `₱${paid.toFixed(2)}`;
    balanceEl.textContent = `₱${balance.toFixed(2)}`;

    // Reset styles first
    balanceEl.classList.remove(
        "text-red-600",
        "text-emerald-600",
        "text-2xl",
        "font-bold"
    );

    const totalEl = document.getElementById("transactionTotal");
    totalEl?.classList.remove(
        "text-emerald-600",
        "text-2xl",
        "font-bold"
    );

    if (isOnlineBooking && balance > 0) {
        // 🔴 ONLINE BOOKING → emphasize BALANCE
        balanceEl.classList.add("text-amber-600", "text-2xl", "font-bold");
    }
    else {
        // 🟢 WALK-IN → emphasize TOTAL
        totalEl?.classList.add("text-emerald-600", "text-2xl", "font-bold");
    }

    // ======================
    // BALANCE LABEL
    // ======================
    if (balanceText) {
        if (balance <= 0) {
            balanceText.textContent = "Paid in Full";
        }
        else if (isReceivable) {
            balanceText.textContent = "Receivable Balance";
        }
        else if (isOnlineBooking) {
            balanceText.textContent = "Balance to Pay";
        }
        else {
            balanceText.textContent = "Total to Collect";
        }
    }

    const helper = document.getElementById("balanceHelper");
    if (helper) {
        if (isOnlineBooking && balance > 0) {
            helper.textContent = "Client already paid online";
        }
        else {
            helper.textContent = "";
        }
    }

    const payBtn = document.getElementById("payBtn");
    if (payBtn) {
        if (balance <= 0) {
            payBtn.disabled = true;
            payBtn.classList.add("opacity-50");
        } else {
            payBtn.disabled = false;
            payBtn.classList.remove("opacity-50");
        }
    }
    
    if (vatRateEl) {
        vatRateEl.textContent =
            Number(d.totals?.vat_rate ?? 0).toFixed(0);
    }

    if (vatAmountEl) {
        vatAmountEl.textContent =
            `₱${Number(d.totals?.vat_amount ?? 0).toFixed(2)}`;
    }


    // ======================
    // AMOUNTS
    // ======================
    amountPaidEl.textContent = `₱${paid.toFixed(2)}`;
    balanceEl.textContent = `₱${balance.toFixed(2)}`;

    const changeEl = document.getElementById("changeLabel");
    if (changeEl) changeEl.textContent = "₱0.00";

    // ======================
    // BALANCE LABEL
    // ======================
    if (balanceText) {
        if (isReceivable) {
            balanceText.textContent = "Receivable Balance";
        }
        else if (balance > 0 && paid > 0) {
            balanceText.textContent = "Remaining Balance";
        }
        else {
            balanceText.textContent = "Balance";
        }
    }


    // ======================
    // STATUS
    // ======================
    statusLabel.className = "font-semibold";
    statusLabel.classList.remove(
        "text-purple-600",
        "text-emerald-600",
        "text-amber-500",
        "text-gray-400"
    );

    if (isReceivable) {
        statusLabel.textContent = "RECEIVABLE";
        statusLabel.classList.add("text-purple-600");
    }
    else if (txn.payment_status === "paid") {
        statusLabel.textContent = "PAID";
        statusLabel.classList.add("text-emerald-600");
    }
    else if (txn.payment_status === "partial") {
        statusLabel.textContent = "PARTIAL";
        statusLabel.classList.add("text-amber-500");
    }
    else {
        statusLabel.textContent = "UNPAID";
        statusLabel.classList.add("text-gray-400");
    }

    // ======================
    // RECEIPTS
    // ======================
    renderPaymentReceipts(d.payments || []);

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
                <div class="text-xs space-y-0.5">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium">${s.service_name}</div>

                            ${s.has_variant_price ? `
                                <div class="text-xs text-gray-500">
                                    Variant price
                                </div>
                            ` : ""}
                        </div>

                        <div class="font-semibold">
                            ₱${Number(s.total_price).toFixed(2)}
                        </div>
                    </div>

                    ${s.products_used?.length ? `
                        <div class="ml-3 mt-1 space-y-0.5 text-[11px] text-gray-500">
                            ${s.products_used.map(p => `
                                <div>
                                    • ${p.name} (${p.quantity_used}${p.unit})
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

    document.getElementById("lockPaidTotal").textContent =
        `₱${Number(data.transaction.amount_paid).toFixed(2)}`;

    document.getElementById("lockBalanceDue").textContent =
        `₱${Number(data.transaction.balance_due).toFixed(2)}`;
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
function refreshAppointmentsRealtime() {
    // Preserve highlight
    const activeId = CashierState.activeAppointmentId;

    loadTodayAppointments();

    // Re-apply highlight after render
    setTimeout(() => {
        if (activeId) {
            highlightActiveAppointment(activeId);
        }
    }, 100);
}

document.getElementById("serviceList").addEventListener("click", e => {
    const btn = e.target.closest("[data-action]");
    if (!btn) return;

    if (CashierState.transactionLocked) {
        showToast("Transaction is locked", "error");
        return;
    }

    const id = btn.dataset.id;

    if (btn.dataset.action === "edit-usage") {
        openEditService(id);
    }

    if (btn.dataset.action === "edit-service") {
        openEditService(id);
    }

    if (btn.dataset.action === "remove-service") {
        removeService(id);
    }
});
