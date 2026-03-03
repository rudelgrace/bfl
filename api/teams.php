<?php
/**
 * Battle 3x3 — Public API
 * GET /api/teams.php?league_id=&season_id=
 *
 * Fixes:
 *  - SQL injection: season_id bound as parameter (was string-concat)
 *  - Wrong column name: point_differential (not points_diff)
 *  - Upload paths: use UPLOADS_URL constant (portable)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo       = Database::getInstance();
    $league_id = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;
    $season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;

    $params = [];
    if ($season_id > 0) {
        $seasonJoin = 'LEFT JOIN season_rosters sr ON sr.team_id = t.id AND sr.season_id = ?';
        $params[] = $season_id;
    } else {
        $seasonJoin = 'LEFT JOIN season_rosters sr ON sr.team_id = t.id';
    }

    $sql = "
        SELECT t.id, t.name, t.logo, t.league_id,
               l.name AS league_name,
               COUNT(DISTINCT sr.player_id) AS player_count
        FROM teams t
        JOIN leagues l ON t.league_id = l.id
        {$seasonJoin}
    ";

    $where = [];
    if ($league_id > 0) { $where[] = 't.league_id = ?'; $params[] = $league_id; }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' GROUP BY t.id ORDER BY t.name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($season_id > 0) {
        // correct column is point_differential
        $stStmt = $pdo->prepare('
            SELECT team_id, wins, losses, points_for, points_against, point_differential
            FROM standings WHERE season_id = ?
        ');
        $stStmt->execute([$season_id]);
        $standings = [];
        foreach ($stStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $standings[$row['team_id']] = $row;
        }
        foreach ($teams as &$t) {
            $t['logo'] = $t['logo'] ? UPLOADS_URL . '/' . $t['logo'] : null;
            $st = $standings[$t['id']] ?? null;
            $t['wins']               = $st ? (int)$st['wins']               : 0;
            $t['losses']             = $st ? (int)$st['losses']             : 0;
            $t['points_for']         = $st ? (int)$st['points_for']         : 0;
            $t['points_against']     = $st ? (int)$st['points_against']     : 0;
            $t['point_differential'] = $st ? (int)$st['point_differential'] : 0;
            $t['points_diff']        = $t['point_differential']; // alias for JS
        }
        unset($t);
    } else {
        foreach ($teams as &$t) {
            $t['logo'] = $t['logo'] ? UPLOADS_URL . '/' . $t['logo'] : null;
        }
        unset($t);
    }

    echo json_encode(['success' => true, 'data' => $teams]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
