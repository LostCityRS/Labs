-- Times are unix milliseconds. XP values are the game's xp10 integers. type 0 = Overall (level = total level). rank is the official rank at the player's last logout.
CREATE TABLE players (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(12) NOT NULL UNIQUE, first_seen BIGINT NOT NULL, KEY (first_seen));
CREATE TABLE xp (player_id INT NOT NULL, type TINYINT NOT NULL, at BIGINT NOT NULL, level SMALLINT NOT NULL, value BIGINT NOT NULL, PRIMARY KEY (player_id, type, at));
CREATE TABLE current (player_id INT NOT NULL, type TINYINT NOT NULL, at BIGINT NOT NULL, level SMALLINT NOT NULL, value BIGINT NOT NULL, `rank` INT NOT NULL, PRIMARY KEY (player_id, type), KEY (type, level, value));
CREATE TABLE levels (player_id INT NOT NULL, type TINYINT NOT NULL, level SMALLINT NOT NULL, prev_level SMALLINT NOT NULL, at BIGINT NOT NULL, PRIMARY KEY (player_id, type, at), KEY (level, at));
CREATE TABLE gains (period VARCHAR(3) NOT NULL, type TINYINT NOT NULL, player_id INT NOT NULL, gain BIGINT NOT NULL, PRIMARY KEY (period, type, player_id), KEY (period, type, gain));
CREATE TABLE records (period VARCHAR(3) NOT NULL, type TINYINT NOT NULL, player_id INT NOT NULL, gain BIGINT NOT NULL, start_at BIGINT NOT NULL, end_at BIGINT NOT NULL, PRIMARY KEY (period, type, player_id), KEY (period, type, gain));
