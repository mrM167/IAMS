<?php
// dashboard.php
require_once 'config/database.php';
require_once 'config/session.php';
requireLogin();

$user = getCurrentUser();
$database = new Database();
$db = $database->getConnection();

// Get user's application
$app_stmt = $db->prepare("SELECT * FROM applications WHERE user_id = ?");
$app_stmt->execute([$user['id']]);
$application = $app_stmt->fetch(PDO::FETCH_ASSOC);

// Get user's documents
$doc_stmt = $db->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$doc_stmt->execute([$user['id']]);
$documents = $doc_stmt->fetchAll();

// Get user's profile
$profile_stmt = $db->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$profile_stmt->execute([$user['id']]);
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);

// Get user's job interests
$interests_stmt = $db->prepare("SELECT j.* FROM job_interests ji JOIN job_posts j ON ji.job_id = j.job_id WHERE ji.user_id = ?");
$interests_stmt->execute([$user['id']]);
$interests = $interests_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IAMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
        }
        .header {
            background: #0a2f44;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .logo { display: flex; align-items: center; gap: 1rem; }
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.1); }
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .welcome-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .mission-text {
            font-style: italic;
            color: #0a2f44;
            margin-top: 0.5rem;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .card h3 {
            margin-bottom: 1rem;
            color: #0a2f44;
            border-bottom: 2px solid #eee;
            padding-bottom: 0.5rem;
        }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.3rem; }
        input, textarea, select {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        button {
            background: #0a2f44;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .success-msg { background: #d4edda; color: #155724; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .file-list { margin-top: 1rem; }
        .file-item {
            background: #f8f9fa;
            padding: 0.5rem;
            margin: 0.5rem 0;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
        }
        .interest-item {
            background: #e8f0fe;
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 8px;
        }
        @media (max-width: 700px) {
            .grid-2 { grid-template-columns: 1fr; }
            .container { padding: 0 1rem; }
        }
    </style>
</head>
<body>
<div class="header">
    <div class="logo">
        <svg width="100" height="40" viewBox="0 0 300 90" xmlns="http://www.w3.org/2000/svg">
            <rect width="300" height="90" fill="white" rx="8" ry="8"/>
            <text x="15" y="38" font-family="Georgia, serif" font-size="18" font-weight="bold" fill="#0a2f44">UNIVERSITY</text>
            <text x="15" y="68" font-family="Georgia, serif" font-size="18" font-weight="bold" fill="#0a2f44">OF BOTSWANA</text>
        </svg>
        <h2>IAMS Dashboard</h2>
    </div>
    <div class="nav-links">
        <span>Welcome, <?php echo htmlspecialchars($user['name']); ?></span>
        <a href="dashboard.php">Dashboard</a>
        <a href="index.php">Home</a>
        <?php if (in_array($user['role'], ['admin','coordinator'])): ?>
        <a href="admin/index.php" style="background:#c9a84c;color:#0a2f44;font-weight:700;">Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php">Logout</a>
    </div>
</div>
<?php
// Show session messages
if (!empty($_SESSION['success'])) {
    echo '<div style="background:#d4edda;color:#155724;padding:0.75rem 2rem;text-align:center;font-weight:500;">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
if (!empty($_SESSION['error'])) {
    echo '<div style="background:#f8d7da;color:#721c24;padding:0.75rem 2rem;text-align:center;font-weight:500;">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
?>

<div class="container">
    <div class="welcome-card">
        <h3>Welcome to IAMS</h3>
        <p>Complete your profile, submit your attachment application, and browse available positions with the Ministry of Labour and Home Affairs and other partners.</p>
        <p class="mission-text">"We give the pathway to future leaders"</p>
    </div>

    <div class="grid-2">
        <!-- Application Form -->
        <div class="card">
            <h3>Attachment Application</h3>
            <?php if ($application && $application['status'] !== 'rejected'): ?>
                <div class="success-msg">
                    <strong>Application Status:</strong> 
                    <span class="status-badge status-<?php echo $application['status']; ?>">
                        <?php echo strtoupper($application['status']); ?>
                    </span>
                    <p>Submitted on: <?php echo date('F j, Y', strtotime($application['submission_date'])); ?></p>
                    <?php if ($application['status'] == 'accepted'): ?>
                        <p style="margin-top: 0.5rem;">Congratulations! You have been accepted. The coordinator will contact you with placement details.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" action="submit_application.php">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Student Number</label>
                        <input type="text" name="student_number" required>
                    </div>
                    <div class="form-group">
                        <label>Programme</label>
                        <input type="text" name="programme" required>
                    </div>
                    <div class="form-group">
                        <label>Skills (comma separated)</label>
                        <textarea name="skills" rows="3" placeholder="Python, Java, Web Development, Data Analysis, Networking"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Preferred Location</label>
                        <input type="text" name="preferred_location" placeholder="Gaborone, Francistown, Lobatse, etc.">
                    </div>
                    <button type="submit">Submit Application to MLHA</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Document Upload -->
        <div class="card">
            <h3>Upload Documents</h3>
            <form method="POST" action="upload_document.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Document Type</label>
                    <select name="doc_type">
                        <option>CV/Resume</option>
                        <option>Academic Transcript</option>
                        <option>Certificate</option>
                        <option>ID Copy</option>
                        <option>Reference Letter</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>File (PDF, JPEG, PNG, DOCX, HEIC - max 10MB)</label>
                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.docx,.heic" required>
                </div>
                <button type="submit">Upload Document</button>
            </form>
            <div class="file-list">
                <h4>Uploaded Documents</h4>
                <?php foreach ($documents as $doc): ?>
                    <div class="file-item">
                        <span><?php echo htmlspecialchars($doc['doc_type']); ?> - <?php echo htmlspecialchars($doc['filename']); ?></span>
                        <a href="download.php?id=<?php echo $doc['doc_id']; ?>" style="color: #0a2f44;">Download</a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($documents)): ?>
                    <p>No documents uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile Links -->
        <div class="card">
            <h3>Profile Links</h3>
            <form method="POST" action="update_profile.php">
                <div class="form-group">
                    <label>LinkedIn Profile</label>
                    <input type="url" name="linkedin" value="<?php echo htmlspecialchars($profile['linkedin_url'] ?? ''); ?>" placeholder="https://linkedin.com/in/username">
                </div>
                <div class="form-group">
                    <label>GitHub Profile</label>
                    <input type="url" name="github" value="<?php echo htmlspecialchars($profile['github_url'] ?? ''); ?>" placeholder="https://github.com/username">
                </div>
                <div class="form-group">
                    <label>Portfolio Website</label>
                    <input type="url" name="portfolio" value="<?php echo htmlspecialchars($profile['portfolio_url'] ?? ''); ?>" placeholder="https://myportfolio.com">
                </div>
                <div class="form-group">
                    <label>Skills Summary</label>
                    <textarea name="skills" rows="2" placeholder="List your key technical skills"><?php echo htmlspecialchars($profile['skills'] ?? ''); ?></textarea>
                </div>
                <button type="submit">Save Profile</button>
            </form>
        </div>

        <!-- Job Interests -->
        <div class="card">
            <h3>Jobs You've Expressed Interest In</h3>
            <?php if (empty($interests)): ?>
                <p>You haven't expressed interest in any jobs yet. <a href="index.php#jobsGrid">Browse available positions with Ministry of Labour and Home Affairs</a></p>
            <?php else: ?>
                <?php foreach ($interests as $job): ?>
                    <div class="interest-item">
                        <strong><?php echo htmlspecialchars($job['title']); ?></strong><br>
                        <?php echo htmlspecialchars($job['organization']); ?> | <?php echo htmlspecialchars($job['location']); ?><br>
                        <small>Salary: <?php echo htmlspecialchars($job['salary_range']); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <p style="margin-top: 1rem; font-size: 0.85rem; color: #666;">
                <strong>Note:</strong> The Ministry of Labour and Home Affairs reviews applications based on skills alignment and academic performance.
            </p>
        </div>
    </div>
</div>
</body>
</html>