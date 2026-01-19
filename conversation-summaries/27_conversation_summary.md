# 27. Beszélgetés összefoglaló: Wallet REST API az Ádám-sablonra állítva

## Áttekintés
A `/wp-json/impact/v1/ngo-card/<slug>/wallet-pass` végpont mostantól ugyanazt az Ádám Reménye sablont adja, mint amit manuálisan/szkripttel gyártunk, így a share gombok is az új mezőstruktúrát töltik le.

## Fő lépések
- Az `impactshop-wallet.php::build_pass_json()` teljesen megújult: minden slugra slugos `src=wallet-pass` CTA-t, fix sorrendű hátlap mezőket (CTA, Tombola, Videó, Sharity hírek + opcionális Rendszerüzenet) és "Név" auxiliary mezőt állít elő; a `userInfo` blokkban `badge` + `test_version=share-card-v1` szerepel.
- Az API automatikusan hozzáadja a "Rendszerüzenet" mezőt, ha az API `announcement.url` értéke eltér a Sharity hírektől, ugyanúgy, ahogy a manuális workflowban tettük.
- `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-wallet.php` felküldte a módosítást prod/stagingre, cache flush után `~/bin/impactall` megerősítette, hogy minden guard rendben (csak a baseline WARN maradt).

## Következő lépések
- A share gombok mostantól az új formátumot adják vissza; ha bármelyik slughoz manuális Rendszerüzenet kell, add át a szkript második paraméterében, vagy bővítsd ki az API `announcement` mezőjét URL-lel.
