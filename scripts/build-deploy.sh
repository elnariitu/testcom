#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M)"
OUT_DIR="$ROOT/deploy"
OUT_FILE="$OUT_DIR/maten-web-${STAMP}.zip"

mkdir -p "$OUT_DIR"

cd "$ROOT"

zip -r "$OUT_FILE" . \
  -x '*.git*' \
  -x '*__MACOSX*' \
  -x 'awstats/*' \
  -x '*.zip' \
  -x '.env' \
  -x 'maten_config.local.php' \
  -x 'config.local.php' \
  -x 'apns_config.local.php' \
  -x 'deploy/*' \
  -x '.DS_Store' \
  -x 'uploads/*'

echo "Created deploy archive: $OUT_FILE"
echo "Database config is included from includes/maten_config.local.php for this hosting package."
