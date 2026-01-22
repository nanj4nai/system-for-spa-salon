const overlay = document.getElementById('noShiftOverlay');
const pendingOverlay = document.getElementById('pendingApprovalOverlay');
const pos = document.getElementById('possContainer');
const badge = document.getElementById('shiftBadge');
const closeBtn = document.getElementById('closeShiftBtn');
const openBtn = document.getElementById('openShiftBtn');
const closeShiftModal = document.getElementById('closeShiftModal');
const closeShiftCash = document.getElementById('closeShiftCash');
const closeShiftRemarks = document.getElementById('closeShiftRemarks');
const cancelCloseShiftBtn = document.getElementById('cancelCloseShiftBtn');
const confirmCloseShiftBtn = document.getElementById('confirmCloseShiftBtn');
const finalConfirmShiftModal = document.getElementById('finalConfirmShiftModal');
const cancelFinalConfirmBtn = document.getElementById('cancelFinalConfirmBtn');
const confirmFinalSubmitBtn = document.getElementById('confirmFinalSubmitBtn');

let lastKnownStatus = null;
let statusPoller = null;

/* =====================
   STATUS POLLING
===================== */
function startStatusPolling() {
    if (statusPoller) return;

    statusPoller = setInterval(() => {
        fetch('../php/cashier/cashier-shift.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=status'
        })
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;

                const currentStatus = d.status;
                const shift = d.shift || {};

                if (currentStatus === lastKnownStatus) return;

                if (lastKnownStatus === 'pending_close' && currentStatus === 'closed') {
                    showToast(
                        shift.remarks?.includes('Force')
                            ? '⚠️ Shift force-closed by admin'
                            : '✅ Shift approved and closed',
                        'success'
                    );
                    stopPolling();
                    setState('none');
                }

                if (lastKnownStatus === 'pending_close' && currentStatus === 'open') {
                    showToast('❌ Shift close request rejected by admin', 'error');
                    stopPolling();
                    setState('open');
                }

                lastKnownStatus = currentStatus;
            });
    }, 3000);
}

function stopPolling() {
    clearInterval(statusPoller);
    statusPoller = null;
}

/* =====================
   SHIFT STATE UI
===================== */
function setState(state) {
    overlay?.classList.add('hidden');
    pendingOverlay?.classList.add('hidden');

    if (state === "open") {
        pos?.classList.remove('opacity-50', 'pointer-events-none');

        badge.textContent = 'SHIFT OPEN';
        badge.className = 'bg-green-100 text-green-700 px-3 py-1 rounded-full';

        closeBtn?.classList.remove('hidden');
        openBtn?.classList.add('hidden');

        stopPolling();
        loadTodayAppointments?.();
    }

    else if (state === "pending_close") {
        pos?.classList.add('opacity-50', 'pointer-events-none');
        pendingOverlay?.classList.remove('hidden');

        badge.textContent = 'SHIFT PENDING APPROVAL';
        badge.className = 'bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full';

        closeBtn?.classList.add('hidden');
        openBtn?.classList.add('hidden');

        lastKnownStatus = 'pending_close';
        startStatusPolling();
    }

    else {
        overlay?.classList.remove('hidden');
        pos?.classList.add('opacity-50', 'pointer-events-none');

        badge.textContent = 'NO SHIFT';
        badge.className = 'bg-red-100 text-red-700 px-3 py-1 rounded-full';

        openBtn?.classList.remove('hidden');
        closeBtn?.classList.add('hidden');

        stopPolling();
    }
}

/* =====================
   INITIAL STATUS
===================== */
fetch('../php/cashier/cashier-shift.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=status'
})
    .then(r => r.json())
    .then(d => d.success && setState(d.status));

/* =====================
   OPEN SHIFT
===================== */
openBtn?.addEventListener('click', () => {
    const cash = document.getElementById('openingCash')?.value || 0;

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=open&opening_cash=${cash}`
    })
        .then(r => r.json())
        .then(d => d.success ? setState("open") : alert(d.error));
});

/* =====================
   CLOSE SHIFT
===================== */
closeBtn?.addEventListener('click', async () => {
    const res = await fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=can_close'
    });

    const d = await res.json();
    if (!d.success) {
        showToast(d.error || "Unable to verify shift status", "error");
        return;
    }

    if (!d.can_close) {
        showToast(
            `Cannot close shift — ${d.unsettled} unsettled transaction(s) remaining`,
            "error"
        );
        return;
    }

    closeShiftCash.value = '';
    closeShiftRemarks.value = '';

    window.lastShiftSummary = d.summary;

    renderShiftSummary(d.summary);
    renderShiftPaymentBreakdown(d.summary.payments || []);

    closeShiftModal.classList.remove('hidden');
});


/* =====================
   CANCEL CLOSE SHIFT
===================== */
cancelCloseShiftBtn?.addEventListener('click', () => {
    closeShiftModal.classList.add('hidden');

    document.getElementById('sumExpectedCash').textContent = '₱0.00';
    document.getElementById('sumDeclaredCash').textContent = '₱0.00';
    document.getElementById('sumVariance').textContent = '₱0.00';

    const box = document.getElementById('paymentBreakdowned');
    if (box) box.innerHTML = '';

    window.lastShiftSummary = null;
});

/* =====================
   CONFIRM CLOSE SHIFT
===================== */
confirmCloseShiftBtn?.addEventListener('click', () => {
    const cash = closeShiftCash.value.trim();

    if (cash === '') {
        showToast("Closing cash is required", "error");
        closeShiftCash.focus();
        return;
    }

    if (isNaN(cash) || Number(cash) < 0) {
        showToast("Closing cash must be a valid amount", "error");
        closeShiftCash.focus();
        return;
    }

    if (!window.lastShiftSummary) return;

    const payments = window.lastShiftSummary.payments || [];

    const expectedCash = payments
        .filter(p => p.payment_method === 'cash')
        .reduce((sum, p) => sum + Number(p.total), 0);

    const declared = Number(cash);
    const variance = declared - expectedCash;

    document.getElementById('finalExpectedCash').textContent =
        `₱${expectedCash.toFixed(2)}`;

    document.getElementById('finalDeclaredCash').textContent =
        `₱${declared.toFixed(2)}`;

    const varianceEl = document.getElementById('finalVariance');
    varianceEl.textContent = `₱${variance.toFixed(2)}`;
    varianceEl.className =
        variance === 0
            ? "text-green-600"
            : variance < 0
                ? "text-red-600"
                : "text-amber-600";

    finalConfirmShiftModal.classList.remove('hidden');
});

cancelFinalConfirmBtn?.addEventListener('click', () => {
    finalConfirmShiftModal.classList.add('hidden');
});

confirmFinalSubmitBtn?.addEventListener('click', () => {
    const cash = closeShiftCash.value;
    const remarks = closeShiftRemarks.value;

    confirmFinalSubmitBtn.disabled = true;

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=request_close&closing_cash=${cash}&remarks=${encodeURIComponent(remarks)}`
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                finalConfirmShiftModal.classList.add('hidden');
                closeShiftModal.classList.add('hidden');

                showToast("Shift submitted for admin approval");
                setState("pending_close");
                startStatusPolling();
            } else {
                alert(d.error);
            }
        })
        .finally(() => {
            confirmFinalSubmitBtn.disabled = false;
        });
});

/* =====================
   SHIFT SUMMARY (NO BREAKDOWN)
===================== */
function renderShiftSummary(d) {
    if (!d) return;

    const totals = d.totals || {};
    const payments = d.payments || [];

    document.getElementById('sumTransactions').textContent =
        totals.total_transactions || 0;

    document.getElementById('sumGross').textContent =
        `₱${Number(totals.gross_sales || 0).toFixed(2)}`;

    document.getElementById('sumPaid').textContent =
        `₱${Number(totals.total_paid || 0).toFixed(2)}`;

    const expectedCash = payments
        .filter(p => p.payment_method === 'cash')
        .reduce((sum, p) => sum + Number(p.total), 0);

    document.getElementById('sumExpectedCash').textContent =
        `₱${expectedCash.toFixed(2)}`;

    const declared = Number(closeShiftCash.value || 0);
    document.getElementById('sumDeclaredCash').textContent =
        `₱${declared.toFixed(2)}`;

    const variance = declared - expectedCash;
    const varianceEl = document.getElementById('sumVariance');

    varianceEl.textContent = `₱${variance.toFixed(2)}`;
    varianceEl.className =
        variance === 0
            ? "text-green-600"
            : variance < 0
                ? "text-red-600"
                : "text-amber-600";
}

function renderShiftPaymentBreakdown(payments) {
    const box = document.getElementById('paymentBreakdowned');
    if (!box) return;

    box.innerHTML = '';

    if (!payments.length) {
        box.innerHTML =
            `<div class="italic text-gray-400">No payments recorded</div>`;
        return;
    }

    payments.forEach(p => {
        box.innerHTML += `
            <div class="flex justify-between">
                <span>${p.payment_method.toUpperCase()}</span>
                <span>₱${Number(p.total).toFixed(2)}</span>
            </div>
        `;
    });
}


/* =====================
   LIVE CASH INPUT
===================== */
closeShiftCash?.addEventListener('input', () => {
    if (closeShiftModal.classList.contains('hidden')) return;
    if (window.lastShiftSummary) renderShiftSummary(window.lastShiftSummary);
});

/* =====================
   TOAST
===================== */
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = `
        fixed bottom-6 right-6 z-50
        px-4 py-3 rounded-lg shadow-lg text-sm text-white
        ${type === 'error' ? 'bg-red-600' : 'bg-emerald-600'}
    `;

    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 3500);
}
