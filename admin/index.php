<?php
// admin/index.php — Coordinator/Admin Dashboard (US-05)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();

$stats = [];
foreach ([
    'total_students'   => "SELECT COUNT(*) FROM users WHERE role='student'",
    'total_orgs'       => "SELECT COUNT(*) FROM organisations WHERE is_active=1",
    'total_apps'       => "SELECT COUNT(*) FROM applications",
    'pending'          => "SELECT COUNT(*) FROM applications WHERE status='pending'",
    'under_review'     => "SELECT COUNT(*) FROM applications WHERE status='under_review'",
    'matched'          => "SELECT COUNT(*) FROM applications WHERE status IN('matched','accepted')",
    'rejected'         => "SELECT COUNT(*) FROM applications WHERE status='rejected'",
    'active_jobs'      => "SELECT COUNT(*) FROM job_posts WHERE is_active=1",
    'total_docs'       => "SELECT COUNT(*) FROM documents",
    'total_matches'    => "SELECT COUNT(*) FROM matches WHERE status='confirmed'",
] as $k => $sql) {
    $stats[$k] = (int)$db->query($sql)->fetchColumn();
}

$recent_apps = $db->query("
    SELECT a.app_id,a.full_name,a.programme,a.status,a.submission_date,u.email,u.phone
    FROM applications a JOIN users u ON a.user_id=u.user_id
    ORDER BY a.submission_date DESC LIMIT 8
")->fetchAll();

$recent_orgs = $db->query("
    SELECT o.*,u.email,u.last_login FROM organisations o JOIN users u ON o.user_id=u.user_id
    WHERE o.is_active=1 ORDER BY o.created_at DESC LIMIT 5
")->fetchAll();

$pageTitle = 'Admin Dashboard';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-sub">Welcome back, <?php echo htmlspecialchars($user['name']); ?> — <?php echo date('l, j F Y'); ?></div>
  </div>
  <div style="display:flex;gap:.75rem">
    <a href="/admin/matching.php" class="btn btn-gold">🤖 Run Matching</a>
    <a href="/admin/applications.php" class="btn btn-primary">Review Applications</a>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-num"><?php echo $stats['total_students']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Organisations</div><div class="stat-num"><?php echo $stats['total_orgs']; ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Pending Review</div><div class="stat-num"><?php echo $stats['pending']; ?></div></div>
  <div class="stat-card"><div class="stat-label">Under Review</div><div class="stat-num"><?php echo $stats['under_review']; ?></div></div>
  <div class="stat-card green"><div class="stat-label">Matched / Accepted</div><div class="stat-num"><?php echo $stats['matched']; ?></div></div>
  <div class="stat-card red"><div class="stat-label">Rejected</div><div class="stat-num"><?php echo $stats['rejected']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Active Jobs</div><div class="stat-num"><?php echo $stats['active_jobs']; ?></div></div>
  <div class="stat-card"><div class="stat-label">Documents</div><div class="stat-num"><?php echo $stats['total_docs']; ?></div></div>
  <div class="stat-card green"><div class="stat-label">Confirmed Matches</div><div class="stat-num"><?php echo $stats['total_matches']; ?></div></div>
</div>

<div class="grid-2">
<!-- Recent Applications -->
<div class="card">
  <div class="card-header"><h3>Recent Applications</h3><a href="/admin/applications.php" style="font-size:.8rem;color:var(--teal)">View all →</a></div>
  <table><thead><tr><th>Student</th><th>Programme</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($recent_apps as $app): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($app['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($app['email']); ?></span></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars(substr($app['programme'],0,28)); ?></td>
    <td><span class="badge badge-<?php echo str_replace(' ','_',$app['status']); ?>"><?php echo strtoupper(str_replace('_',' ',$app['status'])); ?></span></td>
    <td><a href="/admin/applications.php?action=view&id=<?php echo $app['app_id']; ?>" class="btn btn-primary btn-sm">View</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$recent_apps): ?><tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">No applications yet.</td></tr><?php endif; ?>
  </tbody></table>
</div>

<!-- Recent Organisations -->
<div class="card">
  <div class="card-header"><h3>Registered Organisations</h3><a href="/admin/organisations.php" style="font-size:.8rem;color:var(--teal)">View all →</a></div>
  <table><thead><tr><th>Organisation</th><th>Industry</th><th>Capacity</th><th>Location</th></tr></thead><tbody>
  <?php foreach ($recent_orgs as $org): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($org['org_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($org['email']); ?></span></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($org['industry']??'—'); ?></td>
    <td style="text-align:center"><?php echo $org['capacity']; ?></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($org['location']??'—'); ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$recent_orgs): ?><tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem">No organisations yet.</td></tr><?php endif; ?>
  </tbody></table>
</div>
</div>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana · <?php echo ucfirst($user['role']); ?></footer>
</body></html>
