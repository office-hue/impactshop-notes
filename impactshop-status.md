# ImpactShop – Projekt státusz

*Generálva:* 2026-01-19 14:33:08 +0100 (Bujdoso-Mac-mini)

## Meta
- Gyökér: /Users/bujdosoarnold/Developer/GitHub/impactshop-notes
- Környezet: local
- SSH_HOST: sharityh@s59.tarhely.com
- Git ág: docs/notes-update-2026-01-07
- Git hash: c3a9c91e
- Módosított fájlok száma: 533

## REST healthcheck
- Staging: HTTP 200 (900 ms, ok) – https://www.sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
- Production: HTTP 200 (832 ms, ok) – https://app.sharity.hu/wp-json/

## Git státusz
  -  M .codex/context-latest.json
  -  M docs/api/openapi.yaml
  -  M docs/coupon-harvester-overview.md
  -  M docs/prod-guard-checklist.md
  -  M impactshop-status.md
  -  M notes.md
  -  M wp-content/mu-plugins/impact-social-mvp.php
  -  M wp-content/mu-plugins/impactshop-identity-panel.php
  - ?? .codex/adr/
  - ?? .codex/briefing-impl-plan.md
  - ?? ".codex/briefing-v2.1 2.md"
  - ?? .codex/briefing-v2.1.md
  - ?? .codex/briefing.md
  - ?? .codex/changelogs/
  - ?? .codex/config/
  - ?? .codex/cron/
  - ?? .codex/docs/
  - ?? .codex/guards/
  - ?? .codex/logs/
  - ?? .codex/media-import-report.json
  - ?? .codex/observability/
  - ?? .codex/prometheus/
  - ?? .codex/reports/
  - ?? .codex/retrospectives/
  - ?? .codex/scripts/
  - ?? .codex/security-checklist.md
  - ?? .codex/sprint-blockers/
  - ?? .codex/sprint-tasks/
  - ?? .codex/state/
  - ?? .codex/templates/
  - ?? .codex/tm/
  - ?? .git.bak-20260107/
  - ?? .github/workflows/e2e-tests.yml
  - ?? .markdownlint.json
  - ?? .phpunit.result.cache
  - ?? .production_env
  - ?? .wp-test-site/
  - ?? "CJ Integr\303\241ci\303\263.md"
  - ?? "CJ links/"
  - ?? "Claude Sonnet 4_Impact_hub_details.md"
  - ?? "Google Ads/"
  - ?? "Hirdet\303\251si fi\303\263kok integr\303\241ci\303\263ja TERV.ini.md"
  - ?? "Impi Tud\303\241sb\303\241zis/"
  - ?? "NGO data/"
  - ?? Percy-setup.md
  - ?? "TradeTracker-integr\303\241ci\303\263.md"
  - ?? "User token/"
  - ?? apps/
  - ?? bin/backup-sync.sh
  - ?? bin/hotfix-precheck.sh
  - ?? bin/impactctl-core-task.sh
  - ?? bin/install-wp-tests.sh
  - ?? "chatgpt-history/## GitHub Copilot Chat"
  - ?? "chatgpt-history/## GitHub Copilot Chat.md"
  - ?? chatgpt-history/2026-01-18_doc-lint-impactall.md
  - ?? chatgpt-history/2026-01-18_impactall-guard.md
  - ?? chatgpt-history/2026-01-18_openapi-pin-endpoints.md
  - ?? chatgpt-history/2026-01-18_pin-db-delivery.md
  - ?? chatgpt-history/2026-01-18_pin-delivery-cleanup.md
  - ?? chatgpt-history/2026-01-18_pin-implementation-ticket.md
  - ?? chatgpt-history/2026-01-18_pin-p0-implemented.md
  - ?? chatgpt-history/2026-01-18_pin-p1-implemented.md
  - ?? chatgpt-history/2026-01-18_pin-p2-implemented.md
  - ?? chatgpt-history/2026-01-18_pin-p2p3-backlog.md
  - ?? chatgpt-history/2026-01-18_pin-p3-implemented.md
  - ?? chatgpt-history/2026-01-18_pin-parameters.md
  - ?? chatgpt-history/2026-01-18_pin-phpunit-smoke.md
  - ?? chatgpt-history/2026-01-18_pin-rate-limit-cleanup.md
  - ?? chatgpt-history/2026-01-18_pin-release-checklist.md
  - ?? chatgpt-history/2026-01-18_pin-rest-samples.md
  - ?? chatgpt-history/2026-01-18_pin-runbook.md
  - ?? chatgpt-history/2026-01-18_pin-sonnet-review.md
  - ?? chatgpt-history/2026-01-18_pin-staging-deploy-smoke.md
  - ?? chatgpt-history/2026-01-18_pin-wp-stub.md
  - ?? chatgpt-history/2026-01-18_pin-wp-test-bootstrap.md
  - ?? chatgpt-history/2026-01-18_pseudo-id-details.md
  - ?? chatgpt-history/2026-01-18_sms-env-smoke.md
  - ?? chatgpt-history/2026-01-18_vonage-env-smoke.md
  - ?? chatgpt-history/GMAIL_OAUTH_SETUP.md
  - ?? chatgpt-history/image/
  - ?? cj-commission-detail.md
  - ?? conversation-summaries/100_conversation_summary.md
  - ?? conversation-summaries/101_conversation_summary.md
  - ?? conversation-summaries/102_conversation_summary.md
  - ?? conversation-summaries/103_conversation_summary.md
  - ?? conversation-summaries/104_conversation_summary.md
  - ?? conversation-summaries/105_conversation_summary.md
  - ?? conversation-summaries/106_conversation_summary.md
  - ?? conversation-summaries/107_conversation_summary.md
  - ?? conversation-summaries/108_conversation_summary.md
  - ?? conversation-summaries/109_conversation_summary.md
  - ?? conversation-summaries/110_conversation_summary.md
  - ?? conversation-summaries/111_conversation_summary.md
  - ?? conversation-summaries/112_conversation_summary.md
  - ?? conversation-summaries/113_conversation_summary.md
  - ?? conversation-summaries/114_conversation_summary.md
  - ?? conversation-summaries/115_conversation_summary.md
  - ?? conversation-summaries/116_conversation_summary.md
  - ?? conversation-summaries/117_conversation_summary.md
  - ?? conversation-summaries/118_conversation_summary.md
  - ?? conversation-summaries/119_conversation_summary.md
  - ?? conversation-summaries/120_conversation_summary.md
  - ?? conversation-summaries/121_conversation_summary.md
  - ?? conversation-summaries/122_conversation_summary.md
  - ?? conversation-summaries/123_conversation_summary.md
  - ?? conversation-summaries/124_conversation_summary.md
  - ?? conversation-summaries/125_conversation_summary.md
  - ?? conversation-summaries/126_conversation_summary.md
  - ?? conversation-summaries/127_conversation_summary.md
  - ?? conversation-summaries/128_conversation_summary.md
  - ?? conversation-summaries/129_conversation_summary.md
  - ?? conversation-summaries/130_conversation_summary.md
  - ?? conversation-summaries/131_conversation_summary.md
  - ?? conversation-summaries/132_conversation_summary.md
  - ?? conversation-summaries/133_conversation_summary.md
  - ?? conversation-summaries/134_conversation_summary.md
  - ?? conversation-summaries/135_conversation_summary.md
  - ?? conversation-summaries/136_conversation_summary.md
  - ?? conversation-summaries/137_conversation_summary.md
  - ?? conversation-summaries/138_conversation_summary.md
  - ?? conversation-summaries/139_conversation_summary.md
  - ?? conversation-summaries/140_conversation_summary.md
  - ?? conversation-summaries/141_conversation_summary.md
  - ?? conversation-summaries/142_conversation_summary.md
  - ?? conversation-summaries/143_conversation_summary.md
  - ?? conversation-summaries/144_conversation_summary.md
  - ?? conversation-summaries/145_conversation_summary.md
  - ?? conversation-summaries/146_conversation_summary.md
  - ?? conversation-summaries/147_conversation_summary.md
  - ?? conversation-summaries/148_conversation_summary.md
  - ?? conversation-summaries/149_conversation_summary.md
  - ?? conversation-summaries/150_conversation_summary.md
  - ?? conversation-summaries/151_conversation_summary.md
  - ?? conversation-summaries/152_conversation_summary.md
  - ?? conversation-summaries/153_conversation_summary.md
  - ?? conversation-summaries/154_conversation_summary.md
  - ?? conversation-summaries/155_conversation_summary.md
  - ?? conversation-summaries/156_conversation_summary.md
  - ?? conversation-summaries/157_conversation_summary.md
  - ?? conversation-summaries/158_conversation_summary.md
  - ?? conversation-summaries/159_conversation_summary.md
  - ?? conversation-summaries/160_conversation_summary.md
  - ?? conversation-summaries/161_conversation_summary.md
  - ?? conversation-summaries/162_conversation_summary.md
  - ?? conversation-summaries/163_conversation_summary.md
  - ?? conversation-summaries/164_conversation_summary.md
  - ?? conversation-summaries/165_conversation_summary.md
  - ?? conversation-summaries/166_conversation_summary.md
  - ?? conversation-summaries/167_conversation_summary.md
  - ?? conversation-summaries/168_conversation_summary.md
  - ?? conversation-summaries/169_conversation_summary.md
  - ?? conversation-summaries/170_conversation_summary.md
  - ?? conversation-summaries/171_conversation_summary.md
  - ?? conversation-summaries/172_conversation_summary.md
  - ?? conversation-summaries/173_conversation_summary.md
  - ?? conversation-summaries/174_conversation_summary.md
  - ?? conversation-summaries/175_conversation_summary.md
  - ?? conversation-summaries/176_conversation_summary.md
  - ?? conversation-summaries/177_conversation_summary.md
  - ?? conversation-summaries/178_conversation_summary.md
  - ?? conversation-summaries/179_conversation_summary.md
  - ?? conversation-summaries/17_conversation_summary.md
  - ?? conversation-summaries/180_conversation_summary.md
  - ?? conversation-summaries/181_conversation_summary.md
  - ?? conversation-summaries/182_conversation_summary.md
  - ?? conversation-summaries/183_conversation_summary.md
  - ?? conversation-summaries/184_conversation_summary.md
  - ?? conversation-summaries/185_conversation_summary.md
  - ?? conversation-summaries/186_conversation_summary.md
  - ?? conversation-summaries/187_conversation_summary.md
  - ?? conversation-summaries/188_conversation_summary.md
  - ?? conversation-summaries/189_conversation_summary.md
  - ?? conversation-summaries/18_conversation_summary.md
  - ?? conversation-summaries/190_conversation_summary.md
  - ?? conversation-summaries/191_conversation_summary.md
  - ?? conversation-summaries/192_conversation_summary.md
  - ?? conversation-summaries/193_conversation_summary.md
  - ?? conversation-summaries/194_conversation_summary.md
  - ?? conversation-summaries/195_conversation_summary.md
  - ?? conversation-summaries/196_conversation_summary.md
  - ?? conversation-summaries/197_conversation_summary.md
  - ?? conversation-summaries/198_conversation_summary.md
  - ?? conversation-summaries/199_conversation_summary.md
  - ?? conversation-summaries/19_conversation_summary.md
  - ?? conversation-summaries/200_conversation_summary.md
  - ?? conversation-summaries/201_conversation_summary.md
  - ?? conversation-summaries/202_conversation_summary.md
  - ?? conversation-summaries/203_conversation_summary.md
  - ?? conversation-summaries/204_conversation_summary.md
  - ?? conversation-summaries/205_conversation_summary.md
  - ?? conversation-summaries/206_conversation_summary.md
  - ?? conversation-summaries/207_conversation_summary.md
  - ?? conversation-summaries/208_conversation_summary.md
  - ?? conversation-summaries/209_conversation_summary.md
  - ?? conversation-summaries/20_conversation_summary.md
  - ?? conversation-summaries/210_conversation_summary.md
  - ?? conversation-summaries/211_conversation_summary.md
  - ?? conversation-summaries/212_conversation_summary.md
  - ?? conversation-summaries/213_conversation_summary.md
  - ?? conversation-summaries/214_conversation_summary.md
  - ?? conversation-summaries/215_conversation_summary.md
  - ?? conversation-summaries/216_conversation_summary.md
  - ?? conversation-summaries/217_conversation_summary.md
  - ?? conversation-summaries/218_conversation_summary.md
  - ?? conversation-summaries/219_conversation_summary.md
  - ?? conversation-summaries/21_conversation_summary.md
  - ?? conversation-summaries/220_conversation_summary.md
  - ?? conversation-summaries/221_conversation_summary.md
  - ?? conversation-summaries/222_conversation_summary.md
  - ?? conversation-summaries/223_conversation_summary.md
  - ?? conversation-summaries/224_conversation_summary.md
  - ?? conversation-summaries/225_conversation_summary.md
  - ?? conversation-summaries/226_conversation_summary.md
  - ?? conversation-summaries/226_core_proxy.md
  - ?? conversation-summaries/227_conversation_summary.md
  - ?? conversation-summaries/227_core_plan.md
  - ?? conversation-summaries/228_conversation_summary.md
  - ?? conversation-summaries/228_core_console_ui.md
  - ?? conversation-summaries/229_langfuse_todo.md
  - ?? conversation-summaries/229_memory_sync.md
  - ?? conversation-summaries/22_conversation_summary.md
  - ?? conversation-summaries/230_conversation_summary.md
  - ?? conversation-summaries/231_conversation_summary.md
  - ?? conversation-summaries/232_conversation_summary.md
  - ?? conversation-summaries/233_conversation_summary.md
  - ?? conversation-summaries/234_conversation_summary.md
  - ?? conversation-summaries/235_conversation_summary.md
  - ?? conversation-summaries/236_conversation_summary.md
  - ?? conversation-summaries/237_conversation_summary.md
  - ?? conversation-summaries/238_conversation_summary.md
  - ?? conversation-summaries/239_conversation_summary.md
  - ?? conversation-summaries/23_conversation_summary.md
  - ?? conversation-summaries/240_conversation_summary.md
  - ?? conversation-summaries/241_conversation_summary.md
  - ?? conversation-summaries/242_conversation_summary.md
  - ?? conversation-summaries/243_conversation_summary.md
  - ?? conversation-summaries/244_conversation_summary.md
  - ?? conversation-summaries/245_conversation_summary.md
  - ?? conversation-summaries/246_conversation_summary.md
  - ?? conversation-summaries/247_conversation_summary.md
  - ?? conversation-summaries/248_conversation_summary.md
  - ?? conversation-summaries/249_conversation_summary.md
  - ?? conversation-summaries/24_conversation_summary.md
  - ?? conversation-summaries/250_conversation_summary.md
  - ?? conversation-summaries/251_conversation_summary.md
  - ?? conversation-summaries/252_conversation_summary.md
  - ?? conversation-summaries/253_conversation_summary.md
  - ?? conversation-summaries/254_conversation_summary.md
  - ?? conversation-summaries/255_conversation_summary.md
  - ?? conversation-summaries/256_conversation_summary.md
  - ?? conversation-summaries/257_conversation_summary.md
  - ?? conversation-summaries/258_conversation_summary.md
  - ?? conversation-summaries/259_conversation_summary.md
  - ?? conversation-summaries/25_conversation_summary.md
  - ?? conversation-summaries/260_conversation_summary.md
  - ?? conversation-summaries/261_conversation_summary.md
  - ?? conversation-summaries/262_conversation_summary.md
  - ?? conversation-summaries/263_conversation_summary.md
  - ?? conversation-summaries/264_conversation_summary.md
  - ?? conversation-summaries/265_conversation_summary.md
  - ?? conversation-summaries/266_conversation_summary.md
  - ?? conversation-summaries/267_conversation_summary.md
  - ?? conversation-summaries/268_conversation_summary.md
  - ?? conversation-summaries/269_conversation_summary.md
  - ?? conversation-summaries/26_conversation_summary.md
  - ?? conversation-summaries/270_conversation_summary.md
  - ?? conversation-summaries/271_conversation_summary.md
  - ?? conversation-summaries/272_conversation_summary.md
  - ?? conversation-summaries/273_conversation_summary.md
  - ?? conversation-summaries/274_conversation_summary.md
  - ?? conversation-summaries/275_conversation_summary.md
  - ?? conversation-summaries/276_conversation_summary.md
  - ?? conversation-summaries/277_conversation_summary.md
  - ?? conversation-summaries/278_conversation_summary.md
  - ?? conversation-summaries/279_conversation_summary.md
  - ?? conversation-summaries/27_conversation_summary.md
  - ?? conversation-summaries/280_conversation_summary.md
  - ?? conversation-summaries/281_conversation_summary.md
  - ?? conversation-summaries/282_conversation_summary.md
  - ?? conversation-summaries/283_conversation_summary.md
  - ?? conversation-summaries/284_conversation_summary.md
  - ?? conversation-summaries/285_conversation_summary.md
  - ?? conversation-summaries/286_conversation_summary.md
  - ?? conversation-summaries/287_conversation_summary.md
  - ?? conversation-summaries/288_conversation_summary.md
  - ?? conversation-summaries/289_conversation_summary.md
  - ?? conversation-summaries/28_conversation_summary.md
  - ?? conversation-summaries/290_conversation_summary.md
  - ?? conversation-summaries/291_conversation_summary.md
  - ?? conversation-summaries/292_conversation_summary.md
  - ?? conversation-summaries/293_conversation_summary.md
  - ?? conversation-summaries/294_conversation_summary.md
  - ?? conversation-summaries/295_conversation_summary.md
  - ?? conversation-summaries/296_conversation_summary.md
  - ?? conversation-summaries/297_conversation_summary.md
  - ?? conversation-summaries/298_conversation_summary.md
  - ?? conversation-summaries/299_conversation_summary.md
  - ?? conversation-summaries/29_conversation_summary.md
  - ?? conversation-summaries/300_conversation_summary.md
  - ?? conversation-summaries/301_conversation_summary.md
  - ?? conversation-summaries/302_conversation_summary.md
  - ?? conversation-summaries/303_conversation_summary.md
  - ?? conversation-summaries/304_conversation_summary.md
  - ?? conversation-summaries/305_conversation_summary.md
  - ?? conversation-summaries/306_conversation_summary.md
  - ?? conversation-summaries/307_conversation_summary.md
  - ?? conversation-summaries/308_conversation_summary.md
  - ?? conversation-summaries/309_conversation_summary.md
  - ?? conversation-summaries/30_conversation_summary.md
  - ?? conversation-summaries/310_conversation_summary.md
  - ?? conversation-summaries/311_conversation_summary.md
  - ?? conversation-summaries/312_conversation_summary.md
  - ?? conversation-summaries/313_conversation_summary.md
  - ?? conversation-summaries/314_conversation_summary.md
  - ?? conversation-summaries/315_conversation_summary.md
  - ?? conversation-summaries/316_conversation_summary.md
  - ?? conversation-summaries/317_conversation_summary.md
  - ?? conversation-summaries/318_conversation_summary.md
  - ?? conversation-summaries/319_conversation_summary.md
  - ?? conversation-summaries/31_conversation_summary.md
  - ?? conversation-summaries/320_conversation_summary.md
  - ?? conversation-summaries/321_conversation_summary.md
  - ?? conversation-summaries/322_conversation_summary.md
  - ?? conversation-summaries/323_conversation_summary.md
  - ?? conversation-summaries/324_conversation_summary.md
  - ?? conversation-summaries/325_conversation_summary.md
  - ?? conversation-summaries/326_conversation_summary.md
  - ?? conversation-summaries/327_conversation_summary.md
  - ?? conversation-summaries/328_conversation_summary.md
  - ?? conversation-summaries/329_conversation_summary.md
  - ?? conversation-summaries/32_conversation_summary.md
  - ?? conversation-summaries/330_conversation_summary.md
  - ?? conversation-summaries/331_conversation_summary.md
  - ?? conversation-summaries/332_conversation_summary.md
  - ?? conversation-summaries/333_conversation_summary.md
  - ?? conversation-summaries/334_conversation_summary.md
  - ?? conversation-summaries/335_conversation_summary.md
  - ?? conversation-summaries/336_conversation_summary.md
  - ?? conversation-summaries/337_conversation_summary.md
  - ?? conversation-summaries/338_conversation_summary.md
  - ?? conversation-summaries/339_conversation_summary.md
  - ?? conversation-summaries/33_conversation_summary.md
  - ?? conversation-summaries/340_conversation_summary.md
  - ?? conversation-summaries/341_conversation_summary.md
  - ?? conversation-summaries/342_conversation_summary.md
  - ?? conversation-summaries/343_conversation_summary.md
  - ?? conversation-summaries/344_conversation_summary.md
  - ?? conversation-summaries/345_conversation_summary.md
  - ?? conversation-summaries/34_conversation_summary.md
  - ?? conversation-summaries/35_conversation_summary.md
  - ?? conversation-summaries/36_conversation_summary.md
  - ?? conversation-summaries/37_conversation_summary.md
  - ?? conversation-summaries/38_conversation_summary.md
  - ?? conversation-summaries/39_conversation_summary.md
  - ?? conversation-summaries/40_conversation_summary.md
  - ?? conversation-summaries/41_conversation_summary.md
  - ?? conversation-summaries/42_conversation_summary.md
  - ?? conversation-summaries/43_conversation_summary.md
  - ?? conversation-summaries/44_conversation_summary.md
  - ?? conversation-summaries/45_conversation_summary.md
  - ?? conversation-summaries/46_conversation_summary.md
  - ?? conversation-summaries/47_conversation_summary.md
  - ?? conversation-summaries/48_conversation_summary.md
  - ?? conversation-summaries/49_conversation_summary.md
  - ?? conversation-summaries/50_conversation_summary.md
  - ?? conversation-summaries/51_conversation_summary.md
  - ?? conversation-summaries/52_conversation_summary.md
  - ?? conversation-summaries/53_conversation_summary.md
  - ?? conversation-summaries/54_conversation_summary.md
  - ?? conversation-summaries/55_conversation_summary.md
  - ?? conversation-summaries/56_conversation_summary.md
  - ?? conversation-summaries/57_conversation_summary.md
  - ?? conversation-summaries/58_conversation_summary.md
  - ?? conversation-summaries/59_conversation_summary.md
  - ?? conversation-summaries/60_conversation_summary.md
  - ?? conversation-summaries/61_conversation_summary.md
  - ?? conversation-summaries/62_conversation_summary.md
  - ?? conversation-summaries/63_conversation_summary.md
  - ?? conversation-summaries/64_conversation_summary.md
  - ?? conversation-summaries/65_conversation_summary.md
  - ?? conversation-summaries/66_conversation_summary.md
  - ?? conversation-summaries/67_conversation_summary.md
  - ?? conversation-summaries/68_conversation_summary.md
  - ?? conversation-summaries/69_conversation_summary.md
  - ?? conversation-summaries/70_conversation_summary.md
  - ?? conversation-summaries/71_conversation_summary.md
  - ?? conversation-summaries/72_conversation_summary.md
  - ?? conversation-summaries/73_conversation_summary.md
  - ?? conversation-summaries/74_conversation_summary.md
  - ?? conversation-summaries/75_conversation_summary.md
  - ?? conversation-summaries/76_conversation_summary.md
  - ?? conversation-summaries/77_conversation_summary.md
  - ?? conversation-summaries/78_conversation_summary.md
  - ?? conversation-summaries/79_conversation_summary.md
  - ?? conversation-summaries/80_conversation_summary.md
  - ?? conversation-summaries/81_conversation_summary.md
  - ?? conversation-summaries/82_conversation_summary.md
  - ?? conversation-summaries/83_conversation_summary.md
  - ?? conversation-summaries/84_conversation_summary.md
  - ?? conversation-summaries/85_conversation_summary.md
  - ?? conversation-summaries/86_conversation_summary.md
  - ?? conversation-summaries/87_conversation_summary.md
  - ?? conversation-summaries/88_conversation_summary.md
  - ?? conversation-summaries/89_conversation_summary.md
  - ?? conversation-summaries/90_conversation_summary.md
  - ?? conversation-summaries/91_conversation_summary.md
  - ?? conversation-summaries/92_conversation_summary.md
  - ?? conversation-summaries/93_conversation_summary.md
  - ?? conversation-summaries/94_conversation_summary.md
  - ?? conversation-summaries/95_conversation_summary.md
  - ?? conversation-summaries/96_conversation_summary.md
  - ?? conversation-summaries/97_conversation_summary.md
  - ?? conversation-summaries/98_conversation_summary.md
  - ?? conversation-summaries/99_conversation_summary.md
  - ?? docs/affiliate-integration-runbook.md
  - ?? docs/cj-transactions.md
  - ?? docs/nav-online.md
  - ?? docs/pin-error-codes.md
  - ?? docs/pin-sequence-diagram.md
  - ?? docs/pin-sms-runbook.md
  - ?? docs/pin-sonnet-review.md
  - ?? export-coupons.csv
  - ?? fixtures/
  - ?? guard-actions.md
  - ?? image/
  - ?? impact-bridge-local/
  - ?? impact-hub-system-v1.3.md
  - ?? impactshop-baseline-2025-11-02.md
  - ?? impactshop-baseline-2026-01-05.md
  - ?? impactshop-notes
  - ?? mu-plugins/impact-ledger.php
  - ?? mu-plugins/impactshop-card-request.js
  - ?? mu-plugins/impactshop-card-request.php
  - ?? ngo-leaderboard.html
  - ?? ngo_codes.csv
  - ?? package-lock.json
  - ?? package.json
  - ?? phpunit.xml
  - ?? scripts/ads-fetch-mock.js
  - ?? scripts/cj-commission-smoke.sh
  - ?? scripts/coupon-harvester-smoke.sh
  - ?? scripts/coupon_harvester_pipeline.py
  - ?? scripts/diagnostics/
  - ?? scripts/generate_shops_whitelist.py
  - ?? scripts/impact-fragment-prewarm.sh
  - ?? scripts/impact-ledger-import.php
  - ?? scripts/impact-publish-status.php
  - ?? scripts/impact-publish-worker.php
  - ?? scripts/install-ai-agent-guard-cron.sh
  - ?? scripts/install-fragment-prewarm-cron.sh
  - ?? scripts/sync-mu-and-health.sh
  - ?? scripts/token-health-guard.php
  - ?? scripts/wallet/
  - ?? shop-donation-cards.html
  - ?? tests/fixtures/
  - ?? tests/phpunit/ImpactshopIdentityPinTest.php
  - ?? tests/wordpress-tests-lib/
  - ?? tests/wordpress/
  - ?? tools/
  - ?? types/
  - ?? vendor/bin/php-parse
  - ?? vendor/bin/phpunit
  - ?? vendor/brick/
  - ?? vendor/composer/platform_check.php
  - ?? vendor/doctrine/
  - ?? vendor/dompdf/
  - ?? vendor/guzzlehttp/
  - ?? vendor/laminas/
  - ?? vendor/lcobucci/
  - ?? vendor/masterminds/
  - ?? vendor/myclabs/
  - ?? vendor/nikic/
  - ?? vendor/phar-io/
  - ?? vendor/phenx/
  - ?? vendor/phpunit/
  - ?? vendor/psr/clock/
  - ?? vendor/psr/http-client/
  - ?? vendor/psr/http-factory/
  - ?? vendor/psr/http-message/
  - ?? vendor/ralouphie/
  - ?? vendor/ramsey/
  - ?? vendor/sabberworm/
  - ?? vendor/sebastian/cli-parser/
  - ?? vendor/sebastian/code-unit-reverse-lookup/
  - ?? vendor/sebastian/code-unit/
  - ?? vendor/sebastian/comparator/
  - ?? vendor/sebastian/complexity/
  - ?? vendor/sebastian/environment/
  - ?? vendor/sebastian/exporter/
  - ?? vendor/sebastian/global-state/
  - ?? vendor/sebastian/lines-of-code/
  - ?? vendor/sebastian/object-enumerator/
  - ?? vendor/sebastian/object-reflector/
  - ?? vendor/sebastian/recursion-context/
  - ?? vendor/sebastian/resource-operations/
  - ?? vendor/sebastian/type/
  - ?? vendor/sebastian/version/
  - ?? vendor/theseer/
  - ?? vendor/vonage/
  - ?? vendor/yoast/
  - ?? wp-content/mu-plugins/0-impact-organic-insights.php
  - ?? wp-content/mu-plugins/ai-agent-proxy.php
  - ?? wp-content/mu-plugins/image/
  - ?? wp-content/mu-plugins/impact-ads-endpoint.php
  - ?? wp-content/mu-plugins/impact-ads-provider.php
  - ?? wp-content/mu-plugins/impact-ai-metrics.php
  - ?? wp-content/mu-plugins/impact-ledger-approval.php
  - ?? wp-content/mu-plugins/impact-ledger-migration.php
  - ?? "wp-content/mu-plugins/impact-ledger-pdf 2.php"
  - ?? wp-content/mu-plugins/impact-ledger-pdf.php
  - ?? wp-content/mu-plugins/impact-ledger-report.php
  - ?? wp-content/mu-plugins/impact-ledger-sync.php
  - ?? wp-content/mu-plugins/impact-organic-insights.php
  - ?? wp-content/mu-plugins/impact-publisher-brand-safety-admin.php
  - ?? wp-content/mu-plugins/impact-publisher-brand-safety.php
  - ?? wp-content/mu-plugins/impact-publisher-migration.php
  - ?? wp-content/mu-plugins/impact-publisher-token.php
  - ?? wp-content/mu-plugins/impact-social-mvp-flag.php
  - ?? wp-content/mu-plugins/impact-sum-sticky-ui.php
  - ?? wp-content/mu-plugins/impactshop-go-bridge.php
  - ?? wp-content/mu-plugins/impactshop-health-endpoint.php
  - ?? wp-content/mu-plugins/impactshop-identity-pin-cron.php
  - ?? wp-content/mu-plugins/impactshop-identity-pin-metrics.php
  - ?? wp-content/mu-plugins/impactshop-identity-pin-migration.php
  - ?? wp-content/mu-plugins/impactshop-identity-pin-qr-quickchart.php
  - ?? wp-content/mu-plugins/impactshop-identity-pin-sms-vonage.php
  - ?? wp-content/mu-plugins/impactshop-identity-pin.php
  - ?? wp-content/mu-plugins/impactshop-impi-chat.php
  - ?? wp-content/mu-plugins/impactshop-netflix-shortcodes.php
  - ?? wp-content/mu-plugins/impactshop-rest-coupons.php
  - ?? wp-content/mu-plugins/impactshop-style-fix.php
  - ?? wp-content/mu-plugins/impactshop-style-reset.php
  - ?? wp-content/mu-plugins/impactshop-sum-pack.php

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
./.git/REBASE_HEAD
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
./.venv/bin
./.venv/lib
./.venv/pyvenv.cfg
./.venv/share
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

