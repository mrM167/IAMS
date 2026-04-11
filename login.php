<?php
// login.php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT user_id, email, password_hash, full_name, role FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IAMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a2f44 0%, #1a5a7a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .logo { text-align: center; margin-bottom: 1.5rem; }
        h2 { text-align: center; color: #0a2f44; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-weight: 600; }
        input { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        button { width: 100%; padding: 0.75rem; background: #0a2f44; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #1a5a7a; }
        .error { background: #ffebee; color: #c62828; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; }
        .links { text-align: center; margin-top: 1rem; }
        .links a { color: #0a2f44; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        <svg width="180" height="50" viewBox="0 0 300 90" xmlns="http://www.w3.org/2000/svg">
            <rect width="300" height="90" fill="#0a2f44" rx="8" ry="8"/>
            <text x="20" y="38" font-family="Georgia, serif" font-size="20" font-weight="bold" fill="white">UNIVERSITY</text>
            <text x="20" y="68" font-family="Georgia, serif" font-size="20" font-weight="bold" fill="white">OF BOTSWANA</text>
        </svg>
    </div>
    <h2>Login to IAMS</h2>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required placeholder="student@ub.ac.bw">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Enter your password">
        </div>
        <button type="submit">Login</button>
    </form>
    <div class="links">
        <a href="forgot_password.php">Forgot Password?</a> | 
        <a href="register.php">Create Account</a>
    </div>
</div>
</body>
</html>