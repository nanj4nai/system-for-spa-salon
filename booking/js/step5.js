document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("paymentForm");
    const submitBtn = document.getElementById("submitBtn");
    const confirmCheckbox = document.getElementById("confirmPayment");

    const amountInput = form.querySelector("[name='amount_paid']");
    const refInput = form.querySelector("[name='payment_reference']");
    const proofInput = form.querySelector("[name='payment_proof']");

    const toggleBtn = document.getElementById('toggleGcash');
    const details = document.getElementById('gcashDetails');
    const icon = document.getElementById('toggleIcon');
    const qrThumb = document.getElementById("gcashQrThumb");
    const qrModal = document.getElementById("qrModal");
    const qrModalImg = document.getElementById("qrModalImg");
    const submitHint = document.getElementById("submitHint");
    const balancePreview = document.getElementById("balancePreview");
    const remainingBalanceEl = document.getElementById("remainingBalance");
    const errorBox = document.getElementById("errorBox");
    const errorMessage = document.getElementById("errorMessage");
    const errorActions = document.getElementById("errorActions");
    const restartBookingBtn = document.getElementById("restartBookingBtn");

    let remaining = Number(document.body.dataset.remaining);
    let allowLeave = false;

    /* ===============================
       Enable / Disable Form Elements
    =============================== */
    function enableForm() {
        form.querySelectorAll("input, button").forEach(el => el.disabled = false);
    }

    /* ===============================
    QR Image Zoom
    =============================== */


    if (qrThumb) {
        qrThumb.addEventListener("click", () => {
            qrModalImg.src = qrThumb.src;
            qrModal.classList.remove("hidden");
            qrModal.classList.add("flex");
            document.body.classList.add("overflow-hidden");
        });
    }

    qrModal.addEventListener("click", () => {
        qrModal.classList.add("hidden");
        qrModal.classList.remove("flex");
        qrModalImg.src = "";
        document.body.classList.remove("overflow-hidden");
    });

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && !qrModal.classList.contains("hidden")) {
            qrModal.click();
        }
    });

    if (toggleBtn && details && icon) {
        toggleBtn.addEventListener('click', () => {
            const isHidden = details.classList.contains('hidden');
            details.classList.toggle('hidden');
            icon.textContent = isHidden ? '−' : '+';
        });
    }


    /* ===============================
       Countdown
    =============================== */
    const countdownEl = document.getElementById("countdown");

    function tick() {
        if (remaining <= 0) {
            window.location.href = "step4.php?expired=1";
            return;
        }

        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        countdownEl.textContent = `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
        remaining--;
    }

    tick();
    setInterval(tick, 1000);
    const minDownPayment = Math.round(window.TOTAL_AMOUNT * 0.30 * 100) / 100;

    restartBookingBtn?.addEventListener("click", async () => {
        allowLeave = true; // ✅ prevent unload warning
        await fetch("../booking/api/reset-booking.php");
        window.location.href = "../booking/index.php";
    });


    /* ===============================
       Enable / Disable Submit
    =============================== */
    function validateForm() {
        clearError();
        const missing = [];

        if (!amountInput.value.trim()) {
            missing.push("enter the amount paid");
        }
        if (!refInput.value.trim()) {
            missing.push("enter the reference number");
        }
        if (proofInput.files.length !== 1) {
            missing.push("upload proof of payment");
        }
        if (!confirmCheckbox.checked) {
            missing.push("check the confirmation box below");
        }
        if (remaining <= 0) {
            missing.push("time has expired");
        }
        if (Number(amountInput.value) < minDownPayment) {
            missing.push(`pay at least ₱${minDownPayment.toFixed(2)} (30%)`);
        }

        const paid = Number(amountInput.value || 0);
        const total = window.TOTAL_AMOUNT;
        const remainingBalance = Math.max(total - paid, 0);

        if (paid > 0) {
            balancePreview.classList.remove("hidden");
            remainingBalanceEl.textContent =
                remainingBalance === 0
                    ? "₱0.00 (Paid in full)"
                    : `₱${remainingBalance.toFixed(2)}`;
        } else {
            balancePreview.classList.add("hidden");
        }

        const ok = missing.length === 0;

        submitBtn.disabled = !ok;
        submitBtn.classList.toggle("bg-indigo-600", ok);
        submitBtn.classList.toggle("text-white", ok);
        submitBtn.classList.toggle("cursor-not-allowed", !ok);

        if (ok) {
            submitHint.classList.add("hidden");
        } else {
            submitHint.classList.remove("hidden");
            submitHint.textContent =
                "Please " + missing.join(", ") + " to continue";
        }
    }


    form.addEventListener("input", validateForm);
    confirmCheckbox.addEventListener("change", validateForm);

    /* ===============================
       Submit via JSON
    =============================== */
    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        validateForm();
        if (submitBtn.disabled) return;

        submitBtn.textContent = "Submitting…";
        submitBtn.disabled = true;

        const fd = new FormData(form);

        try {
            form.querySelectorAll("input, button").forEach(el => el.disabled = true);
            const res = await fetch("../booking/api/submit-booking.php", {
                method: "POST",
                body: fd,
                headers: { "Accept": "application/json" }
            });

            const data = await res.json();

            if (!data.success) {
                if (data.message.includes("expired")) {
                    window.location.href = "step4.php?expired=1";
                    return;
                }

                if (data.message.includes("Too many attempts")) {
                    showError(
                        "You’ve tried too many times for this payment session. Please start again to protect your booking.",
                        { showRestart: true }
                    );
                }
                else if (data.message.includes("Client session invalid")) {
                    showError(
                        "Your booking session has ended. Please start again to continue.",
                        { showRestart: true }
                    );
                }
                else {
                    showError(data.message);
                }

                enableForm();
                submitBtn.textContent = "Submit Payment";
                return;
            }


            allowLeave = true;
            window.location.href = "step6.php";

        } catch (err) {
            showError("Network error. Please check your connection and try again.");
            enableForm();
            submitBtn.textContent = "Submit Payment";
        }
    });
    window.addEventListener("beforeunload", (e) => {
        if (allowLeave) return;
        e.preventDefault();
        e.returnValue = "";
    });

    function showError(message, options = {}) {
        errorMessage.textContent = message;
        errorBox.classList.remove("hidden");

        if (options.showRestart) {
            errorActions.classList.remove("hidden");
        } else {
            errorActions.classList.add("hidden");
        }
    }

    function clearError() {
        errorBox.classList.add("hidden");
        errorMessage.textContent = "";
        errorActions.classList.add("hidden");
    }

});
