<?php
// forgot_password.php
// Basic forgot password page - replace with email-based reset when SMTP is configured
require_once __DIR__ . '/config/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - IAMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a2f44 0%, #1a5a7a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .container {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        h2 { color: #0a2f44; margin-bottom: 1rem; }
        p { color: #5a7080; line-height: 1.7; margin-bottom: 1.5rem; }
        .info-box {
            background: #e8f4fd;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            text-align: left;
            margin-bottom: 1.5rem;
        }
        .info-box h4 { color: #0a2f44; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .info-box p { color: #2c5282; font-size: 0.85rem; margin: 0; }
        a.btn {
            display: inline-block;
            background: #0a2f44;
            color: white;
            text-decoration: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .back { margin-top: 1rem; display: block; color: #0a2f44; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="container">
    <h2>Forgot Password?</h2>
    <p>Password reset via email is not yet configured for this system.</p>
    <div class="info-box">
        <h4>What to do:</h4>
        <p>Please contact your system coordinator or email <strong>iams@ub.ac.bw</strong> with your student number to have your password reset manually.</p>
    </div>
    <a href="login.php" class="btn">Back to Login</a>
    <a href="index.php" class="back">← Return to Home</a>
</div>
</body>
</html>
