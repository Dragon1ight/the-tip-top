#!/bin/bash
# Indicateurs DORA : compteurs conservés côté Jenkins, puis publiés sur le Pushgateway.
set -euo pipefail
event="$1"
state="${DORA_STATE:-/var/jenkins_home/dora.env}"
gateway="${PUSHGATEWAY_URL:-http://pushgateway:9091}"
deployments=0; rollbacks=0; last_deploy=0; mttr_total=0
[ -f "$state" ] && source "$state"
now=$(date +%s)
lead=""
case "$event" in
  deploy)
    deployments=$((deployments + 1))
    last_deploy=$now
    # Le commit livré, et non le commit [ci skip] créé par la bascule.
    lead=$((now - $(git log -1 --format=%ct "${GIT_COMMIT:-HEAD}")))
    ;;
  rollback)
    rollbacks=$((rollbacks + 1))
    # MTTR : temps écoulé entre la mise en production fautive et le retour arrière.
    [ "$last_deploy" -gt 0 ] && mttr_total=$((mttr_total + now - last_deploy))
    ;;
  *) echo "Événement inconnu : $event"; exit 1 ;;
esac
printf 'deployments=%s\nrollbacks=%s\nlast_deploy=%s\nmttr_total=%s\n' "$deployments" "$rollbacks" "$last_deploy" "$mttr_total" > "$state"
mttr=0; [ "$rollbacks" -gt 0 ] && mttr=$((mttr_total / rollbacks))
failure=0; [ "$deployments" -gt 0 ] && failure=$(awk "BEGIN{print $rollbacks/$deployments}")
{
  echo "dora_deployments_total $deployments"
  echo "dora_rollbacks_total $rollbacks"
  echo "dora_change_failure_rate $failure"
  echo "dora_mttr_seconds $mttr"
  if [ -n "$lead" ]; then echo "dora_lead_time_seconds $lead"; fi
  echo "dora_last_event_timestamp $now"
} | curl -fsS --data-binary @- "$gateway/metrics/job/dora"
echo "DORA publié ($event)"
