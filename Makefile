COMPOSE := $(shell \
	if docker compose version >/dev/null 2>&1; then \
		echo "docker compose"; \
	else \
		echo "docker-compose"; \
	fi \
)

.PHONY: help setup up down build migrate seed reset test test-setup load-test resilience-test provider-test phase5-seed benchmark-report benchmark-search routes doctor namespace-check composer-host lint verify-delivery verify-delivery-fresh logs

help:
	@echo "Atlas Ticketing commands:"
	@echo "  make setup                 Build/start full stack; API migrates + baseline seeds automatically"
	@echo "  make doctor                Verify containers, Composer/vendor, DB, Horizon, Scheduler and health"
	@echo "  make test                  PHPUnit against MySQL test database"
	@echo "  make load-test             k6: 50 concurrent buyers / 10 seats + duplicate-sale SQL validation"
	@echo "  make provider-test         Exercise normal/error/down/duplicate/slow provider modes"
	@echo "  make resilience-test       Prove provider outage does not block checkout and recovers"
	@echo "  make phase5-seed           Seed 10k searchable events + exactly 200k tickets"
	@echo "  make benchmark-report      Benchmark 200k-ticket event sales report"
	@echo "  make benchmark-search      Benchmark indexed search on 10k events"
	@echo "  make routes                Export Laravel API route list to docs/evidence/routes.txt"
	@echo "  make verify-delivery       Setup + all tests/load/resilience/Phase5 benchmarks + routes"
	@echo "  make verify-delivery-fresh DESTRUCTIVE: remove volumes, then run full verification"
	@echo "  make composer-host         OPTIONAL: create api/vendor on host for IDE/local tooling"

setup:
	$(COMPOSE) up -d --build
	@echo "Project ready: API http://localhost:8000 | Horizon /horizon | Pulse /pulse"

up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

migrate:
	$(COMPOSE) exec -T api php artisan migrate --force

seed:
	$(COMPOSE) exec -T api php artisan db:seed --force

reset:
	$(COMPOSE) exec -T api php artisan migrate:fresh --seed --force

test-setup:
	./scripts/setup-test-db.sh

test:
	./scripts/run-tests.sh

load-test:
	./scripts/run-seat-contention.sh

resilience-test:
	./scripts/test-provider-resilience.sh

provider-test:
	./scripts/test-provider-modes.sh

phase5-seed:
	$(COMPOSE) exec -T api php artisan db:seed --class=Phase5Seeder

benchmark-report:
	@mkdir -p docs/evidence; \
	EVENT_ID=`$(COMPOSE) exec -T mysql mysql -N -s -uatlas -patlas atlas_ticketing -e "SELECT id FROM events WHERE name='Report Benchmark 200k' ORDER BY id DESC LIMIT 1" 2>/dev/null`; \
	if [ -z "$$EVENT_ID" ]; then echo "Run make phase5-seed first"; exit 1; fi; \
	$(COMPOSE) exec -T api php artisan reports:benchmark $$EVENT_ID --runs=5 | tee docs/evidence/report-benchmark.txt

benchmark-search:
	@mkdir -p docs/evidence; \
	$(COMPOSE) exec -T api php artisan search:benchmark --runs=5 | tee docs/evidence/search-benchmark.txt

routes:
	./scripts/export-routes.sh

doctor:
	./scripts/check-stack.sh

# Runtime does NOT require host vendor/. Composer dependencies are baked into
# the API/Horizon/Scheduler image. This target exists only for IDE/local tooling.
composer-host:
	docker run --rm \
		--user "$$(id -u):$$(id -g)" \
		-v "$(CURDIR)/api:/app" \
		-w /app \
		-e COMPOSER_HOME=/tmp/composer \
		composer:2 \
		composer install --no-interaction --prefer-dist --optimize-autoloader

lint:
	$(COMPOSE) exec -T api sh -lc 'find app routes database tests -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null && echo "PHP syntax OK"'

verify-delivery: setup namespace-check doctor test load-test provider-test resilience-test phase5-seed benchmark-report benchmark-search routes
	@echo "All reproducible delivery checks completed. Review docs/evidence before committing."

verify-delivery-fresh:
	@echo "WARNING: removing MySQL/Redis volumes before verification."
	$(COMPOSE) down -v --remove-orphans
	$(MAKE) verify-delivery

logs:
	$(COMPOSE) logs -f
