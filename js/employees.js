document.addEventListener("DOMContentLoaded", () => {
    const addEmployeeBtn = document.getElementById("addEmployeeBtn");
    const employeeForm = document.getElementById("employeeForm");

    if (addEmployeeBtn) {
        addEmployeeBtn.onclick = () => openEmployeeModal();
    }

    if (employeeForm) {
        employeeForm.addEventListener("submit", saveEmployee);
    }

    fetchEmployees();
    fetchEmployeeRoles();
});

/* =======================
   FETCH EMPLOYEES
======================= */
async function fetchEmployees() {
    const res = await fetch("php/employees.php?action=list");
    const data = await res.json();

    const tbody = document.getElementById("employeesTableBody");
    tbody.innerHTML = "";

    data.forEach(emp => {
        const tr = document.createElement("tr");
        tr.id = `employeeRow${emp.id}`;
        tr.className = "hover:bg-teal-50 dark:hover:bg-gray-700 transition";

        tr.innerHTML = `
            <td class="px-4 py-3 font-medium">${emp.full_name}</td>
            <td class="px-4 py-3">${emp.role_name || "-"}</td>
            <td class="px-4 py-3">
                ${emp.contact_number || "-"}<br>
                <span class="text-xs text-gray-500">${emp.email || ""}</span>
            </td>
            <td class="px-4 py-3">
                ${emp.hire_date || "-"}<br>
                <span class="text-xs text-gray-500">${emp.address || ""}</span>
            </td>
            <td class="px-4 py-3">
            <span class="px-2 py-1 rounded-full text-xs ${emp.is_active == 1
                ? "bg-green-100 text-green-700"
                : "bg-red-100 text-red-700"
            }">
                ${emp.is_active == 1 ? "Active" : "Inactive"}
            </span>
            </td>
            <td class="px-4 py-3 text-center">
                <button
                    class="px-2 py-1 bg-blue-500 text-white rounded text-xs"
                    onclick="editEmployee(${emp.id})">
                    Edit
                </button>
                <button
                    class="px-2 py-1 text-white rounded text-xs ${emp.is_active == 1 ? "bg-red-500" : "bg-green-500"
            }"
                    onclick="toggleEmployeeStatus(${emp.id}, ${emp.is_active})">
                    ${emp.is_active == 1 ? "Deactivate" : "Activate"}
                </button>
            </td>
        `;

        tbody.appendChild(tr);
    });
}

/* =======================
   FETCH JOB ROLES
======================= */
async function fetchEmployeeRoles() {
    const res = await fetch("php/employees.php?action=roles");
    const roles = await res.json();

    const datalist = document.getElementById("employeeRoleList");
    if (!datalist) return;

    datalist.innerHTML = "";

    roles.forEach(role => {
        const opt = document.createElement("option");
        opt.value = role;
        datalist.appendChild(opt);
    });
}

/* =======================
   MODAL HANDLERS
======================= */
function openEmployeeModal(employee = null) {
    const modal = document.getElementById("employeeModal");
    modal.classList.remove("hidden");

    if (employee) {
        document.getElementById("employeeModalTitle").textContent = "Edit Employee";

        document.getElementById("employeeHiddenId").value = employee.id;

        // 🔥 store role ID
        document.getElementById("employeeRoleId").value = employee.staff_role_id;

        document.getElementById("employeeName").value = employee.full_name;
        document.getElementById("employeeRole").value = employee.role_name || "";
        document.getElementById("employeeContact").value = employee.contact_number || "";
        document.getElementById("employeeEmail").value = employee.email || "";
        document.getElementById("employeeHireDate").value = employee.hire_date || "";
        document.getElementById("employeeAddress").value = employee.address || "";
    } else {
        document.getElementById("employeeModalTitle").textContent = "Add Employee";
        document.getElementById("employeeForm").reset();

        document.getElementById("employeeHiddenId").value = "";
        document.getElementById("employeeRoleId").value = "";
    }
}




function closeEmployeeModal() {
    document.getElementById("employeeModal").classList.add("hidden");
}

/* =======================
   EDIT / DELETE
======================= */
async function editEmployee(id) {
    const res = await fetch(`php/employees.php?action=get&id=${id}`);
    const emp = await res.json();
    openEmployeeModal(emp);
}

async function toggleEmployeeStatus(id, currentStatus) {
    let remarks = "";

    if (currentStatus == 1) {
        remarks = prompt("Reason for deactivating this employee:");
        if (!remarks) return;
    }

    const res = await fetch("php/employees.php", {
        method: "POST",
        body: new URLSearchParams({
            action: "toggle_status",
            id,
            is_active: currentStatus == 1 ? 0 : 1,
            remarks
        })
    });

    const data = await res.json();

    if (!data.success) {
        alert(data.error);
        return;
    }

    showSuccessToast(
        currentStatus == 1
            ? "Employee deactivated successfully."
            : "Employee activated successfully."
    );

    fetchEmployees();
}



/* =======================
   SAVE EMPLOYEE
======================= */
async function saveEmployee(e) {
    e.preventDefault();

    const formData = new URLSearchParams({
        id: document.getElementById("employeeHiddenId").value,
        role_id: document.getElementById("employeeRoleId").value, // 🔥 NEW
        full_name: document.getElementById("employeeName").value,
        job_role: document.getElementById("employeeRole").value,
        contact_number: document.getElementById("employeeContact").value,
        email: document.getElementById("employeeEmail").value,
        hire_date: document.getElementById("employeeHireDate").value,
        address: document.getElementById("employeeAddress").value,
        action: "save"
    });


    const res = await fetch("php/employees.php", {
        method: "POST",
        body: formData
    });

    const data = await res.json();
    if (!data.success) {
        showSuccessToast(data.error, 5000); // reuse toast
        return;
    }


    closeEmployeeModal();
    fetchEmployees();
}

