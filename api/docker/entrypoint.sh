#!/usr/bin/env sh
set -eu

# Dependencies are installed during image build. This fallback keeps the image
# robust if /var/www/html is ever replaced by a bind mount without vendor/.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "==> vendor/autoload.php not found; installing Composer dependencies..."
    composer install \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader
else
    echo "==> Composer dependencies ready."
fi

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ "${RUN_BOOTSTRAP:-false}" = "true" ]; then
    echo "==> Running database migrations..."
    php artisan migrate --force

    echo "==> Running baseline seeders..."
    php artisan db:seed --force
fi

exec "$@"
