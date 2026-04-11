<?php
// config/admin_session.php
require_once __DIR__ . '/session.php';

function requireAdmin() {
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'admin' && $role !== 'coordinator') {
        header("Location: ../dashboard.php");
        exit();
    }
}

function isAdminOrCoordinator() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'coordinator']);
}
?>
