<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: php/login.php");
    exit;
}

require_once "php/db.php";
require_once "php/company_settings.php";

$username = $_SESSION["username"];
$role = $_SESSION["role"];

// Fetch all users/staff
$usersResult = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> – Employees Management</title>

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

        /* Fade-in animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeIn {
            animation: fadeIn 0.3s ease-out;
        }

        /* Input hover/focus improvements */
        input:focus,
        select:focus {
            border-color: #facc15;
            /* Tailwind yellow-400 */
        }
    </style>
</head>

<body class="flex bg-gray-100 dark:bg-[#121212] transition-all overflow-x-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="w-64 h-screen fixed top-0 left-0 bg-gradient-to-b from-[#d6f0ec] to-[#f9e8ff] 
               dark:from-gray-900 dark:to-gray-800 text-gray-800 dark:text-gray-200 shadow-lg 
               transform -translate-x-full md:translate-x-0 transition-all duration-300 z-50">

        <div class="p-6 flex items-center gap-3">
            <i data-lucide="id-card-lanyard" class="w-7 h-7 text-teal-700 dark:text-teal-300"></i>
            <h1 class="text-xl font-bold text-teal-900 dark:text-teal-200">Employees</h1>
        </div>

        <nav class="mt-4 px-4 flex flex-col space-y-1 z-10">
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
            <?php endif; ?>
            <?php if ($role === "admin"): ?>
                <a href="staff.php" class="flex items-center gap-3 p-3 rounded-xl bg-yellow-200 dark:bg-yellow-700 transition">
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

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 hidden z-40 md:hidden"></div>

    <!-- MOBILE MENU -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-teal-400 shadow-lg transition">
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>

    <!-- MAIN CONTENT -->
    <main class="flex-1 md:ml-64 px-4 sm:px-6 py-6 text-gray-800 dark:text-gray-200 transition-all max-w-full">

        <!-- PAGE HEADER -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow border border-yellow-200 dark:border-yellow-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-semibold text-yellow-900 dark:text-yellow-200">
                        Employees Management
                        <?php if ($role === "admin"): ?>
                            / Users Management
                        <?php endif; ?>
                    </h2>

                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Manage employees
                        <?php if ($role === "admin"): ?>
                            and system access accounts
                        <?php endif; ?>
                    </p>
                </div>

                <div class="flex gap-2">
                    <button id="addEmployeeBtn"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 text-white rounded-xl shadow hover:bg-teal-700 transition">
                        + Add Employee
                    </button>

                    <?php if ($role === "admin"): ?>
                        <button id="addUserBtn"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-yellow-600 text-white rounded-xl shadow hover:bg-yellow-700 transition">
                            + Add User
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- EMPLOYEES TABLE -->
        <div class="bg-white dark:bg-gray-800 mt-6 p-5 sm:p-6 rounded-2xl shadow border border-gray-200 dark:border-gray-700">

            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">
                Employees
            </h3>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full table-auto text-sm min-w-[700px]">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-700">
                            <th class="px-4 py-3 text-left">Full Name</th>
                            <th class="px-4 py-3 text-left">Job Role</th>
                            <th class="px-4 py-3 text-left">Contact</th>
                            <th class="px-4 py-3 text-left">Addt'l., Info</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody id="employeesTableBody">
                        <!-- populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($role === "admin"): ?>
            <!-- TABLE CARD -->
            <div class="bg-white dark:bg-gray-800 mt-6 p-5 sm:p-6 rounded-2xl shadow border border-gray-200 dark:border-gray-700">

                <!-- SEARCH + PAGINATION HEADER -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">

                    <!-- Search + Filter -->
                    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto z-10">

                        <!-- Search -->
                        <div class="relative w-full sm:w-64">
                            <input
                                id="searchInput"
                                type="text"
                                placeholder="Search Employees..."
                                class="flex-1 w-full pl-10 pr-3 py-2 rounded-lg border border-gray-300
                            dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                            focus:ring-2 focus:ring-yellow-400 outline-none" />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                <i data-lucide="search" class="w-5 h-5"></i>
                            </span>
                        </div>

                        <!-- Role Filter -->
                        <select
                            id="roleFilter"
                            class="w-full sm:w-40 px-3 py-2 rounded-lg border border-gray-300
                        dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                        focus:ring-2 focus:ring-yellow-400 outline-none">

                            <option value="all">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="cashier">Cashier</option>
                        </select>

                    </div>

                    <!-- Pagination -->
                    <div id="pagination" class="flex gap-1 justify-start sm:justify-end flex-wrap overflow-x-auto">
                    </div>
                </div>

                <!-- RESPONSIVE TABLE -->
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 w-full">
                    <table class="w-full table-auto text-sm min-w-[600px]">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                <th class="px-4 py-3 text-left font-semibold">Username</th>
                                <th class="px-4 py-3 text-left font-semibold">Full Name</th>
                                <th class="px-4 py-3 text-left font-semibold">Role</th>
                                <th class="px-4 py-3 text-left font-semibold">Created</th>
                                <th class="px-4 py-3 text-center font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody"
                            class="divide-y divide-gray-200 dark:divide-gray-700">
                            <!-- populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!--Employee Modal Add/Edit  -->
    <div id="employeeModal"
        class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-lg p-6 shadow-lg animate-fadeIn">

            <h3 id="employeeModalTitle"
                class="text-xl font-semibold mb-4 text-center text-gray-700 dark:text-gray-300">
                Add Employee
            </h3>

            <form id="employeeForm" class="grid grid-cols-1 gap-3">
                <input type="hidden" id="employeeHiddenId">
                <input type="hidden" id="employeeRoleId">

                <input type="text" id="employeeName" placeholder="Full Name" required class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-400 focus:ring-2 focus:ring-yellow-400 outline-none">

                <input
                    type="text"
                    id="employeeRole"
                    list="employeeRoleList"
                    placeholder="Job Role (e.g. Therapist)"
                    required
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-yellow-400 outline-none">

                <datalist id="employeeRoleList"></datalist>

                <input type="text" id="employeeContact" placeholder="Contact Number" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-400 focus:ring-2 focus:ring-yellow-400 outline-none">
                <input type="email" id="employeeEmail" placeholder="Email" class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-400 focus:ring-2 focus:ring-yellow-400 outline-none">

                <label for="date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Date Hired:
                </label>

                <input
                    type="date"
                    id="employeeHireDate"
                    class="px-3 py-2 rounded border border-gray-300
           dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
           focus:ring-2 focus:ring-yellow-400 outline-none
           dark:[color-scheme:dark]">


                <textarea
                    id="employeeAddress"
                    placeholder="Address"
                    rows="2"
                    class="px-3 py-2 rounded border border-gray-300
                    dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                    placeholder-gray-400 focus:ring-2 focus:ring-yellow-400 outline-none"></textarea>

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeEmployeeModal()"
                        class="px-4 py-2 bg-gray-300 rounded">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-teal-600 text-white rounded">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md p-6 relative shadow-lg animate-fadeIn">
            <!-- Modal Header -->
            <h3 id="modalTitle" class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Add New User</h3>

            <!-- Modal Form -->
            <form id="userForm" class="flex flex-col gap-3">
                <input type="hidden" name="id" id="userId">


                <input
                    type="text"
                    name="full_name"
                    id="fullName"
                    placeholder="Full Name"
                    readonly
                    class="px-3 py-2 rounded border border-gray-300
                        dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                        opacity-70 cursor-not-allowed" />
                <p id="fullNameHint" class="text-xs text-gray-500 italic mt-1"></p>
                <select
                    name="employee_id"
                    id="employeeId"
                    class="px-3 py-2 rounded border border-gray-300
                        dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                        focus:ring-2 focus:ring-yellow-400 outline-none">
                    <option value="">Select Employee</option>
                </select>
                <input type="text" name="username" id="username" placeholder="Username" required
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-400 focus:ring-2 focus:ring-yellow-400 outline-none">
                <input type="password" name="password" id="password" placeholder="Leave blank to keep current password" required
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-400 focus:ring-2 focus:ring-yellow-400 outline-none">

                <select name="role" id="role" required
                    class="px-3 py-2 rounded border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-2 focus:ring-yellow-400 outline-none">
                    <option value="cashier">Cashier</option>
                    <option value="admin">Admin</option>
                </select>


                <!-- Modal Buttons -->
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeModal()"
                        class="px-4 py-2 bg-gray-300 dark:bg-gray-600 rounded hover:bg-gray-400 dark:hover:bg-gray-500 dark:text-gray-200 transition">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition">Save</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Success Toast -->
    <div id="successToast"
        class="fixed top-6 right-6 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-all duration-300 z-50">
        User saved successfully!
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="js/staff.js" defer></script>
    <script src="js/employees.js" defer></script>
</body>

</html>