<?php
/**
 * Battle 3x3 — Public API
 * GET /api/game.php?id=
 * Full game detail with player stats
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo = Database::getInstance();
    $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Missing id']); exit; }

    $stmt = $pdo->prepare('
        SELECT g.*, s.name AS season_name, l.name AS league_name,
               ht.name AS home_team, ht.logo AS home_logo,
               at.name AS away_team, at.logo AS away_logo
        FROM games g
        JOIN seasons s ON g.season_id    = s.id
        JOIN leagues l ON s.league_id    = l.id
        JOIN teams ht  ON g.home_team_id = ht.id
        JOIN teams at  ON g.away_team_id = at.id
        WHERE g.id = ?
    ');
    $stmt->execute([$id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$game) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Game not found']); exit; }
    $game['home_logo'] = $game['home_logo'] ? UPLOADS_URL . '/' . $game['home_logo'] : null;
    $game['away_logo'] = $game['away_logo'] ? UPLOADS_URL . '/' . $game['away_logo'] : null;

    // Player stats
    $stmt = $pdo->prepare('
        SELECT pgs.*, p.first_name, p.last_name, p.photo, p.position,
               t.name AS team_name, t.id AS team_id
        FROM player_game_stats pgs
        JOIN players p ON pgs.player_id = p.id
        JOIN teams   t ON pgs.team_id   = t.id
        WHERE pgs.game_id = ?
        ORDER BY t.id ASC, pgs.total_points DESC
    ');
    $stmt->execute([$id]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $home_stats = [];
    $away_stats = [];
    foreach ($stats as &$s) {
        $s['photo'] = $s['photo'] ? UPLOADS_URL . '/' . $s['photo'] : null;
        if ($s['team_id'] == $game['home_team_id']) $home_stats[] = $s;
        else $away_stats[] = $s;
    }
    unset($s);

    echo json_encode(['success'=>true,'data'=>compact('game','home_stats','away_stats')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','detail'=>$e->getMessage()]);
}
