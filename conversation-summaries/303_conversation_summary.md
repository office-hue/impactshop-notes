# 303. Beszélgetés összefoglaló: CJ smoke futtatás

- A CJ Commission Detail endpointot a schema alapján javítottam `https://commissions.api.cj.com/query` értékre.
- A GraphQL query kiegészült a kötelező `forPublishers` listával és `validationStatuses` tömbbel.
- A `scripts/cj-commission-smoke.sh` támogatja az opcionális `ADVERTISER_IDS` és `WEBSITE_IDS` szűrőket.
- A CJ smoke futások `PENDING` és `ACCEPTED` státuszra is 0 rekordot adtak az elmúlt 30 napban (websiteIds=101302202 mellett is).
