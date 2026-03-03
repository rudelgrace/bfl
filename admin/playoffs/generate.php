<?php
/**
 * The Battle 3x3 — Admin
 * admin/playoffs/generate.php
 *
 * Generates or resets the playoff bracket for a season.
 * Delegates all bracket logic to PlayoffService (concurrency-safe).
 *
 * Access: Admin / Super-Admin only. Scorers are blocked.
 */

require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
requireAuth();

$seasonId = intGet('season_id');
$leagueId = intGet('league_id');
$season   = requireSeason($seasonId);
$league   = requireLeague($leagueId ?: $season['league_id']);

// Only allow in active or playoffs state
if (!in_array($season['status'], ['active', 'playoffs'])) {
    setFlash('error', 'Playoff bracket can only be generated for an active season.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id']);
}

try {
    $gamesCreated = generatePlayoffBracket($seasonId);
    $bracketType  = ($gamesCreated === 7) ? '8-team' : '4-team';

    setFlash(
        'success',
        "Playoff bracket generated! {$gamesCreated} games created ({$bracketType} single elimination). \u{1F3C6}"
    );

} catch (RuntimeException $e) {
    setFlash('error', 'Could not generate bracket: ' . $e->getMessage());
}

redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $league['id'] . '&tab=playoffs');
