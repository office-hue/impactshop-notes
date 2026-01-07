# ImpactHub API Documentation

## Quick Start
```bash
# Validate spec
.codex/scripts/openapi-validate.sh

# Interactive docs
npx swagger-ui-watcher docs/api/openapi.yaml
```

## Rate Limits
| Endpoint | Limit |
|----------|-------|
| `/ticker` | 50 req/s |
| `/ledger` | 5 req/s |
| `/token/*` | 10 req/s |

## Idempotency
Használj `Idempotency-Key` fejléces UUID-t minden módosító kérésnél.

## Core merge download
Merge export kimenetek letöltésére szolgál, API key-vel védve.

- Endpoint: `GET /core/merge-download?file=<abszolút_útvonal>`
- Auth: `x-api-key: <AI_AGENT_API_KEY>` (alternatíva: `?key=...` query param)
- Whitelist: csak a megadott gyökerekből enged (env: `CORE_MERGE_DOWNLOAD_ROOTS`), alap: `tmp/state/documents`, `tmp/document-uploads`
- Támogatott kiterjesztések: `.xlsx`, `.csv`, `.json`, `.pdf`, `.docx`

Példa:
```bash
curl -sS \
  -H "x-api-key: $AI_AGENT_API_KEY" \
  "https://<host>/core/merge-download?file=/abs/path/Output.core.xlsx" \
  -o Output.core.xlsx
```

Hibák:
- `401 invalid_api_key`
- `403 forbidden` (nem engedélyezett útvonal)
- `404 not_found`
