<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
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
    <title><?= htmlspecialchars($company_name) ?> – Inventory</title>

    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind CSS -->
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

        /* Fade-in animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-fadeIn {
            animation: fadeIn 0.2s ease-out forwards;
        }

        #previewImage {
            max-height: 150px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 0.5rem;
            cursor: pointer;
        }

        #categoryModal,
        #tableImageModal,
        #itemFormContainer {
            max-width: 95vw;
            /* prevent horizontal overflow */
        }
    </style>
</head>

<body class="flex bg-gray-100 dark:bg-[#121212] transition-all overflow-x-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="fixed top-0 left-0 w-64 h-screen z-50 bg-gray-100 dark:bg-gray-900 shadow-lg transform -translate-x-full md:translate-x-0 transition-transform duration-300 overflow-y-auto">
        <div class="p-6 flex items-center gap-3">
            <i data-lucide="package" class="w-7 h-7 text-green-700 dark:text-green-300"></i>
            <h1 class="text-xl font-bold text-green-900 dark:text-green-200">Inventory</h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1 text-gray-800 dark:text-white">
            <p class="text-xs mb-3">Welcome, <?= htmlspecialchars($username) ?> (<?= htmlspecialchars($role) ?>)</p>

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
                <a href="inventory.php" class="flex items-center gap-3 p-3 rounded-xl bg-green-200 dark:bg-green-700 transition">
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


    <!-- SUCCESS TOAST -->
    <div id="successToast"
        class="fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-all duration-300 z-50"></div>

    <!-- MOBILE MENU BUTTON -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-green-500 dark:bg-green-600 shadow-lg transition hover:bg-green-600 dark:hover:bg-green-500">
        <i data-lucide="menu" class="w-6 h-6 text-white"></i>
    </button>

    <!-- MAIN CONTENT -->
    <main class="flex-1 md:ml-64 p-6 text-gray-800 dark:text-gray-200 transition-all max-w-full">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md border-l-4 border-green-500 dark:border-green-300 transition">
            <h2 class="text-2xl font-semibold text-green-900 dark:text-green-200">Inventory Management</h2>
            <p class="mt-1 text-gray-600 dark:text-gray-300">Manage your products, stock, and pricing.</p>
        </div>

        <div class="flex justify-end mt-6">
            <button id="addItemBtn" class="px-6 py-2 bg-green-500 text-white rounded-xl shadow hover:bg-green-600 transition">Add Product</button>
        </div>

        <!-- Add/Edit Form -->
        <div id="itemFormContainer"
            class="bg-white dark:bg-gray-800 rounded-xl p-6 sm:p-8 shadow-lg mt-6 hidden transition-all 
            max-w-full sm:max-w-3xl md:max-w-3xl mx-auto">
            <h3 id="formTitle" class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Add Product</h3>
            <form id="itemForm" class="grid grid-cols-1 sm:grid-cols-2 gap-4 md:gap-4 items-end" enctype="multipart/form-data">
                <input type="hidden" id="itemId" name="id">

                <!-- Product Name -->
                <div class="flex flex-col">
                    <label class="text-sm font-medium mb-2">Product Name</label>
                    <input type="text" id="itemName" name="name" required
                        class="w-full px-3 sm:px-5 py-2 sm:py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                </div>

                <!-- Category + Tooltip -->
                <div class="flex flex-col relative w-full mt-4">
                    <label class="text-sm font-medium mb-2 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <span>Category</span>
                        <div class="relative">
                            <button type="button" id="manageCategoryBtn"
                                class="mt-2 sm:mt-0 sm:ml-2 flex items-center justify-center w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition shadow-md group"
                                title="Manage Categories">
                                <i data-lucide="settings" class="w-5 h-5"></i>
                            </button>
                            <span id="manageCategoryTooltip"
                                class="absolute left-1/2 transform -translate-x-1/2 bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900 text-xs font-medium rounded px-3 py-2 text-center max-w-[220px] break-words opacity-0 pointer-events-none transition-opacity group-hover:opacity-100 z-40 -top-2 sm:-top-12">
                                Manage categories
                            </span>
                        </div>
                    </label>
                    <select id="itemCategory" name="category_id"
                        class="w-full px-3 sm:px-5 py-2 sm:py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                        <option value="">-- Select Category --</option>
                    </select>
                </div>
                <!-- Product Type -->
                <div class="flex flex-col">
                    <label class="text-sm font-medium mb-2">Product Type</label>
                    <select id="itemProductType" name="product_type"
                        class="w-full px-3 sm:px-5 py-2 sm:py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                        <option value="consumable">Consumable (used by amount)</option>
                        <option value="one_time">One-time use</option>
                        <option value="reusable">Reusable</option>
                    </select>
                </div>
                <!-- Unit -->
                <div class="flex flex-col">
                    <label class="text-sm font-medium mb-2">Unit</label>
                    <select id="itemUnit" name="unit"
                        class="w-full px-3 md:px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                        <option value="pcs">Pieces</option>
                        <option value="ml">Milliliters (ml)</option>
                        <option value="mg">Milligrams (mg)</option>
                    </select>
                </div>
                <!-- Unit per Item (Consumables only) -->
                <div id="unitPerItemContainer" class="flex flex-col sm:col-span-2 hidden">
                    <label class="text-sm font-medium mb-1">
                        Amount per Package
                    </label>
                    <p class="text-xs text-gray-500 mb-2">
                        Example: 1000 ml per pouch, 60 mg per pack
                    </p>
                    <input type="number" id="itemUnitPerItem" name="unit_per_item" min="0"
                        class="w-full px-3 md:px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                    <!-- Number of Packages (Consumables only) -->
                    <div id="packageCountContainer" class="flex flex-col sm:col-span-2 hidden">
                        <label class="text-sm font-medium mb-1">
                            Number of Packages
                        </label>
                        <p class="text-xs text-gray-500 mb-2">
                            How many pouches / bottles you currently have
                        </p>
                        <input type="number" id="itemPackageCount" min="0" step="1"
                            class="w-full px-3 md:px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                    </div>
                </div>



                <!-- Stock -->
                <div class="flex flex-col">
                    <label id="stockLabel" class="text-sm font-medium mb-1">
                        Total Available Amount
                    </label>
                    <p id="stockHelp" class="text-xs text-gray-500 mb-2">
                        How much you currently have in stock
                    </p>
                    <input type="number" step="0.01" id="itemStock" name="stock" min="0" value="0"
                        class="w-full px-3 md:px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                </div>


                <!-- Price -->
                <div class="flex flex-col">
                    <label class="text-sm font-medium mb-2">Price</label>
                    <input type="number" step="0.01" id="itemPrice" name="price" required
                        class="w-full px-3 md:px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                </div>

                <!-- Image -->
                <div class="flex flex-col md:col-span-2">
                    <label class="text-sm font-medium mb-2">Product Image</label>
                    <input type="file" id="itemImage" name="image" accept="image/*"
                        class="w-full px-3 md:px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition">
                    <img id="previewImage" src="" alt="Preview" class="mt-4 max-h-40 w-full rounded-lg shadow-sm hidden">
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-4 md:col-span-2">
                    <button type="button" id="cancelForm"
                        class="px-4 py-2.5 rounded-lg bg-gray-300 dark:bg-gray-700 hover:bg-gray-400 dark:hover:bg-gray-600 text-sm transition shadow-sm">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2.5 rounded-lg bg-green-500 text-white hover:bg-green-600 text-sm transition shadow-sm">
                        Save
                    </button>
                </div>
            </form>
        </div>
        <!-- Inventory Table -->
        <div class="overflow-x-auto mt-4 rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-[600px] w-full bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden">
                <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left">Image</th>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Category</th>
                        <th class="px-4 py-2 text-left">Stock</th>
                        <th class="px-4 py-2 text-left">Price</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
            </table>

            <!-- Pagination -->
            <div class="flex justify-center items-center mt-3 gap-2">
                <button id="prevPageInventory" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">Previous</button>
                <span id="inventoryPageInfo" class="text-sm text-gray-700 dark:text-gray-200 px-2">Page 1 of 1</span>
                <button id="nextPageInventory" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">Next</button>
            </div>
        </div>
    </main>

    <!-- Category Modal -->
    <div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center p-4 sm:p-6 hidden z-50">
        <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md sm:max-w-lg p-6 sm:p-8 shadow-lg animate-fadeIn relative">

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg sm:text-xl font-semibold text-gray-800 dark:text-gray-200">Manage Categories</h3>
                <button type="button" id="closeCategoryModal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition">
                    <i data-lucide="x" class="w-5 h-5 sm:w-6 sm:h-6"></i>
                </button>
            </div>

            <form id="categoryForm" class="flex flex-col sm:flex-row gap-2 mb-4">
                <input type="hidden" id="categoryId" name="id">
                <input type="text" id="categoryName" name="name" placeholder="Category Name"
                    class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 dark:focus:ring-green-500 transition"
                    required>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">Save</button>
            </form>

            <!-- Category Table in Modal -->
            <div class="overflow-x-auto max-h-60 border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="min-w-[400px] w-full text-left text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-gray-700 dark:text-gray-200 font-medium">Name</th>
                            <th class="px-3 py-2 text-gray-700 dark:text-gray-200 font-medium text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTable" class="divide-y divide-gray-200 dark:divide-gray-700"></tbody>
                </table>
            </div>

            <div class="flex justify-center items-center mt-3 gap-2">
                <button id="prevPageCategory" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">Previous</button>
                <span id="categoryPageInfo" class="text-sm text-gray-700 dark:text-gray-200 px-2">Page 1 of 1</span>
                <button id="nextPageCategory" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">Next</button>
            </div>

        </div>
    </div>

    <!-- Image Lightbox -->
    <div id="tableImageModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
        <img id="tableModalImage" src="" alt="Preview" class="max-h-[90%] max-w-[90%] rounded-xl shadow-lg">
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="js/inventory.js" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.documentElement.style.visibility = "visible";
            lucide.createIcons();

            const sidebar = document.getElementById("sidebar");
            const sidebarToggle = document.getElementById("sidebarToggle");
            const darkToggle = document.getElementById("darkModeToggle");

            // Dark mode
            if (localStorage.getItem("theme") === "dark") document.documentElement.classList.add("dark");

            // Toggle sidebar
            sidebarToggle.addEventListener("click", () => sidebar.classList.toggle("-translate-x-full"));

            // Toggle dark mode
            darkToggle.addEventListener("click", () => {
                document.documentElement.classList.toggle("dark");
                localStorage.setItem("theme", document.documentElement.classList.contains("dark") ? "dark" : "light");
                lucide.createIcons();
            });

            // Close sidebar when clicking outside on mobile
            document.addEventListener("click", (e) => {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && !sidebar.classList.contains("-translate-x-full")) {
                    sidebar.classList.add("-translate-x-full");
                }
            });
        });
    </script>
</body>

</html>