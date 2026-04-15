<?php
// config/session.php — PHP 7.4 compatible

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('SESSION_TIMEOUT', 1800); // 30 minutes

// Session fixation prevention
if (!isset($_SESSION['_created'])) {
    $_SESSION['_created'] = time();
} elseif (time() - $_SESSION['_created'] > 300) {
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

// Inactivity timeout
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit();
    }
    $_SESSION['_last_activity'] = time();
}

// No-cache headers (prevent back-button after logout)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), $token);
}

function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die('CSRF validation failed. Please go back and try again.');
    }
}

// Auth helpers
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect . '?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

// PHP 7.4: no union type string|array — use $roles as mixed, check with is_array
function requireRole($roles): void {
    requireLogin();
    $allowed = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['role'] ?? '', $allowed, true)) {
        header('Location: /dashboard.php');
        exit();
    }
}

function requireAdmin(): void {
    requireLogin('/login.php');
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'coordinator'], true)) {
        header('Location: /dashboard.php');
        exit();
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'email' => $_SESSION['email']     ?? '',
        'name'  => $_SESSION['full_name'] ?? '',
        'role'  => $_SESSION['role']      ?? 'student',
    ];
}

function setFlash(string $type, string $msg): void {
    $_SESSION['_flash'][$type] = $msg;
}

function getFlash(string $type): string {
    $msg = $_SESSION['_flash'][$type] ?? '';
    unset($_SESSION['_flash'][$type]);
    return $msg;
}

function renderFlash(): string {
    $out = '';
    $colors = [
        'success' => 'background:#d4edda;color:#155724',
        'error'   => 'background:#f8d7da;color:#721c24',
        'info'    => 'background:#d1ecf1;color:#0c5460',
        'warning' => 'background:#fff3cd;color:#856404',
    ];
    foreach ($colors as $t => $style) {
        $msg = getFlash($t);
        if ($msg) {
            $out .= '<div style="' . $style . ';padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem;">'
                  . htmlspecialchars($msg) . '</div>';
        }
    }
    return $out;
}
