# JYSK report max-protection extension

## Summary

The JYSK report route family is now named explicitly as a max-protected guide surface.

## Protected files touched

- `docs/impactshop-protected-files.json`
- `docs/ai-assistant-canonical-policy.md`
- `docs/pr-policy.md`
- `docs/impactshop-deploy.md`

## Supporting docs touched

- `docs/bastion-guard-status.md`

## Why

- The guide subtree was already protected, but the JYSK report route family was only implicitly covered.
- We want `/jysk-riport/`, `/jysk-riport/?print=1`, and `/jysk-riport.data.json` to be explicit guard-model and smoke-scope citizens.

## Risks

- Over-tightening the guide smoke model could block future legitimate guide-only changes if smoke scopes are incomplete.
- Policy/docs drift is possible if the machine-readable model and narrative docs diverge.

## Smoke

- `route:jysk-riport`
- `route:ngo-guides`
- `flow:guide-route-render`
- `flow:guide-print-mode`
- `flow:guide-data-json`

## Rollback

- `git revert <protected-commit>`
- `git revert <ops-doc-commit>`

## Validation

- `python3 -m json.tool docs/impactshop-protected-files.json`
- grep confirmation for `jysk-riport` and `guide_runtime` across policy docs
