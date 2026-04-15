<?php
// admin/documents.php — All student documents
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();

$search = trim($_GET['q'] ?? '');
$type   = $_GET['type'] ?? '';

$where  = [];
$params = [];
if ($search) {
    $where[]  = "(u.full_name LIKE ? OR u.student_number LIKE ? OR u.email LIKE ?)";
    $like     = "%$search%"; $params = array_merge($params, [$like,$like,$like]);
}
if ($type) { $where[] = "d.doc_type=?"; $params[] = $type; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$docs = $db->prepare("
    SELECT d.*,u.full_name,u.student_number,u.email
    FROM documents d JOIN users u ON d.user_id=u.user_id
    $whereSQL
    ORDER BY d.uploaded_at DESC
");
$docs->execute($params);
$docs = $docs->fetchAll();

// Count by type
$typeCounts = $db->query("SELECT doc_type, COUNT(*) as cnt FROM documents GROUP BY doc_type ORDER BY cnt DESC")->fetchAll();

$pageTitle = 'Documents';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div class="page-title">📁 Student Documents</div>
<div class="page-sub"><?php echo count($docs); ?> document(s) found</div>

<!-- Stats by type -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.25rem">
  <a href="/admin/documents.php" style="padding:.35rem .85rem;border-radius:6px;text-decoration:none;font-size:.8rem;font-weight:600;<?php echo !$type?'background:var(--navy);color:#fff':'background:#fff;color:var(--muted);border:1px solid #ddd'; ?>">All</a>
  <?php foreach ($typeCounts as $tc): ?>
  <a href="?type=<?php echo urlencode($tc['doc_type']); ?>" style="padding:.35rem .85rem;border-radius:6px;text-decoration:none;font-size:.8rem;font-weight:600;<?php echo $type===$tc['doc_type']?'background:var(--navy);color:#fff':'background:#fff;color:var(--muted);border:1px solid #ddd'; ?>"><?php echo htmlspecialchars($tc['doc_type']); ?> (<?php echo $tc['cnt']; ?>)</a>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:.5rem;margin-bottom:1.25rem">
  <form method="GET" style="display:flex;gap:.5rem;flex:1;max-width:420px">
    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search student name, number, email..." style="flex:1;padding:.55rem .85rem;border:1px solid #ddd;border-radius:7px;font-size:.85rem">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($search||$type): ?><a href="/admin/documents.php" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card">
<table>
  <thead><tr><th>Student</th><th>Student #</th><th>Document Type</th><th>Filename</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($docs as $d): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($d['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($d['email']); ?></span></td>
    <td class="text-muted"><?php echo htmlspecialchars($d['student_number']??'—'); ?></td>
    <td><span style="background:#e8f0fe;color:#1a3a6a;padding:.2rem .55rem;border-radius:4px;font-size:.75rem;font-weight:700"><?php echo htmlspecialchars($d['doc_type']); ?></span></td>
    <td style="font-size:.82rem;color:var(--muted)"><?php echo htmlspecialchars($d['filename']); ?></td>
    <td class="text-muted" style="font-size:.8rem"><?php echo $d['file_size'] ? round($d['file_size']/1024).'KB' : '—'; ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo date('j M Y H:i',strtotime($d['uploaded_at'])); ?></td>
    <td><a href="/download.php?id=<?php echo $d['doc_id']; ?>" class="btn btn-primary btn-sm" target="_blank">⬇ Download</a></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$docs): ?><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">No documents found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>
