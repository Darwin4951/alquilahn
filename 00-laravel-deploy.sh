#!/bin/sh
set -eu
cd /var/www/html
: "${APP_KEY:?Define una APP_KEY persistente en Render antes de desplegar}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-}}"
: "${APP_URL:?Define APP_URL con https}"
if [ -z "${GOOGLE_REDIRECT_URI:-}" ]; then
    export GOOGLE_REDIRECT_URI="${APP_URL%/}/auth/google/callback"
fi
: "${DB_HOST:?Define el host MySQL de Aiven}"
: "${DB_PASSWORD:?Define la contraseña MySQL de Aiven}"
: "${CLOUDINARY_URL:?Define CLOUDINARY_URL}"
test "${DB_CONNECTION:-}" = mysql || { echo 'Producción requiere DB_CONNECTION=mysql'; exit 1; }
case "$APP_URL" in https://*) ;; *) echo 'APP_URL debe comenzar con https://'; exit 1 ;; esac
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache
if [ -n "${AIVEN_CA_CERT_BASE64:-}" ]; then
    mkdir -p /var/www/certs
    printf '%s' "$AIVEN_CA_CERT_BASE64" | base64 -d > /var/www/certs/aiven-ca.pem
    export MYSQL_ATTR_SSL_CA=/var/www/certs/aiven-ca.pem
fi
: "${MYSQL_ATTR_SSL_CA:?Proporciona el certificado CA de Aiven}"
test -r "$MYSQL_ATTR_SSL_CA" || { echo 'No se puede leer el certificado CA'; exit 1; }
php artisan config:cache
# Free instances have no pre-deploy command: migrate on startup, before accepting requests.
php artisan migrate --force --no-interaction
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
port="${PORT:-10000}"
case "$port" in ''|*[!0-9]*) echo 'PORT debe ser numérico'; exit 1 ;; esac
sed -i "s/^Listen 80$/Listen $port/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$port>/" /etc/apache2/sites-available/000-default.conf
exec apache2-foreground
