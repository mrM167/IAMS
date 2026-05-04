<?php
// login.php — PHP 7.4 compatible
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'organisation') {
        header('Location: /org/dashboard.php');
    } elseif ($role === 'admin' || $role === 'coordinator') {
        header('Location: /admin/index.php');
    } else {
        header('Location: /dashboard.php');
    }
    exit();
}

$error   = '';
$timeout = isset($_GET['timeout']);
$next    = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $result = Auth::login($email, $password);
        if ($result['ok']) {
            $role = $result['user']['role'];
            if ($role === 'organisation') {
                $dest = '/org/dashboard.php';
            } elseif ($role === 'admin' || $role === 'coordinator') {
                $dest = '/admin/index.php';
            } else {
                $dest = '/dashboard.php';
            }
            // Safe redirect: only allow relative paths on same host
            if ($next && substr($next, 0, 1) === '/' && strpos($next, '//') === false) {
                $dest = $next;
            }
            header('Location: ' . $dest);
            exit();
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — IAMS</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap');
:root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,var(--navy) 0%,var(--teal) 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
.login-card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:420px;overflow:hidden}
.login-top{background:var(--navy);padding:2rem;text-align:center}
.login-top h1{font-family:'Playfair Display',serif;color:#fff;font-size:1.8rem;margin-bottom:.25rem}
.login-top p{color:var(--gold);font-size:.85rem;font-style:italic}
.login-body{padding:2rem}
.alert-error{background:#f8d7da;color:#721c24;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem}
.alert-warning{background:#fff3cd;color:#856404;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.82rem;font-weight:600;margin-bottom:.3rem;color:#374151}
.form-group input{width:100%;padding:.75rem 1rem;border:1px solid #ddd;border-radius:8px;font-size:.95rem;font-family:inherit;transition:border .15s}
.form-group input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(26,90,122,.12)}
.password-wrapper{position:relative}
.password-wrapper input{padding-right:3rem}
.toggle-pw{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:#9ca3af;padding:.25rem}
.btn-login{width:100%;padding:.85rem;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s}
.btn-login:hover{background:var(--teal)}
.login-footer{text-align:center;margin-top:1.25rem;font-size:.85rem;color:#6b7280}
.login-footer a{color:var(--navy);font-weight:600;text-decoration:none}
.login-footer a:hover{text-decoration:underline}
.divider{border:none;border-top:1px solid #f0f0f0;margin:1.25rem 0}
.role-links{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.75rem}
.role-link{text-align:center;padding:.5rem;border:1px solid #eee;border-radius:6px;font-size:.78rem;color:#6b7280;text-decoration:none;transition:all .15s}
.role-link:hover{border-color:var(--teal);color:var(--teal)}
</style>
</head>
<body>
<div class="login-card">
  <div class="login-top">
    <svg width="50" height="50" viewBox="0 0 50 50" style="margin-bottom:.75rem">
      <circle cx="25" cy="25" r="23" fill="none" stroke="#c9a84c" stroke-width="2"/>
      <text x="25" y="31" text-anchor="middle" font-family="Georgia,serif" font-size="14" font-weight="bold" fill="white">UB</text>
    </svg>
    <h1>IAMS</h1>
    <p>"We give the pathway to future leaders"</p>
  </div>
  <div class="login-body">
    <?php if ($timeout): ?>
    <div class="alert-warning">&#8987; Session expired due to inactivity. Please log in again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert-error">&#9888;&#65039; <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required autofocus autocomplete="email"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
               placeholder="your@email.com">
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="password-wrapper">
          <input type="password" name="password" id="pw" required autocomplete="current-password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
          <button type="button" class="toggle-pw" onclick="togglePw()">&#128065;</button>
        </div>
      </div>
      <button type="submit" class="btn-login">Login &rarr;</button>
    </form>

    <hr class="divider">
    <div class="login-footer">
      <a href="/forgot_password.php">Forgot password?</a>
    </div>
    <div class="login-footer" style="margin-top:.75rem">Don't have an account? 
      <div style="text-align:center;margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f0f0f0">
  <a href="/admin/register.php" style="font-size:.75rem;color:#9ca3af;text-decoration:none">🔐 Admin Registration</a>
</div>
    </div>
    <div class="role-links">
      <a href="/register.php" class="role-link">&#127891; Student</a>
      <a href="/register_org.php" class="role-link">&#127970; Organisation</a>
    </div>
    <div style="text-align:center;margin-top:.75rem">
      <a href="/index.php" style="font-size:.8rem;color:#9ca3af;text-decoration:none">&larr; Back to home</a>
    </div>
  </div>
</div>
<script>
function togglePw(){
  var i=document.getElementById('pw');
  i.type=i.type==='password'?'text':'password';
}
</script>
</body>
</html>
