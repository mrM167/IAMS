<?php
// notifications.php — Notification centre (US-15)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
requireLogin();

$user = getCurrentUser();
$db   = Database::getInstance();

// ============================================================
// HANDLE ACTIONS
// ============================================================

// Mark single notification as read
if (isset($_GET['read'])) {
    $notifId = (int)$_GET['read'];
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE notif_id = ? AND user_id = ?")
       ->execute([$notifId, $user['id']]);
    header('Location: /notifications.php?filter=' . urlencode($_GET['filter'] ?? 'all'));
    exit();
}

// Mark all notifications as read
if (isset($_GET['read_all'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
       ->execute([$user['id']]);
    header('Location: /notifications.php?msg=all_read');
    exit();
}

// Delete a single notification
if (isset($_GET['del'])) {
    $notifId = (int)$_GET['del'];
    $db->prepare("DELETE FROM notifications WHERE notif_id = ? AND user_id = ?")
       ->execute([$notifId, $user['id']]);
    header('Location: /notifications.php?filter=' . urlencode($_GET['filter'] ?? 'all') . '&msg=deleted');
    exit();
}

// ============================================================
// GET FILTER & LOAD NOTIFICATIONS
// ============================================================

$filter  = $_GET['filter'] ?? 'all';
$msg     = $_GET['msg'] ?? '';

// Build WHERE clause based on filter
$whereConditions = [];
$queryParams = [$user['id']];

if ($filter === 'unread') {
    $whereConditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $whereConditions[] = "is_read = 1";
}

$whereSQL = '';
if (!empty($whereConditions)) {
    $whereSQL = 'AND ' . implode(' AND ', $whereConditions);
}

// Load notifications
$sql = "SELECT * FROM notifications 
        WHERE user_id = ? {$whereSQL} 
        ORDER BY created_at DESC 
        LIMIT 100";

$notifStmt = $db->prepare($sql);
$notifStmt->execute($queryParams);
$notifications = $notifStmt->fetchAll();

// Get counts for filter tabs
$countAllStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$countAllStmt->execute([$user['id']]);
$countAll = (int)$countAllStmt->fetchColumn();

$countUnreadStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$countUnreadStmt->execute([$user['id']]);
$countUnread = (int)$countUnreadStmt->fetchColumn();

$countReadStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 1");
$countReadStmt->execute([$user['id']]);
$countRead = (int)$countReadStmt->fetchColumn();

// ============================================================
// ICONS & COLORS
// ============================================================

$typeIcons = [
    'info'     => 'ℹ️',
    'warning'  => '⚠️',
    'success'  => '✅',
    'deadline' => '📅'
];

$typeColors = [
    'info'     => '#d1ecf1',
    'warning'  => '#fff3cd',
    'success'  => '#d4edda',
    'deadline' => '#f8d7da'
];

$msgText = '';
if ($msg === 'all_read') {
    $msgText = '✅ All notifications marked as read.';
} elseif ($msg === 'deleted') {
    $msgText = '🗑️ Notification deleted.';
}

// ============================================================
// PAGE OUTPUT
// ============================================================

$pageTitle = 'Notifications';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">

    <!-- Page Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem">
        <div>
            <div class="page-title">🔔 Notifications</div>
            <div class="page-sub">
                <?php echo $countUnread; ?> unread · <?php echo $countAll; ?> total
            </div>
        </div>
        
        <?php if ($countUnread > 0): ?>
            <a href="?read_all=1" class="btn btn-outline">✅ Mark All as Read</a>
        <?php endif; ?>
    </div>

    <!-- Success Message -->
    <?php if ($msgText): ?>
        <div class="alert alert-success" style="margin-bottom:1.25rem">
            <?php echo $msgText; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div style="display:flex; gap:.4rem; margin-bottom:1.25rem; flex-wrap:wrap">
        <a href="?filter=all" 
           style="padding:.5rem 1rem; border-radius:7px; text-decoration:none; font-size:.84rem; font-weight:600; 
                  <?php echo $filter === 'all' 
                      ? 'background:var(--navy); color:#fff' 
                      : 'background:#fff; color:var(--muted); border:1px solid #ddd'; ?>">
            All (<?php echo $countAll; ?>)
        </a>
        
        <a href="?filter=unread" 
           style="padding:.5rem 1rem; border-radius:7px; text-decoration:none; font-size:.84rem; font-weight:600; 
                  <?php echo $filter === 'unread' 
                      ? 'background:var(--navy); color:#fff' 
                      : 'background:#fff; color:var(--muted); border:1px solid #ddd'; ?>">
            🔵 Unread (<?php echo $countUnread; ?>)
        </a>
        
        <a href="?filter=read" 
           style="padding:.5rem 1rem; border-radius:7px; text-decoration:none; font-size:.84rem; font-weight:600; 
                  <?php echo $filter === 'read' 
                      ? 'background:var(--navy); color:#fff' 
                      : 'background:#fff; color:var(--muted); border:1px solid #ddd'; ?>">
            ✓ Read (<?php echo $countRead; ?>)
        </a>
    </div>

    <!-- Notifications List -->
    <?php if (!empty($notifications)): ?>
        <div class="card">
            <?php foreach ($notifications as $notif): 
                $icon = $typeIcons[$notif['type']] ?? 'ℹ️';
                $bgColor = $typeColors[$notif['type']] ?? '#d1ecf1';
                $isUnread = !$notif['is_read'];
                $rowBg = $isUnread ? '#fffbec' : '#fff';
            ?>
                <div style="padding:1rem 1.25rem; border-bottom:1px solid #f5f5f5; display:flex; gap:1rem; 
                            align-items:flex-start; background:<?php echo $rowBg; ?>; transition:background .2s">
                    
                    <!-- Icon -->
                    <div style="font-size:1.4rem; flex-shrink:0; margin-top:.1rem">
                        <?php echo $icon; ?>
                    </div>
                    
                    <!-- Content -->
                    <div style="flex:1; min-width:0">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; 
                                    gap:1rem; flex-wrap:wrap">
                            <div>
                                <div style="font-weight:<?php echo $isUnread ? '700' : '600'; ?>; 
                                            font-size:.9rem; color:var(--navy); margin-bottom:.2rem">
                                    <?php echo htmlspecialchars($notif['title']); ?>
                                </div>
                                
                                <div style="font-size:.85rem; color:var(--muted); line-height:1.5">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </div>
                                
                                <?php if ($notif['link']): ?>
                                    <a href="<?php echo htmlspecialchars($notif['link']); ?>" 
                                       style="font-size:.8rem; color:var(--teal); font-weight:600; 
                                              margin-top:.3rem; display:inline-block">
                                        View Details →
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Timestamp & Actions -->
                            <div style="text-align:right; flex-shrink:0">
                                <div style="font-size:.75rem; color:#9ca3af; margin-bottom:.4rem">
                                    <?php echo date('j M Y H:i', strtotime($notif['created_at'])); ?>
                                </div>
                                
                                <div style="display:flex; gap:.35rem; justify-content:flex-end">
                                    <?php if ($isUnread): ?>
                                        <a href="?read=<?php echo $notif['notif_id']; ?>&filter=<?php echo urlencode($filter); ?>" 
                                           style="font-size:.72rem; color:var(--teal); text-decoration:none; font-weight:600">
                                            Mark Read
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?del=<?php echo $notif['notif_id']; ?>&filter=<?php echo urlencode($filter); ?>" 
                                       style="font-size:.72rem; color:#c0392b; text-decoration:none; font-weight:600"
                                       onclick="return confirm('Delete this notification?')">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    
    <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align:center; padding:3rem">
                <div style="font-size:2.5rem; margin-bottom:1rem">📭</div>
                <p style="font-size:1.1rem; color:var(--muted); margin-bottom:.5rem">
                    <?php echo $filter === 'unread' ? 'No unread notifications!' : 'No notifications yet.'; ?>
                </p>
                <p style="font-size:.85rem; color:#9ca3af">
                    <?php echo $filter === 'unread' 
                        ? 'You\'re all caught up. 🎉' 
                        : 'Notifications will appear here when there are updates.'; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

</div>

<footer class="site-footer">
    IAMS © <?php echo date('Y'); ?> — University of Botswana
</footer>
</body>
</html>