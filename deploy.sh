#!/usr/bin/env bash
#
# Production deploy for Kor Hishab.
#
#   ssh you@server
#   cd /var/www/kor-hishab
#   ./deploy.sh
#
# First-time server setup (PHP 8.4, Nginx, .env, HTTPS) is described in
# README.md → "Deploying to a VPS". This script is the everyday part: ship
# whatever is on GitHub with as little downtime as possible.
#
# NO BUILD STEP: the CSS and JS are plain files in public/ (Vite is an unused
# skeleton leftover), so unlike most Laravel apps there is no `npm ci` /
# `npm run build` here and the server does not need Node at all.
#
# ORDERING CONTRACT:
#
#   The pull and Composer run with the site UP — they only add files. The
#   maintenance window covers just the backup → migrate → cache rebuild, the
#   only part where a request could see a half-updated schema or stale config.
#
# Safe to re-run. Every step is idempotent.

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

# Files this script creates (logs, caches, SQLite -wal/-shm) must stay writable
# by PHP-FPM's www-data group. Combined with the setgid dirs from the README
# setup, umask 002 makes them group-writable instead of owner-only.
umask 002

BRANCH="${DEPLOY_BRANCH:-main}"
LOG="storage/logs/deploy.log"
BACKUP_DIR="storage/app/backups"      # storage/app/.gitignore already ignores it
KEEP_BACKUPS=10
TOTAL=9
STEP=0
IN_MAINTENANCE=0

# ---------------------------------------------------------------------------
# Look & feel. Colours, spinner and live output only on a real terminal;
# plain lines when piped, in cron, or when an agent runs it.
# ---------------------------------------------------------------------------
if [[ -t 1 && "${TERM:-dumb}" != dumb && -z "${NO_COLOR:-}" ]]; then
    TTY=1
    R=$'\e[0m' B=$'\e[1m' D=$'\e[2m' RED=$'\e[31m' GRN=$'\e[32m' YEL=$'\e[33m' CYN=$'\e[36m' TEAL=$'\e[38;5;37m'
else
    TTY=0 R='' B='' D='' RED='' GRN='' YEL='' CYN='' TEAL=''
fi
FRAMES=('⠋' '⠙' '⠹' '⠸' '⠼' '⠴' '⠦' '⠧' '⠇' '⠏')

repeat() { local s='' i; for ((i = 0; i < $2; i++)); do s+="$1"; done; printf '%s' "$s"; }

# Step header with an overall progress bar:
#   ━━━━━━━━━━━━━━━──────────────  3/9  Composer
step() {
    STEP=$((STEP + 1))
    local w=30 f=$((STEP * 30 / TOTAL))
    printf '\n  %s%s%s%s%s  %s%d/%d%s  %s%s%s\n' \
        "$TEAL" "$(repeat '━' "$f")" "$D" "$(repeat '─' $((w - f)))" "$R" \
        "$D" "$STEP" "$TOTAL" "$R" "$B" "$1" "$R"
    printf '\n===== [%d/%d] %s — %s\n' "$STEP" "$TOTAL" "$1" "$(date '+%F %T')" >>"$LOG"
}

ok()   { printf '    %s✔%s %s\n' "$GRN" "$R" "$*"; }
note() { printf '    %s·%s %s%s%s\n' "$D" "$R" "$D" "$*" "$R"; }
warn() { printf '    %s!%s %s%s%s\n' "$YEL" "$R" "$YEL" "$*" "$R"; }
die()  { printf '\n    %s✘ %s%s\n' "$RED" "$*" "$R" >&2; exit 1; }

# run "Label" command…
#   Spinner + elapsed time + the command's latest output line, live, on one row.
#   Everything also goes to storage/logs/deploy.log. A failure stops the deploy.
run() {
    local label="$1" start=$SECONDS rc=0
    shift
    printf '$ %s\n' "$*" >>"$LOG"

    if ((TTY)); then
        "$@" >>"$LOG" 2>&1 &
        local pid=$! i=0 head last room cols
        tput civis 2>/dev/null || true
        while kill -0 "$pid" 2>/dev/null; do
            cols=$(tput cols 2>/dev/null || echo 80)
            head="    ${FRAMES[i % 10]} $label  $((SECONDS - start))s"
            last=$(tail -n 1 "$LOG" 2>/dev/null | tr -d '\r' | sed -E 's/\x1b\[[0-9;]*[A-Za-z]//g; s/^[[:space:]]+//')
            room=$((cols - ${#head} - 5))
            ((room < 0)) && room=0
            printf '\r\e[K    %s%s%s %s  %s%ds  %s%s' "$CYN" "${FRAMES[i % 10]}" "$R" "$label" "$D" $((SECONDS - start)) "${last:0:room}" "$R"
            i=$((i + 1))
            sleep 0.08
        done
        wait "$pid" || rc=$?
        tput cnorm 2>/dev/null || true
        printf '\r\e[K'
    else
        "$@" >>"$LOG" 2>&1 || rc=$?
    fi

    local t=$((SECONDS - start))
    if ((rc == 0)); then
        if ((t >= 1)); then ok "$label ${D}${t}s${R}"; else ok "$label"; fi
    else
        printf '    %s✘ %s%s %s(exit %d)%s\n\n' "$RED" "$label" "$R" "$D" "$rc" "$R"
        tail -n 15 "$LOG" | sed "s/^/      ${D}│${R} /"
        die "Deploy stopped at \"$label\". Full log: $LOG"
    fi
}

# Never leave the site in maintenance mode because a step failed.
on_exit() {
    local rc=$?
    tput cnorm 2>/dev/null || true
    if ((IN_MAINTENANCE)); then
        php artisan up >>"$LOG" 2>&1 || true
        printf '    %s!%s Site brought back up. Pre-deploy database copy: %s%s%s\n\n' "$YEL" "$R" "$B" "${BACKUP_FILE:-none}" "$R" >&2
    fi
    exit "$rc"
}
trap on_exit EXIT
trap 'exit 130' INT TERM

# ---------------------------------------------------------------------------
# Banner
# ---------------------------------------------------------------------------
mkdir -p "$(dirname "$LOG")"
printf '\n  %s৳ Kor Hishab%s %s— deploying %s%s\n' "$B$TEAL" "$R" "$D" "$BRANCH" "$R"

# ---------------------------------------------------------------------------
# 1. Guards. Nothing is changed until all of these pass, so a failure here
#    leaves the server exactly as it was.
# ---------------------------------------------------------------------------
step "Pre-flight"

[[ -f artisan ]] || die "No artisan here — run this from the application root."
[[ -f .env ]] || die "No .env — do the first-time setup in README.md first."

# Laravel 13 pulls in Symfony 8, which needs PHP ≥ 8.4.1. Ubuntu 24.04's apt
# PHP is 8.3 and Composer's error for that is long and unhelpful.
php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' \
    || die "PHP $(php -r 'echo PHP_VERSION;') is too old — Kor Hishab needs PHP ≥ 8.4.1."

# Edited files on the server: stop rather than discard someone's work.
# (Untracked files are fine — the SQLite database and .env are untracked.)
if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    git status --short --untracked-files=no | sed 's/^/      /'
    die "Tracked files were edited on the server. Commit or discard them (git checkout -- .), then re-run."
fi

run "Fetching origin/$BRANCH" git fetch --quiet origin "$BRANCH"

# --ff-only later would fail anyway; checking now means it fails before any change.
git merge-base --is-ancestor HEAD "origin/$BRANCH" \
    || die "This server has commits that aren't on origin/$BRANCH. Push them, or: git reset --hard origin/$BRANCH"

FROM=$(git rev-parse --short HEAD)
NEW_COMMITS=$(git rev-list --count "HEAD..origin/$BRANCH")

note "php     $(php -r 'echo PHP_VERSION;')"
note "commit  $FROM  $(git log -1 --format=%s)"
if ((NEW_COMMITS == 0)); then
    note "github  nothing new — re-running the remaining steps anyway"
else
    note "github  $NEW_COMMITS new commit(s):"
    git log --format="%h  %s" "HEAD..origin/$BRANCH" | head -n 8 | sed "s/^/              ${D}│${R} /"
fi

# ---------------------------------------------------------------------------
# 2–3. Fetch and install. Site stays up for all of this.
# ---------------------------------------------------------------------------
step "Pulling $BRANCH"
if ((NEW_COMMITS > 0)); then
    run "Fast-forward to origin/$BRANCH" git merge --ff-only --quiet "origin/$BRANCH"
else
    note "Already up to date"
fi

step "Composer"
run "composer install --no-dev" \
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

# ---------------------------------------------------------------------------
# 4–7. The window. Keep it as short as possible.
# ---------------------------------------------------------------------------
step "Maintenance mode"
run "php artisan down" php artisan down --retry=15
IN_MAINTENANCE=1

# The whole app state is one SQLite file, so a bad migration is survivable by
# copying it back — but only if the copy exists. PHP's SQLite3::backup() takes
# a consistent snapshot even with WAL active, and needs no sqlite3 CLI.
step "Backing up the database"
DB_CONNECTION_VALUE=$(grep -E '^DB_CONNECTION=' .env | cut -d= -f2 | tr -d '"' || true)
if [[ "${DB_CONNECTION_VALUE:-sqlite}" == sqlite && -f database/database.sqlite ]]; then
    mkdir -p "$BACKUP_DIR"
    BACKUP_FILE="$BACKUP_DIR/database-$(date +%Y%m%d-%H%M%S)-$FROM.sqlite"
    run "Snapshot → $BACKUP_FILE" php -r '
        $src = new SQLite3($argv[1], SQLITE3_OPEN_READONLY);
        $src->busyTimeout(5000);
        exit($src->backup(new SQLite3($argv[2])) ? 0 : 1);
    ' database/database.sqlite "$BACKUP_FILE"
    [[ -s "$BACKUP_FILE" ]] || die "The backup file is empty — refusing to migrate."
    note "$(du -h "$BACKUP_FILE" | cut -f1)  ·  keeping the last $KEEP_BACKUPS"
    # shellcheck disable=SC2012
    ls -1t "$BACKUP_DIR"/database-*.sqlite 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)) | xargs -r rm -f
else
    warn "Database is '${DB_CONNECTION_VALUE}', not SQLite — no automatic backup. Back it up yourself."
fi

step "Migrations"
run "php artisan migrate" php artisan migrate --force

step "Rebuilding caches"
run "Clearing old caches" php artisan optimize:clear
run "Caching config, routes, views, events" php artisan optimize

# Compiled Blade views keep the SAME filename when their contents change, and
# the README enables OPcache with validate_timestamps=0 — without a reload PHP
# keeps serving the old code. Classic "I deployed and nothing changed".
step "Reloading PHP-FPM"
FPM="php$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')-fpm"

# Root → passwordless sudo → interactive prompt only if someone is there to type.
if systemctl reload "$FPM" 2>/dev/null \
    || { command -v sudo >/dev/null && sudo -n systemctl reload "$FPM" 2>/dev/null; } \
    || { [[ -t 0 ]] && command -v sudo >/dev/null && sudo systemctl reload "$FPM"; }; then
    ok "Reloaded $FPM"
else
    warn "Could not reload $FPM — PHP may still run the OLD code. Run: sudo systemctl reload $FPM"
fi

# ---------------------------------------------------------------------------
# 9. Back up, then make sure the app actually answers.
# ---------------------------------------------------------------------------
step "Going live"
run "php artisan up" php artisan up
IN_MAINTENANCE=0

# Ask Nginx on this machine for /up, using the real hostname from APP_URL, so
# it works whatever DNS or a CDN in front is doing.
APP_URL=$(grep -E '^APP_URL=' .env | cut -d= -f2- | tr -d '"' || true)
HOST=${APP_URL#*://}; HOST=${HOST%%/*}; HOST=${HOST%%:*}
CODE=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 10 \
    --resolve "$HOST:443:127.0.0.1" --resolve "$HOST:80:127.0.0.1" "${APP_URL%/}/up" 2>/dev/null || true)
if [[ "$CODE" == 200 ]]; then ok "Health check  /up → 200"
else warn "Health check /up returned '${CODE:-nothing}' — check storage/logs/laravel.log"; fi

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------
TO=$(git rev-parse --short HEAD)
printf '\n  %s━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━%s\n' "$GRN" "$R"
if [[ "$FROM" == "$TO" ]]; then
    printf '  %s✓ Deployed %s%s %s(no new commits)  ·  %ds%s\n' "$B$GRN" "$TO" "$R" "$D" "$SECONDS" "$R"
else
    printf '  %s✓ Deployed %s%s %s(from %s, %d commit(s))  ·  %ds%s\n' "$B$GRN" "$TO" "$R" "$D" "$FROM" "$NEW_COMMITS" "$SECONDS" "$R"
fi
printf '  %s%s%s\n\n' "$D" "${APP_URL:-}" "$R"
