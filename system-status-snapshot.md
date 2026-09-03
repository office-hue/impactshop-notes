## 2026-09-01T16:55:00+0200 - Impi source hidden-route contract

- The protected GET-only Impi source returns anonymous `404` and authenticated
  strict pilot context only; no writer or publication authority was added.
- Focused PHP and maximum-bastion tests cover both permission and callback
  authorization paths before exact production redeploy.

## 2026-08-21T13:00:00+0200 - Hatás Körök Human Touch route cutover prepared

- New additive MU-plugin owns only `app.sharity.hu/hatas-korok[/]` GET/HEAD and
  returns a hardcoded query-free 302 to `https://sharity.hu/hatas-korok`.
- Legacy community/API/dev, identity/profile-return, Offerwall, VB2026 and all
  economic writers remain unchanged. No cron/watchdog addition is needed.

## 2026-07-09T09:10:00+0200 - Dognet totals fallback localized into runtime
- A `wp-content/mu-plugins/impactshop-rest-totals.php` most már nem csak letiltja a hibás `conversions/search` probingot, hanem a canonical `raw-transactions/filter` fetch fallback helperjeit is saját scope-ban hordozza.
- Ezzel a `/impactshop/v1/totals` route többé nem külső, runtime-ban esetleg hiányzó `dognet_api_list_conversions_all(...)` definícióra támaszkodik.
- Production verifikáció: a publikus totals route újra `200` JSON választ ad, az origin és a Cloudflare útvonalon is.

## 2026-07-09T11:35:00+0200 - Dognet invalid conversions endpoint probing disabled
- `wp-content/mu-plugins/impactshop-rest-totals.php` page-level Dognet conversions lane-je fail-closed tiltást kapott.
- A korábbi runtime a Dognet által jelzett nem létező `conversions/search` és kapcsolódó endpointokat próbálta, ami 404/405 burst zajt generált.
- Az új állapot szándékosan nem próbál alternatív conversions endpointokat; az aggregáció a meglévő `dognet_api_list_conversions_all(...)` fallbackre és azon belül a canonical `raw-transactions/filter` útra támaszkodik.
- Célzott scope: incident containment és endpoint-higiénia. REST payload contract, NGO totals aggregáció és publikus surface változatlan marad.

## 2026-05-14T09:35:00+0200 - adomany-automata portal redirects fixed (JVK embed)
- Redirect korrekció: `/adomany-automata-portal-1` és `/adomany-automata-portal-2` → `https://app.sharity.hu/?impact_event_auction_embed=1&slug=jovonkvize-2026`
- Root cause: hotfix (2026-05-05) szerverre írva, nem commitolva. Deploy (`--delete` rsync) felülírta.
- Fix: PHP code-ba commitolva a feature branchre. Backup szerveren: `~/impactshop-ngo-guides.php.bak-20260514`
- Live verify: mindkét URL 301 → embed → 200

## 2026-05-11T14:40:00+0200 - JVK bank transfer confirm hotfix deployed + recovery audit clean
- A `wp-content/mu-plugins/impactshop-event-donation-widget.php` bank transfer confirm ága javítva lett: a hibás `impactshop_event_donation_generate_ticket_serial()` hívás az elérhető `impactshop_event_donation_generate_ticket_serials(...)` helperre lett cserélve.
- Bastion-approved hotfix deploy lefutott productionre és stagingre; a sync cache flush-sal zárult.
- A korábban félbemaradt production rekord `ED-20260507190704-CKqNJM` célzott recoveryt kapott: a hiányzó `ticket_serials` backfill megtörtént, a buyer confirm ág újra lett triggerelve, és a certificate státusz `sent` lett (`donation_cert_id=SHA-ADOMANY-2026-0008`).
- Recovery utáni production adat-audit: a `completed + bank_transfer` rekordok száma `2`, a maradék `ticket_serials` / `donation_cert_status` alapú anomália-sorok száma `0`.
- Célzott post-check futott a recovery utáni logablakra is; külön app-log sor nem keletkezett, ezért ennél az incidensnél a primer verifikáció a DB végállapot.

## 2026-05-07T10:40:00+0200 - impactshop intl runtime canonicalized for clean PR
- Kesz a tiszta release-branch a live-on validalt EN shop + EN challenge runtime allapothoz.
- Uj additiv overlay assetek bekerultek: `impactshop-ads-watch-intl-overlay.js`, `impactshop-identity-panel-intl-overlay.js`.
- Protected runtime frissitesek: `impactshop-ads-watch.php`, `impactshop-ads-watch.js`, `impactshop-identity-panel.php`.
- Guard-admin evidencia: kulon protected change record, rollback terv es smoke scope rogzitve a PR lane-hez.

## 2026-05-04T15:20:00+0200 - hatas-korok: Citation Verifier Integration befejezt (P2 phase)
- Az `ic_impi_run_legal_review()` fuggveny dynamic release gate logicot kapta.
- Runtime response-bol kinyerest tortennek: `citation_check` es `hallucination_guard` objektumok.
- Release decision: csak akkor engedelyezett ha citation PASS + hallucination PASS.
- Audit trail bovitett: full gate_results snapshot rogzitesre kerul az audit payloaddal.
- Szintaxis validacio: PHP-l OK, VS Code error scan OK.
- Dokumentacio frissitett: risk/coherence/security assessment MD es notes.md.
- Statusz: Gate infrastruktura keszult, release meg BLOKKOLT az ai-agent legal_ask endpont citation_verifier output megvalositasaig.
- **Next phase**: ai-agent legal_ask modositasa a citation_check details biztositasaert.

## 2026-05-04T14:40:00+0200 - hatas-korok: legal source reachability smoke refreshed
- Local tooling frissites: `npx playwright install chromium` sikeres, a korabbi browser-hiany megszunt.
- Direkt source smoke eredmenyek:
  - `https://njt.hu/` -> HTTP 200
  - `https://kuria-birosag.hu/` -> HTTP 200
  - `https://alkotmanybirosag.hu/` -> HTTP 200
- Statusz: forras-elerhetoseg jelenleg rendben; release gate tovabbra is citation/hallucination enforce-hoz kotott.

## 2026-05-04T14:25:00+0200 - hatas-korok: detailed Impi risk/coherence/security assessment documented
- Uj reszletes dokumentum: `docs/impi-ngo-workspace-detailed-plan-risk-coherence-security-2026-05-04.md`.
- A report dev legal memoria es legal tool evidence alapjan keszult (legal_memory, legal_ask, hallucination_guard, legal_fact_claim_check).
- Fo statusz: belso review-only/fail-closed uzemmod koherens, de production release tovabbra is blokkolt citation/hallucination enforce es runtime auth hardening teljesiteseig.
- Operacios megjegyzes: `source_health` lokalis futtatasa Playwright browser hiany miatt nem volt vegrehajthato.

## 2026-05-04T13:55:00+0200 - hatas-korok: Impi workspace onboarding and external runtime hooks extended
- `impact-community-app.php`: az NGO workspace Impi panel most mar access-reason alapon vezet tovabb; ha hianyzik az NGO admin jog, a workspacebol ujraaktiválható vagy ujraellenorizheto.
- A frontend response box mar a runtime allapotot is mutatja (`configured`, `connected`, `review_only`), igy a review-only es fail-closed allapotok azonnal lathatok.
- `impact-community.php`: az `image_generation` es `marketing_copy` mod most mar kulon runtime adapterrel futtathato, de tovabbra is review-only / release-blocked mintaval.
- Legal connectivity ellenorzes: `https://sharity-legal-production.up.railway.app/healthz` elerheto, de a `POST /api/ai/query` tovabbra is auth-gated, ezert service token nelkul teljes smoke nem futott.

## 2026-05-04T13:25:00+0200 - hatas-korok: Impi NGO-admin authz and durable review storage enabled
- `impact-community.php`: az Impi route-ok jogosultsaga most mar NGO admin account allapotra epul, nem csak sima circle membershipre.
- Uj `wp_ic_ngo_accounts`, `wp_ic_impi_jobs`, `wp_ic_impi_audit_events` tablak kerültek a migracios lane-be, igy az NGO admin allapot es az Impi audit/job rekordok tartosan megmaradnak.
- Uj NGO admin REST endpointok: `/wp-json/impact/v1/ngo/admin/mine`, `/ngo/admin/company-search`, `/ngo/admin/register`.
- A `legal_finance` Impi mod review-only adapteren keresztul tud kapcsolodni konfiguralt ai-agent legal runtime-hoz; sikeres runtime utan is `release_blocked=true` marad, tehat production release tovabbra is tiltott.
- Biztonsagi erosites: `/impi/jobs/{job_id}` es `/impi/audit/{audit_id}` mar csak az eredeti pseudo identityvel olvashato vissza.

## 2026-05-04T12:55:00+0200 - hatas-korok: Impi foundation orchestrator shell started
- `impact-community.php`: új foundation REST surface az NGO workspace Impi modulhoz (`/wp-json/impact/v1/impi/capabilities`, `/impi/orchestrate`, `/impi/jobs/{job_id}`, `/impi/audit/{audit_id}`).
- `impact-community-app.php`: az NGO workspace Impi placeholder helyén most már foundation UI jelenik meg, amely capability állapotot kérdez le és safe, gate-first foundation kérést tud indítani három módra.
- A jelenlegi increment nem ad ki release-ready AI választ vagy assetet; a legal/compliance/moderation gate-ek továbbra is blokkoló státuszban maradnak.
- Szintaxis verifikáció: mindkét módosított MU fájl `php -l` ellenőrzésen zöld.

## 2026-04-29T10:25:00+0200 - JVK auction scaffold canonicalized + staging payment guard verified
- Uj additiv aukcios MU runtime kerult a repo truthba:
  - `wp-content/mu-plugins/impactshop-event-auction-widget.php`
  - `wp-content/mu-plugins/impactshop-event-auction-widget-jovonkvize-1.0.0.js`
- A public read lane, session-tokenes bidder regisztracio es tranzakcios bid lane stagingen verifikalt.
- A close + winner-payment backend direkt staging smoke-kal igazolt; a route-szintu admin REST smoke-ot jelenleg staging capability drift blokkolja.
- Payment integrity fix: a success redirect mar csak ellenorzott Stripe session-status alapjan teljesit, igy callback oldalrol nem lehet tevesen `paid` allapotot irni.
- Staging safety fix: `home_url=https://app.sharity.hu/impactshop-staging/`, `stripe_mode=live`, `is_staging_runtime=true` mellett a winner-payment checkout letrehozas `null`, tehat stagingbol elo Stripe checkout mar nem nyithato.

## 2026-04-28T12:25:00+0200 - jovonkvize widget ticket-mix parity + cache-proof dev/live split
- `impactshop-event-donation-widget.php`: schema `1.3.0`, runtime `1.5.5`; separate `regular_ticket_count` / `supporter_ticket_count` storage, Stripe metadata, stats and buyer/admin email breakdown added.
- Schema hardening added for older prod/staging tables via explicit missing-column backfill, plus helper `function_exists` guards for MU double-load tolerance.
- Widget JS parity established across dev and live files with identical `1.7.2` behavior: mixed ticket selector, clearer summary copy, and additive package + extra ticket computation.
- Cache bypass canonicalized through physical versioned URLs:
  - dev: `impactshop-event-donation-widget-dev-1.7.2.js`
  - live: `impactshop-event-donation-widget-jovonkvize-1.7.2.js`
- Deploy verification during session: production and staging hotfix sync completed, cache flush completed, remote grep confirmed live files contain `WIDGET_VERSION = "1.7.2"` and additive total logic.

## 2026-04-28T11:05:00+0200 - hatas-korok report flow fixed end-to-end (store + mail)
- A `impact-community.php` report útvonala most már közvetlenül menti a bejelentéseket a `wp_ic_reports` táblába, nem csak `do_action` + log ágon fut.
- A report válasz most visszaadja a `report_id` és `emailed` mezőket is, így a kliens és a debug log külön tudja azonosítani a sikeres report kérést.
- A report e-mail a bevált Sharity `From`/`Reply-To` profillal megy, és külön `ic_post_report_mail_result` log sor rögzíti a küldési eredményt.
- A `@sharity.hu` címre menő report levél scoped Google MX SMTP routingot kapott csak erre a csatornára, hogy a kézbesítés ne a default lokális útvonalon múljon.
- Production verifikáció: report rekordok létrejöttek (`wp_ic_reports` id: 1, 2, 3), a debug logban `sent:true` mail result sorok jelentek meg, a felhasználói inbox kézhezvétel is megerősített.

## 2026-04-23T08:15:00+0200 - hatas-korok: Impi avatar UI refresh prepared as isolated clean PR
- A Hatás Körök frontendben az Impi szerző-blokk emoji avatar helyett animált meerkat videó + pulzáló kék ring UI-t kapott.
- A változás kizárólag a `impact-community-app.php` frontend markup/CSS réteget érinti; backend route vagy REST szerződés nem változott.
- A clean PR célja, hogy a korábbi kevert settlement-picker ág nélkül, önállóan merge-elhető legyen az Impi vizuális frissítés.

## 2026-04-20T06:45:00+0200 - ads-watch mobile resize hotfix canonicalized + cache-bypass closed
- A 2026-04-19-es mobil-freeze incidens resize hotfixe most már repo truthként is rögzítve van.
- `impactshop-ads-watch.js`: IMA resize throttle + hidden-doc guard + tiny-delta skip.
- `impactshop-ads-watch.php`: asset verzió bump `2.5.65 -> 2.5.66`, hogy az új JS URL megkerülje a beragadt CDN cache-t.
- Publikus verifikáció megtörtént:
  - `X-ImpactShop-AdsWatch-Version: 2.5.66`
  - `impactshop-ads-watch.js?ver=2.5.66`
  - publikus JS hash: `3cd313f32a253cff5226a8322a971d7f529bba999cf3b698af22a88da48a614b`
- `impact-challenge-ui-smoke.sh` továbbra is zöld.
- Külön audit finding rögzítve: a dokumentált production deploy path és a ténylegesen kiszolgált live asset út között drift gyanú áll fenn.

## 2026-04-20T08:30:00+0200 - production deploy-path audit closed for ads-watch incident follow-up
- Audit bizonyította, hogy a kanonikus production WordPress runtime gyökér továbbra is `/home/sharityh/app`.
- A publikus belépési pont `public_html/index.php`, de ez csak wrapper, amely az `../app/wp-blog-header.php` runtime-ra mutat.
- `bin/deploy-wpcontent-map.sh` most már explicit production-origin alignment ellenőrzést futtat erre a wrapper-kapcsolatra.
- `bin/post-deploy-activate.sh` production pathja korrigálva lett `/home/sharityh/app` értékre.
- A runbook és az audit/follow-up docs most már ugyanazt a production truth-ot nevezik meg.

## 2026-04-20T07:00:00+0200 - ads-watch review-fix: trailing resize after burst
- Review-fix kör: a leading-edge throttle mellé trailing IMA resize futás került, hogy a mobil resize burst utolsó konténermérete se vesszen el.
- A kapcsolódó postmortem follow-up szövegben javítva lett az `eldobj` → `eldob` elírás.
- Gyors verifikáció: `node --check` OK, `impact-challenge-ui-smoke.sh` OK.

## 2026-04-26T11:40:00+0200 - fix(impact-challenge): always-reward flag rollout + CTA sandbox parity
- `wp-content/mu-plugins/impactshop-ads-watch.php`: default-off always-reward feature flag gate added (`IMPACTSHOP_ADS_ALWAYS_REWARD_DEFAULT=false`) with filter-controlled activation.
- `wp-content/mu-plugins/impactshop-click-tracking.php`: always-reward dedupe parity added and CTA sandbox early-return introduced for dev-clone safe testing.
- Bastion-approved deploy executed to production+staging, with auto backup/manifests + rollback script emitted by `scripts/hotfix-sync.sh`.
- Cache flush completed on both environments; production runtime check confirms flag file active and CTA post-flag mismatch metric = 0.

## 2026-04-17T09:45:00+0200 - analytics guard stabilization: routes + skip telemetry + audit range fix
- Added signed analytics canary routes in MU runtime: `/wp-json/impact/v1/analytics/summary` and `/wp-json/impact/v1/analytics/flags`.
- Added SKIP telemetry log (`.codex/logs/analytics-skip-events.log`) and 24h WARN threshold in `scripts/verify/analytics-suite.sh`.
- Hardened `scripts/safe-repo-audit.sh` push range resolution to avoid full-history false positives on fresh branches.
- No deploy executed in this change set; this is code + ops readiness only.

## 2026-04-16T11:00:00+0200 — fix: sync 4 mu-plugins to match production state + guard bypass fix
- 4 mu-plugin szinkronizálva production-nal: impactshop-boot.php, impactshop-offerwall.php, impact-community.php, impactshop-netflix-shortcodes.php
- safe-repo-audit.sh: SAFE_REPO_AUDIT_ALLOW_REMOTE_WRITE bypass hozzáadva (notes.md false positive remote-write minták)
- origin/main merge: PR #103 (ads-watch security) + PR #101 (ngo-guides v1.1.4) integrálva

## 2026-04-14T15:50:00+0200 — fix(review): ads-watch sandbox trust tightening
- Sandbox write mode most már csak admin+nonce request esetén aktiválható; query param fallback eltávolítva.
- `allocate_votes` sandbox ág validációs sorrendje javítva (sandbox return az NGO-mismatch elé).
- Asset cache-buster bump: `IMPACTSHOP_ADS_WATCH_VERSION=2.5.65`.

## 2026-04-14T15:25:00+0200 — security(ads-watch): debug hardening + dev-clone sandbox route guard
- Debug endpoint lezárva alapértelmezésben: route nincs regisztrálva ha `IMPACTSHOP_ADS_DEBUG_ENDPOINT_ENABLED=false`.
- Dev clone route anon hozzáférés tiltva (`/impact-challenge-dev` -> 404), noindex header aktív.
- Production sync megtörtént (`impactshop-ads-watch.php`, `impactshop-ads-watch.js`), remote hash parity ellenőrizve.
- Operatív smoke: `https://app.sharity.hu/wp-json/` 200, debug endpoint 404, dev clone route 404.

## 2026-04-07T21:00:00+0200 — fix(ads-watch): v2.5.52 sponsor video freeze fix
- A v2.5.52 visszaállítja a 7 kritikus sponsor return patternt ami v2.5.55-ben működött de v2.5.51-ben elveszett.
- Érintett fájlok: impactshop-ads-watch.js, .php, .css (917 ins, 339 del vs v2.5.32 origin/main).
- Kulcs javítások: externalNavigationSource/externalNavigationVisibilityLost tracking, sponsor CTA native _blank link, visibility handler minden módhoz, adsLoader.contentComplete() elhelyezés.
- Gyökér ok: v2.5.51 nem tartalmazta a v2.5.55 sponsor-specifikus kezelését → Chrome/Safari freeze.
- Production deploy megtörtént és verifikált (x-impactshop-adswatch-version: 2.5.52).
- Change record: docs/protected-change-records/2026-04-07-ads-watch-sponsor-v252.md

## 2026-04-01T08:58:00+0200 — JYSK riport max-védett guide surface
- A `jysk-riport` route-család név szerint is bekerült a guide-rendszer max-védett perimeterébe: `/jysk-riport/`, `/jysk-riport/?print=1`, `/jysk-riport.data.json`.
- A machine-readable protected modell új `guide_runtime` smoke csoportot kapott, így guide/JYSK touch esetén kötelező a route render, print render és JSON payload smoke scope.

## 2026-03-31T22:10:00+0200 — Guard hardening propagation a közös policy rétegbe
- Az utóbbi guard/review-fix körök általánosítható tanulságai most már nem csak surface-specifikus runbookokban élnek.
- Új közös szabály lett: local/CI guard parity kötelező, a guarded push önálló belépési pont, a protected env párokat együtt kell kezelni, review-fix után kötelező a teljes recheck, stale empty cache pedig külön kockázati kategória.
- Frissült és a fő repo szabályrendszerének policy felületére is kiemelésre került a `docs/protected-file-change-checklist.md`.

## 2026-03-26T19:45:00+0100 — fix: impact-community.php ngo_admin_url URL-ek visszaállítva
- Merge conflict resolution hiba: auto-merge visszaállította `/impact-challenge/ngo-admin/` URL-eket.
- Javítva: `/impact-shop_ngo/` (sorok: 2058, 2647, 4743 — ngo_admin_url ×2 + reset_url ×1).
- Production nem érintett (PR #83 még nyitott, nincs deploy).

## 2026-03-26T19:30:00+0100 — feat/jovonkvize-ticket-count merge: origin/main beolvasztva
- Konflikció-feloldás: `impact-community.php` test_mode bypass (main) megtartva; `impactshop-event-donation-widget.php` ticket_serials DB oszlop + schema 1.2.0 (feature) megtartva; `impactshop-ngo-guides.php` jogi-dokumentumok route + version 1.1.2 (feature) megtartva.
- `scripts/hatas-korok-load-memory.sh`: feature branch verzió megtartva.
- `impact-community-app.php`: auto-merge, konfliktus nem volt.

## 2026-03-26T16:54:00+0100 — Impact Challenge kanonikus baseline rögzítve
- Létrejött az egységes kanonikus alapdokumentum: `docs/impact-challenge-canonical-baseline.md`.
- Ettől kezdve ez a baseline az elsődleges referencia az Impact Challenge teljes scope-jára: ads-watch, autobanner, offerwall, identity, pont/szavazat, affiliate glue, PWA és a guide rendszer.
- A fő lokális Impact Challenge runtime MU-plugin kör is vissza lett zárva `0444` read-only célállapotra, a guide rendszer már korábban lezárt `0444/0555` állapotával együtt.
- PR / merge / deploy kapuk most már explicit módon ehhez a baseline-hoz mérik az eltérést.

## 2026-03-26T16:40:00+0100 — NGO guide teljes készlet beton védelem alá zárva
- A teljes guide készlet most már nem csak router-szinten, hanem teljes fáfa-szinten védett: `impactshop-ngo-guides.php` és `wp-content/mu-plugins/impactshop-ngo-guides/**` bekerült a protected perimeterbe.
- A productionről hiányzó lokális guide elemek visszaszinkronizálva a repo-ba, így megszűnt a külön élő és külön lokális guide-készlet állapota.
- Rögzített szabály: guide-route, guide-HTML, fordítás, jogi asset, PDF és renderelt output csak explicit felhasználói engedéllyel módosítható; sem automatika, sem deploy nem írhatja felül engedély nélkül.
- Fizikai célállapot: lokálban és productionön guide fájlok `0444`, guide könyvtárak `0555`.

## 2026-03-26T15:42:00+0100 — Autobanner/CJ runtime perimeter lezárva
- A hiányzó WordPress oldali autobanner/CJ runtime rés bekerült a protected körbe: `wp-content/mu-plugins/impactshop-cj.php`.
- Ezzel a WordPress oldali autobanner runtime/import/rotáció/redirect/CJ bridge körben nincs ismert nyitott pipeline-kódfájl a protected listán kívül.

## 2026-03-26T15:24:00+0100 — Protected-file koherencia és UI checklist szabály bevezetve
- Védett fájl módosítás előtt kötelezővé vált: koherencia vizsgálat, kockázatelemzés, érintett funkciólista.
- Védett fájl módosítás után kötelezővé vált: post-merge/deploy ellenőrzési kör és külön manuális UI checklist a felhasználónak.
- Kiemelt kanonikus dokumentumként frissült a `docs/protected-file-change-checklist.md`, és a szabály bekötve a PR/deploy/policy fájlakba.

## 2026-03-26T15:12:00+0100 — PR / merge / push / deploy bastion szabályok szigorítva
- A bástyavédelmi és írásvédettségi szabályok most már explicit részei a PR policynek, PR template-nek, exit checklistnek és deploy runbooknak is.
- Impact Challenge esetén rögzítve: additive new-code first; legacy touch csak explicit jóváhagyással; deploy után a védett fájlok read-only visszazárása kötelező.

## 2026-03-26T14:58:30+0100 — Impact Challenge bastion perimeter bővítés
- A bástya védelem és a védett fájllista kiterjesztve a teljes Impact Challenge működési körre: ads-watch, auto-banner, offerwall, NGO selector/guides/card, identity, points engine, vote purchase, quarter-close, redirect/go bekötések, event donation widgetek, PWA és kapcsolódó workflow modulok.
- Policy rögzítve: elsődleges fejlesztési út csak additív, új kód; meglévő Impact Challenge kód módosítása csak külön, explicit jóváhagyással és backup+rollback mellett.
- Kanonikus források frissítve: docs/impactshop-guard-config.json, docs/bastion-protection-extension-plan.md, docs/ai-assistant-canonical-policy.md, docs/bastion-guard-status.md, AGENTS.md

## 2026-03-25 — CJ + Dognet go-deal fix (impactshop-boot.php, impactshop-cj.php)
- impactshop-boot.php: Dognet tracking URL passthrough (go.dognet.com href → skip API, append d1+data5)
- impactshop-cj.php: limit 200→5000, --advertiser-ids CLI param, Skechers hozzáadva (473 link)
- impactshop-cj.php: --merge flag a sync_links-hez (meglévő linkek megőrzése szűrt fetch esetén)
- fizz shop törölve mindkét repo shops_registry.json-ból

## 2026-03-25 — ticket_serials DB mentés (schema v1.2.0)
- ticket_serials TEXT oszlop a donations táblában
- fulfill(): sorszámok JSON-ben DB-be kerülnek, email fallback

## 2026-03-25 21:55 - Hatás Körök smoke deploy rutinba kötve
- `bin/deploy-wpcontent-map.sh`: production deploy végén automatikus Hatás Körök read-only smoke
- `bin/post-deploy-checklist.sh`: kibővítve a Hatás Körök smoke ellenőrzéssel
- Validáció: shell parse OK + production smoke OK

## 2026-03-25 21:45 - Hatás Körök post-deploy smoke tooling
- Új read-only smoke script: `scripts/hatas-korok-post-deploy-smoke.sh`
- Új checklist: `docs/hatas-korok-post-deploy-checklist.md`
- Ellenőrzési kör: `/hatas-korok`, `auth/status`, `circles?page=1`, HTML bootstrap markerek

## 2026-03-25 — cert aláírás kép csere (v1.5.3)
- Pecsétes aláírás lecserélve pecsét nélküli változatra
- Új kép: bujdoso-alaiiras-2026.png (IMG_3880.HEIC forrás)

## 2026-03-25 13:42 — Meghatalmazás PDF frissítve
- Új aláírású meghatalmazás feltöltve (pecsét nélküli, magánszemély)
- Fájl: sharity-meghatalmazas-adomanyigazolas.pdf (2.4MB)
- Prod szerveren frissítve: s59.tarhely.com

## 2026-03-25 — v1.5.2 meghatalmazás PDF auto-csatolás
- sharity-meghatalmazas-adomanyigazolas.pdf feltöltve szerverre (267KB, 444 jog)
- send_certificate_for_donation() csatolja a cert mellé (WPMU_PLUGIN_DIR + file_exists)

## 2026-03-25 — vagy egyszerű adományozás szeparátor
- solo jegyek és preset összeg ikonok közé beszúrva (jovonkvize.js)

## 2026-03-25 — v1.5.1 cert aláírási blokk javítva
- igazgatósági tagja → meghatalmazott, Sharity Zrt. → Sharity Adományszervező Alapítvány (PDF + email szöveg)
- meghatalmazás HTML dokumentum létrehozva: docs/meghatalmazas-adomanyigazolas-kiallitas.html

## 2026-03-25 — v1.3.0 solo CSS !important
- solo-select bg/color/border !important, host CSS override javítva.

## 2026-03-24 — solo ticket CSS fix
- __or-sep, __solo-tickets, __solo-label, __solo-select CSS hozzáadva (hiányzó stílusok, fehér bg szivárgás javítva).

## 2026-03-24c — PHP version 1.2.0 deploy
- IMPACTSHOP_EVENT_DONATION_VERSION bumped 1.1.0 → 1.2.0, prod verified.

## 2026-03-24c — PHP version 1.2.0 deploy
- IMPACTSHOP_EVENT_DONATION_VERSION bumped 1.1.0 → 1.2.0, prod verified.

## 2026-05-28c - jovonkvize.js production deploy (solo jegyvásárlás)
- `impactshop-event-donation-widget-jovonkvize.js` frissítve: TICKET_UNIT_PRICE=150000, solo dropdown, change handler.

## 2026-05-28b - Jövőnk Vize: version bump 1.1.0 + cache-bust embed kód
- `IMPACTSHOP_EVENT_DONATION_VERSION` 1.0.0 → 1.1.0, prod-ra deploy-olva.
- JS embed URL már `?v=1.1.0`-t tartalmaz.

## 2026-05-28 - Jövőnk Vize: solo jegyvásárlás + tranzakció értesítő + cert BCC
- Branch: `feat/jovonkvize-ticket-count` — dev JS + PHP módosítások.
- JS dev: `TICKET_UNIT_PRICE=150000`, `STANDALONE_TICKET_MAX=10`, solo dropdown, change handler, init() feltöltés.
- PHP: `impactshop_event_donation_send_transaction_notification()` — minden COMMIT után email bujdoso.arnold@ + koncz.veronika@.
- PHP: adományigazolás emailekbe BCC: `bujdoso.arnold@bujdosoiroda.com`.
- Nincs deploy — csak dev verzió, production deploy külön jóváhagyás után.

## 2026-03-24 - Jövőnk Vize gála widget: ticket_count + selected_package
- Branch: `feat/jovonkvize-ticket-count` from `main`.
- `impactshop-event-donation-widget.php` schema 1.0.0 → 1.1.0: `ticket_count` + `selected_package` mentés DB-be és Stripe metadatába.
- DB: `dbDelta` additive (meglévő soroknál DEFAULT érték).
- Embed URL fixelve: `impactshop-event-donation-widget-jovonkvize.js`.

## 2026-03-24 - Impact Community (Hatás Körök) Sprint 1+2
- Branch: `feat/impact-community-sprint1` from `origin/main`.
- New modules: `impact-community.php` (backend, 978 lines) + `impact-community-app.php` (SPA frontend, 1279 lines).
- DB: 7 tables (ic_circles, ic_memberships, ic_missions, ic_mission_completions, ic_buddies, ic_posts, ic_circle_stats).
- REST API: 11 endpoints under `impact/v1`.
- Feature flag: `IMPACT_COMMUNITY_ENABLED` — must be set in production wp-config.php.
- Route: `/hatas-korok/` via `template_redirect` priority 4.
- Guard coverage: `impactall` mu-plugins parity guard monitors presence.

## 2026-03-23 15:45:00 CET - autobanner rotation and canonical dognet snapshot
- Branch continuity for autobanner runtime hardening: `fix/autobanner-feed-import-clean`.
- Added per-user autobanner rotation state via `pseudo_id`, removing the old 300-item repeat-prone pool behavior.
- Canonical Dognet autobanner mapping now accepts `dognet_program_id` / `program_id` from the `Shops` CSV in addition to `dognet_base` CID parsing.
- Sheets banner ingest now keeps every non-empty canonical banner row, allowing shop-logo fallback when `img` is empty instead of dropping the offer.
- Production follow-up remains blocked by MU-plugin filesystem permissions on `/home/sharityh/app/wp-content/mu-plugins/*.php` (read-only `0444`).

## 2026-03-23 12:25:00 CET - autobanner feed import snapshot
- Branch continuity for autobanner inventory path: `fix/autobanner-feed-import-clean`.
- Added direct JSON feed discovery/import helpers and `wp impactshop auto-banner import-feed`.
- Raised source caps: Google Sheets `50 -> 1000`, Dognet `120 -> 1000`.
- Validation: PHP lint clean on `impactshop-auto-banner.php`, `impactshop-auto-banner-sync.php`, `impactshop-auto-banner-dognet.php`.

# ImpactShop – Projekt státusz

## 2026-03-23 10:47:00 CET - offerwall hotfix continuity snapshot
- Branch continuity for protected MU-plugin changes: `hotfix/offerwall-sync-20260323`.
- Production hotfix scope: survey chooser restore (`Sharity`, `CPX Research`, `AyeT`), article quiz reward-token fallback, signed WP email proxy deployment.
- Production validation recorded: `impact-challenge` HTML contains survey chooser + CPX/AyeT containers; email proxy test returned `HTTP 200` with `{"sent":true,"count":1}`.
- Rollback path: restore the pre-deploy MU-plugin backup directory created on the production host before targeted copy.

*Generálva:* 2026-02-26 07:48:32 +0100 (Bujdoso-Mac-mini)

## Meta
- Gyökér: /Users/bujdosoarnold/Developer/GitHub
- Környezet: local
- SSH_HOST: nincs megadva
- Git ág: main
- Git hash: 1950945
- Módosított fájlok száma: 33

## REST healthcheck
- Staging: HTTP 200 (1641 ms, ok) – https://sharity.hu/impactshop-staging/wp-json/ (redirected_to:app.sharity.hu)
- Production: HTTP 200 (1222 ms, ok) – https://app.sharity.hu/wp-json/

## Git státusz
 D .codex/cron/impact-social-ledger-sync.sh
 D .codex/cron/impactshop-snippet-refresh.sh
 D .codex/cron/workspace-backup.sh
 D .codex/scripts/impact-social-ledger-sync.php
 D .codex/scripts/lib/guard-common.sh
 D .codex/scripts/tradetracker-scope-check.sh
 M .gitignore
 M docs/system-recovery-map.md
 M scripts/shortcode_sync/shortcode_sync_run_REAL.sh
 M scripts/workspace-backup.sh
 M services/capi-proxy/README.md
?? .codex
?? .continue/
?? .venv-old-20260225-1940/
?? .vscode/
?? "Befektet\305\221 c\303\255mlista/"
?? "Cikk kv\303\255z/"
?? "Civil szervezetek/"
?? "Fillout export/"
?? IKEA/
?? JYSK/
?? "K\303\251perny\305\221k\303\251pek hib\303\241kr\303\263l/"
?? "OTP Simple API/"
?? Survey/
?? _archive/
?? ads.txt
?? impactshop-notes-clean-2/
?? impactshop-notes-clean/
?? impactshop-notes-pr/
?? partner-docs.html
?? robots.txt
?? services/capi-proxy/DEPRECATED.md
?? "\303\201SZF/"

## Fájlstruktúra (max depth 2, top 200 elem)
~~~
.
.DS_Store
.backups
.backups/git-status-20251207-202853.txt
.backups/git-status-20251207-203439.txt
.backups/impactshop-git-20251207-202853.bundle
.backups/impactshop-git-20251207-203439.bundle
.backups/working-tree-20251207-203439.patch
.codex
.continue
.continue/agents
.git
.git/.DS_Store
.git/COMMIT_EDITMSG
.git/FETCH_HEAD
.git/HEAD
.git/config
.git/description
.git/hooks
.git/index
.git/index 2
.git/info
.git/logs
.git/objects
.git/refs
.github
.github/copilot-instructions.md
.github/dependabot.yml
.github/workflows
.gitignore
.markdownlint.json
.prettierignore
.production_env
.staging_env
.venv
.venv-1
.venv-1/.gitignore
.venv-1/bin
.venv-1/include
.venv-1/lib
.venv-1/pyvenv.cfg
.venv-1/share
.venv-old-20260225-1940
.venv-old-20260225-1940/bin
.venv-old-20260225-1940/include
.venv-old-20260225-1940/lib
.venv-old-20260225-1940/pyvenv.cfg
.venv-old-20260225-1940/share
.venv/bin
.venv/include
.venv/lib
.venv/pyvenv.cfg
.venv/share
.vscode
.vscode/extensions-installed.txt
.vscode/extensions.json
.vscode/extensions.lock
.vscode/launch.json
.vscode/settings.json
.vscode/tasks.json
000-dognet-token-ttl.php
=
ÁSZF
ÁSZF/.DS_Store
ÁSZF/1sz_melleklet_szponzori_megallapodas.md
ÁSZF/ÁSZF 1.docx
ÁSZF/Sharity ÁSZF_2022_11_10_korr nélk.docx
ÁSZF/Sharity_ASZF_2026.md
ÁSZF/Sharity_Adatkezelési_tájékoztató_2024_PDF.pdf
ÁSZF/Win4Good szabályzat.pdf
Befektető címlista
Befektető címlista/TokePortal - Sharity részvény-allokációs tábla_08.05_nagykövetek nélkül.xlsx
Cikk kvíz
Cikk kvíz/articles_quiz.csv
Civil szervezetek
Civil szervezetek/szervezetek-2026-02-13.xlsx
Fillout export
Fillout export/Fillout Válassz egy ügyet results.csv
Google vision
Google vision/durable-verve-458410-s5-df32776d6854.json
Google vision/f6e927b0994e7d7fb36abd600a100b05.webp
Graphiql
Graphiql/graphiql-main
IKEA
IKEA/Átvilágításhoz szükséges nyilatkozat_DD short_NEW (1).pdf
IKEA/Sharity_IKEA_Veled_Kozosen_Ajanlat_2026.docx
IKEA/Untitled-1.ini
IKEA/dr._Korossy_Csaba___A_kiadmanyozas_hibaja_miatti_semmisseg_es_az_elevules_a_Kuria_itelkezesenek_tukreben.pdf
IKEA/fy26_twy_reszveteli_szabalyzat_hu_plg.pdf
IKEA/generate_ajanlatot.py
IKEA/~$arity_IKEA_Veled_Kozosen_Ajanlat_2026.docx
Impactshop Wallet Key.cer
Impactshop Wallet Key.p12
Impactshop.p12
JYSK
JYSK/JYSK_WhoisJYSK_Cutdown_20sec_JHU.mp4
Képernyőképek hibákról
Képernyőképek hibákról/IMG_3734.png
Képernyőképek hibákról/IMG_3739.png
Képernyőképek hibákról/IMG_3741.mov
Képernyőképek hibákról/IMG_3746.png
Makefile
OTP Simple API
OTP Simple API/PaymentService_SimplePay_2.x_Payment_HU_251105 (3).pdf
README.md
Survey
Survey/.DS_Store
Survey/Kérdőív kutatás és gamifikáció.docx
Survey/codex_batch_logic_implementation_guide.md
Survey/codex_implementation_guide.md
Survey/codex_implementation_guide.zip
Survey/kerdesbank_minta.csv
Survey/question_mapping.csv
Survey/segment_taxonomy.csv
Survey/sharity_master_builder
Survey/sharity_master_builder.zip
Survey/sharity_questions_MASTER_1250.csv
Survey/sharity_questions_batch1_250.csv
Survey/sharity_questions_batch2_250.csv
Survey/sharity_questions_batch3_250.csv
Survey/sharity_questions_batch4_250.csv
Survey/sharity_questions_batch5_250.csv
_archive
_archive/aszf-extract
_archive/examples
_archive/old-snippets
active_plugins_diff_testdiff.txt
ads-bridge
ads-bridge/.codex
ads-bridge/.env
ads-bridge/.env.example
ads-bridge/AYET_OFFERWALL_INTEGRATION.md
ads-bridge/IMPACT_ADS_BRIDGE_SPEC.md
ads-bridge/bin
ads-bridge/services
ads-bridge/setup-ads-bridge.sh
ads-bridge/wp-content
ads.txt
ai-agent
ai-agent/.DS_Store
ai-agent/.codex
ai-agent/.env
ai-agent/.env.example
ai-agent/.env.graphmemory
ai-agent/.env.local
ai-agent/Drive desktop APP 
ai-agent/Feladatok
ai-agent/Google Ads
ai-agent/Impi Tudásbázis
ai-agent/OCR
ai-agent/ai-agent-baseline-2026-01-05.md
ai-agent/apps
ai-agent/client_secret_438682830954-tr4grg5b1gqrr7eribckcihcigk3nfmu.apps.googleusercontent.com.json
ai-agent/config
ai-agent/data
ai-agent/dist
ai-agent/docs
ai-agent/durable-verve-458410-s5-3b5f4ae2531f.json
ai-agent/durable-verve-458410-s5-9eeee98b5969.json
ai-agent/dwd_clients.csv
ai-agent/ngo_codes.csv
ai-agent/node_modules
ai-agent/package-lock.json
ai-agent/package.json
ai-agent/scripts
ai-agent/secrets
ai-agent/services
ai-agent/system-status-snapshot.md
ai-agent/tests
ai-agent/tmp
ai-agent/tools
ai-agent/tsconfig.json
ai-agent/types
backup20251208
backup20251208/.DS_Store
backups
backups/impactshop-netflix-shortcodes.php.20251202-174457
backups/impactshop-shortcode-pack.php.20251202-174457
backups/page-refresh-2026-02-19
bin
bin/codex-tui
bin/dev-qa.sh
bin/ngo-rate-limit-check.sh
bin/preflight-check.sh
blog
blog/.dockerignore
blog/.git
blog/.gitattributes
blog/.github
blog/.gitignore
blog/.graphiticfg.yml
blog/.kamal
blog/.rubocop.yml
blog/.ruby-version
blog/Dockerfile
blog/Gemfile
blog/Gemfile.lock
blog/README.md
blog/Rakefile
blog/app
~~~

## Jegyzetek
- A ~/bin alatt elérhető helper scriptek: codex-refresh, impactresume.
- A fájl automatikusan generálva (scripts/status-snapshot.sh).
- 2025-10-14: Netflix/Deals shortcode REST go-deal linkpreferencia él; deploy után Elementor cache flush + REST warmup kötelező.
- GPT/Sonnet promptokat mindig szakmai review előz meg – automatikus végrehajtás tiltott, eltéréseket jelezd.

---
_Auto update: 2026-03-05 10:47:00 CET_

### Health check summary

```
Pre-push safe audit now evaluates pushed commit range (--mode push)
Doc continuity checks remain strict for committed changes
```

**Baseline referencia:** impactshop-baseline-2026-03-03.md

## 2026-05-12 impactshop selector restore hotfix
- `wp-content/mu-plugins/impactshop-action-bar.php`: visszaallitva a nyelv/orszag selector UI blokk (`sharity-slc`) a kanonikus main/prod lane-ben.
- Ok: selector csak nemzetkozi worktree-ben volt jelen, a kanonikus branch/prod allapotbol kimaradt.
- Hotfix branch: `fix/impactshop-selector-restore`, commit: `fix(intl): restore language-country selector on impactshop`.

---
_Auto update: 2026-03-05 10:12:00 CET_

### Health check summary

```
Identity nickname save path fixed (profile greeting + Legacy Wall sync)
PHP lint ok for identity/gamification modules
```

**Baseline referencia:** impactshop-baseline-2026-03-03.md

## 2026-03-09 Workflow Infra Update
- Dev-memory workflow 1-8 aktiválva (pre-task, context-pack, memory gate, PR auto-memory, commit template/hook, incident, digest, Copilot MCP guard).
- Hookok újratelepítve; napi digest cron aktív.

## 2026-03-10 15:34:42 CET - ads-watch strict audit continuity fix
- Added explicit docs continuity note for `wp-content/mu-plugins/impactshop-ads-watch.css` module update.
- Added matching conversation summary evidence so `safe-repo-audit --mode push --strict` sees note continuity in range.

## 2026-03-20 - Miele Jövőnk Vize gála widget modulok
- Új widget modulok hozzáadva: `wp-content/mu-plugins/impactshop-event-donation-widget-dev.js` (fejlesztési), `wp-content/mu-plugins/impactshop-event-donation-widget-jovonkvize.js` (éles Miele kampány).
- Funkciók: Ezüst/Arany/Platina csomag választó (1M/2M/3M Ft), gálajegy szám selector (max 2/4/6 fő), ezres törés összegmezőben.
- Stripe maximum_amount: 2 500 000 → 3 500 000 Ft (`impactshop-event-donation-widget.php`).
- Deploy: `sharityh@s59.tarhely.com:/home/sharityh/app/wp-content/mu-plugins/`

## 2026-03-24 - Impact Community plugin (Hatás Körök) — Sprint 1–16 + audit

- IC_DB_VERSION: 1.3.7 | 22 DB tábla | 47+ REST endpoint | 15+ cron
- Branch: `feat/impact-community-sprint1` | PR #73 (office-hue/impactshop-notes)
- Audit: 11 SQL séma-inkonzisztencia javítva; PHP lint OK; security OK
- Status: merge pending → prod deploy szükséges (rsync to s59)
### 2026-03-25T06:24:01Z | jovonkvize STYLE_ID collision fix deployed | branch=feat/jovonkvize-ticket-count | result=OK
### 2026-03-25T07:10:40Z | v1.4.0 solo ticket input fix deployed | result=OK
### 2026-03-25T07:39:33Z | v1.5.0 buyer email + ticket serial deploy | result=OK

### 2026-03-25 — ÁSZF link kattintható (jovonkvize widget)
- consent checkbox szöveg → kattintható `<a>` link: `/ngo-guides/jogi-dokumentumok/`
- `stopPropagation` a link click-re → checkbox nem togglel
- JS deployed, chmod 444

## 2026-03-26 — ngo-admin route bevezetés
- ic_ngo_admin_template_redirect() hozzáadva: /ngo-admin/ route kezelés
- impact-community-app.php guard refactored (NGO admin MU-init safe)
- PHP lint: OK, branch: feat/jovonkvize-ticket-count

## 2026-03-26 — IC canonical restore (REVERT bf227d9e → 10c9930d state)
- impact-community.php restored to 10c9930d canonical
- impact-community-app.php restored to 10c9930d canonical
- Reverted: ic_ngo_admin_template_redirect, guard refactor, /ngo-admin/ URLs

## 2026-03-26 — Ads Watch external return recovery
- `impactshop-ads-watch.js` now tracks outbound sponsor CTA / autobanner tab opens and reloads on return when Safari restores the original tab in a bad visual state.
- `impactshop-ads-watch.php` version bumped to `2.5.30` for Cloudflare/browser cache bust.
- Live verification: `/impact-challenge/` references `impactshop-ads-watch.js?ver=2.5.30`; direct header check returned `cf-cache-status: MISS`.

## 2026-03-26 — Ads Watch initial banner block fix
- `impactshop-ads-watch.js`: page init no longer starts the idle auto-banner loop over the player.
- legacy `loadAutoBanner()` completion now hides the banner and returns control to the start state instead of chaining forever.
- `impactshop-ads-watch.php`: version bumped to `2.5.31`.
- Live verification: desktop and mobile Playwright snapshots again show `▶ Reklám megtekintése` on `/impact-challenge/`.

## 2026-03-26 — Ads Watch nav revert, Safari fixes retained
- Reverted the 8-icon Ads Watch floating nav back to the earlier 4-button layout in `impactshop-ads-watch.php` and `impactshop-ads-watch.css`.
- Kept the later Safari external-tab recovery logic in `impactshop-ads-watch.js`.
- `impactshop-ads-watch` asset version bumped to `2.5.32`.
- Live verification: `/impact-challenge/` now references `impactshop-ads-watch.css?ver=2.5.32` and the mobile snapshot again shows 4 nav buttons plus visible `Reklám megtekintése`.
## 2026-03-31 21:45:00 CET - guard baseline bootstrap on clean main lineage

- Clean `main`-based guard baseline branch prepared so later protected runtime work can use canonical guarded commit/push/PR flow.
- Minimum control-plane history now exists in git, not only in dirty worktree state.
- Control-plane hardening added: protected touch gate, workflow lane guard, guarded push prechecks, and protected classification for the guard control plane itself.
- Follow-up expected: baseline PR/merge first, then runtime lanes such as AyeT surveywall restoration.
## 2026-03-31 21:52:00 CET - guard baseline review hardening

- `guarded-push.sh` hardened further: lane check + protected-touch gate mellé safe audit és memory gate is bekerült, ha a repo ezeket már eléri.
- `workflow-state.sh` most már worktree-ben is a valódi repo-identitást használja a git common dir alapján, nem a worktree könyvtárnevét.
## 2026-03-31 21:58:00 CET - guard baseline upstream fallback hardening

- Push-mode guardok új branch esetén upstream hiányában már nem az üres tree/full history felé esnek vissza.
- A push-base feloldás sorrendje: upstream, `origin/HEAD`, `origin/main`, `origin/master`, `main`, `master`, végül `HEAD^` és csak legvégül empty tree.
- `workflow-state.sh` detached HEAD esetén explicit `detached` branch-értéket ad.

## 2026-03-31T22:22:00+0200 — AyeT surveywall runtime lane isolated
- Clean runtime branch: `fix/ayet-surveywall-runtime`, based on `origin/main` after guard baseline merge.
- AyeT runtime separation kept explicit:
  - offerwall/game slot: `25643`
  - surveywall slot: `25740`
  - surveywall profile hash: `b970533bbaf884d085d7c0e6734da1c2`
- `impactshop_ayet_surveys()` now serves surveywall questionnaires instead of general AyeT offerwall inventory.
- `impactshop_offerwall_health()` exposes both `ayet_adslot` and `ayet_surveywall` diagnostics for post-deploy verification.

## 2026-03-31T22:34:00+0200 — AyeT PR guard workflow aligned
- `.github/workflows/protect-critical-files.yml` now treats the paired deploy env
  files as part of the same overrideable protected runtime lane when continuity
  evidence is present.
- This keeps GitHub PR validation aligned with the merged local guard baseline
  for the AyeT surveywall runtime branch.

## 2026-03-31T22:42:00+0200 — AyeT review fixes applied
- Surveywall cache flush now clears the active `default` cache key too.
- `impactshop_ayet_surveys()` returns `surveys: []` consistently on
  `missing_pseudo`.
- Survey refresh is rate-limited per pseudo, and the survey tab activation logic
  now respects the server-side disabled state.

## 2026-04-01 09:15:00 CET - guard deploy path realignment

- A `bin/impactshop-guard-preflight.sh` most már a git common dir alapján is felismeri az ugyanahhoz a repóhoz tartozó worktree-ket, így nem dob hamis `repo root mismatch` hibát tiszta worktree deploy-előkészítésnél.
- A `docs/impactshop-guard-config.json` és `docs/impactshop-guard-hashes.json` repo-meta többé nem a régi `ops/adswatch-clean` branchre mutat, hanem a kanonikus `main` ágra.
- A kanonikus policy és deploy runbook külön rögzíti, hogy hibás guard deploy infrastruktúra esetén csak explicit nem-kanonikus, auditált incidens restore megengedett.
## 2026-04-01 10:00:00 CET - guard deploy review follow-up

- A `bin/impactshop-guard-deploy.sh` checksum output formátuma most már egyezik a kézzel commitolt `.sha256` fájlokkal (`docs/...` útvonal).
- A `docs/impactshop-guard-hashes.json` manifest frissült a `docs/impactshop-guard-config.json` és `docs/impactshop-guard-config.sha256` új digestjeire.
- A `docs/bastion-guard-status.md` `Last updated` mezője visszakapta az auditbarát dátum + idő + zóna formátumot.
## 2026-04-01 15:55:00 CET - JYSK report source restoration

- A `/jysk-riport/`, `/jysk-riport/?print=1` és `/jysk-riport.data.json` route forrása most már repo-tracked lane-ben is helyreáll.
- A `wp-content/mu-plugins/impactshop-ngo-guides.php` additive route map bővítést kapott a JYSK riporthoz.
- A dedikált `jysk-riport.html` és `jysk-riport.data.json` asset bekerült a repo forrásfái közé, így a live restore többé nem csak szerverállapotként létezik.

## 2026-04-01 16:40:00 CET - JYSK canonical inventory lock

- A JYSK riport forrásfájljai most már explicit guard inventory és digest manifest alatt is állnak.
- A `docs/impactshop-guard-config.json` és `docs/impactshop-guard-hashes.json` külön rögzíti a `impactshop-ngo-guides.php`, `jysk-riport.html` és `jysk-riport.data.json` kanonikus source állapotát.

## 2026-04-01 16:47:00 CET - JYSK review thread cleanup

- A `jysk-riport.data.json` legacy dátumhibái normalizálva lettek: Debrecen `vote_period_start` ISO formátumot kapott, Kispest/Szarvas bizonytalan végei `null` értékre kerültek.
- A `jysk-riport.html` toolbar gombja most az aktuális scroll viselkedést nevezi meg, és a riport elsődleges adatforrása a route-on kiszolgált JSON lett; az embedded snapshot csak fail-safe fallback marad.

## 2026-04-10 10:59:00 CET - ngo-guides v1.1.4 — befektetoknek 404 + lang fix

- `impactshop-ngo-guides.php` v1.1.4: `befektetoknek` bejegyzés visszakerült `page_meta()`-ba, `resolve_file($lang)` bekötve `template_redirect()`-be.
- Rollback: `backups/ngo-guides-fix-20260410/rollback.sh` (MD5-el ellenőrzött v1.1.3 backup, git commit `9b7ab942`).

## 2026-04-29T12:48:00+0200 - JVK public embed status label removed

- `impactshop-event-auction-widget-jovonkvize-1.0.0.js`: write-enabled alapallapotban a fo status-mezo ures marad, nem jelenik meg technikai scaffold copy.
- A write-disabled es error uzenetek megmaradtak.
- `impactshop-event-auction-widget.php` asset verzio `0.2.4`, frontend widget belso verzio `1.0.4` a biztos cache-bustinghoz.

## 2026-04-29T15:12:00+0200 - JVK public detail drawer copy normalized

- A publikus detail drawer es licitform technikai szovegezese felhasznaloi nyelvre cserelve.
- Eltunt a scaffold/lane/admin UI jellegu copy a drawer alol; csak ertelmezheto felhasznaloi instrukcio maradt.
- `impactshop-event-auction-widget.php` asset verzio `0.2.5`, frontend widget belso verzio `1.0.5`.

## 2026-04-29 jvk-auction
- JS v1.0.6: scroll restore, HU copy, no scaffold
- PHP v0.2.6: wp_mail notify bid + admin_close
- Deployed: app.sharity.hu/wp-content/mu-plugins

## 2026-04-30 jvk-auction v0.3.6
- PHP v0.3.6: auction_end_time=2026-05-16T20:00:00Z (máj.16 22:00 Budapest), snipe_window/extend=120s, lot_end_time helper, snipe extension place_bid-ben
- JS v0.3.6: countdown CSS + formatCountdown() + card/detail countdown, setInterval 1s tick, bidder localStorage autofill
- Deployed: app.sharity.hu/wp-content/mu-plugins

## 2026-04-30 jvk-auction v0.3.6
- PHP v0.3.6: auction_end_time=2026-05-16T20:00:00Z (máj.16 22:00 Budapest), snipe_window/extend=120s, lot_end_time helper, snipe extension place_bid-ben
- JS v0.3.6: countdown CSS + formatCountdown() + card/detail countdown, setInterval 1s tick, bidder localStorage autofill
- Deployed: app.sharity.hu/wp-content/mu-plugins

## 2026-05-04 jvk-auction v0.3.7
- Képlevágás fix: object-fit:cover → object-fit:contain, letterboxing card + detail
- Kártya + detail panel kép: teljes mű látható, arány megtartva
- Deployed: app.sharity.hu ✅

---
_Auto update: $(date '+%Y-%m-%d %H:%M:%S')_
### v0.3.8 deploy summary
- JS: height growth fix (min-height:100% removed), country code selector, WIDGET_VERSION=1.0.7
- PHP: Vonage unicode type fix, version=0.3.8
- Prod deploy: OK (wp-cli verified 0.3.8)
## JVK Dashboard Merge (2026-05-04)

## 2026-05-05 impact-community source guard hardening
- `scripts/guarded-remote-write.sh`: canonical path + sha256 ellenorzes kotelezo `impact-community.php` deploy elott.
- `scripts/impact-intl-runtime-backup.sh` es `scripts/impact-intl-runtime-rollback.sh`: duplikalt legacy blokk eltavolitva, egyetlen kanonikus implementacio maradt.
- Community backup/rollback csak explicit `--include-community --ack-include-community` eseten engedelyezett.
- CI kiegeszites: `.github/workflows/impact-community-source-guard.yml` ellenorzi a canonical guard jelenletet.

## 2026-05-05 impact-community source guard hardening (review-fix)
- `guarded-remote-write.sh`: anti-shrink override aktiv (`--allow-shrink`) es hash-elsodleges canonical check, worktree-kompatibilis elfogadassal.
- `impact-intl-runtime-rollback.sh`: partial rollback mar nem jelent sikeres lefutast, hibara fail-closed kimenet van.
- `impact-community-source-guard.yml`: marker-grep helyett futtathato parity check lepesei validaljak a guard viselkedest.

## 2026-05-05 impact-community source guard hardening (workflow stabilizalas)
- `impact-community-source-guard.yml` workflow job timeout 5 percre allitva.
- A non-canonical rejection parity lepesben `REMOTE_LINES_OVERRIDE=1` hasznalat, hogy semmilyen halozati mellekhatas ne akassza meg a CI futast.

## 2026-05-05 impact-community source guard hardening (workflow parity path fix)
- A non-canonical CI teszt mutalt fajlja most `impact-community.php` neven jon letre ideiglenes mappaban, hogy biztosan a guard relevans kodag fusson.

## 2026-05-05 Impi Step 3: Admin Capability Gating (impi-step3-scoped branch)

- Extracted clean Impi Step 3 implementation to separate scoped branch
- Feature: authoritative capability gating for ask/image_generation/marketing_copy modes
- Fail-closed defaults: all modes disabled until capability endpoint responds
- Security audit: sanitized reason messages, nonce coherence fixed (GET now sends nonce)
- Protected-Change: impi-step3-admin-hardening
- Status: ✅ Guard-compliant, ready for independent PR review

---
_Auto update: 2026-05-05 14:16:13_

### Health check summary

```
staging: HTTP 200 (5499 ms, ok) – https://sharity.hu/impactshop-staging (redirected_to:app.sharity.hu)
production: HTTP 200 (1129 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2026-03-03.md

---
_Auto update: 2026-05-05 20:03:17_

### Health check summary

```
staging: HTTP 200 (7503 ms, ok) – https://sharity.hu/impactshop-staging (redirected_to:app.sharity.hu)
production: HTTP 200 (1468 ms, ok) – https://app.sharity.hu/wp-json/
```

**Baseline referencia:** impactshop-baseline-2026-03-03.md
## 2026-08-19T12:45:00+0200 - Sharity affiliate runtime WordPress checkpoint
- Elkészült a default-off, Dognet-only Shopping Assistant attribúciós runtime; a providerhez csak opaque `sat1` kerül, a raw pseudo és URL nem.
- A helyi mapping a kiválasztott NGO-t és HMAC-pseudonimizált subjectet tartja meg, 15 perces intent TTL-lel és 45 napos retentionnel.
- A védett `/go` tulajdonos csak exact `src=shopping-assistant` esetén delegál; legacy `/go`, `/go-deal`, CJ, pont, reward, vote és settlement truth változatlan.
- Daily retention cron: `impactshop_sharity_affiliate_retention_cleanup`; a központi watchdog bekötése külön ai-agent checkpointban következik.
- A digest guard 27 korábbi stale hashát csak olyan fájlokra frissítette, amelyek a worktree `HEAD`-hez képest tiszták voltak; a teljes 145 fájlos manifest és mindkét checksum zöld.
- Állapot: lokális checkpoint készül, feature option `0`, deploy és production aktiválás még nem történt.

## 2026-08-19T17:50:00+0200 - Deploy bastion manifest guard

- A merged-main deploy dry-runban feltárt undefined
  `verify_remote_bastion_manifest` kontroll helyreállt fail-closed remote
  manifest validációval.
- `DRY_RUN=1` alatt minden remote `mkdir`, WP cache/cron/rewrite maintenance és
  post-deploy smoke tiltott; az rsync mindig `-n --itemize-changes`.
- Mockolt SSH/rsync integrációs teszt védi az érvényes, hibás manifest és hiányzó
  remote cél ágakat.
- Production állapot változatlan: affiliate runtime fájl nincs fenn, option
  unset, cleanup cron nincs. Staging preflight 404; két identity-panel fájl
  live-main driftje miatt real deploy külön döntésig blokkolt.

## 2026-08-20T09:50:28+0200 - Exact-file production release preview

- A read-only production inventory 20 live-only MU-plugin bejegyzést és hat
  közös, SHA-256 szerint eltérő fájlt bizonyított; a széles `--delete` mapping
  ezért nem tekinthető biztonságos production deploynak.
- A mapping profil most hálózat előtt validálódik. Unsafe/space/traversal,
  duplicate, symlink és repo-rooton kívüli source fail-closed leáll.
- `IMPACTSHOP_DEPLOY_FILE` mellett a production dry-run pontosan egy fájlt old
  fel, minden `--delete*` opciót eltávolít és `--checksum` módot kényszerít.
- Minden valós production írás továbbra is tiltott, mert a távoli backup/CAS és
  futtatható rollback még nincs implementálva. Runtime/option/cron nem változott.
- Cronos/watchdog változás nem kell: ez deploy-control, nem scheduler; az
  affiliate watchdog csak a későbbi aktiválási lane-ben válik operatívvá.

## 2026-08-20T10:12:00+0200 - Exact-file CI shell parity

- A deploy dry-run számlálói Bash 3/5 kompatibilis `+= 1` műveletet használnak.
- A változás kizárólag a fail-closed CI futás hordozhatóságát javítja; deploy
  scope, távoli cél, attribúció és production állapot nem változott.
- A két deploy-control regressziós teszt és a kapcsolódó affiliate bástyateszt
  helyben zöld; valós production írás továbbra is tiltott.

## 2026-08-20T10:14:00+0200 - Rollback handover truth guard

- A merged-main production dry-run pontosan egy affiliate fájlt jelzett,
  no-delete/checksum/no-write módban; runtime, option és cron továbbra sincs.
- A guard többé nem ajánl nem létező rollback parancsot. Futtatható script
  hiányában lokális source snapshotot és fennmaradó production write blokkot ír.
- Új determinisztikus CI-teszt védi az executable gate és a fallback sorrendjét.

## 2026-08-20T10:22:00+0200 - Guard checksum portability closure

- A merged-main dry-run lokálisan frissítette a megváltozott guard wrapper
  digestjét, és feltárta az abszolút worktree-pathot író checksum hibát.
- A checksum writer repository-relatív címkét használ; a teszt tiltja a nyers
  `hash_path` kiírást, a manifest pedig a végleges wrapper digestjére van zárva.
- Production állapot változatlan: runtime absent, option unset, cron absent.

## 2026-08-20T16:40:00+0200 - Exact production release admission

- Elkészült a privát remote release manifest + lock + verified backup/absent
  truth + apply CAS + staged PHP lint + atomikus replace + SHA/`0444` relock.
- A `bin/impactshop-guard-rollback.sh` inspect-only alapállásból explicit
  production/apply/release-ID/deployed-SHA kapuval állít vissza vagy töröl.
- A broad production mapping és minden `--delete*` exact írás továbbra is
  tiltott; real exact release csak clean named `main` vagy detached
  `HEAD == origin/main`, kanonikus profil és explicit expected-before state
  mellett lehetséges.
- A temporary-filesystem és fake SSH/rsync/git regressziók zöldek. Production
  még nem változott: runtime absent, option unset, cron/watchdog absent.
- A dirty primary `main` érintése nélkül tiszta detached release worktree is
  használható, de csak exact opt-in és `HEAD == origin/main` esetén; ezt külön
  feature/stale negatív preflight teszt védi.
- A merged-main dry-run egyetlen no-delete runtime additiont mutatott, de a
  wrapper régi snapshot-ID rollback szövegét feltárta. Éles írás nem indult; a
  follow-up a local source snapshotot elválasztja a release-ID + SHA runtime
  rollbacktól.

## 2026-08-20T11:50:00+0200 - Exact release Python 3.6 compatibility closure

- Az első explicit exact apply minden preflight után, de még a remote `prepare`
  végrehajtása előtt Python syntax hibával leállt; a VPS csak Python 3.6.8-at ad.
- Read-only ellenőrzés szerint a runtime target és a sikertelen release-ID
  könyvtára absent maradt, ezért részleges éles állapot és rollback-igény nincs.
- A remote engine állapotgépe változatlan, de a typing és subprocess szintaxis
  Python 3.6-kompatibilis; külön AST/API regressziós guard védi ezt a minimumot.
- Feature option továbbra is unset/`0`, cleanup cron nincs. Default-off exact
  release után sem kell Cronos; watchdog csak az aktiválási csomagban kötelező.

## 2026-08-20T12:05:00+0200 - Max-protected parent release closure

- A Python 3.6-kompatibilis retry már létrehozta a `prepared` manifestet és
  ellenőrizte a payloadot, de a target létrehozása `PermissionError` miatt
  fail-closed maradt: a production `mu-plugins` kanonikusan `0555`.
- Read-only inspect: target absent, release prepared, payload hash és PHP lint
  helyes; partial runtime, option-, cron- vagy üzleti truth írás nincs.
- Az exact engine a lock alatt csak az owner-owned, inode-azonos parent owner-write
  bitjét nyitja, majd deploy/rollback/hiba után az eredeti `0555` módot ellenőrzi.
- A max-protected parent round trip és race teszt zöld; broad/recursive chmod,
  sibling write, aktiválás és watchdog továbbra sincs ebben a csomagban.

## 2026-08-20T12:20:00+0200 - Affiliate runtime production default-off

- Exact release `20260820T094433Z-87fe5d3ac628-98513d73` deployed a merged
  `856a5fa1` állapotból; live SHA `4347dded...e0ef4859`, target `0444`.
- A `mu-plugins` parent visszazárt `0555`; release inspect és PHP lint zöld.
- Aktiválási option hiányzik, cleanup cron és affiliate tábla absent, tehát az
  új runtime jelen van, de nem ír attribúciós vagy pénzügyi állapotot.
- Öt Impact baseline endpoint zöld, a Vásárlási Segéd HTTP 200. Cronos/watchdog
  az aktiválás előtt, külön ai-agent csomagban kötelező.
# 2026-08-21 — Hatás Körök Human Touch smoke ops checkpoint

- A route cutover post-deploy smoke szerződése exact `302` + query-mentes
  `Location`, Human Touch target marker, dev-route és community read API check.
- A kapcsolódó statikus és PHP-stub futásidejű teszt 6/6 zöld; write smoke nincs.
# 2026-08-21 — Hatás Körök post-deploy smoke portability fix

- A production Human Touch redirect él és exact; a post-deploy smoke parser
  macOS/BSD awk kompatibilitási javítást kapott (`tolower`, nincs `IGNORECASE`).
- A változás kizárólag ellenőrző tooling, éles runtime-ot nem módosít.

## 2026-08-21T16:55:00+0200 - Sharity Shopping opaque sat1 cutover live

- Production boot legacy SHA `cccc3f41...13b56f` exact Git blobhoz lett kötve;
  a merged main eltérés kizárólag a review-zott Shopping affiliate adapter.
- Exact release `20260821T145250Z-1716e6fc2761-6892b1d3` egyetlen
  `impactshop-boot.php` fájlt telepített. Live SHA
  `e05a538f...45ba06`, target `0444`, parent `0555`, PHP lint és rollback
  inspect zöld.
- A central watchdog a megőrzött régi branch után új exact-main runtime branchre
  állt; rollback crontab: `central-watchdog.20260821T144748Z.crontab`.
- Release előtti és utáni affiliate postactivation admission `ADMITTED`; az
  option/schema/table/egyetlen cleanup hook/next run és freshness zöld. A global
  watchdog idegen hibák miatt `FAIL`, de affiliate-retention blocker nincs.
- Öt Impact endpoint és a Vásárlási Segéd HTTP 200. Emberi Árukereső
  `sat1 -> last_click_data1` canary még kötelező; automatizált kattintás nem volt.
## 2026-08-23T00:00:00+0200 - Impi source-owner context projection

- New additive MU-plugin is default-off and read-only. It serves a bounded,
  redacted circle context only to the Impi shadow service with a dedicated
  credential and request ID; browser/session routes and all protected identity,
  points, votes, rewards, money, Offerwall and VB2026 writers are unchanged.
- Tests and maximum-bastion audit are green. No secret or numeric pilot ID is
  stored in the repository. No cron/watchdog/provider/deploy mutation occurred.
- Next gate is a separate operator-controlled shadow activation after exact
  source SHA review and runtime-only key provisioning; 30-day retention is the
  maximum deletion window, not a waiting period.

## 2026-08-23 — VB2026 autobanner SAT1 source admission

- Exact new source vb2026-autobanner uses the existing opaque affiliate runtime.
- Shopping Assistant remains unchanged; unknown and broad sources are rejected.
- Dognet Data 1 receives SAT1 and raw pseudo/data5 remain suppressed.
- Local intent retains selected NGO and exact source placement for later
  commission correlation, without asserting purchase or settlement.
- Schema, retention cron, provider gate and all economic writers are unchanged.
- Protected runtime, boot, bastion and tamper tests pass in the clean VPS worktree.

## 2026-08-23 — VB2026 autobanner affiliate production closure

- PR #182 merged at `4ab348480ead`; exact-file releases deployed the affiliate
  runtime and boot adapter with private backup, double CAS, PHP lint and atomic
  replacement.
- Live SHA values are `0c49b041...d92e4` and `845d284f...c67a`; both targets
  are `0444`, their parent is `0555`, and both rollback inspections are green.
- The existing retention watchdog tuple is unchanged and postactivation
  admission is `ADMITTED`; no new cron, schema or economic writer was added.
- Human affiliate canary remains pending; no automated click occurred.
# 2026-09-02 DEV governance adapter: source-ready, not merged or deployed.
# 2026-09-02 DEV governance: repo-adapted and docsync-registered; not a product deploy.
## 2026-09-03 — Impact Shopping UNice CJ adapter

- Source-ready in a clean exact-main worktree; not yet deployed or enabled.
- Affiliate schema v2 adds only handoff/snapshot/disclosure metadata.
- CJ intent requires canonical active session plus service auth; browser sees
  only a random one-use Sharity handoff.
- CJ redirect is pinned to `101302202-15487360` with sole opaque `sid`.
- Dognet, VB2026, identity writers, rewards, financial ledger and NGO Card are
  unchanged. Existing retention cron is reused.
