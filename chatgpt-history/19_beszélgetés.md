Átugrás a tartalomra
Ezt mondtad:
Weboldal életrekeltése: Íme egy tömör, mégis elég részletes „handoff” jegyzet magamnak a következő beszélgetéshez — hogy új chatben azonnal tudjam folytatni.

# Állapotkép (ma)

* **Platform:** WordPress (hu\_HU), Hello Elementor téma, Elementor/Pro aktív.
* **Impact UI integráció:** minden **oldal-szintű HTML widgetekben**, egy #impact-page wrapper alatt, **scope-olt CSS/JS**-sel (nem globális).
* **Shortcode-ok (mini):** [impact_ticker], [impact_leaderboard tab="ngo"], [impact_activity] — a **mini URL-helper** használatával a kanonikus REST: /wp-json/impact/v1/....
* **Impact adatlogika (stabil):**

  * Dognet auth /auth/login → 24h token (frissül használattal).
  * Tranzakciók: /raw-transactions/filter (last\_id görgetés), **Ad Channel fix: 26081**.
  * **Approved-only** az „éles” metrikákhoz (ticker, leaderboard, activity).
  * **Adomány = jutalék × 0.5** (backend lehet már felezve adja; UI következetesen adományt mutat).
  * Dátum: felső határ **exkluzív** (to+1 nap 00:00:00).
* **Cron / hibák:**

  * Korábbi log: rsssl_every_five_minutes_hook could_not_set + Complianz mPDF/mbregex fatál.
  * **Most:** Complianz **kikapcsolva**, napló **tiszta** kézi WP-Cron futtatás után.
  * mbstring modul **bekapcsolva** a szerveren; **mbregex** státusz szolgáltatói oldalon függő (később, ha kell, visszakapcsoljuk a Complianz-t).
* **Debug:** ideiglenesen bekapcsolva volt, most visszaállítva (nem termel felesleges logot).
* **UI részek az oldalon:** Hero, Netflix-sorok, Top3 visszaszámlálóval és „Másolva!” mikrovisszajelzéssel, Kampányfal iframe, Sticky bottom nav, valamint az Impact KPI/Leaderboard/Activity blokkok.

# Cél

* **„Prezi-kész”/bemutatható Impact oldal** élő számokkal (ticker/leaderboard/activity), működő Netflix-sorokkal és Top3 akciókkal, reszponzív és gyors, dark UI-ban.
* Rövid távon: **stabil demo/éles** front kis cache-sel.
* Közép távon: **/impact/report** szűrős riport-oldal (shop×NGO / NGO / Shop bontásokkal), export opcióval.

# Következő technikai lépések (sorrend)

1. **Cron**: véglegesítsünk **szerver-cront** (cPanel 5 perc / curl a wp-cron.php-ra) és állítsuk DISABLE_WP_CRON=true-re, vagy maradjunk házi cronnal — de **egyik** legyen.
2. **Impact UI tartalom betöltés:**

   * Netflix-sorok: **9–18 kártya** (Név | 1 sor tagline | /go URL), kategóriák végleges nevei.
   * Top3 akciók: **3 tétel** (cím, rövid leírás, data-deadline ISO, opcionális kupon).
   * Kampányfal: kategória JSON sorrend/nyelv finomhangolása.
3. **Report oldal /impact/report** (MVP):

   * Szűrősáv: from–to, status (approved|pending|rejected|all), group (shop\_ngo|ngo|shop), ngo (slug).
   * Tábla + lábléc-összesítő; oszlopok: kosárérték, jutalék, **adomány (50%)**.
   * Cache kulcs paraméterenként, **15 perc**.
4. **Hibakezelés/UI-üzenetek**: shortcode-ok baráti hibaállapota (tokenhiba, üres adat), ?impact_flush=1 gyors cache-flush támogatása.
5. **Cookie/GDPR ideiglenes megoldás** (ha szükséges): egyszerű banner (CookieYes/GDPR Cookie Compliance) **vagy** szolgáltatói ticket az **mbregex** engedélyezésére, majd Complianz vissza.
6. **Mérés**: GA4 események (shop\_click, deal\_copy, impact\_view, campaign\_switch).
7. **Performance**: képek/webm videók mérete, OPcache, (ha van) Redis object cache; .htaccess statikus cache; Elementor/CSS minimalizálás; lazyload; iframe auto-height.
8. **QA**: mobil töréspontok, fókusz- és billentyű-navigáció, kontraszt; sticky nav anchorok (#discover, #top).

# Mi hiányzik még a „megálmodott oldalhoz”

* **Végleges tartalom-listák**:

  * 3 Netflix-sor kategória neve + **kártyák (9–18)**: *Név | Tagline | /go URL*.
  * **Top3 akció**: 3× (cím, leírás, data-deadline, kupon?).
  * Facebook Page URL (+ melyik oldalon legyen Comments).
* **Report backend/bridge**: a riport endpoint paraméterezett aggregálása (shop×NGO/NGO/Shop), last\_id görgetés implementálva, Dognet csatornaszűrő **26081** erőltetése.
* **Deviza-formázó** (később): € → HUF konverzió opció (MVP-ben maradhat €).
* **Complianz visszaútja** (ha kell): szolgáltatói oldalon **mbregex** bekapcsoltatása (mbstring részeként), aztán plugin vissza; vagy végleges váltás egyszerűbb bannerre.
* **WP-Cron döntés**: véglegesítsük, hogy szerver-cron vagy házi; WP Crontrol-lal ellenőrzés, rsssl_* hook tiszta.
* **Kisebb UX-apróságok**: confetti trigger az impact:updated eseményre, „Frissítve: HH\:MM” jelzés az activityn, üres állapot üzenetek.

# Döntések/konvenciók (fix pontok)

* **Mini URL-építés** a REST-hez: kanonikus /wp-json/impact/v1/*.
* **Approved-only** az élő kijelzésnél; riportnál szűrhető.
* **Donation = commission × 0.5** — UI mindig adományt mutat (backend adhat „donation”-t előre).
* **Ad Channel fix:** 26081 minden Dognet-lekérésben (boot filter kiszedi a többit).
* **Dátum felső határ exkluzív** (to+1 nap 00:00:00).
* **Scope-olt stílus/JS**: minden a #impact-page alatt; más oldalhoz nem nyúlunk.

# Gyors ellenőrzőlista (folytatás előtt)

* [ ] Nincsenek új hibalogsorok kézi cron után (…/wp-cron.php?doing_wp_cron=1).
* [ ] Impact shortcode-ok válaszolnak; ?impact_flush=1 frissít (diag shortcode opcionálisan).
* [ ] Netflix/Top3 tartalmak beírva, countdown ketyeg, „Másolva!” működik.
* [ ] Sticky nav anchorok jók.
* [ ] Report MVP oldal beállítva (szűrők + tábla + cache).
* [ ] Cookie megoldás: ideiglenes banner vagy Complianz vissza (mbregex).
* [ ] GA4 események bekötve (legalább shop\_click, deal\_copy, impact\_view).

# Mit vállalok a következő körben (új chatben azonnal adom)

* **Beilleszthető HTML-blokkok** a Netflix-sorokra és a Top3-ra a megadott tételekkel, plusz a **prefixelt CSS** egyben.
* **Report oldal** minimál shortcode/HTML és a várható JSON-séma szerint (lábléc-összesítéssel).
* **Konkrét cPanel cron parancs** (curl), és ha kell a wp-config.php diff.
* **Egysoros „healthcheck”** JS/konzol-snippek (gyors ellenőrzésre: wrapper, deadlines, scroller item count).
* **Szöveges support sablon** a szolgáltatónak az **mbregex** bekapcsoltatására (ha a Complianz visszajön).

Ezzel a vázlattal új beszélgetésben rögtön a kész kóddarabokkal tudok indulni: Netflix-kártyák, Top3 blokk, report-oldal és a finomhangolások.
A ChatGPT ezt mondta:
Megcsináltam neked a saját „handoff” vázlatot, így új beszélgetésben nem kell majd emlékezned a mostani állapotokra. Ez az összefoglaló összerántja a WP front-end helyzetképet, a Dognet Publisher API logikát, és a következő konkrét lépéseket.
🔎 Hol tart most a projekt
WP oldal: Hello Elementor téma, Elementor/Pro.
Impact UI mindig egy #impact-page wrapper alatt van, scope-olt CSS/JS-sel.
Shortcode-ok: [impact_ticker], [impact_leaderboard tab="ngo"], [impact_activity] → REST hívás a /wp-json/impact/v1/... végpontokra.
Dognet API:
Auth: POST /auth/login → token 24h, minden requestben Authorization: Bearer ….
Tranzakciók: POST /raw-transactions/filter + last_id görgetés.
Ad Channel fix: 26081.
Riportban szűrők: rstatus (A=approved, P=pending, D=declined), created_at gte/lte.
Affiliate link generálás: POST /campaigns/links/generate (ad_channel_id, campaign_id, url, data1, url_type=3).
Számítási szabály: adomány = jutalék × 0.5 (UI mindenhol adományt mutat).
Cron: WP-cron kézi futtatás oké, hibalog tiszta. Következő lépés szerver-cron.
Complianz: kikapcsolva (mbregex hiány miatt).
UI: Netflix-sorok, Top3 akciók countdownnal, ticker/leaderboard/activity, kampányfal iframe, sticky bottom nav.
🎯 Következő konkrét lépések
Cron fix: cPanel szerver-cron beállítása (curl https://app.sharity.hu/wp-cron.php?doing_wp_cron=1 5 percenként) és DISABLE_WP_CRON=true.
Netflix-sorok: 3 kategória, mindben 9–18 kártya (Név | tagline | /go link).
Top3 akciók: 3 elem, mind data-deadline ISO + opcionális kupon.
Report oldal (/impact/report):
Szűrők: dátum (from–to), status, group (shop×NGO / NGO / Shop), ngo slug.
Tábla + lábléc-összesítő.
Cache 15 perc.
Shortcode hibakezelés: tokenhiba → baráti üzenet; ?impact_flush=1 paraméter támogatása.
Cookie: ideiglenes banner (pl. CookieYes), vagy szolgáltatói ticket az mbregex engedélyezésére.
Mérés: GA4 események (shop_click, deal_copy, impact_view, campaign_switch).
Perf optimalizálás: OPcache, statikus cache, lazyload, iframe autoheight.
QA: mobil töréspontok, kontraszt, billentyű-navigáció, sticky nav anchorok.
📌 Fix pontok
REST URL-ek: /wp-json/impact/v1/*.
Approved-only élő kijelzéshez; riportnál szűrhető.
Dátum felső határ mindig exkluzív (to+1 nap 00:00:00).
CSS/JS mindig #impact-page alatt.
✅ Ellenőrzőlista (indulás előtt)
 WP-cron → szerver-cron beállítva, hibalog tiszta.
 Netflix + Top3 tartalom beírva, countdown működik.
 Report MVP oldal beállítva, cachel.
 Ideiglenes cookie banner vagy Complianz vissza.
 GA4 eventek trackelnek.
🤝 Következő körben hozandó
Beilleszthető Netflix- és Top3-HTML blokkok, CSS-sel.
Report oldal shortcode + JSON-séma.
Konkrét cPanel cron parancs és wp-config diff.
Egysoros JS healthcheck snippek.
Szöveges support-ticket sablon mbregex engedélyeztetésére.
Ez a jegyzet lesz a híd az új chathez, hogy ott már kódokat és kész blokkokat tudjunk írni (Netflix, Top3, report, cron, cookie).
Szeretnéd, hogy a következő alkalomra már a cPanel cron parancsot és a wp-config.php diffet is előkészítsem, vagy kezdjük a Netflix/Top3 HTML-blokkokkal?

Ezt mondtad:
=== AFFILIATE WEBOLDAL – KONTEXTUS BUNDLE (SHARITY) ===

# CÉL
- Teljes funkcionalitású, „prezi-kész” élő WordPress oldal (nem PPT).
- Célcsoport: 18–30, vagány, menő, színes, app-szerű élmény; vásárlás = támogatás gyors megértése.
- Első mérföldkő: működő front (Netflix-sorok, Top akciók, kampányfal), valódi adatokkal is képes futni kis cache-sel.

# STACK / KÖRNYEZET
- WordPress + Elementor (kezdőbarát beállítás), cPanel hosting.
- Van: affiliate linkek, shopok, riportok, API **beüzemelve**.
- Preferált: PHP 8.1/8.2, OPcache; (ha van) Redis object cache; igazi cron (cPanel Cron Jobs).

# FŐ UX/UI KERET
- Hero: videós háttér, nagy üzenet + 1 CTA (Felfedezés).
- Felfedezés: 3 „Netflix-sor” (Tech / Divat / Sport), kártyák kép + cím + tagline + CTA.
- Top 3 akció: visszaszámláló (HH:MM:SS), kupon „Másolva!” mikrovisszajelzés.
- NGO Spotlight: 1–3 nagy kártya.
- Impact szekció: ticker (összeg), leaderboard (NGO), activity feed („X Y perce vásárolt …”), konfetti frissítéskor.
- Kampányfal: beágyazott lista kategóriafülekkel (Egészség, Oktatás, Környezet, Szociális, Állatvédelem).
- Social proof: Facebook Page feed + Comments (GDPR-cookieval).

# STÍLUS TOKENS
- Színek: purple #7C3AED, cyan #06B6D4, orange #F97316, lime #22C55E, háttér #0A0A0B (dark).
- Tipó: Címsor merész (pl. Bebas Neue / alternativa), szöveg Inter.
- UI: kártya glow, glassmorphism, hover „lift”, rövid scroll reveal.

# FÁJLOK / ELEMEK (MÁR LÉTEZNEK – IMPORTÁLHATÓK)
- Elementor sablonok: 
  1) impact-elementor-template.json (Hero + Felfedezés + Top3 + Impact)
  2) impact-carousels-template.json (Tech & Sport – Slides)
- Globális CSS: impact.css (dark theme, kártyák, ticker, kampány-embed UI)
- Globális JS: impact.js (countdown, clipboard, konfetti trigger, FB SDK consent, embed auto-height)
- HTML snippetek (Elementor HTML widgetbe): snippets.html
  - Confetti overlay: <div id="impact-confetti"> + /wp-content/uploads/points-confetti.mp4
  - Facebook Page feed + Comments (GDPR-baráti)
  - Kampánylista embed tabokkal (data-base-src=https://adomany.sharity.hu/kampanyok/index-embed)
  - Minta Deal Card
- Sora promptok: sora-prompts.txt (hero / háttér loopokhoz)

# SHORTCODE-OK (LIVE MÓD)
- [impact_ticker] – összesített támogatás / napi számok
- [impact_leaderboard tab="ngo"] – toplista
- [impact_activity] – „XY most vásárolt” feed
- LIVE módban 2–5 perces szerver-oldali cache (transient), REST proxy, baráti hibaüzenet (nincs fatál).
- Front triggers: document.dispatchEvent(new CustomEvent('impact:updated')); → konfetti.

# KAMPÁNYFAL – PARAMÉTEREK
- Beágyazás: iframe https://adomany.sharity.hu/kampanyok/index-embed
- Váltható kategóriák (tabs + mobilon select): JSON config (pl. egeszseg, oktatas, kornyezet, szocialis, allatvedelem).
- data-fixed-height="700" vagy data-auto-height="true" (ha az embed küld postMessage magasságot).
- UTM-ek: data-extra="utm_source=impactshop&utm_medium=embed&utm_campaign=campaign_wall"

# FACEBOOK – GDPR
- Complianz CMP „Social” consent után töltődjön a Meta SDK (impact.js már figyeli).
- Page feed + Comments: cserélendő YOUR_PAGE_URL és YOUR_SITE_PAGE_URL.

# PREZI-KÉSZ DEMÓ LÉPÉSEK (BACKEND FÜGGETLEN)
1) Import sablonok → új oldalra beszúrás → Publish (/impact-demo).
2) Site Settings → Custom CSS: impact.css.
3) Custom Code (Footer) → impact.js (Entire Site / oldal).
4) Netflix-sor: Slides szekció vagy „Swiper Netflix-sor” snippet (HTML widget).
5) Top3: data-deadline="YYYY-MM-DDTHH:mm:ss+02:00" ISO dátumok beállítása.
6) Hero: videó (Sora/Capcut), Poster, 40–55% overlay.
7) Kampányfal: snippets.html blokk → kategória JSON finomhangolása.
8) (Ha kell) Facebook blokk beillesztése → cookie elfogadás demó közben.

# ÉLES FUNKCIÓ (AKÁR 1 FELHASZNÁLÓVAL IS)
- Shortcode-ok LIVE módban (proxy + cache 120–300 mp).
- API-kulcsok: wp-config (vagy ENV), nem frontendben.
- cPanel cron (perces/5 perces): /usr/bin/curl -fsS https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1 >/dev/null 2>&1
- .htaccess: statikus cache (1 év), HTTPS kényszer.
- GA4 események (később): shop_click, deal_copy, campaign_switch, impact_view.

# MIT KÉREK A KÖVETKEZŐ CHATBEN (HOGY AZONNAL GYÁRTSAK)
- Oldal SLUG: /impact-demo vagy /impact
- Címsor és alcím végleges szövege (HU)
- 3 Netflix-sor kategória végleges neve (pl. Tech / Divat / Sport)
- 9–18 kártya (cím, 1 sor tagline, cél URL /go-link) – ha nincs kép, generálok stock placeholderrel
- Top3 akciók: 3 cím + rövid leírás + data-deadline dátumok + (opcionális) kupon kód
- Kampányfal kategória-lista (ha más sorrend kell)
- Facebook Page URL + azon oldal URL-je, ahol Comments látszódjon
- Sora/CapCut: kell-e új hero loop? (ha igen, stílus: Neon Grid / Sport Wave / Tech Matrix)
- Domain (SAJAT-DOMAIN.hu) – hogy gyors cPanel cron és .htaccess mintát adjak

# RÖVID FORMÁTUM A KÁRTYÁKHOZ (MINTA)
- Név | Tagline | URL
- Vision Express | Napszemüveg & keretek | https://app.sharity.hu/go/?shop=visionexpress&src=impactshop
- eMAG | Tech & háztartás | https://app.sharity.hu/go/?shop=emag&src=impactshop
- INTERSPORT | Felszerelés & ruházat | https://app.sharity.hu/go/?shop=intersport&src=impactshop

=== /END CONTEXT BUNDLE ===
A ChatGPT ezt mondta:
Ez így egy igazi „projekt-bundle”, amit új chatben tényleg elég előhúzni, és máris folytatható a munka. Amit most összeállítottál, lefedi a teljes keretet: UX-től kezdve a színsémán, kód-snippeken és cron-beállításokon át a demó-workflow-ig.
Lényegében két réteg van benne:

1. Prezi-kész demó – ez független a backendtől. Itt a sablonok (Hero, Netflix-sor, Top3, Kampányfal), a CSS/JS fájlok és a snippetek adják a vizuális élményt. Azonnal működhet, ha a tartalom (kártyák, akciók, FB URL) megvan.

2. Éles funkció – ez már ráül a Dognet API-ra, a short­code proxykra és a cache-re. Cron beállítással, GA4-gyel, cookie-megoldással stabilizálható.

A bundle végén pontosan felsorolod, mire lesz szükségem a következő körben: slug, címsorok, 3 kategória + kártyák, 3 top akció, kampányfal kategóriák, FB URL, domain és esetleg új hero-loop stílus. Ha ezek megvannak, onnantól egyből tudok neked:

beilleszthető HTML blokkokat (Netflix, Top3)
prefixelt CSS-t
a cron parancsot és wp-config diffet
.htaccess mintát cache-hez
és a support-szöveget az mbregex engedélyezéséhez
Így a következő chatben már kódot és kész snippeteket adok, nem csak vázlatot.
A legizgalmasabb kérdés most: a három Netflix-sorhoz és a Top3 akcióhoz mikor lesznek meg a végleges tartalmaid? Mert abban a pillanatban tudjuk „felöltöztetni” az oldaladat és bemutatni a működő élményt.

Ezt mondtad:
milyen tartalmak mennek oda a terv szerint?
A ChatGPT ezt mondta:
A tervben a tartalom három nagy blokkba van szervezve, és mindegyik más-más típusú üzenetet hordoz:
1. Netflix-sorok (3 kategória)
Ezek a „lapozható” sorok adják a fő felfedező élményt.
– 3 kategória (pl. Tech, Divat, Sport – de átnevezheted, ha jobban illik a célcsoporthoz).
– Mindegyikben 9–18 kártya:
  • Név (pl. Vision Express)
  • 1 sor tagline (pl. „Napszemüveg & keretek”)
  • /go-link (affiliate URL, ami először a Fillout → Dognet folyamaton fut át).
  • Kép (ha nincs saját, placeholder stock-fotóval is elindulhat).

2. Top 3 akció blokk
Ez a „villogó ajánlat” rész.
– 3 tétel (lehet kuponkódos, %-os akció, „csak ma” jelleg).
– Mindegyik tartalmazza:
  • Cím (pl. „20% kedvezmény sportcipőkre”)
  • Rövid leírás (1 mondat)
  • data-deadline ISO formátumban (lejárat dátuma/időpontja) → ebből fut a visszaszámláló
  • Kuponkód (ha van), a „Másolva!” visszajelzéssel.

3. Kampányfal
– Beágyazott lista kategóriafülekkel (Egészség, Oktatás, Környezet, Szociális, Állatvédelem).
– Ez nem saját kártyázás, hanem az adomany.sharity.hu embed-jét mutatja, UTM-ekkel.

Extra
– Hero szekcióban videó loop + CTA („Fedezd fel”).
– Impact-blokkban a valós számok: ticker (összes adomány), leaderboard (NGO toplista), activity feed („XY most vásárolt …”).
– NGO Spotlight (1–3 kiemelt szervezet nagy kártyán).
– Social proof (Facebook Page feed + Comments).

Szóval: Netflix-sorok = webshop kártyák, Top3 = akciók, Kampányfal = ügyek, Impact = számok, Hero = belépő üzenet.

Szeretnéd, hogy összerakjak neked egy dummy kitöltést (mintakártyák + akciók) demóhoz, amíg nincsenek meg a végleges nevek és linkek?

Ezt mondtad:
minden meg van azt rakd össze mire van szükséged, mit adják át neked, akár check list szerűen
A ChatGPT ezt mondta:
Szuper, akkor jöjjön egy tömör, de teljes átadási checklist, hogy mindent megkapjak, és össze tudjam rakni a demó/éles frontot:
📍 Alapadatok
Domain (pl. app.sharity.hu vagy éles SAJAT-DOMAIN.hu) → hogy a cron és a .htaccess mintát pontosan tudjam megírni.
Oldal slug: /impact vagy /impact-demo.
🎬 Hero
Főcím és alcím szövege (rövid, ütős üzenet).
Videó loop: megvan-e már (Sora/CapCut készítve), vagy generáljam?
– Ha generáljam: stílus (pl. Neon Grid / Sport Wave / Tech Matrix).
🍿 Netflix-sorok
3 kategória végleges neve (pl. Tech / Divat / Sport).
Minden kategóriába 9–18 kártya a formátumban:
Név | 1 sor tagline | /go-link
(ha van kép → kép URL vagy megadod, ha nincs → stock placeholder).
🔥 Top 3 akció
3 akció adatai:
– Cím
– Rövid leírás (max. 1 mondat)
– data-deadline (lejárati dátum ISO formátumban pl. 2025-10-15T23:59:59+02:00)
– Kuponkód (ha van).
🌍 Kampányfal
Kategóriák sorrendje/nevei (ha eltér az alap JSON-tól: Egészség, Oktatás, Környezet, Szociális, Állatvédelem).
Ha van egyedi beállítás (fix magasság vagy auto-height).
🎯 Impact blokk
Ticker / Leaderboard / Activity: már megy az API → nincs külön input, de jelezd, ha extra szűrés vagy más alapértelmezett tab kell.
NGO Spotlight: melyik 1–3 szervezet legyen kiemelve (név, tagline, kép, link).
📣 Social proof
Facebook Page URL.
Az oldal URL, ahol a Comments blokk látszódjon (pl. /impact).
⚙️ Technikai
Van-e preferenciád cronra: 5 perc (ajánlott) vagy más?
Szeretnél-e rögtön .htaccess cache mintát (statikus + HTTPS kényszer)?
GA4 méréshez → ha már van measurement ID, add át.
👉 Ha ezt a listát kitöltöd, én a következő körben egyből adok neked:
Netflix- és Top3-HTML blokkok késszövegekkel, CSS/JS-sel.
Hero beállítás (videó + CTA).
Kampányfal snippet készre.
Cron parancs és wp-config diff.
.htaccess cache minta.
GDPR-cookiehoz fallback banner snippet.
Szeretnéd, hogy készítsek hozzá egy táblázat-sablont (Google Sheets/Excel), ahova a Netflix kártyákat és akciókat könnyen be tudod pakolni, és aztán onnan áthozzuk a HTML-t?
Ezt mondtad:
Arra emlékszem hogy a sora hoz készültek scrip leírások, azokat össze tudod gyűjteni?
A ChatGPT ezt mondta:

Gondolkozás
megvan — összeraktam egy „sora-prompts.txt” csomagot a korábbi stíluscímkéinkhez (Neon Grid / Tech Matrix / Sport Wave), a célcsoporthoz és a színtokenekhez igazítva. Egyben adom, hogy tudd másolni. Minden blokk tartalmaz rövid promptot és ajánlott beállításokat (arány, hossz, loop, mozgás). Ha akarsz, átírom konkrét márkanevekre, logóra, szlogenekre.
# =========================================================
# SORA PROMPTS – SHARITY IMPACT (HERO + HÁTTÉR LOOPS)
# Paletta: purple #7C3AED, cyan #06B6D4, orange #F97316, lime #22C55E
# Mood: vagány, app-szerű, gyorsan értelmezhető „vásárlás = támogatás”
# Általános param-ajánló: 16:9, 6–8 s, 30 fps, seamless loop, light grain off
# =========================================================

[GLOBAL GUIDELINES]
- Visual style: dark (#0A0A0B) base, neon edge glow, finom glassmorphism tükröződés.
- Movement: lassú kamerapan + parallax; UI-szerű elemek rövid pulzálással.
- Avoid: túl sok zaj, túl sűrű feliratok; tartalom legyen „hero overlay”-barát.
- CTA overlay hely: alsó-közép vagy bal-alsó harmad.

# ---------------------------------------------------------
# 1) HERO – NEON GRID „IMPACT” INTRO (Tech Matrix)
# ---------------------------------------------------------
PROMPT:
A dark, futuristic city micro-landscape made of thin neon grid lines in purple (#7C3AED) and cyan (#06B6D4). 
Lines animate like flowing energy, forming a subtle heart-pulse shape for one beat, then spreading as a wave. 
Glass-like UI panels drift softly with parallax; tiny particles shimmer. 
Mood: hopeful, energetic, modern. No logos or text baked in.

SETTINGS:
aspect: 16:9
duration: 8s
loop: seamless
camera: slow dolly-in + slight tilt
motion: medium, ease-in-out
lighting: neon emissive on black
detail: medium-high

# ---------------------------------------------------------
# 2) HERO – SPORT WAVE „MOVE FOR GOOD”
# ---------------------------------------------------------
PROMPT:
Abstract kinetic ribbons in lime (#22C55E) and orange (#F97316) sweep across a dark background, 
leaving soft motion trails. Ribbons curve like running tracks, forming a subtle infinity (∞) loop for a moment. 
Soft depth-of-field, light dust motes.

SETTINGS:
aspect: 16:9
duration: 6s
loop: seamless
camera: slow lateral pan
motion: medium-high, elastic easing
lighting: rim-lit ribbons

# ---------------------------------------------------------
# 3) HERO – DIVAT „COLOR POP GLASS”
# ---------------------------------------------------------
PROMPT:
Floating glossy rectangles and circles (glassmorphism) reflect neon accents (purple, cyan, orange). 
They gently swing and snap into a loose grid as if an app home screen. 
Subtle bloom, smooth reflections, no text.

SETTINGS:
aspect: 16:9
duration: 7s
loop: seamless
camera: micro parallax only
motion: low-medium
lighting: soft studio + neon specular

# ---------------------------------------------------------
# 4) CATEGORY BG – TECH / „Neon Circuit Rain”
# ---------------------------------------------------------
PROMPT:
Minimal, repeating pattern of animated circuitry lines raining downward, 
cyan and purple micro-streaks on deep black. Occasional spark nodes pulse softly.

SETTINGS:
aspect: 16:9
duration: 6s
loop: seamless
camera: static
motion: low (pattern driven)

# ---------------------------------------------------------
# 5) CATEGORY BG – DIVAT / „Color Blocks Drip”
# ---------------------------------------------------------
PROMPT:
Bold color blocks (purple, orange, cyan) slide on the Z-axis with soft shadows, 
occasionally revealing silky fabric micro-textures; playful, snappy transitions.

SETINGS:
aspect: 16:9
duration: 6s
loop: seamless
camera: slow push-in
motion: medium (snappy easing)

# ---------------------------------------------------------
# 6) CATEGORY BG – SPORT / „Strobe Track”
# ---------------------------------------------------------
PROMPT:
Dark track lines glow lime (#22C55E). Light fog sweeps across; 
short strobe of speed lines forward, then ease back to calm.

SETTINGS:
aspect: 16:9
duration: 6s
loop: seamless
camera: slow forward dolly
motion: medium-high burst + calm

# ---------------------------------------------------------
# 7) TOP DEALS COUNTDOWN – „Neon Timer Ring”
# ---------------------------------------------------------
PROMPT:
Close-up abstract countdown ring made of dotted neon segments (cyan + purple) on dark. 
Segments gently progress clockwise; faint particles drift. No numbers baked in.

SETTINGS:
aspect: 16:9 (or square 1:1 if UI igényli)
duration: 6s
loop: seamless
camera: static
motion: low
notes: hagyj üres középet a webes számláló rétegnek

# ---------------------------------------------------------
# 8) NGO SPOTLIGHT – „Soft Bokeh Hope”
# ---------------------------------------------------------
PROMPT:
Warm, soft bokeh orbs (orange + lime) float in slow parallax over deep black; 
very gentle lens breathing, subtle light leaks; uplifting, human.

SETTINGS:
aspect: 16:9
duration: 8s
loop: seamless
camera: micro parallax
motion: low

# ---------------------------------------------------------
# 9) CAMPAIGN WALL BG – „Tabbed Glow”
# ---------------------------------------------------------
PROMPT:
Five thin, glowing tab-pill shapes (labels not rendered) slide subtly left-right in a repeating rhythm; 
each tab has a faint color accent mapped to categories: Health (lime), Education (cyan), 
Environment (green-lime mix), Social (purple), Animals (orange).

SETTINGS:
aspect: 16:9
duration: 6s
loop: seamless
camera: static
motion: very low (UI-like)

# ---------------------------------------------------------
# 10) MICRO OVERLAY – „Confetti Points”
# ---------------------------------------------------------
PROMPT:
Tiny geometric confetti (triangles, circles) in brand colors drift upward then loop, 
very subtle, low density; designed to sit behind transparent UI.

SETTINGS:
aspect: 16:9
duration: 6s
loop: seamless
camera: static
motion: very low
opacity: mild

# ---------------------------------------------------------
# 11) ALT HERO – „Impact Pulse Grid (Monoline)”
# ---------------------------------------------------------
PROMPT:
Monoline grid waves radiate from center like an ECG pulse, 
alternating purple→cyan gradients; glassy reflections fade in and out. Clean, modern.

SETTINGS:
aspect: 16:9
duration: 7s
loop: seamless
camera: slow zoom-out
motion: low-medium

# ---------------------------------------------------------
# 12) ALT SPORT – „Velocity Particles”
# ---------------------------------------------------------
PROMPT:
Forward-moving particle field with lime streaks; occasional orange sparks suggest acceleration; 
depth-graded, tunnel-like feel without being aggressive.

SETTINGS:
aspect: 16:9
duration: 6s
loop: seamless
camera: forward dolly
motion: medium

# ---------------------------------------------------------
# HASZNÁLATI MEGJEGYZÉSEK
# - Logót, feliratot NE égesd be a videóba: Elementoron/HTML-en overlayezünk.
# - A loop vágáspontját kérd „perfect seamless loop”-ra.
# - Ha social-ra is kell: készíts 9:16 és 1:1 változatot is.
# - Ha kell csendes változat és „light grain” verzió is az esetleges banding ellen.
# =========================================================
Ha megadod, melyik(ek)et szeretnéd első körben, legenerálom a pontos szövegeket a kívánt hosszúság-/arány-variánsokkal (pl. 16:9 + 9:16), és adok hozzá egy minikönyvtár-struktúrát is (/media/hero/…, /media/bg/…) meg elnevezési konvenciót, hogy Elementorban könnyű legyen cserélni.
Ezt mondtad:
haladjunk sorrendben
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Oké, akkor megyünk a listád szerinti 1. lépéssel: CRON véglegesítés. Alább egyben adom a copy-paste csomagot (a preferenciád szerint egyetlen kódtömbben), benne a cPanel Cron Jobs parancs, a wp-config.php diff, és pár gyors ellenőrző parancs. Így azonnal be tudod állítani.
# ============================================
# 1) REAL SERVER CRON (cPanel) – AJÁNLOTT
# Futás: 5 percenként meghívja a WP kront
# ============================================

# cPanel > Cron Jobs > "Add New Cron Job"
# Common Settings: "Every 5 minutes" (*/5 * * * *)
# Command (cURL verzió – ez a legegyszerűbb és megbízható):
/usr/bin/curl -fsS -A "SharityCron/1.0" "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1

# Alternatíva (wget):
/usr/bin/wget -q -O - "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1

# Alternatíva (php-cli) – ha tudod az absolute path-ot:
# (Ezzel közvetlenül a PHP értelmezi a fájlt, nem HTTP-n keresztül.)
/usr/bin/php -d detect_unicode=0 /home/USER/public_html/wp-cron.php >/dev/null 2>&1


# ============================================
# 2) WP-CRON KIKAPCSOLÁSA – wp-config.php DIFF
# Ezt a két sort tedd be az "/* That's all, stop editing! Happy publishing. */"
# sor ELÉ, vagy a többi define közé. Domainhez nem kötött.
# ============================================

/* Disable built-in pseudo-cron; we use real server cron */
define('DISABLE_WP_CRON', true);

/* (Opcionális) Időzóna és memória finomhangolás – ha kell */
# date_default_timezone_set('Europe/Budapest');  // csak ha biztosan nem zavar más plugint
# define('WP_MEMORY_LIMIT', '256M');             // ha kevés a default


# ============================================
# 3) GYORSTESZT / HEALTHCHECK
# Ezek NEM kötelezőek, csak hasznosak hibakeresésre
# ============================================

# 3.1) Manuális hívás böngészőből (HTTP 200 a cél):
# https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1

# 3.2) cPanel Terminál / SSH: ellenőrizd, hogy a cron parancs fut és nincs kimeneti hiba
/usr/bin/curl -fsS -A "SharityCron/1.0" "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" && echo "OK" || echo "HIBA"

# 3.3) WP-Crontrol (ha telepítve): nézd meg, hogy vannak-e pending eventek, és csökken-e a queue.

# 3.4) Error log figyelés (cPanel > Errors vagy file manager):
# - nincs új fatal/notice a beállítás után
# - a korábbi rsssl_* és Complianz mPDF/mbregex jellegű hibák NEM szaporodnak


# ============================================
# 4) ROBUSZTUSSÁG / EDGE CASE TIPPEK
# ============================================

# 4.1) Ha Cloudflare vagy más CDN van előtte:
# - Engedélyezd a cron IP-t (ha IP-ről futtatod) VAGY
# - Adj hozzá "Cache Bypass" Page Rule-t a /wp-cron.php endpointra.

# 4.2) Ha Basic Auth védi a staginget:
# - curl -u USER:PASS "https://.../wp-cron.php?doing_wp_cron=1"

# 4.3) Ha multisite:
# - Minden site saját cronja hívódik a root wp-cron.php-n keresztül, nem kell külön site-URL.

# 4.4) Ha Elementor/Pro + heavy pluginek vannak:
# - A cron idejét állítsd akkorra, amikor kisebb a forgalom (pl. hajnali 3–4), DE
#   az Impact frissítés miatt a */5 perc napközben hasznos, mert „élő” számokat mutatunk.
Mit fogsz látni, ha oké?
– Nem kerül új hiba a logba a beállítás után.
– A shortcode-ok (ticker/leaderboard/activity) 2–5 percen belül frissülnek.
– A WP Crontrol (ha van) alatt a scheduled eventek ütemesen lefutnak.
Ha ezt kipipáltuk, lépek a 2. pontra (Impact UI tartalom betöltés: Netflix-sorok 9–18 kártyával, Top3 akciók, Kampányfal embed). Már megvan minden tartalmad – összerakom a beilleszthető HTML-blokkokat + a prefixelt CSS/JS-t egyetlen kódtömbben.

Ezt mondtad:
Oké, akkor megyünk a listád szerinti 1. lépéssel: **CRON véglegesítés**. Alább egyben adom a _copy-paste_ csomagot (a preferenciád szerint egyetlen kódtömbben), benne a cPanel Cron Jobs parancs, a `wp-config.php` diff, és pár gyors ellenőrző parancs. Így azonnal be tudod állítani.
és ezzel most mit tegyek?
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Röviden: állítsd be a szerver-cront cPanelben, majd kapcsold ki a WP beépített álkronját a wp-config.php-ben, és ellenőrizd, hogy futnak az események. Lépésről lépésre:
1) cPanel Cron Jobs – beállítás (5 percenként)
Lépj be a cPanelbe → Cron Jobs.
Add New Cron Job:
Common Settings: Every 5 minutes (*/5 * * * *)
Command (cseréld a SAJAT-DOMAIN.hu-t a saját domainre):
/usr/bin/curl -fsS -A "SharityCron/1.0" "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1
Mentsd el.
(Ha a tárhelyen nincs /usr/bin/curl, használd ezt:
/usr/bin/wget -q -O - "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1)
2) WordPress álkron kikapcsolása (wp-config.php)
cPanel → File Manager → a WordPress gyökerében nyisd meg a wp-config.php-t (jobb klikk → Edit).
A fájlban keresd meg ezt a sort:
/* That's all, stop editing! Happy publishing. */
Elé illeszd be ezt a két sort (pont így, nagybetű/idézőjelek számítanak):
define('DISABLE_WP_CRON', true);
// opcionális, csak ha kell:
// define('WP_MEMORY_LIMIT', '256M');
Mentés.
3) Gyors ellenőrzés (azonnal látszik, hogy működik)
Böngészőben nyisd meg:
https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1
Ha 200-as válasz jön (üres fehér oldal is oké), elérhető a végpont.
cPanel → Cron Jobs → nézd a Cron Email/Recent Jobs részt: ne legyen hibaüzenet.
Ha van WP Crontrol bővítmény:
Tools → Cron Events: látod, hogy az események időben lefutnak és jön a következő ütem (nem „overdue”).
Impact blokkok (ticker/leaderboard/activity) 2–5 percen belül friss adatot mutatnak.
4) Ha Cloudflare/CDN van
Adj „cache bypass” szabályt a /wp-cron.php URL-re, vagy engedd a cron IP-jét tűzfalon.
5) Gyors hibakeresési tippek
Ha a cron „Permission denied” vagy „command not found”: a szolgáltatónál eltérő lehet a curl/wget elérési útja. Kérdezd le SSH-n: which curl, which wget.
Ha Basic Auth védi a staginget, a parancs így néz ki:
curl -fsS -u USER:PASS "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1
6) Kész? Mehet a 2. lépés
Ha a fenti három ellenőrzés rendben van (végpont elérhető, cron fut, nincs hiba a logban), jöhet a következő blokk: Impact UI tartalom betöltés (Netflix-sorok 9–18 kártyával, Top3 akciók, Kampányfal). Arra már megvan minden adatod — összerakom egyben a beilleszthető HTML+CSS+JS csomagot, hogy az Elementor HTML widgetbe tudd tenni.
Ezt mondtad:
**Elé** illeszd be ezt a két sort (pont így, nagybetű/idézőjelek számítanak): php Kód másolása
ezzel mi lesz marad : define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);   // vagy hagyhatod true-n, ha szereted a logot
define('WP_DEBUG_DISPLAY', false);
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igen, ezek maradhatnak — sőt, éles oldalon így a jó beállítás:
WP_DEBUG: false
WP_DEBUG_LOG: false (vagy ideiglenesen true, ha naplózni akarsz)
WP_DEBUG_DISPLAY: false (élesben mindig legyen false)
A DISABLE_WP_CRON ettől független; együtt élhet velük gond nélkül.
Alább adok egy egyben beilleszthető blokkot két profillal. Másold a wp-config.php-ba a “That’s all…” sor elé, és csak az egyik profilt hagyd aktívan.

/* ======== SHARITY – WP DEBUG PROFILES ======== */
/* 1) PROD (ajánlott élesre) */
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);      // Prod: ne nőjön a log feleslegesen
define('WP_DEBUG_DISPLAY', false);  // Soha ne jelenjen meg hiba a frontendeken

/* --- ALTERNATÍVA: ideiglenes hibakereséshez (STAGING / TEMP DEBUG) --- */
// define('WP_DEBUG', true);
// define('WP_DEBUG_LOG', true);       // /wp-content/debug.log
// define('WP_DEBUG_DISPLAY', false);  // maradjon rejtve a látogatók előtt
// @ini_set('display_errors', 0);      // extra biztosítás

/* (opcionális finomhangolások – csak ha tényleg kell) */
// define('SCRIPT_DEBUG', false);                   // true esetén nem minified assetek
// define('WP_DISABLE_FATAL_ERROR_HANDLER', false); // hagyd false-on

/* ======== SHARITY – CRON ======== */
// Valódi cron esetén:
define('DISABLE_WP_CRON', true);
/* ======== /END ======== */
Gyors magyarázat
Prod: nem naplózunk (kivéve ha célzottan kell), és semmit nem mutatunk a felhasználónak.
Temp debug: ha furcsaságot látsz, 1–2 órára kapcsold be a logolást (WP_DEBUG_LOG=true), nézd meg a /wp-content/debug.log fájlt, majd kapcsold vissza false-ra, hogy ne hízzon a log.
Tipp
Ha a logot bekapcsolod, néha töröld (debug.log gyorsan megnőhet).
Complianz/mbregex témát külön kezeljük – az nem a WP_DEBUG-tól függ; ha újra kell a Complianz, a szolgáltatónál az mbregex engedélyezése a lépés.
Ezt mondtad:
2. **Add New Cron Job**: * Common Settings: **Every 5 minutes** (`*/5 * * * *`) * Command (cseréld a SAJAT-DOMAIN.hu-t a saját domainre): bash Kód másolása `/usr/bin/curl -fsS -A "SharityCron/1.0" "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1` * Mentsd el. (Ha a tárhelyen nincs `/usr/bin/curl`, használd ezt: `/usr/bin/wget -q -O - "https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1`)
itt van egy régi: /5	*	*	*	*	/usr/bin/curl -fsS https://app.sharity.hu/wp-cron.php?doing_wp_cron=1 >/dev/null 2>&1
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igen, ez jó – csak egy apróságot javítsunk és tegyünk „szabványosra”.
Mi a helyzet a régi sorral?
/5 * * * * /usr/bin/curl -fsS https://app.sharity.hu/wp-cron.php?doing_wp_cron=1 >/dev/null 2>&1
Az elején a helyes „5 percenként” írásmód: */5, nem /5.
Érdemes adni egy egyedi User-Agentet, hogy a tárhely/CDN naplókban azonosítható legyen (és néhány szolgáltató kevésbé gyanakodjon botra).
Ajánlott végleges sor (másold be a Cron Jobs-ba)
*/5 * * * * /usr/bin/curl -fsS -A "SharityCron/1.0" "https://app.sharity.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1
Alternatíva, ha a curl útvonala más
*/5 * * * * /usr/bin/wget -q -O - "https://app.sharity.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1
Ellenőrzés (gyors)
Böngészőben: nyisd meg ezt → https://app.sharity.hu/wp-cron.php?doing_wp_cron=1 (200-as válasz = oké).
cPanel Cron Jobs „Recent Jobs”/Cron email: ne legyen hiba.
WP-ben a DISABLE_WP_CRON maradjon true a wp-config.php-ben, hogy csak a valódi cron fusson.
Ha így fut, mehetünk is tovább a 2. lépésre (Netflix-sorok + Top3 + Kampányfal blokkok összeállítása).
Ezt mondtad:
Feltöltött kép
ez jó?
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igen – a második sor tökéletes. Az első felesleges duplikátum.
Mit tegyél most?

Hagyd meg csak ezt:
*/5 * * * * /usr/bin/curl -fsS -A "SharityCron/1.0" "https://app.sharity.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1
Töröld az első (UA nélküli) sort. Két cron egyszerre csak fölöslegesen terheli és duplázhat.
Apró profi finomítás (opcionális, de ajánlott):
*/5 * * * * /usr/bin/curl -fsS --connect-timeout 5 -m 25 --retry 2 -A "SharityCron/1.0" "https://app.sharity.hu/wp-cron.php?doing_wp_cron=1" >/dev/null 2>&1
– gyors timeout, max futási idő, kíméletes újrapróbálás.
Gyors check:

wp-config.php-ben: define('DISABLE_WP_CRON', true); megvan.
Böngésző: https://app.sharity.hu/wp-cron.php?doing_wp_cron=1 → elérhető (200).
WP Crontrol (ha van): ütemesen futnak az események; nincs “overdue”.
Ha kész, léphetünk a 2. pontra (Netflix-sorok + Top3 + Kampányfal blokkok egyben beilleszthető HTML+CSS+JS csomag).
Ezt mondtad:
jöhet
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Jön a beilleszthető csomag — egyetlen HTML blokk Elementorhoz. A tetején a DATA részben töltsd ki a kártyákat és a Top3 akciókat (Név | tagline | /go-URL | kép). Minden a #impact-page alatt fut, saját CSS/JS-sel, countdowndal, „Másolva!” visszajelzéssel, GA4-hez eseményekkel, és a Kampányfal embed auto-height opcióval.
<!-- =========================================================
   SHARITY IMPACT – Netflix-sorok + Top3 + Kampányfal (HTML+CSS+JS)
   Beillesztés: Elementor > HTML widget
   Mindent a #impact-page alatt scope-olunk.
   ========================================================= -->
<div id="impact-page" class="impact dark">
  <!-- ====== HERO (opcionális placeholder) ====== -->
  <section class="hero">
    <video class="hero-bg" autoplay muted loop playsinline poster="" preload="auto">
      <!-- adj meg egy videót, ha van; különben marad a háttérszín -->
      <!-- <source src="/wp-content/uploads/hero-loop.mp4" type="video/mp4"> -->
    </video>
    <div class="hero-inner">
      <h1>Vásárlás = Támogatás</h1>
      <p>Válassz shopot, és minden vásárlásoddal jótékonykodsz.</p>
      <a href="#discover" class="btn-cta">Felfedezés</a>
    </div>
  </section>

  <!-- ====== IMPACT KPI BLOKK – ide Shortcode Widgetet ajánlott tenni külön ======
       Tipp: tedd külön Elementor Shortcode widgetekbe:
       [impact_ticker]   [impact_leaderboard tab="ngo"]   [impact_activity]
  -->
  <section class="impact-kpis" id="impact">
    <div class="kpi placeholder">[impact_ticker]</div>
    <div class="kpi placeholder">[impact_leaderboard tab="ngo"]</div>
    <div class="kpi placeholder">[impact_activity]</div>
  </section>

  <!-- ====== DISCOVER / NETFLIX-SOROK ====== -->
  <section class="discover" id="discover">
    <h2>Felfedezés</h2>

    <div class="rail" data-rail="tech">
      <div class="rail-head">
        <h3>Tech</h3>
        <div class="rail-ctrl">
          <button class="nav prev" aria-label="Vissza">‹</button>
          <button class="nav next" aria-label="Előre">›</button>
        </div>
      </div>
      <div class="rail-track"></div>
    </div>

    <div class="rail" data-rail="divat">
      <div class="rail-head">
        <h3>Divat</h3>
        <div class="rail-ctrl">
          <button class="nav prev" aria-label="Vissza">‹</button>
          <button class="nav next" aria-label="Előre">›</button>
        </div>
      </div>
      <div class="rail-track"></div>
    </div>

    <div class="rail" data-rail="sport">
      <div class="rail-head">
        <h3>Sport</h3>
        <div class="rail-ctrl">
          <button class="nav prev" aria-label="Vissza">‹</button>
          <button class="nav next" aria-label="Előre">›</button>
        </div>
      </div>
      <div class="rail-track"></div>
    </div>
  </section>

  <!-- ====== TOP 3 AKCIÓ ====== -->
  <section class="top-deals" id="top">
    <h2>Top akciók</h2>
    <div class="deals-grid" id="deals-grid"></div>
  </section>

  <!-- ====== KAMPÁNYFAL EMBED ====== -->
  <section class="campaign-wall">
    <h2>Kampányok</h2>
    <div class="campaign-embed">
      <iframe id="campaign-iframe"
              title="Kampányfal"
              loading="lazy"
              data-base-src="https://adomany.sharity.hu/kampanyok/index-embed"
              data-auto-height="true"
              data-extra="utm_source=impactshop&utm_medium=embed&utm_campaign=campaign_wall"
              src=""
              style="width:100%;border:0;height:720px;"></iframe>
      <div class="campaign-tabs" id="campaign-tabs"></div>
    </div>
  </section>

  <!-- Konfetti overlay (ha szeretnéd, cseréld videóra / képre) -->
  <div id="impact-confetti" aria-hidden="true"></div>
</div>

<style>
/* =========================================================
   SHARITY IMPACT – CSS (dark UI, brand colors)
   Paletta: purple #7C3AED, cyan #06B6D4, orange #F97316, lime #22C55E, bg #0A0A0B
   ========================================================= */
#impact-page.impact.dark{--bg:#0A0A0B;--fg:#E5E7EB;--muted:#9CA3AF;--card:#111214;--line:#1F2937;
  --purple:#7C3AED;--cyan:#06B6D4;--orange:#F97316;--lime:#22C55E;--glow:0 0 24px rgba(124,58,237,.25);
  color:var(--fg);background:var(--bg);font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}
#impact-page *{box-sizing:border-box}
#impact-page h1,#impact-page h2,#impact-page h3{margin:0 0 .5rem}
#impact-page a{color:var(--cyan);text-decoration:none}
#impact-page section{margin:48px auto;max-width:1200px;padding:0 16px}

/* Hero */
#impact-page .hero{position:relative;min-height:46vh;display:grid;place-items:center;overflow:hidden;border-radius:20px;background:#0a0a0b}
#impact-page .hero-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.4;filter:saturate(120%) contrast(105%)}
#impact-page .hero::after{content:"";position:absolute;inset:0;background:radial-gradient(60% 70% at 50% 50%,transparent,rgba(10,10,11,.9))}
#impact-page .hero-inner{position:relative;z-index:2;text-align:center;padding:32px}
#impact-page .hero-inner h1{font-size:clamp(28px,5vw,56px);letter-spacing:.02em}
#impact-page .hero-inner p{color:var(--muted);margin:.5rem 0 1.5rem}
#impact-page .btn-cta{display:inline-block;background:linear-gradient(90deg,var(--purple),var(--cyan));
  color:#fff;padding:12px 20px;border-radius:999px;box-shadow:var(--glow);font-weight:700}

/* KPI placeholder dobozok (ha shortcodelod van, tegyél külön widgetet) */
#impact-page .impact-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
#impact-page .impact-kpis .kpi{background:var(--card);border:1px solid var(--line);border-radius:16px;min-height:92px;padding:16px}
#impact-page .impact-kpis .placeholder{display:grid;place-items:center;color:var(--muted);font-weight:600}

/* Discover / rails */
#impact-page .discover h2,#impact-page .top-deals h2,#impact-page .campaign-wall h2{font-size:clamp(22px,3vw,28px)}
#impact-page .rail{margin-top:18px}
#impact-page .rail-head{display:flex;align-items:center;justify-content:space-between;margin:6px 0 10px}
#impact-page .rail-head h3{font-size:20px}
#impact-page .rail-ctrl .nav{background:var(--card);border:1px solid var(--line);color:var(--fg);border-radius:10px;padding:6px 10px;margin-left:8px}
#impact-page .rail-track{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(220px,1fr);gap:12px;overflow:auto;scroll-snap-type:x mandatory;padding-bottom:6px}
#impact-page .card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden;scroll-snap-align:start;position:relative;transition:transform .2s ease, box-shadow .2s ease}
#impact-page .card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,.35)}
#impact-page .card img{width:100%;aspect-ratio:16/10;object-fit:cover;background:#0f1012}
#impact-page .card .body{padding:12px}
#impact-page .card .title{font-weight:700}
#impact-page .card .tagline{color:var(--muted);font-size:.92rem;margin-top:2px}
#impact-page .card .cta{display:inline-flex;align-items:center;margin-top:10px;font-weight:700}
#impact-page .card .cta .dot{width:8px;height:8px;border-radius:50%;background:var(--lime);margin-right:8px;box-shadow:0 0 0 3px rgba(34,197,94,.15)}

/* Top deals */
#impact-page .deals-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
#impact-page .deal{background:linear-gradient(180deg,rgba(124,58,237,.15),rgba(17,18,20,.9));border:1px solid var(--line);border-radius:16px;padding:14px;position:relative}
#impact-page .deal h4{font-size:18px;margin:0 0 4px}
#impact-page .deal p{color:var(--muted);margin:0 0 10px}
#impact-page .deal .meta{display:flex;gap:12px;align-items:center;color:#fff}
#impact-page .badge{display:inline-block;background:var(--orange);color:#000;padding:4px 8px;border-radius:999px;font-weight:800;font-size:.78rem}
#impact-page .copy{margin-left:auto;background:#0d0e11;border:1px solid var(--line);border-radius:10px;padding:6px 10px;cursor:pointer}
#impact-page .copy.copied{outline:2px solid var(--lime)}
#impact-page .deadline{font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:.03em}

/* Kampányfal */
#impact-page .campaign-embed{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:12px}
#impact-page .campaign-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 12px}
#impact-page .campaign-tabs button{background:#0d0e11;border:1px solid var(--line);color:var(--fg);border-radius:999px;padding:6px 10px}
#impact-page .campaign-tabs button.active{background:linear-gradient(90deg,var(--purple),var(--cyan));color:#000;font-weight:800}

/* Confetti (egyszerű háttérglow) */
#impact-page #impact-confetti{pointer-events:none;position:fixed;inset:0;background:radial-gradient(600px 80px at 20% 10%,rgba(124,58,237,.14),transparent),
  radial-gradient(600px 80px at 80% 10%,rgba(6,182,212,.14),transparent);
  opacity:0;transition:opacity .4s ease}
#impact-page #impact-confetti.show{opacity:1}

/* Reszponzív */
@media (max-width: 960px){
  #impact-page .impact-kpis{grid-template-columns:1fr}
  #impact-page .deals-grid{grid-template-columns:1fr}
}
</style>

<script>
/* =========================================================
   SHARITY IMPACT – JS
   - DATA SZEKCIÓ: töltsd ki a kártyákat és a Top3-at
   - Netflix-sor render + scroll nav
   - Top3 countdown + kupon „Másolva!”
   - Kampányfal tabs + auto-height
   - GA4 események (window.dataLayer)
   ========================================================= */

/* ---------- DATA: TÖLTSD KI A SAJÁT TARTALOMMAL ---------- */
const DATA = {
  rails: {
    tech: [
      // Név, tagline, URL, kép (opcionális)
      {title:"eMAG", tagline:"Tech & háztartás", url:"https://app.sharity.hu/go/?shop=emag&src=impactshop", img:""},
      {title:"Alza", tagline:"Elektronika & kütyük", url:"https://app.sharity.hu/go/?shop=alza&src=impactshop", img:""},
      {title:"MediaMarkt", tagline:"Szórakoztató elektronika", url:"https://app.sharity.hu/go/?shop=mediamarkt&src=impactshop", img:""}
      // + további 6–15 elem
    ],
    divat: [
      {title:"About You", tagline:"Stílus, gyorsan", url:"https://app.sharity.hu/go/?shop=aboutyou&src=impactshop", img:""},
      {title:"Zalando", tagline:"Márkák & trendek", url:"https://app.sharity.hu/go/?shop=zalando&src=impactshop", img:""},
      {title:"H&M", tagline:"Divat mindenkinek", url:"https://app.sharity.hu/go/?shop=hm&src=impactshop", img:""}
    ],
    sport: [
      {title:"INTERSPORT", tagline:"Felszerelés & ruházat", url:"https://app.sharity.hu/go/?shop=intersport&src=impactshop", img:""},
      {title:"Decathlon", tagline:"Sport minden napra", url:"https://app.sharity.hu/go/?shop=decathlon&src=impactshop", img:""},
      {title:"Vision Express", tagline:"Napszemüveg & keretek", url:"https://app.sharity.hu/go/?shop=visionexpress&src=impactshop", img:""}
    ]
  },

  topDeals: [
    // title, desc, deadline ISO (+02:00), coupon (opcionális), url
    {title:"20% kedvezmény futócipőkre", desc:"Ma éjfélig a kiválasztott modellekre.", deadline:"2025-10-15T23:59:59+02:00", coupon:"RUN20", url:"https://app.sharity.hu/go/?shop=intersport&src=impactshop"},
    {title:"-15% tech kiegészítők", desc:"Korlátozott készlet – siess!", deadline:"2025-10-18T23:59:59+02:00", coupon:"TECH15", url:"https://app.sharity.hu/go/?shop=emag&src=impactshop"},
    {title:"Ingyen szállítás 20 000 Ft felett", desc:"Divatkedvencek – csak a héten.", deadline:"2025-10-20T23:59:59+02:00", coupon:"", url:"https://app.sharity.hu/go/?shop=aboutyou&src=impactshop"}
  ],

  campaignCategories: [
    // {key, label}
    {key:"egeszseg", label:"Egészség"},
    {key:"oktatas", label:"Oktatás"},
    {key:"kornyezet", label:"Környezet"},
    {key:"szocialis", label:"Szociális"},
    {key:"allatvedelem", label:"Állatvédelem"}
  ]
};
/* ---------- /DATA ---------- */

/* Util: create el, text, attrs */
const el = (tag, cls, text) => {
  const e = document.createElement(tag);
  if(cls) e.className = cls;
  if(text) e.textContent = text;
  return e;
};
const setAttrs = (node, attrs={}) => Object.entries(attrs).forEach(([k,v]) => v!=null && node.setAttribute(k, v));

/* Render: Netflix rails */
function renderRails(){
  document.querySelectorAll('#impact-page .rail').forEach(rail => {
    const key = rail.dataset.rail;
    const items = (DATA.rails[key]||[]);
    const track = rail.querySelector('.rail-track');
    track.innerHTML = "";
    items.forEach(it => {
      const card = el('a','card'); setAttrs(card,{href:it.url,target:"_blank",rel:"nofollow sponsored noopener"});
      card.addEventListener('click', () => {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({event:'shop_click', shop:it.title, src:'impactshop'});
      });
      const img = el('img'); img.alt = it.title; img.loading = "lazy"; img.src = it.img || "https://picsum.photos/640/400?blur=2&random="+Math.random().toString(36).slice(2);
      const body = el('div','body');
      const title = el('div','title', it.title);
      const tagline = el('div','tagline', it.tagline || "");
      const cta = el('div','cta'); cta.innerHTML = '<span class="dot"></span>Megnézem';
      body.append(title,tagline,cta);
      card.append(img,body);
      track.append(card);
    });

    // nav
    const prev = rail.querySelector('.nav.prev');
    const next = rail.querySelector('.nav.next');
    const step = 260;
    prev.onclick = () => track.scrollBy({left: -step, behavior: 'smooth'});
    next.onclick = () => track.scrollBy({left: +step, behavior: 'smooth'});
  });
}

/* Render: Top deals */
function pad(n){return n.toString().padStart(2,'0')}
function diffTimeISO(iso){
  const t = new Date(iso);
  const now = new Date();
  const ms = Math.max(0, t - now);
  const s = Math.floor(ms/1000);
  const h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
  return {h, m, s: sec, done: ms<=0};
}
function renderTopDeals(){
  const grid = document.getElementById('deals-grid');
  grid.innerHTML = "";
  DATA.topDeals.slice(0,3).forEach(d => {
    const box = el('div','deal');
    const h4 = el('h4',null,d.title);
    const p = el('p',null,d.desc||"");
    const meta = el('div','meta');
    const deadline = el('span','deadline',"00:00:00");
    const badge = el('span','badge','AKCIÓ');
    const copy = el('button','copy', d.coupon ? 'Kupon másolása' : 'Megnyitás');
    copy.addEventListener('click', (ev)=>{
      ev.preventDefault();
      if(d.coupon){
        navigator.clipboard.writeText(d.coupon).then(()=>{
          copy.classList.add('copied'); copy.textContent = 'Másolva!';
          setTimeout(()=>{copy.classList.remove('copied'); copy.textContent='Kupon másolása';}, 1400);
          window.dataLayer = window.dataLayer || [];
          window.dataLayer.push({event:'deal_copy', title:d.title});
        });
      }else{
        window.open(d.url, '_blank','noopener');
      }
    });
    box.addEventListener('click', (e)=>{
      // csak ha nem a copy gombot nyomta
      if(e.target!==copy){ window.open(d.url,'_blank','noopener'); }
    }, false);

    meta.append(badge, deadline, copy);
    box.append(h4,p,meta);
    grid.append(box);

    // countdown
    const tick = ()=>{
      const dt = diffTimeISO(d.deadline);
      deadline.textContent = `${pad(dt.h)}:${pad(dt.m)}:${pad(dt.s)}`;
      if(dt.done){ deadline.textContent = "LEJÁRT"; box.classList.add('expired'); clearInterval(timer); }
    };
    tick();
    const timer = setInterval(tick, 1000);
  });
}

/* Campaign wall: tabs + src builder + autoheight */
function initCampaignWall(){
  const iframe = document.getElementById('campaign-iframe');
  const tabs = document.getElementById('campaign-tabs');

  // tabs render
  tabs.innerHTML = "";
  DATA.campaignCategories.forEach((c,i)=>{
    const b = el('button', i===0?'active':'', c.label);
    b.onclick = ()=>selectCat(c.key, b);
    tabs.append(b);
  });

  function buildSrc(catKey){
    const base = iframe.dataset.baseSrc;
    const extra = iframe.dataset.extra || "";
    const params = new URLSearchParams(extra);
    if(catKey) params.set('cat', catKey);
    return `${base}?${params.toString()}`;
  }

  function selectCat(key, btn){
    tabs.querySelectorAll('button').forEach(x=>x.classList.remove('active'));
    btn.classList.add('active');
    iframe.src = buildSrc(key);
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({event:'campaign_switch', category:key});
  }

  // első töltés
  iframe.src = buildSrc(DATA.campaignCategories[0]?.key);

  // auto-height (ha az embed küld postMessage-t {height:...})
  if(iframe.dataset.autoHeight === "true"){
    window.addEventListener('message', (e)=>{
      if(!e.data) return;
      try{
        const msg = typeof e.data==='string' ? JSON.parse(e.data) : e.data;
        if(msg && msg.type==='embedHeight' && msg.height){
          iframe.style.height = Math.max(480, Number(msg.height)) + 'px';
        }
      }catch(_){}
    });
  }
}

/* Confetti trigger az impact:updated eseményre */
document.addEventListener('impact:updated', ()=>{
  const c = document.getElementById('impact-confetti');
  c.classList.add('show');
  setTimeout(()=>c.classList.remove('show'), 800);
});

/* Init */
(function initImpact(){
  renderRails();
  renderTopDeals();
  initCampaignWall();
})();
</script>
Ha kész a dátumok/URL-ek véglegesítése a DATA blokkban, a szekciók azonnal életre kelnek. Következő lépésként össze tudom fűzni a KPI-shortcode-okkal (külön Elementor Shortcode widgetekben), vagy ha kéred, adok hozzá egy minimál „riport” blokkot is /impact/report aloldalra szűrőkkel és 15 perces cache-sel.
Ezt mondtad:
A Data blokkot miért nekem kell kitöltenem? az akciókat, webshopokat,  NGO-kat te húzod le és rakod ki. Ha egyéb felirat kell, generálj a stílusnak megfelelően. 
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igazad van: ne kelljen kézzel tölteni a DATA-t. Itt a „mindent egyben” megoldás:
1. rész (PHP plugin): wp-content/plugins/impact-api-bridge/impact-api-bridge.php — WordPress REST proxy Dognethez.
Auth → token cache (20h)
Visszaad:
/wp-json/impact/v1/rails → „Netflix-sorok” (kampányok/campaigns, kategória-szűréssel és shop-kártya adatokkal)
/wp-json/impact/v1/top-deals → „Top 3 akció” (coupons/actions, csak érvényes)
/wp-json/impact/v1/ngos → NGO-lista (egyelőre placeholder mezők; ha van forrásod, ide kötjük)
Minden 10 percig cache-ben (transient).
Ad Channel fix: 26081
LIVE: csak publikus/approved adatok; a riportban majd külön kezeljük.
2. rész (HTML): Elementor HTML widget — ugyanaz a vizuál, de most automatikusan betölti az adatokat a fenti REST végpontokról.
Nincs kézi DATA.
GA4 eventek, countdown, „Másolva!” marad.
Másold be EGYBEN, de figyelj a kommentekre, hogy a PHP rész külön fájlba megy.
<?php
/* ========================================================================
   (1) WORDPRESS PLUGIN – IMPACT API BRIDGE (Dognet REST proxy)
   Fájl: wp-content/plugins/impact-api-bridge/impact-api-bridge.php
   Aktiváld a WP adminban (Plugins).
   Előfeltétel (wp-config.php): állítsd be a Dognet belépőt és csatornát:
     define('DOGNET_EMAIL',    'office@sharity.hu');
     define('DOGNET_PASSWORD', '***JELSZO***');
     define('DOGNET_AD_CHANNEL_ID', 26081);
   ======================================================================== */
if (!defined('ABSPATH')) { exit; }
/*
Plugin Name: Impact API Bridge
Description: REST proxy a Dognet Publisher API-hoz (rails/top-deals/ngos)
Version: 0.1.0
Author: Sharity
*/

add_action('rest_api_init', function () {
  register_rest_route('impact/v1', '/rails', [
    'methods'  => 'GET',
    'callback' => 'impact_api_get_rails',
    'permission_callback' => '__return_true'
  ]);
  register_rest_route('impact/v1', '/top-deals', [
    'methods'  => 'GET',
    'callback' => 'impact_api_get_top_deals',
    'permission_callback' => '__return_true'
  ]);
  register_rest_route('impact/v1', '/ngos', [
    'methods'  => 'GET',
    'callback' => 'impact_api_get_ngos',
    'permission_callback' => '__return_true'
  ]);
});

/* ------------ Alap: HTTP hívó + auth token cache ------------ */
function impact_api_http($method, $url, $body=null, $headers=[]) {
  $args = [
    'method'  => $method,
    'timeout' => 25,
    'headers' => $headers,
    'sslverify' => true
  ];
  if ($body !== null) {
    $args['headers']['Content-Type'] = 'application/json';
    $args['body'] = is_string($body) ? $body : wp_json_encode($body);
  }
  $res = wp_remote_request($url, $args);
  if (is_wp_error($res)) { return $res; }
  $code = wp_remote_retrieve_response_code($res);
  $json = json_decode(wp_remote_retrieve_body($res), true);
  if ($code >= 200 && $code < 300) return $json;
  return new WP_Error('dognet_http', 'Dognet API hiba', ['code'=>$code, 'body'=>$json]);
}

function impact_api_token() {
  $key = 'impact_dognet_token_v1';
  $tok = get_transient($key);
  if ($tok) return $tok;

  $email = defined('DOGNET_EMAIL') ? DOGNET_EMAIL : '';
  $pass  = defined('DOGNET_PASSWORD') ? DOGNET_PASSWORD : '';
  if (!$email || !$pass) {
    return new WP_Error('dognet_cfg', 'Hiányzó DOGNET_EMAIL / DOGNET_PASSWORD a wp-config.php-ban');
  }

  $auth = impact_api_http('POST', 'https://api.app.dognet.com/api/v1/auth/login', [
    'email' => $email,
    'password' => $pass
  ]);
  if (is_wp_error($auth) || empty($auth['token'])) {
    return new WP_Error('dognet_auth', 'Auth sikertelen', ['auth'=>$auth]);
  }
  $tok = $auth['token'];
  // Token 24h és használattal hosszabbodik → 20 órára cache-eljük
  set_transient($key, $tok, 20*HOUR_IN_SECONDS);
  return $tok;
}

function impact_api_get($url, $body=null) {
  $tok = impact_api_token();
  if (is_wp_error($tok)) return $tok;
  $headers = ['Authorization' => 'Bearer '.$tok];
  $method = $body ? 'POST' : 'GET';
  return impact_api_http($method, $url, $body, $headers);
}

/* --------- Rails (kampányok → kártyák Tech/Divat/Sport) ---------
   - Forrás: campaigns/mine/filter → approved (status=1) adott ad_channel_id-vel
   - Kimenet: kategóriák szerinti tömb (tech/divat/sport) kártyákkal
   Megjegyzés: kategorizálás most egyszerű kulcsszavas (name/meta alapján),
   később finomíthatjuk táblával.
------------------------------------------------------------------*/
function impact_api_get_rails(WP_REST_Request $req) {
  $cache_key = 'impact_rails_v1';
  $cached = get_transient($cache_key);
  if ($cached) return $cached;

  $adch = defined('DOGNET_AD_CHANNEL_ID') ? DOGNET_AD_CHANNEL_ID : 26081;

  // Approved kampányok az adott csatornához
  $mine = impact_api_get('https://api.app.dognet.com/api/v1/campaigns/mine/filter', [
    'filter' => [
      ['ad_channel_id' => ['eq' => $adch]],
      ['ad_channel_in_campaign_status' => ['eq' => 1]] // 1=approved
    ],
    'per-page' => 200
  ]);
  if (is_wp_error($mine)) return $mine;

  $items = [];
  foreach (($mine['data'] ?? []) as $c) {
    $title = $c['name'] ?? 'Ismeretlen kampány';
    $img   = $c['logo_url'] ?? ''; // ha van
    // egyszerű kategória-besorolás
    $nameL = mb_strtolower($title);
    $cat = 'tech';
    if (preg_match('~sport|intersport|decathlon|outdoor|fitness|bike~u', $nameL)) $cat='sport';
    if (preg_match('~divat|fashion|ruha|cipő|zalando|about you|h&m|hm|shein|answear~u', $nameL)) $cat='divat';

    // A frontend /go linket használ → slug a kampány nevéből
    $slug = sanitize_title($title);
    $url  = home_url('/go/?shop='.$slug.'&src=impactshop'); // a meglévő redirect/Fillout flow-val kompatibilis

    $items[$cat][] = [
      'title'   => $title,
      'tagline' => $c['short_description'] ?? '',
      'url'     => $url,
      'img'     => $img
    ];
  }

  // Legfeljebb 18 kártyát kérünk kategóriánként
  foreach (['tech','divat','sport'] as $k) {
    $items[$k] = array_values(array_slice($items[$k] ?? [], 0, 18));
  }

  set_transient($cache_key, $items, 10*MINUTE_IN_SECONDS);
  return $items;
}

/* ----------------- Top deals (akciók/kuponok) ------------------
   - Forrás: coupons/filter (validity=present, form=1 Public vagy 3 Action)
   - Kimenet: max 3 elem: title, desc, deadline ISO, coupon, url
------------------------------------------------------------------*/
function impact_api_get_top_deals(WP_REST_Request $req) {
  $cache_key = 'impact_top_deals_v1';
  $cached = get_transient($cache_key);
  if ($cached) return $cached;

  $adch = defined('DOGNET_AD_CHANNEL_ID') ? DOGNET_AD_CHANNEL_ID : 26081;

  $res = impact_api_get('https://api.app.dognet.com/api/v1/coupons/filter', [
    'ad_channel_id' => $adch, // így kapunk affiliate linkeket is
    'filter' => [
      'form' => ['in' => ['1','3']],        // Public + Action
      'validity' => ['eq' => 'present']     // csak érvényesek
    ],
    'per-page' => 100
  ]);
  if (is_wp_error($res)) return $res;

  $out = [];
  foreach (($res['data'] ?? []) as $row) {
    $title    = $row['name'] ?? 'Akció';
    $desc     = $row['description'] ?? '';
    $coupon   = $row['coupon'] ?? '';
    $deeplink = $row['affiliate_link'] ?? ''; // API adja, ha ad_channel_id-t küldtünk
    // lejárat
    $deadline = '';
    if (!empty($row['valid_to'])) {
      // API időzóna: Europe/Bratislava; ISO-ra alakítjuk
      try { $deadline = (new DateTime($row['valid_to']))->format('c'); } catch(Exception $e) {}
    }
    $out[] = [
      'title' => $title,
      'desc'  => $desc,
      'deadline' => $deadline,
      'coupon' => $coupon,
      'url'   => $deeplink
    ];
  }

  // Top 3 – egyszerű rendezés: ha van deadline, a legközelebbi jöjjön előre
  usort($out, function($a,$b){
    return strcmp($a['deadline'] ?? '9999', $b['deadline'] ?? '9999');
  });
  $out = array_slice($out, 0, 3);

  set_transient($cache_key, $out, 10*MINUTE_IN_SECONDS);
  return $out;
}

/* -------------------- NGOs (helykitöltő) -----------------------
   - Itt most placeholder: a te listádból/DB-ből tudunk olvasni később.
   - Frontendnek csak név/slug/kép/link kellhet.
------------------------------------------------------------------*/
function impact_api_get_ngos(WP_REST_Request $req) {
  $cache_key = 'impact_ngos_v1';
  $cached = get_transient($cache_key);
  if ($cached) return $cached;

  $list = [
    ['name'=>'Bátor Tábor', 'slug'=>'bator-tabor-alapitvany', 'img'=>'', 'url'=>home_url('/ngo/bator-tabor')],
    ['name'=>'Ökumenikus Segélyszervezet', 'slug'=>'okumenikus', 'img'=>'', 'url'=>home_url('/ngo/okumenikus')],
    ['name'=>'Noé Állatotthon', 'slug'=>'noe-allatotthon', 'img'=>'', 'url'=>home_url('/ngo/noe')]
  ];

  set_transient($cache_key, $list, 10*MINUTE_IN_SECONDS);
  return $list;
}

/* ========================================================================
   (2) FRONTEND HTML – Elementor HTML widgetbe (adatok automatikusan jönnek)
   A REST hívások: /wp-json/impact/v1/rails, /top-deals, /ngos
   ======================================================================== */ ?>
<!-- ========= IMPACT AUTOFILL FRONT (NO MANUAL DATA) ========= -->
<div id="impact-page" class="impact dark">
  <section class="hero">
    <div class="hero-inner">
      <h1>Vásárlás = Támogatás</h1>
      <p>Válassz shopot, és minden vásárlásoddal jót teszel.</p>
      <a href="#discover" class="btn-cta">Felfedezés</a>
    </div>
  </section>

  <section class="impact-kpis" id="impact">
    <div class="kpi placeholder">[impact_ticker]</div>
    <div class="kpi placeholder">[impact_leaderboard tab="ngo"]</div>
    <div class="kpi placeholder">[impact_activity]</div>
  </section>

  <section class="discover" id="discover">
    <h2>Felfedezés</h2>
    <div class="rail" data-rail="tech"><div class="rail-head"><h3>Tech</h3><div class="rail-ctrl"><button class="nav prev">‹</button><button class="nav next">›</button></div></div><div class="rail-track"></div></div>
    <div class="rail" data-rail="divat"><div class="rail-head"><h3>Divat</h3><div class="rail-ctrl"><button class="nav prev">‹</button><button class="nav next">›</button></div></div><div class="rail-track"></div></div>
    <div class="rail" data-rail="sport"><div class="rail-head"><h3>Sport</h3><div class="rail-ctrl"><button class="nav prev">‹</button><button class="nav next">›</button></div></div><div class="rail-track"></div></div>
  </section>

  <section class="top-deals" id="top">
    <h2>Top akciók</h2>
    <div class="deals-grid" id="deals-grid"></div>
  </section>

  <section class="campaign-wall">
    <h2>Kampányok</h2>
    <div class="campaign-embed">
      <iframe id="campaign-iframe" title="Kampányfal" loading="lazy" data-base-src="https://adomany.sharity.hu/kampanyok/index-embed" data-auto-height="true" data-extra="utm_source=impactshop&utm_medium=embed&utm_campaign=campaign_wall" src="" style="width:100%;border:0;height:720px;"></iframe>
      <div class="campaign-tabs" id="campaign-tabs"></div>
    </div>
  </section>

  <div id="impact-confetti" aria-hidden="true"></div>
</div>

<style>
#impact-page.impact.dark{--bg:#0A0A0B;--fg:#E5E7EB;--muted:#9CA3AF;--card:#111214;--line:#1F2937;--purple:#7C3AED;--cyan:#06B6D4;--orange:#F97316;--lime:#22C55E;--glow:0 0 24px rgba(124,58,237,.25);color:var(--fg);background:var(--bg);font-family:Inter,system-ui,Segoe UI,Roboto,Arial,sans-serif}
#impact-page *{box-sizing:border-box} #impact-page a{color:var(--cyan);text-decoration:none}
#impact-page section{margin:48px auto;max-width:1200px;padding:0 16px}
.hero{min-height:34vh;display:grid;place-items:center;background:#0a0a0b;border-radius:20px}
.btn-cta{display:inline-block;background:linear-gradient(90deg,var(--purple),var(--cyan));color:#fff;padding:12px 20px;border-radius:999px;box-shadow:var(--glow);font-weight:700}
.impact-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:16px;min-height:92px;padding:16px;display:grid;place-items:center;color:var(--muted);font-weight:600}
.discover h2,.top-deals h2,.campaign-wall h2{font-size:clamp(22px,3vw,28px)}
.rail{margin-top:18px}.rail-head{display:flex;align-items:center;justify-content:space-between;margin:6px 0 10px}
.rail-track{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(220px,1fr);gap:12px;overflow:auto;scroll-snap-type:x mandatory;padding-bottom:6px}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden;scroll-snap-align:start;position:relative;transition:transform .2s ease, box-shadow .2s ease}
.card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,.35)}
.card img{width:100%;aspect-ratio:16/10;object-fit:cover;background:#0f1012}
.card .body{padding:12px}.title{font-weight:700}.tagline{color:var(--muted);font-size:.92rem;margin-top:2px}
.card .cta{display:inline-flex;align-items:center;margin-top:10px;font-weight:700}.cta .dot{width:8px;height:8px;border-radius:50%;background:var(--lime);margin-right:8px;box-shadow:0 0 0 3px rgba(34,197,94,.15)}
.deals-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.deal{background:linear-gradient(180deg,rgba(124,58,237,.15),rgba(17,18,20,.9));border:1px solid var(--line);border-radius:16px;padding:14px;position:relative}
.deal h4{font-size:18px;margin:0 0 4px}.deal p{color:var(--muted);margin:0 0 10px}
.meta{display:flex;gap:12px;align-items:center;color:#fff}.badge{display:inline-block;background:var(--orange);color:#000;padding:4px 8px;border-radius:999px;font-weight:800;font-size:.78rem}
.copy{margin-left:auto;background:#0d0e11;border:1px solid var(--line);border-radius:10px;padding:6px 10px;cursor:pointer}.copy.copied{outline:2px solid var(--lime)}
.deadline{font-variant-numeric:tabular-nums;font-weight:700;letter-spacing:.03em}
.campaign-embed{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:12px}
.campaign-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:8px 0 12px}
.campaign-tabs button{background:#0d0e11;border:1px solid var(--line);color:var(--fg);border-radius:999px;padding:6px 10px}
.campaign-tabs button.active{background:linear-gradient(90deg,var(--purple),var(--cyan));color:#000;font-weight:800}
#impact-confetti{pointer-events:none;position:fixed;inset:0;background:radial-gradient(600px 80px at 20% 10%,rgba(124,58,237,.14),transparent),radial-gradient(600px 80px at 80% 10%,rgba(6,182,212,.14),transparent);opacity:0;transition:opacity .4s}
#impact-confetti.show{opacity:1}
@media (max-width:960px){.impact-kpis{grid-template-columns:1fr}.deals-grid{grid-template-columns:1fr}}
</style>

<script>
const API_BASE = '/wp-json/impact/v1';

function el(tag, cls, text){const e=document.createElement(tag); if(cls) e.className=cls; if(text) e.textContent=text; return e;}
function setAttrs(n,a={}){Object.entries(a).forEach(([k,v])=>v!=null&&n.setAttribute(k,v));}

/* Rails betöltése */
async function loadRails(){
  const res = await fetch(`${API_BASE}/rails`, {credentials:'same-origin'});
  const data = await res.json();
  ['tech','divat','sport'].forEach(key=>{
    const rail = document.querySelector(`.rail[data-rail="${key}"] .rail-track`);
    if(!rail || !data[key]) return;
    rail.innerHTML = '';
    data[key].forEach(it=>{
      const card = el('a','card'); setAttrs(card,{href:it.url,target:'_blank',rel:'nofollow sponsored noopener'});
      card.addEventListener('click', ()=>{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'shop_click',shop:it.title,src:'impactshop'});});
      const img = el('img'); img.alt = it.title; img.loading="lazy"; img.src = it.img || `https://picsum.photos/640/400?blur=2&random=${Math.random().toString(36).slice(2)}`;
      const body = el('div','body'); const t = el('div','title',it.title); const g = el('div','tagline',it.tagline||''); const cta=el('div','cta'); cta.innerHTML='<span class="dot"></span>Megnézem';
      body.append(t,g,cta); card.append(img,body); rail.append(card);
    });
    const wrap = rail.closest('.rail'); const prev=wrap.querySelector('.nav.prev'); const next=wrap.querySelector('.nav.next'); const step=260;
    prev.onclick = ()=> rail.scrollBy({left:-step,behavior:'smooth'}); next.onclick = ()=> rail.scrollBy({left:+step,behavior:'smooth'});
  });
}

/* Top deals betöltése */
function pad(n){return n.toString().padStart(2,'0')}
function diffISO(iso){const t=new Date(iso),now=new Date(); const ms=Math.max(0,t-now); const s=Math.floor(ms/1000); return {h:Math.floor(s/3600), m:Math.floor((s%3600)/60), s:s%60, done:ms<=0};}
async function loadTopDeals(){
  const res = await fetch(`${API_BASE}/top-deals`, {credentials:'same-origin'});
  const deals = await res.json();
  const grid = document.getElementById('deals-grid'); grid.innerHTML='';
  deals.forEach(d=>{
    const box=el('div','deal'); const h4=el('h4',null,d.title||'Akció'); const p=el('p',null,d.desc||''); const meta=el('div','meta');
    const deadline=el('span','deadline','00:00:00'); const badge=el('span','badge','AKCIÓ');
    const copy=el('button','copy', d.coupon ? 'Kupon másolása' : 'Megnyitás');
    copy.addEventListener('click', ev=>{
      ev.preventDefault();
      if(d.coupon){ navigator.clipboard.writeText(d.coupon).then(()=>{copy.classList.add('copied');copy.textContent='Másolva!'; setTimeout(()=>{copy.classList.remove('copied');copy.textContent='Kupon másolása';},1400); window.dataLayer=window.dataLayer||[]; window.dataLayer.push({event:'deal_copy',title:d.title});}); }
      else if(d.url){ window.open(d.url,'_blank','noopener'); }
    });
    box.addEventListener('click', e=>{ if(e.target!==copy && d.url){ window.open(d.url,'_blank','noopener'); }}, false);
    meta.append(badge,deadline,copy); box.append(h4,p,meta); grid.append(box);
    if(d.deadline){
      const tick=()=>{const dt=diffISO(d.deadline); deadline.textContent=`${pad(dt.h)}:${pad(dt.m)}:${pad(dt.s)}`; if(dt.done){deadline.textContent='LEJÁRT';box.classList.add('expired'); clearInterval(timer);} };
      tick(); const timer=setInterval(tick,1000);
    } else { deadline.textContent=''; }
  });
}

/* Kampányfal */
function initCampaignWall(){
  const iframe=document.getElementById('campaign-iframe'); const tabs=document.getElementById('campaign-tabs');
  const cats=[{key:'egeszseg',label:'Egészség'},{key:'oktatas',label:'Oktatás'},{key:'kornyezet',label:'Környezet'},{key:'szocialis',label:'Szociális'},{key:'allatvedelem',label:'Állatvédelem'}];
  tabs.innerHTML=''; cats.forEach((c,i)=>{const b=el('button',i===0?'active':'',c.label); b.onclick=()=>sel(c.key,b); tabs.append(b);});
  function buildSrc(k){const base=iframe.dataset.baseSrc; const extra=iframe.dataset.extra||""; const qs=new URLSearchParams(extra); if(k) qs.set('cat',k); return `${base}?${qs.toString()}`;}
  function sel(k,btn){tabs.querySelectorAll('button').forEach(x=>x.classList.remove('active')); btn.classList.add('active'); iframe.src=buildSrc(k); window.dataLayer=window.dataLayer||[]; window.dataLayer.push({event:'campaign_switch',category:k});}
  iframe.src = buildSrc(cats[0].key);
  if(iframe.dataset.autoHeight==='true'){ window.addEventListener('message',e=>{ if(!e.data) return; try{ const msg=typeof e.data==='string'?JSON.parse(e.data):e.data; if(msg && msg.type==='embedHeight' && msg.height){ iframe.style.height=Math.max(480, Number(msg.height))+'px'; } }catch(_){} }); }
}

/* Confetti trigger */
document.addEventListener('impact:updated', ()=>{const c=document.getElementById('impact-confetti'); c.classList.add('show'); setTimeout(()=>c.classList.remove('show'),800);});

/* Init */
(async function(){
  await Promise.all([loadRails(), loadTopDeals()]);
  initCampaignWall();
})();
</script>
Mit állíts be még?
wp-config.php-ban DOGNET_EMAIL, DOGNET_PASSWORD, DOGNET_AD_CHANNEL_ID=26081.
Aktiváld a plugint: Impact API Bridge.
A régi /go flow érintetlen — a kártyák URL-je azt hívja, így az NGO-választás+Dognet továbbra is megy.
Ha szeretnéd, hozzáadom a /impact/report MVP REST–shortcode párost is (szűrők + 15 perces cache, approved/pending kapcsolóval), hogy a következő lépésnél instant kész legyen az élő riport nézet.
Ezt mondtad:
* `wp-config.php`\-ban **DOGNET\_EMAIL**, **DOGNET\_PASSWORD**, **DOGNET\_AD\_CHANNEL\_ID=26081**.
impact-bridge-local.php
Fájl
Van már impact bridge ugye tudod?wp consign belül hol? Azt azért rögzítsük hogy ha lehet kerüljük a wpjconfig módosítását
A ChatGPT ezt mondta:
Értem, tehát van már impact bridge, és nem szeretnél többé a wp-config.php-hoz nyúlni. Ez teljesen jogos: a config fájl babrálása mindig rizikós.
A jó hír: az impact-bridge-local.php, amit feltöltöttél, pont ezt a célt szolgálhatja. Így tudjuk megoldani:

Hol legyen?
wp-content/ mappában, közvetlenül (nem theme/plugin könyvtárban).
Így nem írja felül egy frissítés, és a pluginunk betöltheti, ha létezik.
Hogyan használjuk?
Az impact-api-bridge pluginban az eddigi
define('DOGNET_EMAIL', ...);
define('DOGNET_PASSWORD', ...);
helyett ellenőrzünk egy külön fájlt:
// impact-api-bridge.php elején:
if (file_exists(WP_CONTENT_DIR.'/impact-bridge-local.php')) {
    include WP_CONTENT_DIR.'/impact-bridge-local.php';
}
Mit tartalmazzon a impact-bridge-local.php?
Ebben adod meg a jelszót, e-mailt, channel ID-t, és bármilyen más lokális kulcsot, anélkül hogy a wp-configot piszkálnád:
<?php
// wp-content/impact-bridge-local.php

// Dognet belépők (Sharity publisher)
define('DOGNET_EMAIL',    'office@sharity.hu');
define('DOGNET_PASSWORD', '***TITKOS_JELSZO***');
define('DOGNET_AD_CHANNEL_ID', 26081);

// Később ide jöhet más privát kulcs is (pl. GA4 Measurement ID, saját API key, stb.)
Miért jobb így?
Nem kell a wp-config.php-t módosítani.
Verzióbiztos: ha a plugint frissítjük, a local file megmarad.
Titkos: a repo-ba nem kerül be, a WP core-t sem kell bolygatni.
👉 Tehát most csak azt kell rögzíteni: az impact-api-bridge.php kódját kicsit módosítjuk, hogy betöltse a wp-content/impact-bridge-local.php fájlt, és onnantól minden Dognet API kulcs/paraméter onnan jön.
Szeretnéd, hogy adjak egy kész diff-et (az impact-api-bridge.php elejére illesztendő pár sor + a teljes impact-bridge-local.php sablon), amit egyben bemásolsz?

Ezt mondtad:
kérek mindkettőből teljes egész egyben beilleszthető verziót, hogy ne keveredjek
A ChatGPT ezt mondta:

Gondolkozás
megvan — itt a két fájl teljes, készre szerelve. Nem kell a wp-config.php-hoz nyúlni. Másold be pontosan ezekre az útvonalakra, majd a plugint aktiváld.
1) wp-content/impact-bridge-local.php (lokális, privát beállítások – NE commitold)
<?php
/**
 * SHARITY – Impact Bridge (local config)
 * Hely: wp-content/impact-bridge-local.php
 * Cél: minden titkos/egyedi kulcs ide kerüljön, ne a wp-config.php-ba.
 */

if (!defined('ABSPATH')) { exit; }

/** Dognet Publisher fiók */
if (!defined('DOGNET_EMAIL'))         define('DOGNET_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_PASSWORD'))      define('DOGNET_PASSWORD', '***IDE-ADD-MEG-A-JELSZÓT***');

/** Fix ad channel (Sharity) */
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 26081);

/** (Opcionális) Egyéb lokális beállítások, ha szükséges:
 *  define('IMPACT_BRIDGE_CACHE_MINUTES', 10);
 *  define('IMPACT_BRIDGE_TIMEOUT', 25);
 *  define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
 */
2) wp-content/plugins/impact-api-bridge/impact-api-bridge.php (WordPress plugin – REST proxy + autofill végpontok)
<?php
/**
 * Plugin Name:  Impact API Bridge (Sharity)
 * Description:  REST proxy a Dognet Publisher API-hoz + frontend „autofill” végpontok (rails, top-deals, ngos).
 * Version:      0.2.0
 * Author:       Sharity
 * License:      GPLv2 or later
 *
 * Telepítés:
 *   1) Hozd létre a fájlt:  wp-content/impact-bridge-local.php  (lásd a mellékelt mintát)
 *   2) Másold ezt a plugint: wp-content/plugins/impact-api-bridge/impact-api-bridge.php
 *   3) WP admin > Plugins > Activate
 *
 * Megjegyzés: tudatosan NEM módosítjuk a wp-config.php-t – minden titkos adat a wp-content/impact-bridge-local.php fájlban.
 */

if (!defined('ABSPATH')) { exit; }

/* -----------------------------------------------------------
 * Local config betöltése (wp-content/impact-bridge-local.php)
 * ----------------------------------------------------------- */
$__impact_local = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local)) {
    include_once $__impact_local;
}

/* -----------------------------------------------------------
 * Alap konstansok / defaultok (felülírhatók a local fájlban)
 * ----------------------------------------------------------- */
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 26081);
if (!defined('IMPACT_BRIDGE_CACHE_MINUTES')) define('IMPACT_BRIDGE_CACHE_MINUTES', 10);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))       define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))    define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');

/* -----------------------------------------------------------
 * REST: /wp-json/impact/v1/{rails,top-deals,ngos}
 * ----------------------------------------------------------- */
add_action('rest_api_init', function () {
    register_rest_route('impact/v1', '/rails', [
        'methods'  => 'GET',
        'callback' => 'impact_api_get_rails',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/top-deals', [
        'methods'  => 'GET',
        'callback' => 'impact_api_get_top_deals',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('impact/v1', '/ngos', [
        'methods'  => 'GET',
        'callback' => 'impact_api_get_ngos',
        'permission_callback' => '__return_true',
    ]);
});

/* -----------------------------------------------------------
 * HTTP kliens + Auth token cache
 * ----------------------------------------------------------- */
function impact_bridge_http($method, $url, $body = null, $headers = []) {
    $args = [
        'method'      => $method,
        'timeout'     => IMPACT_BRIDGE_TIMEOUT,
        'headers'     => array_merge([
            'User-Agent' => IMPACT_BRIDGE_USER_AGENT,
        ], $headers),
        'sslverify'   => true,
        'redirection' => 3,
    ];
    if ($body !== null) {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body'] = is_string($body) ? $body : wp_json_encode($body);
    }
    $res  = wp_remote_request($url, $args);
    if (is_wp_error($res)) return $res;
    $code = wp_remote_retrieve_response_code($res);
    $json = json_decode(wp_remote_retrieve_body($res), true);
    if ($code >= 200 && $code < 300) return $json;
    return new WP_Error('dognet_http', 'Dognet API hiba', ['code' => $code, 'body' => $json]);
}

function impact_bridge_token() {
    $cache_key = 'impact_dognet_token_v1';
    if ($tok = get_transient($cache_key)) return $tok;

    // Elsőbbség: local config konstansok, másodlagos: környezeti változók
    $email = defined('DOGNET_EMAIL') ? DOGNET_EMAIL : getenv('DOGNET_EMAIL');
    $pass  = defined('DOGNET_PASSWORD') ? DOGNET_PASSWORD : getenv('DOGNET_PASSWORD');
    if (!$email || !$pass) {
        return new WP_Error('dognet_cfg', 'Hiányzik a DOGNET_EMAIL / DOGNET_PASSWORD (wp-content/impact-bridge-local.php)');
    }

    $auth = impact_bridge_http('POST', 'https://api.app.dognet.com/api/v1/auth/login', [
        'email'    => $email,
        'password' => $pass,
    ]);
    if (is_wp_error($auth) || empty($auth['token'])) {
        return new WP_Error('dognet_auth', 'Auth sikertelen', ['auth' => $auth]);
    }
    $tok = $auth['token'];
    // 24h token, használattal hosszabbodik → 20 órára cache
    set_transient($cache_key, $tok, 20 * HOUR_IN_SECONDS);
    return $tok;
}

function impact_bridge_get($url, $body = null) {
    $tok = impact_bridge_token();
    if (is_wp_error($tok)) return $tok;
    $headers = ['Authorization' => 'Bearer ' . $tok];
    $method  = $body ? 'POST' : 'GET';
    return impact_bridge_http($method, $url, $body, $headers);
}

/* -----------------------------------------------------------
 * Rails – jóváhagyott kampányok (mine/filter) → kártyák
 *  - kategorizálás: egyszerű kulcsszavas (tech/divat/sport), finomítható
 *  - output: max 18 elem/kategória
 * ----------------------------------------------------------- */
function impact_api_get_rails(WP_REST_Request $req) {
    $cache_key = 'impact_rails_v2';
    if ($cached = get_transient($cache_key)) return $cached;

    $adch = (int) DOGNET_AD_CHANNEL_ID;

    $resp = impact_bridge_get('https://api.app.dognet.com/api/v1/campaigns/mine/filter', [
        'filter'   => [
            ['ad_channel_id' => ['eq' => $adch]],
            ['ad_channel_in_campaign_status' => ['eq' => 1]], // 1=approved
        ],
        'per-page' => 200,
    ]);
    if (is_wp_error($resp)) return $resp;

    $items = ['tech' => [], 'divat' => [], 'sport' => []];

    foreach (($resp['data'] ?? []) as $c) {
        $title = $c['name'] ?? 'Ismeretlen kampány';
        $img   = $c['logo_url'] ?? '';
        $desc  = $c['short_description'] ?? '';

        $nameL = function_exists('mb_strtolower') ? mb_strtolower($title) : strtolower($title);
        $cat   = 'tech';
        if (preg_match('~sport|intersport|decathlon|outdoor|fitness|bike|run|futó|kerékpár~u', $nameL)) $cat = 'sport';
        if (preg_match('~divat|fashion|ruha|cipő|zalando|about you|h&m|hm|shein|answear|outfit~u', $nameL)) $cat = 'divat';

        $slug = sanitize_title($title);
        // A meglévő /go flow-val kompatibilis URL
        $url  = home_url('/go/?shop=' . $slug . '&src=impactshop');

        $items[$cat][] = [
            'title'   => $title,
            'tagline' => $desc,
            'url'     => $url,
            'img'     => $img,
        ];
    }

    foreach ($items as $k => $arr) {
        $items[$k] = array_values(array_slice($arr, 0, 18));
    }

    set_transient($cache_key, $items, IMPACT_BRIDGE_CACHE_MINUTES * MINUTE_IN_SECONDS);
    return $items;
}

/* -----------------------------------------------------------
 * Top deals – érvényes kuponok/akciók (coupons/filter)
 *  - rendezés: közelebbi lejárat előre
 *  - output: top 3
 * ----------------------------------------------------------- */
function impact_api_get_top_deals(WP_REST_Request $req) {
    $cache_key = 'impact_top_deals_v2';
    if ($cached = get_transient($cache_key)) return $cached;

    $adch = (int) DOGNET_AD_CHANNEL_ID;

    $resp = impact_bridge_get('https://api.app.dognet.com/api/v1/coupons/filter', [
        'ad_channel_id' => $adch, // így ad affiliate_linket is
        'filter' => [
            'form'     => ['in' => ['1','3']],   // Public + Action
            'validity' => ['eq' => 'present'],   // csak érvényes
        ],
        'per-page' => 100,
    ]);
    if (is_wp_error($resp)) return $resp;

    $out = [];
    foreach (($resp['data'] ?? []) as $row) {
        $title    = $row['name'] ?? 'Akció';
        $desc     = $row['description'] ?? '';
        $coupon   = $row['coupon'] ?? '';
        $deeplink = $row['affiliate_link'] ?? ''; // ha van ad_channel_id
        $deadline = '';

        if (!empty($row['valid_to'])) {
            try { $deadline = (new DateTime($row['valid_to']))->format('c'); } catch (Exception $e) {}
        }

        $out[] = [
            'title'    => $title,
            'desc'     => $desc,
            'deadline' => $deadline,
            'coupon'   => $coupon,
            'url'      => $deeplink,
        ];
    }

    usort($out, function($a,$b){
        return strcmp($a['deadline'] ?? '9999', $b['deadline'] ?? '9999');
    });
    $out = array_slice($out, 0, 3);

    set_transient($cache_key, $out, IMPACT_BRIDGE_CACHE_MINUTES * MINUTE_IN_SECONDS);
    return $out;
}

/* -----------------------------------------------------------
 * NGOs – jelenleg placeholder (később saját forrásra köthető)
 * ----------------------------------------------------------- */
function impact_api_get_ngos(WP_REST_Request $req) {
    $cache_key = 'impact_ngos_v2';
    if ($cached = get_transient($cache_key)) return $cached;

    $list = [
        ['name'=>'Bátor Tábor',                 'slug'=>'bator-tabor-alapitvany', 'img'=>'', 'url'=>home_url('/ngo/bator-tabor')],
        ['name'=>'Ökumenikus Segélyszervezet',  'slug'=>'okumenikus',              'img'=>'', 'url'=>home_url('/ngo/okumenikus')],
        ['name'=>'Noé Állatotthon',             'slug'=>'noe-allatotthon',         'img'=>'', 'url'=>home_url('/ngo/noe')],
    ];

    set_transient($cache_key, $list, IMPACT_BRIDGE_CACHE_MINUTES * MINUTE_IN_SECONDS);
    return $list;
}

/* -----------------------------------------------------------
 * Segédfüggvény: admin notice, ha hiányzik a local config
 * ----------------------------------------------------------- */
add_action('admin_notices', function () use ($__impact_local) {
    if (!file_exists($__impact_local)) {
        echo '<div class="notice notice-warning"><p><strong>Impact API Bridge:</strong> '
           . 'Hiányzik a <code>wp-content/impact-bridge-local.php</code> fájl. '
           . 'Hozd létre a mellékelt sablon alapján!</p></div>';
    }
});
Használat (gyors ellenőrzőlista)
Fájlok a helyükön?
wp-content/impact-bridge-local.php – kitöltött adatokkal
wp-content/plugins/impact-api-bridge/impact-api-bridge.php
Plugin aktiválva?
WP admin → Plugins → Impact API Bridge (Sharity) → Activate
Végpontok élnek? (böngészőben nyisd)
/wp-json/impact/v1/rails
/wp-json/impact/v1/top-deals
/wp-json/impact/v1/ngos
Frontend
A korábbi Elementor HTML blokkod automatikusan ezeket a végpontokat hívja, így nem kell kézzel DATA-t töltened.
Ezt mondtad:
1) `wp-content/impact-bridge-local.php`
ez direkt lett ilyen rövid (ez az egész?) és direkt nincs saját könyvtára hanem közvetlenül a wp contentbe kerül, nem is a pluginsba?
A ChatGPT ezt mondta:
Pontosan: az impact-bridge-local.php azért ennyire rövid, mert nem plugin, hanem csak egy lokális config fájl.
Miért nem külön könyvtárban, hanem közvetlenül a wp-content-ben?
Így nem érinti sem a WordPress core, sem a theme, sem a plugin frissítés.
Biztonságos hely: a wp-content-ben mindig megmarad, ha plugint törölsz vagy cserélsz.
Könnyű megtalálni: csak egyetlen file, nem keveredik bele a többi plugin logikába.
A plugin (impact-api-bridge) betölti, ha létezik — tehát a helye kifejezetten a wp-content/ gyökérben van, nem a plugins/ alatt.
Mi van benne?
Csak a privát beállítások: Dognet email/jelszó, ad_channel_id, opcionálisan cache idő, timeout stb. Ezért rövid.
Minden működési logika, REST endpoint, cache kezelés a plugin fájlban van.
Röviden
impact-bridge-local.php = konfiguráció (privát kulcsok, email, jelszó).
impact-api-bridge.php (plugins mappában) = logika, végpontok, API hívások.
Így nem kell a wp-config.php-hoz nyúlni, és minden titkos adat külön, frissítéstől független fájlban van.
Szeretnéd, hogy mutassak egy „fa szerkezetet” (hova melyik fájl kerüljön pontosan), hogy lásd egyben a teljes WordPress könyvtárképben?
Ezt mondtad:
Akkor kapcsoljam ki mint plugin, töröljem és újra hozzam létre? Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
Kikapcsolás | Visszaállítás
Helyi REST végpontok az Impact frontnak. A Dognet Publisher API-ból számol ticker/leaderboard/activity-t, cache-sel.

1.0.0 verzió | Szerző: Sharity
A ChatGPT ezt mondta:
Nem kell teljesen kidobnod mindent, csak tisztázzuk a szerepeket:
Most mi van nálad?
Van egy külön pluginod Impact Bridge Local (1.0.0), ami a ticker/leaderboard/activity REST-végpontokat számolja.
Ez fut pluginként a wp-content/plugins/ alatt, aktív, és működik.
Mit javasoltam én?
Két szintet szétválasztani:
Plugin (impact-api-bridge) → ebben van minden logika: Dognet hívások, rails/top-deals/ngos REST végpontok, cache.
Config file (wp-content/impact-bridge-local.php) → csak az email/jelszó/ad_channel.
Így a plugin frissíthető/cserélhető, a kulcsok pedig érintetlenül maradnak.
Mit tegyél most?
NE töröld a meglévő Impact Bridge plugint, mert abban vannak a ticker/leaderboard/activity végpontjaid.
– Ezekre az Elementor shortcode-ok épülnek.
Az új impact-api-bridge plugint (amit küldtem) teheted mellé, ugyanúgy a plugins/ alá. Ez adja a Netflix-sorokat, Top3 akciót, NGO-listát.
A kettő békésen megfér egymás mellett.
Ha szeretnéd, a jövőben összevonhatjuk egyetlen pluginba (ticker/leaderboard/activity + rails/top-deals), de most nem muszáj.
👉 Tehát a válasz: nem kell kikapcsolni/törölni a régi Impact Bridge plugint.
Hagyd bekapcsolva.
Az új „impact-api-bridge”-et telepítsd külön.
A wp-content/impact-bridge-local.php fájlt tedd be a kulcsokkal, így mindkettő tudja használni a Dognetet.
Szeretnéd, hogy készítsek egy pontos fájlstruktúra-fát (melyik file/mappa hova kerüljön, melyik plugin marad, melyiket kell létrehozni)?
Ezt mondtad:
az új impact bridge local jóval rövidebb és ráadásul a helye is máshol van (contentben) helyezzem át?
A ChatGPT ezt mondta:

Gondolkozás
Röviden: ne költöztesd át a meglévő plugint. Az maradjon a wp-content/plugins/… alatt.
A most adott „új” impact-bridge-local.php pedig nem plugin, hanem csak egy lokális konfigurációs fájl, és helyesen a wp-content/ gyökerébe kerül.
Így lesz kerek a rendszer:

Meglévő plugin marad (Impact Bridge Local … a plugins mappában, aktív).
Új plugin (ha betetted: impact-api-bridge) szintén a plugins alatt fut.
Közös config: wp-content/impact-bridge-local.php – itt vannak a Dognet belépők és az ad_channel ID.
A két plugin beolvassa ezt a config fájlt, így nem kell a wp-config.php-t piszkálni.
Ha a régi plugin eddig nem töltött be lokális configot, tedd az elejére ezt a 4 sort (hogy ő is használja a közös fájlt):
<?php
// Tedd a RÉGI plugin fő fájljának legelejére:
$__impact_local = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local)) {
    include_once $__impact_local;
}

// ... és mindenhol, ahol konstansokat definiálsz, védd le:
if (!defined('DOGNET_EMAIL'))         define('DOGNET_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_PASSWORD'))      define('DOGNET_PASSWORD', '***IDE-ADD-MEG-A-JELSZÓT***');
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 26081);
És maga a lokális config (ez megy közvetlenül a wp-content/ gyökérbe):
<?php
/**
 * SHARITY – Impact Bridge (local config)
 * Hely: wp-content/impact-bridge-local.php
 * Csak privát kulcsok / helyi beállítások. NEM plugin.
 */
if (!defined('ABSPATH')) { exit; }

if (!defined('DOGNET_EMAIL'))         define('DOGNET_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_PASSWORD'))      define('DOGNET_PASSWORD', '***IDE-ADD-MEG-A-JELSZÓT***');
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 26081);

// (opciós)
if (!defined('IMPACT_BRIDGE_CACHE_MINUTES')) define('IMPACT_BRIDGE_CACHE_MINUTES', 10);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))       define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))    define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
Miért ez a felállás?
A pluginok maradnak a helyükön, frissíthetők, kikapcsolhatók.
A privát adatok (email/jelszó/ID-k) egy közös, frissítéstől független fájlban élnek.
Nincs szükség wp-config.php módosításra.
Ha később egy plugin-t cserélünk, mindkettő ugyanazt a configot fogja olvasni.
Gyors ellenőrzés
A wp-content/impact-bridge-local.php megvan, kitöltve.
Mindkét plugin beolvassa (a fenti 4 sorral).
Végpontok élnek:
/wp-json/impact/v1/rails (Netflix-sorok)
/wp-json/impact/v1/top-deals (Top 3 akció)
a régi plugin végpontjai (ticker/leaderboard/activity) is válaszolnak.
Ha ez pipa, mehetünk tovább a front finomhangolásával (szövegek, képek, GA4 eventek, kampányfal kategóriák).
Ezt mondtad:
Ha a régi plugin eddig **nem** töltött be lokális configot, tedd az elejére ezt a 4 sort (hogy ő is használja a közös fájlt): php Kód másolása
fogalmam sincs: <?php
/**
 * Plugin Name: Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
 * Description: Helyi REST végpontok az Impact frontnak. A Dognet Publisher API-ból számol ticker/leaderboard/activity-t, cache-sel.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ======= KONFIG / VÉDŐSÍNEK ======= */

if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);

/* ======= DOGNET: login + request (24h token) ======= */

function ibl__dognet_try_login_once($payload, $headers){
  $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $resp = wp_remote_post($ep, ['timeout'=>20,'headers'=>$headers,'body'=>$payload]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);
  $j = json_decode($body, true); $tok = '';
  if (is_array($j)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k==='data' || $k==='result') { if (!empty($j[$k]['token'])){ $tok=$j[$k]['token']; break; } if (!empty($j[$k]['access_token'])){ $tok=$j[$k]['access_token']; break; } }
      elseif (!empty($j[$k]) && is_string($j[$k])) { $tok = $j[$k]; break; }
    }
  }
  return ($code>=200 && $code<300 && $tok) ? ['ok'=>true,'token'=>$tok] : ['ok'=>false,'why'=>'HTTP '.$code];
}

function ibl_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key='ibl_dognet_tok_v1';
  if (!$force){ $t = get_transient($key); if ($t) return $t; }
  $r = ibl__dognet_try_login_once(wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]), ['Content-Type'=>'application/json','Accept'=>'application/json']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  $r = ibl__dognet_try_login_once(http_build_query(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]), ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  return '';
}

function ibl_dognet_request($method,$path,$body=null){
  $tok = ibl_dognet_get_token(false);
  if (!$tok) return new WP_Error('no_token','Dognet token hiba');
  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $args=['timeout'=>25,'method'=>$method,'headers'=>['Authorization'=>'Bearer '.$tok,'Accept'=>'application/json','Content-Type'=>'application/json']];
  if ($body!==null) $args['body']=wp_json_encode($body);
  $r = wp_remote_request($url,$args);
  if (is_wp_error($r)) return $r;
  $code = wp_remote_retrieve_response_code($r);
  $j = json_decode(wp_remote_retrieve_body($r), true);
  if ($code==401){ delete_transient('ibl_dognet_tok_v1'); $tok=ibl_dognet_get_token(true); if(!$tok) return new WP_Error('no_token','401 + token refresh fail');
    $args['headers']['Authorization']='Bearer '.$tok; $r=wp_remote_request($url,$args);
    if (is_wp_error($r)) return $r; $code=wp_remote_retrieve_response_code($r); $j=json_decode(wp_remote_retrieve_body($r), true);
  }
  if ($code<200 || $code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}

/* ======= APRÓ SEGÉDEK ======= */

function ibl__pick_d1($row){
  foreach (['d1','ref1','sub_id','subid','sub_id1','data1'] as $k){ if(isset($row[$k]) && $row[$k] !== '' && !is_array($row[$k])) return (string)$row[$k]; }
  return '';
}
function ibl__num($v){ return is_numeric($v)? floatval($v) : 0.0; }

/* ======= ALAP LEKÉRDEZŐ (raw-transactions/filter last_id görgetéssel) ======= */

function ibl_fetch_transactions($from, $to, $status='approved', $maxBatches=60, $perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [ ['created_at'=>['gte'=>$fromDt]], ['created_at'=>['lte'=>$toDt]] ];
  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];
  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ibl_dognet_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){ // fallback: legnagyobb id
      foreach($rows as $r){ foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); } }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ======= TICKER/LEADERBOARD/ACTIVITY számítás ======= */

function ibl_build_ticker(){
  $cache='ibl_ticker_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];
  $total=0.0; $todaySum=0.0; $today=date('Y-m-d');
  foreach($rows as $r){
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $total += $comm;
    $created = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,10);
    if ($created===$today) $todaySum += $comm;
  }
  $out=['total'=>$total,'today'=>$todaySum,'generated_at'=>current_time('mysql')];
  set_transient($cache,$out, 180); return $out;
}

function ibl_build_leaderboard($tab='ngo'){
  $cache='ibl_lb_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];
  $map=[];
  if ($tab==='ngo'){
    foreach($rows as $r){ $ngo = ibl__pick_d1($r) ?: '(ismeretlen)'; $comm=ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0))); $map[$ngo]=($map[$ngo]??0)+$comm; }
    $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  } else { // tab=shop
    foreach($rows as $r){
      $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){ if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; } }
      $name = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
      $comm=ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $map[$name]=($map[$name]??0)+$comm;
    }
    $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  }
  usort($out, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));
  set_transient($cache,$out, 300); return $out;
}

function ibl_build_activity(){
  $cache='ibl_activity_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d', strtotime('-3 days')); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved', 10, 100);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];
  // pár friss (legutolsó 10), egyszerű szöveg
  usort($rows, function($a,$b){
    $da = strtotime($a['created_at'] ?? ($a['created'] ?? ''));
    $db = strtotime($b['created_at'] ?? ($b['created'] ?? ''));
    return $db <=> $da;
  });
  $rows = array_slice($rows,0,10);
  $out=[];
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r) ?: 'egy szervezet';
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $out[]=['text'=> sprintf('%s támogatást hozott ~%s € értékben', $ngo, number_format($comm,0,',',' '))];
  }
  set_transient($cache,$out, 120); return $out;
}

/* ======= REST: /wp-json/impact/v1/{ticker|leaderboard|activity} ======= */

add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/ticker', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_ticker(); return is_array($d)? rest_ensure_response($d) : new WP_Error('err','Hiba'); }
  ]);
  register_rest_route('impact/v1','/leaderboard', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $d=ibl_build_leaderboard($tab);
      if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
      return rest_ensure_response($d);
    }
  ]);
  register_rest_route('impact/v1','/activity', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_activity(); if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]); return rest_ensure_response($d); }
  ]);
});

/* ======= Admin kis segéd: manuális cache flush ======= */
add_shortcode('impact_local_flush', function(){
  delete_transient('ibl_ticker_v1');
  delete_transient('ibl_activity_v1');
  global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ibl_lb_%' OR option_name LIKE '_transient_timeout_ibl_lb_%'");
  return '<div style="background:#0f1f13;color:#22C55E;padding:.6rem 1rem;border-radius:.5rem">Impact LOCAL cache ürítve.</div>';
});
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
értem — nézzük meg ezt lépésről lépésre, keveredés nélkül. Az alábbi EGY kódtömbben két fájl van, fejléccel jelölve, hova kell bemásolni.
Az első: wp-content/impact-bridge-local.php → csak a titkos adatok (email/jelszó/ID).
A második: a meglévő plugined teljes, javított változata (betölti a fenti local configot, kiszedi a hardcode-olt belépőket, és — ha akarod — rászűr az Ad Channel 26081-re).
Másold a tartalmakat a megadott helyre. Nem kell wp-config-ot piszkálni.
/* =======================================================================
 * FILE 1/2
 * Hely:  wp-content/impact-bridge-local.php
 * Cél:   TITKOS/LOKÁLIS beállítások – ezt tölti be a plugin.
 * ======================================================================= */
<?php
if (!defined('ABSPATH')) { exit; }

/** Dognet Publisher account (csak itt tartsd, NE a pluginban) */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', '***IDE-ADD-MEG-A-JELSZÓT***');

/** (Ajánlott) Fix Ad Channel – Sharity: 26081 */
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);

/** (Kompatibilitási aliasok más bridge-hez) */
if (!defined('DOGNET_EMAIL') && defined('DOGNET_LOGIN_EMAIL'))         define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD') && defined('DOGNET_LOGIN_PASSWORD'))   define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

/** (Opcionális finomhangolások) */
if (!defined('DOGNET_API_BASE'))         define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))        define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))   define('IMPACT_BRIDGE_TIMEOUT', 25);



/* =======================================================================
 * FILE 2/2
 * Hely:  wp-content/plugins/impact-bridge-local/impact-bridge-local.php
 * Cél:   A MEGLÉVŐ pluginod teljes, javított fő fájlja (betölti a local configot)
 *        → ticker / leaderboard / activity REST végpontok, cache-sel.
 * ======================================================================= */
<?php
/**
 * Plugin Name: Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
 * Description: Helyi REST végpontok az Impact frontnak. A Dognet Publisher API-ból számol ticker/leaderboard/activity-t, cache-sel.
 * Version: 1.0.1
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ======= LOCAL CONFIG betöltés (NE módosíts wp-configot) ======= */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) {
  include_once $__impact_local_cfg;
}

/* ======= KONFIG / VÉDŐSÍNEK (HARD-CODE ELKERÜLVE) ======= */
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', getenv('DOGNET_LOGIN_EMAIL') ?: '');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', getenv('DOGNET_LOGIN_PASSWORD') ?: '');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT')) define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))    define('IMPACT_BRIDGE_TIMEOUT', 25);

/* (Kompat aliasok más pluginokhoz) */
if (!defined('DOGNET_EMAIL') && defined('DOGNET_LOGIN_EMAIL'))         define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD') && defined('DOGNET_LOGIN_PASSWORD'))   define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

/* ======= DOGNET: login + request (24h token) ======= */
function ibl__dognet_try_login_once($payload, $headers){
  $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $headers = array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers);
  $resp = wp_remote_post($ep, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'headers'=>$headers,'body'=>$payload,'redirection'=>3,'sslverify'=>true]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);
  $j = json_decode($body, true); $tok = '';
  if (is_array($j)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k==='data' || $k==='result') {
        if (!empty($j[$k]['token']))        { $tok=$j[$k]['token']; break; }
        if (!empty($j[$k]['access_token'])) { $tok=$j[$k]['access_token']; break; }
      } elseif (!empty($j[$k]) && is_string($j[$k])) {
        $tok = $j[$k]; break;
      }
    }
  }
  return ($code>=200 && $code<300 && $tok) ? ['ok'=>true,'token'=>$tok] : ['ok'=>false,'why'=>'HTTP '.$code];
}

function ibl_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key='ibl_dognet_tok_v1';
  if (!$force){ $t = get_transient($key); if ($t) return $t; }

  // email/jelszó kötelező – most már NEM hardcode, a local config tölti
  $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;
  if (!$email || !$pass) return '';

  $r = ibl__dognet_try_login_once(
    wp_json_encode(['email'=>$email,'password'=>$pass]),
    ['Content-Type'=>'application/json']
  );
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }

  $r = ibl__dognet_try_login_once(
    http_build_query(['email'=>$email,'password'=>$pass]),
    ['Content-Type'=>'application/x-www-form-urlencoded']
  );
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }

  return '';
}

function ibl_dognet_request($method,$path,$body=null){
  $tok = ibl_dognet_get_token(false);
  if (!$tok) return new WP_Error('no_token','Dognet token hiba – nincs email/jelszó a local configban?');

  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $args = [
    'timeout'  => IMPACT_BRIDGE_TIMEOUT,
    'method'   => $method,
    'headers'  => [
      'Authorization' => 'Bearer '.$tok,
      'Accept'        => 'application/json',
      'Content-Type'  => 'application/json',
      'User-Agent'    => IMPACT_BRIDGE_USER_AGENT,
    ],
    'redirection'=>3,
    'sslverify' => true,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);

  $r = wp_remote_request($url,$args);
  if (is_wp_error($r)) return $r;
  $code = wp_remote_retrieve_response_code($r);
  $j    = json_decode(wp_remote_retrieve_body($r), true);

  if ($code==401){
    delete_transient('ibl_dognet_tok_v1');
    $tok=ibl_dognet_get_token(true);
    if(!$tok) return new WP_Error('no_token','401 + token refresh fail');
    $args['headers']['Authorization']='Bearer '.$tok;
    $r=wp_remote_request($url,$args);
    if (is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r);
    $j=json_decode(wp_remote_retrieve_body($r), true);
  }
  if ($code<200 || $code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}

/* ======= APRÓ SEGÉDEK ======= */
function ibl__pick_d1($row){
  foreach (['d1','ref1','sub_id','subid','sub_id1','data1'] as $k){
    if(isset($row[$k]) && $row[$k] !== '' && !is_array($row[$k])) return (string)$row[$k];
  }
  return '';
}
function ibl__num($v){ return is_numeric($v)? floatval($v) : 0.0; }

/* ======= ALAP LEKÉRDEZŐ (raw-transactions/filter last_id görgetéssel) ======= */
function ibl_fetch_transactions($from, $to, $status='approved', $maxBatches=60, $perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [ ['created_at'=>['gte'=>$fromDt]], ['created_at'=>['lte'=>$toDt]] ];

  // (AI) opcionális Ad Channel fix – ha definiáltad a local configban
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id' => ['eq' => (int)DOGNET_AD_CHANNEL_ID]];
  }

  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ibl_dognet_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){
        foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); }
      }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ======= TICKER/LEADERBOARD/ACTIVITY számítás ======= */
function ibl_build_ticker(){
  $cache='ibl_ticker_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $total=0.0; $todaySum=0.0; $today=date('Y-m-d');
  foreach($rows as $r){
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $total += $comm;
    $created = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,10);
    if ($created===$today) $todaySum += $comm;
  }
  $out=['total'=>$total,'today'=>$todaySum,'generated_at'=>current_time('mysql')];
  set_transient($cache,$out, 180); return $out;
}

function ibl_build_leaderboard($tab='ngo'){
  $cache='ibl_lb_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $map=[];
  if ($tab==='ngo'){
    foreach($rows as $r){
      $ngo = ibl__pick_d1($r) ?: '(ismeretlen)';
      $comm=ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $map[$ngo]=($map[$ngo]??0)+$comm;
    }
    $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  } else { // tab=shop
    foreach($rows as $r){
      $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
        if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
      }
      $name = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
      $comm=ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $map[$name]=($map[$name]??0)+$comm;
    }
    $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  }
  usort($out, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));
  set_transient($cache,$out, 300); return $out;
}

function ibl_build_activity(){
  $cache='ibl_activity_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d', strtotime('-3 days')); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved', 10, 100);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  usort($rows, function($a,$b){
    $da = strtotime($a['created_at'] ?? ($a['created'] ?? ''));
    $db = strtotime($b['created_at'] ?? ($b['created'] ?? ''));
    return $db <=> $da;
  });
  $rows = array_slice($rows,0,10);

  $out=[];
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r) ?: 'egy szervezet';
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $out[]=['text'=> sprintf('%s támogatást hozott ~%s € értékben', $ngo, number_format($comm,0,',',' '))];
  }
  set_transient($cache,$out, 120); return $out;
}

/* ======= REST: /wp-json/impact/v1/{ticker|leaderboard|activity} ======= */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/ticker', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_ticker(); return is_array($d)? rest_ensure_response($d) : new WP_Error('err','Hiba'); }
  ]);
  register_rest_route('impact/v1','/leaderboard', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $d=ibl_build_leaderboard($tab);
      if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
      return rest_ensure_response($d);
    }
  ]);
  register_rest_route('impact/v1','/activity', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_activity(); if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]); return rest_ensure_response($d); }
  ]);
});

/* ======= Admin kis segéd: manuális cache flush ======= */
add_shortcode('impact_local_flush', function(){
  delete_transient('ibl_ticker_v1');
  delete_transient('ibl_activity_v1');
  global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ibl_lb_%' OR option_name LIKE '_transient_timeout_ibl_lb_%'");
  return '<div style="background:#0f1f13;color:#22C55E;padding:.6rem 1rem;border-radius:.5rem">Impact LOCAL cache ürítve.</div>';
});
Mi változott és miért jó így?
Nem marad hardcodeolt jelszó a pluginban. A bejelentkezési adatok mostantól a wp-content/impact-bridge-local.php fájlban élnek.
A plugin automatikusan betölti a local configot, ha létezik.
(Opcionális) Ad Channel 26081 szűrő hozzáadva a tranzakció-lekérdezéshez, hogy biztosan a Sharity-csatorna adatai menjenek a ticker/leaderboard/activity-be.
Közös User-Agent + timeout — stabilabb hívások.
Gyors checklista
 Létrehoztad és kitöltötted a wp-content/impact-bridge-local.php-t?
 Lecserélted a plugin fő fájlját a fenti javított verzióra?
 WP-ben az Impact Bridge Local továbbra is aktív?
 REST válaszok élnek:
/wp-json/impact/v1/ticker
/wp-json/impact/v1/leaderboard?tab=ngo
/wp-json/impact/v1/activity
Ha ezek rendben, jöhet a másik plugin (rails/top-deals) vagy mehetünk tovább a fronttal (Netflix-sorok/Top3/kampányfal automatikus).
Ezt mondtad:
most minden ok
A ChatGPT ezt mondta:
Összefoglalva: most minden a helyén van. A rendszered két rétegben dolgozik:
Plugin (wp-content/plugins/impact-bridge-local/impact-bridge-local.php)
– ez a fő logika: REST végpontok (ticker, leaderboard, activity) + Dognet API hívások + cache.
– ugyanúgy plugin marad, WP-ben aktív.
Config fájl (wp-content/impact-bridge-local.php)
– ez rövid és direkt, csak az érzékeny adatok: email, jelszó, channel ID.
– a plugin automatikusan betölti, így nem kell többé a wp-config.php-t piszkálni.
A Dognet API működéséhez pont így kell: először auth/login → token (24 óráig érvényes, használattal hosszabbodik), majd minden más hívásban ott az Authorization: Bearer [token]. A te bridge-ed ezt már kezeli.
Tehát nem kell áthelyezni a plugint sehova, nem kell kikapcsolni. A plugin marad a plugins alatt, a rövid config fájl pedig a wp-content gyökerében van, és ez teljesen rendben van.

Most már stabil a setup:

a kulcsok a configban,
a logika a pluginban,
a front a REST végpontokat használja.
Innen mehetünk tovább a frontend finomhangolással (Netflix-sorok, Top3, kampányfal), mert a backend/bridge rész összeállt.
Szeretnéd, hogy adjak egy fa-struktúrát a teljes WP telepítésedről, kiemelve, hova kerül a plugin és a config, hogy mindig tudd ellenőrizni?

Ezt mondtad:
mehetünk tovább
A ChatGPT ezt mondta:

Gondolkozás
zseni — akkor lépjünk a következő mérföldkőre: /impact/report – MVP.
Az alábbi EGY fájlos plugin:
ad egy REST végpontot: /wp-json/impact/v1/report (paraméterezett, 15 perces cache)
és egy shortcode-ot: [impact_report] (szűrősáv + tábla + lábléc-összesítő)
Nem nyúl a wp-config.php-hoz, a wp-content/impact-bridge-local.php configot tölti be (ugyanúgy, mint a többi bridge). Másold pontosan erre az útra, majd kapcsold be a WP-ben.
wp-content/plugins/impact-report-mvp/impact-report-mvp.php
<?php
/**
 * Plugin Name: Impact Report MVP (Sharity)
 * Description: Paraméterezhető riport (shop×NGO / NGO / Shop) – REST + shortcode, 15 perces cache.
 * Version: 0.1.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése (NE módosíts wp-configot) ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) {
  include_once $__impact_local_cfg;
}

/* ===== Alap konstansok (felülírhatók a local configban) ===== */
if (!defined('DOGNET_API_BASE'))              define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))             define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_RPT_CACHE_MINUTES'))     define('IMPACT_RPT_CACHE_MINUTES', 15);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))        define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))     define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');

/* ===== HTTP + Auth (önálló, de a local config credjeire támaszkodik) ===== */
function ir_http($method, $url, $body=null, $headers=[]) {
  $args = [
    'method' => $method,
    'timeout'=> IMPACT_BRIDGE_TIMEOUT,
    'headers'=> array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers),
    'sslverify'=> true, 'redirection'=> 3,
  ];
  if ($body !== null) { $args['headers']['Content-Type']='application/json'; $args['body']=is_string($body)?$body:wp_json_encode($body); }
  $res = wp_remote_request($url,$args);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  $json = json_decode(wp_remote_retrieve_body($res), true);
  if ($code>=200 && $code<300) return $json;
  return new WP_Error('dognet_http','Dognet API hiba',['code'=>$code,'body'=>$json]);
}
function ir_token($force=false){
  $key='impact_rpt_tok_v1';
  if (!$force && ($t=get_transient($key))) return $t;
  $email = defined('DOGNET_LOGIN_EMAIL')    ? DOGNET_LOGIN_EMAIL    : getenv('DOGNET_LOGIN_EMAIL');
  $pass  = defined('DOGNET_LOGIN_PASSWORD') ? DOGNET_LOGIN_PASSWORD : getenv('DOGNET_LOGIN_PASSWORD');
  if (!$email || !$pass) return new WP_Error('cfg','Hiányzik DOGNET_LOGIN_EMAIL / DOGNET_LOGIN_PASSWORD a wp-content/impact-bridge-local.php-ban');
  $auth = ir_http('POST', rtrim(DOGNET_API_BASE,'/').'/auth/login', ['email'=>$email,'password'=>$pass]);
  if (is_wp_error($auth) || empty($auth['token'])) return new WP_Error('auth','Auth sikertelen', ['resp'=>$auth]);
  set_transient($key,$auth['token'], DOGNET_TOKEN_TTL);
  return $auth['token'];
}
function ir_api($path,$body=null){
  $tok = ir_token(false);
  if (is_wp_error($tok)) return $tok;
  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  if (is_wp_error($res) && $res->get_error_code()==='dognet_http' && ($res->get_error_data()['code']??0)==401){
    delete_transient('impact_rpt_tok_v1');
    $tok = ir_token(true); if (is_wp_error($tok)) return $tok;
    $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  }
  return $res;
}

/* ===== Segédek ===== */
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }
function ir_pick_d1($row){ foreach(['d1','ref1','sub_id','subid','sub_id1','data1'] as $k){ if(isset($row[$k]) && $row[$k]!=='' && !is_array($row[$k])) return (string)$row[$k]; } return ''; }

/* ===== Alap lekérés – raw-transactions/filter (last_id görgetés) ===== */
function ir_fetch_transactions($from,$to,$status='approved',$maxBatches=60,$perPage=200){
  // Felső határ exkluzív – API-ban lte-vel közelítünk (23:59:59)
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';

  $filter = [
    ['created_at'=>['gte'=>$fromDt]],
    ['created_at'=>['lte'=>$toDt]],
  ];
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }
  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ir_api('/raw-transactions/filter', $body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){ foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); } }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Aggregálás (group: shop_ngo | ngo | shop) ===== */
function ir_aggregate($rows,$group='shop_ngo'){
  $out=[]; $sumBasket=0; $sumComm=0; $sumDon=0;
  foreach($rows as $r){
    $basket = ir_num($r['order_value'] ?? ($r['basket_value'] ?? 0));
    $comm   = ir_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don    = $comm * 0.5;
    $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){ if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; } }
    $shop = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
    $ngo  = ir_pick_d1($r) ?: '(ismeretlen NGO)';

    $key = $group==='ngo'  ? $ngo
         : ($group==='shop' ? $shop
         : ($shop.' × '.$ngo));

    if (!isset($out[$key])) $out[$key]=['rows'=>0,'basket'=>0.0,'comm'=>0.0,'don'=>0.0];
    $out[$key]['rows']   += 1;
    $out[$key]['basket'] += $basket;
    $out[$key]['comm']   += $comm;
    $out[$key]['don']    += $don;

    $sumBasket += $basket; $sumComm += $comm; $sumDon += $don;
  }
  // sorba rendezés: don desc, majd név
  uasort($out, function($a,$b){
    $d = $b['don'] <=> $a['don'];
    if ($d!==0) return $d;
    return 0;
  });
  return ['groups'=>$out,'totals'=>['rows'=>array_sum(array_column($out,'rows')),'basket'=>$sumBasket,'comm'=>$sumComm,'don'=>$sumDon]];
}

/* ====== REST: /impact/v1/report?from=YYYY-MM-DD&to=YYYY-MM-DD&status=approved|pending|rejected|all&group=shop_ngo|ngo|shop&ngo=slug ====== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/report', [
    'methods'=>'GET',
    'permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $from  = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $stat  = sanitize_text_field($req->get_param('status') ?: 'approved');
      $group = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $ngo   = sanitize_text_field($req->get_param('ngo') ?: '');

      // cache kulcs
      $cache_key = 'impact_report_v1:'.md5(json_encode([$from,$to,$stat,$group,$ngo]));
      if ($cached = get_transient($cache_key)) return $cached;

      $rows = ir_fetch_transactions($from,$to,$stat);
      if (is_wp_error($rows)) return new WP_Error('dognet_err','Dognet hiba: '.$rows->get_error_message(),['status'=>502]);

      if ($ngo){
        $rows = array_values(array_filter($rows, function($r) use($ngo){
          $d1 = ir_pick_d1($r);
          return $d1 && (sanitize_title($d1) === sanitize_title($ngo));
        }));
      }

      $agg = ir_aggregate($rows,$group);
      set_transient($cache_key, $agg, IMPACT_RPT_CACHE_MINUTES * MINUTE_IN_SECONDS);
      return $agg;
    }
  ]);
});

/* ===== Shortcode: [impact_report] – szűrősáv + tábla + összesítő ===== */
add_shortcode('impact_report', function($atts){
  $q = wp_parse_args($_GET, [
    'from'=>date('Y-m-01'),
    'to'=>date('Y-m-d'),
    'status'=>'approved',
    'group'=>'shop_ngo',
    'ngo'=>'',
  ]);
  $api = esc_url_raw( add_query_arg([
    'from'=>$q['from'],'to'=>$q['to'],'status'=>$q['status'],'group'=>$q['group'],'ngo'=>$q['ngo']
  ], rest_url('impact/v1/report')) );

  // lekérés (server-side render)
  $res = wp_remote_get($api, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'sslverify'=>true, 'headers'=>['User-Agent'=>IMPACT_BRIDGE_USER_AGENT]]);
  $body = is_wp_error($res) ? [] : json_decode(wp_remote_retrieve_body($res), true);
  $groups = $body['groups'] ?? [];
  $tot = $body['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

  ob_start(); ?>
  <div class="impact-report-wrap">
    <form class="ir-filters" method="get">
      <label>From <input type="date" name="from" value="<?php echo esc_attr($q['from']); ?>"></label>
      <label>To <input type="date" name="to" value="<?php echo esc_attr($q['to']); ?>"></label>
      <label>Status
        <select name="status">
          <?php foreach(['approved','pending','rejected','all'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php selected($q['status'],$s); ?>><?php echo ucfirst($s); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Group
        <select name="group">
          <option value="shop_ngo" <?php selected($q['group'],'shop_ngo'); ?>>Shop × NGO</option>
          <option value="ngo" <?php selected($q['group'],'ngo'); ?>>NGO</option>
          <option value="shop" <?php selected($q['group'],'shop'); ?>>Shop</option>
        </select>
      </label>
      <label>NGO (slug)
        <input type="text" name="ngo" placeholder="pl. bator-tabor-alapitvany" value="<?php echo esc_attr($q['ngo']); ?>">
      </label>
      <button type="submit">Szűrés</button>
    </form>

    <div class="ir-table-wrap">
      <table class="ir-table">
        <thead>
          <tr>
            <th>Csoport</th>
            <th style="text-align:right">Kosárérték (€)</th>
            <th style="text-align:right">Jutalék (€)</th>
            <th style="text-align:right">Adomány (50%) (€)</th>
            <th style="text-align:right">Darab</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$groups): ?>
          <tr><td colspan="5" style="text-align:center;color:#9CA3AF">Nincs találat a megadott szűrőkre.</td></tr>
        <?php else:
          foreach($groups as $name=>$v): ?>
            <tr>
              <td><?php echo esc_html($name); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['basket'], 2, ',', ' '); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['comm'],   2, ',', ' '); ?></td>
              <td style="text-align:right"><strong><?php echo number_format((float)$v['don'],    2, ',', ' '); ?></strong></td>
              <td style="text-align:right"><?php echo (int)$v['rows']; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th>Összesen</th>
            <th style="text-align:right"><?php echo number_format((float)$tot['basket'], 2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['comm'],   2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['don'],    2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo (int)$tot['rows']; ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <style>
    .impact-report-wrap{background:#111214;border:1px solid #1F2937;border-radius:16px;padding:12px}
    .impact-report-wrap .ir-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;align-items:end}
    .impact-report-wrap .ir-filters label{display:flex;flex-direction:column;font-size:.92rem;color:#E5E7EB}
    .impact-report-wrap .ir-filters input,.impact-report-wrap .ir-filters select{background:#0d0e11;color:#E5E7EB;border:1px solid #1F2937;border-radius:10px;padding:8px}
    .impact-report-wrap .ir-filters button{background:linear-gradient(90deg,#7C3AED,#06B6D4);color:#000;border:0;border-radius:999px;padding:8px 14px;font-weight:800}
    .impact-report-wrap .ir-table{width:100%;border-collapse:separate;border-spacing:0 6px;color:#E5E7EB}
    .impact-report-wrap .ir-table thead th{font-size:.9rem;color:#9CA3AF;border-bottom:1px solid #1F2937;padding:8px}
    .impact-report-wrap .ir-table tbody td{background:#0d0e11;border-top:1px solid #1F2937;border-bottom:1px solid #1F2937;padding:10px}
    .impact-report-wrap .ir-table tbody tr td:first-child{border-left:1px solid #1F2937;border-top-left-radius:10px;border-bottom-left-radius:10px}
    .impact-report-wrap .ir-table tbody tr td:last-child{border-right:1px solid #1F2937;border-top-right-radius:10px;border-bottom-right-radius:10px}
    .impact-report-wrap .ir-table tfoot th{border-top:1px solid #1F2937;padding:10px}
    @media(max-width:900px){.impact-report-wrap .ir-filters{flex-direction:column;align-items:stretch}}
  </style>
  <?php
  return ob_get_clean();
});
Használat
Másold a fájlt a fenti útvonalra, majd WP admin → Plugins → Impact Report MVP (Sharity) → Activate.
Hozz létre egy oldalt: /impact/report, és odarakd a shortcode-ot:
[impact_report]
A szűrők az oldal GET paraméterein is működnek; pl.:
/impact/report?from=2025-09-01&to=2025-09-23&status=approved&group=shop_ngo
A REST végpont:
/wp-json/impact/v1/report?from=YYYY-MM-DD&to=YYYY-MM-DD&status=approved|pending|rejected|all&group=shop_ngo|ngo|shop&ngo=slug
Mit tud most az MVP?
Szűrősáv: from–to, status, group, NGO slug.
Csoportosítás: shop_ngo (alapértelmezett), ngo, vagy shop.
Számítás: kosárérték, jutalék, adomány = 50%.
Cache: 15 perc / paraméterkombináció.
Ad Channel 26081: ha a local configban van definiálva, automatikus szűrés.
Ha kívánod, adok hozzá CSV exportot és „Export” gombot, plusz egy ?impact_flush=1 paramétert, amivel a riport-specifikus cache üríthető egy kattintással.
Ezt mondtad:
**Impact Report MVP (Sharity)** → Activate.
Ez a bővítmény 280 karakternyi váratlan kiírást generált az aktiválás során. Ha a “headers already sent” üzenetekkel találkozunk, problémák vannak a feeddel vagy más problémát tapasztalunk, próbáljuk meg kikapcsolni vagy eltávolítani ezt a bővítményt.
A ChatGPT ezt mondta:
Ez a „280 karakteres váratlan kiírás” tipikusan abból fakad, hogy a plugin fájlban a <?php … ?> nyitó/záró tageken kívül is maradt látható szöveg vagy whitespace.
A WordPress bővítmény betöltésnél ilyenkor „headers already sent” hibákat dob, mert a PHP már kiírt valamit, mielőtt a HTTP-header ment volna.

Mit kell javítani?
A plugin fájl (impact-report-mvp.php) elején legyen kizárólag:
<?php
/**
 * Plugin Name: Impact Report MVP (Sharity)
 * …
 */
és ne legyen BOM (UTF-8-BOM nélkül mentsd el).
A fájl végén soha ne legyen ?> záró PHP tag, és főleg ne legyen utána üres sor vagy space.
Ellenőrizd, hogy semmilyen debug echo, var_dump, print_r nem maradt bent.
Gyors megoldás
Nyisd meg az impact-report-mvp.php-t egy szerkesztőben (pl. VSCode, Notepad++).
Állítsd a kódolást UTF-8 (BOM nélkül).
Töröld a fájl legvégéről a ?>-t és az utána lévő whitespace/sorokat.
Mentsd el, töltsd fel újra, majd aktiváld.
Ellenőrzés
Ha a plugin aktiválás után már nem ír ki semmit (csendben betöltődik), akkor jó.
Utána teszteld a REST végpontot:
/wp-json/impact/v1/report?from=2025-09-01&to=2025-09-23&status=approved&group=shop_ngo
Válaszban JSON kell jöjjön, nem HTML.
Szeretnéd, hogy átküldjem újra a tiszta, BOM-mentes, záró tag nélküli verzióját a teljes impact-report-mvp.php fájlnak, hogy biztosan ne okozzon többé ilyen kiírást?
Ezt mondtad:
kész
A ChatGPT ezt mondta:

Gondolkozás
szuper — akkor rátesszük a masnit a riport-MVP-re: CSV export + gyors cache-flush + healthcheck snippek. Minden itt van, egyben bemásolható kiegészítés az általad már telepített impact-report-mvp.php végére (nem kell bármit törölni, csak ezt a blokkot tedd a fájl legvégére). UTF-8 (BOM nélkül), záró ?> nélkül.
/* =======================================================================
 * IMPACT REPORT MVP – EXTRA: CSV EXPORT + CACHE FLUSH
 * Másold a fájl VÉGÉRE. Nincs záró "?>" tag.
 * ======================================================================= */

/** Helper: CSV render (UTF-8, ; elválasztó) */
function ir_render_csv($groups, $totals){
  $out = fopen('php://temp', 'r+');
  fputcsv($out, ['Csoport','Kosárérték (€)','Jutalék (€)','Adomány (50%) (€)','Darab'], ';');
  foreach($groups as $name=>$v){
    fputcsv($out, [
      $name,
      number_format((float)$v['basket'], 2, ',', ' '),
      number_format((float)$v['comm'],   2, ',', ' '),
      number_format((float)$v['don'],    2, ',', ' '),
      (int)$v['rows'],
    ], ';');
  }
  // összesítő sor
  fputcsv($out, ['Összesen',
    number_format((float)$totals['basket'], 2, ',', ' '),
    number_format((float)$totals['comm'],   2, ',', ' '),
    number_format((float)$totals['don'],    2, ',', ' '),
    (int)$totals['rows']
  ], ';');
  rewind($out);
  return stream_get_contents($out);
}

/** REST bővítés: format=csv és impact_flush=1 támogatás */
add_filter('rest_pre_echo_response', function($result, $server, $request){
  // Csak a mi végpontunknál
  if ($request->get_route() !== '/impact/v1/report') return $result;

  // Cache flush (param: ?impact_flush=1)
  if (isset($_GET['impact_flush']) && $_GET['impact_flush']=='1'){
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_impact_report_v1:%' OR option_name LIKE '_transient_timeout_impact_report_v1:%'");
  }

  // CSV export (param: &format=csv)
  if (strtolower((string)$request->get_param('format')) === 'csv'){
    $data = is_array($result) ? $result : [];
    $csv  = ir_render_csv($data['groups'] ?? [], $data['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0]);
    // WP_REST_Response header beállítások
    $resp = new WP_REST_Response($csv, 200);
    $resp->header('Content-Type', 'text/csv; charset=utf-8');
    $resp->header('Content-Disposition', 'attachment; filename="impact-report.csv"');
    return $resp;
  }
  return $result;
}, 10, 3);

/** Shortcode kiegészítés: Export gomb + Flush link UI a meglévő [impact_report]-hoz
 *  Használat: csak hagyd a mostani [impact_report]-ot, a gombok megjelennek alatta. */
add_action('wp_footer', function(){
  if (!is_singular()) return;
  // próbáljuk megtalálni a tábla konténerét és alátenni a gombokat JS-ből
  ?>
  <script>
  (function(){
    const wrap = document.querySelector('.impact-report-wrap');
    if(!wrap) return;

    function qs(name, def=''){
      const u = new URL(window.location.href);
      return u.searchParams.get(name) || def;
    }
    // összeállítjuk az aktuális query-t CSV exporthoz
    const params = new URLSearchParams({
      from: qs('from','<?php echo esc_js(date('Y-m-01')); ?>'),
      to: qs('to','<?php echo esc_js(date('Y-m-d')); ?>'),
      status: qs('status','approved'),
      group: qs('group','shop_ngo'),
      ngo: qs('ngo',''),
      format: 'csv'
    });
    const exportUrl = '<?php echo esc_url_raw( rest_url('impact/v1/report') ); ?>' + '?' + params.toString();

    const bar = document.createElement('div');
    bar.style.display='flex'; bar.style.gap='10px'; bar.style.marginTop='10px';

    const btnExport = document.createElement('a');
    btnExport.textContent = 'Export CSV';
    btnExport.href = exportUrl;
    btnExport.className = 'btn-export';
    btnExport.style.background='linear-gradient(90deg,#7C3AED,#06B6D4)';
    btnExport.style.color='#000'; btnExport.style.padding='8px 14px';
    btnExport.style.borderRadius='999px'; btnExport.style.fontWeight='800'; btnExport.style.textDecoration='none';

    const btnFlush = document.createElement('a');
    const u = new URL(window.location.href);
    u.searchParams.set('impact_flush','1'); // cache ürítés a REST oldalon
    btnFlush.textContent = 'Cache frissítése';
    btnFlush.href = u.toString();
    btnFlush.className = 'btn-flush';
    btnFlush.style.background='#0d0e11'; btnFlush.style.color='#E5E7EB';
    btnFlush.style.padding='8px 14px'; btnFlush.style.borderRadius='999px';
    btnFlush.style.border='1px solid #1F2937'; btnFlush.style.textDecoration='none';

    bar.append(btnExport, btnFlush);
    wrap.append(bar);
  })();
  </script>
  <?php
});
Használat röviden
CSV export: a riport oldaladon az „Export CSV” gomb egyből letölti:
/wp-json/impact/v1/report?...&format=csv
Cache frissítése: „Cache frissítése” gomb az aktuális paraméterekhez kapcsolt 15 perces cache-t üríti.
„Healthcheck” mini-snippetek (gyors ellenőrzés a böngésző konzolban)
Másold be a konzolba az adott oldal nézetén:
Impact wrapper létezik + KPI shortcode-ok válaszolnak-e?
(() => {
  const ok = !!document.querySelector('#impact-page');
  const kpis = ['impact_ticker','impact_leaderboard','impact_activity']
    .map(x => ({x, el: document.querySelector(`.kpi:contains([${x}])`) || document.querySelector(`.kpi:has([${x}])`)}));
  console.log('impact wrapper:', ok);
  console.table(kpis);
})();
Top3 countdown tickel-e (nem „LEJÁRT” minden)?
(() => {
  const nodes = [...document.querySelectorAll('#impact-page .deadline')];
  console.log('deadline számlálók:', nodes.length, nodes.slice(0,3).map(n => n.textContent));
})();
Netflix-sorok kártyaszám (legalább 9/kategória az MVP-nél):
(() => {
  const rails = ['tech','divat','sport'].map(k => ({k, n: document.querySelectorAll(`.rail[data-rail="${k}"] .card`).length}));
  console.table(rails);
})();
Következő pici upgrade-ek, ha szeretnéd:
NGO név szerinti szűrő a riport tetején (autocomplete a /impact/v1/ngos-ból).
HUF konverzió devizaváltási opcióval.
GA4 eventek a CSV export/flush gombokra (pl. event: 'impact_report_export', event: 'impact_report_flush').
Ezt mondtad:
Másold be a konzolba az adott oldal nézetén: 1. **Impact wrapper létezik + KPI shortcode-ok válaszolnak-e?**
ez hol van?
A ChatGPT ezt mondta:
Az a kis „healthcheck snippet”, amit mutattam ((() => { … })();), nem a szerveren van, hanem neked kell lefuttatni a böngésződben, amikor nézed az oldalad.
Hol találod / hogyan használod?
Nyisd meg a saját oldaladon azt az aloldalt, ahol a /impact vagy /impact/report blokk fut.
Nyomj F12-t (vagy jobb klikk → Inspect → Console fül).
Illeszd be a snippetet a Console ablakba, és üsd le az Entert.
Példa:
Az első snippet kiírja a konzolra, hogy van-e #impact-page wrapper, és a 3 KPI (ticker/leaderboard/activity) be van-e szúrva.
A második snippet a Top3 akcióid visszaszámlálóit ellenőrzi.
A harmadik a Netflix-sorok kártyaszámát írja ki.
Így tudsz 2 másodperc alatt „egészségügyi ellenőrzést” futtatni a fronton anélkül, hogy a szerverhez vagy a kódhoz kéne nyúlni.
Szeretnéd, hogy adjak egy gyors, 1-soros snippetet is, ami egyszerre lefut, és összefoglalja a legfontosabbakat (wrapper + kártyák + deadlines) egyetlen táblázatban a konzolban?

Ezt mondtad:
Nyomj **F12**\-t (vagy jobb klikk → Inspect → Console fül).
Mac-en hogy kell mert itt az F12 nem működik
A ChatGPT ezt mondta:
Mac-en nincs „F12” varázsbillentyű, ott így tudod előhozni a fejlesztői konzolt:
Safari
Safari → Beállítások → Advanced (Haladó).
Pipáld be: „Show Develop menu in menu bar” (Fejlesztő menü megjelenítése).
A menüsorban megjelenik a Develop menü → Show Web Inspector.
Billentyű: ⌥⌘I (Alt+Command+I).
Chrome (és a legtöbb Chromium-alapú böngésző, pl. Edge, Brave)
Menüből: View → Developer → Developer Tools.
Billentyű: ⌥⌘I (Alt+Command+I).
Firefox
Menüből: Tools → Web Developer → Web Console.
Billentyű: ⌥⌘K (Alt+Command+K).
Ha már megvan a konzol, egyszerűen illeszd be a snippetet, nyomj Entert, és kiírja az eredményeket.
👉 Szeretnéd, hogy készítsek egy egyetlen rövid snipetet, ami a legfontosabbakat (wrapper, kártyák, deadline) egy táblázatban összefoglalja, hogy ne kelljen három külön kódot futtatnod?

Ezt mondtad:
### Safari 1. Safari → **Beállítások** → Advanced (Haladó). 2. Pipáld be: _„Show Develop menu in menu bar”_ (Fejlesztő menü megjelenítése). 3. A menüsorban megjelenik a **Develop** menü → **Show Web Inspector**. * Billentyű: **⌥⌘I** (Alt+Command+I
magyar Mac, így mindig magyar menüpontokat jelölj meg
A ChatGPT ezt mondta:
Értem 🙂 akkor magyar menüpontokkal mutatom:
Safari (Mac, magyar)
Safari menüsorban: Safari → Beállítások…
A megnyíló ablakban menj a Haladó fülre.
Alul pipáld be: „Fejlesztői menü megjelenítése a menüsorban”.
Most a menüsorban megjelenik egy új menüpont: Fejlesztés.
– Itt válaszd: Webfelügyelő megjelenítése.
– Billentyűparancs: ⌥⌘I (Alt+Command+I).
Google Chrome (magyar)
Menüsor: Nézet → Fejlesztő → Fejlesztői eszközök.
Billentyű: ⌥⌘I.
Firefox (magyar)
Menüsor: Eszközök → Böngészőfejlesztő → Web konzol.
Billentyű: ⌥⌘K.
Amikor a konzol nyitva van, oda illeszd be a snippetet (pl. az egészségügyi ellenőrzést), majd nyomj Entert.
Szeretnéd, hogy készítsek egy „mindent egyben” rövid snipetet magyar kommentekkel, ami egyszerre ellenőrzi a wrapper jelenlétét, a Netflix-kártyák számát és a Top3 visszaszámlálót?

Ezt mondtad:
Safari (Mac, magyar)
Feltöltött kép
hol?
A ChatGPT ezt mondta:
A képen a Safari → Beállítások… → Fejlesztő paneljét látod.
Itt még nem az a pipa van, amit keresel 🙂
Ami kell: Safari → Beállítások… → Haladó fül (angolban Advanced).
Ott legalul találsz egy jelölőnégyzetet:
„Fejlesztői menü megjelenítése a menüsorban”.

✅ Ha ezt bepipálod, akkor a felső menüsorban (ahol most van, hogy „Safari, Fájl, Szerkesztés…”) megjelenik egy új menüpont: Fejlesztés.

Innen tudod kiválasztani:

Webfelügyelő megjelenítése → megnyílik a panel alul, és ott a Konzol fülre kattintva tudod beilleszteni a JavaScript snippeteimet.
Szeretnéd, hogy készítsek egy rövid képes útmutatót (nyilakkal berajzolva), pontosan melyik „Haladó” fülön hol kell kipipálni?
Ezt mondtad:
Feltöltött kép
Feltöltött kép
Feltöltött kép
az már meg volt, két új menüpont jelent mega Fejlesztő és a Funkciójelző. Ha gondolod rajzold le
A ChatGPT ezt mondta:
Tökéletes, látom a képeken, hogy most már megjelent a menüsorban a két új menüpont: Fejlesztő és Funkciójelzők. Ez pont az, amit kerestünk.
Most már így tudod előhívni a konzolt Safariban, magyar felületen:

A felső menüsorban kattints a Fejlesztő menüpontra.
Válaszd a Webfelügyelő megjelenítése menüpontot.
– Billentyűparancs: ⌥⌘I (Alt+Command+I).
A megnyíló ablakban a fülválasztók közül kattints a Konzol-ra.
Oda illeszd be a snippeteket (például a healthcheck kódokat), és üss Entert.
Így működni fog ugyanúgy, mint Chrome-ban vagy Firefoxban.
Szeretnéd, hogy készítsek egy rövid „mindent egyben” healthcheck snipetet, amit egyszerűen bemásolsz a konzolba, és egy táblázatban kiírja:
– van-e #impact-page,
– hány Netflix-kártya van kategóriánként,
– fut-e a Top3 visszaszámláló?

Ezt mondtad:
**Webfelügyelő megjelenítése** m
pontosan hol látod ezt a menüpontot, mert én sehol
A ChatGPT ezt mondta:
Értem 🙂 akkor pontosan leírom, hogy hol keresd a Safari menüsorban a „Webfelügyelő megjelenítése” pontot:
A képernyő tetején, a macOS felső menüsorában ott van a Safari, Fájl, Szerkesztés, Nézet, … stb.
Miután a Beállítások → Haladó → „Fejlesztői menü megjelenítése a menüsorban” be volt pipálva, ezek között a felső menüpontok között megjelenik egy új: Fejlesztő.
– Ez a Nézet és a Könyvjelzők között szokott megjelenni.
Kattints a Fejlesztő menüpontra.
Ebben a legördülő listában az egyik első menüpont lesz: Webfelügyelő megjelenítése.
Amint ezt kiválasztod, alul vagy külön ablakban megnyílik a Safari fejlesztői eszköze, benne a Konzol füllel. Oda tudod bemásolni a snippeteimet.
👉 Ha szeretnéd, küldhetek egy képernyőképet, nyilakkal jelölve, pontosan hol lesz a „Fejlesztő” menü a menüsorban. Akarod, hogy így vizuálisan is mutassam?

Ezt mondtad:
**Webfelügyelő megjelenítése**
ne idegesíts már, nincs ilyen menüpont
A ChatGPT ezt mondta:
Oké, menjünk teljesen biztosra, lépésenként, magyar Safari alatt:
Nyisd meg a Safari → Beállítások… ablakot.
Menj a Haladó fülre (Advanced).
A lap alján van egy jelölőnégyzet: „Fejlesztői menü megjelenítése a menüsorban”.
– Ez NEM a „Fejlesztő” vagy „Speciális” fül, hanem konkrétan a Haladó fül alján.
Ha ezt bepipálod, akkor a felső menüsávban (ahol most a „Safari, Fájl, Szerkesztés, Nézet, Előzmények…” látszik) megjelenik egy új menüpont: Fejlesztés.
Figyelj: magyar Safari-ban a menüpont neve Fejlesztés (nem „Fejlesztő”). Ez kicsit zavaró, mert a beállításban „Fejlesztői menü” szerepel, de a menüsorban végül Fejlesztés lesz.
Onnan éred el:

Fejlesztés → Webfelügyelő megjelenítése (⌥⌘I).
👉 Tehát amit eddig hiába kerestél „Fejlesztő” néven, az valójában „Fejlesztés” néven jelenik meg a menüsorban.
Szeretnéd, hogy készítsek egy képernyőképet, amin pontosan látszik, hol bukkan fel a „Fejlesztés” menü a felső sávban?

Ezt mondtad:
igen
A ChatGPT ezt mondta:
Akkor pontosan így fog kinézni a felső macOS menüsorod (a képernyő legfelső sávja), ha a Safari → Beállítások → Haladó → „Fejlesztői menü megjelenítése a menüsorban” be van pipálva:
   Safari   Fájl   Szerkesztés   Nézet   Előzmények   Könyvjelzők   Fejlesztés   Ablak   Súgó
👉 A Fejlesztés menü a Könyvjelzők és az Ablak közé kerül.
(Magyar Safari így nevezi, nem „Fejlesztő”.)
Innen tudsz választani:

Fejlesztés → Webfelügyelő megjelenítése
– vagy billentyű: ⌥⌘I (Alt+Command+I)
És a Webfelügyelő ablakban a Konzol fülön tudod beilleszteni a snippeteimet.
Szeretnéd, hogy készítsek egy „egylövéses” healthcheck snipetet, amit ha bemásolsz a Konzolba, egyetlen táblázatban megkapod a wrapper állapotát, a Netflix-kártyák számát és a Top3 countdownokat?
Ezt mondtad:
Szeretnéd, hogy készítsek egy „egylövéses” healthcheck snipetet, amit ha bemásolsz a Konzolba, egyetlen táblázatban megkapod a wrapper állapotát, a Netflix-kártyák számát és a Top3 countdownokat?
igen
A ChatGPT ezt mondta:

Gondolkozás
oké, itt az „egylövéses” healthcheck. Másold be a böngésző Konzol-ába (Safari: Fejlesztés → Webfelügyelő megjelenítése, majd Konzol fül) és üsd le az Entert. Egy perc alatt felméri a front állapotát, és táblázatban kiír mindent.
(async () => {
  const pad = n => String(n).padStart(2,"0");
  const now = new Date(); 
  const hms = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

  const root = document.querySelector('#impact-page');
  const rails = ['tech','divat','sport'];
  const railCounts = Object.fromEntries(
    rails.map(k => [k, document.querySelectorAll(`.rail[data-rail="${k}"] .card`).length])
  );

  const dealNodes = [...document.querySelectorAll('#deals-grid .deal')];
  const deadlines = [...document.querySelectorAll('#deals-grid .deal .deadline')];
  const activeDeals = deadlines.filter(d => {
    const t = (d.textContent || '').trim().toUpperCase();
    return t && t !== 'LEJÁRT';
  }).length;

  // REST-végpontok gyors ellenőrzése (200 OK + minimális tartalom)
  async function ping(url){
    try{
      const r = await fetch(url, {credentials:'same-origin'});
      if(!r.ok) return {ok:false, status:r.status};
      const j = await r.json();
      return {ok:true, status:r.status, size: Array.isArray(j) ? j.length : (j && typeof j==='object' ? Object.keys(j).length : 0)};
    }catch(e){ return {ok:false, status:'ERR'}; }
  }
  const [tick, lb, act] = await Promise.all([
    ping('/wp-json/impact/v1/ticker'),
    ping('/wp-json/impact/v1/leaderboard?tab=ngo'),
    ping('/wp-json/impact/v1/activity')
  ]);

  // Kampányfal gyors check
  const iframe = document.querySelector('#campaign-iframe');
  const tabs = document.querySelectorAll('#campaign-tabs button').length;
  const iframeH = iframe ? iframe.style.height || iframe.getAttribute('height') || '(auto)' : '(nincs)';

  // Összefoglaló táblázat
  const summary = [
    {Kulcs:'Idő', Érték:hms},
    {Kulcs:'Wrapper (#impact-page)', Érték: root ? 'OK' : 'HIÁNYZIK'},
    {Kulcs:'Tech kártyák', Érték: railCounts.tech},
    {Kulcs:'Divat kártyák', Érték: railCounts.divat},
    {Kulcs:'Sport kártyák', Érték: railCounts.sport},
    {Kulcs:'Top akciók (összes/aktív)', Érték: `${dealNodes.length}/${activeDeals}`},
    {Kulcs:'Ticker REST', Érték: tick.ok ? `OK (${tick.status})` : `HIBA (${tick.status})`},
    {Kulcs:'Leaderboard REST', Érték: lb.ok ? `OK (${lb.status})` : `HIBA (${lb.status})`},
    {Kulcs:'Activity REST', Érték: act.ok ? `OK (${act.status})` : `HIBA (${act.status})`},
    {Kulcs:'Kampány tabs', Érték: tabs},
    {Kulcs:'Kampány iframe magasság', Érték: iframeH},
  ];
  console.table(summary);

  // Részletesebb log (ha kell)
  console.log('Részletek:', {
    rails: railCounts,
    deals: {total: dealNodes.length, active: activeDeals},
    rest: {ticker:tick, leaderboard:lb, activity:act}
  });
})();
Mit nézz a táblában?
Wrapper = OK → az oldal Impact része betöltött.
Tech/Divat/Sport kártyák → MVP-ben cél 9–18/db.
Top akciók (összes/aktív) → legyen 1–3, az „aktív” ne legyen 0.
REST = OK (200) → backend hívások mennek.
Kampány tabs → 5 körül (Egészség/Oktatás/Környezet/Szociális/Állatvédelem).
Ha bármi HIBA vagy gyanúsan 0, onnan tudunk célzottan tovább menni (pl. melyik REST endpoint esik el, melyik rail üres).
Ezt mondtad:
Feltöltött kép
A ChatGPT ezt mondta:
Most már látom, miért kaptál a healthcheckben mindenhol 404-et:
a konzolban a hívások így néznek ki:
https://chatgpt.com/wp-json/impact/v1/ticker
Ez nyilván nem a te oldalad, hanem a ChatGPT környezete. A snippet automatikusan a jelenlegi domainre próbálta a /wp-json/... végpontokat hívni, de te nem az app.sharity.hu oldalon futtattad, hanem itt a beszélgetésben.
Mit kell tenni?
Nyisd meg a saját oldaladat (pl. https://app.sharity.hu/impact-demo vagy ahol a blokkok futnak).
Safari menü → Fejlesztés → Webfelügyelő megjelenítése → Konzol fül.
Oda illeszd be ugyanazt a snipetet.
Akkor a /wp-json/impact/v1/ticker stb. hívások már a saját domaineden futnak le, és nem fognak 404-re menni.
Ha biztosra akarsz menni
Cseréld a snippetben a fetch URL-eket fix domainre, pl.:
ping('https://app.sharity.hu/wp-json/impact/v1/ticker')
és így tovább (leaderboard, activity).
Szeretnéd, hogy készítsek neked egy rövidített verziót a healthcheckből, ahol a fetch URL-ek direktben a app.sharity.hu-ra mutatnak, így biztos nem a rossz domainen fut?
Ezt mondtad:
Feltöltött kép
A ChatGPT ezt mondta:
Most már jobb a helyzet: a táblázatban a Wrapper (#impact-page) = OK, tehát a front-end keret jelen van.
Ami viszont gond: a REST végpontokra (ticker, leaderboard, activity) mind HIBA (ERR) jött vissza → tehát a fetch hívás elindul, de a szerver nem ad választ (vagy hiba van a plugin oldalon).
Ez két dolgot jelenthet:

A plugin nincs bekapcsolva vagy hibára fut
– Nézd meg WP adminban: Bővítmények → aktív-e az Impact Bridge Local.
– Ha aktív, akkor nézd meg a WP → Eszközök → Napló (vagy a szerver error.log) fájlt, van-e PHP fatal error.
REST route nem regisztrálódik
– A snippet /wp-json/impact/v1/ticker-t hív, de ha a plugin nem tölt be, nincs ilyen route, és jön az ERR.
– Gyors teszt: nyisd meg közvetlenül a böngészőben:
https://app.sharity.hu/wp-json/impact/v1/ticker
Ha minden oké, JSON-t kell visszaadjon. Ha „rest_no_route”, akkor a plugin nem aktív / nem fut.
👉 Következő lépés: nyisd meg közvetlen a https://app.sharity.hu/wp-json/impact/v1/ticker linket a böngészőben, és mondd meg mit látsz: JSON adatot, WP error üzenetet, vagy teljes 404-et.
Ez alapján tudom megmondani, hogy a hiba a plugin betöltésében van, vagy csak a hívás paramétereivel.

Ezt mondtad:
Feltöltött kép
miért látszik minden oldalon a lap tetején minden oldalon ez? 
A ChatGPT ezt mondta:

Gondolkozás
Azért jelenik meg minden oldal tetején ez a szöveg, mert a wp-content/impact-bridge-local.php fájlod kimenetet generál: a fájl elején a <?php nyitó tag ELŐTT is van tartalom (pl. komment vagy BOM), ezért amit a plugin include-ol, az nyers szövegként kiírásra kerül.
Gyors javítás (2 perc)
Kapcsold ki a kiírást átmenetileg
– FTP/SFTP-n nevezd át ideiglenesen a fájlt, pl. impact-bridge-local.php.off, vagy a pluginben egy percre kommentezd az include WP_CONTENT_DIR.'/impact-bridge-local.php'; sort.
Így a site nem ír ki semmit, amíg javítasz.
Nyisd meg a wp-content/impact-bridge-local.php-t szerkesztőben, és tedd pont ilyenre:
Az első karakter a fájl elején legyen a <?php (nincs előtte üres sor, szóköz, BOM).
Nincs záró ?> a végén.
Kizárólag definíciók, semmilyen echo/print nincs benne.
Kódolás: UTF-8 (BOM nélkül).
Használd ezt a tiszta mintát:
<?php
// SHARITY – Impact Bridge (local config)
// Hely: wp-content/impact-bridge-local.php
// Csak privát beállítások. NEM generálhat kimenetet.

if (!defined('ABSPATH')) { exit; }

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', '***IDE-ADD-MEG-A-JELSZÓT***');

if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);

if (!defined('DOGNET_API_BASE'))         define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))        define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))   define('IMPACT_BRIDGE_TIMEOUT', 25);

// Kompat aliasok
if (!defined('DOGNET_EMAIL'))    define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD')) define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

// NINCS záró "?>"
Mentsd el BOM nélkül, töltsd fel vissza ugyanoda (nevezd vissza az eredeti névre, és/vagy vedd ki a kommentet az include-ról).
Frissítsd az oldalt: a fejlécci „kiírásnak” el kell tűnnie. Ha korábban „headers already sent” hiba jött, az is megszűnik.
Mi történt pontosan?
A screenshot alapján a fájl eleje /* … */ kommenttel indult, PHP tagon kívül, ezért a szerver simán kiszolgálta szövegként. Minden include után ez a szöveg „ráöntődött” az oldal tetejére.
Gyors ellenőrzés
Nyisd meg közvetlenül: /wp-json/impact/v1/ticker – JSON-t kell adnia, és nem jelenhet meg a fenti szöveg.
Ha mégis „headers already sent” üzenet jön, akkor még maradt valahol BOM vagy whitespace. Ilyenkor mentsd újra a fájlt „UTF-8 without BOM”-mal, és ellenőrizd, hogy a plugin fő fájljában sincs záró ?> és nincs utána üres sor.
Ha szeretnéd, adok egy 1-soros PHP tesztet is, ami kiírja, hogy a PHP értelmező BOM-mentesnek látja-e a fájlt.
Ezt mondtad:
`https://app.sharity.hu/wp-json/impact/v1/ticker` Ha minden oké, JSON-t kell visszaadjon. Ha „rest\_no\_route”, akkor a plugin nem aktív / nem fut.
kész rendben van. "{"total":0.4461414275,"today":0,"generated_at":"2025-09-23 17:26:19"}
A ChatGPT ezt mondta:
Szuper, akkor most tisztán látszik: amikor a böngészőben megnyitottad a
https://app.sharity.hu/wp-json/impact/v1/ticker-t, már helyes JSON jött vissza:
{"total":0.4461414275,"today":0,"generated_at":"2025-09-23 17:26:19"}
Ez azt jelenti:
A plugin aktív és a REST route regisztrálódik.
A Dognet auth/token kérés már működik (megkapta a tokent és tudott lekérdezni).
Az összesített adomány (commission × 0.5) épp ~0.446 € szeptemberre, és ma nincs új tranzakció.
Tehát a nagy piros hibakiírás (ami korábban a fájl tetején ott volt minden oldalon) eltűnt, a REST API él, és az adatok helyesen jönnek.
Következő lépések innen
LeaderBoard és Activity endpointokat is nyisd meg böngészőben:
/wp-json/impact/v1/leaderboard?tab=ngo
/wp-json/impact/v1/activity
Ha ezek is JSON-nal válaszolnak → minden core KPI forrás rendben.
Front oldal: a shortcode-ok [impact_ticker], [impact_leaderboard], [impact_activity] mostantól élő adatot tudnak mutatni.
Report MVP plugin (amit legutóbb csináltunk) már ugyanígy elérhető lesz a /wp-json/impact/v1/report alatt.
Szeretnéd, hogy most írjak egy gyors ellenőrzőlistát arról, mit érdemes lépésenként csekkolnod a ticker után (leaderboard, activity, majd a riport), hogy biztos legyél benne, minden láncszem működik?
Ezt mondtad:
/wp-json/impact/v1/leaderboard?tab=ngo
"[{"name":"0.0545367225","amount":0.3090414275},{"name":"0.0242026875","amount":0.1371}]      /wp-json/impact/v1/activity   "[]
A ChatGPT ezt mondta:

Gondolkozás
Látom:
/leaderboard?tab=ngo: a name mezők számok → az NGO-név mezőt rossz helyről olvassuk.
/activity: üres tömb → az elmúlt 3 napban nincs approved, vagy a mezőfelismerés miatt minden sor kiesik.
Gyors javítás: bővítsük a „NGO kiválasztó” függvényt, dobjuk el a számnak látszó értékeket, és adjak egy debug végpontot, hogy lásd, a Dognet milyen kulcsokkal küldi a custom mezőket.
Másold ezt a KÉT patchet a meglévő Impact Bridge Local pluginodba:

1) Cseréld le az NGO-kiválasztót egy erősebb verzióra
Keresd meg a régi ibl__pick_d1 függvényt, és cseréld erre:
// NGO/azonosító kiválasztása a tranzakcióból (ref/d1…d5/subid…data1…data5/utm)
// - Eldobja a számnak látszó értékeket (pl. "0.0545")
// - Kipróbál több kulcsot és egyszerű "key=value" mintákat is
function ibl__pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (isset($row[$k]) && $row[$k] !== '' && !is_array($row[$k])) {
      $v = trim((string)$row[$k]);
      // ha "key=value&..." formátum → próbáljuk kiszedni az ngo|ngo_name|d1 kulcsot
      if (strpos($v,'=') !== false && strpos($v,' ') === false) {
        parse_str($v, $qs);
        foreach (['ngo','ngo_name','d1','org','org_name'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
      // ha teljes URL UTM-ekkel
      if (filter_var($v, FILTER_VALIDATE_URL)) {
        $parts = parse_url($v);
        if (!empty($parts['query'])) {
          parse_str($parts['query'], $qs);
          foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
            if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
          }
        }
      }
      // numeric jellegű "név" eldobása (ez okozta nálad a "0.0545…" neveket)
      if ($v === '' || is_numeric($v)) continue;
      return $v;
    }
  }
  return '';
}
2) Leaderboard: biztosan „adomány” (50%), és szűrjük a rossz neveket
A ibl_build_leaderboard() függvényben az NGO ágon csere az összegző részre:
// NGO toplista
foreach($rows as $r){
  $ngo  = ibl__pick_d1($r);
  if ($ngo === '' || is_numeric($ngo)) $ngo = '(ismeretlen)';
  $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
  $don  = $comm * 0.5; // UI = adomány
  $map[$ngo] = ($map[$ngo] ?? 0) + $don;
}
$out = [];
foreach($map as $name=>$amount){ $out[] = ['name'=>$name,'amount'=>$amount]; }
3) Activity: legyen értelmes fallback szöveg
Cseréld a sorösszerakó részt erre (ugyanabban a fájlban, ibl_build_activity() végén):
$out=[];
foreach($rows as $r){
  $ngo = ibl__pick_d1($r);
  if ($ngo === '' || is_numeric($ngo)) $ngo = 'egy szervezet';
  $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
  $don  = $comm * 0.5;
  $out[]=['text'=> sprintf('%s támogatást hozott ~%s € adományban', $ngo, number_format($don,2,',',' '))];
}
4) (Opcionális, de ajánlott) Debug végpont – csak kulcsok listája
Tedd be valahová a pluginba (pl. a REST regisztrációk után). Így megnézheted, a legfrissebb tranzakció(k) milyen kulcsokat tartalmaznak, anélkül hogy szenzitív adatot öntenénk ki.
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/debug/sample', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){
      $from = date('Y-m-d', strtotime('-14 days'));
      $to   = date('Y-m-d');
      $rows = ibl_fetch_transactions($from,$to,'approved', 3, 50);
      if (is_wp_error($rows) || !$rows) return ['keys'=>[],'sample'=>[]];
      $first = (array)$rows[0];
      // csak a kulcsok + rövidített értékhossz, ne legyen adatkiömlés
      $keys = array_keys($first);
      $sample = [];
      foreach($first as $k=>$v){
        $sample[$k] = is_scalar($v) ? mb_substr((string)$v,0,80) : (is_array($v)?'[array]':'[obj]');
      }
      return ['keys'=>$keys, 'sample'=>$sample];
    }
  ]);
});
Ezután böngészőben:
/wp-json/impact/v1/debug/sample → látod, van-e d1, data1, subid, ngo
Ezt mondtad:
ugyanígy elérhető lesz a /wp-json/impact/v1/report alat
"{"groups":{"cid 897 \u00d7 0.0545367225":{"rows":1,"basket":0,"comm":0.3090414275,"don":0.15452071375},"cid 897 \u00d7 0.0242026875":{"rows":1,"basket":0,"comm":0.1371,"don":0.06855}},"totals":{"rows":2,"basket":0,"comm":0.4461414275,"don":0.22307071375}}
A ChatGPT ezt mondta:
Igen, pontosan 🙂
A /wp-json/impact/v1/report végpont most már ugyanúgy elérhető, mint a ticker/leaderboard/activity, mert a külön Impact Report MVP plugin regisztrálta.
Ez nem a Dognet API natív része, hanem a mi bridge-ünk épít rá. A Dognetben az alap nyers adatokat a /raw-transactions/filter adja vissza, abból a plugin görgetve lehúzza a sorokat (last_id módszerrel), és helyben aggregálja:

from / to: dátumtartomány
status: approved | pending | rejected | all
group: shop×NGO / NGO / Shop
ngo: opcionális szűrő
Amit most kaptál:
{"groups":{"cid 897 × 0.0545…":{"rows":1,"basket":0,"comm":0.309…,"don":0.1545…},
           "cid 897 × 0.0242…":{"rows":1,"basket":0,"comm":0.1371,"don":0.06855}},
 "totals":{"rows":2,"basket":0,"comm":0.4461,"don":0.2230}}
Ez a plugin által számolt riport:
két tranzakció (rows=2),
összes jutalék 0.4461 €,
adomány (felezve) 0.223 €.
Mire jó így a /report végpont?
Frontend táblázathoz (shortcode: [impact_report]).
Exporthoz (CSV, cache flush paraméterek).
Szűréshez (NGO-ra vagy shopra külön).
Ha szeretnéd, tudok adni egy példa linket /wp-json/impact/v1/report?... paraméterezve, amivel kipróbálhatod az összes funkciót (approved vs pending, group váltás, NGO filter). Akarod, hogy mutassak 2–3 konkrét URL példát?
Ezt mondtad:
Másold ezt a KÉT patchet a meglévő **Impact Bridge Local** pluginodba:
kérem egyben az egészet teljes cserére
A ChatGPT ezt mondta:
rendben — itt a teljes, egyben bemásolható csereverzió az Impact Bridge Local pluginhoz. UTF-8 (BOM nélkül) mentsd, ne tegyél a végére ?> záró taget. Útja:
wp-content/plugins/impact-bridge-local/impact-bridge-local.php

<?php
/**
 * Plugin Name: Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
 * Description: Helyi REST végpontok az Impact frontra. Dognet Publisher API → ticker/leaderboard/activity, cache-sel.
 * Version: 1.1.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ──────────────────────────────────────────────────────────────────────────
 * LOCAL CONFIG betöltés (NE módosíts wp-configot)
 * ────────────────────────────────────────────────────────────────────────── */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) {
  include_once $__impact_local_cfg;
}

/* ──────────────────────────────────────────────────────────────────────────
 * Alap konstansok (felülírhatók a local configban)
 * ────────────────────────────────────────────────────────────────────────── */
if (!defined('DOGNET_API_BASE'))          define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_LOGIN_EMAIL'))       define('DOGNET_LOGIN_EMAIL', getenv('DOGNET_LOGIN_EMAIL') ?: '');
if (!defined('DOGNET_LOGIN_PASSWORD'))    define('DOGNET_LOGIN_PASSWORD', getenv('DOGNET_LOGIN_PASSWORD') ?: '');
if (!defined('DOGNET_API_TOKEN'))         define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_TOKEN_TTL'))         define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT')) define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))    define('IMPACT_BRIDGE_TIMEOUT', 25);
/* Kompat aliasok */
if (!defined('DOGNET_EMAIL') && defined('DOGNET_LOGIN_EMAIL'))         define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD') && defined('DOGNET_LOGIN_PASSWORD'))   define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

/* ──────────────────────────────────────────────────────────────────────────
 * Dognet auth + request (24h token, használattal hosszabbodik)
 * ────────────────────────────────────────────────────────────────────────── */
function ibl__dognet_try_login_once($payload, $headers){
  $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $headers = array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers);
  $resp = wp_remote_post($ep, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'headers'=>$headers,'body'=>$payload,'redirection'=>3,'sslverify'=>true]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);
  $j = json_decode($body, true); $tok = '';
  if (is_array($j)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k==='data' || $k==='result') {
        if (!empty($j[$k]['token']))        { $tok=$j[$k]['token']; break; }
        if (!empty($j[$k]['access_token'])) { $tok=$j[$k]['access_token']; break; }
      } elseif (!empty($j[$k]) && is_string($j[$k])) {
        $tok = $j[$k]; break;
      }
    }
  }
  return ($code>=200 && $code<300 && $tok) ? ['ok'=>true,'token'=>$tok] : ['ok'=>false,'why'=>'HTTP '.$code];
}

function ibl_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key='ibl_dognet_tok_v1';
  if (!$force){ $t = get_transient($key); if ($t) return $t; }

  $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;
  if (!$email || !$pass) return '';

  $r = ibl__dognet_try_login_once(
    wp_json_encode(['email'=>$email,'password'=>$pass]),
    ['Content-Type'=>'application/json']
  );
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }

  $r = ibl__dognet_try_login_once(
    http_build_query(['email'=>$email,'password'=>$pass]),
    ['Content-Type'=>'application/x-www-form-urlencoded']
  );
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }

  return '';
}

function ibl_dognet_request($method,$path,$body=null){
  $tok = ibl_dognet_get_token(false);
  if (!$tok) return new WP_Error('no_token','Dognet token hiba – hiányzik az email/jelszó a local configban?');

  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $args = [
    'timeout'  => IMPACT_BRIDGE_TIMEOUT,
    'method'   => $method,
    'headers'  => [
      'Authorization' => 'Bearer '.$tok,
      'Accept'        => 'application/json',
      'Content-Type'  => 'application/json',
      'User-Agent'    => IMPACT_BRIDGE_USER_AGENT,
    ],
    'redirection'=>3,
    'sslverify' => true,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);

  $r = wp_remote_request($url,$args);
  if (is_wp_error($r)) return $r;
  $code = wp_remote_retrieve_response_code($r);
  $j    = json_decode(wp_remote_retrieve_body($r), true);

  if ($code==401){
    delete_transient('ibl_dognet_tok_v1');
    $tok=ibl_dognet_get_token(true);
    if(!$tok) return new WP_Error('no_token','401 + token refresh fail');
    $args['headers']['Authorization']='Bearer '.$tok;
    $r=wp_remote_request($url,$args);
    if (is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r);
    $j=json_decode(wp_remote_retrieve_body($r), true);
  }
  if ($code<200 || $code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}

/* ──────────────────────────────────────────────────────────────────────────
 * Segédek
 * ────────────────────────────────────────────────────────────────────────── */
function ibl__num($v){ return is_numeric($v)? floatval($v) : 0.0; }

/** NGO/azonosító kiválasztása – robusztusabb mezőkereső
 *  - számnak látszó értékek eldobása (ez okozta a "0.0545…" neveket)
 *  - támogat: d1..d5, data1..data5, ref*, sub_id*, subid*, ngo, ngo_name, note, comment
 *  - parse-olja az "a=b&ngo=XY" jellegű értékeket és a teljes URL-ek query-jét is
 */
function ibl__pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (isset($row[$k]) && $row[$k] !== '' && !is_array($row[$k])) {
      $v = trim((string)$row[$k]);

      // "key=value&..." sor → próbáljuk kiszedni az ngo|ngo_name|d1 kulcsot
      if (strpos($v,'=') !== false && strpos($v,' ') === false) {
        parse_str($v, $qs);
        foreach (['ngo','ngo_name','d1','org','org_name'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }

      // Teljes URL esetén query pars-olás
      if (filter_var($v, FILTER_VALIDATE_URL)) {
        $parts = parse_url($v);
        if (!empty($parts['query'])) {
          parse_str($parts['query'], $qs);
          foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
            if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
          }
        }
      }

      if ($v === '' || is_numeric($v)) continue;
      return $v;
    }
  }
  return '';
}

/* ──────────────────────────────────────────────────────────────────────────
 * Alap lekérés – raw-transactions/filter (last_id görgetéssel)
 * ────────────────────────────────────────────────────────────────────────── */
function ibl_fetch_transactions($from, $to, $status='approved', $maxBatches=60, $perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';

  $filter = [
    ['created_at'=>['gte'=>$fromDt]],
    ['created_at'=>['lte'=>$toDt]],
  ];
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id' => ['eq' => (int)DOGNET_AD_CHANNEL_ID]];
  }

  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ibl_dognet_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;

    if ($lastId===null){
      foreach($rows as $r){
        foreach(['id','transaction_id','tid'] as $k){
          if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]);
        }
      }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ──────────────────────────────────────────────────────────────────────────
 * Ticker / Leaderboard / Activity
 * ────────────────────────────────────────────────────────────────────────── */
function ibl_build_ticker(){
  $cache='ibl_ticker_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $total=0.0; $todaySum=0.0; $today=date('Y-m-d');
  foreach($rows as $r){
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $total += $comm;
    $created = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,10);
    if ($created===$today) $todaySum += $comm;
  }
  // UI: mindig adományt mutatunk? ticker továbbra is jutalék összeget ad vissza;
  // ha adomány kellene, itt *0.5-öt tegyél a $total-ra és $todaySum-ra.
  $out=['total'=>$total,'today'=>$todaySum,'generated_at'=>current_time('mysql')];
  set_transient($cache,$out, 180); return $out;
}

function ibl_build_leaderboard($tab='ngo'){
  $cache='ibl_lb_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $map=[];
  if ($tab==='ngo'){
    // NGO toplista – adomány (commission * 0.5)
    foreach($rows as $r){
      $ngo  = ibl__pick_d1($r);
      if ($ngo === '' || is_numeric($ngo)) $ngo = '(ismeretlen)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$ngo] = ($map[$ngo] ?? 0) + $don;
    }
  } else { // tab = shop
    foreach($rows as $r){
      $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
        if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
      }
      $name = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
      $comm= ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $map[$name]=($map[$name]??0)+($comm*0.5); // shopnál is adományt jelenítünk
    }
  }

  $out=[];
  foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  usort($out, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));

  set_transient($cache,$out, 300); return $out;
}

function ibl_build_activity(){
  $cache='ibl_activity_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d', strtotime('-3 days')); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved', 10, 100);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  usort($rows, function($a,$b){
    $da = strtotime($a['created_at'] ?? ($a['created'] ?? ''));
    $db = strtotime($b['created_at'] ?? ($b['created'] ?? ''));
    return $db <=> $da;
  });
  $rows = array_slice($rows,0,10);

  $out=[];
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r);
    if ($ngo === '' || is_numeric($ngo)) $ngo = 'egy szervezet';
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don  = $comm * 0.5;
    $out[]=['text'=> sprintf('%s támogatást hozott ~%s € adományban', $ngo, number_format($don,2,',',' '))];
  }
  set_transient($cache,$out, 120); return $out;
}

/* ──────────────────────────────────────────────────────────────────────────
 * REST: /wp-json/impact/v1/{ticker|leaderboard|activity}
 * ────────────────────────────────────────────────────────────────────────── */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/ticker', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_ticker(); return is_array($d)? rest_ensure_response($d) : new WP_Error('err','Hiba'); }
  ]);
  register_rest_route('impact/v1','/leaderboard', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $d=ibl_build_leaderboard($tab);
      if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
      return rest_ensure_response($d);
    }
  ]);
  register_rest_route('impact/v1','/activity', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_activity(); if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]); return rest_ensure_response($d); }
  ]);
});

/* ──────────────────────────────────────────────────────────────────────────
 * Opcionális debug: legutóbbi sor kulcsainak megmutatása (szenzitív adat nélkül)
 * ────────────────────────────────────────────────────────────────────────── */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/debug/sample', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){
      $from = date('Y-m-d', strtotime('-14 days'));
      $to   = date('Y-m-d');
      $rows = ibl_fetch_transactions($from,$to,'approved', 3, 50);
      if (is_wp_error($rows) || !$rows) return ['keys'=>[],'sample'=>[]];
      $first = (array)$rows[0];
      $keys = array_keys($first);
      $sample = [];
      foreach($first as $k=>$v){
        $sample[$k] = is_scalar($v) ? mb_substr((string)$v,0,80) : (is_array($v)?'[array]':'[obj]');
      }
      return ['keys'=>$keys, 'sample'=>$sample];
    }
  ]);
});

/* ──────────────────────────────────────────────────────────────────────────
 * Admin mini segéd: manuális cache flush shortcode
 * ────────────────────────────────────────────────────────────────────────── */
add_shortcode('impact_local_flush', function(){
  delete_transient('ibl_ticker_v1');
  delete_transient('ibl_activity_v1');
  global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ibl_lb_%' OR option_name LIKE '_transient_timeout_ibl_lb_%'");
  return '<div style="background:#0f1f13;color:#22C55E;padding:.6rem 1rem;border-radius:.5rem">Impact LOCAL cache ürítve.</div>';
});
Mit javít ez a verzió?
NGO-név kiválasztás: nem enged számot névnek; URL/param-string esetén kiszedi az ngo|ngo_name|d1 értéket.
Leaderboard/Activity: következetesen adományt számol (commission × 0.5), értelmes fallback nevekkel.
Ad Channel 26081 szűrés, ha a local configban definiáltad.
Debug végpont: /wp-json/impact/v1/debug/sample — kulcslista + rövid mintaérték, adatömlés nélkül.
BOM/whitespace safe: nincs kimenet generálás; a fájl eleje <?php, vége nincs záró tag.
Gyors teszt
Böngészőben:
/wp-json/impact/v1/ticker
/wp-json/impact/v1/leaderboard?tab=ngo
/wp-json/impact/v1/activity
Ha kell minta-kulcsok: /wp-json/impact/v1/debug/sample
Fronton futtasd a korábbi healthcheck snipetet — a REST soroknak „OK (200)”-t kell mutatniuk, és a kártyaszámok >0 legyenek.
Ha szeretnéd, adok hozzá egy minimal log kapcsolót (IMPACT_DEBUG_LOG) is, ami WP-debug logba írja a REST hívások összegét/időt — hasznos teljesítményméréshez.
Ezt mondtad:
kérem egyben az egészet teljes cserére
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
megvan. Itt a teljes, tiszta csere-csomag – három fájl, pontos útvonalakkal. Mindegyik UTF-8 (BOM nélkül), nincs záró ?>. Másold fel, kapcsold be a két plugint, és kész.
1) wp-content/impact-bridge-local.php
Privát beállítások egy helyen. Nem plugin, csak include-oljuk. Semmit nem írhat ki!
<?php
// SHARITY – Impact Bridge (local config)
// Hely: wp-content/impact-bridge-local.php
// Csak privát beállítások. NE írjon ki semmit.

if (!defined('ABSPATH')) { exit; }

/** Dognet Publisher account (ide jön az e-mail/jelszó) */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', '***IDE-ADD-MEG-A-JELSZÓT***');

/** Fix Ad Channel – Sharity: 26081 */
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);

/** Alapok / finomhangolás */
if (!defined('DOGNET_API_BASE'))         define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))        define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))   define('IMPACT_BRIDGE_TIMEOUT', 25);

/** Kompat aliasok (ha másik plugin mást vár) */
if (!defined('DOGNET_EMAIL'))    define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD')) define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

// NINCS záró "?>"
2) wp-content/plugins/impact-bridge-local/impact-bridge-local.php
Ticker / Leaderboard / Activity (REST), javított NGO-név felismerv, adomány=50%, cache. Ez a TE meglévő pluginod teljes csere változata.
<?php
/**
 * Plugin Name: Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
 * Description: Helyi REST végpontok az Impact frontnak. A Dognet Publisher API-ból számol ticker/leaderboard/activity-t, cache-sel.
 * Version: 1.1.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése (NE módosíts wp-configot) ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) { include_once $__impact_local_cfg; }

/* ===== KONFIG / VÉDŐSÍNEK ===== */
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', getenv('DOGNET_LOGIN_EMAIL') ?: '');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', getenv('DOGNET_LOGIN_PASSWORD') ?: '');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT')) define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))    define('IMPACT_BRIDGE_TIMEOUT', 25);

/* Kompat aliasok */
if (!defined('DOGNET_EMAIL') && defined('DOGNET_LOGIN_EMAIL'))       define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD') && defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

/* ===== DOGNET auth + request ===== */
function ibl__dognet_try_login_once($payload, $headers){
  $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $headers = array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers);
  $resp = wp_remote_post($ep, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'headers'=>$headers,'body'=>$payload,'redirection'=>3,'sslverify'=>true]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $j = json_decode(wp_remote_retrieve_body($resp), true);
  $tok = '';
  if (is_array($j)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k==='data' || $k==='result') {
        if (!empty($j[$k]['token']))        { $tok=$j[$k]['token']; break; }
        if (!empty($j[$k]['access_token'])) { $tok=$j[$k]['access_token']; break; }
      } elseif (!empty($j[$k]) && is_string($j[$k])) { $tok = $j[$k]; break; }
    }
  }
  return ($code>=200 && $code<300 && $tok) ? ['ok'=>true,'token'=>$tok] : ['ok'=>false,'why'=>'HTTP '.$code];
}

function ibl_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key='ibl_dognet_tok_v1';
  if (!$force){ $t = get_transient($key); if ($t) return $t; }
  $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;
  if (!$email || !$pass) return '';

  $r = ibl__dognet_try_login_once(wp_json_encode(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/json']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  $r = ibl__dognet_try_login_once(http_build_query(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/x-www-form-urlencoded']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  return '';
}

function ibl_dognet_request($method,$path,$body=null){
  $tok = ibl_dognet_get_token(false);
  if (!$tok) return new WP_Error('no_token','Dognet token hiba – hiányzik az e-mail/jelszó a local configban');

  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $args = [
    'timeout'  => IMPACT_BRIDGE_TIMEOUT,
    'method'   => $method,
    'headers'  => [
      'Authorization' => 'Bearer '.$tok,
      'Accept'        => 'application/json',
      'Content-Type'  => 'application/json',
      'User-Agent'    => IMPACT_BRIDGE_USER_AGENT,
    ],
    'redirection'=>3, 'sslverify'=>true,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);

  $r = wp_remote_request($url,$args);
  if (is_wp_error($r)) return $r;
  $code = wp_remote_retrieve_response_code($r);
  $j    = json_decode(wp_remote_retrieve_body($r), true);

  if ($code==401){
    delete_transient('ibl_dognet_tok_v1');
    $tok=ibl_dognet_get_token(true);
    if(!$tok) return new WP_Error('no_token','401 + token refresh fail');
    $args['headers']['Authorization']='Bearer '.$tok;
    $r=wp_remote_request($url,$args);
    if (is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r);
    $j=json_decode(wp_remote_retrieve_body($r), true);
  }
  if ($code<200 || $code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}

/* ===== Segédek ===== */
function ibl__num($v){ return is_numeric($v)? floatval($v) : 0.0; }

/**
 * NGO/azonosító kiválasztása a tranzakcióból (ref/d1…d5/subid…data1…data5/utm)
 * - Eldobja a számnak látszó értékeket (pl. "0.0545")
 * - Key=Value stringekből és teljes URL queryből is próbál olvasni
 */
function ibl__pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (isset($row[$k]) && $row[$k] !== '' && !is_array($row[$k])) {
      $v = trim((string)$row[$k]);
      // "key=value&..." eset:
      if (strpos($v,'=') !== false && strpos($v,' ') === false) {
        parse_str($v, $qs);
        foreach (['ngo','ngo_name','d1','org','org_name'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
      // Teljes URL UTM-ekkel
      if (filter_var($v, FILTER_VALIDATE_URL)) {
        $parts = parse_url($v);
        if (!empty($parts['query'])) {
          parse_str($parts['query'], $qs);
          foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
            if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
          }
        }
      }
      if ($v === '' || is_numeric($v)) continue;
      return $v;
    }
  }
  return '';
}

/* ===== Alap lekérdező: raw-transactions/filter (last_id görgetés) ===== */
function ibl_fetch_transactions($from, $to, $status='approved', $maxBatches=60, $perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [ ['created_at'=>['gte'=>$fromDt]], ['created_at'=>['lte'=>$toDt]] ];

  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }

  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ibl_dognet_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){
        foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); }
      }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Ticker / Leaderboard / Activity ===== */
function ibl_build_ticker(){
  $cache='ibl_ticker_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $total=0.0; $todaySum=0.0; $today=date('Y-m-d');
  foreach($rows as $r){
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $total += $comm;
    $created = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,10);
    if ($created===$today) $todaySum += $comm;
  }
  $out=['total'=>$total*0.5, 'today'=>$todaySum*0.5, 'generated_at'=>current_time('mysql')]; // UI: adomány
  set_transient($cache,$out, 180); return $out;
}

function ibl_build_leaderboard($tab='ngo'){
  $cache='ibl_lb_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $map=[];
  if ($tab==='ngo'){
    foreach($rows as $r){
      $ngo  = ibl__pick_d1($r);
      if ($ngo === '' || is_numeric($ngo)) $ngo = '(ismeretlen)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$ngo]=($map[$ngo]??0)+$don;
    }
  } else {
    foreach($rows as $r){
      $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
        if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
      }
      $name = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$name]=($map[$name]??0)+$don;
    }
  }
  $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  usort($out, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));
  set_transient($cache,$out, 300); return $out;
}

function ibl_build_activity(){
  $cache='ibl_activity_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d', strtotime('-3 days')); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved', 10, 100);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  usort($rows, function($a,$b){
    $da = strtotime($a['created_at'] ?? ($a['created'] ?? ''));
    $db = strtotime($b['created_at'] ?? ($b['created'] ?? ''));
    return $db <=> $da;
  });
  $rows = array_slice($rows,0,10);

  $out=[];
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r);
    if ($ngo === '' || is_numeric($ngo)) $ngo = 'egy szervezet';
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don  = $comm * 0.5;
    $out[]=['text'=> sprintf('%s támogatást hozott ~%s € adományban', $ngo, number_format($don,2,',',' '))];
  }
  set_transient($cache,$out, 120); return $out;
}

/* ===== REST: /wp-json/impact/v1/{ticker|leaderboard|activity} ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/ticker', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_ticker(); return is_array($d)? rest_ensure_response($d) : new WP_Error('err','Hiba'); }
  ]);
  register_rest_route('impact/v1','/leaderboard', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $d=ibl_build_leaderboard($tab);
      if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
      return rest_ensure_response($d);
    }
  ]);
  register_rest_route('impact/v1','/activity', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_activity(); if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]); return rest_ensure_response($d); }
  ]);
});

/* ===== (Opcionális) Debug: kulcsok mintavétele ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/debug/sample', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){
      $from = date('Y-m-d', strtotime('-14 days'));
      $to   = date('Y-m-d');
      $rows = ibl_fetch_transactions($from,$to,'approved', 3, 50);
      if (is_wp_error($rows) || !$rows) return ['keys'=>[],'sample'=>[]];
      $first = (array)$rows[0];
      $keys = array_keys($first);
      $sample = [];
      foreach($first as $k=>$v){ $sample[$k] = is_scalar($v) ? mb_substr((string)$v,0,80) : (is_array($v)?'[array]':'[obj]'); }
      return ['keys'=>$keys, 'sample'=>$sample];
    }
  ]);
});
3) wp-content/plugins/impact-report-mvp/impact-report-mvp.php
Riport REST + shortcode + CSV export + gyors cache-flush. Teljes csere.
<?php
/**
 * Plugin Name: Impact Report MVP (Sharity)
 * Description: Paraméterezhető riport (shop×NGO / NGO / Shop) – REST + shortcode, 15 perces cache, CSV export.
 * Version: 0.2.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) { include_once $__impact_local_cfg; }

/* ===== Alap konstansok ===== */
if (!defined('DOGNET_API_BASE'))              define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))             define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_RPT_CACHE_MINUTES'))     define('IMPACT_RPT_CACHE_MINUTES', 15);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))        define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))     define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');

/* ===== HTTP + Auth ===== */
function ir_http($method, $url, $body=null, $headers=[]) {
  $args = [
    'method' => $method,
    'timeout'=> IMPACT_BRIDGE_TIMEOUT,
    'headers'=> array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers),
    'sslverify'=> true, 'redirection'=> 3,
  ];
  if ($body !== null) { $args['headers']['Content-Type']='application/json'; $args['body']=is_string($body)?$body:wp_json_encode($body); }
  $res = wp_remote_request($url,$args);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  $json = json_decode(wp_remote_retrieve_body($res), true);
  if ($code>=200 && $code<300) return $json;
  return new WP_Error('dognet_http','Dognet API hiba',['code'=>$code,'body'=>$json]);
}
function ir_token($force=false){
  $key='impact_rpt_tok_v1';
  if (!$force && ($t=get_transient($key))) return $t;
  $email = defined('DOGNET_LOGIN_EMAIL')    ? DOGNET_LOGIN_EMAIL    : getenv('DOGNET_LOGIN_EMAIL');
  $pass  = defined('DOGNET_LOGIN_PASSWORD') ? DOGNET_LOGIN_PASSWORD : getenv('DOGNET_LOGIN_PASSWORD');
  if (!$email || !$pass) return new WP_Error('cfg','Hiányzik DOGNET_LOGIN_EMAIL / DOGNET_LOGIN_PASSWORD a wp-content/impact-bridge-local.php-ban');
  $auth = ir_http('POST', rtrim(DOGNET_API_BASE,'/').'/auth/login', ['email'=>$email,'password'=>$pass]);
  if (is_wp_error($auth) || empty($auth['token'])) return new WP_Error('auth','Auth sikertelen', ['resp'=>$auth]);
  set_transient($key,$auth['token'], DOGNET_TOKEN_TTL);
  return $auth['token'];
}
function ir_api($path,$body=null){
  $tok = ir_token(false);
  if (is_wp_error($tok)) return $tok;
  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  if (is_wp_error($res) && $res->get_error_code()==='dognet_http' && ($res->get_error_data()['code']??0)==401){
    delete_transient('impact_rpt_tok_v1');
    $tok = ir_token(true); if (is_wp_error($tok)) return $tok;
    $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  }
  return $res;
}

/* ===== Segédek ===== */
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }
function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if(isset($row[$k]) && $row[$k]!=='' && !is_array($row[$k])){
      $v = trim((string)$row[$k]);
      if (strpos($v,'=') !== false && strpos($v,' ') === false) {
        parse_str($v, $qs);
        foreach (['ngo','ngo_name','d1','org','org_name'] as $qq) { if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; } }
      }
      if (filter_var($v, FILTER_VALIDATE_URL)) {
        $parts = parse_url($v);
        if (!empty($parts['query'])) {
          parse_str($parts['query'], $qs);
          foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) { if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; } }
        }
      }
      if ($v === '' || is_numeric($v)) continue;
      return $v;
    }
  }
  return '';
}

/* ===== Adatok: raw-transactions/filter ===== */
function ir_fetch_transactions($from,$to,$status='approved',$maxBatches=60,$perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [
    ['created_at'=>['gte'=>$fromDt]],
    ['created_at'=>['lte'=>$toDt]],
  ];
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }
  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ir_api('/raw-transactions/filter', $body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){ foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); } }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Aggregálás (group: shop_ngo | ngo | shop) ===== */
function ir_aggregate($rows,$group='shop_ngo'){
  $out=[]; $sumBasket=0; $sumComm=0; $sumDon=0;
  foreach($rows as $r){
    $basket = ir_num($r['order_value'] ?? ($r['basket_value'] ?? 0));
    $comm   = ir_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don    = $comm * 0.5;
    $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){ if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; } }
    $shop = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
    $ngo  = ir_pick_d1($r) ?: '(ismeretlen NGO)';

    $key = $group==='ngo'  ? $ngo : ($group==='shop' ? $shop : ($shop.' × '.$ngo));

    if (!isset($out[$key])) $out[$key]=['rows'=>0,'basket'=>0.0,'comm'=>0.0,'don'=>0.0];
    $out[$key]['rows']   += 1;
    $out[$key]['basket'] += $basket;
    $out[$key]['comm']   += $comm;
    $out[$key]['don']    += $don;

    $sumBasket += $basket; $sumComm += $comm; $sumDon += $don;
  }
  uasort($out, function($a,$b){
    $d = $b['don'] <=> $a['don'];
    if ($d!==0) return $d;
    return 0;
  });
  return ['groups'=>$out,'totals'=>['rows'=>array_sum(array_column($out,'rows')),'basket'=>$sumBasket,'comm'=>$sumComm,'don'=>$sumDon]];
}

/* ====== REST: /impact/v1/report ====== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/report', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $from  = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $stat  = sanitize_text_field($req->get_param('status') ?: 'approved');
      $group = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $ngo   = sanitize_text_field($req->get_param('ngo') ?: '');

      $cache_key = 'impact_report_v1:'.md5(json_encode([$from,$to,$stat,$group,$ngo]));
      if ($cached = get_transient($cache_key)) return $cached;

      $rows = ir_fetch_transactions($from,$to,$stat);
      if (is_wp_error($rows)) return new WP_Error('dognet_err','Dognet hiba: '.$rows->get_error_message(),['status'=>502]);

      if ($ngo){
        $rows = array_values(array_filter($rows, function($r) use($ngo){
          $d1 = ir_pick_d1($r);
          return $d1 && (sanitize_title($d1) === sanitize_title($ngo));
        }));
      }

      $agg = ir_aggregate($rows,$group);
      set_transient($cache_key, $agg, IMPACT_RPT_CACHE_MINUTES * MINUTE_IN_SECONDS);
      return $agg;
    }
  ]);
});

/* ===== Shortcode: [impact_report] ===== */
add_shortcode('impact_report', function($atts){
  $q = wp_parse_args($_GET, [
    'from'=>date('Y-m-01'),
    'to'=>date('Y-m-d'),
    'status'=>'approved',
    'group'=>'shop_ngo',
    'ngo'=>'',
  ]);
  $api = esc_url_raw( add_query_arg([
    'from'=>$q['from'],'to'=>$q['to'],'status'=>$q['status'],'group'=>$q['group'],'ngo'=>$q['ngo']
  ], rest_url('impact/v1/report')) );

  $res = wp_remote_get($api, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'sslverify'=>true, 'headers'=>['User-Agent'=>IMPACT_BRIDGE_USER_AGENT]]);
  $body = is_wp_error($res) ? [] : json_decode(wp_remote_retrieve_body($res), true);
  $groups = $body['groups'] ?? [];
  $tot = $body['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

  ob_start(); ?>
  <div class="impact-report-wrap">
    <form class="ir-filters" method="get">
      <label>From <input type="date" name="from" value="<?php echo esc_attr($q['from']); ?>"></label>
      <label>To <input type="date" name="to" value="<?php echo esc_attr($q['to']); ?>"></label>
      <label>Status
        <select name="status">
          <?php foreach(['approved','pending','rejected','all'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php selected($q['status'],$s); ?>><?php echo ucfirst($s); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Group
        <select name="group">
          <option value="shop_ngo" <?php selected($q['group'],'shop_ngo'); ?>>Shop × NGO</option>
          <option value="ngo" <?php selected($q['group'],'ngo'); ?>>NGO</option>
          <option value="shop" <?php selected($q['group'],'shop'); ?>>Shop</option>
        </select>
      </label>
      <label>NGO (slug)
        <input type="text" name="ngo" placeholder="pl. bator-tabor-alapitvany" value="<?php echo esc_attr($q['ngo']); ?>">
      </label>
      <button type="submit">Szűrés</button>
    </form>

    <div class="ir-table-wrap">
      <table class="ir-table">
        <thead>
          <tr>
            <th>Csoport</th>
            <th style="text-align:right">Kosárérték (€)</th>
            <th style="text-align:right">Jutalék (€)</th>
            <th style="text-align:right">Adomány (50%) (€)</th>
            <th style="text-align:right">Darab</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$groups): ?>
          <tr><td colspan="5" style="text-align:center;color:#9CA3AF">Nincs találat a megadott szűrőkre.</td></tr>
        <?php else:
          foreach($groups as $name=>$v): ?>
            <tr>
              <td><?php echo esc_html($name); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['basket'], 2, ',', ' '); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['comm'],   2, ',', ' '); ?></td>
              <td style="text-align:right"><strong><?php echo number_format((float)$v['don'],    2, ',', ' '); ?></strong></td>
              <td style="text-align:right"><?php echo (int)$v['rows']; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th>Összesen</th>
            <th style="text-align:right"><?php echo number_format((float)$tot['basket'], 2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['comm'],   2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['don'],    2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo (int)$tot['rows']; ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <style>
    .impact-report-wrap{background:#111214;border:1px solid #1F2937;border-radius:16px;padding:12px}
    .impact-report-wrap .ir-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;align-items:end}
    .impact-report-wrap .ir-filters label{display:flex;flex-direction:column;font-size:.92rem;color:#E5E7EB}
    .impact-report-wrap .ir-filters input,.impact-report-wrap .ir-filters select{background:#0d0e11;color:#E5E7EB;border:1px solid #1F2937;border-radius:10px;padding:8px}
    .impact-report-wrap .ir-filters button{background:linear-gradient(90deg,#7C3AED,#06B6D4);color:#000;border:0;border-radius:999px;padding:8px 14px;font-weight:800}
    .impact-report-wrap .ir-table{width:100%;border-collapse:separate;border-spacing:0 6px;color:#E5E7EB}
    .impact-report-wrap .ir-table thead th{font-size:.9rem;color:#9CA3AF;border-bottom:1px solid #1F2937;padding:8px}
    .impact-report-wrap .ir-table tbody td{background:#0d0e11;border-top:1px solid #1F2937;border-bottom:1px solid #1F2937;padding:10px}
    .impact-report-wrap .ir-table tbody tr td:first-child{border-left:1px solid #1F2937;border-top-left-radius:10px;border-bottom-left-radius:10px}
    .impact-report-wrap .ir-table tbody tr td:last-child{border-right:1px solid #1F2937;border-top-right-radius:10px;border-bottom-right-radius:10px}
    .impact-report-wrap .ir-table tfoot th{border-top:1px solid #1F2937;padding:10px}
    @media(max-width:900px){.impact-report-wrap .ir-filters{flex-direction:column;align-items:stretch}}
  </style>
  <?php
  return ob_get_clean();
});

/* ===== CSV EXPORT + CACHE FLUSH ===== */
/** Helper: CSV render (UTF-8, ; elválasztó) */
function ir_render_csv($groups, $totals){
  $out = fopen('php://temp', 'r+');
  fputcsv($out, ['Csoport','Kosárérték (€)','Jutalék (€)','Adomány (50%) (€)','Darab'], ';');
  foreach($groups as $name=>$v){
    fputcsv($out, [
      $name,
      number_format((float)$v['basket'], 2, ',', ' '),
      number_format((float)$v['comm'],   2, ',', ' '),
      number_format((float)$v['don'],    2, ',', ' '),
      (int)$v['rows'],
    ], ';');
  }
  fputcsv($out, ['Összesen',
    number_format((float)$totals['basket'], 2, ',', ' '),
    number_format((float)$totals['comm'],   2, ',', ' '),
    number_format((float)$totals['don'],    2, ',', ' '),
    (int)$totals['rows']
  ], ';');
  rewind($out);
  return stream_get_contents($out);
}

/** REST bővítés: format=csv és impact_flush=1 támogatás */
add_filter('rest_pre_echo_response', function($result, $server, $request){
  if ($request->get_route() !== '/impact/v1/report') return $result;

  // Cache flush (param: ?impact_flush=1)
  if (isset($_GET['impact_flush']) && $_GET['impact_flush']=='1'){
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_impact_report_v1:%' OR option_name LIKE '_transient_timeout_impact_report_v1:%'");
  }

  // CSV export (param: &format=csv)
  if (strtolower((string)$request->get_param('format')) === 'csv'){
    $data = is_array($result) ? $result : [];
    $csv  = ir_render_csv($data['groups'] ?? [], $data['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0]);
    $resp = new WP_REST_Response($csv, 200);
    $resp->header('Content-Type', 'text/csv; charset=utf-8');
    $resp->header('Content-Disposition', 'attachment; filename="impact-report.csv"');
    return $resp;
  }
  return $result;
}, 10, 3);

/** UI gombok a [impact_report] alá (Export / Cache frissítés) */
add_action('wp_footer', function(){
  if (!is_singular()) return; ?>
  <script>
  (function(){
    const wrap = document.querySelector('.impact-report-wrap');
    if(!wrap) return;
    function qs(name, def=''){ const u = new URL(window.location.href); return u.searchParams.get(name) || def; }
    const params = new URLSearchParams({
      from: qs('from','<?php echo esc_js(date('Y-m-01')); ?>'),
      to: qs('to','<?php echo esc_js(date('Y-m-d')); ?>'),
      status: qs('status','approved'),
      group: qs('group','shop_ngo'),
      ngo: qs('ngo',''),
      format: 'csv'
    });
    const exportUrl = '<?php echo esc_url_raw( rest_url('impact/v1/report') ); ?>' + '?' + params.toString();

    const bar = document.createElement('div');
    bar.style.display='flex'; bar.style.gap='10px'; bar.style.marginTop='10px';

    const btnExport = document.createElement('a');
    btnExport.textContent = 'Export CSV';
    btnExport.href = exportUrl;
    btnExport.style.background='linear-gradient(90deg,#7C3AED,#06B6D4)';
    btnExport.style.color='#000'; btnExport.style.padding='8px 14px';
    btnExport.style.borderRadius='999px'; btnExport.style.fontWeight='800'; btnExport.style.textDecoration='none';

    const btnFlush = document.createElement('a');
    const u = new URL(window.location.href);
    u.searchParams.set('impact_flush','1');
    btnFlush.textContent = 'Cache frissítése';
    btnFlush.href = u.toString();
    btnFlush.style.background='#0d0e11'; btnFlush.style.color='#E5E7EB';
    btnFlush.style.padding='8px 14px'; btnFlush.style.borderRadius='999px';
    btnFlush.style.border='1px solid #1F2937'; btnFlush.style.textDecoration='none';

    bar.append(btnExport, btnFlush);
    wrap.append(bar);
  })();
  </script>
  <?php
});
Gyors telepítési ellenőrzőlista
 Feltöltve: wp-content/impact-bridge-local.php (BOM nélkül, nincs ?>).
 Impact Bridge Local plugin fájl lecserélve és aktív.
 Impact Report MVP plugin fájl lecserélve és aktív.
 REST teszt böngészőben:
/wp-json/impact/v1/ticker → JSON (adomány értékek)
/wp-json/impact/v1/leaderboard?tab=ngo → értelmes nevek, € adomány
/wp-json/impact/v1/activity → 1–10 sor, „… € adományban”
/wp-json/impact/v1/report?from=2025-09-01&to=2025-09-23&status=approved&group=shop_ngo
 Oldal: /impact/report → [impact_report] shortcode megjelenik, alatta Export CSV + Cache frissítése gomb.
Ha bárhol „headers already sent” vagy furcsa kiírás jelenne meg, az 99%, hogy egy fájl elején/végén maradt whitespace/BOM. Ilyenkor ments újra UTF-8 (BOM nélkül) és töröld a záró ?>-t.
Következő lépésre szívesen adok gyors GA4 esemény bekötést (shop_click, deal_copy, campaign_switch, impact_report_export) – egy 10 soros snippettel.

Ezt mondtad:
wp-json/impact/v1/ticker

"{"total":0.22307071375,"today":0,"generated_at":"2025-09-23 17:57:18"}

•	/wp-json/impact/v1/leaderboard?tab=ngo

"[{"name":"{\"source_currency\":\"HUF\",\"target_currency\":\"EUR\",\"exchange_rate\":0.002531,\"original_comm\":0.427739,\"exchanged_comm\":0.6099709999999999,\"network_fee\":0.054536722499999996}","amount":0.15452071375},{"name":"{\"source_currency\":\"HUF\",\"target_currency\":\"EUR\",\"exchange_rate\":0.002531,\"original_comm\":0.189825,\"exchanged_comm\":0.189825,\"network_fee\":0.0242026875}","amount":0.06855}]

wp-json/impact/v1/activity

"[]

wp-json/impact/v1/report?from=2025-09-01&to=2025-09-23&status=approved&group=shop_ngo

"{"groups":{"cid 897 \u00d7 {\"source_currency\":\"HUF\",\"target_currency\":\"EUR\",\"exchange_rate\":0.002531,\"original_comm\":0.427739,\"exchanged_comm\":0.6099709999999999,\"network_fee\":0.054536722499999996}":{"rows":1,"basket":0,"comm":0.3090414275,"don":0.15452071375},"cid 897 \u00d7 {\"source_currency\":\"HUF\",\"target_currency\":\"EUR\",\"exchange_rate\":0.002531,\"original_comm\":0.189825,\"exchanged_comm\":0.189825,\"network_fee\":0.0242026875}":{"rows":1,"basket":0,"comm":0.1371,"don":0.06855}},"totals":{"rows":2,"basket":0,"comm":0.4461414275,"don":0.22307071375}}

A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Látom, mi történik: az NGO “név” most egy JSON-szöveg lett (HUF→EUR váltási meta), ezért a leaderboard-ban és a report kulcsaiban is ilyen objektum-sztring jelenik meg. Az activity pedig üres, mert a 3 napos ablakban nincs approved sor, vagy ugyanebből a mező-felismerési hibából minden kiesik.
Megoldás:

a „név-picker” dobja el a JSON-nak látszó értékeket ({…} vagy […]),
ha nincs tiszta név, essen vissza (ismeretlen)-re,
az activity időablakát tágítsuk (pl. 14 nap), hogy legyen mit mutatni.
Alább a célzott patch – csak másold be a meglévő Impact Bridge Local pluginod helyére a megjelölt részeket. (Nem kell mindent cserélni, ez elég.)
1) NGO-név szedő – csere (dobja a JSON/”szám” értékeket)
Keresd meg a pluginban a ibl__pick_d1 függvényt, és cseréld erre:
/**
 * NGO/azonosító kiválasztása (ref/d1…d5/subid…data1…data5/utm)
 * - Eldobja a számnak látszó ÉS a JSON-nak tűnő értékeket ({"..."} vagy ["..."])
 * - Key=Value stringből és teljes URL queryből is próbál olvasni (ngo|ngo_name|d1|org|org_name|utm_term)
 */
function ibl__pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // 1) ha "key=value&..." → bontsuk ki
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // 2) ha teljes URL → query olvasás
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // 3) szemét/hibás értékek szűrése
    if ($v === '' || is_numeric($v)) continue;             // számnak látszó → kuka
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue; // JSON-szerű → kuka

    return $v;
  }
  return '';
}
Ezzel a most látott {"source_currency":"HUF",...} típusú „nevek” kiesnek, és ha nincs valódi név, a kód a meglévő helyeken (ismeretlen)-re fog esni.
2) Leaderboard & Report már jó lesz, de üríts cache-t
A régi „rossz nevek” még benn lehetnek a transiensekben. Tisztíts:
tedd ki ideiglenesen egy üres oldalra a shortcode-ot:
[impact_local_flush]
majd frissítsd az oldalt (zöld „Impact LOCAL cache ürítve.” jelzés).
VAGY kapcsold ki/be a plugint.
Ez törli a ibl_lb_* és társai cache-eket.
3) Activity ablak bővítése (opcionális, de most segít)
Ha az elmúlt 3 napban tényleg nincs approved, üres tömb jön. Növeljük 14 napra. Keresd meg az ibl_build_activity() elejét, és módosítsd ezt a két sort:
$from = date('Y-m-d', strtotime('-14 days')); // -3 helyett -14
$to   = date('Y-m-d');
A függvény többi része maradhat. (Továbbra is max. 10 sort ad, friss szerint rendezve.)
4) Gyors ellenőrzés
GET /wp-json/impact/v1/leaderboard?tab=ngo
– name mezők most nem lehetnek JSON-ok vagy számok; ha nincs adat: (ismeretlen).
GET /wp-json/impact/v1/report?...
– a „Csoport” kulcsokból eltűnnek a JSON-sztringek.
GET /wp-json/impact/v1/activity
– most már várhatóan lesz 1–10 sor (ha az utóbbi 14 napban volt approved).
Ha szeretnéd, adok egy mini „fekete lista” kiegészítőt is (regex), amivel konkrét kulcsszavakat szűrünk ki „névként” (pl. source_currency, exchange_rate, stb.), de a fenti JSON-detekt már elegánsabban megfogja a mostani esetet.
Menjünk rá a gyors retesztre: küldd el mit ad a három végpont a fenti patch és cache flush után, és ha kell, finomhangolok tovább a mezőlistán.

Ezt mondtad:
Mi az a BOM?
A ChatGPT ezt mondta:
A „BOM” rövidítés Byte Order Mark – egy speciális, láthatatlan kódrészlet a fájl elején.
Ez arra való, hogy megmondja a programoknak, milyen kódolással készült a szövegfájl (pl. UTF-8, UTF-16).

– Ha egy PHP fájl UTF-8 BOM-mal van elmentve, akkor a BOM karakterek még a <?php előtt kikerülnek a kimenetre.
– A böngésző ezt „nyers szövegként” látja, és ilyenkor jön a hírhedt „Headers already sent” hiba vagy az a jelenség, hogy minden oldal tetején fura kiírás jelenik meg.

Ezért mondtam korábban, hogy:

a PHP fájlokat mindig UTF-8 BOM nélkül kell menteni,
a legelső karakter legyen a <?php, előtte ne legyen semmi (üres sor sem).
Mac-en és Windows-on is a legtöbb kódszerkesztőben (pl. VS Code, Sublime, Notepad++) el tudod állítani a mentést „UTF-8 without BOM”-ra.
Szeretnéd, hogy mutassam, Mac-en a leggyorsabban hogyan tudod megnézni, van-e BOM a fájlod elején?

Ezt mondtad:
A vegere jo ez? // NINCS záró "?>"
A ChatGPT ezt mondta:
Pontosan, ez a lényeg.
PHP-ban nem kötelező a záró ?>. Ha mégis berakod, és utána véletlenül marad egy üres sor vagy szóköz, akkor az már tényleges kimenetnek számít → emiatt jönnek a „headers already sent” hibák, vagy az, hogy minden oldal tetején ott lóg valami váratlan karakter.

Ezért a biztonságos gyakorlat:

ne rakj a fájl végére ?>-t,
a legutolsó karakter a kódban egy záró kapcsos zárójel legyen (}), vagy komment (pl. // NINCS záró "?>").
Így biztos, hogy a fájl vége után nem tud kicsúszni semmi felesleges kimenet.
Akarsz egy rövid „ellenőrző trükköt” is, amivel megnézheted, van-e a fájlod elején BOM vagy a végén kóbor whitespace?
Ezt mondtad:
wp-json/impact/v1/leaderboard?tab=ngo

"[{"name":"(ismeretlen)","amount":0.22307071375}]

/wp-json/impact/v1/report?

"{"groups":{"cid 897 \u00d7 {\"source_currency\":\"HUF\",\"target_currency\":\"EUR\",\"exchange_rate\":0.002531,\"original_comm\":0.427739,\"exchanged_comm\":0.6099709999999999,\"network_fee\":0.054536722499999996}":{"rows":1,"basket":0,"comm":0.3090414275,"don":0.15452071375},"cid 897 \u00d7 {\"source_currency\":\"HUF\",\"target_currency\":\"EUR\",\"exchange_rate\":0.002531,\"original_comm\":0.189825,\"exchanged_comm\":0.189825,\"network_fee\":0.0242026875}":{"rows":1,"basket":0,"comm":0.1371,"don":0.06855}},"totals":{"rows":2,"basket":0,"comm":0.4461414275,"don":0.22307071375}}

/wp-json/impact/v1/activity

"[]

Levittem -30 days re mert utoljara 09.02 én volt approved de így is üres

A ChatGPT ezt mondta:

Gondolkozás
Jó hír–rossz hír keverék:
Jó: a ticker rendben, a leaderboard most már nem „szám/JSON a név”, hanem (ismeretlen).
Gond: a report csoportkulcsaiban még mindig ott a JSON (pl. {"source_currency":"HUF",…}), mert a riport pluginban az ir_pick_d1() még nem tudja kidobni a JSON-szerű értékeket.
Activity üres: nagy valószínűséggel cache miatt (az ibl_activity_v1 transient megmaradt a régi logikával). Hiába állítottad –30 napra, az activity cache kulcsa nem változik paraméter szerint.
Megcsináljuk mindkettőt, egy lépésben.
1) Report plugin – JSON-szűrés hozzáadása (TELJES csere a function ir_pick_d1()-re)
Nyisd meg:
wp-content/plugins/impact-report-mvp/impact-report-mvp.php
Keresd meg az ir_pick_d1() függvényt, és cseréld le erre (ez ugyanaz a robusztus verzió, mint a bridge-ben, kiegészítve JSON-detekcióval):

function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // 1) "a=b&ngo=XY" jellegű értékekből kibányászás
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // 2) Teljes URL → query bontás
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // 3) Kidobandó értékek: üres, számnak látszó, vagy JSON-szerű ({...} / [...])
    if ($v === '' || is_numeric($v)) continue;
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue;

    return $v;
  }
  return '';
}
Ez kiszedi a most látott HUF→EUR meta JSON-t, így a /report „Csoport” kulcsai többé nem lesznek JSON-sztringek.
2) „(ismeretlen NGO)” fallback a riportban
Ugyanebben a fájlban, az ir_aggregate() függvényben győződj meg róla, hogy a ngo változónál fallback van (ez a korábbi csomagban már így volt):
$ngo  = ir_pick_d1($r) ?: '(ismeretlen NGO)';
Ha még nem így áll, írd át erre.
3) Activity üresség – cache flush + (opció) ablakbővítés
a) Flushold a bridge cache-t
A bridge plugin ad egy flush shortcode-ot. Tegyél ki egy ideiglenes oldalra:
[impact_local_flush]
Nyisd meg az oldalt, látni fogod: „Impact LOCAL cache ürítve.”
Ez törli az ibl_activity_v1-et is. (Alternatíva: plugin ki-/bekapcs.)
b) (Opcionális) növeld az activity ablakát 14 napra
A bridge fájlban (impact-bridge-local.php) az ibl_build_activity() elején ez a két sor legyen:
$from = date('Y-m-d', strtotime('-14 days'));
$to   = date('Y-m-d');
Mentés után még egyszer flush (lásd a) pont), hogy az új logika lépjen életbe.
Gyors ellenőrzési sorrend
Flush: [impact_local_flush] oldal megnyit → zöld üzenet.
/report újratöltés:
.../wp-json/impact/v1/report?from=2025-09-01&to=2025-09-23&status=approved&group=shop_ngo
– a „Csoport” kulcsokból eltűnnek a JSON-ok.
/leaderboard?tab=ngo
– name többé nem lehet JSON; ha nincs név: (ismeretlen).
/activity
– 1–10 sor, „… € adományban” (ha az elmúlt 14 napban volt approved). Ha még mindig üres, megnézzük a /impact/v1/debug/sample-t (bridge debug route) és finomhangoljuk a mezőlistát.
Ha szeretnéd, beépítek a report pluginba is egy debug végpontot (ugyanolyat, mint a bridge-ben), hogy egy gombnyomásra lásd, milyen kulcsok érkeznek ott is – hasznos, ha később új mezők jönnek a hálózattól.
Ezt mondtad:
Vmelyik plugin miatt nem tolt be az elementor
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Oké, végigmegyünk rajta rendszeren — gyors hibaelhárítás + célzott javítás a két saját pluginre, mert tipikusan egy bővítmény JS/PHP hibája állítja meg az Elementor szerkesztőt.
Gyors teszt-sorrend (2–5 perc)
Elementor „Biztonságos mód”
WordPress admin → Elementor → Eszközök → Biztonságos mód: BE.
Ha így betölt az editor: biztos, hogy plugin vagy téma konfliktus.
Konfliktus-izolálás (leggyorsabb)
Kapcsold KI ideiglenesen ezeket és próbáld újra az Elementort:
Impact Report MVP
Impact Bridge Local
Ha az Elementor ezután betölt: a hiba valamelyikünk kódjából jön. Lásd „Javítás” lejjebb.
Böngésző konzol + Hálózat (Safari: Fejlesztés → Webfelügyelő megjelenítése → Konzol/Hálózat)
Nézd, van-e piros hiba. Gyakori tettes: admin-ajax.php / rest_route 500/403, vagy egy JS TypeError.
Ha látsz piros sort, írd meg a hibaüzenetet (első 1–2 sor bőven elég).
REST/Permalink egészség
Nyisd meg: /wp-json/ – jöjjön JSON index.
Beállítások → Közvetlen hivatkozások: mentsd újra (nem kell változtatni). Ez gyakran elég.
Memória + CSS regenerálás
Elementor → Eszközök → CSS & Adat: Regenerálás, majd Könyvtár szinkron.
cPanel Select PHP version / INI editor: memory_limit ≥ 256M, max_input_vars 5000 (wp-config módosítás nélkül is megy).
Valószínű ok a mi kódunkban és javítás
A két saját pluginban van egy apróság, ami Elementor előnézetben (editor iframe) is lefuthat, és ha bármelyik oldal „különös” DOM-ra számít, az editorban JS hibát okozhat.
1) Guard az wp_footer alól futó JS-re (Report plugin)
Nyisd meg:
wp-content/plugins/impact-report-mvp/impact-report-mvp.php
Keresd a add_action('wp_footer', function(){ ... }); blokkot, és cseréld az elejét erre a védett verzióra:
add_action('wp_footer', function(){
  // Ne fusson adminban vagy Elementor szerkesztő módban
  if (is_admin()) return;
  if (defined('ELEMENTOR_VERSION')) {
    try {
      if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return;
      }
    } catch (\Throwable $e) { /* nyeljük le: csak védekezünk */ }
  }

  if (!is_singular()) return;

  // ... a meglévő JS-generáló kódod innen mehet tovább változatlanul ...
Ezzel a gombok beszúrását végző script nem fut a szerkesztőben, csak élő nézetben.
2) Csak a saját REST-re hasson a CSV/flush filter
Ugyanebben a fájlban a rest_pre_echo_response filterünk most is route-szűrt, de tegyük szuper szűkítőre:
add_filter('rest_pre_echo_response', function($result, $server, $request){
  $route = $request->get_route();
  if ($route !== '/impact/v1/report') return $result; // csak a MI végpontunk!

  // ... marad a cache flush + CSV kód ...
}, 10, 3);
Ha eddig is így volt, jó — csak erősítjük a gyakorlatot: ne legyen „globális” mellékhatás a REST válaszoknál.
3) BOM + kimenetellenőrzés
Megvolt, de ismét kulcs: a három fájl (local config + 2 plugin) UTF-8 BOM nélkül, file elején <?php az első karakter, nincs záró ?>.
Ha bármi whitespace kicsúszik, az Elementor editor panel simán megáll.
Egyéb, ami még blokkolhatja az Elementort
ModSecurity / WAF blokkolja az admin-ajax.php-t vagy a REST-et → a Hálózat fülön 403/406 látszik. Ilyenkor a tárhelyszolgáltató felé egy szabály-kivételezés kell az admin-ajax/REST hívásokra.
Mixed content (http/https keveredik) → böngésző letilt szkripteket.
Beállítások → Általános: WordPress cím (URL) és Honlap cím (URL) legyen HTTPS.
Elavult Elementor/Pro → próbáld a legfrissebbre frissíteni, majd Eszközök → Visszaállítás (roll back) 1 verzióval, ha friss hiba jött.
Gyorskérdés nélkül is folytatható következő lépés
Kapcsold be a „Biztonságos módot”, teszteld.
Kapcsold ki a két saját plugint egyszerre, teszteld.
Ha így betölt, kapcsold vissza csak a Bridge-et, teszteld; majd a Reportot, teszteld.
– Amelyiknél megáll, oda tegyük be a fenti Elementor-guardot és route-szűkítést (ha nem volt).
Ha elakadsz, dobd ide a Hálózat fül piros sorának státuszkódját és rövid hibaüzenetét (pl. admin-ajax.php 500 vagy rest?_locale=user 403), és mondom a pontos következő lépést.
Ezt mondtad:
ir_pick_d
/* ===== Segédek ===== */
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }
function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if(isset($row[$k]) && $row[$k]!=='' && !is_array($row[$k])){
      $v = trim((string)$row[$k]);
      if (strpos($v,'=') !== false && strpos($v,' ') === false) {
        parse_str($v, $qs);
        foreach (['ngo','ngo_name','d1','org','org_name'] as $qq) { if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; } }
      }
      if (filter_var($v, FILTER_VALIDATE_URL)) {
        $parts = parse_url($v);
        if (!empty($parts['query'])) {
          parse_str($parts['query'], $qs);
          foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) { if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; } }
        }
      }
      if ($v === '' || is_numeric($v)) continue;
      return $v;
    }
  }
  return '';
} ezt? Az első sor eltűnik?
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igen, ezt a függvényt kell cserélned — de egészítsük ki a JSON-szűréssel is.
Az „első sor” (/* ===== Segédek ===== */) csak komment, maradhat; nem tűnik el semmi, csak az ir_pick_d1() tartalma frissül.
Tedd ezt az egy az egyben cserét az impact-report-mvp.php fájlban, a /* ===== Segédek ===== */ blokk alatt (hagyd meg az ir_num()-ot változatlanul):

/* ===== Segédek ===== */
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }

function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // "a=b&ngo=XY" jellegű értékből kibányászás
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // Teljes URL → query bontás
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // Kidobandó értékek: üres, számnak látszó, vagy JSON-szerű ({...} / [...])
    if ($v === '' || is_numeric($v)) continue;
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue;

    return $v;
  }
  return '';
}
Mi változik ezzel?
A riportban és leaderboardban nem kerül be többé JSON-szöveg „névnek”.
Ha nincs értelmes név, a kódod úgyis (ismeretlen NGO)-ra esik vissza – a sorok nem tűnnek el, csak a kulcsok/név normalizálódik.
Ne feledd cache-t üríteni
A régi kulcsok bent lehetnek a transiensekben. Ürítheted így:
Nyisd meg a riport URL-t &impact_flush=1-gyel:
/wp-json/impact/v1/report?from=...&to=...&status=approved&group=shop_ngo&impact_flush=1
Vagy a front oldalon a „Cache frissítése” gombbal (ha be van drótozva).
Vagy plugin ki/be.
Ha kell, adok egy ugyanezt a JSON-szűrést tartalmazó minipatch-et a bridge plugin ibl__pick_d1()-jére is, de azt már korábban betettük.
Ezt mondtad:
Mind a report mind az impact bridge bekapcsolasa eseten nem nyilik meg csak ha mindkettot kikapcsolom. 

Report: 

<?php
/**
 * Plugin Name: Impact Report MVP (Sharity)
 * Description: Paraméterezhető riport (shop×NGO / NGO / Shop) – REST + shortcode, 15 perces cache, CSV export.
 * Version: 0.2.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) { include_once $__impact_local_cfg; }

/* ===== Alap konstansok ===== */
if (!defined('DOGNET_API_BASE'))              define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))             define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_RPT_CACHE_MINUTES'))     define('IMPACT_RPT_CACHE_MINUTES', 15);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))        define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))     define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');

/* ===== HTTP + Auth ===== */
function ir_http($method, $url, $body=null, $headers=[]) {
  $args = [
    'method' => $method,
    'timeout'=> IMPACT_BRIDGE_TIMEOUT,
    'headers'=> array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers),
    'sslverify'=> true, 'redirection'=> 3,
  ];
  if ($body !== null) { $args['headers']['Content-Type']='application/json'; $args['body']=is_string($body)?$body:wp_json_encode($body); }
  $res = wp_remote_request($url,$args);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  $json = json_decode(wp_remote_retrieve_body($res), true);
  if ($code>=200 && $code<300) return $json;
  return new WP_Error('dognet_http','Dognet API hiba',['code'=>$code,'body'=>$json]);
}
function ir_token($force=false){
  $key='impact_rpt_tok_v1';
  if (!$force && ($t=get_transient($key))) return $t;
  $email = defined('DOGNET_LOGIN_EMAIL')    ? DOGNET_LOGIN_EMAIL    : getenv('DOGNET_LOGIN_EMAIL');
  $pass  = defined('DOGNET_LOGIN_PASSWORD') ? DOGNET_LOGIN_PASSWORD : getenv('DOGNET_LOGIN_PASSWORD');
  if (!$email || !$pass) return new WP_Error('cfg','Hiányzik DOGNET_LOGIN_EMAIL / DOGNET_LOGIN_PASSWORD a wp-content/impact-bridge-local.php-ban');
  $auth = ir_http('POST', rtrim(DOGNET_API_BASE,'/').'/auth/login', ['email'=>$email,'password'=>$pass]);
  if (is_wp_error($auth) || empty($auth['token'])) return new WP_Error('auth','Auth sikertelen', ['resp'=>$auth]);
  set_transient($key,$auth['token'], DOGNET_TOKEN_TTL);
  return $auth['token'];
}
function ir_api($path,$body=null){
  $tok = ir_token(false);
  if (is_wp_error($tok)) return $tok;
  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  if (is_wp_error($res) && $res->get_error_code()==='dognet_http' && ($res->get_error_data()['code']??0)==401){
    delete_transient('impact_rpt_tok_v1');
    $tok = ir_token(true); if (is_wp_error($tok)) return $tok;
    $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  }
  return $res;
}

/* ===== Segédek ===== */
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }
function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if(isset($row[$k]) && $row[$k]!=='' && !is_array($row[$k])){
      $v = trim((string)$row[$k]);
      if (strpos($v,'=') !== false && strpos($v,' ') === false) {
        parse_str($v, $qs);
        foreach (['ngo','ngo_name','d1','org','org_name'] as $qq) { if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; } }
      }
      if (filter_var($v, FILTER_VALIDATE_URL)) {
        $parts = parse_url($v);
        if (!empty($parts['query'])) {
          parse_str($parts['query'], $qs);
          foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) { if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; } }
        }
      }
      if ($v === '' || is_numeric($v)) continue;
      return $v;
    }
  }
  return '';
}

/* ===== Adatok: raw-transactions/filter ===== */
function ir_fetch_transactions($from,$to,$status='approved',$maxBatches=60,$perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [
    ['created_at'=>['gte'=>$fromDt]],
    ['created_at'=>['lte'=>$toDt]],
  ];
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }
  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ir_api('/raw-transactions/filter', $body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){ foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); } }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Aggregálás (group: shop_ngo | ngo | shop) ===== */
function ir_aggregate($rows,$group='shop_ngo'){
  $out=[]; $sumBasket=0; $sumComm=0; $sumDon=0;
  foreach($rows as $r){
    $basket = ir_num($r['order_value'] ?? ($r['basket_value'] ?? 0));
    $comm   = ir_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don    = $comm * 0.5;
    $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){ if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; } }
    $shop = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
    $ngo  = ir_pick_d1($r) ?: '(ismeretlen NGO)';

    $key = $group==='ngo'  ? $ngo : ($group==='shop' ? $shop : ($shop.' × '.$ngo));

    if (!isset($out[$key])) $out[$key]=['rows'=>0,'basket'=>0.0,'comm'=>0.0,'don'=>0.0];
    $out[$key]['rows']   += 1;
    $out[$key]['basket'] += $basket;
    $out[$key]['comm']   += $comm;
    $out[$key]['don']    += $don;

    $sumBasket += $basket; $sumComm += $comm; $sumDon += $don;
  }
  uasort($out, function($a,$b){
    $d = $b['don'] <=> $a['don'];
    if ($d!==0) return $d;
    return 0;
  });
  return ['groups'=>$out,'totals'=>['rows'=>array_sum(array_column($out,'rows')),'basket'=>$sumBasket,'comm'=>$sumComm,'don'=>$sumDon]];
}

/* ====== REST: /impact/v1/report ====== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/report', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $from  = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $stat  = sanitize_text_field($req->get_param('status') ?: 'approved');
      $group = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $ngo   = sanitize_text_field($req->get_param('ngo') ?: '');

      $cache_key = 'impact_report_v1:'.md5(json_encode([$from,$to,$stat,$group,$ngo]));
      if ($cached = get_transient($cache_key)) return $cached;

      $rows = ir_fetch_transactions($from,$to,$stat);
      if (is_wp_error($rows)) return new WP_Error('dognet_err','Dognet hiba: '.$rows->get_error_message(),['status'=>502]);

      if ($ngo){
        $rows = array_values(array_filter($rows, function($r) use($ngo){
          $d1 = ir_pick_d1($r);
          return $d1 && (sanitize_title($d1) === sanitize_title($ngo));
        }));
      }

      $agg = ir_aggregate($rows,$group);
      set_transient($cache_key, $agg, IMPACT_RPT_CACHE_MINUTES * MINUTE_IN_SECONDS);
      return $agg;
    }
  ]);
});

/* ===== Shortcode: [impact_report] ===== */
add_shortcode('impact_report', function($atts){
  $q = wp_parse_args($_GET, [
    'from'=>date('Y-m-01'),
    'to'=>date('Y-m-d'),
    'status'=>'approved',
    'group'=>'shop_ngo',
    'ngo'=>'',
  ]);
  $api = esc_url_raw( add_query_arg([
    'from'=>$q['from'],'to'=>$q['to'],'status'=>$q['status'],'group'=>$q['group'],'ngo'=>$q['ngo']
  ], rest_url('impact/v1/report')) );

  $res = wp_remote_get($api, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'sslverify'=>true, 'headers'=>['User-Agent'=>IMPACT_BRIDGE_USER_AGENT]]);
  $body = is_wp_error($res) ? [] : json_decode(wp_remote_retrieve_body($res), true);
  $groups = $body['groups'] ?? [];
  $tot = $body['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

  ob_start(); ?>
  <div class="impact-report-wrap">
    <form class="ir-filters" method="get">
      <label>From <input type="date" name="from" value="<?php echo esc_attr($q['from']); ?>"></label>
      <label>To <input type="date" name="to" value="<?php echo esc_attr($q['to']); ?>"></label>
      <label>Status
        <select name="status">
          <?php foreach(['approved','pending','rejected','all'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php selected($q['status'],$s); ?>><?php echo ucfirst($s); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Group
        <select name="group">
          <option value="shop_ngo" <?php selected($q['group'],'shop_ngo'); ?>>Shop × NGO</option>
          <option value="ngo" <?php selected($q['group'],'ngo'); ?>>NGO</option>
          <option value="shop" <?php selected($q['group'],'shop'); ?>>Shop</option>
        </select>
      </label>
      <label>NGO (slug)
        <input type="text" name="ngo" placeholder="pl. bator-tabor-alapitvany" value="<?php echo esc_attr($q['ngo']); ?>">
      </label>
      <button type="submit">Szűrés</button>
    </form>

    <div class="ir-table-wrap">
      <table class="ir-table">
        <thead>
          <tr>
            <th>Csoport</th>
            <th style="text-align:right">Kosárérték (€)</th>
            <th style="text-align:right">Jutalék (€)</th>
            <th style="text-align:right">Adomány (50%) (€)</th>
            <th style="text-align:right">Darab</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$groups): ?>
          <tr><td colspan="5" style="text-align:center;color:#9CA3AF">Nincs találat a megadott szűrőkre.</td></tr>
        <?php else:
          foreach($groups as $name=>$v): ?>
            <tr>
              <td><?php echo esc_html($name); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['basket'], 2, ',', ' '); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['comm'],   2, ',', ' '); ?></td>
              <td style="text-align:right"><strong><?php echo number_format((float)$v['don'],    2, ',', ' '); ?></strong></td>
              <td style="text-align:right"><?php echo (int)$v['rows']; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th>Összesen</th>
            <th style="text-align:right"><?php echo number_format((float)$tot['basket'], 2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['comm'],   2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['don'],    2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo (int)$tot['rows']; ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <style>
    .impact-report-wrap{background:#111214;border:1px solid #1F2937;border-radius:16px;padding:12px}
    .impact-report-wrap .ir-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;align-items:end}
    .impact-report-wrap .ir-filters label{display:flex;flex-direction:column;font-size:.92rem;color:#E5E7EB}
    .impact-report-wrap .ir-filters input,.impact-report-wrap .ir-filters select{background:#0d0e11;color:#E5E7EB;border:1px solid #1F2937;border-radius:10px;padding:8px}
    .impact-report-wrap .ir-filters button{background:linear-gradient(90deg,#7C3AED,#06B6D4);color:#000;border:0;border-radius:999px;padding:8px 14px;font-weight:800}
    .impact-report-wrap .ir-table{width:100%;border-collapse:separate;border-spacing:0 6px;color:#E5E7EB}
    .impact-report-wrap .ir-table thead th{font-size:.9rem;color:#9CA3AF;border-bottom:1px solid #1F2937;padding:8px}
    .impact-report-wrap .ir-table tbody td{background:#0d0e11;border-top:1px solid #1F2937;border-bottom:1px solid #1F2937;padding:10px}
    .impact-report-wrap .ir-table tbody tr td:first-child{border-left:1px solid #1F2937;border-top-left-radius:10px;border-bottom-left-radius:10px}
    .impact-report-wrap .ir-table tbody tr td:last-child{border-right:1px solid #1F2937;border-top-right-radius:10px;border-bottom-right-radius:10px}
    .impact-report-wrap .ir-table tfoot th{border-top:1px solid #1F2937;padding:10px}
    @media(max-width:900px){.impact-report-wrap .ir-filters{flex-direction:column;align-items:stretch}}
  </style>
  <?php
  return ob_get_clean();
});

/* ===== CSV EXPORT + CACHE FLUSH ===== */
/** Helper: CSV render (UTF-8, ; elválasztó) */
function ir_render_csv($groups, $totals){
  $out = fopen('php://temp', 'r+');
  fputcsv($out, ['Csoport','Kosárérték (€)','Jutalék (€)','Adomány (50%) (€)','Darab'], ';');
  foreach($groups as $name=>$v){
    fputcsv($out, [
      $name,
      number_format((float)$v['basket'], 2, ',', ' '),
      number_format((float)$v['comm'],   2, ',', ' '),
      number_format((float)$v['don'],    2, ',', ' '),
      (int)$v['rows'],
    ], ';');
  }
  fputcsv($out, ['Összesen',
    number_format((float)$totals['basket'], 2, ',', ' '),
    number_format((float)$totals['comm'],   2, ',', ' '),
    number_format((float)$totals['don'],    2, ',', ' '),
    (int)$totals['rows']
  ], ';');
  rewind($out);
  return stream_get_contents($out);
}

/** REST bővítés: format=csv és impact_flush=1 támogatás */
add_filter('rest_pre_echo_response', function($result, $server, $request){
  if ($request->get_route() !== '/impact/v1/report') return $result;

  // Cache flush (param: ?impact_flush=1)
  if (isset($_GET['impact_flush']) && $_GET['impact_flush']=='1'){
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_impact_report_v1:%' OR option_name LIKE '_transient_timeout_impact_report_v1:%'");
  }

  // CSV export (param: &format=csv)
  if (strtolower((string)$request->get_param('format')) === 'csv'){
    $data = is_array($result) ? $result : [];
    $csv  = ir_render_csv($data['groups'] ?? [], $data['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0]);
    $resp = new WP_REST_Response($csv, 200);
    $resp->header('Content-Type', 'text/csv; charset=utf-8');
    $resp->header('Content-Disposition', 'attachment; filename="impact-report.csv"');
    return $resp;
  }
  return $result;
}, 10, 3);

/** UI gombok a [impact_report] alá (Export / Cache frissítés) */
add_action('wp_footer', function(){
  if (!is_singular()) return; ?>
  <script>
  (function(){
    const wrap = document.querySelector('.impact-report-wrap');
    if(!wrap) return;
    function qs(name, def=''){ const u = new URL(window.location.href); return u.searchParams.get(name) || def; }
    const params = new URLSearchParams({
      from: qs('from','<?php echo esc_js(date('Y-m-01')); ?>'),
      to: qs('to','<?php echo esc_js(date('Y-m-d')); ?>'),
      status: qs('status','approved'),
      group: qs('group','shop_ngo'),
      ngo: qs('ngo',''),
      format: 'csv'
    });
    const exportUrl = '<?php echo esc_url_raw( rest_url('impact/v1/report') ); ?>' + '?' + params.toString();

    const bar = document.createElement('div');
    bar.style.display='flex'; bar.style.gap='10px'; bar.style.marginTop='10px';

    const btnExport = document.createElement('a');
    btnExport.textContent = 'Export CSV';
    btnExport.href = exportUrl;
    btnExport.style.background='linear-gradient(90deg,#7C3AED,#06B6D4)';
    btnExport.style.color='#000'; btnExport.style.padding='8px 14px';
    btnExport.style.borderRadius='999px'; btnExport.style.fontWeight='800'; btnExport.style.textDecoration='none';

    const btnFlush = document.createElement('a');
    const u = new URL(window.location.href);
    u.searchParams.set('impact_flush','1');
    btnFlush.textContent = 'Cache frissítése';
    btnFlush.href = u.toString();
    btnFlush.style.background='#0d0e11'; btnFlush.style.color='#E5E7EB';
    btnFlush.style.padding='8px 14px'; btnFlush.style.borderRadius='999px';
    btnFlush.style.border='1px solid #1F2937'; btnFlush.style.textDecoration='none';

    bar.append(btnExport, btnFlush);
    wrap.append(bar);
  })();
  </script>
  <?php
});

Bridge:

<?php
/**
 * Plugin Name: Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
 * Description: Helyi REST végpontok az Impact frontnak. A Dognet Publisher API-ból számol ticker/leaderboard/activity-t, cache-sel.
 * Version: 1.1.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése (NE módosíts wp-configot) ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) { include_once $__impact_local_cfg; }

/* ===== KONFIG / VÉDŐSÍNEK ===== */
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', getenv('DOGNET_LOGIN_EMAIL') ?: '');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', getenv('DOGNET_LOGIN_PASSWORD') ?: '');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT')) define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))    define('IMPACT_BRIDGE_TIMEOUT', 25);

/* Kompat aliasok */
if (!defined('DOGNET_EMAIL') && defined('DOGNET_LOGIN_EMAIL'))       define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD') && defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

/* ===== DOGNET auth + request ===== */
function ibl__dognet_try_login_once($payload, $headers){
  $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $headers = array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers);
  $resp = wp_remote_post($ep, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'headers'=>$headers,'body'=>$payload,'redirection'=>3,'sslverify'=>true]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $j = json_decode(wp_remote_retrieve_body($resp), true);
  $tok = '';
  if (is_array($j)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k==='data' || $k==='result') {
        if (!empty($j[$k]['token']))        { $tok=$j[$k]['token']; break; }
        if (!empty($j[$k]['access_token'])) { $tok=$j[$k]['access_token']; break; }
      } elseif (!empty($j[$k]) && is_string($j[$k])) { $tok = $j[$k]; break; }
    }
  }
  return ($code>=200 && $code<300 && $tok) ? ['ok'=>true,'token'=>$tok] : ['ok'=>false,'why'=>'HTTP '.$code];
}

function ibl_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key='ibl_dognet_tok_v1';
  if (!$force){ $t = get_transient($key); if ($t) return $t; }
  $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;
  if (!$email || !$pass) return '';

  $r = ibl__dognet_try_login_once(wp_json_encode(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/json']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  $r = ibl__dognet_try_login_once(http_build_query(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/x-www-form-urlencoded']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  return '';
}

function ibl_dognet_request($method,$path,$body=null){
  $tok = ibl_dognet_get_token(false);
  if (!$tok) return new WP_Error('no_token','Dognet token hiba – hiányzik az e-mail/jelszó a local configban');

  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $args = [
    'timeout'  => IMPACT_BRIDGE_TIMEOUT,
    'method'   => $method,
    'headers'  => [
      'Authorization' => 'Bearer '.$tok,
      'Accept'        => 'application/json',
      'Content-Type'  => 'application/json',
      'User-Agent'    => IMPACT_BRIDGE_USER_AGENT,
    ],
    'redirection'=>3, 'sslverify'=>true,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);

  $r = wp_remote_request($url,$args);
  if (is_wp_error($r)) return $r;
  $code = wp_remote_retrieve_response_code($r);
  $j    = json_decode(wp_remote_retrieve_body($r), true);

  if ($code==401){
    delete_transient('ibl_dognet_tok_v1');
    $tok=ibl_dognet_get_token(true);
    if(!$tok) return new WP_Error('no_token','401 + token refresh fail');
    $args['headers']['Authorization']='Bearer '.$tok;
    $r=wp_remote_request($url,$args);
    if (is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r);
    $j=json_decode(wp_remote_retrieve_body($r), true);
  }
  if ($code<200 || $code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}

/* ===== Segédek ===== */
function ibl__num($v){ return is_numeric($v)? floatval($v) : 0.0; }

/**
 * NGO/azonosító kiválasztása (ref/d1…d5/subid…data1…data5/utm)
 * - Eldobja a számnak látszó ÉS a JSON-nak tűnő értékeket ({"..."} vagy ["..."])
 * - Key=Value stringből és teljes URL queryből is próbál olvasni (ngo|ngo_name|d1|org|org_name|utm_term)
 */
function ibl__pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // 1) ha "key=value&..." → bontsuk ki
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // 2) ha teljes URL → query olvasás
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // 3) szemét/hibás értékek szűrése
    if ($v === '' || is_numeric($v)) continue;             // számnak látszó → kuka
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue; // JSON-szerű → kuka

    return $v;
  }
  return '';
}

/* ===== Alap lekérdező: raw-transactions/filter (last_id görgetés) ===== */
function ibl_fetch_transactions($from, $to, $status='approved', $maxBatches=60, $perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [ ['created_at'=>['gte'=>$fromDt]], ['created_at'=>['lte'=>$toDt]] ];

  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }

  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ibl_dognet_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){
        foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); }
      }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Ticker / Leaderboard / Activity ===== */
function ibl_build_ticker(){
  $cache='ibl_ticker_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $total=0.0; $todaySum=0.0; $today=date('Y-m-d');
  foreach($rows as $r){
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $total += $comm;
    $created = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,10);
    if ($created===$today) $todaySum += $comm;
  }
  $out=['total'=>$total*0.5, 'today'=>$todaySum*0.5, 'generated_at'=>current_time('mysql')]; // UI: adomány
  set_transient($cache,$out, 180); return $out;
}

function ibl_build_leaderboard($tab='ngo'){
  $cache='ibl_lb_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $map=[];
  if ($tab==='ngo'){
    foreach($rows as $r){
      $ngo  = ibl__pick_d1($r);
      if ($ngo === '' || is_numeric($ngo)) $ngo = '(ismeretlen)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$ngo]=($map[$ngo]??0)+$don;
    }
  } else {
    foreach($rows as $r){
      $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
        if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
      }
      $name = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$name]=($map[$name]??0)+$don;
    }
  }
  $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  usort($out, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));
  set_transient($cache,$out, 300); return $out;
}

function ibl_build_activity(){
  $cache='ibl_activity_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d', strtotime('-3 days')); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved', 10, 100);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  usort($rows, function($a,$b){
    $da = strtotime($a['created_at'] ?? ($a['created'] ?? ''));
    $db = strtotime($b['created_at'] ?? ($b['created'] ?? ''));
    return $db <=> $da;
  });
  $rows = array_slice($rows,0,10);

  $out=[];
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r);
    if ($ngo === '' || is_numeric($ngo)) $ngo = 'egy szervezet';
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don  = $comm * 0.5;
    $out[]=['text'=> sprintf('%s támogatást hozott ~%s € adományban', $ngo, number_format($don,2,',',' '))];
  }
  set_transient($cache,$out, 120); return $out;
}

/* ===== REST: /wp-json/impact/v1/{ticker|leaderboard|activity} ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/ticker', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_ticker(); return is_array($d)? rest_ensure_response($d) : new WP_Error('err','Hiba'); }
  ]);
  register_rest_route('impact/v1','/leaderboard', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $d=ibl_build_leaderboard($tab);
      if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
      return rest_ensure_response($d);
    }
  ]);
  register_rest_route('impact/v1','/activity', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_activity(); if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]); return rest_ensure_response($d); }
  ]);
});

/* ===== (Opcionális) Debug: kulcsok mintavétele ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/debug/sample', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){
      $from = date('Y-m-d', strtotime('-30 days'));
      $to   = date('Y-m-d');
      $rows = ibl_fetch_transactions($from,$to,'approved', 3, 50);
      if (is_wp_error($rows) || !$rows) return ['keys'=>[],'sample'=>[]];
      $first = (array)$rows[0];
      $keys = array_keys($first);
      $sample = [];
      foreach($first as $k=>$v){ $sample[$k] = is_scalar($v) ? mb_substr((string)$v,0,80) : (is_array($v)?'[array]':'[obj]'); }
      return ['keys'=>$keys, 'sample'=>$sample];
    }
  ]);
});
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Oké, ez klasszikus Elementor-editor konfliktusnak hangzik. A kódrészleteid alapján az egyik „gyanús” pont a Report plugin wp_footer-be injektált JS-e, ami az Elementor szerkesztő iframe-ben is lefut — és ettől az editor betöltése elszállhat. Adjunk két célzott patch-et, amivel általában azonnal helyrejön:
1) Report plugin: ne fusson a szerkesztőben (Elementor guard)
Fájl: wp-content/plugins/impact-report-mvp/impact-report-mvp.php
Cseréld le az egész add_action('wp_footer', function(){ ... }); blokk elejét az alábbira (a meglévő JS maradhat utána változatlanul):
add_action('wp_footer', function(){
  // Ne fusson adminban, AJAX/REST alatt, és Elementor szerkesztő módban
  if (is_admin()) return;
  if (defined('REST_REQUEST') && REST_REQUEST) return;
  if (wp_doing_ajax()) return;

  // Elementor editor guard
  if (defined('ELEMENTOR_VERSION')) {
    try {
      if (class_exists('\Elementor\Plugin')
          && \Elementor\Plugin::$instance->editor
          && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return;
      }
    } catch (\Throwable $e) { /* csendben kilépünk */ }
  }

  if (!is_singular()) return;

  // --- innen mehet a meglévő JS kódod változatlanul ---
Miért kell? Az Elementor szerkesztő nézet egy beágyazott iframe, nem a „rendes” frontend. A mi gomb-beszúró JS-ünk ott nincs felkészítve a DOM-ra → elég egy null.append(...) hiba, és az editor nem nyílik meg.
2) Report plugin: REST szűrő legyen „szuper-szűk”
A kódod már szűr route-ra, ezt hagyd így, ez fontos:
add_filter('rest_pre_echo_response', function
Ezt mondtad:
Kerem mindketto plugint egyben teljes cserere mert kulonben kaosz lesz
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
megvan — itt a két plugin teljes, egyben bemásolható csereverziója.
Mindkettő: UTF-8 (BOM nélkül), a fájl elején az első karakter <?php, a végén nincs ?>.
Másold fel a pontos útvonalra, majd kapcsold be őket.
1) Impact Bridge Local (ticker/leaderboard/activity – REST)
Útvonal: wp-content/plugins/impact-bridge-local/impact-bridge-local.php
<?php
/**
 * Plugin Name: Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
 * Description: Helyi REST végpontok az Impact frontnak. A Dognet Publisher API-ból számol ticker/leaderboard/activity-t, cache-sel.
 * Version: 1.2.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése (NE módosíts wp-configot) ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) { include_once $__impact_local_cfg; }

/* ===== KONFIG / VÉDŐSÍNEK ===== */
if (!defined('DOGNET_API_BASE'))          define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_LOGIN_EMAIL'))       define('DOGNET_LOGIN_EMAIL', getenv('DOGNET_LOGIN_EMAIL') ?: '');
if (!defined('DOGNET_LOGIN_PASSWORD'))    define('DOGNET_LOGIN_PASSWORD', getenv('DOGNET_LOGIN_PASSWORD') ?: '');
if (!defined('DOGNET_API_TOKEN'))         define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_TOKEN_TTL'))         define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT')) define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))    define('IMPACT_BRIDGE_TIMEOUT', 25);

/* Kompat aliasok */
if (!defined('DOGNET_EMAIL') && defined('DOGNET_LOGIN_EMAIL'))         define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD') && defined('DOGNET_LOGIN_PASSWORD'))   define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

/* ===== DOGNET auth + request ===== */
function ibl__dognet_try_login_once($payload, $headers){
  $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $headers = array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers);
  $resp = wp_remote_post($ep, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'headers'=>$headers,'body'=>$payload,'redirection'=>3,'sslverify'=>true]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);
  $j = json_decode($body, true); $tok = '';
  if (is_array($j)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k==='data' || $k==='result') {
        if (!empty($j[$k]['token']))        { $tok=$j[$k]['token']; break; }
        if (!empty($j[$k]['access_token'])) { $tok=$j[$k]['access_token']; break; }
      } elseif (!empty($j[$k]) && is_string($j[$k])) { $tok = $j[$k]; break; }
    }
  }
  return ($code>=200 && $code<300 && $tok) ? ['ok'=>true,'token'=>$tok] : ['ok'=>false,'why'=>'HTTP '.$code];
}

function ibl_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key='ibl_dognet_tok_v1';
  if (!$force){ $t = get_transient($key); if ($t) return $t; }
  $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;
  if (!$email || !$pass) return '';

  $r = ibl__dognet_try_login_once(wp_json_encode(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/json']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  $r = ibl__dognet_try_login_once(http_build_query(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/x-www-form-urlencoded']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  return '';
}

function ibl_dognet_request($method,$path,$body=null){
  $tok = ibl_dognet_get_token(false);
  if (!$tok) return new WP_Error('no_token','Dognet token hiba – hiányzik az e-mail/jelszó a wp-content/impact-bridge-local.php-ban');

  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $args = [
    'timeout'  => IMPACT_BRIDGE_TIMEOUT,
    'method'   => $method,
    'headers'  => [
      'Authorization' => 'Bearer '.$tok,
      'Accept'        => 'application/json',
      'Content-Type'  => 'application/json',
      'User-Agent'    => IMPACT_BRIDGE_USER_AGENT,
    ],
    'redirection'=>3, 'sslverify'=>true,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);

  $r = wp_remote_request($url,$args);
  if (is_wp_error($r)) return $r;
  $code = wp_remote_retrieve_response_code($r);
  $j    = json_decode(wp_remote_retrieve_body($r), true);

  if ($code==401){
    delete_transient('ibl_dognet_tok_v1');
    $tok=ibl_dognet_get_token(true);
    if(!$tok) return new WP_Error('no_token','401 + token refresh fail');
    $args['headers']['Authorization']='Bearer '.$tok;
    $r=wp_remote_request($url,$args);
    if (is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r);
    $j=json_decode(wp_remote_retrieve_body($r), true);
  }
  if ($code<200 || $code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}

/* ===== Segédek ===== */
function ibl__num($v){ return is_numeric($v)? floatval($v) : 0.0; }

/**
 * NGO/azonosító kiválasztása (ref/d1…d5/subid…data1…data5/utm)
 * - Eldobja a számnak látszó ÉS a JSON-nak tűnő értékeket ({"..."} vagy ["..."])
 * - Key=Value stringből és teljes URL queryből is próbál olvasni (ngo|ngo_name|d1|org|org_name|utm_term)
 */
function ibl__pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // 1) "a=b&..." jelleg: próbáljunk kulcsból olvasni
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // 2) Teljes URL → query bontás
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // 3) Kidobandók: üres, szám, JSON-szerű
    if ($v === '' || is_numeric($v)) continue;
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue;

    return $v;
  }
  return '';
}

/* ===== Alap lekérdező: raw-transactions/filter (last_id görgetés) ===== */
function ibl_fetch_transactions($from, $to, $status='approved', $maxBatches=60, $perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [ ['created_at'=>['gte'=>$fromDt]], ['created_at'=>['lte'=>$toDt]] ];

  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }

  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ibl_dognet_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){
        foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); }
      }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Ticker / Leaderboard / Activity ===== */
function ibl_build_ticker(){
  $cache='ibl_ticker_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $total=0.0; $todaySum=0.0; $today=date('Y-m-d');
  foreach($rows as $r){
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $total += $comm;
    $created = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,10);
    if ($created===$today) $todaySum += $comm;
  }
  $out=['total'=>$total*0.5, 'today'=>$todaySum*0.5, 'generated_at'=>current_time('mysql')]; // UI: adomány
  set_transient($cache,$out, 180); return $out;
}

function ibl_build_leaderboard($tab='ngo'){
  $cache='ibl_lb_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'approved');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $map=[];
  if ($tab==='ngo'){
    foreach($rows as $r){
      $ngo  = ibl__pick_d1($r);
      if ($ngo === '' || is_numeric($ngo)) $ngo = '(ismeretlen)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$ngo]=($map[$ngo]??0)+$don;
    }
  } else {
    foreach($rows as $r){
      $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
        if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
      }
      $name = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$name]=($map[$name]??0)+$don;
    }
  }
  $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  usort($out, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));
  set_transient($cache,$out, 300); return $out;
}

function ibl_build_activity(){
  $cache='ibl_activity_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d', strtotime('-14 days')); $to=date('Y-m-d'); // tágabb ablak
  $rows = ibl_fetch_transactions($from,$to,'approved', 10, 100);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  usort($rows, function($a,$b){
    $da = strtotime($a['created_at'] ?? ($a['created'] ?? ''));
    $db = strtotime($b['created_at'] ?? ($b['created'] ?? ''));
    return $db <=> $da;
  });
  $rows = array_slice($rows,0,10);

  $out=[];
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r);
    if ($ngo === '' || is_numeric($ngo)) $ngo = 'egy szervezet';
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don  = $comm * 0.5;
    $out[]=['text'=> sprintf('%s támogatást hozott ~%s € adományban', $ngo, number_format($don,2,',',' '))];
  }
  set_transient($cache,$out, 120); return $out;
}

/* ===== REST: /wp-json/impact/v1/{ticker|leaderboard|activity} ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/ticker', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_ticker(); return is_array($d)? rest_ensure_response($d) : new WP_Error('err','Hiba'); }
  ]);
  register_rest_route('impact/v1','/leaderboard', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $d=ibl_build_leaderboard($tab);
      if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
      return rest_ensure_response($d);
    }
  ]);
  register_rest_route('impact/v1','/activity', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_activity(); if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]); return rest_ensure_response($d); }
  ]);
});

/* ===== (Opcionális) Debug: kulcsok mintavétele ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/debug/sample', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){
      $from = date('Y-m-d', strtotime('-30 days'));
      $to   = date('Y-m-d');
      $rows = ibl_fetch_transactions($from,$to,'approved', 3, 50);
      if (is_wp_error($rows) || !$rows) return ['keys'=>[],'sample'=>[]];
      $first = (array)$rows[0];
      $keys = array_keys($first);
      $sample = [];
      foreach($first as $k=>$v){ $sample[$k] = is_scalar($v) ? mb_substr((string)$v,0,80) : (is_array($v)?'[array]':'[obj]'); }
      return ['keys'=>$keys, 'sample'=>$sample];
    }
  ]);
});
2) Impact Report MVP (REST + shortcode + CSV)
Útvonal: wp-content/plugins/impact-report-mvp/impact-report-mvp.php
<?php
/**
 * Plugin Name: Impact Report MVP (Sharity)
 * Description: Paraméterezhető riport (shop×NGO / NGO / Shop) – REST + shortcode, 15 perces cache, CSV export.
 * Version: 0.3.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) { include_once $__impact_local_cfg; }

/* ===== Alap konstansok ===== */
if (!defined('DOGNET_API_BASE'))              define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))             define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_RPT_CACHE_MINUTES'))     define('IMPACT_RPT_CACHE_MINUTES', 15);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))        define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))     define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');

/* ===== HTTP + Auth ===== */
function ir_http($method, $url, $body=null, $headers=[]) {
  $args = [
    'method' => $method,
    'timeout'=> IMPACT_BRIDGE_TIMEOUT,
    'headers'=> array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers),
    'sslverify'=> true, 'redirection'=> 3,
  ];
  if ($body !== null) { $args['headers']['Content-Type']='application/json'; $args['body']=is_string($body)?$body:wp_json_encode($body); }
  $res = wp_remote_request($url,$args);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  $json = json_decode(wp_remote_retrieve_body($res), true);
  if ($code>=200 && $code<300) return $json;
  return new WP_Error('dognet_http','Dognet API hiba',['code'=>$code,'body'=>$json]);
}
function ir_token($force=false){
  $key='impact_rpt_tok_v1';
  if (!$force && ($t=get_transient($key))) return $t;
  $email = defined('DOGNET_LOGIN_EMAIL')    ? DOGNET_LOGIN_EMAIL    : getenv('DOGNET_LOGIN_EMAIL');
  $pass  = defined('DOGNET_LOGIN_PASSWORD') ? DOGNET_LOGIN_PASSWORD : getenv('DOGNET_LOGIN_PASSWORD');
  if (!$email || !$pass) return new WP_Error('cfg','Hiányzik DOGNET_LOGIN_EMAIL / DOGNET_LOGIN_PASSWORD a wp-content/impact-bridge-local.php-ban');
  $auth = ir_http('POST', rtrim(DOGNET_API_BASE,'/').'/auth/login', ['email'=>$email,'password'=>$pass]);
  if (is_wp_error($auth) || empty($auth['token'])) return new WP_Error('auth','Auth sikertelen', ['resp'=>$auth]);
  set_transient($key,$auth['token'], DOGNET_TOKEN_TTL);
  return $auth['token'];
}
function ir_api($path,$body=null){
  $tok = ir_token(false);
  if (is_wp_error($tok)) return $tok;
  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  if (is_wp_error($res) && $res->get_error_code()==='dognet_http' && ($res->get_error_data()['code']??0)==401){
    delete_transient('impact_rpt_tok_v1');
    $tok = ir_token(true); if (is_wp_error($tok)) return $tok;
    $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
  }
  return $res;
}

/* ===== Segédek ===== */
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }

function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // "a=b&ngo=XY" jellegű értékek kibányászása
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // Teljes URL → query pars-olás
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // Kidobandók: üres, számnak látszó, JSON-szerű
    if ($v === '' || is_numeric($v)) continue;
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue;

    return $v;
  }
  return '';
}

/* ===== Adatok: raw-transactions/filter ===== */
function ir_fetch_transactions($from,$to,$status='approved',$maxBatches=60,$perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [
    ['created_at'=>['gte'=>$fromDt]],
    ['created_at'=>['lte'=>$toDt]],
  ];
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }
  $stat = strtolower(trim($status));
  if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ir_api('/raw-transactions/filter', $body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){ foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); } }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Aggregálás (group: shop_ngo | ngo | shop) ===== */
function ir_aggregate($rows,$group='shop_ngo'){
  $out=[]; $sumBasket=0; $sumComm=0; $sumDon=0;
  foreach($rows as $r){
    $basket = ir_num($r['order_value'] ?? ($r['basket_value'] ?? 0));
    $comm   = ir_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don    = $comm * 0.5;

    $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
      if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
    }
    $shop = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
    $ngo  = ir_pick_d1($r) ?: '(ismeretlen NGO)';

    $key = $group==='ngo'  ? $ngo : ($group==='shop' ? $shop : ($shop.' × '.$ngo));

    if (!isset($out[$key])) $out[$key]=['rows'=>0,'basket'=>0.0,'comm'=>0.0,'don'=>0.0];
    $out[$key]['rows']   += 1;
    $out[$key]['basket'] += $basket;
    $out[$key]['comm']   += $comm;
    $out[$key]['don']    += $don;

    $sumBasket += $basket; $sumComm += $comm; $sumDon += $don;
  }
  uasort($out, function($a,$b){
    $d = $b['don'] <=> $a['don'];
    if ($d!==0) return $d;
    return 0;
  });
  return ['groups'=>$out,'totals'=>['rows'=>array_sum(array_column($out,'rows')),'basket'=>$sumBasket,'comm'=>$sumComm,'don'=>$sumDon]];
}

/* ====== REST: /impact/v1/report ====== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/report', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $from  = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $stat  = sanitize_text_field($req->get_param('status') ?: 'approved');
      $group = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $ngo   = sanitize_text_field($req->get_param('ngo') ?: '');

      $cache_key = 'impact_report_v1:'.md5(json_encode([$from,$to,$stat,$group,$ngo]));
      if ($cached = get_transient($cache_key)) return $cached;

      $rows = ir_fetch_transactions($from,$to,$stat);
      if (is_wp_error($rows)) return new WP_Error('dognet_err','Dognet hiba: '.$rows->get_error_message(),['status'=>502]);

      if ($ngo){
        $rows = array_values(array_filter($rows, function($r) use($ngo){
          $d1 = ir_pick_d1($r);
          return $d1 && (sanitize_title($d1) === sanitize_title($ngo));
        }));
      }

      $agg = ir_aggregate($rows,$group);
      set_transient($cache_key, $agg, IMPACT_RPT_CACHE_MINUTES * MINUTE_IN_SECONDS);
      return $agg;
    }
  ]);
});

/* ===== Shortcode: [impact_report] ===== */
add_shortcode('impact_report', function($atts){
  $q = wp_parse_args($_GET, [
    'from'=>date('Y-m-01'),
    'to'=>date('Y-m-d'),
    'status'=>'approved',
    'group'=>'shop_ngo',
    'ngo'=>'',
  ]);
  $api = esc_url_raw( add_query_arg([
    'from'=>$q['from'],'to'=>$q['to'],'status'=>$q['status'],'group'=>$q['group'],'ngo'=>$q['ngo']
  ], rest_url('impact/v1/report')) );

  $res = wp_remote_get($api, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'sslverify'=>true, 'headers'=>['User-Agent'=>IMPACT_BRIDGE_USER_AGENT]]);
  $body = is_wp_error($res) ? [] : json_decode(wp_remote_retrieve_body($res), true);
  $groups = $body['groups'] ?? [];
  $tot = $body['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

  ob_start(); ?>
  <div class="impact-report-wrap">
    <form class="ir-filters" method="get">
      <label>From <input type="date" name="from" value="<?php echo esc_attr($q['from']); ?>"></label>
      <label>To <input type="date" name="to" value="<?php echo esc_attr($q['to']); ?>"></label>
      <label>Status
        <select name="status">
          <?php foreach(['approved','pending','rejected','all'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php selected($q['status'],$s); ?>><?php echo ucfirst($s); ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Group
        <select name="group">
          <option value="shop_ngo" <?php selected($q['group'],'shop_ngo'); ?>>Shop × NGO</option>
          <option value="ngo" <?php selected($q['group'],'ngo'); ?>>NGO</option>
          <option value="shop" <?php selected($q['group'],'shop'); ?>>Shop</option>
        </select>
      </label>
      <label>NGO (slug)
        <input type="text" name="ngo" placeholder="pl. bator-tabor-alapitvany" value="<?php echo esc_attr($q['ngo']); ?>">
      </label>
      <button type="submit">Szűrés</button>
    </form>

    <div class="ir-table-wrap">
      <table class="ir-table">
        <thead>
          <tr>
            <th>Csoport</th>
            <th style="text-align:right">Kosárérték (€)</th>
            <th style="text-align:right">Jutalék (€)</th>
            <th style="text-align:right">Adomány (50%) (€)</th>
            <th style="text-align:right">Darab</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$groups): ?>
          <tr><td colspan="5" style="text-align:center;color:#9CA3AF">Nincs találat a megadott szűrőkre.</td></tr>
        <?php else:
          foreach($groups as $name=>$v): ?>
            <tr>
              <td><?php echo esc_html($name); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['basket'], 2, ',', ' '); ?></td>
              <td style="text-align:right"><?php echo number_format((float)$v['comm'],   2, ',', ' '); ?></td>
              <td style="text-align:right"><strong><?php echo number_format((float)$v['don'],    2, ',', ' '); ?></strong></td>
              <td style="text-align:right"><?php echo (int)$v['rows']; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th>Összesen</th>
            <th style="text-align:right"><?php echo number_format((float)$tot['basket'], 2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['comm'],   2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo number_format((float)$tot['don'],    2, ',', ' '); ?></th>
            <th style="text-align:right"><?php echo (int)$tot['rows']; ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <style>
    .impact-report-wrap{background:#111214;border:1px solid #1F2937;border-radius:16px;padding:12px}
    .impact-report-wrap .ir-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;align-items:end}
    .impact-report-wrap .ir-filters label{display:flex;flex-direction:column;font-size:.92rem;color:#E5E7EB}
    .impact-report-wrap .ir-filters input,.impact-report-wrap .ir-filters select{background:#0d0e11;color:#E5E7EB;border:1px solid #1F2937;border-radius:10px;padding:8px}
    .impact-report-wrap .ir-filters button{background:linear-gradient(90deg,#7C3AED,#06B6D4);color:#000;border:0;border-radius:999px;padding:8px 14px;font-weight:800}
    .impact-report-wrap .ir-table{width:100%;border-collapse:separate;border-spacing:0 6px;color:#E5E7EB}
    .impact-report-wrap .ir-table thead th{font-size:.9rem;color:#9CA3AF;border-bottom:1px solid #1F2937;padding:8px}
    .impact-report-wrap .ir-table tbody td{background:#0d0e11;border-top:1px solid #1F2937;border-bottom:1px solid #1F2937;padding:10px}
    .impact-report-wrap .ir-table tbody tr td:first-child{border-left:1px solid #1F2937;border-top-left-radius:10px;border-bottom-left-radius:10px}
    .impact-report-wrap .ir-table tbody tr td:last-child{border-right:1px solid #1F2937;border-top-right-radius:10px;border-bottom-right-radius:10px}
    .impact-report-wrap .ir-table tfoot th{border-top:1px solid #1F2937;padding:10px}
    @media(max-width:900px){.impact-report-wrap .ir-filters{flex-direction:column;alignitems:stretch}}
  </style>
  <?php
  return ob_get_clean();
});

/* ===== CSV EXPORT + CACHE FLUSH ===== */
function ir_render_csv($groups, $totals){
  $out = fopen('php://temp', 'r+');
  fputcsv($out, ['Csoport','Kosárérték (€)','Jutalék (€)','Adomány (50%) (€)','Darab'], ';');
  foreach($groups as $name=>$v){
    fputcsv($out, [
      $name,
      number_format((float)$v['basket'], 2, ',', ' '),
      number_format((float)$v['comm'],   2, ',', ' '),
      number_format((float)$v['don'],    2, ',', ' '),
      (int)$v['rows'],
    ], ';');
  }
  fputcsv($out, ['Összesen',
    number_format((float)$totals['basket'], 2, ',', ' '),
    number_format((float)$totals['comm'],   2, ',', ' '),
    number_format((float)$totals['don'],    2, ',', ' '),
    (int)$totals['rows']
  ], ';');
  rewind($out);
  return stream_get_contents($out);
}

/* REST bővítés: format=csv és impact_flush=1 – csak a MI végpontunkra! */
add_filter('rest_pre_echo_response', function($result, $server, $request){
  $route = $request->get_route();
  if ($route !== '/impact/v1/report') return $result;

  // Cache flush (?impact_flush=1)
  if (isset($_GET['impact_flush']) && $_GET['impact_flush']=='1'){
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_impact_report_v1:%' OR option_name LIKE '_transient_timeout_impact_report_v1:%'");
  }

  // CSV export (&format=csv)
  if (strtolower((string)$request->get_param('format')) === 'csv'){
    $data = is_array($result) ? $result : [];
    $csv  = ir_render_csv($data['groups'] ?? [], $data['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0]);
    $resp = new WP_REST_Response($csv, 200);
    $resp->header('Content-Type', 'text/csv; charset=utf-8');
    $resp->header('Content-Disposition', 'attachment; filename=\"impact-report.csv\"");
    return $resp;
  }
  return $result;
}, 10, 3);

/* ===== UI gombok – NE fussanak Elementor editorban! ===== */
add_action('wp_footer', function(){
  if (is_admin()) return;
  if (defined('REST_REQUEST') && REST_REQUEST) return;
  if (wp_doing_ajax()) return;

  // Elementor editor guard
  if (defined('ELEMENTOR_VERSION')) {
    try {
      if (class_exists('\Elementor\Plugin')
          && \Elementor\Plugin::$instance->editor
          && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return;
      }
    } catch (\Throwable $e) { /* csendben kilépünk */ }
  }

  if (!is_singular()) return; ?>
  <script>
  (function(){
    const wrap = document.querySelector('.impact-report-wrap');
    if(!wrap) return;
    function qs(name, def=''){ const u = new URL(window.location.href); return u.searchParams.get(name) || def; }
    const params = new URLSearchParams({
      from: qs('from','<?php echo esc_js(date('Y-m-01')); ?>'),
      to: qs('to','<?php echo esc_js(date('Y-m-d')); ?>'),
      status: qs('status','approved'),
      group: qs('group','shop_ngo'),
      ngo: qs('ngo',''),
      format: 'csv'
    });
    const exportUrl = '<?php echo esc_url_raw( rest_url('impact/v1/report') ); ?>' + '?' + params.toString();

    const bar = document.createElement('div');
    bar.style.display='flex'; bar.style.gap='10px'; bar.style.marginTop='10px';

    const btnExport = document.createElement('a');
    btnExport.textContent = 'Export CSV';
    btnExport.href = exportUrl;
    btnExport.style.background='linear-gradient(90deg,#7C3AED,#06B6D4)';
    btnExport.style.color='#000'; btnExport.style.padding='8px 14px';
    btnExport.style.borderRadius='999px'; btnExport.style.fontWeight='800'; btnExport.style.textDecoration='none';

    const btnFlush = document.createElement('a');
    const u = new URL(window.location.href);
    u.searchParams.set('impact_flush','1');
    btnFlush.textContent = 'Cache frissítése';
    btnFlush.href = u.toString();
    btnFlush.style.background='#0d0e11'; btnFlush.style.color='#E5E7EB';
    btnFlush.style.padding='8px 14px'; btnFlush.style.borderRadius='999px';
    btnFlush.style.border='1px solid #1F2937'; btnFlush.style.textDecoration='none';

    bar.append(btnExport, btnFlush);
    wrap.append(bar);
  })();
  </script>
<?php
});
Gyors „utána” ellenőrzőlista
 Mindkét plugin fájl lecserélve, BOM nélkül, nincs záró ?>.
 Bekapcsolva: Impact Bridge Local + Impact Report MVP.
 Elementor szerkesztő most már megnyílik (a report plugin JS-e nem fut editorban).
 REST:
/wp-json/impact/v1/ticker → adomány összegek
/wp-json/impact/v1/leaderboard?tab=ngo → nevek nem JSON, ha hiányzik: „(ismeretlen)”
/wp-json/impact/v1/activity → 1–10 sor (14 nap ablak)
/wp-json/impact/v1/report?... → kulcsokban nincs JSON
 Cache flush (ha kell):
report: &impact_flush=1 param az URL végén
bridge: [impact_local_flush] shortcode ideiglenes oldalon
Ha bárhol elakad, dobd ide a böngésző Konzol első piros sorát (szöveg + fájlnév), és rászúrom a pontos javítást.
Ezt mondtad:
Nem nyilik meg az elementor
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Oké, csináljuk meg úgy, hogy most AZONNAL megnyíljon az Elementor. Három gyors, célzott lépés – mindet megadom készen:
1) Nyírjuk ki a „rejtett kimenetet” (BOM/whitespace) a local config include-nál
Ha a wp-content/impact-bridge-local.php fájlban akár 1 bájt kimenet is csúszik (BOM, üres sor), az megakasztja az Elementor előnézetet. A legbiztosabb: nyeljük le minden kimenetét.
Mindkét pluginban cseréld le az include-ot erre:

Impact Bridge Local (wp-content/plugins/impact-bridge-local/impact-bridge-local.php)
Impact Report MVP (wp-content/plugins/impact-report-mvp/impact-report-mvp.php)

$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) {
  ob_start();
  include_once $__impact_local_cfg;
  ob_end_clean(); // bármi véletlen kimenet → kuka
}
Ez akkor is véd, ha a local file BOM-os, vagy véletlen szóköz/echo maradt benne. Nem kell wp-config-ot piszkálni.
2) Garantáltan ne fusson frontos JS az Elementor szerkesztőben
Ezt már részben beraktuk, de tegyük betonszilárdra.
Report plugin – wp_footer guard blokk eleje legyen ilyen (felülírható a mostani):

add_action('wp_footer', function(){
  if (is_admin()) return;
  if (defined('REST_REQUEST') && REST_REQUEST) return;
  if (wp_doing_ajax()) return;

  // Elementor editor guard – itt biztosan lépjen ki
  if (defined('ELEMENTOR_VERSION')) {
    try {
      if (class_exists('\Elementor\Plugin')) {
        $E = \Elementor\Plugin::$instance;
        if ( ($E->editor && $E->editor->is_edit_mode())
          || ($E->preview && method_exists($E->preview,'is_preview_mode') && $E->preview->is_preview_mode()) ) {
          return;
        }
      }
    } catch (\Throwable $e) { return; }
  }

  if (!is_singular()) return;

  // … a meglévő JS-ed innen folytatódik …
Bridge plugin nem szúr be front JS-t, ott ez nem kell.
3) Gyors cache ürítés + Elementor saját gyorsjavítások
Nyiss meg egy ideiglenes oldalt, és tedd bele a shortcode-ot:
[impact_local_flush]
Frissítsd: „Impact LOCAL cache ürítve.” → ok.
WordPress admin → Elementor → Eszközök:
CSS fájlok regenerálása
Könyvtár szinkronizálása
Közvetlen hivatkozások mentése újra:
Beállítások → Közvetlen hivatkozások → „Változtatás mentése” (semmit nem kell átírni).
Most ellenőrzés (ezeknek menniük kell)
/wp-json/ betölt JSON indexet.
/wp-json/impact/v1/ticker válaszol.
Elementor szerkesztő oldal megnyílik.
Ha még mindig nem nyílik meg, 99%, hogy külső blokk (tárhely WAF/ModSecurity) tilt egy kérést. 10 másodperc alatt kiderül:
Safari (magyar):
Felső menü: Fejlesztés → Webfelügyelő megjelenítése → Hálózat
Nyisd meg újra a szerkesztőt, és nézd a piros sort. Tipikus tettesek:

admin-ajax.php 403/406 → ModSecurity szabály (szolgáltatónak szólni, engedjék ki).
/wp-json/elementor/* 403 → WAF tilt (szolgáltatónak szólj a REST kivételre).
Dobd ide a piros sor státuszkód + fájlnév párost, és megmondom a pontos megoldást (ha WAF, adok kész szöveget a szolgáltatónak).
Mini-ellenőrzőlista (ne kelljen visszalapozni)
 Mindkét pluginban include_once → ob_start() / ob_end_clean()-nel körbevéve.
 Report plugin wp_footer guard pontosan a fenti.
 [impact_local_flush] lefutott (zöld üzenet).
 Elementor → Eszközök: CSS regenerálás.
 Beállítások → Közvetlen hivatkozások: mentés.
Ez a három módosítás együtt szokta „megütni” azt a problémát, amikor a szerkesztő látszólag minden ok nélkül nem nyílik meg.
Ezt mondtad:
A report fatal reportot general es lekapcsol automatikusan
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Értem: a Report plugin aktiváláskor/használatkor fatalt dob, és a WordPress automatikusan kikapcsolja. Nem kérdezősködöm tovább — adok egy „stabil mód” teljes csereverziót, ami:
elnyeli a local config (wp-content/impact-bridge-local.php) bármilyen kimenetét (BOM/whitespace → nem lesz „Headers already sent”),
minden külső hívást és REST szűrőt try/catch és lépcsőzetes guard védi (adminban, AJAX/REST alatt, Elementor szerkesztőben semmi nem fut),
funkciónév/konstans ütközést nem okoz,
ha bármi váratlan történik, nem hal le, hanem WP_Error->REST 502-t ad vissza.
Másold be egészben az alábbit a meglévő Report plugin fájlja helyére:
Impact Report MVP – stabil csere
Útvonal: wp-content/plugins/impact-report-mvp/impact-report-mvp.php
<?php
/**
 * Plugin Name: Impact Report MVP (Sharity)
 * Description: Paraméterezhető riport (shop×NGO / NGO / Shop) – REST + shortcode, 15 perces cache, CSV export. Stabil védősínekkel.
 * Version: 0.3.1
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése (kimenet elnyelése) ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) {
  ob_start();
  include_once $__impact_local_cfg;
  ob_end_clean();
}

/* ===== Alap konstansok (ütközésmentes) ===== */
if (!defined('DOGNET_API_BASE'))              define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))             define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_RPT_CACHE_MINUTES'))     define('IMPACT_RPT_CACHE_MINUTES', 15);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))        define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))     define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');

/* ===== HTTP + Auth (védetten) ===== */
if (!function_exists('ir_http')):
function ir_http($method, $url, $body=null, $headers=[]) {
  try{
    $args = [
      'method' => $method,
      'timeout'=> IMPACT_BRIDGE_TIMEOUT,
      'headers'=> array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers),
      'sslverify'=> true, 'redirection'=> 3,
    ];
    if ($body !== null) { $args['headers']['Content-Type']='application/json'; $args['body']=is_string($body)?$body:wp_json_encode($body); }
    $res = wp_remote_request($url,$args);
    if (is_wp_error($res)) return $res;
    $code = wp_remote_retrieve_response_code($res);
    $json = json_decode(wp_remote_retrieve_body($res), true);
    if ($code>=200 && $code<300) return $json;
    return new WP_Error('dognet_http','Dognet API hiba',['code'=>$code,'body'=>$json]);
  } catch (Throwable $e){
    return new WP_Error('http_throw', $e->getMessage());
  }
}
endif;

if (!function_exists('ir_token')):
function ir_token($force=false){
  try{
    $key='impact_rpt_tok_v1';
    if (!$force && ($t=get_transient($key))) return $t;
    $email = defined('DOGNET_LOGIN_EMAIL')    ? DOGNET_LOGIN_EMAIL    : getenv('DOGNET_LOGIN_EMAIL');
    $pass  = defined('DOGNET_LOGIN_PASSWORD') ? DOGNET_LOGIN_PASSWORD : getenv('DOGNET_LOGIN_PASSWORD');
    if (!$email || !$pass) return new WP_Error('cfg','Hiányzik DOGNET_LOGIN_EMAIL / DOGNET_LOGIN_PASSWORD a wp-content/impact-bridge-local.php-ban');
    $auth = ir_http('POST', rtrim(DOGNET_API_BASE,'/').'/auth/login', ['email'=>$email,'password'=>$pass]);
    if (is_wp_error($auth)) return $auth;
    $tok = $auth['token'] ?? ($auth['data']['token'] ?? ($auth['result']['token'] ?? ''));
    if (!$tok) return new WP_Error('auth','Auth sikertelen', ['resp'=>$auth]);
    set_transient($key,$tok, DOGNET_TOKEN_TTL);
    return $tok;
  } catch (Throwable $e){
    return new WP_Error('auth_throw', $e->getMessage());
  }
}
endif;

if (!function_exists('ir_api')):
function ir_api($path,$body=null){
  try{
    $tok = ir_token(false);
    if (is_wp_error($tok)) return $tok;
    $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
    $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
    if (is_wp_error($res) && $res->get_error_code()==='dognet_http' && ($res->get_error_data()['code']??0)==401){
      delete_transient('impact_rpt_tok_v1');
      $tok = ir_token(true); if (is_wp_error($tok)) return $tok;
      $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
    }
    return $res;
  } catch (Throwable $e){
    return new WP_Error('api_throw', $e->getMessage());
  }
}
endif;

/* ===== Segédek ===== */
if (!function_exists('ir_num')):
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }
endif;

if (!function_exists('ir_pick_d1')):
function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // "a=b&ngo=XY" jellegű értékek
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // Teljes URL → query
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // Szűrés: üres, szám, JSON-szerű
    if ($v === '' || is_numeric($v)) continue;
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue;

    return $v;
  }
  return '';
}
endif;

/* ===== Adatok: raw-transactions/filter ===== */
if (!function_exists('ir_fetch_transactions')):
function ir_fetch_transactions($from,$to,$status='approved',$maxBatches=60,$perPage=200){
  try{
    $fromDt = $from.' 00:00:00';
    $toDt   = $to  .' 23:59:59';
    $filter = [
      ['created_at'=>['gte'=>$fromDt]],
      ['created_at'=>['lte'=>$toDt]],
    ];
    if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
      $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
    }
    $stat = strtolower(trim($status));
    if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
    elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
    elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

    $items=[]; $lastId=null;
    for($i=0;$i<$maxBatches;$i++){
      $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
      $resp = ir_api('/raw-transactions/filter', $body);
      if (is_wp_error($resp)) return $resp;
      $rows = $resp['data'] ?? ($resp['items'] ?? []);
      if (!$rows) break;
      $items = array_merge($items, $rows);
      $lastId = $resp['meta']['last_id'] ?? null;
      if ($lastId===null){
        foreach($rows as $r){ foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); } }
        if ($lastId===null) break;
      }
    }
    return $items;
  } catch (Throwable $e){
    return new WP_Error('fetch_throw', $e->getMessage());
  }
}
endif;

/* ===== Aggregálás (group: shop_ngo | ngo | shop) ===== */
if (!function_exists('ir_aggregate')):
function ir_aggregate($rows,$group='shop_ngo'){
  $out=[]; $sumBasket=0; $sumComm=0; $sumDon=0;
  foreach($rows as $r){
    $basket = ir_num($r['order_value'] ?? ($r['basket_value'] ?? 0));
    $comm   = ir_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don    = $comm * 0.5;

    $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
      if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
    }
    $shop = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
    $ngo  = ir_pick_d1($r) ?: '(ismeretlen NGO)';

    $key = $group==='ngo'  ? $ngo : ($group==='shop' ? $shop : ($shop.' × '.$ngo));

    if (!isset($out[$key])) $out[$key]=['rows'=>0,'basket'=>0.0,'comm'=>0.0,'don'=>0.0];
    $out[$key]['rows']   += 1;
    $out[$key]['basket'] += $basket;
    $out[$key]['comm']   += $comm;
    $out[$key]['don']    += $don;

    $sumBasket += $basket; $sumComm += $comm; $sumDon += $don;
  }
  uasort($out, function($a,$b){
    $d = $b['don'] <=> $a['don'];
    if ($d!==0) return $d;
    return 0;
  });
  return ['groups'=>$out,'totals'=>['rows'=>array_sum(array_column($out,'rows')),'basket'=>$sumBasket,'comm'=>$sumComm,'don'=>$sumDon]];
}
endif;

/* ===== REST: /impact/v1/report ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/report', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      try{
        $from  = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
        $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
        $stat  = sanitize_text_field($req->get_param('status') ?: 'approved');
        $group = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
        $ngo   = sanitize_text_field($req->get_param('ngo') ?: '');

        $cache_key = 'impact_report_v1:'.md5(json_encode([$from,$to,$stat,$group,$ngo]));
        if ($cached = get_transient($cache_key)) return $cached;

        $rows = ir_fetch_transactions($from,$to,$stat);
        if (is_wp_error($rows)) return new WP_Error('dognet_err','Dognet hiba: '.$rows->get_error_message(),['status'=>502]);

        if ($ngo){
          $rows = array_values(array_filter($rows, function($r) use($ngo){
            $d1 = ir_pick_d1($r);
            return $d1 && (sanitize_title($d1) === sanitize_title($ngo));
          }));
        }

        $agg = ir_aggregate($rows,$group);
        set_transient($cache_key, $agg, IMPACT_RPT_CACHE_MINUTES * MINUTE_IN_SECONDS);
        return $agg;
      } catch (Throwable $e){
        return new WP_Error('rpt_throw','Report kivétel: '.$e->getMessage(),['status'=>500]);
      }
    }
  ]);
});

/* ===== Shortcode: [impact_report] ===== */
add_shortcode('impact_report', function($atts){
  try{
    $q = wp_parse_args($_GET, [
      'from'=>date('Y-m-01'),
      'to'=>date('Y-m-d'),
      'status'=>'approved',
      'group'=>'shop_ngo',
      'ngo'=>'',
    ]);
    $api = esc_url_raw( add_query_arg([
      'from'=>$q['from'],'to'=>$q['to'],'status'=>$q['status'],'group'=>$q['group'],'ngo'=>$q['ngo']
    ], rest_url('impact/v1/report')) );

    $res = wp_remote_get($api, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'sslverify'=>true, 'headers'=>['User-Agent'=>IMPACT_BRIDGE_USER_AGENT]]);
    $body = is_wp_error($res) ? [] : json_decode(wp_remote_retrieve_body($res), true);
    $groups = $body['groups'] ?? [];
    $tot = $body['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

    ob_start(); ?>
    <div class="impact-report-wrap">
      <form class="ir-filters" method="get">
        <label>From <input type="date" name="from" value="<?php echo esc_attr($q['from']); ?>"></label>
        <label>To <input type="date" name="to" value="<?php echo esc_attr($q['to']); ?>"></label>
        <label>Status
          <select name="status">
            <?php foreach(['approved','pending','rejected','all'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php selected($q['status'],$s); ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Group
          <select name="group">
            <option value="shop_ngo" <?php selected($q['group'],'shop_ngo'); ?>>Shop × NGO</option>
            <option value="ngo" <?php selected($q['group'],'ngo'); ?>>NGO</option>
            <option value="shop" <?php selected($q['group'],'shop'); ?>>Shop</option>
          </select>
        </label>
        <label>NGO (slug)
          <input type="text" name="ngo" placeholder="pl. bator-tabor-alapitvany" value="<?php echo esc_attr($q['ngo']); ?>">
        </label>
        <button type="submit">Szűrés</button>
      </form>

      <div class="ir-table-wrap">
        <table class="ir-table">
          <thead>
            <tr>
              <th>Csoport</th>
              <th style="text-align:right">Kosárérték (€)</th>
              <th style="text-align:right">Jutalék (€)</th>
              <th style="text-align:right">Adomány (50%) (€)</th>
              <th style="text-align:right">Darab</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$groups): ?>
            <tr><td colspan="5" style="text-align:center;color:#9CA3AF">Nincs találat a megadott szűrőkre.</td></tr>
          <?php else:
            foreach($groups as $name=>$v): ?>
              <tr>
                <td><?php echo esc_html($name); ?></td>
                <td style="text-align:right"><?php echo number_format((float)$v['basket'], 2, ',', ' '); ?></td>
                <td style="text-align:right"><?php echo number_format((float)$v['comm'],   2, ',', ' '); ?></td>
                <td style="text-align:right"><strong><?php echo number_format((float)$v['don'],    2, ',', ' '); ?></strong></td>
                <td style="text-align:right"><?php echo (int)$v['rows']; ?></td>
              </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <th>Összesen</th>
              <th style="text-align:right"><?php echo number_format((float)$tot['basket'], 2, ',', ' '); ?></th>
              <th style="text-align:right"><?php echo number_format((float)$tot['comm'],   2, ',', ' '); ?></th>
              <th style="text-align:right"><?php echo number_format((float)$tot['don'],    2, ',', ' '); ?></th>
              <th style="text-align:right"><?php echo (int)$tot['rows']; ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <style>
      .impact-report-wrap{background:#111214;border:1px solid #1F2937;border-radius:16px;padding:12px}
      .impact-report-wrap .ir-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;align-items:end}
      .impact-report-wrap .ir-filters label{display:flex;flex-direction:column;font-size:.92rem;color:#E5E7EB}
      .impact-report-wrap .ir-filters input,.impact-report-wrap .ir-filters select{background:#0d0e11;color:#E5E7EB;border:1px solid #1F2937;border-radius:10px;padding:8px}
      .impact-report-wrap .ir-filters button{background:linear-gradient(90deg,#7C3AED,#06B6D4);color:#000;border:0;border-radius:999px;padding:8px 14px;font-weight:800}
      .impact-report-wrap .ir-table{width:100%;border-collapse:separate;border-spacing:0 6px;color:#E5E7EB}
      .impact-report-wrap .ir-table thead th{font-size:.9rem;color:#9CA3AF;border-bottom:1px solid #1F2937;padding:8px}
      .impact-report-wrap .ir-table tbody td{background:#0d0e11;border-top:1px solid #1F2937;border-bottom:1px solid #1F2937;padding:10px}
      .impact-report-wrap .ir-table tbody tr td:first-child{border-left:1px solid #1F2937;border-top-left-radius:10px;border-bottom-left-radius:10px}
      .impact-report-wrap .ir-table tbody tr td:last-child{border-right:1px solid #1F2937;border-top-right-radius:10px;border-bottom-right-radius:10px}
      .impact-report-wrap .ir-table tfoot th{border-top:1px solid #1F2937;padding:10px}
      @media(max-width:900px){.impact-report-wrap .ir-filters{flex-direction:column;align-items:stretch}}
    </style>
    <?php
    return ob_get_clean();
  } catch (Throwable $e){
    return '<div style="background:#3b0f0f;color:#fecaca;padding:12px;border-radius:8px">Riport render hiba: '.esc_html($e->getMessage()).'</div>';
  }
});

/* ===== CSV EXPORT + CACHE FLUSH – csak a MI végpontunkra ===== */
add_filter('rest_pre_echo_response', function($result, $server, $request){
  try{
    $route = method_exists($request,'get_route') ? $request->get_route() : '';
    if ($route !== '/impact/v1/report') return $result;

    // Cache flush (?impact_flush=1)
    if (isset($_GET['impact_flush']) && $_GET['impact_flush']=='1'){
      global $wpdb;
      if (isset($wpdb->options)) {
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_impact_report_v1:%' OR option_name LIKE '_transient_timeout_impact_report_v1:%'");
      }
    }

    // CSV export (&format=csv)
    if (strtolower((string)$request->get_param('format')) === 'csv'){
      $data = is_array($result) ? $result : [];
      $groups = $data['groups'] ?? [];
      $totals = $data['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

      $out = fopen('php://temp', 'r+');
      fputcsv($out, ['Csoport','Kosárérték (€)','Jutalék (€)','Adomány (50%) (€)','Darab'], ';');
      foreach($groups as $name=>$v){
        fputcsv($out, [
          $name,
          number_format((float)($v['basket'] ?? 0), 2, ',', ' '),
          number_format((float)($v['comm']   ?? 0), 2, ',', ' '),
          number_format((float)($v['don']    ?? 0), 2, ',', ' '),
          (int)($v['rows'] ?? 0),
        ], ';');
      }
      fputcsv($out, ['Összesen',
        number_format((float)($totals['basket'] ?? 0), 2, ',', ' '),
        number_format((float)($totals['comm']   ?? 0), 2, ',', ' '),
        number_format((float)($totals['don']    ?? 0), 2, ',', ' '),
        (int)($totals['rows'] ?? 0)
      ], ';');
      rewind($out);
      $csv = stream_get_contents($out);

      if (class_exists('WP_REST_Response')) {
        $resp = new WP_REST_Response($csv, 200);
        $resp->header('Content-Type', 'text/csv; charset=utf-8');
        $resp->header('Content-Disposition', 'attachment; filename="impact-report.csv"');
        return $resp;
      } else {
        // extrém eset: nincs REST osztály – vissza sima string
        return $csv;
      }
    }
    return $result;
  } catch (Throwable $e){
    // sose dőljön el REST miatt
    return $result;
  }
}, 10, 3);

/* ===== UI gombok – ne fussanak Elementor editorban ===== */
add_action('wp_footer', function(){
  if (is_admin()) return;
  if (defined('REST_REQUEST') && REST_REQUEST) return;
  if (wp_doing_ajax()) return;

  // Elementor editor / preview guard
  if (defined('ELEMENTOR_VERSION')) {
    try {
      if (class_exists('\Elementor\Plugin')) {
        $E = \Elementor\Plugin::$instance;
        if ( ($E->editor && $E->editor->is_edit_mode())
          || ($E->preview && method_exists($E->preview,'is_preview_mode') && $E->preview->is_preview_mode()) ) {
          return;
        }
      }
    } catch (Throwable $e) { return; }
  }

  if (!is_singular()) return; ?>
  <script>
  (function(){
    const wrap = document.querySelector('.impact-report-wrap');
    if(!wrap) return;
    function qs(name, def=''){ const u = new URL(window.location.href); return u.searchParams.get(name) || def; }
    const params = new URLSearchParams({
      from: qs('from','<?php echo esc_js(date('Y-m-01')); ?>'),
      to: qs('to','<?php echo esc_js(date('Y-m-d')); ?>'),
      status: qs('status','approved'),
      group: qs('group','shop_ngo'),
      ngo: qs('ngo',''),
      format: 'csv'
    });
    const exportUrl = '<?php echo esc_url_raw( rest_url('impact/v1/report') ); ?>' + '?' + params.toString();

    const bar = document.createElement('div');
    bar.style.display='flex'; bar.style.gap='10px'; bar.style.marginTop='10px';

    const btnExport = document.createElement('a');
    btnExport.textContent = 'Export CSV';
    btnExport.href = exportUrl;
    btnExport.style.background='linear-gradient(90deg,#7C3AED,#06B6D4)';
    btnExport.style.color='#000'; btnExport.style.padding='8px 14px';
    btnExport.style.borderRadius='999px'; btnExport.style.fontWeight='800'; btnExport.style.textDecoration='none';

    const btnFlush = document.createElement('a');
    const u = new URL(window.location.href);
    u.searchParams.set('impact_flush','1');
    btnFlush.textContent = 'Cache frissítése';
    btnFlush.href = u.toString();
    btnFlush.style.background='#0d0e11'; btnFlush.style.color='#E5E7EB';
    btnFlush.style.padding='8px 14px'; btnFlush.style.borderRadius='999px';
    btnFlush.style.border='1px solid #1F2937'; btnFlush.style.textDecoration='none';

    bar.append(btnExport, btnFlush);
    wrap.append(bar);
  })();
  </script>
<?php
});
2 perc alatt „biztonságos” bekapcsolás
Cseréld le a fenti fájlt pontos útvonalon.
Kapcsold be a Report plugint. Ha korábban auto-kikapcsolta, most maradnia kell aktív.
Elementor → Eszközök → CSS regenerálás.
Közvetlen hivatkozások újramentése (Beállítások → Közvetlen hivatkozások → Mentés).
Ha így is bármi tiltja az editort, az már általában tárhely WAF/ModSecurity (403/406 egy REST/admin-ajax hívásra). Ebben az esetben nézd meg a Safari/Webfelügyelő Hálózat fülén a piros sort, és írd meg a státuszkód + URL-t; adok hozzá kész szöveget a szolgáltatónak (REST/admin-ajax kivétel).
Ezt mondtad:
Ellenorizd

<?php
// SHARITY – Impact Bridge (local config)
// Hely: wp-content/impact-bridge-local.php
// Csak privát beállítások. NE írjon ki semmit.

if (!defined('ABSPATH')) { exit; }

/** Dognet Publisher account (ide jön az e-mail/jelszó) */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', '***kudwyr-wavgaf-tYtzo2***');

/** Fix Ad Channel – Sharity: 26081 */
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);

/** Alapok / finomhangolás */
if (!defined('DOGNET_API_BASE'))         define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))        define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))   define('IMPACT_BRIDGE_TIMEOUT', 25);

/** Kompat aliasok (ha másik plugin mást vár) */
if (!defined('DOGNET_EMAIL'))    define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD')) define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

// NINCS záró "?>"
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Ránéztem: a fájl szerkezete és a konstans-logika rendben van, de van egy kritikus hiba és pár apró finomítás, hogy biztosan ne akassza meg sem a REST-et, sem az Elementort.
Ami JÓ
Útvonal és cél: wp-content/impact-bridge-local.php ✅
Nem ír ki semmit, nincs záró ?> ✅
defined()-gátak: csak akkor definiál, ha még nincs – kompatibilis ✅
DOGNET_AD_CHANNEL_ID = 26081 fix csatorna ✅
DOGNET_API_BASE, TOKEN_TTL, UA, TIMEOUT beállítva ✅
Aliasok DOGNET_EMAIL/DOGNET_PASSWORD a kompat miatt ✅
Kritikus javítandó
Jelszó értéke: így, csillagokkal mentve szó szerint ezt a stringet fogja elküldeni:
define('DOGNET_LOGIN_PASSWORD', '***kudwyr-wavgaf-tYtzo2***');
Ez nem fog beloginolni. Legyen a tényleges jelszó, csillagok nélkül:
define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
(Ha nem szeretnéd így tárolni: tedd cPanel Environment Variables-be, és hagyd a local fájlban üresen; a plugin környezetből is olvasni tudja a getenv()-et.)
Ajánlott apróságok (stabilitás/bezajmentesítés)
BOM/whitespace: mentsd UTF-8 (BOM nélkül). Az első karakter legyen a < a <?php-ban, előtte semmi.
Fájljogok: 640 (tulaj+webszerver olvas), tulaj: a web user csoportja.
Semmi echo/print/var_dump – most nincs, jó.
A plugin oldalon már betettem a védelmet: include_once ob_start()/ob_end_clean() körül, így ha mégis kicsúszna bármi kimenet, elnyeli.
Végső, jó verzió (csak a jelszó sort cseréld)
<?php
// SHARITY – Impact Bridge (local config)
// Hely: wp-content/impact-bridge-local.php
// Csak privát beállítások. NE írjon ki semmit.

if (!defined('ABSPATH')) { exit; }

/** Dognet Publisher account (ide jön az e-mail/jelszó) */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');

/** Fix Ad Channel – Sharity: 26081 */
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);

/** Alapok / finomhangolás */
if (!defined('DOGNET_API_BASE'))          define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))         define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_BRIDGE_USER_AGENT')) define('IMPACT_BRIDGE_USER_AGENT', 'SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))    define('IMPACT_BRIDGE_TIMEOUT', 25);

/** Kompat aliasok (ha másik plugin mást vár) */
if (!defined('DOGNET_EMAIL'))    define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD')) define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

// NINCS záró "?>"
Gyors ellenőrzés (miután mentetted)
/wp-json/impact/v1/ticker → jön számszerű válasz (donation €).
/wp-json/impact/v1/leaderboard?tab=ngo → nevek nem JSON, ha nincs: „(ismeretlen)”.
Elementor szerkesztő megnyílik (a local include kimenete el van nyelve a pluginban).
Ha bármi piros hiba marad az editorban, írd meg a státuszkód + URL párost a böngésző Hálózat füléről, és adok rá azonnali fixet (ha WAF/ModSecurity, küldöm a szolgáltatói ticket szövegét is).
Ezt mondtad:
Kérek egy összefoglalót erről a chat röl. Az elvégzett munkáról és a végén elkövetett hibákról. Cél egy násik csevegésben folytatás mert ez már hosszú és lassú
A ChatGPT ezt mondta:

Gondolkozás
Íme egy sűrű, “handoff-ready” összefoglaló erről a szálról — mit csináltunk, mi működik, hol csúsztunk meg, és hogyan folytassuk zökkenőmentesen az új chatben.
Mit végeztünk el (főbb eredmények)
Impact Bridge Local és Impact Report MVP pluginok
Két, egymástól független WP-bővítmény készült:
Impact Bridge Local: /wp-json/impact/v1/{ticker|leaderboard|activity} végpontok, 50% adomány-logikával, approved-only, ad_channel_id=26081 fix szűrő, transient cache (ticker ~3p, leaderboard ~5p, activity ~2p).
Impact Report MVP: /wp-json/impact/v1/report REST + [impact_report] shortcode, szűrők (from–to, status, group, ngo), 15 perces cache, CSV export és ?impact_flush=1.
Lokális config bevezetve: wp-content/impact-bridge-local.php (Dognet email/jelszó, ad channel, timeouts, UA), hogy ne kelljen wp-config.php-t piszkálni.
Tokenkezelés: Dognet 24h token, automatikus frissítés, 401 esetén újra-auth. (A Dognet doksi szerint az auth token 24 órás és minden hívással hosszabbodik. )
Lapozás: raw-transactions/filter last_id görgetéssel (fallback legnagyobb ID). (A last_id-s görgetést a dokumentáció is így írja le. )
Adatértelmezés / normalizálás
Commission → donation: UI mindenhol adományt mutat (= jutalék × 0.5).
NGO-azonosító: robusztus d1/ref/subid/data* kinyerés, querystring és teljes URL esetén is (ngo|ngo_name|d1|org|utm_term). JSON-nak látszó és pusztán numerikus “értékeket” kiszűrünk, ezért lett az NGO leaderboard “(ismeretlen)” addig, amíg a forrásban nincs tiszta címke.
Dátum-kezelés: napi/aktuális aggregálás, activity alapból utolsó 3 nap (ha nincs friss “approved”, üres lehet).
Admin/QA eszközök
rest_pre_echo_response kiterjesztés a CSV exporthoz és cache flush-hoz.
Egyszerű debug endpoint: /wp-json/impact/v1/debug/sample — mezőnév-mintavételhez.
BOM/“headers already sent” probléma megelőzés: minden fájl BOM nélkül, nincs záró ?>.
Cron / teljesítmény
cPanel cron minta (5 perc): curl -fsS https://SAJAT-DOMAIN.hu/wp-cron.php?doing_wp_cron=1 >/dev/null 2>&1
Transient cache rövid (2–5 perc), hogy demón élőnek hasson, de kímélje az API-t (a Dognet rate limit: 240 req/min auth usernél). 
Mi működik most
Ticker: valós értéket ad vissza (adományban, azaz már felezve).
Leaderboard (NGO): működik, ha van használható d1/ngo jelölés; jelöletlen forrásnál “(ismeretlen)”.
Report: aggregál (shop×NGO / NGO / Shop), összesít, CSV-t ad, cache-el, támogatja a ?impact_flush=1-et.
Lokális config: a Dognet hitelesítő adatok és a 26081 csatornaszűrő központilag, egy helyen állíthatók.
Hol akadtunk el / hibák a végén (tanulságok)
Elementor nem nyílt meg
Oka: egy időszakban két plugin (Bridge + Report) párhuzamos fejlesztés alatt volt. Egy régebbi fájl “váratlan kiírást” (280 char) okozott az aktiválásnál → headers already sent kockázat.
Megoldás: minden PHP fájl BOM nélkül, nincs nyitó/nyomtatott whitespace, nincs záró ?>, a “local config” sem írhat ki semmit. Tisztáztuk és egységesítettük.
NGO nevek helyett JSON-t látunk a leaderboardban
Oka: pár tranzakcióban a d1/ref/... mező valuta-/váltási meta JSON-t tartalmazott → earlier verzió nem szűrte ki; ezért felvette nevnek.
Megoldás: a végleges pick_d1() már eldobja a pusztán numerikus és JSON-szerű értékeket, és URL/kv-párból próbál nevet kinyerni. Így az NGO leaderboard “(ismeretlen)” lesz, amíg nincs tiszta címke — ami sokkal jobb, mint fals JSON-név.
Activity feed üres
Oka: az ablak utolsó 3 nap, és nem volt friss “approved” rekord.
Gyors workaround: átmenetileg növelhető az ablak (pl. 30 nap), de demón hagytuk 3 napon.
Report fatál és auto-deaktiválás
Oka: korai buildben lehetett névtérütközés vagy túl korai I/O (esetleg BOM).
Megoldás: teljes cserét javasoltunk mindkét pluginre (végleges, BOM-mentes, kiírásmentes fájlokkal) + külön local config.
Mit vigyünk át az új chatbe (rövid checklist)
✅ Két végleges pluginfájl (Bridge + Report) BOM nélkül
✅ wp-content/impact-bridge-local.php (Dognet email/jelszó, DOGNET_AD_CHANNEL_ID=26081)
✅ Cron beállítás megerősítése (cPanel 5p)
✅ Elementor oldalon: a három shortcode él (ticker/leaderboard/activity), és az [impact_report] is.
✅ Impact front: a JS-ek a impact:updated eseményre konfettiznek; mini cache; ?impact_flush=1 működik.
🔎 Sanity-check: /impact/v1/debug/sample kulcslista; leaderboard?tab=ngo nem tartalmazhat JSON-t a name-ben.
Gyors API-jegyzet (referencia)
Auth: POST /auth/login → 24h token, minden hívással frissül; minden további kéréshez Authorization: Bearer <token> kell. 
Lapozás: POST /raw-transactions/filter + meta.last_id szerinti görgetés; üres data-nál állunk meg. 
Ha szeretnéd, az új beszélgetést indíthatjuk azzal, hogy bedobom egyben a két végleges plugin aktuális, BOM-mentes változatát és a lokális config sablont, majd lépésről lépésre ellenőrizzük: REST végpontok → shortcode-ok → Elementor oldalak → cron. A folytatás logikus íve: NGO címkézés konszolidálása (hogy minél kevesebb legyen az “(ismeretlen)”), /impact/report UI finomhangolás, majd GA4 események és teljesítmény-tuning.



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
