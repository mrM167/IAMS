<?php
// upload_document.php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $user = getCurrentUser();
    $upload_dir = 'uploads/';
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['document'];
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'heic'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed_ext)) {
        $_SESSION['error'] = "Invalid file type. Allowed: PDF, JPEG, PNG, DOCX, HEIC";
        header("Location: dashboard.php");
        exit();
    }
    
    if ($file['size'] > 10 * 1024 * 1024) {
        $_SESSION['error'] = "File too large. Maximum 10MB.";
        header("Location: dashboard.php");
        exit();
    }
    
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("INSERT INTO documents (user_id, doc_type, filename, file_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['id'], $_POST['doc_type'], $file['name'], $filepath]);
        
        $_SESSION['success'] = "Document uploaded successfully!";
    } else {
        $_SESSION['error'] = "Upload failed. Please try again.";
    }
    
    header("Location: dashboard.php");
    exit();
}
?>