<?php
/**
 * The Battle 3x3 — Service Layer
 * RosterService
 *
 * Manages season_rosters: adding/removing players,
 * enforcing locking rules, and querying roster data.
 */

class RosterService
{
    public function __construct(private PDO $pdo) {}

    // ── Queries ──────────────────────────────────────────────────────────────

    /**
     * Full roster for a team in a season, ordered by jersey number.
     */
    public function forTeam(int $seasonId, int $teamId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT sr.*, p.first_name, p.last_name, p.position, p.photo
            FROM season_rosters sr
            JOIN players p ON sr.player_id = p.id
            WHERE sr.season_id = ? AND sr.team_id = ?
            ORDER BY sr.jersey_number
        ');
        $stmt->execute([$seasonId, $teamId]);
        return $stmt->fetchAll();
    }

    /**
     * All players in a league NOT yet on any roster this season.
     */
    public function available(int $leagueId, int $seasonId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.*
            FROM players p
            WHERE p.league_id = ?
              AND p.id NOT IN (
                  SELECT player_id FROM season_rosters WHERE season_id = ?
              )
            ORDER BY p.last_name, p.first_name
        ');
        $stmt->execute([$leagueId, $seasonId]);
        return $stmt->fetchAll();
    }

    /**
     * Games played per player in a season (for stat-preservation warnings).
     * Returns [player_id => game_count].
     */
    public function gamesPlayedMap(int $seasonId, array $playerIds): array
    {
        if (empty($playerIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT pgs.player_id, COUNT(DISTINCT pgs.game_id) AS gp
            FROM player_game_stats pgs
            JOIN games g ON pgs.game_id = g.id
            WHERE g.season_id = ? AND g.status = 'completed'
              AND pgs.player_id IN ($placeholders)
            GROUP BY pgs.player_id
        ");
        $stmt->execute([$seasonId, ...$playerIds]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['player_id']] = (int) $row['gp'];
        }
        return $map;
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * Add a player to a team roster.
     *
     * @throws PDOException on duplicate player or jersey.
     */
    public function add(int $seasonId, int $teamId, int $playerId, int $jerseyNumber): void
    {
        $this->pdo->prepare('
            INSERT INTO season_rosters (season_id, team_id, player_id, jersey_number)
            VALUES (?, ?, ?, ?)
        ')->execute([$seasonId, $teamId, $playerId, $jerseyNumber]);
    }

    /**
     * Remove a player from a team roster by roster row ID.
     * Stats are preserved — this only removes the roster entry.
     */
    public function remove(int $rosterId, int $seasonId, int $teamId): void
    {
        $this->pdo->prepare('
            DELETE FROM season_rosters
            WHERE id = ? AND season_id = ? AND team_id = ?
        ')->execute([$rosterId, $seasonId, $teamId]);
    }

    // ── Guards ───────────────────────────────────────────────────────────────

    /**
     * Whether the roster for this season is currently editable.
     * Editable during: upcoming, active.
     * Locked during:   playoffs, completed.
     */
    public function isEditable(int $seasonId): bool
    {
        $stmt = $this->pdo->prepare('SELECT status FROM seasons WHERE id = ?');
        $stmt->execute([$seasonId]);
        $season = $stmt->fetch();
        return $season && in_array($season['status'], ['upcoming', 'active']);
    }

    /**
     * How many games has a specific player played in this season.
     * Used to warn admins before removing a player who already has stats.
     */
    public function gamesPlayedByPlayer(int $seasonId, int $playerId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(DISTINCT pgs.game_id)
            FROM player_game_stats pgs
            JOIN games g ON pgs.game_id = g.id
            WHERE g.season_id = ? AND g.status = "completed"
              AND pgs.player_id = ?
        ');
        $stmt->execute([$seasonId, $playerId]);
        return (int) $stmt->fetchColumn();
    }
}
