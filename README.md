<h1><img src="public/assets/stats.png" width="44" height="42" align="absmiddle" alt=""> Lost City Labs</h1>

XP tracker for https://2004.lostcity.rs , inspired by anders' original Lost City Labs.

Live at https://labs.lostcity.rs

## Run locally

Install PHP and MySQL, then run:

```sh
mysql -e 'CREATE DATABASE labs' && mysql labs < schema.sql
LABS_DB_DSN='mysql:host=localhost;dbname=labs' LABS_DB_USER=root LABS_API_KEY=dev php -S 127.0.0.1:8080 -t public
```

## Similar players

For an installation predating Similar players, create the `similarity` table using its definition in the schema. For an existing single-model table, first run `ALTER TABLE similarity DROP PRIMARY KEY, ADD PRIMARY KEY (model, player_id);` so the next model can be built without replacing the live model's projections.

Run `php src/refresh.php --similar` with the normal database environment before serving an updated similarity model or EHP rates. Incompatible projections fail with the rebuild command rather than serving an old ranking. Callbacks update each account, and the regular refresh job advances recent training windows hourly.

## Labs visitors

For an existing installation, create the `visits` table using its definition in the schema before deploying visitor tracking. `/stats` reports distinct browsers using a random local-storage ID, not accounts or IPs; only a UTC day and browser ID are retained. Returning visitors compares the last seven UTC days (including today) against the preceding seven and is unavailable until 14 days of history exist. Non-JavaScript clients and hidden pages are not counted; browser automation can still inflate counts. There is no backfill before tracking began.

## Community poll

For an installation predating the poll, create the `poll_weeks` and `poll_votes` tables using their definitions in the schema.

`src/polls.json` lists the polls. Each UTC week from `start` runs one poll: polls that haven't run yet go first, in file order, and once all have run the longest-rested one repeats. Each week's poll is recorded in `poll_weeks` when the week is first viewed, so adding or reordering polls never swaps the running one; removing the running poll fails loudly until the week ends. Votes are stored per `slug`, so a poll that comes round again keeps its earlier votes (and everyone who voted can't vote again): add new polls with new slugs, and never reorder an existing poll's options. Icons are PNGs in `public/assets/poll`; `qty` draws an in-game stack count. Voting requires a Discord login.

## Historical records

The historical-records data file contains manually curated firsts and rank-one handovers from https://lostcity.rs/t/hiscore-history-2004/1811/2 . Its `source` field is a forum post number; dates are UTC calendar dates, and `note` preserves unresolved source discrepancies.

---

Join Discord: https://discord.gg/m5Q6ZZrbPH
