<?php
require_once __DIR__ . '/../../includes/functions.php';
$playerId = intGet('id');
$leagueId = intGet('league_id');

$playerStmt = getDB()->prepare('SELECT p.*, l.name as league_name FROM players p
    JOIN leagues l ON p.league_id = l.id WHERE p.id = ?');
$playerStmt->execute([$playerId]);
$player = $playerStmt->fetch();

if (!$player) {
    setFlash('error', 'Player not found.');
    redirect(ADMIN_URL . '/players/index.php?league_id=' . $leagueId);
}

$league        = requireLeague($player['league_id']);
$leagueContext = $league;
$activeSidebar = 'players';
$activeNav     = 'leagues';
$pageTitle     = $player['first_name'] . ' ' . $player['last_name'];

require_once __DIR__ . '/../../includes/header.php';
$pdo = getDB();

// ── Career stats (regular/playoff split + career high) ─────────────────────
$career = getPlayerCareerStatsSplit($playerId);

// ── Season history with reg/playoff split ─────────────────────────────────
$seasons = app()['stats']->playerSeasonHistory($playerId);

// ── Recent game log (all game types, last 15) ─────────────────────────────
$recentGames = app()['stats']->playerRecentGames($playerId, 15);

// ── MVP awards ────────────────────────────────────────────────────────────
$mvpAwardsStmt = $pdo->prepare('
    SELECT s.name as season_name, s.status as season_status,
           CASE
               WHEN s.regular_season_mvp_id = ? THEN "Regular Season"
               WHEN s.playoffs_mvp_id = ?       THEN "Playoffs"
           END as award_type
    FROM seasons s
    WHERE (s.regular_season_mvp_id = ? OR s.playoffs_mvp_id = ?)
    ORDER BY s.created_at DESC
');
$mvpAwardsStmt->execute([$playerId, $playerId, $playerId, $playerId]);
$mvpAwards = $mvpAwardsStmt->fetchAll();

// ── All-time rankings (regular season only) ───────────────────────────────
$rankPoints = getPlayerAllTimeRank($playerId, $league['id'], 'COALESCE(SUM(pgs.total_points),0)', 'pts');
$rank3PT    = getPlayerAllTimeRank($playerId, $league['id'], 'COALESCE(SUM(pgs.three_points_made),0)', '3pt');
$rankFT     = getPlayerAllTimeRank($playerId, $league['id'], 'COALESCE(SUM(pgs.free_throws_made),0)', 'ft');

// ── Derived convenience values ────────────────────────────────────────────
$reg3pct = ($career['reg_3pt_att'] ?? 0) > 0
    ? round($career['reg_3pt'] / $career['reg_3pt_att'] * 100) : null;
$regFtpct = ($career['reg_ft_att'] ?? 0) > 0
    ? round($career['reg_ft'] / $career['reg_ft_att'] * 100) : null;
$po3pct  = ($career['po_3pt_att'] ?? 0) > 0
    ? round($career['po_3pt'] / $career['po_3pt_att'] * 100) : null;
$poFtpct  = ($career['po_ft_att'] ?? 0) > 0
    ? round($career['po_ft'] / $career['po_ft_att'] * 100) : null;

// Career-high game info (so we can show which season/type it was)
$careerHighGameStmt = $pdo->prepare('
    SELECT pgs.total_points, g.game_date, g.game_type, g.playoff_round,
           ht.name AS home_team, at.name AS away_team,
           t.name  AS player_team, s.name AS season_name
    FROM player_game_stats pgs
    JOIN games g   ON pgs.game_id    = g.id
    JOIN teams ht  ON g.home_team_id = ht.id
    JOIN teams at  ON g.away_team_id = at.id
    JOIN teams t   ON pgs.team_id    = t.id
    JOIN seasons s ON g.season_id    = s.id
    WHERE pgs.player_id = ? AND g.status = "completed"
    ORDER BY pgs.total_points DESC, g.game_date DESC
    LIMIT 1
');
$careerHighGameStmt->execute([$playerId]);
$careerHighGame = $careerHighGameStmt->fetch();
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $league['id'] ?>"><?= clean($league['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/players/index.php?league_id=<?= $league['id'] ?>">Players</a>
        </li>
        <li class="breadcrumb-item active"><?= clean($player['first_name'] . ' ' . $player['last_name']) ?></li>
    </ol>
</nav>

<!-- ══ PLAYER HEADER ══════════════════════════════════════════════════════ -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <!-- Avatar -->
            <div style="width:90px;height:90px;border-radius:var(--r);overflow:hidden;flex-shrink:0;">
                <?php if (!empty($player['photo'])): ?>
                    <img src="<?= UPLOADS_URL . '/' . $player['photo'] ?>"
                         style="width:100%;height:100%;object-fit:cover;"
                         alt="<?= clean($player['first_name'] . ' ' . $player['last_name']) ?>">
                <?php else: ?>
                    <div style="width:100%;height:100%;background:var(--surface-2);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:var(--text-3);">
                        <?= strtoupper(substr($player['first_name'],0,1) . substr($player['last_name'],0,1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Info -->
            <div class="flex-grow-1">
                <h1 style="font-size:26px;font-weight:800;margin:0 0 6px;">
                    <?= clean($player['first_name'] . ' ' . $player['last_name']) ?>
                </h1>
                <p style="color:var(--text-3);margin:0 0 8px;font-size:13px;">
                    <i class="fas fa-trophy me-1"></i><?= clean($player['league_name']) ?>
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($player['position']): ?>
                        <span class="badge bg-secondary"><?= clean($player['position']) ?></span>
                    <?php endif; ?>
                    <?php if ($player['height']): ?>
                        <span class="badge bg-light text-dark"><?= clean($player['height']) ?></span>
                    <?php endif; ?>
                    <?php if ($player['date_of_birth']): ?>
                        <span class="badge bg-light text-dark">
                            Age <?= floor((time() - strtotime($player['date_of_birth'])) / (365.25*24*60*60)) ?>
                        </span>
                    <?php endif; ?>
                    <?php foreach ($mvpAwards as $a): ?>
                        <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-weight:700;">
                            🏆 <?= clean($a['award_type']) ?> MVP — <?= clean($a['season_name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Career High callout -->
            <?php if (!empty($career['career_high']) && $career['career_high'] > 0): ?>
            <div style="text-align:center;flex-shrink:0;padding:12px 20px;background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:var(--r);color:#fff;">
                <div style="font-size:38px;font-weight:900;line-height:1;color:#f59e0b;"><?= $career['career_high'] ?></div>
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:rgba(255,255,255,.55);margin-top:4px;">Career High</div>
                <?php if ($careerHighGame): ?>
                <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:3px;">
                    <?= $careerHighGame['game_type'] === 'playoff' ? '🏆 ' . ($careerHighGame['playoff_round'] ?? 'Playoff') : '📋 Regular' ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($player['bio'])): ?>
<div class="card mb-4">
    <div class="card-header"><h3>Biography</h3></div>
    <div class="card-body"><p style="line-height:1.6;color:var(--text-2);margin:0;"><?= nl2br(clean($player['bio'])) ?></p></div>
</div>
<?php endif; ?>

<!-- ══ CAREER STATS (Regular Season vs Playoffs side-by-side) ════════════ -->
<div class="row g-3 mb-4">
    <!-- Regular Season card -->
    <div class="col-md-6">
        <div class="card h-100" style="border-top:3px solid #1d4ed8;">
            <div class="card-header" style="background:linear-gradient(135deg,#1e3a5f,#1d4ed8);color:#fff;">
                <h3 style="margin:0;font-size:14px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-calendar-days"></i> Regular Season Career
                </h3>
                <span style="font-size:11px;opacity:.7;"><?= ($career['reg_games'] ?? 0) ?> games</span>
            </div>
            <div class="card-body p-0">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid var(--border);">
                    <?php
                    $regStats = [
                        ['Points',  $career['reg_points'] ?? 0, '#f97316'],
                        ['PPG',     $career['reg_ppg']    ?? '0.0', '#f97316'],
                        ['3PT',     $career['reg_3pt']    ?? 0, '#8b5cf6'],
                    ];
                    foreach ($regStats as [$label, $val, $color]):
                    ?>
                    <div style="text-align:center;padding:16px 8px;border-right:1px solid var(--border);">
                        <div style="font-size:22px;font-weight:800;color:<?= $color ?>;line-height:1;"><?= $val ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;">
                    <div style="text-align:center;padding:14px 8px;border-right:1px solid var(--border);">
                        <div style="font-size:18px;font-weight:700;color:#8b5cf6;line-height:1;"><?= $reg3pct !== null ? $reg3pct.'%' : '—' ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;">3PT%</div>
                        <div style="font-size:10px;color:var(--text-4);"><?= ($career['reg_3pt'] ?? 0) ?>/<?= ($career['reg_3pt_att'] ?? 0) ?></div>
                    </div>
                    <div style="text-align:center;padding:14px 8px;border-right:1px solid var(--border);">
                        <div style="font-size:18px;font-weight:700;color:#10b981;line-height:1;"><?= $regFtpct !== null ? $regFtpct.'%' : '—' ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;">FT%</div>
                        <div style="font-size:10px;color:var(--text-4);"><?= ($career['reg_ft'] ?? 0) ?>/<?= ($career['reg_ft_att'] ?? 0) ?></div>
                    </div>
                    <div style="text-align:center;padding:14px 8px;">
                        <div style="font-size:18px;font-weight:700;color:var(--text-2);line-height:1;"><?= $career['reg_2pt'] ?? 0 ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;">2PT</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Playoffs card -->
    <div class="col-md-6">
        <div class="card h-100" style="border-top:3px solid #b45309;">
            <div class="card-header" style="background:linear-gradient(135deg,#92400e,#b45309);color:#fff;">
                <h3 style="margin:0;font-size:14px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-trophy"></i> Playoff Career
                </h3>
                <span style="font-size:11px;opacity:.7;"><?= ($career['po_games'] ?? 0) ?> games</span>
            </div>
            <?php if (($career['po_games'] ?? 0) === 0): ?>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height:120px;">
                <p style="color:var(--text-4);font-size:13px;margin:0;text-align:center;">
                    <i class="fas fa-trophy" style="font-size:24px;display:block;margin-bottom:8px;opacity:.3;"></i>
                    No playoff games yet
                </p>
            </div>
            <?php else: ?>
            <div class="card-body p-0">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-bottom:1px solid var(--border);">
                    <?php
                    $poStats = [
                        ['Points',  $career['po_points'] ?? 0, '#f59e0b'],
                        ['PPG',     $career['po_ppg']    ?? '0.0', '#f59e0b'],
                        ['3PT',     $career['po_3pt']    ?? 0, '#8b5cf6'],
                    ];
                    foreach ($poStats as [$label, $val, $color]):
                    ?>
                    <div style="text-align:center;padding:16px 8px;border-right:1px solid var(--border);">
                        <div style="font-size:22px;font-weight:800;color:<?= $color ?>;line-height:1;"><?= $val ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0;">
                    <div style="text-align:center;padding:14px 8px;border-right:1px solid var(--border);">
                        <div style="font-size:18px;font-weight:700;color:#8b5cf6;line-height:1;"><?= $po3pct !== null ? $po3pct.'%' : '—' ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;">3PT%</div>
                        <div style="font-size:10px;color:var(--text-4);"><?= ($career['po_3pt'] ?? 0) ?>/<?= ($career['po_3pt_att'] ?? 0) ?></div>
                    </div>
                    <div style="text-align:center;padding:14px 8px;border-right:1px solid var(--border);">
                        <div style="font-size:18px;font-weight:700;color:#10b981;line-height:1;"><?= $poFtpct !== null ? $poFtpct.'%' : '—' ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;">FT%</div>
                        <div style="font-size:10px;color:var(--text-4);"><?= ($career['po_ft'] ?? 0) ?>/<?= ($career['po_ft_att'] ?? 0) ?></div>
                    </div>
                    <div style="text-align:center;padding:14px 8px;">
                        <div style="font-size:18px;font-weight:700;color:var(--text-2);line-height:1;"><?= $career['po_2pt'] ?? 0 ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;">2PT</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══ QUICK CAREER TOTALS ════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-value"><?= $career['total_games'] ?? 0 ?></div>
            <div class="stat-label">Total Games</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-value"><?= $career['seasons_played'] ?? 0 ?></div>
            <div class="stat-label">Seasons</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-value"><?= $career['teams_played_for'] ?? 0 ?></div>
            <div class="stat-label">Teams</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-value" style="color:#f59e0b;"><?= $career['career_high'] ?? 0 ?></div>
            <div class="stat-label">Career High (pts)</div>
        </div>
    </div>
</div>

<!-- ══ ALL-TIME REGULAR SEASON RANKINGS ══════════════════════════════════ -->
<?php if (($career['reg_games'] ?? 0) > 0): ?>
<div class="card mb-4">
    <div class="card-header">
        <h3 style="display:flex;align-items:center;gap:8px;">
            <i class="fas fa-ranking-star" style="color:var(--brand);"></i>
            All-Time League Rankings
        </h3>
        <span style="font-size:11px;color:var(--text-3);">Regular season only · <?= clean($league['name']) ?></span>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <?php foreach ([
                ['Total Points', $rankPoints, '#f97316'],
                ['3PT Made',     $rank3PT,    '#8b5cf6'],
                ['Free Throws',  $rankFT,     '#10b981'],
            ] as [$label, $rankData, $color]):
                $rankNum   = $rankData['rank'];
                $rankTotal = $rankData['total'];
                $rankVal   = $rankData['value'];
                $rankPct   = $rankTotal > 0 ? round((($rankTotal - $rankNum + 1) / $rankTotal) * 100) : 0;
            ?>
            <div class="col-md-4">
                <div style="text-align:center;padding:16px;background:var(--surface-2);border-radius:var(--r);overflow:hidden;">
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);font-weight:700;margin-bottom:8px;"><?= $label ?></div>
                    <?php if ($rankNum > 0): ?>
                    <div style="font-size:36px;font-weight:900;color:<?= $color ?>;line-height:1;">#<?= $rankNum ?></div>
                    <div style="font-size:11px;color:var(--text-3);margin-top:4px;">of <?= $rankTotal ?> players</div>
                    <div style="font-size:13px;font-weight:600;color:var(--text-2);margin-top:6px;"><?= $rankVal ?> total</div>
                    <div style="margin-top:10px;height:4px;background:var(--surface-3);border-radius:4px;overflow:hidden;">
                        <div style="height:100%;width:<?= $rankPct ?>%;background:<?= $color ?>;border-radius:4px;"></div>
                    </div>
                    <div style="font-size:10px;color:var(--text-3);margin-top:4px;">Top <?= 100 - $rankPct + 1 ?>%</div>
                    <?php else: ?>
                    <div style="font-size:24px;color:var(--text-4);">—</div>
                    <div style="font-size:11px;color:var(--text-3);">No data</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ SEASON HISTORY (Regular + Playoff split per season) ════════════════ -->
<div class="card mb-4">
    <div class="card-header">
        <h3>Seasons History</h3>
        <span style="font-size:11px;color:var(--text-3);">Regular season &amp; playoff stats separated per season</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($seasons)): ?>
            <div class="card-body"><p class="text-muted">No seasons participated yet.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border);">
                        <th rowspan="2" style="vertical-align:middle;">Season</th>
                        <th rowspan="2" style="vertical-align:middle;">Team</th>
                        <th rowspan="2" class="text-center" style="vertical-align:middle;">#</th>
                        <!-- Regular season sub-header -->
                        <th colspan="5" class="text-center" style="background:#dbeafe;color:#1d4ed8;font-size:10px;text-transform:uppercase;letter-spacing:.06em;border-left:2px solid #1d4ed8;">
                            📋 Regular Season
                        </th>
                        <!-- Playoff sub-header -->
                        <th colspan="5" class="text-center" style="background:#fef3c7;color:#92400e;font-size:10px;text-transform:uppercase;letter-spacing:.06em;border-left:2px solid #b45309;">
                            🏆 Playoffs
                        </th>
                    </tr>
                    <tr style="font-size:11px;color:var(--text-3);">
                        <th class="text-center" style="border-left:2px solid #1d4ed8;">GP</th>
                        <th class="text-center">PTS</th>
                        <th class="text-center">PPG</th>
                        <th class="text-center" style="color:#8b5cf6;">3PT%</th>
                        <th class="text-center" style="color:#10b981;">FT%</th>
                        <th class="text-center" style="border-left:2px solid #b45309;">GP</th>
                        <th class="text-center">PTS</th>
                        <th class="text-center">PPG</th>
                        <th class="text-center" style="color:#8b5cf6;">3PT%</th>
                        <th class="text-center" style="color:#10b981;">FT%</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($seasons as $s):
                    $r3pct  = $s['reg_3pt_att']  > 0 ? round($s['reg_3pt_made']  / $s['reg_3pt_att']  * 100) : null;
                    $rftpct = $s['reg_ft_att']   > 0 ? round($s['reg_ft_made']   / $s['reg_ft_att']   * 100) : null;
                    $p3pct  = $s['po_3pt_att']   > 0 ? round($s['po_3pt_made']   / $s['po_3pt_att']   * 100) : null;
                    $pftpct = $s['po_ft_att']    > 0 ? round($s['po_ft_made']    / $s['po_ft_att']    * 100) : null;
                ?>
                <tr>
                    <td>
                        <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $s['id'] ?>&league_id=<?= $league['id'] ?>"
                           style="font-weight:600;color:var(--brand);"><?= clean($s['name']) ?></a>
                        <?= seasonStatusBadge($s['status']) ?>
                    </td>
                    <td>
                        <a href="<?= ADMIN_URL ?>/teams/view.php?id=<?= $s['team_id'] ?>&league_id=<?= $league['id'] ?>"
                           style="color:var(--text-2);"><?= clean($s['team_name']) ?></a>
                    </td>
                    <td class="text-center" style="color:var(--text-3);">#<?= $s['jersey_number'] ?></td>

                    <!-- Regular season columns -->
                    <td class="text-center" style="border-left:2px solid #dbeafe;color:var(--text-3);"><?= $s['reg_games'] ?></td>
                    <td class="text-center" style="font-weight:700;"><?= $s['reg_points'] ?: '—' ?></td>
                    <td class="text-center" style="color:#f97316;font-weight:600;"><?= $s['reg_ppg'] ?: '—' ?></td>
                    <td class="text-center" style="color:#8b5cf6;font-weight:600;">
                        <?php if ($r3pct !== null): ?>
                            <?= $r3pct ?>%
                            <div style="font-size:10px;color:var(--text-4);font-weight:400;"><?= $s['reg_3pt_made'] ?>/<?= $s['reg_3pt_att'] ?></div>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-center" style="color:#10b981;font-weight:600;">
                        <?php if ($rftpct !== null): ?>
                            <?= $rftpct ?>%
                            <div style="font-size:10px;color:var(--text-4);font-weight:400;"><?= $s['reg_ft_made'] ?>/<?= $s['reg_ft_att'] ?></div>
                        <?php else: ?>—<?php endif; ?>
                    </td>

                    <!-- Playoff columns -->
                    <?php if ($s['po_games'] > 0): ?>
                    <td class="text-center" style="border-left:2px solid #fef3c7;color:var(--text-3);"><?= $s['po_games'] ?></td>
                    <td class="text-center" style="font-weight:700;color:#b45309;"><?= $s['po_points'] ?></td>
                    <td class="text-center" style="color:#f59e0b;font-weight:600;"><?= $s['po_ppg'] ?: '—' ?></td>
                    <td class="text-center" style="color:#8b5cf6;font-weight:600;">
                        <?= $p3pct !== null ? $p3pct.'%' : '—' ?>
                        <?php if ($p3pct !== null): ?>
                            <div style="font-size:10px;color:var(--text-4);font-weight:400;"><?= $s['po_3pt_made'] ?>/<?= $s['po_3pt_att'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="color:#10b981;font-weight:600;">
                        <?= $pftpct !== null ? $pftpct.'%' : '—' ?>
                        <?php if ($pftpct !== null): ?>
                            <div style="font-size:10px;color:var(--text-4);font-weight:400;"><?= $s['po_ft_made'] ?>/<?= $s['po_ft_att'] ?></div>
                        <?php endif; ?>
                    </td>
                    <?php else: ?>
                    <td colspan="5" class="text-center" style="border-left:2px solid #fef3c7;color:var(--text-4);font-size:12px;font-style:italic;">
                        Did not play in playoffs
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ GAME LOG ════════════════════════════════════════════════════════════ -->
<div class="card mb-4">
    <div class="card-header">
        <h3>Game Log</h3>
        <span style="font-size:11px;color:var(--text-3);">Last 15 games · regular &amp; playoffs</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recentGames)): ?>
            <div class="card-body"><p class="text-muted">No games played yet.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:100px;">Date</th>
                        <th>Matchup</th>
                        <th class="text-center" style="width:70px;">Score</th>
                        <th class="text-center" style="width:55px;color:var(--brand);">PTS</th>
                        <th class="text-center" style="width:60px;">FG</th>
                        <th class="text-center" style="width:60px;color:#8b5cf6;">3PT</th>
                        <th class="text-center" style="width:60px;color:#10b981;">FT</th>
                        <th class="text-center" style="width:70px;">Type</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentGames as $g):
                    $won = $g['player_team_score'] > $g['opponent_score'];
                    $opp = ($g['home_team'] === $g['player_team']) ? $g['away_team'] : $g['home_team'];
                    $isPlayoff = $g['game_type'] === 'playoff';
                    $isCareerHigh = $careerHighGame && $g['total_points'] == $career['career_high'] && $career['career_high'] > 0;
                ?>
                <tr style="<?= $isPlayoff ? 'border-left:3px solid #f59e0b;' : '' ?>">
                    <td style="font-size:11px;color:var(--text-3);"><?= formatDate($g['game_date']) ?></td>
                    <td>
                        <span style="font-weight:600;"><?= clean($g['player_team']) ?></span>
                        <span style="font-size:11px;color:var(--text-3);"> vs <?= clean($opp) ?></span>
                    </td>
                    <td class="text-center">
                        <span style="font-weight:800;color:<?= $won?'#22c55e':'#ef4444' ?>;">
                            <?= $g['player_team_score'] ?>–<?= $g['opponent_score'] ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span style="font-weight:800;color:var(--brand);font-size:15px;"><?= $g['total_points'] ?></span>
                        <?php if ($isCareerHigh): ?>
                        <span style="display:block;font-size:9px;color:#f59e0b;font-weight:700;">★ HI</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="font-size:11px;color:var(--text-3);">
                        <?= ($g['two_points_made'] + $g['three_points_made']) ?>/<?= (($g['two_points_attempted'] ?? 0) + ($g['three_points_attempted'] ?? 0)) ?>
                    </td>
                    <td class="text-center" style="font-size:11px;color:#8b5cf6;">
                        <?= $g['three_points_made'] ?>/<?= $g['three_points_attempted'] ?? 0 ?>
                    </td>
                    <td class="text-center" style="font-size:11px;color:#10b981;">
                        <?= $g['free_throws_made'] ?>/<?= $g['free_throws_attempted'] ?? 0 ?>
                    </td>
                    <td class="text-center">
                        <?php if ($isPlayoff): ?>
                        <span style="font-size:10px;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:10px;font-weight:600;">
                            <?= $g['playoff_round'] ? clean($g['playoff_round']) : 'Playoff' ?>
                        </span>
                        <?php else: ?>
                        <span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:2px 6px;border-radius:10px;font-weight:600;">Regular</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <a href="<?= ADMIN_URL ?>/players/index.php?league_id=<?= $league['id'] ?>" class="btn btn-light">
        <i class="fas fa-arrow-left me-1"></i> Back to Players
    </a>
    <a href="<?= ADMIN_URL ?>/players/edit.php?id=<?= $playerId ?>&league_id=<?= $league['id'] ?>" class="btn btn-brand">
        <i class="fas fa-edit me-1"></i> Edit Player
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
