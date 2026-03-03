<?php
/**
 * Battle 3x3 — Public API
 * GET /api/player.php?id=
 * Full player profile: bio, career stats, season history, highs.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo = Database::getInstance();
    $id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }

    // Basic profile
    $stmt = $pdo->prepare('
        SELECT p.*, l.name AS league_name
        FROM players p
        JOIN leagues l ON p.league_id = l.id
        WHERE p.id = ?
    ');
    $stmt->execute([$id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$player) { http_response_code(404); echo json_encode(['success' => false, 'error' => 'Player not found']); exit; }

    $player['photo'] = $player['photo'] ? UPLOADS_URL . '/' . $player['photo'] : null;
    $player['age']   = $player['date_of_birth'] ? (int)date_diff(date_create($player['date_of_birth']), date_create('today'))->y : null;

    // Season history
    $stmt = $pdo->prepare('
        SELECT s.id AS season_id, s.name AS season_name, s.status AS season_status,
               t.id AS team_id, t.name AS team_name, t.logo AS team_logo,
               sr.jersey_number,
               SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.total_points      ELSE 0 END) AS reg_pts,
               SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.two_points_made   ELSE 0 END) AS reg_2pt,
               SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.three_points_made ELSE 0 END) AS reg_3pt,
               SUM(CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.free_throws_made  ELSE 0 END) AS reg_ft,
               COUNT(DISTINCT CASE WHEN g.game_type="regular" AND g.status="completed" THEN pgs.game_id END) AS reg_gp,
               SUM(CASE WHEN g.game_type="playoff" AND g.status="completed" THEN pgs.total_points      ELSE 0 END) AS po_pts,
               SUM(CASE WHEN g.game_type="playoff" AND g.status="completed" THEN pgs.three_points_made ELSE 0 END) AS po_3pt,
               COUNT(DISTINCT CASE WHEN g.game_type="playoff" AND g.status="completed" THEN pgs.game_id END) AS po_gp
        FROM season_rosters sr
        JOIN seasons s ON sr.season_id = s.id
        JOIN teams t   ON sr.team_id   = t.id
        LEFT JOIN player_game_stats pgs ON pgs.player_id = sr.player_id AND pgs.team_id = sr.team_id
        LEFT JOIN games g ON pgs.game_id = g.id AND g.season_id = sr.season_id
        WHERE sr.player_id = ?
        GROUP BY s.id, t.id, sr.jersey_number
        ORDER BY s.id DESC
    ');
    $stmt->execute([$id]);
    $seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($seasons as &$s) {
        $s['team_logo'] = $s['team_logo'] ? UPLOADS_URL . '/' . $s['team_logo'] : null;
        $gp = (int)$s['reg_gp'];
        $s['reg_ppg'] = $gp > 0 ? round($s['reg_pts'] / $gp, 1) : 0;
        $po = (int)$s['po_gp'];
        $s['po_ppg']  = $po > 0 ? round($s['po_pts'] / $po, 1) : 0;
    }
    unset($s);

    // Career totals (regular season)
    $stmt = $pdo->prepare('
        SELECT
            SUM(pgs.total_points)           AS career_pts,
            SUM(pgs.two_points_made)         AS career_2pt,
            SUM(pgs.three_points_made)       AS career_3pt,
            SUM(pgs.free_throws_made)        AS career_ft,
            COUNT(DISTINCT pgs.game_id)      AS career_gp,
            MAX(pgs.total_points)            AS career_high
        FROM player_game_stats pgs
        JOIN games g ON pgs.game_id = g.id
        WHERE pgs.player_id = ? AND g.status = "completed"
    ');
    $stmt->execute([$id]);
    $career = $stmt->fetch(PDO::FETCH_ASSOC);
    $cgp = (int)($career['career_gp'] ?? 0);
    $career['career_ppg'] = $cgp > 0 ? round($career['career_pts'] / $cgp, 1) : 0;

    // Game log (last 10)
    $stmt = $pdo->prepare('
        SELECT g.id AS game_id, g.game_date, g.game_type, g.playoff_round,
               ht.name AS home_team, at.name AS away_team,
               g.home_score, g.away_score,
               pgs.total_points, pgs.two_points_made, pgs.three_points_made, pgs.free_throws_made,
               pgs.team_id, t.name AS player_team
        FROM player_game_stats pgs
        JOIN games g  ON pgs.game_id  = g.id
        JOIN teams ht ON g.home_team_id = ht.id
        JOIN teams at ON g.away_team_id = at.id
        JOIN teams t  ON pgs.team_id    = t.id
        WHERE pgs.player_id = ? AND g.status = "completed"
        ORDER BY g.game_date DESC, g.id DESC
        LIMIT 10
    ');
    $stmt->execute([$id]);
    $game_log = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // All-time ranking among league players
    $stmt = $pdo->prepare('
        SELECT COUNT(*) + 1 AS rank_pts
        FROM (
            SELECT pgs2.player_id, SUM(pgs2.total_points) AS total
            FROM player_game_stats pgs2
            JOIN games g2 ON pgs2.game_id = g2.id
            JOIN players p2 ON pgs2.player_id = p2.id
            WHERE g2.status = "completed" AND p2.league_id = ?
            GROUP BY pgs2.player_id
            HAVING total > (
                SELECT COALESCE(SUM(pgs3.total_points), 0)
                FROM player_game_stats pgs3
                JOIN games g3 ON pgs3.game_id = g3.id
                WHERE pgs3.player_id = ? AND g3.status = "completed"
            )
        ) sub
    ');
    $stmt->execute([$player['league_id'], $id]);
    $rank = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'  => true,
        'data'     => [
            'player'   => $player,
            'seasons'  => $seasons,
            'career'   => $career,
            'game_log' => $game_log,
            'rankings' => ['all_time_pts_rank' => (int)($rank['rank_pts'] ?? 0)],
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
