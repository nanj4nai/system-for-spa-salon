const servicesGrid = document.getElementById("servicesGrid");
const continueBtn = document.getElementById("continueBtn");
const summaryBox = document.getElementById("bookingSummary");
const summaryItems = document.getElementById("summaryItems");
const summarySubtotal = document.getElementById("summarySubtotal");
const summaryTotal = document.getElementById("summaryTotal");
const vatToggle = document.getElementById("vatToggle");
const summaryVat = document.getElementById("summaryVat");

const VAT_RATE = (window.APP_SETTINGS?.vatRate || 12) / 100;

let savedServices = [];
let selectedServices = [];
let servicesChanged = false;
let originalServices = [];
let hydratedFromSession = false;

/* ---------------------------
   Helpers
--------------------------- */

const isEditMode = new URLSearchParams(window.location.search).has("edit");

function updateContinueState() {
    continueBtn.disabled = selectedServices.length === 0;
}

/* ---------------------------
   Selection Handling
--------------------------- */
function handleSelect(e) {
    const cb = e.target;

    const entry = {
        service_id: cb.dataset.service,
        variant_id: cb.dataset.variant,
        name: cb.closest("label").querySelector(".font-medium").textContent.trim(),
        price: Number(cb.dataset.price)
    };

    if (cb.checked) {
        const exists = selectedServices.some(
            s => s.service_id === entry.service_id && s.variant_id === entry.variant_id
        );
        if (!exists) selectedServices.push(entry);
    } else {
        selectedServices = selectedServices.filter(
            s => !(s.service_id === entry.service_id && s.variant_id === entry.variant_id)
        );
    }

    // ✅ compare AFTER mutation
    servicesChanged =
        JSON.stringify(
            selectedServices
                .map(s => ({ service_id: s.service_id, variant_id: s.variant_id }))
                .sort((a, b) =>
                    a.service_id - b.service_id ||
                    a.variant_id - b.variant_id
                )
        ) !== originalServices;

    updateContinueState();
    renderSummary();
}

function showScheduleResetWarning() {
    const warning = document.getElementById("scheduleResetWarning");
    if (!warning) return;

    warning.classList.remove("hidden");
}

function hideScheduleResetWarning() {
    const warning = document.getElementById("scheduleResetWarning");
    if (!warning) return;

    warning.classList.add("hidden");
}


/* ---------------------------
   Render Services
--------------------------- */

function renderServices(services) {
    servicesGrid.innerHTML = services.map(service => `
        <section class="bg-white rounded-2xl shadow-sm border p-5">
            <h3 class="text-lg font-semibold text-gray-800">
                ${service.name}
            </h3>

            ${service.description ? `
                <p class="text-sm text-gray-500 mt-1 mb-3">
                    ${service.description}
                </p>` : ""}

            <div class="space-y-3">
                    ${service.variants.map(v => {

        const price =
            v.price && Number(v.price) > 0
                ? Number(v.price)
                : Number(service.price || 0);

        return `
                    <label class="group block rounded-xl border p-4 cursor-pointer transition
                                hover:border-indigo-400 hover:bg-indigo-50/30
                                has-[:checked]:border-indigo-600
                                has-[:checked]:bg-indigo-50
                                has-[:checked]:ring-1 has-[:checked]:ring-indigo-600">

                        <div class="flex gap-3 items-start">
                            <input type="checkbox"
                                class="mt-1 service-check accent-indigo-600"
                                data-service="${service.id}"
                                data-variant="${v.id}"
                                data-price="${price.toFixed(2)}">

                            <div class="flex-1">
                                <div class="flex justify-between gap-2">
                                    <span class="font-medium text-gray-800">
                                        ${v.name}
                                    </span>
                                    <span class="font-semibold text-indigo-600 whitespace-nowrap">
                                        ₱${price.toFixed(2)}
                                    </span>
                                </div>

                                <div class="text-xs text-gray-500 mt-0.5">
                                    ${v.duration} mins
                                </div>
                            </div>
                        </div>
                    </label>`;
    }).join("")}
            </div>
        </section>
    `).join("");

    document.querySelectorAll(".service-check").forEach(cb => {
        cb.addEventListener("change", handleSelect);
    });

    if (!hydratedFromSession) {
        selectedServices = [];

        savedServices.forEach(s => {
            selectServiceVariant(s.service_id, s.variant_id);
        });

        hydratedFromSession = true;
    }


    renderSummary();
    updateContinueState();

}

/* ---------------------------
   Booking Summary
--------------------------- */
function renderSummary() {
    if (!selectedServices.length) {
        summaryItems.innerHTML = "";
        summarySubtotal.textContent = "₱0.00";
        summaryVat.textContent = "₱0.00";
        summaryTotal.textContent = "₱0.00";
        summaryBox.classList.add("hidden");
        return;
    }

    summaryBox.classList.remove("hidden");
    const unique = Object.values(
        selectedServices.reduce((acc, s) => {
            acc[`${s.service_id}-${s.variant_id}`] = s;
            return acc;
        }, {})
    );

    summaryItems.innerHTML = unique.map(s => `
        <li class="flex justify-between gap-3">
            <span class="truncate">${s.name}</span>
            <span>₱${s.price.toFixed(2)}</span>
        </li>
    `).join("");

    const subtotal = unique.reduce((sum, s) => sum + s.price, 0);
    const vat = subtotal * VAT_RATE;
    const total = subtotal + vat;

    summarySubtotal.textContent = `₱${subtotal.toFixed(2)}`;
    summaryVat.textContent = `₱${vat.toFixed(2)}`;
    summaryTotal.textContent = `₱${total.toFixed(2)}`;

}

function selectServiceVariant(serviceId, variantId) {
    const checkbox = document.querySelector(
        `.service-check[data-service="${serviceId}"][data-variant="${variantId}"]`
    );

    if (!checkbox) return;

    checkbox.checked = true;

    const entry = {
        service_id: serviceId,
        variant_id: variantId,
        name: checkbox.closest("label").querySelector(".font-medium").textContent.trim(),
        price: Number(checkbox.dataset.price)
    };

    const exists = selectedServices.some(
        s => s.service_id === entry.service_id && s.variant_id === entry.variant_id
    );

    if (!exists) {
        selectedServices.push(entry);
    }
}

async function loadSavedServices() {
    const res = await fetch("api/get-booking-services.php");
    const data = await res.json();

    savedServices = data.services || [];
    originalServices = JSON.stringify(
        savedServices
            .map(s => ({ service_id: s.service_id, variant_id: s.variant_id }))
            .sort((a, b) =>
                a.service_id - b.service_id ||
                a.variant_id - b.variant_id
            )
    );

}


/* ---------------------------
   Continue
--------------------------- */
continueBtn.addEventListener("click", async () => {
    if (!selectedServices.length) {
        alert("Please select at least one service.");
        return;
    }

    const res = await fetch("api/save-services.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            services: selectedServices,
            reset_schedule: servicesChanged // 👈 NEW
        })
    });

    const data = await res.json();

    if (data.success) {
        allowLeave = true;
        window.location.href = "step3.php";
    }
});


async function init() {
    if (!isEditMode) {
        await loadSavedServices(); // only restore if NOT editing
    } else {
        savedServices = []; // 🔥 ignore old session
    }

    const res = await fetch("api/get-services.php");
    const services = await res.json();

    renderServices(services);
}

init();

