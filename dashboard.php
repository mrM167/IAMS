<?php
// dashboard.php — Student dashboard (US-02, US-04, US-08)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();
requireRole('student');

$user = getCurrentUser();
$db   = Database::getInstance();
$tab  = $_GET['tab'] ?? 'home';

// Load all student data
$application = $db->prepare("SELECT a.*, o.org_name, o.location as org_location FROM applications a LEFT JOIN organisations o ON a.matched_org_id=o.org_id WHERE a.user_id=?");
$application->execute([$user['id']]); $application = $application->fetch();

$profile = $db->prepare("SELECT * FROM student_profiles WHERE user_id=?");
$profile->execute([$user['id']]); $profile = $profile->fetch();

$documents = $db->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY uploaded_at DESC");
$documents->execute([$user['id']]); $documents = $documents->fetchAll();

$interests = $db->prepare("SELECT j.*,ji.expressed_at FROM job_interests ji JOIN job_posts j ON ji.job_id=j.job_id WHERE ji.user_id=?");
$interests->execute([$user['id']]); $interests = $interests->fetchAll();

$jobs = $db->query("SELECT j.*,COUNT(ji.interest_id) as interest_count FROM job_posts j LEFT JOIN job_interests ji ON j.job_id=ji.job_id WHERE j.is_active=1 GROUP BY j.job_id ORDER BY j.created_at DESC")->fetchAll();

$notifications = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$notifications->execute([$user['id']]); $notifications = $notifications->fetchAll();
$unread_notifs = array_sum(array_column($notifications,'is_read')==0?[1]:[0]);

// Handle POST actions
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
                if ($application) {
                    $db->prepare("DELETE FROM applications WHERE user_id=?")->execute([$user['id']]);
                }
                $db->prepare("INSERT INTO applications (user_id,full_name,student_number,programme,year_of_study,skills,preferred_location,cover_letter)
                              VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$user['id'],$data['full_name'],$data['student_number'],$data['programme'],
                              $data['year_of_study'],$data['skills'],$data['preferred_location'],$data['cover_letter']]);
                // Notify coordinators
                $coords = $db->query("SELECT user_id FROM users WHERE role IN ('admin','coordinator')")->fetchAll();
                foreach ($coords as $c) {
                    $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
                       ->execute([$c['user_id'],'New Application','Student '.$user['name'].' submitted an attachment application.','info','/admin/applications.php']);
                }
                $msg = 'Application submitted successfully!';
                header('Location: /dashboard.php?tab=home&msg=applied'); exit();
            }
        }
    }

    // Update profile links
    if ($action === 'profile') {
        $db->prepare("UPDATE student_profiles SET linkedin_url=?,github_url=?,portfolio_url=?,skills=?,bio=? WHERE user_id=?")
           ->execute([
               trim($_POST['linkedin'] ?? ''), trim($_POST['github'] ?? ''),
               trim($_POST['portfolio'] ?? ''), trim($_POST['skills'] ?? ''),
               trim($_POST['bio'] ?? ''), $user['id']
           ]);
        $msg = 'Profile updated!';
        header('Location: /dashboard.php?tab=profile&msg=saved'); exit();
    }

    // Express interest in job
    if ($action === 'interest') {
        $job_id = (int)($_POST['job_id'] ?? 0);
        try {
            $db->prepare("INSERT IGNORE INTO job_interests (user_id,job_id) VALUES (?,?)")->execute([$user['id'],$job_id]);
            $msg = 'Interest recorded!';
        } catch (Exception $e) { $err = 'Could not record interest.'; }
        header('Location: /dashboard.php?tab=jobs'); exit();
    }

    // Document upload
    if ($action === 'upload' && isset($_FILES['document'])) {
        $file    = $_FILES['document'];
        $allowed = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext]))         { $err = 'Invalid file type. Allowed: PDF, JPG, PNG, DOCX.'; }
        elseif ($file['size'] > 10485760)   { $err = 'File too large. Max 10MB.'; }
        elseif ($file['error'] !== UPLOAD_ERR_OK) { $err = 'Upload failed. Please try again.'; }
        else {
            $dir = __DIR__ . '/uploads/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = $user['id'] . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                $db->prepare("INSERT INTO documents (user_id,doc_type,filename,file_path,file_size,mime_type) VALUES (?,?,?,?,?,?)")
                   ->execute([$user['id'],$_POST['doc_type'],$file['name'],'uploads/'.$fname,$file['size'],$allowed[$ext]??'application/octet-stream']);
                $msg = 'Document uploaded!';
            } else { $err = 'Could not save file.'; }
        }
        header('Location: /dashboard.php?tab=docs' . ($err?'':'&msg=uploaded')); exit();
    }

    // Mark notifications read
    if ($action === 'read_notif') {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
        header('Location: /dashboard.php?tab=notifs'); exit();
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['applied'=>'Application submitted!','saved'=>'Profile saved!','uploaded'=>'Document uploaded!'];
    $msg = $msgs[$_GET['msg']] ?? '';
}

$statusColors = ['pending'=>'badge-pending','under_review'=>'badge-under_review','matched'=>'badge-matched','accepted'=>'badge-accepted','rejected'=>'badge-rejected'];
$pageTitle = 'Student Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="page-wrap">

<?php if ($msg): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">⚠️ <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<!-- Tab Nav -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <?php foreach(['home'=>'🏠 Home','apply'=>'📋 Apply','docs'=>'📁 Documents','jobs'=>'💼 Jobs','profile'=>'👤 Profile','notifs'=>'🔔 Notifications'] as $t=>$label): ?>
  <a href="?tab=<?php echo $t; ?>" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab===$t?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>">
    <?php echo $label; ?><?php if($t==='notifs'){$un=$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");$un->execute([$user['id']]);$un=(int)$un->fetchColumn();if($un):?><span style="background:#c0392b;color:#fff;border-radius:50%;padding:.05rem .3rem;font-size:.65rem;margin-left:.3rem"><?php echo $un;?></span><?php endif;}?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ═══════ HOME TAB ═══════ -->
<?php if ($tab === 'home'): ?>
<div class="page-title">Welcome, <?php echo htmlspecialchars($user['name']); ?> 👋</div>
<div class="page-sub">University of Botswana — Internship & Attachment Management System</div>

<div class="stats-grid">
  <div class="stat-card <?php echo $application ? 'green' : 'gold'; ?>">
    <div class="stat-label">Application Status</div>
    <div class="stat-num" style="font-size:1rem;margin-top:.25rem">
      <?php if ($application): ?>
        <span class="badge badge-<?php echo $application['status']; ?>"><?php echo strtoupper(str_replace('_',' ',$application['status'])); ?></span>
      <?php else: ?><span style="font-size:.85rem;color:var(--muted)">Not submitted</span><?php endif; ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Documents Uploaded</div>
    <div class="stat-num"><?php echo count($documents); ?></div>
  </div>
  <div class="stat-card teal">
    <div class="stat-label">Job Interests</div>
    <div class="stat-num"><?php echo count($interests); ?></div>
  </div>
  <?php if ($application && $application['matched_org_id']): ?>
  <div class="stat-card green">
    <div class="stat-label">Matched Organisation</div>
    <div class="stat-num" style="font-size:.9rem"><?php echo htmlspecialchars($application['org_name']); ?></div>
  </div>
  <?php endif; ?>
</div>

<?php if ($application): ?>
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><h3>📋 Your Application</h3><span class="badge badge-<?php echo $application['status']; ?>"><?php echo strtoupper(str_replace('_',' ',$application['status'])); ?></span></div>
  <div class="card-body">
    <div class="grid-2">
      <div><b>Programme:</b> <?php echo htmlspecialchars($application['programme']); ?></div>
      <div><b>Student #:</b> <?php echo htmlspecialchars($application['student_number']); ?></div>
      <div><b>Preferred Location:</b> <?php echo htmlspecialchars($application['preferred_location'] ?: '—'); ?></div>
      <div><b>Submitted:</b> <?php echo date('j M Y', strtotime($application['submission_date'])); ?></div>
    </div>
    <?php if ($application['review_notes']): ?>
    <div style="margin-top:.75rem;padding:.75rem;background:#f8f9fb;border-radius:7px">
      <b>Coordinator Note:</b> <?php echo htmlspecialchars($application['review_notes']); ?>
    </div>
    <?php endif; ?>
    <?php if ($application['status'] === 'matched' || $application['status'] === 'accepted'): ?>
    <div style="margin-top:.75rem;padding:1rem;background:#d4edda;border-radius:7px;color:#155724">
      🎉 <b>You have been matched to: <?php echo htmlspecialchars($application['org_name']); ?></b> (<?php echo htmlspecialchars($application['org_location']); ?>)
    </div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-body" style="text-align:center;padding:2rem">
    <p style="font-size:1.1rem;margin-bottom:1rem">📋 You haven't submitted an application yet.</p>
    <a href="?tab=apply" class="btn btn-primary">Apply for Attachment →</a>
  </div>
</div>
<?php endif; ?>

<!-- Recent notifications -->
<?php if ($notifications): ?>
<div class="card">
  <div class="card-header"><h3>🔔 Recent Notifications</h3><a href="?tab=notifs" style="font-size:.8rem;color:var(--teal)">View all</a></div>
  <?php foreach (array_slice($notifications,0,4) as $n): ?>
  <div style="padding:.75rem 1.25rem;border-bottom:1px solid #f5f5f5;<?php echo !$n['is_read']?'background:#fffbec':''; ?>">
    <div style="font-weight:600;font-size:.88rem"><?php echo htmlspecialchars($n['title']); ?></div>
    <div style="font-size:.82rem;color:var(--muted)"><?php echo htmlspecialchars($n['message']); ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══════ APPLY TAB ═══════ -->
<?php elseif ($tab === 'apply'): ?>
<div class="page-title">📋 Attachment Application</div>
<div class="page-sub">Submit your application to the Ministry of Labour and Home Affairs</div>

<?php if ($application && $application['status'] !== 'rejected'): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2rem">
  <p>You already have an application with status: <span class="badge badge-<?php echo $application['status']; ?>"><?php echo strtoupper(str_replace('_',' ',$application['status'])); ?></span></p>
  <p style="margin-top:.75rem;color:var(--muted)">Contact your coordinator to make changes.</p>
</div></div>
<?php else: ?>
<div class="card"><div class="card-body">
<form method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="apply">
  <div class="grid-2">
    <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['name']); ?>"></div>
    <div class="form-group"><label>Student Number *</label><input type="text" name="student_number" required value="<?php echo htmlspecialchars($_SESSION['student_number'] ?? ''); ?>" placeholder="e.g. 202200960">
    </div>
  </div>
  <div class="grid-2">
    <div class="form-group"><label>Programme of Study *</label><input type="text" name="programme" required placeholder="e.g. BSc Computer Science"></div>
    <div class="form-group"><label>Year of Study</label>
      <select name="year_of_study">
        <?php for($y=1;$y<=6;$y++): ?><option value="<?php echo $y; ?>" <?php echo ($profile['year_of_study']??0)==$y?'selected':''; ?>>Year <?php echo $y; ?></option><?php endfor; ?>
      </select>
    </div>
  </div>
  <div class="form-group"><label>Skills (comma separated)</label><input type="text" name="skills" value="<?php echo htmlspecialchars($profile['skills'] ?? ''); ?>" placeholder="PHP, Python, Data Analysis, Microsoft Office..."></div>
  <div class="form-group"><label>Preferred Location</label><input type="text" name="preferred_location" placeholder="Gaborone, Francistown, Lobatse..."></div>
  <div class="form-group"><label>Cover Letter / Motivation</label><textarea name="cover_letter" rows="5" placeholder="Tell us why you are interested in this attachment programme and what you hope to achieve..."></textarea></div>
  <button type="submit" class="btn btn-primary">Submit Application to MLHA →</button>
</form>
</div></div>
<?php endif; ?>

<!-- ═══════ DOCS TAB ═══════ -->
<?php elseif ($tab === 'docs'): ?>
<div class="page-title">📁 Documents</div>
<div class="page-sub">Upload your CV, transcripts, and supporting documents</div>
<div class="grid-2" style="align-items:start">
  <div class="card">
    <div class="card-header"><h3>Upload Document</h3></div>
    <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="upload">
      <div class="form-group"><label>Document Type *</label>
        <select name="doc_type" required>
          <?php foreach(['CV/Resume','Academic Transcript','ID Copy','Certificate','Reference Letter','Other'] as $t): ?>
          <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>File (PDF, JPEG, PNG, DOCX — max 10MB) *</label>
        <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.docx">
      </div>
      <button type="submit" class="btn btn-primary">Upload Document</button>
    </form>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>Uploaded Documents (<?php echo count($documents); ?>)</h3></div>
    <?php if ($documents): ?>
    <table><thead><tr><th>Type</th><th>File</th><th>Date</th><th></th></tr></thead><tbody>
    <?php foreach ($documents as $d): ?>
    <tr>
      <td><span class="badge" style="background:#e8f0fe;color:#1a3a6a"><?php echo htmlspecialchars($d['doc_type']); ?></span></td>
      <td style="font-size:.8rem;color:var(--muted)"><?php echo htmlspecialchars($d['filename']); ?></td>
      <td style="font-size:.78rem;color:var(--muted)"><?php echo date('j M Y', strtotime($d['uploaded_at'])); ?></td>
      <td><a href="/download.php?id=<?php echo $d['doc_id']; ?>" class="btn btn-primary btn-sm">⬇</a></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="card-body"><p class="text-muted">No documents uploaded yet.</p></div><?php endif; ?>
  </div>
</div>

<!-- ═══════ JOBS TAB ═══════ -->
<?php elseif ($tab === 'jobs'): ?>
<div class="page-title">💼 Available Positions</div>
<div class="page-sub">Browse and express interest in attachment opportunities</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
<?php $myJobIds = array_column($interests,'job_id'); ?>
<?php foreach ($jobs as $job): ?>
<div class="card">
  <div style="padding:1.25rem">
    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--teal);margin-bottom:.35rem"><?php echo htmlspecialchars($job['organization']); ?></div>
    <h3 style="font-size:1rem;color:var(--navy);margin-bottom:.5rem"><?php echo htmlspecialchars($job['title']); ?></h3>
    <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.75rem">
      <span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem">📍 <?php echo htmlspecialchars($job['location']); ?></span>
      <?php if($job['duration']): ?><span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem">⏱ <?php echo htmlspecialchars($job['duration']); ?></span><?php endif; ?>
      <?php if($job['salary_range']): ?><span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem">💰 <?php echo htmlspecialchars($job['salary_range']); ?></span><?php endif; ?>
      <span style="background:var(--light);padding:.2rem .5rem;border-radius:4px;font-size:.78rem">👥 <?php echo $job['slots']; ?> slot(s)</span>
    </div>
    <?php if($job['description']): ?><p style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem"><?php echo htmlspecialchars(substr($job['description'],0,100)).(strlen($job['description'])>100?'...':''); ?></p><?php endif; ?>
    <?php if (in_array($job['job_id'], $myJobIds)): ?>
      <span class="badge badge-accepted">✓ Interest Expressed</span>
    <?php else: ?>
      <form method="POST" style="display:inline">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="interest">
        <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
        <button type="submit" class="btn btn-primary" style="width:100%">Express Interest</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$jobs): ?><p class="text-muted">No positions available yet. Check back soon.</p><?php endif; ?>
</div>

<!-- ═══════ PROFILE TAB ═══════ -->
<?php elseif ($tab === 'profile'): ?>
<div class="page-title">👤 My Profile</div>
<div class="page-sub">Update your professional links and skills summary</div>
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

<!-- ═══════ NOTIFICATIONS TAB ═══════ -->
<?php elseif ($tab === 'notifs'): ?>
<div class="page-title">🔔 Notifications</div>
<div class="page-sub">System messages and updates</div>
<?php if ($notifications): ?>
<div style="margin-bottom:1rem">
  <form method="POST"><input type="hidden" name="action" value="read_notif"><?php echo csrf_field(); ?>
  <button type="submit" class="btn btn-outline btn-sm">Mark all as read</button></form>
</div>
<div class="card">
<?php foreach ($notifications as $n): ?>
<div style="padding:1rem 1.25rem;border-bottom:1px solid #f5f5f5;display:flex;gap:1rem;align-items:flex-start;<?php echo !$n['is_read']?'background:#fffbec':''; ?>">
  <span style="font-size:1.2rem"><?php echo match($n['type']){'success'=>'✅','warning'=>'⚠️','deadline'=>'📅',default=>'ℹ️'}; ?></span>
  <div>
    <div style="font-weight:600;font-size:.9rem;margin-bottom:.2rem"><?php echo htmlspecialchars($n['title']); ?></div>
    <div style="font-size:.83rem;color:var(--muted)"><?php echo htmlspecialchars($n['message']); ?></div>
    <div style="font-size:.75rem;color:#9ca3af;margin-top:.25rem"><?php echo date('j M Y H:i', strtotime($n['created_at'])); ?></div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?><div class="card"><div class="card-body"><p class="text-muted">No notifications yet.</p></div></div><?php endif; ?>
<?php endif; ?>

</div><!-- /page-wrap -->
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana · Ministry of Labour and Home Affairs</footer>
</body></html>