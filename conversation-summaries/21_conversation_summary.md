# 21. Beszélgetés összefoglaló: Sharity hírek vs. rendszerüzenet a wallet passon

## Áttekintés
Finomhangoltam a wallet pass dokumentációt és guard leírást, hogy a hátlapi „Sharity hírek” mező továbbra is a /impact/v1/ngo-card announcementjét tükrözze, míg a külön „Rendszerüzenet” blokk csak akkor jelenjen meg, ha valóban eltérő információt kell közölni.

## Főbb változások
- A `docs/impactshop-ngo-card-usage.md` most kimondja: `sharity_news` kötelezően az API-ból jön, az `announcement` mező opcionális és csak külön üzenet esetén használható; ellenkező esetben hagyjuk ki.
- Az `impact-hub-system-v1.3.md` wallet fejezete ugyanezt rögzíti, így az impactall guard elvárása egyértelmű: nincs értelme két egyforma blokkot fenntartani.
- A `notes.md` naplóban is feljegyeztem, hogy a duplikált mezőket el kell kerülni.

## Következő lépések
- Amikor pass-t frissítesz, csak akkor add hozzá az `announcement` mezőt, ha tényleg más üzenetet kommunikálsz, különben hagyd ki és ellenőrizd a preview-t a megjelenő hely kihasználása miatt.
