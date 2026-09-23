# ADR-001 - Concorrencia de assentos

**Status:** Accepted

## Contexto

Em uma flash sale, dezenas de requests podem tentar reservar o mesmo assento no mesmo instante. O requisito absoluto e impedir que um Seat tenha dois donos ativos, inclusive com varias instancias da API.

## Decisao

MySQL e a autoridade. `ReserveSeats` ordena IDs e executa `SELECT ... FOR UPDATE` apenas nas linhas de assentos solicitadas. Na mesma transacao valida disponibilidade, cria `reservations`/`reservation_items` e atualiza Seats para `reserved`. A operacao multi-assento e all-or-nothing. `DB::transaction(..., 3)` permite retry de deadlock.

Reserva expirada e considerada reutilizavel pela propria transacao; o Scheduler e housekeeping. Na confirmacao, Reservation e Seats sao travados/revalidados e cada Seat precisa continuar com a mesma `reservation_id` e `reserved_until` valido.

## Alternativas consideradas

- Redis distributed lock: rapido, mas adicionaria outra autoridade e problemas de lease/failover; rejeitado como garantia primaria.
- Optimistic locking/version: viavel, mas exige retry explicito sob grande contencao; pessimistic row lock ficou mais simples para a regra atual.
- Fila unica por evento: reduz contencao, mas serializa demais e aumenta latencia/complexidade; pode voltar em eventos extremamente quentes.

## Consequencias

Locks ficam curtos e restritos aos seats disputados. Nao existe HTTP externo na transacao. Em hot seats havera espera/409, o que e intencional. O teste k6 (`make load-test`) comprova 50 requests contra 10 seats com validacao final no banco.
