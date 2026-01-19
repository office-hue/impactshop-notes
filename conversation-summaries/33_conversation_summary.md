# 33. Beszélgetés összefoglaló: Wallet template visszaállítás

## Áttekintés
A manuális share pass rebuild mostantól ismét a Bátor Tábor mintából indul, mert a felhasználó kérte a korábbi referencia visszaállítását.

## Fő lépések
- A `wallet-pass-downloads/impactshop-share-card-base-bator.pkpass` fájlt átmásoltam `impactshop-share-card-template.pkpass` névre, így a `scripts/wallet/rebuild-share-pass.sh` ugyanazt a sablont kapja, mint az Ádám előtti workflow-ban.
- Más változtatásra nem volt szükség, az API-injektált mezők és a guardok érintetlenek maradnak.

## Következő lépések
- Ha a template-be új vizuális elem kerül, ezt továbbra is a Bátor Tábor fájlban kell először rögzíteni, majd szükség esetén megosztani a slug-specifikus pass-okkal.
