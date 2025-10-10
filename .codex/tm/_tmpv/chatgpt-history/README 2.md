# ChatGPT History Integration

Ebben a mappában tárold el a ChatGPT beszélgetéseket strukturáltan:

## Fájl elnevezési konvenció
- `YYYY-MM-DD_tema.md` - pl: `2025-10-01_deeplink-fixing.md`
- `conversations/` - hosszabb beszélgetések almappában
- `solutions/` - kész megoldások külön

## Template minden beszélgetéshez:

```markdown
# ChatGPT beszélgetés - [Téma]
**Dátum**: YYYY-MM-DD
**Cél**: [Mit akartál elérni]
**Status**: [Folyamatban/Megoldva/Félbehagyva]

## Probléma leírása
[Az eredeti problémád]

## ChatGPT megoldása
[A generált kód/megoldás]

## Tesztelés eredménye
[Mi működött, mi nem]

## Következő lépések
[Mit kell még csinálni]

## Kapcsolódó fájlok
- [ ] `wp-content/mu-plugins/xyz.php`
- [ ] `notes.md` frissítve

## GitHub Copilot notes
[Amit itt szeretnél másképp csinálni]
```

## Használat
1. ChatGPT beszélgetés után másold ide a lényeget
2. GitHub Copilot automatikusan látni fogja a kontextust
3. Hivatkozz rá a `notes.md`-ban is