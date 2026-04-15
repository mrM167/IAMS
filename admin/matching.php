<?php
// admin/matching.php — Student-Organisation Matching (US-06, US-07) — PHP 7.4 compatible
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

$user = getCurrentUser();
$db   = Database::getInstance();
$msg  = '';

// ── Matching algorithm (PHP 7.4: no str_contains — use strpos instead) ──
function computeMatchScore(array $app, array $org, array $orgJobIds, array $studentJobInterests): float {
    $score = 0;

    // 1. Skill overlap (max 50 pts)
    $studentSkills = array_filter(array_map('trim', explode(',', strtolower($app['skills'] ?? ''))));
    $orgSkills     = array_filter(array_map('trim', explode(',', strtolower($org['required_skills'] ?? ''))));

    if ($studentSkills && $orgSkills) {
        $overlap = 0;
        foreach ($studentSkills as $ss) {
            foreach ($orgSkills as $os) {
                // PHP 7.4: strpos instead of str_contains
                if ($ss && $os && (strpos($ss, $os) !== false || strpos($os, $ss) !== false)) {
                    $overlap++;
                    break;
                }
            }
        }
        $score += min(50, (int)round(($overlap / max(count($orgSkills), 1)) * 50));
    } else {
        $score += 20; // neutral if no skills listed
    }

    // 2. Location preference (max 30 pts)
    $studentLoc = strtolower(trim($app['preferred_location'] ?? ''));
    $orgLoc     = strtolower(trim($org['location'] ?? ''));
    if ($studentLoc && $orgLoc) {
        if ($studentLoc === $orgLoc
            || strpos($orgLoc, $studentLoc) !== false
            || strpos($studentLoc, $orgLoc) !== false
        ) {
            $score += 30;
        } else {
            $score += 5;
        }
    } else {
        $score += 15;
    }

    // 3. Capacity available (10 pts)
    if ((int)($org['available'] ?? 0) > 0) $score += 10;

    // 4. Job interest bonus (10 pts)
    if (array_intersect($orgJobIds, $studentJobInterests)) $score += 10;

    return min(100.0, (float)$score);
}

// ── POST handlers ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'run_matching') {
        $apps = $db->query("
            SELECT a.*, sp.skills as profile_skills
            FROM applications a
            LEFT JOIN student_profiles sp ON a.user_id=sp.user_id
            WHERE a.status IN ('pending','under_review') AND a.matched_org_id IS NULL
        ")->fetchAll();

        $orgs = $db->query("
            SELECT o.*,
                   (o.capacity - COALESCE(
                     (SELECT COUNT(*) FROM matches m WHERE m.org_id=o.org_id AND m.status='confirmed'),0
                   )) as available
            FROM organisations o WHERE o.is_active=1
        ")->fetchAll();

        $allInterests = $db->query("SELECT user_id, job_id FROM job_interests")->fetchAll();
        $interestMap  = [];
        foreach ($allInterests as $i) {
            $interestMap[$i['user_id']][] = (int)$i['job_id'];
        }

        $allJobsMap = [];
        foreach ($db->query("SELECT job_id, org_id FROM job_posts WHERE is_active=1")->fetchAll() as $j) {
            $allJobsMap[(int)$j['org_id']][] = (int)$j['job_id'];
        }

        $inserted = 0;
        foreach ($apps as $app) {
            $app['skills'] = $app['skills'] ?: ($app['profile_skills'] ?? '');
            $studentInterests = $interestMap[$app['user_id']] ?? [];
            $best      = null;
            $bestScore = -1;

            foreach ($orgs as $org) {
                if ((int)$org['available'] < 1) continue;
                $orgJobIds = $allJobsMap[(int)$org['org_id']] ?? [];
                $score = computeMatchScore($app, $org, $orgJobIds, $studentInterests);
                if ($score > $bestScore) { $bestScore = $score; $best = $org; }
            }

            if ($best !== null && $bestScore >= 20) {
                $db->prepare("DELETE FROM matches WHERE app_id=? AND status='suggested'")->execute([$app['app_id']]);
                $db->prepare("INSERT INTO matches (app_id,user_id,org_id,match_score,status,coordinator_id) VALUES (?,?,?,?,?,?)")
                   ->execute([$app['app_id'], $app['user_id'], $best['org_id'], $bestScore, 'suggested', $user['id']]);
                $db->prepare("UPDATE applications SET status='under_review' WHERE app_id=?")->execute([$app['app_id']]);
                $inserted++;
            }
        }
        $msg = "Matching complete. {$inserted} suggestion(s) generated.";
        header('Location: /admin/matching.php?msg=' . urlencode($msg)); exit();
    }

    if ($action === 'confirm') {
        $match_id = (int)($_POST['match_id'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');
        $mStmt = $db->prepare("SELECT * FROM matches WHERE match_id=?"); $mStmt->execute([$match_id]); $match = $mStmt->fetch();
        if ($match) {
            $db->prepare("UPDATE matches SET status='confirmed',notes=?,confirmed_at=NOW() WHERE match_id=?")->execute([$notes, $match_id]);
            $db->prepare("UPDATE applications SET status='matched',matched_org_id=?,reviewed_by=?,reviewed_at=NOW(),review_notes=? WHERE app_id=?")
               ->execute([$match['org_id'], $user['id'], $notes, $match['app_id']]);
            $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
               ->execute([$match['user_id'], 'Attachment Match Confirmed', 'Congratulations! Your attachment placement has been confirmed. Check your dashboard for details.', 'success', '/dashboard.php']);
            $msg = 'Match confirmed!';
        }
        header('Location: /admin/matching.php?msg=' . urlencode($msg)); exit();
    }

    if ($action === 'decline') {
        $match_id = (int)($_POST['match_id'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');
        $db->prepare("UPDATE matches SET status='declined',notes=? WHERE match_id=?")->execute([$notes, $match_id]);
        $msg = 'Match declined.';
        header('Location: /admin/matching.php?msg=' . urlencode($msg)); exit();
    }

    if ($action === 'manual_match') {
        $app_id = (int)($_POST['app_id'] ?? 0);
        $org_id = (int)($_POST['org_id'] ?? 0);
        $notes  = trim($_POST['notes'] ?? '');
        if ($app_id && $org_id) {
            $aStmt = $db->prepare("SELECT * FROM applications WHERE app_id=?"); $aStmt->execute([$app_id]); $appRow = $aStmt->fetch();
            if ($appRow) {
                $db->prepare("DELETE FROM matches WHERE app_id=?")->execute([$app_id]);
                $db->prepare("INSERT INTO matches (app_id,user_id,org_id,match_score,status,coordinator_id,notes,confirmed_at) VALUES (?,?,?,100,'confirmed',?,?,NOW())")
                   ->execute([$app_id, $appRow['user_id'], $org_id, $user['id'], $notes]);
                $db->prepare("UPDATE applications SET status='matched',matched_org_id=?,reviewed_by=?,reviewed_at=NOW(),review_notes=? WHERE app_id=?")
                   ->execute([$org_id, $user['id'], $notes, $app_id]);
                $db->prepare("INSERT INTO notifications (user_id,title,message,type,link) VALUES (?,?,?,?,?)")
                   ->execute([$appRow['user_id'], 'Placement Confirmed', 'You have been manually placed by the coordinator. Check your dashboard.', 'success', '/dashboard.php']);
                $msg = 'Manual match confirmed!';
            }
        }
        header('Location: /admin/matching.php?msg=' . urlencode($msg)); exit();
    }
}

if (isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

// Load data
$suggested = $db->query("
    SELECT m.*,a.full_name,a.programme,a.preferred_location,a.skills,
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
    SELECT m.*,a.full_name,a.programme,o.org_name,o.location as org_location,u.email as student_email
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
    SELECT o.*,
           (o.capacity - COALESCE((SELECT COUNT(*) FROM matches m WHERE m.org_id=o.org_id AND m.status='confirmed'),0)) as available
    FROM organisations o WHERE o.is_active=1 ORDER BY o.org_name
")->fetchAll();

$allApps = $db->query("SELECT a.app_id,a.full_name,a.programme,a.status FROM applications a ORDER BY a.full_name")->fetchAll();

$view = $_GET['view'] ?? 'suggestions';
$pageTitle = 'Matching';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<div class="page-wrap">
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;margin-bottom:1rem">
  <div>
    <div class="page-title">&#129302; Student-Organisation Matching</div>
    <div class="page-sub"><?php echo count($unmatched); ?> unmatched &middot; <?php echo count($suggested); ?> suggestions pending &middot; <?php echo count($confirmed); ?> confirmed</div>
  </div>
  <form method="POST" onsubmit="return confirm('Run matching algorithm on all unmatched applications?')">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="run_matching">
    <button type="submit" class="btn btn-gold">&#128640; Run Auto-Matching</button>
  </form>
</div>

<?php if ($msg): ?><div class="alert alert-success">&#10003; <?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem;border-bottom:2px solid #e5e7eb;padding-bottom:.75rem">
  <?php
  $tabs = [
    'suggestions' => 'Suggestions (' . count($suggested) . ')',
    'confirmed'   => 'Confirmed (' . count($confirmed) . ')',
    'unmatched'   => 'Unmatched (' . count($unmatched) . ')',
    'manual'      => 'Manual Match',
  ];
  foreach ($tabs as $t => $label):
  ?>
  <a href="?view=<?php echo $t; ?>" style="padding:.5rem 1rem;border-radius:7px;text-decoration:none;font-size:.85rem;font-weight:600;<?php echo $view===$t?'background:var(--navy);color:#fff':'color:var(--muted)'; ?>"><?php echo $label; ?></a>
  <?php endforeach; ?>
</div>

<?php if ($view === 'suggestions'): ?>
  <?php if ($suggested): ?>
    <?php foreach ($suggested as $m): ?>
    <div class="card" style="margin-bottom:1.25rem">
      <div style="background:linear-gradient(90deg,#0a2f44,#1a5a7a);padding:.75rem 1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <span style="color:#fff;font-weight:600;font-size:.95rem"><?php echo htmlspecialchars($m['full_name']); ?> &rarr; <?php echo htmlspecialchars($m['org_name']); ?></span>
        <span style="background:#c9a84c;color:#0a2f44;padding:.3rem .9rem;border-radius:20px;font-weight:700;font-size:.9rem"><?php echo number_format($m['match_score'], 1); ?>% match</span>
      </div>
      <div class="card-body">
        <div class="grid-2" style="margin-bottom:1rem">
          <div style="padding:1rem;background:#f8f9fb;border-radius:8px">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--teal);margin-bottom:.5rem">Student</div>
            <div><strong><?php echo htmlspecialchars($m['full_name']); ?></strong></div>
            <div class="text-muted"><?php echo htmlspecialchars($m['student_email']); ?></div>
            <div class="text-muted">&#128218; <?php echo htmlspecialchars($m['programme']); ?></div>
            <div class="text-muted">&#128205; Prefers: <?php echo htmlspecialchars($m['preferred_location'] ?: 'Any'); ?></div>
            <div style="margin-top:.5rem;font-size:.82rem"><strong>Skills:</strong> <?php echo htmlspecialchars($m['skills'] ?: '—'); ?></div>
          </div>
          <div style="padding:1rem;background:#f0f7f0;border-radius:8px">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--green);margin-bottom:.5rem">Organisation</div>
            <div><strong><?php echo htmlspecialchars($m['org_name']); ?></strong></div>
            <div class="text-muted">&#128205; <?php echo htmlspecialchars($m['org_location'] ?: '—'); ?></div>
            <div class="text-muted">&#128101; Capacity: <?php echo $m['capacity']; ?> (<?php echo $m['org_used']; ?> filled)</div>
            <div style="margin-top:.5rem;font-size:.82rem"><strong>Needs:</strong> <?php echo htmlspecialchars($m['required_skills'] ?: '—'); ?></div>
          </div>
        </div>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
          <form method="POST" style="display:flex;gap:.5rem;flex:1;min-width:260px">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="confirm">
            <input type="hidden" name="match_id" value="<?php echo $m['match_id']; ?>">
            <input type="text" name="notes" placeholder="Optional notes..." style="flex:1;padding:.5rem .75rem;border:1px solid #ddd;border-radius:7px;font-size:.85rem">
            <button type="submit" class="btn btn-green">&#10003; Confirm</button>
          </form>
          <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="match_id" value="<?php echo $m['match_id']; ?>">
            <button type="submit" class="btn btn-red" onclick="return confirm('Decline this match?')">&#10007; Decline</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="card"><div class="card-body" style="text-align:center;padding:2.5rem">
      <p style="font-size:1.05rem;margin-bottom:1rem">No suggestions yet.</p>
      <p class="text-muted">Click <strong>Run Auto-Matching</strong> to generate suggestions.</p>
    </div></div>
  <?php endif; ?>

<?php elseif ($view === 'confirmed'): ?>
  <div class="card">
  <table><thead><tr><th>Student</th><th>Organisation</th><th>Programme</th><th>Location</th><th>Notes</th><th>Confirmed</th></tr></thead><tbody>
  <?php foreach ($confirmed as $m): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($m['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($m['student_email']); ?></span></td>
    <td><strong><?php echo htmlspecialchars($m['org_name']); ?></strong></td>
    <td class="text-muted"><?php echo htmlspecialchars($m['programme']); ?></td>
    <td class="text-muted"><?php echo htmlspecialchars($m['org_location']); ?></td>
    <td class="text-muted" style="font-size:.82rem"><?php echo htmlspecialchars($m['notes'] ?: '—'); ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo $m['confirmed_at'] ? date('j M Y', strtotime($m['confirmed_at'])) : '—'; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$confirmed): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">No confirmed matches yet.</td></tr><?php endif; ?>
  </tbody></table>
  </div>

<?php elseif ($view === 'unmatched'): ?>
  <div class="card">
  <table><thead><tr><th>Student</th><th>Programme</th><th>Skills</th><th>Preferred Location</th><th>Submitted</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($unmatched as $a): ?>
  <tr>
    <td><strong><?php echo htmlspecialchars($a['full_name']); ?></strong><br><span class="text-muted"><?php echo htmlspecialchars($a['email']); ?></span></td>
    <td class="text-muted"><?php echo htmlspecialchars($a['programme']); ?></td>
    <td style="font-size:.8rem;color:var(--muted)"><?php echo htmlspecialchars(substr($a['skills'] ?? '—', 0, 60)); ?></td>
    <td class="text-muted"><?php echo htmlspecialchars($a['preferred_location'] ?: 'Any'); ?></td>
    <td class="text-muted" style="font-size:.78rem"><?php echo date('j M Y', strtotime($a['submission_date'])); ?></td>
    <td><span class="badge badge-<?php echo $a['status']; ?>"><?php echo strtoupper($a['status']); ?></span></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$unmatched): ?><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">All applications matched. &#127881;</td></tr><?php endif; ?>
  </tbody></table>
  </div>

<?php elseif ($view === 'manual'): ?>
  <div class="card" style="max-width:560px">
    <div class="card-header"><h3>Manual Match Assignment</h3></div>
    <div class="card-body">
    <p class="text-muted" style="margin-bottom:1rem">Override the algorithm and directly assign a student to an organisation.</p>
    <form method="POST">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="manual_match">
      <div class="form-group">
        <label>Student Application *</label>
        <select name="app_id" required>
          <option value="">-- Choose student --</option>
          <?php foreach ($allApps as $a): ?>
          <option value="<?php echo $a['app_id']; ?>"><?php echo htmlspecialchars($a['full_name']); ?> (<?php echo htmlspecialchars($a['programme']); ?>) — <?php echo strtoupper($a['status']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Organisation *</label>
        <select name="org_id" required>
          <option value="">-- Choose organisation --</option>
          <?php foreach ($allOrgs as $o): ?>
          <option value="<?php echo $o['org_id']; ?>"><?php echo htmlspecialchars($o['org_name']); ?> (<?php echo htmlspecialchars($o['location'] ?? ''); ?>) — <?php echo (int)$o['available']; ?> slot(s)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Coordinator Notes</label>
        <textarea name="notes" rows="3" placeholder="Reason for manual assignment..."></textarea>
      </div>
      <button type="submit" class="btn btn-green" onclick="return confirm('Confirm this manual match?')">&#10003; Confirm Manual Match</button>
    </form>
    </div>
  </div>
<?php endif; ?>

</div>
<footer class="site-footer">IAMS &copy; <?php echo date('Y'); ?> &mdash; University of Botswana</footer>
</body></html>
