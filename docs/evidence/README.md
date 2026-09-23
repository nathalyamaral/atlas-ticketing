# Evidências runtime

Os arquivos desta pasta devem ser gerados pela versão final executada na máquina de entrega. Não edite resultados manualmente.

## Um comando para tudo

```bash
make verify-delivery
```

Execução do zero, removendo volumes:

```bash
make verify-delivery-fresh
```

## Arquivos gerados

| Arquivo | Comando | Evidência |
|---|---|---|
| `stack-doctor.txt` | `make doctor` | containers, Composer/vendor, migrations, Horizon, Scheduler, health |
| `phpunit.txt` | `make test` | testes automatizados |
| `seat-contention.txt` | `make load-test` | k6 50×10 + validação SQL de reservas/vendas |
| `provider-modes.txt` | `make provider-test` | normal/error/down/duplicate/slow |
| `provider-resilience.txt` | `make resilience-test` | checkout não bloqueado + recuperação |
| `report-benchmark.txt` | `make benchmark-report` | benchmark relatório 200k |
| `search-benchmark.txt` | `make benchmark-search` | benchmark busca 10k |
| `routes.txt` | `make routes` | rotas reais registradas no Laravel |

## Nota sobre `seat-contention.txt`

Qualquer captura antiga deve ser substituída executando `make load-test` após a última alteração de infraestrutura/código. O script só atualiza o bloco de resultado no README depois que k6 e validações SQL passam.
