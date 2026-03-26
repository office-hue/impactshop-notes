# Hatás Körök memory loader

## Cél

Gyors, célzott kontextusbetöltés a Hatás Körök témához, hogy a munka előtt a dev-memory és a repo-specifikus context pack is frissüljön.

## Futtatás

```bash
bash ./scripts/hatas-korok-load-memory.sh
```

Teljes memory sync-kel:

```bash
bash ./scripts/hatas-korok-load-memory.sh --full-sync
```

## Mit csinál

- generál egy kurált memo fájlt: `.codex/context/hatas-korok-work-memo.md`
- futtat egy `memory:pre-task` lekérést az `ai-agent` repón keresztül
- generál egy dedikált context packot: `.codex/context/hatas-korok-work-context.md`
- opcionálisan teljes `memory:full-sync`-et indít

## Lefedett kulcsfájlok

- `wp-content/mu-plugins/impact-community.php`
- `wp-content/mu-plugins/impact-community-app.php`
- `wp-content/mu-plugins/impactshop-ngo-guides.php`
- `scripts/hatas-korok-post-deploy-smoke.sh`
- `bin/deploy-wpcontent-map.sh`
- `bin/post-deploy-checklist.sh`
- `docs/hatas-korok-post-deploy-checklist.md`
- `docs/impactshop-deploy.md`
- `notes.md`
- `system-status-snapshot.md`
