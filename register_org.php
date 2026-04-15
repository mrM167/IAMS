<?php
// register_org.php — Organisation registration (US-03)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) { header('Location: /org/dashboard.php'); exit(); }

$errors  = [];
$success = false;
$data    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'org_name'        => trim($_POST['org_name'] ?? ''),
        'industry'        => trim($_POST['industry'] ?? ''),
        'contact_person'  => trim($_POST['contact_person'] ?? ''),
        'contact_email'   => strtolower(trim($_POST['contact_email'] ?? '')),
        'contact_phone'   => trim($_POST['contact_phone'] ?? ''),
        'address'         => trim($_POST['address'] ?? ''),
        'location'        => trim($_POST['location'] ?? ''),
        'description'     => trim($_POST['description'] ?? ''),
        'required_skills' => trim($_POST['required_skills'] ?? ''),
        'capacity'        => (int)($_POST['capacity'] ?? 1),
        'login_email'     => strtolower(trim($_POST['login_email'] ?? '')),
        'password'        => $_POST['password'] ?? '',
        'confirm'         => $_POST['confirm_password'] ?? '',
    ];

    if (!$data['org_name'])       $errors[] = 'Organisation name is required.';
    if (!$data['contact_person']) $errors[] = 'Contact person name is required.';
    if (!filter_var($data['login_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid login email required.';
    if ($data['capacity'] < 1)    $errors[] = 'Capacity must be at least 1.';

    $pwErrors = Auth::validatePasswordStrength($data['password']);
    $errors = array_merge($errors, $pwErrors);
    if ($data['password'] !== $data['confirm']) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db = Database::getInstance();
        $chk = $db->prepare("SELECT user_id FROM users WHERE email=?");
        $chk->execute([$data['login_email']]);
        if ($chk->rowCount()) {
            $errors[] = 'This email is already registered.';
        } else {
            $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO users (email,password_hash,full_name,phone,role) VALUES (?,?,?,?,'organisation')")
                   ->execute([
                       $data['login_email'],
                       Auth::hashPassword($data['password']),
                       $data['org_name'],
                       $data['contact_phone'],
                   ]);
                $uid = (int)$db->lastInsertId();
                $db->prepare("INSERT INTO organisations
                    (user_id,org_name,industry,contact_person,contact_email,contact_phone,address,location,description,required_skills,capacity)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([
                       $uid, $data['org_name'], $data['industry'], $data['contact_person'],
                       $data['contact_email'] ?: $data['login_email'],
                       $data['contact_phone'], $data['address'], $data['location'],
                       $data['description'], $data['required_skills'], $data['capacity'],
                   ]);
                $db->commit();
                $success = true;
                $data    = [];
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Organisation Registration — IAMS</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap');
:root{--navy:#0a2f44;--teal:#1a5a7a;--gold:#c9a84c}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,var(--navy) 0%,var(--teal) 100%);min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem}
.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:620px;overflow:hidden}
.card-top{background:var(--teal);padding:1.75rem 2rem;text-align:center}
.card-top h1{font-family:'Playfair Display',serif;color:#fff;font-size:1.5rem;margin-bottom:.2rem}
.card-top p{color:var(--gold);font-size:.82rem}
.card-body{padding:2rem}
.section-label{font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--teal);margin:.5rem 0 .75rem;padding-bottom:.35rem;border-bottom:2px solid #e5e7eb}
.alert-error{background:#f8d7da;color:#721c24;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.88rem}
.alert-success{background:#d4edda;color:#155724;padding:.75rem 1rem;border-radius:8px;margin-bottom:1.25rem;font-size:.9rem}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{margin-bottom:.9rem}
.form-group label{display:block;font-size:.8rem;font-weight:600;margin-bottom:.3rem;color:#374151}
.req{color:#c0392b}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:.65rem .9rem;border:1px solid #ddd;border-radius:7px;font-size:.9rem;font-family:inherit;transition:border .15s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(26,90,122,.1)}
.form-group textarea{resize:vertical}
.pw-hint{font-size:.75rem;color:#6b7280;margin-top:.3rem}
.pw-strength{height:4px;border-radius:2px;margin-top:.4rem;transition:all .3s;background:#e5e7eb}
.btn{width:100%;padding:.8rem;background:var(--teal);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;margin-top:.5rem}
.btn:hover{background:var(--navy)}
.footer-links{text-align:center;margin-top:1.25rem;font-size:.85rem;color:#6b7280}
.footer-links a{color:var(--navy);font-weight:600;text-decoration:none}
@media(max-width:480px){.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <h1>🏢 Organisation Registration</h1>
    <p>Register your organisation to host student attachments</p>
  </div>
  <div class="card-body">
    <?php if ($success): ?>
      <div class="alert-success">✅ Registration successful! <a href="/login.php" style="color:#155724;font-weight:700">Login to your dashboard →</a></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert-error">
        <?php foreach ($errors as $e): ?><div>• <?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <?php echo csrf_field(); ?>

      <div class="section-label">Organisation Details</div>
      <div class="form-group">
        <label>Organisation Name <span class="req">*</span></label>
        <input type="text" name="org_name" required value="<?php echo htmlspecialchars($data['org_name'] ?? ''); ?>" placeholder="e.g. Ministry of Labour and Home Affairs">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Industry / Sector</label>
          <select name="industry">
            <option value="">Select sector</option>
            <?php foreach(['Government','Information Technology','Finance & Banking','Healthcare','Education','Engineering','Legal','Mining & Resources','Retail & Commerce','Other'] as $ind): ?>
            <option value="<?php echo $ind; ?>" <?php echo ($data['industry']??'')===$ind?'selected':''; ?>><?php echo $ind; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Capacity (students) <span class="req">*</span></label>
          <input type="number" name="capacity" min="1" max="50" value="<?php echo htmlspecialchars($data['capacity'] ?? 1); ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Location / City <span class="req">*</span></label>
          <input type="text" name="location" required value="<?php echo htmlspecialchars($data['location'] ?? ''); ?>" placeholder="e.g. Gaborone">
        </div>
        <div class="form-group">
          <label>Physical Address</label>
          <input type="text" name="address" value="<?php echo htmlspecialchars($data['address'] ?? ''); ?>" placeholder="e.g. Private Bag 0032">
        </div>
      </div>
      <div class="form-group">
        <label>Organisation Description</label>
        <textarea name="description" rows="3" placeholder="Brief description of your organisation and the work students would do..."><?php echo htmlspecialchars($data['description'] ?? ''); ?></textarea>
      </div>
      <div class="form-group">
        <label>Required Skills / Profile (for student matching)</label>
        <textarea name="required_skills" rows="2" placeholder="e.g. PHP, Python, Data Analysis, Microsoft Office, Accounting..."><?php echo htmlspecialchars($data['required_skills'] ?? ''); ?></textarea>
      </div>

      <div class="section-label">Contact Person</div>
      <div class="form-row">
        <div class="form-group">
          <label>Contact Person Name <span class="req">*</span></label>
          <input type="text" name="contact_person" required value="<?php echo htmlspecialchars($data['contact_person'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label>Contact Phone</label>
          <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($data['contact_phone'] ?? ''); ?>" placeholder="+267 71XXXXXX">
        </div>
      </div>
      <div class="form-group">
        <label>Contact Email (for correspondence)</label>
        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($data['contact_email'] ?? ''); ?>">
      </div>

      <div class="section-label">Login Credentials</div>
      <div class="form-group">
        <label>Login Email <span class="req">*</span></label>
        <input type="email" name="login_email" required value="<?php echo htmlspecialchars($data['login_email'] ?? ''); ?>" placeholder="Use your organisation's email">
      </div>
      <div class="form-group">
        <label>Password <span class="req">*</span></label>
        <input type="password" name="password" id="pw" required placeholder="Min 8 chars" oninput="checkStrength(this.value)">
        <div class="pw-strength" id="pwBar"></div>
        <div class="pw-hint">Must contain: uppercase · lowercase · number · special character</div>
      </div>
      <div class="form-group">
        <label>Confirm Password <span class="req">*</span></label>
        <input type="password" name="confirm_password" required>
      </div>
      <button type="submit" class="btn">Register Organisation →</button>
    </form>
    <div class="footer-links">
      Already registered? <a href="/login.php">Login here</a> &nbsp;|&nbsp; <a href="/register.php">Student registration</a>
    </div>
  </div>
</div>
<script>
function checkStrength(pw){
  let s=0;
  if(pw.length>=8)s++;if(/[A-Z]/.test(pw))s++;if(/[a-z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[^A-Za-z0-9]/.test(pw))s++;
  const b=document.getElementById('pwBar');
  b.style.background=['#e5e7eb','#ef4444','#f97316','#eab308','#22c55e','#16a34a'][s];
  b.style.width=['0%','20%','40%','60%','80%','100%'][s];
}
</script>
</body>
</html>
