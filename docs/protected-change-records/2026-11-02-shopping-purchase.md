# Megerősített Sharity vásárlási indítás

## Felhatalmazás, koherencia, érintett fájlok

Arnold jóváhagyott Dognet-terve, 2026-11-02. Saját worktree:
`impactshop-shopping-purchase-20261102`, induló HEAD `6098a532`.
A profil-visszatérés kétfájlos bővítését a hiányzó vásárlási visszatérés
igazolása után Arnold külön jóváhagyta. Nincs adományszámítás-módosítás.

Protected files touched:
- `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`
- `wp-content/mu-plugins/impactshop-identity-panel.php`
- `wp-content/mu-plugins/impactshop-identity-panel.js`
- `scripts/sharity-affiliate-runtime-bastion-guard.sh`
- `docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`

Kapcsolatok: plugin olvasótool → webes termékátadás → meglévő owner-session
belépés → vásárlásonkénti NGO-megerősítés → szolgáltatásként hitelesített
WordPress POST → pontszámla-kötés → egyetlen intent → Dognet terméklink.
A profil preferenciája és a pénzügyi mozgások nem változnak. Az egyetlen új
adatbázismező nullable `request_payload_hash`; kizárólag explicit
`wp sharity affiliate migrate-shopping` migrálja. Új kapcsoló
`impactshop_sharity_shopping_purchase_enabled`, alapértelmezés OFF.

A megerősítés UUID-ja a hiteles affiliate-subjecthez kötött. A payload hash
összeköti a terméket, NGO-t, partnert, programot, tényleges Dognet-paramétereket
és tájékoztatóverziót. Azonos ismétlés ugyanaz a link; változás 409; 15 perc
után 410. A kiadott hozzárendelés 45 napig marad. `redirected` csak a link
kiadását jelenti. Nincs tárolt nyers termék-URL vagy SAT1, nincs HTTP DDL.

Konkrét kockázat: rossz felhasználó/NGO/cél, dupla intent, bizonytalan commit,
profil-visszatérés regresszió. Meglévő owner-grant ellenőrzés és tranzakciós
session/NGO-zár, egyedi requestkulcs, kapcsolatvédelem és tartós readback kezeli.
A meglévő pontkötés saját tranzakciója az intent előtt fut, nincs nested transaction.
A két új profilcél rögzített allowlist; a FactLens-cookie és prctx elsőbbsége
megmarad, dinamikus külső return URL nem fogadható el.

## Tényleges ellenőrzés

- `php tests/sharity-affiliate-runtime-test.php`: régi és új út sikeres.
- `php tests/impactshop-points-attribution.test.php`: kompatibilitás sikeres.
- `tests/shopping-purchase-mysql.integration.php`: 52 check, saját eldobható
  MariaDB 10.11.19; valódi wpdb (WordPress 6.8, külön hash-pinnel), kanonikus
  ai-agent websession osztály, valódi pontkötő és affiliate-kód. Két kapcsolat,
  owner/NGO visszavonás, lost-ACK readback, KILL CONNECTION, 15 perc/45 nap.
  Ez nem a jelenlegi hostverzió egyezésének bizonyítéka.
- Profil PHP-fixture: 72 eset a kanonikus FactLens-resolverrel.
- Meglévő teljes JS→PHP Chrome-fixture célzott részhalmaza: 5/5; régi User Card,
  shopping-visszatérés, érvényes/hibás FactLens-cookie elsőbbség. Minden hálózati
  kérés helyi fixture, nem valódi belépés/termékkattintás.
- PHP/JS szintaxis sikeres; affiliate Bástya és meglévő negatív teszt sikeres.

Független review: a WP-kör kapcsolatvesztési findingja javítva és valódi SQL-lel
ellenőrizve. A webreview lejárt session miatti hiányzó belépést talált, javítva.
A reviewer ezután használati keret miatt megszakadt; teljes záró független review
még szükséges. Nincs új fizetős helyettesítő futás vagy feltételezett PASS.

## Kiadás, backup és rollback

Forrás implementálva/tesztelve; integráció, PR, merge, telepítés és aktiválás
még nincs igazolva. Pusztán commit/push nem telepít: bridge workflow csak a
`.codex/bridge` érintett fájljaira indul, ezek nem változnak.

Sorrend: függő review és CI → rövid main-integráció/merge ablak → külön
release-klón → staging exact-file → production exact-file, mindkettőn lane OFF.
Telepítés előtt pontos host/prefix/session-osztály/binder/NGO-katalógus ellenőrzés,
érintett intenttábla/opció és három runtimefájl mentése. Explicit oszlopmigráció,
fájlhash/mód/séma visszaolvasás. A három fájlt koherensen kell kiadni; a web
csak ezek után válthat. Nincs teljes rsync vagy pénzügyi táblamódosítás.

Aktiválás előtt aktuális hiteles partner + shop/program megfelelés és a Dognet
`d1` → `data1` visszaadás igazolása szükséges. Decathlon kizárt; CJ ezen az új
úton nincs aktiválva. Opciáltás előállapot-egyezéssel/readbackkel, egy ember
által megerősített természetes kattintás: termék, subject és NGO ellenőrzése.
Tesztvásárlás nincs. Pénzügyi elfogadás másik munkacsomag.

Rollback első lépése az új opció OFF. A már kiadott link nem hívható vissza.
Saját fájlmentés/CAS; profilpár együtt állítandó vissza. Intent/pontkötés nem
választható le törléssel; az additív oszlop marad, nincs régi DB visszatöltése.

## Kézi UI és smoke — még nem éles PASS

Terméklink → belépés/restore → változatlan termék → másik aktív NGO → külön
megerősítés → ugyanaz a termék affiliate-paraméterekkel. Dupla kattintás egy
intent; lejárt vagy másik fiók új belépés/megerősítés. Profil NGO-preferencia,
User Card, profil/pont/szint és FactLens-visszatérés változatlan.
Smoke scope: `route:profil`, `route:impactshop`, `route:factlens-vb-prod`,
`route:impact-challenge`, `flow:profile-return-account`,
`flow:profile-return-restore`, `flow:message-popup`, `flow:points-jump`,
`flow:legacy-pool-visibility`, `flow:saved-offers-open`, `flow:go-deal`,
`browser:chrome`, `browser:webkit`. A tagek kötelező hatókört, nem futási eredményt jelentenek.
