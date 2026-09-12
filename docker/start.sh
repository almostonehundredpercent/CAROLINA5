#!/usr/bin/env sh
set -eu

php artisan migrate --force

if [ "${RUN_INITIAL_SEED:-false}" = "true" ]; then
    php artisan db:seed --class=HostedSetupSeeder --force
fi

php artisan config:cache
exec php -S 0.0.0.0:"${PORT:-8000}" -t public docker/router.php
