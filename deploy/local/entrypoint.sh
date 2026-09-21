#!/bin/sh
set -eu
: "${APP_KEY:?Missing local APP_KEY}"
: "${DB_PASSWORD:?Missing local database password}"
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage/framework storage/logs bootstrap/cache
chown www-data:www-data storage/app storage/app/public
# Local database only; configuration fixes DB_HOST to the private Compose service.
php artisan migrate --force --no-interaction
exec docker-php-entrypoint "$@"
