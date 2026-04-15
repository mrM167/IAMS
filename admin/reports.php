<?php
// admin/reports.php — Admin summary reports & student ranking (US-18)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';

// Handle report grading (called from assessments page)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_report') {
    csrf_check();
    $reportId = (int)($_POST['report_id'] ?? 0);
    $grade    = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');
    $studentId = (int)($_POST['student_user_id'] ?? 0);
    if ($reportId) {
        $db->prepare("UPDATE student_reports SET grade=?,feedback=?,status='reviewed',reviewed_by=?,reviewed_at=NOW() WHERE report_id=?")->execute([$grade,$feedback,$user['id'],$reportId]);
        if ($studentId) {
            $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
               ->execute([$studentId,'Report Graded','Your final attachment report has been graded. Log in to view your grade and feedback.','success','/student_report.php']);
        }
        $msg = 'Report graded and feedback saved.';
    }
    header('Location: /admin/reports.php?tab=student_reports&msg='.urlencode($msg)); exit();
}

if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);
$tab = $_GET['tab'] ?? 'overview';

// ── Load all stats ────────────────────────────────────────────────
$stats = [];
foreach([
    'total_students'       => "SELECT COUNT(*) FROM users WHERE role='student'",
    'total_orgs'           => "SELECT COUNT(*) FROM organisations WHERE is_active=1",
    'total_apps'           => "SELECT COUNT(*) FROM applications",
    'pending'              => "SELECT COUNT(*) FROM applications WHERE status='pending'",
    'matched'              => "SELECT COUNT(*) FROM applications WHERE status IN('matched','accepted')",
    'rejected'             => "SELECT COUNT(*) FROM applications WHERE status='rejected'",
    'confirmed_matches'    => "SELECT COUNT(*) FROM matches WHERE status='confirmed'",
    'logbooks_submitted'   => "SELECT COUNT(*) FROM logbooks WHERE status IN('submitted','reviewed')",
    'logbooks_late'        => "SELECT COUNT(*) FROM logbooks WHERE status='late'",
    'supervisor_reports'   => "SELECT COUNT(*) FROM supervisor_reports WHERE status='submitted'",
    'final_reports'        => "SELECT COUNT(*) FROM student_reports WHERE status IN('submitted','reviewed')",
    'graded_reports'       => "SELECT COUNT(*) FROM student_reports WHERE status='reviewed'",
    'site_assessments'     => "SELECT COUNT(*) FROM site_visit_assessments",
] as $k=>$sql) { $stats[$k] = (int)$db->query($sql)->fetchColumn(); }

// Placement rate
$placementRate = $stats['total_apps'] > 0 ? round($stats['matched'] / $stats['total_apps'] * 100) : 0;

// Placement by programme
$byProgramme = $db->query("
    SELECT a.programme, COUNT(*) as total,
           SUM(CASE WHEN a.status IN('matched','accepted') THEN 1 ELSE 0 END) as matched
    FROM applications a GROUP BY a.programme ORDER BY total DESC
")->fetchAll();

// Placement by organisation
$byOrg = $db->query("
    SELECT o.org_name, o.location, o.capacity,
           COUNT(m.match_id) as placed,
           o.capacity - COUNT(m.match_id) as available
    FROM organisations o
    LEFT JOIN matches m ON o.org_id=m.org_id AND m.status='confirmed'
    WHERE o.is_active=1
    GROUP BY o.org_id ORDER BY placed DESC
")->fetchAll();

// All placed students with their info (ranking view)
$placedStudents = $db->query("
    SELECT u.full_name,u.student_number,u.email,u.programme,
           a.preferred_location,a.skills,a.submission_date,a.status,
           o.org_name,o.location as org_location,
           sp.gpa,sp.year_of_study,
           AVG(sv.overall_score) as avg_visit_score,
           sr.status as sup_report_status,
           fr.grade, fr.status as final_report_status
    FROM applications a
    JOIN users u ON a.user_id=u.user_id
    LEFT JOIN student_profiles sp ON u.user_id=sp.user_id
    LEFT JOIN organisations o ON a.matched_org_id=o.org_id
    LEFT JOIN site_visit_assessments sv ON sv.student_user_id=u.user_id
    LEFT JOIN supervisor_reports sr ON sr.student_user_id=u.user_id
    LEFT JOIN student_reports fr ON fr.user_id=u.user_id
    WHERE a.status IN('matched','accepted')
    GROUP BY u.user_id ORDER BY u.full_name
")->fetchAll();

// Supervisor reports list
$supReports = $db->query("
    SELECT sr.*,u.full_name as student_name,u.student_number,su.full_name as supervisor_name,o.org_name
    FROM supervisor_reports sr
    JOIN users u ON sr.student_user_id=u.user_id
    JOIN users su ON sr.supervisor_user_id=su.user_id
    LEFT JOIN applications a ON sr.app_id=a.app_id
    LEFT JOIN organisations o ON a.matched_org_id=o.org_id
    ORDER BY sr.submitted_at DESC
")->fetchAll();

// Student final reports
$finalReports = $db->query("
    SELECT fr.*,u.full_name,u.student_number,u.programme,o.org_name
    FROM student_reports fr
    JOIN users u ON fr.user_id=u.user_id
    LEFT JOIN applications a ON fr.app_id=a.app_id
    LEFT JOIN organisations o ON a.matched_org_id=o.org_id
    ORDER BY fr.submitted_at DESC
")->fetchAll();

$pageTitle = 'Reports & Analytics';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">📊 Reports & Analytics</div>
<div class="page-sub">Placement statistics, student assessments, and system reports</div>

<?php if ($msg): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- Tab nav -->
<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.5rem;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <?php foreach(['overview'=>'📊 Overview','placement'=>'🎯 Placement','supervisor_reports'=>'📋 Supervisor Reports','student_reports'=>'📄 Final Reports'] as $t=>$l): ?>
  <a href="?tab=<?php echo $t; ?>" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab===$t?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>"><?php echo $l; ?></a>
  <?php endforeach; ?>
</div>

<!-- ═══ OVERVIEW ═══ -->
<?php if ($tab === 'overview'): ?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-num"><?php echo $stats['total_students']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Organisations</div><div class="stat-num"><?php echo $stats['total_orgs']; ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Applications</div><div class="stat-num"><?php echo $stats['total_apps']; ?></div></div>
  <div class="stat-card green"><div class="stat-label">Placed Students</div><div class="stat-num"><?php echo $stats['matched']; ?></div></div>
  <div class="stat-card"><div class="stat-label">Placement Rate</div><div class="stat-num"><?php echo $placementRate; ?>%</div></div>
  <div class="stat-card red"><div class="stat-label">Rejected</div><div class="stat-num"><?php echo $stats['rejected']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Logbooks Submitted</div><div class="stat-num"><?php echo $stats['logbooks_submitted']; ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Sup. Reports</div><div class="stat-num"><?php echo $stats['supervisor_reports']; ?></div></div>
  <div class="stat-card green"><div class="stat-label">Final Reports</div><div class="stat-num"><?php echo $stats['final_reports']; ?></div></div>
  <div class="stat-card"><div class="stat-label">Graded Reports</div><div class="stat-num"><?php echo $stats['graded_reports']; ?></div></div>
  <div class="stat-card red"><div class="stat-label">Late Logbooks</div><div class="stat-num"><?php echo $stats['logbooks_late']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Site Assessments</div><div class="stat-num"><?php echo $stats['site_assessments']; ?></div></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3>Placement by Programme</h3></div>
    <table><thead><tr><th>Programme</th><th>Applications</th><th>Placed</th><th>Rate</th></tr></thead><tbody>
    <?php foreach ($byProgramme as $row): ?>
    <tr>
      <td><?php echo htmlspecialchars($row['programme']); ?></td>
      <td style="text-align:center"><?php echo $row['total']; ?></td>
      <td style="text-align:center;color:var(--green);font-weight:700"><?php echo $row['matched']; ?></td>
      <td>
        <?php $rate = $row['total']>0?round($row['matched']/$row['total']*100):0; ?>
        <div style="display:flex;align-items:center;gap:.5rem">
          <div style="flex:1;background:#e5e7eb;border-radius:4px;height:6px"><div style="background:var(--green);height:6px;border-radius:4px;width:<?php echo $rate; ?>%"></div></div>
          <span style="font-size:.8rem;font-weight:600;width:35px"><?php echo $rate; ?>%</span>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <div class="card-header"><h3>Capacity by Organisation</h3></div>
    <table><thead><tr><th>Organisation</th><th>Capacity</th><th>Placed</th><th>Available</th></tr></thead><tbody>
    <?php foreach ($byOrg as $row): ?>
    <tr>
      <td><strong style="font-size:.88rem"><?php echo htmlspecialchars($row['org_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($row['location']??''); ?></span></td>
      <td style="text-align:center"><?php echo $row['capacity']; ?></td>
      <td style="text-align:center;color:var(--green);font-weight:700"><?php echo $row['placed']; ?></td>
      <td style="text-align:center;color:<?php echo $row['available']>0?'var(--teal)':'var(--red)'; ?>;font-weight:700"><?php echo max(0,$row['available']); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</div>

<!-- ═══ PLACEMENT ═══ -->
<?php elseif ($tab === 'placement'): ?>
<div class="card">
  <div class="card-header">
    <h3>Placed Students — Full List (<?php echo count($placedStudents); ?>)</h3>
    <span style="font-size:.8rem;color:var(--muted)">Sorted alphabetically</span>
  </div>
  <table>
    <thead><tr><th>#</th><th>Student</th><th>Programme</th><th>Placed At</th><th>Location</th><th>GPA</th><th>Visit Score</th><th>Sup. Report</th><th>Final Report</th><th>Grade</th></tr></thead>
    <tbody>
    <?php foreach ($placedStudents as $i=>$s): ?>
    <tr>
      <td style="font-weight:700;color:var(--muted)"><?php echo $i+1; ?></td>
      <td>
        <strong><?php echo htmlspecialchars($s['full_name']); ?></strong><br>
        <span class="text-muted"><?php echo htmlspecialchars($s['student_number']??''); ?></span>
      </td>
      <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($s['programme']??'—'); ?></td>
      <td><strong style="font-size:.88rem"><?php echo htmlspecialchars($s['org_name']??'—'); ?></strong></td>
      <td class="text-muted"><?php echo htmlspecialchars($s['org_location']??'—'); ?></td>
      <td style="text-align:center"><?php echo $s['gpa']??'—'; ?></td>
      <td style="text-align:center;font-weight:700;color:<?php echo $s['avg_visit_score']?($s['avg_visit_score']>=7?'var(--green)':($s['avg_visit_score']>=5?'var(--gold)':'var(--red)')):'var(--muted)'; ?>">
        <?php echo $s['avg_visit_score'] ? number_format($s['avg_visit_score'],1).'/10' : '—'; ?>
      </td>
      <td><span class="badge badge-<?php echo $s['sup_report_status']==='submitted'?'accepted':($s['sup_report_status']?'pending':'inactive'); ?>"><?php echo $s['sup_report_status']?strtoupper($s['sup_report_status']):'NONE'; ?></span></td>
      <td><span class="badge badge-<?php echo $s['final_report_status']==='reviewed'?'accepted':($s['final_report_status']==='submitted'?'under_review':($s['final_report_status']?'pending':'inactive')); ?>"><?php echo $s['final_report_status']?strtoupper($s['final_report_status']):'NONE'; ?></span></td>
      <td style="font-weight:700;color:var(--green)"><?php echo htmlspecialchars($s['grade']??'—'); ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$placedStudents): ?><tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2rem">No placed students yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ═══ SUPERVISOR REPORTS ═══ -->
<?php elseif ($tab === 'supervisor_reports'): ?>
<div class="card">
<table>
  <thead><tr><th>Student</th><th>Organisation / Supervisor</th><th>Overall</th><th>Punctuality</th><th>Communication</th><th>Technical</th><th>Teamwork</th><th>Recommendation</th><th>Status</th><th>Submitted</th></tr></thead>
  <tbody>
  <?php foreach ($supReports as $r): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($r['student_number']??''); ?></span></td>
    <td><strong style="font-size:.88rem"><?php echo htmlspecialchars($r['org_name']??'—'); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($r['supervisor_name']); ?></span></td>
    <?php foreach(['performance_rating','punctuality','communication','technical_skills','teamwork'] as $f): ?>
    <td style="text-align:center;font-weight:700"><?php echo $r[$f]??'—'; ?>/5</td>
    <?php endforeach; ?>
    <td class="text-muted" style="font-size:.8rem"><?php echo str_replace('_',' ',ucwords($r['recommendation']??'—')); ?></td>
    <td><span class="badge badge-<?php echo $r['status']==='submitted'?'accepted':'pending'; ?>"><?php echo strtoupper($r['status']); ?></span></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $r['submitted_at']?date('j M Y',strtotime($r['submitted_at'])):'—'; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$supReports): ?><tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2rem">No supervisor reports yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<!-- ═══ STUDENT FINAL REPORTS ═══ -->
<?php elseif ($tab === 'student_reports'): ?>
<div class="card">
<table>
  <thead><tr><th>Student</th><th>Programme</th><th>Organisation</th><th>Report Title</th><th>Status</th><th>Grade</th><th>Submitted</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($finalReports as $r): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($r['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($r['student_number']??''); ?></span></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($r['programme']??'—'); ?></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($r['org_name']??'—'); ?></td>
    <td style="font-size:.88rem"><?php echo htmlspecialchars(substr($r['title'],0,50)); ?></td>
    <td><span class="badge badge-<?php echo ['draft'=>'pending','submitted'=>'under_review','reviewed'=>'accepted'][$r['status']]??'pending'; ?>"><?php echo strtoupper($r['status']); ?></span></td>
    <td style="font-weight:700;color:var(--green)"><?php echo htmlspecialchars($r['grade']??'—'); ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $r['submitted_at']?date('j M Y',strtotime($r['submitted_at'])):'—'; ?></td>
    <td>
      <?php if ($r['status']==='submitted'): ?>
      <form method="POST" style="display:inline">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="grade_report">
        <input type="hidden" name="report_id" value="<?php echo $r['report_id']; ?>">
        <input type="hidden" name="student_user_id" value="<?php echo $r['user_id']; ?>">
        <button type="button" class="btn btn-gold btn-sm" onclick="gradeDialog(<?php echo $r['report_id']; ?>,<?php echo $r['user_id']; ?>)">Grade</button>
      </form>
      <?php else: ?><a href="/admin/assessments.php?student=<?php echo $r['user_id']; ?>" class="btn btn-outline btn-sm">View</a><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$finalReports): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No final reports submitted yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<!-- Grade dialog -->
<div id="gradeDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:2rem;max-width:480px;width:90%">
    <h3 style="margin-bottom:1rem;color:var(--navy)">Grade Final Report</h3>
    <form method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="grade_report">
      <input type="hidden" name="report_id" id="g_report_id">
      <input type="hidden" name="student_user_id" id="g_student_id">
      <div class="form-group"><label>Grade</label><input type="text" name="grade" required placeholder="e.g. A, B+, 75%"></div>
      <div class="form-group"><label>Feedback</label><textarea name="feedback" rows="5" placeholder="Written feedback for the student..."></textarea></div>
      <div style="display:flex;gap:.75rem"><button type="button" onclick="document.getElementById('gradeDialog').style.display='none'" class="btn btn-outline">Cancel</button><button type="submit" class="btn btn-primary">Save Grade</button></div>
    </form>
  </div>
</div>
<script>
function gradeDialog(rid,sid){
  document.getElementById('g_report_id').value=rid;
  document.getElementById('g_student_id').value=sid;
  document.getElementById('gradeDialog').style.display='flex';
}
</script>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
