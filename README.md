<h1><img src="public/assets/stats.png" width="44" height="42" align="absmiddle" alt=""> Lost City Labs</h1>

XP tracker for https://2004.lostcity.rs , inspired by anders' original Lost City Labs.

Live at https://labs.lostcity.rs


## Run locally

Install PHP and MySQL, then run:

```sh
mysql -e 'CREATE DATABASE labs' && mysql labs < schema.sql
LABS_DB_DSN='mysql:host=localhost;dbname=labs' LABS_DB_USER=root LABS_API_KEY=dev php -S 127.0.0.1:8080 -t public
```

Open http://127.0.0.1:8080 and search for a player.

---

Join Discord: https://discord.gg/m5Q6ZZrbPH
