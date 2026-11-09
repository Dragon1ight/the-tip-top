#!/bin/bash
# Après le déploiement DEV : test de charge k6 et contrôle SEO Lighthouse.
source ci/lib.sh
trap stack_down EXIT
stack_up
docker build -q -f ci/k6.Dockerfile -t "ttt-k6:$COMMIT" . >/dev/null
docker run --rm --network "$NET" -e BASE_URL=http://web:10000 -e DURATION="${K6_DURATION:-1m}" "ttt-k6:$COMMIT" run --quiet /tests/saisie.js

docker build -q -f ci/e2e.Dockerfile -t "ttt-e2e:$COMMIT" . >/dev/null
name="lh-$$"; rc=0
docker run --name "$name" --network "$NET" --init "ttt-e2e:$COMMIT" node seo-check.js || rc=$?
docker cp "$name:/e2e/reports/lighthouse.json" reports/lighthouse.json 2>/dev/null || true
docker rm -f "$name" >/dev/null
exit $rc
