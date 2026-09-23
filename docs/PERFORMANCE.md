# Phase 5 performance verification

## Dataset

`make phase5-seed` runs `Phase5Seeder` and creates:

- 10,000 published events for search testing;
- one event named `Report Benchmark 200k`;
- 25,000 confirmed orders for that event;
- 8 seats/tickets per order (same maximum accepted by the reservation API);
- exactly 200,000 ticket rows.

The large seed is intentionally not part of normal `docker compose up`, so a normal evaluator startup stays fast.

## Sales report

Endpoint:

```text
GET /api/events/{event}/report
```

The report uses SQL aggregates over `orders` and `tickets`; it does not hydrate 200,000 Eloquent models. Relevant access paths start with `orders.event_id` and `tickets.order_id` (FK/unique indexes).

Run:

```bash
make phase5-seed
make benchmark-report
```

The benchmark runs the same report multiple times and writes the measured output to:

```text
docs/evidence/report-benchmark.txt
```

This repository deliberately does not hard-code a latency claim measured on another machine. The committed final evidence should be the result produced on the delivery machine.

## Event search

Search uses prefix filters (`value%`) rather than leading wildcards so MySQL can use indexes on name/location, plus `starts_at` for date filtering. The seed supplies 10,000 events so the evaluator can inspect behavior on a non-trivial local dataset.

Measure the same search abstraction against the 10,000-event dataset with:

```bash
make benchmark-search
```

The measured min/avg/max is written to `docs/evidence/search-benchmark.txt`; no latency number is hard-coded before the final run.

If the search component is disabled via `EVENT_SEARCH_AVAILABLE=false`, the search endpoint returns 503 while reservation/checkout remain available. The interface boundary (`EventSearch`) allows a future OpenSearch/Elasticsearch implementation without moving checkout correctness out of MySQL.
