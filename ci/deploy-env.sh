#!/bin/bash
# Déploie l'image sur l'environnement DEV ou PREPROD du VPS, puis lance les tests de fumée.
set -euo pipefail
env="$1"; export IMAGE="$2"
target="${DEPLOY_SSH:-}"
file=infra/env/docker-compose.env.yml
if [ -n "$target" ]; then
  scp -o StrictHostKeyChecking=accept-new "$file" "$target:/opt/ttt-$env.yml"
  ssh "$target" "cd /opt && IMAGE='$IMAGE' docker compose -p ttt-$env --env-file /opt/ttt-$env.env -f ttt-$env.yml up -d --pull always --wait"
else
  # Sans cible distante : Jenkins et les environnements partagent le même hôte Docker.
  docker compose -p "ttt-$env" --env-file "${ENV_DIR:-/run/env}/ttt-$env.env" -f "$file" up -d --wait
fi
url_var="$(echo "$env" | tr a-z A-Z)_URL"
bash ci/smoke.sh "${!url_var:?$url_var manquante}"
