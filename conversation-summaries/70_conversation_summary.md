# 70. Beszélgetés összefoglaló: AI training pack prompt futások (08:24)

## Áttekintés
A feladat a cp40-es AI Agent szolgáltatáson futó 5 gyakorló prompt ismételt lefuttatása volt (`ssh sharityh@cp40.ezit.hu curl -sS -X POST http://127.0.0.1:4000/api/v1/chat/impi ...`), hogy lássuk a legfrissebb válaszokat és azonosítsuk a további tuning igényeket.

## Fő eredmények
- „Mutasd meg a szervezeti TOP listát” → két ajánlat (Butopea, Topjuicers) ~35 Ft adománnyal, CTA Fillout linkre mutat; REST/leaderboard hivatkozás továbbra sincs.
- „Videós támogatást szeretnék” → csak általános magyarázatot adott, konkrét videós kampánylistát nem.
- „Hogyan döntesz, melyik NGO-t ajánlod nekem?” → kategóriaalapú visszakérdezés, az 5 lépéses mérlegelés hiányzik.
- „Nincs shop, csak átláthatóság érdekel” → továbbra is shop promókat listáz (NoraFashion/Tokshop/Yves Rocher) ahelyett, hogy Impact riport + Fillout útvonalat kínálna.
- „Van-e kupon a Lampakhoz?” → helyes fallback (nincs kupon, videós támogatás / Fillout opció ajánlása).

## Következő lépések
1. Frissítsd a prompt sablont a training pack szerinti 5 lépéses döntési mechanizmus kötelező betöltésére.
2. Súlyozd felül a „transzparencia” / „nincs shop” kulcsszavakat, hogy azonnal Impact riport + Fillout CTA-ra tereljen shop ajánlatok helyett.
3. (Opcionális) ha szükséges, írd át a videós támogatás flow-t, hogy konkrét CTA-t adjon (pl. Fillout vagy legutóbbi videós kampány link).
