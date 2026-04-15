<?php
// logbook.php — Student weekly logbook submission (US-09)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();
requireRole('student');

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';

// Student must have an accepted/matched application to submit logbooks
$appStmt = $db->prepare("SELECT * FROM applications WHERE user_id=? AND status IN ('matched','accepted')");
$appStmt->execute([$user['id']]);
$application = $appStmt->fetch();

// Load existing logbooks
$logbooks = $db->prepare("SELECT * FROM logbooks WHERE user_id=? ORDER BY week_number ASC");
$logbooks->execute([$user['id']]);
$logbooks = $logbooks->fetchAll();
$submittedWeeks = array_column($logbooks, 'week_number');

// Load / edit specific logbook
$editLogbook = null;
if (isset($_GET['edit'])) {
    $elStmt = $db->prepare("SELECT * FROM logbooks WHERE logbook_id=? AND user_id=?");
    $elStmt->execute([(int)$_GET['edit'], $user['id']]);
    $editLogbook = $elStmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if (!$application) { $err = 'You must have a confirmed attachment placement to submit logbooks.'; }
    else {
        $weekNum   = (int)($_POST['week_number'] ?? 0);
        $weekStart = trim($_POST['week_start_date'] ?? '');
        $activities= trim($_POST['activities'] ?? '');
        $learning  = trim($_POST['learning_outcomes'] ?? '');
        $challenges= trim($_POST['challenges'] ?? '');

        if ($weekNum < 1 || $weekNum > 52) { $err = 'Week number must be between 1 and 52.'; }
        elseif (!$weekStart)               { $err = 'Week start date is required.'; }
        elseif (!$activities)              { $err = 'Activities section cannot be empty.'; }
        else {
            $status    = ($action === 'submit') ? 'submitted' : 'draft';
            $submittedAt = ($action === 'submit') ? date('Y-m-d H:i:s') : null;
            $logbookId = (int)($_POST['logbook_id'] ?? 0);

            // Check if deadline passed (logbooks due Sunday of each week)
            $weekStartTs   = strtotime($weekStart);
            $weekEndSunday = strtotime('next Sunday', $weekStartTs);
            $isLate        = ($action === 'submit' && time() > $weekEndSunday + 86400 * 3); // 3-day grace
            if ($isLate) $status = 'late';

            try {
                if ($logbookId) {
                    // Update existing
                    $existStmt = $db->prepare("SELECT * FROM logbooks WHERE logbook_id=? AND user_id=?");
                    $existStmt->execute([$logbookId, $user['id']]);
                    $existing = $existStmt->fetch();
                    if ($existing && $existing['status'] !== 'reviewed') {
                        $db->prepare("UPDATE logbooks SET week_number=?,week_start_date=?,activities=?,learning_outcomes=?,challenges=?,status=?,submitted_at=?,updated_at=NOW() WHERE logbook_id=? AND user_id=?")
                           ->execute([$weekNum,$weekStart,$activities,$learning,$challenges,$status,$submittedAt,$logbookId,$user['id']]);
                        $msg = $action==='submit' ? 'Logbook submitted for Week '.$weekNum.'!' : 'Draft saved for Week '.$weekNum.'.';
                    } else { $err = 'Cannot edit a reviewed logbook.'; }
                } else {
                    // Insert new — handle duplicate week
                    $dupStmt = $db->prepare("SELECT logbook_id FROM logbooks WHERE user_id=? AND week_number=?");
                    $dupStmt->execute([$user['id'], $weekNum]);
                    if ($dupStmt->fetch()) { $err = 'You already have a logbook entry for Week '.$weekNum.'. Edit the existing one.'; }
                    else {
                        $db->prepare("INSERT INTO logbooks (user_id,app_id,week_number,week_start_date,activities,learning_outcomes,challenges,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?)")
                           ->execute([$user['id'],$application['app_id'],$weekNum,$weekStart,$activities,$learning,$challenges,$status,$submittedAt]);
                        // Notify coordinator when submitted
                        if ($action === 'submit') {
                            $coords = $db->query("SELECT user_id FROM users WHERE role IN ('admin','coordinator')")->fetchAll();
                            foreach ($coords as $c) {
                                $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
                                   ->execute([$c['user_id'],'Logbook Submitted',$user['name'].' submitted Week '.$weekNum.' logbook.','info','/admin/logbooks.php']);
                            }
                        }
                        $msg = $action==='submit' ? '✅ Logbook submitted for Week '.$weekNum.'!' : '💾 Draft saved for Week '.$weekNum.'.';
                        $editLogbook = null;
                    }
                }
            } catch (Exception $e) { $err = 'Save failed: '.$e->getMessage(); }

            if (!$err) {
                header('Location: /logbook.php?msg=' . urlencode($msg)); exit();
            }
        }
    }
}
if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// Determine next week number to suggest
$nextWeek = max(1, count($submittedWeeks) + 1);
$nextWeekStart = date('Y-m-d', strtotime('monday this week'));

$statusColors = ['draft'=>'badge-pending','submitted'=>'badge-under_review','reviewed'=>'badge-accepted','late'=>'badge-rejected'];
$pageTitle = 'Weekly Logbook';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">📒 Weekly Logbook</div>
<div class="page-sub">Record your weekly activities during industrial attachment</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err):  ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<?php if (!$application): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2.5rem">
  <p style="font-size:1.05rem;color:var(--muted)">You need a confirmed attachment placement before submitting logbooks.</p>
  <a href="/dashboard.php?tab=apply" class="btn btn-primary" style="margin-top:1rem">Apply for Attachment →</a>
</div></div>
<?php else: ?>
<div class="grid-2" style="align-items:start">

<!-- Logbook Form -->
<div class="card">
  <div class="card-header"><h3><?php echo $editLogbook ? '✏️ Edit Week '.$editLogbook['week_number'].' Logbook' : '➕ New Logbook Entry'; ?></h3></div>
  <div class="card-body">
  <form method="POST">
    <?php echo csrf_field(); ?>
    <?php if ($editLogbook): ?><input type="hidden" name="logbook_id" value="<?php echo $editLogbook['logbook_id']; ?>"><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
      <div class="form-group">
        <label>Week Number * (1–26)</label>
        <input type="number" name="week_number" min="1" max="52" required value="<?php echo htmlspecialchars((string)($editLogbook['week_number'] ?? $nextWeek)); ?>">
      </div>
      <div class="form-group">
        <label>Week Start Date (Monday) *</label>
        <input type="date" name="week_start_date" required value="<?php echo htmlspecialchars($editLogbook['week_start_date'] ?? $nextWeekStart); ?>">
      </div>
    </div>

    <div class="form-group">
      <label>Activities Completed This Week *</label>
      <textarea name="activities" rows="6" required placeholder="Describe in detail what tasks and activities you performed this week..."><?php echo htmlspecialchars($editLogbook['activities'] ?? ''); ?></textarea>
    </div>
    <div class="form-group">
      <label>Learning Outcomes</label>
      <textarea name="learning_outcomes" rows="4" placeholder="What new skills or knowledge did you gain this week?"><?php echo htmlspecialchars($editLogbook['learning_outcomes'] ?? ''); ?></textarea>
    </div>
    <div class="form-group">
      <label>Challenges Faced</label>
      <textarea name="challenges" rows="3" placeholder="Any difficulties or challenges encountered this week?"><?php echo htmlspecialchars($editLogbook['challenges'] ?? ''); ?></textarea>
    </div>

    <?php if ($editLogbook && $editLogbook['supervisor_comment']): ?>
    <div style="background:#d1ecf1;border-radius:7px;padding:.85rem;margin-bottom:1rem">
      <strong style="font-size:.8rem;color:#0c5460">💬 Supervisor Comment:</strong><br>
      <span style="font-size:.88rem;color:#0c5460"><?php echo nl2br(htmlspecialchars($editLogbook['supervisor_comment'])); ?></span>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:.75rem;margin-top:.5rem">
      <button type="submit" name="action" value="save" class="btn btn-outline" style="flex:1">💾 Save Draft</button>
      <button type="submit" name="action" value="submit" class="btn btn-primary" style="flex:1">📤 Submit Logbook</button>
    </div>
    <?php if ($editLogbook): ?><a href="/logbook.php" style="display:block;text-align:center;margin-top:.75rem;color:var(--muted);font-size:.85rem">Cancel</a><?php endif; ?>
  </form>
  </div>
</div>

<!-- Logbook History -->
<div class="card">
  <div class="card-header"><h3>📋 Logbook History (<?php echo count($logbooks); ?> entries)</h3></div>
  <?php if ($logbooks): ?>
  <table><thead><tr><th>Week</th><th>Start Date</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody>
  <?php foreach ($logbooks as $lb): ?>
  <tr>
    <td><strong>Week <?php echo $lb['week_number']; ?></strong></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo date('j M Y',strtotime($lb['week_start_date'])); ?></td>
    <td><span class="badge <?php echo $statusColors[$lb['status']]??'badge-pending'; ?>"><?php echo strtoupper($lb['status']); ?></span></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $lb['submitted_at'] ? date('j M Y',strtotime($lb['submitted_at'])) : '—'; ?></td>
    <td>
      <?php if ($lb['status'] !== 'reviewed'): ?>
      <a href="?edit=<?php echo $lb['logbook_id']; ?>" class="btn btn-gold btn-sm">Edit</a>
      <?php else: ?>
      <a href="?edit=<?php echo $lb['logbook_id']; ?>" class="btn btn-outline btn-sm">View</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?>
  <div class="card-body"><p class="text-muted">No logbook entries yet. Start with Week 1.</p></div>
  <?php endif; ?>

  <!-- Progress bar -->
  <?php if ($logbooks): ?>
  <?php $submitted = count(array_filter($logbooks, fn($l)=>in_array($l['status'],['submitted','reviewed','late']))); ?>
  <div style="padding:1rem 1.25rem;border-top:1px solid #eee">
    <div style="font-size:.78rem;font-weight:600;color:var(--muted);margin-bottom:.4rem">Submission Progress (<?php echo $submitted; ?>/<?php echo count($logbooks); ?> submitted)</div>
    <div style="background:#e5e7eb;border-radius:4px;height:8px"><div style="background:var(--green);height:8px;border-radius:4px;width:<?php echo count($logbooks)?round($submitted/count($logbooks)*100):0; ?>%"></div></div>
  </div>
  <?php endif; ?>
</div>

</div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
