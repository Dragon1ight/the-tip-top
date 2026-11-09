#!/bin/bash
# Fonctions partagées par les jobs : pile applicative jetable et image de test.
set -euo pipefail
export COMMIT="${COMMIT:-$(git rev-parse --short HEAD)}"
export PROJECT="ttt-ci-$(echo "${BUILD_TAG:-local}" | tr -cd 'a-z0-9-' | tail -c 30)"
export COMPOSE="docker compose -p $PROJECT -f docker-compose.yml -f docker-compose.ci.yml"
mkdir -p reports

test_image() { docker build -q -f ci/test.Dockerfile -t "ttt-test:$COMMIT" . >/dev/null; }

# Lance PHPUnit puis récupère le rapport même en cas d'échec.
phpunit() {
  local suite="$1"; shift
  local name="phpunit-$suite-$$"
  local rc=0
  docker run --name "$name" "$@" "ttt-test:$COMMIT" php vendor/bin/phpunit --testsuite "$suite" --log-junit /tmp/junit.xml || rc=$?
  docker cp "$name:/tmp/junit.xml" "reports/$suite.xml" 2>/dev/null || true
  docker rm -f "$name" >/dev/null
  return $rc
}

stack_up() {
  $COMPOSE up -d --build --wait --wait-timeout 180 web db redis
  export NET="${PROJECT}_default"
  local i=0
  until [ "$(docker run --rm --network "$NET" curlimages/curl:8.11.1 -s -m 5 -o /dev/null -w '%{http_code}' http://web:10000/sante)" = 200 ]; do
    i=$((i+1)); [ "$i" -gt 40 ] && { $COMPOSE logs web | tail -30; return 1; }; sleep 3
  done
  $COMPOSE exec -T web php -d memory_limit=-1 bin/console app:codes:generate 3000 --no-interaction >/dev/null
}

stack_down() { $COMPOSE down -v --remove-orphans >/dev/null 2>&1 || true; }

codes() { $COMPOSE exec -T db mariadb -uttt -pttt_local ttt -N -e "SELECT valeur FROM code_ticket WHERE participant_id IS NULL ORDER BY RAND() LIMIT $1" | tr -d '\r' | paste -sd, -; }

# Lance Playwright pour un projet (http ou chromium).
playwright_run() {
  local project="$1" list; list="$(codes 40)"
  docker build -q -f ci/e2e.Dockerfile -t "ttt-e2e:$COMMIT" . >/dev/null
  local name="e2e-$project-$$" rc=0
  docker run --name "$name" --network "$NET" --init -e BASE_URL=http://web:10000 -e CODES="$list" "ttt-e2e:$COMMIT" npx playwright test --project="$project" || rc=$?
  docker cp "$name:/e2e/reports/." "reports/e2e-$project" 2>/dev/null || true
  docker rm -f "$name" >/dev/null
  return $rc
}
