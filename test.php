<?php
// test.php — Diagnostic tool. DELETE THIS FILE after fixing your site!
// Visit: yourdomain.com/test.php

echo "<style>body{font-family:sans-serif;max-width:700px;margin:2rem auto;padding:1rem;}
.ok{color:green;font-weight:bold;} .fail{color:red;font-weight:bold;} .warn{color:orange;font-weight:bold;}
.box{background:#f5f5f5;border:1px solid #ddd;padding:1rem;border-radius:6px;margin:1rem 0;}
pre{background:#222;color:#0f0;padding:1rem;border-radius:6px;overflow-x:auto;font-size:0.85rem;}
</style>";

echo "<h1>IAMS Diagnostic Tool</h1>";
echo "<p style='color:red;font-weight:bold;'>⚠️ DELETE this file (test.php) after fixing your site!</p>";

// 1. PHP version
echo "<div class='box'><h3>1. PHP Version</h3>";
$phpver = PHP_VERSION;
if (version_compare($phpver, '7.4', '>=')) {
    echo "<span class='ok'>✅ PHP $phpver — OK</span>";
} else {
    echo "<span class='fail'>❌ PHP $phpver — too old, need 7.4+</span>";
}
echo "</div>";

// 2. PDO extension
echo "<div class='box'><h3>2. PDO MySQL Extension</h3>";
if (extension_loaded('pdo_mysql')) {
    echo "<span class='ok'>✅ pdo_mysql loaded — OK</span>";
} else {
    echo "<span class='fail'>❌ pdo_mysql NOT loaded — contact your host to enable it</span>";
}
echo "</div>";

// 3. Database connection
echo "<div class='box'><h3>3. Database Connection</h3>";
// Read current credentials from database.php
$db_host = ''; $db_name = ''; $db_user = ''; $db_pass = '';
$config_file = __DIR__ . '/config/database.php';
if (file_exists($config_file)) {
    $content = file_get_contents($config_file);
    preg_match('/\$host\s*=\s*["\']([^"\']+)["\']/', $content, $m); $db_host = $m[1] ?? '';
    preg_match('/\$db_name\s*=\s*["\']([^"\']+)["\']/', $content, $m); $db_name = $m[1] ?? '';
    preg_match('/\$username\s*=\s*["\']([^"\']+)["\']/', $content, $m); $db_user = $m[1] ?? '';
    preg_match('/\$password\s*=\s*["\']([^"\']+)["\']/', $content, $m); $db_pass = $m[1] ?? '';

    echo "<p>Host: <code>$db_host</code> | DB: <code>$db_name</code> | User: <code>$db_user</code></p>";

    if (strpos($db_name, 'your_') !== false || strpos($db_user, 'your_') !== false) {
        echo "<span class='fail'>❌ You haven't updated config/database.php yet! Still has placeholder values.</span>";
        echo "<br><br><strong>Open config/database.php and replace:</strong><pre>";
        echo "private \$host     = \"localhost\";      // your host\n";
        echo "private \$db_name  = \"your_db_name\";   // e.g. epiz_12345_iams\n";
        echo "private \$username = \"your_db_user\";   // e.g. epiz_12345\n";
        echo "private \$password = \"your_password\";  // your password";
        echo "</pre>";
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            echo "<span class='ok'>✅ Database connected successfully!</span>";

            // Check tables
            echo "<br><br><strong>Tables found:</strong><br>";
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $required = ['users','student_profiles','applications','documents','job_posts','job_interests'];
            foreach ($required as $t) {
                if (in_array($t, $tables)) {
                    echo "<span class='ok'>✅ $t</span><br>";
                } else {
                    echo "<span class='fail'>❌ $t — MISSING. Did you import database_schema.sql?</span><br>";
                }
            }
        } catch (PDOException $e) {
            echo "<span class='fail'>❌ Connection FAILED: " . htmlspecialchars($e->getMessage()) . "</span>";
            echo "<br><br><strong>Common fixes:</strong><ul>
            <li>Check your host — InfinityFree uses something like <code>sql305.infinityfree.com</code> (not localhost)</li>
            <li>Make sure the DB user has privileges on the database</li>
            <li>Double-check username/password for typos</li>
            </ul>";
        }
    }
} else {
    echo "<span class='fail'>❌ config/database.php not found!</span>";
}
echo "</div>";

// 4. Uploads folder
echo "<div class='box'><h3>4. Uploads Folder</h3>";
$uploads = __DIR__ . '/uploads';
if (!is_dir($uploads)) {
    if (mkdir($uploads, 0755, true)) echo "<span class='ok'>✅ Created uploads/ folder</span>";
    else echo "<span class='fail'>❌ Cannot create uploads/ — check folder permissions</span>";
} elseif (!is_writable($uploads)) {
    echo "<span class='fail'>❌ uploads/ folder exists but is NOT writable — set permissions to 755</span>";
} else {
    echo "<span class='ok'>✅ uploads/ folder exists and is writable</span>";
}
echo "</div>";

// 5. Session test
echo "<div class='box'><h3>5. Sessions</h3>";
session_start();
$_SESSION['test'] = 'ok';
if (isset($_SESSION['test'])) echo "<span class='ok'>✅ Sessions working</span>";
else echo "<span class='fail'>❌ Sessions not working</span>";
echo "</div>";

// 6. Config files readable
echo "<div class='box'><h3>6. Key Files</h3>";
$files = ['config/database.php','config/session.php','login.php','register.php','dashboard.php','index.php'];
foreach ($files as $f) {
    if (file_exists(__DIR__ . '/' . $f)) echo "<span class='ok'>✅ $f</span><br>";
    else echo "<span class='fail'>❌ $f — NOT FOUND</span><br>";
}
echo "</div>";

echo "<hr><p style='color:#888;font-size:0.85rem;'>⚠️ Remember to delete test.php after you fix the issue.</p>";
?>
