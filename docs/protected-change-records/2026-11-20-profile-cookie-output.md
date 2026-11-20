# Sharity-profilbelépés és a Legacy MU-fájl kimenete

Az éles PHP-napló 2026. november 19-én 23:14-kor és november 20-án 09:23-kor budapesti idő szerint a `deals_shortcode_fixed.php` első sorára vezette vissza a profil sütiírási hibáját. Az éles fájl és a WordPress-repó azonos, egyetlen újsort tartalmazó fájlt adott. A meglévő integritásvédelem a sikertelen sütiírás után ismét lezárt.

A jóváhagyott módosítás ebben az egyetlen éles fájlban az újsort kimenetmentes PHP-helyőrzőre cseréli. A fájl nem definiál szolgáltatást vagy hookot. A kockázat a megosztott MU-betöltés és a profilválasz fejléceinek folytonossága. A profilhívás, a JYSK-visszatérés, az általános WordPress REST és a meglévő jogosultsági ellenőrzések adják a kiadási ellenőrzés körét. Shortcode, pont, szavazat, cron, séma és jutalmazási folyamat nem része a változásnak.

A helyi és hosting PHP lint sikeres. A jelölt fájl standard kimenete 0 bájt. Forráskész, éles elfogadás még függő.

A meglévő exact-file kiadás rögzítse az eredeti fájl hashét, privát mentését és a rollback manifestet. Az éles fájlt cserét követően hash és mód szerint olvassuk vissza. A runtime-önellenőrzés és a tranzakciós tároló ellenőrzése után privát előképpel állítható vissza a központi kapcsoló. A fájl és a kapcsoló külön visszaállítható.

Éles ellenőrzéskor ismételt anonim belépésikapu-próba, opció-visszaolvasás és JYSK bootstrap szükséges. A kézi próbán a meglévő Sharity-fiók sikeresen kapcsolódik, a JYSK-ról indítva pedig ugyanez a fiók jelenik meg visszatéréskor. A hitelesítő adat nélküli próba csak a belépési kapu állapotát igazolja.
