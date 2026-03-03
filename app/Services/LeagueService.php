<?php
/**
 * The Battle 3x3 — Service Layer
 * LeagueService
 *
 * Owns all database interactions for the `leagues` table.
 * No HTTP logic here — no redirects, no header(), no $_GET.
 * Callers (admin pages, functions.php shim) handle HTTP concerns.
 */

class LeagueService
{
    public function __construct(private PDO $pdo) {}

    // ── Queries ──────────────────────────────────────────────────────────────

    /**
     * Find a single league by PK. Returns null if not found.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM leagues WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Return all leagues, newest first.
     */
    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM leagues ORDER BY created_at DESC')->fetchAll();
    }

    /**
     * Summary list: leagues enriched with team/player/season counts.
     */
    public function allWithStats(): array
    {
        return $this->pdo->query("
            SELECT l.*,
                COUNT(DISTINCT t.id)  AS team_count,
                COUNT(DISTINCT p.id)  AS player_count,
                COUNT(DISTINCT s.id)  AS season_count
            FROM leagues l
            LEFT JOIN teams   t ON t.league_id = l.id
            LEFT JOIN players p ON p.league_id = l.id
            LEFT JOIN seasons s ON s.league_id = l.id
            GROUP BY l.id
            ORDER BY l.created_at DESC
        ")->fetchAll();
    }

    // ── Mutations ────────────────────────────────────────────────────────────

    /**
     * Create a new league. Returns the new ID.
     */
    public function create(array $data): int
    {
        $this->pdo->prepare('
            INSERT INTO leagues (name, description, logo, status)
            VALUES (:name, :description, :logo, :status)
        ')->execute([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'logo'        => $data['logo']         ?? null,
            'status'      => $data['status']        ?? 'active',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing league by PK.
     */
    public function update(int $id, array $data): void
    {
        $this->pdo->prepare('
            UPDATE leagues
            SET name = :name, description = :description, logo = :logo, status = :status
            WHERE id = :id
        ')->execute([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'logo'        => $data['logo']         ?? null,
            'status'      => $data['status']        ?? 'active',
            'id'          => $id,
        ]);
    }

    /**
     * Delete a league and cascade-clean related data.
     * NOTE: relies on ON DELETE CASCADE in the schema where possible;
     * otherwise removes manually in safe order.
     */
    public function delete(int $id): void
    {
        // Cascade order: stats → games → seasons/rosters/teams → league
        $this->pdo->prepare('
            DELETE pgs FROM player_game_stats pgs
            JOIN games g ON pgs.game_id = g.id
            JOIN seasons s ON g.season_id = s.id
            WHERE s.league_id = ?
        ')->execute([$id]);

        $this->pdo->prepare('
            DELETE g FROM games g
            JOIN seasons s ON g.season_id = s.id
            WHERE s.league_id = ?
        ')->execute([$id]);

        $this->pdo->prepare('
            DELETE sr FROM season_rosters sr
            JOIN seasons s ON sr.season_id = s.id
            WHERE s.league_id = ?
        ')->execute([$id]);

        $this->pdo->prepare('
            DELETE st FROM standings st
            JOIN seasons s ON st.season_id = s.id
            WHERE s.league_id = ?
        ')->execute([$id]);

        $this->pdo->prepare('DELETE FROM seasons  WHERE league_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM teams    WHERE league_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM players  WHERE league_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM leagues  WHERE id = ?')->execute([$id]);
    }

    // ── Aggregates ───────────────────────────────────────────────────────────

    /**
     * Count of teams in a league.
     */
    public function teamCount(int $leagueId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM teams WHERE league_id = ?');
        $stmt->execute([$leagueId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count of players in a league.
     */
    public function playerCount(int $leagueId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM players WHERE league_id = ?');
        $stmt->execute([$leagueId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Mark a league as completed if all its seasons are completed.
     * Called automatically after season lifecycle events.
     */
    public function autoComplete(int $leagueId): void
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed
            FROM seasons
            WHERE league_id = ?
        ');
        $stmt->execute([$leagueId]);
        $row = $stmt->fetch();

        if ($row['total'] > 0 && $row['total'] == $row['completed']) {
            $this->pdo->prepare('UPDATE leagues SET status = "completed" WHERE id = ?')
                ->execute([$leagueId]);
        }
    }
}
