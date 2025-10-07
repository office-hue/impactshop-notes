run-task:
	@bash ./.codex/bridge/execute.sh

show-last:
	@cat ./.codex/bridge/last-run.json | jq .

show-usage:
	@cat ./.codex/bridge/usage.json | jq .

doctor:
	@cp ./.codex/bridge/tasks/current-task.doctor.json ./.codex/bridge/current-task.json
	@bash ./.codex/bridge/execute.sh
	@echo "== Doctor kész. Nézd meg: make show-last && make show-usage =="
