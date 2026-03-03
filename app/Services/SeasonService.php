<?php
/**
 * The Battle 3x3 — Service Layer
 * SeasonService
 *
 * Owns all business logic and database operations for seasons.
 * Lifecycle: upcoming → active → playoffs → completed
 */

class SeasonService
{
    public function __construct(private PDO $pdo) {}

    // ── Queries ──────────────────────────────────────────────────────────────

    /**
     * Find a season by PK, joined with its league name.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.*, l.name AS league_name
            FROM seasons s
            JOIN leagues l ON s.league_id = l.id
            WHERE s.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * All seasons for a league, newest first.
     */
    public function forLeague(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT s.*,
                COUNT(DISTINCT st.team_id) AS team_count,
                COUNT(DISTINCT g.id)       AS game_count,
                SUM(g.status = "completed") AS games_completed
            FROM seasons s
            LEFT JOIN season_teams st ON st.season_id = s.id
            LEFT JOIN games g         ON g.season_id  = s.id AND g.game_type = "regular"
            WHERE s.league_id = ?
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    /**
     * Whether the season is locked for roster/schedule changes.
     */
    public function isLocked(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT status FROM seasons WHERE id = ?');
        $stmt->execute([$id]);
        $season = $stmt->fetch();
        return $season && in_array($season['status'], ['playoffs', 'completed']);
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * Create a new season. Returns the new ID.
     */
    public function create(array $data): int
    {
        $this->pdo->prepare('
            INSERT INTO seasons (league_id, name, games_per_team, playoff_teams_count, status)
            VALUES (:league_id, :name, :games_per_team, :playoff_teams_count, "upcoming")
        ')->execute([
            'league_id'           => $data['league_id'],
            'name'                => $data['name'],
            'games_per_team'      => $data['games_per_team']      ?? 2,
            'playoff_teams_count' => $data['playoff_teams_count'] ?? 4,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update season metadata (name, games_per_team, playoff_teams_count).
     */
    public function update(int $id, array $data): void
    {
        $this->pdo->prepare('
            UPDATE seasons
            SET name = :name,
                games_per_team      = :games_per_team,
                playoff_teams_count = :playoff_teams_count
            WHERE id = :id
        ')->execute([
            'name'                => $data['name'],
            'games_per_team'      => $data['games_per_team'],
            'playoff_teams_count' => $data['playoff_teams_count'],
            'id'                  => $id,
        ]);
    }

    /**
     * Delete a season and all related records.
     */
    public function delete(int $id): void
    {
        $this->pdo->prepare('
            DELETE pgs FROM player_game_stats pgs
            JOIN games g ON pgs.game_id = g.id
            WHERE g.season_id = ?
        ')->execute([$id]);

        $this->pdo->prepare('DELETE FROM games         WHERE season_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM standings     WHERE season_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM season_rosters WHERE season_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM season_teams  WHERE season_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM seasons       WHERE id = ?')->execute([$id]);
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Start a season (upcoming → active).
     * Returns false if the season is not in upcoming state.
     */
    public function start(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT status FROM seasons WHERE id = ? AND status = "upcoming"');
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            return false;
        }

        $this->pdo->prepare('
            UPDATE seasons SET status = "active", start_date = CURDATE() WHERE id = ?
        ')->execute([$id]);

        return true;
    }

    /**
     * Move a season to playoffs (active → playoffs).
     * Fails if any regular season games are still pending.
     */
    public function moveToPlayoffs(int $id): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT (SELECT COUNT(*) FROM games
                    WHERE season_id = s.id AND game_type = "regular" AND status != "completed") AS pending
            FROM seasons s
            WHERE s.id = ? AND s.status = "active"
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row || (int) $row['pending'] > 0) {
            return false;
        }

        $this->pdo->prepare('UPDATE seasons SET status = "playoffs" WHERE id = ?')->execute([$id]);
        $this->calculateRegularSeasonMVP($id);
        return true;
    }

    /**
     * Complete a season (playoffs → completed).
     * Called automatically after the last playoff game is entered.
     */
    public function checkAndComplete(int $id): void
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS done
            FROM games
            WHERE season_id = ? AND game_type = "playoff"
        ');
        $stmt->execute([$id]);
        $games = $stmt->fetch();

        if ($games['total'] > 0 && $games['total'] == $games['done']) {
            $this->calculatePlayoffsMVP($id);
            $this->pdo->prepare('
                UPDATE seasons SET status = "completed", end_date = CURDATE() WHERE id = ?
            ')->execute([$id]);

            // Propagate to league if all seasons are done
            $leagueStmt = $this->pdo->prepare('SELECT league_id FROM seasons WHERE id = ?');
            $leagueStmt->execute([$id]);
            $leagueId = (int) $leagueStmt->fetchColumn();
            // Imported at runtime via functions.php; call via helper to avoid circular dependency
            app()['leagues']->autoComplete($leagueId);
        }
    }

    /**
     * After every regular-season game, check if all are done → trigger playoff move
     * and automatically generate the bracket.
     */
    public function checkAndMoveToPlayoffs(int $id): void
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS done
            FROM games
            WHERE season_id = ? AND game_type = "regular"
        ');
        $stmt->execute([$id]);
        $games = $stmt->fetch();

        if ($games['total'] > 0 && $games['total'] == $games['done']) {
            if ($this->moveToPlayoffs($id)) {
                // Auto-generate bracket immediately after last regular game
                // Use a fresh PlayoffService instance to avoid circular dependency
                $playoff = new PlayoffService($this->pdo);
                try {
                    $playoff->generateBracket($id);
                } catch (\Throwable) {
                    // Bracket generation failure must not roll back the moveToPlayoffs.
                    // The admin can manually trigger it from the dashboard.
                }
            }
        }
    }

    // ── Schedule Generation ──────────────────────────────────────────────────

    /**
     * Generate a round-robin schedule.
     * Returns an array of [home_team_id, away_team_id] pairs.
     *
     * @param int[] $teamIds
     */
    public function generateSchedule(array $teamIds, int $gamesPerTeam): array
    {
        $n = count($teamIds);
        if ($n < 2) {
            return [];
        }

        // Pad to even number with a "bye" slot
        if ($n % 2 !== 0) {
            $teamIds[] = null;
            $n++;
        }

        $rounds   = $n - 1;
        $fixed    = $teamIds[0];
        $rotate   = array_slice($teamIds, 1);
        $allRounds = [];

        for ($r = 0; $r < $rounds; $r++) {
            $round  = [];
            $circle = array_merge([$fixed], $rotate);

            for ($i = 0; $i < $n / 2; $i++) {
                $home = $circle[$i];
                $away = $circle[$n - 1 - $i];
                if ($home !== null && $away !== null) {
                    $round[] = [$home, $away];
                }
            }

            $allRounds[] = $round;
            array_unshift($rotate, array_pop($rotate));
        }

        // Repeat rounds until gamesPerTeam is satisfied
        $gamesCount = array_fill_keys(array_filter($teamIds), 0);
        $schedule   = [];
        $roundIndex = 0;

        while (count($schedule) < ($n * $gamesPerTeam) / 2) {
            $round = $allRounds[$roundIndex % count($allRounds)];

            foreach ($round as [$home, $away]) {
                if (
                    $gamesCount[$home] < $gamesPerTeam &&
                    $gamesCount[$away] < $gamesPerTeam
                ) {
                    $schedule[]        = [$home, $away];
                    $gamesCount[$home]++;
                    $gamesCount[$away]++;
                }
            }

            $roundIndex++;
            if ($roundIndex > $rounds * 10) {
                break; // Safety valve
            }
        }

        return $schedule;
    }

    // ── Awards ───────────────────────────────────────────────────────────────

    /**
     * Determine and persist the Regular Season MVP (top scorer in regular season games).
     * Called automatically when the season transitions to playoffs.
     */
    public function calculateRegularSeasonMVP(int $seasonId): void
    {
        $stmt = $this->pdo->prepare('
            SELECT pgs.player_id, SUM(pgs.total_points) AS pts
            FROM player_game_stats pgs
            JOIN games g ON pgs.game_id = g.id
            WHERE g.season_id = ? AND g.game_type = "regular"
            GROUP BY pgs.player_id
            ORDER BY pts DESC
            LIMIT 1
        ');
        $stmt->execute([$seasonId]);
        $mvp = $stmt->fetch();

        if ($mvp) {
            $this->pdo->prepare('UPDATE seasons SET regular_season_mvp_id = ? WHERE id = ?')
                ->execute([$mvp['player_id'], $seasonId]);
        }
    }

    /**
     * Determine and persist the playoffs MVP (top scorer in playoff games).
     */
    public function calculatePlayoffsMVP(int $seasonId): void
    {
        $stmt = $this->pdo->prepare('
            SELECT pgs.player_id, SUM(pgs.total_points) AS pts
            FROM player_game_stats pgs
            JOIN games g ON pgs.game_id = g.id
            WHERE g.season_id = ? AND g.game_type = "playoff"
            GROUP BY pgs.player_id
            ORDER BY pts DESC
            LIMIT 1
        ');
        $stmt->execute([$seasonId]);
        $mvp = $stmt->fetch();

        if ($mvp) {
            $this->pdo->prepare('UPDATE seasons SET playoffs_mvp_id = ? WHERE id = ?')
                ->execute([$mvp['player_id'], $seasonId]);
        }
    }
}
