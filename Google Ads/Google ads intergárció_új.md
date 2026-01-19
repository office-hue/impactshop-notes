Szuper ötlet — de csináljuk szabálytisztán, különben az AdSense egy mozdulattal elvágja a bevételt. Az alábbi tervben két működő modellt kapsz, részletes lépésekkel, mérföldkövekkel, és a kötelező Google-szabályokkal.

Röviden a pálya széléről
	•	Nem szabad a felhasználókat arra ösztönözni, hogy „kattints, hogy adományozz”, vagy „nézd meg a hirdetést és támogatod az ügyet”. Ez kifejezetten tiltott: „kihasználó vagy zavart keltő megfogalmazás”, illetve „harmadik fél számára pénzgyűjtés ígérete kattintásért/megtekintésért” = invalid traffic ok miatti büntetés/számlazárás.
	•	EEA/UK/CH forgalomhoz Google-tanúsított CMP (hozzájáruláskezelő) és Consent Mode kell, különben csak korlátozott hirdetések futnak.  ￼

⸻

1) Ajánlott modell: „AdRevenue → Donation” (nem ösztönzünk kattintást)

Ez a legegyszerűbb és legbiztonságosabb: minden AdSense-bevétel X%-át havi rendszerben adományozzuk, nem kötjük felhasználói kattintáshoz/megtekintéshez.

Bevezetési lépések (WordPress + Impact Shop)
	1.	AdSense fiók + Site hitelesítés
	•	Nyiss/aktiválj AdSense-fiókot, add hozzá a domaint, és várd meg az ellenőrzést.
	•	WordPressen a Site Kit by Google bővítéssel csatlakoztasd az AdSense-t (ez injektálja a kódot és segít az engedélyezésben).
	2.	Auto ads bekapcsolása (gyors bevétel, minimális karbantartás)
	•	Kapcsold be az Auto ads-t, és finomhangold a formátumokat (in-page, anchor, vignette). Kezdetnek hagyd az alapbeállításokat, majd fokozatosan állíts a sűrűségen.
	3.	ads.txt felvétel
	•	Helyezd ki a gyökérbe az ads.txt fájlt az AdSense ajánlás szerint (pl. google.com, pub-XXXX, DIRECT, f08c47fec0942fa0). Ez nem kötelező minden esetben, de erősen ajánlott a bevétel-stabilitásért.
	4.	Hozzájáruláskezelés (CMP) + Consent Mode
	•	EEA/UK/CH közönségnél Google-tanúsított CMP kell (IAB TCF-integrációval), ami a Consent Mode v2 jeleket küldi (ad_storage, analytics_storage, stb.). A Google hivatalosan előírja a tanúsított CMP használatát publikálók számára.  ￼
	5.	Mérés és átláthatóság
	•	Kösd össze AdSense ↔ GA4 (AdSense-link GA4-ben), így egy nézetben látod az oldalszintű bevételt.
	•	Hozz létre „Átláthatóság és adomány” aloldalt: „A hirdetési bevételek X%-át havonta a Sharity Impact Alap javára fordítjuk. A hirdetések megtekintése nem kötelező, és nem növeli közvetlenül az adomány összegét.” (Ez a szöveg elkerüli az ösztönzést; lásd tiltások fent.)
	6.	Brand safety / kategória-blokkolás
	•	Blokkold az ügyeitekhez nem illő szenzitív kategóriákat és/vagy konkrét hirdető domaineket az AdSense Brand safety / Blocking controls felületén.  ￼
	7.	Elhelyezési irányelvek betartása
	•	Ne tedd a hirdetéseket félrevezető gombok, űrlapok vagy „kötelező” interakció közelébe; ne használd olyan szövegek mellett, amelyek kattintásra buzdítanak.

Elfogadási kritériumok
	•	CMP banner megjelenik EEA/UK/CH látogatónak; GA4 DebugView mutat Consent Mode jeleket.  ￼
	•	Auto ads fut, ads.txt elérhető, AdSense → GA4 link aktív.
	•	„AdRevenue → Donation” oldal kint, ösztönző megfogalmazás nélkül.

⸻

2) „Rewarded / Jutalmazott megtekintés” — csak ha muszáj, szigorú feltételekkel

Ha szeretnél élményszerű „nézz reklámot és kapsz valamit” mechanikát, azt csak a Rewarded formátumokkal szabad (és nem „adományért nézésként” kommunikálva).

Fontos korlátok:
	•	Reward nem lehet közvetlen pénzügyi juttatás (készpénz, ajándékkártya).
	•	Tilos a választás befolyásolása olyan üzenettel, mint „Nézd meg a hirdetést, és támogatsz minket/az ügyet.” A kreatívban/oldalon nem lehet ilyen ösztönzés.
	•	A klasszikus AdSense program tiltja az ösztönzött kattintást/megtekintést; a kivétel a Rewarded készlet szigorú szabályai között kezelhető.

Biztonságos megfogalmazás
	•	Jutalom legyen nem pénzügyi: pl. „NGO-badge”, extra tartalom feloldása, „köszönő animáció”, leaderboard-szalag.
	•	Az adományt külön, aggregált módon kommunikáld: „A hirdetési bevételek X%-át havonta utaljuk az alapnak.” (Nem kötjük a felhasználó egyedi megtekintéséhez.)

⸻

3) UX/Content minta (magyar szövegek)
	•	CMP első réteg: „A működéshez és méréshez sütiket használunk. Hozzájárulsz a személyre szabott és nem személyre szabott hirdetésekhez?” (Link: Google „Business Data Responsibility” oldal + saját Adatkezelési tájékoztató.)  ￼
	•	Hirdetés melletti megjegyzés (opcionális): „Hirdetés • Megjelenítésük nem kötelező. A Sharity a hirdetési bevételek X%-át havonta adományozza.”
	•	Transzparencia oldal: rövid magyarázat, havi riport-kivonat (Impact riport linkekkel), nincs „kattints és adományozol” üzenet.

⸻

4) Technikai részletek a te stackeden
	•	WordPress + Site Kit: AdSense bekötése, Auto ads kapcsolása, kód injektálás sablonpiszkálás nélkül.
	•	Complianz (vagy más tanúsított CMP): állíts be TCF v2.2 jeleket és Consent Mode v2-t. (A Google kötelezővé teszi a tanúsított CMP-t EEA/UK/CH közönséghez.)  ￼
	•	Brand safety: Kategória-blokkolás és hirdető-URL tiltólista a Sharity értékeihez igazítva.  ￼
	•	GA4 integráció: AdSense → GA4 link az összevont riporthoz.

⸻

5) Ütemezés (low-risk rollout)

Fázis A – Előkészítés (1–2 nap)
	•	AdSense fiók és site-felvétel, Site Kit bekötés.
	•	CMP beállítás (tanúsított), Consent Mode jelek ellenőrzése.  ￼
	•	ads.txt publikálása.

Fázis B – Pilot (1 hét)
	•	Auto ads csak az /impactshop és blogoldalakon.
	•	Brand-safety szabályok, első kategória-szűrések.  ￼
	•	GA4 + AdSense link, heti bevételi/UX ellenőrzés.

Fázis C – Kiterjesztés (2–4 hét)
	•	Manuális egységek azokon a helyeken, ahol az Auto ads nem elég (oldal/poszt sablon HTML-widget).
	•	A/B teszt: sűrűség, formátumok; CLS/vizualis stabilitás figyelése.
	•	Transzparencia oldal havi frissítése, Impact riport linkek.

Opcionális Fázis D – Rewarded kísérlet
	•	Csak ha szükséges, és szigorúan a Rewarded szabályok szerint, nem adomány-ígérettel kommunikálva.

⸻

6) Kockázatok és elhárítás
	•	Számlazárás kockázata: bármely „kattints, hogy adományozz” üzenet kizárás. Tartalom-/UI-review minden kihelyezés előtt.
	•	EU megfelelés: tanúsított CMP és Consent Mode v2 jelek hiánya korlátozott hirdetést/árbevétel-esést okoz. Ellenőrző lista szerint konfigurálni.  ￼
	•	Nem kívánt hirdetők: kategória/URL blokkolás használata.  ￼

⸻

7) Bővítési utak (később)
	•	AdSense for Search (belső kereső monetizálása) — külön egységként, szabályok szerint.  ￼
	•	Google Ad Grants a forgalom és NGO-ismertség növeléséhez (nem monetizáció): nonprofitoknak havi Google Ads keret.  ￼

⸻

8) Konkrét teendőlista (nekünk)
	1.	Site Kit-ben AdSense összekapcsolás (prod + staging).
	2.	ads.txt kitétel a gyökérben.
	3.	Tanúsított CMP beállítás (TCF + Consent Mode v2), teszt: EEA IP → banner, GA4/Tag Assistant → consent jelek.  ￼
	4.	Auto ads bekapcs, sűrűség: „konzervatív” indulás, majd heti finomhangolás.
	5.	Brand safety: szenzitív kategóriák tiltása, hirdető-URL blacklist első köre.  ￼
	6.	GA4↔AdSense összelinkelés; transzparencia oldal publikálása.
	7.	Heti mini-riport: megjelenések, RPM, bevétel → „AdRevenue → Donation” táblába.

⸻

Ha szeretnéd, paraméterezek egy szabálytalan-szöveg ellenőrző checklistet is az ImpactShop szerkesztőinek (mi kerülhet/mi nem kerülhet a hirdetéskörnyezetbe), hogy mindenki „policy-proof” legyen a szerkesztés során.

Szuper — menjünk a legegyszerűbb, „nem lehet elrontani” verzióval.

Egyszerű modell: Központi Adományalap + kevés, okosan elhelyezett AdSense
	•	Mit csinálunk? 1–2 Auto ads egység az Impact Shop oldalain (Site Kit beállítja), a havi AdSense-bevételt pedig betesszük egy Központi Adományalapba. Onnan osztunk szét az NGO kártyákról érkező forgalom aránya szerint.
	•	Miért elég ez? Az AdSense ma már megjelenés (impresszió) alapú kifizetéssel működik, nem feltétel a kattintás – a felhasználónak elég, hogy oldalbetöltéskor látja a hirdetést.  ￼

⸻

1) Bevezetés 6 gyors lépésben (WordPress + Impact Shop)
	1.	AdSense bekötés Site Kit-tel (WP): a plugin végigvezeti a csatlakozást, és automatikusan beteszi a kódot.  ￼
	2.	Auto ads bekapcsolása (kezdjünk minimállal; később finomhangoljuk a sűrűséget/formátumokat).  ￼
	3.	ads.txt kihelyezése a domain gyökerébe (javítja a hirdetéskeresletet).  ￼
	4.	EEA/UK/CH megfelelés: Google-tanúsított CMP + Consent Mode v2 (különben csak korlátozott kiszolgálás).  ￼
	5.	Brand safety: tiltsd a nem kívánt kategóriákat/hirdető domaineket az AdSense „Blocking controls”-ban. (Best practice a reputáció védelmére.)
	6.	Mérés: GA4-ben hozz létre egyedi dimenziót (pl. ngo_code, embed_host), és naponta írd felül UTM-ből/paramból; a hónap végén egy táblázatban exportálsz Sessions (vagy Pageviews) NGO-kód szerint.

⸻

2) Elosztási képlet (átlátható és száraz, mint a matek)
	•	Havi adományozandó összeg = AdSense havi bevétel × donation_share (pl. 80%).
	•	NGO részesedés(ngó) = Havi adományozandó összeg × (NGO-hoz köthető látogatások / Összes NGO-hoz köthető látogatás).
	•	Forrás: GA4 riport „Sessions by ngo_code” (szűrés: source=impactshop), kerekítés szabállyal + minimumküszöbbel a nagyon kicsi forgalmakra.

Tipp: ha akarsz minőségi súlyt is, használhatsz Engaged Sessions vagy Avg. engagement time szorzót, de első körben a „csak sessions” osztás a legtisztább.

⸻

3) Elhelyezés – kicsi zaj, tiszta UX
	•	Indulj 2 felülettel: 1 in-page (tartalom előtt), 1 in-article (tartalomban).
	•	Kerüld a félreértést és a véletlen kattintást: ne rakd menük, letöltés-gombok vagy fontos CTA-k közvetlen közelébe; ne kérj kattintást/adnézést támogatásért (tilos).  ￼

⸻

4) Kötelező policy-checklist (ultrarövid)
	•	NINCS ösztönzés: nem írjuk ki, hogy „kattints és adományozol” / „nézd meg a reklámot a támogatáshoz”.  ￼
	•	Megfelelő elhelyezés: ne legyen megtévesztő, ne növeld szándékosan a véletlen kattintásokat.  ￼
	•	CMP + Consent Mode v2 aktív EEA/UK/CH forgalomhoz.  ￼
	•	ads.txt kint, naprakész.  ￼

⸻

5) Kommunikáció (mintaszöveg, ami nem ütközik szabályba)
	•	Rövid megjegyzés az oldalon:
„Hirdetések jelennek meg. A bevétel meghatározott részét havi rendszerességgel a Sharity Központi Adományalapjába tesszük, és az NGO-kártyákról érkező forgalom arányában osztjuk szét.”
(Nem ígér kattintásért/megtekintésért ellenszolgáltatást → megfelel az AdSense irányelveknek.  ￼)

⸻

6) Mini ütemterv
	•	Nap 1: Site Kit ↔ AdSense összekötés, Auto ads ON, ads.txt kitétel.  ￼
	•	Nap 1–2: CMP bekapcsolás (Google-tanúsított), Consent Mode v2 ellenőrzés (Tag Assistant).  ￼
	•	Hét 1: Pilot csak az /impactshop (+ kapcsolódó cikkek) oldalakon; GA4 egyedi dimenziók bekötése.
	•	Hónap vége: GA4 export ngo_code szerint → elosztás a képlet alapján → transzparens összefoglaló poszt.

⸻

7) Gyors háttérmagyarázat (miért működik ez)
	•	A modell nem kér kattintást, így nem sérti a „no-incentive/no-misleading placement” szabályokat.  ￼
	•	A bevétel impresszióhoz kötött, ezért elegendő a tartalmi forgalom és a látható pozíció — nincs szükség semmilyen „trükkre”.  ￼
	•	Az EEA/UK/CH CMP-követelmény teljesítésével stabil a hirdetéskiszolgálás és a mérés.  ￼

Ha kéred, adok egy 1 oldalas „AdSense x Impact Shop – beállítási checklistát” (PDF), és egy GA4-hez illesztett „NGO-elosztás” táblamintát (CSV-fejlécekkel), hogy az első hónap végén egy kattintással le tudd vezetni az elosztást.

Megjegyzések (policy & kötelezők — a miért mögötte)
	•	Ösztönzés tilos (nincs „kattints/nézd meg és adományozol”) — invalid traffic és számlazárás kockázat.  ￼
	•	Tanúsított CMP + Consent Mode v2 kell az EEA/UK/CH forgalomhoz, különben a kiszolgálás korlátozott.  ￼
	•	Site Kit a legegyszerűbb mód az AdSense bekötésére WordPressen; kezdetnek Auto ads (minimál sűrűség).  ￼
	•	ads.txt erősen ajánlott (autorizált eladók, jobb kereslet), és legyen crawl-olható a gyökérben.  ￼
	•	Brand safety / Blocking controls: érzékeny kategóriák és hirdető-URL blokkolása az értékeinkhez igazítva.  ￼
	•	GA4↔AdSense link: oldalszintű „Publisher ads” riporthoz és az NGO-arányok auditálásához.  ￼
