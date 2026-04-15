<?php
// notifications.php — Notification centre (US-15)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();

$user = getCurrentUser();
$db   = Database::getInstance();

// Mark single as read
if (isset($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE notif_id=? AND user_id=?")->execute([(int)$_GET['read'], $user['id']]);
    header('Location: /notifications.php'); exit();
}
// Mark all read
if (isset($_GET['read_all'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
    header('Location: /notifications.php'); exit();
}
// Delete single
if (isset($_GET['del'])) {
    $db->prepare("DELETE FROM notifications WHERE notif_id=? AND user_id=?")->execute([(int)$_GET['del'], $user['id']]);
    header('Location: /notifications.php'); exit();
}

$filter  = $_GET['filter'] ?? 'all';
$whereEx = $filter === 'unread' ? "AND n.is_read=0" : ($filter === 'read' ? "AND n.is_read=1" : '');

$notifs = $db->prepare("
    SELECT * FROM notifications WHERE user_id=? $whereEx ORDER BY created_at DESC LIMIT 100
");
$notifs->execute([$user['id']]);
$notifs = $notifs->fetchAll();
$unreadCount = (int)$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$user['id']]) ? $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$user['id']]) : 0;
$ucStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$ucStmt->execute([$user['id']]);
$unreadCount = (int)$ucStmt->fetchColumn();

$typeIcons = ['info'=>'ℹ️','warning'=>'⚠️','success'=>'✅','deadline'=>'📅'];
$typeColors = ['info'=>'#d1ecf1','warning'=>'#fff3cd','success'=>'#d4edda','deadline'=>'#f8d7da'];

$pageTitle = 'Notifications';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="page-wrap">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem">
  <div>
    <div class="page-title">🔔 Notifications</div>
    <div class="page-sub"><?php echo $unreadCount; ?> unread notification(s)</div>
  </div>
  <?php if ($unreadCount > 0): ?>
  <a href="?read_all=1" class="btn btn-outline">Mark all as read</a>
  <?php endif; ?>
</div>

<!-- Filter tabs -->
<div style="display:flex;gap:.4rem;margin-bottom:1.25rem">
  <?php foreach(['all'=>'All','unread'=>'Unread ('.$unreadCount.')','read'=>'Read'] as $f=>$label): ?>
  <a href="?filter=<?php echo $f; ?>" style="padding:.4rem .85rem;border-radius:6px;text-decoration:none;font-size:.82rem;font-weight:600;<?php echo $filter===$f?'background:var(--navy);color:#fff':'background:#fff;color:var(--muted);border:1px solid #ddd'; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<?php if ($notifs): ?>
<div class="card">
<?php foreach ($notifs as $n): ?>
<div style="padding:1rem 1.25rem;border-bottom:1px solid #f5f5f5;display:flex;gap:1rem;align-items:flex-start;background:<?php echo !$n['is_read']?'#fffbec':'#fff'; ?>;transition:background .2s">
  <div style="font-size:1.4rem;flex-shrink:0;margin-top:.1rem"><?php echo $typeIcons[$n['type']] ?? 'ℹ️'; ?></div>
  <div style="flex:1;min-width:0">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
      <div>
        <div style="font-weight:<?php echo !$n['is_read']?'700':'600'; ?>;font-size:.9rem;color:var(--navy);margin-bottom:.2rem"><?php echo htmlspecialchars($n['title']); ?></div>
        <div style="font-size:.85rem;color:var(--muted);line-height:1.5"><?php echo htmlspecialchars($n['message']); ?></div>
        <?php if ($n['link']): ?><a href="<?php echo htmlspecialchars($n['link']); ?>" style="font-size:.8rem;color:var(--teal);font-weight:600;margin-top:.3rem;display:inline-block">View →</a><?php endif; ?>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:.75rem;color:#9ca3af;margin-bottom:.4rem"><?php echo date('j M Y H:i',strtotime($n['created_at'])); ?></div>
        <div style="display:flex;gap:.35rem;justify-content:flex-end">
          <?php if (!$n['is_read']): ?><a href="?read=<?php echo $n['notif_id']; ?>" style="font-size:.72rem;color:var(--teal);text-decoration:none;font-weight:600">Mark read</a><?php endif; ?>
          <a href="?del=<?php echo $n['notif_id']; ?>" style="font-size:.72rem;color:var(--red);text-decoration:none;font-weight:600" onclick="return confirm('Delete this notification?')">Delete</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:3rem">
  <p style="font-size:1.1rem;color:var(--muted)">No notifications.</p>
</div></div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
