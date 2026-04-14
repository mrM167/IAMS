<?php
// admin/applications.php — View, review and update application statuses
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin_session.php';
requireAdmin();

$user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

$msg = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'], $_POST['status'])) {
    $allowed = ['pending','under_review','accepted','rejected'];
    if (in_array($_POST['status'], $allowed)) {
        $stmt = $db->prepare("UPDATE applications SET status=?, review_notes=?, reviewed_by=? WHERE app_id=?");
        $stmt->execute([$_POST['status'], $_POST['notes'] ?? '', $user['id'], $_POST['app_id']]);
        $msg = 'Application updated successfully.';
    }
}

// View single application
$view_app = null;
$view_docs = [];
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'view') {
    $stmt = $db->prepare("SELECT a.*, u.email, u.phone, u.programme as user_programme,
        sp.linkedin_url, sp.github_url, sp.portfolio_url, sp.skills as profile_skills
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
        WHERE a.app_id = ?");
    $stmt->execute([$_GET['id']]);
    $view_app = $stmt->fetch();

    if ($view_app) {
        $doc_stmt = $db->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY uploaded_at DESC");
        $doc_stmt->execute([$view_app['user_id']]);
        $view_docs = $doc_stmt->fetchAll();
    }
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where = $filter !== 'all' ? "WHERE a.status = " . $db->quote($filter) : '';
$applications = $db->query("
    SELECT a.*, u.email, u.phone
    FROM applications a
    JOIN users u ON a.user_id = u.user_id
    $where
    ORDER BY a.submission_date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications — IAMS Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
        :root { --navy:#0a2f44; --teal:#1a5a7a; --gold:#c9a84c; --bg:#f0f4f8; --muted:#5a7080; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:#1a2733; display:flex; min-height:100vh; }
        .sidebar { width:240px; background:var(--navy); color:white; position:fixed; top:0; left:0; height:100vh; overflow-y:auto; display:flex; flex-direction:column; }
        .sidebar-logo { padding:1.5rem 1.25rem; border-bottom:1px solid rgba(255,255,255,0.1); }
        .sidebar-logo h2 { font-size:1.1rem; } .sidebar-logo p { font-size:0.72rem; color:var(--gold); letter-spacing:0.08em; text-transform:uppercase; margin-top:0.2rem; }
        .nav-section { padding:0.5rem 1.25rem; font-size:0.65rem; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:rgba(255,255,255,0.4); margin-top:0.75rem; }
        .sidebar nav a { display:flex; align-items:center; gap:0.75rem; padding:0.65rem 1.25rem; color:rgba(255,255,255,0.75); text-decoration:none; font-size:0.88rem; font-weight:500; transition:all 0.15s; border-left:3px solid transparent; }
        .sidebar nav a:hover, .sidebar nav a.active { color:white; background:rgba(255,255,255,0.08); border-left-color:var(--gold); }
        .sidebar-footer { margin-top:auto; padding:1rem 1.25rem; border-top:1px solid rgba(255,255,255,0.1); font-size:0.8rem; color:rgba(255,255,255,0.5); }
        .sidebar-footer strong { color:white; display:block; margin-bottom:0.2rem; }
        .main { margin-left:240px; flex:1; padding:2rem; }
        h1 { font-size:1.4rem; color:var(--navy); margin-bottom:0.25rem; }
        .sub { color:var(--muted); font-size:0.85rem; margin-bottom:1.5rem; }
        .msg { background:#d4edda; color:#155724; padding:0.75rem 1rem; border-radius:8px; margin-bottom:1rem; }
        .filters { display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap; }
        .filter-btn { padding:0.4rem 1rem; border-radius:6px; text-decoration:none; font-size:0.82rem; font-weight:600; background:white; color:var(--muted); border:1px solid #ddd; transition:all 0.15s; }
        .filter-btn.active, .filter-btn:hover { background:var(--navy); color:white; border-color:var(--navy); }
        .card { background:white; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:1.5rem; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fb; padding:0.65rem 1rem; font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.07em; color:var(--muted); text-align:left; }
        td { padding:0.75rem 1rem; font-size:0.85rem; border-top:1px solid #f0f0f0; vertical-align:middle; }
        tr:hover td { background:#fafbfc; }
        .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:20px; font-size:0.72rem; font-weight:700; }
        .badge-pending { background:#fff3cd; color:#856404; }
        .badge-accepted { background:#d4edda; color:#155724; }
        .badge-rejected { background:#f8d7da; color:#721c24; }
        .badge-under_review { background:#cce5ff; color:#004085; }
        .btn { display:inline-block; padding:0.35rem 0.8rem; border-radius:6px; font-size:0.8rem; font-weight:600; text-decoration:none; background:var(--navy); color:white; border:none; cursor:pointer; }
        .btn:hover { background:var(--teal); }
        .btn-outline { background:transparent; color:var(--navy); border:1px solid var(--navy); }
        /* Detail panel */
        .detail { background:white; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); padding:1.5rem; }
        .detail h2 { color:var(--navy); margin-bottom:1rem; padding-bottom:0.75rem; border-bottom:2px solid #eee; }
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem 2rem; margin-bottom:1.5rem; }
        .detail-item label { font-size:0.72rem; font-weight:600; text-transform:uppercase; color:var(--muted); letter-spacing:0.07em; display:block; margin-bottom:0.2rem; }
        .detail-item p { font-size:0.9rem; }
        .detail-item a { color:var(--teal); }
        select { padding:0.5rem 0.75rem; border:1px solid #ddd; border-radius:6px; font-size:0.9rem; width:100%; }
        textarea { padding:0.6rem 0.75rem; border:1px solid #ddd; border-radius:6px; font-size:0.85rem; width:100%; resize:vertical; }
        .doc-item { display:flex; justify-content:space-between; align-items:center; background:#f8f9fb; padding:0.6rem 0.9rem; border-radius:6px; margin-bottom:0.5rem; font-size:0.85rem; }
        @media(max-width:900px){ .sidebar{display:none;} .main{margin-left:0;} .detail-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><h2>IAMS Admin</h2><p>University of Botswana</p></div>
    <nav>
        <p class="nav-section">Overview</p>
        <a href="index.php"><span>📊</span> Dashboard</a>
        <p class="nav-section">Management</p>
        <a href="applications.php" class="active"><span>📋</span> Applications</a>
        <a href="students.php"><span>👩‍🎓</span> Students</a>
        <a href="jobs.php"><span>💼</span> Job Posts</a>
        <a href="documents.php"><span>📁</span> Documents</a>
        <p class="nav-section">System</p>
        <a href="../index.php"><span>🌐</span> View Site</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
    <div class="sidebar-footer"><strong><?php echo htmlspecialchars($user['name']); ?></strong><?php echo ucfirst($user['role']); ?></div>
</aside>

<main class="main">
    <h1>Applications</h1>
    <p class="sub">Review and update student attachment applications</p>

    <?php if ($msg): ?><div class="msg">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <?php if ($view_app): ?>
    <!-- DETAIL VIEW -->
    <div style="margin-bottom:1rem;">
        <a href="applications.php" class="btn btn-outline">← Back to list</a>
    </div>
    <div class="detail">
        <h2>Application #<?php echo $view_app['app_id']; ?> — <?php echo htmlspecialchars($view_app['full_name']); ?></h2>
        <div class="detail-grid">
            <div class="detail-item"><label>Full Name</label><p><?php echo htmlspecialchars($view_app['full_name']); ?></p></div>
            <div class="detail-item"><label>Student Number</label><p><?php echo htmlspecialchars($view_app['student_number']); ?></p></div>
            <div class="detail-item"><label>Email</label><p><?php echo htmlspecialchars($view_app['email']); ?></p></div>
            <div class="detail-item"><label>Phone</label><p><?php echo htmlspecialchars($view_app['phone'] ?? '—'); ?></p></div>
            <div class="detail-item"><label>Programme</label><p><?php echo htmlspecialchars($view_app['programme']); ?></p></div>
            <div class="detail-item"><label>Preferred Location</label><p><?php echo htmlspecialchars($view_app['preferred_location'] ?? '—'); ?></p></div>
            <div class="detail-item" style="grid-column:1/-1"><label>Skills</label><p><?php echo htmlspecialchars($view_app['skills'] ?? '—'); ?></p></div>
            <?php if (!empty($view_app['linkedin_url'])): ?>
            <div class="detail-item"><label>LinkedIn</label><p><a href="<?php echo htmlspecialchars($view_app['linkedin_url']); ?>" target="_blank"><?php echo htmlspecialchars($view_app['linkedin_url']); ?></a></p></div>
            <?php endif; ?>
            <?php if (!empty($view_app['github_url'])): ?>
            <div class="detail-item"><label>GitHub</label><p><a href="<?php echo htmlspecialchars($view_app['github_url']); ?>" target="_blank"><?php echo htmlspecialchars($view_app['github_url']); ?></a></p></div>
            <?php endif; ?>
            <div class="detail-item"><label>Submitted</label><p><?php echo date('j F Y, H:i', strtotime($view_app['submission_date'])); ?></p></div>
            <div class="detail-item"><label>Current Status</label><p><span class="badge badge-<?php echo str_replace(' ','_',$view_app['status']); ?>"><?php echo strtoupper(str_replace('_',' ',$view_app['status'])); ?></span></p></div>
        </div>

        <!-- Uploaded Documents -->
        <h3 style="color:var(--navy);margin-bottom:0.75rem;">Uploaded Documents (<?php echo count($view_docs); ?>)</h3>
        <?php if ($view_docs): ?>
            <?php foreach ($view_docs as $doc): ?>
            <div class="doc-item">
                <span>📄 <?php echo htmlspecialchars($doc['doc_type']); ?> — <?php echo htmlspecialchars($doc['filename']); ?></span>
                <a href="../download.php?id=<?php echo $doc['doc_id']; ?>" class="btn" target="_blank">Download</a>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:var(--muted);font-size:0.85rem;">No documents uploaded.</p>
        <?php endif; ?>

        <!-- Update Status Form -->
        <h3 style="color:var(--navy);margin:1.5rem 0 0.75rem;">Update Application Status</h3>
        <form method="POST">
            <input type="hidden" name="app_id" value="<?php echo $view_app['app_id']; ?>">
            <div style="margin-bottom:1rem;">
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Status</label>
                <select name="status">
                    <?php foreach (['pending','under_review','accepted','rejected'] as $s): ?>
                    <option value="<?php echo $s; ?>" <?php echo $view_app['status'] === $s ? 'selected' : ''; ?>>
                        <?php echo strtoupper(str_replace('_',' ',$s)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom:1rem;">
                <label style="font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.35rem;">Notes for Student (optional)</label>
                <textarea name="notes" rows="3" placeholder="e.g. Congratulations, you have been placed at MLHA HQ. Report on 1 June 2025."><?php echo htmlspecialchars($view_app['review_notes'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn">Save Changes</button>
        </form>
    </div>

    <?php else: ?>
    <!-- APPLICATION LIST -->
    <div class="filters">
        <?php foreach (['all','pending','under_review','accepted','rejected'] as $f): ?>
        <a href="?filter=<?php echo $f; ?>" class="filter-btn <?php echo $filter === $f ? 'active' : ''; ?>">
            <?php echo strtoupper(str_replace('_',' ',$f)); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="card">
        <table>
            <thead>
                <tr><th>Student</th><th>Programme</th><th>Location</th><th>Date</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($app['full_name']); ?></strong><br>
                    <span style="color:var(--muted);font-size:0.78rem;"><?php echo htmlspecialchars($app['email']); ?></span>
                </td>
                <td style="color:var(--muted)"><?php echo htmlspecialchars($app['programme']); ?></td>
                <td style="color:var(--muted)"><?php echo htmlspecialchars($app['preferred_location'] ?? '—'); ?></td>
                <td style="color:var(--muted);font-size:0.8rem;"><?php echo date('j M Y', strtotime($app['submission_date'])); ?></td>
                <td><span class="badge badge-<?php echo str_replace(' ','_',$app['status']); ?>"><?php echo strtoupper(str_replace('_',' ',$app['status'])); ?></span></td>
                <td><a href="?action=view&id=<?php echo $app['app_id']; ?>" class="btn">Review</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($applications)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No applications found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
