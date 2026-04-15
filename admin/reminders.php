<?php
// admin/reminders.php — Send reminders and email notifications (US-14)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/mailer.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Send logbook deadline reminders
    if ($action === 'remind_logbooks') {
        $weekNum = (int)($_POST['week_number'] ?? 0);
        if ($weekNum < 1 || $weekNum > 52) {
            $err = 'Invalid week number.';
        } else {
            $students = $db->query("
                SELECT DISTINCT u.user_id, u.full_name, u.email
                FROM applications a
                JOIN users u ON a.user_id = u.user_id
                WHERE a.status IN ('matched','accepted')
                  AND u.is_active = 1
                  AND u.user_id NOT IN (
                    SELECT lb.user_id FROM logbooks lb
                    WHERE lb.week_number = {$weekNum} AND lb.status IN ('submitted','reviewed')
                  )
            ")->fetchAll();

            $sent = 0;
            foreach ($students as $s) {
                $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$s['user_id'], "Week {$weekNum} Logbook Reminder", "Your Week {$weekNum} logbook is due. Please submit it as soon as possible.", 'deadline', '/logbook.php']);
                Mailer::sendLogbookReminder($s['email'], $s['full_name'], $weekNum);
                $sent++;
                $results[] = $s['full_name'] . ' <' . $s['email'] . '>';
            }
            $msg = "Reminder sent to {$sent} student(s) for Week {$weekNum}.";
        }
    }

    // Remind students with missing documents
    if ($action === 'remind_docs') {
        $students = $db->query("
            SELECT u.user_id, u.full_name, u.email
            FROM applications a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.status = 'pending' AND u.is_active = 1
              AND (SELECT COUNT(*) FROM documents d WHERE d.user_id = u.user_id) = 0
        ")->fetchAll();
        $sent = 0;
        foreach ($students as $s) {
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)")
               ->execute([$s['user_id'], 'Documents Missing', 'Please upload your CV and academic transcript to support your application.', 'warning', '/dashboard.php?tab=docs']);
            Mailer::send($s['email'], $s['full_name'], 'IAMS — Documents Required', '<p>Please upload your CV and academic transcript to support your application review.</p>');
            $sent++;
            $results[] = $s['full_name'];
        }
        $msg = "Document reminder sent to {$sent} student(s).";
    }

    // Remind organisations to submit supervisor reports
    if ($action === 'remind_sup_reports') {
        $orgs = $db->query("
            SELECT DISTINCT u.user_id, u.full_name, u.email
            FROM matches m
            JOIN organisations o ON m.org_id = o.org_id
            JOIN users u ON o.user_id = u.user_id
            WHERE m.status = 'confirmed' AND u.is_active = 1
              AND m.user_id NOT IN (
                SELECT sr.student_user_id FROM supervisor_reports sr WHERE sr.status = 'submitted'
              )
        ")->fetchAll();
        $sent = 0;
        foreach ($orgs as $o) {
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)")
               ->execute([$o['user_id'], 'Supervisor Report Pending', 'Please submit performance reports for your matched students.', 'deadline', '/supervisor_report.php']);
            Mailer::send($o['email'], $o['full_name'], 'IAMS — Supervisor Report Reminder', '<p>Please submit the end-of-attachment performance report(s) for your matched student(s) via the IAMS portal.</p>');
            $sent++;
            $results[] = $o['full_name'];
        }
        $msg = "Report reminder sent to {$sent} organisation(s).";
    }

    // Send custom notification
    if ($action === 'custom_notify') {
        $target  = $_POST['target'] ?? 'student';
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $sendEmail = isset($_POST['send_email']);
        if (!$title || !$message) {
            $err = 'Title and message are required.';
        } else {
            $roles = in_array($target, ['student', 'organisation', 'coordinator', 'admin', 'all']) ? $target : 'student';
            if ($roles === 'all') {
                $users = $db->query("SELECT user_id, full_name, email FROM users WHERE is_active = 1")->fetchAll();
            } else {
                $uStmt = $db->prepare("SELECT user_id, full_name, email FROM users WHERE role = ? AND is_active = 1");
                $uStmt->execute([$roles]);
                $users = $uStmt->fetchAll();
            }
            $sent = 0;
            foreach ($users as $u) {
                $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)")
                   ->execute([$u['user_id'], $title, $message, 'info']);
                if ($sendEmail) {
                    Mailer::send($u['email'], $u['full_name'], 'IAMS — ' . $title, '<p>' . nl2br(htmlspecialchars($message)) . '</p>');
                }
                $sent++;
            }
            $msg = "Notification sent to {$sent} user(s).";
        }
    }
}

// Stats
$placed = (int)$db->query("SELECT COUNT(*) FROM applications WHERE status IN ('matched','accepted')")->fetchColumn();
$totalLogbooks = (int)$db->query("SELECT COUNT(*) FROM logbooks")->fetchColumn();
$pendingSupReports = (int)$db->query("
    SELECT COUNT(DISTINCT m.user_id) FROM matches m
    WHERE m.status = 'confirmed'
      AND m.user_id NOT IN (SELECT student_user_id FROM supervisor_reports WHERE status = 'submitted')
")->fetchColumn();
$missingDocs = (int)$db->query("
    SELECT COUNT(*) FROM applications a
    WHERE a.status = 'pending'
      AND (SELECT COUNT(*) FROM documents d WHERE d.user_id = a.user_id) = 0
")->fetchColumn();

$pageTitle = 'Reminders & Notifications';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">&#128276; Reminders &amp; Notifications</div>
<div class="page-sub">Send deadline reminders and bulk notifications to students and organisations</div>

<?php if ($msg): ?><div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg); ?><?php if ($results): ?><br><small style="opacity:.8">Recipients: <?php echo implode(', ', array_slice($results, 0, 10)); ?><?php echo count($results) > 10 ? ' +' . (count($results) - 10) . ' more' : ''; ?></small><?php endif; ?></div><?php endif; ?>
<?php if ($err):  ?><div class="alert alert-error">&#9888; <?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<?php if (!MAIL_ENABLED): ?>
<div class="alert alert-warning">&#9888; <strong>Email is disabled.</strong> Configure <code>MAIL_ENABLED = true</code> and SMTP settings in <code>config/mailer.php</code> to send real emails. In-app notifications will still be delivered.</div>
<?php endif; ?>

<!-- Overview stats -->
<div class="stats-grid" style="margin-bottom:2rem">
  <div class="stat-card gold"><div class="stat-label">Placed Students</div><div class="stat-num"><?php echo $placed; ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Logbooks</div><div class="stat-num"><?php echo $totalLogbooks; ?></div></div>
  <div class="stat-card red"><div class="stat-label">Sup. Reports Pending</div><div class="stat-num"><?php echo $pendingSupReports; ?></div></div>
  <div class="stat-card gold"><div class="stat-label">Apps Missing Docs</div><div class="stat-num"><?php echo $missingDocs; ?></div></div>
</div>

<div class="grid-2" style="align-items:start">

<!-- Quick reminder actions -->
<div style="display:flex;flex-direction:column;gap:1.25rem">

  <div class="card">
    <div class="card-header"><h3>&#128221; Logbook Deadline Reminder</h3></div>
    <div class="card-body">
      <p class="text-muted" style="margin-bottom:1rem">Send a reminder to all placed students who haven't submitted their logbook for a specific week.</p>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="remind_logbooks">
        <div class="form-group"><label>Week Number *</label><input type="number" name="week_number" min="1" max="52" required placeholder="e.g. 3" style="max-width:120px"></div>
        <button type="submit" class="btn btn-primary">Send Logbook Reminders</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>&#128193; Missing Documents Reminder</h3></div>
    <div class="card-body">
      <p class="text-muted" style="margin-bottom:1rem">Remind students with pending applications who have not uploaded any documents.</p>
      <p style="margin-bottom:1rem"><strong><?php echo $missingDocs; ?></strong> student(s) with pending apps and no documents.</p>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="remind_docs">
        <button type="submit" class="btn btn-primary" <?php echo !$missingDocs ? 'disabled' : ''; ?>>Send Document Reminders</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>&#128203; Supervisor Report Reminder</h3></div>
    <div class="card-body">
      <p class="text-muted" style="margin-bottom:1rem">Remind organisations that haven't submitted supervisor reports for their matched students.</p>
      <p style="margin-bottom:1rem"><strong><?php echo $pendingSupReports; ?></strong> student(s) awaiting supervisor reports.</p>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="remind_sup_reports">
        <button type="submit" class="btn btn-primary" <?php echo !$pendingSupReports ? 'disabled' : ''; ?>>Send Report Reminders</button>
      </form>
    </div>
  </div>

</div>

<!-- Custom notification -->
<div class="card">
  <div class="card-header"><h3>&#128172; Send Custom Notification</h3></div>
  <div class="card-body">
    <p class="text-muted" style="margin-bottom:1.25rem">Send a custom in-app notification (and optionally an email) to any group of users.</p>
    <form method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="custom_notify">
      <div class="form-group">
        <label>Send To *</label>
        <select name="target">
          <option value="student">All Students</option>
          <option value="organisation">All Organisations</option>
          <option value="coordinator">All Coordinators</option>
          <option value="admin">All Admins</option>
          <option value="all">Everyone</option>
        </select>
      </div>
      <div class="form-group"><label>Notification Title *</label><input type="text" name="title" required placeholder="e.g. Semester Reminder"></div>
      <div class="form-group"><label>Message *</label><textarea name="message" rows="5" required placeholder="Write your message here..."></textarea></div>
      <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
        <input type="checkbox" name="send_email" id="send_email" value="1" style="width:auto">
        <label for="send_email" style="margin:0;font-weight:400">Also send as email <?php echo !MAIL_ENABLED ? '(email disabled — in-app only)' : ''; ?></label>
      </div>
      <button type="submit" class="btn btn-primary">Send Notification</button>
    </form>
  </div>
</div>
</div>

<!-- SMTP configuration guide -->
<div class="card" style="margin-top:1.5rem">
  <div class="card-header"><h3>&#9993;&#65039; Email Setup Guide</h3></div>
  <div class="card-body">
    <p style="margin-bottom:1rem;color:var(--muted)">To enable real email delivery, edit <code>config/mailer.php</code>:</p>
    <table>
      <thead><tr><th>Setting</th><th>Value to set</th><th>Example</th></tr></thead>
      <tbody>
        <tr><td><code>MAIL_ENABLED</code></td><td>Set to <code>true</code></td><td><code>define('MAIL_ENABLED', true);</code></td></tr>
        <tr><td><code>MAIL_SMTP_HOST</code></td><td>Your SMTP server</td><td><code>'smtp.gmail.com'</code> or <code>'smtp.brevo.com'</code></td></tr>
        <tr><td><code>MAIL_SMTP_PORT</code></td><td>587 (TLS)</td><td><code>587</code></td></tr>
        <tr><td><code>MAIL_SMTP_USER</code></td><td>Your SMTP username</td><td><code>'your@gmail.com'</code></td></tr>
        <tr><td><code>MAIL_SMTP_PASS</code></td><td>App password (not login password)</td><td>16-char Gmail App Password</td></tr>
        <tr><td><code>MAIL_FROM</code></td><td>Sender email</td><td><code>'noreply@ub.ac.bw'</code></td></tr>
      </tbody>
    </table>
    <div style="margin-top:1rem;padding:.85rem;background:#d1ecf1;border-radius:7px;font-size:.85rem;color:#0c5460">
      <strong>Recommended free SMTP:</strong> <a href="https://brevo.com" target="_blank">Brevo (formerly Sendinblue)</a> — 300 emails/day free, no credit card.
    </div>
  </div>
</div>

</div>
<footer class="site-footer">IAMS &copy; <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>