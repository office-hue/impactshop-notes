<!-- 🌐 Nyelv / Language: [Magyar](#hu) | [English](#en) -->

<div id="hu">

# 🇭🇺 MAGYAR VERZIÓ

# ImpactShop – Szolgáltatási Szintmegállapodás (SLA)

**Verzió:** 2.0  
**Hatályos:** 2026. január 15-től  
**Utolsó frissítés:** 2026-01-15  

---

## 1. Rendelkezésre állás

### 1.1 Uptime-célok

| Szolgáltatás | Cél (havi) | Mérés módja |
|---|---|---|
| Főoldal (impactshop.hu) | 99,5 % | Automatikus ellenőrzés – 5 percenként |
| /go átirányítás | 99,5 % | Automatikus ellenőrzés – 5 percenként |
| NGO Card REST API | 99,0 % | Automatikus ellenőrzés – 5 percenként |
| Wallet Pass letöltés | 98,5 % | Automatikus ellenőrzés – 15 percenként |
| Admin felület (wp-admin) | 98,0 % | Automatikus ellenőrzés – 15 percenként |

A mérés kizárja a tervezett karbantartási ablakot (lásd §1.2).

### 1.2 Karbantartási ablak

- **Rendszeres karbantartás:** kedd és csütörtök 03:00–05:00 CET.
- Az ablak alatt a szolgáltatás részlegesen vagy teljesen szünetelhet; ez nem számít kiesésnek.
- Sürgős biztonsági javítás esetén az ablak előrehozható – ilyenkor legalább 1 óra előzetes értesítést küldünk.
- **Egyedi megállapodás:** rendezvény vagy kampány esetén — külön megállapodás szerinti időtartamra — a karbantartási ablak korlátozható vagy kizárható.

---

## 2. Incidenskezelés

### 2.1 Súlyossági besorolás

| Szint | Megnevezés | Leírás | Reakcióidő | Megoldási cél |
|---|---|---|---|---|
| S1 | Kritikus | Teljes szolgáltatáskiesés, /go nem működik | 15 perc | 1 óra |
| S2 | Súlyos | NGO Card API hibás, Wallet Pass nem tölthető le | 30 perc | 4 óra |
| S3 | Közepes | Admin felület lassú, cache-inkonzisztencia | 2 óra | 24 óra |
| S4 | Alacsony | Kozmetikai hiba, dokumentációs eltérés | 1 munkanap | 5 munkanap |

### 2.2 Munkaidő és ügyeleti rend

- **Munkaidő:** H–P 08:00–18:00 CET.
- Munkaidőn kívül automatikus monitoring figyeli a szolgáltatásokat; S1 incidens esetén riasztás.
- Hétvégén és ünnepnapokon kizárólag S1 incidensekre reagálunk.

### 2.3 Riasztási lánc

1. Automatikus monitoring észleli a hibát (5 perces ciklus).
2. Riasztás a Discord üzemeltetési csatornán + e-mail az ügyeletesnek.
3. S1/S2 esetén azonnali eszkaláció a rendszergazdához.
4. Cloudflare WAF és CDN automatikus védekezés aktiválódik (pl. Under Attack Mode).

### 2.4 Eszkaláció

| Lépés | Időkorlát | Felelős |
|---|---|---|
| 1. Automatikus riasztás | 0 perc | Monitoring rendszer |
| 2. Üzemeltető reagál | +15 perc (S1) / +30 perc (S2) | Üzemeltető |
| 3. Tárhelyszolgáltató support | +1 óra | Tárhelyszolgáltató |
| 4. Vezetői értesítés | +2 óra | Üzemeltető → Vezető |

---

## 3. Vészhelyzeti eljárásrend

### 3.1 Teljes kiesés (S1)

1. Cloudflare Under Attack Mode aktiválása (ha támadás okozza).
2. Szolgáltatás állapotfelmérés (automatikus health-check eredmények áttekintése).
3. Ha szükséges: MU plugin letiltás, cache ürítés, szerver újraindítás kérése.
4. Visszaállítás legutóbbi stabil állapotból (biztonsági mentés).
5. Utólagos elemzés és dokumentálás.

### 3.2 Adatvédelmi incidens

1. Érintett szolgáltatás azonnali leválasztása.
2. GDPR incidenskezelési protokoll indítása (72 órás NAIH bejelentési kötelezettség).
3. Érintettek értesítése az ÁSZF §16 szerint.
4. Utólagos biztonsági audit.

### 3.3 Affiliate partner API kiesés (Dognet, stb.)

1. Automatikus fallback: cache-ből szolgáltatás, hibaoldal megjelenítése.
2. Partner API állapot ellenőrzése.
3. Értesítés az érintett NGO partnereknek, ha a kiesés > 4 óra.

---

## 4. Kommunikáció

### 4.1 Hibabejelentés

Hibabejelentés az alábbi elérhetőségeken lehetséges:

- **E-mail:** office@sharity.hu
- **Telefon:** +36 30 400 7470
- **Elérhetőség:** H–P 08:00–18:00 CET

Bejelentéskor kérjük megadni: a hiba leírását, az érintett URL-t vagy funkciót, és a hiba észlelésének időpontját.

### 4.2 NGO partnerek felé

- Kiesés > 30 perc esetén proaktív tájékoztatás e-mailben.
- Negyedéves elszámolási ütemezés és kifizetési szabályok az ÁSZF §4.6.3 szerint.
- Karbantartási ablak változásáról legalább 48 óra előzetes értesítés.

### 4.3 Felhasználók felé

- Kiesés esetén egyedi hibaoldal jelenik meg a szolgáltatás állapotáról.
- Hosszabb kiesés (> 2 óra) esetén közösségi média tájékoztatás.

### 4.4 Partneri SLA vitakezelés

A szolgáltatási szinttel kapcsolatos reklamációk kezelése az ÁSZF §15 (Panaszkezelés) szerint történik:

- **Első visszajelzés:** legkésőbb 2 munkanapon belül.
- **Érdemi döntés:** legkésőbb 5 munkanapon belül.

---

## Kapcsolódó dokumentumok és guide-ok

- [Általános Szerződési Feltételek (ÁSZF)](../ÁSZF/Sharity_ASZF_2026.md)
- [Vitakezelési eljárásrend](./dispute-handling-procedure.md)
- [Hozzáférés-kezelési mátrix](./access-control-matrix.md)
- [GDPR adatfeldolgozók](./gdpr-data-processors.md)
- [Impact Challenge útmutató](https://app.sharity.hu/ngo-guides/impact-challenge/)
- [Cégeknek](https://app.sharity.hu/cegeknek/)
- Amennyiben a felhasználó vagy partner a döntéssel nem ért egyet, az ÁSZF §20.2 szerinti jogvitarendezési eljárás alkalmazandó.

---

## 5. Monitoring és mérés

### 5.1 Uptime mérés

- Automatikus health-check 5 percenként (főoldal, /go, NGO Card API).
- Wallet Pass és admin felület: 15 perces ciklus.
- A mért adatok alapján havonta összesítő riport készül.

### 5.2 Teljesítménymutatók

| Mutató | Cél |
|---|---|
| Főoldal betöltés (TTFB) | < 800 ms |
| /go átirányítás | < 300 ms |
| NGO Card API válaszidő | < 500 ms |
| Wallet Pass letöltés | < 2 s |

---

## 6. Kereszthivatkozás

A jelen SLA az ÁSZF §10 (A szolgáltatás szüneteltetése, korlátozása) rendelkezéseire épül, és azzal összhangban értelmezendő. Az ÁSZF és az SLA közötti eltérés esetén az ÁSZF rendelkezései az irányadók.

---

## Változásnapló

| Verzió | Dátum | Változás |
|---|---|---|
| 1.0 | 2025-11-15 | Első kiadás |
| 2.0 | 2026-01-15 | Véglegesítés: uptime-táblázat egyszerűsítés, karbantartási ablak egyedi megállapodás lehetőség, hibabejelentési elérhetőségek hozzáadása, belső hivatkozások eltávolítása, partneri vitakezelés szabályozása, Cloudflare és Discord integráció megerősítése |

</div>

---

<div id="en">

# 🇬🇧 ENGLISH VERSION

# ImpactShop – Service Level Agreement (SLA)

**Version:** 2.0  
**Effective:** from 15 January 2026  
**Last updated:** 2026-01-15  

---

## 1. Availability

### 1.1 Uptime Targets

| Service | Target (monthly) | Measurement method |
|---|---|---|
| Homepage (impactshop.hu) | 99.5% | Automated check – every 5 minutes |
| /go redirect | 99.5% | Automated check – every 5 minutes |
| NGO Card REST API | 99.0% | Automated check – every 5 minutes |
| Wallet Pass download | 98.5% | Automated check – every 15 minutes |
| Admin panel (wp-admin) | 98.0% | Automated check – every 15 minutes |

Measurements exclude the scheduled Maintenance Window (see §1.2).

### 1.2 Maintenance Window

- **Scheduled maintenance:** Tuesday and Thursday 03:00–05:00 CET.
- During the window, the service may be partially or fully unavailable; this does not count as downtime.
- In case of an urgent security fix, the window may be moved forward — in such cases we will provide at least 1 hour advance notice.
- **Custom agreement:** for events or campaigns — for a period as agreed separately — the Maintenance Window may be restricted or excluded.

---

## 2. Incident Management

### 2.1 Severity Classification

| Level | Name | Description | Response time | Resolution target |
|---|---|---|---|---|
| S1 | Critical | Complete service outage, /go not functioning | 15 minutes | 1 hour |
| S2 | Major | NGO Card API faulty, Wallet Pass not downloadable | 30 minutes | 4 hours |
| S3 | Medium | Admin panel slow, cache inconsistency | 2 hours | 24 hours |
| S4 | Low | Cosmetic issue, documentation discrepancy | 1 business day | 5 business days |

### 2.2 Business Hours and On-Call Schedule

- **Business hours:** Mon–Fri 08:00–18:00 CET.
- Outside business hours, automated monitoring watches the services; alerts are triggered for S1 incidents.
- On weekends and public holidays, we respond only to S1 incidents.

### 2.3 Alert Chain

1. Automated monitoring detects the issue (5-minute cycle).
2. Alert on the Discord operations channel + email to the on-call engineer.
3. For S1/S2, immediate escalation to the system administrator.
4. Cloudflare WAF and CDN automatic defenses are activated (e.g. Under Attack Mode).

### 2.4 Escalation

| Step | Time limit | Responsible |
|---|---|---|
| 1. Automated alert | 0 minutes | Monitoring system |
| 2. Operator responds | +15 min (S1) / +30 min (S2) | Operator |
| 3. Hosting provider support | +1 hour | Hosting provider |
| 4. Management notification | +2 hours | Operator → Management |

---

## 3. Emergency Procedures

### 3.1 Complete Outage (S1)

1. Activate Cloudflare Under Attack Mode (if caused by an attack).
2. Service status assessment (review automated health-check results).
3. If necessary: disable MU plugin, clear cache, request server restart.
4. Restore from the most recent stable state (backup).
5. Post-incident analysis and documentation.

### 3.2 Data Protection Incident

1. Immediate isolation of the affected service.
2. Initiation of the GDPR incident management protocol (72-hour NAIH notification obligation).
3. Notification of affected individuals pursuant to GTC §16.
4. Post-incident security audit.

### 3.3 Affiliate Partner API Outage (Dognet, etc.)

1. Automatic fallback: serve from cache, display error page.
2. Check partner API status.
3. Notify affected NGO partners if the outage exceeds 4 hours.

---

## 4. Communication

### 4.1 Issue Reporting

Issues can be reported through the following channels:

- **E-mail:** office@sharity.hu
- **Phone:** +36 30 400 7470
- **Availability:** Mon–Fri 08:00–18:00 CET

When reporting, please provide: a description of the issue, the affected URL or feature, and the time the issue was detected.

### 4.2 Towards NGO Partners

- Proactive notification via email for outages exceeding 30 minutes.
- Quarterly settlement schedule and payout rules pursuant to GTC §4.6.3.
- At least 48 hours advance notice of Maintenance Window changes.

### 4.3 Towards Users

- In case of an outage, a custom error page is displayed with the service status.
- For extended outages (> 2 hours), social media notification.

### 4.4 Partner SLA Dispute Resolution

Complaints related to service levels are handled pursuant to GTC §15 (Complaint Handling):

- **Initial response:** within 2 business days at the latest.
- **Substantive decision:** within 5 business days at the latest.

---

## Related Documents and Guides

- [General Terms and Conditions (GTC)](../ÁSZF/Sharity_ASZF_2026.md)
- [Dispute Handling Procedure](./dispute-handling-procedure.md)
- [Access Control Matrix](./access-control-matrix.md)
- [GDPR Data Processors](./gdpr-data-processors.md)
- [Impact Challenge Guide](https://app.sharity.hu/ngo-guides/impact-challenge/)
- [For Companies](https://app.sharity.hu/cegeknek/)
- If the user or partner disagrees with the decision, the dispute resolution procedure under GTC §20.2 shall apply.

---

## 5. Monitoring and Measurement

### 5.1 Uptime Measurement

- Automated health-check every 5 minutes (homepage, /go, NGO Card API).
- Wallet Pass and admin panel: 15-minute cycle.
- Monthly summary reports are prepared based on the measured data.

### 5.2 Performance Metrics

| Metric | Target |
|---|---|
| Homepage load (TTFB) | < 800 ms |
| /go redirect | < 300 ms |
| NGO Card API response time | < 500 ms |
| Wallet Pass download | < 2 s |

---

## 6. Cross-Reference

This SLA is based on the provisions of GTC §10 (Suspension and Restriction of the Service) and shall be interpreted in conjunction therewith. In case of discrepancy between the GTC and the SLA, the provisions of the GTC shall prevail.

---

## Changelog

| Version | Date | Change |
|---|---|---|
| 1.0 | 2025-11-15 | First edition |
| 2.0 | 2026-01-15 | Finalization: uptime table simplification, custom Maintenance Window agreement option, addition of issue reporting contacts, removal of internal references, partner dispute resolution regulation, Cloudflare and Discord integration reinforcement |

</div>
