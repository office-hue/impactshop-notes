# 2026-08-19 Deploy bastion manifest guard

## Summary

Az operátor által jóváhagyott SOL csomag helyreállítja a kanonikus mapping
deploy hiányzó remote-manifest ellenőrzését, és a `DRY_RUN=1` módot ténylegesen
távoli írásmentessé teszi. A módosítás nem telepít WordPress runtimeot és nem
aktivál affiliate funkciót.

## Protected files touched

- `bin/deploy-wpcontent-map.sh`
- `docs/impactshop-deploy.md`

## Coherence assessment

Közvetlenül érintett:

- staging és production mapping deploy pre-rsync admission;
- remote WordPress root és bástya manifest ellenőrzés;
- dry-run target/destination vizsgálat;
- rsync dry-run itemizálás;
- deploy utáni WordPress maintenance és Hatás Körök smoke indítása.

Nem érintett:

- WordPress route, REST, shortcode és felhasználói UI;
- `/go`, `/go-deal`, Dognet/CJ link, feed vagy attribúció;
- profil-, pont-, reward-, szavazat-, adomány- és settlement writer;
- production option, séma, cron vagy watchdog állapot.

Upstream a guard wrapper és a `.deploy.*.env`; downstream az összes mapping,
az rsync, a WP maintenance és a post-deploy smoke. A staging/production env pár
nem változik.

## Risk and security assessment

- A hiányzó/hibás/symlinkelt/túlméretes vagy veszélyes bejegyzést tartalmazó
  manifest fail-closed módon blokkol.
- Az SSH-válasz csak rögzített státuszkódokon engedhető át.
- A dry-run nem használ `mkdir`-t és nem futtat `wp` írást; hiányzó cél esetén
  megáll.
- A valós deploy meglévő `--delete` kockázata nem változik. Emiatt real deploy
  csak merged main, zöld itemizált dry-run és a két live-only identity fájl
  külön operátori döntése után mehet.
- Secret, manifest-tartalom vagy user adat nem kerül logba.

## Rollback

Forrás rollback: a csomag egyetlen merge commitjának normál PR-es revertje.
A csomag után csak dry-run engedélyezett, ezért remote snapshot restore nem
szükséges. Ha később valós deploy történik, először az emitted guard snapshot
azonosítójával `bin/impactshop-guard-rollback.sh <snapshot>` fut, majd a
production option `0` marad és a route smoke ismétlendő.

## Smoke checklist

Kötelező smoke tagek:

- `deploy:guard-preflight`
- `deploy:checksum-verify`

Automatizált ellenőrzés:

- `bash -n bin/deploy-wpcontent-map.sh`;
- `bash tests/deploy-wpcontent-map-bastion.test.sh`;
- protected-touch local/CI parity;
- strict push audit és `git diff --check`;
- merged-main staging és production `DRY_RUN=1`;
- runtime fájl, option és cron változatlansága.

## Manual UI checklist

Ez a csomag nem módosít UI-t. A dry-run/merge után read-only kézi ellenőrzés:

- `https://app.sharity.hu/` betölt és a fő ImpactShop felület látható;
- a Shopping Assistant továbbra is megnyílik, de új runtime még nem aktív;
- egy meglévő `/go-deal` link normál céloldalra visz;
- a FactLensből visszatérő profilpanelt csak megtekinteni szabad, mentési vagy
  overwrite teszt a live-main identity drift rendezéséig nem futtatható.

## Deploy notes

- Kézi `scp`/`rsync` tiltott.
- A remote manifest read-only ebben a csomagban.
- Staging jelenleg 404 preflight blocker.
- Production dry-run után a live-only identity-panel PHP/JS drift külön
  operátori döntést igényel; addig real deploy tilos.
