<?php
// admin/students.php — All students with status tracking (US-08, US-16)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();

// Toggle active
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE users SET is_active=1-is_active WHERE user_id=? AND role='student'")->execute([(int)$_GET['toggle']]);
    header('Location: /admin/students.php'); exit();
}

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';

$where = "WHERE u.role='student'";
$params = [];
if ($search) { $where .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.student_number LIKE ?)"; $params = array_fill(0,3,"%$search%"); }
if ($status) { $where .= " AND a.status=?"; $params[] = $status; }

$students = $db->prepare("
    SELECT u.user_id,u.full_name,u.email,u.phone,u.student_number,u.programme,u.is_active,u.created_at,u.last_login,
           a.app_id,a.status as app_status,a.submission_date,a.preferred_location,a.skills,
           o.org_name as matched_org, o.location as org_location,
           sp.linkedin_url,sp.github_url,sp.year_of_study,sp.gpa,
           COUNT(DISTINCT d.doc_id) as doc_count
    FROM users u
    LEFT JOIN applications a ON u.user_id=a.user_id
    LEFT JOIN organisations o ON a.matched_org_id=o.org_id
    LEFT JOIN student_profiles sp ON u.user_id=sp.user_id
    LEFT JOIN documents d ON u.user_id=d.user_id
    $where
    GROUP BY u.user_id ORDER BY u.created_at DESC
");
$students->execute($params);
$students = $students->fetchAll();

$pageTitle = 'Students';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">👩‍🎓 Students</div>
<div class="page-sub"><?php echo count($students); ?> student(s) registered</div>

<!-- Filters -->
<div style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap;align-items:center">
  <form method="GET" style="display:flex;gap:.5rem;flex:1;max-width:500px">
    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, student number..." style="flex:1;padding:.55rem .85rem;border:1px solid #ddd;border-radius:7px;font-size:.88rem">
    <select name="status" style="padding:.55rem .75rem;border:1px solid #ddd;border-radius:7px;font-size:.88rem">
      <option value="">All statuses</option>
      <?php foreach(['pending','under_review','matched','accepted','rejected'] as $s): ?>
      <option value="<?php echo $s; ?>" <?php echo $status===$s?'selected':''; ?>><?php echo strtoupper(str_replace('_',' ',$s)); ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search||$status): ?><a href="/admin/students.php" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
<table>
  <thead><tr><th>Student</th><th>Student #</th><th>Programme</th><th>Year</th><th>Skills</th><th>Docs</th><th>Application</th><th>Matched To</th><th>Account</th><th>Last Login</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($students as $s): ?>
  <tr>
    <td>
      <strong><?php echo htmlspecialchars($s['full_name']); ?></strong><br>
      <span class="text-muted"><?php echo htmlspecialchars($s['email']); ?></span>
      <?php if ($s['phone']): ?><br><span class="text-muted"><?php echo htmlspecialchars($s['phone']); ?></span><?php endif; ?>
      <?php if ($s['linkedin_url']): ?><br><a href="<?php echo htmlspecialchars($s['linkedin_url']); ?>" target="_blank" style="font-size:.75rem;color:var(--teal)">LinkedIn ↗</a><?php endif; ?>
    </td>
    <td class="text-muted"><?php echo htmlspecialchars($s['student_number']??'—'); ?></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($s['programme']??'—'); ?></td>
    <td style="text-align:center"><?php echo $s['year_of_study']??'—'; ?></td>
    <td style="font-size:.78rem;color:var(--muted);max-width:150px"><?php echo htmlspecialchars(substr($s['skills']??'—',0,60)); ?></td>
    <td style="text-align:center"><?php echo $s['doc_count']; ?></td>
    <td>
      <?php if ($s['app_status']): ?>
        <span class="badge badge-<?php echo str_replace(' ','_',$s['app_status']); ?>"><?php echo strtoupper(str_replace('_',' ',$s['app_status'])); ?></span>
        <?php if($s['app_id']): ?><br><a href="/admin/applications.php?action=view&id=<?php echo $s['app_id']; ?>" style="font-size:.75rem;color:var(--teal)">View →</a><?php endif; ?>
      <?php else: ?><span class="badge" style="background:#f0f0f0;color:#888">NONE</span><?php endif; ?>
    </td>
    <td>
      <?php if ($s['matched_org']): ?>
        <strong style="font-size:.85rem"><?php echo htmlspecialchars($s['matched_org']); ?></strong><br>
        <span class="text-muted"><?php echo htmlspecialchars($s['org_location']??''); ?></span>
      <?php else: ?><span class="text-muted">—</span><?php endif; ?>
    </td>
    <td><span class="badge badge-<?php echo $s['is_active']?'active':'inactive'; ?>"><?php echo $s['is_active']?'ACTIVE':'DISABLED'; ?></span></td>
    <td class="text-muted" style="font-size:.75rem"><?php echo $s['last_login'] ? date('j M y H:i',strtotime($s['last_login'])) : 'Never'; ?></td>
    <td>
      <a href="?toggle=<?php echo $s['user_id']; ?>" class="btn btn-outline btn-sm" onclick="return confirm('Toggle account status?')"><?php echo $s['is_active']?'Disable':'Enable'; ?></a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$students): ?><tr><td colspan="11" style="text-align:center;color:var(--muted);padding:2rem">No students found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>