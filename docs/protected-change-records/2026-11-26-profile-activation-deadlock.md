# Sharity-profilbelépés és az aktiváló tranzakció holtpontja

A november 25-i 22:55:03-as hostingnapló a függő eszközjogosultság
aktiváló UPDATE műveleténél holtpontot mutatott (budapesti idő).
A kód ezen az ágon globális profiltiltást állít be. A kapcsoló írásának
pontos időpontja külön nem igazolható. A november 26-i helyreállítás után
a központi ütemezett ellenőrzés nyitott kaput, a JYSK monitorprofilja
sikeres belépést és visszatérést igazolt.

## Érintett fájl és kockázat

`wp-content/mu-plugins/impactshop-identity-panel.php`

A meglévő WordPress MU-plugin egyetlen védett fájljában a profil-GET és a
HTML profilfeloldó ugyanazt az aktiválást használja. A JYSK visszatérési út
a profilállapot után ugyanabban a kérésben ismét owner-jogot vizsgál.
A jogosultsági ellenőrzést ezért a közös tárolókapunál is le kell állítani.
Ismeretlen hibánál, bizonytalan rollbacknél vagy sikertelen sütilejáratásnál
továbbra is a központi védelem érvényes. A tranzakció és a sütik hitelesítési
feltételei a meglévő kapuk alá tartoznak. A kockázat a globális belépési
kiesés, illetve hibás elkülönítésnél a profiladat vagy JYSK-handoff téves
engedélyezése. Az aktiváló SQL helyén szükséges az additív hibafeltétel,
mert itt áll még rendelkezésre a driver hibakódja.

## Helyi ellenőrzés

A célzott HTTP-próba stock WordPress 6.9.4 wpdb-vel, PHP 8.4.25-tel és
eldobható MariaDB 10.11.19-cel futott. A valódi holtpont a végső UPDATE-et
érte. A válasz visszavonta a két sütit, eltakarta a profiladatokat és
elutasította az ismételt owner-vizsgálatot és a JYSK handoffot. A korábbi
grant megmaradt, a másik kérés rendben aktiválódott. A 1205, a sikertelen
rollback és a sikertelen sütitakarítás esetén a globális védelem megmaradt.

## Kiadás és elfogadás

A meglévő Bástya exact-file kiadó stagingre, majd az éles hostingra csak
az identity PHP-fájlt telepíti, friss előállapot-lenyomattal és privát
mentéssel. Rollback a kiadási azonosítóval és az aktuális kiadási SHA-val
a guard-rollback útvonalon. A telepítési állapotot lemezlenyomat, a kód
betöltését szerveroldali futási bizonyíték, a működést természetes profil-
és JYSK-monitor igazolja. A stagingen a profilválasz, a sütik, az owner
kapu, a JYSK visszatérés és a már használt védett release ellenőrzések
tartoznak a kiadási próbához.

Kézi UI ellenőrzőlista. JYSK → Profil → belépés meglévő fiókkal → visszatérés
ugyanabba a játékfiókba. A játék fiókjelzője a megfelelő profilt mutassa.
Ellenőrizd, hogy a profil újratöltésekor az egyenleg és a profilnév olvasható,
és nincs belépési vagy átirányítási hurok.
