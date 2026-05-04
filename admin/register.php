<?php
// admin/register.php — Admin/Coordinator Registration
// Accessible when: no admins exist (first run) OR logged in as admin
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

$db = Database::getInstance();

// Count existing admin/coordinator accounts
$adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','coordinator')")->fetchColumn();

// Get current user if logged in
$currentUser = null;
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
}

// Security: Allow if no admins exist (first run) OR logged in as admin
$allowed = ($adminCount == 0) || ($currentUser && $currentUser['role'] === 'admin');

if (!$allowed) {
    http_response_code(403);
    die('<!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Access Denied — IAMS</title>
    <style>
    body{font-family:sans-serif;text-align:center;margin-top:4rem;color:#721c24}
    h2{margin-bottom:.5rem}a{color:#0a2f44}
    </style>
    </head>
    <body>
    <h2>🔒 Access Denied</h2>
    <p>Admin accounts can only be created by existing administrators or during initial setup.</p>
    <p><a href="/login.php">Login as Admin</a> | <a href="/index.php">Go Home</a></p>
    </body>
    </html>');
    exit();
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => strtolower(trim($_POST['email'] ?? '')),
        'phone'     => trim($_POST['phone'] ?? ''),
        'role'      => in_array($_POST['role'] ?? '', ['admin', 'coordinator']) ? $_POST['role'] : 'coordinator',
        'password'  => $_POST['password'] ?? '',
        'confirm'   => $_POST['confirm_password'] ?? '',
    ];

    // ── Validation ──────────────────────────────
    if (!$data['full_name']) {
        $errors[] = 'Full name is required.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email address required.';
    }
    
    $pwErrors = Auth::validatePasswordStrength($data['password']);
    $errors = array_merge($errors, $pwErrors);
    
    if ($data['password'] !== $data['confirm']) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Uniqueness Check ────────────────────────
    if (!$errors) {
        $emailChk = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $emailChk->execute([$data['email']]);
        if ($emailChk->fetch()) {
            $errors[] = 'This email is already registered. Please use a different email.';
        }
    }

    // ── Create User ─────────────────────────────
    if (!$errors) {
        try {
            $db->prepare("INSERT INTO users (email, password_hash, full_name, phone, role, is_active) VALUES (?, ?, ?, ?, ?, 1)")
               ->execute([
                   $data['email'],
                   Auth::hashPassword($data['password']),
                   $data['full_name'],
                   $data['phone'],
                   $data['role'],
               ]);
            
            $success = true;
            $successRole = $data['role'];
            $successEmail = $data['email'];
            
        } catch (Exception $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Register Admin/Coordinator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> — IAMS</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap');
:root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,var(--navy) 0%,var(--teal) 100%);min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:520px;overflow:hidden}
.card-top{background:var(--navy);padding:1.75rem 2rem;text-align:center}
.card-top h1{font-family:'Playfair Display',serif;color:#fff;font-size:1.5rem;margin-bottom:.2rem}
.card-top p{color:var(--gold);font-size:.82rem}
.card-body{padding:2rem}
.alert-error{background:#f8d7da;color:#721c24;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.88rem}
.alert-error div{margin-bottom:.15rem}
.alert-error div:last-child{margin-bottom:0}
.alert-success{background:#d4edda;color:#155724;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem}
.alert-info{background:#d1ecf1;color:#0c5460;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.85rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.82rem;font-weight:600;margin-bottom:.3rem;color:#374151}
.req{color:#c0392b}
.form-group input,.form-group select{width:100%;padding:.7rem .9rem;border:1px solid #ddd;border-radius:7px;font-size:.9rem;font-family:inherit}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(26,90,122,.1)}
.pw-hint{font-size:.75rem;color:#6b7280;margin-top:.3rem}
.pw-strength{height:4px;border-radius:2px;margin-top:.4rem;transition:all .3s;background:#e5e7eb}
.btn{width:100%;padding:.85rem;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;margin-top:.5rem}
.btn:hover{background:var(--teal)}
.links{text-align:center;margin-top:1.25rem;font-size:.85rem;color:#6b7280}
.links a{color:var(--navy);font-weight:600;text-decoration:none}
.role-info{font-size:.75rem;color:var(--muted);margin-top:.3rem}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <h1>🔐 Register Admin/Coordinator</h1>
    <p>Internship & Attachment Management System</p>
  </div>
  <div class="card-body">
    
    <?php if ($success): ?>
      <div class="alert-success">
        ✅ <strong><?php echo ucfirst($successRole); ?> account created!</strong><br>
        <span style="font-size:.85rem">Email: <strong><?php echo htmlspecialchars($successEmail); ?></strong> can now log in.</span>
      </div>
      
      <div style="text-align:center;margin:1.5rem 0">
        <a href="/login.php" class="btn" style="display:inline-block;width:auto;padding:.75rem 2rem;text-decoration:none;margin:0 .5rem">Go to Login →</a>
        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
        <a href="/admin/register.php" class="btn" style="display:inline-block;width:auto;padding:.75rem 2rem;text-decoration:none;background:var(--teal);margin:0 .5rem">➕ Create Another</a>
        <?php endif; ?>
      </div>
      
    <?php else: ?>
    
      <?php if ($errors): ?>
      <div class="alert-error">
        <?php foreach ($errors as $e): ?><div>• <?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
      </div>
      <?php endif; ?>
      
      <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
      <div class="alert-info" style="background:#fff3cd;color:#856404">
        👋 Logged in as <strong><?php echo htmlspecialchars($currentUser['full_name'] ?? 'Admin'); ?></strong> — Creating a new staff account.
      </div>
      <?php endif; ?>
      
      <form method="POST">
        <?php echo csrf_field(); ?>
        
        <div class="form-group">
          <label>Full Name <span class="req">*</span></label>
          <input type="text" name="full_name" required placeholder="e.g. Dr. Sarah Admin" 
                 value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
          <label>Email Address <span class="req">*</span></label>
          <input type="email" name="email" required placeholder="admin@ub.ac.bw"
                 value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
          <label>Phone Number</label>
          <input type="tel" name="phone" placeholder="+267 71XXXXXX"
                 value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
          <label>Role <span class="req">*</span></label>
          <select name="role" required>
            <option value="admin" <?php echo ($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>
              👑 Admin — Full system access, can manage users
            </option>
            <option value="coordinator" <?php echo ($_POST['role'] ?? 'coordinator') === 'coordinator' ? 'selected' : ''; ?>>
              📋 Coordinator — Application review, matching, assessments
            </option>
          </select>
          <div class="role-info">
            <strong>Admin:</strong> Everything + user management | 
            <strong>Coordinator:</strong> Cannot manage users
          </div>
        </div>
        
        <div class="form-group">
          <label>Password <span class="req">*</span></label>
          <input type="password" name="password" id="pw" required placeholder="Min 8 characters" oninput="checkStrength(this.value)">
          <div class="pw-strength" id="pwBar"></div>
          <div class="pw-hint">Must contain: uppercase · lowercase · number · special character (!@#$%^&*)</div>
        </div>
        
        <div class="form-group">
          <label>Confirm Password <span class="req">*</span></label>
          <input type="password" name="confirm_password" required placeholder="Repeat password">
        </div>
        
        <button type="submit" class="btn">
          ➕ Create Account
        </button>
      </form>
      
    <?php endif; ?>
    
    <div class="links">
      <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
      <a href="/admin/index.php">← Back to Dashboard</a> | 
      <a href="/admin/users.php">Manage Users</a>
      <?php else: ?>
      <a href="/login.php">Already registered? Login</a> | 
      <a href="/index.php">Home</a>
      <?php endif; ?>
    </div>
    
  </div>
</div>

<script>
function checkStrength(pw){
  var score=0;
  if(pw.length>=8)score++;
  if(/[A-Z]/.test(pw))score++;
  if(/[a-z]/.test(pw))score++;
  if(/[0-9]/.test(pw))score++;
  if(/[^A-Za-z0-9]/.test(pw))score++;
  var bar=document.getElementById('pwBar');
  var colors=['#e5e7eb','#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
  var widths=['0%','20%','40%','60%','80%','100%'];
  bar.style.background=colors[score];
  bar.style.width=widths[score];
}
</script>
</body>
</html>