// cashier-shift.js
const overlay = document.getElementById('noShiftOverlay');
const pos = document.getElementById('posContainer');
const badge = document.getElementById('shiftBadge');
const closeBtn = document.getElementById('closeShiftBtn');
const openBtn = document.getElementById('openShiftBtn');

function updateUI(open) {
    document.body.classList.toggle('overflow-hidden', !open);

    if (open) {
        overlay.classList.add('hidden');
        pos.classList.remove('opacity-50', 'pointer-events-none');

        badge.textContent = 'SHIFT OPEN';
        badge.className = `
            text-xs px-3 py-1 rounded-full font-medium
            bg-green-100 text-green-700
        `;

        closeBtn.classList.remove('hidden');

        if (typeof loadTodayAppointments === 'function') {
            loadTodayAppointments();
        }
    } else {
        overlay.classList.remove('hidden');
        pos.classList.add('opacity-50', 'pointer-events-none');

        badge.textContent = 'NO SHIFT';
        badge.className = `
            text-xs px-3 py-1 rounded-full font-medium
            bg-red-100 text-red-700
        `;

        closeBtn.classList.add('hidden');
    }
}

// CHECK SHIFT STATUS ON LOAD
fetch('../php/cashier/cashier-shift.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=status'
})
    .then(r => r.json())
    .then(d => {
        if (d.success) updateUI(d.open);
    })
    .catch(() => console.error('Failed to check shift status'));

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
            if (d.success) updateUI(true);
            else alert(d.error);
        });
});

// CLOSE SHIFT
closeBtn?.addEventListener('click', () => {
    const cash = prompt("Enter closing cash:");
    if (cash === null) return;

    fetch('../php/cashier/cashier-shift.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=close&closing_cash=${cash}`
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) updateUI(false);
            else alert(d.error);
        });
});
