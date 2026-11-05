<h1><img src="public/assets/stats.png" width="44" height="42" align="absmiddle" alt=""> Lost City Labs</h1>

XP tracker for https://2004.lostcity.rs , inspired by anders' original Lost City Labs.

Live at https://labs.lostcity.rs

Pages are rendered by PHP with ordinary links and forms. JavaScript handles chart tooltips and redirects old hash bookmarks.


## Run locally

Install PHP and MySQL, then run:

```sh
mysql -e 'CREATE DATABASE labs' && mysql labs < schema.sql
LABS_DB_DSN='mysql:host=localhost;dbname=labs' LABS_DB_USER=root LABS_API_KEY=dev php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080 and search for a player.

For nginx, set `index index.php`, route pages with `try_files $uri $uri/ /index.php$is_args$args;`, and include `index.php` alongside `api.php` and `ingest.php` in the PHP-FPM handler. Run `php refresh.php` every five minutes on a UTC cron schedule, including 00:00, with the same environment.

EHP uses the versioned 2004Tracker-derived benchmarks and explicit rules in `rates.json`, scored against recorded skill XP. Callbacks refresh cached scores; completed UTC weeks are finalized after a five-minute commit grace period. Run `php refresh.php --ehp` to rebuild scores after a model change. Published weekly winners retain their original model version.

---

Join Discord: https://discord.gg/m5Q6ZZrbPH
