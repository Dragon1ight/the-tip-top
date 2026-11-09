#!/bin/bash
# Planification : sauvegarde toutes les 6 h, test de restauration le 1er du mois.
set -euo pipefail
cat > /etc/crontabs/root <<'EOF'
0 */6 * * * /usr/local/bin/backup.sh >> /proc/1/fd/1 2>&1
30 4 1 * * /usr/local/bin/restore-test.sh >> /proc/1/fd/1 2>&1
EOF
exec crond -f -l 8
