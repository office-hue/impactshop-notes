# Percy vizuális regresszió – ImpactShop gyors útmutató

## 1. Előfeltételek
- Percy CLI: `npm install -g @percy/cli`
- Percy token: `PERCY_TOKEN=<org-token>` (Percy webfelületen generálva)
- Chrome/Chromium a lokális screenshotokhoz

## 2. Snapshot futtatás
```bash
cd /Users/bujdosoarnold/Documents/GitHub
export PERCY_TOKEN=<helyettesítsd>
percy snapshot docs/impactshop-shortcodes.html \
  --dry-run \
  --widths=1280,1920
```

## 3. CI integráció (GitHub Actions)
1. Percy token a repo secretben: `PERCY_TOKEN`.
2. Workflow lépés:
   ```yaml
   - name: Percy snapshots
     run: |
       npm install -g @percy/cli
       percy snapshot docs/impactshop-shortcodes.html --widths=1280,1920
     env:
       PERCY_TOKEN: ${{ secrets.PERCY_TOKEN }}
   ```

## 4. Hibaelhárítás
- 403: token hiányzik vagy rossz org.
- Üres build: Percy CLI nem találta a cél HTML-t → ellenőrizd az elérési utat.
- Lassú build: add `--parallel` és oszd a snapshotokat kisebb csomagokra.
