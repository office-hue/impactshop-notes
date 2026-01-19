# CJ tranzakció visszajelzés (Commission Detail) – specifikáció váz

Forrás: https://developers.cj.com/graphql/reference/Commission%20Detail

## Cél
Egységes CJ tranzakciós visszajelzés (commission) feldolgozása az ImpactShop
leaderboard/activity/ticker útvonalak számára, Dognettel azonos szemantikával.

## Minimum mezők (CJ → ImpactShop normalizált mezők)
Az alábbi mezők **szükségesek**, a pontos CJ mezőnevek a Commission Detail
GraphQL schema alapján:

| CJ mező (forrásban név szerint) | ImpactShop normalizált mező | Megjegyzés |
| --- | --- | --- |
| `commissionId` | `transaction_id` | Idempotencia kulcs. |
| `eventDate` (fallback: `postingDate`) | `created_at` | Időablak szűréshez. |
| `validationStatus` | `status` | Pending/Approved/Rejected mapping. |
| `pubCommissionAmountPubCurrency` (fallback: `pubCommissionAmountUsd`) | `commission` | Adomány = commission * 0.5. |
| `saleAmountPubCurrency` (fallback: `saleAmountUsd`) | `order_value` | Ha elérhető. |
| `advertiserId` | `campaign_id` | Shop mappinghez. |
| `advertiserName` | `campaign_name` | UI/leaderboard. |
| `sid` | `ngo_slug` | CJ clickből érkező subid/data1; lehet null. |

## Státusz mapping (CJ → ImpactShop)
`validationStatus` enum értékek:
- `PENDING` → `pending`
- `AUTOMATED` → `pending` (előminősített, de nem végleges)
- `ACCEPTED` → `approved`
- `DECLINED` → `rejected` (kizárás)

## Activity időzítés
- **Alap**: `eventDate` alapján kerül a feedbe (fallback: `postingDate`).
- **Szűrés**: status=all esetén pending + approved is megjelenhet.
- **Kizárás**: `DECLINED` tételek nem kerülnek a feedbe.
- **Megjegyzés**: `lockingDate` használható “véglegesített” időpontként.

## CJ → NGO slug (data1/subid) elv
- A CJ click URL-ben továbbított azonosító `sid` mezőben érkezik.
  Ezt kell `ngo_slug` értékre visszakötni.
- Ha `sid` null, `ngo_slug=unknown` és a tétel "unknown" scope-ba kerül
  (csak összegzésben). Ilyenkor ellenőrizni kell, hogy a `/go` CJ link
  tényleg tartalmazza‑e a `sid` paramétert, és a `impactshop-go-clicks.log`
  alapján visszaköthető‑e.

## Nyitott kérdések
- `ActionStatus` és `ActionType` mezők jelentése (CJ-ben scalar), szükséges-e
  külön kezelés.
- Deviza: pub currency mező hiányzik; `pubCommissionAmountPubCurrency` és
  `saleAmountPubCurrency` implicit pénznemet ad, USD fallback elérhető.

## Következő lépés
- Commission Detail mezők alapján kitölteni a fenti táblázatot és
  bevezetni a normalizáló mappinget az ingest pipeline-ba.

## CJ ingest TODO (rövid lista)
1. **GraphQL query**: `publisherCommissions` hívás, szűrők: `sinceEventDate`, `beforeEventDate`, `validationStatuses`, `sinceCommissionId`.
2. **Mapping**: a fenti mezők szerint normalizálni (`commissionId`, `eventDate`, `validationStatus`, `pubCommissionAmountPubCurrency`, `saleAmountPubCurrency`, `advertiserId/Name`, `sid`).
3. **NGO slug**: `sid` → `ngo_slug` feloldás; hiány esetén `unknown` scope.
4. **Status kezelés**: `DECLINED` kizárás; `PENDING/AUTOMATED` megjelenhet `status=all` mellett; `ACCEPTED` approved.
5. **Időzítés**: activity window `eventDate` alapján, fallback `postingDate`; `lockingDate` opcionális “véglegesített” dátum.
6. **Dedup**: `commissionId` alapján idempotens ingest.
7. **Fallback**: CJ hiba esetén Dognet‑only mód (impactcj_disabled).
8. **SID verifikáció**: egy teszt CJ click után ellenőrizni, hogy a `sid`
   ténylegesen visszajön‑e a Commission Detail rekordban.

## GraphQL query minta (PublisherCommissions)
```graphql
query ImpactshopCjPublisherCommissions(
  $forPublishers: [String!]!
  $sinceEventDate: String
  $beforeEventDate: String
  $validationStatuses: [ValidationStatus!]
  $sinceCommissionId: String
  $advertiserIds: String
  $websiteIds: String
) {
  publisherCommissions(
    forPublishers: $forPublishers
    sinceEventDate: $sinceEventDate
    beforeEventDate: $beforeEventDate
    validationStatuses: $validationStatuses
    sinceCommissionId: $sinceCommissionId
    advertiserIds: $advertiserIds
    websiteIds: $websiteIds
  ) {
    count
    limit
    maxCommissionId
    payloadComplete
    records {
      commissionId
      eventDate
      postingDate
      lockingDate
      validationStatus
      advertiserId
      advertiserName
      sid
      pubCommissionAmountPubCurrency
      pubCommissionAmountUsd
      saleAmountPubCurrency
      saleAmountUsd
      orderId
      actionType
      actionStatus
    }
  }
}
```

## Minimal curl példa
```bash
curl -sS https://commissions.api.cj.com/query -H "Authorization: Bearer <CJ_PAT>" -H "Content-Type: application/json" -d '{
  "query":"query ImpactshopCjPublisherCommissions($forPublishers:[String!]!,$sinceEventDate:String,$beforeEventDate:String,$validationStatuses:[ValidationStatus!],$sinceCommissionId:String){publisherCommissions(forPublishers:$forPublishers,sinceEventDate:$sinceEventDate,beforeEventDate:$beforeEventDate,validationStatuses:$validationStatuses,sinceCommissionId:$sinceCommissionId){count limit maxCommissionId payloadComplete records{commissionId eventDate postingDate lockingDate validationStatus advertiserId advertiserName sid pubCommissionAmountPubCurrency pubCommissionAmountUsd saleAmountPubCurrency saleAmountUsd orderId actionType actionStatus}}}",
  "variables":{
    "forPublishers":["<CJ_PUBLISHER_ID>"],
    "sinceEventDate":"2026-01-01T00:00:00Z",
    "beforeEventDate":"2026-01-02T00:00:00Z",
    "validationStatuses":["PENDING"]
  }
}'
```

Megjegyzés: opcionálisan adható `advertiserIds` és `websiteIds` (string),
ha szűkíteni kell a lekérdezést.
