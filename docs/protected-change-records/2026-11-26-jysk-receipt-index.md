# A JYSK pontnyugták keresése

A hosting adatbázisa elérte a felhasználónkénti 30 kapcsolatot. A JYSK egyetlen nyugtájának kereséséhez az éles MariaDB mintegy 2,7 millió sor olvasását tervezte. A Vercel a küldésnél és a visszaolvasásnál is nyolc másodperces időtúllépéseket rögzített. Egy adott telefon visszatérésének pontos hibáját a napló nem azonosította.

Érintett fájl: `wp-content/mu-plugins/impactshop-jysk-game.php`. A jutalom elküldése és a nyugta visszaolvasása ugyanazt a SELECT-et használja. A keresés a meglévő összetett indexen minden tulajdonoscsoportot megvizsgál, a NULL csoportot is. Több egyezés ütközést jelez. Az éles EXPLAIN három becsült csoportot és csoportonként egy indexelt találatot mutatott. Az árva vagy más profilhoz tartozó nyugta kihagyása dupla jóváírást okozhatna. Ezt a vegyes tulajdonosú adatbázispróba ellenőrzi.

Kiadáskor a Mini a pontos merge-ből egyetlen PHP-fájlt telepít stagingre, majd élesbe. A kiadó ellenőrizze a korábbi fájl lenyomatát és őrizze meg a privát visszaállítási példányt. Rollback: a fájlkiadás azonosítójával és a telepített SHA-val az előző bájtok állíthatók vissza. Adatbázis-migráció nincs.

Éles ellenőrzőlista: a játék fogad részvételt, a legrégebbi nyugták rendeződnek, a természetes profilpróba visszatér a játékhoz. Mobilon a Sharity-belépés után ugyanaz a JYSK-profil legyen látható. A próba pont és szavazat nélkül elvégezhető.
