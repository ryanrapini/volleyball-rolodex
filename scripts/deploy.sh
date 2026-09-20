#!/usr/bin/env bash
#
# Deploy a new release of the Volleyball Rolodex.
#
# Layout it maintains:
#   /srv/http/rolodex.ryanrapini.com/
#     releases/<timestamp>/   one immutable build per deploy
#     shared/.env             production environment, shared by every release
#     shared/storage/         logs, sessions, cache, uploaded photos (symlinked into each release)
#     shared/database/        the SQLite database, so it survives deploys
#     current -> releases/<timestamp>
#
# The swap is a single `ln -sfn`, so requests see either the old release or the
# new one, never a half-copied tree. A failed build never reaches the swap.
#
# Usage:  scripts/deploy.sh
#         scripts/deploy.sh --no-build   # frontend assets already built

set -euo pipefail

APP_ROOT=/srv/http/rolodex.ryanrapini.com
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN=rolodex.ryanrapini.com
RUN_USER=ryan
RUN_GROUP=http
KEEP_RELEASES=5

BUILD_ASSETS=1
[[ "${1:-}" == "--no-build" ]] && BUILD_ASSETS=0

timestamp="$(date +%Y%m%d%H%M%S)"
release="$APP_ROOT/releases/$timestamp"

log() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }

log "Preflight"
[[ -f "$APP_ROOT/shared/.env" ]] || { echo "shared/.env is missing — run scripts/write-production-env.php first"; exit 1; }
[[ -d "$SOURCE_DIR/app" ]] || { echo "not running from an app checkout ($SOURCE_DIR)"; exit 1; }
command -v composer >/dev/null || { echo "composer not on PATH"; exit 1; }

log "Creating release $timestamp"
sudo install -d -o "$RUN_USER" -g "$RUN_GROUP" -m 2775 "$release"

log "Syncing source"
sudo rsync -a --delete \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude 'storage/' \
  --exclude 'bootstrap/cache/*' \
  --exclude '.env' \
  --exclude '.git/' \
  --exclude '.phpunit.result.cache' \
  --exclude 'database/database.sqlite' \
  --exclude 'public/build/' \
  --exclude 'public/storage' \
  "$SOURCE_DIR/" "$release/"

log "Linking shared state"
sudo -u "$RUN_USER" ln -sfn "$APP_ROOT/shared/.env" "$release/.env"
sudo -u "$RUN_USER" ln -sfn "$APP_ROOT/shared/storage" "$release/storage"
sudo -u "$RUN_USER" install -d -o "$RUN_USER" -g "$RUN_GROUP" -m 2775 "$release/bootstrap/cache"

log "Installing PHP dependencies"
(cd "$release" && sudo -u "$RUN_USER" composer install \
  --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts 2>&1 | tail -3)

# --no-scripts skips the post-autoload-dump hook, which is what writes
# bootstrap/cache/services.php. Without this the app boots with no service
# providers and every request dies with `Class "view" does not exist`.
log "Discovering packages"
(cd "$release" && sudo -u "$RUN_USER" php artisan package:discover --ansi 2>&1 | tail -3)

log "Building frontend assets"
if [[ "$BUILD_ASSETS" == "1" ]]; then
  (cd "$release" && sudo -u "$RUN_USER" npm ci --no-audit --no-fund 2>&1 | tail -2 \
    && sudo -u "$RUN_USER" npm run build 2>&1 | tail -3)
else
  echo "skipped (--no-build)"
fi

log "Migrating"
(cd "$release" && sudo -u "$RUN_USER" php artisan migrate --force 2>&1 | tail -5)

log "Warming caches"
(cd "$release" && sudo -u "$RUN_USER" php artisan storage:link >/dev/null 2>&1 || true)
(cd "$release" && sudo -u "$RUN_USER" php artisan config:clear >/dev/null)

# Permissions come last because artisan writes bootstrap/cache/*.php with the
# process umask (0600 under sudo -u), leaving the package manifest unreadable to
# httpd — a 500 that survives the obvious config fixes.
log "Fixing permissions"
sudo chown -R "$RUN_USER:$RUN_GROUP" "$release"
sudo find "$release" -type d -exec chmod 2775 {} \;
sudo find "$release" -type f -exec chmod 664 {} \;
sudo chmod -R 2775 "$APP_ROOT/shared/storage" "$APP_ROOT/shared/database"
sudo chmod 664 "$APP_ROOT/shared/storage/logs/laravel.log" 2>/dev/null || true
sudo chmod 640 "$APP_ROOT/shared/.env"

log "Swapping current -> releases/$timestamp"
sudo -u "$RUN_USER" ln -sfn "$release" "$APP_ROOT/current"

log "Reloading Apache"
sudo systemctl reload httpd

log "Pruning old releases (keeping $KEEP_RELEASES)"
mapfile -t old < <(ls -1dt "$APP_ROOT"/releases/*/ 2>/dev/null | tail -n "+$((KEEP_RELEASES + 1))" || true)
for dir in "${old[@]:-}"; do
  [[ -n "$dir" ]] || continue
  echo "  removing $dir"
  sudo rm -rf "$dir"
done

log "Verifying"
if curl -fsS -o /dev/null -w '  local HTTP %{http_code}\n' -H "Host: $DOMAIN" http://127.0.0.1/ ; then
  echo "  deployed $release"
else
  echo "  WARNING: local request through Apache did not return 2xx/3xx"
  exit 1
fi
