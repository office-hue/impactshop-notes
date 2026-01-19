# 29. Beszélgetés összefoglaló: NGO card embed/share/pass doksik áttekintése

## Áttekintés
Átolvastam a fő ImpactShop NGO card dokumentumokat (embed spec, usage guide, wallet jegyzetek), hogy tételesen fel lehessen sorolni az embed, share landing és Wallet pass workflow követelményeit.

## Fő pontok
- Az `impactshop/docs/impactshop-ngo-card-embed.md` alapján rögzítettem a három embed variánst, a Sharity hírek blokkot, a CTA/link trackinget és a script technikai kötelezőit.
- A `impactshop/docs/impactshop-ngo-card-usage.md` összesítette a REST API, share landing és QR/Wallet landing működését, plusz a reset/fillout flow-t és a GA eseménykövetést.
- A Wallet pass elvárásokat a brief (`docs/impactshop-ngo-card-brief.md` 3.5) + `docs/impactshop-wallet-setup.md` + `impactshop-notes/docs/impactshop-ngo-card-usage.md` alapján foglaltam össze: slugos CTA, `sharity_news` egyezés, tanúsítvány konstansok, rebuild/hotfix lépések.

## Következő lépések
- Ha új embed/share feature jön, ellenőrizni kell, hogy a fenti doksik naprakészek-e (különösen az analytics hookok és a Wallet guard feltételek).
