## 2026-06-17T12:20:00+0200 - env/auth/runtime local adapter added
- Uj helyi adapter anchor keszult: `docs/impactshop-env-auth-runtime-guard-adapter-2026-06-17.md`.
- A dokumentum konkretan a protected inventory, a deploy env parok es a profile-return runtime lane-ek helyi guard szerzodeset fogja ossze.
- A local governance system plan mar erre is hivatkozik, igy az env/auth/runtime drift kulon repo-helyi entrypointot kapott.

## 2026-06-16T10:20:00+0200 - local governance system plan hub added
- Uj rovid, repo-helyi governance entrypoint keszult: `docs/impactshop-governance-system-plan-2026-06-16.md`.
- A dokumentum nem uj policy-t vezet be, hanem a mar ervenyes helyi anchorokat rendezi egybe: `AGENTS.md`, `docs/pr-policy.md`, `PR-EXIT-CHECKLIST.md`, `system-status-snapshot.md`, `notes.md`, `docs/bastion-guard-status.md`.
- Bekotes megtortent a gyors helyi belpeshez: `AGENTS.md` canonical sources lista, `README.md` start-here blokk es a `docs/ai-fejlesztes-veglegesitett-masterterv-2026-03-04.md` kanonikus tervreferencia.

## 2026-06-16T10:55:00+0200 - governance hub coherence audit added
- Uj rovid audit rogzitve: `docs/impactshop-governance-hub-coherence-audit-2026-06-16.md`.
- Az audit explicit modon visszaellenorzi, hogy a helyi governance-hub csak valos anchorokra mutat, docs-only maradt, es nem lazitotta a protected/perimeter szabalyokat.
- A masterplan referenciaretege frissult, hogy a hub mellett az audit is kanonikus helyi referenciakent elerheto legyen.

## 2026-06-14T08:20:00+0000 - VB2026 profile-return session carry canonicalized
- A Sharity source-side profile-return lane kulon account-top es restore-fragment deeplink contractot kapott: `Sharity profil megnyitasa` -> `/profil/?bridge_target=account#impactshop-account-top`, `Ez nem az en fiokom` -> `/profil/?bridge_target=restore#impactshop-restore-title`.
- A sikeres save/restore mar nem kozvetlen `vb-prod` redirecttel zar, hanem a FactLens `GET /api/vb2026/profile-return/complete` route-jara kuld vissza alairt payloadot, hogy a target host `__Host-factlens_vb_session` cookie-t issue-zon a vegso visszateres elott.
- A source-side protected runtime coupling explicit lett: `impactshop-identity-panel.php` es `impactshop-identity-panel.js` egy kozos VB2026 profile-return lane-kent kezelendo.

## 2026-06-03T08:00:00+0200 - vote-purchase JS null-safe currency fallback fix
- `impactshop-vote-purchase.js`: `getEffectiveCurrency()` fallback hozzáadva — `currencySelect` null esetén `preferredCurrency`/`defaultCurrency` sorrendben esik vissza
- Root cause: `currencySelect.value` TypeError → Adományozom gomb néma
- Fix: 3 commit (`c073391f`, `abcee270`, `649732f0`) — null-safe init + coherence hardening + preferredCurrency QA fix

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
## 2026-05-15T16:30:00Z - JVK Tarcsi lot dimensions follow-up
- `wp-content/mu-plugins/impactshop-event-auction-widget.php` lot 3 (`tarcsi-daniel-part-iii`) metadata corrected to final delivered artwork data.
- Updated fields only: `description_short`, `dimensions`, `medium`.
- Follow-up required because PR #140 completed canonical flow but did not alter the canonical Tarcsi lines in `origin/main`; PR #141 carries the effective runtime correction.
## 2026-05-16T16:20:00Z - JVK auction 11-lot emergency restore
- A kanonikus `origin/main` és az élő JVK public payload 9 tételes regresszióban volt.
- A dedikált aukciós memória és a `0734ba80` history alapján visszaállítva a lot 10 (`Balla Gemma`) és lot 11 (`Kocsis Katica / Weiler Péter`) tételek a kanonikus runtime source-ba.
- A helyreállító change set a `wp-content/mu-plugins/impactshop-event-auction-widget.php` fájlra koncentrál, a 11 tételes állapotot állítja vissza.
2026-06-16T18:20:00Z | feat(dev-guard/local-governance-sync-enforcement): a local pre-push audit most mar fail-closed modon megkoveteli, hogy a guard/governance/policy lane valtozasai a helyi governance system plan frissitesevel egyutt menjenek ki. Erintett fajlok: `scripts/safe-repo-audit.sh`, `scripts/git-health-check.sh`, `AGENTS.md`, `docs/impactshop-governance-system-plan-2026-06-16.md`.

## 2026-06-23T09:55:00+0200 - VB2026 NGO catalog and selection plan canonicalized
- New canonical planning document added: `docs/VB2026-SHARITY-NGO-CATALOG-AND-SELECTION-PLAN-2026-06-23.md`.
- Scope:
  - full Sharity NGO catalog model
  - VB2026 featured Top 10 campaign layer
  - user-level NGO selection truth
- `vb-prod` to Sharity domain connection model
- No runtime or deploy change in this slice; documentation and product architecture only.

## 2026-06-23T10:35:00+0200 - VB2026 NGO catalog plan raised to implementation-ready baseline
- The canonical NGO plan now includes:
  - implementation-ready route and endpoint contracts
  - storage/migration design
  - source field mapping from the Sharity CSV export
  - selection intent and selection state machine rules
  - fallback/cache/publish-safe rules
  - formal audit and QA findings with corrections
- No runtime change in this slice; documentation baseline only.

## 2026-06-23T11:05:00+0200 - VB2026 NGO catalog plan fresh QA pass completed
- Additional documentation QA corrections applied:
  - API `county` vs UI `Megye` naming contract explicitly fixed
  - open slug decision narrowed to source-side canonical slug availability only
- No runtime change in this slice; planning baseline tightened further.

## 2026-06-23T11:20:00+0200 - VB2026 NGO catalog plan QA tightened phase and fallback contract
- Additional plan-level QA corrections applied:
  - MVP launch vs Phase 1/2 relationship explicitly fixed
  - API `ngo_id` now explicitly tied to canonical `sharity_ngo_id`
  - snapshot optionality separated from mandatory last-good fallback
- No runtime change in this slice; documentation baseline only.

## 2026-06-23T11:35:00+0200 - VB2026 NGO catalog plan QA tightened intent and campaign publish rules
- Additional plan-level QA corrections applied:
  - pre-auth selection intent storage model added
  - featured publish gate tied to active/public-listable/active-campaign records
  - inactive campaign-state cannot remain selectable in the given campaign scope
  - publish-safe ingest now explicitly protects against suspiciously low active-record publish
- No runtime change in this slice; documentation baseline only.

## 2026-06-23T11:50:00+0200 - VB2026 NGO catalog plan QA hardened lock, audit and token rules
- Additional plan-level QA corrections applied:
  - `selection_lock_state` enum and overwrite rules explicitly fixed
  - dedicated NGO selection audit-log storage added
  - pre-auth selection intent token storage tightened to hashed-at-rest model
  - public catalog default active-only behavior explicitly fixed
  - `allow_public_listing` separated from prior user selection validity
- No runtime change in this slice; documentation baseline only.

## 2026-06-23T12:10:00+0200 - VB2026 NGO catalog plan aligned to canonical slug and widget roadmap
- Plan baseline updated:
  - canonical NGO slug now explicitly follows the existing Impact Shop / NGO Card slug truth
  - raw CSV media links are no longer treated as final public image truth
  - widget/embed and VB2026 donation-routing phase added to the roadmap
- No runtime change in this slice; documentation baseline only.

## 2026-06-23T12:18:00+0200 - VB2026 NGO catalog plan wired to canonical NGO share view
- Plan baseline updated:
  - NGO cards and planned widget flow now include a dedicated Share action
  - canonical target is the existing NGO share route: `/ngo/{slug}/share/`
- No runtime change in this slice; documentation baseline only.

## 2026-06-23T12:35:00+0200 - VB2026 NGO catalog Phase 1 implementation pack added
- New implementation packet added:
  - `docs/VB2026-SHARITY-NGO-CATALOG-PHASE1-IMPLEMENTATION-PACK-2026-06-23.md`
- Scope:
  - Repo A source-side NGO catalog and selection lane
  - Repo B `vb-prod` compact NGO bridge
- No runtime change in this slice; implementation planning baseline only.

## 2026-06-23T12:48:00+0200 - VB2026 NGO catalog Phase 1 packet QA tightened cross-repo gates
- Additional plan-level QA corrections applied:
  - source truth decisions elevated to hard blocker gate
  - `my-ngo-selection` minimum state contract made explicit
  - target-side URL building forbidden in favor of source-provided URL truth
  - cross-domain pseudo/session consistency added to acceptance
- No runtime change in this slice; implementation planning baseline only.

## 2026-06-23T14:20:00+0200 - VB2026 NGO catalog Phase 1 source runtime scaffold implemented
- New source-side MU-plugin added:
  - `wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php`
- Current runtime scope:
  - catalog sync from the Sharity NGO CSV
  - canonical slug/share/details/media merge from the NGO-card lane
  - `/szervezetek/` public catalog page
  - featured Top 10 read lane
  - own NGO selection read lane
  - direct selection write lane
  - pre-auth selection-intent storage and completion lane
- Validation on this slice:
  - `php -l wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php` PASS
  - `git diff --check` PASS
- This is not yet a live deploy claim; current status is implementation-in-worktree with source/runtime contract documented.

## 2026-06-23T14:42:00+0200 - VB2026 NGO Phase I source/target audit hardening applied
- Public catalog publish contract tightened:
  - `ngo-catalog` now enforces `allow_public_listing = 1`
  - `ngo-catalog` now enforces `campaign_state = 'active'`
- Sync contract tightened:
  - suspiciously low active-row CSV input no longer overwrites the source catalog truth
- Selection contract tightened:
  - `selection-intent` creation now blocks non-selectable NGOs the same way as final `select-ngo`
- UX/read-state tightened:
  - own NGO banner now distinguishes auth-required vs unavailable vs empty-selection states
[2026-06-23T15:40:00Z] | docs(doc-sync/local-canonical-map): az `impactshop-notes` repo megkapta a sajat contract-kompatibilis local canonical doc-sync mapjet (`docs/impactshop-notes-doc-sync-map-2026-06-23.md`). A helyi map most mar egy helyen oldja fel a governance, env/auth/runtime, protected bridge/profile-return, VB2026 NGO catalog es core public baseline doku-truthokat, es a `README.md`, `docs/README.md`, valamint a helyi governance hub felol is elerheto.
- A `docs/impactshop-guard-config.json` es a `docs/impactshop-guard-config.sha256` paritasa helyreallt; a canonical guard deploy mar nem checksum-drift miatt all meg, hanem a kovetkezo valos runtime/deploy gate-re tud tovabblepni.

## 2026-06-23T18:20:00+0200 - VB2026 NGO source lane prod callback hotfix applied
- Prod source-lane outage oka: a `wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php` `template_redirect` hookja elirt callbacket hasznalt.
- A hook most mar a tenyleges `impactshop_vb2026_catalog_template_redirect` handlert hivja.
- Ennek hatasara a prod Sharity source lane ujra helyesen szolgaltatja:
  - `GET /wp-json/impact/v1/ngo-catalog`
  - `GET /wp-json/impact/v1/vb2026/featured-ngos?campaign=vb2026`
  - `GET /szervezetek/?campaign=vb2026`
- Ez a statusz mar tenyleges prod-helyreallitas, nem csak worktree-implementacios allapot.
- [2026-06-23T21:05:00Z] NGO return-flow closure: a source oldali `selection_urls` es a katalogus/profil JS mar explicit `return_to=vb-prod` celt es `redirect_url` alapu visszalepest hasznal. A `selection-intent` completion vegre valos browser-flowban is le tud zarulni, nem csak tavolrol hivhato endpoint marad.
## 2026-06-29T17:25:00+0200 - VB2026 NGO catalog full-filter hydration truth applied
- A `szervezetek/` source-oldali katalogus mar nem csak az elso `per_page=48` oldalbol epiti a szuro-opciokat es a latszo kartyalistat.
- A jelenlegi JS a teljes paginalt aktiv katalogust tolti be, igy a `Minden település` dropdown nem hamis, reszhalmazos varoslistat mutat.
- Ugyanebben a korben rogzitve lett a display truth is: a `Top 10` target/shell oldalon nem csonkithato tovabb 5 elemre.
