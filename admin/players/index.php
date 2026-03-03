<?php
require_once __DIR__ . '/../../includes/functions.php';
if (isScorer()) { redirect(ADMIN_URL . '/scorer/index.php'); }
$leagueId=intGet('league_id'); $league=requireLeague($leagueId);
$leagueContext=$league; $activeSidebar='players'; $activeNav='leagues'; $pageTitle='Players';
require_once __DIR__ . '/../../includes/header.php';

// Search functionality
$search = trim(get('search', ''));
$page = max(1, intGet('page', 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$pdo = getDB();

// Get total count for pagination
if (!empty($search)) {
    $countStmt = $pdo->prepare('
        SELECT COUNT(*) FROM players 
        WHERE league_id = ? AND (first_name LIKE ? OR last_name LIKE ?)
    ');
    $searchTerm = '%' . $search . '%';
    $countStmt->execute([$leagueId, $searchTerm, $searchTerm]);
} else {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM players WHERE league_id = ?');
    $countStmt->execute([$leagueId]);
}
$totalPlayers = $countStmt->fetchColumn();
$totalPages = ceil($totalPlayers / $perPage);

// Get paginated results
if (!empty($search)) {
    $playersStmt = $pdo->prepare('
        SELECT * FROM players 
        WHERE league_id = ? AND (first_name LIKE ? OR last_name LIKE ?)
        ORDER BY last_name, first_name
        LIMIT ? OFFSET ?
    ');
    $playersStmt->execute([$leagueId, $searchTerm, $searchTerm, $perPage, $offset]);
} else {
    $playersStmt = $pdo->prepare('
        SELECT * FROM players 
        WHERE league_id = ? 
        ORDER BY last_name, first_name
        LIMIT ? OFFSET ?
    ');
    $playersStmt->execute([$leagueId, $perPage, $offset]);
}
$players = $playersStmt->fetchAll();
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $leagueId ?>"><?= clean($league['name']) ?></a></li>
        <li class="breadcrumb-item active">Players</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 style="font-size:22px;font-weight:800;letter-spacing:-.025em;margin:0;">Players</h1>
        <p style="font-size:13px;color:var(--text-3);margin:3px 0 0;">
    Showing <?= count($players) ?> of <?= $totalPlayers ?> players in <?= clean($league['name']) ?><?php if(!empty($search)): ?> matching "<?= clean($search) ?>"<?php endif; ?>
</p>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="league_id" value="<?= $leagueId ?>">
            <input type="text" name="search" class="form-control" placeholder="Search players..." value="<?= clean($search) ?>" style="min-width: 200px;">
            <button type="submit" class="btn btn-outline-secondary">
                <i class="fas fa-search"></i>
            </button>
        </form>
        <a href="<?= ADMIN_URL ?>/players/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand btn-sm">
            <i class="fas fa-plus"></i> New Player
        </a>
    </div>
</div>

<?php if(empty($players)): ?>
<div class="card">
    <div class="card-body empty-state">
        <div class="empty-state-icon"><i class="fas fa-person-running"></i></div>
        <h5><?php if(!empty($search)): ?>No players found<?php else: ?>No players yet<?php endif; ?></h5>
        <p><?php if(!empty($search)): ?>No players matching "<?= clean($search) ?>" were found.<?php else: ?>Register players to this league. They'll be assigned to teams per season.<?php endif; ?></p>
        <?php if(!empty($search)): ?>
        <a href="<?= ADMIN_URL ?>/players/index.php?league_id=<?= $leagueId ?>" class="btn btn-light">Clear Search</a>
        <?php else: ?>
        <a href="<?= ADMIN_URL ?>/players/create.php?league_id=<?= $leagueId ?>" class="btn btn-brand">
            <i class="fas fa-plus"></i> Add First Player
        </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Position</th>
                    <th>Height</th>
                    <th>Date of Birth</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($players as $p): ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-3">
                        <?php if(!empty($p['photo'])): ?>
                            <img src="<?= UPLOADS_URL.'/'.$p['photo'] ?>"
                                 style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="">
                        <?php else: ?>
                            <div class="player-avatar"><?= strtoupper(substr($p['first_name'],0,1)) ?></div>
                        <?php endif; ?>
                        <span style="font-weight:600;"><?= clean($p['first_name'].' '.$p['last_name']) ?></span>
                    </div>
                </td>
                <td style="color:var(--text-3);"><?= clean($p['position']??'—') ?></td>
                <td style="color:var(--text-3);"><?= clean($p['height']??'—') ?></td>
                <td style="color:var(--text-3);"><?= formatDate($p['date_of_birth']) ?></td>
                <td class="text-end">
                    <a href="<?= ADMIN_URL ?>/players/view.php?id=<?= $p['id'] ?>&league_id=<?= $leagueId ?>"
                       class="btn btn-sm btn-outline-secondary me-1">
                        <i class="fas fa-eye fa-xs"></i> View
                    </a>
                    <a href="<?= ADMIN_URL ?>/players/edit.php?id=<?= $p['id'] ?>&league_id=<?= $leagueId ?>"
                       class="btn btn-light btn-sm me-1">
                        <i class="fas fa-pen fa-xs"></i> Edit
                    </a>
                    <a href="<?= ADMIN_URL ?>/players/delete.php?id=<?= $p['id'] ?>&league_id=<?= $leagueId ?>"
                       data-confirm="Delete player '<?= clean($p['first_name'].' '.$p['last_name']) ?>'?"
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

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="d-flex justify-content-center mt-4">
    <nav>
        <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?league_id=<?= $leagueId ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?league_id=<?= $leagueId ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?league_id=<?= $leagueId ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
