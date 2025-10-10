#!/usr/bin/env bash
set -euo pipefail

TASK_FILE=".codex/bridge/current-task.json"
OUT_FILE=".codex/bridge/last-run.json"
USAGE_FILE=".codex/bridge/usage.json"

command -v jq >/dev/null || { echo "jq required"; exit 1; }

goal=$(jq -r '.goal' "$TASK_FILE")
host=$(jq -r '.host' "$TASK_FILE")
user=$(jq -r '.user' "$TASK_FILE")
workdir=$(jq -r '.workdir // ""' "$TASK_FILE")
timeout_sec=$(jq -r '.timeout_sec // 600' "$TASK_FILE")
mapfile -t actions < <(jq -r '.actions[]' "$TASK_FILE")

# Env felülírás (Secrets-ből jöhet)
host=${SSH_HOST:-$host}
user=${SSH_USER:-$user}
port=${SSH_PORT:-22}

started=$(date -Iseconds); started_epoch=$(date +%s)
results=(); status="OK"
total_cmds=0; total_seconds=0; total_stdout_bytes=0

have_timeout_cmd=$(command -v timeout >/dev/null 2>&1 && echo 1 || echo 0)
have_time_cmd=$(test -x /usr/bin/time && echo 1 || echo 0)

run_remote() {
  local cmd="$1"
  local pre="set -euo pipefail; ${workdir:+cd \"$workdir\"; }$cmd"
  local elapsed="0.000" rc out

  # preferáld az ssh configban a 'target' Host-ot ha be van állítva
  local dest="${user}@${host}"
  if grep -q '^Host target' ~/.ssh/config 2>/dev/null; then dest="target"; fi

  if [[ "$have_time_cmd" == "1" ]]; then
    if [[ "$have_timeout_cmd" == "1" ]]; then
      out=$( { /usr/bin/time -f '%e' bash -lc "timeout ${timeout_sec}s ssh -p $port -o StrictHostKeyChecking=no \"$dest\" \"$pre\"" 1>&1; } 2>._time )
    else
      out=$( { /usr/bin/time -f '%e' ssh -p $port -o StrictHostKeyChecking=no "$dest" "$pre" 1>&1; } 2>._time )
    fi
    rc=$?; elapsed=$(cat ._time 2>/dev/null || echo "0.000"); rm -f ._time
  else
    if [[ "$have_timeout_cmd" == "1" ]]; then
      out=$(timeout ${timeout_sec}s ssh -p $port -o StrictHostKeyChecking=no "$dest" "$pre" 2>&1); rc=$?
    else
      out=$(ssh -p $port -o StrictHostKeyChecking=no "$dest" "$pre" 2>&1); rc=$?
    fi
  fi

  (( total_cmds++ )) || true
  [[ "$elapsed" =~ ^[0-9]+(\.[0-9]+)?$ ]] && total_seconds=$(awk -v a="$total_seconds" -v b="$elapsed" 'BEGIN{printf "%.3f", a+b}')
  total_stdout_bytes=$(( total_stdout_bytes + ${#out} ))

  jq -n --arg c "$cmd" --arg o "$out" --arg el "$elapsed" --argjson e "$rc" \
      '{cmd:$c, exit:$e, elapsed_sec:$el, stdout:$o, stderr:""}'
}

for cmd in "${actions[@]}"; do
  item_json=$(run_remote "$cmd")
  results+=( "$item_json" )
  exit_code=$(jq -r '.exit' <<<"$item_json")
  [[ "${exit_code:-1}" -ne 0 ]] && status="ERROR"
done

finished=$(date -Iseconds); finished_epoch=$(date +%s)
wallclock=$(( finished_epoch - started_epoch ))

jq -n \
  --arg goal "$goal" \
  --arg started "$started" \
  --arg finished "$finished" \
  --arg status "$status" \
  --arg wallclock_sec "$wallclock" \
  --arg total_seconds "$total_seconds" \
  --arg total_cmds "$total_cmds" \
  --arg total_stdout_bytes "$total_stdout_bytes" \
  --argjson results "[ $(IFS=,; echo "${results[*]}") ]" '
{
  goal: $goal,
  started_at: $started,
  finished_at: $finished,
  status: $status,
  summary: {
    wallclock_sec: ($wallclock_sec|tonumber),
    commands: ($total_cmds|tonumber),
    cpu_elapsed_sec: ($total_seconds|tonumber),
    stdout_bytes: ($total_stdout_bytes|tonumber)
  },
  results: ($results)
}' > "$OUT_FILE"

# usage.json gördülő aggregáció
if [[ -f "$USAGE_FILE" ]]; then prev=$(cat "$USAGE_FILE"); else prev='{"runs":0,"commands":0,"cpu_elapsed_sec":0,"wallclock_sec":0,"stdout_bytes":0,"actions_wallclock_sec":0,"last_goal":""}'; fi
jq -n --argjson prev "$prev" --arg goal "$goal" --arg wc "$wallclock" --arg cmds "$total_cmds" --arg cpu "$total_seconds" --arg bytes "$total_stdout_bytes" '
{
  runs: ($prev.runs + 1),
  commands: ($prev.commands + ($cmds|tonumber)),
  cpu_elapsed_sec: ($prev.cpu_elapsed_sec + ($cpu|tonumber)),
  wallclock_sec: ($prev.wallclock_sec + ($wc|tonumber)),
  stdout_bytes: ($prev.stdout_bytes + ($bytes|tonumber)),
  actions_wallclock_sec: ($prev.actions_wallclock_sec // 0),
  last_goal: $goal,
  updated_at: (now|todate)
}' > "$USAGE_FILE"

echo "Wrote $OUT_FILE; updated $USAGE_FILE"
