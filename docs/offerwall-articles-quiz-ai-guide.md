# Offerwall cikk kvíz – AI munkautasítás (gyűjtés + kérdéssor összeállítás)

Ez a feladatleírás azt írja le, hogyan gyűjts új cikkeket és hogyan készíts hozzájuk kvíz-kérdéssorokat úgy, hogy azonnal és biztonságosan integrálható legyen.

## 1) Cikkgyűjtés – források és linkek

### 1.1 Forráselvek
- Csak nyilvánosan elérhető cikkeket vegyél fel (ne legyen regisztráció / előfizetés / paywall).
- Kerüld a dinamikusan eltűnő hivatkozásokat (pl. kampányoldalak, törékeny UTM-es preview linkek).
- Részesítsd előnyben a tartós, stabil publikációkat (hosszabb távon is elérhetők).

### 1.2 Linkellenőrzés (kötelező)
Minden cikklinket ellenőrizz:
- 200-as státuszkód (a link betölthető).
- Nem kér bejelentkezést/regisztrációt.
- Nem dob át fizetős falra (paywall).
- A tartalom tényleg a cikket mutatja (nem listázó, nem kategória oldal).

Ha egy link bizonytalan (pl. Qubitnél sok cikk paywall mögé kerülhet), akkor:
- vagy válassz másik cikket,
- vagy ellenőrizd manuálisan több eszközről is, hogy publikus marad-e.

### 1.3 Link normalizálás
- Egy cikk = egy link.
- Ne hagyj a linkben UTM, tracking vagy hosszú query paramétereket (tisztítsd).
- A link legyen rövid, tiszta, stabil.

## 2) Cikk kiválasztási kritériumok
- Téma: fenntarthatóság, környezet, közösségi felelősség, tudományos/edukációs tartalom.
- Legyen egyértelmű tényállítás, amiből 2–4 kérdés megbízhatóan levezethető.
- Kerüld a túlzottan szakmai/akadémiai írásokat, ha a kérdések nem lesznek közérthetők.
- Ne legyen félreérthető vagy erősen vélemény-alapú (kvízhez tény kell).

### Tiltott forrástípusok
- Paywallos vagy regisztrációhoz kötött cikkek.
- Preview/teaser oldalak, ahol a teljes tartalom nem látszik.
- Kampány landingek, rövid életű promóciós oldalak.
- Aggregátor/lista oldalak (nem konkrét cikk).

### Link ellenőrző parancs (javaslat)
```
curl -I "URL"
```
Elvárás: 200-as státusz és nincs átirányítás paywall/login oldalra.

## 3) Kérdéssor összeállítás

### 3.1 Kérdésszám
- 1 cikk = 3 kérdés (fix).

### 3.2 Kérdéstípus
- Mindig feleletválasztós (A–D).
- Egy helyes válasz legyen.
- A rossz opciók legyenek életszerűek, de egyértelműen tévesek a cikk alapján.

### 3.3 Minőség és érthetőség
- A kérdések legyenek egyértelműek, rövidek, közérthetők.
- Ne legyenek túl szakmaiak.
- Ne építs rá olyan külső tudást, ami nincs a cikkben.
- Egy kérdés csak egy állítást fedjen le.

### 3.4 Ismétlés tiltása
- Ne legyen két kérdés ugyanarról a mondatról újrafogalmazva.
- A 3 kérdés lehetőleg a cikk 3 különböző pontjára épüljön.

## 4) Tartalomellenőrzés
Mielőtt véglegesíted a kérdéssort:
- Ellenőrizd, hogy a kérdés–válasz párok pontosan visszavezethetők a cikkre.
- Ne legyenek trükkös vagy „becsapós” kérdések.
- A helyes válasz legyen egyértelműen igazolható.

## 5) Integrációs formátum (amit végül átadsz)

Minden cikket ilyen mezőkkel adj át:
- `title`: cikk címe (röviden)
- `summary`: 1–3 mondatos összefoglaló (nem idézet, saját szöveg)
- `link`: tisztított cikk URL
- `questions`: 3 kérdés, mindegyik:
  - `label`: a kérdés szövege
  - `options`: A–D opciók
  - `correct`: helyes opció betűjele (A/B/C/D)

## 6) Példa (sablon)

```
{
  "title": "...",
  "summary": "...",
  "link": "https://...",
  "questions": [
    {
      "label": "...?",
      "options": {
        "A": "...",
        "B": "...",
        "C": "...",
        "D": "..."
      },
      "correct": "B"
    },
    {
      "label": "...?",
      "options": {
        "A": "...",
        "B": "...",
        "C": "...",
        "D": "..."
      },
      "correct": "D"
    },
    {
      "label": "...?",
      "options": {
        "A": "...",
        "B": "...",
        "C": "...",
        "D": "..."
      },
      "correct": "A"
    }
  ]
}
```

## 7) Végső ellenőrző lista
- A link betöltődik 200-as státusszal.
- Nem paywall / nem regisztráció-köteles.
- A kérdések tényleg a cikkből származnak.
- 3 kérdés van.
- Pontosan 1 helyes válasz minden kérdésnél.
- A link tiszta (nincs tracking paraméter).

## 8) Elfogadási kritériumok (kötelező kitöltés cikkenként)
Minden cikkhez add meg ezt a rövid blokkot is:

```
Acceptance checklist:
- link_status: 200 / egyéb
- paywall_or_login: no/yes
- link_clean: yes/no
- questions_count: 3
- correct_answers: 3/3
- notes: (ha bármi gyanús)
```

Ha bármelyik pont nem teljesül, a cikk NEM felvehető.

### Minta kitöltés (példa)
```
Acceptance checklist:
- link_status: 200
- paywall_or_login: no
- link_clean: yes
- questions_count: 3
- correct_answers: 3/3
- notes: -
```

## 9) Minta cikk + 3 kérdés (példa)
```
{
  "title": "Fenntartható közlekedés: miért fontos a biciklizést támogatni?",
  "summary": "A cikk bemutatja, hogy a városi biciklizést támogató infrastruktúra csökkenti a közlekedési kibocsátást és javítja a levegő minőségét.",
  "link": "https://example.com/fenntarthato-kozlekedes-bicikli",
  "questions": [
    {
      "label": "Milyen hatást említ a cikk a biciklizést támogató infrastruktúra kapcsán?",
      "options": {
        "A": "Növeli a dugók számát",
        "B": "Csökkenti a közlekedési kibocsátást",
        "C": "Csökkenti a városi zöldterületek számát",
        "D": "Növeli a zajszennyezést"
      },
      "correct": "B"
    },
    {
      "label": "Mi javul a cikk szerint a biciklizést támogató fejlesztésekkel?",
      "options": {
        "A": "A levegő minősége",
        "B": "Az ingatlanárak azonnal csökkennek",
        "C": "A járművek átlagos mérete nő",
        "D": "A városi parkolók száma nő"
      },
      "correct": "A"
    },
    {
      "label": "Mire használja a cikk a biciklizést támogató infrastruktúra példáját?",
      "options": {
        "A": "Fenntartható közlekedési megoldások szemléltetésére",
        "B": "Új autópálya építése indoklására",
        "C": "Parkolási díj emelés igazolására",
        "D": "Sportturizmus reklámozására"
      },
      "correct": "A"
    }
  ]
}
```
