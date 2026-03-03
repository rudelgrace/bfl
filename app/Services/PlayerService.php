<?php
/**
 * The Battle 3x3 — Service Layer
 * PlayerService
 *
 * Database operations for the `players` table.
 */

class PlayerService
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.*, l.name AS league_name
            FROM players p
            JOIN leagues l ON p.league_id = l.id
            WHERE p.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function forLeague(int $leagueId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT p.*,
                   COUNT(DISTINCT sr.season_id) AS seasons_played
            FROM players p
            LEFT JOIN season_rosters sr ON sr.player_id = p.id
            WHERE p.league_id = ?
            GROUP BY p.id
            ORDER BY p.last_name, p.first_name
        ');
        $stmt->execute([$leagueId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $this->pdo->prepare('
            INSERT INTO players (league_id, first_name, last_name, position, photo)
            VALUES (:league_id, :first_name, :last_name, :position, :photo)
        ')->execute([
            'league_id'  => $data['league_id'],
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'position'   => $data['position'] ?? null,
            'photo'      => $data['photo']    ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->pdo->prepare('
            UPDATE players
            SET first_name = :first_name, last_name = :last_name,
                position = :position, photo = :photo
            WHERE id = :id
        ')->execute([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'position'   => $data['position'] ?? null,
            'photo'      => $data['photo']    ?? null,
            'id'         => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM season_rosters WHERE player_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM players WHERE id = ?')->execute([$id]);
        // player_game_stats intentionally kept — historical record
    }
}
