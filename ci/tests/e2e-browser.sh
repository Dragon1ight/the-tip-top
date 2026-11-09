#!/bin/bash
# Job 7 : parcours critiques dans un vrai navigateur Chromium.
source ci/lib.sh
trap stack_down EXIT
stack_up
playwright_run chromium
