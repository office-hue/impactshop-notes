# VB2026 Sharity NGO Catalog — Phase 1 Implementation Pack (2026-06-23)

Státusz: implementációs csomag  
Scope: az I. ütem tényleges fejlesztői kivitelezési csomagja a két érintett repo között.  
Kanonikus tervforrás: `docs/VB2026-SHARITY-NGO-CATALOG-AND-SELECTION-PLAN-2026-06-23.md`

---

## 1. Cél

Az I. ütem célja, hogy a VB2026-hoz kapcsolódó NGO-választási alaprendszer ténylegesen elkészüljön úgy, hogy:

1. a teljes NGO-katalógus a Sharity / Impact Shop oldalon él,
2. a user ott tudjon NGO-t választani,
3. a `vb-prod` csak kompakt fogyasztója legyen ennek az állapotnak,
4. ne alakuljon ki kettős truth a két repo között,
5. az I. ütem még ne keveredjen bele a widget-generáló és adomány-routing Phase 2 szeletbe.

---

## 2. Két repo, két felelősség

## 2.1. Repo A — Impact Shop / Sharity source oldal

Repo-szerep:

1. NGO-katalógus source oldali truth
2. NGO-kártyák és Sharity oldali katalógus UI
3. user NGO-választás write lane
4. pre-auth selection intent lane
5. featured Top 10 kampányréteg

Itt készül el:

1. adatmodell
2. ingest
3. publikus read endpointok
4. autholt write endpointok
5. Sharity oldali katalógusnézet

## 2.2. Repo B — `ai-agent` / VB2026 target oldal

Repo-szerep:

1. `vb-prod` kompakt NGO-bridge
2. Sharity kiválasztott NGO állapot fogyasztása
3. `vb-prod` CTA-k átvezetése a Sharity source oldali katalógusra
4. target oldali payload és render igazítása

Itt készül el:

1. `game/profile` vagy kapcsolódó payload NGO-bridge bővítése
2. `vb-prod` kompakt NGO-blokk
3. route és CTA kötés a Sharity katalógusra

---

## 3. I. ütem scope-határ

## 3.1. Benne van

1. kanonikus NGO slug átvétele a meglévő Impact Shop / NGO Card lane-ből
2. media-truth normalizálás logó / cover szintjén
3. Sharity oldali NGO-katalógus read modell
4. featured Top 10 kampányréteg
5. user NGO-választás
6. pre-auth selection intent
7. auditálható selection write log
8. `vb-prod` kompakt NGO-bridge

## 3.2. Nincs benne

1. widgetkód-generálás
2. `Adományozok` VB2026-specifikus Impact Amplifier ág
3. NGO share widget-finomítás
4. Cégjelző enrichment
5. részletes NGO versenyoldal
6. teljes donor- és szavazati ledger integráció

---

## 4. Fázislogika az I. ütemen belül

Az I. ütemen belül a kivitelezést 6 technikai szeletre kell bontani.

1. Source truth és storage
2. Ingest és publish-safe read
3. Public NGO-katalógus endpointok
4. Autholt NGO-választási lane
5. Sharity oldali NGO-katalógus UI
6. `vb-prod` kompakt bridge

---

## 5. Source truth és storage — Repo A

## 5.1. Kötelező táblák

Elkészítendő:

1. `wp_sharity_ngo_catalog`
2. `wp_sharity_ngo_campaign_flags`
3. `wp_vb2026_user_ngo_selection`
4. `wp_vb2026_selection_intents`
5. `wp_vb2026_ngo_selection_audit_log`

Opcionális, de erősen ajánlott:

1. `wp_vb2026_ngo_catalog_snapshots`

## 5.2. Kötelező source döntések

Le kell zárni implementáció előtt:

1. honnan jön a kanonikus NGO slug a meglévő Impact Shop / NGO Card lane-ben
2. mi a kanonikus logóforrás
3. mi a kanonikus cover image forrás
4. mi a featured Top 10 kezdeti seed listája

Hard blocker szabály:

1. amíg a fenti 4 döntésből bármelyik nincs tényleges source truthként kijelölve, az implementáció nem mehet túl a scaffolding és migration előkészítés szintjén
2. különösen:
   - nincs saját, új slug-logika
   - nincs ad hoc Top 10 lista
   - nincs közvetlen nyers CSV-media render

## 5.3. Storage acceptance

Elfogadási feltétel:

1. a slug nem a CSV-ből jön
2. a media lane fallbackkel működik
3. selection write log külön táblába íródik
4. selection intent hash-elt tokennel tárolódik

---

## 6. Ingest és publish-safe read — Repo A

## 6.1. Ingest feladat

Meg kell építeni egy olyan source ingestet, amely:

1. letölti a NGO exportot
2. parse-olja
3. public-safe mezőkre transzformál
4. összeköti a kanonikus slug- és media truth réteggel
5. upserteli a `wp_sharity_ngo_catalog` táblát

## 6.2. Publish-safe gate

Kötelező védelmek:

1. `source_row_hash` alapján ne legyen felesleges rewrite
2. hibás vagy üres ingest esetén ne nullázódjon a publikus lista
3. gyanúsan alacsony aktív NGO-szám esetén fail-closed last-good viselkedés legyen

## 6.3. Read model

A publikus read lane ne a nyers táblából szolgáljon ki közvetlenül bármit.

Kell:

1. public-safe view model
2. featured read model
3. user selection summary model

---

## 7. Public endpointok — Repo A

## 7.1. Kötelező publikus read endpointok

1. `GET /wp-json/impact/v1/ngo-catalog`
2. `GET /wp-json/impact/v1/vb2026/featured-ngos`

## 7.2. Kötelező autholt endpointok

1. `GET /wp-json/impact/v1/vb2026/my-ngo-selection`
2. `POST /wp-json/impact/v1/vb2026/select-ngo`
3. `POST /wp-json/impact/v1/vb2026/selection-intent`
4. `POST /wp-json/impact/v1/vb2026/selection-intent/complete`

## 7.3. Endpoint acceptance

1. `ngo-catalog` alapból `active_only=1`
2. `featured-ngos` csak aktív, publikusan listázható, featured és aktív campaign-state rekordot adhat
3. `select-ngo` csak autholt, same-origin vagy nonce-védett write lehet
4. `selection-intent` raw tokent csak kliensnek ad vissza, DB-ben hash marad
5. `my-ngo-selection` explicit tudja a három minimális állapotot:
   - `has_selection=false`
   - `has_selection=true`
   - `needs_attention=true`

---

## 8. Sharity oldali UI — Repo A

## 8.1. Első körös route

I. ütemben kötelező:

1. `GET /szervezetek/`

## 8.2. Első körös UI blokkok

1. hero blokk
2. kiemelt Top 10 blokk
3. saját NGO csík, ha van
4. kereső
5. kategóriaszűrő
6. városszűrő
7. aktív-only lista
8. NGO-kártyák

## 8.3. Kötelező kártya CTA-k I. ütemben

1. `Támogatom ezt az ügyet`
2. `Részletek`
3. `Megosztás`

Megosztás route:

1. `https://app.sharity.hu/ngo/{slug}/share/`

## 8.4. Amit még ne építsünk ide

1. HTML-kód generálása
2. widget configurator
3. VB2026 adományozási ág

---

## 9. `vb-prod` bridge — Repo B

## 9.1. Kötelező cél

A `vb-prod` oldalon ne jelenjen meg teljes NGO-katalógus.

Csak egy kompakt bridge jelenjen meg:

1. van-e kapcsolt profil
2. van-e kiválasztott NGO
3. a kiválasztott NGO neve
4. featured-e
5. aktív-e
6. CTA-k a source oldalra

## 9.2. Kötelező payload mezők

1. `has_connected_profile`
2. `selected_ngo_summary`
3. `selected_ngo_is_featured`
4. `selected_ngo_is_active`
5. `selection_urls.select`
6. `selection_urls.manage`
7. `has_selection`
8. `needs_attention`
9. `attention_message`

URL-truth szabály:

1. Repo B nem építhet saját NGO URL-eket string-összerakással
2. a cél-URL-ek kanonikus forrása Repo A payloadja
3. a target oldal csak a source oldaltól kapott `selection_urls` és kapcsolódó URL-eket használhatja

## 9.3. `vb-prod` UI acceptance

1. NGO-katalógus nem renderelődik target oldalon
2. nincs külön target-side NGO truth
3. minden NGO-művelet visszavisz a Sharity source oldalra

---

## 10. Fájlszintű érintettség — Repo A

Ez a lista tervezési csomag, pontos fájlnév implementációkor igazítható a valós struktúrához.

## 10.1. Backend / WP source lane

Várható érintett területek:

1. `wp-content/mu-plugins/` alatti új vagy bővített source plugin
2. NGO-card / canonical slug lane-hez kapcsolódó meglévő source fájlok
3. REST route regisztráció
4. ingest / sync script
5. opcionális admin seed script

## 10.2. Frontend / source UI lane

Várható érintett területek:

1. `/szervezetek/` route template vagy render layer
2. katalógus-kártya komponens
3. kereső/szűrő komponensek
4. saját NGO csík

---

## 11. Fájlszintű érintettség — Repo B

Várható érintett területek:

1. `apps/api-gateway/src/services/` alatti VB2026 game/profile service
2. `apps/api-gateway/src/routes/` vagy kapcsolódó public API layer
3. `apps/api-gateway/src/static-assets/vb-prod/index.html`
4. szükség esetén target oldali render helper JS

---

## 12. Fejlesztési sorrend

Az ajánlott kanonikus sorrend:

1. slug- és media-truth forrás lezárása Repo A oldalon
2. storage migrationok Repo A oldalon
3. ingest és public-safe read lane Repo A oldalon
4. public endpointok Repo A oldalon
5. autholt selection lane Repo A oldalon
6. Sharity katalógus UI Repo A oldalon
7. `vb-prod` NGO-bridge payload Repo B oldalon
8. `vb-prod` kompakt render Repo B oldalon
9. végső cross-repo smoke

Köztes gate:

1. Repo B implementáció nem indulhat el érdemben addig, amíg Repo A payload contractjai nem stabilak legalább lokális/staging smoke szinten

---

## 13. Cross-repo contract

## 13.1. Repo A → Repo B

Repo A adja:

1. kanonikus slug
2. kanonikus NGO-azonosító
3. selection truth
4. featured truth
5. share route

Repo B fogyasztja:

1. kiválasztott NGO summary
2. source oldali CTA URL-ek
3. aktív / featured állapot

## 13.2. Tiltott cross-repo drift

Nem megengedett:

1. Repo B-ben külön NGO lista építése
2. Repo B-ben külön NGO-választás tárolása
3. Repo B-ben saját slug-logika
4. Repo B-ben saját featured truth

---

## 14. Acceptance checklist — I. ütem

Az I. ütem akkor tekinthető késznek, ha:

1. Sharity oldalon megnyitható a publikus NGO-katalógus
2. Top 10 blokk látszik
3. keresés működik
4. aktív NGO kiválasztható
5. login előtti intent nem vész el
6. visszatérés után a kiválasztás befejeződik
7. `my-ngo-selection` visszaadja a választott NGO-t
8. `vb-prod` ugyanazt a kiválasztott NGO-t mutatja
9. `Megosztás` az adott NGO `/ngo/{slug}/share/` nézetére visz
10. nincs target-oldali duplikált truth
11. `vb-prod` a source oldali `selection_urls` alapján navigál, nem saját URL-összerakással
12. a `my-ngo-selection` `has_selection=false` állapota is kulturáltan renderelődik
13. a `needs_attention=true` invalidált állapot is kulturáltan renderelődik
14. a cross-domain identity/session lane ugyanahhoz a pseudo truthhoz kötődik source és target oldalon

---

## 15. I. ütem utáni közvetlen következő csomag

Az I. ütem lezárása után jöhet:

1. widgetkód-generálás
2. NGO embed-flow
3. `Adományozok` Impact Amplifier ág
4. VB2026 adomány- és szavazati ledger összekötés

---

## 16. Doc sync szabály

Ha az I. ütem implementáció elindul vagy módosul, kötelező frissíteni:

Repo A oldalon:

1. `docs/VB2026-SHARITY-NGO-CATALOG-AND-SELECTION-PLAN-2026-06-23.md`
2. ezt a fájlt
3. `docs/ngo-guides/README.md`
4. `notes.md`
5. `system-status-snapshot.md`

Repo B oldalon:

1. `docs/VB2026-DOCUMENTATION-HUB-2026-06-11.md`
2. `docs/VB2026-TIPP-ELEMZO-IMPLEMENTATION-PLAN-2026-06-11.md`
3. `notes.md`
4. `system-status-snapshot.md`

---

## 17. Megvalósulási állapot — 2026-06-23

Az I. ütem implementációja elindult, és a jelenlegi worktree-ben már futóképes source-oldali alapréteg áll.

Elkészült source oldalon:

1. új MU-plugin:
   - `wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php`
2. migrációs scaffold:
   - `wp_sharity_ngo_catalog`
   - `wp_sharity_ngo_campaign_flags`
   - `wp_vb2026_user_ngo_selection`
   - `wp_vb2026_selection_intents`
   - `wp_vb2026_ngo_selection_audit_log`
3. publikus és autholt REST endpointok:
   - `GET /wp-json/impact/v1/ngo-catalog`
   - `GET /wp-json/impact/v1/vb2026/featured-ngos`
   - `GET /wp-json/impact/v1/vb2026/my-ngo-selection`
   - `POST /wp-json/impact/v1/vb2026/select-ngo`
   - `POST /wp-json/impact/v1/vb2026/selection-intent`
   - `POST /wp-json/impact/v1/vb2026/selection-intent/complete`
4. Sharity oldali Phase I katalógus route:
   - `/szervezetek/`
5. source ingest merge-logika:
   - CSV input + NGO-card slug/media/share/details truth összefűzés

Jelenlegi validáció:

1. `php -l wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php` PASS
2. `git diff --check` PASS

Még külön lezárandó a target oldali `vb-prod` bridge teljes QA-ja és az esetleges cross-repo finomítások.

Friss audit-hardening ugyanebben a körben:

1. a publikus source-katalógus immár nem listázhat `allow_public_listing=0` vagy inaktív campaign-state rekordot
2. a source sync gyanúsan alacsony aktív CSV-állapotnál fail-closed módon megáll
3. a `selection-intent` és a `select-ngo` lane ugyanarra a választhatósági truthra igazodik
