<?php
// start.php
require_once __DIR__ . '/../../includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(ADMIN_URL . '/seasons/index.php?league_id=' . intGet('league_id'));
}

$seasonId = intPost('season_id');
$leagueId = intPost('league_id');
$season   = requireSeason($seasonId);
$league   = requireLeague($leagueId ?: $season['league_id']);

// Verify season belongs to league
if ($season['league_id'] != $leagueId) {
    setFlash('error', 'Invalid season.');
    redirect(ADMIN_URL . '/seasons/index.php?league_id=' . $leagueId);
}

// ── Server-side roster validation ──────────────────────────────────────────
// Ensure every team in this season has at least 3 players on their roster
$pdo = getDB();

$rosterCheck = $pdo->prepare("
    SELECT t.name,
           COUNT(sr.player_id) AS roster_size
    FROM season_teams st
    JOIN teams t ON t.id = st.team_id
    LEFT JOIN season_rosters sr ON sr.team_id = st.team_id AND sr.season_id = st.season_id
    WHERE st.season_id = ?
    GROUP BY t.id, t.name
    HAVING roster_size < 3
");
$rosterCheck->execute([$seasonId]);
$teamsWithoutRosters = $rosterCheck->fetchAll();

if (!empty($teamsWithoutRosters)) {
    $names = array_map(fn($t) => $t['name'] . ' (' . $t['roster_size'] . ' player' . ($t['roster_size'] == 1 ? '' : 's') . ')', $teamsWithoutRosters);
    setFlash('error', 'Cannot start season — the following teams need at least 3 players on their roster: ' . implode(', ', $names) . '. Please fill all rosters before starting.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

// ── Also require a schedule to exist ───────────────────────────────────────
$gameCount = $pdo->prepare("SELECT COUNT(*) FROM games WHERE season_id = ? AND game_type = 'regular'");
$gameCount->execute([$seasonId]);
if ((int)$gameCount->fetchColumn() === 0) {
    setFlash('error', 'Cannot start season — no schedule has been generated yet. Please generate the schedule first.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

if (startSeason($seasonId)) {
    setFlash('success', "Season '{$season['name']}' is now active! Good luck! 🏀");
} else {
    setFlash('error', 'Failed to start season. It may have already been started.');
}

redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
