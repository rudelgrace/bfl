<?php
/**
 * Battle 3x3 — Public API
 * GET /api/seasons.php?league_id=
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo = Database::getInstance();
    $league_id = isset($_GET['league_id']) ? (int)$_GET['league_id'] : 0;

    $sql = 'SELECT id, league_id, name, status, start_date, end_date, games_per_team, playoff_teams_count FROM seasons';
    $params = [];

    if ($league_id > 0) {
        $sql .= ' WHERE league_id = ?';
        $params[] = $league_id;
    }
    $sql .= ' ORDER BY id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $seasons]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
