<?php
require_once __DIR__ . '/../../includes/functions.php';
if (isScorer()) { redirect(ADMIN_URL . '/scorer/index.php'); }
$seasonId = intGet('id');
$leagueId = intGet('league_id');
$season   = requireSeason($seasonId);
$league   = requireLeague($leagueId ?: $season['league_id']);
$leagueContext = $league;
$activeSidebar = 'seasons';
$activeNav     = 'leagues';
$pageTitle     = $season['name'];

require_once __DIR__ . '/../../includes/header.php';
$pdo = getDB();

// Pagination settings
$itemsPerPage = 12;
$currentSchedulePage = max(1, intGet('schedule_page', 1));
$currentStandingsPage = max(1, intGet('standings_page', 1));
$currentRankingsPage = max(1, intGet('rankings_page', 1));
$currentRecentPage = max(1, intGet('recent_page', 1));

// Calculate offsets
$scheduleOffset = ($currentSchedulePage - 1) * $itemsPerPage;
$standingsOffset = ($currentStandingsPage - 1) * $itemsPerPage;
$rankingsOffset = ($currentRankingsPage - 1) * $itemsPerPage;
$recentOffset = ($currentRecentPage - 1) * $itemsPerPage;

$isCompleted = $season['status'] === 'completed';
$isLocked    = in_array($season['status'], ['playoffs', 'completed']);

$teams = $pdo->prepare("SELECT t.*, COUNT(sr.player_id) as roster_size
    FROM teams t JOIN season_teams st ON t.id=st.team_id AND st.season_id=?
    LEFT JOIN season_rosters sr ON sr.team_id=t.id AND sr.season_id=?
    WHERE t.league_id=? GROUP BY t.id ORDER BY t.name");
$teams->execute([$seasonId,$seasonId,$league['id']]); $teams=$teams->fetchAll();

// Fetch scheduled games only (for Schedule tab)
$scheduledGamesQuery = "SELECT g.*,ht.name as home_team,at.name as away_team
    FROM games g JOIN teams ht ON g.home_team_id=ht.id JOIN teams at ON g.away_team_id=at.id
    WHERE g.season_id=? AND g.game_type='regular' AND g.status='scheduled' ORDER BY g.game_date,g.id";
$scheduledGames = $pdo->prepare($scheduledGamesQuery);
$scheduledGames->execute([$seasonId]); 
$scheduledGames = $scheduledGames->fetchAll();
$totalScheduledGames = count($scheduledGames);
$scheduledGamesPage = array_slice($scheduledGames, $scheduleOffset, $itemsPerPage);

// Fetch completed games only (for Recent Games tab)
$completedGamesQuery = "SELECT g.*,ht.name as home_team,at.name as away_team,
       CASE WHEN g.home_score > g.away_score THEN ht.name WHEN g.away_score > g.home_score THEN at.name ELSE 'Tie' END AS winner,
       ABS(g.home_score - g.away_score) AS point_diff
    FROM games g JOIN teams ht ON g.home_team_id=ht.id JOIN teams at ON g.away_team_id=at.id
    WHERE g.season_id=? AND g.game_type='regular' AND g.status='completed' ORDER BY g.updated_at DESC";
$completedGames = $pdo->prepare($completedGamesQuery);
$completedGames->execute([$seasonId]); 
$completedGames = $completedGames->fetchAll();
$totalCompletedGames = count($completedGames);
$completedGamesPage = array_slice($completedGames, $recentOffset, $itemsPerPage);

// Keep original for compatibility
$regularGames=$pdo->prepare("SELECT g.*,ht.name as home_team,at.name as away_team
    FROM games g JOIN teams ht ON g.home_team_id=ht.id JOIN teams at ON g.away_team_id=at.id
    WHERE g.season_id=? AND g.game_type='regular' ORDER BY g.game_date,g.id");
$regularGames->execute([$seasonId]); $regularGames=$regularGames->fetchAll();

$standingsAll=getStandings($seasonId);
$totalStandings = count($standingsAll);
$standingsPage = array_slice($standingsAll, $standingsOffset, $itemsPerPage);

// Team shooting percentages for Standings tab — regular season only
$teamShootingStmt = $pdo->prepare("
    SELECT pgs.team_id,
           SUM(pgs.three_points_made) as total_3pt_made,
           SUM(pgs.three_points_attempted) as total_3pt_att,
           SUM(pgs.free_throws_made) as total_ft_made,
           SUM(pgs.free_throws_attempted) as total_ft_att
    FROM player_game_stats pgs
    JOIN games g ON pgs.game_id = g.id
    WHERE g.season_id = ? AND g.game_type = 'regular' AND g.status = 'completed'
    GROUP BY pgs.team_id
");
$teamShootingStmt->execute([$seasonId]);
$teamShootingMap = [];
foreach ($teamShootingStmt->fetchAll() as $row) {
    $teamShootingMap[$row['team_id']] = $row;
}

$playoffGames=$pdo->prepare("SELECT g.*,ht.name as home_team,at.name as away_team
    FROM games g JOIN teams ht ON g.home_team_id=ht.id JOIN teams at ON g.away_team_id=at.id
    WHERE g.season_id=? AND g.game_type='playoff' ORDER BY g.playoff_position");
$playoffGames->execute([$seasonId]); $playoffGames=$playoffGames->fetchAll();

// Playoff player rankings (only shown when playoffs have completed games)
$playoffRankings = [];
$hasPlayoffStats = false;
if (!empty($playoffGames)) {
    $playoffRankings = getSeasonPlayoffRankings($seasonId);
    $hasPlayoffStats = !empty($playoffRankings);
}

$seasonRankingsAll=$pdo->prepare("
    SELECT p.id,p.first_name,p.last_name,t.name as team_name,t.id as team_id,
           SUM(pgs.total_points) as total_pts,
           SUM(pgs.two_points_made) as total_2pt,
           SUM(pgs.three_points_made) as total_3pt,
           SUM(pgs.three_points_attempted) as total_3pt_att,
           SUM(pgs.free_throws_made) as total_ft,
           SUM(pgs.free_throws_attempted) as total_ft_att,
           COUNT(DISTINCT pgs.game_id) as games,
           ROUND(SUM(pgs.total_points)/COUNT(DISTINCT pgs.game_id),1) as ppg
    FROM player_game_stats pgs
    JOIN players p ON pgs.player_id=p.id
    JOIN games g ON pgs.game_id=g.id
    JOIN teams t ON pgs.team_id=t.id
    WHERE g.season_id=? AND g.game_type='regular' AND g.status='completed'
    GROUP BY p.id,t.id HAVING games>0 ORDER BY total_pts DESC,ppg DESC");
$seasonRankingsAll->execute([$seasonId]); 
$seasonRankingsAll=$seasonRankingsAll->fetchAll();
$totalRankings = count($seasonRankingsAll);
$seasonRankingsPage = array_slice($seasonRankingsAll, $rankingsOffset, $itemsPerPage);

// Keep original for compatibility
$seasonRankings=$seasonRankingsAll;

$regularMvp=$playoffMvp=null;
if($season['regular_season_mvp_id']){
    $s=$pdo->prepare('SELECT p.*,t.name as team_name FROM players p LEFT JOIN season_rosters sr ON p.id=sr.player_id AND sr.season_id=? LEFT JOIN teams t ON sr.team_id=t.id WHERE p.id=?');
    $s->execute([$seasonId,$season['regular_season_mvp_id']]); $regularMvp=$s->fetch();
}
if($season['playoffs_mvp_id']){
    $s=$pdo->prepare('SELECT p.*,t.name as team_name FROM players p LEFT JOIN season_rosters sr ON p.id=sr.player_id AND sr.season_id=? LEFT JOIN teams t ON sr.team_id=t.id WHERE p.id=?');
    $s->execute([$seasonId,$season['playoffs_mvp_id']]); $playoffMvp=$s->fetch();
}

// Build a lookup map of playoff games by their game ID (used for bracket source resolution)
$playoffGameById = [];
foreach ($playoffGames as $pg) {
    $playoffGameById[(int)$pg['id']] = $pg;
}

$hasSchedule=!empty($scheduledGames); $hasPlayoffs=!empty($playoffGames);
$gamesCompleted=count(array_filter($regularGames,fn($g)=>$g['status']==='completed'));
$totalGames=count($regularGames);
$regularComplete=$totalGames>0&&$gamesCompleted===$totalGames;
$progressPct=$totalGames>0?round(($gamesCompleted/$totalGames)*100):0;
$teamsWithRosters=count(array_filter($teams,fn($t)=>$t['roster_size']>=3));
$canStart=!empty($regularGames)&&$teamsWithRosters===count($teams)&&count($teams)>0;
$seasonBest3PTPct=getSeasonBest3PTPct($seasonId);
$seasonBestFTPct=getSeasonBestFTPct($seasonId);

// Pagination helper function
function renderSeasonPagination(string $tab, int $currentPage, int $totalItems, int $perPage = 12, int $seasonId = 0, int $leagueId = 0): string {
    $totalPages = (int) ceil($totalItems / $perPage);
    if ($totalPages <= 1) return '';

    // Base URL: preserves season context + active tab so paginating never loses your place
    $base   = '?id=' . $seasonId . '&league_id=' . $leagueId . '&tab=' . $tab . '&';
    $anchor = '#tab-' . $tab;

    $html = '<div style="display:flex;align-items:center;gap:8px;justify-content:center;margin-top:20px;padding:16px;border-top:1px solid var(--border);">';

    if ($currentPage > 1) {
        $html .= '<a href="' . $base . $tab . '_page=' . ($currentPage - 1) . $anchor . '" class="pagination-btn">← Previous</a>';
    }

    $startPage = max(1, $currentPage - 2);
    $endPage   = min($totalPages, $currentPage + 2);

    if ($startPage > 1) {
        $html .= '<a href="' . $base . $tab . '_page=1' . $anchor . '" class="pagination-btn">1</a>';
        if ($startPage > 2) $html .= '<span style="color:var(--text-4);padding:0 4px;">...</span>';
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
        $cls  = $i === $currentPage ? 'pagination-btn active' : 'pagination-btn';
        $html .= '<a href="' . $base . $tab . '_page=' . $i . $anchor . '" class="' . $cls . '">' . $i . '</a>';
    }

    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) $html .= '<span style="color:var(--text-4);padding:0 4px;">...</span>';
        $html .= '<a href="' . $base . $tab . '_page=' . $totalPages . $anchor . '" class="pagination-btn">' . $totalPages . '</a>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $base . $tab . '_page=' . ($currentPage + 1) . $anchor . '" class="pagination-btn">Next →</a>';
    }

    $html .= '</div>';
    return $html;
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $league['id'] ?>"><?= clean($league['name']) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/seasons/index.php?league_id=<?= $league['id'] ?>">Seasons</a></li>
        <li class="breadcrumb-item active"><?= clean($season['name']) ?></li>
    </ol>
</nav>

<!-- SEASON HEADER -->
<div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:24px 28px;margin-bottom:24px;box-shadow:var(--shadow);">
    <div class="d-flex align-items-start justify-content-between gap-3">
        <div style="flex:1;min-width:0;">
            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                <h1 style="font-size:22px;font-weight:800;letter-spacing:-.025em;margin:0;"><?= clean($season['name']) ?></h1>
                <?= seasonStatusBadge($season['status']) ?>
                <?php if($isCompleted): ?>
                <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-weight:600;"><i class="fas fa-lock fa-xs me-1"></i>View Only</span>
                <?php endif; ?>
            </div>
            <?php if($season['start_date']): ?>
            <p style="color:var(--text-3);font-size:13px;margin:0 0 12px;"><?= formatDate($season['start_date']) ?><?php if($season['end_date']): ?> → <?= formatDate($season['end_date']) ?><?php endif; ?></p>
            <?php endif; ?>
            <?php if(!$isCompleted&&$totalGames>0): ?>
            <div style="max-width:440px;">
                <div class="d-flex justify-content-between mb-1" style="font-size:11px;color:var(--text-3);font-weight:600;">
                    <span><?= $season['status']==='playoffs'?'Playoffs in Progress':($progressPct>=100?'Regular Season Complete':'Season Progress') ?></span>
                    <span><?= $progressPct ?>% (<?= $gamesCompleted ?>/<?= $totalGames ?>)</span>
                </div>
                <div class="tb-progress">
                    <div class="tb-progress-bar <?= $progressPct>=100?'progress-complete':'' ?>" style="width:<?= $progressPct ?>%;<?= $progressPct>=100?'background:linear-gradient(90deg,#22c55e,#16a34a);':'' ?>"></div>
                </div>
            </div>
            <?php elseif($isCompleted): ?>
            <div style="max-width:440px;">
                <div class="d-flex justify-content-between mb-1" style="font-size:11px;font-weight:600;color:#16a34a;">
                    <span><i class="fas fa-circle-check me-1"></i>Season Complete</span><span>100%</span>
                </div>
                <div class="tb-progress"><div class="tb-progress-bar progress-complete" style="width:100%;"></div></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 flex-shrink-0 flex-wrap justify-content-end">
            <?php if(!$isLocked): ?>
            <a href="<?= ADMIN_URL ?>/seasons/settings.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-light btn-sm"><i class="fas fa-cog fa-xs me-1"></i>Settings</a>
            <?php endif; ?>
            <?php if($season['status']==='upcoming'): ?>
            <a href="<?= ADMIN_URL ?>/seasons/edit.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-light btn-sm"><i class="fas fa-edit fa-xs me-1"></i>Edit</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- AWARDS BANNER (playoffs or completed) -->
<?php 
$showAwards = ($season['status'] === 'playoffs' || $isCompleted) && ($regularMvp || $playoffMvp);
if($showAwards): ?>
<div style="background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);border-radius:var(--r-lg);padding:22px 28px;margin-bottom:24px;color:#fff;display:flex;gap:32px;flex-wrap:wrap;align-items:center;">
    <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.07em;flex-shrink:0;">Season Awards</div>
    <?php if($regularMvp): ?>
    <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;border-radius:50%;background:var(--brand);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-star" style="color:#fff;font-size:18px;"></i></div>
        <div>
            <div style="font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Regular Season MVP</div>
            <div style="font-size:16px;font-weight:800;"><?= clean($regularMvp['first_name'].' '.$regularMvp['last_name']) ?></div>
            <?php if(!empty($regularMvp['team_name'])): ?><div style="font-size:11px;color:rgba(255,255,255,.5);"><?= clean($regularMvp['team_name']) ?></div><?php endif; ?>
        </div>
    </div>
    <?php elseif($season['status'] === 'playoffs'): ?>
    <div style="display:flex;align-items:center;gap:12px;opacity:.5;">
        <div style="width:44px;height:44px;border-radius:50%;background:#475569;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-star" style="color:#fff;font-size:18px;"></i></div>
        <div>
            <div style="font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Regular Season MVP</div>
            <div style="font-size:13px;color:rgba(255,255,255,.5);">No scoring data recorded</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if($playoffMvp): ?>
    <div style="width:1px;height:48px;background:rgba(255,255,255,.1);flex-shrink:0;" class="d-none d-md-block"></div>
    <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:44px;height:44px;border-radius:50%;background:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-trophy" style="color:#fff;font-size:18px;"></i></div>
        <div>
            <div style="font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em;font-weight:700;">Playoffs MVP 🏆</div>
            <div style="font-size:16px;font-weight:800;"><?= clean($playoffMvp['first_name'].' '.$playoffMvp['last_name']) ?></div>
            <?php if(!empty($playoffMvp['team_name'])): ?><div style="font-size:11px;color:rgba(255,255,255,.5);"><?= clean($playoffMvp['team_name']) ?></div><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- SETUP WIZARD -->
<?php if($season['status']==='upcoming'): ?>
<div class="card mb-4">
    <div class="card-header"><h2>Season Setup</h2></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="setup-step <?= $teamsWithRosters===count($teams)&&count($teams)>0?'done':'' ?>">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="setup-num <?= $teamsWithRosters===count($teams)&&count($teams)>0?'done':'pending' ?>"><?= $teamsWithRosters===count($teams)&&count($teams)>0?'<i class="fas fa-check" style="font-size:11px;"></i>':'1' ?></div>
                        <span style="font-size:13px;font-weight:700;">Assign Rosters</span>
                    </div>
                    <div style="font-size:12px;color:var(--text-3);margin-bottom:10px;"><?= $teamsWithRosters ?>/<?= count($teams) ?> teams ready (min 3)</div>
                    <?php foreach($teams as $t): ?>
                    <div class="d-flex align-items-center justify-content-between py-1">
                        <div class="d-flex align-items-center gap-2">
                            <span style="width:7px;height:7px;border-radius:50%;background:<?= $t['roster_size']>=3?'#22c55e':'#e2e8f0' ?>;display:inline-block;flex-shrink:0;"></span>
                            <span style="font-size:12px;font-weight:500;"><?= clean($t['name']) ?></span>
                        </div>
                        <a href="<?= ADMIN_URL ?>/seasons/roster.php?season_id=<?= $seasonId ?>&team_id=<?= $t['id'] ?>&league_id=<?= $league['id'] ?>" style="font-size:11px;color:var(--brand);font-weight:600;"><?= $t['roster_size'] ?> players · Manage</a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="setup-step <?= !empty($regularGames)?'done':'' ?>">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="setup-num <?= !empty($regularGames)?'done':'pending' ?>"><?= !empty($regularGames)?'<i class="fas fa-check" style="font-size:11px;"></i>':'2' ?></div>
                        <span style="font-size:13px;font-weight:700;">Generate Schedule</span>
                    </div>
                    <?php if(!empty($regularGames)): ?>
                    <p style="font-size:12px;color:#16a34a;margin-bottom:10px;font-weight:600;"><i class="fas fa-check me-1"></i><?= count($regularGames) ?> games</p>
                    <a href="<?= ADMIN_URL ?>/seasons/generate_schedule.php?season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-light btn-sm" onclick="return confirm('Regenerate? This will delete existing games.')"><i class="fas fa-rotate"></i> Regenerate</a>
                    <?php else: ?>
                    <p style="font-size:12px;color:var(--text-3);margin-bottom:10px;">Auto-generate round-robin</p>
                    <a href="<?= ADMIN_URL ?>/seasons/generate_schedule.php?season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-brand btn-sm"><i class="fas fa-bolt"></i> Generate</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="setup-step <?= $canStart?'active-step':'' ?>">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="setup-num <?= $canStart?'ready':'pending' ?>">3</div>
                        <span style="font-size:13px;font-weight:700;">Start League</span>
                    </div>
                    <?php if($canStart): ?>
                    <p style="font-size:12px;color:var(--text-2);margin-bottom:12px;">Ready to go. Launch now.</p>
                    <form method="POST" action="<?= ADMIN_URL ?>/seasons/start.php" style="margin:0;">
                        <input type="hidden" name="season_id" value="<?= $seasonId ?>">
                        <input type="hidden" name="league_id" value="<?= $league['id'] ?>">
                        <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Start season? Cannot be undone.')"><i class="fas fa-play fa-xs me-1"></i> Start Season</button>
                    </form>
                    <?php else: ?>
                    <p style="font-size:12px;color:var(--text-3);">Complete steps 1 & 2 first</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- QUICK STATS (not upcoming) -->
<?php if($season['status']!=='upcoming'): ?>
<div class="row g-3 mb-4">
    <?php if(!$isCompleted): ?>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#fff7ed;color:#c2410c;"><i class="fas fa-basketball"></i></div>
            <div class="stat-value"><?= $gamesCompleted ?></div>
            <div class="stat-label">Games Played</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?= $totalGames-$gamesCompleted ?></div>
            <div class="stat-label">Remaining</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
    <?php else: ?>
    <div class="col-6 col-md-6">
    <?php endif; ?>
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#ede9fe;color:#6d28d9;"><i class="fas fa-shield-halved"></i></div>
            <div class="stat-value"><?= count($teams) ?></div>
            <div class="stat-label">Teams</div>
        </div>
    </div>
    <?php if($isCompleted): ?><div class="col-6 col-md-6"><?php else: ?><div class="col-6 col-md-3"><?php endif; ?>
        <div class="stat-card text-center">
            <div class="stat-icon mx-auto mb-2" style="background:#d1fae5;color:#065f46;"><i class="fas fa-person-running"></i></div>
            <div class="stat-value"><?= count($seasonRankings) ?></div>
            <div class="stat-label">Players</div>
        </div>
    </div>
</div>

<?php if(!empty($seasonRankings)): ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card" style="border-left:3px solid var(--brand);">
            <div class="card-body py-3">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);font-weight:700;margin-bottom:10px;">Leading Scorer</div>
                <?php $p=$seasonRankings[0]; ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="player-avatar" style="width:44px;height:44px;font-size:16px;"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:14px;"><?= clean($p['first_name'].' '.$p['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-3);"><?= clean($p['team_name']) ?> · <?= $p['games'] ?> games</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:26px;font-weight:900;color:var(--brand);line-height:1;"><?= $p['ppg'] ?></div>
                        <div style="font-size:9px;color:var(--text-3);text-transform:uppercase;">PPG</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="border-left:3px solid #8b5cf6;">
            <div class="card-body py-3">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);font-weight:700;margin-bottom:10px;">Best 3PT%</div>
                <?php if($seasonBest3PTPct): $p=$seasonBest3PTPct; ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="player-avatar" style="width:44px;height:44px;font-size:16px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:14px;"><?= clean($p['first_name'].' '.$p['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-3);"><?= $p['made'] ?>/<?= $p['attempted'] ?> made</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:26px;font-weight:900;color:#8b5cf6;line-height:1;"><?= $p['pct'] ?>%</div>
                        <div style="font-size:9px;color:var(--text-3);text-transform:uppercase;">3PT%</div>
                    </div>
                </div>
                <?php else: ?><p style="font-size:12px;color:var(--text-3);margin:0;">Min. 3 attempts required</p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="border-left:3px solid #10b981;">
            <div class="card-body py-3">
                <div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:var(--text-3);font-weight:700;margin-bottom:10px;">Best FT%</div>
                <?php if($seasonBestFTPct): $p=$seasonBestFTPct; ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="player-avatar" style="width:44px;height:44px;font-size:16px;background:linear-gradient(135deg,#10b981,#065f46);"><?= strtoupper(substr($p['first_name'],0,1).substr($p['last_name'],0,1)) ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:14px;"><?= clean($p['first_name'].' '.$p['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-3);"><?= $p['made'] ?>/<?= $p['attempted'] ?> made</div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:26px;font-weight:900;color:#10b981;line-height:1;"><?= $p['pct'] ?>%</div>
                        <div style="font-size:9px;color:var(--text-3);text-transform:uppercase;">FT%</div>
                    </div>
                </div>
                <?php else: ?><p style="font-size:12px;color:var(--text-3);margin:0;">Min. 3 attempts required</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php
// Determine which tab to show — priority: URL ?tab= param, then pagination context, then hash
$activeTab = get('tab', '');
// If paginating a specific section, default that section's tab
if (!$activeTab) {
    if (intGet('schedule_page',0) > 0)   $activeTab = 'schedule';
    elseif (intGet('recent_page',0) > 0)  $activeTab = 'recent';
    elseif (intGet('standings_page',0) > 0) $activeTab = 'standings';
    elseif (intGet('rankings_page',0) > 0)  $activeTab = 'rankings';
    else $activeTab = 'schedule';
}
$validTabs = ['schedule','recent','standings','rankings','playoffs','roster'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'schedule';

// Guard: if the requested tab has no data / doesn't exist in the DOM, fall back to schedule
if ($activeTab === 'recent' && $totalCompletedGames === 0) $activeTab = 'schedule';
if ($activeTab === 'rankings' && empty($seasonRankings)) $activeTab = 'schedule';
if ($activeTab === 'playoffs' && !$hasPlayoffs) $activeTab = 'schedule';

// Helper: return 'active show' if this is the active tab
function isActiveTab(string $tab, string $activeTab): string {
    return $tab === $activeTab ? 'active' : '';
}
function isActivePaneClass(string $tab, string $activeTab): string {
    return $tab === $activeTab ? 'show active' : '';
}
?>

<!-- TABS -->
<ul class="nav nav-tabs mb-4" id="seasonTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= isActiveTab('schedule',$activeTab) ?>" id="tab-schedule-btn"
           data-bs-toggle="tab" href="#tab-schedule" role="tab">
            <i class="fas fa-calendar fa-sm"></i> Schedule
            <?php if($totalScheduledGames>0): ?><span class="tab-badge"><?= $totalScheduledGames ?></span><?php endif; ?>
        </a>
    </li>
    <?php if($totalCompletedGames > 0): ?>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= isActiveTab('recent',$activeTab) ?>" id="tab-recent-btn"
           data-bs-toggle="tab" href="#tab-recent" role="tab">
            <i class="fas fa-history fa-sm"></i> Recent Games
            <span class="tab-badge"><?= $totalCompletedGames ?></span>
        </a>
    </li>
    <?php endif; ?>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= isActiveTab('standings',$activeTab) ?>" id="tab-standings-btn"
           data-bs-toggle="tab" href="#tab-standings" role="tab">
            <i class="fas fa-list-ol fa-sm"></i> Standings
        </a>
    </li>
    <?php if(!empty($seasonRankingsAll)): ?>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= isActiveTab('rankings',$activeTab) ?>" id="tab-rankings-btn"
           data-bs-toggle="tab" href="#tab-rankings" role="tab">
            <i class="fas fa-ranking-star fa-sm"></i> Rankings
            <span style="font-size:9px;background:#dbeafe;color:#1d4ed8;padding:1px 5px;border-radius:8px;font-weight:700;margin-left:2px;">RS</span>
        </a>
    </li>
    <?php endif; ?>
    <?php if($hasPlayoffs): ?>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= isActiveTab('playoffs',$activeTab) ?>" id="tab-playoffs-btn"
           data-bs-toggle="tab" href="#tab-playoffs" role="tab">
            <i class="fas fa-trophy fa-sm"></i> Playoffs
            <?php $pDone=count(array_filter($playoffGames,fn($g)=>$g['status']==='completed')); $pTotal=count($playoffGames); if($pTotal>0): ?><span class="tab-badge"><?= $pDone ?>/<?= $pTotal ?></span><?php endif; ?>
        </a>
    </li>
    <?php endif; ?>
    <li class="nav-item" role="presentation">
        <a class="nav-link <?= isActiveTab('roster',$activeTab) ?>" id="tab-roster-btn"
           data-bs-toggle="tab" href="#tab-roster" role="tab">
            <i class="fas fa-users fa-sm"></i> Rosters
        </a>
    </li>
</ul>

<div class="tab-content" id="seasonTabContent">

<!-- SCHEDULE -->
<div class="tab-pane fade <?= isActivePaneClass('schedule',$activeTab) ?>" id="tab-schedule" role="tabpanel">
<?php if(empty($scheduledGames)): ?>
<div class="card"><div class="card-body empty-state"><div class="empty-state-icon"><i class="fas fa-calendar-xmark"></i></div><h5>No scheduled games</h5><p>All games have been completed or no schedule has been generated</p></div></div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2>Scheduled Games · <?= $totalScheduledGames ?> remaining</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th style="width:120px;">Date</th><th>Home</th><th class="text-center" style="width:100px;">Score</th><th>Away</th><th style="width:80px;">Status</th>
                <?php if(!$isCompleted): ?><th class="text-end" style="width:120px;">Action</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach($scheduledGamesPage as $g): ?>
            <tr>
                <td>
                    <?php if(!$isCompleted): ?>
                    <form method="POST" action="<?= ADMIN_URL ?>/games/update_date.php" style="margin:0;">
                        <input type="hidden" name="game_id" value="<?= $g['id'] ?>">
                        <input type="hidden" name="season_id" value="<?= $seasonId ?>">
                        <input type="hidden" name="league_id" value="<?= $league['id'] ?>">
                        <input type="date" name="game_date" value="<?= $g['game_date']?:date('Y-m-d') ?>"
                               style="border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface-2);font-size:12px;color:var(--text-2);cursor:pointer;padding:3px 6px;width:130px;font-family:inherit;"
                               onchange="this.form.submit()" title="Change date">
                    </form>
                    <?php else: ?><span style="font-size:12px;color:var(--text-3);"><?= formatDate($g['game_date']) ?></span><?php endif; ?>
                </td>
                <td style="font-weight:500;"><?= clean($g['home_team']) ?></td>
                <td class="text-center">
                    <span style="color:var(--text-4);font-size:12px;">vs</span>
                </td>
                <td style="font-weight:500;"><?= clean($g['away_team']) ?></td>
                <td><span class="badge-upcoming">Scheduled</span></td>
                <?php if(!$isCompleted): ?>
                <td class="text-end">
                    <?php if($season['status']==='active'): ?>
                    <a href="<?= ADMIN_URL ?>/games/enter_results.php?id=<?= $g['id'] ?>&season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>&return_tab=schedule" class="btn btn-brand btn-sm"><i class="fas fa-plus fa-xs"></i> Enter</a>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?= renderSeasonPagination('schedule', $currentSchedulePage, $totalScheduledGames, $itemsPerPage, $seasonId, $league['id']) ?>
</div>

<!-- RECENT GAMES -->
<div class="tab-pane fade <?= isActivePaneClass('recent',$activeTab) ?>" id="tab-recent" role="tabpanel">
<?php if(empty($completedGames)): ?>
<div class="card"><div class="card-body empty-state"><div class="empty-state-icon"><i class="fas fa-history"></i></div><h5>No completed games</h5><p>Games will appear here once they're completed</p></div></div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2>Recent Games · <?= $totalCompletedGames ?> completed</h2>
        <span style="font-size:11px;color:var(--text-3);">Most recent completed games first</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr>
                <th style="width:120px;">Date</th><th>Home</th><th class="text-center" style="width:100px;">Score</th><th>Away</th>
                <th class="text-end" style="width:120px;">Action</th>
            </tr></thead>
            <tbody>
            <?php foreach($completedGamesPage as $g): ?>
            <tr>
                <td><span style="font-size:12px;color:var(--text-3);"><?= formatDate($g['updated_at']) ?></span></td>
                <td style="font-weight:<?= $g['home_score']>$g['away_score']?'700':'500' ?>;"><?= clean($g['home_team']) ?></td>
                <td class="text-center">
                    <span style="font-weight:800;font-variant-numeric:tabular-nums;">
                        <span style="<?= $g['home_score']>$g['away_score']?'color:var(--brand-dark);':'' ?>"><?= $g['home_score'] ?></span>
                        <span style="color:var(--text-4);margin:0 3px;">—</span>
                        <span style="<?= $g['away_score']>$g['home_score']?'color:var(--brand-dark);':'' ?>"><?= $g['away_score'] ?></span>
                    </span>
                </td>
                <td style="font-weight:<?= $g['away_score']>$g['home_score']?'700':'500' ?>;"><?= clean($g['away_team']) ?></td>
                <td>
                    <a href="<?= ADMIN_URL ?>/games/enter_results.php?id=<?= $g['id'] ?>&season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>&edit=1&return_tab=schedule" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-edit fa-xs"></i> Edit
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?= renderSeasonPagination('recent', $currentRecentPage, $totalCompletedGames, $itemsPerPage, $seasonId, $league['id']) ?>
</div>

<!-- STANDINGS -->
<div class="tab-pane fade <?= isActivePaneClass('standings',$activeTab) ?>" id="tab-standings" role="tabpanel">
<?php if(empty($standingsAll)): ?>
<div class="card"><div class="card-body empty-state"><div class="empty-state-icon"><i class="fas fa-list-ol"></i></div><h5>No standings yet</h5><p>Standings update automatically after games</p></div></div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h2>Standings</h2>
        <span style="font-size:11px;color:var(--text-3);"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--brand);margin-right:4px;"></span>Playoff positions</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th style="width:50px;">#</th><th>Team</th><th class="text-center" style="width:50px;">W</th><th class="text-center" style="width:50px;">L</th><th class="text-center" style="width:60px;">PF</th><th class="text-center" style="width:60px;">PA</th><th class="text-center" style="width:70px;">+/−</th><th class="text-center" style="width:75px;color:#8b5cf6;">3PT%</th><th class="text-center" style="width:70px;color:#10b981;">FT%</th></tr></thead>
            <tbody>
            <?php foreach($standingsPage as $i=>$s): 
                $globalIndex = $standingsOffset + $i;
                $po=$globalIndex<$season['playoff_teams_count'];
                $tShoot = $teamShootingMap[$s['team_id']] ?? null;
                $pct3   = ($tShoot && $tShoot['total_3pt_att'] > 0) ? round($tShoot['total_3pt_made'] / $tShoot['total_3pt_att'] * 100) : null;
                $pctFt  = ($tShoot && $tShoot['total_ft_att'] > 0)  ? round($tShoot['total_ft_made']  / $tShoot['total_ft_att']  * 100) : null;
            ?>
            <tr style="<?= $po?'background:var(--brand-light);':'' ?>">
                <td class="text-center"><span class="rank-badge <?= $globalIndex===0?'rank-1':($globalIndex===1?'rank-2':($globalIndex===2?'rank-3':'rank-n')) ?>"><?= $globalIndex+1 ?></span></td>
                <td><div class="d-flex align-items-center gap-2"><?= $po?'<span style="width:6px;height:6px;border-radius:50%;background:var(--brand);display:inline-block;flex-shrink:0;"></span>':'' ?><span style="font-weight:600;"><?= clean($s['team_name']) ?></span></div></td>
                <td class="text-center" style="font-weight:800;"><?= $s['wins'] ?></td>
                <td class="text-center" style="color:var(--text-3);"><?= $s['losses'] ?></td>
                <td class="text-center" style="color:var(--text-2);"><?= $s['points_for'] ?></td>
                <td class="text-center" style="color:var(--text-2);"><?= $s['points_against'] ?></td>
                <td class="text-center" style="font-weight:600;color:<?= $s['point_differential']>0?'#16a34a':($s['point_differential']<0?'#dc2626':'var(--text-3)') ?>;"><?= $s['point_differential']>0?'+'.$s['point_differential']:$s['point_differential'] ?></td>
                <td class="text-center" style="font-size:12px;color:#8b5cf6;font-weight:600;"><?= $pct3 !== null ? $pct3.'%' : '—' ?></td>
                <td class="text-center" style="font-size:12px;color:#10b981;font-weight:600;"><?= $pctFt !== null ? $pctFt.'%' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?= renderSeasonPagination('standings', $currentStandingsPage, $totalStandings, $itemsPerPage, $seasonId, $league['id']) ?>
</div>

<!-- RANKINGS -->
<?php if(!empty($seasonRankingsAll)): ?>
<div class="tab-pane fade <?= isActivePaneClass('rankings',$activeTab) ?>" id="tab-rankings" role="tabpanel">
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-ranking-star me-2" style="color:var(--brand);"></i>Regular Season Rankings</h2>
        <span style="font-size:11px;color:var(--text-3);"><?= count($seasonRankingsAll) ?> players · Regular season games only</span>
    </div>
    <div style="max-height:520px;overflow-y:auto;overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table class="table table-hover mb-0">
            <thead style="position:sticky;top:0;z-index:2;">
            <tr>
                <th style="width:50px;">#</th><th>Player</th><th class="text-center" style="width:50px;">GP</th>
                <th class="text-center" style="width:70px;color:var(--brand);">PTS</th>
                <th class="text-center" style="width:60px;color:var(--brand);">PPG</th>
                <th class="text-center" style="width:50px;">2PT</th>
                <th class="text-center" style="width:50px;color:#8b5cf6;">3PT</th>
                <th class="text-center" style="width:50px;color:#10b981;">FT</th>
                <th class="text-center" style="width:65px;color:#8b5cf6;">3PT%</th>
                <th class="text-center" style="width:65px;color:#10b981;">FT%</th>
            </tr></thead>
            <tbody>
            <?php foreach($seasonRankingsAll as $i=>$p):
                $rank = $i + 1;
                $pct3=$p['total_3pt_att']>0?round($p['total_3pt']/$p['total_3pt_att']*100):0;
                $pctFt=$p['total_ft_att']>0?round($p['total_ft']/$p['total_ft_att']*100):0;
            ?>
            <tr>
                <td class="text-center"><span class="rank-badge <?= $rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':'rank-n')) ?>"><?= $rank ?></span></td>
                <td>
                    <div style="font-weight:600;font-size:13px;"><?= clean($p['first_name'].' '.$p['last_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-3);"><?= clean($p['team_name']) ?></div>
                </td>
                <td class="text-center" style="color:var(--text-3);"><?= $p['games'] ?></td>
                <td class="text-center"><span style="font-weight:800;font-size:15px;color:var(--brand);"><?= $p['total_pts'] ?></span></td>
                <td class="text-center"><span style="font-weight:700;color:var(--brand-dark);"><?= $p['ppg'] ?></span></td>
                <td class="text-center" style="color:var(--text-2);"><?= $p['total_2pt'] ?></td>
                <td class="text-center" style="color:#8b5cf6;font-weight:600;"><?= $p['total_3pt'] ?></td>
                <td class="text-center" style="color:#10b981;font-weight:600;"><?= $p['total_ft'] ?></td>
                <td class="text-center" style="font-size:12px;color:var(--text-3);"><?= $p['total_3pt_att']>0?$pct3.'%':'—' ?></td>
                <td class="text-center" style="font-size:12px;color:var(--text-3);"><?= $p['total_ft_att']>0?$pctFt.'%':'—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php endif; ?>

<!-- PLAYOFFS -->
<?php if($hasPlayoffs): ?>
<div class="tab-pane fade <?= isActivePaneClass('playoffs',$activeTab) ?>" id="tab-playoffs" role="tabpanel">

<?php
// Build a bracket map keyed by position for easy rendering
$bracketMap = [];
$totalPlayoffGames = count($playoffGames);
foreach ($playoffGames as $g) {
    $bracketMap[(int)$g['playoff_position']] = $g;
}
$is4Team = ($totalPlayoffGames === 3);
$is8Team = ($totalPlayoffGames === 7);

// Helper: returns a human-readable label for the source game slot
// e.g. "Winner of QF1", "Winner of SF2"
function getSourceLabel(array $sourceGame, bool $is8Team): string {
    $round = $sourceGame['playoff_round'] ?? '';
    $pos   = (int)$sourceGame['playoff_position'];
    if ($round === 'Quarterfinal') {
        return 'Winner of QF' . $pos;
    }
    if ($round === 'Semifinal') {
        // 8-team: SF positions are 5 & 6 → SF1, SF2
        // 4-team: SF positions are 1 & 2 → SF1, SF2
        $sfNum = $is8Team ? ($pos - 4) : $pos;
        return 'Winner of SF' . $sfNum;
    }
    return 'Winner of Game ' . $pos;
}

// Helper: render a single bracket card
// $gameById  — map of all playoff game_id → game row (for source-game lookups)
// $is8Team   — true when it's an 8-team bracket (affects SF label numbering)
function renderBracketGame(array $g, int $seasonId, int $leagueId, bool $isCompleted, array $gameById = [], bool $is8Team = false): string {
    $done     = $g['status'] === 'completed';
    $homeWin  = $done && (int)$g['home_score'] > (int)$g['away_score'];
    $awayWin  = $done && (int)$g['away_score'] > (int)$g['home_score'];
    $editUrl  = ADMIN_URL . '/games/enter_results.php?id=' . $g['id'] . '&season_id=' . $seasonId . '&league_id=' . $leagueId . '&return_tab=playoffs';

    // ── Sequential lock logic ─────────────────────────────────────────────────
    // A downstream game is "locked" (not yet enterable) until all its source
    // games are completed.  We never change backend data — we only gate the UI.
    $homeSourceId   = isset($g['home_source_game_id']) ? (int)$g['home_source_game_id'] : 0;
    $awaySourceId   = isset($g['away_source_game_id']) ? (int)$g['away_source_game_id'] : 0;
    $homeSourceGame = $homeSourceId ? ($gameById[$homeSourceId] ?? null) : null;
    $awaySourceGame = $awaySourceId ? ($gameById[$awaySourceId] ?? null) : null;

    $homeSourceDone = !$homeSourceGame || $homeSourceGame['status'] === 'completed';
    $awaySourceDone = !$awaySourceGame || $awaySourceGame['status'] === 'completed';
    $canEnter       = $homeSourceDone && $awaySourceDone;

    // Use placeholder text when the source game hasn't finished yet
    $homeLabel = (!$homeSourceDone && $homeSourceGame)
        ? getSourceLabel($homeSourceGame, $is8Team)
        : clean($g['home_team']);
    $awayLabel = (!$awaySourceDone && $awaySourceGame)
        ? getSourceLabel($awaySourceGame, $is8Team)
        : clean($g['away_team']);

    // Extra CSS class when the game is pending its prerequisites
    $lockedClass = (!$done && !$canEnter) ? ' bracket-locked' : '';

    $card  = '<div class="bracket-game' . ($done ? ' bracket-done' : '') . $lockedClass . '">';
    $card .= '<div class="bracket-game-label">' . clean($g['playoff_round'] ?? '') . ' · ' . ($done ? 'Final' : ($canEnter ? 'Scheduled' : 'Awaiting')) . '</div>';

    // Home team row
    $homeIsPlaceholder = (!$homeSourceDone && $homeSourceGame);
    $card .= '<div class="bracket-team' . ($homeWin ? ' winner' : ($done ? ' loser' : ($homeIsPlaceholder ? ' tbd' : ''))) . '">';
    $card .= '<span class="bracket-team-name' . ($homeIsPlaceholder ? ' bracket-tbd-name' : '') . '">' . $homeLabel . '</span>';
    $card .= '<span class="bracket-team-score">' . ($done ? $g['home_score'] : '—') . '</span>';
    $card .= '</div>';

    // Away team row
    $awayIsPlaceholder = (!$awaySourceDone && $awaySourceGame);
    $card .= '<div class="bracket-team' . ($awayWin ? ' winner' : ($done ? ' loser' : ($awayIsPlaceholder ? ' tbd' : ''))) . '">';
    $card .= '<span class="bracket-team-name' . ($awayIsPlaceholder ? ' bracket-tbd-name' : '') . '">' . $awayLabel . '</span>';
    $card .= '<span class="bracket-team-score">' . ($done ? $g['away_score'] : '—') . '</span>';
    $card .= '</div>';

    if (!$isCompleted) {
        $card .= '<div class="bracket-game-action">';
        if ($canEnter) {
            $card .= '<a href="' . $editUrl . '" class="btn btn-brand btn-sm" style="font-size:11px;padding:3px 10px;">';
            $card .= $done ? '<i class="fas fa-pen fa-xs"></i> Edit' : '<i class="fas fa-plus fa-xs"></i> Enter';
            $card .= '</a>';
        } else {
            // Previous round(s) not yet finished — lock this game
            $pending = [];
            if (!$homeSourceDone && $homeSourceGame) {
                $pending[] = getSourceLabel($homeSourceGame, $is8Team);
            }
            if (!$awaySourceDone && $awaySourceGame) {
                $pending[] = getSourceLabel($awaySourceGame, $is8Team);
            }
            $tip = 'Waiting for: ' . implode(' & ', $pending);
            $card .= '<button class="btn btn-sm bracket-locked-btn" disabled title="' . htmlspecialchars($tip) . '">';
            $card .= '<i class="fas fa-lock fa-xs me-1"></i>Locked';
            $card .= '</button>';
        }
        $card .= '</div>';
    }

    $card .= '</div>';
    return $card;
}
?>

<div class="card mb-3">
    <div class="card-header" style="gap:12px;flex-wrap:wrap;">
        <div>
            <h2 style="margin:0;">Playoff Bracket</h2>
            <?php if($isCompleted && $playoffMvp): ?>
            <div style="font-size:12px;color:#d97706;font-weight:700;margin-top:4px;"><i class="fas fa-trophy me-1"></i>Playoffs MVP: <?= clean($playoffMvp['first_name'].' '.$playoffMvp['last_name']) ?></div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-left:auto;">
            <span style="font-size:11px;color:var(--text-3);"><?= $totalPlayoffGames === 3 ? '4-team' : '8-team' ?> single elimination</span>
            <?php if(!$isCompleted && isAdmin()): ?>
            <form method="POST" action="<?= ADMIN_URL ?>/playoffs/reset.php" style="margin:0;"
                  onsubmit="return confirm('Reset the bracket? All playoff results will be deleted and the bracket will be regenerated from standings.')">
                <input type="hidden" name="season_id" value="<?= $seasonId ?>">
                <input type="hidden" name="league_id" value="<?= $league['id'] ?>">
                <button type="submit" class="btn btn-light btn-sm" style="font-size:11px;">
                    <i class="fas fa-rotate fa-xs me-1"></i>Reset Bracket
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Visual Bracket -->
    <div class="bracket-container" style="overflow-x:auto;padding:24px;">

    <?php if($is4Team): ?>
    <!-- 4-TEAM BRACKET: Semis → Final -->
    <div class="bracket-layout bracket-4team">
        <div class="bracket-col" data-round="Semifinals">
            <div class="bracket-col-label">Semifinals</div>
            <?= renderBracketGame($bracketMap[1], $seasonId, $league['id'], $isCompleted, $playoffGameById, false) ?>
            <?= renderBracketGame($bracketMap[2], $seasonId, $league['id'], $isCompleted, $playoffGameById, false) ?>
        </div>
        <div class="bracket-connector-col">
            <div class="bracket-line-wrap">
                <div class="bracket-line-top"></div>
                <div class="bracket-line-center"></div>
                <div class="bracket-line-bottom"></div>
            </div>
        </div>
        <div class="bracket-col" data-round="Final">
            <div class="bracket-col-label">Final</div>
            <div class="bracket-final-wrap">
                <?= renderBracketGame($bracketMap[3], $seasonId, $league['id'], $isCompleted, $playoffGameById, false) ?>
            </div>
        </div>
    </div>

    <?php elseif($is8Team): ?>
    <!-- 8-TEAM BRACKET: Quarters → Semis → Final -->
    <div class="bracket-layout bracket-8team">
        <div class="bracket-col" data-round="Quarterfinals">
            <div class="bracket-col-label">Quarterfinals</div>
            <?= renderBracketGame($bracketMap[1], $seasonId, $league['id'], $isCompleted, $playoffGameById, true) ?>
            <?= renderBracketGame($bracketMap[2], $seasonId, $league['id'], $isCompleted, $playoffGameById, true) ?>
            <?= renderBracketGame($bracketMap[3], $seasonId, $league['id'], $isCompleted, $playoffGameById, true) ?>
            <?= renderBracketGame($bracketMap[4], $seasonId, $league['id'], $isCompleted, $playoffGameById, true) ?>
        </div>
        <div class="bracket-connector-col">
            <div class="bracket-connector-pair"></div>
            <div class="bracket-connector-gap"></div>
            <div class="bracket-connector-pair"></div>
        </div>
        <div class="bracket-col" data-round="Semifinals">
            <div class="bracket-col-label">Semifinals</div>
            <div class="bracket-sf-wrap">
                <?= renderBracketGame($bracketMap[5], $seasonId, $league['id'], $isCompleted, $playoffGameById, true) ?>
                <?= renderBracketGame($bracketMap[6], $seasonId, $league['id'], $isCompleted, $playoffGameById, true) ?>
            </div>
        </div>
        <div class="bracket-connector-col">
            <div class="bracket-line-wrap">
                <div class="bracket-line-top"></div>
                <div class="bracket-line-center"></div>
                <div class="bracket-line-bottom"></div>
            </div>
        </div>
        <div class="bracket-col" data-round="Final">
            <div class="bracket-col-label">Final</div>
            <div class="bracket-final-wrap">
                <?= renderBracketGame($bracketMap[7], $seasonId, $league['id'], $isCompleted, $playoffGameById, true) ?>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Fallback table for non-standard brackets -->
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Round</th><th>Home</th><th class="text-center">Score</th><th>Away</th><th>Status</th><?php if(!$isCompleted): ?><th class="text-end">Action</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach($playoffGames as $g): $done=$g['status']==='completed'; ?>
            <tr>
                <td style="font-weight:600;font-size:12px;"><?= clean($g['playoff_round']??'—') ?></td>
                <td style="font-weight:<?= $done&&$g['home_score']>$g['away_score']?'700':'500' ?>;"><?= clean($g['home_team']) ?></td>
                <td class="text-center"><?php if($done): ?><span style="font-weight:800;"><?= $g['home_score']?> — <?= $g['away_score'] ?></span><?php else: ?><span style="color:var(--text-4);">vs</span><?php endif; ?></td>
                <td style="font-weight:<?= $done&&$g['away_score']>$g['home_score']?'700':'500' ?>;"><?= clean($g['away_team']) ?></td>
                <td><?= $done?'<span style="background:#dcfce7;color:#15803d;font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px;">Done</span>':'<span class="badge-upcoming">Scheduled</span>' ?></td>
                <?php if(!$isCompleted): ?><td class="text-end"><a href="<?= ADMIN_URL ?>/games/enter_results.php?id=<?= $g['id'] ?>&season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>&return_tab=playoffs" class="btn btn-brand btn-sm"><?= $done?'<i class="fas fa-pen fa-xs"></i> Edit':'<i class="fas fa-plus fa-xs"></i> Enter' ?></a></td><?php else: ?><td></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    </div><!-- .bracket-container -->
</div><!-- .card -->

<!-- ── PLAYOFF PLAYER RANKINGS ── -->
<?php if ($hasPlayoffStats): ?>
<div class="card mb-3">
    <div class="card-header">
        <h2 style="display:flex;align-items:center;gap:8px;">
            <i class="fas fa-ranking-star" style="color:#f59e0b;"></i> Playoff Player Rankings
        </h2>
        <span style="font-size:11px;color:var(--text-3);"><?= count($playoffRankings) ?> players · Playoff games only</span>
    </div>
    <div style="max-height:480px;overflow-y:auto;overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table class="table table-hover mb-0">
            <thead style="position:sticky;top:0;z-index:2;">
            <tr>
                <th style="width:50px;">#</th>
                <th>Player</th>
                <th class="text-center" style="width:50px;">GP</th>
                <th class="text-center" style="width:70px;color:#f59e0b;">PTS</th>
                <th class="text-center" style="width:60px;color:#f59e0b;">PPG</th>
                <th class="text-center" style="width:50px;">2PT</th>
                <th class="text-center" style="width:50px;color:#8b5cf6;">3PT</th>
                <th class="text-center" style="width:50px;color:#10b981;">FT</th>
                <th class="text-center" style="width:65px;color:#8b5cf6;">3PT%</th>
                <th class="text-center" style="width:65px;color:#10b981;">FT%</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($playoffRankings as $i => $p):
                $rank = $i + 1;
                $pct3 = $p['total_3pt_att'] > 0 ? round($p['total_3pt'] / $p['total_3pt_att'] * 100) : 0;
                $pctFt= $p['total_ft_att']  > 0 ? round($p['total_ft']  / $p['total_ft_att']  * 100) : 0;
            ?>
            <tr>
                <td class="text-center">
                    <span class="rank-badge <?= $rank===1?'rank-1':($rank===2?'rank-2':($rank===3?'rank-3':'rank-n')) ?>"><?= $rank ?></span>
                </td>
                <td>
                    <div style="font-weight:600;font-size:13px;"><?= clean($p['first_name'].' '.$p['last_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-3);"><?= clean($p['team_name']) ?></div>
                </td>
                <td class="text-center" style="color:var(--text-3);"><?= $p['games'] ?></td>
                <td class="text-center"><span style="font-weight:800;font-size:15px;color:#f59e0b;"><?= $p['total_pts'] ?></span></td>
                <td class="text-center"><span style="font-weight:700;color:#b45309;"><?= $p['ppg'] ?></span></td>
                <td class="text-center" style="color:var(--text-2);"><?= $p['total_2pt'] ?></td>
                <td class="text-center" style="color:#8b5cf6;font-weight:600;"><?= $p['total_3pt'] ?></td>
                <td class="text-center" style="color:#10b981;font-weight:600;"><?= $p['total_ft'] ?></td>
                <td class="text-center" style="font-size:12px;color:var(--text-3);"><?= $p['total_3pt_att']>0 ? $pct3.'%' : '—' ?></td>
                <td class="text-center" style="font-size:12px;color:var(--text-3);"><?= $p['total_ft_att']>0 ? $pctFt.'%' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div><!-- #tab-playoffs -->
<?php endif; ?>

<!-- ROSTERS -->
<div class="tab-pane fade <?= isActivePaneClass('roster',$activeTab) ?>" id="tab-roster" role="tabpanel">
<div class="row g-3">
<?php foreach($teams as $t):
    $rs=$pdo->prepare("SELECT sr.jersey_number,p.id as player_id,p.first_name,p.last_name,p.position,sr.status FROM season_rosters sr JOIN players p ON sr.player_id=p.id WHERE sr.season_id=? AND sr.team_id=? ORDER BY sr.jersey_number");
    $rs->execute([$seasonId,$t['id']]); $roster=$rs->fetchAll(); ?>
<div class="col-md-6">
    <div class="card">
        <div class="card-header">
            <h2 style="display:flex;align-items:center;gap:8px;">
                <?php if(!empty($t['logo'])): ?><img src="<?= UPLOADS_URL.'/'.$t['logo'] ?>" style="width:24px;height:24px;border-radius:4px;object-fit:cover;" alt=""><?php else: ?><div class="team-avatar" style="width:28px;height:28px;font-size:12px;"><?= strtoupper(substr($t['name'],0,1)) ?></div><?php endif; ?>
                <?= clean($t['name']) ?>
            </h2>
            <?php if (!$isLocked): ?>
            <a href="<?= ADMIN_URL ?>/seasons/roster.php?season_id=<?= $seasonId ?>&team_id=<?= $t['id'] ?>&league_id=<?= $league['id'] ?>" class="btn btn-light btn-sm"><i class="fas fa-edit fa-xs me-1"></i>Manage</a>
            <?php else: ?>
            <span style="font-size:11px;color:var(--text-3);display:flex;align-items:center;gap:4px;"><i class="fas fa-lock fa-xs"></i> Locked</span>
            <?php endif; ?>
        </div>
        <?php if(empty($roster)): ?>
        <div class="card-body" style="padding:16px;"><p style="font-size:12px;color:var(--text-3);margin:0;">No roster set</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:13px;">
                <thead><tr><th style="width:36px;">#</th><th>Player</th><th style="width:70px;">Pos</th><th style="width:70px;">Status</th></tr></thead>
                <tbody>
                <?php foreach($roster as $r): ?>
                <tr>
                    <td style="font-weight:700;color:var(--text-3);font-size:12px;"><?= $r['jersey_number'] ?></td>
                    <td style="font-weight:600;"><a href="<?= ADMIN_URL ?>/players/view.php?id=<?= $r['player_id'] ?>&league_id=<?= $league['id'] ?>" style="color:var(--text-1);text-decoration:none;" onmouseover="this.style.color='var(--brand)'" onmouseout="this.style.color='var(--text-1)'"><?= clean($r['first_name'].' '.$r['last_name']) ?></a></td>
                    <td style="color:var(--text-3);font-size:12px;"><?= $r['position']?:'—' ?></td>
                    <td><?= $r['status']==='active'?'<span style="font-size:10px;background:#dcfce7;color:#15803d;padding:2px 7px;border-radius:10px;font-weight:600;">Active</span>':'<span style="font-size:10px;background:#fee2e2;color:#dc2626;padding:2px 7px;border-radius:10px;font-weight:600;">'.ucfirst($r['status']).'</span>' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
</div>

</div><!-- .tab-content -->

<!-- Pagination & Bracket Styles -->
<style>
/* ── Locked / TBD states ── */
.bracket-game.bracket-locked {
    opacity: 0.72;
    background: var(--surface-2);
    border-style: dashed;
}
.bracket-tbd-name {
    color: var(--text-4) !important;
    font-style: italic;
    font-weight: 500 !important;
    font-size: 11px !important;
}
.bracket-team.tbd .bracket-team-score {
    color: var(--text-4);
}
.bracket-locked-btn {
    font-size: 11px;
    padding: 3px 10px;
    background: var(--surface-3);
    color: var(--text-4);
    cursor: not-allowed;
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
}

/* ── Tab badges ── */
.tab-badge {
    background: var(--surface-3);
    color: var(--text-3);
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
    margin-left: 4px;
}

/* ── Pagination ── */
.pagination-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 32px;
    padding: 0 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-2);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    text-decoration: none;
    transition: all 0.15s ease;
    cursor: pointer;
}
.pagination-btn:hover {
    background: var(--surface-2);
    color: var(--text-1);
    border-color: var(--brand);
    transform: translateY(-1px);
}
.pagination-btn.active {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
    font-weight: 700;
}

/* ── Bracket layout ── */
.bracket-layout {
    display: flex;
    align-items: center;
    gap: 0;
    min-width: 480px;
}
.bracket-col {
    display: flex;
    flex-direction: column;
    gap: 16px;
    flex-shrink: 0;
}
.bracket-col-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-weight: 700;
    color: var(--text-3);
    margin-bottom: 4px;
    text-align: center;
}
.bracket-final-wrap,
.bracket-sf-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
    justify-content: center;
    height: 100%;
}
.bracket-game {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    padding: 10px 12px;
    min-width: 190px;
    max-width: 220px;
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
    transition: box-shadow .15s;
}
.bracket-game:hover {
    box-shadow: 0 3px 10px rgba(0,0,0,.1);
}
.bracket-game.bracket-done {
    border-color: #d1fae5;
    background: var(--surface);
}
.bracket-game-label {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    color: var(--text-4);
    margin-bottom: 7px;
}
.bracket-team {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid var(--border);
}
.bracket-team:last-of-type {
    border-bottom: none;
}
.bracket-team.winner .bracket-team-name {
    font-weight: 800;
    color: var(--brand-dark, #e55a00);
}
.bracket-team.winner .bracket-team-score {
    font-weight: 900;
    color: var(--brand-dark, #e55a00);
}
.bracket-team.loser .bracket-team-name,
.bracket-team.loser .bracket-team-score {
    color: var(--text-4);
}
.bracket-team-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-1);
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bracket-team-score {
    font-size: 14px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: var(--text-2);
    flex-shrink: 0;
    min-width: 22px;
    text-align: right;
}
.bracket-game-action {
    margin-top: 8px;
    text-align: right;
}

/* ── Connector lines ── */
.bracket-connector-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 32px;
    flex-shrink: 0;
    align-self: stretch;
}
.bracket-line-wrap {
    display: flex;
    flex-direction: column;
    height: 100%;
    width: 100%;
    align-items: flex-start;
}
.bracket-line-top,
.bracket-line-bottom {
    flex: 1;
    border-right: 2px solid var(--border);
    width: 50%;
}
.bracket-line-top { border-bottom: 2px solid var(--border); border-radius: 0 0 6px 0; }
.bracket-line-bottom { border-top: 2px solid var(--border); border-radius: 0 6px 0 0; }
.bracket-line-center {
    width: 50%;
    border-right: 2px solid var(--border);
    height: 2px;
    border-right: none;
    border-top: 2px solid var(--border);
    align-self: center;
}

/* 8-team connector helpers */
.bracket-connector-pair { flex: 1; }
.bracket-connector-gap  { flex: 2; }

@media (max-width: 640px) {
    .bracket-layout {
        flex-direction: column;
        align-items: stretch;
        min-width: unset;
    }
    .bracket-connector-col { display: none; }
    .bracket-col { min-width: unset; }
    .bracket-game { max-width: 100%; }
}
</style>

<script>
// ── Tab persistence ───────────────────────────────────────────────────────────
// The server already handles initial tab state via PHP (?tab= param).
// This JS handles:
//  1. Updating the URL hash when user clicks a tab (for bookmarking)
//  2. Passing the active tab on pagination links so the server can restore it
(function () {
    'use strict';

    const tabs = document.querySelectorAll('#seasonTabs [data-bs-toggle="tab"]');
    const baseSearch = new URLSearchParams(window.location.search);

    // When a tab is clicked, update URL hash without reloading
    tabs.forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (e) {
            const targetId = e.target.getAttribute('href'); // e.g. "#tab-standings"
            const tabName  = targetId.replace('#tab-', '');

            // Update URL hash for bookmarking (no reload)
            history.replaceState(null, '', window.location.pathname + '?' +
                buildSearch(baseSearch, tabName));
        });
    });

    /**
     * Rebuild query string preserving season context, setting the active tab,
     * and clearing any pagination offsets from other tabs.
     */
    function buildSearch(base, tabName) {
        const p = new URLSearchParams();
        ['id', 'league_id'].forEach(function (k) {
            if (base.has(k)) p.set(k, base.get(k));
        });
        p.set('tab', tabName);
        // Preserve current pagination for the active tab only
        const pageKey = tabName + '_page';
        if (base.has(pageKey)) p.set(pageKey, base.get(pageKey));
        return p.toString();
    }

    // Rewrite ALL pagination links so they include &tab=<current-tab>
    // This ensures pagination never loses the active tab.
    function rewirePagination() {
        const activeTab = document.querySelector('#seasonTabs .nav-link.active');
        if (!activeTab) return;
        const tabName = activeTab.getAttribute('href').replace('#tab-', '');

        document.querySelectorAll('a.pagination-btn').forEach(function (link) {
            const u = new URL(link.href, window.location.href);
            u.searchParams.set('tab', tabName);
            link.href = u.pathname + '?' + u.searchParams.toString();
        });
    }

    // Run on load and whenever a tab changes
    document.addEventListener('DOMContentLoaded', rewirePagination);
    tabs.forEach(function (t) {
        t.addEventListener('shown.bs.tab', rewirePagination);
    });
}());
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
