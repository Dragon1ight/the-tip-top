#!/bin/bash
# Tests de fumée sur une couleur, sur l'adresse publique, ou sur une URL donnée.
set -euo pipefail
source ci/render.env
target="$1"
case "$target" in
  blue|green) var="RENDER_URL_$target"; base="${!var}" ;;
  public) base="${PUBLIC_URL:?PUBLIC_URL manquante}" ;;
  *) base="$target" ;;
esac
# L'offre gratuite peut mettre environ une minute à réveiller le service.
for _ in $(seq 1 24); do
  [ "$(curl -s -m 30 -o /dev/null -w '%{http_code}' "$base/sante")" = 200 ] && break
  sleep 5
done
fail=0
for path in /sante / /les-lots /reglement /connexion /robots.txt; do
  code=$(curl -s -m 30 -o /dev/null -w '%{http_code}' "$base$path")
  echo "$path $code"
  [ "$code" = 200 ] || fail=1
done
exit $fail
