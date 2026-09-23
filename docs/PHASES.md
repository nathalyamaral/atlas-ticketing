# Checklist das fases

## Fase 1

- Docker Compose sobe Nginx, API/PHP-FPM, MySQL, Redis, Horizon, Scheduler e provider separado.
- `docker compose up -d --build` não exige `.env`, migration ou seed manual; o bootstrap acontece no container da API.
- Auth Sanctum e roles buyer/organizer.
- Evento, mapa de assentos, listagem de disponibilidade e ownership.
- Documentação completa de endpoints em `docs/API.md`.

## Fase 2

- Lock pessimista MySQL, IDs ordenados e reserva all-or-nothing.
- Expiração correta mesmo antes do Scheduler limpar o estado.
- k6: 50 requests / 10 seats; thresholds 10 sucessos / 40 conflitos / 0 inesperadas.
- Validação SQL adicional exige zero assentos duplicados.
- Evidência: `docs/evidence/seat-contention.txt` regenerada por `make load-test`.

## Fase 3

- Confirmação idempotente, Order/Ticket e CPF cifrado.
- QR payload opaco, sem PII.
- Transactional Outbox + NotificationDelivery no mesmo commit da compra.
- HTTP real para segundo container, assíncrono via Redis/Horizon.
- Timeout, retries/backoff, estado failed e reprocessamento explícito.
- Testes automatizados cobrindo fluxos principais das Fases 1-3.
- Cancelamento idempotente libera seat.

## Fase 4

- Provider alternável entre normal/slow/error/down/duplicate e pode ser fisicamente parado.
- Checkout não chama provider e não trava quando ele está indisponível.
- Horizon, Pulse, delivery state e `/api/ops/metrics` tornam falhas observáveis.
- `make resilience-test` reproduz queda e recuperação.
- ADR-002 em Markdown + PDF de 2 páginas contém contexto, decisão, alternativas, trade-offs, múltiplas instâncias, 10x/100x e melhoria de regra de produto.

## Fase 5

- Relatório owner-only, seeder de 200 mil Tickets e benchmark reproduzível.
- Busca com 10 mil eventos, índices, abstração e 503 isolado quando o mecanismo é desabilitado.
- Alerta automático de taxa de falha de confirmação.
- Audit trail append-only para confirmação, cancelamento e reemissão; reemissão rotaciona código/versão sem duplicar Ticket.
- Pulse + Horizon + `/api/ops/metrics`.
- Estratégia 100x documentada.

## Validação final

```bash
make verify-delivery
```

Os resultados executados ficam em `docs/evidence/` e devem ser revisados/commitados antes da entrega.
