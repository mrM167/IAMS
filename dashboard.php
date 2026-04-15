<?php
// dashboard.php — Student Dashboard (US-02)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();
requireRole('student');

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = '';
$tab  = $_GET['tab'] ?? 'home';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_check();
    $db->prepare("UPDATE student_profiles SET year_of_study=?, gpa=?, linkedin_url=?, github_url=?, portfolio_url=?, skills=?, bio=? WHERE user_id=?")
       ->execute([
           $_POST['year_of_study'] ?? null,
           $_POST['gpa'] ?? null,
           $_POST['linkedin_url'] ?? null,
           $_POST['github_url'] ?? null,
           $_POST['portfolio_url'] ?? null,
           $_POST['skills'] ?? null,
           $_POST['bio'] ?? null,
           $user['id']
       ]);
    $msg = 'Profile updated.';
}

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_app'])) {
    csrf_check();
    $appExists = $db->prepare("SELECT app_id FROM applications WHERE user_id=?");
    $appExists->execute([$user['id']]);
    if ($appExists->fetch()) {
        $msg = 'You have already submitted an application.';
    } else {
        $db->prepare("INSERT INTO applications (user_id, full_name, student_number, programme, skills, preferred_location, cover_letter, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')")
           ->execute([
               $user['id'],
               $_POST['full_name'],
               $_POST['student_number'],
               $_POST['programme'],
               $_POST['skills'] ?? '',
               $_POST['preferred_location'] ?? '',
               $_POST['cover_letter'] ?? ''
           ]);
        $msg = 'Application submitted successfully!';
    }
}

// Load student profile and application
$profile = $db->prepare("SELECT * FROM student_profiles WHERE user_id=?");
$profile->execute([$user['id']]);
$profile = $profile->fetch();

$application = $db->prepare("SELECT * FROM applications WHERE user_id=?");
$application->execute([$user['id']]);
$application = $application->fetch();

$matched = null;
if ($application && $application['status'] === 'matched') {
    $m = $db->prepare("SELECT m.*, o.org_name, o.location FROM matches m JOIN organisations o ON m.org_id = o.org_id WHERE m.app_id=? AND m.status='confirmed'");
    $m->execute([$application['app_id']]);
    $matched = $m->fetch();
}

$jobs = $db->query("SELECT * FROM job_posts WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
$docs = $db->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY uploaded_at DESC");
$docs->execute([$user['id']]);
$docs = $docs->fetchAll();

$pageTitle = 'Student Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="page-wrap">
<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <a href="?tab=home" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab==='home'?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>">🏠 Home</a>
  <a href="?tab=profile" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab==='profile'?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>">👤 Profile</a>
  <a href="?tab=apply" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab==='apply'?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>">📝 Apply</a>
  <a href="?tab=jobs" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab==='jobs'?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>">💼 Jobs</a>
  <a href="?tab=documents" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab==='documents'?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>">📄 Documents</a>
</div>

<?php if ($tab === 'home'): ?>
  <div class="page-title">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</div>
  <?php if ($matched): ?>
    <div class="alert alert-success">✅ You have been matched to <strong><?php echo htmlspecialchars($matched['org_name']); ?></strong> in <?php echo htmlspecialchars($matched['location']); ?>.</div>
  <?php elseif ($application): ?>
    <div class="alert alert-info">📋 Application Status: <strong><?php echo strtoupper(str_replace('_',' ',$application['status'])); ?></strong></div>
  <?php else: ?>
    <div class="alert alert-warning">You have not submitted an application yet. <a href="?tab=apply">Apply now →</a></div>
  <?php endif; ?>

<?php elseif ($tab === 'profile'): ?>
  <div class="page-title">Your Profile</div>
  <div class="card" style="max-width:720px">
  <div class="card-body">
  <form method="POST">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="update_profile" value="1">
    <div class="grid-2">
      <div class="form-group"><label>Year of Study</label><input type="number" name="year_of_study" min="1" max="6" value="<?php echo htmlspecialchars($profile['year_of_study']??''); ?>"></div>
      <div class="form-group"><label>GPA</label><input type="text" name="gpa" value="<?php echo htmlspecialchars($profile['gpa']??''); ?>"></div>
    </div>
    <div class="form-group"><label>LinkedIn URL</label><input type="url" name="linkedin_url" value="<?php echo htmlspecialchars($profile['linkedin_url']??''); ?>"></div>
    <div class="form-group"><label>GitHub URL</label><input type="url" name="github_url" value="<?php echo htmlspecialchars($profile['github_url']??''); ?>"></div>
    <div class="form-group"><label>Portfolio URL</label><input type="url" name="portfolio_url" value="<?php echo htmlspecialchars($profile['portfolio_url']??''); ?>"></div>
    <div class="form-group"><label>Skills (comma separated)</label><textarea name="skills" rows="3"><?php echo htmlspecialchars($profile['skills']??''); ?></textarea></div>
    <div class="form-group"><label>Bio</label><textarea name="bio" rows="4"><?php echo htmlspecialchars($profile['bio']??''); ?></textarea></div>
    <button type="submit" class="btn btn-primary">Save Profile</button>
  </form>
  </div></div>

<?php elseif ($tab === 'apply'): ?>
  <div class="page-title">Attachment Application</div>
  <?php if ($application): ?>
    <div class="card"><div class="card-body"><p>You have already submitted an application. Status: <strong><?php echo strtoupper(str_replace('_',' ',$application['status'])); ?></strong></p></div></div>
  <?php else: ?>
  <div class="card" style="max-width:720px">
  <div class="card-body">
  <form method="POST">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="submit_app" value="1">
    <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['name']); ?>"></div>
    <div class="form-group"><label>Student Number *</label><input type="text" name="student_number" required value="<?php echo htmlspecialchars($_SESSION['student_number']??''); ?>"></div>
    <div class="form-group"><label>Programme *</label><input type="text" name="programme" required value="<?php echo htmlspecialchars($_SESSION['programme']??''); ?>"></div>
    <div class="form-group"><label>Skills</label><textarea name="skills" rows="3"><?php echo htmlspecialchars($profile['skills']??''); ?></textarea></div>
    <div class="form-group"><label>Preferred Location</label><input type="text" name="preferred_location" placeholder="Gaborone"></div>
    <div class="form-group"><label>Cover Letter</label><textarea name="cover_letter" rows="6"></textarea></div>
    <button type="submit" class="btn btn-primary">Submit Application</button>
  </form>
  </div></div>
  <?php endif; ?>

<?php elseif ($tab === 'jobs'): ?>
  <div class="page-title">Available Positions</div>
  <?php foreach ($jobs as $job): ?>
    <div class="card" style="margin-bottom:1rem"><div class="card-body">
      <h3><?php echo htmlspecialchars($job['title']); ?></h3>
      <p><strong><?php echo htmlspecialchars($job['organization']); ?></strong> — <?php echo htmlspecialchars($job['location']); ?></p>
      <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
      <p><small>Slots: <?php echo $job['slots']; ?> | <?php echo htmlspecialchars($job['duration']); ?></small></p>
    </div></div>
  <?php endforeach; ?>

<?php elseif ($tab === 'documents'): ?>
  <div class="page-title">My Documents</div>
  <div class="card">
  <?php if ($docs): ?>
  <table><thead><tr><th>Type</th><th>Filename</th><th>Uploaded</th><th></th></tr></thead><tbody>
  <?php foreach ($docs as $d): ?>
  <tr><td><?php echo htmlspecialchars($d['doc_type']); ?></td><td><?php echo htmlspecialchars($d['filename']); ?></td><td><?php echo $d['uploaded_at']; ?></td><td><a href="/download.php?id=<?php echo $d['doc_id']; ?>" class="btn btn-primary btn-sm">Download</a></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><div class="card-body"><p>No documents uploaded.</p></div><?php endif; ?>
  </div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>