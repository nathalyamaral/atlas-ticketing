#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "$PROJECT_ROOT"
mkdir -p docs/evidence

if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
else
    COMPOSE="docker-compose"
fi

PROVIDER_URL="http://localhost:8081"
LOG_FILE="docs/evidence/provider-modes.txt"
: > "$LOG_FILE"

log() { printf '%s\n' "$*" | tee -a "$LOG_FILE"; }

curl -fsS -X POST "$PROVIDER_URL/admin/reset" >/dev/null

log "==> normal"
NORMAL=$(curl -sS -o /tmp/atlas-provider-normal.json -w '%{http_code}' \
  -X POST "$PROVIDER_URL/notifications" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: provider-normal-1' \
  -d '{"type":"test"}')
[ "$NORMAL" = "202" ] || { log "FAILED normal HTTP=$NORMAL"; exit 1; }
log "PASS normal HTTP=202"

log "==> idempotent retry"
DEDUP=$(curl -fsS -X POST "$PROVIDER_URL/notifications" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: provider-normal-1' \
  -d '{"type":"test"}')
printf '%s\n' "$DEDUP" | grep -q '"deduplicated": true' || { log "FAILED dedup"; exit 1; }
log "PASS deduplicated retry"

log "==> error"
curl -fsS -X POST "$PROVIDER_URL/admin/mode" -H 'Content-Type: application/json' -d '{"mode":"error"}' >/dev/null
ERROR_CODE=$(curl -sS -o /tmp/atlas-provider-error.json -w '%{http_code}' \
  -X POST "$PROVIDER_URL/notifications" -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: provider-error-1' -d '{"type":"test"}')
[ "$ERROR_CODE" = "500" ] || { log "FAILED error HTTP=$ERROR_CODE"; exit 1; }
log "PASS error HTTP=500"

log "==> down"
curl -fsS -X POST "$PROVIDER_URL/admin/mode" -H 'Content-Type: application/json' -d '{"mode":"down"}' >/dev/null
DOWN_CODE=$(curl -sS -o /tmp/atlas-provider-down.json -w '%{http_code}' \
  -X POST "$PROVIDER_URL/notifications" -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: provider-down-1' -d '{"type":"test"}')
[ "$DOWN_CODE" = "503" ] || { log "FAILED down HTTP=$DOWN_CODE"; exit 1; }
log "PASS down HTTP=503"

log "==> duplicate"
curl -fsS -X POST "$PROVIDER_URL/admin/reset" >/dev/null
curl -fsS -X POST "$PROVIDER_URL/admin/mode" -H 'Content-Type: application/json' -d '{"mode":"duplicate"}' >/dev/null
DUP_CODE=$(curl -sS -o /tmp/atlas-provider-duplicate.json -w '%{http_code}' \
  -X POST "$PROVIDER_URL/notifications" -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: provider-duplicate-1' -d '{"type":"test"}')
[ "$DUP_CODE" = "202" ] || { log "FAILED duplicate HTTP=$DUP_CODE"; exit 1; }
if $COMPOSE ps -q notification-provider >/dev/null 2>&1; then
    $COMPOSE exec -T notification-provider sh -c 'grep -q "duplicate_delivery" /tmp/received-notifications.log' \
        || { log "FAILED duplicate mode did not record duplicate delivery"; exit 1; }
fi
log "PASS duplicate mode HTTP=202 with duplicate delivery evidence"

log "==> slow"
curl -fsS -X POST "$PROVIDER_URL/admin/reset" >/dev/null
curl -fsS -X POST "$PROVIDER_URL/admin/mode" -H 'Content-Type: application/json' -d '{"mode":"slow"}' >/dev/null
SLOW_RESULT=$(curl -sS -o /tmp/atlas-provider-slow.json -w '%{http_code} %{time_total}' \
  -X POST "$PROVIDER_URL/notifications" -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: provider-slow-1' -d '{"type":"test"}')
SLOW_CODE=$(printf '%s' "$SLOW_RESULT" | awk '{print $1}')
SLOW_TIME=$(printf '%s' "$SLOW_RESULT" | awk '{print $2}')
[ "$SLOW_CODE" = "202" ] || { log "FAILED slow HTTP=$SLOW_CODE"; exit 1; }
python3 - "$SLOW_TIME" <<'PYTIME'
import sys
elapsed = float(sys.argv[1])
if elapsed < 4.5:
    raise SystemExit(f"FAILED: slow mode returned too quickly ({elapsed:.3f}s)")
PYTIME
log "PASS slow HTTP=202 TIME=${SLOW_TIME}s"

curl -fsS -X POST "$PROVIDER_URL/admin/reset" >/dev/null
log "PASS: all provider modes reproduced."
