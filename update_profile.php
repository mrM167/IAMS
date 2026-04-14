<?php
// update_profile.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = getCurrentUser();
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("UPDATE student_profiles SET linkedin_url = ?, github_url = ?, portfolio_url = ?, skills = ? WHERE user_id = ?");
    $stmt->execute([$_POST['linkedin'], $_POST['github'], $_POST['portfolio'], $_POST['skills'], $user['id']]);
    
    $_SESSION['success'] = "Profile updated successfully!";
    header("Location: dashboard.php");
    exit();
}
?>