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
let lastKnownStatus = null;
let statusPoller = null;

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

                // ⛔ nothing changed
                if (currentStatus === lastKnownStatus) return;

                // ✅ APPROVED / FORCE CLOSED
                if (lastKnownStatus === 'pending_close' && currentStatus === 'closed') {
                    showToast(
                        shift.remarks && shift.remarks.includes('Force')
                            ? '⚠️ Shift force-closed by admin'
                            : '✅ Shift approved and closed',
                        'success'
                    );

                    stopPolling();
                    setState('none');
                }

                // ❌ REJECTED
                if (lastKnownStatus === 'pending_close' && currentStatus === 'open') {
                    showToast(
                        '❌ Shift close request rejected by admin',
                        'error'
                    );

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

function setState(state) {

    // RESET OVERLAYS
    overlay?.classList.add('hidden');
    pendingOverlay?.classList.add('hidden');

    if (state === "open") {
        pos?.classList.remove('opacity-50', 'pointer-events-none');

        badge.textContent = 'SHIFT OPEN';
        badge.className = 'bg-green-100 text-green-700 px-3 py-1 rounded-full';

        closeBtn?.classList.remove('hidden');
        openBtn?.classList.add('hidden');

        stopPolling();

        if (typeof loadTodayAppointments === 'function') {
            loadTodayAppointments();
        }
    }

    else if (state === "pending_close") {
        // 🔒 FULL PAGE LOCK
        pos?.classList.add('opacity-50', 'pointer-events-none');

        pendingOverlay?.classList.remove('hidden');

        badge.textContent = 'SHIFT PENDING APPROVAL';
        badge.className = 'bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full';

        closeBtn?.classList.add('hidden');
        openBtn?.classList.add('hidden');

        lastKnownStatus = 'pending_close';
        startStatusPolling();
    }

    else { // none / closed
        overlay?.classList.remove('hidden');
        pos?.classList.add('opacity-50', 'pointer-events-none');

        badge.textContent = 'NO SHIFT';
        badge.className = 'bg-red-100 text-red-700 px-3 py-1 rounded-full';

        openBtn?.classList.remove('hidden');
        closeBtn?.classList.add('hidden');

        stopPolling();
    }
}

// STATUS CHECK
fetch('../php/cashier/cashier-shift.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=status'
})
    .then(r => r.json())
    .then(d => {
        if (!d.success) return;
        setState(d.status);
    });

// OPEN SHIFT
openBtn?.addEventListener('click', () => {
    const cash = document.getElementById('openingCash')?.value || 0;

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=open&opening_cash=${cash}`
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) setState("open");
            else alert(d.error);
        });
})

closeBtn?.addEventListener('click', () => {
    closeShiftCash.value = '';
    closeShiftRemarks.value = '';
    closeShiftModal.classList.remove('hidden');
});

cancelCloseShiftBtn?.addEventListener('click', () => {
    closeShiftModal.classList.add('hidden');
});
confirmCloseShiftBtn?.addEventListener('click', () => {
    const cash = closeShiftCash.value;
    const remarks = closeShiftRemarks.value;

    if (cash === '' || Number(cash) < 0) {
        alert("Please enter valid closing cash");
        return;
    }

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=request_close&closing_cash=${cash}&remarks=${encodeURIComponent(remarks)}`
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                closeShiftModal.classList.add('hidden');
                alert("Shift submitted for admin approval");
                setState("pending_close");
                startStatusPolling();
            } else {
                alert(d.error);
            }
        });
});

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

    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3500);
}
