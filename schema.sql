-- Times are unix milliseconds. XP values are the game's xp10 integers. type 0 = Overall (level = total level). rank is the official rank at the player's last logout.
CREATE TABLE players (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(12) NOT NULL UNIQUE, first_seen BIGINT NOT NULL, skiller TINYINT, maxed_at BIGINT, KEY (first_seen));
CREATE TABLE xp (player_id INT NOT NULL, type TINYINT NOT NULL, at BIGINT NOT NULL, level SMALLINT NOT NULL, value BIGINT NOT NULL, PRIMARY KEY (player_id, type, at), KEY (at));
CREATE TABLE world_xp (day BIGINT NOT NULL, type TINYINT NOT NULL, gain DOUBLE NOT NULL, PRIMARY KEY (day, type));
CREATE TABLE current (player_id INT NOT NULL, type TINYINT NOT NULL, at BIGINT NOT NULL, level SMALLINT NOT NULL, value BIGINT NOT NULL, `rank` INT NOT NULL, PRIMARY KEY (player_id, type), KEY (type, level, value));
CREATE TABLE levels (player_id INT NOT NULL, type TINYINT NOT NULL, level SMALLINT NOT NULL, prev_level SMALLINT NOT NULL, at BIGINT NOT NULL, PRIMARY KEY (player_id, type, at), KEY (level, at));
CREATE TABLE gains (period VARCHAR(3) NOT NULL, type TINYINT NOT NULL, player_id INT NOT NULL, gain BIGINT NOT NULL, PRIMARY KEY (period, type, player_id), KEY (period, type, gain), KEY (player_id));
CREATE TABLE records (period VARCHAR(3) NOT NULL, type TINYINT NOT NULL, player_id INT NOT NULL, gain BIGINT NOT NULL, start_at BIGINT NOT NULL, end_at BIGINT NOT NULL, PRIMARY KEY (period, type, player_id), KEY (period, type, gain));
CREATE TABLE daily_observed (day BIGINT NOT NULL, player_id INT NOT NULL, PRIMARY KEY (day, player_id));
CREATE TABLE online (at BIGINT PRIMARY KEY, players INT NOT NULL);
CREATE TABLE daily_players (day BIGINT PRIMARY KEY, started_at BIGINT NOT NULL, online INT, sampled_at BIGINT);
CREATE TABLE ehp (model VARCHAR(64) NOT NULL, player_id INT NOT NULL, type TINYINT NOT NULL, value DOUBLE NOT NULL, PRIMARY KEY (model, player_id, type), KEY (model, type, value));
CREATE TABLE character_clans (name VARCHAR(12) PRIMARY KEY, discord_id VARCHAR(20) NOT NULL, clan VARCHAR(16), KEY (discord_id));
CREATE TABLE ehp_weeks (week_at BIGINT PRIMARY KEY, start_at BIGINT NOT NULL, end_at BIGINT NOT NULL, player_id INT, gain DOUBLE NOT NULL, model VARCHAR(64) NOT NULL);
CREATE TABLE clan_roster (clan VARCHAR(16) NOT NULL, name VARCHAR(12) NOT NULL, PRIMARY KEY (clan, name), KEY (name));
CREATE TABLE clan_videos (clan VARCHAR(16) NOT NULL, video_id VARCHAR(11) NOT NULL, title VARCHAR(255), channel VARCHAR(255), shared_at BIGINT NOT NULL, PRIMARY KEY (clan, video_id));
CREATE TABLE youtube_videos (video_id VARCHAR(11) PRIMARY KEY, channel_id VARCHAR(24) NOT NULL, channel VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, views INT NOT NULL, published_at BIGINT NOT NULL, KEY (published_at));
