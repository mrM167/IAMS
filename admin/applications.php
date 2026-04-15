<?php
// admin/applications.php — PHP 7.4 compatible
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    csrf_check();
    $allowed = ['pending', 'under_review', 'matched', 'accepted', 'rejected'];
    $appId   = (int)($_POST['app_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $notes   = trim($_POST['review_notes'] ?? '');

    if ($appId && in_array($status, $allowed)) {
        $aStmt = $db->prepare("SELECT * FROM applications WHERE app_id=?"); $aStmt->execute([$appId]); $appRow = $aStmt->fetch();
        if ($appRow) {
            $db->prepare("UPDATE applications SET status=?,review_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE app_id=?")
               ->execute([$status, $notes, $user['id'], $appId]);

            // PHP 7.4: no match expression
            if ($status === 'under_review') {
                $notifMsg = 'Your application is now under review by the coordinator.';
            } elseif ($status === 'accepted') {
                $notifMsg = 'Congratulations! Your attachment application has been accepted.';
            } elseif ($status === 'rejected') {
                $notifMsg = 'Your attachment application was not successful this round.';
            } elseif ($status === 'matched') {
                $notifMsg = 'You have been matched to an organisation. Check your dashboard.';
            } else {
                $notifMsg = 'Your application status has been updated to: ' . strtoupper($status) . '.';
            }

            $notifType = ($status === 'accepted' || $status === 'matched') ? 'success' : 'info';
            $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
               ->execute([$appRow['user_id'], 'Application Update', $notifMsg, $notifType, '/dashboard.php']);
            $msg = 'Status updated to ' . strtoupper(str_replace('_', ' ', $status)) . '.';
        }
    }
    header('Location: /admin/applications.php?msg=' . urlencode($msg)); exit();
}

if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// Detail view
$viewApp  = null;
$viewDocs = [];
$matchInfo = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'view') {
    $aStmt = $db->prepare("
        SELECT a.*,u.email,u.phone,
               sp.linkedin_url,sp.github_url,sp.portfolio_url,sp.skills as profile_skills,sp.year_of_study,sp.gpa,
               o.org_name as matched_org_name,o.location as matched_org_location,
               ru.full_name as reviewer_name
        FROM applications a
        JOIN users u ON a.user_id=u.user_id
        LEFT JOIN student_profiles sp ON a.user_id=sp.user_id
        LEFT JOIN organisations o ON a.matched_org_id=o.org_id
        LEFT JOIN users ru ON a.reviewed_by=ru.user_id
        WHERE a.app_id=?
    ");
    $aStmt->execute([(int)$_GET['id']]);
    $viewApp = $aStmt->fetch();
    if ($viewApp) {
        $dStmt = $db->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY uploaded_at DESC");
        $dStmt->execute([$viewApp['user_id']]);
        $viewDocs = $dStmt->fetchAll();
        $mStmt = $db->prepare("SELECT m.*,o.org_name,o.location as org_location FROM matches m JOIN organisations o ON m.org_id=o.org_id WHERE m.app_id=? ORDER BY m.created_at DESC LIMIT 1");
        $mStmt->execute([$viewApp['app_id']]);
        $matchInfo = $mStmt->fetch();
    }
}

// List with filters
$filter   = $_GET['filter'] ?? 'all';
$search   = trim($_GET['q'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';

$where  = [];
$params = [];
if ($filter !== 'all') { $where[] = "a.status=?"; $params[] = $filter; }
if ($search) {
    $where[] = "(a.full_name LIKE ? OR u.email LIKE ? OR a.student_number LIKE ? OR a.programme LIKE ?)";
    $like = "%$search%"; $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($dateFrom) { $where[] = "DATE(a.submission_date) >= ?"; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = "DATE(a.submission_date) <= ?"; $params[] = $dateTo; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$apps = $db->prepare("
    SELECT a.*,u.email,u.phone,o.org_name as matched_org
    FROM applications a
    JOIN users u ON a.user_id=u.user_id
    LEFT JOIN organisations o ON a.matched_org_id=o.org_id
    $whereSQL ORDER BY a.submission_date DESC
");
$apps->execute($params);
$applications = $apps->fetchAll();

// Tab counts
$counts = [];
foreach (['all', 'pending', 'under_review', 'matched', 'accepted', 'rejected'] as $s) {
    if ($s === 'all') {
        $counts[$s] = (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn();
    } else {
        $cStmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE status=?"); $cStmt->execute([$s]);
        $counts[$s] = (int)$cStmt->fetchColumn();
    }
}

$pageTitle = 'Applications';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">

<?php if ($msg): ?><div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<?php if ($viewApp): ?>
<!-- DETAIL VIEW -->
<div style="margin-bottom:1rem;display:flex;gap:.75rem;align-items:center">
  <a href="/admin/applications.php" class="btn btn-outline">&larr; Back to list</a>
  <span class="badge badge-<?php echo str_replace(' ', '_', $viewApp['status']); ?>"><?php echo strtoupper(str_replace('_', ' ', $viewApp['status'])); ?></span>
</div>
<div class="page-title">Application #<?php echo $viewApp['app_id']; ?> &mdash; <?php echo htmlspecialchars($viewApp['full_name']); ?></div>
<div class="page-sub">Submitted <?php echo date('j F Y \a\t H:i', strtotime($viewApp['submission_date'])); ?></div>

<div class="grid-2" style="margin-bottom:1.5rem;align-items:start">
  <div class="card">
    <div class="card-header"><h3>Student Details</h3></div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <?php
        $fields = [
          'Full Name'         => $viewApp['full_name'],
          'Student Number'    => $viewApp['student_number'],
          'Email'             => $viewApp['email'],
          'Phone'             => ($viewApp['phone'] ?? '—'),
          'Programme'         => $viewApp['programme'],
          'Year of Study'     => ($viewApp['year_of_study'] ?? '—'),
          'GPA'               => ($viewApp['gpa'] ?? '—'),
          'Preferred Location'=> ($viewApp['preferred_location'] ?? '—'),
        ];
        foreach ($fields as $label => $val):
        ?>
        <div>
          <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--muted)"><?php echo $label; ?></span><br>
          <span style="font-size:.9rem"><?php echo htmlspecialchars((string)$val); ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($viewApp['linkedin_url'] || $viewApp['github_url'] || $viewApp['portfolio_url']): ?>
      <div style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap">
        <?php if ($viewApp['linkedin_url']): ?><a href="<?php echo htmlspecialchars($viewApp['linkedin_url']); ?>" target="_blank" class="btn btn-outline btn-sm">LinkedIn &nearr;</a><?php endif; ?>
        <?php if ($viewApp['github_url']): ?><a href="<?php echo htmlspecialchars($viewApp['github_url']); ?>" target="_blank" class="btn btn-outline btn-sm">GitHub &nearr;</a><?php endif; ?>
        <?php if ($viewApp['portfolio_url']): ?><a href="<?php echo htmlspecialchars($viewApp['portfolio_url']); ?>" target="_blank" class="btn btn-outline btn-sm">Portfolio &nearr;</a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>Application Details</h3></div>
    <div class="card-body">
      <div style="margin-bottom:.75rem">
        <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--muted)">Skills</span><br>
        <span style="font-size:.88rem"><?php echo htmlspecialchars($viewApp['skills'] ?? 'Not specified'); ?></span>
      </div>
      <?php if ($viewApp['cover_letter']): ?>
      <div style="margin-bottom:.75rem">
        <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;color:var(--muted)">Cover Letter</span>
        <div style="background:#f8f9fb;border-radius:7px;padding:.75rem;font-size:.85rem;line-height:1.6;margin-top:.25rem"><?php echo nl2br(htmlspecialchars($viewApp['cover_letter'])); ?></div>
      </div>
      <?php endif; ?>
      <?php if ($matchInfo): ?>
      <div style="background:#d4edda;border-radius:7px;padding:.75rem;margin-top:.5rem">
        <strong style="color:#155724">&#10003; Matched to: <?php echo htmlspecialchars($matchInfo['org_name']); ?></strong><br>
        <span style="font-size:.82rem;color:#155724">Score: <?php echo number_format($matchInfo['match_score'], 1); ?>% &middot; <?php echo htmlspecialchars($matchInfo['org_location']); ?></span>
      </div>
      <?php endif; ?>
      <?php if ($viewApp['review_notes']): ?>
      <div style="margin-top:.75rem;background:#fff3cd;border-radius:7px;padding:.75rem">
        <strong style="font-size:.82rem">Coordinator Note:</strong> <?php echo htmlspecialchars($viewApp['review_notes']); ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><h3>Documents (<?php echo count($viewDocs); ?>)</h3></div>
  <?php if ($viewDocs): ?>
  <table><thead><tr><th>Type</th><th>Filename</th><th>Size</th><th>Uploaded</th><th></th></tr></thead><tbody>
  <?php foreach ($viewDocs as $d): ?>
  <tr>
    <td><span style="background:#e8f0fe;color:#1a3a6a;padding:.2rem .55rem;border-radius:4px;font-size:.75rem;font-weight:700"><?php echo htmlspecialchars($d['doc_type']); ?></span></td>
    <td style="font-size:.85rem"><?php echo htmlspecialchars($d['filename']); ?></td>
    <td class="text-muted"><?php echo $d['file_size'] ? round($d['file_size'] / 1024) . 'KB' : '—'; ?></td>
    <td class="text-muted" style="font-size:.8rem"><?php echo date('j M Y H:i', strtotime($d['uploaded_at'])); ?></td>
    <td><a href="/download.php?id=<?php echo $d['doc_id']; ?>" class="btn btn-primary btn-sm" target="_blank">Download</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><div class="card-body"><p class="text-muted">No documents uploaded.</p></div><?php endif; ?>
</div>

<div class="card" style="max-width:580px">
  <div class="card-header"><h3>Update Status</h3></div>
  <div class="card-body">
  <form method="POST">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="update_status" value="1">
    <input type="hidden" name="app_id" value="<?php echo $viewApp['app_id']; ?>">
    <div class="form-group">
      <label>Status</label>
      <select name="status">
        <?php foreach (['pending', 'under_review', 'matched', 'accepted', 'rejected'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php echo $viewApp['status'] === $s ? 'selected' : ''; ?>><?php echo strtoupper(str_replace('_', ' ', $s)); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Notes for Student</label>
      <textarea name="review_notes" rows="4" placeholder="Optional feedback or placement details..."><?php echo htmlspecialchars($viewApp['review_notes'] ?? ''); ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
  </div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<div class="page-title">Applications</div>
<div class="page-sub">Review and manage student attachment applications</div>

<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.25rem">
  <?php
  $filterLabels = ['all'=>'All','pending'=>'Pending','under_review'=>'Under Review','matched'=>'Matched','accepted'=>'Accepted','rejected'=>'Rejected'];
  foreach ($filterLabels as $f => $label):
  ?>
  <a href="?filter=<?php echo $f; ?>&q=<?php echo urlencode($search); ?>"
     style="padding:.4rem .85rem;border-radius:6px;text-decoration:none;font-size:.8rem;font-weight:600;<?php echo $filter===$f?'background:var(--navy);color:#fff':'background:#fff;color:var(--muted);border:1px solid #ddd'; ?>">
    <?php echo $label; ?> (<?php echo $counts[$f]; ?>)
  </a>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, email, student #, programme..." style="padding:.55rem .85rem;border:1px solid #ddd;border-radius:7px;font-size:.85rem;min-width:260px">
    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" style="padding:.55rem .75rem;border:1px solid #ddd;border-radius:7px;font-size:.85rem">
    <input type="date" name="date_to"   value="<?php echo htmlspecialchars($dateTo); ?>"   style="padding:.55rem .75rem;border:1px solid #ddd;border-radius:7px;font-size:.85rem">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search || $dateFrom || $dateTo): ?><a href="/admin/applications.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
<table>
  <thead><tr><th>Student</th><th>Programme</th><th>Skills</th><th>Preferred Location</th><th>Matched To</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($applications as $app): ?>
  <tr>
    <td>
      <strong><?php echo htmlspecialchars($app['full_name']); ?></strong><br>
      <span class="text-muted"><?php echo htmlspecialchars($app['email']); ?></span>
    </td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($app['programme']); ?></td>
    <td style="font-size:.78rem;color:var(--muted)"><?php echo htmlspecialchars(substr($app['skills'] ?? '—', 0, 50)); ?></td>
    <td class="text-muted"><?php echo htmlspecialchars($app['preferred_location'] ?? 'Any'); ?></td>
    <td><?php echo $app['matched_org'] ? '<strong style="font-size:.85rem">' . htmlspecialchars($app['matched_org']) . '</strong>' : '<span class="text-muted">—</span>'; ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo date('j M Y', strtotime($app['submission_date'])); ?></td>
    <td><span class="badge badge-<?php echo str_replace(' ', '_', $app['status']); ?>"><?php echo strtoupper(str_replace('_', ' ', $app['status'])); ?></span></td>
    <td><a href="?action=view&id=<?php echo $app['app_id']; ?>" class="btn btn-primary btn-sm">Review</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$applications): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No applications found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS &copy; <?php echo date('Y'); ?> &mdash; University of Botswana</footer>
</body></html>
