# Media Cleanup

A Laravel web app that replaces a PowerShell scheduled-task script (see [scripts/](scripts/)):

- **Expired episode cleanup** — scans for `deleteafter.txt` marker files and deletes media in that
  folder older than the configured day count.

Runs on its own configurable schedule (cron expression, editable from Settings) and can also be
triggered manually. Run history lives on a dedicated Jobs page (retention configurable, old runs are
pruned automatically); the dashboard shows summary stats and trend graphs instead. No SMB credentials
are needed: the app scans whatever is bind-mounted into the container at `/media`.

The app sits behind simple single-admin authentication. Default login is **admin / password** —
**change the password from Settings → Admin Account after first login.** The username is fixed and
can't be changed.

Everything (web, queue workers, scheduler) runs inside a single container via supervisord. See
`docker/` for the nginx/php-fpm/supervisord config and `Dockerfile` for the build.

## Deploying to a NAS via Portainer

See **[DEPLOYMENT.md](DEPLOYMENT.md)** for the full step-by-step workflow (building the image on the
NAS, Portainer stack/volume/env-var setup, and a troubleshooting section covering every gotcha hit
getting this running for real — reverse proxy URL generation, GitHub archive-zip caching, container
recreation, and more). `docker-compose.yml` and `.env.example` in this repo are also usable directly if
you'd rather have Portainer/Compose build the image itself from a git-repo stack, but the manual-build
workflow in DEPLOYMENT.md is what's actually been exercised in production.

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev   # serves the app, queue worker, and Vite together
```

Point `MEDIA_ROOT` in `.env` at a scratch directory instead of `/media` for local testing. If you're
testing through the browser (rather than just `php artisan test`), also set `APP_URL` to match
whatever host/port you're actually serving on — see the `APP_URL` gotcha in
[DEPLOYMENT.md](DEPLOYMENT.md), it applies locally too.

## Tests

```bash
php artisan test
```
