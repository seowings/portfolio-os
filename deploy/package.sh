#!/usr/bin/env bash
# Hostinger FTP packaging — produces app.zip + public.zip in deploy/dist/
# Does NOT upload. No live FTP. Local builds only.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST="${ROOT}/deploy/dist"
SKIP_BUILD=0
SKIP_COMPOSER=0
DRY_RUN=0
RESTORE_DEV=1

usage() {
  cat <<'EOF'
Usage: deploy/package.sh [options]

Build Hostinger-ready archive pair (no FTP upload):
  deploy/dist/app.zip     — Laravel app (incl. vendor --no-dev), without public/
  deploy/dist/public.zip  — contents of public/ (built assets + .htaccess + dual-path index.php)

Options:
  --skip-build       Skip npm run build (use existing public/build)
  --skip-composer    Skip composer --no-dev; use current vendor/ as-is
  --no-restore-dev   After packaging, do not run composer install (keep --no-dev vendor)
  --dry-run          Print steps only
  -h, --help         Show this help

Recommended order (script does this by default):
  1. npm run build
  2. composer install --no-dev --optimize-autoloader
  3. zip app + public → deploy/dist/
  4. composer install  (restore local dev tooling / tests)

Pre-flight (optional, manual):
  php artisan test
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-build) SKIP_BUILD=1; shift ;;
    --skip-composer) SKIP_COMPOSER=1; shift ;;
    --no-restore-dev) RESTORE_DEV=0; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
  esac
done

step() { echo "==> $*"; }
run() {
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "    [dry-run] $*"
  else
    eval "$@"
  fi
}

cd "$ROOT"

step "Root: $ROOT"
step "Output: $DIST"

if [[ "$SKIP_BUILD" -eq 0 ]]; then
  step "npm run build"
  run "npm run build"
else
  step "Skip npm run build"
fi

COMPOSER_RAN=0
if [[ "$SKIP_COMPOSER" -eq 0 ]]; then
  step "composer install --no-dev --optimize-autoloader"
  run "composer install --no-dev --optimize-autoloader --no-interaction"
  COMPOSER_RAN=1
else
  step "Skip composer --no-dev (using existing vendor/)"
fi

restore_dev() {
  if [[ "$COMPOSER_RAN" -eq 1 && "$RESTORE_DEV" -eq 1 && "$DRY_RUN" -eq 0 ]]; then
    step "Restoring full composer install for local dev"
    composer install --no-interaction
  fi
}
trap restore_dev EXIT

if [[ "$DRY_RUN" -eq 1 ]]; then
  step "Would create $DIST/app.zip and $DIST/public.zip"
  exit 0
fi

mkdir -p "$DIST"
rm -f "$DIST/app.zip" "$DIST/public.zip"

STAGE="$(mktemp -d "${TMPDIR:-/tmp}/portfolio-os-package.XXXXXX")"
cleanup_stage() {
  rm -rf "$STAGE"
}
trap 'cleanup_stage; restore_dev' EXIT

step "Staging app tree → $STAGE/app"
mkdir -p "$STAGE/app"

# rsync if available; else tar pipe.
# Patterns are root-anchored on purpose: a bare "dist/" or "tests/" would also
# strip vendor/livewire/livewire/dist (and any package tests), which breaks login
# on the deployed site (Livewire JS 500 → silent Livewire form submit).
if command -v rsync >/dev/null 2>&1; then
  rsync -a \
    --exclude '/public/' \
    --exclude '/node_modules/' \
    --exclude '/.git/' \
    --exclude '/tests/' \
    --exclude '/.env' \
    --exclude '/.env.backup' \
    --exclude '/.env.production' \
    --exclude '/deploy/dist/' \
    --exclude '/dist/' \
    --exclude '/docs/videos/*-build/' \
    --exclude '/storage/logs/*' \
    --exclude '/storage/framework/cache/*' \
    --exclude '/storage/framework/sessions/*' \
    --exclude '/storage/framework/views/*' \
    --exclude '/storage/framework/testing/*' \
    --exclude '/storage/pail' \
    --exclude '/.phpunit.result.cache' \
    --exclude '/phpunit.xml' \
    --exclude '/database/*.sqlite' \
    --exclude '/database/*.sqlite-journal' \
    "$ROOT/" "$STAGE/app/"
else
  # Portable fallback. Anchor every pattern to ./ so tar does not match names
  # deeper in vendor (same Livewire dist trap as the rsync path).
  tar -C "$ROOT" \
    --exclude='./public' \
    --exclude='./node_modules' \
    --exclude='./.git' \
    --exclude='./tests' \
    --exclude='./.env' \
    --exclude='./.env.backup' \
    --exclude='./.env.production' \
    --exclude='./deploy/dist' \
    --exclude='./dist' \
    --exclude='./storage/logs' \
    --exclude='./storage/framework/cache' \
    --exclude='./storage/framework/sessions' \
    --exclude='./storage/framework/views' \
    --exclude='./storage/framework/testing' \
    --exclude='./storage/pail' \
    --exclude='./.phpunit.result.cache' \
    --exclude='./phpunit.xml' \
    --exclude='./database/*.sqlite' \
    -cf - . | tar -C "$STAGE/app" -xf -
fi

# Keep storage skeleton (empty dirs + gitignore placeholders)
mkdir -p \
  "$STAGE/app/storage/app/public" \
  "$STAGE/app/storage/app/private" \
  "$STAGE/app/storage/framework/cache/data" \
  "$STAGE/app/storage/framework/sessions" \
  "$STAGE/app/storage/framework/views" \
  "$STAGE/app/storage/logs" \
  "$STAGE/app/bootstrap/cache"

# Drop accidental log/cache files from stage
find "$STAGE/app/storage/logs" -type f ! -name '.gitignore' -delete 2>/dev/null || true
find "$STAGE/app/storage/framework/cache" -type f ! -name '.gitignore' -delete 2>/dev/null || true
find "$STAGE/app/storage/framework/sessions" -type f ! -name '.gitignore' -delete 2>/dev/null || true
find "$STAGE/app/storage/framework/views" -type f ! -name '.gitignore' -delete 2>/dev/null || true

# Ensure .env is not packaged
rm -f "$STAGE/app/.env" "$STAGE/app/.env.backup" "$STAGE/app/.env.production"

if [[ ! -d "$STAGE/app/vendor" ]]; then
  echo "ERROR: vendor/ missing in package stage. Run without --skip-composer or install deps first." >&2
  exit 1
fi

# Livewire serves JS from vendor/.../dist over a Laravel route. Incomplete FTP
# uploads that omit this folder cause silent login (livewire.min.js → 500).
if [[ ! -f "$STAGE/app/vendor/livewire/livewire/dist/livewire.min.js" ]]; then
  echo "ERROR: vendor/livewire/livewire/dist/livewire.min.js missing from package." >&2
  echo "Re-run composer install so Livewire dist assets are present, then package again." >&2
  exit 1
fi

step "Creating app.zip"
(
  cd "$STAGE/app"
  if command -v zip >/dev/null 2>&1; then
    zip -qry "$DIST/app.zip" .
  else
    # macOS ditto alternative
    ditto -c -k --sequesterRsrc --keepParent . "$DIST/app.zip.tmp"
    # ditto keepParent nests folder; prefer pure zip contents at root of archive
    rm -f "$DIST/app.zip.tmp"
    (cd "$STAGE/app" && tar -cf - .) | (cd "$DIST" && tar -xf -) 2>/dev/null || true
    echo "zip CLI not found; install zip. Falling back to python zipfile." >&2
    python3 - <<PY
import zipfile, os
from pathlib import Path
root = Path("$STAGE/app")
out = Path("$DIST/app.zip")
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    for p in root.rglob("*"):
        if p.is_file():
            z.write(p, p.relative_to(root).as_posix())
print("wrote", out)
PY
  fi
)

step "Creating public.zip (contents of public/)"
if [[ ! -f "$ROOT/public/index.php" ]]; then
  echo "ERROR: public/index.php missing" >&2
  exit 1
fi
PUBLIC_STAGE="$STAGE/public"
mkdir -p "$PUBLIC_STAGE"
if command -v rsync >/dev/null 2>&1; then
  rsync -a --exclude 'storage' --exclude 'hot' --exclude 'hotfile' \
    "$ROOT/public/" "$PUBLIC_STAGE/"
else
  # copy then strip local storage link / hot file
  if command -v ditto >/dev/null 2>&1; then
    ditto "$ROOT/public" "$PUBLIC_STAGE"
  else
    cp -R "$ROOT/public/." "$PUBLIC_STAGE/"
  fi
  rm -rf "$PUBLIC_STAGE/storage" "$PUBLIC_STAGE/hot"
fi
(
  cd "$PUBLIC_STAGE"
  if command -v zip >/dev/null 2>&1; then
    zip -qry "$DIST/public.zip" .
  else
    python3 - <<PY
import zipfile
from pathlib import Path
root = Path("$PUBLIC_STAGE")
out = Path("$DIST/public.zip")
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    for p in root.rglob("*"):
        if p.is_file():
            z.write(p, p.relative_to(root).as_posix())
print("wrote", out)
PY
  fi
)

step "Done"
ls -lh "$DIST/app.zip" "$DIST/public.zip"
echo ""
echo "Next: see DEPLOYMENT.md — extract app.zip → laravel_app/, public.zip → public_html/"
