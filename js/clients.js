const table = document.getElementById("clientsTable");
const searchInput = document.getElementById("searchInput");
let clients = [];

fetchClients();

async function fetchClients() {
    const res = await fetch("php/clients.php?action=list");
    clients = await res.json();
    render();
}

function render() {
    const q = searchInput.value.toLowerCase();
    table.innerHTML = "";

    clients
        .filter(c =>
            c.full_name.toLowerCase().includes(q) ||
            (c.contact_number || "").includes(q) ||
            (c.email || "").toLowerCase().includes(q)
        )
        .forEach(c => {
            const tr = document.createElement("tr");
            tr.className = "cursor-pointer hover:bg-blue-50 dark:hover:bg-gray-700 transition";

            tr.onclick = () => {
                window.location.href = `client-profile.php?id=${c.id}`;
            };

            tr.innerHTML = `
                <td class="px-4 py-3 font-medium truncate">
                    ${c.full_name}
                </td>

                <td class="px-4 py-3 hidden sm:table-cell">
                    ${c.contact_number ?? "-"}<br>
                    <span class="text-xs text-gray-500 truncate">${c.email ?? ""}</span>
                </td>

                <td class="px-4 py-3 text-gray-500 hidden md:table-cell truncate">
                    ${c.notes ?? "-"}
                </td>

                <td class="px-4 py-3 hidden sm:table-cell">
                    ${c.created_at_formatted}
                </td>

                <td class="px-4 py-3 text-center whitespace-nowrap">
                    <button class="text-blue-600 hover:underline"
                        onclick="event.stopPropagation(); openClientModal(${c.id})">
                        Edit
                    </button>
                </td>
            `;

            table.appendChild(tr);
        });
}

searchInput.addEventListener("input", render);

// toast
function showSuccessToast(msg, duration = 3000) {
    const t = document.getElementById("successToast");
    t.textContent = msg;
    t.classList.remove("opacity-0", "pointer-events-none");
    setTimeout(() => t.classList.add("opacity-0", "pointer-events-none"), duration);
}

const modal = document.getElementById("clientModal");
const form = document.getElementById("clientForm");

function openClientModal(id = null) {
    modal.classList.remove("hidden");
    modal.classList.add("flex");

    form.reset();
    document.getElementById("clientId").value = "";

    if (id) {
        document.getElementById("clientModalTitle").textContent = "Edit Client";
        const c = clients.find(x => x.id == id);

        if (c) {
            document.getElementById("clientId").value = c.id;
            document.getElementById("clientName").value = c.full_name;
            document.getElementById("clientContact").value = c.contact_number ?? "";
            document.getElementById("clientEmail").value = c.email ?? "";
            document.getElementById("clientAddress").value = c.address ?? "";
            document.getElementById("clientNotes").value = c.notes ?? "";
        }
    } else {
        document.getElementById("clientModalTitle").textContent = "Add Client";
    }
}

function closeClientModal() {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

// SAVE CLIENT
form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(form);
    const res = await fetch("php/clients.php", {
        method: "POST",
        body: formData
    });

    const data = await res.json();

    if (!data.success) {
        showSuccessToast(data.error || "Failed to save client", 4000);
        return;
    }

    closeClientModal();
    showSuccessToast("Client saved successfully");
    fetchClients();
});
