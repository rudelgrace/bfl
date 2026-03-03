<?php
/**
 * Battle 3x3 — Public API
 * GET /api/games.php?league_id=&season_id=&type=&status=
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo       = Database::getInstance();
    $league_id = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;
    $season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;
    $type      = $_GET['type']   ?? '';   // regular|playoff
    $status    = $_GET['status'] ?? '';   // scheduled|completed

    $sql = '
        SELECT g.id, g.game_date, g.game_time, g.home_score, g.away_score,
               g.status, g.game_type, g.playoff_round, g.playoff_position,
               g.home_source_game_id, g.away_source_game_id,
               g.season_id,
               ht.id AS home_id, ht.name AS home_team, ht.logo AS home_logo,
               at.id AS away_id, at.name AS away_team, at.logo AS away_logo,
               s.name AS season_name, s.status AS season_status,
               l.id AS league_id, l.name AS league_name
        FROM games g
        JOIN teams ht  ON g.home_team_id = ht.id
        JOIN teams at  ON g.away_team_id = at.id
        JOIN seasons s ON g.season_id    = s.id
        JOIN leagues l ON s.league_id    = l.id
        WHERE 1=1
    ';
    $params = [];

    if ($league_id > 0) { $sql .= ' AND l.id = ?';       $params[] = $league_id; }
    if ($season_id > 0) { $sql .= ' AND g.season_id = ?'; $params[] = $season_id; }
    if ($type)          { $sql .= ' AND g.game_type = ?'; $params[] = $type; }
    if ($status)        { $sql .= ' AND g.status = ?';    $params[] = $status; }

    $sql .= ' ORDER BY g.game_date DESC, g.game_time DESC, g.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($games as &$g) {
        $g['home_logo'] = $g['home_logo'] ? UPLOADS_URL . '/' . $g['home_logo'] : null;
        $g['away_logo'] = $g['away_logo'] ? UPLOADS_URL . '/' . $g['away_logo'] : null;
    }
    unset($g);

    echo json_encode(['success' => true, 'data' => $games]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
