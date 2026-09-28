#!/bin/sh
set -e

# Render tells the container which port to listen on.
PORT="${PORT:-10000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Render's secret files are readable only by root, but Apache serves requests as
# www-data. Copy the MySQL CA to a world-readable path before config is cached.
if [ -n "$MYSQL_ATTR_SSL_CA" ] && [ -f "$MYSQL_ATTR_SSL_CA" ]; then
    cp "$MYSQL_ATTR_SSL_CA" /etc/ssl/certs/mysql-ca.pem
    chmod 644 /etc/ssl/certs/mysql-ca.pem
    export MYSQL_ATTR_SSL_CA=/etc/ssl/certs/mysql-ca.pem
fi

# Cache config at runtime, once Render's environment variables are present.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# The free plan has no pre-deploy command, so migrate on boot.
php artisan migrate --force

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
