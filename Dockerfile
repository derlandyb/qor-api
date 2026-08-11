FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000
# Runs php's built-in server directly (not `artisan serve`) so the request-handling
# process inherits the full container environment — `artisan serve` spawns a subprocess
# that only forwards an allowlist of vars and re-reads .env, ignoring docker-compose's
# injected DB_* values.
WORKDIR /var/www/html/public
CMD ["php", "-S", "0.0.0.0:8000", "/var/www/html/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
