<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: php/login.php");
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
    <title><?= htmlspecialchars($company_name) ?> – Activity Logs</title>

    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="js/admin-logs.js"></script>

    <script>
        tailwind.config = {
            darkMode: "class",
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
        class="w-64 h-screen fixed top-0 left-0 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 shadow-lg transform -translate-x-full md:translate-x-0 transition-all duration-300">
        <div class="p-6 flex items-center gap-3">
            <i data-lucide="sprout" class="w-7 h-7 text-teal-700 dark:text-teal-300"></i>
            <h1 class="text-xl font-bold text-teal-900 dark:text-teal-200">Wellness Dashboard</h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1">
            <p class="text-xs opacity-70 mb-3">Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)</p>

            <a href="dashboard.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-teal-200 dark:hover:bg-teal-800 transition">
                <i data-lucide="home" class="w-5 h-5"></i> Dashboard
            </a>

            <a href="admin-logs.php" class="flex items-center gap-3 p-3 rounded-xl bg-purple-200 dark:bg-purple-800 transition">
                <i data-lucide="clipboard-list" class="w-5 h-5"></i> Activity Logs
            </a>

            <a href="php/logout.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-red-200 dark:hover:bg-red-800 mt-3 transition">
                <i data-lucide="log-out" class="w-5 h-5"></i> Logout
            </a>
        </nav>

        <div class="p-4 mt-8">
            <button id="darkModeToggle"
                class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                <i data-lucide="moon" class="w-5 h-5 dark:hidden"></i>
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                Dark Mode
            </button>
        </div>
    </aside>

    <!-- MOBILE MENU -->
    <button id="sidebarToggle"
        class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <!-- MAIN CONTENT -->
    <main class="flex-1 md:ml-64 p-6 text-gray-800 dark:text-gray-200 transition-all">

        <!-- HEADER -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 border-l-4 border-purple-500 dark:border-purple-300">
            <h2 class="text-2xl font-semibold text-purple-900 dark:text-purple-200">Activity Logs</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-300">View all user activities and changes in the Wellness Spa System.</p>
        </div>

        <!-- FILTERS -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 mt-6 flex flex-wrap gap-4 p-6">

            <div class="flex flex-col">
                <label for="searchLogs" class="mb-1 font-medium text-gray-800 dark:text-gray-200">Search</label>
                <input type="text" id="searchLogs" placeholder="Search logs..."
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 transition">
            </div>

            <div class="flex flex-col">
                <label for="filterAction" class="mb-1 font-medium text-gray-800 dark:text-gray-200">Action</label>
                <select id="filterAction"
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 transition">
                    <option value="">All Actions</option>
                </select>
            </div>

            <div class="flex flex-col">
                <label for="startDate" class="mb-1 font-medium text-gray-800 dark:text-gray-200">From</label>
                <input type="date" id="startDate"
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 transition">
            </div>

            <div class="flex flex-col">
                <label for="endDate" class="mb-1 font-medium text-gray-800 dark:text-gray-200">To</label>
                <input type="date" id="endDate"
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 transition">
            </div>

            <div class="flex items-end">
                <button id="exportLogs" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded flex items-center gap-1 transition">
                    <i data-lucide="save" class="w-4 h-4"></i> Export
                </button>
            </div>
        </div>

        <!-- LOGS TABLE -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 mt-6 overflow-x-auto transition">
            <table class="min-w-full table-auto border-collapse">
                <thead class="bg-gray-100 dark:bg-gray-700 text-left text-gray-800 dark:text-gray-200">
                    <tr>
                        <th class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">ID</th>
                        <th class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">User</th>
                        <th class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">Action</th>
                        <th class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">Description</th>
                        <th class="px-4 py-2 border-b border-gray-300 dark:border-gray-600">Timestamp</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody" class="text-gray-900 dark:text-gray-200">
                    <tr>
                        <td colspan="5" class="text-center py-4">Loading logs...</td>
                    </tr>
                </tbody>
            </table>
            <div id="paginationControls" class="mt-4 flex justify-center gap-2"></div>
        </div>

    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.documentElement.style.visibility = "visible";
            lucide.createIcons();

            const sidebar = document.getElementById("sidebar");
            const sidebarToggle = document.getElementById("sidebarToggle");
            const darkToggle = document.getElementById("darkModeToggle");

            // Apply saved theme on load
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