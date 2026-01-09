const overlay = document.getElementById('noShiftOverlay');
const pos = document.getElementById('posContainer');
const badge = document.getElementById('shiftBadge');
const closeBtn = document.getElementById('closeShiftBtn');
const openBtn = document.getElementById('openShiftBtn');
const appointmentsList = document.getElementById('appointmentsList');
let activeAppointmentId = null;
let activeTransactionId = null;
let searchTimer = null;
let pendingCheckinAppointment = null;
const walkinModal = document.getElementById('walkinModal');
const walkinBtn = document.getElementById('walkinBtn');
const cancelWalkin = document.getElementById('cancelWalkin');
const confirmWalkin = document.getElementById('confirmWalkin');
const searchInput = document.getElementById('walkinSearch');
const clientResults = document.getElementById('clientResults');
const serviceModal = document.getElementById('serviceModal');
const addServiceBtn = document.getElementById('addServiceBtn');
const cancelServiceBtn = document.getElementById('cancelServiceBtn');
const serviceSelect = document.getElementById('serviceSelect');
const confirmAddServiceBtn = document.getElementById('confirmAddServiceBtn');
const staffSelect = document.getElementById('staffSelect');
const qtyInput = document.getElementById('serviceQty');
const checkinModal = document.getElementById("checkinModal");
const confirmCheckinBtn = document.getElementById("confirmCheckinBtn");
const cancelCheckinBtn = document.getElementById("cancelCheckinBtn");
const statusModal = document.getElementById("statusModal");
const statusClientName = document.getElementById("statusClientName");
const statusActionLabel = document.getElementById("statusActionLabel");
const statusModalTitle = document.getElementById("statusModalTitle");
const confirmStatusBtn = document.getElementById("confirmStatusBtn");
const cancelStatusBtn = document.getElementById("cancelStatusBtn");

let pendingStatusAppointment = null;
let pendingStatusAction = null;


walkinBtn.addEventListener('click', () => {
    walkinModal.classList.remove('hidden');
    walkinModal.classList.add('flex');
});

cancelWalkin.addEventListener('click', () => {
    walkinModal.classList.add('hidden');
});

addServiceBtn.addEventListener('click', () => {
    serviceModal.classList.remove('hidden');
    serviceModal.classList.add('flex');
});

cancelServiceBtn.addEventListener('click', () => {
    serviceModal.classList.add('hidden');
});

function loadServices() {
    serviceSelect.innerHTML = `<option>Loading services…</option>`;

    fetch('../php/cashier/get-services.php')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;

            serviceSelect.innerHTML = `
                <option value="">Select service</option>
                ${d.services.map(s => `
                    <option value="${s.id}">
                        ${s.name} — ₱${parseFloat(s.base_price).toFixed(2)}
                    </option>
                `).join('')}
            `;
        });
}

addServiceBtn.addEventListener('click', loadServices);

//confirm walk-in
confirmWalkin.addEventListener('click', () => {
    const name = document.getElementById('walkinName').value.trim();
    const contact = document.getElementById('walkinContact').value.trim();

    if (!name) {
        showToast('Client name is required', 'error');
        return;
    }

    fetch('../php/cashier/create-walkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}&contact=${encodeURIComponent(contact)}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, 'error');
                return;
            }

            clientResults.innerHTML = '';
            searchInput.value = '';

            walkinModal.classList.add('hidden');
            showToast('Walk-in created (existing client)');

            activeAppointmentId = d.appointment_id;
            activeTransactionId = d.transaction_id;

            loadTodayAppointments();
        })
        .catch(() => showToast('Server error', 'error'));
});

//confirm adding service
confirmAddServiceBtn.addEventListener('click', () => {
    fetch('../php/cashier/add-transaction-service.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            transaction_id: activeTransactionId,
            service_id: serviceSelect.value,
            employee_id: staffSelect.value,
            quantity: qtyInput.value
        })
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error || 'Failed to add service', 'error');
                return;
            }

            serviceModal.classList.add('hidden');
            loadTransactionDetails(activeTransactionId);
            showToast('Service added');
        });
});

confirmCheckinBtn.addEventListener("click", () => {
    confirmCheckinBtn.disabled = true;
    fetch("../php/cashier/check-in-appointment.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `appointment_id=${pendingCheckinAppointment}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            checkinModal.classList.add("hidden");

            // Step 1: load/create transaction FIRST
            loadOrCreateTransaction(pendingCheckinAppointment);

            // Step 2: ask whether to sync services
            setTimeout(() => {
                openSyncServicesPrompt(pendingCheckinAppointment);
            }, 300);

            loadTodayAppointments();

            pendingCheckinAppointment = null;
            showToast("Client checked in");
        })
        .finally(() => {
            confirmCheckinBtn.disabled = false;
        });
});

function updateUI(open) {
    document.body.classList.toggle('overflow-hidden', !open);

    if (open) {
        overlay.classList.add('hidden');
        pos.classList.remove('opacity-50', 'pointer-events-none');

        badge.textContent = 'SHIFT OPEN';
        badge.className = `
            text-xs px-3 py-1 rounded-full font-medium
            bg-green-100 text-green-700
            dark:bg-green-900/40 dark:text-green-300
        `;

        closeBtn.classList.remove('hidden');
        loadTodayAppointments();

    } else {
        overlay.classList.remove('hidden');
        pos.classList.add('opacity-50', 'pointer-events-none');

        badge.textContent = 'NO SHIFT';
        badge.className = `
            text-xs px-3 py-1 rounded-full font-medium
            bg-red-100 text-red-700
            dark:bg-red-900/40 dark:text-red-300
        `;

        closeBtn.classList.add('hidden');
    }
}


function loadTodayAppointments() {
    fetch('../php/cashier/get-todays-appointments.php')
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                appointmentsList.innerHTML =
                    '<div class="text-xs text-red-500">Failed to load appointments</div>';
                return;
            }

            if (d.appointments.length === 0) {
                appointmentsList.innerHTML = `
                    <div class="text-sm text-gray-500 text-center">
                        No appointments today
                    </div>
                `;
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

                    <div class="font-medium">${a.client_name}</div>

                    <div class="text-xs text-gray-500">
                        ${a.start_time} · ${a.services || 'No service yet'}
                    </div>

                    <div class="text-xs mt-1 ${a.status === 'confirmed'
                    ? 'text-green-600'
                    : a.status === 'checked_in'
                        ? 'text-blue-500'
                        : 'text-gray-400'
                }">
                        ${a.status.replace('_', ' ')}
                    </div>

                    ${a.status === 'confirmed' ? `
                        <div class="flex gap-2 mt-2">
                            <button
                                data-action="checkin"
                                class="text-xs px-2 py-1 rounded bg-green-500 text-white">
                                Check-in
                            </button>
                            <button
                                data-action="no_show"
                                class="text-xs px-2 py-1 rounded bg-yellow-500 text-white">
                                No-show
                            </button>
                            <button
                                data-action="cancelled"
                                class="text-xs px-2 py-1 rounded bg-red-500 text-white">
                                Cancel
                            </button>
                        </div>
                    ` : ``}

                </div>
            `).join('');
        });
}

function loadOrCreateTransaction(appointmentId) {
    fetch('../php/cashier/get-or-create-transaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `appointment_id=${appointmentId}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, 'error');
                return;
            }

            document
                .querySelectorAll('[data-appointment-id]')
                .forEach(el => el.classList.remove('ring-2', 'ring-green-400'));

            const activeCard = document.querySelector(
                `[data-appointment-id="${appointmentId}"]`
            );
            activeCard?.classList.add('ring-2', 'ring-green-400');

            activeAppointmentId = appointmentId;
            activeTransactionId = d.transaction_id;

            loadTransactionDetails(activeTransactionId);

            showToast(d.existing ? 'Transaction loaded' : 'Transaction started');
        });
}


function loadTransactionDetails(transactionId) {
    fetch(`../php/cashier/get-transaction-details.php?transaction_id=${transactionId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, 'error');
                return;
            }

            // Client info
            document.getElementById('clientInfo').innerHTML = `
                <div class="font-medium">${d.transaction.full_name}</div>
                <div>${d.transaction.contact_number || 'No contact'}</div>
                <div class="text-xs mt-1 text-gray-400">
                    ${d.transaction.transaction_number}
                </div>
            `;

            // Services
            const serviceList = document.getElementById('serviceList');

            if (d.services.length === 0) {
                serviceList.innerHTML = `
                    <div class="text-sm text-gray-400">
                        No services added yet
                    </div>
                `;
            } else {
                serviceList.innerHTML = d.services.map(s => `
                    <div class="p-3 bg-white dark:bg-gray-800 rounded-lg shadow flex justify-between">
                        <div>
                            <div class="font-medium">${s.service_name}</div>
                            <div class="text-xs text-gray-400">Qty: ${s.quantity}</div>
                        </div>
                        <div class="font-semibold">₱${s.total_price}</div>
                    </div>
                `).join('');
            }

            // Total
            document.getElementById('transactionTotal').textContent = `₱${d.total}`;

            // Enable buttons
            document.getElementById('addServiceBtn').disabled = false;
            document.getElementById('addServiceBtn').classList.remove('opacity-50');

            document.getElementById('payBtn').disabled = d.services.length === 0;
            document.getElementById('payBtn').classList.toggle('opacity-50', d.services.length === 0);
        });
}

function openSyncServicesPrompt(appointmentId) {
    fetch(`../php/cashier/has-appointment-services.php?appointment_id=${appointmentId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.has_services) return;

            if (confirm("This client booked services. Load them into the transaction?")) {
                fetch("../php/cashier/sync-appointment-services.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `appointment_id=${appointmentId}&transaction_id=${activeTransactionId}`
                })
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success) {
                            showToast(res.error, "error");
                            return;
                        }

                        loadTransactionDetails(activeTransactionId);
                        showToast("Booked services loaded");
                    });
            }
        });
}


appointmentsList.addEventListener("click", (e) => {

    // 🔹 ACTION BUTTONS
    const actionBtn = e.target.closest("button[data-action]");
    if (actionBtn) {
        e.stopPropagation();

        const card = actionBtn.closest("[data-appointment-id]");
        pendingStatusAppointment = card.dataset.appointmentId;
        pendingStatusAction = actionBtn.dataset.action;

        statusClientName.textContent = card.dataset.clientName;
        statusActionLabel.textContent = pendingStatusAction.replace("_", " ");

        // Styling per action
        if (pendingStatusAction === "no_show") {
            statusModalTitle.textContent = "Confirm No-Show";
            confirmStatusBtn.className =
                "px-4 py-2 text-sm rounded bg-yellow-500 text-white";
        }

        if (pendingStatusAction === "cancelled") {
            statusModalTitle.textContent = "Confirm Cancellation";
            confirmStatusBtn.className =
                "px-4 py-2 text-sm rounded bg-red-500 text-white";
        }

        statusModal.classList.remove("hidden");
        statusModal.classList.add("flex");
        return;
    }

    // 🔹 CARD CLICK
    const card = e.target.closest("[data-appointment-id]");
    if (!card) return;

    if (card.dataset.status === "checked_in") {
        loadOrCreateTransaction(card.dataset.appointmentId);
        return;
    }

    showToast("Please check-in the client first", "error");
});

confirmStatusBtn.addEventListener("click", () => {
    confirmStatusBtn.disabled = true;

    fetch("../php/cashier/update-appointment-status.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `appointment_id=${pendingStatusAppointment}&status=${pendingStatusAction}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            showToast(`Appointment marked as ${pendingStatusAction.replace("_", " ")}`);
            loadTodayAppointments();
            statusModal.classList.add("hidden");

            pendingStatusAppointment = null;
            pendingStatusAction = null;
        })
        .finally(() => {
            confirmStatusBtn.disabled = false;
        });
});

cancelCheckinBtn.addEventListener("click", () => {
    pendingCheckinAppointment = null;
    checkinModal.classList.add("hidden");
});

cancelStatusBtn.addEventListener("click", () => {
    statusModal.classList.add("hidden");
    pendingStatusAppointment = null;
    pendingStatusAction = null;
});

function showClientLoading() {
    clientResults.innerHTML = `
        <div class="flex items-center gap-2 text-xs text-gray-400 animate-pulse">
            <span class="h-3 w-3 rounded-full bg-teal-400"></span>
            Searching clients…
        </div>
    `;
}

if (searchInput && clientResults) {
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);

        const q = searchInput.value.trim();

        if (q.length < 2) {
            clientResults.innerHTML = '';
            return;
        }

        // 👇 show loading immediately
        showClientLoading();

        searchTimer = setTimeout(() => {
            fetch(`../php/cashier/search-clients.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        clientResults.innerHTML = `
                            <div class="text-xs text-red-400">Search failed</div>
                        `;
                        return;
                    }

                    if (d.clients.length === 0) {
                        clientResults.innerHTML = `
                            <div class="text-xs text-gray-400">No matching clients</div>
                        `;
                        return;
                    }

                    clientResults.innerHTML = d.clients.map(c => `
                        <div
                            class="p-2 rounded-lg border cursor-pointer
                            hover:bg-teal-50 dark:hover:bg-gray-700 transition"
                            data-client-id="${c.id}">
                            <div class="font-medium">${c.full_name}</div>
                            <div class="text-xs text-gray-400">${c.contact_number || ''}</div>
                        </div>
                    `).join('');
                })
                .catch(() => {
                    clientResults.innerHTML = `
                        <div class="text-xs text-red-400">Server error</div>
                    `;
                });
        }, 300);
    });
}

clientResults.addEventListener('click', e => {
    const item = e.target.closest('[data-client-id]');
    if (!item) return;

    const clientId = item.dataset.clientId;

    fetch('../php/cashier/create-walkin-existing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `client_id=${clientId}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, 'error');
                return;
            }

            walkinModal.classList.add('hidden');
            showToast('Walk-in created (existing client)');

            activeAppointmentId = d.appointment_id;
            activeTransactionId = d.transaction_id;

            loadTodayAppointments();
        });
});

// Check shift status on load
fetch('../php/cashier/cashier-shift.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'action=status'
})
    .then(r => r.json())
    .then(d => {
        if (d.success) updateUI(d.open);
        else alert(d.error || 'Shift check failed');
    })
    .catch(() => alert('Server error checking shift status'));

// Open shift
openBtn.addEventListener('click', () => {
    const cash = document.getElementById('openingCash').value || 0;

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=open&opening_cash=${cash}`
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) updateUI(true);
            else alert(d.error);
        });
});
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;

    toast.className = `
        fixed bottom-6 right-6 z-50
        px-4 py-3 rounded-lg shadow-lg text-sm text-white
        ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}
    `;

    toast.classList.remove('hidden');

    setTimeout(() => {
        toast.classList.add('hidden');
    }, 2500);
}


// Close shift
closeBtn.addEventListener('click', () => {
    const cash = prompt("Enter closing cash:");

    if (cash === null) return;

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `action=close&closing_cash=${cash}`
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) updateUI(false);
            else alert(d.error);
        });
});