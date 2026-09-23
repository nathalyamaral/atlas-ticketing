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

LOG_FILE="docs/evidence/stack-doctor.txt"
exec > >(tee "$LOG_FILE") 2>&1

echo "==> Containers"
$COMPOSE ps

echo
echo "==> Composer inside API image"
$COMPOSE exec -T api composer --version

echo
echo "==> vendor/autoload.php inside API image"
$COMPOSE exec -T api sh -c 'test -f vendor/autoload.php && echo "OK: /var/www/html/vendor/autoload.php exists"'

echo
echo "==> Laravel"
$COMPOSE exec -T api php artisan --version

echo
echo "==> Database migrations"
$COMPOSE exec -T api php artisan migrate:status

echo
echo "==> Horizon"
$COMPOSE exec -T api php artisan horizon:status

echo
echo "==> Scheduler"
$COMPOSE exec -T api php artisan schedule:list

echo
echo "==> HTTP health checks"
curl -fsS http://localhost:8000/up >/dev/null
echo "OK: API/Nginx http://localhost:8000/up"
curl -fsS http://localhost:8081/health
echo

echo "PASS: stack, Composer/vendor, database, Horizon, Scheduler and HTTP health checks are ready."
