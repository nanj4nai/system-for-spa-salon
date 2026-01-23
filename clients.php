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
<html lang="en" class="overflow-x-hidden">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> – Clients</title>
    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">

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
    </style>
</head>

<body class="flex bg-gray-100 dark:bg-[#121212] transition-all overflow-x-hidden">

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

            <a href="clients.php" class="flex items-center gap-3 p-3 rounded-xl bg-blue-200 dark:bg-blue-800 transition">
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
    <!-- MOBILE TOGGLE -->
    <button id="sidebarToggle"
        class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg">
        <i data-lucide="menu"></i>
    </button>

    <!-- MAIN -->
    <main class="flex-1 md:ml-64 p-6 text-gray-800 dark:text-gray-200 transition-all">

        <!-- HEADER -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md border-l-4 border-blue-500 dark:border-blue-300">
            <h2 class="text-2xl font-semibold text-blue-900 dark:text-blue-200">Clients</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-300">
                View and manage your customers. Clients are never deleted.
            </p>
        </div>

        <!-- CONTROLS -->
        <div class="mt-6 flex flex-col md:flex-row gap-3 justify-between w-full">
            <input id="searchInput" type="text"
                placeholder="Search client name, phone, email…"
                class="w-full md:w-1/3 px-4 py-2 rounded-lg border dark:border-gray-600
                   bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500">

            <button onclick="openClientModal()"
                class="px-5 py-2 bg-blue-600 text-white rounded-xl shadow hover:bg-blue-700 transition">
                + Add Client
            </button>
        </div>

        <!-- CLIENT TABLE CARD -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border dark:border-gray-700 overflow-x-auto">
            <table class="w-full text-sm table-fixed">
                <thead class="bg-blue-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left hidden sm:table-cell">Contact</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Notes</th>
                        <th class="px-4 py-3 text-left hidden sm:table-cell">Joined</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="clientsTable" class="divide-y dark:divide-gray-700"></tbody>
            </table>
        </div>

    </main>

    <!-- CLIENT MODAL -->
    <div id="clientModal"
        class="fixed inset-0 bg-black/40 hidden z-50
            items-center justify-center
            overflow-y-auto overflow-x-hidden px-3">
        <div
            class="bg-white dark:bg-gray-800 w-full max-w-lg rounded-xl shadow-xl p-6 mx-4
               border border-gray-200 dark:border-gray-700 transition-colors">

            <h3 id="clientModalTitle"
                class="text-xl font-semibold mb-4 text-blue-700 dark:text-blue-300">
                Add Client
            </h3>

            <form id="clientForm" class="space-y-4">
                <input type="hidden" name="id" id="clientId">

                <!-- Full Name -->
                <div>
                    <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="full_name"
                        id="clientName"
                        required
                        placeholder="Client full name"
                        class="w-full px-3 py-2 rounded-lg
                           border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700
                           text-gray-900 dark:text-gray-100
                           placeholder-gray-400 dark:placeholder-gray-400
                           focus:outline-none focus:ring-2 focus:ring-blue-500
                           transition">
                </div>

                <!-- Contact + Email -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">
                            Contact Number
                        </label>
                        <input
                            type="text"
                            name="contact_number"
                            id="clientContact"
                            placeholder="09xx xxx xxxx"
                            class="w-full px-3 py-2 rounded-lg
                               border border-gray-300 dark:border-gray-600
                               bg-white dark:bg-gray-700
                               text-gray-900 dark:text-gray-100
                               placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-blue-500
                               transition">
                    </div>

                    <div>
                        <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">
                            Email
                        </label>
                        <input
                            type="email"
                            name="email"
                            id="clientEmail"
                            placeholder="email@example.com"
                            class="w-full px-3 py-2 rounded-lg
                               border border-gray-300 dark:border-gray-600
                               bg-white dark:bg-gray-700
                               text-gray-900 dark:text-gray-100
                               placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-blue-500
                               transition">
                    </div>
                </div>

                <!-- Address -->
                <div>
                    <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">
                        Address
                    </label>
                    <input
                        type="text"
                        name="address"
                        id="clientAddress"
                        placeholder="Optional address"
                        class="w-full px-3 py-2 rounded-lg
                           border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700
                           text-gray-900 dark:text-gray-100
                           placeholder-gray-400
                           focus:outline-none focus:ring-2 focus:ring-blue-500
                           transition">
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm mb-1 text-gray-700 dark:text-gray-300">
                        Notes
                    </label>
                    <textarea
                        name="notes"
                        id="clientNotes"
                        rows="3"
                        placeholder="Preferences, allergies, remarks..."
                        class="w-full px-3 py-2 rounded-lg
                           border border-gray-300 dark:border-gray-600
                           bg-white dark:bg-gray-700
                           text-gray-900 dark:text-gray-100
                           placeholder-gray-400
                           focus:outline-none focus:ring-2 focus:ring-blue-500
                           transition"></textarea>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-3 pt-4">
                    <button
                        type="button"
                        onclick="closeClientModal()"
                        class="px-4 py-2 rounded-lg
                           bg-gray-200 dark:bg-gray-700
                           text-gray-700 dark:text-gray-200
                           hover:bg-gray-300 dark:hover:bg-gray-600
                           transition">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 rounded-lg
                           bg-blue-600 text-white
                           hover:bg-blue-700
                           focus:ring-2 focus:ring-blue-500
                           transition">
                        Save Client
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TOAST -->
    <div id="successToast"
        class="fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded-lg
           opacity-0 pointer-events-none transition z-50"></div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="js/clients.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.documentElement.style.visibility = "visible";
            lucide.createIcons();

            // Apply saved theme
            if (localStorage.getItem("theme") === "dark") {
                document.documentElement.classList.add("dark");
            }

            // Sidebar toggle
            document.getElementById("sidebarToggle").onclick =
                () => document.getElementById("sidebar").classList.toggle("-translate-x-full");

            // ✅ DARK MODE TOGGLE (THIS WAS MISSING)
            const darkToggle = document.getElementById("darkModeToggle");
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