<?php
session_start();
require_once "php/db.php";

/* =====================
   AUTH GUARD
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$settings = $conn->query("SELECT spa_name FROM settings LIMIT 1")->fetch_assoc();
$spaName = $settings['spa_name'] ?? 'My Wellness Spa';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports | <?= htmlspecialchars($spaName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
</head>

<body class="font-poppins bg-gray-50 min-h-screen">

    <!-- Header -->
    <header class="bg-white border-b">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-800">
                Reports
            </h1>

            <a href="dashboard.php"
               class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Back to Dashboard
            </a>
        </div>
    </header>

    <!-- Main -->
    <main class="max-w-6xl mx-auto px-4 py-10">

        <div class="bg-white rounded-2xl border border-dashed border-gray-300
                    p-10 text-center">

            <div class="flex justify-center mb-4">
                <div class="h-14 w-14 rounded-full bg-indigo-100
                            flex items-center justify-center">
                    <i data-lucide="bar-chart-3"
                       class="w-7 h-7 text-indigo-600"></i>
                </div>
            </div>

            <h2 class="text-xl font-semibold text-gray-800 mb-2">
                Reports Module
            </h2>

            <p class="text-gray-500 max-w-md mx-auto">
                Reports and analytics will be added here soon.
                This section will include sales, payments, shifts,
                and performance summaries.
            </p>

            <div class="mt-6 inline-flex items-center gap-2
                        px-4 py-2 rounded-lg
                        bg-gray-100 text-gray-500 text-sm">
                🚧 To be added
            </div>
        </div>

    </main>

    <script>
        lucide.createIcons();
    </script>

</body>
</html>
