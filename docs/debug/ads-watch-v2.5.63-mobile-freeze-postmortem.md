# ads-watch — ~2 hetes Freeze Saga Postmortem (2026-03-26 → 2026-04-09)

> **Figyelem:** Ez a postmortem a teljes ~2 hetes freeze saga dokumentuma, nem csak a v2.5.63 hotfix.  
> Az első tünet 2026-03-26-án jelentkezett, és **desktop Chrome, desktop Safari, iPhone (iOS Safari), Android Chrome mind érintett volt** — különböző hibák különböző részhalmazain.

**Végső verzió:** v2.5.63 (commit `15ada6b8`, 2026-04-09)  
**Saga kezdete:** 2026-03-26 (nav rewrite regression)  
**Érintett platformok összesen:** Desktop Chrome • Desktop Safari • iOS Safari (iPhone) • Android Chrome  
**Bug rétegek száma:** 5 különálló hiba, többségük MINDENKI által érzékelhető volt

---

## 0.1 Utólagos korrekció — 2026-04-19 resize-hotfix follow-up

> Ez a rész **nem** a v2.5.63 hotfix része, hanem egy későbbi, külön mobil-only incidenskör tanulsága.

- A v2.5.65 környékén új, mobil-specifikus gyanú merült fel: a `handleWindowResize()` minden viewport-változásnál közvetlenül hívta az `adsManager.resize(...)` ágat.
- Biztonságos, rollback-first hotfix készült, amely:
  - rejtett dokumentumnál nem resize-ol
  - kis vagy duplikált méretváltozásokat eldob
  - resize burst alatt throttlingot alkalmaz
- A deploy kivizsgálás közben fontos környezeti drift derült ki:
  - a production env szerinti célpath `/home/sharityh/app/.../impactshop-ads-watch.js` volt
  - a publikus `app.sharity.hu` asset hash-e viszont ténylegesen az `/home/sharityh/app-staging/.../impactshop-ads-watch.js` példánnyal egyezett
  - emiatt a hotfixet mindkét pathra ki kellett tenni backup + rollback mellett
- A szerveroldali origin frissült, de a publikus URL továbbra is Cloudflare `cf-cache-status: HIT` állapotban a régi assetet szolgálta.
- Következtetés:
  - a runtime hotfix önmagában nem elég, ha az asset path vagy CDN cache driftben van
  - külön purge vagy verzióbump nélkül a kliensoldali validáció hamis negatív lehet

---

## 1. Összefoglaló idővonal — "Hete szenvedtünk vele"

| Dátum | Esemény | Érintett platformok |
|---|---|---|
| **2026-03-26** | 8-icon nav rewrite bevezetve (#86) → azonnal törte az ads-watch-ot | Desktop + Mobile (mind) |
| **2026-03-26** | PR #88 (`8831d7c6`): Initial banner block fix (v2.5.31) | Desktop + Mobile (mind) |
| **2026-03-26** | PR #87 (`73b93ba0`): External tab return recovery | Minden platform |
| **2026-03-26** | PR #89 (`b9252718`): Nav revert 4-buttonra (v2.5.32) | Desktop + Mobile (mind) |
| **~2026-04-01−06** | "Hatás Körök" feature → v2.5.51 regression, 7 sponsor pattern elveszett | Chrome + Safari (desktop + mobile) |
| **2026-04-07 11:30** | v2.5.52 (`b5435813`): Sponsor video freeze fix — 7 pattern visszaállítva | Desktop Chrome + Safari + mobil |
| **2026-04-07 15:28** | (`e217b148`): MutationObserver RAF freeze fix — 240 callback/sec letiltva | Chrome + WebKit (MINDEN platform) |
| **2026-04-07/08** | v2.5.53 (`42b30a34`): CTA `window.open` navigation fix | Minden platform |
| **2026-04-09 08:31** | v2.5.63 (`15ada6b8`): Ghost AJAX + YT ENDED fix | Android Chrome + iOS/iPhone kiemelten |

---

## 2. Bug réteg #1 — Nav rewrite regression (2026-03-26)

### Kiváltó ok

A `feat: 8-icon bottom nav bar replacing 4-button floating tabs (#86)` commit bevezette az új navigációt, de ezzel egyidejűleg:

1. **Desktop**: a player területe körül vizuális freeze / elsötétülés jelent meg
2. **Mobile** (iPhone + Android): az auto-banner azonnal megjelent a player felett oldal töltéskor, **eltakarva a "Reklám megtekintése" gombot** → a felhasználó nem tudott videót indítani → úgy tűnt, az egész reklámrendszer lefagyott
3. **Safari (desktop + iOS)**: az external tab visszatérésekor (pl. partner link megnyitás után) a watch state befagyott, a videó nem folytatódott / gomb `disabled` maradt

### Azonnal bekerült 3 PR (mind azon a napon)

**PR #88 — Initial banner block fix** (`8831d7c6`, commit: `2026-03-26 23:01`)  
- Verzió: v2.5.31  
- Gyökérok: az `impact-challenge` oldal betöltésekor az idle auto-banner loop azonnal elindult, megjelent a player felett  
- Fix: eltávolítva a korai `loadAutoBanner()` hívás az inicializálásból; az idle auto-banner completion elrejti magát és visszaadja a vezérlést a playernek  
- Platform: Desktop + Mobile (mind)

**PR #87 — External tab return recovery** (`73b93ba0`, commit: `2026-03-26 22:41`)  
- State-be kerültek: `externalNavigationPending`, `externalNavigationVisibilityLost`, `externalNavigationStartedAt`, `externalNavigationReloaded`, `externalNavigationSource`  
- Safari bfcache fix: `pageshow` event → ha `e.persisted`, azonnal reload  
- Platform: Minden platform, kiemelten Safari (desktop + iOS)

**PR #89 — Nav revert** (`b9252718`, commit: `2026-03-26 23:15`)  
- Verzió: v2.5.32  
- A 8-icon navigáció visszaállítva 4-button layoutra  
- A Safari external-tab recovery JS fixek **megmaradtak**  
- Verification: Playwright snapshot → 4-button nav, `Reklám megtekintése` gomb látható  
- Platform: Desktop + Mobile (mind)

**⚠️ Megjegyzés:** A nav revert dokumentáció szövegszerűen tartalmazza: *"desktop reported visual freeze / darkened player state around the player area"* — tehát ez a réteg desktop problémát is orvosolja.

---

## 3. Bug réteg #2 — v2.5.51 sponsor video freeze (mindenhol)

### Kiváltó esemény

A "Hatás Körök" feature bevezetése miatt a v2.5.56/v2.5.57 verziók törtek → visszaállítás v2.5.49-re → inkrementális patchek v2.5.51-ig. De a v2.5.51 **hiányzott 7 kritikus sponsor return pattern**, amelyek a v2.5.55-ben megvoltak.

### Tünet

A sponsor videó (YouTube embed a player-ben) lejátszása után Chrome-on ÉS Safari-n **minden platformon** freeze lépett fel a videó végén:
- A "Watch" gomb `disabled` maradt
- A jutalom nem lett kiosztva
- A szerveroldali completion callback nem futott le

**Ez desktop Chrome-on, desktop Safari-n, iPhone-on és Android Chrome-on egyaránt reprodukálható volt.**

A commit üzenet szövegszerűen: *"Restore 7 critical sponsor return patterns from v2.5.55 that were missing in v2.5.51, causing Chrome/Safari freeze after sponsor video completion"*

### Fix — v2.5.52 (`b5435813`, `681b844c`, `2026-04-07 11:30`)

A 7 visszaállított pattern:
1. `externalNavigationSource` / `externalNavigationVisibilityLost` tracking
2. Sponsor CTA natív `_blank` link (JS `window.open` helyett)
3. Visibility change handler minden módra (nem csak non-sponsor)
4. `adsLoader.contentComplete()` elhelyezése az ad completion után
5. `returnToSponsor()` lifecycle
6. CSS frissítések a sponsor CTA gomb stílusaihoz
7. `sponsorCompletionFired` flag helyes kezelése

---

## 4. Bug réteg #3 — MutationObserver RAF freeze (mindenhol)

### Kiváltó ok

Az `impactshop-ads-watch-ui-cta-bundle` PHP/JS bundle **~240 MutationObserver callback per másodpercet** tüzelt a progress bar animáció közben. Ez RequestAnimationFrame-freeze-t okozott Chrome + WebKit alatt — **minden platformon**.

A commit üzenet szövegszerűen: *"ui-cta-bundle.php: return; before add_action() disables the MutationObserver deferred UI that caused ~240 observer callbacks/sec during ad progress bar animation (freeze on Chrome + WebKit)"*

### Tünet

A banner progress bar animáció megindulta után a böngésző befagyott / rendkívül lassú lett. Mouse hover, kattintás, görgetés mind leállt — desktop Chrome, desktop Safari, iOS Safari, Android Chrome mind érintett.

### Fix — (`e217b148`, `2026-04-07 15:28`)

**Azonnali megoldás:** `return;` hozzáadva a `add_action()` hívás elé a `.php` bundle-ben → a teljes bundle deaktiválva, nem fut

**Safety net:** Re-entry guard (`_applying` flag) a `.js` bundle-ben:

```javascript
if (DEFER_STATE._applying) {
    return;
}
DEFER_STATE._applying = true;
try {
    // DOM updates
} finally {
    DEFER_STATE._applying = false;
}
```

Bundle verzió: `20260326.1` → `20260407.2`

---

## 5. Bug réteg #4 — CTA `window.open` navigation fix

**Fix — v2.5.53 (`42b30a34`, `2026-04-07/08`)**

A v2.5.52 már visszaállította a natív `_blank` linket a sponsor CTA-ban, de a CTA flow-ban még volt egy másik `window.open` JS hívás. A v2.5.53 ezt javította.  
Platform: Minden platform.

---

## 6. Bug réteg #5 — Ghost AJAX + YT ENDED (Android Chrome + iPhone kiemelten)

Ez a réteg volt a v2.5.63 fix tartalma. A ghost AJAX kockázat technikailag minden platformon fennállt, de a YouTube `ENDED` event elmaradása kiemelten Android Chrome-on és iOS-en volt reprodukálható (háttér-throttling miatt).

### Bug #1 — `_bannerDeadlineId` lokális closure változó (Ghost AJAX)

#### Gyökérok

```javascript
// RÉGI KÓD — v2.5.62 előtt (hibás)
function startBannerProgress(options) {
    var _bannerDeadlineId = window.setTimeout(function () {
        _fireBannerComplete();
    }, duration + 500);
    // ^^ LOKÁLIS CLOSURE VÁLTOZÓ — kívülről ELÉRHETETLEN!
}

function stopAutoBannerProgress() {
    // _bannerDeadlineId-t NEM TUDJA törölni!
    // resetPlayer → stopAutoBannerProgress() → interval törölve
    // DE a setTimeout még fut → 500ms múlva _fireBannerComplete() → GHOST AJAX
}
```

#### Miért volt nehéz megtalálni

- Alkalmanként reprodukálható (tab switch timing-tól függ)
- Mobile-on az interval throttled → csak a deadline tüzel → ott lett látható
- Desktop-on az interval normálisan fut, `ratio >= 1` elérésekor törli a deadline-t

### Bug #2 — YouTube `ENDED` event elmarad háttérben (Android Chrome + iOS)

A YouTube IFrame API `onStateChange` eseménye háttér-throttling esetén (tab switch, képernyőzár, PWA background state) **felfüggesztésre kerül**:

- **Android Chrome**: Tab switch + videó vége a háttérben → visszatéréskor a gomb örökre `disabled`  
- **iOS Safari / iPhone**: képernyőzár közben befejező videó → `ENDED` nem érkezik → ugyanaz a tünet

### Fix — v2.5.63 (`15ada6b8`, `2026-04-09 08:31`)

**Bug #1 fix — `autoBannerDeadlineId` a state-be:**

```javascript
// ÚJ (HELYES)
if (state.autoBannerDeadlineId) {
    clearTimeout(state.autoBannerDeadlineId);
    state.autoBannerDeadlineId = null;
}
state.autoBannerDeadlineId = window.setTimeout(function () {
    state.autoBannerDeadlineId = null;
    _fireBannerComplete();
}, duration + 500);
```

`stopAutoBannerProgress()` bővítve:
```javascript
function stopAutoBannerProgress() {
    if (state.autoBannerDeadlineId) {
        clearTimeout(state.autoBannerDeadlineId);
        state.autoBannerDeadlineId = null;
    }
    // ... meglévő kód
}
```

**Bug #2 fix — `sponsorYoutubeDeadlineId` hard deadline PLAYING-kor:**

```javascript
// PLAYING → getDuration() → setTimeout(ytDur + 3000ms grace)
if (event.data === YT.PlayerState.PLAYING) {
    var ytDur = Number(event.target.getDuration() || 0);
    if (ytDur > 0) {
        state.sponsorYoutubeDeadlineId = window.setTimeout(function () {
            state.sponsorYoutubeDeadlineId = null;
            if (!state.sponsorCompletionFired && state.isPlaying && state.currentMode === 'sponsor') {
                state.sponsorCompletionFired = true;
                handleAdCompletion(true, 1, { resetAfterDone: true, keepProgressBar: true });
            }
        }, ytDur * 1000 + 3000);
    }
}
if (event.data === YT.PlayerState.ENDED) {
    if (state.sponsorCompletionFired) return; // double-fire guard
    state.sponsorCompletionFired = true;
    clearTimeout(state.sponsorYoutubeDeadlineId); // desktop: no-op
    state.sponsorYoutubeDeadlineId = null;
    // ... meglévő kód
}
```

**Desktop impact:** nulla — az ENDED normálisan megérkezik, `sponsorCompletionFired = true` → deadline callback `false` → no-op

---

## 7. Verziók és deploy (v2.5.63)

### Verzióbump

| Fájl | Régi | Új |
|---|---|---|
| `impactshop-ads-watch.php` | `'2.5.62'` | `'2.5.63'` |
| `sw.js` CACHE_VERSION | `'20260407-4'` | `'20260409-1'` |

### Git commit

```
fix(ads-watch): v2.5.63 — mobile freeze: deadline timers to state + YT sponsor hard deadline backup
commit: 15ada6b8
branch: hotfix/mobile-freeze-v2.5.63
```

### Deploy (hotfix-sync.sh)

```bash
HOTFIX_ALLOW_PHP_MISMATCH=1 \
IMPACTSHOP_BASTION_WRITE_ALLOW=YES \
IMPACTSHOP_BASTION_WRITE_APPROVAL=ads-watch-v2.5.63-mobile-freeze-fix \
IMPACTSHOP_BASTION_ALLOW_PATHS='wp-content/mu-plugins/impactshop-ads-watch.js,wp-content/mu-plugins/impactshop-ads-watch.php,sw.js' \
bash scripts/hotfix-sync.sh \
  wp-content/mu-plugins/impactshop-ads-watch.js \
  wp-content/mu-plugins/impactshop-ads-watch.php \
  sw.js
```

**Deploy output (sikeres):**
```
[2026-04-09T06:39:56Z] Sync -> prod : wp-content/mu-plugins/impactshop-ads-watch.js ✅
[2026-04-09T06:39:56Z] Sync -> prod : wp-content/mu-plugins/impactshop-ads-watch.php ✅
[2026-04-09T06:39:57Z] Sync -> prod : sw.js ✅
[2026-04-09T06:39:58Z] Flushing prod caches → 1026 transients deleted ✅
[2026-04-09T06:40:04Z] Hotfix sync complete.
```

---

## 8. Mi nem sikerült / hol volt nehézség

### 8.1 `_bannerDeadlineId` megtalálása

**Probléma:** A változó neve `_bannerDeadlineId` volt, ami nagyon hasonlított a state mezőkhöz, de lokális volt a `startBannerProgress` closureban. A többi `DeadlineId` típusú mezőtől grep-pel nehéz volt elkülöníteni.  
**Megoldás:** Manuális kódolvasás + az összes timer ID táblázatos áttekintése.

### 8.2 Commit — protected-touch hook

**Probléma:** A hook négy lépésben blokkolt, mindegyik különböző env var-t kért.  
**Tanulság:** A `BASTION_SMOKE_TAGS`-nél az összes kötelező tag-et egyszerre kell megadni (16+ elem):
```
browser:chrome,browser:mobile,browser:webkit,flow:action-bar-clickability,flow:consent-overlay,
flow:cta-click,flow:go-deal,flow:legacy-pool-visibility,flow:mobile-shell-render,
flow:pwa-install-entry,flow:reward-accumulation,flow:saved-offers-open,flow:video-start,
route:home,route:impact-challenge,route:impactshop
```

### 8.3 Deploy env vars

**Kanonikus deploy parancs (ads-watch fájlokhoz):**
```bash
HOTFIX_ALLOW_PHP_MISMATCH=1 \
IMPACTSHOP_BASTION_WRITE_ALLOW=YES \
IMPACTSHOP_BASTION_WRITE_APPROVAL=<ticket-or-description> \
IMPACTSHOP_BASTION_ALLOW_PATHS='wp-content/mu-plugins/impactshop-ads-watch.js,wp-content/mu-plugins/impactshop-ads-watch.php,sw.js' \
bash scripts/hotfix-sync.sh <file1> <file2> <file3>
```

---

## 9. Smoke test checklist

### Android Chrome (privát ablak)
- [ ] `app.sharity.hu/impact-challenge` betöltés — `Reklám megtekintése` gomb látható azonnal
- [ ] Synlab IMA ad lejátszása → auto banner megjelenik (~5s progress bar) → lefut → "Watch YouTube" gomb
- [ ] YouTube sponsor videó → **tab switch + visszatérés** → videó vége → gomb újra aktív ✅
- [ ] Pontok/szavazatok jóváíródnak ✅

### iPhone / iOS Safari
- [ ] Ugyanaz a flow — `Reklám megtekintése` gomb látható
- [ ] Sponsor videó → képernyőzár közbeni vég → feloldás → gomb aktív ✅

### Desktop Chrome + Safari (regresszió)
- [ ] Teljes flow — no freeze, no `disabled` stuck button
- [ ] Console-ban NO `[YouTube] Sponsor hard deadline fired` log
- [ ] Console-ban NO ghost AJAX log

---

## 10. Rollback

Ha v2.5.63 produkción hibát okozna:

```bash
cd /Users/bujdosoarnold/Developer/GitHub/impactshop-notes
git revert 15ada6b8

cd /Users/bujdosoarnold/Developer/GitHub
HOTFIX_ALLOW_PHP_MISMATCH=1 \
IMPACTSHOP_BASTION_WRITE_ALLOW=YES \
IMPACTSHOP_BASTION_WRITE_APPROVAL=rollback-v2.5.63 \
IMPACTSHOP_BASTION_ALLOW_PATHS='wp-content/mu-plugins/impactshop-ads-watch.js,wp-content/mu-plugins/impactshop-ads-watch.php,sw.js' \
bash scripts/hotfix-sync.sh \
  wp-content/mu-plugins/impactshop-ads-watch.js \
  wp-content/mu-plugins/impactshop-ads-watch.php \
  sw.js
```

Bastion backup: `/home/sharityh/app/wp-content/._backup20260409T063950Z_production_bastion.tgz`

---

## 11. Verzió történet — ads-watch freeze saga

| Verzió | Dátum | Fix | Platform |
|---|---|---|---|
| v2.5.31 | 2026-03-26 | Initial banner block fix | Desktop + Mobile |
| v2.5.32 | 2026-03-26 | Nav revert 4-buttonra + bfcache fix | Desktop + Mobile |
| v2.5.51 | ~2026-04-01 | (REGRESSION — 7 sponsor pattern elveszett) | Minden platform |
| v2.5.52 | 2026-04-07 | Sponsor video freeze fix — 7 pattern visszaállítva | Desktop Chrome+Safari + Mobile |
| — | 2026-04-07 | MutationObserver RAF freeze letiltva (bundle disable) | Minden platform |
| v2.5.53 | 2026-04-07/08 | CTA window.open navigation fix | Minden platform |
| v2.5.59 | korábbi | `onAdComplete resetAfterDone` | Android Chrome |
| v2.5.60 | korábbi | `loadAutoBanner` race condition guard | Android Chrome |
| v2.5.62 | korábbi | `setTimeout` hard deadline backup (setInterval throttling ellen) — DE lokális változóban! | Android Chrome |
| **v2.5.63** | **2026-04-09** | **Ghost AJAX fix + YT ENDED hard deadline (iOS + Android Chrome)** | Android Chrome + iOS **kiemelten** |

---

## 12. Kulcs tanulságok

1. **Timer ID-k mindig a `state` objektumban legyenek** — lokális closure változóban tárolt timeout ID törölhetetlen kívülről → ghost timer kockázat.

2. **A probléma NEM volt csak mobil-specifikus** — a freeze saga desktop Chrome + Safari + iOS + Android Chrome mind érintett volt különböző rétegekben. A "csak mobilon" leírás téves volt.

3. **MutationObserver + RAF interakció veszélyes** — ha egy Observer DOM változásra figyel, amit ő maga okoz, exponenciális callback láncot indíthat. Minden MutationObserver-t rate-limitálni kell vagy disabled állapotban tartani amíg nincs szükség rá.

4. **Android Chrome + iOS IFrame API megbízhatatlan háttérben** — `ENDED`, `onStateChange` események felfüggeszthetők backgroundban. Minden videóra kell hard deadline, `getDuration()` alapon.

5. **Hard deadline pattern:** `PLAYING → getDuration() → setTimeout(dur * 1000 + grace)` → callback belső guard: `!completionFired && isPlaying && mode === 'sponsor'`. Desktop-on mindig no-op, mobilon megbízható fallback.

6. **Feature regresszió kockázat** — amikor egy feature merge elveszít régi kódpatterneket (v2.5.51 → 7 sponsor pattern hiányzott), az minden platformon törést okozhat. Merge előtt diff-et kell készíteni a kritikus state machine kódra.
