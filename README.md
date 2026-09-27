# Media Cleanup

A small, standalone container that deletes expired media: it scans a media library for
`deleteafter.txt` marker files and removes media in that folder older than the day count written in
the marker, on a cron schedule. It also cleans up the Jellyfin trickplay sibling folder for anything it
deletes, since Jellyfin doesn't do that itself.

See **[cron-cleanup/](cron-cleanup/)** for the script, Dockerfile, and full documentation (environment
variables, an example Portainer/Compose service block, log format).

## History

This used to be a Laravel web app with a database-backed dashboard, a video-conversion pipeline
(`.mkv`/`.avi` → `.mp4` via ffmpeg), and a UI for the cleanup feature above. The conversion feature was
dropped first, and once that left the cleanup feature as the app's only real functionality, it was
rewritten as the plain shell script + cron container in `cron-cleanup/` — no PHP, database, or web
server required. The original PowerShell scheduled-task scripts this app first replaced no longer exist
either; `cron-cleanup/delete-expired.sh` is the direct descendant of both.
