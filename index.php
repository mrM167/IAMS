<?php
// index.php - Main landing page for IAMS
require_once 'config/database.php';

// Try to get job posts; gracefully degrade if DB is unavailable
$jobs = [];
try {
    $database = new Database();
    $db = $database->getConnection();
    if ($db) {
        $stmt = $db->query("SELECT * FROM job_posts WHERE is_active = 1 ORDER BY created_at DESC LIMIT 12");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // DB not ready yet; show empty jobs list
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IAMS - Internship & Attachment Management System | University of Botswana</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap');

        :root {
            --navy: #0a2f44;
            --teal: #1a5a7a;
            --gold: #c9a84c;
            --light: #f0f4f8;
            --white: #ffffff;
            --text: #1a2733;
            --muted: #5a7080;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--light);
            color: var(--text);
        }

        /* ===== HEADER ===== */
        header {
            background: var(--navy);
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 16px rgba(0,0,0,0.3);
        }
        .header-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo-text {
            font-family: 'Playfair Display', serif;
            color: var(--white);
            font-size: 1.1rem;
            line-height: 1.2;
        }
        .logo-text span {
            display: block;
            font-size: 0.65rem;
            font-family: 'DM Sans', sans-serif;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 500;
        }
        nav { display: flex; gap: 0.5rem; align-items: center; }
        nav a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        nav a:hover { color: white; background: rgba(255,255,255,0.1); }
        .btn-login {
            background: var(--gold) !important;
            color: var(--navy) !important;
            font-weight: 600 !important;
            padding: 0.5rem 1.25rem !important;
        }
        .btn-login:hover { background: #e0bc60 !important; }

        /* ===== HERO ===== */
        .hero {
            background: linear-gradient(135deg, var(--navy) 0%, var(--teal) 60%, #2a7a9a 100%);
            color: white;
            padding: 6rem 2rem 5rem;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
        }
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.2rem, 4vw, 3.2rem);
            font-weight: 900;
            line-height: 1.15;
            margin-bottom: 1.5rem;
        }
        .hero h1 em {
            font-style: normal;
            color: var(--gold);
        }
        .hero p {
            font-size: 1.05rem;
            line-height: 1.7;
            opacity: 0.85;
            margin-bottom: 2rem;
        }
        .hero-tagline {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 1.1rem;
            color: var(--gold);
            margin-bottom: 2rem;
        }
        .hero-btns { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn-primary {
            background: var(--gold);
            color: var(--navy);
            text-decoration: none;
            padding: 0.85rem 2rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-primary:hover { background: #e0bc60; transform: translateY(-2px); }
        .btn-outline {
            background: transparent;
            color: white;
            text-decoration: none;
            padding: 0.85rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid rgba(255,255,255,0.5);
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-outline:hover { border-color: white; background: rgba(255,255,255,0.1); }
        .hero-stats {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 2.5rem;
            backdrop-filter: blur(10px);
        }
        .stat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .stat-item { text-align: center; }
        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--gold);
        }
        .stat-label { font-size: 0.8rem; opacity: 0.75; letter-spacing: 0.05em; text-transform: uppercase; margin-top: 0.25rem; }
        .partner-logos {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.15);
            text-align: center;
        }
        .partner-logos p { font-size: 0.75rem; opacity: 0.6; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.75rem; }
        .partner-badge {
            display: inline-block;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 0.4rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            margin: 0.25rem;
        }

        /* ===== HOW IT WORKS ===== */
        .how-it-works {
            background: white;
            padding: 5rem 2rem;
        }
        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 3rem;
        }
        .section-header .label {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 0.75rem;
        }
        .section-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--navy);
            margin-bottom: 0.75rem;
        }
        .section-header p { color: var(--muted); line-height: 1.6; }

        .steps-grid {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }
        .step-card {
            text-align: center;
            padding: 2rem 1.5rem;
            border-radius: 12px;
            background: var(--light);
            position: relative;
        }
        .step-num {
            width: 48px;
            height: 48px;
            background: var(--navy);
            color: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 auto 1rem;
        }
        .step-card h3 { font-size: 1rem; font-weight: 600; color: var(--navy); margin-bottom: 0.5rem; }
        .step-card p { font-size: 0.875rem; color: var(--muted); line-height: 1.6; }

        /* ===== JOBS SECTION ===== */
        .jobs-section {
            padding: 5rem 2rem;
            background: var(--light);
        }
        .jobs-container { max-width: 1200px; margin: 0 auto; }
        #jobsGrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .job-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.06);
            transition: all 0.25s;
        }
        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(10,47,68,0.12);
        }
        .job-org {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--teal);
            margin-bottom: 0.4rem;
        }
        .job-card h3 { font-size: 1rem; font-weight: 600; color: var(--navy); margin-bottom: 0.75rem; }
        .job-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
        .job-tag {
            background: var(--light);
            color: var(--muted);
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.78rem;
        }
        .job-salary { font-weight: 600; color: var(--navy); font-size: 0.9rem; }
        .job-actions { margin-top: 1rem; display: flex; gap: 0.75rem; }
        .btn-interest {
            background: var(--navy);
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 7px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn-interest:hover { background: var(--teal); }

        .no-jobs {
            text-align: center;
            padding: 3rem;
            color: var(--muted);
            background: white;
            border-radius: 12px;
            grid-column: 1/-1;
        }
        .no-jobs h3 { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--navy); }

        /* ===== FOOTER ===== */
        footer {
            background: var(--navy);
            color: rgba(255,255,255,0.7);
            padding: 3rem 2rem 1.5rem;
        }
        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 2rem;
        }
        .footer-brand h3 {
            font-family: 'Playfair Display', serif;
            color: white;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .footer-brand p { font-size: 0.85rem; line-height: 1.6; margin-bottom: 1rem; }
        footer h4 { color: white; font-size: 0.85rem; font-weight: 600; margin-bottom: 1rem; letter-spacing: 0.05em; }
        footer ul { list-style: none; }
        footer ul li { margin-bottom: 0.5rem; }
        footer ul li a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
        footer ul li a:hover { color: var(--gold); }
        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.8rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .hero-inner { grid-template-columns: 1fr; gap: 2rem; }
            .hero { padding: 4rem 1.5rem 3rem; }
            .footer-inner { grid-template-columns: 1fr; gap: 2rem; }
            nav .hide-mobile { display: none; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="header-inner">
        <div class="logo-area">
            <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="20" r="18" fill="none" stroke="#c9a84c" stroke-width="2"/>
                <text x="20" y="25" text-anchor="middle" font-family="Georgia,serif" font-size="12" font-weight="bold" fill="white">UB</text>
            </svg>
            <div class="logo-text">
                IAMS
                <span>University of Botswana</span>
            </div>
        </div>
        <nav>
            <a href="#jobsGrid" class="hide-mobile">Browse Jobs</a>
            <a href="#how-it-works" class="hide-mobile">How It Works</a>
            <a href="register.php" class="hide-mobile">Register</a>
            <a href="login.php" class="btn-login">Login</a>
        </nav>
    </div>
</header>

<!-- HERO -->
<section class="hero">
    <div class="hero-inner">
        <div>
            <p class="hero-tagline">"We give the pathway to future leaders"</p>
            <h1>Your <em>Career</em><br>Starts Here.</h1>
            <p>The Internship & Attachment Management System connects University of Botswana students with the Ministry of Labour and Home Affairs and partner organisations for industrial attachment opportunities.</p>
            <div class="hero-btns">
                <a href="register.php" class="btn-primary">Apply for Attachment</a>
                <a href="#jobsGrid" class="btn-outline">Browse Positions</a>
            </div>
        </div>
        <div>
            <div class="hero-stats">
                <div class="stat-grid">
                    <div class="stat-item">
                        <div class="stat-num">500+</div>
                        <div class="stat-label">Students Placed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">50+</div>
                        <div class="stat-label">Partner Orgs</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">8</div>
                        <div class="stat-label">Departments</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">100%</div>
                        <div class="stat-label">Online Process</div>
                    </div>
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

<!-- HOW IT WORKS -->
<section class="how-it-works" id="how-it-works">
    <div class="section-header">
        <p class="label">Process Overview</p>
        <h2>How IAMS Works</h2>
        <p>A simple four-step process to secure your industrial attachment placement.</p>
    </div>
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-num">1</div>
            <h3>Register</h3>
            <p>Create your student account using your UB student number and university email address.</p>
        </div>
        <div class="step-card">
            <div class="step-num">2</div>
            <h3>Complete Profile</h3>
            <p>Upload your CV, transcripts, and add your LinkedIn or portfolio links to stand out.</p>
        </div>
        <div class="step-card">
            <div class="step-num">3</div>
            <h3>Apply</h3>
            <p>Submit your attachment application and express interest in available positions.</p>
        </div>
        <div class="step-card">
            <div class="step-num">4</div>
            <h3>Get Placed</h3>
            <p>MLHA reviews your application and the coordinator contacts you with placement details.</p>
        </div>
    </div>
</section>

<!-- JOBS SECTION -->
<section class="jobs-section">
    <div class="jobs-container">
        <div class="section-header">
            <p class="label">Current Openings</p>
            <h2>Available Positions</h2>
            <p>Attachment opportunities from the Ministry of Labour and Home Affairs and partner organisations.</p>
        </div>
        <div id="jobsGrid">
            <?php if (!empty($jobs)): ?>
                <?php foreach ($jobs as $job): ?>
                <div class="job-card">
                    <p class="job-org"><?php echo htmlspecialchars($job['organization']); ?></p>
                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                    <div class="job-meta">
                        <span class="job-tag">📍 <?php echo htmlspecialchars($job['location']); ?></span>
                        <?php if (!empty($job['duration'])): ?>
                        <span class="job-tag">⏱ <?php echo htmlspecialchars($job['duration']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($job['salary_range'])): ?>
                    <p class="job-salary"><?php echo htmlspecialchars($job['salary_range']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($job['description'])): ?>
                    <p style="font-size:0.85rem;color:#5a7080;margin-top:0.5rem;line-height:1.5;"><?php echo htmlspecialchars(substr($job['description'], 0, 120)) . (strlen($job['description']) > 120 ? '...' : ''); ?></p>
                    <?php endif; ?>
                    <div class="job-actions">
                        <a href="login.php" class="btn-interest">Express Interest</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-jobs">
                    <h3>Positions will appear here</h3>
                    <p>Job listings are posted by coordinators through the admin panel. Register now to be ready when positions open.</p>
                    <a href="register.php" class="btn-interest" style="margin-top:1rem;display:inline-block;">Register Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-brand">
            <h3>IAMS — Internship & Attachment Management System</h3>
            <p>A platform by the University of Botswana in partnership with the Ministry of Labour and Home Affairs (MLHA) to facilitate student industrial attachment placements.</p>
            <p style="color:var(--gold);font-style:italic;font-family:'Playfair Display',serif;">"We give the pathway to future leaders"</p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul>
                <li><a href="register.php">Register</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="#jobsGrid">Browse Jobs</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
            </ul>
        </div>
        <div>
            <h4>Contact</h4>
            <ul>
                <li><a href="#">University of Botswana</a></li>
                <li><a href="#">Private Bag 0022</a></li>
                <li><a href="#">Gaborone, Botswana</a></li>
                <li><a href="mailto:iams@ub.ac.bw">iams@ub.ac.bw</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; <?php echo date('Y'); ?> University of Botswana · IAMS. All rights reserved.</span>
        <span>Ministry of Labour &amp; Home Affairs Partnership</span>
    </div>
</footer>

</body>
</html>
