# ADR-002 - Confirmacao resiliente, notificacao assincrona e escala

**Status:** Accepted

## Contexto

Confirmar uma compra precisa produzir um unico resultado logico (Order + Tickets), mas o provider HTTP de notificacao e propositalmente instavel: pode ficar lento, retornar 5xx, ficar indisponivel ou entregar duplicado. A falha externa nao pode manter transacoes abertas nem bloquear novas compras. O desenho tambem deve funcionar com multiplas instancias e explicar evolucao 10x/100x.

## Decisao

A confirmacao trava a Reservation e os Seats associados, revalida ownership/TTL e grava `Order`, `Ticket(s)`, `OutboxEvent` e `NotificationDelivery` no mesmo commit MySQL. `orders.reservation_id` e unico; chamadas repetidas retornam a mesma compra. CPF do comprador e cifrado no Ticket e nao sai no evento de Outbox.

Um relay agendado publica Outbox pendente no Redis. Horizon processa `SendTicketNotification` fora da request. O client HTTP usa connect timeout de 1s, timeout de 2s, cinco tentativas e backoff 5/15/30/60s. A semantica assumida e at-least-once: `notification_id` estavel e enviado como `Idempotency-Key`; o provider deduplica retries ja aceitos. Apos esgotar tentativas, Delivery vira `failed`, permanece observavel e pode ser reprocessada explicitamente sem recriar Order/Ticket.

Pulse/Horizon e `/api/ops/metrics` fornecem observabilidade local. `confirmation_attempts` alimenta alerta automatico de failure rate. Auditoria append-only registra mudancas de negocio sem dados sensiveis.

## Alternativas

1. **HTTP sincrono dentro da confirmacao:** rejeitado; acopla latencia/disponibilidade externa ao checkout e estende transacao.
2. **Disparar queue job depois do commit sem Outbox:** simples, mas existe janela entre commit e enqueue em que a notificacao pode ser perdida silenciosamente.
3. **Exactly-once:** nao e prometido. Exigiria coordenacao end-to-end que HTTP/Redis/MySQL nao oferecem; at-least-once + idempotencia e a garantia realista.
4. **Kafka agora:** rejeitado por complexidade sem necessidade de replay/multiplos consumidores. Redis/Horizon resolve o escopo atual; SQS/Kafka sao opcoes futuras conforme semantica/escala.

## Consequencias e escala

Falha do provider nao reverte compra nem bloqueia novos checkouts. Operacao precisa acompanhar `failed` e reprocessar apos recuperacao. Uma queda apos aceite remoto e antes de persistir `sent` pode causar retry, por isso idempotencia downstream e obrigatoria.

Com **10x**, APIs stateless podem escalar horizontalmente: locks e constraints continuam no MySQL compartilhado; Horizon escala por filas e replicas de leitura atendem relatorios. Com **100x**, adicionar waiting room/virtual queue, CDN/cache de catalogo, isolamento por evento quente, read models/aggregados, busca secundaria reconstruivel e, se necessario, particionamento por `event_id`. Filas gerenciadas substituem Redis quando justificadas; Kafka entra apenas para log/replay e multiplos consumidores. OpenTelemetry permite exportar observabilidade para Datadog/Grafana.

Melhoria de produto recomendada: fila virtual com limite de assentos por comprador e janela curta de checkout. Isso reduz thundering herd e abuso sem enfraquecer a garantia final de unicidade no banco.
