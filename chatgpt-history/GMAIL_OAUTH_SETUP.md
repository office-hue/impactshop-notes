# Gmail OAuth konfiguráció (AI Agent)

Az AI agent Google szolgáltatásai (`gmail.promotions` ingest, harvester) csak akkor működnek, ha az alábbi OAuth mezők rendelkezésre állnak a futtatási környezetben:

```
GMAIL_CLIENT_ID=
GMAIL_CLIENT_SECRET=
GMAIL_REFRESH_TOKEN=
GMAIL_REDIRECT_URI=https://developers.google.com/oauthplayground
```

## Hol tárold / hol keresd a valódi értékeket?
- **Lokálisan**: hozd létre a `secrets/gmail-oauth.env` fájlt (a repo `.gitignore`-ban van, így nem kerül verziókövetés alá), majd töltsd fel a fenti kulcsokat.
- **GitHub Actions / szerver**: állítsd be a `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN`, `GMAIL_REDIRECT_URI` environment változókat a CI/CD pipeline-ban vagy a process managerben.

> Ha bármelyik folyamat hiányolja ezeket az értékeket, **először** a `secrets/gmail-oauth.env` fájlban (helyi fejlesztés) vagy a GitHub/production Secrets-ben keresd őket – a `chatgpt-history/GMAIL_CLIENT_ID=...` fájlokat szándékosan töröltük a repóból.

> ⚠️ Biztonsági okokból a valós tokenek és client secretek **nem** kerülhetnek a `chatgpt-history` könyvtár alá. Ez a fájl csak emlékeztetőként szolgál, hogy milyen mezőket kell kitölteni a saját `secrets/gmail-oauth.env` fájlodban vagy GitHub Secrets-ben.

## Betöltés
A Node.js alapú AI agent `.env`-je (vagy `secrets/gmail-oauth.env` fájlja) betöltés után automatikusan továbbadja ezeket a változókat a `googleapis` kliensnek, így a rendszer változatlanul működik.
