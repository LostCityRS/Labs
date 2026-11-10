<h1><img src="public/assets/stats.png" width="44" height="42" align="absmiddle" alt=""> Lost City Labs</h1>

XP tracker for https://2004.lostcity.rs , inspired by anders' original Lost City Labs.

Live at https://labs.lostcity.rs

## Run locally

Install PHP and MySQL, then run:

```sh
mysql -e 'CREATE DATABASE labs' && mysql labs < schema.sql
LABS_DB_DSN='mysql:host=localhost;dbname=labs' LABS_DB_USER=root LABS_API_KEY=dev php -S 127.0.0.1:8080 -t public
```
## Deployment

Pushes to `main` run PHP checks, fast-forward the production checkout, then check the live homepage. Pull requests run checks only. Deployment status and logs: https://github.com/LostCityRS/Labs/actions/workflows/deploy.yml

The server's deploy key is restricted to a forced command installed from `.github/deploy.sh` at `~/.local/bin/labs-deploy`. Changes to that script must also be installed on the server. The checkout must be clean; deployments refuse stale commits or divergent server history. Database migrations are not automatic.

GitHub configuration: secret `LABS_DEPLOY_KEY`; variables `LABS_DEPLOY_HOST`, `LABS_DEPLOY_USER`, and `LABS_DEPLOY_KNOWN_HOSTS` (verified SSH host key). Re-run the Deploy workflow on `main` after resolving a failed deployment.

---

Join Discord: https://discord.gg/m5Q6ZZrbPH
