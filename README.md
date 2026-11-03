# Labs

XP tracker for skillers on https://2004.lostcity.rs

Inspired by anders' Lost City Labs.

## Data from the game website

Labs needs one endpoint next to the existing `/api/hiscores/*` routes. It returns every hiscore row that changed since a time, in the same shape as the existing tables (`date` is already updated whenever a row's XP changes):

```ts
const changed = (table: 'hiscore' | 'hiscore_large', since: string) => db.selectFrom(table).innerJoin('account', 'account.id', `${table}.account_id`).select(['account.username', 'type', 'level', 'value', 'date']).where('profile', '=', 'main').where('date', '>=', since);
f.get('/api/hiscores/updated', async (req: any) => changed('hiscore', toDbDate(Number(req.query.since))).unionAll(changed('hiscore_large', toDbDate(Number(req.query.since)))).orderBy('date').limit(10000).execute());
```

`GET /api/hiscores/updated?since=<unix ms>` → `[{"username":"detective","type":7,"level":99,"value":300450268,"date":"2026-11-03 12:15:00"}, …]`, oldest first, at most 10,000 rows. `since=0` returns everyone's current stats.

History can come later as a file of the same rows (old backups of the hiscore tables, oldest first).

## Run

- `schema.sql` creates the MySQL tables.
- nginx serves `public/` with PHP-FPM.
- `php collect.php` every 5 minutes (cron) imports changes and refreshes gains.
- Both read `LABS_DB_DSN` (e.g. `mysql:host=localhost;dbname=labs`), `LABS_DB_USER`, `LABS_DB_PASS` and `LABS_GAME_API` (e.g. `https://2004.lostcity.rs`) from the environment.

Hiscores only change when a player logs out and only list skills from level 15, so Labs has the same limits.
