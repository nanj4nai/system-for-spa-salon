<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once "db.php"; // adjust path if needed

// Validate inputs
$spa_name = trim($_POST['spa_name'] ?? '');
$address = trim($_POST['address'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$invoice_prefix = trim($_POST['invoice_prefix'] ?? 'SPA');
$vat_rate = floatval($_POST['vat_rate'] ?? 0);

if ($spa_name === '' || $address === '' || $contact_number === '' || $invoice_prefix === '') {
    echo json_encode(['success' => false, 'error' => 'Please fill all required fields.']);
    exit;
}

// Helper function to handle file uploads
function uploadFile($fileInputName, $prefix)
{
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName = basename($_FILES[$fileInputName]['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];

        if (!in_array($fileExt, $allowed)) {
            return ['error' => "Invalid file type for $fileInputName."];
        }

        $newFileName = $prefix . '_' . time() . '.' . $fileExt;
        $uploadDir = '../images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $destPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            return ['error' => "Failed to upload $fileInputName."];
        }

        return ['path' => 'images/' . $newFileName];
    }
    return ['path' => null];
}

// Handle logo and favicon uploads
$logoUpload = uploadFile('logo', 'logo');
$faviconUpload = uploadFile('favicon', 'favicon');

if (isset($logoUpload['error'])) {
    echo json_encode(['success' => false, 'error' => $logoUpload['error']]);
    exit;
}

if (isset($faviconUpload['error'])) {
    echo json_encode(['success' => false, 'error' => $faviconUpload['error']]);
    exit;
}

$logo_path = $logoUpload['path'];
$favicon_path = $faviconUpload['path'];

// Check if a settings row exists
$result = $conn->query("SELECT * FROM settings LIMIT 1");
if ($result->num_rows > 0) {
    // Update existing
    $sql = "UPDATE settings SET spa_name=?, address=?, contact_number=?, invoice_prefix=?, vat_rate=?";
    $params = [$spa_name, $address, $contact_number, $invoice_prefix, $vat_rate];
    $types = 'ssssd';

    if ($logo_path) {
        $sql .= ", logo_path=?";
        $types .= 's';
        $params[] = $logo_path;
    }

    if ($favicon_path) {
        $sql .= ", favicon_path=?";
        $types .= 's';
        $params[] = $favicon_path;
    }

    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
} else {
    // Insert new
    $sql = "INSERT INTO settings (spa_name, address, contact_number, invoice_prefix, vat_rate";
    $placeholders = "?, ?, ?, ?, ?";
    $types = 'ssssd';
    $params = [$spa_name, $address, $contact_number, $invoice_prefix, $vat_rate];

    if ($logo_path) {
        $sql .= ", logo_path";
        $placeholders .= ", ?";
        $types .= 's';
        $params[] = $logo_path;
    }

    if ($favicon_path) {
        $sql .= ", favicon_path";
        $placeholders .= ", ?";
        $types .= 's';
        $params[] = $favicon_path;
    }

    $sql .= ") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();
