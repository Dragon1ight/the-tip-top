#!/bin/bash
# Déploie un commit sur une couleur Render et attend qu'il soit en ligne.
set -euo pipefail
source ci/render.env
color="$1"; commit="$2"
var="RENDER_SERVICE_$color"; service="${!var}"
api="https://api.render.com/v1/services/$service/deploys"
id=$(curl -fsS -X POST "$api" -H "Authorization: Bearer $RENDER_API_KEY" -H 'Content-Type: application/json' -d "{\"commitId\":\"$commit\"}" | sed -E 's/.*"id":"(dep-[^"]+)".*/\1/')
echo "Déploiement $id sur $color"
for _ in $(seq 1 90); do
  status=$(curl -fsS "$api/$id" -H "Authorization: Bearer $RENDER_API_KEY" | sed -E 's/.*"status":"([a-z_]+)".*/\1/')
  case "$status" in
    live) echo "$color en ligne"; exit 0 ;;
    *failed|canceled|deactivated) echo "Échec : $status"; exit 1 ;;
  esac
  sleep 10
done
echo "Délai dépassé"; exit 1
