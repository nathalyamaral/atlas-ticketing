#!/usr/bin/env bash

set -euo pipefail

if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
else
    COMPOSE="docker-compose"
fi

echo "Creating test database..."

$COMPOSE exec -T mysql \
    mysql \
    -uroot \
    -proot \
    -e "
        CREATE DATABASE IF NOT EXISTS atlas_ticketing_test
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci;

        GRANT ALL PRIVILEGES
        ON atlas_ticketing_test.*
        TO 'atlas'@'%';

        FLUSH PRIVILEGES;
    "

echo "Test database ready."