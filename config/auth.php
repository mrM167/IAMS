<?php
// config/auth.php — Authentication helpers
require_once __DIR__ . '/database.php';

class Auth {
    private const MAX_ATTEMPTS   = 5;
    private const LOCKOUT_MINS   = 15;
    private const HASH_ALGO      = PASSWORD_BCRYPT;
    private const HASH_COST      = 12;

    public static function hashPassword(string $password): string {
        return password_hash($password, self::HASH_ALGO, ['cost' => self::HASH_COST]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function validatePasswordStrength(string $password): array {
        $errors = [];
        if (strlen($password) < 8)                          $errors[] = 'At least 8 characters required.';
        if (!preg_match('/[A-Z]/', $password))              $errors[] = 'At least one uppercase letter required.';
        if (!preg_match('/[a-z]/', $password))              $errors[] = 'At least one lowercase letter required.';
        if (!preg_match('/[0-9]/', $password))              $errors[] = 'At least one number required.';
        if (!preg_match('/[^A-Za-z0-9]/', $password))      $errors[] = 'At least one special character required (!@#$%^&*).';
        return $errors;
    }

    // ── Brute-force protection ────────────────────────────────────────
    public static function isLockedOut(string $email, string $ip): bool {
        $db    = Database::getInstance();
        $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_MINS * 60);

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE (email = ? OR ip_address = ?) AND attempted_at > ?"
        );
        $stmt->execute([$email, $ip, $since]);
        return (int)$stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function recordFailedAttempt(string $email, string $ip): void {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?,?)")
           ->execute([$email, $ip]);
    }

    public static function clearAttempts(string $email, string $ip): void {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?")
           ->execute([$email, $ip]);
    }

    public static function remainingAttempts(string $email, string $ip): int {
        $db    = Database::getInstance();
        $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_MINS * 60);
        $stmt  = $db->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE (email = ? OR ip_address = ?) AND attempted_at > ?"
        );
        $stmt->execute([$email, $ip, $since]);
        return max(0, self::MAX_ATTEMPTS - (int)$stmt->fetchColumn());
    }

    // ── Login ─────────────────────────────────────────────────────────
    public static function login(string $email, string $password): array {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $email = strtolower(trim($email));

        if (self::isLockedOut($email, $ip)) {
            return ['ok' => false, 'error' => 'Too many failed attempts. Account locked for ' . self::LOCKOUT_MINS . ' minutes.'];
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !self::verifyPassword($password, $user['password_hash'])) {
            self::recordFailedAttempt($email, $ip);
            $left = self::remainingAttempts($email, $ip);
            $msg  = $left > 0
                ? "Invalid email or password. {$left} attempt(s) remaining."
                : 'Too many failed attempts. Account locked for ' . self::LOCKOUT_MINS . ' minutes.';
            return ['ok' => false, 'error' => $msg];
        }

        // Check if hash needs upgrade
        if (password_needs_rehash($user['password_hash'], self::HASH_ALGO, ['cost' => self::HASH_COST])) {
            $db->prepare("UPDATE users SET password_hash=? WHERE user_id=?")
               ->execute([self::hashPassword($password), $user['user_id']]);
        }

        self::clearAttempts($email, $ip);
        $db->prepare("UPDATE users SET last_login=NOW() WHERE user_id=?")
           ->execute([$user['user_id']]);

        // Set session
        session_regenerate_id(true);
        $_SESSION['user_id']     = $user['user_id'];
        $_SESSION['email']       = $user['email'];
        $_SESSION['full_name']   = $user['full_name'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['_last_activity'] = time();
        $_SESSION['_created']    = time();

        return ['ok' => true, 'user' => $user];
    }

    public static function logout(): void {
        session_unset();
        session_destroy();
        // Clear cookie
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
    }
}