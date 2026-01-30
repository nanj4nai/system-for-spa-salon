/* =====================================================
   STEP 3 — SCHEDULING (PER-SERVICE STAFF)
===================================================== */

const datePicker = document.getElementById("datePicker");
const timeSlotsBox = document.getElementById("timeSlots");
const continueBtn = document.getElementById("continueBtn");
const summaryDate = document.getElementById("summaryDate");
const summaryTime = document.getElementById("summaryTime");
const pageLoader = document.getElementById("pageLoader");
const mobileSummaryToggle = document.getElementById("mobileSummaryToggle");
const mobileSummaryDrawer = document.getElementById("mobileSummaryDrawer");
const closeMobileSummary = document.getElementById("closeMobileSummary");

const mobileSummaryDate = document.getElementById("mobileSummaryDate");
const mobileSummaryTime = document.getElementById("mobileSummaryTime");

/* ---------------------------
   State
--------------------------- */
let selectedDate = "";
let selectedTime = "";

// variant_id => employee_id|null
let selectedStaffByVariant = {};

/* ---------------------------
   Init
--------------------------- */
init();

async function init() {
    resetTimeSlots();
    await loadStaffForServices();
    setupDatePicker();
}

/* ---------------------------
   Load staff into EACH service select
--------------------------- */
async function loadStaffForServices() {
    try {
        const res = await fetch("api/get-staff.php");
        const staff = await res.json();

        document.querySelectorAll(".staff-select").forEach(select => {
            const variantId = select.dataset.variant;

            // default
            selectedStaffByVariant[variantId] = null;

            staff.forEach(s => {
                const opt = document.createElement("option");
                opt.value = s.id;
                opt.textContent = `${s.name} (${s.role})`;
                select.appendChild(opt);
            });

            select.addEventListener("change", () => {
                selectedStaffByVariant[variantId] =
                    select.value ? Number(select.value) : null;

                updateSummaryStaff();     // 🔥 NEW
                selectedTime = "";
                resetTimeSlots();
            });

        });
    } catch (err) {
        console.error("Failed to load staff", err);
    }
}

/* ---------------------------
   Date Picker
--------------------------- */
function setupDatePicker() {
    const today = new Date().toISOString().split("T")[0];
    datePicker.min = today;

    datePicker.addEventListener("change", () => {
        selectedDate = datePicker.value;
        selectedTime = "";
        updateSummaryDate();
        fetchAvailability();
    });
}

function updateSummaryDate() {
    if (!selectedDate) {
        summaryDate && (summaryDate.textContent = "—");
        mobileSummaryDate && (mobileSummaryDate.textContent = "—");
        return;
    }

    const d = new Date(selectedDate);
    const formatted = d.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric"
    });

    summaryDate && (summaryDate.textContent = formatted);
    mobileSummaryDate && (mobileSummaryDate.textContent = formatted);
}

function updateSummaryTime(label) {
    summaryTime && (summaryTime.textContent = label);
    mobileSummaryTime && (mobileSummaryTime.textContent = label);
}

/* ---------------------------
   Availability
   (temporary: global availability until per-service logic)
--------------------------- */
async function fetchAvailability() {
    resetTimeSlots();
    if (!selectedDate) return;

    try {
        const params = new URLSearchParams({
            date: selectedDate,
            staff: JSON.stringify(selectedStaffByVariant)
        });
        const res = await fetch(`api/get-availability.php?${params.toString()}`);
        const data = await res.json();
        renderTimeSlots(data.slots || []);
    } catch (err) {
        console.error("Failed to load availability", err);
    }
}

function updateSummaryStaff() {
    const updateList = (selector) => {
        document.querySelectorAll(`${selector} li`).forEach(li => {
            const variantId = li.dataset.variant;
            const staffId = selectedStaffByVariant[variantId];

            const staffSelect = document.querySelector(
                `.staff-select[data-variant="${variantId}"]`
            );

            if (!staffId || !staffSelect) {
                li.querySelector("span:last-child").textContent = "Any available";
                return;
            }

            const selectedOption =
                staffSelect.options[staffSelect.selectedIndex];

            li.querySelector("span:last-child").textContent =
                selectedOption?.textContent || "Any available";
        });
    };

    updateList("#summaryStaffList");
    updateList("#mobileSummaryStaffList");
}

/* ---------------------------
   Render Time Slots
--------------------------- */
function renderTimeSlots(slots) {
    if (!slots.length) {
        timeSlotsBox.innerHTML = `
            <p class="col-span-full text-sm text-gray-400">
                No available times for this date.
            </p>
        `;
        return;
    }

    slots.forEach(time => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = time.label;
        btn.dataset.value = time.value;

        btn.className = `
            slot-btn
            px-3 py-2 rounded-lg border text-center
            hover:border-indigo-600 hover:bg-indigo-50
            transition
        `;

        btn.addEventListener("click", () => selectTime(btn));
        timeSlotsBox.appendChild(btn);
    });
}

/* ---------------------------
   Select Time
--------------------------- */
function selectTime(btn) {
    document.querySelectorAll(".slot-btn").forEach(b => {
        b.classList.remove("bg-indigo-600", "text-white", "border-indigo-600");
    });

    btn.classList.add("bg-indigo-600", "text-white", "border-indigo-600");

    selectedTime = btn.dataset.value;
    updateSummaryTime(btn.textContent);
    updateContinueState();
}

/* ---------------------------
   Helpers
--------------------------- */
function resetTimeSlots() {
    timeSlotsBox.innerHTML = `
        <p class="col-span-full text-sm text-gray-400">
            Please select a date to see available times.
        </p>
    `;
    selectedTime = "";
    if (summaryTime) summaryTime.textContent = "—";
    updateContinueState();
}

function updateContinueState() {
    continueBtn.disabled = !(selectedDate && selectedTime);
}

if (mobileSummaryToggle && mobileSummaryDrawer) {
    mobileSummaryToggle.addEventListener("click", () => {
        mobileSummaryDrawer.classList.remove("translate-y-full");
    });
}

if (closeMobileSummary) {
    closeMobileSummary.addEventListener("click", () => {
        mobileSummaryDrawer.classList.add("translate-y-full");
    });
}

/* ---------------------------
   Continue
--------------------------- */
continueBtn.addEventListener("click", async () => {
    if (!selectedDate || !selectedTime) return;

    allowLeave = true;

    continueBtn.disabled = true;
    document.getElementById("continueText").textContent = "Saving…";
    document.getElementById("continueSpinner").classList.remove("hidden");
    pageLoader.classList.remove("hidden");

    try {
        const res = await fetch("api/save-schedule.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                date: selectedDate,
                time: selectedTime,
                staff_by_variant: selectedStaffByVariant
            })
        });

        const data = await res.json();
        if (!data.success) throw new Error(data.message);

        window.location.href = "step4.php";

    } catch (err) {
        alert(err.message || "Network error.");
        continueBtn.disabled = false;
        document.getElementById("continueText").textContent = "Continue →";
        document.getElementById("continueSpinner").classList.add("hidden");
        pageLoader.classList.add("hidden");
        allowLeave = false;
    }
});
