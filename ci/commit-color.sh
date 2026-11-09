#!/bin/bash
# Note la couleur active dans le dépôt ; le marqueur [ci skip] et l'auteur évitent toute boucle.
set -euo pipefail
color="$1"
echo "$color" > active_color.txt
git config user.name jenkins-bot
git config user.email jenkins-bot@furiousducks.fr
git add active_color.txt
git diff --cached --quiet && { echo "Couleur déjà à jour"; exit 0; }
git commit -q -m "Couleur active : $color [ci skip]"
repo="$(git config remote.origin.url | sed -E 's#https://([^/]*@)?##')"
git push -q "https://$GIT_USER:$GIT_TOKEN@$repo" HEAD:prod
