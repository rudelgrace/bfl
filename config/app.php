<?php
/**
 * The Battle 3x3 — Bootstrap
 * config/app.php
 *
 * Entry point for every PHP request.
 * Loaded via: require_once __DIR__ . '/../../config/app.php'
 *
 * Responsibilities:
 *   1. Load the .env file
 *   2. Autoload core classes and services
 *   3. Define app constants
 *   4. Start the session
 */

// ── 1. Load Core Classes (no Composer required) ───────────────────────────────

$coreFiles = [
    __DIR__ . '/../core/Env.php',
    __DIR__ . '/../core/Database.php',
    __DIR__ . '/../core/Helpers.php',
];

$serviceFiles = [
    __DIR__ . '/../app/Services/LeagueService.php',
    __DIR__ . '/../app/Services/SeasonService.php',
    __DIR__ . '/../app/Services/TeamService.php',
    __DIR__ . '/../app/Services/PlayerService.php',
    __DIR__ . '/../app/Services/RosterService.php',
    __DIR__ . '/../app/Services/GameService.php',
    __DIR__ . '/../app/Services/StatsService.php',
    __DIR__ . '/../app/Services/FileService.php',
    __DIR__ . '/../app/Services/PlayoffService.php',
];

foreach ([...$coreFiles, ...$serviceFiles] as $file) {
    require_once $file;
}

// ── 2. Load Environment Variables ─────────────────────────────────────────────

// .env lives in the project root (same level as /admin, /config, etc.)
Env::load(dirname(__DIR__) . '/.env');

// ── 3. Application Constants ──────────────────────────────────────────────────

define('APP_NAME',    Env::get('APP_NAME',    'The Battle 3x3'));
define('APP_VERSION', Env::get('APP_VERSION', '2.0.0'));
define('APP_ENV',     Env::get('APP_ENV',     'production')); // 'development' | 'production'

// Database
define('DB_HOST',    Env::require('DB_HOST'));
define('DB_PORT',    Env::get('DB_PORT', '3306'));
define('DB_NAME',    Env::require('DB_NAME'));
define('DB_USER',    Env::require('DB_USER'));
define('DB_PASS',    Env::require('DB_PASS'));
define('DB_CHARSET', Env::get('DB_CHARSET', 'utf8mb4'));

// URLs — auto-detected from the request, or overridden via .env
if (Env::get('BASE_URL')) {
    define('BASE_URL', rtrim(Env::get('BASE_URL'), '/'));
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script   = $_SERVER['SCRIPT_NAME'] ?? '';

    // Strip any known sub-directory prefixes to find the project root.
    // Handles requests from /admin/, /api/, etc.
    $basePath = '';
    foreach (['/admin/', '/api/'] as $marker) {
        $pos = strpos($script, $marker);
        if ($pos !== false) {
            $basePath = substr($script, 0, $pos);
            break;
        }
    }

    define('BASE_URL', $protocol . '://' . $host . rtrim($basePath, '/'));
}

define('ADMIN_URL',   BASE_URL . '/admin');
define('ASSETS_URL',  BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');
define('UPLOADS_PATH', dirname(__DIR__) . '/uploads');

// File uploads
define('MAX_FILE_SIZE',       (int) Env::get('MAX_FILE_SIZE_MB', 2) * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

// ── 4. Error Display ──────────────────────────────────────────────────────────

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ── 5. Session ────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 6. Database Config ───────────────────────────────────────────────────────

require_once __DIR__ . '/database.php';
