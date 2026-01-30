document.addEventListener("click", (e) => {
    const btn = e.target.closest(".resendBtn");
    if (!btn) return;

    const appointmentId = btn.dataset.appointmentId;

    openConfirmModal({
        title: "Resend Email",
        message: `
            This will resend the last email sent to the client.
            <br><br>
            <span class="text-sm opacity-70">
                (Approved → approval email, Rejected → rejection email)
            </span>
        `,
        onConfirm: async () => {
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = "Sending…";

            try {
                const res = await fetch("php/admin-resend-email.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: `appointment_id=${appointmentId}`
                });

                const data = await res.json();

                if (!data.success) {
                    openAlertModal(data.message || "Failed to resend email");
                    return;
                }

                showToast("Email resent successfully", "green");

            } catch (err) {
                openAlertModal("Network error. Please try again.");
            } finally {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }
    });
});
