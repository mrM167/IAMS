<?php
// supervisor_report.php — Industrial supervisor performance report (US-12)
// Accessible by organisation users for their matched students
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();
requireRole('organisation');

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';

// Get org record
$orgStmt = $db->prepare("SELECT * FROM organisations WHERE user_id=?");
$orgStmt->execute([$user['id']]);
$org = $orgStmt->fetch();
if (!$org) { header('Location: /logout.php'); exit(); }

// Get matched students
$studentsStmt = $db->prepare("
    SELECT u.user_id,u.full_name,u.email,u.student_number,u.programme,
           a.app_id,a.status as app_status,
           sr.sup_report_id,sr.status as report_status,sr.submitted_at as report_submitted
    FROM matches m
    JOIN users u ON m.user_id=u.user_id
    JOIN applications a ON m.app_id=a.app_id
    LEFT JOIN supervisor_reports sr ON sr.student_user_id=u.user_id AND sr.supervisor_user_id=?
    WHERE m.org_id=? AND m.status='confirmed'
    ORDER BY u.full_name
");
$studentsStmt->execute([$user['id'], $org['org_id']]);
$students = $studentsStmt->fetchAll();

// Load existing report for selected student
$selectedStudent = null;
$existingReport  = null;
if (isset($_GET['student'])) {
    foreach ($students as $s) {
        if ($s['user_id'] == (int)$_GET['student']) { $selectedStudent = $s; break; }
    }
    if ($selectedStudent) {
        $repStmt = $db->prepare("SELECT * FROM supervisor_reports WHERE student_user_id=? AND supervisor_user_id=? LIMIT 1");
        $repStmt->execute([$selectedStudent['user_id'], $user['id']]);
        $existingReport = $repStmt->fetch();
    }
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $studentId = (int)($_POST['student_user_id'] ?? 0);
    $appId     = (int)($_POST['app_id'] ?? 0);
    $action    = $_POST['action'] ?? 'save';

    // Verify this student belongs to this org
    $verifyStmt = $db->prepare("SELECT m.match_id FROM matches m WHERE m.user_id=? AND m.org_id=? AND m.status='confirmed'");
    $verifyStmt->execute([$studentId, $org['org_id']]);
    if (!$verifyStmt->fetch()) { $err = 'Invalid student selection.'; }
    else {
        $data = [
            'performance_rating' => min(5, max(1, (int)($_POST['performance_rating']??3))),
            'punctuality'        => min(5, max(1, (int)($_POST['punctuality']??3))),
            'communication'      => min(5, max(1, (int)($_POST['communication']??3))),
            'technical_skills'   => min(5, max(1, (int)($_POST['technical_skills']??3))),
            'teamwork'           => min(5, max(1, (int)($_POST['teamwork']??3))),
            'comments'           => trim($_POST['comments']??''),
            'recommendation'     => $_POST['recommendation']??'recommend',
        ];
        $status      = ($action === 'submit') ? 'submitted' : 'draft';
        $submittedAt = ($action === 'submit') ? date('Y-m-d H:i:s') : null;

        $existCheckStmt = $db->prepare("SELECT sup_report_id,status FROM supervisor_reports WHERE student_user_id=? AND supervisor_user_id=?");
        $existCheckStmt->execute([$studentId, $user['id']]);
        $existing = $existCheckStmt->fetch();

        if ($existing && $existing['status'] === 'submitted') {
            $err = 'You have already submitted a report for this student.';
        } else {
            if ($existing) {
                $db->prepare("UPDATE supervisor_reports SET performance_rating=?,punctuality=?,communication=?,technical_skills=?,teamwork=?,comments=?,recommendation=?,status=?,submitted_at=? WHERE sup_report_id=?")
                   ->execute([...(array_values($data)), $status, $submittedAt, $existing['sup_report_id']]);
            } else {
                $db->prepare("INSERT INTO supervisor_reports (student_user_id,app_id,supervisor_user_id,performance_rating,punctuality,communication,technical_skills,teamwork,comments,recommendation,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$studentId,$appId,$user['id'],...array_values($data),$status,$submittedAt]);
            }
            if ($action === 'submit') {
                // Notify student and coordinators
                $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
                   ->execute([$studentId,'Supervisor Report Submitted','Your industrial supervisor has submitted your end-of-attachment performance report.','success','/student_report.php']);
                $coords = $db->query("SELECT user_id FROM users WHERE role IN ('admin','coordinator')")->fetchAll();
                foreach ($coords as $c) {
                    $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
                       ->execute([$c['user_id'],'Supervisor Report','Industrial supervisor report submitted for a student.','info','/admin/reports.php?tab=supervisor_reports']);
                }
                $msg = '✅ Performance report submitted!';
            } else { $msg = '💾 Draft saved.'; }
            header('Location: /supervisor_report.php?student='.$studentId.'&msg='.urlencode($msg)); exit();
        }
    }
}
if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

$ratingLabels = [1=>'Poor',2=>'Below Average',3=>'Average',4=>'Good',5=>'Excellent'];
$recOptions = ['highly_recommend'=>'Highly Recommend','recommend'=>'Recommend','neutral'=>'Neutral','not_recommend'=>'Do Not Recommend'];
$pageTitle = 'Supervisor Report';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">📊 Industrial Supervisor Report</div>
<div class="page-sub">Submit end-of-attachment performance assessments for your matched students</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err):  ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<div class="grid-2" style="align-items:start">
<!-- Student List -->
<div class="card">
  <div class="card-header"><h3>Your Matched Students (<?php echo count($students); ?>)</h3></div>
  <?php if ($students): ?>
  <table><thead><tr><th>Student</th><th>Programme</th><th>Report Status</th><th></th></tr></thead><tbody>
  <?php foreach ($students as $s): ?>
  <tr style="<?php echo ($selectedStudent && $selectedStudent['user_id']===$s['user_id'])?'background:#fffbec':''; ?>">
    <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($s['student_number']??''); ?></span></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($s['programme']??''); ?></td>
    <td>
      <?php if ($s['report_status'] === 'submitted'): ?>
        <span class="badge badge-accepted">SUBMITTED</span>
      <?php elseif ($s['report_status'] === 'draft'): ?>
        <span class="badge badge-pending">DRAFT</span>
      <?php else: ?>
        <span class="badge" style="background:#f0f0f0;color:#888">NOT STARTED</span>
      <?php endif; ?>
    </td>
    <td>
      <?php if ($s['report_status'] !== 'submitted'): ?>
      <a href="?student=<?php echo $s['user_id']; ?>" class="btn btn-primary btn-sm">Write Report</a>
      <?php else: ?>
      <a href="?student=<?php echo $s['user_id']; ?>" class="btn btn-outline btn-sm">View</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?>
  <div class="card-body"><p class="text-muted">No students matched to your organisation yet.</p></div>
  <?php endif; ?>
</div>

<!-- Report Form -->
<?php if ($selectedStudent): ?>
<div class="card">
  <div class="card-header">
    <h3>Report for: <?php echo htmlspecialchars($selectedStudent['full_name']); ?></h3>
    <?php if ($existingReport && $existingReport['status']==='submitted'): ?><span class="badge badge-accepted">SUBMITTED</span><?php endif; ?>
  </div>
  <div class="card-body">
  <?php if ($existingReport && $existingReport['status']==='submitted'): ?>
    <div style="background:#d4edda;padding:1rem;border-radius:8px;margin-bottom:1rem;color:#155724">
      ✅ Report submitted on <?php echo date('j M Y H:i',strtotime($existingReport['submitted_at'])); ?>. No further changes allowed.
    </div>
    <!-- Show submitted values read-only -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <?php foreach(['Overall Performance'=>$existingReport['performance_rating'],'Punctuality'=>$existingReport['punctuality'],'Communication'=>$existingReport['communication'],'Technical Skills'=>$existingReport['technical_skills'],'Teamwork'=>$existingReport['teamwork']] as $label=>$val): ?>
      <div><span style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted)"><?php echo $label; ?></span><br>
      <strong><?php echo $ratingLabels[$val]??'—'; ?></strong> (<?php echo $val; ?>/5)</div>
      <?php endforeach; ?>
    </div>
    <?php if ($existingReport['comments']): ?><div style="margin-top:1rem"><strong>Comments:</strong><br><span class="text-muted"><?php echo nl2br(htmlspecialchars($existingReport['comments'])); ?></span></div><?php endif; ?>
    <div style="margin-top:.75rem"><strong>Recommendation:</strong> <?php echo $recOptions[$existingReport['recommendation']]??'—'; ?></div>
  <?php else: ?>
  <form method="POST">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="student_user_id" value="<?php echo $selectedStudent['user_id']; ?>">
    <input type="hidden" name="app_id" value="<?php echo $selectedStudent['app_id']; ?>">

    <p style="font-size:.82rem;color:var(--muted);margin-bottom:1.25rem">Rate each area from 1 (Poor) to 5 (Excellent)</p>

    <?php foreach(['performance_rating'=>'Overall Performance','punctuality'=>'Punctuality & Attendance','communication'=>'Communication Skills','technical_skills'=>'Technical Skills','teamwork'=>'Teamwork & Collaboration'] as $field=>$label): ?>
    <div class="form-group">
      <label><?php echo $label; ?></label>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <?php for($r=1;$r<=5;$r++): ?>
        <?php $checked = ($existingReport[$field]??3)===$r; ?>
        <label style="display:flex;align-items:center;gap:.3rem;cursor:pointer;padding:.4rem .75rem;border-radius:6px;border:1px solid <?php echo $checked?'var(--navy)':'#ddd'; ?>;background:<?php echo $checked?'var(--navy)':'#fff'; ?>;color:<?php echo $checked?'#fff':'var(--text)'; ?>;font-size:.85rem;font-weight:<?php echo $checked?'700':'400'; ?>">
          <input type="radio" name="<?php echo $field; ?>" value="<?php echo $r; ?>" <?php echo $checked?'checked':''; ?> style="display:none">
          <?php echo $r; ?> — <?php echo $ratingLabels[$r]; ?>
        </label>
        <?php endfor; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="form-group">
      <label>Comments & Observations</label>
      <textarea name="comments" rows="5" placeholder="Describe the student's overall performance, notable achievements, areas for improvement, and general attitude during the attachment..."><?php echo htmlspecialchars($existingReport['comments']??''); ?></textarea>
    </div>
    <div class="form-group">
      <label>Recommendation</label>
      <select name="recommendation">
        <?php foreach($recOptions as $v=>$l): ?>
        <option value="<?php echo $v; ?>" <?php echo ($existingReport['recommendation']??'recommend')===$v?'selected':''; ?>><?php echo $l; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;gap:.75rem">
      <button type="submit" name="action" value="save" class="btn btn-outline" style="flex:1">💾 Save Draft</button>
      <button type="submit" name="action" value="submit" class="btn btn-primary" style="flex:1" onclick="return confirm('Submit this report? You won\'t be able to change it afterwards.')">📤 Submit Report</button>
    </div>
  </form>
  <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2.5rem">
  <p class="text-muted">Select a student from the list to write their performance report.</p>
</div></div>
<?php endif; ?>
</div>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
