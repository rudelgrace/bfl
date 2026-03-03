<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdminRole();
requireAuth();
$id  = intGet('id');
$lid = intGet('league_id');
$s   = requireSeason($id);
getDB()->prepare('DELETE FROM seasons WHERE id = ?')->execute([$id]);
setFlash('success', "Season '{$s['name']}' deleted.");
redirect(ADMIN_URL . '/seasons/index.php?league_id=' . ($lid ?: $s['league_id']));
