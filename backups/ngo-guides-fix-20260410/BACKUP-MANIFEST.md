# Backup manifest — ngo-guides fix 2026-04-10

## Érintett fájlok

| Fájl | Szerver path | Méret | MD5 | Módosítva |
|------|-------------|-------|-----|-----------|
| `impactshop-ngo-guides.v1.1.3.PROD.php` | `/home/sharityh/app/wp-content/mu-plugins/impactshop-ngo-guides.php` | 13096 bytes | `f2bf5afbe5ea61d5124397788708e395` | 2026-04-07 12:40:38 |

## Git referencia

- origin/main HEAD: `18894043` (impactshop-notes)
- Git blob MD5: `f2bf5afbe5ea61d5124397788708e395` ← egyezik a szerverrel ✅

## CSAK EZEKET érinti a fix

- ✅ `impactshop-ngo-guides.php` — kizárólag ez a fájl módosul
- ❌ ads-watch — NEM érintett
- ❌ Cloudflare — NEM érintett
- ❌ WordPress options / database — NEM érintett (admin_init flush fut, de version key-re; ez safe)
- ❌ Elementor — NEM érintett
- ❌ .htaccess — NEM érintett

## WordPress flush megjegyzés

A v1.1.4 verzióbump miatt az `admin_init` hook futtatja a `flush_rewrite_rules()`-t (option key változik: `1.1.3` → `1.1.4`).  
Ez **nem visszafordíthatatlan**: a rewrite szabályok mindig újragenerálhatók, és a backup nem szükséges hozzá.  
A rewrite cache a `wp_rewrite_rules` WordPress option-ben tárolódik — automatikusan felülíródik.

## Rollback

```bash
bash /Users/bujdosoarnold/Developer/GitHub/impactshop-notes/backups/ngo-guides-fix-20260410/rollback.sh
```

Manuálisan:
```bash
scp /Users/bujdosoarnold/Developer/GitHub/impactshop-notes/backups/ngo-guides-fix-20260410/impactshop-ngo-guides.v1.1.3.PROD.php \
    sharityh@s59.tarhely.com:/home/sharityh/app/wp-content/mu-plugins/impactshop-ngo-guides.php
```

Visszaellenőrzés:
```bash
curl -s -o /dev/null -w "%{http_code}" https://app.sharity.hu/befektetoknek/
# Visszaállás után: 404 (az volt a rollback előtti állapot)
curl -s -o /dev/null -w "%{http_code}" https://app.sharity.hu/cegeknek/
# Ennek 200-nak kell maradnia
```
