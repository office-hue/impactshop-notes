# Sharity P2n — WordPress preferenciák (forrásmunka)

## Felhatalmazás, koherencia és határ

Arnold jóváhagyott scope-ja a Sharity lebegő gombsor és közvetlen oldalai
nemzetköziesítése, design- és működésmegőrzéssel. Saját Notes worktree:
`impactshop-sharity-internationalization-20261031`, ág
`feat/sharity-finance-intl-20261031`, base `88cb74f485229ddb2a04a8868a4bbd3eda29fb62`.
Nincs main fetch, remote írás, merge vagy deploy. A donor érintetlen.

Érintett termékkód: `impactshop-action-bar.php` meglévő választója/inline JS-e;
az identity és ads-watch intl overlay csak az új, kifejezetten megjelölt
navigáció/választó szöveg- és linkhatárát hagyja saját fordítójának.
Az identity-panel PHP/JS, auth-proxy, challenge/reward/runtime, affiliate,
országfüggő kínálat és árfolyam authority, shortcode-ok/endpointok változatlanok.
A meglévő választó bővítése kisebb és koherensebb, mint új párhuzamos plugin/toolbar.
A közös footer kizárási feltételeit és actionbar-elrendezését megőrizzük.

A szöveg- és nyelvpreferencia nem pénzügyi írás. Nyelv HU/EN/DE/FR, deviza
HUF/EUR/USD/GBP külön. A meglévő HU/EN oldalváltó linkek és az országválasztó
megmaradnak; DE/FR egyelőre a navigáció és választó nyelve. Ezt a felület közli.
A régi USD-fallbacket nem kötjük az új devizaválasztóhoz. Nincs törlés/adatvándorlás.

## Konkrét kockázat és ellenőrzés

- Cookie/storage/query prioritás, duplikált és kódolt paraméter; kizárólag
  ui_lang/ui_currency tisztítása, return/bridge/affiliate/hash/state byte-megőrzés.
- Régi EN overlay ne írja felül az új választást, ne dekorálja a kezelt
  selector linkeket. Ezen kívül a HU/EN profil- és challenge-fordítás megmarad.
- Mobil/szűk fekvő nézet, modal billentyűzet/fókusz, hosszabb német/francia címkék.
- Profil/account/restore linkek, disabled üzenet, pont/szavazat/adomány szöveg és
  összegek változatlanul működjenek. Nincs új API-hívás, háttérfolyamat vagy admin.
- Célzott PHP-render + valódi böngészős fixture, minden network helyben lezárva;
  syntax és meglévő protected inventory/hash teszt. A kiinduló hash-teszt PASS.

## Backup, kiadás és rollback

A három eredeti termékfájl saját `tmp/sharity-intl-p2n-backup/` mentésben, valamint
az exact base commitban van. A helyi mentést a forrás-UI ellenőrzés lezárása után
eltávolítjuk, másik mentéshez nem nyúlunk. Éles telepítéshez külön friss szerveroldali
backup/CAS és a meglévő `bin/impactshop-guard-deploy.sh` út kell, csak merge-elt ágból.
Forrásrollback a saját commit revertje; élesben előbb a Next küldő lekapcsolása,
utána az előző három asset koherens visszaállítása. A preference adatok a régi
kód számára inaktívak; az auth/session nem módosul.

## Kiadás utáni kézi UI checklist (még nem végrehajtott éles próba)

1. `/profil/`, `/impactshop/`, `/impact-challenge/`: lebegő gombsor és választó
   mobil/asztali nézetben, négy nyelv, külön deviza, country változatlan.
2. HU/EN oldalváltó és országváltó link; DE/FR mellett a fordítási korlát világos.
3. Profil megnyitás, mentés/visszaállítás, eredeti return cél; nincs elvesző kontextus.
4. Bolti/affiliate navigáció és meglévő összegek; nincs átváltás vagy jutalomváltozás.
5. Modal nyitás, Escape/bezárás, Tab/fókuszvisszatérés; cache-friss assetek.

## Tényleges helyi eredmények

- 7 valódi Chrome teszt sikeres: 16 nyelv/deviza kombináció; 7 query/cookie/storage/
  legacy-prioritási eset; HU/EN navigáció; DE/FR helyi váltás/reload; blokkolt
  storage/cookie/history; modal Tab/Escape/fókusz és 375×850, 667×375, 1280×900
  geometria; route-kizárások. Valódi PHP-render + mindkét tényleges overlay fut,
  minden hálózati kérés lokálisan kiszolgált vagy tiltott. Nincs éles API/írás.
- PHP lint és minden renderelt inline JS syntax-check sikeres. A választón kívüli
  teljes régi footer-script byteazonos. Az identity-overlay a kezelt UI-kizárás
  eltávolításával byteazonos; az ads-overlay egyetlen ilyen feltételt kapott.
- A 166 regisztrált útvonalon a meglévő hash-checker és hat negatív/pozitív eset
  PASS. **Ez a lista nem tartalmazza az action-bart**, ezért nem igazolja azt.
  Az action-bar korábbi manifesthash-e külön egyezett a base-mentéssel; saját új
  hash `7f225542ee67c8ba8c715f44fcbfe65880ac14992fae13272300e21997ecefad` és a
  manifest checksumja célzottan frissült. Más hash vagy guard-konfiguráció nem.
  Mindhárom termékfájlt a meglévő `.github/protected-files.txt` mu-plugin mintája
  védi; új module/guard/release rendszer nincs.
- A fixture első futása hibásan inline-olta a NUL karaktert tartalmazó régi overlayt;
  a HTML-parser átírta, ezért regexhiba lett. Külön JS-assetként kiszolgálva,
  a termékbájtok módosítása nélkül minden böngészőteszt sikeres. Nem éles hiba.
- Hermes014 `343bcc4a-de8d-410b-b53f-f49042024902`: completed/remote_ack,
  8 294 input + 2 235 output = 10 529 token, egy hívás, 8,638 s;
  becsült 0,001161524 USD, nem számla. Nincs JEV/retry/provider-fallback.
  Szótár és UI/CSS részben átvéve; javítva az át nem váltó devizaválasztó valótlan
  formázási ígérete, egységes navnevek, native select/nyelvi elemek, tényleges
  akadálymentes modal és a létező HU/EN linkek. Codex külön díja ismeretlen.

**Forráskész, nem release-ready.** A fixture nem teljes WordPress/Elementor vagy
éles profile/account-save/restore E2E. A fenti éles checklist és guarded rollout
még szükséges; az eredeti HU/EN oldalszövegek DE/FR fordítása és a pénzügyi P3–P7
nem része ennek a csomagnak. A WP kifelé vezető linkjei még nem adnak át új
preferenciát; csak a Next→WP profillink küldője készül a társrepo csomagjában.
A régi `lang/country` és USD-fallback változatlan legacy, nem új FX-authority.

A tesztparancs:
`USER_CARD_TEST_DEPENDENCIES_ROOT=<existing apps/web> node --test tests/impactshop-international-preferences.test.mjs`.
A meglévő Playwright és telepített Chrome használatos, nincs dependencytelepítés.
A helyi képek `output/playwright/` alatt készültek, megtekintés után a saját
ignorált `tmp/` alá archiválva. A forrás-UI elfogadása után a három saját átmeneti
backup törölhető; a base commitból a forrás bitpontosan visszaállítható.

## P2o — statikus profilcopy, változtatás előtti terv

Saját base `5a505817456400b2b4ea0e831c9fcfb9f90f306b`. Egyetlen termékfájl:
`impactshop-identity-panel-intl-overlay.js`; meglévő fordítóréteg és observer.
A `.impactshop-profile-shell` előre felsorolt címei, súgói, címkéi, statikus
másolás/frissítés/infó gombjai és input-placeholderjei HU/EN/DE/FR nyelvűek.
Az action-bar által már feloldott nyelvet olvassuk; nem készül új preferenciatároló.
Az eredeti dinamikus gombok (mentés/belépés/kódgenerálás/push/vakáció), pontok,
szavazatok, státuszok, nevek, üzenetek és összegek nem ennek a szótárnak a részei.
A régi angol fordítás a saját eredeti feltételeivel megmarad; az új statikus
feliratok nem indíthatják el véletlenül a régi teljesoldal-fordítást. A személyes
adatmezők nem szótárkulcsok, még akkor sem, ha szövegük egy UI-felirattal egyezik.
A pontszám és az adományszorzó számítása változatlan, egymástól külön adat.

Koherencia: meglévő footer választó → lang attribútum → meglévő overlay observer;
PHP-shortcode/core JS/auth-proxy változatlan. Nincs endpoint, API-hívás, cron,
admin, reward, affiliate, ledger, cookie vagy pénzügyi írás. Kockázat: legacy EN
felülírás, betöltési sorrend, szövegegyező személyes adat, dinamikus újrarender,
DOM-eseménykezelő/fókusz elvesztése, fordítási ciklus. Célzott, valódi PHP-markupot
és helyi Chrome-ot használó teszt; a meglévő P2n regressziós teszt újrafut.
Nincs új module/guard/kiadási rendszer; a meglévő mu-plugin protected glob érvényes.
A 166 regisztrált fájl hash-tesztjének korábbi eredménye csak a változatlan listára
használható, az overlay nincs benne: azt nem nevezzük az overlay igazolásának.

Mentés: saját `tmp/sharity-intl-p2o-backup/` exact overlay + a fenti commit.
Forrásrollback a saját commit revertje; kiadáskor csak merge-elt forrás, meglévő
Notes guard-deploy, friss élő backup/CAS, cache-frissítés és visszaolvasás.
A P2n választó legyen előbb jelen. Visszaállításkor elég az overlay korábbi assetje;
adatazonosság és pénzügyi állapot nem változik. Nincs jelen körben éles művelet.

Kézi UI ellenőrzés kiadáskor (még nyitott): `/profil/` négy nyelv, mobil/asztali;
DE/FR azonnali váltás, HU/EN navigáció; becenév/kód és beírt érték változatlan;
másolás/frissítés, info nyitás, mentés/belépés/visszatérés működik; összegek és
szavazatok változatlanok. A dinamikus állapotüzenetek HU/EN maradhatnak, a választó
jelenlegi figyelmeztetése továbbra is helyes. `/impactshop/` és compact profil
régi működésének ellenőrzése is szükséges. Ez részcsomag, nem teljes profilfordítás.

### P2o eredmény és ellenőrzési határ

30 statikus copykulcs integrálva, HU/EN/DE/FR. A szótár csak felsorolt profil-
címeken/súgókon/címkéken/placeholdereken és statikus gombokon működik. A választó
`lang` attribútumát ugyanaz az observer figyeli, új tároló/eseménybusz nincs.
Csak text node/placeholder/lang módosul: a gyermek info gomb, eseménykezelő,
input value/name/type/autocomplete, hidden/disabled állapot és return cél megmarad.
A legacy overlay kifejezetten kihagyja a személyes adatmezőket és inputértékeket;
selector nélkül a régi angol placeholderek tovább működnek. A már angolra fordított
statikus feliratokat későn érkező választó is vissza tudja venni. Nem nevezzük
négy nyelvűnek a még HU/EN dinamikus státuszokat/gombokat.

Két célzott stíluskiegészítés az overlayben: a szöveges points-info gomb nem
szorul a régi 22px ikonszélességre; a hosszabb introcopy a dekoratív kör elé kerül.
Színek, szerkezet, core CSS, többi gomb változatlan. Ez konkrét helyi olvashatósági
lelet javítása, nem új design vagy globális CSS-rendszer.

- 5 új Chrome tesztcsoport PASS: 4 nyelv × 2 legacy-alap; eredeti PHP-markup;
  visszatérési cél és titkos beviteli mező attribútumai; szótárral egyező becenév,
  kód, üzenet, beírt adat és `1 234,56 Ft` változatlan; ismételt helyi nyelvváltás;
  gombazonosság/esemény/fókusz/href megőrzés; currency önmagában nem írja át a
  profilcopyt; nyugalomban nincs megfigyelési ciklus; késői selector és legacy EN.
- Valódi változatlan core JS + szimulált helyi API: info billentyűzetes nyitás,
  frissítés, elutasított becenévmentés után engedélyezett gomb/eredeti label és
  pontos beküldött érték. Nem éles fiókmódosítás és nem teljes auth/save/restore E2E.
- A P2n 7 Chrome tesztcsoportja is PASS; az utolsó profil-only CSS/placeholder
  módosítás ezeket nem érinti. Összesen 12 külön sikeres csoport, nem 13: a
  végső CSS után csak az érintett geometriai próba ismétlődött.
- PHP fixture lint, overlay JS syntax és diff-check sikeres. Az integrációs
  határokon kívüli overlay bájtjai, eredeti NUL-ja, identity core PHP/JS,
  action-bar és ads-overlay egyeznek a kiinduló committal. Guard/hash nem változott.
- Első futás két lelete: a teszt click előtt mouseentert váltott ki, miközben
  a régi core hoverre már nyit, majd clickre zár; keyboard-próbára javítva.
  A 22px gomb valódi stílusprobléma volt, célzottan javítva. Nem két vak javítás.
  Saját review még a selector nélküli legacy placeholder megőrzését pontosította.
- Helyi képek 375×850,667×375,1280×900 nézetben áttekintve, `tmp/p2o-*.png`.
  Ezek a komponens saját CSS-ét és szimulált adatállapotát mutatják. Nincs teljes
  WordPress/Elementor téma/font/betöltési bizonyíték: a fixture globális box-sizing
  nélkül inputkilógást és rejtett részek üres helyét is mutatja. Ezekből nem
  állítunk éles hibát vagy teljes vizuális PASS-t, nem írjuk át emiatt a core CSS-t.
  A bounded copy/olvashatóság review után csak saját ideiglenes overlay-backup
  törölve; exact `5a505817` forrásbackup megmarad Gitben. Éles checklist tovább nyitott.

Hermes015: `862cd804-8fce-47cb-9d4e-649eb54e5e2c`, completed/remote_ack,
4 598 input +1 557 output =6 155 token,1 hívás; becsült **0,000701792 USD**.
A 30 kulcs szótára átvéve; Codex javította Refresh/Actualiser és a német/francia
felhasználható szavazat kifejezést, integrálta a szűk DOM-határt, legacy együttélést,
megjelenést és teszteket. Nincs retry, JEV vagy provider-fallback. Codex díja
ismeretlen. Forráskész részcsomag, nincs push/PR/merge/telepítés/aktiválás.

Következő P2p: dinamikus profilszöveg, greeting/név biztonságos paraméterezés,
points/history/votes/vacation/referral/badges állapotok és gombok. A JS a
`save-nickname` finally ágában a szó szerinti `Mentés…` szöveget is ellenőrzi:
naiv overlaycsere itt működési hibát okozna. Előbb bounded terv és Hermes-vázlat;
core-touch esetén a védett PHP/JS/auth-proxy kapcsolatot és meglévő return tesztet
célzottan vizsgálni kell. Addig a P2n fordítási korlátról szóló szövege helyes.


## P2p — dinamikus profilkijelzés, 2026-11-01, változtatás előtti terv

Saját base `4cb889dc251e988bd8983a92e5b211569a497770`, egy termékfájl:
`impactshop-identity-panel-intl-overlay.js`. Hermes016 az első kidolgozó.
A csomag a teljes profil üdvözlését, szavazatállapotait, pontösszegét/előrehaladását,
előnycímkéit, vakációállapotát és kapcsolófeliratát, Legacy Pool helyezés/pont
feliratát fordítja. Az eredeti számkarakterek, szintnevek és paraméterek megmaradnak.
Az adománybónusz csak címke, nincs új szorzó vagy pontképlet. A választott deviza
nem módosít pontot, szavazatot vagy pénzösszeget.

Koherencia: változatlan core JS render → meglévő overlay observer → lokalizált
text node; a P2n választó nyelvét használja. Nincs core PHP/JS/auth-proxy változás,
API, új observer/tároló, adatírás, admin, cron, reward, affiliate vagy pénzügyi ág.
A mentés/belépés/kódgenerálás/push és általános hibaüzenetek, aktivitástörténet,
NGO-nevek, badge-lista külön következő csomag. A szövegfüggő mentési vezérlést
nem érinti ez a megjelenítési kör; annak későbbi lokalizációja külön kontrollált javítás.

Kockázat és elfogadás: ismételt nyelvváltás/aszinkron újrarender ne hozzon vissza
régi adatot; a becenév és minden számkarakter pontosan megmarad; nincs HTML-
értelmezés, elvesző strong-elem/listener vagy mutációs ciklus. A régi angol overlay
ne írja át újra az átvett adatmezőket. Ismeretlen szöveget nem fordítunk találgatva.
Csak a felsorolt teljesprofil-role-ok érintettek; compact profil/shop változatlan.
A meglévő profilteszt bővítése, core-mockolt kérések és P2n választóregresszió elég;
nincs új tesztrendszer vagy széles pénzügyi audit.

Mentés: saját `tmp/sharity-intl-p2p-backup/` egy overlay-fájl és exact base Gitben.
Rollback a P2o overlayre; nincs adatmigráció. Élő kiadás csak meglévő guard-deploy,
merge-elt source, friss backup/CAS/cache/readback útján. A mu-plugin protected
perimeter megmarad; konfiguráció/hash és regisztrált guard-bemenet nem módosul.

Kiadás utáni kézi UI checklist: `/profil/` HU/EN/DE/FR, frissítés és nyelvváltás;
név/pont/szavazat/bónusz/helyezés értékei egyeznek; vakáció kapcsoló engedélyezése
és módja nem változik puszta nyelvváltástól; info/mentés/belépés tovább működik;
compact profil és shop legacy megmarad. Valós vakációállapotot próbaként nem írunk.

### P2p eredmény, 2026-11-01

27 üzenetsablon négy nyelven, tíz név szerint felsorolt teljesprofil-mezőcsoportban.
A parser kizárólag teljes, ismert sablont fogad el és a paramétereket sztringként
viszi át; nincs számolás/normalizálás vagy DOM-ból visszaírt üzleti állapot.
A szám, név, szintnév, emoji, strong-elem és bónuszérték megmarad. A meglévő
observer fut, új API/modul/dependency/tároló nincs. Ismeretlen szöveg változatlan;
korábban ráadott nyelvjelzését visszaállítjuk, nincs régi érték visszahozása.
A selector nélküli legacy EN üdvözlés nevét is megőrizzük: a globális fordító
nem fordíthatja le a névben szereplő „Profilod” vagy „Mentés” szavakat.

**9 külön profiltesztcsoport sikeres** (5 meglévő +4 új, ismétlés nélkül számolva):
- négy nyelv × két legacy-alap, mezők/return/összegek és statikus copy regresszió;
- ismételt váltás, esemény/fókusz/href/strong-azonosság és mutációs nyugalom;
- valódi core, helyi szimulált API: info, refresh és elutasított becenévmentés;
- öt identity-state, nyelvváltás után változatlan számok, disabled/mode, nulla POST;
- nevekben `!`, HTML-szerű szöveg, `$&`, `$1`, whitespace és `{0}` megőrzése;
- aszinkron felülírás, node-csere, ismeretlen payload, legfelső szint/üres helyezés;
- késői selector a tényleges legacy EN után, megőrzött névvel és számformátummal;
- core-renderelt német felület 375×850,667×375,1280×900: a kezelt feliratok
  konténer-geometriája rendben, képek megtekintve, `tmp/p2p-*.png`. A P2o-ban
  jelzett teljes téma/font/box-sizing korlát megmarad; nincs éles vizuális PASS.

Az első tesztfutás két téves fixture-feltevést jelzett: inaktív identity esetén
nincs vakációlekérés; a futó Chrome nem tagolja a négyjegyű 1234 értéket. Az utóbbi
próba első javítása még a native HU-írás és az EN observer közé időzített; az EN
megjelenési állapot megvárása után sikeres. Kizárólag ezeket a próbákat pontosítottuk,
nem a működést vagy a számformátumot. Nem volt provider-újrapróba vagy vak termékjavítás.
JS syntax és diff-check sikeres; core PHP/JS, selector, ads-overlay és az integrációs
határon kívüli legacy-bájtok egyeznek a base-szel. P2n 7 tesztjét és a változatlan
166 guard-bemenet korábbi eredményét újrahasználjuk, nem új futásként számoljuk;
a guard továbbra nem igazolja a listájából hiányzó overlayt.

Hermes016 `eae32b67-e3ce-4540-9137-f4a5224b0020`: completed/remote_ack,
10 624 input +4 532 output =15 156 token,1 hívás; becsült **0,001791608 USD**.
Szótár részben átvéve, természetesebb és a szavazatot pénzegyenlegtől megkülönböztető
copyval javítva. A kódvázlat tiltott core-touchot és üres/inoperatív részeket
javasolt; ezek elvetve. Codex integrálta és tesztelte a szűk szöveges megoldást.
Nincs JEV/retry/fallback; Codex költsége ismeretlen. Saját overlay-backup a bounded
UI-review után törölve, exact base Gitben; más backup/dependency/WIP változatlan.

Forráskész részcsomag, nincs push/PR/merge/deploy/aktiválás. P2q még nyitott:
mentés/belépés/kódgenerálás/push/általános státusz, history/NGO/badge részletek.
A mentési `Mentés…` összehasonlítást nem oldja meg ez a csomag; későbbi core-touch
csak a védett koherencia és meglévő return/állapot tesztek mellett készülhet.
A pénzügyi P3–P7 és minden fedezeti feltétel továbbra terv, nem aktív pénzmozgás.


## P2q — előzetes koherencia és kockázat (2026-11-01)

Base `9b7df230d12634e12813f28c99b9f7951bd8de2e`. A jóváhagyott profilfordítás
következő szűk egysége: becenévmentés, belépés, belépési kód megjelenítése és
ismert saját státusz-/hibaszövegeik. Hermes017 első tervezet, Codex integráció.
Érintett overlay és szükség esetén a core JS mentési gombjának szövegfüggő resetje.
PHP shortcode/DOM/data-role, auth-proxy, nonce/cookie, REST request/response,
identity-state, cache, redirect, timeridő és event szerződés változatlan.
A `/profil/` teljes panel az új fordítás célja; compact/shop core regresszió
ellenőrzendő. Nincs pont/szavazat/ledger, admin/import/cron vagy pénzügyi írás.

Konkrét kockázat: fordított busy szöveg miatt elmaradó gombreset; kérés közbeni
nyelvváltás után elavult címke; idegen szerverüzenet részleges átfordítása;
kétszeres request vagy auth/return regresszió. Megelőzés: minimális UI-only core
korrekció, meglévő exact-match overlay; valódi core és helyben szimulált siker,
elutasítás, hálózati hiba és pending közbeni váltás; meglévő return teszt.
Nincs új guardmodell. Core digest változásakor csak a meglévő hash-bejegyzés és
manifest checksum frissülhet, inventory és policy változatlan marad.

Backup: saját `tmp/sharity-intl-p2q-backup/` két asset, exact base Gitben.
Rollback: core és overlay együtt a base-re; nincs adatvándorlás. Saját backup
legfeljebb két napig, helyi UI-review után eltávolítható; más mentés érintetlen.
Kiadás csak a korábbi §12 útján, friss élő backup/CAS/readback mellett.
Kézi kiadás utáni checklist: `/profil/` HU/EN/DE/FR; becenévmentés siker/elutasítás
és gomb visszaállása; hibás belépési formátum, saját fiókos belépés és visszatérés;
kódoldal és popup-fallback; nyelvváltás folyamat közben; compact/shop regresszió.
Ezek éles ellenőrzése most nem történt meg.


## 2026-11-01 — P2q profil műveletszövegek, forráskész

Saját `9b7df230` base-ről: 55 HU/EN/DE/FR sablon, hat új szerepkör a meglévő
identity overlayben. Becenévmentés, belépés és kódmegjelenítés gomb/státusz;
a közös státuszmező 30 további ismert helyi szövege is bekerült, mert enélkül
az új mezőkezelés elnyomta volna a korábbi EN fordítást. Ez integrációs regresszió
elhárítása, nem új funkció. Ismeretlen szerverüzenet nincs részlegesen fordítva.
Core JS: két helyi boolean-értékadás és egy finally feltételcsere; a mentés
sikerét nem a lefordítható `Mentés…` feliratból állapítjuk meg. A 1600ms sikerjelzés,
request/body/nonce/disabled/event/redirect, PHP és auth-proxy szerződés változatlan.
Nincs CSS-, pont-, szavazat-, szorzó-, deviza- vagy pénzügyi módosítás.

Ellenőrzés: **13/13 profilcsoport**, **25/25 meglévő return-csoport**, identity-v2
statikus kontraktus, syntax/diff és a teljes meglévő guard inventory +6 izolált
hash/checksum eset sikeres. A core hash és meglévő manifest checksum célzottan
frissítve; policy/inventory/guardmodell változatlan. Az overlay nincs a hash-listában,
annak ellenőrzése külön a profilteszt és diff. Pontos rekonstrukció igazolja:
core csak a három UI-helyen, overlay csak szótár/szerepkör-bővítésben változott.
A P2n selector tesztjét most nem futtattuk újra; régi VB6/Sharing12 release-leletek
nyitottak. Minden browser HTTP helyben szimulált/tiltott, nincs éles fiókművelet.

Új próbák: halasztott request alatt DE→FR váltás; sikerjelzés alatt EN váltás;
siker/elutasítás/network hiba és egyszeri POST; pontos nick/credential/body;
restore helyi validáció és natív completion navigáció; popup URL/options és
same-window fallback; ismeretlen szerverüzenet és meglévő status EN megőrzése.
Az első restore-próba hibásan más pathname-re várt a natív completion URL helyett;
a fixture elvárása javítva, a működés nem. Egy egyszeri diff-rekonstrukciós parancs
rossz szótárjelölőt keresett; a pontos jelölővel sikeres. Nincs vak termékjavítás.

Három komponenskép megtekintve: `tmp/p2q-375.png`, `tmp/p2q-667.png`,
`tmp/p2q-1280.png`. A későbbi kizárólagos státuszszótár-bővítés után a teljes
geometriateszt ismét sikeres; a képek az előtte készült UI-t mutatják. Teljes
WP/Elementor/theme/font/éles account-flow továbbra nincs igazolva, a korábbi
fixture inputkilógási és fenntartott-hely korlát megmarad. Saját kétasset-backup
helyi review után törölve, exact base Gitben; más backup és idegen WIP érintetlen.

Hermes017 `953451c4-c3d2-4e63-9e27-1c738b7e808d`: completed/remote_ack;
8 383 input +2 822 output = **11 205 token**,1 hívás, **becsült0,001276457 USD**,
nem számla. Szótár és boolean-javaslat átvéve; kért patch helyett terv érkezett,
hiányzó idle-label és közös status bekötés, hibás role-lista Codexnél javítva.
Codex integrálta a kódot és teszteket; díja ismeretlen. JEV/retry/fallback nincs.

Forráskész részcsomag; nincs push/PR/merge/deploy/aktiválás, nem release-ready.
Következő P2r: push állapot/gomb, maradék popup/copy/share/üzenetek és
history/NGO/badge megjelenítés célzott szövegleltárból; Hermes018 még nincs kiadva.
Pénzügyi P3–P7 terv változatlan, nem aktív. Rollback core+overlay együtt `9b7df230`,
a meglévő manifest/checksum ugyanazon commitból; nincs adatvándorlás.
Kiadás továbbra meglévő guard-deploy, merge-elt source, friss backup/CAS/readback.

Kézi kiadás utáni UI: `/profil/` négy nyelv; sikeres és elutasított becenévmentés
után használható gomb; belépés és visszatérés; kódmegjelenítés popup/fallback;
folyamat közbeni nyelvváltás; compact/shop regresszió. Ez még nem éles PASS.


## P2r előzetes scope, koherencia, rollback (2026-11-01)
Base `d0362587`. Csak a teljes `/profil/` meglévő overlaye: push-gomb/státusz,
pontelőzmény helyi címkéi/pont-utótagja, legutóbbi NGO helyi környező szövege.
Hermes018 első tervezet. Core PHP/JS, push/SW permission, subscribe/unsubscribe,
NGO selection POST, pont/adomány/árfolyam és auth/redirect változatlan.
Kockázat: szövegnek vélt név/dátum/szám sérülése, DOM/listener/disabled elvesztése,
aszinkron újrarender és legacy EN felülírás. Célzott core-browser fixture, lokális
HTTP és push-capability mock, pontos adat- és eseménymegőrzés ellenőrzése.
Ismeretlen provider-jelvénynév/leírás adat, fordítása külön szerződés; most nem írandó.
Nincs admin/import/cron/guardmodell vagy új infrastruktúra. Saját overlaybackup:
`tmp/sharity-intl-p2r-backup/`, legfeljebb két napig, helyi UI-review után törölhető;
Git base megmarad. Rollback csak overlay a base-re, adatvándorlás nincs.
Éles út változatlan §12, most nincs kiadás. Kézi UI majd: `/profil/` négy nyelv,
push státusz/toggle és változatlan tiltás; előzmény pontos szám/dátum/sorrend;
NGO-név és választógomb; üres/hiba és nyelvváltás; compact/shop legacy.


## P2r eredmény (2026-11-01)
36 új HU/EN/DE/FR sablon és négy szerepkör a meglévő overlayben. A push helyi
szövegei, 20 ismert előzménycímke és a legutóbbi NGO körüli feliratok fordulnak.
A dátum, pontszám és NGO-név karakterlánca megmarad, nincs újraszámítás.
Az előzmény kizárólag saját listája két egyszerű spanből álló sorait kezeli;
NGO-nál a közvetlen szöveg/span/button, pushnál a már létező data-role a határ.
A meglévő matcher/observer/WeakMap használatos; nincs új observer vagy széles
DOM-bejáró. Névben HTML-szerű szöveg, `$&`, `{0}` és szóköz megőrizve.
A selector nélküli EN-ben az NGO-név is védett az általános cseréktől.
Az `Ismeretlen NGO` név karakterlánca adatként megmarad: az API-név és a core
fallback nem különböztethető meg biztonságosan puszta szövegből.

**18 külön profiltesztcsoport sikeres** célzott futásokban (13 korábbi+5 új).
Valódi PHP/core és helyi HTTP; 21 tranzakciótípus, dátummal/anélkül, előjel és
formázás, négy nyelv és újrarender; NGO-választógomb eredeti egyetlen POST-ja;
push engedély/feliratkozás/leiratkozás csak mockban, nyelvváltáskor nulla írás;
tiltott/inaktív/hiányzó worker; DOM/listener és ismeretlen tartalom megőrzés.
Első futás16 siker+1 hibás mock: a ready Promise túl korai elutasítása pageerror-t
okozott. A mock getterrel csak a core lekérésekor utasít el; érintett újrafutás
sikeres, termékkódot ezért nem módosítottunk. Syntax/diff-check sikeres.
Core PHP/JS és hash-manifest/checksum byteazonos P2q-val: annak25 return és guard
bizonyítéka újrahasznált, nem új futás. Az overlay továbbra nem hash-listaelem.

375×850,667×375,1280×900 kitöltött push/history/NGO komponensképek megtekintve,
kezelt szöveg-geometria sikeres: `tmp/p2r-*.png`. Teljes WP/Elementor/theme/font,
éles push/fiók és végpont nincs ellenőrizve. A korábbi fixture inputkilógása
változatlan; nincs teljes éles vizuális PASS. Saját overlaybackup helyi review
után törölve, exact base Gitben megmaradt, más mentés/idegen WIP érintetlen.

Hermes018 `6fb992f5-ecf9-449b-8ff0-7129d4601474`: **partial**, natív terminális
lezárás és Secretary remote_ack,1 API-hívás. Használat/díj **ismeretlen**: a nulla
számlálók nem bizonyítanak nulla költséget. Szótár részben átvéve; széles li/span/
button bejárás, csak HU/EN felismerés, minden passban új WeakMap és téves hiányzó
push-role feltételezés miatt a kódjavaslat elvetve. Codex a meglévő szerepkörös
fordítóba integrált és tesztelt; nincs retry/fallback/JEV, Codex-díj ismeretlen.

Következő P2s: maradék core prompt/share/dialog és üzenetgomb; badge-adatok
nyelvi szerződése. Pontos forrás: `impact-gamification.php`507–511, a
`badges/available` csak name_hu/description_hu mezőt ad; a core ezeket rendereli.
A nevek nem statikus UI-szótárként kezelendők. Hermes019 nincs kiadva.
Pénzügy és P3–P7 változatlan, nincs pénzügyi aktiválás. Forráskész részcsomag,
nincs push/PR/merge/deploy/aktiválás; a teljes ág nem release-ready.
Kiadási út §12 változatlan; rollback P2q overlay (`d0362587`), adatvándorlás nincs.
Kézi éles checklist fent: négy nyelv, push-állapot és eredeti engedélykapu,
előzmény pontos szám/dátum/sorrend, NGO-név/választógomb, üres/hiba és compact/shop.


## P2s előzetes koherencia és határ (2026-11-01)
Base `620973b4`. Jóváhagyott profilfordítás: ismert seed-jelvények neve és title
leírása, üzenet nyugtázógomb. Hermes019 első tervezet. Badge key+seed meta egyezés
kell; egyedi/hiányos metadata maradjon eredeti. Core csak renderelt key marker,
overlay kezelt childszöveg/title; csoport/sorrend/tier/reward/API/DB változatlan.
Az API `name_hu`/`description_hu` mezőinek szerződése nem módosul. Üzenet arbitrary
content változatlan, read POST és hidden állapot a core-ban marad.
Kockázat: egyedi név véletlen fordítása, régi EN overlay adatátírása, title/name
eltérése, async rerender/stale adat, read gomb eseményvesztés. Célzott valós core
fixture, minden HTTP helyi; byte-diff, seedadat és invariánsok ellenőrzése.
Nincs CSS, pénzügy, auth, redirect, admin/import/cron vagy guardmodell-módosítás.
Core marker esetén meglévő hash+checksum célzott frissítése. Saját kétasset-backup
`tmp/sharity-intl-p2s-backup/`, legfeljebb két napig; helyi UI-review után törölhető.
Rollback két asset és szükség esetén hash/checksum együtt a base-re; nincs migráció.
Kézi kiadás utáni ellenőrzés: `/profil/` négy nyelv, ismert jelvény neve és tooltip,
egyedi meta megőrzés, üzenetszöveg változatlan, Rendben gomb nyugtáz és eltűnik;
compact/shop regresszió. Kiadás továbbra §12, jelenleg nincs éles művelet.


### P2s eredmény és ellenőrzés
A meglévő overlay 41 seed-kulcs teljes HU/EN/DE/FR név/title párját kezeli.
Csak pontos kulcs+pár egyezés fordul; ismeretlen/egyedileg módosított/hiányos
adat változatlan. A badge-container a régi globális EN fordítóból kizárva,
selector nélküli és későn érkező selector esetén is. Lang-eredet megőrzött és
visszaállított; új/rich szövegből nem támad fel régi adat. Az üzenet közvetlen
meglévő gombja a szerepkörös fordítóban kap feliratot, új gomb/listener nincs.
Core JS egyetlen additív data-badge-key sor: ennek eltávolítása bytepontosan
visszaadja a base-t. API/PHP/CSS/adatbázis/pont/pénzügy/redirect változatlan.
A meglévő hash és checksum frissült, guardpolicy/inventory nem változott.

Ellenőrzés:21 külön profilcsoport sikeres célzott futásokban (18 meglévő+3 új).
Az első futás19 sikeres/2 hibás teszt; pontos ok és javítás: a badge refresh
aszimmetrikus waitje már a régi fordított node-on teljesült, ezért elemszámra
is vár; message mock hibás sharity namespace helyett tényleges impact/v1,
a profilfrissítés nem kér üzenetet, ezért új initet oldalújratöltés ellenőriz.
Termékkódot a tesztmock miatt nem módosítottunk. Három új csoport újrafutva3/3;
rich-label lang restoration bővítés után az érintett csoport1/1.
Native csoport/sorrend, mind41 név/title roundtrip, késői selector, ismeretlen/
hiányos/metaátírás, prototípusnév, DOM/listener, célzott pontos egy read POST,
broadcast nulla POST ellenőrizve; minden hálózat helyi mock.

Parancs: `USER_CARD_TEST_DEPENDENCIES_ROOT=/Users/bujdosoarnold/.worktrees/ai-agent-sharity-internationalization-20261030/apps/web node --test tests/impactshop-profile-copy.test.mjs`;
érintett újrafutások `--test-name-pattern='native badge|badge custom|native account acknowledgment'`, majd `'badge custom'`.
`python3 tests/impactshop-identity-profile-v2-static.test.py`,
`python3 tests/impactshop-guard-hash-baseline.test.py` (teljes inventory,6 eset),
JS syntax és diff-check sikeres. Korábbi25 return eredmény újrahasznált:
csak render marker core-diff, visszaút kódja/bemenete változatlan; nem új PASS.
Mobil375px német native jelvény-komponenskép megtekintve: `tmp/p2s-badges-de.png`.
Nincs CSS-diff; jelvények elférnek. A korábbi fixture inputkilógása/üres helye
megmarad; teljes WP/Elementor/theme/font/éles account nincs ellenőrizve.
Saját kétasset-backup helyi review után törölve; exact base620973b4 Gitben.
Rollback két asset+hash/checksum együtt620973b4; nincs adatvándorlás.

Hermes019 e857208f-739c-4ed3-a34d-2ca001c6d5dd completed/remote_ack:
7857 input+3751 output=11 608 token,1 hívás; becsült0,001397669 USD
(provider models API), Codex-díj ismeretlen. Szótár kisebb nyelvi javítással
átvéve; kód elvetve: key-only felülírás, HU/visszaállítás hiánya, ismeretlen
adat EN-védelmének hiánya, duplikált ack gomb elvesztett POST-tal. Codex
integrálta/tesztelte. Indító path inputhiba dispatch előtt javult, fizetős
retry/fallback/JEV nincs. Secretary evt-b44fd02c-0c2f-4971-8afc-cc3738abf35e ACK.
Inaktív share/copy-recovery dialógushoz nem nyúltunk: jelenlegi PHP nem rendereli.
Következő P2t meglévő WP kifelé preferenciaátadás; további oldalak és P3–P7 nyitott.
VB6/Sharing korábbi nyitott leletek változatlanok. Forráskész részcsomag,
nincs push/PR/merge/deploy/aktiválás. Teljes ág nem release-ready.

Kézi UI-checklist kiadás után: `/profil/` HU/EN/DE/FR, ismert jelvény neve és
hover title ugyanazon nyelven; egyedi meta eredeti; nyelvváltás/Frissítés után
változatlan jelvénysorrend; üzenet eredeti szöveggel, egyetlen Rendben/OK gomb,
kattintásra eltűnik és célzott üzenet olvasottá válik; compact/shop ellenőrzés.
Kiadás a meglévő §12 szerint, mainművelethez szűk quiet window szükséges.


## P2t előállapot és jóváhagyott munkahatár — 2026-11-01

Base48905f887f34f1eb309bcba147625cfc03af2574, tiszta saját worktree.
A jóváhagyott terv szerint csak az action-bar meglévő preference-scriptje és
annak tesztje kap kifelé ui_lang/ui_currency átadást hat pontos külső dock-célra.
Koherencia: WP footer→meglévő választó state→dock href→már létező Next/FactLens
fogadó. Az identity/ads overlay managed jelölés mellett nem írja a dockot.
Érintett funkció: külső navigáció, linkmásolás/új lap, nyelv/devizaváltás.
Kockázat: más query vagy return/attribution sérülése, régi választás felülírása,
rossz host továbbítása. Elfogadás: csak saját két paraméter változik; exact
célok; minden más raw query/fragment megmarad; helyi profil, országválasztó,
auth/merchant/affiliate link és disabled messages változatlan. Nincs új POST,
API/admin/cron/pont/szavazat/pénz módosítás. Design és eseménykezelők megmaradnak.
Hermes020 az első kidolgozó, Codex integrál/tesztel. Tesztek: meglévő PHP+Chrome
preference fixture,16 kombináció, malformed/duplicate/encoded query, blocked
storage, linknyitás, mobil/fókusz, meglévő return suite és Next fogadók.
Backup: saját tmp/sharity-intl-p2t-backup action-bar + hashmanifest/checksum;
rollback ugyanezen három fájl exact base-re, adatmigráció nélkül. Mentés legfeljebb
2 nap, helyi UI review után eltávolítható; Git előállapot megmarad.
Hash csak az érintett action-bar rekordnál és manifest checksumnál frissülhet;
guardpolicy/inventory változatlan. Kiadáskor aktuális remote backup/CAS és a
fogadó-előbb sorrend kell; e lokális módosítás még nem éles aktiválás.
Kézi UI checklist: profil→DE/EUR→Vásárlási Segéd/Offerwall/NGO-katalógus/
Vállalások/Közösség/Tippjáték; fogadott nyelv/deviza, visszaút, ország és helyi
profilgomb változatlansága. Mobil modal/fókusz, HU-ra visszaváltás, új lap.

### P2t eredmény és ellenőrzés

26 additív sor az action-bar meglévő preference-scriptjében. Hermes020 final
logika átvéve, két helper egy helyi függvényre egyszerűsítve; felesleges korai
vázlatok és hiányos tesztjavaslat elvetve. Új pénz-/API-/auth-/CSS kód nincs.
A korábbi handlerek, sheet és legacy overlay byteazonosak. Egyedi consumeroldali
URL-szerializálás megmaradt; az új href-átadás raw mezőket tart meg.

Tényleges ellenőrzések első futásra sikeresek:
- `USER_CARD_TEST_DEPENDENCIES_ROOT=/Users/bujdosoarnold/.worktrees/ai-agent-sharity-internationalization-20261030/apps/web node --test tests/impactshop-international-preferences.test.mjs`:10 csoport,16 kombináció, strict negatív célok, nyers ismételt query/fragment, blocked storage, natív click és új lap GET.
- A `bash scripts/run-web-vitest.sh src/components/sharity/SharityInternationalPreferences.test.tsx src/components/sharity/SharityQuickDock.test.tsx src/lib/sharity/tippjatek-preferences.test.ts`:85/85. A meglévő dependency-környezetben, nem lockazonos release-buildként.
- N azonos dependency-root mellett `node --test tests/impactshop-user-card-return.test.mjs`:25/25.
- `php tests/impactshop-user-card-profile-return.test.php`:36 completion eset, canonical resolver NOT RUN.
- `python3 tests/impactshop-guard-hash-baseline.test.py`:teljes protected inventory eltérésmentes és6 izolált eset.
- `php -l wp-content/mu-plugins/impactshop-action-bar.php`, `git diff --check`:sikeres.
A böngészőteszt valódi PHP-t és két változatlan overlayt használ; minden hálózat
lokális/tiltott. CSS/markup változatlan, meglévő mobil/fókuszteszt újrafutott.
Teljes éles WordPress/Elementor vagy provider-elfogadás nem történt.

Hermes020 execution4f6e0474-60ce-4e4b-a301-7c6a08fda227 completed/remote_ack;
13 762 input+5 839 output=19 601 token,1 hívás,becsült0,00231504 USD, nem számla.
Codex-költség ismeretlen. Nem volt hibás tesztfutás, retry vagy fallback.
Rollback a base48905f88 action-bar és hash/checksum, migráció nélkül. A saját
háromfájlos ideiglenes backup a helyi review után eltávolítható; Git előállapot
megmarad. Productionre fogadó→küldő sorrendben, friss remote mentéssel/CAS-szal.


## P2u előállapot — NGO runtime copy, 2026-11-01

Base3ba9765a; termékfájlok NGO JS és PHP verziókonstans. Explicit jóváhagyott
nemzetköziesítési scope, meglévő runtime bővítése. Koherencia: enqueue version→
NGO full/compact/widget fetch→render; meglévő managed actionbar lang→saját feliratok.
Kockázatok: provider/user szöveg fordítása, pénz átformázása, URL/attribution drift,
dupla fetch/analytics/listener, késői selector/fetch és hibaképernyő régi nyelve.
Nincs API/schema/reward/vote/adományszorzó vagy route-változás; Impact Challenge
védett forrása nem módosul. Meglévő challenge gomb URL/eseménye megőrzendő.
Hermes021 első draft partial, terminal readback igazolt; használat/díj ismeretlen,
nem nulla. Újraküldés nincs. Szótár/render minta átvétele Codex-javítással;
hiányzó teszteket Codex pótolja. Saját backup tmp/sharity-intl-p2u-backup négy
fájl; rollback NGO JS/PHP és két meglévő hashfájl, adatmigráció nélkül. Max2 nap.
Célzott ellenőrzés: mind3variant/mind4nyelv/HU-visszaállítás, custom text,
összeg/URL és egyszeri analytics, loading/error/late-selector; PHP lint/JSsyntax,
meglévő hashcheck. Kézi UI: NGO full/compact/widget négy nyelv és mobil,
NGO-adat/pénz eredeti, kattintás/megosztás célja változatlan, hiba is fordul.
Teljes live theme/Elementor acceptance külön szükséges. Impact Shop/NGO live
source discovery SSH host-key verification failed; nincs bypass/remote írás.

### P2u helyi eredmény

Mindhárom NGO runtime-változat saját feliratai HU/EN/DE/FR nyelven követik a
meglévő választót. Késői választó, folyamatban lévő kérés és hibaképernyő is
frissül. A renderelt adatok cache-ben maradnak: nincs új fetch vagy card_view,
a kezdeti CTA/attribution megmarad. Provider szöveg és pénzérték változatlan;
hibaüzenet HTML-ként nem hajtódhat végre. PHP csak scriptverziót vált; CSS azonos.
Hermes021 partial draft szótára/render-mintája részben felhasználva. Codex
javította a locale fallbacket, késői változást, hiba újrafordítását, CTA
megőrzését és escapinget; a hiányzó teszteket elkészítette. Egy hívás,
token/költség ismeretlen (nem nulla), retry/fallback nincs.

Tényleges célzott Chrome teszt: `tests/impactshop-ngo-card-locale.test.mjs`,
7/7 csoport sikeres. Első futás 6 teszthiba: fixture UTF-8 jelölés hiánya és
széles címkeselector; egy javítás után sikeres. Termékkódot ehhez nem kellett
javítani. A valós PHP selector és JS lokális fixture-je, tiltott külső hálózat;
négy nyelv, három változat, eredeti pénz/provider/URL, egyszeri analytics,
consent-denied view, late/loading/error/missing állapot. 375px FR teljes kép
megtekintve; eredeti design megtartott. Teljes éles theme/Elementor elfogadás
még nyitott. Független read-only review nem talált lényeges hibát; a gtag
csatorna és consent-denied kattintás külön nem tesztelt, helper változatlan.
JS syntax, PHP lint és diff-check sikeres; meglévő guard-check teljes védett
leltára eltérésmentes, hat izolált hash/checksum esete sikeres.
Rollback: e saját JS/PHP/hash/checksum fájlok Git 3ba9765a állapota;
adatmigráció nincs. Push/merge/telepítés/aktiválás nem történt.

## P3b — display FX cache, 2026-11-01

Új Notes-owned `sharity-support-finance-read.php`, két kockázat: lejárt/hibás
árfolyam mutatása és párhuzamos cron miatti ismételt lekérés. A meglévő pontos
MNB parser/hívó változatlan; pontképlet, pénzügyi ledger és Offerwall bundle nem
érintett. Default OFF refresh, nincs automatikus crontelepítés; public GET csak
ellenőrzött cache-t olvas. Atomi napi option-CAS, legfeljebb3×10s hívás/nap,
hiba is elfogyasztja a napot. Teljes, azonos jegyzésnapú csomag publikálódik;
36h retrieval/7Budapest-nap quote limit, nincs jövő/normalizált hibás dátum.
Eredeti rate/unit sztring, HUFimplicit1. Régi cache hiba esetén változatlan.

Bástya bővítés előtt: csak új fájl inventory és hash, a config önhash és
manifestchecksum változik. Guard/deploy szabály nem változik. Saját három
metadata-fájl mentése tmp/sharity-intl-p3b-backup, max2nap, Git f1cc928a rollback.
Új modul az approved P3 fájltérkép része; nincs hipotetikus új infrastruktúra.
Teszt:150 kiírt assertion izolált PHP fixture-ökben, tényleges MNB parser/caller,
GET/defaultOFF/no-network/hibák/időhatár/egészségescache/onceaday/CASvesztes;
meglévő MNB és money suite sikeres. Valódi párhuzamos DB/cron/staging még nincs.
Kézi kiadási checklist: cachehidegGET→unavailable, explicitrefresh→aktuális3quote,
GET nem indít providert, flagOFF újrefreshstop, meglévő frisscache olvasható,
lejárat→unavailable. UI még nincs bekötve, így designhatás nincs.
Hermes023 completed/remote_ack a7ba9234-0d98-4449-ad24-688ce4206dfa,
9165input+7205output=16370token,1hívás,becsült0,002145325USD. Codex a hibás
claim/date/cachevalidálást és használhatatlan tesztvázat javította. No retry.

Független P3b read-only review nem talált lényeges hibát. A CAS-verseny tesztje
izolált modell, nem éles párhuzamos WordPress/DB bizonyíték. Guardmetadata csak
a fenti két rekordra változik; teljes meglévő hashcheck külön ellenőrizve.

## Publikálási csomag, 2026-11-01

Protected files touched: impactshop-action-bar.php, impactshop-identity-panel.js,
impactshop-ngo-card.php, docs/bastion-guard-status.md, impactshop-guard-config.json,
impactshop-guard-hashes.json és impactshop-guard-hashes.sha256. Az új FX fájl
inventory tag. Arnold kifejezetten jóváhagyott végrehajtási scope-ja és a fenti
csomagonkénti koherencia/teszt/rollback érvényes; nincs új funkcióengedély kérése.
Smoke scope: identity_and_points, profile_identity_surface, pwa_shell,
mobile_shell_runtime, deploy_guard; a pontos tagok a meglévő policyból származnak.
A scope nem állít éles WebKit/Chrome/route/próba PASS-t; a tényleges helyi
ellenőrzéseket csomagonként külön rögzítettük. Éles smoke telepítéskor szükséges.
Rollback: saját célfájlok előállapot-mentése és CAS; nincs pénzügyi adatvisszaírás.

A teljes eredeti fejlesztési ág e79c8188 megőrizve. A saját publikálási ág
feat/sharity-intl-wp-delivery-20261101 két commitba választja az alkalmazáskódot
és protected fájlokat.695ce4ff fájlfája bitazonos e79c8188-cal (git diff exit0).
A lane-audit ezzel sikeres; a protected-audit explicit címszóhiánya e bekezdéssel
javítva. A natív titokscan mind az eredeti84, mind a publikálási22 snapshoton
sikeres. Nincs mainfetch/merge vagy telepítés; a kiadási hullám draft review.
