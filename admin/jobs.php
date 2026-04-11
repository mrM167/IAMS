<?php
// admin/jobs.php — Create, edit, toggle job posts
require_once '../config/database.php';
require_once '../config/admin_session.php';
requireAdmin();

$user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();
$msg = '';

// Toggle active
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE job_posts SET is_active = 1 - is_active WHERE job_id=?")->execute([$_GET['toggle']]);
    header("Location: jobs.php"); exit();
}
// Delete
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM job_posts WHERE job_id=?")->execute([$_GET['delete']]);
    header("Location: jobs.php?msg=deleted"); exit();
}
if (isset($_GET['msg'])) $msg = $_GET['msg'] === 'deleted' ? 'Job post deleted.' : '';

// Create / Edit
$edit_job = null;
if (isset($_GET['edit'])) {
    $edit_job = $db->prepare("SELECT * FROM job_posts WHERE job_id=?")->execute([$_GET['edit']]) ? $db->prepare("SELECT * FROM job_posts WHERE job_id=?")->execute([$_GET['edit']]) : null;
    $s = $db->prepare("SELECT * FROM job_posts WHERE job_id=?"); $s->execute([$_GET['edit']]); $edit_job = $s->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['title','organization','location','description','requirements','salary_range','duration'];
    $vals = [];
    foreach ($fields as $f) $vals[$f] = trim($_POST[$f] ?? '');

    if (isset($_POST['job_id']) && $_POST['job_id']) {
        $db->prepare("UPDATE job_posts SET title=?,organization=?,location=?,description=?,requirements=?,salary_range=?,duration=? WHERE job_id=?")
           ->execute(array_values($vals) + [8 => $_POST['job_id']]);
        $msg = 'Job post updated.';
    } else {
        $db->prepare("INSERT INTO job_posts (title,organization,location,description,requirements,salary_range,duration,posted_by) VALUES (?,?,?,?,?,?,?,?)")
           ->execute(array_merge(array_values($vals), [$user['id']]));
        $msg = 'Job post created.';
    }
    $edit_job = null;
}

$jobs = $db->query("SELECT j.*, COUNT(ji.interest_id) as interest_count FROM job_posts j LEFT JOIN job_interests ji ON j.job_id=ji.job_id GROUP BY j.job_id ORDER BY j.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Posts — IAMS Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
        :root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c;--bg:#f0f4f8;--muted:#5a7080;}
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:#1a2733;display:flex;min-height:100vh;}
        .sidebar{width:240px;background:var(--navy);color:white;position:fixed;top:0;left:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column;}
        .sidebar-logo{padding:1.5rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.1);}
        .sidebar-logo h2{font-size:1.1rem;}.sidebar-logo p{font-size:0.72rem;color:var(--gold);letter-spacing:0.08em;text-transform:uppercase;margin-top:0.2rem;}
        .nav-section{padding:0.5rem 1.25rem;font-size:0.65rem;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.4);margin-top:0.75rem;}
        .sidebar nav a{display:flex;align-items:center;gap:0.75rem;padding:0.65rem 1.25rem;color:rgba(255,255,255,0.75);text-decoration:none;font-size:0.88rem;font-weight:500;transition:all 0.15s;border-left:3px solid transparent;}
        .sidebar nav a:hover,.sidebar nav a.active{color:white;background:rgba(255,255,255,0.08);border-left-color:var(--gold);}
        .sidebar-footer{margin-top:auto;padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,0.1);font-size:0.8rem;color:rgba(255,255,255,0.5);}
        .sidebar-footer strong{color:white;display:block;}
        .main{margin-left:240px;flex:1;padding:2rem;}
        h1{font-size:1.4rem;color:var(--navy);margin-bottom:0.25rem;}
        .sub{color:var(--muted);font-size:0.85rem;margin-bottom:1.5rem;}
        .msg{background:#d4edda;color:#155724;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;}
        .layout{display:grid;grid-template-columns:1fr 380px;gap:1.5rem;align-items:start;}
        .card{background:white;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.06);overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        th{background:#f8f9fb;padding:0.65rem 1rem;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted);text-align:left;}
        td{padding:0.75rem 1rem;font-size:0.85rem;border-top:1px solid #f0f0f0;vertical-align:middle;}
        tr:hover td{background:#fafbfc;}
        .badge-active{background:#d4edda;color:#155724;padding:0.2rem 0.5rem;border-radius:20px;font-size:0.72rem;font-weight:700;}
        .badge-inactive{background:#f8f9fa;color:#6c757d;padding:0.2rem 0.5rem;border-radius:20px;font-size:0.72rem;font-weight:700;}
        .btn{display:inline-block;padding:0.3rem 0.75rem;border-radius:6px;font-size:0.78rem;font-weight:600;text-decoration:none;background:var(--navy);color:white;border:none;cursor:pointer;margin-right:0.3rem;}
        .btn:hover{background:var(--teal);}
        .btn-red{background:#c0392b;}.btn-red:hover{background:#a93226;}
        .btn-gold{background:var(--gold);color:var(--navy);}.btn-gold:hover{background:#e0bc60;}
        .form-card{background:white;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.06);padding:1.5rem;}
        .form-card h3{color:var(--navy);margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid #eee;}
        .form-group{margin-bottom:1rem;}
        .form-group label{display:block;font-size:0.8rem;font-weight:600;margin-bottom:0.3rem;color:#374151;}
        .form-group input,.form-group textarea,.form-group select{width:100%;padding:0.6rem 0.75rem;border:1px solid #ddd;border-radius:6px;font-size:0.88rem;font-family:inherit;}
        .form-group textarea{resize:vertical;}
        @media(max-width:1000px){.layout{grid-template-columns:1fr;}.sidebar{display:none;}.main{margin-left:0;}}
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><h2>IAMS Admin</h2><p>University of Botswana</p></div>
    <nav>
        <p class="nav-section">Overview</p><a href="index.php"><span>📊</span> Dashboard</a>
        <p class="nav-section">Management</p>
        <a href="applications.php"><span>📋</span> Applications</a>
        <a href="students.php"><span>👩‍🎓</span> Students</a>
        <a href="jobs.php" class="active"><span>💼</span> Job Posts</a>
        <a href="documents.php"><span>📁</span> Documents</a>
        <p class="nav-section">System</p>
        <a href="../index.php"><span>🌐</span> View Site</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
    <div class="sidebar-footer"><strong><?php echo htmlspecialchars($user['name']); ?></strong><?php echo ucfirst($user['role']); ?></div>
</aside>

<main class="main">
    <h1>Job Posts</h1>
    <p class="sub">Manage attachment positions visible to students</p>
    <?php if ($msg): ?><div class="msg">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <div class="layout">
        <!-- JOB LIST -->
        <div class="card">
            <table>
                <thead><tr><th>Title</th><th>Organisation</th><th>Location</th><th>Interests</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($job['title']); ?></strong><br><span style="color:var(--muted);font-size:0.78rem;"><?php echo htmlspecialchars($job['salary_range'] ?? ''); ?></span></td>
                    <td style="color:var(--muted);font-size:0.82rem;"><?php echo htmlspecialchars($job['organization']); ?></td>
                    <td style="color:var(--muted);font-size:0.82rem;"><?php echo htmlspecialchars($job['location'] ?? '—'); ?></td>
                    <td style="text-align:center;"><?php echo $job['interest_count']; ?></td>
                    <td><span class="badge-<?php echo $job['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $job['is_active'] ? 'ACTIVE' : 'HIDDEN'; ?></span></td>
                    <td>
                        <a href="?edit=<?php echo $job['job_id']; ?>" class="btn btn-gold">Edit</a>
                        <a href="?toggle=<?php echo $job['job_id']; ?>" class="btn"><?php echo $job['is_active'] ? 'Hide' : 'Show'; ?></a>
                        <a href="?delete=<?php echo $job['job_id']; ?>" class="btn btn-red" onclick="return confirm('Delete this job post?')">Del</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($jobs)): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No job posts yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- CREATE / EDIT FORM -->
        <div class="form-card">
            <h3><?php echo $edit_job ? 'Edit Job Post' : 'Create New Job Post'; ?></h3>
            <form method="POST">
                <?php if ($edit_job): ?><input type="hidden" name="job_id" value="<?php echo $edit_job['job_id']; ?>"><?php endif; ?>
                <div class="form-group"><label>Job Title *</label><input type="text" name="title" required value="<?php echo htmlspecialchars($edit_job['title'] ?? ''); ?>"></div>
                <div class="form-group"><label>Organisation *</label><input type="text" name="organization" required value="<?php echo htmlspecialchars($edit_job['organization'] ?? 'Ministry of Labour and Home Affairs'); ?>"></div>
                <div class="form-group"><label>Location</label><input type="text" name="location" placeholder="Gaborone" value="<?php echo htmlspecialchars($edit_job['location'] ?? ''); ?>"></div>
                <div class="form-group"><label>Salary / Stipend Range</label><input type="text" name="salary_range" placeholder="BWP 1,500/month" value="<?php echo htmlspecialchars($edit_job['salary_range'] ?? ''); ?>"></div>
                <div class="form-group"><label>Duration</label><input type="text" name="duration" placeholder="6 months" value="<?php echo htmlspecialchars($edit_job['duration'] ?? ''); ?>"></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4" placeholder="Describe the role..."><?php echo htmlspecialchars($edit_job['description'] ?? ''); ?></textarea></div>
                <div class="form-group"><label>Requirements</label><textarea name="requirements" rows="3" placeholder="Skills or qualifications needed..."><?php echo htmlspecialchars($edit_job['requirements'] ?? ''); ?></textarea></div>
                <button type="submit" class="btn" style="width:100%;padding:0.7rem;"><?php echo $edit_job ? 'Update Job Post' : 'Create Job Post'; ?></button>
                <?php if ($edit_job): ?><a href="jobs.php" style="display:block;text-align:center;margin-top:0.75rem;color:var(--muted);font-size:0.85rem;">Cancel edit</a><?php endif; ?>
            </form>
        </div>
    </div>
</main>
</body>
</html>
