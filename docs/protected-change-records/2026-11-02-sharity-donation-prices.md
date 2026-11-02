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

## D2b Stripe adapter — before modification

Approved additive adapter and isolated SDK lock only; no endpoint, loader activation, account change, payment or schema write. Risks are amount/currency drift, wrong connected account, automatic retries after uncertain results and treating Checkout return as financial proof. Hermes034 drafts adapter/tests; Codex independently verifies the official SDK and integrates. Existing quote, price, point and wallet paths remain unchanged. Restore only these new source files/own inventory changes for rollback; no live data exists for this package. The future durable order writer must persist its request before one provider attempt and resolve unknown outcomes by readback. SDK artifact belongs to a new version directory, never the shared vendor. Runtime loading and live validation remain later release work.

### D2b SDK build and provider contract

The dedicated `wp-content/mu-plugins/sharity-ngo-donations/composer.json` and lock pin official `stripe/stripe-php` 22.1.0, source commit `24ffd4c5708bdd490362baedb1df8efe4bf76484`, API `2026-10-28.endive`. Only this dependency is resolved; scripts/plugins disabled, PHP resolution baseline8.1. Local proof uses PHP8.4.25; production PHP/extensions and isolated load order still need readback. SDK files are in own ignored `tmp/sharity-donation-sdk/vendor`, not committed or loaded by WordPress. If another plugin has already loaded a different global Stripe SDK, donation startup must fail closed rather than replace that plugin's dependency.

Local dependency preparation used Composer2.11.1, verified against the [official download](https://getcomposer.org/download/) SHA256 `6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c`. Reproduce with `composer install --no-dev --no-plugins --no-scripts --prefer-dist --no-interaction` from the isolated manifest directory and an explicitly own `COMPOSER_VENDOR_DIR`, home and cache. Do not use update for release. Build the final version directory from the exact lock on a compatible build host; verify extensions with `composer check-platform-reqs --no-dev`. The release must include lock, vendor and file manifest with artifact SHA, never credentials. No production Composer or shared vendor change.

Official references checked2026-11-02: [Checkout create](https://docs.stripe.com/api/checkout/sessions/create.md), [direct charges](https://docs.stripe.com/connect/direct-charges.md?platform=web&ui=stripe-hosted), [idempotency](https://docs.stripe.com/api/idempotent_requests). Connected-account scope is required for both creation and readback. Checkout's adaptive pricing inherits dashboard settings unless explicitly disabled; fixed donation currency requires `adaptive_pricing.enabled=false`. Expiry must be30minutes–24hours from session creation; accepted request parameters cannot be silently rewritten after uncertainty. SDK retries must be zero. Direct application fee must explicitly be zero. A Checkout return/completed status is not settlement, net split, vote credit or payout evidence.

Stripe connector currently requires reauthentication. Public documentation and local offline SDK tests remain usable; no real Stripe call, account verification or sandbox acceptance is inferred.

## 2026-11-02 — D2b Stripe Checkout-adapter, csak forrás

Elkülönített hivatalos SDK22.1.0/lock, API2026-10-28.endive. Új, még be nem töltött adapter: pontos minorösszeg és deviza, kapcsolt NGO-account, közvetlen0%platformdíj, Amplifier ideiglenes20%bruttó alaprész (végső nettó80/20 egyeztetés később). Automatikus devizaárazás és SDK-retry kikapcsolva; változatlan kérés/idempotenciakulcs, szűrt visszaolvasás, Checkout-státusz nem pénzügyi bizonyíték. Új endpoint, rendelésírás, loader, webhook, séma, szavazat vagy aktiválás nincs.

197 valódi SDK-s, helyettesített HTTP-válaszos ellenőrzés,72 meglévő quote+33 árteszt és PHP lint sikeres. Lockazonos install dry-run és helyi PHP8.4.25/curl/json/mbstring megfelel. Composer validate manifest/lock rendben, de strict módban figyelmeztetés: nincs megadott projektlicenc és szándékosan pontos SDK-verzió; ezt nem nevezzük warning-free PASS-nak. Független review két javítása: történeti Amplifier-visszaolvasás változatlan v1-policyből, örökölt StripeContext elutasítása. Javítások visszaolvasva, további scoped blokkoló nincs. Nincs sandbox/live API vagy aktuális éles/UI elfogadás; Stripe connector újrahitelesítést kér.

Hermes034:1 hívás,5031 input+8203 output, becsült0,001950767USD, nem számla. Hibás pénz-/szavazatszabály és SDK-kezelés miatt a javaslat elvetve; Codex készítette a végleges adaptert/tesztet. Nincs ismétlés, tényleges Codex/reviewer költség ismeretlen.

Következő: tartós tulajdonoshoz kötött rendelés/kísérlet és egyszeri küldési igényfoglalás, privát státusz, webhook/pénzügyi egyeztetés; utána D3/D4. A korábbi72/33 teszt nem igazol Stripe-párhuzamosságot. Rollback saját új fájlokra korlátozott, meglévő quote/pénzügyi adatok megőrzendők. Kézi UI/release ellenőrzés továbbra függő. Részletek: docs/protected-change-records/2026-11-02-sharity-donation-prices.md.

Bástya: csak a három saját új fájl kerül a meglévő config/hash inventoryba, config önhash és checksumoldalkocsik frissülnek. Régi donation PHP és manifest, valamint minden idegen pin változatlan. Kézi checklist a későbbi staginghez: UI továbbra inaktív; quote nem indít fizetést; meglévő árlista olvasható; közös SDK/WordPress-funkciók változatlanok. Adapter betöltése előtt verzió/extension ellenőrzés, SDK-ütközéskor az új ág marad zárva.
