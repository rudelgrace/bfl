<?php
require_once __DIR__ . '/../../includes/functions.php';
if (isScorer()) { redirect(ADMIN_URL . '/scorer/index.php'); }

$leagueId      = intGet('id');
$league        = requireLeague($leagueId);
$leagueContext = $league;
$activeSidebar = 'overview';
$activeNav     = 'leagues';
$pageTitle     = $league['name'];

require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE league_id=?'); $stmt->execute([$leagueId]); $playerCount=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM teams WHERE league_id=?'); $stmt->execute([$leagueId]); $teamCount=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM seasons WHERE league_id=?'); $stmt->execute([$leagueId]); $seasonCount=(int)$stmt->fetchColumn();

// Active season ONLY (not completed)
$stmt = $pdo->prepare("SELECT * FROM seasons WHERE league_id=? AND status IN ('active','playoffs') LIMIT 1");
$stmt->execute([$leagueId]); $activeSeason=$stmt->fetch();

// Seasons list
$stmt = $pdo->prepare("SELECT s.*,
    (SELECT COUNT(*) FROM season_teams WHERE season_id=s.id) as team_count,
    (SELECT COUNT(*) FROM games WHERE season_id=s.id AND status='completed') as games_played,
    (SELECT COUNT(*) FROM games WHERE season_id=s.id) as total_games
    FROM seasons s WHERE s.league_id=? ORDER BY s.created_at DESC LIMIT 10");
$stmt->execute([$leagueId]); $seasons=$stmt->fetchAll();

// Recent games
$stmt = $pdo->prepare("SELECT g.*, ht.name as home_team, at.name as away_team, s.name as season_name
    FROM games g JOIN seasons s ON g.season_id=s.id
    JOIN teams ht ON g.home_team_id=ht.id JOIN teams at ON g.away_team_id=at.id
    WHERE s.league_id=? AND g.status='completed' ORDER BY g.updated_at DESC LIMIT 8");
$stmt->execute([$leagueId]); $recentGames=$stmt->fetchAll();

// Top-5 all-time leaders (regular season only)
$top5Scorers = getLeagueTop5Scorers($leagueId);
$top5ThreePT = getLeagueTop5ThreePointers($leagueId);
$top5FT      = getLeagueTop5FreeThrows($leagueId);

// Top-5 playoff leaders (shown only when data exists)
$top5POScorers = getLeagueTop5PlayoffScorers($leagueId);
$hasPlayoffLeaderData = !empty($top5POScorers);
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/index.php">Leagues</a></li>
        <li class="breadcrumb-item active"><?= clean($league['name']) ?></li>
    </ol>
</nav>

<!-- League Header -->
<div class="d-flex align-items-start justify-content-between mb-5">
    <div class="d-flex align-items-center gap-4">
        <?php if (!empty($league['logo'])): ?>
            <img src="<?= UPLOADS_URL.'/'.$league['logo'] ?>"
                 style="width:64px;height:64px;border-radius:var(--r-lg);object-fit:cover;box-shadow:var(--shadow-md);" alt="">
        <?php else: ?>
            <div class="league-avatar" style="width:64px;height:64px;border-radius:var(--r-lg);font-size:28px;">
                <?= strtoupper(substr($league['name'],0,1)) ?>
            </div>
        <?php endif; ?>
        <div>
            <h1 style="font-size:24px;font-weight:800;letter-spacing:-.025em;margin:0;"><?= clean($league['name']) ?></h1>
            <?php if (!empty($league['description'])): ?>
                <p style="font-size:13px;color:var(--text-3);margin:4px 0 0;"><?= clean($league['description']) ?></p>
            <?php endif; ?>
            <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                <span style="font-size:11px;color:var(--text-3);font-weight:600;"><i class="fas fa-shield-halved me-1"></i><?= $teamCount ?> teams</span>
                <span style="font-size:11px;color:var(--text-3);font-weight:600;"><i class="fas fa-person-running me-1"></i><?= $playerCount ?> players</span>
                <span style="font-size:11px;color:var(--text-3);font-weight:600;"><i class="fas fa-calendar-days me-1"></i><?= $seasonCount ?> seasons</span>
            </div>
        </div>
    </div>
    <a href="<?= ADMIN_URL ?>/leagues/edit.php?id=<?= $leagueId ?>" class="btn btn-light btn-sm">
        <i class="fas fa-pen fa-xs"></i> Edit League
    </a>
</div>

<!-- Active Season Banner -->
<?php if ($activeSeason): ?>
<div class="asb mb-4">
    <div>
        <div class="asb-pill">
            <span class="asb-dot"></span>
            <?= $activeSeason['status'] === 'playoffs' ? 'Playoffs' : 'Live Season' ?>
        </div>
        <h2 style="font-size:20px;font-weight:800;margin:6px 0 4px;letter-spacing:-.02em;"><?= clean($activeSeason['name']) ?></h2>
        <?php if ($activeSeason['start_date']): ?>
            <p style="font-size:12px;color:rgba(255,255,255,.6);margin:0;">
                <?= formatDate($activeSeason['start_date']) ?>
                <?php if ($activeSeason['end_date']): ?> → <?= formatDate($activeSeason['end_date']) ?><?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
    <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $activeSeason['id'] ?>&league_id=<?= $leagueId ?>"
       style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.25);color:#fff;padding:10px 18px;border-radius:var(--r-sm);font-size:13px;font-weight:700;white-space:nowrap;flex-shrink:0;display:inline-flex;align-items:center;gap:6px;transition:background .15s;"
       onmouseover="this.style.background='rgba(255,255,255,.3)'" onmouseout="this.style.background='rgba(255,255,255,.2)'">
        Open Season <i class="fas fa-arrow-right fa-xs"></i>
    </a>
</div>
<?php else: ?>
<div style="border:2px dashed var(--border);border-radius:var(--r-lg);padding:24px;text-align:center;margin-bottom:24px;">
    <p style="color:var(--text-3);font-size:13px;margin-bottom:12px;">No active season</p>
    <a href="<?= ADMIN_URL ?>/seasons/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand btn-sm">
        <i class="fas fa-plus"></i> Start a Season
    </a>
</div>
<?php endif; ?>

<!-- Two-column: Seasons + Recent Games -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h2>Seasons</h2>
                <a href="<?= ADMIN_URL ?>/seasons/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand btn-sm">
                    <i class="fas fa-plus"></i> New
                </a>
            </div>
            <?php if (empty($seasons)): ?>
            <div class="card-body empty-state py-5">
                <div class="empty-state-icon"><i class="fas fa-calendar-xmark"></i></div>
                <h5>No seasons yet</h5>
            </div>
            <?php else: ?>
            <?php foreach ($seasons as $s): ?>
            <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $s['id'] ?>&league_id=<?= $leagueId ?>"
               class="list-item" style="text-decoration:none;color:inherit;gap:12px;">
                <div style="width:36px;height:36px;border-radius:8px;background:var(--surface-3);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-calendar-days" style="color:var(--text-3);font-size:14px;"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:13px;font-weight:600;color:var(--text-1);"><?= clean($s['name']) ?></div>
                    <div style="font-size:11px;color:var(--text-3);margin-top:2px;">
                        <?= $s['team_count'] ?> teams · <?= $s['games_played'] ?>/<?= $s['total_games'] ?> games
                    </div>
                </div>
                <?= seasonStatusBadge($s['status']) ?>
            </a>
            <?php endforeach; ?>
            <?php if ($seasonCount > 10): ?>
            <div style="padding:12px 20px;border-top:1px solid var(--border);">
                <a href="<?= ADMIN_URL ?>/seasons/index.php?league_id=<?= $leagueId ?>"
                   style="font-size:12px;font-weight:600;color:var(--brand);">View all <?= $seasonCount ?> seasons →</a>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h2>Recent Games</h2></div>
            <?php if (empty($recentGames)): ?>
            <div class="card-body empty-state py-5">
                <div class="empty-state-icon"><i class="fas fa-basketball"></i></div>
                <h5>No games played yet</h5>
            </div>
            <?php else: ?>
            <?php foreach ($recentGames as $g): ?>
            <div class="list-item" style="display:block;padding:12px 20px;">
                <div style="font-size:10px;color:var(--text-3);margin-bottom:4px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;"><?= clean($g['season_name']) ?></div>
                <div class="d-flex align-items-center justify-content-between">
                    <span style="font-size:13px;font-weight:<?= $g['home_score']>$g['away_score']?'700':'500' ?>;<?= $g['home_score']>$g['away_score']?'color:var(--text-1)':'color:var(--text-2)' ?>;"><?= clean($g['home_team']) ?></span>
                    <div style="display:flex;align-items:center;gap:8px;font-variant-numeric:tabular-nums;">
                        <span style="font-size:16px;font-weight:800;"><?= $g['home_score'] ?></span>
                        <span style="color:var(--text-4);font-size:12px;">—</span>
                        <span style="font-size:16px;font-weight:800;"><?= $g['away_score'] ?></span>
                    </div>
                    <span style="font-size:13px;font-weight:<?= $g['away_score']>$g['home_score']?'700':'500' ?>;<?= $g['away_score']>$g['home_score']?'color:var(--text-1)':'color:var(--text-2)' ?>;text-align:right;"><?= clean($g['away_team']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- All-Time Leaders (3 columns, top 5 each, view all link) -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 style="font-size:16px;font-weight:800;margin:0;">
        All-Time Leaders
        <span style="font-size:11px;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:20px;font-weight:600;margin-left:6px;">📋 Regular Season</span>
    </h2>
    <?php if ($hasPlayoffLeaderData): ?>
    <a href="<?= ADMIN_URL ?>/leagues/leaders.php?league_id=<?= $leagueId ?>&type=playoff"
       style="font-size:12px;color:#b45309;font-weight:600;display:flex;align-items:center;gap:4px;">
        <i class="fas fa-trophy fa-xs"></i> View Playoff Leaders →
    </a>
    <?php endif; ?>
</div>
<div class="row g-3">
<?php
$leaderSections = [
    ['All-Time Scoring Leaders',  $top5Scorers, 'total_points', 'Points', '#f97316', 'fa-basketball',    'points'],
    ['All-Time 3PT Leaders',      $top5ThreePT, 'total_3pt',   '3PT',    '#8b5cf6', 'fa-circle-dot',    'three'],
    ['All-Time FT Leaders',       $top5FT,      'total_ft',    'FT',     '#10b981', 'fa-hand-point-up', 'ft'],
];
foreach ($leaderSections as [$title, $rows, $valKey, $valLabel, $color, $icon, $statKey]):
?>
<div class="col-lg-4">
    <div class="card h-100">
        <div class="card-header">
            <h2 style="display:flex;align-items:center;gap:8px;">
                <i class="fas <?= $icon ?>" style="color:<?= $color ?>;font-size:12px;"></i> <?= $title ?>
            </h2>
            <a href="<?= ADMIN_URL ?>/leagues/leaders.php?league_id=<?= $leagueId ?>&stat=<?= $statKey ?>&type=regular"
               style="font-size:11px;color:var(--brand);font-weight:600;white-space:nowrap;">View all →</a>
        </div>
        <?php if (empty($rows)): ?>
        <div class="card-body empty-state py-4">
            <div class="empty-state-icon" style="width:44px;height:44px;"><i class="fas fa-chart-bar" style="font-size:16px;"></i></div>
            <p style="font-size:12px;">No stats yet</p>
        </div>
        <?php else: ?>
        <?php foreach ($rows as $i => $p): ?>
        <div class="list-item" style="gap:12px;">
            <div class="rank-badge <?= $i===0?'rank-1':($i===1?'rank-2':($i===2?'rank-3':'rank-n')) ?>"><?= $i+1 ?></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= clean($p['first_name'].' '.$p['last_name']) ?>
                </div>
                <div style="font-size:11px;color:var(--text-3);"><?= $p['games'] ?> games</div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:16px;font-weight:800;color:<?= $color ?>;line-height:1;"><?= $p[$valKey] ?></div>
                <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;"><?= strtolower($valLabel) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
