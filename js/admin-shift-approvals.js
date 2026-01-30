/* =====================================================
   ELEMENTS & STATE
===================================================== */
const table = document.getElementById("shiftTable");
const head = document.getElementById("tableHead");
const tabs = document.querySelectorAll(".tab-btn");

const shiftModal = document.getElementById("shiftModal");
const arModal = document.getElementById("arModal");
const transactionModal = document.getElementById("transactionModal");

let currentTab = "pending";
let currentViewedShiftId = null;
let arTransactionId = null;
let currentReceivable = null;
let currentReceivableId = null;
let currentViewedTransactionId = null;
let gateCashierId = null;

function formatDateSafe(value) {
    if (!value) return "—";

    const d = new Date(value);
    return isNaN(d.getTime())
        ? "—"
        : d.toLocaleString();
}

function renderARBadge(s) {
    const count = Number(s.ar_count);
    if (!count) {
        return `<span class="text-xs text-gray-400 italic">None</span>`;
    }

    return `
    <span class="inline-flex items-center gap-1
        px-2 py-1 text-xs rounded
        bg-orange-100 text-orange-700 font-semibold">
        💼 Pay-Later · ${count} · ₱${Number(s.ar_balance).toFixed(2)}
    </span>`;
}

/* =====================================================
   TAB HANDLING
===================================================== */
tabs.forEach(btn => {
    btn.addEventListener("click", () => {
        tabs.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");

        currentTab = btn.dataset.tab;
        loadShifts();
    });
});


/* =====================================================
   LOAD SHIFTS
===================================================== */
function loadShifts() {
    let action =
        currentTab === "pending_open" ? "list_pending_open" :
            currentTab === "pending" ? "list_pending" :
                currentTab === "active" ? "list_active" :
                    "list_closed";


    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=${action}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.shifts.length) {
                head.innerHTML = "";
                table.innerHTML = `
                    <tr>
                        <td colspan="7" class="py-6 text-center text-gray-400">
                            No shifts found
                        </td>
                    </tr>`;
                return;
            }

            renderTable(d.shifts);
        });
}


/* =====================================================
   TABLE RENDERING
===================================================== */
function renderTable(shifts) {

    if (currentTab === "pending") {
        head.innerHTML = `
            <th>Cashier</th>
            <th>Opened</th>
            <th>Opening</th>
            <th>Declared</th>
            <th>A/R</th>
            <th>Status</th>
            <th class="text-right">Actions</th>`;
    }

    if (currentTab === "active") {
        head.innerHTML = `
            <th>Cashier</th>
            <th>Opened</th>
            <th>Opening</th>
            <th>A/R</th>
            <th class="text-right">Actions</th>`;
    }

    if (currentTab === "closed") {
        head.innerHTML = `
        <th>Cashier</th>
        <th>Opened</th>
        <th>Closed</th>
        <th>Opening</th>
        <th>Closing</th>
        <th>A/R</th>
        <th class="text-right">Actions</th>`;
    }

    table.innerHTML = shifts.map(s => {
        /* ---------- PENDING OPEN ---------- */
        if (currentTab === "pending_open") {
            return `
            <tr class="border-b bg-blue-50/40">
                <td class="py-3 font-medium">${s.username}</td>
                <td>${formatDateSafe(s.opened_at)}</td>

                <td>
                    <span class="px-2 py-1 text-xs rounded
                        bg-blue-100 text-blue-700 font-semibold">
                        Waiting for cashier
                    </span>
                </td>

                <td class="text-right">
                    <button onclick="cancelGate(${s.id})"
                        class="px-3 py-1 text-xs bg-red-600 text-white rounded">
                        Cancel Gate
                    </button>
                </td>
            </tr>`;
        }


        /* ---------- PENDING ---------- */
        if (currentTab === "pending") {
            const hasAR = Number(s.ar_count) > 0;
            const arBadge = hasAR
                ? `
        <span class="inline-flex items-center gap-1
            px-2 py-1 text-xs rounded
            bg-orange-100 text-orange-700 font-semibold">
            ⚠ ${s.ar_count} · ₱${Number(s.ar_balance).toFixed(2)}
        </span>`
                : `
        <span class="text-xs text-gray-400 italic">
            None
        </span>`;
            return `
            <tr class="border-b ${hasAR ? 'bg-orange-50/40' : ''}">
                <td class="py-3">${s.username}</td>
                <td>${new Date(s.opened_at).toLocaleString()}</td>
                <td>₱${Number(s.opening_cash).toFixed(2)}</td>
                <td>₱${Number(s.closing_cash).toFixed(2)}</td>

                <td>
                    ${arBadge}
                </td>

                <td>
                    <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-700">
                        Pending
                    </span>
                </td>

                <td class="text-right space-x-2">
                    <button onclick="viewDetails(${s.id})"
                        class="px-3 py-1 text-xs bg-indigo-600 text-white rounded">
                        Review
                    </button>
                    <button onclick="approve(${s.id})"
                        class="px-3 py-1 text-xs bg-green-600 text-white rounded">
                        Approve
                    </button>
                    <button onclick="reject(${s.id})"
                        class="px-3 py-1 text-xs bg-red-600 text-white rounded">
                        Reject
                    </button>
                </td>
            </tr>`;
        }

        /* ---------- ACTIVE ---------- */
        if (currentTab === "active") {
            const hasAR = Number(s.ar_count) > 0;

            return `
            <tr class="border-b ${hasAR ? 'bg-orange-50/40' : ''}">
                <td class="py-3">${s.username}</td>
                <td>${new Date(s.opened_at).toLocaleString()}</td>
                <td>₱${Number(s.opening_cash).toFixed(2)}</td>

                <td>
                    ${renderARBadge(s)}
                </td>

                <td class="text-right">
                    <button onclick="forceClose(${s.id})"
                        class="px-3 py-1 text-xs bg-red-600 text-white rounded">
                        Force Close
                    </button>
                </td>
            </tr>`;
        }

        /* ---------- CLOSED ---------- */
        const hasAR = Number(s.ar_count) > 0;

        return `
        <tr class="border-b ${hasAR ? 'bg-orange-50/40' : ''}">
            <td class="py-3">${s.username}</td>
            <td>${new Date(s.opened_at).toLocaleString()}</td>
            <td>${new Date(s.closed_at).toLocaleString()}</td>
            <td>₱${Number(s.opening_cash).toFixed(2)}</td>
            <td>₱${Number(s.closing_cash).toFixed(2)}</td>

            <td>
                ${renderARBadge(s)}
            </td>

            <td class="text-right">
                <button onclick="viewDetails(${s.id})"
                    class="px-3 py-1 text-xs bg-indigo-600 text-white rounded">
                    View
                </button>
            </td>
        </tr>`;

    }).join("");
}


/* =====================================================
   SHIFT ACTIONS
===================================================== */
function approve(id) {
    if (!confirm("Approve and close this shift?")) return;
    postAction("approve", id);
}

function reject(id) {
    if (!confirm("Reject close request?")) return;
    postAction("reject", id);
}

function forceClose(id) {
    if (!confirm("Force close this shift?")) return;
    postAction("force_close", id);
}

function postAction(action, id) {
    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=${action}&shift_id=${id}`
    })
        .then(r => r.json())
        .then(() => loadShifts());
}

function cancelGate(id) {
    if (!confirm("Cancel this open gate?")) return;

    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=cancel_gate&shift_id=${id}`
    })
        .then(r => r.json())
        .then(() => loadShifts());
}

function loadCashiersForOpenShift() {
    const select = document.getElementById("openShiftCashier");
    select.innerHTML = `<option value="">Loading…</option>`;

    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list_cashiers"
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.cashiers.length) {
                select.innerHTML =
                    `<option value="">No available cashiers</option>`;
                return;
            }

            select.innerHTML =
                `<option value="">Select cashier</option>` +
                d.cashiers.map(c =>
                    `<option value="${c.id}">${c.username}</option>`
                ).join("");
        });
}
function openOpenShiftModal() {
    loadCashiersForOpenShift();
    document.getElementById("openShiftModal")
        .classList.remove("hidden");
}

function closeOpenShiftModal() {
    document.getElementById("openShiftModal")
        .classList.add("hidden");
}


function confirmOpenShift() {
    const cashierId = document.getElementById("openShiftCashier").value;

    if (!cashierId) {
        alert("Select a cashier");
        return;
    }

    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=open_shift&cashier_id=${cashierId}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert(d.error || "Failed to open gate");
                return;
            }

            closeOpenShiftModal();
            loadShifts();
        });
}

/* =====================================================
   SHIFT DETAILS MODAL
===================================================== */
function closeModal() {
    shiftModal.classList.add("hidden");
}

function viewDetails(shiftId) {
    currentViewedShiftId = shiftId;
    shiftModal.classList.remove("hidden");

    resetSummary();
    loadSummary(shiftId);
    loadTransactions(shiftId);
}

function resetSummary() {
    [
        "sumOpening",
        "sumGross",
        "sumCollected",
        "sumCashSales",
        "sumPayLater",
        "sumExpected",
        "sumClosing",
        "sumVariance"
    ].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = "—";
    });

    document.getElementById("transactionTable").innerHTML =
        `<tr><td colspan="6" class="p-4 text-center text-gray-400">Loading…</td></tr>`;
}


/* =====================================================
   SUMMARY
===================================================== */
function loadSummary(shiftId) {
    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=summary&shift_id=${shiftId}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;

            const s = d.summary;

            sumOpening.textContent = `₱${Number(s.opening_cash).toFixed(2)}`;
            sumGross.textContent = `₱${Number(s.gross_sales).toFixed(2)}`;
            sumCollected.textContent = `₱${Number(s.total_collected).toFixed(2)}`;
            sumCashSales.textContent = `₱${Number(s.cash_collected).toFixed(2)}`;
            sumPayLater.textContent = `₱${Number(s.pay_later_balance).toFixed(2)}`;
            sumExpected.textContent = `₱${Number(s.expected_cash).toFixed(2)}`;
            sumClosing.textContent = `₱${Number(s.closing_cash).toFixed(2)}`;

            sumVariance.textContent = `₱${Number(s.variance).toFixed(2)}`;
            sumVariance.className =
                s.variance == 0
                    ? "font-semibold text-green-600"
                    : "font-semibold text-red-600";
        });
}

/* =====================================================
   TRANSACTIONS LIST
===================================================== */
function loadTransactions(shiftId) {
    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=transactions&shift_id=${shiftId}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.transactions.length) {
                document.getElementById("transactionTable").innerHTML =
                    `<tr><td colspan="6" class="p-4 text-center text-gray-400">No transactions</td></tr>`;
                return;
            }

            document.getElementById("transactionTable").innerHTML =
                d.transactions.map(renderTransactionRow).join("");
        });
}

function renderTransactionRow(t) {
    const hasBalance = Number(t.balance_due) > 0;
    const canMarkAR =
        hasBalance &&
        !t.has_receivable &&
        t.transaction_type !== "booking_payment";

    return `
    <tr class="border-b ${hasBalance ? 'bg-red-50' : ''}">
        <td class="p-2 font-mono text-xs">
            <button onclick="openTransactionModal(${t.id})"
                class="text-indigo-600 underline hover:text-indigo-800">
                ${t.transaction_number}
            </button>
        </td>
        <td>${t.client_name || '<span class="italic text-gray-400">Walk-in</span>'}</td>
        <td>₱${Number(t.total_amount).toFixed(2)}</td>
        <td>₱${Number(t.total_paid).toFixed(2)}</td>
        <td class="${hasBalance ? 'text-red-600 font-semibold' : 'text-green-600'}">
            ₱${Number(t.balance_due).toFixed(2)}
        </td>
        <td class="space-x-1">
            <button onclick="openTransactionModal(${t.id})"
                class="px-2 py-1 text-xs bg-blue-600 text-white rounded">
                View
            </button>

            ${canMarkAR ? `
            <button onclick="openARModal(${t.id}, '${t.client_name || ''}', ${t.balance_due})"
                class="px-2 py-1 text-xs bg-orange-600 text-white rounded">
                Mark A/R
            </button>` : ""}

        </td>
    </tr>`;
}

function statusBadge(tx) {
    const balance = Number(tx.balance_due);
    const paid = Number(tx.total_paid ?? 0);

    if (balance <= 0) {
        return `
        <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-700 font-semibold">
            Paid
        </span>`;
    }

    if (paid > 0) {
        return `
        <span class="px-2 py-1 text-xs rounded bg-orange-100 text-orange-700 font-semibold">
            Partial
        </span>`;
    }

    return `
    <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 font-semibold">
        Unpaid
    </span>`;
}

/* =====================================================
   TRANSACTION MODAL
===================================================== */
function openTransactionModal(transactionId) {
    currentViewedTransactionId = transactionId; // 👈 ADD THIS
    transactionModal.classList.remove("hidden");

    ["txServices", "txProducts", "txPayments"]
        .forEach(id => document.getElementById(id).innerHTML = "");

    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=transaction_details&transaction_id=${transactionId}`
    })
        .then(r => r.json())
        .then(d => {
            const tx = d.transaction;

            txNumber.textContent = tx.transaction_number;
            txClient.textContent = tx.client_name || "Walk-in";
            txStatus.innerHTML = statusBadge(tx);
            txBalance.textContent = `₱${Number(tx.balance_due).toFixed(2)}`;

            txServices.innerHTML = renderServices(
                d.services,
                d.service_consumables || []
            );
            txProducts.innerHTML = renderProducts(d.products);
            txPayments.innerHTML = renderPayments(d.payments);


            // 🔥 ACCOUNTS RECEIVABLE VIEW
            const arSection = document.getElementById("txARSection");
            if (d.receivable) {
                arSection.classList.remove("hidden");

                const typeLabel =
                    d.receivable.ar_type === "online_tracking"
                        ? `<span class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700">Online Tracking</span>`
                        : `<span class="text-xs px-2 py-1 rounded bg-orange-100 text-orange-700">Pay Later</span>`;

                arTypeView.innerHTML = typeLabel;

                // 🔒 Disable payments for online tracking
                if (d.receivable.ar_type === "online_tracking") {
                    payARBtn.classList.add("hidden");
                } else {
                    payARBtn.classList.remove("hidden");
                }
            } else {
                arSection.classList.add("hidden");
            }

            // 🔥 A/R PAYMENT HISTORY
            const arPaymentsSection = document.getElementById("txARPaymentsSection");
            const arPaymentsBody = document.getElementById("txARPayments");

            if (d.receivable && d.ar_payments && d.ar_payments.length) {
                arPaymentsSection.classList.remove("hidden");
                arPaymentsBody.innerHTML = renderARPayments(d.ar_payments);
            } else {
                arPaymentsSection.classList.add("hidden");
            }

        });
}

function closeTransactionModal() {
    transactionModal.classList.add("hidden");
}


/* =====================================================
   A/R MODAL
===================================================== */
function openARModal(id, clientName, balance) {
    arTransactionId = id;
    document.getElementById("arClientName").textContent = clientName || "Walk-in";
    document.getElementById("arAmount").textContent = `₱${Number(balance).toFixed(2)}`;
    document.getElementById("arRemarks").value = "";
    arModal.classList.remove("hidden");
}

function closeARModal() {
    arModal.classList.add("hidden");
}

function confirmAR() {
    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=mark_ar&transaction_id=${arTransactionId}&remarks=${encodeURIComponent(arRemarks.value)}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert(d.error || "Failed to mark as A/R");
                return;
            }

            closeARModal();
            openTransactionModal(arTransactionId); // refresh details
            loadTransactions(currentViewedShiftId);
        });
}

function openARPaymentModal() {
    if (!currentReceivableId) {
        alert("No receivable selected");
        return;
    }

    arPayBalance.textContent =
        `₱${Number(currentReceivable.balance).toFixed(2)}`;

    arPayAmount.value = "";
    arPayRemarks.value = "";

    arPaymentModal.classList.remove("hidden");
}

function closeARPaymentModal() {
    arPaymentModal.classList.add("hidden");
}

function confirmARPayment() {
    const amount = parseFloat(arPayAmount.value);
    const method = arPayMethod.value;
    const ref = arPayRef.value;
    if (!amount || amount <= 0) {
        alert("Enter a valid amount");
        return;
    }
    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:
            `action=apply_ar_payment` +
            `&receivable_id=${currentReceivableId}` +
            `&amount=${amount}` +
            `&payment_method=${method}` +
            `&reference=${encodeURIComponent(ref)}` +
            `&remarks=${encodeURIComponent(arPayRemarks.value)}`
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert(d.error || "Payment failed");
                return;
            }

            closeARPaymentModal();

            // Refresh transaction + history
            openTransactionModal(currentViewedTransactionId);
            loadTransactions(currentViewedShiftId);
        });
}

/* =====================================================
   UTIL
===================================================== */
function renderPayments(rows) {
    if (!rows.length) {
        return `
        <tr>
            <td colspan="4" class="py-4 text-center text-gray-400 italic">
                No payments recorded
            </td>
        </tr>`;
    }

    return rows.map(p => `
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
            <td class="p-2 text-left capitalize">
                ${p.payment_method}
            </td>

            <td class="p-2 text-right font-mono">
                ₱${Number(p.amount).toFixed(2)}
            </td>

            <td class="p-2 text-right text-xs text-gray-500">
                ${formatDateSafe(p.payment_date)}
            </td>

            <td class="p-2 text-right">
                ${p.receipt_number ? `
                    <a
                        href="receipt-view.php?receipt_number=${encodeURIComponent(p.receipt_number)}"
                        target="_blank"
                        class="text-indigo-600 text-xs underline hover:text-indigo-800"
                    >
                        View
                    </a>
                ` : `
                    <span class="text-xs text-gray-400 italic">—</span>
                `}
            </td>
        </tr>
    `).join("");
}

function renderServices(services, consumables) {
    if (!services.length) {
        return `
        <tr>
            <td colspan="4" class="py-4 text-center text-gray-400 italic">
                No services
            </td>
        </tr>`;
    }

    return services.map(s => {
        const usedProducts = consumables.filter(
            c => c.appointment_service_id === s.appointment_service_id
        );

        return `
        <!-- SERVICE ROW -->
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
            <td class="p-2 font-medium">
                ${s.service_name}
            </td>
            <td class="p-2 text-gray-500">
                ${s.staff_name || "—"}
            </td>
            <td class="p-2 text-center">
                ${s.quantity}
            </td>
            <td class="p-2 text-right font-mono font-semibold">
                ₱${Number(s.total_price).toFixed(2)}
            </td>
        </tr>

        <!-- CONSUMABLES -->
        ${usedProducts.length ? `
        <tr class="bg-gray-50 dark:bg-gray-700/30">
            <td colspan="4" class="px-4 py-2 text-xs text-gray-600 dark:text-gray-300">
                <span class="font-semibold">Consumables used:</span>
                <ul class="mt-1 space-y-1">
                    ${usedProducts.map(p => `
                        <li class="flex justify-between">
                            <span>
                                ${p.product_name}
                                — ${p.quantity_used} ${p.unit}
                                <span class="text-gray-400">
                                    (₱${Number(p.unit_price).toFixed(2)}/${p.unit})
                                </span>
                            </span>
                            <span class="font-mono">
                                ₱${Number(p.total_price).toFixed(2)}
                            </span>
                        </li>
                    `).join("")}
                </ul>
            </td>
        </tr>
        ` : ``}
        `;
    }).join("");
}

function renderProducts(rows) {
    if (!rows.length) {
        return `
        <tr>
            <td colspan="4" class="py-4 text-center text-gray-400 italic">
                No products
            </td>
        </tr>`;
    }

    return rows.map(p => `
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
            <td class="p-2 font-medium">
                ${p.product_name}
            </td>
            <td class="p-2 text-center">
                ${p.quantity}
            </td>
            <td class="p-2 text-right font-mono">
                ₱${Number(p.unit_price).toFixed(2)}
            </td>
            <td class="p-2 text-right font-mono font-semibold">
                ₱${Number(p.total_price).toFixed(2)}
            </td>
        </tr>
    `).join("");
}

function renderARPayments(rows) {
    if (!rows || !rows.length) {
        return `
        <tr>
            <td colspan="3" class="py-4 text-center text-gray-400 italic">
                No A/R payments yet
            </td>
        </tr>`;
    }

    return rows.map(p => `
        <tr class="hover:bg-orange-50">
            <td class="p-2 text-right font-mono">
                ₱${Number(p.amount).toFixed(2)}
            </td>
            <td class="p-2 text-right text-xs text-gray-500">
                ${formatDateSafe(p.payment_date)}
            </td>
            <td class="p-2 text-left text-xs">
                ${p.remarks || "—"}
            </td>
        </tr>
    `).join("");
}

arPayMethod.addEventListener("change", () => {
    const method = arPayMethod.value;
    arPayRef.classList.toggle(
        "hidden",
        method === "cash"
    );
});



/* =====================================================
   INIT
===================================================== */
loadShifts();
