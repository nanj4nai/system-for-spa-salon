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

function formatDateSafe(value) {
    if (!value) return "—";

    const d = new Date(value);
    return isNaN(d.getTime())
        ? "—"
        : d.toLocaleString();
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
            <th>Remarks</th>
            <th>Status</th>
            <th class="text-right">Actions</th>`;
    }

    if (currentTab === "active") {
        head.innerHTML = `
            <th>Cashier</th>
            <th>Opened</th>
            <th>Opening</th>
            <th class="text-right">Actions</th>`;
    }

    if (currentTab === "closed") {
        head.innerHTML = `
            <th>Cashier</th>
            <th>Opened</th>
            <th>Closed</th>
            <th>Opening</th>
            <th>Closing</th>
            <th class="text-right">Actions</th>`;
    }

    table.innerHTML = shifts.map(s => {

        /* ---------- PENDING ---------- */
        if (currentTab === "pending") {
            return `
            <tr class="border-b">
                <td class="py-3">${s.username}</td>
                <td>${new Date(s.opened_at).toLocaleString()}</td>
                <td>₱${Number(s.opening_cash).toFixed(2)}</td>
                <td>₱${Number(s.closing_cash).toFixed(2)}</td>
                <td class="text-xs text-gray-500 max-w-xs truncate">
                    ${s.remarks || '<span class="italic text-gray-400">—</span>'}
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
            return `
            <tr class="border-b">
                <td class="py-3">${s.username}</td>
                <td>${new Date(s.opened_at).toLocaleString()}</td>
                <td>₱${Number(s.opening_cash).toFixed(2)}</td>
                <td class="text-right">
                    <button onclick="forceClose(${s.id})"
                        class="px-3 py-1 text-xs bg-red-600 text-white rounded">
                        Force Close
                    </button>
                </td>
            </tr>`;
        }

        /* ---------- CLOSED ---------- */
        return `
        <tr class="border-b">
            <td class="py-3">${s.username}</td>
            <td>${new Date(s.opened_at).toLocaleString()}</td>
            <td>${new Date(s.closed_at).toLocaleString()}</td>
            <td>₱${Number(s.opening_cash).toFixed(2)}</td>
            <td>₱${Number(s.closing_cash).toFixed(2)}</td>
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
    ["sumOpening", "sumCashSales", "sumExpected", "sumClosing", "sumVariance"]
        .forEach(id => document.getElementById(id).textContent = "—");

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
            document.getElementById("sumOpening").textContent = `₱${Number(s.opening_cash).toFixed(2)}`;
            document.getElementById("sumCashSales").textContent = `₱${Number(s.cash_sales).toFixed(2)}`;
            document.getElementById("sumExpected").textContent = `₱${Number(s.expected_cash).toFixed(2)}`;
            document.getElementById("sumClosing").textContent = `₱${Number(s.closing_cash).toFixed(2)}`;

            const v = document.getElementById("sumVariance");
            v.textContent = `₱${Number(s.variance).toFixed(2)}`;
            v.className = s.variance == 0
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
    const canMarkAR = hasBalance && !t.has_receivable;

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

            txServices.innerHTML = renderServices(d.services);
            txProducts.innerHTML = renderProducts(d.products);
            txPayments.innerHTML = renderPayments(d.payments);


            // 🔥 ACCOUNTS RECEIVABLE VIEW
            const arSection = document.getElementById("txARSection");
            if (d.receivable) {
                arSection.classList.remove("hidden");

                currentReceivable = d.receivable;
                currentReceivableId = d.receivable.id;

                arAmountView.textContent = `₱${Number(d.receivable.amount).toFixed(2)}`;
                arBalanceView.textContent = `₱${Number(d.receivable.balance).toFixed(2)}`;
                arStatusView.textContent = d.receivable.status;
                arRemarksView.textContent = d.receivable.remarks || "—";

                // Hide pay button if fully paid
                if (Number(d.receivable.balance) <= 0) {
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
            <td colspan="3" class="py-4 text-center text-gray-400 italic">
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
        </tr>
    `).join("");
}

function renderServices(rows) {
    if (!rows.length) {
        return `
        <tr>
            <td colspan="5" class="py-4 text-center text-gray-400 italic">
                No services
            </td>
        </tr>`;
    }

    return rows.map(s => `
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
            <td class="p-2 text-right font-mono">
                ₱${Number(s.unit_price).toFixed(2)}
            </td>
            <td class="p-2 text-right font-mono font-semibold">
                ₱${Number(s.total_price).toFixed(2)}
            </td>
        </tr>
    `).join("");
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


/* =====================================================
   INIT
===================================================== */
loadShifts();
