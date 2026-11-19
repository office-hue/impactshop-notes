# BabaMama Expo Stripe webhook

A külön Expo Payment Link szerveroldali fogadója a WordPressben.

- Tervezett partneri célcím: `https://app.sharity.hu/wp-json/sharity/v1/expo/babamama-2026/events`.
- A végpont csak POST-ot fogad. A fogadó alapállapotban nincs regisztrálva. A címet az éles útvonal célzott visszaolvasása után lehet bekötésre átadni.
- A nyilvános köszönőoldal címe `https://sharity.hu/babamama-expo/koszonjuk`. A sikeres fizetés Stripe-átirányítása és a webhook külön beállítás.

## Bejövő események

A Stripe célpontja közvetlenül az alapítvány saját fiókjából küldött, `2026-10-28.endive` verziójú snapshot webhook. A Stripe a kiválasztott eseménytípusokat fiókszinten küldi. Éles bekötés előtt a partnerrel egyeztetni kell a nyers payloadok adatköri határát. A partner saját, szűrt továbbítója eltérő szerződés, mivel a nyers Stripe-aláírás az eredeti törzsre vonatkozik.

| Esemény | Fogadás |
| --- | --- |
| `checkout.session.completed`, `checkout.session.async_payment_succeeded` | Az egyeztetett Payment Link, a fizetett egyszeri Checkout Session, a megfelelő mód és a HUF az előfeltétel. A két esemény ugyanahhoz a PaymentIntent-rekordhoz vezethet. |
| `refund.created`, `refund.updated`, `refund.failed` | A sikeres HUF-visszatérítés a már rögzített Expo-fizetéshez kapcsolható a PaymentIntent alapján. A később sikertelenné vagy visszavonttá vált refundhoz külön korrekció kerül. A még nem besorolható refund 503-at kap, amíg a fizetés fel nem oldja. |

Másik link sikeres, hiteles Checkout-eseménye mentés nélkül 200-at kap. Ismeretlen HUF-refundnál a fogadó még nem tudja, idegen vagy sorrenden kívül érkezett-e. A visszamenőleges tételek és a kevert fiók eseményeinek feloldási módja élesítési előfeltétel. A 200-as válasz tárolt vagy ismételten kézbesített eseményt, illetve kifejezetten kihagyott eseményt jelent. A befizetés és a refund belső forrásnyilvántartás. A későbbi összegzés csak olyan `refund` összeget vonhat le, amelyhez ugyanazon a kampánykötésen és refundazonosítón nincs `refund_reversal` rekord. Ez a szabály az események érkezési sorrendjétől független. Az Expo-oldal számlálója ehhez külön egyeztetést követően kapcsolható.

## Későbbi telepítés és aktiválás

1. Egyeztessétek a pontos Stripe-fiókot és a kampány `plink_…` azonosítóját, az indulástól számolt partneri kivonatot, a refundok kezelését és a jóváhagyott adatkört. A `whsec_…` az Expo saját Stripe-destinationjéhez tartozzon. A közvetlen Stripe-eseményen a fiókmező hiányozhat, ezért a dedikált aláíró titok és a Stripe Dashboardban ellenőrzött célpont együtt köti a fogadót a fiókhoz.
2. A telepített, egyező Stripe PHP SDK-t a meglévő adományozási runtime tölti be. A séma külön fájlban van. Az éles gazda a tényleges WordPress prefixet olvassa ki, a sémában behelyettesíti az `__PREFIX__` értéket, és a meglévő release útvonalon hozza létre az InnoDB táblát. Utána visszaolvassa az elsődleges és az egyedi eseménykulcsot. DDL nem fut pluginbetöltéskor vagy webhook-kérésben.
3. A letiltott WordPress plugin telepítése után a fogadót először elkülönített Stripe sandboxban ellenőrizzétek. A szerver oldali konfiguráció mezői `SHARITY_EXPO_STRIPE_ACCOUNT`, `SHARITY_EXPO_STRIPE_PAYMENT_LINK`, `SHARITY_EXPO_STRIPE_LIVEMODE` (boolean), `SHARITY_EXPO_STRIPE_WEBHOOK_SECRET`. A `SHARITY_EXPO_STRIPE_ENABLED` kapcsoló csak a kijelölt környezetben, külön élesítési döntés után legyen boolean `true`. Ellenőrzésre az első természetes éles esemény használható.
4. Visszaállításkor az engedélyező kapcsolót kapcsold ki, a partner szüneteltesse a Stripe-destinationt. A belső rekordokat és a mentést tartsd meg az egyeztetéshez.

A táblában csak kampánykötés, Stripe technikai azonosítók, típus, kisebb pénzegységben tárolt HUF összeg és Stripe-eseményidő szerepel. A nyers esemény és a donoradatok tárolása kívül esik a fogadó szerződésén. Ellenőrizni kell a befogadó host és a proxy naplózási beállítását is.

Az aláírási és adatbázis-fixture a repóban rögzített Stripe SDK-val, elkülönített MariaDB-n fut. A teszt kizárólag helyi adatbázist és mesterséges eseményeket használ.

A fixture futtatása a rögzített SDK `init.php` fájljával és a
`127.0.0.1`-re kötött, eldobható `expo_ingress_test` adatbázissal:

```bash
SHARITY_EXPO_TEST_DB_PORT='<helyi-port>' \
SHARITY_EXPO_TEST_SDK_INIT='<pontos-init.php-utvonal>' \
php tests/sharity-expo-stripe-webhook.test.php
```
