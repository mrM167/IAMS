<?php
// admin/index.php — Admin/Coordinator Dashboard
require_once '../config/database.php';
require_once '../config/admin_session.php';
requireAdmin();

$user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

// Stats
$stats = [];
$queries = [
    'total_students'     => "SELECT COUNT(*) FROM users WHERE role='student'",
    'total_applications' => "SELECT COUNT(*) FROM applications",
    'pending'            => "SELECT COUNT(*) FROM applications WHERE status='pending'",
    'accepted'           => "SELECT COUNT(*) FROM applications WHERE status='accepted'",
    'rejected'           => "SELECT COUNT(*) FROM applications WHERE status='rejected'",
    'active_jobs'        => "SELECT COUNT(*) FROM job_posts WHERE is_active=1",
    'total_docs'         => "SELECT COUNT(*) FROM documents",
];
foreach ($queries as $key => $sql) {
    $stats[$key] = $db->query($sql)->fetchColumn();
}

// Recent applications
$recent = $db->query("
    SELECT a.*, u.email, u.phone
    FROM applications a
    JOIN users u ON a.user_id = u.user_id
    ORDER BY a.submission_date DESC
    LIMIT 8
")->fetchAll();

// Recent registrations
$new_users = $db->query("
    SELECT user_id, full_name, email, student_number, programme, created_at
    FROM users WHERE role='student'
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — IAMS</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap');
        :root {
            --navy:#0a2f44; --teal:#1a5a7a; --gold:#c9a84c;
            --bg:#f0f4f8; --white:#fff; --text:#1a2733; --muted:#5a7080;
            --green:#1a7a4a; --red:#c0392b; --orange:#d68910;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; }

        /* SIDEBAR */
        .sidebar {
            width:240px; background:var(--navy); color:white;
            display:flex; flex-direction:column; position:fixed;
            top:0; left:0; height:100vh; z-index:50;
            overflow-y:auto;
        }
        .sidebar-logo {
            padding:1.5rem 1.25rem;
            border-bottom:1px solid rgba(255,255,255,0.1);
        }
        .sidebar-logo h2 { font-size:1.1rem; font-weight:700; }
        .sidebar-logo p { font-size:0.72rem; color:var(--gold); letter-spacing:0.08em; text-transform:uppercase; margin-top:0.2rem; }
        .sidebar nav { padding:1rem 0; flex:1; }
        .nav-section { padding:0.5rem 1.25rem; font-size:0.65rem; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:rgba(255,255,255,0.4); margin-top:0.75rem; }
        .sidebar nav a {
            display:flex; align-items:center; gap:0.75rem;
            padding:0.65rem 1.25rem; color:rgba(255,255,255,0.75);
            text-decoration:none; font-size:0.88rem; font-weight:500;
            transition:all 0.15s; border-left:3px solid transparent;
        }
        .sidebar nav a:hover, .sidebar nav a.active {
            color:white; background:rgba(255,255,255,0.08);
            border-left-color:var(--gold);
        }
        .sidebar nav a .icon { font-size:1rem; width:20px; text-align:center; }
        .sidebar-footer { padding:1rem 1.25rem; border-top:1px solid rgba(255,255,255,0.1); font-size:0.8rem; color:rgba(255,255,255,0.5); }
        .sidebar-footer strong { color:white; display:block; margin-bottom:0.2rem; }
        .sidebar-footer a { color:var(--gold); text-decoration:none; font-size:0.8rem; }

        /* MAIN */
        .main { margin-left:240px; flex:1; padding:2rem; }
        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .topbar h1 { font-size:1.4rem; font-weight:700; color:var(--navy); }
        .topbar p { font-size:0.85rem; color:var(--muted); margin-top:0.1rem; }
        .topbar-right { display:flex; align-items:center; gap:1rem; }
        .avatar { width:36px; height:36px; border-radius:50%; background:var(--navy); color:var(--gold); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem; }

        /* STATS GRID */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:1.25rem; margin-bottom:2rem; }
        .stat-card {
            background:white; border-radius:12px; padding:1.25rem 1.5rem;
            box-shadow:0 1px 6px rgba(0,0,0,0.06); border-top:3px solid var(--navy);
        }
        .stat-card.green { border-top-color:var(--green); }
        .stat-card.red   { border-top-color:var(--red); }
        .stat-card.gold  { border-top-color:var(--gold); }
        .stat-card.teal  { border-top-color:var(--teal); }
        .stat-label { font-size:0.72rem; font-weight:600; letter-spacing:0.08em; text-transform:uppercase; color:var(--muted); margin-bottom:0.4rem; }
        .stat-num { font-size:2rem; font-weight:700; color:var(--navy); line-height:1; }

        /* TABLES */
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; }
        .card { background:white; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,0.06); overflow:hidden; }
        .card-header { padding:1rem 1.25rem; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
        .card-header h3 { font-size:0.95rem; font-weight:600; color:var(--navy); }
        .card-header a { font-size:0.8rem; color:var(--teal); text-decoration:none; font-weight:500; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fb; padding:0.6rem 1rem; font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.07em; color:var(--muted); text-align:left; }
        td { padding:0.7rem 1rem; font-size:0.85rem; border-top:1px solid #f0f0f0; vertical-align:middle; }
        tr:hover td { background:#fafbfc; }
        .badge {
            display:inline-block; padding:0.2rem 0.6rem; border-radius:20px;
            font-size:0.72rem; font-weight:700; letter-spacing:0.04em;
        }
        .badge-pending  { background:#fff3cd; color:#856404; }
        .badge-accepted { background:#d4edda; color:#155724; }
        .badge-rejected { background:#f8d7da; color:#721c24; }
        .badge-review   { background:#cce5ff; color:#004085; }
        .btn-sm {
            display:inline-block; padding:0.3rem 0.75rem; border-radius:6px;
            font-size:0.78rem; font-weight:600; text-decoration:none; cursor:pointer; border:none;
            background:var(--navy); color:white; transition:background 0.15s;
        }
        .btn-sm:hover { background:var(--teal); }
        @media(max-width:900px) { .grid-2 { grid-template-columns:1fr; } .sidebar { display:none; } .main { margin-left:0; } }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>IAMS Admin</h2>
        <p>University of Botswana</p>
    </div>
    <nav>
        <p class="nav-section">Overview</p>
        <a href="index.php" class="active"><span class="icon">📊</span> Dashboard</a>

        <p class="nav-section">Management</p>
        <a href="applications.php"><span class="icon">📋</span> Applications</a>
        <a href="students.php"><span class="icon">👩‍🎓</span> Students</a>
        <a href="jobs.php"><span class="icon">💼</span> Job Posts</a>
        <a href="documents.php"><span class="icon">📁</span> Documents</a>

        <p class="nav-section">System</p>
        <a href="../index.php"><span class="icon">🌐</span> View Site</a>
        <a href="../logout.php"><span class="icon">🚪</span> Logout</a>
    </nav>
    <div class="sidebar-footer">
        <strong><?php echo htmlspecialchars($user['name']); ?></strong>
        <?php echo ucfirst($user['role']); ?>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="topbar">
        <div>
            <h1>Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($user['name']); ?> — <?php echo date('l, j F Y'); ?></p>
        </div>
        <div class="topbar-right">
            <a href="applications.php" class="btn-sm">Review Applications</a>
            <div class="avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <p class="stat-label">Total Students</p>
            <p class="stat-num"><?php echo $stats['total_students']; ?></p>
        </div>
        <div class="stat-card gold">
            <p class="stat-label">Pending Review</p>
            <p class="stat-num"><?php echo $stats['pending']; ?></p>
        </div>
        <div class="stat-card green">
            <p class="stat-label">Accepted</p>
            <p class="stat-num"><?php echo $stats['accepted']; ?></p>
        </div>
        <div class="stat-card red">
            <p class="stat-label">Rejected</p>
            <p class="stat-num"><?php echo $stats['rejected']; ?></p>
        </div>
        <div class="stat-card teal">
            <p class="stat-label">Active Jobs</p>
            <p class="stat-num"><?php echo $stats['active_jobs']; ?></p>
        </div>
        <div class="stat-card">
            <p class="stat-label">Documents</p>
            <p class="stat-num"><?php echo $stats['total_docs']; ?></p>
        </div>
    </div>

    <!-- TABLES -->
    <div class="grid-2">
        <!-- Recent Applications -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Applications</h3>
                <a href="applications.php">View all →</a>
            </div>
            <table>
                <thead>
                    <tr><th>Student</th><th>Programme</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $app): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($app['full_name']); ?></strong><br>
                        <span style="color:var(--muted);font-size:0.78rem;"><?php echo htmlspecialchars($app['email']); ?></span>
                    </td>
                    <td style="color:var(--muted)"><?php echo htmlspecialchars(substr($app['programme'],0,25)); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $app['status'] === 'under_review' ? 'review' : $app['status']; ?>">
                            <?php echo strtoupper(str_replace('_',' ',$app['status'])); ?>
                        </span>
                    </td>
                    <td><a href="applications.php?action=view&id=<?php echo $app['app_id']; ?>" class="btn-sm">View</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:2rem;">No applications yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- New Registrations -->
        <div class="card">
            <div class="card-header">
                <h3>Recently Registered Students</h3>
                <a href="students.php">View all →</a>
            </div>
            <table>
                <thead>
                    <tr><th>Name</th><th>Student #</th><th>Registered</th></tr>
                </thead>
                <tbody>
                <?php foreach ($new_users as $u): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($u['full_name']); ?></strong><br>
                        <span style="color:var(--muted);font-size:0.78rem;"><?php echo htmlspecialchars($u['programme'] ?? ''); ?></span>
                    </td>
                    <td style="color:var(--muted)"><?php echo htmlspecialchars($u['student_number'] ?? '—'); ?></td>
                    <td style="color:var(--muted);font-size:0.8rem;"><?php echo date('j M Y', strtotime($u['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($new_users)): ?>
                <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:2rem;">No students yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>
