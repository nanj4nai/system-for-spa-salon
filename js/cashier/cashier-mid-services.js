// cashier-services.js
const serviceModal = document.getElementById('serviceModal');
const addServiceBtn = document.getElementById('addServiceBtn');
const serviceSelect = document.getElementById('serviceSelect');
const confirmAddServiceBtn = document.getElementById("confirmAddServiceBtn");
const cancelServiceBtn = document.getElementById("cancelServiceBtn");
const staffSelect = document.getElementById("staffSelect");
const variantSelect = document.getElementById("variantSelect");
let editingAppointmentServiceId = null;
let pendingRemoveAppointmentServiceId = null;
let pendingRemoveServiceName = "";

function applyServiceLockUI(container) {

    // 🔥 ALWAYS reset first
    container.classList.remove("opacity-50");

    container.querySelectorAll("button").forEach(btn => {
        btn.disabled = false;
        btn.classList.remove("opacity-50", "cursor-not-allowed");
    });

    // 🔒 Apply lock ONLY if locked
    if (!CashierState.transactionLocked) return;

    container.classList.add("opacity-50");

    container.querySelectorAll("button").forEach(btn => {
        btn.disabled = true;
        btn.classList.add("opacity-50", "cursor-not-allowed");
    });
}



function hasDuplicateService(serviceId, variantId, excludeAppointmentServiceId = null) {
    const cards = document.querySelectorAll(".service-card");

    for (const card of cards) {
        const id = card.dataset.appointmentServiceId;
        if (excludeAppointmentServiceId && String(id) === String(excludeAppointmentServiceId)) {
            continue; // ignore self when editing
        }

        const sId = card.dataset.serviceId;
        const vId = card.dataset.variantId || "";

        // service WITHOUT variants
        if (!variantId && !vId && String(sId) === String(serviceId)) {
            return true;
        }

        // service WITH variants
        if (
            variantId &&
            String(sId) === String(serviceId) &&
            String(vId) === String(variantId)
        ) {
            return true;
        }
    }
    return false;
}

addServiceBtn.addEventListener('click', () => {
    if (CashierState.transactionLocked) {
        showToast("Transaction is locked — services cannot be modified", "error");
        return;
    }
    serviceSelect.disabled = false;
    variantSelect.disabled = false;

    serviceSelect.classList.remove("opacity-50", "cursor-not-allowed");
    variantSelect.classList.remove("opacity-50", "cursor-not-allowed");
    // 🛑 GUARD: do not reset while editing
    if (editingAppointmentServiceId) {
        showToast(
            "Finish editing the current service first",
            "error"
        );
        return;
    }

    // ✅ ADD MODE ONLY
    serviceSelect.value = "";
    staffSelect.value = "";
    variantSelect.innerHTML = "";

    document.getElementById("variantWrapper").classList.add("hidden");
    document.getElementById("serviceProductsPreview").classList.add("hidden");
    document.getElementById("serviceProductsPreview").innerHTML = "";

    serviceModal.classList.remove('hidden');
    serviceModal.classList.add('flex');

    loadServices();
    loadStaff();
});



function loadServices() {
    serviceSelect.innerHTML = `<option>Loading services…</option>`;

    return fetch('../php/cashier/center/get-services.php')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;

            serviceSelect.innerHTML = `
                <option value="">Select service</option>
                ${d.services.map(s => `
                    <option value="${s.id}">
                        ${s.name} — ₱${s.base_price}
                    </option>
                `).join('')}
            `;
        });
}


function loadAppointmentServices() {
    if (CashierState.activeTransactionId) return;

    if (!CashierState.activeAppointmentId) {
        console.warn("No active appointment — skipping loadAppointmentServices");
        return;
    }

    const container = document.getElementById("serviceList");
    container.innerHTML = `<div class="text-xs text-gray-400">Loading services…</div>`;

    fetch(`../php/cashier/center/get-appointment-services.php?appointment_id=${CashierState.activeAppointmentId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.services.length) {
                container.innerHTML =
                    `<div class="text-sm text-gray-400 italic">No services added yet</div>`;
                return;
            }

            container.innerHTML = d.services.map(s => `
                <div class="p-4 rounded-xl border service-card"
                    data-appointment-service-id="${s.id}"
                    data-service-id="${s.service_id}"
                    data-variant-id="${s.variant_id || ""}">

                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium">${s.service_name}</div>
                            ${s.variant_name
                    ? `<div class="text-xs text-gray-400">Variant: ${s.variant_name}</div>`
                    : ""}
                            <div class="text-xs text-gray-500">
                                Staff: ${s.staff_name}
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button data-mutation
                                class="text-xs px-2 py-1 rounded bg-gray-200 dark:bg-gray-600"
                                data-action="edit-service"
                                data-id="${s.id}">
                                Edit
                            </button>

                            <button data-mutation
                                class="text-xs px-2 py-1 rounded bg-red-500 text-white"
                                data-action="remove-service"
                                data-id="${s.id}">
                                Remove
                            </button>
                        </div>
                    </div>
                    ${s.products && s.products.length ? `
                        <div class="mt-3 space-y-1 text-xs">
                           ${s.products.map(p => {

                        // reusable items
                        if (p.product_type === "reusable") {
                            return `
                                        <div class="flex justify-between items-center">
                                            <span>${p.name}</span>
                                            <span class="text-gray-400 italic">
                                                Reusable (included)
                                            </span>
                                        </div>
                                    `;
                        }

                        // consumable / one_time
                        return `
                                    <div class="flex justify-between items-center">
                                        <span>${p.name}</span>
                                        <span class="text-gray-500">
                                            ${p.quantity_used}${p.unit}
                                            <span class="opacity-60 ml-1">
                                                • ₱${p.price} / pack
                                            </span>
                                        </span>
                                    </div>
                                `;
                    }).join("")}

                        </div>
                    ` : `
                        <div class="mt-3 text-xs italic text-gray-400">
                            No products used
                        </div>
                    `}
                </div>
            `).join("");

            // 🔒 APPLY LOCK VISUALS
            applyServiceLockUI(container);
            updateExtraProductAvailability();
        });

}
function loadStaff() {
    staffSelect.innerHTML = `<option>Loading staff…</option>`;

    return fetch('../php/cashier/center/get-staff.php')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;

            staffSelect.innerHTML = `
                <option value="">Select staff</option>
                ${d.staff.map(st => `
                    <option value="${st.id}">
                        ${st.full_name} (${st.role_name})
                    </option>
                `).join("")}
            `;
        });
}

serviceSelect.addEventListener("change", () => {
    if (serviceSelect.disabled) return;
    const serviceId = serviceSelect.value;

    document.getElementById("variantWrapper").classList.add("hidden");
    variantSelect.innerHTML = "";

    if (!serviceId) return;

    // ✅ NEW: show products preview
    loadServiceProducts(serviceId);

    // Existing variant loader
    fetch(`../php/cashier/center/get-service-variants.php?service_id=${serviceId}`)
        .then(r => r.json())
        .then(d => {
            if (!d.success || !d.variants.length) return;

            variantSelect.innerHTML = d.variants.map(v => `
                <option value="${v.id}">
                    ${v.name} — ₱${v.price}
                </option>
            `).join("");

            document.getElementById("variantWrapper").classList.remove("hidden");
        });
});


cancelServiceBtn.addEventListener("click", () => {
    serviceSelect.disabled = false;
    variantSelect.disabled = false;
    serviceModal.classList.add("hidden");
    editingAppointmentServiceId = null;
    unlockAddService();
});

confirmAddServiceBtn.addEventListener("click", () => {
    if (!CashierState.activeAppointmentId) {
        showToast("Select an appointment first", "error");
        return;
    }

    if (!serviceSelect.value || !staffSelect.value) {
        showToast("Service and staff are required", "error");
        return;
    }

    confirmAddServiceBtn.disabled = true;

    const url = editingAppointmentServiceId
        ? "../php/cashier/center/update-appointment-service.php"
        : "../php/cashier/center/add-appointment-service.php";

    const payload = {
        appointment_id: CashierState.activeAppointmentId,
        service_id: serviceSelect.value,
        variant_id: variantSelect?.value || null,
        staff_id: staffSelect.value,
    };

    if (editingAppointmentServiceId) {
        payload.appointment_service_id = editingAppointmentServiceId;
    }
    const serviceId = serviceSelect.value;
    const variantId = variantSelect?.value || null;
    if (
        hasDuplicateService(
            serviceId,
            variantId,
            editingAppointmentServiceId
        )
    ) {
        showToast(
            variantId
                ? "This service variant is already added"
                : "This service is already added",
            "error"
        );
        confirmAddServiceBtn.disabled = false;
        return;
    }

    fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast(d.error, "error");
                return;
            }

            const highlightId =
                d.appointment_service_id || editingAppointmentServiceId;

            showToast(editingAppointmentServiceId ? "Service updated" : "Service added");
            serviceModal.classList.add("hidden");

            editingAppointmentServiceId = null;
            unlockAddService();
            loadAppointmentServices();
            updateExtraProductAvailability();

            if (CashierState.activeTransactionId) {
                loadTransaction(CashierState.activeTransactionId); // 🔥 FIX
            }

            loadTodayAppointments();

            setTimeout(() => {
                highlightActiveService(highlightId);
            }, 100);
        })
        .finally(() => {
            confirmAddServiceBtn.disabled = false;
        });
});

document.getElementById("serviceList").addEventListener("click", e => {
    if (CashierState.transactionLocked) {
        showToast("Transaction is locked", "error");
        return;
    }
    const btn = e.target.closest("[data-action]");
    if (!btn) return;

    // 🔥 FORCE appointment context
    if (!CashierState.activeAppointmentId) {
        showToast("No active appointment selected", "error");
        return;
    }

    const serviceId = btn.dataset.id;
    const action = btn.dataset.action;

    if (action === "edit-service") {
        openEditService(serviceId);
    }

    if (action === "remove-service") {
        removeService(serviceId);
    }
});

function removeService(appointmentServiceId) {
    pendingRemoveAppointmentServiceId = appointmentServiceId;

    const card = document.querySelector(
        `.service-card[data-appointment-service-id="${appointmentServiceId}"]`
    );

    let serviceName =
        card?.querySelector(".font-medium")?.textContent || "Selected service";

    let variantText = card?.querySelector(".text-xs.text-gray-400")?.textContent;

    if (variantText && variantText.toLowerCase().includes("variant")) {
        serviceName += `\n${variantText}`;
    }

    document.getElementById("removeServiceName").innerHTML =
        serviceName.replace("\n", "<br>");

    const modal = document.getElementById("removeServiceModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

document.getElementById("cancelRemoveServiceBtn")
    .addEventListener("click", () => {

        pendingRemoveAppointmentServiceId = null;
        pendingRemoveServiceName = "";

        const modal = document.getElementById("removeServiceModal");
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    });

document.getElementById("confirmRemoveServiceBtn")
    .addEventListener("click", () => {

        if (!pendingRemoveAppointmentServiceId) return;

        const appointmentServiceId = pendingRemoveAppointmentServiceId;

        pendingRemoveAppointmentServiceId = null;
        pendingRemoveServiceName = "";

        const modal = document.getElementById("removeServiceModal");
        modal.classList.add("hidden");
        modal.classList.remove("flex");

        fetch("../php/cashier/center/remove-appointment-service.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                appointment_service_id: appointmentServiceId
            })
        })
            .then(r => r.json())
            .then(d => {
                if (!d.success) {
                    showToast(d.error, "error");
                    return;
                }

                showToast("Service removed");
                loadAppointmentServices();
                updateExtraProductAvailability();
                if (CashierState.activeTransactionId) {
                    loadTransaction(CashierState.activeTransactionId); // 🔥 FIX
                }
                loadTodayAppointments();
            });
    });

function highlightActiveService(id) {
    document.querySelectorAll(".service-card").forEach(card => {
        if (card.dataset.appointmentServiceId === String(id)) {
            card.classList.add(
                "ring-1",
                "ring-green-500",
                "bg-green-50",
                "dark:bg-green-900/30"
            );
        } else {
            card.classList.remove(
                "ring-1",
                "ring-green-500",
                "bg-green-50",
                "dark:bg-green-900/30"
            );
        }
    });
}


function setActiveContext(text) {
    const el = document.getElementById("activeContext");
    if (!el) return;

    el.innerHTML = `
        <span class="font-medium">${text}</span>
        <span class="ml-2 text-[10px] opacity-70">
            Appointment ID: ${CashierState.activeAppointmentId}
        </span>
    `;
    el.classList.remove("hidden");

}

async function openEditService(appointmentServiceId) {
    editingAppointmentServiceId = appointmentServiceId;

    lockAddService("You are editing a service");

    // reset UI
    document.getElementById("variantWrapper").classList.add("hidden");
    variantSelect.innerHTML = "";

    // IMPORTANT: do NOT hide this — we will render into it
    const preview = document.getElementById("serviceProductsPreview");
    preview.innerHTML = "";
    preview.classList.remove("hidden");

    // fetch service data
    const res = await fetch(
        `../php/cashier/center/get-appointment-service.php?id=${appointmentServiceId}`
    );
    const d = await res.json();
    if (!d.success) return;

    const s = d.service;
    const isBookingService = s.is_booking_service === true;

    // 🔥 RENDER PRODUCT USAGE EDITOR (THIS WAS MISSING)
    renderServiceProductUsageEditor(
        s.id,
        s.products || [],
        d.transaction_status
    );

    // 🔥 LOAD DROPDOWNS FIRST
    await Promise.all([
        loadServices(),
        loadStaff()
    ]);

    // 🔥 SET VALUES
    serviceSelect.value = String(s.service_id);
    staffSelect.value = String(s.employee_id);

    // variants
    const vr = await fetch(
        `../php/cashier/center/get-service-variants.php?service_id=${s.service_id}`
    );
    const v = await vr.json();

    if (v.success && v.variants.length) {
        variantSelect.innerHTML = v.variants.map(opt => `
        <option value="${opt.id}">
            ${opt.name} — ₱${opt.price}
        </option>
    `).join("");

        variantSelect.value = s.variant_id || "";
        document.getElementById("variantWrapper").classList.remove("hidden");
    }

    // 🔒 NOW LOCK IF ONLINE
    if (isBookingService) {
        serviceSelect.disabled = true;
        variantSelect.disabled = true;

        serviceSelect.classList.add("opacity-50", "cursor-not-allowed");
        variantSelect.classList.add("opacity-50", "cursor-not-allowed");

        showToast(
            "Online booking service — service and variant cannot be changed",
            "info"
        );
    } else {
        serviceSelect.disabled = false;
        variantSelect.disabled = false;

        serviceSelect.classList.remove("opacity-50", "cursor-not-allowed");
        variantSelect.classList.remove("opacity-50", "cursor-not-allowed");
    }


    if (d.service.is_booking_service) {
        showToast(
            "This service came from an online booking. Only staff and product usage can be edited.",
            "info"
        );
    }

    serviceModal.classList.remove("hidden");
    serviceModal.classList.add("flex");
}

function resetExtraProductsUI() {
    const box = document.getElementById("extraProductList");
    if (box) {
        box.innerHTML = `<div class="text-xs italic text-gray-400">
            No extra products added
        </div>`;
    }
}

function renderServiceProductUsageEditor(
    appointmentServiceId,
    products,
    transactionStatus
) {
    const box = document.getElementById("serviceProductsPreview");
    box.innerHTML = "";
    box.classList.remove("hidden");

    if (!products.length) {
        box.innerHTML =
            `<div class="text-xs italic text-gray-400">No products linked</div>`;
        return;
    }

    const locked = transactionStatus === "locked";

    box.innerHTML = `
        <div class="font-medium mb-1 text-xs">
            Product Usage
        </div>
        <div class="mb-2 text-[11px] text-gray-500 italic">
            Changes are saved automatically.
        </div>
        ${products.map(p => {

        if (p.product_type === "reusable") {
            return `
                    <div class="flex justify-between text-xs">
                        <span>${p.name}</span>
                        <span class="italic text-gray-400">Reusable</span>
                    </div>
                `;
        }

        return `
                <div class="flex justify-between items-center text-xs">
                    <span>${p.name}</span>

                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value="${p.quantity_used || p.default_qty}"
                        ${locked ? "disabled" : ""}
                        class="
                            w-20 px-2 py-1 text-right rounded-md
                            border
                            bg-white dark:bg-gray-800
                            border-gray-300 dark:border-gray-600
                            text-gray-900 dark:text-gray-100
                            placeholder-gray-400 dark:placeholder-gray-500
                            focus:outline-none focus:ring-1
                            focus:ring-green-500 focus:border-green-500
                            disabled:opacity-60 disabled:cursor-not-allowed
                        "
                        onblur="updateServiceProductUsage(
                            ${appointmentServiceId},
                            ${p.product_id},
                            this.value
                        )"
                    />
                    <span class="ml-1">${p.unit}</span>
                </div>
            `;
    }).join("")}
        ${locked ? `
            <div class="mt-2 text-[11px] text-gray-400 italic">
                Product usage is locked for this transaction
            </div>
        ` : ""}
    `;
}

async function updateServiceProductUsage(
    appointmentServiceId,
    productId,
    quantity
) {
    const qty = parseFloat(quantity);

    if (isNaN(qty) || qty < 0) {
        showToast("Invalid quantity", "error");
        return;
    }

    const res = await fetch(
        "../php/cashier/center/products/update-service-product-usage.php",
        {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                appointment_service_id: appointmentServiceId,
                product_id: productId,
                quantity_used: qty
            })
        }
    );

    const d = await res.json();
    if (!d.success) {
        showToast(d.error, "error");
        return;
    }

    // 🔥 ADD THIS
    if (CashierState.activeTransactionId) {
        loadTransaction(CashierState.activeTransactionId);
    }

    showToast("Usage saved — totals updated", "success");
}

function lockAddService(reason = "Finish editing the service first") {
    addServiceBtn.disabled = true;
    addServiceBtn.classList.add("opacity-50", "cursor-not-allowed");
    addServiceBtn.dataset.lockReason = reason;
}

function unlockAddService() {
    addServiceBtn.disabled = false;
    addServiceBtn.classList.remove("opacity-50", "cursor-not-allowed");
    delete addServiceBtn.dataset.lockReason;
}

function updateAddServiceButtonState() {
    if (CashierState.transactionLocked) {
        addServiceBtn.disabled = true;
        addServiceBtn.classList.add("opacity-40", "cursor-not-allowed");
        addServiceBtn.title = "Transaction is locked";
    } else {
        addServiceBtn.disabled = false;
        addServiceBtn.classList.remove("opacity-40", "cursor-not-allowed");
        addServiceBtn.title = "";
    }
}
