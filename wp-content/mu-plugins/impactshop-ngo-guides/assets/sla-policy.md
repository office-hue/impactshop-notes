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
