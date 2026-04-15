<?php
// setup.php — First-run installer. DELETE THIS FILE after running it once.
// Visit: yourdomain.com/setup.php

// Hard lock: only allow from localhost OR if no admin exists yet
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance();
$adminExists = (int)$db->query("SELECT COUNT(*) FROM users WHERE role IN ('admin','coordinator')")->fetchColumn();

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1']);

// Once an admin exists, only localhost can re-run setup
if ($adminExists > 0 && !$isLocal) {
    http_response_code(403);
    die('<h2>Setup already completed. Delete setup.php from your server.</h2>');
}

$messages = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    $adminEmail    = filter_var($_POST['admin_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $adminName     = trim($_POST['admin_name'] ?? 'System Administrator');
    $adminPass     = $_POST['admin_password'] ?? '';
    $coordEmail    = filter_var($_POST['coord_email'] ?? '', FILTER_SANITIZE_EMAIL);
    $coordName     = trim($_POST['coord_name'] ?? 'Dr. Coordinator');
    $coordPass     = $_POST['coord_password'] ?? '';
    $errors = [];

    if (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid admin email required.';
    if (strlen($adminPass) < 8) $errors[] = 'Admin password must be at least 8 characters.';
    if (!$coordEmail || !filter_var($coordEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid coordinator email required.';
    if (strlen($coordPass) < 8) $errors[] = 'Coordinator password must be at least 8 characters.';
    if ($adminEmail === $coordEmail) $errors[] = 'Admin and coordinator must have different emails.';

    if (!$errors) {
        $db->beginTransaction();
        try {
            // Remove old admin/coordinator accounts
            $db->exec("DELETE FROM users WHERE role IN ('admin','coordinator')");

            $hash = fn($p) => password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);

            $db->prepare("INSERT INTO users (email,password_hash,full_name,role,is_active) VALUES (?,?,?,'admin',1)")
               ->execute([$adminEmail, $hash($adminPass), $adminName]);

            $db->prepare("INSERT INTO users (email,password_hash,full_name,role,is_active) VALUES (?,?,?,'coordinator',1)")
               ->execute([$coordEmail, $hash($coordPass), $coordName]);

            // Create uploads directory
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Write .htaccess to uploads to prevent script execution
            $uploadHtaccess = $uploadDir . '.htaccess';
            if (!file_exists($uploadHtaccess)) {
                file_put_contents($uploadHtaccess,
                    "Options -Indexes\nDeny from all\n<FilesMatch \"\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n  Deny from all\n</FilesMatch>\n"
                );
            }

            $db->commit();
            $messages[] = ['type' => 'success', 'text' => "✅ Admin account created: {$adminEmail}"];
            $messages[] = ['type' => 'success', 'text' => "✅ Coordinator account created: {$coordEmail}"];
            $messages[] = ['type' => 'success', 'text' => "✅ Uploads directory configured."];
            $messages[] = ['type' => 'warning', 'text' => "⚠️ IMPORTANT: Delete setup.php from your server immediately!"];
            $done = true;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    foreach ($errors as $e) {
        $messages[] = ['type' => 'error', 'text' => $e];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>IAMS Setup</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:#f0f4f8;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.12);width:100%;max-width:580px;overflow:hidden}
.card-top{background:#0a2f44;padding:2rem;text-align:center;color:#fff}
.card-top h1{font-size:1.5rem;font-weight:700;margin-bottom:.25rem}
.card-top p{color:#c9a84c;font-size:.85rem}
.card-body{padding:2rem}
.alert{padding:.75rem 1rem;border-radius:8px;margin-bottom:.75rem;font-size:.9rem}
.alert-success{background:#d4edda;color:#155724}
.alert-error{background:#f8d7da;color:#721c24}
.alert-warning{background:#fff3cd;color:#856404}
.alert-info{background:#d1ecf1;color:#0c5460}
.section-label{font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#1a5a7a;margin:1.25rem 0 .75rem;padding-bottom:.35rem;border-bottom:2px solid #e5e7eb}
.form-group{margin-bottom:.9rem}
.form-group label{display:block;font-size:.82rem;font-weight:600;margin-bottom:.3rem;color:#374151}
.form-group input{width:100%;padding:.65rem .9rem;border:1px solid #ddd;border-radius:7px;font-size:.9rem;font-family:inherit}
.form-group input:focus{outline:none;border-color:#1a5a7a;box-shadow:0 0 0 3px rgba(26,90,122,.1)}
.btn{width:100%;padding:.85rem;background:#0a2f44;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;margin-top:.5rem}
.btn:hover{background:#1a5a7a}
.done-box{text-align:center;padding:1rem 0}
.done-box a{display:inline-block;margin-top:1rem;background:#1a7a4a;color:#fff;padding:.75rem 2rem;border-radius:8px;text-decoration:none;font-weight:700}
pre{background:#111;color:#c9a84c;padding:1rem;border-radius:8px;font-size:.82rem;overflow-x:auto;margin-top:.75rem}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <h1>⚙️ IAMS Setup</h1>
    <p>University of Botswana — First-run configuration</p>
  </div>
  <div class="card-body">
    <?php foreach ($messages as $m): ?>
    <div class="alert alert-<?php echo $m['type']; ?>"><?php echo htmlspecialchars($m['text']); ?></div>
    <?php endforeach; ?>

    <?php if ($done): ?>
    <div class="done-box">
      <p style="font-size:1.05rem;font-weight:600;color:#155724">Setup complete! 🎉</p>
      <p style="color:#5a7080;margin-top:.5rem;font-size:.9rem">You can now log in to the system.</p>
      <a href="/login.php">Go to Login →</a>
      <pre>⚠️ SECURITY: Run this command on your server:
rm setup.php</pre>
    </div>
    <?php else: ?>
    <p style="color:#5a7080;font-size:.88rem;margin-bottom:1.25rem">This will create the admin and coordinator accounts. Run once, then delete this file.</p>

    <form method="POST">
      <input type="hidden" name="run_setup" value="1">

      <div class="section-label">Administrator Account</div>
      <div class="form-group">
        <label>Admin Full Name</label>
        <input type="text" name="admin_name" value="System Administrator" required>
      </div>
      <div class="form-group">
        <label>Admin Email *</label>
        <input type="email" name="admin_email" value="admin@ub.ac.bw" required>
      </div>
      <div class="form-group">
        <label>Admin Password * (min 8 chars)</label>
        <input type="password" name="admin_password" required placeholder="Strong password here">
      </div>

      <div class="section-label">Coordinator Account</div>
      <div class="form-group">
        <label>Coordinator Full Name</label>
        <input type="text" name="coord_name" value="Dr. Coordinator" required>
      </div>
      <div class="form-group">
        <label>Coordinator Email *</label>
        <input type="email" name="coord_email" value="coordinator@ub.ac.bw" required>
      </div>
      <div class="form-group">
        <label>Coordinator Password * (min 8 chars)</label>
        <input type="password" name="coord_password" required placeholder="Strong password here">
      </div>

      <button type="submit" class="btn">Run Setup →</button>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
