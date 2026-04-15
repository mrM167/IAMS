<?php
// dashboard.php — Student dashboard (PHP 7.4 compatible)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/mailer.php';
requireLogin();
requireRole('student');

$user = getCurrentUser();
$db   = Database::getInstance();
$tab  = $_GET['tab'] ?? 'home';

// Load all student data
$appStmt = $db->prepare("
    SELECT a.*, o.org_name, o.location as org_location, o.contact_person, o.contact_email, o.contact_phone
    FROM applications a
    LEFT JOIN organisations o ON a.matched_org_id = o.org_id
    WHERE a.user_id = ?
");
$appStmt->execute([$user['id']]);
$application = $appStmt->fetch();

$profile = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$profile->execute([$user['id']]);
$profile = $profile->fetch();

$documents = $db->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$documents->execute([$user['id']]);
$documents = $documents->fetchAll();

$interests = $db->prepare("SELECT j.*, ji.expressed_at FROM job_interests ji JOIN job_posts j ON ji.job_id = j.job_id WHERE ji.user_id = ?");
$interests->execute([$user['id']]);
$interests = $interests->fetchAll();
$myJobIds = array_column($interests, 'job_id');

$jobs = $db->query("SELECT j.*, COUNT(ji.interest_id) as interest_count FROM job_posts j LEFT JOIN job_interests ji ON j.job_id = ji.job_id WHERE j.is_active = 1 GROUP BY j.job_id ORDER BY j.created_at DESC")->fetchAll();

$ucStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$ucStmt->execute([$user['id']]);
$unread = (int)$ucStmt->fetchColumn();

$logbookStmt = $db->prepare("SELECT * FROM logbooks WHERE user_id = ? ORDER BY week_number DESC LIMIT 5");
$logbookStmt->execute([$user['id']]);
$recentLogbooks = $logbookStmt->fetchAll();
$lbCountStmt = $db->prepare("SELECT COUNT(*) FROM logbooks WHERE user_id = ?");
$lbCountStmt->execute([$user['id']]);
$logbookCount = (int)$lbCountStmt->fetchColumn();

$reportStmt = $db->prepare("SELECT * FROM student_reports WHERE user_id = ? LIMIT 1");
$reportStmt->execute([$user['id']]);
$myReport = $reportStmt->fetch();

// Handle POSTs
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Submit application
    if ($action === 'apply') {
        if ($application && $application['status'] !== 'rejected') {
            $err = 'You already have an active application.';
        } else {
            $data = [
                'full_name'          => trim($_POST['full_name'] ?? $user['name']),
                'student_number'     => trim($_POST['student_number'] ?? ''),
                'programme'          => trim($_POST['programme'] ?? ''),
                'year_of_study'      => (int)($_POST['year_of_study'] ?? 0),
                'skills'             => trim($_POST['skills'] ?? ''),
                'preferred_location' => trim($_POST['preferred_location'] ?? ''),
                'cover_letter'       => trim($_POST['cover_letter'] ?? ''),
            ];
            if (!$data['student_number'] || !$data['programme']) {
                $err = 'Student number and programme are required.';
            } else {
                if ($application) $db->prepare("DELETE FROM applications WHERE user_id = ?")->execute([$user['id']]);
                $db->prepare("INSERT INTO applications (user_id, full_name, student_number, programme, year_of_study, skills, preferred_location, cover_letter) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                   ->execute([$user['id'], $data['full_name'], $data['student_number'], $data['programme'], $data['year_of_study'], $data['skills'], $data['preferred_location'], $data['cover_letter']]);
                $coords = $db->query("SELECT user_id FROM users WHERE role IN ('admin','coordinator') AND is_active = 1")->fetchAll();
                foreach ($coords as $c) {
                    $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)")
                       ->execute([$c['user_id'], 'New Application', $user['name'] . ' submitted an attachment application.', 'info', '/admin/applications.php']);
                }
                header('Location: /dashboard.php?tab=home&msg=applied'); exit();
            }
        }
    }

    // Update profile
    if ($action === 'profile') {
        $db->prepare("UPDATE student_profiles SET linkedin_url = ?, github_url = ?, portfolio_url = ?, skills = ?, bio = ? WHERE user_id = ?")
           ->execute([trim($_POST['linkedin'] ?? ''), trim($_POST['github'] ?? ''), trim($_POST['portfolio'] ?? ''), trim($_POST['skills'] ?? ''), trim($_POST['bio'] ?? ''), $user['id']]);
        header('Location: /dashboard.php?tab=profile&msg=saved'); exit();
    }

    // Express interest
    if ($action === 'interest') {
        $job_id = (int)($_POST['job_id'] ?? 0);
        try {
            $db->prepare("INSERT IGNORE INTO job_interests (user_id, job_id) VALUES (?, ?)")->execute([$user['id'], $job_id]);
        } catch (Exception $e) {}
        header('Location: /dashboard.php?tab=jobs'); exit();
    }

    // Document upload
    if ($action === 'upload' && isset($_FILES['document'])) {
        $file    = $_FILES['document'];
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'heic'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $err = 'Invalid file type. Allowed: PDF, JPG, PNG, DOCX.';
        } elseif ($file['size'] > 10485760) {
            $err = 'File too large. Maximum 10MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $err = 'Upload failed. Please try again.';
        } else {
            $dir = __DIR__ . '/uploads/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = $user['id'] . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                $mimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'heic' => 'image/heic'];
                $db->prepare("INSERT INTO documents (user_id, doc_type, filename, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)")
                   ->execute([$user['id'], $_POST['doc_type'] ?? 'Other', $file['name'], 'uploads/' . $fname, $file['size'], $mimes[$ext] ?? 'application/octet-stream']);
                header('Location: /dashboard.php?tab=docs&msg=uploaded'); exit();
            } else {
                $err = 'Could not save file. Check server permissions.';
            }
        }
    }

    // Mark all notifications read
    if ($action === 'read_notif') {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);
        header('Location: /dashboard.php?tab=notifs'); exit();
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['applied' => 'Application submitted successfully!', 'saved' => 'Profile saved!', 'uploaded' => 'Document uploaded!'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

$statusBadge = ['pending' => 'badge-pending', 'under_review' => 'badge-under_review', 'matched' => 'badge-matched', 'accepted' => 'badge-accepted', 'rejected' => 'badge-rejected'];
$pageTitle = 'My Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="page-wrap">

<?php if ($msg): ?><div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err):  ?><div class="alert alert-error">&#9888; <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<!-- Tab nav -->
<div style="display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.5rem;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <?php
  $tabs = ['home' => '&#127968; Home', 'apply' => '&#128203; Apply', 'docs' => '&#128193; Documents', 'jobs' => '&#128188; Jobs', 'profile' => '&#128100; Profile', 'logbook' => '&#128221; Logbook', 'report' => '&#128196; Final Report', 'notifs' => '&#128276; Notifications'];
  foreach ($tabs as $t => $label):
    $isActive = $tab === $t;
    $badge = ($t === 'notifs' && $unread > 0) ? ' <span style="background:#c0392b;color:#fff;border-radius:50%;padding:.05rem .3rem;font-size:.65rem">' . $unread . '</span>' : '';
  ?>
  <a href="?tab=<?php echo $t; ?>" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.84rem;font-weight:600;<?php echo $isActive ? 'background:var(--navy);color:#fff' : 'color:var(--muted)'; ?>"><?php echo $label . $badge; ?></a>
  <?php endforeach; ?>
</div>

<!-- HOME -->
<?php if ($tab === 'home'): ?>
<div class="page-title">Welcome back, <?php echo htmlspecialchars($user['name']); ?> &#128075;</div>
<div class="page-sub">University of Botswana — IAMS Student Portal</div>

<div class="stats-grid">
  <div class="stat-card <?php echo $application ? 'green' : 'gold'; ?>">
    <div class="stat-label">Application</div>
    <div style="margin-top:.4rem">
      <?php if ($application): ?>
        <span class="badge badge-<?php echo $application['status']; ?>"><?php echo strtoupper(str_replace('_', ' ', $application['status'])); ?></span>
      <?php else: ?><span style="font-size:.85rem;color:var(--muted)">Not submitted</span><?php endif; ?>
    </div>
  </div>
  <div class="stat-card"><div class="stat-label">Documents</div><div class="stat-num"><?php echo count($documents); ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Logbooks</div><div class="stat-num"><?php echo $logbookCount; ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Job Interests</div><div class="stat-num"><?php echo count($interests); ?></div></div>
</div>

<?php if ($application): ?>
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-header">
    <h3>&#128203; Your Application</h3>
    <span class="badge badge-<?php echo $application['status']; ?>"><?php echo strtoupper(str_replace('_', ' ', $application['status'])); ?></span>
  </div>
  <div class="card-body">
    <div class="grid-2">
      <div><strong>Programme:</strong> <?php echo htmlspecialchars($application['programme']); ?></div>
      <div><strong>Student #:</strong> <?php echo htmlspecialchars($application['student_number']); ?></div>
      <div><strong>Preferred Location:</strong> <?php echo htmlspecialchars($application['preferred_location'] ?: '—'); ?></div>
      <div><strong>Submitted:</strong> <?php echo date('j M Y', strtotime($application['submission_date'])); ?></div>
    </div>
    <?php if ($application['review_notes']): ?>
    <div style="margin-top:.75rem;padding:.75rem;background:#fff3cd;border-radius:7px">
      <strong>Coordinator Note:</strong> <?php echo htmlspecialchars($application['review_notes']); ?>
    </div>
    <?php endif; ?>
    <?php if (in_array($application['status'], ['matched', 'accepted']) && $application['org_name']): ?>
    <div style="margin-top:.75rem;padding:1rem;background:#d4edda;border-radius:8px;color:#155724">
      &#127881; <strong>Placed at: <?php echo htmlspecialchars($application['org_name']); ?></strong>
      (<?php echo htmlspecialchars($application['org_location'] ?? ''); ?>)<br>
      <?php if ($application['contact_person']): ?><span style="font-size:.85rem">Contact: <?php echo htmlspecialchars($application['contact_person']); ?> — <?php echo htmlspecialchars($application['contact_email'] ?? ''); ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body" style="text-align:center;padding:2rem">
    <p style="font-size:1.05rem;margin-bottom:1rem">You haven't submitted an application yet.</p>
    <a href="?tab=apply" class="btn btn-primary">Apply for Attachment &rarr;</a>
  </div>
</div>
<?php endif; ?>

<div class="grid-3">
  <a href="?tab=docs" style="text-decoration:none">
    <div class="card" style="text-align:center;padding:1.25rem;cursor:pointer">
      <div style="font-size:2rem">&#128193;</div>
      <div style="font-weight:600;color:var(--navy);margin-top:.5rem">Upload Documents</div>
      <div class="text-muted"><?php echo count($documents); ?> uploaded</div>
    </div>
  </a>
  <a href="?tab=logbook" style="text-decoration:none">
    <div class="card" style="text-align:center;padding:1.25rem;cursor:pointer">
      <div style="font-size:2rem">&#128221;</div>
      <div style="font-weight:600;color:var(--navy);margin-top:.5rem">Weekly Logbook</div>
      <div class="text-muted"><?php echo $logbookCount; ?> entr<?php echo $logbookCount === 1 ? 'y' : 'ies'; ?></div>
    </div>
  </a>
  <a href="?tab=report" style="text-decoration:none">
    <div class="card" style="text-align:center;padding:1.25rem;cursor:pointer">
      <div style="font-size:2rem">&#128196;</div>
      <div style="font-weight:600;color:var(--navy);margin-top:.5rem">Final Report</div>
      <div class="text-muted"><?php echo $myReport ? strtoupper($myReport['status']) : 'Not started'; ?></div>
    </div>
  </a>
</div>

<!-- APPLY -->
<?php elseif ($tab === 'apply'): ?>
<div class="page-title">&#128203; Attachment Application</div>
<div class="page-sub">Submit your application to the Ministry of Labour and Home Affairs</div>

<?php if ($application && $application['status'] !== 'rejected'): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2rem">
  <p>You have an active application: <span class="badge badge-<?php echo $application['status']; ?>"><?php echo strtoupper(str_replace('_', ' ', $application['status'])); ?></span></p>
  <p class="text-muted" style="margin-top:.5rem">Contact your coordinator to make changes.</p>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body">
<form method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="apply">
  <div class="grid-2">
    <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['name']); ?>"></div>
    <div class="form-group"><label>Student Number *</label><input type="text" name="student_number" required placeholder="e.g. 202200960"></div>
  </div>
  <div class="grid-2">
    <div class="form-group"><label>Programme of Study *</label><input type="text" name="programme" required placeholder="e.g. BSc Computer Science"></div>
    <div class="form-group"><label>Year of Study</label>
      <select name="year_of_study">
        <?php for ($y = 1; $y <= 6; $y++): ?><option value="<?php echo $y; ?>" <?php echo ($profile['year_of_study'] ?? 0) == $y ? 'selected' : ''; ?>>Year <?php echo $y; ?></option><?php endfor; ?>
      </select>
    </div>
  </div>
  <div class="form-group"><label>Skills (comma separated)</label><input type="text" name="skills" value="<?php echo htmlspecialchars($profile['skills'] ?? ''); ?>" placeholder="PHP, Python, Data Analysis..."></div>
  <div class="form-group"><label>Preferred Location</label><input type="text" name="preferred_location" placeholder="Gaborone, Francistown..."></div>
  <div class="form-group"><label>Cover Letter / Motivation</label><textarea name="cover_letter" rows="6" placeholder="Tell us about yourself and why you want this attachment..."></textarea></div>
  <button type="submit" class="btn btn-primary">Submit Application &rarr;</button>
</form>
</div></div>
<?php endif; ?>

<!-- DOCUMENTS -->
<?php elseif ($tab === 'docs'): ?>
<div class="page-title">&#128193; Documents</div>
<div class="page-sub">Upload your CV, transcripts, and supporting documents (max 10MB each)</div>
<div class="grid-2" style="align-items:start">
  <div class="card">
    <div class="card-header"><h3>Upload New Document</h3></div>
    <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="upload">
      <div class="form-group"><label>Document Type *</label>
        <select name="doc_type" required>
          <?php foreach (['CV/Resume', 'Academic Transcript', 'ID Copy', 'Certificate', 'Reference Letter', 'Other'] as $t): ?>
          <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>File (PDF, JPG, PNG, DOCX — max 10MB) *</label><input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.docx,.heic"></div>
      <button type="submit" class="btn btn-primary">Upload</button>
    </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Uploaded Documents (<?php echo count($documents); ?>)</h3></div>
    <?php if ($documents): ?>
    <table><thead><tr><th>Type</th><th>Filename</th><th>Size</th><th>Date</th><th></th></tr></thead><tbody>
    <?php foreach ($documents as $d): ?>
    <tr>
      <td><span style="background:#e8f0fe;color:#1a3a6a;padding:.2rem .55rem;border-radius:4px;font-size:.75rem;font-weight:700"><?php echo htmlspecialchars($d['doc_type']); ?></span></td>
      <td style="font-size:.82rem"><?php echo htmlspecialchars($d['filename']); ?></td>
      <td class="text-muted" style="font-size:.78rem"><?php echo $d['file_size'] ? round($d['file_size'] / 1024) . 'KB' : '—'; ?></td>
      <td class="text-muted" style="font-size:.78rem"><?php echo date('j M Y', strtotime($d['uploaded_at'])); ?></td>
      <td><a href="/download.php?id=<?php echo $d['doc_id']; ?>" class="btn btn-primary btn-sm" target="_blank">&#11015;</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="card-body"><p class="text-muted">No documents uploaded yet.</p></div><?php endif; ?>
  </div>
</div>

<!-- JOBS -->
<?php elseif ($tab === 'jobs'): ?>
<div class="page-title">&#128188; Available Positions</div>
<div class="page-sub">Browse and express interest in attachment opportunities</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
<?php foreach ($jobs as $job): ?>
<div class="card">
  <div style="padding:1.25rem">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--teal);margin-bottom:.35rem"><?php echo htmlspecialchars($job['organization']); ?></div>
    <h3 style="font-size:1rem;color:var(--navy);margin-bottom:.5rem"><?php echo htmlspecialchars($job['title']); ?></h3>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.75rem">
      <span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem;color:var(--muted)">&#128205; <?php echo htmlspecialchars($job['location'] ?? '—'); ?></span>
      <?php if ($job['duration']): ?><span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem;color:var(--muted)">&#8987; <?php echo htmlspecialchars($job['duration']); ?></span><?php endif; ?>
      <?php if ($job['salary_range']): ?><span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem;color:var(--muted)">&#128176; <?php echo htmlspecialchars($job['salary_range']); ?></span><?php endif; ?>
      <span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem;color:var(--muted)">&#128101; <?php echo (int)$job['slots']; ?> slot(s)</span>
    </div>
    <?php if ($job['description']): ?><p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem;line-height:1.5"><?php echo htmlspecialchars(substr($job['description'], 0, 110)) . (strlen($job['description']) > 110 ? '...' : ''); ?></p><?php endif; ?>
    <?php if (in_array($job['job_id'], $myJobIds)): ?>
      <span class="badge badge-accepted">&#10003; Interest Expressed</span>
    <?php else: ?>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="interest">
        <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
        <button type="submit" class="btn btn-primary" style="width:100%">Express Interest</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$jobs): ?><p class="text-muted">No positions available yet.</p><?php endif; ?>
</div>

<!-- PROFILE -->
<?php elseif ($tab === 'profile'): ?>
<div class="page-title">&#128100; My Profile</div>
<div class="page-sub">Update your professional links and skills</div>
<div class="card" style="max-width:640px">
<div class="card-body">
<form method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="profile">
  <div class="form-group"><label>LinkedIn Profile URL</label><input type="url" name="linkedin" value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>" placeholder="https://linkedin.com/in/username"></div>
  <div class="form-group"><label>GitHub Profile URL</label><input type="url" name="github" value="<?php echo htmlspecialchars($profile['github_url'] ?? ''); ?>" placeholder="https://github.com/username"></div>
  <div class="form-group"><label>Portfolio Website</label><input type="url" name="portfolio" value="<?php echo htmlspecialchars($profile['portfolio_url'] ?? ''); ?>" placeholder="https://myportfolio.com"></div>
  <div class="form-group"><label>Skills Summary</label><input type="text" name="skills" value="<?php echo htmlspecialchars($profile['skills'] ?? ''); ?>" placeholder="PHP, Python, Data Analysis..."></div>
  <div class="form-group"><label>Short Bio</label><textarea name="bio" rows="4" placeholder="Brief professional summary..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea></div>
  <button type="submit" class="btn btn-primary">Save Profile</button>
</form>
</div></div>

<!-- LOGBOOK -->
<?php elseif ($tab === 'logbook'): ?>
<div class="page-title">&#128221; Weekly Logbook</div>
<div class="page-sub">Record your weekly activities — <a href="/logbook.php" style="color:var(--teal);font-weight:600">Open full logbook page &rarr;</a></div>
<?php if (!$application || !in_array($application['status'], ['matched', 'accepted'])): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2rem"><p class="text-muted">You need a confirmed placement before submitting logbooks.</p></div></div>
<?php else: ?>
<div class="card">
<div class="card-header"><h3>Recent Logbook Entries</h3><a href="/logbook.php" class="btn btn-primary btn-sm">+ New Entry</a></div>
<?php if ($recentLogbooks): ?>
<table><thead><tr><th>Week</th><th>Start Date</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
<?php foreach ($recentLogbooks as $lb): ?>
<tr>
  <td><strong>Week <?php echo $lb['week_number']; ?></strong></td>
  <td class="text-muted"><?php echo date('j M Y', strtotime($lb['week_start_date'])); ?></td>
  <td><span class="badge badge-<?php echo $lb['status'] === 'submitted' ? 'under_review' : ($lb['status'] === 'reviewed' ? 'accepted' : ($lb['status'] === 'late' ? 'rejected' : 'pending')); ?>"><?php echo strtoupper($lb['status']); ?></span></td>
  <td class="text-muted" style="font-size:.8rem"><?php echo $lb['submitted_at'] ? date('j M Y', strtotime($lb['submitted_at'])) : '—'; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><div class="card-body"><p class="text-muted">No logbook entries yet. <a href="/logbook.php">Start Week 1 &rarr;</a></p></div><?php endif; ?>
</div>
<?php endif; ?>

<!-- FINAL REPORT -->
<?php elseif ($tab === 'report'): ?>
<div class="page-title">&#128196; Final Report</div>
<div class="page-sub">Submit your end-of-attachment report — <a href="/student_report.php" style="color:var(--teal);font-weight:600">Open full report page &rarr;</a></div>
<?php if ($myReport): ?>
<div class="card"><div class="card-body">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
    <div>
      <strong><?php echo htmlspecialchars($myReport['title']); ?></strong><br>
      <span class="text-muted">Status: <span class="badge badge-<?php echo ['draft' => 'pending', 'submitted' => 'under_review', 'reviewed' => 'accepted'][$myReport['status']] ?? 'pending'; ?>"><?php echo strtoupper($myReport['status']); ?></span></span>
    </div>
    <?php if ($myReport['grade']): ?><div style="font-size:1.5rem;font-weight:700;color:var(--green)">Grade: <?php echo htmlspecialchars($myReport['grade']); ?></div><?php endif; ?>
    <a href="/student_report.php" class="btn btn-primary">View / Edit</a>
  </div>
  <?php if ($myReport['feedback']): ?>
  <div style="margin-top:.75rem;padding:.85rem;background:#d4edda;border-radius:7px;color:#155724"><strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($myReport['feedback'])); ?></div>
  <?php endif; ?>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2rem">
  <p class="text-muted" style="margin-bottom:1rem">You haven't started your final report yet.</p>
  <a href="/student_report.php" class="btn btn-primary">Start Final Report &rarr;</a>
</div></div>
<?php endif; ?>

<!-- NOTIFICATIONS -->
<?php elseif ($tab === 'notifs'): ?>
<div class="page-title">&#128276; Notifications</div>
<div class="page-sub"><a href="/notifications.php" style="color:var(--teal);font-weight:600">Open full notification centre &rarr;</a></div>
<?php
$notifStmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$notifStmt->execute([$user['id']]);
$notifs = $notifStmt->fetchAll();
?>
<?php if ($unread): ?>
<div style="margin-bottom:.75rem">
  <form method="POST"><input type="hidden" name="action" value="read_notif"><?php echo csrf_field(); ?>
  <button type="submit" class="btn btn-outline btn-sm">Mark all as read</button></form>
</div>
<?php endif; ?>
<?php if ($notifs): ?>
<div class="card">
<?php foreach ($notifs as $n): ?>
<div style="padding:.85rem 1.25rem;border-bottom:1px solid #f5f5f5;display:flex;gap:.85rem;align-items:flex-start;background:<?php echo !$n['is_read'] ? '#fffbec' : '#fff'; ?>">
  <span style="font-size:1.2rem"><?php
    if ($n['type'] === 'success') echo '&#10003;';
    elseif ($n['type'] === 'warning') echo '&#9888;&#65039;';
    elseif ($n['type'] === 'deadline') echo '&#128197;';
    else echo 'ℹ️';
  ?></span>
  <div style="flex:1">
    <div style="font-weight:<?php echo !$n['is_read'] ? '700' : '600'; ?>;font-size:.88rem;color:var(--navy)"><?php echo htmlspecialchars($n['title']); ?></div>
    <div style="font-size:.82rem;color:var(--muted)"><?php echo htmlspecialchars($n['message']); ?></div>
    <div style="font-size:.75rem;color:#9ca3af;margin-top:.2rem"><?php echo date('j M Y H:i', strtotime($n['created_at'])); ?></div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?><div class="card"><div class="card-body"><p class="text-muted">No notifications.</p></div></div><?php endif; ?>

<?php endif; ?>
</div>
<footer class="site-footer">IAMS &copy; <?php echo date('Y'); ?> — University of Botswana &middot; Ministry of Labour &amp; Home Affairs</footer>
</body></html>