# ImpactShop – Projekt státusz

*Generálva:* 2026-01-15 17:30:38 +0100 (Bujdoso-Mac-mini)

## Meta
- Gyökér: /Users/bujdosoarnold/Developer/GitHub/impactshop-notes
- Környezet: local
- SSH_HOST: sharityh@s59.tarhely.com
- Git ág: docs/notes-update-2026-01-07
- Git hash: 7ea859d
- Módosított fájlok száma: 74

## REST healthcheck
- Staging: HTTP 200 (1250 ms, ok) – https://sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
- Production: HTTP 200 (993 ms, ok) – https://app.sharity.hu/wp-json/

## Git státusz
 M .codex/cron/impact-social-ledger-sync.sh
 M .codex/cron/workspace-backup.sh
 M .codex/scripts/impact-social-ledger-sync.php
 M .codex/scripts/lib/guard-common.sh
 M .codex/sprint-tasks/S1.md
 M .gitignore
 M wp-content/mu-plugins/impact-social-mvp.php
?? .production_env
?? .staging_env
?? "AI asszisztens/"
?? CertificateSigningRequest.certSigningRequest
?? "Impactshop Wallet Key.cer"
?? "Impactshop Wallet Key.p12"
?? Impactshop.p12
?? bator-tabor-alapitvany-impactshop.pkpass
?? "dr. Bujdoso Arnold.p12"
?? impact_hub/
?? impactshop-dirty-20251106.patch
?? logs/
?? pass.cer
?? prefix}options
?? staging-qa-20251107-084536.log
?? staging-qa-20251107-084609.log
?? staging-qa-20251107-084621.log
?? staging-qa-20251107-084701.log
?? staging-qa-20251107-084709.log
?? test.pkpass
?? tests/
?? wallet-diagnostics-20251103T220853/
?? wallet-diagnostics-20251103T221050/
?? wallet-diagnostics-20251103T221136/
?? wallet-diagnostics-20251103T221234/
?? wallet-diagnostics-20251103T221330/
?? wallet-diagnostics-20251103T221521/
?? wallet-diagnostics-20251103T221552/
?? wallet-diagnostics-20251103T221702/
?? wallet-diagnostics-20251103T221752/
?? wallet-diagnostics-20251103T221830/
?? wallet-diagnostics-20251103T222017/
?? wallet-diagnostics-20251103T222105/
?? wallet-diagnostics-20251103T222706/
?? wallet-diagnostics-20251103T225427/
?? wallet-diagnostics-20251103T231709/
?? wallet-pass
?? wp-content/mu-plugins/impactshop-ngo-card.php

## Fájlstruktúra (max depth 2, top 200 elem)
~~~
.
.DS_Store
.codex
.codex/.DS_Store
.codex/.env
.codex/.env.backup
.codex/.env.guard
.codex/.env.guard.bak.20251107-204119
.codex/.env.guard.example
.codex/.gitignore
.codex/BRIDGE.md
.codex/adr
.codex/agent-events.log
.codex/analytics.env
.codex/assets
.codex/auto-commit-loop.sh
.codex/auto-commit.sh
.codex/backups
.codex/bastion_manifest.json
.codex/briefing.md
.codex/changelogs
.codex/codex-version.lock
.codex/config
.codex/connection.json
.codex/context-latest.json
.codex/context-restore.txt
.codex/cron
.codex/dns-guard-hosts.txt
.codex/docs
.codex/guards
.codex/hooks
.codex/logs
.codex/media-import-report.json
.codex/observability
.codex/prometheus
.codex/releases
.codex/reports
.codex/retrospectives
.codex/scripts
.codex/security-checklist.md
.codex/sprint-blockers
.codex/sprint-tasks
.codex/status-latest.md
.codex/tasks
.codex/templates
.codex/tm
.codex/tmp
.codex/watch-refresh.launchd.plist
.codex/watch-refresh.sh
.codex/watch.pid
.codex/watchctl.sh
.codex/wheels
.git
.git/.DS_Store
.git/COMMIT_EDITMSG
.git/FETCH_HEAD
.git/HEAD
.git/ORIG_HEAD
.git/config
.git/description
.git/hooks
.git/index
.git/info
.git/logs
.git/objects
.git/refs
.github
.github/.DS_Store
.github/dependabot.yml
.github/workflows
.gitignore
.markdownlint.json
.prettierignore
.production_env
.staging_env
.venv
.venv/.DS_Store
.venv/lib
.venv/share
.vscode
.vscode/extensions.json
.vscode/extensions.lock
.vscode/settings.json
.vscode/tasks.json
000-dognet-token-ttl.php
=
AI asszisztens
AI asszisztens/AI asszisztens
CertificateSigningRequest.certSigningRequest
Impactshop Wallet Key.cer
Impactshop Wallet Key.p12
Impactshop.p12
Makefile
README.md
_archive
_archive/.DS_Store
_archive/examples
_archive/old-snippets
_repo_quarantine
_repo_quarantine/.codex_guard_put.json
_repo_quarantine/.codex_tm_backup
_repo_quarantine/corridor-audit-20251010_204447.md
_repo_quarantine/corridor-audit-20251010_204535.md
active_plugins_diff_testdiff.txt
ads-bridge
ads-bridge/.DS_Store
ads-bridge/.codex
ads-bridge/.env
ads-bridge/.env.example
ads-bridge/IMPACT_ADS_BRIDGE_SPEC.md
ads-bridge/bin
ads-bridge/services
ads-bridge/setup-ads-bridge.sh
ads-bridge/wp-content
bator-tabor-alapitvany-impactshop.pkpass
bin
bin/ngo-rate-limit-check.sh
bin/preflight-check.sh
codex_parity_green.sh
docs
docs/.DS_Store
docs/CHANGELOG.md
docs/adr
docs/api
docs/bastion-guard-status.md
docs/hosting
docs/image
docs/impactshop-badge-system.md
docs/impactshop-diagnostics.md
docs/impactshop-handbook.md
docs/impactshop-ngo-card-acceptance.md
docs/impactshop-ngo-card-brief.md
docs/impactshop-ngo-card-embed.md
docs/impactshop-ngo-card-release-phase1.md
docs/impactshop-ngo-card-usage.md
docs/impactshop-ngo-card-ux-spec.md
docs/impactshop-shortcodes.md
docs/impactshop-wallet-setup.md
docs/ops
docs/staging-mirroring.md
docs/system-recovery-map.md
docs/team
dr. Bujdoso Arnold.p12
image
image/=
impact-bridge-local
impact-bridge-local/cj-init.php
impact_hub
impact_hub/.codex
impact_hub/.git
impact_hub/.gitignore
impact_hub/README.md
impact_hub/scripts
impact_hub/system-status-snapshot.md
impactshop-baseline-2025-10-15.md
impactshop-baseline-2025-11-02.md
impactshop-dirty-20251106.patch
impactshop-link-diagnostics.php
impactshop-netflix-shortcodes.php
impactshop-notes
impactshop-notes/.DS_Store
impactshop-notes/.backups
impactshop-notes/.codex
impactshop-notes/.deploy.production.env
impactshop-notes/.deploy.staging.env
impactshop-notes/.editorconfig
impactshop-notes/.git
impactshop-notes/.github
impactshop-notes/.gitignore
impactshop-notes/.php-cs-fixer.cache
impactshop-notes/.php-cs-fixer.php
impactshop-notes/.phpunit.result.cache
impactshop-notes/.prettierrc
impactshop-notes/.production_env
impactshop-notes/.staging_env
impactshop-notes/.venv
impactshop-notes/.vscode
impactshop-notes/AGENTS.md
impactshop-notes/Claude Sonnet 4_Impact_hub_details.md
impactshop-notes/Hirdetési fiókok integrációja TERV.ini.md
impactshop-notes/Percy-setup.md
impactshop-notes/README.md
impactshop-notes/TradeTracker-integráció.md
impactshop-notes/WORKFLOW.md
impactshop-notes/analyze-diagnostics.php
impactshop-notes/bin
impactshop-notes/chatgpt-history
impactshop-notes/composer.json
impactshop-notes/composer.lock
impactshop-notes/conversation-summaries
impactshop-notes/docs
impactshop-notes/execution_run.sh
impactshop-notes/fix-diagnostics.php
impactshop-notes/image
impactshop-notes/impact-hub-system-v1.3.md
impactshop-notes/impactctl
impactshop-notes/impactshop-link-diag.php
impactshop-notes/impactshop-status.md
impactshop-notes/impactshop_diagnostics_2025-10-02_04-53-16.csv
impactshop-notes/impactshop_diagnostics_2025-10-02_06-01-00.csv
~~~

## Jegyzetek
- A ~/bin alatt elérhető helper scriptek: codex-refresh, impactresume.
- A fájl automatikusan generálva (scripts/status-snapshot.sh).
- 2025-10-14: Netflix/Deals shortcode REST go-deal linkpreferencia él; deploy után Elementor cache flush + REST warmup kötelező.
- GPT/Sonnet promptokat mindig szakmai review előz meg – automatikus végrehajtás tiltott, eltéréseket jelezd.

---
_Auto update: 2025-11-27 17:42:17_

### Health check summary

```
staging: curl error (exit=6) – https://www.sharity.hu/impactshop-staging/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-28 06:35:06_

### Health check summary

```
staging: curl error (exit=6) – https://www.sharity.hu/impactshop-staging/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-28 06:37:37_

### Health check summary

```
staging: HTTP 200 (960 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (918 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-28 07:30:13_

### Health check summary

```
staging: HTTP 200 (1020 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (905 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-28 08:15:09_

### Health check summary

```
staging: HTTP 200 (950 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (911 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 08:45:14_

### Health check summary

```
staging: HTTP 200 (1170 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1121 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 10:43:01_

### Health check summary

```
staging: HTTP 200 (1275 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1161 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 10:54:04_

### Health check summary

```
staging: HTTP 200 (1256 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1167 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 11:12:40_

### Health check summary

```
staging: HTTP 200 (1296 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1227 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 11:32:20_

### Health check summary

```
staging: HTTP 200 (1304 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1216 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 11:50:11_

### Health check summary

```
staging: HTTP 200 (1035 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (960 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 13:04:33_

### Health check summary

```
staging: HTTP 200 (1613 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1477 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 13:12:48_

### Health check summary

```
staging: HTTP 200 (1562 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1589 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 13:44:49_

### Health check summary

```
staging: HTTP 200 (1762 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1692 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 13:57:53_

### Health check summary

```
staging: HTTP 200 (1628 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1597 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 14:17:40_

### Health check summary

```
staging: HTTP 200 (1671 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (2333 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 14:42:08_

### Health check summary

```
staging: HTTP 200 (2091 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (2091 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 14:48:46_

### Health check summary

```
staging: HTTP 200 (3099 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (2961 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 15:00:02_

### Health check summary

```
staging: HTTP 200 (1487 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1347 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 15:09:09_

### Health check summary

```
staging: HTTP 200 (1905 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1591 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 15:20:47_

### Health check summary

```
staging: HTTP 200 (1883 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (3768 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 20:42:41_

### Health check summary

```
staging: HTTP 200 (1601 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1284 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-29 22:27:10_

### Health check summary

```
staging: HTTP 200 (1141 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1009 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-30 10:44:31_

### Health check summary

```
staging: HTTP 200 (1081 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1020 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-11-30 16:35:06_

### Health check summary

```
staging: curl error (exit=16) – https://www.sharity.hu/impactshop-staging/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-01 08:17:50_

### Health check summary

```
staging: HTTP 200 (899 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (860 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-01 10:57:50_

### Health check summary

```
staging: HTTP 200 (955 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (921 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-01 20:00:44_

### Health check summary

```
staging: HTTP 200 (1327 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1344 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 08:21:46_

### Health check summary

```
staging: HTTP 200 (1204 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1051 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 08:30:16_

### Health check summary

```
staging: HTTP 200 (1553 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1282 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 08:33:34_

### Health check summary

```
staging: HTTP 200 (1405 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1220 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 08:35:03_

### Health check summary

```
staging: HTTP 200 (1335 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1271 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 09:40:26_

### Health check summary

```
staging: HTTP 200 (1335 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1248 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 09:50:32_

### Health check summary

```
staging: HTTP 200 (1286 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1195 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 14:52:53_

### Health check summary

```
staging: HTTP 200 (1178 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1159 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-03 16:19:35_

### Health check summary

```
staging: HTTP 200 (1293 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1220 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-04 08:09:21_

### Health check summary

```
staging: HTTP 200 (896 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: curl error (exit=16) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-04 14:42:48_

### Health check summary

```
staging: HTTP 200 (1059 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (964 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-04 15:29:44_

### Health check summary

```
staging: HTTP 200 (1018 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1016 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-04 17:28:05_

### Health check summary

```
staging: HTTP 200 (999 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (920 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-04 17:28:31_

### Health check summary

```
staging: HTTP 200 (1024 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (921 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 05:31:02_

### Health check summary

```
staging: HTTP 200 (955 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (896 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 12:38:12_

### Health check summary

```
staging: HTTP 200 (896 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (850 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 18:28:00_

### Health check summary

```
staging: HTTP 200 (1178 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (996 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 19:09:57_

### Health check summary

```
staging: HTTP 200 (1001 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (972 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 20:46:33_

### Health check summary

```
staging: HTTP 200 (1078 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1058 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 20:58:40_

### Health check summary

```
staging: HTTP 200 (1160 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1050 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 21:03:41_

### Health check summary

```
staging: HTTP 200 (1192 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1018 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 21:13:34_

### Health check summary

```
staging: HTTP 200 (1158 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1009 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 21:18:21_

### Health check summary

```
staging: HTTP 200 (1006 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (887 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 21:22:43_

### Health check summary

```
staging: HTTP 200 (1132 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (977 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 21:26:22_

### Health check summary

```
staging: HTTP 200 (1041 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (985 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 21:29:40_

### Health check summary

```
staging: HTTP 200 (1344 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (12389 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-05 21:30:42_

### Health check summary

```
staging: HTTP 200 (1097 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1029 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-06 10:19:20_

### Health check summary

```
staging: HTTP 200 (1262 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1285 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-06 12:13:10_

### Health check summary

```
staging: HTTP 200 (1316 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1231 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-06 12:44:33_

### Health check summary

```
staging: HTTP 200 (1436 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1344 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 15:17:41_

### Health check summary

```
staging: HTTP 200 (917 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (960 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 15:34:13_

### Health check summary

```
staging: HTTP 200 (1012 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (929 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 17:16:18_

### Health check summary

```
staging: HTTP 200 (912 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (944 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 18:00:48_

### Health check summary

```
staging: HTTP 200 (1004 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (973 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 18:06:57_

### Health check summary

```
staging: curl error (exit=16) – https://www.sharity.hu/impactshop-staging/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 18:27:47_

### Health check summary

```
staging: HTTP 200 (924 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (894 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 18:34:22_

### Health check summary

```
staging: HTTP 200 (950 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (919 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2025-12-07 18:37:28_

### Health check summary

```
staging: HTTP 200 (973 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (955 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-05 20:46:58_

### Health check summary

```
staging: HTTP 200 (1203 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1006 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2026-01-05.md

---
_Auto update: 2026-01-07 19:15:13_

### Health check summary

```
staging: HTTP 200 (1097 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (916 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-07 19:15:31_

### Health check summary

```
staging: HTTP 200 (1158 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (893 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-07 19:20:02_

### Health check summary

```
staging: HTTP 200 (1024 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (961 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-07 21:22:37_

### Health check summary

```

---
_Auto update: 2026-01-07 21:36:18_

### Health check summary

```
staging: HTTP 200 (1147 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (1091 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-13 09:35:43_

### Health check summary

```
staging: HTTP 200 (822 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (797 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-13 09:35:59_

### Health check summary

```
staging: HTTP 200 (760 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
production: HTTP 200 (698 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-15 09:51:43_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-15 09:51:52_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-15 09:52:06_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-15 10:14:24_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-15 10:25:54_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-15 17:27:36_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-15 17:34:43_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-16 06:35:21_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-16 19:28:27_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-18 18:15:50_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-18 18:20:23_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-18 21:58:06_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-18 22:20:56_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-19 14:32:58_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-19 14:33:11_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-20 07:21:06_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-20 07:21:26_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-20 08:06:35_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-20 10:47:12_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md

---
_Auto update: 2026-01-20 19:27:34_

### Health check summary

```

```

**Baseline referencia:** impactshop-baseline-2025-11-02.md
