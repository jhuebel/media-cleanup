#!/usr/bin/env bash
set -euo pipefail

TZ="${TZ:-UTC}"
PUID="${PUID:-1000}"
PGID="${PGID:-1000}"

if [ -f "/usr/share/zoneinfo/$TZ" ]; then
    ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime
    echo "$TZ" > /etc/timezone
fi

# Cron jobs don't inherit the container's environment, so the settings the
# script needs have to be dumped to a file it can source at run time.
env | grep -E '^(MEDIA_ROOT|SCAN_PATH|MARKER_FILENAME|DELETE_EXTENSIONS|CLEAN_TRICKPLAY|TRICKPLAY_SUFFIX)=' > /etc/environment

# crond itself stays root (it owns /etc/crontabs and doesn't touch /media),
# but the actual script - and any files it deletes - run as PUID:PGID so
# ownership on the NAS share matches the other containers writing to it.
echo "${CRON_SCHEDULE:-0 3 * * *} . /etc/environment; su-exec ${PUID}:${PGID} /usr/local/bin/delete-expired.sh >> /proc/1/fd/1 2>> /proc/1/fd/2" \
    > /etc/crontabs/root

echo "Scheduled '${CRON_SCHEDULE:-0 3 * * *}' as ${PUID}:${PGID}, TZ=${TZ}."

if [ "${RUN_ON_START:-false}" = "true" ]; then
    su-exec "${PUID}:${PGID}" /usr/local/bin/delete-expired.sh || true
fi

exec crond -f -l 2
