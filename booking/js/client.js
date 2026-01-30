const form = document.getElementById("clientForm");
const errorBox = document.getElementById("errorBox");
const submitBtn = form.querySelector("button[type='submit']");
const toastContainer = document.getElementById("toastContainer");
const introScreen = document.getElementById("introScreen");
const formScreen = document.getElementById("formScreen");
const startBtn = document.getElementById("startBookingBtn");
/* -----------------------------
   TOAST SYSTEM
----------------------------- */
function showToast(type, message) {
    const colors = {
        success: "bg-green-600",
        error: "bg-red-600",
        info: "bg-indigo-600"
    };

    const toast = document.createElement("div");
    toast.className = `
        pointer-events-auto text-white px-4 py-3 rounded-xl shadow-lg
        text-sm font-medium flex items-start gap-3
        animate-slide-in ${colors[type] || colors.info}
    `;

    toast.innerHTML = `
        <span class="mt-0.5">
            ${type === "success" ? "✓" : type === "error" ? "⚠" : "ℹ"}
        </span>
        <span class="flex-1">${message}</span>
    `;

    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.add("opacity-0");
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function isValidPHPhone(number) {
    // remove spaces, dashes, parentheses
    const cleaned = number.replace(/[\s\-()]/g, "");

    // PH formats:
    // 09XXXXXXXXX
    // +639XXXXXXXXX
    const phRegex = /^(09\d{9}|\+639\d{9})$/;

    return phRegex.test(cleaned);
}

function restoreClientForm() {
    if (!window.BOOKING_CLIENT) return;

    const hasAnyValue = Object.values(window.BOOKING_CLIENT).some(v => v);
    if (!hasAnyValue) return;

    Object.entries(window.BOOKING_CLIENT).forEach(([key, value]) => {
        const input = form.querySelector(`[name="${key}"]`);
        if (input && value) {
            input.value = value;
        }
    });

    introScreen.classList.add("hidden");
    formScreen.classList.remove("hidden");
}


restoreClientForm();

if (startBtn) {
    startBtn.addEventListener("click", () => {
        introScreen.classList.add("hidden");
        formScreen.classList.remove("hidden");
        formScreen.classList.add("animate-fade-in");

        window.scrollTo({ top: 0, behavior: "smooth" });
    });
}

/* -----------------------------
   FORM SUBMIT
----------------------------- */
form.addEventListener("submit", async (e) => {
    e.preventDefault();

    // Clear previous states
    errorBox.style.display = "none";

    const requiredFields = [
        { name: "full_name", label: "Full Name" },
        { name: "email", label: "Email Address" },
        { name: "contact_number", label: "Contact Number" }
    ];

    let firstInvalid = null;

    requiredFields.forEach(f => {
        const input = form.querySelector(`[name="${f.name}"]`);
        input.classList.remove("border-red-500");

        if (!input.value.trim()) {
            input.classList.add("border-red-500");
            if (!firstInvalid) firstInvalid = f.label;
        }
    });

    if (firstInvalid) {
        showToast("error", `${firstInvalid} is required.`);
        return; // ⛔ STOP here
    }

    // Optional: email format check
    const emailInput = form.querySelector('[name="email"]');
    if (!emailInput.checkValidity()) {
        emailInput.classList.add("border-red-500");
        showToast("error", "Please enter a valid email address.");
        return;
    }

    const contactInput = form.querySelector('[name="contact_number"]');

    if (!isValidPHPhone(contactInput.value)) {
        contactInput.classList.add("border-red-500");
        showToast(
            "error",
            "Please enter a valid Philippine mobile number (e.g. 09XXXXXXXXX or +639XXXXXXXXX)."
        );
        return;
    }


    submitBtn.disabled = true;
    submitBtn.textContent = "Please wait...";

    const formData = new FormData(form);

    try {
        const res = await fetch("api/validate-client.php", {
            method: "POST",
            body: formData
        });

        const data = await res.json();

        if (!data.success) {
            showToast("error", data.message || "Something went wrong.");
            submitBtn.disabled = false;
            submitBtn.textContent = "Continue to Services →";
            return;
        }

        showToast("success", "Information saved. Redirecting…");

        setTimeout(() => {
            window.location.href = "step2.php";
        }, 800);

    } catch (err) {
        showToast("error", "Network error. Please try again.");
        submitBtn.disabled = false;
        submitBtn.textContent = "Continue to Services →";
    }
});

form.querySelectorAll("input").forEach(input => {
    input.addEventListener("input", () => {
        input.classList.remove("border-red-500");
    });
});

const contactInput = form.querySelector('[name="contact_number"]');

contactInput.addEventListener("input", () => {
    contactInput.value = contactInput.value.replace(/[^\d+]/g, "");
});

contactInput.addEventListener("blur", () => {
    let v = contactInput.value.trim();
    if (v.startsWith("09")) {
        contactInput.value = "+63" + v.slice(1);
    }
});
