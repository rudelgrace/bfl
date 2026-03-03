<?php
/**
 * Battle 3x3 — Public API
 * GET /api/mvp.php?league_id=&season_id=
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo       = Database::getInstance();
    $league_id = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;
    $season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;

    // MVP race — top 10 by PPG in regular season
    $sql = '
        SELECT p.id, p.first_name, p.last_name, p.photo,
               t.name AS team_name, t.id AS team_id,
               s.id AS season_id, s.name AS season_name,
               l.id AS league_id, l.name AS league_name,
               SUM(pgs.total_points)           AS total_points,
               SUM(pgs.three_points_made)       AS total_3pt,
               SUM(pgs.free_throws_made)        AS total_ft,
               COUNT(DISTINCT pgs.game_id)      AS games_played,
               ROUND(SUM(pgs.total_points) / COUNT(DISTINCT pgs.game_id), 1) AS ppg,
               -- is this player the official season MVP?
               (s.regular_season_mvp_id = p.id) AS is_regular_mvp,
               (s.playoffs_mvp_id        = p.id) AS is_playoff_mvp
        FROM player_game_stats pgs
        JOIN players p ON pgs.player_id = p.id
        JOIN games   g ON pgs.game_id   = g.id
        JOIN seasons s ON g.season_id   = s.id
        JOIN leagues l ON s.league_id   = l.id
        JOIN season_rosters sr ON sr.player_id = p.id AND sr.season_id = s.id
        JOIN teams t ON sr.team_id = t.id
        WHERE g.game_type = "regular" AND g.status = "completed"
    ';
    $params = [];
    if ($season_id > 0) { $sql .= ' AND s.id = ?';       $params[] = $season_id; }
    if ($league_id > 0) { $sql .= ' AND l.id = ?';       $params[] = $league_id; }
    $sql .= '
        GROUP BY p.id, t.id, s.id
        HAVING games_played > 0
        ORDER BY ppg DESC, total_points DESC
        LIMIT 15
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $race = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($race as &$r) {
        $r['photo'] = $r['photo'] ? UPLOADS_URL . '/' . $r['photo'] : null;
    }
    unset($r);

    // Official MVPs from completed seasons
    $mvpSql = '
        SELECT s.id AS season_id, s.name AS season_name,
               l.name AS league_name,
               p1.id AS reg_mvp_id, p1.first_name AS reg_mvp_fn, p1.last_name AS reg_mvp_ln, p1.photo AS reg_mvp_photo,
               p2.id AS po_mvp_id,  p2.first_name AS po_mvp_fn,  p2.last_name AS po_mvp_ln,  p2.photo AS po_mvp_photo
        FROM seasons s
        JOIN leagues l ON s.league_id = l.id
        LEFT JOIN players p1 ON s.regular_season_mvp_id = p1.id
        LEFT JOIN players p2 ON s.playoffs_mvp_id        = p2.id
        WHERE s.status = "completed"
          AND (s.regular_season_mvp_id IS NOT NULL OR s.playoffs_mvp_id IS NOT NULL)
    ';
    $mvpParams = [];
    if ($league_id > 0) { $mvpSql .= ' AND l.id = ?'; $mvpParams[] = $league_id; }
    $mvpSql .= ' ORDER BY s.id DESC LIMIT 10';
    $stmt = $pdo->prepare($mvpSql);
    $stmt->execute($mvpParams);
    $official = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($official as &$o) {
        $o['reg_mvp_photo'] = $o['reg_mvp_photo'] ? UPLOADS_URL . '/' . $o['reg_mvp_photo'] : null;
        $o['po_mvp_photo']  = $o['po_mvp_photo']  ? UPLOADS_URL . '/' . $o['po_mvp_photo']  : null;
    }
    unset($o);

    echo json_encode(['success'=>true,'data'=>['race'=>$race,'official'=>$official]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','detail'=>$e->getMessage()]);
}
