# Sharity D1 donation package prices (source only)

Approved scope: direct net100% NGO and Amplifier net80/20, fixed package votes,
no further multiplier. D1 supplies prices only; no payment, wallet, ledger,
NGO account activation, legacy vote-purchase alteration or SDK yet.

Coherence/risk: new sharity-ngo-donations.php reads amplifier-packages.json and
existing validated MNB cache. GET /sharity/v1/donations/packages reads only.
Existing sharity_support_fx_refresh hook priority20 may publish one immutable
weekly option after refresh, only with sharity_donation_prices_enabled='1'.
Default off; no cron installation, HTTP provider call or migration. Invalid or
stale data fails closed for foreign prices; fixed HUF remains. First valid
cache within a Budapest week creates that week's price book; failed Monday
refresh leaves foreign prices unavailable until recovery, then frozen to next
Monday. No republishing/repricing current books. Package fees/net are never
computed from indicative rates.

Bastion extension before mutation: add only the PHP module and JSON manifest to
existing guard-config/hash inventory, update config self-hash and two checksum
sidecars. Existing unrelated hashes remain exact. No guard code/policy weakening.
Git HEAD 71e934800 is the metadata backup; no live file changed. Rollback own
commits or prior exact-file bytes; options preserved. Missing JSON makes read
unavailable and publishes nothing. Deploy JSON before loader; GET readback.

Tests: php tests/sharity-donation-prices.test.php: 33 checks passed, real existing
money/FX validators with isolated WP options. Lint passed. Covers five packages,
minor units, half-up/minimum/overflow, malformed/mixed/stale/future FX, freeze,
Monday expiry, DST, tampered book, default-off and no-write public GET.
No real DB concurrency, cron, staging or production acceptance is claimed.
Independent D1 implementation review: no blocking finding; separate A amount
field aria relationship improvement accepted. No duplicate product tests by reviewer.

Manual release checklist (pending): publish disabled files; GET HUF-only; enable
price publication only after validated FX source; run existing refresh hook;
read EUR/USD/GBP book version and expiry; repeated refresh preserves prices;
next Monday unavailable until new valid book. In A preview check HU/EN/DE/FR at
320/375/desktop and 200% zoom, explicit payment currency, full amount, fixed
package votes. Preview always disables quote creation until D2; no checkout,
auto-vote, point change or guaranteed multiplier. Live design acceptance pending.

SDK later: isolated lock build, private artifact staging, digest/manifest, reject
absolute/traversing/link members, absent versioned SDK destination, loader last
via existing exact-file release. No production Composer/shared vendor changes.

Hermes031 drafted A UI only; N pricing authored by Codex. One completed call,
14551 tokens, estimated USD0.001997177 (not invoice), no retry. Codex actual
cost unknown. Usage/integration/repair status recorded in Secretary.

Protected files touched (approved additive inventory only): docs/bastion-guard-status.md, docs/impactshop-guard-config.json, docs/impactshop-guard-config.sha256, docs/impactshop-guard-hashes.json, docs/impactshop-guard-hashes.sha256. Smoke: real existing guard checker and six negative/checksum cases passed. Existing pin diff restricted to config and its checksum; two new own files added. Rollback: own metadata commit to 71e934800 bytes, no runtime operation.

## D2 quote preparation — before modification

Additive own-module change: authenticated POST donations/quote and explicit CLI quote-schema. Reuse canonical Web Session authority including owner-grant revocation. Frozen price/account snapshot, exact minor amounts, owner-scoped unique replay key; no Stripe/payment/wallet/fund writer. Existing price GET and refresh remain unchanged. Risks: wrong units, owner leak, replay conflict/uncertain insert, malformed schema. Tests cover these contracts with isolated WP transport; actual MySQL race/schema acceptance remains required before activation. No existing Impact Challenge code touched. Backup/rollback: own release file only, disable new quotes first; retain immutable quote rows. UI checklist after future release: disabled modes, no automatic checkout/votes, same selection/price; no live UI check performed now.

### D2a verification and source-only release boundary

Protected files touched additionally: docs/impactshop-guard-hashes.json and docs/impactshop-guard-hashes.sha256. Only own donation PHP pin changes; package JSON and unrelated pins unchanged. No new guard or rule relaxation.72 isolated tests+33 prices,18 real disposable MariaDB checks and independent review (stale readiness fixed). CLI schema verifies ASCII binary IDs, InnoDB and exact unique topology. Manual future UI checklist: donation preview remains disabled; language/payment currency changes create no request; account data never shown; quote expiry/conflict must be explained when D2 client connects. Current checkout/status BFF has no corresponding backend writer yet. No live UI, financial or release acceptance. Hermes033 draft substantially repaired; no retry. Source rollback is own module/hash only, quote rows retained.
