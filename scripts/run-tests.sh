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

./scripts/setup-test-db.sh

set +e
$COMPOSE exec -T api php artisan test 2>&1 | tee docs/evidence/phpunit.txt
STATUS=${PIPESTATUS[0]}
set -e

exit "$STATUS"
