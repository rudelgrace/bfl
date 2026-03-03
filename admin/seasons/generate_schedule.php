<?php
// generate_schedule.php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
requireAuth();

$seasonId = intGet('season_id');
$leagueId = intGet('league_id');
$season   = requireSeason($seasonId);
$league   = requireLeague($leagueId ?: $season['league_id']);

$pdo = getDB();

// Delete existing regular season games
$pdo->prepare("DELETE FROM games WHERE season_id = ? AND game_type = 'regular'")
    ->execute([$seasonId]);

// Get teams in this season
$teams = $pdo->prepare('SELECT team_id FROM season_teams WHERE season_id = ?');
$teams->execute([$seasonId]);
$teamIds = $teams->fetchAll(PDO::FETCH_COLUMN);

// Generate schedule
$matchups = generateSchedule($teamIds, $season['games_per_team']);

if (empty($matchups)) {
    setFlash('error', 'Could not generate schedule. Need at least 2 teams.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

// Insert games with sequential dates (start from today or season start)
$startDate = $season['start_date'] ?: date('Y-m-d');
$date      = new DateTime($startDate);
$gamesPerDay = 2; // adjust as needed
$dayCount  = 0;

$insert = $pdo->prepare('INSERT INTO games (season_id, home_team_id, away_team_id, game_date, status, game_type) VALUES (?,?,?,?,?,?)');

foreach ($matchups as $i => [$home, $away]) {
    if ($i > 0 && $i % $gamesPerDay === 0) {
        $date->modify('+7 days');
    }
    $insert->execute([$seasonId, $home, $away, $date->format('Y-m-d'), 'scheduled', 'regular']);
}

setFlash('success', count($matchups) . ' games scheduled successfully!');
redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
