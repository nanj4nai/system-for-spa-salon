// ================================
// CASHIER — APPOINTMENTS & WALK-INS
// ================================

const appointmentsList = document.getElementById("appointmentsList");

// Walk-in modal
const walkinModal = document.getElementById("walkinModal");
const walkinBtn = document.getElementById("walkinBtn");
const cancelWalkin = document.getElementById("cancelWalkin");
const confirmWalkin = document.getElementById("confirmWalkin");
const searchInput = document.getElementById("walkinSearch");
const clientResults = document.getElementById("clientResults");
const walkinConfirmModal = document.getElementById("walkinConfirmModal");
const confirmWalkinConfirm = document.getElementById("confirmWalkinConfirm");
const cancelWalkinConfirm = document.getElementById("cancelWalkinConfirm");

let searchTimer = null;

// Check-in modal
const checkinModal = document.getElementById("checkinModal");
const confirmCheckinBtn = document.getElementById("confirmCheckinBtn");
const cancelCheckinBtn = document.getElementById("cancelCheckinBtn");

// Status modal (no-show / cancel)
const statusModal = document.getElementById("statusModal");
const statusClientName = document.getElementById("statusClientName");
const statusActionLabel = document.getElementById("statusActionLabel");
const statusModalTitle = document.getElementById("statusModalTitle");
const confirmStatusBtn = document.getElementById("confirmStatusBtn");
const cancelStatusBtn = document.getElementById("cancelStatusBtn");

/* =====================
   WALK-IN
===================== */
walkinBtn.addEventListener("click", () => {
    walkinModal.classList.remove("hidden");
    walkinModal.classList.add("flex");
    searchInput.value = "";
    clientResults.innerHTML = "";
});

cancelWalkin.addEventListener("click", () => {
    walkinModal.classList.add("hidden");
    clientResults.innerHTML = "";
});

confirmWalkin.addEventListener("click", () => {
    const name = document.getElementById("walkinName").value.trim();

    if (!name) {
        showToast("Client name is required", "error");
        return;
    }

    // Open safety confirmation
    walkinConfirmModal.classList.remove("hidden");
    walkinConfirmModal.classList.add("flex");
});

cancelWalkinConfirm.addEventListener("click", () => {
    walkinConfirmModal.classList.add("hidden");
    document.getElementById("walkinName").value = "";
    document.getElementById("walkinContact").value = "";
    document.getElementById("walkinClientId").value = "";
});

confirmWalkinConfirm.addEventListener("click", () => {
    const name = document.getElementById("walkinName").value.trim();
    const contact = document.getElementById("walkinContact").value.trim();
    const clientId = document.getElementById("walkinClientId").value;

    confirmWalkinConfirm.disabled = true;

    fetch("../php/cashier/left/create-walkin.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:
            `client_id=${encodeURIComponent(clientId)}` +
            `&name=${encodeURIComponent(name)}` +
            `&contact=${encodeURIComponent(contact)}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            walkinModal.classList.add("hidden");
            walkinConfirmModal.classList.add("hidden");

            showToast("Walk-in created and checked in");

            CashierState.activeAppointmentId = d.appointment_id;
            CashierState.activeTransactionId = d.transaction_id;

            loadTodayAppointments();
        })
        .finally(() => {
            confirmWalkinConfirm.disabled = false;
        });
});

/* =====================
   WALK-IN CLIENT SEARCH
===================== */
searchInput.addEventListener("input", () => {
    const q = searchInput.value.trim();

    clearTimeout(searchTimer);
    clientResults.innerHTML = "";

    if (q.length < 2) return;

    searchTimer = setTimeout(() => {
        fetch(`../php/cashier/left/search-clients.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(d => {
                if (!d.success || !d.clients.length) {
                    clientResults.innerHTML =
                        `<div class="text-xs text-gray-400 italic">No clients found</div>`;
                    return;
                }

                clientResults.innerHTML = d.clients.map(c => `
                    <div
                        class="p-2 rounded border bg-white dark:bg-gray-700
                               hover:bg-gray-100 dark:hover:bg-gray-600
                               cursor-pointer text-sm"
                        data-client-id="${c.id}"
                        data-name="${c.full_name}"
                        data-contact="${c.contact_number || ''}"
                    >
                        <div class="font-medium">${c.full_name}</div>
                        <div class="text-xs text-gray-500">
                            ${c.contact_number || "No contact"}
                        </div>
                    </div>
                `).join("");
            });
    }, 300);
});
clientResults.addEventListener("click", (e) => {
    const item = e.target.closest("[data-client-id]");
    if (!item) return;

    document.getElementById("walkinClientId").value = item.dataset.clientId;
    document.getElementById("walkinName").value = item.dataset.name;
    document.getElementById("walkinContact").value = item.dataset.contact;

    clientResults.innerHTML = "";
    searchInput.value = item.dataset.name;

    showToast("Client selected", "success");
});




/* =====================
   APPOINTMENTS LIST
===================== */
function loadTodayAppointments() {
    fetch("../php/cashier/left/get-todays-appointments.php")
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                appointmentsList.innerHTML =
                    `<div class="text-xs text-red-500">Failed to load appointments</div>`;
                return;
            }

            if (!d.appointments.length) {
                appointmentsList.innerHTML =
                    `<div class="text-sm text-gray-500 text-center">No appointments today</div>`;
                return;
            }

            appointmentsList.innerHTML = d.appointments.map(a => `
                <div
                    class="p-3 rounded-xl bg-white dark:bg-gray-700
                        border border-gray-200 dark:border-gray-600
                        cursor-pointer hover:shadow-md transition"
                    data-appointment-id="${a.id}"
                    data-status="${a.status}"
                    data-client-name="${a.client_name}"
                >

                <div class="flex justify-between items-center gap-2">
                    <div class="font-medium">${a.client_name}</div>

                    <div class="flex gap-1 items-center">
                        ${renderTransactionBadge(a)}
                        ${renderSourceBadge(a.source)}
                    </div>
                </div>

                    <div class="text-xs text-gray-500 mt-0.5">
                        ${a.start_time}
                    </div>

                    <div class="text-xs text-gray-500">
                        ${a.service_count > 0
                    ? a.services
                    : "<span class='italic text-gray-400'>No services selected</span>"
                }
                    </div>

                    ${a.payment_status ? `
                        <div class="text-[11px] mt-1 ${a.payment_status === "paid" ? "text-green-600" :
                        a.payment_status === "partial" ? "text-yellow-600" :
                            "text-gray-400"
                    }">
                            Payment: ${a.payment_status}
                        </div>
                    ` : ""}

                    <div class="text-xs mt-1 ${a.status === "confirmed"
                    ? "text-green-600"
                    : a.status === "checked_in"
                        ? "text-blue-500"
                        : "text-gray-400"
                }">
                        ${a.status.replace("_", " ")}
                    </div>

                    ${a.status === "confirmed" ? `
                        <div class="flex gap-2 mt-2">
                            <button data-action="checkin"
                                class="text-xs px-2 py-1 rounded bg-green-500 text-white">
                                Check-in
                            </button>
                            <button data-action="no_show"
                                class="text-xs px-2 py-1 rounded bg-yellow-500 text-white">
                                No-show
                            </button>
                            <button data-action="cancelled"
                                class="text-xs px-2 py-1 rounded bg-red-500 text-white">
                                Cancel
                            </button>
                        </div>
                    ` : ""}
                </div>
            `).join("");

        });
}

function renderSourceBadge(source) {
    if (source === "online") {
        return `<span class="text-[10px] px-2 py-0.5 rounded bg-blue-100 text-blue-700">Online</span>`;
    }
    if (source === "admin") {
        return `<span class="text-[10px] px-2 py-0.5 rounded bg-purple-100 text-purple-700">Admin</span>`;
    }
    return `<span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 text-gray-700">Walk-in</span>`;
}

function renderTransactionBadge(a) {
    if (a.status !== "checked_in") return "";

    if (a.is_receivable == 1) {
        return `
            <span class="text-[10px] px-2 py-0.5 rounded bg-purple-100 text-purple-700">
                RECEIVABLE
            </span>
        `;
    }

    if (a.payment_status === "paid") {
        return `
            <span class="text-[10px] px-2 py-0.5 rounded bg-green-100 text-green-700">
                PAID
            </span>
        `;
    }

    if (a.payment_status === "partial") {
        return `
            <span class="text-[10px] px-2 py-0.5 rounded bg-amber-100 text-amber-700">
                PARTIAL
            </span>
        `;
    }

    return `
        <span class="text-[10px] px-2 py-0.5 rounded bg-blue-100 text-blue-700">
            OPEN
        </span>
    `;
}

/* =====================
   APPOINTMENT CLICK HANDLING
===================== */
appointmentsList.addEventListener("click", (e) => {

    // ACTION BUTTONS
    const actionBtn = e.target.closest("button[data-action]");
    if (actionBtn) {
        e.stopPropagation();

        const card = actionBtn.closest("[data-appointment-id]");
        const action = actionBtn.dataset.action;

        // CHECK-IN is its own flow
        if (action === "checkin") {
            CashierState.pendingCheckinAppointment = card.dataset.appointmentId;
            document.getElementById("checkinClientName").textContent =
                card.dataset.clientName;

            checkinModal.classList.remove("hidden");
            checkinModal.classList.add("flex");
            return;
        }

        // CANCEL / NO-SHOW flow
        CashierState.pendingStatus = {
            appointmentId: card.dataset.appointmentId,
            action,
            refundType: "none",
            refundAmount: 0
        };

        statusClientName.textContent = card.dataset.clientName;

        fetch(`../php/cashier/left/appointment-financial-status.php?appointment_id=${card.dataset.appointmentId}`)
            .then(r => r.json())
            .then(fin => {
                CashierState.pendingFinancial = fin;
                openStatusModal(fin);
            });

        statusActionLabel.textContent = action.replace("_", " ");
        statusModalTitle.textContent =
            action === "no_show" ? "Confirm No-Show" : "Confirm Cancellation";

        return;
    }

    // CARD CLICK
    const card = e.target.closest("[data-appointment-id]");
    if (!card) return;

    if (card.dataset.status === "checked_in") {

        const appointmentId = card.dataset.appointmentId;

        // Ask backend first — NEVER GUESS
        fetch(`../php/cashier/left/appointment-financial-status.php?appointment_id=${appointmentId}`)
            .then(r => r.json())
            .then(fin => {
                if (!fin.success) {
                    showToast("Failed to load financial status", "error");
                    return;
                }

                // 🔵 NO TRANSACTION YET → WALK-IN / ADMIN FLOW
                if (!fin.has_transaction) {
                    createTransactionFromAppointment(appointmentId);
                    highlightActiveAppointment(appointmentId);
                    return;
                }

                // 🟢 TRANSACTION EXISTS → ONLINE OR EXISTING FLOW
                CashierState.activeAppointmentId = appointmentId;
                CashierState.activeTransactionId = fin.transaction_id;

                // 🔒 DO NOT RESET PAYMENT STATE
                CashierState.pendingPayment = false;
                CashierState.lockCountdownFinished = false;

                closeAllServiceModals?.();

                document.dispatchEvent(
                    new CustomEvent("appointment:checkedIn", {
                        detail: {
                            appointmentId,
                            financial: fin
                        }
                    })
                );

                highlightActiveAppointment(appointmentId);
            });

        return;
    }

    showToast("Please check-in the client first", "error");
});

function openStatusModal(fin) {
    // default refund choice
    CashierState.pendingStatus.refundType = "none";
    CashierState.pendingStatus.refundAmount = 0;

    // OPTIONAL: show payment info
    document.getElementById("refundInfo").textContent =
        fin.amount_paid > 0
            ? `Paid: ₱${fin.amount_paid}`
            : "No payment recorded";

    statusModal.classList.remove("hidden");
    statusModal.classList.add("flex");
}


/* =====================
   CHECK-IN CONFIRM
===================== */
confirmCheckinBtn.addEventListener("click", () => {
    confirmCheckinBtn.disabled = true;

    fetch("../php/cashier/left/check-in-appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `appointment_id=${CashierState.pendingCheckinAppointment}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            checkinModal.classList.add("hidden");
            showToast("Client checked in");

            fetch(`../php/cashier/left/appointment-financial-status.php?appointment_id=${CashierState.pendingCheckinAppointment}`)
                .then(r => r.json())
                .then(fin => {
                    if (!fin.success) {
                        showToast("Failed to check payment status", "error");
                        return;
                    }

                    document.dispatchEvent(
                        new CustomEvent("appointment:checkedIn", {
                            detail: {
                                appointmentId: CashierState.pendingCheckinAppointment,
                                financial: fin
                            }
                        })
                    );
                });
            loadTodayAppointments();
            CashierState.pendingCheckinAppointment = null;
        })
        .finally(() => {
            confirmCheckinBtn.disabled = false;
        });
});

cancelCheckinBtn.addEventListener("click", () => {
    checkinModal.classList.add("hidden");
    CashierState.pendingCheckinAppointment = null;
});

/* =====================
   STATUS CONFIRM
===================== */
confirmStatusBtn.addEventListener("click", () => {
    if (!CashierState.pendingStatus) {
        showToast("No pending action", "error");
        return;
    }

    confirmStatusBtn.disabled = true;

    fetch("../php/cashier/left/refund-appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            appointment_id: CashierState.pendingStatus.appointmentId,
            status: CashierState.pendingStatus.action,
            refund_type: CashierState.pendingStatus.refundType,
            refund_amount: CashierState.pendingStatus.refundAmount
        })
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            showToast("Appointment resolved");
            loadTodayAppointments();
            statusModal.classList.add("hidden");
        })
        .finally(() => {
            confirmStatusBtn.disabled = false;
            CashierState.pendingStatus = null;
        });
});


cancelStatusBtn.addEventListener("click", () => {
    statusModal.classList.add("hidden");
    CashierState.pendingStatus = null;

});

// Export loader
window.loadTodayAppointments = loadTodayAppointments;


function highlightActiveAppointment(appointmentId) {
    document.querySelectorAll("[data-appointment-id]").forEach(card => {
        if (card.dataset.appointmentId === String(appointmentId)) {
            card.classList.add(
                "ring-2",
                "ring-blue-500",
                "bg-blue-50",
                "dark:bg-blue-900/30"
            );
        } else {
            card.classList.remove(
                "ring-2",
                "ring-blue-500",
                "bg-blue-50",
                "dark:bg-blue-900/30"
            );
        }
    });
}

function resetExtraProductsUI() {
    const box = document.getElementById("extraProductList");
    if (box) {
        box.innerHTML = `<div class="text-xs italic text-gray-400">
            No extra products added
        </div>`;
    }
}
