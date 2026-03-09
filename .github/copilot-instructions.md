# Copilot Workflow Guardrails

When the task involves code changes, refactor, debugging, or PR review:

1. Call `dev_memory_brief` first with a short task description.
2. Use returned memory/file context before proposing edits.
3. If memory is stale or missing, call `dev_memory_stats` and note it explicitly.
4. Keep answers aligned with repository guardrails (`pr-policy`, hooks, safe audit).

If MCP tool calls fail, continue in fail-open mode, but clearly state that memory context was unavailable.
