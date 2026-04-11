<?php
// register.php
require_once 'config/database.php';
require_once 'config/session.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $student_number = trim($_POST['student_number']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $programme = trim($_POST['programme']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $check = $db->prepare("SELECT user_id FROM users WHERE email = ? OR student_number = ?");
        $check->execute([$email, $student_number]);
        if ($check->rowCount() > 0) {
            $error = "Email or student number already exists.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (email, password_hash, full_name, student_number, programme, phone) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$email, $password_hash, $full_name, $student_number, $programme, $phone])) {
                $user_id = $db->lastInsertId();
                $profile_stmt = $db->prepare("INSERT INTO student_profiles (user_id) VALUES (?)");
                $profile_stmt->execute([$user_id]);
                $success = "Registration successful! You can now login.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - IAMS</title>
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
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }
        h2 { text-align: center; color: #0a2f44; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-weight: 600; }
        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        button {
            width: 100%;
            padding: 0.75rem;
            background: #0a2f44;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .error { background: #ffebee; color: #c62828; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; }
        .links { text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="register-container">
    <h2>Student Registration</h2>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required>
        </div>
        <div class="form-group">
            <label>Student Number</label>
            <input type="text" name="student_number" required placeholder="2021XXXXX">
        </div>
        <div class="form-group">
            <label>Email (University email)</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" placeholder="+267 71XXXXXX">
        </div>
        <div class="form-group">
            <label>Programme of Study</label>
            <input type="text" name="programme" required placeholder="BSc Computer Science">
        </div>
        <div class="form-group">
            <label>Password (min 6 characters)</label>
            <input type="password" name="password" required>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>
        <button type="submit">Register</button>
    </form>
    <div class="links">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>
</body>
</html>