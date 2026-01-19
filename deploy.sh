#!/usr/bin/env bash

set -euo pipefail

SOURCE_DIR="$(pwd)"
TARGET_DIR="/var/www/html"

if [[ ! -d "$SOURCE_DIR" ]]; then
    echo "Source directory not found: $SOURCE_DIR"
    exit 1
fi

if [[ ! -d "$TARGET_DIR" ]]; then
    echo "Target directory not found: $TARGET_DIR"
    exit 1
fi

echo "Deploying to $TARGET_DIR"

rsync -av --delete \
    --include='*/' \
    --include='*.php' \
    --include='*.html' \
    --include='*.css' \
    --exclude='*' \
    "$SOURCE_DIR" "$TARGET_DIR"

echo "Deployment complete."

