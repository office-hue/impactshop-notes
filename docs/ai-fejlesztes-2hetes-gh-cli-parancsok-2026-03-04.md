# AI fejlesztés 2 hét – `gh` CLI parancsok (kanonikus)

> Dátum: 2026-03-04

## Előfeltétel

```bash
gh auth status
gh repo view office-hue/impactshop-notes
gh repo view office-hue/impact_hub
```

## Dry-run

```bash
bash impactshop-notes/scripts/create-ai-issues.sh --dry-run
```

## Éles issue-nyitás

```bash
bash impactshop-notes/scripts/create-ai-issues.sh
```

## Ellenőrzés

```bash
gh issue list --repo office-hue/impactshop-notes --limit 50
gh issue list --repo office-hue/impact_hub --limit 50
```

