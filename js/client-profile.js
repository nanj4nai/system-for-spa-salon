// CLIENT TRANSACTION MODAL ELEMENTS
const ctxModal = document.getElementById("clientTxModal");
const ctxNumber = document.getElementById("ctxNumber");
const ctxStatus = document.getElementById("ctxStatus");
const ctxTotal = document.getElementById("ctxTotal");
const ctxBalance = document.getElementById("ctxBalance");

const ctxServices = document.getElementById("ctxServices");
const ctxProducts = document.getElementById("ctxProducts");
const ctxPayments = document.getElementById("ctxPayments");

const ctxARSection = document.getElementById("ctxARSection");
const ctxARAmount = document.getElementById("ctxARAmount");
const ctxARBalance = document.getElementById("ctxARBalance");
const ctxARStatus = document.getElementById("ctxARStatus");
const ctxARRemarks = document.getElementById("ctxARRemarks");
const ctxARPayments = document.getElementById("ctxARPayments");

// ===== PAGE ELEMENTS =====
const visitTable = document.getElementById("visitTable");
const txTable = document.getElementById("clientTransactions");
const arBadge = document.getElementById("clientARBadge");

// ===== CLIENT CONTEXT =====
const clientId = new URLSearchParams(window.location.search).get("id");


document.addEventListener("DOMContentLoaded", () => {
    fetchVisits();
    fetchClientTransactions();
    fetchARSummary();
});

function isMobile() {
    return window.matchMedia("(max-width: 768px)").matches;
}


async function fetchVisits() {
    const res = await fetch(`php/clients.php?action=visits&id=${clientId}`);
    const visits = await res.json();

    visitTable.innerHTML = "";

    document.getElementById("totalVisits").textContent = visits.length;

    document.getElementById("lastVisit").textContent =
        visits.length ? visits[0].appointment_date : "—";

    visits.forEach(v => {
        const tr = document.createElement("tr");
        tr.className = "cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/30 transition";

        if (v.transaction_id) {
            tr.onclick = () => openClientTransactionModal(v.transaction_id);
        }

        tr.innerHTML = `
            <td class="px-4 py-2">${v.appointment_date}</td>
            <td class="px-4 py-2">${v.services}</td>
            <td class="px-4 py-2">₱${Number(v.total_amount).toFixed(2)}</td>
            <td class="px-4 py-2 capitalize">${v.status}</td>
        `;
        visitTable.appendChild(tr);
    });
}

async function fetchClientTransactions() {
    if (!txTable) return;

    const res = await fetch(`php/clients.php?action=transactions&id=${clientId}`);
    const rows = await res.json();

    txTable.innerHTML = "";

    rows.forEach(t => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td class="px-4 py-2">${new Date(t.created_at).toLocaleString()}</td>
            <td class="px-4 py-2 font-mono text-xs">${t.transaction_number}</td>
            <td class="px-4 py-2 text-right">₱${Number(t.total_amount).toFixed(2)}</td>
            <td class="px-4 py-2 text-right">₱${Number(t.total_paid).toFixed(2)}</td>
            <td class="px-4 py-2 text-right ${t.balance_due > 0 ? 'text-red-600 font-semibold' : 'text-green-600'}">
                ₱${Number(t.balance_due).toFixed(2)}
            </td>
            <td class="px-4 py-2 capitalize">${t.payment_status}</td>
        `;
        txTable.appendChild(tr);
    });
}

async function fetchARSummary() {
    const res = await fetch(`php/clients.php?action=ar_summary&id=${clientId}`);
    const ar = await res.json();

    if (Number(ar.total_balance) > 0) {
        arBadge.classList.remove("hidden");
        arBadge.textContent = `A/R ₱${Number(ar.total_balance).toFixed(2)}`;
    }
}

function openClientTransactionModal(transactionId) {
    ctxModal.classList.remove("hidden");

    fetch(`php/clients.php?action=transaction_details&id=${transactionId}`)
        .then(r => r.json())
        .then(d => {

            if (!d.transaction) {
                alert("Failed to load transaction details");
                return;
            }

            ctxNumber.textContent = d.transaction.transaction_number;
            ctxStatus.textContent = d.transaction.payment_status;
            ctxTotal.textContent = `₱${Number(d.transaction.total_amount).toFixed(2)}`;
            ctxBalance.textContent = `₱${Number(d.transaction.balance_due).toFixed(2)}`;

            ctxServices.innerHTML = renderClientRows(d.services, "services");
            ctxProducts.innerHTML = renderClientRows(d.products, "products");
            ctxPayments.innerHTML = renderClientRows(d.payments, "payments");
            if (d.receivable) {
                ctxARSection.classList.remove("hidden");
                ctxARAmount.textContent = `₱${d.receivable.amount}`;
                ctxARBalance.textContent = `₱${d.receivable.balance}`;
                ctxARStatus.textContent = d.receivable.status;
                ctxARRemarks.textContent = d.receivable.remarks || "—";
                ctxARPayments.innerHTML = renderClientRows(d.ar_payments, "ar");
            } else {
                ctxARSection.classList.add("hidden");
            }
        });
}


function closeClientTransactionModal() {
    document.getElementById("clientTxModal").classList.add("hidden");
}

function renderClientRows(rows, mode) {
    if (!rows || !rows.length) {
        return `
            <tr>
                <td class="py-4 text-center text-gray-400 italic">
                    —
                </td>
            </tr>`;
    }

    // 📱 MOBILE — stacked cards
    if (isMobile()) {
        return `
            <tr>
                <td class="p-0">
                    <div class="space-y-3">
                        ${rows.map(r => `
                            <div class="rounded-xl border dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3 text-sm">
                                ${Object.entries(r).map(([key, val]) => `
                                    <div class="flex justify-between gap-3">
                                        <span class="text-gray-500 capitalize text-xs">
                                            ${key.replace(/_/g, ' ')}
                                        </span>
                                        <span class="font-medium text-right">
                                            ${val ?? "—"}
                                        </span>
                                    </div>
                                `).join("")}
                            </div>
                        `).join("")}
                    </div>
                </td>
            </tr>`;
    }

    // 🖥️ DESKTOP — normal table rows
    return rows.map(r => `
        <tr class="border-b last:border-0 dark:border-gray-700">
            <td class="px-4 py-2">
                ${Object.values(r).join(" · ")}
            </td>
        </tr>
    `).join("");
}

