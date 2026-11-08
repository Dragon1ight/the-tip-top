#!/bin/sh
# Démarre Apache sur le port fourni par l'hébergeur ; prépare la base si elle est configurée.
set -u
PORT="${PORT:-10000}"
export PORT
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
cd /var/www/html
if [ -n "${DATABASE_URL:-}" ]; then
  i=0
  until php -d memory_limit=256M bin/console app:migrate --no-interaction || [ "$i" -ge 5 ]; do i=$((i+1)); sleep 3; done
  if [ -n "${CODES_AUTOGEN:-}" ]; then
    (php -d memory_limit=-1 bin/console app:codes:generate "$CODES_AUTOGEN" --no-interaction >/proc/1/fd/1 2>&1 &)
  fi
fi
chown -R www-data:www-data var
exec apache2-foreground
