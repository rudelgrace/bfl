<?php
/**
 * Battle 3x3 — Public API
 * GET /api/team.php?id=&season_id=
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config/app.php';

try {
    $pdo       = Database::getInstance();
    $id        = isset($_GET['id'])        ? (int)$_GET['id']        : 0;
    $season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;
    if (!$id) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Missing id']); exit; }

    // Team base
    $stmt = $pdo->prepare('SELECT t.*, l.name AS league_name FROM teams t JOIN leagues l ON t.league_id = l.id WHERE t.id = ?');
    $stmt->execute([$id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$team) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Team not found']); exit; }
    $team['logo'] = $team['logo'] ? UPLOADS_URL . '/' . $team['logo'] : null;

    // Roster (for given season or latest)
    $rosterSql = '
        SELECT p.id, p.first_name, p.last_name, p.position, p.photo, p.height, p.date_of_birth,
               sr.jersey_number, sr.status AS roster_status
        FROM season_rosters sr
        JOIN players p ON sr.player_id = p.id
        WHERE sr.team_id = ?
    ';
    $rParams = [$id];
    if ($season_id > 0) {
        $rosterSql .= ' AND sr.season_id = ?';
        $rParams[] = $season_id;
    } else {
        $rosterSql .= ' AND sr.season_id = (SELECT MAX(season_id) FROM season_rosters WHERE team_id = ?)';
        $rParams[] = $id;
    }
    $rosterSql .= ' ORDER BY sr.jersey_number ASC';
    $stmt = $pdo->prepare($rosterSql);
    $stmt->execute($rParams);
    $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roster as &$p) {
        $p['photo'] = $p['photo'] ? UPLOADS_URL . '/' . $p['photo'] : null;
        $p['age']   = $p['date_of_birth'] ? (int)date_diff(date_create($p['date_of_birth']), date_create('today'))->y : null;
    }
    unset($p);

    // Games (most recent 10, team involved)
    $gSql = '
        SELECT g.id, g.game_date, g.game_time, g.home_score, g.away_score,
               g.status, g.game_type, g.playoff_round,
               ht.id AS home_id, ht.name AS home_team, ht.logo AS home_logo,
               at.id AS away_id, at.name AS away_team, at.logo AS away_logo
        FROM games g
        JOIN teams ht ON g.home_team_id = ht.id
        JOIN teams at ON g.away_team_id = at.id
        WHERE (g.home_team_id = ? OR g.away_team_id = ?)
    ';
    $gParams = [$id, $id];
    if ($season_id > 0) { $gSql .= ' AND g.season_id = ?'; $gParams[] = $season_id; }
    $gSql .= ' ORDER BY g.game_date DESC, g.id DESC LIMIT 15';
    $stmt = $pdo->prepare($gSql);
    $stmt->execute($gParams);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($games as &$g) {
        $g['home_logo'] = $g['home_logo'] ? UPLOADS_URL . '/' . $g['home_logo'] : null;
        $g['away_logo'] = $g['away_logo'] ? UPLOADS_URL . '/' . $g['away_logo'] : null;
    }
    unset($g);

    // Standings
    $standings = null;
    if ($season_id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM standings WHERE team_id = ? AND season_id = ?');
        $stmt->execute([$id, $season_id]);
        $standings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($standings) {
            $standings['point_differential'] = (int) $standings['point_differential'];
            // Add legacy/consistent key so frontend can use points_diff
            $standings['points_diff'] = $standings['point_differential'];
        }
    }

    echo json_encode(['success'=>true,'data'=>compact('team','roster','games','standings')]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','detail'=>$e->getMessage()]);
}