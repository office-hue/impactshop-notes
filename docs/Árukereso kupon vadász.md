Árukereso kupon vadász AI

Árukereső.hu kupon-harvester koncepció

### Implementált funkciók (2025-11)
- **Merchant mapping bővítés**: induláskor opcionálisan betöltjük a Dognet `tools/dognet_programs.csv` (product_url → domain/slug/merchantId) és a CJ `tools/cj_shops.csv` (slug/domain/program_id) adatokat, és hozzáfűzzük a registry-hez. Kapcsoló: `ARUKERESO_INCLUDE_MERCHANT_FEEDS=1` (default).
- **Offer-follow mélyítés**: Playwright response/popup intercept, több selector; a productads/go.dognet nincs kizárva. Shopdomaindra csak akkor írunk át, ha nem arukereso/adhost. Debug: `ARUKERESO_DEBUG=1`, opcionális screenshot.
- **Sale event típus**: kód nélküli akciók bent maradnak `saleEvent=true`, `type=sale_event` jelöléssel; a kuponkódos találatok `type=coupon_code`.
- **Kuponoldal-index “extra” mód**: a `/kupon` `/akcio` `/kedvezmeny` oldalak elsődleges bemenetként mennek át (coupon-page harvester), gyors releváns sale_event/coupon_code találatokkal.
- **Product radar külön**: új futtatható `npx ts-node tools/arukereso-product-radar.ts --feeds <feedlista>` → `tools/out/arukereso-product-radar.json` (termék, ár, domain, kategória).
- **AI Agent Gmail Promotions endpoint**: új `GET /gmail/promotions` az api-gateway-ben, a meglévő `fetchPromotionCoupons` lib-et publikálja (admin szerep).

Árukereső API elérhetőségek és adatkinyerés

Az Árukereső.hu hivatalosan nem kínál nyilvános API-t a partnerek listájának vagy promóciós ajánlatok lekérdezésére. A partneráruházak számára léteznek API-k a konverzióméréshez és a megrendelések továbbításához (ezek a partnerportálon érhetők el), de ezek nem a kuponok vagy promóciók begyűjtésére szolgálnak ￼. Így a partnerboltok listáját és az aktuális akciókat közvetlenül a weboldalról kell kinyerni.

Az Árukereső oldalán ugyanakkor elérhető egy nyilvános partnerlista: a “Partnereink” oldalon ábécérendben felsorolva megtalálható az összes regisztrált webáruház neve és értékelése ￼ ￼. Ebben a listában minden áruházhoz tartozik egy egyedi Árukereső adatlap (pl. Media Markt Online), amelyen a bolt domainje (“Web:” link) is szerepel. Ezt az oldalt web-scrapinggel lehet felhasználni a teljes partnerlista és a hozzájuk tartozó domain nevek kinyerésére. Mivel több ezer partner szerepel a listában, érdemes automatizáltan bejárni vagy lapozni az oldalt (szükség esetén a betöltést görgetéssel), és kiolvasni az egyes boltok profiloldaláról a domain URL-t. Így a shops_registry.json bővíthető az összes Árukereső-partner domainnel, illetve opcionálisan az Árukereső boltazonosítójával.

Fontos, hogy az új rendszer semmiképpen se írja felül a meglévő adatokat: a shops_registry-be a domain szintű integráció azt jelenti, hogy az egyes shopok domainje mellé felvesszük, hogy elérhetőek az Árukereső affiliate-en keresztül is. Például, ha egy bolt nincs benne egyik meglévő hálózatban sem (Dognet, CJ, TradeTracker), de az Árukereső partnerei közt szerepel, akkor a domain-jéhez társíthatunk egy jelzést vagy azonosítót, hogy Árukeresőn keresztül monetizálható.

Hasonló Árukereső-alapú kupon-harvesterek

Léteznek olyan magyar kedvezmény- és kuponoldalak, amelyek már gyűjtenek Árukereső promóciókat. Példaként a NapiKuponok.hu említhető, ahol külön oldalon listázzák az Árukereső aktuális akcióit ￼ ￼. Itt látható, hogy az Árukereső időszakos kampányai (pl. “Őszi Kiárusítás”, Black Friday akciók, kategória-specifikus leárazások) fel vannak sorolva, gyakran “akár X% kedvezmény” formában. Ezek a site-ok valószínűleg az Árukereső affiliate programját használják: az “Kérem a kedvezményt” gombok olyan hivatkozások, melyek az Árukeresőre vezetnek, onnan pedig a konkrét termékekre vagy boltokhoz lehet tovább navigálni. A NapiKuponok példája mutatja, hogy létezik igény és megoldás az Árukereső promóciók begyűjtésére és publikálására (a citeált oldalon például 20% kedvezmény egy Google Pixel telefonra ￼). Hasonló gyűjtőoldalak még a Picodi, Kuponkodok.hu, Kuplio stb., amelyek szintén listáznak Árukereső kedvezményeket, bár ezek sokszor ugyanazokat az akciókat tükrözik.

Maga az Árukereső affiliate programja a Dognet hálózaton fut ￼, tehát az ImpactShop számára is hozzáférhető, ha partnerként regisztrálnak. A Dognet kampány oldalán látható, hogy az Árukereső engedélyezi a kupon- és promóciós oldalak részvételét (a “Permissions” közt szerepel a “Coupon sites” engedélyezése) ￼. Emellett a hirdető biztosít promóciós elemeket, például XML feedet és kuponajánlatokat a partnereknek ￼. Ez arra utal, hogy a Dognet felületén vagy API-ján keresztül is lekérhetők az Árukereső aktuális akciói, kuponjai. Érdemes ellenőrizni a Dognet partnerfelületén az XML feed szekciót: ha ott elérhető az Árukereső kampányhoz tartozó kupon/adat feed, az a legstabilabb forrás a promóciók automatizált begyűjtéséhez. Ha a Dognet biztosít JSON/XML feedet a kampányok kedvezményeiről, azt integrálhatjuk a meglévő harvester workflow-ba (hasonlóan ahhoz, ahogy a többi hálózat esetében történik).

Technikai megoldás a promóciók és kuponok gyűjtésére

Promóciók azonosítása: Az Árukereső.hu-n jellemzően időszakos promóciós oldalak működnek (pl. Black Friday külön oldal, tematikus akciók, szezonális kiárusítások). Ezek a promocio.arukereso.hu aldomain alatt jelennek meg (pl. a Black Friday oldalon kategóriánként csoportosítva listázzák a leárazott termékeket ￼ ￼). A harvester megoldásnak figyelnie kell az ilyen promóciós aloldalakat. Technikailag két fő módszer kínálkozik:
	•	Web scraping az Árukereső promóciós oldalairól: Playwright segítségével automatizálva betölthetjük a promóciós oldalakat, és kinyerhetjük az akciók leírását. Például a Black Friday oldalról kiolvasható, hogy mely kategóriákban hány %-os kedvezmények vannak, és akár a konkrét terméknevek is. Ezt azonban össze kell sűríteni “kupon” formátumú információvá (pl. “Árukereső Black Friday – akár 50% kedvezmény rengeteg termékre” stb.). A promóciós oldalak URL-jeit az Árukereső valószínűleg előre kommunikálja (pl. a főoldalon banner, vagy a Dognet hírlevelekben). A scraper beállítható, hogy bizonyos időközönként ellenőrizze a gyakori kampányidőszakokban (pl. Black Friday, karácsony) a promocio.arukereso.hu alatti új oldalakat, vagy egy központi “akciók” listát, ha van ilyen. (Megjegyzés: A konkurens kuponoldalak figyelése is segíthet észrevenni új Árukereső-akciókat.)
	•	Adatgyűjtés az affiliate hálózaton keresztül: Ahogy fent említettük, a Dogneten belül az Árukereső kampányhoz tartozhat strukturált adat (pl. kuponlista vagy promóciók listája). Ha ez elérhető, akkor az ImpactShop meglévő harvester moduljába érdemes integrálni. Például, a Dognet “sales promotions” listája tartalmazhatja az Árukereső aktuális akcióit is (ha nem is került felsorolásra nyilvánosan, a partner API-n vagy feeden keresztül hozzáférhető lehet). Ennek előnye, hogy a hirdető által közvetlenül megadott leírásokat, érvényességi időt stb. kapunk. A NapiKuponok.hu-n látott példák alapján az akciókhoz gyakran tartozik lejárati dátum is (pl. “Érvényes: 2025. dec. 1.” a Black Friday kedvezményeknél ￼, vagy dec. 31. a karácsonyig tartó akcióknál). Ezeket az információkat a harvester be tudja emelni, így a kupon-adatbázisban az Árukereső promóciók is rendelkeznek érvényességi intervallummal.

Domain-szintű integráció: Miután megvan a partnerek domain listája és az akciók, a következő lépés ezek összekapcsolása a meglévő kupon-adatbázissal. Az ImpactShop shops_registry.json feltehetően minden bolthoz tartalmaz meta-adatokat, pl. mely hálózatban érhető el kupon. Ide új attribútumot vezethetünk be (vagy meglévőt bővíthetünk) az Árukereső elérhetőség jelzésére. Például {"domain": "mediamarkt.hu", "dognet": true, "cj": false, "tradetracker": false, "arukereso": true} vagy az Árukereső kampány azonosítója. Így a harvester tudni fogja, hogy ha egy adott bolt kapcsán keres kuponokat, az Árukeresőn keresztüli út is szóba jöhet. Fontos, hogy nem írjuk felül a meglévő kuponokat: tehát ha pl. egy bolt közvetlenül is szerepel a Dognet hálózatban saját kampánnyal, akkor elsődlegesen annak kuponjait használjuk. Az Árukereső-harvester inkább kiegészítő forrásként működjön, amely lefedi azokat az akciókat, amiket a hagyományos csatornák nem (vagy amelyek kifejezetten az Árukereső platformhoz kötődnek).

Vásárlók átirányítása Árukereső affiliate linken

Az utolsó kérdés egy kritikus technikai részlet: hogyan irányítsuk a vásárlót az Árukereső affiliate hivatkozáson keresztül, hogy a vásárlás (pontosabban az átkattintás) után az affiliate jutalék jóváíródjon. Mivel az Árukereső affiliate programja kattintás alapú (a partner az Árukeresőről a bolt oldalára történő átirányításért kap jutalékot, nem a konkrét vásárlásért) ￼, biztosítani kell, hogy a felhasználó ténylegesen az Árukereső weboldalán keresztül kattintson tovább a boltba. Ezt az alábbi módon célszerű megoldani:
	•	Dognet deep link használata: A Dognet hálózatban minden partnerkampányhoz tartozik egyedi affiliate link. Az Árukereső kampánynál ez valószínűleg egy olyan URL, ami a promóter azonosítóját és a célt (Árukereső URL-t) tartalmazza. Például, generálható egy mélylink, ami az Árukereső adott promóciós vagy bolt-oldalára visz. A Dognet dokumentáció figyelmeztet, hogy csak közvetlen linkeket használjunk, ne láncolt átirányításokat, különben nem garantált a követés ￼. Tehát a legjobb, ha a kapott affiliate link közvetlenül az Árukereső.hu domainre mutat, a szükséges tracking paraméterekkel.
	•	Céloldal kiválasztása: Kupon jellegű ajánlat esetén a link vezethet közvetlenül az adott promóciós gyűjtőoldalra az Árukeresőn. Például a “Black Friday – akár 50% kedvezmény” ajánlatnál az affiliate link a promocio.arukereso.hu/black-friday oldalra irányít, ahol a user kategóriákra bontva böngészhet és onnan kattinthat tovább egy konkrét termékre/botra. Ha a promóció egy adott terméktípushoz vagy márkához kötődik (pl. “+1 év garancia minden Candy háztartási gépre” akció ￼), akkor érdemes lehet egy olyan Árukereső találati listára mélylinkelni, ami az adott márka termékeit mutatja. Alternatív megoldásként a bolt profiloldalára is lehet küldeni a usert (például “MediaMarkt akciók az Árukeresőn”), ahonnan a “Ajánlatok” fülön látszanak a bolt termékei és onnan kattinthat át a bolt saját weboldalára.
	•	Jutalékkövetés: Amikor a felhasználó az így generált linken az Árukeresőre érkezik, az Árukereső már tudni fogja, hogy egy adott affiliate partner küldte (Dognet ID alapján). Az Árukereső oldalán belüli továbblépés (a “Megrendelem” vagy “Tovább a boltba” gomb megnyomása) váltja ki a kattintási jutalékot az affiliate felé ￼. Ezt a modellt figyelembe véve fontos, hogy a felhasználó ne kerüljön ki az Árukereső site-ból az affiliate tracking megvalósulása előtt. Az integráció során érdemes tesztelni, hogy a link (pl. egy Dognet által generált mélylink) valóban megjelenik-e az Árukereső partnerportál statisztikáiban kattintásként.

Integráció a meglévő ImpactShop workflow-ba

Az új Árukereső-harvester modult úgy kell megtervezni, hogy ne zavarja a meglévő Dognet/CJ/TradeTracker folyamatokat, hanem azokat kiegészítve működjön. Néhány javaslat ennek kapcsán:
	•	Párhuzamos futtatás és merge: Az Árukeresőből gyűjtött kuponokat/akciókat egy külön pipeline-ban gyűjtsük (akár külön Python scraper modul a Codex workflow-ban), majd az eredményt összefésülve adjuk hozzá a kupon-adatbázishoz. Ügyeljünk a duplikációkra: ha egy akció tartalmilag megegyezik egy már létező kuponnal, vagy ugyanarra a boltra vonatkozik, akkor vagy kihagyjuk, vagy egyesítjük az információt. Mivel az Árukereső akciók általában platformszintűek és nem konkrét kuponkódok, valószínűleg nem ütköznek közvetlenül pl. egy Dognet-en kapott bolt-specifikus kuponkóddal.
	•	Automatizálhatóság: Használjuk ki a meglévő eszközöket: a Playwright felhasználható az Árukereső oldalainak rendereléséhez és a dinamikusan betöltődő tartalmak (pl. végtelen scroll listák) kezeléséhez. A Gmail integráció hasznos lehet, ha a Dognet küld e-mail értesítőket az Árukereső kampány új akcióiról – ezek automatikus feldolgozásával rögtön értesülhetünk az új promóciókról. Ugyanígy, a Google CSE (Custom Search Engine) modul alkalmazható arra, hogy internetes kereséssel ellenőrizzük, megjelent-e új Árukereső promóció (pl. rákeresünk a “site:arukereso.hu promóció” vagy hasonló kulcsszavakra a pipeline részeként).
	•	Robusztusság: Az Árukereső oldalstruktúrája idővel változhat, ezért a scraper kódot úgy írjuk meg, hogy könnyen adaptálható legyen. Használjunk megbízható szelektorokat (pl. az akciós oldalon a szekciócímek és leírások struktúráját). Ha elérhető az affiliate feed, az elsődleges forrásként szolgáljon, mert az stabilabb (a hirdető által definiált). A web-scraping legyen másodlagos, arra az esetre, ha a feed nem tartalmazna minden szükséges adatot.

Összességében egy hibrid megoldást érdemes kialakítani: először az Árukereső partnerlistáját integráljuk a rendszerbe, hogy minden shop domainnél tudjuk, az Árukeresőn keresztül jutalékkal irányítható. Ez nem foglal nagy erőforrást futásidőben, csak az adatbázis egyszeri bővítését jelenti (esetleg időnkénti frissítését, ha új boltok jelennek meg az Árukeresőn). Másodszor, a promóciógyűjtő modul figyelje és importálja az Árukereső akciókat anélkül, hogy a meglévő (Dognet/CJ/TT) kuponokat felülírná. Az így összegyűjtött adatokat az ImpactShop jelenlegi workflow-ja fel tudja használni: a frontenden új Árukeresőhöz kapcsolódó ajánlatok jelenhetnek meg, és a kattintások az Árukereső affiliate linken mennek majd (biztosítva a jutalékot).

Források:
	•	Dognet affiliate program (Árukereső.hu kampány) dokumentáció ￼ ￼
	•	NapiKuponok.hu – Árukereső.hu kuponok, akciók (példák az aktuális promóciókra) ￼ ￼ ￼
	•	Árukereső.hu Partnereink oldal – partnerboltok listája (domain integráció) ￼ ￼
Röviden: úgy tudtok jobbak lenni, mint a NapiKupon & társai, hogy nem „még egy kuponoldal” vagytok, hanem intelligens, hatás-vezérelt kuponréteg NGO-kra és Árukeresőre építve, AI-val megtámogatva.

## Kritikus tisztázások / P0-akciók
- Dognet Árukereső tracking tisztázás (kattintás vs. vásárlás, cookie domain, deep link formátum). PoC: Dognet deep link → Árukereső boltoldal → továbbkattintás, Playwright-tel végigmenni, cookie-t/logot nézni, 1 óra múlva Dognet dashboardon ellenőrizni.
- Feed elérhetőség: van-e Árukereső promó/kupon feed a Dognetben? Ha igen, elsődleges forrás, scraping csak fallback.
- Domain matching: Árukereső partnerdomain ≠ registry domain (about-you.hu vs aboutyou.hu). Fuzzy/alias matcher kell (www-/hyphen törlés, path vágás, alias tábla).
- Tracking flow: ImpactShop → Dognet redirect → Árukereső → shop. Teszt stagingen, hogy a Dognet kattintás loggolódik-e.
- Cookie/ITP kockázat: Safari/Firefox ellenőrzés, ha kell server-side klikkelés.

## Scrape/forrás stratégia (hibrid)
- Forrás-prioritás: 1) Dognet feed (ha van), 2) Árukereső promó oldalak (promocio.arukereso.hu + fő akció oldalak), 3) Competitor mirror (pl. NapiKupon/Picodi Árukereső szekció), 4) CSE fallback.
- Scraper robusztusság: JSON-LD > API endpoint > DOM selector > AI/vision fallback. Naplózd a sikeres stratégiát; ha minden bukik, alert.
- Kampányradar: promó subdomain + homepage banner + Gmail hírlevelek + versenytársak; új kampányokra AI relevancia score, auto-import javaslat, ha relevancia > 0.7.

## Matching és enrich
- Domain matcher: normalizálás (www, path, kötőjel), alias tábla (about-you.hu→aboutyou.hu, h-m.com→hm.com), fuzzy match (Levenshtein >0.85). Tanulás sikeres vásárlásokból (manual override bővítése).
- Registry jelölés: `arukereso=true`, aliasok, Árukereső store URL. Auto-enrich: sikertelen/NEEDS_MAPPING találatokból javaslatot generálni, de manuális review szükséges.

## Minőség és scoring
- AI quality scorer (faktorok): frissesség, sikerességi arány, forrás hitelessége (feed > API > email > scrape > mirror), NGO-impact potenciál, user feedback. Kimenet: score + ajánlás („Kiemelt”, „Ellenőrizd”, „Nem ajánlott”).
- Kupon QA: kód/lejárat/discount JSON-LD-ből is; Playwright validáció csak shop-spec selectorral és limitáltan; user/visszajelzés + sikerességi stat rögzítés.

## Tracking és impact
- NGO impact számítás: csak megerősített jutalék alapján; click-fee modell Árukeresőn (ha kattintás alapú), donation = jutalék 50%-a. Public stats: pending vs confirmed, top NGO-k, total savings.
- User journey tracking: route (arukereso/direct), decision time, konverzió; AI ajánlás „top combo” (ár + kupon + NGO-hatás).
- Etikai guard: ne hijackelj affiliate-et, jelezd a trackinget; AI figyelje a konfliktusokat (más affiliate?) és jelezzen.

## Operatív AI / Competitive intel
- Belső AI: draftolja a tisztítási listát (lejárt, alacsony score), ajánl extra Gmail/CSE fókuszt shopokra, készít NGO/shop levél draftot (impact riport).
- Versenytárs monitoring: NapiKupon/Picodi/Kuponkodok scrape; gap/advantage riport, alert kritikus gap esetén.

## Prioritások (rövid)
- P0: Dognet tracking PoC Árukeresővel, feed elérhetőség, domain matcher élesítése.
- P1: Scraper fallback réteg, AI quality scorer baseline, NGO impact tracker (csak confirmed jutalékból).
- P2: Kampány-detektor + competitive intel, user journey analytics, A/B keretrendszer (Arukereso-prior vs kupon-prior vs NGO-prior).

## Gyakorlati checklist
- Kapcsolj feedet, ha van (Dognet Árukereső kampány); ha nincs: promó oldalak + mirror scrape + CSE.
- Fuzzy/alias domain match aktiválása, manual override tábla karbantartása.
- Tracking teszt: Dognet deeplink → Árukereső → bolt, cookie/log ellenőrzés, dashboard hit.
- Scoring & QA: frissesség + forrás + impact alapú rangsor, lejárt/gyenge kuponok tisztítása, sikerességi arány gyűjtése.
- Monitoring: napi riport (találat forrásonként, gap vs competitor), alert 0 találat vagy tömeges scrape-fail esetén.

## Megvalósítási terv (lépések, prioritások)
### P0 – Alapok és kockázatok (1–2 nap)
- Dognet Árukereső tracking PoC (deeplink, cookie/redirect teszt, dashboard hit).
- Feed elérhetőség ellenőrzés a Dogneten (ha van: URL/mezők; ha nincs: fallback scraping).
- Domain matcher élesítése (normalizálás, alias, fuzzy; manual override táblázat).

### P1 – Források és robusztusság (3–5 nap)
- Forrás-prioritás: 1) feed (ha van), 2) promó oldalak (promocio.arukereso.hu + fő akciók), 3) competitor mirror (NapiKupon/Picodi), 4) CSE fallback. Ütemezés: feed óránként, promó/mirror napi 1x, CSE fallback.
- Scraper fallback lánc: JSON-LD > API endpoint > DOM selector > AI/vision fallback; sikeres stratégia log, bukáskor alert.
- Kampányradar (light): promó subdomain + homepage banner napi 1x, új slug relevancia score, auto-import javaslat.

### P1.5 – Minőség és scoring (2–3 nap)
- AI quality scorer baseline: frissesség, sikerességi arány, forrás hitelesség, NGO-impact, user feedback → score + ajánlás.
- Kupon QA: JSON-LD kód/lejárat/discount; limitált Playwright validáció shop-spec selectorkkal; user feedback/sikerességi számlálás.

### P2 – Impact és tracking (3–4 nap)
- NGO impact tracker: confirmed jutalékon alapuló donation (click-fee Árukereső esetén), public stat pending/confirmed, total savings.
- User journey tracking: route (arukereso/direct), decision time, konverzió; AI „top combo” ajánlás (ár + kupon + NGO-hatás).
- Operatív AI: tisztítási listák, shop fókusz javaslat, NGO/shop levél draft.

### P3 – Competitive/Campaign/AB (opcionális, 1–2 sprint)
- Versenytárs monitoring (NapiKupon/Picodi/Kuponkodok), gap/advantage riport, kritikus gap alert.
- Kampány-detektor mélyebben: AI relevancia, auto-import javaslat.
- A/B keretrendszer: Arukereso-prior vs kupon-prior vs NGO-prior variánsok, metrikák.

### Technikai jegyzetek
- Lassú domainek: blocklist (decathlon, alza, dyson, stb.), `waitUntil=domcontentloaded`, 5s goto timeout, 10s domain budget; fetch-only fallback.
- CSE rate limit: kisebb domain batch-ek; mirror források.
- Registry jelölés: `arukereso=true` + alias + store URL; auto-enrich javaslat, manuális reviewval.
- Monitoring: napi riport forrás bontásban; alert 0 találat vagy tömeges hiba esetén.

### Javaslat – építsünk a jelenlegi default harvester beállításokra
- Default parancs (web+gmail, 1 URL/domain, domain budget 10s, Playwright domcontentloaded 5s, blocklist a lassúakra):
```
GOOGLE_SEARCH_ENABLED=1 GOOGLE_SEARCH_MAX_DOMAINS=100 GOOGLE_SEARCH_RESULTS_PER_DOMAIN=1 \
GOOGLE_SEARCH_API_KEY=<API_KEY> GOOGLE_SEARCH_CX=<CX> \
PLAYWRIGHT=1 PLAYWRIGHT_GOTO_TIMEOUT=5000 WEB_DOMAIN_BUDGET_MS=10000 PLAYWRIGHT_BLOCKLIST=decathlon.hu,alza.hu \
DRY_RUN=0 TS_NODE_TRANSPILE_ONLY=1 HARVEST_FULL_SCAN=0 RELAXED_MODE=1 DISCOVERY_MODE=0 \
GMAIL_CREDENTIALS=./secrets/gmail/credentials.json GMAIL_TOKEN=./secrets/gmail/token.json \
npx ts-node coupon-harvester.ts
```
- Ezt tekintsük baseline-nak, és Arukereso-specifikusan finomítsuk:
  - Registry-ben `arukereso=true` jelölés, aliasok.
  - Ha feed van, web/CSE csak fallback; ha nincs feed, marad a fenti CSE+fetch/Playwright.
  - Lassú/hibás domaineket bővítve blocklisteljük, de a domain budget megakadályozza a beragadást.

A klasszikus kuponoldalak gyengéi elég jól ismertek:
	•	rengeteg lejárt / nem működő kód, néha mégis „verified” címkével
	•	mennyiségre mennek, affiliate-re optimalizálnak, nem a user élményre
	•	gyakorlatilag semmi valódi személyre szabás.

Erre érdemes ráépíteni az AI Agentet.

⸻

1. Minőség > mennyiség: AI-vezérelt „megbízható kupon” réteg

a) Automatikus kupon-verifikáció

A legtöbb kuponoldal ott bukik el, hogy nem teszteli a kódokat, csak listázza őket hónapokig.
Ti viszont már most rendelkeztek:
	•	Playwright + scrapinggel,
	•	Gmail feeddel (hírlevelek),
	•	saját manual_coupons_draft-*.csv + statokkal.

Erre rá lehet rakni egy AI-asszisztált QA réteget:
	•	A harvester minden kuponnál:
	•	gyűjti a sikerességi adatot (pl. mennyi user jelzett vissza, hogy működött / nem működött).
	•	ha van rá technikai tér (bizonyos shopoknál): időnként automatizáltan „szimulál” kosár + kód-beírást (csak ellenőrzés, nem vásárlás).
	•	Az AI Agent:
	•	score-olja a kuponokat: reliability_score, avg_savings, last_verified.
	•	javaslatot tesz:
	•	mit húzzatok le a listáról,
	•	mit emeljetek ki „Super megbízható” címkével.

Ez már önmagában nagy ugrás NapiKupon felett: nem „listát” adtok, hanem kurált, minősített kuponokat.

⸻

2. AI Agent mint személyi kupon-&-NGO tanácsadó

Más kuponoldal: „válassz egy boltot, nézd meg a kódlistát, aztán szerencse”.
Nálatok az AI lehet:

„Oké, mondd el mit akarsz venni, mennyiből, kit akarsz támogatni – én meg összerakom hozzá a legjobb utat.”

Az AI mit csinálhat:
	•	Felhasználói input:
	•	„Laptopot akarok 250k alatt, Bátor Tábor támogatással.”
	•	„Karácsonyi ajándék 3 gyereknek, minél több támogatás NGO-knak.”
	•	Agent lépések:
	1.	Megnézi a shops_registry + ImpactAll adatokat:
	•	mely boltok relevánsak kategóriában,
	•	hol fut most akció / kupon,
	•	melyik bolt milyen NGO-impactet ad (Dognet/Árukereső jutalék → donation).
	2.	Összeköti:
	•	Arukereso route (ha ott van a bolt),
	•	a legjobb kupon (megbízhatóság + %-os kedvezmény),
	•	NGO hatás (hol lesz nagyobb donation).
	3.	Emberi nyelven elmagyarázza:
	•	„Ezt a 2 webshopot ajánlom, itt a kupon, így megy a pénz az adott NGO-hoz, erre figyelj a szállításnál / garanciánál.”

Ehhez az AI-s kupon-personalizáció világából jön a know-how: a személyre szabott kuponok növelik a konverziót és az elégedettséget, ha viselkedési adatot, preferenciákat és kontextust használnak  – ti ezt kiegészítitek azzal, hogy NGO-hatást is optimalizáltok.

⸻

3. Arukereso-fókusz: „AI price & coupon scout” NGO-val kombinálva

A NapiKupon, Kuplio, Picodi stb. főleg shop→kupon párokat listáz.
Nektek van egy extra fegyveretek: Árukereső + Dognet.

AI-val meg lehet csinálni:
	•	A harvester registry-be beírja, mely shopok Árukereső partnerek, és hogy náluk via="arukereso".
	•	Az AI:
	•	végigfut Árukereső promó oldalain (Black Friday, tematikus akciók),
	•	összeköti az ottani akciókat a saját kupon/adat készletetekkel,
	•	kijelöli a „Top combo” ajánlatokat:
	•	ahol:
	•	jó ár van,
	•	van kupon vagy erős akció,
	•	magas az affiliate → donation hatás.

A user felé ez így néz ki:

„Itt a legjobb deal most: Arukereso + X bolt + ez a kupon = ennyit spórolsz, ennyi megy az NGO-nak.”

A konkurens kuponoldal maximum annyit mond, hogy „-20% kupon ide”.

⸻

4. Bizalom & etika: AI mint „anti-Honey” ellenőrzőtorony

A Honey-sztori jó elrettentő példa: a böngészőbővítményeket azzal vádolták, hogy néha nem a legjobb kuponokat adják, és közben elviszik az affiliate jutalékot az influencerektől.

Ti itt nagyon szépen tudtok különbözni:
	•	Nyíltan kommunikálható policy:
	•	nem hijackeltek más affiliate linket,
	•	világosan jelzitek, mikor kerül a Sharity/NGO jutalék.
	•	Az AI Agent:
	•	figyeli a saját tracking logokat,
	•	nézi, hogy nem ütközik-e más forrás affiliate-jével (pl. user egy másik affiliate linkről jött),
	•	szükség esetén javaslatot tesz:
	•	„itt inkább ne írjuk felül, mert etikailag kérdéses”.
	•	Ráadás:
	•	kuponoknál láthatóvá teszitek a sikerességi arányt, „utoljára tesztelve” időpontot,
	•	az AI folyamatosan javasol tisztogatást.

Ez presztízstépő fegyver a „csak SEO + affiliate” kuponoldalak ellen.

⸻

5. AI Agent mint „belső operációs agy” – nem csak user felé

A fronton user-facing AI, a háttérben meg egy operatív AI-agent, aki segít neked:
	1.	Kupon pipeline tuning:
	•	végigmegy a manual_coupons_draft-*.csv és manual_coupons_stats.json fájlokon,
	•	kiemeli:
	•	mely shopoknál érdemes több Gmail queryt, több CSE találatot futtatni,
	•	hol lenne érdemes közvetlen merchant kapcsolatot építeni (sok forgalom, sok kupon, erős NGO-impact).
	2.	NGO-kommunikáció:
	•	készít rövid, emberi levél draftokat NGO-knak:
	•	„Múlt hónapban ennyi támogatás jött a kuponjaidon keresztül, ha növelni akarjátok, itt és itt érdemes promózni…”
	•	ugyanezt shopoknak:
	•	„A Sharity-n keresztül ilyen forgalmat hoztunk, érdemes lenne extra kuponkódot adni X NGO kampányához.”
	3.	GDPR / compliance radar:
	•	mivel amúgy is AI-asszisztens projektekről beszélünk nálad:
	•	a kuponos rendszerben is figyel rá:
	•	milyen adatot tároltok user szinten (email, preferencia),
	•	javasolja, hogyan lehet ezt minimális adatmennyiséggel / anonimizálva / szabályosan tárolni.

⸻

6. Konkrét AI-feature ötletek, amik NapiKupon szint fölé lőnek

Pár konkrét modul, ami simán beilleszthető a meglévő harvester + ImpactShop világba:
	1.	„Kupon coach” chat a usernek:
	•	„Írd be, mit vennél” → AI:
	•	keres releváns shopot,
	•	ránéz a friss kuponokra,
	•	ajánl 2–3 opciót NGO-hatással együtt.
	2.	Intelligens kupon-rangsor:
	•	Az AI rangsorolja:
	•	nem csak %-ra, hanem:
	•	megbízhatóság,
	•	átlagos megtakarítás,
	•	lejárati kockázat,
	•	NGO-impact alapján.
	•	UI-ban: „AI szerint legjobb ajánlatok most” blokk.
	3.	„Early-bird” kampányradar:
	•	AI figyeli:
	•	Gmail promó emailekben,
	•	weben (CSE + scraping),
	•	hogy mikor jelennek meg új Black Friday / szezonális / limitált akciók.
	•	Ha talál ilyet, javasolja:
	•	automatikusan felvenni a draft CSV-be,
	•	kiemelni az ImpactShop főoldalon.
	4.	Story-szintű ajánlatok:
	•	Nem csak: „-20% a X boltban.”
	•	Hanem: „Ha most itt vásárolsz, ennyi pénzt spórolsz, és ennyi megy Bátor Tábornak / X NGO-nak.”
	•	Az AI generál egy 1–2 mondatos magyarázó szöveget, amit a kártyán / kampányoldalon megjelenítesz.

⸻

Összezárva

A NapiKupon-típusú site-ok:
	•	listáznak,
	•	affiliate-et hajtanak,
	•	semmi „agy” nincs mögötte.

Te:
	•	már most építettél egy félig kész „kupon-adat infrastruktúrát” (harvester, registry, metrics),
	•	erre hozol egy AI-t, ami:
	•	minőségbiztosít,
	•	személyre szab,
	•	NGO-hatásra optimalizál,
	•	és árukeresős forgalmat is okosan terel.

Így nem csak „jobb kuponoldal” leszel, hanem okos, etikus, hatás-vezérelt kupon- és NGO-agent, amihez képest a sima kupongyűjtők olyanok, mint egy régi újságban a reklámoldal.

## AI véleményekből (Sonnet/Opus) átemelt kritikus kiegészítések
- **Tracking flow tisztázás**: Dognet kattintás vs. Árukereső (kattintás/vásárlás) modell ellentmondásos. Safari/Firefox ITP miatt Playwright-es teszt kell (deeplink → Árukereső → shop, cookie/log ellenőrzés, Dognet dashboard hit).
- **Feed/API bizonytalanság**: ha nincs Árukereső feed a Dognetben és nincs partner API, scraping csak fallback (robots.txt/ÁSZF ellenőrzés kötelező).
- **Jog/etika**: robots.txt check, írásos engedély nélküli agresszív scraping kerülendő; rate limiting, tiszta UA, alacsony frekvencia. Affiliate transzparencia (disclosure, mit trackingelünk/nem, retention 90 nap).
- **Dedup**: több forrás (feed, scrape, hírlevél, competitor) → duplikáció. Prioritás: feed > hírlevél > scrape > competitor; fuzzy cím/discount, merge multi-source info.
- **Cold start scoring**: kezdetben forrásalapú pontszám + frissesség degradáció; később implicit/explicit user feedback; ML modell csak elég adatnál.
- **NGO impact**: csak confirmed jutalék után kommunikálj; estimated/pending/confirmed szintek, Dognet webhook szükséges.
- **Legal risk**: competitor mirror (NapiKupon/Picodi) max. verifikációra, nem elsődleges forrás; ToS/robots respektálása.
- **GDPR**: affiliate/AI személyre szabás consent, minimális adat (hash), opt-out, retention policy, publikus disclosure oldal.
- **Operatív finomhangolás**: browser-pool / scheduling, külön lassú domainek blocklist, CSE rate-limit, registry alias + arukereso flag, kampányradar relevancia score, monitoring/alerting (feed 404, 0 új kupon, scraper fail).

## Kiviteli terv (részletes, a fenti alapokra építve)
### Fázis 0 – Jogi/technikai kockázatkezelés (1–2 nap)
- robots.txt/ÁSZF check (Árukereső): scraping engedélyezett-e; ha tiltott → csak feed/API + engedély.
- Dognet tracking PoC (Chrome, Safari/ITP, Firefox): deeplink → Árukereső → shop, cookie/log, dashboard hit.
- Feed elérhetőség: Dognet partnerfelület – van-e Árukereső kampány feed (XML/JSON)? URL, mezők, refresh.
- GDPR/affiliate disclosure: tájékoztató (mit trackelünk, retention 90 nap, opt-out).

### Fázis 1 – Alap integráció és forrás-prioritás (3–5 nap)
- Forrás-prioritás: 1) feed (ha van), 2) promó oldalak (promocio.arukereso.hu + fő akciók), 3) competitor mirror (NapiKupon/Picodi) csak verifikációra, 4) CSE fallback.
- Registry bővítés: `arukereso=true`, alias/slug, Árukereső store URL; alias tábla (about-you.hu→aboutyou.hu, stb.).
- Domain matcher: normalizálás, alias, fuzzy (Levenshtein), manual override tábla.
- Scraper fallback: JSON-LD > API endpoint > DOM selector > AI/vision (opcionális); sikeres stratégia log, bukáskor alert.
- Baseline parancs (web+gmail, 1 URL/domain, 10s domain budget, Playwright domcontentloaded 5s, blocklist a lassúakra):
```
GOOGLE_SEARCH_ENABLED=1 GOOGLE_SEARCH_MAX_DOMAINS=100 GOOGLE_SEARCH_RESULTS_PER_DOMAIN=1 \
GOOGLE_SEARCH_API_KEY=<API_KEY> GOOGLE_SEARCH_CX=<CX> \
PLAYWRIGHT=1 PLAYWRIGHT_GOTO_TIMEOUT=5000 WEB_DOMAIN_BUDGET_MS=10000 PLAYWRIGHT_BLOCKLIST=decathlon.hu,alza.hu \
DRY_RUN=0 TS_NODE_TRANSPILE_ONLY=1 HARVEST_FULL_SCAN=0 RELAXED_MODE=1 DISCOVERY_MODE=0 \
GMAIL_CREDENTIALS=./secrets/gmail/credentials.json GMAIL_TOKEN=./secrets/gmail/token.json \
npx ts-node coupon-harvester.ts
```
- **Baseline fagyasztva:** a jelenlegi coupon-harvester.ts implementációt nem módosítjuk/írjuk felül; minden új Árukereső-specifikus fejlesztés külön modulban/ágon készül.
- Monitoring/alert: napi riport forrás bontásban; alert 0 új kupon, feed 404, scraper fail.

### Fázis 2 – Minőségbiztosítás és deduplikáció (2–3 nap)
- Dedup engine: forrásprior (feed > hírlevél > scrape > competitor), fuzzy cím/discount, merge multi-source.
- Cold start scoring: forrásalapú + frissesség degradáció; implicit/explicit feedback; ML csak elég adatnál.
- QA: JSON-LD kód/lejárat/discount mentése; limitált Playwright validáció shop-spec selectorral (ha megadva); user feedback számláló.

### Fázis 3 – Tracking és NGO-impact (3–4 nap)
- NGO impact 3 szint: estimated → tracked → confirmed; csak confirmed donation publikus; Dognet webhook commission-confirmed.
- Impact stat: totalDonations (confirmed), totalSavings, top NGOs, pending vs confirmed.

### Fázis 4 – Kampányradar és versenytárs figyelés (1 sprint)
- Kampányradar: promó subdomain + homepage banner napi 1x, új slug relevancia score, auto-import javaslat (>0.7).
- Versenytárs monitoring: NapiKupon/Picodi/Kuponkodok; gap/advantage riport, kritikus gap alert; etikai korlát: csak verifikáció.

### Fázis 5 – AI/UX finomhangolás (folyamatos)
- AI quality scorer: frissesség, forrás hitelesség, user feedback, NGO-hatás → score + ajánlás.
- „Kupon coach” MVP: user input → 1–3 ajánlat (shop+kupon+NGO-hatás), rövid magyarázat.
- A/B keret (később): Arukereso-prior vs kupon-prior vs NGO-prior variánsok, metrikák (CTR, copy rate, conversion, NGO választás).

### KPI-k (javaslat)
- Új monetizálható shopok Árukeresőből; új kupon/promó havonta; extra NGO-donation/affiliate; dead kupon arány; scrape/feed sikeresség; dedup ráta.

### Kritikus blokkolók
- Dognet tracking flow (ha nem működik, az Árukereső út nem értelmezhető).
- Feed/API hiánya + robots.txt tiltás → scraping nem járható; partneri megállapodás kell.

# Állapot / baseline rögzítés
- Baseline freeze: a meglévő `tools/coupon-harvester.ts` változatlan, nem módosítjuk/írjuk felül.
- Külön Árukereső-skeleton modul: `tools/arukereso-harvester.ts` (robots.txt check, Dognet feed/API stub, tracking POC stub, dedup + cold-start scoring váz, monitoring snapshot írás). Minden Árukereső-specifikus fejlesztés ebben a modulban/ágban készül.
- Baseline futtatási beállítások (web+gmail, CSE 1 URL/domain, domcontentloaded 5s, domain budget 10s, blocklist decathlon/alza, RELAXED_MODE=1, DISCOVERY_MODE=0) maradnak, ezekhez nem nyúlunk.
- Aktuális modulállapot: a skeleton bővítve env-alapú Dognet feed/API ellenőrzéssel (HEAD próba), robot.txt checkkel, tracking POC jelzéssel, dedup/normalizálás finomítással és monitoring snapshot builderrel; éles scraping/Playwright továbbra is külön lépésben fejlesztendő.
- Strukturált adat bővítés: JSON-LD Offer/Promotion kinyerés az extractSignals-ben (reliability ~0.85), kevesebb HTML-regex függés.
- Merchant radar builder: `tools/arukereso-merchant-radar.ts` (promos + registry → merchantId/domains/categories/feedVolume/couponPriority).
- Opcionális upstream kupon adapter (helyi JSON): `UPSTREAM_API_ENABLED=1`, `UPSTREAM_COUPONS_JSON` → promók becsatornázhatók (forrás: manual), unified pipeline stub `ARUKERESO_UNIFIED_PIPELINE=1` (egyelőre csak log), affiliate guard log-only `AFFILIATE_GUARD_LOG=1`.
- Frissítés: ha a `DOGNET_ARUKERESO_FEED_URL` env megvan és elérhető, a modul megpróbálja beolvasni a feedet (HEAD + fetch, XML/JSON minimál parser); ha több feed van, `DOGNET_ARUKERESO_FEED_LIST` (vesszővel) használható, és mindet beolvassa. Ha nincs feed, üres listával tér vissza. Az ARUKERESO_API_* env jelenleg csak elérhetőség-checket végez.
- Snapshot: CLI futáskor opcionálisan menti a monitoring snapshotot (alapértelmezés: `tools/out/arukereso-snapshot.json`, felülírható `ARUKERESO_SNAPSHOT_OUT` env-vel). A snapshot tartalmazza a forrás-eloszlást, feed/API/robots állapotot és jegyzeteket.
- Registry-s stub scrape: feed hiányában betölti a `shops_registry.json`-t (REGISTRY_PATH env felülírhatja), domainenként path-hintekkel (`/`, `/kupon`, `/akcio`, `/kedvezmeny` vagy shop-spec `paths`), egyszerű fetch-scan, domainenként max 1 találat, domain budget `WEB_DOMAIN_BUDGET_MS` (default 10000). Playwright továbbra sincs bekötve ebben a modulban.
- Leválasztási pontok:
  - `ARUKERESO_ENABLED=0` → teljes modul tiltása; a baseline coupon-harvester fut tovább változatlanul.
  - `ARUKERESO_FOLLOW_OFFERS=0` (alapértelmezés) → offer-follow/Playwright off; `=1`-re kézzel bekapcsolható.
  - `AFFILIATE_GUARD_LOG=1` csak logol, nem blokkol; `=0`-ra visszaállítva semmi extra.
  - `ARUKERESO_UNIFIED_PIPELINE=0` → régi sorrendezés (nincs expiry filter/sorrendezés reliability alapján); `=1` → expiry filter + alap NGO/reliability score szerint rendez.
  - `ARUKERESO_PROMO_MODE=0` → catalog (arukereso.hu) elemek szűrhetők; `=1` → catalog jelzéseket is megtartjuk radarhoz.
  - Visszaállítás: futtasd a baseline parancsot az Árukereso env-ek nélkül, vagy állítsd a fenti kapcsolókat 0-ra; a baseline modulhoz nem nyúltunk.
- Feed-lista jegyzet: `DOGNET_ARUKERESO_FEED_LIST` használatakor a modul jelzi, hány feed URL-t próbált (stats.notes, stats.dognetFeedsTried).
- Promó export: CLI futáskor JSON és CSV export is készül (default: `tools/out/arukereso-promos.json` és `.csv`, env: `ARUKERESO_PROMOS_JSON_OUT`, `ARUKERESO_PROMOS_CSV_OUT`).
- robots.txt: ha tiltást jelez, a modul notes-ban figyelmeztet, scraping csak engedéllyel/óvatosan.
- Feed riport: feed-listás futásnál per-feed riport készül (ok/count/error), hibás feedek száma jelzés a notes-ban.
- Domain következtetés: feedből beolvasott URL-eknél kísérlet a shopDomain-re (URL hostname normalizálás).
- Stub scrape: HTML-ből kulcsszó/regex alapú jelzés, egyszerű kuponkód/discount detektálás (kód minták, %/Ft). CSV export tartalmazza a `couponCode` és `discountLabel` mezőket is.
- Domain blocklist + limit: `ARUKERESO_DOMAIN_BLOCKLIST` (vessző), illetve `ARUKERESO_MAX_PROMOS` (ha >0, limitálja a kimenetet), notes-ban jelzi a vágást.
- Kombinált mód: ha a feed üres vagy `ARUKERESO_SCRAPE_IF_FEED_OK!=0`, a feed + registry-s stub scrape kombinálható; ha a feed üres, fallback jelzés a notes-ban. Statisztikában forrás/domain eloszlás is mentésre kerül.
- További limitek: `ARUKERESO_FETCH_TIMEOUT_MS` (fetch timeout), `ARUKERESO_MAX_PER_DOMAIN` (default 1, domainenkénti limit, eldobott elemek statban), forrás/domain eloszlás a stats-ban.
- Playwright bekötve a modulban: `PLAYWRIGHT=1` esetén a registry-s scrape Playwright-tal fut (domcontentloaded, `PLAYWRIGHT_GOTO_TIMEOUT` default 5000, `WAIT_AFTER_LOAD` default 300, `ARUKERESO_DOMAIN_BLOCKLIST`/domain budget érvényes). Alapból headless; `PLAYWRIGHT_HEADLESS=0` kikapcsolja.
- Tracking POC: ha `PLAYWRIGHT=1` és `DOGNET_TEST_URL` megadva, lefut egy Playwright-os redirect lánc teszt, statban `trackingResult`-ban elérhető; ha nincs env, jelzi, hogy kihagyva.
- Slug hozzárendelés: registry alapján domain → slug, forrásprior (feed > scrape > manual) szerint rendezés; kimenetben shopSlug is megjelenik, ha egyezés van.
- Playwright screenshot: `ARUKERESO_SAVE_SCREENSHOT=1` esetén a scrape screenshotot is ment (default dir: `tools/out/arukereso-shots`, env: `ARUKERESO_SCREENSHOT_DIR`).
- Feed parsing bővítés: JSON feed esetén próbálja kinyerni a promoCode/discount mezőket is (couponCode, discountLabel), slug-assign + prior-sort a registry alapján.
- JSON-LD parsolás: ha a HTML-ben van `application/ld+json` promoCode/discount, azt is hozzáadja a találatokhoz.
- Feed → slug pontosítás: feedből URL host alapján shopDomain, registry szerint slug-assign; slug nélküli találatok exportja `ARUKERESO_UNMATCHED_OUT` (default: `tools/out/arukereso-unmatched.csv`).
- Playwright mélyítés: CTA/gomb kattintás próbák (max 3), screenshot csak találat/engedély esetén, opcionális OCR (`ARUKERESO_OCR=1`) a screenshotból is keres mintákat.
- Tracking POC erősítve: redirect lánc + cookie dump (domain, name, sameSite, secure), hibák részletes log; külön CLI script: `npx ts-node tools/test-arukereso-tracking.ts` (PLAYWRIGHT=1, DOGNET_TEST_URL szükséges), JSON-kimenet.
- Dedup/score: slug-assign + forrásprior után score, majd limitálás (forrás/domain/globál); statban jelzett filteredBadCodes, codesWithoutDiscount, truncation; bad-code szűrő (DOCTYPE/BACKGROUND-IMAGE/2025/2026 stb.), discount nélküli kódok számlálása.
- Monitoring: rövid summary TXT (`ARUKERESO_SUMMARY_OUT`, default `tools/out/arukereso-summary.txt`), feed-hibák JSON (`ARUKERESO_FEED_ERRORS_JSON_OUT`), scrape hibák JSON (`ARUKERESO_SCRAPE_ERRORS_JSON_OUT`, bontás error típus szerint), unmatched JSON/CSV (env).
- Feed alias: `ARUKERESO_DOMAIN_ALIASES` (from=to, vesszővel) slug-assignhez; feed JSON parsing dobja a kód/discount mezőket, rossz URL esetén skip.
- Playwright: célzott selector-scan a CTA után (`.coupon-code`, `.promo-code`, `[data-coupon]`), max 3 extra találat; tracking POC JSON export `ARUKERESO_TRACKING_JSON_OUT`-tal (default `tools/out/arukereso-tracking.json`).
- Feed parsing: URL nélküliek kiszűrése, promoCode/discount + expiry/category/merchantID beolvasás, domain alias a slug-matchhez. Confidence score kicsit emelkedik, ha van discount/category.
- Tracking POC: időmérés, cookie scope+TTL logolva; JSON exportba is kerül, ha fut.
- Részletek: statban blockedDomains, unmatchedCount, unkDomainBucket; scrape hibák bontása (error típus), summary TXT kiírja; shopCategory (registry) rákerül a promókra, slug nélküli találatok „unknown” bucketként jelezhetők statban.
- Path hint preferencia a registryből: a scrape a shop-spec `paths` sorrendjében próbál, így a preferált aloldalak hamarabb jönnek.
- Tracking POC retry/időzítés: `ARUKERESO_TRACKING_RETRY`-vel újrapróbál, nav timing + cookie scope/TTL is mentésre kerül.
- Feed-hiba toplista: feed riport tartalmazza a missing URL/title countot és mintákat (badUrlSamples, badTitleSamples); top hibás feed URL-ek a feed-errors JSON-ban visszakereshetők.
- Shop kategóriás scoring: kategória-súlyok bevonva a confidence-be; unknown bucket jelzése statban/notes-ban.
- Még hiányzó toplista: feed-hibák/hiányos link/title külön toplistáját érdemes később hozzáadni (jelenleg feedErrors JSON-ban csak az ok/hiba/hiányzó mezők száma van), illetve a score-ba a shop kategória súlyozása még minimális (heurisztikus emelés discount/category esetén).

## Finomhangolások (legutóbbi iteráció)
- Dedup súlyozás: forrás-prioritás (dognet_feed > arukereso_scrape > manual), kódazonosság elsőbbséget élvez; dedup eldobott mennyiség statban `dedupDropped`.
- Alias bővítés: beépített gyakori párok (about-you.hu→aboutyou.hu, mediamarkt.hu/hu-hu→mediamarkt.hu, h-m.com→hm.com, mall.cz/hu→mall.hu, alza.sk→alza.hu, ikea.com/hu→ikea.com/hu/hu) + env `ARUKERESO_DOMAIN_ALIASES`.
- Feed auto-list: ha nincs `DOGNET_ARUKERESO_FEED_LIST`, a teljes Dognet Árukereső feed-lista (19 kategóriás URL) alapból betöltésre kerül; továbbra is felülírható env-vel.
- Summary kiegészítés: dedup eldobott, path hit top, feed-hiba top, blocked/domain limit statok a TXT-ben is.
- Feed-hiba toplista külön export: JSON/CSV (fetch hiba, hiányzó URL/title, mintákkal) `ARUKERESO_FEED_ISSUES_*` outputokban.
- Fuzzy dedup: title-sim + discount tolerancia, stat: `dedupFuzzyDropped`.
- Kategória-map bővítés (magyar → canonical), nem besorolt kategóriák jelzése statban.
- Dashboard JSON export: fő metrikák egyben (`ARUKERESO_DASHBOARD_OUT`).

## Extra céloldalak (manuális/CSE/harvester)
- kuplio.hu/kuponok és kuplio.hu/akciok: érdemes felvenni a webes/Playwright scrape listába (mind a baseline kupon harvester, mind az Árukereső ág esetén), illetve CSE keresésekbe, mert kupon- és akciógyűjtő aggregátor.

## Legutóbbi futási tapasztalatok / beállítások
- 2 feed (film + étel) feed-only + Playwright offer-follow mellett is jellemzően arukereso.* katalógus URL-ek jöttek; dedup/limit miatt 30 promó maradt, de a follow adhostokra (p1.akcdn.net, productads.hu) ment, nem shop domainre; OffersFollowed stat sem íródott be rendesen.
- ARUKERESO_SKIP_ARUKERESO_DOMAINS=1 esetén minden arukereso.* kidobódik → feedből 0 promó.
- Domain limit feloldása mellett (ARUKERESO_MAX_PER_DOMAIN=0, globális limit 500) is a feed adata arukereso.* aldoméneket tartalmaz → offer-followot shop-linkre kell finomítani (kizárni CDN/image linket, vagy célzott offer gombot keresni).
- Feed timeout: 2–3s/URL; több feed esetén a hosszú futás miatt batch-ben érdemes futtatni vagy kisebb feedlistával.
- Registry/path/alias rendben, de a feedek shop linket nem adnak; a katalógusoldalról kell kinyerni a shop URL-t Playwrighttal (célszerű exclude: akcdn.net).

### Legutóbbi run (2 feed, offer-follow fixelt stattal, skip arukereso)
- Beállítás: 2 feed (film + étel), PLAYWRIGHT=1, ARUKERESO_SKIP_ARUKERESO_DOMAINS=1, per-domain limit ki (0), globális limit 500, follow limit 30, feed timeout 2s.
- Eredmény: 0 promó. Dedup drop 3842, fuzzy 3081; feed OK (2 és ~13k elem), de minden offer-follow arukereso.hu-ra mutatott (offersByDomain: {arukereso.hu:30}), ezért a skip-szűrés miatt minden kiesett.
- Következtetés: offer-follow továbbra sem jut el shop domainre; vagy ki kell kapcsolni a skip-et és promó módba tenni, vagy mapping/deep-follow kell a shop domainhez.

### Friss fejlesztések (2025-11-22)
- Dinamikus alias map (feed+registry alapján) a slug-assign előtt → path/TLD eltérő domainek automatikus hozzárendelése, notes-ban alias találatok száma.
- Új alias builder script: `tools/build-arukereso-mapping.ts` (promos + registry alapján alias-javaslatok generálása `tools/out/arukereso-alias-map.json`-ba).
- Offer-follow: multi-hop (env `ARUKERESO_FOLLOW_MAX_HOPS`, default 5), kibővített blacklist (`productads.`, `go.dognet.com`, `doubleclick`, `googleadservices`, stb.), stats közvetlenül frissül (`offersFollowed`, `offersFollowErrors`, `offersByDomain`), user-agent beállítva.
- Promo mód flag: `ARUKERESO_PROMO_MODE=1` esetén nem szűrjük az arukereso.hu katalógus URL-eket, `isCatalogUrl` megjelölés, notes jelzi a promo módot.
- Feed riport bővítve: duplikált URL-ek számlálása (duplicateUrlCount/duplicateUrlSamples) a feed quality exportban.
- Dashboard finomítás: átlagolt confidence/reliability/ngo-impact score, futási idő (runDurationMs), promoRadar/catalog/direct bontás extra mezőkkel.
- Timeout tanulság: nagy feed + Playwright mellett 120 mp limit kifuthat; gyorsabb teszthez kisebb feedlista vagy hosszabb CLI timeout javasolt (PLAYWRIGHT=0 csak feed-parse-ra).

### Következő teendők (haladó)
- Multi-hop tovább finomítani (popup/új tab intercept, redirect chain log mentése) + shop-mapping tábla (Arukereso partnerlista → shop domain).
- MerchantId/registry alapú mapping erősítése (Dognet merchant → registry slug) a slug nélküli bucket csökkentésére.
- Dedup lazítása Arukereso feedre vagy feed-specifikus kulcs (url+merchantId), hogy ne hulljon el a katalógus radar jellegű adat.
- Ha elérhető merchant-specifikus Dognet feed, preferálni a kategória feed helyett (közvetlen shop domainnel).
- Legutóbbi futás (2025-11-26, PLAYWRIGHT=1, PROMO_MODE=0, follow ON, 1 feed, max 50, domain limit 5): 50 promó (2 dognet_feed + 48 arukereso_scrape); offer-follow továbbra is csak arukereso/adhost (ok=0, fail=2); slug nélküli 2 db; domain limit 5; feed OK, DOGNET_ARUKERESO_FEED_URL hiányzik, a feedlist env-ből jött; dedup drop 2. A katalógus URL-ek maradtak, shop domainre nem jutott át a follow.
- Leválasztási pont: `ARUKERESO_ENABLED=0` esetén az egész Árukereso harvester átugorható, üres kimenettel és rövid summary/dashboard-dal tér vissza. Visszakapcsolás: `ARUKERESO_ENABLED=1`. Ez a kapcsoló nem érinti a baseline coupon-harvester modult.

## Sale event és Gmail Promotions megjelenítés
- Kód nélküli akciók bent maradnak `saleEvent=true`, `type=sale_event` jelöléssel; front/pipeline oldalon külön “akció” típus/ badge kell, ne várj “Kód:” mezőt. A kuponkódos találatok `type=coupon_code`.
- A coupon-page harvester “extra” módban fut, így a /kupon /akcio /kedvezmeny oldalak gyorsan hoznak releváns sale_event vagy coupon_code találatokat.
- Gmail Promotions: van Gmail API az AI Agentnél; a harvesterben elérhető a JSON-LD parser (`gmail_structured`). Következő lépés az AI agentben: Promotions label lekérése a Gmail connectorral, HTML-ből JSON-LD kiolvasása, majd `source=gmail_structured` rekordként betöltés (magasabb reliability). A kód nélküli akciók itt is `sale_event` típusként jelenjenek meg.
- Gmail API bekötés (ARUKERESO_GMAIL_API_ENABLED=1): a Promotions/Updates levelekből JSON-LD-t olvasunk ki, shop domainhez kötjük (From/seller), reliability ~0.85, `source=manual`, `type=coupon_code` ha van kód, különben `sale_event`.

## Kupongyűjtő oldalak (opcionális, registry-s partnerekre szűrve)
Ha plusz kuponforrást akarsz (Dognet/CJ/Árukereső shopokra szűrve), érdemes a scrape/CSE/Playwright listába felvenni az alábbi gyűjtőoldalakat, de a kimenetben csak akkor tartsd meg a találatot, ha a shop domain/slug egyezik a registry-ben lévő partnerrel:

- Kuponkodok.hu – https://kuponkodok.hu/
- Kuponkodok.org / kupon-kodok.org – https://www.kuponkodok.org/ , https://www.kupon-kodok.org/
- Kuponkódom.hu – https://www.kuponkodom.hu/
- KUPLIO.hu – https://kuplio.hu/
- eKedvezmeny.hu – https://www.ekedvezmeny.hu/
- Magyar-kupon.com – https://www.magyar-kupon.com/
- Kuponhu.com – https://www.kuponhu.com/
- Kupone.hu – https://www.kupone.hu/
- Coupert (HU) – https://hu.coupert.com/

Élmény/utazás fókusz (kód ritkább, inkább akció/sale):
- Bónusz Brigád / KuponBrigád – https://www.bonuszbrigad.hu/ , https://kuponbrigad.hu/
- MaiKupon.hu – https://maikupon.hu/
- Alkupon.hu – https://www.alkupon.hu/
- Kuponguru.hu – https://kuponguru.hu/
- Kuponvilág.hu – https://kuponvilag.hu/

Meta-aggregátorok:
- Qponverzum – https://www.qponverzum.hu/
- KuponWebshop – https://kuponwebshop.hu/
