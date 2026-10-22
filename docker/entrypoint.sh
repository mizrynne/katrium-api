#!/bin/sh
set -e
cd /var/www/html

php artisan config:cache
php artisan route:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force
fi

if [ "${RUN_SEEDER:-false}" = "true" ]; then
  php artisan db:seed --class=RolesAndPermissionsSeeder --force
fi

exec "$@"