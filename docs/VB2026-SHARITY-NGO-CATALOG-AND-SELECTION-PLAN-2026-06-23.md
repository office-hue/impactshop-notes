# VB2026 Sharity NGO Catalog And Selection Plan — 2026-06-23

Statusz: kanonikus tervdoksi  
Scope: a `sharity.hu` domainen megjeleno NGO-katalogus, a VB2026-hoz kapcsolodo NGO-valasztasi modell, a kiemelt NGO-lista, a felhasznaloi sajat NGO-allapot, valamint a `vb-prod` es a Sharity source oldal kozotti kapcsolat.  
Implementacios hatar: terv, UX, adatmodell, sync- es integracios szabalyok. Cegjelzo-enrichment ebben a verzoban tudatosan nincs reszletezve.

Kapcsolodo forrasok:
- `https://app.sharity.hu/impact-challenge/`
- `https://app.sharity.hu/ngo-guides/impact-challenge/`
- `https://app.sharity.hu/ngo-guides/ngo-card/`
- `https://app.sharity.hu/impactshop_teszt/`
- Implementációs packet:
  - `docs/VB2026-SHARITY-NGO-CATALOG-PHASE1-IMPLEMENTATION-PACK-2026-06-23.md`
- Sharity NGO export CSV:
  - `https://docs.google.com/spreadsheets/d/e/2PACX-1vRHyEoPuoisnLotuF5fN7lqjTgfrB5Q_zWJWbg_l6IzVh2uL2I9dPFhrgZ2aEMuMA/pub?gid=340592635&single=true&output=csv`
- Kapcsolodo workspace terv:
  - `/Users/bujdosoarnold/Developer/GitHub/docs/impi-ngo-workspace-plan-finalization-2026-05-04.md`
- Kapcsolodo VB2026 target tervek:
  - `/Users/bujdosoarnold/Developer/GitHub/ai-agent/docs/VB2026-DOCUMENTATION-HUB-2026-06-11.md`
  - `/Users/bujdosoarnold/Developer/GitHub/ai-agent/docs/VB2026-TIPP-ELEMZO-IMPLEMENTATION-PLAN-2026-06-11.md`

---

## 1. Mi a problema, amit meg kell oldani

Jelenleg a VB2026 jatekban ket, egymasnak ellentmondo igeny van egyszerre jelen:

1. A kampanynak kell egy egyszeruen kommunikalt, atlathato, versenyszeru NGO-mezonye.
2. A usernek nem szabad elvesznie akkor sem, ha nem a kiemelt szervezetek kozott talalja meg a sajat ugyet.

Ha csak egy szuk VB2026-lista lenne:

1. a kampany ertheto maradna,
2. de romlana a konverzio azoknal, akik mast keresnek.

Ha a teljes Sharity NGO-allomany menne ki kontroll nelkul:

1. a user talan jobban megtalalna a sajat ugyet,
2. de szetesne a VB2026 verseny es kommunikacio,
3. tul nagy lenne a valasztasi teher,
4. nehez lenne egy kozos, ertheto toplistat es adomanyversenyt kommunikani.

Ezert a jo modell nem a teljes zaras, es nem is a teljes nyitas, hanem a ketretegu rendszer.

---

## 2. Vegleges termekdontes

### 2.1. Ketretegu NGO-modell

A `sharity.hu` oldalon ket kulon fogalmat kell kezelni:

1. `Kiemelt VB2026 NGO-k`
2. `Teljes Sharity NGO katalogus`

### 2.2. Mit jelent ez a gyakorlatban

1. A VB2026 publikus kommunikacio, verseny, toplista es gyorsvalasztas a `kiemelt VB2026 NGO-k` korul szervezodik.
2. A user ettol fuggetlenul a `teljes Sharity NGO katalogus` aktiv szervezetei kozul is valaszthat.
3. Ha a user nem kiemelt NGO-t valaszt, attol meg a sajat NGO-ja teljesen ervenyes marad.
4. A usernek bejelentkezes utan mindig a `sajat valasztott NGO` az elso szamu truth, nem a toplista.

### 2.3. A kiemelt lista alapja

Az elso verzioban a `kiemelt VB2026 NGO-k` kore:

1. az Impact Challenge Top 10-re epul,
2. kesobb operatori dontessel bovitheto,
3. de nem automatikus teljes katalogus-klon.

### 2.4. A teljes katalogus szerepe

A teljes katalogus nem a VB2026 verseny "masodik toplistaja", hanem:

1. keresesi felulet,
2. NGO-felfedezo oldal,
3. szemelyes valasztas tamogato felulet,
4. hosszu tavu Sharity NGO directory.

---

## 3. Source of truth es ownership

### 3.1. Kanonikus NGO-forras

A kanonikus szervezeti source of truth a Sharity sajat NGO-adata.

Ez azt jelenti, hogy a szervezet alap-identitasa innen jon:

1. belso Sharity NGO azonosito
2. nev
3. logo
4. statusz
5. alap leiro mezok
6. kategoria / jogallas / foldrajzi meta

### 3.2. Kiegeszito kampany-layer

A VB2026-specifikus allapot nem irja felul a Sharity NGO-identitast, csak rarakodik.

Kulon campaign-layer kell a kovetkezo mezokre:

1. szerepel-e a VB2026 kiemelt listaban
2. milyen sorrendben jelenjen meg
3. van-e egyedi kampanyszovege
4. megjelenjen-e a publikus VB2026 versenyben
5. kapjon-e kulon badge-et vagy kiemelest

### 3.3. Ownership

1. `NGO alapadatok`: Sharity source truth
2. `VB2026 kampany allapot`: operatori/admin kampany truth
3. `User kinek szavaz`: user-level truth
4. `VB2026 toplista / verseny`: a kampanyban reszt vevo, kiemelt mezony truthja

---

## 4. Adatmodell

## 4.1. Alap NGO entity

Az alap szervezet-entity minimum mezoi:

1. `sharity_ngo_id`
2. `slug`
3. `name`
4. `city`
5. `postal_code`
6. `category_label`
7. `legal_status_label`
8. `website_url`
9. `logo_url`
10. `cover_image_url`
11. `campaign_count`
12. `tax_number_masked_or_internal_only`
13. `is_active`
14. `created_at`
15. `source_last_synced_at`

Megjegyzes:

1. a nyers CSV-ben szereplo erzekeny mezok nem mehetnek ki publikus UI-ba,
2. ilyenek kulonosen:
   - kapcsolattarto e-mail
   - merchant secret
   - bankszamlaszam
3. ezek backend/internal-only mezok maradnak.

ID-szabaly:

1. az API-kban szereplo `ngo_id` MVP-ben a kanonikus `sharity_ngo_id`-t jelenti
2. implementacioban nem lehet kulon `ngo_id` es `sharity_ngo_id` truthot bevezetni dokumentalt migracio nelkul
3. ha kesobb kulon belso surrogate PK marad a DB-ben, attol a publikus/autholt contract meg ugyanugy a kanonikus NGO-azonositora epuljon

## 4.2. NGO public summary view model

A publikus kartyahoz kulon, szurt view model kell:

1. `ngo_id`
2. `slug`
3. `name`
4. `city`
5. `category_label`
6. `legal_status_label`
7. `short_mission`
8. `logo_url`
9. `cover_image_url`
10. `website_url`
11. `is_active`
12. `is_featured_vb2026`
13. `is_user_selected`
14. `is_in_vb2026_race`
15. `current_public_rank` opcionális
16. `current_public_votes` opcionális
17. `campaign_badges[]`

## 4.3. VB2026 campaign layer

Kulon campaign tabla vagy campaign config entity kell:

1. `campaign_key`
2. `sharity_ngo_id`
3. `is_featured`
4. `is_race_visible`
5. `display_priority`
6. `campaign_copy_short`
7. `campaign_copy_long`
8. `hero_badge`
9. `allow_public_listing`
10. `allow_user_selection`
11. `campaign_state`
12. `updated_at`
13. `updated_by`

Javasolt `campaign_key`:

1. `vb2026`

## 4.4. User selection layer

Kulon user-level selection entity kell:

1. `pseudo_id`
2. `contest_scope`
3. `selected_sharity_ngo_id`
4. `selected_at`
5. `selection_source`
6. `was_featured_at_selection_time`
7. `selection_lock_state`
8. `last_changed_at`

Javasolt ertekek:

1. `contest_scope='vb2026'`
2. `selection_source='vb_prod' | 'sharity_catalog' | 'sharity_compact_selector'`

`selection_lock_state` minimum ertekek:

1. `open`
2. `locked_by_user`
3. `locked_by_system`

Szabalya:

1. MVP-ben az alapertelmezett allapot `open`
2. ha nem `open`, a valasztas nem irhato felul normal user write-tal
3. a lock-allapot valtozas auditlandó operatori/system muvelet

## 4.5. Slug truth szabály

QA finding:

1. a jelenlegi CSV nem ad külön, garantált publikus slug mezőt

Vegleges szabaly:

1. itt nem új slug-rendszert kell kitalálni, hanem a meglévő Impact Shop / NGO Card oldali kanonikus NGO slugot kell átvenni
2. a VB2026 NGO-katalógus, az NGO-kártya widget és a kapcsolódó deeplinkek ugyanazt a slug truthot használják
3. a CSV nem slug-source, legfeljebb kiegészítő adatforrás
4. a slug nem változhat csendben sync közben
5. ha slug-migráció egyszer mégis kell, az csak külön, dokumentált source-oldali migrációval történhet

Következmény:

1. a korábbi deterministic fallback slug csak vészhelyzeti technikai fallback lehet
2. normál működésben a kanonikus Impact Shop NGO slug az irányadó

## 4.5.1. Kép- és logóforrás szabály

QA finding:

1. a nyers CSV-beli `Logo` és `Kép` linkek nem tekinthetők önmagukban elég stabil, végleges publikus forrásnak

Végleges szabály:

1. a kártyák ne közvetlenül a nyers CSV-linkekre épüljenek
2. source-side media normalizáló réteg kell:
   - elsődleges: meglévő kanonikus NGO-card / Sharity media lane
   - másodlagos: ellenőrzött, lokálisan tárolt vagy proxyzott media URL
3. ha nincs megbízható cover image, a kártya működjön cover nélkül is
4. ha nincs megbízható logó, legyen egységes fallback embléma / monogram
5. a publikus UI nem törhet el attól, hogy egy CSV-link megváltozik vagy megszűnik

## 4.6. Verseny / leaderboard layer

Kulon public race summary kell a kiemelt mezonyre:

1. `contest_scope`
2. `ngo_id`
3. `rank`
4. `raw_votes`
5. `weighted_votes`
6. `share_percent`
7. `trend_delta`
8. `updated_at`

Fontos:

1. a teljes katalogus es a publikus verseny nem ugyanaz a lista,
2. a versenyblokk kezdetben a kiemelt VB2026 mezonyre optimalizaljon.

---

## 5. Oldalarchitektura a Sharity domainen

Javasolt uj vagy bovitett publikus oldal:

1. `app.sharity.hu/szervezetek/` vagy funkcioazonos uj NGO directory route

Ez legyen a teljes NGO-katalogus emberi oldala.

### 5.1. Oldalcél

Az oldal egyszerre legyen:

1. NGO-katalogus
2. keresofelulet
3. VB2026-bol erkezo usernek valasztasi felulet
4. Sharity onmagaban is hasznalhato NGO-felfedezo oldal

### 5.2. Oldal fo blokkjai

1. Hero / bevezeto blokk
2. Kiemelt VB2026 NGO-k blokk
3. Szurok es kereso
4. Talalati lista / kartyaracs
5. ABC gyorsnavigacio vagy nev szerinti keresesi side-rail
6. Kivalasztott NGO allapotcsik, ha van user session

---

## 6. Kiemelt VB2026 blokk terve

### 6.1. Celja

Gyorsan mutassa meg:

1. kik a kampany kiemelt szervezetei
2. kik versenyeznek most a VB2026 kommunikacioban
3. hova tud a user egy kattintassal beloni magat

### 6.2. Mit tartalmaz

1. `Top 10 a VB2026 kampanyban`
2. 10 kartyas vagy tobb soros kompakt lista
3. aktualis helyezes badge
4. rovid leiras
5. `Támogatom ezt az ügyet` gomb
6. `Reszletek` gomb

### 6.3. Fontos szabaly

Ez a blokk:

1. nem zarja ki a tobbi NGO-t,
2. hanem gyors bejaro a kampanyhoz,
3. es a "Nem ezt keresed?" CTA-val atvisz a teljes katalogusba.

---

## 7. Teljes NGO katalogus UX

## 7.1. Szurok

Az oldal tetejen vagy bal oldali desktop sávban:

1. `Nev szerinti kereses`
2. `Kategoria`
3. `Jogallas`
4. `Megye` csak akkor, ha van stabil derived mapping
5. `Telepules`
6. `Csak aktiv szervezetek`
7. `Csak VB2026-ban kiemeltek`

### 7.2. Minimalis szuroviselkedes

1. kereseskor az eredmenylista azonnal szukuljon
2. a keresoben a nev elejere es reszsztringre is mukodjon talalat
3. a szurok URL-ben is tarolhatoak legyenek
4. nulltalalatra egyertelmu fallback copy kell

### 7.3. ABC keresesi seged

Az oldal kapjon:

1. normal szoveges keresot
2. opcionális ABC gyorsugrast

Az ABC ne helyettesitse a keresot, csak segitse azt.

---

## 8. NGO kartya terv

## 8.1. A kartya fo szerepe

A kartya ne admin adatlap legyen, hanem gyors emberi dontest segito blokk.

A usernek 3 kerdesre kell gyorsan valaszt kapnia:

1. ki ez a szervezet
2. mivel foglalkozik
3. tamogathatom-e most ezt az ugyet

## 8.2. Kartyan kotelezoen megjeleno elemek

1. logo
2. szervezet neve
3. telepules vagy varos
4. kategoria badge
5. jogallas badge
6. 2-3 soros rovid kuldetes / leiras
7. aktiv / inaktiv statuszjelzes
8. ha relevans: `VB2026 kiemelt` badge
9. ha relevans: `Most ezt tamogatod` badge
10. fo CTA: `Ezt tamogatom`
11. masodlagos CTA: `Reszletek`

## 8.3. Kartyan opcionálisan megjeleno elemek

1. honlap link
2. toplistás helyezés, ha VB2026 versenyblokkban jelenik meg
3. rövid kampánymondat
4. `Megosztás` akció

Kifejezett kizárás:

1. a `kampányok száma` nem kártyamező

## 8.4. Mit ne tegyunk a kartyara

1. hosszu jogi szoveget
2. belso technikai mezoket
3. erzekeny kapcsolattarto adatot
4. tul sok numerikus adatot
5. tobbfele versenylogikat egyszerre

## 8.5. Kartya valtozatok

Kulon UI-variant kell:

1. `featured-card`
2. `catalog-card`
3. `selected-card`
4. `race-card`

Ezek adatban kozel azonosak, de a hangsulyuk mas:

1. `featured-card`: gyors valasztas
2. `catalog-card`: felfedezes
3. `selected-card`: megerosites
4. `race-card`: versenykommunikacio

---

## 9. Felhasznaloi flow

## 9.1. VB2026 oldalrol erkezo user

### Allapot A: meg nincs valasztott NGO

1. user a `vb-prod` oldalon be van lepve / pseudo kapcsolt
2. a Sharity nezetben lat egy rovid NGO blokkot
3. `Valassz civil ugyet` CTA a Sharity NGO-katalogusra visz
4. ott eloszor a kiemelt VB2026 lista latszik
5. ha nem megfelelo, atmegy a teljes katalogusba
6. kivalaszt egy aktiv NGO-t
7. sikeres mentes utan visszaterhet a `vb-prod` oldalra
8. a `vb-prod` innentol a sajat NGO-jat mutatja

### Allapot B: mar van valasztott NGO

1. user megnyitja a `vb-prod` oldalt
2. latja a sajat NGO-jat
3. kap `Masik NGO keresese` vagy `Valtas` CTA-t
4. atmegy a Sharity NGO-katalogusra
5. valtas utan visszater a `vb-prod`-ra

## 9.2. Kozvetlenul a Sharity katalogusrol erkezo user

1. katalogus nezes
2. NGO kivalasztasa
3. ha nincs pseudo/session, identity-folyamat
4. mentett NGO allapot
5. opcionális tovabblepes VB2026 oldalra

## 9.2.1. Kattintas bejelentkezes elott

Implementacios kovetelmeny:

1. ha a user nincs kapcsolva, az `Ezt tamogatom` kattintas ne vesszen el
2. a rendszer rogzitsen egy rovid elettartamu `selection intent` allapotot
3. az identity-flow utan ezt a pending intentet be kell tudni fejezni

Javasolt minimal modell:

1. `POST /wp-json/impact/v1/vb2026/selection-intent`
2. payload:
   - `selected_sharity_ngo_id`
   - `contest_scope='vb2026'`
   - opcionális `return_to`
3. response:
   - `intent_token`
   - `auth_url`
4. sikeres profile/connect utan:
   - `POST /wp-json/impact/v1/vb2026/selection-intent/complete`
5. a pending intent lejaraati idovel es alairassal vedett legyen

Ez a flow csokkenti azt a kockazatot, hogy a user kivalaszt egy NGO-t, majd az identity-lepes utan elveszik a szandeka.

Tovabbi kovetelmenyek:

1. egy intent csak egyszer teljesitheto
2. lejart intent nem fejezheto be
3. idegen pseudo/session nem fejezheti be a mas intentjet
4. a `return_to` csak allowlistes cel lehet

## 9.3. Ha a user nem kiemelt NGO-t valaszt

Ez kulon szabaly:

1. a valasztasa ervenyes
2. a sajat NGO-ja neki mindig latszodjon
3. de a publikus VB2026 versenyblokk attol meg maradhat a kiemelt mezonyre optimalizalt

---

## 10. Hogyan kapcsolodik ossze a `vb-prod` oldallal

## 10.1. A `vb-prod` oldalon mi marad

A user kerese alapjan a `Tippjatek` nezet maradjon tisztan betting-fokuszu.

Ezert a `vb-prod` betting oldalon csak egy kompakt NGO-bridge jelenjen meg:

1. van-e fiók
2. van-e kivalasztott civil ugy
3. gyors CTA a Sharity nezetre / katalogusra

## 10.2. Mi maradjon a Sharity nezetben

Minden NGO-hoz, pontokhoz, szavazati sulyhoz es versenyhez kapcsolodo bovebb funkcio:

1. Fiókom reszletesen
2. NGO valasztas
3. NGO toplista
4. NGO verseny
5. szavazati allas
6. "kit tamogatsz most" allapot

## 10.3. `vb-prod` minimal NGO surface

A `vb-prod` oldalon a Sharity blokk minimal payloadja:

1. `has_connected_profile`
2. `selected_ngo_summary`
3. `selected_ngo_is_featured`
4. `selected_ngo_is_active`
5. `selection_cta_url`
6. `selection_cta_label`

## 10.4. Deep linkek

Javasolt deep link celok:

1. `Sharity nezet` -> sajat NGO blokk
2. `Valassz NGO-t` -> katalogus kiemelt lista teteje
3. `Masik NGO keresese` -> teljes katalogus es elofeszitett kereso
4. `Megosztás` -> `https://app.sharity.hu/ngo/{slug}/share/`

---

## 11. API- es payload terv

## 11.1. Publikus katalogus endpoint

Javasolt olvaso endpoint:

1. `GET /wp-json/impact/v1/ngo-catalog`

Query parameterek:

1. `q`
2. `category`
3. `legal_status`
4. `county`
5. `city`
6. `featured_only=1`
7. `active_only=1`
8. `page`
9. `per_page`

Nevezektani szabaly:

1. az API payloadban a mezo neve kovetkezetesen `county`
2. a magyar UI-felirat ennek megfeleloen `Megye`
3. implementacioban nem lehet vegyesen `county` es `megye` payload-kulcsot hasznalni

Response fo blokkjai:

1. `filters`
2. `results`
3. `pagination`
4. `featured_summary`

## 11.2. Kiemelt VB2026 lista endpoint

Kulon egyszeru endpoint:

1. `GET /wp-json/impact/v1/vb2026/featured-ngos`

Response:

1. `campaign_key`
2. `updated_at`
3. `items[]`

## 11.3. User sajat NGO endpoint

Autholt endpoint:

1. `GET /wp-json/impact/v1/vb2026/my-ngo-selection`

Response:

1. `has_selection`
2. `selected_ngo`
3. `is_featured`
4. `can_switch`
5. `switch_help_copy`
6. `needs_attention`
7. `attention_message`

## 11.4. Valasztas endpoint

Autholt write endpoint:

1. `POST /wp-json/impact/v1/vb2026/select-ngo`

Request:

1. `selected_sharity_ngo_id`
2. `contest_scope='vb2026'`
3. opcionális `source_context`

Response:

1. `ok`
2. `selected_ngo`
3. `is_featured`
4. `effective_from`
5. `selection_message`

## 11.5. VB2026 bridge payload

A `vb-prod` target oldali `game/profile` vagy dedikalt sync payload kapjon NGO-reszt:

1. `selected_ngo`
2. `selected_ngo_badges`
3. `selected_ngo_is_featured`
4. `ngo_selection_url`
5. `ngo_competition_url`

---

## 12. Sync modell

## 12.1. NGO torzsadat sync

A teljes NGO-katalogus torzsadat nem kezzel szerkesztett HTML, hanem syncelt adat legyen.

Javasolt ritmus:

1. napi vagy felnapos sync a Sharity kanonikus NGO-forrasbol
2. publikus katalogus payload build ugyanebbol

## 12.2. Kampany-layer sync

A VB2026 kiemelt lista nem a teljes torzsadattal egyutt frissul automatikusan.

Ez operatori adat:

1. kezdeti top 10 seed
2. kesobbi manualis bovites / kivetel
3. kulon campaign config

## 12.3. User selection sync

Ez online runtime adat:

1. user valaszt
2. mentes azonnal megtortenik
3. a sajat NGO allapot azonnal latszodjon
4. a `vb-prod` a kovetkezo fetchkor ezt olvassa vissza

---

## 13. UX copy elvek

Az NGO-s feluletnel az emberek nem fognak hosszu szabalyzatot olvasni.

Ezert az oldal copy-ja:

1. egyszeru
2. rovid
3. dontest segito
4. allapotot magyarazo

### 13.1. Jo mintak

1. `Kit tamogass?`
2. `Most ezt a szervezetet tamogatod.`
3. `Nem ezt keresed? Nezz meg tobb szervezetet is.`
4. `A VB2026 kiemeltjei ezek, de mast is valaszthatsz.`

### 13.2. Rossz mintak

1. technikai zsargon
2. egyszerre tul sok szam
3. kevert verseny- es adminlogika egy blokkban

---

## 14. Allapotszabalyok

## 14.1. Aktiv / inaktiv NGO

1. csak aktiv NGO legyen valaszthato
2. inaktiv NGO megjelenhet archivalt / informacios nezetben, de ne legyen fo CTA-ja

## 14.2. Kiemelt / nem kiemelt

1. mindketto valaszthato lehet
2. csak a kiemelt kap publikus VB2026 race hangsulyt

## 14.3. Sajat NGO lathatosag

1. ha a usernek mar van sajat NGO-ja, mindig lassa
2. akkor is, ha az nem kiemelt
3. akkor is, ha a toplistaban nincs elol

---

## 15. Adatvedelmi es biztonsagi szabalyok

Kotelezo tiltott publikus mezok:

1. kapcsolattarto e-mail
2. merchant secret
3. bankszamlaszam
4. belso admin statuszmagyarazatok
5. olyan adatok, amelyek nem publikus NGO-katalogusra valok

Kotelezo backend-szabaly:

1. a katalogus API csak public-safe mezoket adhat ki
2. a user selection write endpoint autholt legyen
3. a selection write trail auditolhato legyen

---

## 16. Implementacios architektura

## 16.1. Fobb rendszerkomponensek

Az implementacioban 4 kulon reteget kell szetvalasztani:

1. `source ingest layer`
2. `campaign config layer`
3. `public catalog read layer`
4. `user selection + vb-prod bridge layer`

## 16.2. Route-topologia

Javasolt publikus oldalak a Sharity domainen:

1. `GET /szervezetek/`
   - teljes NGO katalogus
2. `GET /szervezetek/?featured=vb2026`
   - kiemelt VB2026 lista nezet
3. `GET /szervezetek/:slug/`
   - NGO reszletoldal, ha kesobb kell
4. `GET /profil/`
   - user sajat fiok es sajat NGO allapot

Javasolt autholt oldali logikai celpontok:

1. `#vb2026-ngo-selector`
2. `#vb2026-ngo-race`
3. `#vb2026-my-ngo`

## 16.3. `vb-prod` kapcsolodas

A `vb-prod` oldalon nem kell teljes katalogus vagy komplett NGO race render.

Csak ez a minimal bridge kell:

1. fiok kapcsolva van-e
2. van-e valasztott NGO
3. a valasztott NGO neve
4. featured-e vagy nem
5. gyors CTA:
   - `Civil ugy valasztasa`
   - `A tamogatott ugyem`
   - `Masik NGO keresese`

## 16.4. Runtime ownership szabaly

1. NGO katalogus render source-side felelosseg
2. NGO valasztas write source-side felelosseg
3. `vb-prod` csak olvaso-fogyaszto erre a lane-re
4. a `vb-prod` nem tarolhat kulon sajat NGO truthot
5. a kanonikus write mindig a Sharity source oldalon tortenjen

---

## 17. Forrasmezok es mapping

## 17.1. A jelenlegi CSV-bol kozvetlenul olvashato mezok

A mostani export fejlec alapjan a kovetkezo publikus/szurt mezok hasznalhatok:

1. `Azonosito` -> `sharity_ngo_id`
2. `Nev` -> `name`
3. `Szekhely - Iranyitoszam` -> `postal_code`
4. `Szekhely - Varos` -> `city`
5. `Tevekenyseg` -> `activity_text`
6. `Cel` -> `goal_text`
7. `Kategoriak` -> `category_label`
8. `Adoszam` -> internal-only vagy masked
9. `Kampanyok szama` -> `campaign_count_raw`
10. `Jogallas` -> `legal_status_label`
11. `Pontok osszesen` -> nem kotelezo katalogusmező
12. `Honlap` -> `website_url`
13. `Adomany.sharity.hu link` -> `donation_url`
14. `Logo` -> nyers source media input, nem közvetlen publikus truth
15. `Kep` -> nyers source media input, nem közvetlen publikus truth
16. `Letrehozas datuma` -> `created_at`
17. `Statusz` -> `source_status_label`

Megjegyzés:

1. a `slug` nem ebből az exportból jön megbízhatóan
2. a slug forrása a meglévő Impact Shop / NGO Card kanonikus slug lane
3. a `Logo` és `Kép` oszlop legfeljebb nyers media inputként kezelhető, nem végleges publikus render-truthként

## 17.2. Olyan mezok, amikhez derivalt logika kell

Nem minden kivant UX-mezo jon kozvetlenul a CSV-bol.

Kulon transzformacio kell:

1. `short_mission`
   - elso szabaly: `Cel` roviditett valtozat
   - masodik szabaly: ha `Cel` ures, `Tevekenyseg` roviditett valtozat
2. `is_active`
   - `Statusz == Aktiv`
3. `campaign_count`
   - a `Kampanyok szama` nyers stringbol numerikus parse
4. `legal_status_badge_variant`
   - `Kozhasznu szervezet` / `Normal` / `Egyeb`

## 17.3. Megye szuro problemaja

QA finding:

1. a jelenlegi CSV-ben nincs kulon `megye` oszlop
2. ezert a `megye` szuro nem epulhet vakon a nyers exportra

Javitott terv:

1. `megye` mezot kulon derived enrichment reteg adja
2. elso MVP-ben eleg lehet:
   - csak `varos`
   - opcionális `megye`, ha van megbizhato mapping
3. ha nincs stabil megye mapping, a szuro UI ezt ne igergesse hamisan

## 17.4. Public-safe mapping szabaly

Kulon whitelist kell a publikus exporthoz.

Tiltott mezok:

1. `Kapcsolattarto e-mail cime`
2. `Merchant secret`
3. `Bankszamlaszam`
4. barmely belso merchant credential
5. barmely nyers admin note

---

## 18. Adatbazis- es storage-terv

## 18.1. Javasolt tablák

### `wp_sharity_ngo_catalog`

Felelosseg:

1. syncelt torzsadat a kanonikus NGO-listahoz

Minimalis oszlopok:

1. `id BIGINT PK`
2. `sharity_ngo_id BIGINT UNIQUE NOT NULL`
3. `slug VARCHAR(190) NULL`
4. `name VARCHAR(255) NOT NULL`
5. `postal_code VARCHAR(20) NULL`
6. `city VARCHAR(190) NULL`
7. `county VARCHAR(190) NULL`
8. `category_label VARCHAR(190) NULL`
9. `legal_status_label VARCHAR(190) NULL`
10. `short_mission TEXT NULL`
11. `activity_text LONGTEXT NULL`
12. `goal_text LONGTEXT NULL`
13. `website_url VARCHAR(500) NULL`
14. `donation_url VARCHAR(500) NULL`
15. `logo_url VARCHAR(500) NULL`
16. `cover_image_url VARCHAR(500) NULL`
17. `campaign_count INT NOT NULL DEFAULT 0`
18. `source_status_label VARCHAR(64) NULL`
19. `is_active TINYINT(1) NOT NULL DEFAULT 0`
20. `source_created_at DATETIME NULL`
21. `source_last_synced_at DATETIME NOT NULL`
22. `source_row_hash CHAR(64) NOT NULL`
23. `created_at DATETIME NOT NULL`
24. `updated_at DATETIME NOT NULL`

### `wp_sharity_ngo_campaign_flags`

Felelosseg:

1. kampanyszintu config, nem source truth

Minimalis oszlopok:

1. `id BIGINT PK`
2. `campaign_key VARCHAR(64) NOT NULL`
3. `sharity_ngo_id BIGINT NOT NULL`
4. `is_featured TINYINT(1) NOT NULL DEFAULT 0`
5. `is_race_visible TINYINT(1) NOT NULL DEFAULT 0`
6. `allow_public_listing TINYINT(1) NOT NULL DEFAULT 1`
7. `allow_user_selection TINYINT(1) NOT NULL DEFAULT 1`
8. `display_priority INT NOT NULL DEFAULT 1000`
9. `hero_badge VARCHAR(64) NULL`
10. `campaign_copy_short TEXT NULL`
11. `campaign_copy_long LONGTEXT NULL`
12. `campaign_state VARCHAR(64) NOT NULL DEFAULT 'active'`
13. `updated_by BIGINT NULL`
14. `updated_at DATETIME NOT NULL`

Indexek:

1. `UNIQUE(campaign_key, sharity_ngo_id)`
2. `INDEX(campaign_key, is_featured, display_priority)`

### `wp_vb2026_user_ngo_selection`

Felelosseg:

1. user sajat NGO valasztasa a `vb2026` scope-ban

Minimalis oszlopok:

1. `id BIGINT PK`
2. `pseudo_id VARCHAR(191) NOT NULL`
3. `contest_scope VARCHAR(64) NOT NULL`
4. `selected_sharity_ngo_id BIGINT NOT NULL`
5. `selection_source VARCHAR(64) NOT NULL`
6. `was_featured_at_selection_time TINYINT(1) NOT NULL DEFAULT 0`
7. `selection_lock_state VARCHAR(32) NOT NULL DEFAULT 'open'`
8. `selected_at DATETIME NOT NULL`
9. `last_changed_at DATETIME NOT NULL`
10. `invalidated_at DATETIME NULL`
11. `invalidation_reason VARCHAR(64) NULL`

Indexek:

1. `UNIQUE(pseudo_id, contest_scope)`
2. `INDEX(contest_scope, selected_sharity_ngo_id)`

### `wp_vb2026_selection_intents`

Felelosseg:

1. bejelentkezes elotti NGO-valasztasi szandek rogzítese

Minimalis oszlopok:

1. `id BIGINT PK`
2. `intent_token_hash CHAR(64) UNIQUE NOT NULL`
3. `contest_scope VARCHAR(64) NOT NULL`
4. `selected_sharity_ngo_id BIGINT NOT NULL`
5. `return_to VARCHAR(64) NULL`
6. `created_for_session_id VARCHAR(191) NULL`
7. `created_for_pseudo_id VARCHAR(191) NULL`
8. `status VARCHAR(32) NOT NULL DEFAULT 'pending'`
9. `expires_at DATETIME NOT NULL`
10. `completed_at DATETIME NULL`
11. `created_at DATETIME NOT NULL`

Indexek:

1. `INDEX(status, expires_at)`
2. `INDEX(contest_scope, selected_sharity_ngo_id)`

Biztonsagi szabaly:

1. az endpoint a raw `intent_token` erteket adhatja vissza a kliensnek
2. adatbazisban ennek csak a hash-elt valtozata maradjon
3. a raw token ne keruljon auditlogba, URL-historyba vagy hibalogba feleslegesen

### `wp_vb2026_ngo_selection_audit_log`

Felelosseg:

1. az NGO-valasztasi write trail auditolasa

Minimalis oszlopok:

1. `id BIGINT PK`
2. `pseudo_id VARCHAR(191) NOT NULL`
3. `contest_scope VARCHAR(64) NOT NULL`
4. `previous_sharity_ngo_id BIGINT NULL`
5. `new_sharity_ngo_id BIGINT NOT NULL`
6. `selection_source VARCHAR(64) NOT NULL`
7. `actor_type VARCHAR(32) NOT NULL`
8. `result_state VARCHAR(32) NOT NULL`
9. `reason_code VARCHAR(64) NULL`
10. `created_at DATETIME NOT NULL`

Indexek:

1. `INDEX(pseudo_id, contest_scope, created_at)`
2. `INDEX(new_sharity_ngo_id, created_at)`

### `wp_vb2026_ngo_catalog_snapshots`

Felelosseg:

1. publikus olvaso snapshot publish layer

Minimalis oszlopok:

1. `id BIGINT PK`
2. `snapshot_key VARCHAR(64) NOT NULL`
3. `campaign_key VARCHAR(64) NOT NULL`
4. `payload_json LONGTEXT NOT NULL`
5. `item_count INT NOT NULL`
6. `created_at DATETIME NOT NULL`
7. `is_active TINYINT(1) NOT NULL DEFAULT 0`

Ez a snapshot tabla nem kotelezo az elso MVP-hez, de erosen ajanlott a safe publish miatt.

## 18.2. Migration sorrend

Kanonikus sorrend:

1. `wp_sharity_ngo_catalog`
2. `wp_sharity_ngo_campaign_flags`
3. `wp_vb2026_user_ngo_selection`
4. `wp_vb2026_selection_intents`
5. `wp_vb2026_ngo_selection_audit_log`
6. opcionálisan `wp_vb2026_ngo_catalog_snapshots`

## 18.3. Publish-safe szabaly

1. ingest ne irjon kozvetlenul publikus HTML-t
2. ingest eloszor torzsadatba irjon
3. publikus read layer csak validalt/szurt rekordokbol epitkezzen
4. ha snapshot publish van, az uj snapshot csak teljes build utan aktiválódjon
5. ha a sync szokatlanul keves aktiv rekordot ad vissza, a publikus publish fail-closed maradjon a last-good allapoton

## 18.4. Kiemelt lista operatori seedje

Mivel a kiemelt lista nem teljesen automatikus truth, kulon seed-szabaly kell.

MVP javaslat:

1. az elso seed `wp_sharity_ngo_campaign_flags` tabla feltoltesebol jon
2. ezt kezdetben egyszeri operatori seed script vagy SQL seed vegzi
3. a top 10 lista kezdeti forrasa az Impact Challenge aktualis Top 10
4. kesobbi kezeli mod:
   - Phase 1: seed script + manual DB update eleg
   - Phase 2: kulon admin UI kaphato

Ez tudatosan elvalasztja:

1. az NGO source truthot
2. a kampany kiemeles truthjat

---

## 19. Endpoint-contract implementacios reszletek

## 19.1. `GET /wp-json/impact/v1/ngo-catalog`

Cel:

1. teljes szurt katalogus oldali olvasas

Query:

```text
q?: string
category?: string
legal_status?: string
city?: string
county?: string
featured_only?: 0|1
active_only?: 0|1
page?: int
per_page?: int (max 48)
campaign?: string
```

Default szabaly:

1. publikus usernezetben `active_only=1` az alapertelmezett
2. `featured_only=0` az alapertelmezett
3. inaktiv rekord csak explicit archiv/diagnosztikai nezetben jelenhet meg

Response:

```json
{
  "ok": true,
  "campaign": "vb2026",
  "filters": {
    "q": "mento",
    "category": null,
    "legal_status": null,
    "city": null,
    "county": null,
    "featured_only": false,
    "active_only": true
  },
  "results": [
    {
      "ngo_id": 385,
      "slug": "onkentes-mentoszolgalat",
      "name": "Onkentes Mentoszolgalat",
      "city": "Tatabanya",
      "category_label": "Egeszsegugy",
      "legal_status_label": "Normal",
      "short_mission": "Mobil automata defibrillator beszerzese kozossegi es sportesemenyekhez.",
      "logo_url": "https://...",
      "cover_image_url": "https://...",
      "website_url": null,
      "is_active": true,
      "is_featured_vb2026": false,
      "is_in_vb2026_race": false,
      "campaign_badges": []
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 24,
    "total_items": 17,
    "total_pages": 1
  }
}
```

## 19.2. `GET /wp-json/impact/v1/vb2026/featured-ngos`

Cel:

1. gyors, kicsi payload a top 10 blokkhoz

Response:

```json
{
  "ok": true,
  "campaign_key": "vb2026",
  "updated_at": "2026-06-23T10:00:00Z",
  "items": [
    {
      "ngo_id": 101,
      "name": "Pelda Alapitvany",
      "city": "Budapest",
      "short_mission": "Rovid kampanyleiras",
      "logo_url": "https://...",
      "is_featured_vb2026": true,
      "is_in_vb2026_race": true,
      "rank": 1,
      "share_percent": 12.4,
      "hero_badge": "TOP 10"
    }
  ]
}
```

Publikalasi szabaly:

1. csak olyan NGO jelenhet meg itt, amely:
   - `is_active=1`
   - `allow_public_listing=1`
   - `is_featured=1`
   - `campaign_state='active'`
2. az `is_race_visible` azt szabalyzza, hogy kap-e nyilvanos versenyhangsulyt
3. ettol fuggetlenul a user korabbi sajat NGO-valasztasa nem ervenytelenedik

## 19.3. `GET /wp-json/impact/v1/vb2026/my-ngo-selection`

Cel:

1. autholt sajat NGO allapot

Response:

```json
{
  "ok": true,
  "has_selection": true,
  "selected_ngo": {
    "ngo_id": 205,
    "name": "Pelda Egyesulet",
    "slug": "pelda-egyesulet",
    "city": "Szeged",
    "short_mission": "Rovid leiras",
    "logo_url": "https://...",
    "is_active": true
  },
  "is_featured": false,
  "is_in_vb2026_race": false,
  "can_switch": true,
  "needs_attention": false,
  "attention_message": null,
  "switch_help_copy": "A valasztasodat barmikor modosithatod.",
  "selection_urls": {
    "manage": "/szervezetek/?campaign=vb2026&view=my-choice",
    "browse": "/szervezetek/?campaign=vb2026"
  }
}
```

## 19.4. `POST /wp-json/impact/v1/vb2026/select-ngo`

Cel:

1. user NGO-valasztasa vagy valtasa

Request:

```json
{
  "selected_sharity_ngo_id": 205,
  "contest_scope": "vb2026",
  "source_context": "sharity_catalog"
}
```

Response:

```json
{
  "ok": true,
  "selected_ngo": {
    "ngo_id": 205,
    "name": "Pelda Egyesulet",
    "slug": "pelda-egyesulet",
    "is_active": true,
    "is_featured_vb2026": false
  },
  "effective_from": "2026-06-23T10:15:00Z",
  "selection_message": "Mostantol ezt a civil ugyet tamogatod a VB2026 jatekban."
}
```

Write-semantika:

1. upsert `pseudo_id + contest_scope` alapon
2. idempotens viselkedes, ha ugyanazt az NGO-t kuldi ujra a user
3. ha az NGO mar nem aktiv vagy nem valaszthato, fail-closed hiba jon

CSRF/auth minimum:

1. same-origin session vagy nonce ellenorzes kotelezo
2. cross-site POST ne tudjon NGO-t valtani a user neveben

## 19.4.1. `POST /wp-json/impact/v1/vb2026/selection-intent`

Cel:

1. NGO-valasztasi szandek rogzites bejelentkezes elott

Request:

```json
{
  "selected_sharity_ngo_id": 205,
  "contest_scope": "vb2026",
  "return_to": "vb-prod"
}
```

Response:

```json
{
  "ok": true,
  "intent_token": "vb2026_sel_xxx",
  "expires_at": "2026-06-23T10:25:00Z",
  "auth_url": "https://app.sharity.hu/profil/?bridge_target=account&selection_intent=vb2026_sel_xxx"
}
```

## 19.4.2. `POST /wp-json/impact/v1/vb2026/selection-intent/complete`

Cel:

1. identity-flow utan a pending NGO-valasztas befejezese

Request:

```json
{
  "intent_token": "vb2026_sel_xxx"
}
```

Response:

1. ugyanaz a shape, mint a `select-ngo` write response-nal

## 19.5. Hiba-response contract

Koherencia miatt minden endpoint azonos hibaformatumot adjon:

```json
{
  "ok": false,
  "error": {
    "code": "NGO_NOT_SELECTABLE",
    "message": "Ez a szervezet jelenleg nem valaszthato."
  }
}
```

Javasolt hibakodok:

1. `INVALID_QUERY`
2. `UNAUTHORIZED`
3. `NGO_NOT_FOUND`
4. `NGO_NOT_ACTIVE`
5. `NGO_NOT_SELECTABLE`
6. `SELECTION_WRITE_FAILED`
7. `CATALOG_UNAVAILABLE`
8. `SELECTION_INTENT_EXPIRED`
9. `SELECTION_INTENT_INVALID`
10. `RETURN_TO_NOT_ALLOWED`

---

## 20. UI blokkterv implementacios reszletekkel

## 20.1. `szervezetek/` oldal blokkjai

Sorrend:

1. hero
2. kiemelt VB2026 blokk
3. sajat NGO csik, ha van session
4. kereso + szurok
5. katalogus lista
6. pagination vagy `Mutass tobbet`

## 20.2. Hero blokk

Tartalom:

1. cim: `Valassz civil ugyet`
2. rovid magyarazat:
   - `A VB2026 kiemeltjei mellett mas Sharity-szervezetet is valaszthatsz.`
3. ket CTA:
   - `Top 10 megnezese`
   - `Osszes szervezet`

## 20.3. Sajat NGO csik

Csak autholt usernek.

Tartalom:

1. `Most ezt tamogatod`
2. sajat NGO logo + nev
3. featured badge, ha relevans
4. `Megnezem`
5. `Masik szervezetet valasztok`

## 20.4. NGO kartya konkret layout

Felso sor:

1. logo balra
2. nev
3. badge-ek:
   - `VB2026 kiemelt`
   - `Most ezt tamogatod`
   - `Aktiv`

Kozepso resz:

1. `varos`
2. `kategoria`
3. `jogallas`
4. `short_mission`

Also sor:

1. fő CTA: `Támogatom ezt az ügyet`
2. másodlagos CTA: `Részletek`
3. harmadlagos CTA: `Megosztás`
4. opcionálisan `Honlap`
5. Phase 2-ben: `HTML-kód generálása`

Megosztás szabály:

1. a `Megosztás` gomb az adott NGO kanonikus share nézetére vigyen
2. kanonikus minta:
   - `https://app.sharity.hu/ngo/{slug}/share/`
3. a `{slug}` minden esetben az adott NGO kanonikus Impact Shop / NGO Card slugja
4. a share nézet NGO-függő, tehát mindig a kiválasztott szervezethez tartozó nézet nyílik meg

## 20.5. Kiemelt race kartya

Plusz elemek:

1. rank badge
2. reszesedesi szazalek
3. `versenyben van` rovid copy

## 20.6. Ures allapotok

Szukseges kulon copy-k:

1. nincs talalat:
   - `Nem talaltunk ilyen szervezetet. Probald mas nevre vagy szurore.`
2. nincs meg sajat NGO:
   - `Meg nincs beallitott civil ugyed.`
3. nem featured sajat NGO:
   - `A valasztott szervezetedet tovabbra is tamogatod, akkor is, ha nincs a kiemelt listaban.`

---

## 21. Allapotgep es valasztasi szabalyok

## 21.1. User selection state machine

Allapotok:

1. `no_profile`
2. `selection_requires_profile`
3. `connected_no_selection`
4. `connected_featured_selection`
5. `connected_non_featured_selection`
6. `connected_selection_invalidated`
7. `selection_write_pending`
8. `selection_write_failed`

Atmenetek:

1. `no_profile` -> `selection_requires_profile`
2. `selection_requires_profile` -> `connected_no_selection`
3. `connected_no_selection` -> `selection_write_pending`
4. `selection_write_pending` -> `connected_featured_selection`
5. `selection_write_pending` -> `connected_non_featured_selection`
6. `connected_featured_selection` -> `connected_selection_invalidated`
7. `connected_non_featured_selection` -> `connected_selection_invalidated`
8. `selection_write_pending` -> `selection_write_failed`
9. `selection_write_failed` -> `selection_write_pending`

## 21.2. Valasztasi szabalyok

1. egyszerre csak 1 aktiv NGO legyen valasztva `contest_scope='vb2026'` alatt
2. a valasztas felulirhato, ha `selection_lock_state='open'`
3. inaktiv NGO nem valaszthato
4. `allow_user_selection=0` NGO nem valaszthato
5. `campaign_state!='active'` NGO nem valaszthato ebben a campaign scope-ban
6. a featured allapot csak display-logaika, nem ervenyessegi feltetel
7. `allow_public_listing=0` onmagaban nem torli a korabbi valasztast, csak a publikus listazasbol veheti ki a rekordot

## 21.2.1. Invalidation szabaly

Ha a korabban valasztott NGO:

1. inaktiv lesz, vagy
2. `allow_user_selection=0` allapotra kerul

akkor:

1. a user selection rekord ne torlodjon csendben
2. kapjon `needs_attention` allapotot
3. a user latja, hogy uj NGO-t kell valasztania
4. a `vb-prod` es a Sharity oldal is egyertelmu figyelmeztetest mutasson

## 21.3. Featured listabol kieses szabaly

Koherencia finding es javitas:

1. ha egy NGO kesobb kiesik a top 10-bol vagy a featured listabol,
2. a user korabbi valasztasa ne ervenytelenedjen automatikusan

Vegleges szabaly:

1. a korabban valasztott, aktiv NGO maradjon ervenyes
2. csak a publikus race/featured badge valtozzon
3. forced invalidation csak akkor lehet, ha az NGO inaktiv vagy tiltott lett

---

## 22. Sync, cache es fallback szabalyok

## 22.1. Torzsadat ingest

Javasolt ingest lane:

1. CSV letoltes
2. parse
3. public-safe transform
4. row hash kepzes
5. upsert `wp_sharity_ngo_catalog`
6. post-sync summary

Minimum gate-ek:

1. ha `source_row_hash` nem valtozott, ne legyen felesleges rewrite
2. a publish-safe ellenorzes validalja az osszes rekord es az aktiv rekordok minimum darabszamat
3. gyanusan alacsony aktiv rekordszam eseten a last-good allapot maradjon ervenyben

## 22.2. Publish ritmus

1. napi 1-2 sync eleg a katalogushoz
2. user selection write realtime
3. featured config valtas publisholhato azonnal

## 22.3. Last-good fallback

Biztonsagi kovetelmeny:

1. ha az uj ingest hibas vagy ures,
2. a publikus katalogus ne nullazodjon le

Ezert:

1. last-good snapshot vagy last-good read layer kell
2. csak sikeres build utan aktiv snapshot csere
3. ha az MVP-ben meg nincs kulon snapshot tabla, attol meg kotelezo egy last-good olvaso fallback strategia
4. vagyis `snapshot` lehet Phase 2, de `fail-soft last-good` nem tolhato ki kesobbre

## 22.4. Cache policy

1. katalogus lista: rovid cache
2. featured lista: rovid cache
3. sajat NGO endpoint: no-store vagy minimal cache
4. selection write response: no-store

## 22.5. Failure modeok

1. CSV nem erheto el
   - last-good snapshot marad
2. parse hiba
   - ingest fail, public lane valtozatlan
3. featured config rekord hibas
   - teljes katalogus tovabb mukodik, featured blokk fail-soft
4. selection write hiba
   - user kapjon egyertelmu retry uzenetet

---

## 23. `vb-prod` integracios contract

## 23.1. `vb-prod` oldalon kotelezo elemek

1. NGO quick summary
2. `Civil ugy beallitasa` CTA, ha nincs selection
3. `A tamogatott ugyem` CTA, ha van selection
4. `Masik NGO keresese` secondary CTA, ha van selection

## 23.2. `vb-prod` oldalon tiltott elemek

1. teljes NGO katalogus lista
2. hosszu szurofal
3. kevert betting + NGO verseny nagy panel
4. teljes NGO toplista a betting foldben

## 23.3. `vb-prod` payload mezok

Javasolt shape:

```json
{
  "sharity_bridge": {
    "has_connected_profile": true,
    "selected_ngo_summary": {
      "ngo_id": 205,
      "name": "Pelda Egyesulet",
      "logo_url": "https://...",
      "city": "Szeged",
      "is_featured": false,
      "is_active": true
    },
    "selection_urls": {
      "select": "https://app.sharity.hu/szervezetek/?campaign=vb2026",
      "manage": "https://app.sharity.hu/szervezetek/?campaign=vb2026&view=my-choice"
    }
  }
}
```

## 23.4. Cross-domain return szabaly

1. `vb-prod` -> Sharity katalogus atlepese tartalmazhat `return_to=vb-prod`
2. ezt allowlistes return targetkent kell kezelni
3. mentes utan a user visszakuldheto a `vb-prod`-ra
4. a selection truth ettol fuggetlenul source-side-ban marad

---

## 24. MVP, Phase 2 es kesobbi scope

Fontos fazis-szabaly:

1. az MVP launch csomag nem azonos a szuk `Phase 1` alappal
2. az MVP launch a `Phase 1` es a kotelezo `Phase 2` elemek egyuttese
3. ez azert kell, mert a VB2026-hez a katalogus-alap onmagaban nem eleg, kell a featured es selection lane is

## 24.1. MVP kotelezo elemek

1. NGO torzsadat sync
2. teljes katalogus oldal
3. nev szerinti kereso
4. kategoriaszuro
5. varos szuro
6. aktiv-only kivalaszthatosag
7. featured top 10 blokk
8. user NGO selection write/read
9. `vb-prod` quick NGO bridge

## 24.2. Phase 2

1. race badge-ek
2. sajat NGO megerosito csik
3. featured vs non-featured copy finomitas
4. snapshot publish layer
5. NGO-kártya widgetkód-generáló ág
6. NGO-kártya embed gomb minden releváns szervezeti kártyán
7. Impact Amplifier / adományozási ág VB2026-kompatibilis átvezetése
8. NGO `Megosztás` gomb egységes bekötése a kanonikus share nézetre

## 24.3. Phase 3

1. NGO reszletoldal
2. erosebb versenynarrativa
3. szemelyre szabott javaslatok
4. NGO-widget admin- és preview-flow finomítása

## 24.4. Phase 4

1. Cegjelzo enrichment
2. tovabbi bizalmi adatok
3. tamogatoi insightok

## 24.5. Phase 2 részletesen — NGO widget és adományozási ág

Ez a szelet a meglévő NGO Card / `impactshop_teszt` referencia logikára épül, de a VB2026 célhoz igazítva.

### 24.5.1. Widgetkód-generáló gomb

Minden releváns NGO-kártyán legyen:

1. `HTML-kód generálása`

Viselkedés:

1. kattintásra modal vagy külön panel nyílik
2. a user kiválaszthatja a widget-változatot:
   - `Normál kártya`
   - `Kompakt kártya`
   - `Widget`
3. a rendszer kész HTML embed kódot ad
4. legyen `Másolás` gomb
5. a generált kód a kanonikus NGO slugra épüljön

### 24.5.2. Widget tartalmi irány

A widgetből tudatosan ki kell venni:

1. `Videók`
2. `Feladatok`

Megmaradhat:

1. szervezet neve
2. logó
3. rövid leírás
4. kampány-/állapotbadge
5. `Adományozok`
6. `Megosztás`
7. `Megnyitás` vagy `Részletek`

### 24.5.2.1. Megosztás gomb a kártyákon és a widgetben

Minden NGO-hoz tartozzon külön megosztási ág:

1. `Megosztás` gomb a katalógus-kártyán
2. `Megosztás` gomb a widgetes nézetben is

Viselkedés:

1. a kattintás az adott NGO meglévő share nézetét nyitja meg
2. kanonikus route:
   - `https://app.sharity.hu/ngo/{slug}/share/`
3. a share nézet NGO-függő, vagyis mindig az adott szervezet slugjára épül
4. a share route nem külön VB2026 route, hanem a meglévő Sharity NGO share felület

### 24.5.3. Adományozok gomb új célja

Az `Adományozok` gomb ne a régi, sima NGO-card adománylogikát vigye tovább, hanem a VB2026-hez igazított Impact Amplifier ágra mutasson.

Javasolt cél:

1. Impact Amplifier / Impact Challenge alapú céloldal
2. deeplink paraméterezve a kanonikus NGO sluggal
3. a jelenlegi NGO-card challenge deeplink mintája megtartható kiindulásnak, de a VB2026 adománylogikára át kell szabni

### 24.5.4. VB2026 adományelosztási szabály

A tervben a VB2026 adományozási ágnál ezt kell rögzíteni:

1. a bejövő adomány 90%-a a VB2026 közös adományalapba megy
2. a fennmaradó 10% a Sharity és a FactLens között 50-50%-ban oszlik meg
3. vagyis effektív:
   - 90% közös VB2026 adományalap
   - 5% Sharity
   - 5% FactLens

### 24.5.5. Szavazati logika az adományozásnál

1. az adományért továbbra is járjanak szavazatok
2. ezek a szavazatok már a VB2026 rendszerben legyenek leszavazhatók
3. az adománycsomagok maradhatnak
4. az NGO-card / Amplifier ág és a VB2026 szavazati ledger között külön integrációs szerződés kell majd

---

## 25. Koherencia, kockázatkezelesi es biztonsagi audit

Ez a szakasz mar a terv auditjanak eredmenyet rogzíti.

## 25.1. Audit finding 1 — featured lista es user truth osszekeverese

Kockazat:

1. a featured lista valtozasa ervenytelenithette volna a user NGO-valasztasat

Javitas a tervben:

1. featured allapot csak display-layer
2. user selection kulon source truth tabla
3. featured listabol kieses nem torli a user NGO-jat

## 25.2. Audit finding 2 — erzekeny CSV mezok kiszivarghatnak

Kockazat:

1. merchant secret
2. bankszamlaszam
3. kapcsolattarto email
4. egyeb belso mezok publikus API-ba kerulhettek volna

Javitas a tervben:

1. public-safe whitelist modell
2. kulon public summary view model
3. tiltolistak explicit rogzítve

## 25.3. Audit finding 3 — megye szuro hamis igeret lenne

Kockazat:

1. a CSV nem tartalmaz megyet
2. a UI olyan szurot mutathatna, amit az adat nem tud kiszolgalni

Javitas a tervben:

1. `megye` mezot derived enrichmentre tettem
2. MVP-ben nem kotelezo, csak ha stabil mapping van

## 25.4. Audit finding 4 — teljes katalogus szeteshet ingest hiba eseten

Kockazat:

1. egy hibas ingest lenullazhatna a publikus katalogust

Javitas a tervben:

1. last-good fallback
2. opcionális snapshot publish layer
3. fail-soft olvaso viselkedes

## 25.5. Audit finding 5 — `vb-prod` es Sharity kozott duplikalt truth alakulhat ki

Kockazat:

1. a target oldal kulon sajat NGO-allapotot kezdene tarolni

Javitas a tervben:

1. source-side write only
2. `vb-prod` consumer-only
3. explicit ownership szabaly

## 25.6. Audit finding 6 — inaktiv NGO-ra is lehetne szavazni

Kockazat:

1. user invalid szervezetet valasztana

Javitas a tervben:

1. `is_active` kotelezo gate
2. `allow_user_selection` kulon kampanygate

## 25.7. Biztonsagi minimumok

Kotelezo:

1. autholt write endpoint
2. pseudo/session ellenorzes
3. allowlistes `return_to`
4. public-safe mezowhitelist
5. last-good fallback
6. auditalhato selection write log
7. signed/TTL-limited pre-auth selection intent
8. same-origin vagy nonce-vedett selection-intent completion

---

## 26. QA a tervre es javitasok

Ez a szakasz a friss szemű terv-QA utan javitott allapotot rogzíti.

## 26.1. QA finding 1 — a kartya nem mondta meg eleg tisztan, mit jelent a nem featured allapot

Javitas:

1. bekerult kulon copy:
   - `A valasztott szervezetedet tovabbra is tamogatod, akkor is, ha nincs a kiemelt listaban.`

## 26.2. QA finding 2 — hianyzott a `vb-prod` tiltott scope listaja

Javitas:

1. kulon rogzitettem, mi nem mehet vissza a betting shellbe
2. ezzel kisebb a kesobbi UI-zsufolodas kockazata

## 26.3. QA finding 3 — hianyzott a migration sorrend

Javitas:

1. kulon migration szekcio bekerult
2. a storage publish-safe szabaly is rogzitve lett

## 26.4. QA finding 4 — hianyzott az egységes hibaformatum

Javitas:

1. bekerult a standard error payload
2. hibakodlista is meg lett adva

## 26.5. QA finding 5 — nem volt eleg konkret a source mezomapping

Javitas:

1. bekerult a CSV-header -> canonical field mapping
2. kulon transzformacios szabalyokkal

## 26.6. QA finding 6 — a `county` API-mezo es a `Megye` UI-copy konnyen szetcsuszhatott volna

Javitas:

1. kulon rogzitettem, hogy az API-kulcs mindenhol `county`
2. a magyar UI-felirat mindenhol `Megye`
3. ezzel kisebb a kesobbi contract-drift es frontend/backend felrenevezes kockazata

## 26.7. QA finding 7 — a slug körül túl sok bizonytalanság maradt

Javitas:

1. a terv most már explicit a meglévő Impact Shop / NGO Card kanonikus NGO slugot tekinti elsődleges truthnak
2. a deterministic fallback slug-modell csak vészhelyzeti technikai fallback lehet
3. a CSV slug-forrásként nem használható

## 26.8. QA finding 8 — az MVP es a fazisolas reszben egymasnak ellentmondott

Javitas:

1. kulon rogzitettem, hogy az MVP launch csomag a `Phase 1` es a kotelezo `Phase 2` szeletek egyuttese
2. igy mar nem mond ellent egymasnak az, hogy a featured top 10 es a selection lane MVP-kotelezo, mikozben fazislogikailag a masodik reteghez tartoznak

## 26.9. QA finding 9 — az `ngo_id` contract nem volt elegge lezarva

Javitas:

1. kulon ID-szabaly kerult a tervbe
2. MVP-ben az API `ngo_id` explicit a kanonikus `sharity_ngo_id`
3. ezzel kisebb a kesobbi dual-ID drift kockazata

## 26.10. QA finding 10 — a snapshot optionalitasa mellett nem volt eleg kimondva a kotelezo fallback

Javitas:

1. rogzitettem, hogy a `snapshot` maradhat kesobbi fazis
2. de a `last-good fail-soft` fallback mar MVP-minimum
3. ezzel kisebb a nullazodo katalogus vagy publikus uresallas kockazata ingest-hiba eseten

## 26.11. QA finding 11 — a pre-auth selection intentnek nem volt sajat storage truthja

Javitas:

1. bekerult a `wp_vb2026_selection_intents` tabla terve
2. ezzel a selection-intent flow mar nem csak endpoint-szinten, hanem tarolasi szinten is implementalhato

## 26.12. QA finding 12 — a featured lista publikacios gate-je nem volt eleg explicit

Javitas:

1. kulon rogzitettem, hogy a featured endpoint csak aktiv, publikusan listazhato, featured es aktiv campaign-state rekordokat adhat vissza
2. az `is_race_visible` kulon csak a versenyhangsulyt szabalyzza

## 26.13. QA finding 13 — a `campaign_state` szerepe lebego mezo maradt

Javitas:

1. bekerult a valasztasi szabalyok koze, hogy `campaign_state!='active'` alatt az NGO nem valaszthato az adott campaign scope-ban
2. ezzel kisebb a reszben archiv, de meg kivalaszthato rekordok kockazata

## 26.14. QA finding 14 — a publish-safe ingesthez hianyzott a minimum sanity gate

Javitas:

1. bekerult, hogy a publish-safe ellenorzes validalja az aktiv rekordok minimum darabszamat
2. gyanusan alacsony aktiv rekordszam eseten a last-good allapot maradjon ervenyben

## 26.15. QA finding 15 — a `selection_lock_state` szerepe nem volt eleg konkret

Javitas:

1. bekerult a minimum enum: `open`, `locked_by_user`, `locked_by_system`
2. rogzitve lett, hogy nem-`open` allapotban normal user write-tal nincs feluliras

## 26.16. QA finding 16 — az auditkovetelmenyhez nem volt konkret write-log storage

Javitas:

1. bekerult a `wp_vb2026_ngo_selection_audit_log` tabla terve
2. ezzel az `auditalhato selection write log` mar nem elvi, hanem implementalhato kovetelmeny

## 26.17. QA finding 17 — a pre-auth intent token tarolasa tul lazan volt leirva

Javitas:

1. a terv most mar hash-elt `intent_token` tarolast ir elo
2. a raw token csak kliens-visszaadott, rovid eletu ertek
3. ezzel kisebb a token-szivargas es log-expozicio kockazata

## 26.18. QA finding 18 — a katalogus default szuroviselkedese nem volt eleg explicit

Javitas:

1. rogzitettem, hogy publikus usernezetben `active_only=1` az alapertelmezett
2. inaktiv rekord csak explicit archiv/diagnosztikai nezetben jelenhet meg

## 26.19. QA finding 19 — az `allow_public_listing` es a valasztasi ervenyesseg konnyen osszekeveredhetett volna

Javitas:

1. bekerult, hogy `allow_public_listing=0` onmagaban nem torli a korabbi user-valasztast
2. ez csak a publikus listazasbol veheti ki a rekordot

## 26.20. QA vegso verdict

Jelen dokumentum most mar:

1. implementacio-elokeszitett
2. auditolt
3. QA-zott
4. kanonikus docsync forrasnak alkalmas

Megmarado tudatos nyitott pontok:

1. `megye` enrichment modszere
2. featured top 10 kezdeti operatori seed konkret listaja
3. snapshot tabla MVP-be bekerul-e vagy Phase 2-be marad
4. a VB2026 adomány- és szavazati ledger pontos technikai összekötése az Impact Amplifier ággal

Ezek mar nem tervezesi hianyok, hanem implementacios termekdontesi kapcsolopontok.

---

## 27. Fazisolas

## 27.1. Phase 1 — katalogus alap

1. NGO torzsadat sync
2. publikus katalogus oldal
3. szurok
4. kereso
5. NGO kartya

## 27.2. Phase 2 — VB2026 kampany layer

1. top 10 featured config
2. kiemelt blokk
3. sajat NGO mentes
4. `vb-prod` bridge payload

## 27.3. Phase 3 — verseny es szemelyesebb allapot

1. sajat NGO allapot megerositese
2. kampany uzenetek
3. toplista-magyarazatok
4. felhasznaloi guidance

## 27.4. Phase 4 — kesobbi enrichment

1. Cegjelzo es egyeb bizalmi enrich
2. reszletesebb NGO profiloldal
3. kampanyoldali tovabbi bizonyito elemek

---

## 28. Nem-celok ebben a tervben

1. Cegjelzo mostani reszletes implementacioja
2. teljes verseny-matematika implementalasa
3. VB2026 szavazatledger vegleges szabalyainak ujranyitasa
4. az Impact Challenge jelenlegi teljes rangsorlogikajanak attervezese

---

## 29. Kanonikus docsync szabaly

Ez a fajl a kanonikus tervdoksi ehhez a temaegyseghez.

Ha a kesobbi implementacio barmelyik alabbi teruletet erinti, ezt a fajlt frissiteni kell:

1. NGO katalogus oldal szerkezete
2. NGO kartya mezoi
3. featured vs full catalog logika
4. user NGO-valasztasi folyamat
5. `vb-prod` <-> Sharity osszekotes
6. campaign-layer ownership
7. publikus vagy autholt NGO endpoint contract
8. source mezomapping
9. selection state machine
10. fallback/cache/snapshot szabaly

Kotelezo docsync celpontok:

1. ez a fajl
2. `docs/ngo-guides/README.md`
3. az ai-agent oldali `VB2026` dokumentacios hub
4. az ai-agent oldali `VB2026` implementacios / termekterv hivatkozasok

Megvalosulasi dokumentacio szabaly:

1. a jovobeli implementacios allapotot ehhez a fajlhoz kell visszairni,
2. vagy explicit erre hivatkozo closeout / implementation update fajlba,
3. de ez marad az elso szamu kanonikus tervforras.

---

## 30. Vegso javaslat

Rovid, vegleges termekdontes:

1. a Sharity domainen kell egy teljes, keresheto NGO katalogus,
2. a VB2026 kampany ettol fuggetlenul egy kiemelt top 10 mezonnyel dolgozzon,
3. a user barmely aktiv NGO-t valaszthasson,
4. a sajat NGO-ja mindig latszodjon neki,
5. a publikus versenykommunikacio maradhat a kiemelt mezonyre optimalizalt,
6. a `vb-prod` betting oldalon csak kompakt NGO-bridge maradjon,
7. a reszletes NGO, pont, szavazat es verseny UX a Sharity nezetben / domainen eljen,
8. a source-side Sharity maradjon a kanonikus write truth, a `vb-prod` pedig csak fogyaszto legyen.
