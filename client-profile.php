<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: php/login.php");
    exit;
}

require_once "php/company_settings.php";
require_once "php/db.php";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: clients.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT *
    FROM clients
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    header("Location: clients.php");
    exit;
}

$username = $_SESSION["username"];
$role = $_SESSION["role"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> – Client Profile</title>
    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: "class"
        }
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
            <h1 class="text-xl font-bold text-teal-900 dark:text-teal-200">Client's Information</h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1">

            <p class="text-xs opacity-70 mb-3">
                Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)
            </p>

            <a href="dashboard.php"
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-teal-200 dark:hover:bg-teal-800 transition">
                <i data-lucide="home" class="w-5 h-5"></i> Dashboard
            </a>

            <!-- Clients main -->
            <a href="clients.php"
                class="flex items-center gap-3 p-3 rounded-xl hover:bg-blue-200 dark:hover:bg-blue-800 transition">
                <i data-lucide="users" class="w-5 h-5"></i> Clients
            </a>

            <!-- Client profile (sub-page) -->
            <div class="ml-6 border-l border-blue-300 dark:border-blue-700 pl-3">
                <div class="flex items-center gap-2 p-2 rounded-lg
                    bg-blue-100 dark:bg-blue-900
                    text-blue-800 dark:text-blue-200">
                    <i data-lucide="user-circle" class="w-4 h-4"></i>
                    <span class="text-sm font-medium truncate">
                        <?= htmlspecialchars($client['full_name'] . "'s Profile") ?>
                    </span>
                </div>
            </div>

        </nav>


        <div class="p-4 mt-8">
            <button id="darkModeToggle" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                <i data-lucide="moon" class="w-5 h-5 dark:hidden"></i>
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                Dark Mode
            </button>
        </div>
    </aside>

    <button id="sidebarToggle"
        class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg">
        <i data-lucide="menu"></i>
    </button>

    <main class="flex-1 md:ml-64 p-6 text-gray-800 dark:text-gray-200 transition-all">

        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md border-l-4 border-blue-500">
            <div class="flex items-center gap-3 mb-2">
                <a href="clients.php"
                    class="text-blue-600 dark:text-blue-300 hover:underline flex items-center gap-1">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Back
                </a>
            </div>

            <h2 class="text-2xl font-semibold text-blue-900 dark:text-blue-200">
                <?= htmlspecialchars($client['full_name']) ?>
            </h2>
            <p class="mt-1 text-gray-600 dark:text-gray-300">
                Client profile & visit history
            </p>
        </div>

        <!-- INFO CARDS -->
        <div class="grid sm:grid-cols-3 gap-6 mt-6">
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow">
                <p class="text-sm opacity-70">Total Visits</p>
                <p id="totalVisits" class="text-3xl font-bold">—</p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow">
                <p class="text-sm opacity-70">Last Visit</p>
                <p id="lastVisit" class="text-lg font-semibold">—</p>
            </div>
            <div class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow">
                <p class="text-sm opacity-70">Contact</p>
                <p><?= htmlspecialchars($client['contact_number'] ?? '-') ?></p>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($client['email'] ?? '') ?></p>
            </div>
        </div>

        <!-- VISIT HISTORY -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow border dark:border-gray-700">
            <div class="p-4 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold">Visit History</h3>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Services</th>
                        <th class="px-4 py-2 text-left">Amount</th>
                        <th class="px-4 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody id="visitTable" class="divide-y dark:divide-gray-700"></tbody>
            </table>
        </div>

    </main>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="js/client-profile.js"></script>
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