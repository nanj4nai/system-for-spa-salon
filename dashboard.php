<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: php/login");
    exit;
}

require_once "php/company_settings.php";
$username = $_SESSION["username"];
$role = $_SESSION["role"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> – Dashboard</title>

    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    </style>
</head>

<body class="flex bg-gray-100 dark:bg-[#121212] transition-all">

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="w-64 h-screen fixed top-0 left-0 bg-gradient-to-b from-[#d6f0ec] to-[#f9e8ff] 
               dark:from-gray-900 dark:to-gray-800 text-gray-800 dark:text-gray-200 shadow-lg 
               transform -translate-x-full md:translate-x-0 transition-all duration-300">

        <div class="p-6 flex items-center gap-3">
            <i data-lucide="sprout" class="w-7 h-7 text-teal-700 dark:text-teal-300"></i>
            <h1 class="text-xl font-bold text-teal-900 dark:text-teal-200">Dashboard</h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1">
            <p class="text-xs opacity-70 mb-3">Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)</p>

            <a href="dashboard.php" class="flex items-center gap-3 p-3 rounded-xl bg-teal-200 dark:bg-teal-800 transition">
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
                <a href="settings.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-700 transition">
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
    <!-- Success Toast -->
    <div id="successToast"
        class="fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-all duration-300 z-50">
        User saved successfully!
    </div>

    <!-- MOBILE MENU -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg transition">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <!-- MAIN CONTENT -->
    <main class="flex-1 md:ml-64 p-6 text-gray-800 dark:text-gray-200 transition-all">
        <div id="loadingSpinner" class="hidden spinner mt-4"></div>
        <!-- HEADER -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md border-l-4 border-teal-500 dark:border-teal-300 transition">
            <h2 class="text-2xl font-semibold text-teal-900 dark:text-teal-200">Welcome to Dashboard</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Manage appointments, clients, and daily operations.</p>
        </div>

        <!-- STAT CARDS -->
        <div class="grid sm:grid-cols-3 gap-6 mt-6">
            <div class="p-6 rounded-xl bg-white dark:bg-gray-800 shadow border border-teal-200 dark:border-teal-700 hover:-translate-y-1 transition">
                <i data-lucide="calendar-clock" class="w-10 h-10 text-teal-700 dark:text-teal-300"></i>
                <h3 class="mt-3 text-sm font-medium">Upcoming Appointments</h3>
                <p id="upcomingBookings" class="text-3xl font-bold">—</p>
            </div>

            <div class="p-6 rounded-xl bg-white dark:bg-gray-800 shadow border border-purple-200 dark:border-purple-700 hover:-translate-y-1 transition">
                <i data-lucide="smile" class="w-10 h-10 text-purple-700 dark:text-purple-300"></i>
                <h3 class="mt-3 text-sm font-medium">Active Clients</h3>
                <p id="activeBookings" class="text-3xl font-bold">—</p>
            </div>

            <div class="p-6 rounded-xl bg-white dark:bg-gray-800 shadow border border-rose-200 dark:border-rose-700 hover:-translate-y-1 transition">
                <i data-lucide="heart-handshake" class="w-10 h-10 text-rose-700 dark:text-rose-300"></i>
                <h3 class="mt-3 text-sm font-medium">Completed Services Today</h3>
                <p id="totalServices" class="text-3xl font-bold">—</p>
            </div>
        </div>

        <!-- APPOINTMENT LIST + CHART -->
        <div class="grid md:grid-cols-2 gap-6 mt-6">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-teal-200 dark:border-teal-700 transition">
                <h3 class="text-lg font-semibold mb-3">Appointments Today</h3>
                <ul id="dueSoonList" class="space-y-3"></ul>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-purple-200 dark:border-purple-700 transition">
                <h3 class="text-lg font-semibold mb-4">Popular Services</h3>
                <div class="relative h-72">
                    <canvas id="bookingChart"></canvas>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-8">
            <a href="admin-logs" class="px-6 py-3 bg-teal-600 text-white rounded-xl shadow hover:bg-teal-700 transition flex items-center gap-2">
                <i data-lucide="clipboard-list"></i> View Staff Logs
            </a>
        </div>

    </main>

    <script src="js/dashboard.js" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.documentElement.style.visibility = "visible";
            lucide.createIcons();

            const sidebar = document.getElementById("sidebar");
            const sidebarToggle = document.getElementById("sidebarToggle");
            const darkToggle = document.getElementById("darkModeToggle");

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
        });
    </script>

</body>

</html>