<?php
// admin/documents.php — View all uploaded student documents
require_once '../config/database.php';
require_once '../config/admin_session.php';
requireAdmin();

$user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

$docs = $db->query("
    SELECT d.*, u.full_name, u.student_number, u.email
    FROM documents d
    JOIN users u ON d.user_id = u.user_id
    ORDER BY d.uploaded_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents — IAMS Admin</title>
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
        .card{background:white;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,0.06);overflow:hidden;}
        table{width:100%;border-collapse:collapse;}
        th{background:#f8f9fb;padding:0.65rem 1rem;font-size:0.72rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted);text-align:left;}
        td{padding:0.75rem 1rem;font-size:0.85rem;border-top:1px solid #f0f0f0;vertical-align:middle;}
        tr:hover td{background:#fafbfc;}
        .btn{display:inline-block;padding:0.3rem 0.75rem;border-radius:6px;font-size:0.78rem;font-weight:600;text-decoration:none;background:var(--navy);color:white;border:none;cursor:pointer;}
        .btn:hover{background:var(--teal);}
        .doc-type{background:#e8f0fe;color:#1a3a6a;padding:0.2rem 0.55rem;border-radius:4px;font-size:0.72rem;font-weight:600;}
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
        <a href="students.php"><span>👩‍🎓</span> Students</a>
        <a href="jobs.php"><span>💼</span> Job Posts</a>
        <a href="documents.php" class="active"><span>📁</span> Documents</a>
        <p class="nav-section">System</p>
        <a href="../index.php"><span>🌐</span> View Site</a>
        <a href="../logout.php"><span>🚪</span> Logout</a>
    </nav>
    <div class="sidebar-footer"><strong><?php echo htmlspecialchars($user['name']); ?></strong><?php echo ucfirst($user['role']); ?></div>
</aside>

<main class="main">
    <h1>Student Documents</h1>
    <p class="sub"><?php echo count($docs); ?> document<?php echo count($docs) !== 1 ? 's' : ''; ?> uploaded</p>
    <div class="card">
        <table>
            <thead><tr><th>Student</th><th>Student #</th><th>Document Type</th><th>Filename</th><th>Uploaded</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($docs as $doc): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($doc['full_name']); ?></strong><br><span style="color:var(--muted);font-size:0.78rem;"><?php echo htmlspecialchars($doc['email']); ?></span></td>
                <td style="color:var(--muted)"><?php echo htmlspecialchars($doc['student_number'] ?? '—'); ?></td>
                <td><span class="doc-type"><?php echo htmlspecialchars($doc['doc_type']); ?></span></td>
                <td style="color:var(--muted);font-size:0.82rem;"><?php echo htmlspecialchars($doc['filename']); ?></td>
                <td style="color:var(--muted);font-size:0.8rem;"><?php echo date('j M Y, H:i', strtotime($doc['uploaded_at'])); ?></td>
                <td><a href="../download.php?id=<?php echo $doc['doc_id']; ?>" class="btn" target="_blank">Download</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($docs)): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem;">No documents uploaded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
