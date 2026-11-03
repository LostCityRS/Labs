# Labs

XP tracker for skillers on https://2004.lostcity.rs

Inspired by anders' Lost City Labs.

## Data from the game server

The game server pushes each player's hiscore rows to Labs when they log out:

```
POST https://labs.lostcity.rs/ingest.php
Authorization: Bearer <LABS_PUSH_TOKEN>
[{"username":"detective","type":7,"level":99,"value":300450268,"date":"2026-11-03 12:15:00"}, …]
```

`type` and `value` (xp10) are the same as `/api/hiscores/player/:username`. History can be sent the same way, oldest first.

## Run

- `schema.sql` creates the MySQL tables.
- nginx serves `public/` with PHP-FPM.
- `php refresh.php` every 5 minutes (cron) refreshes period gains.
- PHP reads `LABS_DB_DSN` (e.g. `mysql:host=localhost;dbname=labs`), `LABS_DB_USER`, `LABS_DB_PASS` and `LABS_PUSH_TOKEN` from the environment.

Stats update when a player logs out.
