# Offerwall Article Quiz – Implementacios terv

## Cel
Uj offerwall feladat tipus: cikk elolvasasa + 3 kerdeses kvíz. Jutalom csak sikeres submit utan. A feladat legyen gyors, mobilbarat, es uj cikkekkel bovitheto.

## Forras (articles_quiz.csv)
Elvart mezok:
- id, title, link, summary
- question1..3, q1_a..q1_d, correct1 (es ugyanez 2/3)

Megjegyzes: a CSV valojaban 1 darab quoted sor lehet, ';' a sor vege. Parsingkor kezeld:
1) Sorok olvasasa `;` delimiterrel
2) Ha 1 oszlopos sor jon vissza, akkor azt ujra parsolni `,` delimiterrel es `quotechar="`"`-ral

## Adatbeolvasas -> JSON
1) Ingestion script (python) -> `wp-content/mu-plugins/impactshop-offerwall-article-quiz-data/articles_quiz.json`
2) JSON schema (egy cikk):
   - id, title, link, summary
   - questions: [ {label, options, correct} x3 ]
   - tags (opcionalis: tema/neh. szint/forras)

## UI / UX (shortcode)
Shortcode: `[impactshop_article_quiz]`
Flow egy blokkon belul:
1) Cikk kivalasztas (rotacio/veletlen) + rovid summary
2) “Cikk megnyitasa” gomb (uj tab)
3) 3 kerdes egy blokkban (egyenkent vagy egy oldalon)
4) Eredmeny + “Pontok jovairasa” gomb
5) Uj kvíz inditasa (opcionalis)

UX vedelmek:
- Minimum olvasasi ido (pl. 20-30 mp) a submit elott
- Valasz opciok sorrend randomizalasa
- Egy sessionben ugyanaz a cikk ne ismetlodjon

## Backend
### Uj MU plugin
- `wp-content/mu-plugins/impactshop-offerwall-article-quiz.php`
- Feladat: adatbetoltes, shortcode render, REST submit endpoint

### REST endpoint
`POST /impact/v1/offerwall/article-quiz/submit`
Payload:
- survey_token
- quiz_id (cikk ID + attempt)
- answers (A/B/C/D)
- answers_correct (bool array)
- question_count (3)
- time_spent_sec

### Rewards / postback
Uj provider javasolt: `internal_article_quiz`
- Sajatos postback secret
- Rate limit (pl. 10/ora)
- Reward calc: payout=1 -> 10 pont + 10 szavazat (egyeztesd)

### Segment mapping
A cikk kvíz **nem profiloz**, csak jutalmaz. Opcio:
1) Kulon provider/handler, nincs segment update
2) Ha reuse kell: `QUIZ_ARTICLE` kategoriat add a mappingbe, update_type: `skip`, es az apply_rule-ben ignore

## Anti-fraud
- Min idotartam kerdesek elott
- Session + pseudo_id alapu rate limit
- Duplikalt quiz_id blokkolasa
- Ha 3/3 rossz, opcionalsan kisebb payout (V2)

## Admin / tartalomfrissites
- CSV -> JSON konverter script
- Verziozott JSON es diffelheto

## Teszt / smoke
- 1 cikk, 3 kerdes, helyes/hibas valasz
- Reward postback OK
- Duplikalt submit elutasitas
- Min read time enforced

## Kockazatok
- CSV formatum ingadozo (1 oszlopos quoted sor) -> parser robust legyen
- Linkekben vesszok/quoting -> csak csv modul hasznalata
