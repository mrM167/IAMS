<?php
// admin/assessments.php — University supervisor site visit assessments (US-13)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $studentId  = (int)($_POST['student_user_id'] ?? 0);
    $appId      = (int)($_POST['app_id'] ?? 0);
    $visitNum   = (int)($_POST['visit_number'] ?? 1);
    $visitDate  = trim($_POST['visit_date'] ?? '');
    $wquality   = min(10, max(1, (int)($_POST['work_quality']??5)));
    $attitude   = min(10, max(1, (int)($_POST['attitude']??5)));
    $technical  = min(10, max(1, (int)($_POST['technical_ability']??5)));
    $overall    = round(($wquality + $attitude + $technical) / 3, 1);
    $comments   = trim($_POST['comments']??'');

    if (!$studentId||!$appId||!$visitDate) { $err = 'All fields required.'; }
    elseif ($visitNum < 1 || $visitNum > 2) { $err = 'Visit number must be 1 or 2.'; }
    else {
        try {
            // Check if this visit already recorded
            $dupStmt = $db->prepare("SELECT assessment_id FROM site_visit_assessments WHERE student_user_id=? AND visit_number=?");
            $dupStmt->execute([$studentId, $visitNum]);
            $dup = $dupStmt->fetch();
            if ($dup) {
                $db->prepare("UPDATE site_visit_assessments SET visit_date=?,work_quality=?,attitude=?,technical_ability=?,overall_score=?,comments=?,assessor_id=? WHERE assessment_id=?")
                   ->execute([$visitDate,$wquality,$attitude,$technical,$overall,$comments,$user['id'],$dup['assessment_id']]);
                $msg = 'Visit '.$visitNum.' assessment updated.';
            } else {
                $db->prepare("INSERT INTO site_visit_assessments (student_user_id,app_id,assessor_id,visit_number,visit_date,work_quality,attitude,technical_ability,overall_score,comments) VALUES (?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$studentId,$appId,$user['id'],$visitNum,$visitDate,$wquality,$attitude,$technical,$overall,$comments]);
                $msg = 'Visit '.$visitNum.' assessment recorded!';
                $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
                   ->execute([$studentId,'Site Visit Assessment Recorded','Your university supervisor has recorded Visit '.$visitNum.' assessment results.','info','/student_report.php']);
            }
        } catch (Exception $e) { $err = $e->getMessage(); }
    }
    header('Location: /admin/assessments.php?student='.$studentId.'&msg='.urlencode($msg)); exit();
}
if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// Load all matched/accepted students
$studentsStmt = $db->query("
    SELECT u.user_id,u.full_name,u.student_number,u.programme,u.email,
           a.app_id,a.status as app_status,
           o.org_name,o.location as org_location,
           COUNT(DISTINCT sv.assessment_id) as visit_count,
           MAX(sv.overall_score) as best_score
    FROM applications a
    JOIN users u ON a.user_id=u.user_id
    LEFT JOIN organisations o ON a.matched_org_id=o.org_id
    LEFT JOIN site_visit_assessments sv ON sv.student_user_id=u.user_id
    WHERE a.status IN ('matched','accepted')
    GROUP BY u.user_id ORDER BY u.full_name
");
$students = $studentsStmt->fetchAll();

// Detail view for selected student
$selStudent  = null;
$selVisits   = [];
$selSupRep   = null;
$selFinalRep = null;
if (isset($_GET['student'])) {
    foreach ($students as $s) { if ($s['user_id']==(int)$_GET['student']) { $selStudent=$s; break; } }
    if ($selStudent) {
        $vsStmt = $db->prepare("SELECT * FROM site_visit_assessments WHERE student_user_id=? ORDER BY visit_number");
        $vsStmt->execute([$selStudent['user_id']]);
        $selVisits = $vsStmt->fetchAll();
        $srStmt = $db->prepare("SELECT * FROM supervisor_reports WHERE student_user_id=? ORDER BY created_at DESC LIMIT 1");
        $srStmt->execute([$selStudent['user_id']]);
        $selSupRep = $srStmt->fetch();
        $frStmt = $db->prepare("SELECT * FROM student_reports WHERE user_id=? LIMIT 1");
        $frStmt->execute([$selStudent['user_id']]);
        $selFinalRep = $frStmt->fetch();
    }
}

$ratingMap = [1=>'1',2=>'2',3=>'3',4=>'4',5=>'5',6=>'6',7=>'7',8=>'8',9=>'9',10=>'10'];
$pageTitle = 'Site Visit Assessments';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">🏭 Site Visit Assessments</div>
<div class="page-sub">Record university supervisor assessments from site visits (max 2 per student)</div>

<?php if ($msg): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err):  ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start">

<!-- Student list sidebar -->
<div class="card">
  <div class="card-header"><h3>Placed Students (<?php echo count($students); ?>)</h3></div>
  <?php foreach ($students as $s): ?>
  <a href="?student=<?php echo $s['user_id']; ?>" style="display:block;padding:.85rem 1.25rem;border-bottom:1px solid #f5f5f5;text-decoration:none;background:<?php echo ($selStudent&&$selStudent['user_id']===$s['user_id'])?'#fffbec':'#fff'; ?>;transition:background .15s">
    <div style="font-weight:600;color:var(--navy);font-size:.9rem"><?php echo htmlspecialchars($s['full_name']); ?></div>
    <div style="font-size:.78rem;color:var(--muted)"><?php echo htmlspecialchars($s['student_number']??''); ?> · <?php echo htmlspecialchars($s['programme']??''); ?></div>
    <div style="font-size:.75rem;color:var(--teal);margin-top:.2rem"><?php echo htmlspecialchars($s['org_name']??'Unassigned'); ?></div>
    <div style="margin-top:.35rem">
      <?php for($v=1;$v<=2;$v++): ?>
      <span style="display:inline-block;padding:.1rem .45rem;border-radius:4px;font-size:.7rem;font-weight:700;margin-right:.25rem;background:<?php echo $s['visit_count']>=$v?'var(--green)':'#e5e7eb'; ?>;color:<?php echo $s['visit_count']>=$v?'#fff':'#9ca3af'; ?>">Visit <?php echo $v; ?></span>
      <?php endfor; ?>
    </div>
  </a>
  <?php endforeach; ?>
  <?php if (!$students): ?><div class="card-body"><p class="text-muted">No placed students yet.</p></div><?php endif; ?>
</div>

<!-- Assessment panel -->
<?php if ($selStudent): ?>
<div>
  <div style="margin-bottom:.75rem">
    <div class="page-title" style="font-size:1.2rem;margin-bottom:.15rem"><?php echo htmlspecialchars($selStudent['full_name']); ?></div>
    <div class="text-muted"><?php echo htmlspecialchars($selStudent['programme']??''); ?> · <?php echo htmlspecialchars($selStudent['org_name']??''); ?>, <?php echo htmlspecialchars($selStudent['org_location']??''); ?></div>
  </div>

  <!-- Existing visit records -->
  <?php if ($selVisits): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><h3>Recorded Assessments</h3></div>
    <table><thead><tr><th>Visit</th><th>Date</th><th>Work Quality</th><th>Attitude</th><th>Technical</th><th>Overall</th><th>Comments</th></tr></thead><tbody>
    <?php foreach ($selVisits as $v): ?>
    <tr>
      <td><strong>Visit <?php echo $v['visit_number']; ?></strong></td>
      <td class="text-muted" style="font-size:.82rem"><?php echo date('j M Y',strtotime($v['visit_date'])); ?></td>
      <td style="text-align:center"><?php echo $v['work_quality']; ?>/10</td>
      <td style="text-align:center"><?php echo $v['attitude']; ?>/10</td>
      <td style="text-align:center"><?php echo $v['technical_ability']; ?>/10</td>
      <td style="font-weight:700;color:var(--navy);text-align:center"><?php echo $v['overall_score']; ?>/10</td>
      <td class="text-muted" style="font-size:.8rem;max-width:200px"><?php echo htmlspecialchars(substr($v['comments']??'—',0,80)); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <!-- Average score -->
    <?php if (count($selVisits)===2): ?>
    <?php $avg = round(array_sum(array_column($selVisits,'overall_score'))/2,1); ?>
    <div style="padding:1rem 1.25rem;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
      <span style="font-weight:600">Final Site Visit Average Score:</span>
      <span style="font-size:1.5rem;font-weight:700;color:<?php echo $avg>=7?'var(--green)':($avg>=5?'var(--gold)':'var(--red)'); ?>"><?php echo $avg; ?>/10</span>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Supervisor Report summary -->
  <?php if ($selSupRep): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><h3>Industrial Supervisor Report</h3><span class="badge badge-<?php echo $selSupRep['status']==='submitted'?'accepted':'pending'; ?>"><?php echo strtoupper($selSupRep['status']); ?></span></div>
    <div class="card-body">
      <?php $ratingLabels=[1=>'Poor',2=>'Below Avg',3=>'Average',4=>'Good',5=>'Excellent']; ?>
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;margin-bottom:.75rem">
        <?php foreach(['performance_rating'=>'Overall','punctuality'=>'Punctuality','communication'=>'Communication','technical_skills'=>'Technical','teamwork'=>'Teamwork'] as $f=>$l): ?>
        <div style="text-align:center;padding:.5rem;background:#f8f9fb;border-radius:7px">
          <div style="font-size:.65rem;color:var(--muted);margin-bottom:.2rem"><?php echo $l; ?></div>
          <div style="font-weight:700;color:var(--navy)"><?php echo $selSupRep[$f]??'—'; ?>/5</div>
          <div style="font-size:.65rem;color:var(--muted)"><?php echo $ratingLabels[$selSupRep[$f]]??''; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if ($selSupRep['comments']): ?><p style="font-size:.85rem;color:var(--muted)"><?php echo nl2br(htmlspecialchars($selSupRep['comments'])); ?></p><?php endif; ?>
      <p style="margin-top:.5rem;font-size:.82rem"><strong>Recommendation:</strong> <?php echo str_replace('_',' ',ucwords($selSupRep['recommendation']??'')); ?></p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Grade final report -->
  <?php if ($selFinalRep): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-header"><h3>Student Final Report — Grade</h3>
    <span class="badge badge-<?php echo ['draft'=>'pending','submitted'=>'under_review','reviewed'=>'accepted'][$selFinalRep['status']]??'pending'; ?>"><?php echo strtoupper($selFinalRep['status']); ?></span></div>
    <div class="card-body">
      <?php if ($selFinalRep['status']==='submitted'): ?>
      <form method="POST" action="/admin/reports.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="grade_report">
        <input type="hidden" name="report_id" value="<?php echo $selFinalRep['report_id']; ?>">
        <input type="hidden" name="student_user_id" value="<?php echo $selStudent['user_id']; ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label>Grade</label><input type="text" name="grade" value="<?php echo htmlspecialchars($selFinalRep['grade']??''); ?>" placeholder="e.g. A, B+, 75%"></div>
        </div>
        <div class="form-group"><label>Feedback for Student</label><textarea name="feedback" rows="4"><?php echo htmlspecialchars($selFinalRep['feedback']??''); ?></textarea></div>
        <button type="submit" class="btn btn-primary">Save Grade & Feedback</button>
      </form>
      <?php elseif ($selFinalRep['status']==='reviewed'): ?>
      <p><strong>Grade: </strong><span style="color:var(--green);font-weight:700;font-size:1.1rem"><?php echo htmlspecialchars($selFinalRep['grade']??''); ?></span></p>
      <?php if ($selFinalRep['feedback']): ?><p class="text-muted" style="margin-top:.5rem"><?php echo nl2br(htmlspecialchars($selFinalRep['feedback'])); ?></p><?php endif; ?>
      <?php else: ?><p class="text-muted">Student has not yet submitted their final report.</p><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- New assessment form -->
  <?php $visitsDone = array_column($selVisits,'visit_number'); $nextVisit = in_array(1,$visitsDone)?2:1; ?>
  <?php if (count($selVisits)<2): ?>
  <div class="card">
    <div class="card-header"><h3>Record Visit <?php echo $nextVisit; ?> Assessment</h3></div>
    <div class="card-body">
    <form method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="student_user_id" value="<?php echo $selStudent['user_id']; ?>">
      <input type="hidden" name="app_id" value="<?php echo $selStudent['app_id']; ?>">
      <input type="hidden" name="visit_number" value="<?php echo $nextVisit; ?>">
      <div class="form-group"><label>Visit Date *</label><input type="date" name="visit_date" required value="<?php echo date('Y-m-d'); ?>"></div>
      <?php foreach(['work_quality'=>'Work Quality (1–10)','attitude'=>'Attitude & Professionalism (1–10)','technical_ability'=>'Technical Ability (1–10)'] as $field=>$label): ?>
      <div class="form-group">
        <label><?php echo $label; ?></label>
        <input type="range" name="<?php echo $field; ?>" min="1" max="10" value="5" oninput="document.getElementById('<?php echo $field; ?>_val').textContent=this.value" style="width:100%;margin-bottom:.25rem">
        <div style="display:flex;justify-content:space-between;font-size:.75rem;color:var(--muted)"><span>1 (Poor)</span><span id="<?php echo $field; ?>_val" style="font-weight:700;color:var(--navy)">5</span><span>10 (Excellent)</span></div>
      </div>
      <?php endforeach; ?>
      <div class="form-group"><label>Comments & Observations</label><textarea name="comments" rows="4" placeholder="Record observations about the student's performance during this site visit..."></textarea></div>
      <button type="submit" class="btn btn-primary">Record Visit <?php echo $nextVisit; ?> Assessment</button>
    </form>
    </div>
  </div>
  <?php else: ?>
  <div class="card"><div class="card-body" style="text-align:center;padding:1.5rem;color:var(--green)">
    <strong>✅ Both site visits have been assessed for this student.</strong>
  </div></div>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:3rem">
  <p class="text-muted">Select a student from the list to record site visit assessments.</p>
</div></div>
<?php endif; ?>

</div>
</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
