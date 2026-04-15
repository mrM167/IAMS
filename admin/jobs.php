<?php
// admin/jobs.php — Job post management (US-05)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = '';

// Toggle active/hidden
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE job_posts SET is_active=1-is_active WHERE job_id=?")->execute([(int)$_GET['toggle']]);
    header('Location: /admin/jobs.php?msg=toggled'); exit();
}
// Delete
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM job_posts WHERE job_id=?")->execute([(int)$_GET['delete']]);
    header('Location: /admin/jobs.php?msg=deleted'); exit();
}

// Edit: load existing post
$editJob = null;
if (isset($_GET['edit'])) {
    $eStmt = $db->prepare("SELECT * FROM job_posts WHERE job_id=?"); $eStmt->execute([(int)$_GET['edit']]); $editJob = $eStmt->fetch();
}

// Create or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fields = [
        'title'        => trim($_POST['title'] ?? ''),
        'organization' => trim($_POST['organization'] ?? 'Ministry of Labour and Home Affairs'),
        'location'     => trim($_POST['location'] ?? ''),
        'description'  => trim($_POST['description'] ?? ''),
        'requirements' => trim($_POST['requirements'] ?? ''),
        'salary_range' => trim($_POST['salary_range'] ?? ''),
        'duration'     => trim($_POST['duration'] ?? ''),
        'slots'        => max(1, (int)($_POST['slots'] ?? 1)),
    ];

    if (!$fields['title']) {
        $msg = 'Job title is required.';
    } elseif (!empty($_POST['job_id'])) {
        // Update
        $db->prepare("UPDATE job_posts SET title=?,organization=?,location=?,description=?,requirements=?,salary_range=?,duration=?,slots=? WHERE job_id=?")
           ->execute([...array_values($fields), (int)$_POST['job_id']]);
        $editJob = null;
        header('Location: /admin/jobs.php?msg=updated'); exit();
    } else {
        // Create
        $db->prepare("INSERT INTO job_posts (title,organization,location,description,requirements,salary_range,duration,slots,posted_by) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([...array_values($fields), $user['id']]);
        header('Location: /admin/jobs.php?msg=created'); exit();
    }
}

if (isset($_GET['msg'])) {
    $msgs = ['created'=>'Job post created!','updated'=>'Job post updated!','toggled'=>'Job post visibility toggled.','deleted'=>'Job post deleted.'];
    $msg  = $msgs[$_GET['msg']] ?? '';
}

// Load all jobs with interest count and interests detail
$jobs = $db->query("
    SELECT j.*,COUNT(ji.interest_id) as interest_count,
           GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ') as interested_students
    FROM job_posts j
    LEFT JOIN job_interests ji ON j.job_id=ji.job_id
    LEFT JOIN users u ON ji.user_id=u.user_id
    GROUP BY j.job_id ORDER BY j.created_at DESC
")->fetchAll();

$orgs = $db->query("SELECT org_id,org_name FROM organisations WHERE is_active=1 ORDER BY org_name")->fetchAll();

$pageTitle = 'Job Posts';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">💼 Job Posts</div>
<div class="page-sub">Manage attachment positions visible to students</div>
<?php if ($msg): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start">

<!-- JOB LIST -->
<div class="card">
<table>
  <thead><tr><th>Title</th><th>Organisation</th><th>Location</th><th>Slots</th><th>Interests</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($jobs as $job): ?>
  <tr>
    <td>
      <strong><?php echo htmlspecialchars($job['title']); ?></strong><br>
      <span class="text-muted" style="font-size:.78rem"><?php echo htmlspecialchars($job['salary_range']??''); ?><?php echo $job['duration']?' · '.$job['duration']:''; ?></span>
      <?php if ($job['interested_students']): ?><br><span style="font-size:.72rem;color:var(--teal)">Interests: <?php echo htmlspecialchars($job['interested_students']); ?></span><?php endif; ?>
    </td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($job['organization']); ?></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($job['location']??'—'); ?></td>
    <td style="text-align:center"><?php echo $job['slots']; ?></td>
    <td style="text-align:center;font-weight:700;color:var(--teal)"><?php echo $job['interest_count']; ?></td>
    <td><span class="badge badge-<?php echo $job['is_active']?'active':'inactive'; ?>"><?php echo $job['is_active']?'ACTIVE':'HIDDEN'; ?></span></td>
    <td style="white-space:nowrap">
      <a href="?edit=<?php echo $job['job_id']; ?>" class="btn btn-gold btn-sm">Edit</a>
      <a href="?toggle=<?php echo $job['job_id']; ?>" class="btn btn-outline btn-sm"><?php echo $job['is_active']?'Hide':'Show'; ?></a>
      <a href="?delete=<?php echo $job['job_id']; ?>" class="btn btn-red btn-sm" onclick="return confirm('Delete this job post? This cannot be undone.')">Del</a>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$jobs): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No job posts yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<!-- CREATE / EDIT FORM -->
<div class="card">
  <div class="card-header"><h3><?php echo $editJob ? '✏️ Edit Job Post' : '➕ Create Job Post'; ?></h3></div>
  <div class="card-body">
  <form method="POST">
    <?php echo csrf_field(); ?>
    <?php if ($editJob): ?><input type="hidden" name="job_id" value="<?php echo $editJob['job_id']; ?>"><?php endif; ?>

    <div class="form-group">
      <label>Job Title *</label>
      <input type="text" name="title" required value="<?php echo htmlspecialchars($editJob['title'] ?? ''); ?>" placeholder="e.g. IT Support Intern">
    </div>
    <div class="form-group">
      <label>Organisation *</label>
      <input type="text" name="organization" required value="<?php echo htmlspecialchars($editJob['organization'] ?? 'Ministry of Labour and Home Affairs'); ?>">
    </div>
    <div class="form-group">
      <label>Location</label>
      <input type="text" name="location" value="<?php echo htmlspecialchars($editJob['location'] ?? ''); ?>" placeholder="Gaborone">
    </div>
    <div class="form-group">
      <label>Salary / Stipend Range</label>
      <input type="text" name="salary_range" value="<?php echo htmlspecialchars($editJob['salary_range'] ?? ''); ?>" placeholder="BWP 1,500/month">
    </div>
    <div class="form-group">
      <label>Duration</label>
      <input type="text" name="duration" value="<?php echo htmlspecialchars($editJob['duration'] ?? ''); ?>" placeholder="6 months">
    </div>
    <div class="form-group">
      <label>Number of Slots</label>
      <input type="number" name="slots" min="1" max="50" value="<?php echo htmlspecialchars((string)($editJob['slots'] ?? 1)); ?>">
    </div>
    <div class="form-group">
      <label>Description</label>
      <textarea name="description" rows="4" placeholder="Describe the role and responsibilities..."><?php echo htmlspecialchars($editJob['description'] ?? ''); ?></textarea>
    </div>
    <div class="form-group">
      <label>Requirements / Skills Needed</label>
      <textarea name="requirements" rows="3" placeholder="Skills or qualifications required..."><?php echo htmlspecialchars($editJob['requirements'] ?? ''); ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%"><?php echo $editJob ? 'Update Job Post' : 'Create Job Post'; ?></button>
    <?php if ($editJob): ?>
    <a href="/admin/jobs.php" style="display:block;text-align:center;margin-top:.75rem;color:var(--muted);font-size:.85rem">Cancel edit</a>
    <?php endif; ?>
  </form>
  </div>
</div>

</div>
</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
