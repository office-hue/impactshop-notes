# ImpactShop – Projekt státusz

*Generálva:* 2025-10-06 19:22:49 +0200 (Bujdoso-Mac-mini)

## Meta
- Gyökér: /Users/bujdosoarnold/Documents/GitHub/impactshop-notes
- Környezet: local
- SSH_HOST: (nincs megadva)
- Git ág: main
- Git hash: b0ab8bc
- Módosított fájlok száma: 33

## REST healthcheck
- Staging: HTTP 0 (0 ms, unreachable) – https://app.sharity.hu/impactshop-staging/wp-json/impact/v1/total
- Production: HTTP 0 (0 ms, unreachable) – https://app.sharity.hu/?rest_route=/impact/v1/total

## Git státusz
  -  M .codex/context-meta.json
  -  M .codex/context-snapshot.txt
  -  M .staging_env
  -  M bin/codex-refresh.sh
  -  M bin/staging-qa-suite.sh
  -  M notes.md
  - ?? .codex/connection.json
  - ?? .codex/context-20251005-223221.json
  - ?? .codex/context-20251005-223436.json
  - ?? .codex/context-20251005-224323.json
  - ?? .codex/context-20251005-224702.json
  - ?? .codex/context-20251005-224703.json
  - ?? .codex/context-20251005-224736.json
  - ?? .codex/context-20251005-230035.json
  - ?? .codex/context-20251006-072731.json
  - ?? .codex/context-20251006-074424.json
  - ?? .codex/context-20251006-074503.json
  - ?? .codex/context-20251006-074515.json
  - ?? .codex/context-20251006-081951.json
  - ?? .codex/context-20251006-082022.json
  - ?? .codex/context-20251006-082023.json
  - ?? .codex/context-20251006-102244.json
  - ?? .codex/context-latest.json
  - ?? .codex/context-restore.txt
  - ?? .codex/deploy-latency-20251005-192507.png
  - ?? .codex/deploy-latency.png
  - ?? .venv/
  - ?? AGENTS.md
  - ?? bin/codex-with-ssh.sh
  - ?? bin/impactctl-resume.sh
  - ?? bin/staging-qa-suite.sh.bak.20251005-191357
  - ?? conversation-summaries/16_conversation_summary.md
  - ?? wp-content/wp-config.php

## Fájlstruktúra (max depth 2, top 200 elem)
~~~
./.DS_Store
./.backups
./.backups/critical-20251002-091624
./.backups/files-to-modify-20251002-091624.txt
./.backups/impactshop-20251002-091624.tar.gz
./.codex
./.codex/_go_no_go_production_20251005-184535.out
./.codex/_go_no_go_production_20251005-184930.out
./.codex/_go_no_go_production_20251005-185455.out
./.codex/_go_no_go_production_20251005-185656.out
./.codex/_go_no_go_production_20251005-185757.out
./.codex/_go_no_go_staging_20251005-195717.out
./.codex/_preflight_production_20251005-184535.out
./.codex/_preflight_production_20251005-184930.out
./.codex/_preflight_production_20251005-185455.out
./.codex/_preflight_production_20251005-185656.out
./.codex/_preflight_production_20251005-185757.out
./.codex/_preflight_production_20251005-185816.out
./.codex/_preflight_production_20251005-185936.out
./.codex/_preflight_production_20251005-190101.out
./.codex/_preflight_production_20251005-190214.out
./.codex/_preflight_production_20251005-190331.out
./.codex/_preflight_production_20251005-190424.out
./.codex/_preflight_staging_20251005-181650.out
./.codex/_preflight_staging_20251005-181848.out
./.codex/_preflight_staging_20251005-182733.out
./.codex/_preflight_staging_20251005-182800.out
./.codex/_preflight_staging_20251005-182824.out
./.codex/_preflight_staging_20251005-182844.out
./.codex/_preflight_staging_20251005-184418.out
./.codex/_preflight_staging_20251005-195717.out
./.codex/_preflight_staging_20251006-074333.out
./.codex/_preflight_staging_20251006-074420.out
./.codex/_preflight_staging_20251006-074429.out
./.codex/_preflight_staging_20251006-074459.out
./.codex/connection.json
./.codex/context-20251005-223221.json
./.codex/context-20251005-223436.json
./.codex/context-20251005-224323.json
./.codex/context-20251005-224702.json
./.codex/context-20251005-224703.json
./.codex/context-20251005-224736.json
./.codex/context-20251005-230035.json
./.codex/context-20251006-072731.json
./.codex/context-20251006-074424.json
./.codex/context-20251006-074503.json
./.codex/context-20251006-074515.json
./.codex/context-20251006-081951.json
./.codex/context-20251006-082022.json
./.codex/context-20251006-082023.json
./.codex/context-20251006-102244.json
./.codex/context-latest.json
./.codex/context-meta.json
./.codex/context-restore.txt
./.codex/context-snapshot.txt
./.codex/deploy-latency-20251005-192507.png
./.codex/deploy-latency.png
./.codex/deploy-log.txt
./.deploy.production.env
./.deploy.staging.env
./.editorconfig
./.git
./.git/.DS_Store
./.git/COMMIT_EDITMSG
./.git/FETCH_HEAD
./.git/HEAD
./.git/ORIG_HEAD
./.git/branches
./.git/config
./.git/description
./.git/hooks
./.git/index
./.git/info
./.git/logs
./.git/objects
./.git/packed-refs
./.git/refs
./.github
./.github/PROD_RELEASE_TEMPLATE.md
./.github/copilot-instructions.md
./.gitignore
./.php-cs-fixer.cache
./.php-cs-fixer.php
./.prettierrc
./.staging_env
./.venv
./.venv/.DS_Store
./.venv/bin
./.venv/include
./.venv/lib
./.venv/pyvenv.cfg
./.venv/share
./.vscode
./.vscode/extensions.json
./AGENTS.md
./README.md
./WORKFLOW.md
./analyze-diagnostics.php
./bin
./bin/codex-refresh.sh
./bin/codex-with-ssh.sh
./bin/deploy-latency-chart.py
./bin/deploy-log-view.sh
./bin/deploy-wpcontent-map.sh
./bin/deploy.sh
./bin/emergency-brake.sh
./bin/emergency-fixes.sh
./bin/error-reporter.sh
./bin/go-no-go-check.sh
./bin/go-nogo-check.sh
./bin/impact-backup.sh
./bin/impact-safety-qa.php
./bin/impactctl-connect.sh
./bin/impactctl-guard-staging.sh
./bin/impactctl-resume.sh
./bin/post-deploy-activate.sh
./bin/post-deploy-checklist.sh
./bin/preflight-check.sh
./bin/preflight-run.sh
./bin/production-go-live.sh
./bin/quick-go-nogo.sh
./bin/rollback.sh
./bin/setup-deploy.sh
./bin/setup-sharity-environment.sh
./bin/staging-auto-diagnose.sh
./bin/staging-certification.sh
./bin/staging-qa-suite.sh
./bin/staging-qa-suite.sh.bak
./bin/staging-qa-suite.sh.bak.20251005-191357
./bin/staging-qa-suite.sh.precasefix
./bin/staging-quick-fix.sh
./bin/staging-readiness-check-fallback.sh
./bin/staging-readiness-check.sh
./bin/staging-rest-fix.sh
./bin/staging-rollback-drill.sh
./chatgpt-history
./chatgpt-history/10_beszélgetés.md
./chatgpt-history/11_beszélgetés.md
./chatgpt-history/12_beszélgetés.md
./chatgpt-history/13_beszélgetés.md
./chatgpt-history/14_beszélgetés.md
./chatgpt-history/15_beszélgetés.md
./chatgpt-history/16_beszélgetés.md
./chatgpt-history/17_beszélgetés.md
./chatgpt-history/18_beszélgetés.md
./chatgpt-history/19_beszélgetés.md
./chatgpt-history/2025-10-01_dognet-api-integráció.md
./chatgpt-history/2025-10-01_fillout-redirection-dognet.md
./chatgpt-history/2025-10-01_impact-shop-fejlesztes.md
./chatgpt-history/2025-10-01_nyeremenyjáték-rendszer.md
./chatgpt-history/2025-10-01_xml-feed-banner-scroller-kategorizalas.md
./chatgpt-history/2025-10-01_xml-feed-google-sheets-webshop-management.md
./chatgpt-history/20_beszélgetés.md
./chatgpt-history/21_beszélgetés.md
./chatgpt-history/22_beszélgetés.md
./chatgpt-history/23_megbeszélés.md
./chatgpt-history/24_beszélgetés.md
./chatgpt-history/25_beszélgetés.md
./chatgpt-history/26_megbeszélés.md
./chatgpt-history/27_megbeszélés.md
./chatgpt-history/28_megbeszélés.md
./chatgpt-history/29_megbeszélés.md
./chatgpt-history/2_ beszélgetés.md
./chatgpt-history/30_megbeszélés.md
./chatgpt-history/31_megbeszélés.md
./chatgpt-history/33_megbeszélés.md
./chatgpt-history/3_beszélgetés.md
./chatgpt-history/4_beszélgetés.md
./chatgpt-history/5_beszélgetés.md
./chatgpt-history/6_beszélgetés.md
./chatgpt-history/7_beszélgetés.md
./chatgpt-history/8_beszélgetés.md
./chatgpt-history/8_beszélgetés_summary.md
./chatgpt-history/9_beszélgetés.md
./chatgpt-history/9_beszélgetés_összefoglaló.md
./chatgpt-history/Induló beszélgetés.md
./chatgpt-history/README.md
./composer.json
./composer.lock
./conversation-summaries
./conversation-summaries/10_conversation_summary.md
./conversation-summaries/11_conversation_summary.md
./conversation-summaries/12_conversation_summary.md
./conversation-summaries/13_conversation_summary.md
./conversation-summaries/14_conversation_summary.md
./conversation-summaries/15_conversation_summary.md
./conversation-summaries/16_conversation_summary.md
./conversation-summaries/1_conversation_summary.md
./fix-diagnostics.php
./impactctl
./impactshop-link-diag.php
./impactshop_diagnostics_2025-10-02_04-53-16.csv
./impactshop_diagnostics_2025-10-02_06-01-00.csv
./manifest.json
./mu-plugins
./mu-plugins/banner-fix-instant.php
./notes.md
./nyeremenyjáték-technikai-spec.md
./settings.json
./staging-qa-20251003-102455.log
~~~

## Jegyzetek
- A ~/bin alatt elérhető helper scriptek: codex-refresh, impactresume.
- Új ChatGPT 5 beszélgetésnél illeszd be ezt a fájlt nyitó kontextusnak.

