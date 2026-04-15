<?php
// admin/users.php — Internal user management (admin only)
// Coordinators can view but not modify — only admins can create/edit/disable
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$isAdmin = ($user['role'] === 'admin');
$msg = $err = '';

// Toggle active (admin only)
if ($isAdmin && isset($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    if ($uid !== (int)$user['id']) { // prevent self-disable
        $db->prepare("UPDATE users SET is_active=1-is_active WHERE user_id=? AND role IN ('admin','coordinator')")->execute([$uid]);
    }
    header('Location: /admin/users.php?msg=toggled'); exit();
}

// Delete (admin only, not self)
if ($isAdmin && isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    if ($uid !== (int)$user['id']) {
        $db->prepare("DELETE FROM users WHERE user_id=? AND role IN ('admin','coordinator')")->execute([$uid]);
    }
    header('Location: /admin/users.php?msg=deleted'); exit();
}

// Edit: load
$editUser = null;
if ($isAdmin && isset($_GET['edit'])) {
    $eStmt = $db->prepare("SELECT * FROM users WHERE user_id=? AND role IN ('admin','coordinator')");
    $eStmt->execute([(int)$_GET['edit']]);
    $editUser = $eStmt->fetch();
}

// Create / Update
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action   = $_POST['action'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $role     = in_array($_POST['role'] ?? '', ['admin', 'coordinator']) ? $_POST['role'] : 'coordinator';
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $userId   = (int)($_POST['user_id'] ?? 0);

    if (!$fullName) {
        $err = 'Full name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Valid email required.';
    } else {
        if ($action === 'create') {
            $pwErrors = Auth::validatePasswordStrength($password);
            if ($pwErrors) {
                $err = implode(' ', $pwErrors);
            } else {
                $chk = $db->prepare("SELECT user_id FROM users WHERE email=?");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    $err = 'Email already registered.';
                } else {
                    $db->prepare("INSERT INTO users (email, password_hash, full_name, phone, role, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                       ->execute([$email, Auth::hashPassword($password), $fullName, $phone, $role]);
                    $msg = ucfirst($role) . ' account created for ' . $fullName . '.';
                    $editUser = null;
                    header('Location: /admin/users.php?msg=' . urlencode($msg)); exit();
                }
            }
        } elseif ($action === 'update' && $userId) {
            $chk = $db->prepare("SELECT user_id FROM users WHERE email=? AND user_id!=?");
            $chk->execute([$email, $userId]);
            if ($chk->fetch()) {
                $err = 'Email already used by another account.';
            } else {
                $db->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=? WHERE user_id=? AND role IN ('admin','coordinator')")
                   ->execute([$fullName, $email, $phone, $role, $userId]);
                if ($password) {
                    $pwErrors = Auth::validatePasswordStrength($password);
                    if ($pwErrors) {
                        $err = implode(' ', $pwErrors);
                    } else {
                        $db->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([Auth::hashPassword($password), $userId]);
                    }
                }
                if (!$err) {
                    header('Location: /admin/users.php?msg=updated'); exit();
                }
            }
        }
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['toggled' => 'Account status toggled.', 'deleted' => 'Account deleted.', 'updated' => 'Account updated.'];
    $msg = $msgs[$_GET['msg']] ?? urldecode($_GET['msg']);
}

// Load internal users
$internalUsers = $db->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM applications WHERE reviewed_by = u.user_id) as apps_reviewed,
           (SELECT COUNT(*) FROM matches WHERE coordinator_id = u.user_id) as matches_made
    FROM users u
    WHERE u.role IN ('admin', 'coordinator')
    ORDER BY u.role, u.full_name
")->fetchAll();

$pageTitle = 'User Management';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">&#128101; Internal User Management</div>
<div class="page-sub">
  <?php if ($isAdmin): ?>Manage admin and coordinator accounts<?php else: ?>Coordinator accounts (view only — admin access required to make changes)<?php endif; ?>
</div>

<?php if ($msg): ?><div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err):  ?><div class="alert alert-error">&#9888; <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<?php if (!$isAdmin): ?>
<div class="alert alert-warning">&#9888; You are logged in as a <strong>Coordinator</strong>. Only Admins can create or modify internal accounts.</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr <?php echo $isAdmin ? '380px' : ''; ?>;gap:1.5rem;align-items:start">

<!-- User list -->
<div class="card">
<table>
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Apps Reviewed</th><th>Matches Made</th><th>Last Login</th><th>Status</th><?php if($isAdmin):?><th></th><?php endif;?></tr></thead>
  <tbody>
  <?php foreach ($internalUsers as $u): ?>
  <tr style="<?php echo ($u['user_id'] == $user['id']) ? 'background:#f0f7ff' : ''; ?>">
    <td>
      <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
      <?php if ($u['user_id'] == $user['id']): ?><span style="font-size:.7rem;color:var(--teal);font-weight:700"> (You)</span><?php endif; ?>
    </td>
    <td class="text-muted"><?php echo htmlspecialchars($u['email']); ?></td>
    <td><span class="badge badge-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span></td>
    <td class="text-muted"><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
    <td style="text-align:center"><?php echo $u['apps_reviewed']; ?></td>
    <td style="text-align:center"><?php echo $u['matches_made']; ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $u['last_login'] ? date('j M y H:i', strtotime($u['last_login'])) : 'Never'; ?></td>
    <td><span class="badge badge-<?php echo $u['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $u['is_active'] ? 'ACTIVE' : 'DISABLED'; ?></span></td>
    <?php if ($isAdmin): ?>
    <td style="white-space:nowrap">
      <a href="?edit=<?php echo $u['user_id']; ?>" class="btn btn-gold btn-sm">Edit</a>
      <?php if ($u['user_id'] != $user['id']): ?>
      <a href="?toggle=<?php echo $u['user_id']; ?>" class="btn btn-outline btn-sm" onclick="return confirm('Toggle account status?')"><?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?></a>
      <a href="?delete=<?php echo $u['user_id']; ?>" class="btn btn-red btn-sm" onclick="return confirm('Permanently delete this account?')">Del</a>
      <?php endif; ?>
    </td>
    <?php endif; ?>
  </tr>
  <?php endforeach; ?>
  <?php if (!$internalUsers): ?><tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem">No internal users found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<!-- Create / Edit form (admin only) -->
<?php if ($isAdmin): ?>
<div class="card">
  <div class="card-header"><h3><?php echo $editUser ? '&#9999;&#65039; Edit Account' : '&#43; Create Account'; ?></h3></div>
  <div class="card-body">
  <form method="POST">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
    <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>"><?php endif; ?>

    <div class="form-group">
      <label>Full Name *</label>
      <input type="text" name="full_name" required value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>">
    </div>
    <div class="form-group">
      <label>Email Address *</label>
      <input type="email" name="email" required value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>">
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input type="tel" name="phone" value="<?php echo htmlspecialchars($editUser['phone'] ?? ''); ?>" placeholder="+267 71XXXXXX">
    </div>
    <div class="form-group">
      <label>Role *</label>
      <select name="role">
        <option value="coordinator" <?php echo ($editUser['role'] ?? 'coordinator') === 'coordinator' ? 'selected' : ''; ?>>Coordinator</option>
        <option value="admin" <?php echo ($editUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
      </select>
    </div>
    <div class="form-group">
      <label><?php echo $editUser ? 'New Password (leave blank to keep current)' : 'Password *'; ?></label>
      <input type="password" name="password" <?php echo $editUser ? '' : 'required'; ?> placeholder="Min 8 chars">
      <div style="font-size:.75rem;color:var(--muted);margin-top:.3rem">Uppercase · lowercase · number · special character</div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%"><?php echo $editUser ? 'Update Account' : 'Create Account'; ?></button>
    <?php if ($editUser): ?><a href="/admin/users.php" style="display:block;text-align:center;margin-top:.75rem;color:var(--muted);font-size:.85rem">Cancel</a><?php endif; ?>
  </form>
  </div>
</div>
<?php endif; ?>

</div>

<!-- Role explanation -->
<div class="card" style="margin-top:1.5rem">
<div class="card-body">
<h3 style="margin-bottom:.75rem;color:var(--navy)">&#128274; Role Permissions</h3>
<table>
  <thead><tr><th>Feature</th><th>Coordinator</th><th>Admin</th></tr></thead>
  <tbody>
  <?php
  $perms = [
      'View dashboard & stats'         => [true,  true],
      'Review & update applications'   => [true,  true],
      'Run matching algorithm'         => [true,  true],
      'Manage job posts'               => [true,  true],
      'View all students & documents'  => [true,  true],
      'View all organisations'         => [true,  true],
      'Record site visit assessments'  => [true,  true],
      'Grade final reports'            => [true,  true],
      'View placement reports'         => [true,  true],
      'Create internal user accounts'  => [false, true],
      'Edit / disable user accounts'   => [false, true],
      'Send bulk reminders'            => [true,  true],
      'Export placement data (CSV)'    => [true,  true],
  ];
  foreach ($perms as $feature => $access):
  ?>
  <tr>
    <td><?php echo $feature; ?></td>
    <td style="text-align:center"><?php echo $access[0] ? '<span style="color:var(--green);font-weight:700">&#10003;</span>' : '<span style="color:#ccc">&#10007;</span>'; ?></td>
    <td style="text-align:center"><?php echo $access[1] ? '<span style="color:var(--green);font-weight:700">&#10003;</span>' : '<span style="color:#ccc">&#10007;</span>'; ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

</div>
<footer class="site-footer">IAMS &copy; <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>