<?php
// student_report.php — Student final attachment report (US-11)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();
requireRole('student');

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';

// Must have confirmed placement
$appStmt = $db->prepare("SELECT * FROM applications WHERE user_id=? AND status IN ('matched','accepted')");
$appStmt->execute([$user['id']]);
$application = $appStmt->fetch();

// Load existing report
$reportStmt = $db->prepare("SELECT * FROM student_reports WHERE user_id=?");
$reportStmt->execute([$user['id']]);
$report = $reportStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action  = $_POST['action'] ?? 'save';
    $title   = trim($_POST['title'] ?? '');
    $summary = trim($_POST['executive_summary'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $concl   = trim($_POST['conclusion'] ?? '');

    if (!$application) { $err = 'You must have a confirmed attachment to submit a report.'; }
    elseif (!$title)   { $err = 'Report title is required.'; }
    elseif ($action === 'submit' && !$body) { $err = 'Report body cannot be empty when submitting.'; }
    elseif ($report && $report['status'] === 'reviewed') { $err = 'Your report has already been reviewed and cannot be edited.'; }
    else {
        $status      = ($action === 'submit') ? 'submitted' : 'draft';
        $submittedAt = ($action === 'submit') ? date('Y-m-d H:i:s') : null;

        // Handle optional file attachment
        $filePath = $report['file_path'] ?? null;
        if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['report_file'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf','docx'];
            if (!in_array($ext, $allowed))        { $err = 'Only PDF and DOCX files allowed.'; }
            elseif ($file['size'] > 20971520)      { $err = 'File too large. Max 20MB.'; }
            else {
                $dir = __DIR__ . '/uploads/reports/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname    = $user['id'] . '_report_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                    $filePath = 'uploads/reports/' . $fname;
                } else { $err = 'File upload failed.'; }
            }
        }

        if (!$err) {
            if ($report) {
                $db->prepare("UPDATE student_reports SET title=?,executive_summary=?,body=?,conclusion=?,file_path=?,status=?,submitted_at=? WHERE report_id=?")
                   ->execute([$title,$summary,$body,$concl,$filePath,$status,$submittedAt,$report['report_id']]);
            } else {
                $db->prepare("INSERT INTO student_reports (user_id,app_id,title,executive_summary,body,conclusion,file_path,status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?)")
                   ->execute([$user['id'],$application['app_id'],$title,$summary,$body,$concl,$filePath,$status,$submittedAt]);
            }
            if ($action === 'submit') {
                $coords = $db->query("SELECT user_id FROM users WHERE role IN ('admin','coordinator')")->fetchAll();
                foreach ($coords as $c) {
                    $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
                       ->execute([$c['user_id'],'Final Report Submitted',$user['name'].' submitted their final attachment report.','info','/admin/reports.php?tab=student_reports']);
                }
                $msg = '✅ Final report submitted successfully!';
            } else {
                $msg = '💾 Draft saved.';
            }
            header('Location: /student_report.php?msg=' . urlencode($msg)); exit();
        }
    }
}
if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// Reload report after save
$reportStmt->execute([$user['id']]);
$report = $reportStmt->fetch();

$pageTitle = 'Final Report';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">📄 Final Attachment Report</div>
<div class="page-sub">Submit your end-of-attachment report for assessment</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err):  ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<?php if (!$application): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2.5rem">
  <p class="text-muted">You need a confirmed attachment placement before submitting your final report.</p>
</div></div>
<?php else: ?>

<!-- Status banner -->
<?php if ($report): ?>
<div class="card" style="margin-bottom:1.25rem">
<div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
  <div>
    <strong>Report Status: </strong>
    <span class="badge badge-<?php echo ['draft'=>'pending','submitted'=>'under_review','reviewed'=>'accepted'][$report['status']]??'pending'; ?>"><?php echo strtoupper($report['status']); ?></span>
    <?php if ($report['submitted_at']): ?><span class="text-muted" style="margin-left:.75rem">Submitted <?php echo date('j M Y H:i',strtotime($report['submitted_at'])); ?></span><?php endif; ?>
  </div>
  <?php if ($report['file_path']): ?><a href="/download.php?id=<?php /* serve report files via download */ echo 0; ?>" class="btn btn-outline btn-sm">📄 View Uploaded File</a><?php endif; ?>
  <?php if ($report['grade']): ?><div><strong>Grade: </strong><span style="color:var(--green);font-weight:700;font-size:1.1rem"><?php echo htmlspecialchars($report['grade']); ?></span></div><?php endif; ?>
</div>
<?php if ($report['feedback']): ?>
<div style="padding:.85rem 1.25rem;border-top:1px solid #eee;background:#d4edda">
  <strong style="font-size:.82rem;color:#155724">📝 Assessor Feedback:</strong><br>
  <span style="font-size:.88rem;color:#155724"><?php echo nl2br(htmlspecialchars($report['feedback'])); ?></span>
</div>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$report || $report['status'] !== 'reviewed'): ?>
<div class="card">
<div class="card-header"><h3><?php echo $report ? '✏️ Edit Your Report' : '📝 Write Your Report'; ?></h3></div>
<div class="card-body">
<form method="POST" enctype="multipart/form-data">
  <?php echo csrf_field(); ?>
  <div class="form-group">
    <label>Report Title *</label>
    <input type="text" name="title" required value="<?php echo htmlspecialchars($report['title']??'Industrial Attachment Report — '.date('Y')); ?>" placeholder="e.g. Industrial Attachment Report — Ministry of Labour and Home Affairs">
  </div>
  <div class="form-group">
    <label>Executive Summary</label>
    <textarea name="executive_summary" rows="4" placeholder="Brief overview of your attachment experience, key highlights, and overall impression..."><?php echo htmlspecialchars($report['executive_summary']??''); ?></textarea>
  </div>
  <div class="form-group">
    <label>Report Body * <span style="color:var(--muted);font-weight:400;font-size:.78rem">(Main content — describe your duties, projects, skills gained, interactions)</span></label>
    <textarea name="body" rows="12" placeholder="Write your full report here. Include:&#10;• Description of the organisation and your department&#10;• Duties and responsibilities assigned&#10;• Projects worked on&#10;• Skills and knowledge gained&#10;• How the attachment relates to your academic programme&#10;• Recommendations..."><?php echo htmlspecialchars($report['body']??''); ?></textarea>
  </div>
  <div class="form-group">
    <label>Conclusion</label>
    <textarea name="conclusion" rows="4" placeholder="Summarise what you learned, how the attachment benefited you, and your recommendations for future students..."><?php echo htmlspecialchars($report['conclusion']??''); ?></textarea>
  </div>
  <div class="form-group">
    <label>Upload Report File (optional — PDF or DOCX, max 20MB)</label>
    <input type="file" name="report_file" accept=".pdf,.docx">
    <?php if ($report && $report['file_path']): ?><p style="font-size:.8rem;color:var(--muted);margin-top:.3rem">Current file: <?php echo htmlspecialchars(basename($report['file_path'])); ?></p><?php endif; ?>
  </div>

  <div style="display:flex;gap:.75rem;margin-top:1rem">
    <button type="submit" name="action" value="save" class="btn btn-outline" style="flex:1">💾 Save Draft</button>
    <button type="submit" name="action" value="submit" class="btn btn-primary" style="flex:1" onclick="return confirm('Submit your final report? You won\'t be able to edit it after submission.')">📤 Submit Final Report</button>
  </div>
</form>
</div>
</div>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2rem">
  <p style="color:var(--green);font-weight:600;font-size:1.05rem">✅ Your report has been reviewed and graded.</p>
  <p class="text-muted" style="margin-top:.5rem">No further edits are allowed.</p>
</div></div>
<?php endif; ?>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
