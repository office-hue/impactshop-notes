Átugrás a tartalomra
Ezt mondtad:
Oké 🙌, itt van egy részletes összefoglaló a mostani állapothoz, hogy a következő chatben tiszta lappal, minden shortcode és funkció egyben legyen:

⸻

📊 Sharity ImpactShop – összefoglaló (2025-09-27)

🔌 Architektúra
	•	WordPress + Elementor
	•	MU pluginek (közvetlenül wp-content/mu-plugins mappában):
	•	impactshop-boot.php → Redirect + Dognet link generálás + deeplink tisztítás (Árukereső fix).
	•	impactshop-metrics-ngo.php → Ticker, Leaderboard, Activity API, csak érvényes NGO sluggal.
	•	impactshop-addons-no-unknown.php → Új shortcodo k: „no_unknown” változatok, plusz frontend ticker/leaderboard/activity megjelenítők.
	•	Összevont snippet → ImpactShop UI + Report + Rows nézet (diagnosztika, riport, táblák).
	•	Google Sheets → Shops és Banners táblák (CSV feed).

📥 Adatszabályok
	•	Csak data1 (NGO slug) tranzakciók számítanak.
	•	Csak approved + pending (A+P).
Rejected (D) → mindenhol kizárva.
	•	Adomány = 0.5 × publisher_commission.
	•	Pénznem: €.
	•	Időablakok:
	•	Ticker = aktuális hónap + „ma”.
	•	Leaderboard = aktuális hónap.
	•	Activity = utolsó 14 nap.

⸻

🧩 Elérhető shortcodo k

1. Ticker
	•	[impactshop_ticker]
→ Havi és mai adomány összeg (csak valid NGO, rejected nélkül).

2. Leaderboard
	•	[impactshop_leaderboard tab="ngo" top="10"]
NGO toplista (alapértelmezett).
	•	[impactshop_leaderboard tab="shop" top="5"]
Shop toplista.

3. Activity
	•	[impactshop_activity limit="10"]
Utolsó 14 nap aktivitásai, legújabb elöl.

4. Report (alap)
	•	[impactshop_report from="2025-09-01" to="2025-09-30" status="approved" group="shop_ngo"]
→ Alap riport (NGO/Shop bontás, tartalmazhat „ismeretlen” sorokat).

5. Report (szűrt – no_unknown)
	•	[impactshop_report_no_unknown from="2025-09-01" to="2025-09-30" status="all" group="ngo"]
→ Ugyanaz, de kihagyja az „ismeretlen” sorokat (NGO és shop).

6. Rows (alap)
	•	[impactshop_rows from="2025-09-01" to="2025-09-30" status="all"]
→ Nyers sorok, shop+NGO bontással (lehet benne „ismeretlen”).

7. Rows (szűrt – no_unknown)
	•	[impactshop_rows_no_unknown from="2025-09-01" to="2025-09-30" status="approved"]
→ Ugyanaz, de kihagyja az „ismeretlen” NGO/shop sorokat.

8. Egyéb UI shortcode-ok
	•	[impactshop_scroller category="Tech"]
Folyamatosan gördülő logósor + bannerek.
	•	[impactshop_catalog show_tabs="1" search="1"]
Teljes katalógus tabokkal és keresővel.
	•	[impactshop_diag]
Diagnosztika (ellenőrzi a dognet_base + deeplink_param mezőket).
	•	[impactshop_debug]
Debug infók + minta banner.

⸻

📝 Mit kell észben tartani
	•	Kettős változat van: alap (report, rows) és szűrt (_no_unknown).
	•	Rejected-ek sehol sem számítanak (kizárás kód szinten megoldva).
	•	Deeplink tisztítás → Árukereső hibákhoz külön logika a boot pluginban.
	•	Minden új shortcode külön MU pluginban van, így nem ütközik az összevont snippet vagy más részek kódjaival.

⸻

👉 Innen két út:
	1.	Meghagyjuk így (külön pluginek, külön shortcodo k).
	2.	Átemeljük egy nagy MU pluginba, hogy minden egy helyen legyen (kevesebb fájl, de hosszabb kód).

Szeretnéd, hogy a következő körben összegyúrjam egyetlen stabil MU pluginba, ahol minden shortcode, minden logika benne van?
A ChatGPT ezt mondta:

Gondolkozás
Igen. Egyetlen, stabil MU pluginba gyúrom össze mindent — rövidkódok, redirect, API, riportok, „no_unknown” szűrők, diag. Így kevesebb az ütközés, egyszerűbb a frissítés, és a teljes ImpactShop logika egy helyen él.
Mit kapsz a következő körben (egy darab, egyben bemásolható fájlként):

Teljes MU plugin: wp-content/mu-plugins/impactshop/impactshop.php (önmagában működik).
Visszafelé kompatibilis shortcode-ok: [impactshop_ticker], [impactshop_leaderboard], [impactshop_activity] (a mini alias is támogatott), plusz a riport- és rows-változatok, valamint a _no_unknown párok — mind egy helyen.  
Stabil Dognet API réteg: tokenkezelés (24h, automata refresh), /campaigns/links/generate deeplink/base link, /raw-transactions/filter görgetés last_id-dal — pontosan a Publisher API szerint. 
Riport REST endpoint és shortcode (pénznem 2 tizedes, shop×NGO / NGO / shop nézetek), + „Rows” lista az okos d1-kinyeréssel (nem marad „0.0545” típusú zaj). 
Admin diag oldal opcionálisan (auth, approved kampányok, link generálás) — ha kéred, beépítem külön admin menüpontra. 
Kis UI/CSS csomag a ticker/leaderboard/activity-hez, hogy Elementor nélkül is kulturált legyen az alapmegjelenítés. 
Vázlatos műszaki terv (röviden):
Egyetlen namespace: ImpactShop\*, ütközésmentes függvények.
Rewrite: ^go és ^go-deal marad (flush-olás endpointtal), paraméter-propagálás (amb, src, UTM).
„Árukereső” deeplink tisztítás és base64-u dekódolás megmarad a redirectben. 
Cache policy: riport 15 perc, CSV 15–20 perc; minden TTL egy konstansban.
Hard rule-ok: csak data1-es tranzakciók; státusz: A+P; DOGNET_AD_CHANNEL_ID=26081; adomány = 0.5 × publisher_commission — ez kódszinten rögzítve marad. 
Sheet integráció: Shops + Banners CSV beolvasás változatlanul (UTF-8 BOM, délimiter-detektálás, slug-safe header-ek). 
Tennivalód előtte (gyors csekklista — semmi új feltöltés nem kell):
A mostani MU apró plugineket nem kell törölnöd, csak kikapcsoljuk/beárnyékoljuk őket: az új MU plugin felül fogja definiálni a shortcode-okat és a redirectet.
Ha szeretnéd az admin „Dognet Diagnosztika” menüt is (auth/kampány/link próbák), jelezd, beteszem defaultban. 
Oké, akkor a következő üzenetben hozom az egyetlen MU plugint egyben (kommentált, beilleszthető, <?php–tól az utolsó sorig), a fenti funkciókkal és a mostani beállításaidra optimalizálva. A Dognet végpontok és filterek a hivatalos Publisher API szerint lesznek hívva (auth/login, campaigns/mine/filter, links/generate, raw-transactions/filter). 
Később bővíthetjük minipluginekre bontott moduláris csomaggá (ha külön csapat kezeli a riportot vs. UI-t), de indulásnak a monolit MU a legstabilabb.




A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
