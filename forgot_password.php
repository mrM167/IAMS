<?php
// forgot_password.php — Token-based password reset
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) { header('Location: /dashboard.php'); exit(); }

$step    = $_GET['step'] ?? 'request'; // request | reset
$token   = $_GET['token'] ?? '';
$msg     = '';
$error   = '';
$success = false;

// ── Step 1: User submits email ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT user_id FROM users WHERE email=? AND is_active=1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always show the same message (don't reveal if email exists)
        if ($user) {
            // Clean old tokens
            $db->prepare("DELETE FROM password_resets WHERE user_id=? OR expires_at < NOW()")->execute([$user['user_id']]);
            // Generate secure token
            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expires   = date('Y-m-d H:i:s', time() + 3600); // 1 hour
            $db->prepare("INSERT INTO password_resets (user_id,token_hash,expires_at) VALUES (?,?,?)")
               ->execute([$user['user_id'], $tokenHash, $expires]);

            // In production this would send an email. For now, show the reset link directly.
            $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/forgot_password.php?step=reset&token=' . $rawToken;
            // Store in session for demo display (remove in production, use email instead)
            $_SESSION['_reset_link_demo'] = $resetLink;
        }
        $success = true;
    }
}

// ── Step 2: Validate token from URL ──────────────────────────────
$validToken = null;
if ($step === 'reset' && $token) {
    $db   = Database::getInstance();
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT pr.*,u.email FROM password_resets pr JOIN users u ON pr.user_id=u.user_id WHERE pr.token_hash=? AND pr.expires_at > NOW() AND pr.used=0");
    $stmt->execute([$hash]);
    $validToken = $stmt->fetch();
    if (!$validToken) $error = 'This reset link is invalid or has expired. Please request a new one.';
}

// ── Step 2: User submits new password ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    csrf_check();
    $db       = Database::getInstance();
    $hash     = hash('sha256', $_GET['token'] ?? '');
    $stmt     = $db->prepare("SELECT pr.*,u.email FROM password_resets pr JOIN users u ON pr.user_id=u.user_id WHERE pr.token_hash=? AND pr.expires_at > NOW() AND pr.used=0");
    $stmt->execute([$hash]);
    $validToken = $stmt->fetch();

    if (!$validToken) {
        $error = 'Invalid or expired reset link.';
    } else {
        $pw1 = $_POST['password'] ?? '';
        $pw2 = $_POST['confirm'] ?? '';
        $pwErrors = Auth::validatePasswordStrength($pw1);
        if ($pw1 !== $pw2) $pwErrors[] = 'Passwords do not match.';

        if ($pwErrors) {
            $error = implode(' ', $pwErrors);
        } else {
            $db->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([Auth::hashPassword($pw1), $validToken['user_id']]);
            $db->prepare("UPDATE password_resets SET used=1 WHERE token_hash=?")->execute([$hash]);
            // Clear all active sessions (security)
            $db->prepare("DELETE FROM login_attempts WHERE email=?")->execute([$validToken['email']]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Password Reset — IAMS</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
:root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,var(--navy) 0%,var(--teal) 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:420px;overflow:hidden}
.card-top{background:var(--navy);padding:1.75rem 2rem;text-align:center}
.card-top h1{color:#fff;font-size:1.3rem;font-weight:700;margin-bottom:.2rem}
.card-top p{color:var(--gold);font-size:.82rem}
.card-body{padding:2rem}
.alert-error{background:#f8d7da;color:#721c24;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.88rem}
.alert-success{background:#d4edda;color:#155724;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem}
.alert-info{background:#d1ecf1;color:#0c5460;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.85rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.82rem;font-weight:600;margin-bottom:.3rem;color:#374151}
.form-group input{width:100%;padding:.7rem .9rem;border:1px solid #ddd;border-radius:7px;font-size:.9rem;font-family:inherit;transition:border .15s}
.form-group input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(26,90,122,.12)}
.pw-hint{font-size:.75rem;color:#6b7280;margin-top:.3rem}
.btn{width:100%;padding:.8rem;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;margin-top:.5rem}
.btn:hover{background:var(--teal)}
.links{text-align:center;margin-top:1.25rem;font-size:.85rem;color:#6b7280}
.links a{color:var(--navy);font-weight:600;text-decoration:none}
code{background:#f0f4f8;padding:.2rem .5rem;border-radius:4px;font-size:.82rem;word-break:break-all}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <h1>🔐 Password Reset</h1>
    <p>University of Botswana — IAMS</p>
  </div>
  <div class="card-body">
    <?php if ($error): ?><div class="alert-error">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <?php if ($step === 'request'): ?>
      <?php if ($success): ?>
        <div class="alert-success">✅ If your email is registered, a reset link has been generated.</div>
        <?php if (!empty($_SESSION['_reset_link_demo'])): ?>
        <div class="alert-info">
          <strong>Demo mode — in production this would be emailed.</strong><br>
          Your reset link:<br><br>
          <code><?php echo htmlspecialchars($_SESSION['_reset_link_demo']); ?></code>
        </div>
        <?php unset($_SESSION['_reset_link_demo']); ?>
        <?php endif; ?>
      <?php else: ?>
        <p style="color:#5a7080;font-size:.88rem;margin-bottom:1.25rem">Enter your email address and we will send you a password reset link.</p>
        <form method="POST">
          <?php echo csrf_field(); ?>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required autofocus placeholder="your@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
          </div>
          <button type="submit" class="btn">Send Reset Link →</button>
        </form>
      <?php endif; ?>

    <?php elseif ($step === 'reset'): ?>
      <?php if ($success): ?>
        <div class="alert-success">✅ Password changed successfully! You can now log in.</div>
        <div class="links"><a href="/login.php">Go to Login →</a></div>
      <?php elseif ($validToken): ?>
        <p style="color:#5a7080;font-size:.88rem;margin-bottom:1.25rem">Resetting password for: <strong><?php echo htmlspecialchars($validToken['email']); ?></strong></p>
        <form method="POST">
          <?php echo csrf_field(); ?>
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" required placeholder="Min 8 chars, upper, lower, number, symbol">
            <div class="pw-hint">Must contain: uppercase · lowercase · number · special character</div>
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm" required>
          </div>
          <button type="submit" class="btn">Change Password →</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <div class="links"><a href="/login.php">← Back to Login</a></div>
  </div>
</div>
</body>
</html>
