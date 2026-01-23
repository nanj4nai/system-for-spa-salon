<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: php/login.php");
    exit;
}

$username = $_SESSION["username"];
$role = $_SESSION["role"];

// Block non-admins
if ($role !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once "php/db.php";
require_once "php/company_settings.php";

// Fetch current settings
$settingsResult = $conn->query("SELECT * FROM settings LIMIT 1");
$settings = $settingsResult->fetch_assoc() ?: [
    'spa_name' => '',
    'address' => '',
    'contact_number' => '',
    'invoice_prefix' => 'SPA',
    'vat_rate' => 12,
    'logo_path' => ''
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> – Inventory</title>

    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: "class"
        };
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        html {
            visibility: hidden;
        }

        @keyframes slide-in {
            0% {
                transform: translateX(100%);
                opacity: 0;
            }

            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fade-out {
            0% {
                opacity: 1;
            }

            100% {
                opacity: 0;
            }
        }

        .animate-slide-in {
            animation: slide-in 0.3s ease forwards;
        }

        .animate-fade-out {
            animation: fade-out 0.5s ease forwards;
        }
    </style>
</head>


<body class="flex bg-gray-100 dark:bg-[#121212] transition-all">

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="w-64 md:w-64 h-screen fixed top-0 left-0 bg-gradient-to-b from-[#d6f0ec] to-[#f9e8ff] dark:from-gray-900 dark:to-gray-800 text-gray-800 dark:text-gray-200 shadow-lg transform -translate-x-full md:translate-x-0 transition-all duration-300 z-50">

        <div class="p-6 flex items-center gap-3">
            <i data-lucide="sprout" class="w-7 h-7 text-teal-700 dark:text-teal-300"></i>
            <h1 class="text-xl font-bold text-teal-900 dark:text-teal-200">Settings</h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1">
            <p class="text-xs opacity-70 mb-3">Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)</p>

            <a href="dashboard.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-teal-200 dark:hover:bg-teal-800 transition">
                <i data-lucide="home" class="w-5 h-5"></i> Dashboard
            </a>
            <?php if ($role === "admin"): ?>
                <a href="services.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-purple-200 dark:hover:bg-purple-800 transition">
                    <i data-lucide="scissors" class="w-5 h-5"></i> Services
                </a>
            <?php endif; ?>

            <a href="appointments.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-rose-200 dark:hover:bg-rose-800 transition">
                <i data-lucide="calendar" class="w-5 h-5"></i> Appointments
            </a>
            <a href="clients.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-blue-200 dark:hover:bg-blue-800 transition">
                <i data-lucide="users" class="w-5 h-5"></i> Clients
            </a>
            <?php if ($role === "admin"): ?>
                <a href="inventory.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-green-200 dark:hover:bg-green-800 transition">
                    <i data-lucide="package" class="w-5 h-5"></i> Inventory
                </a>
                <a href="staff.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-yellow-200 dark:hover:bg-yellow-700 transition">
                    <i data-lucide="badge-check" class="w-5 h-5"></i> Employees
                </a>
                <a href="admin-shift-approvals.php"
                    class="flex items-center gap-3 p-3 rounded-xl hover:bg-indigo-200 dark:hover:bg-indigo-800 transition">
                    <i data-lucide="clipboard-check" class="w-5 h-5"></i>
                    Shift Approvals
                </a>
            <?php endif; ?>
            <a href="reports.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-orange-200 dark:hover:bg-orange-800 transition">
                <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Reports
            </a>
            <?php if ($role === "admin"): ?>
                <a href="settings.php" class="flex items-center gap-3 p-3 rounded-xl bg-gray-300 dark:bg-gray-700 transition">
                    <i data-lucide="settings" class="w-5 h-5"></i> Settings
                </a>
            <?php endif; ?>
            <a href="php/logout.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-200 dark:hover:bg-red-800 mt-3 transition">
                <i data-lucide="log-out" class="w-5 h-5"></i> Logout
            </a>
        </nav>

        <div class="p-4 mt-8">
            <button id="darkModeToggle" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                <i data-lucide="moon" class="w-5 h-5 dark:hidden"></i>
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                Dark Mode
            </button>
        </div>
    </aside>

    <!-- MOBILE MENU -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg transition">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <!-- MAIN CONTENT -->
    <main class="flex-1 ml-0 md:ml-64 p-4 md:p-6 text-gray-800 dark:text-gray-200 transition-all">

        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md border-l-4 border-teal-500 dark:border-teal-300 transition">
            <h2 class="text-2xl font-semibold text-teal-900 dark:text-teal-200">Company Settings</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Update your Company details, invoice settings, and logo.</p>
        </div>

        <div class="bg-white dark:bg-gray-800 mt-6 p-4 md:p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700 transition max-w-3xl mx-auto">
            <form id="settingsForm" class="space-y-4">

                <div>
                    <label class="block mb-1 font-medium">Company Name</label>
                    <input type="text" name="spa_name" value="<?= htmlspecialchars($settings['spa_name']) ?>" required
                        class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500 transition">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($settings['address']) ?>" required
                        class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500 transition">
                </div>

                <div>
                    <label class="block mb-1 font-medium">Contact Number</label>
                    <input type="text" name="contact_number" value="<?= htmlspecialchars($settings['contact_number']) ?>" required
                        class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 font-medium">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($settings['invoice_prefix']) ?>" required
                            class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500 transition">
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Your invoices will follow this format: <strong><?= htmlspecialchars($settings['invoice_prefix']) ?>-<?= date('Ymd') ?>-0001</strong>
                        </p>
                    </div>
                    <div>
                        <label class="block mb-1 font-medium">VAT Rate (%)</label>
                        <input type="number" min="0" step="0.01" name="vat_rate" value="<?= htmlspecialchars($settings['vat_rate']) ?>" required
                            class="w-full px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-teal-500 transition">
                    </div>
                </div>

                <div>
                    <label class="block mb-1 font-medium">Logo</label>
                    <input type="file" name="logo" id="logoInput" class="w-full text-gray-900 dark:text-gray-200">
                    <img id="logoPreview" src="<?= htmlspecialchars($settings['logo_path']) ?>" class="mt-2 h-16 <?= $settings['logo_path'] ? '' : 'hidden' ?>" alt="Logo">
                </div>

                <button type="submit" class="px-6 py-2 bg-teal-600 text-white rounded-xl shadow hover:bg-teal-700 transition w-full sm:w-auto">
                    Save Settings
                </button>
            </form>
        </div>
    </main>
    <!-- Toast container -->
    <div id="toastContainer" class="fixed top-4 right-4 flex flex-col gap-2 z-50"></div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.documentElement.style.visibility = "visible";
            lucide.createIcons();

            const sidebar = document.getElementById("sidebar");
            const sidebarToggle = document.getElementById("sidebarToggle");
            const darkToggle = document.getElementById("darkModeToggle");

            if (localStorage.getItem("theme") === "dark") document.documentElement.classList.add("dark");

            sidebarToggle.onclick = () => sidebar.classList.toggle("-translate-x-full");
            darkToggle.onclick = () => {
                document.documentElement.classList.toggle("dark");
                localStorage.setItem("theme", document.documentElement.classList.contains("dark") ? "dark" : "light");
                lucide.createIcons();
            };

            const logoInput = document.getElementById('logoInput');
            const logoPreview = document.getElementById('logoPreview');
            logoInput.addEventListener('change', e => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = () => {
                        logoPreview.src = reader.result;
                        logoPreview.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });

            const form = document.getElementById('settingsForm');
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);

                const res = await fetch('php/settings.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Settings updated successfully.', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Error: ' + data.error, 'error');
                }

            });

            function showToast(message, type = 'success', duration = 3000) {
                const toastContainer = document.getElementById('toastContainer');
                const toast = document.createElement('div');

                const colors = {
                    success: 'bg-green-500',
                    error: 'bg-red-500',
                    info: 'bg-blue-500',
                    warning: 'bg-yellow-500'
                };

                toast.className = `text-white px-4 py-2 rounded shadow-lg ${colors[type] || colors.info} animate-slide-in`;
                toast.textContent = message;

                toastContainer.appendChild(toast);

                setTimeout(() => {
                    toast.classList.add('animate-fade-out');
                    toast.addEventListener('animationend', () => toast.remove());
                }, duration);
            }

        });
    </script>

</body>

</html>