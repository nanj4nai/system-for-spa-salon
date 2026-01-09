<?php
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once "db.php";

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* =========================
   LIST USERS
========================= */
if ($action === 'list') {
    $res = $conn->query("
        SELECT 
            u.id,
            u.username,
            u.role,
            u.employee_id,
            e.full_name AS employee_name,
            u.created_at
        FROM users u
        LEFT JOIN employees e ON e.id = u.employee_id
        ORDER BY 
            (u.role = 'admin') DESC,
            u.created_at DESC
    ");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
    exit;
}

/* =========================
   GET SINGLE USER
========================= */
if ($action === 'get') {
    $id = intval($_GET['id'] ?? 0);

    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.username,
            u.role,
            u.employee_id,
            e.full_name AS employee_name
        FROM users u
        LEFT JOIN employees e ON e.id = u.employee_id
        WHERE u.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode($stmt->get_result()->fetch_assoc());
    exit;
}

/* =========================
   DELETE USER
========================= */
if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);

    // Fetch role
    $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $user = $check->get_result()->fetch_assoc();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    if ($user['role'] === 'admin') {
        $count = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'")
            ->fetch_assoc();

        if ($count['total'] <= 1) {
            echo json_encode([
                'success' => false,
                'error' => 'Cannot delete the last admin account'
            ]);
            exit;
        }
    }


    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);

    echo json_encode(['success' => $stmt->execute()]);
    exit;
}


/* =========================
   ADD / EDIT USER
========================= */
$id          = intval($_POST['id'] ?? 0);
$username    = trim($_POST['username'] ?? '');
$password    = $_POST['password'] ?? '';
$role        = $_POST['role'] ?? 'cashier';
$employee_id = intval($_POST['employee_id'] ?? 0);

/* ---------- BASIC VALIDATION ---------- */
if ($username === '') {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
    exit;
}

/* ---------- ROLE RULES ---------- */
if ($role === 'cashier' && $employee_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Cashier must be assigned to an employee'
    ]);
    exit;
}

if ($role === 'admin') {
    $employee_id = null; // admins do NOT require employees
}

/* ---------- PREVENT DUPLICATE EMPLOYEE ---------- */
if ($employee_id !== null) {
    $check = $conn->prepare("
        SELECT id FROM users
        WHERE employee_id = ? AND id != ?
    ");
    $check->bind_param("ii", $employee_id, $id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'This employee already has a user account'
        ]);
        exit;
    }
}

/* =========================
   UPDATE USER
========================= */
if ($id > 0) {

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users
            SET username = ?, password = ?, role = ?, employee_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssii", $username, $hash, $role, $employee_id, $id);
    } else {
        $stmt = $conn->prepare("
            UPDATE users
            SET username = ?, role = ?, employee_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssii", $username, $role, $employee_id, $id);
    }

    /* =========================
   INSERT USER
========================= */
} else {

    if ($password === '') {
        echo json_encode(['success' => false, 'error' => 'Password is required']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        INSERT INTO users (username, password, role, employee_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("sssi", $username, $hash, $role, $employee_id);
}

/* ---------- EXECUTE ---------- */
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
    exit;
}

$user_id = $id ?: $stmt->insert_id;

/* =========================
   RETURN SAVED USER
========================= */
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.username,
        u.role,
        u.employee_id,
        e.full_name AS employee_name,
        u.created_at
    FROM users u
    LEFT JOIN employees e ON e.id = u.employee_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo json_encode([
    'success' => true,
    'user' => $stmt->get_result()->fetch_assoc()
]);
