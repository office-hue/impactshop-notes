# Affiliate integrációs runbook (Impact Shop)

## Cél és hatókör
Ez a runbook végigvezet minden lépésen, hogy egy új affiliate hálózat bekötése ne hagyjon ki funkciót, és ne bontsa meg a meglévő (Dognet/CJ) integrációkat. A cél, hogy az Impact Shop összes felülete és számítása konzisztensen működjön:
- Netflix cards (shop kártyák)
- Deals (banner/termék ajánlatok)
- Coupons (kupon kártyák)
- Sticky összeg (adomány/összegzések)
- Social ticker (megosztható donor nézettel)
- NGO toplista (weboldal)
- Shop toplista (weboldal)
- Shop + jutalékmérték oldal (donation rate)
- Activity feed (tranzakciók)
- Adományok nyomonkövetése (ledger, státuszok)

## 0) Előkészítés és scope
1) **Határozd meg a scope-ot**:
   - Click tracking (go/deeplink) szükséges?
   - Tranzakciós ingest (commission/approved/pending) szükséges?
   - Product/coupon/deal feed érkezik?
   - NGO azonosítás d1/d2/sid alapon megoldható?
   - Integráció érettség: basic click only / click + transactions / full feed + deals + coupons + webhook.
2) **Környezeti elérés**: staging + production hozzáférés, WP CLI, SSH, logs.
3) **Integrációs flag**: legyen feature flag (pl. `IMPACTSHOP_<NETWORK>_ENABLED`), hogy izoláltan kapcsolható legyen.
4) **Dokumentáció források**: API spec, auth, webhooks/pull API, rate limit, status mapping.
5) **Feature crosswalk** (kötelező/opcionális/jövőbeni):

| Feature | Kötelező | Opcionális | Jövőbeni |
| --- | --- | --- | --- |
| /go + deeplink | ✓ |  |  |
| Tranzakció ingest | ✓ |  |  |
| Sticky | ✓ |  |  |
| NGO toplista | ✓ |  |  |
| Shop toplista | ✓ |  |  |
| Activity | ✓ |  |  |
| Social ticker | ✓ |  |  |
| Coupons |  | ✓ |  |
| Deals |  | ✓ |  |
| Totals endpoint |  | ✓ |  |
| Webhook push |  |  | ✓ |

## 1) Adatforrások és kulcsok (biztonság)
- Auth kulcsok: PAT/API key/client secret.
- Rate limit és retry policy.
- Kulcsok helye: szerver env/secret fájlok (ne kerüljön gitbe).
- Kötelező mentési pont: `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env` (minden secret itt is legyen, akkor is, ha máshova is mented).
- Token lejárat/refresh mechanizmus.
- Prod/staging külön kulcs.
- Audit: mikor és ki frissítette a kulcsot.
- Kulcs ellenőrzés: cron/CLI health check (lejárat/invalid kulcs riasztás).

## 2) Azonosítók és attribúció
**Legkritikusabb pont**, mert minden nézet erre épül.
- **NGO azonosítás**: d1 (NGO slug) kötelező. Ha nincs d1 vagy nincs betűs slug, a sor **nem számolható** és **nem jeleníthető meg**.
- **Donor pseudo**: d2/sid (vagy network-specifikus param) → social ticker share és owner-azonosítás.
- **Shop azonosítás**: shop slug (pl. `cj-<id>` vagy saját `partner-<id>`). Ütközések elkerülése prefix-szel.
- **Tranzakció egyedi ID**: source_ref = `<NETWORK>:<transaction_id>` deduphoz.

## 3) Link generálás (/go és deeplink)
1) **/go branch**: új hálózat linképítése a meglévő `impactshop-go-bridge.php` (vagy megfelelő helper) előtt.
2) **NGO d1**: minden linkben legyen `d1=<ngo_slug>`. Ha nincs d1, a link ne menjen.
3) **Pseudo d2/sid**: a donor pseudo kerüljön `d2`-be (Dognet pattern) vagy `sid`-be (CJ pattern).
4) **Deeplink param**: network-specifikus (pl. `url`, `deeplink`, `destination`).
5) **Log**: minden /go hívás logolva (`impactshop-go-clicks.log`) → ts, shop, ngo, sid/d2, pseudo, target_host.
6) **Paraméter átadás**: ellenőrizd, hogy d1/d2/sid végigmegy-e a redirect láncon.

## 4) Shop registry és megjelenítés
1) **Shop lista**: legyen egységes registry (slug, name, logo, category, network).
2) **Netflix cards**:
   - Shop adatok összevonása (Dognet + új hálózat).
   - Kategóriák kompatibilisek a szűrőkkel.
3) **Shop + jutalékmérték oldal**:
   - Jutalék/donation rate egységesen megjelenik (pl. 50% szabály).
   - Számítás szabályai dokumentálva.

## 5) Deals és Coupons
1) **Coupons**:
   - Kupon feed → kártyák (logo, cím, CTA /go).
   - Ha nincs kuponkód, akkor „kupon jellegű” link is kezelhető.
   - Új hálózatnál a **reklámozói/advertiser lista** kerüljön be a kupon pipeline-ba (partnerlista + slug mapping), különben nem kerülnek be a kártyák.
2) **Deals**:
   - Banner/termék feed → kártyák.
   - Logo fallback (helyi logó cache).
   - CTA: /go?shop=<slug>&u=<deeplink>.
   - Új hálózat reklámozói itt is kötelezőek (partnerlista + slug mapping), különben nem jelennek meg a deals kártyák.

## 6) Tranzakciós ingest (commission)
1) **Pull/Push**:
   - Pull: cron (WP‑Cron/CLI).
   - Push: webhook (ha van).
2) **Status mapping**:
   - approved/locked → approved
   - pending/automated → pending
   - rejected/corrected → rejected (nem számoljuk)
3) **Időzítés**:
   - eventDate / postingDate / created_at alapján.
4) **Deviza**:
   - FX konverzió (rate cache, fallback).
5) **Dedup**:
   - `source_ref` alapján replace/upsert.
6) **Webhook queue** (ha van push):
   - Retry + dedup stratégia, hogy ne vesszen el tranzakció.

## 7) Ledger (egységes adatforrás)
1) **Táblázat**: `wp_impact_ledger` (pseudo_id, ngo_slug, shop_slug, amount_huf, status, happened_at, source_ref).
2) **Ingest szabály**:
   - D1 nélkül **nincs ingest**.
   - Numeric NGO kódok **kizárva** (nincs betű).
3) **Cron**:
   - `impactshop_<network>_ledger_cron` 30 percenként.
4) **Monitoring**:
   - `impactshop_<network>_ledger_last_stats` opciók.
5) **FX réteg**:
   - Egységes FX provider (cache + fallback), hogy multi‑currency bővítés ne törje meg az ingestet.

## 8) Aggregációk és API-k
**Minden nézet a ledgerből és/vagy a raw feedből számol.**
- **Sticky**: összesített adomány (commission * rate).
- **Leaderboard (NGO)**: NGO alapján aggregál.
- **Leaderboard (Shop)**: shop alapján aggregál.
- **Activity**: időrendben listáz.
- **Totals endpoint**: `/impactshop/v1/totals` (Dognet + új hálózat merge).
- **Versioning**: új mezőknél v2 terv (pl. `totals?v=2`).

## 9) Social ticker (megosztás)
1) **Alap**: ledgerből jön, fallback activity csak ha ledger üres/stale.
2) **Owner azonosítás**: pseudo match → `can_share=true`.
3) **NGO display**: slug → `ngo_codes.csv` alapján ékezetes név.
4) **Szűrés**: teszt NGO és numeric NGO kódok nem jelennek meg.
5) **Privacy**: pseudo minimalizált, csak hashed/maszkolt megjelenítés.

## 10) Cache és invalidálás
- Elementor cache: `wp elementor flush_css`.
- WP cache: `wp cache flush`.
- Transiensek: `impactshop_*` (ticker/leaderboard/activity).

## 11) QA forgatókönyv (részletes)
1) **Click**:
   - `/go?shop=<slug>&d1=<ngo>&u=<url>` → redirect OK.
   - Log: `impactshop-go-clicks.log` új sor.
2) **Tranzakció ingest**:
   - Cron futás: `wp cron event run impactshop_<network>_ledger_cron`.
   - Ledgerben sor megjelenik.
3) **NGO név**:
   - Ticker/leaderboard/activity név ékezetes.
4) **Shop megjelenítés**:
   - Netflix cards: logo + név + kategória OK.
   - Deals: kártya + CTA OK.
   - Coupons: kártya + CTA OK.
5) **Sticky + Leaderboard**:
   - Összeg egyezik.
6) **Social ticker**:
   - Pending is látszik (`status=all`).
   - Pseudo match esetén share gombok aktívak.
7) **Regression check**:
   - Dognet + CJ továbbra is működik (shopok, leaderboards, totals, activity).
8) **Performance**:
   - Rate limit és batch/szeletelés terhelés alatt.
   - Cache hit arány (Elementor + transiensek) stabil.
9) **Security**:
   - Paraméter validáció (sanitize), open redirect ellenőrzés.
   - Secret scan (kulcs ne legyen repo-ban).

## 12) Rollback terv
- Feature flag OFF (network disabled).
- Cron event unschedule.
- Ledgerből csak a network `source_ref` sorok törlése (ha szükséges).
- Cache flush.
- DB backup előtt törlés (ha tömeges clean).
- Stakeholder értesítés + post-mortem jegyzet.

## 13) Impactall hivatkozás
Ezt a dokumentumot az Impactall autoload blokk hivatkozza, hogy mindig kéznél legyen.

## 14) Hálózati sajátosságok (gyors checklist)
Az alábbi pontokat minden új hálózatnál ellenőrizd és dokumentáld.

### Awin
- **Click param**: `clickref` (NGO slug), `clickref2` vagy `custom` mező (pseudo).
- **Deeplink**: `clickref` megőrzése kötelező; ellenőrizd, hogy a deeplink átviszi‑e a paramokat.
- **Transactions**: `commissionStatus` mapping (approved/pending/declined).
- **Feedek**: coupons/ads/merchants CSV; logo URL gyakran külön oszlop.

### TradeTracker
- **Click param**: `r`/`t` tracking link + `cid`/`campaignID`; custom param `tt` vagy `data` mezőben NGO.
- **Deeplink**: `u` vagy `url` param; a platform gyakran saját shortenerrel dolgozik.
- **Transactions**: `transactionStatus` (pending/approved/declined) + `transactionID` dedup.
- **Feedek**: product/coupon feed külön endpoint; kategória mapping gyakran szükséges.

### Partnerize
- **Click param**: `clickref` + `clickref2` (pseudo) vagy `subid`.
- **Deeplink**: `deeplink` mező külön, ellenőrizd a param átadást.
- **Transactions**: `status` + `event_date`/`validation_date`; visszautasítások külön API-ban is jöhetnek.

### Impact (Radius)
- **Click param**: `subId1`/`subId2` (NGO/pseudo).
- **Deeplink**: `destination` param; a tracking domain kötelezően őrizze a subId-ket.
- **Transactions**: `actionStatus` mapping; payout külön tábla lehet.

### Webgains
- **Click param**: `subid`/`clickref` + `subid2` (pseudo).
- **Deeplink**: `wglink` + `deeplink` param; ellenőrizd a kettős param oldódást.
- **Transactions**: `status` + `eventDate`/`approvalDate`.

### Admitad
- **Click param**: `subid` + `subid2` (pseudo).
- **Deeplink**: `subid` átadása kötelező, különben nincs NGO.
- **Transactions**: többféle státusz (pending/approved/declined); gyakran net/hold.

### CJ (referencia)
- **Click param**: `sid` = `d1~pseudo`.
- **Deeplink**: `url` param.
- **Transactions**: GraphQL Commission Detail, `AUTOMATED` → pending.

### Dognet (referencia)
- **Click param**: `data1` (d1 = NGO slug), `data2` (pseudo).
- **Transactions**: raw transactions feed (`rstatus` A/P/D).

### Általános különbségek
- **Timezone**: több API UTC‑ben adja, de UI helyi időt vár; normalizáld.
- **Currency**: commission deviza, FX konverzió kötelező (cache + fallback).
- **Rate limit**: retry + backoff, batch/szeletelés (pl. 30 napos chunk).
- **Dedup**: mindig `source_ref` és `transaction_id` alapján.

## 15) Kupon Harvester integráció (ha van kupon feed)
- Feed parsing szabályai: `docs/coupon-harvester.md` szerint.
- Shop slug mapping: `tools/shops_registry.json` frissítés (új hálózat reklámozói/advertiser listája kötelező).
- Whitelist frissítés: coupon‑harvester config + domain lista.
- Output review: `tmp/manual_coupons_draft-YYYY-MM-DD.csv` → kézi review → import.
- Guard: impactall kupon smoke test futtatása.

## 16) Impi AI ajánlások (ha érinti az AI feedet)
- Shop registry frissítés: új shopok + kategóriák.
- Kategória normalizálás: csak ismert kategóriák.
- Deploy: `npm run build` + `rsync dist/` → `s59:/home/sharityh/ai-agent/dist/`.
- Restart: `bash ~/ai-agent/ai-agent-keepalive.sh`.
- Teszt: `/api/v1/chat/command` ajánlás ellenőrzés.

## 17) Sprint scope és dependency check
- Sprint hozzárendelés + dependency graph (impact-hub-system-v1.3).
- Feature flag policy (staging/prod külön).
- Sprint blocker esetén workaround + dokumentálás.

## 18) GDPR & CMP compliance
- CMP vendor lista bővítés (affiliate tracking cookie).
- Privacy policy frissítés (affiliate partner adatkezelés).
- Right to erasure: pseudo alapú ledger clean (audit loggal).

## 19) Observability & Telemetria
- API error rate + rate limit monitor.
- Affiliate click/conversion eventek (GA4/metrics).
- Dashboard panel + alert rule (hibaarány, null ingest).

## 20) Documentation & Stub Inventory
- OpenAPI/docs frissítés (ha új endpoint van).
- Stub inventory sync + doc lint.
- ADR rövid döntésnapló (miért így).

## 21) Security Testing (pre-production)
- OWASP checklist új endpointokra.
- Rate limiting és input sanitization ellenőrzés.
- Secrets scan + log PII review.

## 22) Phased integráció (irányadó)
**Phase 1 – Előkészítés:** 0–1, 17–18, 21.  
**Phase 2 – Core:** 2–7, 15–16.  
**Phase 3 – Élesítés:** 8–12, 19–20, 13.
