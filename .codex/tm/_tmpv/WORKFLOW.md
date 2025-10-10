# Optimalizált WordPress Development Workflow

## 1. Új funkció fejlesztése

### Lépések:
1. **Tervezés**: `notes.md`-ban dokumentáld a célt
2. **Kutatás**: Nézd meg a `chatgpt-history/` mappát hasonló megoldásokért
3. **Kódolás**: Itt VS Code-ban GitHub Copilot segítségével
4. **Dokumentálás**: Frissítsd a `notes.md`-t
5. **Feltöltés**: WP adminba
6. **Tesztelés**: Eredmények vissza a `notes.md`-ba

## 2. ChatGPT integráció
- Minden ChatGPT beszélgetés után másold ide a lényeget
- Használd a template-et a `chatgpt-history/README.md`-ból
- Hivatkozz rá a `notes.md`-ban

## 3. VS Code extensions ajánlatok
```bash
# PHP fejlesztéshez
PHP Intelephense
PHP Debug
PHP DocBlocker

# WordPress specifikus
WordPress Snippets
```

## 4. Gyors parancsok
- `Ctrl+Shift+P` → "PHP: Validate syntax"
- `Ctrl+K Ctrl+C` → kikommentezés
- `Ctrl+K Ctrl+U` → komment eltávolítás
- `Alt+Shift+F` → kód formázás

## 5. Tippek
- Mindig mentsd el a munkát Git-be
- Használj beszédes fájlneveket
- Dokumentálj mindent azonnal
- Tesztelj minden változtatást

## 6. Shortcode karbantartási szabály
- Egyetlen forrás marad aktív: `impact-mini-shortcodes` + `sharity-impact-mini` (biztosíték + production UI)
- Minden duplikált rövidkód-regisztrálót archiválj `.off` kiterjesztéssel és dokumentáld az `IMPACT_PACK_STATUS.txt`-ben
- Validálj minden módosítást `wp eval` (`shortcode_exists` ✓), REST 200 válaszok, valamint `do_shortcode` HTML kimenet alapján
- Elementorban kizárólag Shortcode widgetet használj; a renderelt frontend HTML-ben és CSS-ben jelenjen meg az Impact design
