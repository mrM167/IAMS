<?php
// admin/logbooks.php — Coordinator logbook review (US-10)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = '';

// Add supervisor comment / mark reviewed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $lbId    = (int)($_POST['logbook_id'] ?? 0);
    $comment = trim($_POST['supervisor_comment'] ?? '');
    $status  = $_POST['status'] ?? 'reviewed';
    if ($lbId && in_array($status, ['submitted','reviewed','late'])) {
        $db->prepare("UPDATE logbooks SET supervisor_comment=?,status=?,reviewed_at=NOW(),reviewed_by=? WHERE logbook_id=?")
           ->execute([$comment, $status, $user['id'], $lbId]);
        // Notify student
        $lbStmt = $db->prepare("SELECT * FROM logbooks WHERE logbook_id=?"); $lbStmt->execute([$lbId]); $lb = $lbStmt->fetch();
        if ($lb) {
            $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
               ->execute([$lb['user_id'],'Logbook Reviewed','Your Week '.$lb['week_number'].' logbook has been reviewed.','success','/logbook.php']);
        }
        $msg = 'Logbook reviewed and feedback saved.';
    }
    header('Location: /admin/logbooks.php?msg=' . urlencode($msg)); exit();
}
if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// Filters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($filter !== 'all') { $where[] = "lb.status=?"; $params[] = $filter; }
if ($search) {
    $where[] = "(u.full_name LIKE ? OR u.student_number LIKE ?)";
    $like = "%$search%"; $params = array_merge($params, [$like,$like]);
}
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$logbooks = $db->prepare("
    SELECT lb.*,u.full_name,u.student_number,u.email,u.programme,
           o.org_name as placement_org
    FROM logbooks lb
    JOIN users u ON lb.user_id=u.user_id
    LEFT JOIN applications a ON lb.app_id=a.app_id
    LEFT JOIN organisations o ON a.matched_org_id=o.org_id
    $whereSQL
    ORDER BY lb.submitted_at DESC, lb.week_number ASC
");
$logbooks->execute($params);
$logbooks = $logbooks->fetchAll();

// Counts
$counts = [];
foreach(['all','draft','submitted','reviewed','late'] as $s) {
    $cStmt = $s==='all' ? $db->query("SELECT COUNT(*) FROM logbooks") : $db->prepare("SELECT COUNT(*) FROM logbooks WHERE status=?");
    if ($s!=='all') $cStmt->execute([$s]);
    $counts[$s] = (int)$cStmt->fetchColumn();
}

// Detail view
$viewLb = null;
if (isset($_GET['view'])) {
    $vStmt = $db->prepare("SELECT lb.*,u.full_name,u.student_number,u.email,u.programme,o.org_name FROM logbooks lb JOIN users u ON lb.user_id=u.user_id LEFT JOIN applications a ON lb.app_id=a.app_id LEFT JOIN organisations o ON a.matched_org_id=o.org_id WHERE lb.logbook_id=?");
    $vStmt->execute([(int)$_GET['view']]);
    $viewLb = $vStmt->fetch();
}

$statusColors = ['draft'=>'badge-pending','submitted'=>'badge-under_review','reviewed'=>'badge-accepted','late'=>'badge-rejected'];
$pageTitle = 'Logbook Review';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<?php if ($msg): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<?php if ($viewLb): ?>
<!-- DETAIL VIEW -->
<div style="margin-bottom:1rem"><a href="/admin/logbooks.php" class="btn btn-outline">← Back to list</a></div>
<div class="page-title">📒 <?php echo htmlspecialchars($viewLb['full_name']); ?> — Week <?php echo $viewLb['week_number']; ?></div>
<div class="page-sub"><?php echo htmlspecialchars($viewLb['programme']??''); ?> · <?php echo htmlspecialchars($viewLb['org_name']??''); ?></div>

<div class="grid-2" style="align-items:start">
<div>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><h3>Week <?php echo $viewLb['week_number']; ?> Entry — <?php echo date('j M Y',strtotime($viewLb['week_start_date'])); ?></h3>
    <span class="badge <?php echo $statusColors[$viewLb['status']]??'badge-pending'; ?>"><?php echo strtoupper($viewLb['status']); ?></span></div>
    <div class="card-body">
      <div style="margin-bottom:1rem">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem">Activities</div>
        <div style="background:#f8f9fb;padding:.85rem;border-radius:7px;font-size:.9rem;line-height:1.7"><?php echo nl2br(htmlspecialchars($viewLb['activities'])); ?></div>
      </div>
      <?php if ($viewLb['learning_outcomes']): ?>
      <div style="margin-bottom:1rem">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem">Learning Outcomes</div>
        <div style="background:#f8f9fb;padding:.85rem;border-radius:7px;font-size:.9rem;line-height:1.7"><?php echo nl2br(htmlspecialchars($viewLb['learning_outcomes'])); ?></div>
      </div>
      <?php endif; ?>
      <?php if ($viewLb['challenges']): ?>
      <div>
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem">Challenges</div>
        <div style="background:#f8f9fb;padding:.85rem;border-radius:7px;font-size:.9rem;line-height:1.7"><?php echo nl2br(htmlspecialchars($viewLb['challenges'])); ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<div>
  <div class="card">
    <div class="card-header"><h3>💬 Review & Comment</h3></div>
    <div class="card-body">
      <?php if ($viewLb['supervisor_comment']): ?>
      <div style="background:#d1ecf1;padding:.85rem;border-radius:7px;margin-bottom:1rem">
        <strong style="font-size:.8rem;color:#0c5460">Previous Comment:</strong><br>
        <span style="font-size:.88rem;color:#0c5460"><?php echo nl2br(htmlspecialchars($viewLb['supervisor_comment'])); ?></span>
      </div>
      <?php endif; ?>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="logbook_id" value="<?php echo $viewLb['logbook_id']; ?>">
        <div class="form-group">
          <label>Supervisor / Reviewer Comment</label>
          <textarea name="supervisor_comment" rows="5" placeholder="Provide feedback on the student's activities and progress..."><?php echo htmlspecialchars($viewLb['supervisor_comment']??''); ?></textarea>
        </div>
        <div class="form-group">
          <label>Update Status</label>
          <select name="status">
            <?php foreach(['submitted'=>'Submitted','reviewed'=>'Reviewed / Approved','late'=>'Late Submission'] as $s=>$l): ?>
            <option value="<?php echo $s; ?>" <?php echo $viewLb['status']===$s?'selected':''; ?>><?php echo $l; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Save Review</button>
      </form>
    </div>
  </div>
</div>
</div>

<?php else: ?>
<!-- LIST VIEW -->
<div class="page-title">📒 Logbook Review</div>
<div class="page-sub">Review and comment on student weekly logbooks</div>

<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.25rem">
  <?php foreach(['all'=>'All','submitted'=>'Submitted','reviewed'=>'Reviewed','draft'=>'Draft','late'=>'Late'] as $f=>$l): ?>
  <a href="?filter=<?php echo $f; ?>" style="padding:.4rem .85rem;border-radius:6px;text-decoration:none;font-size:.8rem;font-weight:600;<?php echo $filter===$f?'background:var(--navy);color:#fff':'background:#fff;color:var(--muted);border:1px solid #ddd'; ?>"><?php echo $l; ?> (<?php echo $counts[$f]??0; ?>)</a>
  <?php endforeach; ?>
</div>

<div style="margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.5rem">
    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student name or number..." style="padding:.55rem .85rem;border:1px solid #ddd;border-radius:7px;font-size:.85rem;min-width:280px">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search): ?><a href="/admin/logbooks.php?filter=<?php echo htmlspecialchars($filter); ?>" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
<table>
  <thead><tr><th>Student</th><th>Organisation</th><th>Week</th><th>Week Start</th><th>Status</th><th>Submitted</th><th>Reviewed</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($logbooks as $lb): ?>
  <tr style="<?php echo $lb['status']==='submitted'?'background:#fffbec':''; ?>">
    <td><strong><?php echo htmlspecialchars($lb['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($lb['student_number']??''); ?></span></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($lb['org_name']??'—'); ?></td>
    <td style="font-weight:700;text-align:center">Week <?php echo $lb['week_number']; ?></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo date('j M Y',strtotime($lb['week_start_date'])); ?></td>
    <td><span class="badge <?php echo $statusColors[$lb['status']]??'badge-pending'; ?>"><?php echo strtoupper($lb['status']); ?></span></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $lb['submitted_at']?date('j M Y H:i',strtotime($lb['submitted_at'])):'—'; ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $lb['reviewed_at']?date('j M Y',strtotime($lb['reviewed_at'])):'—'; ?></td>
    <td><a href="?view=<?php echo $lb['logbook_id']; ?>" class="btn btn-primary btn-sm">Review</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$logbooks): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No logbooks found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
