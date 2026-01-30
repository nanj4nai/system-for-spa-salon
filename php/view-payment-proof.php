<?php
session_start();

// 🔐 Admin-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$filename = basename($_GET['file'] ?? '');

if ($filename === '') {
    http_response_code(400);
    exit('Missing file');
}

$path = __DIR__ . '/../secure_uploads/payments/' . $filename;

if (!file_exists($path)) {
    http_response_code(404);
    exit('Not found');
}

// Detect mime type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path);

header("Content-Type: $mime");
header("Content-Length: " . filesize($path));
header("X-Content-Type-Options: nosniff");

readfile($path);
exit;
