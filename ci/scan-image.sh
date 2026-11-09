#!/bin/bash
# Bloque le pipeline si l'image construite contient une vulnérabilité critique corrigeable.
set -euo pipefail
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock aquasec/trivy:0.57.1 image --quiet --severity CRITICAL --ignore-unfixed --exit-code 1 "$1"
