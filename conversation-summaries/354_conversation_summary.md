# 354

- Beallitottam a partner API smoke tesztet: runner timestamp header + API status code fix, ledger insert kitolti a jelenlegi impact_ledger schema kotelezo mezoit.
- Partner demo kulcsok: `IMPACT_PARTNER_SECRETS` beallitva a szerveren (`/home/sharityh/.impact-secrets/env.d/partner.env`), config sorok staging/prod DB-ben.
- Runner futas: staging dupe valaszok, prod accepted/duplicate; invalid-signature fixture tenylegesen nem invalid (runner mindig helyes HMAC-ot kuld).
- Audit logok frissultek mindket kornyezeten.
