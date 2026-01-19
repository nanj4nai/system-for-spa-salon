const overlay = document.getElementById('noShiftOverlay');
const pos = document.getElementById('posContainer');
const badge = document.getElementById('shiftBadge');
const closeBtn = document.getElementById('closeShiftBtn');
const openBtn = document.getElementById('openShiftBtn');

function setState(state) {

    if (state === "open") {
        overlay.classList.add('hidden');
        pos.classList.remove('opacity-50', 'pointer-events-none');

        badge.textContent = 'SHIFT OPEN';
        badge.className = 'bg-green-100 text-green-700 px-3 py-1 rounded-full';

        closeBtn.classList.remove('hidden');
        openBtn.classList.add('hidden');

        // 👈 IMPORTANT
        if (typeof loadTodayAppointments === 'function') {
            loadTodayAppointments();
        }
    }

    else if (state === "pending_close") {
        // 🔒 Block actions but allow viewing
        overlay.classList.add('hidden');
        pos.classList.add('opacity-50', 'pointer-events-none');

        badge.textContent = 'SHIFT PENDING APPROVAL';
        badge.className = 'bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full';

        closeBtn.classList.add('hidden');
        openBtn.classList.add('hidden');

        // 👈 STILL LOAD APPOINTMENTS
        if (typeof loadTodayAppointments === 'function') {
            loadTodayAppointments();
        }
    }

    else {
        overlay.classList.remove('hidden');
        pos.classList.add('opacity-50', 'pointer-events-none');

        badge.textContent = 'NO SHIFT';
        badge.className = 'bg-red-100 text-red-700 px-3 py-1 rounded-full';

        openBtn.classList.remove('hidden');
        closeBtn.classList.add('hidden');
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
});

// REQUEST CLOSE SHIFT
closeBtn?.addEventListener('click', () => {
    const cash = prompt("Enter closing cash:");
    if (cash === null) return;

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=request_close&closing_cash=${cash}`
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                alert("Shift submitted for admin approval");
                setState("pending_close");
            } else {
                alert(d.error);
            }
        });
});
