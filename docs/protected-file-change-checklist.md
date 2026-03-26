# Protected File Change Checklist

Ez a kötelező ellenőrzési rend minden olyan módosítás előtt és után, amely bástyavédett / írásvédett fájlt érint.

## 1. Módosítás előtt kötelező

1. Koherencia vizsgálat készül.
   - Mely route-okat, hookokat, shortcode-okat, REST endpointokat, cronokat, CLI parancsokat, adatírási pontokat és frontend belépési pontokat érinti a változás.
   - Mely upstream/downstream kapcsolatokhoz csatlakozik a módosított fájl.
2. Kockázatelemzés készül.
   - Mi romolhat el közvetlenül.
   - Mi romolhat el közvetetten.
   - Mely területeken várható rejtett regresszió.
3. Funkció-érintettségi lista készül.
   - Pontosan fel kell sorolni, mely user-facing funkciókat érintheti a módosítás.
   - Pontosan fel kell sorolni, mely admin / háttérfolyamat / import / sync / redirect / reward / rotációs ág érintett.
4. Megoldási döntés rögzítve van.
   - Additív új kód a megoldás.
   - Ha nem, akkor explicit jóváhagyott legacy touch történt.
5. Backup + rollback útvonal előre rögzítve van.

## 2. Kötelező funkció-ellenőrzési lista

Protected-file módosításnál előre össze kell állítani, hogy deploy / PR / merge után mit kell ellenőrizni:

- érintett route-ok
- érintett REST endpointok
- érintett shortcode / embed / widget / player / CTA ágak
- érintett reward / pont / szavazat / ledger / dedupe ágak
- érintett redirect / go / go-deal / affiliate link ágak
- érintett import / sync / cron / watchdog / pipeline ágak
- érintett rotáció / kategorizálás / inventory / whitelist ágak
- érintett admin vagy operátori képernyők

Ez a lista kötelező része a PR-nek és a deploy jegyzetnek is.

## 3. Kötelező manuális UI checklist a megrendelőnek

Protected-file módosítás után külön, rövid checklistet kell adni, hogy a weboldalon mit érdemes végigellenőrizni.

Minimum:

- mely oldalakra menjen fel
- mely UI blokkokat nézze meg
- mit kell látnia normál esetben
- milyen kattintási vagy végigjátszási flow-t futtasson le
- milyen regressziójelekre figyeljen

Példa:

- oldalak: `/impact-challenge`, releváns céloldalak, partner/redirect flow
- UI blokkok: player, számláló, CTA, NGO lista, top lista, identity blokk
- flow: videó indítás, CTA kattintás, videóvégi jutalom, redirect, visszatérés
- regressziójelek: eltűnő számláló, nem frissülő jutalom, hibás link, rossz logó, rossz shop, üres lista

## 4. Deploy / PR / merge utáni kötelező ellenőrzés

Protected-file módosítás után az asszisztensnek kötelező:

1. végigmenni az előre rögzített funkció-érintettségi listán,
2. dokumentálni, mi lett ténylegesen ellenőrizve,
3. külön kiemelni, mi NEM lett ellenőrizve,
4. a felhasználó figyelmét külön checklistben felhívni a kézi UI ellenőrzésre.

## 5. Hard szabály

- Protected-file módosítás nem történhet „gyors hotfix” alapon koherencia- és kockázatelemzés nélkül.
- Ha az érintett funkciók listája nincs meg, a munka nincs kész.
- Ha a kézi UI checklist nincs meg, a handoff nincs kész.
