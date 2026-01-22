const themeToggle = document.getElementById("themeToggle");
const themeIcon = document.getElementById("themeIcon");
const helpToggle = document.getElementById("helpToggle");
const tips = document.querySelectorAll(".panel-tip");
const helpIcon = document.getElementById("helpIcon");
const dateTimeText = document.getElementById("dateTimeText");

lucide.createIcons();

/* =========================
   REALTIME DATE & TIME
========================= */

function formatDateTime(now = new Date()) {
    const date = now.toLocaleDateString(undefined, {
        weekday: "short",
        month: "short",
        day: "numeric"
    });

    const time = now.toLocaleTimeString(undefined, {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit" // realtime feel
    });

    return `${date} • ${time}`;
}

function startRealtimeClock() {
    if (!dateTimeText) return;

    // initial render
    dateTimeText.textContent = formatDateTime();

    // align to the next exact second
    const now = new Date();
    const delay = 1000 - now.getMilliseconds();

    setTimeout(() => {
        setInterval(() => {
            dateTimeText.textContent = formatDateTime();
        }, 1000);
    }, delay);
}

// start clock
startRealtimeClock();

/* =========================
   THEME
========================= */

function renderThemeIcon(mode) {
    if (!themeIcon) return;

    themeIcon.innerHTML = `
        <i data-lucide="${mode === "dark" ? "sun" : "moon"}"
           class="w-5 h-5"></i>
    `;
    lucide.createIcons();
}

function setTheme(mode) {
    document.documentElement.classList.toggle("dark", mode === "dark");
    localStorage.setItem("theme", mode);
    renderThemeIcon(mode);
}

const savedTheme = localStorage.getItem("theme") || "light";
setTheme(savedTheme);

themeToggle?.addEventListener("click", () => {
    const isDark = document.documentElement.classList.contains("dark");
    setTheme(isDark ? "light" : "dark");
});

/* =========================
   HELP / TIPS
========================= */

function setHelpMode(on) {
    tips.forEach(t => t.classList.toggle("hidden", !on));
    localStorage.setItem("cashier_help", on ? "1" : "0");

    if (helpIcon) {
        helpIcon.innerHTML = `
            <i data-lucide="${on ? "circle-help" : "circle-question-mark"}"
               class="w-4 h-4"></i>
        `;
        lucide.createIcons();
    }

    helpToggle.classList.toggle("opacity-40", !on);
    helpToggle.classList.toggle("hover:opacity-90", on);
}

const helpEnabled = localStorage.getItem("cashier_help") === "1";
setHelpMode(helpEnabled);

helpToggle?.addEventListener("click", () => {
    const enabled = localStorage.getItem("cashier_help") === "1";
    setHelpMode(!enabled);

    if (typeof showToast === "function") {
        showToast(!enabled ? "Tips enabled" : "Tips hidden");
    }
});
