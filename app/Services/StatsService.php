<?php
/**
 * The Battle 3x3 — Service Layer
 * StatsService  v1.3
 *
 * All leaderboard / ranking queries.
 * Key rule: EVERY query that aggregates per-player stats must filter
 * on g.game_type ('regular' or 'playoff') so the two pools never mix.
 *
 * Career high = MAX single-game points across ALL game types (mirrors NBA).
 * Career totals and rankings = regular season only (same as NBA official stats).
 * Playoff totals / playoff rankings = separate methods.
 */
class StatsService
{
    public function __construct(private PDO $pdo) {}

    // ═══════════════════════════════════════════════════════════════════════
    // SEASON-LEVEL STATS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Regular-season rankings for a season (used by Rankings tab & MVP race).
     * Filters: game_type = 'regular' only.
     */
    public function seasonRankings(int $seasonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   t.name AS team_name, t.id AS team_id,
                   SUM(pgs.total_points)           AS total_pts,
                   SUM(pgs.two_points_made)         AS total_2pt,
                   SUM(pgs.three_points_made)       AS total_3pt,
                   SUM(pgs.three_points_attempted)  AS total_3pt_att,
                   SUM(pgs.free_throws_made)        AS total_ft,
                   SUM(pgs.free_throws_attempted)   AS total_ft_att,
                   COUNT(DISTINCT pgs.game_id)      AS games,
                   ROUND(SUM(pgs.total_points) / COUNT(DISTINCT pgs.game_id), 1) AS ppg
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN teams t   ON pgs.team_id   = t.id
            WHERE g.season_id = ?
              AND g.game_type  = "regular"
              AND g.status     = "completed"
            GROUP BY p.id, t.id
            HAVING games > 0
            ORDER BY total_pts DESC, ppg DESC
        ');
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll();
    }

    /**
     * Playoff rankings for a season.
     * Filters: game_type = 'playoff' only.
     */
    public function seasonPlayoffRankings(int $seasonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   t.name AS team_name, t.id AS team_id,
                   SUM(pgs.total_points)           AS total_pts,
                   SUM(pgs.two_points_made)         AS total_2pt,
                   SUM(pgs.three_points_made)       AS total_3pt,
                   SUM(pgs.three_points_attempted)  AS total_3pt_att,
                   SUM(pgs.free_throws_made)        AS total_ft,
                   SUM(pgs.free_throws_attempted)   AS total_ft_att,
                   COUNT(DISTINCT pgs.game_id)      AS games,
                   ROUND(SUM(pgs.total_points) / COUNT(DISTINCT pgs.game_id), 1) AS ppg
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN teams t   ON pgs.team_id   = t.id
            WHERE g.season_id = ?
              AND g.game_type  = "playoff"
              AND g.status     = "completed"
            GROUP BY p.id, t.id
            HAVING games > 0
            ORDER BY total_pts DESC, ppg DESC
        ');
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll();
    }

    /**
     * Best 3PT% in REGULAR SEASON games for a season (min attempts threshold).
     */
    public function seasonBest3PTPct(int $seasonId, int $minAttempts = 3): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name, t.name AS team_name,
                   SUM(pgs.three_points_made)      AS made,
                   SUM(pgs.three_points_attempted) AS attempted,
                   ROUND(SUM(pgs.three_points_made) / SUM(pgs.three_points_attempted) * 100, 1) AS pct
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN teams t   ON pgs.team_id   = t.id
            WHERE g.season_id = ?
              AND g.game_type  = "regular"
              AND g.status     = "completed"
            GROUP BY p.id, t.id
            HAVING attempted >= ?
            ORDER BY pct DESC, made DESC
            LIMIT 1
        ');
        $stmt->execute([$seasonId, $minAttempts]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Best FT% in REGULAR SEASON games for a season (min attempts threshold).
     */
    public function seasonBestFTPct(int $seasonId, int $minAttempts = 3): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name, t.name AS team_name,
                   SUM(pgs.free_throws_made)      AS made,
                   SUM(pgs.free_throws_attempted) AS attempted,
                   ROUND(SUM(pgs.free_throws_made) / SUM(pgs.free_throws_attempted) * 100, 1) AS pct
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN teams t   ON pgs.team_id   = t.id
            WHERE g.season_id = ?
              AND g.game_type  = "regular"
              AND g.status     = "completed"
            GROUP BY p.id, t.id
            HAVING attempted >= ?
            ORDER BY pct DESC, made DESC
            LIMIT 1
        ');
        $stmt->execute([$seasonId, $minAttempts]);
        return $stmt->fetch() ?: null;
    }

    /**
     * MVP race for the regular season (top 10 by PPG).
     * Already filtered correctly — kept for compatibility.
     */
    public function regularSeasonMVPRace(int $seasonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name, t.name AS team_name,
                   SUM(pgs.total_points)      AS total_points,
                   COUNT(DISTINCT pgs.game_id) AS games_played,
                   ROUND(SUM(pgs.total_points) / COUNT(DISTINCT pgs.game_id), 1) AS ppg
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN teams t   ON pgs.team_id   = t.id
            WHERE g.season_id = ?
              AND g.game_type  = "regular"
              AND g.status     = "completed"
            GROUP BY p.id, t.id
            HAVING games_played > 0
            ORDER BY ppg DESC, total_points DESC
            LIMIT 10
        ');
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LEAGUE ALL-TIME LEADERS  (regular season only — like NBA official records)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Top 5 all-time REGULAR SEASON scorers in a league.
     */
    public function leagueTop5Scorers(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   SUM(pgs.total_points)       AS total_points,
                   COUNT(DISTINCT pgs.game_id) AS games
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN seasons s ON g.season_id   = s.id
            WHERE s.league_id  = ?
              AND g.game_type   = "regular"
              AND g.status      = "completed"
            GROUP BY p.id
            ORDER BY total_points DESC
            LIMIT 5
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    /**
     * Top 5 all-time REGULAR SEASON three-point shooters in a league.
     */
    public function leagueTop5ThreePointers(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   SUM(pgs.three_points_made)  AS total_3pt,
                   COUNT(DISTINCT pgs.game_id) AS games
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN seasons s ON g.season_id   = s.id
            WHERE s.league_id  = ?
              AND g.game_type   = "regular"
              AND g.status      = "completed"
            GROUP BY p.id
            HAVING total_3pt > 0
            ORDER BY total_3pt DESC
            LIMIT 5
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    /**
     * Top 5 all-time REGULAR SEASON free-throw makers in a league.
     */
    public function leagueTop5FreeThrows(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   SUM(pgs.free_throws_made)   AS total_ft,
                   COUNT(DISTINCT pgs.game_id) AS games
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN seasons s ON g.season_id   = s.id
            WHERE s.league_id  = ?
              AND g.game_type   = "regular"
              AND g.status      = "completed"
            GROUP BY p.id
            HAVING total_ft > 0
            ORDER BY total_ft DESC
            LIMIT 5
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LEAGUE ALL-TIME PLAYOFF LEADERS  (separate board, playoff games only)
    // ═══════════════════════════════════════════════════════════════════════

    /** Top 5 all-time PLAYOFF scorers in a league. */
    public function leagueTop5PlayoffScorers(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   SUM(pgs.total_points)       AS total_points,
                   COUNT(DISTINCT pgs.game_id) AS games
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN seasons s ON g.season_id   = s.id
            WHERE s.league_id = ?
              AND g.game_type  = "playoff"
              AND g.status     = "completed"
            GROUP BY p.id
            HAVING total_points > 0
            ORDER BY total_points DESC
            LIMIT 5
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    /** Top 5 all-time PLAYOFF three-point shooters in a league. */
    public function leagueTop5PlayoffThreePointers(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   SUM(pgs.three_points_made)  AS total_3pt,
                   COUNT(DISTINCT pgs.game_id) AS games
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN seasons s ON g.season_id   = s.id
            WHERE s.league_id = ?
              AND g.game_type  = "playoff"
              AND g.status     = "completed"
            GROUP BY p.id
            HAVING total_3pt > 0
            ORDER BY total_3pt DESC
            LIMIT 5
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    /** Top 5 all-time PLAYOFF free-throw makers in a league. */
    public function leagueTop5PlayoffFreeThrows(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.first_name, p.last_name,
                   SUM(pgs.free_throws_made)   AS total_ft,
                   COUNT(DISTINCT pgs.game_id) AS games
            FROM player_game_stats pgs
            JOIN players p ON pgs.player_id = p.id
            JOIN games g   ON pgs.game_id   = g.id
            JOIN seasons s ON g.season_id   = s.id
            WHERE s.league_id = ?
              AND g.game_type  = "playoff"
              AND g.status     = "completed"
            GROUP BY p.id
            HAVING total_ft > 0
            ORDER BY total_ft DESC
            LIMIT 5
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PLAYER PROFILE STATS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Full career split for a player's profile page.
     *
     * Returns one row containing:
     *  - career_high          : best single-game pts (ALL games, incl. playoffs)
     *  - reg_games / reg_points / reg_ppg / reg_3pt / reg_3pt_att / reg_ft / reg_ft_att / reg_2pt
     *  - po_games  / po_points  / po_ppg  / po_3pt  / po_3pt_att  / po_ft  / po_ft_att  / po_2pt
     *  - total_games / seasons_played / teams_played_for
     */
    public function playerCareerStatsSplit(int $playerId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                MAX(pgs.total_points)                                             AS career_high,

                SUM(CASE WHEN g.game_type = "regular" THEN 1          ELSE 0 END) AS reg_games,
                COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.total_points      ELSE 0 END), 0) AS reg_points,
                COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.two_points_made   ELSE 0 END), 0) AS reg_2pt,
                COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.three_points_made ELSE 0 END), 0) AS reg_3pt,
                COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.three_points_attempted ELSE 0 END), 0) AS reg_3pt_att,
                COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.free_throws_made  ELSE 0 END), 0) AS reg_ft,
                COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.free_throws_attempted ELSE 0 END), 0) AS reg_ft_att,

                SUM(CASE WHEN g.game_type = "playoff" THEN 1          ELSE 0 END) AS po_games,
                COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.total_points      ELSE 0 END), 0) AS po_points,
                COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.two_points_made   ELSE 0 END), 0) AS po_2pt,
                COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.three_points_made ELSE 0 END), 0) AS po_3pt,
                COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.three_points_attempted ELSE 0 END), 0) AS po_3pt_att,
                COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.free_throws_made  ELSE 0 END), 0) AS po_ft,
                COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.free_throws_attempted ELSE 0 END), 0) AS po_ft_att,

                COUNT(DISTINCT g.id)         AS total_games,
                COUNT(DISTINCT g.season_id)  AS seasons_played,
                COUNT(DISTINCT sr.team_id)   AS teams_played_for
            FROM player_game_stats pgs
            JOIN games g    ON pgs.game_id  = g.id
            JOIN seasons s  ON g.season_id  = s.id
            LEFT JOIN season_rosters sr ON sr.season_id = s.id AND sr.player_id = ?
            WHERE pgs.player_id = ? AND g.status = "completed"
        ');
        $stmt->execute([$playerId, $playerId]);
        $row = $stmt->fetch() ?: [];

        // Compute derived PPG values
        if (!empty($row)) {
            $row['reg_ppg'] = $row['reg_games'] > 0
                ? round($row['reg_points'] / $row['reg_games'], 1) : 0.0;
            $row['po_ppg']  = $row['po_games']  > 0
                ? round($row['po_points']  / $row['po_games'],  1) : 0.0;
        }
        return $row;
    }

    /**
     * @deprecated Use playerCareerStatsSplit() for split regular/playoff career stats.
     * Kept for legacy compatibility only.
     */
    public function playerCareerStats(int $playerId): array
    {
        $split = $this->playerCareerStatsSplit($playerId);
        return [
            'total_games'      => $split['total_games']      ?? 0,
            'career_points'    => ($split['reg_points'] ?? 0) + ($split['po_points'] ?? 0),
            'career_ppg'       => $split['total_games'] > 0
                ? round((($split['reg_points'] ?? 0) + ($split['po_points'] ?? 0)) / $split['total_games'], 1)
                : 0.0,
            'seasons_played'   => $split['seasons_played']   ?? 0,
            'teams_played_for' => $split['teams_played_for'] ?? 0,
        ];
    }

    /**
     * Season-by-season history for a player's profile page.
     * Each row contains regular-season stats AND playoff stats for that season.
     */
    public function playerSeasonHistory(int $playerId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.id, s.name, s.status, t.name AS team_name,
                   sr.team_id, sr.jersey_number, sr.status AS roster_status,

                   SUM(CASE WHEN g.game_type = "regular" THEN 1 ELSE 0 END) AS reg_games,
                   COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.total_points           ELSE 0 END), 0) AS reg_points,
                   COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.three_points_made      ELSE 0 END), 0) AS reg_3pt_made,
                   COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.three_points_attempted ELSE 0 END), 0) AS reg_3pt_att,
                   COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.free_throws_made       ELSE 0 END), 0) AS reg_ft_made,
                   COALESCE(SUM(CASE WHEN g.game_type = "regular" THEN pgs.free_throws_attempted  ELSE 0 END), 0) AS reg_ft_att,

                   SUM(CASE WHEN g.game_type = "playoff" THEN 1 ELSE 0 END) AS po_games,
                   COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.total_points           ELSE 0 END), 0) AS po_points,
                   COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.three_points_made      ELSE 0 END), 0) AS po_3pt_made,
                   COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.three_points_attempted ELSE 0 END), 0) AS po_3pt_att,
                   COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.free_throws_made       ELSE 0 END), 0) AS po_ft_made,
                   COALESCE(SUM(CASE WHEN g.game_type = "playoff" THEN pgs.free_throws_attempted  ELSE 0 END), 0) AS po_ft_att

            FROM seasons s
            JOIN season_rosters sr ON s.id = sr.season_id AND sr.player_id = ?
            JOIN teams t           ON sr.team_id = t.id
            LEFT JOIN games g      ON g.season_id = s.id AND g.status = "completed"
            LEFT JOIN player_game_stats pgs ON pgs.game_id = g.id AND pgs.player_id = ?
            GROUP BY s.id, sr.team_id
            ORDER BY s.created_at DESC
        ');
        $stmt->execute([$playerId, $playerId]);
        $rows = $stmt->fetchAll();

        // Compute PPG per row
        foreach ($rows as &$row) {
            $row['reg_ppg'] = $row['reg_games'] > 0
                ? round($row['reg_points'] / $row['reg_games'], 1) : null;
            $row['po_ppg']  = $row['po_games']  > 0
                ? round($row['po_points']  / $row['po_games'],  1) : null;
        }
        return $rows;
    }

    /**
     * All-time REGULAR SEASON ranking for a player in a given stat within their league.
     * Filters: game_type = 'regular' only.
     *
     * @param  string $statExpr  SQL expression for the stat (e.g. "COALESCE(SUM(pgs.total_points),0)")
     * @return array{rank: int, total: int, value: int}
     */
    public function playerAllTimeRank(int $playerId, int $leagueId, string $statExpr): array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.id,
                   {$statExpr} AS stat_value,
                   RANK() OVER (ORDER BY {$statExpr} DESC) AS `rank`
            FROM players p
            LEFT JOIN player_game_stats pgs ON pgs.player_id  = p.id
            LEFT JOIN games g   ON pgs.game_id   = g.id
                                AND g.status      = 'completed'
                                AND g.game_type   = 'regular'
            LEFT JOIN seasons s ON g.season_id   = s.id
                                AND s.league_id   = ?
            WHERE p.league_id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$leagueId, $leagueId]);
        $rows  = $stmt->fetchAll();
        $total = count($rows);

        foreach ($rows as $row) {
            if ((int) $row['id'] === $playerId) {
                return [
                    'rank'  => (int) $row['rank'],
                    'total' => $total,
                    'value' => (int) $row['stat_value'],
                ];
            }
        }

        return ['rank' => 0, 'total' => $total, 'value' => 0];
    }

    /**
     * Recent games (all types) for a player's profile game log.
     */
    public function playerRecentGames(int $playerId, int $limit = 15): array
    {
        $stmt = $this->pdo->prepare('
            SELECT g.game_date, g.home_score, g.away_score, g.season_id, g.game_type,
                   g.playoff_round,
                   ht.name AS home_team, at.name AS away_team,
                   t.name  AS player_team,
                   pgs.total_points, pgs.two_points_made, pgs.two_points_attempted,
                   pgs.three_points_made, pgs.three_points_attempted,
                   pgs.free_throws_made, pgs.free_throws_attempted,
                   CASE
                       WHEN g.home_team_id = pgs.team_id THEN g.home_score
                       ELSE g.away_score
                   END AS player_team_score,
                   CASE
                       WHEN g.home_team_id = pgs.team_id THEN g.away_score
                       ELSE g.home_score
                   END AS opponent_score
            FROM player_game_stats pgs
            JOIN games g   ON pgs.game_id      = g.id
            JOIN teams ht  ON g.home_team_id   = ht.id
            JOIN teams at  ON g.away_team_id   = at.id
            JOIN teams t   ON pgs.team_id      = t.id
            WHERE pgs.player_id = ? AND g.status = "completed"
            ORDER BY g.game_date DESC, g.id DESC
            LIMIT ?
        ');
        $stmt->execute([$playerId, $limit]);
        return $stmt->fetchAll();
    }
}
