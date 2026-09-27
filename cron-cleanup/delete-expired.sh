#!/usr/bin/env bash
set -euo pipefail

MEDIA_ROOT="${MEDIA_ROOT:-/media}"
SCAN_PATH="${SCAN_PATH:-}"
MARKER_FILENAME="${MARKER_FILENAME:-deleteafter.txt}"
DELETE_EXTENSIONS="${DELETE_EXTENSIONS:-mp4,mkv,avi,srt,sub}"
LOCK_FILE="${LOCK_FILE:-/tmp/delete-expired.lock}"
CLEAN_TRICKPLAY="${CLEAN_TRICKPLAY:-true}"
TRICKPLAY_SUFFIX="${TRICKPLAY_SUFFIX:-.trickplay}"

exec 9>"$LOCK_FILE"
flock -n 9 || { echo "$(date -Iseconds) Skipping: previous run still in progress."; exit 0; }

ROOT="$MEDIA_ROOT"
[ -n "$SCAN_PATH" ] && ROOT="$MEDIA_ROOT/$SCAN_PATH"

if [ ! -d "$MEDIA_ROOT" ]; then
    echo "$(date -Iseconds) Media root does not exist: $MEDIA_ROOT" >&2
    exit 1
fi

REAL_ROOT=$(realpath "$MEDIA_ROOT")
REAL_SCAN=$(realpath -m "$ROOT")
case "$REAL_SCAN" in
    "$REAL_ROOT"|"$REAL_ROOT"/*) ;;
    *)
        echo "$(date -Iseconds) Scan path escapes media root: $ROOT" >&2
        exit 1
        ;;
esac
ROOT="$REAL_SCAN"

if [ ! -d "$ROOT" ]; then
    echo "$(date -Iseconds) Scan path does not exist: $ROOT" >&2
    exit 1
fi

# Build a find(1) "-iname *.ext -o -iname *.ext2 ..." expression from the
# comma-separated extension list.
name_expr=()
IFS=',' read -ra exts <<< "$DELETE_EXTENSIONS"
for ext in "${exts[@]}"; do
    ext="$(echo "$ext" | tr -d '[:space:]')"
    [ -z "$ext" ] && continue
    [ "${#name_expr[@]}" -gt 0 ] && name_expr+=(-o)
    name_expr+=(-iname "*.${ext}")
done

if [ "${#name_expr[@]}" -eq 0 ]; then
    echo "$(date -Iseconds) No delete extensions configured, nothing to do."
    exit 0
fi

markers_found=0
files_deleted=0
trickplay_dirs_deleted=0

while IFS= read -r -d '' marker; do
    markers_found=$((markers_found + 1))
    dir=$(dirname "$marker")
    days=$(head -n1 "$marker" | tr -d '[:space:]')

    if ! [[ "$days" =~ ^[0-9]+$ ]] || [ "$days" -le 0 ]; then
        echo "$(date -Iseconds) ${marker}: BAD VALUE ('${days}')"
        continue
    fi

    echo "$(date -Iseconds) ${dir}: ${days} day(s)"

    while IFS= read -r -d '' file; do
        echo "$(date -Iseconds)   - ${file}"
        rm -f -- "$file"
        files_deleted=$((files_deleted + 1))

        if [ "$CLEAN_TRICKPLAY" = "true" ]; then
            # Jellyfin's "save trickplay files into media folders" option
            # writes a sibling "<filename-without-extension>.trickplay/"
            # directory next to the video; Jellyfin itself doesn't clean
            # these up when the video goes away (jellyfin/jellyfin#13223),
            # so it's orphaned unless we do it here.
            trickplay_dir="${file%.*}${TRICKPLAY_SUFFIX}"
            if [ -d "$trickplay_dir" ]; then
                echo "$(date -Iseconds)     - removing trickplay data: ${trickplay_dir}"
                rm -rf -- "$trickplay_dir"
                trickplay_dirs_deleted=$((trickplay_dirs_deleted + 1))
            fi
        fi
    done < <(find "$dir" -type f \( "${name_expr[@]}" \) -mtime "+$((days - 1))" -print0)

done < <(find "$ROOT" -type f -name "$MARKER_FILENAME" -print0 | sort -z)

echo "$(date -Iseconds) Done. ${markers_found} marker(s) found, ${files_deleted} file(s) deleted, ${trickplay_dirs_deleted} trickplay folder(s) removed."
