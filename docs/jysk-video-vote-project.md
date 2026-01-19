# JYSK reklam video szavazas – projektterv

## Cel es scope
- JYSK reklam video megtekintes utan a felhasznalo 1 szavazatot adhat egy civil szervezetnek.
- 5-10 elore megadott civil szervezet versenyez.
- Egy felhasznalo (pseudo_id) naponta egyszer szavazhat.
- Szavazas idoszakhoz kotott: indulasi es zarasi idopont.
- Zaraskor automatikusan a legtobb szavazatot kapo civil szervezet nyer.
- Nyeremeny: 500 000 Ft erteku JYSK termekadomany.
- Oldalba be kell illeszteni az Impact Shop fiok/azonosito panelt.

## Fo UX flow
1) Felhasznalo kivalaszt egy civil szervezetet.
2) Vegignezi a JYSK reklamot (100% befejezes szukseges).
3) A szavazat gomb aktiv, bekuldesre kerul a szavazat.
4) Szavazatszamlalo folyamatosan frissul.

## Idozona es napi limit
- Idozona: Europe/Budapest.
- Napi limit kulcs: day_key = Y-m-d (HU idozona).
- Egy pseudo_id naponta 1 szavazat.

## Adatmodell (WP DB)

### impact_vote_campaigns
- id (PK)
- name
- start_at (datetime, HU idozona)
- end_at (datetime, HU idozona)
- status (scheduled | active | closed)
- created_at

### impact_vote_ngos
- id (PK)
- campaign_id (FK)
- ngo_slug
- ngo_name
- description
- logo_url
- is_active

### impact_vote_log
- id (PK)
- campaign_id
- ngo_id
- pseudo_id
- voted_at
- day_key (Y-m-d HU)
- ip_hash
- ua_hash

### impact_vote_daily
- day_key
- campaign_id
- ngo_id
- votes

### impact_vote_winner
- campaign_id
- ngo_id
- decided_at
- votes

## REST API v1

### GET /impact/v1/vote/campaign
- Visszaadja az aktualis kampanyt, start/end idopontokat, NGO listat.
- Ha nincs aktiv kampany, status: none.
- Idopontok ISO8601 formatumban, timezone offsettel.

### GET /impact/v1/vote/tally?campaign_id=
- Osszesitett szavazatok NGO-nkent (impact_vote_daily).
- Cache 15s, ETag/If-None-Match tamogatas a 304 valaszhoz.

### GET /impact/v1/vote/status
- Visszaadja, hogy az adott pseudo_id szavazott-e aznap.

### POST /impact/v1/vote/view
- Video view event. 100% befejezes utan kuldjuk.
- Payload: { campaign_id, completed: true }
- A szerver visszaad egy rovid elettu view_token-t (5-10 perc), amit a vote/cast-nek kuldeni kell.

### POST /impact/v1/vote/cast
- Payload: { campaign_id, ngo_id, view_token }
- Validacio:
  - kampany active
  - video 100% teljesites (view_token ellenorzes, HMAC + transient/Redis)
  - napi limit (pseudo_id + day_key)
  - NGO aktiv

## Frontend oldal (shortcode)

### [impactshop_vote_page]
Reszek:
- Fenti identitas blokk: [impactshop_identity_id]
- Video player (JYSK reklam)
- NGO kartyak (5-10)
- Szavazat gomb (csak 100% video megtekintes utan aktiv)
- Szavazatszamlalo (poll 10-20s)
- Lenti fiok/kezelo panel: [impactshop_identity_panel]
- UI allapotgep: NOT_STARTED -> PLAYING -> COMPLETED -> VOTED (PAUSED opcionalis).
- Gomb allapotok: disabled -> enabled -> success ("Koszonjuk").

## Admin / Operacio
- Admin oldal kampany es NGO lista kezelesre.
- Kampany automatikus zarasa cronon.
- Zaraskor automatikus gyoztes jeloles.
- Export CSV a szavazatokrol es daily osszesitesrol.
- Kampany statusz kezeles: scheduled -> active -> closed (5 percenkenti cron).
- Gyoztes edge case: dontetlen es 0 szavazat kezelese.

## Anti-abuse
- Napi limit pseudo_id alapon (HU idozona).
- IP hash + user agent hash log (anomalia detektalas, hash_hmac + salt).
- Rate limit a vote/cast endpointon (pseudo_id: 10/ora, IP: 50/ora, 429 + Retry-After).
- Video vegignezest csak ended event utan fogadjuk el.

## Video player kovetelmenyek
- HTML5 video: ended event + currentTime >= duration * 0.98.
- Seek elore tiltasa, playback speed 1x.

## Idokezeles
- Minden datetime UTC-ben tarolva (gmdate), day_key HU idozonaban szamolva.
- REST valaszok ISO8601 offsettel.

## DB indexek (javasolt)
- impact_vote_log: idx_pseudo_day (pseudo_id, day_key, campaign_id), idx_campaign_ngo (campaign_id, ngo_id)
- impact_vote_daily: UNIQUE (day_key, campaign_id, ngo_id)
- impact_vote_ngos: idx_campaign_active (campaign_id, is_active)
- impact_vote_log: idx_campaign_day (campaign_id, day_key)
- impact_vote_daily: idx_campaign_votes (campaign_id, votes)
- impact_vote_log: UNIQUE (campaign_id, pseudo_id, day_key)

## Kiegeszito epizodok (opcionalis, gyors fejlesztesek)
1) View hitelesites heartbeat
   - Video lejatszas kozben 10-20 masodpercenkent heartbeat esemeny.
   - A /vote/view csak akkor ad view_token-t, ha a heartbeat folytonos volt.

2) Eredmeny endpoint
   - GET /impact/v1/vote/results: osszesitett toplista kampanyon belul.
   - Cache + ETag (304 Not Modified).

3) Frontend progress visszajelzes
   - “Eddig hitelesen: 85%” jelzes a vegignezesehez.
   - Gomb allapot: disabled -> enabled -> success.

4) Offline/ujraprobalas UX
- Vote/cast hibanal auto retry 5s backoff-fal.
- Latvanyos hibauzenet + “Ujraprobalas”.

5) Kampany status cron fallback
   - WP cron 5 percenkent, plusz WP-CLI scheduled fallback.
   - Indulas/zaras/gyoztes automatikus update.

6) Admin monitoring mini panel
   - Aktiv kampany statusz, napi szavazatok, top 3 NGO.
   - Rate limit esemenyek szama (IP/UA hash).

7) E2E teszt csomag
   - Playwright/Cypress: video completion, napi limit, idozona atlepes.

8) Kozossegi cel progress bar (gamification)
- “Kozos cel 50%” jelzo sav a reszvetel novelesehez.

9) View token NGO-tol fuggetlen
   - A view_token csak a kampanyhoz kotott, nem NGO-hoz.
   - Megtekintes utan barmelyik NGO-ra leadhato a szavazat.

10) View es vote retry logika
   - Ha a view/cast request elhasal, frontend retry backoff-fal.
   - “Megtekintes rogzitese folyamatban…” allapot jelzese.

11) Cache kulcs kampany azonositohoz
   - Tally cache kulcs tartalmazza a campaign_id-t.
   - Kampany valtaskor nem ragad be regi adat.

## Teszt / QA
- Kampany start/end elott/utan nem lehet szavazni.
- HU idozona szerinti napvaltast ellenorizni.
- 100% video megtekintes szukseges (ended event).
- Napi limit ujraprobalas ellen.
- Szamlalo frissules es mobil kompatibilitas.

## Implementacios foci
- MU plugin a DB, REST, cron, shortcode logikaval.
- Frontend JS a video es szavazas flow-hoz.
- Identity panel mar letezik, csak beagyazni kell.
