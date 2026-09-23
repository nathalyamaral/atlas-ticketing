FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install \
        pdo_mysql \
        intl \
        pcntl \
        zip \
        curl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html
COPY . .

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN sed -ri \
    -e 's!^pm = .*!pm = dynamic!' \
    -e 's!^pm\.max_children = .*!pm.max_children = 20!' \
    -e 's!^pm\.start_servers = .*!pm.start_servers = 4!' \
    -e 's!^pm\.min_spare_servers = .*!pm.min_spare_servers = 2!' \
    -e 's!^pm\.max_spare_servers = .*!pm.max_spare_servers = 8!' \
    /usr/local/etc/php-fpm.d/www.conf

COPY docker/entrypoint.sh /usr/local/bin/atlas-entrypoint
RUN chmod +x /usr/local/bin/atlas-entrypoint

EXPOSE 9000

ENTRYPOINT ["atlas-entrypoint"]
CMD ["php-fpm"]
