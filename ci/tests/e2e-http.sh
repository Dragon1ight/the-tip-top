#!/bin/bash
# Job 6 : parcours complets au niveau HTTP.
source ci/lib.sh
trap stack_down EXIT
stack_up
playwright_run http
