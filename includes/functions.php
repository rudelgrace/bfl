<?php
/**
 * The Battle 3x3 — Compatibility Shim
 * includes/functions.php
 *
 * This file intentionally contains NO business logic.
 * Every function here is a thin delegation to a Service class.
 *
 * WHY THIS FILE EXISTS:
 *   All 34 admin/*.php files start with:
 *     require_once __DIR__ . '/../../includes/functions.php'
 *   Keeping this shim means ZERO changes to existing admin files
 *   while the logic lives in clean, testable service classes.
 *
 * ADDING NEW FEATURES:
 *   1. Add the logic to the appropriate app/Services/*.php class.
 *   2. If you need it as a global function (e.g. for a view), add
 *      a one-line wrapper here.
 *   3. New admin pages can import services directly instead of
 *      relying on this file at all.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/auth.php';

// ── Service Container ────────────────────────────────────────────────────────

/**
 * Return the shared service container.
 * Services are instantiated once and reused (lazy singleton per request).
 *
 * @return array{
 *   leagues:  LeagueService,
 *   seasons:  SeasonService,
 *   teams:    TeamService,
 *   players:  PlayerService,
 *   roster:   RosterService,
 *   games:    GameService,
 *   stats:    StatsService,
 *   files:    FileService,
 * }
 */
function app(): array
{
    static $services = null;
    if ($services === null) {
        $pdo      = Database::getInstance();
        $services = [
            'leagues'  => new LeagueService($pdo),
            'seasons'  => new SeasonService($pdo),
            'teams'    => new TeamService($pdo),
            'players'  => new PlayerService($pdo),
            'roster'   => new RosterService($pdo),
            'games'    => new GameService($pdo),
            'stats'    => new StatsService($pdo),
            'files'    => new FileService($pdo),
            'playoffs' => new PlayoffService($pdo),
        ];
    }
    return $services;
}

// ── Helpers (delegated to Helpers class) ────────────────────────────────────

function clean(mixed $value): string         { return Helpers::clean($value); }
function redirect(string $url): void         { Helpers::redirect($url); }
function setFlash(string $type, string $msg): void { Helpers::setFlash($type, $msg); }
function getFlash(): ?array                  { return Helpers::getFlash(); }
function post(string $k, mixed $d = ''): mixed    { return Helpers::post($k, $d); }
function get(string $k, mixed $d = ''): mixed     { return Helpers::get($k, $d); }
function intGet(string $k, int $d = 0): int  { return Helpers::intGet($k, $d); }
function intPost(string $k, int $d = 0): int { return Helpers::intPost($k, $d); }
function formatDate(?string $d, string $f = 'M j, Y'): string { return Helpers::formatDate($d, $f); }
function seasonStatusBadge(string $s): string { return Helpers::seasonStatusBadge($s); }
function roleBadge(string $r): string        { return Helpers::roleBadge($r); }
function roleLabel(string $r): string        { return Helpers::roleLabel($r); }

// ── File Uploads ─────────────────────────────────────────────────────────────

function handleUpload(array $file, string $subDir): array  { return app()['files']->handle($file, $subDir); }
function deleteUpload(?string $filename): void             { app()['files']->delete($filename); }

// ── League ───────────────────────────────────────────────────────────────────

function getLeague(int $id): ?array
{
    return app()['leagues']->find($id);
}

function requireLeague(int $id): array
{
    $league = getLeague($id);
    if (!$league) {
        setFlash('error', 'League not found.');
        redirect(ADMIN_URL . '/leagues/index.php');
    }
    return $league;
}

// ── Season ───────────────────────────────────────────────────────────────────

function getSeason(int $id): ?array
{
    return app()['seasons']->find($id);
}

function requireSeason(int $id): array
{
    $season = getSeason($id);
    if (!$season) {
        setFlash('error', 'Season not found.');
        redirect(ADMIN_URL . '/leagues/index.php');
    }
    return $season;
}

function isSeasonLocked(int $id): bool
{
    return app()['seasons']->isLocked($id);
}

function startSeason(int $id): bool
{
    return app()['seasons']->start($id);
}

function moveToPlayoffs(int $id): bool
{
    return app()['seasons']->moveToPlayoffs($id);
}

function checkAndCompleteSeason(int $id): void
{
    app()['seasons']->checkAndComplete($id);
}

function checkAndMoveToPlayoffs(int $id): void
{
    app()['seasons']->checkAndMoveToPlayoffs($id);
}

function generateSchedule(array $teamIds, int $gamesPerTeam): array
{
    return app()['seasons']->generateSchedule($teamIds, $gamesPerTeam);
}

function calculatePlayoffsMVP(int $seasonId): void
{
    app()['seasons']->calculatePlayoffsMVP($seasonId);
}

// ── Standings / Game ─────────────────────────────────────────────────────────

function updateStandings(int $seasonId): void
{
    app()['games']->updateStandings($seasonId);
}

function getStandings(int $seasonId): array
{
    return app()['games']->getStandings($seasonId);
}

// ── Stats ────────────────────────────────────────────────────────────────────

function getSeasonBest3PTPct(int $seasonId, int $min = 3): ?array
{
    return app()['stats']->seasonBest3PTPct($seasonId, $min);
}

function getSeasonBestFTPct(int $seasonId, int $min = 3): ?array
{
    return app()['stats']->seasonBestFTPct($seasonId, $min);
}

function getRegularSeasonMVPRace(int $seasonId): array
{
    return app()['stats']->regularSeasonMVPRace($seasonId);
}

function getLeagueTop5Scorers(int $leagueId): array
{
    return app()['stats']->leagueTop5Scorers($leagueId);
}

function getLeagueTop5ThreePointers(int $leagueId): array
{
    return app()['stats']->leagueTop5ThreePointers($leagueId);
}

function getLeagueTop5FreeThrows(int $leagueId): array
{
    return app()['stats']->leagueTop5FreeThrows($leagueId);
}

// ── League All-Time PLAYOFF Leaders ─────────────────────────────────────────

function getLeagueTop5PlayoffScorers(int $leagueId): array
{
    return app()['stats']->leagueTop5PlayoffScorers($leagueId);
}

function getLeagueTop5PlayoffThreePointers(int $leagueId): array
{
    return app()['stats']->leagueTop5PlayoffThreePointers($leagueId);
}

function getLeagueTop5PlayoffFreeThrows(int $leagueId): array
{
    return app()['stats']->leagueTop5PlayoffFreeThrows($leagueId);
}

// ── Player career stats (regular/playoff split + career high) ────────────────

function getPlayerCareerStatsSplit(int $playerId): array
{
    return app()['stats']->playerCareerStatsSplit($playerId);
}

// ── Season playoff rankings ──────────────────────────────────────────────────

function getSeasonPlayoffRankings(int $seasonId): array
{
    return app()['stats']->seasonPlayoffRankings($seasonId);
}

function getPlayerAllTimeRank(int $playerId, int $leagueId, string $statExpr, string $alias = ''): array
{
    return app()['stats']->playerAllTimeRank($playerId, $leagueId, $statExpr);
}

// ── Playoffs ─────────────────────────────────────────────────────────────────

/**
 * Generate (or reset) the playoff bracket for a season.
 * Concurrency-safe. Throws RuntimeException on failure.
 * Returns the number of games created.
 */
function generatePlayoffBracket(int $seasonId): int
{
    return app()['playoffs']->generateBracket($seasonId);
}

/**
 * After a playoff game result is saved, propagate the winner forward
 * into any downstream bracket slots that reference this game.
 */
function propagatePlayoffWinner(int $completedGameId): void
{
    app()['playoffs']->propagateWinner($completedGameId);
}

/**
 * Delete all playoff games and regenerate from current standings.
 * Season must be in 'playoffs' status.
 */
function resetPlayoffBracket(int $seasonId): int
{
    return app()['playoffs']->resetBracket($seasonId);
}

// ── Legacy compatibility aliases (functions renamed in v2) ───────────────────
// Keep these so any customisations made on top of v1 still work.

function getSeasonTopScorer(int $seasonId): ?array
{
    $rows = app()['stats']->seasonRankings($seasonId);
    return $rows[0] ?? null;
}

function getSeasonTopScorerFull(int $seasonId): ?array
{
    return getSeasonTopScorer($seasonId);
}

function getLeagueAllTimeTopScorer(int $leagueId): ?array
{
    $rows = app()['stats']->leagueTop5Scorers($leagueId);
    return $rows[0] ?? null;
}

/**
 * @deprecated v2 — kept for v1 compatibility.
 *             updateGameWithStats() logic is now in GameService::saveResults().
 *             enter_results.php handles this inline.
 */
function updateGameWithStats(int $gameId, array $stats): void
{
    $game = app()['games']->find($gameId);
    if (!$game) return;

    app()['games']->saveResults(
        $gameId,
        $stats['player_stats'] ?? [],
        ['home' => $game['home_team_id'], 'away' => $game['away_team_id']],
        null,
        $stats['manual_score'] ?? false,
        $stats['home_score'] ?? 0,
        $stats['away_score'] ?? 0,
    );

    updateStandings($game['season_id']);

    if ($game['game_type'] === 'regular') {
        checkAndMoveToPlayoffs($game['season_id']);
    }
    if ($game['game_type'] === 'playoff') {
        checkAndCompleteSeason($game['season_id']);
    }
}

// ── Deprecated / removed in v2 ───────────────────────────────────────────────
// These functions existed in v1 but have been renamed/merged.
// Stubs are kept to prevent fatal errors on any un-updated page.

/** @deprecated Use checkAndCompleteSeason() */
function checkAndCompleteLeague(int $seasonId): void
{
    checkAndCompleteSeason($seasonId);
}
