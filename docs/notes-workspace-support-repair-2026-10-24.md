# Notes stabil support — szűk repair plan

Base: `7417e18bad21a31a781917003af7b8ea61335838`; saját ág:
`dev/retirement-notes-support-20261024`. Engedély: a feltárt kompatibilitási
hiány helyi forrásjavítása review-ra; nem publikálás vagy aktiválás.

## Pontos fájlkör (13)

1. scripts/workspace-hooks.py — source-owned stable fallback, explicit trusted tool-root.
2. scripts/notes-workspace-support.py — inaktív, verziózott Notes-csomag prepare/build; nem current-aktiváló.
3. scripts/notes-product-check.py — kizárólag az Impi változatlan checker explicit célrepo inputadaptere.
4. tests/notes-workspace-support.test.py — izolált valódi parancs/negatív fixture.
5. .github/workflows/ci.yml — a következő engedélyezett CI-ben az új célzott suite is fusson.
6. docs/notes-workspace-support-repair-2026-10-24.md — ez a terv és valós eredmények.
7. docs/protected-change-records/2026-10-24-notes-workspace-support.md — saját protected rekord.
8. notes.md
9. system-status-snapshot.md
10. DOC-SYNC-HUB.md
11. docs/bastion-guard-status.md
12. docs/impactshop-governance-system-plan-2026-06-16.md
13. docs/impactshop-notes-doc-sync-map-2026-06-23.md

## Diagnózis és döntés

Air 37 + Mini 26 régi WT-ben hiányzik a helper. Egyikben sincs új staged-only
protected ág vagy foreign-WIP-advisory lane-kezelés. Mini affiliate: 19/26,
Impi: 10/26. A puszta helpercopy nem elegendő. Az Impi a saját __file__
útvonalából képezte a vizsgált repót, ezért csomagból explicit inputadapter kell.

A Notes csomag a helperből, a négy változatlan review-zott product scriptből
és az explicit Impi-adapterből áll. A builder csak megadott inaktív outputba
írhat; current symlinket, hostkonfigurációt vagy hookot nem állít át.
A fallback fix Notes-specifikus target, nem központi üres PRODUCT_GUARDS.

## Sorrend és elfogadás

1. Terv/DOCSYNC előbb; explicit tool-root owner/containment/integrity ellenőrzés.
2. Source-owned fallback; helyi helper esetén default viselkedés változatlan.
3. Support checker kód a csomagból, minden adat a tényleges cél-WT-ből.
   Két shellguard index/staged kezelése a már review-zott #224-verzió.
   Affiliate inputok és Impi ROOT/PLUGIN/POLICY kizárólag célrepo-alapúak.
4. Célzott pozitív/negatív teszt: secret staged/outgoing, protected permit hiány,
   idegen WIP/index változatlanság, affiliate/Impi tamper, hibás/adat-root eltérés,
   tool-root symlink/owner/manifest drift, default működés.
5. Formátum/syntax/érintett regresszió és öt tengelyű selfreview; source review-ra átadás.
   Valódi hosted CI majd külön engedéllyel, billingot vagy checket nem kerülünk meg.

## Határok, kockázatok és rollback

Nincs termékpayload-, guardlogika-, foreign WT-forrás- vagy central módosítás.
Sem production/staging, sem provider, bridge vagy host apply nem része.
Hamis source/product PASS és rossz adatgyökér a fő kockázat; tényleges command,
repo és checker-root szerepeljen a readbackben. Hiányzó eszköz nem permit.

Forrásrollback: saját delta célzott visszaállítása az exact #224 preimage-re,
idegen WIP megőrzésével. Jövőbeli csomag/current/hook váltás csak külön
backup/CAS/readback, változatlan index/WIP és saját rollback alapján.
UI checklist: N/A, termék/UI forrás nem változik.

## Implementáció és helyi bizonyíték

A builder `prepare --source <saját clone>` írásmentes hash/HEAD/tree/dirty
leltár. A `build --source <saját clone> --output <explicit releases/VERSION>
--expect <prepared VERSION>` csak új inaktív, privát könyvtárat hoz létre;
létezőt/currentet nem ír felül, nem hoz létre automatikus home-targetet.
Félbeszakadt export manifest nélkül inaktív marad. Current-váltás/hookapply
nem része ennek a javításnak; hostlépés előtt új backup/CAS terv szükséges.

A renderer csak a fix Notes `~/.local/share/office-workspace/targets/
impactshop-notes/current` selectorát követheti, saját forrásból számolt
content-version pin mellett. A kiválasztott csomag owner/útvonal/manifest/
hash ellenőrzése a csomagkód futása előtt történik, izolált Pythonban.
Az explicit `--tool-root` és `--tool-version` együtt szükséges a stabil
checkerhez; nem általános DEV-engedély vagy új engine/selector gate.
Hiány/malformed/nonobject manifest/ciklus/idegen csomag unavailable, natív
Git továbbmehet; nem jelenik meg sikeres audit. Konkrét natív productparancs
látható az advisoryban. Valódi secret vagy productguard finding továbbra 1.

CheckerRoot a verziózott Notes release; cwd/dataRoot az aktuális cél-WT.
Manifest/record/affiliate/Impi input nem lehet idegen vagy symlinkelt; staged
és push módban az érintett input index-/HEAD-egyezése is ellenőrzött.
Az unrelated foreign WIP nem globális tisztasági blokk. A helyi helperes
default működés nem vált át automatikusan csomagellenőrzésre.

- `python3 -B tests/notes-workspace-support.test.py`: 22/22, 17.209 s.
  Valós parancs: régi WT helper nélkül, renderelt staged/push secretfinding;
  protected deny és saját record; foreign WIP/index sértetlen; affiliate
  tamper az env-redirect ellenére failed; Impi target policy tamper failed;
  manifest/record/data-root drift unavailable; package symlink/hardlink/mode/
  owner/version/kind, malformed/list/null/string és selectorciklus kezelve.
  A bootstrap nem importálja a cél-WT `json.py` fájlját.
- `python3 -B tests/workspace-retirement.test.py`: 34 eset PASS, egy no-PASS
  szövegassertion sikertelen volt a „not PASS” advisory szóhasználat miatt.
  Csak a szöveg javítva „no successful evidence”-re, a régi teszt érintetlen.
  Az egyetlen eset végső célzott ismétlése 1/1 PASS, 0.782 s. A többi 34-et nem
  futtattuk újra, mert a javítás nem érinti őket.
- Python AST parse: 4 érintett `.py` OK; CI YAML Psych parse OK;
  `git diff --check` OK. A négy eredeti termékchecker `git diff --exit-code
  HEAD -- <négy script>` szerint byte-változatlan.
- Az öt változatlan #224 deploy/rollback mock eredménye újrafelhasznált,
  nem újrafuttatott. Nincs staging, UI-, host- vagy production-PASS.
- Saját `audit --strict --mode local --repo <saját clone>`: 13 változott
  fájl/snapshot, 0 finding; tényleges protected-touch és commit-lane exit0.
  Saját fenti rekord, explicit BASTION_OVERRIDE/rollback/smoke scope mellett;
  a tagok nem jelentenek staging/deploy-futást. productPermission not-evaluated.
  Read-only prepare content-version:
  `3ddfad1ace48e09209606ce5ba7e60e3cba6ca99f91b1bfd279d1f3897509f0a`;
  sourceHead a fenti base, sourceDirty true, activated/authorityGranted false.

Hibaledger: egy fixture whitespace-negatív kontrollja az input-drift
unavailable előtt joggal failedet adott; csak a fixture JSON-formázása javult.
A no-PASS szóhasználati eltérés külön hiba volt, javítva. Review-finding:
manifest `.get` előtti object type check + kizárólag selector resolve körül
RuntimeError→unavailable fordítás; új valódi hook-regresszió igazolja.

## Független main-review — nem önreview

A main agent a tényleges diffet függetlenül olvasva két konkrét leletet adott:

1. Nem-object JSON manifest `.get` hívásából AttributeError, régebbi Pythonon
   current-symlinkciklusból RuntimeError lehetett: az opcionális metadatahiba
   tévesen hard-blokkolt volna. Explicit object-check és kizárólag selector
   resolve körüli RuntimeError→ValueError fordítás került be. Valódi renderelt
   hook: malformed/list/null/string és ciklus mind native0/advisory/no evidence,
   Traceback és sikeres audit nélkül.
2. A szülő `-I` nem izolálta a shellguardok belső `python3 -` hívásait.
   Célzott reprodukcióban markeríró target `json.py` + `PYTHONPATH=target`
   kétszer végrehajtódott a valódi protected/lane checkben (exit1). Az Impi
   már `-I -B` alatt futott: két valós próba exit0, marker nélkül.
   A jóváhagyott javítás kizárólag tool_root gyermekfuttatáskor privát
   TemporaryDirectory0700 + owner-only `python3` shim:
   `exec <shlex.quote(sys.executable)> -I -B "$@"`.
   Saját envmásolatból PYTHONPATH/PYTHONHOME törölve, csak gyermek-PATHprefix;
   cwd továbbra explicit target. Nincs hostconfig/cache vagy guardbyte-módosítás.
   Az új végső fixture markeríró targetmodullal, PYTHONPATH/PYTHONHOME mellett
   tényleges protected permit + lane exit0-t igazol, marker nélkül; WIP/index
   változatlan és productPermission továbbra not-evaluated.

A 22-es suite és az érintett régi advisoryeset egyszer futott a végleges
shimkódon. A review leleteit a main agent adta; az alábbi öt tengely saját
selfreview, nem helyettesítő független review vagy hosted CI bizonyíték.

## Öt tengelyű selfreview és nyitott határ

1. Cél/jogosultság: pontosan 13 saját forrás/DOCSYNC fájl, exact #224 alap;
   nincs foreign WT-, központ-, telepített csomag- vagy providerírás.
2. Viselkedés: default local megmaradt; régi helper nélküli WT ténylegesen
   vizsgált, opcionális metadatahiba advisory, valódi finding továbbra deny.
3. Biztonság: kiválasztott kód explicit trust-bound, adatok/manifest/record
   a cél-WT-ből; secret- és terméklogika változatlan, nincs jogot adó PASS.
4. Kompatibilitás: stdlib csomagolás, négy változatlan reviewed script,
   explicit Impi inputadapter; PHP-hiány unavailable, nem helyettesítő PASS.
5. Evidencia/státusz: helyi source-review jelölt, nem publikált/merged/
   telepített/aktivált. A 63 valós régi WT hatékony auditja továbbra nincs
   aktiválva; ehhez később reviewed release+current+hook metadata rollout kell.

Független main-review lezárva: a végleges shim, a tényleges hívóág,
validator, csomagoló, inputadapter és negatív tesztek visszaolvasva. A két
feltárt hiba javítását az egyszer lefuttatott végleges regresszió igazolja;
új blokkoló forráslelet nem maradt. Ez nem host- vagy hosted-CI-elfogadás.
Nyitott: publikálás és a merge-elt csomag külön hostaktiválása, későbbi
hosted CI állapot-readback. Billinget most nem kezelünk, nem futtatunk CI-t.

## Commit-előkészítés

A teljes 13 fájlos staged scan: titokellenőrzés 13 snapshot, 0 finding;
protected-touch exit0. A commit-lane exit2 oka az ops és protected csoport
együttes stagingje, nem termékhiba. Javítási döntés: két natív commit,
előbb a kód/teszt és kísérő dokumentáció, majd a két protected felület
(`.github/workflows/ci.yml`, `docs/bastion-guard-status.md`). A teljes forrásfa
nem változik ettől; a sikertelen összesített vizsgálatot nem nevezzük PASS-nak.
A klónban csak Git mintahookok vannak, nem állítunk futott natív hookot;
a tényleges tartalom- és termékellenőrzéseket közvetlenül futtatjuk.
