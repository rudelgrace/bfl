<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
requireAuth();
$id = intGet('id'); $lid = intGet('league_id');
$stmt = getDB()->prepare('SELECT * FROM players WHERE id = ? AND league_id = ?');
$stmt->execute([$id, $lid]);
$p = $stmt->fetch();
if ($p) { deleteUpload($p['photo']); getDB()->prepare('DELETE FROM players WHERE id = ?')->execute([$id]); setFlash('success','Player deleted.'); }
else setFlash('error','Player not found.');
redirect(ADMIN_URL . '/players/index.php?league_id=' . $lid);
