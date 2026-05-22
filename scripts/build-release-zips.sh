#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FREE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGINS_DIR="$(cd "$FREE_DIR/.." && pwd)"
PRO_DIR="$PLUGINS_DIR/import-export-pro-by-rockstarlab"

FREE_SLUG="import-export-by-rockstarlab"
PRO_SLUG="import-export-pro-by-rockstarlab"

DIST_DIR="$FREE_DIR/dist"
mkdir -p "$DIST_DIR"

FREE_ZIP="$DIST_DIR/${FREE_SLUG}.zip"
PRO_ZIP="$DIST_DIR/${PRO_SLUG}.zip"

need_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1" >&2
    exit 1
  fi
}

need_cmd rsync
need_cmd zip
need_cmd php
need_cmd yarn

if [ ! -d "$PRO_DIR" ]; then
  echo "PRO plugin folder not found: $PRO_DIR" >&2
  exit 1
fi

echo "Cleaning dist directory..."
rm -f "$FREE_ZIP" "$PRO_ZIP"

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${FREE_SLUG}.release.XXXXXX")"
cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

echo "Building FREE assets (yarn run prod)..."
(cd "$FREE_DIR" && yarn run prod)

echo "Preparing FREE package..."
rsync -a --delete \
  --exclude-from="$FREE_DIR/.distignore" \
  "$FREE_DIR/" "$TMP_DIR/$FREE_SLUG/"

pushd "$TMP_DIR/$FREE_SLUG" >/dev/null

# Keep only Freemius in vendor for release ZIPs.
rm -rf vendor
if [ -d "$FREE_DIR/vendor/freemius" ]; then
  mkdir -p vendor
  rsync -a "$FREE_DIR/vendor/freemius" vendor/
else
  echo "Missing Freemius SDK folder: $FREE_DIR/vendor/freemius" >&2
  exit 1
fi

rm -f "$FREE_ZIP"
popd >/dev/null

(cd "$TMP_DIR" && zip -qr "$FREE_ZIP" "$FREE_SLUG")

echo "Preparing PRO package..."
rsync -a --delete \
  --exclude='/.git' \
  --exclude='/.github' \
  --exclude='/.husky' \
  --exclude='/.env*' \
  --exclude='/.gitignore' \
  --exclude='/node_modules' \
  --exclude='/documentation' \
  --exclude='/landing' \
  --exclude='/scripts' \
  --exclude='/dist' \
  --exclude='/.nvmrc' \
  --exclude='/.prettierrc' \
  --exclude='/.huskyrc.js' \
  --exclude='/package.json' \
  --exclude='/package-lock.json' \
  --exclude='/yarn.lock' \
  "$PRO_DIR/" "$TMP_DIR/$PRO_SLUG/"

rm -f "$PRO_ZIP"
(cd "$TMP_DIR" && zip -qr "$PRO_ZIP" "$PRO_SLUG")

echo "Built:"
echo " - $FREE_ZIP"
echo " - $PRO_ZIP"

# Deploy both plugins to aie2 local site by unzipping release archives.
AIE2_PLUGINS_DIR="/Users/shaggywizard/Local Sites/aie2/app/public/wp-content/plugins"

if [ -d "$AIE2_PLUGINS_DIR" ]; then
  echo "Removing old FREE plugin from aie2..."
  rm -rf "$AIE2_PLUGINS_DIR/$FREE_SLUG"

  echo "Removing old PRO plugin from aie2..."
  rm -rf "$AIE2_PLUGINS_DIR/$PRO_SLUG"

  echo "Deploying FREE plugin to aie2..."
  unzip -q "$FREE_ZIP" -d "$AIE2_PLUGINS_DIR"

  echo "Deploying PRO plugin to aie2..."
  unzip -q "$PRO_ZIP" -d "$AIE2_PLUGINS_DIR"

  echo "Deployed to aie2:"
  echo " - $AIE2_PLUGINS_DIR/$FREE_SLUG"
  echo " - $AIE2_PLUGINS_DIR/$PRO_SLUG"
else
  echo "WARNING: aie2 plugins directory not found: $AIE2_PLUGINS_DIR" >&2
fi
