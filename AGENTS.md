# AGENTS.md

## Purpose

This repository implements a flash-sale ticketing platform with numbered seats.

When modifying this project, prioritize correctness under concurrency, failure
handling, idempotency, authorization and reproducibility over introducing new
frameworks or abstractions.

Read these files before making architectural changes:

- `README.md`
- `DECISIONS.md`
- `docs/API.md`
- `docs/REQUIREMENTS_MATRIX.md`
- `docs/TESTING_AND_EVIDENCE.md`

---

## Documentation map

The `docs/` directory is part of the project contract. It is not auxiliary
documentation that can silently drift from the code.

Before changing a feature, identify which documents below describe or verify
that behavior. Update the relevant documents in the same change when behavior,
architecture, endpoints, infrastructure, tests, performance assumptions or
delivery instructions change.

### `docs/API.md`

Complete API reference.

Contains:

- authentication requirements;
- roles and authorization expectations;
- request payloads;
- response payloads;
- HTTP status codes;
- events and seats endpoints;
- reservations and confirmation;
- orders and cancellation;
- ticket reissue;
- reports;
- audit;
- alerts;
- operational metrics.

Read this file before modifying controllers, requests, resources, policies or
`routes/api.php`.

If an endpoint is added, removed, renamed, changes payload, changes status code
or changes authorization behavior, update `docs/API.md`.

The runtime route inventory can be regenerated with:

```bash
make routes
```

The generated evidence is stored in:

```text
docs/evidence/routes.txt
```

### `docs/PHASES.md`

High-level implementation checklist for Phases 1 through 5 of the technical
challenge.

Use it to understand which challenge phase a change belongs to and which
capabilities are expected to remain working.

It summarizes:

- Phase 1 foundation;
- Phase 2 concurrency and expiration;
- Phase 3 purchase confirmation and external communication;
- Phase 4 prolonged provider failure and architectural decision record;
- Phase 5 scale and differentiators.

If a feature is added or removed from a phase, keep this checklist aligned.

### `docs/REQUIREMENTS_MATRIX.md`

Traceability matrix from challenge requirement to:

```text
requirement
    -> implementation
    -> test / command
    -> evidence
```

This is the main compliance document.

Read it before declaring a challenge requirement complete.

Whenever implementation paths, verification commands or evidence locations
change, update this matrix.

Do not mark a requirement as verified unless the corresponding test or
reproducible command actually exists.

### `docs/TESTING_AND_EVIDENCE.md`

Source of truth for how the project is validated.

It explains the difference between:

```text
application startup
vs.
delivery verification
```

and documents:

- `make doctor`;
- PHPUnit;
- k6 contention test;
- SQL duplicate-sale validation;
- provider mode tests;
- prolonged outage/resilience tests;
- Phase 5 seeds;
- report benchmark;
- search benchmark;
- evidence output files.

Read this before changing tests, Make targets, load tests, resilience scripts or
evidence generation.

Do not add expensive/destructive benchmark workloads to normal
`docker compose up`.

### `docs/PERFORMANCE.md`

Performance verification for Phase 5.

Contains:

- 10,000-event search dataset;
- 200,000-ticket sales-report dataset;
- seed strategy;
- report query approach;
- search approach;
- benchmark commands;
- evidence file locations;
- rationale for not hard-coding latency measured on another machine.

Read this before changing report queries, search queries, indexes, benchmark
seeders or performance claims.

Any performance claim must come from a reproducible measurement.

### `docs/DOCKER.md`

Docker, Composer and startup behavior.

Explains:

- full-stack startup;
- why `api/vendor/` does not need to exist on the host;
- Composer installation inside the API image;
- entrypoint fallback;
- migrations and baseline seeds;
- Docker-internal service hostnames;
- optional IDE/local Composer workflow.

Read this before modifying:

- `docker-compose.yml`;
- `api/Dockerfile`;
- `api/docker/entrypoint.sh`;
- `docker/app.env`;
- Nginx configuration;
- container networking;
- Composer startup behavior.

The evaluator should not need local PHP or Composer.

### `docs/DELIVERY.md`

GitHub delivery instructions.

Defines:

- private repository requirement;
- files that must be committed;
- files that must not be committed;
- evaluator startup command;
- final evidence expectations;
- repository handoff steps.

Read this before changing repository layout, `.gitignore`, generated evidence or
delivery workflow.

### `docs/DELIVERY_CHECKLIST.md`

Operational pre-submission checklist.

Use this immediately before the final GitHub delivery.

It covers:

- clean startup from zero;
- Composer/vendor verification;
- migrations/seeds;
- PHPUnit;
- k6;
- provider behavior;
- resilience;
- Phase 5 datasets and benchmarks;
- route export;
- final Git status checks.

The preferred clean validation gate is:

```bash
make verify-delivery-fresh
```

### `docs/FINAL_AUDIT.md`

Final audit record for the repository.

Documents which checks are static and which still require runtime verification
on the delivery machine.

Do not change this file to claim runtime success unless the corresponding
runtime command was actually executed.

If a final audit assumption changes, update this document.

### `docs/ADR-001-seat-concurrency.md`

Architectural Decision Record for concurrent seat reservation.

Documents:

- MySQL as authority;
- pessimistic row locking;
- deterministic seat locking order;
- all-or-nothing reservations;
- expiration/reclaim behavior;
- alternatives such as Redis locks, optimistic locking and event serialization;
- consequences and contention trade-offs.

Read this before changing seat ownership, reservation locking or expiration
semantics.

If the concurrency strategy changes, update this ADR or create a superseding
ADR.

### `docs/ADR-002-resilience-scale.md`

Architectural Decision Record for purchase confirmation, notification
resilience and scale.

Documents:

- idempotent confirmation;
- transactional Outbox;
- asynchronous provider communication;
- at-least-once delivery;
- provider idempotency;
- retries/backoff/final failure;
- observability;
- 10x/100x evolution;
- alternatives such as synchronous HTTP, queue-without-Outbox, exactly-once and
  Kafka;
- product-level improvement suggestions.

Read this before changing the checkout transaction, Outbox, queue semantics,
notification provider behavior or scale strategy.

### `docs/ADR-002-resilience-scale.pdf`

The submission artifact required by the challenge.

It is the PDF representation of the Phase 4 ADR/RFC and must remain suitable
for submission.

When the source ADR changes materially, regenerate and visually inspect the PDF
before delivery.

Do not let the Markdown ADR and PDF contradict each other.

### `docs/evidence/`

Contains reproducible execution evidence generated on the delivery machine.

Expected files include:

```text
stack-doctor.txt
phpunit.txt
seat-contention.txt
provider-modes.txt
provider-resilience.txt
report-benchmark.txt
search-benchmark.txt
routes.txt
```

`docs/evidence/README.md` explains the directory.

Evidence files are outputs, not substitutes for tests.

Do not fabricate, hand-edit or copy performance/concurrency results from a
different environment.

Regenerate them after any material change affecting:

- infrastructure;
- concurrency;
- database schema;
- queue/provider behavior;
- routes;
- report/search performance.

---

## Documentation consistency rules

When code changes, keep these relationships synchronized:

```text
routes/api.php
    <-> docs/API.md
    <-> README endpoint summary
    <-> docs/evidence/routes.txt

challenge requirement
    <-> docs/REQUIREMENTS_MATRIX.md
    <-> docs/PHASES.md
    <-> test/evidence

architecture decision
    <-> DECISIONS.md
    <-> ADR(s)
    <-> README architecture overview

Docker/runtime behavior
    <-> docker-compose.yml
    <-> docs/DOCKER.md
    <-> README startup section
    <-> docs/DELIVERY_CHECKLIST.md

performance implementation
    <-> docs/PERFORMANCE.md
    <-> benchmark seeders/scripts
    <-> docs/evidence/*benchmark.txt
```

Documentation changes are part of the definition of done.

Do not leave documentation describing behavior that the current code no longer
implements.

---

## Stack

Main application:

- PHP 8.4
- Laravel 13
- MySQL 8.4
- Redis 7
- Laravel Sanctum
- Laravel Horizon
- Laravel Pulse
- Nginx
- PHP-FPM
- Docker Compose

Testing and tooling:

- PHPUnit
- k6
- Composer
- Make
- Bash

Secondary service:

- Simulated HTTP notification provider
- Runs as its own Docker container

---

## Project structure

Main Laravel application:

```text
api/app/
├── Application/
├── Contracts/
├── Enums/
├── Exceptions/
├── Http/
├── Infrastructure/
├── Jobs/
├── Models/
├── Policies/
└── Providers/
```

Important responsibilities:

- `Application/`
  - business use cases
  - reservation
  - purchase confirmation
  - cancellation
  - ticket reissue
  - reports
  - audit

- `Contracts/`
  - interfaces for infrastructure dependencies

- `Infrastructure/`
  - implementations of external integrations

- `Jobs/`
  - asynchronous processing

- `Policies/`
  - ownership and role authorization

- `Models/`
  - persistence and relationships

Keep controllers thin.

Do not move business rules into controllers.

---

## Namespace convention

Laravel PSR-4 root is:

```json
"App\\": "app/"
```

Therefore:

```text
api/app/Models/User.php
=> App\Models\User

api/app/Application/Reservations/ReserveSeats.php
=> App\Application\Reservations\ReserveSeats

api/app/Jobs/SendTicketNotification.php
=> App\Jobs\SendTicketNotification
```

Never introduce namespaces containing:

```text
atlas-ticketing
ticketing\api
api\app
```

All application namespaces must begin with:

```php
App\
```

After changing PHP files, run:

```bash
make namespace-check
```

---

## Architectural style

The application is a modular Laravel monolith.

It uses selected ideas from Clean Architecture and Ports & Adapters without
forcing every Laravel dependency behind an abstraction.

Do not introduce microservices, CQRS, Event Sourcing, Kafka or generic
repositories unless a concrete requirement justifies them.

Prefer simple Laravel code with explicit domain rules.

---

## Critical business invariants

### Seat reservation

A seat must never be actively reserved or sold to two different buyers.

MySQL is the source of truth.

Redis must never determine seat ownership.

Seat reservation currently relies on:

```text
DB transaction
+
SELECT ... FOR UPDATE
+
deterministic seat locking order
```

Do not replace database locking with Redis locks without documenting and
testing the consistency implications.

Multi-seat reservations are atomic:

```text
all requested seats succeed
or
none of them are reserved
```

---

## Reservation expiration

Reservations are temporary.

Current expiration is approximately:

```text
10 minutes
```

Correctness must not depend exclusively on the scheduler.

An expired reservation may be reclaimed transactionally even before the
background expiration command has normalized its database state.

Do not introduce a window where an expired reservation can still be confirmed
after another buyer has acquired its seat.

---

## Purchase confirmation

Purchase confirmation must be idempotent.

The same reservation must never generate two different purchases.

Important protection includes:

```text
orders.reservation_id UNIQUE
```

Repeated confirmation must return the already existing order/tickets instead of
creating new ones.

Never remove database uniqueness constraints and rely only on application code.

---

## Tickets

A confirmed purchase creates tickets.

A ticket contains:

- opaque ticket code / QR identifier
- seat
- purchase
- buyer CPF

CPF is sensitive data.

Requirements:

- keep CPF encrypted at rest
- never include CPF in logs
- never include CPF in QR payload
- never send CPF to the notification provider
- avoid returning the full CPF from API resources

---

## External notifications

Never call the external notification provider inside the purchase database
transaction or synchronously as part of the confirmation request.

The current flow is:

```text
Purchase transaction
    |
    +-- Order
    +-- Ticket(s)
    +-- OutboxEvent
    +-- NotificationDelivery
    |
   COMMIT
    |
    v
Outbox dispatcher
    |
    v
Redis Queue
    |
    v
Laravel Horizon
    |
    v
SendTicketNotification
    |
    v
HTTP Notification Provider
```

The system intentionally provides at-least-once delivery semantics.

Do not describe this system as exactly-once delivery.

Consumers must remain idempotent.

---

## Transactional Outbox

Order, tickets and the corresponding outbox event must be persisted in the same
database transaction.

Never dispatch the notification directly before the purchase transaction
commits.

Outbox status and delivery status represent different facts:

```text
Outbox PUBLISHED
=
message was dispatched to the queue

NotificationDelivery SENT
=
external provider accepted the notification
```

Do not merge these states.

---

## Notification failure handling

The provider deliberately supports unstable modes:

```text
normal
slow
error
down
duplicate
```

The main API must continue accepting purchases while the provider is
unavailable.

Notifications use:

- short connection timeout
- request timeout
- retries
- exponential/backoff delays
- final failed state
- explicit retry command

A provider outage must never roll back an already confirmed purchase.

---

## Authorization

Roles:

```text
buyer
organizer
```

Organizer:

- may manage only their own events
- may view reports/audit only for their own events

Buyer:

- may reserve seats
- may view only their own reservations/orders/tickets
- may cancel only their own purchases where allowed

Prefer Laravel Policies for ownership checks.

Never trust a client-supplied `user_id`, `buyer_id` or `organizer_id` when the
authenticated user determines ownership.

---

## Search

Event search currently uses MySQL through an abstraction:

```text
EventSearch
    |
    v
MysqlEventSearch
```

Keep the controller dependent on the contract rather than hard-coding the
storage implementation.

A search outage must remain isolated from the ticket purchase flow.

---

## Reports

Organizer sales reports must verify event ownership.

Performance changes should be validated using the existing benchmark and the
large dataset seed.

Do not introduce N+1 queries into report endpoints.

---

## Audit

Important state-changing actions must be auditable, including:

- reservation-related actions where applicable
- order cancellation
- ticket reissue

Audit data should be append-only.

Do not overwrite previous audit records.

---

## Observability

Local observability uses:

- Laravel Horizon for queue processing
- Laravel Pulse for application visibility
- structured application logs
- operational metrics endpoint
- persisted system alerts

Do not introduce an external observability stack unless justified.

Future production integration may use OpenTelemetry and systems such as
Datadog or Grafana.

---

## Docker

The complete stack must run through Docker Compose.

Internal service communication must use Docker service names.

Correct:

```text
http://notification-provider:8080
mysql:3306
redis:6379
```

Incorrect:

```text
localhost
127.0.0.1
hard-coded container IP
```

Do not add fixed `container_name` values unless there is a strong reason.
Compose-generated names avoid collisions between clones/projects.

---

## Composer dependencies

`api/vendor/` must not be committed.

Composer dependencies are installed during image build.

The application container should be self-contained after:

```bash
docker compose up -d --build
```

or the documented equivalent:

```bash
docker-compose up -d --build
```

---

## Tests required after changes

For normal PHP changes run:

```bash
make namespace-check
make test
```

For reservation/concurrency changes also run:

```bash
make load-test
```

For queue/provider/outbox changes also run the resilience tests.

Before final delivery run:

```bash
make verify-delivery
```

Never claim a concurrency, performance or resilience result without executing
the corresponding test.

---

## Load-test invariant

The required concurrency scenario is:

```text
50 concurrent buyers
10 seats
```

Expected result:

```text
10 successful reservations
40 conflicts
0 unexpected responses
0 duplicated seats
```

Changes to reservation logic must preserve this result.

---

## Database changes

When adding migrations:

- use foreign keys where appropriate
- add uniqueness constraints for business invariants
- add indexes justified by query patterns
- keep migration rollback valid
- avoid MySQL ENUM when PHP backed enums are sufficient

Do not encode critical invariants only in comments or application validation
when the database can enforce them.

---

## Code style

Prefer:

- small methods
- explicit names
- typed parameters and return types
- backed enums for finite domain states
- early validation
- dependency injection
- focused application services
- database constraints
- clear exceptions for domain conflicts

Avoid:

- fat controllers
- duplicated business rules
- hidden side effects
- static global state
- unnecessary abstractions
- generic repositories with no business value
- HTTP calls inside DB transactions

---

## AI-generated changes

AI-generated code is not considered correct by default.

Before accepting a generated change:

1. understand the affected invariant
2. inspect SQL/database implications
3. inspect concurrency implications
4. inspect failure behaviour
5. inspect authorization
6. inspect sensitive data handling
7. run the appropriate tests

If AI changes a critical architectural decision, update `DECISIONS.md` when
appropriate.

---

## Do not modify casually

Changes to these areas require extra review:

```text
ReserveSeats
ConfirmPurchase
ExpireReservations
SendTicketNotification
DispatchOutboxEvents
CancelOrder
database migrations
Laravel Policies
Docker Compose
load-tests/
```

These files implement the critical correctness guarantees of the challenge.
