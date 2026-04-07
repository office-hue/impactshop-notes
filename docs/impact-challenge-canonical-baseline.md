# Impact Challenge Canonical Baseline

Ez a dokumentum a teljes Impact Challenge rendszer kanonikus védelmi és működési alapja 2026-03-26-tól. Ha ettől a baseline-tól bármely route, szöveg, bekötés, adatút, workflow vagy fizikai jogosultsági állapot eltér, azt regressziónak kell tekinteni, amíg az eltérésre nincs külön, explicit jóváhagyás.

## Kanonikus scope

Az Impact Challenge védett pereme kiterjed:

- ads-watch runtime és UI
- auto-banner ingest, rotáció, redirect és shopfeloldás
- offerwall és survey/article wall
- identity, pont- és szavazatmotor, leaderboard
- vote purchase és quarter-close kapcsolódási pontok
- NGO selector, NGO card és a teljes NGO guide rendszer
- go / go-deal / click-tracking / affiliate glue
- PWA shell és push kapcsolatok
- minden ezekhez tartozó route, REST bekötés, workflow glue, adatírási mód és deploy/push/PR gate

## Kanonikus működési szabály

- Az alapértelmezett fejlesztési stratégia: `new code first`.
- Meglévő védett Impact Challenge kód csak külön, explicit felhasználói engedéllyel módosítható.
- Legacy touch csak akkor megengedett, ha nincs azonos minőségű additív megoldás.
- Nem lehet olyan deploy, automatika, sync vagy “helyreállítás”, amely hallgatólagosan átírja a védett Impact Challenge kódot vagy guide tartalmat.

## Fizikai célállapot

### Lokál

- A fő Impact Challenge runtime MU-plugin fájlok read-only célállapota: `0444`
- A guide router célállapota: `0444`
- A guide könyvtárak célállapota: `0555`
- A guide fájlok célállapota: `0444`

### Production

- A fő Impact Challenge runtime MU-plugin fájlok read-only célállapota: `0444`
- A guide router célállapota: `0444`
- A guide könyvtárak célállapota: `0555`
- A guide fájlok célállapota: `0444`

## Kanonikus védett runtime fájlok

### Ads Watch

- `wp-content/mu-plugins/impactshop-ads-watch.php`
- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-ads-watch.css`
- `wp-content/mu-plugins/impactshop-ads-watch-quarter.php`

### Autobanner / Affiliate glue

- `wp-content/mu-plugins/impactshop-auto-banner.php`
- `wp-content/mu-plugins/impactshop-auto-banner-sync.php`
- `wp-content/mu-plugins/impactshop-auto-banner-dognet.php`
- `wp-content/mu-plugins/impactshop-boot.php`
- `wp-content/mu-plugins/impactshop-cj.php`
- `wp-content/mu-plugins/impactshop-click-tracking.php`
- `wp-content/mu-plugins/impactshop-go-bridge.php`
- `wp-content/mu-plugins/impactshop-impactad-redirect.php`
- `wp-content/mu-plugins/impactshop-saved-offers.php`
- `wp-content/mu-plugins/impactshop-saved-offers.js`

### Offerwall / Survey / Leaderboard / Identity

- `wp-content/mu-plugins/impactshop-offerwall.php`
- `wp-content/mu-plugins/impactshop-offerwall-survey.php`
- `wp-content/mu-plugins/impactshop-offerwall-article-quiz.php`
- `wp-content/mu-plugins/impactshop-ayet-offerwall.php`
- `wp-content/mu-plugins/impactshop-full-leaderboard.php`
- `wp-content/mu-plugins/impactshop-rest-totals.php`
- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-ngo-selector.php`
- `wp-content/mu-plugins/impactshop-ngo-card.php`
- `wp-content/mu-plugins/impactshop-vote-purchase.php`

### PWA / Guide perimeter

- `wp-content/mu-plugins/impactshop-pwa.php`
- `wp-content/mu-plugins/impactshop-pwa-push.php`
- `wp-content/mu-plugins/impactshop-ngo-guides.php`
- `wp-content/mu-plugins/impactshop-ngo-guides/**`

## Guide rendszer kanonikus szabálya

- A teljes guide rendszer külön beton protected perimeter.
- A kanonikus guide-készlet most a repo-ban és productionön is szinkronban lévő teljes subtree.
- Guide route, HTML, fordítás, asset, PDF, renderelt output és kapcsolódó tartalmi blokk csak külön, explicit engedéllyel módosítható.
- Ha egy guide bármely pontja eltér a jóváhagyott végleges változattól, azt nem lokális “részfixként”, hanem baseline-eltérésként kell kezelni.

## Kötelező változtatási kapu protected file esetén

Protected file módosítás előtt kötelező:

- koherencia vizsgálat
- kockázatelemzés
- érintett funkciólista
- backup + rollback snapshot

Protected file módosítás után kötelező:

- post-merge / post-deploy funkcióellenőrzési kör
- külön manuális UI checklist a felhasználónak
- fizikai read-only visszazárás

Részletes eljárás: `docs/protected-file-change-checklist.md`

## Kanonikus regressziószabály

Az alábbiak bármelyike regressziónak számít:

- guide tartalom vagy guide route eltérés a jóváhagyott végleges állapottól
- Impact Challenge feliratok, workflow vagy jutalmazási UI váratlan driftje
- auto-banner / CJ / Dognet / shopfeloldási logika hallgatólagos változása
- affiliate linkek vagy mentett ajánlatok impliciten megváltozó formátuma
- védett runtime fájlok vagy guide subtree fizikai `0444/0555` állapotának elvesztése
- olyan PR / merge / deploy, amely nem hivatkozik erre a baseline-ra, miközben Impact Challenge protected perimeterhez nyúl

## Snapshotok

- Lokális Impact Challenge lock snapshot: `.codex/guard-snapshots/impact-challenge-lock-20260326-165338/`
- Lokális guide lock snapshot: `.codex/guard-snapshots/guide-lock-20260326-164637/`
- Production guide lock rollback: `/home/sharityh/app/tmp/codex-backups/guide-lock-20260326-164708/rollback.sh`

## Kanonikus hivatkozás

Ez a fájl az Impact Challenge teljes rendszerére vonatkozó elsődleges baseline-hivatkozás. Későbbi policy, PR, deploy és handover szöveg ennek rendelődik alá.
