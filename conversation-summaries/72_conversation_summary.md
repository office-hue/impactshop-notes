# 72. Beszélgetés összefoglaló: AI batch prompt QA futtatás

## Áttekintés
A Sonnet-féle tréning prompt csomaghoz készített batch scriptet futtattam le a cp40-es AI Agent szolgáltatáson (Szia / Állatvédő szervezet / Hogyan döntöd el... / asdfghjkl / Nem akarok vásárolni, csak átláthatóság).

## Eredmények
- `Szia`: welcome flow rögtön „nincs kupon” hibát jelez, nincs 3 opciós menü.
- `Állatvédő szervezetet szeretnék támogatni`: nincs konkrét NGO ajánlat, csak általános videós támogatás fallback.
- `Hogyan döntöd el, melyik NGO-t ajánlod nekem?`: hiányzik az 5 lépéses döntési struktúra, csak visszakérdezés történik.
- `asdfghjkl`: fallback válasz túl általános, nincs „próbáld újra” CTA.
- `Nem akarok vásárolni, csak érdekel az átláthatóság`: továbbra is shop promókat listáz, nem kínál Impact riport + Fillout útvonalat.

## Következő lépések
1. Setup prompt frissítése: kötelező welcome menü, 5 lépéses döntési sablon, transzparencia-first fallback.
2. Állatvédős/videós flow-k finomhangolása, hogy mindig legalább 2 NGO + CTA jelenjen meg, ha nincs kupon.
3. Off-topic/fallback blokkokban jelenjen meg konkrét retry kérés vagy Fillout CTA.
