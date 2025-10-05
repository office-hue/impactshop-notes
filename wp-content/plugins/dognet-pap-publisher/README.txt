
Dognet PAP Publisher Connector (WordPress plugin)

Mi ez?
- Publisher (affiliate) integráció a Dognet/PAP API-hoz (NEM a Dognet 2.0 Merchant REST-hez).
- Bannerek és linkek lehívása; SubID (data1, data2) és csatorna (chan) paraméterek automatikus hozzáfűzése.

Telepítés (röviden):
1) Menj a WordPress Vezérlőpultba → Bővítmények → Új hozzáadása → Bővítmény feltöltése.
2) Töltsd fel ezt a ZIP-et, majd aktiváld a bővítményt.
3) FTP/SFTP vagy tárhely- fájlkezelővel másold be a PAP API fájlt:
   wp-content/plugins/dognet-pap-publisher/includes/PapApi.class.php
   (Ezt a PAP API csomagból tudod kivenni.)
4) Vezérlőpult → Beállítások → Dognet PAP (Publisher): add meg a PAP szerver URL-t (pl. https://login.dognet.sk),
   az affiliate e-mail címed és jelszavad.
5) Helyezd el a shortcake-okat (példák a beállító oldalon is).

Megjegyzés: A feltöltött Dognet 2.0 PDF egy MERCHANT (hirdetői) REST API dokumentum, publisher oldalra nem ez kell.
