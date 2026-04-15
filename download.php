<?php
// download.php — Secure document download (role-checked, no direct file URL access)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400); die('Invalid request.');
}

$user = getCurrentUser();
$db   = Database::getInstance();
$docId = (int)$_GET['id'];

// Admins/coordinators can download any document.
// Students and orgs can only download their own.
if (in_array($user['role'], ['admin', 'coordinator'])) {
    $stmt = $db->prepare("SELECT d.*,u.full_name FROM documents d JOIN users u ON d.user_id=u.user_id WHERE d.doc_id=?");
    $stmt->execute([$docId]);
} elseif ($user['role'] === 'organisation') {
    // Org can download docs of students matched to them
    $stmt = $db->prepare("
        SELECT d.*,u.full_name FROM documents d
        JOIN users u ON d.user_id=u.user_id
        JOIN matches m ON m.user_id=d.user_id
        JOIN organisations o ON m.org_id=o.org_id
        WHERE d.doc_id=? AND o.user_id=? AND m.status='confirmed'
    ");
    $stmt->execute([$docId, $user['id']]);
} else {
    // Student: own documents only
    $stmt = $db->prepare("SELECT d.*,u.full_name FROM documents d JOIN users u ON d.user_id=u.user_id WHERE d.doc_id=? AND d.user_id=?");
    $stmt->execute([$docId, $user['id']]);
}

$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); die('Document not found or access denied.'); }

// Resolve file path relative to document root
$filePath = __DIR__ . '/' . ltrim($doc['file_path'], '/');
if (!file_exists($filePath)) { http_response_code(404); die('File not found on server.'); }

// MIME type map (safe subset)
$ext = strtolower(pathinfo($doc['filename'], PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'heic' => 'image/heic',
];
$mime = $mimes[$ext] ?? 'application/octet-stream';

// Force download for docx and unknown; inline for PDF/images
$disp = in_array($ext, ['pdf','jpg','jpeg','png']) ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode($doc['filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache');
readfile($filePath);
exit();
