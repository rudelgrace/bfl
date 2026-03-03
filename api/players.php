<?php
/**
 * Battle 3x3 — Public API
 * GET /api/players.php?league_id=&season_id=
 * Returns players with their current season stats.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo       = Database::getInstance();
    $league_id = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;
    $season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;

    // Base query: all players with career stats
    $sql = '
        SELECT p.id, p.first_name, p.last_name, p.position, p.height,
               p.date_of_birth, p.photo, p.bio, p.league_id,
               l.name AS league_name,
               -- Current season team
               t.id   AS team_id,
               t.name AS team_name,
               t.logo AS team_logo,
               sr.jersey_number,
               sr.status AS roster_status,
               -- Season stats (regular season)
               COALESCE(SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.total_points      ELSE 0 END), 0) AS season_pts,
               COALESCE(SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.two_points_made   ELSE 0 END), 0) AS season_2pt,
               COALESCE(SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.three_points_made ELSE 0 END), 0) AS season_3pt,
               COALESCE(SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.free_throws_made  ELSE 0 END), 0) AS season_ft,
               COALESCE(COUNT(DISTINCT CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.game_id END), 0) AS season_gp,
               -- Playoff stats
               COALESCE(SUM(CASE WHEN g.game_type="playoff" AND g.status="completed" THEN pgs.total_points      ELSE 0 END), 0) AS playoff_pts,
               COALESCE(COUNT(DISTINCT CASE WHEN g.game_type="playoff" AND g.status="completed" THEN pgs.game_id END), 0) AS playoff_gp
        FROM players p
        JOIN leagues l ON p.league_id = l.id
    ';
    $params = [];

    if ($season_id > 0) {
        $sql .= '
            LEFT JOIN season_rosters sr ON sr.player_id = p.id AND sr.season_id = ?
            LEFT JOIN teams t ON sr.team_id = t.id
            LEFT JOIN player_game_stats pgs ON pgs.player_id = p.id
            LEFT JOIN games g ON pgs.game_id = g.id AND g.season_id = ?
        ';
        $params[] = $season_id;
        $params[] = $season_id;
    } else {
        $sql .= '
            LEFT JOIN season_rosters sr ON sr.player_id = p.id
            LEFT JOIN teams t ON sr.team_id = t.id
            LEFT JOIN player_game_stats pgs ON pgs.player_id = p.id
            LEFT JOIN games g ON pgs.game_id = g.id
        ';
    }

    if ($league_id > 0) {
        $sql .= ' WHERE p.league_id = ?';
        $params[] = $league_id;
    }

    $sql .= ' GROUP BY p.id, t.id, sr.jersey_number, sr.status ORDER BY p.last_name ASC, p.first_name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $players = [];
    foreach ($rows as $row) {
        $gp  = (int)$row['season_gp'];
        $row['photo']    = $row['photo']    ? UPLOADS_URL . '/' . $row['photo']    : null;
        $row['team_logo'] = $row['team_logo'] ? UPLOADS_URL . '/' . $row['team_logo'] : null;
        $row['ppg']       = $gp > 0 ? round($row['season_pts'] / $gp, 1) : 0;
        $row['age']       = $row['date_of_birth'] ? (int)date_diff(date_create($row['date_of_birth']), date_create('today'))->y : null;
        $players[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $players]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
