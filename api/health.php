<?php
/**
 * Battle 3x3 — API Health Check
 * GET /api/health.php
 *
 * Returns the status of the API and database connection.
 * Useful for monitoring, deployment verification, and debugging.
 *
 * Response:
 *   200 + { status: "ok", ... }   — everything is working
 *   503 + { status: "error", ... } — database unreachable
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, must-revalidate');

// Time the response
$start = microtime(true);

$report = [
    'status'     => 'ok',
    'app'        => 'Battle 3x3',
    'timestamp'  => date('c'),
    'php'        => PHP_VERSION,
    'database'   => 'unknown',
    'latency_ms' => null,
];

try {
    require_once __DIR__ . '/../config/app.php';

    $pdo = Database::getInstance();
    $pdo->query('SELECT 1'); // minimal query

    $report['database']   = 'connected';
    $report['env']        = APP_ENV;
    $report['version']    = APP_VERSION;
    $report['latency_ms'] = round((microtime(true) - $start) * 1000, 2);

    echo json_encode(['success' => true, 'data' => $report]);

} catch (Throwable $e) {
    $report['status']   = 'error';
    $report['database'] = 'unreachable';
    $report['error']    = (APP_ENV ?? 'production') === 'development'
                            ? $e->getMessage()
                            : 'Database connection failed. Check server logs.';

    http_response_code(503);
    echo json_encode(['success' => false, 'data' => $report]);
}
