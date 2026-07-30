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
need_cmd corepack

YARN_CMD=(corepack yarn@1.22.22)

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
(cd "$FREE_DIR" && "${YARN_CMD[@]}" run prod)

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
  --exclude='/README.md' \
	--exclude='/.DS_Store' \
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

deploy_to_local_site() {
  local site_slug="$1"
  local plugins_dir="/Users/shaggywizard/Local Sites/${site_slug}/app/public/wp-content/plugins"

  if [ ! -d "$plugins_dir" ]; then
    echo "WARNING: ${site_slug} plugins directory not found: $plugins_dir" >&2
    return
  fi

  echo "Removing old FREE plugin from ${site_slug}..."
  rm -rf "$plugins_dir/$FREE_SLUG"

  echo "Removing old PRO plugin from ${site_slug}..."
  rm -rf "$plugins_dir/$PRO_SLUG"

  echo "Deploying FREE plugin to ${site_slug}..."
  unzip -q "$FREE_ZIP" -d "$plugins_dir"

  echo "Deploying PRO plugin to ${site_slug}..."
  unzip -q "$PRO_ZIP" -d "$plugins_dir"

  echo "Deployed to ${site_slug}:"
  echo " - $plugins_dir/$FREE_SLUG"
  echo " - $plugins_dir/$PRO_SLUG"
}

# Deploy both plugins to local test sites by unzipping release archives.
for site_slug in aie2 aie3 aie4; do
  deploy_to_local_site "$site_slug"
done
