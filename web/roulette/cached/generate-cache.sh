#!/bin/sh
set -e

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
DATA_DIR="${ROULETTE_CACHE_DIR:-$SCRIPT_DIR/../../sites/default/files/roulette-cache}"
mkdir -p "$DATA_DIR"

if [ -n "$PANTHEON_ENVIRONMENT" ]; then
  SITE="${PANTHEON_SITE_NAME:-piv-d11}"
  if [ "$PANTHEON_ENVIRONMENT" = "live" ]; then
    EN_BASE="https://poetryinvoice.ca"
    FR_BASE="https://lesvoixdelapoesie.ca"
  else
    EN_BASE="https://${PANTHEON_ENVIRONMENT}-${SITE}.pantheonsite.io"
    FR_BASE="${EN_BASE}/fr"
  fi
else
  EN_BASE="${ROULETTE_EN_BASE:-https://poetryinvoice.ca}"
  FR_BASE="${ROULETTE_FR_BASE:-https://lesvoixdelapoesie.ca}"
fi

CURL_AUTH=""
if [ -n "$ROULETTE_HTTP_AUTH" ]; then
  CURL_AUTH="-u $ROULETTE_HTTP_AUTH"
fi

fetch() {
  url="$1"
  out="$2"
  curl --connect-timeout 30 $CURL_AUTH -s -S -f -o "$out" "$url"
}

# Poets.
fetch "${EN_BASE}/cached/cached_poets_en_j.json" "$DATA_DIR/cached_poets_en_j.json"
fetch "${EN_BASE}/cached/cached_poets_en_s.json" "$DATA_DIR/cached_poets_en_s.json"
fetch "${FR_BASE}/cached/cached_poets_fr_j.json" "$DATA_DIR/cached_poets_fr_j.json"
fetch "${FR_BASE}/cached/cached_poets_fr_s.json" "$DATA_DIR/cached_poets_fr_s.json"

# Tags.
fetch "${EN_BASE}/tags-for-roulette" "$DATA_DIR/cached_tags_en.json"
fetch "${FR_BASE}/tags-for-roulette" "$DATA_DIR/cached_tags_fr.json"

# Moods.
fetch "${EN_BASE}/moods-for-roulette" "$DATA_DIR/cached_moods_en.json"
fetch "${FR_BASE}/moods-for-roulette" "$DATA_DIR/cached_moods_fr.json"
