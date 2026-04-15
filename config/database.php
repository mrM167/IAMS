<?php
// config/database.php — PHP 7.4 compatible
define('DB_HOST', 'localhost');        // InfinityFree: e.g. sql305.infinityfree.com
define('DB_NAME', 'your_db_name');     // e.g. epiz_12345678_iams
define('DB_USER', 'your_db_user');     // e.g. epiz_12345678
define('DB_PASS', 'your_password');    // Your MySQL password

class Database {
    private static $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            if (strpos(DB_NAME, 'your_') !== false || strpos(DB_USER, 'your_') !== false) {
                self::showSetupPage();
            }
            try {
                self::$instance = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER, DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                error_log("IAMS DB Error: " . $e->getMessage());
                self::showDbError($e->getMessage());
            }
        }
        return self::$instance;
    }

    // Legacy support: new Database()->getConnection()
    public function getConnection(): PDO {
        return self::getInstance();
    }

    // PHP 7.4: no 'never' return type — just void + exit()
    private static function showSetupPage(): void {
        http_response_code(503);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Setup Required — IAMS</title>
        <style>body{font-family:sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        .box{background:white;border-radius:12px;padding:2.5rem;max-width:580px;box-shadow:0 4px 20px rgba(0,0,0,.1)}
        h2{color:#0a2f44}pre{background:#1a1a2e;color:#00ff88;padding:1rem;border-radius:6px;font-size:.85rem;overflow-x:auto}
        p{color:#5a7080;line-height:1.6;margin-bottom:1rem}code{background:#f0f4f8;padding:.2rem .4rem;border-radius:3px}</style>
        </head><body><div class="box">
        <h2>&#9881;&#65039; Database Setup Required</h2>
        <p>Open <code>config/database.php</code> and fill in your MySQL credentials:</p>
        <pre>define("DB_HOST", "sql305.infinityfree.com");
define("DB_NAME", "epiz_12345678_iams");
define("DB_USER", "epiz_12345678");
define("DB_PASS", "your_actual_password");</pre>
        <p>Then import <code>database_schema.sql</code> via phpMyAdmin, run <code>setup.php</code> to create admin accounts, and delete it.</p>
        </div></body></html>';
        exit();
    }

    private static function showDbError(string $error): void {
        http_response_code(503);
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>DB Error — IAMS</title>
        <style>body{font-family:sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
        .box{background:white;border-radius:12px;padding:2.5rem;max-width:600px;box-shadow:0 4px 20px rgba(0,0,0,.1)}
        h2{color:#c0392b}p{color:#5a7080;line-height:1.6;margin-bottom:.75rem}
        pre{background:#111;color:#f66;padding:1rem;border-radius:6px;font-size:.82rem}
        ol{color:#5a7080;line-height:2}</style>
        </head><body><div class="box"><h2>&#10060; Database Connection Failed</h2>'
        . ($local ? '<pre>' . htmlspecialchars($error) . '</pre>' : '')
        . '<p><strong>Common fixes:</strong></p><ol>
        <li>InfinityFree does NOT use <code>localhost</code> — use the host shown in your cPanel MySQL section</li>
        <li>Double-check DB name, username, and password in <code>config/database.php</code></li>
        <li>Make sure you imported <code>database_schema.sql</code> via phpMyAdmin</li>
        <li>Assign the DB user to the database in cPanel</li>
        </ol></div></body></html>';
        exit();
    }
}
