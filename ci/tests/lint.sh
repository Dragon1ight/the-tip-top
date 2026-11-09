#!/bin/bash
# Job 1 : syntaxe PHP de tout le code.
source ci/lib.sh
test_image
docker run --rm "ttt-test:$COMMIT" sh -c 'find src tests public bin -name "*.php" -print0 | xargs -0 -n1 -P4 php -l | grep -v "No syntax errors" || true; find src tests public -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'
echo "Lint PHP : OK"
