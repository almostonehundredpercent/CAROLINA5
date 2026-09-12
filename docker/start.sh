#!/usr/bin/env sh
set -eu

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_INITIAL_SEED:-false}" = "true" ]; then
    php artisan db:seed --class=HostedSetupSeeder --force
fi

php artisan config:cache
php artisan storage:link --relative --force >/dev/null 2>&1 || true
exec php -S 0.0.0.0:"${PORT:-8000}" -t public docker/router.php
