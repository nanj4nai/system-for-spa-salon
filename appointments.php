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
    <title><?= htmlspecialchars($company_name) ?> – Appointments</title>

    <link rel="icon" href="<?= htmlspecialchars($company_logo) ?>?v=<?= time() ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {}
            },
            corePlugins: {},
            plugins: []
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
    <aside id="sidebar" class="w-64 max-w-[85vw] h-screen fixed top-0 left-0 bg-gradient-to-b from-[#d6f0ec] to-[#f9e8ff]
    dark:from-gray-900 dark:to-gray-800 text-gray-800 dark:text-gray-200 shadow-lg
    transform -translate-x-full md:translate-x-0 transition-all duration-300">

        <div class="p-6 flex items-center gap-3">
            <i data-lucide="calendar" class="w-7 h-7 text-rose-700 dark:text-rose-300"></i>
            <h1 class="text-xl font-bold">Appointments</h1>
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

            <a href="appointments.php" class="flex items-center gap-3 p-3 rounded-xl bg-rose-200 dark:bg-rose-800 transition">
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
            <button id="darkModeToggle" class="w-full flex items-center gap-3 p-3 rounded-xl bg-gray-200 dark:bg-gray-700">
                <i data-lucide="moon" class="dark:hidden"></i>
                <i data-lucide="sun" class="hidden dark:block"></i>
                Dark Mode
            </button>
        </div>
    </aside>

    <!-- MOBILE TOGGLE -->
    <button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 p-3 rounded-full bg-rose-400 shadow-lg">
        <i data-lucide="menu"></i>
    </button>

    <!-- MAIN -->
    <main class="flex-1 md:ml-64 p-4 sm:p-4 md:p-6 text-gray-800 dark:text-gray-200 transition-all">

        <!-- HEADER -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-md mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                <div>
                    <h2 class="text-2xl font-semibold">Manage Appointments</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        View, filter, and manage all bookings
                    </p>
                </div>

                <div class="flex gap-3">
                    <button
                        class="btn-add-appointment px-4 py-2 bg-rose-500 text-white rounded-xl flex items-center gap-2">
                        <i data-lucide="plus"></i> Add New
                    </button>
                </div>

            </div>
        </div>

        <!-- FILTERS -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow mb-6">
            <!-- QUICK DATE TOGGLE -->
            <div class="flex gap-2 mb-4">
                <button type="button"
                    class="quick-filter btn-secondary px-4 py-2 rounded-lg"
                    data-mode="today">
                    Today
                </button>

                <button type="button"
                    class="quick-filter btn-secondary px-4 py-2 rounded-lg"
                    data-mode="upcoming">
                    Upcoming
                </button>

                <button type="button"
                    class="quick-filter btn-secondary px-4 py-2 rounded-lg"
                    data-mode="all">
                    All
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
                <div>
                    <label class="text-sm mb-1 block">Appointment Date</label>
                    <input type="date" id="filterDate" class="input w-full" />
                </div>

                <div>
                    <label class="text-sm mb-1 block">Staff Member</label>
                    <select id="filterStaff" class="input w-full">
                        <option value="">All Staff</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm mb-1 block">Customer Name</label>
                    <input type="text" id="filterCustomer" class="input w-full" placeholder="Start typing…" />
                </div>

                <div>
                    <label class="text-sm mb-1 block">Service</label>
                    <select id="filterService" class="input w-full">
                        <option value="">All Services</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm mb-1 block">Status</label>
                    <select id="filterStatus" class="input w-full">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No Show</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm mb-1 block">Appointment ID</label>
                    <input type="text" id="filterId" class="input w-full" />
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="resetFilters" class="btn-secondary">Reset</button>
                <button type="button" id="applyFilters" class="btn-primary">Apply</button>

            </div>
        </div>
        <!-- MOBILE APPOINTMENT CARDS -->
        <div id="appointmentsCards" class="md:hidden space-y-4">
            <!-- JS will inject cards here -->
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow hidden md:block overflow-x-auto">

            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr class="text-left">
                        <th class="p-4"></th>
                        <th class="p-4">ID</th>
                        <th class="p-4">Date</th>
                        <th class="p-4">Customer</th>
                        <th class="p-4">Details</th>
                        <th class="p-4">Total</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Created Date</th>
                    </tr>
                </thead>

                <tbody id="appointmentsBody"
                    class="divide-y divide-gray-200 dark:divide-gray-700">
                </tbody>
            </table>

            <!-- FOOTER -->
            <div class="flex flex-col md:flex-row md:justify-between md:items-center p-4 text-sm text-gray-500">
                <span id="paginationInfo">Showing 0–0 of 0</span>

                <div class="flex items-center gap-3">
                    <button id="prevPage" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-50">Prev</button>
                    <button id="nextPage" class="px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-50">Next</button>

                    <span>Per Page</span>
                    <select id="perPageSelect" class="input">
                        <option value="20">20</option>
                        <option value="50">50</option>
                    </select>
                </div>
            </div>
        </div>


        <!-- DETAILS APPOINTMENT MODAL -->
        <div id="appointmentDetailsModal"
            class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50 p-4">

            <div
                class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-3xl mx-auto shadow-xl overflow-y-auto max-h-[90vh]">

                <!-- HEADER -->
                <div class="flex items-center justify-between px-6 py-4 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold">Appointment Details</h3>
                    <button id="closeDetailsModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 text-2xl leading-none">
                        &times;
                    </button>
                </div>

                <!-- CONTENT -->
                <div id="detailsContent" class="p-6 space-y-6 text-sm">
                    <!-- injected by JS -->
                </div>

                <!-- FOOTER -->
                <div class="flex justify-end gap-2 px-6 py-4 border-t dark:border-gray-700">
                    <button
                        id="closeDetailsBtn"
                        class="btn-secondary px-5 py-2 rounded-lg">
                        Close
                    </button>
                </div>

            </div>
        </div>

        <!-- ADD APPOINTMENT MODAL -->
        <div id="appointmentModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50 p-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-4xl p-6 shadow-lg
                    overflow-y-auto overflow-x-hidden max-h-[90vh]">

                <h3 class="text-lg sm:text-xl font-semibold mb-4">Add Appointment</h3>

                <div class="space-y-4">
                    <!-- EXISTING CLIENT SEARCH -->
                    <div class="flex flex-col gap-1">
                        <label for="clientSearch" class="text-sm font-medium">Select Existing Client</label>
                        <div class="flex gap-2">
                            <input id="clientSearch" class="input flex-1" placeholder="Search client..." />
                            <button id="newClientToggle" type="button" class="px-3 py-1 bg-green-500 text-white rounded">+ New</button>
                        </div>
                        <div id="clientResults" class="bg-white dark:bg-gray-700 rounded shadow hidden"></div>
                    </div>

                    <!-- NEW CLIENT INPUTS -->
                    <div id="newClientFields" class="space-y-2 hidden">
                        <label for="newClientName" class="text-sm font-medium">Full Name</label>
                        <input id="newClientName" class="input w-full" placeholder="Full Name" required />

                        <label for="newClientContact" class="text-sm font-medium">Contact Number</label>
                        <input id="newClientContact" class="input w-full" placeholder="Contact Number" required />

                        <label for="newClientEmail" class="text-sm font-medium">Email (optional)</label>
                        <input id="newClientEmail" class="input w-full" placeholder="Email (optional)" />

                        <label for="newClientAddress" class="text-sm font-medium">Address</label>
                        <input id="newClientAddress" class="input w-full" placeholder="Address" required />

                        <label for="newClientNotes" class="text-sm font-medium">Notes (optional)</label>
                        <textarea id="newClientNotes" class="input w-full" placeholder="Notes (optional)"></textarea>
                    </div>

                    <!-- SERVICE + STAFF ROWS -->
                    <div id="serviceStaffContainer" class="space-y-2">
                        <label class="text-sm font-medium">Services & Staff</label>

                        <!-- rows go here -->
                        <div class="service-staff-row flex flex-col gap-2">
                            <div class="flex flex-col sm:flex-row gap-2">
                                <select class="serviceSelect input flex-1"></select>
                                <select class="variantSelect input flex-1 hidden"></select>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2">
                                <select class="staffSelect input flex-1" disabled></select>
                                <button type="button"
                                    class="removeRowBtn px-2 py-1 bg-red-500 text-white rounded">-</button>
                            </div>

                            <!-- products (read-only) -->
                            <div class="productInfo text-xs text-gray-500 hidden"></div>
                        </div>

                        <!-- ADD BUTTON (anchor) -->
                        <button type="button"
                            class="addRowBtn px-2 py-1 bg-green-500 text-white rounded flex items-center gap-1">
                            + Add Service
                        </button>
                    </div>


                    <!-- DATE & TIME -->
                    <label for="appointmentDate" class="text-sm font-medium">Appointment Date</label>
                    <input id="appointmentDate" type="date" class="input w-full" />

                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="flex-1">
                            <label for="startTime" class="text-sm font-medium">Start Time</label>
                            <input id="startTime" type="time" class="input w-full" />
                        </div>
                        <div class="flex-1">
                            <label for="endTime" class="text-sm font-medium">End Time</label>
                            <input id="endTime" type="time" class="input w-full" />
                        </div>
                    </div>

                    <label for="notes" class="text-sm font-medium">Notes (optional)</label>
                    <textarea id="notes" class="input w-full" placeholder="Notes/Reminders"></textarea>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-2 mt-6">
                    <button class="btn-cancel btn-secondary w-full sm:w-auto">Cancel</button>
                    <button class="btn-save btn-primary w-full sm:w-auto">Save</button>
                </div>
            </div>
        </div>

    </main>
    <div id="toastContainer" class="fixed top-5 right-5 flex flex-col gap-2 z-50"></div>


    <script src="js/appointment.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.documentElement.style.visibility = 'visible';
            lucide.createIcons();

            const sidebar = document.getElementById('sidebar');
            document.getElementById('sidebarToggle').onclick = () => sidebar.classList.toggle('-translate-x-full');

            document.getElementById('darkModeToggle').onclick = () => {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                lucide.createIcons();
            };

            if (localStorage.getItem('theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        });
    </script>

    <style>
        .input {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            background-color: #f9fafb;
            max-width: 100%;
        }

        .dark .input {
            background-color: #374151;
            border-color: #4b5563;
        }

        .btn-primary {
            background-color: #f43f5e;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
        }

        .btn-primary:hover {
            background-color: #e11d48;
        }

        .btn-secondary {
            border: 1px solid #e5e7eb;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
        }

        .dark .btn-secondary {
            border-color: #4b5563;
        }
    </style>

</body>

</html>