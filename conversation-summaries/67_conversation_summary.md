# 67. Beszélgetés összefoglaló: AI training prompt futások

## Áttekintés
A friss training pack alapján lefuttattam az 5 gyakorló promptot a cp40-es `ai-agent` szolgáltatáson, hogy rögzítsük az aktuális viselkedést és látható legyen, hol kell további prompt/flow finomhangolás.

## Fő eredmények
- "Mutasd meg a szervezeti TOP listát" → Butopea + Topjuicers ajánlatok, 35 Ft adománnyal; nincs REST/leaderboard hivatkozás.
- "Videós támogatást szeretnék" → általános magyarázat videós kampányokról, konkrét kampánylistát nem adott.
- "Hogyan döntesz, melyik NGO-t ajánlod?" → csak kategória-választékot sorol, az 5 lépéses mérlegelést nem részletezi.
- "Nincs shop, csak átláthatóság érdekel" → továbbra is shop ajánlatokat listáz (NoraFashion, Tokshop, Yves Rocher), nem ugrik át impact riport / Fillout opcióra.
- "Van-e kupon a Lampakhoz?" → helyes fallback: jelzi, hogy nincs kupon, videós támogatást ajánl.

## Következő lépések
- Prompt/flow tuning: (1) a ranglista kérdésnél REST linkeket és max. 3 ajánlatot kérni; (3) válaszba illessze az 5 lépéses döntési mechanizmust; (4) transzparencia esetén preferálja az átláthatósági flow-t.
