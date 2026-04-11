<?php
// admin/students.php — View all registered students
require_once '../config/database.php';
require_once '../config/admin_session.php';
requireAdmin();

$user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

// Toggle active/inactive
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE user_id=? AND role='student'")->execute([$_GET['toggle']]);
    header("Location: students.php"); exit();
}

$search = trim($_GET['q'] ?? '');
if ($search) {
    $stmt = $db->prepare("
        SELECT u.*, a.status as app_status, COUNT(d.doc_id) as doc_count
        FROM users u
        LEFT JOIN applications a ON u.user_id = a.user_id
        LEFT JOIN documents d ON u.user_id = d.user_id
        WHERE u.role='student' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.student_number LIKE ?)
        GROUP BY u.user_id ORDER BY u.created_at DESC
    ");
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $db->query("
        SELECT u.*, a.status as app_status, COUNT(d.doc_id) as doc_count
        FROM users u
        LEFT JOIN applications a ON u.user_id = a.user_id
        LEFT JOIN documents d ON u.user_id = d.user_id
        WHERE u.role='student'
        GROUP BY u.user_id ORDER BY u.created_at DESC
    ");
}
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — IAMS Admin</title>
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
        .toolbar{display:flex;gap:1rem;margin-bottom:1.25rem;align-items:center;}
        .search-box{display:flex;gap:0.5rem;flex:1;max-width:400px;}
        .search-box input{flex:1;padding:0.55rem 0.85rem;border:1px solid #ddd;border-radius:6px;font-size:0.88rem;}
        .btn{display:inline-block;padding:0.4rem 0.9rem;border-radius:6px;font-size:0.82rem;font-weight:600;text-decoration:none;background:var(--navy);color:white;border:none;cursor:pointer;}
        .btn:hover{background:var(--teal);}
        .card{background:white;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.06);overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        th{background:#f8f9fb;padding:0.65rem 1rem;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted);text-align:left;}
        td{padding:0.75rem 1rem;font-size:0.85rem;border-top:1px solid #f0f0f0;vertical-align:middle;}
        tr:hover td{background:#fafbfc;}
        .badge{display:inline-block;padding:0.2rem 0.55rem;border-radius:20px;font-size:0.7rem;font-weight:700;}
        .badge-pending{background:#fff3cd;color:#856404;}
        .badge-accepted{background:#d4edda;color:#155724;}
        .badge-rejected{background:#f8d7da;color:#721c24;}
        .badge-under_review{background:#cce5ff;color:#004085;}
        .badge-none{background:#f0f0f0;color:#888;}
        .badge-active{background:#d4edda;color:#155724;}
        .badge-inactive{background:#f8d7da;color:#721c24;}
        @media(max-width:900px){.sidebar{display:none;}.main{margin-left:0;}}
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo"><h2>IAMS Admin</h2><p>University of Botswana</p></div>
    <nav>
        <p class="nav-section">Overview</p><a href="index.php"><span>📊</span> Dashboard</a>
        <p class="nav-section">Management</p>
        <a href="applications.php"><span>📋</span> Applications</a>
        <a href="students.php" class="active"><span>👩‍🎓</span> Students</a>
        <a href="jobs.php"><span>💼</span> Job Posts</a>
        <a href="documents.php"><span>📁</span> Documents</a>
        <p class="nav-section">System</p>
        <a href="../index.php"><span>🌐</span> View Site</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
    <div class="sidebar-footer"><strong><?php echo htmlspecialchars($user['name']); ?></strong><?php echo ucfirst($user['role']); ?></div>
</aside>

<main class="main">
    <h1>Students</h1>
    <p class="sub"><?php echo count($students); ?> registered student<?php echo count($students) !== 1 ? 's' : ''; ?><?php echo $search ? " matching \"$search\"" : ''; ?></p>

    <div class="toolbar">
        <form method="GET" class="search-box">
            <input type="text" name="q" placeholder="Search by name, email, or student number..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn">Search</button>
            <?php if ($search): ?><a href="students.php" class="btn" style="background:#6c757d;">Clear</a><?php endif; ?>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr><th>Name</th><th>Student #</th><th>Programme</th><th>Email</th><th>Docs</th><th>Application</th><th>Status</th><th>Registered</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong></td>
                <td style="color:var(--muted)"><?php echo htmlspecialchars($s['student_number'] ?? '—'); ?></td>
                <td style="color:var(--muted);font-size:0.82rem;"><?php echo htmlspecialchars($s['programme'] ?? '—'); ?></td>
                <td style="color:var(--muted);font-size:0.82rem;"><?php echo htmlspecialchars($s['email']); ?></td>
                <td style="text-align:center"><?php echo $s['doc_count']; ?></td>
                <td>
                    <?php if ($s['app_status']): ?>
                    <span class="badge badge-<?php echo str_replace(' ','_',$s['app_status']); ?>"><?php echo strtoupper(str_replace('_',' ',$s['app_status'])); ?></span>
                    <?php else: ?>
                    <span class="badge badge-none">NONE</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?php echo $s['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $s['is_active'] ? 'ACTIVE' : 'DISABLED'; ?></span></td>
                <td style="color:var(--muted);font-size:0.78rem;"><?php echo date('j M Y', strtotime($s['created_at'])); ?></td>
                <td><a href="?toggle=<?php echo $s['user_id']; ?>" class="btn" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="return confirm('Toggle account status?')"><?php echo $s['is_active'] ? 'Disable' : 'Enable'; ?></a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?><tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No students found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
