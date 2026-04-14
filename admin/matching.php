<?php
// admin/matching.php — Student-Organisation Matching Algorithm (US-06, US-07)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = $err = '';

// ═══════════════════════════════════════════════════════════════════
// MATCHING ALGORITHM
// Score (0–100) based on:
//   - Skill overlap          (max 50 pts)
//   - Location preference    (max 30 pts)
//   - Capacity available     (10 pts — passes or fails)
//   - Job interest expressed (10 pts bonus)
// ═══════════════════════════════════════════════════════════════════
function computeMatchScore(array $app, array $org, array $orgJobIds, array $studentJobInterests): float {
    $score = 0;

    // 1. Skill matching
    $studentSkills = array_map('trim', explode(',', strtolower($app['skills'] ?? '')));
    $orgSkills     = array_map('trim', explode(',', strtolower($org['required_skills'] ?? '')));
    $studentSkills = array_filter($studentSkills);
    $orgSkills     = array_filter($orgSkills);
    if ($studentSkills && $orgSkills) {
        $overlap = 0;
        foreach ($studentSkills as $ss) {
            foreach ($orgSkills as $os) {
                if ($ss && $os && (str_contains($ss, $os) || str_contains($os, $ss))) {
                    $overlap++;
                    break;
                }
            }
        }
        $score += min(50, round(($overlap / max(count($orgSkills), 1)) * 50));
    } else {
        $score += 20; // no skills listed — neutral
    }

    // 2. Location preference
    $studentLoc = strtolower(trim($app['preferred_location'] ?? ''));
    $orgLoc     = strtolower(trim($org['location'] ?? ''));
    if ($studentLoc && $orgLoc) {
        if ($studentLoc === $orgLoc || str_contains($orgLoc, $studentLoc) || str_contains($studentLoc, $orgLoc)) {
            $score += 30;
        } else {
            $score += 5; // different location but not disqualifying
        }
    } else {
        $score += 15; // unknown — neutral
    }

    // 3. Capacity (org has slots available)
    if ($org['available'] > 0) $score += 10;

    // 4. Job interest bonus
    if (array_intersect($orgJobIds, $studentJobInterests)) $score += 10;

    return min(100, $score);
}

// ── Handle POST actions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // Run auto-matching
    if ($action === 'run_matching') {
        // Get all unmatched pending/under_review applications
        $apps = $db->query("
            SELECT a.*, sp.skills as profile_skills
            FROM applications a
            LEFT JOIN student_profiles sp ON a.user_id = sp.user_id
            WHERE a.status IN ('pending','under_review') AND a.matched_org_id IS NULL
        ")->fetchAll();

        // Get all active orgs with capacity info
        $orgs = $db->query("
            SELECT o.*,
                   o.capacity - COALESCE(
                     (SELECT COUNT(*) FROM matches m WHERE m.org_id=o.org_id AND m.status='confirmed'), 0
                   ) as available
            FROM organisations o WHERE o.is_active=1
        ")->fetchAll();

        // Get job interests per student
        $allInterests = $db->query("SELECT user_id, job_id FROM job_interests")->fetchAll();
        $interestMap  = [];
        foreach ($allInterests as $i) $interestMap[$i['user_id']][] = $i['job_id'];

        // Get job IDs per org
        $allJobsMap = [];
        foreach ($db->query("SELECT job_id, org_id FROM job_posts WHERE is_active=1")->fetchAll() as $j) {
            $allJobsMap[$j['org_id']][] = $j['job_id'];
        }

        $inserted = 0;
        foreach ($apps as $app) {
            // Merge profile skills with application skills
            $app['skills'] = $app['skills'] ?: ($app['profile_skills'] ?? '');
            $studentInterests = $interestMap[$app['user_id']] ?? [];
            $best = null; $bestScore = -1;

            foreach ($orgs as $org) {
                if ($org['available'] < 1) continue;
                $orgJobIds = $allJobsMap[$org['org_id']] ?? [];
                $score = computeMatchScore($app, $org, $orgJobIds, $studentInterests);
                if ($score > $bestScore) { $bestScore = $score; $best = $org; }
            }

            if ($best && $bestScore >= 20) {
                // Delete old suggested match if exists
                $db->prepare("DELETE FROM matches WHERE app_id=? AND status='suggested'")->execute([$app['app_id']]);
                $db->prepare("INSERT INTO matches (app_id,user_id,org_id,match_score,status,coordinator_id) VALUES (?,?,?,?,?,?)")
                   ->execute([$app['app_id'],$app['user_id'],$best['org_id'],$bestScore,'suggested',$user['id']]);
                $db->prepare("UPDATE applications SET status='under_review' WHERE app_id=?")->execute([$app['app_id']]);
                $inserted++;
            }
        }
        $msg = "Matching complete. {$inserted} suggestion(s) generated.";
    }

    // Confirm a match
    if ($action === 'confirm') {
        $match_id = (int)($_POST['match_id'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');
        $match = $db->prepare("SELECT * FROM matches WHERE match_id=?")->execute([$match_id]) ? null : null;
        $mStmt = $db->prepare("SELECT * FROM matches WHERE match_id=?"); $mStmt->execute([$match_id]); $match = $mStmt->fetch();
        if ($match) {
            $db->prepare("UPDATE matches SET status='confirmed',notes=?,confirmed_at=NOW() WHERE match_id=?")->execute([$notes,$match_id]);
            $db->prepare("UPDATE applications SET status='matched',matched_org_id=?,reviewed_by=?,reviewed_at=NOW(),review_notes=? WHERE app_id=?")
               ->execute([$match['org_id'],$user['id'],$notes,$match['app_id']]);
            // Notify student
            $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
               ->execute([$match['user_id'],'Attachment Match Confirmed','Congratulations! Your attachment placement has been confirmed. Check your application for details.','success','/dashboard.php?tab=home']);
            $msg = 'Match confirmed!';
        }
    }

    // Decline a match
    if ($action === 'decline') {
        $match_id = (int)($_POST['match_id'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');
        $db->prepare("UPDATE matches SET status='declined',notes=? WHERE match_id=?")->execute([$notes,$match_id]);
        $msg = 'Match declined.';
    }

    // Manual match assignment
    if ($action === 'manual_match') {
        $app_id = (int)($_POST['app_id'] ?? 0);
        $org_id = (int)($_POST['org_id'] ?? 0);
        $notes  = trim($_POST['notes'] ?? '');
        if ($app_id && $org_id) {
            $aStmt = $db->prepare("SELECT * FROM applications WHERE app_id=?"); $aStmt->execute([$app_id]); $appRow = $aStmt->fetch();
            $db->prepare("DELETE FROM matches WHERE app_id=?")->execute([$app_id]);
            $db->prepare("INSERT INTO matches (app_id,user_id,org_id,match_score,status,coordinator_id,notes,confirmed_at) VALUES (?,?,?,?,?,?,?,NOW())")
               ->execute([$app_id,$appRow['user_id'],$org_id,100,'confirmed',$user['id'],$notes]);
            $db->prepare("UPDATE applications SET status='matched',matched_org_id=?,reviewed_by=?,reviewed_at=NOW(),review_notes=? WHERE app_id=?")
               ->execute([$org_id,$user['id'],$notes,$app_id]);
            $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
               ->execute([$appRow['user_id'],'Placement Confirmed','You have been manually placed by the coordinator. Check your dashboard for details.','success','/dashboard.php?tab=home']);
            $msg = 'Manual match confirmed!';
        }
    }

    if ($_SERVER['REQUEST_METHOD']==='POST') { header('Location: /admin/matching.php?msg='.urlencode($msg)); exit(); }
}

if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// Load data for display
$suggested = $db->query("
    SELECT m.*,a.full_name,a.programme,a.status as app_status,a.preferred_location,a.skills,
           o.org_name,o.location as org_location,o.required_skills,o.capacity,
           u.email as student_email,
           (SELECT COUNT(*) FROM matches m2 WHERE m2.org_id=m.org_id AND m2.status='confirmed') as org_used
    FROM matches m
    JOIN applications a ON m.app_id=a.app_id
    JOIN organisations o ON m.org_id=o.org_id
    JOIN users u ON m.user_id=u.user_id
    WHERE m.status='suggested'
    ORDER BY m.match_score DESC
")->fetchAll();

$confirmed = $db->query("
    SELECT m.*,a.full_name,a.programme,o.org_name,o.location as org_location,u.email as student_email,m.confirmed_at
    FROM matches m
    JOIN applications a ON m.app_id=a.app_id
    JOIN organisations o ON m.org_id=o.org_id
    JOIN users u ON m.user_id=u.user_id
    WHERE m.status='confirmed'
    ORDER BY m.confirmed_at DESC
")->fetchAll();

$unmatched = $db->query("
    SELECT a.*,u.email FROM applications a JOIN users u ON a.user_id=u.user_id
    WHERE a.status IN ('pending','under_review') AND a.matched_org_id IS NULL
    ORDER BY a.submission_date ASC
")->fetchAll();

$allOrgs = $db->query("
    SELECT o.*,(o.capacity - COALESCE((SELECT COUNT(*) FROM matches m WHERE m.org_id=o.org_id AND m.status='confirmed'),0)) as available
    FROM organisations o WHERE o.is_active=1 ORDER BY o.org_name
")->fetchAll();

$pendingCount = count($unmatched);

$pageTitle = 'Matching';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:.75rem">
  <div>
    <div class="page-title">🤖 Student-Organisation Matching</div>
    <div class="page-sub"><?php echo $pendingCount; ?> unmatched application(s) · <?php echo count($suggested); ?> suggestion(s) pending review · <?php echo count($confirmed); ?> confirmed</div>
  </div>
  <form method="POST" onsubmit="return confirm('Run matching algorithm on all unmatched applications?')">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="run_matching">
    <button type="submit" class="btn btn-gold">🚀 Run Auto-Matching</button>
  </form>
</div>

<?php if ($msg): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- Tab nav -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <?php foreach(['suggestions'=>'🔍 Suggestions ('.count($suggested).')','confirmed'=>'✅ Confirmed ('.count($confirmed).')','unmatched'=>'⏳ Unmatched ('.$pendingCount.')','manual'=>'✋ Manual Match'] as $t=>$label): ?>
  <?php $active = ($_GET['view']??'suggestions')===$t; ?>
  <a href="?view=<?php echo $t; ?>" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $active?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<?php $view = $_GET['view'] ?? 'suggestions'; ?>

<!-- ── SUGGESTIONS ── -->
<?php if ($view === 'suggestions'): ?>
<?php if ($suggested): ?>
<?php foreach ($suggested as $m): ?>
<div class="card" style="margin-bottom:1.25rem">
  <div style="background:linear-gradient(90deg,var(--navy),var(--teal));padding:.75rem 1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
    <span style="color:#fff;font-weight:600;font-size:.95rem"><?php echo htmlspecialchars($m['full_name']); ?> → <?php echo htmlspecialchars($m['org_name']); ?></span>
    <span style="background:var(--gold);color:var(--navy);padding:.3rem .9rem;border-radius:20px;font-weight:700;font-size:.9rem"><?php echo number_format($m['match_score'],1); ?>% match</span>
  </div>
  <div class="card-body">
    <div class="grid-2" style="margin-bottom:1rem">
      <div style="padding:1rem;background:#f8f9fb;border-radius:8px">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--teal);margin-bottom:.5rem">👤 Student</div>
        <div style="font-size:.88rem"><b><?php echo htmlspecialchars($m['full_name']); ?></b></div>
        <div class="text-muted"><?php echo htmlspecialchars($m['student_email']); ?></div>
        <div class="text-muted">📚 <?php echo htmlspecialchars($m['programme']); ?></div>
        <div class="text-muted">📍 Prefers: <?php echo htmlspecialchars($m['preferred_location']??'Any'); ?></div>
        <div style="margin-top:.5rem;font-size:.8rem"><b>Skills:</b> <?php echo htmlspecialchars($m['skills']??'—'); ?></div>
      </div>
      <div style="padding:1rem;background:#f0f7f0;border-radius:8px">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--green);margin-bottom:.5rem">🏢 Organisation</div>
        <div style="font-size:.88rem"><b><?php echo htmlspecialchars($m['org_name']); ?></b></div>
        <div class="text-muted">📍 <?php echo htmlspecialchars($m['org_location']??'—'); ?></div>
        <div class="text-muted">👥 Capacity: <?php echo $m['capacity']; ?> (<?php echo ($m['org_used']??0); ?> filled)</div>
        <div style="margin-top:.5rem;font-size:.8rem"><b>Needs:</b> <?php echo htmlspecialchars($m['required_skills']??'—'); ?></div>
      </div>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <form method="POST" style="display:flex;gap:.5rem;flex:1;min-width:260px">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="match_id" value="<?php echo $m['match_id']; ?>">
        <input type="text" name="notes" placeholder="Optional coordinator notes..." style="flex:1;padding:.5rem .75rem;border:1px solid #ddd;border-radius:7px;font-size:.85rem">
        <button type="submit" class="btn btn-green">✅ Confirm</button>
      </form>
      <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="decline">
        <input type="hidden" name="match_id" value="<?php echo $m['match_id']; ?>">
        <button type="submit" class="btn btn-red" onclick="return confirm('Decline this match?')">✗ Decline</button>
      </form>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:2.5rem">
  <p style="font-size:1.1rem;margin-bottom:1rem">No suggested matches yet.</p>
  <p class="text-muted">Click <b>Run Auto-Matching</b> to generate suggestions based on skills and location preferences.</p>
</div></div>
<?php endif; ?>

<!-- ── CONFIRMED ── -->
<?php elseif ($view === 'confirmed'): ?>
<div class="card">
<table><thead><tr><th>Student</th><th>Organisation</th><th>Programme</th><th>Location</th><th>Notes</th><th>Confirmed</th></tr></thead><tbody>
<?php foreach ($confirmed as $m): ?>
<tr>
  <td><strong><?php echo htmlspecialchars($m['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($m['student_email']); ?></span></td>
  <td><strong><?php echo htmlspecialchars($m['org_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($m['org_location']); ?></span></td>
  <td class="text-muted"><?php echo htmlspecialchars($m['programme']); ?></td>
  <td class="text-muted"><?php echo htmlspecialchars($m['org_location']); ?></td>
  <td class="text-muted" style="font-size:.8rem"><?php echo htmlspecialchars($m['notes']??'—'); ?></td>
  <td class="text-muted" style="font-size:.78rem"><?php echo date('j M Y',strtotime($m['confirmed_at'])); ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$confirmed): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No confirmed matches yet.</td></tr><?php endif; ?>
</tbody></table>
</div>

<!-- ── UNMATCHED ── -->
<?php elseif ($view === 'unmatched'): ?>
<div class="card">
<table><thead><tr><th>Student</th><th>Programme</th><th>Skills</th><th>Preferred Location</th><th>Submitted</th><th>Status</th></tr></thead><tbody>
<?php foreach ($unmatched as $a): ?>
<tr>
  <td><strong><?php echo htmlspecialchars($a['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($a['email']); ?></span></td>
  <td class="text-muted"><?php echo htmlspecialchars($a['programme']); ?></td>
  <td style="font-size:.8rem;color:var(--muted)"><?php echo htmlspecialchars(substr($a['skills']??'—',0,60)); ?></td>
  <td class="text-muted"><?php echo htmlspecialchars($a['preferred_location']??'Any'); ?></td>
  <td class="text-muted" style="font-size:.78rem"><?php echo date('j M Y',strtotime($a['submission_date'])); ?></td>
  <td><span class="badge badge-<?php echo $a['status']; ?>"><?php echo strtoupper($a['status']); ?></span></td>
</tr>
<?php endforeach; ?>
<?php if (!$unmatched): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">All applications have been matched. 🎉</td></tr><?php endif; ?>
</tbody></table>
</div>

<!-- ── MANUAL MATCH ── -->
<?php elseif ($view === 'manual'): ?>
<div class="card" style="max-width:600px">
<div class="card-header"><h3>✋ Manual Match Assignment</h3></div>
<div class="card-body">
<p class="text-muted" style="margin-bottom:1rem">Override the algorithm and directly assign a student to an organisation.</p>
<form method="POST">
  <?php echo csrf_field(); ?>
  <input type="hidden" name="action" value="manual_match">
  <div class="form-group">
    <label>Select Student Application *</label>
    <select name="app_id" required>
      <option value="">— Choose student —</option>
      <?php
      $allApps = $db->query("SELECT a.app_id,a.full_name,a.programme,a.status FROM applications a ORDER BY a.full_name")->fetchAll();
      foreach ($allApps as $a): ?>
      <option value="<?php echo $a['app_id']; ?>"><?php echo htmlspecialchars($a['full_name']); ?> (<?php echo $a['programme']; ?>) — <?php echo strtoupper($a['status']); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Select Organisation *</label>
    <select name="org_id" required>
      <option value="">— Choose organisation —</option>
      <?php foreach ($allOrgs as $o): ?>
      <option value="<?php echo $o['org_id']; ?>"><?php echo htmlspecialchars($o['org_name']); ?> (<?php echo htmlspecialchars($o['location']??''); ?>) — <?php echo $o['available']; ?> slot(s)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Coordinator Notes</label>
    <textarea name="notes" rows="3" placeholder="Reason for manual assignment..."></textarea>
  </div>
  <button type="submit" class="btn btn-green" onclick="return confirm('Confirm this manual match?')">✅ Confirm Manual Match</button>
</form>
</div>
</div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS © <?php echo date('Y'); ?> — University of Botswana</footer>
</body></html>