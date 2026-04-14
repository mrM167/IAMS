<?php
// submit_application.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = getCurrentUser();
    $database = new Database();
    $db = $database->getConnection();
    
    $check = $db->prepare("SELECT app_id FROM applications WHERE user_id = ?");
    $check->execute([$user['id']]);
    if ($check->rowCount() > 0) {
        $_SESSION['error'] = "You have already submitted an application.";
        header("Location: dashboard.php");
        exit();
    }
    
    $stmt = $db->prepare("INSERT INTO applications (user_id, full_name, student_number, programme, skills, preferred_location) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $user['id'],
        $_POST['full_name'],
        $_POST['student_number'],
        $_POST['programme'],
        $_POST['skills'],
        $_POST['preferred_location']
    ]);
    
    $_SESSION['success'] = "Application submitted successfully to the Ministry of Labour and Home Affairs!";
    header("Location: dashboard.php");
    exit();
}
?>