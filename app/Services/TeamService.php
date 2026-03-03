<?php
/**
 * The Battle 3x3 — Service Layer
 * TeamService
 *
 * Database operations for the `teams` table.
 */

class TeamService
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teams WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function forLeague(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.*,
                   COUNT(DISTINCT sr.player_id) AS total_players
            FROM teams t
            LEFT JOIN season_rosters sr ON sr.team_id = t.id
            WHERE t.league_id = ?
            GROUP BY t.id
            ORDER BY t.name
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    /**
     * Teams in a season with their current roster size.
     */
    public function forSeason(int $seasonId, int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.*, COUNT(sr.player_id) AS roster_size
            FROM teams t
            JOIN season_teams st ON t.id = st.team_id AND st.season_id = ?
            LEFT JOIN season_rosters sr ON sr.team_id = t.id AND sr.season_id = ?
            WHERE t.league_id = ?
            GROUP BY t.id
            ORDER BY t.name
        ');
        $stmt->execute([$seasonId, $seasonId, $leagueId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $this->pdo->prepare('
            INSERT INTO teams (league_id, name, logo)
            VALUES (:league_id, :name, :logo)
        ')->execute([
            'league_id' => $data['league_id'],
            'name'      => $data['name'],
            'logo'      => $data['logo'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->pdo->prepare('
            UPDATE teams SET name = :name, logo = :logo WHERE id = :id
        ')->execute([
            'name' => $data['name'],
            'logo' => $data['logo'] ?? null,
            'id'   => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM season_rosters WHERE team_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM season_teams   WHERE team_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM standings      WHERE team_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM teams          WHERE id = ?')->execute([$id]);
    }
}
