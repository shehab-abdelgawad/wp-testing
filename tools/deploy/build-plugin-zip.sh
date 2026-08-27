#!/usr/bin/env bash
#
# Builds an installable wp-testing.zip from the current git HEAD:
#   1. git archive HEAD (respects .gitattributes export-ignore) into a clean tree
#   2. composer install --no-dev in that tree (pulls in vendor/, applies patches,
#      runs clean-up-vendor.sh)
#   3. zips the result as wp-testing-<version>.zip, ready to upload via
#      Plugins > Add New > Upload Plugin
#
# Usage: tools/deploy/build-plugin-zip.sh [ref]
#   ref defaults to HEAD.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REF="${1:-HEAD}"
SLUG="wp-testing"

cd "$REPO_ROOT"

if [ -n "$(git status --porcelain)" ]; then
    echo "Warning: working tree has uncommitted changes; git archive only packages what's committed to '$REF'." >&2
fi

VERSION="$(grep -oP '^\s*\*\s*Version:\s*\K[0-9][0-9A-Za-z.\-]*' wp-testing.php)"
if [ -z "$VERSION" ]; then
    echo "Could not read Version from wp-testing.php" >&2
    exit 1
fi

DIST_DIR="$REPO_ROOT/dist"
BUILD_DIR="$(mktemp -d)"
PLUGIN_DIR="$BUILD_DIR/$SLUG"
trap 'rm -rf "$BUILD_DIR"' EXIT

mkdir -p "$PLUGIN_DIR"
git archive --worktree-attributes "$REF" | tar -x -C "$PLUGIN_DIR"

(
    cd "$PLUGIN_DIR"
    composer install --no-dev --optimize-autoloader --no-interaction
)

mkdir -p "$DIST_DIR"
ZIP_PATH="$DIST_DIR/${SLUG}-${VERSION}.zip"
rm -f "$ZIP_PATH"
(
    cd "$BUILD_DIR"
    zip -rq "$ZIP_PATH" "$SLUG"
)

echo "Built $ZIP_PATH"
