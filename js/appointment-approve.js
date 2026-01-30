document.addEventListener("DOMContentLoaded", () => {
    document.documentElement.style.visibility = "visible";
    lucide.createIcons();

    const sidebar = document.getElementById("sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const darkToggle = document.getElementById("darkModeToggle");
    const loadingOverlay = document.getElementById("loadingOverlay");
    /* ===============================
        GENERIC MODALS
        =============================== */
    const confirmModal = document.getElementById("confirmModal");
    const confirmTitle = document.getElementById("confirmTitle");
    const confirmMessage = document.getElementById("confirmMessage");
    const confirmOk = document.getElementById("confirmOk");
    const confirmCancel = document.getElementById("confirmCancel");

    const alertModal = document.getElementById("alertModal");
    const alertMessage = document.getElementById("alertMessage");
    const alertOk = document.getElementById("alertOk");

    // Apply saved theme
    if (localStorage.getItem("theme") === "dark") {
        document.documentElement.classList.add("dark");
    }

    sidebarToggle.onclick = () => sidebar.classList.toggle("-translate-x-full");

    darkToggle.onclick = () => {
        document.documentElement.classList.toggle("dark");
        const isDark = document.documentElement.classList.contains("dark");
        localStorage.setItem("theme", isDark ? "dark" : "light");
        lucide.createIcons();
    };

    const pendingList = document.getElementById("pendingList");
    const paymentList = document.getElementById("paymentList");


    function showLoading(text = "Processing…") {
        const label = loadingOverlay.querySelector("span");
        if (label) label.textContent = text;

        loadingOverlay.classList.remove("hidden");
        loadingOverlay.classList.add("flex");
        document.body.classList.add("overflow-hidden");
    }

    function hideLoading() {
        loadingOverlay.classList.add("hidden");
        loadingOverlay.classList.remove("flex");
        document.body.classList.remove("overflow-hidden");
    }
    /* ===============================
       PROOF MODAL
    =============================== */
    const proofModal = document.getElementById("proofModal");
    const proofImg = document.getElementById("proofImage");
    const closeProofBtn = document.getElementById("closeProofModal");

    function closeProofModal() {
        proofModal.classList.add("hidden");
        proofModal.classList.remove("flex");
        proofImg.src = "";
        document.body.classList.remove("overflow-hidden");
    }

    closeProofBtn.onclick = closeProofModal;
    proofModal.onclick = e => e.target === proofModal && closeProofModal();

    /* ===============================
       REJECT MODAL
    =============================== */
    const rejectModal = document.getElementById("rejectModal");
    const rejectReason = document.getElementById("rejectReason");
    const cancelReject = document.getElementById("cancelReject");
    const confirmReject = document.getElementById("confirmReject");

    let activeAppointmentId = null;

    cancelReject.onclick = () => {
        rejectModal.classList.add("hidden");
        rejectModal.classList.remove("flex");
        document.body.classList.remove("overflow-hidden");
        activeAppointmentId = null;
    };

    /* ===============================
       EVENT DELEGATION (IMPORTANT)
    =============================== */
    paymentList.addEventListener("click", async (e) => {
        const viewBtn = e.target.closest(".viewProofBtn");
        const approveBtn = e.target.closest(".approveBtn");
        const rejectBtn = e.target.closest(".rejectBtn");
        const resendBtn = e.target.closest(".resendBtn");
        /* ---- VIEW PROOF ---- */
        if (viewBtn) {
            proofImg.src = viewBtn.dataset.proofSrc;
            proofModal.classList.remove("hidden");
            proofModal.classList.add("flex");
            document.body.classList.add("overflow-hidden");
        }

        /* ---- APPROVE ---- */
        /* ---- APPROVE ---- */
        if (approveBtn) {
            const appointmentId = approveBtn.dataset.appointmentId;

            openConfirmModal({
                title: "Approve Payment",
                message: `
            This will:
            <ul class="list-disc ml-5 mt-2">
                <li>Confirm the appointment</li>
                <li>Send an email to the client</li>
            </ul>
        `,
                onConfirm: async () => {
                    showLoading("Approving payment…");

                    try {
                        const res = await fetch("php/admin-approve-pay.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: `appointment_id=${appointmentId}`
                        });

                        const data = await res.json();

                        if (!data.success) {
                            openAlertModal(data.message || "Approval failed");
                            return;
                        }

                        showToast("Payment approved successfully", "green");

                        // Remove from pending list
                        approveBtn.closest(".rounded-xl")?.remove();

                    } catch (err) {
                        openAlertModal("Network error");
                    } finally {
                        hideLoading();
                    }
                }
            });
        }

        /* ---- REJECT ---- */
        if (rejectBtn) {
            const appointmentId = rejectBtn.dataset.appointmentId;

            openConfirmModal({
                title: "Reject Payment",
                message: `
                    Are you sure you want to reject this payment?
                    <br><br>
                    The client will be notified by email.
                `,
                onConfirm: () => {
                    activeAppointmentId = appointmentId;
                    rejectReason.value = "";
                    rejectModal.classList.remove("hidden");
                    rejectModal.classList.add("flex");
                    document.body.classList.add("overflow-hidden");
                }
            });
        }
        /* ---- RESEND EMAIL ---- */
        if (resendBtn) {
            const appointmentId = resendBtn.dataset.appointmentId;

            openConfirmModal({
                title: "Resend Email",
                message: `
            This will resend the email to the client.
            <br><br>
            Cooldown applies (60 seconds).
        `,
                onConfirm: async () => {
                    showLoading("Resending email…");

                    try {
                        const res = await fetch("php/admin-resend-email.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: `appointment_id=${appointmentId}`
                        });

                        const data = await res.json();

                        if (!data.success) {
                            openAlertModal(data.message || "Failed to resend email");
                            return;
                        }

                        showToast("Email resent successfully", "green");

                        const timeEl = resendBtn.nextElementSibling;
                        if (timeEl) {
                            timeEl.textContent = "Last sent: just now";
                        }

                    } catch (err) {
                        openAlertModal("Network error");
                    } finally {
                        hideLoading();
                    }
                }
            });
        }
    });

    function openConfirmModal({ title, message, onConfirm }) {
        confirmTitle.textContent = title;
        confirmMessage.innerHTML = message;

        confirmModal.classList.remove("hidden");
        confirmModal.classList.add("flex");
        document.body.classList.add("overflow-hidden");

        const cleanup = () => {
            confirmModal.classList.add("hidden");
            confirmModal.classList.remove("flex");
            document.body.classList.remove("overflow-hidden");
            confirmOk.onclick = null;
            confirmCancel.onclick = null;
        };

        confirmCancel.onclick = cleanup;
        confirmOk.onclick = () => {
            cleanup();
            onConfirm();
        };
    }

    function openAlertModal(message) {
        alertMessage.textContent = message;
        alertModal.classList.remove("hidden");
        alertModal.classList.add("flex");
        document.body.classList.add("overflow-hidden");

        alertOk.onclick = () => {
            alertModal.classList.add("hidden");
            alertModal.classList.remove("flex");
            document.body.classList.remove("overflow-hidden");
        };
    }

    confirmReject.onclick = async () => {
        if (!rejectReason.value.trim()) {
            openAlertModal("Please enter a rejection reason.");
            return;
        }

        showLoading("Rejecting payment…");

        try {
            const res = await fetch("php/admin-reject-pay.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `appointment_id=${activeAppointmentId}&reason=${encodeURIComponent(rejectReason.value)}`
            });

            const data = await res.json();
            if (!data.success) {
                openAlertModal(data.message || "Reject failed");
                return;
            }

            showToast("Payment rejected successfully", "red");
            document
                .querySelector(`[data-appointment-id="${activeAppointmentId}"]`)
                ?.closest(".rounded-xl")
                ?.remove();

            cancelReject.onclick();
        } catch (err) {
            openAlertModal("Network error");
        } finally {
            hideLoading();
        }
    };

    /* ===============================
       AUTO REFRESH
    =============================== */
    async function refreshPayments() {
        try {
            pendingList.classList.add("opacity-50");

            const res = await fetch("admin-render-pending-payments.php");
            const data = await res.json();

            if (!data.success) return;

            pendingList.innerHTML = data.html;
            lucide.createIcons();
        } catch (err) {
            console.error("Auto-refresh failed", err);
        } finally {
            pendingList.classList.remove("opacity-50");
        }
    }


    refreshPayments();
    setInterval(refreshPayments, 5000);

    /* ===============================
       TOAST
    =============================== */
    const toast = document.getElementById("successToast");
    function showToast(message, color = "green") {
        toast.textContent = message;
        toast.className =
            `fixed top-6 right-6 px-4 py-2 rounded-lg shadow-lg z-50
             ${color === "red" ? "bg-red-600" : "bg-green-600"}
             text-white transition-all`;

        toast.style.opacity = "1";
        setTimeout(() => toast.style.opacity = "0", 2500);
    }
});
