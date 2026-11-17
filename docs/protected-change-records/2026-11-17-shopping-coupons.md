# Shopping kuponfeed – additív C1

Scope: új `impactshop-coupon-feed.php`, offline fixture-teszt és a meglévő inventory/hash nyilvántartás kiegészítése. A meglévő affiliate, reward, pont, JYSK, guide, autobanner, tulajdonosi és pénzügyi runtime kódja nem változik.

Kapcsolatok: meglévő service-auth checker → privát cache-only GET; meglévő Dognet token-cache → explicit bounded refresh; saját nem autoloadolt policy/config/snapshot/state/lock optionok; `impactshop_coupon_refresh_v1` órás hook kizárólag explicit CLI-enable után. Betöltéskor nincs frissítés, ütemezés vagy provideraktiválás. A többi hálózat adaptere inaktív.

Konkrét kockázat: hibás program/site kötés vagy kuponfeltétel jutalék elvesztését okozhatja; stale/partial adat érvényesnek látszhat; egy késői refresh visszavonás után publikálhatna. Exact binding, kvalifikációs policy, fail-closed dátum/compatibility, cache-state-owner ellenőrzés és saját config/policy/lock fence védi ezeket. GET nem hív providert, személyes kód és tracking URL nem kerül a publikus projekcióba.

Valós Q 2026-11-17: a saját Dognet token-cache-ből két korlátos olvasó POST (limit1, majd teljes limit100), mindkettő200;21 rekord, végleges lap1/1. A21 rekord kezdő/végdátuma null, `form=1`,20×`type=1`,1×`type=2`, `url_type=3`. Ez nem bizonyít ongoing szemantikát vagy publikálási/attribúciós kompatibilitást. Éles policy és enable nem történik e bizonyíték alapján. Token/kód/raw válasz nem tárolt. `DISABLE_WP_CRON=true`; a meglévő külső cron-wake elfogadása külön függő.

Teszt: `php -l` és saját standalone mockfixture; a végleges eredményt a kiadási jegyzetben rögzítjük. Mockolt HTTP és option-CAS vizsgálat nem valódi DB/cron vagy provideraktiválás.

Release: merged saját checkout, existing exact-file guard staging→production; új plugin expectedSHA=absent, célhash/mode/lint és anonim401 readback, valamennyi új kapcsoló OFF. Policy-cache pilot csak igazolt saját programjog és szemantika után. WP rollback a saját exact-file release backupból, csak a saját változatlan célhashről; előtte provider disable és saját hook leállítása. Pénzügyi táblákhoz és teljes DB-restorehoz nem nyúl.

UI checklist: a Sharity Vásárlási Segédben a kód feltétellel/lejárattal látszik; másolás nem redirect; inputváltás/lejárat után eltűnik; elérhetetlen kupon mellett partner és vásárlási megerősítés működik. A pozitív élő kupon és természetes órás frissítés még nem elfogadott.

## Érintett fájlok / smoke scope

A termékfájl mellett docs/impactshop-protected-files.json, docs/impactshop-guard-config.json és .sha256, docs/impactshop-guard-hashes.json és .sha256, docs/bastion-guard-status.md a meglévő védelmi inventoryt bővíti. Új guardkód vagy általános release rendszer nincs. Smoke tagek: deploy:guard-preflight, deploy:checksum-verify, coupon:cache-only, coupon:offline-contract. A preflight/chksum izolált fixture és readback; éles preflight nem futott.

### Valós HTTP router-kompatibilitás javítása

Az első telepítés valódi HTTP-próbája400-at adott: a WP core a pretty REST útvonalnál is hozzáadja a `rest_route` queryparamétert. Csak az e végponthoz tartozó pontos útvonalat engedjük meg; ismeretlen/dupla paraméter, idegen routerút továbbra is400. Service-auth, binding, provider, cache/cron és pénzügyi út változatlan.153 offline assertion PASS; új pozitív/negatív routeresetek és0upstream. Élő újratelepítés/readback még függő; az eredeti fájlhash rollback-cél.
