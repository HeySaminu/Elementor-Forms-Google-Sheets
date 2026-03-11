#!/bin/sh

set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/.build"
PACKAGE_DIR="$BUILD_DIR/elementor-forms-google-sheets"
DIST_DIR="$ROOT_DIR/dist"
VERSION="$(sed -n 's/^ \* Version: //p' "$ROOT_DIR/elementor-forms-google-sheets.php" | head -n 1)"
OUTPUT_ZIP="$ROOT_DIR/elementor-forms-google-sheets-installable.zip"
VERSIONED_ZIP="$DIST_DIR/elementor-forms-google-sheets-v$VERSION.zip"

if [ -z "$VERSION" ]; then
    echo "Unable to determine plugin version from elementor-forms-google-sheets.php" >&2
    exit 1
fi

mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"
rm -rf "$PACKAGE_DIR"
rm -f "$OUTPUT_ZIP"
rm -f "$VERSIONED_ZIP"
mkdir -p "$PACKAGE_DIR/includes"

cp "$ROOT_DIR/elementor-forms-google-sheets.php" "$PACKAGE_DIR/"
cp "$ROOT_DIR/includes/action-google-sheets.php" "$PACKAGE_DIR/includes/"

if [ -f "$ROOT_DIR/CHANGELOG.md" ]; then
    cp "$ROOT_DIR/CHANGELOG.md" "$PACKAGE_DIR/"
fi

cd "$BUILD_DIR"
zip -rq "$OUTPUT_ZIP" elementor-forms-google-sheets
cp "$OUTPUT_ZIP" "$VERSIONED_ZIP"

echo "Built: $OUTPUT_ZIP"
echo "Built: $VERSIONED_ZIP"
