# ChatGPT beszélgetés - Impact Shop fejlesztés
**Dátum**: 2025-10-01
**Cél**: WordPress alapú Impact Shop létrehozása affiliate marketing + adomány funkcióval
**Status**: Részben megoldva - technikai stack kiválasztva

## Probléma leírása
- ChatGPT nem őrzi meg a projekt memóriát beszélgetések között
- Hosszú kódok, hibás megoldások
- WordPress Impact Shop fejlesztése Dognet affiliate hálózattal
- NGO választási mechanizmus szükséges

## ChatGPT megoldása

### Technikai stack:
1. **WordPress pluginek**:
   - Redirection (ingyenes) - Pretty Links helyett
   - WPCode - Header & Footer kódokhoz
   - Site Kit by Google - GA4 analitikához

2. **NGO választó rendszer**:
   - Tally űrlap (tally.so) - dinamikus átirányítással
   - Rejtett mezők: shop, amb (nagykövet kód)
   - Redirect on completion: `@ngo_code` beillesztés

3. **Link struktúra**:
   ```
   /go/emag → Dognet link data1=NGO_kód
   /go/amazon → Dognet link data1=NGO_kód
   /go/pepita → Dognet link data1=NGO_kód
   ```

4. **Paraméter átadás**:
   - Dognet: `data1` (nem subid1/subid2)
   - UTM tracking: `utm_source=sharity&utm_medium=impactshop`

## Tesztelés eredménye
✅ **Működik**:
- Redirection plugin linkek
- Tally NGO kód átadása
- Dognet data1 paraméter

❌ **Problémák voltak**:
- @ngo_code szövegesen jelent meg (calculated field logika hiba)
- data1 nem látszott click listában (d1 vs data1 paraméter)

## Következő lépések
1. Tally űrlap finomhangolása minden partnerre
2. WordPress oldalon partner kártyák linkjének beállítása
3. Analytics és mérés konfigurálása
4. Apple/Google Wallet kártyák létrehozása (opcionális)

## Kapcsolódó fájlok
- [x] `chatgpt-history/README.md` létrehozva
- [x] `.github/copilot-instructions.md` frissítve
- [x] `notes.md` frissítve
- [x] `WORKFLOW.md` és `wordpress-helpers.md` létrehozva

## GitHub Copilot notes
- **Főelőny**: Projekt memória megőrzése beszélgetések között
- **Kontextus**: Minden fájl és korábbi döntés elérhető
- **Technikai részletek**: Dognet data1, Tally dinamikus átirányítás
- **Következő**: WordPress oldal további fejlesztése a strukturált tervek alapján