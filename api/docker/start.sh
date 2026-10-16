#!/bin/sh
# Runs migrations, then the HTTP server and a queue worker in one container.
# Review sync, summaries and demo pruning are triggered hourly through POST /api/cron/run (see README).
set -e

mkdir -p storage/framework/cache storage/framework/views storage/logs

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:enable-rls

(while true; do php artisan queue:work --tries=3 --sleep=3 --max-time=3600 || true; sleep 2; done) &

exec frankenphp php-server --root public --listen ":${PORT:-8080}"
