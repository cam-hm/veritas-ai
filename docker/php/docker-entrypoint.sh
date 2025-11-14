#!/bin/bash
set -e

# Create necessary storage subdirectories if they don't exist
# Laravel requires these directories to exist
mkdir -p /var/www/html/storage/framework/cache/data
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/testing
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/app/public

# Fix permissions for vendor and storage directories if they exist
# This should run as root to fix permissions
if [ -d "/var/www/html/vendor" ]; then
    chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true
fi

if [ -d "/var/www/html/storage" ]; then
    chown -R www-data:www-data /var/www/html/storage 2>/dev/null || true
fi

# PHP-FPM needs to run as root initially to bind to port 9000
# It will drop privileges to www-data based on the pool configuration
# Other commands (composer, artisan, etc.) should run as www-data
if [ "$1" = "php-fpm" ]; then
    # PHP-FPM should run as root - it handles privilege dropping internally
    # Use -F flag to run in foreground (required for Docker)
    exec php-fpm -F
else
    # For other commands, switch to www-data
    exec gosu www-data "$@"
fi

