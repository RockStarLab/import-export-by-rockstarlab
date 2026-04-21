#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="$(basename "$ROOT_DIR")"

if ! command -v composer >/dev/null 2>&1; then
  echo "composer not found. Install Composer to build the WP.org ZIP." >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "zip not found. Install zip to build the WP.org ZIP." >&2
  exit 1
fi

DIST_DIR="$ROOT_DIR/dist"
mkdir -p "$DIST_DIR"

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${PLUGIN_SLUG}.dist.XXXXXX")"
cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

# Copy plugin sources, excluding dev-only folders.
rsync -a --delete \
  --exclude-from="$ROOT_DIR/.distignore" \
  "$ROOT_DIR/" "$TMP_DIR/$PLUGIN_SLUG/"

pushd "$TMP_DIR/$PLUGIN_SLUG" >/dev/null

# Install production Composer deps only (drops vendor/* dev packages).
rm -rf vendor
# Skip scripts because post-install hooks reference dev-only tools (phpcs).
composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress --optimize-autoloader

# Freemius SDK is vendored manually (not in composer.json); keep it in the release.
if [ -d "$ROOT_DIR/vendor/freemius" ]; then
  mkdir -p vendor
  rsync -a "$ROOT_DIR/vendor/freemius" vendor/
else
  echo "Missing $ROOT_DIR/vendor/freemius (Freemius SDK). Release ZIP would be broken." >&2
  exit 1
fi

VERSION="$(php -r "preg_match(\"/Version:\\s*([^\\n]+)/\", file_get_contents('import-export-by-rockstarlab.php'), \$m); echo trim(\$m[1] ?? 'unknown');")"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"

rm -f "$DIST_DIR/$ZIP_NAME"
zip -qr "$DIST_DIR/$ZIP_NAME" .

popd >/dev/null

echo "Built: $DIST_DIR/$ZIP_NAME"
