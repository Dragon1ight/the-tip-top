#!/bin/bash
# Job 3 : règles du jeu sur une vraie base MariaDB.
source ci/lib.sh
test_image
NET="ttt-int-$$"; DB="ttt-int-db-$$"
cleanup() { docker rm -f "$DB" >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap cleanup EXIT
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" -e MARIADB_DATABASE=ttt -e MARIADB_USER=ttt -e MARIADB_PASSWORD=ttt_local -e MARIADB_RANDOM_ROOT_PASSWORD=1 --health-cmd='healthcheck.sh --connect --innodb_initialized' --health-interval=2s mariadb:11 >/dev/null
until [ "$(docker inspect -f '{{.State.Health.Status}}' "$DB")" = healthy ]; do sleep 2; done
phpunit integration --network "$NET" -e "DATABASE_URL=mysql://ttt:ttt_local@$DB:3306/ttt"
