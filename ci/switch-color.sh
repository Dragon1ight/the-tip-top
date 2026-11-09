#!/bin/bash
# Bascule immédiate : Traefik relit ce fichier et envoie le trafic vers la couleur demandée.
set -euo pipefail
source ci/render.env
color="$1"
var="RENDER_URL_$color"; url="${!var}"
dir="${TRAEFIK_DYNAMIC_DIR:-/traefik-dynamic}"
host="${PUBLIC_HOST:?PUBLIC_HOST manquante}"
tmp="$dir/.site.yml.$$"
cat > "$tmp" <<EOF
# Fichier généré par ci/switch-color.sh : ne pas modifier à la main.
http:
  routers:
    site:
      rule: "Host(\`$host\`)"
      entryPoints: [websecure]
      service: site-$color
      tls:
        certResolver: letsencrypt
  services:
    site-$color:
      loadBalancer:
        passHostHeader: false
        servers:
          - url: "$url"
EOF
mv "$tmp" "$dir/site.yml"
# Prometheus sonde l'adresse publique dès que la bascule a été mise en service.
echo "[{\"targets\":[\"https://$host/sante\"]}]" > "$dir/.site-target.tmp"
mv "$dir/.site-target.tmp" "$dir/site-target.json"
echo "Trafic public dirigé vers $color"
