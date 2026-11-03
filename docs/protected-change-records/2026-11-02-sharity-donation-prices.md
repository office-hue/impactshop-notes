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


## D2f — kezdés: tényleges eredeti Stripe-terhelés és díjak olvasása

Koherencia: meglévő SDK-adapter read-only bővítése és explicit admin payment-inspect CLI; session→PaymentIntent→charge→balance transaction, Amplifiernél a platform alkalmazásdíja külön. A díjkétszerezés és külön devizák összeadása konkrét pénzkockázat, ezért az eredeti kötés és teljes díjrészletezés ellenőrzött. Hermes038 első kidolgozó. Nincs új DB-tábla, közös infrastruktúra, provider-írás, pénzügyi allokáció, szavazat vagy aktiválás; az új pénzügyi napló az egyeztetett korrekciós szerződéssel következik. Az eredeti terhelés adata nem a refund/dispute utáni aktuális egyenleg; ezt a visszaadott állapot egyértelműen jelöli.

Célzott teszt: valódi rögzített SDK, helyettesített HTTP, pontos account és platform scope, teljes lánc/összeg/díj/FX konzisztencia, hiány/pending/refund/dispute, PII- és hibaszűrés. Rollback saját adapter/CLI/loader/pinek; rendelés/inbox/pénzügyi adatok és korábbi callbackek megmaradnak. Későbbi UI-checklist: a fizetési felület még OFF, meglévő státusz nem állít allokációt/kifizetést. Most nincs UI/éles provider próba.

## 60. D2f — eredeti Stripe-terhelés és tényleges díjak olvasása (2026-11-02)

Kiindulás: tiszta saját A ae408f520 / N d5769bbd worktree. A `stripe-checkout.php` adapter új `retrievePaymentEvidence` metódusa, az additív `finance-read.php` és a fő loader módosult; nincs új adatbázis, író, queue, release-rendszer vagy jogosultsági réteg. A meglévő ár/quote/order/webhook és UI szerződés megmarad. A pénzügyi állapot még nem végleges és egyik NGO-nál sem aktivált.

Az eredeti, változatlan rendelési envelope alapján legfeljebb öt GET követi a session → kibontott PaymentIntent → charge → balance transaction láncot; Amplifiernél az alkalmazásdíj és annak balance transactionje további két GET. Az első három az eredeti NGO connected accountján, az utolsó kettő a platformon fut. A kliensszintű örökölt account tiltott ezen az úton, így a per-request fejléc törlése nem tud tévesen ugyanazon NGO-n maradni. SDK22.1.0/API2026-10-28.endive, retry0, nincs idempotens create-fejléc vagy POST. Korábbi rendelés az aktiválás és helyi ajánlatlejárat után is olvasható.

Azonosító, object, livemode, rendelés/quote/policy/mód, eredeti összeg/deviza, teljes sikeres beszedés és providerhivatkozások ellenőrzöttek. Pénz csak provider egész minor értékből kerül pontos decimális sztringbe; HUF-nál is százados Stripe-egység marad. Az eredeti balance összeg − teljes díj = nettó egyenletét és a díjrészletek összegét ellenőrizzük. Az `application_fee` külön oszlop, a tényleges feldolgozási költségből kivonva: a közösalap-rész így nem fizetési költség másodszor. Platformdíj és platformdeviza külön eredeti balance-ként látható. Azonos devizán pontos összegazonosság szükséges; eltérő devizák nem adhatók össze. A szolgáltatói exchange_rate információs szám, nem pénzszámítási vagy MNB-fedezeti alap.

A `scope=original_charge_only`, `payment_verified=false` és `allocation_eligible=false` mindig megmarad. Kifizetésre/allokációra jogosító összeg nincs. Fizetetlen/hiányos session nem gyárt nulla díjat vagy nulla pénzügyi bizonyítékot; sérült lánc szűrt hibát ad. A hiányzó refundlista nem üres lista. Visszatérített összeg, teljes refund, refundlista/pagináció, dispute és failure balance transaction külön rendezést jelez; a platformdíj refundja is feljut a felső állapotba. Pending balance és negatív eredeti nettó látható, de nem jogosít felszabadításra. A nyers objektum, név/email/kártya/receipt/description és providerhibaszöveg nem kerül a válaszba.

`wp sharity donations payment-inspect --order=<do_id>`: explicit megbízható admin CLI, nincs publikus route. Valódi korábbi session_recorded rendelés, a meglévő tulajdonoshoz kötött státuszvalidálás és autocommit/nyitott tranzakció ellenőrzés előzi meg az SDK-hívást. Nem ír rendelést, inboxot, pénzügyi naplót vagy kapcsolót. A fő loader csak meglévő új fájlt tölt, hiányzó finance/webhook nem veszi el a korábbi checkout/státusz útvonalat. A parancs forrásban elérhető; távoli telepítését vagy futtatását ez nem állítja.

Tényleges tesztek: `php tests/sharity-donation-finance-read.test.php` 397 ellenőrzés, `php tests/sharity-donation-stripe-checkout.test.php` 197 regresszió, `php tests/sharity-donation-runtime.test.php` 12 külön folyamatú eset. Rögzített valódi SDK, helyettesített HTTP, CLI-ben valós order-decode/status/autocommit kód és mockolt WPDB-transport. Mindkét mód × négy deviza, három külön devizájú lánc, platformdíj, pending/fizetetlen/részbeszedés/refund/dispute, részleges telepítés, hibás input/hiány/összeg/id/mód, hálózati hiba és örökölt account szerepel. Nincs valódi DB/provider/sandbox/éles pénzmozgás, a korábbi 174 MariaDB/207 HTTP eredmény nem új futás. A DB-író változatlan, új párhuzamossági mechanizmus nincs, ezért új konténer nem indult. PHP lint és diff ellenőrzött. Bástya inventory és publikálási audit külön, eredményük alább.

Független `/root/donation_d2f_review` forrásreview: nincs konkrét blokkoló; a reviewer a helyi rögzített SDK-val vetette össze a forrást, tesztet/távoli hívást nem futtatott. Kért modell gpt-6.1-sol/high; a reviewer pontos futó modellazonosságát külön nem tudja introspektálni. A két korábbi pénzügyi tervaudit változatlan; új pénzügyi író vagy felosztási policy nem keletkezett.

Hermes038 execution ec65fa8a-31be-4727-8574-1df5ffad6943: 1 hívás, 11902 input + 5549 output = 17451 token; becsült 0,002093 USD, nem számla. deepseek/deepseek-v4-flash; nincs retry vagy keretemelés. Az első tervezet nem volt integrálható: duplikált metódus, hibás SDK-service/method, kitalált test/live resource-ID és application-fee status, hibás PI alkalmazásdíjtiltás, float/HUF-formázás, cross-currency és díjellenőrzés, hiányzó refund/állapotfelvezetés, PII-leírás és hibás CLI oszlop/argumentum. A GET-lánc/CLI szándéka hasznosult; a tényleges implementáció és teszt Codex-javítás. Megtakarítás nem állított; Codex/reviewer tényleges költsége ismeretlen. Delegálási terminális remote ACK megvan; termékcheckpoint/publikálás külön visszaolvasandó.

Hivatalos szerződésellenőrzés: [Charge](https://docs.stripe.com/api/charges/object), [Balance transaction](https://docs.stripe.com/api/balance_transactions/object), [Application fee](https://docs.stripe.com/api/application_fees/object), [direct charges](https://docs.stripe.com/connect/direct-charges), [Connect devizák](https://docs.stripe.com/connect/currencies). Az eredeti charge balance transaction nem tartalmazza a későbbi refund/dispute hatását. Stripe-account/költség/felelősség élő visszaolvasás most nem történt; a korábbi connector-auth ismétlődést nem próbáltuk újra.

Kiadás: saját explicit commitok → branch push/SHA-readback → draft PR244 és A1870 DOCSYNC → mainművelet csak külön szűk csendablakban → tiszta merge-SHA-ból meglévő exact-file út. Függőségként a bővített adapter, majd finance-read, végül additív loader; először stagingen ugyanazon byte-ok, utána production. Nincs új séma vagy kapcsoló. Privát eredeti SDK/kulcs és meglévő order/inbox szükséges az admin olvasáshoz; tesztrend elöl a sandboxlánc, majd engedélyezett saját valódi rendelés olvasása. A parancsot most egyik távoli rendszeren sem futtattuk. Mentés/CAS/readback a meglévő release-szabály szerint. Rollback csak saját adapter/finance/loader és pinek, quote/order/inbox/pénzügyi adat és korábbi callback megmarad.

Következő koherens pénzügyi csomag: a charge/refund/dispute/application-fee-refund tényleges mozgások teljes, idempotens rendeléshez kötött egyeztetése és pénzügyi naplója; csak ebből nettó80/20-cél, tartalék, kontrollált díjkorrekció, egyszeri felszabadítás. A mostani eredeti balance nem használható aktuális nettóként. NGO-account/onboarding/díj/felelősségi bizonyíték, UI fizetési lánc, D3 rendelési szavazat/alap, D4 előzmény/legacy, affiliate/Offerwall teljes pénzügy és VB7/Sharing12 továbbra nyitott. Új fizetések OFF; nincs merge, telepítés, aktiválás, élő UI- vagy pénzügyi elfogadás. Kézi UI-checklist változatlan: régi belépés/státusz/callback, tiltott új adományindítás, sikeroldal nem állít könyvelést. Megőrzött átadás: SUBSTACK-HANDOVER.md, nem végrehajtandó szerkesztési feladat.

D2f védelmi visszaolvasás: az első inventoryfutás kizárólag a saját config.sha256 kimaradt pinjét jelezte. A konkrét pin javítása után teljes inventory nulla drift és6 izolált hash/checksum eset sikeres; idegen pin változatlan.


## D2g - korrekcios penzmozgasok: kezdes (2026-11-02)

Sajat N ef7dd5b2 / A ed9c2741e; Hermes039 elso kidolgozo. Az eredeti terheles olvasoja melle teljes, korlatos refund/dispute/application-fee-refund olvasas es admin CLI kapcsolo keszul. Konkret kockazat: korrekcio utani hibas netto, dupla dij es kihagyott penzmozgas. Nincs adatbazis- vagy provider-iro, pont/szavazat, UI vagy aktivalas. Celzott valodi SDK/helyettesitett HTTP teszt es fuggetlen review; rollback csak sajat forras es pin. A meglevo UI/status/webhook valtozatlan; kesobbi UI-checklist: uj adomany OFF, regi sajat status nem allit kifizetest.


## 61. D2g — visszatérítések és viták olvasása, inaktív (2026-11-02)

Kiindulás: saját N ef7dd5b2 / A ed9c2741e, tiszta worktree-k. A meglévő `stripe-checkout.php` új `retrieveAdjustmentEvidence` metódust kapott; a meglévő `finance-read.php` parancs `--adjustments` kapcsolóval kérheti. Az eredeti olvasó változatlan alapértelmezés; nincs új modul, tábla, ütemező, pénzügyi író vagy release-rendszer.

Szerződés: egy korábbi rendelés eredeti envelope-ja → eredeti terhelés → teljes, lapozott refund/dispute/application-fee-refund listák → eredeti terhelés ismételt összevetése. Kollekciónként legfeljebb10, egyenként100 soros oldal, összesen legfeljebb40 GET; nincs retry vagy soronkénti korlátlan lekérés. A connected-account és platform scope külön. Teljes listaazonosság, ismételt azonosító, inline-listaszám/has_more és pénzügyi aggregátum ellenőrzött. Változás vagy ellentmondás szűrt hibával megállítja ezt az olvasást. Ez nem atomikus Stripe-pillanatkép és nem zárja ki a későbbi eseményt.

Az összeg, nettó és díjrészletek előjele megmarad; `amount-fee=net` és díjösszeg ellenőrzött. A refund terhelése és sikertelenségi visszafordítása külön tényleges balance. A vita névleges összege/devizája eltérhet az adományétól; ebből nem gyártunk pénzmozgást. A nyert, elvesztett, appeal és prevented állapotok is olvashatók, a nyitott ügy külön egyeztetendő. A négy deviza minorértékei sztringként maradnak, HUF-nál százados Stripe-egységgel. Nincs MNB-váltás, devizák közötti összegzés vagy személyes payload a kimenetben.

A `resources_complete` csak a lekért listák teljessége. `atomic_snapshot=false`, `payment_verified=false`, `settlement_reconciled=false` és `allocation_eligible=false` minden eredményben. Hiányzó/kibontatlan/függő balance, nyitott refund/vita külön jelzés. Az alkalmazásdíj platformoldali visszatérítése nem bizonyítja a kapcsolt NGO jóváírását: `connected_credit_verified=false`, `fee_refund_connected_credit_unreconciled`. A kapcsolt ellenoldalt nem helyettesíti becsült összeg vagy ugyanazon platform-balance ID. Ennek igazolása a teljes egyeztetés és főkönyv előtt szükséges.

Tényleges teszt: `php tests/sharity-donation-adjustments.test.php` a korábbi397 + új461 ellenőrzést futtatta; `php tests/sharity-donation-stripe-checkout.test.php`197, `php tests/sharity-donation-runtime.test.php`12 külön folyamatú eset sikeres. Valódi SDK22.1.0, kizárólag helyettesített HTTP; CLI-ben a korábbi WPDB-transport mock. Az első új tesztfutás érvénytelen szintetikus txn_re_A azonosítót talált; célzott diagnózis után a fixture javítva. Nincs új DB-író, ezért a korábbi174 MariaDB/207 HTTP eredmény újrahasznált, konténer és élő Stripe-próba nem indult. PHP lint/diff ellenőrzött.

Független gpt-6.1-sol/high kérésű forrás- és API-szerződésreview: appeal/prevented állapotok és ellentmondó inline-listaszám kezelése javítva, célzott újraolvasás után nincs további konkrét blokkoló. Reviewer nem futtatott tesztet. A két korábbi tervaudit érvényes; nincs új pénzügyi policy. Hivatalos források: [refund](https://docs.stripe.com/api/refunds/object), [dispute](https://docs.stripe.com/api/disputes/object), [fee refund](https://docs.stripe.com/api/fee_refunds/object), [balance típusok](https://docs.stripe.com/reports/balance-transaction-types).

Hermes039: execution84b06127-303c-4e0e-94a1-0a7cde0e921a;1hívás,13309input+4068output=17377token, becsült0,001951495USD, nem számla. Terminális remote ACK, nincs retry. A közvetlen patch nem volt alkalmazható: hibás diff/szerkezet, kihagyott eredetirendelés-validálás, hiányzó lista-/összeg-/scope-ellenőrzések, pozitív-only balance helper, hiányos failure-balance, téves WP-CLI argv-kezelés. A lapozási és szolgáltatáshívási szándék részben átvéve; Codex integrálta a javított működést és teszteket. Megtakarítás nem állított; Codex/reviewer/teljes tényleges költség ismeretlen.

Pilotdöntés: Sharity Adományszervező Alapítvány, slug `sharity-adomanyszervezo-alapitvany`, HUF. A Zrt. Stripe-fiókjának legutóbbi sikeres HUF-kifizetési célját read-only connector visszaadta; Arnold megerősítette, hogy az az Alapítvány bankszámlája. Ez felhasználói megerősítés, nem Stripe-számlatulajdonos mező vagy külön NGO-account. Teljes bankadat nincs a repóban. A kapcsolt fiókok korábbi teljes listája üres; az aktuális connector nem adott saját account controller/díj/felelősségi olvasóműveletet. Nem indult új fiók, fizetés, visszatérítés vagy beállításmódosítás. A platformon végzett fizetés díjai a platformot terhelhetik; a banki cél nem változtatja meg a Stripe-felelősséget. A pilot technikai account-/díj-/fedezeti ellenőrzése továbbra nyitott.

Kiadás: saját commit/push és SHA-readback, meglévő PR244 + A1870 DOCSYNC; mainművelet csak külön szűk csendablakban. Meglévő exact-file út tiszta merge-SHA-ból: adapter előbb, CLI utána, stagingen ellenőrzött byte-ok productionön. Nincs sémaváltás vagy új aktiválóflag. Meglévő SDK/key/order/inbox függőség; privát mentés, friss célhash/CAS és readback. Rollback a saját két fájlra/pinekre; a régi callback, rendelés és pénzügyi adat megmarad. Érintett Bástya csak a két saját hash frissítése, guardlogika/idegen baseline változatlan.

Kézi UI-checklist későbbi kiadáskor: meglévő bejelentkezés/saját státusz működik; új adományindítás még OFF; sikeroldal nem állít könyvelt vagy kifizetett összeget. Az admin csak engedélyezett sandboxrendelésen próbálja a `wp sharity donations payment-inspect --order=<do_id> --adjustments` parancsot. Most nem volt élő UI vagy távoli parancs.

Nyitott: kapcsolt díjvisszatérítési ellenoldal igazolása, tartós pénzügyi napló, nettó80/20-cél/teljes kitettségi tartalék/vezérelt korrekció és felszabadítás, NGO-account és díj/felelősség, UI-fizetési lánc, D3 szavazat/alap, D4 előzmény/legacy, affiliate/Offerwall pénzügy, VB7/Sharing12. Forrás és célzott ellenőrzés kész; merge/telepítés/aktiválás/élő elfogadás nem történt. Megőrzött átadás: SUBSTACK-HANDOVER.md.


## D2h — tartós pénzügyi megfigyelési napló: kezdés (2026-11-02)

Saját N3d22762b/A22a6ced8, Hermes040 első kidolgozó. Egy rendeléshez kötött, változatlanul megőrzött Stripe-megfigyelés; ismételt import nem új pénz. Konkrét kockázat: adatvesztés, dupla import, bizonytalan INSERT-nyugta és téves pénzügyi készültség. Meglévő autocommit/insert/readback/séma mintát használunk; nincs provider-írás, allokáció, szavazat vagy aktiválás. Explicit séma/CLI, nincs HTTP-migráció. Valódi izolált MariaDB-versenyteszt és független review szükséges. Rollback saját kód/pinek, megfigyelési és rendelési sorok megmaradnak. Érintett UI változatlan; későbbi kézi checklist: meglévő login/státusz, új adomány OFF, sikeroldal nem állít kifizetést.


## 62. Aktiválási lezárási terv és megőrzött Hermes040-tervezet (2026-11-02)

A D2h kezdése után Arnold részletes végrehajtási tervet kért; termékkódot nem integráltunk. Kanonikus terv: ai-agent `docs/dev-plans/sharity-internationalization-delivery-20261031.md` §62, a meglévő #1870 PR-ben; N #244 változatlanul inaktív forrás. Rögzített fájlok, tervezett adminparancsok, pontos account/UI/webhook hiányok, két mód külön sandbox/aktiválás, SDK/exact-file/séma/Vercel/publisher sorrend és adatmegőrző rollback szerepel benne.

Két egymást követő független tervaudit, javítással lezárva; második audit utáni célzott readback elfogadta a közös backing javítását. Közös campaign/backing/order/grant/wallet/cast/movement/operation zársorrend; prepared korrekció is foglal, stale cél nem dispatch-elhető; pénzzel fedezett reserve és közös keret egyszeres felhasználása; devizás alaprész csak igazolt HUF-váltás után növelhet pool_huf-ot. A direct pilot önállóan kiadható. Ez tervreview, nem futási vagy éles bizonyíték.

Hermes0401hívás,23738token, becsült0,002791698USD, nem számla; pending és terminal remote_ack. Javaslat privát artifactban megőrizve; nincs átvétel/termékteszt, javításigény még ismeretlen. Nincs retry/fallback, Codex/reviewer tényleges költsége ismeretlen. Új termékfájl/séma/guardpin/SDK/providerbeállítás nem változott, runtime OFF állapotát ez a kör nem ellenőrizte újra. A korábbi D2g teszteket csak változatlan forrásbizonyítékként használjuk újra. Dokumentum-diff és célzott publikálási audit következik; Secretary mentés/readback külön. Rollback a saját dokumentumcommit visszavonása; pénzügyi adatot nem érint. Mainablak nincs, merge/deploy/aktiválás/élő elfogadás hátravan. SUBSTACK-HANDOVER.md megőrzött hivatkozás.

## D2h — jóváhagyott implementáció indulása (2026-11-02)

A §62 és Arnold végrehajtási utasítása alapján a megőrzött Hermes040 terminális result/text tervezet javított átvétele következik, új providerhívás nélkül. Saját tiszta N 515cff09 / A 58a94bebe. Érintett termékfájl: új finance-ledger.php, meglévő finance-read.php és fő loader. Egyedi (order_id,digest), teljes tartalmi visszaolvasás, kanonikus bizonyíték, 4 MiB korlát, explicit --apply séma. Nincs provider POST, order-átírás, pénzügyi allokáció vagy aktiválás. Valódi izolált DB-verseny és bizonytalan INSERT-válasz teszt szükséges; teszteredmény még nincs. Rollback: saját fájlok visszaállítása, új megfigyelési sorok megőrzése; nincs éles adatbázis-visszatöltés.

D2h teszt közben konkrét korábbi rendelési hiba: az első request_key_hash olvasás után egy másik kapcsolat ugyanazzal a kulccsal beilleszthet; a következő quote_id olvasás téves QUOTE_ALREADY_ORDERED hibát adott. Kétkapcsolatos determinisztikus reprodukció igazolta az orders.php:76 ágat. Szűk javítás: a quote alapján megtalált azonos tulajdonos/kulcs/digest rendelés kanonikus ellenőrzés után visszaadható; eltérő kulcs vagy tartalom hibája megmarad. Ugyanez a bizonytalan INSERT utáni késői quote-read határra is érvényes. Nincs új query, providerhívás, séma vagy pénzügyi hatás. Emiatt negyedik termékfájl az orders.php és a saját hash; kiadási/rollback sorrend változatlan.

## 63. D2h - megfigyelesi naplo, helyi ellenorzes kesz (2026-11-02)

Hermes040 terminalis result/text tervezetenek javitott atvetele. Erintett termek: uj sharity-ngo-donations/finance-ledger.php, finance-read.php, fo loader es a tesztben feltart orders.php race-javitas. UNIQUE(order_id,digest), kanonikus objektumkulcsok es azonositott eroforrashalmazok, pontos decimalis minor-sztringek. 4 MiB kanonikus bizonyitekmaximum es tenyleges escaped INSERT/DB-packet ellenorzes. Egy INSERT utan teljes tartalmi readback; ismetles az eredeti helyi ID-t es idopontot adja. Nincs observation_seq, szemelyazonosito vagy nyers providerpayload. A helyi ID/idopont nem szolgaltatoi idorend vagy penzugyi veglegesseg.

Parancsok: `wp sharity donations finance-schema --apply` es `wp sharity donations payment-record --order=<do_id>`. Schema csak pontos --apply argumentummal ir; hibas/inkompatibilis tabla nem javul automatikusan. A record csak tarolt, kanonikus order_status szerint is session_recorded rendelesre olvas, tranzakcion kivul. A regi payment-inspect valtozatlan. Nincs HTTP-migracio, provider POST, order-modositas, szavazat vagy allokacio. payment_verified/allocation_eligible/atomic_snapshot/settlement_reconciled false marad. Hianyzo opcionalis ledger mellett a regi ar/quote/status/webhook/inspect megmarad.

Konkreten feltart korabbi hiba: az elso kulcs-read utan nyero azonos keresre a quote-read teves QUOTE_ALREADY_ORDERED hibaval valaszolt. Ketkapcsolatos determinisztikus reprodukcio a javitas elott hibazott; utana ugyanazt a rendelesi sort adja mindket hivas. Az orders.php ket quote-visszaolvasasa azonos kulcs/digest es ervenyes envelope eseten replay; eltero kulcs/input tovabbra is hiba. Nincs uj SQL/providerhivas vagy ujraprobalkozasi kulcs.

Tenyleges tesztek: `SHARITY_DONATION_TEST_PORT=<isolated-loopback-port> php -d memory_limit=512M tests/sharity-donation-orders-mysql.test.php`: 281 ellenorzes, ebbol105 D2h. `php tests/sharity-donation-adjustments.test.php`: 400 eredeti penzugyi/CLI +461 adjustment. `php tests/sharity-donation-runtime.test.php`: 13 friss folyamat. Valodi MariaDB10.11.19 es rogzitett SDK, helyettesitett HTTP; nincs provider/sandbox/eles penzmozgas. Ketkapcsolatos azonos/eltero iras, lost ACK/throw_after, hianyzo/serult post-INSERT readback, tobb MiB es 4 MiB elutasitas, tenyleges kicsi server packet, tranzakciotilalom es serult schema. A sajat 512 MiB loopback/tmpfs kontener torolve es hianya visszaolvasva. PHP lint es diff-check sikeres. Az idoszakos korabbi race nem lett PASS-kent elrejtve: determinisztikusan reprodukalva es javitva.

Fuggetlen celzott implementacioreview: d2h_source_review, naplo es kulon race-javitas lelet nelkul; a reviewer tesztet nem futtatott. A ket korabbi penzugyi tervaudit ujrahasznalva. Hermes040 korabbi egy hivas,16798 input+6940 output=23738 token, becsult0.002791698USD, estimate_not_invoice. Tenyleges es Codex/review koltseg ismeretlen; uj Hermes/JEV/providerkoltes nincs. Reszleges atvetel, lenyeges Codex-javitas: hibas loadercel, forditott --apply, hianyzo canonical order_status, seq/egyediseg, kanonizalas, accountcsonkitas, schemaellenorzes, 64 KiB korlat, nem mukodo draftteszt. Megtakaritas nem allitott.

Kiadas: nev szerinti product commit, kulon guardhash commit, sajat branch/PR244 readback, kesobbi szuk maincsendablak, tiszta merge-SHA. Meglevo exact-file staging, ugyanazon byte production; privat mentes/CAS/readback. Sorrend: kompatibilis orders es meglevo adapter, ledger, CLI, loader, explicit schema, engine/mezok/indexek/adatmegorzes visszaolvasasa, engedelyezett sajat sandboxrendeles recordja. Nincs scheduler vagy automatikus recording. A schema-ready jelzo nem fizetesi aktivalas.

Rollback: uj admin recordhivasok leallitasa; csak sajat file/pin visszaallitasa. Megfigyelesek/order/inbox megmaradnak; nincs eles DB-visszatoltes uj esemenyek fole. Smoke: regi ar/quote/order/status/webhook/inspect, kapcsolo nelkuli DDL-tilalom, explicit schema es ismetelt record. Kezi UI-checklist: regi HU/EN/DE/FR donation shell es belepes megmarad, nincs uj fizetesi/szavazati CTA. Tavoli/UI smoke meg nem futott.

Nyitott: egyedi penzmozgas/egyeztetes/foglalas es kozos fedezeti keret; mode_currencies/account CLI; availability/status-by-quote/UI; directHUF pilot; netto80/20 es D3/D4; tobbi deviza, affiliate/Offerwall, VB7/Sharing12. A megfigyelesek nem osszeadhato penzmozgasok. Helyben ellenorzott forras; merge/telepites/aktivalas/eles elfogadas nincs. Megorzendo atadas: SUBSTACK-HANDOVER.md.


## D2i — írásmentes pénzmozgás-egyeztetés, kezdés (2026-11-02)

Saját N f65bddd9 / A 36603930; jóváhagyott §62 folytatása. Hermes041 első kidolgozó egy bevezető mondatot adott, patch nélkül; 1 hívás, 20451 input/72 output token, becsült 0.001874145 USD (nem számla). Nincs újrahívás, Codex készíti a szűk részt. Két termékfájl: finance-ledger.php és finance-read.php; meglévő DB-harnessbe illesztett célzott teszt.

A payment-reconcile kizárólag a tartós megfigyeléseket olvassa. Egy Stripe balance transaction egy account/livemode körben egyszer szerepelhet; azonos azonosság eltérő pénzügyi tartalommal hiba, nem legújabb sor választása. Pending/available megfigyelési metaadat. Névleges refund/dispute nem új pénz. Platform-account bizonyíték nélkül a platformtételek külön, feloldatlanok; nincs NGO-accountnak címkézés. Nincs DDL/SDK/providerhívás/írás/foglalás/allokáció/aktiválás; --apply elutasítva. A tartós közös pénzmozgás-egyediség, backing és alkalmazás külön következő feladat.

Bemenetkorlát: legfeljebb 256 megfigyelés és összesen 16 MiB kanonikus bizonyíték rendelésenként, soronként a meglévő 4 MiB. Túllépés explicit hiba, nem csonkolt eredmény. Nem atomi szolgáltatói pillanatkép; a későbbi író friss, zárolt feltételeket ellenőriz. Teszt: valódi izolált DB-olvasás, sorrend/replay/konfliktus/sérülés, pénzsztringek és szigorú CLI, nulla üzleti írás és providerhívás. Rollback saját két file és pin; meglévő megfigyelések változatlanok. Kiadási UI-checklist korábbi login/státusz, donation OFF; ez admin-olvasó, jelen körben nincs éles UI-teszt.

## 64. D2i — tárolt pénzmozgások írásmentes egyeztetése (2026-11-02)

A jóváhagyott §62 folytatása: a `finance-ledger.php` és `finance-read.php` most a `wp sharity donations payment-reconcile --order=<do_id>` adminparancsot adja. Ez a rögzített megfigyeléseket olvassa, és az account/livemode/balance-transaction azonosság alapján elkülöníti az egyedi pénzmozgásokat. Nem ad össze megfigyeléseket, névleges refundot vagy dispute-ot. Eltérő pénzügyi tartalom, eredeti charge/fee egyenlegazonosító vagy forráskötés esetén hiba; nincs helyi ID/időpont szerinti felülírás. A díjtételek sorrendje nem számít, de többszörös előfordulásuk megmarad. Pénz csak pontos előjeles minor-sztring; szolgáltatói árfolyam és pending/available időadat megfigyelési metaadat, nem könyvelési váltás vagy véglegesség.

Az igazolt platform-account hiánya miatt az alkalmazásdíj és díjvisszatérítés platformmozgása külön `unscoped_platform_movements`, null accounttal és feloldatlan jelzéssel szerepel. Nem lesz NGO-accountnak címkézett pénz. A charge failure balance jelenlegi projekcióhiánya külön jelzés. A kimutatás nem helyettesíti a rendeléseken átívelő tartós mozgásregisztert, a közös likvid fedezetet, a nettó80/20-célt vagy a díjvisszatérítési ellenoldalt. `payment_verified`, `allocation_eligible`, `settlement_reconciled`, `apply_supported`, `atomic_snapshot` mind false.

A kanonikus order_status/envelope és minden naplósor account/order/session/mód/livemode/digest/id/időpont/kanonikus JSON ellenőrzött. Legfeljebb256 megfigyelés, összesen16 MiB és soronként4 MiB; túlcsordulás explicit `DONATION_RECONCILE_LIMIT`, nincs csonkolt siker. Előbb a lokális megfigyeléslista rögzül, majd korlátos bináris sorolvasás következik; ez nem atomi szolgáltatói pillanatkép. Egyidejű append a következő olvasásban látszik, nem írja át az aktuális bizonyítékhalmazt. A pénzügyi és bizonyítékhalmaz-digest külön. A későbbi végrehajtó friss adatokból, a §62 szerinti közös tranzakcióban ellenőriz újra.

Nincs új séma, index, HTTP-végpont, SDK-betöltés, Stripe-hívás, adatírás, scheduler, aktiválókapcsoló vagy általános helper. `--apply` és minden nem dokumentált CLI-argumentum elutasított. Az üres napló hiányzó bizonyíték, nem nulla beérkezés. Régi inspect/record/status/webhook változatlanul elérhető.

Tényleges ellenőrzés: `SHARITY_DONATION_TEST_PORT=<own-loopback> php -d memory_limit=512M tests/sharity-donation-orders-mysql.test.php` végső936 assertion, ebből655 D2i (a fixture-beillesztés/takarítás ellenőrzéseit is tartalmazza). Valódi MariaDB10.11.19, read-only SQL-tranzakcióban sikeres egyeztetés, második kapcsolat közbeni append, ismétlés, eltérő pénzügyi tény, eredeti balance-ID, előjel, sérült hash/azonosság/kanonikus adat, díjtétel-multihalmaz, refund/reversal/dispute, ismeretlen platformaccount, CLI-tilalom,4/16 MiB és256 sor határ. Nulla SDK/HTTP/option/üzleti írás az egyeztetés alatt. A korábbi D2h/order-versenytesztek is ebben a futásban szerepelnek.

`php tests/sharity-donation-adjustments.test.php`:401 eredeti pénzügyi/CLI +461 adjustment. `php tests/sharity-donation-runtime.test.php`:13 friss folyamat. Valódi rögzített SDK, helyettesített HTTP; nincs sandbox/live Stripe-kérés. Egy köztes futás a régi HTTP-harness eltérő óráinál megállt: HTTP time() mellett az SDK rögzített $now értéke egy másodperccel korábbi lehetett. Csak a HTTP-teszt használ azóta normál SDK-órát, majd visszaáll a fixture-óra; termékóra nem változott. A hibás futás nem PASS. A végső futás sikeres. Saját512 MiB/tmpfs konténer eltávolítva, hiánya visszaolvasva.

Független implementációreview: d2i_source_review, kért gpt-6.1-sol/high, kezdeti és kiegészítések utáni readback lelet nélkül; reviewer viselkedési tesztet nem futtatott. Két megjelölt tesztlefedettségi hiány pótolva. A két korábbi pénzügyi tervaudit újrahasználva. Hermes041 első kidolgozás eredménye csak bevezető mondat, kód nélkül:0 átvett patch, teljes Codex-implementáció. Egy hívás,20451 input+72 output=20523 token, becsült0.001874145USD, estimate_not_invoice. Nincs újrahívás/JEV/fallback; tényleges összköltség és Codex/reviewer díja ismeretlen, megtakarítást nem állítunk. Secretary evt-492ae8e1-e765-47f7-8486-02318767ed9c ezt visszaigazolta; csomagcheckpoint külön mentendő/readbackelendő.

Kiadás: saját product/docs commit, külön kizárólag két módosult saját module hash/Bástya commit; meglévő PR244 és A1870 DOCSYNC. Main előtt Arnold új, szűk csendablaka kell. Tiszta merge-SHA → meglévő exact-file staging → azonos byte production. Meglévő SDK/order/ledger séma szükséges; D2i nem migrál. Privát érintett-fájlmentés, friss előállapot/CAS, ledger előbb, CLI utána, távoli hash és engedélyezett saját sandbox-order read-only próba. Már telepített új CLI régi ledger mellett egyértelmű unavailable, korábbi parancsok megmaradnak. Rollback saját két file/pin; pénzügyi napló/order/inbox megmarad, DB-visszatöltés nincs.

Későbbi kézi kiadási checklist: korábbi login/státusz és donation shell HU/EN/DE/FR; nincs új fizetési vagy szavazati CTA, nincs könyvelt/kifizetett összeget ígérő sikeroldal. Távoli/UI smoke most nem futott. Helyben tesztelt forrás; merge, telepítés, aktiválás és pénzügyi elfogadás nincs. Következő: tényleges cross-order pénzmozgásregiszter/egyeztetés/foglalás és közös fedezet; account mód–deviza mátrix és saját quote-státusz/UI; directHUF majd Amplifier, D3/D4, többi deviza, affiliate/Offerwall és VB7/Sharing12. SUBSTACK-HANDOVER.md megőrzött hivatkozás.


## D2j - mode/currency and owner quote read: start (2026-11-02)

Approved section62; own N60a06ba6/Ada8746db2. Pending main quiet-window request has no answer, no main operation authorized. Hermes042 proposes shared strict mode_currencies resolver and read-only owner status-by-quote. Scope: donation main/orders/http, existing tests, mechanical web BFF using existing read auth. Concrete risks: unauthorized mode/currency checkout, other-owner order disclosure and duplicate checkout after lost response. No SDK/provider write, live binding update or schema/activation. Existing order snapshot and history remain readable; missing matrix disables new activity. Tests target selected strict cells, revocation after prepared, own/foreign/missing/corrupt read, disable and no SDK/POST. Existing exact-file release, dependencies before route; own file/pin rollback, preserve quotes/orders/inbox and never reactivate old binding. UI checklist at later release: login/status preserved, new checkout remains OFF, refresh/status GET must not pay. No live UI check in this source package.


## 65. D2j — mód–deviza engedély és saját rendelés visszaolvasása (2026-11-02)

A közös `sharity_donation_quote_binding()` csak a kiválasztott mód/deviza literal true celláját engedi. A hiányzó/hibás mátrix új ajánlatot és dispatch-et tilt; a meglévő mód-, lejárat- és összeghatár megmarad. Prepared rendelés utáni visszavonás is nulla provider POST-ot eredményez. Történeti státusz nem függ a mai bindingtól.

A Notes GET `donations/status-by-quote/<dq_id>` az autentikált tulajdonos és kizárólag az URL quote-paramétere alapján olvas a meglévő indexen; ugyanazt a szigorú publikus projekciót használja, mint a korábbi státusz. Idegen/ismeretlen rendelés azonos404; sérült adat/DB-hiba503; attempted202; private,no-store. A web új `status-by-quote/[id]/route.ts` BFF-je a meglévő read-auth/forwardert használja, egy GET, nincs POST vagy retry. A404 még nem látható rendelés, nem bizonyít fizetési kudarcot. Nincs új séma, SDK-hívás, providerbeállítás vagy aktiválás.

Tényleges teszt:101 PHP quote,264 HTTP;950 valódi MariaDB rendelési/pénzügyi assertion (D2h/D2i regressziókkal),18 quote-DB eset;401 pénzügyi/CLI+461 adjustment és13 friss runtime;65 webes Vitest. PHP lint/diff-check sikeres. Valódi MariaDB10.11.19, SDK offline HTTP-vel; saját konténer eltávolítva és hiánya visszaolvasva. SQL read-only tranzakció, valódi idegen owner/DB-hiba/sérült envelope, kikapcsolás és későn megjelenő rendelés ellenőrizve. A N és A product diff független forrásreview-ja leletmentes; reviewer nem futtatott tesztet. Nincs új teljes tervaudit.

Hermes042 egy hívás, execution fda567a2-a8f4-4272-b92a-989043b384e0;28504 input+5721 output=34225 token; becsült0.003635086USD, estimate_not_invoice. Pending/terminal remote_ack; tényleges összköltség, Codex/reviewer díja ismeretlen. Három termékpatch átvéve; Codex javította a hibás, nem kiválasztott módra vonatkozó tesztelvárást/idempotenciakulcsot, pótolta a DB-fixture-t és tényleges adatbázisos eseteket, elkészítette a mechanikus web-BFF-et. Nincs retry/fallback/JEV vagy megtakarítási állítás.

Kiadás: saját product/docs commit, külön csak a három érintett Notes-hash; saját PR244/1870. Main csendablak-kérés válaszra vár, mainművelet nem történt. Tiszta merge-SHA-ból meglévő exact-file út: kompatibilis orders-projekció a HTTP-route előtt, matrix-resolver az új binding előtt, Notes fogadó a BFF előtt. Staging/production azonos byte, privát mentés/friss CAS/readback. Régi matrix nélküli binding nem aktiválható; nincs automatikus konverzió. Rollbackkor új indítás OFF, csak saját fájl/pin/alias visszaállítás, orders/quotes/inbox megőrzése; a korábbi engedékeny binding nem kapcsolhat vissza fizetést. UI-checklist kiadáskor: login és régi státusz megmarad, refresh egy GET, nincs automatikus checkout. Élő UI-próba most nem történt.

Helyben ellenőrzött forrás; merge/telepítés/aktiválás/élő pénzügyi elfogadás továbbra nincs. Következő: publikus NGO-elérhetőség és tényleges fizetési UI, account CLI/igazolás, tartós cross-order mozgások és közös fedezet; directHUF majd Amplifier, D3/D4/további devizák, affiliate/Offerwall és VB7/Sharing12. A teljes cél aktív. SUBSTACK-HANDOVER.md megőrzött hivatkozás.


## D2k — availability és fizetési kliens, indulás (2026-11-02)

Jóváhagyott§62, N7448e43f/A3079df97f. Hermes043 első tervezete javítva integrálandó. A meglévő fő modulban csak a kért NGO publikus mode/currency/bounds/árlista read; nincs accountadat vagy providerhívás. Meglévő form/parsers/copy és új kis kliens: külön ajánlat/megerősítés, URL-be mentett quote/nyelv a checkout előtt, bizonytalan válasz/frissítés kizárólag GET, prepared kézi folytatás. Fő kockázat: más összeg/NGO, dupla POST, hibás státusz, privát adatszivárgás. Teszt/review szükséges; eredmény még nincs. Meglévő kiadásban fogadó→kliens, inaktív binding; rollback saját file/pin, új indítás OFF, korábbi rendelés megmarad. Mainablak továbbra válaszra vár, nincs mainművelet.


## 66. D2k — publikus elérhetőség és bekötött fizetési kliens (2026-11-02)

A meglévő Notes fő donation-modul új GET availability/<slug> válasza csak a kért NGO mód/deviza/összeghatár/árlista adatait adja. Ugyanaz a binding-resolver, külön quotes/orders és schema readiness, részleges dependencyhiánynál tiltott indítás. Hiányzó devizás csomag csak az érintett Amplifier-cellát tiltja; az üres map JSON-objektum. Nincs NGO-enumeráció, accountazonosító, SDK/providerhívás, authlekérdezés vagy írás. A csomaglista korábbi végpontja változatlan.

A meglévő weboldal/form/parsers/copy és új NgoDonationClient a meglévő védett BFF-eket használja. Külön felhasználói ajánlatkérés és bruttóösszeg/deviza-megerősítés; a válasz NGO/mód/deviza/összeg/csomagszavazat/árlistaverzió egyezése ellenőrzött. Checkout előtt donation_quote és donation_language bekerül az URL-be, visszaolvasással; key=donation-checkout:<dq>. Dupla kattintás szinkron lockkal kezelt. Cookie/URL-mentés hiányában nincs POST. Megjelenítési preferencia nem fizetési kérés. HU/EN/DE/FR és meglévő design megmarad.

URL-es frissítés egyszer GET-et küld.404 még nem látható rendelés,202 egyeztetés,503/hálózati hiba bizonytalan; kizárólag kézi olvasás következhet. Prepared csak külön megerősítéssel folytatható ugyanazzal a quote/key/nyelv hármassal. Korábbi olvasás API-indítás OFF vagy elérhetőségi hiba mellett is működik. Open checkout csak ellenőrzött https://checkout.stripe.com/c/pay/ linkként jelenik meg; lezárt/lejárt sessionnek nem kell link. Nincs automatikus navigáció, új fizetés vagy fizetést igazoló sikerállítás. Élő accountbindinget/kapcsolót ez a kör nem állított át.

Tényleges ellenőrzés:129 PHP quote/availability assertion és13 friss runtime;134 webteszt öt fájlban (új kliens és read model, meglévő form/price/BFF regresszió). Kiválasztott mód/deviza, bounds, publikus mezők, nulla írás, hiányzó dependency, több mint200 NGO-nál is kért cél, quote–checkout egyezés, minden nyelv, frozen locale, URL-before-POST, dupla kattintás, reload/404/202/503/timeout, prepared, lejárat, CSRF, rossz NGO és támadó link. Scoped TypeScript hét érintett fájl+importok sikeres, no emit. Teljes web tsc NEM PASS: egy saját unknown→string hiba javítva; más ágakon hiányzó zod/MCP/csv függőség és elavult .next fund-preview-local hivatkozás megmarad. Közös node_modules/cache nem módosult. A D2j950+18 MariaDB-bizonyíték változatlan order/finance kódra újrahasznált, nem új DB-futás. Teljes böngészős/mobil/zoom/élő elfogadás későbbi kiadási feltétel.

Független product review két konkrét eltérése javítva/visszaolvasva: prepared CSRF-hiba megjelenítése és a canonical /c/pay/ URL-szerződés. Reviewer tesztet nem futtatott. Hermes043 egy hívás, execution d88c8752-0f48-4ac1-b199-f0d77fac5b7a;27337input+14109output=41446token, becsült0.0049995946USD, estimate_not_invoice. Pending/terminal remote_ack. Részleges átvétel, jelentős Codex-javítás: felesleges új modul/200 NGO-s truncálás, hiányzó dependencyvédelem/üres JSON map, hibás WP/BFF-út, státuszban nyers minorérték, hiányzó prepared-folytatás, hibás session-null-URL szabály, egyezés/four-language copy és nem működő tesztváz. Nincs újrahívás/JEV/fallback vagy megtakarítási állítás; tényleges összköltség ismeretlen.

Kiadás/rollback a§62/65 útján: saját product/docs commit és külön egyetlen main-modul hash; Notes receiver előbb, web kliens utána. Tiszta merge-SHA, staging/azonos productionbyte, privát mentés/CAS/readback; nincs új séma vagy release-rendszer. Vercel flag és NGO/mód/deviza binding csak igazolt account/díj/felelősség/fedezet+sandbox után aktiválható. Rollback új indítás OFF, saját file/pin/alias; meglévő order/webhook/napló megmarad. Maincsendablak kérdés még válaszra vár, mainművelet nincs.

Forrás tesztelt; commit/push/PR/Secretary exact SHA külön visszaolvasandó. Merge/telepítés/aktiválás/pénzügyi elfogadás nincs. Következő: cross-order mozgásregiszter és közös likvid fedezet, hiteles account CLI/binding, directHUF pilot, Amplifier/D3/D4, további devizák/affiliate/Offerwall és VB7/Sharing12. Teljes cél aktív. SUBSTACK-HANDOVER.md megőrzött hivatkozás.


## 67. Notes main-integráció — 2026-11-02

Arnold külön ötperces csendablakot engedélyezett kizárólag a Notes main lekérésére és saját #244 ág integrációjára. 19:22:57 UTC-től, a művelet és visszaolvasás után 19:25 UTC előtt felszabadítva; PR-merge/telepítés nem történt. Saját mergecommit `8c1800e9ebbc61c8d64e0a3a1f8d4a08bf24832b`, szülők `c2b01c8e4be09b9d016de5b855769d2f575d9168` és a tényleges távoli main `d5ff78fe966065bb5e478c2105a6e493f7528f31`. Tiszta munkafa és main-ancestor visszaolvasva. A fetch csak FETCH_HEAD-et frissített, a régi origin/main ref helyett a távolival egyező FETCH_HEAD került integrálásra.

Hat dokumentáció/hash ütközés rendezve. A Bástya, governance, notes és snapshot mindkét oldali bejegyzései megmaradtak. A két identity-hash a tényleges integrált PHP/JS-re mutat, más pin felülírása nincs. Az identity PHP byteazonos a mainnel; JS-ben csak a korábban tesztelt lokalizációs badge-jelölő és nickname-pending módosítás marad pluszban. A main shopping/marketing/affiliate változásai változatlanul átvéve; donation forrás nem változott.

Célzott ellenőrzés:72 PHP completion-eset, teljes meglévő Bástya-inventory nulla drift és6 hash-eset, majd38 Chrome-eset sikeres. Utóbbi a tényleges PHP/JS visszatérési teszt és nemzetközi preferenciateszt együtt, külső hálózat tiltva. A PHP teszt önmagában a canonical resolver ágat NOT RUN-ként jelzi; a böngészőteszt saját fixture bizonyíték, nem élő loginelfogadás. Első node-futás a Notes alatt nem talált Playwrightot; az előírt USER_CARD_TEST_DEPENDENCIES_ROOT meglévő web-függőségút feloldásával sikeres, telepítés/shared cache módosítás nélkül.

A merge commit csak a saját PR-ágat integrálja. Mainbe történő PR-merge, staging/production, aktiválás és pénzügyi elfogadás nincs. Az A main-integrációhoz külön csendablak kérve; válaszra vár. A D2k teszt/review bizonyíték változatlanul érvényes.


## 68. D2l — tartós pénzmozgás-regiszter, indulás (2026-11-02)

Jóváhagyott §62; N edf67c5b, A 07913e78b. Saját munkafák tiszták; csendablak nincs nyitva. Hermes045 első kidolgozása terminális, használható patch nélkül; újragenerálás helyett Codex folytatja. Érintett meglévő finance-ledger.php és finance-read.php, saját MySQL-harness és új célzott teszt. Az observation- és száraz inventory-szerződés megmarad; új, explicit séma és digesthez kötött CLI-regisztráció. A provider/livemode/tényleges account/balance transaction egyediség, teljes gazdasági tartalom-visszaolvasás és egyetlen rögzített mysqli-tranzakció védi a kettős felhasználást és részleges batch-commitot. Külső hívás és új pénzügyi könyvelés nincs. Ismeretlen platformaccount, fedezet és tényleges egyeztetés továbbra nyitott; readiness false.

Kockázatok: két rendelés ugyanazt a jóváírást igényli; megváltozott bizonyíték; elveszett commit-válasz; nem tranzakciós vagy hibás egyedi index; részleges kiadás. Célzott valódi DB-verseny, schema/drift, teljes batch rollback és bizonytalan eredmény-visszaolvasás szükséges. Új általános keretrendszer nincs. Kiadás a meglévő exact-file úton: ledger függőség → CLI → explicit finance-schema --apply, előtte saját fájl-/táblamentés/CAS; schema engine/index readback. Rollback új regisztrálás leállítása, saját korábbi fájl/pin, megfigyelések/regiszter megtartása; új események fölé DB-visszatöltés nincs. Post-deploy funkciólista: régi inspect/record/inventory, új explicit regiszter és replay, nulla provider POST/NGO-allokáció. Kézi UI checklist: nincs UI-módosítás; adományozási felület nem kap aktív fizetési/fedezeti állítást. Élő teszt és telepítés még nem történt.


### D2l ellenőrzött megvalósítás

A meglévő finance-ledger.php additív `{prefix}sharity_donation_movements_v1` táblája provider/livemode/tényleges account/balance transaction elsődleges kulccsal tartja a szűrt, kanonikus gazdasági tényeket, azok digestjét, orderazonosságát és első rögzítési idejét. Nincs megfigyelésekből összeadott bevétel, személyazonosító vagy mesterséges sorszám. Azonos rendelés/tény ismétlése az eredeti receipt; másik rendelés, deviza, forrás vagy gazdasági eltérés konfliktus. Az ismeretlen platformaccount tételei külön feloldatlanok maradnak. A payment_verified/allocation_eligible/settlement_reconciled/apply_supported továbbra false; a közös likvid fedezet és tényleges pénzügyi egyeztetés nincs ezzel kész.

A meglévő `payment-reconcile --order=<do_id>` GET/provider nélküli inventoryja determinisztikus `movement_proposal_digest` mezővel bővült, amely az ordert, bizonyítékhalmazt és gazdasági leltárt köti. Kizárólag `payment-reconcile --order=<do_id> --record-movements=<digest>` regisztrál; a generikus --apply továbbra tiltott. Új séma csak explicit `finance-schema --apply`, külön readiness és pontos oszlop/index/collation/engine ellenőrzéssel. Hibás új séma nem javul automatikusan és nem veszi el a korábbi observation-olvasót. HTTP migráció nincs.

Tranzakció előtt történik az inventory/validált order olvasása. Ezután egyetlen tényleges mysqli handle/thread, reconnect nélküli natív SQL; order → observation range → movement zársorrend. Az order teljes tartalma és a zárolt bizonyítékhalmaz SHA2/digest/meta egyezése szükséges. REPEATABLE READ range lock kizárja a közben hozzáadott bizonyítékot. Metadata lock alatt a három érintett tábla InnoDB, a regiszter pontos egyedi indexe ellenőrzött. A teljes batch INSERT/readback atomikus; duplicate csak minden immutable mező egyezése után elfogadott. Külső tranzakció/handlecsere tiltott. Bizonytalan commitnál nincs új kérés vagy reconnect; a tranzakció lezárása után minden elvárt tétel tartalmi visszaolvasása dönt. A bizonyítékok 256/4 MiB/16 MiB korlátja megmarad; az INSERT a tényleges packetkerethez igazodik.

Tényleges teszt: `SHARITY_DONATION_TEST_PORT=<saját-loopback> php -d memory_limit=512M tests/sharity-donation-orders-mysql.test.php`:1060 assertion, ebből110 új movement-regiszter. Valódi MariaDB10.11.19, külön kapcsolat/fork/barrier: két order egyetlen mozgásért, azonos order replay, metadata/forrás/deviza/összegeltérés, második INSERT és későbbi cross-order konfliktus teljes rollbackje, előjeles refund/reversal/dispute, két ismeretlen platformtétel kizárása, stale bizonyíték másik kapcsolatról, nested tranzakció/handlecsere, corrupt readback és engine/index/collation drift. Natív mysqli commit-before/after fault injection kezeli az elveszett választ; fizikai hálózatszakítást nem bizonyít.401 offline pénzügyi/CLI és13 friss runtime eset szintén sikeres, PHP lint/diffcheck rendben. Az SDK-adapter változatlan; korábbi adjustment-bizonyíték újrahasznált. Saját tesztkonténer eltávolítása és hiánya visszaolvasva; éles adat/szolgáltató nem érintett.

Javítási napló: egy érvénytelen szintetikus txn_AAA_NEW azonosító txn_AAANEW-re javult. A packet teszt először azonos hibakódot várt eltérő helyzetekre: célzott diagnózis szerint1 KiB-nál már a meglévő bounded evidence-olvasás nem teljes, ezért unavailable;4 KiB-nál az INSERT+4096 protokollkeret konkrét PACKET_TOO_SMALL hibát ad. Két külön teszteset, mindkettő nulla írással. A diagnosztikai forrásváltoztatás finally ágban byteazonosan visszaállítva; e tesztkorrekciók nem módosították a termékkódot. Független kód- és teszt-review (d2i_source_review) leletmentes, reviewer nem futtatott tesztet.

Hermes045: egy hívás, eef27554-c36c-49cc-b575-f31943f93c5d,34165 input+1498 output=35663 token; becsült0.003381651 USD (estimate_not_invoice), tényleges és Codex/reviewer összköltség ismeretlen. Pending/terminal remote_ack. A teljes result/text artifact technikai kérdéseket adott, használható patch nélkül; elvetett javaslat, Codex implementálta a jóváhagyott szerződést. Nincs újrahívás/fallback/JEV vagy megtakarítási állítás.

Funkciólista/release/rollback az indulási bejegyzés szerint; új HTTP, pont, szavazat vagy UI út nincs. Kiadáskor a meglévő NGO-kártyáról nyíló adományozási oldalon a korábbi nyelv/deviza és letiltott fizetési állapot maradjon; az admin regisztráció ne növelje a publikus NGO/adományalap összeget. Ez kézi kiadási checklist, most nem élő UI-PASS. Saját termék/docs commit, külön kizárólag két saját hash és oldalkocsi, #244 frissítés; A §68 DOCSYNC. Maincsendablak nincs nyitva; új mainművelethez Arnold külön engedélye kell. PR-merge/telepítés/aktiválás/valódi pénzügyi elfogadás nincs. Következő: tartós egyeztetés/foglalás és közös likvid fedezet, account/díj/felelősség igazolása, direct HUF pilot, Amplifier/D3/D4/további devizák és a teljes §62 cél. VB7/Sharing12 nyitott. Megőrzött hivatkozás: SUBSTACK-HANDOVER.md.


## 69. D2m — közös fedezet és prepared foglalások, indulás (2026-11-02)

Jóváhagyott §62; saját N7ec60041/A11dd47390, tiszta munkafák, nincs csendablak. Hermes046 részleges javaslatának megőrzött fragmentumai visszaolvasva, nincs újrahívás. Scope a meglévő finance-ledger/finance-read, az orders MySQL-harnesshez illesztett backing teszt. A draft sématopológiája, import-replay sorrendje, csökkent fedezet kezelése, tulajdonosazonossága és tesztfixture-je javítandó. Az igazolt DB-kapcsolat/kanonizálás mintáit megtartjuk; nincs új általános infrastruktúra.

Konkrét kockázat: egy beérkezés/saját biztosíték többszörös felhasználása, más NGO pénzével fedezés, elavult vagy csökkent fedezet elhallgatása, részleges commit. Explicit CLI-only, privát szűrt bizonylatimport, verzió/hash CAS, változatlan forrásazonosság, egyetlen rögzített mysqli tranzakció és teljes tartalmi readback szükséges. A verziózott frame megőrzi a korábbi importokat; az új frame nem töröl forrást/foglalást. Order/kind foglalás csak prepared, nincs release/execute/pay API; a későbbi attempted/unknown is foglal. Az operátori állítás nem független banki igazolás és nem aktiválás. A csökkent retained összeg új verzióként megmarad, deficit látható, új foglalás nem engedett. A donor/NGO forrás más rendeléshez nem használható.

Funkciók: finance-schema --apply additív táblák; backing-import/backing-reserve explicit digesthez kötött CLI; régi inspect/record/inventory/registry megmarad. Nincs HTTP migráció, provider POST, pont/szavazat, publikus könyvelés, pénzváltás vagy aktiválás. Tests: valódi kétkapcsolatos MariaDB-verseny, ismétlés, stale CAS/target, source/owner/currency, lowered/expired backing, rollback/uncertain commit/readback, schema drift és privát input.

Release a meglévő exact-file úton, saját fájl/config és konzisztens táblamentés/CAS; ledger → CLI → explicit séma → index/engine/hash readback. Függőség hiánya az új parancsot tiltja. Rollback új import/reserve indítás OFF, saját byte/pin; meglévő frame/source/claim/financial sorok megmaradnak, régi DB nem tölthető új eseményekre. Termék- és hashcommit külön, csak saját érintett pin. Kézi későbbi UI checklist: NGO donation oldal nyelv/deviza/login megmarad, a backend import nem jelenít meg fizetett vagy kifizetett adományt; nincs új aktív CTA. Most nincs élő UI/telepítési/aktiválási PASS. SUBSTACK-HANDOVER.md megőrzött hivatkozás.

### D2m ellenőrzött megvalósítás

A meglévő finance-ledger/read három explicit InnoDB táblával bővül: backing_frames_v1 (frame_key+revision és frame_key+digest egyedi), backing_sources_v1 (globális native source hash), backing_claims_v1 (claim_key és order_id+kind egyedi). A frame provider/livemode/account/deviza szerint elkülönül. A source azonossága nem tartalmaz devizát, így ugyanaz a tényleges forrás nem nevezhető át más pénznemű új pénzzé. A frame verziói és forrásazonosságok változatlanok maradnak; importkor nincs korábbi foglalást törlő replace/reset. A jelenlegi CLI csak a rendelés connected-accountjához tartozó Stripe-keretet kezeli; a még ismeretlen platformaccount/átvezetés nem válik ettől használhatóvá.

Pontos operátori út: `wp sharity donations finance-schema --apply`; `wp sharity donations backing-import --input=/private/path/proof.json` adja a proposal_digestet, ugyanaz `--apply=<digest>` ír. Foglalás: `wp sharity donations backing-reserve --order=<do_id> --input=/private/path/reservation.json`, majd ugyanaz `--apply=<digest>`. A sima --apply, extra mező/argumentum és másik order elutasított. A fájl kanonikus JSON (meglévő finance_json), legfeljebb64KiB, saját tulajdonú0600 regular inode, nem symlink/hardlink; nyitás után inode/mode/owner/size egyezés. Ismeretlen mező, lebegőpontos pénz, nyers szolgáltatói/customer/bankadat nincs az elfogadott szerződésben.

A proof version1 frame-je: provider=stripe, account_id, livemode, currency, as_of_utc/expires_at_utc UTC, retained_minor, external_liability_minor, external_liability_proof/retention_proof (opaque ref+sha256), confirmed=true, previous_revision és previous_digest (első0/null). A sources minden korábbi forrást is tartalmaz, nullára csökkentett történetit is; kind/order_receipt vagy own_collateral, native_id, order_id (saját biztosítéknál null), principal_minor, retained_minor, evidence/retention ref+hash, ownership és időbélyegek. Order-receipt pontos D2l-native tranzakcióhoz, order/account/livemode/deviza/digesthez és tényleges pozitív nettóhoz kötött. Own-collateral explicit Sharity-tulajdon és elkülönítés-bizonyítékot követel, ngo_funds/common_fund=false; ismert adománytranzakció nem minősíthető át. Operátori állítás, nem független banki vagy provider-igazolás. Az éles aktiváláshoz ténylegesen megtartott pénz és teljes díj/felelősség-vizsgálat továbbra szükséges.

A reservation version1: order_id, kind=liability/correction/payout, frame_key/revision/digest, target_generation, amount_minor, allocations(source_key+amount_minor). A target_generation a meglévő order+evidence_set_digest+financial_inventory_digest szerveroldali hash-e. E csomag admin foglalást készít, nem számít végleges nettó pénzügyi célösszeget és nem enged végrehajtást. A következő egyeztetési csomagnak kell hitelesen levezetnie a teljes kötelezettséget, célösszeget, majd az aktuális keretet ismét ellenőriznie. Az order/kind egyediség kizárja másik cél/kulcs alatt ugyanazon rendezetlen művelet megismétlését; csak prepared keletkezik. Nincs release/execute API; prepared/attempted/unknown egyaránt foglal. Történeti receipt/time visszaolvasható. Import-replay az eredeti proof receiptet és külön címkézett aktuális capacity_frame_revision állapotot mutatja, nem új verziót vagy felszabadítást.

A számítás exact15jegyű minor-sztringekből ellenőrzött egész aritmetikával történik. Globális keretből az összes más accountteher és minden foglalás egyszer vonódik le, külön a natív forrás maradéka is ellenőrzött. Kisebb retained új verzióként menthető: negatív available/pozitív deficit látható, új foglalás tiltott. Forrást elhagyni vagy retainedet a korábbi érték fölé visszaemelni nem lehet; új beérkezés külön hiteles native forrás. A nulla retained lejárt történeti sor megmarad, nem fagyasztja az új pénzt; pozitív lejárt fedezet új foglalást tilt. Korlát:256 forrás/allokáció,64KiB proof/claim,1024 rendezetlen claim/frame; túllépés explicit LIMIT, nem csonkolt siker. Ezek operátori pilotkorlátok; nincs automatikus payout, feldolgozási queue vagy erőforrás-bővítés.

Egy pinned mysqli handle/thread, reconnect nélkül: közös frame → source → order → megfigyelés/movement → claim. D2l a már létező backing-séma esetén ugyanazokat a source mutexeket veszi fel az order előtt; hiányzó új séma mellett a korábbi registry működés megmarad. A foglalás a jelenlegi movement-regisztert ismét ellenőrzi. Ha az operátori saját-fedezet állítását későbbi tényleges donation movement cáfolja, az igazolt movement megmarad, a korábbi hold nem törlődik, de új vagy ismételt foglalás SOURCE_UNPROVEN-nel egyeztetést kér. Az ilyen hold nem végrehajtható fizetési engedély. A metadata/engine/index ellenőrzés, exact CAS és tartalmi readback ugyanabban a tranzakcióban véd. Elveszett commit-válasz után teljes receipt/source visszaolvasás dönt; nincs új művelet/reconnect/retry. A proof/index sémahibája nem javul automatikusan. Minden provider_verified/retained_cash_verified/settlement_reconciled/activation_allowed jelző false marad.

Tényleges ellenőrzés:1187 assertions a meglévő orders MariaDB-harnessben, ebből127 új backing. Külön valódi connection/fork/barrier: két order egyetlen biztosítékért, ugyanazon claim versenye, globális és forrásonkénti korlát, külső teher, cross-NGO/account/live/currency tiltás, csökkent/lejárt fedezet, eredeti proof replay későbbi új forrás után, stale evidence másik kapcsolatról, késői és párhuzamos D2l-register/backing-reserve. Fault injection: commit előtt/után elveszett válasz, félbeszakadt source/frame batch és hibás/oversize readback; nem fizikai hálózatszakítási próba. Explicit schema replay/drift, privát file és CLI-negatív esetek is sikeresek.401 offline pénzügyi/CLI +13 friss runtime, PHP lint és diffcheck sikeres. Nincs élő/sandbox pénz, provider POST, új order/public allokáció. A korábbi változatlan webes11dd47390 pontos head impact/security/product/lint/visual CI checkjei sikeresek; skipped nem PASS.

Független review: a nulla retained történeti lejárat, a SELECT * melletti ál-korlátozás és a késői source-átminősítés konkrét leletei javítva, célzott regresszióval. A reviewer nem futtatott tesztet. Hermes046 execution19dc8f0a-a965-4458-8b0d-5156b62999df, input4b88a210eb0eade9e30a45c01d2cf590561f7dbbe659e7e5b056dbcdbdceb350; egy hívás, output_token_limit/partial. Mindkét megőrzött assistantfragmentum és a result/text olvasva, nincs összefűzött duplikáció. Csak a szűk CLI/proof/schema váz szándéka hasznosult; jelentős helyi újraírás kellett a hibás séma, dry-run írás, replay/CAS, forráskapacitás, hiányzó saját-tulajdonvizsgálat, deficit és használhatatlan tesztfixture miatt. Tokenhasználat és költség unknown (a runtime0 értékei nem nullaköltség-bizonyíték); nincs JEV, újragenerálás vagy fallback. Pending/terminal Secretary remote_ack; Codex/reviewer teljes ráfordítás ismeretlen.

Release/rollback és kézi checklist a §69 indulási rész szerint; actual schema/apply/input ezen bejegyzésben pontosítva. A két saját termékfájl külön hashcommitja és PR244, A DOCSYNC/PR1870 következik. PR-merge, telepítés, aktiválás és pénzügyi elfogadás nincs. A következő csomag tényleges nettó cél/egyeztetési generáció, teljes kitettség és végrehajtási foglalás; továbbra account proof/directHUF, Amplifier/D3/D4, többi deviza, affiliate/Offerwall, nyelvi release és VB7/Sharing12 a teljes aktív cél része. Csendablak nincs nyitva; új mainművelet előtt külön jóváhagyás kell.


## 70. D2n — tartós, tényleges pénzmozgásokhoz kötött elszámolási cél (indulás)

Kiindulás N8084ea4a / Ab612f05e3, tiszta saját munkafák. A korábban engedélyezett a35c02e2 integráció már f18916d91-ben van; új mainművelet nincs, csendablak szabad. Hermes047 egy új, még meg nem oldott csomag első kidolgozója; 046 terminális eredménye nem indult újra.

Koherencia és scope: a meglévő finance-ledger/finance-read és izolált MySQL-harness bővül. A saját eredeti rendelési policy és D2l egyedi mozgásai adják a nettó elszámolási célt; verziózott, változatlan egyeztetési bejegyzés kapcsolja a bizonyítékot a következő fedezett művelethez. Megfigyelések nem összegezhetők pénzként. Érintett: explicit finance-schema és operátori payment-reconcile CLI. Nincs HTTP/shortcode/cron, checkout/session, szavazat/pont, közösalap vagy banki/Stripe írás. A backing foglalás önmagában továbbra nem igazolja a teljes kitettséget.

Konkrét kockázatok: díj kétszeri levonása; devizák összekeverése; refund/dispute duplikáció; régi cél késői mentése; hiányzó platform/NGO-ellenoldal vagy jövőbeni kötelezettség nulla értéknek feltüntetése; részleges/ismételt commit. Meglévő rögzített kapcsolati, explicit séma-, kanonikus digest- és teljes readback minták újrahasználata; új általános infrastruktúra nélkül. Ismeretlen teljes kötelezettség külön null/unknown, a tényleges deficit nem helyettesíti. Az aktiválás és kifizethetőség nem lesz igazolt pusztán a cél mentésével.

Ellenőrzés: valós izolált MariaDB, pontos nettó és előjeles korrekció, registryazonosság, devizahatár, két kapcsolat/stale/ismétlés/bizonytalan ACK, sématiltás és nulla provider POST. Pénzügyi forrásreview az integrált diffen. Saját termék- és védelmi hash külön commit; távoli branch/PR readback.

Release/rollback: a meglévő exact-file út, csak merge-elt release-SHA; előállapot és saját fájl/tábla mentése, backend kikapcsolva, explicit additív séma, readback. Korábbi pénzügyi sorok és rendelési utak megmaradnak; új eseményeket nem írunk felül régi DB-mentéssel. Kézi UI checklist telepítés után: meglévő adományoldal két választása, HUF összeg, bejelentkezés/saját státusz és nyelvváltás változatlan; az új CLI-eredmény ne jelenjen meg kész/kifizetett adományként. Ebben a csomagban élő UI-próba/telepítés még nincs.

### D2n tényleges eredmény

A meglévő finance-ledger/read bővült; új explicit `finance-schema --apply` tábla: `{prefix}sharity_donation_targets_v1`, PK(order_id,revision), UNIQUE(order_id,digest), pontos InnoDB/index/collation. `wp sharity donations finance-target --order=<do_id>` csak dry-run; ugyanaz `--apply=<proposal_digest>` mellett ment. A `payment-reconcile` régi CLI-szerződése változatlan. Nincs HTTP-migráció vagy providerhívás.

Változatlan dokumentum rögzíti az eredeti policy-t, teljes bizonyíték/mozgáshalmaz hashét, devizánkénti előjeles bruttó/tényleges költség/alkalmazásdíj/nettó összegét, a közvetlen mód előzetes NGO-célját és a D2m target-generation kapcsolatát. A 20%-os alkalmazásdíj nem második feldolgozási költség. Más fizetési devizájú egyetlen elszámolási deviza az eredeti elszámolási jelöléssel marad; többdevizás mozgásnál minden bucket megmarad, nincs összeadás vagy kitalált FX. Negatív nettó signed marad, külön deficit és nulla előzetes kifizetés. Amplifier végleges 80/20 cél továbbra null: platformaccount és hiteles NGO-ellenoldal a jelen adapterben nem igazolt. Teljes jövőbeni kötelezettség `null/unknown`, nem nulla és nem azonos a megfigyelt deficittel. Minden readiness false; ez még nem végrehajtható, teljesen fedezett adomány.

Order-lock alatt friss order/evidence és a teljes valódi registryhalmaz/body SHA ellenőrzött; hiányzó vagy eltűnt mozgás hiba. Append-only revision és külön economic_generation: status-only új megfigyelés nem új pénz/gazdasági generáció. Ismétlés eredeti időpontot ad, korábbi proposal nem írhat újabb elé. Pinned mysqli, egy tranzakció, teljes commit-readback; nincs provider a lock alatt. Legfeljebb4096 scoped mozgás/cél és4MiB dokumentum; explicit limit/packet hiba, nem csonkolás. A D2m holdot e csomag nem engedi el és nem végrehajtási jogosultságként használja.

Valós izolált MariaDB: **1274 assertion**, ebből**87 új target**; teljes direct és Amplifier rendelés mentés/visszaolvasás, signed refund/reversal/dispute és díjcredit, devizahatár, metadata-only generáció, teljes registry-body, két kapcsolat egyszeres cél, stale evidence, native commit előtti/utáni ACK/insert/corrupt-readback, explicit séma és CLI. Ez célzott hibainjektálás, nem fizikai hálózatszakítás. **401 offline pénzügyi/CLI és13 friss runtime**, PHP-lint és diff-check sikeres. Független forrás-/tesztreview leletmentes; reviewer nem futtatott tesztet. A későbbi87-es futás a persisted Amplifier-esettel egészítette ki a korábbi83-as tesztet, termékkód-változás nélkül.

Hermes047 egy hívás, completed; execution070b2be0-f894-4b9b-af90-4fa5c678a0cd, inputfde5cc85e71c061f915e7b83f525b807e753a2790a8ab0495b3c0d29e97211d2.48027input+11494output=59521token, **becsült0.006462365USD**, tényleges számlázás és teljes Codex/review ráfordítás ismeretlen. A szűk CLI és immutable target/schema szándék részben átvéve; helyi javítás/egyszerűsítés kellett a kihagyott devizák, hiányzó direct cél, duplikált/hibás összesítés, tranzakcióból korai return, részleges registry-validálás, readback és használhatatlan tesztfixture miatt. Nincs újrahívás/JEV/fallback. Pending és terminal delegálási memória remote_ack.

Állapot: saját forrás és célzott tesztek elkészültek; commit/push/readback és két saját baselinehash következik. Merge, telepítés, aktiválás, tényleges pénz/kifizetés nincs. Következő konkrét hiány: platform-account és végrehajtott díj/NGO-ellenoldal bizonyítéka; teljes kötelezettség és fedezett műveleti állapotgép. Nem kérünk újra már megerősített banktulajdont. A teljes §62 cél változatlan; részállapot nem teljes elfogadás.


## 71. D2o - verified connected-account binding and targeted disable (implementation)

Pre-change source N4415b0f3, Aeffd2049a, both clean; no main operation or quiet window.
Approved scope: existing stripe-checkout.php and finance-read.php gain sanitized GET-only
account inspection and CLI-only CAS binding/disable for sharity_donation_accounts_v1.
Quotes and pre-dispatch retain their shared binding/mode/currency check; historical
orders/status/webhooks keep their original immutable account. No schema migration,
provider POST, payment, wallet, points, vote or allocation operation is added.
Risks: confusing platform with NGO, unknown fee/loss exposure, leaking provider PII,
losing another NGO update, stale retries undoing disable, WordPress option cache staleness.
Use pinned SDK, selected fields, strict private evidence, native atomic option CAS and
full readback. Existing helpers and option map are reused; no new release framework.
Direct binding needs account-paid processing, Stripe-connected-loss responsibility,
explicit documented zero platform own cost/liability, currency/representative/retention
and readiness references. These are operator attestations, not automatic provider or
financial acceptance. Amplifier enabling remains unavailable until its execution path
is complete; its targeted disable remains supported. Global switches do not change.
Planned checks: actual SDK mock transport and sanitized errors; disposable native MySQL
CAS races/replay/uncertain writes; quote/checkout/readback regressions; fresh-process
partial installation; only the two own Bastyas hashes after tests and independent review.
Release: existing exact-file staging then production from accepted merge SHA, private
file/option backup and current target hash CAS. Binding requires fresh actual proof and
separate runtime verification; no real account has been bound by this source package.
Rollback: disable new target mode/currency first; restore only own source from verified
poststate; preserve bindings/history/order/events and later disables. Do not restore an
old option map across later updates. UI checklist after release: own NGO donation page,
HU/EN/DE/FR, payment currency and login; disabled cell unavailable; another cell/NGO
unchanged; prior order remains readable. Live UI/deploy/activation not yet tested.

### D2o implemented contract and actual verification

`wp sharity donations account-inspect --account=<acct_id>` uses the existing pinned
Stripe22.1.0 / API2026-10-28.endive adapter. GET /v1/account then /v1/accounts/<id>,
no inherited account/context, no automatic retry. Own platform is rejected as NGO.
V1 livemode derives from the verified configured key; a provider livemode field, if
present, must agree. Output contains only IDs, key mode, selected capability/flags,
requirement counts/disabled state, fee/loss controller, default currency and observed
UTC. Missing facts remain null; activation_allowed remains false. Raw names, email,
bank data and provider exceptions are excluded. Card payments, not transfers, is the
required direct-payment capability. Default currency is not a multi-currency permit.

`account-bind --ngo=<slug> --input=<private_json_path> --expect=<version_or_absent>`
reuses the canonical private proof reader (owner-only regular nonlinked file, <=64KiB,
canonical JSON), validates before GET and revalidates expiry after GET. Binding payload
requires provider observation <=30seconds old, identity/key/default-settlement currency,
charges/payouts/details enabled, active card_payments, no missing/due/pending/error
requirements, account-paid fees and Stripe payment-loss responsibility.
Input exact fields: version=1, ngo_slug, account_id, platform_account_id, boolean livemode,
verified_at_utc/verified_until_utc, settlement_currency, payout_currency, fee_payer=account,
losses_payments=stripe, platform_costs_minor="0", platform_liability_minor="0", currencies
map with min_minor/max_minor strings, mode_currencies direct/amplifier maps, confirmed=true,
evidence map of representation/fee_terms/currency_support/platform_costs/retention/readiness.
Each evidence entry is only opaque ref + sha256, tied to this operator-reviewed document.
No raw identity/bank/key is accepted. Nonzero or unknown platform exposure needs its own
financial solution; this command does not finance it. Evidence references are operator
attestations, not automatic independent verification or financial acceptance.
Only direct cells can be enabled by this release; every true cell needs tested bounds
and evidence for its payment/settlement/payout combination. Amplifier true is refused.
No global quote/order switch changes; live release still requires sandbox/actual proof.

The WordPress serialized NGO option map is preserved. The whole current raw value is
compared atomically using native mysqli BINARY CAS; first insert relies on actual unique
option_name index. No read/update race, reconnect, nested transaction, DDL or automatic
retry. 1MiB option limit is explicit. Another NGO's concurrent update cannot be lost;
an operator may explicitly retry after an unconfirmed write and fresh readback. Sealed
binding version and request digest make an identical request replay retain the original
receipt, while an old bind cannot undo a later disable. Uncertain write is decided by
full native readback. Own option/alloptions/notoptions caches are invalidated after a
potential write. Existing quote and pre-dispatch readers consume the unchanged map.

`account-disable --ngo=<slug> --mode=<direct|amplifier> --currency=<HUF|EUR|USD|GBP|all>
--expect=<binding_version>` uses the same CAS without provider calls. It disables only
selected cells; sibling currencies/NGOs and historical account/session records remain.
No fee correction, money movement, points, votes or allocation occurs.

Actual tests:90 account assertions (58 SDK/CLI/proof +32 native MariaDB),197 existing
checkout,401 finance/CLI,13 fresh-process runtime,129 quotes; syntax/diff checks pass.
MariaDB10.11.19 was an existing image; no image pull/install. Real separate connections
and fork/barrier cover identical requests, competing NGOs, bind-vs-disable, stale replay,
prior disable preservation, nested-transaction rejection and cache paths. Fault injection
covers write-before, lost write ACK and corrupt readback; not physical network loss.
The synthetic provider clock advances through both GETs, proving post-request freshness.
Independent d2i_source_review final source/test readback: no findings; reviewer did not
run tests. The prior financial plan audits are reused; no domain-scope redesign.

Hermes048 execution4f4f75b6-f611-4d1b-a5d6-0178d5192f4b, one completed call:
19202input+9360output=28562tokens; estimated USD0.0033949916, actual invoice and total
Codex/reviewer cost unknown. Pending/terminal Secretary remote_ack. Sanitized inspection
and CLI/CAS intent partially reused; raw draft rejected because SDK property/setter use,
key-mode parsing, option map/SQL placeholders, absent/replay/CAS, missing exposure proof,
private-file reader and test skeleton were incorrect. No retry/JEV/fallback. Local bind
clock corrected before tests; account tests passed on first execution. Initial Docker
short tag was absent; native image inventory identified the existing exact10.11.19 tag.

Live read: user-selected Chrome Stripe page confirms the Zrt platform. Connector's
connected account list is empty; no NGO binding was created. Browser follow-up was
stopped by approval review after an unrelated-window/change risk; no workaround or
external mutation. Foundation bank ownership is already user-confirmed, not re-asked.
Separate Foundation Connect onboarding/account proof still required. No new main window,
merge, deployment, activation, real payment or financial acceptance occurred.
SUBSTACK-HANDOVER.md remains a preserved reference, not an active authoring task.


## 72. D2p - account-scoped platform financial movements (implementation)

Pre-change N b93bc5391, A 3d5c2c410, both clean. No main window or main operation.
Approved existing donation financial path: retrieve actual platform identity, retain it
in sanitized observations, register application-fee/refund transactions in the existing
account-scoped registry, and preserve separate connected/platform target totals.
Hermes049 is first drafter; Codex integrates, tests and obtains targeted source review.
Coherence: fee.account denotes NGO, never platform. Legacy observations remain immutable;
only a matching newly verified movement can remove duplicate unscoped representation.
Risks: identity substitution, double counting historical+new evidence, cross-order reuse,
wrong-account collateral and treating platform debit as NGO credit. Reuse existing SDK
pins, exact money, native registry/source locks and full readback; no schema or new guard.
Unknown counterleg/liability keeps Amplifier unresolved. No provider POST, activation,
order/points/votes/fund mutation. Existing direct path must remain compatible.
Planned checks: mocked pinned SDK GET identity/scope/mode/no PII; native MariaDB mixed
legacy/new facts, replay/conflicts/account isolation and separate target totals.
Release remains exact-file from accepted merge SHA, dependency first, same staging bytes
production, private source backup/CAS/readback. No live release in this package.
Rollback restores only own unchanged release files; observations/registered movements and
later financial events remain. Manual post-release UI checklist: direct and Amplifier
availability unchanged, own order status readable, four languages and prior checkout
return unaffected. No UI acceptance claimed by backend tests.

### D2p implemented contract and verification

Existing adapter now adds one platform-scoped GET /v1/account on the paid Amplifier
path only: max6 original /42 adjustment GETs, API2026-10-28.endive, SDK22.1.0,
zero retries/idempotency keys/provider POST. Frozen-order key mode validation remains;
account object/id/optional livemode, account!=NGO and inherited context are checked.
Only original.platform_account_id is retained; no contact/bank/raw account payload.
The second original read detects a platform change; direct projection/GET count unchanged.

Existing immutable ledger/registry now retain actual platform identity for fee/refund
movements. Missing historical identity stays unknown. A verified native transaction
supersedes the legacy unscoped view only with exact same non-account economic facts;
both provenance observations remain and contradictory facts fail. No historical row
rewrite or inferred scope for an unobserved legacy leg. Existing native source mutex,
provider/live/account/transaction uniqueness and full batch/readback are reused unchanged.
Target computation keeps platform account/currency buckets separate from connected
payment totals, without double-counting application fee as income/cost. Direct target
remains unchanged; Amplifier counterleg/liability unresolved, all readiness false.
No new schema/helper/framework, no live binding or financial operation execution.

Actual tests: PHP lint and diffcheck; original finance/CLI437; adjustments495;
checkout197; fresh runtime13. Real disposable MariaDB10.11.19:1340 assertions total,
including D2p66 (target153 total), movement110, backing127, reconcile655. Two native
connections reject cross-order reuse; same transaction ID on a different actual account
is separate; mixed legacy/known facts, missing registry, exact replay and separate
platform currency verified. Existing uncertain-commit/fault tests are injected failures,
not physical network failures. No sandbox/live money, deployment or UI acceptance.
Independent d2i_source_review inspected actual source and three test diffs, final no
findings; reviewer did not execute tests. No new full plan audit needed for this
already-approved account identity gap; prior financial audits remain applicable.

Hermes049 execution ee630e9b-dfed-4867-b381-98acafb64130, task SHA
 a91f86400da6e71b4ea95852b69bd948e0717066e8c5bf0ecc357cf65935a3e4:
1completed call,42522input+6571output=49093tokens, estimated USD0.0050095136;
actual invoice and total Codex/reviewer cost unknown, no savings claim. Concept and
selected target/identity structure partially used. Codex repaired scope validation,
legacy contradiction handling/provenance, direct compatibility, and completed actual
SDK/native tests absent from draft. No redispatch/JEV/fallback. Pending/terminal
Secretary memory remote_ack. First SDK test attempt exposed two old five-GET assertions
(bound and ordered injected failures); diagnosis was stale fixture graph, updated to the
new six-GET path including account, preserving no-retry checks; final run passed.

Current live account read (separate authorized read-only check) still finds Zrt with
zero connected NGOs. Foundation bank ownership is already confirmed by Arnold, not an
NGO-account verification. Next financial work: verified NGO fee-refund counterleg,
complete liability, actual covered correction lifecycle and Foundation onboarding;
then direct HUF pilot, Amplifier votes/fund, other currencies/history/remaining plan.
Source-ready for this package, not full goal, merge, deployment or activation.
Own exact source/hash publication and remote readback recorded separately in Secretary.
SUBSTACK-HANDOVER.md retained as reference only.


## 73. D2q — exact NGO counterleg and provisional net80/20 (2026-11-02)

Pre-change scope: existing stripe-checkout.php fee-refund evidence and finance-ledger.php
movement/reconcile/target projection; existing SDK/actual-MySQL tests. Base4b2a1e96.
Concrete risk: platform debit mistaken for NGO credit; duplicate/interaccount money counted
as donor proceeds; missing refund/currency/cost relationship yielding an unfunded split.
Only exact provider source identity under frozen NGO account is accepted; no amount/time
matching, invented API fields, new schema/helper framework or live provider writes.
At most20 extra GETs, no pagination/fallback or direct/no-refund increase; excess remains
unresolved. Provider docs support exact source-filtered balance lists but do not guarantee
that this identifies every NGO fee-refund counterleg. Real sandbox acceptance is still required.
Both native sides must be coherent before provisional80/20; full liability/activation remain
unknown/false. Existing direct path/history preserved; actual internal credits are not new gross.
Functions affected: retrieveAdjustmentEvidence/private counterleg projection,
sharity_donation_finance_movement/reconcile/target_compute and existing tests.
Manual release UI checklist: unchanged donation availability/login/prior owner status, four
languages/payment currency, no false success or fund increase; no live UI acceptance claimed.
Release follows existing merged-SHA exact-file staging/production path; old ledger first-read
compatibility requires deploy new ledger reader before new adapter observations. Backup own
files/config; no schema migration. Rollback stops new observations first, preserves stored
financial evidence/registry/targets and uses compatible reader; no DB restore over new events.
### Implemented contract and verification

The adapter conditionally reads at most20 exact-source NGO balance transactions, with
frozen account, zero retries/provider POST and no direct/no-refund extra GET. Empty,
ambiguous or unsupported mapping remains unresolved. Full projected credit evidence,
not a boolean assertion, enters the existing immutable observations/native registry.
Exact legacy absence can be resolved by a later observed native leg; conflicting refund
facts/IDs fail. Actual interaccount credit is excluded from donor gross. Same-currency
provisional net80/20 requires matching source sets and exact equality to the combined
actual account net, including actual platform costs once. Incoherent executed FX retains
raw money but yields no split. Full future liability remains unknown; all readiness false.
No schema, financial execution, fund/vote/order mutation or activation was added.

Actual checks: PHP lint/diff;437 finance/CLI,521 adjustment assertions including26 new
D2q SDK cases;197 checkout,13 fresh-process runtime. Real isolated MariaDB10.11.19:
1407 assertions total, including67 D2q,220 target,655 reconciliation,110 movement and
127 backing. Counts overlap within the suite, not additive totals. Coverage includes
exact native identity/sign/account,20/21 GET bounds, absent/ambiguous credits, nominal
and actual currency, legacy resolution, full refund/dispute, platform fee, missing
registry and replay. Existing uncertain-ACK tests are fault injection, not actual network
failure. Actual provider/sandbox counterleg mapping and live money are not proved.
Independent d2i_source_review found differing executed-FX amounts could inflate the
provisional target; Codex added the exact combined-net check and native INITIALDIFF /
REFUNDDIFF cases. Final source/test readback found no remaining blocker; reviewer did
not execute tests. One missing fixture bracket failed lint before correction; subsequent
native behavioral run passed. No new full plan audit; prior two financial audits reused.
Own tmpfs/loopback test container removed, absence verified; no image pull/shared deps.

Hermes050 execution4a37ce6f-32eb-4d63-949b-d634264f90d5, task SHA
 ebe799297c0ba1f14c39edcbe463da047201f28ba6595cb0652e4201f0d76264:
1completed call,40793input+3717output=44510tokens,768cached, estimated USD0.0043327466;
actual invoice and total Codex/reviewer cost unknown, no savings claim. Exact-source
lookup intent partially reused; sign/projection/GET-budget, missing ledger/80-20 and
invalid tests repaired or implemented by Codex. No redispatch/JEV/fallback. Initial
automatic approval rejected transmission before execution; verified public repository,
HEAD-identical selected source and sanitized synthetic key fixtures supported a newly
approved dispatch. Only one actual call occurred. Pending/terminal delegation remote_ack;
final product checkpoint is separate and requires readback.

Source publication uses only own files and separate two-pin baseline. Existing PR244/1870
remain draft; no main operation or open quiet window, deployment, activation or financial
acceptance. Next: full-liability/covered correction lifecycle, separate Foundation NGO
account verification, direct HUF acceptance, then Amplifier votes/fund and remaining scope.
Foundation bank ownership remains confirmed; no duplicate onboarding question or live write.
SUBSTACK-HANDOVER.md retained as reference only.


## 75. D2r — értesítéstől pénzügyi megfigyelésig (előkészítés, 2026-11-02)

Kiindulás N d45d536b / A fd058397a, tiszta saját worktree-k. Az előző R2
UserCard-only csomag forrása feltöltött; a teljes §62 pénzügyi cél változatlan.
A következő konkrét hiány: a hiteles Checkout-inbox és a meglévő pénzügyi
GET/napló/mozgásregiszter/cél még külön kézi lépés. Hermes052 az első kidolgozó;
a javaslat nem teszt vagy átvétel.

Koherencia és érintettség: csak saját donation webhook.php, runtime.php,
finance-read.php és célzott tesztek. A négy meglévő Checkout-eseményből korlátos
admin drain és külön WP-Cron callback készül; nincs automatikus ütemezés vagy
HTTP-migráció. A meglévő inbox additív, explicit frissítése megőrzi az eredeti
azonosságot és a régi recovery-t. A pénzügyi napló/regiszter/cél meglévő
szerződését használja; nincs általános queue vagy új külső POST.

Konkrét kockázatok: kétszer feldolgozott értesítés, elveszett nyugta, migráció
után kieső régi recovery, későn érkező esemény téves lezárása, ismételten hibás
sor miatti éhezés, túl hosszú SDK-hívás és más Stripe-plugin HTTP-állapotának
módosítása. Célzott valódi DB-/offline SDK-teszt és független forrásreview kell.
A legfeljebb öt rendeléses futás 60 s szolgáltatói keretet és rövid HTTP-timeoutot
kap; a transport minden kilépésnél visszaáll. Nem állíthatunk pénzügyi véglegességet,
könyvelést, kifizetést, szavazatot vagy aktiválást.

A teljes refund/dispute/account/payout eseménykör, kiesett értesítések periodikus
pótlása, saját pénzügyi státuszfelület és végrehajtható fedezet továbbra megvalósítandó.
Kiadás: meglévő exact-file, előállapot-hash és privát mentés; kompatibilis runtime/
webhook/CLI, explicit additív séma, readback, majd külön scheduler-beállítás.
Rollback: csak saját fájl/ütemezés, új inbox/finance adat nem törölhető, régi
checkout/webhook/owner status marad. Funkciópróba: signature/recovery/history,
hiányos és teljes pénzügyi olvasás, dedupe/verseny/hibajavítás, időkeret és nulla
provider POST. Kézi UI-checklist kiadáskor: NGO-adományoldal, login és saját
státusz négy nyelven; nincs téves kész/kifizetett állítás, preferenciaváltás nem
indít fizetést. Most nincs merge, telepítés, scheduler vagy aktiválás.

### D2r tényleges eredmény

A végleges termékdiff csak webhook.php és finance-read.php; runtime.php változatlan.
A webhook-inbox három nullable mezővel bővül: observation_digest, observed_at_utc,
last_attempt_at_utc. A régi és az új pontos séma elfogadott; ismeretlen séma tilt.
DDL kizárólag `wp sharity donations webhook-finance-schema --apply` parancsra;
hiányzó vagy extra argumentum nem ír. A meglévő `webhook-schema` is elfogadja az
új alakot. HTTP-betöltés nem migrál, ütemezés nem jön létre automatikusan.

`wp sharity donations finance-drain` vagy a külön névterű cron callback legfeljebb
öt rendelést és rendelésenként öt függő értesítést dolgoz fel. Az existing options
opaque kurzora natív CAS/readback alapján halad és körbefordul. A legutóbb próbált
esemény ideje külön marad a sikeres megfigyeléstől: a hibás esemény megőrződik,
de nem szorítja ki tartósan az ugyanazon rendeléshez tartozó későbbit.
A meglévő recovery, kanonikus finance-record, registry és immutable provisional
target szerződés után csak a futás elején kiválasztott esemény kap teljes receipt-
ellenőrzött markert. Nem fizetett állapot függő marad, nem nulla adományként zárul.

A privát, lazán betöltött SDK-transport csak hivatalos Stripe GET-et enged,
rendelésenként legfeljebb63-at, retry nélkül. Az egész batch szolgáltatói kerete60s,
HTTP-timeout legfeljebb5s/connect2s, a maradék kerettel szűkítve; kilépéskor a
korábbi transport visszaáll. A keret nem folyamatmegszakító és nem bizonyít kemény
60s teljes futásidőt: a helyi DB-művelet/zárvárakozás ideje külön futási feltétel,
éles scheduler-próbában ellenőrizendő. Nincs provider POST, pénz-/szavazat-/alap-
végrehajtás vagy új pénzügyi jogosultság; readiness false marad.

Tényleges végső ellenőrzés: izolált MariaDB10.11.19, két kapcsolat/fork/barrier,
1767 assertion PASS, ebből360 D2r; a suite részszámai átfednek, nem összeadandók.
Első futás1723/316 sikeres volt, a két review-javítás után a végső1767/360 újra
lefutott. A fizikai Stripe-hálózat helyett a rögzített SDK offline HTTP-válaszokat
kapott, az elveszett ACK fault injection. Lefedve: séma/DDL-argumentum/old rows,
ismétlés, hiányzó session recovery, nem fizetett állapot, providerhiba, cursor-
konfliktus, két drain versenye, későn érkező esemény, öt poison utáni hatodik valid,
korrupt completion-időbélyeg, GET-/időkeret és transport-visszaállítás.
A meglévő437 finance/CLI és13 friss processzes runtime ellenőrzés sikeres; PHP
lint/diff sikeres. Független forrásreview két lelete javítva és visszaolvasva;
a reviewer tesztet nem futtatott. Nincs új teljes tervaudit vagy sandbox/live PASS.

Hermes052 execution9b0d2bfe-c296-4e48-85f6-81e014aa57f8, input SHA
b336c9dcbc0c15970211ff0cc38d6cf782f26ed5c051e3cb1d72f872f8486f79:
1completed hívás,35279input+10809output=46088token,768cached; becsült
USD0.0051217166. Tényleges számla és Codex/reviewer összköltség ismeretlen.
Részleges átvétel, jelentős javítás: nem alkalmazható patch/undefined placeholder,
séma-kompatibilitás, hiányzó-session recovery, kurzor/liveness, lazy SDK-betöltés,
privát transport/budget és hibás fixture-ek. Nem volt újragenerálás/JEV/fallback.
A delegálás pending/terminal remote_ack; végső termékcheckpoint külön readbackkel.

Kiadási sorrend: saját kompatibilis webhook → CLI; kikapcsolt worker mellett
inbox+options és érintett finance táblák konzisztens mentése, friss remote hash,
explicit séma/parancs, engine/index/readback, staging batch, azonos productionbyte.
Csak ezután külön scheduler bejegyzés, futási idő és hibaszámlálók ellenőrzésével.
Rollbackkor új workerindítás leáll, a már eltárolt esemény/megfigyelés/movement/target
megmarad; előző webhook a nullable bővítéssel kompatibilis, régi schema-apply-t
nem futtatjuk az új táblára. Nincs DB-visszaállítás új események fölé.
Kézi UI-checklist kiadás után: NGO-adományoldal és belépés; saját rendelés olvasása;
HU/EN/DE/FR és fizetési deviza; frissítés/preferenciaváltás nem indít fizetést;
nincs téves kifizetett állítás vagy publikus alapnövekmény. Most nem futott élő UI.
Teljes refund/dispute/payout/account eseménykör, catch-up, owner pénzügyi státusz,
fedezett végrehajtás és a §62 cél többi része nyitott. SUBSTACK-HANDOVER.md csak
megőrzött hivatkozás. Nincs mainablak, merge, telepítés, aktiválás vagy élő elfogadás.

D2r lezáró helyi ellenőrzés: teljes meglévő protected inventory nulla drift és6
izolált hash/checksum eset PASS; kizárólag webhook.php/finance-read.php saját pinje
változott. Guardlogika/inventory/idegen pin érintetlen. A saját loopback/tmpfs MariaDB-
konténer eltávolítva és hiánya visszaolvasva; nincs image pull vagy közös függőségírás.


## 76. D2s — saját rendelés tárolt pénzügyi nézete (előkészítés, 2026-11-03)

Saját N324c6e65/A015b5e00d. D2r nyilvános push külön jóváhagyásra vár; ez a
helyi implementációt nem állítja meg. Hermes053 csak már közzétett d45-ből
byteazonos, szűrt forrást kap. Első kidolgozás, nem előre megoldott patch.
Scope: existing finance-ledger.php owner-only bounded snapshot reader, két
existing HTTP GET top-level financial mező és célzott HTTP/native DB teszt.
Checkout POST és belső order-status változatlan; nincs rekurzió, provider/DDL/
DML/zárolás/új személyes előzmény, explicit unavailable részleges telepítéskor.
Kockázat: idegen rendelés pénzadata, hamis aktuális/fizetett/NGO-bank állítás,
sérült vagy más policy alatti snapshot, float egységhiba. Azonos tulajdonosi
404/no-store, validált signed minor értékek és történeti dátum; nincs account/
providerazonosító vagy nyers napló a válaszban. Provisional nem kifizetett.
Funkciópróba: mindkét saját státusz GET, auth-first/idegen/hibás adatok/hiányos
modul, tárolt régi target/refund, nulla írás és providerhívás; meglévő POST és
readonly működés kikapcsolt új indításnál. Kézi UI kiadáskor: login/saját státusz,
négy nyelv/összeg, dátum és előzetes felirat, nulla téves siker vagy fizetésindítás.
Existing exact-file rollout: kompatibilis olvasó előbb, HTTP után, web parser
később. Saját fájl/pin rollback, pénzügyi adatok megőrzése, séma nem változik.
Nincs mainablak, provider írás, merge/deploy/aktiválás.


### D2s helyi integráció — 2026-11-03

Saját két pénzügyi PHP-fájl és három teszt változik, új séma/modul nélkül.
Két meglévő authenticated GET owner-only snapshot; eredeti order/checkout
megmarad. Hiányos modul/séma/adat explicit unavailable, tárolt target dátummal,
eredeti signed minor/deviza; nincs provider/írás/zár/új hosszú előzmény.
1839 natív MariaDB (72 új),309 HTTP,437 finance/CLI,13 runtime sikeres;
web65+scoped TypeScript sikeres. Review: rejtett credit és null→0 lelet javítva/tesztelve, végső független visszaolvasás leletmentes; reviewer nem futtatott tesztet.
Hermes053 részben átvett, helyben javított;41968token,becsültUSD0.004442802,
tényleges/Codex-összköltség ismeretlen. Nincs retry/fallback/JEV.
Részletes szerződés/érintettség/backup/rollback/UI-checklist:
protected-change-records/2026-11-02-sharity-donation-prices.md§76.
D2r324c6e65 már remote-visszaolvasott; D2s még helyi, nem commit/push/release.
Nincs nyitott csendablak, merge, telepítés, aktiválás vagy élő elfogadás.

A technikai díj visszaírása külön signed minor mező; a történeti v1-ben csak
a credit/donor_gross mezőpár teljes hiánya jelenti a0 visszaírást. Jelen lévő
null vagy hibás érték unavailable; nincs új pénzügyi számítás.


## 77. D2t — kapcsolt fiók későbbi fizetési értesítései (előkészítés, 2026-11-03)

Saját N4a595238; Hermes054 első javaslat, kizárólag a nyilvános324c6e65
byteazonos webhook/adapter és két szintetikus teszt. A D2s nyilvános push
külön jóváhagyásra vár; mainablak nincs. Cél: charge/refund/dispute értesítés
után a meglévő bounded drain friss szolgáltatói bizonyítékot olvas és a
meglevő előzetes kimutatást frissíti. Értesítésből nincs közvetlen könyvelés.
Érintett funkciók: aláírás-ellenőrzött inbox, minimális eseményazonosság,
kapcsolt charge pontos GET-azonosítása, eredeti rendeléshez kötés, drain.
Kockázat: klónozott metadata másik fizetést kapcsolhatna rendeléshez; refund
vagy vita nincs még a visszaolvasott listában; API-hiba téves ACK-ja eseményt
veszíthet; régi értesítés felülírhatná az aktuális állapotot. Követelmény:
friss session/charge/PI egyezés és erőforrásazonosság a pénzügyi projekció
előtt, hiba esetén függő állapot. A refund/dispute szűk charge-GET feloldása
csak érvényes aláírás után, rövid időkerettel, tranzakción kívül lehetséges;
az eredeti Checkout fogadás providerfüggetlen marad. Nincs általános queue,
ütemezés, új auth, saját pénzügyi algoritmus vagy élő provider POST.
Teszt: meglévő izolált MariaDB/offline SDK, signed→drain lánc, ismétlés és
elveszett ACK, idegen account/mód/forrás/metadata, hiányzó késői erőforrás,
régi Checkout és nulla POST. Publikus readiness továbbra false.
Kiadás: kompatibilis adapter előbb, webhook utána; meglévő exact-file út,
privát saját fájlmentés/friss előállapot-hash és readback. Csak ellenőrzött
fogadó után bővülhet a szolgáltatói eseménylista. Rollback saját fájlra,
a már tárolt értesítések/finance adatok megtartásával; korábbi kód nem kaphat
új értesítést, amelyet csak ACK-val eldobna. Nincs DB-visszatöltés új eseményre.
Kézi kiadási ellenőrzés: saját rendelés refund/vita után tárolt dátummal és
helyes devizával olvasható; nincs téves banki beérkezés vagy kifizetési állítás.
Platform-díj/account/payout események, elveszett értesítés catch-up, valódi
fedezett végrehajtás, merge/deploy/aktiválás és pénzügyi elfogadás nyitott.


### D2t helyi lezárás — 2026-11-03

Meglévő webhook.php + stripe-checkout.php:15 explicit connected charge/refund/
dispute típus, ugyanabban az inboxban, új séma/queue/scheduler nélkül. Refund/
dispute aláírás/context után pontos charge-GET, legfeljebb1 kérés,5s időkeret,
tranzakción kívül. Checkout-fogadás eredeti, providerfüggetlen szerződésű.
Minimális signed állapot és balance-ID-k megmaradnak. Drain a friss, eredeti
session charge/PI és konkrét refund/dispute azonosságát, az állapot előrehaladását
és valamennyi jelzett balance-ID jelenlétét ellenőrzi a projekció/marker előtt.
Hiányos, elavult vagy össze nem vethető újranyitott állapot pending; régi
értesítés nem állítja vissza a refund előtti összeget. Nincs provider POST,
allokáció, payout, szavazat, közösalap-írás vagy readiness-aktiválás.
Végső2116 natív MariaDB-ellenőrzés (277 új lifecycle),197 Checkout,437 finance/
CLI,521 adjustment és13 friss runtime sikeres. Offline SDK+izolált DB; nem
valódi Stripe/sandbox/production bizonyíték. Egy köztes új tesztben hibás
settlements mezőnév volt; a szerződés szerinti settlement-re javítva. Review
elavult resource-state/balance false-completion leletét javítottuk, célzott
regresszió és független visszaolvasás igazolja; reviewer nem futtatott tesztet.
Hermes054:1 hívás,35011 input+7662 output=42673 token, becsültUSD0.004580485;
tényleges díj és teljes Codex/review-költség ismeretlen. Részleges koncepcióátvétel,
jelentős helyi javítás: draft kihagyta a refundfeloldást, hibásan véglegesített
pending eseményt, szükségtelen ismételt providerlistákat és üres/no-op patch-et
adott, tesztet nem adott. Nincs újragenerálás, JEV, retry vagy fallback.
A delegálási pending/terminal remote_ack. Termékcheckpoint külön readbackkel.
Kiadási/rollback/kézi UI-szerződés: protected record§77. Adapter→webhook exact-file,
majd szolgáltatói event selection; rollbackkor új eventdelivery leáll vagy
kompatibilis fogadó marad, tárolt eseményeket nem törlünk. Sem régi Checkout
nem veszhet el, sem korábbi fogadó nem dobhatja ACK-val az új értesítéseket.
Nyitott: platform application_fee/account/payout, catch-up/ütemezés, valódi
felelősség/fedezet/korrekció, direct HUF pilot→Amplifier→további devizák,
nyelvi/affiliate/Offerwall cél, VB7/Sharing12. D2r324c6e65 már közzétéve;
D2s06f0d4db+4a595238 nyilvános push automatikus visszautasítás után külön
jóváhagyásra vár, D2t is helyi. Mainablak nincs; merge/deploy/aktiválás nincs.


## 78. D2u — kimaradt értesítés utáni visszaolvasás (előkészítés, 2026-11-03)

Kiindulás N35c8150d/Ade7a72794, tiszta saját ágak. A D2s/D2t négy
commitját Arnold külön jóváhagyta, a nyilvános #244 ágon visszaolvasottak;
a §77 korábbi függő publikálási állapotát ez felváltja. Mainablak nincs.
Hermes055 első draft: meglévő webhook/finance CLI és szintetikus DB-teszt.
Cél: session_recorded rendelés valódi bizonyítékának korlátos visszaolvasása
kimaradt webhook esetén, a meglevő megfigyelés/movement/előzetes target úton.
Érintett: új explicit finance-poll CLI és nem ütemezett WP-Cron callback;
meglévő drain pipeline szűk újrahasználata. Nem új queue vagy pénzügyi algoritmus.
Kockázat: megkettőzött pénztétel, éhező sor, más account/session keveredése,
elveszett cursor-ACK, providerhívás DB-zár alatt, régi értesítés téves lezárása.
Korlát: legfeljebb5 rendelés,63 GET/rendelés, közös60s providerkeret; kizárólag
GET, nincs inboxgyártás vagy -lezárás, nincs automatikus ütemezés/aktiválás.
Prepared/attempted mentett session nélkül nem visszaállított fizetés.
Teszt: natív izolált MariaDB/offline SDK, kimaradt eredeti/refund értesítés,
ismétlés, poison és körbeforduló cursor, CAS/ACK, időkeret, kapcsolók utáni
történeti olvasás, régi drain, nulla provider POST. Readiness false marad.
Kiadás: webhook függvény előbb, CLI utána; kikapcsolt scheduler; meglévő
exact-file út friss hash/privát mentés/readback mellett. Aktiválás később
külön runtime-próbával. Rollbackkor csak saját callback ütemezése áll le
és saját fájl áll vissza; megfigyelés/pénzügyi sor megmarad.
Kézi kiadási lista: saját rendelés pénzügyi nézete helyes eredeti devizát
és megfigyelési időt mutasson; visszatérítés után ne maradjon régi nettó,
ne állítson NGO-bankbeérkezést vagy kifizetést. Élő vizsgálat még nem futott.


### D2u helyi ellenőrzés — 2026-11-03

A már eltárolt sessionök kimaradt értesítés nélkül is visszaolvashatók a
`sharity donations finance-poll` paranccsal. Külön, nem ütemezett
`sharity_donation_finance_poll` hook; legfeljebb 5 rendelés és 63 GET/rendelés,
közös 60 másodperces providerkeret. A függő inboxsor a signed-progress drain
hatáskörében marad; GET után friss ellenőrzés történik. Később érkező notice
függő maradhat egy korábbi előzetes target mellett, nem vész el vagy ACKolódik.
Az eredeti pipeline két szűk lépése és a két opcióra korlátozott CAS-kurzor
közös; új pénzügyi algoritmus, séma, queue, ütemezés vagy provider POST nincs.
Prepared/attempted mentett session nélkül továbbra külön egyeztetést igényel.

Tényleges ellenőrzés: 2371 natív MariaDB check, ebből 255 új polling;
442 finance/CLI és 13 friss runtime sikeres. Kimaradt eredeti/refund notice,
ismétlés, két valódi kapcsolat, mérgezett sor, wrap, lost ACK, CAS, kapcsolók
utáni történeti olvasás, időkeret és nulla POST ellenőrizve. Offline SDK és
izolált DB, nem sandbox/élő elfogadás. Két új teszt-fixture hibát javítottunk:
elvárt hibakód, majd a race-harness kapcsolatcseréje után régi closure-hivatkozás.
A független célzott forrásreview nem talált új hibát; reviewer nem futtatott tesztet.
Hermes055: 1 hívás, 30545 input + 3251 output = 33796 token, becsült
USD 0.003371277; tényleges számla és teljes Codex/review-ráfordítás ismeretlen.
Részleges átvétel: scan/CLI/hook; duplikált cursor/pipeline helyett újrahasználat,
pending notice ellenőrzés és hiányzó tesztek helyben. Nincs újrahívás/JEV/fallback.
Delegálás pending/terminal remote_ack. Kiadás/rollback/kézi lista: protected
record §78. Forrás még helyi; mainablak, merge, telepítés, aktiválás nincs.
Nyitott: platform fee/account/payout életciklus, ütemezés, valódi fedezett
végrehajtás, NGO-account és HUF pilot, Amplifier, további devizák, teljes
nyelvi/affiliate/Offerwall cél, VB7/Sharing12 eltérések és éles elfogadás.


## 2026-11-03 — D2u saját polling baseline

Termékcommit abfc63dedfe3243fd365a3b336346d44436be1d9. Csak saját webhook.php
és finance-read.php pin; inventory, guardlogika, idegen hash változatlan.
2371 natív DB (255 új polling), 442 CLI, 13 runtime; teljes valódi guard
inventory nulla drift és 6 checksum eset ellenőrizve. Független source-review
leletmentes, reviewer nem futtatott tesztet. Részletek és kézi kiadási lista:
protected record §78. Új poll schedule nincs; állapot forráskész, helyi, nem
merge-elt/telepített/aktivált vagy pénzügyileg elfogadott. Saját tesztkonténer
eltávolítva és hiánya visszaolvasva.


## 80. D2v — accountváltozás/leválasztás előkészítése (2026-11-03)

A jóváhagyott62.4 következő része; első kidolgozó Hermes057. Publisher valós
V2 export nélkül nem kap új telepítőt. Érintett a meglévő Connect receiver,
minimális inbox, account option/CAS és quote/dispatch jogosultság, szükség
esetén explicit additív séma. Account.updated/deauthorized, fiók/livemode,
duplikáció/late event és friss bind verseny; régi rendelés/status/refund marad.
Kockázat: elavult újfizetési engedély, idegen NGO tiltása, ACK után elveszett
tiltás, párhuzamos bind visszaállása. Nincs pénzügyi POST/allokáció/szavazat.
Teszt: signed negatív/replay/context, tényleges kétkapcsolatos DB-verseny,
uncertain write readback, explicit séma, historikus order regresszió.
Kiadás meglévő exact-file úton, saját backup/CAS, explicit séma, majd konkrét
event selection; végső kompatibilis sorrend megvalósítás után pontosítandó.
Rollback új indítást leállíthat, fogadott tiltást/eseményt nem töröl; régi
receiver nem ACKolhat feldolgozatlan új accounteseményt. UI: érintett NGO-nál
új fizetés tiltott, saját korábbi rendelés olvasható, más NGO és nyelv/deviza/
belépés működik. Erre a csomagra még nincs teszt/telepítés/aktiválás.

## 80. D2v — account-esemény és újfizetési engedély (2026-11-03)

A jóváhagyott §62.4 része elkészült a saját Notes-worktree-ben. A meglévő
`sharity-ngo-donations/webhook.php` kezeli az `account.updated` és
`account.application.deauthorized` aláírt Connect-eseményt. A top-level
account/livemode/API-verzió és a megfelelő account/application objektum
ellenőrzött; az application `ca_` azonosítója nem az accountból származik.
Nyers account-, ügyfél- és bankadat nincs az inboxban. Hivatalos szerződés:
https://docs.stripe.com/connect/webhooks és https://docs.stripe.com/api/events/types .

Az inbox harmadik, explicit sémaalakja egy nullable `account_key` mezőt ad,
az order/session account-eseménynél NULL; a történeti sorok megmaradnak.
`sharity donations webhook-account-schema --apply` nélkül nincs DDL.
Az account-sorokat a meglévő order-only drain/poll eleve kizárja; nem lesz
belőlük pénzügyi megfigyelés, movement, allokáció vagy fizetésbizonyíték.

A minimális receipt és a matching NGO/livemode binding lejáratása egyetlen
rögzített mysqli-kapcsolaton, tranzakcióban történik. Metadata-lock alatt
mindkét tábla tényleges InnoDB engine-je, az inbox pontos sémája és az
option_name egyedisége ellenőrzött. Commit/rollback után ac=1, tx=0 és teljes
visszaolvasás kell az ACK-hoz; a saját, még nem commitolt sor nem bizonyíték.
A teljes accounts-option CAS megőrzi a többi szervezetet. Még nem kötött
account is tartós eseményhatárt hagy, ezért a közben indult account-bind
nem állíthat vissza régi engedélyt. A bind szolgáltatói GET-je zár nélkül
fut, előtte rögzített epoch és kezdési idő mellett. Késői, régebbi esemény
nem érvényteleníti az utána kezdett friss ellenőrzést; azonos másodpercnél
új ellenőrzés kell. Ismételt esemény nem tiltja újra a későbbi friss bindet.
Az új quote és prepared-dispatch a meglévő lejárat/version ellenőrzéssel
leáll; a tulajdonos korábbi rendelése tovább olvasható.

Tényleges teszt: 2458 natív MariaDB check, ebből 87 account-event;
90 account-binding/CAS, 442 finance/CLI, 13 friss runtime forgatókönyv.
Aláírt események, idegen account/livemode, késői esemény, duplicate/conflict,
unbound barrier, valódi kétkapcsolatos bind/event verseny, INSERT/commit
ACK-hiba, rollback false/throw/no-op, mindkét MyISAM engine és hiányzó
unique key, stale schemaflag, prepared-dispatch tiltása és nulla új provider
POST ellenőrizve. A pinned SDK HTTP-je offline, MariaDB10.11.19 izolált;
nem Stripe sandbox vagy élő elfogadás. PHP lint és diff ellenőrizve.
Egy fixture-hiba javult: a negatív séma próba char(63) helyett adatmegőrző
char(65)-öt használ. Nem történt production adat-/providerhívás.

Független forrásreview két lelete javítva: nyitott tranzakcióból téves ACK,
és nemtranzakciós options miatti részleges commit. Célzott visszaolvasás
után nincs további konkrét lelet; reviewer nem futtatott tesztet. A két
korábbi pénzügyi tervaudit újrahasználva, nincs új pénzügyi policy.
Hermes057: egy hívás, 28305 input + 6790 output = 35095 token, cache768;
becsült USD0.0037556246, tényleges számla/Codex/review-ráfordítás ismeretlen.
Execution046550ec-b813-4a44-b43e-56aa25030ee0, pending/terminal remote_ack.
Csak a meglévő receiver/minimális receipt/CAS iránya átvett. Jelentős helyi
javítás: application/account azonosítás, object livemode, fake orderID,
duplikáció előtti tartós tiltás, egytranzakciós receipt, unbound bind-race,
valós CLI/séma és tesztek. Nincs újragenerálás, JEV vagy fallback.

Kiadási sorrend a meglévő exact-file úton: ellenőrzött merge-SHA → saját
fájlok, accounts-opció és inbox konzisztens privát mentése → új indítások
szüneteltetése → kompatibilis webhook.php, majd finance-read.php → explicit
webhook-account-schema --apply → séma/engine/index és történeti sorok
visszaolvasása → sandboxban signed account-event és friss bind próba →
Connect endpoint két új eseményének bekötése → NGO/mód/deviza szerinti
engedély friss igazolása. A provider event-kiválasztás önmagában nem pénzügyi
aktiválás. Féltelepítésben az account-ág 503-at ad; nincs HTTP-migráció.
A régi order/refund feldolgozás folytatható. Rollback új indítást állít le;
a fogadott account-eseményt/tiltást és kompatibilis receivert meg kell őrizni.
Régi, account-eseményt ignored=true-val ACKoló webhookra visszaállni nem
szabad. Új pénzügyi adatok fölé régi DB-mentés nem tölthető.

Kézi UI-checklist kiadás után: az érintett NGO adományozási oldalán új
quote/checkout nem indul lejárt engedéllyel; a saját rendelés állapota
olvasható; frissen ellenőrzött új kötésnél a megfelelő mód/deviza elérhető;
a másik NGO, belépés és nyelvválasztás változatlan. Ez még nem futott élőben.
Forrás és helyi teszt kész; mainablak/merge/telepítés/aktiválás/élő pénzügyi
elfogadás nincs. D2u nyilvános Notes-publikálása továbbra külön függő kérés;
az új D2v sem kapott külön nyilvános publikálási jóváhagyást. A teljes cél,
valódi fedezet/végrehajtás, platformfee/payout-életciklus, Foundation account,
HUF pilot, Amplifier, további devizák, history/V2-publisher, affiliate/Offerwall
és nyelvi elfogadás, VB7/Sharing12 nyitott. SUBSTACK-HANDOVER.md megőrzött
hivatkozás, nem aktív feladat.


## 81. D2w — platform alkalmazásdíj-esemény, előkészítés (2026-11-03)

A jóváhagyott §62.4 következő önálló része, első kidolgozó Hermes058.
A meglévő webhook/SDK adapter és drain bővül a platform alkalmazásdíj három
eseményével, külön explicit endpoint/signing scope mellett. A payout batchhez
külön balance-item authority szükséges, ebből nem találgatunk rendelést.
Konkrét kockázat: platform/NGO-scope keverése, idegen vagy elavult refund
lezárása, webhookból pénzgyártás, duplikáció/ACK után elveszett értesítés.
Meglévő inbox, bounded GET/readback, eredeti rendelés és pénzügyi megfigyelés
újrahasználata; nincs pénzmozgatás, új queue, automatikus aktiválás vagy APIkulcs.
Érintett funkció: signed receiver, fee/charge SDK lookup, evidence matching,
meglévő drain; quote/checkout és régi connected/account/poll ág megmarad.
Kiadás existing exact-file/backup/CAS: függőség és inaktív endpoint előbb,
sandbox/próba után külön platform signing/event configuration. Rollback
új event-bekötést/indítást állít le, korábbi receipt és feldolgozás megmarad.
UI kiadás után: saját pénzügyi nézet fee-refund után frissül, eredeti deviza és
időpont helyes, nincs kész kifizetés/banki beérkezés ígéret. Még nincs tesztPASS.

### D2w végrehajtás és ellenőrzés

A két meglévő termékfájl változott: `sharity-ngo-donations/webhook.php` és
`stripe-checkout.php`. A `/wp-json/sharity/v1/donations/platform-webhook`
külön platform receiver, nem a connected endpoint átnevezése. Konfiguráció:
`SHARITY_DONATIONS_STRIPE_PLATFORM_WEBHOOK_SECRET`,
`SHARITY_DONATIONS_STRIPE_PLATFORM_WEBHOOK_LIVEMODE` (boolean),
`SHARITY_DONATIONS_STRIPE_PLATFORM_ACCOUNT_ID`. Secretérték nem kerül repóba.
A connected secret azonossága, top-level account, hibás livemode/API/verzió/
aláírás tiltott. `application_fee.created` és `application_fee.refunded`
application_fee objektum; `application_fee.refund.updated` fee_refund objektum.
Hivatalos forrás: https://docs.stripe.com/connect/webhooks ,
https://docs.stripe.com/api/events/types és
https://docs.stripe.com/api/fee_refunds/object .

Legfeljebb három GET, közös 5s keret, nulla retry és adatbáziszároláson kívül:
platform account → platform application fee → pontos connected charge.
Credential owner/config, key-mode, fee/account/charge, eredeti amplifier
rendelés, összeg/fizetési deviza, frozen metadata és payload-alkalmazásdíj
ellenőrzött. A fee saját devizája megmarad, nem címkézzük át a charge
pénznemére. Csak minimális receipt tárolódik az eredeti inboxban; duplikált
azonosság, eltérő eventtartalom és elveszett INSERT ACK teljes readbackkel
kezelve. Nyers szolgáltatói payload, ügyfél/bankadat nem kerül az inboxba.

Az existing drain a tényleges pénzügyi bizonyítékkal hasonlítja össze az
értesítést. Az aggregate refund-floor nem lehet újabb, mint a visszaolvasott
összeg; a konkrét refundhoz azonos fee/fr/amount/currency és kibontott debit
kell. Csak txn-azonosító esetén függő marad. Az új checkout/binding tiltása
után a régi fizetés értesítése tovább feldolgozható. Pénzügyi készültség
hamis marad: receipt/megfigyelés/provizórikus cél nem tényleges allokáció,
fedezetfelszabadítás, banki kifizetés vagy elfogadás. Nincs új séma, queue,
scheduler, provider POST, szavazat vagy aktuális fizetési aktiválás.

Tényleges ellenőrzés: 2775 natív MariaDB check, ebből317 platform-fee;
17 friss runtime eset,197 SDK checkout,521 adjustments (a CLI442 és D2q26
ellenőrzése is lefutott), PHP lint és diff. Az eredeti actual DB harnessbe
bekötött új `tests/sharity-donation-platform-fee-mysql.test.php` külön
aláírás/scope/account, duplikáció/conflict/lostACK, stale/refund, tiltás utáni
feldolgozás, rossz eredeti kapcsolat, hiányos bizonyíték és nulla POST esetet
fed. Offline pinned SDK és saját izolált MariaDB10.11.19: ez nem Stripe
sandbox vagy élő elfogadás. Független célzott forrásreview leletmentes;
a reviewer nem futtatott tesztet. A két korábbi pénzügyi tervaudit megmarad.

Diagnózis: a szintetikus refund-lista nem adta vissza a kért expanded balance
objektumot. A feldolgozó helyesen pendinget jelzett. A teszt előbb ezt,
majd a kibontott debit után az observedet ellenőrzi; nincs termékvédelem-
lazítás. A teszt nem létező finance_latest hívása a meglévő pontos
finance_read(order,digest) visszaolvasásra cserélve. A diagnosztikai kimenet
eltávolítva. Nem történt vak szolgáltatói retry vagy új Hermes-generálás.

Hermes058:1hívás,44325input+7098output=51423token,cache768;
becsült USD0.0052695006, tényleges számla és Codex/review teljes ráfordítás
ismeretlen. Execution581fdece-bbdd-4ee8-bcef-ac831c957e34; pending/terminal
remote_ack. TaskSHA3dabe0e0679a1d3add2c0ba992b8afedfeef8f9d1420f65aca100d0a7396c41c.
Csak a külön receiver/minimális receipt/lookup iránya átvett. Jelentős helyi
újraírás kellett a téves refunded objektum, nem létező SDK-hívás/placeholder,
hiányzó platform/eredetiorder/FX/signed-progress ellenőrzés és nem bekötött
hibás tesztek miatt. A megőrzött javaslat nem termék-PASS; megtakarítás nincs
igazolva. Nem volt JEV, fallback vagy új kiadási rendszer.

Kiadás a meglévő merge/exact-file úton: saját két fájl és privát konfiguráció
mentése/friss hash → adapter → kompatibilis receiver → fájl/runtime readback
→ sandboxban külön platform endpoint/secret és három eventtípus → aláírás,
receipt/drain és saját állapot visszaolvasása → külön engedélyezett élő
platform konfiguráció. A rossz endpointon érkező platform event400, hiányos
új konfiguráció503; nincs csendes pénzügyi ACK. Nincs új DDL; a korábbi
inbox/finance sémák szükségesek. Stripe-secretet kizárólag privát configba.
Providerbekötés után rollback nem törölhet receiptet, nem válthat ignored
ACK-ra: új indítások/bekötés szünetel, a meglévő kompatibilis feldolgozás és
retry/readback út megmarad. A saját SDK-adapter önálló visszaállítása csak
az új receiver használatának megszűnése után lehetséges. DB-visszatekerés nincs.

Kézi UI kiadás után: saját rendelés pénzügyi nézetén eredeti összeg/deviza és
frissítés helyes; fee-refund nem kész NGO-kifizetés vagy banki beérkezés;
checkout tiltásakor a korábbi rendelés olvasható; nyelvválasztó és másik NGO
változatlan. Éles UI/sandbox/provider próba ebben a körben nem történt.
Mainablak, merge, telepítés és aktiválás nincs. Nyilvános D2u/D2v-jóváhagyás
még függ; D2w nincs benne az abban megnevezett négy commitban. A teljes cél
nyitott: payout/balance-item authority, ütemezés, valódi fedezett végrehajtás,
NGO-account/directHUF, Amplifier, további devizák, history/V2-publisher,
affiliate/Offerwall és teljes nyelvi elfogadás, VB7/Sharing12. Következő
önálló pénzügyi hiány a payout authority; nincs találgatott order-hozzárendelés.
SUBSTACK-HANDOVER.md megőrzendő hivatkozás, nem aktív feladat.


## 82. D2x — payout/balance-item olvasó, előkészítés (2026-11-03)

A §62.4/62.7 kifizetési bizonyítékához szükséges SDK-olvasó és trusted CLI,
első draft Hermes059. Meglévő stripe-checkout.php/finance-read.php és célzott
payout teszt; bounded GET, pontos platform/connected account, teljes batch,
natív deviza és díj, változás miatti újraolvasási hiba. Kockázat: részlista
vagy paid flag téves bankbeérkezésként, más account/payout mozgásának keverése,
refund/díj kihagyása. Manual/instant payout összetétele nem találgatható.
Nincs séma/pénzállapot/scheduler/provider POST. A későbbi tartós payout-
kapcsolat és értesítésfeldolgozás ettől még nem kész. Koherencia: régi order/
finance API és UI változatlan; új kizárólag admin CLI, történeti olvasás.
Kiadás existing merge/exact-file: két saját fájl mentés/friss hash, adapter
előbb/CLI utána, readback és engedélyezett sandbox-olvasás; rollback a két
saját fájl, pénzügyi adat változatlan. Kézi UI: nincs új felület; meglévő
saját rendelés és nyelvválasztó változatlan, banki beérkezés nem ígérhető.
Még nincs megvalósítási vagy teszt-PASS.


### D2x végrehajtás és review

Új `payout-inspect --payout=po_… --account=acct_… --mode=test|live
--scope=connected|platform` csak trusted WP-CLI-n. A meglévő SDK-adapter
pontos credential/account/mode ellenőrzés után a payoutot, debit/failure
balance-ot és a teljes automatikus batch-et olvassa. Közös 60s/15GET keret,
legfeljebb10×100 tétel, nincs retry vagy szolgáltatói hívás DB-tranzakcióban.
Hiányos/lapozáskorlátos/ismétlődő lista és közben változó payout hibát ad;
manual/instant összetétel explicit unavailable. Natív pénzegység, előjeles
korrekció, tényleges díj, devizánkénti összeg és eredeti source/txn megmarad;
nyers payload, bank-/ügyféladat és description nem jut a válaszba.
A saját payout-debit a listában megmarad, de nem finanszírozza önmagát.
Az összegegyezés a debit nettójához mér, a payoutdíjat egyszer veszi figyelembe.
FX-adat bizonyíték, nem átváltási algoritmus. A provider paid jelzés és
batch_totals_reconciled külön mező; order_attribution_verified,
ngo_bank_received, allocation_eligible mindig false. Nincs DB-írás, új séma,
scheduler, pénzmozgatás, publikus API vagy felületi módosítás.

Tényleges parancsok: `php tests/sharity-donation-payouts.test.php`741 payout
ellenőrzés (+442 meglévő finance/CLI), `php tests/sharity-donation-stripe-checkout.test.php`197,
`php tests/sharity-donation-adjustments.test.php`521 (+26 counterleg),
`php tests/sharity-donation-runtime.test.php`17 friss process; PHP lint és
diff-check sikeres. Pinned valódi SDK, offline HTTP, WPDB mock; nincs Stripe
sandbox/élő elfogadás. Ehhez az írásmentes bővítéshez új DB-harness nem készült.
A korábbi D2w MariaDB-eredmény nem új D2x-adatbázisteszt.
Független forrásreview egy FX előjelhibát talált: eltérő devizájú debit vagy
failure return fordított irányát a régi conversion helper nem ellenőrizte.
Az új payout-ág explicit előjelvizsgálata és pozitív/negatív reversal-payout
regresszió javítja; az ellenkező fundingösszeg sem adhat hamis egyezést.
A reviewer célzottan visszaolvasta és lezárta a leletet; tesztet nem futtatott.

Hermes059:1hívás,16610input+4672output=21282token,cache4608;
becsült USD0.0020263516, tényleges számla/Codex/review összköltség ismeretlen.
Execution5276a146-10dc-464b-8818-8d345421b8b2; pending/terminal remote_ack.
TaskSHA334ecdab4c73ba36118c303daa3e82a9a7b479d0c7f090d460b166adc9f05700.
Részleges projection/CLI-irányátvétel; a hiányzó pagination/balance/összegzés/
végső readback/teszt, téves kötelező account.livemode és nem igazolt
credential-mode helyben javult. A placeholder nem kész implementáció;
nincs újragenerálás/JEV/fallback vagy igazolt megtakarítás. A Stripe CLI
nincs telepítve; új globális csomag helyett a hivatalos dokumentációt olvastuk.
Források: https://docs.stripe.com/api/payouts/object ,
https://docs.stripe.com/api/balance_transactions/list ,
https://docs.stripe.com/payouts/reconciliation .

Kiadás/rollback: a fenti meglévő exact-file út és két saját fájl mentése;
adapter előbb, CLI utána, hash/runtime readback, majd engedélyezett sandbox
payout-olvasás. Nincs DDL és nem szükséges új funkciókapcsoló. Hiányzó új
adaptermetódus vagy bounded wrapper hibát ad, régi payment-inspect megmarad.
Csak a két saját fájl állítandó vissza; pénzügyi adat nem változik.
Kézi UI kiadás után: meglévő adományoldal és saját rendelés belépés/nyelv/
összeg/státusz változatlan, másik NGO adata nem látható; a payout paid jelzés
nem új bankbeérkezési ígéret. Élő UI/sandbox teszt most nem történt.
D2u/v/w nyilvános feltöltésének hatcommit-os jóváhagyása függ; D2x nem része
annak. Nincs csendablak/main/merge/deploy/aktiválás vagy pénzügyi elfogadás.
Következő: tartós payout-bizonylat és igazolt order/balance kapcsolat, majd
a teljes §62 cél; ez az olvasó nem zárja le azokat. SUBSTACK-HANDOVER.md
megőrzendő hivatkozás, nem aktív feladat.


## 83. D2y — tartós payout-bizonylat és pontos mozgáskapcsolat (2026-11-03)

A meglévő finance-ledger.php és finance-read.php bővül; a dedikált
sharity_donation_payouts_v1 tábla az order nélküli/többorderes szolgáltatói
batch változatlan bizonyítékát tartja. Egyedi account/live/payout/digest,
natív decimális sztring pénz és eredeti source/type; első UTC megfigyelés.
Kizárólag explicit `wp sharity donations payout-schema --apply` végez DDL-t;
az eltérő InnoDB/mező/index séma hibás, nincs automatikus javítás/adattörlés.

Trusted CLI: `payout-record --payout=po_... --account=acct_... --mode=test
--scope=connected` (élő/platform csak a megfelelő külön hiteles accounttal),
majd `payout-reconcile --payout=po_... --account=acct_... --mode=test
--digest=<receipt-digest>`. A record a D2x 60s/15GET olvasóját tranzakción
kívül használja; legfeljebb4MiB allowlist, valós SQL-packet korlát, teljes
INSERT-readback. Ismétlés és elveszett ACK ugyanazt a sort/időpontot adja.
Ügyfél-/bankadat és nyers providerpayload nem kerül bele.

A riport egy repeatable-read, read-only natív DB-pillanatképen ellenőrzi a
pontos stripe/live/account/balance-transaction egyezést, a kanonikus registry
tényeket és az eredeti order/envelope/finance observation provenienciát.
Azonos ID eltérő pénzzel/devizával/source-szal konfliktus, nem csendes unmatched.
Ismeretlen vagy más account tétele látható unmatched; saját payout/failure
bizonylat és más payoutmozgás külön megmarad. A rendeléshez rendelt batch-
hozzájárulás nem önkényes payoutdíj-allokáció: payout_allocation_minor=null,
payout_allocation_verified/ngo_bank_received/allocation_eligible=false.
A provider paid és batchösszeg-egyezés külön állapot, nem bankbeérkezés.
Nincs provider POST, business/order/movement írás, pénzmozgatás, scheduler,
publikus API, új authréteg vagy felületi módosítás.

Tényleges teszt: `SHARITY_DONATION_TEST_PORT=64141 php -d memory_limit=512M
tests/sharity-donation-orders-mysql.test.php`3353 sikeres ellenőrzés, ebből
578 payout; két valódi kapcsolat ismétlési/párhuzamos próbái, elveszett ACK,
sérült readback, pontos/idegen/conflicting mozgás és eredeti bizonyítékhiány,
manual/instant, failure/reversal/FX,4deviza×2scope,4MiB és valós packetkorlát,
sémaismétlés és inkompatibilis séma. 442 finance/CLI és17 friss runtime,
PHP lint és diff-check sikeres. Pinned valódi SDK offline HTTP-val, helyi
MariaDB10.11.19; nem Stripe-sandbox/élő PASS. Saját címkézett konténer
sharity-d2y-20261103 törölve és hiánya visszaolvasva; más konténer, közös
függőség vagy szolgáltatói adat nem változott. Az első DB-futás sikeres.

Független célzott source-review két lelete az első futás előtt javítva:
natív integer receipt-ID string normalizálás; a batch saját failure sorának
teljes tényazonossága a külön failure_balance ellen. Mindkét javítás és
regresszió visszaolvasva/lezárva; reviewer tesztet nem futtatott.
Hermes060:1hívás,23044input+16352output=39396token,cache0;
becsült USD0.005073068, tényleges számla és teljes Codex/review-ráfordítás
ismeretlen. Execution5ebc85fd-1e74-46ab-ab0f-e922facff536,
taskSHAaf7c5885fd9426779adbc5a35575fa4e85c4ef9bbeb700dffee14810854d133a;
pending/terminal remote_ack. Séma/receipt/record/CLI-váz részben átvett;
téves helperargumentumok, rövid ID-korlát, negatív payout, hibás tesztút,
hiányzó provenance és nem futtatható free-function hívások helyben javítva.
Jelentős helyi újraírás, igazolt megtakarítás nincs. Nincs újragenerálás,
JEV, fallback, új fizetős szolgáltatás vagy általános release-rendszer.

Kiadás: saját merge-SHA/exact-file út; friss távoli hash, privát kétfájl- és
érintett tábla/config-mentés. D2x adapter előfeltétel, kompatibilis ledger
előbb, CLI utána; explicit payout-schema --apply, engine/index/mező readback,
majd engedélyezett sandbox payout-record/reconcile és teljes readback.
Élő import csak eredeti élő account/payout hitelesítése után. Ez továbbra
bizonylat, nem allokációs/pénzkifizetési aktiválás. Rollback saját kódra
korlátozott, az új tárolt bizonyíték megmarad; új események fölé régi DB-mentés
nem tölthető. A korábbi webhook/order feldolgozás fennmarad.
Kiadás utáni kézi lista: saját adományoldal/státusz belépés/nyelv/összeg,
NGO-izoláció és eddigi működés megmarad; nincs új bankbeérkezési ígéret.
Ez a kézi/élő lista még nem futott. Nincs mainablak, merge/deploy/aktiválás
vagy pénzügyi elfogadás. D2u/v/w publikálási kérdése függ; D2x/y nincs benne.
Következő: igazolt NGO-kifizetési hozzárendelés/banki egyeztetés, fennálló
fedezet és végrehajtás, valamint a teljes §62 cél. SUBSTACK-HANDOVER.md
megőrzendő hivatkozás, nem aktív feladat.


## 85. D2z — díjkorrekció előkészítése (folyamatban, 2026-11-03)

Jóváhagyott §62.3–62.4; N kiindulás d1ca3601. Érintett finance-ledger/read,
meglévő natív MySQL/CLI harness és saját új fee-prepare teszt. Az eredeti
account/order/fee és tényleges egyező devizájú mozgás alapján pozitív,
fennmaradó díjra korlátos visszatérítési művelet előkészítése. Közös platform
fedezet és a prepared művelet egy tranzakcióban; külső POST nélkül.
Koherencia: meglévő target/reconcile/backing és pinned mysqli; új általános
keretrendszer nincs. Meglévő receipt/import szűken az adott Amplifier eredeti
platformdíjának natív jóváírására bővül; a kapcsolt charge-út megmarad.
Kockázat: hibás nettó/díj, kettős foglalás, idegen account/pénz, stale cél,
részleges commit/ACK. Védelem: teljes natív forrásazonosság, shared frame
zársorrend, atomi operation+claim, pontos visszaolvasás; unknown díj nem nulla.
Határ: nincs checkout, webhook, UI, pont, szavazat, scheduler, kifizetés vagy
alapfelszabadítás-változás. Az operátori igazolás nem provider/banki bizonyíték;
a settlement/aktiválási jelzők hamisak maradnak. Meglévő schema csak explicit
CLI, hiányos telepítés nem veheti el a történeti olvasókat.
Kiadás majd merge-SHA/exact-file: saját ledger/read/config/érintett táblák
privát mentése, kompatibilis kód, explicit fee-schema, index/engine/readback,
stagingben szintetikus dry-run/apply. Nincs éles pénzmozgás e csomagból.
Rollback a saját kód; korábbi operation/claim/bizonylat nem törölhető és
prepared foglalás nem engedhető el bizonytalan dispatch fölött. Kézi UI
kiadás után: adományozás két módja, saját státusz, HU/EN/DE/FR, refresh/belépés,
változatlan összeg és automatikus POST hiánya; élő UI most nem ellenőrzött.
Hermes061 első draft részben hibás és hiányos, nem alkalmazva egészben;
Codex a használható operation/CLI szándékot átveszi, pénzügyet/tranzakciót
és teszteket javít. Teszt- és kiadási PASS ebben a kezdő rekordban nincs.


### D2z forrás- és ellenőrzési eredmény — 2026-11-03

A fee-schema kizárólag pontos --apply kapcsolóval migrál. A fee-prepare
privát inputból, dry-run digest visszaigazolásával egyetlen pinned mysqli
tranzakcióban rögzíti az immutable operationt és a prepared fedezetfoglalást.
A meglévő regiszter/target/frame teljes tartalma és előállapota visszaolvasott;
az eredeti platformfee receipt csak saját rendeléshez használható.
Generic backing-reserve nem készíthet önálló fee_refund foglalást.
Az idempotenciakulcs az order/gazdasági generáció/kumulált cél függvénye;
nincs provider POST, automatikus retry vagy scheduler. Az aktuális prepared
művelet nem aktiválás és nem teljes pénzügyi véglegesség. A dispatcher,
attempted/unknown egyeztetése, tartós lezárás és tartalékfelszabadítás még
következő megvalósítás; addig a foglalás nem szabadul fel.

A korrekció a meglévő nettó 80/20 célhoz és tényleges platformnettóhoz képest
számít; már megfigyelt refundot nem ismétel. Az eredeti díj még fennmaradó
összege felső határ. Csak egyező tényleges payment/fee/split deviza állhat
előkészítésbe; hiányos FX bizonyíték tilt. A díjköltség igazolt felső korlátja
külön foglalt; hiányzó korlát nem nulla. A proof operátori állítás, nem
Stripe/banki visszaigazolás; minden acceptance/aktiválási jelző hamis.

Tényleges végső teszt: SHARITY_DONATION_TEST_PORT=53160 php -d memory_limit=512M
tests/sharity-donation-orders-mysql.test.php — 3509 assertion, ebből156 új
D2z. Négy deviza, nettó/cost/refund/zero, privát scope, két valódi kapcsolat
azonos és versengő kérelme, stale frame/evidence, expiry, generic bypass,
commit előtt/után és második INSERT hibainjektálás, teljes corrupt readback,
schema ismétlés/drift szerepel. Ez nem fizikai hálózatszakítási vagy Stripe
sandbox/élő próba. A fixture-előkészítés offline SDK checkoutját külön
számolja a teszt; a díj-előkészítő alatt nulla GET/POST történik.
Finance/CLI447, fresh runtime17, PHP lint/diff-check sikeres.

Javítások: kezdeti tesztharness táblatakarítás, négydevizás fixture-limit és
régi racing order téves újrahasználata; termékben a nem létező original
balance.scope elvárás és az oszlopsorrendre érzékeny claim replay.
Független review P2: régi pending/legacy megfigyelés örök blokkolása.
Javítva kizárólag a reconcile által már validált/superseded történelemre;
aktuális scoped+complete+notpending bizonyíték és teljes natív regiszter
marad kötelező. HISTORY regresszió előbb tilt, majd későbbi pontos bizonyíték
után400 minor, a régi sorok megőrzésével. Reviewer célzott visszaolvasással
lezárta; más konkrét double-use/ACK hibát nem talált, tesztet nem futtatott.

Hermes061: execution614d966f-7898-404b-9b9c-b52c80a1dd90,
task SHA721e2c89227862dc5df6278452ec46f44e0c7bb5e5ce00c774a18633adb49f8e.
1 API;65252input+9949output=75201token; becsült USD0.00774865.
Tényleges számla és teljes Codex/review-költség ismeretlen. Rossz nettó cél,
hiányzó atomi foglalás, nem létező segédek és teszthiány miatt csak az
operation/CLI-szándék részleges átvétele; jelentős Codex újraírás. Nincs
igazolt megtakarítás, újragenerálás, JEV vagy fallback. Pending/terminal
Hermes események remote_ack; termék-checkpoint külön mentendő.

Forráskész helyi csomag, nincs új N-push/main/merge/deploy/aktiválás.
A csendablak másik, A worktree-re szólt: 10:09:30–10:09:52 UTC,
engedélyezett fetch eredménye1f34de904, merge-tree tíz tartalmi konfliktus;
integráció nélkül azonnal felszabadítva, saját A e48f5bf1c tiszta maradt.
Nincs nyitott ablak. Régi publikus engedély D2s/t-ig; új csomagok kérdése
külön függ. A teljes termékcél és a kiadási/rollback/UI lista fent változatlan.


## §87 — D2aa előkészített díjkorrekció végrehajtása (folyamatban)

Koherencia és határ: meglévő finance-ledger/finance-read/Stripe adapter; kézi CLI,
alapból tiltott végrehajtás. Új HTTP/cron nincs. A frozen operation+backing claim
egy tranzakcióban vált attempted állapotba, csak igazolt COMMIT-válasz jogosít
egy POST-ra. A korábbi GET/checkout/ledger szerződések megmaradnak.
Konkrét kockázat: dupla pénzmozgás elveszett ACK után, idegen account/fee,
elavult cél vagy fedezet, receipt mint téves pénzügyi elfogadás.
Az eredeti dokumentum/digest változatlan, külön egyedi receipt marad;
a claim nem szabadul fel. A meglévő közös tranzakció opcionális szigorú
commit-ellenőrzése az egyetlen megosztott viselkedési kiegészítés.
Teszt: valódi izolált MariaDB verseny/ACK/rollback/readback + szintetikus SDK
HTTP, nulla élő provider-kérés. Post-release: default-off, pontos schema,
korábbi payment-inspect/fee-prepare és disabled melletti readback.
Rollback: először új dispatch tiltása; saját fájlok exact-file mentése/CAS;
receipt/attempt/claim megmarad, történeti webhook és olvasás tovább működik.
Kézi UI checklist majd kiadáskor: saját NGO-adományoldal és saját rendelés
státusza tovább olvasható, nincs automatikus fizetés/refund/plusz szavazat.
Ez még implementáció, teszt/review/commit/telepítés/aktiválás nem igazolt.


### D2aa végleges forrás és ellenőrzés — 2026-11-03

Meglévő három PHP-fájl bővült; új termékmodul vagy SDK nincs. A fee-schema
explicit --apply új, operation_id szerint egyedi immutable receipt táblát
készít; a régi operations/claims mezőit és dokumentumait nem írja át.
A régi fee-prepare dry-run/azonos digest eredménye megmarad. A fee-execute
--operation=<64hex> --digest=<64hex> csak a pontos tárolt kérelemre és
sharity_donation_fee_execution_enabled='1' mellett indulhat; default OFF.
Nincs új bekapcsoló CLI, cron vagy HTTP-végpont. A régi checkout-kapcsoló
nem tilthatja a történeti pénzügyi ügy rendezését. A fee-recover ugyanilyen
azonosítókkal GET-only, kikapcsolt dispatch mellett is használható.

A platform/account/fee/mód/deviza és teljes refundlista ellenőrzése után
friss frame/source/target/observation/movement-egyezés kell; az operation
és claim attempted állapota ugyanabban a pinned tranzakcióban íródik.
Csak sikeres COMMIT-ACK + teljes readback enged egyetlen POST-ot. UPDATE-,
commit- vagy readback-bizonytalanság nem enged új POST-ot; új kulcs nincs.
A request/digest/idempotencykey változatlan, a receipt külön marad.
A CLI 60s határidő, legfeljebb23 GET/1 POST, kérésenként legfeljebb5s,
retries0, fix platform fee endpoint, Stripe-Account/Context tiltás.

A provider_recorded csak a refund erőforrás igazolt létét jelöli. A backing
attempted foglalása teljes összegben megmarad; nincs allocation, szavazat,
settlement vagy NGO-bankbeérkezés. A fee-recover teljes listát kér (max10x100),
csak egy pontos operation/fee/account/currency/amount match fogadható el;
hiány/ambiguity/hiányos lapozás nem pénzügyi siker. A nullable/későbbi balance
nem az immutable receipt része: a valódi platform-debit és NGO-credit
egyeztetése külön következő csomag. Sikertelen restore esetén receipt vagy
pénzügyi napló nem törölhető, a régi 50/50 út nem aktiválható.

Primer szerződés: [Stripe fee refund](https://docs.stripe.com/api/fee_refunds/create),
[list](https://docs.stripe.com/api/fee_refunds/list),
[idempotency](https://docs.stripe.com/api/idempotent_requests).
A telepített22.1.0 SDK ApplicationFeeService endpointjait is visszaolvastuk.

Tényleges ellenőrzés: SHARITY_DONATION_TEST_PORT=13390 php -d memory_limit=512M
 tests/sharity-donation-orders-mysql.test.php — 4395 check, ebből886 új.
Két valódi DB-kapcsolat, UPDATE-ACK/commit előtti-utáni/readback hiba,
provider-válaszvesztés, receipt INSERT/ACK, stale target/expiry, négy natív
deviza, hibás account/currency/amount/fee, második oldal/hiányos lista,
receipt-corruption és engine drift szerepel. Valódi SDK, szintetikus HTTP;
nem fizikai hálózatszakítás és nem Stripe sandbox/élő pénzügyi elfogadás.
Finance-read447, runtime17, checkout197 és PHP lint/diff-check sikeres.
Saját loopback/tmpfs MariaDB-konténer eltávolítva, hiánya visszaolvasva.

Független review P1: GET/lock-várakozás alatt lejárhat a retained proof.
Javítva: friss óra a lock/source/attempt és post-commit POST előtt;
minimum frame/source expiry, read/lock/aftercommit regressziók zero POST.
Reviewer visszaolvasta és lezárta, más konkrét lelet nincs; nem futtatott
tesztet, ez nem független runtime vagy általános biztonsági PASS.

Hermes063 execution1bc30d16-9974-4184-8c83-7f3231935824,
taskbc9730edbdb5dce0de4ab058167fde24818ed1e85dd4eb148b345044bc4e1e0c;
30984input+8154output=39138token,1API,becsült USD0.004303572.
Tényleges számla és teljes Codex/review költség ismeretlen. Csak a
manual execute/recover és SDK irány részleges átvétele; hibás helperhívások,
immutable document mutáció, nem atomi attempt és disabled recovery miatt
jelentős helyi újraírás, nincs igazolt megtakarítás. Nincs retry/JEV/fallback;
pending/terminal Hermes esemény remote_ack; termék-checkpoint külön mentve.

Forráskész, helyi. Kiadás: elfogadott merge SHA, exact-file backup/CAS,
SDK változatlan, új adapter/ledger majd CLI, default OFF, explicit fee-schema
--apply, engine/PK/tartalom visszaolvasás, sandboxlánc, külön account/díj/
fedezet és konkrét művelet jóváhagyása. Pénzmozgató próba most nem futott.
Rollbackkor dispatch OFF, attempted/receipt/claim nem törölhető; a működő
recovery és a történeti webhook marad. A teljes termékcél és korábbi UI
checklist nyitott. Nincs mainablak/merge/deploy/aktiválás/élő elfogadás.
