# cron-cleanup

A standalone, database-free replacement for the Laravel app's expired-episode
cleanup feature: a single container that runs `delete-expired.sh` on a cron
schedule. No PHP, no web UI, no SQLite - just a script and cron.

It scans for marker files (default `deleteafter.txt`), reads a day-count from
the first line of each one, and deletes media files older than that many days
in the marker's directory (recursively). It also removes each deleted video's
Jellyfin trickplay sibling folder, if present - Jellyfin's "save trickplay
files into media folders" option writes a `<filename-without-extension>.trickplay/`
directory next to the video, and Jellyfin itself doesn't clean these up when
the video is removed (see [jellyfin/jellyfin#13223](https://github.com/jellyfin/jellyfin/issues/13223)).

## Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `MEDIA_ROOT` | `/media` | Root directory to scan (container path). |
| `SCAN_PATH` | *(empty)* | Optional subdirectory under `MEDIA_ROOT` to restrict scanning to. Rejected if it would resolve outside `MEDIA_ROOT`. |
| `MARKER_FILENAME` | `deleteafter.txt` | Name of the marker file to look for. |
| `DELETE_EXTENSIONS` | `mp4,mkv,avi,srt,sub` | Comma-separated, case-insensitive extensions eligible for deletion. |
| `CLEAN_TRICKPLAY` | `true` | Whether to also remove a deleted video's Jellyfin trickplay sibling folder. |
| `TRICKPLAY_SUFFIX` | `.trickplay` | Suffix appended to a video's filename (minus extension) to find its trickplay folder. |
| `CRON_SCHEDULE` | `0 3 * * *` | Standard 5-field cron expression for how often to run. |
| `RUN_ON_START` | `false` | Set to `true` to also run once immediately when the container starts, in addition to the schedule. |
| `PUID` / `PGID` | `1000` / `1000` | uid/gid the script (and thus any file deletions) run as - match whatever owns your media, e.g. the same values your Radarr/Sonarr containers use. |
| `TZ` | `UTC` | Container timezone; also localizes when `CRON_SCHEDULE` actually fires. |

`crond` itself always runs as root (it only touches `/etc/crontabs`, never
`/media`); the actual script is invoked via `su-exec ${PUID}:${PGID}` so
deletions happen with the same ownership as the rest of your media stack.

## Example: dropped into an existing *arr stack

```yaml
  media-cleanup:
    build: /volume1/docker/media-cleanup/cron-cleanup
    container_name: media-cleanup
    volumes:
      - /volume1/Media:/media
    environment:
      - PUID=1034
      - PGID=65537
      - TZ=America/Chicago
      - CRON_SCHEDULE=0 3 * * *
      - MEDIA_ROOT=/media
      - MARKER_FILENAME=deleteafter.txt
      - DELETE_EXTENSIONS=mp4,mkv,avi,srt,sub
      - CLEAN_TRICKPLAY=true
    restart: unless-stopped
```

## Logs

Everything is written to stdout/stderr, so `docker logs media-cleanup` (or
Portainer's container log view) shows every run: markers found, bad-value
markers, each file deleted, each trickplay folder removed, and a one-line
summary per run. There's no run-history database or dashboard - if you need
retention beyond your container log driver's own limits, redirect the cron
line in `entrypoint.sh` to also append to a file under a mounted volume.

## What this does not do

This intentionally has no relation to the Laravel app's (now-removed) video
conversion feature - it only reimplements the `deleteafter.txt` expiry
cleanup. It also has no web UI, authentication, or run-history browser; it's
meant to be operated entirely through its logs and the Portainer stack's
environment variables.
