// ================== GLOBAL STATE ==================
const rowsPerPage = 5;
let currentPage = 1;
let allRows = [];

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

    const addUserBtn = document.getElementById("addUserBtn");
    const userForm = document.getElementById("userForm");
    const searchInput = document.getElementById("searchInput");
    const roleFilter = document.getElementById("roleFilter");

    if (addUserBtn) addUserBtn.onclick = () => openModal();
    if (userForm) userForm.addEventListener("submit", handleFormSubmit);

    if (searchInput) {
        searchInput.addEventListener("input", () => {
            currentPage = 1;
            renderTable();
        });
    }

    if (roleFilter) {
        roleFilter.addEventListener("change", () => {
            currentPage = 1;
            renderTable();
        });
    }

    fetchUsers();
});

// ================== FETCH USERS ==================
async function fetchUsers() {
    try {
        const res = await fetch("php/staff.php?action=list");
        const users = await res.json();

        const tbody = document.getElementById("usersTableBody");
        tbody.innerHTML = "";

        users.forEach(user => {
            const tr = document.createElement("tr");
            tr.id = "userRow" + user.id;
            tr.className = "hover:bg-yellow-50 dark:hover:bg-gray-700 transition";

            tr.innerHTML = `
                <td class="px-4 py-3 font-medium">${user.username}</td>

                <td class="px-4 py-3">
                ${user.employee_name
                    ? user.employee_name
                    : '<span class="text-gray-400 italic">System Administrator</span>'
                }
                </td>

                <td class="px-4 py-3 capitalize">
                    <span class="px-2 py-1 rounded-full text-xs ${user.role === "admin"
                    ? "bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-200"
                    : "bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200"
                }">${user.role}</span>
                </td>

                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                    ${user.created_at}
                </td>

                <td class="px-4 py-3">
                    <div class="flex justify-center gap-2">
                        <button
                            class="px-3 py-1 text-xs bg-blue-500 text-white rounded hover:bg-blue-600"
                            onclick="editUser(${user.id})">
                            Edit
                        </button>
                        <button
                            class="px-3 py-1 text-xs bg-red-500 text-white rounded hover:bg-red-600"
                            onclick="deleteUser(${user.id})">
                            Delete
                        </button>
                    </div>
                </td>
            `;

            tbody.appendChild(tr);
        });

        allRows = Array.from(document.querySelectorAll("#usersTableBody tr"));
        currentPage = 1;
        renderTable();

    } catch (err) {
        console.error(err);
        showSuccessToast("Failed to load users", 5000);
    }
}

// ================== SEARCH + FILTER ==================
function filterRows() {
    const query = document.getElementById("searchInput")?.value.toLowerCase() || "";
    const role = document.getElementById("roleFilter")?.value.toLowerCase() || "all";

    return allRows.filter(row => {
        const username = row.children[0].innerText.toLowerCase();
        const fullName = row.children[1].innerText.toLowerCase();
        const roleText = row.children[2].innerText.toLowerCase();

        const textMatch = username.includes(query) || fullName.includes(query);
        const roleMatch = role === "all" || roleText.includes(role);

        return textMatch && roleMatch;
    });
}

// ================== RENDER TABLE ==================
function renderTable() {
    const tbody = document.getElementById("usersTableBody");
    tbody.innerHTML = "";

    const rows = filterRows();
    const start = (currentPage - 1) * rowsPerPage;
    const pageRows = rows.slice(start, start + rowsPerPage);

    pageRows.forEach(row => tbody.appendChild(row));
    renderPagination(rows.length);
}

// ================== PAGINATION ==================
function renderPagination(totalRows) {
    const pagination = document.getElementById("pagination");
    pagination.innerHTML = "";

    const totalPages = Math.ceil(totalRows / rowsPerPage);
    if (totalPages <= 1) return;

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.textContent = i;
        btn.className = `px-3 py-1 rounded text-sm ${i === currentPage
            ? "bg-yellow-600 text-white"
            : "bg-gray-200 dark:bg-gray-700 dark:text-gray-200"
            }`;

        btn.onclick = () => {
            currentPage = i;
            renderTable();
        };

        pagination.appendChild(btn);
    }
}

// ================== MODAL ==================
async function openModal(user = null) {
    const modal = document.getElementById("userModal");
    modal.classList.remove("hidden");

    const roleSelect = document.getElementById("role");
    const fullNameInput = document.getElementById("fullName");
    const fullNameHint = document.getElementById("fullNameHint");
    const passwordInput = document.getElementById("password");

    roleSelect.removeEventListener("change", handleRoleChange);
    roleSelect.addEventListener("change", handleRoleChange);

    // Full name is ALWAYS read-only
    fullNameInput.readOnly = true;
    fullNameInput.classList.add("opacity-60", "cursor-not-allowed");

    if (user) {
        // ===== EDIT USER =====
        document.getElementById("modalTitle").textContent = "Edit User";
        document.getElementById("userId").value = user.id;
        document.getElementById("username").value = user.username;
        roleSelect.value = user.role;

        if (user.role === "admin") {
            fullNameInput.value = "System Administrator";
            fullNameHint.textContent =
                "System account (not linked to an employee)";
        } else {
            fullNameInput.value = user.employee_name ?? "";
            fullNameHint.textContent =
                "To edit this name, update the employee record in Employees Management.";
        }

        // Password behavior
        passwordInput.value = "";
        passwordInput.required = false;
        passwordInput.placeholder = "Leave blank to keep current password";

        await loadEmployeesForUsers(user.employee_id);
    } else {
        // ===== ADD USER =====
        document.getElementById("modalTitle").textContent = "Add New User";
        document.getElementById("userForm").reset();

        roleSelect.value = "cashier";
        fullNameInput.value = "";
        fullNameHint.textContent =
            "Full name is automatically taken from the selected employee.";

        passwordInput.required = true;
        passwordInput.placeholder = "Enter password";

        await loadEmployeesForUsers();
    }

    handleRoleChange(); // apply role-based rules
}
async function handleRoleChange() {
    const roleSelect = document.getElementById("role");
    const employeeSelect = document.getElementById("employeeId");
    const fullNameInput = document.getElementById("fullName");
    const fullNameHint = document.getElementById("fullNameHint");

    if (roleSelect.value === "admin") {
        // 🔒 Employee select
        employeeSelect.value = "";
        employeeSelect.disabled = true;
        employeeSelect.required = false;
        employeeSelect.classList.add("opacity-50", "cursor-not-allowed");

        // 🔒 Full name
        fullNameInput.value = "System Administrator";
        fullNameHint.textContent =
            "System account (not linked to an employee)";
    } else {
        // 🔓 Employee select
        employeeSelect.disabled = false;
        employeeSelect.required = true;
        employeeSelect.classList.remove("opacity-50", "cursor-not-allowed");

        // 🔓 Full name (still read-only)
        if (fullNameInput.value === "System Administrator") {
            fullNameInput.value = "";
        }
        fullNameHint.textContent =
            "Full name is taken from the employee record and cannot be edited here.";
    }
}
function closeModal() {
    document.getElementById("userModal").classList.add("hidden");
}

// ================== CRUD ==================
async function editUser(id) {
    try {
        const res = await fetch(`php/staff.php?action=get&id=${id}`);
        const user = await res.json();
        openModal(user);
    } catch {
        showSuccessToast("Failed to load user", 4000);
    }
}


function deleteUser(id) {
    const row = document.getElementById("userRow" + id);
    const roleText = row?.children[2]?.innerText.toLowerCase();

    // 🔒 Frontend guard: block admin deletion
    if (roleText && roleText.includes("admin")) {
        showSuccessToast("Admin accounts cannot be deleted", 4000);
        return;
    }

    if (!confirm("Delete this user?")) return;

    fetch("php/staff.php", {
        method: "POST",
        body: new URLSearchParams({ action: "delete", id })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showSuccessToast("User deleted", 3000);
                fetchUsers();
            } else {
                showSuccessToast(data.error, 5000);
            }
        })
        .catch(() => {
            showSuccessToast("Failed to delete user", 5000);
        });
}


// ================== FORM SUBMIT ==================
async function handleFormSubmit(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    // 🔥 remove password if blank
    if (!formData.get("password")) {
        formData.delete("password");
    }

    const res = await fetch("php/staff.php", {
        method: "POST",
        body: formData
    });

    const data = await res.json();

    if (!data.success) {
        showSuccessToast(data.error, 5000);
        return;
    }

    closeModal();
    showSuccessToast("User saved successfully", 3000);
    fetchUsers();
}


// ================== EMPLOYEE SELECT ==================
async function loadEmployeesForUsers(selectedEmployeeId = null) {
    const [empRes, userRes] = await Promise.all([
        fetch("php/employees.php?action=list"),
        fetch("php/staff.php?action=list")
    ]);

    const employees = await empRes.json();
    const users = await userRes.json();

    const usedEmployeeIds = users
        .map(u => u.employee_id)
        .filter(id => id !== null && Number(id) > 0)
        .map(id => Number(id));

    const select = document.getElementById("employeeId");
    select.innerHTML = `<option value="">Select Employee</option>`;

    employees
        .filter(e =>
            Number(e.is_active) === 1 &&
            (
                !usedEmployeeIds.includes(Number(e.id)) ||
                Number(e.id) === Number(selectedEmployeeId)
            )
        )
        .forEach(emp => {
            const opt = document.createElement("option");
            opt.value = emp.id;
            opt.textContent = `${emp.full_name} (${emp.role_name})`;
            select.appendChild(opt);
        });

    if (selectedEmployeeId) {
        select.value = String(selectedEmployeeId);
    }

    // UX: show reason if empty
    if (select.options.length === 1) {
        const opt = document.createElement("option");
        opt.disabled = true;
        opt.textContent = "No available employees";
        select.appendChild(opt);
    }
}

// ================== TOAST ==================
function showSuccessToast(message, duration = 4000) {
    const toast = document.getElementById("successToast");
    toast.textContent = message;

    toast.classList.remove("opacity-0", "pointer-events-none");
    toast.classList.add("opacity-100");

    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.add("opacity-0", "pointer-events-none");
    }, duration);
}
