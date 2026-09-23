# API reference

Base URL local: `http://localhost:8000/api`.

Except for `POST /auth/login`, all endpoints require `Authorization: Bearer <token>` and should send `Accept: application/json`.

## Authentication

### POST `/auth/login`

Request:

```json
{"email":"buyer@atlas.test","password":"Password123!"}
```

Success `200`:

```json
{
  "token": "1|...",
  "user": {"id": 2, "name": "Atlas Buyer", "email": "buyer@atlas.test", "role": "buyer"}
}
```

Invalid credentials: `422`.

### GET `/me`

Returns the authenticated user (`200`). Unauthenticated: `401`.

### POST `/auth/logout`

Revokes the current Sanctum token and returns `200`.

## Events and seats

### GET `/events`

Role: organizer. Returns only events owned by the authenticated organizer, paginated (`200`). Buyer: `403`.

### POST `/events`

Role: organizer.

Request:

```json
{
  "name": "Flash Sale",
  "location": "Campo Grande - MS",
  "starts_at": "2026-12-10T20:00:00-04:00",
  "sales_start_at": "2026-09-21T10:00:00-04:00",
  "sales_end_at": "2026-12-10T18:00:00-04:00",
  "status": "published"
}
```

Success `201`: event resource. Validation: `422`. Buyer: `403`.

### POST `/events/{event}/seats`

Role: organizer that owns the event.

Request:

```json
{
  "seats": [
    {"sector":"A","row_label":"A","number":"1"},
    {"sector":"A","row_label":"A","number":"2"}
  ]
}
```

Success `201`:

```json
{"message":"Seats created successfully.","count":2}
```

Duplicate physical seat: `409`. Other organizer/buyer: `403`.

### GET `/events/{event}/seats`

Role: buyer, or organizer that owns the event. Returns paginated seats currently available. A reservation whose TTL has elapsed is logically exposed as available even before housekeeping normalizes the row.

Success `200` item:

```json
{"id":1,"sector":"A","row":"A","number":"1","status":"available"}
```

## Reservation and purchase

### POST `/events/{event}/reservations`

Role: buyer. Reserves 1-8 distinct seat IDs atomically.

Request:

```json
{"seat_ids":[1,2]}
```

Success `201`:

```json
{
  "data": {
    "id": 10,
    "event_id": 1,
    "status": "active",
    "expires_at": "...",
    "seats": [{"id":1,"sector":"A","row":"A","number":"1"}]
  }
}
```

Seat unavailable, wrong event or closed sale: `409`. Organizer: `403`. Validation: `422`.

### GET `/reservations/{reservation}`

Role: buyer that owns the reservation. Returns reservation and seats (`200`). Another buyer: `403`.

### POST `/reservations/{reservation}/confirm`

Role: buyer that owns the reservation. Optional/recommended header: `Idempotency-Key`.

Request:

```json
{"cpf":"123.456.789-01"}
```

First success: `201`; idempotent repeat for the same reservation: `200` with the same order/tickets.

Response:

```json
{
  "data": {
    "id": 20,
    "reservation_id": 10,
    "event_id": 1,
    "status": "confirmed",
    "confirmed_at": "...",
    "cancelled_at": null,
    "tickets": [
      {
        "id": 30,
        "code": "uuid",
        "qr_payload": "atlas-ticket:uuid",
        "status": "active",
        "version": 1,
        "buyer_cpf": "***.***.***-01",
        "seat": {"id":1,"sector":"A","row":"A","number":"1"},
        "issued_at": "..."
      }
    ]
  }
}
```

Expired/lost reservation or reused idempotency key for another reservation: `409`. Validation: `422`.

### GET `/orders`

Role: buyer. Returns only orders owned by the authenticated buyer, with tickets, paginated (`200`). Organizer: `403`.

### GET `/orders/{order}`

Role: buyer that owns the order. Success `200`; another buyer: `403`.

### POST `/orders/{order}/cancel`

Role: buyer that owns the order. Cancels active tickets and releases seats in the same transaction. Repetition is idempotent.

Success `200`: order resource with `status=cancelled`. Another buyer: `403`.

### POST `/tickets/{ticket}/reissue`

Role: buyer that owns the ticket. Rotates the opaque ticket code, increments `version`, preserves the same ticket row/seat, and appends `ticket.reissued` to the audit trail.

Success `200`: updated ticket resource. Cancelled ticket/order: `409`. Another buyer: `403`.

## Organizer reporting and audit

### GET `/events/{event}/report`

Role: organizer that owns the event. Returns SQL aggregates for orders and tickets plus locally measured query time (`200`). Other organizer/buyer: `403`.

Example:

```json
{
  "data": {
    "event_id": 1,
    "event_name": "Flash Sale",
    "orders": {"total":10,"confirmed":9,"cancelled":1,"unique_buyers":8},
    "tickets": {"total":20,"active":18,"cancelled":2},
    "query_time_ms": 12.34,
    "generated_at": "..."
  }
}
```

### GET `/events/{event}/audit`

Role: organizer that owns the event. Returns append-only audit entries, including actor, action, entity and metadata (`200`, paginated). Other organizer/buyer: `403`.

Recorded business actions include event creation, seat-map creation, reservation creation, order confirmation, order cancellation and ticket reissue.

## Search

### GET `/events/search`

Role: buyer.

Query parameters: `name`, `location`, `starts_from`, `starts_to`, `per_page` (1-100).

Example:

```text
GET /api/events/search?name=Festival&location=Campo&starts_from=2026-10-01&starts_to=2027-01-01
```

Success `200`: paginated published events. If the search mechanism is intentionally disabled (`EVENT_SEARCH_AVAILABLE=false`), only this endpoint returns `503`; checkout remains independent.

## Operational endpoints

### GET `/system/alerts`

Role: organizer. Returns automatic system alerts, paginated (`200`). Buyer: `403`.

### GET `/ops/metrics`

Role: organizer. Returns local operational counters (`200`): pending Outbox, pending/failed notifications, open alerts, confirmation attempts/failures and current failure-rate percentage. Buyer: `403`.

## Common status codes

- `200`: success / idempotent repeat.
- `201`: resource or first purchase confirmation created.
- `401`: missing/invalid authentication.
- `403`: authenticated but not authorized/owner.
- `409`: domain conflict (seat unavailable, expired reservation, invalid reissue state, idempotency conflict).
- `422`: validation/credentials error.
- `503`: intentionally unavailable event-search mechanism.

## Exportar as rotas reais do framework

Para conferir a documentação contra o roteamento efetivamente registrado no Laravel:

```bash
make routes
```

Isso executa `php artisan route:list --path=api --except-vendor` dentro do container e grava a saída em:

```text
docs/evidence/routes.txt
```

## Smoke test recomendado

1. Faça login como organizer e buyer.
2. Organizer cria evento e seats.
3. Buyer lista seats, cria reserva e confirma com `Idempotency-Key`.
4. Repita a confirmação e confira o mesmo Order/Ticket.
5. Buyer cancela a compra e confira o seat novamente disponível.
6. Organizer consulta relatório e audit trail do próprio evento.

O README contém comandos `curl` para esse fluxo.
