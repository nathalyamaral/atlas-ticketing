# Docker / Composer / startup

## Objetivo

A máquina avaliadora precisa apenas de Docker + Docker Compose.

```bash
docker compose up -d --build
```

ou:

```bash
docker-compose up -d --build
```

## Por que não aparece `api/vendor` no host?

O serviço `api` usa uma imagem imutável. Durante `docker build`, o Dockerfile executa:

```text
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
composer install --no-interaction --prefer-dist --optimize-autoloader
```

Logo, `/var/www/html/vendor` fica **dentro da imagem/container**, e não no diretório `api/` do host. Esse é o comportamento esperado e evita exigir Composer local.

Confira:

```bash
docker-compose exec api composer --version
docker-compose exec api ls -ld vendor
docker-compose exec api test -f vendor/autoload.php && echo OK
```

O entrypoint possui fallback: se `vendor/autoload.php` não existir dentro do container, ele roda `composer install` antes de migrations/startup.

Para criar `api/vendor` no host somente para IDE:

```bash
make composer-host
```

Nunca commite `vendor/`.

## Bootstrap

Somente o serviço `api` recebe:

```text
RUN_BOOTSTRAP=true
```

O entrypoint então executa:

```text
php artisan migrate --force
php artisan db:seed --force
```

Horizon e Scheduler reutilizam a mesma imagem, mas não repetem o bootstrap do banco.

## Rede interna

A API usa:

```text
DB_HOST=mysql
REDIS_HOST=redis
NOTIFICATION_PROVIDER_URL=http://notification-provider:8080
```

Os nomes acima são nomes de serviços do Compose. `localhost` só é usado pelo usuário no host para acessar portas publicadas.
