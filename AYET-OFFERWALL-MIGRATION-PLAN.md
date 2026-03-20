# AyeT Offerwall átalakítási terv (kész implementációból)

## Módosítás indokai
- Több provider (saját kvíz/survey + AyeT + jövőbeli) egységes kezelése ugyanabban a Task Hub UX-ben.
- Egységes **user history** biztosítása minden providerre (teljesítések, jóváírások, visszavonások).
- Stabilabb és skálázható integráció: Website placement + opcionális iframe a jobb external ID egységesítéshez.
- Biztonságos, visszagörgethető változtatás (feature flag + backup + rollback).

## Célok
- A meglévő AyeT callback és jóváírás logika **megmarad**, csak bővül.
- Task Hub provider switcher: saját offerwall alapértelmezett, AyeT opcionális.
- Egységesített history/adatgerinc minden providerre.
- Mobilon stabil működés (iframe helyett link), desktopon iframe opcionálisan.

## Végleges döntések
- **Callback útvonal**: AyeT marad a dedikált `/impact/v1/ayet-callback` endpointon; az általános `/impact/v1/offerwall/callback/{provider}` a belső survey és más providerek számára marad. (Kockázatcsökkentés: nem keverjük a validációs logikákat.)
- **Placement**: Website placement + API adslot kötelező; iframe adslot opcionális (desktop).
- **Mobil**: iframe helyett külső megnyitás (stabilabb tracking).
- **History**: minden provider a közös completions táblába ír, `source_ref` dedup szabállyal.

---

## 0. Kiinduló állapot (kész implementáció)
- AyeT callback: `/impact/v1/ayet-callback` HMAC + IP allowlist + rate limit.
- Azonnali pont/szavazat jóváírás, reversal/decline kezeléssel.
- Ledger bejegyzés pending státusszal.
> **Koherencia állapot:** AyeT dedikált endpointon marad, az általános postback route nem kezeli AyeT-t.

---

## 1. Előzetes biztonsági lépések
1) **DB backup**
   - `impactshop_offerwall_completions`, `impact_ledger`, `wp_user_points`, `impactshop_ads_user_votes`
2) **MU-plugin backup**
   - teljes `/wp-content/mu-plugins` archíválás
3) **Feature flagok** (alapból OFF)
   - `AYET_IFRAME_ENABLED`
   - `TASK_HUB_PROVIDERS`
> **Operációs tanulság:** Deploy esetén a **live** útvonal `/home/sharityh/app/wp-content/mu-plugins` (prod) és `/home/sharityh/app-staging/wp-content/mu-plugins` (staging). A tesztkörnyezetben elvégzett változtatásokat mindig verifikálni kell a stagingen is a live előtt.
> **Megjegyzés:** Backup után javasolt kötelező restore-próba stagingen (mintavételes rekord-ellenőrzéssel), hogy rollback helyzetben ne csak mentés, hanem bizonyított visszaállíthatóság is legyen.
> **Koherencia tanulság:** Belső postbackeknél mindenhol `WP_REST_Request::get_params()` szükséges, különben a `transaction_id` üres maradhat.

---

## 2. Placement stratégia (AyeT dashboard)
- **Website placement** létrehozása.
- Ugyanahhoz a placementhez:
  - API adslot (Offerwall API)
  - Iframe adslot (opcionális)
- **External ID** mindenhol: Sharity pseudo ID
> **Megjegyzés:** Ha iframe és API adslot is ugyanazt a user history-t/profilt kell hogy lássa, akkor elengedhetetlen a konzisztens `external_identifier` használata (ami jelenleg a Sharity pseudo ID). Győződjünk meg róla, hogy az iframe paraméterekben is átadjuk ezt (`&external_identifier=...`), különben az AyeT generálhat saját ID-t, ami széttöri a perszonalizációt.
> **Biztonsági megjegyzés:** Iframe használatnál legyen előre tisztázva `Content-Security-Policy` / `frame-src` és `X-Frame-Options` kompatibilitás, különben rolloutkor környezetspecifikus betöltési hibák jöhetnek.

---

## 3. Task Hub UX (provider switcher)
- Provider gombok:
  - Sharity (kvíz + survey) – **default**
  - AyeT Offerwall (API)
  - AyeT Iframe (opcionális, desktop)
- Mobilon iframe helyett **külső link** (stabilabb tracking/ITP miatt).
> **UX/koherencia megjegyzés:** Érdemes rögzíteni a fallback szabályt is: ha az AyeT provider disabled/unhealthy, automatikus visszaállás Sharity providerre történjen, és jelenjen meg felhasználói tájékoztató.
> **Biztonsági megjegyzés:** Mobil külső link megnyitásnál legyen `target="_blank"` + `rel="noopener noreferrer"`, hogy ne maradjon nyitott `window.opener` támadási felület.
> **Döntés:** A fallback Sharity providerre kötelező; a gombok sorrendje rögzített (Sharity → AyeT API → AyeT iframe).

---

## 4. Egységes user history
- Közös „completion history” táblába ír minden provider:
  - `provider`, `source_ref`, `pseudo_id`, `offer_id`, `offer_name`, `status`, `awarded_at`
- Dedup: `source_ref` **UNIQUE**
- Saját kvíz/survey is ugyanoda ír.
> **Megjegyzés:** Az `impactshop_offerwall_completions` tábla structure már létezik az `impactshop-offerwall.php`-ban, de a jelenlegi `impactshop-ayet-offerwall.php` saját táblát használhat vagy közvetlen logikát. Migrációkor adatmozgatás szükséges a régi AyeT táblából az új közös táblába, ha van régi data amit meg akarunk tartani. A `source_ref` legyen `provider_transaction_id` formátumú composite key a teljes egyediséghez.
> **Koherencia megjegyzés:** A státusz-életciklust érdemes explicit szabályozni (`pending -> approved -> reversed|declined`), és tiltani az érvénytelen átmeneteket (pl. `reversed -> approved`). Ez megkönnyíti az auditot és a későbbi automata konzisztencia ellenőrzést.
> **Adatminőségi megjegyzés:** Jelentésekhez ajánlott plusz index: `(pseudo_id, created_at)` és `(provider, created_at)`, hogy a history/listázás és provider dashboard queryk nagy adathalmaznál is stabilak maradjanak.

---

## 5. Callback és biztonság
- AyeT callback logika változatlan:
  - HMAC (RFC3986), IP allowlist, rate limit, silent fail
- Reversal/decline:
  - pont/szavazat visszavonás
  - targeted üzenet pseudo ID-ra
> **Biztonsági döntés:** HMAC ellenőrzés `hash_equals`-szal kötelező; a callback válasz után `exit`/`wp_die` alkalmazása ajánlott, ha kimenet keveredne (jelenleg opcionális, nincs blokkoló incidens).
> **Biztonsági megjegyzés:** Replay védelemhez javasolt időbélyeg alapú freshness check (ha provider támogatja), vagy legalább szigorú dedupe-szabály tranzakció + provider kulcson túl request fingerprint naplózással.
> **Biztonsági megjegyzés:** A headerből vett kliens IP (`x-forwarded-for`, `x-real-ip`) csak megbízható reverse proxy forrás esetén tekinthető hitelesnek. A tervben érdemes rögzíteni a trusted proxy policy-t, hogy ne legyen spoofolható IP allowlist döntés.
> **Koherencia megjegyzés:** Az általános `impactshop_offerwall_signature_valid()` jelenleg fail-open viselkedést engedhet (`secret` vagy `signature` hiányában `true`). A migráció során érdemes minimum AyeT-re fail-closed szabályt rögzíteni.
> **Secret-kezelési megjegyzés:** Provider secretek admin optionben tárolódhatnak; javasolt környezeti változóra/konstansra migrálás vagy legalább maszkolt megjelenítés + rotációs eljárás dokumentálása.
> **Koherencia megjegyzés:** Belső REST hívásoknál paraméterolvasásra érdemes következetesen `WP_REST_Request::get_params()`-t használni, mert eltérő források (`query/body/json`) külön kezelése transaction azonosító eltérést okozhat.
> **Adatvédelmi megjegyzés:** Naplózásnál javasolt a pseudo és IP mezők maszkolása/trunkálása a normál logokban, és csak incidenskezelési szinten teljes érték tárolása.

---

## 6. Rollout stratégia
1) **Stage 1 (dark launch)**
   - Provider switcher + history egységesítés
   - AyeT iframe OFF
2) **Stage 2**
   - Iframe desktopon 5–10% user (feature flag)
3) **Stage 3**
   - teljes rollout + monitoring
> **Rollout megjegyzés:** Érdemes hozzáadni objektív go/no-go kritériumokat stage-váltáshoz (pl. invalid signature < 0.5%, duplicate ratio stabil, reversal arány nem romlik, callback latency küszöb alatt).
> **Megjegyzés:** A canary rolloutnál legyen determinisztikus mintavétel (pl. pseudo hash alapú), hogy ugyanaz a user ne ugráljon stage-ek között.

---

## 7. Monitoring / ellenőrzés
- AyeT postback logok: ok / invalid / missing params
- Completions vs rewards konzisztencia
- Task Hub UI smoke (desktop + mobile)
> **Monitoring megjegyzés:** Javasolt külön KPI-k: `duplicate_rate`, `reversal_rate`, `hmac_mismatch_count`, `ip_blocked_count`, `missing_pseudo_count`, `provider_disabled_hits`. Ezek segítik a regressziók korai észlelését.
> **Operációs megjegyzés:** Legyen napi automata reconciliation report a completions ↔ point_transactions ↔ votes ↔ ledger között, eltérés esetén riasztással.
> **Megjegyzés:** Érdemes külön dashboardot fenntartani a két callback útvonalra (legacy AyeT vs unified provider callback), amíg a végleges útvonal lezárása meg nem történik.

---

## 8. Rollback terv
- Feature flag OFF → visszaáll a jelenlegi állapot
- DB + MU-plugin backup visszatöltése
> **Rollback megjegyzés:** Schema-változás esetén legyen előre definiált visszafelé kompatibilitási lépés (olvasási fallback), hogy rollback alatt se legyen history/API kiesés.

---

## 9. Nyitott döntési pontok
- Iframe legyen-e mindig elérhető desktopon, vagy csak opcionális gomb mögött?
- API-only mód elegendő-e mobilon (javasolt), vagy legyen ott is iframe?
> **Döntési megjegyzés:** AyeT callback dedikált marad; a közös provider callback a többi szolgáltatóhoz.

---

## 10. Végrehajtási lépések (checklist)
1) Backup (DB + mu-plugins) + staging restore-próba.
2) Feature flagok felvétele/ellenőrzése.
3) Provider switcher és history logika ellenőrzése (default Sharity).
4) AyeT callback validáció + logok ellenőrzése.
5) Mobil fallback link + desktop iframe opció tesztje.
6) Monitoring KPI-k beállítása + riasztás.
7) Guardos deploy (prod + staging).
