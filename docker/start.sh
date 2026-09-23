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
# Keep mail delivery outside the HTTP request. Failed messages are retried and
# retained in failed_jobs for inspection instead of blocking receipt rendering.
php artisan queue:work database --queue=mail --sleep=3 --tries=3 --backoff=30 --timeout=45 &
MAIL_WORKER_PID=$!
php -S 0.0.0.0:"${PORT:-8000}" -t public docker/router.php &
WEB_SERVER_PID=$!
trap 'kill "$MAIL_WORKER_PID" "$WEB_SERVER_PID" 2>/dev/null || true' EXIT TERM INT
wait "$WEB_SERVER_PID"
