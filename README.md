# Labs

XP tracker for skillers on https://2004.lostcity.rs

Inspired by anders' Lost City Labs.

## Data from the game server

Searching for a player that Labs doesn't know yet calls `GET https://2004.lostcity.rs/api/hiscores/player/:username` with our `x-api-key`. That returns their stats and enrolls them; from then on the game website sends their hiscores on every logout:

```
POST https://labs.lostcity.rs/api/hiscores/callback/:username
x-api-key: <LABS_API_KEY>
[{"type":0,"level":1234,"value":5678901,"rank":42}, …]
```

## Run

- `schema.sql` creates the MySQL tables.
- nginx serves `public/` with PHP-FPM and rewrites `/api/hiscores/callback/(.+)` to `/ingest.php?username=$1`.
- `php refresh.php` every 5 minutes (cron) refreshes period gains.
- PHP reads `LABS_DB_DSN` (e.g. `mysql:host=localhost;dbname=labs`), `LABS_DB_USER`, `LABS_DB_PASS` and `LABS_API_KEY` from the environment.

Stats update when a player logs out.
