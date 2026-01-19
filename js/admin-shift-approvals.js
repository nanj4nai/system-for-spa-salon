const table = document.getElementById("shiftTable");

function loadShifts() {
    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=list"
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success || !d.shifts.length) {
            table.innerHTML = `
                <tr>
                    <td colspan="6" class="py-6 text-center text-gray-400">
                        No pending shift approvals
                    </td>
                </tr>`;
            return;
        }

        table.innerHTML = d.shifts.map(s => `
            <tr class="border-b">
                <td class="py-3">${s.username}</td>
                <td>${new Date(s.opened_at).toLocaleString()}</td>
                <td>₱${Number(s.opening_cash).toFixed(2)}</td>
                <td>₱${Number(s.closing_cash).toFixed(2)}</td>
                <td>
                    <span class="px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-700">
                        Pending
                    </span>
                </td>
                <td class="text-right space-x-2">
                    <button
                        onclick="approve(${s.id})"
                        class="px-3 py-1 text-xs bg-green-600 text-white rounded">
                        Approve
                    </button>
                    <button
                        onclick="reject(${s.id})"
                        class="px-3 py-1 text-xs bg-red-600 text-white rounded">
                        Reject
                    </button>
                </td>
            </tr>
        `).join("");
    });
}

function approve(id) {
    if (!confirm("Approve and close this shift?")) return;

    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=approve&shift_id=${id}`
    })
    .then(() => loadShifts());
}

function reject(id) {
    if (!confirm("Reject this close request?")) return;

    fetch("php/shift-approvals.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `action=reject&shift_id=${id}`
    })
    .then(() => loadShifts());
}

loadShifts();
