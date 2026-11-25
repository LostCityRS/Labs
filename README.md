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

For an existing installation, create the `visits` table using its definition in the schema before deploying visitor tracking. `/stats` counts distinct local-storage IDs, not people; only a UTC day and browser ID are retained. On `/hit`, HTTP 204 means accepted (possibly a repeat ID), and HTTP 200 with `X-Labs-Visit: crawler` means filtered without a write. Unknown automation can still count. Headline totals and returning visitors use the filtered baseline only; legacy data cannot be retroactively filtered. Returning visitors compares the last seven UTC days (including today) with the preceding seven, after 14 full days of filtered tracking.

## Community poll

For an installation predating the poll, create the `poll_weeks` and `poll_votes` tables using their definitions in the schema.

`src/polls.json` lists the polls. Each UTC week from `start` runs one poll: polls that haven't run yet go first, in file order, and once all have run the longest-rested one repeats. Each week's poll is recorded in `poll_weeks` when the week is first viewed, so adding or reordering polls never swaps the running one; removing the running poll fails loudly until the week ends. Votes are stored per `slug`, so a poll that comes round again keeps its earlier votes (and everyone who voted can't vote again): add new polls with new slugs, and never reorder an existing poll's options. Icons are PNGs in `public/assets/poll`; `qty` draws an in-game stack count. Voting does not require signing in. A persistent browser session remembers each voter's first vote; clearing cookies or using another browser can allow another vote. Existing signed-in voters retain their account-keyed votes when their voting session is initialized. The legacy `discord_id` column also stores anonymous voter keys prefixed with `a:`.

## Historical records

The historical-records data file contains manually curated firsts and rank-one handovers from https://lostcity.rs/t/hiscore-history-2004/1811/2 . Its `source` field is a forum post number; dates are UTC calendar dates, and `note` preserves unresolved source discrepancies.

## YouTube

To add a creator, add their channel ID to `YOUTUBE_CHANNELS` in `src/youtube.php`; `youtube_listed()` decides which uploads count. For an installation predating feed failure tracking, create the `youtube_feed_failures` table using its definition in the schema.

## Hiscores account types

For an installation predating Pure and F2P, run `ALTER TABLE players ADD pure TINYINT AFTER skiller, ADD f2p TINYINT AFTER pure`, then `php src/refresh.php --ehp` to classify existing players. Only confirmed accounts are listed: omitted stats count as unknown unless Overall totals prove them.

## Active PKers

`/pkers` lists players who gained XP in the last seven days and whose Defence is at most 78% of the higher of their Strength and Ranged, most weekly XP first. The threshold was fitted to 30 hand-rated accounts; Attack and Magic did not separate PKers from mains. It is a guess from stats, not kill data: a main with balanced stats who PKs is not listed. The official hiscores omit levels below 15; an omitted level counts as 1 (Hitpoints as 10), so a listed combat level can be a few levels too low. A combat level shows the players within five levels of it (Wilderness level 5).

---

Join Discord: https://discord.gg/m5Q6ZZrbPH
