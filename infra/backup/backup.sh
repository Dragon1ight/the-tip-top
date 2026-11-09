#!/bin/bash
# Sauvegarde chiffrée et incrémentale (Restic) : base, dépôts, volumes des outils.
set -euo pipefail
export RESTIC_REPOSITORY RESTIC_PASSWORD
work=/tmp/backup; rm -rf "$work"; mkdir -p "$work"
restic snapshots >/dev/null 2>&1 || restic init

echo "$(date -Is) dump de la base"
MYSQL_PWD="$DB_PASSWORD" mariadb-dump --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" ${DB_DUMP_SSL:---ssl} --single-transaction --routines --ignore-table="$DB_NAME.sessions" "$DB_NAME" | gzip > "$work/$DB_NAME.sql.gz"
[ "$(zcat "$work/$DB_NAME.sql.gz" | wc -c)" -gt 1000 ] || { echo "Dump vide : abandon"; exit 1; }

echo "$(date -Is) miroir des dépôts Git"
if [ -d /data/mirror/the-tip-top.git ]; then git -C /data/mirror/the-tip-top.git remote update --prune; else git clone --mirror "$GIT_MIRROR_URL" /data/mirror/the-tip-top.git; fi

echo "$(date -Is) envoi chiffré"
# Nom d'hôte fixe : Restic retrouve le snapshot parent même si le conteneur est recréé.
restic backup --host workflow-backup --tag auto --exclude 'workspace' --exclude '*/caches/*' "$work" /data/mirror /data/jenkins /data/grafana /data/registry
restic forget --tag auto --keep-within 30d --keep-monthly 6 --prune
restic check --read-data-subset=1%

curl -fsS --data-binary "backup_last_success_timestamp $(date +%s)
" "${PUSHGATEWAY_URL:-http://pushgateway:9091}/metrics/job/backup" || true
rm -rf "$work"
echo "$(date -Is) sauvegarde terminée"
