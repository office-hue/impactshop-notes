# ImpactShop – Projekt státusz

*Generálva:* 2026-01-21 08:42:12 +0100 (Bujdoso-Mac-mini)

## Meta
- Gyökér: /Users/bujdosoarnold/Developer/GitHub/impactshop-notes
- Környezet: local
- SSH_HOST: sharityh@s59.tarhely.com
- Git ág: docs/notes-update-2026-01-07
- Git hash: d0a3f93f
- Módosított fájlok száma: 12

## REST healthcheck
- Staging: HTTP 200 (830 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
- Production: HTTP 200 (671 ms, ok) – https://app.sharity.hu/wp-json/

## Git státusz
  -  M .codex/context-latest.json
  -  M docs/impactshop-system-map.md
  -  M impactshop-status.md
  -  M notes.md
  - ?? JYSK_WhoisJYSK_Cutdown_20sec_JHU.mp4
  - ?? bin/image/
  - ?? bin/impactshop-guard-deploy.sh
  - ?? bin/impactshop-guard-init.sh
  - ?? bin/impactshop-guard-rollback.sh
  - ?? docs/impactshop-guard-config.json
  - ?? docs/impactshop-guard-hashes.json
  - ?? docs/impactshop-guard-hashes.sha256

## Fájlstruktúra (max depth 2, top 200 elem)
~~~
./.DS_Store
./.backups
./.backups/critical-20251002-091624
./.backups/dataless-20251207-181326.txt
./.backups/dataless-20251207-181553.txt
./.backups/dataless-20251207-181644.txt
./.backups/files-to-modify-20251002-091624.txt
./.backups/git-status-20251207-190046.txt
./.backups/git-status-20251207-190213.txt
./.backups/git-status-20251207-190311.txt
./.backups/impactshop-20251002-091624.tar.gz
./.backups/impactshop-git-20251207-190046.bundle
./.backups/impactshop-git-20251207-190213.bundle
./.backups/impactshop-git-20251207-190311.bundle
./.backups/working-tree-20251207-190046.patch
./.backups/working-tree-20251207-190213.patch
./.backups/working-tree-20251207-190311.patch
./.codex
./.codex/.DS_Store
./.codex/.env
./.codex/.env.local
./.codex/.env.local.example
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
./.codex/_preflight_production_20251006-200541.out
./.codex/_preflight_production_20251006-200612.out
./.codex/_preflight_production_20251008-090650.out
./.codex/_preflight_production_20251008-090847.out
./.codex/_preflight_production_20251008-112944.out
./.codex/_preflight_production_20251008-113037.out
./.codex/_preflight_production_20260120-104144.out
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
./.codex/_preflight_staging_20251006-200307.out
./.codex/_preflight_staging_20251006-200334.out
./.codex/_preflight_staging_20251006-200404.out
./.codex/_preflight_staging_20251008-084955.out
./.codex/_preflight_staging_20251008-085050.out
./.codex/_preflight_staging_20251008-093811.out
./.codex/_preflight_staging_20251008-093906.out
./.codex/_preflight_staging_20251008-093947.out
./.codex/_preflight_staging_20251008-094031.out
./.codex/_preflight_staging_20251008-094106.out
./.codex/_preflight_staging_20251008-094148.out
./.codex/_preflight_staging_20251008-112843.out
./.codex/_preflight_staging_20251008-112935.out
./.codex/_preflight_staging_20251008-125449.out
./.codex/_preflight_staging_20260120-092133.out
./.codex/_preflight_staging_20260120-095343.out
./.codex/_preflight_staging_20260120-100220.out
./.codex/_preflight_staging_20260120-101439.out
./.codex/adr
./.codex/ads
./.codex/bridge
./.codex/briefing-impl-plan.md
./.codex/briefing-v2.1 2.md
./.codex/briefing-v2.1.md
./.codex/briefing.md
./.codex/changelogs
./.codex/config
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
./.codex/cron
./.codex/deploy-latency-20251005-192507.png
./.codex/deploy-latency.png
./.codex/deploy-log.txt
./.codex/docs
./.codex/guards
./.codex/logs
./.codex/media-import-report.json
./.codex/observability
./.codex/prometheus
./.codex/reports
./.codex/retrospectives
./.codex/scripts
./.codex/security-checklist.md
./.codex/sprint-blockers
./.codex/sprint-tasks
./.codex/state
./.codex/templates
./.codex/tm
./.codex/tmp
./.deploy.production.env
./.deploy.staging.env
./.editorconfig
./.git
./.git.bak-20260107
./.git.bak-20260107/.DS_Store
./.git.bak-20260107/COMMIT_EDITMSG
./.git.bak-20260107/FETCH_HEAD
./.git.bak-20260107/HEAD
./.git.bak-20260107/config
./.git.bak-20260107/description
./.git.bak-20260107/hooks
./.git.bak-20260107/index
./.git.bak-20260107/info
./.git.bak-20260107/logs
./.git.bak-20260107/objects
./.git.bak-20260107/refs
./.git/.DS_Store
./.git/COMMIT_EDITMSG
./.git/FETCH_HEAD
./.git/HEAD
./.git/ORIG_HEAD
./.git/config
./.git/description
./.git/hooks
./.git/index
./.git/info
./.git/logs
./.git/objects
./.git/packed-refs
./.git/refs
./.git/shallow
./.github
./.github/CODEOWNERS
./.github/PROD_RELEASE_TEMPLATE.md
./.github/copilot-instructions.md
./.github/protected-files.txt
./.github/workflows
./.gitignore
./.markdownlint.json
./.php-cs-fixer.cache
./.php-cs-fixer.php
./.phpunit.result.cache
./.prettierrc
./.production_env
./.staging_env
./.venv
./.venv/.DS_Store
./.vscode
./.vscode/extensions.json
./.vscode/settings.json
./.vscode/tasks.json
./.wp-test-site
./.wp-test-site/index.php
./.wp-test-site/license.txt
./.wp-test-site/readme.html
./.wp-test-site/wp-activate.php
./.wp-test-site/wp-admin
./.wp-test-site/wp-blog-header.php
./.wp-test-site/wp-comments-post.php
./.wp-test-site/wp-config-sample.php
./.wp-test-site/wp-config.php
./.wp-test-site/wp-content
./.wp-test-site/wp-cron.php
./.wp-test-site/wp-includes
./.wp-test-site/wp-links-opml.php
./.wp-test-site/wp-load.php
./.wp-test-site/wp-login.php
./.wp-test-site/wp-mail.php
./.wp-test-site/wp-settings.php
./.wp-test-site/wp-signup.php
./.wp-test-site/wp-trackback.php
./.wp-test-site/xmlrpc.php
./AGENTS.md
./CJ Integráció.md
~~~

## Jegyzetek
- A ~/bin alatt elérhető helper scriptek: codex-refresh, impactresume.
- Új ChatGPT 5 beszélgetésnél illeszd be ezt a fájlt nyitó kontextusnak.
- 2025-10-14: Netflix/Deals shortcode már a REST /go-deal/?u= linket preferálja; deploy után Elementor cache flush + REST warmup kötelező.
- GPT/Sonnet promptokat minden esetben szakmai review előz meg – automatikus végrehajtás tiltott, szükség esetén javasolj korrekciót.

