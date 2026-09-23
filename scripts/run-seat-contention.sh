#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(
    cd "$(dirname "${BASH_SOURCE[0]}")"
    pwd
)"

PROJECT_ROOT="$(
    cd "${SCRIPT_DIR}/.."
    pwd
)"

cd "$PROJECT_ROOT"

if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
else
    COMPOSE="docker-compose"
fi

K6_SCRIPT="${PROJECT_ROOT}/load-tests/seat-contention.js"
EVIDENCE_DIR="${PROJECT_ROOT}/docs/evidence"

if [ ! -f "$K6_SCRIPT" ]; then
    echo "ERROR: k6 script not found:"
    echo "$K6_SCRIPT"
    exit 1
fi

API_CONTAINER=$($COMPOSE ps -q api)

if [ -z "$API_CONTAINER" ]; then
    echo "ERROR: API container is not running."
    exit 1
fi

NETWORK=$(
    docker inspect \
        --format '{{range $name, $_ := .NetworkSettings.Networks}}{{$name}}{{"\n"}}{{end}}' \
        "$API_CONTAINER" \
        | grep 'atlas-network' \
        | head -n 1
)

if [ -z "$NETWORK" ]; then
    echo "ERROR: atlas-network was not found on the API container."
    exit 1
fi

echo "==> Docker network: $NETWORK"

echo "==> Preparing load-test scenario..."

$COMPOSE exec -T api \
    php artisan db:seed \
    --class=LoadTestSeeder

EVENT_ID=$(
    $COMPOSE exec -T mysql \
        mysql \
        -N \
        -s \
        -uatlas \
        -patlas \
        atlas_ticketing \
        -e "
            SELECT id
            FROM events
            WHERE name = 'Flash Sale Load Test'
            ORDER BY id DESC
            LIMIT 1;
        " 2>/dev/null
)

if [ -z "$EVENT_ID" ]; then
    echo "ERROR: could not determine load-test event ID."
    exit 1
fi

echo "==> Event ID: $EVENT_ID"

mkdir -p "$EVIDENCE_DIR"

echo "==> Running 50 buyers against 10 seats..."

docker run --rm \
    --network "$NETWORK" \
    -e BASE_URL=http://nginx \
    -e EVENT_ID="$EVENT_ID" \
    -v "${PROJECT_ROOT}/load-tests:/scripts:ro" \
    grafana/k6 \
    run /scripts/seat-contention.js \
    | tee "${EVIDENCE_DIR}/seat-contention.txt"

echo
echo "==> Validating database..."

ACTIVE_RESERVATIONS=$(
    $COMPOSE exec -T mysql \
        mysql \
        -N \
        -s \
        -uatlas \
        -patlas \
        atlas_ticketing \
        -e "
            SELECT COUNT(*)
            FROM reservations
            WHERE event_id = ${EVENT_ID}
              AND status = 'active'
              AND expires_at > NOW();
        " 2>/dev/null
)

DUPLICATED_SEATS=$(
    $COMPOSE exec -T mysql \
        mysql \
        -N \
        -s \
        -uatlas \
        -patlas \
        atlas_ticketing \
        -e "
            SELECT COUNT(*)
            FROM (
                SELECT ri.seat_id
                FROM reservation_items ri
                INNER JOIN reservations r
                    ON r.id = ri.reservation_id
                WHERE r.event_id = ${EVENT_ID}
                  AND r.status = 'active'
                  AND r.expires_at > NOW()
                GROUP BY ri.seat_id
                HAVING COUNT(*) > 1
            ) AS duplicates;
        " 2>/dev/null
)

echo "Active reservations: $ACTIVE_RESERVATIONS"
echo "Duplicated seats:     $DUPLICATED_SEATS"

{
    echo
    echo "DATABASE VALIDATION"
    echo "Event ID: $EVENT_ID"
    echo "Active reservations: $ACTIVE_RESERVATIONS"
    echo "Duplicated seats: $DUPLICATED_SEATS"
} >> "${EVIDENCE_DIR}/seat-contention.txt"

if [ "$ACTIVE_RESERVATIONS" -ne 10 ]; then
    echo "FAILED: expected exactly 10 active reservations."
    exit 1
fi

if [ "$DUPLICATED_SEATS" -ne 0 ]; then
    echo "FAILED: duplicated active seat reservation detected."
    exit 1
fi

echo
echo "==> Confirming the 10 winning reservations..."

while IFS=$'\t' read -r RESERVATION_ID BUYER_EMAIL; do
    [ -n "$RESERVATION_ID" ] || continue

    LOGIN_RESPONSE=$(curl -fsS -X POST \
        http://localhost:8000/api/auth/login \
        -H 'Content-Type: application/json' \
        -H 'Accept: application/json' \
        -d "{\"email\":\"${BUYER_EMAIL}\",\"password\":\"Password123!\"}")

    TOKEN=$(printf '%s' "$LOGIN_RESPONSE" \
        | python3 -c 'import sys,json; print(json.load(sys.stdin)["token"])')

    CONFIRM_CODE=$(curl -sS -o /tmp/atlas-load-confirm.json -w '%{http_code}' \
        -X POST "http://localhost:8000/api/reservations/${RESERVATION_ID}/confirm" \
        -H 'Content-Type: application/json' \
        -H 'Accept: application/json' \
        -H "Authorization: Bearer ${TOKEN}" \
        -H "Idempotency-Key: load-${EVENT_ID}-${RESERVATION_ID}" \
        -d '{"cpf":"12345678901"}')

    if [ "$CONFIRM_CODE" != "201" ] && [ "$CONFIRM_CODE" != "200" ]; then
        echo "FAILED: reservation ${RESERVATION_ID} confirmation HTTP=${CONFIRM_CODE}"
        cat /tmp/atlas-load-confirm.json
        exit 1
    fi
done < <(
    $COMPOSE exec -T mysql \
        mysql -N -B -uatlas -patlas atlas_ticketing \
        -e "
            SELECT r.id, u.email
            FROM reservations r
            INNER JOIN users u ON u.id = r.buyer_id
            WHERE r.event_id = ${EVENT_ID}
              AND r.status = 'active'
              AND r.expires_at > NOW()
            ORDER BY r.id;
        " 2>/dev/null
)

echo "==> Validating confirmed sales..."

CONFIRMED_ORDERS=$(
    $COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing \
        -e "SELECT COUNT(*) FROM orders WHERE event_id=${EVENT_ID} AND status='confirmed';" 2>/dev/null
)

SOLD_SEATS=$(
    $COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing \
        -e "SELECT COUNT(*) FROM seats WHERE event_id=${EVENT_ID} AND status='sold';" 2>/dev/null
)

DUPLICATED_SALES=$(
    $COMPOSE exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing \
        -e "
            SELECT COUNT(*)
            FROM (
                SELECT t.seat_id
                FROM tickets t
                INNER JOIN orders o ON o.id = t.order_id
                WHERE o.event_id = ${EVENT_ID}
                  AND o.status = 'confirmed'
                  AND t.status = 'active'
                GROUP BY t.seat_id
                HAVING COUNT(DISTINCT o.id) > 1
            ) sold_duplicates;
        " 2>/dev/null
)

echo "Confirmed orders: $CONFIRMED_ORDERS"
echo "Sold seats:       $SOLD_SEATS"
echo "Duplicated sales: $DUPLICATED_SALES"

{
    echo "Confirmed orders: $CONFIRMED_ORDERS"
    echo "Sold seats: $SOLD_SEATS"
    echo "Duplicated sales: $DUPLICATED_SALES"
} >> "${EVIDENCE_DIR}/seat-contention.txt"

if [ "$CONFIRMED_ORDERS" -ne 10 ] || [ "$SOLD_SEATS" -ne 10 ]; then
    echo "FAILED: expected 10 confirmed orders and 10 sold seats."
    exit 1
fi

if [ "$DUPLICATED_SALES" -ne 0 ]; then
    echo "FAILED: the same seat was sold to more than one confirmed order."
    exit 1
fi

echo
echo "PASS: 10 reservation winners, 10 confirmed sales, zero duplicated seats/sales."
TIMESTAMP=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
python3 - "$TIMESTAMP" <<'PY'
from pathlib import Path
import sys

path = Path('README.md')
text = path.read_text()
start = '<!-- LOAD_TEST_RESULT_START -->'
end = '<!-- LOAD_TEST_RESULT_END -->'
result = (
    f"{start}\n"
    f"**Última execução final:** {sys.argv[1]} - 50 VUs disputando 10 assentos; "
    "10 reservas vencedoras, 40 conflitos, 0 respostas inesperadas; as 10 reservas vencedoras foram confirmadas "
    "e a validação SQL encontrou 10 assentos vendidos e 0 vendas duplicadas.\n"
    f"{end}"
)
left, rest = text.split(start, 1)
_, right = rest.split(end, 1)
path.write_text(left + result + right)
PY

echo "README load-test result block updated."
