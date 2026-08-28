#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64' .env; then
    php artisan key:generate --ansi
fi

php artisan migrate --graceful --ansi

exec "$@"
