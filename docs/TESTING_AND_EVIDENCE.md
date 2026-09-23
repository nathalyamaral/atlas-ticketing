# Testes, carga, resiliência e evidências

Este documento separa duas coisas que não devem ser misturadas:

1. **startup da aplicação** — `docker compose up -d --build` deve apenas deixar toda a stack pronta;
2. **validação da entrega** — testes, k6, cenários de falha e seeds/benchmarks pesados são executados de forma explícita e reproduzível.

Rodar k6 e criar 200 mil tickets automaticamente a cada startup deixaria o Compose lento, mutaria o banco e tornaria a experiência do avaliador pior. Por isso a validação completa fica em um comando próprio.

## Validação completa em um comando

```bash
make verify-delivery
```

A partir de um banco totalmente limpo:

```bash
make verify-delivery-fresh
```

O segundo comando remove os volumes MySQL/Redis antes da execução.

## Etapas

### 1. Stack doctor

```bash
make doctor
```

Valida:

- serviços do Compose;
- Composer dentro da imagem API;
- `vendor/autoload.php` dentro do container;
- Laravel/Artisan;
- migrations;
- Horizon;
- Scheduler;
- `/up` da API;
- `/health` do provider.

Evidência:

```text
docs/evidence/stack-doctor.txt
```

### 2. PHPUnit

```bash
make test
```

Cria/garante `atlas_ticketing_test` no MySQL e executa a suíte Laravel usando MySQL, não SQLite.

Evidência:

```text
docs/evidence/phpunit.txt
```

### 3. k6 — 50 compradores / 10 assentos

```bash
make load-test
```

Fluxo:

1. recria o cenário `Flash Sale Load Test`;
2. cria 10 seats;
3. cria 50 buyers;
4. executa 50 VUs concorrentes pelo Nginx/PHP-FPM;
5. cinco compradores disputam cada seat;
6. exige 10 `201`, 40 `409`, 0 respostas inesperadas;
7. valida no MySQL que não há hold duplicado;
8. confirma as 10 reservas vencedoras;
9. valida 10 Orders, 10 Seats `sold` e 0 venda duplicada.

Evidência:

```text
docs/evidence/seat-contention.txt
```

### 4. Modos do provider

```bash
make provider-test
```

Exercita:

- normal;
- retry idempotente;
- HTTP 500;
- HTTP 503/down;
- duplicate;
- slow.

Evidência:

```text
docs/evidence/provider-modes.txt
```

### 5. Falha prolongada / recuperação

```bash
make resilience-test
```

Prova que:

- checkout não espera um provider que dorme 5s;
- Order permanece confirmada;
- envio assíncrono observa falha;
- provider pode ficar down;
- após voltar, a mesma Delivery chega a `sent` sem gerar novo Ticket.

Evidência:

```text
docs/evidence/provider-resilience.txt
```

### 6. Fase 5 — dataset

```bash
make phase5-seed
```

Cria 10 mil eventos pesquisáveis e exatamente 200 mil Tickets no evento `Report Benchmark 200k`.

### 7. Benchmark relatório

```bash
make benchmark-report
```

Evidência:

```text
docs/evidence/report-benchmark.txt
```

### 8. Benchmark busca

```bash
make benchmark-search
```

Evidência:

```text
docs/evidence/search-benchmark.txt
```

### 9. Rotas reais do Laravel

```bash
make routes
```

Evidência:

```text
docs/evidence/routes.txt
```

## Critério para commit final

Depois de qualquer mudança em Docker, concorrência, checkout, provider, migrations ou índices, rode novamente:

```bash
make verify-delivery
```

Não altere manualmente os arquivos em `docs/evidence/` para fabricar resultados. Eles devem refletir a execução real da versão commitada.
