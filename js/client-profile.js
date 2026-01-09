const visitTable = document.getElementById("visitTable");
const clientId = new URLSearchParams(window.location.search).get("id");

fetchVisits();

async function fetchVisits() {
    const res = await fetch(`php/clients.php?action=visits&id=${clientId}`);
    const visits = await res.json();

    visitTable.innerHTML = "";

    document.getElementById("totalVisits").textContent = visits.length;

    document.getElementById("lastVisit").textContent =
        visits.length ? visits[0].appointment_date : "—";

    visits.forEach(v => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td class="px-4 py-2">${v.appointment_date}</td>
            <td class="px-4 py-2">${v.services}</td>
            <td class="px-4 py-2">₱${Number(v.total_amount).toFixed(2)}</td>
            <td class="px-4 py-2 capitalize">${v.status}</td>
        `;
        visitTable.appendChild(tr);
    });
}
