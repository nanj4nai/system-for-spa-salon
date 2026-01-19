<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: dashboard.php");
    exit;
}

require_once "php/db.php";
require_once "php/company_settings.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shift Approvals</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 dark:bg-[#121212] p-6 text-gray-800 dark:text-gray-200">

    <h1 class="text-2xl font-semibold mb-6">Pending Shift Closures</h1>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-6">
        <table class="w-full text-sm">
            <thead class="text-left border-b">
                <tr>
                    <th class="py-2">Cashier</th>
                    <th>Opened At</th>
                    <th>Opening Cash</th>
                    <th>Declared Closing</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="shiftTable">
                <tr>
                    <td colspan="6" class="py-6 text-center text-gray-400">
                        Loading pending shifts…
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <script src="js/admin-shift-approvals.js"></script>
</body>
</html>
