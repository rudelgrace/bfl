<?php
require_once __DIR__ . '/../../includes/functions.php';
if (isScorer()) { redirect(ADMIN_URL . '/scorer/index.php'); }
$leagueId=intGet('league_id'); $league=requireLeague($leagueId);
$leagueContext=$league; $activeSidebar='teams'; $activeNav='leagues'; $pageTitle='Teams';
require_once __DIR__ . '/../../includes/header.php';
$teams=getDB()->prepare('SELECT * FROM teams WHERE league_id=? ORDER BY name');
$teams->execute([$leagueId]); $teams=$teams->fetchAll();
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a></li>
        <li class="breadcrumb-item active">Teams</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.025em;margin:0;">Teams</h1>
        <p style="font-size:13px;color:var(--text-3);margin:3px 0 0;"><?= count($teams) ?> team<?= count($teams)!==1?'s':'' ?> in <?= clean($league['name']) ?></p>
    </div>
    <a href="<?= ADMIN_URL ?>/teams/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand btn-sm">
        <i class="fas fa-plus"></i> New Team
    </a>
</div>

<?php if(empty($teams)): ?>
<div class="card">
    <div class="card-body empty-state">
        <div class="empty-state-icon"><i class="fas fa-shield-halved"></i></div>
        <h5>No teams yet</h5>
        <p>Create teams for this league. You'll need at least 4 to run a season.</p>
        <a href="<?= ADMIN_URL ?>/teams/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand">
            <i class="fas fa-plus"></i> Create First Team
        </a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Team</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($teams as $t): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <?php if(!empty($t['logo'])): ?>
                            <img src="<?= UPLOADS_URL.'/'.$t['logo'] ?>"
                                 style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0;" alt="">
                        <?php else: ?>
                            <div class="team-avatar"><?= strtoupper(substr($t['name'],0,1)) ?></div>
                        <?php endif; ?>
                        <span style="font-weight:600;"><?= clean($t['name']) ?></span>
                    </div>
                </td>
                <td class="text-end">
                    <a href="<?= ADMIN_URL ?>/teams/view.php?id=<?= $t['id'] ?>&league_id=<?= $leagueId ?>"
                       class="btn btn-sm btn-outline-secondary me-1">
                        <i class="fas fa-eye fa-xs"></i> View
                    </a>
                    <a href="<?= ADMIN_URL ?>/teams/edit.php?id=<?= $t['id'] ?>&league_id=<?= $leagueId ?>"
                       class="btn btn-light btn-sm me-1">
                        <i class="fas fa-pen fa-xs"></i> Edit
                    </a>
                    <a href="<?= ADMIN_URL ?>/teams/delete.php?id=<?= $t['id'] ?>&league_id=<?= $leagueId ?>"
                       data-confirm="Delete team '<?= clean($t['name']) ?>'? This cannot be undone."
                       class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash fa-xs"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
