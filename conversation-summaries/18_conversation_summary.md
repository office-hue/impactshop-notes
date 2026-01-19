# 18. Beszélgetés összefoglaló: Wallet pass CTA + announcement guard

## Áttekintés
Dokumentáltam a Wallet share pass workflow kötelező mezőit, hogy a CTA link és a `sharity_news` blokk mindig az Impact Shop slugjához illetve az API announcementjéhez igazodjon.

## Főbb változások
- Létrehoztam a `docs/impactshop-ngo-card-usage.md` fájlt: részletesen leírja a `storeCard.backFields[0]` CTA blokkot, a slugos URL-t, az attributed anchor formát, és azt, hogy a `sharity_news` értékét a `/impact/v1/ngo-card/<slug>` `announcement.text` mezőből kell másolni.
- Az `impact-hub-system-v1.3.md` „QR / NFC / Wallet” szakasza bővült: az impactall guard most elvárja a CTA blokkot, az announcement egyezést és a manifest+signature hotfix workflow dokumentálását (`scripts/hotfix-sync.sh`).
- A `notes.md` napló tartalmazza az Ádám Reménye passzára vonatkozó frissítési teendőt és a guardrail magyarázatát.

## Következő lépések
- Regenerálni kell az `impactshop-share-card-adamremenye.pkpass` fájlt a friss CTA/announcement szabályokkal, majd hotfix-szel a szerverre szinkronizálni.
