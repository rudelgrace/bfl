<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
$seasonId = intGet('id');
$season = requireSeason($seasonId);
$league = requireLeague($season['league_id']);
$leagueContext = $league;
$activeSidebar = 'seasons';
$activeNav = 'leagues';
$pageTitle = 'Edit Matchups';

require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

// Only allow editing if season hasn't started or no games are completed
if ($season['status'] === 'completed') {
    setFlash('error', 'This season is completed. Matchups cannot be edited.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

if ($season['status'] === 'active') {
    $gamesCheck = $pdo->prepare('SELECT COUNT(*) FROM games WHERE season_id = ? AND status = "completed"');
    $gamesCheck->execute([$seasonId]);
    $completedGames = $gamesCheck->fetchColumn();
    
    if ($completedGames > 0) {
        setFlash('error', 'Cannot edit matchups after games have been completed.');
        redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
    }
}

// Get all games for this season
$gamesStmt = $pdo->prepare('
    SELECT g.*, ht.name as home_team_name, at.name as away_team_name
    FROM games g
    INNER JOIN teams ht ON g.home_team_id = ht.id
    INNER JOIN teams at ON g.away_team_id = at.id
    WHERE g.season_id = ?
    ORDER BY g.game_date, g.game_time, g.id
');
$gamesStmt->execute([$seasonId]);
$games = $gamesStmt->fetchAll();

// Get all teams in this season
$teamsStmt = $pdo->prepare('
    SELECT t.id, t.name
    FROM teams t
    INNER JOIN season_teams st ON t.id = st.team_id
    WHERE st.season_id = ?
    ORDER BY t.name
');
$teamsStmt->execute([$seasonId]);
$teams = $teamsStmt->fetchAll();

$success = '';
$error = '';

// Handle game updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameId = intPost('game_id');
    $homeTeamId = intPost('home_team_id');
    $awayTeamId = intPost('away_team_id');
    $gameDate = trim(post('game_date'));
    $gameTime = trim(post('game_time'));
    
    if ($gameId === 0 || $homeTeamId === 0 || $awayTeamId === 0) {
        $error = 'Invalid game data';
    } elseif ($homeTeamId === $awayTeamId) {
        $error = 'Home and away teams cannot be the same';
    } elseif (empty($gameDate)) {
        $error = 'Game date is required';
    } else {
        // Check for duplicate matchup (same teams playing each other multiple times)
        $duplicateCheck = $pdo->prepare('
            SELECT COUNT(*) FROM games 
            WHERE season_id = ? AND id != ? AND 
            ((home_team_id = ? AND away_team_id = ?) OR (home_team_id = ? AND away_team_id = ?))
        ');
        $duplicateCheck->execute([$seasonId, $gameId, $homeTeamId, $awayTeamId, $awayTeamId, $homeTeamId]);
        $duplicateCount = $duplicateCheck->fetchColumn();
        
        if ($duplicateCount > 0) {
            $error = 'These teams already have a game scheduled';
        } else {
            try {
                $updateStmt = $pdo->prepare('
                    UPDATE games 
                    SET home_team_id = ?, away_team_id = ?, game_date = ?, game_time = ?
                    WHERE id = ? AND season_id = ?
                ');
                $updateStmt->execute([$homeTeamId, $awayTeamId, $gameDate, $gameTime ?: null, $gameId, $seasonId]);
                
                $success = 'Game updated successfully!';
                
                // Refresh games list
                $gamesStmt->execute([$seasonId]);
                $games = $gamesStmt->fetchAll();
                
            } catch (PDOException $e) {
                $error = 'Error updating game: ' . $e->getMessage();
            }
        }
    }
}

// Convert teams to associative array for easy lookup
$teamsArray = [];
foreach ($teams as $team) {
    $teamsArray[$team['id']] = $team['name'];
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/leagues/dashboard.php?id=<?= $league['id'] ?>"><?= clean($league['name']) ?></a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/seasons/index.php?league_id=<?= $league['id'] ?>">Seasons</a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>"><?= clean($season['name']) ?></a>
        </li>
        <li class="breadcrumb-item active">Edit Matchups</li>
    </ol>
</nav>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="page-title">Edit Matchups</h1>
        <p class="page-sub">Season: <?= clean($season['name']) ?> (<?= count($games) ?> games)</p>
    </div>
</div>

<div class="alert alert-info" style="border-radius: 10px; font-size: 13px">
    <i class="fas fa-info-circle me-2"></i> 
    <strong>Note:</strong> You can only edit matchups before games are completed. Make sure each team plays the required number of games.
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="border-radius: 10px; font-size: 13px">
        <?= clean($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger" style="border-radius: 10px; font-size: 13px">
        <?= clean($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Game Schedule</h3>
    </div>
    <div class="card-body">
        <?php if (empty($games)): ?>
            <div class="text-center py-4">
                <p class="text-muted mb-3">No games found. <a href="<?= ADMIN_URL ?>/seasons/generate_schedule.php?season_id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-brand btn-sm">Generate schedule first</a>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" id="gamesTable">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Game #</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Home Team</th>
                            <th class="text-center">vs</th>
                            <th>Away Team</th>
                            <th style="width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($games as $index => $game): ?>
                            <tr id="game-row-<?= $game['id'] ?>">
                                <td><?= $index + 1 ?></td>
                                <td><?= formatDate($game['game_date']) ?></td>
                                <td><?= $game['game_time'] ?: 'TBD' ?></td>
                                <td style="font-weight: 600;"><?= clean($game['home_team_name']) ?></td>
                                <td class="text-center" style="color: var(--text-3);">—</td>
                                <td style="font-weight: 600;"><?= clean($game['away_team_name']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-brand" onclick="editGame(<?= $game['id'] ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Game Modal -->
<div class="modal" id="editGameModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-edit"></i> Edit Game</h4>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" id="edit_game_id" name="game_id">
                
                <div class="mb-3">
                    <label for="edit_game_date" class="form-label">Game Date *</label>
                    <input type="date" id="edit_game_date" name="game_date" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label for="edit_game_time" class="form-label">Game Time</label>
                    <input type="time" id="edit_game_time" name="game_time" class="form-control">
                </div>
                
                <div class="mb-3">
                    <label for="edit_home_team" class="form-label">Home Team *</label>
                    <select id="edit_home_team" name="home_team_id" class="form-select" required>
                        <option value="">Select Home Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?= $team['id'] ?>"><?= clean($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="edit_away_team" class="form-label">Away Team *</label>
                    <select id="edit_away_team" name="away_team_id" class="form-select" required>
                        <option value="">Select Away Team</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?= $team['id'] ?>"><?= clean($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="alert alert-warning" style="border-radius: 10px; font-size: 12px">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Make sure teams don't play each other multiple times unless intended.
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-brand">
                    <i class="fas fa-save"></i> Update Game
                </button>
            </div>
        </form>
    </div>
</div>

<div class="mt-4">
    <a href="<?= ADMIN_URL ?>/seasons/view.php?id=<?= $seasonId ?>&league_id=<?= $league['id'] ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-1"></i> Back to Season
    </a>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    background: var(--surface);
    margin: 50px auto;
    padding: 0;
    width: 90%;
    max-width: 500px;
    border-radius: var(--r);
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid var(--border);
    text-align: right;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-3);
}
</style>

<script>
function editGame(gameId) {
    // Find the game data from the table
    const row = document.getElementById('game-row-' + gameId);
    const cells = row.getElementsByTagName('td');
    
    // Extract current values
    const dateText = cells[1].textContent;
    const timeText = cells[2].textContent;
    const homeTeamName = cells[3].textContent;
    const awayTeamName = cells[5].textContent;
    
    // Parse date (format from formatDate function)
    const dateObj = new Date(dateText);
    const dateStr = dateObj.toISOString().split('T')[0];
    
    // Set form values
    document.getElementById('edit_game_id').value = gameId;
    document.getElementById('edit_game_date').value = dateStr;
    document.getElementById('edit_game_time').value = timeText === 'TBD' ? '' : timeText;
    
    // Set team selections
    const homeSelect = document.getElementById('edit_home_team');
    const awaySelect = document.getElementById('edit_away_team');
    
    // Reset selections
    homeSelect.value = '';
    awaySelect.value = '';
    
    // Find and set home team
    for (let option of homeSelect.options) {
        if (option.textContent === homeTeamName) {
            homeSelect.value = option.value;
            break;
        }
    }
    
    // Find and set away team
    for (let option of awaySelect.options) {
        if (option.textContent === awayTeamName) {
            awaySelect.value = option.value;
            break;
        }
    }
    
    // Show modal
    document.getElementById('editGameModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editGameModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editGameModal');
    if (event.target === modal) {
        closeEditModal();
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
