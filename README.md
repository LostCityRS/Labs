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

## Historical records

The historical-records data file contains manually curated firsts and rank-one handovers from https://lostcity.rs/t/hiscore-history-2004/1811/2 . Its `source` field is a forum post number; dates are UTC calendar dates, and `note` preserves unresolved source discrepancies.

---

Join Discord: https://discord.gg/m5Q6ZZrbPH
