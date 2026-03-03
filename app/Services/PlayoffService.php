<?php
/**
 * The Battle 3x3 — Service Layer
 * PlayoffService
 *
 * Owns all business logic for the single-elimination playoff bracket.
 *
 * Supported formats:
 *   • 4-team  → 1 Semifinal round  (2 games) + Final    = 3 games
 *   • 8-team  → 1 Quarterfinal round (4 games) + 2 SFs + Final = 7 games
 *
 * Seeding (highest vs lowest):
 *   4-team:  SF1=1v4, SF2=2v3, Final=winner(SF1) v winner(SF2)
 *   8-team:  QF1=1v8, QF2=2v7, QF3=3v6, QF4=4v5
 *             SF1=winner(QF1) v winner(QF4), SF2=winner(QF2) v winner(QF3)
 *             Final=winner(SF1) v winner(SF2)
 */
class PlayoffService
{
    public function __construct(private PDO $pdo) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Generate a fresh bracket for the season.
     *
     * Concurrency-safe: acquires a row-level lock on the season row
     * before making any changes, and checks for existing games inside
     * the same transaction so double-clicks / race conditions are harmless.
     *
     * @throws RuntimeException on validation failure (caller should catch)
     */
    public function generateBracket(int $seasonId): int
    {
        $this->pdo->beginTransaction();

        try {
            // ── Row-level lock — prevents concurrent generation ──────────────
            $stmt = $this->pdo->prepare(
                'SELECT id, status, playoff_teams_count
                 FROM seasons WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$seasonId]);
            $season = $stmt->fetch();

            if (!$season) {
                throw new RuntimeException('Season not found.');
            }

            // Guard: only generate when active or already in playoffs (reset)
            if (!in_array($season['status'], ['active', 'playoffs'])) {
                throw new RuntimeException(
                    'Playoff bracket can only be generated for an active season.'
                );
            }

            // Guard: all regular-season games must be complete
            $pendingStmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM games
                 WHERE season_id = ? AND game_type = 'regular' AND status != 'completed'"
            );
            $pendingStmt->execute([$seasonId]);
            if ((int) $pendingStmt->fetchColumn() > 0) {
                throw new RuntimeException(
                    'All regular-season games must be completed before generating the playoff bracket.'
                );
            }

            // Delete any existing playoff games (safe reset)
            $this->pdo->prepare(
                "DELETE FROM games WHERE season_id = ? AND game_type = 'playoff'"
            )->execute([$seasonId]);

            // ── Determine bracket size ────────────────────────────────────────
            $standings   = $this->getStandings($seasonId);
            $teamCount   = count($standings);
            $requestedPO = (int) $season['playoff_teams_count'];

            $bracketSize = $this->resolveBracketSize($requestedPO, $teamCount);
            $seeds       = array_slice($standings, 0, $bracketSize);

            // ── Insert games ──────────────────────────────────────────────────
            $gamesCreated = match ($bracketSize) {
                4 => $this->insertFourTeamBracket($seasonId, $seeds),
                8 => $this->insertEightTeamBracket($seasonId, $seeds),
            };

            // ── Advance season status ─────────────────────────────────────────
            $this->pdo->prepare(
                "UPDATE seasons SET status = 'playoffs' WHERE id = ? AND status = 'active'"
            )->execute([$seasonId]);

            $this->pdo->commit();
            return $gamesCreated;

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * After a playoff game result is saved, advance the winner into any
     * downstream bracket game that references this game as a source.
     *
     * This is the "forward correction" mechanism — works for both first-time
     * entry and edits of already-completed games.
     */
    public function propagateWinner(int $completedGameId): void
    {
        // Load the completed game
        $gameStmt = $this->pdo->prepare(
            'SELECT id, home_team_id, away_team_id, home_score, away_score
             FROM games WHERE id = ? AND status = "completed"'
        );
        $gameStmt->execute([$completedGameId]);
        $game = $gameStmt->fetch();

        if (!$game) {
            return; // game not complete yet — nothing to propagate
        }

        // Determine winner
        $winnerId = ((int) $game['home_score'] >= (int) $game['away_score'])
            ? $game['home_team_id']
            : $game['away_team_id'];

        // Find downstream games where this game is the home source
        $homeChildStmt = $this->pdo->prepare(
            'SELECT id FROM games WHERE home_source_game_id = ?'
        );
        $homeChildStmt->execute([$completedGameId]);
        foreach ($homeChildStmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $this->pdo->prepare('UPDATE games SET home_team_id = ? WHERE id = ?')
                ->execute([$winnerId, $childId]);
        }

        // Find downstream games where this game is the away source
        $awayChildStmt = $this->pdo->prepare(
            'SELECT id FROM games WHERE away_source_game_id = ?'
        );
        $awayChildStmt->execute([$completedGameId]);
        foreach ($awayChildStmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $this->pdo->prepare('UPDATE games SET away_team_id = ? WHERE id = ?')
                ->execute([$winnerId, $childId]);
        }
    }

    /**
     * Reset the bracket: delete all playoff games and regenerate from
     * current standings. Season must be in 'playoffs' status.
     *
     * @throws RuntimeException
     */
    public function resetBracket(int $seasonId): int
    {
        return $this->generateBracket($seasonId);
    }

    // ── Private — Bracket builders ────────────────────────────────────────────

    /**
     * 4-team single elimination:
     *   pos1: Semifinal — seed1 (home) vs seed4 (away)
     *   pos2: Semifinal — seed2 (home) vs seed3 (away)
     *   pos3: Final     — winner(pos1) vs winner(pos2)
     */
    private function insertFourTeamBracket(int $seasonId, array $seeds): int
    {
        $date   = date('Y-m-d');
        $insert = $this->prepareInsert();

        // Semifinal 1 — seed 1 vs seed 4
        $insert->execute([
            $seasonId,
            $seeds[0]['team_id'], $seeds[3]['team_id'],
            $date, 'Semifinal', 1, null, null,
        ]);
        $sf1Id = (int) $this->pdo->lastInsertId();

        // Semifinal 2 — seed 2 vs seed 3
        $insert->execute([
            $seasonId,
            $seeds[1]['team_id'], $seeds[2]['team_id'],
            $date, 'Semifinal', 2, null, null,
        ]);
        $sf2Id = (int) $this->pdo->lastInsertId();

        // Final — TBD placeholder uses seed 1 & 2 (overwritten by propagation)
        $insert->execute([
            $seasonId,
            $seeds[0]['team_id'], $seeds[1]['team_id'],
            $date, 'Final', 3, $sf1Id, $sf2Id,
        ]);

        return 3;
    }

    /**
     * 8-team single elimination:
     *   pos1-4: Quarterfinals (1v8, 2v7, 3v6, 4v5)
     *   pos5-6: Semifinals   (QF1w vs QF4w, QF2w vs QF3w)
     *   pos7:   Final        (SF1w vs SF2w)
     */
    private function insertEightTeamBracket(int $seasonId, array $seeds): int
    {
        $date   = date('Y-m-d');
        $insert = $this->prepareInsert();

        // Quarterfinals — standard 1v8, 2v7, 3v6, 4v5 seeding
        $qfPairs = [
            [0, 7], // seed 1 vs 8
            [1, 6], // seed 2 vs 7
            [2, 5], // seed 3 vs 6
            [3, 4], // seed 4 vs 5
        ];
        $qfIds = [];
        foreach ($qfPairs as $pos => [$homeIdx, $awayIdx]) {
            $insert->execute([
                $seasonId,
                $seeds[$homeIdx]['team_id'], $seeds[$awayIdx]['team_id'],
                $date, 'Quarterfinal', $pos + 1, null, null,
            ]);
            $qfIds[] = (int) $this->pdo->lastInsertId();
        }

        // Semifinal 1 — QF1 winner vs QF4 winner
        $insert->execute([
            $seasonId,
            $seeds[0]['team_id'], $seeds[3]['team_id'],
            $date, 'Semifinal', 5, $qfIds[0], $qfIds[3],
        ]);
        $sf1Id = (int) $this->pdo->lastInsertId();

        // Semifinal 2 — QF2 winner vs QF3 winner
        $insert->execute([
            $seasonId,
            $seeds[1]['team_id'], $seeds[2]['team_id'],
            $date, 'Semifinal', 6, $qfIds[1], $qfIds[2],
        ]);
        $sf2Id = (int) $this->pdo->lastInsertId();

        // Final — SF1 winner vs SF2 winner
        $insert->execute([
            $seasonId,
            $seeds[0]['team_id'], $seeds[1]['team_id'],
            $date, 'Final', 7, $sf1Id, $sf2Id,
        ]);

        return 7;
    }

    // ── Private — helpers ──────────────────────────────────────────────────────

    /**
     * Resolve 4 or 8 from the admin-configured count and available teams.
     * Never over-engineers beyond these two formats.
     *
     * @throws RuntimeException if fewer than 4 teams available
     */
    private function resolveBracketSize(int $requested, int $available): int
    {
        if ($available < 4) {
            throw new RuntimeException(
                "Not enough teams for a playoff bracket (need 4, have $available)."
            );
        }

        if ($requested >= 8 && $available >= 8) {
            return 8;
        }

        return 4;
    }

    private function prepareInsert(): \PDOStatement
    {
        return $this->pdo->prepare(
            "INSERT INTO games
                (season_id, home_team_id, away_team_id, game_date,
                 status, game_type, playoff_round, playoff_position,
                 home_source_game_id, away_source_game_id)
             VALUES (?, ?, ?, ?, 'scheduled', 'playoff', ?, ?, ?, ?)"
        );
    }

    private function getStandings(int $seasonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT st.*, t.name AS team_name
             FROM standings st
             JOIN teams t ON st.team_id = t.id
             WHERE st.season_id = ?
             ORDER BY st.wins DESC, st.point_differential DESC, t.name ASC'
        );
        $stmt->execute([$seasonId]);
        return $stmt->fetchAll();
    }
}
