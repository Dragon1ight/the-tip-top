#!/bin/bash
# Job 2 : règles métier isolées.
source ci/lib.sh
test_image
phpunit unit
