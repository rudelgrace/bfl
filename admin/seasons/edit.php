<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
$seasonId = intGet('id');
$season = requireSeason($seasonId);
$league = requireLeague($season['league_id']);
$leagueContext = $league;
$activeSidebar = 'seasons';
$activeNav = 'leagues';
$pageTitle = 'Edit Season';

// Prevent editing completed seasons
if (in_array($season['status'], ['playoffs', 'completed'])) {
    setFlash('error', 'Cannot edit season in ' . $season['status'] . ' status.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $season['league_id']);
}

$pdo = getDB();

// Get teams in this season
$teamsStmt = $pdo->prepare('SELECT t.* FROM teams t 
    JOIN season_teams st ON t.id = st.team_id 
    WHERE st.season_id = ? ORDER BY t.name');
$teamsStmt->execute([$seasonId]);
$seasonTeams = $teamsStmt->fetchAll();

// Get all teams in league for potential additions
$allTeamsStmt = $pdo->prepare('SELECT * FROM teams WHERE league_id = ? ORDER BY name');
$allTeamsStmt->execute([$league['id']]);
$allTeams = $allTeamsStmt->fetchAll();

// Get current season team IDs
$seasonTeamIds = array_column($seasonTeams, 'id');

$errors = [];
$values = [
    'name' => $season['name'],
    'start_date' => $season['start_date'],
    'end_date' => $season['end_date'],
    'status' => $season['status'],
    'games_per_team' => $season['games_per_team'],
    'playoff_teams_count' => $season['playoff_teams_count'],
    'regular_season_mvp_id' => $season['regular_season_mvp_id'],
    'playoffs_mvp_id' => $season['playoffs_mvp_id'],
    'selected_teams' => $seasonTeamIds
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['name'] = trim(post('name'));
    $values['start_date'] = trim(post('start_date'));
    $values['end_date'] = trim(post('end_date'));
    $values['status'] = post('status');
    $values['games_per_team'] = intPost('games_per_team', 4);
    $values['playoff_teams_count'] = intPost('playoff_teams_count', 4);
    $values['regular_season_mvp_id'] = intPost('regular_season_mvp_id') ?: null;
    $values['playoffs_mvp_id'] = intPost('playoffs_mvp_id') ?: null;
    $values['selected_teams'] = array_map('intval', $_POST['teams'] ?? []);

    if (empty($values['name'])) {
        $errors[] = 'Season name is required.';
    }
    if (!empty($values['name'])) {
        $dup = $pdo->prepare('SELECT id FROM seasons WHERE league_id = ? AND LOWER(name) = LOWER(?) AND id != ?');
        $dup->execute([$league['id'], $values['name'], $seasonId]);
        if ($dup->fetch()) $errors[] = 'A season named "' . htmlspecialchars($values['name'], ENT_QUOTES) . '" already exists in this league. Please choose a different name.';
    }
    if (count($values['selected_teams']) < 4) {
        $errors[] = 'Select at least 4 teams.';
    }
    if ($values['games_per_team'] < 1 || $values['games_per_team'] > 20) {
        $errors[] = 'Games per team must be 1–20.';
    }
    if ($values['playoff_teams_count'] > count($values['selected_teams'])) {
        $errors[] = 'Playoff teams cannot exceed selected teams.';
    }

    // Check if season can be edited (no games played yet)
    if ($season['status'] === 'active') {
        $gamesCheck = $pdo->prepare('SELECT COUNT(*) FROM games WHERE season_id = ? AND status = "completed"');
        $gamesCheck->execute([$seasonId]);
        $completedGames = $gamesCheck->fetchColumn();
        
        if ($completedGames > 0) {
            $errors[] = 'Cannot edit season with completed games.';
        }
    }

    if (empty($errors)) {
        try {
            // Update season
            $updateStmt = $pdo->prepare('
                UPDATE seasons SET 
                    name = ?, start_date = ?, end_date = ?, status = ?, 
                    games_per_team = ?, playoff_teams_count = ?,
                    regular_season_mvp_id = ?, playoffs_mvp_id = ?
                WHERE id = ?
            ');
            $updateStmt->execute([
                $values['name'],
                $values['start_date'] ?: null,
                $values['end_date'] ?: null,
                $values['status'],
                $values['games_per_team'],
                $values['playoff_teams_count'],
                $values['regular_season_mvp_id'] ?: null,
                $values['playoffs_mvp_id'] ?: null,
                $seasonId
            ]);

            // Update season teams
            $pdo->prepare('DELETE FROM season_teams WHERE season_id = ?')->execute([$seasonId]);
            $insertTeamStmt = $pdo->prepare('INSERT INTO season_teams (season_id, team_id) VALUES (?,?)');
            foreach ($values['selected_teams'] as $teamId) {
                $insertTeamStmt->execute([$seasonId, $teamId]);
            }

            // Update standings if season hasn't started
            if ($season['status'] === 'upcoming') {
                $pdo->prepare('DELETE FROM standings WHERE season_id = ?')->execute([$seasonId]);
                $insertStandingStmt = $pdo->prepare('INSERT INTO standings (season_id, team_id) VALUES (?,?)');
                foreach ($values['selected_teams'] as $teamId) {
                    $insertStandingStmt->execute([$seasonId, $teamId]);
                }
            }

            setFlash('success', "Season '{$values['name']}' updated successfully!");
            redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';

// Get MVP candidates (players in this season)
$mvpCandidatesStmt = $pdo->prepare('
    SELECT p.id, p.first_name, p.last_name, t.name as team_name
    FROM players p
    LEFT JOIN season_rosters sr ON p.id = sr.player_id AND sr.season_id = ?
    LEFT JOIN teams t ON sr.team_id = t.id
    WHERE p.league_id = ?
    ORDER BY p.last_name, p.first_name
');
$mvpCandidatesStmt->execute([$seasonId, $league['id']]);
$mvpCandidates = $mvpCandidatesStmt->fetchAll();
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $league['id'] ?>"><?= clean($league['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/seasons/index.php?league_id=<?= $league['id'] ?>">Seasons</a>
        </li>
        <li class="breadcrumb-item active">Edit Season</li>
    </ol>
</nav>

<div style="max-width: 800px">
    <h1 class="h4 fw-bold mb-4">Edit Season</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="border-radius: 10px; font-size: 13px">
            <?php foreach ($errors as $e): ?>
                <div><?= clean($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Season Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           value="<?= clean($values['name']) ?>" placeholder="e.g. Summer League 2025">
                </div>

                <div class="row g-3 mb-3">
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

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="upcoming" <?= $values['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                        <option value="active" <?= $values['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $values['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Games Per Team</label>
                        <input type="number" name="games_per_team" class="form-control" 
                               min="1" max="20" value="<?= $values['games_per_team'] ?>">
                        <div class="form-text">Regular season games per team</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Playoff Teams</label>
                        <select name="playoff_teams_count" class="form-select">
                            <?php foreach ([2, 4, 6, 8] as $n): ?>
                                <option value="<?= $n ?>" <?= $values['playoff_teams_count'] == $n ? 'selected' : '' ?>>
                                    <?= $n ?> teams
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label">Regular Season MVP</label>
                        <select name="regular_season_mvp_id" class="form-select">
                            <option value="">Select MVP</option>
                            <?php foreach ($mvpCandidates as $player): ?>
                                <option value="<?= $player['id'] ?>" 
                                        <?= $values['regular_season_mvp_id'] == $player['id'] ? 'selected' : '' ?>>
                                    <?= clean($player['first_name'] . ' ' . $player['last_name']) ?> 
                                    (<?= clean($player['team_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Playoffs MVP</label>
                        <select name="playoffs_mvp_id" class="form-select">
                            <option value="">Select MVP</option>
                            <?php foreach ($mvpCandidates as $player): ?>
                                <option value="<?= $player['id'] ?>" 
                                        <?= $values['playoffs_mvp_id'] == $player['id'] ? 'selected' : '' ?>>
                                    <?= clean($player['first_name'] . ' ' . $player['last_name']) ?> 
                                    (<?= clean($player['team_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Select Teams <span class="text-danger">*</span></label>
                    <div class="form-text mb-2">Choose teams for this season (minimum 4)</div>
                    <div class="row g-2">
                        <?php foreach ($allTeams as $team): ?>
                            <div class="col-6">
                                <label class="d-flex align-items-center gap-2 p-3 border rounded-3 cursor-pointer" 
                                       style="cursor: pointer; transition: all .15s" 
                                       onmouseover="this.style.borderColor='#f97316';this.style.background='#fff7ed'" 
                                       onmouseout="this.style.borderColor='';this.style.background=''">
                                    <input type="checkbox" name="teams[]" value="<?= $team['id'] ?>" 
                                           class="form-check-input mt-0" style="accent-color: #f97316" 
                                           <?= in_array($team['id'], $values['selected_teams']) ? 'checked' : '' ?>>
                                    <?php if (!empty($team['logo'])): ?>
                                        <img src="<?= UPLOADS_URL . '/' . $team['logo'] ?>" 
                                             style="width: 28px; height: 28px; border-radius: 6px; object-fit: cover; flex-shrink: 0" alt="">
                                    <?php else: ?>
                                        <div style="width: 28px; height: 28px; border-radius: 6px; background: #e2e8f0; color: #64748b; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0">
                                            <?= strtoupper(substr($team['name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <span style="font-size: 13px; font-weight: 500"><?= clean($team['name']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
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
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
