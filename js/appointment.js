document.addEventListener("DOMContentLoaded", () => {
    let selectedClientId = null;
    let allServices = [];

    let currentPage = 1;
    let perPage = 20;
    let allAppointments = [];
    let activeFilters = {};

    const appointmentModal = document.getElementById("appointmentModal");
    const appointmentDate = document.getElementById("appointmentDate");
    const startTime = document.getElementById("startTime");
    const endTime = document.getElementById("endTime");
    const notes = document.getElementById("notes");
    const serviceStaffContainer = document.getElementById("serviceStaffContainer");
    const addAppointmentBtn = document.querySelector(".btn-add-appointment");
    const saveAppointmentBtn = appointmentModal.querySelector(".btn-primary");
    const cancelAppointmentBtn = appointmentModal.querySelector(".btn-secondary");
    const clientSearch = document.getElementById("clientSearch");
    const clientResults = document.getElementById("clientResults");
    const toastContainer = document.getElementById("toastContainer");
    const perPageSelect = document.querySelector("select.input");

    // --- NEW CLIENT TOGGLE ---
    const newClientFields = document.getElementById("newClientFields");
    const newClientToggle = document.getElementById("newClientToggle");
    let isNewClient = false;
    const applyBtn = document.getElementById("applyFilters");
    const resetBtn = document.getElementById("resetFilters");


    const openModal = () => appointmentModal.classList.remove("hidden");
    const closeModal = () => appointmentModal.classList.add("hidden");

    addAppointmentBtn.addEventListener("click", openModal);
    cancelAppointmentBtn.addEventListener("click", closeModal);

    const getTodayISO = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, "0");
        const day = String(now.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };

    const getTomorrowISO = () => {
        const d = new Date();
        d.setDate(d.getDate() + 1);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    };

    const setActiveQuickFilter = (mode) => {
        document.querySelectorAll(".quick-filter").forEach(btn => {
            btn.classList.remove("bg-rose-500", "text-white");
            btn.classList.add("btn-secondary");
        });

        const activeBtn = document.querySelector(`.quick-filter[data-mode="${mode}"]`);
        if (activeBtn) {
            activeBtn.classList.remove("btn-secondary");
            activeBtn.classList.add("bg-rose-500", "text-white");
        }
    };

    // --- TOAST FUNCTION ---
    const showToast = (message, type = "info", duration = 3000) => {
        const toast = document.createElement("div");
        toast.className = `px-4 py-2 rounded shadow text-white ${type === "error" ? "bg-red-500" : type === "success" ? "bg-green-500" : "bg-blue-500"
            }`;
        toast.textContent = message;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.add("opacity-0");
            setTimeout(() => toast.remove(), 500);
        }, duration);
    };

    // --- Load all services ---
    const loadAllServices = async () => {
        const res = await fetch("php/services_list.php");
        allServices = await res.json();
    };

    const getAvailableServices = (excludeIds = []) => {
        return allServices.filter(s => !excludeIds.includes(s.id.toString()));
    };

    // --- Update dropdowns and Add button state ---
    const updateServiceDropdowns = () => {
        const selectedIds = Array.from(document.querySelectorAll(".serviceSelect"))
            .map(sel => sel.value)
            .filter(v => v);

        document.querySelectorAll(".serviceSelect").forEach(select => {
            const currentVal = select.value;
            select.innerHTML = '<option value="">Select service</option>';
            getAvailableServices(selectedIds.filter(id => id !== currentVal))
                .forEach(s => {
                    const opt = document.createElement("option");
                    opt.value = s.id;
                    opt.textContent = s.name;
                    if (s.id == currentVal) opt.selected = true;
                    select.appendChild(opt);
                });
        });

        // Manage Add button
        const addBtn = serviceStaffContainer.querySelector(".addRowBtn");
        if (addBtn) {
            const totalRows = serviceStaffContainer.querySelectorAll(".service-staff-row").length;
            const wasDisabled = addBtn.disabled;
            addBtn.disabled = selectedIds.length >= allServices.length || totalRows >= allServices.length;

            // Show toast when Add becomes enabled after removing
            if (wasDisabled && !addBtn.disabled) {
                showToast("You can now add more services!", "success");
            }
        }
    };

    const statusClass = (status, row) => {
        if (status === "confirmed" && row?.source === "online") {
            return "bg-indigo-100 text-indigo-700";
        }
        if (
            status === "pending" &&
            row?.source === "online" &&
            row?.transaction_status === "pending_verification"
        ) {
            return "bg-blue-100 text-blue-700";
        }

        switch (status) {
            case "completed":
                return "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300";
            case "checked_in":
                return "bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300";
            case "confirmed":
                return "bg-purple-100 text-purple-700";
            case "cancelled":
            case "no_show":
                return "bg-gray-200 text-gray-600";
            default:
                return "bg-rose-100 text-rose-700";
        }
    };

    const totalClass = (row) => {
        if (row.transaction_id && row.has_receivable) {
            return "text-orange-600 dark:text-orange-400";
        }

        if (row.transaction_id && row.payment_status === "paid") {
            return "text-green-600 dark:text-green-400";
        }

        if (row.transaction_id && row.payment_status === "partial" && row.source === "online") {
            return "text-yellow-600 dark:text-yellow-400";
        }

        return "text-gray-800 dark:text-gray-200";
    };


    const computeAppointmentTotal = (row) => {
        // 🔥 SOURCE OF TRUTH
        if (row.transaction_id && row.total_amount != null) {
            return parseFloat(row.total_amount);
        }

        // ❗ Estimate only (no transaction yet)
        let estimate = 0;
        row.services.forEach(s => {
            estimate += parseFloat(s.price || 0);
        });

        return estimate;
    };


    const paymentBadge = (row) => {
        if (!row.transaction_id) return "";

        if (row.source === "online" &&
            row.transaction_status === "pending_verification") {
            return `
            <div class="text-xs font-semibold text-blue-700 bg-blue-100
                        dark:bg-blue-900 dark:text-blue-300
                        px-2 py-0.5 rounded inline-block mt-1">
                PENDING VERIFICATION
            </div>
        `;
        }


        if (row.has_receivable) {
            return `
            <div class="text-xs font-semibold text-orange-700 bg-orange-100
                        dark:bg-orange-900 dark:text-orange-300
                        px-2 py-0.5 rounded inline-block mt-1">
                RECEIVABLE ₱${parseFloat(row.balance_due || 0).toFixed(2)}
            </div>
        `;
        }

        if (row.payment_status === "paid") {
            return `
            <div class="text-xs font-semibold text-green-700 bg-green-100
                        dark:bg-green-900 dark:text-green-300
                        px-2 py-0.5 rounded inline-block mt-1">
                PAID
            </div>
        `;
        }

        if (row.payment_status === "partial" && row.source === "online") {
            return `
            <div class="text-xs font-semibold text-yellow-700 bg-yellow-100
                        dark:bg-yellow-900 dark:text-yellow-300
                        px-2 py-0.5 rounded inline-block mt-1">
                DEPOSIT ₱${parseFloat(row.balance_due || 0).toFixed(2)}
            </div>
        `;
        }

        return "";
    };

    // --- Staff dropdown ---
    const loadStaffDropdown = async (selectElement) => {
        const res = await fetch("php/staff_list.php");
        const staff = await res.json();
        selectElement.innerHTML = '<option value="">Select staff</option>';
        staff.forEach(s => {
            const opt = document.createElement("option");
            opt.value = s.id;
            opt.textContent = `${s.full_name} (${s.role_name})`;
            selectElement.appendChild(opt);
        });
    };

    // --- Load service-staff row ---
    const loadServiceStaffRow = async (row) => {
        const staffSelect = row.querySelector(".staffSelect");
        await loadStaffDropdown(staffSelect);
        updateServiceDropdowns();
    };


    newClientToggle.addEventListener("click", () => {
        isNewClient = !isNewClient;
        newClientFields.classList.toggle("hidden", !isNewClient);
        clientSearch.disabled = isNewClient;
        clientResults.classList.add("hidden");
        if (!newClientFields.classList.contains("hidden")) {
            selectedClientId = null;
            clientSearch.value = "";
            document.getElementById("newClientName").focus();
        }
        selectedClientId = null;
        clientSearch.value = "";
    });

    // --- Add new service-staff row ---
    const addServiceStaffRow = async () => {
        const totalRows = serviceStaffContainer.querySelectorAll(".service-staff-row").length;
        if (totalRows >= allServices.length) {
            showToast("Cannot add more services, maximum reached!", "error");
            return;
        }

        const row = document.createElement("div");
        row.className = "flex gap-2 service-staff-row mt-2";
        row.innerHTML = `
        <div class="flex flex-col gap-2 w-full">
            <div class="flex gap-2">
                <select class="serviceSelect input flex-1"></select>
                <select class="variantSelect input flex-1 hidden"></select>
            </div>

            <div class="flex gap-2">
                <select class="staffSelect input flex-1" disabled></select>
                <button type="button"
                    class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">-</button>
            </div>

            <div class="productInfo text-xs text-gray-500 hidden"></div>
        </div>
        `;

        const addBtn = serviceStaffContainer.querySelector(".addRowBtn");
        serviceStaffContainer.insertBefore(row, addBtn);

        await loadServiceStaffRow(row);
        updateServiceDropdowns();
    };


    // --- Add hover toast for disabled Add button ---
    const addBtn = serviceStaffContainer.querySelector(".addRowBtn");
    addBtn.addEventListener("mouseenter", () => {
        if (addBtn.disabled) showToast("Cannot add more services, maximum reached!", "warning", 2000);
    });

    // --- Event listeners for service-staff container ---
    serviceStaffContainer.addEventListener("click", async (e) => {
        if (e.target.classList.contains("addRowBtn")) {
            await addServiceStaffRow();
        }

        if (e.target.classList.contains("removeRowBtn")) {
            e.target.closest(".service-staff-row").remove();
            updateServiceDropdowns();
            showToast("Service row removed", "warning", 2000);
        }
    });

    serviceStaffContainer.addEventListener("change", (e) => {

        const row = e.target.closest(".service-staff-row");
        if (!row) return;

        const serviceSelect = row.querySelector(".serviceSelect");
        const variantSelect = row.querySelector(".variantSelect");
        const staffSelect = row.querySelector(".staffSelect");
        const productInfo = row.querySelector(".productInfo");

        /* =========================
           SERVICE SELECTED
        ========================= */
        if (e.target.classList.contains("serviceSelect")) {

            const service = allServices.find(s => s.id == serviceSelect.value);

            // Reset row state
            variantSelect.classList.add("hidden");
            variantSelect.innerHTML = "";
            staffSelect.disabled = true;
            staffSelect.value = "";
            productInfo.classList.add("hidden");
            productInfo.innerHTML = "";

            if (!service) return;

            // 🔥 FIX: object → array
            const variants = Object.values(service.variants || {});
            const products = Object.values(service.products || {});

            /* ---- VARIANTS ---- */
            if (variants.length > 0) {
                variantSelect.classList.remove("hidden");
                variantSelect.innerHTML = `<option value="">Select variant</option>`;

                variants.forEach(v => {
                    variantSelect.innerHTML += `
                    <option value="${v.id}">
                        ${v.name} — ₱${v.price}
                    </option>`;
                });

                // Auto-select if only one
                if (variants.length === 1) {
                    variantSelect.value = variants[0].id;
                    staffSelect.disabled = false;
                }
            } else {
                // No variants → base service
                staffSelect.disabled = false;
            }

            /* ---- PRODUCTS ---- */
            if (products.length > 0) {
                productInfo.classList.remove("hidden");
                productInfo.innerHTML =
                    "<strong>Products used:</strong><br>" +
                    products.map(p => `• ${p.name} (x${p.quantity})`).join("<br>");
            }

            updateServiceDropdowns();
        }

        /* =========================
           VARIANT SELECTED
        ========================= */
        if (e.target.classList.contains("variantSelect")) {
            if (variantSelect.value) {
                staffSelect.disabled = false;
            } else {
                staffSelect.disabled = true;
                staffSelect.value = "";
            }
        }
    });

    // --- Load initial row ---
    loadServiceStaffRow(document.querySelector(".service-staff-row"));

    // --- Client search ---
    clientSearch.addEventListener("input", async e => {
        const q = e.target.value;
        if (q.length < 2) return clientResults.classList.add("hidden");

        const res = await fetch(`php/clients_appointments.php?q=${q}`);
        const data = await res.json();
        clientResults.innerHTML = "";
        clientResults.classList.remove("hidden");

        data.forEach(c => {
            const div = document.createElement("div");
            div.className = `
                p-2 cursor-pointer rounded
                hover:bg-blue-100 dark:hover:bg-gray-600
                transition
            `;
            div.innerHTML = `
                <p class="font-medium">${c.full_name}</p>
                <p class="text-xs text-gray-500">${c.contact_number ?? ""} ${c.email ?? ""}</p>
            `;

            div.onclick = () => {
                selectedClientId = c.id;
                clientSearch.value = c.full_name;
                clientResults.classList.add("hidden");
                newClientFields.classList.add("hidden");

                showToast("Client selected: " + c.full_name, "success");
            };

            clientResults.appendChild(div);
        });
    });


    perPageSelect.addEventListener("change", () => {
        perPage = parseInt(perPageSelect.value);
        currentPage = 1;
        renderAppointments();
    });

    applyBtn.addEventListener("click", (e) => {
        e.preventDefault(); // 🔥 THIS FIXES IT

        const filters = {
            date: document.getElementById("filterDate").value,
            staff: document.getElementById("filterStaff").value,
            customer: document.getElementById("filterCustomer").value.trim(),
            service: document.getElementById("filterService").value,
            status: document.getElementById("filterStatus").value,
            id: document.getElementById("filterId").value.trim()
        };

        loadAppointments(filters);
        showToast("Filters applied", "success", 2000);
    });


    resetBtn.addEventListener("click", () => {
        document.querySelectorAll(
            "#filterDate, #filterStaff, #filterCustomer, #filterService, #filterStatus, #filterId"
        ).forEach(el => el.value = "");

        const today = getTodayISO();
        activeFilters = { date_from: today };
        loadAppointments(activeFilters);

        showToast("Showing today & upcoming appointments", "info", 2000);
    });

    document.querySelectorAll(".quick-filter").forEach(btn => {
        btn.addEventListener("click", () => {
            const mode = btn.dataset.mode;
            const today = getTodayISO();

            // Reset form inputs visually
            document.querySelectorAll(
                "#filterDate, #filterStaff, #filterCustomer, #filterService, #filterStatus, #filterId"
            ).forEach(el => el.value = "");

            if (mode === "today") {
                document.getElementById("filterDate").value = today;
                activeFilters = { date: today };
            }

            if (mode === "upcoming") {
                const tomorrow = getTomorrowISO();
                activeFilters = { date_from: tomorrow };
            }

            if (mode === "all") {
                activeFilters = {};
            }

            setActiveQuickFilter(mode);
            loadAppointments(activeFilters);

            showToast(
                mode === "today"
                    ? "Showing today's appointments"
                    : mode === "upcoming"
                        ? "Showing upcoming appointments"
                        : "Showing all appointments",
                "info",
                2000
            );
        });
    });


    // --- Format date like: December 19, 2025 ---
    const formatDateLong = (dateStr) => {
        if (!dateStr) return "—";

        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return "—";

        return new Intl.DateTimeFormat("en-US", {
            month: "long",
            day: "numeric",
            year: "numeric"
        }).format(date);
    };


    // --- Format time like: 11:30 AM ---
    const formatTime = (timeStr) => {
        if (!timeStr || typeof timeStr !== "string") {
            return "—";
        }

        // Normalize: ensure HH:mm:ss
        const normalized = timeStr.length === 5 ? `${timeStr}:00` : timeStr;

        const date = new Date(`1970-01-01T${normalized}`);

        if (isNaN(date.getTime())) {
            console.warn("Invalid time value:", timeStr);
            return "—";
        }

        return new Intl.DateTimeFormat("en-US", {
            hour: "numeric",
            minute: "2-digit",
            hour12: true
        }).format(date);
    };


    // --- Format datetime like: December 19, 2025, 11:30 AM ---
    const formatDateTimeLong = (dateTimeStr) => {
        if (!dateTimeStr) return "—";

        const date = new Date(dateTimeStr);
        if (isNaN(date.getTime())) return "—";

        return new Intl.DateTimeFormat("en-US", {
            month: "long",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit",
            hour12: true
        }).format(date);
    };

    // --- Load appointments ---
    const loadAppointments = async (filters = {}) => {
        activeFilters = filters;

        const params = new URLSearchParams();
        Object.entries(filters).forEach(([k, v]) => {
            if (v) params.append(k, v);
        });

        const res = await fetch("php/appointment.php?" + params.toString());
        let data = await res.json();

        // 🔥 DEFAULT: today + future
        if (filters.date_from) {
            const from = new Date(filters.date_from);
            data = data.filter(a => {
                const d = new Date(a.appointment_date + "T00:00:00");

                return d >= from;
            });
        }

        allAppointments = data;
        currentPage = 1;
        renderAppointments();
    };

    // --- Render appointments with pagination ---
    const renderAppointments = () => {
        const tbody = document.getElementById("appointmentsBody");
        const cards = document.getElementById("appointmentsCards");
        const info = document.getElementById("paginationInfo");

        tbody.innerHTML = "";
        cards.innerHTML = "";

        if (!allAppointments.length) {
            tbody.innerHTML = `
            <tr>
                <td colspan="9" class="p-6 text-center text-gray-400">
                    No appointments found
                </td>
            </tr>`;
            cards.innerHTML = `
            <div class="text-center text-gray-400 py-10">
                No appointments found
            </div>`;
            info.textContent = "Showing 0–0 of 0";
            updatePaginationButtons();
            return;
        }

        const startIndex = (currentPage - 1) * perPage;
        const endIndex = startIndex + perPage;
        const pageData = allAppointments.slice(startIndex, endIndex);

        pageData.forEach(row => {
            const total = computeAppointmentTotal(row);

            const servicesHtml = row.services.map(s => {
                return `
                    <div class="text-sm">
                        <span class="font-medium">${s.service_name}</span>
                        <span class="text-xs opacity-70"> (${s.staff_name})</span>
                    </div>
                `;
            }).join("");


            /* =========================
               DESKTOP TABLE ROW
            ========================= */
            const tr = document.createElement("tr");
            tr.className = "hover:bg-gray-50 dark:hover:bg-gray-700";
            const statusDisabled = row.transaction_id ? "disabled opacity-60 cursor-not-allowed" : "";

            tr.innerHTML = `
            <td class="p-4 text-center">
                <button class="view-details text-rose-600 font-semibold hover:underline"
                        data-id="${row.id}">
                    View
                </button>
            </td>

            <td class="p-4 font-medium">
                #${row.id}
                ${sourceBadge(row)}
            </td>

            <td class="p-4">
                <div class="font-medium">${formatDateLong(row.appointment_date)}</div>
                <div class="text-xs opacity-70">
                    ${formatTime(row.start_time)} – ${formatTime(row.end_time)}
                </div>
            </td>

            <td class="p-4">${row.client_name}</td>
            <td class="p-4">${servicesHtml}</td>
            <td class="p-4">
                <div class="font-semibold ${totalClass(row)}">

                    ₱${total.toFixed(2)}
                </div>
                ${paymentBadge(row)}
            </td>
            <td class="p-4">
                <select class="status-select w-full px-2 py-1 rounded
                            ${statusClass(row.status)} ${statusDisabled}"
                        data-id="${row.id}"
                        ${row.transaction_id ? "disabled" : ""}>
                        ${["pending", "confirmed", "cancelled", "no_show"]
                    .map(s => `
                            <option value="${s}" ${s === row.status ? "selected" : ""}>
                                ${s.replace("_", " ")}
                            </option>
                        `).join("")}

                </select>
            </td>
            <td class="p-4 text-xs opacity-70">
                ${formatDateTimeLong(row.created_at)}
            </td>
        `;

            tbody.appendChild(tr);

            /* =========================
               MOBILE CARD
            ========================= */
            const card = document.createElement("div");
            card.className = `
            bg-white dark:bg-gray-800 rounded-2xl shadow
            border dark:border-gray-700 p-4 space-y-3
        `;

            card.innerHTML = `
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-xs opacity-70">Client</p>
                    <p class="font-semibold">${row.client_name}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-medium ${statusClass(row.status)}">
                        ${row.status.replace("_", " ")}
                    </span>
                    ${sourceBadge(row)}
                </div>

                ${paymentBadge(row)}
            </div>

            <div class="text-sm">
                <p><span class="opacity-70">Date:</span> ${formatDateLong(row.appointment_date)}</p>
                <p><span class="opacity-70">Time:</span> ${formatTime(row.start_time)} – ${formatTime(row.end_time)}</p>
            </div>

            <div class="text-sm space-y-1">
                <p class="opacity-70">Services</p>
                ${servicesHtml}
            </div>

            <div class="flex justify-between items-center pt-2">
                <span class="font-bold ${totalClass(row)}">₱${total.toFixed(2)}</span>
                <button class="px-4 py-2 rounded-xl bg-rose-500 text-white text-sm font-medium"
                        data-id="${row.id}">
                    View Details
                </button>
            </div>
        `;

            cards.appendChild(card);
        });

        /* =========================
           EVENTS
        ========================= */
        document.querySelectorAll(".view-details, #appointmentsCards button").forEach(btn => {
            btn.addEventListener("click", () => {
                const appointment = allAppointments.find(a => a.id == btn.dataset.id);
                if (appointment) openDetailsModal(appointment);
            });
        });

        info.textContent = `Showing ${startIndex + 1}–${Math.min(endIndex, allAppointments.length)} of ${allAppointments.length}`;
        updatePaginationButtons();
    };

    const renderPricingBreakdown = (appointment) => {
        const pb = appointment.pricing_breakdown;
        if (!pb) return "";

        const serviceTotal = appointment.services.reduce(
            (sum, s) => sum + parseFloat(s.price || 0),
            0
        );

        const consumableTotal = appointment.services.reduce(
            (sum, s) =>
                sum +
                (s.products || []).reduce(
                    (pSum, p) => pSum + parseFloat(p.cost || 0),
                    0
                ),
            0
        );

        const extraProductTotal = (appointment.extra_products || []).reduce(
            (sum, p) => sum + parseFloat(p.total_price || 0),
            0
        );

        return `
        <div class="mt-6 border-t dark:border-gray-700 pt-4 space-y-2 text-sm">
            <div class="flex justify-between">
                <span>Service</span>
                <span>₱${serviceTotal.toFixed(2)}</span>
            </div>

            ${consumableTotal > 0 ? `
                <div class="flex justify-between text-gray-500">
                    <span>+ Consumables</span>
                    <span>₱${consumableTotal.toFixed(2)}</span>
                </div>
            ` : ""}

            ${extraProductTotal > 0 ? `
                <div class="flex justify-between text-gray-500">
                    <span>+ Extra Products</span>
                    <span>₱${extraProductTotal.toFixed(2)}</span>
                </div>
            ` : ""}

            <div class="flex justify-between font-medium border-t pt-2">
                <span>Subtotal</span>
                <span>₱${pb.subtotal.toFixed(2)}</span>
            </div>

            <div class="flex justify-between text-gray-500">
                <span>VAT (${pb.vat_rate}%)</span>
                <span>₱${pb.vat_amount.toFixed(2)}</span>
            </div>

            <div class="flex justify-between text-lg font-bold border-t pt-3">
                <span>TOTAL</span>
                <span>₱${pb.grand_total.toFixed(2)}</span>
            </div>
        </div>
    `;
    };


    const detailsModal = document.getElementById("appointmentDetailsModal");
    const detailsContent = document.getElementById("detailsContent");
    const openDetailsModal = (appointment) => {
        const total = computeAppointmentTotal(appointment);

        const servicesHtml = appointment.services.map(s => {
            const price = parseFloat(s.price || 0);

            const variant = s.variant_name
                ? `<p class="text-sm text-gray-500 dark:text-gray-400">
                    Variant: <span class="font-medium">${s.variant_name}</span>
                </p>`
                : "";

            const products = s.products?.length
                ? `
                        <div class="mt-2">
                            <p class="text-xs uppercase tracking-wide text-gray-400">
                                Consumables Used
                            </p>
                            <ul class="mt-1 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                ${s.products.map(p => `
                                    <li class="flex justify-between">
                                        <span>${p.name}</span>
                                        <span class="text-gray-400">
                                            ${appointment.usage_mode === "actual"
                        ? `${p.qty} ${p.unit}`
                        : `x ${p.qty ?? 1}`
                    }
                                        </span>
                                    </li>
                                `).join("")}
                            </ul>
                        </div>
                    `
                : "";

            return `
                <div class="rounded-2xl border dark:border-gray-700 p-5 bg-gray-50 dark:bg-gray-900 space-y-2">
                    <div class="flex justify-between items-start">
                        <h5 class="text-lg font-semibold">${s.service_name}</h5>
                        <span class="text-base font-bold text-gray-700 dark:text-gray-200">
                            ₱${price.toFixed(2)}
                        </span>
                    </div>

                    ${variant}
                    ${products}

                    <p class="text-sm">
                        Staff:
                        <span class="font-medium">${s.staff_name}</span>
                    </p>
                </div>
            `;
        }).join("");

        detailsContent.innerHTML = `
        <!-- HEADER -->
        <div class="space-y-4">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <p class="text-sm opacity-70">Client</p>
                    <div class="flex items-center gap-2">
                        <h3 class="text-2xl font-bold">${appointment.client_name}</h3>
                        ${sourceBadge(appointment)}
                    </div>
                </div>

                <span class="
                    inline-flex items-center px-3 py-1 rounded-full
                    text-sm font-semibold capitalize
                    ${appointment.status === 'completed'
                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                : 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300'}
                ">
                    ${appointment.status}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-base">
                <div>
                    <p class="text-sm opacity-70">Date</p>
                    <p class="font-medium">${formatDateLong(appointment.appointment_date)}</p>
                </div>

                <div>
                    <p class="text-sm opacity-70">Time</p>
                    <p class="font-medium">
                        ${formatTime(appointment.start_time)} – ${formatTime(appointment.end_time)}
                    </p>
                </div>

                <div>
                    <p class="text-sm opacity-70">Total (from cashier)</p>
                    <p class="text-xl font-bold ${totalClass(appointment)}">
                        ₱${total.toFixed(2)}
                    </p>
                </div>
            </div>
        </div>

        <hr class="my-6 dark:border-gray-700">

        <!-- SERVICES -->
        <div class="space-y-4">
            <h4 class="text-xl font-semibold">Services</h4>
            ${servicesHtml}
        </div>

        <!-- PRICING BREAKDOWN -->
        ${appointment.pricing_breakdown ? `
            <div class="mt-6 bg-gray-50 dark:bg-gray-900 p-5 rounded-2xl border dark:border-gray-700">
                <h4 class="text-sm uppercase tracking-wide text-gray-400 mb-3">
                    Pricing Breakdown
                </h4>
                ${renderPricingBreakdown(appointment)}
            </div>
        ` : `
            <div class="mt-6 flex justify-end">
                <div class="px-6 py-3 rounded-2xl text-xl font-bold shadow
                            ${appointment.payment_status === "paid"
                ? "bg-green-600 text-white"
                : "bg-gray-700 text-white"}">
                    Total: ₱${total.toFixed(2)}
            </div>
            </div>
        `}

        `;

        detailsModal.classList.remove("hidden");
    };


    const closeDetailsModal = () => {
        detailsModal.classList.add("hidden");
    };

    document.getElementById("closeDetailsBtn")
        .addEventListener("click", closeDetailsModal);

    document.getElementById("closeDetailsModal")
        .addEventListener("click", closeDetailsModal);



    // --- Update Prev/Next buttons ---
    const updatePaginationButtons = () => {
        const totalPages = Math.ceil(allAppointments.length / perPage);
        document.getElementById("prevPage").disabled = currentPage <= 1;
        document.getElementById("nextPage").disabled = currentPage >= totalPages;
    };

    // --- Prev button click ---
    document.getElementById("prevPage").addEventListener("click", () => {
        if (currentPage > 1) {
            currentPage--;
            renderAppointments();
        }
    });

    // --- Next button click ---
    document.getElementById("nextPage").addEventListener("click", () => {
        const totalPages = Math.ceil(allAppointments.length / perPage);
        if (currentPage < totalPages) {
            currentPage++;
            renderAppointments();
        }
    });

    const updateStatus = async (id, status) => {
        await fetch("php/appointment.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id, status })
        });
        showToast(`Status updated to "${status}"`, "success");
    };

    document.addEventListener("change", async (e) => {
        if (!e.target.classList.contains("status-select")) return;

        const id = e.target.dataset.id;
        const status = e.target.value;

        await updateStatus(id, status);
        loadAppointments(activeFilters);
    });



    // --- Save appointment with new client fields ---
    const saveAppointment = async () => {

        // --- Validate client ---
        if (!isNewClient && !selectedClientId) {
            showToast("Please select a client", "error");
            return;
        }

        let payload = {
            appointment_date: appointmentDate.value,
            start_time: startTime.value,
            end_time: endTime.value,
            notes: notes.value.trim(),
            services: []
        };

        // --- Validate appointment date/time ---
        if (!payload.appointment_date || !payload.start_time || !payload.end_time) {
            showToast("Please select appointment date and time", "error");
            return;
        }

        /* ============================
           VALIDATE SERVICES, VARIANTS, STAFF
        ============================ */
        let hasError = false;

        document.querySelectorAll(".service-staff-row").forEach(row => {
            const serviceId = row.querySelector(".serviceSelect")?.value;
            const variantId = row.querySelector(".variantSelect")?.value || null;
            const staffId = row.querySelector(".staffSelect")?.value;

            if (!serviceId) return;

            const service = allServices.find(s => s.id == serviceId);

            // ❌ Service selected but no staff
            if (!staffId) {
                showToast("Please assign staff for each service", "error");
                hasError = true;
                return;
            }

            // ❌ Service has variants but none selected
            if (service?.variants?.length && !variantId) {
                showToast(`Please select a variant for ${service.name}`, "error");
                hasError = true;
                return;
            }

            payload.services.push({
                service_id: serviceId,
                variant_id: variantId,
                staff_id: staffId
            });
        });

        if (hasError) return;

        if (!payload.services.length) {
            showToast("Please select at least one service with staff", "error");
            return;
        }

        /* ============================
           CLIENT HANDLING
        ============================ */
        if (isNewClient) {
            const fullName = document.getElementById("newClientName").value.trim();
            if (!fullName) {
                showToast("Enter new client full name", "error");
                return;
            }

            payload.new_client_data = {
                full_name: fullName,
                contact_number: document.getElementById("newClientContact").value.trim(),
                email: document.getElementById("newClientEmail").value.trim(),
                address: document.getElementById("newClientAddress").value.trim(),
                notes: document.getElementById("newClientNotes").value.trim()
            };
        } else {
            payload.client_id = selectedClientId;
        }

        /* ============================
           SEND REQUEST
        ============================ */
        try {
            const res = await fetch("php/appointment.php", {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload)
            });

            const result = await res.json();

            if (!result.success) {
                showToast(result.message || "Conflict detected", "error");
                return;
            }

            showToast("Appointment saved successfully!", "success");
            closeModal();
            loadAppointments();

        } catch (err) {
            console.error(err);
            showToast("Failed to save appointment", "error");
        }
    };

    saveAppointmentBtn.addEventListener("click", saveAppointment);

    // --- INITIAL LOAD (TODAY + FUTURE ONLY) ---
    loadAllServices().then(() => {
        loadFilterDropdowns();

        const today = getTodayISO();

        // Show today's date in the date picker
        document.getElementById("filterDate").value = today;

        activeFilters = { date_from: today };
        loadAppointments(activeFilters);

        setActiveQuickFilter("upcoming");
        updateServiceDropdowns();
    });

});

const sourceBadge = (row) => {
    if (row.source !== "online") return "";

    return `
        <span class="ml-2 px-2 py-0.5 rounded-full
                     text-xs font-semibold
                     bg-indigo-100 text-indigo-700
                     dark:bg-indigo-900 dark:text-indigo-300">
            ONLINE
        </span>
    `;
};

const loadFilterDropdowns = async () => {
    // Staff
    const staffRes = await fetch("php/staff_list.php");
    const staff = await staffRes.json();
    const staffSelect = document.getElementById("filterStaff");

    staff.forEach(s => {
        const opt = document.createElement("option");
        opt.value = s.id;
        opt.textContent = s.full_name;
        staffSelect.appendChild(opt);
    });

    // Services
    const serviceRes = await fetch("php/services_list.php");
    const services = await serviceRes.json();
    const serviceSelect = document.getElementById("filterService");

    services.forEach(s => {
        const opt = document.createElement("option");
        opt.value = s.id;
        opt.textContent = s.name;
        serviceSelect.appendChild(opt);
    });
};
