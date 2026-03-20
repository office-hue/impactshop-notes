# Offerwall Survey – Kerdesosszeallitasi modszer

## Cel
Edukacios, tobblepcsos kerdoiv, ami a valaszok alapjan agazik es fokozatosan nehezedik. A kerdesbank folyamatosan bovitheto uj forrasokbol (docx, csv). A flow 5 kerdeses blokkokban fut, blokkonkent kuld pontokat.

## Forrasok
- **Docx kerdeslista**: alap kerdesek (mindennapi, edukacios, attitud/viselkedes).
- **Batch5 CSV**: minosegi, kognitiv tipussal, delayed pair-rel es kovetkezetesseg proba kerdesekkel.

## Kimeneti formatum (survey_questions.json)
Minden kerdes egy rekord, fo mezoivel:
- `id`: egyedi azonosito
- `question_category`: KN_GENERAL / ATT_GENERAL / BEH_GENERAL (altalanos poolok)
- `label`: kerdes szovege
- `options`: A-D valaszok
- `correct`: helyes valasz betuje (ha van)
- `segment`: SUST / ENV / SOC / DON (tematikus irany)
- `subsegment`: kulcsszavas besorolas (pl. WASTE, ENERGY, DONATION)
- `difficulty`: 1–4
- `cognitive_type`: knowledge / behavior / attitude
- `delayed_pair_id`, `consistency_probe` (ha relevans)
- `batch`: 0 (docx) vagy 5 (batch5)

## Docx-bol epites (heurisztika)
1. **Parsing**: a docx egyetlen, `;`-vel tagolt szoveg. A fejléc: `Kerdes;A;B;C;D;Helyes valasz;Szegmens;Suly`.
2. **Kognitiv tipus**:
   - `behavior`: gyakorisag, szokas, mit teszel, hogyan csinalod tipusu mondatok.
   - `attitude`: mennyire, mit gondolsz, velemeny, fontosnak tartod, hajlando vagy.
   - `knowledge`: egyeb, definicio/teny jelleg.
3. **Segment** (SUST/ENV/SOC/DON): a docx “Szegmens” mezo magyar megnevezesebol.
4. **Subsegment**: kulcsszavas tematikus besorolas (pl. hulladek, energia, viz, kozlekedes, klima, biodiverzitas, adomanyozas, tarsadalmi felelosseg).
5. **Difficulty**: knowledge kerdesnel hossz/ritka kifejezesek (pl. sztratoszfera, biodiverzitas) alapjan 2–3; attitud/behavior 1.

## Batch5-bol epites
- A batch5 mar tartalmaz `cognitive_type`, `difficulty`, `delayed_pair_id`, `consistency_probe` mezoket.
- Ezeket atvesszuk valtozatlanul.
- `question_category` itt is altalanos poolokra van egyszerusitve (KN_GENERAL / ATT_GENERAL / BEH_GENERAL).

## Flow (5 kerdes/blokk)
Sorrend blokkon belul (edukacios iv):
1. **Intro**: behavior vagy attitude (konnyu, belevezeto)
2. **Knowledge**: teny/definicio
3. **Knowledge**: teny/definicio
4. **Apply**: viselkedes vagy helyzeti kerdes
5. **Reflect**: attitude vagy behavior (osszefoglalo)

## Elagazas logika (valaszfuggo)
- **Track**: `segment` es `level` (1–4) folyamatosan frissul.
- Ha helyes a valasz: level +1, ha hibas: level -1.
- A kovetkezo kerdes elsodlegesen a jelenlegi segmentre es a celzott nehezsegre szurik.
- Ha nincs eleg kerdes, akkor lazul a szuro (segment → tipus → barmi).
- **Delayed pair**: ha van `delayed_pair_id`, a parja 2 kerdes mulva preferaltan jon.

## Pontozas
- 5 kerdes/blokk utan a felhasznalo ranyom a “Pontok jovairasa” gombra.
- Ekkor kuldjuk el a blokkon beluli valaszokat, a jutalom ennek a sikeres postbackjenel irodik jova.

## Bovithetoseg
- Uj docx/csv kerdeslista beadhato a generatornak.
- A kimeneti json automatikusan bovul.
- A flow nem “fix listat” hasznal, hanem a bankbol valogat, igy a bovites azonnal hasznosul.

## Kutatasi megallapitasok beemelese (hasznos reszek)

### GDPR / privacy-by-design
- A tokenes es valasz alapú profilozas pseudonym adatnak minosul, ezert szemelyes adatkent kezelendo.
- Cel: minimalis adatgyujtes, atlathatosag, belso szemelyre szabas es partner fele csak aggregalt szegmensek.
- Kulon consent a belso szemelyre szabashoz es a partner ajanlatokhoz.

### Dimenziok (kerdesbank strukturahoz)
Javasolt dimenziok 8-10 modulban, mindegyikben knowledge + attitude + behavior + motivacio/akadaly reteg:\n
1) Alap profil (eletkor sav, megye, telepules tipus, vegzettseg)\n
2) Szocioekonomiai hatter (csak savosan, ovatosan)\n
3) Fenntarthatosagi tudas (klima, energia, kozlekedes, hulladek, viz, biodiverzitas)\n
4) Attitud / erzelmi viszony (aggodas, onhatekonyseg, bizalom)\n
5) Tenyletes viselkedes (szokasok)\n
6) Kozossegi reszvetel / onkentesseg\n
7) Adomanyozas (forma, gyakorisag, okok)\n
8) Nem adomanyozas okai (bizalom, penz, ido, informaciohiany)\n
9) Media / informacios csatornak (opcionalis)\n
10) Sharity-specifikus: gamifikacio es kuldetes preferenciak\n

### Adaptiv kerdezes + planned missingness
- A kerdesbankbol adaptiv valogatas: a leginformatívabb kerdes kovetkezik, nem mindenkinek ugyanaz.
- Planned missingness: 1000 kerdesbol minden user csak egy tervezett reszhalmazt kap (pl. 5 kerdes/blokk), a populacios becsles igy is stabil.

### Erzekeny kerdesek kezelese
- Erzekeny temaknal indirekt kerdezes (RRT / crosswise modell) vagy skip opcio.
- Jatekositott, nem bunteto jellegu figyelemellenorzes.

### Adatminoseg vedelmek
- "Nem tudom / kihagyom" opcio a tudas kerdeseknel.
- Valasz opciok sorrendjenek randomizalasa (ahol lehet).
- Valaszido es mintazat figyeles (flag, nem buntetes).

### Reprezentativitas
- A minta nem valoszinusegi, ezt a riportokban mindig jelezni kell.
- Post-stratifikacios sulyozas (kor, vegzettseg, regio) javasolt.

### Targetalas (privacy-barat)
- Targetalas csak szegmensekbol, nem nyers valaszokbol.
- Partner fele csak aggregalt, minimum csoportmeret felett.
- Kiskorunak ne legyen profilos reklam.

## Peldak

### 1) Blokk pelda (5 kerdes)
1. Intro (behavior): “Mit hasznalsz bevasarlaskor?”
2. Knowledge: “Mit jelent a fenntarthato fejlodes?”
3. Knowledge: “Melyik NEM megujulo energiaforras?”
4. Apply (behavior): “Milyen gyakran utazol repulovel?”
5. Reflect (attitude): “Mennyire tartod fontosnak a fenntarthato eletmodot?”

### 2) Elagazas pelda (valaszfuggo)
- Ha a felhasznalo a knowledge kerdesre helyesen valaszol: `level` +1 → kovetkezo knowledge kerdes nehezebb.
- Ha rosszul: `level` -1 → kovetkezo knowledge kerdes konnyebb.
- A dominans `segment` (pl. ENV) elonyt kap a kovetkezo kerdesekben, amig van eleg megfelelo kerdes.

### 3) Delayed pair pelda
- Kerdes A (delayed_pair_id = DP-01): “Az EU hulladekhierarchiajaban mi az elso lepes?”
- Kerdes B (DP-01 parja): “Emlékszel? Mi jon kozvetlenul a megelőzes utan?”
- A parkerdes 2 lepes mulva preferaltan jon, ha meg nem volt kerdezve.

### 4) Bovithetoseg pelda
- Uj docx: +200 kerdes → ujrageneralas utan azonnal bekerul a bankba.
- Flow automatikusan szetdobja a kerdeseiket a megfelelo stage-ekbe (intro/knowledge/apply/reflect).
