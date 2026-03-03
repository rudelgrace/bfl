<?php
/**
 * Battle 3x3 — Public API
 * GET /api/standings.php?season_id=&league_id=
 *
 * v1.2 — Fixed column reference: `point_differential` (was `points_diff` — bug).
 *         Separates regular-season and playoff stats correctly.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo       = Database::getInstance();
    $season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;
    $league_id = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;

    $sql = '
        SELECT st.team_id,
               st.wins,
               st.losses,
               st.points_for,
               st.points_against,
               st.point_differential,   -- correct column name (not points_diff)
               st.season_id,
               t.name AS team_name,
               t.logo AS team_logo,
               s.name AS season_name,
               s.status AS season_status,
               l.id   AS league_id,
               l.name AS league_name,
               s.playoff_teams_count
        FROM standings st
        JOIN teams   t ON st.team_id   = t.id
        JOIN seasons s ON st.season_id = s.id
        JOIN leagues l ON s.league_id  = l.id
        WHERE 1=1
    ';
    $params = [];
    if ($season_id > 0) { $sql .= ' AND st.season_id = ?'; $params[] = $season_id; }
    if ($league_id > 0) { $sql .= ' AND l.id = ?';         $params[] = $league_id; }
    $sql .= ' ORDER BY st.wins DESC, st.point_differential DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['team_logo']          = $r['team_logo'] ? UPLOADS_URL . '/' . $r['team_logo'] : null;
        $r['point_differential'] = (int) $r['point_differential'];
        // Keep legacy key so existing frontend code still works
        $r['points_diff']        = $r['point_differential'];
    }
    unset($r);

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
