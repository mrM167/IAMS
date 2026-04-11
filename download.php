<?php
// download.php - Serve uploaded documents securely
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Invalid request.');
}

$user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

// Only allow users to download their own documents (or admins any)
if ($user['role'] === 'admin' || $user['role'] === 'coordinator') {
    $stmt = $db->prepare("SELECT * FROM documents WHERE doc_id = ?");
    $stmt->execute([$_GET['id']]);
} else {
    $stmt = $db->prepare("SELECT * FROM documents WHERE doc_id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $user['id']]);
}

$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    die('Document not found or access denied.');
}

$filepath = $doc['file_path'];
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found on server.');
}

$ext = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
$mime_types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'heic' => 'image/heic',
];
$mime = $mime_types[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . addslashes($doc['filename']) . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit();
?>
