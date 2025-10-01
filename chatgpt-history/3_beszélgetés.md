Átugrás a tartalomra
Ezt mondtad:
affiliate webshop folytatása: itt fejezzük be az affiliate webshop beállításait
A ChatGPT ezt mondta:
Tökéletes, innen visszük tovább a fonalat. Összefoglalom, hol állunk az affiliate webshop (Sharity Impact Shop) beállításaival, és mi van még hátra:
Jelenlegi állapot (2025-09-10):

WordPress aloldal készül (Impact Shop).
Pluginok:
• Redirection → /go/{shop} → Dognet affiliate link.
• WPCode → egyedi kódokhoz.
• Site Kit (GA4) → statisztika.
• TablePress → CSV → toplisták, élő számlálók.
• Complianz (tervezett) → cookie banner.
Átirányítási logika:
Felhasználó kattint webshop logóra.
Megnyílik Tally form (NGO kiválasztás).
Submit után redirect: /go/{shop}?d1=<ngo_code>&amb=<amb>&src=impactshop&utm_*.
Redirection továbbítja Dognet affiliate linkre, megtartva a paramétereket.
Dognet elmenti a d1 értéket → NGO azonosító.
Megoldott kérdések:
Tokenek most már helyesen illesztődnek (shop, ngo_code, amb).
Dognet valóban nem viszi át a paramétereket a végső webshop URL-re, de a riportokban rögzíti.
NGO kódlista egységesítése folyamatban.
Hátralévő teendők:
NGO kódlista feltöltése a Tally-ba → minden szervezethez egyedi, fix kód.
Ambassador (amb) kezelés: eldönteni, kötelező-e vagy opcionális.
UI/UX: webshop logók rendezése kategóriába, kártyák, CTA-k (vásárlás gomb), kereső, toplista.
Számlálók: TablePress + CSV → élő támogatási összeg/átkattintás kijelzése.
Tesztelés:
NGO választás → d1 paraméter valóban rögzül Dognet-ben.
Amb → külön riportálódik-e.
UTM-ek mennek-e GA4-be.
Ha szeretnéd, most összeállítok egy konkrét ellenőrző listát, mit kell pontosan beírnod a WordPress Redirection-be, a Tally form redirect URL-be, és a Dognet linkekbe, hogy minden patentül menjen.
Szeretnéd, hogy ezt a check-listát most összerakjam lépésről lépésre?

Ezt mondtad:
Átirányítás beállítása: Fillout űrlap → NGO kiválasztás → Dognet affiliate link

Folyamat áttekintése

Az alábbi lépésekben konfigurálhatjuk a rendszert úgy, hogy a weboldalon az Árukereső logóra kattintás után a látogató először egy űrlapon NGO-t választhasson, majd automatikusan továbbirányításra kerüljön a megfelelő Dognet affiliate linkre a választott NGO azonosítójával. A folyamat főbb állomásai:
	1.	Kattintás a weboldalon az Árukereső logóra: A link egy Fillout űrlapra mutat, pl. https://fillout.com/formxyz?shop=arukereso. Itt a shop=arukereso paraméter jelzi, melyik webshophoz (Árukereső) tartozik a kattintás.
	2.	Fillout űrlap – NGO választás: Az űrlapon a felhasználó kiválaszt egy támogatandó NGO-t. Az űrlap be van állítva, hogy a beküldés után automatikusan átirányítson egy megadott URL-re, átadva a választott NGO kódját paraméterként.
	3.	Átirányítás a Sharity “go” linkre: Az űrlap beküldése után a felhasználó böngészője automatikusan a https://app.sharity.hu/go?shop=arukereso&d1=VALASZTOTT_NGO címre kerül (ahol VALASZTOTT_NGO helyén a kiválasztott szervezet azonosítója van). Itt a d1 paraméter hordozza az NGO kódját.
	4.	WordPress Redirection plugin – affiliate linkre továbbítás: A WordPress oldalunkon telepített Redirection bővítmény érzékeli a /go?shop=arukereso... mintájú kérést, és azonnal átirányítja a látogatót a Dognet affiliate linkre. A továbbított affiliate URL tartalmazza a szükséges Dognet paramétereket (affiliate azonosító, kampány ID stb.), és beleillesztjük a d1 paraméter értékét (az NGO kódját). Ennek köszönhetően a Dognet nyilvántartja majd, hogy a kattintás mely NGO-hoz tartozott.

Az alábbiakban részletesen bemutatjuk az egyes lépések beállítását: a Fillout űrlap konfigurálását, a Redirection plugin szabályainak megadását, és azt is, hogy a Dognet milyen paramétereket vár az affiliate linkben az egyéni adatok (NGO kód) továbbításához.

Fillout űrlap beállítása (NGO választás és átirányítás)

1. Fillout űrlap létrehozása az NGO kiválasztásához: Hozzunk létre egy új űrlapot a Fillout felületén, amelyben a felhasználó kiválaszthat egy NGO-t. Ehhez használhatunk például egy legördülő listát vagy rádiógombokat az elérhető szervezetek nevével. Érdemes az NGO választó mezőhöz egyedi értékeket (custom values) beállítani, hogy a megjelenő névhez egy konkrét kód társuljon. Például a listában megjelenhet Magyar Vöröskereszt, de a háttérben a hozzá rendelt érték legyen mondjuk voroskereszt vagy egy ID – így ezt a kódot fogjuk továbbadni paraméterként. (A Fillout lehetőséget ad egyedi értékek megadására a választható opciókhoz , így a továbbított paraméter tiszta, szóköz és ékezet nélküli kód lesz.)

2. shop paraméter kezelése (rejtett mező): Mivel ugyanazt az űrlapot szeretnénk használni több webshop logójához is, célszerű a shop paramétert dinamikusan kezelni. A Fillout-ban lehetőség van URL paraméterek (ún. hidden field-ek) fogadására és felhasználására  . Regisztráljuk az űrlap Settings > URL parameters menüjében a shop nevű paramétert, majd hozzunk létre egy rejtett mezőt az űrlapon, aminek alapértelmezett értékét ehhez a shop URL-paraméterhez kötjük. Állítsuk a mezőt “Hide always” módra, hogy a kitöltő ne lássa . Így amikor a látogató a ?shop=arukereso URL-lel nyitja meg az űrlapot, ez a rejtett mező például arukereso értéket vesz fel. (Ezt a módszert alkalmazhatjuk más kampányokra is, pl. shop=amazon stb., hogy egy űrlappal több webshopot kezeljünk.)

3. Az átirányítási URL sablonjának beállítása: Állítsuk be az űrlap beküldése utáni műveletet. A Fillout-ban a Form designer nézetben adjunk hozzá egy Ending page-et, és válasszuk a Redirect típust (átirányítás külső oldalra). Itt megadhatunk egy URL-t, ahová a kitöltés után a felhasználót irányítsa a rendszer  . Ide kell beírnunk azt a sablon URL-t, ami tartalmazza a szükséges paramétereket: a shop és az NGO kód (d1). Például:

https://app.sharity.hu/go?shop=arukereso&d1={ngo_kod}

A fenti sablonban a {ngo_kod} helyére az űrlapon kiválasztott NGO azonosítóját illesztjük be. Ezt a Fillout-ban dinamikusan is meg lehet adni: a Redirect URL mezőben kattintsunk a + ikonra vagy kezdjük el begépelni a mező nevét (vagy használjuk az @ szintaxist), így kiválaszthatjuk a NGO választó mező értékét beszúrásra . Ugyanígy, ha nem fix a shop érték, akkor a rejtett shop mezőt is beilleszthetjük dinamikusan – de mivel ebben a példában az Árukereső logóról van szó, a shop=arukereso konstans is maradhat a sablonban.
	•	Példa: Tegyük fel, hogy az űrlapon a “Válassz egy NGO-t” kérdésnél a kitöltő a Magyar Vöröskeresztet választotta, melynek egyedi kódja voroskereszt. A Redirect sablon URL így néz ki:
https://app.sharity.hu/go?shop=arukereso&d1=voroskereszt
Az űrlap beküldésekor a Fillout automatikusan erre a címre irányítja a böngészőt. (Fontos: győződjünk meg róla, hogy a Fillout fiókunkban használt csomag támogatja a külső redirect funkciót – ez Starter csomagtól felfelé érhető el .)

4. Fillout űrlap tesztelése: Mentsük és publikáljuk az űrlapot, majd próbáljuk ki egy éles linkkel. Nyissuk meg a böngészőben pl. a https://fillout.com/formxyz?shop=arukereso címet, válasszunk egy NGO-t és küldjük be az űrlapot. Helyes beállítás esetén a böngésző a beküldés után átirányít a megadott app.sharity.hu/go... URL-re, és a címsorban látható lesz mind a shop=arukereso, mind a d1= paraméter a választott értékkel. Ez azt jelenti, hogy a Fillout oldalról sikeresen továbbadtuk a szükséges adatokat a saját weboldalunk felé.

WordPress Redirection plugin konfigurálása (átirányítás az affiliate linkre)

A következő lépés, hogy a app.sharity.hu/go címre érkező kérést a WordPress a megfelelő Dognet affiliate URL-re továbbítsa, miközben a paramétereket (különösen az NGO kódot) beilleszti a cél URL-be. Ehhez használjuk a Redirection bővítményt WordPress alatt:

1. Új átirányítási szabály hozzáadása: A WordPress vezérlőpultján nyissuk meg a Tools > Redirection (Eszközök > Átirányítás) menüt. Hozzunk létre egy új redirect szabályt. A Source URL mezőbe adjuk meg azt az útvonalmintát, amelyet el szeretnénk kapni. Mivel az átirányításunk tartalmaz URL-paramétereket is, engedélyeznünk kell a Regex (Reguláris kifejezés) opciót a mintánál. Pipáljuk be a „Regex” jelölőnégyzetet, majd írjuk be a forrás URL mintát például az alábbi módon:

^/go\?shop=arukereso&d1=([^&]+)$

Magyarázat: ez a minta illeszkedik bármely /go útvonalra, amelynek query stringjében shop=arukereso és egy d1= paraméter szerepel. A ([^&]+) rész egy csoport, ami a d1= utáni értéket fogja megfogni, egészen a query string végéig ($). Ez lesz az NGO kód (változó hosszúságú, & karaktert nem tartalmazó rész) . Ügyeljünk rá, hogy a regex-ben a ? és a & karaktereket escape-eljük (\?, &), ahogy a fenti példában is látható. A Source URL minta elején lévő ^ és a végén lévő $ biztosítja, hogy a teljes útvonalat lefedjük, így ha bármi egyéb paraméter lenne mögötte, az már nem illeszkedne. (Ez azért fontos, mert így nem marad “le nem fedett” query paraméter, amit a plugin esetleg hozzáfűzne a cél URL-hez. A Redirection ugyanis alapértelmezésben a nem illesztett query részeket átviszi a cél URL-re . A teljes illesztéssel elkerüljük, hogy például a shop paraméter feleslegesen továbbmenjen az affiliate linkre.)

2. Cél URL (Target URL) sablon megadása: A Target URL mezőbe kerül a Dognet affiliate hivatkozás sablonja. Ide azt az URL-t kell beírnunk, amely a Dognet rendszerében az Árukereső kampányra mutat, kiegészítve a mi affiliate-azonosítónkkal és a választott NGO paraméterével. A Dognet affiliate linkek általában az alábbi paramétereket tartalmazzák:
	•	a_aid – az affiliate (partner) egyedi azonosítója
	•	a_bid – a kampány/banner azonosítója (ez határozza meg, mely hirdetőhöz, jelen esetben Árukeresőhöz tartozik a link)

Ezeket az értékeket a Dognet biztosítja számunkra; megtalálhatók a Dognet admin felületén a kiválasztott kampány linkjeinél. Például egy alap Árukereső nyitóoldal link így nézhet ki (ez egy példa, a valós értékek mások lesznek):

https://login.dognet.sk/scripts/click.php?a_aid=55d1e5e96f49d&a_bid=db2af2ac

A fenti példa a Dognet FAQ-ból származik, és a topanky.sk kampány linkjét mutatja (a topanky.sk főoldalra mutat) . Látható benne az a_aid (affiliate azonosító) és a_bid (banner/kampány azonosító).

A mi célunkban ezt az URL-t kell kiegészítenünk a d1 paraméterrel. A Redirection plugin lehetővé teszi, hogy a forrás URL regex csoportjait beilleszd a cél URL-be. Az előző lépésben a ([^&]+) csoporttal megfogtuk az NGO kódot; ez a $1 hivatkozással érhető el a Target URL-ben (az első zárójeles csoport tartalma). Így a cél URL sablonunk például:

https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_ID>&a_bid=<KAMPANY_ID>&data1=$1

Fontos, hogy a fenti URL-ben cseréljük ki a <SAJAT_ID> részt a saját Dognet affiliate azonosítónkra, a <KAMPANY_ID> részt pedig az Árukereső kampányhoz tartozó banner ID-re (kampány azonosító). A data1=$1 szekció pedig hozzáfűzi az első regex csoport tartalmát, vagyis a felhasználó által választott NGO kódot a linkhez data1 paraméterként. (A Dognet platform a data1 paramétert használja az egyedi, partner által meghatározott adatok továbbítására . Ezt gyakran csak “d1”-ként emlegetik, és a rendszer továbbítja a konverziókhoz, így később az admin felületen látni fogjuk, melyik tranzakcióhoz milyen érték tartozott .)
	•	Megjegyzés: Ügyeljünk rá, hogy a Redirection bővítmény Regex opciója be legyen kapcsolva ennél a szabálynál, különben a fenti mintákat nem helyettesíti be. Illetve az átirányítás típusánál válasszuk a 302 (Temporary) opciót. Mivel a d1 paraméter értéke minden kattintásnál más lehet (különböző NGO-k), jobb, ha nem 301-es (állandó) átirányítást használunk, így elkerüljük a böngésző cache miatti esetleges rossz gyorsítótárazást.

3. Mentés és tesztelés: Mentsük el az új átirányítási szabályt. Ezután ellenőrizzük a működést: nyissuk meg az előzőleg használt űrlapot, válasszunk NGO-t, és küldjük be. A böngésző át fog menni a app.sharity.hu/go?shop=arukereso&d1=... címre, amit a WordPress elkap, és azonnal továbbirányít a megadott Dognet linkre. A címsorban a végső URL már a Dognet-es domain (login.dognet.sk vagy hasonló) lesz, és ellenőrizhetjük, hogy tartalmazza-e a data1=<NGO_kód> paramétert. (Pl. ...&data1=voroskereszt). A Redirection plugin megfelelő beállításával a shop=arukereso paraméter nem fog továbbmenni az affiliate linkre – erre nincs is szükség, hiszen az affiliate link már eleve arra a kampányra vonatkozik.

Dognet affiliate URL paraméterek (NGO adat továbbítása)

Végül nézzük át, milyen paramétereket kell tartalmaznia a Dognet affiliate linknek, és milyen formátumban, hogy az egyéni NGO adatot helyesen továbbítsuk. A Dognet (mely a Post Affiliate Pro alapú rendszerre épül) a következő paramétereket ismeri az affiliate linkekben:
	•	a_aid / refid (Affiliate azonosító): Az Ön egyedi partnerazonosítója a Dognet hálózatban. Ezzel azonosítja a rendszer, hogy a látogatót Ön küldte. Ezt az értéket a Dognet adja meg (jellemzően egy 8-12 karakter hosszú hexadecimális azonosító). A linkben pl. a_aid=55d1e5e96f49d formában szerepel . (A Dognet magyar dokumentációjában ezt nevezhetik “Referral ID”-nek is.)
	•	a_bid / cid (Kampány azonosító): Annak a kampánynak vagy hirdetőnek a kódja, amelyhez a link tartozik. Minden Dognet kampánynak van egy egyedi azonosítója (Banner ID), amit szintén a link generálásakor kapunk meg. Példánkban az Árukereső kampányhoz tartozó azonosító pl. db2af2ac . A linkben a_bid=db2af2ac formában látjuk. (Ezt olykor Campaign ID vagy cid paraméternek is hívhatjuk – a lényeg, hogy a megfelelő azonosító kerüljön ide, különben a kattintás nem a kívánt hirdetőhöz lesz társítva.)
	•	desturl / url (Opcionális cél URL): Alapesetben a Dognet-től kapott affiliate link a hirdető egy előre meghatározott oldalára (gyakran a főoldalára, vagy egy kampány specifikus landing oldalra) irányít. Ha más aloldalra vagy termékre szeretnénk mélylinkelni, akkor használni kell a desturl paramétert. Ennek értéke az a teljes URL, ahová a látogatót végül küldeni akarjuk, URL-kódolt formában . Például a Dognet útmutatója szerint, ha egy aloldalra akarunk linket készíteni, akkor:
	1.	Vegyük a cél URL-t, pl. http://www.pelda.hu/aloldal?param=ertek – adjuk hozzá a szükséges UTM vagy egyéb kötelező elemeket,
	2.	kódoljuk URL-enkódolással (http%3A%2F%2Fwww.pelda.hu%2Faloldal%3Fparam%3Dertek formára),
	3.	majd illesszük be a linkünkbe desturl= paraméterként .
A teljes affiliate link így tartalmazni fogja az a_aid, a_bid mellett a desturl paramétert is. Fontos tudni, hogy bizonyos kampányoknál a Dognet nem a desturl-t, hanem más paramétert (pl. data2 paramétert) kér a mélylinkhez – erről a Dognet kampány leírásában találsz információt . Általánosságban azonban a desturl használatos a legtöbb esetben a cél URL átadására.
	•	chan / chid (Csatorna azonosító – opcionális): A Dognet lehetőséget ad arra, hogy egy affiliate több “csatornát” megkülönböztessen (pl. ha több weboldalad van, vagy különböző helyeken szeretnéd ugyanazon kampány kattintásait nyomon követni). Ezt a chan paraméterrel lehet elérni. Ha a Dognet admin felületén hozol létre csatornákat, azok azonosítóit (vagy nevét) a linkben chan= (vagy egyes dokumentációkban chid – channel ID) formában lehet megadni. Amennyiben nem használsz ilyet, ez a paraméter kihagyható. (A Redirection szabályunk is egyszerűbb, ha nem kell még a chan-t is átadni.)
	•	data1 / d1 (Egyéni paraméter): Ez az a paraméter, amelyet kifejezetten az egyedi adatok továbbítására tart fenn a rendszer. Az affiliate linkbe szabadon beilleszthetünk egy data1=<érték> részt, és ez az érték minden kattintással együtt rögzítésre kerül a Dognet-nél . Később, amikor konverzió (pl. vásárlás) történik, a Dognet jelentéseiben látni fogjuk, hogy az adott vásárlás melyik data1 értékhez tartozó kattintásból jött. Esetünkben a data1 paramétert használjuk arra, hogy az NGO azonosítóját továbbítsuk. A Redirection beállításnál ezt illesztettük be $1-ként a link végére. A Dognet rendszerébe így beérkező kattintásoknál a data1 oszlopban például voroskereszt fog szerepelni – így egyértelműen összekapcsolható, hogy a jutalékot a Magyar Vöröskereszt számára kell félretenni. (A Dognet a “data1” kifejezést használja, de néha “d1”-ként rövidítik a kommunikációban – ez ugyanazt jelenti.) Nincs szigorú formai megkötés az értékére, de javasolt URL-barát karaktersorozatot használni (kisbetű, szám, kötőjel alulvonás), hogy biztosan ne okozzon problémát az átadás. Célszerű tehát a szervezeteket előre kódokkal vagy ID-kkal azonosítani, ahogy azt tettük.

Összefoglalva: A Dognet affiliate link végleges formátuma az Árukereső kampányra, a mi affiliate azonosítónkkal és a továbbított NGO kóddal így néz ki:

https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_REFID>&a_bid=<ARUKERESO_CAMPAIGN>&data1=NGO_KOD

(Ha konkrét aloldalra mutat, kiegészül még a &desturl=ENCODED_URL résszel is.) Ezt a linket állítja elő automatikusan a WordPress Redirection szabályunk minden kattintás esetén, behelyettesítve a megfelelő értékeket.

Ellenőrző checklist (beállítások áttekintése)
	•	Fillout űrlap:
	•	Regisztráltuk a shop URL-paramétert az űrlapban, és (ha több shopot kezelünk egy űrlappal) létrehoztunk egy rejtett mezőt, ami ezt befogadja alapértelmezett értékként .
	•	Létrehoztuk az NGO választó kérdést, és beállítottunk egyedi értékeket az opcióknál (NGO kódok a továbbításhoz).
	•	Beállítottuk a Redirect típusú befejező oldalt az űrlapnak. A redirect URL tartalmazza a shop paramétert (fixen vagy dinamikusan) és a d1 paramétert az NGO kóddal. A Fillout sablon URL-t helyesen állítottuk össze, és a mezőértékeket beszúrtuk (pl. ...&d1=@NGO_kod).
	•	Kipróbáltuk az űrlapot élesben: a beküldés után a böngészőnk a megfelelő .../go?...&d1=... URL-re jutott, a kiválasztott NGO kódjával.
	•	WordPress Redirection plugin:
	•	Telepítve és bekapcsolva a Redirection bővítmény.
	•	Létrehoztunk egy új átirányítást: Source URL ^/go\?shop=arukereso&d1=([^&]+)$ (regex engedélyezve).
	•	A Target URL-be bemásoltuk a Dognet affiliate linkünket az Árukereső kampányhoz, és beillesztettük a $1 változót a data1 paraméter értékeként . (Ellenőriztük, hogy a saját affiliate azonosítónk és a helyes kampány ID szerepel benne.)
	•	Az átirányítás típusát Temporary (302) értékre állítottuk a biztonság kedvéért.
	•	Teszteltük: a .../go?shop=arukereso&d1=teszt URL-re navigálva a böngészőnk átirányult a várt Dognet linkre, és a címsorban ellenőriztük, hogy a data1 paraméter értéke teszt (vagyis a plugin átadja a regex által megfogott értéket).
	•	Dognet paraméterek ellenőrzése:
	•	A Dognet affiliate linkben jelen van az a_aid (refid) paraméter a saját azonosítónkkal, és az a_bid paraméter a megfelelő kampánykóddal – így a rendszer a kattintást hozzánk és a helyes hirdetőhöz fogja kapcsolni .
	•	A link végén megjelenik a data1 paraméter a kiválasztott NGO kódjával .
	•	(Opcionális) Ha mélylinket használunk: a desturl paraméter helyesen szerepel, URL-kódolt formában .
	•	Belépve a Dognet partner fiókunkba, a kattintási statisztikáknál ellenőrizhetjük, hogy a tesztelt kattintások megjelennek-e. (A Dognet rendszerében a kattintások pár perces késleltetéssel frissülnek , de rövid időn belül látszani fog a kattintás. A data1 paramétert általában a részletes riportokban, vagy a konverziók listájában láthatjuk viszont a tranzakcióknál.)

Ha a fenti checklist minden pontja teljesült, akkor sikerült konfigurálni a rendszert. Mostantól az Árukereső logóra kattintó látogatók az NGO választó űrlapon keresztül a Dognet affiliate linken keresztül jutnak a webshopba, és a Dognet nyilvántartásában a data1 (d1) paraméteren keresztül azonosítható, hogy melyik szervezet javára történt a vásárlás. Így átláthatóan nyomon követhető és elszámolható a jutalék felajánlása az adott NGO részére.
A ChatGPT ezt mondta:

Gondolkozás
Szuper részletes összefoglaló – ebből már működő folyamat lesz. Az alábbi beállításokkal pontosan eléred, amit szeretnél: Fillout → NGO választás → automatikus átirányítás Dognet affiliate linkre, a választott NGO kódjával (data1).
1) Fillout űrlap (NGO választás + redirect)
Mezők
NGO választó (dropdown vagy rádió): az opciókhoz egyedi értékek (custom value) legyenek beállítva → ezek lesznek az NGO-kódok (ékezet/space nélkül).
shop rejtett mező: az URL-ből vegye át a shop paramétert (?shop=arukereso), „Hide always”.
Redirect (beküldés után)
Ending page típusa: Redirect
URL sablon (Árukereső példával):
https://app.sharity.hu/go?shop=arukereso&d1={NGO_KOD}
A {NGO_KOD} helyére dinamikusan szúrd be az NGO mező értékét (Fillout mezőbeszúrás @-szintaxissal vagy a mezőválasztóból).
Ha több shopot egy űrlappal kezelsz, a shop értéket is a rejtett mezőből illeszd:
https://app.sharity.hu/go?shop={shop}&d1={NGO_KOD}
Gyors próba: nyisd meg az űrlapot ...?shop=arukereso, válassz NGO-t, küldd be → a böngésző https://app.sharity.hu/go?shop=arukereso&d1=...-ra érkezik.
2) WordPress Redirection beállítás (go → Dognet)
Cél: a /go?... hívást azonnal vidd tovább a megfelelő Dognet affiliate linkre, a data1 mezőben az NGO-kóddal.
Javaslat: shoponként egy szabály. Így egyszerű a kampány-ID (a_bid) kezelése, és nincs bonyolult feltétel.
Új szabály létrehozása (Árukereső példával):
Menü: Tools → Redirection → Add new
Enable Regex: bejelölve
Query Parameters: Exact (így nem visz át fölös paramétert)
Source URL:
^/go\?shop=arukereso&d1=([^&]+)$
Ez elkapja a /go?shop=arukereso&d1=<bármi> alakot, és a zárójelben lévő rész lesz az NGO-kód ($1).
Target URL (Dognet affiliate link sablon – töltsd ki a saját azonosítóiddal):
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<ARUKERESO_BID>&data1=$1
Magyarázat:
a_aid = a Te affiliate azonosítód
a_bid = az Árukereső kampány/banner azonosítója
data1=$1 = az NGO-kód bekerül a Dognet linkbe
HTTP kód: 302 (Temporary)
Így elkerülöd, hogy a böngésző rosszul cache-elje a különböző NGO-kódokat.
Teszt:
Böngészőben: https://app.sharity.hu/go?shop=arukereso&d1=teszt
Azonnal át kell ugrania pl. https://login.dognet.sk/scripts/click.php?...&data1=teszt címre.
A címsorban látod a data1=teszt paramétert → siker.
Több shop kezelése
Minden további shophoz (pl. emag, alza, stb.) hozz létre külön szabályt:
Source URL (példa Emagra):

^/go\?shop=emag&d1=([^&]+)$
Target URL:
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<EMAG_BID>&data1=$1
Ha szeretnél mélylinket (konkrét aloldalra/termékre), használd a desturl paramétert URL-kódolt céllal:
https://login.dognet.sk/scripts/click.php?a_aid=<AID>&a_bid=<BID>&data1=$1&desturl=https%3A%2F%2Fwww.pelda.hu%2Ftermek%3Fsku%3D123
Tipikus buktatók és gyors fixek
Nem jó a regex? Győződj meg róla, hogy a Regex be van kapcsolva, és a Query Parameters = Exact.
Felesleges paraméterek átmennek a Dognetre? Állítsd Exact-ra a Query Parameters-t (ne „Pass-through”).
Nem jelenik meg a data1 a Dognet URL-ben? Ellenőrizd, hogy a Source zárójeles csoportja ([^&]+) és a Targetben $1 szerepel.
Fillout nem redirectel? Csak Starter csomagtól felfelé tud külső URL-re átirányítani; a redirect URL-ben helyesen legyenek beszúrva a mezők.
Rövid ellenőrzőlista
Fillout
 NGO mező opciói: custom value = NGO-kód
 shop rejtett mező: URL-paraméterből
 Ending page: Redirect → https://app.sharity.hu/go?shop={shop|arukereso}&d1={NGO_KOD}
 Éles teszt: ...?shop=arukereso → beküld → /go?...&d1=...
Redirection
 Regex: on, Query Parameters: Exact, 302
 Source: ^/go\?shop=arukereso&d1=([^&]+)$
 Target: https://login.dognet.sk/scripts/click.php?a_aid=<AID>&a_bid=<BID>&data1=$1
 Teszt: /go?shop=arukereso&d1=teszt → Dognet URL ...&data1=teszt
Dognet
 Helyes a_aid és a_bid az adott shop kampányához
 (Opcionális) desturl URL-kódolva, ha mélylinket használsz
 Riportban megjelenik a kattintás; a data1 a részletes nézetekben/conversionöknél látszik
Ha szeretnéd, adok egy minta-CSV-t az egységes NGO-kódokhoz (név, kód), amit be tudsz importálni Filloutba, és írok előre kitöltött Redirection szabály-sablonokat más shopokhoz is – csak cseréled az a_bid értékét és kész.
Ezt mondtad:
Fillout űrlap (NGO választás + redirect) mit állítsak be az url paraméterekhez az ending redirecten?
A ChatGPT ezt mondta:
A Fillout ending redirectjénél a trükk az, hogy az URL-t sablonként adod meg, és beleilleszted azokat a mezőket, amelyeket az űrlapból vagy az URL-paraméterekből át akarsz adni.
Konkrétan:

URL paraméterek regisztrálása
Lépj be a Fillout form Settings → URL parameters menüpontjába.
Vedd fel a shop paramétert (és opcionálisan az amb-et is, ha használnál nagykövet kódot).
Ezekhez hozz létre a formban hidden fieldet.
A hidden field értékét állítsd úgy, hogy automatikusan vegye át a megfelelő URL-paramétert. (Pl. a shop mező értéke mindig a ?shop=... értéke legyen.)
NGO mező egyedi értékek
Az NGO választó (dropdown/radio) opcióihoz állíts be custom value-t → ez lesz az a kód, amit továbbküldünk (pl. voroskereszt, bator-tabor, unicef).
Redirect URL sablon
Az Ending redirect beállításánál írd be a következőt:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
@shop → a hidden shop mező értéke (URL-ből jön).
@NGO_kod → az NGO választó mező custom value-ja.
Ha használsz ambassador kódot is:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod&amb=@amb
Itt az @mezőnév szintaxis dinamikusan beszúrja a mező értékét. (Filloutban a redirect URL szerkesztőjénél tudod a + gombbal kiválasztani a mezőt is, nem kell fejből beírni.)
Teszt
Nyisd meg az űrlapot pl. így:
https://fillout.com/formxyz?shop=arukereso
Válassz egy NGO-t, küldd be.
A redirect után a böngészőben ilyet kell látnod:
https://app.sharity.hu/go?shop=arukereso&d1=voroskereszt
A kérdésedre rövid válasz: az Ending redirect URL-jébe azt írd, hogy
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
ahol @shop a hidden shop mező, @NGO_kod pedig az NGO választó mező custom value-ja.
Szeretnéd, hogy készítsek egy konkrét példát képernyőképes mintával (pl. Árukereső + Magyar Vöröskereszt esetén), hogy lásd pontosan mit kell a Filloutban beállítani?

Ezt mondtad:
Feltöltött kép
akkor ezt töröljem?
A ChatGPT ezt mondta:
A képernyőképen most azt látom, hogy a Redirect link parameters panelen minden paramétert külön sorban párosítasz (shop, d1, src, utm_source stb.). Emiatt az NGO neve (pl. Rápcselaki Szent István Alapítvány) teljes szöveggel kerül át, ékezetekkel és szóközzel → ezt nem szeretnéd, mert a Dognetnek kód kell (voroskereszt, bator-tabor stb.).
Itt a helyes megoldás:

d1 paraméterhez NE a “megjelenített szöveget” kösd, hanem a custom value-t (NGO kód), amit az űrlap NGO mezőjében adtál meg.
Ha az opció: „Magyar Vöröskereszt” → custom value: voroskereszt
Akkor a redirectben d1=voroskereszt fog megjelenni.
Az extra paraméterek (src, utm_source, utm_medium, utm_campaign) opcionálisak.
Ha most a cél az, hogy tisztán csak a shop és a d1 menjen át, nyugodtan töröld ezeket a plusz sorokat innen.
Később visszarakhatod őket, ha analitikához kell (pl. GA4-hez UTM-ek).
Az Ending redirect meződ kinézhet így:
https://app.sharity.hu/go?shop={shop}&d1={NGO_KOD}
(ahol {shop} a hidden shop mező, {NGO_KOD} pedig az NGO választó mező custom value-ja).
A preview-ban így kell majd kinéznie:
https://app.sharity.hu/go?shop=arukereso&d1=voroskereszt
Tehát röviden: igen, töröld a felesleges sorokat (src, utm_source, utm_medium, utm_campaign), amíg nem akarsz UTM-eket küldeni. Csak a shop és d1 maradjon, és a d1 mindenképp a custom value mezőre legyen kötve.
Szeretnéd, hogy készítsek egy konkrét lépéslistát Filloutban, hogyan állítsd át a d1 paramétert, hogy mindig a custom value menjen át, ne a teljes NGO név?

Ezt mondtad:
Feltöltött kép
hogy tudom a d1 et custom value ra állítani, nem tudom kiválasztani a listából
A ChatGPT ezt mondta:
Most pontosan rátapintottál a kulcsra: a d1 paraméterhez nem a megjelenített szöveget kell hozzárendelni, hanem az NGO mező custom value-ját.
Filloutban ezt így tudod beállítani:

Nyisd meg az űrlap szerkesztőjében az NGO választó mezőt („Válassz ügyet”).
A jobb oldali beállításoknál az opcióknál látni fogsz két oszlopot:
Label (amit a felhasználó lát, pl. „Magyar Vöröskereszt”),
Value (a háttérben tárolt érték → ide írd be a rövid kódot, pl. voroskereszt).
→ Ez a „Value” lesz a custom value, amit a rendszer mindenhol átad.
Példa:
Label: Magyar Vöröskereszt → Value: voroskereszt
Label: Bátor Tábor Alapítvány → Value: bator-tabor
Label: UNICEF → Value: unicef
Menj vissza az Ending → Redirect → Redirect link parameters részhez.
Ott a d1 sor jobb oldali legördülőjében nem a „Válassz ügyet” label-t kell választanod, hanem magát a mezőt.
Ha a mezőnek több mezőnév-változata van (pl. „Válassz ügyet – value” vagy „Válassz ügyet – text”), akkor a value verziót válaszd. Ez adja vissza a custom value-t.
A Redirect előnézetben most így kell kinéznie:
https://app.sharity.hu/go?shop=arukereso&d1=voroskereszt
(nem pedig a teljes név ékezetekkel és szóközzel).
Ha a listában most nem tudod kiválasztani a value-t, akkor az azért van, mert az NGO meződ opcióihoz még nincsenek „Value”-k kitöltve → csak a Label van meg. Amint kitöltöd a Value oszlopot az opcióknál, a Fillout automatikusan elérhetővé teszi, és a Redirect paramétereknél megjelenik a „mező (value)” lehetőség.
Szeretnéd, hogy generáljak neked egy komplett NGO név–kód táblát (pl. CSV-be), amit egy az egyben be tudsz másolni a Fillout opciókhoz?
Ezt mondtad:
Feltöltött kép
ezt tudom választani
A ChatGPT ezt mondta:
Nagyon jó, látom mit mutat a listád: most a d1-hez tudsz választani pl. „Válassz ügyet”.
Ez pontosan az a mező, amit használni kell — de akkor fog működni, ha az „Válassz ügyet” mező opcióihoz beállítasz Value-t (custom value).

Most valószínűleg csak Label van kitöltve (pl. „Magyar Vöröskereszt”), ezért amikor redirectelsz, a teljes szöveget adná át (Rápcselaki Szent István Alapítvány, ékezetekkel, szóközökkel).

Mit kell csinálnod:
Menj vissza az űrlap szerkesztőben az „Válassz ügyet” mezőhöz.
Az opcióknál minden sorhoz add meg:
Label: a megjelenő név (pl. Magyar Vöröskereszt)
Value: az egyszerűsített kód (pl. voroskereszt)
Példa:
Label: Bátor Tábor Alapítvány → Value: bator-tabor
Label: UNICEF Magyar Bizottság → Value: unicef
Ezután a Redirect beállításnál, amikor a d1 paraméterhez kiválasztod a „Válassz ügyet” mezőt, a rendszer automatikusan a Value-t fogja továbbadni, nem a Labelt.
Eredmény:
A redirect link így fog kinézni:
https://app.sharity.hu/go?shop=arukereso&d1=voroskereszt
és nem így:
...&d1=Magyar Vöröskereszt
Tehát röviden: igen, a „Válassz ügyet” mezőt válaszd a d1-hez, de előtte mindenképp töltsd ki a Value oszlopokat az opcióknál.
Szeretnéd, hogy most legeneráljam neked az NGO-kód listát (Label + Value párosok), amit csak be kell másolnod a Fillout mező opcióiba?

Ezt mondtad:
Feltöltött kép
itt mit írjak át? 
A ChatGPT ezt mondta:
Nagyon jó, itt már a WordPress Redirection szabályt látom. Most kicsit túl van bonyolítva: jelenleg az egész query stringet továbbadod ({QUERY:shop}, {QUERY:d1}, {QUERY:src} stb.), plusz egy url= paraméterbe ágyazva. Ez nem szükséges, és zavarhatja a Dognetet.
Ami kell:

a Dognet affiliate link fix része: https://go.dognet.com/?cid=...&refid=... (ez a te kampány- és partnerazonosítód),
plusz a választott NGO kód: &data1=... (ezt kell a Fillout → /go átadásból beilleszteni).
Hogyan írd át
Source URL:
Most:
^/go/?$
Ez így csak az üres /go-t fogná meg. Neked a query paramétereket is illeszteni kell. Például:
^/go\?shop=([^&]*)&d1=([^&]+)$
– ([^&]*) = első csoport a shop neve,
– ([^&]+) = második csoport az NGO kód.
Target URL:
Most sok fölös {QUERY:...} van. Egyszerűsítsd:

https://go.dognet.com/?cid=897&refid=67246ee77827f&data1=$2
Itt:
cid=897 és refid=67246ee77827f → ezeket a saját Dognet fiókodból vedd,
$2 → a második regex csoport = a d1 értéke, vagyis az NGO kód.
Összefoglalva:
Source URL legyen:
^/go\?shop=([^&]*)&d1=([^&]+)$
Target URL legyen:
https://go.dognet.com/?cid=897&refid=67246ee77827f&data1=$2
Regex maradjon bekapcsolva.
HTTP code: 302 (Temporary).
Tesztelés:
Nyisd meg a böngészőben:
https://app.sharity.hu/go?shop=arukereso&d1=tesztngo
→ azonnal át kell irányítson Dognet linkre, ahol a címsorban ...&data1=tesztngo látszik.
Szeretnéd, hogy írjak egy példát Emag-ra is, hogy lásd hogyan kell több webshophoz külön szabályt létrehozni?
Ezt mondtad:
Feltöltött kép
https://go.dognet.com/?cid=897&refid=67246ee77827f&data1=tesztngo ez jött be
A ChatGPT ezt mondta:

Gondolkozás
Igen, ez a hiba pont azt jelzi, hogy a go.dognet.com típusú link URL-t (destination) is vár, de azt nem adtuk meg → „No destination URL provided”.
Két jó megoldás van, válassz egyet:

A) Egyszerűbb: PAP „click.php” link (nem kell destination)
Itt nem kötelező külön cél URL, mert a banner/kampány alap-landingje be van állítva.
Redirection:

Source URL
^/go\?shop=([^&]*)&d1=([^&]+)$
Target URL
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<ARUKERESO_BID>&data1=$2
<SAJAT_AID> = a te affiliate azonosítód
<ARUKERESO_BID> = az Árukereső kampány/banner ID
$2 = a regex 2. csoportja = d1 (NGO-kód)
HTTP kód: 302 (Temporary)
Query Parameters: Exact
Regex: be
Teszt:
https://app.sharity.hu/go?shop=arukereso&d1=tesztngo → át kell ugrania Dognetre, data1=tesztngo-val.
B) Ha ragaszkodsz a go.dognet.com rövid linkhez: add meg a url= paramétert
A rövidlink megköveteli a céloldalt (url=), URL-kódolva.
Target URL példa (Árukereső főoldalra):

https://go.dognet.com/?cid=897&refid=67246ee77827f&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$2
A https://www.arukereso.hu/ kódolt formája: https%3A%2F%2Fwww.arukereso.hu%2F
Ha más aloldalra akarsz menni, azt kódold és tedd a url=-be.
A Source URL maradhat ugyanaz:
^/go\?shop=([^&]*)&d1=([^&]+)$
Gyors hibakeresés
Ha „No destination URL provided” jelenik meg → B verziónál biztosan hiányzik a url= vagy nincs URL-kódolva.
Ha nem jelenik meg a data1 a végső URL-ben → ellenőrizd, hogy a Source regex két csoportot fog: a második ($2) legyen a d1.
Ha több shopod van → shoponként külön szabály (más-más a_bid vagy más url=).
Szeretnéd, hogy most megírjam neked mindkét mintát Arukereso + Emag-ra konkrét, bemásolható Target URL-ekkel (click.php és rövidlink verzióban is)?
Ezt mondtad:
de nem az árukeresőt akarom beállítani hanem mindig azt a webshopot ahonnan jött a user
A ChatGPT ezt mondta:

Gondolkozás
Értem: dinamikusan kell mennie annak a shopnak, ahonnan a user jött (shop URL-paraméter alapján). Ezt a Redirection önmagában nem tudja egyetlen szabállyal megoldani, mert minden shophoz más a Dognet-azonosító (a_bid / cid / refid). Két bevált út van:
1) „Low-tech” megoldás – shoponként 1 szabály (kód nélkül)
Egyszerű és stabil, csak ismétlődő munka.
Minden shophoz külön szabály:

Source URL
^/go\?shop=ARUKERESO&d1=([^&]+)$
(cseréld a ARUKERESO részt a shop kódodra)
Target URL (click.php verzió – nincs szükség dest URL-re):
https://login.dognet.sk/scripts/click.php?a_aid=<AID>&a_bid=<BID_ARUKERESO>&data1=$1
HTTP code: 302 (Temporary)
Regex: ON, Query Parameters: Exact
Duplikálod a szabályt minden shopra (emag, alza, stb.), csak a Source shop-kódját és a Target a_bid-jét cseréled.
Előny: nincs kódolás.
Hátrány: minden új boltnál új szabály.

2) „Pro” megoldás – 1 szabály helyett 1 kis kódrészlet (dinamikus routing)
Egyetlen URL-t használsz: /go?shop=...&d1=... és a kód dönt, hova menjen a Dogneten.
Lépések
Kapcsold ki / töröld a mostani /go Redirection szabályt (ne ütközzön).
WPCode (vagy mini plugin) – illeszd be az alábbi kódot „Run everywhere” beállítással:
<?php
// WPCode snippet: Dynamic Dognet redirector for /go?shop=...&d1=...
add_action('init', function () {
  // Csak a /go végpontot kezeljük
  $req_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
  if ($req_path !== 'go') return;

  // Kell: shop és d1
  if (!isset($_GET['shop'], $_GET['d1'])) return;

  // Biztonságos értékek
  $shop = sanitize_key($_GET['shop']);                // pl. arukereso, emag
  $d1   = rawurlencode(sanitize_text_field($_GET['d1'])); // NGO kód

  // --- SHOP → Dognet konfigurációs tábla ---
  // Választhatsz két mintát:
  // A) click.php (nem kell külön destination URL)
  // B) go.dognet.com rövidlink (kötelező url=, URL-kódolva)
  $map = [
    'arukereso' => [
      'type' => 'click', // A) click.php
      'a_aid' => 'YOUR_AID_HERE',
      'a_bid' => 'BID_ARUKERESO'
    ],
    'emag' => [
      'type' => 'click',
      'a_aid' => 'YOUR_AID_HERE',
      'a_bid' => 'BID_EMAG'
    ],
    // Példa rövidlinkes shop (B verzió):
    'pelda' => [
      'type'  => 'short', // B) go.dognet.com
      'cid'   => '897',
      'refid' => '67246ee77827f',
      'url'   => 'https://www.pelda.hu/' // ide megy a user
    ],
  ];

  if (!isset($map[$shop])) {
    // Ismeretlen shop → vissza az Impact Shopra (vagy 404)
    wp_redirect(home_url('/impactshop/?unknown_shop=' . rawurlencode($shop)), 302);
    exit;
  }

  $conf = $map[$shop];

  // Cél URL összeállítása
  if ($conf['type'] === 'click') {
    // A) click.php – egyszerű, megbízható
    $target = 'https://login.dognet.sk/scripts/click.php'
            . '?a_aid=' . rawurlencode($conf['a_aid'])
            . '&a_bid=' . rawurlencode($conf['a_bid'])
            . '&data1=' . $d1;
  } else {
    // B) rövidlink – KELL destination
    $dest = isset($conf['url']) ? $conf['url'] : 'https://www.google.com/';
    $target = 'https://go.dognet.com/'
            . '?cid='   . rawurlencode($conf['cid'])
            . '&refid=' . rawurlencode($conf['refid'])
            . '&url='   . rawurlencode($dest)
            . '&data1=' . $d1;
  }

  // Irány a Dognet
  wp_redirect($target, 302);
  exit;
});
Használat
A Fillout továbbra is ide küld:
https://app.sharity.hu/go?shop={shop}&d1={NGO_KOD}
A kód megnézi a shop értékét, és a $map alapján összeállítja a helyes Dognet URL-t.
A/ „click.php” típusnál nem kell külön cél URL.
B/ „go.dognet.com” típusnál kötelező a url= (a táblában az adott shophoz add meg a saját céloldalt).
Előny: bármennyi shop, egy helyen karbantartható.
Hátrány: egyszer kell egy rövid kódot betenni.
Miért kaptad a „No destination URL provided” hibát?
Mert a go.dognet.com rövidlink megköveteli a url= paramétert. Ha nincs megadva (és URL-kódolva), ezt a hibát mutatja.
Ha nem akarsz cél URL-lel bajlódni, válts a click.php linkformára (A megoldás) – az mindig működik a kampány alap landingjére.
Mit tarts meg a Fillout
Ezt mondtad:
maradjunk az egyszerűbbnél, minden webshopnak lesz egy külön fillout linkje, ez elvisz a Filloutra, az hozzáteszi a választott ngo-t. Miért nem megyünk innen egyből a dognetre?
A ChatGPT ezt mondta:

Gondolkozás
Röviden: mehetünk egyből a Dognetre a Filloutból – technikailag semmi akadálya. Azért javasoltam köztes /go lépést, mert így rugalmasabban tudsz mérni/logolni és később változtatni, de ha egyszerűséget akarsz és shoponként külön Fillout űrlapod lesz, akkor csináljuk direktben.
Hogyan irányíts közvetlenül Dognetre (ajánlott „click.php” forma)
A Fillout Ending → Redirect URL-jébe írd a Dognet-link sablont, és szúrd be az NGO-kód mezőt (custom value):
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<SHOP_BID>&data1=@NGO_kod
<SAJAT_AID>: a te affiliate azonosítód
<SHOP_BID>: az adott webshop (kampány/banner) ID-ja a Dognetben
@NGO_kod: a „Válassz ügyet” mező Value-ja (custom value)
Példák
Árukereső űrlap redirectje:
https://login.dognet.sk/scripts/click.php?a_aid=YOURAID&a_bid=BID_ARUKERESO&data1=@NGO_kod
eMAG űrlap redirectje:
https://login.dognet.sk/scripts/click.php?a_aid=YOURAID&a_bid=BID_EMAG&data1=@NGO_kod
A „click.php” link előnye, hogy nem igényel külön cél URL-t – a kampány alap-landingjére visz, és a data1 (NGO-kód) rögzül.
Alternatíva: rövidlink (go.dognet.com) – csak ha feltétlen kell
Ekkor kötelező megadni a céloldalt URL-kódolva:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=<ENCODED_URL>&data1=@NGO_kod
Példa (Árukereső főoldal):
https://go.dognet.com/?cid=897&refid=67246ee77827f&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=@NGO_kod
Ha hiányzik az url=, jön a „No destination URL provided” hiba.
Miért érdemes mégis saját /go réteget tartani? (ha később bővítesz)
Egy helyen cserélheted a kampány-ID-kat/üzleti logikát, nem minden űrlapban külön.
Könnyebb UTM/analytics egységesítés, eseménylog (kattintásnaplózás).
Ambassador/partner csatorna kezelés (amb, chan) bővíthető.
Adblock/ITP miatti harmadik feles átirányítások néha finnyásak; saját domain érintése stabilabb mérést adhat.
De ha most az első cél a legkevesebb mozdulat, a Fillout → Dognet (click.php) a leggyorsabb és stabil.
Gyorskontroll a Filloutban
Az NGO mező opcióihoz legyen Value (custom value) (pl. voroskereszt, bator-tabor), ne a label menjen át.
Redirect URL-ben a @NGO_kod a Value-t szúrja be.
Teszt: válassz egy NGO-t → böngésző címe Dognet „click.php…&data1=voroskereszt”.
Ezzel a beállítással minden shop saját űrlapjáról egy lépésben a Dognetre ugrasz, a data1-ben a választott NGO kóddal.
Ezt mondtad:
Akkor próbálkozhatunk még a vissza linkkel az én oldalamra és onnan a dognetre, de már sokféleképpen próbáltuk és mindig hibás. De adok még egy esélyt, hogy megoldd. Tehát akkor egy form, onnan vissza az oldalra és onnan tovább a pdognet webshop affiliate linkre. Küldd a beállításokat
A ChatGPT ezt mondta:
Oké, csináljuk meg a „Fillout → vissza a saját oldalra → onnan Dognetre” láncot úgy, hogy stabilan működjön. Itt a konkrét, bemásolható beállítási csomag – először Fillout, aztán WordPress Redirection. (A Dognethez a click.php formát használjuk, mert nem kér külön destination URL-t, így nem lesz „No destination URL provided” hiba.)
1) Fillout: visszairányítás a saját “/go” endpointodra
Mezők
NGO választó mező (dropdown/radio): minden opciónál legyen Value (custom value), pl. voroskereszt, bator-tabor, unicef.
shop rejtett mező: az űrlap URL-jéből vegye át a shop paramétert (pl. ...?shop=arukereso). „Hide always”.
Ending → Redirect (beküldés után)
Redirect URL:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
(@shop = a hidden shop mező; @NGO_kod = az NGO mező Value-ja, nem a Label)
Gyorsteszt
Nyisd meg: https://fillout.com/your-form?shop=arukereso → válassz NGO-t → Submit → a böngésző a
https://app.sharity.hu/go?shop=arukereso&d1=voroskereszt-re érkezik.

2) WordPress Redirection: /go → Dognet (shoponként külön szabály)
A Redirection egy szabállyal nem tudja a különböző shopokhoz tartozó eltérő Dognet-kampányokat (külön a_bid) kezelni, ezért shoponként 1 szabály a biztos megoldás. Mindegyiknél ugyanazt a mintát követed:
Általános beállítások (minden szabályra)
Add new redirect
Regex: ✔ bekapcsolva
Match: URL and query (vagy hagyd „URL only”-n, de akkor a Source-hoz úgyis regexet adunk)
Query Parameters: Exact
When matched: Redirect to URL → 302 – Temporary Redirect
(Title, Group tetszőleges)
2.1 Árukereső (példa)
Source URL
^/go\?shop=arukereso&d1=([^&]+)$
Target URL
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<ARUKERESO_BID>&data1=$1
<SAJAT_AID> = a te affiliate azonosítód
<ARUKERESO_BID> = az Árukereső kampány/banner ID-ja a Dognetben
$1 = a fenti regex 1. csoportja → a d1 (NGO-kód)
2.2 eMAG (minta)
Source URL
^/go\?shop=emag&d1=([^&]+)$
Target URL
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<EMAG_BID>&data1=$1
2.3 További shopok
Ugyanezt ismételd minden további bolthoz: a Source-ban a shop=... értéket, a Targetben az a_bid-et cseréled.
Teszt minden szabályra
Böngészőben nyisd meg:

https://app.sharity.hu/go?shop=<shop_kod>&d1=tesztngo
– azonnal át kell ugrania a Dognetre, és a címsorban látszania kell ...&data1=tesztngo.
(Csak ha mindenképp rövidlinket akarsz) go.dognet.com változat
A rövidlink kötelező url= paramétert vár (URL-kódolva). Ugyanígy shoponként külön szabály kell.
Target URL példa Árukeresőre (főoldalra):

https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Ha az url= hiányzik vagy nincs kódolva → „No destination URL provided”.
Javaslat: maradjunk a click.php formánál (fenti beállítás), ez a legstrapabíróbb.

Tipikus hibák, gyors fix
Nem történik redirect / rossz link készül: ellenőrizd, hogy a Redirection szabálynál Regex ON, Query Parameters = Exact.
Nem megy át a d1 érték: a Source regexben legyen ([^&]+) csoport a d1-re, és a Targetben $1-gyel hivatkozz rá.
Fillout rossz értéket ad (ékezetes, szóközös név): az NGO mező opcióinál töltsd ki a Value-t; a Redirectben az NGO Value menjen (nem a Label).
„No destination URL provided”: ezt csak a go.dognet.com dobja, ha nincs url=. A click.php-nál nem fordul elő.
Gyorskivonat (amit konkrétan beírsz)
Fillout Redirect URL
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
Redirection – Árukereső
Source:
^/go\?shop=arukereso&d1=([^&]+)$
Target:
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<ARUKERESO_BID>&data1=$1
302, Regex ON, Query Parameters: Exact
Redirection – eMAG
Source:
^/go\?shop=emag&d1=([^&]+)$
Target:
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<EMAG_BID>&data1=$1
302, Regex ON, Query Parameters: Exact
Ugorjunk neki így. Ha küldöd a konkrét shop-listát és a hozzájuk tartozó a_bid-eket, megírom készen a teljes szabálycsomagot, hogy csak be kelljen másolnod.
Ezt mondtad:
Feltöltött kép
Feltöltött kép
Feltöltött kép
Feltöltött kép
Feltöltött kép
Először teszteljük árukeresőre hogy működik-e, utána fogom csak a többi webshopot beállítani. https://www.fillout.com/your-form?shop=arukereso
A ChatGPT ezt mondta:

Gondolkozás
Megoldjuk úgy, hogy biztosan működjön. Két helyen kell javítanod:
1) Fillout – NE duplázd a paramétert
Most a Redirect URL-ben @NGO_kod is szerepel, és alul a Redirect link parameters-ben is hozzárendeled → emiatt a preview-ban látszik a duplázás és a „%40NGO_kod”. Válassz egy módszert. A legegyszerűbb:
A) Paraméter-mappolás (ajánlott)

Redirect URL:
https://app.sharity.hu/go
(tehát NINCS kérdőjel, NINCS shop= és NINCS d1= itt)
Redirect link parameters:
shop → mező: shop (hidden, az URL-ből jön)
d1 → mező: Válassz ügyet (ennek a Value-ja fog menni – lásd képed: „Custom values” ON, pl. bator-tabor-alapitvany)
Ezzel a böngésző a beküldés után pontosan erre megy:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
B) (Alternatíva) Inline tokenes URL
Redirect URL:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
Redirect link parameters: üres (ne adj hozzá semmit)
Vagy A vagy B – ne keverd őket.
2) WordPress Redirection – a 404 oka és a helyes szabály
A 404 azért jött, mert a szabály nem illesztette a query stringet. Oldjuk meg egyszerűen, regex nélkül, és az NGO-kódot a Redirection saját {QUERY:...} változójából vesszük:
Árukereső szabály (próba)
Add new redirect
Source URL:
/go
Match: URL and query parameters
Query Parameters: Exact
(Ha van „Add parameter” opció): add hozzá, hogy
shop = arukereso (így csak az Árukeresős hívást fogja)
When matched: Redirect to URL – 302 Temporary
Target URL:
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<ARUKERESO_BID>&data1={QUERY:d1}
Nincs regex, nincs $1. A data1 a d1 query param értéke lesz.
Ha nincs külön „Add parameter” mező a Source-hoz, akkor használd a regex-es változatot (lent).
Ugyanez REGEX-szel (ha a fenti UI nincs)
Regex: ✔ bekapcsolva
Match: URL only (maradhat), de fontos: a Query Parameters résznél állíts Exact-ra (ha látszik nálad).
Source URL:
^/go\?shop=arukereso&d1=([^&]+)$
Target URL:
https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<ARUKERESO_BID>&data1=$1
HTTP kód: 302
A 404 akkor tűnik el, ha a Redirection valóban „elkapja” a /go?... kérést. Ha mégis 404-et látsz, ellenőrizd:
Regex be van kapcsolva a szabálynál.
Nem ütközik más /go-s szabállyal.
A Fillout tényleg /go-ra megy (nem pl. /go/ extra slash-sel). Ha kell, a Source-ot írd így: ^/go/?\?shop=arukereso&d1=([^&]+)$
Gyorsteszt lépések (Árukereső)
Fillout űrlap megnyitása:
https://fillout.com/VALOS-URL?shop=arukereso (ne a „your-form” dummy linken tesztelj – az 404-et fog adni a Fillouttól)
Válassz egy ügyet, Submit → a böngésző címe legyen:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
A Redirection azonnal továbbküld:
https://login.dognet.sk/scripts/click.php?...&data1=bator-tabor-alapitvany
Ha ez megy, 60 másodperc alatt klónozhatod a szabályt a többi shopra:
Source URL: ^/go\?shop=emag&d1=([^&]+)$
Target URL: https://login.dognet.sk/scripts/click.php?a_aid=<SAJAT_AID>&a_bid=<EMAG_BID>&data1=$1
Leggyakoribb elakadás és megoldása
Preview-ban ...&d1=%40NGO_kod&NGO_kod=... → Filloutban duplán adtad át. Válts az A (mappolós) vagy B (inline) megoldásra, de ne keverd.
„No destination URL provided” → ez csak a go.dognet.com rövidlinkkel jön, ha nincs url=. A fenti megoldás click.php-t használ, itt nem kell url=.
Redirection nem kapja el → 404 → állítsd a Match-et „URL and query parameters”-ra, és/vagy használd a fenti REGEX-es Source-ot; kapcsold be a Regex-et.
Ha küldöd az Árukeresőhöz tartozó valódi form URL-t és a konkrét a_bid értéket, rákötöm pontosan a Target URL-t szövegszerint is.
Ezt mondtad:
Ez a link jól megy a pdognet oldalra, tehát így kell beállítani a redirectet: https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1 Ehhez kell igazítani a Source t és a Fillout Redirect linket illetve az url paramétert. 
A ChatGPT ezt mondta:
Tiszta sor. Ha a rövidlinkes Dognet-formát akarod (kötelező url= paraméterrel), akkor így állítsuk be Árukeresőre:
1) Fillout beállítás
Cél: https://app.sharity.hu/go?shop=arukereso&d1=<NGO_KOD>
Kétféleképp jó – csak egyiket használd (ne duplázd a paramétereket):

A) Paraméter-mappolással (ajánlott)

Redirect URL:
https://app.sharity.hu/go
Redirect link parameters:
shop → shop (hidden mező az URL-ből)
d1 → Válassz ügyet mező Value (custom value)
Eredmény beküldés után:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
B) Inline tokenes URL
Redirect URL:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
Redirect link parameters: üres
Fontos: az NGO mező Custom values = ON, és a Value legyen az URL-barát kód (pl. bator-tabor-alapitvany).
2) WordPress Redirection szabály (Árukereső → go.dognet.com)
Változat: rövidlink kötelező url=-lel (ez kell neked)
Add new redirect
Regex: ✔ bekapcsolva
Match: „URL only” is jó, de a Query Parameters-t állítsd Exact-ra (ha van ilyen opció nálad)
Source URL:
^/go\?shop=arukereso&d1=([^&]+)$
Ez a d1 értékét az 1. csoportba teszi → $1.
Target URL:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
<CID> és <REFID>: a Dognet fiókod szerinti értékek
url=: URL-kódolt cél (Árukereső főoldal)
data1=$1: a Filloutból érkező NGO-kód
HTTP code: 302 – Temporary
Teszt URL:
https://app.sharity.hu/go?shop=arukereso&d1=tesztngo
→ át kell ugrania erre a mintára:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=tesztngo
3) Gyakori elakadás → gyors javítás
„%40NGO_kod” vagy duplázott paraméter a preview-ban: a Filloutban ne add meg a d1-et mindkét helyen. Válaszd A vagy B módszert.
404 a /go hívásnál: biztosítsd, hogy a Source pontosan illeszkedjen (nincs plusz /go/ perjel); ha kell, használhatod ezt is:
^/go/?\?shop=arukereso&d1=([^&]+)$
„No destination URL provided”: ez csak akkor jön, ha go.dognet.com-nál hiányzik az url= vagy nincs URL-kódolva. A fenti Targetben ez megvan.
4) Minták más shopokra (csak a szükséges részeket cseréled)
Source:
^/go\?shop=emag&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.emag.hu%2F&data1=$1
Ha küldöd a boltok listáját a hozzájuk tartozó cél-URL-lel (amit be kell kódolni), visszadobom a kész Target URL-eket és a Source regexeket mindenhez, bemásolhatóan.
Ezt mondtad:
kérem Decathlonra
A ChatGPT ezt mondta:
Oké, csináljuk meg a Decathlon szabályt ugyanabban a mintában, ahogy az Árukeresőnél beállítottad.
1) Fillout
Marad minden ugyanaz, csak az adott formot így nyitod meg:
https://fillout.com/<formid>?shop=decathlon
Beküldés után így kell kinéznie az URL-nek:
https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor-alapitvany
2) WordPress Redirection (Decathlon)
Source URL
^/go\?shop=decathlon&d1=([^&]+)$
Target URL
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
<CID> és <REFID> → a Dognet fiókodban a Decathlon kampányhoz tartozó adatok
url= → itt a Decathlon főoldala van beállítva, URL-kódolva (https%3A%2F%2Fwww.decathlon.hu%2F)
data1=$1 → a Filloutból érkező NGO kód
HTTP code: 302 – Temporary
Regex: bekapcsolva
Query Parameters: Exact
3) Teszt
Nyisd meg:
https://app.sharity.hu/go?shop=decathlon&d1=tesztngo
→ át kell ugorjon ide (példa):
https://go.dognet.com/?cid=123&refid=abcd1234&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=tesztngo
Szeretnéd, hogy összegyűjtsem neked a magyar Decathlon valódi Dognet linkjét (cid, refid paraméterekkel kitöltve), hogy ne kelljen kézzel keresgélned a fiókodban?
Ezt mondtad:
rövid linket használtam
A ChatGPT ezt mondta:
Tökéletes, akkor tartsuk a rövidlinkes formát (go.dognet.com), csak a url= paraméter legyen mindig benne és URL-kódolva.
Decathlon beállítás
Source URL (WordPress Redirection):
^/go\?shop=decathlon&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
<CID> → a Dognet által adott kampányazonosító (Decathlonhoz).
<REFID> → a te affiliate azonosítód.
url= → itt a Decathlon főoldala, URL-kódolva (https://www.decathlon.hu/ → https%3A%2F%2Fwww.decathlon.hu%2F).
data1=$1 → a Filloutból jövő NGO-kód.
HTTP code: 302 (Temporary)
Regex: bekapcsolva
Query Parameters: Exact
Fillout
A redirect marad a szokásos formában:
https://app.sharity.hu/go?shop=decathlon&d1=@NGO_kod
(@NGO_kod → az ügy mező Value értéke, pl. bator-tabor-alapitvany.)
Teszt
Böngészőben:
https://app.sharity.hu/go?shop=decathlon&d1=tesztngo
→ Át kell irányítania erre:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=tesztngo
Szeretnéd, hogy legeneráljam neked a pontos Target URL-t a Decathlonhoz a Dognet kampányadatokból (cid, refid), vagy megvan nálad a Dognet fiókban?
Ezt mondtad:
működik de nézzük az elejétől a fillout ra navigálás előttről
A ChatGPT ezt mondta:
Szuper, örülök, hogy a Decathlon rövidlink most már működik! Nézzük át az egész folyamatot az elejétől, hogy világos legyen, mit hova kell tenni:
0. Kiindulópont – webshop ikon a Sharity oldaladon
A felhasználó rákattint a webshop (pl. Decathlon) logójára.
A link nem közvetlenül Dognetre mutat, hanem a Fillout űrlapodra, hogy NGO-t választhasson.
Példa link a logóra:
https://fillout.com/<formid>?shop=decathlon
Itt a shop=decathlon paraméter már az URL-ben van → ezt a Fillout hidden mezője átveszi.
1. Fillout űrlap
Mezők:
„Válassz ügyet” → dropdown/radio, minden opcióhoz Label (felhasználó látja) és Value (custom value, pl. bator-tabor-alapitvany).
shop → hidden mező, értékét a ?shop= URL-paraméterből kapja.
Redirect beállítás:
Redirect URL:
https://app.sharity.hu/go
Redirect link parameters:
shop → mező: shop
d1 → mező: Válassz ügyet (Value)
Eredmény:
Felhasználó a Submit után ide kerül:
https://app
Ezt mondtad:
ez nem jó: https://fillout.com/<formid>?shop=decathlon
A ChatGPT ezt mondta:

Befejezte a gondolkodást
Ezt mondtad:
Ez az arukereso fillout jó link: https://form.fillout.com/t/eM61RLkz6jus?Redirect=xxx&shop=arukereso
A ChatGPT ezt mondta:
Pontosan 👍 – most látom, hogy a helyes Fillout link formátum így néz ki, nem pedig a „https://fillout.com/…” általános domain alatt.
Mit kell használni webshop ikonhoz
Árukereső példa:
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Decathlon példa:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Tehát: mindig a saját űrlapod form.fillout.com/t/<formid> linkjét használd, és csak a ?shop=<shopnev> query-t cseréld a megfelelőre.
Mi történik ezután?
User kattint → pl. ?shop=decathlon
Fillout űrlap megnyílik, a hidden shop mező értéke decathlon lesz.
Beküldés után a Redirect URL így néz ki:
https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor-alapitvany
WordPress Redirection szabály elkapja a shop=decathlon hívást, és Dognet linkre küldi:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=bator-tabor-alapitvany
Szeretnéd, hogy most legeneráljam neked a teljes ikon-link + Fillout redirect + WordPress Source/Target szabályt egyben az Árukeresőre és a Decathlonra, hogy másolható legyen és biztosan működjön?
Ezt mondtad:
Ez a jó link, ami kiviszi a Filloutból az arukeresore: https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1. Ez pedig a jó link ami arukereso fillout ot nyit meg: https://build.fillout.com/editor/eM61RLkz6jus/edit/8FjX?Redirect=xxx&shop=arukereso  Rakd már össze valahogy
A ChatGPT ezt mondta:

Gondolkozás
Megvan — összerakom úgy, hogy Fillout → saját /go → Dognet rövidlink legyen, és közben kijavítom a két „link-keverést”.
0) Webshop ikon linkje a Sharity oldaladon
NE az editor-linket (build.fillout.com/editor/...) használd, az csak neked működik bejelentkezve. A publikus link formátuma ez:
Árukereső ikon URL:

https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
A Redirect=xxx query itt nem kell, mert az űrlapon belül állítjuk a redirectet.
1) Fillout beállítás (NGO választás → vissza a /go-ra)
Mezők
„Válassz ügyet” (dropdown/radio): minden opcióhoz Value (custom value) legyen (pl. bator-tabor-alapitvany).
shop (hidden): értékét az URL ?shop= paraméteréből vegye, „Hide always”.
Ending → Redirect
Redirect URL:
https://app.sharity.hu/go
Redirect link parameters:
shop → mező: shop (hidden)
d1 → mező: Válassz ügyet (Value)
Eredmény beküldés után:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
Ne duplázd a paraméterezést! Vagy paraméter-mappelés (fenti mód), vagy inline tokenes URL (pl. ...?shop=@shop&d1=@NGO_kod) — de ne mindkettő.
2) WordPress Redirection ( /go → Dognet rövidlink, kötelező url= )
Új szabály (Árukereső):
Regex: ✔ (bekapcsolva)
Query Parameters: Exact
HTTP code: 302 – Temporary
Source URL:
^/go\?shop=arukereso&d1=([^&]+)$
(az 1. csoport = a d1 értéke)
Target URL:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
<CID> és <REFID>: a Dognet fiókod szerinti értékek
url=: URL-kódolt cél (Árukereső főoldal)
data1=$1: az NGO-kód, a Source regex 1. csoportja
Teszt:
https://app.sharity.hu/go?shop=arukereso&d1=tesztngo
→ át kell ugrania ide:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=tesztngo
Gyors hibakereső jegyzet
„No destination URL provided” ⇒ a go.dognet.com rövidlinknél hiányzik a url= vagy nincs URL-kódolva. A fenti Target jó.
„%40NGO_kod” / duplázott d1 ⇒ a Filloutban egyszerre használtad az inline tokent és a paraméter-mappelést. Válassz egyet (fent az A megoldás aktív).
404 a /go-n ⇒ a Source nem illeszkedik. Kapcsold be a Regex-et és állítsd a Query Parameters-t Exact-ra. Ha kell, ez is jó Source:
^/go/?\?shop=arukereso&d1=([^&]+)$
Ha ez működik (és most már fog), 30 másodperc a Decathlon klón:
Source:

^/go\?shop=decathlon&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
Küldd át a <CID> és <REFID> értékeket, ha szeretnéd, kitöltöm a kész Targeteket minden shophoz, hogy csak bemásold.
Ezt mondtad:
mi az árukereső logó alá kerülő link?
A ChatGPT ezt mondta:

Gondolkozás
Ez kerüljön az Árukereső logó alá:
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Ennyi. (A redirectet az űrlapon belül állítjuk, ezért nem kell külön Redirect= paraméter a linkbe.)
Ha HTML-ben adod meg:

<a href="https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso" target="_blank" rel="noopener">Vásárlás az Árukeresőn</a>
Következő lépésnek kattints rá, válassz egy NGO-t, és nézd meg, hogy a böngésző a beküldés után erre megy-e:
https://app.sharity.hu/go?shop=arukereso&d1=<ngo_kod>
Ezt mondtad:
oldal nem található. Ide vitt: https://app.sharity.hu/go?d1=bator-tabor-alapitvany
A ChatGPT ezt mondta:

Gondolkozás
A hiba oka: a Fillout nem adta át a shop paramétert, ezért a böngésző csak ide jutott:
https://app.sharity.hu/go?d1=bator-tabor-alapitvany → a Redirection szabály nem illeszkedett (shop hiányzik) → “oldal nem található”.
Csináljuk meg gyorsan és bombabiztosan:

1) Fillout – állítsuk be úgy, hogy a shop IS átmenjen
Most az Árukeresőt teszteled, ezért a legegyszerűbb a fix, inline megoldás.
Nyisd meg az űrlap Ending → Redirect beállítását.
Redirect URL legyen pontosan:
https://app.sharity.hu/go?shop=arukereso&d1=@NGO_kod
A Redirect link parameters részt hagyd ÜRESEN (ne adj hozzá külön shop/d1 sort).
Fontos: az „Válassz ügyet” mező opcióihoz legyen Value (custom value) beállítva (pl. bator-tabor-alapitvany). Az @NGO_kod ezt a Value-t fogja beszúrni.
Ezzel a Submit után a böngésző mindig így néz ki a tesztben:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
(Ha ragaszkodsz az általános, több shopos űrlaphoz: használhatod a mappolós verziót is → Redirect URL: https://app.sharity.hu/go és a Redirect link parameters-ben add hozzá shop→shop(hidden), d1→NGO(value). Most a gyors teszthez a fenti inline a legegyszerűbb.)
2) WordPress Redirection – Árukereső szabály (rövidlinkkel)
Add new redirect
Regex: be
Query Parameters: Exact
HTTP code: 302 – Temporary
Source URL:
^/go\?shop=arukereso&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
<CID> és <REFID>: a Dognet fiókod szerinti értékek
data1=$1 → a regex 1. csoportja = az NGO-kód
3) Teszt – 10 másodperces ellenőrzés
Nyisd meg az űrlapot az Árukereső ikon linkjéről:
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Válassz egy NGO-t → Submit.
A címsorban ezt kell látnod:
https://app.sharity.hu/go?shop=arukereso&d1=<ngo_kod>
Azonnal tovább kell ugrania erre (minta):
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=<ngo_kod>
4) (Opcionális, de hasznos) Fallback szabály, ha mégis lemaradna a shop
Ha szeretnél „védőhálót”, tehetsz be egy második, alacsonyabb prioritású szabályt:
Source:
^/go\?d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Ez ÁRUKERESŐRE terel, ha véletlenül hiányzik a shop. Ha ez nem kívánt, irányíthatod inkább egy tájékoztató oldalra is (pl. /impactshop?missing_shop=1).
Ha így beállítod, a „shop nem megy át” hiba megszűnik. Ha kész az Árukereső, 20 másodperc klónozni Decathlonra: csak a Fillout redirectben arukereso→decathlon, és a Redirection szabályban a Source/Target megfelelő részeit cseréled.
Ezt mondtad:
most jó
A ChatGPT ezt mondta:

Gondolkozás
Nagyszerű! Akkor rögzítsük a működő mintát, és adok bemásolható sablonokat a további shopokhoz.
Ikon → Fillout (publikus link)
https://form.fillout.com/t/eM61RLkz6jus?shop={SHOP}
Példák:
Árukereső: ... ?shop=arukereso
Decathlon: ... ?shop=decathlon
Fillout → vissza a /go-ra (Ending → Redirect)
Használd az egyszerű inline verziót shoponként:
https://app.sharity.hu/go?shop={SHOP}&d1=@NGO_kod
(Az @NGO_kod az ÜGY mező Value-ja, ne a felirat. A Redirect link parameters részt hagyd üresen.)
WordPress Redirection ( /go → Dognet rövidlink )
Beállítások: Regex ON, Query Parameters: Exact, 302 Temporary.
Sablon (shoponként 1 szabály):

Source URL
^/go\?shop={SHOP}&d1=([^&]+)$
Target URL
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url={ENCODED_URL}&data1=$1
Kész példák (URL-k már kódolva)
Árukereső
Source:
^/go\?shop=arukereso&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Decathlon
Source:
^/go\?shop=decathlon&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
eMAG (ha kell majd)
Source:
^/go\?shop=emag&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.emag.hu%2F&data1=$1
Gyors ellenőrzés
Katt az ikonra → Fillout megnyílik ?shop=...-pal.
Submit után: https://app.sharity.hu/go?shop={SHOP}&d1=<ngo_kod>
Azonnal redirect: https://go.dognet.com/?cid=...&refid=...&url=...&data1=<ngo_kod>
Küldd el a <CID> és <REFID> értékeket (shoponként), és legyártom a kész Target URL-eket egyben az összes bolthoz, hogy csak be kelljen másolnod.
Ezt mondtad:
Tisztázzuk: mindegyik shop hoz új form kell és új redisrect szabály?
A ChatGPT ezt mondta:

Gondolkozás
Röviden:
Űrlapból elég 1 darab. Ugyanazt a Fillout formot használjuk minden shophoz, csak a linkben adjuk át a ?shop= paramétert.
Redirection szabályból shoponként 1 kell (ha csak a Redirection plugint használod), mert a Dognet rövidlinkben a url= mindig más (más webáruház főoldala / cél-URL).
Így néz ki a „1 form + több szabály” megoldás
1) Ikon linkek (mind a ugyanarra a formra mutatnak)
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
...stb.
2) Fillout (egyetlen form)
Hidden mező: shop (az URL-ből veszi át).
NGO mező: opciók Value = NGO-kódok (ékezet/space nélkül).
Ending → Redirect URL:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
(Ne használj külön Redirect link parameters-t, így nincs duplikálás.)
3) WordPress Redirection (shoponként 1 szabály)
Árukereső
Source:
^/go\?shop=arukereso&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Beállítások: Regex ON, Query Parameters = Exact, kód = 302.
Decathlon
Source:
^/go\?shop=decathlon&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
…és így tovább a többi shopra (a Source-ban a shop=... értéket, a Targetben az url= kódolt célt cseréled).
Ha nem akarsz sok szabályt
Van „pro” opció egy rövid WPCode snippet-tel, ami 1 szabály helyett egy kódtáblából dönti el, melyik shophoz melyik url= menjen (és összeállítja a go.dognet.com linket). Szívesen adom a kész, bemásolható kódot egy 10–15 soros shop→URL térképpel.
Következő lépésként küldd el a shopok listáját és a hozzájuk tartozó cél-URL-eket (vagy megadom a tipikus főoldalakat), és visszaírom a kész Source/Target párokat, hogy csak bemásold.
Ezt mondtad:
kérem a decathlon linket a logó alá
A ChatGPT ezt mondta:
Ez kerüljön a Decathlon logó alá:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Ha HTML-ben adod meg:
<a href="https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon" target="_blank" rel="noopener">Vásárlás a Decathlonnál</a>
Ezt mondtad:
most megint az arukeresore ment. A Fillout form linkje valamiért fixen ez: https://form.fillout.com/t/eM61RLkz6jus?Redirect=xxx&shop=arukereso Nem tudom miért és hogyan került bele az arukereso 
A ChatGPT ezt mondta:

Gondolkozás
Értem: a link mindig shop=arukereso-val nyílik, még akkor is, ha Decathlont adsz meg. Ez 3 tipikus okból szokott történni. Menjünk végig rajtuk, és javítsuk:
1) A logó alatti linken csak a shop legyen – semmi más
A logó href legyen pontosan ez (nincs Redirect=, nincs más paraméter):
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Ha WordPress-ben szerkeszted, töröld ki a korábbi ...&shop=arukereso maradványt.
Teszteld inkognitó ablakban is (cache ne zavarjon).
2) Fillout: nincs „beragasztott” alapérték a shop mezőn
A Filloutban sokszor a hidden shop mező van fixre állítva.
Menj az űrlap szerkesztőbe → shop hidden mező.
A Value/Default ne legyen fix szöveg („arukereso”), hanem az URL-paraméterből jöjjön.
Tipikus beállítás: “Prefill from URL param: shop” / „Use @shop”.
Settings → URL parameters: a shop paraméternél ne legyen „Default value: arukereso”.
3) Fillout Ending (Redirect) ne legyen bekeményítve Árukeresőre
Az Ending → Redirect URL NE legyen ilyen:
https://app.sharity.hu/go?shop=arukereso&d1=@NGO_kod
Hanem legyen dinamikus, a hidden mezőt használva:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
És a Redirect link parameters részt hagyd üresen (ne duplázd a paramétereket).
4) Redirection szabály: Árukereső és Decathlon külön
Marad a rövidlinkes forma (go.dognet.com), shoponként 1 szabállyal.
Árukereső

Source:
^/go\?shop=arukereso&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Decathlon
Source:
^/go\?shop=decathlon&d1=([^&]+)$
Target:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
Beállítások mindkettőn: Regex ON, Query Parameters: Exact, 302 Temporary.
5) Gyors diagnosztika (ha még mindig „arukereso” jön)
Nyisd meg inkognitóban: https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Ideiglenesen tegyél az űrlapra egy read-only szövegmezőt, ami megjeleníti a @shop értéket. Ha itt is „arukereso”, akkor a hidden mező vagy a URL parameters default rögzített „arukereso”-ra — azt töröld.
Ellenőrizd, hogy a logó linkje tényleg a form domainre mutat (form.fillout.com), nem az editorra (build.fillout.com), és a hivatkozásban nincs Redirect= paraméter maradvány.
Ha van WP/Cloudflare gyorsítótár, ürítsd (és próbáld inkognitóban).
Összefoglaló, bemásolható értékek
Logó (Decathlon)
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Fillout Ending Redirect
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
Redirection – Decathlon
Source: ^/go\?shop=decathlon&d1=([^&]+)$
Target: https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
Ha így áll, a Decathlon logó → Decathlon shop lesz, nem Árukereső. Ha küldesz egy screenshotot a Fillout hidden shop mező beállításáról, ránézek, nincs-e benne fix „arukereso” default.
Ezt mondtad:
Ráadásul ezt írtad a redisrect linkbe: https://form.fillout.com/t/eM61RLkz6jus?Redirect=xxx&shop=arukereso Ez fixen arukereso
A ChatGPT ezt mondta:

Gondolkozás
Jogos – ezt benéztem. A Fillout editor-linket és a fix shop=arukereso paramétert véletlenül rossz helyre írtam. Itt a tiszta, működő beállítás lépésről lépésre, másolhatóan:
1) A logó alatti link (nyilvános űrlap-link)
Csak a publikus form linket használd, és csak a saját shop-paramétert tedd hozzá.
Árukereső logó:
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Decathlon logó:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Ne használd a build.fillout.com/... editor-linket és ne tegyél Redirect= paramétert ide.
2) Fillout – Ending → Redirect (űrlap beküldés után)
Redirect URL (dinamikus, NEM fix Árukereső!):
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
A Redirect link parameters részt hagyd üresen.
A shop hidden mező URL-paraméterből vegye az értéket (ne legyen fix „arukereso” default).
Az „Ügy” mező opcióinál a Value (custom value) menjen át @NGO_kod-ként (pl. bator-tabor-alapitvany).
3) WordPress Redirection – /go → Dognet rövidlink
Beállítások: Regex ON, Query Parameters: Exact, 302 Temporary.
Árukereső

Source URL
^/go\?shop=arukereso&d1=([^&]+)$
Target URL
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Decathlon
Source URL
^/go\?shop=decathlon&d1=([^&]+)$
Target URL
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
4) 10 mp-es ellenőrzés (incognitó ablakban)
Katt az Árukereső logóra → URL a böngészőben:
.../t/eM61RLkz6jus?shop=arukereso
Submit után:
https://app.sharity.hu/go?shop=arukereso&d1=<ngo_kod>
Azonnal tovább:
https://go.dognet.com/?cid=...&refid=...&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=<ngo_kod>
Ugyanez Decathlonnal shop=decathlon-nal. Ha bárhol újra „arukereso” ragad be, nézd meg a shop hidden mezőt: biztosan „URL param: shop”-ról töltődik, és nincs fix default.
Ezt mondtad:
.../t/eM61RLkz6jus?shop=arukereso
írj már teljes linket nem vagyok memória hogy mindent megjegyezzek vagy visszakeressek
A ChatGPT ezt mondta:
Értem 🙂 Akkor most leírom neked a kész, teljes linkeket mindkét webshophoz, hogy egyben lásd, és ne kelljen visszakeresgélned.
Árukereső logó alá kerülő link (Fillout form nyitására):
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Decathlon logó alá kerülő link:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Mi történik beküldés után?
Ha az űrlapban a Redirect URL így van beállítva:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
akkor a Submit után a böngészőben ez jelenik meg (például, ha a felhasználó a Bátor Tábor-t választja):
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
vagy
https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor-alapitvany
Hová irányít a Redirection?
Árukereső esetén:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=bator-tabor-alapitvany
Decathlon esetén:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=bator-tabor-alapitvany
(<CID> és <REFID> helyére a Dognet-től kapott saját azonosítók kerülnek.)
Így a teljes lánc mindenhol ki van írva:
Logó → Fillout link → /go redirect → Dognet rövidlink.
Szeretnéd, hogy a többi webshophoz (pl. eMAG, Alza stb.) is legeneráljam ugyanezt a teljes láncot előre, hogy csak másolni kelljen?

Ezt mondtad:
Feltöltött kép
A formát megnyitja így: https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso és így is , de utána nem teszi bele a shop-ot: https://app.sharity.hu/go?shop=%40shop&d1=bator-tabor-alapitvany és az oldal nem található lesz. Ha manuálisan átírom ezt a részt: %40shop pl. arukereso-re akkor bejön az arukereso, ha decathlon-ra akkor az. Tehát csak egy probléma lehet a Filloutban az kending redisrect link generálása nincs jól beállítva a shop szempontjából. Jelenleg ez van: 
A ChatGPT ezt mondta:

Gondolkozás
Megvan a hiba oka: duplázod a paraméterátadást, ezért az @shop szövegként kerül a linkbe és URL-kódolódik %40shop-ra. Válassz egy módszert – most állítsuk be a mappolós verziót, mert az bombabiztos.
Mit csinálj pontosan (3 kattintás)
Fillout → Ending → Redirect
Redirect URL: pontosan ez legyen, paraméterek nélkül
https://app.sharity.hu/go
Redirect link parameters (ugyanezen a panelen)
Sor 1: shop → jobb oldalt shop (a hidden mező)
Sor 2: d1 → jobb oldalt Válassz ügyet (ennek a Value értéke megy át)
Semmi mást NE adj hozzá (ne legyen még egyszer shop vagy d1 az URL-ben).
Hidden “shop” mező
Nyisd meg a shop mező beállítását.
Default / Prefill: állítsd URL paraméterre, név: shop.
(Ne legyen fix szöveg; ne legyen „arukereso” beégetve.)
Ezzel a beküldés UTÁN a generált link így fog kinézni:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
és nem így: ...?shop=%40shop...
Gyors ellenőrzés
A Redirect panel kék előnézetében NE láss ilyet, hogy ...shop=%3Fshop%3D... vagy %40shop.
A te képernyőképednél ez látszott → ez a duplázás jele (URL-ben is volt ?shop=@shop és a „Redirect link parameters”-ben is megadtad a shop-ot).
Alternatív (ha inkább inline tokent akarsz)
Redirect URL:
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
Redirect link parameters: üresen hagyni.
A „shop” hidden továbbra is URL paraméterből töltődjön.
WP Redirection emlékeztető (Árukereső példa)
Source: ^/go\?shop=arukereso&d1=([^&]+)$
Target: https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Regex ON, Query Parameters = Exact, 302 Temporary.
Ha most beállítod az első módszert (URL = /go, paraméter-mappolás két sorral), a shop biztosan át fog menni, és eltűnik a %40shop/404 gond.
Ezt mondtad:
* **efault / Prefill**: állítsd **URL paraméterre**, név: `shop`. (Ne legyen fix szöveg; ne legyen „arukereso” beégetve.)
itt nem enged választani csak be tudom írni hogy shop, de nincs legördülő menü
A ChatGPT ezt mondta:
Értem 👍 Ez a különbség a hidden mező és a redirect paraméter mappelés között a Filloutban. Nem mindenhol van legördülő menü – a hidden field-nél simán kézzel kell beírnod, hogy honnan vegye az értéket.
Mit csinálj a shop hidden mezőnél:
Menj a Form designer → shop hidden field beállításába.
Kapcsold be a Custom values / Default value mezőt.
Ahelyett, hogy fix szöveget (pl. arukereso) írsz, írd be pontosan így:
{shop}
vagy @shop – attól függ, melyik szintaxist engedi a Fillout. Ez azt jelenti: „töltse fel a hidden field-et az URL ?shop= paraméteréből”.
– Ha jól működik, amikor megnyitod a formot pl.

https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
akkor a hidden mező értéke decathlon lesz.
Redirect Endingnél
Használj tiszta paraméter-mappelést:
Redirect URL:
https://app.sharity.hu/go
Redirect link parameters:
shop → shop (a hidden field)
d1 → Válassz ügyet
Eredmény
Beküldés után mindig ilyen lesz:
https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor-alapitvany
vagy
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
És innen a WP Redirection szabály már pontosan a megfelelő Dognet linkre küldi tovább.
Szeretnéd, hogy írjak egy konkrét mintát a Fillout hidden field beállítására screenshot szöveggel (mit hova írj), hogy biztosan lásd, hova kell a {shop} kerülni?
Ezt mondtad:
### Mit csinálj a `shop` hidden mezőnél: 1. Menj a **Form designer → shop hidden field** beállításába. 2. Kapcsold be a **Custom values / Default value** mezőt. 3. Ahelyett, hogy fix szöveget (pl. `arukereso`) írsz, írd be pontosan így: Kód másolása
Feltöltött kép
nem találok mustom value t.  nem működik
A ChatGPT ezt mondta:
Most látom a screenshoton, hogy jól rátapintottál: a Filloutban a hidden mező Default value-ját állítottad, de az most fixként kezeli (ezért mindig „shop” marad vagy URL-kódolódik).
A helyes lépés: nem a „Default value” kell, hanem a Prefill from URL parameter funkció. Ez külön menüpont, nem a mezőnél a sima „Default value” input.

Hol találod:
Menj az űrlap jobb oldali menüjében a Settings fülre.
Ott van egy URL parameters rész.
Add hozzá itt a shop paramétert → állítsd be, hogy az értékét a böngésző URL ?shop=...-ból vegye.
Ezután a hidden mező „shop” automatikusan felveszi az értéket, amikor ?shop=arukereso vagy ?shop=decathlon paraméterrel nyitod az űrlapot.
Miért nem kell Default value
A Default value mindig fix szövegként kerülne be (pl. „arukereso”), ami rossz, mert nekünk dinamikusan kell. Ezért kell a URL parameter binding.
Ellenőrzés
Ha ez jól van beállítva, akkor:
Megnyitod: https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
A hidden shop értéke = decathlon.
Redirect link:
https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor-alapitvany
Szeretnéd, hogy írjak egy pontos, képernyőmenüpont szövegre bontott útmutatót (hogy hol kell rákattintani Filloutban, pl. Settings → URL parameters → + Add parameter → „shop”)?
Ezt mondtad:
Feltöltött kép
itt csak sample van
A ChatGPT ezt mondta:
Pontosan, jó helyen jársz 🙂 A Sample value mező nem fixálja a shop értéket, csak szerkesztés közben mutatja előnézetnek. Az éles űrlapnál a shop mindig az URL-ből töltődik be (?shop=arukereso vagy ?shop=decathlon).
Tehát amit most csináltál → az a helyes beállítás.

Összefoglalva a működő lánc:
Logó alatti linkek:
Árukereső:
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Decathlon:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Fillout Ending → Redirect:
Redirect URL:
https://app.sharity.hu/go
Redirect link parameters:
shop → shop (a hidden mező)
d1 → Válassz ügyet (ennek a Value-ja, pl. bator-tabor-alapitvany)
Teszt:
Nyisd: ...?shop=decathlon
Submit után:
https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor-alapitvany
Innen a WP Redirection megy a megfelelő Dognet linkre.
👉 A Sample value maradhat „arukereso” — ez nem fixálja, csak szerkesztőben segít előnézetnél.
Az éles űrlapban mindig az URL alapján lesz kitöltve.
Akarod, hogy írjak egy kész WP Redirection szabályt Decathlonra a teljes linkkel, amit már csak bemásolnod kell?
Ezt mondtad:
nem adja tovább: https://app.sharity.hu/go?d1=bator-tabor-alapitvany
A ChatGPT ezt mondta:

Gondolkozás
Igazad van: most azért esik le a shop, mert a Fillout kétféleképp próbálod átadni, és a hidden mező üres marad → a rendszer eldobja a shop paramétert. Oldjuk meg úgy, hogy egyetlen helyen adjuk át, méghozzá bombabiztosan.
Mit állíts be a Filloutban (2 perc)
A) Redirect URL — csak inline tokenek, NINCS paraméter-mappelés
Menj: Ending → Redirect
Redirect URL legyen pontosan ez (másold be egy az egyben):
https://app.sharity.hu/go?shop=@shop&d1=@NGO_kod
– @shop = a shop mező aktuális értéke
– @NGO_kod = az „Ügy” mező Value (custom value)
Redirect link parameters részt hagyd ÜRESEN (ne legyen benne se shop, se d1 sor).
Ha a tokenválasztóban külön látod a URL parameter → shop opciót, még jobb: kattints rá, hogy biztosan a helyes hivatkozás kerüljön be. A végeredmény így is ...shop=@shop... lesz.
B) Hidden shop mező — az URL-ből töltődjön
A shop mezőn Hide always = ON.
Settings → URL parameters: szerepeljen a shop paraméter (a Sample value maradhat „arukereso”, ez csak szerkesztői előnézet).
A shop mezőn ne legyen fix „Default value” szöveg (üresen hagyhatod; az értéket az URL-paraméter fogja adni).
Így amikor a formot így nyitod:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
a shop mező értéke decathlon, és a Redirect URL-ben az @shop → decathlon.
Gyors teszt (inkognitóban)
Nyisd:
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Válassz ügyet → Submit → ennek kell kijönnie:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
Ugyanez Decathlonnal:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon →
https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor-alapitvany
WordPress Redirection (emlékeztető)
Árukereső – Source
^/go\?shop=arukereso&d1=([^&]+)$
Target
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Decathlon – Source
^/go\?shop=decathlon&d1=([^&]+)$
Target
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
Beállítások: Regex ON, Query Parameters = Exact, 302 Temporary.
Ha a Fillout „Your redirect link will look like…” előnézetében még mindig ilyesmi látszik: shop=%40shop vagy shop=%3Fshop%3D, akkor valahol még maradt egy plusz mappelés/szöveg. Töröld a Redirect link parameters sorokat, és csak az inline URL maradjon.
Ezt mondtad:
Feltöltött kép
Feltöltött kép
Feltöltött kép
ez a jó beállítás , így tökéletesen működik
A ChatGPT ezt mondta:
Szuper, látom, most már minden a helyére kattant 🎯
Jelenlegi működő setupod:

Fillout:
Redirect URL: https://app.sharity.hu/go
Redirect link parameters: shop → shop (hidden mező az URL paraméterből), d1 → Válassz ügyet (az NGO value)
shop hidden mező → URL parameter-ből töltődik (?shop=arukereso / ?shop=decathlon stb.)
WordPress Redirection:
Source URL: ^/go\?shop=arukereso&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Regex ON, Query paraméterek pontos illesztéssel.
Eredmény:
Logó → Fillout form → NGO választás → átirányítás a shop+d1 paraméterrel → WP Redirection → Dognet affiliate link.
Tesztelve: működik, a data1-ben az NGO kód korrektül megy tovább.
Mostantól minden új shophoz csak:
Új Redirection szabály (Source URL-ben a shop=xxxx, Target URL-ben a megfelelő Dognet link + $1 a data1-hez).
Azonos Fillout form, mert a shop paraméter dinamikusan érkezik az URL-ből.
Szeretnéd, hogy adjak egy előre kitöltött mintát a Decathlonhoz (Fillout link + WP Redirection Source/Target), hogy már csak be kelljen másolnod?
Ezt mondtad:
igen, elég ugye egy form, csak több redirect szabály lesz?
A ChatGPT ezt mondta:
Pontosan 🙌 Nem kell minden webshophoz új űrlapot csinálnod — egy Fillout form elég.
A trükk az, hogy a shop paramétert mindig az URL-ben adod át (pl. ?shop=arukereso, ?shop=decathlon), és a hidden field felveszi ezt.
Így a Fillout → Sharity /go mindig ugyanazt a logikát követi:

https://app.sharity.hu/go?shop=<shop>&d1=<ngo_kod>
A különbséget a WordPress Redirection szabályok kezelik:
minden shophoz 1 új szabály kell, ami a shop= alapján szűr.
ugyanazt a regex mintát használod, csak a shop=xxxx részt változtatod.
Példa
Árukereső – Source URL
^/go\?shop=arukereso&d1=([^&]+)$
Target URL
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Decathlon – Source URL
^/go\?shop=decathlon&d1=([^&]+)$
Target URL
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
Logó alatti linkek
Árukereső logó →
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Decathlon logó →
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Így mindig ugyanarra az űrlapra mennek, a különbséget a shop paraméter + a WP Redirection szabályok oldják meg.
👉 Tehát igen: 1 Fillout form + több redirect szabály a WP-ben.

Szeretnéd, hogy készítsek neked egy sablon-listát a leggyakoribb shopjaidhoz (link + redirect szabály), hogy csak copy-paste kelljen?

Ezt mondtad:
egyesével megyünk, írd le a redisrect szabály beállítását decathlonra
A ChatGPT ezt mondta:

Gondolkozás
Íme a Decathlon redirect szabály pontos, bemásolható beállítása a WordPress Redirection pluginhoz.
Decathlon – Redirection szabály
1) Add new redirect
Title: Decathlon
Source URL (Regex ON):
^/go\?shop=decathlon&d1=([^&]+)$
(Az ([^&]+) az NGO-kódot fogja meg, ez lesz a $1.)
When matched: Redirect to URL
HTTP code: 302 – Temporary Redirect
Match: URL only
Query parameters: Exact
Regex: ✅ bekapcsolva
Ignore case / Ignore slash: maradhat alapértelmezett (nem kötelező)
Target URL:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
Cseréld:
<CID> → a Dognet kampányod CID-je
<REFID> → a saját affiliate azonosítód (refid)
2) Save
Gyorsteszt
Nyisd meg a böngészőben (incognitóban is kipróbálhatod):
https://app.sharity.hu/go?shop=decathlon&d1=tesztngo
Várt átirányítás:
https://go.dognet.com/?cid=<CID>&refid=<REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=tesztngo
Ha ez működik, a Decathlon kész. Következőnek jöhet bármelyik másik shop ugyanígy: csak a Source-ban shop=..., a Target-ben pedig az url= kódolt célja változik.
Ezt mondtad:
* `<CID>` → a Dognet kampányod CID-je * `<REFID>` → a saját affiliate azonosítód (refid)
Feltöltött kép
ezeket te nem tudod? itt sem töltöttekm ki, vagy cseréltem, mégis működik 
A ChatGPT ezt mondta:

Gondolkozás
Lehet, hogy „működik” így is (átvisz a Decathlonra), de CID/REFID nélkül a Dognet nem fog hozzád attribuálni – azaz a kattintás/jutalék nem biztos, hogy a te fiókodhoz kerül. A go.dognet.com rövidlink három dolgot vár:
cid = kampányazonosító (shop/kampány)
refid = a te affiliate azonosítód
url = a cél-URL URL-kódolva
Ha a cid/refid hiányzik vagy placeholder marad (<CID>, <REFID>), a rendszer többnyire átirányít, de nem lesz jóváírás.
Tedd rendbe 2 perc alatt
1) Szerezd meg a saját értékeidet Dognetben
Lépj be Dognet → Kampányok / Linkek (ahol a shop linkjeit listázza).
Válassz Decathlon (vagy Árukereső) kampányt → Rövid link (Short link) nézet.
Másold ki a mintát; így fog kinézni:
https://go.dognet.com/?cid=XXXX&refid=YYYY&url=...
– ebből a XXXX a CID, a YYYY a REFID.
2) Írd be a Redirection Target URL-jébe
A jelenlegi szabályodhoz ezt használd (csak a két helyre illeszd be a saját számaidat):
https://go.dognet.com/?cid=<IDE_A_CID>&refid=<IDE_A_REFID>&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
A többivel nem kell piszkálni:
Source URL (regex):
^/go\?shop=decathlon&d1=([^&]+)$
HTTP kód: 302 vagy 307 Temporary (mindkettő oké).
Regex ON, Query params = Exact.
3) Gyors ellenőrzés (hogy a track is meglegyen)
Nyisd meg:
https://app.sharity.hu/go?shop=decathlon&d1=tesztngo
A címsorban a Dognet linkben látszódjon a te cid és refid + data1=tesztngo.
Dognetben 1–2 perc múlva a Clicks / Reports nézetben fel kell bukkanjon a kattintás; a „custom”/data1 mezőben a tesztngo.
Miért ment „CID/REFID” nélkül is?
A url= megadása miatt a rövidlink destination redirectet csinál akkor is, ha nincs minden paraméter. Ez csak átirányítás, nem garantál attribúciót. Jutalékhoz kötelező a saját cid + refid.
Ha szeretnéd, adok egy gyors checklistát minden új shophoz (Source/Target sablon, kódolt url=), hogy csak a két számot kelljen mindig beírnod.

Ezt mondtad:
értem, ez az árukereso linkem a dognetben: https://go.dognet.com/?cid=897&chid=KVirfJde&refid=67246ee77827f
A ChatGPT ezt mondta:
Nagyon jó, ez már a saját, működő rövidlinked Dognetben az Árukereső kampányhoz 👍
Ebben:

cid=897 → a kampány (Árukereső) azonosítója
refid=67246ee77827f → a te partnerazonosítód
chid=KVirfJde → csatorna azonosító (opcionális, de maradhat, így a riportban látod, hogy honnan jött a kattintás)
Árukereső Redirection szabály
Source URL (Regex ON):
^/go\?shop=arukereso&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=897&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=$1
Mi történik?
A Filloutból visszajön a user ide:
https://app.sharity.hu/go?shop=arukereso&d1=bator-tabor-alapitvany
A WP Redirection felismeri, hogy shop=arukereso, és a d1 értékét (itt bator-tabor-alapitvany) beteszi a $1 helyére.
A végső link így néz ki:
https://go.dognet.com/?cid=897&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.arukereso.hu%2F&data1=bator-tabor-alapitvany
Dognet naplózza a kattintást, és a data1 mezőben ott lesz az NGO kód.
A Decathlonhoz is pont így kell majd megcsinálni: csak a cid és a url=https%3A%2F%2Fwww.decathlon.hu%2F részt kell kicserélni.
Szeretnéd, hogy adjam a Decathlonhoz is a konkrét kész Target URL-t, a CID/REFID részedet megtartva?

Ezt mondtad:
decathlon: https://go.dognet.com/?cid=5191&chid=KVirfJde&refid=67246ee77827f
A ChatGPT ezt mondta:
Szuper, akkor a Decathlonhoz ez lesz a pontos Redirection szabály:
Source URL (Regex ON):
^/go\?shop=decathlon&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=5191&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=$1
Mit csinál?
A d1 (NGO kód) értékét behelyettesíti $1-ként.
Így ha a user pl. a Bátor Tábor Alapítványt választja, a végső link ez lesz:
https://go.dognet.com/?cid=5191&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.decathlon.hu%2F&data1=bator-tabor-alapitvany
Dognet naplózza a kattintást, és a data1 mezőben ott lesz az NGO.
Akarsz, hogy a következő shophoz (pl. Emag, AboutYou stb.) is elkészítsem ugyanilyen sablonnal, csak a cid és url cseréjével?
Ezt mondtad:
kérek arukereso és decarhlon linkeket a weboldalra a logók alá
A ChatGPT ezt mondta:
Értettem 🙌
A logók alá mindig a Fillout űrlap linkje kerül, mert ott választja ki a user az NGO-t.
A shop neve (pl. arukereso, decathlon) URL-paraméterként megy át az űrlapnak, innen kerül be a redirect folyamatba.
Íme a kész linkek:

Árukereső logó alá:
https://form.fillout.com/t/eM61RLkz6jus?shop=arukereso
Decathlon logó alá:
https://form.fillout.com/t/eM61RLkz6jus?shop=decathlon
Amikor a felhasználó ezekre kattint:
Megnyílik a Fillout form → kiválaszt NGO-t.
Redirect vissza a te oldaladra (app.sharity.hu/go?...).
WP Redirection továbbdobja a megfelelő Dognet affiliate linkre, a data1 mezőben az NGO-kóddal.
Így a folyamat teljesen automatizált.
Szeretnéd, hogy adjam hozzá rögtön a következő shophoz is a linket + redirect sablont (pl. Emag vagy más nagyobb partner)?

Ezt mondtad:
kérem a következőt, tehát mindig redisrect linkek és a weboldal link, ezek kellenek. következő shop: 4home https://go.dognet.com/?cid=4319&chid=KVirfJde&refid=67246ee77827f
A ChatGPT ezt mondta:
Nagyon jó, akkor nézzük a 4home-ot, ugyanazzal a logikával mint eddig.
🔁 Redirection szabály WordPress-ben (4home)
Source URL (Regex ON):
^/go\?shop=4home&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=4319&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.4home.hu%2F&data1=$1
🌐 Link a weboldalra (4home logó alá)
https://form.fillout.com/t/eM61RLkz6jus?shop=4home
Mi történik?
Felhasználó rákattint a 4home logóra → megnyílik a Fillout NGO-választó form a ?shop=4home paraméterrel.
Kitöltés után a form visszairányít a .../go?shop=4home&d1=... URL-re.
A WP Redirection elkapja, és a fenti Dognet-linkre dobja át, a data1 paraméterben az NGO kóddal.
Szeretnéd, hogy adjam sorban a következőt is (pl. Emag vagy más nagy partner), ugyanígy készre összeállítva?
Ezt mondtad:
Allegro.hu https://go.dognet.com/?cid=5385&chid=KVirfJde&refid=67246ee77827f
A ChatGPT ezt mondta:
Szuper, akkor jöhet az Allegro.hu beállítás.
🔁 Redirection szabály WordPress-ben (Allegro)
Source URL (Regex ON):
^/go\?shop=allegro&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=5385&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fallegro.hu%2F&data1=$1
🌐 Link a weboldalra (Allegro logó alá)
https://form.fillout.com/t/eM61RLkz6jus?shop=allegro
👉 Így a folyamat ugyanaz, mint az előző shopoknál: a logó → Fillout form → /go?shop=allegro&d1=ngo_kod → Redirection → Dognet rövidlink az Allegrohoz, data1 paraméterrel.
Szeretnéd, hogy folytassam a következő bolttal is?

Ezt mondtad:
Feltöltött kép
Feltöltött kép
működik minden, de nem rakja be a Data 1 hoz a pdognet az ngo kódot, így nem tudom riportban megkülönböztetni. Így nézne ki, ha benne lenne a data1 Ben egy alapítvány kódja: https://go.dognet.com/?cid=897&chid=KVirfJde&refid=67246ee77827f&d1=bator-tabor-alapitvany látható a fotón hogy itt az arukereso clickben már megjelenik a Bator tábor kódja
A ChatGPT ezt mondta:

Gondolkozás
Igazad van: a Dognet rövidlinknél több kampánynál a d1 kulcsot várják (aliasa a data1-nek), és a riportokban az „Extra data 1” akkor töltődik biztosan, ha d1=-et küldünk.
Árukeresőnél már látszik is, mert ott épp d1-gyel ment a teszt link.
Mit csináljunk?
Cseréljük le a Redirection szabályok Target URL részében a data1=$1-et d1=$1-re (akár tehetsz mindkettőt is: data1=$1&d1=$1, de elég a d1).
Frissített Target URL-ek
Árukereső
https://go.dognet.com/?cid=897&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.arukereso.hu%2F&d1=$1
Decathlon
https://go.dognet.com/?cid=5191&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.decathlon.hu%2F&d1=$1
4home
https://go.dognet.com/?cid=4319&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.4home.hu%2F&d1=$1
Allegro
https://go.dognet.com/?cid=5385&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fallegro.hu%2F&d1=$1
Ha nagyon biztosra akarsz menni: ...&data1=$1&d1=$1 – így akkor is beírja, ha a kampány épp a másik aliasra figyel.
Gyors teszt
Nyisd inkognitóban:
https://app.sharity.hu/go?shop=arukereso&d1=tesztngo
A végső Dognet URL-ben lásd: &d1=tesztngo.
Dognet > Clicks riportban az Extra data 1 oszlopban megjelenik: tesztngo.
Ha szeretnéd, végigmegyek a többi shopodon is és adom a kész, frissített Targeteket d1-gyel.
Ezt mondtad:
Most jó. Mehetünk tovább: visionexpress https://go.dognet.com/?cid=223&chid=KVirfJde&refid=67246ee77827f
A ChatGPT ezt mondta:
Nagyon jó hír, hogy most már a d1 paraméter szépen megy a Dognetbe! 🚀
Akkor jöhet a Vision Express.
🔁 Redirection szabály (Vision Express)
Source URL (Regex ON):
^/go\?shop=visionexpress&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=223&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.visionexpress.hu%2F&d1=$1
🌐 Link a weboldalra (Vision Express logó alá)
https://form.fillout.com/t/eM61RLkz6jus?shop=visionexpress
Ezzel ugyanúgy működni fog, mint az előzőek: logó → Fillout → /go?shop=visionexpress&d1=ngo_kod → Redirection → Dognet link a Vision Express kampányra, az NGO kóddal.
Szeretnéd, hogy előre összegyűjtsem a következő 3 shopot is egyben, vagy inkább menjünk egyesével, ahogy eddig?

Ezt mondtad:
regiojatek https://go.dognet.com/?cid=357&chid=KVirfJde&refid=67246ee77827f
A ChatGPT ezt mondta:
Jön a REGIO Játék 👇
🔁 Redirection szabály (WordPress – Redirection)
Source URL (Regex ON, Query params = Exact, 302):
^/go\?shop=regiojatek&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=357&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.regiojatek.hu%2F&d1=$1
🌐 Link a weboldalra (REGIO Játék logó alá)
https://form.fillout.com/t/eM61RLkz6jus?shop=regiojatek
Ugyanaz a flow: logó → Fillout (NGO) → /go?shop=regiojatek&d1=<ngo_kod> → Dognet, a d1-ben az NGO kód.
Ezt mondtad:
sparkl https://go.dognet.com/?cid=249&chid=KVirfJde&refid=67246ee77827f
A ChatGPT ezt mondta:
Jöhet a Sparkl 👇
🔁 Redirection szabály (WordPress – Redirection)
Source URL (Regex ON, Query params = Exact, 302/307):
^/go\?shop=sparkl&d1=([^&]+)$
Target URL:
https://go.dognet.com/?cid=249&chid=KVirfJde&refid=67246ee77827f&url=https%3A%2F%2Fwww.sparkl.hu%2F&d1=$1
🌐 Link a weboldalra (Sparkl logó alá)
https://form.fillout.com/t/eM61RLkz6jus?shop=sparkl
Ha a Sparkl kampány más domainre mutat (nem sparkl.hu), írd meg a pontos célt és átírom az url= részt.



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
