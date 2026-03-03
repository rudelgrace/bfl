<?php
/**
 * The Battle 3x3 — Admin
 * admin/playoffs/reset.php
 *
 * Safely deletes all playoff games and regenerates the bracket
 * from the current regular-season standings.
 *
 * POST only. Admin / Super-Admin only.
 */

require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(ADMIN_URL . '/leagues/index.php');
}

$seasonId = intPost('season_id');
$leagueId = intPost('league_id');
$season   = requireSeason($seasonId);
$league   = requireLeague($leagueId ?: $season['league_id']);

if ($season['status'] !== 'playoffs') {
    setFlash('error', 'Bracket reset is only available when the season is in playoffs mode.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

try {
    $gamesCreated = resetPlayoffBracket($seasonId);
    $bracketType  = ($gamesCreated === 7) ? '8-team' : '4-team';

    setFlash(
        'success',
        "Playoff bracket reset and regenerated! {$gamesCreated} games created ({$bracketType}). \u{1F504}"
    );

} catch (RuntimeException $e) {
    setFlash('error', 'Reset failed: ' . $e->getMessage());
}

redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id'] . '&tab=playoffs');
