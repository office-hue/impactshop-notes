# ImpactShop NGO kártya – használati és frissítési jegyzet

Ez a dokumentum összegzi, hogyan kell az ImpactShop Apple Wallet megosztási kártyáit előkészíteni és frissíteni, különös tekintettel a backFields CTA blokkra és a dinamikus `sharity_news` szövegre.

## 1. Alap workflow
1. **Sablon kibontása:** másold az `impactshop-share-card-base-bator.pkpass` állományt egy ideiglenes mappába (pl. `/tmp/share-pass-<slug>`), majd `unzip`-peld.
2. **`pass.json` szerkesztése:** a slugnak megfelelő adatokkal töltsd ki. A `storeCard.backFields` első eleme legyen a CTA blokk:
   ```json
   {
     "key": "cta",
     "label": "Impact Shop link",
     "value": "https://app.sharity.hu/impactshop/?d1=<slug>&ngo=<slug>&src=wallet-pass",
     "dataDetectorTypes": ["PKDataDetectorTypeLink"],
     "attributedValue": "<a href=\"https://app.sharity.hu/impactshop/?d1=<slug>&#038;ngo=<slug>&#038;src=wallet-pass\">Impact Shop megnyitása</a>"
   }
   ```
   - **Fontos:** a slugot minden mezőben cseréld a konkrét NGO azonosítóra (pl. `adamremenye`).
3. **`sharity_news` mező:** a backFields következő eleme `key: "sharity_news"` vagy `key: "note"`, és a `value` mezőbe az API által adott announcement kerüljön. Az értéket a `/impact/v1/ngo-card/<slug>` endpoint `announcement.text` mezőjéből kell beégetni, *nem* statikus sablonból (például: „Elindult a legújabb WIN4Good tombola…”).
4. **`announcement` mező (opcionális):** csak akkor add hozzá, ha a Sharity hírektől eltérő rendszerüzenetet kell kommunikálni (pl. karbantartás, manuális felhívás). Ha nincs külön mondanivaló, hagyd ki ezt a blokkot, így nem duplikáljuk a tartalmat a hátlapon.
5. **Manifest + aláírás:** generálj friss `manifest.json`-t (SHA1 hash minden fájlról), majd írd alá:  
   `openssl smime -sign -binary -in manifest.json -signer impactshop-prod-cert.pem -inkey impactshop-prod-key.pem -certfile AppleWWDRCA.cer -noattr -out signature`
5. **Újra-csomagolás:** `zip -r impactshop-share-card-<slug>.pkpass *` a temporális mappában.
6. **Hotfix szinkron:** futtasd a `scripts/hotfix-sync.sh impactshop-share-card-<slug>.pkpass <slug>` scriptet, hogy a szerverre kerüljön a frissített állomány.

### Gyorsított (szkriptelt) rebuild

- Segéd: `scripts/wallet/rebuild-share-pass.sh <slug> [rendszerüzenet]` – mindig az Ádám Reménye mintából (`wallet-pass-downloads/impactshop-share-card-template.pkpass`) indul, csak az API-ból érkező értékeket tölti be (összeg, rank, slugos CTA, tombola/videó link, Sharity hírek). Opcionálisan adhatsz meg külön „Rendszerüzenet” szöveget második paraméterként; ha nincs megadva, de az API `announcement.url` mezője szerepel, a script automatikusan a szöveg + URL kombinációt jeleníti meg a hátoldalon.
- Eredmény: `wallet-pass-downloads/impactshop-share-card-<slug>-<ts>.pkpass` + a canonical `impactshop-share-card-<slug>.pkpass`. Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-<slug>.pkpass`.
- Előfeltétel: `wallet-pass-downloads/tmp_rebuild/{cert.pem,key.pem,AppleWWDRCAG4.pem}` tanúsítványok + a base Bátor pkpass jelenléte.

## 2. Guardrail követelmények
- A CTA blokk hiánya „impactall guard” hibát vált ki: ennek ellenőrzése a `storeCard.backFields[0].key === "cta"` szabály alapján történik.
- A `sharity_news` mező értékének *meg kell egyeznie* az API által szolgáltatott `announcement.text` tartalmával. A guard figyeli az eltérést, mivel a Wallet pass hátlapja és az embed kártyák tartalma egységes kell legyen.
- Az opcionális `announcement` blokkot csak valódi rendszerüzenet esetén használd; ha nincs ilyen, hagyd el, különben feleslegesen ismétlődő szöveget kapunk.
- Minden módosítás után wallet pass cache prewarm + új letöltés szükséges a kvalifikált slug(ok)ra (pl. `wp impactshop ngo-card prewarm --slugs=adamremenye --variant=wallet`).

## 3. Gyakorlati példa – Ádám Reménye
1. Kibontva a sablont írd be a slugot: `adamremenye`.
2. A CTA blokk `value` mezője: `https://app.sharity.hu/impactshop/?d1=adamremenye&ngo=adamremenye&src=wallet-pass`.
3. A `sharity_news` mező `value` mezőjébe a legutóbbi announcement: `Elindult a legújabb WIN4Good tombola…` (idézőjel nélkül, HTML nélkül).
4. Ha nincs külön rendszerüzenet, *ne* adj hozzá `announcement` mezőt; ha van, ide jöhet pl. „Rendszerkarbantartás – adatfrissítés folyamatban”.
5. Manifest → `signature` → zip → `scripts/hotfix-sync.sh impactshop-share-card-adamremenye.pkpass adamremenye`.
5. Ellenőrzés: a Wallet hátlapján megjelenik a tappolható Impact Shop link és a friss „Sharity hírek” blokk.

Tartsuk naprakészen ezt a dokumentumot minden új slug vagy mezőkövetelmény esetén.
