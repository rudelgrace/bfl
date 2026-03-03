<?php
require_once __DIR__ . '/../../includes/functions.php';
if (isScorer()) { redirect(ADMIN_URL . '/scorer/index.php'); }
$leagueId=intGet('league_id'); $league=requireLeague($leagueId);
$leagueContext=$league; $activeSidebar='seasons'; $activeNav='leagues'; $pageTitle='Seasons';
require_once __DIR__ . '/../../includes/header.php';
$pdo=getDB();
$seasons=$pdo->prepare("SELECT s.*,
    (SELECT COUNT(*) FROM season_teams WHERE season_id=s.id) as team_count,
    (SELECT COUNT(*) FROM games WHERE season_id=s.id) as total_games,
    (SELECT COUNT(*) FROM games WHERE season_id=s.id AND status='completed') as played_games
    FROM seasons s WHERE s.league_id=? ORDER BY s.created_at DESC");
$seasons->execute([$leagueId]); $seasons=$seasons->fetchAll();
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a></li>
        <li class="breadcrumb-item active">Seasons</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.025em;margin:0;">Seasons</h1>
        <p style="font-size:13px;color:var(--text-3);margin:3px 0 0;"><?= count($seasons) ?> season<?= count($seasons)!==1?'s':'' ?></p>
    </div>
    <a href="<?= ADMIN_URL ?>/seasons/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand btn-sm">
        <i class="fas fa-plus"></i> New Season
    </a>
</div>

<?php if(empty($seasons)): ?>
<div class="card">
    <div class="card-body empty-state">
        <div class="empty-state-icon"><i class="fas fa-calendar-days"></i></div>
        <h5>No seasons yet</h5>
        <p>Create a season, assign teams, generate schedule, and start playing.</p>
        <a href="<?= ADMIN_URL ?>/seasons/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand"><i class="fas fa-plus"></i> Create First Season</a>
    </div>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
<?php foreach($seasons as $s):
    $pct=$s['total_games']>0?round(($s['played_games']/$s['total_games'])*100):0;
    $isDone = $s['status'] === 'completed';
    $isLocked = in_array($s['status'], ['playoffs','completed']);
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div style="width:46px;height:46px;border-radius:var(--r);background:<?= $isDone?'#d1fae5':($s['status']==='active'?'#fff7ed':'var(--surface-3)') ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-calendar-days" style="color:<?= $isDone?'#16a34a':($s['status']==='active'?'var(--brand)':'var(--text-3)') ?>;font-size:18px;"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span style="font-weight:700;font-size:14px;"><?= clean($s['name']) ?></span>
                        <?= seasonStatusBadge($s['status']) ?>
                    </div>
                    <div style="font-size:12px;color:var(--text-3);">
                        <?= $s['team_count'] ?> teams
                        &middot; <?= $s['played_games'] ?>/<?= $s['total_games'] ?> games
                        <?php if($s['start_date']): ?>&middot; <?= formatDate($s['start_date']) ?><?php endif; ?>
                        <?php if($isDone&&$s['end_date']): ?>&middot; Ended <?= formatDate($s['end_date']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <?php if($s['status']==='upcoming'): ?>
                <form method="POST" action="<?= ADMIN_URL ?>/seasons/start.php" class="d-inline">
                    <input type="hidden" name="season_id" value="<?= $s['id'] ?>">
                    <input type="hidden" name="league_id" value="<?= $leagueId ?>">
                    <button type="submit" class="btn btn-sm btn-success"
                            data-confirm="Start season &quot;<?= clean($s['name']) ?>&quot;?">
                        <i class="fas fa-play fa-xs"></i> Start
                    </button>
                </form>
                <a href="<?= ADMIN_URL ?>/seasons/edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit fa-xs"></i></a>
                <?php endif; ?>

                <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $s['id'] ?>&league_id=<?= $leagueId ?>"
                   class="btn <?= $isDone?'btn-light':'btn-brand' ?> btn-sm">
                    <?= $isDone?'<i class="fas fa-eye fa-xs me-1"></i>View':'Open' ?> <i class="fas fa-arrow-right fa-xs ms-1"></i>
                </a>

                <?php if($s['status']==='upcoming'): ?>
                <a href="<?= ADMIN_URL ?>/seasons/delete.php?id=<?= $s['id'] ?>&league_id=<?= $leagueId ?>"
                   data-confirm="Delete season &quot;<?= clean($s['name']) ?>&quot;? All data will be lost."
                   class="btn btn-sm btn-outline-danger"><i class="fas fa-trash fa-xs"></i></a>
                <?php endif; ?>
            </div>
        </div>

        <?php if($s['total_games']>0): ?>
        <div class="mt-3">
            <div class="d-flex justify-content-between mb-1" style="font-size:11px;font-weight:600;color:<?= $isDone?'#16a34a':'var(--text-3)' ?>;">
                <span>
                    <?php if($isDone): ?><i class="fas fa-circle-check me-1"></i>Season Complete
                    <?php elseif($s['status']==='playoffs'): ?><i class="fas fa-trophy me-1"></i>Playoffs
                    <?php elseif($s['status']==='active'): ?>In Progress
                    <?php else: ?>Upcoming<?php endif; ?>
                </span>
                <span><?= $pct ?>%</span>
            </div>
            <div class="tb-progress">
                <div class="tb-progress-bar <?= $isDone?'progress-complete':'' ?>"
                     style="width:<?= $pct ?>%;<?= $isDone?'background:linear-gradient(90deg,#22c55e,#16a34a);':''; $s['status']==='playoffs'?'background:linear-gradient(90deg,#f59e0b,#d97706);':'' ?>"></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
