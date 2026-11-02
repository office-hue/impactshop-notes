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

## D2c — kezdés, tartós rendelés és egyszeri küldés

Jóváhagyott D2 folytatás a saját f4badb83 alapról. Új orders.php: owner-bound immutable rendelés, unique quote/kulcs, tartós egyszeri küldési foglalás, privát státusz. Kockázat: két párhuzamos indítás/veszett DB-ACK duplafizetést okozhat; ezért tényleges MariaDB-versenyteszt szükséges. Új endpoint/aktiválás/sémafuttatás nincs; meglévő pont-, szavazat-, adományalap és SDK változatlan. Hermes035 első vázlat, Codex integráció és elkülönített DB-teszt. Rollback csak saját új fájl/inventory, tartós order/quote-adat megmarad. Manuális UI-checklist későbbi kiadáskor: továbbra inaktív választó, ár- és nyelvváltás nem indít pénzmozgást.

## 57. D2c — tartós rendelés és egyszeri fizetési kísérlet (2026-11-02)

Saját A cfb23edf2 / N f4badb83 alapról. Új N `wp-content/mu-plugins/sharity-ngo-donations/orders.php` és `tests/sharity-donation-orders-mysql.test.php`. Belső, még be nem töltött tároló; nincs új route, loader, CLI-regisztráció vagy éles sémafrissítés. Az összes publikus hívó majd a kanonikus Web Session authoritytől kapja a subject_refet, nem böngészős owner mezőből.

Egy ajánlatból egy változatlan rendelés készül, egyedi quote- és owner/kulcs-korláttal. Az elfogadott összeg, deviza, account, policy, nyelv és teljes SDK-kérés rögzített. Azonos kérés visszaolvasható, eltérő tartalom konfliktus. Új rendeléshez és küldéshez érvényes aktuális accountkötés, határok és bekapcsolt saját kapcsoló kell; történeti státusz ezek kikapcsolása után is olvasható. A quote lejáratát az INSERT SELECT ismét ellenőrzi.

Állapotok: prepared → attempted → session_recorded. A külső POST előtt tartós, egyszeri CAS-foglalás történik; kizárólag biztosan egy módosított sor jogosít küldésre. Elveszett commit-nyugta esetén visszaolvasás és reconciliation_required, nem újabb POST vagy új kulcs. A külső hívás előtt az autocommit és a tranzakción kívüli állapot újra ellenőrzött. A provider eredmény mentése is visszaolvasott és egyszeri; a sessionazonosító egyedi. A privát státusz nem ad accountot, tulajdonosazonosítót, envelope-ot vagy nyers hibát; lejárt checkoutlinket elrejt. payment_verified mindig false: a Checkout-állapot továbbra nem pénzügyi bizonyíték.

A külön meghívandó sémafüggvény csak hiányzó táblát hozhat létre, pontos InnoDB/mező/collation/index ellenőrzéssel. Aktív tranzakcióban nem fut; hibás ellenőrzésnél readiness nincs. Nem alakít át inkompatibilis táblát és nem töröl meglévő adatot. A tényleges CLI-bekötés következő csomag.

Tényleges ellenőrzés:77 sikeres vizsgálat MariaDB10.11.19-en, külön mysqli-kapcsolatokkal és kikényszerített párhuzamos olvasásokkal. Azonos/különböző idempotenciakérés, egy quote több kulccsal, párhuzamos küldés, owner-szűrés, lejárat, disable utáni olvasás, insert/claim/result elveszett ACK és commit utáni kivétel, provider-bizonytalanság, hibás URL, külső tranzakció és hiányzó unique index szerepel. A valódi rögzített Stripe SDK transportja helyettesített; nincs sandbox/live hívás. A már cache-elt image-ből loopback/tmpfs konténer készült, majd eltávolítását visszaolvastuk. PHP lint és teljes meglévő Bástya inventory +6 negatív/hash eset sikeres. Az érintetlen adapter197, quote72 és ár33 korábbi eredménye újrahasznált, nem új futás.

Független gpt-6.1-sol/high review: nincs nyitott hiba a belső tároló scope-jában. A párhuzamos worker-teszt két külön hibamentes eredményt követelő assertja javítva, séma tranzakcióvédelme és hiányos envelope szűrése visszaolvasva. Reviewer nem futtatott újra teszteket. Végső SHA256: orders.php f80dc75cf34ad319ef45244e05e0cdd24aeaf4535367c468f60b098642367cba; teszt eeafdc5cef0e046db854f1c3cd375fde4d6494ede8e596a42ce352ec99cf3697. Csak az új saját orders.php került a meglévő védett inventoryba; config önhash/oldalkocsik frissültek, idegen pinek változatlanok.

Hermes035: execution50fd50d0-78f7-46a7-a813-28171979514c,1 hívás,11204 input +7803 output =19007, becsült0,00243971USD, nem számla. Csak a séma vázlatát vettük át; a működést Codex újradolgozta. Javításigény: üres ownerrel olvasás, önmagára hivatkozó request-digest, kéretlen HTTP/auth-helykitöltő, nem használt provider/session mezők, hiányos eredményvalidálás, aktuális accountkötés és bizonytalan írás visszaolvasása, autocommit-védelem. Nincs retry vagy új szolgáltatói költési keret; megtakarítást nem állítunk. Codex/reviewer tényleges költsége ismeretlen.

Stripe-hozzáférés pontosítása: a korábbi connector újrahitelesítési hibája dokumentációhívásnál jelentkezett, nem bizonyítja Arnold Stripe-fiókjának hibáját. Arnold bejelentkezett Chrome-hozzáférést ajánlott. Most a böngészős host nem volt elérhető, a natív Chrome-olvasás egyszer időtúllépett. Fiókot nem olvastunk/módosítottunk; ez a helyi forrásmunkát nem blokkolta.

Következő D2: verziózott SDK-loader és explicit order-schema CLI, kanonikus auth mögötti checkout/status backend és route-szerződés, ellenőrzött account/díj/felelősség, hiteles webhook és bizonytalan kísérletek visszaolvasási rendezése, tényleges díj/refund/dispute egyeztetés. Az aktuális BFF checkout pontosan quote_id-t fogad: a nyelv átadását szerveroldali szerződéssel kell rendezni, nem hallgatólagos új kliensmezővel. Ezután D3 fizetett szavazat/alap, D4 előzmény és legacy belépő. VB7/Sharing12 és teljes pénzügyi elfogadás nyitott. A korábbi két tervaudit változatlanul újrahasználható.

Forráscsomag és célzott tesztek elkészültek; commit/push SHA a Secretaryben és a meglévő draft PR-ekben. Merge, telepítés, aktiválás, élő UI- és pénzügyi elfogadás nem történt. Nem nyitottunk mainablakot. A §54 kiadási út marad: függőség → kikapcsolt backend → explicit séma → olvasó/felület → ellenőrzött NGO-aktiválás. Rollback csak saját fájl/kapcsoló/inventory; quote-, rendelés- és pénzügyi sorok megmaradnak. A bizonytalan attempted állapot nem állítható vissza preparedre. Későbbi kézi UI-checklist: a kapcsolók nélkül a választó inaktív; nyelv/deviza nem indít checkoutot; csak saját státusz olvasható; bizonytalan válasz nem indít új fizetést.

Megőrzött átadási hivatkozás: SUBSTACK-HANDOVER.md; ebben a fejlesztési csomagban nem szerkesztési/publikálási feladat.

## D2d — kezdés: hitelesített checkout/státusz és explicit séma

Saját N d60239aa / A78912de74. Új belső HTTP-modul kapcsolja a kanonikus Web Session authot a már review-zott rendeléstárolóhoz; külön verziózott SDK-loader csak új prepared küldésnél. Status/replay nem kér kulcsot vagy SDK-t, kikapcsolás után is működik. Browser body továbbra quote_id, validált nyelvheaderből BFF upstream locale. Új schema csak explicit CLI; nincs pénzügyi/szavazati írás vagy aktiválás. Hermes036 HTTP-modul/teszt első draft, Codex loader/BFF integráció. Konkrét kockázat: idegen rendelés kiolvasása, bizonytalan kérés újraküldése, közös Stripe SDK ütközése; célzott auth/replay/loader tesztek és független review szükséges. Előállapot Git HEAD, rollback csak saját modul/loader/route/inventory, quote/order és idegen munka megmarad. Későbbi kézi checklist: inaktív adományozási oldal, nyelvváltás nem indít checkoutot, másik tulajdonos státusza elutasítva, disable után saját történeti státusz elérhető.

## 58. D2d — hitelesített checkout/státusz, SDK-loader és séma CLI (2026-11-02)

Kiindulás N d60239aa / A78912de74, tiszta saját worktree-k; mainművelet nincs. N új `sharity-ngo-donations/http.php` a kanonikus Web Session authority mögött regisztrálja a POST donations/checkout és GET donations/status/{id} végpontot. Kizárólag a hiteles subject_ref jut a már review-zott rendeléstárolóba. Status ID csak URL-paraméterből olvasható; query/body nem írja felül. Minden válasz privát,no-store, hibák szűrtek. A status/replay nem kér SDK-t, kulcsot vagy szolgáltatói hívást, kikapcsolás után is olvasható. Bizonytalan eredmény202 reconciliation_required; nincs újraküldés vagy payment_verified állítás.

A browser checkout body továbbra pontosan {quote_id}. A BFF az opcionális x-sharity-donation-language fejlécből kizárólag hu/en/de/fr értéket fogad, hiány esetén hu; az upstream szerződés pontosan {quote_id,locale}. Más böngészős fejléc, pénzügyi mező vagy account nem kerül továbbításra. A rendelésben a nyelv is befagy: bizonytalan kérés után ugyanaz a kulcs és nyelv használandó. A form továbbra inaktív, nem indít automatikusan quote-ot/checkoutot; a későbbi UI-bekötés ezt a headert a memóriában választott nyelvből küldi, blokkolt cookie mellett is.

Új `runtime.php`: csak új prepared küldés előtt tölti be a fix `sharity-ngo-donations/sdk/stripe-22.1.0/vendor/autoload.php` artifactot, a privát wp-config SHARITY_DONATIONS_STRIPE_KEY konstanssal. Nincs kulcsbeírás/lekérés ebben a csomagban, nincs env-fallback vagy production Composer. Idegen Stripe-osztály/eltérő SDK-verzió elutasítva, globális kulcs nem módosul. A main donation-modul plugins_loaded hookja csak teljes saját függőségkészlet esetén tölti be a definíciókat; részleges telepítés megőrzi a korábbi ár/quote útvonalat. A loader utolsóként telepítendő. Új `wp sharity donations order-schema --apply` parancs kizárólag a meglévő additív, ellenőrzött sémafüggvényt hívja, kapcsolót nem aktivál. HTTP/cron/load alatt nincs DDL.

Tényleges teszt: A59 route-teszt és célzott TypeScript sikeres. N207 izolált HTTP-határteszt,7 friss PHP-folyamatban futó loader-szcenárió a meglévő lockazonos valódi SDK saját ideiglenes másolatával,72 quote+33 árregresszió és PHP lint sikeres. Az előző MariaDB-harness most HTTP→valódi store→valódi SDK/helyettesített HTTP lánccal bővült:99 ellenőrzés, ebből22 új, négy nyelv, saját/idegen tulajdonos, kikapcsolt replay, bizonytalan202 és nulla újraküldés. A cache-elt MariaDB10.11.19 loopback/tmpfs konténer eltávolítva és visszaolvasva; nincs image-letöltés, élő DB vagy sandbox/live Stripe-hívás. Korábbi197 adapterteszt újrahasználható, adapter/orders termékbyte-ok változatlanok. Bástya teljes meglévő inventory+6 hash-eset sikeres; csak két új saját modul, a donation loader és config önhash/oldalkocsik változtak.

Független gpt-6.1-sol/high read-only review: nincs konkrét blokkoló a D2d scope-ban; auth, URL-paraméter, owner, replay, SDK-proveniencia és részleges kiadás vizsgálva. Reviewer nem futtatott tesztet/providerhívást; fenti futásokat Codex végezte. A két korábbi pénzügyi tervaudit változatlanul újrahasználható.

Hermes036 execution3f93d3a7-addb-4d0e-8286-daa32218b27a,1hívás,11625input+5893output=17518, becsült0,002130401USD, nem számla; nincs retry. Route/hibakód-váz részben átvéve, többi javítva: hiányzó auth-kivételszűrés, túlengedő/hibás bemenet, query által felülírható ID, dispatch utáni hibás order_id, kéretlen factory-stub, nem futtatható PHPUnit/eval teszt helyett tényleges natív teszt. SDK-loader és BFF Codex független integráció. Megtakarítást nem állítunk; Codex/reviewer tényleges költség ismeretlen. Delegálás remote ACK; termékcheckpoint/publikálás SHA a Secretaryben visszaolvasandó.

Következő D2: signed webhook + tartós esemény/dedupe és bizonytalan kísérletek hiteles rendezése, tényleges díj/refund/dispute egyeztetés, account/onboarding/díj- és felelősségi bizonyíték, UI fizetési lánc. Utána D3 fizetett szavazat/alap és D4 előzmény/legacy. Aktiválás egyik NGO-nál sem történt; hiányzó webhook/elszámolás miatt a kapcsolók maradnak OFF. A teljes adományozás, VB7/Sharing12, merge/deploy/élő UI/pénzügyi elfogadás továbbra nyitott. A meglévő §54 kiadási sorrend/rollback marad, nincs új kiadórendszer. Telepített SDK-artifact és private key beállítás külön, ellenőrzött művelet; nem a forráscommit következménye.

Rollback: új indítás tiltása, saját route/loader/fájl visszaállítása, meglévő quote/order/pénzügyi adat megőrzése; attempted állapot nem resetelhető. Későbbi kézi UI-checklist: adomanyozas/{slug} most inaktív; engedélyezett sandboxkiadáskor HU/EN/DE/FR checkoutnyelv, fix fizetési deviza, saját státusz, másik fiók404, bizonytalan válasz után nincs második fizetés, disable után saját státusz. Ezt a helyi teszt nem helyettesíti. Megőrzött átadási hivatkozás SUBSTACK-HANDOVER.md, nem végrehajtandó szerkesztési feladat.


## D2e — kezdés: hiteles Checkout-eseményfogadás és bizonytalan kísérlet rendezése

Koherencia: új webhookmodul, a saját SDK-loader szűk szétválasztása, saját modullista; explicit inbox-séma és egyeseményes CLI. Az eredeti kapcsolt account/rendelés az authority, a publikus tulajdonosi státusz változatlan. Nincs pénzügyi allokáció, szavazat, új cron vagy szolgáltatói írás. Kockázat: hamisított/ismételt értesítés, elveszett adatbázis-visszaigazolás, idegen account/SDK és hibás sikerjelzés. Védelem: valódi SDK-aláírás, befagyasztott rendelési egyezés, minimális egyedi inbox, visszaolvasás, GET-only rendezés, payment_verified=false. Hermes037 első kidolgozás; Codex integráció/teszt és független célzott review.

Ellenőrzés: aláírás/lejárat/test-live/account, duplikáció/párhuzamosság/bizonytalan commit tényleges izolált MariaDB-n, kulcs nélküli signature loader, meglévő checkout/státusz regresszió. Rollback csak saját kódra, adatokat és régi callbackek feldolgozhatóságát megtartva; telepítésig nincs éles változás. Kézi UI checklist kiadáskor: adományoldal és meglévő belépés/státusz változatlan, sikeroldal nem állít könyvelt adományt; sandboxban checkout kikapcsolása után korábbi esemény még fogadható. Éles UI és szolgáltatói próba most nincs.


## 59. D2e — aláírt Checkout-inbox és bizonytalan kísérlet helyreállítása (2026-11-02)

Kiindulás N bc08221b / A20b6ddb33, saját worktree-k. Új N `sharity-ngo-donations/webhook.php`; a meglévő runtime SDK-betöltése külön, API-kulcs nélkül is használható, az adapter továbbra saját kulcsot kér. A fő modul additívan tölti a webhookot: hiányzó új fájl nem veszi el a meglévő checkout/státusz útvonalat. Más termékhez, ponthoz, szavazathoz és pénzügyi allokációhoz nincs írás.

POST `/wp-json/sharity/v1/donations/webhook`: a valódi rögzített SDK ellenőrzi a nyers body aláírását, 300 másodperces toleranciával. Legfeljebb256KiB body/8KiB fejléc; konfiguráció/SDK/sémahiba503, hibás aláírás/kontextus400, azonos esemény eltérő tartós azonossága409. A válasz privát/no-store és szűrt. A minimális inbox csak az esemény/rendelés/session/account/verzió/típus/idő azonosságát tárolja; nincs nyers payload, név, email vagy külön ownermező. A PK eseményazonosító; ugyanazon session külön eseményeit megőrizzük. INSERT után mindig readback: duplikáció, elveszett ACK és commit utáni kivétel is így dönthető el.

A fogadó csak Connect snapshot Checkout-eseményekre készült: completed, async_payment_succeeded, async_payment_failed, expired. Rögzített API2026-10-28.endive; az eredeti rendelési envelope accountja, livemode-ja, összege/devizája, quote/policy/módja, lejárata és sessionje egyezzen. Az új checkout kapcsolóját és az időközben megváltozott NGO-accountkötést nem olvassa authorityként. Más ellenőrzött eventtípus vagy idegen termék Checkoutja figyelmen kívül marad. Saját, de hiányzó rendelés503, nem hamis tartós ACK.

`wp sharity donations webhook-schema --apply`: explicit hiányzó tábla létrehozása, pontos InnoDB/oszlop/collation/index/null/default ellenőrzés, readiness. Nincs HTTP-migráció, meglévő inkompatibilis tábla átírása vagy külső tranzakció implicit commitja. A fogadáshoz külön, privát szerverkonfiguráció kell: `SHARITY_DONATIONS_STRIPE_WEBHOOK_SECRET` és tényleges boolean `SHARITY_DONATIONS_STRIPE_WEBHOOK_LIVEMODE`. Titokérték nincs a forrásban/naplóban/Secretaryben.

`wp sharity donations webhook-recover --event=<evt_id>`: egy tárolt, hiteles eseményből az eredeti kapcsolt accounton kizárólag GET olvassa vissza a hiányzó sessiont; nincs új checkout, új kulcs vagy attempted-reset. A már rögzített azonos session esetén nincs új SDK/kulcs/providerigény; konkurens visszaolvasás eltérő pillanatnyi státusza nem cseréli fel a sessiont. A helyreállítás jelölése is visszaolvasott. Nincs új cron/queue; a későbbi pénzügyi feldolgozó ettől külön készül. `payment_verified=false`, a browser success és Checkout-esemény továbbra nem díj-/nettó-/kifizetési bizonyíték.

Tényleges ellenőrzés:174 MariaDB10.11.19 ellenőrzés (korábbi99+75 új), valódi párhuzamos mysqli-kapcsolatokkal és rögzített StripeSDK22.1.0-val. Valódi aláírás, nyers body, régi/jövőbeli időbélyeg, idegen account/live/mód/verzió/összeg/deviza/metadata, duplikált/sorrenden kívüli esemény, változó deliveryadat, insert/update elveszett ACK, külső tranzakció, GET-only helyreállítás, kapcsolók/binding kikapcsolása, konkurens eltérő státusz és adatmegőrző sémahiba szerepel. A teszt első futását egy elírt várt adapterhibakód állította meg; javítás után174 sikeres. Stripe HTTP helyettesített, nincs sandbox/live API. A saját loopback/tmpfs konténer eltávolítása visszaolvasva. Továbbá207 HTTP-határteszt és10 friss folyamatban SDK-loader/konfiguráció ellenőrzés sikeres, köztük API-kulcs nélküli tényleges aláírás-ellenőrzés. PHP lint/diff rendben. A változatlan pénz/ár/quote/UI korábbi bizonyítéka újrahasznált, nem új teljes suite.

Független gpt-6.1-sol/high D2e forrásreview: nincs konkrét scoped blokkoló; reviewer nem futtatott tesztet vagy távoli hívást. Hermes037 execution7de72d19-e78a-49f4-a2b5-6bfcd02d86a1:1 hívás,18109 input+8840 output=26949; becsült0,003256799USD, nem számla. A funkció/séma/CLI-vázlat csak részben hasznosult; a működést Codex javította. Fő hibák: titokkonstans árnyékolása, hibás session.account a top-level account helyett, hiányzó eredetirendelés-ellenőrzés, fiktív owner, sessionre egyedi inbox, hiányos dedupe/readback, elérhetetlen attempted-recovery, hiányzó loader/indexellenőrzés és nem futtatható tesztek. Nincs újrahívás vagy keretemelés; Codex/reviewer tényleges költsége ismeretlen, megtakarítás nem állított. A delegálás terminális memória ACK-ja megvan; a terméklezárás külön Secretary esemény/checkpoint és readback.

Stripe-hozzáférés: Arnold jóváhagyta a connector hitelesítését; a dokumentációeszköz a megismételt hívásra is ugyanezt a kérést adta, így nem futott harmadik próbálkozás. Hivatalos webes forrás és helyi SDK használt: https://docs.stripe.com/webhooks és https://docs.stripe.com/connect/webhooks. Ez nem Stripe-fiókhiba bizonyítéka és nem account-visszaolvasás.

Kiadási sorrend a meglévő §54/58 szerint: merge-SHA-ból artifact/függőség → kikapcsolt backend → quote/order/inbox explicit séma → privát aláírási konfiguráció → Connect snapshot endpoint négy fenti típussal → sandbox/readback → további pénzügyi egyeztetés/accountbizonyíték/UI és csak utána NGO-aktiválás. Sem endpointregisztráció, sem kulcsbeállítás, sem éles sémaírás nem történt most. Az új fizetések kapcsolója továbbra OFF. Rollback csak saját fájl/pin; quote/order/inbox/pénzügyi adat megmarad, történeti callback és recovery nem állítható le az új checkout tiltásával.

Következő D2: tényleges charge/balance transaction/díj/refund/dispute egyeztetés és nettó80/20/tartalék; ehhez további pénzügyi eseménytípusok kezelése is kell. Account/onboarding/díj/felelősségi bizonyíték és kliensfizetési lánc még nyitott. D3 fizetett szavazat/alap, D4 előzmény/legacy, affiliate/Offerwall teljes pénzügy és VB7/Sharing12 sem zárult le. Forrás/célzott teszt kész; commit/push konkrét SHA a Secretaryben/PR-ben. Merge, telepítés, aktiválás, élő UI- és pénzügyi elfogadás NEM történt. Nincs mainablak.

Kézi checklist kiadáskor: meglévő bejelentkezés/saját státusz működik; a fizetési felület OFF marad az elfogadásig; sandboxban checkout tiltása után korábbi callback fogadható; ismételt értesítésből nincs második rendelés/jóváírás; a sikeroldal nem állít könyvelt vagy kifizetett adományt. Most nincs éles UI-ellenőrzés. Megőrzött átadási hivatkozás: SUBSTACK-HANDOVER.md, nem végrehajtandó szerkesztési feladat.
