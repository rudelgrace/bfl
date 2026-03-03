<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
$seasonId = intGet('id');
$season = requireSeason($seasonId);
$league = requireLeague($season['league_id']);

// Completed season: redirect to view (read-only)
if ($season['status'] === 'completed') {
    setFlash('info', 'This season is completed and locked. No settings can be changed.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

$pdo = getDB();

$errors = [];
$values = [
    'name'       => $season['name'],
    'start_date' => $season['start_date'],
    'end_date'   => $season['end_date'],
];

// Process POST BEFORE any output (header.php) to allow redirect()
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name']       = trim(post('name'));
    $values['start_date'] = trim(post('start_date'));
    $values['end_date']   = trim(post('end_date'));

    if (empty($values['name'])) {
        $errors[] = 'Season name is required.';
    }

    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare('
                UPDATE seasons SET name = ?, start_date = ?, end_date = ? WHERE id = ?
            ');
            $updateStmt->execute([
                $values['name'],
                $values['start_date'] ?: null,
                $values['end_date']   ?: null,
                $seasonId
            ]);

            setFlash('success', 'Season updated successfully!');
            redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$leagueContext = $league;
$activeSidebar = 'seasons';
$activeNav     = 'leagues';
$pageTitle     = 'Season Settings';

require_once __DIR__ . '/../../includes/header.php';

// Get MVP candidates (players in this season) — used for read-only display
// Note: $pdo is already initialized above (before header.php)
$mvpCandidatesStmt = $pdo->prepare('
    SELECT p.id, p.first_name, p.last_name, t.name as team_name
    FROM players p
    JOIN season_rosters sr ON p.id = sr.player_id
    JOIN teams t ON sr.team_id = t.id
    WHERE sr.season_id = ?
    ORDER BY p.last_name, p.first_name
');
$mvpCandidatesStmt->execute([$seasonId]);
$mvpCandidates = $mvpCandidatesStmt->fetchAll();

// Helper: find MVP name by id
function findMvpName(array $candidates, ?int $id): string {
    if (!$id) return '—';
    foreach ($candidates as $c) {
        if ((int)$c['id'] === $id) {
            return htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['team_name'] . ')', ENT_QUOTES);
        }
    }
    return '—';
}

// Get season statistics
$statsStmt = $pdo->prepare('
    SELECT 
        COUNT(DISTINCT g.id) as total_games,
        SUM(CASE WHEN g.status = "completed" THEN 1 ELSE 0 END) as completed_games,
        COUNT(DISTINCT sr.player_id) as total_players
    FROM games g
    LEFT JOIN season_rosters sr ON g.season_id = sr.season_id
    WHERE g.season_id = ?
');
$statsStmt->execute([$seasonId]);
$stats = $statsStmt->fetch();
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $league['id'] ?>"><?= clean($league['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/seasons/index.php?league_id=<?= $league['id'] ?>">Seasons</a>
        </li>
        <li class="breadcrumb-item active"><?= clean($season['name']) ?> - Settings</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Season Settings</h1>
        <p class="page-sub"><?= clean($season['name']) ?></p>
    </div>
    <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Season
    </a>
</div>

<div class="row g-4">
    <!-- Settings Form -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3>Season Configuration</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" style="border-radius:10px;font-size:13px;">
                        <?php foreach ($errors as $e): ?>
                            <div><?= clean($e) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Editable fields -->
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label">Season Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= clean($values['name']) ?>" placeholder="e.g. Summer League 2025">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control"
                                   value="<?= clean($values['start_date']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control"
                                   value="<?= clean($values['end_date']) ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-brand">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                        <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>"
                           class="btn btn-light">Cancel</a>
                    </div>
                </form>

                <!-- Read-only fields (locked after season start) -->
                <hr style="margin:28px 0 20px;border-color:var(--border);">
                <p style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:16px;">
                    <i class="fas fa-lock fa-xs me-1"></i> Locked Settings
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label" style="font-size:12px;color:var(--text-3);">Status</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= ucfirst(clean($season['status'])) ?>"
                               style="background:var(--surface-2);color:var(--text-3);cursor:not-allowed;">
                    </div>
                    <div class="col-3">
                        <label class="form-label" style="font-size:12px;color:var(--text-3);">Games / Team</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= (int)$season['games_per_team'] ?>"
                               style="background:var(--surface-2);color:var(--text-3);cursor:not-allowed;">
                    </div>
                    <div class="col-3">
                        <label class="form-label" style="font-size:12px;color:var(--text-3);">Playoff Teams</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= (int)$season['playoff_teams_count'] ?>"
                               style="background:var(--surface-2);color:var(--text-3);cursor:not-allowed;">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" style="font-size:12px;color:var(--text-3);">Regular Season MVP</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= findMvpName($mvpCandidates, $season['regular_season_mvp_id'] ? (int)$season['regular_season_mvp_id'] : null) ?>"
                               style="background:var(--surface-2);color:var(--text-3);cursor:not-allowed;">
                    </div>
                    <div class="col-6">
                        <label class="form-label" style="font-size:12px;color:var(--text-3);">Playoffs MVP</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= findMvpName($mvpCandidates, $season['playoffs_mvp_id'] ? (int)$season['playoffs_mvp_id'] : null) ?>"
                               style="background:var(--surface-2);color:var(--text-3);cursor:not-allowed;">
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Season Statistics -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3>Season Statistics</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div style="font-size:24px;font-weight:800;color:var(--brand);"><?= $stats['total_games'] ?></div>
                    <div style="font-size:12px;color:var(--text-3);font-weight:600;">Total Games</div>
                </div>
                <div class="mb-3">
                    <div style="font-size:24px;font-weight:800;color:#22c55e;"><?= $stats['completed_games'] ?></div>
                    <div style="font-size:12px;color:var(--text-3);font-weight:600;">Completed Games</div>
                </div>
                <div class="mb-3">
                    <div style="font-size:24px;font-weight:800;color:#6366f1;"><?= $stats['total_players'] ?></div>
                    <div style="font-size:12px;color:var(--text-3);font-weight:600;">Total Players</div>
                </div>

                <div class="mt-4 pt-3 border-top">
                    <h5 style="font-size:14px;font-weight:700;margin-bottom:12px;">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="<?= ADMIN_URL ?>/seasons/edit_matchups.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-edit me-1"></i> Edit Matchups
                        </a>
                        <a href="<?= ADMIN_URL ?>/seasons/roster.php?season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-users me-1"></i> Manage Rosters
                        </a>
                        <?php if ($season['status'] === 'upcoming'): ?>
                            <a href="<?= ADMIN_URL ?>/seasons/generate_schedule.php?season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-calendar me-1"></i> Generate Schedule
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
