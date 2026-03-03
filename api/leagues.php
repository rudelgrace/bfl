<?php
/**
 * Battle 3x3 — Public API
 * GET /api/leagues.php
 * Returns all leagues with their current season info, description, rules & structure.
 *
 * v1.2 — Added `rules` and `structure` columns so the public About page
 *        can display admin-managed content rather than hard-coded text.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo = Database::getInstance();

    $stmt = $pdo->query('
        SELECT l.id, l.name, l.description, l.rules, l.structure, l.logo, l.status,
               s.id AS season_id, s.name AS season_name, s.status AS season_status,
               s.start_date, s.end_date
        FROM leagues l
        LEFT JOIN seasons s ON s.league_id = l.id
            AND s.status IN ("active","playoffs")
        ORDER BY l.id ASC
    ');

    $leagues = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = $row['id'];
        if (!isset($leagues[$id])) {
            $leagues[$id] = [
                'id'          => (int) $row['id'],
                'name'        => $row['name'],
                'description' => $row['description'] ?: null,
                'rules'       => $row['rules']       ?: null,
                'structure'   => $row['structure']   ?: null,
                'logo'        => $row['logo'] ? UPLOADS_URL . '/' . $row['logo'] : null,
                'status'      => $row['status'],
                'season'      => null,
            ];
        }
        if ($row['season_id']) {
            $leagues[$id]['season'] = [
                'id'         => (int) $row['season_id'],
                'name'       => $row['season_name'],
                'status'     => $row['season_status'],
                'start_date' => $row['start_date'],
                'end_date'   => $row['end_date'],
            ];
        }
    }

    echo json_encode(['success' => true, 'data' => array_values($leagues)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
