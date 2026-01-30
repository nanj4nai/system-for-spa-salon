<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

require_once "db.php";

// Inputs
$spa_name        = trim($_POST['spa_name'] ?? '');
$address         = trim($_POST['address'] ?? '');
$contact_number  = trim($_POST['contact_number'] ?? '');
$invoice_prefix  = trim($_POST['invoice_prefix'] ?? 'SPA');
$vat_rate        = floatval($_POST['vat_rate'] ?? 0);
$gcash_number    = trim($_POST['gcash_number'] ?? '');
$email = trim($_POST['email'] ?? '');
// Validation
if (
    $spa_name === '' ||
    $address === '' ||
    $contact_number === '' ||
    $invoice_prefix === '' ||
    $email === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}


/* ---------- Upload helper ---------- */
function uploadFile($name, $prefix)
{
    if (!isset($_FILES[$name]) || $_FILES[$name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES[$name]['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    if (!in_array($mime, $allowedMimes)) {
        throw new Exception("Invalid image format for $name.");
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp'
    ];

    $ext = $extMap[$mime];

    $dir = '../images/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = $prefix . '_' . time() . '.' . $ext;
    move_uploaded_file($_FILES[$name]['tmp_name'], $dir . $filename);

    return 'images/' . $filename;
}


try {
    $logo_path     = uploadFile('logo', 'logo');
    $gcash_qr_path = uploadFile('gcash_qr', 'gcash_qr');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

/* ---------- Save ---------- */
$exists = $conn->query("SELECT id FROM settings LIMIT 1")->num_rows > 0;

if ($exists) {
    $sql = "UPDATE settings SET
        spa_name=?,
        address=?,
        contact_number=?,
        email=?,
        invoice_prefix=?,
        vat_rate=?,
        gcash_number=?";

    $params = [
        $spa_name,
        $address,
        $contact_number,
        $email,
        $invoice_prefix,
        $vat_rate,
        $gcash_number
    ];
    $types = 'sssssds';

    if ($logo_path) {
        $sql .= ", logo_path=?";
        $types .= 's';
        $params[] = $logo_path;
    }

    if ($gcash_qr_path) {
        $sql .= ", gcash_qr_path=?";
        $types .= 's';
        $params[] = $gcash_qr_path;
    }

    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
} else {
    $stmt = $conn->prepare("
    INSERT INTO settings (
        spa_name,address,contact_number,email,invoice_prefix,vat_rate,
        gcash_number,logo_path,gcash_qr_path
    )
     VALUES (?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        'sssssdsss',
        $spa_name,
        $address,
        $contact_number,
        $email,
        $invoice_prefix,
        $vat_rate,
        $gcash_number,
        $logo_path,
        $gcash_qr_path
    );
}

$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['success' => $success]);
