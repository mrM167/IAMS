<?php
// includes/header.php — Shared nav for all authenticated pages
// Usage: include it after requireLogin() so $user is available
$user = getCurrentUser();
$role = $user['role'];
$unread = 0;
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$user['id']]);
    $unread = (int)$stmt->fetchColumn();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — IAMS' : 'IAMS'; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap');
        :root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c;--light:#f0f4f8;--white:#fff;--muted:#5a7080;--red:#c0392b;--green:#1a7a4a}
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'DM Sans',sans-serif;background:var(--light);color:#1a2733;display:flex;flex-direction:column;min-height:100vh}
        /* TOP NAV */
        .topnav{background:var(--navy);padding:0 1.5rem;position:sticky;top:0;z-index:200;box-shadow:0 2px 12px rgba(0,0,0,.3)}
        .topnav-inner{max-width:1400px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;height:60px}
        .nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none}
        .nav-brand-text{font-family:'Playfair Display',serif;color:var(--white);font-size:1rem;line-height:1.1}
        .nav-brand-text span{display:block;font-size:.6rem;font-family:'DM Sans',sans-serif;letter-spacing:.1em;text-transform:uppercase;color:var(--gold)}
        .nav-links{display:flex;gap:.25rem;align-items:center}
        .nav-links a{color:rgba(255,255,255,.75);text-decoration:none;padding:.4rem .85rem;border-radius:6px;font-size:.85rem;font-weight:500;transition:all .15s;display:flex;align-items:center;gap:.35rem}
        .nav-links a:hover,.nav-links a.active{color:#fff;background:rgba(255,255,255,.1)}
        .nav-links .btn-gold{background:var(--gold);color:var(--navy) !important;font-weight:700}
        .nav-links .btn-gold:hover{background:#e0bc60}
        .notif-badge{background:var(--red);color:#fff;border-radius:50%;padding:.1rem .35rem;font-size:.65rem;font-weight:700;margin-left:.2rem}
        /* LAYOUT */
        .page-wrap{max-width:1400px;margin:2rem auto;padding:0 1.5rem;width:100%;flex:1}
        /* CARDS */
        .card{background:var(--white);border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.07);overflow:hidden}
        .card-header{padding:1rem 1.25rem;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
        .card-header h3{font-size:.95rem;font-weight:600;color:var(--navy)}
        .card-body{padding:1.25rem}
        /* TABLES */
        table{width:100%;border-collapse:collapse}
        th{background:#f8f9fb;padding:.65rem 1rem;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);text-align:left}
        td{padding:.75rem 1rem;font-size:.85rem;border-top:1px solid #f0f0f0;vertical-align:middle}
        tr:hover td{background:#fafbfc}
        /* BADGES */
        .badge{display:inline-block;padding:.2rem .6rem;border-radius:20px;font-size:.72rem;font-weight:700}
        .badge-pending{background:#fff3cd;color:#856404}
        .badge-under_review{background:#cce5ff;color:#004085}
        .badge-matched{background:#d1ecf1;color:#0c5460}
        .badge-accepted{background:#d4edda;color:#155724}
        .badge-rejected{background:#f8d7da;color:#721c24}
        .badge-active{background:#d4edda;color:#155724}
        .badge-inactive{background:#f8d7da;color:#721c24}
        .badge-student{background:#e8f0fe;color:#1a3a6a}
        .badge-organisation{background:#fff3e0;color:#e65100}
        .badge-coordinator{background:#e8f5e9;color:#1b5e20}
        .badge-admin{background:#fce4ec;color:#880e4f}
        /* BUTTONS */
        .btn{display:inline-block;padding:.45rem 1rem;border-radius:7px;font-size:.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .15s}
        .btn-primary{background:var(--navy);color:#fff}.btn-primary:hover{background:var(--teal)}
        .btn-gold{background:var(--gold);color:var(--navy)}.btn-gold:hover{background:#e0bc60}
        .btn-red{background:var(--red);color:#fff}.btn-red:hover{background:#a93226}
        .btn-green{background:var(--green);color:#fff}.btn-green:hover{background:#155724}
        .btn-outline{background:transparent;color:var(--navy);border:1px solid var(--navy)}.btn-outline:hover{background:var(--navy);color:#fff}
        .btn-sm{padding:.25rem .6rem;font-size:.75rem}
        /* FORMS */
        .form-group{margin-bottom:1rem}
        .form-group label{display:block;font-size:.8rem;font-weight:600;margin-bottom:.3rem;color:#374151}
        .form-group input,.form-group textarea,.form-group select{width:100%;padding:.65rem .85rem;border:1px solid #ddd;border-radius:7px;font-size:.9rem;font-family:inherit;transition:border .15s}
        .form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(26,90,122,.1)}
        .form-group textarea{resize:vertical}
        /* STAT CARDS */
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1.25rem;margin-bottom:2rem}
        .stat-card{background:var(--white);border-radius:12px;padding:1.25rem 1.5rem;box-shadow:0 1px 6px rgba(0,0,0,.06);border-top:3px solid var(--navy)}
        .stat-card.gold{border-top-color:var(--gold)}.stat-card.green{border-top-color:var(--green)}.stat-card.red{border-top-color:var(--red)}.stat-card.teal{border-top-color:var(--teal)}
        .stat-label{font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:.4rem}
        .stat-num{font-size:2rem;font-weight:700;color:var(--navy);line-height:1}
        /* ALERTS */
        .alert{padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.9rem}
        .alert-success{background:#d4edda;color:#155724}.alert-error{background:#f8d7da;color:#721c24}
        .alert-info{background:#d1ecf1;color:#0c5460}.alert-warning{background:#fff3cd;color:#856404}
        /* MISC */
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
        .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem}
        .text-muted{color:var(--muted);font-size:.85rem}
        .page-title{font-size:1.4rem;font-weight:700;color:var(--navy);margin-bottom:.25rem}
        .page-sub{color:var(--muted);font-size:.85rem;margin-bottom:1.5rem}
        .msg-box{padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem}
        footer.site-footer{background:var(--navy);color:rgba(255,255,255,.5);text-align:center;padding:1rem;font-size:.8rem;margin-top:auto}
        @media(max-width:768px){.grid-2,.grid-3{grid-template-columns:1fr}.nav-links .hide-mob{display:none}.page-wrap{padding:0 1rem}}
    </style>
</head>
<body>
<nav class="topnav">
  <div class="topnav-inner">
    <a href="/index.php" class="nav-brand">
      <svg width="36" height="36" viewBox="0 0 36 36"><circle cx="18" cy="18" r="17" fill="none" stroke="#c9a84c" stroke-width="1.5"/><text x="18" y="23" text-anchor="middle" font-family="Georgia,serif" font-size="11" font-weight="bold" fill="white">UB</text></svg>
      <div class="nav-brand-text">IAMS <span>University of Botswana</span></div>
    </a>
    <div class="nav-links">
      <?php if ($role === 'student'): ?>
        <a href="/dashboard.php" class="hide-mob">Dashboard</a>
        <a href="/dashboard.php?tab=apply" class="hide-mob">Apply</a>
        <a href="/dashboard.php?tab=jobs" class="hide-mob">Jobs</a>
        <?php if (defined('RELEASE2')): ?>
        <a href="/logbook.php" class="hide-mob">Logbook</a>
        <?php endif; ?>
      <?php elseif ($role === 'organisation'): ?>
        <a href="/org/dashboard.php">Dashboard</a>
        <a href="/org/profile.php" class="hide-mob">Profile</a>
        <a href="/org/students.php" class="hide-mob">Matched Students</a>
      <?php elseif (in_array($role, ['admin','coordinator'])): ?>
        <a href="/admin/index.php">Dashboard</a>
        <a href="/admin/students.php" class="hide-mob">Students</a>
        <a href="/admin/organisations.php" class="hide-mob">Organisations</a>
        <a href="/admin/matching.php" class="hide-mob">Matching</a>
        <a href="/admin/applications.php" class="hide-mob">Applications</a>
        <a href="/admin/jobs.php" class="hide-mob">Jobs</a>
      <?php endif; ?>
      <a href="/notifications.php">
        🔔<?php if ($unread): ?><span class="notif-badge"><?php echo $unread; ?></span><?php endif; ?>
      </a>
      <a href="/logout.php" class="btn-gold btn">Logout</a>
    </div>
  </div>
</nav>
