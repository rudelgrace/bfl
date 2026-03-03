<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAuth();

$gameId   = intPost('game_id');
$leagueId = intPost('league_id');
$seasonId = intPost('season_id');
$newDate  = trim($_POST['game_date'] ?? '');

$pdo = getDB();

$stmt = $pdo->prepare('SELECT g.*, s.status as season_status FROM games g JOIN seasons s ON g.season_id = s.id WHERE g.id = ?');
$stmt->execute([$gameId]);
$game = $stmt->fetch();

if (!$game || $game['season_status'] === 'completed') {
    setFlash('error', 'Cannot update this game.');
    redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $leagueId);
}

if ($newDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
    $pdo->prepare('UPDATE games SET game_date = ? WHERE id = ?')->execute([$newDate, $gameId]);
    setFlash('success', 'Game date updated.');
} else {
    setFlash('error', 'Invalid date.');
}

redirect(ADMIN_URL . '/seasons/view.php?id=' . $seasonId . '&league_id=' . $leagueId);
