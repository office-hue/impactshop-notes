# 48. Beszélgetés összefoglaló: NGO slug feedback + Fillout fallback

## Áttekintés
Tovább finomítottam Impi NGO-felismerését: most már a WordPress oldalról kapott slug-listával dolgozik, visszajelzést mutat a felhasználónak, és csak akkor épít `d1` paramétert, ha tényleg ismert az ügy. Ellenkező esetben a CTA automatikusan a Fillout űrlapra visz, ahol kiválasztható a támogatott szervezet.

## Fő lépések
- `impactshop-ngo-card.php` – új `get_dataset_items()` publikus metódus teszi elérhetővé a slug+alias listát a front-end scriptnek.
- `impactshop-impi-chat.php/.js` – világosabb UI, NGO-indikátor (törölhető), slug-normalizálás és Fillout CTA fallback; az AI válasz is megjeleníti, melyik ügyet célozza épp Impi.
- `tools/ingest/normalizer.ts` + `apps/ai-agent-core/src/impi/recommend.ts` – `fillout_url` és `preferred_ngo_slug` mezők a DTO-ban, sanitizált sluggal csak akkor készül `go?d1=` link, ha van konkrét NGO; különben Fillout linket adunk vissza.
- Deploy: MU plugin hotfix (`scripts/hotfix-sync.sh ...`) + `ai-agent` bundle rsync + `~/ai-agent-service.js` restart; `.codex/guards/ai-agent-guard.sh` továbbra is OK.

## Következő lépések
- Ha egy adott NGO-nál szeretnénk biztosan ismert nevet megjeleníteni, kiterjeszthetjük a slug→név mapet a backendben is, vagy engedhetünk manuális ügyválasztást (dropdown) a chatben.
