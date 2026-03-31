# 2026-03-31 — Guard hardening propagation

## Protected files touched

- `docs/pr-policy.md`
- `docs/impactshop-deploy.md`
- `docs/ai-assistant-canonical-policy.md`
- `docs/protected-file-change-checklist.md`
- `docs/bastion-guard-status.md`

## Why

Az utóbbi sérülékenységi és review-fix körökből levont, általánosítható hardening tanulságokat közös policy-szintre kell emelni, hogy ne csak az adott surface-eknél éljenek.

## Risk

- Alacsony runtime-kockázat: ez a lane csak policy- és checklist-szintű dokumentációt módosít.
- Közepes operatív kockázat: ha a szabályok nincsenek egységesen dokumentálva, a későbbi protected lane-ek ismét eltérhetnek lokál és CI között.

## Rollback

- Git revert a docs lane commitjára.
- Dokumentum-visszaállítás az előző `main` állapotból, ha a friss szabályszöveg félrevezető vagy túl széles.

## Smoke / verification

- `bash scripts/check-commit-lane.sh --mode local`
- `git diff --check`
- smoke scope marker: `deploy_guard`
- protected docs commit `deploy_guard` smoke tagekkel:
  - `deploy:guard-preflight`
  - `deploy:checksum-verify`
