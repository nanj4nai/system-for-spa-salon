const themeToggle = document.getElementById("themeToggle");
const themeIcon = document.getElementById("themeIcon");
const helpToggle = document.getElementById('helpToggle');
const tips = document.querySelectorAll('.panel-tip');
const helpIcon = document.getElementById("helpIcon");
const dateTimeText = document.getElementById("dateTimeText");

lucide.createIcons();


function updateDateTime() {
    if (!dateTimeText) return;

    const now = new Date();

    const date = now.toLocaleDateString(undefined, {
        weekday: "short",
        month: "short",
        day: "numeric"
    });

    const time = now.toLocaleTimeString(undefined, {
        hour: "2-digit",
        minute: "2-digit"
    });

    dateTimeText.textContent = `${date} • ${time}`;
}

// initial render
updateDateTime();

// update every minute (no overkill)
setInterval(updateDateTime, 60 * 1000);

function renderThemeIcon(mode) {
    if (!themeIcon) return;

    themeIcon.innerHTML = `
        <i data-lucide="${mode === 'dark' ? 'sun' : 'moon'}"
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

function setHelpMode(on) {
    tips.forEach(t => t.classList.toggle("hidden", !on));
    localStorage.setItem("cashier_help", on ? "1" : "0");

    // icon swap
    if (helpIcon) {
        helpIcon.innerHTML = `
            <i data-lucide="${on ? 'circle-help' : 'circle-question-mark'}"
               class="w-4 h-4"></i>
        `;
        lucide.createIcons();
    }

    // dim button when OFF
    helpToggle.classList.toggle("opacity-40", !on);
    helpToggle.classList.toggle("hover:opacity-90", on);
}
const helpEnabled = localStorage.getItem("cashier_help") === "1";
setHelpMode(helpEnabled);


if (helpToggle) {
    helpToggle.addEventListener("click", () => {
        const enabled = localStorage.getItem("cashier_help") === "1";
        setHelpMode(!enabled);

        if (typeof showToast === "function") {
            showToast(!enabled ? "Tips enabled" : "Tips hidden");
        }
    });
}

// restore help state
if (localStorage.getItem("cashier_help") === "1") {
    setHelpMode(true);
}