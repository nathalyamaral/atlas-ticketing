# Final pre-delivery audit

## Static checks completed on the final tree

- PHP syntax checked across **131 PHP files** in `api/` + `notification-provider/`.
- Bash/sh syntax checked for all delivery scripts and the API Docker entrypoint.
- Docker Compose YAML parsed successfully and contains the full stack: MySQL, Redis, provider, API/PHP-FPM, Nginx, Horizon and Scheduler.
- Composer is copied into the API image and `composer install` runs at image build. The entrypoint also has a fallback install if `vendor/autoload.php` is ever missing inside the container.
- `vendor/` is intentionally not committed and does not need to exist on the host; `make doctor` verifies it inside the container. `make composer-host` is optional for IDE/local tooling.
- Compose bootstrap is self-contained: the API waits for MySQL/Redis, then automatically executes `php artisan migrate --force` and `php artisan db:seed --force` before PHP-FPM becomes healthy.
- Docker startup does not depend on an uncommitted `api/.env`; local-only evaluation defaults are versioned in `docker/app.env`.
- Inter-container notification traffic uses `http://notification-provider:8080`, never host `localhost`/fixed IP.
- Endpoint inventory was cross-checked: all **19 API route declarations** are represented in README + `docs/API.md`; `make routes` exports the framework's actual route list to `docs/evidence/routes.txt`.
- Mandatory load test validates both stages: 50 concurrent reservation attempts over 10 seats, followed by confirmation of the 10 winners and SQL verification of zero duplicate sales.
- Phase 5 large seed is exactly **25,000 orders × 8 tickets = 200,000 tickets**.
- Phase 5 includes reproducible benchmarks for both the 200k-ticket report and search over 10,000 seeded events.
- Provider-mode script asserts normal, idempotent retry, HTTP 500, HTTP 503/down, duplicate delivery and ~5s slow behavior.
- ADR/RFC PDF remains the required 2-page artifact produced for the challenge.
- Repository tree excludes `api/.env`, `.idea`, `vendor`, `node_modules` and runtime logs.

## Runtime checks required on the delivery machine

This artifact-generation environment does not provide Docker/Docker Compose, so the final containerized runtime cannot truthfully be certified here. Final evidence must be generated on the machine that will be used before the GitHub push.

Recommended clean gate:

```bash
make verify-delivery-fresh
```

Non-destructive gate:

```bash
make verify-delivery
```

These generate/update:

```text
docs/evidence/stack-doctor.txt
docs/evidence/phpunit.txt
docs/evidence/seat-contention.txt
docs/evidence/provider-modes.txt
docs/evidence/provider-resilience.txt
docs/evidence/report-benchmark.txt
docs/evidence/search-benchmark.txt
docs/evidence/routes.txt
```

Only after the gate passes should the final evidence and README load-test result be committed.
