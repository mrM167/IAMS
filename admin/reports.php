<?php
// admin/reports.php — Placement reports, student ranking, CSV export (US-18)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = '';

// Grade a final report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_report') {
    csrf_check();
    $reportId  = (int)($_POST['report_id'] ?? 0);
    $grade     = trim($_POST['grade'] ?? '');
    $feedback  = trim($_POST['feedback'] ?? '');
    $studentId = (int)($_POST['student_user_id'] ?? 0);
    if ($reportId) {
        $db->prepare("UPDATE student_reports SET grade=?, feedback=?, status='reviewed', reviewed_by=?, reviewed_at=NOW() WHERE report_id=?")
           ->execute([$grade, $feedback, $user['id'], $reportId]);
        if ($studentId) {
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)")
               ->execute([$studentId, 'Report Graded', 'Your final attachment report has been graded. Log in to view your grade.', 'success', '/student_report.php']);
            $stuStmt = $db->prepare("SELECT full_name, email FROM users WHERE user_id=?");
            $stuStmt->execute([$studentId]);
            $stu = $stuStmt->fetch();
            if ($stu) Mailer::sendReportGraded($stu['email'], $stu['full_name'], $grade, $feedback);
        }
        $msg = 'Report graded and feedback saved.';
    }
    header('Location: /admin/reports.php?tab=student_reports&msg=' . urlencode($msg)); exit();
}

// CSV Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="iams_' . $type . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    if ($type === 'placement') {
        fputcsv($out, ['#','Full Name','Student Number','Email','Programme','Year','GPA','Organisation','Location','App Status','Match Score','Sup Report','Final Report','Grade','Visit 1 Score','Visit 2 Score']);
        $rows = $db->query("
            SELECT u.full_name, u.student_number, u.email, a.programme, sp.year_of_study, sp.gpa,
                   o.org_name, o.location as org_location, a.status,
                   m.match_score,
                   (SELECT status FROM supervisor_reports sr WHERE sr.student_user_id = u.user_id LIMIT 1) as sup_status,
                   fr.status as fr_status, fr.grade,
                   (SELECT overall_score FROM site_visit_assessments sv WHERE sv.student_user_id = u.user_id AND sv.visit_number = 1) as v1,
                   (SELECT overall_score FROM site_visit_assessments sv WHERE sv.student_user_id = u.user_id AND sv.visit_number = 2) as v2
            FROM applications a
            JOIN users u ON a.user_id = u.user_id
            LEFT JOIN student_profiles sp ON u.user_id = sp.user_id
            LEFT JOIN organisations o ON a.matched_org_id = o.org_id
            LEFT JOIN matches m ON m.app_id = a.app_id AND m.status = 'confirmed'
            LEFT JOIN student_reports fr ON fr.user_id = u.user_id
            WHERE a.status IN ('matched','accepted')
            ORDER BY u.full_name
        ")->fetchAll();
        $i = 1;
        foreach ($rows as $r) {
            fputcsv($out, [$i++, $r['full_name'], $r['student_number'], $r['email'], $r['programme'], $r['year_of_study'] ?? '', $r['gpa'] ?? '', $r['org_name'] ?? '', $r['org_location'] ?? '', $r['status'], $r['match_score'] ?? '', $r['sup_status'] ?? '', $r['fr_status'] ?? '', $r['grade'] ?? '', $r['v1'] ?? '', $r['v2'] ?? '']);
        }
    } elseif ($type === 'applications') {
        fputcsv($out, ['#','Full Name','Student Number','Email','Programme','Skills','Preferred Location','Status','Submitted']);
        $rows = $db->query("SELECT a.*, u.email FROM applications a JOIN users u ON a.user_id = u.user_id ORDER BY a.submission_date DESC")->fetchAll();
        $i = 1;
        foreach ($rows as $r) fputcsv($out, [$i++, $r['full_name'], $r['student_number'], $r['email'], $r['programme'], $r['skills'] ?? '', $r['preferred_location'] ?? '', $r['status'], date('j M Y', strtotime($r['submission_date']))]);
    } elseif ($type === 'students') {
        fputcsv($out, ['#','Full Name','Student Number','Email','Programme','Year','GPA','Skills','Registered','Last Login']);
        $rows = $db->query("SELECT u.*, sp.year_of_study, sp.gpa, sp.skills FROM users u LEFT JOIN student_profiles sp ON u.user_id = sp.user_id WHERE u.role = 'student' ORDER BY u.full_name")->fetchAll();
        $i = 1;
        foreach ($rows as $r) fputcsv($out, [$i++, $r['full_name'], $r['student_number'] ?? '', $r['email'], $r['programme'] ?? '', $r['year_of_study'] ?? '', $r['gpa'] ?? '', $r['skills'] ?? '', date('j M Y', strtotime($r['created_at'])), $r['last_login'] ? date('j M Y', strtotime($r['last_login'])) : 'Never']);
    }
    fclose($out);
    exit();
}

if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);
$tab = $_GET['tab'] ?? 'overview';

// Stats
$stats = [];
$statQueries = [
    'total_students'  => "SELECT COUNT(*) FROM users WHERE role='student'",
    'total_orgs'      => "SELECT COUNT(*) FROM organisations WHERE is_active=1",
    'total_apps'      => "SELECT COUNT(*) FROM applications",
    'pending'         => "SELECT COUNT(*) FROM applications WHERE status='pending'",
    'matched'         => "SELECT COUNT(*) FROM applications WHERE status IN('matched','accepted')",
    'rejected'        => "SELECT COUNT(*) FROM applications WHERE status='rejected'",
    'confirmed'       => "SELECT COUNT(*) FROM matches WHERE status='confirmed'",
    'logbooks_sub'    => "SELECT COUNT(*) FROM logbooks WHERE status IN('submitted','reviewed')",
    'logbooks_late'   => "SELECT COUNT(*) FROM logbooks WHERE status='late'",
    'sup_reports'     => "SELECT COUNT(*) FROM supervisor_reports WHERE status='submitted'",
    'final_reports'   => "SELECT COUNT(*) FROM student_reports WHERE status IN('submitted','reviewed')",
    'graded'          => "SELECT COUNT(*) FROM student_reports WHERE status='reviewed'",
    'assessments'     => "SELECT COUNT(*) FROM site_visit_assessments",
];
foreach ($statQueries as $k => $sql) $stats[$k] = (int)$db->query($sql)->fetchColumn();
$placementRate = $stats['total_apps'] > 0 ? round($stats['matched'] / $stats['total_apps'] * 100) : 0;

// Placement by programme
$byProg = $db->query("
    SELECT a.programme, COUNT(*) as total,
           SUM(CASE WHEN a.status IN('matched','accepted') THEN 1 ELSE 0 END) as matched
    FROM applications a GROUP BY a.programme ORDER BY total DESC
")->fetchAll();

// Capacity by org
$byOrg = $db->query("
    SELECT o.org_name, o.location, o.capacity,
           COALESCE((SELECT COUNT(*) FROM matches m WHERE m.org_id = o.org_id AND m.status = 'confirmed'), 0) as placed
    FROM organisations o WHERE o.is_active = 1 ORDER BY placed DESC
")->fetchAll();

// Student ranking
$rankedStudents = $db->query("
    SELECT u.full_name, u.student_number, u.email, a.programme, sp.gpa, sp.year_of_study,
           a.status as app_status, o.org_name, o.location as org_location,
           AVG(sv.overall_score) as avg_visit,
           (SELECT status FROM supervisor_reports sr WHERE sr.student_user_id = u.user_id LIMIT 1) as sup_status,
           fr.grade, fr.status as fr_status
    FROM users u
    JOIN applications a ON a.user_id = u.user_id
    LEFT JOIN student_profiles sp ON u.user_id = sp.user_id
    LEFT JOIN organisations o ON a.matched_org_id = o.org_id
    LEFT JOIN site_visit_assessments sv ON sv.student_user_id = u.user_id
    LEFT JOIN student_reports fr ON fr.user_id = u.user_id
    WHERE u.role = 'student'
    GROUP BY u.user_id
    ORDER BY sp.gpa DESC, avg_visit DESC, u.full_name ASC
")->fetchAll();

// Supervisor reports
$supReports = $db->query("
    SELECT sr.*, u.full_name as student_name, u.student_number,
           su.full_name as supervisor_name, o.org_name
    FROM supervisor_reports sr
    JOIN users u ON sr.student_user_id = u.user_id
    JOIN users su ON sr.supervisor_user_id = su.user_id
    LEFT JOIN applications a ON sr.app_id = a.app_id
    LEFT JOIN organisations o ON a.matched_org_id = o.org_id
    ORDER BY sr.submitted_at DESC
")->fetchAll();

// Student final reports
$finalReports = $db->query("
    SELECT fr.*, u.full_name, u.student_number, u.programme, o.org_name
    FROM student_reports fr
    JOIN users u ON fr.user_id = u.user_id
    LEFT JOIN applications a ON fr.app_id = a.app_id
    LEFT JOIN organisations o ON a.matched_org_id = o.org_id
    ORDER BY fr.submitted_at DESC
")->fetchAll();

$pageTitle = 'Reports & Analytics';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
  <div>
    <div class="page-title">&#128202; Reports &amp; Analytics</div>
    <div class="page-sub">Placement statistics, rankings, assessment results</div>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a href="?export=placement" class="btn btn-gold">&#11015; Export Placements CSV</a>
    <a href="?export=applications" class="btn btn-outline">&#11015; Applications CSV</a>
    <a href="?export=students" class="btn btn-outline">&#11015; Students CSV</a>
  </div>
</div>

<?php if ($msg): ?><div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.5rem;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <?php
  $tabs = ['overview' => '&#128202; Overview', 'ranking' => '&#127941; Student Ranking', 'supervisor_reports' => '&#128203; Supervisor Reports', 'student_reports' => '&#128196; Final Reports'];
  foreach ($tabs as $t => $l):
  ?>
  <a href="?tab=<?php echo $t; ?>" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab === $t ? 'background:var(--navy);color:#fff' : 'color:var(--muted)'; ?>"><?php echo $l; ?></a>
  <?php endforeach; ?>
</div>

<!-- OVERVIEW -->
<?php if ($tab === 'overview'): ?>
<div class="stats-grid">
  <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-num"><?php echo $stats['total_students']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Organisations</div><div class="stat-num"><?php echo $stats['total_orgs']; ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Applications</div><div class="stat-num"><?php echo $stats['total_apps']; ?></div></div>
  <div class="stat-card green"><div class="stat-label">Placed</div><div class="stat-num"><?php echo $stats['matched']; ?></div></div>
  <div class="stat-card"><div class="stat-label">Placement Rate</div><div class="stat-num"><?php echo $placementRate; ?>%</div></div>
  <div class="stat-card red"><div class="stat-label">Rejected</div><div class="stat-num"><?php echo $stats['rejected']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Logbooks Submitted</div><div class="stat-num"><?php echo $stats['logbooks_sub']; ?></div></div>
  <div class="stat-card red"><div class="stat-label">Late Logbooks</div><div class="stat-num"><?php echo $stats['logbooks_late']; ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Sup. Reports</div><div class="stat-num"><?php echo $stats['sup_reports']; ?></div></div>
  <div class="stat-card green"><div class="stat-label">Final Reports</div><div class="stat-num"><?php echo $stats['final_reports']; ?></div></div>
  <div class="stat-card"><div class="stat-label">Graded</div><div class="stat-num"><?php echo $stats['graded']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Site Assessments</div><div class="stat-num"><?php echo $stats['assessments']; ?></div></div>
</div>
<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3>Placement by Programme</h3></div>
    <table><thead><tr><th>Programme</th><th>Applied</th><th>Placed</th><th>Rate</th></tr></thead><tbody>
    <?php foreach ($byProg as $r): $rate = $r['total'] > 0 ? round($r['matched'] / $r['total'] * 100) : 0; ?>
    <tr>
      <td><?php echo htmlspecialchars($r['programme']); ?></td>
      <td style="text-align:center"><?php echo $r['total']; ?></td>
      <td style="text-align:center;color:var(--green);font-weight:700"><?php echo $r['matched']; ?></td>
      <td>
        <div style="display:flex;align-items:center;gap:.5rem">
          <div style="flex:1;background:#e5e7eb;border-radius:4px;height:6px"><div style="background:var(--green);height:6px;border-radius:4px;width:<?php echo $rate; ?>%"></div></div>
          <span style="font-size:.8rem;font-weight:600;min-width:35px"><?php echo $rate; ?>%</span>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
  <div class="card">
    <div class="card-header"><h3>Capacity by Organisation</h3></div>
    <table><thead><tr><th>Organisation</th><th>Capacity</th><th>Placed</th><th>Available</th></tr></thead><tbody>
    <?php foreach ($byOrg as $r): $avail = max(0, $r['capacity'] - $r['placed']); ?>
    <tr>
      <td><strong style="font-size:.88rem"><?php echo htmlspecialchars($r['org_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($r['location'] ?? ''); ?></span></td>
      <td style="text-align:center"><?php echo $r['capacity']; ?></td>
      <td style="text-align:center;color:var(--green);font-weight:700"><?php echo $r['placed']; ?></td>
      <td style="text-align:center;color:<?php echo $avail > 0 ? 'var(--teal)' : 'var(--red)'; ?>;font-weight:700"><?php echo $avail; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
</div>

<!-- RANKING -->
<?php elseif ($tab === 'ranking'): ?>
<div class="card">
  <div class="card-header">
    <h3>&#127941; Student Ranking — By GPA &amp; Assessment Score</h3>
    <a href="?export=students" class="btn btn-outline btn-sm">&#11015; Export CSV</a>
  </div>
  <table>
    <thead><tr><th>#</th><th>Student</th><th>Programme</th><th>Year</th><th>GPA</th><th>Placement</th><th>Visit Score</th><th>App Status</th><th>Sup Report</th><th>Final Grade</th></tr></thead>
    <tbody>
    <?php $rank = 1; foreach ($rankedStudents as $s): ?>
    <tr>
      <td style="font-weight:700;color:<?php echo $rank <= 3 ? 'var(--gold)' : 'var(--muted)'; ?>">
        <?php if ($rank === 1) echo '&#127881;'; elseif ($rank === 2) echo '&#129352;'; elseif ($rank === 3) echo '&#129353;'; else echo $rank; ?>
      </td>
      <td>
        <strong><?php echo htmlspecialchars($s['full_name']); ?></strong><br>
        <span class="text-muted"><?php echo htmlspecialchars($s['student_number'] ?? ''); ?></span>
      </td>
      <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($s['programme'] ?? '—'); ?></td>
      <td style="text-align:center"><?php echo $s['year_of_study'] ?? '—'; ?></td>
      <td style="font-weight:700;text-align:center;color:<?php echo $s['gpa'] ? ($s['gpa'] >= 3.5 ? 'var(--green)' : ($s['gpa'] >= 2.5 ? 'var(--teal)' : 'var(--gold)')) : 'var(--muted)'; ?>">
        <?php echo $s['gpa'] ? number_format($s['gpa'], 2) : '—'; ?>
      </td>
      <td style="font-size:.85rem"><?php echo $s['org_name'] ? htmlspecialchars($s['org_name']) : '<span class="text-muted">—</span>'; ?></td>
      <td style="text-align:center;font-weight:700;color:<?php echo $s['avg_visit'] ? ($s['avg_visit'] >= 7 ? 'var(--green)' : ($s['avg_visit'] >= 5 ? 'var(--gold)' : 'var(--red)')) : 'var(--muted)'; ?>">
        <?php echo $s['avg_visit'] ? number_format($s['avg_visit'], 1) . '/10' : '—'; ?>
      </td>
      <td><span class="badge badge-<?php echo str_replace(' ', '_', $s['app_status'] ?? 'pending'); ?>"><?php echo strtoupper(str_replace('_', ' ', $s['app_status'] ?? '—')); ?></span></td>
      <td><span class="badge badge-<?php echo $s['sup_status'] === 'submitted' ? 'accepted' : ($s['sup_status'] ? 'pending' : 'inactive'); ?>"><?php echo $s['sup_status'] ? strtoupper($s['sup_status']) : 'NONE'; ?></span></td>
      <td style="font-weight:700;color:var(--green)"><?php echo htmlspecialchars($s['grade'] ?? '—'); ?></td>
    </tr>
    <?php $rank++; endforeach; ?>
    <?php if (!$rankedStudents): ?><tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2rem">No students found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<!-- SUPERVISOR REPORTS -->
<?php elseif ($tab === 'supervisor_reports'): ?>
<div class="card">
<table>
  <thead><tr><th>Student</th><th>Organisation / Supervisor</th><th>Overall</th><th>Punctuality</th><th>Communication</th><th>Technical</th><th>Teamwork</th><th>Recommendation</th><th>Status</th><th>Submitted</th></tr></thead>
  <tbody>
  <?php foreach ($supReports as $r): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($r['student_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($r['student_number'] ?? ''); ?></span></td>
    <td><strong style="font-size:.88rem"><?php echo htmlspecialchars($r['org_name'] ?? '—'); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($r['supervisor_name']); ?></span></td>
    <?php foreach (['performance_rating', 'punctuality', 'communication', 'technical_skills', 'teamwork'] as $f): ?>
    <td style="text-align:center;font-weight:700"><?php echo $r[$f] ?? '—'; ?>/5</td>
    <?php endforeach; ?>
    <td class="text-muted" style="font-size:.8rem"><?php echo str_replace('_', ' ', ucwords($r['recommendation'] ?? '—')); ?></td>
    <td><span class="badge badge-<?php echo $r['status'] === 'submitted' ? 'accepted' : 'pending'; ?>"><?php echo strtoupper($r['status']); ?></span></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $r['submitted_at'] ? date('j M Y', strtotime($r['submitted_at'])) : '—'; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$supReports): ?><tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2rem">No supervisor reports yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<!-- FINAL REPORTS -->
<?php elseif ($tab === 'student_reports'): ?>
<div class="card">
<table>
  <thead><tr><th>Student</th><th>Programme</th><th>Organisation</th><th>Report Title</th><th>Status</th><th>Grade</th><th>Submitted</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($finalReports as $r): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($r['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($r['student_number'] ?? ''); ?></span></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($r['programme'] ?? '—'); ?></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($r['org_name'] ?? '—'); ?></td>
    <td style="font-size:.88rem"><?php echo htmlspecialchars(substr($r['title'], 0, 50)); ?></td>
    <td><span class="badge badge-<?php echo ['draft' => 'pending', 'submitted' => 'under_review', 'reviewed' => 'accepted'][$r['status']] ?? 'pending'; ?>"><?php echo strtoupper($r['status']); ?></span></td>
    <td style="font-weight:700;color:var(--green)"><?php echo htmlspecialchars($r['grade'] ?? '—'); ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $r['submitted_at'] ? date('j M Y', strtotime($r['submitted_at'])) : '—'; ?></td>
    <td>
      <?php if ($r['status'] === 'submitted'): ?>
      <button class="btn btn-gold btn-sm" onclick="openGradeDialog(<?php echo $r['report_id']; ?>, <?php echo $r['user_id']; ?>)">Grade</button>
      <?php else: ?>
      <a href="/admin/assessments.php?student=<?php echo $r['user_id']; ?>" class="btn btn-outline btn-sm">View</a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$finalReports): ?><tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem">No final reports yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<!-- Grade dialog -->
<div id="gradeDialog" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:2rem;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <h3 style="margin-bottom:1rem;color:var(--navy)">Grade Final Report</h3>
    <form method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="grade_report">
      <input type="hidden" name="report_id" id="g_report_id">
      <input type="hidden" name="student_user_id" id="g_student_id">
      <div class="form-group"><label>Grade *</label><input type="text" name="grade" required placeholder="e.g. A, B+, Distinction, 78%"></div>
      <div class="form-group"><label>Written Feedback</label><textarea name="feedback" rows="5" placeholder="Provide constructive feedback for the student..."></textarea></div>
      <div style="display:flex;gap:.75rem">
        <button type="button" onclick="document.getElementById('gradeDialog').style.display='none'" class="btn btn-outline" style="flex:1">Cancel</button>
        <button type="submit" class="btn btn-primary" style="flex:1">Save Grade</button>
      </div>
    </form>
  </div>
</div>
<script>
function openGradeDialog(rid, sid) {
  document.getElementById('g_report_id').value = rid;
  document.getElementById('g_student_id').value = sid;
  document.getElementById('gradeDialog').style.display = 'flex';
}
</script>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS &copy; <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>