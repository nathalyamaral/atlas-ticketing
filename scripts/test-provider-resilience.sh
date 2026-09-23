#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "$PROJECT_ROOT"
mkdir -p docs/evidence
exec > >(tee docs/evidence/provider-resilience.txt) 2>&1

if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
else
    COMPOSE="docker-compose"
fi

API_URL="http://localhost:8000"
PROVIDER_URL="http://localhost:8081"

echo "==> Reset provider and prepare isolated event"
curl -fsS -X POST "$PROVIDER_URL/admin/reset" >/dev/null
$COMPOSE exec -T api php artisan db:seed --class=LoadTestSeeder >/dev/null

EVENT_ID=$($COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT id FROM events WHERE name='Flash Sale Load Test' ORDER BY id DESC LIMIT 1" 2>/dev/null)
SEAT_ID=$($COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT id FROM seats WHERE event_id=${EVENT_ID} AND status='available' ORDER BY id LIMIT 1" 2>/dev/null)

LOGIN=$(curl -fsS -X POST "$API_URL/api/auth/login" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"loadtest-buyer-01@atlas.test","password":"Password123!"}')
TOKEN=$(printf '%s' "$LOGIN" | python3 -c 'import sys,json; print(json.load(sys.stdin)["token"])')

RESERVATION=$(curl -fsS -X POST "$API_URL/api/events/${EVENT_ID}/reservations" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -H "Authorization: Bearer $TOKEN" \
  -d "{\"seat_ids\":[${SEAT_ID}]}")
RESERVATION_ID=$(printf '%s' "$RESERVATION" | python3 -c 'import sys,json; print(json.load(sys.stdin)["data"]["id"])')

echo "==> Put provider in SLOW mode before checkout"
curl -fsS -X POST "$PROVIDER_URL/admin/mode" -H 'Content-Type: application/json' -d '{"mode":"slow"}' >/dev/null

IDEMPOTENCY_KEY="resilience-$(date +%s)"
HTTP_RESULT=$(curl -sS -o /tmp/atlas-resilience-confirm.json \
  -w '%{http_code} %{time_total}' \
  -X POST "$API_URL/api/reservations/${RESERVATION_ID}/confirm" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: $IDEMPOTENCY_KEY" \
  -d '{"cpf":"12345678901"}')
HTTP_CODE=$(printf '%s' "$HTTP_RESULT" | awk '{print $1}')
HTTP_TIME=$(printf '%s' "$HTTP_RESULT" | awk '{print $2}')

if [ "$HTTP_CODE" != "201" ]; then
    echo "FAILED: confirmation HTTP=$HTTP_CODE"
    cat /tmp/atlas-resilience-confirm.json
    exit 1
fi

python3 - "$HTTP_TIME" <<'PYTIME'
import sys
elapsed = float(sys.argv[1])
if elapsed >= 3.0:
    raise SystemExit(f"FAILED: checkout took {elapsed:.3f}s while provider slow mode sleeps 5s")
PYTIME

echo "Confirmation stayed independent from slow provider: HTTP=$HTTP_CODE TIME=${HTTP_TIME}s"

echo "==> Switch provider to DOWN before asynchronous delivery"
curl -fsS -X POST "$PROVIDER_URL/admin/mode" -H 'Content-Type: application/json' -d '{"mode":"down"}' >/dev/null

ORDER_ID=$($COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT id FROM orders WHERE reservation_id=${RESERVATION_ID} LIMIT 1" 2>/dev/null)
$COMPOSE exec -T api php artisan outbox:dispatch >/dev/null

ATTEMPTS=0
for _ in $(seq 1 15); do
    ATTEMPTS=$($COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT nd.attempts FROM notification_deliveries nd JOIN outbox_events oe ON oe.id=nd.outbox_event_id WHERE oe.aggregate_type='order' AND oe.aggregate_id=${ORDER_ID} LIMIT 1" 2>/dev/null)
    if [ "${ATTEMPTS:-0}" -ge 1 ]; then break; fi
    sleep 1
done

if [ "${ATTEMPTS:-0}" -lt 1 ]; then
    echo "FAILED: notification worker did not attempt provider call"
    exit 1
fi

echo "Observed provider failure; attempts=$ATTEMPTS"

echo "==> Recover provider"
curl -fsS -X POST "$PROVIDER_URL/admin/mode" -H 'Content-Type: application/json' -d '{"mode":"normal"}' >/dev/null

STATUS=""
for _ in $(seq 1 45); do
    STATUS=$($COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT nd.status FROM notification_deliveries nd JOIN outbox_events oe ON oe.id=nd.outbox_event_id WHERE oe.aggregate_type='order' AND oe.aggregate_id=${ORDER_ID} LIMIT 1" 2>/dev/null)
    if [ "$STATUS" = "sent" ]; then break; fi
    sleep 1
done

if [ "$STATUS" != "sent" ]; then
    echo "FAILED: notification did not recover; status=$STATUS"
    exit 1
fi

FINAL_ATTEMPTS=$($COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT nd.attempts FROM notification_deliveries nd JOIN outbox_events oe ON oe.id=nd.outbox_event_id WHERE oe.aggregate_type='order' AND oe.aggregate_id=${ORDER_ID} LIMIT 1" 2>/dev/null)

ORDER_STATUS=$($COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT status FROM orders WHERE id=${ORDER_ID}" 2>/dev/null)
if [ "$ORDER_STATUS" != "confirmed" ]; then
    echo "FAILED: order status changed unexpectedly: $ORDER_STATUS"
    exit 1
fi

echo "PASS: checkout ignored slow provider, order remained confirmed during outage, and notification recovered automatically (attempts=$FINAL_ATTEMPTS)."
