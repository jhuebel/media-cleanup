#!/bin/sh
set -e

PUID="${PUID:-1000}"
PGID="${PGID:-1000}"
TZ="${TZ:-UTC}"

# --- Timezone -------------------------------------------------------------
if [ -f "/usr/share/zoneinfo/$TZ" ]; then
    ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime
    echo "$TZ" > /etc/timezone
fi

# --- Match the container's app user to the host media owner ---------------
# NAS media is usually owned by a specific host user/group. We remap our
# fixed "appuser" account's numeric ids to PUID/PGID (rather than chown-ing
# /media itself) so file reads/writes/deletes/renames under /media land
# with the same ownership the NAS already expects.
CURRENT_UID=$(id -u appuser)
CURRENT_GID=$(id -g appuser)

if [ "$CURRENT_GID" != "$PGID" ]; then
    groupmod -o -g "$PGID" appuser
fi

if [ "$CURRENT_UID" != "$PUID" ]; then
    usermod -o -u "$PUID" appuser
fi

# --- App storage -----------------------------------------------------------
mkdir -p /var/www/html/storage/app \
         /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R appuser:appuser /var/www/html/storage /var/www/html/bootstrap/cache

# --- APP_KEY: generate once and persist it in the storage volume ----------
KEY_FILE="/var/www/html/storage/app/app.key"

if [ -z "$APP_KEY" ]; then
    if [ -f "$KEY_FILE" ]; then
        APP_KEY=$(cat "$KEY_FILE")
    else
        APP_KEY="base64:$(openssl rand -base64 32)"
        echo "$APP_KEY" > "$KEY_FILE"
        chown appuser:appuser "$KEY_FILE"
    fi
    export APP_KEY
fi

# --- Database: ensure the SQLite file exists, then migrate ----------------
DB_DATABASE="${DB_DATABASE:-/var/www/html/storage/app/database.sqlite}"
export DB_DATABASE

if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    chown appuser:appuser "$DB_DATABASE"
fi

gosu appuser php artisan migrate --force

exec "$@"
