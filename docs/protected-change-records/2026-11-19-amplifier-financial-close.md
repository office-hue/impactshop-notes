# Amplifier nettózárás: meglévő kiadási forráshash követése

Protected files touched: `docs/impactshop-guard-hashes.json` és `docs/impactshop-guard-hashes.sha256`. Egyetlen pénzügyi forráshash követi a reviewed57a1e843 és merged31784bc1 `wp-content/mu-plugins/sharity-ngo-donations/finance-ledger.php` fájlját:8399a3ea46ed4e234c178ee50fb8e3a3825f856d7b1a2f004a87e84c772279d2. Minden más hash, config, guardkód és policy megmarad.

Indok/koherencia: a meglévő guard előnézet a régi04b9eb66 referenciától eltérő, jóváhagyott pénzügyi fájlnál megállt; ezt a tesztelt forrást kell a meglévő leltárban rögzíteni. Az éles/staging forrás SHA e67bfddd, HUF-only Direct; a konkrét diff csak a már tervezett Direct EUR és új Amplifier close. User scope: teljes pénzügyi implementáció és commit/PR/merge/telepítés, TimeWall külön tulajdonos. Nincs pénzügyi DB-írás vagy kifizetésaktiválás.

Kockázat: téves hash más forrást engedhetne át. Smoke: a teljes natív protected inventory0drift és6negatív hash/checksum eset; egyetlen digest változásának külön összevetése; staged/outgoing secret/productguard. Korábbi actual CI44522431646 financial135554423118 SUCCESS a változatlan PHP-forrásra (Amplifier162, parent9356); nem futtatjuk újra a puszta hashkötés miatt.

Rollback terv: a két manifestfájl együtt visszavonható a saját commitból, ha nincs új pénzügyi írás. Távoli runtime rollback csak a tényleges exact release manifestjének backup/CAS/deployedSHA útján; új Amplifier-close után régi reader helyett kompatibilis előrejavítás. Deploy a merge-elt, tiszta exact mainből, előzetesen read-only visszaolvasott remoteSHA-val. Post-merge/deploy: exact sourcehash, checksum, PHP lint, mód0444 és a szülő eredeti módjának visszaolvasása; nincs általános cron/rewrite.

Kézi UI ellenőrzőlista: nincs UI-változás ebben a mechanikus bejegyzésben; a meglévő adományoldal read-only elérhetősége ellenőrizhető. Valós financial-close és NGO-jóváírás nem következik ebből. Forráskész/merge-elt/telepített/aktivált/élőben elfogadott külön marad.
