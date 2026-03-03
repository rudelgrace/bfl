<?php
require_once __DIR__ . '/../../includes/functions.php';
$teamId = intGet('id');
$leagueId = intGet('league_id');

// Get team and league
$teamStmt = getDB()->prepare('SELECT t.*, l.name as league_name FROM teams t 
    JOIN leagues l ON t.league_id = l.id WHERE t.id = ?');
$teamStmt->execute([$teamId]);
$team = $teamStmt->fetch();

if (!$team) {
    setFlash('error', 'Team not found.');
    redirect(ADMIN_URL . '/teams/index.php?league_id=' . $leagueId);
}

$league = requireLeague($team['league_id']);
$leagueContext = $league;
$activeSidebar = 'teams';
$activeNav = 'leagues';
$pageTitle = $team['name'];

require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

// Get seasons this team participated in
$seasonsStmt = $pdo->prepare('
    SELECT s.*, st.created_at as joined_date,
        (SELECT COUNT(*) FROM games WHERE season_id = s.id AND (home_team_id = ? OR away_team_id = ?) AND status = "completed") as games_played,
        (SELECT COUNT(*) FROM games g 
         JOIN standings stnd ON g.season_id = stnd.season_id AND stnd.team_id = ?
         WHERE g.season_id = s.id AND g.status = "completed" AND 
         ((g.home_team_id = ? AND g.home_score > g.away_score) OR 
          (g.away_team_id = ? AND g.away_score > g.home_score))) as wins
    FROM seasons s
    JOIN season_teams st ON s.id = st.season_id
    WHERE st.team_id = ?
    ORDER BY s.created_at DESC
');
$seasonsStmt->execute([$teamId, $teamId, $teamId, $teamId, $teamId, $teamId]);
$seasons = $seasonsStmt->fetchAll();

// Get current season roster
$currentSeasonStmt = $pdo->prepare('
    SELECT s.id FROM seasons s 
    JOIN season_teams st ON s.id = st.season_id 
    WHERE st.team_id = ? AND s.status IN ("upcoming", "active")
    ORDER BY s.created_at DESC LIMIT 1
');
$currentSeasonStmt->execute([$teamId]);
$currentSeason = $currentSeasonStmt->fetch();

$currentRoster = [];
if ($currentSeason) {
    $rosterStmt = $pdo->prepare('
        SELECT p.*, sr.jersey_number, sr.status
        FROM players p
        JOIN season_rosters sr ON p.id = sr.player_id
        WHERE sr.season_id = ? AND sr.team_id = ?
        ORDER BY sr.jersey_number, p.last_name, p.first_name
    ');
    $rosterStmt->execute([$currentSeason['id'], $teamId]);
    $currentRoster = $rosterStmt->fetchAll();
}

// Get all players who ever played for this team
$allPlayersStmt = $pdo->prepare('
    SELECT DISTINCT p.*, COUNT(DISTINCT sr.season_id) as seasons_played
    FROM players p
    JOIN season_rosters sr ON p.id = sr.player_id
    WHERE sr.team_id = ?
    GROUP BY p.id
    ORDER BY p.last_name, p.first_name
');
$allPlayersStmt->execute([$teamId]);
$allPlayers = $allPlayersStmt->fetchAll();

// Get recent games
$recentGamesStmt = $pdo->prepare('
    SELECT g.*, 
        ht.name as home_team_name, at.name as away_team_name,
        s.name as season_name
    FROM games g
    JOIN teams ht ON g.home_team_id = ht.id
    JOIN teams at ON g.away_team_id = at.id
    JOIN seasons s ON g.season_id = s.id
    WHERE (g.home_team_id = ? OR g.away_team_id = ?)
    ORDER BY g.game_date DESC, g.created_at DESC
    LIMIT 10
');
$recentGamesStmt->execute([$teamId, $teamId]);
$recentGames = $recentGamesStmt->fetchAll();

// Calculate overall stats
$totalWins = array_sum(array_column($seasons, 'wins'));
$totalGames = array_sum(array_column($seasons, 'games_played'));
$totalLosses = $totalGames - $totalWins;
$winPercentage = $totalGames > 0 ? round(($totalWins / $totalGames) * 100, 1) : 0;

// Team overall 3PT% and FT% — regular season only
$teamShootingStmt = $pdo->prepare("
    SELECT SUM(pgs.three_points_made) as total_3pt_made,
           SUM(pgs.three_points_attempted) as total_3pt_att,
           SUM(pgs.free_throws_made) as total_ft_made,
           SUM(pgs.free_throws_attempted) as total_ft_att
    FROM player_game_stats pgs
    JOIN games g ON pgs.game_id = g.id
    WHERE pgs.team_id = ? AND g.game_type = 'regular' AND g.status = 'completed'
");
$teamShootingStmt->execute([$teamId]);
$teamShooting = $teamShootingStmt->fetch();
$team3ptPct = ($teamShooting && $teamShooting['total_3pt_att'] > 0) ? round($teamShooting['total_3pt_made'] / $teamShooting['total_3pt_att'] * 100, 1) : null;
$teamFtPct  = ($teamShooting && $teamShooting['total_ft_att'] > 0)  ? round($teamShooting['total_ft_made']  / $teamShooting['total_ft_att']  * 100, 1) : null;
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $league['id'] ?>"><?= clean($league['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/teams/index.php?league_id=<?= $league['id'] ?>">Teams</a>
        </li>
        <li class="breadcrumb-item active"><?= clean($team['name']) ?></li>
    </ol>
</nav>

<!-- Team Header -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center gap-4">
            <div style="width: 80px; height: 80px; border-radius: var(--r); overflow: hidden; flex-shrink: 0;">
                <?php if (!empty($team['logo'])): ?>
                    <img src="<?= UPLOADS_URL . '/' . $team['logo'] ?>" 
                         style="width: 100%; height: 100%; object-fit: cover;" alt="<?= clean($team['name']) ?>">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; background: var(--surface-2); display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; color: var(--text-3);">
                        <?= strtoupper(substr($team['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1">
                <h1 style="font-size: 24px; font-weight: 800; margin: 0 0 8px 0;"><?= clean($team['name']) ?></h1>
                <p style="color: var(--text-3); margin: 0; font-size: 14px;">
                    <i class="fas fa-trophy me-1"></i><?= clean($team['league_name']) ?>
                </p>
            </div>
            <div class="text-end" style="flex-shrink: 0;">
                <div style="font-size: 32px; font-weight: 800; color: var(--brand);"><?= $winPercentage ?>%</div>
                <div style="font-size: 12px; color: var(--text-3); font-weight: 600;">WIN RATE</div>
                <div style="font-size: 14px; margin-top: 4px;">
                    <span style="color: #22c55e; font-weight: 600;"><?= $totalWins ?>W</span>
                    <span style="color: var(--text-3);">-</span>
                    <span style="color: #ef4444; font-weight: 600;"><?= $totalLosses ?>L</span>
                    <span style="color: var(--text-3);">(<?= $totalGames ?> games)</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Overview -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= count($seasons) ?></div>
            <div class="stat-label">Seasons</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= count($allPlayers) ?></div>
            <div class="stat-label">Total Players</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= $totalWins ?></div>
            <div class="stat-label">Total Wins</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?= count($currentRoster) ?></div>
            <div class="stat-label">Current Roster</div>
        </div>
    </div>
</div>

<!-- Team Shooting Percentages -->
<?php if ($team3ptPct !== null || $teamFtPct !== null): ?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card" style="border-left:3px solid #8b5cf6;">
            <div class="card-body py-3">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);font-weight:700;margin-bottom:6px;">
                    <i class="fas fa-circle-dot me-1" style="color:#8b5cf6;"></i> Team 3PT%
                </div>
                <div style="display:flex;align-items:baseline;gap:10px;">
                    <div style="font-size:32px;font-weight:900;color:#8b5cf6;line-height:1;"><?= $team3ptPct ?? '—' ?><?= $team3ptPct !== null ? '%' : '' ?></div>
                    <?php if ($teamShooting && $teamShooting['total_3pt_att'] > 0): ?>
                    <div style="font-size:12px;color:var(--text-3);"><?= (int)$teamShooting['total_3pt_made'] ?>/<?= (int)$teamShooting['total_3pt_att'] ?> made</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card" style="border-left:3px solid #10b981;">
            <div class="card-body py-3">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);font-weight:700;margin-bottom:6px;">
                    <i class="fas fa-hand-point-up me-1" style="color:#10b981;"></i> Team FT%
                </div>
                <div style="display:flex;align-items:baseline;gap:10px;">
                    <div style="font-size:32px;font-weight:900;color:#10b981;line-height:1;"><?= $teamFtPct ?? '—' ?><?= $teamFtPct !== null ? '%' : '' ?></div>
                    <?php if ($teamShooting && $teamShooting['total_ft_att'] > 0): ?>
                    <div style="font-size:12px;color:var(--text-3);"><?= (int)$teamShooting['total_ft_made'] ?>/<?= (int)$teamShooting['total_ft_att'] ?> made</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Current Roster -->
<?php if (!empty($currentRoster)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h3>Current Roster (<?= clean($currentSeason['name'] ?? 'Current Season') ?>)</h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($currentRoster as $player): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="d-flex align-items-center gap-3 p-3 border rounded-3">
                        <div style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; flex-shrink: 0;">
                            <?php if (!empty($player['photo'])): ?>
                                <img src="<?= UPLOADS_URL . '/' . $player['photo'] ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;" alt="">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: var(--surface-2); display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; color: var(--text-3);">
                                    <?= strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div style="font-weight: 600; font-size: 14px;">
                                <?= clean($player['first_name'] . ' ' . $player['last_name']) ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-3);">
                                #<?= $player['jersey_number'] ?> · <?= clean($player['position'] ?: '—') ?>
                                <?php if ($player['height']): ?>· <?= clean($player['height']) ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Seasons History -->
<div class="card mb-4">
    <div class="card-header">
        <h3>Seasons History</h3>
    </div>
    <div class="card-body">
        <?php if (empty($seasons)): ?>
            <p class="text-muted">No seasons participated yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Season</th>
                            <th>Status</th>
                            <th>Games</th>
                            <th>Wins</th>
                            <th>Losses</th>
                            <th>Win %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seasons as $season): ?>
                            <tr>
                                <td>
                                    <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $season['id'] ?>&league_id=<?= $league['id'] ?>" 
                                       style="font-weight: 600; color: var(--brand);">
                                        <?= clean($season['name']) ?>
                                    </a>
                                </td>
                                <td><?= seasonStatusBadge($season['status']) ?></td>
                                <td><?= $season['games_played'] ?></td>
                                <td><?= $season['wins'] ?></td>
                                <td><?= $season['games_played'] - $season['wins'] ?></td>
                                <td>
                                    <?php 
                                    $seasonWinPct = $season['games_played'] > 0 ? 
                                        round(($season['wins'] / $season['games_played']) * 100, 1) : 0;
                                    echo $seasonWinPct . '%';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Games -->
<div class="card mb-4">
    <div class="card-header">
        <h3>Recent Games</h3>
    </div>
    <div class="card-body">
        <?php if (empty($recentGames)): ?>
            <p class="text-muted">No games played yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Season</th>
                            <th>Matchup</th>
                            <th class="text-center">Score</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentGames as $game): ?>
                            <tr>
                                <td><?= formatDate($game['game_date']) ?></td>
                                <td style="font-size: 12px; color: var(--text-3);"><?= clean($game['season_name']) ?></td>
                                <td>
                                    <?= $game['home_team_id'] == $teamId ? 
                                        '<strong>' . clean($game['home_team_name']) . '</strong> vs ' . clean($game['away_team_name']) :
                                        clean($game['home_team_name']) . ' vs <strong>' . clean($game['away_team_name']) . '</strong>' 
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($game['status'] === 'completed'): ?>
                                        <span style="font-weight: 800; font-variant-numeric: tabular-nums;">
                                            <?= $game['home_score'] ?> <span style="color: var(--text-4);">—</span> <?= $game['away_score'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-3); font-size: 12px;">Scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($game['status'] === 'completed'): ?>
                                        <?php 
                                        $isWin = ($game['home_team_id'] == $teamId && $game['home_score'] > $game['away_score']) ||
                                                ($game['away_team_id'] == $teamId && $game['away_score'] > $game['home_score']);
                                        ?>
                                        <?php if ($isWin): ?>
                                            <span class="badge-active">W</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #ef4444; color: white;">L</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-3); font-size: 12px;">—</span>
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

<!-- All Players -->
<div class="card">
    <div class="card-header">
        <h3>All Players (<?= count($allPlayers) ?> total)</h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($allPlayers as $player): ?>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= ADMIN_URL ?>/players/view.php?id=<?= $player['id'] ?>&league_id=<?= $league['id'] ?>" style="text-decoration:none;color:inherit;display:block;">
                    <div class="d-flex align-items-center gap-3 p-3 border rounded-3" style="transition:background .15s;cursor:pointer;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
                        <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; flex-shrink: 0;">
                            <?php if (!empty($player['photo'])): ?>
                                <img src="<?= UPLOADS_URL . '/' . $player['photo'] ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;" alt="">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: var(--surface-2); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: var(--text-3);">
                                    <?= strtoupper(substr($player['first_name'], 0, 1) . substr($player['last_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div style="font-weight: 600; font-size: 13px; color:var(--brand);">
                                <?= clean($player['first_name'] . ' ' . $player['last_name']) ?>
                            </div>
                            <div style="font-size: 11px; color: var(--text-3);">
                                <?= $player['seasons_played'] ?> season<?= $player['seasons_played'] != 1 ? 's' : '' ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:var(--text-4);font-size:11px;flex-shrink:0;"></i>
                    </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="<?= ADMIN_URL ?>/teams/index.php?league_id=<?= $league['id'] ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Teams
    </a>
    <a href="<?= ADMIN_URL ?>/teams/edit.php?id=<?= $teamId ?>&league_id=<?= $league['id'] ?>" class="btn btn-brand">
        <i class="fas fa-edit me-1"></i> Edit Team
    </a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
