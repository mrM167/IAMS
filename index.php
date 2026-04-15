<?php
// index.php — Public landing page, PHP 7.4 compatible
require_once __DIR__ . '/config/database.php';

$jobs = [];
try {
    $db   = Database::getInstance();
    $jobs = $db->query("SELECT * FROM job_posts WHERE is_active=1 ORDER BY created_at DESC LIMIT 12")->fetchAll();
} catch (Exception $e) {}

// Redirect if already logged in
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    if ($role === 'organisation') {
        header('Location: /org/dashboard.php');
    } elseif ($role === 'admin' || $role === 'coordinator') {
        header('Location: /admin/index.php');
    } else {
        header('Location: /dashboard.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>IAMS &mdash; Internship &amp; Attachment Management System | University of Botswana</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap');
:root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c;--light:#f0f4f8;--white:#fff;--text:#1a2733;--muted:#5a7080}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:var(--light);color:var(--text)}
header{background:var(--navy);padding:0 2rem;position:sticky;top:0;z-index:100;box-shadow:0 2px 16px rgba(0,0,0,.3)}
.header-inner{max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;height:68px}
.logo-area{display:flex;align-items:center;gap:.85rem;text-decoration:none}
.logo-text{font-family:'Playfair Display',serif;color:#fff;font-size:1.05rem;line-height:1.2}
.logo-text span{display:block;font-size:.6rem;font-family:'DM Sans',sans-serif;letter-spacing:.12em;text-transform:uppercase;color:var(--gold);font-weight:500}
nav{display:flex;gap:.25rem;align-items:center}
nav a{color:rgba(255,255,255,.8);text-decoration:none;padding:.45rem .9rem;border-radius:6px;font-size:.88rem;font-weight:500;transition:all .2s}
nav a:hover{color:#fff;background:rgba(255,255,255,.1)}
.btn-nav-gold{background:var(--gold)!important;color:var(--navy)!important;font-weight:700!important}
.hero{background:linear-gradient(135deg,var(--navy) 0%,var(--teal) 60%,#2a7a9a 100%);color:#fff;padding:6rem 2rem 5rem;position:relative;overflow:hidden}
.hero-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center}
.hero h1{font-family:'Playfair Display',serif;font-size:clamp(2.2rem,4vw,3.2rem);font-weight:900;line-height:1.15;margin-bottom:1.25rem}
.hero h1 em{font-style:normal;color:var(--gold)}
.hero p{font-size:1.05rem;line-height:1.7;opacity:.85;margin-bottom:1.75rem}
.hero-tagline{font-family:'Playfair Display',serif;font-style:italic;font-size:1.05rem;color:var(--gold);margin-bottom:1.5rem}
.hero-btns{display:flex;gap:1rem;flex-wrap:wrap}
.btn-hero{padding:.85rem 2rem;border-radius:8px;font-weight:700;font-size:.95rem;text-decoration:none;display:inline-block;transition:all .2s}
.btn-gold{background:var(--gold);color:var(--navy)}.btn-gold:hover{background:#e0bc60;transform:translateY(-2px)}
.btn-outline-w{background:transparent;color:#fff;border:2px solid rgba(255,255,255,.5)}.btn-outline-w:hover{border-color:#fff;background:rgba(255,255,255,.1)}
.hero-stats{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:16px;padding:2.25rem;backdrop-filter:blur(10px)}
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem}
.stat-item{text-align:center}
.stat-num{font-family:'Playfair Display',serif;font-size:2rem;font-weight:700;color:var(--gold)}
.stat-label{font-size:.78rem;opacity:.75;letter-spacing:.05em;text-transform:uppercase;margin-top:.25rem}
.partner-logos{margin-top:1.75rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,.15);text-align:center}
.partner-logos p{font-size:.72rem;opacity:.6;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.75rem}
.partner-badge{display:inline-block;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);padding:.35rem .9rem;border-radius:6px;font-size:.78rem;font-weight:600;letter-spacing:.05em;margin:.2rem}
.section{padding:5rem 2rem}
.section-white{background:#fff}
.section-header{text-align:center;max-width:600px;margin:0 auto 3rem}
.section-label-text{font-size:.72rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:var(--gold);margin-bottom:.6rem}
.section-header h2{font-family:'Playfair Display',serif;font-size:2rem;color:var(--navy);margin-bottom:.6rem}
.section-header p{color:var(--muted);line-height:1.6}
.steps-grid{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.75rem}
.step-card{text-align:center;padding:2rem 1.5rem;border-radius:12px;background:var(--light)}
.step-num{width:52px;height:52px;background:var(--navy);color:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;margin:0 auto 1rem}
.step-card h3{font-size:1rem;font-weight:600;color:var(--navy);margin-bottom:.5rem}
.step-card p{font-size:.875rem;color:var(--muted);line-height:1.6}
.jobs-grid{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.5rem}
.job-card{background:#fff;border-radius:12px;padding:1.5rem;box-shadow:0 2px 8px rgba(0,0,0,.07);border:1px solid rgba(0,0,0,.06);transition:all .25s}
.job-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(10,47,68,.12)}
.job-org{font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--teal);margin-bottom:.35rem}
.job-card h3{font-size:1rem;font-weight:600;color:var(--navy);margin-bottom:.65rem}
.job-meta{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:.85rem}
.job-tag{background:var(--light);color:var(--muted);padding:.2rem .55rem;border-radius:4px;font-size:.78rem}
.btn-interest{background:var(--navy);color:#fff;border:none;padding:.6rem 1.25rem;border-radius:7px;font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:background .2s}
.btn-interest:hover{background:var(--teal)}
.no-jobs{text-align:center;padding:3rem;color:var(--muted);background:#fff;border-radius:12px;grid-column:1/-1}
.register-section{padding:5rem 2rem;background:var(--navy);color:#fff}
.register-grid{max-width:900px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:2rem}
.reg-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:2rem;text-align:center}
.reg-card h3{font-family:'Playfair Display',serif;font-size:1.3rem;margin-bottom:.75rem}
.reg-card p{opacity:.8;font-size:.9rem;line-height:1.6;margin-bottom:1.5rem}
footer{background:var(--navy);color:rgba(255,255,255,.6);padding:3rem 2rem 1.5rem}
.footer-inner{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr;gap:3rem;margin-bottom:2rem}
.footer-brand h3{font-family:'Playfair Display',serif;color:#fff;font-size:1.05rem;margin-bottom:.5rem}
.footer-brand p{font-size:.85rem;line-height:1.6;margin-bottom:.75rem}
footer h4{color:#fff;font-size:.85rem;font-weight:600;margin-bottom:.85rem}
footer ul{list-style:none}
footer ul li{margin-bottom:.45rem}
footer ul li a{color:rgba(255,255,255,.6);text-decoration:none;font-size:.85rem;transition:color .2s}
footer ul li a:hover{color:var(--gold)}
.footer-bottom{max-width:1200px;margin:0 auto;padding-top:1.5rem;border-top:1px solid rgba(255,255,255,.1);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;font-size:.8rem}
@media(max-width:768px){.hero-inner,.register-grid,.footer-inner{grid-template-columns:1fr}.hero{padding:3.5rem 1.5rem 2.5rem}nav .hide-mob{display:none}}
</style>
</head>
<body>
<header>
  <div class="header-inner">
    <a href="/index.php" class="logo-area">
      <svg width="42" height="42" viewBox="0 0 42 42"><circle cx="21" cy="21" r="19" fill="none" stroke="#c9a84c" stroke-width="2"/><text x="21" y="27" text-anchor="middle" font-family="Georgia,serif" font-size="13" font-weight="bold" fill="white">UB</text></svg>
      <div class="logo-text">IAMS <span>University of Botswana</span></div>
    </a>
    <nav>
      <a href="#how-it-works" class="hide-mob">How It Works</a>
      <a href="#jobs" class="hide-mob">Browse Jobs</a>
      <a href="/register.php" class="hide-mob">Register</a>
      <a href="/register_org.php" class="hide-mob">Organisations</a>
      <a href="/login.php" class="btn-nav-gold">Login</a>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="hero-inner">
    <div>
      <p class="hero-tagline">"We give the pathway to future leaders"</p>
      <h1>Your <em>Career</em><br>Starts Here.</h1>
      <p>The Internship &amp; Attachment Management System connects University of Botswana students with the Ministry of Labour and Home Affairs and partner organisations for industrial attachment opportunities.</p>
      <div class="hero-btns">
        <a href="/register.php" class="btn-hero btn-gold">&#127891; Apply as Student</a>
        <a href="#jobs" class="btn-hero btn-outline-w">Browse Positions</a>
      </div>
    </div>
    <div>
      <div class="hero-stats">
        <div class="stat-grid">
          <div class="stat-item"><div class="stat-num">500+</div><div class="stat-label">Students Placed</div></div>
          <div class="stat-item"><div class="stat-num">50+</div><div class="stat-label">Partner Orgs</div></div>
          <div class="stat-item"><div class="stat-num">8</div><div class="stat-label">Departments</div></div>
          <div class="stat-item"><div class="stat-num">100%</div><div class="stat-label">Online Process</div></div>
        </div>
        <div class="partner-logos">
          <p>Key Partners</p>
          <span class="partner-badge">MLHA</span>
          <span class="partner-badge">DPSM</span>
          <span class="partner-badge">BURS</span>
          <span class="partner-badge">BOCRA</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section section-white" id="how-it-works">
  <div class="section-header">
    <p class="section-label-text">Process Overview</p>
    <h2>How IAMS Works</h2>
    <p>A simple four-step process to secure your industrial attachment placement.</p>
  </div>
  <div class="steps-grid">
    <div class="step-card"><div class="step-num">1</div><h3>Register</h3><p>Create your student account using your UB student number and university email address.</p></div>
    <div class="step-card"><div class="step-num">2</div><h3>Complete Profile</h3><p>Upload your CV, transcripts, and add your LinkedIn or portfolio links to stand out.</p></div>
    <div class="step-card"><div class="step-num">3</div><h3>Apply</h3><p>Submit your attachment application and express interest in available positions.</p></div>
    <div class="step-card"><div class="step-num">4</div><h3>Get Placed</h3><p>MLHA reviews your application and the coordinator contacts you with placement details.</p></div>
  </div>
</section>

<section class="section" id="jobs">
  <div class="section-header">
    <p class="section-label-text">Current Openings</p>
    <h2>Available Positions</h2>
    <p>Attachment opportunities from MLHA and partner organisations.</p>
  </div>
  <div class="jobs-grid">
    <?php if ($jobs): ?>
      <?php foreach ($jobs as $job): ?>
      <div class="job-card">
        <p class="job-org"><?php echo htmlspecialchars($job['organization']); ?></p>
        <h3><?php echo htmlspecialchars($job['title']); ?></h3>
        <div class="job-meta">
          <span class="job-tag">&#128205; <?php echo htmlspecialchars($job['location'] ?? '—'); ?></span>
          <?php if ($job['duration']): ?><span class="job-tag">&#8987; <?php echo htmlspecialchars($job['duration']); ?></span><?php endif; ?>
          <?php if ($job['salary_range']): ?><span class="job-tag">&#128176; <?php echo htmlspecialchars($job['salary_range']); ?></span><?php endif; ?>
          <span class="job-tag">&#128101; <?php echo (int)$job['slots']; ?> slot(s)</span>
        </div>
        <?php if ($job['description']): ?>
        <p style="font-size:.83rem;color:var(--muted);margin-bottom:.85rem;line-height:1.5"><?php echo htmlspecialchars(substr($job['description'], 0, 110)) . (strlen($job['description']) > 110 ? '...' : ''); ?></p>
        <?php endif; ?>
        <a href="/login.php" class="btn-interest">Express Interest &rarr;</a>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-jobs">
        <h3>Positions will appear here</h3>
        <p>Register now to be ready when positions open.</p>
        <a href="/register.php" class="btn-interest" style="margin-top:1rem;display:inline-block">Register Now</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="register-section">
  <div class="section-header" style="margin-bottom:2rem">
    <p class="section-label-text" style="color:var(--gold)">Get Started</p>
    <h2 style="color:#fff">Join IAMS Today</h2>
    <p style="color:rgba(255,255,255,.7)">Register as a student or register your organisation to host interns.</p>
  </div>
  <div class="register-grid">
    <div class="reg-card">
      <div style="font-size:2.5rem;margin-bottom:.75rem">&#127891;</div>
      <h3>Students</h3>
      <p>Register with your UB student number, complete your profile, upload documents, and apply for attachment positions.</p>
      <a href="/register.php" style="display:inline-block;padding:.75rem 2rem;border-radius:8px;font-weight:700;text-decoration:none;background:var(--gold);color:var(--navy)">Register as Student &rarr;</a>
    </div>
    <div class="reg-card">
      <div style="font-size:2.5rem;margin-bottom:.75rem">&#127970;</div>
      <h3>Organisations</h3>
      <p>Register your organisation, specify the skills and profiles you need, and the system will match you with suitable students.</p>
      <a href="/register_org.php" style="display:inline-block;padding:.75rem 2rem;border-radius:8px;font-weight:700;text-decoration:none;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3)">Register Organisation &rarr;</a>
    </div>
  </div>
</section>

<footer>
  <div class="footer-inner">
    <div class="footer-brand">
      <h3>IAMS &mdash; Internship &amp; Attachment Management System</h3>
      <p>A platform by the University of Botswana in partnership with the Ministry of Labour and Home Affairs (MLHA).</p>
      <p style="color:var(--gold);font-style:italic;font-family:'Playfair Display',serif">"We give the pathway to future leaders"</p>
    </div>
    <div>
      <h4>Quick Links</h4>
      <ul>
        <li><a href="/register.php">Student Registration</a></li>
        <li><a href="/register_org.php">Organisation Registration</a></li>
        <li><a href="/login.php">Login</a></li>
        <li><a href="#jobs">Browse Jobs</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
      </ul>
    </div>
    <div>
      <h4>Contact</h4>
      <ul>
        <li><a href="#">University of Botswana</a></li>
        <li><a href="#">Private Bag 0022, Gaborone</a></li>
        <li><a href="mailto:iams@ub.ac.bw">iams@ub.ac.bw</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <span>&copy; <?php echo date('Y'); ?> University of Botswana &middot; IAMS. All rights reserved.</span>
    <span>Ministry of Labour &amp; Home Affairs Partnership</span>
  </div>
</footer>
</body>
</html>
