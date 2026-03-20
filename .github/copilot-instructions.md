<!-- BEGIN CANONICAL ASSISTANT POLICY -->

# AI Assistant Canonical Policy

Primary sources for this repo:

- `/Users/bujdosoarnold/AGENTS.md`
- `/Users/bujdosoarnold/Developer/GitHub/ai-agent/docs/ai-assistant-canonical-policy.md`
- `docs/ai-assistant-canonical-policy.md`
- `docs/pr-policy.md`

Minimum required behavior:

1. Session elején futtasd az `ai-agent` repóban a `memory:pre-task` parancsot.
2. Ha a kontextus stale vagy hiányos, futtasd a `memory:full-sync` parancsot.
3. Session végén kötelező a `memory:v2:session-save` és a `memory:full-sync`.
4. Közvetlen `main/master` commit és push tiltott.
5. Deploy csak merge-elt főágból mehet, guardolt útvonalon.
6. `notes.md`, releváns `docs/*.md`, és szükség esetén `conversation-summaries/*` frissítése kötelező.
7. Bastion / védett fájl módosítás előtt backup + rollback kötelező.
8. Minden user-facing válasz magyarul készüljön.

<!-- END CANONICAL ASSISTANT POLICY -->

# Copilot Workflow Guardrails

When the task involves code changes, refactor, debugging, or PR review:

1. Call `dev_memory_brief` first with a short task description.
2. Use returned memory/file context before proposing edits.
3. If memory is stale or missing, call `dev_memory_stats` and note it explicitly.
4. Keep answers aligned with repository guardrails (`pr-policy`, hooks, safe audit).

If MCP tool calls fail, continue in fail-open mode, but clearly state that memory context was unavailable.
