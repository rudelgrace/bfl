<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
requireAuth();

$id     = intGet('id');
$league = requireLeague($id);

// Delete logo file
deleteUpload($league['logo']);

// Delete all player photos and team logos in this league
$pdo = getDB();
$players = $pdo->prepare('SELECT photo FROM players WHERE league_id = ?');
$players->execute([$id]);
foreach ($players->fetchAll(PDO::FETCH_COLUMN) as $photo) deleteUpload($photo);

$teams = $pdo->prepare('SELECT logo FROM teams WHERE league_id = ?');
$teams->execute([$id]);
foreach ($teams->fetchAll(PDO::FETCH_COLUMN) as $logo) deleteUpload($logo);

// Cascade delete handled by FK constraints
$pdo->prepare('DELETE FROM leagues WHERE id = ?')->execute([$id]);

setFlash('success', "League '{$league['name']}' deleted.");
redirect(ADMIN_URL . '/leagues/index.php');
