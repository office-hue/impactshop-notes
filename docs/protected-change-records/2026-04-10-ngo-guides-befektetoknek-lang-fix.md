# 2026-04-10 NGO Guides — befektetoknek 404 + lang fájl fix (v1.1.4)

## Summary

Két bug javítása az `impactshop-ngo-guides.php` v1.1.3-ban, amit a `0564382f`
refactor (ic-bastion PR#98) vezetett be 2026-04-07-én:

1. `/befektetoknek/` → 404: a `page_meta()` tömbből hiányzott a `befektetoknek`
   bejegyzés, holott a rewrite rule, `public_path()`, `page_filename()` és
   `sitemap_pages()` mind tartalmazta.

2. Angol oldal (`?lang=en`) soha nem töltődött be: a `template_redirect()` nem
   adta át `$lang`-ot a meglévő `resolve_file()` segédfüggvénynek.

Verzió bump: 1.1.3 → 1.1.4.

## Protected files touched

- `wp-content/mu-plugins/impactshop-ngo-guides.php`

## Risk

- Alacsony: kizárólag guide lapok érintettek (statikus HTML kiszolgálás)
- ads-watch, offerwall, boot.php, cloudflare — NEM érintett
- WP DB nem módosul (csak rewrite flush option frissül, ami safe)
- A `resolve_file()` implementáció hibátlan volt — csak meghívni kellett

## Rollback

- Egykattintásos rollback script: `backups/ngo-guides-fix-20260410/rollback.sh`
- Backup commit: `9b7ab942` (hotfix/mobile-freeze-v2.5.63 branch)
- MD5 referencia: `f2bf5afbe5ea61d5124397788708e395`

```bash
bash backups/ngo-guides-fix-20260410/rollback.sh
```

Utána ellenőrzés:
```bash
curl -s -o /dev/null -w "%{http_code}" https://app.sharity.hu/befektetoknek/
# rollback után: 404 (v1.1.3 állapot visszaáll)
```

## Smoke

- `route:befektetoknek`
- `route:ngo-guides`
- `route:cegeknek`
- `route:rolunk`
- `lang:en`
- `browser:chrome`

## Notes

- Backup: `backups/ngo-guides-fix-20260410/impactshop-ngo-guides.v1.1.3.PROD.php`
- Cloudflare: NEM érintett (TTL lejárat után automatikusan frissül)
- Elementor: NEM érintett
- admin_init flush: version key 1.1.3 → 1.1.4, automatikus, nem-destruktív
