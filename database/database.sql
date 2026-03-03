-- ============================================================
-- THE BATTLE 3x3 
-- Complete schema including rules & structure columns on leagues.
-- Compatible with all backend admin and public API code.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS player_game_stats;
DROP TABLE IF EXISTS standings;
DROP TABLE IF EXISTS games;
DROP TABLE IF EXISTS season_rosters;
DROP TABLE IF EXISTS season_teams;
DROP TABLE IF EXISTS seasons;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS leagues;
DROP TABLE IF EXISTS admins;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- ADMINS
-- ============================================================
CREATE TABLE admins (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)  UNIQUE NOT NULL,
    password     VARCHAR(255) NOT NULL COMMENT 'bcrypt hashed',
    email        VARCHAR(100) UNIQUE NOT NULL,
    full_name    VARCHAR(100),
    role         ENUM('super_admin','admin','scorer') NOT NULL DEFAULT 'admin',
    created_by   INT DEFAULT NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 1,
    is_protected TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- LEAGUES
-- v1.2: Added `rules` and `structure` columns.
--       These are edited via the admin League Settings page
--       and displayed dynamically on the public About page.
-- ============================================================
CREATE TABLE leagues (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT         NULL COMMENT 'Short league description — shown in hero of public About page',
    rules       TEXT         NULL COMMENT 'Competition rules — shown in Rules & Structure section of public About page',
    structure   TEXT         NULL COMMENT 'Season format/structure — shown in Rules & Structure section of public About page',
    logo        VARCHAR(255) NULL,
    status      ENUM('active','completed') DEFAULT 'active',
    created_by  INT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TEAMS
-- ============================================================
CREATE TABLE teams (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    league_id   INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    logo        VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE,
    UNIQUE KEY uq_team_name_league (league_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLAYERS
-- ============================================================
CREATE TABLE players (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    league_id     INT NOT NULL,
    first_name    VARCHAR(50) NOT NULL,
    last_name     VARCHAR(50) NOT NULL,
    date_of_birth DATE         NULL,
    position      VARCHAR(20)  NULL,
    height        VARCHAR(10)  NULL,
    photo         VARCHAR(255) NULL,
    bio           TEXT         NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES leagues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEASONS
-- ============================================================
CREATE TABLE seasons (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    league_id             INT NOT NULL,
    name                  VARCHAR(100) NOT NULL,
    status                ENUM('upcoming','active','playoffs','completed') DEFAULT 'upcoming',
    start_date            DATE NULL,
    end_date              DATE NULL,
    games_per_team        INT NOT NULL DEFAULT 4,
    playoff_teams_count   INT DEFAULT 4,
    regular_season_mvp_id INT DEFAULT NULL,
    playoffs_mvp_id       INT DEFAULT NULL,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id)             REFERENCES leagues(id)  ON DELETE CASCADE,
    FOREIGN KEY (regular_season_mvp_id) REFERENCES players(id)  ON DELETE SET NULL,
    FOREIGN KEY (playoffs_mvp_id)       REFERENCES players(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEASON TEAMS
-- ============================================================
CREATE TABLE season_teams (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    season_id   INT NOT NULL,
    team_id     INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id)   REFERENCES teams(id)   ON DELETE CASCADE,
    UNIQUE KEY uq_season_team (season_id, team_id),
    INDEX idx_season_teams_season (season_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEASON ROSTERS
-- ============================================================
CREATE TABLE season_rosters (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    season_id     INT NOT NULL,
    team_id       INT NOT NULL,
    player_id     INT NOT NULL,
    jersey_number INT NOT NULL,
    status        ENUM('active','injured','suspended') DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id)  REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id)    REFERENCES teams(id)   ON DELETE CASCADE,
    FOREIGN KEY (player_id)  REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY uq_player_per_season      (season_id, player_id),
    UNIQUE KEY uq_jersey_per_team_season (season_id, team_id, jersey_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- GAMES
-- ============================================================
CREATE TABLE games (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    season_id            INT NOT NULL,
    home_team_id         INT NOT NULL,
    away_team_id         INT NOT NULL,
    game_date            DATE NULL,
    game_time            TIME DEFAULT NULL,
    home_score           INT DEFAULT NULL,
    away_score           INT DEFAULT NULL,
    status               ENUM('scheduled','completed') DEFAULT 'scheduled',
    game_type            ENUM('regular','playoff') DEFAULT 'regular',
    playoff_round        VARCHAR(50) DEFAULT NULL,
    playoff_position     INT DEFAULT NULL,
    home_source_game_id  INT DEFAULT NULL COMMENT 'Playoff: game whose winner fills home slot',
    away_source_game_id  INT DEFAULT NULL COMMENT 'Playoff: game whose winner fills away slot',
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id)           REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (home_team_id)        REFERENCES teams(id)   ON DELETE CASCADE,
    FOREIGN KEY (away_team_id)        REFERENCES teams(id)   ON DELETE CASCADE,
    FOREIGN KEY (home_source_game_id) REFERENCES games(id)   ON DELETE SET NULL,
    FOREIGN KEY (away_source_game_id) REFERENCES games(id)   ON DELETE SET NULL,
    UNIQUE KEY uq_playoff_position_season (season_id, game_type, playoff_position),
    INDEX idx_games_season_type   (season_id, game_type, status),
    INDEX idx_games_season_status (season_id, status),
    INDEX idx_games_home_source   (home_source_game_id),
    INDEX idx_games_away_source   (away_source_game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PLAYER GAME STATS
-- ============================================================
CREATE TABLE player_game_stats (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    game_id                INT NOT NULL,
    player_id              INT NOT NULL,
    team_id                INT NOT NULL,
    two_points_made        INT DEFAULT 0,
    two_points_attempted   INT DEFAULT 0,
    three_points_made      INT DEFAULT 0,
    three_points_attempted INT DEFAULT 0,
    free_throws_made       INT DEFAULT 0,
    free_throws_attempted  INT DEFAULT 0,
    total_points           INT DEFAULT 0 COMMENT 'Auto-calculated by trigger',
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id)   REFERENCES games(id)   ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id)   REFERENCES teams(id)   ON DELETE CASCADE,
    UNIQUE KEY uq_player_game (game_id, player_id),
    INDEX idx_pgs_game (game_id, team_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- STANDINGS
-- ============================================================
CREATE TABLE standings (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    season_id          INT NOT NULL,
    team_id            INT NOT NULL,
    wins               INT DEFAULT 0,
    losses             INT DEFAULT 0,
    points_for         INT DEFAULT 0,
    points_against     INT DEFAULT 0,
    point_differential INT DEFAULT 0,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id)   REFERENCES teams(id)   ON DELETE CASCADE,
    UNIQUE KEY uq_standing_season_team (season_id, team_id),
    INDEX idx_standings_season (season_id, wins, point_differential)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGERS — auto-calculate total_points on stats insert/update
-- ============================================================
DELIMITER //

CREATE TRIGGER trg_stats_total_insert
BEFORE INSERT ON player_game_stats FOR EACH ROW
BEGIN
    SET NEW.total_points =
        (NEW.two_points_made * 2) +
        (NEW.three_points_made * 3) +
        NEW.free_throws_made;
END//

CREATE TRIGGER trg_stats_total_update
BEFORE UPDATE ON player_game_stats FOR EACH ROW
BEGIN
    SET NEW.total_points =
        (NEW.two_points_made * 2) +
        (NEW.three_points_made * 3) +
        NEW.free_throws_made;
END//

DELIMITER ;

-- ============================================================
-- SEED DATA — Default admin account
--
-- Username : admin
-- Password : password   ← CHANGE THIS IMMEDIATELY after first login
--
-- The password hash below is bcrypt for the string "password".
-- ============================================================
INSERT INTO admins (username, password, email, full_name, role, is_protected) VALUES
(
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'basketballcapetown@gmail.com',
    'Carter',
    'super_admin',
    1
);
