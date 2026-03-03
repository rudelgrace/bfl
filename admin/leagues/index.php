<?php
$pageTitle='Leagues'; $activeNav='leagues'; $leagueContext=null;
require_once __DIR__ . '/../../includes/functions.php';
if (isScorer()) { redirect(ADMIN_URL . '/scorer/index.php'); }
require_once __DIR__ . '/../../includes/header.php';
$pdo=getDB();
$leagues=$pdo->query("SELECT l.*,
    (SELECT COUNT(*) FROM teams   WHERE league_id=l.id) as team_count,
    (SELECT COUNT(*) FROM players WHERE league_id=l.id) as player_count,
    (SELECT COUNT(*) FROM seasons WHERE league_id=l.id) as season_count,
    (SELECT COUNT(*) FROM seasons WHERE league_id=l.id AND status='active') as active_seasons
    FROM leagues l ORDER BY l.created_at DESC")->fetchAll();
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.025em;margin:0;">Leagues</h1>
        <p style="font-size:13px;color:var(--text-3);margin:3px 0 0;"><?= count($leagues) ?> league<?= count($leagues)!==1?'s':'' ?></p>
    </div>
    <a href="<?= ADMIN_URL ?>/leagues/create.php" class="btn btn-brand btn-sm">
        <i class="fas fa-plus"></i> New League
    </a>
</div>

<?php if(empty($leagues)): ?>
<div class="card">
    <div class="card-body empty-state">
        <div class="empty-state-icon"><i class="fas fa-trophy"></i></div>
        <h5>No leagues yet</h5>
        <p>Create your first league to start managing teams, players and seasons.</p>
        <a href="<?= ADMIN_URL ?>/leagues/create.php" class="btn btn-brand">
            <i class="fas fa-plus"></i> Create First League
        </a>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach($leagues as $l): ?>
    <div class="col-sm-6 col-xl-4">
        <div class="card h-100" style="overflow:visible;">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <?php if(!empty($l['logo'])): ?>
                            <img src="<?= UPLOADS_URL.'/'.$l['logo'] ?>"
                                 style="width:48px;height:48px;border-radius:var(--r);object-fit:cover;flex-shrink:0;" alt="">
                        <?php else: ?>
                            <div class="league-avatar"><?= strtoupper(substr($l['name'],0,1)) ?></div>
                        <?php endif; ?>
                        <div style="min-width:0;">
                            <div style="font-weight:700;font-size:14px;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= clean($l['name']) ?></div>
                            <?= $l['active_seasons']>0?'<span class="badge-active">Active Season</span>':'<span style="font-size:11px;color:var(--text-3);font-weight:500;">No active season</span>' ?>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm" data-bs-toggle="dropdown"
                                style="width:30px;height:30px;padding:0;border-radius:6px;flex-shrink:0;">
                            <i class="fas fa-ellipsis" style="font-size:12px;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $l['id'] ?>">
                                <i class="fas fa-chart-pie fa-sm"></i> Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= ADMIN_URL ?>/leagues/edit.php?id=<?= $l['id'] ?>">
                                <i class="fas fa-pen fa-sm"></i> Edit</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger"
                                   href="<?= ADMIN_URL ?>/leagues/delete.php?id=<?= $l['id'] ?>"
                                   data-confirm="Delete '<?= clean($l['name']) ?>'? All data will be permanently lost.">
                                <i class="fas fa-trash fa-sm"></i> Delete</a></li>
                        </ul>
                    </div>
                </div>
                <?php if(!empty($l['description'])): ?>
                    <p style="font-size:12px;color:var(--text-3);margin-bottom:12px;line-height:1.5;"><?= clean($l['description']) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:20px;padding:12px 0;border-top:1px solid var(--surface-3);border-bottom:1px solid var(--surface-3);margin-bottom:14px;">
                    <?php foreach([[$l['team_count'],'Teams'],[$l['player_count'],'Players'],[$l['season_count'],'Seasons']] as [$v,$lb]): ?>
                    <div>
                        <div style="font-size:18px;font-weight:800;line-height:1;"><?= $v ?></div>
                        <div style="font-size:10px;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;font-weight:600;margin-top:3px;"><?= $lb ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $l['id'] ?>"
                   class="btn btn-brand btn-sm w-100">
                    Open League <i class="fas fa-arrow-right fa-xs ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
