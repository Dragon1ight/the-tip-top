#!/bin/bash
# Job 4 : API sur l'application démarrée (signature HMAC, anti-rejeu, limite de débit).
source ci/lib.sh
test_image
trap stack_down EXIT
stack_up
phpunit api --network "$NET" -e API_BASE=http://web:10000 -e API_HMAC_SECRET=local-dev-only
