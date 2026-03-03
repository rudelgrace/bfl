<?php
require_once __DIR__ . '/../../includes/functions.php';
$seasonId = intGet('season_id');
$teamId   = intGet('team_id');
$leagueId = intGet('league_id');

$season = requireSeason($seasonId);
$league = requireLeague($leagueId ?: $season['league_id']);
$leagueContext = $league;
$activeSidebar = 'seasons';
$activeNav     = 'leagues';

$pdo = getDB();

$teamStmt = $pdo->prepare('SELECT * FROM teams WHERE id = ? AND league_id = ?');
$teamStmt->execute([$teamId, $league['id']]);
$team = $teamStmt->fetch();
if (!$team) {
    setFlash('error', 'Team not found.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

$pageTitle = 'Roster: ' . $team['name'];

// Roster is editable during 'upcoming' and 'active' (regular season only).
// Once playoffs are generated (status = 'playoffs') or completed, it is locked.
$canEdit  = in_array($season['status'], ['upcoming', 'active']);
$isLocked = in_array($season['status'], ['playoffs', 'completed']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hard server-side guard — reject if locked regardless of how the request arrived
    if (!$canEdit) {
        setFlash('error', 'Roster is locked. Changes are not allowed once playoffs have been generated.');
        redirect(ADMIN_URL . '/seasons/roster.php?season_id=' . $seasonId . '&team_id=' . $teamId . '&league_id=' . $league['id']);
        exit;
    }

    $action = post('action');

    if ($action === 'add') {
        $pid    = intPost('player_id');
        $jersey = intPost('jersey_number');

        $chk = $pdo->prepare('SELECT id FROM players WHERE id = ? AND league_id = ?');
        $chk->execute([$pid, $league['id']]);

        if (!$chk->fetch()) {
            setFlash('error', 'Player not found in this league.');
        } else {
            try {
                $pdo->prepare('INSERT INTO season_rosters (season_id, team_id, player_id, jersey_number) VALUES (?, ?, ?, ?)')
                    ->execute([$seasonId, $teamId, $pid, $jersey]);
                setFlash('success', 'Player added to roster.');
            } catch (PDOException $e) {
                setFlash('error', $e->getCode() === '23000'
                    ? 'Player is already on a roster this season, or that jersey number is taken.'
                    : 'Error: ' . $e->getMessage());
            }
        }

    } elseif ($action === 'remove') {
        $rid = intPost('roster_id');

        // Check how many games the player has already played this season (for the flash message)
        $playedStmt = $pdo->prepare('
            SELECT COUNT(DISTINCT pgs.game_id)
            FROM player_game_stats pgs
            JOIN games g ON pgs.game_id = g.id
            WHERE g.season_id = ? AND g.status = "completed"
              AND pgs.player_id = (SELECT player_id FROM season_rosters WHERE id = ?)
        ');
        $playedStmt->execute([$seasonId, $rid]);
        $gamesPlayed = (int) $playedStmt->fetchColumn();

        $pdo->prepare('DELETE FROM season_rosters WHERE id = ? AND season_id = ? AND team_id = ?')
            ->execute([$rid, $seasonId, $teamId]);

        if ($gamesPlayed > 0) {
            setFlash('success', 'Player removed from roster. Their recorded stats for this season are preserved in the rankings.');
        } else {
            setFlash('success', 'Player removed from roster.');
        }
    }

    redirect(ADMIN_URL . '/seasons/roster.php?season_id=' . $seasonId . '&team_id=' . $teamId . '&league_id=' . $league['id']);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$rosterStmt = $pdo->prepare("
    SELECT sr.*, p.first_name, p.last_name, p.position, p.photo
    FROM season_rosters sr
    JOIN players p ON sr.player_id = p.id
    WHERE sr.season_id = ? AND sr.team_id = ?
    ORDER BY sr.jersey_number
");
$rosterStmt->execute([$seasonId, $teamId]);
$roster = $rosterStmt->fetchAll();

$availableStmt = $pdo->prepare("
    SELECT p.* FROM players p
    WHERE p.league_id = ?
      AND p.id NOT IN (SELECT player_id FROM season_rosters WHERE season_id = ?)
    ORDER BY p.last_name, p.first_name
");
$availableStmt->execute([$league['id'], $seasonId]);
$available = $availableStmt->fetchAll();

// Games played per player this season — used to show a warning when removing someone with stats
$gamesPerPlayer = [];
if (!empty($roster)) {
    $playerIds = implode(',', array_map('intval', array_column($roster, 'player_id')));
    $gpStmt = $pdo->query("
        SELECT pgs.player_id, COUNT(DISTINCT pgs.game_id) as gp
        FROM player_game_stats pgs
        JOIN games g ON pgs.game_id = g.id
        WHERE g.season_id = $seasonId AND g.status = 'completed'
          AND pgs.player_id IN ($playerIds)
        GROUP BY pgs.player_id
    ");
    foreach ($gpStmt->fetchAll() as $row) {
        $gamesPerPlayer[$row['player_id']] = (int) $row['gp'];
    }
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $league['id'] ?>"><?= clean($league['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>"><?= clean($season['name']) ?></a>
        </li>
        <li class="breadcrumb-item active">Roster — <?= clean($team['name']) ?></li>
    </ol>
</nav>

<div class="d-flex align-items-center gap-3 mb-1">
    <h1 class="h4 fw-bold mb-0"><?= clean($team['name']) ?> — Roster</h1>
    <?= seasonStatusBadge($season['status']) ?>
</div>
<p class="text-muted mb-4" style="font-size:13px">
    <?= count($roster) ?> player<?= count($roster) !== 1 ? 's' : '' ?> · Requires 3–5 players
</p>

<?php if ($isLocked): ?>
<!-- Locked banner -->
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:var(--r-md);padding:14px 18px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-lock" style="color:#ea580c;font-size:16px;flex-shrink:0;"></i>
    <div>
        <div style="font-size:13px;font-weight:700;color:#9a3412;">Roster Locked</div>
        <div style="font-size:12px;color:#c2410c;margin-top:2px;">
            <?= $season['status'] === 'playoffs'
                ? 'Playoffs have been generated. The roster cannot be changed.'
                : 'This season is completed. The roster is view-only.' ?>
        </div>
    </div>
</div>

<?php elseif ($season['status'] === 'active'): ?>
<!-- Editable during regular season banner -->
<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--r-md);padding:14px 18px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-circle-info" style="color:#16a34a;font-size:16px;flex-shrink:0;"></i>
    <div>
        <div style="font-size:13px;font-weight:700;color:#15803d;">Regular Season — Roster Editable</div>
        <div style="font-size:12px;color:#166534;margin-top:2px;">
            You can add or remove players while the regular season is in progress. The roster will be
            <strong>locked automatically</strong> once you generate the playoff bracket.
            Removing a player keeps all their recorded stats intact.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── Current Roster ── -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h2>Current Roster</h2>
                <span class="<?= count($roster) >= 3 ? 'badge-active' : 'badge-upcoming' ?>"><?= count($roster) ?>/5</span>
            </div>

            <?php if (empty($roster)): ?>
            <div class="card-body empty-state py-5">
                <i class="fas fa-users d-block" style="font-size:36px"></i>
                <p>No players yet.</p>
            </div>
            <?php else: ?>

            <?php foreach ($roster as $r):
                $gp = $gamesPerPlayer[$r['player_id']] ?? 0;
            ?>
            <div class="list-item">
                <!-- Jersey -->
                <div style="width:34px;height:34px;border-radius:8px;background:#1a1a2e;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;margin-right:12px;">
                    <?= $r['jersey_number'] ?>
                </div>

                <!-- Photo / initials -->
                <?php if (!empty($r['photo'])): ?>
                <img src="<?= UPLOADS_URL . '/' . $r['photo'] ?>"
                     style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;margin-right:12px;" alt="">
                <?php else: ?>
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#fb923c,#f97316);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;margin-right:12px;">
                    <?= strtoupper(substr($r['first_name'], 0, 1)) ?>
                </div>
                <?php endif; ?>

                <div class="flex-grow-1">
                    <div class="fw-medium"><?= clean($r['first_name'] . ' ' . $r['last_name']) ?></div>
                    <div style="font-size:12px;color:var(--text-3);display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span><?= clean($r['position'] ?? '—') ?></span>
                        <?php if ($gp > 0): ?>
                        <span style="background:#f1f5f9;color:#64748b;font-size:10px;font-weight:600;padding:1px 7px;border-radius:10px;">
                            <?= $gp ?> game<?= $gp !== 1 ? 's' : '' ?> played
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($canEdit): ?>
                <form method="POST" class="ms-2"
                      onsubmit="return confirmRemove(<?= $gp ?>, '<?= clean($r['first_name'] . ' ' . $r['last_name']) ?>')">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="roster_id" value="<?= $r['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from roster">
                        <i class="fas fa-xmark fa-xs"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- ── Add Player panel (only when editable) ── -->
    <?php if ($canEdit): ?>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h2>Add Player</h2></div>
            <div class="card-body">
                <?php if (empty($available)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-user-check fa-2x mb-3 d-block"></i>
                    <p style="font-size:13px;">All players in this league are already rostered this season.</p>
                    <a href="<?= ADMIN_URL ?>/players/create.php?league_id=<?= $league['id'] ?>" class="btn btn-brand btn-sm">
                        <i class="fas fa-plus me-1"></i> Register New Player
                    </a>
                </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Player</label>
                        <select name="player_id" class="form-select" required>
                            <option value="">Select player…</option>
                            <?php foreach ($available as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= clean($p['first_name'] . ' ' . $p['last_name']) ?><?= $p['position'] ? ' — ' . $p['position'] : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jersey Number</label>
                        <input type="number" name="jersey_number" class="form-control"
                               min="0" max="99" required placeholder="e.g. 23">
                    </div>
                    <button type="submit" class="btn btn-brand w-100">
                        <i class="fas fa-plus me-1"></i> Add to Roster
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /row -->

<div class="mt-4">
    <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-light">
        <i class="fas fa-arrow-left me-1"></i> Back to Season
    </a>
</div>

<script>
function confirmRemove(gamesPlayed, name) {
    if (gamesPlayed > 0) {
        return confirm(
            name + ' has played ' + gamesPlayed + ' game(s) this season.\n\n' +
            'Removing them from the roster will NOT delete their stats — ' +
            'they will still appear in season rankings.\n\n' +
            'They will no longer appear in future game scoresheets.\n\n' +
            'Remove anyway?'
        );
    }
    return confirm('Remove ' + name + ' from the roster?');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
