<?php
// register.php — Student registration (US-01, US-04)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) { header('Location: /dashboard.php'); exit(); }

$errors  = [];
$success = false;
$data    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'full_name'      => trim($_POST['full_name'] ?? ''),
        'student_number' => trim($_POST['student_number'] ?? ''),
        'email'          => strtolower(trim($_POST['email'] ?? '')),
        'phone'          => trim($_POST['phone'] ?? ''),
        'programme'      => trim($_POST['programme'] ?? ''),
        'year_of_study'  => (int)($_POST['year_of_study'] ?? 0),
        'password'       => $_POST['password'] ?? '',
        'confirm'        => $_POST['confirm_password'] ?? '',
    ];

    // Validate
    if (!$data['full_name'])      $errors[] = 'Full name is required.';
    if (!$data['student_number']) $errors[] = 'Student number is required.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address required.';
    if (!$data['programme'])      $errors[] = 'Programme of study is required.';
    if ($data['year_of_study'] < 1 || $data['year_of_study'] > 6) $errors[] = 'Year of study must be between 1 and 6.';

    $pwErrors = Auth::validatePasswordStrength($data['password']);
    $errors = array_merge($errors, $pwErrors);

    if ($data['password'] !== $data['confirm']) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db = Database::getInstance();
        // Check duplicates
        $chk = $db->prepare("SELECT user_id FROM users WHERE email=? OR student_number=?");
        $chk->execute([$data['email'], $data['student_number']]);
        if ($chk->rowCount()) {
            $errors[] = 'Email or student number is already registered.';
        } else {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO users (email,password_hash,full_name,student_number,programme,phone,role)
                              VALUES (?,?,?,?,?,?,'student')")
                   ->execute([
                       $data['email'],
                       Auth::hashPassword($data['password']),
                       $data['full_name'],
                       $data['student_number'],
                       $data['programme'],
                       $data['phone'],
                   ]);
                $uid = (int)$db->lastInsertId();
                $db->prepare("INSERT INTO student_profiles (user_id,year_of_study) VALUES (?,?)")
                   ->execute([$uid, $data['year_of_study']]);
                $db->commit();
                $success = true;
                $data    = [];
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Registration — IAMS</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap');
:root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,var(--navy) 0%,var(--teal) 100%);min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:560px;overflow:hidden}
.card-top{background:var(--navy);padding:1.75rem 2rem;text-align:center}
.card-top h1{font-family:'Playfair Display',serif;color:#fff;font-size:1.5rem;margin-bottom:.2rem}
.card-top p{color:var(--gold);font-size:.82rem}
.card-body{padding:2rem}
.alert-error{background:#f8d7da;color:#721c24;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.88rem}
.alert-success{background:#d4edda;color:#155724;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.8rem;font-weight:600;margin-bottom:.3rem;color:#374151}
.form-group label .req{color:#c0392b}
.form-group input,.form-group select{width:100%;padding:.65rem .9rem;border:1px solid #ddd;border-radius:7px;font-size:.9rem;font-family:inherit;transition:border .15s}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(26,90,122,.1)}
.pw-hint{font-size:.75rem;color:#6b7280;margin-top:.3rem;line-height:1.5}
.pw-strength{height:4px;border-radius:2px;margin-top:.4rem;transition:all .3s;background:#e5e7eb}
.btn{width:100%;padding:.8rem;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;margin-top:.5rem}
.btn:hover{background:var(--teal)}
.footer-links{text-align:center;margin-top:1.25rem;font-size:.85rem;color:#6b7280}
.footer-links a{color:var(--navy);font-weight:600;text-decoration:none}
@media(max-width:480px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <h1>Student Registration</h1>
    <p>University of Botswana — IAMS</p>
  </div>
  <div class="card-body">
    <?php if ($success): ?>
      <div class="alert-success">✅ Registration successful! <a href="/login.php" style="color:#155724;font-weight:700">Login now →</a></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert-error">
        <?php foreach ($errors as $e): ?><div>• <?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="regForm">
      <?php echo csrf_field(); ?>
      <div class="form-row">
        <div class="form-group">
          <label>Full Name <span class="req">*</span></label>
          <input type="text" name="full_name" required value="<?php echo htmlspecialchars($data['full_name'] ?? ''); ?>" placeholder="e.g. Kemmifhele Tom">
        </div>
        <div class="form-group">
          <label>Student Number <span class="req">*</span></label>
          <input type="text" name="student_number" required value="<?php echo htmlspecialchars($data['student_number'] ?? ''); ?>" placeholder="e.g. 202200960">
        </div>
      </div>
      <div class="form-group">
        <label>Email Address <span class="req">*</span></label>
        <input type="email" name="email" required value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>" placeholder="student@ub.ac.bw">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Programme of Study <span class="req">*</span></label>
          <input type="text" name="programme" required value="<?php echo htmlspecialchars($data['programme'] ?? ''); ?>" placeholder="e.g. BSc Computer Science">
        </div>
        <div class="form-group">
          <label>Year of Study <span class="req">*</span></label>
          <select name="year_of_study" required>
            <option value="">Select year</option>
            <?php for($y=1;$y<=6;$y++): ?>
            <option value="<?php echo $y; ?>" <?php echo ($data['year_of_study'] ?? 0)==$y?'selected':''; ?>>Year <?php echo $y; ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Phone Number</label>
        <input type="tel" name="phone" value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>" placeholder="+267 71XXXXXX">
      </div>
      <div class="form-group">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="password" id="pw" required placeholder="Min 8 chars, upper, lower, number, symbol" oninput="checkStrength(this.value)">
        <div class="pw-strength" id="pwBar"></div>
        <div class="pw-hint">Must contain: uppercase · lowercase · number · special character (!@#$%^&*)</div>
      </div>
      <div class="form-group">
        <label>Confirm Password <span class="req">*</span></label>
        <input type="password" name="confirm_password" required placeholder="Repeat your password">
      </div>
      <button type="submit" class="btn">Create Account →</button>
    </form>
    <div class="footer-links">
      Already registered? <a href="/login.php">Login here</a> &nbsp;|&nbsp; <a href="/register_org.php">Register as Organisation</a>
    </div>
  </div>
</div>
<script>
function checkStrength(pw){
  let score=0;
  if(pw.length>=8)score++;
  if(/[A-Z]/.test(pw))score++;
  if(/[a-z]/.test(pw))score++;
  if(/[0-9]/.test(pw))score++;
  if(/[^A-Za-z0-9]/.test(pw))score++;
  const bar=document.getElementById('pwBar');
  const colors=['#e5e7eb','#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
  const widths=['0%','20%','40%','60%','80%','100%'];
  bar.style.background=colors[score];
  bar.style.width=widths[score];
}
</script>
</body>
</html>