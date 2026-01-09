<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: php/login");
    exit;
}
$username = $_SESSION["username"];
$role = $_SESSION["role"];

require_once "php/db.php"; // Make sure this connects to wellness_spa_db
require_once "php/company_settings.php";
// Fetch services with categories, including description
$services = $conn->query("
    SELECT s.id, s.name, s.base_price, s.default_commission_percent, s.description, c.name AS category
    FROM services s
    LEFT JOIN service_categories c ON s.category_id = c.id
    ORDER BY s.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch categories for the Add/Edit form
$categories = $conn->query("SELECT * FROM service_categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> – Services Management</title>

    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slideIn {
            animation: slideDown 0.25s ease-out;
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
            <i data-lucide="scissors" class="w-7 h-7 text-teal-700 dark:text-teal-300"></i>
            <h1 class="text-xl font-bold text-teal-900 dark:text-teal-200">Services</h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1">
            <p class="text-xs opacity-70 mb-3">Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)</p>

            <a href="dashboard.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-teal-200 dark:hover:bg-teal-800 transition">
                <i data-lucide="home" class="w-5 h-5"></i> Dashboard
            </a>
            <?php if ($role === "admin"): ?>
                <a href="services.php" class="flex items-center gap-3 p-3 rounded-xl bg-purple-200 dark:bg-purple-800 transition">
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
            <?php endif; ?>
            <?php if ($role === "admin"): ?>
                <a href="staff.php" class="flex items-center gap-3 p-3 rounded-xl hover:bg-yellow-200 dark:hover:bg-yellow-700 transition">
                    <i data-lucide="badge-check" class="w-5 h-5"></i> Employees
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

    <!-- MOBILE MENU -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg transition">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <!-- MAIN CONTENT -->
    <main class="flex-1 md:ml-64 p-6 text-gray-800 dark:text-gray-200 transition-all">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md border-l-4 border-purple-500 dark:border-purple-300 transition">
            <h2 class="text-2xl font-semibold text-purple-900 dark:text-purple-200">Services Management</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Manage your spa services and categories.</p>
        </div>
        <!-- Services Table -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-purple-200 dark:border-purple-700 transition">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">All Services</h3>
                <button id="addServiceBtn" class="px-4 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition">Add Service</button>
            </div>
            <div class="overflow-x-auto">
                <!-- Search & Filter -->
                <div class="flex flex-wrap gap-3 mb-4">
                    <input
                        id="serviceSearch"
                        type="text"
                        placeholder="Search services..."
                        class="px-3 py-2 border rounded w-full md:w-64 dark:bg-gray-700 dark:text-white" />

                    <select
                        id="categoryFilter"
                        class="px-3 py-2 border rounded w-full md:w-56 dark:bg-gray-700 dark:text-white">
                        <option value="">All Categories</option>
                    </select>
                </div>

                <table id="servicesTable" class="hidden md:table min-w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-purple-100 dark:bg-purple-700">
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Category</th>
                            <th class="px-4 py-2">Prices</th>
                            <th class="px-4 py-2">Commission %</th>
                            <th class="px-4 py-2">Notes</th>
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- JS will populate -->
                    </tbody>
                </table>
                <div id="servicesMobile"
                    class="md:hidden space-y-4">
                    <!-- JS will populate service cards here -->
                </div>

                <!-- Pagination -->
                <div id="servicesPagination" class="flex flex-col md:flex-row
                        md:justify-between md:items-center
                        gap-3 mt-4">
                    <p id="paginationInfo" class="text-sm text-gray-600 dark:text-gray-300"></p>

                    <div class="flex gap-2">
                        <button id="prevPage"
                            class="px-3 py-1 rounded bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 disabled:opacity-50">
                            Prev
                        </button>

                        <div id="pageNumbers" class="flex gap-1"></div>

                        <button id="nextPage"
                            class="px-3 py-1 rounded bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 disabled:opacity-50">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Service Form Modal -->
        <div id="serviceFormContainer" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg
                    w-11/12 max-w-4xl
                    max-h-[90vh] overflow-hidden
                    relative">
                <!-- Header -->
                <div class="px-6 py-4 border-b dark:border-gray-700">
                    <h3 id="formTitle" class="text-xl font-semibold">Add Service</h3>
                </div>
                <div class="p-6 overflow-y-auto max-h-[70vh]">
                    <form id="serviceForm">
                        <input type="hidden" name="service_id" id="service_id">
                        <input type="hidden" name="service_products" id="service_products">

                        <div class="mb-3">
                            <label class="block mb-1">Service Name</label>
                            <input type="text" name="name" id="name" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-white" required>
                        </div>
                        <div class="mb-3">
                            <label class="block mb-1">Category</label>
                            <select name="category_id" id="category_id" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="block mb-1">Base Price</label>
                            <input type="number" name="base_price" id="base_price" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-white" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="block mb-1">Default Commission %</label>
                            <input type="number" name="default_commission_percent" id="default_commission_percent" placeholder="10%, 25%" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-white" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="block mb-1">Description / Notes</label>
                            <textarea name="description" id="description" rows="3" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-white" placeholder="Optional notes or details about the service"></textarea>
                        </div>
                        <!-- PRODUCTS -->
                        <div id="productContainer" class="mb-3" data-role="service-products">
                            <p id="noProductsText" class="text-sm text-gray-500">
                                No products added to this service yet.
                            </p>
                        </div>

                        <button type="button" id="addProductBtn" class="px-3 py-1 mb-3 bg-blue-500 text-white rounded">Add Product</button>

                        <!-- VARIANTS -->
                        <div id="variantContainer" class="mb-3" data-role="service-variants"></div>

                        <button type="button" id="addVariantBtn" class="px-3 py-1 mb-3 bg-teal-500 text-white rounded">Add Variant</button>


                        <div class="flex justify-end gap-2 mt-4">
                            <button type="button" id="cancelServiceBtn" class="px-4 py-2 bg-gray-400 dark:bg-gray-600 text-white rounded hover:bg-gray-500 transition">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Inside <main> after Services Table -->
        <div class="mt-8 bg-white dark:bg-gray-800 rounded-xl shadow p-6 border border-purple-200 dark:border-purple-700 transition">
            <div class="flex flex-col md:flex-row 
            md:justify-between md:items-center 
            gap-3 mb-4">

                <h3 class="text-lg font-semibold">Service Categories</h3>
                <button id="addCategoryBtn" class="px-4 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition">Add Category</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-purple-100 dark:bg-purple-700">
                            <th class="px-4 py-2">Category Name</th>
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <td class="px-4 py-2"><?= htmlspecialchars($cat['name']) ?></td>
                                <td class="px-4 py-2">
                                    <button class="editCategoryBtn px-2 py-1 bg-yellow-400 rounded hover:bg-yellow-500 text-white" data-id="<?= $cat['id'] ?>">Edit</button>
                                    <button class="deleteCategoryBtn px-2 py-1 bg-red-500 rounded hover:bg-red-600 text-white" data-id="<?= $cat['id'] ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- Categories Pagination -->
                <div class="flex justify-between items-center mt-4">
                    <p id="categoryPageInfo" class="text-sm text-gray-600 dark:text-gray-300"></p>

                    <div class="flex gap-2">
                        <button id="prevCategoryPage"
                            class="px-3 py-1 rounded bg-gray-300 dark:bg-gray-600 disabled:opacity-50">
                            Prev
                        </button>

                        <button id="nextCategoryPage"
                            class="px-3 py-1 rounded bg-gray-300 dark:bg-gray-600 disabled:opacity-50">
                            Next
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Add/Edit Category Form Modal -->
        <div id="categoryFormContainer" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 w-96 relative">
                <h3 id="categoryFormTitle" class="text-xl font-semibold mb-4">Add Category</h3>
                <form id="categoryForm">
                    <!-- Changed ID to be unique -->
                    <input type="hidden" name="category_id_input" id="category_id_input">
                    <div class="mb-3">
                        <label class="block mb-1">Category Name</label>
                        <input type="text" name="name" id="category_name" class="w-full px-3 py-2 border rounded dark:bg-gray-700 dark:text-white" required>
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <button type="button" id="cancelCategoryBtn" class="px-4 py-2 bg-gray-400 dark:bg-gray-600 text-white rounded hover:bg-gray-500 transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 transition">Save</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <!-- Notes Modal -->
    <div id="notesModal" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden z-50">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 w-11/12 max-w-2xl relative">
            <h3 class="text-gray-900 dark:text-gray-200 font-semibold mb-4 text-lg md:text-xl">Service Notes</h3>
            <p id="notesContent" class="text-gray-800 dark:text-gray-200 
          whitespace-pre-wrap 
          text-sm md:text-lg"></p>
            <button id="closeNotesBtn" class="absolute top-3 right-3 px-2 py-1 bg-gray-400 dark:bg-gray-600 text-white rounded hover:bg-gray-500 transition">X</button>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-black/40 flex items-center justify-center hidden z-50">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 w-80">
            <h3 id="confirmModalTitle" class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Confirm</h3>
            <p id="confirmModalMessage" class="text-gray-600 dark:text-gray-300 mb-6"></p>
            <div class="flex justify-end gap-3">
                <button id="cancelConfirmBtn" class="px-4 py-2 rounded bg-gray-200 dark:bg-gray-700">Cancel</button>
                <button id="confirmBtn" class="px-4 py-2 rounded bg-red-500 text-white">Delete</button>
            </div>
        </div>
    </div>


    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="js/services.js" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.documentElement.style.visibility = "visible";
            lucide.createIcons();

            const sidebar = document.getElementById("sidebar");
            const sidebarToggle = document.getElementById("sidebarToggle");
            const darkToggle = document.getElementById("darkModeToggle");

            sidebarToggle.onclick = () => sidebar.classList.toggle("-translate-x-full");
            darkToggle.onclick = () => {
                document.documentElement.classList.toggle("dark");
                localStorage.setItem("theme", document.documentElement.classList.contains("dark") ? "dark" : "light");
                lucide.createIcons();
            };

            if (localStorage.getItem("theme") === "dark") document.documentElement.classList.add("dark");

        });
    </script>
</body>

</html>