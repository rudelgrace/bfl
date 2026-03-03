<?php
/**
 * The Battle 3x3 — Service Layer
 * GameService
 *
 * Handles game result entry, standings recalculation,
 * and all writes to the `games` and `player_game_stats` tables.
 */

class GameService
{
    public function __construct(private PDO $pdo) {}

    // ── Queries ──────────────────────────────────────────────────────────────

    /**
     * Find a single game by PK with team name joins.
     */
    public function find(int $gameId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT g.*,
                   s.league_id, s.name AS season_name,
                   ht.name AS home_team_name,
                   at.name AS away_team_name
            FROM games g
            JOIN seasons s ON g.season_id = s.id
            JOIN teams ht ON g.home_team_id = ht.id
            JOIN teams at ON g.away_team_id = at.id
            WHERE g.id = ?
        ');
        $stmt->execute([$gameId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Games for a season, by type (regular|playoff), ordered for display.
     */
    public function forSeason(int $seasonId, string $type = 'regular'): array
    {
        $stmt = $this->pdo->prepare('
            SELECT g.*, ht.name AS home_team, at.name AS away_team
            FROM games g
            JOIN teams ht ON g.home_team_id = ht.id
            JOIN teams at ON g.away_team_id = at.id
            WHERE g.season_id = ? AND g.game_type = ?
            ORDER BY g.game_date, g.id
        ');
        $stmt->execute([$seasonId, $type]);
        return $stmt->fetchAll();
    }

    /**
     * Existing player stats for a game and team, keyed by player_id.
     */
    public function statsForTeam(int $gameId, int $teamId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM player_game_stats WHERE game_id = ? AND team_id = ?
        ');
        $stmt->execute([$gameId, $teamId]);
        $rows = $stmt->fetchAll();
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['player_id']] = $row;
        }
        return $indexed;
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * Save all player stats for a game, update the game score, and refresh standings.
     *
     * @param array $statsByTeam   ['home' => [player_id => [...fields]], 'away' => [...]]
     * @param array $teamIds       ['home' => int, 'away' => int]
     * @param int   $manualHome    Override score (0 = compute from stats)
     * @param int   $manualAway
     * @param bool  $useManual
     * @param string|null $gameDate
     */
    public function saveResults(
        int $gameId,
        array $statsByTeam,
        array $teamIds,
        ?string $gameDate = null,
        bool $useManual = false,
        int $manualHome = 0,
        int $manualAway = 0
    ): void {
        $this->pdo->beginTransaction();

        try {
            // Upsert player stats
            foreach ($statsByTeam as $side => $players) {
                $tid = $teamIds[$side];
                foreach ($players as $pid => $s) {
                    $pid  = (int) $pid;
                    $s2m  = max(0, (int) ($s['two_points_made']          ?? 0));
                    $s2a  = max($s2m, (int) ($s['two_points_attempted']  ?? 0));
                    $s3m  = max(0, (int) ($s['three_points_made']        ?? 0));
                    $s3a  = max($s3m, (int) ($s['three_points_attempted']?? 0));
                    $ftm  = max(0, (int) ($s['free_throws_made']         ?? 0));
                    $fta  = max($ftm, (int) ($s['free_throws_attempted'] ?? 0));

                    $exists = $this->pdo->prepare('SELECT id FROM player_game_stats WHERE game_id = ? AND player_id = ?');
                    $exists->execute([$gameId, $pid]);
                    $eid = $exists->fetchColumn();

                    if ($eid) {
                        $this->pdo->prepare('
                            UPDATE player_game_stats
                            SET two_points_made = ?, two_points_attempted = ?,
                                three_points_made = ?, three_points_attempted = ?,
                                free_throws_made = ?, free_throws_attempted = ?
                            WHERE id = ?
                        ')->execute([$s2m, $s2a, $s3m, $s3a, $ftm, $fta, $eid]);
                    } else {
                        $this->pdo->prepare('
                            INSERT INTO player_game_stats
                                (game_id, player_id, team_id, two_points_made, two_points_attempted,
                                 three_points_made, three_points_attempted, free_throws_made, free_throws_attempted)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ')->execute([$gameId, $pid, $tid, $s2m, $s2a, $s3m, $s3a, $ftm, $fta]);
                    }
                }
            }

            // Compute score from stats unless manual override
            if ($useManual) {
                $homeScore = $manualHome;
                $awayScore = $manualAway;
            } else {
                $sc = $this->pdo->prepare('
                    SELECT team_id, SUM(total_points) AS ts
                    FROM player_game_stats
                    WHERE game_id = ?
                    GROUP BY team_id
                ');
                $sc->execute([$gameId]);
                $scores = [];
                foreach ($sc->fetchAll() as $row) {
                    $scores[$row['team_id']] = (int) $row['ts'];
                }
                $homeScore = $scores[$teamIds['home']] ?? 0;
                $awayScore = $scores[$teamIds['away']] ?? 0;
            }

            $gd = $gameDate ?: date('Y-m-d');
            $this->pdo->prepare("
                UPDATE games SET home_score = ?, away_score = ?, status = 'completed', game_date = ?
                WHERE id = ?
            ")->execute([$homeScore, $awayScore, $gd, $gameId]);

            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update only the game date (inline date picker in the schedule view).
     */
    public function updateDate(int $gameId, string $date): void
    {
        $this->pdo->prepare('UPDATE games SET game_date = ? WHERE id = ?')
            ->execute([$date, $gameId]);
    }

    // ── Standings ────────────────────────────────────────────────────────────

    /**
     * Rebuild the standings table from scratch for a season.
     * Includes regular games always, playoff games when status ∈ {playoffs, completed}.
     */
    public function updateStandings(int $seasonId): void
    {
        $seasonStmt = $this->pdo->prepare('SELECT status FROM seasons WHERE id = ?');
        $seasonStmt->execute([$seasonId]);
        $season = $seasonStmt->fetch();

        // Reset standings
        $this->pdo->prepare('DELETE FROM standings WHERE season_id = ?')->execute([$seasonId]);

        $teams = $this->pdo->prepare('SELECT team_id FROM season_teams WHERE season_id = ?');
        $teams->execute([$seasonId]);

        foreach ($teams->fetchAll(PDO::FETCH_COLUMN) as $teamId) {
            $this->pdo->prepare('INSERT INTO standings (season_id, team_id) VALUES (?, ?)')->execute([$seasonId, $teamId]);
        }

        $typeClause = $season && in_array($season['status'], ['playoffs', 'completed'])
            ? "AND (game_type = 'regular' OR game_type = 'playoff')"
            : "AND game_type = 'regular'";

        $games = $this->pdo->prepare("
            SELECT * FROM games
            WHERE season_id = ? AND status = 'completed' $typeClause
        ");
        $games->execute([$seasonId]);

        foreach ($games->fetchAll() as $g) {
            $homeScore = (int) $g['home_score'];
            $awayScore = (int) $g['away_score'];
            $diff      = abs($homeScore - $awayScore);

            if ($homeScore >= $awayScore) {
                $this->applyResult($seasonId, $g['home_team_id'], 1, 0, $homeScore, $awayScore,  $diff);
                $this->applyResult($seasonId, $g['away_team_id'], 0, 1, $awayScore, $homeScore, -$diff);
            } else {
                $this->applyResult($seasonId, $g['away_team_id'], 1, 0, $awayScore, $homeScore,  $diff);
                $this->applyResult($seasonId, $g['home_team_id'], 0, 1, $homeScore, $awayScore, -$diff);
            }
        }
    }

    /**
     * Get the current standings for a season.
     */
    public function getStandings(int $seasonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT st.*, t.name AS team_name, t.logo
            FROM standings st
            JOIN teams t ON st.team_id = t.id
            WHERE st.season_id = ?
            ORDER BY st.wins DESC, st.point_differential DESC, t.name ASC
        ');
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function applyResult(
        int $seasonId, int $teamId,
        int $w, int $l, int $pf, int $pa, int $diff
    ): void {
        $this->pdo->prepare('
            UPDATE standings
            SET wins = wins + ?, losses = losses + ?,
                points_for = points_for + ?, points_against = points_against + ?,
                point_differential = point_differential + ?
            WHERE season_id = ? AND team_id = ?
        ')->execute([$w, $l, $pf, $pa, $diff, $seasonId, $teamId]);
    }
}
