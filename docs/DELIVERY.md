# Entrega GitHub

## 1. Repositório

1. Crie um repositório **privado** na sua conta GitHub.
2. Extraia/commite o conteúdo da raiz `atlas-ticketing/`; não commite o ZIP.
3. Convide `atlas-recrutamento-backend@atlastechnol.com` / usuário `atlas-recrutamento-backend`, conforme o desafio.
4. Envie o link do repositório pelo canal combinado.

## 2. O que deve estar commitado

- código Laravel;
- migrations e seeders;
- testes automatizados;
- `docker-compose.yml`;
- Dockerfiles e configs Nginx;
- notification-provider;
- scripts k6/resiliência;
- `README.md`;
- `DECISIONS.md`;
- `docs/API.md`;
- ADR/RFC PDF;
- matriz de requisitos;
- evidências finais geradas na máquina de entrega.

Não commitar:

- `api/.env`;
- `api/vendor/`;
- `api/node_modules/`;
- logs runtime;
- `.idea/`;
- CPF/dados reais.

## 3. Startup do avaliador

Comando principal:

```bash
docker compose up -d --build
```

Legacy:

```bash
docker-compose up -d --build
```

Esse comando deve subir toda a stack sem passos manuais adicionais. A API instala dependências Composer na imagem, executa migrations + baseline seeds e inicia PHP-FPM.

Após startup:

```text
API      http://localhost:8000
Health   http://localhost:8000/up
Horizon  http://localhost:8000/horizon
Pulse    http://localhost:8000/pulse
Provider http://localhost:8081/health
```

Usuários baseline:

```text
organizer@atlas.test / Password123!
buyer@atlas.test     / Password123!
```

## 4. Composer / vendor

A ausência de `api/vendor` no host é esperada. As dependências estão dentro da imagem Docker.

```bash
make doctor
```

prova Composer + `vendor/autoload.php` no container.

Opcional para IDE:

```bash
make composer-host
```

## 5. Verificação final obrigatória antes do push

Para uma execução completa, reproduzível e limpa:

```bash
make verify-delivery-fresh
```

Ou, preservando os volumes atuais:

```bash
make verify-delivery
```

A verificação executa PHPUnit, k6, validações SQL de concorrência/venda duplicada, modos do provider, outage/recuperação, seed de 10k eventos + 200k tickets, benchmarks e export das rotas.

Revise:

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

## 6. Git

Exemplo:

```bash
git init
git add .
git status
git commit -m "feat: complete flash-sale ticketing challenge"
git branch -M main
git remote add origin <REPOSITORIO_PRIVADO>
git push -u origin main
```

Confirme no `git status` que `vendor/`, `.env`, logs e arquivos de IDE não foram adicionados.
