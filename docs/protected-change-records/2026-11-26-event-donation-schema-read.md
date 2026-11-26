# Az adományozási modul oszlopellenőrzése

A staging és az éles modul azonos PHP-fájlt használ. A WordPress az `init` alatt a már telepített 1.5.0 sémánál is tizenegy külön lekérdezést indított. A hostingon egy mintában ezek 493 ms-ot, az egyetlen teljes oszloplista 70 ms-ot vett igénybe. Ugyanezen az estén a JYSK profilátadása adatbázis-kapcsolati hibával tért vissza, és a WordPress a 30 kapcsolatos korlát elérését naplózta.

A változás az `impactshop-event-donation-widget.php` sémavizsgálatát érinti. Egy olvasásból továbbra is mind a tizenegy jegy-, igazolás- és átutalásoszlopot ellenőrzi. Régi telepítésnél a dbDelta után ugyanabban a sorrendben pótolja a valóban hiányzó oszlopokat. Olvasási vagy módosítási hiba után az új verzió bejegyzése elmarad. A következő kérés újraolvassa a tényleges oszlopokat. A hét célzott eset a szokásos betöltést, a hibaágakat és a részleges módosítás utáni folytatást fedi le.

Kiadáskor a Mini a pontos merge-ből telepíti ezt az egy PHP-fájlt, előbb stagingre. Mindkét célon ellenőrzi a korábbi és az új lenyomatot, és megőrzi a korábbi fájl privát mentését. Visszaállításkor az adott fájlkiadás azonosítója és a telepített SHA az előfeltétel.

Felületi ellenőrzéshez nyisd meg az esemény adományozási felületét, és ellenőrizd, hogy megjelenik az összegválasztó. Mobilon a JYSK „Van profilom – kapcsolódás” gombja után ugyanaz a fiók jelenjen meg a játékban. A technikai ellenőrzéshez nincs szükség fizetésre vagy új játékaktivitásra.

Az érintett fájl új lenyomatát a `docs/impactshop-guard-hashes.json` és a `docs/impactshop-guard-hashes.sha256` rögzíti. A rollback és az éles ellenőrzőlista az előző két bekezdésben található.
