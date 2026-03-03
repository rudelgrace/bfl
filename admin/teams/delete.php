<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
requireAuth();

$teamId   = intGet('id');
$leagueId = intGet('league_id');
$pdo      = getDB();

$stmt = $pdo->prepare('SELECT * FROM teams WHERE id = ? AND league_id = ?');
$stmt->execute([$teamId, $leagueId]);
$team = $stmt->fetch();

if ($team) {
    deleteUpload($team['logo']);
    $pdo->prepare('DELETE FROM teams WHERE id = ?')->execute([$teamId]);
    setFlash('success', "Team '{$team['name']}' deleted.");
} else {
    setFlash('error', 'Team not found.');
}

redirect(ADMIN_URL . '/teams/index.php?league_id=' . $leagueId);
