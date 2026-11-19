# JYSK — nyolc Card katalógusba kötése, 2026-11-09

## 2026-11-19 — napi besorolás: koherencia és kockázat

Cél: a teljes aktív Card-directory napi átvétele adja a meglévő Base/Rising/Legend
besorolást, a 25-ös részletrotációtól függetlenül. A WP eredeti szintszámítása,
identity/NGO-ID, pont, szavazat, pénzügy és a normál/VB katalógus változatlan.
A felhatalmazás a napi frissítéshez szükséges szűk WP/TS olvasó/átviteli javítás;
éles telepítésre vagy új jogosultságra nem vonatkozik.

Érintett hook csak GET `/impact/v1/ngo-catalog?card_directory=1` sikeres alapválasz
után; upstream a meglévő `ImpactShop_NGO_Card_API::get_dataset_items(false)`.
A false friss, legfeljebb 15 perces natív cache-t vagy sikeresen újraépített
adatot ad; hiba esetén nem hozza vissza stale-ként a régi datasetet.
A natív cache/újraépítés belső működése megmarad. Új endpoint/cron/tábla/író nincs.
Downstream: meglévő napi producer → hash-ellenőrzött V2 Turso-snapshot → Card,
részletes oldal és embed. Opcionális badge-hiba nem veheti el az identitásokat.

Kockázat: tierforrás-hiba vagy ismeretlen key nem lehet kitalált Base; más slug
nem keveredhet a sorba; csak key/szűrt label/idő kerülhet ki, összeg és privát mező
nem. A megfigyelési idő nem az eredeti pénzügyi/szintszámítási időpont, és nem
minősítheti frissé a régi támogatási részletet. Egy in-process batch történik,
nincs szervezetenkénti HTTP-kérés. A meglévő addon additív mezőbővítése kisebb
felület és költség, mint új plugin/hook vagy a védett szintszámítás módosítása.

Kötelező célzott ellenőrzés: PHP syntax és izolált directory-fixture; ordinary
route, pagination, approval és identity-coverage megmarad; új tier/hiány/hiba,
key-szűrés, slugkötés és tier-váltás külön fixture. Natív secret/protected-touch/
commit-lane audit. A felület a Card #2171-ben kap külön badge-időt; a régi
snapshot fallbackje és a stale támogatási részlet megmarad.

Kiadás csak merge után a meglévő egyfájlos guarded deployon, külön konkrét
jóváhagyással; előkép, pontos hash-CAS, lint és külön readback szükséges.
Rollback a saját mentett normál addonra csak változatlan saját utóállapotból;
producer és közös web visszaállítása külön, korábbi ellenőrzött artifactból.
Nincs kézi teljes baseline vagy pont/pénzügy/identity helyreállítás.
Kézi UI-lista: katalógus, részletes oldal és embed ugyanazt a forrástiert mutassa;
régi vagy hiányzó tier jelölt, a támogatási adatok ideje változatlan.
A természetes napi futás utáni pontos badge/snapshot readback még függő.

A saját natív Card-jóváhagyás megvan mind a nyolc weboldalt megadó szervezetnek. A korábbi25approval megmaradt; a névfeloldó aktív CSV8új párt kapott, eredeti bájtjai megmaradtak. A JYSK játék, cardCommitment, pont/szavazat, hozzájárulás és pénzügyi jogosultság nem változott. Kapcsolattartói adat nincs a forrásban.

Új additív modul: `wp-content/mu-plugins/impactshop-ngo-card-directory.php`; a meglévő eredeti200-as GET `/impact/v1/ngo-catalog` válasz után kizárólag `card_directory=1` kérésnél működik. A normál katalógus és a VB-választó érintetlen. A primary query teljes aktív állományához fűzi az explicit metadata-opcióban és a natív Card-jóváhagyásban is szereplő új Cardokat. Public allowlist; kitalált pénzügyi NGO-ID, campaign flag vagy option/DB írás nincs. A hiányzó kanonikus forrás/metadata503, a támogatáson kívüli county/campaign400; eltérő név/slug nem írhat felül másik szervezetet. Native approval visszavonása elrejti a kiegészítő rekordot. Az önálló `ngo_id` nélkül megjelenő Card nem pénzügyi vagy támogatási jogosultság.

Az opció neve `impactshop_ngo_card_directory_v1`; a pontos nyolc nyilvános rekord `config/ngo-card-directory-jysk-20261109.json`. Csak létező natív jóváhagyással és pontos névegyezéssel jelenhetnek meg; az adminisztráció a meglévő WP-CLI/operator út. Új adminpanel, publikus író-API, séma és cron nincs. A JSON-ból személyes kapcsolattartó, adószám, banki/merchant/fund-mező kimarad. Logó és jóváhagyott projektleírás hiányában nem találunk ki ilyet; a Rugby/DKKA küldetésmezője ezért nincs kitöltve.

Célzott teszt: `php tests/impactshop-ngo-card-directory-test.php`:47állítás sikeres, írásmentes fixture. PHP-lint sikeres. Független célzott review: nincs blokkolóP1/P2; county és hiányzó metadata pontosítása beépítve. Nincs teszteltnek állított éles publikáció. A saját Card-repó explicit opt-in paramétert kér és minden oldalon `identity_scope=ngo_card_directory_v1` markert követel; régi/ki nem bővített válasz nem csendes fallback.

Kiadás: normál commit/push/PR/merge; friss main-művelethez Arnold új csendablaka kell, readback után azonnali elengedéssel. A merge-elt új modul telepítése a meglévő exact-file úton, host/hash/előképes CAS, PHP-lint és readback mellett. A metadata-opció friss előképpel és exact natív approval-összevetéssel állítható a JSON-ra. Ezután csak a Card adatgyűjtő meglévő artifact-kiadása és a szükséges korlátos prepare/V1-V2 append következik; új webbuild, env/alias/campaign/JYSK-aktiválás nem szükséges. Teljes oldallánc-readback, a régi kanonikus NGO-k és minden új Card ellenőrzése szükséges; örökölt not-found200 miatt HTTP200 önmagában nem elég.

Rollback: kizárólag saját változatlan utóállapotból opcióelőállapot és új modul visszavonása; a kapcsolt adatgyűjtő korábbi runtime-ja és valósan friss snapshot előre publikálása a meglévő append-úton. Teljes DB-restore, idegen frissítés felülírása, hamis időbélyeg vagy pénzügyi adatpótlás nincs.

Saját WP-worktree a Minin: `/Users/bujdosoarnold/.worktrees/impactshop-notes-ngo-card-news-release-20261102`; ág `feat/jysk-ngo-card-directory-current-20261109`; rögzített alap `388bc97259aff383fa79057957dfa1adb7270b09`. Arnold a pontos munkaterületre külön megerősítette a forrás-/teszt-/dokumentációírást. Az idegen checkout nem módosult.

Állapot: nyolc natív WP Card létrejött; a modul és public metadata forrásjelölt. Nincs még merge, modultelepítés vagy modern katalóguspublikáció. Közösmemória-ACK nincs; az útvonal lezárt, a saját checkpoint helyi.

A korábbi #259 PR dokumentumütközését feloldottuk, de a mainből érkező vegyes fájlcsoportú merge-commitot a natív commit-lane elutasította. A jóváhagyott egyszeri ágcsere után ugyanaz a nyolcfájlos Card-delta került az új rögzített alapra, külön forrás-/dokumentációs commitokkal, force-push és guardmódosítás nélkül. A régi ág és a feloldott delta hash-elt mentése megmaradt.
