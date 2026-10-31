# Napi marketingmérés — dokumentációs protected változás

## Felhatalmazás és érintett fájl

A kijelölt feladat kifejezetten kéri az új modul kötelező
`docs/bastion-guard-status.md` evidenciáját. Ez az egyetlen meglévő
protected fájl változik: csak új státuszbekezdés kerül az elejére.
Guard-kód, jogosultság, config, hashlista és meglévő runtime változatlan.
A publikus guide HTML engedélyezett additív injekciója külön új MU-fájlban van;
JYSK és a többi védett guide forrás változatlan.

## Koherencia és kockázat

A státusz közvetlenül a tényleges 54 PHP-check/5 JS-HTTP-teszt eredményét
rögzíti; nincs production vagy aktiválási állítás. A dokumentáció nem ad
új végrehajtási jogosultságot. Kockázat: pontatlan státusz; a parancskimenetek
és a saját diff ellenőrzése kezeli. Funkciók/határ/UI:
`docs/marketing-daily-counts-2026-10-31.md`.

## Backup és rollback

Az eredeti változat megmarad a base `88cb74f4` Git-objektumában és
külön helyi másolatban: `/var/folders/cy/d86v1d6n2bn28qrv9xjv5cjr0000gn/T/impactshop-marketing-bastion-20261031-e7e84_u4/bastion-guard-status.md`.
A backup a dokumentációs guardtalálat után, a HEAD-változatból készült;
nem állítunk előzetesen végrehajtott snapshotot.
Rollback: kizárólag a saját új 2026-10-31 státuszblokk eltávolítása;
idegen vagy későbbi változás nem írható felül. Runtime rollback: kapcsoló OFF,
későbbi engedélyezett kiadási útvonalon csak az új két MU-fájl kivonása.
Backup legfeljebb 2 napig, a review után törölhető; teljes mentéshez nem nyúlunk.

## Smoke és ellenőrzőlista

Smoke tag: `marketing-daily-counts-docs`. Protected-touch a meglévő
explicit dokumentációs override, e record és a szűk rollback-leírás mellett.
54 PHP fixture és5 JS/valódi helyi HTTP-teszt, PHP/JS syntax és az affiliate
bastion checker sikeres. Nincs külső szolgáltatás- vagy élő UI-teszt.
Kézi UI checklist a fenti runbookban; kiadás és aktiválás nyitott.
