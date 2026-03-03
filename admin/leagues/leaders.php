<?php
/**
 * The Battle 3x3 — All-Time Leaderboards
 * Supports ?type=regular (default) and ?type=playoff
 * Stat switcher: points | three | ft
 */
require_once __DIR__ . '/../../includes/functions.php';
$leagueId      = intGet('league_id');
$stat          = get('stat', 'points');   // points | three | ft
$type          = get('type', 'regular');  // regular | playoff
if (!in_array($type, ['regular', 'playoff'])) $type = 'regular';

$league        = requireLeague($leagueId);
$leagueContext = $league;
$activeSidebar = 'overview';
$activeNav     = 'leagues';
$pageTitle     = ($type === 'playoff' ? 'Playoff Leaders' : 'All-Time Leaders') . ' — ' . $league['name'];

require_once __DIR__ . '/../../includes/header.php';
$pdo = getDB();

// ── Stat config ────────────────────────────────────────────────────────────
$statConfig = [
    'points' => [
        'label'   => $type === 'playoff' ? 'All-Time Playoff Scoring Leaders' : 'All-Time Scoring Leaders',
        'col_label'=> 'Total Points',
        'icon'    => 'fa-basketball',
        'color'   => '#f97316',
        'sum_col' => 'pgs.total_points',
    ],
    'three' => [
        'label'   => $type === 'playoff' ? 'All-Time Playoff 3PT Leaders' : 'All-Time 3PT Leaders',
        'col_label'=> '3PT Made',
        'icon'    => 'fa-circle-dot',
        'color'   => '#8b5cf6',
        'sum_col' => 'pgs.three_points_made',
    ],
    'ft' => [
        'label'   => $type === 'playoff' ? 'All-Time Playoff FT Leaders' : 'All-Time Free Throw Leaders',
        'col_label'=> 'FT Made',
        'icon'    => 'fa-hand-point-up',
        'color'   => '#10b981',
        'sum_col' => 'pgs.free_throws_made',
    ],
];
if (!isset($statConfig[$stat])) $stat = 'points';
$cfg = $statConfig[$stat];

// ── Main leaderboard query — always filters on game_type ──────────────────
$stmt = $pdo->prepare("
    SELECT p.id, p.first_name, p.last_name,
           COALESCE(SUM({$cfg['sum_col']}), 0)         AS stat_value,
           COALESCE(SUM(pgs.total_points), 0)           AS total_points,
           COALESCE(SUM(pgs.three_points_made), 0)      AS total_3pt,
           COALESCE(SUM(pgs.three_points_attempted), 0) AS total_3pt_att,
           COALESCE(SUM(pgs.free_throws_made), 0)       AS total_ft,
           COALESCE(SUM(pgs.free_throws_attempted), 0)  AS total_ft_att,
           COUNT(DISTINCT pgs.game_id)                  AS games,
           COUNT(DISTINCT g.season_id)                  AS seasons
    FROM players p
    JOIN player_game_stats pgs ON pgs.player_id = p.id
    JOIN games g  ON pgs.game_id  = g.id
                 AND g.status     = 'completed'
                 AND g.game_type  = ?
    JOIN seasons s ON g.season_id = s.id
                  AND s.league_id = ?
    WHERE p.league_id = ?
    GROUP BY p.id
    HAVING stat_value > 0
    ORDER BY stat_value DESC, total_points DESC
");
$stmt->execute([$type, $leagueId, $leagueId]);
$leaders = $stmt->fetchAll();

// Check whether league has any playoff game data (to decide if toggle shows)
$hasPlayoffData = (bool) $pdo->prepare("
    SELECT COUNT(*) FROM games g
    JOIN seasons s ON g.season_id = s.id
    WHERE s.league_id = ? AND g.game_type = 'playoff' AND g.status = 'completed'
")->execute([$leagueId]);
$hasPlayoffData = (int) $pdo->query("
    SELECT COUNT(*) FROM games g
    JOIN seasons s ON g.season_id = s.id
    WHERE s.league_id = $leagueId AND g.game_type = 'playoff' AND g.status = 'completed'
")->fetchColumn() > 0;
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a>
        </li>
        <li class="breadcrumb-item active">
            <?= $type === 'playoff' ? 'Playoff Leaders' : 'All-Time Leaders' ?>
        </li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.025em;margin:0;"><?= $cfg['label'] ?></h1>
        <p style="font-size:13px;color:var(--text-3);margin:3px 0 0;">
            <?= clean($league['name']) ?> · <?= count($leaders) ?> players
            <span style="margin-left:8px;font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;
                  <?= $type==='playoff'?'background:#fef3c7;color:#92400e;':'background:#dbeafe;color:#1d4ed8;' ?>">
                <?= $type === 'playoff' ? '🏆 Playoffs' : '📋 Regular Season' ?>
            </span>
        </p>
    </div>
    <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>" class="btn btn-light btn-sm">
        <i class="fas fa-arrow-left fa-xs"></i> Back
    </a>
</div>

<!-- ── Regular / Playoff toggle ── -->
<?php if ($hasPlayoffData): ?>
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="?league_id=<?= $leagueId ?>&stat=<?= $stat ?>&type=regular"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r);font-size:13px;font-weight:600;border:1px solid var(--border);text-decoration:none;
              background:<?= $type==='regular'?'#1d4ed8':'var(--surface)' ?>;color:<?= $type==='regular'?'#fff':'var(--text-2)' ?>;">
        <i class="fas fa-calendar-days" style="font-size:11px;"></i> Regular Season
    </a>
    <a href="?league_id=<?= $leagueId ?>&stat=<?= $stat ?>&type=playoff"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r);font-size:13px;font-weight:600;border:1px solid var(--border);text-decoration:none;
              background:<?= $type==='playoff'?'#b45309':'var(--surface)' ?>;color:<?= $type==='playoff'?'#fff':'var(--text-2)' ?>;">
        <i class="fas fa-trophy" style="font-size:11px;"></i> Playoffs
    </a>
</div>
<?php endif; ?>

<!-- ── Stat switcher ── -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php foreach ($statConfig as $key => $c): ?>
    <a href="?league_id=<?= $leagueId ?>&stat=<?= $key ?>&type=<?= $type ?>"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--r);font-size:13px;font-weight:600;border:1px solid var(--border);text-decoration:none;transition:all .15s;
              background:<?= $stat===$key?$c['color']:'var(--surface)' ?>;color:<?= $stat===$key?'#fff':'var(--text-2)' ?>;">
        <i class="fas <?= $c['icon'] ?>" style="font-size:12px;"></i> <?= $c['col_label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($leaders)): ?>
<div class="card">
    <div class="card-body empty-state py-5">
        <div class="empty-state-icon"><i class="fas fa-chart-bar"></i></div>
        <h5>No data yet</h5>
        <p><?= $type === 'playoff' ? 'Playoff stats appear after playoff games are entered.' : 'Stats appear after regular season games are entered.' ?></p>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2 style="display:flex;align-items:center;gap:8px;">
            <i class="fas <?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;"></i>
            <?= $cfg['label'] ?>
        </h2>
        <span style="font-size:12px;color:var(--text-3);"><?= count($leaders) ?> players ranked</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Player</th>
                    <th class="text-center" style="width:80px;">Games</th>
                    <th class="text-center" style="width:80px;">Seasons</th>
                    <th class="text-center" style="width:90px;">Points</th>
                    <?php if ($stat !== 'ft'): ?>
                    <th class="text-center" style="width:90px;color:#8b5cf6;">3PT Made</th>
                    <th class="text-center" style="width:90px;color:#8b5cf6;">3PT Att</th>
                    <?php endif; ?>
                    <?php if ($stat !== 'three'): ?>
                    <th class="text-center" style="width:90px;color:#10b981;">FT Made</th>
                    <th class="text-center" style="width:90px;color:#10b981;">FT Att</th>
                    <?php endif; ?>
                    <?php if ($stat === 'three'): ?>
                    <th class="text-center" style="width:90px;color:#8b5cf6;">3PT%</th>
                    <?php elseif ($stat === 'ft'): ?>
                    <th class="text-center" style="width:90px;color:#10b981;">FT%</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($leaders as $i => $p):
                $rank = $i + 1;
            ?>
            <tr>
                <td>
                    <span class="rank-badge <?= $rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':'rank-n')) ?>">
                        <?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank ?>
                    </span>
                </td>
                <td>
                    <a href="<?= ADMIN_URL ?>/players/view.php?id=<?= $p['id'] ?>&league_id=<?= $leagueId ?>"
                       style="font-weight:600;color:var(--text-1);">
                        <?= clean($p['first_name'] . ' ' . $p['last_name']) ?>
                    </a>
                </td>
                <td class="text-center" style="color:var(--text-3);"><?= $p['games'] ?></td>
                <td class="text-center" style="color:var(--text-3);"><?= $p['seasons'] ?></td>
                <td class="text-center"><?= $p['total_points'] ?></td>
                <?php if ($stat !== 'ft'): ?>
                <td class="text-center">
                    <span style="font-weight:700;color:#8b5cf6;"><?= $p['total_3pt'] ?></span>
                </td>
                <td class="text-center">
                    <span style="font-size:13px;color:var(--text-3);"><?= $p['total_3pt_att'] ?></span>
                </td>
                <?php endif; ?>
                <?php if ($stat !== 'three'): ?>
                <td class="text-center">
                    <span style="font-weight:700;color:#10b981;"><?= $p['total_ft'] ?></span>
                </td>
                <td class="text-center">
                    <span style="font-size:13px;color:var(--text-3);"><?= $p['total_ft_att'] ?></span>
                </td>
                <?php endif; ?>
                <?php if ($stat === 'three'): ?>
                <td class="text-center">
                    <?php $pct3 = $p['total_3pt_att'] > 0 ? round($p['total_3pt'] / $p['total_3pt_att'] * 100, 1) : 0; ?>
                    <span style="font-size:16px;font-weight:800;color:#8b5cf6;"><?= $pct3 ?>%</span>
                </td>
                <?php elseif ($stat === 'ft'): ?>
                <td class="text-center">
                    <?php $pctft = $p['total_ft_att'] > 0 ? round($p['total_ft'] / $p['total_ft_att'] * 100, 1) : 0; ?>
                    <span style="font-size:16px;font-weight:800;color:#10b981;"><?= $pctft ?>%</span>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>