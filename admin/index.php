<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$leagueContext = null;
require_once __DIR__ . '/../includes/functions.php';

// Scorers have their own landing page
if (isScorer()) {
    redirect(ADMIN_URL . '/scorer/index.php');
}

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$stats = [
    'leagues' => (int)$pdo->query('SELECT COUNT(*) FROM leagues')->fetchColumn(),
    'teams'   => (int)$pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn(),
    'players' => (int)$pdo->query('SELECT COUNT(*) FROM players')->fetchColumn(),
    'seasons' => (int)$pdo->query('SELECT COUNT(*) FROM seasons')->fetchColumn(),
    'active'  => (int)$pdo->query("SELECT COUNT(*) FROM seasons WHERE status='active'")->fetchColumn(),
];

$leagues = $pdo->query("
    SELECT l.*,
        (SELECT COUNT(*) FROM teams   WHERE league_id=l.id) as team_count,
        (SELECT COUNT(*) FROM players WHERE league_id=l.id) as player_count,
        (SELECT COUNT(*) FROM seasons WHERE league_id=l.id) as season_count,
        (SELECT name     FROM seasons WHERE league_id=l.id AND status='active' LIMIT 1) as active_season
    FROM leagues l ORDER BY l.created_at DESC LIMIT 6
")->fetchAll();

$recentGames = $pdo->query("
    SELECT g.*, s.name as season_name, l.name as league_name,
           ht.name as home_team, at.name as away_team
    FROM games g
    JOIN seasons s ON g.season_id=s.id JOIN leagues l ON s.league_id=l.id
    JOIN teams ht  ON g.home_team_id=ht.id JOIN teams at ON g.away_team_id=at.id
    WHERE g.status='completed' ORDER BY g.updated_at DESC LIMIT 5
")->fetchAll();
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub">Multi-league management overview</p>
    </div>
    <a href="<?= ADMIN_URL ?>/leagues/create.php" class="btn btn-brand btn-sm">
        <i class="fas fa-plus"></i> New League
    </a>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Leagues',  $stats['leagues'], 'fa-trophy',        '#f59e0b','#fffbeb','#92400e'],
        ['Seasons',  $stats['seasons'], 'fa-calendar-days', '#3b82f6','#dbeafe','#1d4ed8'],
        ['Teams',    $stats['teams'],   'fa-shield-halved', '#8b5cf6','#ede9fe','#6d28d9'],
        ['Players',  $stats['players'], 'fa-person-running','#10b981','#d1fae5','#065f46'],
    ];
    foreach ($cards as [$label,$val,$icon,$color,$bg,$text]):
    ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:<?=$color?>;opacity:.5;border-radius:var(--r-lg) var(--r-lg) 0 0;"></div>
            <div class="stat-icon" style="background:<?=$bg?>;color:<?=$text?>;">
                <i class="fas <?=$icon?>"></i>
            </div>
            <div class="stat-value"><?= $val ?></div>
            <div class="stat-label"><?= $label ?></div>
            <?php if ($label === 'Seasons' && $stats['active'] > 0): ?>
                <div class="stat-trend" style="color:#16a34a;">
                    <i class="fas fa-circle" style="font-size:6px;animation:blink 1.8s ease infinite;"></i>
                    <?= $stats['active'] ?> active
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Leagues heading -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 style="font-size:15px;font-weight:700;margin:0;letter-spacing:-.01em;">Leagues</h2>
    <a href="<?= ADMIN_URL ?>/leagues/index.php" style="font-size:12px;color:var(--text-3);font-weight:600;">
        View all <i class="fas fa-arrow-right fa-xs"></i>
    </a>
</div>

<?php if (empty($leagues)): ?>
<div class="card">
    <div class="card-body empty-state">
        <div class="empty-state-icon"><i class="fas fa-trophy"></i></div>
        <h5>No leagues yet</h5>
        <p>Create your first league to start managing teams, players and seasons.</p>
        <a href="<?= ADMIN_URL ?>/leagues/create.php" class="btn btn-brand">
            <i class="fas fa-plus"></i> Create League
        </a>
    </div>
</div>
<?php else: ?>
<div class="row g-3 mb-4">
    <?php foreach ($leagues as $l): ?>
    <div class="col-sm-6 col-xl-4">
        <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $l['id'] ?>" class="league-card">
            <div class="d-flex align-items-start gap-3 mb-3">
                <?php if (!empty($l['logo'])): ?>
                    <img src="<?= UPLOADS_URL.'/'.$l['logo'] ?>"
                         style="width:48px;height:48px;border-radius:var(--r);object-fit:cover;flex-shrink:0;" alt="">
                <?php else: ?>
                    <div class="league-avatar"><?= strtoupper(substr($l['name'],0,1)) ?></div>
                <?php endif; ?>
                <div style="min-width:0;">
                    <div style="font-size:14px;font-weight:700;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($l['name']) ?></div>
                    <?php if ($l['active_season']): ?>
                        <span class="badge-active"><?= clean($l['active_season']) ?></span>
                    <?php else: ?>
                        <span style="font-size:11px;color:var(--text-3);font-weight:500;">No active season</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="display:flex;gap:20px;padding-top:12px;border-top:1px solid var(--surface-3);margin-top:4px;">
                <?php foreach ([[$l['team_count'],'Teams'],[$l['player_count'],'Players'],[$l['season_count'],'Seasons']] as [$v,$lb]): ?>
                <div>
                    <div style="font-size:18px;font-weight:800;line-height:1;"><?= $v ?></div>
                    <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-top:3px;"><?= $lb ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
    <div class="col-sm-6 col-xl-4">
        <a href="<?= ADMIN_URL ?>/leagues/create.php" class="league-card-new">
            <div style="text-align:center;">
                <i class="fas fa-plus" style="font-size:22px;display:block;margin-bottom:8px;"></i>
                <span style="font-size:13px;font-weight:600;">New League</span>
            </div>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Recent Games -->
<?php if (!empty($recentGames)): ?>
<div class="card">
    <div class="card-header"><h3>Recent Games</h3></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>League</th>
                    <th>Season</th>
                    <th>Home</th>
                    <th class="text-center">Score</th>
                    <th>Away</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentGames as $g): ?>
            <tr>
                <td style="color:var(--text-3);"><?= clean($g['league_name']) ?></td>
                <td style="color:var(--text-3);"><?= clean($g['season_name']) ?></td>
                <td style="font-weight:<?= $g['home_score']>$g['away_score']?'700':'500' ?>;"><?= clean($g['home_team']) ?></td>
                <td class="text-center">
                    <span style="font-weight:800;font-variant-numeric:tabular-nums;">
                        <?= $g['home_score'] ?> <span style="color:var(--text-4);">—</span> <?= $g['away_score'] ?>
                    </span>
                </td>
                <td style="font-weight:<?= $g['away_score']>$g['home_score']?'700':'500' ?>;"><?= clean($g['away_team']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
