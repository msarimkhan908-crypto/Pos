#!/bin/sh
set -e

# SQLite database lives at /data (Fly.io persistent volume)
DB_PATH="/data/database.sqlite"
if [ ! -f "$DB_PATH" ]; then
    touch "$DB_PATH"
fi
# www-data needs write access to the directory (for WAL/journal files) and the file
chown -R www-data:www-data /data

# Point Laravel at the volume database
export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_PATH"

# Storage symlink
php artisan storage:link --no-interaction 2>/dev/null || true

# Run migrations (safe to re-run)
php artisan migrate --force --no-interaction

# Warm up caches
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

# Hand off to supervisor
exec /usr/bin/supervisord -c /etc/supervisord.conf
