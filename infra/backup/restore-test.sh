#!/bin/bash
# Restaure la dernière sauvegarde dans une base jetable et vérifie son contenu.
set -euo pipefail
export RESTIC_REPOSITORY RESTIC_PASSWORD
work=/tmp/restore; rm -rf "$work"; mkdir -p "$work"
restic restore latest --tag auto --target "$work" --include "/tmp/backup/$DB_NAME.sql.gz"
dump="$work/tmp/backup/$DB_NAME.sql.gz"
gzip -t "$dump"

host="${RESTORE_DB_HOST:-restore-db}"
name="restore-db-$$"
if [ -z "${RESTORE_DB_HOST:-}" ]; then
  apk add --no-cache docker-cli >/dev/null
  docker run -d --name "$name" --network workflow_ci --network-alias restore-db -e MARIADB_DATABASE=restore -e MARIADB_ROOT_PASSWORD=restore mariadb:11 >/dev/null
  trap 'docker rm -f "$name" >/dev/null 2>&1 || true' EXIT
fi
for _ in $(seq 1 40); do mariadb -h "$host" -uroot -prestore --skip-ssl -e 'SELECT 1' >/dev/null 2>&1 && break; sleep 3; done
zcat "$dump" | mariadb -h "$host" -uroot -prestore --skip-ssl restore
codes=$(mariadb -h "$host" -uroot -prestore --skip-ssl -N restore -e 'SELECT COUNT(*) FROM code_ticket')
lots=$(mariadb -h "$host" -uroot -prestore --skip-ssl -N restore -e 'SELECT COUNT(*) FROM lot')
echo "Restauration : $codes codes, $lots lots"
[ "$codes" -gt 0 ] && [ "$lots" -gt 0 ] || { echo "Restauration invalide"; exit 1; }
echo "Test de restauration réussi"
