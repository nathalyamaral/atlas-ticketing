# Checklist final de entrega

## A. Startup do zero

Recomendado para a validação final:

```bash
make verify-delivery-fresh
```

Esse comando remove volumes locais e depois executa toda a validação.

Se quiser testar somente o requisito de startup:

```bash
docker compose down -v --remove-orphans || true
docker compose up -d --build
```

Legacy:

```bash
docker-compose down -v --remove-orphans || true
docker-compose up -d --build
```

Critérios:

- nenhuma criação manual de `api/.env`;
- nenhuma instalação local de PHP/Composer;
- migrations automáticas;
- baseline seed automático;
- API, MySQL, Redis, Horizon, Scheduler, Nginx e provider ativos.

## B. Confirmar Composer/vendor corretamente

A pasta `vendor` deve existir **dentro do container**, não precisa existir no host.

```bash
make doctor
```

ou:

```bash
docker-compose exec api composer --version
docker-compose exec api test -f vendor/autoload.php && echo OK
```

## C. PHPUnit

```bash
make test
```

Esperado: suíte verde e `docs/evidence/phpunit.txt` atualizado.

## D. Concorrência obrigatória

```bash
make load-test
```

Esperado:

- 50 VUs;
- 10 seats;
- 10 reservas vencedoras;
- 40 conflitos;
- 0 respostas inesperadas;
- 10 compras confirmadas;
- 10 seats vendidos;
- 0 reserva duplicada;
- 0 venda duplicada.

Arquivo: `docs/evidence/seat-contention.txt`.

## E. Provider instável

```bash
make provider-test
make resilience-test
```

Esperado: normal/error/down/duplicate/slow reproduzíveis, checkout independente da latência do provider e recuperação da Delivery após retorno.

Arquivos:

```text
docs/evidence/provider-modes.txt
docs/evidence/provider-resilience.txt
```

## F. Fase 5

```bash
make phase5-seed
make benchmark-report
make benchmark-search
```

Confirme que o seeder informa 10.000 eventos e 200.000 tickets e que os benchmarks geram:

```text
docs/evidence/report-benchmark.txt
docs/evidence/search-benchmark.txt
```

## G. Rotas

```bash
make routes
```

Compare `docs/evidence/routes.txt` com `README.md` e `docs/API.md`.

## H. Dashboards / observabilidade

Abra:

```text
http://localhost:8000/horizon
http://localhost:8000/pulse
```

Também valide `/api/ops/metrics` com token de organizer.

## I. Documentação obrigatória

Confirme no repositório:

```text
README.md
DECISIONS.md
docs/API.md
docs/ADR-002-resilience-scale.pdf
docs/REQUIREMENTS_MATRIX.md
docs/TESTING_AND_EVIDENCE.md
docs/PERFORMANCE.md
docs/DELIVERY.md
```

## J. Higiene do repositório

Não enviar:

```text
api/.env
api/vendor/
api/node_modules/
.idea/
logs runtime
dados/CPF reais
```

## K. GitHub

- repositório privado;
- convidar o revisor Atlas informado no enunciado;
- commit das evidências finais reais;
- enviar link pelo canal combinado.
