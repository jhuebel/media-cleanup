# Deployment (Synology NAS + Portainer)

This is the workflow actually in use, refined from a few failed approaches — see
[Gotchas](#gotchas--why-things-are-set-up-this-way) at the bottom for the reasoning behind each one.

Portainer does **not** build this image itself (see gotchas). Instead, the image is built manually on
the NAS from a git clone, and Portainer's stack just references that pre-built local image tag.

## One-time setup

**1. Get git onto the NAS**, if it isn't already. Synology doesn't ship git in the base OS. Either:
   - Install Synology's official **Git Server** package (Package Center → search "Git Server"), which
     drops a `git` binary usable from SSH, or
   - Skip installing anything and run git via a throwaway container instead (see step 2b below).

**2a. Clone the repo** (if git is installed on the NAS):
```bash
cd /volume1/docker/media-cleanup
git clone https://github.com/jhuebel/media-cleanup.git app
```

**2b. Or clone via a disposable container** (no NAS-level install needed):
```bash
cd /volume1/docker/media-cleanup
docker run --rm -v "$(pwd):/data" alpine/git clone https://github.com/jhuebel/media-cleanup.git /data/app
```

**3. Create a persistent storage folder** (outside the git checkout, so it survives re-clones/rebuilds):
```bash
mkdir -p /volume1/docker/media-cleanup/storage
```
This holds the SQLite database, the auto-generated `APP_KEY`, and logs. The container's entrypoint
creates the subfolders it needs and `chown`s them to match `PUID`/`PGID` automatically — you don't
need to pre-create anything inside it.

**4. Create the Portainer stack.** Use a raw container/stack definition (not a "Repository" build —
see gotchas) referencing the image tag `media-cleanup:local`, with:

| Volume | Container path |
|---|---|
| Your media share | `/media` |
| `/volume1/docker/media-cleanup/storage` | `/var/www/html/storage` |

Environment variables (see `.env.example` for the full annotated list):

| Variable | Notes |
|---|---|
| `APP_NAME` | `Media Cleanup` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (flip to `true` temporarily when debugging a 500) |
| `APP_URL` | **The exact address you browse to, including port** (e.g. `http://nas.huebel.local:9880`) — see gotchas, this one bites |
| `APP_KEY` | Optional — leave unset, it's auto-generated and persisted into `storage/app/app.key` on first boot |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `/var/www/html/storage/app/database.sqlite` |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` |
| `DB_QUEUE_RETRY_AFTER` | `86400` (ffmpeg jobs can run for hours; don't let a second worker re-pick a still-running job) |
| `MEDIA_ROOT` | `/media` |
| `PUID` / `PGID` | uid/gid that owns your media on the host — run `id <user>` on the NAS to find them |
| `TZ` | your timezone, e.g. `America/Chicago` |

Publish container port 80 to whatever host port you want (e.g. `9880`).

**5. Log in and change the default password.** The app seeds a single admin account
(`admin` / `password`) on first migration — sign in and change the password immediately from
**Settings → Admin Account**. The username `admin` is fixed and can't be changed.

## Every subsequent deploy

```bash
cd /volume1/docker/media-cleanup/app
git fetch origin && git reset --hard origin/master   # NOT curl+zip — see gotchas
docker build -t media-cleanup:local .
```

Then in Portainer: **recreate the container** (not just "restart" — a plain restart keeps using the
old image; you need Portainer to actually swap in the freshly built one). Make sure "always pull
image" is **off** for this stack, since `media-cleanup:local` only exists in the NAS's local image
cache, not a registry — if Portainer tries to pull it, it'll fail or silently keep the stale container.

**Once the container is recreated on the new image, clean up the old one:**
```bash
docker image prune -f
```
Every rebuild reuses the `media-cleanup:local` tag, so the previous image (and any stale
intermediate layers from the multi-stage build) becomes untagged/"dangling" once the tag moves to
the new image — Docker doesn't delete it automatically, which is why unused images pile up in
Portainer over repeated deploys. `docker image prune -f` (no `-a`) only removes dangling images, so
it won't touch this or any other stack's tagged/in-use images.

## Diagnosing a broken deploy

Portainer's container logs show nginx access logs and supervisord startup — useful for confirming
migrations ran and all 5 processes (`nginx`, `php-fpm`, `queue-conversions`, `queue-default`,
`scheduler`) came up, but **not** the actual PHP exception behind a 500. For that, use Portainer's
**Console** on the running container:
```bash
tail -100 /var/www/html/storage/logs/laravel.log
```
or temporarily set `APP_DEBUG=true` and reload the page to see the full stack trace in-browser (set it
back to `false` afterward).

To check what the container actually has on disk (e.g. confirm a deploy picked up the commit you
think it did):
```bash
docker ps                                    # find the container id/name
docker exec <container> ls -b resources/views/components/
```

## Gotchas / why things are set up this way

- **Portainer doesn't build the image from a git-repo stack.** It's technically supported, but
  `composer.lock` was originally generated on a dev machine running PHP 8.5 with no platform
  constraint, so composer resolved several Symfony components to their 8.x line (requires PHP
  ≥8.4.1) — while the Docker image runs PHP 8.3. `composer.json` now pins
  `config.platform.php` to `8.3.99` so this can't silently recur, but a manual `docker build` on the
  NAS is the workflow actually being used, so that's what's documented here.
- **`git fetch`/`reset --hard`, never `curl .../archive/refs/heads/master.zip`.** GitHub's
  `codeload.github.com` caches generated branch-name archive zips and can keep serving a stale
  snapshot for several minutes after a push, even though the commit on GitHub is already correct.
  `git` always fetches the exact current ref — no CDN staleness window.
- **Rebuilding the image doesn't restart the container using it.** Even with the right tag, the
  already-running container keeps using the old image layers until Portainer actually recreates it.
- **`APP_URL` must match the exact address (including port) you browse to.** Laravel only derives
  `url()`/`asset()`/`route()` from `APP_URL` in contexts with no real HTTP request (CLI, queue
  workers). For actual page loads it derives the host from the incoming request — and a reverse
  proxy in front of the container may not forward the original public port. `AppServiceProvider::boot()`
  calls `URL::forceRootUrl(config('app.url'))` specifically to make this deterministic regardless of
  what the proxy passes through; if asset/CSS loads ever break again after a proxy change, check this
  first.
- **Livewire single-file component filenames are plain ASCII on purpose.** `php artisan make:livewire`
  defaults to prefixing files with a ⚡ emoji (e.g. `⚡dashboard.blade.php`) as a visual convention —
  that's not a functional requirement. That 4-byte Unicode character was getting mangled somewhere
  between `git clone` → Docker multi-stage `COPY` → the NAS filesystem, causing
  `Unable to find component` errors in production despite working locally. If you ever run
  `make:livewire` again, rename the generated file to drop the emoji prefix before committing.
- **Storage must be a real persistent mount, not left as container-local.** The SQLite database, the
  auto-generated `APP_KEY`, and logs all live under `storage/`. If that directory isn't a bind
  mount/volume, all of it (including the encryption key used for sessions/cookies) gets silently
  regenerated on every container recreate.
- **npm is pinned to a minor version (`npm@12.0`) rather than `npm@latest`.** As of 12.0, npm started
  blocking dependency install scripts by default (`esbuild`'s postinstall gets skipped) - harmless
  today since esbuild still resolves its binary via `optionalDependencies`, but a floating `@latest`
  could pick up a future npm release with a different breaking default on some later rebuild with no
  corresponding code change to explain it. The `12.0` pin (rather than an exact `12.0.2`) still lets
  bugfix releases land automatically on rebuild - it's npm minor/major bumps, not patches, that tend to
  carry behavior changes. Bump the pin deliberately when there's a reason to.
