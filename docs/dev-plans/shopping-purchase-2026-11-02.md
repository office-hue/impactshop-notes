# Hitelesített Sharity vásárlási indítás

Jóváhagyott scope: Dognet, explicit Sharity-owner session, vásárlásonként választott aktív NGO, SAT1. Pénzügyi számítás nincs.
Érintett: affiliate-runtime két új REST út, explicit nullable oszlopmigráció és alapból OFF kapcsoló. Meglévő CJ/go/cron út szerződése marad.
Kockázat: rossz account/NGO/termék, párhuzamos dupla intent, bizonytalan commit. Védelem: meglévő owner auth + friss tranzakciós session/NGO ellenőrzés, egyedi requestkulcs és payload hash, commit readback. Nincs provider link API.
Backup: Git alap 6098a532; telepítéskor exact-file mentés, intenttábla és érintett opciók mentése. Rollback elsőként új lane OFF; létrejött intent/pontkötés nem törölhető.
Ellenőrzendő: új GET/POST, SQL-konkurencia/commit, régi affiliate és attribúció teszt, célzott Bástya. UI: login, más NGO, termékcél, duplakattintás, profilpreferencia változatlansága.
Aktiválás előtt a d1 → provider data1 megőrzést igazolni kell. E dokumentum nem deployment PASS.
Állapot: implementáció folyamatban; merge/deploy/aktiválás nem történt.
