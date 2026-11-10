# Profil közös szavazategyenlege — protected change record

Arnold 2026-11-10-én jóváhagyta a fájlszintű terv megvalósítását. Saját
`fix/sharity-canonical-balance-read-20261110`, alap `75647555`; donor read-only.
Ez a jóváhagyás a meglévő Profil-adapter szűk legacy touchját tartalmazza.
Új párhuzamos kijelző meghagyná a régi félrevezető általános egyenleget,
ezért a meglévő olvasó és renderer módosítása a kisebb koherens megoldás.

## Protected files touched

- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-identity-panel.js`
- A két saját forrás helyi `docs/impactshop-guard-hashes.json` digestje és checksumja.

## Koherencia, érintettség és kockázat

Profil full/compact SSR és `/impact/v1/identity/profile` ugyanazt az új
`ImpactShop_Sharity_Web_Session::read_shared_wallet_for_pseudo()` metódust hívja,
mint a Szívügyeim/Vállalások összesítője. A hívó saját meglévő aktív identity
ellenőrzése változatlan. A válasz `votes_available` int|null; hiányzó reader
vagy hibás adat null, nincs ads-wallet fallback. JS csak tényleges nemnegatív
safe integer értéket jelenít meg. Az aktív profil wallet-hiba alatt is aktív;
becenév/kód/restore jogosultságát a számláló nem módosítja.

Közvetlen kockázat: vegyes PHP/JS-kiadás, hiányzó függőség, null->0 konverzió.
JS előbb telepíthető, régi számos API-val kompatibilis. A reader, majd Profil-PHP
következik. Újratöltésig régi JS maradhat a megnyitott lapokon; elfogadás friss
lapbetöltéssel kell. A class betöltési sorrendje az MU betöltés után futó SSR/REST
belépőknél érvényes; függőséghiány látható hiányállapot.

Pont/wallet/ads writer, owner-cookie, grant, nonce, profilreturn, jelszókezelő
form, kampány, pénz, média, cron és régi üzenetek változatlanok. Nincs gazdasági
írás vagy új végpont. Az53/163 számpár mögötti személyes tranzakcióegyeztetés
függő; a javítás nem enged átvezetést vagy automatikus produceraktiválást.

## Tényleges helyi ellenőrzés és smoke scope

- PHP lint, checkpoint-A statikus szerződés és új adapterteszt sikeres.
- `SHARITY_WEB_SESSION_SOURCE=<saját ai-agent reader>` alatt a két repo valós
  PHP-ja együtt fut szintetikus SELECT-only DB-n:53, hiányzó sor0, DB-hiba/null,
  invariánshiba/null. Idegen profil vagy SQL-forrás, illetve írás kivételt dob.
- Teljes aktuális JS17 új böngészőesete sikeres:0/53/maxsafe, null/hiányzó/
  logikai/szöveges/negatív/tört/túl nagy szám, profilállapot és profilizoláció.
  Minden HTTP mockolt, szolgáltatókhoz nincs kérés.
- Aktuális PHP code-save teszt és9 aktuális Chrome code-save/restore eset sikeres:
  nyers return, egyszeri natív form, v2 szerződés, nincs véletlen pontírás.
- Régi `impactshop-user-card-return.test.mjs`48/50: a két account-save fixture
  nem szolgáltatja az új codeSaveContract2 formmezőt/konfigurációt; az aktuális
  kód a védelmi üzenetnél áll meg. Nem teljes PASS, a legacy fixture nincs átírva.
- Smoke tag scope: route:factlens-vb-prod, route:impact-challenge, route:profil,
  flow:message-popup, flow:points-jump, flow:legacy-pool-visibility,
  flow:profile-return-account, flow:profile-return-restore. A tagsor érintettség,
  nem minden route új, éles elfogadásának állítása. Éles hitelesített olvasópróba függő.

Hermes egyszeri első JS/teszt draftja részben hasznos, de hibás patchkörnyezetet,
Node-oldali window/document hivatkozást és hiányos state-text választást adott.
Codex a tényleges forrásra illesztette és tesztelte; nem indult újrahívás.
Becsült díj0.000735553USD, nem számla. Célzott független diff-review kész:
a közös reader másik, community hívójának hibája javítva és külön dual-auth
tesztelve; végső újraellenőrzéskor nincs nyitott új lelet. Reviewer saját PHP
source/adapter/valódi cross-repo/checkpoint-A tesztje sikeres, böngészőt nem ismételt.

## Telepítés és rollback

Friss élő hash/mód/uid/gid/parentmode/tárhely/cache és backup-readback kell.
Meglévő exact-file prepare/payload/lint/apply/inspect, privát külső mentés
0700/0600, kifejezetten >=7nap megőrzés. Motor0444 mód és tulajdonos/csoport
megőrzése ellenőrzendő. Nincs broad rsync, Vercel-build vagy DB-migráció.
Kiadás: JS -> közös reader -> Profil-PHP; hash és HTTP/SAPI-readback mindenhol.
Rollback: Profil-PHP -> szükség esetén közös reader; a kompatibilis JS maradhat.
Csak saját változatlan telepített hashből; idegen driftnél nincs felülírás.
Az egész Profil-pár/source/return lane mentése és account/save+restore próbája
megmarad. DB-visszatöltés nem kell, mert a csomag nem módosít egyenleget.
VB2026 SESSION_SHA automatikus repin tilos: a forráskészlet egyezése producert
is aktiválhat. Az aktív függőség ellenőrzése a reader éles cseréjének előfeltétele.

## Kézi UI checklist és állapot

Frissen betöltött Profil full/compact, Szívügyeim és közös szavazófelület
azonos, szerveresen igazolt profilnál ugyanazt a rendelkezésre álló összeget
mutatja. Közben történt jóváírás/költés esetén új olvasás kell. Pont aktuális
és lifetime jelentése nem keveredik; profilváltás/restore/save működik. Hibánál
elérhetetlenség látszik, nem nulla. Éles pontot/pénzt/szavazatot nem írunk tesztként.

Helyi forrás, tesztek és célzott független review elkészültek; PR/merge/telepítés/aktiválás/élő elfogadás
még nincs. Személyes ledger-egyeztetés hiányában nincs végleges számbeli diagnózis.
