#!/bin/bash
# Job 5 : secrets, dépendances vulnérables, analyse statique.
source ci/lib.sh
test_image

# Les outils lisent une copie du dépôt, ce qui évite tout partage de dossier avec le serveur Jenkins.
SRC="ttt-src-$$"
docker create --name "$SRC" -v /scan alpine:3.20 true >/dev/null
trap 'docker rm -fv "$SRC" >/dev/null 2>&1 || true' EXIT
tar --exclude=vendor --exclude=var --exclude=reports --exclude=node_modules -cf - . | docker cp - "$SRC:/scan"

echo "== Gitleaks (secrets)"
docker run --rm --volumes-from "$SRC" zricethezav/gitleaks:v8.21.2 detect --source /scan --config /scan/ci/gitleaks.toml --redact --no-banner

echo "== Composer audit (dépendances)"
docker run --rm "ttt-test:$COMMIT" composer audit --locked --no-dev

echo "== Trivy (système de fichiers)"
# Exclus : images de test jetables, et outils du workflow qui pilotent Docker par son socket (root assumé).
docker run --rm --volumes-from "$SRC" aquasec/trivy:0.57.1 fs --quiet --severity CRITICAL,HIGH --exit-code 1 --scanners vuln,misconfig --skip-dirs /scan/tests /scan --skip-files /scan/ci/test.Dockerfile,/scan/ci/e2e.Dockerfile,/scan/ci/k6.Dockerfile,/scan/infra/jenkins/Dockerfile,/scan/infra/backup/Dockerfile --misconfig-scanners dockerfile --ignorefile /scan/ci/trivyignore

if [ -n "${NVD_API_KEY:-}" ]; then
  echo "== OWASP Dependency-Check"
  docker run --rm --volumes-from "$SRC" -e NVD_API_KEY owasp/dependency-check:12.1.0 --scan /scan/composer.lock --project the-tip-top --failOnCVSS 7 --nvdApiKey "$NVD_API_KEY" --noupdate=false
else
  echo "OWASP Dependency-Check ignoré : NVD_API_KEY absente (composer audit a couvert les dépendances)."
fi

if [ -n "${SONAR_URL:-}" ] && [ -n "${SONAR_TOKEN:-}" ]; then
  echo "== SonarQube (analyse statique et Quality Gate)"
  docker run --rm --network "${SONAR_NETWORK:-bridge}" --volumes-from "$SRC" -w /scan -e SONAR_HOST_URL="$SONAR_URL" -e SONAR_TOKEN sonarsource/sonar-scanner-cli:11.1 -Dsonar.projectKey=the-tip-top -Dsonar.sources=src,templates -Dsonar.qualitygate.wait=true
else
  echo "SonarQube ignoré : SONAR_URL ou SONAR_TOKEN absents."
fi
