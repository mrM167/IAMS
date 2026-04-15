<?php
// includes/header.php — Shared authenticated header & nav
$user = getCurrentUser();
$role = $user['role'];
$unread = 0;
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$user['id']]);
    $unread = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
$isAdmin = ($role === 'admin');
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
  /* NAV */
  .topnav{background:var(--navy);padding:0 1.25rem;position:sticky;top:0;z-index:200;box-shadow:0 2px 12px rgba(0,0,0,.3)}
  .topnav-inner{max-width:1400px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;height:58px}
  .nav-brand{display:flex;align-items:center;gap:.65rem;text-decoration:none}
  .nav-brand-text{font-family:'Playfair Display',serif;color:#fff;font-size:.95rem;line-height:1.1}
  .nav-brand-text span{display:block;font-size:.55rem;font-family:'DM Sans',sans-serif;letter-spacing:.1em;text-transform:uppercase;color:var(--gold)}
  .nav-links{display:flex;gap:.15rem;align-items:center;flex-wrap:wrap}
  .nav-links a{color:rgba(255,255,255,.75);text-decoration:none;padding:.38rem .72rem;border-radius:5px;font-size:.82rem;font-weight:500;transition:all .15s;white-space:nowrap}
  .nav-links a:hover,.nav-links a.active{color:#fff;background:rgba(255,255,255,.1)}
  .btn-gold-nav{background:var(--gold)!important;color:var(--navy)!important;font-weight:700!important;padding:.38rem .85rem!important}
  .btn-gold-nav:hover{background:#e0bc60!important}
  .notif-badge{background:var(--red);color:#fff;border-radius:50%;padding:.08rem .32rem;font-size:.62rem;font-weight:700;margin-left:.2rem;vertical-align:middle}
  /* LAYOUT */
  .page-wrap{max-width:1400px;margin:1.75rem auto;padding:0 1.25rem;width:100%;flex:1}
  /* CARDS */
  .card{background:var(--white);border-radius:11px;box-shadow:0 1px 6px rgba(0,0,0,.07);overflow:hidden}
  .card-header{padding:.9rem 1.25rem;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
  .card-header h3{font-size:.9rem;font-weight:600;color:var(--navy)}
  .card-body{padding:1.25rem}
  /* TABLES */
  table{width:100%;border-collapse:collapse}
  th{background:#f8f9fb;padding:.6rem 1rem;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);text-align:left}
  td{padding:.7rem 1rem;font-size:.84rem;border-top:1px solid #f0f0f0;vertical-align:middle}
  tr:hover td{background:#fafbfc}
  /* BADGES */
  .badge{display:inline-block;padding:.18rem .55rem;border-radius:20px;font-size:.7rem;font-weight:700}
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
  .btn{display:inline-block;padding:.42rem .95rem;border-radius:7px;font-size:.8rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .15s}
  .btn-primary{background:var(--navy);color:#fff}.btn-primary:hover{background:var(--teal)}
  .btn-gold{background:var(--gold);color:var(--navy)}.btn-gold:hover{background:#e0bc60}
  .btn-red{background:var(--red);color:#fff}.btn-red:hover{background:#a93226}
  .btn-green{background:var(--green);color:#fff}.btn-green:hover{background:#155724}
  .btn-outline{background:transparent;color:var(--navy);border:1px solid var(--navy)}.btn-outline:hover{background:var(--navy);color:#fff}
  .btn-sm{padding:.22rem .55rem;font-size:.73rem}
  /* FORMS */
  .form-group{margin-bottom:1rem}
  .form-group label{display:block;font-size:.78rem;font-weight:600;margin-bottom:.28rem;color:#374151}
  .form-group input,.form-group textarea,.form-group select{width:100%;padding:.62rem .82rem;border:1px solid #ddd;border-radius:7px;font-size:.88rem;font-family:inherit;transition:border .15s}
  .form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(26,90,122,.1)}
  .form-group textarea{resize:vertical}
  /* STAT CARDS */
  .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:1.1rem;margin-bottom:1.75rem}
  .stat-card{background:var(--white);border-radius:11px;padding:1.1rem 1.4rem;box-shadow:0 1px 6px rgba(0,0,0,.06);border-top:3px solid var(--navy)}
  .stat-card.gold{border-top-color:var(--gold)}.stat-card.green{border-top-color:var(--green)}.stat-card.red{border-top-color:var(--red)}.stat-card.teal{border-top-color:var(--teal)}
  .stat-label{font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:.35rem}
  .stat-num{font-size:1.9rem;font-weight:700;color:var(--navy);line-height:1}
  /* ALERTS */
  .alert{padding:.72rem 1rem;border-radius:8px;margin-bottom:.9rem;font-size:.88rem}
  .alert-success{background:#d4edda;color:#155724}
  .alert-error{background:#f8d7da;color:#721c24}
  .alert-info{background:#d1ecf1;color:#0c5460}
  .alert-warning{background:#fff3cd;color:#856404}
  /* GRID */
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1.4rem}
  .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.4rem}
  .text-muted{color:var(--muted);font-size:.83rem}
  .page-title{font-size:1.35rem;font-weight:700;color:var(--navy);margin-bottom:.2rem}
  .page-sub{color:var(--muted);font-size:.83rem;margin-bottom:1.35rem}
  footer.site-footer{background:var(--navy);color:rgba(255,255,255,.5);text-align:center;padding:.85rem;font-size:.78rem;margin-top:auto}
  code{background:#f0f4f8;padding:.18rem .42rem;border-radius:3px;font-size:.82rem}
  @media(max-width:900px){.grid-2,.grid-3{grid-template-columns:1fr}.page-wrap{padding:0 .85rem}}
  @media(max-width:640px){.nav-links .hm{display:none}.topnav-inner{height:54px}}
  </style>
</head>
<body>
<nav class="topnav">
  <div class="topnav-inner">
    <a href="/index.php" class="nav-brand">
      <svg width="34" height="34" viewBox="0 0 34 34"><circle cx="17" cy="17" r="16" fill="none" stroke="#c9a84c" stroke-width="1.5"/><text x="17" y="22" text-anchor="middle" font-family="Georgia,serif" font-size="10" font-weight="bold" fill="white">UB</text></svg>
      <div class="nav-brand-text">IAMS <span>University of Botswana</span></div>
    </a>
    <div class="nav-links">
      <?php if ($role === 'student'): ?>
        <a href="/dashboard.php" class="hm">Dashboard</a>
        <a href="/dashboard.php?tab=apply" class="hm">Apply</a>
        <a href="/dashboard.php?tab=jobs" class="hm">Jobs</a>
        <a href="/logbook.php" class="hm">Logbook</a>
        <a href="/student_report.php" class="hm">Report</a>
      <?php elseif ($role === 'organisation'): ?>
        <a href="/org/dashboard.php">Dashboard</a>
        <a href="/org/dashboard.php?tab=students" class="hm">My Students</a>
        <a href="/org/dashboard.php?tab=jobs" class="hm">Job Posts</a>
        <a href="/supervisor_report.php" class="hm">Sup. Report</a>
      <?php elseif (in_array($role, ['admin','coordinator'])): ?>
        <a href="/admin/index.php" class="hm">Dashboard</a>
        <a href="/admin/students.php" class="hm">Students</a>
        <a href="/admin/organisations.php" class="hm">Orgs</a>
        <a href="/admin/matching.php" class="hm">Matching</a>
        <a href="/admin/applications.php" class="hm">Apps</a>
        <a href="/admin/jobs.php" class="hm">Jobs</a>
        <a href="/admin/logbooks.php" class="hm">Logbooks</a>
        <a href="/admin/assessments.php" class="hm">Assess.</a>
        <a href="/admin/reports.php" class="hm">Reports</a>
        <a href="/admin/reminders.php" class="hm">&#128276;</a>
        <?php if ($isAdmin): ?><a href="/admin/users.php" class="hm">&#128101; Users</a><?php endif; ?>
      <?php endif; ?>
      <a href="/notifications.php">&#128276;<?php if ($unread): ?><span class="notif-badge"><?php echo $unread; ?></span><?php endif; ?></a>
      <a href="/logout.php" class="btn-gold-nav btn">Logout</a>
    </div>
  </div>
</nav>