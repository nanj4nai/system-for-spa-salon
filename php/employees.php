<?php
session_start();
header("Content-Type: application/json");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once "db.php";

/* =====================
   AUTH CHECK
===================== */
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

/* =====================
   INPUT
===================== */
$action = $_GET["action"] ?? $_POST["action"] ?? "";

/* =====================
   LIST EMPLOYEES
===================== */
if ($action === "list") {

    $sql = "
        SELECT 
            e.id,
            e.full_name,
            e.contact_number,
            e.email,
            e.address,
            e.hire_date,
            e.is_active,
            e.staff_role_id,
            r.name AS role_name
        FROM employees e
        LEFT JOIN staff_roles r ON r.id = e.staff_role_id
        ORDER BY e.created_at DESC
    ";

    $res = $conn->query($sql);
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* =====================
   GET SINGLE EMPLOYEE
===================== */
if ($action === "get") {
    $id = intval($_GET["id"] ?? 0);

    $stmt = $conn->prepare("
        SELECT 
            e.id,
            e.full_name,
            e.contact_number,
            e.email,
            e.address,
            e.hire_date,
            e.is_active,
            e.staff_role_id,
            r.name AS role_name
        FROM employees e
        LEFT JOIN staff_roles r ON r.id = e.staff_role_id
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode($stmt->get_result()->fetch_assoc());
    exit;
}

/* =====================
   ROLE AUTOCOMPLETE
===================== */
if ($action === "roles") {
    $res = $conn->query("SELECT name FROM staff_roles ORDER BY name ASC");

    $roles = [];
    while ($row = $res->fetch_assoc()) {
        $roles[] = $row["name"];
    }

    echo json_encode($roles);
    exit;
}

/* =====================
   SAVE EMPLOYEE (ADD / EDIT)
===================== */
if ($action === "save") {

    try {

        // ---- EXISTING CODE (unchanged) ----
        $id        = intval($_POST["id"] ?? 0);
        $role_id  = intval($_POST["role_id"] ?? 0);
        $full_name = trim($_POST["full_name"] ?? "");
        $role_name = trim($_POST["job_role"] ?? "");
        $role_name = trim(preg_replace('/\s+/', ' ', $role_name));
        $role_name = ucwords(strtolower($role_name));
        $contact   = trim($_POST["contact_number"] ?? "");
        $email     = trim($_POST["email"] ?? "");
        $hire_date = $_POST["hire_date"] ?? null;
        $address   = trim($_POST["address"] ?? "");

        if ($full_name === "" || $role_name === "") {
            echo json_encode(["success" => false, "error" => "Full name and role are required"]);
            exit;
        }

        /* =====================
           ROLE HANDLING
        ===================== */

        if ($id > 0 && $role_id > 0 && $role_name !== "") {

            // 🔎 check if role name already exists (other than this role)
            $check = $conn->prepare("
                SELECT id FROM staff_roles
                WHERE name = ? AND id != ?
            ");
            $check->bind_param("si", $role_name, $role_id);
            $check->execute();

            if ($check->get_result()->num_rows > 0) {
                echo json_encode([
                    "success" => false,
                    "error" => "Role name already exists. Please choose a different name."
                ]);
                exit;
            }

            // safe rename
            $stmt = $conn->prepare("
                UPDATE staff_roles SET name = ? WHERE id = ?
            ");
            $stmt->bind_param("si", $role_name, $role_id);
            $stmt->execute();
            $staff_role_id = $role_id;
        } else {
            // add / find role
            $stmt = $conn->prepare("SELECT id FROM staff_roles WHERE name = ?");
            $stmt->bind_param("s", $role_name);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
                $staff_role_id = $res->fetch_assoc()["id"];
            } else {
                $stmt = $conn->prepare("INSERT INTO staff_roles (name) VALUES (?)");
                $stmt->bind_param("s", $role_name);
                $stmt->execute();
                $staff_role_id = $stmt->insert_id;
            }
        }

        /* =====================
           EMPLOYEE SAVE
        ===================== */

        if ($id > 0) {
            $stmt = $conn->prepare("
                UPDATE employees
                SET full_name = ?, staff_role_id = ?, contact_number = ?, email = ?, hire_date = ?, address = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sissssi", $full_name, $staff_role_id, $contact, $email, $hire_date, $address, $id);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO employees (full_name, staff_role_id, contact_number, email, hire_date, address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sissss", $full_name, $staff_role_id, $contact, $email, $hire_date, $address);
        }

        $stmt->execute();

        echo json_encode(["success" => true]);
        exit;
    } catch (mysqli_sql_exception $e) {
        echo json_encode([
            "success" => false,
            "error" => "Database error: " . $e->getMessage()
        ]);
        exit;
    }
}


/* =====================
   DELETE EMPLOYEE
===================== */
if ($action === "toggle_status") {

    $id = intval($_POST["id"] ?? 0);
    $is_active = intval($_POST["is_active"] ?? 1);
    $remarks = trim($_POST["remarks"] ?? null);

    if ($id <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid employee"]);
        exit;
    }

    if ($is_active === 0 && $remarks === "") {
        echo json_encode([
            "success" => false,
            "error" => "Remarks are required when deactivating an employee"
        ]);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE employees
        SET is_active = ?, inactive_remarks = ?
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $is_active, $remarks, $id);

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "error" => $stmt->error]);
        exit;
    }

    echo json_encode(["success" => true]);
    exit;
}


/* =====================
   FALLBACK
===================== */
echo json_encode(["success" => false, "error" => "Invalid action"]);
