#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SKIP_LOG_FILE="${REPO_ROOT}/.codex/logs/analytics-skip-events.log"
SKIP_WARN_THRESHOLD="${ANALYTICS_SKIP_WARN_THRESHOLD:-6}"

record_skip() {
	local guard_name="$1"
	local reason="$2"
	mkdir -p "$(dirname "${SKIP_LOG_FILE}")"
	printf '%s | guard=%s | reason=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "${guard_name}" "${reason}" >> "${SKIP_LOG_FILE}"
}

run_guard_with_skip_telemetry() {
	local guard_name="$1"
	local guard_script="$2"
	local output

	output="$("${guard_script}" 2>&1)" || {
		printf '%s\n' "${output}" >&2
		return 1
	}

	printf '%s\n' "${output}"

	while IFS= read -r line; do
		[[ "${line}" == *" SKIP:"* ]] || continue
		record_skip "${guard_name}" "${line}"
	done <<< "${output}"
}

warn_if_skip_volume_high() {
	[[ -f "${SKIP_LOG_FILE}" ]] || return 0
	local now
	local last24_count
	now="$(date -u +%s)"
	last24_count="$((
		$(awk -v now="${now}" '
			{
				ts=$1
				gsub(/T/, " ", ts)
				gsub(/Z/, "", ts)
				cmd="date -u -j -f \"%Y-%m-%d %H:%M:%S\" \"" ts "\" +%s 2>/dev/null"
				cmd | getline epoch
				close(cmd)
				if (epoch == "") {
					cmd="date -u -d \"" ts "\" +%s 2>/dev/null"
					cmd | getline epoch
					close(cmd)
				}
				if (epoch != "" && (now - epoch) <= 86400) {
					c++
				}
			}
			END {print c+0}
		' "${SKIP_LOG_FILE}")
	))"

	if (( last24_count >= SKIP_WARN_THRESHOLD )); then
		echo "WARN: analytics skip volume high in last 24h (count=${last24_count}, threshold=${SKIP_WARN_THRESHOLD})"
	fi
}

echo "Running analytics canary guard..."
run_guard_with_skip_telemetry "analytics-canary-guard" "${SCRIPT_DIR}/analytics-canary-guard.sh"

echo "Running analytics consent guard..."
run_guard_with_skip_telemetry "analytics-consent-guard" "${SCRIPT_DIR}/analytics-consent-guard.sh"

echo "Running ads-watch pseudo canary..."
"${SCRIPT_DIR}/ads-watch-pseudo-canary.sh"

warn_if_skip_volume_high

echo "Analytics suite OK"
