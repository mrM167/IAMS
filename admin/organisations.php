<?php
// admin/organisations.php — All organisations with capacity and match info
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();

if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE organisations SET is_active=1-is_active WHERE org_id=?")->execute([(int)$_GET['toggle']]);
    header('Location: /admin/organisations.php'); exit();
}

$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE (o.org_name LIKE ? OR o.location LIKE ? OR o.industry LIKE ?)" : "";
$params = $search ? ["%$search%","%$search%","%$search%"] : [];

$orgs = $db->prepare("
    SELECT o.*,u.email,u.last_login,
           COUNT(DISTINCT m.match_id) as confirmed_matches,
           COUNT(DISTINCT j.job_id) as job_count,
           GROUP_CONCAT(DISTINCT s.full_name ORDER BY s.full_name SEPARATOR ', ') as matched_students
    FROM organisations o
    JOIN users u ON o.user_id=u.user_id
    LEFT JOIN matches m ON o.org_id=m.org_id AND m.status='confirmed'
    LEFT JOIN users s ON m.user_id=s.user_id
    LEFT JOIN job_posts j ON o.org_id=j.org_id AND j.is_active=1
    $where
    GROUP BY o.org_id ORDER BY o.org_name
");
$orgs->execute($params);
$orgs = $orgs->fetchAll();

// View single org detail
$viewOrg = null;
if (isset($_GET['view'])) {
    $vStmt = $db->prepare("SELECT o.*,u.email,u.last_login FROM organisations o JOIN users u ON o.user_id=u.user_id WHERE o.org_id=?");
    $vStmt->execute([(int)$_GET['view']]);
    $viewOrg = $vStmt->fetch();
    if ($viewOrg) {
        $orgStudents = $db->prepare("
            SELECT u.full_name,u.email,u.student_number,u.programme,sp.skills,sp.linkedin_url,
                   a.status as app_status,m.match_score,m.confirmed_at,m.status as match_status
            FROM matches m
            JOIN users u ON m.user_id=u.user_id
            JOIN applications a ON m.app_id=a.app_id
            LEFT JOIN student_profiles sp ON u.user_id=sp.user_id
            WHERE m.org_id=?
            ORDER BY m.status DESC,m.match_score DESC
        ");
        $orgStudents->execute([$viewOrg['org_id']]);
        $orgStudents = $orgStudents->fetchAll();
        $orgJobs = $db->prepare("SELECT j.*,COUNT(ji.interest_id) as interest_count FROM job_posts j LEFT JOIN job_interests ji ON j.job_id=ji.job_id WHERE j.org_id=? GROUP BY j.job_id ORDER BY j.created_at DESC");
        $orgJobs->execute([$viewOrg['org_id']]);
        $orgJobs = $orgJobs->fetchAll();
    }
}

$pageTitle = 'Organisations';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<?php if ($viewOrg): ?>
  <!-- DETAIL VIEW -->
  <div style="margin-bottom:1rem"><a href="/admin/organisations.php" class="btn btn-outline">← Back to list</a></div>
  <div class="page-title"><?php echo htmlspecialchars($viewOrg['org_name']); ?></div>
  <div class="page-sub"><?php echo htmlspecialchars($viewOrg['industry']??''); ?> · <?php echo htmlspecialchars($viewOrg['location']??''); ?></div>

  <div class="grid-2" style="margin-bottom:1.5rem">
    <div class="card">
      <div class="card-header"><h3>Organisation Details</h3></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <?php foreach(['Contact Person'=>$viewOrg['contact_person'],'Contact Email'=>$viewOrg['contact_email'],'Phone'=>$viewOrg['contact_phone'],'Address'=>$viewOrg['address'],'Location'=>$viewOrg['location'],'Industry'=>$viewOrg['industry'],'Capacity'=>$viewOrg['capacity'].' students','Login Email'=>$viewOrg['email']] as $label=>$val): ?>
          <div><span class="text-muted" style="font-size:.72rem;text-transform:uppercase;font-weight:700"><?php echo $label; ?></span><br><?php echo htmlspecialchars($val??'—'); ?></div>
          <?php endforeach; ?>
        </div>
        <?php if ($viewOrg['description']): ?>
        <div style="margin-top:.75rem"><b>Description:</b><br><span class="text-muted" style="font-size:.85rem"><?php echo nl2br(htmlspecialchars($viewOrg['description'])); ?></span></div>
        <?php endif; ?>
        <?php if ($viewOrg['required_skills']): ?>
        <div style="margin-top:.75rem"><b>Required Skills:</b> <span class="text-muted"><?php echo htmlspecialchars($viewOrg['required_skills']); ?></span></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3>Job Posts (<?php echo count($orgJobs); ?>)</h3></div>
      <?php if ($orgJobs): ?>
      <table><thead><tr><th>Title</th><th>Slots</th><th>Interests</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($orgJobs as $j): ?>
      <tr>
        <td><strong style="font-size:.88rem"><?php echo htmlspecialchars($j['title']); ?></strong><br><span class="text-muted" style="font-size:.78rem"><?php echo htmlspecialchars($j['salary_range']??''); ?></span></td>
        <td style="text-align:center"><?php echo $j['slots']; ?></td>
        <td style="text-align:center"><?php echo $j['interest_count']; ?></td>
        <td><span class="badge badge-<?php echo $j['is_active']?'active':'inactive'; ?>"><?php echo $j['is_active']?'ACTIVE':'HIDDEN'; ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php else: ?><div class="card-body"><p class="text-muted">No job posts.</p></div><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3>Matched/Assigned Students (<?php echo count($orgStudents); ?>)</h3></div>
    <?php if ($orgStudents): ?>
    <table><thead><tr><th>Student</th><th>Student #</th><th>Programme</th><th>Skills</th><th>Score</th><th>Match Status</th><th>App Status</th><th>Confirmed</th></tr></thead><tbody>
    <?php foreach ($orgStudents as $s): ?>
    <tr>
      <td><strong><?php echo htmlspecialchars($s['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($s['email']); ?></span>
      <?php if($s['linkedin_url']): ?><br><a href="<?php echo htmlspecialchars($s['linkedin_url']); ?>" target="_blank" style="font-size:.75rem;color:var(--teal)">LinkedIn ↗</a><?php endif; ?></td>
      <td class="text-muted"><?php echo htmlspecialchars($s['student_number']??'—'); ?></td>
      <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($s['programme']??'—'); ?></td>
      <td style="font-size:.78rem;color:var(--muted)"><?php echo htmlspecialchars(substr($s['skills']??'—',0,50)); ?></td>
      <td style="font-weight:700"><?php echo $s['match_score']?number_format($s['match_score'],1).'%':'—'; ?></td>
      <td><span class="badge badge-<?php echo $s['match_status']==='confirmed'?'accepted':($s['match_status']==='declined'?'rejected':'matched'); ?>"><?php echo strtoupper($s['match_status']); ?></span></td>
      <td><span class="badge badge-<?php echo str_replace(' ','_',$s['app_status']); ?>"><?php echo strtoupper(str_replace('_',' ',$s['app_status'])); ?></span></td>
      <td class="text-muted" style="font-size:.78rem"><?php echo $s['confirmed_at']?date('j M Y',strtotime($s['confirmed_at'])):'Pending'; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php else: ?><div class="card-body"><p class="text-muted">No students matched yet.</p></div><?php endif; ?>
  </div>

<?php else: ?>
  <!-- LIST VIEW -->
  <div class="page-title">🏢 Organisations</div>
  <div class="page-sub"><?php echo count($orgs); ?> organisation(s) registered</div>

  <div style="display:flex;gap:.75rem;margin-bottom:1.25rem">
    <form method="GET" style="display:flex;gap:.5rem;flex:1;max-width:400px">
      <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search name, location, industry..." style="flex:1;padding:.55rem .85rem;border:1px solid #ddd;border-radius:7px;font-size:.88rem">
      <button type="submit" class="btn btn-primary">Search</button>
      <?php if($search): ?><a href="/admin/organisations.php" class="btn btn-outline">Clear</a><?php endif; ?>
    </form>
  </div>

  <div class="card">
  <table>
    <thead><tr><th>Organisation</th><th>Industry</th><th>Location</th><th>Capacity</th><th>Matched</th><th>Available</th><th>Jobs</th><th>Matched Students</th><th>Status</th><th>Login</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orgs as $org): ?>
    <tr>
      <td>
        <strong><a href="?view=<?php echo $org['org_id']; ?>" style="color:var(--navy);text-decoration:none"><?php echo htmlspecialchars($org['org_name']); ?></a></strong><br>
        <span class="text-muted"><?php echo htmlspecialchars($org['email']); ?></span>
      </td>
      <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($org['industry']??'—'); ?></td>
      <td class="text-muted"><?php echo htmlspecialchars($org['location']??'—'); ?></td>
      <td style="text-align:center;font-weight:700"><?php echo $org['capacity']; ?></td>
      <td style="text-align:center;color:var(--green);font-weight:700"><?php echo $org['confirmed_matches']; ?></td>
      <td style="text-align:center;color:<?php echo ($org['capacity']-$org['confirmed_matches'])>0?'var(--navy)':'var(--red)'; ?>;font-weight:700"><?php echo max(0,$org['capacity']-$org['confirmed_matches']); ?></td>
      <td style="text-align:center"><?php echo $org['job_count']; ?></td>
      <td style="font-size:.78rem;color:var(--muted);max-width:180px"><?php echo htmlspecialchars($org['matched_students']??'—'); ?></td>
      <td><span class="badge badge-<?php echo $org['is_active']?'active':'inactive'; ?>"><?php echo $org['is_active']?'ACTIVE':'DISABLED'; ?></span></td>
      <td class="text-muted" style="font-size:.75rem"><?php echo $org['last_login']?date('j M y',strtotime($org['last_login'])):'Never'; ?></td>
      <td style="white-space:nowrap">
        <a href="?view=<?php echo $org['org_id']; ?>" class="btn btn-primary btn-sm">View</a>
        <a href="?toggle=<?php echo $org['org_id']; ?>" class="btn btn-outline btn-sm" onclick="return confirm('Toggle status?')"><?php echo $org['is_active']?'Disable':'Enable'; ?></a>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$orgs): ?><tr><td colspan="11" style="text-align:center;color:var(--muted);padding:2rem">No organisations registered yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>