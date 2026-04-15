<?php
// org/dashboard.php — Organisation dashboard (US-03, US-17)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
requireRole('organisation');

$user = getCurrentUser();
$db   = Database::getInstance();

// Get org record
$orgStmt = $db->prepare("SELECT * FROM organisations WHERE user_id=?");
$orgStmt->execute([$user['id']]);
$org = $orgStmt->fetch();
if (!$org) { header('Location: /logout.php'); exit(); }

// Matched students
$matched = $db->prepare("
    SELECT u.full_name, u.email, u.phone, u.student_number, u.programme,
           a.status, a.submission_date, sp.skills, sp.linkedin_url, sp.github_url,
           a.app_id, m.match_score, m.status as match_status, m.confirmed_at
    FROM matches m
    JOIN applications a ON m.app_id = a.app_id
    JOIN users u ON m.user_id = u.user_id
    LEFT JOIN student_profiles sp ON u.user_id = sp.user_id
    WHERE m.org_id = ? AND m.status != 'declined'
    ORDER BY m.created_at DESC
");
$matched->execute([$org['org_id']]);
$matched = $matched->fetchAll();

// Job posts for this org
$myJobs = $db->prepare("SELECT j.*,COUNT(ji.interest_id) as interest_count FROM job_posts j LEFT JOIN job_interests ji ON j.job_id=ji.job_id WHERE j.org_id=? GROUP BY j.job_id ORDER BY j.created_at DESC");
$myJobs->execute([$org['org_id']]);
$myJobs = $myJobs->fetchAll();

$tab = $_GET['tab'] ?? 'home';
$msg = $err = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $db->prepare("UPDATE organisations SET org_name=?,industry=?,contact_person=?,contact_email=?,contact_phone=?,address=?,location=?,description=?,required_skills=?,capacity=? WHERE org_id=?")
           ->execute([
               trim($_POST['org_name'] ?? $org['org_name']),
               trim($_POST['industry'] ?? ''),
               trim($_POST['contact_person'] ?? ''),
               trim($_POST['contact_email'] ?? ''),
               trim($_POST['contact_phone'] ?? ''),
               trim($_POST['address'] ?? ''),
               trim($_POST['location'] ?? ''),
               trim($_POST['description'] ?? ''),
               trim($_POST['required_skills'] ?? ''),
               (int)($_POST['capacity'] ?? 1),
               $org['org_id'],
           ]);
        $db->prepare("UPDATE users SET full_name=? WHERE user_id=?")->execute([$_POST['org_name'],$user['id']]);
        header('Location: /org/dashboard.php?tab=profile&msg=saved'); exit();
    }

    if ($action === 'post_job') {
        $db->prepare("INSERT INTO job_posts (title,organization,org_id,location,description,requirements,salary_range,duration,slots,posted_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([
               trim($_POST['title']??''),
               $org['org_name'],
               $org['org_id'],
               trim($_POST['location']??''),
               trim($_POST['description']??''),
               trim($_POST['requirements']??''),
               trim($_POST['salary_range']??''),
               trim($_POST['duration']??''),
               (int)($_POST['slots']??1),
               $user['id'],
           ]);
        header('Location: /org/dashboard.php?tab=jobs&msg=posted'); exit();
    }

    if ($action === 'toggle_job') {
        $db->prepare("UPDATE job_posts SET is_active = 1-is_active WHERE job_id=? AND org_id=?")->execute([$_POST['job_id'],$org['org_id']]);
        header('Location: /org/dashboard.php?tab=jobs'); exit();
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['saved'=>'Profile updated!','posted'=>'Job post created!'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

$pageTitle = 'Organisation Dashboard';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">

<?php if ($msg): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- Tab nav -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <?php foreach(['home'=>'🏠 Home','students'=>'🎓 Matched Students','jobs'=>'💼 Job Posts','profile'=>'🏢 Profile'] as $t=>$label): ?>
  <a href="?tab=<?php echo $t; ?>" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $tab===$t?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'home'): ?>
<div class="page-title"><?php echo htmlspecialchars($org['org_name']); ?></div>
<div class="page-sub"><?php echo htmlspecialchars($org['industry']??''); ?> · <?php echo htmlspecialchars($org['location']??''); ?></div>

<div class="stats-grid">
  <div class="stat-card green"><div class="stat-label">Matched Students</div><div class="stat-num"><?php echo count($matched); ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Capacity</div><div class="stat-num"><?php echo $org['capacity']; ?></div></div>
  <div class="stat-card teal"><div class="stat-label">Job Posts</div><div class="stat-num"><?php echo count($myJobs); ?></div></div>
  <div class="stat-card"><div class="stat-label">Spots Available</div><div class="stat-num"><?php echo max(0,$org['capacity']-count($matched)); ?></div></div>
</div>

<div class="card">
  <div class="card-header"><h3>Recently Matched Students</h3><a href="?tab=students" style="font-size:.8rem;color:var(--teal)">View all →</a></div>
  <?php if ($matched): ?>
  <table><thead><tr><th>Student</th><th>Programme</th><th>Skills</th><th>Match Status</th></tr></thead><tbody>
  <?php foreach (array_slice($matched,0,5) as $s): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($s['email']); ?></span></td>
    <td class="text-muted"><?php echo htmlspecialchars($s['programme']??'—'); ?></td>
    <td style="font-size:.8rem;color:var(--muted)"><?php echo htmlspecialchars(substr($s['skills']??'—',0,50)); ?></td>
    <td><span class="badge badge-<?php echo $s['match_status']==='confirmed'?'accepted':'matched'; ?>"><?php echo strtoupper($s['match_status']); ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><div class="card-body"><p class="text-muted">No students matched yet. The coordinator will assign students to your organisation.</p></div><?php endif; ?>
</div>

<?php elseif ($tab === 'students'): ?>
<div class="page-title">🎓 Matched Students</div>
<div class="page-sub">Students assigned to <?php echo htmlspecialchars($org['org_name']); ?></div>
<?php if ($matched): ?>
<div class="card">
<table><thead><tr><th>Student</th><th>Student #</th><th>Programme</th><th>Skills</th><th>Score</th><th>Status</th><th>Confirmed</th></tr></thead><tbody>
<?php foreach ($matched as $s): ?>
<tr>
  <td>
    <strong><?php echo htmlspecialchars($s['full_name']); ?></strong><br>
    <span class="text-muted"><?php echo htmlspecialchars($s['email']); ?></span>
    <?php if($s['linkedin_url']): ?><br><a href="<?php echo htmlspecialchars($s['linkedin_url']); ?>" target="_blank" style="font-size:.75rem;color:var(--teal)">LinkedIn ↗</a><?php endif; ?>
  </td>
  <td class="text-muted"><?php echo htmlspecialchars($s['student_number']??'—'); ?></td>
  <td class="text-muted"><?php echo htmlspecialchars($s['programme']??'—'); ?></td>
  <td style="font-size:.8rem;color:var(--muted)"><?php echo htmlspecialchars($s['skills']??'—'); ?></td>
  <td style="font-weight:700;color:var(--navy)"><?php echo $s['match_score'] ? number_format($s['match_score'],1).'%' : '—'; ?></td>
  <td><span class="badge badge-<?php echo $s['match_status']==='confirmed'?'accepted':'matched'; ?>"><?php echo strtoupper($s['match_status']); ?></span></td>
  <td class="text-muted" style="font-size:.78rem"><?php echo $s['confirmed_at'] ? date('j M Y',strtotime($s['confirmed_at'])) : 'Pending'; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php else: ?><div class="card"><div class="card-body"><p class="text-muted">No students have been matched to your organisation yet. The coordinator will assign students based on skill and preference alignment.</p></div></div><?php endif; ?>

<?php elseif ($tab === 'jobs'): ?>
<div class="page-title">💼 Your Job Posts</div>
<div class="page-sub">Manage positions visible to students</div>

<div class="grid-2" style="align-items:start">
<div class="card">
<?php if ($myJobs): ?>
<table><thead><tr><th>Title</th><th>Location</th><th>Slots</th><th>Interests</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($myJobs as $j): ?>
<tr>
  <td><strong><?php echo htmlspecialchars($j['title']); ?></strong><br><span class="text-muted" style="font-size:.78rem"><?php echo htmlspecialchars($j['salary_range']??''); ?></span></td>
  <td class="text-muted"><?php echo htmlspecialchars($j['location']??''); ?></td>
  <td style="text-align:center"><?php echo $j['slots']; ?></td>
  <td style="text-align:center"><?php echo $j['interest_count']; ?></td>
  <td><span class="badge badge-<?php echo $j['is_active']?'active':'inactive'; ?>"><?php echo $j['is_active']?'ACTIVE':'HIDDEN'; ?></span></td>
  <td>
    <form method="POST" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle_job"><input type="hidden" name="job_id" value="<?php echo $j['job_id']; ?>">
    <button type="submit" class="btn btn-outline btn-sm"><?php echo $j['is_active']?'Hide':'Show'; ?></button></form>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><div class="card-body"><p class="text-muted">No job posts yet.</p></div><?php endif; ?>
</div>

<div class="card">
<div class="card-header"><h3>Post a New Position</h3></div>
<div class="card-body">
<form method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="post_job">
  <div class="form-group"><label>Job Title *</label><input type="text" name="title" required placeholder="e.g. IT Support Intern"></div>
  <div class="form-group"><label>Location</label><input type="text" name="location" value="<?php echo htmlspecialchars($org['location']??''); ?>"></div>
  <div class="form-group"><label>Salary / Stipend</label><input type="text" name="salary_range" placeholder="BWP 1,500/month"></div>
  <div class="form-group"><label>Duration</label><input type="text" name="duration" placeholder="6 months"></div>
  <div class="form-group"><label>Number of Slots</label><input type="number" name="slots" min="1" max="50" value="1"></div>
  <div class="form-group"><label>Description</label><textarea name="description" rows="3" placeholder="Describe the role..."></textarea></div>
  <div class="form-group"><label>Requirements</label><textarea name="requirements" rows="2" placeholder="Skills / qualifications needed..."></textarea></div>
  <button type="submit" class="btn btn-primary">Create Job Post</button>
</form>
</div>
</div>
</div>

<?php elseif ($tab === 'profile'): ?>
<div class="page-title">🏢 Organisation Profile</div>
<div class="page-sub">Update your organisation details and skill requirements</div>
<div class="card" style="max-width:720px">
<div class="card-body">
<form method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="update_profile">
  <div class="form-group"><label>Organisation Name *</label><input type="text" name="org_name" required value="<?php echo htmlspecialchars($org['org_name']); ?>"></div>
  <div class="grid-2">
    <div class="form-group"><label>Industry</label>
      <select name="industry">
        <?php foreach(['Government','Information Technology','Finance & Banking','Healthcare','Education','Engineering','Legal','Mining & Resources','Retail & Commerce','Other'] as $ind): ?>
        <option value="<?php echo $ind; ?>" <?php echo ($org['industry']??'')===$ind?'selected':''; ?>><?php echo $ind; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Capacity (students)</label><input type="number" name="capacity" min="1" max="50" value="<?php echo $org['capacity']; ?>"></div>
  </div>
  <div class="grid-2">
    <div class="form-group"><label>Location</label><input type="text" name="location" value="<?php echo htmlspecialchars($org['location']??''); ?>"></div>
    <div class="form-group"><label>Address</label><input type="text" name="address" value="<?php echo htmlspecialchars($org['address']??''); ?>"></div>
  </div>
  <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" value="<?php echo htmlspecialchars($org['contact_person']??''); ?>"></div>
  <div class="grid-2">
    <div class="form-group"><label>Contact Email</label><input type="email" name="contact_email" value="<?php echo htmlspecialchars($org['contact_email']??''); ?>"></div>
    <div class="form-group"><label>Contact Phone</label><input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($org['contact_phone']??''); ?>"></div>
  </div>
  <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?php echo htmlspecialchars($org['description']??''); ?></textarea></div>
  <div class="form-group"><label>Required Skills (for student matching)</label><textarea name="required_skills" rows="2" placeholder="PHP, Python, Data Analysis, Accounting..."><?php echo htmlspecialchars($org['required_skills']??''); ?></textarea></div>
  <button type="submit" class="btn btn-primary">Save Profile</button>
</form>
</div></div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
