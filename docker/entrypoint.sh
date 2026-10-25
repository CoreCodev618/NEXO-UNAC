#!/bin/sh
# Entrypoint del contenedor del PilotoV1.
#
# CodeIgniter usa claves con puntos (`database.default.hostname`, `app.baseURL`)
# que muchos PaaS no permiten como variables de entorno. Este script traduce
# variables simples (DB_HOST, APP_BASE_URL, ...) a un `.env` generado en tiempo
# de arranque. No imprime contraseñas ni claves.
set -eu

APP_DIR=/var/www/html
cd "$APP_DIR"

# Render (y otros PaaS) inyectan PORT. Apache se configura antes de arrancar
# para escuchar en ese puerto; en local el default es 80.
port="${PORT:-80}"
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

base_url="${APP_BASE_URL:-${RENDER_EXTERNAL_URL:-http://localhost:8080}}"
case "$base_url" in
    */) ;;
    *) base_url="$base_url/" ;;
esac

db_host="${DB_HOST:-127.0.0.1}"
db_port="${DB_PORT:-3306}"
db_name="${DB_NAME:-nexo_unac_piloto}"
db_user="${DB_USER:-root}"
db_pass="${DB_PASS:-}"

escape_value() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'
}

write_env() {
    key="$1"
    value="${2:-}"
    printf '%s = "%s"\n' "$key" "$(escape_value "$value")"
}

tmp_env="$(mktemp "${APP_DIR}/.env.XXXXXX")"
{
    echo "# Generado por docker/entrypoint.sh; no editar dentro del contenedor."
    write_env "CI_ENVIRONMENT" "production"
    write_env "app.baseURL" "$base_url"
    # No activar app.forceGlobalSecureRequests detrás del proxy de Render:
    # la petición llega por HTTP y CI4 no confía en X-Forwarded-Proto por
    # defecto, lo que produciría un bucle de redirecciones. Render ya termina
    # TLS en el borde y sirve el enlace https.
    write_env "database.default.hostname" "$db_host"
    write_env "database.default.port" "$db_port"
    write_env "database.default.database" "$db_name"
    write_env "database.default.username" "$db_user"
    write_env "database.default.password" "$db_pass"
    write_env "database.default.DBDriver" "MySQLi"
    if [ "${DB_SSL:-false}" = "true" ]; then
        write_env "database.default.encrypt" "true"
    fi
    write_env "NEXO_PREVIEW" "false"
} > "$tmp_env"
mv "$tmp_env" "$APP_DIR/.env"
chown www-data:www-data "$APP_DIR/.env"
chmod 640 "$APP_DIR/.env"

if [ "${NEXO_AUTO_MIGRATE:-false}" = "true" ]; then
    attempt=1
    retries="${DB_WAIT_RETRIES:-30}"
    until php spark migrate --all; do
        if [ "$attempt" -ge "$retries" ]; then
            echo "ERROR: la base de datos no respondió; migraciones canceladas." >&2
            exit 1
        fi
        echo "Esperando base de datos (intento ${attempt}/${retries})..." >&2
        attempt=$((attempt + 1))
        sleep "${DB_WAIT_SECONDS:-2}"
    done
fi

if [ "${NEXO_AUTO_SEED:-false}" = "true" ]; then
    php spark db:seed DemoSeeder
fi

# Los comandos spark escriben logs/caché como root; Apache corre como www-data.
chown -R www-data:www-data "$APP_DIR/writable"

exec "$@"
