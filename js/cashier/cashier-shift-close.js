
function loadShiftSummaryView() {
    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=summary'
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;

            renderShiftSummaryView(d);
        });
}

function renderShiftSummaryView(d) {
    const box = document.getElementById('shiftSummaryView');
    if (!box) return;

    const totals = d.totals || {};
    const payments = d.payments || [];
    const transactions = d.transactions || [];
    const commissions = d.commissions || [];
    const receivable = Number(d.receivable || 0);

    box.innerHTML = `
        <h2 class="text-lg font-semibold mb-4">
            Shift Summary (Read Only)
        </h2>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>Gross Sales</div><div>₱${Number(totals.gross_sales || 0).toFixed(2)}</div>
            <div>Total Paid</div><div>₱${Number(totals.total_paid || 0).toFixed(2)}</div>
            <div>Receivables</div><div>₱${receivable.toFixed(2)}</div>
        </div>

        <h3 class="font-semibold mb-2">Payments</h3>
        ${payments.length
            ? payments.map(p => `
                <div class="flex justify-between">
                    <span>${p.payment_method.toUpperCase()}</span>
                    <span>₱${Number(p.total).toFixed(2)}</span>
                </div>
              `).join('')
            : '<div class="italic text-gray-400">No payments</div>'
        }

        <h3 class="font-semibold mt-6 mb-2">Staff Commissions</h3>
        ${commissions.length
            ? commissions.map(c => `
                <div class="flex justify-between">
                    <span>${c.full_name}</span>
                    <span>₱${Number(c.total_commission).toFixed(2)}</span>
                </div>
              `).join('')
            : '<div class="text-gray-400 italic">No commissions</div>'
        }

        <h3 class="font-semibold mt-6 mb-2">Transactions</h3>
        ${transactions.map(t => `
            <div class="flex justify-between border-b py-1">
                <span>${t.transaction_number}</span>
                <span>₱${Number(t.total_amount).toFixed(2)}</span>
            </div>
        `).join('')}
    `;
}

