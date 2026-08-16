# Media Cleanup

A Laravel web app that replaces two PowerShell scheduled-task scripts (see [scripts/](scripts/)):

- **Video conversion** — scans a media library for `.mkv`/`.avi` files and converts them to `.mp4`
  (mkv is remuxed via stream copy, avi is re-encoded) via `ffmpeg`, in a queued background batch with
  live progress in the dashboard.
- **Expired episode cleanup** — scans for `deleteafter.txt` marker files and deletes media in that
  folder older than the configured day count.

No SMB credentials are needed: the app scans whatever is bind-mounted into the container at `/media`.

## Running on a NAS via Portainer

1. Copy `.env.example` to `.env` next to `docker-compose.yml` and set at least:
   - `MEDIA_PATH` — host path to your media share, e.g. `/mnt/nas/media`
   - `PUID` / `PGID` — the uid/gid that owns your media on the host (`id <user>` on the NAS)
   - `TZ` — your local timezone
2. In Portainer, add a stack pointing at this repository (or paste the contents of
   `docker-compose.yml`), and load the same variables into the stack's environment variables.
3. Deploy. The app generates and persists its own `APP_KEY` and SQLite database inside the
   `storage-data` volume on first boot, and runs migrations automatically.
4. Open the published port (default `8080`), scan/convert and cleanup are triggered from the
   dashboard, and both also run on a daily schedule (`routes/console.php`).

Everything (web, queue workers, scheduler) runs inside a single container via supervisord; ffmpeg is
bundled in the image. See `docker/` for the nginx/php-fpm/supervisord config and `Dockerfile` for the
build.

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev   # serves the app, queue worker, and Vite together
```

Point `MEDIA_ROOT` in `.env` at a scratch directory instead of `/media` for local testing.

## Tests

```bash
php artisan test
```
