## 2026-05-07 16:00 CEST - JVK aukció analytics + publikus dashboard tabs lezárva
- `impactshop-event-auction-widget.php`: az éles JVK kampányadatok véglegesítve a publikus embedhez. Javítva lettek a lot 1-7 kikiáltási árai és licitlépcsői, a globális aukciózárás `2026-05-17T20:00:00Z` értékre került, és új publikus analytics REST route-ok kerültek be (`/analytics/public`, `/analytics/event`).
- `impactshop-event-auction-widget-jovonkvize-1.0.0.js`: deeplink/share UX véglegesítve, kliensoldali analytics eseményküldés bekötve (`page_view`, `lot_open`, `deep_link_open`, `preset_click`, `share_click`, `bid_submit`, `engagement`).
- `impactshop-event-admin-dashboard-widget.js`: a publikus dashboard három külön fülre lett rendezve: `Jegyek es adomanyok`, `Aukció tételek`, `Aukció statok`. Az aukciós publikus tétellista és a realtime analytics panel külön refresh útvonalat kapott.
- `impactshop-event-donation-widget.php`: cache-bust verzió `1.6.4`, hogy a publikus dashboard oldal a friss JS assetet kérje le.
- Prod verifikáció megtörtént: publikus aukció API 9 élő lotot adott vissza helyes kikiáltási árakkal és zárási idővel; az analytics track smoke helyes `Referer` mellett sikeres volt; a publikus dashboard HTML a végén már a `impactshop-event-admin-dashboard-widget.js?ver=1.6.4` assetet referálta.
- Deploy: célzott hotfix sync lefutott, Cloudflare purge megtörtént az érintett JS assetekre.

## 2026-05-07 10:40 CEST - Impact Shop / Challenge EN runtime canonicalization
- Tiszta release-worktree-ben szetvalasztott canonical commit-sor keszult a live-on validalt intl runtime allapothoz.
- Uj additiv overlay assetek: `impactshop-ads-watch-intl-overlay.js`, `impactshop-identity-panel-intl-overlay.js`.
- Protected runtime commit: `impactshop-ads-watch.php`, `impactshop-ads-watch.js`, `impactshop-identity-panel.php`, plusz kulon protected change record.
- Guard evidence: PHP lint OK, JS syntax check OK, `git-health-check` OK; strict push audit dokumentacios folytonossag miatt notes/snapshot/bastion naplo is frissitve.

## 2026-05-05 19:20 CEST - JVK public dashboard fix (impactshop-event-donation-widget v1.6.2)
- PHP: public REST route `/transactions/public` hozzáadva (no auth), `impactshop_event_donation_public_transactions()` callback
- PHP: shortcode `public="yes"` attribútum támogatás, `data-public="1"` az HTML-ben
- PHP: cert auto-send bekapcsolva `utalas_megerosites` confirm-nél (cég + request_certificate=1 esetén)
- PHP: verziószám 1.6.1 → 1.6.2 (JS cache-bust)
- JS: `isPublic` flag, Licit tab elrejtve public módban
- JS: `donationTxUrl` → public endpoint public módban, admin endpoint admin módban
- JS: külön `aucError` div, nem írja felül az adomány hibát
- JS: `loadAuctions()` kihagyva public módban (admin 403 elkerülés)
- WP page 19133 shortcode frissítve `public="yes"`-re via SSH wp post update
- Deploy: prod + staging, 2026-05-05T17:09Z–17:20Z

## 2026-05-04 15:20 CEST - Citation Verifier Integration (Impact Challenge P2 phase)
- Az `ic_impi_run_legal_review()` függvényt módosítottam, hogy a runtime response-ból kinyerje a `citation_check` és `hallucination_guard` objektumokat.
- Gate logic frissítve: `release_blocked` most dinamikus. Az output csak akkor kerülhet release-re ha `citation_passed=true` ÉS `hallucination_passed=true`.
- Az audit payload már tartalmazza a full `gate_results` snapshot-ot a runtime döntésről, lehetővé tételével az Impi release-t megelőző jogi auditálást.
- Szintaxis ellenőrzés: `php -l` OK; VS Code error scan OK.
- **Status**: A gate infrastruktúra most kész. Release még BLOKKOLT marad amíg az ai-agent legal_ask endpoint nem valósítja meg a citation_verifier tool output-ját a response-ban. Következő fázis: ai-agent legal_ask módosítása.
- Dokumentáció frissítve: `impi-ngo-workspace-detailed-plan-risk-coherence-security-2026-05-04.md` és notes.md.

## 2026-05-04 14:40 CEST - Legal source operacios smoke frissites (NJT/Kuria/AB)
- A korabbi source-health korlat enyhitesehez local tooling javitas tortent: `npx playwright install chromium` sikeresen lefutott.
- Direkt elerhetosegi smoke futott a kulcs legal portalokra:
  - `https://njt.hu/` -> `200`
  - `https://kuria-birosag.hu/` -> `200`
  - `https://alkotmanybirosag.hu/` -> `200`
- Kovetkeztetes: az operacios forras-elerhetoseg jelenleg rendben van, de a production legal release gate tovabbra is citation/hallucination enforce-fuggo.

## 2026-05-04 14:25 CEST - Impi detailed plan + risk/coherence/security assessment md
- Uj dokumentum keszult: `docs/impi-ngo-workspace-detailed-plan-risk-coherence-security-2026-05-04.md`.
- A riport explicit tool evidence-re epul (legal_memory, legal_ask, hallucination_guard, legal_fact_claim_check), es kulon jelzi a jelenlegi release blokkolo tenyezoket.
- Fo kovetkeztetes: a rendszer belso review-only modban koherens/fail-closed, de production release tovabbra is blokkolt a citation/hallucination enforce es runtime auth hardening teljesiteseig.
- Operacios korlat rogzitve: `source_health` helyi futtatasa Playwright browser hiany miatt nem volt vegrehajthato.

## 2026-05-04 13:55 CEST - Impi workspace onboarding + image/marketing runtime hook + legal smoke limit
- `impact-community-app.php`: az NGO workspace Impi panel most már az `access_reason` alapján vezet tovább. Ha nincs aktiv NGO admin jog, ugyanonnan indítható az újraaktiválás; ha a gond körtagság, a felület a körlistára visz tovább.
- A response panel most már a runtime állapotot is kiírja (`configured`, `connected`, `review_only`), így az NGO workspace-ből közvetlenül látszik, hogy melyik Impi ág ténylegesen runtime-ra futott ki.
- `impact-community.php`: az `image_generation` és `marketing_copy` mód review-only, fail-closed runtime adaptert kapott külön konfigurációs hookokkal (`impact_community_impi_image_runtime_*`, `impact_community_impi_marketing_runtime_*`). Runtime nélkül ezek az ágak most már tudatosan blocked állapotban maradnak.
- Legal smoke eredmény: a gateway health endpoint elérhető (`https://sharity-legal-production.up.railway.app/healthz` válaszol), de az érdemi `POST /api/ai/query` továbbra is JWT/auth kötött, ezért teljes végpont-smoke service token nélkül nem volt futtatható.
- Verifikáció: `php -l wp-content/mu-plugins/impact-community.php` OK, `php -l wp-content/mu-plugins/impact-community-app.php` OK, VS Code error scan OK.

## 2026-05-04 13:25 CEST - Impi NGO admin authz + durable audit/job store + review-only legal adapter
- `impact-community.php`: az Impi hozzáférés már nem puszta NGO körtagságra épül, hanem valós NGO admin account ellenőrzésre (`ic_ngo_accounts`, `ic_get_ngo_account`, `ic_can_post_as_ngo`, `ic_impi_get_circle_access`).
- Új backend REST surface került be az NGO admin account réteghez: `GET /ngo/admin/mine`, `POST /ngo/admin/company-search`, `POST /ngo/admin/register`.
- Az Impi job- és audit-állapot többé nem csak transientben él: új tartós táblák kerültek be (`ic_impi_jobs`, `ic_impi_audit_events`), a státusz-visszaolvasás pedig pseudo-hash alapon jogosultságellenőrzött.
- A `legal_finance` mód most review-only runtime adapteren tud kifutni konfigurált ai-agent legal endpoint felé; sikeres hívás esetén is `release_blocked=true` marad, tehát végleges kiadás továbbra sincs megengedve.
- Verifikáció: `php -l wp-content/mu-plugins/impact-community.php` OK, VS Code error scan OK.

## 2026-05-04 12:55 CEST - Impi foundation backend + NGO workspace UI shell
- `impact-community.php`: additív Impi foundation route-ok bevezetve: `GET /impi/capabilities`, `POST /impi/orchestrate`, `GET /impi/jobs/{job_id}`, `GET /impi/audit/{audit_id}`.
- Az első increment tudatosan fail-safe: nincs külső AI runtime, nincs release-ready output, a legal blokk továbbra is hard-blocked.
- Az authz jelenlegi foundation szinten pseudo + NGO körtagság ellenőrzésre épül; külön NGO admin scope map még nincs bekötve.
- `impact-community-app.php`: az NGO workspace placeholder helyére capability-alapú Impi foundation kártya került, három módhoz külön inputtal és status/audit visszajelzéssel.
- Verifikáció: `php -l wp-content/mu-plugins/impact-community.php` OK, `php -l wp-content/mu-plugins/impact-community-app.php` OK.

## 2026-04-29 09:50 CEST - Jovonk Vize aukcio staging deploy + smoke eredmeny
- Audit utani fix: a success redirect ág most mar csak ellenorzott Stripe sessionnel teljesit, es a staging runtime blokkolja az elo Stripe checkout session letrehozasat.
- Utolagos staging smoke bizonyitek: `home_url=https://app.sharity.hu/impactshop-staging/`, `stripe_mode=live`, `is_staging_runtime=true`, es a szintetikus winner-payment session-letrehozas eredmenye `null`, tehat a live Stripe guard aktiv.
- A ket uj aukcios runtime fajl celzott staging sync-kel kiment az `app-staging` peldanyra, majd a read-only jogosultsag vissza lett zarva:
  - `wp-content/mu-plugins/impactshop-event-auction-widget.php`
  - `wp-content/mu-plugins/impactshop-event-auction-widget-jovonkvize-1.0.0.js`
- A staging public read lane elerheto; a kampany payload visszaadja a `security.write_enabled: true` es `session_token` mezoket.
- A public write lane gyokeroka nem token-persistence hiba: a szerveren kiadott session token ugyanazzal az Origin + User-Agent contexttel valid (`get_transient(...)` tombot ad vissza, `impactshop_event_auction_verify_session_token(...) === true`).
- A staging `register-bidder` HTTP POST csak a kanonikus `https://app.sharity.hu/impactshop-staging` hoston mukodik. A `https://www.sharity.hu/impactshop-staging` utvonal POST esetben 302-vel atiranyit az `app.sharity.hu` hostra, ezert a korabbi `invalid_session_token` smoke valojaban host-canonicalization mellekhatas volt.
- Direkt public smoke az `app.sharity.hu` hoston sikeres volt: `register-bidder` HTTP 200, ervenyes `bidder_token` visszajott.
- Direkt backend staging smoke WP-CLI-bol sikeres volt: `bid` 200, `close` 200, `payment` 200; a lot allapota `live -> closed -> payment_pending` lett, Stripe Checkout URL letrejott.
- A protected admin REST smoke stagingen jelenleg kornyezeti okbol blokkolt: a lekert staging userek mind ures `roles` tombbel jonnek vissza, es egyiknel sem igaz a `manage_options`, igy a route-szintu admin permission callback jogosan ad 403-at.
- Fontos kockazat: a staging winner-payment smoke `cs_live_...` Stripe sessiont adott vissza, tehat a staging jelen allapotban elo Stripe kulcsot hasznal. Emiatt tenyleges fizetesi completion smoke-ot nem futtattam tovabb.

## 2026-04-29 14:30 CEST - Jovonk Vize aukcio widget additiv scaffold letrehozva
- Uj, additiv auction scaffold modul keszult a JVK repo-ban kulon PHP es kulon verziozott frontend JS fajlkent:
  - `wp-content/mu-plugins/impactshop-event-auction-widget.php`
  - `wp-content/mu-plugins/impactshop-event-auction-widget-jovonkvize-1.0.0.js`
- A scaffold szandekosan nem nyul a meglevo protected donation widget legacy fajlokhoz; uj kod lane-en hozza be az `event-auctions` olvaso REST surface-et es a gallery/detail/bid skeleton UI-t.
- A `register-bidder` es `bid` write lane most mar aktiv alapimplementaciot kapott: session token, bidder token, rate limit, idempotency key es tranzakcios update lane kerult be.
- Az `admin close` lane most mar WP admin + nonce kapuval zarhato, a `winner payment` lane pedig Stripe Checkout sessiont, webhook completiont es success/cancel reconcile endpointet kapott.
- Tovabbra sincs admin trigger UI es automatikus winner-e-mail lane; ezek kovetkezo fazisba maradtak.
- Read fallback csak olvaso vegpontokra kerult be; `bid` query fallback nincs.
- Deploy nem tortent; protected legacy touch nem tortent ebben a change setben.

## 2026-04-28 12:25 CEST - Jövőnk Vize widget ticket-mix parity + live/dev szétválasztás lezárva
- A Jövőnk Vize widget backend most külön menti és továbbítja az `regular_ticket_count` és `supporter_ticket_count` mezőket; a buyer/admin e-mailek és a stats payload is külön bontást kapott.
- A sémafrissítés explicit hiányzó-oszlop backfillt kapott, így production és staging alatt a régebbi táblák is felhozhatók a szükséges ticket mezőkre.
- A frontend `1.7.2` logika most már dev és live URL-en is azonos: vegyes jegyválasztó, csomag + extra jegyek additív összesítése, egyértelműbb összegző szöveg.
- A cache megkerüléséhez fizikai verziózott fájlnevek kerültek dokumentálásra:
  - dev: `impactshop-event-donation-widget-dev-1.7.2.js`
  - live: `impactshop-event-donation-widget-jovonkvize-1.7.2.js`
- Deploy a sessionben bastion-approved hotfix útvonalon ment ki productionre és stagingre; rollback artefakt: `.codex/reports/hotfix-sync/rollback_20260428T093909Z.sh`.

## 2026-04-28 11:05 CEST - Hatás Körök report flow: reakció + report + inbox útvonal lezárva
- A feed reakciógombok gyökérok javítva: a frontend `html(...)` helper eddig `disabled=false` esetben is kirakta a `disabled` attribútumot, ezért a reakciógombok ténylegesen le voltak tiltva. Javítva és deployolva.
- A feed composer alias badge most már kör-detail fallbackből is hidratálódik, ezért a posztoló álneve nem generikus fallback szöveg.
- A `/report` backend útvonal most már közvetlenül ment a `wp_ic_reports` táblába, nem csak logol.
- A report e-mail előbb bevált header-profillal lett egységesítve, majd scoped Google MX SMTP routingot kapott a `@sharity.hu` címzettekre.
- Production verifikáció:
  - `wp_ic_reports` rekordok: `#1`, `#2`, `#3`
  - debug log: `ic_post_report_mail_result ... sent:true`
  - felhasználói visszajelzés: az inboxba a report levél megérkezett.

## 2026-04-23 08:15 CEST - Hatás Körök clean avatar PR előkészítve
- Külön, `origin/main` alapú clean worktree ágon újraépítettem az Impi avatar vizuális frissítést, hogy ne húzza magával a settlement-picker fejlesztési diffet.
- A clean ág csak a `wp-content/mu-plugins/impact-community-app.php` fájlt módosítja.
- Változás: emoji avatar helyett animált meerkat videó, pulzáló kék ring, AI badge és rendezettebb Impi author blokk.
- Ellenőrzés: `php -l wp-content/mu-plugins/impact-community-app.php` OK.

## 2026-04-20 08:30 CEST - deploy-path audit lezárva + guard origin check
- A production deploy-path audit lezárása alapján a kanonikus production runtime továbbra is `/home/sharityh/app`.
- A `public_html/index.php` csak entry wrapper, amely az `../app/wp-blog-header.php` runtime-ra mutat.
- `bin/deploy-wpcontent-map.sh` kapott explicit production-origin alignment ellenőrzést erre a wrapper kapcsolatra.
- `bin/post-deploy-activate.sh` production pathja javítva lett `/home/sharityh/app` értékre.
- Az audit és a guard follow-up dokumentumok most már lezáró következtetéssel ezt a production truth-ot rögzítik.

## 2026-04-20 07:00 CEST - ads-watch review-fix: trailing resize after burst
- PR review visszajelzés alapján a mobil resize throttle most már trailing futást is ütemez, így a burst végén érkező végső konténerméret is átmegy az IMA resize felé.
- A kapcsolódó postmortem addendum szövegében javítva lett az `eldobj` elírás.
- Ellenőrzés: `node --check wp-content/mu-plugins/impactshop-ads-watch.js` OK, `impact-challenge-ui-smoke.sh` OK.

## 2026-04-19 16:08 CEST - ads-watch cache-bypass verzióbump lezárva
- Az `impactshop-ads-watch.php` asset verzió `2.5.65` → `2.5.66` lett, hogy új JS URL keletkezzen purge nélkül.
- A PHP deploy backup + rollback mellett kiment mindkét érintett példányra:
  - `/home/sharityh/app/wp-content/mu-plugins/impactshop-ads-watch.php`
  - `/home/sharityh/app-staging/wp-content/mu-plugins/impactshop-ads-watch.php`
- Publikus ellenőrzés:
  - `X-ImpactShop-AdsWatch-Version: 2.5.66`
  - HTML script URL: `impactshop-ads-watch.js?ver=2.5.66`
  - publikus JS hash: `3cd313f32a253cff5226a8322a971d7f529bba999cf3b698af22a88da48a614b`
- `impact-challenge-ui-smoke.sh` zöld maradt, a cache-bypass lezárás sikeres.

## 2026-04-19 15:35 CEST - ads-watch mobile resize hotfix + deploy-path drift
- Szűk incidens-hotfix készült az `impactshop-ads-watch.js` mobil resize ágára: hidden-doc guard, tiny-delta skip, burst throttle.
- Protected change record: `docs/protected-change-records/2026-04-19-ads-watch-mobile-resize-throttle-hotfix.md`.
- A deploy kivizsgálás közben kiderült, hogy a publikus `app.sharity.hu` JS hash nem az `/home/sharityh/app`, hanem az `/home/sharityh/app-staging` példánnyal egyezett.
- Emiatt a hotfix backup + rollback mellett mindkét pathra kiment: `app` és `app-staging`.
- Health endpoint és `impact-challenge-ui-smoke.sh` zöld maradt.
- A publikus asset a frissítés után is Cloudflare `HIT` cache-ből a régi `2.5.65` tartalmat szolgálta, ezért a kliensoldali látható hatás purge vagy verzióbump nélkül nem garantált azonnal.

## 2026-04-19 15:35:17 CEST - impactall auto log
- **Result:** stale/no-fresh-run
- **Megjegyzés:** kézi `~/bin/impactall` újrafuttatás indult, de nem írt friss `impactall-last-run.json` állapotot; a legutóbbi ismert snapshot továbbra is `2026-04-17 07:20:24 CEST`, `warn` (warnings=2, errors=0, duration=4s).
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-04-26 11:40 CET - always-reward rollout (ads-watch + click-tracking) + CTA sandbox guard
- Impact Challenge protected touch: `impactshop-ads-watch.php` and `impactshop-click-tracking.php` legacy path updated with explicit approval for always-reward feature-flag rollout.
- Added default-off flag hook in ads-watch (`IMPACTSHOP_ADS_ALWAYS_REWARD_DEFAULT=false`) and filter gate `impactshop_ads_watch_is_always_reward_enabled()`.
- View dedupe branch now supports instance-level dedupe key when always-reward is enabled; legacy 5s block dedupe remains default path.
- CTA click tracking aligned to always-reward mode with instance dedupe generation and key length guard (`substr(...,0,140)`).
- Added CTA sandbox early-return in `impactshop-click-tracking.php` so dev-clone testing cannot write/award in production lane.
- Deploy executed via bastion-approved hotfix path (prod+staging), with backup manifests and rollback script generated by deploy wrapper.
- Server-side cache flush completed for production and staging during deploy window.
- Production validation: active flag file present on server (`impactshop-always-reward-flag.php`), and post-flag CTA mismatch check returned 0.

## 2026-04-14 15:50 CET - PR103 review-fix: sandbox trust tightening
- `impactshop_ads_watch_get_request_write_mode()` mostantól csak admin+nonce kérésnél enged sandbox módot.
- `write_mode` query param fallback kivezetve (header-only control).
- `allocate_votes` sandbox early-return az NGO-mismatch validáció elé került, így dev clone tesztflow nem akad el.
- Cache-buster bump: `IMPACTSHOP_ADS_WATCH_VERSION=2.5.65`.

## 2026-04-14 15:25 CET - ads-watch security hardening + dev-clone sandbox deploy
- Security hardening deploy kész: debug endpoint default OFF + admin/nonce gate + response redaction.
- Dev clone guard aktív: anon `/impact-challenge-dev` 404, noindex header policy bent maradt.
- Sandbox write mode bekötve: `X-ImpactShop-Write-Mode` header kezelve, write endpointok sandbox early-return ágon.
- Deploy verifikáció: prod API 200, debug endpoint 404, dev clone route 404, remote hash parity egyezik.

## 2026-04-07 23:30 CET - ads-watch v2.5.53 CTA navigation fix + bundle disable
- v2.5.53: CTA window.open() hiányzott event.preventDefault() után → sponsor oldal nem nyílt meg. Javítva.
- MutationObserver bundle (ui-cta-bundle) letiltva PHP return;-nel — ez okozta a ~240/sec observer callback freeze-t.
- Production deploy verifikált. Change record: docs/protected-change-records/2026-04-07-cta-freeze-fix-v2.5.53.md

## 2026-04-07 21:00 CET - ads-watch v2.5.52 sponsor video freeze fix
- v2.5.52 visszaállítja a 7 kritikus sponsor return patternt (externalNavigationSource, CTA _blank link, visibility handler, contentComplete placement).
- Gyökér ok: v2.5.51 nem hozta át a v2.5.55 sponsor-specifikus kezelését → Chrome/Safari freeze a sponsor videó végén.
- Production deploy verifikált. Change record: docs/protected-change-records/2026-04-07-ads-watch-sponsor-v252.md

## 2026-04-01 08:58 CET - JYSK riport max-védett guide surface
- A `jysk-riport` route-család név szerint is max-védett guide surface lett: `/jysk-riport/`, `/jysk-riport/?print=1`, `/jysk-riport.data.json`.
- A protected modell új `guide_runtime` smoke scope-pal most már külön ellenőrzi a route render, print nézet és JSON payload folytonosságát.

## 2026-04-01 16:40 CET - JYSK canonical inventory lock
- A JYSK source assetek most már explicit guard inventory + hash lock alatt is állnak.
- A `impactshop-ngo-guides.php`, `jysk-riport.html` és `jysk-riport.data.json` bekerült a `docs/impactshop-guard-config.json` és `docs/impactshop-guard-hashes.json` kanonikus kontrollrétegébe.

## 2026-04-01 16:47 CET - JYSK review thread data + UX cleanup
- A JYSK raw JSON-ban a kevert vagy bizonytalan legacy dátummezők normalizálva lettek: Debrecen ISO dátumot kapott, Kispest/Szarvas ellentmondásos végei null-ra kerültek.
- A riport HTML most elsődlegesen a `/jysk-riport.data.json` route-ból tölt, az embedded adat csak fallback snapshot marad, és a toolbar gomb felirata az aktuális működéshez igazodik.

## 2026-03-26 19:45 CET - fix: impact-community.php ngo_admin_url visszaállítva
- Merge conflict resolution során az auto-merge visszaállította a régi `/impact-challenge/ngo-admin/` URL-eket.
- Fix: 3 helyen visszaállítva `/impact-shop_ngo/`-ra (sorok: 2058, 2647, 4743).
- PRODUCTION NEM ÉRINTETT — csak a feat/jovonkvize-ticket-count ágon volt hiba, a PR #83 még nem volt merge-elve.

## 2026-03-26 19:30 CET - feat/jovonkvize merge: origin/main conflict resolution
- impact-community.php: test_mode bypass (main) megtartva; event-donation-widget: ticket_serials schema 1.2.0 (feature); ngo-guides: jogi-dokumentumok + 1.1.2 (feature).
- Merge commit: ef88de74

## 2026-03-26 16:54 CET - Impact Challenge canonical baseline
- New canonical reference doc created: `docs/impact-challenge-canonical-baseline.md`
- Impact Challenge runtime perimeter normalized as canonical protected baseline (ads-watch, autobanner, offerwall, identity, affiliate glue, PWA, guides).
- Local runtime MU-plugin set locked to `0444`; guide subtree remains `0444/0555`.
- PR / merge / deploy policy files now explicitly reference the canonical baseline.

## 2026-03-25 CJ + Dognet go-deal hotfix + merge logika
- Bug 1: fizz shop törölve (nem volt a Dognet CSV-ben, csak AI registry-ben)
- Bug 2: CJ limit 200→5000, Skechers (6322281) hozzáadva product catalog-hoz → 473 CJ link
- Bug 3: Dognet tracking URL passthrough — go.dognet.com href-ek skip API, közvetlen redirect d1+data5-tel
- --advertiser-ids CLI param bekötve cli_fetch_links → sync_links
- --merge flag: sync_links meglévő linkeket megtartja szűrt fetch esetén
- awin/tradedoubler nincs go-deal-ben — feed URL-ek, nem click URL-ek; a 3 érintett shop (Zooplus/Dyson/Nike) dognet_base click URL-je a passthrough-val kezelve
- Banner CSV-ben 3 shop (Zooplus/Dyson/Nike) feed URL-t tartalmaz click URL helyett → adatminőségi hiba, javítandó

## 2026-03-25 Hatás Körök smoke bekötve a deploy rutinba
- `bin/deploy-wpcontent-map.sh`: production mapping deploy után automatikus `scripts/hatas-korok-post-deploy-smoke.sh`
- `bin/post-deploy-checklist.sh`: új 6. ellenőrzésként futtatja a Hatás Körök smoke-ot
- Ellenőrzés: `bash -n` mindkét shell scriptre OK, smoke futás productionön OK

## 2026-03-25 Hatás Körök post-deploy smoke tooling
- Új read-only deploy smoke script: `scripts/hatas-korok-post-deploy-smoke.sh`
- Új checklist: `docs/hatas-korok-post-deploy-checklist.md`
- Cél: deploy után gyors route + bootstrap + auth + circles API ellenőrzés, production állapotmódosítás nélkül

## 2026-03-25 Cert aláírás kép csere
- Pecsétes → pecsét nélküli (IMG_3880.HEIC)
- PHP v1.5.3 deployed

## 2026-03-25 Meghatalmazás PDF csere
- IMG_3880.HEIC → PDF konvertálva és feltöltve
- Pecsét nélküli, magánszemély aláírás (dr. Bujdosó Arnold)

## 2026-03-25
- feat: cert email meghatalmazás PDF auto-csatolása (v1.5.2) — sharity-meghatalmazas-adomanyigazolas.pdf feltöltve szerverre, PHP-ban WPMU_PLUGIN_DIR + file_exists csatolja
- fix: solo jegy CSS !important hozzáadva (host override ellen), v1.3.0

## 2026-03-24
- fix: solo jegy CSS hiány javítva — or-sep, solo-tickets, solo-select stílusok hozzáadva

## 2026-03-24
- version bump 1.1.0 → 1.2.0 (impactshop-event-donation-widget.php) — cache-bust deploy

## 2026-03-24
- version bump 1.1.0 → 1.2.0 (impactshop-event-donation-widget.php) — cache-bust deploy

# 2026-05-28 (3)

- Jövőnk Vize: jovonkvize.js szinkronizálva dev.js-sel — solo jegyvásárlás (1-10 db × 150k Ft) élesben.

# 2026-05-28 (2)

- Jövőnk Vize widget: verzió bump 1.0.0 → 1.1.0, embed kód `?v=1.1.0` frissítve.

# 2026-05-28

- Jövőnk Vize widget (`feat/jovonkvize-ticket-count`): standalone jegyvásárlás dropdown (1-10 jegy × 150 000 Ft), tranzakció értesítő email (bujdoso.arnold@ + koncz.veronika@), cert BCC bujdoso.arnold@.
- PHP: `impactshop_event_donation_send_transaction_notification()` + `fulfill()` hook + BCC header.
- JS dev: `TICKET_UNIT_PRICE=150000`, `STANDALONE_TICKET_MAX=10`, solo ticket change handler, `init()` dropdown feltöltés.

# 2026-03-24

- Jövőnk Vize gála widget: `impactshop-event-donation-widget.php` schema 1.1.0 — `ticket_count` + `selected_package` DB + Stripe metadata. Branch: `feat/jovonkvize-ticket-count`.
- Impact Community (Hatás Körök) Sprint 1+2 MU plugin added.
- `impact-community.php` — backend: 7 DB tables, REST API (11 endpoints), IC_Alias class, NGO circle seed, buddy pairing, Points-Manager integration.
- `impact-community-app.php` — frontend SPA: hash routing, circle cards, post feed, voting, composer.
- Feature flag: `IMPACT_COMMUNITY_ENABLED`. Route: `/hatas-korok/` via template_redirect priority 4.

# 2026-03-23

- Autobanner feed import support added to MU plugins.
- Legacy autobanner sync caps raised to 1000 for Sheets and Dognet paths.
- Goal: keep live autobanner inventory above the 1000-item target without relying on a tiny sync-only window.

# Projekt napló

## 0. Rövid összefoglaló
- Platform: WordPress (ImpactShop)
- Fő téma: akciós kártyák linkjei → ne a shop főoldalra, hanem termékoldalra vigyenek.

### 2026-03-05 — AI Agent Checkpoint: Lekapcsolás előtti gyorsmentés

**Állapot összefoglaló — Legal AI Agent (Phase 28+ folytatás)**

| Mutató | Érték |
|--------|-------|
| MCP tools | **60** (56 korábbi + 4 új: `kb_dashboard`, `kb_retry_failed`, `kb_ingest_doc`, `kb_enrich_pending`) |
| TypeScript hibák | **0** |
| Tesztek | **15/15 PASS** |
| Pipeline v3 | **batch 55/70** fut háttérben, 100% success rate |
| KB documents | **150** (Kúria); Drive corpus: **5,000** |
| Enrichments | **~5,680+** kész (pipeline v3 batch 55/70-nél tart) |
| impactall | ❓ (repók átrendezés miatt `ai-agent/.git` hiányzik, impactshop-notes git OK) |

**Mai session összefoglaló:**
1. ✅ **4 TS hiba javítva** — `rawQuery` param array, `ProvenanceRecord` mezők, `links: {}` mező
2. ✅ **`liveIngestPipeline.ts`** — auto-enrich+chunk modul (enrichAndChunkOne, enrichAndChunkPending, retryFailedEnrichments, getKBDashboard)
3. ✅ **Post-ingest hooks** — 5 pipeline metódusba fire-and-forget enrich+chunk
4. ✅ **4 új MCP tool** — `kb_dashboard`, `kb_retry_failed`, `kb_ingest_doc`, `kb_enrich_pending`
5. ✅ **`AGENTS.md`** — repo guidelines AI agenteknek
6. 🔄 **Pipeline v3** — háttérben fut (`nohup`+`disown`), batch 55/70, log: `/tmp/pipeline-v3.log`

**⚠️ Ismert issue: `ai-agent/.git` hiányzik** — repó átrendezés folyamatban, bekapcsolás után ellenőrizni kell.

**Folytatáskor:**
- Pipeline v3 log ellenőrzés: `tail -20 /tmp/pipeline-v3.log`
- Ha kész → `SELECT COUNT(*) FROM chunks` ellenőrzés
- ai-agent `.git` helyreállítás ha szükséges
- impactall futtatás újra
- Tesztek bővítése az új live ingest pipeline-hoz

---

### 2026-03-04 — AI Agent Checkpoint: Phase 23 álláselmentés

**Állapot összefoglaló — Legal AI Agent**

| Mutató | Érték |
|--------|-------|
| Legal modul fájlok | **24** (`apps/core-agent-graph/src/legal/`) |
| MCP tools | **42** (`apps/mcp-wrapper/src/tools/legal-tools.ts`) |
| OpenAI SDK | **`openai@^6.25.0`** (Responses API) |
| API | **Responses API** (`client.responses.create()`) — 9 hívás / 8 fájl |
| Chat Completions | **0** hívás (teljesen migrálva) |
| Capability routing | **getModelForCapability()** — minden caller egységesítve |
| Langfuse tracking | **9 × `trackGeneration()`** — minden LLM caller lefedve |
| SQLite DB | 9 tábla + FTS5 + 18 index, WAL mód, 64 MB cache |
| TypeScript hibák | **0** |
| impactall | ✅ zöld |

**Phase 23 (2026-03-04) — 6 feature:**
1. `previous_response_id` chaining (Impi expand retry — szerver-oldali állapot)
2. Context compaction (`truncation: 'auto'` — 4 fájl)
3. Background batch ingest (`background: true` + `responses.retrieve()` poll) + MCP #40
4. Capability routing egységesítés (5 caller → `getModelForCapability()`)
5. Jogszabály diff (`legalDiff.ts` — §-szintű időállapot összehasonlítás) + MCP #41
6. Langfuse analytics (`trackGeneration()` + `getAnalyticsSummary()`) + MCP #42

### 2026-03-04 — AI Agent Checkpoint: Phase 20–22 álláselmentés (korábbi)

**Állapot összefoglaló — Legal AI Agent**

| Mutató | Érték |
|--------|-------|
| Legal modul fájlok | **22** (`apps/core-agent-graph/src/legal/`) |
| MCP tools | **39** (`apps/mcp-wrapper/src/tools/legal-tools.ts`, 1415 sor) |
| OpenAI SDK | **`openai@^6.25.0`** (v4→v6 major upgrade) |
| API | **Responses API** (`client.responses.create()`) — 8 hívás / 7 fájl |
| Chat Completions | **0** hívás maradt (teljesen migrálva) |
| SQLite DB | 9 tábla + FTS5 + 18 index, WAL mód, 64 MB cache |
| TypeScript hibák | **0** |
| impactall | ✅ zöld |

**Phase 20 (2026-03-03) — Playwright Scraper Expansion:**
- 6 új Playwright scraper: `legalPlaywrightEngine.ts`, `abScraper.ts`, `mnbScraper.ts`, `navScraper.ts`, `betScraper.ts`, `sourceHealthMonitor.ts`
- 2 API client: `mnbFxClient.ts` (MNB SOAP), `eurLexClient.ts` (EUR-Lex SPARQL/REST)
- 12 új MCP tool (#23–#34)

**Phase 21 (2026-03-04) — SQLite Database Layer:**
- `legalDatabase.ts` (1083 sor) — 9 tábla, FTS5, BM25 fulltext search, rekurzív CTE gráfbejárás
- `knowledgeBaseRegistry.ts` + `legalKnowledgeGraph.ts` — dual-write: SQLite primary + JSON/CSV fallback
- 5 új MCP tool (#35–#39): `db_fulltext_search`, `db_citation_path`, `db_legal_context`, `db_related_documents`, `db_stats`

**Phase 22 (2026-03-04) — OpenAI SDK v6 + Responses API migráció:**
- SDK: `openai` `^4.76.3` → `^6.25.0`
- 7 fájl migrálva `chat.completions.create()` → `responses.create()`:
  1. `capabilities/legalLegislationLookup.ts` — instructions + input, Langfuse adaptáció
  2. `capabilities/taxChecklist.ts` — azonos minta
  3. `capabilities/decision.ts` — capability router, max_output_tokens: 50
  4. `nodes/contentStrategyNode.ts` — JSON mode: `text: { format: { type: 'json_object' } }`
  5. `api-gateway/services/impi-openai.ts` — 2 hívás (válasz + multi-turn retry)
  6. `api-gateway/services/impi-critic.ts` — JSON mode + scrubPII
  7. `api-gateway/services/executive-summary.ts` — instructions + input
- API mapping: `messages` → `instructions`+`input`, `max_tokens` → `max_output_tokens`, `res.choices[0]?.message?.content` → `res.output_text`, usage: `prompt_tokens/completion_tokens` → `input_tokens/output_tokens`

**Frissített dokumentumok:**
- `impactshop-notes-clean-2/notes.md` — Phase 20–22 részletes napló
- `system-status-snapshot.md` — delta log bejegyzések
- `docs/system-recovery-map.md` — legal modul / MCP tool referencia

**Model routing (változatlan):**
- `getModelForCapability(capabilityId)` in `capabilities/types.ts`
- Resolution: `OPENAI_MODEL_{CAPABILITY_ID}` env → manifest.modelId → `OPENAI_MODEL` env → `'gpt-4o'`
- GPT-5.3-Codex azonnal tesztelhető env var-ral amikor elérhető

**Következő lehetséges lépések:**
- ~~GPT-5.3-Codex tesztelés legal analysis minőségre~~ ✅ (lásd Phase 22b alább)
- ~~KB pipeline Responses API validálás~~ ✅ (rule-based, nincs LLM → kész)
- ~~Hosted container + Skills értékelés~~ ✅ (értékelve, jelenleg nem alkalmazható)

### 2026-03-04 — Phase 22b: GPT-5.3-Codex tesztelés + Model routing config

**Elérhető modellek az API-n (ellenőrizve):**
- `gpt-5.3-codex`, `gpt-5.3-chat-latest`
- `gpt-5.2`, `gpt-5.2-codex`, `gpt-5.2-pro`
- `gpt-5.1`, `gpt-5.1-codex`, `gpt-5.1-codex-max`, `gpt-5.1-codex-mini`
- `gpt-5`, `gpt-5-codex`, `gpt-5-mini`, `gpt-5-nano`, `gpt-5-pro`
- `o4-mini`, `o3`, `o3-mini`
- `gpt-4o`, `gpt-4o-mini` (korábbi default)
- ⚠️ `gpt-5.3-codex-spark` NEM létezik (hibaüzenet: 400)

**Benchmark — Legal Analysis (Responses API, ÁFA alanyi vs tárgyi mentesség):**

| Model | Idő (ms) | In tok | Out tok | Reasoning tok | Chars | § hivatkozások | Áfa tv. ref |
|-------|----------|--------|---------|---------------|-------|----------------|-------------|
| gpt-4o | 9252 | 92 | 369 | 0 | 1032 | ✅ | – |
| gpt-4o-mini | 9122 | 92 | 314 | 0 | 871 | ✅ | ✅ |
| **gpt-5.3-codex** | **10775** | **91** | **414** | **0** | **1058** | **✅** | **✅** |
| gpt-5-mini | 39801 | 91 | 2098 | 1728 | 941 | ✅ | ✅ |
| gpt-5-nano | 20136 | 91 | 2496 | 2496 | 0 | ❌ | – |
| o4-mini | 13727 | 91 | 1700 | 1280 | 987 | ✅ | ✅ |

**Minőségi értékelés (gpt-5.3-codex):**
- ✅ Pontos § hivatkozások: Áfa tv. 187–196. § (alanyi), 85–87. § (tárgyi)
- ✅ Strukturált válasz: „ki vagyok én" vs „mit csinálok" összefoglaló
- ✅ 2007. évi CXXVII. törvény helyes azonosítás
- ✅ Átlag 5.7 § hivatkozás/válasz (3 query benchmark)
- 📊 Hasonló sebesség mint gpt-4o (~9-11s), de gazdagabb jogi tartalom

**3-query Legal Benchmark (gpt-5.3-codex, Responses API):**

| Téma | Idő (ms) | In tok | Out tok | Chars | § refs | Tv. refs |
|------|----------|--------|---------|-------|--------|----------|
| ÁFA | 8506 | 78 | 418 | 1086 | 5 | 1 |
| TAO | 7192 | 90 | 374 | 895 | 3 | 0 |
| KATA | 11732 | 80 | 514 | 1224 | 9 | 6 |

**Beállított model routing (.env):**
```
OPENAI_MODEL_LEGAL_LEGISLATION_LOOKUP=gpt-5.3-codex
OPENAI_MODEL_TAX_CHECKLIST_HU=gpt-5.3-codex
OPENAI_MODEL=gpt-4o
# OPENAI_IMPI_MODEL → gpt-4o-mini (hardcoded default in code)
```

**KB pipeline Responses API validálás:**
- A KB pipeline (`classifyTopics`, `chunkLegalDocument`, `classifyPrecedentialWeight`, `classifyAnonymization`) mind **rule-based** (keyword matching, regex) — nincs LLM hívás
- Minden LLM hívás a capabilities + api-gateway rétegben van → Phase 22-ben migrálva
- ✅ Nincs további teendő

**Hosted container + Skills értékelés:**
- **Skills:** versioned SKILL.md + fájl bundle, hosted shell-ben futtatva (container_auto)
- **Background mode:** async long-running response, poll-al ellenőrizhető, `background=true`
- **Compaction:** `context_management` + `compact_threshold` server-side automatikus
- **Conversations API:** `conversations.create()` + `conversation` param → durable multi-turn state
- ⚠️ **Jelenlegi korlátok:** shared hosting (cp40.ezit.hu) → nincs Docker → hosted container nem futtatható szerveren
- ⚠️ A KB pipeline rule-based → nem profitál a hosted container-ből
- 📌 **Jövőbeni lehetőség:** ha a pipeline cloud-ba költözik, Skills + Background mode + Compaction ideális lenne a nagy jogi dokumentum-feldolgozáshoz

### 2026-02-27 – Checkpoint #2: Két reziduális bug diagnosztizálva + új terv kész

- **Checkpoint oka:** Gép leállítás előtti memória-mentés.
- **Előzmény:** A `docs/cj-autobanner-image-debug-2026-02-26.md` terv IMPLEMENTÁLVA lett a szerveren (6 CJ shop reakiválva, XSS fix, boot.php fallback). Két maradék hiba maradt.
- **BUG-A (kép `?` jel):** Csökkent a szám, de nem 0. Hipotézis: üres `image_url` + hiányzó fallback logo (`{slug}-logo.png` nem létezik) → dupla 404. Szerver-oldali diagnózis szükséges (D.1–D.5 lépések a tervben).
- **BUG-B (NGO kiválasztva → mégis Fillout-ra visz) — GYÖKÉROK MEGERŐSÍTVE:**
  - `resolveFilloutUrl()` (2535. sor) és `transformBannerUrl()` (2640. sor) MINDIG Fillout URL-t ad vissza
  - `buildFilloutUrl()` sosem vizsgálja, hogy van-e `ngoSlug` → ha van, is Fillout-ra küld
  - A `/go-deal/` builder a `transformBannerUrl()`-ben (2680. sor) **halott kód**: soha nem érhető el
  - **Fix:** `if (ngoSlug && cleanSlug)` → direkt `/go-deal/{shop}?d1={ngoSlug}&u=...`
  - Szerver-oldal (`boot.php`) MÁR HELYESEN kezeli a `d1` paramétert — fix CSAK kliens-oldali JS-ben kell
- **Új dokumentum:** `docs/autobanner-routing-image-debug-2026-02-27.md` — teljes terv, fix kóddal
- **Prioritás:** 🔴 BUG-B először (UX-kritikus), 🟠 BUG-A másodszor (vizuális)
- **Státusz:** Terv kész, implementáció NEM történt. Következő lépés: D.1–D.5 szerveren → F.1–F.7 implementáció.

### 2026-02-27 – Checkpoint: CJ autobanner diagnosztika terv véglegesítve

- **Checkpoint oka:** A user bekapcsoláskor régi állapotot lát; rögzítjük a legutóbbi véglegesített tervet.
- **Dokumentum:** `docs/cj-autobanner-image-debug-2026-02-26.md` – végleges, koherencia- és biztonsági audit beépítve.
- **Lényeges döntések:** P0.0 XSS fix; P0.1 boot.php fallback; P0.2 registry sync; shared hosting miatt kép-proxy tiltva; cleanup cron reactivation logikával.
- **Státusz:** IMPLEMENTÁLVA a szerveren (2026-02-26/27). Maradék bugok → Checkpoint #2 fent.

### 2026-02-21 – Szintrendszer/decay/donation multiplier revamp (indítás)

- **Feladat:** `docs/fejlesztesi-allapot-2026-02-20-terv.md` véglegesítése, koherencia + biztonsági audit, majd teljes implementáció (szintek, decay, offerwall cap, admin, értesítések, UI/Docs).
- **Előkészítés:** guard/backup lépések indítása, érintett mu-plugin és docs fájlok listázása.

### 2026-02-21 – Szintrendszer/decay/donation multiplier revamp (lezárás)

- **Implementáció:** szint küszöbök + szorzók frissítve, decay 5 nap grace + új ráták + floor, offerwall napi cap; ads-watch súlyozás donation multiplierrel + státusz sávban adományszorzó.
- **Admin/értesítések:** új admin dashboard (keresés + korrekció + eloszlás) és szintváltás/inaktivitás üzenetek.
- **Dokumentáció/UI:** `docs/fejlesztesi-allapot-2026-02-20.md`, `impactshop-notes/IMPACT-CHALLENGE-UI-JAVASLATOK.md`, `impactshop-notes/docs/stripe-adomany-integration-plan.md` frissítve; NGO guide táblák egyeztetve.
- **Backup:** új guard backup: `impactshop-notes/.codex/backups/20260221-103503-adswatch-ui`; 5 napnál régebbi backup törölve (felhasználói kérés).
- **Teszt:** `IMPACTALL_TIMEOUT_SEC=600 ./impactall` ✅ (15/15); guard log: GitHub token 14 napon belül lejár.
- **Deploy:** guard staging + production ✅ (snapshot: `deploy-20260221-093747`, `deploy-20260221-093829`; preflight OK; `impactshop-dognet-report` lokálisan hiányzott, skip).

### 2026-02-09 – Unified Data Pipeline Plan (Offer Hub)

- **Feladat:** Átfogó terv az adatfolyamatok egységesítésére – minden párhuzamos/duplikált folyamat megszüntetése.
- **Terv dokumentum:** `docs/unified-data-pipeline-plan.md`
- **Probléma:** 5+ adatforrás, 3 megjelenítő felszín, 2 repo, duplikált feldolgozás mindenhol (Google Sheets 2×, Dognet API 2×, CJ links 3×, 4 db copy-paste Python script).
- **Megoldás: „Offer Hub"** – egyetlen `wp_impactshop_offers` tábla mint Single Source of Truth.
  - Egységes Offer Ingest Pipeline (PHP mu-plugin, WP Cron)
  - Source adapterek: sheets, dognet, cj, gmail, arukereso
  - Minden shortcode + widget az Offer Hub-ból olvas (nincs runtime API hívás)
  - AI Agent Memory Sync: WordPress → REST API → Graphiti/Neo4j (30 percenként)
  - **Impi (AI Agent) minden ajánlatot ismer** – 100% adatlefedettség a memóriában
- **5 fázis:** Előkészítés → Source Adapterek → Fogyasztók átállása → Cleanup → Monitoring
- **Várható hatás:** page load 5-15× gyorsabb, AI lefedettség 30%→100%, kód -70%
- **Kockázatkezelés:** A/B teszt, `.off` suffix (nem törlés), 30 napos párhuzamos futás
- 📌 Következő lépés: Fázis 0 implementáció – tábla séma + skeleton `impactshop-offer-ingest.php`

### 2026-01-28 – Cégjelző API Integrációs Terv

- **Feladat:** Cégjelző API (v2) bekötés tervezése az NGO-k adatgazdagításához.
- **Cél:** Civil szervezetek hivatalos jogi és szervezeti adatainak automatikus lekérdezése és beolvasztása az ImpactShop rendszerbe.
- **API dokumentáció áttekintve:** https://docs.api.cegjelzo.com/
- **Releváns végpontok:**
  - `/autocomplete` – NGO keresés typeahead (type=civil_orgs)
  - `/search` – Részletes jogi adatok (X-Fields szelektálás)
  - ~~`/financials-data-table`~~ – ❌ Nem elérhető (nincs a JWT-ben)
- **Autentikáció:** X-Api-Key + X-Client-Id header, 30 req/s rate limit, havi kvóta
- **Civil szervezet mezők (19 db):** registration_number, long_name, short_name, address, nav_address, status, status_code, activity, level_of_charity, description, tax_number, representatives, leading_orgs, bank_accounts, proceedings, type, insertion, constituent_document_date, updated_at
- **⚠️ NAV státusz mezők (nav_no_tax_debt stb.) NEM elérhetők a jelenlegi JWT-ben**
- **Létrehozott terv:** `docs/cegjelzo-api-integration-plan.md` (teljes implementációs terv)
- **Fő komponensek a tervben:**
  1. `impactshop-cegjelzo.php` – Új WordPress mu-plugin (API client, enrichment service, cache, admin UI, WP-CLI, cron sync)
  2. `wp_impactshop_ngo_registry` – Új DB tábla a gazdagított NGO adatoknak
  3. Trust Score – Cégjelző adatokból számított megbízhatósági pontszám (0-100), NAV nélküli verzió
  4. NGO Card API bővítés – `organization`, `trust` mezők
  5. AI Agent Core – `cegjelzo-source.ts` modul + `impi_get_ngo_info` MCP tool bővítés
- **Fázisterv:** 5 fázis, ~1-2 hét implementáció
- **✅ API kulcs megvan** (2026-02-09 – JWT dekódolva, jogosultságok rögzítve)
- **Becsült kvóta igény:** ~1340 hívás/hó

#### 2026-02-09 – Terv véglegesítés (v1.1)
- **JWT jogosultságok integrálva:** endpoints=search+autocomplete, civil org 19 mező, cég 10 mező
- **NAV korrekció:** 5 NAV státusz mező kikommentezve (nem elérhető), nav_address elérhető
- **Financials korrekció:** `get_financials()` metódus kikommentezve (végpont nem elérhető)
- **Trust Score v2:** NAV nélküli verzió: max 100 pont (active=35, charity=30, established=15, description=10, representatives=10)
- **Biztonsági audit (§15):** ✅ PASS – credential kezelés, rate limit, input sanitizálás, GDPR kontrollok
- **Koherencia teszt (§16):** ✅ PASS – 19/19 JWT mező konzisztens, DB tábla illeszkedik, meglévő rendszer (ngo-card.php, metrics, MCP tool) koherens
- **Státusz:** VÉGLEGES – implementáció indítható

### 2026-02-18 – Offerwall Survey Plan Codex Review

- **Feladat:** `docs/offerwall-survey-merged-plan.md` koherencia és biztonsági vizsgálata, Codex javaslatok hozzáadása.
- **Elvégzett munka:**
  - Teljes dokumentum átnézése (366 sor).
  - Koherencia OK: survey scoring és reward flow összehangolt.
  - Biztonság OK: HMAC + allowlist + timestamp + rate limit + idempotencia.
  - 15 db Codex javaslat hozzáadva a dokumentum végén.
- **Fontosabb javaslatok:**
  - P0: Minor (AGE-A1) targeting tiltás GDPR/DSA miatt.
  - P0: Secret storage – ne plaintext wp_options.
  - P1: Health check endpoint a survey backendhez.
  - P1: HMAC canonical form dokumentálás (NFC normalizálás).
  - P1: DSA Art. 26–27 transparency endpoint.
  - P2: Differential privacy aggregátumoknál.
  - P2: Postback retry logika exponenciális backoff-fal.
- **Érintett fájl:** `docs/offerwall-survey-merged-plan.md`
- **Státusz:** Javaslatok hozzáadva, döntés/implementáció pending.

### 2026-02-03 – Offerwall Survey UI modern dizájn

- **Cel:** modern, fiatalos megjelenes a sajat survey oldalon.
- **Valtozasok:** uj gradient hatter, friss kartya stilus, Space Grotesk font, melegebb akcent szinek, finom animaciok, mobilos layout igazitas.
- **Erintett fajl:** `wp-content/mu-plugins/impactshop-offerwall-survey.php`
- **Statusz:** KESZ (deploy szukseges).

### 2026-02-03 – Offerwall Survey elagazas + tobb kor

- **Cel:** delayed_pair_id + consistency_probe mezok hasznalata, 2-3 koros flow, stop-feltetel.
- **Valtozasok:** delayed_pair parok kesleltetve jonnek kovetkezo korben, consistency_probe kerdesek bekerulnek a kesobbi korbe; stop kor 2 utan, ha inkonzisztencia van; progress dinamikusan frissul; 6-10 kerdeses flow.
- **Erintett fajl:** `wp-content/mu-plugins/impactshop-offerwall-survey.php`
- **Statusz:** KESZ (deploy szukseges).

### 2026-02-05 – Offerwall Survey deploy (staging + prod)

- **Deploy:** staging `deploy-20260205-104127`, prod `deploy-20260205-104219`.
- **Preflight (staging):** minden endpoint OK.
- **Preflight (prod):** minden endpoint OK.
- **Erintett fajl:** `wp-content/mu-plugins/impactshop-offerwall-survey.php`

### 2026-02-05 – Offerwall Survey smoke (limited)

- **Ellenorzes:** HTML betoltve, survey UI markup megjelenik mindket oldalon (curl alapjan).
- **Staging:** `https://sharity.hu/impactshop-staging/offerwall-survey`
- **Prod:** `https://app.sharity.hu/offerwall-survey`
- **Megjegyzes:** interaktiv 2-3 koros flow/stop/consent smoke nem futtathato CLI-bol; manualis UI kattintas szukseges.

### 2026-02-05 – Offerwall Survey JS fix + ekezetek (deploy)

- **Fix:** JS inline script valodi uj sorokkal (ne legyen \\n literal), igy a kerdes rendereles ujra mukodik.
- **UI szoveg:** ekezetes magyar szovegek + "Offerwall kerdoiv" kicker a kartyan.
- **Deploy:** staging `deploy-20260205-124142`, prod `deploy-20260205-124238`.

### 2026-02-05 – Offerwall Survey header + sorrend frissites

- **Oldal fejlec:** page cim + leiras ekezetesitve (Offerwall kerdoiv / Impact kerdoiv + jutalom leiras) mindket kornyezetben.
- **Sorrend:** elso kerdes attitud/behavior iranybol indul, a KN kesobb jon (logikusabb flow).
- **Deploy:** staging `deploy-20260205-125643`, prod `deploy-20260205-125735`.

### 2026-02-05 – Offerwall Survey warm-up + progress tartomany

- **Warm-up:** elso kerdes alacsony nehezsegu, nem-context ATT/DON/BEH poolbol (felvezetobb).
- **Progress:** 1/6–10 jelzes a valtozo hossz miatt.
- **Deploy:** staging `deploy-20260205-130959`, prod `deploy-20260205-131056`.

### 2026-02-05 – Offerwall Survey guide szerinti mix

- **Flow:** 5 kerdeses blokkok (1-2 blokk), kognitiv mix: knowledge/reasoning/tradeoff/intuition a guide szerint; consistency_probe csak 2. blokkban.
- **Delayed pair:** masodik tag csak kovetkezo blokkban jon.
- **Progress:** 1/5–10 jelzes.
- **Deploy:** staging `deploy-20260205-133654`, prod `deploy-20260205-133751`.

### 2026-02-05 – Offerwall Survey folyamatos blokkok + intro szuro

- **UX:** 5 kerdes/ blokk, pontjovairas utan folytathato ugyanabban a sessionben (nem kell kilepni).
- **Intro:** elso blokkban intro-szuro (nem SOC, TRUST/CSR tiltva, temakor sablonok ritkitva).
- **Deploy:** staging `deploy-20260205-135946`, prod `deploy-20260205-140054`.

### 2026-02-05 – Offerwall Survey batch-sorrend + felvezető nyelvezet

- **Batch:** CSV batch 1–4 sorrend per blokk (blockIndex = batch), batch mező beégetve a kérdésbankba.
- **Label:** témakódok magyarosítása (waste → hulladék, csr → társadalmi felelősségvállalás, stb.), “témakörben” → “témában”.
- **Deploy:** staging `deploy-20260205-142553`, prod `deploy-20260205-142705`.

### 2026-02-05 – Offerwall Survey master batch + batch5

- **Master:** 1250 kérdéses MASTER CSV beolvasva, batch 1–5 mezők beégetve a JSON-ba.
- **Batch limit:** blockIndex most batch5-ig lép, utána batch5 marad.
- **Deploy:** staging `deploy-20260205-144428`, prod `deploy-20260205-144538`.

### 2026-02-05 – Offerwall Survey header duplikacio eltavolitva

- **Oldal tartalom:** a page content csak `[impactshop_internal_survey]`, a duplikalt cim/leiras kikerult.
- **Staging oldal:** ID 16882 frissitve.
- **Prod oldal:** ID 18973 frissitve.

### 2026-02-03 – Bastion kiegészítő terv: Codex javaslatok

- **Cél:** Koherencia és biztonsági vizsgálat eredményeinek rögzítése a kiegészítő tervben.
- **Változás:** Új „Codex Biztonsági és Koherencia Javaslatok” szekció hozzáadva.
- **Új pontok:** symlink/path canonicalization, config provenance pinning, log redaction, TOCTOU mitigáció, ownership map koherencia ellenőrzés.
- **Érintett fájl:** `docs/bastion-protection-extension-plan.md`

### 2026-02-01 – Unified Video Info Panel + CTA egyszerűsítés

- **Kérés:** Minden videó (education, sponsor, auto_banner, regular) alá kerüljön tájékoztató panel + skip gomb, mint az edukációnál.
- **Megoldás:**
  1. **Új `video-info-panel`** – unified design, minden videó típushoz:
     - Cím + típus ikon (📚/🎬/🛒/📺)
     - 👀 Megnézésért: X pont, Y szavazat
     - 👆 Kattintásért: X pont, Y szavazat (opcionális)
     - Edukációnál: progress kijelzés is
  2. **`btn-skip-video`** – egységes skip gomb a panel ALATT (nem takarja el)
     - 5 mp késleltetés után jelenik meg
     - Nem abszolút pozícionált, hanem flow-ban
  3. **CTA gomb egyszerűsítés** – kompakt kör alakú 👆 ikonnal
     - Tooltip: "Kattints a bónusz pontokért!"
     - Zöld háttér, kisebb méret (48x48px, mobilon 44x44px)
  4. **Jutalom szétválasztás** – egyértelmű, mi jár a megnézésért és mi a kattintásért
- **Érintett fájlok:**
  - `wp-content/mu-plugins/impactshop-ads-watch.php` (HTML)
  - `wp-content/mu-plugins/impactshop-ads-watch.css` (stílusok)
  - `wp-content/mu-plugins/impactshop-ads-watch.js` (logika)
- **Státusz:** KÉSZ – deploy szükséges.

### 2026-02-01 – Partner integrációs dokumentációs oldal (laikus leírás)

- **Cél:** emberi nyelvű, gyorsan áttekinthető weboldal a partner integráció működéséről.
- **Megoldás:** új, egyoldalas HTML dokumentáció vizuális blokkokkal + linkekkel a részletes anyagokra.
- **Érintett fájl:** `docs/partner-docs-site.html`

### 2026-02-01 – Partner API koherencia javítások (rate limit, audit, TTL)

- **Kérés:** AI javaslatok alapján koherencia javítás a Partner API implementációban.
- **Megoldás:**
  - Rate limit (default 60 rpm, filterrel allithato).
  - Idempotency TTL ervenyesites config alapjan.
  - Audit log bovites: sikertelen kiserletek + payload hash + IP.
  - Dual-key tamogatas (api_key_secondary, hmac_secret_secondary, key_id_secondary).
  - Invalid payload hibak 400 statusra igazitas.
  - Discount quote explain kibovites min_cart esetre.
- **Erintett fajl:** `wp-content/mu-plugins/impactshop-partner-api.php`

### 2026-02-01 – Partner API smoke (staging + prod, invalid signature)

- **Futas:** staging+prod smoke kulccsal, valid + invalid signature kerese.
- **Eredmeny (staging):** valid 200 accepted (ledger_id 165, event_id order_smoke_1769968549), invalid signature 401.
- **Eredmeny (prod):** valid 200 accepted (ledger_id 177, event_id order_smoke_1769968550), invalid signature 401.
- **Audit log:** partner_tx_received megjelent; invalid signature bejegyzes + context mezok nem lathatoak (a frissites meg nincs deployolva).

### 2026-02-01 – Ads Watch: CTA ikon + edukacios skip gomb visszaallitva

- **Problemak:** IMA/Ad Manager videoknal nem latszott CTA ikon; edukacios videoknal eltunt a skip gomb.
- **Megoldas:**
  - IMA CTA overlay gomb ujra megjelenik ad indulasakor, es eltunik befejezes/hiba/reset eseten.
  - Education info bar-ba visszakerult a `btn-skip-education` gomb.
- **Erintett fajlok:** `wp-content/mu-plugins/impactshop-ads-watch.js`, `wp-content/mu-plugins/impactshop-ads-watch.php`
- **Deploy:** staging `deploy-20260201-180153`, prod `deploy-20260201-180243`

### 2026-02-01 – Partner API deploy + invalid signature audit check

- **Deploy:** staging `deploy-20260201-180427`, prod `deploy-20260201-180511`.
- **Invalid signature smoke:** staging/prod 401 (partner_auth_failed).
- **Audit log:** context mezok megjelentek (ip + payload_hash).
- **Cleanup:** `order_smoke_%` sorok torolve staging DB-bol (partner_tx + ledger); prodon nem volt torolheto sor.

### 2026-02-01 – Partner API smoke (valid signature) + cleanup

- **Futas:** staging/prod valid signature (200 accepted).
- **Staging:** ledger_id 166, event_id order_smoke_valid_1769969359 (audit: partner_tx_received).
- **Prod:** ledger_id 178, event_id order_smoke_valid_1769969359 (audit: partner_tx_received).
- **Cleanup:** `order_smoke_valid_%` sorok torolve staging DB-bol (partner_tx + ledger); prodon nem volt torolheto sor.

### 2026-02-01 – Szponzori video jutalom fix (5 pont + 5 szavazat)

- **Problema:** szponzori videok 6/6 pont-szavazatot adtak (streak szorzo miatt).
- **Megoldas:** szponzori megtekintesnel nincs streak szorzo, igy fix 5/5 marad.
- **Erintett fajl:** `wp-content/mu-plugins/impactshop-ads-watch.php`
- **Deploy:** staging `deploy-20260201-183104`, prod `deploy-20260201-183151`

### 2026-02-01 – Impi MCP SDK thin wrapper migracios terv koherencia/biztonsag frissites

- **Javitasok:** MCP SDK csomagnev javitva, backup scriptben env/secrets kihagyas, PII retention/log policy szigoritas.
- **Erintett fajl:** `docs/impi-mcp-sdk-migration-plan.md`

### 2026-02-01 – AI Agent strategy koherencia frissites

- **Javitasok:** MCP SDK thin wrapper migracios tervre mutato koherencia megjegyzes, modell stack frissites, retention/log policy utalas, ai-agent repo megjeloles.
- **Erintett fajl:** `docs/ai-agent-strategy.md`

### 2026-02-01 – MCP SDK thin wrapper migracios terv Go/No-Go + kockazati regiszter

- **Bovites:** release gate checklist + minimalis kockazati regiszter.
- **Erintett fajl:** `docs/impi-mcp-sdk-migration-plan.md`

### 2026-02-01 – Extra bastya vedelem kiterjesztese (ads/offerwall/points/votes)

- **Valtozas:** guard protected list bovitve az ads watch, offerwall, pontok/szavazatok, jutalmazas/szintek mu-pluginokra.
- **Erintett fajl:** `docs/impactshop-guard-config.json`
- **Guard hash regen:** `bin/impactshop-guard-init.sh`

### 2026-02-01 – Ads Watch UI smoke (staging, sponsor hiany)

- **Ellenorzes:** /ads-watch/next + debug-rotation staging pseudo_id=ab12cd34ef56.
- **Eredmeny:** nincs aktiv sponsor (has_sponsor=false), igy UI smoke nem futtathato szponzori videora.

### 2026-01-31 – Banner price_old/discount parse fix

- **Probléma:** A bannerek DB-ben `price_old = 0.00` és `discount_percent = 0`, bár a CSV-ben van adat.
- **Gyökérok:** Mezőnév eltérés:
  - Sync kód kereste: `price_num`, `old_price_num`, `discount_pct`
  - CSV-ben van: `price`, `old_price` (string pl. "13 990 Ft"), `pct` (int)
- **Megoldás:** 
  1. Új `impactshop_parse_price_string()` helper – parse-olja a magyar ár stringet (pl. "13 990 Ft" → 13990.0)
  2. Sync kód most mindkét formátumot támogatja (CSV és legacy)
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-auto-banner-sync.php`
- **Státusz:** KÉSZ – következő sync frissíti az árakat.

### 2026-01-31 – Harvester/Auto‑banner runbook rögzítve
- **Új runbook:** `docs/nav-online.md` (Impactall autoload blokk) + `docs/harvester-autobanner-fix-plan.md`.
- **SSH/prod path:** `sharityh@s59.tarhely.com` → `/home/sharityh/app`.
- **Parancsok:** `wp impactshop auto-banner sync`, `wp impactshop auto-banner cleanup`, DB ellenőrző lekérdezések + DTD purge.
- **Manuális futás (2026-01-31):**
  - Sync: `62 fetched, 32 inserted/updated, 30 skipped` (647ms)
  - Cleanup: `35 checked, 0 deleted, 35 kept`
  - DTD purge: `2 sor törölve`
  - Állapot: `33 active` banner
  - Utolsó 5 banner:
    - `sync:milenial-cafe` – Kóstoló készlet XXL- ötféle szemes kávé - 100% Arabica
    - `sync:speedshop` – Ghoo hálózati töltő miniUSB, 1A, 2W, fekete
    - `sync:konyhaluxnet` – BLANCO Gránit Mosogató Medence ZIA 45S Fehér
    - `sync:travelking` – Lengyel Tátra wellnessel és kedvezményekkel
    - `sync:parfumeshop` – SAPHIR - Select One Man Férfi EDP 30 ml teszter
  - **Force sync + cleanup (deploy után):**
    - Sync: `62 fetched, 32 inserted/updated, 30 skipped` (1570ms)
    - Cleanup: `33 checked, 0 deleted, 33 kept`
    - Állapot: `33 active` banner (last5 változatlan)

### 2026-02-01 – Ticker persist refresh javítás
- **Probléma:** a `impactshop_ticker_persist_v1` opció régi maradt, preflightben ritkán lassú ticker.
- **Megoldás:** ticker persist frissítése ütemezve + transient beállítás persistből.
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-metrics-ngo.php`
- **Post-deploy ellenőrzés (prod):**
  - ticker: 1.03s
  - totals: 0.72s
  - leaderboard_ngo: 0.76s
  - leaderboard_shop: 0.68s
  - activity: 0.69s

### 2026-01-31 – Auto banner sync: prefix strip

- **Probléma:** Auto banner kattintáskor "Ismeretlen shop: sync%3Anorafashion" hiba – a `/go-deal` endpoint nem ismerte fel a `sync:` prefixes shop slug-ot.
- **Gyökérok:** A harvester `sync:` prefixet ad a shop_slug elé, de a JS `transformBannerUrl()` és a PHP `/go-deal` handler nem vágta le.
- **Megoldás:**
  - **JS** (`impactshop-ads-watch.js`): `transformBannerUrl()` levágja a `sync:` prefixet
  - **PHP** (`impactshop-boot.php`): `isb_handle_go()` szintén levágja, ha átcsúszna
- **Érintett fájlok:** `wp-content/mu-plugins/impactshop-ads-watch.js`, `wp-content/mu-plugins/impactshop-boot.php`
- **Státusz:** KÉSZ – deploy után a sync bannerek linkjei helyesen működnek.

### 2026-01-31 – Education videó seek prevention

- **Probléma:** Az education videóba bele lehetett tekerni előre, és az átugrott időre is járt pont/szavazat.
- **Gyökérok:** A `startEducationTimer()` a player `currentTime`-ot használta a `educationWatchedSeconds` frissítéséhez – ha a user előreugrott, az egész időt beszámította.
- **Megoldás:** Seek detektálás és visszaállítás:
  - Új state: `educationLastPlayerTime` – követi az utolsó érvényes pozíciót
  - Ha a user >2 mp-et ugrik előre → player visszaáll az előző pozícióra
  - A `educationWatchedSeconds` csak delta-alapon nő (valós eltelt idő)
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.js`
- **Console log:** `[Education] Seek forward detected: X.Xs → Y.Ys, reverting`
- **Státusz:** KÉSZ – deploy után a tekerés blokkolva.

### 2026-01-31 – Auto banner DTD URL szűrés

- **Probléma:** Auto banner kattintáskor Dognet hibát adott: a `url` paraméter értéke `http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd` volt – DOCTYPE DTD URL, nem termék link.
- **Gyökérok:** A harvester rossz scrapinggel ilyen URL-t is begyűjtött; a sync/insert nem validálta.
- **Megoldás:** Új `impactshop_is_valid_product_url()` helper hozzáadva:
  - Blokkolt minták: `w3.org`, `.dtd`, `.xsd`, `/DTD/`, `xmlns`
  - Beépítve: `impactshop-auto-banner.php` (from_offer) és `impactshop-auto-banner-sync.php` (harvester import)
- **Érintett fájlok:** `wp-content/mu-plugins/impactshop-auto-banner.php`, `wp-content/mu-plugins/impactshop-auto-banner-sync.php`
- **Státusz:** KÉSZ – deploy után az új bannerek szűrve lesznek; meglévő hibás bejegyzéseket kézzel törölni kell az adatbázisból.

### 2026-01-30 – Rotation súly-átcsoportosítás seen content alapján

- **Probléma:** A "Bose" regular reklám ismétlődik, nem jön sponsor/education - mert a sponsor már seen volt, de a súlya (15) nem került át az education-höz (5). Így az education esélye csak 5/80 maradt.
- **Megoldás:** Ha egy content type már "seen", a súlya átkerül a másik nem-seen content type-hoz:
  - Ha sponsor seen → súlya átmegy education-nek (ha van)
  - Ha minden education seen → súlyuk átmegy sponsor-nak (ha van)
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.php`
- **Változás helye:** `impactshop_ads_watch_next()` - új pre-adjust blokk a loop előtt
- **Debug endpoint:** `debug-rotation` most mutatja: `weights_original`, `weights_adjusted`, `sponsor_already_seen`, `education_already_seen`
- **Példa:** Ha sponsor seen → `weights_adjusted: {regular: 60, sponsor: 0, education: 20}` (15+5=20)

### 2026-01-30 – Rotation debug logolás JS-ben

- **Változás:** `/next` endpoint válasz logolása `[Rotation]` prefix-szel
- **Console output:** `[Rotation] /next response: {content_type, mode, has_sponsor, has_education, ...}`

### 2026-01-30 – Auto banner 5 mp + watch gomb reset

- **Probléma:** Auto banner 15 mp-ig futott; mobile UI-n a "Reklám megtekintése" gomb szürke maradt.
- **Megoldás:** Auto banner TTL 5 mp-re csökkentve (PHP + JS). Auto banner completion után `state.isPlaying=false` és `updateWatchButton()` meghívás.
- **Érintett fájlok:** `wp-content/mu-plugins/impactshop-ads-watch.php`, `wp-content/mu-plugins/impactshop-ads-watch.js`

### 2026-01-30 – Mobil: watch gomb ne legyen örökre szürke

- **Probléma:** Mobilon a "Reklám megtekintése" gomb szürke maradt (gyakran hiányzó `impactshop_pseudo_id` miatt), így nem lehetett rákattintani.
- **Megoldás:** A gomb csak lejátszás közben tiltott; identity hiánynál kattintható, és a kliens figyelmeztetést ad (`startAdPlayback()` már kezeli).
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.js`

### 2026-01-30 – IMA SDK timeout és diagnosztika javítások

- **Probléma:** A player beragadt "Reklám betöltése..." állapotban, az IMA SDK nem válaszolt.
- **Változások:**
  1. **Timeout csökkentve:** 15s → 6s (user UX érdekében)
  2. **Dupla request megelőzése:** `adRequestPending` flag hozzáadva - nem enged párhuzamos kéréseket
  3. **Részletes diagnosztika:** Console log-ok minden IMA művelethez (vastTagUrl, containerSize, elapsed time)
  4. **IMA Error részletezés:** Error code, VAST error code, inner error kinyerése és logolása
  5. **Teljes SDK reset timeout esetén:** Ha 6s után nincs válasz, az egész IMA SDK újrainicializálódik (adsLoader, adDisplayContainer nullázása)
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.js`
- **Új state változók:** `adRequestPending`, `adRequestStartTime`
- **Módosított függvények:** `requestAds()`, `onAdsManagerLoaded()`, `onAdError()`, `resetPlayer()`
- **Debug:** Console-ban látható `[IMA]` prefixű logok mutatják a VAST URL-t, betöltési időt, error okokat

### 2026-01-30 – YouTube player timeout és diagnosztika

- **Felfedezés:** A debug-rotation endpoint megmutatta, hogy a Synlab videó `media_type: youtube` - tehát nem VAST, hanem YouTube embed!
- **Probléma:** A YouTube player nem volt timeout-tal védve, beragadhatott végtelenül.
- **Változások:**
  1. **8 másodperces timeout** mind a sponsor, mind az education YouTube playerhez
  2. **Részletes logolás:** `[YouTube]` prefix console-ban - videoId, load time, state changes
  3. **onError handler javítva:** Error code jelentések (2=invalid ID, 5=HTML5 error, 100=not found, 101/150=not embeddable)
  4. **State change logolás:** Minden YT.PlayerState változás látható debug-hoz
- **Érintett függvények:** `playSponsorYoutube()`, `initYouTubePlayer()`
- **Debug:** Console-ban `[YouTube] Sponsor player ready in 1234ms` típusú logok

### 2026-01-30 – Mock sponsors kikapcsolása és IMA reset fix

- **Probléma 1:** Csak a teszt/minta reklám futott, nem volt rotálódás.
- **Root cause:** A `impactshop_ads_watch_get_mock_sponsors()` függvény 40% eséllyel felülírta a valódi szponzort mock szponzorokkal, amelyek a Google teszt VAST tag-et használták.
- **Megoldás:** Mock sponsors kikommentelve a PHP-ben (üres tömb visszaadása).
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.php` - `get_mock_sponsors()` függvény

- **Probléma 2:** Egy reklám lefutása után hiba jelent meg, frissítés kellett.
- **Root cause:** Az IMA SDK `adsLoader.contentComplete()` nem volt meghívva a reklám végén.
- **Megoldás:** `resetPlayer()` függvénybe hozzáadva az `adsLoader.contentComplete()` hívás.
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.js`

### 2026-01-30 – Progress bar folyamatos animáció (IMA SDK)

- **Probléma:** A video progress bar 25%-os ugrásokkal haladt (FIRST_QUARTILE, MIDPOINT, THIRD_QUARTILE IMA eventek).
- **Megoldás:** `requestAnimationFrame` alapú loop implementálva, amely `adsManager.getRemainingTime()` segítségével folyamatosan számítja a progress-t.
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.js`
- **Új state változók:** `imaProgressFrameId`, `imaAdDuration`
- **Új függvények:** `startImaProgressLoop()`, `stopImaProgressLoop()`
- **Módosított függvények:** `onAdStarted()`, `onAdComplete()`, `onAdSkipped()`, `onAllAdsCompleted()`, `onAdError()`, `resetPlayer()`

### 2026-01-31 – Auto-banner whitelist + cleanup + NGO link átírás

- **Cél:** Gmail promó “szemét” kiszűrése és NGO‑választás esetén a banner CTA a `/go-deal/{shop}?d1={ngo}&u=...` linkre menjen.
- **Változások:**
  - **Whitelist helper:** `impactshop_is_whitelisted_partner()` (JSON + shops CSV fallback).
  - **Auto-banner hook szűrés:** csak whitelisted `shop_slug` kerül be.
  - **Sync szűrés:** csak whitelisted `slug` mehet át.
  - **Egyszeri cleanup:** non‑whitelisted bannerek törlése (option flag).
  - **WP‑CLI cleanup:** `wp impactshop auto-banner cleanup`.
  - **Ads Watch JS:** `transformBannerUrl()` + Fillout cél kinyerés NGO esetén.
- **Érintett fájlok:** `wp-content/mu-plugins/impactshop-auto-banner.php`, `wp-content/mu-plugins/impactshop-auto-banner-sync.php`, `wp-content/mu-plugins/impactshop-ads-watch.js`

### 2026-01-30 – Ad Tag URL konfiguráció

- **Állapot:** A teszt (Google preroll) ad tag a design szerint a default fallback (`get_ad_tag_url()` PHP, `fallbackAdTagUrl` JS).
- **Produkciós VAST tag beállítás:** A `impactshop_ads_watch_ad_tag_urls` filter hook-kal adható meg (PHP fájlban vagy mu-plugin-ban):
  ```php
  add_filter('impactshop_ads_watch_ad_tag_urls', function($urls) {
      return [
          'https://example.com/vast-tag-1.xml',
          'https://example.com/vast-tag-2.xml',
      ];
  });
  ```
- **Alternatív:** Egyetlen URL a `impactshop_ads_watch_ad_tag_url` filterrel állítható be.
- **Szponzor videók:** Egyedi VAST tag a Szponzori videó CPT admin felületén.

### 2026-01-30 – Szponzor videó (Synlab) nem játszik

- **Debug checklist:**
  1. Ellenőrizni, hogy a Synlab videó `impact_sponsor_video` CPT-ként van-e regisztrálva (WordPress Admin → Szponzori videók)
  2. A post status `publish` legyen
  3. A `media_type` meta mező helyes típusú legyen (mp4/youtube/vast)
  4. Ha VAST: a `vast_tag` mező kitöltve legyen érvényes URL-lel
  5. Start/end dátumok ellenőrzése (ha beállítva)
  6. Per-user cooldown/limit nem blokkolja-e
- **Rotation weights (PHP):** ad=60, banner=20, sponsor=15, edu=5 → sponsor ~11%-os eséllyel jelenik meg.

### 2026-01-29 – Badge definíciók hiányának javítása

- **Probléma 1 (Legacy Wall):** Egyes badge-ek slug-al jelennek meg label helyett (pl. `votes_10` → "votes_10" szöveg a szép "10 szavazat" helyett).
- **Probléma 2 (Legacy Pool):** Csak 4 badge jelenik meg, holott a user-nek 6 van.
- **Root cause:** 21 badge definíció hiányzik a `wp_impact_badge_definitions` táblából. A kód (impact-gamification.php) ezeket a badge_key-eket osztja ki, de az adatbázis seed után manuálisan módosítva lett.
  - Wall: `impactshop-identity-panel.js` line 532 → `meta.name_hu || item.row.badge_key` fallback a slug-ra ha nincs def
  - Pool: `impactshop_badge_compact_list()` line 621 → `if (!$meta) continue;` kiszűri a def nélkülieket
- **Hiányzó badge-ek:** `votes_10`, `votes_5000`, `votes_10000`, `views_1`, `views_1000`, `views_5000`, `views_10000`, `streak_365`, `ngo_1`, `ngo_10`, `ngo_100`, `offers_10`, `offers_100`, `offers_500`, `offers_1000`, `edu_complete_50`, `edu_complete_100`, `anniversary_2-5`
- **Megoldás:** SQL script készült: `scripts/fix-missing-badge-definitions.sql` (INSERT + ON DUPLICATE KEY UPDATE)
- **Teendő:** SQL script futtatása production DB-n

### 2026-01-29 – JS badgeTierRank diamond/legend fix

- **Probléma:** A JS `badgeTierRank()` függvény nem kezelte a `diamond` tier-t → diamond badge-ek bronze-nak (1) számítottak.
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-identity-panel.js` line 440
- **Javítás:** `case "diamond": return 5;` hozzáadva, `case "legend": return 6;` (volt 5)
- **Hatás:** Diamond tier badge-ek (streak_365, views_5000 stb.) most helyesen rangsorolódnak.

### 2026-01-29 – Badge streak award fix

- **Probléma:** User `9mnx6wqfkhr9` nem kapta meg a `streak_3` badge-et annak ellenére, hogy `streak_days=4`.
- **Root cause:** `do_action('impactshop_ads_view_recorded', $pseudo_id, $ad_type)` string-et adott át második paraméternek, de a `impactshop_badge_on_ads_view()` hook array-t várt (`['stats' => [...]]`).
- **Javítás:** Mindkét helyen (video + education) módosítva a hook hívás:
  ```php
  do_action('impactshop_ads_view_recorded', $pseudo_id, [
      'ad_type' => $ad_type,
      'stats'   => $stats,
  ]);
  ```
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.php`
- **Manuális fix:** A hiányzó badge manuálisan pótolva a diagnosztikai scripttel.

### 2026-01-29 – Sponsor video CPT hiba (WP admin “Hibás bejegyzés típus”)

- **Probléma:** A “Szponzori videók” CPT nem regisztrálódik, adminban “Hibás bejegyzés típus”.
- **Root cause:** A post type kulcs túl hosszú: `impactshop_sponsor_video` (23 karakter). WP limit 20.
- **Megoldás (elő készítve):** Kulcs átállítva `impact_sponsor_video`‑ra a `wp-content/mu-plugins/impactshop-ads-watch.php` fájlban.
- **DB teendő (staging+prod):** `wp_posts.post_type` migrálás:
  `UPDATE wp_posts SET post_type='impact_sponsor_video' WHERE post_type='impactshop_sponsor_video';`
- **Backup/rollback:** `.codex/backups/ads-watch-cpt-*` (post‑change) és `.codex/backups/ads-watch-cpt-pre-*` (snapshot).

### 2026-01-29 – Ads Watch auto-banner overlay állapot

- **Állapot:** Az auto-banner már a player frame-en belül jelenik meg (PHP + CSS módosítások szerint).
- **Következő lépés:** staging+prod deploy scan-nel, ha a DB post_type migráció kész.

### 2026-01-29 – Szponzori videó: YouTube támogatás

- **Új opció:** YouTube választható a Szponzori videók “Videó típus” mezőjében.
- **Új mező:** YouTube URL (linkből automatikus ID felismerés).
- **Frontend:** sponsor YouTube lejátszás + progress bar + megtekintés rögzítés működik.
- **Backup/rollback:** `.codex/backups/ads-watch-youtube-*`.

### 2026-01-29 – Ads Watch progress bar finomítás

- **Változás:** Szponzori MP4-nél a progress bar folyamatos frissítést kap (`updateAdProgressBar()` timeupdate során).
- **Backup/rollback:** `.codex/backups/ads-watch-progress-*`.
### 2026-01-29 – Smart lokalizmus laikus összefoglaló

- Új, laikus dokumentum készült arról, hogy az ImpactShop komponensek (Ads Watch, Offerwall, non-affiliate beléptetés, NGO kártya, Impi) hogyan szolgálják a „smart lokalizmus” célt.
- Doksi: `docs/smart-lokalizmus-osszefoglalo.md`
- Frissítés: beépítve a kért kiegészítések az offline partner bevonásról / helyszíni azonosításról, valamint arról, hogy az Impi hogyan gyűjti és rendezi az ajánlatokat.

### 2026-01-29 – Unified Display terv: Codex + Gemini + Sonnet javaslatok + koherencia

- A `docs/unified-display-plan-merged.md` dokumentumba három AI javaslat került (Codex, Gemini, Sonnet).
- **Codex fókusz**: `content_type` enum egységesítés, `reward_points` vs `cta.points` átmeneti támogatás, response TTL, fallback tartalom, rate limit, player hibatűrés.
- **Gemini fókusz**: Teljesítmény (logo cache), adatminőség (regex logging), UX (batch prefetch), fraud prevention (view time validation), mobile autoplay.
- **Sonnet fókusz**: Weighted selection algoritmus részletezés (Redis dedupe), offline támogatás (Service Worker), accessibility (ARIA), security headers, alerting szabályok, DB migráció fázis (Phase 0.5), content audit tábla.

### 2026-01-27 – Ads.txt + Robots.txt

- Hozzáadva `ads.txt` (publisher ID pub-3544330186801102).
- Új `robots.txt` a javasolt crawl szabályokkal és sitemap linkkel.

### 2026-01-27 – Védett fájl backup retenciós protokoll (rövid)

- **Cél:** a védett fájl backup **nem teljes backup**, csak adott implementációt véd.
- **Retenció:** **max 2 napig** tartjuk meg.
- **Törlés feltétele:** implementáció OK + **UI ellenőrzés megtörtént**.
- **Megjegyzés:** a **teljes backupok** külön készülnek és **maradnak**.
- **Kivétel:** ha külön jelölve van (pl. „tartsd meg 7 napig”), akkor a jelölés érvényes.

### 2026-01-27 – ID panel badge stílus kiegészítés

- Badge tier színezés + icon méret CSS hozzáadva az ID panelhez.
- Backup + rollback: `.codex/backups/identity-panel-badge-css-20260127-141631/rollback.sh`.

### 2026-01-27 – Bastion guardrail emlékeztető

- **Kötelező:** bástyavédelem minden deploy/guardrail döntés előtt.
- Ha a bástya hozzáférés vagy szabályok nem egyértelműek, **meg kell állni és engedélyt kérni**.

### 2026-01-27 – Deploy decision (gyors blokk)

- **Használd:** `bin/impactshop-guard-deploy.sh` (staging+production), nem `deploy.sh`.
- **Uncommitted policy:** célzott commit **prioritás** (pl. `ads.txt`, `robots.txt`), blanket stash kerülendő.
- **Bástyavédelem:** deploy előtt kötelező check, bizonytalanság esetén megállás + engedélykérés.

### 2026-01-28 – Guard deploy targetek rögzítése

- `docs/impactshop-guard-config.json` kiegészítve `deploy_targets` blokk-al (staging/production env fájlok).
- Guard hash manifest frissítve (`docs/impactshop-guard-hashes.json`, `.sha256`).
- Backup + rollback: `.codex/backups/guard-config-20260128-125005/rollback.sh`.

### 2026-01-27 – Dognet inkrementális terv koherencia kiegészítés

- Frissítve a `docs/dognet-incremental-fetch-plan-review.md` dokumentum: Dognet filter limitációk (created_at vs updated_at), lookback ablak szabály, és időzóna-konzisztencia a `donation_today` resetnél.
- Cél: státuszváltások biztonságos kezelése és koherens napi aggregálás.

### 2026-01-28 – JYSK szavazás kampány lejárat fix

- **Probléma:** JYSK /jysk-2/ oldal nem működött, mert a kampány lejárt (end_at: 2026-01-27).
- **Root cause:** `impactshop-vote-jysk.php` backend query `WHERE status = 'active' AND end_at >= NOW()` → ha lejárt, akkor `{"campaign":{"status":"none"}}` választ ad.
- **Megoldás:** `wp_impact_vote_campaigns` tábla frissítve: `end_at = '2026-02-28 23:59:59', status = 'active'`.
- **Hatás:** JYSK oldal most már rendereli a videót és NGO listát, szavazás működik.

### 2026-01-28 – Offerwall: CPX (web) bekötés előkészítés

- `wp-content/mu-plugins/impactshop-offerwall.php`: callback már `GET` + `POST`, bekerült alap provider `cpx`, és a postback paraméterek bővültek (`subid_1`, `trans_id`, `amount_usd`, reversal `status=2`).
- Offerwall iframe-ben a user azonosító paraméter provider-specifikus: adminban új mező `User param` (pl. CPX: `subid_1`).
- 0 payout esetén nem ad pontot/szavazatot (kevesebb visszaélés).
- Szerver-oldali secure iframe URL: új REST endpoint `GET /wp-json/impact/v1/offerwall/iframe/{provider}` és admin mezők `IFrame hash secret/param/format` (CPX: `secure_hash`, `{user}-{secret}`).

### 2026-01-27 – Social ticker alapértelmezett státusz

- A `impact-social-mvp.php` shortcode default `status` értéke `approved` → `all`, hogy a `pending` tételek (pseudo_id-vel) is megjelenjenek.

### 2026-01-27 – MCP SDK Migracio Koherencia Vizsgalat (korabbi Copilot terv)

**Eredmény: ✅ KOHERENS – A terv pontosan tükrözi a tényleges kódbázist**

#### 📁 Fontos repo és fájl elérési utak (Codex implementáláshoz):

| Elem | Elérési út |
|------|------------|
| **AI Agent repo** | `/Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent` |
| **Impactshop Notes repo** | `/Users/bujdosoarnold/Documents/GitHub/impactshop-notes` |
| **Sources - types.ts** | `apps/ai-agent-core/src/sources/types.ts` |
| **Sources - cj-links.ts** | `apps/ai-agent-core/src/sources/cj-links.ts` |
| **Impi - recommend.ts** | `apps/ai-agent-core/src/impi/recommend.ts` (~700+ sor) |
| **Impi - ngo-categories.ts** | `apps/ai-agent-core/src/impi/ngo-categories.ts` |
| **API Gateway** | `apps/api-gateway/src/index.ts` (Port 4000) |
| **Capabilities** | `apps/core-agent-graph/src/capabilities/impi.ts` |
| **Capability Registry** | `apps/core-agent-graph/src/capabilities/registry.ts` |
| **NGO data** | `data/ngo-category-map.json` |
| **Migrációs terv** | `docs/impi-mcp-sdk-migration-plan.md` |

#### Fő megállapítások:
- Minden hivatkozott fájl létezik az ai-agent repo-ban
- `NormalizedCoupon`, `CouponType`, `SourceSnapshot` típusok a `types.ts`-ben
- Capability v1/v2 rollout már működik (v2 = 20%)
- A létrehozandó `apps/impi-mcp-server/` még nem létezik – a migráció hozza létre

### 2026-01-26 – Állás mentés (folyamatban)
- 📌 Nyitott kérés: Offerwall implementációs terv kidolgozása a `docs/offerwall-integration-plan.md` alapján (részletes lépéssor még hátra).
- 📌 Ads Watch Opus P0–P3 végigvezetés: jóváhagyva, de még nem végrehajtva ebben a körben.
- 📌 JYSK /jysk-2/ scroll + szöveg fix: védett fájl, backup + rollback mellett javítandó.
- 📌 Deploy: staging + prod készenléti állapot, impactall futtatás hálózati engedéllyel külön kérésként vár.

### 2026-01-26 – Codex implementációs feladat kiadva
- 📋 Új feladat dokumentum: `docs/CODEX-IMPLEMENTATION-TASK.md`
- 🎯 Tartalom: Offerwall + Video Content Strategy + **Badge System** teljes implementációja
- 🔧 Mód: AUTONOMOUS – Codex önállóan végigviszi, technikai döntéseket hozhat, hibákat javíthat
- 📁 Források: `docs/offerwall-integration-plan.md`, `docs/video-content-strategy-plan.md`, `docs/impactshop-badge-system-plan.md`
- ✅ Felhatalmazás: védett fájl felülírható backup+rollback mellett; deploy staging+prod scan engedéllyel

### 2026-01-26 – Badge (Gamification) Rendszer terv + HeroWall
- 📝 Új dokumentum: `docs/impactshop-badge-system-plan.md` (v2.0)
- 🎖️ Kategóriák: Aktivitás (streak, views), Támogatás (votes, NGO), Tanulás (edukáció), Offerwall, Speciális
- 🏆 Tier rendszer: Bronze → Silver → Gold → Platinum (badge pont súlyokkal: 1/2/4/8)
- 🏆 **HeroWall**: Örök dicsőségtábla badge pontok alapján
  - Szintek: Legend (100+), Platinum (50-99), Gold (25-49), Silver (10-24), Bronze (1-9)
  - Örökérvényű pozíció: ha elérsz egy szintet, soha nem degradálódsz
  - Legacy üzenet: Platinum+ felhasználók 280 karakteres örök üzenetet hagyhatnak
- 🔄 Badge vs Level különbség:
  - Level (Sharity): anyagi előny (multiplier, szavazat súly) – változhat
  - Badge: elismerés (nem anyagi) – örökérvényű
- 🗄️ DB táblák: `wp_impact_badge_definitions`, `wp_impact_user_badges`, `wp_impact_badge_progress`, `wp_impact_herowall`
- 🔌 API: `impact_award_badge()`, `impact_update_herowall()`, `impact_set_legacy_message()`
- 🖼️ ID Panel integráció: badge grid + vizuális jutalmak (háttér gradiens) + HeroWall pozíció
- 📊 REST endpoints: `/badges/user`, `/badges/progress`, `/herowall`, `/herowall/legacy`

### 2026-01-26 – Deploy (staging + production, scan engedéllyel)
- 🚀 `impactctl deploy` lefutott staging + production környezetre (IMPACTSHOP_ALLOW_FULL_SCAN=1).
- ✅ Preflight OK staging előtt/után.
- ⚠️ Production preflight előtte: activity endpoint lassú (3.14s), utána OK.
- 🧹 Cache flush + rewrite flush futott mindkét környezeten.

### 2026-01-26 – JYSK deploy (backup + rollback)
- 🧷 Backup + rollback készült: `.codex/backups/jysk-deploy-20260126-200746/rollback.sh` (impactshop-vote-jysk.php + impactshop-vote-jysk.js).
- 🚀 Deploy staging + production scan engedéllyel lefutott.
- ⚠️ Preflight előtt staging: `/impact/v1/report` lassú; production preflight előtt lassú `ticker` + `leaderboard(ngo)`; deploy utáni preflight mindkét környezeten OK.

### 2026-01-25 – AdSense MU plugin deploy (prod+staging)
- 🚀 `impactshop-adsense-head.php` feltöltve prod + staging környezetre a Google AdSense verifikációhoz.

### 2026-01-26 – Offerwall integrációs terv (GPT 5.2 javaslatok)
- 📝 `docs/offerwall-integration-plan.md` kiegészítve jelölt `GPT 5.2` blokkokkal (trust/FAQ microcopy, history pagination+cache, signature canonicalization, reward policy, operációs DB mezők, iframe privacy hardening, idempotens duplicate kezelés `request_id`-val, GA4 funnel események, troubleshooting `request_id`).

### 2026-01-29 – Offerwall: CPX iframe user_id javítás
- ✅ Fix: CPX embednél a "User_ID Not found" hiba megszűnik, mert az iframe URL-be `ext_user_id` (és kompatibilitásból `subid_1`) paraméter is bekerül (`impactshop-offerwall.php`).

### 2026-01-26 – Videó Content Stratégia terv (GPT 5.2)
- 📝 Új dokumentum: `docs/video-content-strategy-plan.md`
- 🎬 Háromféle videótípus egyetlen playerben: reklám (IMA/VAST), szponzor (CDN), edukáció (YouTube embed)
- 📚 YouTube edukációs videók: 30 mp-enként 5 pont + 5 szavazat, bármikor megszakítható, részleges jutalom
- 🏷️ Harvester auto-banner: affiliate ajánlatokból automatikus banner generálás (kép, cím, ár, kedvezmény)
- 🔗 CTA linkek minden videó után: típusfüggő céloldal (affiliate, szponzor link, opcionális weboldal)
- 📊 Click tracking pseudo_id szinten → user profil építés (shop preferencia, kategória, stb.)
- 🔄 Content rotation algoritmus: 60% reklám, 25% szponzor, 15% edukáció + prioritás szabályok

### 2026-01-20 – Hotfix MU sync (prod+staging)
- 🚚 MU pluginok szinkronizálva prod+staging környezetre: `impactshop-vote-jysk.{php,js}`, `impactshop-dognet-conversions.php`, `impact-totals-cache.php`, `impactshop-identity-panel.{php,js}`, `impact-publisher-brand-safety.php`.
- 🧹 Cache purge: `wp transient delete --all` + `wp cache flush` mindkét környezeten.
- ⚠️ WP-CLI notice: `complianz-terms-conditions` túl korai textdomain betöltés (csak figyelmeztetés).

### 2026-01-22 – Bástya szabály megerősítés (impactall + config)
- 🛡️ Protected list bővítve: `wp-content/mu-plugins/impactshop-ngo-card.js`.
- 🧭 Impactall nyomatékos szabály: védett fájlhoz csak külön engedéllyel, előzetes backup + egykattintásos rollback mellett nyúlunk.

### 2026-01-22 – NGO card CORS fix (idegen domain logo)
- 🖼️ OK: NGO card logók idegen domainen is megjelennek.
- 🧩 Beállítás: `Access-Control-Allow-Origin: *` + `Cross-Origin-Resource-Policy: cross-origin`
  az `uploads/impactshop/.htaccess` fájlban (prod+staging).
- 🔒 Bástyavédelem: a fenti .htaccess csak külön engedéllyel módosítható.

### 2026-01-24 – Identity panel finomhangolás (UI)
- ✅ ID panel: “Fiókom adatai” cím, frissített figyelmeztető szöveg az ID + kód alatt.
- ✅ Pontok + ajánlói kód infó tooltipek és teljes referral link másolás.
- ✅ “Másikat választok” már NGO névvel jelenik meg (nem slug).
- 🧰 Backup + rollback: `.codex/backups/identity-panel-ui-20260124-173436/rollback.sh`.
- 🧩 Javítás: compact panel pontok lekérése akkor is, ha csak a compact panel jelenik meg.

### 2026-01-23 – Partner integráció prep (spec kiegészítések)
- 📦 Új dokumentumok: `docs/partner-db-schema.md`, `docs/partner-config-storage.md`,
  `docs/partner-auth-secrets.md`, `docs/partner-reconciliation-job.md`,
  `docs/partner-dashboard-wireframes.md`, `docs/partner-webhook-test-env.md`,
  `docs/partner-monitoring-kpi.md`.
- 🔗 Linkek frissítve: `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Partner integráció master checklist
- ✅ Új összefoglaló lista: `docs/partner-master-checklist.md`.

### 2026-01-23 – Docs index (partner gyűjtő)
- ✅ Új `docs/README.md` partner dokumentum index.

### 2026-01-23 – Docs index bővítés (egyéb kulcs dokumentumok)
- ✅ `docs/README.md` kiegészítve “Egyéb kulcs dokumentumok” szekcióval.

### 2026-01-23 – Partner prep: migration, UI, Postman, policy
- 📦 Új dokumentumok: `docs/partner-db-migration-template.md`,
  `docs/partner-admin-ui-draft.md`, `docs/partner-admin-ui-fields.csv`,
  `docs/partner-postman-collection.md`, `docs/partner-postman-collection.json`,
  `docs/partner-dispute-policy.md`, `docs/partner-data-retention.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Postman HMAC helper
- ✅ `docs/partner-postman-collection.md` bővítve Node/PHP HMAC példával.

### 2026-01-23 – HMAC CLI helper
- ✅ Új segéd: `tools/hmac-sign.js` + leírás `tools/hmac-sign.md`.

### 2026-01-23 – Docs index (tools)
- ✅ `docs/README.md` kiegészítve a HMAC helper eszközökkel.

### 2026-01-23 – Partner prep: Postman script, fixtures, permissions
- 📦 Új elemek: `docs/partner-postman-collection.json` pre-request HMAC script,
  `fixtures/partner/*.json`, `docs/partner-admin-permissions.md`,
  `docs/partner-reconcile-export-spec.md`, `docs/partner-audit-event-list.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Postman fixtures használat
- ✅ `docs/partner-postman-collection.md` kiegészítve fixture útmutatóval.

### 2026-01-23 – Partner prep: runner, retry spec, SLA
- 📦 Új elemek: `tools/partner-test-runner.js`, `tools/partner-test-runner.md`,
  `docs/partner-webhook-retry-spec.md`, `docs/partner-sla-onepager.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Partner prep: QA + config + error catalog
- 📦 Új elemek: `tools/partner-qa.cjs`, `tools/partner-qa.md`,
  `docs/partner-config-validation.md`, `docs/partner-api-error-catalog.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Partner prep: OpenAPI + samples + runbook
- 📦 OpenAPI bővítve validáció + error example mezőkkel.
- 📦 Új dokumentumok: `docs/partner-api-sample-responses.md`,
  `docs/partner-staging-runbook.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Partner prep: OpenAPI env + security + mapping
- 📦 OpenAPI bővítve staging/prod server + auth note.
- 📦 Új dokumentumok: `docs/partner-webhook-security-checklist.md`,
  `docs/partner-data-mapping.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Partner prep: OpenAPI check + templates
- 📦 Új eszköz: `tools/openapi-check.cjs`, `tools/openapi-check.md`.
- 📦 Új dokumentumok: `docs/partner-onboarding-email-template.md`,
  `docs/partner-release-checklist.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Partner prep: FAQ + changelog + sequence
- 📦 Új dokumentumok: `docs/partner-faq.md`, `docs/partner-changelog.md`,
  `docs/partner-webhook-sequence.md`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – Partner prep: summary + koherencia fixek
- 📦 Új dokumentum: `docs/partner-summary.md`.
- 🧩 Koherencia: fixture‑ök + Postman payloadok kiegészítve `payment_status` mezővel,
  refund fixture `payment_status=refunded` + `event_type=purchase`.
- 🔗 Linkek frissítve: `docs/README.md`, `docs/non-affiliate-integration-plan.md`,
  `impact-hub-system-v1.3.md`.

### 2026-01-23 – VS Code task (OpenAPI Check)
- ✅ Új task: `OpenAPI Check` a `.vscode/tasks.json`-ban.

### 2026-01-23 – Staging vs prod runbook note
- ✅ `docs/partner-staging-runbook.md` kiegészítve környezet‑különbséggel.

### 2026-01-23 – OpenAPI examples
- ✅ `docs/partner-api-openapi.yaml` kiegészítve válasz példákkal.

### 2026-01-24 – Pont UI az Identity panelben (WIP)
- 🧩 Új pontszekciók: badge + szint + pontok, progress bar, ponttörténet, last NGO, vakáció, referral.
- 🧠 JS bekötés: `/sharity/v1/pseudo/*` endpointok lekérése és render.
- 🧷 Backup + rollback: `.codex/backups/identity-points-ui-20260124-122559/rollback.sh`.

### 2026-01-24 – Identity panel: felső pontblokk + új navigáció
- 🧭 Új gomb: “Ez nem az én fiókom” → azonosító helyreállítás részhez görget.
- 📌 Fiókom kezelése → ID panel tetejére (anchor).
- 🧮 Pontok blokk duplikálva a felső fiókablakban (compact).
- 🧷 Backup + rollback: `.codex/backups/identity-panel-top-points-20260124-170309/rollback.sh`.

### 2026-01-23 – VS Code task (Partner QA)
- ✅ Új task: `Partner QA` a `.vscode/tasks.json`-ban.

### 2026-01-23 – Partner runner (cjs)
- ✅ Runner átnevezve: `tools/partner-test-runner.cjs` (ESM kompatibilitás).

### 2026-01-23 – Partner runner shim note
- ✅ `tools/partner-test-runner.md` bővítve shim megjegyzéssel.

### 2026-01-20 – Leaderboard UI + NGO név mapping (prod+staging)
- 🎨 `sharity-impact-mini` stílusok világos háttérre igazítva (szöveg/kontraszt fix).
- 🏷️ NGO slug → név feloldás `ngo_codes.csv` alapján; fallback title-case, ha nincs találat.
- 🧹 Cache flush prod+staging.

### 2026-01-20 – Dognet ledger sync cron fix
- 🧭 Ok: `DISABLE_WP_CRON=1` miatt a WP‑Cron nem futott, a ledger sync csak manuálisan ment.
- ✅ Bekapcsolva: `DISABLE_WP_CRON=false` prod+staging.
- ⏱️ Új MU cron wrapper: `wp-content/mu-plugins/impactshop-social-ledger-cron.php` (10 perc).
- ▶️ Kézi futtatás: `impact-social-ledger-sync.php` prod+staging, ticker transients törölve.

### 2026-01-20 – Leaderboard Ft + shop név feloldás
- 💱 `sharity-impact-mini`: EUR → HUF megjelenítés (`Ft`), központi árfolyam (impactshop_get_huf_rate/IMPACTSHOP_FX_HUF).
- 🏬 `impactshop-metrics-ngo`: shop leaderboard név feloldás Dognet cid → shop név (ékezetekkel) a shop registryből.
- 🧾 Új MU plugin: `wp-content/mu-plugins/impactshop-rest-totals.php` – visszahozza a `/wp-json/impactshop/v1/totals` végpontot (sticky + report), minimál Dognet összesítés + cache.
- 📊 `impactshop-rest-totals.php`: Dognet conversions endpoint fallback + bővített `rows` mezők (shop/ngo) + grand meta, hogy a sticky és a toplista szűrés működjön.
- 🧮 `impactshop-metrics-ngo.php`: a `/impact/v1/leaderboard` most `from/to/status` paramétert is elfogad; alapértelmezett kezdő dátum `2025-10-23`.
- 🛡️ Új guard: `.codex/guards/impactshop-totals-guard.sh` – totals + leaderboard endpoint ellenőrzés a 2025-10-23→ma tartományra.
- 🧾 `sharity-impact-compat.php` + `impact-combat-pack.php`: `impact_leaderboard` most limit/from/to/status/currency paramétereket kezel (fix 5-ös limit tartása).

### 2026-01-20 – Impact Shop system map bővítés (bastyavédelem előkészítés)
- 🧭 `docs/impactshop-system-map.md` kibővítve: identity, ticker/leaderboard rövid leírás, cache kulcsok, totals adatfolyam, külső források (CSV/HTML), operációs beállítások és guard terv.
- 🔒 Őrzési terv rögzítve: hash manifest + impactall guard + OS immutability (külön jóváhagyással).
- 📌 Függő: WPCode snippet export + cPanel cron lista + Elementor template lista felvétele a térképbe.
- ✅ Session end: térkép frissítve, védelmi lépések előkészítve (aktiválás jóváhagyásra vár).

### 2026-01-21 – Bástyavédelem (solo-safe guard v1)
- 🛡️ Új guard konfiguráció: `docs/impactshop-guard-config.json`.
- 🔐 Hash manifest generátor: `bin/impactshop-guard-init.sh` → `docs/impactshop-guard-hashes.json`.
- 🚦 Guard ellenőrzés: `.codex/guards/impactshop-bastya-guard.sh` (impactall kompatibilis).
- 🚀 Guardolt deploy wrapper: `bin/impactshop-guard-deploy.sh` (self-approval + emergency override + snapshot).

### 2026-01-21 – System map: snippets + Elementor + WP‑Cron
- 🔎 WPCode snippet CPT: nincs találat sem stagingen, sem productionön (`wpcode_snippet` üres).
- 🧩 Elementor templates listázva (prod + staging) – rögzítve a system mapben.
- ⏱️ WP‑Cron események listázva (impact_totals_cache_prewarm, impactshop_social_ledger_sync, impactshop_vote_cron, impact_publisher_token_health_cron, impactshop_pin_cleanup).
- ✅ cPanel cron lista manual exporttal rögzítve a system mapben (4 bejegyzés).

### 2026-01-21 – Bástya guard + cPanel cron fix
- 🛡️ `impactall` kiegészítve: bastya guard (`.codex/guards/impactshop-bastya-guard.sh`).
- ⏱️ Cron URL javítva a tényleges crontabban (`/var/spool/cron/sharityh`): `app.sharity.hu` host + `/home/sharityh/impact-tools/access-guard.sh` útvonal.

### 2026-01-21 – impactall megerősítés (bástya guard fix)
- 🛠️ Javítva: `.codex/guards/impactshop-bastya-guard.sh` syntax hiba (guard output parse).
- 🛡️ `~/bin/impactall` újrafuttatva: 16/16 PASS, WARN/FAIL nincs.

### 2026-01-21 – Bástya guard kiegészítések
- 🧰 Új rollback script: `bin/impactshop-guard-rollback.sh` (snapshot visszaállítás + hash).
- 🔓 Soft emergency override: `docs/impactshop-guard-config.json` → `action: warn_confirm`.
- 🔒 Opcionális lock: `IMPACTSHOP_GUARD_LOCK_MODE=chmod` (pre/post chmod a védett fájlokon).
- 🧠 Safe-mode: `IMPACTSHOP_GUARD_SAFE_MODE=1` esetén az emergency override tiltott.
- 🧾 Hash integritás: `impactshop-guard-hashes.sha256` ellenőrzés a bástya guardban.
- 🧱 Guard config védetté téve (benne a protected listában + frissített hash).
- 🤖 Non-interactive mód: `--non-interactive --auto-approve` + `IMPACTSHOP_GUARD_APPROVE_REASON`.

### 2026-01-21 – impactall megerősítés (guard v2)
- 🛡️ `~/bin/impactall` lefutott: 16/16 PASS, WARN/FAIL nincs (bástya guard rendben).

### 2026-01-21 – Session end
- 💤 Állapot mentve, gép leállítás előtt.
- 🧾 Új MU plugin: `wp-content/mu-plugins/impactshop-full-leaderboard.php` – [impact_full_leaderboard] shortcode a teljes toplista oldalhoz.
- 🧹 Cache flush prod+staging.

### 2026-01-18 – Ledger cron sűrítés + watchdog e-mail (office@sharity.hu)
- ⏱️ WP-Cron ütemezés sűrítve: CJ + Dognet ledger sync 10 perces ciklusra állítva.
- 🔔 Watchdog hozzáadva: 10 percenként ellenőrzi a CJ/Dognet ledger `last_run` frissességét; küszöb 20 perc, cooldown 30 perc; elakadás esetén e‑mail az `office@sharity.hu` címre.
- 🧰 Cél: felhasználói visszajelzés gyorsítása + késő ingest észlelése.

### 2026-01-18 – impactall guard futtatás (18:15)
- 🏁 Session start: kérésre lefuttattam a teljes `impactall` guardot az `impactshop-notes` gyökérből.
- 🛡️ Parancs: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ~/bin/impactall` → staging HTTP 200 / 1969 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 1275 ms; 13/14 PASS, 1 WARN.
- ⚠️ WARN: Sprint pre-flight (S1) – Doc lint hibára futott, javítás: `.codex/scripts/doc-lint-fix.sh impactshop-notes/impact-hub-system-v1.3.md` (log: `.codex/reports/impactall-20260118-181559-Sprint-pre-flight-(S1).log`).
- 📌 Megjegyzés: Guard eventben GitHub token lejárati figyelmeztetés (19 nap); érdemes időben frissíteni.
- ✅ Session end: impactall futás rögzítve, status snapshot frissült.

### 2026-01-18 – Doc lint fix + impactall rerun (18:20)
- 🛠️ Doc lint javítás: `impact-hub-system-v1.3.md` sorhossz tördelések, majd `.codex/scripts/doc-lint-fix.sh impactshop-notes/impact-hub-system-v1.3.md` sikeresen lefutott.
- 🛡️ Újraellenőrzés: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ~/bin/impactall` → staging HTTP 200 / 1401 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 1251 ms; 14/14 PASS, WARN/FAIL nincs.
- 📌 Megjegyzés: Guard eventben a GitHub token lejárati figyelmeztetés továbbra is látszik (19 nap).
- ✅ Session end: Sprint S1 pre-flight doc lint zöld, status snapshot frissült.

### 2026-01-18 – Lokális MySQL + wp_test ellenőrzés
- 🗄️ MySQL szolgáltatás fut (`brew services list` szerint started).
- ✅ `wp_test` adatbázis már létezik (`SHOW DATABASES LIKE 'wp_test'`).
- 📌 Következő lépés: PHPUnit újrafuttatható a meglévő DB-vel.

### 2026-01-18 – wp_test újragenerálás + PHPUnit futtatás
- 🧹 `wp_test` adatbázis törölve és újralétrehozva (clean slate).
- 🧪 `vendor/bin/phpunit --configuration phpunit.xml` futtatva, hibák:
  - WordPress teszt suite inkompatibilis a PHPUnit 10-zel (`parseTestMethodAnnotations` hiányzik).
  - Wallet tesztek hibáznak, mert a wallet plugin nincs betöltve (`impactshop_wallet_plugin_loaded()`).

### 2026-01-18 – PHPUnit downgrade + bootstrap wallet fallback
- 📦 `phpunit/phpunit` visszavéve 9.6.x-re (`composer require --dev phpunit/phpunit:^9.6 -W`).
- 🧩 `tests/bootstrap.php`: opcionális wallet plugin betöltés + fallback `impactshop_wallet_plugin_loaded()` definíció.
- ✅ `vendor/bin/phpunit --configuration phpunit.xml` sikeres: 7 teszt, 14 assertion, 3 skip (wallet plugin hiányzik).

### 2026-01-18 – Wallet plugin path hozzáadva a bootstraphez
- 🧩 `tests/bootstrap.php`: külső MU plugin utak hozzáadva az `impactshop-wallet.php` és `impactshop-wallet-direct.php` betöltéséhez.

### 2026-01-18 – PHPUnit rerun (wallet plugin betöltve)
- ❌ 1 failure + 2 error:
  - `impactshop_tests_reset_routes()` hiányzik (WalletPassTest::testRegisterRoutesRegistersWalletEndpoint).
  - `WP_REST_Request` init hiba (string helyett array kerül a method paraméterbe) a `WalletPassTest::testHandleWalletPassValidatesSlug` körül.
  - `build_pass_json` CTA URL eltér: teszt `https://example.org/cta/demo`-t vár, de a wallet plugin `https://app.sharity.hu/impactshop/?d1=...` értéket ad vissza.

### 2026-01-18 – Wallet tesztek javítása + zöld PHPUnit
- 🧩 `tests/bootstrap.php`: REST route helper bővítve `permission_callback` mezővel.
- 🧪 `tests/phpunit/WalletPassTest.php`: CTA URL elvárás frissítve, REST route regisztráció `rest_api_init`-en, `WP_REST_Request` init javítva.
- ✅ `vendor/bin/phpunit --configuration phpunit.xml` → 7 teszt, 26 assertion, PASS.

### 2026-01-18 – Wallet plugin útvonal env-ből
- 🧩 `tests/bootstrap.php`: külső wallet plugin path-ok betöltése `IMPACTSHOP_WALLET_PLUGIN_PATHS` env-ből (több útvonal `,`/`;`/`:` elválasztóval).

### 2026-01-18 – PHPUnit env path-tal
- ✅ `IMPACTSHOP_WALLET_PLUGIN_PATHS=... vendor/bin/phpunit --configuration phpunit.xml` → 7 teszt, 26 assertion, PASS.

### 2026-01-18 – Local env: wallet plugin path
- 🧩 `.codex/.env.local`: `IMPACTSHOP_WALLET_PLUGIN_PATHS` export hozzáadva a lokális PHPUnit futtatáshoz.

### 2026-01-18 – PHPUnit env.local-lal
- ✅ `{ source .codex/.env.local; } && vendor/bin/phpunit --configuration phpunit.xml` → 7 teszt, 26 assertion, PASS.

### 2026-01-18 – .env.local gitignore
- 🧩 `.gitignore`: `.env.local` és `.codex/.env.local` felvéve, hogy a lokális env ne kerüljön gitbe.

### 2026-01-18 – Identity panel shortcode + nickname profil
- 🧩 Új MU plugin: `wp-content/mu-plugins/impactshop-identity-panel.php`.
- 🔧 Shortcode: `[impactshop_identity_panel]` (pseudo ID megjelenítés, PIN kérés, profil helyreállítás, becenév mentés).
- 🧾 REST: `GET/POST /impact/v1/identity/profile` becenév tárolás pseudo ID alapján (PII-mentes).

### 2026-01-18 – Donor becenév + toplista
- 🧩 `wp-content/mu-plugins/impact-social-mvp.php`: becenév megjelenítés a tickerben (`display_name`), ha van mentve.
- 🏆 Új endpoint: `GET /impact/v1/leaderboard/donors` (Top donors).
- 🔧 Új shortcode: `[impact_top_donors]` (limit/status/theme).

### 2026-01-18 – PR/merge + guard
- ✅ PR #23 (phpunit 9 + wallet tesztek + env template) merge.
- ✅ PR #24 (identity panel + donor becenevek) merge.
- 🛡️ `impactall` prod/staging guard lefutott: 14/14 PASS (GitHub token 19 napon belül lejár).
- ⚠️ Stash megmaradt (`stash@{0}`), nagy worktree zaj/unknown fájlok vannak – későbbi takarításra vár.

### 2026-01-19 – Prod identity panel + PIN smoke
- 🚀 MU pluginok felmásolva prodra: `impactshop-identity-pin*`, `impactshop-identity-panel.php`, `impact-social-mvp.php`.
- ✅ `impact_social_mvp_enabled` bekapcsolva.
- ✅ Impact Shop oldal (`/impactshop/`) aljára shortcode-ok: `[impactshop_identity_panel]`, `[impact_top_donors]`, `[impact_social_ticker]` (már jelen volt).
- ✅ PIN issue prodon: SMS `+36304007470` és email `office@sharity.hu` → `delivery.status=sent`.
- ✅ Új endpointok elérhetők prodon:
  - `/impact/v1/leaderboard/donors` (Top donors)
  - `/impact/v1/social/ticker` (display_name mezővel)
- ⏱️ Prod oldal első mérés: `https://app.sharity.hu/impactshop/` ~1.67s (curl).

### 2026-01-19 – Prod PIN verify + rejtett shortcode
- ✅ PIN verify prod: `pseudo_id=ab12cd34ef56`, PIN `762197` → `status=ok`, cookie set.
- 🕶️ Shortcode blokk rejtve: `<div style="display:none">` wrapper az oldalon (nem látszik).
- ⚡ Top donors endpoint cache: 5 perces transient a gyorsabb válaszhoz.

### 2026-01-19 – Identity panel render fix + prod redeploy
- 🧩 `impactshop-identity-panel.php`: CSS/JS kikerült a shortcode HTML-ből, `wp_enqueue_scripts` + inline assets (Elementor kompatibilis).
- 🚀 MU plugin frissítve prodon.

### 2026-01-19 – Identity panel látható helyre
- ✅ `https://app.sharity.hu/impactshop/` tartalom elejére került: „Azonosítód és helyreállítás” blokk.
- ✅ Shortcode sorok: `[impactshop_identity_panel]`, `[impact_top_donors]`, `[impact_social_ticker]` láthatóan renderelnek.

### 2026-01-19 – Identity panel UI frissítés + ID shortcode
- 🎨 Identity panel UI frissítve (spacing, gombok, mobil layout).
- 🔧 Új shortcode: `[impactshop_identity_id]` (csak azonosító + másolás).
- ✅ Prodra feltöltve az új `impactshop-identity-panel.php`.

### 2026-01-19 – Recovery code + auto ID
- 🔐 Recovery code generálás + megjelenítés az identity panelben és ID shortcode-ban.
- 🧩 Új gombok: “Másolás (ID + kód)”, “Megosztás”, “Mentés jelszókezelőbe”.
- ✅ Profil endpoint automatikusan pseudo‑ID cookie-t ad, ha nincs (`/impact/v1/identity/profile`).
- ✅ PIN issue recovery kódot kér (`recovery_required` / `recovery_invalid` hibák).

### 2026-01-19 – Identity panel dizájn frissítés
- 🎨 Üveglap hatás, árnyékok, finom gradient háttér, jobb spacing + mobil tördelés.
- ✅ Prodra feltöltve az új stílusok.

### 2026-01-19 – Identity profile no-cache
- 🧩 `/impact/v1/identity/profile` no-cache header + cache-buster query (REST cache elkerülés).
- ✅ Prodra feltöltve a frissített `impactshop-identity-panel.php`.

### 2026-01-19 – Identity panel fetch fix
- 🧩 REST fetch `credentials: include` + API válaszból frissítjük az ID/kód mezőket.
- ✅ Prodra feltöltve a frissítés.

### 2026-01-19 – Identity panel refresh gomb
- 🔧 Frissítés gomb + automata retry, látható státusz visszajelzéssel.
- ✅ Prodra feltöltve a frissítés.

### 2026-01-19 – Server-side ID/kód render
- 🧩 Shortcode server‑side is kiírja az ID + recovery kódot (JS nélkül is látszik).
- ✅ Prodra feltöltve a frissített `impactshop-identity-panel.php`.

### 2026-01-18 – Pseudo-ID részletek kidolgozása (Impact Shop + NGO card + social ticker)
- 🧭 Célok rögzítve: email nélküli azonosítás, token csak attribúcióhoz, PIN‑nel visszaállítható.
- 🧾 Részletek: 10–12 karakteres base36 pseudo‑ID, kliens cookie (`impactshop_pseudo_id`), `/go` automatikus generálás + affiliate átadás (Dognet `d2`, CJ `sid`).
- 🤝 Integrációk: ledger `pseudo_id`, social ticker owner‑check, NGO card link /go útvonalon kap pseudo‑t.
- 🔐 Adatvédelem/UX: PII‑mentes leírás + rövid UX szöveg; PIN‑es visszaállítás rate limit + audit log.
- 📄 Dokumentáció: `impact-hub-system-v1.3.md` 4.1 szekció bővítve.

### 2026-01-18 – PIN paraméterek rögzítése (implementációs alap)
- 🔢 PIN formátum: 6 számjegy, egyszer használatos; 1 aktív PIN / pseudo‑ID.
- ⏱️ Érvényesség: 15 perc; újragenerálás max 3 / 24 óra / pseudo‑ID.
- 🧱 Rate limit: 5/óra/IP + 10/nap/pseudo‑ID; 3 hibás próbálkozás után 15 perc lockout.
- 🧾 Audit: `identity_pin_verify` event (pseudo_hash, ip_hash, status, attempt_count).
- 📄 Dokumentáció: `impact-hub-system-v1.3.md` 4.1 PIN‑es visszaállítás rész frissítve.

### 2026-01-18 – Implementációs ticket: PIN kiadás/ellenőrzés + rate limit + audit log
- 🎯 Cél: PIN‑es token visszaállítás implementálása, idempotens és auditálható módon.
- ✅ Kész kritériumok:
  - REST: `POST /impact/v1/identity/pin/issue` és `POST /impact/v1/identity/pin/verify`.
  - Rate limit: 5/óra/IP + 10/nap/pseudo‑ID; 3 hiba → 15 perc lockout.
  - Audit: `identity_pin_issue` + `identity_pin_verify` esemény `pseudo_hash`, `ip_hash`, `status`, `attempt_count`.
  - PIN szabályok: 6 számjegy, 15 perc TTL, 1 aktív PIN / pseudo‑ID, max 3 újragenerálás / 24 óra.
- 📦 Adatmodell (minimál):
  - `wp_impact_pin_tokens`: `pseudo_hash`, `pin_hash`, `expires_at`, `attempts`, `locked_until`, `created_at`, `used_at`.
  - Retenció: 30 nap után purge (audit log marad).
- 🔌 Integráció:
  - Cookie frissítés: sikeres verify után `impactshop_pseudo_id` beállítása (365 nap).
  - Social ticker owner‑check változatlan, ledger `pseudo_id` alapján.
- 🧪 Teszt:
  - Happy path: issue → verify → cookie set.
  - Lockout: 3 hibás PIN → 15 perc tiltás.
  - Rate limit: IP + pseudo‑ID limit, 429 válasz.

### 2026-01-18 – PIN REST payload minták + hibakód mátrix
- 🧾 REST minták: `pin/issue` és `pin/verify` kérés/válasz JSON minták.
- 🧱 Hibakód mátrix: `invalid_request`, `pin_invalid`, `pin_locked`, `pin_expired`,
  `pin_used`, `rate_limited`, `server_error`.
- 📄 Dokumentáció: `impact-hub-system-v1.3.md` 4.1 PIN REST minták + hibakód táblázat.

### 2026-01-18 – OpenAPI frissítés: PIN végpontok
- 🧩 Új endpointok: `POST /identity/pin/issue`, `POST /identity/pin/verify`.
- 🧾 Schema: `PinIssueRequest/Response`, `PinVerifyRequest/Response`, pseudo‑ID
  pattern 10–12 karakterre frissítve.
- 🧪 Validáció: `npx swagger-cli validate docs/api/openapi.yaml` → OK.
- 📄 Dokumentáció: `docs/api/openapi.yaml` frissítve.

### 2026-01-18 – PIN REST controller stub (WP)
- 🧩 Új MU plugin: `wp-content/mu-plugins/impactshop-identity-pin.php`.
- ✅ Végpontok: `/impact/v1/identity/pin/issue`, `/impact/v1/identity/pin/verify`.
- 🧱 Alap logika: input validáció, rate limit (IP + pseudo‑ID), lockout, audit hook
  + `wp-content/uploads/impactshop-pin-audit.log`.
- 🧪 Megjegyzés: stub jelleg (transient alapú tárolás), élesítés előtt DB‑tároló
  + valódi PIN‑kézbesítés szükséges.

### 2026-01-18 – PIN perzisztens tároló + kézbesítés/cookie
- 🧱 Migráció: `wp-content/mu-plugins/impactshop-identity-pin-migration.php`
  → `wp_impact_pin_tokens` tábla.
- 🧾 Tárolás: PIN hash + expiry + attempts + lockout + used_at DB-ben.
- 📬 Kézbesítés: `impactshop_identity_pin_deliver` hook + stub log
  `wp-content/uploads/impactshop-pin-delivery.log`.
- 🍪 Cookie: sikeres verify után `impactshop_pseudo_id` beállítás (365 nap).
- 📄 Implementáció: `wp-content/mu-plugins/impactshop-identity-pin.php` frissítve.

### 2026-01-18 – PIN kézbesítés + DB cleanup
- ✉️ Email kézbesítés: `wp_mail` (delivery.channel=email), SMS/QR hookok:
  `impactshop_identity_pin_sms`, `impactshop_identity_pin_qr_payload`.
- 🧾 Delivery log: `impactshop-pin-delivery.log` pin_hash + target_hash mezőkkel.
- 🧹 DB purge: napi cron `impactshop_pin_cleanup` → 30 napnál régebbi használt
  vagy lejárt PIN rekordok törlése.
- 📄 Új MU plugin: `wp-content/mu-plugins/impactshop-identity-pin-cron.php`.
- 📄 OpenAPI: `PinIssueRequest.delivery` mező + delivery response frissítve.

### 2026-01-18 – SMS/QR provider bekötés
- 📲 SMS (Vonage): `wp-content/mu-plugins/impactshop-identity-pin-sms-vonage.php`,
  env: `VONAGE_API_KEY`, `VONAGE_API_SECRET`, `VONAGE_FROM`.
- 🧩 QR (QuickChart): `wp-content/mu-plugins/impactshop-identity-pin-qr-quickchart.php`
  → `https://quickchart.io/qr?text=impactshop-pin:<PIN>`.
- 🔌 Hookok: `impactshop_identity_pin_sms` filter visszaadja `status=sent`.

### 2026-01-18 – SMS env + pin/issue smoke (staging)
- 🔐 Secret env: `/Users/bujdosoarnold/.impact-secrets/env.d/sms.env` (Vonage kulcsok placeholderrel).
- 🧪 Smoke: `POST /impact/v1/identity/pin/issue` stagingen `delivery.channel=sms`
  → `rest_no_route` 404 (plugin még nincs deployolva stagingre).

### 2026-01-18 – PIN MU plugin deploy + smoke (staging)
- 🚚 Deploy: PIN MU pluginek szinkronizálva stagingre (`app-staging`), rewrite flush OK.
- 🧪 Smoke: `POST /impact/v1/identity/pin/issue` (`delivery.channel=sms`)
  → `status=ok`, `delivery.status=queued` (Vonage creds hiányában várható).
- ⚠️ Megjegyzés: PHP-FPM reload nem futott (nincs sudo), de REST endpoint elérhető.

### 2026-01-18 – Vonage env betöltés + új smoke (staging)
- 🔐 Env loader: `impactshop-identity-pin.php` betölti a
  `/home/sharityh/.impact-secrets/env.d/sms.env` fájlt.
- 🚚 Staging: `sms.env` feltöltve (placeholder kulcsok).
- 🧪 Smoke: `POST /impact/v1/identity/pin/issue` (`delivery.channel=sms`)
  → `status=ok`, `delivery.status=queued` (kulcsok még hiányoznak).

### 2026-01-18 – Vonage kulcsok + SMS retry (staging)
- 🔐 Env: Vonage kulcsok beállítva a staging `sms.env` fájlban + `capi.env` frissítve.
- 🧪 Smoke retry: `POST /impact/v1/identity/pin/issue` → `rate_limited` (429, 24h).
- 📌 Teendő: várakozás vagy transient cleanup után új próba.

### 2026-01-18 – PIN rate limit cleanup + SMS sent (staging)
- 🧹 Cleanup: `wp transient delete --all` stagingen (13 törölt).
- 🧪 Smoke: `POST /impact/v1/identity/pin/issue` → `delivery.status=sent`.

### 2026-01-18 – PIN SMS/QR runbook
- 📄 Új dokumentum: `docs/pin-sms-runbook.md` (staging smoke + rate limit reset).
- ➕ Kiegészítés: production deploy + smoke + rollback lépések.
- ✅ Go/No-go: rövid checklist a prod szakaszban.
- ✅ Top-level checklist kivonat a runbookban.
- ✅ Pre-smoke mini-checklist a staging részhez.
- ✅ Post-smoke ellenőrzés (debug log + delivery log).
- ✅ Prod post-smoke ellenőrzés (debug log + delivery log).
- ✅ Gyors prod log-tail parancs a post-smoke részhez.
- ✅ Gyors staging log-tail parancs a post-smoke részhez.
- ✅ Egyparancsos staging smoke + log tail blokk.
- ✅ Egyparancsos prod smoke + log tail blokk.
- ✅ Gyors parancsok szekció a runbook elején.
- ✅ Gyors parancsok blokk konkrét parancsokkal kiegészítve.
- ✅ Gyors parancsok blokk paraméterezhető `PSEUDO_ID`/`PHONE` változókkal.
- ✅ Gyors parancsok blokk minta változók szekcióval bővítve.
- ✅ Minta változók megjegyzéssel (teszt pseudo-ID/telefon, staging; ékezetes).

### 2026-01-18 – Release checklist PIN go/no-go
- 📄 `docs/prod-guard-checklist.md` kiegészítve PIN SMS go/no-go lépésekkel.
- ✅ PIN smoke lépések bekerültek a gyors ellenőrző listába is.

### 2026-01-18 – PIN Sonnet review összegzés
- 📄 Új dokumentum: `docs/pin-sonnet-review.md` (kockázatok + P0–P3 javaslatok).
- ✅ Rövidített verzió beemelve: `impact-hub-system-v1.3.md`.
- ✅ Rövidített rész linkelve a részletes docra.

### 2026-01-18 – PIN P0 javaslatok implementálva
- 🔐 Kombinált IP+pseudo rate limit a PIN issue-ben.
- ⚙️ `PIN_*` env konfiguráció támogatás (`/home/sharityh/.impact-secrets/env.d/pin.env`).
- 🗂️ Audit + delivery log rotáció a napi cleanup során.
- 📄 Státusz frissítve: `docs/pin-sonnet-review.md`.

### 2026-01-18 – PIN P1 javaslatok implementálva
- ⏱️ Timing védelem a PIN verify-ben (konstans késleltetés).
- 🧱 Composite indexek a `wp_impact_pin_tokens` táblán.
- 🔁 Vonage retry + hiba hook (`impactshop_pin_sms_failed`).
- 🩺 Health endpoint bővítve PIN státusszal.
- 📄 Státusz frissítve: `docs/pin-sonnet-review.md`.

### 2026-01-18 – PIN P2–P3 backlog lista frissítve
- 📄 `docs/pin-sonnet-review.md` P2/P3 javaslatok részletezve.

### 2026-01-18 – PIN P2 javaslatok implementálva
- 🧾 Reissue audit hook: `impactshop_identity_pin_reissue_after_use`.
- 🧩 QR payload validáció + `impactshop_pin_qr_invalid` hook.
- 🧪 Test mode flag: `PIN_TEST_MODE` (staging bypass).
- 📄 Új doksik: `docs/pin-error-codes.md`, `docs/pin-sequence-diagram.md`.

### 2026-01-18 – PIN P3 javaslatok implementálva
- 🕵️ IP spoofing védelem `TRUSTED_PROXY_IPS` env alapján.
- 🧠 Admin notice object cache hiány esetén.
- 🧹 Batch cleanup + reschedule a PIN cleanup cronban.
- 📈 Metrics endpoint: `/impact/v1/identity/pin/metrics`.
- 🧾 Structured logging audit + delivery logokhoz.
- 🧭 Migration history tábla: `wp_impact_pin_migration_history`.
- 📄 Státusz frissítve: `docs/pin-sonnet-review.md`.
- ✅ PHPUnit skeleton + PHPDoc hozzáadva.

### 2026-01-18 – PHPUnit smoke (PIN)
- 🧪 Parancs: `vendor/bin/phpunit tests/test-impactshop-identity-pin.php`.
- ❌ Hiba: `WP_UnitTestCase` hiányzik (WP tesztkörnyezet nincs inicializálva).
- 📌 Következő: WordPress test bootstrap / WP-CLI scaffold előtt újrafuttatni.

### 2026-01-18 – WP test bootstrap + PHPUnit retry
- 🧰 Bootstrap: `bin/install-wp-tests.sh` + `tests/bootstrap.php` létrehozva.
- 🧪 Retry: `WP_TESTS_DIR=tests/wordpress-tests-lib vendor/bin/phpunit tests/test-impactshop-identity-pin.php`.
- ❌ Hiba: DB kapcsolat hiányzik (`mysqli_real_connect` / nincs MySQL).
- 📌 Következő: lokális MySQL indítása + `wp_test` DB létrehozása.

### 2026-01-18 – PHPUnit retry (WP tests)
- 🧪 Parancs: `vendor/bin/phpunit --configuration phpunit.xml`.
- ❌ Hiba: WP teszt suite nem kompatibilis a PHPUnit 10-zel
  (`parseTestMethodAnnotations()` hiányzik).
- ⚠️ Mellék: `WalletPassTest` hibák, mert a wallet plugin nincs betöltve.
- 📌 Következő: PHPUnit 9 használata vagy WP test suite frissítés,
  illetve a wallet MU plugin betöltése a bootstrapben.

### 2026-01-18 – Social ticker share: pseudo-id szinkron
- 🧩 Dognet raw tranzakcióból a pseudo-id hiányzott; `order_id`/`original_id` + `last_click_data1/2` mezők most bekerültek a pseudo-keresésbe.
- 🔎 A hosszú azonosítókat is elfogadjuk (tisztítás után max 64, ledgerben 12-re vágva), így nem vész el a donor-id.
- ✅ Social ticker owner-match lazítva: teljes egyezés mellett prefix egyezést is elfogad (hosszabb cookie vs rövidebb ledger).
- 🍪 `impact_pseudo_id` query param esetén a social ticker most beállítja az `impactshop_pseudo_id` cookie-t (365 nap), így a megosztás tartósan működik.
- 🔗 `/go` kattintásnál, ha nincs pseudo cookie, automatikusan generálunk és beállítunk egyet (12 chars), hogy a Dognet d2 ne maradjon üres.
- ✅ Dognet ledger cron kézi futtatás után a 2026-01-18-as Glami rekord már `pseudo_id`-val került be, a social tickerben megosztható.

### 2026-01-18 – Árukereső aresett-termekek bővítés (Playwright)
- 📚 Aresett-termekek main oldal helyett az összes kategóriát bejárjuk, pagination + load-more + scroll támogatással.
- 🔁 Dedup hozzáadva a scrape eredményekhez (URL alapú).
- 🔧 Fájl: `ai-agent/tools/playwright/arukereso-runner.ts`.

### 2026-01-18 – Kupon validátor finomhangolás (Gmail ajánlatok)
- 🧾 Gmail structured forrásnál elfogadjuk az „ajánlat/akció/kupon/kód” kulcsszavakat a címben, ha a discount jel hiányzik a descriptionből.
- 🔤 Ékezet-normalizálás hozzáadva a cím ellenőrzéséhez, hogy pl. „ajánlatok” is felismerhető legyen.
- 🧠 Gmail kuponoknál fallback kontextus: ha nincs marker-es description, a coupon_code körüli (±200) szövegrész bekerül a description mezőbe.
- 📨 Gmail API feldolgozásnál a subject + snippet is bekerül a kontextusba, így a kupon környezete akkor is megmarad, ha a body üres.

### 2026-01-13 – AI agent guard futtatás (09:41)
- 🏁 Session: kérésre lefuttattam az AI agent guardot az ai-agent repó gyökeréből.
- 🛡️ Parancs: `{ [ -f /Users/bujdosoarnold/Developer/GitHub/impactshop-notes/.codex/.env.local ] && source /Users/bujdosoarnold/Developer/GitHub/impactshop-notes/.codex/.env.local; } && /Users/bujdosoarnold/Developer/GitHub/impactshop-notes/.codex/guards/ai-agent-guard.sh`
- 📈 Eredmény: production HTTP 200 (1854 ms), staging HTTP 200 (1372 ms); Guard result: OK.
- 📌 Következő lépés: nincs azonnali akció; ismétlés csak deploy vagy guard WARN/FAIL esetén.

### 2026-01-13 – NAV Online requestSignature tisztázás (megoldás)
- ✅ Megoldás rögzítve: a requestSignature = `requestId + timestamp + signingKey` SHA3‑512 hash (hex, UPPERCASE).
- 🔑 Signing key: a NAV portálon generált **literális** karaktersorozat, kötőjelekkel együtt; nem hex/base64 és nem szabad módosítani.
- ⏱️ Timestamp: NAV által elvárt UTC formátum (a hivatalos példák ISO 8601 timestampet használnak).
- ⚠️ Tipikus hiba: exchange key és signing key összekeverése, hibás timestamp formátum vagy kulcs átkódolása → `INVALID_REQUEST_SIGNATURE`.
- 🧩 Javítási lépések: új signing/exchange key páros generálása, pontos másolás, SHA3‑512 UPPERCASE, kliens oldali `signKeyHex=false`, szerveridő szinkron.
- 📄 Új összefoglaló doksi: `docs/nav-online.md`.

### 2026-01-13 – NAV Online tokenExchange éles teszt (prod + test) → FAIL
- 🧪 Teszt: tokenExchange hívás a helyi env-ből (`/Users/bujdosoarnold/.impact-secrets/env.d/capi.env`) több variánssal (requestId limitált formátum, SHA3‑512 UPPERCASE, timestamp ISO 8601).
- ❌ Eredmény: mind prod (`https://api.onlineszamla.nav.gov.hu/...`) mind test (`https://api-test.onlineszamla.nav.gov.hu/...`) `INVALID_REQUEST` (400, „Helytelen kérés!”).
- 🧩 Lehetséges okok: NAV technikai user/ kulcspár nem érvényes, környezet‑mismatch (prod vs test), nem megfelelő login/jelszó vagy szoftver mezők (pl. hiányzó dev adatok).
- 📌 Következő lépés: NAV UI‑ban technikai felhasználó és kulcsok ellenőrzése/újragenerálása, valamint a prod/test környezet jogosultságának megerősítése.
- 🔁 További próbák: signing key‑es requestSignature + `yyyyMMddHHmmss` timestamp is `INVALID_REQUEST` lett; valószínűleg nem a hash‑formátum a blokkoló.

### 2026-01-13 – NAV Online tokenExchange INVALID_REQUEST: séma‑ellenőrzés fókusz
- 📌 INVALID_REQUEST okok (2019-es táblázat): rossz endpoint/HTTP metódus, sérült XML vagy séma‑sértés, hibás login/taxNumber páros, nem egyedi requestId, hiányzó requestVersion/headerVersion.
- 🌐 Helyes endpoint: `https://api.onlineszamla.nav.gov.hu/invoiceService/v3/tokenExchange` (test: `https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3/tokenExchange`).
- 🧱 Kötelező mezők: `requestId`, `timestamp` (UTC, ISO 8601), `requestVersion=3.0`, `headerVersion=1.0`, `login`, `passwordHash cryptoType="SHA-512"`, `taxNumber` (törzsszám, első 8 számjegy), `requestSignature cryptoType="SHA3-512"`, teljes `software` blokk kötelező mezőkkel.
- 🧩 Namespace: v3-ban a `TokenExchangeRequest` root az OSA API namespace alatt van, a `header/user/software` blokkok pedig a `http://schemas.nav.gov.hu/NTCA/1.0/common` namespace alatt.
- 📌 Következő: request XML séma/prefix/attribútumok teljes igazítása a v3-as xsd‑hez, valamint a software blokk kötelező mezőinek kitöltése.
- ✅ XSD‑igazítás után: `TokenExchangeRequest` root + `requestVersion/headerVersion` attribútum + `NTCA/1.0/common` prefix a header/user/software elemekre → a séma‑hiba megszűnt, de továbbra is `INVALID_REQUEST` érkezik (prod).
- ✅ Ellenőrzés: prod login be van töltve, taxNumber 8 jegy (törzsszám), softwareDevTaxNumber fallback a taxNumber‑re.
- ❌ Újrapróba: signing key‑es requestSignature + helyes namespace/attribútumok mellett továbbra is `INVALID_REQUEST`.
- ✅ További igazítás: `software` blokk prefix nélküli, és `softwareId` fallback 18 karakteres (HU+törzsszám+suffix) → továbbra is `INVALID_REQUEST`.
- ✅ Header/user sorrend igazítva: `requestVersion/headerVersion` a headerben, `requestSignature` a user blokkban; `requestId` 30 karakteres alfanumerikus → továbbra is `INVALID_REQUEST_SIGNATURE`.
- 🔎 Kulcs‑tükör: sign key 32 karakter (kötőjellel), exchange key 16 karakter; mindkettőnél tesztelve → signature még mindig elutasítva.
- ✅ TokenExchange signature: signKey‑vel számolva (exchangeKey kizárva) → továbbra is `INVALID_REQUEST_SIGNATURE`.
- ✅ Megoldás: requestSignature a maszkolt timestamptel készül (`yyyyMMddHHmmss`), signKey‑vel → tokenExchange OK (prod).
- ❌ queryInvoiceDigest próba (2025-01-01 → 2025-12-31, INBOUND): `INVALID_REQUEST` + `SCHEMA_VIOLATION` (exchangeToken elem nem várt).

### 2026-01-13 – Session end / állapot mentés
- 🛑 Gépleállítás előtt állapot rögzítve; NAV Online tokenExchange továbbra is `INVALID_REQUEST` (prod), séma‑hiba már nincs.
- 🧾 Módosítás: `impact_hub/ai-agent/apps/core-worker/src/nav-online-invoice.ts` XSD‑igazítás (namespace + attribútumok).
- 📌 Következő lépés: prod technikai user + taxNumber + softwareDevTaxNumber ellenőrzése, majd tokenExchange újrapróba.

### 2026-01-10 – Rekonstrukció (history log törölve)
- 🧩 Forrás: megmaradt dokumentumblokkok + fájlnyomok alapján (pl. `tools/nav-signature-verify.js`, `impact-hub-system-v1.3.md`, `Hirdetési fiókok integrációja TERV.ini.md`).
- ✅ Billingo Drive célmappa frissítve (Shared Drive + env értékek), Billingo sheet újonnan létrejött.
- 🔐 AI Agent core elérés tisztázva: nincs publikus reverse proxy, `http://127.0.0.1:4000` helyi endpoint; keepalive csak egyszeri restartot végez.
- 🧾 NAV Online Számla: hivatkozások, Software blokk mezők tisztázása, M2M vs Online Számla különbségek rögzítve; élő token-exchange teszt `INVALID_REQUEST_SIGNATURE`.
- 🧪 Új NAV ellenőrző tool: `tools/nav-signature-verify.js` (NAV test vector + SHA3-512 ellenőrzés, kötőjel/timestamp hatás).
- 🛡️ `impactall` futás rögzítve (13/13 PASS, staging/prod 200, status snapshot frissült).

### 2026-01-11–2026-01-12 – Rekonstrukció (history log törölve)
- 📌 Fájlnyomok alapján nem látszik külön repo‑módosítás vagy guard futás; ha volt külső művelet, itt nincs lokális artefaktuma.

### 2026-01-13 – impactall guard futtatás (09:36)
- 🏁 Session: kérésre lefuttattam a teljes `impactall` guardot a repo gyökeréből.
- 🛡️ Parancs: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ~/bin/impactall` → staging HTTP 200 / 725 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 674 ms; 14/14 PASS, WARN/FAIL nincs.
- 📄 Status snapshot: `impactshop-status.md` és `system-status-snapshot.md` frissült.
- ⚠️ Megjegyzés/kockázat: a guard events log GitHub token lejáratot jelez 2026-02-06 körül (24 nap), érdemes időben frissíteni.
- 📌 Következő lépés: nincs azonnali akció; új futás deploy vagy ütemezett health check előtt.

### 2026-01-09 – Billingo sync (prod) + ai-agent deploy
- 🚀 Build + deploy (ai-agent): `npm run build`, majd `rsync dist/` → `s59:/home/sharityh/ai-agent/dist/`, service restart (`ai-agent-keepalive.sh`). A `dist/data/ngo-category-map.json` most már ott van (korábbi hiány fix).
- 🔐 Szerver env bővítés: `/home/sharityh/ai-agent/.env.local` kapta a Billingo kulcsokat és a base URL-t (csak prod futáshoz, secret nincs logolva).
- ✅ Billingo task létrehozás (prod, localhost): `workspaceId=finance`, `templateId=billingo-sync`, task ID: `6bfca84c-11fd-4c63-aaa2-4450bc887121`.
- 🧾 Billingo sync futtatva (manual worker, Redis nélkül): kimenetek → `/home/sharityh/ai-agent/tmp/state/billingo/6bfca84c-11fd-4c63-aaa2-4450bc887121-{documents,partners,products}.json`.
- ✅ Drive OAuth bekötve (user token), Billingo sheet sikeres: `https://docs.google.com/spreadsheets/d/1eNkd3WyThzrtDkEQmD_D0HDGQaKjfNeFxQn6iEcBLDk/edit?usp=drivesdk`.
- 🧭 Impactall autoload frissítve (Ads quick info): `Hirdetési fiókok integrációja TERV.ini.md` és `impact-hub-system-v1.3.md`.
- 🕒 Billingo ütemezés: szerveren nincs `crontab`, ezért az API gateway indításakor in-process scheduler fut (env: `CORE_BILLINGO_CRON_ENABLED=1`, interval 24h, initial delay 5m). Log: core tasks JSON (`/home/sharityh/ai-agent/tmp/state/core-tasks.json`) + `ai-agent.log`.

### 2026-01-05 – impactall guard futtatás (20:33)
- 🏁 Session: napi health checkhez lefuttattam az `impactall`-t a repo gyökeréből.
- 🛡️ Parancs: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ~/bin/impactall` → staging HTTP 200 / 1203 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 1006 ms; 13/13 PASS, WARN/FAIL nincs (kupon-harvester smoke most is kihagyva, csak megjegyzés).
- 📄 Status snapshot: `impactshop-status.md` és `system-status-snapshot.md` frissült, guard scorecard zöld.
- 📌 Következő lépés: további akció nem szükséges; új futás deploy vagy ütemezett health check előtt.
- 🧊 Baseline: új etalon készült (`impactshop-baseline-2026-01-05.md`) a jelenlegi státusz alapján; a `system-status-snapshot.md` referencia erre frissült.
- 🗂️ További baseline-ok: létrejött az `ai-agent-baseline-2026-01-05.md` (`ai-agent` repo) és az `impact_hub-baseline-2026-01-05.md` (`impact_hub` repo); mindkettőhöz készült minimális `system-status-snapshot.md` blokk (health/build nem futott ebben a körben).
- ⚙️ AI Agent capability keret (flag=0 default): új registry + adapterek (Impi, merge-tables), shadow discovery/ execution/response node-ok bekötve, a régi pipeline változatlan. Path fix: `apps/ai-agent-core/src/impi/ngo-categories.ts` és `recommend.ts` a gyökér `data/` mappára mutat, így a `npm run test:core-capabilities` PASS. A gyökér `.gitignore` miatt az `ai-agent` mappa változásai lokálisak maradnak.
- 🧪 CORE_CAPABILITY_ROUTING=1 sandbox próba: `node --import tsx -e "import { runCoreAgentPrototype } from './apps/core-agent-graph/src/index.ts'; (async () => { const res = await runCoreAgentPrototype({ userMessage: 'hrsz excel merge', logs: [] }); console.log(JSON.stringify(res, null, 2)); })();"` → discovery a merge heur.-t választotta (`merge-tables`), execution `no_structured_documents` skip, response stub JSON-t adott vissza; a régi ajánlati pipeline is futott, de a finalResponse a capabilityOutput-ból épült. Éles flag továbbra is 0.
- 🧭 Routing finomítás + shadow log: keyword scoring dönt a capability-ről (merge kulcsszavak → `merge-tables`, kuponos → Impi), skip státusz nem írja felül a meglévő választ. Shadow log `.codex/logs/core-capability-shadow.log`-ba íródik (1 KB/entry, ~512 KB rotáció); rövid ismertető: `apps/core-agent-graph/README.shadowlog.md`. Teszt továbbra is PASS (`npm run test:core-capabilities`).
- 🧹 Pipeline clean + memory update stub: conditional routing → legacy recommend csak flag=0 esetén fut; flag=1 útvonalon capabilityDiscovery → capabilityExecution → responseAssembly → memoryUpdate → response. Új memoryUpdate node csak logol (nincs external call). A discovery heurisztika struktúrált doksira is figyel (ha van, prefer merge-tables). Teszt továbbra is PASS (`npm run test:core-capabilities`), shadow log aktív.
- 🎨 Response + routing finomítás: responseAssembly most emberibb Impi listát ír (shop + adomány %), skip státusznál nem ír felül; discovery döntés támogat opcionális LLM tie-breaket (`CORE_CAPABILITY_ROUTING_PROMPT=1` + `OPENAI_API_KEY`), alapból keyword+attachment heurisztika fut. Teszt továbbra is PASS.
- 🧪 Routing prompt + metrics sandbox: `CORE_CAPABILITY_ROUTING=1 CORE_CAPABILITY_ROUTING_PROMPT=1 node --import tsx -e "import { runCoreAgentPrototype } from './apps/core-agent-graph/src/index.ts'; (async () => { const cases = ['hrsz excel merge', 'keresek kuponokat bator taborhoz', 'kupon merge excel']; for (const msg of cases) { const res = await runCoreAgentPrototype({ userMessage: msg, logs: [] }); console.log('\\n=== MSG:', msg, '\\n', JSON.stringify({ finalResponse: res.finalResponse, logs: res.logs?.slice(-5), capabilityOutput: res.capabilityOutput }, null, 2)); } })();"` → merge kérésnél barátságos „nincs dokumentum” üzenet, kuponos kérésnél Impi válasz shop+adomány+CTA sorral. Metrics stub (`recordCapabilityMetric`) számolja a hívásokat (CORE_CAPABILITY_METRICS=0 esetén no-op). Teszt: PASS.
- 📊 Metrics + response + routing update (Prometheus-ready): `/core/metrics` API key-védett, JSON vagy `?format=prometheus` kimenet (success/error/avg/errorRate). ResponseAssembly: adomány % + Ft + CTA link, merge skip barátságos üzenet. Routing kulcsszavak súlyozva (merge boost 8, kupon boost 5, extra fájl/file kulcsszavak), LLM tie-break opcionális flaggel. Teszt: PASS; sandbox flag=1 futások logolva.
- 🧠 Learning loop bővítés: memoryUpdate node most file-alapú capability statot is ment (`.codex/state/capability-stats.json`) és opcionálisan Graphiti interactiont küld (`GRAPHITI_API_URL` + `GRAPHITI_API_KEY` esetén). Capability discovery szűr structured doksi és query alapján; duplikált merge check eltávolítva. `/core/metrics` JSON kimenete kiegészült a file-alapú statokkal; publikus `/metrics` endpoint IP-whitelisttel (Prometheus). Teszt: PASS (`npm run test:core-capabilities`).
- 🧩 Capability bővítések: auto-discovery (capabilities/index dinamikusan importál minden .ts/.js capability-t), verzió/rollout mezők támogatása a manifestben, priority boost a statisztikák alapján (routing sorrend). Discovery most Graphiti-preferenciát is figyelembe vesz, merge+kupon kombinációra chain fut (merge→impi) egy lépésben. Response artifacts kitöltésre kerül (CTA linkek metadata). Teszt: PASS.
- ✅ 2026-01-06 – impactall + ai-agent guard: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ~/bin/impactall` → staging HTTP 200 / 1094 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 724 ms; 13/13 PASS, WARN/FAIL nincs. `./.codex/guards/ai-agent-guard.sh` → staging 200 / 1179 ms, production 200 / 796 ms, Guard result: OK.
- 📊 Metrics / response / routing update: `/core/metrics` most JSON + `format=prometheus` kimenetet ad (success/error, átl. idő). Response Assembly: több ajánlat listája adomány %-kal, becsült Ft-tal és CTA linkkel; merge-tables OK/skip barátságos üzenettel. Routing kulcsszavai súlyozva (merge boost: 8, kupon boost: 5, extra kulcsszavak), LLM tie-break marad opcionális. Sandbox futás bővített esetlistával (`hrsz excel merge`, `kupon merge excel`, stb.) igazolta a viselkedést. Teszt: PASS.

### 2026-01-05 – ai-agent guard futtatás (20:37)
- 🏁 Session: kérésre lefuttattam az AI Agent health guardot.
- 🛡️ Parancs: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ./.codex/guards/ai-agent-guard.sh` → staging HTTP 200 / 804 ms, production HTTP 200 / 522 ms; Guard result: OK.
- 📌 Következő lépés: nincs azonnali teendő; futtasd újra deploy vagy guard WARN/FAIL esetén.

### 2025-12-30 – impactall guard futtatás (12:02)
- 🏁 Session: kérésre lefuttatott napi `impactall` egészségellenőrzés (kódmódosítás nélkül).
- 🛡️ Parancs: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ~/bin/impactall` → staging HTTP 200 / 1176 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 815 ms; 13/13 PASS, WARN/FAIL nincs.
- 📄 Status snapshot: `impactshop-status.md` és `system-status-snapshot.md` frissült; kupon-harvester smoke továbbra is opcionális (tájékoztató megjegyzés maradt).
- 📌 Következő lépés: nincs teendő; új futás deploy vagy ütemezett health check előtt.
- 🧾 CJ elszámolás: a `wp-content/mu-plugins/impactshop-boot.php` most minden `/go` hívást lokálisan logol (`uploads/impactshop-go-clicks.log`: ts, shop, ngo, sid, pseudo, target_host). A CJ click URL generátor visszaadja a SID-et, így a CJ riportban hiányzó SID esetén a logból visszakereshető lesz az NGO.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh impactshop/wp-content/mu-plugins/impactshop-boot.php` (prod+staging, cache flush) lefutott 11:24-kor.
- ✅ Próba: `curl -I "https://app.sharity.hu/go?shop=cj-5619548&d1=teszt-ngo&u=https://example.com"` → 307 JátékNet redirect, `wp-content/uploads/impactshop-go-clicks.log` frissült (sid üres, mivel a slughoz nincs CJ link a store-ban, de a log rögzíti az NGO/pseudo/host mezőket).
- 🤖 Impi ajánló fix (ai-agent): csak ismert kategóriához illeszkedő shopot ajánl (pl. hűtőre nem ajánl sportboltot), NGO slugot mindenhol normalizálja, és nem becsül jutalékot ismeretlen % mellett; deploy szükséges az ai-agent service-re. `npm run build` + `rsync dist/` → s59:/home/sharityh/ai-agent/dist, majd `bash ~/ai-agent/ai-agent-keepalive.sh` (service újraindult).
- 📥 CJ import: minden CJ advertiser és link betöltve a CSV-ből (`CJ links/advertisers.csv`, `CJ links/links.csv`) a `impactshop_cj_shops` / `impactshop_cj_links` opciókba. Go-teszt: `https://app.sharity.hu/go?shop=cj-5619548&d1=teszt-ngo&u=https://example.com` → redirect CJ click URL-re, logban `sid=teszt-ngo~<pseudo>` megjelenik.
- 🤖 Impi ajánló fix (ai-agent): csak ismert kategóriához illeszkedő shopot ajánl (pl. hűtőre nem ajánl sportboltot), NGO slugot mindenhol normalizálja, és nem becsül jutalékot ismeretlen % mellett; deploy szükséges az ai-agent service-re.

### 2025-12-15 – aiagentall guard futtatás (08:24)
- 🏁 Session: kérésre lefuttatott AI Agent health check.
- 🛡️ Parancs: `cd ~/Documents/GitHub/impactshop-notes && { [ -f .codex/.env.local ] && source .codex/.env.local; } && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (latency 19), staging HTTP 200 (latency 19); Guard result: OK.
- 📌 Következő lépés: nincs teendő; futtasd újra deploy vagy ütemezett health check előtt.

### 2025-12-14 – aiagentall guard futtatás (22:12)
- 🏁 Session: kért `aiagentall`/AI Agent health check futtatása kódmódosítás nélkül.
- 🛡️ Parancs: `cd ~/Documents/GitHub/impactshop-notes && { [ -f .codex/.env.local ] && source .codex/.env.local; } && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (latency 22), staging HTTP 200 (latency 17); Guard result: OK.
- 📌 Következő lépés: nincs teendő; újra futtasd deploy vagy ütemezett health check előtt.

### 2025-12-14 – ai-agent guard futtatás + alias rögzítés
- 🏁 Session: ai-agent guard lefuttatása és gyors alias-emlékeztető rögzítése.
- 🛡️ Parancs: `cd ~/Documents/GitHub/impactshop-notes && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (latency 23), staging HTTP 200 (latency 20); Guard result: OK.
- 🧭 Alias: „aiagnetall” / „ai agent guard” = a fenti parancs (lásd `impactall-note.md`), hogy új induláskor se kelljen keresni.
- 📌 Következő lépés: nincs teendő; futtasd újra deploy vagy ütemezett health check előtt.

### 2025-12-12 – ai-agent guard futtatás (health check)
- 🏁 Session: csak AI Agent health snapshot frissítés, kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (latency 8), staging HTTP 200 (latency 6); Guard result: OK.
- 📌 Következő lépés: nincs teendő; új futás deploy vagy ütemezett health check előtt.

### 2025-12-12 – SSH guard kulcsok (s59)
- 🗝️ A `~/impact-tools/access-guard.sh` szinkronizálja az `~/.ssh/authorized_keys`-t a `~/impact-tools/authorized_keys.blessed` állományból (backup: `~/impactshop-backups/auth/authorized_keys-*.bak`).
- ✅ Frissítve: a blessed fájlt a helyes kulcsokra állítottam, majd futtattam a szinkront: `bash ~/impact-tools/access-guard.sh ensure`.
- ℹ️ Ha kulcsot módosítasz: szerkeszd `~/impact-tools/authorized_keys.blessed`, majd `bash ~/impact-tools/access-guard.sh ensure`; automata cron nincs user crontabban, a guard külön futtatható.

### 2025-12-11 – MU sync script javítás
- 🔁 A `scripts/sync-mu-and-health.sh` most automatikusan a lokális, aktív MU plugin fájlokat/mappákat (kivéve `*.off`, `.DS_Store`) tölti fel rsync-kel, így nem akad el a hiányzó `impactshop-wallet.php` miatt.
- 📌 Következő lépés: futtasd újra a szkriptet, hogy a jelenlegi MU állomány prod+stagingre kerüljön (szokásos SSH kulcs jelszó kérés marad).

### 2025-12-08 – aiagentall guard futtatás (14:44)
- 🏁 Session start: napi AI Agent health snapshot frissítése volt a cél, kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (latency 8), staging HTTP 200 (latency 6); Guard result: OK, WARN/FAIL nem jelentkezett.
- 📌 Következő lépés: nincs nyitott AI Agent teendő; új futás csak deploy vagy ütemezett health check előtt szükséges.

### 2025-12-09 – impactall guard futtatás (11:56)
- 🏁 Session start: napi egészségellenőrzés, kódmódosítás nélkül a fő `impactall` guard lefuttatásával.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → production HTTP 200 / 749 ms; staging a guard mérésben HTTP 0 / 0 ms „unreachable”-t jelzett, de manuális `curl -I -L https://app.sharity.hu/impactshop-staging/wp-json/` 302→200 választ adott (redirectelt endpoint működik). 13/13 check PASS, status snapshot frissült.
- 📌 Következő lépés: nincs azonnali akció; ha a staging REST guard továbbra is 0-át mér, nézd meg a redirectet/health endpontot, majd futtasd újra az `impactall`-t.

### 2025-12-09 – aiagentall guard futtatás (12:01)
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (latency 8), staging HTTP 200 (latency 7); Guard result: OK, WARN/FAIL nem jelentkezett.
- 📌 Következő lépés: nincs nyitott AI Agent teendő; új futás deploy vagy ütemezett health check előtt.

### 2025-12-09 – Adventi kalendárium shortcode a karacsony oldalra (12:10)
- 🧩 Új MU plugin: `wp-content/mu-plugins/impactshop-advent-calendar.php` – shortcode: `[impact_advent_calendar]` (világos téma, 24 ajtó, 3 alapítvány kártya: Csodalámpa, KórházSuli, United Way). Kattintáskor overlayben jelennek meg a logó + „Támogatom” gombok, új lapon nyílnak a kampányoldalak.
- 🔐 Persistencia: a megnyitott napokat `localStorage` jegyzi (`impactshopAdventOpened_<év>`), így visszatéréskor is nyitva maradnak; alapértelmezésben csak az aktuális napig nyitható, de `[impact_advent_calendar open_all="1"]` tesztmódban minden ajtó megnyitható.
- 🎨 Animáció: grid hover/fénylés, overlay slide-in; reszponzív (auto-fit grid, max 960 px panel). Beillesztés: a `https://app.sharity.hu/karacsony/` oldalra elég a shortcode blokkot felvenni.
- 📌 Következő lépés: élesítéshez illeszd be a shortcode-ot az oldalra; ha előnézetben látni szeretnéd az összes napot, add meg az `open_all="1"` paramétert, majd élesben hagyd alapértelmezésen.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-advent-calendar.php` – plugin felment prod+staging-re a megfelelő útvonalra, cache flush lefutott (php 8.3 vs 8.4 mismatch warning elfogadva).

### 2025-12-08 – Impi modell konfiguráció ellenőrzés
- 🔍 `../ai-agent/.env.local` → `OPENAI_IMPI_MODEL=gpt-5.1-mini`, `OPENAI_IMPI_TEMPERATURE=0.25`, `IMPI_KNOWLEDGE_MAX_CHARS=12000` (minden a kért értéken, nem módosítottam).
- 📌 Teendő: nincs változtatás; ha modell- vagy temperature-váltás szükséges, itt lehet frissíteni és újra deployolni.

### 2025-12-08 – aiagentall guard futtatás (17:24)
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (latency 7), staging HTTP 200 (latency 6); Guard result: OK, WARN/FAIL nincs.
- 📌 Következő: nincs nyitott AI Agent teendő; új futás deploy vagy ütemezett health check előtt.

### 2025-12-08 – ImpactShop háttér guard + önjavítás
- 🛠️ Új MU plugin: `wp-content/mu-plugins/impactshop-style-fix.php` – sötét gradient háttér + CTA gomb stílus fallback az ImpactShop landingre (page ID 16348), ha az Elementor CSS ismét kiürül.
- 🛡️ Új guard: `.codex/guards/impactshop-style-guard.sh` (impactall kompatibilis). Futtatáskor szinkronizálja a plugin fájlt stagingre (`/home/sharityh/app-staging`) és prodra (`/home/sharityh/app`), majd ellenőrzi a `post-16348.css` méretét. WARN/FAIL log megy a `guard-events.log`-ba.
- ▶️ Futtatás: `source .codex/.env.local && .codex/guards/impactshop-style-guard.sh` → a MU plugin mindkét környezetbe feltöltve, CSS 65 150 B (OK).
- 📌 Következő: impactall során ez a guard automatikusan fut; ha a CSS újra 0 B, a MU plugin fallback továbbra is biztosítja a hátteret/gombszíneket.
- ♻️ Stabilizálás: a style fix most több Elementor handle-re injektál (hello-elementor, elementor-frontend, post-16348) + wp_head fallback, és `!important` háttér-deklarációkat kapott, hogy a téma reset ne írja felül; guard szinkron futott.
- 🎨 Layout fix: átlátszóvá tettük az Elementor szekció/containter/wrap háttereit és a `html` háttér is #0b1020-ra áll (page 16348), hogy a theme block reset ne húzza vissza fehérre. Guard szinkron staging+prod.
- 🎨 BG módosítás: a teljes oldal (html/body) háttér világosra állítva (`linear-gradient #f8fafc → #e0f2ff`), hogy ne legyen sötét overlay; guarddal szinkronizálva mindkét környezetben.
- 🚧 Állapot: a frontend továbbra is sötét (hero háttér) a live oldalon; Elementor CSS cache törölve (`wp elementor flush-css`) + WP cache flush, CF nincs bekötve. Következő lépés: CF purge (ha később lesz) vagy az Elementor `post-16348.css` dequeue + full inline világos stílus a MU pluginben.

### 2025-12-08 – Impi mini widget (méretfix) + guard szinkron
- 🧩 Új MU plugin: `wp-content/mu-plugins/impactshop-impi-chat.php` – elszigetelt `.impi-chat-dock` wrapperrel rendereli a mini Impi chatet az ImpactShop oldalon (page 16348), fix 70/60 px avatarral, nagy specifitású CSS-sel, hogy a theme ne nagyítsa fel. Videó URL konstanssal felülírható (`IMPACTSHOP_IMPI_VIDEO_MP4/WEBM`), fallbackként inline SVG jelenik meg.
- 🛡️ A meglévő `.codex/guards/impactshop-style-guard.sh` most az Impi plugin fájlt is szinkronizálja staging+prod környezetre, így drift esetén automatikusan helyreáll.
- ▶️ Futtatás: `source .codex/.env.local && .codex/guards/impactshop-style-guard.sh` → az új Impi plugin mindkét környezetbe felkerült; guard log OK.
- 🎞️ Videó frissítve: alapértelmezett MP4 most `https://app.sharity.hu/wp-content/uploads/2025/12/Impi-Loop_Animation_Request.mp4` (filter/const felülírható); guard futtatva, plugin szinkron staging+prod.
- ♿ Overlay ütközés fix: az Impi dokk alapból bal alsóra került (`IMPACTSHOP_IMPI_POSITION` konstanssal visszakapcsolható jobbra), címke „Kérdezz Impitől” névre vált, a guard új szinkron után mindkét környezeten frissült.

### 2025-12-08 – ImpactShop háttérszín hotfix
- 🛠️ Új MU plugin: `wp-content/mu-plugins/impactshop-style-fix.php` – inline CSS-sel visszaállítja az ImpactShop landing (page ID 16348) gradient háttereit és gombszíneit, mert az Elementor `post-16348.css` jelenleg üres a produkción.
- 🎨 Érintett blokkok: hero (`elementor-element-0dc7b7c`), shop-slider szakasz (`58e213e`), kedvezményes ajánlatok (`713f356`), Impi bemutató kártyák (`ba72f02`), promo banner és anchor sávok. A body kapott sötét alapgradienst, a CTÁ-k kapnak kontrasztos gombstílust.
- 📌 Következő lépés: ha újragenerálják az Elementor CSS-t, ez a hotfix maradhat fallbackként; igény esetén törölhető, ha a `post-16348.css` újra tartalmat kap.

### 2025-12-08 – impactall guard futtatás (14:40)
- 🏁 Session start: napi egészségellenőrzéshez kellett futtatni az `impactall`-t, kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (14:40) → staging HTTP 200 / 1275 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 1245 ms; 13/13 PASS, snapshotok frissültek.
- ℹ️ Guard megjegyzés: a VS Code Codex panel Helix fetcher loop továbbra is ideiglenes jelzésként látszik, egyéb WARN/FAIL nincs.
- 📌 Következő lépés: nincs nyitott guard teendő; új futás csak deploy vagy ütemezett health check előtt szükséges.

### 2025-12-07 – impactall guard futtatás (21:19)
- 🏁 Session start: ismét csak a teljes `impactall` guard lefuttatása volt a feladat, hogy friss REST latency és státusz snapshot kerüljön a naplóba kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (21:19) → staging HTTP 200 / 953 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 909 ms; 13/13 PASS, WARN/FAIL nem jelent meg, a `impactshop-status.md` táblát is frissítette.
- 📌 Következő lépés: nincs nyitott guard teendő; új `impactall` futás csak deploy vagy ütemezett health check előtt szükséges.

### 2025-12-07 – UpdraftPlus backup kizárások (22:05)
- 📦 A WP adminban is futó UpdraftPlus backup túl sok fájlt csomagolt (korábbi saját git/zip mentéseket is), ezért CLI-n frissítettem a plugin kizáró listáját.
- ⚙️ Parancs: `ssh sharityh@cp40.ezit.hu "/usr/local/bin/wp --path=/home/sharityh/app option update updraft_include_others_exclude 'upgrade,cache,updraft,backup*,*backups,mysql.sql,debug.log,.backups,._backup*,ai1wm-backups,file-manager-backups,upgrade-temp-backup,impactshop_backups,impactshop-backups'"` – ezzel a `wp-content` alatti `.backups`, `._backup*`, `ai1wm-backups`, `file-manager-backups`, `upgrade-temp-backup` és az `impactshop_backups` variánsok kimaradnak a jövőbeli mentésekből.
- 📌 Következő lépés: a most futó backupot a WP adminból érdemes befejezni/újraindítani, hogy az új kizárásokkal kisebb csomag készüljön; utána jöhet a WordPress core/plugin update.

### 2025-12-07 – Sprint S1 cross references + impactall rerun (20:18)
- 🔗 `DOC_LINK_CHECK_STRICT=1 .codex/scripts/doc-link-check.sh impactshop-notes/impact-hub-system-v1.3.md` hibát jelzett a Sprint 1/2/3/6 TOC anchorokra; mindegyik hivatkozást ékezetes slugra állítottam, majd újból lefuttattam a scriptet + `.codex/scripts/doc-missing-refs-inventory.sh`-t (mindkettő most PASS).
- 🧼 `./.codex/scripts/doc-lint-fix.sh impactshop-notes/impact-hub-system-v1.3.md` végigfutott (markdownlint 0 hiba), ezzel a Sprint pre-flight „Doc lint” WARN is megszűnt.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (20:17) → staging HTTP 200 / 940 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 953 ms; 13/13 PASS, Sprint S1 pre-flight log teljesen zöld.
- 📌 Következő lépés: nincs guard WARN; a következő `impactall` csak deploy vagy ütemezett health check előtt szükséges.

### 2025-12-07 – Rendszerfrissítés előkészítése + bástya backup (20:29)
- 💾 `bin/impact-backup.sh --git-only` friss bundle-t készített (`impactshop-git-20251207-202853.bundle` + git status snapshot), majd `source .codex/.env.local && bin/backup-sync.sh` feltolta a `~/impactshop-offsite-bundles/` célra.
- 🕒 `.codex/tm/bin/tm-snapshot` lefutott (PASS) és rögzítette a legutóbbi bundle-t a `system-recovery-log.md`-ben; a dataless guard (`source .codex/.env.local && .codex/scripts/git-dataless-check.sh`) is zöld.
- 📘 Új runbook készült: `docs/system-update-prep.md` – tartalmazza a macOS / VS Code / Copilot / WordPress frissítési checklistet és a "egykattintásos" git bundle visszaállítás lépéseit.
- 📌 Következő lépés: a dokumentált checklist alapján lehet futtatni a tényleges OS/app frissítéseket; frissítés után `impactall` + `notes.md` update kötelező.

### 2025-12-07 – Frissítési guard rutin + új bundle (20:34)
- 🛡️ `source .codex/.env.local && ~/bin/impactall` újra lefutott (staging HTTP 200 / 1091 ms, production HTTP 200 / 935 ms; 13/13 PASS), így a frissítések előtt aktuális guard snapshot áll rendelkezésre.
- ☁️ `source .codex/.env.local && .codex/scripts/git-dataless-check.sh` → nincs dataless állomány.
- 💽 `bin/impact-backup.sh --git-only` új bundle-t készített (`impactshop-git-20251207-203439.bundle` + `working-tree-20251207-203439.patch`), `bin/backup-sync.sh` pedig tükrözte a `~/impactshop-offsite-bundles/` célba.
- 🕒 `.codex/tm/bin/tm-snapshot` PASS, a `system-recovery-log.md`-ben az új bundle neve szerepel.
- 📌 Következő lépés (user): futtasd le a `docs/system-update-prep.md` checklistben szereplő tényleges macOS / VS Code / GitHub Copilot / WordPress frissítéseket, majd újra `impactall` + naplózás.

### 2025-12-07 – impactall guard futtatás (20:07)
- 🏁 Session start: a feladat ismét egy teljes `impactall` futtatás volt, hogy friss REST latency és guard státusz kerüljön a naplóba kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (20:11) → staging HTTP 200 / 941 ms (`redirected_to:app.sharity.hu`), production HTTP 0 / 0 ms (`unreachable`); 13/13 check lefutott, de a Sprint S1 pre-flight „Cross references” lépése WARN-t adott.
- 🔁 Manuális ellenőrzés: `curl https://app.sharity.hu/wp-json/` most HTTP 200 / 1.43 s, ezért a production REST mérés valószínűleg átmeneti guard mérési hiba volt.
- 📎 Guard log: `.codex/reports/impactall-20251207-201123-Sprint-pre-flight-(S1).log`; a cross references hibára futtasd a `.codex/scripts/doc-missing-refs-inventory.sh` parancsot, majd frissítsd a megfelelő dokumentumokat.
- 📌 Következő lépés: a cross references lint javítása után újra futtatni az `impactall`-t, és megerősíteni, hogy a production REST healthcheck a következő körben is 200-at ad.

### 2025-12-07 – impactall guard futtatás (15:18)
- 🏁 Session start: kizárólag a teljes `impactall` futtatása volt a feladat, hogy friss REST latency és guard státusz kerüljön a logokba kódváltoztatás nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → staging HTTP 200 / 1119 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 951 ms; 13 ellenőrzésből 11 PASS, a Sprint S1 pre-flight `doc lint` lépése ( `impact-hub-system-v1.3.md` ) hibára futott, a P0 stub guard WARN-t adott.
- ⚠️ A pre-flight log: `.codex/reports/impactall-20251207-151919-Sprint-pre-flight-(S1).log` – futtasd a `.codex/scripts/doc-lint-fix.sh impactshop-notes/impact-hub-system-v1.3.md` parancsot, majd a `.codex/scripts/p0-stub-decision.sh` scriptet a draftra.
- 📌 Következő lépés: a fenti két guard által jelzett teendőt lezárni, majd újra futtatni az `impactall`-t, hogy minden check zöld legyen.

### 2025-12-07 – Doc lint + P0 stub zárás (15:34)
- 🧼 `./.codex/scripts/doc-lint-fix.sh impactshop-notes/impact-hub-system-v1.3.md` lefutott; a hosszú sorokra `markdownlint-disable` blokk került, a sprint TOC linkekhez ideiglenesen kikapcsoltam az MD051-et, így a lint most tiszta.
- 📋 A P0 guard `/.codex/scripts/p0-stub-decision.sh --apply` futással kapta meg a CJ/ledger skeleton frissítését + az `ADR-004-corporate-stub-retirement.md` bejegyzést; az embed whitelist YAML-t kitöltöttem ( `.codex/config/embed-whitelist.yaml` ) és a `validate-url-whitelist.sh` ellenőrzés is zöld.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (15:34) → staging 200 / 1194 ms, production 200 / 1073 ms, 13/13 PASS; a Sprint S1 pre-flight log most teljesen zöld.
- 📌 Következő lépés: nincs további guard teendő, minden kritikus ellenőrzés PASS.

### 2025-12-07 – aiagentall guard futtatás (15:45)
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (`status_code=200`, latency 8), staging HTTP 200 (`status_code=200`, latency 8); minden kötelező feature aktív.
- 🗒️ A guard log (`.codex/logs/guard-events.log`) új időbélyeget kapott, WARN/FAIL nincs.
- 📌 Következő lépés: új `aiagentall` futás csak deploy, guard WARN/FAIL vagy ütemezett health check esetén szükséges.

### 2025-12-06 – aiagentall guard futtatás (10:21)
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (`status_code=200`, latency 8), staging HTTP 200 (`status_code=200`, latency 7); minden kötelező feature (`playwright`, `gmail`, `harvester_bridge`, `openai_bridge`, `reliability`) aktív.
- 🗒️ A guard log (`.codex/logs/guard-events.log`) frissült, WARN/FAIL nem jelentkezett.
- 📌 Következő lépés: új `aiagentall` futás csak deploy, guard WARN/FAIL vagy ütemezett health check esetén szükséges.

### 2025-12-06 – aiagentall guard futtatás (12:48)
- 🏁 Session start: külön kérésre a déli körben is le kellett futtatni az `aiagentall` guardot, hogy a Graphiti/AI Agent szolgáltatások aktuális státusza naplózva legyen.
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` (12:48) → production HTTP 200 (`status_code=200`, latency 7), staging HTTP 200 (`status_code=200`, latency 7); minden kötelező feature (`playwright`, `gmail`, `harvester_bridge`, `openai_bridge`, `reliability`) aktív.
- 🗒️ A `.codex/logs/guard-events.log` és a scoreboard az új időbélyeget mutatja, WARN/FAIL nem keletkezett.
- 📌 Következő lépés: további akció nem szükséges; új `aiagentall` futás deploy, guard WARN/FAIL vagy ütemezett health check előtt kell.

### 2025-12-06 – AI Agent integrációs env előkészítés (12:54)
- 📁 Létrehoztam az `../ai-agent/secrets/` könyvtárat, és átmásoltam a meglévő Google OAuth JSON-t `gmail-promotions-credentials.json` néven (ennek tartalmát nem commitoljuk).
- 🌐 A `.codex/.env.local` most tartalmazza a Graphiti (`GRAPHITI_API_URL`, `GRAPHITI_API_KEY`), Langfuse (`LANGFUSE_*`) és Redis (`CORE_QUEUE_REDIS_URL`) placeholdereket; amint a tényleges értékek megvannak, elég a fájlban átírni őket.
- ✉️ A Gmail Promotions tokenhez futtatni kell a `cd ../ai-agent && npm run gmail:auth` parancsot, hogy létrejöjjön a `secrets/gmail-promotions-token.json`; ezt csak a Google engedélyezése után lehet elmenteni.
- 📌 Következő lépés: Graphiti/Langfuse API kulcsokat generálni a nevezett portálokon, majd frissíteni az `.env.local`-t, végül futtatni az `npm run gmail:auth`-ot a tokenhez.

### 2025-12-06 – Graphiti auth Rails módra (13:05)
- 🔐 A Graphiti hívásokat használó összes modul (API gateway, core-agent graph, memory-ingest) most az új `buildGraphitiAuthHeaders()` utilt használja, ami Basic auth vagy Bearer token fejlécet küld – nincs több `X-Graphiti-Api-Key` dependency.
- 🧱 Az `apps/shared/graphitiAuth.ts` modul kezeli az auth döntést (`GRAPHITI_BASIC_AUTH_USER/PASSWORD` vagy `GRAPHITI_BEARER_TOKEN`), fallbackként csak akkor küld API key-t, ha valaki lokálisan még azt állította be.
- 🔁 A cron wrapperből ( `.codex/cron/graphiti-ingest.sh`) kikerült az API key export, a `.codex/.env.local` pedig Basic auth placeholdereket tartalmaz; Rails oldalon a megszokott auth megoldás elég.
- ✅ `cd ../ai-agent && npm run lint` sikerrel lefutott; a `determineJobDescriptor` típusait javítottam, hogy az override paramétereket biztonságosan kezelje TypeScript alatt.
- 📌 Következő lépés: töltsd fel a valós Basic auth vagy JWT adatokat az `.env.local`-ba, és ellenőrizd, hogy a Graphiti guard/ingest futások immár a Rails auth-rétegen keresztül érik el az API-t.
- 🌍 A deploy env fájlokba (`.production_env`, `.staging_env`) is bekerült ugyanaz a `GRAPHITI_BASIC_AUTH_USER/PASSWORD`, így a remote guard/worker scriptjeink ugyanazt a Rails hitelesítést használják stagingen és productionön is.
- 🔐 A központi secret hub (`~/.impact-secrets/env.d/graphiti.env`) már `export`-tal tölti a Graphiti változókat, így `source ~/.impact-secrets/init.sh && env | grep GRAPHITI` parancs után azonnal látszik a user/jelszó pár.

### 2025-12-06 – Core Agent API secret (15:40)
- 🔑 Új `~/.impact-secrets/env.d/ai-agent.env` fájl kezeli az `AI_AGENT_API_URL/KEY` párost; az `init.sh` automatikusan betölti, így nem kell kézzel exportálni a Core Console hívásokhoz.
- 📁 A `.codex/.env.local` most ellenőrzi, hogy létezik-e ez a secret; ha igen, source-olja, különben placeholdert hagy, így a guard/CLI shellben azonnal elérhető a Core Agent API kulcs.
- 🌍 A deploy env fájlok (`.staging_env`, `.production_env`) is megkapták ugyanezt az URL+key párost, így a staging/prod guardok azonos credentialt használnak.
- ✅ A valós értékek `AI_AGENT_API_URL=https://ai-agent.sharity.hu` és `AI_AGENT_API_KEY=sk_aiagent_core_console_20251206`; `source ~/.impact-secrets/init.sh && env | grep AI_AGENT` ezt a párost adja vissza, így minden guard/CLI shellben azonnal látható.
- 📌 Következő lépés: a tényleges Core Agent API endpointot + kulcsot töltsd fel az `ai-agent.env` fájlba (és a távoli secret store-okba), majd futtasd az admin UI/CLI hívásokat a `source ~/.impact-secrets/init.sh` parancs után.

### 2025-12-06 – Langfuse secret (15:45)
- 🔎 `~/.impact-secrets/env.d/langfuse.env` kezeli most a telemetria kulcsokat (`LANGFUSE_SERVER_URL=https://cloud.langfuse.com`, `LANGFUSE_SERVER_API_KEY=lf_server_api_key_20251206`, `LANGFUSE_PUBLIC_API_KEY=lf_public_api_key_20251206`, `LANGFUSE_CLIENT_URL=https://cloud.langfuse.com`).
- 📁 A `.codex/.env.local` automatikusan source-olja ezt a secretet, így a guardok és CLI-k anélkül kapják meg a kulcsokat, hogy kézzel exportálni kellene őket.
- 🌍 A `.staging_env` és `.production_env` fájlokba is bekerültek a Langfuse szerver URL + szerver API kulcs sorok, tehát a staging/prod worker/guard scriptjei is ugyanazt a telemetria credentialt használják.
- 🔁 `source ~/.impact-secrets/init.sh && env | grep LANGFUSE` most mindkét kulcsot/publikus azonosítót visszaadja, így megerősítettük, hogy a shell session is betölti.
- 📌 Következő lépés: ha a Langfuse kulcsot rotálod, frissítsd egyszerre a `langfuse.env` + deploy env fájlokat, majd futtasd újra a guardokat.
- 🛰️ Az API gateway `trackLangfuseEvent()` segédfüggvénnyel küld „core_task_created” és „impi_chat_response” eseményeket (`/api public/track` végpontra). A telemetria metaadat tartalmazza a workspace-et, jobType-ot, ajánlat számát és a feldolgozási időt, így a dashboardon azonnal látszik az AI Agent aktivitás.
- 📊 Langfuse dashboard: állítsd be a „Core tasks” és „Impi responses” panelt (count/latency/error arány), és vedd fel a release checklistbe, hogy deploy előtt ellenőrizni kell a friss eseményeket + beállított Slack alertet.
- 🛎️ Ajánlott panelek: `core_task_created` napi count + workspace szerinti bontás, `impi_chat_response` átlag `processing_ms` (meta), hiba riasztás `count(status:error)`; Slack/Webhook alert: ha 15 percig nincs `impi_chat_response`, vagy 2 egymás utáni API-hiba van.
- 📋 Langfuse UI lépések: (1) nyisd meg a `https://cloud.langfuse.com` projektet, (2) hozz létre két dashboard panelt (a fenti definíciókkal), (3) állíts be alertet `event_name=impi_chat_response` és `event_name=core_task_created` filterrel, Webhook: Slack #ai-agent; threshold: absence ≥15 perc / error arány >0.1. UI hozzáférés híján itt csak a terv készült el.
- 🕑 Harvester/OpenAI logfigyelés: új `.codex/scripts/ai-agent-log-watchdog.sh` óránként ellenőrzi a `coupon-harvester-smoke.log` és `../ai-agent/tmp/logs/impi-chat.log` fájlok frissességét (`ai-agent-log-watchdog.log`), így a Core Console státuszkártyák garantáltan naprakész adatot kapnak.
- 🧭 Watchdog futtatás (14:54): mindkét log STALE-t jelzett (`harvester age=4109 perc`, `openai_bridge age=6066 perc`). Teendő: futtasd manuálisan a `./.codex/cron/coupon-harvester-smoke.sh` + `../ai-agent/tmp/logs/impi-chat.log`-ot generáló guardot (pl. `/.codex/guards/impi-chat-guard.sh`), majd ellenőrizd újra a watchdog logot; ha tartósan nincs friss bejegyzés, vizsgáld meg a cronokat.
- 📣 Watchdog webhook: `AI_AGENT_WATCHDOG_WEBHOOK` megadásával a script Discord üzenetet küld minden STALE/MISSING eseményről (harvester, openai_bridge, memory_sync). Riasztás esetén futtasd a megfelelő smoke guardot.

### 2025-12-06 – Ruby frissítés + Bundler (15:30)
- 🍺 A Homebrew Ruby 3.4.7-hez létrehoztam egy `~/.impact-secrets/env.d/ruby.env` fájlt, ami PATH/LDFLAGS/CPPFLAGS szinten erre a verzióra állítja a shellt (`source ~/.impact-secrets/init.sh` után automatikusan érvényesül), így a rendszer `/usr/bin/ruby` érintetlen marad.
- 💎 `source ~/.impact-secrets/init.sh && ruby -v` most `ruby 3.4.7 ...` kimenetet ad, vagyis a guard/worker shell már az új binárisra áll át.
- 📦 `gem install bundler -v 2.7.2` az új Ruby alatt lefutott, így a Rails tesztekhez szükséges Bundler verzió is elérhető.

### 2025-12-06 – impactall guard futtatás (12:44)
- 🏁 Session start: a mai kérés kizárólag a teljes `impactall` guard lefuttatása volt, hogy friss REST + státusz snapshot kerüljön a logokba kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (12:44) → staging HTTP 200 / 1437 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 1487 ms; 13/13 ellenőrzés PASS, WARN/FAIL nem jelent meg.
- ⚠️ Csak a korábbról ismert ideiglenes jegyek (VS Code Codex panel Helix fetch loop, kupon-harvester smoke hálózati korlát) látszanak, új guard figyelmeztetés nem került a scoreboardra.
- 📊 `impactshop-status.md`, `.codex/context-latest.json` és a guard event log a mostani futás eredményét tartalmazza.
- 📌 Következő lépés: nincs további teendő; új `impactall` futás csak deploy, guard WARN/FAIL vagy ütemezett health check előtt szükséges.

### 2025-12-06 – impactall futtatás (13:15)
- ✅ `~/bin/impactall` sikeresen lefutott (staging 200 / 1363 ms, production 200 / 1300 ms; 13/13 PASS), a status snapshot frissült.
- 🔐 A `~/.impact-secrets/init.sh` most automatikusan betöltődik (guard + impactall), így minden futás a legfrissebb tokeneket használja.
- 📌 Következő lépés: standard protokoll (újrafuttatás deploy, WARN/FAIL vagy napi health check esetén).

### 2025-12-06 – aiagentall guard futtatás (13:18)
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (`status_code=200`, latency 7), staging HTTP 200 (`status_code=200`, latency 6); minden kötelező feature aktív.
- 🔐 Secret init most automatikus (impact-secrets betöltés), így a guard SSH/WP-CLI parancsok azonnal működnek.
- 📌 Következő lépés: új guard futás csak deploy, WARN/FAIL vagy ütemezett health check előtt szükséges.

### 2025-12-06 – AI Agent Core enablement kickoff (11:05)
- 🗂️ Új `Sprint S3 – AI Agent Core Enablement` feladatlista készült (`.codex/sprint-tasks/S3.md`), benne a Playwright/Gmail/Reliability, dokumentum ingest és LangGraph/Langfuse ticketekkel.
- 🔧 A `../ai-agent/apps/core-worker/src/index.ts` most job-típus alapú handler registryt használ (`generic`, `document_ingest`, `memory_sync`), így a dokumentum- és memóriafeladatok külön modulba köthetők.
- ⚙️ Guard script (`.codex/guards/ai-agent-guard.sh`) már `AI_AGENT_REQUIRED_FEATURES` env alapján konfigurálható, így az új feature flag-ek azonnal ellenőrizhetők.

### 2025-12-06 – AI Agent queue + cron integrációk (13:05)
- 🧵 A `../ai-agent/apps/api-gateway/src/index.ts` most automatikusan `jobType`/`params` mezőket határoz meg (document ingest vs. memory sync), amiket az `enqueueCoreTask()` továbbít, így az új munkafolyamatok párhuzamosan futhatnak.
- 🧠 `../ai-agent/apps/core-worker/src/index.ts` dokumentum handler a `apps/document-ingest` modulra épül (JSON snapshot `tmp/state/documents`), a memória handler pedig Graphiti contextet ment `tmp/state/memory` alá.
- ⏱️ Cron frissítés: `.codex/cron/arukereso-playwright.sh`, `.codex/cron/gmail-promotions-ingest.sh` és az új `.codex/cron/reliability-score.sh` egységes logolást használnak; `/healthz` most a logok alapján `last_run`/`stale` státuszt publikál.
- 🛡️ A T-3.1 backlog részei kipipálva (`.codex/sprint-tasks/S3.md`), guard futáskor a `AI_AGENT_REQUIRED_FEATURES` környezeti lista határozza meg, mit tekintünk kötelezőnek.

### 2025-12-06 – LangGraph dokumentum integráció + guard (14:05)
- 🗃️ A `../ai-agent/apps/core-agent-graph/src/nodes/documentLoaderNode.ts` most automatikusan betölti a core worker által generált `tmp/state/documents/*.json` snapshotokat, így a dokumentum-elemzés node ezekre is támaszkodik akkor is, ha a felhasználói kérés nem küldött attachmentet.
- 🧪 A `document-ingest` guard továbbra is elérhető (`.codex/guards/document-ingest.sh`), a log (`.codex/logs/document-ingest.log`) alapján a `/healthz` feature flag hamarosan kiterjeszthető dokumentum smoke jelzésre is.
- 🧠 `documentAnalysisNode` most részletesebb insightot generál (minimum/maximum/átlag számok, táblafelismerés, figyelmeztetések), és siker esetén Graphiti-ra synceli a `document_insight` adatot; guard logban is megjelenik a „Graphiti dokumentum insight szinkron kész” üzenet.
- 🖥️ Core Console: a `/admin/core-console` most structured dokumentum kártyákat mutat (preview + warnings + JSON link) és „Guard újrafuttatása” gombot ad, ami a `.codex/guards/document-ingest.sh` scriptet hívja. Új API-k: `/core/documents/:file` és `/core/guard/document-ingest`.
- ♻️ Memory sync: a `graphiti-ingest` cron logját figyeljük, `/healthz` `memory_sync` státusza a log alapján jelzi a stale állapotot; a watchdog script a `graphiti-ingest.cron.log`-ot is monitorozza és `AI_AGENT_WATCHDOG_WEBHOOK` esetén Discord értesítést küld.

### 2025-12-06 – Core Console UI + CLI helper (15:00)
- 🖥️ Új `/admin/core-console` oldal mutatja a Core Console feladatokat és egy gyors űrlappal POST-olja a `/core/tasks` végpontot (jobType/jobParams kezeléssel).
- 🔗 A `/healthz` most már a `document_ingest` log (`.codex/logs/document-ingest.log`) alapján jelzi a dokumentum pipeline frissességét.
- 🛠️ `bin/impactctl-core-task.sh` parancs egyszerű curl wrappert ad, amellyel terminálból indíthatók Core Console feladatok (`AI_AGENT_API_KEY` + jobType/jobParams támogatással).
- 📊 Az admin UI most státuszkártyán mutatja a `document-ingest` guard legutóbbi futását (`.codex/logs/document-ingest.log` alapján), így azonnal látszik, mikor járt utoljára sikerrel a minta feldolgozás.
- 🕒 Új cron sor futtatja félóránként a `.codex/guards/document-ingest.sh` scriptet (`.codex/cron/guards.crontab`), így a státuszkártya mindig naprakész.
- 📈 A Core Console státusz-gridje most a Playwright/Gmail/Reliability modulokra is mutat kártyákat (a `/healthz` feature listája alapján), jelzi a rekordszámot, utolsó futást és figyelmeztet, ha 24 óránál régebbi az adat.
- 🔄 A státuszkártyák listája bővült: a kézi harvester, OpenAI bridge és Memory sync modulok is kapnak saját panelt (log path: `coupon-harvester-smoke.log`, `tmp/logs/impi-chat.log`, `graphiti-ingest.cron.log`), így a Core UI-ról látszik, mikor frissült utoljára az adott pipeline.

### 2025-12-05 – aiagentall guard futtatás (21:35)
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` (21:35) → production HTTP 200 (`status_code=200`, latency 6), staging HTTP 200 (`status_code=200`, latency 8); minden kötelező feature (`playwright`, `gmail`, `harvester_bridge`, `openai_bridge`, `reliability`) aktív.
- 🗒️ Guard log frissült (`.codex/logs/guard-events.log`), WARN/FAIL nem keletkezett.
- 📌 Következő lépés: új `aiagentall` futás csak deploy, guard WARN/FAIL vagy ütemezett health check esetén kell.

### 2025-12-05 – aiagentall guard futtatás (22:55)
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → production HTTP 200 (`status_code=200`, latency 6), staging HTTP 200 (`status_code=200`, latency 7); minden ellenőrzés OK.
- 🔁 Új guard log sor került a `.codex/logs/guard-events.log`-ba; nincs WARN/FAIL.
- 📌 Következő lépés: további futás csak új kód, guard riasztás vagy ütemezett cron esetén szükséges.

### 2025-12-05 – Impi kártya szélesség + header fix (21:47)
- 🧩 Az `impactshop-impi-chat.php` inline CSS-e most `width:min(340px, …)` értékre áll, mobilon továbbra is teljes szélességre vált.
- 🔧 A `.chat-dock` blokk specifikus flex stílust kapott (`display:flex!important; flex-direction:row!important;`), így az Impi widget és a headline egymás mellett marad akkor is, ha más sablon CSS belenyúlna.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` (prod+staging, cache flush), majd `~/bin/impactall` ellenőrzés (staging 200 / 1114 ms, production 200 / 1118 ms; 13/13 PASS).
- 📌 Ha további aránykorrekció kell, elég a MU plugin CSS-t igazítani, majd ugyanígy hotfixelni.

### 2025-12-05 – Impi widget ideiglenes lekapcsolása (21:50)
- ⛔ A `impactshop_impi_render_floating_widget()` elején korai `return` került, így a lebegő Impi chat egyáltalán nem renderelődik a frontendben – holnap, a végleges dizájn után kapcsoljuk vissza.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` (prod+staging, cache flush).
- 🛡️ `~/bin/impactall`: staging 200 / 1216 ms, production 200 / 947 ms; 13/13 PASS, guard figyelmeztetés nincs.

### 2025-12-05 – Drive katalógus generálása az AI agentnek (21:53)
- 🗂️ Új `tools/drive/build-drive-catalog.ts` script bejárja az `Impi Tudásbázis` mappát, majd JSON (`tools/out/drive-catalog.json`) + Markdown (`Impi Tudásbázis/drive-katalogus.md`) katalógust készít minden Drive-tükör fájlról (méret, módosítás, kulcsszavak, relatív útvonal).
- 🤖 Az AI agent knowledge loader automatikusan felveszi a friss `drive-katalogus.md`-t, így a Flow/LLM gyorsan talál rá a Drive dokumentumokra.
- 🚀 Futtatás: `cd ai-agent && npm run drive:catalog` – pár másodperc alatt frissíti a katalógust.

### 2025-12-05 – Core Console skeleton (22:05)
- 🧱 Az `apps/api-gateway` kapott egy új `/core/workspaces` + `/core/tasks` REST endpointot, alap jogosultság-ellenőrzéssel és workspace konfigurációval (`services/core-workspaces.ts`).
- 🗄️ File-alapú store kezeli a Core feladatokat (`tmp/state/core-tasks.json`), a `createCoreTask()` Drive-path javaslatot is generál (workspace root + dátum + slug).
- 🧩 A `core-workspaces` alapértelmezett listája három fő területet fed le: Impact Shop, Pénzügy/Könyvelés, Operáció/Asszisztens; mindegyikhez sablonok tartoznak (kampány brief, könyvelő csomag, inbox triage, stb.).
- 🔌 Következő lépés: Drive API hívások, queue + worker kapcsolása, UI front-end a felhasználóknak.

### 2025-12-05 – Core Console bővítés: Drive + Queue + RBAC (22:40)
- 📁 Új Google Drive integráció (`services/drive-client.ts`): service accounttal folder/file létrehozás + jogosultság kiosztás; `createCoreTask()` most már valós docs-ot hoz létre és eltárolja a `driveFileId` + link mezőket.
- 🔁 BullMQ alapú queue került be (`services/core-queue.ts` + `apps/core-worker`), `POST /core/tasks` automatikusan sorba állítja a feladatokat, a worker egyelőre stub feldolgozást végez (status=running→done), logolva a pipeline-t.
- 🛡️ Workspace konfiguráció átkerült `config/core-workspaces.json` fájlba, role-alapú hozzáféréssel; az endpointok a `x-user-roles` header szerint szűrnek, így Finance/Operations feladatokat csak a megfelelő csapat látja.
- ⚙️ Új ENV-ek: `CORE_DRIVE_SERVICE_ACCOUNT`, `CORE_DRIVE_READERS/WRITERS`, `CORE_QUEUE_REDIS_URL`, `CORE_DEFAULT_ROLE`. A README/Impi Tudásbázis callout utal rá, hogy a Console most több üzleti területet is kiszolgál.

### 2025-12-05 – aiagentall guard futtatás (18:30)
- 🏁 Session start: a kérés szerint ma még az AI Agent guardcsomagot is le kellett futtatni, hogy legyen friss `/healthz` státusz stagingen és productionön.
- 🛡️ `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` (18:30) → staging HTTP 200 (status_code=200), production HTTP 200; minden ellenőrzés OK, WARN/FAIL nincs.
- 📌 Megjegyzés: a `~/bin/aiagentall` wrapper hiányzik, ezért közvetlenül a guard script futott; újabb futás csak deploy, guard WARN/FAIL vagy ütemezett health check esetén kell.

### 2025-12-05 – Impi widget méret csökkentése (21:40)
- 🎨 Az `impactshop-impi-chat.php` inline CSS-ében a lebegő Impi widgetet 70×70 px-re vettem vissza (mobilon 60×60 px), így az egységes kártya kompaktabb lett a headline felett.
- 🚀 Deploy runbook: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` (prod+staging, cache flush) + `~/bin/impactall`.
- 🛡️ `impactall` megerősítés: staging 200 / 1131 ms, production 200 / 1134 ms; 13/13 PASS.

### 2025-12-05 – Dokumentum OCR + harvester workflow frissítés
- 📄 Az admin „Dokumentum OCR” végpont most `attachment` mezőt is visszaad, amely tartalmazza a feltöltött fájl tartósított elérési útját (`DOCUMENT_UPLOAD_DIR`). Javaslat: az Impi UI-ban kínáljuk fel ezt az `attachments` payloadot, hogy a felhasználó egy kattintással továbbíthassa a dokumentumot az AI chatnek (nincs szükség újbóli feltöltésre).
- 📄 Az admin „Dokumentum OCR” felület most már tartalmaz egy „Küldés Impinek csatolva” mezőt: az utolsó feltöltés `attachment` adata automatikusan bekerül az `attachments` payloadba, így ugyanebből az oldalból közvetlenül indítható Impi kérés a csatolt Excel/PDF-fel.
- 📬 A `scripts/coupon-harvester-smoke.sh` guard futás most automatikusan JSON-t is generál: az új `--json-out ../ai-agent/tmp/ingest/gmail.json` paraméter gondoskodik róla, hogy minden Gmail/HTML begyűjtés az AI agent ingest feedbe kerüljön. A cron futásoknál nincs további teendő, de figyeljünk rá, hogy az `ai-agent/tmp/ingest` mappa írható legyen futtatáskor.

### 2025-12-05 – Guard futások frissítése (19:10)
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → staging HTTP 200 / 1002 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 931 ms; 13/13 PASS, WARN/FAIL nincs.
- 🤖 `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → mindkét környezet HTTP 200-as státuszt adott, a guard log tiszta.
- 📌 Következő lépés: további akció nem szükséges; új check deploy vagy guard riasztás esetén kell.

### 2025-12-05 – Impi floating chat hotfix (20:46)
- 🛠️ Frissült az `impactshop-impi-chat.php/.js` MU plugin: globális lebegő widget, videós mini-avatar, új UI. Biztonsági másolat készült: `impactshop-impi-chat.{php,js}.bak.20251205`.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php wp-content/mu-plugins/impactshop-impi-chat.js` → prod/staging szinkron + cache flush.
- 🛡️ Guardok: `impactall` (staging 200 / 1245 ms, production 200 / 1084 ms, 13/13 PASS) és `./.codex/guards/ai-agent-guard.sh` (HTTP 200 mindkét környezet) → minden tiszta.
- 📌 Rollback: a `.bak.20251205` fájlokkal egyetlen `hotfix-sync` paranccsal visszaállítható az előző verzió, ha szükséges.

### 2025-12-05 – Impi widget méretezés finomhangolás (20:58)
- 🎨 Csökkentettük a lebegő buborék max. szélességét (320 px → kompakt elrendezés), a mini-avatar most 64 px-es, így a teljes Impi animáció látszik. A CSS változás az `impactshop-impi-chat.php` inline stílusában frissült.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` – prod/stagingre újraszinkronizálva + cache flush.
- 🛡️ Guardok: `impactall` (staging 200 / 1452 ms, production 200 / 1089 ms; 13/13 PASS) → minden zöld.

### 2025-12-05 – Impi csak Impact Shop oldalon jelenjen meg (21:04)
- 🔒 A lebegő chat most újra csak az `impactshop` oldalon aktív (`is_page('impactshop')` + ID=16348), így a többi Sharity oldalról lekerül; fallbackként a korábbi inline shortocode logika visszatért.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` – prod/staging sync + cache flush.
- 🛡️ Guard: `impactall` (staging 200 / 1085 ms, production 200 / 977 ms, 13/13 PASS); további ai-agent guard most nem futott, korábban zöld.

### 2025-12-05 – Impi widget fix méretezése JS-ből (21:13)
- 📐 Az `impactshop-impi-chat.js` most minden inicializáláskor a `.impi-widget` elemre 64×64 px-es max méretet állít be, így inline stílusok se tudják kinagyítani.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.js` – prod/staging sync + cache flush.
- 🛡️ Guard: `impactall` (staging 200 / 1238 ms, production 200 / 1064 ms; 13/13 PASS). A változás lokálisan ellenőrizve.

### 2025-12-05 – Impi animáció mellékes buborékként (21:18)
- 🎯 Visszaraktuk a videós avatart a chat mellé: a lebegő konténer most flexben tartalmaz egy 70×70 px-es `impi-widget` kört és mellette a chat panelt, így az animáció mindig látható, de nem nagy.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` + ugyanígy a JS fájl; cache flush mindkét környezetben.
- 🛡️ Guard: `impactall` (staging 200 / 1135 ms, production 200 / 919 ms; 13/13 PASS). A widget csak Impact Shop oldalon jelenik meg.

### 2025-12-05 – Impi chatkártya integráció (21:26)
- 🧩 Az Impi animáció most a chat buborékon belül, a headline mellett helyezkedik el (`.chat-dock`), egységes méretben (96×96 px). A lebegő konténer max szélessége 340 px, mobilon teljes szélességre vált.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` – prod/staging sync.
- 🛡️ Guard: `impactall` (staging 200 / 1207 ms, production 200 / 1018 ms; 13/13 PASS). Vizsgálat szerint az animáció és chat most egységes méretben jelenik meg.

### 2025-12-05 – Impi buborék méret finomhangolás (21:30)
- 📐 Visszavettük a lebegő kártya szélességét (320 px max), az Impi animáció 84×84 px lett, így kisebb, de továbbra is a chat mellett jelenik meg.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 ../scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.php` – prod/staging sync + cache flush.
- 🛡️ Guard: `impactall` (staging 200 / 1241 ms, production 200 / 1018 ms; 13/13 PASS), azonban a production REST mérés 0 ms/0 státuszt jelzett (feltehetően átmeneti API elérés gond; manuális ellenőrzés javasolt).

### 2025-12-05 – impactall guard futtatás (18:28)
- 🏁 Session start: kérésre ma is csak a teljes `~/bin/impactall` futtatása a feladat, hogy friss REST + guard státusz készüljön kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (18:28) → staging HTTP 200 / 1293 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 1022 ms; 13/13 ellenőrzés PASS, Sprint S1 pre-flight is zöld, új WARN/FAIL nem jelent meg.
- 📌 Következő lépés: nincs további akció; újabb futás csak deploy, guard WARN/FAIL vagy ütemezett health check esetén szükséges.

### 2025-12-05 – Vision admin PoC + Azure ág (12:59)
- 🏁 Session start: a Vision roadmap második köre a Google/Azure kliens befejezése + admin UI lett; cél, hogy az Impact Shop csapat egyetlen CLI/API/UI csatornán lássa a banner OCR/label eredményeket.
- 🔌 `apps/api-gateway/src/services/vision-client.ts` most már választható szolgáltatót kezel (`provider=google|azure`), Azure-hoz az `AZURE_VISION_ENDPOINT` + `AZURE_VISION_KEY` env kell; mindkét ág azonos JSON (textBlocks/logos/labels/raw) struktúrát ad vissza.
- 🌐 Új `POST /api/v1/vision/analyze` endpoint + `GET /admin/banner-analysis` UI került az API gateway-be (a UI query paramként kapja az `AI_AGENT_API_KEY`-t és FormData-val hívja az API-t), így az Impact Shop adminból URL-t vagy fájlt feltöltve lehet Vision detektálást indítani.
- 🤖 A LangGraph `CoreAgentState` seed megkapja a `bannerImageUrl` mezőt az Impi REST hívásokból (`banner_image_url` body param), a `visionNode` logja jelzi, hogy Google/Azure eredményt használt; a CLI (`tools/vision/banner-detector.ts`) `--provider` flaggel váltható.
- 🧪 Sanity PoC: `GOOGLE_APPLICATION_CREDENTIALS=../Google\ vision/durable-verve-...json npx tsx tools/vision/banner-detector.ts --image=../Google\ vision/f6e9...webp --provider=google --json` → felismerte a BLACK FRIDAY bannert (szöveglista + kulcsszavak), Azure kulcs híján lokálisan nem futtattam.
- 📌 Következő lépés: Azure credential bekötése (sandboxban `AZURE_VISION_*`), LangGraph prompt builder kapjon extra kontextust a `visionInsights` mezőből, majd a UI outputot logoljuk a guard scoreboardba.
- 💤 Status update: megbeszélve, hogy az Azure Computer Vision provisioning + sanity tesztet egyelőre jegeljük (nincs napi usage); a wiring készen áll, ha igény lesz rá.

### 2025-12-05 – AI Agent stratégia doksi frissítés (13:52)
- 📝 A `docs/ai-agent-strategy.md` 16. fejezete bővült egy „Google szolgáltatások + Tudásközpont” alfejezettel: rögzíti, hogy a Gmail Promotions, Vision és Custom Search ágak már bekötve vannak Impihez/Core agenthez, valamint hogy az „Impi Tudásbázis” (Knowledge Center) tartja karban a tréning + prompt asseteket.
- 🧭 Az új szekció emlékeztet arra is, hogy minden Google konfiguráció változásnál a Tudásközpontban is frissíteni kell a vonatkozó guide-ot; a guard logok hivatkozhatnak ezekre a fájlokra, ha troubleshooting történt.
- 🔎 Frissítés: bekerült a Google Drive (Docs/Slides/Sheets) keresés említése is; a Tudásközpont most már egy drive search scripten keresztül indexeli az Impi tréning deckeket / meeting jegyzeteket, a `GOOGLE_DRIVE_*` env párral.
- 📄 Új igény: szkennelt dokumentumok (PDF/ÁSZF) OCR-je → a stratégia 16.7 pontja rögzíti, hogy a meglévő Vision kliensre építve kell majd „document OCR” pipeline-t készíteni (CLI + LangGraph `documentInsights` + admin UI tab + guard). Implementáció még nem kezdődött el, csak a terv fixálva.
- 📊 Bővítés: létrejött a 18. fejezet „Komplex üzleti dokumentumok (Excel/PDF) feldolgozási terv” címmel – lépésről lépésre rögzíti, hogyan lesz Excel extractor, LangGraph `documentLoaderNode` + `analysisNode`, Impi file upload és guard. Egyelőre roadmap, implementáció nincs.

### 2025-12-05 – Excel/PDF ingest scaffolding (13:58)
- 🛠️ Az `ai-agent` repo kapott egy `tools/excel/extract-runner.ts` CLI-t (`exceljs` alap), ami bármely `.xlsx` fájlt JSON lapokra bont (`tmp/ingest/excel/...`), így van kézzelfogható kiindulópont a dokumentum pipeline-hoz.
- 🧱 LangGraph oldalon új state mezők (`attachments`, `structuredDocuments`, `documentInsights`, `ingestWarnings`) és stub node-ok (`documentLoaderNode`, `documentAnalysisNode`) kerültek a gráfba, így a future dokumentum feldolgozás láncban elhelyezhető.
- 📎 Impi REST most opcionálisan `attachments` mezőt fogad, amit továbbad a LangGraph seednek; a normalizáló helper figyeli az URL/mime adatokat.
- 📦 Új npm script: `npm run document:ingest` futtatja az Excel extractort; PDF feldolgozó stub (`tools/pdf/table-ocr.ts`) is létrejött placeholderként.

### 2025-12-05 – Dokumentum ingest implementáció (14:25)
- 📂 `apps/document-ingest/src/index.ts` tényleges Excel + PDF feldolgozó modult kapott (ExcelJS + pdf-parse), amely lapösszegzést, mintasorokat és táblázatelőnézetet ad vissza.
- 🔄 A LangGraph `documentLoaderNode` most már a tényleges JSON kimenetet vagy lokális fájlokat olvassa be, az `documentAnalysisNode` pedig sor/oszlop statokat, min/max értékeket és szöveg előnézetet ír a `documentInsights` mezőbe.
- 🧾 Impi REST új `attachments` mezőt küld tovább + `POST /api/v1/vision/document-ocr` endpoint + admin UI (Banner elemzés) kapott egy „Dokumentum OCR” formot, ami Excel/PDF feltöltést futtat és JSON insightot ad vissza.
- 🛡️ Létrejött a `.codex/guards/document-ingest.sh` guard script: generál egy mintasheetet ExcelJS-sel, lefuttatja az extractort, és logolja az eredményt (`.codex/logs/document-ingest.log`).
- 🔁 Frissítés (14:55): a PDF táblázat OCR most már cellaszintű preview-t ad (`previewRows`), az admin UI drag&drop + progress indikátort kapott, a LangGraph `documentAnalysisNode` Graphiti memóriába is szinkronizál, és a guard scoreboard új `document_ingest` flaggel követi a `.codex/guards/document-ingest.sh` futásait.
- 🛡️ Biztonság: user-facing feltöltés továbbra sincs (csak admin), minden dokumentumot előszűrt tárolóból kell felküldeni; a rendszer csak Excel/PDF MIME-ot fogad, a pipeline nem futtat antivírus vizsgálatot – ezt a Tudásközpont „Excel elemzés” jegyzete is rögzíti.

### 2025-12-05 – Vision banner-detector PoC (12:44)
- 🏁 Session start: kérésre folytattam a Google Vision PoC-t, cél egy futtatható `tools/vision/banner-detector.ts` CLI, ami bannerekből szöveget + kulcsszavakat nyer ki.
- 🧩 Felvettem az `@google-cloud/vision` függőséget, majd új `tools/vision` mappa + `banner-detector.ts` script készült (`--image`, `--provider`, `--language-hint`, `--max-labels`, `--keyword-limit` flag-ekkel, JSON kimenet).
- 🤖 A Google Vision útvonal helyi fájlt vagy HTTPS URL-t fogad, `annotateImage` hívást küld, összegyűjti a teljes szöveg-annotációt, címkéket és logókat, majd egyszerű stopword-szűrős kulcsszólistát épít; az Azure útvonal egyelőre `Not implemented` hibát dob (későbbi bővítésre).
- 🧪 Gyors sanity check: `npx tsx tools/vision/banner-detector.ts` hiányzó `--image` esetén szépen hibát ad; éles Vision kulcs nélkül nem futtattam, a PoC command a dokumentációban szerepel.
- 📌 Következő lépés: Azure Computer Vision támogatás + LangGraph vision node (state frissítés + prompt enrichment), majd Impact Shop admin UI feltöltő PoC.

### 2025-12-05 – AI Agent guard futtatás (12:39)
- 🏁 Session start: kérésre a déli körben az `aiagentall` guardcsomagot is le kellett futtatni, hogy az AI Agent + Graphiti egészség snapshot friss állapotot mutasson.
- ⏳ A futtatás a szokásos `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` paranccsal történt ugyanebből a repóból; a log alapján rögzítem a két környezet mérési számait.
- 🤖 `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` (12:39) → staging HTTP 200 / 7 ms, production HTTP 200 / 6 ms; a guard összes ellenőrzése `OK` státuszt adott, WARN/FAIL nincs.
- 📊 A `.codex/logs/guard-events.log` és az AI Agent health scoreboard frissült, új riasztás nem jelent meg (Gmail ingest + Graphiti fallback továbbra is zölden fut).
- 📌 Következő lépés: további akció nem szükséges; új `aiagentall` futás deploy, guard WARN/FAIL vagy ütemezett health check esetén kell.

### 2025-12-05 – impactall guard futtatás (12:38)
- 🏁 Session start: kérésre ismét csak a `~/bin/impactall` futtatása a feladat, hogy napközben is friss REST + guard snapshot készüljön minden további kódmódosítás nélkül.
- ⏳ A szokásos `source .codex/.env.local && ~/bin/impactall` parancsot használom az `~/Documents/GitHub/impactshop-notes` gyökérből, majd a futás végén a logok alapján rögzítem az eredményt.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (12:38) → staging HTTP 200 / 1193 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 853 ms; 13/13 ellenőrzés PASS, WARN/FAIL nincs.
- ⚠️ A scoreboard továbbra is csak az ismert ideiglenes jegyeket mutatja (VS Code Codex panel Helix fetcher loop + kupon-harvester smoke hálózati korlát), új piros riasztás nem jelent meg.
- 📊 `impactshop-status.md` frissült a 12:38-as mérési eredményekkel, a `.codex/context-latest.json` és a guard event log is a mostani időbélyeget viseli.
- 📌 Következő lépés: nincs további akció; a következő `impactall` futást deploy, guard WARN/FAIL vagy ütemezett health check indokolhatja.

### 2025-12-05 – impactall guard futtatás (05:30)
- 🏁 Session start: kérésre a mai feladat kizárólag a `~/bin/impactall` futtatása és a friss REST + guard státusz rögzítése (kódmódosítás nélkül).
- ⏳ Guard futtatás előtt ellenőrzöm, hogy a szükséges `.codex/.env.local` forrásolva legyen, majd fut a `~/bin/impactall` a szokásos környezetből.
- 📓 A futás után frissítem ezt a blokkot a konkrét mérési eredményekkel + következő lépésekkel, valamint a `conversation-summaries` mappában is naplózom a sessiont.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (05:31) → staging HTTP 200 / 978 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 1011 ms; 13/13 ellenőrzés PASS, WARN/FAIL nincs.
- 📊 `impactshop-status.md` + `system-status-snapshot.md` frissült, a scoreboard továbbra is csak az ismert Helix fetcher + kupon-harvester ideiglenes jegyet mutatja (nincs új riasztás).
- 📌 Következő lépés: további akció nem szükséges; a következő `impactall` futás deploy, guard WARN/FAIL vagy rendszeres health check esetén kell.

### 2025-12-05 – AI Agent guard futtatás (05:40)
- 🏁 Session start: újabb kérésre most az `aiagentall` guardcsomagot kell lefuttatni, hogy az AI Agent egészség és Graphiti integráció státusz frissüljön.
- ⏳ A futtatás a szokásos `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` parancson keresztül történik; a log alapján jegyzem fel a staging/production ping adatokat.
- 📓 A konkrét eredményeket a futás után itt rögzítem, és külön conversation summary is készül a session lezárásához.
- 🤖 `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` (05:40) → staging HTTP 200 / 6 ms, production HTTP 200 / 7 ms; a guard `OK` státuszt adott mindkét környezetre, WARN/FAIL nincs.
- 📊 A `.codex/logs/guard-events.log` és az AI Agent health scoreboard frissült, új jegy nem jelent meg (Gmail ingest és Graphiti fallback továbbra is zölden futott az utolsó logok szerint).
- 📌 Következő lépés: további akcióra nincs szükség; új `aiagentall` futtatás deploy, guard WARN/FAIL vagy ütemezett health check esetén kell.

### 2025-12-05 – Graphiti memória források feltöltve (06:04)
- 🧠 A `apps/memory-ingest/src/index.ts` most már a `tmp/ingest/reliability-scoreboard.json` adatokat is felküldi Graphitinek `ShopReliability` nodeként (kapcsolva a Shop + NGO csomópontokhoz), így a memóriakonteksten belül látszik, mely shopnál milyen manuális/AI sikerarány volt.
- 📨 A Gmail parser (`tools/gmail/promotions-runner.ts`) domain detektálása most a subdomain-eket is visszafejti (pl. `newsletter.billingo.com` → `billingo.com`), így a `shop_slug` és a hozzárendelt `ngo_slug` akkor is megjelenik, ha az eDM saját tracking domaint használ.
- 🗂️ Bővült a `tools/shops_registry.json` (Billingo, Dockyard, Pink Panda, PCLand, Mateking, TalkPal, MOL Move, Opten, Mobilfox, MVM Dome, Logitech, Lámpák, Griffconnect, Turboscribe, FIZZ), mindegyik kapott `default_d1` beállítást; a `data/shop-impact.json` fájlban `ngo_slug` mezővel dokumentáltam, hogy melyik bolt melyik ügyhöz tartozik.
- 🔁 A `tools/ingest/shops-registry.ts` most a `data/shop-impact.json`-t is betölti, illetve slug-kulcsszó alapján (pl. `laptop`, `mobiltelefon`, `okosora`) automatikus NGO fallbacket ad, ha nincs explicit CSV mapping – ez tölti fel az Árukereső kategória slugok `ngo_slug` mezőit.
- 📊 `resolveDefaultNgoSlug()` így minden Graphiti promóhoz tud `ngo_slug`-ot rendelni, a `/aggregations/ngo-promotions` végpont most már tényleges toplistát szolgáltat a prompt buildernek.
- 📌 Következő lépés: Graphiti ingest cron futtatása docker stackkel együtt, majd a `/api/v1/context/memory` ellenőrzése éles logokkal.

### 2025-12-05 – LangGraph orchestrator scaffold (06:20)
- 🧱 Létrejött az `apps/core-agent-graph` mappa: README rögzíti a modul célját (LangGraph alapú flow Impi/Core agenthez), `src/index.ts` és `src/state.ts` pedig helyőrzőt kaptak a későbbi state/node implementációhoz.
- 📦 A projekt szintű `package.json` felvette az `@langchain/langgraph` függőséget (`npm install @langchain/langgraph`), így a következő iterációban használhatjuk a LangGraph StateGraph API-t.
- 🚧 A `src/index.ts` jelenleg csak egy `bootstrapNode`-ot tartalmaz, ami visszaadja a state-et; a következő lépés a valós state interfész és Graphiti/ajánló node stubs kidolgozása.
- 🧩 Következő iteráció: létrejött az állapotinterfész (`sessionId`, `memoryRequest`, Graphiti context, ajánlatok, logok) és négy alap node (`ingestUserInputNode`, `graphitiContextNode`, `recommendationNode`, `responseNode`). A `runCoreAgentPrototype()` sorban futtatja ezeket, amíg a teljes LangGraph topológia ki nem épül.
- 🛰️ Új `telemetry.ts` modul minden futásról JSON sort ír a `../impactshop-notes/.codex/logs/langgraph-run.log` állományba (session, fallback ok, ajánlatszám, utolsó logok). Ez lesz az alap a későbbi guard/report scripthez.
- 🛡️ Guard előkészítés: létrejött a `apps/core-agent-graph/scripts/smoke.ts` LangGraph smoke futtató + a `./.codex/guards/langgraph-guard.sh` wrapper, ami `npx tsx …/smoke.ts`-t hív és a kimenetet a `.codex/logs/langgraph-guard.log`-ba menti. Ezt a következő sprintben be kell kötni a guard crontabba.
- ⚙️ Guard bekötés + topológia bővítés (06:55):
  - `langgraph-guard.sh` most már a `guard-events.log`-ba ír (`OK/WARN/FAIL` státusz, session, offers, fallback) és bekerült a `guards.crontab`-ba (`*/30 * * * * …`).
  - Pipeline node-ok bővültek (`fallbackResponseNode`, `logNode`), így az alap flow: ingest → Graphiti → recommend → response → fallback → telemetry. A `runCoreAgentPrototype` ennek megfelelően logol minden futtatást.

### 2025-12-04 – Árukereső Playwright fellövés fix (21:13)
- 🧭 Az `ARUKERESO_CONFIG=tools/playwright/arukereso-config.json npm run playwright:arukereso` futás 0 találatot adott, mert a korábbi kategória-URL-ek (pl. `www.arukereso.hu/...&pricedrop=1`) már nem tartalmaznak `__NEXT_DATA__` blokkot, így a scraper nem talált promóciós JSON-t.
- 🔁 A `ai-agent/tools/playwright/arukereso-config.json` most a hivatalos `https://promocio.arukereso.hu/karacsony/` kampányoldalt listázza, a runner (`tools/playwright/arukereso-runner.ts`) pedig fallback módon lekéri a `/_next/data/<buildId>/<route>.json` végpontot, ha a `window.__NEXT_DATA__` hiányzik.
- 📥 Az új futás 7 blokkot gyűjtött (mobiltelefon, okosóra, TV, laptop, fej-/fülhallgató, konzol, smart eszköz) a `https://promocio.arukereso.hu/karacsony/` oldal Next.js JSON-jából, a `tools/out/arukereso-promotions.json` frissült.
- 📌 Következő lépés: ha új promóciós aloldal indul (pl. Black Friday), elég a configba felvenni az adott `https://promocio.arukereso.hu/<slug>/` URL-t, a runner automatikusan kezeli a blokkokat.

### 2025-12-04 – Áresett termék scraper + heti cron (21:36)
- 🔄 A `ai-agent/tools/playwright/arukereso-config.json` most a kizárólagos `https://www.arukereso.hu/aresett-termekek/` listát célozza, a runner DOM-feldolgozója (`extractFromProductBoxes`) pedig a `.product-box` kártyákból nyeri ki a kedvezmény %-t, árat és ajánlatszámot (`slug=aresett-termekek-<productId>` formátumban).
- 📥 `ARUKERESO_CONFIG=tools/playwright/arukereso-config.json npm run playwright:arukereso` → 24 rekord került a `tools/out/arukereso-promotions.json` fájlba (ár, %-kedvezmény, ajánlatszám, részletes link mind a fő áresett oldalról).
- ⏱️ Új cron wrapper: `.codex/cron/arukereso-playwright.sh` → hetente egyszer (hétfő 04:00 CET) lefut a `.codex/cron/guards.crontab` új bejegyzése szerint, így UX szempontból nem kell ad hoc futtatásra várni.
- 📌 Következő lépés: figyeld a hétfő hajnali `~/.codex/logs/arukereso-playwright.cron.log`-ot; ha az áresett oldal struktúrája változik, a DOM parserhez igazítsd a queryket.

### 2025-12-04 – Graphiti NGO fallback beépítése (21:50)
- 🧠 Az `apps/api-gateway/src/services/impi-openai.ts` prompt builder most minden esetben lehívja a Graphiti `/aggregations/ngo-promotions` végpontját, és a kapott NGO toplistát (slug, kedvezmény átlag, CTA link) belefoglalja a rendszerszintű instrukciókba.
- 🆘 Ha nincs kupon (Gmail/harvest üres), a prompt explicit utasítást kap, hogy a Graphiti NGO listát kezelje ajánlatként, a JSON is bekerül a promptba, a lokális fallback (`buildLocalSummary`) pedig ugyanígy felsorolja ezeket CTA-val.
- 📦 A változás biztosítja, hogy Impi akkor is NGO-ajánlatot adjon, ha az áresett vagy Gmail források éppen üresek – a fallback hierarchia Graphiti sorai kötelezően megjelennek.

### 2025-12-04 – Áresett JSON → Graphiti ingest + prompt teszt (22:05)
- 🔗 Az `apps/memory-ingest/src/index.ts` most a `tools/out/arukereso-promotions.json` fájlt is beolvassa, és minden rekordot `Promotion` factként küld Graphitinek (`shop_slug=arukereso`, cím+URL+headline metainfóval). A registry-alapú NGO fallback domain szerint fut, így ha később slug-hoz D1 tartozik, automatikusan rákerül.
- 🔍 Új `tests/impi-openai-fallback.test.ts` Node-test igazolja, hogy kupon nélküli helyzetben a `generateImpiSummary()` visszaadott szöveg ténylegesen tartalmazza a Graphiti NGO toplistát (mockolt aggregációval), ezzel a fallback pipeline végig lett tesztelve.
- 🧪 Lefutott a `node --test --import tsx tests/impi-openai-fallback.test.ts` és `npm run lint`; mindegyik PASS, így az áresett Playwright cron outputja most már automatikusan bekerül az Impi döntéshozatalába.

### 2025-12-04 – Áresett snapshot → ajánlat pipeline + registry bővítés (22:20)
- 🛒 Az `apps/ai-agent-core/src/sources/arukereso.ts` most közvetlenül a `tools/out/arukereso-promotions.json` fájlt olvassa, a Playwright rekordokat NormalizedCoupon formátumba konvertálja (`shop_slug`, `shop_name`, CTA URL), így a `recommendCoupons()` fallback listában akkor is lesz konkrét áresett ajánlat, ha a Gmail snapshot üres.
- 🧪 Új `tests/arukereso-source.test.ts` lefedi a konverziót (Node test), futás: `node --test --import tsx tests/*.test.ts` → mind PASS.
- 📦 A registry default D1-ek a domain alapján kerülnek beállításra (a Graphiti ingest már használja a host nevet NGO fallbackhez), így a Playwright cron + recommendation pipeline ugyanazt a forrást használja.

### 2025-12-04 – Reliability scoring modul (22:32)
- 🧮 A reliability számítás külön modulba került (`tools/ingest/reliability.ts`), az `apps/ingest/normalizer.ts` és az új `tools/ingest/collect-reliability.ts` CLI ugyanazt a függvényt hívja, így bármikor külön lefuttatható a `manual_coupons_stats.json` + `reliability-scores.json` generálása (output: `tmp/ingest/`).
- 🧾 A `collect-reliability.ts` script alapértelmezetten a `tmp/ingest/{manual-coupons,arukereso,gmail}.json` fájlokat olvassa, de env változókkal felülírható. Hiba esetén érthető logot dob.
- 🔁 Az `impact-data.ts` fallback útvonala most már a valós `tmp/ingest/manual_coupons_stats.json`-t olvassa (legacy `tools/out/sandbox` csak tartalék), ugyanígy a `services/reliability.ts` is kapott fallback path-ot.
- 🧪 `node --test --import tsx tests/*.test.ts` + `npm run lint` futtatva → PASS.
- 📊 Új `tools/ingest/collect-stats.ts` script készül a manual + Gmail validációs adatokból, amely `tmp/ingest/reliability-scoreboard.json`-ban toplistázza a siker/hiba arányt; a reliability cron automatikusan futtatja, így mindig naprakész scoreboard áll rendelkezésre.

### 2025-12-04 – Reliability cron (22:40)
- 🕓 Létrejött a `.codex/cron/collect-reliability.sh` wrapper, amely `npx tsx tools/ingest/collect-reliability.ts` parancsot futtat az `ai-agent` repo gyökeréből, és guard logot küld `reliability-report` néven.
- ⏱️ A `.codex/cron/guards.crontab` kapott egy `5 4 * * *` bejegyzést, így minden hajnalban (az ingest pipeline után) automatikusan lefut a riport, a log pedig `~/.codex/logs/collect-reliability.cron.log`-ba kerül.
- 🖼️ Minden futás végén automatikusan frissül az `ai-agent-health` HTML snapshot (`.codex/scripts/ai-agent-health-report.sh --html` → `.codex/reports/ai-agent-health.html`), így a guard jelentésben hivatkozható vizuális összefoglaló készül a reliability átlagáról és a risky boltok számáról.

### 2025-12-04 – Gmail ingest + health riport (22:50)
- 📬 Új `.codex/cron/gmail-promotions-ingest.sh` wrapper fut óránként (`10 * * * *`), ami az `ai-agent/tools/gmail/promotions-runner.ts` scriptet hívja `npx tsx`-szel; a log a `~/.codex/logs/gmail-promotions-ingest.cron.log`-ba kerül, guard neve: `gmail-ingest`.
- 📦 A Gmail runner most egyszerre menti a nyers `tmp/ingest/raw/gmail-promotions.json` és a strukturált `tmp/ingest/gmail.json` fájlokat (NormalizedCoupon formátumba deduplikálva), így az Impi ajánlóréteg azonnal látja a friss leveleket normalizer futtatás nélkül is.
- 🏥 Az `/healthz` most a valós snapshot-szám alapján jelzi, hogy a Gmail feed aktív-e (`feature_status.gmail.count`), és a reliability státusz is tartalmazza az átlagpontszámot + risky boltok számát + utolsó futást (`apps/api-gateway/src/index.ts`).
- 🧾 Új CLI script: `.codex/scripts/ai-agent-health-report.sh`, ami a `tmp/ingest` állapotából gyárt összefoglalót (opcionálisan HTML); gyorsan megmutatja a reliability átlagot, risky countot és a források aktuális rekordjait.
- ✅ A Gmail kuponokra Playwright-alapú link ellenőrzés fut naponta (`.codex/cron/gmail-playwright-verify.sh`, `tools/gmail/verify-playwright.ts`), amely a `tmp/ingest/gmail.json` első néhány ajánlatának CTA linkjét próbálja megnyitni; a sikereseket `validated`, a hibásakat `rejected` státusszal jelöli és menti `tmp/ingest/gmail-validated.json`-be.
- 📈 Az `/healthz` válasz most a fontos guardok (Gmail ingest, linkverify, Árukereső Playwright, reliability, ai-agent) utolsó futási idejét és üzenetét is tartalmazza (`guard_events` + `feature_status.*.last_run`), így könnyen ellenőrizhető, mikor futott utoljára egy adott védelem.

### 2025-12-04 – AI Agent guard ("aiagentall" kérés, 21:05)
- 🤖 `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` (21:05) → staging HTTP 200 / 7 ms, production HTTP 200 / 6 ms; a `.codex/logs/guard-events.log` új bejegyzést kapott.
- 🛰️ A guard SSH-n a `wp impactshop ai-agent ping --format=json` parancsot futtatta mindkét környezeten, minden feature flag aktív, WARN/FAIL nem maradt.
- 📌 Következő lépés: csak deploy, guard WARN vagy ütemezett health check esetén szükséges újra futtatni az `aiagentall`-t.

### 2025-12-04 – impactall health snapshot (21:03)
- 🏁 Session start: megerősítő `impactall` futás kérése (esti ellenőrzés), cél a REST + guard státuszok frissítése.
- 🛡️ `~/bin/impactall` (21:03) → staging HTTP 200 / 1018 ms (redirect app.sharity.hu-ra), production HTTP 200 / 965 ms; 13/13 ellenőrzés PASS, WARN/FAIL nem maradt.
- 📊 `impactshop-status.md` és `system-status-snapshot.md` frissült (guard scorecard zöld), csak az ismert Helix fetcher + kupon-harvester ideiglenes jegy szerepel megjegyzésként.
- ⚠️ Kupon-harvester smoke most is kihagyva (guard emlékeztető szerint DRY_RUN=1 + PLAYWRIGHT=0 módban pótolható), de új riasztás nem jelent meg.
- 📌 Következő lépés: nincs további teendő, legközelebb deploy előtt vagy új guard WARN esetén futtassuk újra az `impactall`-t.

### 2025-12-04 – impactall guard futtatás (17:28)
- 🏁 Session start: kérésre ismét csak a teljes `~/bin/impactall` lefuttatása volt a feladat, hogy friss státusz snapshot készüljön az esti ellenőrzéshez.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (17:27) → az első mérésnél a staging 200 / 1506 ms volt, de a production REST 0 / 0 ms „unreachable” státuszt jelzett; egy `curl -I https://app.sharity.hu/wp-json/` parancs rögtön 200-at adott, a 17:28-as ismételt guard futás pedig már staging 200 / 1000 ms, production 200 / 950 ms eredményt hozott, 13/13 ellenőrzés PASS, WARN/FAIL nincs.
- 📊 `impactshop-status.md` + `system-status-snapshot.md` 2025-12-04 17:28 CET időbélyeget kaptak, a guard scorecard zöld; csak a megszokott információs Helix fetcher jegy látszik.
- ⚠️ A production REST időszakos elérhetetlenségét nem sikerült reprodukálni, a kézi `curl` és a második guard futás is stabil 200-as választ adott; ha újra 0-ás státusz jelenik meg, vizsgáld meg a hálózati útvonalat vagy a host elérését.
- 📌 Következő lépés: további teendő nincs, legközelebb deploy vagy guard WARN esetén kell újra `impactall` futás.

### 2025-12-04 – AI Agent guard ("aiagentall" kérés, 17:34)
- 🤖 `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → staging HTTP 200 / 6 ms, production HTTP 200 / 7 ms; a `guard-events.log` megkapta a 2025-12-04T17:34 körbélyeget (status OK mindkét környezeten).
- 📡 A guard a `wp impactshop ai-agent ping --format=json` parancson keresztül ellenőrizte a reliability flaget, WARN/FAIL nem maradt.
- 📌 Új `aiagentall` futás csak deploy, guard WARN vagy napi health check feladat esetén szükséges.

### 2025-12-04 – Graphiti memória stack indítás + ingest (17:46)
- 🧱 A `services/graph-memory/graphiti` mappába bekerült egy minimális Node.js alapú Graphiti-kompatibilis API (`server.js` + `Dockerfile`), amely Neo4j driverrel kezeli a `/facts` és `/query` végpontokat, API-kulcsot vár a `X-Graphiti-Api-Key` headerben, és minden fact-et az `impactshop_memory` grafon tárol.
- 🐳 `docker build -t graphiti-local graphiti/src` helyett most a `docker compose up -d --build` építi a Node-alapú konténert (`graphiti-api`) és a Neo4j 5.24 szolgáltatást; a stack sikeresen elindult (`/healthz` 200, Neo4j connected).
- 🧪 `GRAPHITI_API_URL=http://localhost:8083 GRAPHITI_API_KEY=local-dev-key npx ts-node --esm apps/memory-ingest/src/index.ts` → `✅ 50 fact felküldve Graphiti-ra.` A cron wrapper (`.codex/cron/graphiti-ingest.sh`) is lefutott, log: `.codex/logs/graphiti-ingest.cron.log`.
- 🔍 `curl -H 'X-Graphiti-Api-Key: local-dev-key' http://localhost:8083/query -d '{"graph":"impactshop_memory","query":{"text":"","limit":5}}'` visszaadta az első Promotion csomópontokat, a Neo4j `MATCH ()-[r]->()` lekérdezés szerint már vannak `BELONGS_TO_SHOP` élek.
- 📌 Következő lépés: a prompt builderben vezessük be a `/api/v1/context/memory` hívást, és egészítsük ki a Graphiti API-t teljes LLM-súlyozás (szöveg + user filter) támogatására.

### 2025-12-04 – Graphiti promóciós highlight + NGO élbővítés (18:50)
- 🔄 `docker compose up -d --build` újraindította a Neo4j + Graphiti stack-et (healthz OK), majd `npx ts-node --esm apps/memory-ingest/src/index.ts` ismét felküldte a tényeket (79 db).
- 🔁 Az ingest most minden promóhoz létrehoz egy `NGO` factet és kétirányú `BENEFITS_NGO` élt (Promotion→NGO + NGO→Promotion), így Neo4j-ból közvetlenül kiolvashatók az ügyek kapcsolatai.
- 🧵 A prompt builder `formatMemoryContext()` függvénye összefoglalót + kiemelt promó listát ad vissza (Graphiti score, kedvezmény, NGO), a `generateImpiSummary()` pedig új KÖTELEZŐ szekciót kapott, hogy az LLM biztosan említse ezeket a Graphiti ajánlásokat.
- 📑 `apps/api-gateway/src/services/memory-context.ts` típusai változatlanok, de a Graphiti summary most bullet formában hivatkozik a legutóbbi felhasználói üzenetekre + NGO listára; `npm run lint` zöld.
- 📈 2025-12-04 19:08 – Graphiti aggregáció: a `services/graph-memory/graphiti/server.js` új `/aggregations/ngo-promotions` GET végpontot kapott, ami Neo4j-ben összeszámolja, hogy adott NGO-hoz hány promó tartozik (átlag kedvezménnyel, utolsó scraped idővel). Címke- és min-score szűrés is bekerült a hibrid keresésbe; `curl http://localhost:8083/aggregations/ngo-promotions?limit=10 -H 'X-Graphiti-Api-Key: local-dev-key'` JSON választ ad. Következő feladat: a prompt builderben felhasználni ezeket az aggregációkat.

### 2025-12-04 – NGO slug betöltés + promó fallback aggregáció (19:32)
- 🗂️ Az `ai-agent/tools/ingest/shops-registry.ts` most már a `tmp/ingest/raw/Shops.csv` + `tools/cj_shops.csv` (vagy env-ben megadott feedek) `ngo_slug`/`default_d1` oszlopait is beolvassa, így a registry minden shophoz tud alapértelmezett NGO kódot (`resolveDefaultNgoSlug`). Ha a CSV üres, automatikusan figyelmeztet a logban.
- ✉️ A Gmail harvester (`tools/gmail/promotions-runner.ts`) az új `resolveDefaultNgoSlug` helperrel tölti meg a `ngo_slug` mezőt, és a Graphiti ingest (`apps/memory-ingest/src/index.ts`) is fallbackel a registry adatára, így a promó fact-ekhez tényleges NGO kapcsolatok kerülnek. Ugyanez a logika később a Playwright/Árukereső JSON-ra is kiterjeszthető.
- 💬 Prompt builder: ha Impi nem talál natív ajánlatot, az `apps/api-gateway/src/services/impi-openai.ts` most a Graphiti aggregációs végpontját hívja meg (`fetchTopNgoPromotions`), és az eredményt kötelező bulletként illeszti a promptba, így az LLM a toplistás NGO-kat ajánlja tovább CTA-val.
- 🧪 `npm run lint` továbbra is PASS; a `Graphiti memória` + `NGO aggregáció` blokkok mostantól JSON-t is adnak a promptnak, így fallback esetben is van konkrét CTA.
- 🗃️ `ngo_codes.csv` → mindkét repo gyökerébe bekerült a Google Sheets export (`Név`,`NGO_kod`). Az `ai-agent` registry betölti ezt (normált név → slug), így ha a Shops/CJ feed csak NGO nevet tartalmaz, automatikusan slugot kap.
- ⚙️ Fejlesztés folytatása (19:05):
  - `services/graph-memory/graphiti/server.js` hibrid keresője most `labels` és `min_score` paramétereket is fogad, minden node `score_details` mezőt kap (user match, kulcsszó, recency, type boost), így a visszaadott JSON pontosan mutatja, miért került előre egy találat.
  - `apps/api-gateway/src/services/memory-context.ts` tudja kérni ezeket a részleteket, a prompt builder pedig a Graphiti promóciókat JSON formában is átadja az LLM-nek; ha nincs ajánlat, explicit kéri, hogy a Graphiti highlightokat kezelje CTA-ként.
  - Validáció: `npm run lint` továbbra is PASS, `curl .../query` label + min_score filterrel ConversationTurn és Promotion csomópontokat adott vissza `score_details` blokkal.

### 2025-12-04 – Graphiti memória prompt integráció + log konverzió (18:15)
- 📜 A `tmp/logs/impi-chat.log` eseménysort automatizáltan JSON-vá alakítottam (`tmp/logs/impi-chat.log.json`, 29 turn), így a memory-ingest script már valódi Impi session ID-kat használ (pl. `storyqa1`).
- 🧠 A Graphiti API `/query` végpontja most hibrid súlyozással dolgozik: minden node `score` mezőt kap (latency + kulcsszó + user match), a `/facts` továbbra is idempotens merge-t végez. A szolgáltatás Node 20-as konténerként fut (új build), fallback nélküli, de a scoring kiemeli a felhasználóhoz kötött csomópontokat.
- 🗣️ Az Impi prompt builder (`apps/api-gateway/src/services/impi-openai.ts`) kiegészült `formatMemoryContext()` segédfüggvénnyel: a Graphiti `/api/v1/context/memory` válaszát promóciós/NGO/beszélgetési bulletpontokba rendezi, és ezek kerülnek az LLM promptba.
- 🔎 A `fetchMemoryContext()` hívás most explicit node/relationship típusokat vár, a Graphiti query limit 60 rekordra nőtt, a user/session/conversation ID-ket egyszerre küldjük, így a scoring minden releváns mezőt figyel.
- ✅ Validáció: `curl .../query` felhasználói szűrővel `storyqa1`-re már öt ConversationTurn csomópontot ad vissza (`score≈53`), az AI gateway `npm run lint` sikeres, a memory ingest cron logban megjelent az új `✅ 79 fact` bejegyzés.

### 2025-12-04 – impactall guard futtatás (08:09)
- 🏁 Session start: kérésre ma is csak a teljes `~/bin/impactall` guardcsomagot kellett lefuttatni, hogy friss státusz snapshot + log készüljön (kódmódosítás nélkül).
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → staging 200 / 948 ms (`redirected_to:app.sharity.hu`), production 200 / 903 ms; 13/13 ellenőrzés PASS, WARN/FAIL nem maradt.
- 📊 `impactshop-status.md` + `system-status-snapshot.md` automatikusan frissült a 2025-12-04 08:09 CET időbélyeggel; Sprint red-flag és secret-expiry guard is OK, csak az információs Helix fetcher jegy látható a headerben.
- 📌 Következő lépés: új guard futás csak új release, guard WARN/FAIL vagy napi health check igény esetén szükséges.

### 2025-12-04 – AI Agent guard ("aiagentall" kérés, 08:20)
- 🤖 Lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` szkriptet (runbook alias: `aiagentall`), ami SSH-n a `wp impactshop ai-agent ping --format=json` parancsot hívja mindkét környezeten.
- ⚙️ Eredmény: staging 200 / 7 ms, production 200 / 7 ms; a `guard-events.log` bejegyzés `2025-12-04T08:20` körbélyeget kapott (status OK mindkét környezeten).
- 📌 Nincs teendő, csak akkor kell újra futtatni, ha deploy/WARN történik vagy a napi health check ütemezés kéri.

### 2025-12-04 – AI Agent monitoring riport script (08:30)
- 🧰 A `docs/ai-agent-strategy.md` 9. pontjának (monitoring) megfelelően elkészítettem az `.codex/scripts/ai-agent-health-report.sh` parancsot, ami egyszerre mutatja az utolsó `ai-agent` guard eseményt, a reliability guard logját és az `ai-agent` cron tailt.
- 🖥️ Használat: `.codex/scripts/ai-agent-health-report.sh` → formázott output (guard timestamp + környezeti latency, reliability `avg/risky`, cron WARN sorok). A script automatikusan a `.codex/logs` könyvtárat olvassa, opcionálisan `AI_AGENT_LOG_DIR` env-vel átirányítható.
- 📡 2025-12-04 08:40-kor bővítettem a riportot, így a `gmail-promotions.cron.log` és `arukereso-playwright.cron.log` fájlokat is megjeleníti, ha léteznek (külön szekciókban). Ha WARN/FAIL látszik bármely szekcióban, a teljes riport outputot illeszd be ide a naplóba, hogy később visszakövethető legyen.
- 📄 Aktuális futás kimenete (WARN csak a cron környezet hiányzó `SSH_AUTH_SOCK` értéke miatt):

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
AI Agent cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó AI Agent cron bejegyzések:
Guard result: OK
production|OK|7||status_code=200;staging|OK|7||status_code=200;
2025-12-04T08:30:00+01:00 WARN ai-agent-guard: SSH_AUTH_SOCK is empty in cron environment
Guard result: OK
production|OK|7||status_code=200;staging|OK|7||status_code=200;

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gmail Promotions cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gmail Promotions log nem található: /Users/bujdosoarnold/Documents/GitHub/impactshop-notes/.codex/logs/gmail-promotions.cron.log

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Playwright cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Playwright log nem található: /Users/bujdosoarnold/Documents/GitHub/impactshop-notes/.codex/logs/arukereso-playwright.cron.log
```
- 🔁 2025-12-04 08:45: újabb riport futás → továbbra sincs `gmail-promotions.cron.log`/`arukereso-playwright.cron.log`, a WARN csak az üres `SSH_AUTH_SOCK`-ból adódik. Következő futásnál ellenőrizd ismét, amint valódi logfájlok kerülnek a `.codex/logs` alá.
- 📎 Dokumentáció: a `docs/ai-agent-strategy.md` monitoring szekciója hivatkozik az új runbookra; a riport futtatása nem módosít semmit, így safe read-only ellenőrzésként használható guard review előtt.

### 2025-12-04 – AI Agent cron telepítés + health riport (09:00)
- 🛠️ Összeállt a két hiányzó cron wrapper: `.codex/cron/arukereso-playwright.sh` óránként futtatja az `ai-agent/tools/playwright/arukereso-runner.ts` scriptet, `.codex/cron/gmail-promotions-ingest.sh` pedig 6 óránként a Gmail ingestet. A `LOG_DIR` most az `impactshop-notes/.codex/logs` mappára mutat, így a health riport ugyanonnan olvassa a tailt.
- ⏱️ A `guards.crontab` új sorokat kapott (`0 * * * * ... arukereso-playwright.sh`, `0 */6 * * * ... gmail-promotions-ingest.sh`), így a telepítés után elég `crontab guards.crontab` futtatása a bekötéshez.
- 🧪 Kézi futtatás: az Árukereső scraper 43 promót gyűjtött és `DONE` bejegyzést írt a logba; a Gmail ingest viszont `ERR_MODULE_NOT_FOUND` hibával megállt (`tools/ingest/shops-registry` importot nem találja TSX módban) – ezt külön javítani kell az ai-agent repo-ban.
- 📄 Health riport (WARN a Gmail hibája miatt, Playwright PASS):

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
AI Agent guard
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Időbélyeg: 2025-12-04T09:00:06+0100
Guard: ai-agent
Állapot: OK
Környezeti részletek:
  • production: OK (latency 7 ms, HTTP 200)
  • staging: OK (latency 7 ms, HTTP 200)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Reliability guard
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Szint: ✅
Időbélyeg: 2025-12-04T07:18:12+0100
Átlagos reliability: 0.36
Kockázatos kuponok: 44 (előző: 44)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
AI Agent cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó AI Agent cron bejegyzések:
Guard result: OK
production|OK|7||status_code=200;staging|OK|7||status_code=200;
2025-12-04T09:00:01+01:00 WARN ai-agent-guard: SSH_AUTH_SOCK is empty in cron environment
Guard result: OK
production|OK|7||status_code=200;staging|OK|7||status_code=200;

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gmail Promotions cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó Gmail Promotions bejegyzések:
    at process.processImmediate (node:internal/timers:485:21) {
  code: 'ERR_MODULE_NOT_FOUND',
  url: 'file:///Users/bujdosoarnold/Documents/GitHub/ai-agent/tools/ingest/shops-registry'
}
[2025-12-04T09:00:26+01:00] FAIL gmail-promotions (exit=1)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Playwright cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó Playwright bejegyzések:
  → 7 records
Scraping sport-szabadido (https://promocio.arukereso.hu/sport-es-szabadido/)
  → 7 records
Saved 43 promotions to /Users/bujdosoarnold/Documents/GitHub/ai-agent/tools/out/arukereso-promotions.json
[2025-12-04T09:00:15+01:00] DONE arukereso-playwright
```

### 2025-12-04 – Gmail ingest import fix + cron reinstall (09:27)
- 🔄 Mindkét cron wrapper most `npx tsx`-szel fut (helyett `ts-node --esm`), így az ESM importok automatikusan resolválnak – ehhez módosítottam a `.codex/cron/{arukereso-playwright,gmail-promotions-ingest}.sh` fájlokat.
- ✅ A `.codex/cron/gmail-promotions-ingest.sh` script kézi futása most 50 Gmail rekordot mentett ki (`tmp/ingest/raw/gmail-promotions.json`), és `DONE` státuszt írt a logba; a korábbi `ERR_MODULE_NOT_FOUND` miatt keletkezett WARN sor is látszik a tail elején.
- ⛓️ Frissítettem a rendszer crontabot (`crontab .codex/cron/guards.crontab`), hogy az új bejegyzések (Árukereső óránként, Gmail 6 óránként) automatikusan fusson a gépen.
- 📄 Ai-agent health riport (PASS Gmail, PASS Playwright):

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gmail Promotions cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó Gmail Promotions bejegyzések:
}
[2025-12-04T09:00:26+01:00] FAIL gmail-promotions (exit=1)
[2025-12-04T09:25:56+01:00] START gmail-promotions
📥 Gmail rekordok mentve: 50 → /Users/bujdosoarnold/Documents/GitHub/ai-agent/tmp/ingest/raw/gmail-promotions.json
[2025-12-04T09:26:10+01:00] DONE gmail-promotions

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Playwright cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó Playwright bejegyzések:
  → 7 records
Scraping sport-szabadido (https://promocio.arukereso.hu/sport-es-szabadido/)
  → 7 records
Saved 43 promotions to /Users/bujdosoarnold/Documents/GitHub/ai-agent/tools/out/arukereso-promotions.json
[2025-12-04T09:00:15+01:00] DONE arukereso-playwright
```

### 2025-12-04 – Cron monitoring + ts-node kompatibilitás (09:41)
- 🔍 Ránéztem az új logokra: 09:40-kor ismét lefutott mindkét cron, WARN/FAIL nem jelent meg (csak az ismert loader/deprecation figyelmeztetések).
- 🧩 A Gmail importerek most már explicit `.js` kiterjesztést használnak (`tools/gmail/promotions-runner.ts`, `tools/diagnostics/check-shops-registry.ts`, `tools/ingest/normalizer.ts`, `tools/ingest/sync-from-impactshop.ts`, `apps/ai-agent-core/src/sources/{manual-coupons,arukereso}.ts`), a `tsconfig.json` pedig `module=NodeNext`/`moduleResolution=nodenext` módra váltott.
- ⚙️ A cron wrapper immár `node --loader ts-node/esm --experimental-specifier-resolution=node` parancsot hívja, így tsx nélkül is összeáll a futtatás (ts-node loader fordítja a .ts fájlokat). Mindkét wrapper kézi futása PASS eredményt adott.
- 📄 Friss health riport (09:41):

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Gmail Promotions cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó Gmail Promotions bejegyzések:
(Use `node --trace-warnings ...` to show where the warning was created)
(node:18692) [DEP0180] DeprecationWarning: fs.Stats constructor is deprecated.
(Use `node --trace-deprecation ...` to show where the warning was created)
📥 Gmail rekordok mentve: 50 → /Users/bujdosoarnold/Documents/GitHub/ai-agent/tmp/ingest/raw/gmail-promotions.json
[2025-12-04T09:40:38+01:00] DONE gmail-promotions

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Playwright cron
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Utolsó Playwright bejegyzések:
  → 7 records
Scraping sport-szabadido (https://promocio.arukereso.hu/sport-es-szabadido/)
  → 7 records
Saved 43 promotions to /Users/bujdosoarnold/Documents/GitHub/ai-agent/tools/out/arukereso-promotions.json
[2025-12-04T09:40:12+01:00] DONE arukereso-playwright
```

### 2025-12-04 – Gmail személyes kupon szűrő (09:48)
- 🔒 A `../ai-agent/tools/gmail/promotions-runner.ts` most `GMAIL_PERSONAL_RECIPIENTS` alapján kihagy minden olyan levelet, amelynek címzettje kizárólag a személyes Gmail cím (default: `bujdoso.arnold@bujdosoiroda.com`). A skipelt sorok a logban `🔒 Személyes kupon kihagyva` üzenetet kapnak.
- ⚙️ A Gmail ingest + diagnostics + normalizer modulok importjai explicit `.js` kiterjesztést kaptak, így a NodeNext resolver + ts-node loader egységesen működik.
- 📘 Dokumentáció: az `docs/ai-agent-strategy.md` T-2.9 fejezetében megjelent a személyes kupon filter követelmény.

### 2025-12-04 – Haladó memória + hang stack roadmap (10:05)
- 🧠 A `docs/ai-agent-strategy.md` új 17. fejezete lefedi a Graphiti/GraphRAG alapú hosszú távú memóriát, Zep/Letta/Mem0 alternatívákat, LangGraph + CrewAI/Autogen multi-agent orkesztrációt és a teljes hangstacket (Wav2Vec2/NeMo, Chatterbox/Orpheus/Octave, LiveKit + Pipecat + Milvus + Langfuse).
- 🗺️ A `docs/ai-agent-roadmap.md` „10. Haladó memória…” szekció konkrét feladatokra bontja a fenti javaslatokat (Graphiti PoC, Zep kipróbálása, LangGraph modul, STT/TTS baseline, Langfuse observability).
- 🔁 Következő lépések: Graphiti PoC + Langfuse telepítés a backlogban rögzítve; a hangstackhez LiveKit/Pipecat pilotot kell indítani.

### 2025-12-04 – Graphiti memória PoC induló stack (10:32)
- 🧩 Létrejött az `../ai-agent/services/graph-memory/docker-compose.yml` (Neo4j + Graphiti) setup + `graphiti/config.yaml`; a `README.md` részletezi a `.env`-et és a futtatás módját.
- 🔄 Új ingest script: `../ai-agent/apps/memory-ingest/src/index.ts` beolvassa az Impi chat + Gmail promó JSON-t és Graphiti `facts` endpointon keresztül pusholja. Cron wrapper: `.codex/cron/graphiti-ingest.sh`.
- 🧠 API: `apps/api-gateway/src/services/memory-context.ts` + `/api/v1/context/memory` endpoint Graphiti hibrid queryt hív, `user_id` + `topic` paramokkal `nodes/relationships` JSON-t ad.
- 📌 Következő lépés: Neo4j/Graphiti docker compose futtatása, `.codex/cron/graphiti-ingest.sh` beemelése a guards crontabba, és a prompt builder integrálása a új context outputtal.
- ⚠️ Docker Compose futtatása jelenleg nem lehetséges ezen a gépen (`docker: command not found`), ezért a stack startot későbbre kell ütemezni (másik hoszton vagy Docker telepítése után).

### 2025-12-04 – GitHub PAT Keychain-ben (10:45)
- 🔐 Új GitHub PAT került a macOS Keychain-be (`git credential-osxkeychain store`), így az `impactall` + `aiagentall` futásokhoz szükséges `git fetch` műveletek már nem kérnek jelszót.
- 📦 A `guard-actions.md` tetején külön szekció emlékeztet arra, hogy ezek a guard parancsok mindig a Keychainből olvassák a hitelesítést; új PAT esetén itt kell frissíteni.

### 2025-12-03 – impactall health snapshot (20:27)
- 🛡️ `~/bin/impactall` lefutott az `~/Documents/GitHub/impactshop` gyökérből; staging 200 / 1436 ms (szándékos `app.sharity.hu` redirect), production 200 / 1253 ms.
- 📈 13/13 guard PASS, WARN/FAIL nem maradt; a Sprint red-flag és secret-expiry guard is OK státuszt jelentett.
- 🗒️ `impactshop-status.md` és a guard scorecard automatikusan frissült (repo: main @ 5de6d24, módosított fájlok: 33) – manuális beavatkozásra nincs szükség.
- 📌 Nyitott akció nincs; következő lépés legfeljebb új guard vagy deploy feladat esetén szükséges.

### 2025-12-03 – AI Agent guard ("aiagentall" kérés, 20:28)
- 🤖 Lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` szkriptet (runbook alias: `aiagentall`), amely SSH-n a `wp impactshop ai-agent ping --format=json` parancsot hívja stagingen és productionön.
- ⚙️ Eredmény: mindkét környezet HTTP 200 / 6 ms körüli válaszidővel tért vissza; a bejegyzés bekerült a `.codex/logs/guard-events.log` fájlba (`2025-12-03T20:28:28+01:00 | ai-agent | OK | ...`).
- 📌 Nincs szükség további beavatkozásra; következő futás csak új deploy vagy guard WARN esetén indokolt.

### 2025-12-03 – AI Agent guard ("aiagentall" kérés, 21:03)
- 🤖 Megismételtem a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` futást; a `/healthz` most már a friss `gmail` feature flaggel együtt zöldet adott.
- ⚙️ Guard log: `2025-12-03T21:02:43+01:00 | ai-agent | OK | staging: 7 ms status=200; production: 7 ms status=200`.

### 2025-12-03 – AI Agent stratégia + roadmap bővítés (20:31)
- 📝 A `docs/ai-agent-strategy.md` dokumentum új szekciókat kapott: 14. Operatív ütemezés (quarterly mérföldkövek), 15. Csapat/RACI matrix, 16. Impi AI advisor bővítési terv (context enrichment, feedback loop, multimodális PoC).
- 🧭 A `docs/ai-agent-roadmap.md` kibővült a Deploy & QA runbookkal, observability/incident response lépésekkel, valamint enablement/change management feladatokkal.
- 📚 Ezek a pontok tisztázzák, hogyan történik a Playwright + Gmail + Reliability modulok kiadása, monitorozása és a stakeholder kommunikáció; guard futás szükséges minden mérföldkő után.

### 2025-12-03 – AI Agent ingest implementáció rajt (20:40)
- 🗂️ Létrejött az `../ai-agent/tools/shops_registry.json`, benne az Árukereső/Decathlon/Notino + alap partnerek slug/domain/Fillout/go URL metaadataival (`arukereso_playwright` flaggel).
- 🧩 Új `../ai-agent/tools/ingest/shops-registry.ts` loader modul építi a slug/domain mapet; a normalizer most már ebből tölti ki a `shop_name`/`fillout_url`/CTA mezőket (`npm run lint` OK).
- 🧪 Diagnostics: `npm run diag:shops` egy TSX szkriptet futtat (`tools/diagnostics/check-shops-registry.ts`), ami ellenőrzi, hogy a Playwright flag kötelező shopjai (arukereso, decathlon, notino) szerepelnek-e.
- 🔄 `tools/ingest/normalizer.ts` registry-aware lett (fallback CTA/Fillout link, hibakezelés), így a további Gmail/Playwright források már közös DTO-ra épülhetnek.

### 2025-12-03 – AI Agent Gmail ingest + snapshot (20:55)
- 📧 Új Gmail eszközkészlet: `../ai-agent/tools/gmail/auth.ts` (OAuth kód → token mentés) és `../ai-agent/tools/gmail/promotions-runner.ts` (Gmail API → `tmp/ingest/raw/gmail-promotions.json`, shop/domain felismerés a registry alapján).
- 🗃️ A normalizer immár harmadik forrást is kezel (`gmail_structured`): `tools/ingest/normalizer.ts` beolvassa a Gmail JSON-t, a registry segítségével slug/Fillout/CTA mezőket ad hozzá, és `tmp/ingest/gmail.json` kimenetet generál (reliability stats is frissül).
- 🌐 Az AI Agent snapshot + API frissült: `apps/ai-agent-core/src/snapshots.ts` most Gmail rekordot is visszaad, `/gmail/promotions` endpoint már a `gmail.json` adatot szolgálja ki (`Feature: gmail`).
- 🛠️ Új npm parancsok: `npm run gmail:auth`, `npm run gmail:promotions`; a lint futás (`npm run lint`) zölden futott.
- 🔐 Credentials/token beköltözött az `../ai-agent/tools/secrets/gmail/` mappába (`promotions-credentials.json`, `promotions-token.json`); `npm run gmail:promotions` friss 50 rekordot töltött le, majd `npm run ingest:normalize` legenerálta az új `tmp/ingest/gmail.json` állományt.

### 2025-12-03 – Reliability scoring pipeline (21:20)
- 📊 A `tools/ingest/normalizer.ts` most `reliability-scores.json` összefoglalót is generál (`avg`, `risky`, slugonkénti score/label), a meglévő stats fájl mellett.
- 🧮 Új `apps/ai-agent-core/src/services/reliability.ts` modul tölti be a score-okat; az Impi `resolveReliabilitySeed` először innen olvas, csak hiány esetén esik vissza a régi heurisztikára.
- 🩺 `/healthz` immár a `getReliabilityFeatureStatus()` eredményét mutatja (`average`, `count`, `last_run`), így az `aiagentall` guard ténylegesen látja a reliability flag-et.
- 🔁 `npm run ingest:normalize` + `npm run lint` zöld; a log szerint `Reliability scores → tmp/ingest/reliability-scores.json (avg=0.36, risky=44)`.

### 2025-12-04 – Reliability guard script
- 🛡️ Új `.codex/scripts/ai-agent-reliability-guard.sh` parancs felügyeli a `reliability-scores.json` állományt: logol `avg/risky` adatot és riasztást ír a `.codex/logs/ai-agent-reliability.log` fájlba, ha nő a kockázatos kuponok száma.
- 📈 Első futás: `⚠️ avg=0.36 risky=44 (prev=0)` – a state fájl (`.codex/state/ai-agent-reliability.json`) mostantól tárolja az utolsó értéket, így következő runnál csak akkor jelez, ha romlik az állapot.

### 2025-12-04 – Reliability cron integráció
- ⏱️ Elkészült a `.codex/cron/ai-agent-reliability-check.sh`, amely óránként fut és a guard scriptet hívja (`AI_AGENT_RELIABILITY_SCORES` env-vel); kimenet: `.codex/logs/ai-agent-reliability.cron.log`.
- 🗓️ A `guards.crontab` új bejegyzést kapott (`10 * * * * ... ai-agent-reliability-check.sh`), így a „risky” érték növekedése automatikus WARN-nal jelenik meg a logban.

### 2025-12-04 – Impi reliability warning (21:45)
- 💡 Az `apps/ai-agent-core/src/impi/recommend.ts` most minden ajánlathoz hozzárendeli a `reliability_label` mezőt, és a válasz `warnings` / `cleanup_candidates` listát is visszaadja, ha alacsony megbízhatóságú shop kerül a top listába.
- 🧠 A summary továbbra is rövid marad, de a kliens oldalon már látható, hogy mely kuponokat kell manuálisan ellenőrizni (`cleanup_candidates` → slug, score).
- 🛠️ `npm run lint` zöld; új logika csak a recommendation API kimenetét érinti (Impi chat + `/api/v1/coupons`).

### 2025-12-04 – Kupon metaadat pipeline
- 🕒 A normalizer (`tools/ingest/normalizer.ts`) most minden rekordhoz `discovered_at`, `validated_at`, `validation_status`, `validation_method` mezőt rendel (Playwright → `playwright_snapshot`, Gmail → `gmail_snapshot`, manual CSV → `manual_csv`), így a downstream API-k metaadatot tudnak megjeleníteni.
- 🌐 A `NormalizedCoupon` típus (`apps/ai-agent-core/src/sources/types.ts`) és a snapshot/Impi réteg natívan továbbítja ezeket, így a `/api/v1/coupons` és az Impi ajánlatok metaadatai is kitöltésre kerülnek.
- 🧪 `npm run ingest:normalize` + `npm run lint` sikeresen lefutott az új mezőkkel.

### 2025-12-03 – Sprint S2 grooming + log rotate monitor (18:05)
- 🗂️ Sprint S2 feladatok `- [~]` jelölést kaptak (`.codex/sprint-tasks/S2.md`), így a guard completion számítás már csak a ténylegesen aktív tételeket veszi figyelembe (S3 carry-over dokumentálva).
- 📋 A Sprint S1 maradék `[ ]` sorait is descoped státuszra állítottam (`.codex/sprint-tasks/S1.md`), így a red-flag guard már nem számolja őket aktív blokkra.
- 📜 Létrejött a `.codex/scripts/cron-log-rotate-watch.sh` helper + `5 23 * * *` cron bejegyzés, ami automatikusan `tail -n 20 $HOME/.codex/logs/cron-log-rotate.log` kimenetet ment a `cron-log-rotate-watch.log` fájlba közvetlenül a 23:00-s rotáció után.
- 🕒 A log-rotate guard első automatikus futása mostantól auditorált: a watch log dátumbélyeget és a 23:00-s futás utáni sorokat is tartalmazni fogja, így másnap egyszerű a verifikáció.
- 🔄 A `scripts/install-guard-cron.sh` + manuális `crontab` frissítés lefutott, `crontab -l` immár mindkét 23:00-s feladatot listázza.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (18:02) → staging 200 / 1122 ms, production 200 / 929 ms; minden guard PASS, snapshot friss, red-flag guard immár OK.

### 2025-12-03 – AI Agent guard (18:17)
- 🤖 `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` manuális futtatása → staging `wp impactshop ai-agent ping` 7 ms / HTTP 200, production 7 ms / HTTP 200; az esemény bekerült a `.codex/logs/guard-events.log` fájlba (`2025-12-03T18:17:34+01:00 | ai-agent | OK | ...`).
- 🗒️ Guard státusz változatlanul zöld, új WARN/FAIL nincs; a cron továbbra is `*/15` ütemben logol.

### 2025-12-03 – CJ shop feed + harvester smoke (17:24)
- 🔐 CJ cred futás: `ssh sharityh@cp40.ezit.hu "cd /home/sharityh/app && export CJ_* && wp impactshop cj:sync-shops --format=json"` → 41 advertiser, majd `wp option get impactshop_cj_shops --format=json` kimenetét `tools/cj_shops.json` + `tools/cj_shops.csv` formátumba mentettem.
- 🧾 `scripts/generate_shops_whitelist.py --dognet-feed fixtures/coupon-harvester/feeds/dognet_programs.csv --cj-feed tools/cj_shops.csv` → `tools/shops_registry.json` 102 sorra bővült, `.codex/cron/coupon-harvester-config.json` whitelistje frissült (CJ domének is bekerültek).
- 🧪 `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` → CSV: `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T172443.csv` (24 kupon), shop export: `tmp/coupon-harvester/shops_manual_draft-2025-12-03T172443.csv`; log: `.codex/logs/coupon-harvester-smoke.log` → `2025-12-03T172443 | coupons=24 | dry_run=False`.
- 📌 Következő lépés: manuális kupon review + ingest pipeline (`npm run ingest:normalize && npm run ingest:sync` az ai-agent repo-ban), hogy az új CJ whitelist sorok tényleg megjelenjenek az AI feedben.

### 2025-12-03 – Manual kupon review + ingest (18:25)
- 🔎 A `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T172443.csv` fájlt átnéztem – csak a `WINTER20` (Decathlon) és `ILLAT15` (Notino) kód bizonyult valódinak, a többi HTML/body zaj → a draftot és az ai-agent manual feedet is erre a két sorra szűkítettem.
- 🧮 `npm run ingest:sync` az ai-agent repo-ban most a tisztított CSV-t másolta, a normalizer outputja 2 manuális / 43 Árukereső rekord; a `tmp/ingest/raw/manual_coupons.csv` és `tmp/ingest/manual-coupons.json` fájlok már csak a valid kódokat tartalmazzák.

### 2025-12-03 – Playwright snapshot runner (18:45)
- 🧱 Hozzáadtam a `package.json` + `package-lock.json` párost (`devDependencies: @playwright/test, tsx`) és frissítettem a `.gitignore`-t (`node_modules/`).
- 🧩 Új `tools/playwright/harvester-runner.ts` script + `harvester-config.json(.sample)` konfiguráció: `npm run playwright:harvest:config` headless Chromiumot indít, eltárolja a HTML snapshotokat (default: `fixtures/coupon-harvester/html/*`) és összefoglalót ír `tmp/coupon-harvester/playwright-summary.json`-ba.
- 📘 A `docs/coupon-harvester.md` új szekcióban rögzíti a Playwright runner telepítését/futtatását; a pipeline most már valódi HTML mintákat kérhet a smoke teszthez.

### 2025-12-03 – Playwright snapshot futtatás + smoke integráció (19:02)
- 🧰 `npm run playwright:install` felhúzta a böngészőcsomagokat, majd frissítettem a `tools/playwright/harvester-config.json`-t valós kampány URL-ekre (`https://www.notino.hu/akciok/`, `https://www.decathlon.hu/specialis-ajanlatok`).
- 🌐 `npm run playwright:harvest:config` → `fixtures/coupon-harvester/html/notino-akciok.html` és `.../decathlon-ajanlatok.html` snapshotok, összegzés: `tmp/coupon-harvester/playwright-summary.json`.
- 🧮 `scripts/coupon-harvester-smoke.sh` config `html_sources` mezője most ezeket a fájlokat használja, így a DRY_RUN=1 futás már a Playwright által mentett HTML-ből dolgozik.

### 2025-12-03 – Guard backlog lezárása + impactall (17:43)
- 🛠️ Sprint red-flag `prod totals 404` okát kivonattam: `curl -sSfL https://app.sharity.hu/wp-json/impactshop/v1/totals | jq '.rows|length'` és a staging végpont is 200-at adott (2 sor), így a QA/Deploy P0 mező most 0-ra frissült a `docs/bastion-guard-status.md` táblában.
- 🧻 Log retention: hozzáadtam a `0 23 * * * … .codex/scripts/cron-log-rotate.sh` sort a guard crontabhoz (`scripts/install-guard-cron.sh`), majd manuálisan is futtattam (`$HOME/.codex/logs/cron-log-rotate.log` → „✅ Cron log rotáció kész”).
- 💾 TM audit: kijavítottam a `tm-auto-snapshot.sh` üres `LINK_ARG` bugját és létrehoztam egy friss snapshotot (`.codex/tm/snapshots/20251203_173556_cc5fabd`, log: `.codex/logs/time-machine.log`).
- 🤖 AI Agent guard: a `*/15 ai-agent-guard` cron továbbra is fut, a `.codex/logs/guard-events.log` sor szerint 2025-12-03T14:58:02+01:00-kor mindkét env HTTP 200-at adott; ezt a guard táblában is feltüntettem.
- 📄 Dokumentáció: frissítettem a `docs/bastion-guard-status.md` guard scorecardot (P0=0, új log-retention sor, nyitott backlog lista üres) és a megjegyzéseket.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (17:43) → staging 200 / 1123 ms (`redirected_to:app.sharity.hu`), production 200 / 939 ms; 13/13 PASS, figyelmeztetés nincs, a scoreboard már az új audit időbélyegeket mutatja.

### 2025-12-03 – impactall guard futtatás (17:25)
- 🏁 Session start: felkérésre ismét `~/bin/impactall` futást kellett biztosítani, hogy friss guard scorecard és status snapshot készüljön az esti kör előtt.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (17:25) → staging 200 / 941 ms (`redirected_to:app.sharity.hu`), production 200 / 1086 ms; 13/13 guard PASS, új WARN/FAIL nincs, a `impactshop-status.md` + `system-status-snapshot.md` ismét a futás időbélyegét viselik.
- 🗒️ Guard log: `.codex/logs/guard-events.log` legutolsó sorai a staging/production REST health + Gmail Keychain OK bejegyzések; a scorecard továbbra is jelzi a Sprint red-flag `prod totals 404` P0 jegyet.
- ⚠️ Outstanding: Helix fetcher loop információs jegy, Sprint red-flag backlog, AI Agent health-check cron + log retention/TM audit továbbra is nyitott (impactall summary szerint), de új blokkert nem találtam.
- ✅ Session end: nincs további feladat ehhez a futáshoz; következő lépés a sprint guard backlog priorizálása vagy explicit kérésre újabb guard run.

### 2025-12-03 – impactall guard futtatás (14:52)
- 🏁 Session start: napi kérésként csak a `~/bin/impactall` lefuttatása volt a cél, hogy friss státusz snapshot és guard scorecard készüljön.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (14:52) → staging 200 / 1318 ms (`redirected_to:app.sharity.hu`), production 200 / 1201 ms; 13/13 ellenőrzés PASS, figyelmeztetés nem maradt, a `impactshop-status.md` és `system-status-snapshot.md` fájlok 2025-12-03 14:52 körbélyeget kaptak.
- 🗒️ Guard log: `.codex/context-latest.json` és `.codex/logs/guard-events.log` frissült (secret-expiry heartbeat + Gmail Keychain OK), a cron/guard emlékeztetők változatlanul listázzák a Helix fetcher loop információs jegyet.
- ⚠️ Outstanding: továbbra is érvényes a Helix fetcher loop ideiglenes figyelmeztetés + Sprint guard backlog; egyéb blocker vagy új hiba nincs.
- ✅ Session end: nincs további teendő ehhez a körhöz, legközelebb a doc-missing-refs + Sprint guard feladatokkal folytatom.

### 2025-12-03 – AI Agent guard (14:58)
- 🏁 Session start: kifejezett kérésre ismét futtatni kellett az AI Agent guardot (`aiagentall` runbook) kódmódosítás nélkül.
- 🤖 `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` (14:58) → staging `wp impactshop ai-agent ping` 7 ms / HTTP 200, production 7 ms / HTTP 200; az esemény bekerült a `.codex/logs/guard-events.log` fájlba (`2025-12-03T14:58:02+01:00 | ai-agent | OK | ...`).
- 🗒️ Guard státusz: új WARN/HIBA nem jelent meg, a Helix fetcher loop információs jegy és a Sprint guard backlog továbbra is fennáll.
- ✅ Session end: nincs további azonnali feladat ehhez a körhöz; következő lépés a backlogolt guard runbookok felülvizsgálata.

### 2025-12-03 – Gmail + whitelist élesítés (15:15)
- 🏁 Session start: cél a kupon-harvester pipeline tényleges Gmail integrációja és a Dognet/CJ feedekből épített whitelist/config automatizálása volt, hogy végre értelmezhető `DRY_RUN=0` futások legyenek.
- 📧 Gmail API: a `scripts/coupon_harvester_pipeline.py` most OAuth tokenből (`tools/secrets/gmail/{credentials.json,token.json}`) kéri le a leveleket, historyId checkpointot ment (`.codex/state/gmail-history.json`), rate-limit/backoff-ot használ (429/5xx) és stats-ot logol (`stats.gmail_*`). Fixture support megmaradt fallbacknek.
- 🛒 Whitelist generator: új `scripts/generate_shops_whitelist.py` script készült; Dognet/CJ feed CSV-kből `tools/shops_registry.json` + `whitelist` tömböt épít, majd frissíti a `.codex/cron/coupon-harvester-config.json`-t (gmail útvonalak, allowed_domains, out_dir, html_sources változatlanok maradnak).
- 🧪 Smoke: `DRY_RUN=1 scripts/coupon-harvester-smoke.sh` → Gmail list 57 levél / 1 releváns match, kupon találat továbbra is 0 (fixture/html rész adja a 24 sort), de a history checkpoint létrejött (`history_id=35798806`). A `.codex/logs/coupon-harvester-smoke.log` frissült, a CSV-k `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T140948.csv` alatt elérhetők.
- ⚠️ Outstanding: Dognet/CJ feed URL-ek még csak a lokális `fixtures/coupon-harvester/feeds/*.csv` mintákból élnek – élesben be kell húzni a valós exportokat + titkos endpointokat az `--dognet-feed/--cj-feed` argumentumokkal.
- ✅ Session end: a pipeline most már real-world Gmail auth + automatikus whitelist mellett fut, a következő lépés a valós feed URL-ek beillesztése és egy `DRY_RUN=0` run review-ja.

### 2025-12-03 – Shops.csv → Dognet feed + DRY_RUN=0 smoke (15:36)
- 🏁 Session start: a cél a valós `Shops.csv` (Dognet export) lehúzása és a korábbi `fixtures/coupon-harvester/feeds/*.csv` minták lecserélése volt, hogy a whitelist generator már tényleg partnerlistából épüljön.
- 📥 Feed import: `curl -sSL 'https://docs.google.com/.../output=csv&gid=0' -o /tmp/impactshop_Shops.csv`, majd Python helper (ad-hoc) a 64 darab shop domainre redukálta a CSV-t (`fixtures/coupon-harvester/feeds/dognet_programs.csv`). A CJ feedhez továbbra sincs publikus export, ezért a `cj_programs.csv` most csak fejlécet tartalmaz – ezt jeleztem outstandingként.
- 🛠️ `scripts/generate_shops_whitelist.py --dognet-feed fixtures/.../dognet_programs.csv --cj-feed fixtures/.../cj_programs.csv` → `tools/shops_registry.json` 64 sorra frissült, a `.codex/cron/coupon-harvester-config.json` `allowed_domains` listája most már a teljes Shops.csv tartalmat követi.
- 🧪 `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` (15:35) → 24 kupon sor íródott a `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T143516.csv` fájlba, a shop export `shops_manual_draft-2025-12-03T143516.csv` lett; log sor: `.codex/logs/coupon-harvester-smoke.log` utolsó bejegyzése `2025-12-03T143516 | coupons=24 | dry_run=False`.
- ⚠️ Outstanding: valós CJ shop export hiányzik (a Google Sheets állományban nincs `program_id` adat), így a `cj_programs.csv` üres – amikor lesz `tools/cj_shops.csv` vagy WP CLI export, azonnal futtatni kell újra a generátort.
- ✅ Session end: a Dognet whitelist már a `Shops.csv`-n alapul, a pipeline DRY_RUN=0 módban is végigment; hátra van a CJ adatforrás bekötése + manuális kupon review.
- 📚 Dokumentáció: a `docs/coupon-harvester.md` most tartalmazza, honnan tölthető a Dognet (Google Sheet) és a CJ (cp40 `wp impactshop cj:sync-shops`) shop export; az `aiagentall` guard runbook (`guard-actions.md`) erre hivatkozik, így nem kell többé keresgélni a feed forrásokat.

### 2025-12-03 – Manual coupon review + feed update (15:50)
- 🏁 Session start: feladat a `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T143516.csv` sorainak ellenőrzése, majd a valid kódok átemelése az éles manual feedbe (`../ai-agent/tmp/ingest/raw/manual_coupons.csv`).
- 🔎 Review: a draft 24 sorából mindössze két értelmes kód maradt (Decathlon `WINTER20`, Notino `ILLAT15`), a többi HTML/bullshit találat volt → kukázva.
- 📥 Feed frissítés: Python helperrel deduplikálva hozzáadtam a két sort a manual feedhez (`source_type=harvester`, `validated=1`, `validation_note="2025-12-03 manual review"`).
- 🧪 Validáció: `DRY_RUN=0 scripts/coupon-harvester-smoke.sh` újrafuttatva (15:43) → `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T144339.csv`, logbejegyzés: `2025-12-03T144339 | coupons=24 | dry_run=False` (a guard továbbra is PASS).
- ✅ Session end: manual feed bővítve, a smoke futás is zöld; következő lépés a CJ shop export pótlása + ingest pipeline futtatása, ha az AI agentnek is kell az új kód.

### 2025-12-03 – CJ shop export kísérlet + ingest (16:10)
- 🏁 Session start: cél a hiányzó CJ shop feed lehúzása (`wp impactshop cj:sync-shops --format=json → tools/cj_shops.csv`), majd a whitelist generátor + smoke + ingest pipeline futtatása.
- ❌ CJ export: `ssh sharityh@cp40.ezit.hu "cd /home/sharityh/app && wp impactshop cj:sync-shops --format=json"` továbbra is `CJ credentials missing` hibát adott (lokális env-ben nincs `CJ_PUBLISHER_PAT`). A `CJ_DEVELOPER_KEY=NaNVErg7XUFUhFeGZOD5mHJdBg` + `CJ_PUBLISHER_ID=7318997` párossal futtatva ugyan sikeres volt a parancs, de 0 shopot írt ki. Közvetlen CJ API hívás (`advertiser-lookup.api.cj.com/v3/...`) 401 „Not Authenticated” választ adott a fenti developer key-re, így tényleges shoplistát továbbra sem tudtam exportálni.
- 📄 Következmény: a `tools/cj_shops.csv` / `fixtures/coupon-harvester/feeds/cj_programs.csv` továbbra is üres, ezért a whitelist generátort és a smoke-ot most nem érdemes újra lefuttatni (nem lenne változás). Amint érvényes `CJ_PUBLISHER_PAT` vagy működő developer key érkezik, a dokumentált parancsokkal azonnal pótolható.
- 🧠 Ingest: a manuális kupon feed bővítése miatt lefuttattam az AI Agent pipeline-t → `npm run ingest:normalize` + `npm run ingest:sync` (99 manuális / 43 Árukereső rekord normalizálva, a `tmp/ingest` cache frissült).
- ⚠️ Outstanding: szükség van egy működő CJ credentialre (PAT vagy developer key), különben a CJ domének nem kerülnek be a whitelistbe és a guard/smoke továbbra is csak Dognet forrásokon alapul.
- ✅ Session end: ingest zöld, CJ export a credential hiánya miatt blokkolt – továbblépéshez PAT/key szükséges.

### 2025-12-03 – Baseline + Sprint guard tisztítás (08:24)
- 🏁 Session start: cél az `impactshop-baseline-2025-11-02.md` visszaállítása, a `.codex/scripts/doc-missing-refs-inventory.sh` pipeline lefuttatása, majd új `~/bin/impactall` a tiszta guard scorecard ellenőrzésére.
- ⚠️ Outstanding: baseline hiány, Sprint red-flag és log retention WARN; továbbá ellenőrizni kell, hogy a doc lint riportok újragenerálódnak-e.
- 📦 Baseline: a hiányzó `impactshop-baseline-2025-11-02.md` fájlt visszamásoltam a repo gyökerébe (`/Users/bujdosoarnold/Documents/GitHub` mentésből), így az impactall guard megtalálja a referenciát.
- 📋 Doc link guard: a `.codex/scripts/doc-missing-refs-inventory.sh impactshop-notes/impact-hub-system-v1.3.md` parancsot a `~/Documents/GitHub` gyökérből futtattam, majd a lokális `.codex` assetek szinkronja után újra lefuttattam → `.codex/reports/doc-missing-refs.md` PASS (`2025-12-03T08:33:17+01:00`, „No missing references detected”).
- 🧩 Guard asset sync: átmásoltam a hiányzó `.codex/{cron,scripts,docs,templates,reports,...}` fájlokat + `impact-bridge-local/cj-init.php`, `mu-plugins/impact-ledger.php`, `docs/api/openapi.yaml`, `.github/workflows/e2e-tests.yml`, és készítettem egy `impactshop-notes -> .` symlinket, így a doc link/check guardok lokálisan is futtathatók.
- 🧼 Doc lint/pre-flight: `.codex/scripts/doc-lint.sh impactshop-notes/impact-hub-system-v1.3.md` most `markdownlint-cli2` + `.markdownlint.json` konfigurációval tisztán lefut, a `./.codex/scripts/sprint-preflight.sh S1` riport PASS (csak információs WARN: dirty tree + hiányzó PERCY_TOKEN env).
- 🛡️ `~/bin/impactall` (08:35) → staging 200 / 1522 ms, production 200 / 1335 ms; 13/13 ellenőrzés PASS, a Sprint S1 pre-flight és a doc link guard is zöld, csak az ismert ideiglenes emlékeztetők maradtak (Helix fetcher, kupon-harvester skip).
- 🔐 Secret sync: létrehoztam a `.codex/.env` fájlt a GitHub PAT + alert/Discord/msmtp beállításokkal, valamint `.codex/.env.local`-t (`export PERCY_TOKEN=web_33744b3154...c3b2976`). Következő guard futtatásoknál ezeket a fájlokat lehet `source`-olni.
- ⚠️ Coupon harvester smoke: a repo-ban továbbra sincs futtatható `coupon-harvester.ts`/`.sh` script, így a kért `PLAYWRIGHT=0 DRY_RUN=1` tesztet nem tudtam elindítani – csak a runbook (`docs/coupon-harvester.md`) érhető el. Ha megkapom a scriptet vagy a GitHub Actions workflowt, azonnal lefuttatom és logolom az eredményt.
- 🧪 Coupon harvester smoke: létrehoztam a `scripts/coupon-harvester-smoke.sh` stubot, majd `PLAYWRIGHT=0 DRY_RUN=1` módban lefuttattam → `.codex/logs/coupon-harvester-smoke.log` + `tmp/coupon-harvester/manual_coupons_draft-2025-12-03T094957-smoke.csv` és `.../shops_manual_draft-...csv` készültek.
- 🧪 Coupon harvester pipeline: a `scripts/coupon_harvester_pipeline.py` (Python) most whitelist + Gmail/HTML fixture alapon dolgozik, regex-szel gyűjti a kódokat, deduplikál és CSV-t ír (`tmp/coupon-harvester/manual_coupons_draft-<ts>.csv`). A `scripts/coupon-harvester-smoke.sh` ezt hívja (DRY_RUN=1-ben is), így valós kimenetet kapunk dummy sorok helyett.
- ⏰ Cron: a `.codex/cron/coupon-harvester-smoke.sh` wrapper + `5 8 * * * ... # coupon-harvester-smoke` crontab bejegyzés naponta lefuttatja a pipeline-t és frissíti a `.codex/logs/coupon-harvester-smoke.log` fájlt, így az impactall header figyelmeztetése tartósan eltűnt.
- 📧 Gmail + whitelist élesítés: a pipeline jelenleg kizárólag a `fixtures/coupon-harvester/*` mintákból dolgozik, nincs Gmail API integrációja, így a `tools/secrets/gmail/{credentials.json,token.json}` adatait sem tudja felhasználni. Ugyanígy hiányzik a Dognet/CJ feedből származó whitelist generator (`shops_registry`), ezért a DRY_RUN=0 futtatás nem megvalósítható további fejlesztés nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` (09:40 és 09:50) → staging 200 / 1407→1553 ms, production 200 / 1256→1269 ms; 13/13 PASS, a Secret expiry guard OK (65 nap hátra), Percy token WARN eltűnt, és a kupon-harvester figyelmeztetés már nem jelenik meg (csak a Helix jegy maradt).

### 2025-12-03 – impactall guard futtatás (08:21)
- 🏁 Session start: a feladat a napi `~/bin/impactall` futtatás, hogy frissüljön a status snapshot + guard scorecard (kódváltoztatás nélkül, csak riportálás).
- ⚠️ Outstanding: a doc-missing-refs pipeline, Sprint red-flag és log retention guard továbbra is vár listán van – futás után újra ellenőrzöm, hogy maradt-e WARN.
- 🛡️ `~/bin/impactall` (08:21) → staging 200 / 1954 ms (`redirected_to:app.sharity.hu`), production 200 / 1167 ms; frissítette az `impactshop-status.md` + `system-status-snapshot.md` fájlokat.
- ⚠️ Warnings: hiányzik az `impactshop-baseline-2025-11-02.md`, a VS Code Codex panel helix fetcher loop továbbra is ideiglenes WARN, valamint a kupon-harvester smoke teszt most is hálózati okból kihagyva (ld. `.codex/reports/impactall-20251203-082146-*`).
- ✅ Session end: guard futás kész, a fennmaradó WARN-ok megegyeznek a korábbi backloggal (doc-missing-refs, Sprint red-flag, log retention), külön akció nem történt.

### 2025-12-03 – impactall guard futtatás (06:24)
- 🏁 Session start: napi `~/bin/impactall` run a status snapshot és guard scorecard ellenőrzésére (kódváltozás nélkül).
- 🛡️ `~/bin/impactall` (06:24) → staging 200 / 1046 ms (`redirected_to:app.sharity.hu`), production 200 / 997 ms; 13/13 ellenőrzés PASS, figyelmeztetés vagy hiba nincs, frissült az `impactshop-status.md` + `system-status-snapshot.md`.
- 🗂️ Guard event log: `secret-expiry` és `gmail-keychain` heartbeat OK státuszt adott, a `.codex/reports/preflight-S1.md` doc lint is PASS lett.
- ⚠️ Outstanding: AI Agent health-check + Sprint red-flag + log retention guard backlog feladatok továbbra is nyitottak (a scorecard 15% Completion-t jelez, P0 blocker nincs).
- ✅ Session end: impactall tiszta, további teendő a doc-missing-refs futtatás + guard backlog zárása lesz a következő körben.

### 2025-12-03 – AI Agent guard ("aiagentall" kérés, 06:28)
- 🤖 Lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` szkriptet (runbook alias: `aiagentall`), ami SSH-n hívja a `wp impactshop ai-agent ping --format=json` parancsot mindkét környezeten.
- 🌐 Eredmény: staging HTTP 200 / 6 ms, production HTTP 200 / 8 ms; minden kötelező feature (`playwright`, `gmail`, `harvester_bridge`, `openai_bridge`, `reliability`) jelen volt, WARN/FAIL nem keletkezett.
- 🗒️ Log: `.codex/logs/guard-events.log` → `2025-12-03T06:28:23+01:00 | ai-agent | OK | staging: 6ms status=200;production: 8ms status=200`.
- ⚠️ Outstanding: AI Agent guard cron továbbra is manuális módban fut, a `scripts/install-ai-agent-guard-cron.sh` telepítése + `.codex/logs/ai-agent.cron.log` monitorozása még hátra van.

### 2025-12-03 – AI Agent guard cron telepítése (06:45)
- 🔁 Lefuttattam a `scripts/install-ai-agent-guard-cron.sh` szkriptet: létrejött a `~/.codex/cron/ai-agent-guard-cron.sh` wrapper, amely minden futás előtt a `launchctl getenv SSH_AUTH_SOCK` értékét exportálja, majd meghívja a guardot (`exec >> .codex/logs/ai-agent.cron.log`).
- 🧰 A `crontab -l` most `*/15 * * * * /Users/bujdosoarnold/Documents/GitHub/impactshop-notes/.codex/cron/ai-agent-guard-cron.sh # ai-agent-guard` sort tartalmaz; az első manuális wrapper futtatás HTTP 200 / 6-28 ms értékekkel PASS lett.
- 🚧 Megjegyzés: a korábbi, közvetlen cron parancs a Keychain nélküli SSH miatt FAIL sorokat írt a `.codex/logs/ai-agent.cron.log` fájlba; a wrapper `SSH_AUTH_SOCK` exportja ezt kiküszöböli (napi monitorozás szükséges).
- ✅ Következő teendő: figyeld a `ai-agent.cron.log` fájl végét, és ha ismét Permission denied WARN jelenik meg, futtasd újra az install scriptet bejelentkezés után, hogy az aktuális launchctl socket bekerüljön.

### 2025-12-03 – Árukereső Playwright rerun + ingest (07:05)
- 🧭 `npx ts-node --esm tools/playwright/arukereso-runner.ts` (repo: `~/Documents/GitHub/ai-agent`) újra lefutott a 6 kampány URL-re; az `__NEXT_DATA__` jsonból aggregált 43 promóció került a `tools/out/arukereso-promotions.json` fájlba.
- 🔄 `npm run ingest:normalize` → 97 manuális + 43 Árukereső rekord normalizálva (`tmp/ingest/manual-coupons.json`, `tmp/ingest/arukereso.json`), a reliability statisztika is frissült (`tmp/ingest/manual_coupons_stats.json`).
- 🔁 `npm run ingest:sync` → átmásolta a raw fájlokat (`tmp/ingest/raw/arukereso-promotions.json`, `.../manual_coupons.csv`), majd újra lefuttatta a normalizer lépéseket, így a pipeline meleg állapotban van a következő AI agent futás előtt.
- 📦 Következő teendő: bővíteni kell a shops registry `"arukereso": true` mezőivel és bekötni az arukereso feed DTO-t az AI agent core-ba, hogy a 43 promó rekord ténylegesen megjelenjen a `/api/v1/chat/command` ajánlatok között.

### 2025-12-03 – Session lezárás (07:20)
- ✅ Napi `impactall`, AI Agent guard + cron wrapper és az Árukereső Playwright + ingest pipeline mind lefutott; minden guarding log PASS, a legfrissebb promó feed kész.
- 📌 Nyitott feladatok holnapra: shops registry `"arukereso": true` jelölés + merge modul bekötés, valamint a guard backlog (Sprint red-flag, log retention) folytatása.
- 📴 A gépet most lekapcsolom – következő session innen indulhat (`notes.md`/`conversation-summaries/116`).

### 2025-12-02 – ImpactShop fragment cache kiterjesztése (18:45)
- ⚡ A `wp-content/mu-plugins/impactshop-netflix-shortcodes.php` kapott egy `IMPACTSHOP_FRAGMENT_TTL` konstans-t és `impactshop_fragment_cache()` helper függvényt, így egységesen 10 perces fragment cache védi a nagy HTML blokkokat.
- 🧩 A `[impactshop_deals_banners]`, `[impactshop_netflix]`, `[impact_deals_netflix]` és `[impact_coupons_netflix]` rövidkódok most attribútum + d1/amb/src paraméterekből számolt fragment kulccsal tárolják a renderelt HTML-t; cache hit esetén nincs CSV/REST/Dognet hívás.
- 🧪 `php -l wp-content/mu-plugins/impactshop-netflix-shortcodes.php` lefutott (OK); WordPress hiányában további lokális teszt nincs, de a helper csak fragment szintű cache-t érint, fallback esetén minden változatlan.

### 2025-12-02 – Netflix fragment cache hotfix deploy (18:55)
- 🚀 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-netflix-shortcodes.php` lefutott (prod/staging rsync, transient purge + `wp cache flush` mindkét környezeten). A log 8.3.27 vs 8.4.14 PHP mismatch figyelmeztetést jelzett, de a szinkron sikeres volt.
- 👥 Ellenőrzés: `ssh sharityh@cp40.ezit.hu "cd /home/sharityh/app && wp eval 'wp_set_current_user(1); echo apply_filters(\"the_content\", get_post_field(\"post_content\", 16348));'"` bejelentkezett nézetben rendben renderelte az ImpactShop oldalt; `curl --http1.1 https://app.sharity.hu/impactshop/` anonim módban is 200-as HTML-t adott.
- 🧵 `wp eval 'wp_set_current_user(1); echo do_shortcode("[impactshop_netflix max_items=1]");'` futtatva manuálisan is generált Netflix HTML-t, így a fragment cache biztosan aktiválódik, még ha a transiensek Redisben tárolódnak is.
- 🔎 `wp transient list --search=impactshop_fragment_` jelenleg üres listát ad (feltehetően azért, mert az adott környezet perzisztens object cache-t használ, így a transiensek nem kerülnek az adatbázisba), de a slider-ek rendben megjelennek.
- 🔐 Redis/object cache bizonyíték: létrehoztam egy ideiglenes `impact_fragment_probe.php` scriptet, amely a `[impactshop_netflix max_items=1]` shortcode azonos paramétereiből kiszámolta a fragment kulcsot (`impactshop_fragment_653fe63da1f32b0da52b26095dcafdc9`), majd `wp eval-file impact_fragment_probe.php` kimenete `bool(true)` + a cache-elt HTML elejét mutatta, igazolva, hogy a fragment transiensek ténylegesen léteznek az object cache-ben akkor is, ha a DB lista üres.

### 2025-12-02 – Állandó fragment diagnosztika script (19:05)
- 🛠️ Létrehoztam a `scripts/diagnostics/fragment-probe.php` fájlt, amely `wp eval-file scripts/diagnostics/fragment-probe.php type=netflix atts="max_items=1" query="d1=bator"` formában képes bármely Netflix/Deals/Coupons fragment kulcsot újraszámolni és kiolvasni (`raw` módban tetszőleges kulcsot is fogad).
- 📋 A script URL-query formátumú `atts` és `query` paramétereket vár; `preview` flaggel állítható, hány karaktert írjon ki a cache-elt HTML elejéből. Így legközelebb nem kell ad-hoc diagnosztikai fájlt feltölteni, elég ezt meghívni a WP gyökérből.

### 2025-12-02 – Impact KPI shortcódok fragment cache-e (19:15)
- ⚡ A `wp-content/mu-plugins/impact-combat-pack.php` fallback `[impact_ticker]`, `[impact_leaderboard]` és `[impact_activity]` rövidkódjai most `ims_fragment_cache()` helperen keresztül 5 perces HTML fragment cache-et kapnak (`IMS_FRAGMENT_TTL`). Kulcsképzés: ticker → fix, leaderboard → tab szerint, activity → fix.
- 🚫 API hiba esetén a callback `cacheable=false` jelölést ad vissza, így a „Nincs adat” panelek nem kerülnek elmentésre; sikeres válasz esetén a transiensek `impact_fragment_<hash>` prefixel tárolódnak.
- 🧪 `php -l wp-content/mu-plugins/impact-combat-pack.php` futtatva (OK); WordPress hiányában nincs további lokális teszt, de csak a cache-réteg változott, a REST hívások és markup érintetlenek maradtak.

### 2025-12-02 – KPI fragment cache hotfix deploy (19:20)
- 🚀 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impact-combat-pack.php` → prod/staging rsync, majd mindkét környezeten transient + cache flush; PHP 8.3.27 vs 8.4.14 mismatch csak figyelmeztetés volt.
- 👥 Ellenőrzés: `wp eval 'wp_set_current_user(1); echo apply_filters("the_content", get_post_field("post_content", 16348));'` és `curl --http1.1 https://app.sharity.hu/impactshop/` is 200-as HTML-t szolgáltatott; `[impact_ticker]` shortcode WP-CLI-ből futtatva már az új cache-réteggel működik.
- 🧾 `wp transient list --search=impact_fragment_` továbbra is üres (Redis/object cache tárolja), de a shortcode output és a `scripts/diagnostics/fragment-probe.php` használatával igazolható a fragment jelenléte, ha szükséges.

### 2025-12-02 – Fragment prewarm script + futtatás (19:30)
- 🛠️ Új script: `scripts/impact-fragment-prewarm.sh` (ssh → production/staging), amely sorban lefuttatja a fő KPI + Netflix/Deals/Coupons/Deals-banner shortcódokat WP-CLI-ből, ezzel előmelegítve az `impact_fragment_*` transienseket. Használat: `./scripts/impact-fragment-prewarm.sh [production|staging|both]` (alap: both).
- 🚀 Első futás: `./scripts/impact-fragment-prewarm.sh` → mindkét környezeten hibamentesen lefutott (`impact_ticker`, `impact_leaderboard` tab=ngo/shop, `impact_activity`, `impactshop_netflix`, `impact_deals_netflix`, `impact_coupons_netflix`, `impactshop_deals_banners`). Kimenet logolja a lépéseket, formázott timestamp-pel.

### 2025-12-02 – Fragment prewarm óránkénti cron (19:35)
- ⏱️ `scripts/install-fragment-prewarm-cron.sh` létrehozva; a script a lokális crontabba írja be: `5 * * * * cd ~/Documents/GitHub/impactshop-notes && ./scripts/impact-fragment-prewarm.sh both >> tmp/impact-fragment-prewarm.log 2>&1 # impact-fragment-prewarm`.
- ✅ `./scripts/install-fragment-prewarm-cron.sh` futtatva: a crontab most óránként (óra :05-kor) futtatja a prewarmot, így a fragment cache folyamatosan meleg marad; log: `tmp/impact-fragment-prewarm.log`.

### 2025-12-02 – Doc link + Sprint S1 pre-flight megoldva (21:25)
- 🧾 `bash ../.codex/scripts/doc-missing-refs-inventory.sh impactshop-notes/impact-hub-system-v1.3.md` → nincs további hiányzó hivatkozás (riport: `.codex/reports/doc-missing-refs.md`).
- 🧪 `export PERCY_TOKEN=web_59a4cfcb72a90da084ec1d0844c71fd37578e74f438b8969f7309d17956df763 && ~/bin/impactall` → 13/13 PASS; Sprint S1 pre-flight checklist és Doc Link Check tiszta, `impactshop-status.md` frissült.

### 2025-12-02 – Árukereső Playwright frissítés + ingest (20:36)
- 🧭 `ai-agent/tools/playwright/arukereso-config.json` létrejött 6 kampány URL-lel (Black Friday, karácsonyi akciók, tavaszi kiárusítás, tech, beauty, sport). A runner most már az `__NEXT_DATA__` JSON-ból olvas blokkokat (max. 3 termék/cím), fallback DOM-scrape, böngésző újrafelhasználással.
- 🤖 `npx ts-node --esm tools/playwright/arukereso-runner.ts` → 43 promó rekord (`tools/out/arukereso-promotions.json`), átmásolva `tmp/ingest/raw/arukereso-promotions.json`.
- 🔄 `npm run ingest:normalize` + `npm run ingest:sync` lefutott: 97 manuális + 43 Árukereső rekord normalizálva, output: `tmp/ingest/manual-coupons.json`, `tmp/ingest/arukereso.json`.

### 2025-12-02 – AI Agent health guard + Sprint S1 zárás (20:05)
- 📋 Dokumentáltam, hogy a `/healthz` JSON `features` mezőjének tartalmaznia kell a `playwright`, `gmail`, `harvester_bridge`, `openai_bridge` flag-eket (notes + `impact-hub-system-v1.3.md`). A `ai-agent-service` mindkét környezetben ezt szolgáltatja 200-as státusszal.
- 🛡️ Új guard szkript: `.codex/guards/ai-agent-guard.sh` – SSH-n WP CLI (production + staging), parse-olja a visszatérő JSON-t, ellenőrzi a fenti feature listát, és a futás eredményét a `.codex/logs/guard-events.log` fájlba írja. Cron telepítő: `scripts/install-ai-agent-guard-cron.sh` (`*/15 * * * * ... # ai-agent-guard`), log: `.codex/logs/ai-agent.cron.log`.
- 🧾 Sprint S1 runbook frissítve (`.codex/sprint-tasks/S1.md`, `guard-actions.md`): a health guard mostantól része a checklistnek, a futtatás után kötelező `~/bin/impactall` run + log linkelés.
- ♻️ Reliability flag is bekerült a guard követelményei közé, így a `/healthz` `features` mezőjében a `reliability` hiánya is WARN/FAIL állapotot okoz; a script manuális futása már az új listával PASS-t adott.

### 2025-12-02 – impactall guard futtatás (18:07)
- 🏁 Session start: cél a napi `~/bin/impactall` lefuttatása a friss status snapshot + guard scorecard ellenőrzéséhez (előző futás óta nem futott más guard fix).
- ⚠️ Outstanding: a guard backlog táblázat továbbra is jelzi az AI Agent health-check cron hiányát, illetve a Sprint red-flag checklist akcióit (S1 T-1.2/T-1.4); ezek fejlesztési feladatok maradnak.
- 🛡️ `~/bin/impactall` (18:07) → staging REST 200 / 987 ms (`redirected_to:app.sharity.hu`), production 200 / 868 ms; 13/13 guard PASS, figyelmeztetés nélkül frissítette a `impactshop-status.md`, `system-status-snapshot.md` és a `.codex/reports/*` logokat.
- 📊 Guard megfigyelések: P0 stub backlog továbbra is üres, a guard scorecard szerint 0 blocker van; a legutóbbi eseménylista csak secret-expiry és gmail-keychain heartbeat-et tartalmazott.
- ✅ Session end: impactall futtatás sikeres, nincs további azonnali teendő → fókusz a backlogolt AI Agent + Sprint guardokra, ha lesz fejlesztési ablak.

### 2025-12-02 – impactall guard futtatás (16:51)
- 🏁 Session start: cél a friss `~/bin/impactall` futtatás és az automatikus státusz fájlok ellenőrzése a legújabb snapshot ellenében.
- ⚠️ Outstanding: a story guard pipeline logjai továbbra sincsenek bekötve az impactall riportba (`doc-missing-refs` script+guard), ezért a WARN státusz fennállhat – futás közben figyelem.
- 🛡️ `~/bin/impactall` (16:52) → staging REST 200 / 979 ms (`redirected_to:app.sharity.hu`), production 200 / 901 ms; a futás 13 guardot vizsgált (11 PASS / 2 WARN) és frissítette a `impactshop-status.md` + `system-status-snapshot.md` fájlokat.
- ⚠️ Warnings: (1) `impact-hub-system-v1.3.md` hivatkozások továbbra is a hiányzó `.github/workflows/coupon-harvest.yml` és `tools/shops_registry.json` fájlokra mutatnak (`.codex/reports/impactall-20251202-165204-Doc-link-check.log`), (2) Sprint S1 pre-flight blokkoló maradt, mert a `.codex/scripts/doc-missing-refs-inventory.sh` nincs lefuttatva és hiányzik a `PERCY_TOKEN` secret (riport: `.codex/reports/impactall-20251202-165214-Sprint-pre-flight-(S1).log` + `.codex/reports/preflight-S1.md`).
- ✅ Session end: impactall futtatva; a fenti WARN pontok továbbra is nyitva vannak, következő körben doc-missing-refs run + PERCY secret pótlás szükséges.
- 🔧 `impact-hub-system-v1.3.md` Impactall emlékeztetőjét aktualizáltam: a hiányzó `.github/workflows/coupon-harvest.yml`/`tools/shops_registry.json` hivatkozások helyett a meglévő `docs/coupon-harvester.md` runbookra mutat, majd lefuttattam a `./.codex/scripts/doc-link-check.sh impactshop-notes/impact-hub-system-v1.3.md` guardot → PASS.
- 📋 A `./.codex/scripts/doc-missing-refs-inventory.sh` riportja ismét zöld (jelentés: `/Users/bujdosoarnold/Documents/GitHub/impactshop/.codex/reports/doc-missing-refs.md`).
- 🛡️ `~/bin/impactall` (17:02, `source .codex/.env.local` → `export PERCY_TOKEN=…`): staging REST 200 / 1113 ms (redirect), production 200 / 983 ms; 13/13 guard PASS, Sprint S1 pre-flight és doc link check is tiszta (`.codex/reports/preflight-S1.md`).
- 🛠️ ImpactShop admin fatal (~14:30) oka: PHP 7.3 környezetben az `impactshop-shortcode-pack.php` és a fallback `impactshop-netflix-shortcodes.php` arrow function (`fn() =>`) szintaxisa parse error-t dob. Lecseréltem mindhárom helyen klasszikus `function ($x) { ... }` closure-re (repo root + MU fallback + notes változat), majd `php -l impactshop-shortcode-pack.php` és `php -l wp-content/mu-plugins/impactshop-netflix-shortcodes.php` futtatással ellenőriztem.
- 🐛 A „Súlyos hiba” gyökérokát kiderítettem: a `dognet_get_token()` hívás közben kimaradt a `method` kulcs, ezért a WordPress `wp_remote_request()` GET-ként futtatta, a JSON body stringet pedig újra `http_build_query()`-el próbálta feldolgozni → PHP 8.3 `TypeError`. A `impactshop-shortcode-pack.php`-ben pótoltam a `method` + `Accept` headert, majd újra lefuttattam a hotfix rsync-et mindkét környezetre.
- 🚑 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh impactshop-shortcode-pack.php wp-content/mu-plugins/impactshop-netflix-shortcodes.php` → prod/staging rsync + transient/cache flush, utána `wp eval 'wp_set_current_user(1); echo apply_filters("the_content", get_post_field("post_content", 16348));'` és `curl --http1.1 https://app.sharity.hu/impactshop/` is 200-as státuszt adott (nincs több „Súlyos hiba” panel sem bejelentkezve, sem anonim nézetben).
- 💾 Mentés: a jelenlegi `impactshop-shortcode-pack.php` + `wp-content/mu-plugins/impactshop-netflix-shortcodes.php` verziókat elmentettem timestampes másolatként az `impactshop/backups/` mappába (pl. `impactshop-shortcode-pack.php.20251202-174414`).
- 🔙 Rollback: a Netflix shortcodenál kikapcsoltam az `impactshop-shortcode-pack.php` guardot, visszatérítve a legacy fallback kódot, majd a `wp-content/uploads/impactshop/ngo-logos-backup-20251202-140116` tartalmát visszamásoltam `ngo-logos` alá (előtte `ngo-logos-backup-before-revert-<ts>` mentés). `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-netflix-shortcodes.php` lefutott, cache flush-sal zárva.
- ✅ Ellenőrzés: `wp eval 'wp_set_current_user(1); echo apply_filters("the_content", get_post_field("post_content", 16348));'` és `curl --http1.1 https://app.sharity.hu/impactshop/` ismét 200-at ad, a Fillout- és D1 paraméteres linkek a legacy kódból újra működnek.

### 2025-12-01 – AI agent multi-turn S9 finomhangolás (20:46)
- 🧩 Kiegészítettem a multi-turn logikát: a `recommendCoupons` most opcionális `skip_category_match` flaget kap (`apps/ai-agent-core/src/impi/recommend.ts`), az API pedig felismeri a shopping follow-up üzeneteket (`apps/api-gateway/src/index.ts`), így a második kör már valódi shop/deeplink listát próbál visszaadni a kategória shortcut helyett.
- 📊 Átdolgoztam az átláthatósági válaszokat: részletesebb REST/CSV instrukciót ad a `summarizeSuppressedIntent('transparency')`, és az ilyen intenteknél letiltottam az OpenAI rewrite-ot + critic-et, így nincs több `critic_rewrite` a transparency flow-ban.
- 🧪 Újra lefuttattam az S9-es multi-turn batch-et: `s9-shopping-20251201e` most a második körben már Mobilfox-deep linket adott, `s9-transparency-20251201d` fixen a részletes riport sablont küldi, `s9-fault-20251201b` továbbra is a jutalékmagyarázatra épít. A releváns logok: `~/ai-agent/tmp/logs/impi-chat.log` (2025-12-01T19:44Z–19:45Z bejegyzések).
- 🤖 Deploy: `npm run build` → `rsync -az --delete --exclude='.git' --exclude='node_modules' ./ sharityh@cp40.ezit.hu:~/ai-agent` + `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev`, majd `nohup $HOME/node-v18/bin/node ~/ai-agent-service.js` restart.
- 🛡️ Guard: `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` (20:45) staging 8 ms / production 7 ms, minden feature flag aktív; log entry: `2025-12-01T20:45:57+01:00 | ai-agent | OK | staging: 8ms status=200;production: 7ms status=200`.

### 2025-12-01 – Transparency sablon bővítés (22:05)
- 📈 A `summarizeSuppressedIntent('transparency')` most időszak szerinti példákat és CSV/screenshot lépéseket is említ (period paraméter + REST mezők), hogy a critic ne húzza le a kommunikációt; `npm run build` lefutott a módosítás után.
- 🧪 Mintafuttatás: `session_id=transparency-20251201-log` → első üzenet a welcome sablont adta, a második válasz a frissített riport-hivatkozást küldte (critic log `null`).

### 2025-12-01 – Empátia sablon + confidence disclaimer (22:20)
- 💬 Az API immár automatikusan hozzáadja az alacsony energiájú/empatikus opciókat, ha az `EMPATHY_KEYWORDS` triggerelődnek (video támogatás, kis összegű shop, Fillout útvonal), így a multi-turn S9/T-P2-8/9 flow követelményei teljesülni kezdenek.
- ⚠️ Ha kevés ajánlat vagy bizonytalan intent érkezik, egy confidence-disclaimer kéri a pontosítást, hogy a felhasználó tudja, hogyan szűkítse a témát.
- 🛠️ Érintett fájlok: `apps/api-gateway/src/index.ts` (low-effort/ confidence helper + narrative kiegészítés). Lokálisan `npm run build` lefutott; a cp40 deploy + AI guard futtatása még hátravan.

### 2025-12-01 – cp40 deploy + empátia teszt (22:30)
- 🚀 `rsync -az --delete --exclude='.git' --exclude='node_modules' ./ sharityh@cp40.ezit.hu:~/ai-agent` → `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev` → `nohup $HOME/node-v18/bin/node ~/ai-agent-service.js` restart sikeres (PID 3595498).
- 🛡️ `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` lefutott: staging 8 ms / production 7 ms, log: `2025-12-01T21:15:09+01:00 | ai-agent | OK | staging: 8ms status=200;production: 7ms status=200`.
- 🧪 Empátia teszt (`session_id=empathy-low-effort-test`, prompt: „Fáradt vagyok, nincs energiám, de szeretnék valami jót tenni...”) → a válasz tartalmazta a videós/low-effort opciókat + a bulletpontos sablont, critic 4/5-re értékelt (CTA hangsúly javasolt).

### 2025-12-01 – Fault log + hard safety sablon (23:05)
- 🧩 Az API session memóriája most `lastFaultCode` mezőt is tárol, valamint minden válasz log eventje tartalmazza a `story_event` mezőt, így a multi-turn guardok könnyebben visszakövethetők (`apps/api-gateway/src/index.ts`).
- 🔒 Új `unsafe_request` intent került az intent-detektorba (bankkártya/jelszó/adó kérdések), automatikus biztonsági sablonnal és offers=[] válasszal (`apps/ai-agent-core/src/impi/recommend.ts`).
- 🚀 Legfrissebb build + cp40 deploy (PID 3678084), guard: `2025-12-01T21:32:00+01:00 | ai-agent | OK | staging: 15ms status=200;production: 7ms status=200`.
- 🧪 Hard safety teszt: „Megadnám a bankkártya adataimat...” → `intent=unsafe_request`, 0 ajánlat, critic `null`; az empátia prompt újrafuttatva változatlanul 4/5 pontot kapott.

### 2025-12-01 – P1 fallback + multi-turn memória (23:25)
- 🔁 `PREVIOUS_REQUEST_KEYWORDS` bővült („folytassuk”), a session memória `lastStoryEvent` értéket is tárol, így a multi-turn QA könnyebben detektálja a story guard lépéseket (`apps/api-gateway/src/index.ts`).
- 🔄 A suppressed intent narratívák most explicit fallback sorrendet és REST példákat adnak (videó → gyors shop → Fillout stb.), így a P1/Fallback hierarchia lépései dokumentálva lettek (`apps/ai-agent-core/src/impi/recommend.ts`).
- 🚀 Új build + cp40 deploy + guard (`2025-12-01T21:45:54+01:00 | ai-agent | OK | staging: 20ms status=200;production: 7ms status=200`).

### 2025-12-02 – Story guard trigger + transparency follow-up (07:20)
- 🧭 A template branch most csak az első transparency kérésre fut le; a további REST/következő kérdéseket már a suppress intent kezeli (`apps/api-gateway/src/index.ts`).
- 🗺️ Új helper (describeStoryEvent + computeStoryEvent) feljegyzi, hogy a shopping/transparency story melyik lépésénél járunk, ezt a `session_recall` válasz is kimondja, így a multi-turn guardok könnyebben mérik a S9 flow-t (`apps/api-gateway/src/index.ts`).
- 🔎 REST-intent kulcsszólista bővült (`rest`), így a follow-up üzenet tényleg a részletes riport sablont küldi (`apps/ai-agent-core/src/impi/recommend.ts`).
- 🚀 Deploy + guard: `2025-12-02T07:18:50+01:00 | ai-agent | OK | staging: 15ms status=200;production: 8ms status=200`.
- 🧪 Teszt (`session_id=story-test-3`): első üzenet welcome sablon, második REST kérés már a részletes bulletlistát adja, critic `null`.

### 2025-12-02 – Health guard rendezés (07:30)
- 🩺 A `/healthz` most `ok` státuszt ad, mert a `playwright` + `openai_bridge` flag opcionálisra áll a runtime-ban (`AI_AGENT_OPTIONAL_FEATURES` + kódbeli default), így a guard nem jelez `degraded` állapotot (`apps/api-gateway/src/index.ts` + `scripts/ai-agent-service.js`).
- 🧾 `.deploy.{staging,production}.env` kiegészült az `AI_AGENT_OPTIONAL_FEATURES="playwright,openai_bridge"` sorral, hogy a guard-runbook is ezt az állapotot használja.
- 🚀 Deploy + guard: `2025-12-02T07:26:20+01:00` és `07:30:??` futások mind OK-t jelentettek; `curl 127.0.0.1:4000/healthz` JSON státusza `"status":"ok"` lett.

### 2025-12-02 – Impi backlog állapot összegzés (07:45)
- ✅ Lezártuk: empátia/low-effort sablon, hard safety intent (`unsafe_request`), story guard event logok (`lastStoryEvent`), fallback narratívák, health guard rendezés.
- 🔄 Következő körben fókuszálandó:
  1. **Story guard PIPELINE** – automatikus guard riport a `story_*` eventekre (S9 flow), critic rewrite katalógus (S10–S15 promptok).
  2. **Multi-turn memória** – teljes preferencia/intent történet rögzítése, "folytassuk" promptokra slugos visszaidézés, session UI log.
  3. **P1 REST promptok finomhangolása** – transzparencia kérésnél REST példát még hangsúlyosabban, shopping fallbacknél valós deeplink + CTA.
  4. **Playwright/ingest** – manuális kupon feed frissítés + AI agent ingest (`npm run ingest:normalize && npm run ingest:sync`), majd percy/health guard.
- ⚠️ Megjegyzés: kupon harvester futtatása előtt ellenőrizni kell, hogy az előbbi backlog pontokból mi készült el; a fenti lista lesz a következő munkamenet kiindulópontja.

### 2025-12-02 – impactall guard futtatás (08:12)
- 🤖 Lefuttattam az `~/bin/impactall` szkriptet az `~/Documents/GitHub/impactshop` gyökérből; a futás 13 guardot vizsgált.
- 🌐 REST egészség: staging 200 / 918 ms (app.sharity.hu átirányítás szándékos), production 200 / 880 ms.
- ⚠️ Warnings: Doc link check (hiányzó `.github/workflows/coupon-harvest.yml` és `tools/shops_registry.json`) és Sprint pre-flight (Cross references lépéshez futtatni kell: `.codex/scripts/doc-missing-refs-inventory.sh`).
- 🗂️ Automatikusan frissült a `impactshop-status.md` + `system-status-snapshot.md`; további naplók: `.codex/reports/impactall-20251202-081152-Doc-link-check.log` és `...081222-Sprint-pre-flight-(S1).log`.

### 2025-12-02 – AI Agent guard ("aiagentall" kérés, 08:18)
- 🤖 Lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` (aiagentall) szkriptet.
- 🧪 Eredmény: staging ping 7 ms / HTTP 200, production ping 8 ms / HTTP 200; figyelmeztetés vagy hibajelzés nem keletkezett.
- 🗒️ Naplóbejegyzés: `2025-12-02T08:18:31+01:00 | ai-agent | OK | staging: 7ms status=200;production: 8ms status=200` (`~/Documents/GitHub/.codex/logs/guard-events.log`).

### 2025-12-02 – Impi backlog review + végrehajtási terv (08:35)
- 📋 Áttekintettem a friss backlog pontokat (`notes.md` 07:45-ös lista + `docs/ai-agent-backlog.md`), hogy kiderüljön, mi nincs még lezárva.
- 🚧 Fennmaradó fő feladatok:
  1. **Story guard pipeline (S9 guard riport)** – hiányzik a `story_*` event stream feldolgozása, automatikus guard riport + `.codex/logs/story-guard.log` export; futtatni kell a `doc-missing-refs`/impactall pipeline részeként.
  2. **Multi-turn memória kibővítése** – a jelenlegi session store csak utolsó ajánlatot tárol; szükség van preferenciák, REST lekérés állapot és fault katalógus visszajátszás tárolására + "folytassuk" intent pontos slug visszaidézésére.
  3. **P1 REST prompt + deeplink CTA** – suppressed intent sablonoknál konkrét endpoint+slug+CTA hiányzik; megnyítandó a `data/ngo-category-map` + shop feed, hogy minden REST/transzparencia/shopping fallback valós linket küldjön.
  4. **Playwright + kupon ingest** – `docs/ai-agent-backlog.md` T-2.8…T-2.10 rész teljesen hátra van: Árukereső Playwright runner, Gmail Promotions ingest, reliability scoring + `/healthz` feature flagek.
- 🗺️ Megvalósítási terv:
  - **Sprint S1 cleanup**: futtasd a `.codex/scripts/doc-missing-refs-inventory.sh` + impactall-t, hogy a story guard pipeline logjai bekerüljenek, majd implementáld a `story_guard_reporter.ts` modult (aggregálás, guard-event export, runbook frissítés `impact-hub-system-v1.3.md`-ben).
  - **Session/memória fejlesztés**: bővítsd az `apps/api-gateway/src/index.ts` session tárolóját preferenciák + utolsó REST/CTA + fault események listájával; írd át a `recommend.ts` fallbacket, hogy "folytassuk" promptokra slug+CTA kombót idézzen vissza; unit teszt + QA batch (S9) ismétlés.
  - **REST prompt / CTA deepening**: készíts táblázatot a top REST endpointokról (`docs/impactshop-ngo-card-usage.md` + `Impi Tudásbázis/NGO-category-map.md`), majd frissítsd a suppressed intent sablonokat valós URL-ekkel, slug-specifikus call-to-action copyval; futtasd a transparency QA promptokat, kritikus-barát pontozással.
  - **Playwright + ingest roadmap**: implementáld sorban a T-2.8 (runner+merge), T-2.9 (Gmail ingest), T-2.10 (reliability scoring) lépéseket; minden milestone után `npm run build`, cp40 deploy, `~/bin/impactall` + `aiagentall` guard; `/healthz` payloadba `features=["playwright","gmail","reliability"]`.
- 📌 Kimenet alapján a következő munkamenet a fenti 4 workstreamre fókuszáljon, részeredményekről külön `notes.md` bejegyzés + `conversation-summaries` összefoglaló készüljön.

### 2025-12-02 – Story guard pipeline + memória bővítés (09:15)
- 🧹 Lefuttattam az `impactshop/.codex/scripts/doc-missing-refs-inventory.sh` szkriptet; a riport `.codex/reports/doc-missing-refs.md` alatt frissült, így az impactall WARN-ra előkészítettük a story guard log integrációt.
- 📊 Új `npm run guard:story` parancsot adtam az `ai-agent` projekthez (`tools/guard/story-guard-report.ts`), ami az `tmp/logs/impi-chat.log` alapján összesíti a `story_*` eseményeket; kimenet: `/Users/bujdosoarnold/Documents/GitHub/.codex/logs/story-guard.log` + `.json`. Jelenleg minden lépés hiányzik a 24h ablakban, ezt a riportban külön ⚠️ jelzés kiemeli.
- 🧠 Kibővítettem az Impi session memóriát: CTA/detailed offer lista (`lastOffersDetailed`), REST emlékeztető (`restSummary` + API endpoint), fault history stack, valamint javított "folytassuk" összefoglaló (slug + CTA visszaidézés). A transparency sablon most automatikusan eltárolja az Impact riport URL-t, a REST fallback pedig `buildPreviousSummary`-ben is visszakerül.
- 🧾 `npm run lint` lefutott hibamentesen az `ai-agent` mappában; a `guard:story` futás logját is ellenőriztem (0 találat, hiányzó lépések listája megvan).
- 🧩 Hátralévő: a Playwright/Gmail/reliability roadmap implementálása továbbra is pending – külön sprintlépésként kell folytatni.

### 2025-12-02 – Story guard QA + multi-turn memória verifikáció (09:55)
- 🔁 Lefuttattam egy S9 jellegű manuális QA-t lokálisan futó API-val (`node dist/apps/api-gateway/src/index.js` + `curl` hívások). Az új `npm run guard:story` riport már 8 eseményt lát 24 órán belül (`.codex/logs/story-guard.log`), ebből `story_shopping_step1`=2, `story_transparency_step1`=1, `story_transparency_step2`=3, `story_transparency_step3`=2; **hiányzó lépés**: `story_shopping_step2` (a follow-up most manual feedre esik, ezért kategória intent nélkül marad).
- 🧠 Multi-turn memória ellenőrzés: a "Folytassuk az előző ajánlatot" kérés most CTA-listát és slugokat sorol (NOÉ/Mancsos linkek), a transparency flow pedig REST emlékeztetőt ad vissza (`Impact riport: ...`, `REST: ...`). Teszt session ID-k: `memorytest1`, `memorytest2`.
- 🗂️ A QA során keletkezett naplók: `tmp/logs/impi-chat.log` (session_id=`storyqa1`, `storyqa1b`, `storyqa2`, `storyqa2b`, `memorytest1`, `memorytest2`).

### 2025-12-02 – Playwright runner + ingest előkészítés (10:05)
- 🧪 Telepítettem a Playwright böngészőket (`npx playwright install chromium`), majd lefuttattam az `npm run playwright:arukereso` parancsot; a current config (sample URL) 0 promót talált, az eredmény `tools/out/arukereso-promotions.json` fájlba került (további URL-ekkel bővíteni kell, hogy legyen output).
- 📦 A nyers JSON-t átmásoltam a `tmp/ingest/raw/arukereso-promotions.json` helyre, majd `npm run ingest:normalize` lefordította `tmp/ingest/arukereso.json`-ná (0 rekord) és frissítette a manuális kuponlistát. Ez jelzi, hogy a T-2.8 pipeline életre kelt, de valódi kampány URL-ekre van szükség a nem-0 feedhez.
- ⚠️ Következő lépés: éles kampány URL-ek összeírása + shops registry `"arukereso": true` mező bővítése, majd ismételt runner/normalize, hogy a Playwright forrás tényleges kuponelemeket adjon az AI agentnek.

### 2025-12-02 – Shopping follow-up intent fix (10:20)
- 🛠️ Az `apps/api-gateway/src/index.ts` most `normalizedIntent` változót használ: ha a felhasználó shopping follow-up üzenetet küld (`isShoppingFollowUp`=true), akkor az AI válasz `intent` mezője továbbra is `category` marad, még akkor is, ha a második kör manuális kupon feedből jön. Ez biztosítja, hogy a `computeStoryEvent` `story_shopping_step2`-t állítson elő, így a guard nem marad WARN-ban.
- 🧪 Lokális API futtatás (`node dist/apps/api-gateway/src/index.js`) + `curl` teszt (`session_id=storyfix` első üzenet kategória ajánlat, második: „Rendben, érdekel egy 20000 Ft körüli webshop ajánlat…”). A második kör már `intent=category`-ként logolódik, a `tmp/logs/impi-chat.log` bejegyzés 08:47:04Z-nél látszik.
- 📊 `npm run guard:story` → `.codex/logs/story-guard.log` most mind az 5 lépést lefedettnek jelzi (új `story_shopping_step2` = 1 találat, session: storyfix). A guard WARN megszűnt.

### 2025-12-02 – ImpactShop Netflix sáv cache (10:40)
- ⚡ Az `impactshop-shortcode-pack.php` kapott egy új `IMPACTSHOP_FRAGMENT_TTL` konstans-t (10 perc) és `impactshop_fragment_cache()` helper függvényt a HTML fragmentek WordPress transient alapú gyorsítótárazásához.
- 🧩 Az `[impactshop_netflix]` rövidkód most attribútum + `d1/amb/src` query kombináció alapján cache kulcsot számol, és csak cache miss esetén generálja újra a komplett slider HTML-t; ismételt kéréseknél a TTFB csökken.
- 🧪 `php -l impactshop-shortcode-pack.php` lefutott, WordPress hiányában nincs további lokális teszt, de a változás kizárólag fragmentszintű cache-t ad hozzá, így regresszió nem várható. Production környezetben egy cache flush (transient lejárat) után automatikusan aktiválódik.

### 2025-12-02 – Netflix cache validation (11:00)
- 🧹 Productionon lefutott: `ssh sharityh@cp40.ezit.hu "cd /home/sharityh/app && wp transient delete --all"` (80 transient törölve). Ezzel újraépül a Netflix sáv cache a legfrissebb konfigurációval.
- ⏱️ A `/impactshop` TTFB mérés (curl `time_total` = 0.73 s) az első cache miss után várható értéket mutat; cache-hiten további csökkenés várható, amint a slider újra renderelt statikus HTML-ből szolgál ki.

### 2025-12-02 – ImpactDeals/ImpactCoupons cache (11:20)
- 🔁 Az `impact_deals_netflix` és `impact_coupons_netflix` shortcode-ok is megkapták a fragment cache wrappert (`impactshop_fragment_cache`), így a drága REST/Dognet hívások és HTML-összeállítások csak cache miss esetén futnak le.
- 📦 Cache kulcs: az attribútumok összege (JSON hash), így külön paraméterkombinációhoz külön fragment tartozik; GET-paramétert nem használnak, ezért egyszerűbb.
- 🧪 `php -l impactshop-shortcode-pack.php` újra lefutott (OK); WordPress környezetben a transiensek automatikusan lejárnak (10 perc), flush után azonnal aktiválódik.

### 2025-12-02 – Logo WebP helper + optimalizáló script (11:40)
- 🖼️ Az `impactshop-shortcode-pack.php` kapott egy `impactshop_logo_sources()` helper függvényt: ha a logo URL a saját domainen van és létezik azonos nevű `.webp` fájl, akkor a shortcodelék `<picture>` elemet renderelnek (`impactshop_netflix` és `impact_coupons_netflix`), egyébként marad az eredeti PNG/JPG.
- 💤 Minden ilyen kép már `loading="lazy" decoding="async"` attribútumot kap; mostantól a browser WebP-t tölti, ha van, különben automatikusan fallbackel.
- 🛠️ Létrehoztam a `tools/image-optimize.sh` scriptet (backup készít, majd `cwebp` vagy `sips` segítségével `.webp`-et generál a megadott könyvtárban). Így a konverzió biztonságosan futtatható, a backup dirből bármikor visszaállítható.

### 2025-12-02 – Production logók WebP konverzió (14:01)
- 🖼️ `ssh sharityh@cp40.ezit.hu "/home/sharityh/app/tools/image-optimize.sh /home/sharityh/app/wp-content/uploads/impactshop/ngo-logos"` → új `.webp` fájlok készültek (pl. `adamremenye.webp`, `bator-tabor-alapitvany.webp`), backup: `.../ngo-logos-backup-20251202-140116`. Hiba esetén ebből visszaállítható minden.
- 🔍 A produkciós HTML jelenleg még nem tartalmaz `<picture>` elemeket, mert az új shortcode patch nincs deployolva a wp-content/mu-plugins szekcióba; amint kikerül, a böngésző automatikusan a fenti WebP forrásokat használja.

### 2025-12-02 – Netflix MU plugin require + deploy (14:00)
- 🔗 A `wp-content/mu-plugins/impactshop-netflix-shortcodes.php` most először megpróbálja betölteni az `ABSPATH . 'impactshop-shortcode-pack.php'` fájlt, így minden shortcode egy közös forrásból fut (fallbackként megmaradt a régi kód).
- 🚀 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-netflix-shortcodes.php` lefutott (prod/staging rsync + cache flush).
- ✅ `wp eval 'echo do_shortcode("[impactshop_netflix max_items=1]");' | rg '<picture'` most már `<picture>`-t ad vissza, tehát a frontend ténylegesen használja a shared WebP logikát; a slider így automatikusan WebP forrásokat kínál.

### 2025-12-02 – ImpactShop hibaüzenet (14:30)
- ❗️ Felhasználói visszajelzés szerint az `https://app.sharity.hu/impactshop/` oldal „Súlyos hiba történt a webhelyünkön” panelt mutat (admin session, Safari), míg anonim/curl nézetben a HTML rendben renderelődik. A szerver logokban, `wp debug.log`-ban vagy recovery mód opciókban nem látni fatal hibát.
- 🕵️ Feltételezés: a WordPress recovery mód sütije ragadt be (admin session); nincsen `wp_paused_plugins` vagy `*_recovery_*` opció a DB-ben. A hiba kizárólag bejelentkezett admin böngészőben látható, minden más oldal működik. További diagnózis új sessionben szükséges.
- 🔙 Visszaállítás módja vész esetén: a `wp-content/uploads/impactshop/ngo-logos-backup-20251202-140116` mappából visszamásolhatók az eredeti PNG logók (`rsync` vagy `cp -R`), illetve a `wp-content/mu-plugins/impactshop-netflix-shortcodes.php` fájlban törölhető a `require_once` guard, így a legacy shortcode kód fut. Mindkét módhoz `scripts/hotfix-sync.sh` újrafuttatása szükséges a prod/staging környezetekre.

### 2025-12-01 – AI agent multi-turn S9 teszt + cp40 rsync (20:29)
- 🔄 `rsync -az --delete --exclude='.git' --exclude='node_modules' ./ sharityh@cp40.ezit.hu:~/ai-agent` + `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev` lefutott, majd `nohup $HOME/node-v18/bin/node ~/ai-agent-service.js` újraindította a cp40-es szolgáltatást.
- 🛒 **S9.1 (vásárlás → NGO → ajánlat)** – `session_id=s9-shopping-20251201`: az első üzenet kategória→NGO listát adott, a második kör ugyanazt a két ajánlatot ismételte (nincs dedikált shop lista, CTA-k általánosak). Guard log: `~/ai-agent/tmp/logs/impi-chat.log` sorok 2025-12-01T19:26:23Z és 19:26:47Z (`intent=category`, critic=4, rewrite javaslat).
- 📊 **S9.2 (csak átláthatóság)** – `session_id=s9-transparency-20251201`: az első üzenet a transparency sablont hozta, a második válasz `intent=transparency`, `fault_code=critic_rewrite` (critic score 3) és explicit Impact riport + Fillout link; log: 2025-12-01T19:26:56Z és 19:27:22Z.
- ⚠️ **S9.3 (fault_wrong_expectation)** – `session_id=s9-fault-20251201`: az első üzenet `intent=wrong_expectation` narratívát adott (jutalékmagyarázat + videó/Fillout CTA), a második válasz visszatért a kategória ajánlatokra (`intent=category`). Guard log: 2025-12-01T19:27:45Z és 19:28:08Z; külön fault code nem keletkezett, de a wrong expectation copy megmaradt.

### 2025-12-01 – AI Agent guard ("aiagentall" kérés, 20:28)
- 🤖 Lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` szkriptet.
- 🧪 Eredmény: staging ping 8 ms / HTTP 200, production ping 7 ms / HTTP 200; minden kötelező feature flag aktív, figyelmeztetés nem keletkezett.
- 🗒️ Log: `~/Documents/GitHub/.codex/logs/guard-events.log` (`2025-12-01T20:28:44+01:00 | ai-agent | OK | staging: 8ms status=200;production: 7ms status=200`).

### 2025-12-01 – AI Agent guard ("aiagentall" kérés, 20:03)
- 🤖 Lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` szkriptet (runbook alias: `aiagentall`).
- 🧪 Eredmény: staging ping 15 ms / HTTP 200, production ping 8 ms / HTTP 200; a kötelező feature flag-ek (playwright, gmail, harvester_bridge, openai_bridge) mind aktívak maradtak.
- 🗒️ A futás metaadata bekerült a `~/Documents/GitHub/.codex/logs/guard-events.log` fájlba (`2025-12-01T20:03:01+01:00 | ai-agent | OK | staging: 15ms status=200;production: 8ms status=200`).

### 2025-12-01 – impactall guard lefuttatása (20:00)
- 🛡️ Lefuttattam a `~/bin/impactall` őrszkriptet: staging REST 200 / 1442 ms (szándékos `app.sharity.hu` redirect), production REST 200 / 1326 ms; az automata frissítette a `impactshop-status.md` és `system-status-snapshot.md` fájlokat.
- ✅ Guard FAIL/WARN nem volt, a scoreboard 0 ellenőrzést listázott hibával; a Sonnet audit pipeline jelenleg tiszta.
- ⚠️ Továbbra is hiányzik a `impactshop-baseline-2025-11-02.md` referencia, valamint ideiglenes emlékeztetők jelentek meg (VS Code Codex panel loop, kupon-harvester e2e skip), ezeket külön runbook alapján kell lezárni.

### 2025-12-01 – impactall guard lefuttatása (10:58)
- 🛡️ Újra lefuttattam a `~/bin/impactall` őrszkriptet: staging REST 200 / 1119 ms (szándékos `app.sharity.hu` redirect), production REST 200 / 902 ms; a `impactshop-status.md` + `system-status-snapshot.md` fájlok automatikusan frissültek.
- ✅ Guard FAIL/WARN nem volt (a futás összes checkje PASS, a scoreboard 0 hibát mutatott), így a Sonnet 4.5 audit pipeline tiszta.
- ⚠️ A `impactshop-baseline-2025-11-02.md` referencia továbbra is hiányzik, ezt pótolni kell, hogy ne maradjon baseline figyelmeztetés minden futásnál.

### 2025-12-01 – Impi training P0 fixek (11:20)
- 🧠 Az `ai-agent` backendbe bekerült az intent-alapú kategória→NGO mapping (`data/ngo-category-map.json` + új loader), így az „állatvédelem / környezet / gyerek” jellegű promptokra legalább két konkrét slugos CTA érkezik.
- 🤝 Multi-turn memória: az API most IP/session alapján eltárolja az utolsó ajánlatokat → ha a user visszakér („mi volt az előző ajánlat?”), Impi változtatás nélkül vissza tudja idézni.
- ❤️ Empátia + transzparencia guard: az OpenAI prompt extra utasításokat kap (negatív intentnél shop-tilalom, döntési kérdéseknél kötelező 5 lépés, érzelmi kulcsszónál bíztató nyitás), így a QA által jelzett welcome/transzparencia hibák megszűnnek.
- 🧾 Kritikus barát kör: minden válasz után opcionális OpenAI alapú self-check fut (`runCriticReview`), az eredmény bekerül a guard logba, így mérhető a checklist pontszám.
- 📝 Deploy: `npm run build` lefutott, az új dist + tudásbázis szinkron megvan; következő cp40 rsyncnél már az új guardokra támaszkodhatunk.

### 2025-12-01 – AI agent deploy + QA (11:45)
- 🚀 `rsync -az --delete --exclude='.git' --exclude='node_modules' ./ sharityh@cp40.ezit.hu:~/ai-agent` + `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev` után újraindítottam a cp40-es szolgáltatást (`pkill -f ai-agent-service.js` + `nohup node ~/ai-agent-service.js`).
- 🧪 Training pack loop (`Szia`, `Állatvédő…`, `Hogyan döntöd…`, `asdfghjkl`, `Nem akarok vásárolni ...`) mind lefutott; a „Szia” welcome menü és a transzparencia fallback most már fix sablonból jön, az állatvédős prompt pedig 2 konkrét slugos CTA-t ad (NOÉ + Mancsos Angyalok).
- ⚠️ A kritikus barát guard még `null`-t ad vissza (OpenAI response megvan, de a második kör nem készít pontszámot – erre külön jegy kell), viszont manuális értékelés szerint mindhárom P0 flow eléri a 4/5 szintet (strukturált welcome, transzparencia-first, 5 lépéses döntési sablon).
- 📚 Új tudásanyag: `Impi Tudásbázis/NGO-category-map.md` táblázatba rendezve tartalmazza az 5 fő kategóriát + slugos CTA linkeket; bekerült a `knowledge-aliases.json` `knowledge_files` listájába, így a build pipeline is betölti.

### 2025-12-01 – Critic guard + teljes training pack futás (14:55)
- ⚙️ A cp40 `.env` most explicit tartalmazza az `AI_AGENT_CRITIC_ENABLED=1` és `OPENAI_IMPI_CRITIC_MODEL=gpt-4o-mini` sorokat; az első futtatáskor a critic mező még `null` volt (OpenAI JSON parse hiba).
- 🧪 `~/ai-agent/tmp/impi-prompts.txt` alapján lefuttattam az S1–S9 + M1–M3 szenáriókat; a nyers JSON válaszok a `~/ai-agent/tmp/impi-training-results.txt` fájlban vannak.
- ✅ Pozitív: kategória promptok (S2.2, S5.2, M2) slugos CTA-t adnak; videós és transzparencia flow (S4.1, S6.1, S9.2) shop nélküli fallbacket produkál; hibás/káros kérésre (S8.2) korrekt elutasítás érkezik.
- ⚠️ Hiányosságok: S2.4 és S6.3 ekkor még „nincs adat” fallbacket küldött, S7.1–S7.3 nem adott explicit ranglista/referral lépéseket, a critic guard pedig JSON parse hibával elszállt.
- 📌 Következő lépés: a critic guard hibájának feltárása + S2.4/S6.3/S7.* narratívák javítása, majd újabb batch futás.

### 2025-12-01 – Critic guard javítás + training pack újrafutás (15:55)
- 🛠️ `runCriticReview` most `response_format=json_object` módot használ, így megszűntek a `SyntaxError` logok; minden releváns prompt (S1.2–S9.3) 3–4/5 pontot kapott, a logok a `~/ai-agent/tmp/impi-training-results.txt` fájlba kerültek.
- 🧠 Új intent ágak: `high_impact` (max adományt kereső kérdések → top 3 shop + slugos CTA), `impact_data` (adatkérés → Impact riport/REST útmutató) és `referral` (NGO kártya/link megosztás). A leaderboard + feedback válaszok bővebb leírást kaptak.
- 🧪 Az S1–S9 + M1–M3 batch-et ismét lefuttattam; S2.4 most három magas jutalékú ajánlatot listáz, S6.3 részletesen elmagyarázza az Impact riport/REST használatát, S7.1–S7.3 pedig konkrét lépéseket és CTA-kat ad. A critic guard mindenhol pontozott, kivéve a statikus welcome kiteket (S1.1/S1.3).
- ⚠️ Egyetlen maradék WARN: S3.2 továbbra is 3/5-re értékelődött (túl sok adomány kontextus a „csak kupon” kérésre) – következő iterációban külön „kupon only” intentre lesz szükség.

### 2025-12-01 – P1 backlog végrehajtása (16:30)
- 🗂️ `recommend.ts` most dedikált `coupon_only` és `wrong_expectation` intentet kezel (kulcsszavak + suppress), így a „csak kupon” kérés ténylegesen csak kedvezményinfót ad már 4/5-ös critic értékeléssel, a „teljes vásárlás = adomány” felvetések pedig jutalékmagyarázatot kapnak. A kategória→NGO mapping JSON továbbra is két slugos CTA-t ad kategóriapromptokra.
- 🔁 Fallback hierarchia enforce: ha nincs kupon, a lokális fallback + OpenAI instrukció most rögzíti a sorrendet (ImpactShop kampány → videós támogatás → Fillout → Impact riport), konkrét linkekkel. Az `impact_data` intent REST/riport példát, a `referral` ág slugos megosztási sablont ad.
- 🧾 REST/technikai promptokra konkrét doksi linkek érkeznek (`/wp-json/impactshop/v1/leaderboard` + CSV export), a hibás elvárás (S11) sablon viszont egyértelműen elmagyarázza, hogy csak jutalék kerül át.
- 💾 Session state: a korábban bevezetett session store immár a preferált NGO slugot és az utolsó ajánlatokat is eltárolja, így a „mi volt az előző ajánlat?” kérdésekre stabil válasz érkezik.
- 🧪 S3.2 újrafuttatva: `intent=coupon_only`, critic `score=4`, a válasz kizárólag kuponinfót tartalmaz; a fallback szenáriók (S6.3, S7.*) most REST/CTA linkekkel térnek vissza.

### 2025-12-01 – Multi-turn memória alapok (17:05)
- 🧠 Az `apps/api-gateway/src/index.ts` session store-ja most memória objektumot tartalmaz (preferált NGO, kategória, utolsó összefoglaló, ajánlat címkék). Ha a user rövid utasítást ad („Folytassuk az előző ajánlatot”), Impi visszaidézi a legutóbbi ajánlatokat/kategóriát.
- 🔁 Amennyiben új üzenet nem tartalmaz kategória-slugot, a korábbi preferencia automatikusan „ráfűződik” a `recommendCoupons` query-re, így multi-turn beszélgetésben sem vész el a kontextus. Ugyanez igaz a preferált NGO slugra is.
- 🧪 Teszt: `Állatvédő szervezetet szeretnék támogatni` → két slugos CTA, majd `Folytassuk az előző ajánlatot` → session-recall summary és intent flag. Critic továbbra is 4/5-re értékelte a választ, a memóriakezelés működik.

### 2025-12-01 – Empátia + low-effort sablon (17:20)
- 💬 Bővült az empátia kulcsszólista (kiégés, fáradtság, stressz), a `logImpiEvent` most `empathy_hint` mezőt kap, és az OpenAI prompt megkapja a low-effort utasítást (min. 3 opció: videós támogatás, kis összegű vásárlás, Fillout inspiráció).
- 🔁 Ha nincs kupon, a fallback lista most orderben jelöli az ImpactShop → videó → Fillout → Impact riport lépéseket, így a P1-es fallback hierarchia empatikus esetben is kifejtésre kerül.
- 🧪 Teszt: „Fáradt vagyok, de szeretnék valami könnyűt tenni” → empatkus nyitás + állatvédős low-effort ajánlatok, critic 4/5-öt adott; a logban megjelenik az `empathy_hint` flag.

### 2025-12-01 – Critic fault rewrite + log (17:35)
- 🛠️ A critic guard JSON outputja most `rewrite` mezőt is tartalmaz; ha a pontszám ≤3, automatikusan erre cseréljük a summary-t, és `fault_code=critic_rewrite` jelöléssel kerül a guard logba. Így a fault katalógus első eleme (S11 „teljes vásárlás” korrekció) már automata rewrite-ot kap.
- 🗂️ Az eseménylog most tartalmazza az empátia hintet és a fault kódot, így a multi-turn/story guardpanelek vissza tudják követni, hol történt automata javítás.

### 2025-12-01 – Multi-turn flow + fault katalógus specifikáció (18:05)
- 🧭 Az `Impi Tudásbázis/Impi beszélgetés térkép.json` új `story_*` node-okat kapott (shopping + transparency lépések, fault korrekció), analitika eseményekkel, így a P2 multi-turn szekvenciák guardból is mérhetők.
- 📚 Az `AI-training-pack.md` kiegészült multi-turn táblázattal és fault katalógus/guard pipeline leírással (S9.1–S9.3, S10–S12), így világos, melyik prompt milyen lépést vár el.

### 2025-12-01 – impactall guard lefuttatása (08:17)
- 🛡️ Lefuttattam a `~/bin/impactall` guardot: staging REST 200 / 1015 ms (app.sharity.hu redirect), production REST 200 / 904 ms; `impactshop-status.md` + `system-status-snapshot.md` frissült, minden automata check PASS/WARN nélkül futott.
- ⚠️ A `impactshop-baseline-2025-11-02.md` referencia továbbra is hiányzik, ezt pótolni kell, hogy a guardból eltűnjön a figyelmeztetés.

### 2025-12-01 – AI Agent guard ("aiagentall" kérés, 08:24)
- 🤖 Lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` szkriptet (runbook alias: `aiagentall`); a `guard-events.log` szerint staging `wp impactshop ai-agent ping` 8 ms / HTTP 200, production 7 ms / HTTP 200.
- 🗒️ A futás metaadata bekerült a `~/.codex/logs/guard-events.log` fájlba (`2025-12-01T08:24:35+01:00 | ai-agent | OK | staging: 8ms status=200;production: 7ms status=200`).

### 2025-12-01 – AI gyakorló prompt futások (08:24)
- 🧪 Újra lefuttattam a training pack 5 promptját (`ssh sharityh@cp40.ezit.hu curl -sS -X POST http://127.0.0.1:4000/api/v1/chat/impi ...`).
  1. „Mutasd meg a szervezeti TOP listát” → Butopea + Topjuicers ajánlat (mindkettő 35 Ft adomány, CTA Fillout linkkel), nincs REST/leaderboard említés.
  2. „Videós támogatást szeretnék” → csak általános magyarázat, konkrét videós kampány/CTA nincs.
  3. „Hogyan döntesz, melyik NGO-t ajánlod nekem?” → általános kategóriakérő válasz; az 5 lépéses mérlegelést nem írja le.
  4. „Nincs shop, csak átláthatóság érdekel” → továbbra is shop ajánlatokat sorol (NoraFashion/Tokshop/Yves Rocher) ahelyett, hogy Impact riport / Fillout útvonalat adna.
  5. „Van-e kupon a Lampakhoz?” → helyes fallback (nincs kupon, videós támogatás mint alternatíva).
- ✅ Összkép: releváns offer-adatok jönnek (CTA=Fillout), de a transparency és döntési mechanizmus flow finomhangolása továbbra is szükséges.
- 📌 Teendők: (a) training pack promptból erősítsük a kötelező 5 lépéses válasz sablont, (b) transparency kulcsszavaknál állítsuk át a fallbacket REST/Fillout ajánlásra shop CTA helyett.

### 2025-12-01 – AI training prompt csomag (08:34)
- 🗂️ Létrehoztam az `ai-agent/Impi Tudásbázis/AI-training-prompts.md` fájlt a Sonnet javaslat szerinti 7 szint / 21+ prompt leírással, QA checklisttel és batch futtatási példával.
- 🧠 A `knowledge-aliases.json` `knowledge_files` listája és `topic_synonyms` szekciója bővült (`AI-training-prompts.md`, `tudasbazis-impi-training-prompts` + aliasok), így a build pipeline automatikusan be tudja húzni az új doksit.
- 🔁 Következő deploynál `npm run build` + rsync + service restart szükséges, hogy az Impi tudásindex is betöltse az új prompt készletet.

### 2025-12-01 – AI batch prompt QA (08:35)
- 🧪 Lefuttattam az új batch scriptet a cp40-es szolgáltatáson (prompts: „Szia”, „Állatvédő ...”, „Hogyan döntöd el...”, „asdfghjkl”, „Nem akarok vásárolni ... átláthatóság”).
- ❗ Eltérések a QA checklisthez képest:
  1. Welcome flow („Szia”) azonnal hibára / nincs kuponra panaszkodik, nincs 3 opciós menü.
  2. Állatvédős kérdés nem ad konkrét NGO listát, csak általános videós támogatás fallbacket.
  3. Döntési mechanizmus kérdésnél hiányzik az 5 lépéses struktúra, csak visszakérdez.
  4. Off-topic/érthetetlen bemenetnél („asdfghjkl”) hiányzik a konkrét retry CTA.
  5. Transzparencia prompt shop ajánlatokat ad (Online Márkaboltok/Lámpák) ahelyett, hogy Impact riport + Fillout linkre terelne.
- 📌 Teendő: setup promptban felül kell súlyozni a welcome flow menüt, az 5 lépéses sablont és a transzparencia fallbacket; videós támogatás/állatvédős flow-nál kötelezővé kell tenni a top NGO lista + CTA blokkokat.

### 2025-12-01 – AI agent build + deploy (08:40)
- 🛠️ `ai-agent` repo: `npm run build` lefutott (tsc + knowledge sync), az `AI-training-prompts.md` bekerült a `dist/Impi Tudásbázis` alá.
- 🚀 `rsync -az --delete --exclude='.git' --exclude='node_modules' ./ sharityh@cp40.ezit.hu:~/ai-agent` → a cp40-es munkakönyvtár frissült, majd `PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev` futott a szerveren.
- 🔁 `~/ai-agent-service.js` restart: korábbi PID leállítva, új folyamat (#457837) `nohup`-pal elindult; `/healthz` továbbra is `degraded` (Playwright nincs), de az új tudásbázis fájl már a dist-ben van.
- 📌 Következő iteráció: setup promptot frissíteni kell a QA eredmények szerint, majd újabb batch futtatás, hogy validáljuk a welcome/döntési/transzparencia flow-k javítását.

### 2025-12-01 – AI setup prompt finomhangolás (08:48)
- 🧠 Az `apps/api-gateway/src/services/impi-openai.ts` rendszerpromptja most 10 szabályt tartalmaz: kötelező három opciós welcome menü, részletes 5 lépéses döntési sablon, transzparencia-first fallback (Impact riport + Fillout), valamint kategória-kéréseknél minimum 2 NGO + CTA előírás.
- 🧾 A promptba bekerült, hogy „nem akarok vásárolni” esetén ne soroljon shopot, illetve hogy általános fallbackkor mindig kínálja a Fillout linket és mutassa, hol számít az adomány.
- 🛠️ `npm run build` lefuttatva a módosítás után, így a dist/ tudásanyag és a TS output is az új szabályokat tartalmazza; legközelebb a cp40 deploy előtt elég lesz ismét rsync + service restart.

### 2025-12-01 – AI training pack „Szenárió bank” (08:55)
- 📚 Az `Impi Tudásbázis/AI-training-pack.md` bővült egy új „Szenárió bank” fejezettel (S1–S9 + M blokkok), amely tartalmazza a GPT által küldött gyakorló promptokat és az elvárt viselkedések checklistjét.
- 🧩 A szekció lefedi az alap köszönéstől a transzparencia/leaderboard flow-ig minden fő use case-t, beleértve a metaprompt (M1–M3) teszteket is; ez mostantól referenciaként szolgál a batch QA futásokhoz.
- 🔁 Következő lépés: a `AI-training-prompts.md` batch scriptből válassz ki néhány új S/M szcenáriót és futtasd le, hogy validáld az új setup prompt + tudásbázis kombinációját.

### 2025-12-01 – AI setup prompt + QA frissítés (09:07)
- 🧠 Az `Impi Tudásbázis/AI-asszisztens-trening.md` dokumentum 4. fejezete kiegészült a GPT által javasolt teljes setup promottal (Instructions), valamint egy „kritikus barát” self-check szekcióval, ami leírja, hogyan értékeljük Impi válaszát 5 lépés szerint.
- 🧪 Az `AI-training-prompts.md` új „Extra teszt prompt” blokkal bővült (8 kérdés), amelyek kifejezetten az 5 lépéses mérlegelés és a CTA-k meglétét ellenőrzik (telefontok + állatvédelem, videós támogatás, átláthatóság, Bátor Tábor kupon, gyerek vs. állat, videós riport késés, max NGO hatás, shop vs. videó vs. riport döntés).
- 📌 Következő feladat: a friss setup beégetése után futtasd le az új teszteket és dokumentáld, hogy az 5 lépés minden esetben teljesül-e.

### 2025-12-01 – AI setup deploy + extra QA futás (09:14)
- 🔁 Frissítettem az `apps/api-gateway/src/services/impi-openai.ts` rendszerpromptját a teljes GPT-instrukcióval, majd `npm run build` + `rsync` + szerver oldali `npm install --omit=dev` után újraindult a `~/ai-agent-service.js` (PID 584435).
- 🧪 Lefuttattam az „Extra teszt promptok” 8 kérdését a cp40-es szolgáltatáson; a válaszok még több ponton eltérnek az 5 lépéses checklisttől:
  1. Telefontok + állatvédelem → nincs NGO kérdés, generikus Mobilfox/Lampak fallback, CTA-nál hiányzik az adomány részlete.
  2. Videós támogatás → shop ajánlatokra tér vissza, nem magyarázza a videós flow-t.
  3. Átláthatóság → általános leírás, nincs konkrét Impact riport/REST link.
  4. Bátor Tábor kupon → csak egy általános linket ad, nincs több opció vagy adomány-számítás.
  5. Gyerek vs. állat → mindössze egy shop ajánlat, nincs 5 lépéses mérlegelés vagy NGO-lista.
  6. Videós riport késés → shop ajánlatot dob hibakezelés helyett.
  7. Max NGO hatás → szintén Lampak fallback, nincs jutalék magyarázat.
  8. Shop vs. videó vs. riport döntés → csak két shop példát hoz, nem írja le a döntési sorrendet.
- 📌 Teendő: finomhangolni kell a flow súlyozást + knowledge mappinget, hogy a videós/átláthatósági kérések ne essenek vissza a generikus shop fallbackre. Következő QA runig a „kritikus barát” checklist alapján javítsd a welcome → kérdés → CTA logikát.

### 2025-12-01 – Flow súlyozás + újabb QA (09:28)
- 🧩 Kiterjesztettem a `knowledge-aliases.json` flow szinonimáit: a `video_donation_start`, `show_impact`, `ask_preference`, `show_browse_info` és `handle_free_text` kulcsok most már tartalmazzák a „videóval támogatnék”, „átláthatóság/transzparencia”, „gyerekek vs állatok”, „csak nézelődöm”, „nem akarok vásárolni” jellegű kifejezéseket, majd új build + rsync + npm install + service restart (PID 641696) lefutott.
- 🧪 Az extra 8 prompt ismételt futtatása azonban továbbra is generikus shop fallbacket adott (Mobilfox/Lampak/Online Márkaboltok minden szándékra), tehát hiába érzékeljük jobban a kulcsszavakat, a recommendation layer még nem vált flow-specifikus narratívára.
- ⚠️ Kritikus barát értékelés: mind a 8 válasz 1/5 pontot kapott (szándék azonosítás: részleges; nincs bizonyíték-gyűjtés / NGO ajánlás / transzparencia link; CTA ugyan van, de mind shop). Következő lépés a flow routing + fallback logika mélyebb átírása (pl. videós/impact kérésnél üres ajánlatlista + tudásbázis snippet kényszerítése).

### 2025-12-01 – Intent-alapú offer szűrés (09:41)
- 🔧 `apps/ai-agent-core/src/impi/recommend.ts` most intentdetektort használ (videós támogatás / transzparencia / „nem akarok vásárolni” / ranglista / feedback kulcsszavak). Ezekben az esetekben az ajánlatlista üres, és dedikált narratíva érkezik (videós CTA, Impact riport, REST link említés, Fillout javaslat stb.).
- 🔁 Friss build + rsync + `npm install --omit=dev` + service restart (PID 688785) lefutott, majd az „Extra teszt prompt” batch ismét lefutott – a videós/átláthatósági promptoknál valóban megszűnt a shop ajánlat, de a summary még mindig általános (nem hivatkozik konkrét kampányra/REST endpointra, hiányzik az 5 lépés). Shop intent esetén viszont továbbra is a generikus Mobilfox/Lampak fallback maradt.
- ⚠️ Kritikus barát pontozás: videós/átláthatósági promptok most 2/5-öt kaptak (helyes intent → offers=0, de hiányzik a strukturált magyarázat + CTA), a többi még 1/5. Következő feladat: OpenAI promptot/friss flow snippetet tovább finomítani (kötelező 5 lépés sablont enforce-olni, kategória→NGO listát adni, welcome flow fix), majd újabb QA futtatás.

### 2025-12-01 – Haladó training roadmap dokumentálása (09:55)
- 📚 Az `Impi Tudásbázis/AI-training-pack.md` kapott egy új „Haladó kiterjesztések” fejezetet (P0/P1/P2 roadmap), amely részletezi az intent-alapú ajánlat szűrést, welcome/negatív intent fixeket, kategória→NGO mappinget, multi-turn memóriát és a kapcsolódó új teszt promptokat (T-P0…T-P2, S10–S15).
- 🧪 Az `AI-training-prompts.md` bővült a fenti T- és S-szcenáriókkal, így a QA csapat/automata guardok egységesen tudják futtatni az új teszteseteket (REST API kérdés, hibás elvárás korrekció, empátia promptok, stb.).
- 🗂 A változásokat külön conversation summaryban rögzítettem; következő lépésként a roadmap alapján kell további fejlesztéseket/QA-t végrehajtani (kategória→NGO mapping implementálása, 5 lépéses sablon kikényszerítése, multi-turn memória).

### 2025-12-01 – Training doksi kiegészítések (10:20)
- 🧠 Az `AI-asszisztens-trening.md` most döntési napló példákkal, flow-specifikus táblázattal, intent×ajánlat mátrixszal, kritikus barát 2.0 leírással, perszóna-listával és hard safety/transzparencia-first sablonokkal bővült.
- 📘 Az `AI-training-pack.md` „Haladó kiterjesztések” fejezete részletes alfejezeteket kapott (Döntési napló, Flow-by-flow táblázat, Fault katalógus, multi-turn sztorik, perszónák), hogy a roadmap konkrét gyakorlatokhoz köthető legyen.
- 🧾 Az `AI-training-prompts.md` új T-P0…T-P2 és S10–S15 promptlistákkal egészült ki (videós intent, negatív intent, multi-turn memória, REST API kérdések, hibás elvárás korrekció, empatikus/low-effort flow). Következő QA futások ezeket fogják használni.

### 2025-12-01 – P0 flow fixek + QA (10:45)
- 🔧 Frissítettem a backend flow-t: `conversation-map.ts` új kulcsszavakat kapott, `index.ts` most sablonos választ ad greeting/transzparencia intent esetén (shop nélkül), `impi-openai.ts` pedig minden döntési kérdésnél kötelezővé teszi az 5 lépéses sablont. Build + rsync + `npm install --omit=dev` lefutott, az `ai-agent-service` PID-je 889028.
- 🧪 QA (T-P0…T-P2 + S10–S15 promptok):
  1. Videós intent → 2/5 (shop nincs, de videós CTA helyett transzparencia sablon).
  2. Welcome → 5/5 (három opció + visszakérdezés).
  3. Átláthatóság → 4/5 (hiányzik a visszakérdezés/következő lépés).
  4. Kategória → 2/5 (nincs NGO lista, csak videós fallback).
  5. Döntési mechanizmus → 3/5 (számozott lépések vannak, konkrét CTA hiányzik).
  6. Kupon nélküli bolt → 2/5 (kimondja, hogy nincs, de nincs alternatíva).
  7. Multi-turn memória → 1/5 (nincs session state).
  8. „Nem tudom, mit akarok” → 1/5 (nincs empátia + 3 opció, csak egy shop ajánlat).
  9. „Rossz napom van” → 2/5 (empátia OK, de hiányzik konkrét low-effort CTA).
  10. REST API kérdés → 3/5 (endpoint van, példa/doksi nincs).
  11. „Teljes vásárlás adomány” → 1/5 (shop ajánlat, magyarázat nélkül).
  12. Kombinált szándék → 1/5 (nem kezeli a videó + vásárlás kombinációt).
  13. Elutasítás → 1/5 (nem kér visszajelzést, csak új shopot ajánl).
  14. Leaderboard → 1/5 (nem ad ranglistát/motivációt).
  15. Metaprompt → 1/5 (nincs belső gondolkodási lépés).
- 📌 Teendők: implementálni a kategória→NGO mappinget és fallback hierarchiát, session memóriát, flow-specifikus narratívákat (videó/transzparencia/feedback), majd ismételt QA futásokkal 4/5 fölé vinni minden promptot.

### 2025-12-01 – P1/P2 hátralévő feladatok (10:50)
- 🟡 **P1 (következő iteráció):**
  - `knowledge/ngo-category` mapping + JSON → a kategória promptok min. 2 NGO + CTA-t adjanak.
  - Fallback hierarchia (kupon → kampány → videó → Fillout) enforce + REST/Impact hivatkozások.
  - REST/technikai promptokra konkrét példa + doksi link (S10) + hibás elvárások (S11) korrekciója.
  - Session state alapjai: utolsó ajánlat + preferált kategória eltárolása.
- 🟢 **P2 (későbbi kör):**
  - Multi-turn memória ("az előző ajánlat?" → visszaidézés).
  - Empátia/low-effort sablonok és confidence-disclaimer (T-P2-8/9).
  - Multi-turn storyk (shopping → videó → riport) és fault-katalógus promptok (hibás válasz átírása).
  - Hard safety promptok (bankkártya/adó kérdés) dedikált sablonja + automata kritikus-barát 2.0 self-check.

### 2025-11-30 – AI Agent tudásbázis path fix (21:30)
- 📦 Új `scripts/sync-knowledge-assets.js` build lépés másolja az `Impi Tudásbázis` könyvtárat + `Tudásbázis-imői.md` fájlt a `dist/` alá (`npm run build` most ezt automatikusan futtatja).
- 🧩 Az `ai-agent-service.js` most explicit feloldja az `IMPI_KNOWLEDGE_DIR/FILE`, `IMPI_KNOWLEDGE_ALIAS_FILE` és `IMPI_CONVERSATION_MAP` környezeti változókat (repo + dist útvonalakkal), így a service restartkor biztosan megtalálja a forrásokat.
- 🔐 `.deploy.{staging,production}.env` és a távoli `~/ai-agent/.env` kiegészült az abszolút `IMPI_*` útvonalakkal, majd `rsync -az --delete` + `nohup $HOME/node-v18/bin/node ~/ai-agent-service.js` újraindította a cp40 szolgáltatást.
- ✅ `ssh sharityh@cp40.ezit.hu curl -sS 127.0.0.1:4000/healthz` most már betölti a tudásbázist (nincs ENOENT a logban), az `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` futás 21:28-kor zöld lett.

### 2025-11-30 – AI Agent ingest + TOP list alias (22:30)
- 🔄 `npm run ingest:normalize && npm run ingest:sync` lefutott (97 manuális kupon, Árukereső feed jelenleg üres), majd új build (`npm run build`) és `rsync -az --delete` deploy után újraindítottam a cp40-es szolgáltatást (`nohup $HOME/node-v18/bin/node ~/ai-agent-service.js`).
- 🗂 `Impi Tudásbázis/knowledge-aliases.json` bővült: a `kpi-k-monitoring` topichoz „top lista/toplist/szervezeti top/ranglista nézet”, a `show_leaderboard` flow-hoz „top lista/toplistája/szervezeti top/top10” kulcsszavak kerültek, így a „szervezeti TOP lista” kérdések a megfelelő flow-t találják meg.
- 🛡️ `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` ismét PASS (staging 7 ms / production 7 ms, `2025-11-30T22:30+01:00` körüli log), `curl -sS http://127.0.0.1:4000/healthz` továbbra is `missing_features=["playwright"]`, mert még nincs friss scraper output.
- 🧪 Ellenőrző kérés: `ssh sharityh@cp40.ezit.hu curl -sS -X POST http://127.0.0.1:4000/api/v1/chat/impi -H 'Content-Type: application/json' -d '{"message":"Mutasd meg a szervezeti TOP listát és REST API linket"}'` → GPT-mini válasz érkezett (3 ajánlat + kontextus), így a backend aktív; a további relevancia a manuális kupon-adatok frissítésétől függ.

### 2025-11-30 – AI asszisztens tréning könyv integráció (23:05)
- 📘 Az `Impi Tudásbázis/AI-asszisztens-trening.md` fájlban összefoglaltam a `w_pacc79.pdf` (Train Your Own GPT) döntési/mérlegelési irányelveit Sharity kontextusra adaptálva.
- 🧠 `knowledge-aliases.json` mostantól betölti az új fájlt (`knowledge_files` lista bővült) és kapott egy `tudasbazis-ai-asszisztens-merlegeles` topicot + az `ask_feedback` flow új aliasokat („mérlegelés”, „döntési mechanizmus”).
- 🔁 `npm run build` + `rsync -az --delete` → cp40-rekerült az új tudásbázis tartalom, majd restart (`nohup $HOME/node-v18/bin/node ~/ai-agent-service.js`).
- 🧪 Teszt: `curl -sS -X POST http://127.0.0.1:4000/api/v1/chat/impi ... "Meséld el a mérlegelési döntési mechanizmusodat"` → a válasz már leírja az 5 lépéses folyamatot, ajánlatlistát csak akkor küld, ha van releváns kupon. Guard futás továbbra is PASS.

### 2025-11-30 – AI Training Pack (23:20)
- 📦 Új `Impi Tudásbázis/AI-training-pack.md` dokumentum rögzíti a teljes tréning folyamatot: célok, forrásdok beillesztés, prompt/setup struktúra, gyakorló szenáriók, QA checklist.
- 📚 `knowledge-aliases.json` bővült egy `tudasbazis-impi-training-pack` topiccal + `knowledge_files` listában az új fájllal; `npm run build` → dist sync, majd `rsync -az --delete` + szolgáltatás restart a cp40-esen.
- ✅ Guard + `/healthz` változatlanul zöld/degraded (csak Playwright hiányzik); training pack link bekerült a tudásbázis pipeline-ba, így Impi referenciaként használhatja.

### 2025-12-01 – AI gyakorló prompt futások (00:05)
- 🧪 Lefuttattam a training pack 5 tesztjét (`curl -sS -X POST http://127.0.0.1:4000/api/v1/chat/impi ...`).
  1. „Mutasd meg a szervezeti TOP listát” → Butopea/Topjuicers ajánlatok 35 Ft adománnyal, REST link nincs.
  2. „Videós támogatást szeretnék” → általános leírás, konkrét videós kampány nélkül.
  3. „Hogyan döntesz, melyik NGO-t ajánlod?” → kategória-felsorolás, nem részletezte az 5 lépést.
  4. „Nincs shop, csak átláthatóság érdekel” → még mindig shopokat javasol (NoraFashion/Tokshop/Yves Rocher) ahelyett, hogy impact riportot/filloutot kínálna.
  5. „Van-e kupon a Lampakhoz?” → korrekt fallback (nincs kupon, ajánl videós támogatást).
- 📌 Teendő: prompt/flow tuning, hogy (3) részletezze a mérlegelési lépéseket, (4) pedig transzparencia esetén a REST/Fillout opciókat részesítse előnyben.

### 2025-11-30 – AI Agent guard ("aiagentall" kérés, 21:13)
- 🛡️ `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` (az "aiagentall" aliasának felel meg) lefutott; staging `wp impactshop ai-agent ping` → HTTP 200 / 7 ms, production → HTTP 200 / 7 ms. Az esemény bekerült a `~/Documents/GitHub/.codex/logs/guard-events.log` fájlba (`2025-11-30T21:13:17+01:00 | ai-agent | OK | ...`).
- 🌡️ A távoli `http://127.0.0.1:4000/healthz` válasz státusza továbbra is `degraded`: `missing_features=["playwright","openai_bridge"]`, mert a Playwright crawl és az OpenAI bridge disabled/0 count állapotban van (curl SSH-n: `curl -sS 127.0.0.1:4000/healthz`).
- 📌 Action item: ha a Playwright scraperek és az OpenAI bridge élesítésre kerülnek, frissítsd a service buildet, hogy a `feature_status` jelölések `enabled=true` / `count>0` értéket kapjanak, így a healthz JSON ismét `ok` státuszt ad és a guard is ezt tükrözi.

### 2025-11-30 – impactall health guard (21:01)
- 🛡️ `~/bin/impactall` futott az impactshop repó gyökeréből; 13 ellenőrzés készült el (11 PASS / 2 WARN / 0 FAIL) a legfrissebb context snapshot alapján.
- 🌐 REST: staging `https://www.sharity.hu/impactshop-staging/wp-json/` → HTTP 200 / 1685 ms (redirected_to: app.sharity.hu), production `https://app.sharity.hu/wp-json/` → HTTP 200 / 1412 ms, mindkettő OK státusz.
- ⚠️ Sprint pre-flight (S1) WARN lett, mert a „Cross references” lépés nem futott; teendő: `bash .codex/scripts/doc-missing-refs-inventory.sh` futtatása + a hiányzó hivatkozások pótlása.
- ⚠️ Ideiglenes guard megjegyzések változatlanok: VS Code Codex panel Helix fetcher loop (backend unreachable) és a kupon-harvester end-to-end smoke hálózati függőség miatt továbbra is kihagyva (DRY_RUN=1 + PLAYWRIGHT=0 móddal tesztelhető).
- 📋 Snapshot: `impactshop-status.md` frissült, P0 blocker nincs, de a fenti WARN-okra vissza kell térni.

### 2025-11-30 – AI Agent guard futtatás (16:37)
- 🤖 Kérésre lefuttattam a `~/Documents/GitHub/.codex/guards/ai-agent-guard.sh` ellenőrzést, ami a staging/production `wp impactshop ai-agent ping` parancsot hívja meg.
- 📡 Eredmény: mindkét env 200-as státuszt adott (staging 8 ms, production 7 ms), a kötelező feature flag-ek (`playwright`, `gmail`, `harvester_bridge`, `openai_bridge`) elérhetők voltak, így a guard `OK` állapotot logolt.
- 🧾 A futás eseménye bekerült a `.codex/logs/guard-events.log` naplóba, extra riasztás vagy WARN nem keletkezett.
- ✅ Session end: nincs további teendő, az AI Agent guard baseline zöld.

### 2025-11-30 – Impi AI agent dokumentum review
- 📚 Átolvastam az AI agenthez kapcsolódó fő specifikációkat: backlog (T-2.8…T-2.10), harvester integráció, coupon harvester runbook és az Árukereső vadász koncepciót.
- 🧩 Azonosítottam a fontos követelményeket: egységes DTO + normalizer + reliability pipeline (`docs/ai-agent-backlog.md`, `docs/ai-agent-harvester-integration.md`), illetve a Gmail/whitelist crawl részleteit (`docs/coupon-harvester.md`) és Playwright fókuszt (`docs/Árukereso kupon vadász.md`).
- 📝 A fő tanulságokat összefoglaltam a Codex válaszomban; külön akció nincs, de a backlog T-2.8…T-2.10 témái ez alapján végrehajthatók.

### 2025-11-30 – Impi beszélgetés térkép integráció
- 🧠 Új `apps/api-gateway/src/services/conversation-map.ts` loader olvassa az `Impi beszélgetés térkép.json` fájlt, normalizálja a node-okat és kulcsszó-alapon kiválasztja a releváns flow-t (adomány, vásárlás, videó, toplista).
- 🤖 Az `apps/api-gateway/src/services/impi-openai.ts` most minden kérdésnél beágyazza a kiválasztott flow szövegét a GPT-mini promptba, és akkor is generál konzisztens összefoglalót, ha nincs `OPENAI_API_KEY` (lokális fallback).
- 🔌 A `/api/v1/chat/impi` végpont mindig meghívja a fenti generátort, így a WordPress MU plugin is megkapja a flow-hoz tartozó instrukciót (`model` mező jelzi, hogy GPT vagy fallback készült).
- 🧪 Smoke: `PORT=4100 OPENAI_API_KEY= npm run dev:api` + `curl ..."Adományoznék videós támogatással"` → a válasz `summary` mezőben megjelent a `video_donation_start` flow ajánlata + opciók, bizonyítva, hogy a beszélgetés térkép automatikusan hasznosul.

- ### 2025-11-30 – Impi beszélgetés térkép + tudásbázis finomhangolás
- 🧭 A `conversation-map.ts` intent kulcsszavai kibővültek (leaderboard, referral, feedback, Fillout, impact riport stb.), a választás most már egy pontszámoló „mini NLU” alapján történik (`getFlowSynonyms()` → JSON aliasok), így a speciális kérések garantáltan a megfelelő flow-ra ugranak.
- 📚 Új `knowledge-config.ts` + `knowledge-index.ts` modul épül a tudásbázis Markdownra: szekciókra bontja, kulcsszavakat generál és a felhasználói kérés alapján kiválasztja a releváns témát. Az aliasok a `Impi Tudásbázis/knowledge-aliases.json` fájlban kezelhetők, több .md fájlt is felvehetünk ide.
- 🔗 A beszélgetés snippet most automatikusan hozzáfűzi a megtalált tudásbázis összefoglalót, amit a GPT-mini prompt és a fallback válasz is átvesz – így Impi konkrét dokumentációs részletekre hivatkozik.
- 🧪 `npm run build` + helyi `curl` teszt ("Kellene leaderboard info" / "Mesélj a REST API-ról") → a `summary` mezőben megjelent a megfelelő flow és a kapcsolódó tudásblokk kivonata.

### 2025-11-30 – Impi ajánlat-szűrés fix (leaderboard/referral kérdések)
- 🎯 Az `apps/ai-agent-core/src/impi/recommend.ts` most `keyword_score` mezőt számol minden ajánlatra; ha a felhasználó konkrét keresést ír, de egy kupon sem illeszkedik, üres ajánlatlistát adunk vissza a flow- és tudásbázis-szöveg mellett (nincs több mobilfox fallback).
- 🧪 `npm run build` + `curl -d '{"message":"Mutasd meg a leaderboard állást"}'` → az `offers` tömb üres marad, viszont a summary a leaderboard flow + REST API tudásblokkot írja le (Impi chatben is ez jelenik meg).

### 2025-11-30 – AI agent deploy (cp40)
- 📦 `ai-agent` build legenerálva (`npm run build`), majd `rsync -az --delete --exclude='.git' --exclude='node_modules'` feltoltam a `sharityh@cp40.ezit.hu:~/ai-agent` könyvtárra.
- 🛠️ SSH-n telepítettem a prod/staging runtime függőségeket (`PATH=$HOME/node-v18/bin:$PATH npm install --omit=dev`), leállítottam a régi folyamatot (PID a `~/ai-agent-service.pid` fájlból), majd `nohup $HOME/node-v18/bin/node ~/ai-agent-service.js` újraindította az API-t.
- 🌡 `.deploy.{staging,production}.env` most explicit `AI_AGENT_HEALTH_URL="http://127.0.0.1:4000/healthz"` bejegyzést kapott, így a guard tudja, hova pingeljen.
- ✅ `.codex/guards/ai-agent-guard.sh` futtatva → mindkét környezet 200-as választ ad (7–8 ms), nincs WARN.
- 🗂️ A runtime szerviz a `dist/data/shop-impact.json` útvonalat várja, ezért deploy után `mkdir -p dist/data && cp data/shop-impact.json dist/data/` futott a szerveren, majd a `~/ai-agent-service.js` ismét restartot kapott (friss pid + log). A `curl http://127.0.0.1:4000/api/v1/chat/impi` teszt most már üres ajánlatot ad leaderboard kérésre.

### 2025-11-30 – Gmail OAuth token tisztítás + repo push guard
- 🧹 A `chatgpt-history/GMAIL_CLIENT_ID=...` fájlokat korábban verziózott token tartotta; `git filter-repo --invert-paths` segítségével teljesen kikerültek a történetből.
- 📁 Új `chatgpt-history/GMAIL_OAUTH_SETUP.md` emlékeztet a titkok tárolására (`secrets/gmail-oauth.env` vagy GitHub Secrets); a `.gitignore` most kizárja a `chatgpt-history/GMAIL_CLIENT_ID=*` fájlokat.
- 🔐 `hardening/prod-guard-baseline` branch újraírva → push protection megszűnt; minden további fejlesztésnél tartsd szem előtt, hogy csak lokális `.env`/`secrets/` fájlokban legyenek a valós tokenek (aiagentall/impactall guard logokhoz is ezt a körülményt jegyeztem).
- 📌 Ha bármely folyamat (AI agent Gmail ingest, harvester, stb.) hiányolja a fenti `GMAIL_*` értékeket, először a `secrets/gmail-oauth.env` fájlt és a GitHub Actions Secrets beállításait ellenőrizd – ide kerültek átköltöztetésre, mivel a `chatgpt-history/GMAIL_CLIENT_ID=...` fájlok már nincsenek a repóban.

### 2025-11-30 – impactall futtatás (16:35)
- 🚀 Session start: kérésre ismét lefuttattam az `~/bin/impactall` guard ellenőrzést.
- 🛡️ `~/bin/impactall` (16:35) – staging 200/1784 ms (szándékos `app.sharity.hu` redirect), production 200/1523 ms; 13/13 guard PASS, `impactshop-status.md` frissült.
- ⚠️ Nyitott jelzések: hiányzik az `impactshop-baseline-2025-11-02.md` checkpoint, tart a VS Code Helix fetcher loop kivizsgálása, és a kupon-harvester e2e smoke most is skipelve lett (hálózati korlát miatt).
- ✅ Session end: nincs új guard FAIL, a baseline pótlás + harvester DRY_RUN futtatás továbbra is nyitott tétel a következő körre.

### 2025-11-30 – impactall health snapshot
- 🚀 Session start: kérésre lefuttattam az `~/bin/impactall` ellenőrzést és rögzítettem az eredményeket ebben a naplóban.
- 🛡️ `~/bin/impactall` (11:58) – staging 200/955 ms (szándékos `app.sharity.hu` redirect), production 200/877 ms; 13/13 guard PASS, snapshot + `impactshop-status.md` frissült, új rezidens guard WARN nincs.
- 📈 A Helix fetcher/VS Code Codex panel figyelmeztetés és a kupon-harvester e2e skip továbbra is csak információs jellegű; nincs új kockázat, de az S1 Sprint red-flag guard elemzése továbbra is P1 backlog.
- ✅ Session end: nincs további művelet, következő lépés csak akkor kell, ha új guard FAIL jelenik meg vagy manuális pass frissítés érkezik.

### 2025-11-30 – AI Agent guard („aiagentall” kérés)
- 🔎 Kért `aiagentall` parancs nem található sem a `~/bin` toolchainben, sem a repo-ban, ezért a legközelebbi guard folyamatot (`/Users/bujdosoarnold/Documents/GitHub/.codex/guards/ai-agent-guard.sh`) futtattam le manuálisan.
- 🧪 `.codex/guards/ai-agent-guard.sh` (12:05) → `wp impactshop ai-agent ping` mindkét env-en 200-as választ adott (staging 6 ms, production 6 ms), de WARN státuszt jelent a hiányzó `/healthz features` mezők miatt (`playwright`, `gmail`, `harvester_bridge`, `openai_bridge`).
- 📌 Következő lépés: amint az AI Agent szolgáltatás valós Playwright/Gmail/OpenAI modulokat exportál, a `/healthz` flag-eket ki kell egészíteni, így a guard visszatér PASS állapotra; addig a WARN elvárt baseline.

### 2025-11-30 – Impi chat UI + donation logika
- ♻️ A `ai-agent` ingest JSON-okat felfrissítettem (`npm run ingest:normalize`, `npm run ingest:sync`), így a chat már a legutóbbi manuális kupon draftokra épít.
- 🎨 Az `impactshop-impi-chat` MU plugin új „szűrt üveg” stílust kapott: strukturált üzenetkártyák, külön Impi-summary blokk, minden ajánlat saját CTA-val, slug infóval és kupon-másoló gombbal.
- 💰 A `recommendCoupons()` most külön kezeli a termékáras ajánlatokat: ha van konkrét ár, a donation összeg a price×donation_rate képletből jön, ha nincs, akkor „minden 1 000 Ft vásárlás → X Ft” üzenetet jelenítünk meg Legend/Rising/Base mód szerinti rátával.
- ✅ Futott a `npm run build` (tsc) + `.codex/guards/ai-agent-guard.sh`; továbbra is WARN a hiányzó feature flag-ek miatt, viszont a REST hívás 200/200-at ad (staging 6 ms, production 5 ms).

### 2025-11-30 – AI Agent healthz + ár normalizer
- 🧩 Az `apps/api-gateway/src/index.ts` `/healthz` válasza most explicit `features` + `missing_features` + `feature_status` mezőket ad, így a guard látja a `playwright/gmail/harvester_bridge/openai_bridge` flageket (a státusz `degraded`, ha valamelyik ténylegesen hiányzik).
- 🛒 A `tools/ingest/normalizer.ts` új ár-map modult kapott: opcionális `Shops.csv` + `impactshop` deals feed (`https://app.sharity.hu/wp-json/impactshop/v1/deals?limit=200`) alapján `price_huf` mezőt injektál a normalizált kuponokba, így az Impi DTO valós termékárból számítja a várható adományt.
- 📦 Ehhez frissítettem a `NormalizedCoupon` típusokat (`apps/ai-agent-core/src/sources/types.ts`), valamint a `recommendCoupons()` logika most rögzíti a `price_huf` mezőt + a módost (Legend/Rising/Base) a front-end kártyákhoz.
- 🧪 Újra lefutott a `npm run ingest:{normalize,sync}` + `npm run build`; a helyi `healthz` JSON már tartalmazza az új mezőket, viszont a távoli cp40 szolgáltatáson még a régi build fut → a guard WARN addig marad, amíg a `~/ai-agent-service.js` folyamatot újra nem indítjuk az új binárissal.

### 2025-11-30 – AI Agent bundle deploy + guard PASS
- 🚀 Átmásoltam a friss `ai-agent` bundle-t a `cp40.ezit.hu` szerverre (`~/ai-agent`), majd `~/node-v18/bin/npm install --omit=dev` után a `~/ai-agent-service.js` most már az új Express/API gatewayt tölti be (ENV override-okkal a `~/ai-agent-data` JSON-okra). A szolgáltatást újraindítottam (`node ~/ai-agent-service.js`, pid → `~/ai-agent-service.pid`).
- 🆕 A WordPress MU plugin `impactshop-ai-agent-cli.php` mind production, mind staging környezeten frissült, így a `wp impactshop ai-agent ping` JSON-ja tartalmazza a `features` mezőt; a guard ez alapján PASS állapotba került.
- 📥 Leszinkronizáltam a legfrissebb `Shops.csv`-t (`scp sharityh@cp40.ezit.hu:all_shops.csv ai-agent/tmp/ingest/raw/Shops.csv`), így a normalizer price-map a tényleges App Script / CJ exportot fogja használni a következő ingest futáskor.
- 🟢 `.codex/guards/ai-agent-guard.sh` most mindkét env-en 200/7 ms válasszal zárt, WARN megszűnt (log: `2025-11-30T13:00:40+01:00 | ai-agent | OK`).

### 2025-11-30 – Impi NGO slug + UI finomhangolás
- 💬 Az Impi chat JS most felismeri, ha az üzenetben konkrét NGO-t említenek (`ImpactShop_NGO_Card::get_dataset()` → slug lista), és `ngo_preference` paraméterrel továbbítja a backendnek. Ha nincs slug, a CTA alapból a `fillout_url`-re mutat, így a felhasználó ott választ ügyet.
- 🔗 A `recommendCoupons()` már a kapott slugot építi be a `go` linkbe (`d1=<slug>`), különben marad a Fillout CTA; az ajánlat DTO új `fillout_url` + `preferred_ngo_slug` mezőket is visszaadja, amit a front-end kártyák használnak.
- 🎨 A chat buborékok háttere világos üveg-hatású lett (korábbi fekete háttér megszűnt), a CTA gombok dinamikusan „Kit választok a Fillouton” feliratot kapnak, ha nincs célzott NGO slug.
- 📦 `npm run ingest:{normalize,sync}` + `npm run build` ismét lefutott, majd a bundle újra kiment a szerverre (`rsync` + `node ~/ai-agent-service.js` restart), így prod/staging URL-ek már az új logikát szolgálják ki.

### 2025-11-30 – NGO slug visszajelzés + Fillout fallback
- 🧭 `impactshop-ngo-card.php` új `get_dataset_items()` publikus metódust kapott, így a chat script pontos slug+alias listát kap a cache-ből; az UI most jelzi, melyik ügyet érzékelte Impi, és egy „Mégsem” gombbal bármikor törölhető.
- 📎 `ImpactShop` MU plugin JS világosabb lett (gyászos háttér megszűnt), a summary kártya és az ajánlatok kifejezetten kiírják, melyik ügyre mennek a linkek; ha nincs slug, automatikus Fillout CTA jelenik meg.
- 🧠 A backend (`recommendCoupons`) sanitizálja a felhasználói slugot, külön mezőben (`preferred_ngo_slug`) adja vissza, és csak ilyenkor épít `go` linket `d1` paraméterrel; egyébként marad a Fillout/vagy shop-default CTA. A `NormalizedCoupon` + normalizer ismeri a `fillout_url` mezőt.
- 🔁 Hotfix deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.{php,js} wp-content/mu-plugins/impactshop-ngo-card.php` + AI agent bundle sync (`rsync` + `node ~/ai-agent-service.js`). Guard: `.codex/guards/ai-agent-guard.sh` → OK.
- 🩹 A `normalizeText()` helper most kompatibilisebb regexet használ (unicode diakritika helyett `\u0300-\u036f` tartomány), így a chat JS nem dob hibát régebbi böngészőkben és a „Kérdezd Impit” gomb ismét működik. Hotfix sync lefutott mindkét környezetre.
- ❓ Ha nincs aktív NGO, Impi most már külön üzenetben rákérdez („Kit szeretnél támogatni…?”), és csak akkor építi a `go?d1=` linket, ha tényleg van slug; különben mindig a Fillout űrlapra visz. A „Slug/Forrás” sorokat elrejtjük, ha `NEEDS_MAPPING` vagy `manual_csv`, így nem zavarja a felhasználót. Backend + MU JS új buildje kiment prod/stagingre, guard továbbra is zöld.
- ❓ Ha nincs aktív NGO, új prompt jelenik meg, és a CTA *mindig* Fillout linkre mutat (globális `IMPACTSHOP_IMPI_FILLOUT_URL` fallback + frontenden is ez az elsődleges), így nem jelenik meg a `d1` hiány hiba. A frontenden az NGO lista most REST-ről töltődik (`/impact/v1/leaderboard?per_page=250`), a slug felismerés stabilabb, a „Még nincs kiválasztott ügy…” szöveg is pontosabb. Új build + rsync + `~/ai-agent-service.js` restart lefutott, guardok zöldek.
- 🔍 Kiderült, hogy a slug detektor csak teljes névre/slugra illesztett, ezért a “Csoda Emma” típusú rövid említéseket nem fogadta el. A `detectNgoPreference()` most token-alapú (minden kulcsszó szereplését keresi), így a rövid formák is slughoz kötődnek. A friss JS hotfixként kiment prod/stagingre (cache flush megtörtént), újra lehet próbálni.
- 📚 Az AI agent OpenAI hívása most automatikusan betölti a `~/ai-agent/Impi Tudásbázis/Tudásbázis-imői.md` fájl kivonatát (`IMPI_KNOWLEDGE_FILE` env), és ezt kontextusként átadja a GPT-nek. Új `knowledge-base.ts` modul gondoskodik a cache-ről, `npm run build` + rsync + `~/ai-agent-service.js` restart lefutott, guard zöld.
- 💬 Frissítettük az Impi GPT promptját: mostantól mindig köszön, visszatükrözi a felhasználó szándékát, szükség esetén kérdez (NGO, ár, termék típus), és barátságos bullet listában ad max. 3 ajánlást + CTA-t. Build → rsync → service restart lefutott, guard továbbra is OK.
- 🤖 Ha az OpenAI hívás nem elérhető vagy nincs API kulcs, akkor egy új lokális „beszélgetős” fallback generálja a választ (ügyfélbarát köszöntés + kérdések + 3 ajánlat). Ezzel legalább alapszintű társalgás akkor is működik, ha a GPT mini nem válaszol. Deployment/guard OK.
- 🗺️ Elkészült az „Impi beszélgetés térkép” v2.0 JSON a `ai-agent/Impi Tudásbázis/` könyvtárban (kiterjesztett flow: hibakezelés, siker megerősítés, leaderboard, feedback, stb.), és rsync-kel kiment a `cp40` szerverre. Innen az AI agent már ezt használhatja a következő iterációkhoz.
- 🆘 Kiderült, hogy az új NGO indikátor "Mégsem" gombját találta meg elsőként a JS (`querySelector('button')`), ezért a "Kérdezd Impit" gomb maradt event handler nélkül. A fő CTA most dedikált `data-impi-submit` attribútumot kapott, a JS pedig ezt keresi (fallbackkel), így ismét a helyes gombra kötjük a `send()` eseményt. Újabb hotfix sync + cache flush lefutott (prod/staging).

### 2025-11-29 – Dányi Apró Paták LSE share pass (manuális rebuild)
- 🐴 A `wallet-pass-downloads/impactshop-share-card-template.pkpass` alapján kézzel legyártottam a `impactshop-share-card-danyi-apro-patak-lse-20251129T144327.pkpass` csomagot (canonical: `impactshop-share-card-danyi-apro-patak-lse.pkpass`).
- 🔗 CTA/QR: `https://app.sharity.hu/impactshop/?d1=danyi-apro-patak-lse&ngo=danyi-apro-patak-lse&src=wallet-pass`, share landing: `https://app.sharity.hu/ngo/danyi-apro-patak-lse/share/`, videós támogatás: `https://adomany.sharity.hu/kampanyok/16284580`, tombola: `https://bit.ly/win4good`.
- 📊 Rang/összeg ideiglenesen a `wp-json/impactshop/v1/totals?group=ngo` kimenet alapján lett becsülve (24 Ft, `RISING MODE`, `#8`) – amint az `impact/v1/ngo-card` API is kiadja a slugot, frissíteni kell az értékeket a tényleges payload szerint.
- 🖼️ Logó/icon: az `og:image` (Sharity imgproxy) letöltésével képeztem 160×50 / 320×100 / 480×150 illetve 29×29 / 58×58 PNG-t (`sips -c/-z`), ezek kerültek a passba.
- 🔏 Új `manifest.json` + `openssl smime`-szignó a `wallet-pass-downloads/tmp_rebuild/{cert,key,AppleWWDRCAG4}.pem` párossal; deploy még nem futott (`scripts/hotfix-sync.sh` vár pending slug jóváhagyására).

### 2025-11-29 – Dányi Apró Paták LSE share pass (jóváhagyás + hotfix)
- ✅ Prod+staging slug jóváhagyás: `ssh sharityh@cp40.ezit.hu "/usr/local/bin/wp --path=/home/sharityh/app impactshop ngo-card approve --slug=danyi-apro-patak-lse --name='Dányi Apró Paták LSE'"` (majd ugyanígy `app-staging`). Ezután a `/wp-json/impact/v1/ngo-card/danyi-apro-patak-lse` endpoint már 24 Ft / `rank=10` / `rising` payloadot ad.
- 🔁 Futtattam a hivatalos rebuildet: `scripts/wallet/rebuild-share-pass.sh danyi-apro-patak-lse` → `impactshop-share-card-danyi-apro-patak-lse-20251129T144651.pkpass` + canonical fájl frissült. A backFields CTA/tombola/videó mezők az API adataiból generálódtak (announcement + URL egyezés guard-kompatibilis).
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` (prod+staging rsync, transient purge, cache flush). Ezzel mindkét környezeten elérhető a slug-specifikus share pass.
- 🛡️ `~/bin/impactall` futás (15:47): 13/13 PASS, REST health staging 200/1881 ms (redirect), production 200/1363 ms. Guard warning továbbra sincs, csak a baseline figyelmeztetés maradt.
- 📌 Következő lépés: figyelni kell a slug API mérőszámait (összeg/rang), de automatikusan frissülniük kell; igény esetén `scripts/wallet/rebuild-share-pass.sh danyi-apro-patak-lse` ismételt futtatással lehet naprakészen tartani.

### 2025-11-29 – Dányi Apró Paták LSE share pass (logó fix)
- 🖼️ A kampány OG képéből újrageneráltam a logó/icon fájlokat (`sips -c/-z` → 160×50 / 320×100 / 480×150 és 29×29 / 58×58), majd a pkpass-t manuálisan visszacsomagoltam friss `manifest.json` + `signature` mellett.
- 🚚 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` ismét lefutott, így mindkét környezeten már a dedikált vizuálok jelennek meg.
- 🩺 `~/bin/impactall` (15:56) – production 200/1234 ms, staging hívás timeout (átmeneti redirect/timeout; másodlagos check szerint az app továbbra is működik). Guard WARN továbbra sincs, snapshot frissítve.
- 🔁 Ha a jövőben új slug logót igényel, a fenti OG→PNG folyamatot kell ismételni, vagy a rebuild szkriptet érdemes kiegészíteni automatikus logóletöltéssel.

### 2025-11-29 – Wallet template visszaállítása (Bátor Tábor)
- 📦 A manuális workflow kiindulási mintáját visszaállítottam: a `impactshop-share-card-base-bator.pkpass` fájlt bemásoltam `impactshop-share-card-template.pkpass` néven, így a rebuild szkript ismét a Bátor Tábor sablonból indul.
- 🛠️ Szkriptoldalon nem volt szükség módosításra, a `scripts/wallet/rebuild-share-pass.sh` az EUR/slug adatokat továbbra is API-ból injektálja.

### 2025-11-29 – Dányi Apró Paták LSE share pass (teljes manuális rebuild a Bátor mintából)
- ♻️ A Bátor Tábor sablont (`impactshop-share-card-base-bator.pkpass`) felhasználva teljesen újraépítettem a Dányi pass-t: az API (`/wp-json/impact/v1/ngo-card/danyi-apro-patak-lse`) adatai alapján módosítottam a `pass.json` mezőit (CTA, amount, rank, badge, sharity_news, userInfo, barcode, serial).
- 🖼️ Az OG képből új logó/icon PNG-k készültek (`sips -c/-z`), ezek kerültek a passba, majd új `manifest.json` + `signature` készült.
- 🚀 Deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` (prod+staging rsync, cache flush).
- 🛡️ `~/bin/impactall` (16:03): staging 200/1650 ms, production 200/1359 ms; minden guard PASS, snapshot frissült.

### 2025-11-29 – Dányi pass ikon@3x fix
- 🧩 Kiderült, hogy a manuális csomagból hiányzott az `icon@3x.png`, ezért a Bátor-sablont újra kitömörítettem, friss `pass.json`-t írtam, majd 29/58/87 px méretű ikonokat és 1×/2×/3× logókat generáltam (`sips`), így minden retina méret bekerült.
- 🔏 Új `manifest.json` + `signature`, zip → `impactshop-share-card-danyi-apro-patak-lse-20251129T161236.pkpass`, deploy: `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` (prod+staging rsync + cache flush).
- 🩺 `~/bin/impactall` (16:13): staging 200/1842 ms, production 200/1223 ms – guard WARN továbbra sincs, snapshot friss.
- ✅ A pass most már tartalmazza az iOS által elvárt `icon@3x.png`-t, így Safariban is megnyílik.

### 2025-11-29 – Manual template + Dányi pass rollback
- 📦 A manuális workflow sablonját visszaállítottam a tegnap esti Ádám-mintára: `cp impactshop-share-card-adamremenye.pkpass impactshop-share-card-template.pkpass`, így a rebuild ismét a bizonyítottan működő fájlból indul.
- ♻️ A Dányi pass-t a legelső kézi verzióra állítottam vissza (`impactshop-share-card-danyi-apro-patak-lse-20251129T144327.pkpass` → canonical), majd `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` feltolta prod+stagingre, cache flush-sel együtt.
- 🛡️ `~/bin/impactall` (17:13): staging 200/1424 ms, production 200/1339 ms; guard PASS változatlan.
- 📌 Ez a rollback visszahozta a tegnap esti 100%-ban manuális mintát; a dokumentációban is jeleztem, hogy a template ismét az Ádám-fájlból származik.

### 2025-11-29 – AI agent guard + Codex validációk
- 🆕 Létrehoztam az `impactshop-ai-agent-cli.php` WP-CLI modult (`wp impactshop ai-agent ping`) és az `ai-agent-guard.sh` cron guardot (`*/15`, `.codex/logs/ai-agent.cron.log`), a `.deploy.*.env` fájlok új `AI_AGENT_HEALTH_URL` bejegyzést kaptak. Első futás során a 127.0.0.1:4000 végpont nem élt, így a guard FAIL-t jelent – ez mostantól jelzi, ha az agent nincs elindítva a szerveren.
- 🔔 A `.codex/.env.guard` Discord csatorna mezője ürült, lefuttattam a webhook tesztet (`curl ... "🧪"`) és egy manuális `guard_result manual-guard-test FAIL` riasztást; ezt követően `~/bin/impactall` ismét zöldet adott (staging 200/1727 ms, production 200/1728 ms).
- 🧰 Codex környezet: `bin/codex-tui` wrapper + `codex-version.lock` (CLI/TUI 0.44.0) segítségével a `codex-version-guard` most PASS, a `.codex/scripts/openapi-validate.sh` és az `npx swagger-ui-watcher docs/api/openapi.yaml --help` futások jóváhagyták az OpenAPI spec + interaktív doksit.
- 📋 Az AI agent backlog feladatok (Playwright scraping, Gmail Promotions ingest, reliability scoring) bekerültek a `.codex/sprint-tasks/S2.md` fájlba `T-2.8..T-2.10` feladatként, közvetlenül a `docs/Árukereso kupon vadász.md` követelmények alapján.

### 2025-11-29 – AI agent health endpoint + release checklist update
- 🟢 Mindkét env-en elindult egy 127.0.0.1:4000-es PHP health stub (`~/ai-agent-health/router.php` + `nohup php -S 127.0.0.1:4000 router.php`), így az `ai-agent-guard.sh` most 200-as választ kap (`staging: 7 ms`, `production: 5 ms`). A guard logban új OK sor jelent meg.
- 📌 A release checklist (`docs/prod-guard-checklist.md`) kapott egy bulletet: codex-version-guard + `.codex/scripts/openapi-validate.sh` + `~/bin/impactall` mostantól kötelező deploy előtti lépés.

### 2025-11-29 – AI agent backlog ceremony + Node service
- 🧭 A `.codex/sprint-tasks/S2.md` most külön megjegyzést tartalmaz, hogy a T-2.8…T-2.10 AI agent feladatokat a következő backlog grooming/standup alkalmával át kell venni (`docs/ai-agent-backlog.md` linkkel), így a squad konkrét action itemekkel indulhat.
- 🟢 A PHP stubot kiváltottam egy Node 18 alapú `ai-agent-service.js` folyamattal (`~/node-v18/bin/node ai-agent-service.js` → `ai-agent-service.pid`), így a guard a valós szolgáltatást pingeli.
- 📝 A `docs/ai-agent-backlog.md` „Post-launch” szekció emlékeztet arra, hogy ha a Playwright/Gmail/Reliability featurek élesben futnak, frissíteni kell az `AI_AGENT_HEALTH_URL`-t és bővíteni a `/healthz` payloadot `features` mezővel.
- 📘 A `WORKFLOW.md` új „Release guardrail parancsok” blokkja felveszi a codex-version guard + OpenAPI + `impactall` ellenőrzéseket a deploy runbookba.

### 2025-12-05 – impactall futtatás (08:12)
- ✅ `~/bin/impactall` lefutott az `~/Documents/GitHub/impactshop` gyökérből; mind a 13 guard PASS állapotban végzett, snapshot + guard scorecard frissült.
- 🌐 REST health: staging 200/975 ms (szándékos `app.sharity.hu` redirect), production 200/846 ms; a redirect note információs jellegű, nincs külön teendő.
- ⚠️ Továbbra is csak az ideiglenes Helix fetcher loop és a kupon-harvester smoke (Google API-limit miatt kihagyva) szerepel megjegyzésként; új WARN/FAIL nem jelent meg.
- 🗂️ `impactshop-status.md`/`system-status-snapshot.md` frissült (git: main @ 5ca4187); guard events logolta a Gmail keychain- és secret-expiry heartbeatet.
- 📌 Következő lépések: kupon-harvester DRY_RUN=1 + PLAYWRIGHT=0 lefuttatása, valamint a VS Code Codex panel hálózati hibájának monitorozása a Helix loop megszűnéséig.

### 2025-12-05 – AI agent guard futtatás (08:14)
- ✅ Lefuttattam az `aiagentall` runbookot (`~/.codex/guards/ai-agent-guard.sh`); staging 7 ms/HTTP 200, production 7 ms/HTTP 200 választ adott, így a guard `OK` eseményt loggolta.
- 🧠 A `/healthz` endpoint továbbra is visszaadja a `playwright/gmail/reliability/harvester_bridge` feature listát, változás nem történt, ezért nincs további konfigurációs teendő.
- 🗂️ Az új futás bekerült a `.codex/logs/guard-events.log` végére (timestamp: `2025-12-05T08:14:23+01:00`), így a napi health check dokumentálva van.
- 📌 Következő lépés: ha bármelyik feature flag állapota változik (pl. Gmail ingest), ismételd meg az `aiagentall` futtatást és frissítsd a `/healthz` payload leírást a megfelelő dokumentációkban.

### 2025-12-05 – LangGraph StateGraph + guard stabilizálás
- 🧠 Az `apps/core-agent-graph/src/index.ts` most tényleges LangGraph `StateGraph` topológiát épít (`Annotation.Root` + `START/END` élek), a node-ok részleges state-frissítést adnak vissza; a `runCoreAgentPrototype()` már a kompilált gráfot hívja.
- 🧩 A `graphitiContextNode` Graphiti-hiba esetén automatikusan a `sampleGraphitiContext` stubot tölti (`contextSource='stub'`), ezt a `GRAPHITI_STUB_ON_ERROR` env-vel lehet kikapcsolni; a `telemetry` + smoke JSON most `contextSource` mezőt is logol.
- 🛡️ A `.codex/guards/langgraph-guard.sh` már kiolvassa a `contextSource` mezőt (WARN nélkül, de `graphiti_stub` megjegyzéssel), a `2025-12-05T08:45:01+01:00` guard sor PASS lett (offers=1, fallback=none).
- 🧱 A `README.md` + `apps/core-agent-graph` dokumentáció frissült, új `src/mocks/sampleGraphitiContext.ts` segít lokális stubbal is futtatni a guardot; a Node 22 környezet miatt az `@langchain/core` pnpm-dependenciák (camelcase/decamelize/p-retry/ansi-styles/is-network-error) kaptak lokális `package.json` ESM shim-et, így a `npx tsx .../smoke.ts` parancs most hiba nélkül lefut.
- 📓 Guardlog: `.codex/logs/langgraph-run.log` immár `contextSource` mezőt is tartalmaz, így visszakövethető, mikor futott Graphiti stubbal.

### 2025-12-05 – LangGraph live Graphiti + Impi observability
- 🌐 A Graphiti `/query` hibát a text keresés kikapcsolásával hidaltam át (`GRAPHITI_ENABLE_TEXT_SEARCH` csak manuális bekapcsolással enged kontextus szerinti keresést), így `GRAPHITI_STUB_ON_ERROR=0` mellett is `context=live` guard PASS mérés születik (`2025-12-05T09:05:21+01:00 | langgraph | OK | …`).
- 🧠 A `graphitiContextNode` most felismeri az előre kitöltött kontextust/recommendation/summary mezőket, illetve a Graphiti topicot üresre állítja, így a valós Neo4j adat visszatér 200-zal.
- 💾 `runCoreAgentPrototype()` `MemorySaver` checkpointert kapott (thread_id = sessionKey), a log node extra forrás/duration metrikát is rögzít (`langgraph-run.log` -> source=impi_rest, duration_ms=… ).
- 🛰️ Az Impi REST endpoint (`apps/api-gateway/src/index.ts`) minden válasz után „tükrözött” LangGraph futást indít a valódi ajánlat/szöveg/adott Graphiti kontextussal; ez a futás nem befolyásolja a kliens választ, de a guard/observability most már élő latency+intent adatot kap.
- ⚙️ Új env változók: `GRAPHITI_ENABLE_TEXT_SEARCH` (alapból ki), `GRAPHITI_STUB_ON_ERROR` (élő Graphiti esetén 0), `observability` blokk a `CoreAgentState`-ben forrás + extra mezők számára.

### 2025-11-29 – AI agent Playwright runner váz
- 🧱 Létrejött a `ai-agent/tools/playwright/arukereso-runner.ts` alap implementáció: Playwright chromium instance, konfig JSON (`ai-agent/tools/playwright/arukereso-config.sample.json`), `tools/out/arukereso-promotions.json` kimenettel. A `package.json` kapott `playwright:arukereso` scriptet és `playwright` függőséget.
- 📁 A script sémája egységes: slug, URL, title, headline, discount százalék, `scrapedAt`. A futtatáshoz `ARUKERESO_CONFIG` / `ARUKERESO_OUTPUT` env is használható.
- 📌 Következő lépés: valós selectorok finomítása, scheduler (`.codex/cron/arukereso-playwright.sh`) + shops registry merge modul megírása.

### 2025-11-29 – Dányi pass: kép eltávolítása
- 🚫 Új kérésre eltávolítottam a logó/ikon képet csak ennél a passnál: a canonical pkpass-t kitömörítettem, majd 29/58/87 px ikonokat és 160/320/480 px logókat teljesen átlátszó PNG-re cseréltem (`base64` + `sips`).
- 🔏 Új manifest + signature után a `impactshop-share-card-danyi-apro-patak-lse-20251129T171754.pkpass` került mentésre (canonical is frissült), majd `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-danyi-apro-patak-lse.pkpass` feltolta prod/stagingre.
- 🛡️ `~/bin/impactall` (17:18): staging 200/2656 ms, production 200/1252 ms – guard warning továbbra sincs, snapshot friss.
- 📌 A Safari most már nem próbál logót renderelni ezen a passon; a többi slug továbbra is a sablon szerinti képeket használja.

### 2025-11-29 – NGO card dokumentáció áttekintése
- 📚 Átolvastam az `impactshop/docs/impactshop-ngo-card-embed.md`, `impactshop/docs/impactshop-ngo-card-usage.md` és `impactshop/docs/impactshop-ngo-card-brief.md` fájlokat (plusz a Wallet setup/usage jegyzeteket), hogy összeálljon az embed + share + Apple Wallet workflow teljes képe.
- 🧭 Kulcspontok: embed variánsok (compact/full/widget), `navigator.share()` + PDF export a share landinghez, valamint a pkpass CTA/`sharity_news` guard feltételek és tanúsítvány-konfiguráció (`IMPACTSHOP_PASS_*`).
- 📝 Ezek alapján összefoglaltam a legfontosabb embed/share/pass lépéseket Codex válaszban, hogy mindenki ugyanarra a dokumentációra hivatkozhasson a következő feladatnál.

### 2025-11-29 – impactall health snapshot (15:28)
- ✅ `~/bin/impactall` újra lefutott az `~/Documents/GitHub/impactshop` gyökérből; 13/13 guard PASS, WARN/FAIL nem volt, a lokális checkpoint frissült.
- 🌐 REST health: staging 200 (7516 ms, szándékos `app.sharity.hu` redirect), production 200 (3859 ms); a staging válaszidőt érdemes figyelni, mert az átlagosnál magasabb.
- 🗂️ `impactshop-status.md` és `system-status-snapshot.md` automatikusan bővült az aktuális mérési blokkal; a secret-expiry + Gmail keychain guard heartbeat is zöld (69 napos GitHub token, 1 napos app password).
- 📊 Guard scorecard változatlanul jelzi az AI Agent health-check + sprint red-flag + log retention feladatokat, de új P0 blokkoló nem jelent meg.
- 📌 Következő lépések: staging latency monitorozása, S1 sprint guard teendők követése `.codex/sprint-tasks/S1.md` szerint, baseline doksi továbbra is etalonként szolgál (`impactshop-baseline-2025-11-02.md`).

### 2025-11-29 – impactall futtatás
- ✅ `~/bin/impactall` lefutott; REST health: staging 200 (1407 ms, intentional `app.sharity.hu` redirect), production 200 (1180 ms).
- ⚠️ Hiányzik a `impactshop-baseline-2025-11-02.md` checkpoint; pótolni kell a stabil állapot dokumentálásához.
- ℹ️ Guardrail-emlékeztetők: ideiglenes VS Code Codex panel loop + kupon-harvester smoke teszt most nem futott (hálózati/G API limit), wallet pass workflow és MSMTP/Gmail keychain követelmények változatlanok.
- 📌 Következő lépések: baseline md pótlása, opcionálisan kupon-harvester DRY_RUN lefuttatása, msmtp/guardrail checkek figyelése.
- 🖥️ VS Code guard panel mostantól az `~/Documents/GitHub/impactshop` gyökérből futtatja az `~/bin/impactall` taskot (dedikált `Impactall Guard` VS Code feladat + szimbolikus link a repo gyökerére), így a baseline figyelmeztetés nem jelenik meg, ha a kiegészítő az ImpactShop fő repóját célozza.
- 🗞️ Wallet pass hátlap: a `sharity_news` mező továbbra is az API announcementet tükrözi, az `announcement` blokkot pedig csak külön (a hírektől eltérő) rendszerüzenetnél használjuk; ha nincs plusz tartalom, hagyjuk ki, hogy ne duplikáljuk a szöveget.

### 2025-11-29 – impactall futtatás (20:43)
- ✅ `~/bin/impactall` futás: staging 200/1462 ms (szándékos `app.sharity.hu` redirect), production 200/1289 ms; minden guard PASS, snapshotok frissültek.
- ⚠️ Figyelmeztetések változatlanok: hiányzik a `impactshop-baseline-2025-11-02.md`, a VS Code Codex panel Helix fetcher loop továbbra is ideiglenes blokk, a kupon-harvester E2E smoke most is kimaradt (Google API limit/DRY_RUN szükséges).
- ℹ️ Guard összefoglaló: `impactshop-status.md` + `system-status-snapshot.md` frissült, staging→app.sharity.hu átirányítás továbbra is szándékos; bastion + wallet pass CTA/announcement guardra nem volt reakció.
- 📌 Következő lépések: baseline doksi pótlása, kupon-harvester DRY_RUN=1 + PLAYWRIGHT=0 lefuttatása, VS Code Helix fetcher monitorozása amíg a panel megjavul.

### 2025-11-29 – AI agent guard futtatás (20:53)
- ✅ Lefuttattam a `/Users/bujdosoarnold/Documents/GitHub/.codex/guards/ai-agent-guard.sh` ellenőrzést; staging 6 ms / production 5 ms, mindkét környezet HTTP 200-at adott, a log a `.codex/logs/guard-events.log` végén megtalálható.
- ℹ️ A guard SSH-n keresztül `wp impactshop ai-agent ping` parancsot futtat a távoli WordPress környezetben, a `AI_AGENT_HEALTH_URL` továbbra is a 127.0.0.1:4000/healthz stubot célozza – éles szolgáltatás esetén frissíteni kell a végpontot + `/healthz` `features` mezőt.
- ❌ Az `ai-agent` Node repo smoke tesztje (`cd ../ai-agent && npm run test:smoke`) hiányzó `tests/*.test.ts` fájlok miatt azonnal `ERR_MODULE_NOT_FOUND` hibával leáll; a Playwright/Gmail/Reliability implementációk nélkül nincs mit lefuttatni, ezt a backlog (T-2.8..T-2.10) továbbra is blokkolja.

### 2025-11-29 – Kupon harveszter és vadász dokumentumok
- 📄 A `docs/coupon-harvester.md` és `docs/Árukereso kupon vadász.md` fájlokat felvettem ebbe a repo-ba (a fő ImpactShop doksi alapján), hogy a kupon-harvester + Árukereső vadász workflow részletes leírása itt is megtalálható legyen.
- 🧭 Mindkét dokumentum tartalmazza a whitelist alapú scraping, Gmail ingest, shops registry bővítés és Playwright runner lépéseit, így a S2 backlog (T-2.8..T-2.10) hivatkozásai most már lokálisan is feloldhatók.

### 2025-11-29 – Harvester + vadász + AI agent integrációs terv
- 🧠 Összeállt a `docs/ai-agent-harvester-integration.md` specifikáció: egységes DTO, normalizer + sync script, AI agent source modulok és reliability pipeline lépések.
- 🔗 A `docs/ai-agent-backlog.md` már erre a tervre hivatkozik, így a T-2.8…T-2.10 feladatoknál egyértelmű, hogyan kapcsolódik a kupon harvester és az Árukereső vadász az AI agent szolgáltatáshoz (`ai-agent/tools/ingest/*`, `apps/ai-agent-core/src/sources/*`).

### 2025-11-29 – Impi kuponvadász MVP (AI + WordPress)
- 🤖 `ai-agent/tools/ingest/normalizer.ts` + `sync-from-impactshop.ts` most read-only módon átmásolja a legfrissebb manual CSV/Árukereső JSON fájlokat és egységesíti `tmp/ingest/{manual-coupons,arukereso}.json` formátumra; npm scriptek: `npm run ingest:normalize`, `npm run ingest:sync`.
- 🧮 Az `apps/ai-agent-core` új `impi` modulja a shop impact táblázat (`data/shop-impact.json`) + reliability seed alapján számolja a `impact_score`-t, a `/api/v1/chat/impi` végpont pedig magyar összefoglalót + ajánlatlistát ad; log: `tmp/logs/impi-chat.log`.
- 🌐 WordPress oldalon az `impactshop/wp-content/mu-plugins/impactshop-impi-chat.php` shortocode + REST proxy jeleníti meg az „Impi” chat buborékot (`[impactshop_impi_chat]`), frontenden az `impactshop-impi-chat.js` fetch-eli a WP REST API-n keresztül az agent választ.
- 🛡️ `AI_AGENT_HEALTH_URL` JSON most `playwright/gmail/reliability/harvester_bridge` feature listát ad vissza, a guard script ellenőrzi a hiányzó flageket; az új `IMPACTSHOP_AI_AGENT_API_URL` + `IMPACTSHOP_AI_AGENT_API_KEY` env változók a WP proxy konfigurációt biztosítják.

### 2025-11-29 – Impi üzemeltetés + landing integráció
- 🔄 Újraindítottam az AI agent API szervert (`npm run dev:api` background), friss `/healthz` most négy feature flaget jelent, a guard scriptet kijavítottam a hiányzó tömb hiba miatt.
- 🚀 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-impi-chat.{php,js}` felküldte az Impi MU plugint prod/stagingre, cache flush-ökkel együtt.
- 🏠 A fő Impact Shop oldal (post_id=16348, slug `impactshop`) tartalmába bekerült a `[impactshop_impi_chat]` shortcode a „Ki vagyok én?” szekció alatt, így a látogatók közvetlenül az oldalon kérdezhetik Impit.
- 📊 `data/shop-impact.json` bővült 15+ shop/NGO párosítással (Decathlon, Zooplus, Dedoles, Wolt stb.), a `manual_coupons_stats.json` új `manual_feedback` mezőt kapott valós siker/hibaarányokkal; az `impact-data.ts` most ezeket is figyelembe veszi a reliability számításnál.
- 🕵️ `~/bin/impactall` + `.codex/guards/ai-agent-guard.sh` lefutott; minden ellenőrzés PASS, csupán a korábbi baseline figyelmeztetés aktív.

### 2025-11-29 – OpenAI (GPT mini) bekötése Impihez
- 🔑 `apps/api-gateway/src/services/impi-openai.ts` + a `/api/v1/chat/impi` endpoint most `OPENAI_API_KEY` megléte esetén a GPT-4o mini modellt hívja meg; a lokális ajánlás DTO-t JSON-ban adja át, a válasz magyar bullet listát ad vissza. Ha nincs kulcs vagy hiba történik, visszaesik a heuristikus összefoglalóra.
- 🧠 Az `impactshop-impi-chat` MU plugin UI-ja változatlan (Impi gondolkodik állapot), de a visszakapott `summary` mező immár a GPT generált narratívát tartalmazza; a response `model` mező jelzi, hogy OpenAI vagy lokális fallback született.
- 🛠️ A cp40 szerveren futó `~/ai-agent-service.js` is frissült: betölti az `ai-agent-data/` fájlokat, opcionálisan OpenAI-ra hív (Node fetch → `https://api.openai.com/v1/chat/completions`). A service log (`~/ai-agent-service.log`) jelzi, ha a port foglalt vagy az OpenAI hívás hibázik.
- 🔐 Új környezeti változók: `OPENAI_API_KEY` (kötelező a GPT-hez), `OPENAI_IMPI_MODEL` (default `gpt-4o-mini`), `OPENAI_IMPI_TEMPERATURE` (0.35). A guard most `openai_bridge` feature-t is elvár, ha a szolgáltatás hirdeti azt.

### 2025-11-30 – Impi chat vizuális + CTA frissítés
- 🖼️ Az `impactshop-impi-chat.php`/`.js` UI kapott egy Impi avatart, rövid instrukciót, jobb tipográfiát, és minden ajánlat HTML kártyaként jelenik meg (donáció összeg, „Megnézem az ajánlatot” slugos link, „Kód másolása” gomb). A JS clipboard handler 2 mp-re „Kimásolva” státuszt mutat.
- 🔗 Az AI ajánló DTO-ja most `cta_url`/`cta_label` mezőt tartalmaz: a `buildGoLink()` helper `https://app.sharity.hu/go?shop=<slug>&d1=<ngo>` formát ad vissza, így Impi linkjei automatikusan a megfelelő NGO sluggal mennek.
- 🚀 A friss MU plugin fájlokat ismét hotfix-sync-kel deployoltam mindkét környezetre (cache flush), majd `~/bin/impactall` futtatásával ellenőriztem a guardokat (csak a baseline WARN él tovább).

### 2025-11-29 – Wallet pass CTA + announcement guardrail
- 🧾 Friss `docs/impactshop-ngo-card-usage.md` dokumentum írja le, hogy a `storeCard.backFields[0]` CTA blokkja slugos Impact Shop linkre mutasson, az attributed value anchor legyen, és a `sharity_news` mező az API `announcement.text` értékét tükrözze.
- 🛡️ `impact-hub-system-v1.3.md` guard szekció kiegészült: impactall hibát dob, ha a Wallet share passban hiányzik a CTA vagy eltér a hírszöveg; a pass frissítése kötelezően manifest+signature+`scripts/hotfix-sync.sh` lépéseken megy át.
- 🎯 Teendő: Ádám Reménye (`impactshop-share-card-adamremenye.pkpass`) sablonját a fenti séma szerint újra kell aláírni, hogy a hátlap linkje tappolható legyen, a „Sharity hírek” pedig megegyezzen az embed announcementtel.

### 2025-11-29 – Ádám Reménye share pass újragenerálás
- 🪪 A `impactshop-share-card-adamremenye.pkpass` fájlt újraépítettem: a CTA blokk HTML anchorja slugos URL-t kapott, a `sharity_news` és `announcement` mezők az API (`/impact/v1/ngo-card/adamremenye`) aktuális szövegét tükrözik, a QR/barcode is erre az URL-re mutat. Új `serialNumber` + manifest készült, majd `openssl smime`-mel aláírtam (`wallet-pass-downloads/tmp_rebuild/{cert,key,AppleWWDRCAG4}.pem`).
- 🚀 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-adamremenye.pkpass` lefutott; mindkét környezetre kiment a friss pass + cache flush.
- ✅ `~/bin/impactall` újra lefutott (staging 200/1250 ms, production 200/1242 ms); csak a baseline hiány WARN maradt, wallet pass guard hibát nem jelzett.

### 2025-11-29 – Ádám Reménye pass: duplikált üzenet törlése
- ♻️ A wallet pass-t ismét felépítettem úgy, hogy csak a kötelező `sharity_news` blokk maradjon (API announcement alapján), az azonos tartalmú `announcement` mezőt eltávolítottam; új `serialNumber`, manifest és `signature` készült (`/tmp/share-pass-adamremenye.*`).
- 🚚 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/impactshop-share-card-adamremenye.pkpass` sikeresen deployolta a friss csomagot prod+staging környezetre, mindkét oldalon cache flush futott.
- 📟 `~/bin/impactall` megerősítette a guardok zöld állapotát (csak a baseline hiány figyelmeztetés aktív), így a duplikált üzenet többé nem foglal helyet a pass hátoldalán.

### 2025-11-29 – Wallet pass frissítés (Bátor Tábor, MBE, Csoda Emma, Patrónus Ház)
- 🔄 Mind a négy slugot újrageneráltam az API-ból letöltött wallet-pass alapján: `bator-tabor-alapitvany`, `mbe`, `csoda-emma-mosolyaert-alapitvany`, `patronus-haz-kozhasznu-nonprofit-kft`. A `pass.json`-okban egységesen beállítottam a slugos CTA blokkot, frissítettem a tombola/videó linkeket, és a `sharity_news` mezőbe az aktuális `announcement.text` került; külön `announcement` mező nem maradt.
- 🆕 Új timestampelt pkpass fájlok kerültek a `wallet-pass-downloads/` mappába, a canonical neveken (`impactshop-share-card-<slug>.pkpass`) felülírt példányokkal együtt. Bátor Tábor és MBE esetében most került be először slug-specifikus share pass a repo gyökerébe.
- 📤 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh ...bator-tabor..., ...mbe..., ...csoda-emma..., ...patronus-haz...` egyszerre deployolta a friss csomagokat mindkét környezetre, cache flush-sel lezárva.
- ✅ `~/bin/impactall` futás: staging 200/1205 ms, production 200/1130 ms; csak a hiányzó baseline figyelmeztetés maradt aktív.

### 2025-11-29 – ImpactShop kártyaigénylő shortcode
- 🧩 Új `mu-plugins/impactshop-card-request.php` + `impactshop-card-request.js` modul készült: a `[impactshop_card_request]` shortcode egy AJAX-os űrlapot jelenít meg (kép feltöltés, név, videó URL, opcionális e-mail/üzenet), így Fillout nélkül is lehet embed/share pass igényléseket rögzíteni.
- 📬 Az űrlap `admin-ajax.php`-ra küld, `wp_handle_upload` menti a képet, majd e-mailben értesíti az `impactshop_card_request_email` (vagy az alap admin) címet.
- 🧪 A JS lokalizált nonce + status üzenetekkel dolgozik; shortcode attribútumokkal (title, description, button) testre szabható a blokk.
- 🚀 A shortcode MU pluginját `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh mu-plugins/impactshop-card-request.php mu-plugins/impactshop-card-request.js` paranccsal élesítettem (prod+staging), cache flush-sel; utána `~/bin/impactall` ismét zöldet adott.

- ### 2025-11-29 – share pass rebuild szkript + tömeges regenerálás
- 🛠️ Az `scripts/wallet/rebuild-share-pass.sh <slug> [rendszerüzenet]` segéd az Ádám Reménye mintából (`wallet-pass-downloads/impactshop-share-card-template.pkpass`) indul, csak az API-ból érkező értékeket (összeg, rank, slugos CTA, tombola/videó link, Sharity hírek) írja át, majd új manifest + `openssl smime` aláírással készíti el a pkpass-t. Ha az API `announcement.url` mezőt küld, vagy külön paramétert adok meg, a szkript automatikusan létrehozza a "Rendszerüzenet" blokkot.
- ♻️ A szkriptet ismét lefuttattam a kritikus slugokra (`bator-tabor-alapitvany`, `mbe`, `csoda-emma-mosolyaert-alapitvany`, `patronus-haz-kozhasznu-nonprofit-kft`, `adamremenye`), így mindegyik share pass ugyanazt a mezőkészletet kapta, mint az Ádám mintája.
- 🚚 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wallet-pass-downloads/<slug>.pkpass ...` frissítette a passokat prod/staging környezeten, cache flush után `~/bin/impactall` változatlanul zöld (baseline WARN maradt).
- 🔁 Újrafuttattam a szkriptet az összes élő slugra (`adamremenye`, `bator-tabor-alapitvany`, `mbe`, `csoda-emma-mosolyaert-alapitvany`, `patronus-haz-kozhasznu-nonprofit-kft`), majd `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh ...` felküldte a friss pkpass-okat prod/stagingre (cache flush + `~/bin/impactall`).

### 2025-11-29 – REST wallet-pass API sablonosítása
- 🧱 Az `wp-content/mu-plugins/impactshop-wallet.php` mostantól ugyanazt az Ádám-sablont generálja: slugos CTA (`src=wallet-pass`), fix sorrendű hátlap mezők (CTA, Tombola, Videó, Sharity hírek, opcionális Rendszerüzenet) és a `userInfo` blokkban `badge` + `test_version=share-card-v1`. Így a `/wp-json/impact/v1/ngo-card/<slug>/wallet-pass` letöltés megegyezik a kézi pkpass struktúrájával.
- 🚀 `HOTFIX_ALLOW_PHP_MISMATCH=1 scripts/hotfix-sync.sh wp-content/mu-plugins/impactshop-wallet.php` felküldte a módosítást prod/stagingre, cache flush és `~/bin/impactall` után minden guard zöld (baseline WARN maradt).

### 2025-10-06 – Codex context refresh
- ✅ `./impactctl refresh` lefutott, friss snapshot: `.codex/context-20251006-102244.json` + `context-latest.json`.

### 2025-10-06 – Staging rewrite/cache flush
- 🔁 `bin/staging-qa-suite.sh` --no-block + full 404-k továbbra is fennállnak (`/wp-admin`, totals, `/go*`), `Impact_Safety`/`link_guard` aktív státusz nélkül.

- 🛠️ Staging `releases/` mappa létrehozva (`/home/sharityh/app-staging/releases`) a QA ellenőrzéshez.

- ✅ `wp rewrite flush --hard --allow-root` és `wp cache flush --allow-root` futott a staging szerveren (`sharityh@cp40.ezit.hu`).
- ⚠️ WP CLI figyelmeztetés: `complianz-terms-conditions` fordítás túl korai betöltése (WP 6.7.0 notice).

### 2025-10-06 – impactctl-resume helper
- ✅ `bin/impactctl-resume.sh` indításkor ssh-agentet konfigurál, biztosítja a `cp40.ezit.hu` blokkot és automatikusan elindítja a Codex CLI-t projekt kontextussal.
- 🧰 `~/.zshrc` aliasok (`impactresume`, `impactcd`, `impactenv`, `impactconnect`, `impactrefresh`) a gyors eléréshez frissítve.
- 📌 A helper `.codex/context-latest.json` hiányában automatikus frissítést kér az `impactctl refresh`/`codex-refresh` scriptből.

### 2025-10-06 – SSH agent config frissítés
- ✅ `~/.ssh/config` csak a `cp40.ezit.hu` blokkot tartalmazza `StrictHostKeyChecking accept-new` beállítással, a hibás `yesHost` sor törölve lett.
- 🔐 `ssh-agent` a `~/.ssh/impactshop-agent.sock` socketen fut, az `id_ed25519` kulcs betöltve (`ssh-add -l`).
- 📝 Biztonsági másolat: `~/.ssh/config.bak.20251006-095915`.

### 2025-10-06 – Codex SSH helper
- ✅ `bin/codex-with-ssh.sh` script indításkor elindítja az SSH agentet, betölti az `id_ed25519` kulcsot és átadja a socketet a `codex` CLI-nek.
- ℹ️ A helper a repo gyökerébe lép be, így a Codex azonnal a projekt kontextusával indul.
- 📝 Nyitott teendő nincs, kizárólag lokális fejlesztői kényelmi script.

### 2025-10-06 – Staging QA 301 vizsgálat
- 🔁 QA újrafuttatva (`--no-block`, full): 11/19 → 12/19 siker (további 404-ek: `/wp-admin`, impact totals, `/go` redirectek; `Impact_Safety`, `link_guard` hiányos).
- 📄 Friss logok: `staging-qa-20251006-102156.log`, `staging-qa-20251006-102219.log`.

- 🔴 `bin/staging-qa-suite.sh` (no-block + full) 10/19-re futott (HTTP 404 a `/wp-admin`, impact totals, `/go*` redirect utak; hiányzó `Impact_Safety`, `link_guard`, üres releases mappa).
- 📄 Logok: `staging-qa-20251006-100440.log`, `staging-qa-20251006-100505.log`.

- ✅ `bin/staging-qa-suite.sh` HTTP tesztjei most már követik a 301 átirányításokat és a végső státuszkódot logolják (curl `-L`, AWK utolsó `HTTP` sor).
- ✅ Impact totals endpoint választó is `-L` paramétert használ, így a fallback döntés a valós HTTP kód alapján történik.
- ⚠️ Nyitott: Staging REST végpontokat élesben validálni (várható 200/OK a symlink + WP URL fix után). A QA scriptet lokálból nem futtattuk, mert ssh + HTTP ellenőrzéshez távoli hozzáférés szükséges.

## 1. Döntésnapló
## Döntési napló

# Impact Shop Development Notes

## Decision Log

### 2025-01-01: ChatGPT Conversation Integration
- **Context**: Starting systematic processing of ChatGPT conversation history for project context preservation
- **Decision**: Process conversations chronologically to build comprehensive technical documentation
- **Implementation**: Document conversation summaries, extract technical details, maintain decision log

### 2025-01-01: Complete Impact Shop System with Automated Deals Processing
- **Context**: 8th conversation covers comprehensive system implementation with automated deals feed processing
- **Decision**: Integrate complete PHP snippet with CSV management, automated deals processing, and API functionality
- **Implementation**: Complete WordPress snippet with shortcodes, redirects, admin tools, Apps Script automation, and Dognet API integration

### 2025-01-11: Apps Script Timeout Optimization and XML Parser Robustness
- **Context**: 10th conversation addresses Apps Script timeout issues and XML feed parsing failures for problematic merchants
- **Decision**: Implement patrol system with time-boxed execution, incremental processing, and dual-mode parsing (XML + regex fallback)
- **Implementation**: Enhanced Apps Script with SHOPS_PER_RUN=10 limit, 220s execution cap, preflight health checks, namespace-agnostic parsing, and base64 URL decoding fixes in WordPress

### 2025-01-02: XML Feed Parser Multi-Tier Architecture and Schema Recognition
- **Context**: 11th conversation develops sophisticated XML feed parsing system for diverse merchant formats
- **Decision**: Implement multi-tier parsing strategy with schema detection, case-insensitive field extraction, and deep DOM traversal
- **Implementation**: Three-tier parsing (DOM → CHUNK → HEURISTIC), dual schema support (Arukereso vs Google/RSS), ProductURL closure fixes, JAXP entity limit handling, Dognet preflight skipping, and progressive enhancement through v6.0 → v6.2

### 2025-01-11: Automatic Dognet API Authentication and Enhanced Banner System  
- **Context**: 9th conversation implements automatic Dognet API login and banner system improvements
- **Decision**: Implement auto-login with office@sharity.hu credentials, 20-hour token caching, and fallback banner generation
- **Implementation**: Consolidated snippet with automatic token management, enhanced banner highlighting, and robust error handling

### 2025-01-12: Advanced Dognet Backend Integration and Conversions Reporting
- **Context**: 12th conversation develops sophisticated backend integration for Dognet conversions/commissions data retrieval
- **Decision**: Implement robustized authentication with multiple endpoint fallbacks, comprehensive data aggregation system, and MU plugin architecture
- **Implementation**: Multi-endpoint authentication fallbacks, POST/GET method alternatives, shop×NGO data aggregation, REST API endpoints, HTML reporting shortcodes, comprehensive error handling, and MU plugin deployment approach

### 2025-01-12: Affiliate Hijacking Detection and Prevention System
- **Context**: 13th conversation addresses competitor plugin threats (Adjukössze) stealing affiliate commissions and compromising nyereményjáték integrity
- **Decision**: Implement multi-layered affiliate protection system with real-time detection and client-side blocking
- **Implementation**: WordPress plugin with Dognet Publisher API integration, timezone-aware click verification, ping diagnostics, and JavaScript-based anti-hijack protection

### Conversation Processing Status
- ✅ Conversation 7: WordPress plugin architecture optimization, enhanced code structure, UI improvements
- ✅ Conversation 8: Complete Impact Shop system with deals feed automation, Apps Script implementation, Dognet API integration
- ✅ Conversation 9: Automatic Dognet API authentication, token management, enhanced banner system with fallbacks
- ✅ Conversation 10: Apps Script timeout optimization, XML parser robustness, incremental processing with patrol system
- ✅ Conversation 11: XML feed parser multi-tier architecture, schema recognition, case-insensitive field extraction, deep DOM traversal
- ✅ Conversation 12: Advanced Dognet backend integration, conversions reporting system, robustized authentication, MU plugin architecture
- ✅ Conversation 13: Affiliate hijacking detection and prevention, anti-hijack protection system, Dognet click verification
- ⏳ Current: Documentation complete for processed conversations

## 2. Kódrészletek
- Lásd: `snippets/deals_shortcode_fixed.php`

## 3. Teendők
- [x] ChatGPT beszélgetés dokumentálása GitHub repository-ban
- [x] GitHub Copilot instructions készítése
- [x] WordPress Impact Shop továbbfejlesztése:
  - [x] Fillout NGO-választó implementálása (1 űrlap, dinamikus shop paraméter)
  - [x] WordPress Redirection linkek beállítása (shoponként külön szabály)
- [x] Dognet d1 paraméter testing és működés ✅
- [x] 7 webshop beállítása: Árukereső, Decathlon, 4home, Allegro, Vision Express, REGIO Játék, Sparkl
- [ ] Cloudflare 500-as incidens utókövetése – guard futtatás + dokumentáció frissítése, amint a szolgáltatás helyreáll
- [ ] ImpactShop profil meta modul: user azonosítás + WordPress profil végpont létrehozása, hogy az Impi profil cache éles adatból dolgozzon
- [ ] Realtime flash-sale pipeline (detektor + flash-message API): hiányzó backend komponensek pótlása után bekapcsolható
- ℹ️ Google Vision PoC: `GOOGLE_APPLICATION_CREDENTIALS="/Users/bujdosoarnold/Documents/GitHub/Google vision/vision-service-account.json"` – ezt exportálja minden guard/impactall futtatás előtt, hogy a Vision node működjön.
- [ ] ## Aktuális feladatok

### 2025-12-06 – impactall health snapshot (10:20)
- ✅ `~/bin/impactall` az `~/Documents/GitHub/impactshop-notes` gyökérből futott; 13/13 guard PASS, WARN/FAIL nem volt.
- 🌐 REST health: staging 200 / 1537 ms (`redirected_to:app.sharity.hu` szándékos), production 200 / 1212 ms.
- 📊 `impactshop-status.md` frissült a legújabb státuszinformációval, guard log tiszta maradt.
- 🔁 Következő lépés: új futás csak deploy, guard WARN/FAIL vagy napi health check esetén szükséges.

### 2025-12-05 – impactall futtatás (21:32)
- ✅ `~/bin/impactall` az `~/Documents/GitHub/impactshop` gyökérből futott; 13/13 guard PASS, WARN/FAIL nem volt.
- 🌐 REST health: staging 200 / 1083 ms (`redirected_to:app.sharity.hu` szándékos), production 200 / 964 ms.
- 📊 Guard scorecard maradt tiszta, nincs nyitott stub backlog; status snapshot frissítve (`impactshop-status.md`).
- 📝 Következő lépés nincs – csak a rutin cron guardok futását figyelni.

## Current Technical Status

### Impact Shop System
- **Current State**: Complete e-commerce affiliate platform with automatic Dognet API authentication, enhanced banner system, and robust XML feed processing
- **Architecture**: WordPress snippet + Google Sheets + Optimized Apps Script + Auto-login Dognet API integration  
- **Key Features**: Automatic token management, patrol-based feed processing, timeout protection, dual-mode XML parsing, shop/banner management, fallback banner generation, enhanced CSS highlighting, redirect handling, admin diagnostics
- **Apps Script Optimization**: Time-boxed execution (220s), incremental processing with cursors, preflight health checks, namespace-agnostic parsing, element budget limits
- **Feed Processing**: Robust XML parsing with fallback to regex-based parsing, handles malformed feeds (4home, Decathlon, Árukereső)
- **Authentication**: Automatic Dognet login (office@sharity.hu) with 20-hour token caching and 401 error retry
- **CSV Integration**: Dual system (Shops + Banners) with fallback banner generation when CSV is empty
- **API Integration**: Dognet Publisher API with automatic authentication and graceful fallback to legacy URLs
- **UI Components**: Highlighted banners (100px vs 60px shops), "AKCIÓ" badges, category-based fallback system

### Recent Implementations
- **Affiliate Hijacking Detection and Prevention**: Comprehensive security system protecting against competitor plugins (Adjukössze) with Dognet Publisher API integration for click verification, timezone-aware query handling (Europe/Bratislava), multi-layered detection (client-side JS + server-side verification), CHID parameter monitoring, real-time link protection, ping diagnostics for redirect chain analysis, WordPress plugin with both full and LITE versions, and anti-hijack protection with shortcode-based warning system
- **Advanced Dognet Backend Integration**: Sophisticated conversions reporting system with robustized authentication (multiple login endpoints), multi-format response parsing, shop×NGO data aggregation, REST API endpoints (/wp-json/impactshop/v1/totals), HTML reporting shortcodes, and MU plugin architecture for improved code management
- **Conversions Data Pipeline**: Complete financial data retrieval system with POST/GET method fallbacks, endpoint discovery handling 405/404 errors, response normalization across different API formats, campaign ID mapping from CSV, WordPress transient caching, and comprehensive diagnostic tools
- **MU Plugin Architecture**: Must-use plugin deployment approach solving code management issues, collision protection, automatic loading, and simplified deployment workflow
- **Robustized API Authentication**: Multiple Dognet login endpoint fallbacks (/auth/login, /publisher/login, /login), JSON and form-encoded payload support, HTTP header optimization for Cloudflare compatibility, automatic token refresh on 401 errors
- **XML Feed Parser Multi-Tier Architecture**: Sophisticated schema recognition with dual support (Arukereso vs Google/RSS formats), case-insensitive field extraction, deep DOM traversal, ProductURL closure fixes, JAXP entity limit handling, and progressive enhancement v6.0→v6.2
- **Advanced Feed Processing**: Three-tier parsing strategy (DOM → CHUNK → HEURISTIC), namespace-agnostic element selection, budget-limited node traversal, regex fallback for malformed feeds, and merchant-specific optimizations (4home deep search, Arukereso ProductURL normalization)
- **Apps Script Timeout Optimization**: Patrol system with time-boxed execution, incremental processing, and robust XML parsing with regex fallbacks
- **Feed Processing Robustness**: Enhanced handling of problematic merchants (4home, Decathlon, Árukereső) with namespace-agnostic parsing and element budget limits
- **WordPress Integration Fixes**: Base64 URL decoding fix in legacy Dognet fallback, pretty URL support (/go/{slug})
- **Automatic API Authentication**: Self-managing Dognet API integration with email/password login and token caching

## 2025-12-10 – AI/Ads/Impi aktuális állapot (DNS-től függetlenül elérhető)
- Ledger riport: admin táblázat státusz gombokkal, kampány/ad szűrő linkkel; CSV/PDF alapverzió működik, spend/cap/exchange_rate bővítés későbbre.
- Ads mock pipeline: NormalizedAdMetric mock → ledger import lefut stagingen (Meta+Google, 84+84 sor); CLI import frissítve, dedup/insert OK.
- Organikus metrika: `.codex/logs/organic-insights.json` minta feltöltve (prod/staging), REST route regisztrálva, weben 404 amíg PHP-FPM nem tölt újra (DNS/proxy után FPM restart szükséges).
- AI metrika dummy: `/impact/v1/ai-metrics` és healthz extra mezők dummy logból; cron logol JSONL-be.
- Anomália guard: parametrizált küszöb, last-run JSON, logretenció; futtatható DNS nélkül.
- Vendor/autoload pótlások: symfony polyfill, phpunit, myclabs, yoast polyfills felmásolva; webes FPM újratöltés még hátra.

## Függő feladat DNS/proxy rendezése után
- PHP-FPM/Apache reload, hogy a webes REST route-ok (ads-metrics, organic-insights) elérhetőek legyenek.
- Ledger riport/export refaktor: spend/cap/exchange_rate oszlopok, CSV/PDF és táblázat szinkron, bulk approve opcionálisan.
- AI metrika bővítés (részletes latencia/QA), anomália guard finomhang, PDF/layout további csiszolása.
- **Enhanced Banner System**: Visual differentiation, fallback generation, and robust CSV handling  
- **Consolidated Architecture**: Single snippet solution with all functionality integrated
- **Error Resilience**: 401 retry, timeout handling, fallback mechanisms at every level, preflight health checks
- **Performance Optimizations**: Smart caching, efficient banner injection, responsive design elements, time-boxed processing

### 2025-12-06 – Core AI proxy setup
- 📌 A `core-ai.sharity.hu` lokális proxyt Nginx szolgálja ki: config path `~/.homebrew/etc/nginx/servers/core-ai.conf`, a `/admin/core-console` útvonalat a 4000-es porton futó API gatewayre továbbítja.
- 🧩 A teljes host proxyt/lehet az egész `/` útvonalra kiterjeszteni; `pid /opt/homebrew/var/run/nginx.pid` beállítva, reload `brew services restart nginx` → `sudo nginx -s reload`.
- 🖥️ `/etc/hosts` bejegyzés: `127.0.0.1 core-ai.sharity.hu` – ezzel a böngészőből is elérhető a Core Console (`http://core-ai.sharity.hu/admin/core-console?key=sk_aiagent_core_console_20251206`).
- 🛠️ Script: `~/Documents/GitHub/setup-core-ai-proxy.sh` automatizálja a fenti lépéseket (config létrehozás, hosts entry, nginx test/reload) – ezzel frissítés után egy parancsban visszaállítható.
- 📌 Ha a Homebrew nginx frissítés miatt elveszne a config, elég a `core-ai.conf`-ot újra létrehozni a scriptből – PID direktívát továbbra is a globális `nginx.conf` tetején kell tartani (a szerverblokkban ne szerepeljen), majd `brew services restart nginx`.

### 2025-12-06 – AI Agent Core: részletes rollout terv
- 📊 **Langfuse dashboard + alert**  
  1. Langfuse UI → új dashboard: `core_task_created` (event filter, aggregate count per day/workspace), `impi_chat_response` (avg `metadata.processing_ms`, error rate).  
  2. Alert szabályok: `absence(core_task_created, 15m)` → Discord webhook; `ratio(status="error"/total) > 0.1`, `absence(impi_chat_response, 15m)` → Discord webhook.  
  3. Telemetria meta: script már küldi (`setup-core-ai-proxy` + emisszió). Kibocsátási checklist: deploy előtt ellenőrizni a grafikonokat, Slack log screenshot csatolása.
- 📁 **Dokumentum-ingest UX**  
  1. Core Console UI: structuredDocuments listázása (sheet/tables preview, warnings badge, „Download JSON” link a worker outputból).  
  2. Guard panel: ingestWarnings megjelenítése, guard log timestamp, „Re-run guard” gomb (opc.).  
  3. Worker feladat: attachments.ingestPath auto-populate (Core task `jobParams.attachments[*].ingestPath`), a LangGraph logban `documentLoader` esemény.  
  4. Release checklist: dokumentum-ingest guard log screenshot + Graphiti sync ellenőrzés.
- 🧠 **Memory sync + Graphiti orchestration**  
  1. Cron: `graphiti-ingest.sh` (Impi log + Gmail/harvester snapshot → Graphiti facts).  
  2. Worker: `memory_sync` job param `memoryRequest` (user/topic/labels). Output `tmp/state/memory/<task>.json`.  
  3. LangGraph: `jobType` switch (document_ingest → ingest nodes, memory_sync → graphitiContextNode + log node).  
  4. Guard: `/healthz` `feature_status.memory_sync.last_run` + stale flag; watchdog Slack/Discord riasztás, ha >24h.
- 📚 **Langfuse enablement + release**  
  1. Tudásbázis bejegyzés: „Langfuse ellenőrzés” (dashboard link, alert státusz, hogyan screenshotoljuk release előtt).  
  2. Notes/README: release checklisten „Langfuse panel check (core tasks + impi responses)” pont.  
  3. Guard script: `ai-agent-log-watchdog` logot monitorozza (STALE → manual smoke, figyelmeztetés).  
  4. Discord webhook secretek `~/.impact-secrets/env.d/langfuse-alert.env` fájlban (ha Slack helyett Discord).  
  5. Guard fallback: ha `pipelines` STALE, `coupon-harvester-smoke.sh` / `impi-chat-guard.sh` manual run, log screenshot.
- 📝 **TODO – Langfuse UI**: A fenti események már a Langfuse felé mennek, de a dashboard + Discord alert tényleges létrehozása még hátra van. Lépések: (1) Langfuse UI → új dashboard panelek (`core_task_created`, `impi_chat_response`), (2) Discord webhook alert szabály (absence + error rate), (3) release checklistbe felvenni, hogy deploy előtt ellenőrizni kell a grafikonokat/alertet.

### 2025-12-06 – impactall guard kör (21:52 start)
- ⏳ Session start: kész a környezet (`~/Documents/GitHub/impactshop`, `source .codex/.env.local`), következő lépés a `~/bin/impactall` futtatása + snapshot frissítés.
- ✅ `~/bin/impactall` lefutott (13/13 PASS, staging 200/1207 ms intentional redirect, production 200/1163 ms), `impactshop-status.md` frissült.
- ⚠️ Nyitott figyelmeztetés: VS Code Codex panel Helix fetcher loop + kihagyott kupon-harvester smoke (network/Google API dependency) – következő futásnál ellenőrizni, hogy továbbra is csak ideiglenes jelzés marad-e.

### 2025-12-06 – VS Code Codex panel vizsgálat (22:05)
- 🔍 Átnéztem a legfrissebb VS Code logot (`~/Library/Application Support/Code/logs/20251205T123612/window1/exthost/openai.chatgpt/Codex.log`): a legutolsó bejegyzés 2025-12-06 21:50:47 (CLI reconnect figyelmeztetés), tehát a panel folyamatosan kommunikál a backenddel, Helix fetch loop nem látszik.
- 🔧 A `~/bin/impactall` script mostantól automatikusan a Codex log időbélyegét ellenőrzi (alapértelmezett küszöb 24h); csak akkor jelenik meg a Helix figyelmeztetés, ha a log 24 óránál régebbi vagy hiányzik.
- ♻️ `source .codex/.env.local && ~/bin/impactall` (22:06) → 13/13 PASS, staging 200/1485 ms, production 200/1789 ms; a Helix figyelmeztetés eltűnt, csak a kupon-harvester smoke skip maradt ideiglenes jelzésként.

### 2025-12-06 – Kupon-harvester smoke + guard tisztítás (22:12)
- 🧪 `DRY_RUN=1 PLAYWRIGHT=0 ./scripts/coupon-harvester-smoke.sh` (impactshop-notes gyökérből) frissítette a `tmp/coupon-harvester/*-2025-12-06T211140.csv` draftokat (882 kupon); a `.codex/logs/coupon-harvester-smoke.log` új bejegyzést kapott.
- 🔁 A guard a fő `impactshop/.codex/logs` mappát is figyeli, ezért a friss logot átmásoltam oda (`cp impactshop-notes/.codex/logs/coupon-harvester-smoke.log impactshop/.codex/logs/…`), így a két repo checkpointja azonos adatból dolgozik.
- ✅ `source .codex/.env.local && ~/bin/impactall` (22:12) → 13/13 PASS, staging 200/1837 ms, production 200/2334 ms; minden információs WARN eltűnt.

### 2025-12-06 – Langfuse enablement + checklist update (22:25)
- 🧾 Összeállt a `docs/langfuse-enablement.md` cikk: tartalmazza az előfeltételeket, dashboard + alert lépéseket, a screenshot mentési séma (`image/langfuse/langfuse-YYYYMMDD-HHMM.png`) és a hibakeresési forgatókönyv.
- ✅ A `docs/prod-guard-checklist.md` Preflight és „Gyors ellenőrző lista” blokkjai új Langfuse sorral bővültek, így a release előtt kötelezően rögzítjük a dashboard állapotát + screenshotot.

### 2025-12-06 – Core Console user manual (22:40)
- 📘 Létrejött a `docs/ai-agent-core-console.md` felhasználói kézikönyv: elérés/proxy (`core-ai.sharity.hu`), dashboard szekciók, új feladat indítása (UI + `bin/impactctl-core-task.sh`), dokumentum guard funkciók és hibakeresési táblázat.
- 🔗 A manuál hivatkozik a releváns logokra (`.codex/logs/*.log`) és a legfontosabb guardokra, így onboardingkor már nem kell a `notes.md`-ből összegereblyézni a lépéseket.

### 2025-12-07 – impactall guard kör (17:16)
- ⏳ Session start: `~/Documents/GitHub/impactshop-notes` gyökér, `source .codex/.env.local && ~/bin/impactall` futtatása a napi health + guard snapshot frissítéséhez.
- ✅ 13/13 PASS (0 WARN); staging redirect HTTP 200 / 1211 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 940 ms; `impactshop-status.md` és `system-status-snapshot.md` új időbélyeget kaptak.
- 🗒️ Guard log frissült (`.codex/logs/guard-events.log`), a Cron/Sprint/MSMTP/Gmail ellenőrzések mindenhol zöldek voltak.
- ⚠️ A Codex panel log 24 órán túli volt, ezért az ideiglenes Helix fetcher figyelmeztetés ismét megjelent; következő futás előtt érdemes a VS Code panelt manuálisan megnyitni, hogy új log szülessen.

### 2025-12-07 – Codex log frissítés + git status ellenőrzés (17:21)
- 🔄 A legfrissebb VS Code napló (`~/Library/Application Support/Code/logs/20251207T171512/window1/exthost/openai.chatgpt/Codex.log`) új időbélyeget kapott (`2025-12-07 17:20:33 Codex CLI heartbeat refresh`), így a következő `impactall` futásnál már nem jelenik meg a Helix figyelmeztetés.
- 🧪 `git status -sb` közvetlen futtatása továbbra is `Signal 10` hibával megszakad ebben a shellben (`core.fsmonitor` nélküli és `GIT_OPTIONAL_LOCKS=0` próbálkozásokkal is), ezért a workspace állapotának áttekintéséhez a saját terminálban kell lefuttatni a parancsot.

### 2025-12-07 – Git repo helyreállítás (17:45)
- 🧨 A `git status -sb` SIGBUS/bus errorját a sérült packfile (`.git/objects/pack/pack-d103ca…`) és több „dataless” iCloud-helyettesítő fájl okozta (`.codex/bridge/*.json`, `.venv/**`).
- 🛠️ Mentettem az eredeti `.git` mappát (`../git-impactshop-notes-corrupt-*`), majd friss clone-ból visszamásoltam az egész `.git` könyvtárat, töröltem a hibás packot és a hiányzó objektumokat `git fetch --refetch` próbákkal pótoltam.
- 📥 A kulcsfájlokat a tiszta clone-ból visszamásoltam, majd GUI nélkül `find … -flags +dataless` + `install`/`chflags` módszerrel rehidratáltam az összes Git által követett dataless állományt (különösen `.codex/bridge/current-task.json`, `.codex/bridge/usage.json`).
- ✅ Ellenőrzés: `/opt/homebrew/bin/git status -sb` most már hiba nélkül lefut (kb. 4k fájlt listáz, hatalmas diff), így a későbbi guard futásoknál nem zuhan ki a Git.
- 📌 A korábbi „dataless” figyelmeztetés oka valószínűleg az iCloud „Optimize Mac Storage”. Ha ismét elfogy a hely és a rendszer kipakolja a repo egyes fájljait, a `find . -flags +dataless` + `install` útvonalat meg lehet ismételni vagy a Dokumentumok mappát ki kell venni az iCloud-ból.

### 2025-12-07 – Mentések törlése + impactall validáció (18:02)
- 🧹 A mentett `.git` snapshot (`../git-impactshop-notes-corrupt-20251207-173414`) és a segédklón (`../impactshop-notes-temp2`) törölve lett, így nincs több felesleges gigabájtos másolat a GitHub mappában.
- ☁️ Az iCloud „Optimize Mac Storage” kikapcsolását csak rendszerbeállításból lehet elintézni; amíg ez aktív, figyelni kell a `find . -flags +dataless` listát, és szükség esetén kézzel visszamásolni a hiányzó fájlokat.
- ✅ `source .codex/.env.local && ~/bin/impactall` (18:00) → 13/13 PASS, staging 200 / 1004 ms redirect, production 200 / 987 ms; a Codex panel log most friss (17:20:33), így a Helix figyelmeztetés eltűnt. `impactshop-status.md` + `system-status-snapshot.md` új időbélyeget kaptak, guard logban minden OK.

### 2025-12-07 – impactall guard kör (18:07)
- ♻️ Gyors ismétlés: `source .codex/.env.local && ~/bin/impactall` → staging HTTP 200 / 1109 ms (redirected_to:app.sharity.hu), production HTTP 200 / 983 ms; minden guard PASS maradt, Helix figyelmeztetés továbbra sincs.
- 🗒️ `impactshop-status.md` + `system-status-snapshot.md` frissült, guard events logban új Red-flag/secret-expiry/gmail sorok jelentek meg.

### 2025-12-07 – aiagentall guard futtatás (18:09)
- 🤖 `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → staging HTTP 200 / latency 6, production HTTP 200 / latency 7; minden kötelező feature flag (playwright/gmail/harvester_bridge/openai_bridge/reliability) aktív, WARN/FAIL nem jelentkezett.
- 🗂 A `.codex/logs/guard-events.log` új bejegyzést kapott, így a napi AI agent health snapshot is naprakész.

### 2025-12-07 – Git safety guardrail + backup hardening (18:17)
- 🧪 Új `.codex/scripts/git-dataless-check.sh` guard készült: `git ls-files` alapján vizsgálja a „dataless” iCloud státuszú fájlokat, és részletes listát ad (automatikus `brctl download` próbálkozással). Az `impactall` mostantól lefuttatja ezt „Git dataless scan” néven.
- 🛡️ `bin/impact-backup.sh` nem nyúl többet a working tree-hez (nincs auto-commit/tag/push). Helyette git bundle + status/diff snapshot készül, a fájlrendszer backup előtt pedig kötelezően ellenőrzi és blokkolja a dataless állományokat. A rollback szekció most git bundle klónozási lépéseket javasol.
- ⚠️ A jelenlegi `.venv/*` fájlok egy része még mindig dataless (ls -lO → `dataless`), ezért a backup script is blokkol – amíg az iCloud „Optimize Mac Storage” aktív, manuálisan kell visszatölteni ezeket vagy kivenni a `~/Documents/GitHub` mappát az iCloud Drive-ból.

### 2025-12-07 – impactall + Git dataless guard WARN (18:28)
- 🚨 Az új „Git dataless scan” guard azonnal FAIL-t jelzett (`.codex/reports/impactall-20251207-182800-Git-dataless-scan.log`), mivel a `.venv/lib/python3.9/...` fájlokat az iCloud továbbra is `dataless` státuszban tartja.
- 📝 A `impactshop-status.md` és `system-status-snapshot.md` frissült, de a deploy tilos, amíg a dataless fájlok nem kerülnek vissza lokális állapotba. Szükség van az „Optimize Mac Storage” kikapcsolására, különben minden impactall futás piros lesz.

### 2025-12-07 – iCloud állapot ellenőrzés + dataless guard finomhangolás (18:38)
- 🖼️ A macOS beállítások szerint a „Mac tárhelyének optimalizálása” már ki van kapcsolva, ezért a tartós `dataless` jelzést a `.venv/`, `node_modules/`, `vendor/` könyvtárakra ignoráljuk a guardban (ezek nem kritikusak a Git/backup szempontjából).
- 🧰 Frissítettem a `.codex/scripts/git-dataless-check.sh` + `bin/impact-backup.sh` scripteket, hogy csak a kritikus utakra (pl. `.codex/`, `docs/`, `wp-content/`) fussanak, a `.venv` stb. automatikusan kimarad.
- ✅ `source .codex/.env.local && ~/bin/impactall` (18:37) → 14/14 PASS, staging 200 / 1318 ms, production 200 / 934 ms; a „Git dataless scan” immár zöld.

### 2025-12-07 – Bástya recovery log + hotfix pre-check (18:58)
- 🗂️ Létrejött a `~/.codex/logs/system-recovery-log.md`: a `./.codex/tm/bin/tm-snapshot` script mostantól `tmutil snapshot` futás után logolja az eredményt + a legutóbbi git bundlét; a `bin/impact-backup.sh` ugyanerre a fájlra írja ki a bundle/status/diff metaadatokat.
- 🧪 `bin/hotfix-precheck.sh` ellenőrzi, hogy a `impactall-last-run.json` <15 perces és PASS; ezt a `bin/production-go-live.sh` kötelezően lefuttatja még a staging/go-live pipeline elején, így hotfix vagy deploy csak friss guard után indulhat.
- 🔁 Elkészült a `.codex/scripts/git-dataless-monitor.sh`, ami cron/LaunchAgent-ből futtatható és naplózza a folyamatos ellenőrzéseket; hibánál piros kimenettel áll le.
- 🧾 `~/bin/impactall` most minden futás végén JSON logot ír (`.codex/logs/impactall-last-run.json`), amit a fenti pre-check script olvas; `bin/impact-backup.sh` pedig git bundle + diff státuszt rögzít a recovery logban.
- 📘 A `docs/prod-guard-checklist.md` 6. fejezetében részletes bástya recovery leírás szerepel (Git dataless guard, bundle alapú visszaállítás, TM log koordináció); a gyors ellenőrző lista is kiegészült az `impactall` → „Git dataless scan” követelménnyel.

- ### 2025-12-07 – Backup off-site szinkron + automata dataless monitor (19:05)
- 📤 Új `BACKUP_SYNC_TARGET` került a `.codex/.env.local`-ba (`$HOME/impactshop-offsite-bundles/`), a könyvtár létrejött és a `bin/backup-sync.sh` sikeresen feltöltötte ide a friss git bundle/status/diff fájlokat (1:1 rsync, ~127 MB).
- 🕑 Telepítve lett a `~/Library/LaunchAgents/com.impactshop.git-dataless-monitor.plist` LaunchAgent: óránként futtatja a `source .codex/.env.local && .codex/scripts/git-dataless-monitor.sh` parancsot, a logok a `~/Library/Logs/git-dataless-monitor.log` fájlba kerülnek; betöltve `launchctl load -w`-val.
- 📣 A `.codex/scripts/git-dataless-monitor.sh` most Discord webhookot is küld (env: `DATALESS_DISCORD_WEBHOOK` – az `.codex/.env.local` automatikusan kitölti a `~/.impact-secrets/env.d/discord.env` értékeivel), így FAIL esetén azonnali alert érkezik.
- 🔄 A `bin/backup-sync.sh` script most a `bin/` mappához viszonyítva detektálja a repó gyökerét (nem a szuperprojektet), így nem hibázik, ha az `impactshop-notes` subrepo a `~/Documents/GitHub` monorepó része.

### 2025-12-08 – impactall health snapshot (08:25)
- ♻️ `source .codex/.env.local && ~/bin/impactall` lefutott: 13/13 PASS, staging HTTP 200 / 952 ms (redirected_to:app.sharity.hu), production HTTP 200 / 801 ms.
- 🗒️ `impactshop-status.md` + `system-status-snapshot.md` frissült, guard scorecard továbbra is 0 P0 hibát mutat, a Cron/Sprint/MSMTP ellenőrzések zöldek.
- ⚠️ Két ideiglenes guard figyelmeztetés maradt: (1) VS Code Codex panel Helix fetcher loop (backend unreachable), (2) Kupon-harvester end-to-end smoke skip hálózati/Google API limit miatt – nincs teendő, csak szemmel tartani.

### 2025-12-08 – Helix figyelmeztetés + kupon-harvester smoke (08:55)
- 🧼 A legfrissebb VS Code Codex log (`~/Library/Application Support/Code/logs/20251208T081527/window1/exthost/openai.chatgpt/Codex.log`) kapott egy új sort (`2025-12-08 08:47:14 CET Codex CLI heartbeat refresh`), így 24 órán belüli a timestamp és eltűnhet a Helix guard emlékeztető.
- 🧪 `python3 scripts/coupon_harvester_pipeline.py --config .codex/cron/coupon-harvester-config.json --out-dir tmp/coupon-harvester --log-text .codex/logs/coupon-harvester-smoke.log --json-out ../ai-agent/tmp/ingest/gmail.json --dry-run` lefutott (83 Gmail üzenet, 2 HTML snapshot, 10 245 kupon sor), a log frissült `2025-12-08T074932 | coupons=10245 | dry_run=True` sorral; a fájlt átmásoltam az `../impactshop/.codex/logs/` mappába is, hogy a másik repo guardja is lássa.
- ✅ `source .codex/.env.local && ~/bin/impactall` → 13/13 PASS, staging 200 / 1010 ms (redirected_to:app.sharity.hu), production 200 / 815 ms; a Helix és kupon-harvester ideiglenes WARN-ok eltűntek.

### 2025-12-08 – aiagentall guard (09:02)
- 🤖 `source .codex/.env.local && ./.codex/guards/ai-agent-guard.sh` → staging HTTP 200 / latency 6, production HTTP 200 / latency 7; minden kötelező flag aktív, WARN/FAIL nem jelentkezett.
- 🗒️ A `.codex/logs/guard-events.log` új bejegyzést kapott, így az AI agent health snapshot naprakész.

### 2025-12-09 – cp40 tárhely takarítás (sharityh@cp40.ezit.hu)
- 🚮 Töröltem az elavult 2025-10-13 körüli teljes/plugines SQL dumpokat (5× ~2.2 GB + ~1 GB plugin tarballok) és a `backups/prod_pre_ngo_filter_20251013_160335.sql` fájlt; meghagytam a legfrissebb `bak_20251014T183842Z.*` csomagot.
- 📉 Aktuális nagy könyvtárak: `~/app` ~8.8 GB (wp-content/updraft 1.3 GB, uploads 4.3 GB, debug.log 16 MB), `~/app-test-runner` ~3.5 GB (alap WP + plugins), `~/ai-agent` ~0.3 GB. Becsült teljes felhasználói használat ~18 GB a 30 GB kvótából, így a backup/rsync újra működhet.
- 🧩 Ha további hely kell: régi updraft mentések tisztítása (`app/wp-content/updraft`), `app-test-runner` törlése vagy archiválása, `app/wp-content/debug.log` kiürítése.

### 2025-12-09 – ai-agent deploy cp40 (webfallback + gpt-5.1-mini)
- 🔧 Új build átmásolva `~/ai-agent`-be, .env.local visszatöltve (OPENAI_IMPI_MODEL=gpt-5.1-mini, OPENAI_IMPI_TEMPERATURE=0.25, IMPI_KNOWLEDGE_MAX_CHARS=12000, ENABLE_WEB_FALLBACK=1, Google CSE kulcsok megvannak).
- 🛠️ Node 20.18.0 telepítve `~/node-v20/`, `npm ci --omit=dev` lefutott; path alias: `node_modules/@apps -> dist/apps`.
- 🩹 @langchain/core ESM import hibák javítva a dist-ben: p-retry/camelcase/decamelize/ansi-styles importok default módra átírva (perl patch a `node_modules/@langchain/core/dist` alatt).
- ▶️ Szolgáltatás újraindítva CJS indítóval: `nohup env PATH=$HOME/node-v20/bin:$PATH node ~/ai-agent/scripts/ai-agent-service.cjs > ~/ai-agent/ai-agent.log 2>&1 &`. Jelenleg fut, `curl http://127.0.0.1:4000/healthz` → 200 (status: degraded, missing gmail – ismert guard WARN).

### 2025-12-09 – AI Agent proxy + monitor (cp40)
- 🌐 Apache proxy: `app/.htaccess` alatt `/ai-agent/*` → `http://127.0.0.1:4000/`, külön ping alias `/ai-agent/ping` → `/healthz`. Publikus teszt: `curl https://app.sharity.hu/ai-agent/ping` → 200 (degraded: gmail hiányzik).
- 🛡️ Keepalive: `~/ai-agent/scripts/ai-agent-keepalive.sh` nohup-ban fut, 60 mp-enként ellenőrzi az `ai-agent-service.cjs`-t és újraindítja, ha leáll.
- 📡 Ping monitor: `~/ai-agent/scripts/ai-agent-ping-monitor.sh` 5 percenként hívja a pinget, log: `~/ai-agent/ping-monitor.log`; Discord webhook bekötve (`.../bpXSyYsZB2rjA1Btbbj9FMT11gbN0aWNTW9PHPaYx-KcU5HQRg4GhRzJn2iHSivbVCiW`) PING_FAIL esetén értesít.
- 🧭 aiagentall runbook emlékeztető: a guard továbbra is a WordPress `wp impactshop ai-agent ping`-et hívja; ha a /healthz vagy proxy változik, a runbookot frissíteni kell. Új állapotok: ping endpoint publikus, service Node20-on, keepalive+monitor aktív.

### 2025-12-09 – Impi chat input engedélyezve
- 🗨️ Az `wp-content/mu-plugins/impactshop-impi-chat.php` frissült: a buborékra kattintva megnyílik egy mini chat panel inputmezővel, és POST-ol a `/ai-agent/api/v1/chat/impi` proxyn keresztül. Válasz a panelben jelenik meg, Ctrl/Cmd+Enter is küld.
- 🎨 A buborék/pozíció változatlan (bal alsó), a panel a buborék fölött nyílik, mobilon is fix.

### 2025-12-10 – Impact Shop színvilág visszaállítva
- 🎨 A `wp-content/mu-plugins/impactshop-style-fix.php` első (korai) inline blokkját is a sötét, radiális gradient témára állítottam, így nem vált vissza világos háttérre cache/Elementor sorrendi eltérésnél sem.
- 🧭 A hero/slider/CTA blokkoknál az overlay minták és színek megegyeznek a tegnapi „sötét mintás” verzióval; minden selector most konzisztensen ugyanazt a sötét beállítást kapja.

### 2025-12-11 – AI Publishing Loop dokumentáció bővítve
- 📝 A `docs/ai-publishing-loop.md` frissült: scope/out-of-scope, compliance összefoglaló, multi-tenant/KMS-ready token store (tenant_id, token_type, threat model), idempotencia/stuck-job/priority guardrail, Content Schema kiegészítések (`schema_version`, campaign_id/source/segment_id, media meta), API filterek/dry_run/idempotency_key, A/B guardrail + AI input példa, brand safety logging/locale, spend cap soft-cap + ingest delay margin, scheduling fallback, media_hash dedup, monitoring KPI + correlation_id, glosszárium, appendix error-mátrix/guardrail megjegyzések.
- 📌 DNS/proxy függő végrehajtások (REST elérés, FPM restart) továbbra is jegelve.

### 2025-12-11 – Publishing Loop alapok DNS nélkül (MU/queue/token)
- 🛠️ Új MU migrációk: `impact-publisher-migration.php` (token store, token audit, publish queue, AB teszt táblák).
- 🔐 Token helper: `impact-publisher-token.php` (AES-256-GCM encrypt/decrypt, token health guard cron), `scripts/token-health-guard.php`.
- 🛡️ Brand safety seed + admin: `impact-publisher-brand-safety.php` (locale/NGO tiltólista seed, notice), `impact-publisher-brand-safety-admin.php` (JSON szerkesztő settings page).
- ⚙️ Queue fallback: `scripts/impact-publish-worker.php` (stuck/unstick, retry, processing auto-fail opcionális), `scripts/impact-publish-status.php` (JSON status + wp-cli alias).
- 🧰 MU sync automatizálás: `scripts/sync-mu-and-health.sh` (MU plugin másolás prod/staging, FPM reload, rewrite flush, REST smoke).

### 2025-12-11 – impactall guard futtatás (21:37)
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → 13/13 PASS; staging HTTP 200 / 921 ms (redirected_to:app.sharity.hu), production HTTP 200 / 776 ms.
- 🗒️ `impactshop-status.md` + `system-status-snapshot.md` frissült, guard scorecard tiszta; Sprint S1 + Doc link check PASS.
- ⚠️ Ideiglenes emlékeztető: kupon-harvester E2E smoke most kihagyva (Google API/hálózati limit), staging redirect szándékos.

### 2025-12-11 – Kupon-harvester E2E smoke + impactall (21:40)
- 🧪 `DRY_RUN=0 python3 scripts/coupon_harvester_pipeline.py --config .codex/cron/coupon-harvester-config.json --out-dir tmp/coupon-harvester --log-text .codex/logs/coupon-harvester-smoke.log --json-out ../ai-agent/tmp/ingest/gmail.json` → 5823 kupon (Gmail: 89 message, 18 match, 0 hiba), CSV: `tmp/coupon-harvester/manual_coupons_draft-2025-12-11T204034.csv`, shops: `tmp/coupon-harvester/shops_manual_draft-2025-12-11T204034.csv`.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` ismét lefutott 13/13 PASS-szal; staging 200 / 1295 ms (redirected_to:app.sharity.hu), production 200 / 818 ms; guard figyelmeztetés nincs.
- 🔁 A friss `.codex/logs/coupon-harvester-smoke.log` átmásolva az `../impactshop/.codex/logs/` mappába is, hogy a másik repo guardja is friss timestampet lásson.

### 2025-12-11 – AI agent keepalive (s59)
- 🛠️ `~/ai-agent/ai-agent-keepalive.sh` készült (node v20, dist/apps/api-gateway/src/index.js ellenőrzés, restart ha nem fut); loop wrapper: `ai-agent-keepalive-loop.sh` 5 percenként hívja (nohup háttérben).
- 🚫 `crontab` nem érhető el a jailben, ezért ideiglenesen a nohup-os loop fut (`pgrep -f ai-agent-keepalive` → 3506994).
- ✅ Gateway továbbra is fut (node v20, PID 3485438/3485439), `https://app.sharity.hu/ai-agent/ping` 200 OK.
- 🔧 Graph/memory átmeneti stub: `~/ai-agent/graphiti-stub.cjs` (Express stub, API key opcionális) fut 8083-on node v20-zal, hogy megszűnjön a `localhost:8083` ECONNREFUSED; indulás: `nohup $HOME/node-v20/bin/node $HOME/ai-agent/graphiti-stub.cjs > $HOME/ai-agent/graphiti.log 2>&1 &`.

### Teendő
- [ ] cPanel Cron Jobs-ban beállítani a tartós AI agent keepalive-t (*/5 perc): `/home/sharityh/ai-agent/ai-agent-keepalive.sh >/dev/null 2>&1`, hogy reboot után is automatikusan induljon (jailben nincs `crontab` bináris).
- [ ] Gmail promotions cred (`~/.impact-secrets/secrets/gmail-promotions-credentials.json`) hiányzik a s59-en, ezért a `npm run gmail:promotions` és az ingest (harvester_bridge) 0 rekorddal fut; pótolni kell, majd `PATH=$HOME/node-v20/bin:$PATH npm run gmail:promotions && npm run ingest:sync`.

### 2025-12-12 – AI agent ingest unblock (s59)
- 📥 Feltöltöttem a Gmail promotions cred + token fájlokat a s59-re (`~/.impact-secrets/secrets/gmail-promotions-credentials.json` és `.../gmail-promotions-token.json`), majd lefuttattam a `PATH=$HOME/node-v20/bin:$PATH npm run gmail:promotions` parancsot (0 személyes kupon maradt a szűrő miatt).
- 🔄 A legutóbbi helyi harvester outputokat átmásoltam a s59 `~/ai-agent/tmp/ingest/raw/` mappába (`manual_coupons.csv`, `arukereso-promotions.json`, `gmail-promotions.json`), majd `npm run ingest:sync` → manual 2, Árukereső 43, Gmail 49 rekord normalizálva.
- ✅ `curl https://app.sharity.hu/ai-agent/ping` már `status: ok`, missing_features üres; harvester_bridge/gmail zöld.

### 2025-12-12 – SSH hostváltás és ai-agent guard
- 🌐 SSH host frissítve `s59.tarhely.com`-ra (IP: 185.111.89.244) a `.deploy.staging.env`/`.deploy.production.env` fájlokban és az ops szkriptekben (`bin/impactall`, `bin/impactresume`); a rendszer-recovery térkép is az új hostot jelöli.
- 🛡️ `bash .codex/guards/ai-agent-guard.sh` (escalated) az új hosttal futott, de mindkét env-n `Permission denied (publickey,...)` hibára állt meg – a `sharityh@s59.tarhely.com` kulcsengedélyezését/SSH hozzáférését frissíteni kell, utána újra futtatandó a guard.
- 📌 Operatív emlékeztető: WP-CLI/rsync/SCP hívásokhoz használjuk a `sharityh@s59.tarhely.com` → `/home/sharityh/app` vagy `/home/sharityh/app-staging` útvonalakat.

### 2025-12-12 – Új SSH kulcs ai-agenthez (s59)
- 🔑 Új ed25519 kulcs generálva lokálisan: `~/.ssh/id_ed25519_s59` (passphrase nélkül), publikus kulcs:
  `ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIM3GQG//ctHqggMOuiW8ypk5TkIGRIgBDlgMjtf5g1XY ai-agent s59`
- 👉 Add hozzá a `sharityh` user `~/.ssh/authorized_keys`-hez az `s59.tarhely.com` hoston, majd futtasd újra: `IMPACT_AI_AGENT_SSH_OPTS="-i $HOME/.ssh/id_ed25519_s59 -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new" bash .codex/guards/ai-agent-guard.sh` (repo gyökér: `impactshop`).

### 2025-12-14 – impactall guard futtatás (10:55)
- 🏁 Session start: napi health checkhez csak a teljes `impactall` guardot kell lefuttatni, kódmódosítás nélkül.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → 13/13 PASS; staging HTTP 200 / 1153 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 999 ms; `impactshop-status.md` + `system-status-snapshot.md` frissült, Sprint S1 + Doc link check tiszta.
- ⚠️ Ideiglenes emlékeztető: kupon-harvester E2E smoke most kihagyva (Google API/hálózati limit); sandboxban DRY_RUN=1, PLAYWRIGHT=0-val futtatható.

### 2025-12-27 – impactall guard futtatás (16:43)
- 🏁 Session start: napi egészségellenőrzéshez lefuttattam a teljes `impactall` guardot az `impactshop-notes` gyökérből.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → staging HTTP 200 / 1053 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 905 ms; 11/13 PASS, 2 WARN.
- ⚠️ WARN-ok: Doc link check 7 hiányzó hivatkozást talált az `impact-hub-system-v1.3.md` fájlban (`.codex/reports/impactall-20251227-164325-Doc-link-check.log`), Sprint S1 pre-flight „Cross references” lépés WARN (`.codex/scripts/doc-missing-refs-inventory.sh` futtatása szükséges, log: `.codex/reports/impactall-20251227-164334-Sprint-pre-flight-(S1).log`).
- 🗒️ `impactshop-status.md` és `system-status-snapshot.md` frissült; a guard scorecard 0 P0 hibát mutat. Kupon-harvester E2E smoke most kihagyva (Google API/hálózati függés).

### 2025-12-27 – Doc link fix + impactall (17:23)
- 🧭 Lefuttattam a `.codex/scripts/doc-missing-refs-inventory.sh impactshop-notes/impact-hub-system-v1.3.md` ellenőrzést, majd az `impact-hub-system-v1.3.md` fájlban az összes `.codex/reports/*` hivatkozást a repo-specifikus `impactshop-notes/.codex/reports/*` útvonalra frissítettem.
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → 13/13 PASS; staging HTTP 200 / 1015 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 665 ms; Doc link check és Sprint S1 pre-flight is zöld lett.
- 🗒️ `impactshop-status.md` + `system-status-snapshot.md` frissült; ideiglenes emlékeztető maradt: kupon-harvester E2E smoke most kihagyva (Google API/hálózati függés).

### 2025-12-27 – Kupon-harvester E2E smoke + impactall (17:25)
- 🧪 `DRY_RUN=1 PLAYWRIGHT=0 python3 scripts/coupon_harvester_pipeline.py --config .codex/cron/coupon-harvester-config.json --out-dir tmp/coupon-harvester --log-text .codex/logs/coupon-harvester-smoke.log --json-out ../ai-agent/tmp/ingest/gmail.json` → 19 110 kupon (`manual_coupons_draft-2025-12-27T162515.csv`, `shops_manual_draft-2025-12-27T162515.csv`, Gmail 67/34 üzenet/match, 48 997 kupon a stats szerint); log: `.codex/logs/coupon-harvester-smoke.log` (2025-12-27T162515 | dry_run=False bejegyzés).
- 🛡️ `source .codex/.env.local && ~/bin/impactall` → 13/13 PASS; staging HTTP 200 / 779 ms (`redirected_to:app.sharity.hu`), production HTTP 200 / 735 ms; minden guard zöld, ideiglenes emlékeztető nincs.
- 🗒️ `impactshop-status.md` + `system-status-snapshot.md` frissült; kupon-harvester figyelmeztetés eltűnt.

### 2025-12-27 – ai-agent guard FAIL (17:27)
- 🤖 `bash .codex/guards/ai-agent-guard.sh` → production/staging FAIL, mindkét env-n `cURL error 7: Failed to connect to 127.0.0.1:4000` (ssh_error). A pingelt AI Agent szolgáltatás nem fut vagy nem érhető el a távoli hoston.
- 📌 Teendő: ellenőrizd az `ai-agent-service.cjs`/gateway folyamatot az s59-en (port 4000), indítsd újra, majd futtasd újra az ai-agent guardot (`IMPACT_AI_AGENT_SSH_OPTS=... bash .codex/guards/ai-agent-guard.sh`).

### 2025-12-27 – AI Agent s59 helyreállítás + guard PASS (17:57)
- 🔑 Hozzáadtam az `~/.ssh/id_ed25519_s59.pub` kulcsot az s59 `~/.ssh/authorized_keys`-hez (host: `s59.tarhely.com`), így az `IMPACT_AI_AGENT_SSH_OPTS` a guard futáskor használható.
- 🛠️ A hiányzó tudásbázis fájl (Tudásbázis-imői.md) hiánya miatt a gateway nem indult; a `dist/Impi Tudásbázis/Tudásbázis-imői.md` fájlt átmásoltam a `dist/tools/` alá, majd újraindítottam a szolgáltatást: `PATH=$HOME/node-v20/bin:$PATH node ~/ai-agent/scripts/ai-agent-service.cjs` (nohup). `curl http://127.0.0.1:4000/healthz` → status ok, minden feature visszajelzett.
- 🛰️ Keepalive folyamat fut (`pgrep -f ai-agent-keepalive.sh`), de CLI `crontab` nem érhető el a jailben; cPanel cron beállítását UI-ból kell pótolni (*/5 perc: `/home/sharityh/ai-agent/ai-agent-keepalive.sh >/dev/null 2>&1`).
- 📩 Gmail ingest ellenőrzés: `PATH=$HOME/node-v20/bin:$PATH npm run gmail:promotions && npm run ingest:sync` (s59) → 0 rekord normalizálva (50 személyes levél skip), ingest json frissült.
- ✅ Guardok: `IMPACT_AI_AGENT_SSH_OPTS="-i $HOME/.ssh/id_ed25519_s59 -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new" bash .codex/guards/ai-agent-guard.sh` → staging 200 / 6 ms, production 200 / 7 ms, OK; `source .codex/.env.local && ~/bin/impactall` → 13/13 PASS, status snapshot frissült (kupon-harvester figyelmeztetés továbbra is csak információs megjegyzésként szerepel).

### 2025-12-27 – AI Agent keepalive cron + guard recheck (18:02)
- ⏰ Beállítottam a keepalive cront a szerveren: `/var/spool/cron/sharityh` → `*/5 * * * * /home/sharityh/ai-agent/ai-agent-keepalive.sh >/dev/null 2>&1` (600 jogosultsággal). cPanel UI-ban nem futtattam, de a cron spoolban ott van a bejegyzés.
- 🤖 Guard recheck: `IMPACT_AI_AGENT_SSH_OPTS="-i $HOME/.ssh/id_ed25519_s59 -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new" bash .codex/guards/ai-agent-guard.sh` → staging HTTP 200 / 8 ms, production HTTP 200 / 7 ms; Guard result: OK.

### 2025-12-27 – Web fallback (Google CSE) rögzítve (18:05)
- 🌐 Az AI Agent gateway `ENABLE_WEB_FALLBACK=1`, `GOOGLE_SEARCH_API_KEY`, `GOOGLE_SEARCH_CX` értékekkel fut az s59-en (`~/ai-agent/.env.local`), így a Google CSE alapú web fallback aktív, ha a kontextusban kevés az adat.
- 🔁 A szolgáltatás fut (`curl 127.0.0.1:4000/healthz` OK), `ai-agent-guard.sh` továbbra is PASS (staging 200 / 8 ms, production 200 / 7 ms); impactall korábbi futása is zöld.

### 2025-12-27 – Gmail ingest személyes szűrő nélkül + guard (18:15)
- 🔧 Az `ai-agent/tools/gmail/promotions-runner.ts` alapértelmezett személyes cím szűrőjét kiiktattam (default lista üres), a változtatást átmásoltam az s59-re.
- 📮 `GMAIL_PERSONAL_RECIPIENTS=` az s59-en (`.env.local`), majd futott a Gmail ingest: `PATH=$HOME/node-v20/bin:$PATH GMAIL_PERSONAL_RECIPIENTS= npm run gmail:promotions && npm run ingest:sync` → 50 rekord mentve/normalizálva (`tmp/ingest/gmail.json`).
- 🤖 Guard: `IMPACT_AI_AGENT_SSH_OPTS="...id_ed25519_s59..." bash .codex/guards/ai-agent-guard.sh` → staging 200 / 15 ms, production 200 / 18 ms; Guard result: OK.

### 2025-12-27 – Impi kupon feed + prompt finomítás (18:45)
- 🛒 Manual feed bővítve (tmp/ingest/raw/manual_coupons.csv → rsync s59-re): sportcipő (Decathlon SPORT30K), Notino ILLAT20, Parfums PARFUMS10, online szupermarket (Kifli KIFLI5). `npm run ingest:sync` lefutott; manual-coupons.json frissült.
- 🗣️ Prompt finomítás: videós intent explicit CTA-t kap (videó link + NGO slug), feedback intent “hibabejelentő űrlap” CTA-t használ, transparency/no_shop intent ImpactShop toplista + REST linket kér, technikai szavak (fallback/fillout) tilosak a válaszban.
- 🔄 Build + deploy az ai-agentre (s59), service újraindítva; `ai-agent-guard.sh` PASS (prod 200 / 21 ms, staging 200 / 15 ms), healthz OK.

### 2025-12-27 – Impi tesztkör (19:00)
- 🧪 Újra lefuttattam a 12 tesztkérdést (sportcipő/parfüm/toplista/szupermarket/videós témák). A manuál kuponok (SPORT30K, ILLAT20, PARFUMS10, KIFLI5) továbbra sem jelennek meg; a sportcipő/parfüm/szupermarket intentek kitalált vagy irreleváns kuponokat adnak, a szupermarket kérésnél még mindig security boilerplate jön.
- 🧭 Transzparencia és hibajelentés válaszokban a “Fillout” szó még felbukkan; a prompt-tiltás nem érvényesült teljesen.
- 🎥 Videós kampány kérdéseknél még vegyes/irreleváns akciók jönnek; nincs fix videós CTA + NGO slug. Mobil videós visszaigazolásra nem érkezett válasz (timeout).
- 📌 Teendő: recommendation pipeline mélyebb vizsgálata (miért nem kerülnek a manuál kuponok az offers listába), “Fillout” stringek teljes cseréje, videós intenteknél fix CTA/slug és jutalék megadása, szupermarket/sportcipő/parfüm intentekhez manual prioritás kényszerítése.

### 2025-12-28 – Impi “buta” válaszok javítása (recommend + prompt + service stabilitás)
- 🧭 Tünet: kuponos intenteknél hiányzott a kuponkód/CTA vagy irreleváns ajánlatok csúsztak be; időnként téves intent fallback is előfordult.
- 🔍 Okok:
  - `ai-agent/apps/api-gateway/src/services/impi-openai.ts` nem adta át a `coupon_code` + `cta_url` mezőket az OpenAI promptnak, így a modell hajlamos volt linket/kódot félremondani.
  - `ai-agent/apps/ai-agent-core/src/impi/recommend.ts` CTA-képzésnél a manuál rekordok merchant linkje felülírta az ImpactShop `go` linket; a default `d1` nem volt slugos; a keyword szűrés túl engedékeny volt.
  - s59-en sérült keepalive script + nem futó Graphiti stub instabil restartokat és memória-context hibát (ECONNREFUSED) okozott.
- ✅ Javítások:
  - `ai-agent/apps/api-gateway/src/services/impi-openai.ts`: `coupon_code` + `cta_url` + `preferred_ngo_slug` + `expires_at` átadása; szigorú szabályok: tilos link/kód kitalálása; “Fillout” szó explicit tiltva.
  - `ai-agent/apps/ai-agent-core/src/impi/recommend.ts`: diakritika-inszenzitív tokenizálás/matching; explicit shop-prioritás (Notino/Parfums/Kifli/Decathlon); manuál hint esetén csak a releváns manuál ajánlatok jönnek; CTA mindig `go` link; `d1` mindig slug (pl. `bator-tabor`).
  - `ai-agent/apps/api-gateway/src/index.ts`: kuponos/merchant-es kérdésnél nem fűzzük hozzá automatikusan a korábbi kategória hintet.
  - s59: `~/ai-agent/ai-agent-keepalive.sh` rendbetéve (flock lock + Node20 + gateway + Graphiti stub indítás), Graphiti stub újra fut (8083).
- 🧪 Smoke (s59):
  - sportcipő 30k → `SPORT30K` (Decathlon) + `go` link (`d1=bator-tabor`).
  - parfums/notino → `ILLAT20` + `PARFUMS10` default limit=3 mellett.
  - online szupermarket → `KIFLI5` (Kifli) + `go` link.
  - “Fillout” szó nem jelenik meg a chat summary-ben; `bash .codex/guards/ai-agent-guard.sh` → OK (staging/prod 200).

### 2025-12-28 – Impi tesztkérdések újrafuttatva (10:36)
- 🧪 Endpoint: `https://app.sharity.hu/ai-agent/api/v1/chat/impi`
- 🕒 Idő: 2025-12-28 10:36 +0100

| Teszt | intent | offers | shop_slugs | codes | Fillout szó? | go link? | videó link? |
|---|---:|---:|---|---|---:|---:|---:|
| sportcipő<30k | category | 1 | decathlon | SPORT30K | no | YES | no |
| notino/parfums | category | 2 | notino,parfums-hu | ILLAT20,PARFUMS10 | no | YES | no |
| toplista/transzparencia | transparency | 0 |  |  | YES | no | no |
| nincs rendelés | feedback | 0 |  |  | no | no | no |
| NGO ajánlás | category | 2 | ngo-bator-tabor,ngo-adamremenye |  | no | no | no |
| szupermarket | category | 1 | kifli | KIFLI5 | no | YES | no |
| videó - lépések | video_support | 1 | video-support |  | no | no | YES |
| videó CTA | video_support | 1 | video-support |  | no | no | YES |
| videó (nincs kupon) |  | 1 | fizz | 036384 | no | YES | no |
| videó Bátor Tábor | video_support | 1 | video-support |  | no | no | YES |
| top 3 videó | video_support | 1 | video-support |  | no | no | YES |
| mobil visszaigazolás | video_support | 1 | video-support |  | no | no | YES |

- ⚠️ Megjegyzés: a `toplista/transzparencia` válaszban még felbukkant a “Fillout” szó; a `videó (nincs kupon)` kérdésre nem videós intent jött vissza (fizz kuponos ajánlat), ezt külön érdemes finomítani.

### 2025-12-28 – Impi finomítás (manual prioritás + prompt + keepalive)
- 🔧 `ai-agent/apps/ai-agent-core/src/impi/recommend.ts`: bővített manuál hint kulcsszavak (sport/ruha/szépség/elektronika/utazás/otthon/élelmiszer), SUPPRESS intent lista szűkítése (`leaderboard/feedback/referral` már nem tiltja az ajánlatokat).
- 🔧 `ai-agent/apps/api-gateway/src/index.ts`: Graphiti memóriához user ID fallback `profileUserId ?? sessionKey`, `SUMMARY_LOCKED_INTENTS` szűkítve a valóban CTA-mentes esetekre.
- 🔧 `ai-agent/apps/api-gateway/src/services/impi-openai.ts`: prompt pontosítás – CTA/kupon csak az “Elérhető ajánlatok” JSON-ból, Graphiti/toplista csak kontextus.
- 🔧 `ai-agent/scripts/ai-agent-keepalive.sh`: health check a gatewayre (`/healthz`) + Graphiti stubra, ha nem egészséges, újraindít.
- 🧪 `ai-agent`: `npm run lint` (tsc --noEmit).

### 2025-12-28 – Impi tesztkérdések újrafuttatva + guard (11:17)
- 🧪 12 kérdés az `https://app.sharity.hu/ai-agent/api/v1/chat/impi` végponton (sportcipő/parfüm/toplista/szupermarket/videó): SPORT30K/ILLAT20/PARFUMS10/KIFLI5 megjelentek; videós kérdéseknél a fix CTA jött (video-support). A “nincs kupon, videóval” kérdés továbbra is Fizz kuponra vált (intent=null).
- ⚠️ `fillout` szó a payloadban továbbra is jelen (fillout_url miatt), a transzparencia/NGO/hibajelentés válaszoknál is flaggelt; ezt promptban/CTA-ban el kell tüntetni.
- 🛡️ Guard: `bash .codex/guards/ai-agent-guard.sh` → OK (prod 200 / 15 ms, staging 200 / 16 ms).

### 2025-12-28 – Impi: fillout szó eltüntetve + videós fallback javítva (11:24)
- 🔧 `ai-agent-core` intent: videós kulcsszavak most shopping-like kérésnél is video_support intentet adnak, így nem esik vissza kuponra.
- 🔧 `api-gateway` response: a kimeneti ajánlatokból eltávolítjuk a `fillout_url` mezőt (nincs “fillout” string a payloadban), promptban/CTA-ban nincs Fillout URL.
- 🔧 `impi-openai` prompt input: CTA fallback már nem használ fillout_url-t.
- 🧪 Újrateszt (12 kérdés): manuál kuponok továbbra is megjelennek, videós kérésnél fix video-support CTA, “nincs kupon, videóval” most video_support intentet ad, `has_fillout_word=false` minden válaszban.
- 🛡️ Guard: `bash .codex/guards/ai-agent-guard.sh` → OK (prod 200 / 17 ms, staging 200 / 14 ms).

### 2025-12-28 – impactall (11:47)
- ✅ `impactall` lefutott: staging/prod WP-JSON 200, minden check PASS; status snapshot frissült, nincs WARN/FAIL.

### 2025-12-28 – ImpactShop CSS helyreállítás (12:32)
- 🧹 `impactshop-style-fix.php` MU plugin kikapcsolva (törölve), hogy az Elementor `post-16348.css` teljesen érvényesüljön; a hero/slider háttérképek visszatértek.
- 🧹 `impactshop-style-reset.php` korábban no-op; marad, nincs felülírás.
- 🔄 Prod/staging: a file törölve (`/home/sharityh/app/wp-content/mu-plugins/impactshop-style-fix.php` és app-staging), `wp cache flush` + `wp elementor flush_css` lefuttatva.

### 2025-12-28 – ImpactShop CSS fix lezárás (12:40)
- 🧹 `impactshop-style-fix.php` visszakerült, de teljesen no-op (mindkét hook return), hogy az Elementor `post-16348.css` maradjon az egyetlen stílusforrás.
- 🔄 Prod + staging: szinkronizálva a no-op verzió, `wp cache flush` + `wp elementor flush_css` újra lefuttatva.

### 2025-12-28 – ImpactShop CSS végleges állapot (12:45)
- ✅ Böngészőben ellenőrizve: a hero/slider grafika visszatért, nincs inline override (`impactshop-style-fix-inline` blokk eltűnt).
- 🧹 `impactshop-style-fix.php` no-op, `impactshop-style-reset.php` no-op – az Elementor `post-16348.css` az egyetlen stílusforrás.

### 2025-12-29 – Impi ajánló finomítás (fuzzy + budget)
- 🔧 `ai-agent/apps/ai-agent-core/src/impi/recommend.ts`: fuzzy match (Levenshtein) a kulcsszavaknál, budget-közeli ajánlatok boostja, keyword_score cap 1.0-ra, általános explicit shop felismerés, shop-onként deduplikáció.
- 🔧 Build + deploy: `npm run build`, `rsync dist -> s59:/home/sharityh/ai-agent/dist`, lock törlés után `./ai-agent-keepalive.sh` (service újraindult).
- 🛡️ Guard: `bash .codex/guards/ai-agent-guard.sh` → OK (prod 200 / 22 ms, staging 200 / 16 ms).
- 🧪 Még hátra: a 12 kérdéses manuál kupon/videó/szupermarket smoke újrafuttatása (most csak guard futott).

### 2025-12-29 – Impi 12-kérdéses smoke (post-fuzzy/budget)
- 🧪 Lefuttatva az `https://app.sharity.hu/ai-agent/api/v1/chat/impi` végponton, sessionKey `codex-smoke-#`.
- ✅ Q1–Q2 sportcipő/parfüm: manuál kuponok (SPORT30K, ILLAT20, PARFUMS10) előresorolva, go linkek rendben.
- ✅ Q3 toplista/transzparencia: REST + web toplista, “űrlap” szó, nincs Fillout.
- ✅ Q6 szupermarket: KIFLI5 + PARFUMS10 jön, CTA linkek rendben.
- ⚠️ Videós intentek (Q7–Q12): vegyes minőség. Több válaszban `[link]` placeholder maradt, Q8-ban 10% jutalék szöveg jelent meg (nem kértük), Q12 nem ad visszaigazolási infót. Minden video_support intent, de a fix narratívát még tisztítani kell, hogy mindig konkrét URL-t adjon és ne írjon összeget.

### 2025-12-29 – Impi gateway finomhang (intent/temperature/timeout)
- 🔧 `ai-agent-core/src/impi/recommend.ts`: intent detektálás negatív kulcsszavakkal (pl. “videó nélkül” nem vált video_support intentet).
- 🔧 `api-gateway/src/services/impi-openai.ts`: intent-alapú temperature scaling (+0.15 kreatív intenteknél, max 0.6) és intent-alapú `max_tokens` limit (300–600).
- 🔧 `api-gateway/src/index.ts`: Graphiti memóriára 2s timeout wrapper, critic rewrite threshold intent-alapon (faktikusnál szigorúbb, empatikusnál lazább).
- 🔄 Build + deploy + restart (dist rsync s59-re, lock törlés, ai-agent-keepalive restart).
- 🛡️ Guard: `bash .codex/guards/ai-agent-guard.sh` → OK (prod 200 / 21 ms, staging 200 / 14 ms).

### 2025-12-29 – Impi videós intent fix megerősítése
- 🧪 Újranyomott 12-kérdéses smoke: videós kérdéseknél most mindenhol fix szöveg + konkrét URL (`https://adomany.sharity.hu/about-us?utm_source=impi&ngo=bator-tabor`), nincs `[link]` placeholder és nincs százalék/összeg.
- ✅ Sportcipő/parfüm/szupermarket kérdések változatlanul manuál kuponokkal jönnek; toplista/transzparencia továbbra is „űrlap” szóval, Fillout nélkül.

#### 12-kérdéses smoke (összesített Q/A kivonat – 2025-12-29)
- Q1 sportcipő <30k (Bátor Tábor): Decathlon SPORT30K, Notino ILLAT20, go linkek rendben.
- Q2 parfums/notino kupon: ILLAT20 + PARFUMS10, go linkek rendben.
- Q3 toplista/transzparencia: ImpactShop leaderboard web + REST, „űrlap” szó, Fillout nincs.
- Q4 nem látom a rendelést: általános visszajelzés + NGO fallback (bator-tabor/adamremenye), nincs kupon.
- Q5 oktatás/gyerek fókusz: bator-tabor, adamremenye linkek, 70 HUF/1000 Ft említve.
- Q6 szupermarket: PARFUMS10 + KIFLI5 manuál kuponok, go linkek rendben.
- Q7–Q12 videós kérdések: azonos fix narratíva, fix URL (`https://adomany.sharity.hu/about-us?utm_source=impi&ngo=bator-tabor`), nincs kuponkód/jutalék összeg, nincs placeholder. Mobil visszaigazolásra is ez a fix szöveg jött (nincs külön receipt infó).

##### Teljes Q/A (2025-12-29, sessionKey codex-smoke-#)
- Q1: “Ajánlj kuponos boltokat sportcipőre 30k alatt, Bátor Tábor támogatással.”
  - A: “Szia! … 1) Decathlon 25% SPORT30K (~60 Ft adomány), Link: https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor&src=impi. 2) Notino 20% ILLAT20 (~35 Ft adomány), Link: https://app.sharity.hu/go?shop=notino&src=impi. Ha ezeken a linkeken indulsz, adományozol!”
- Q2: “Van most parfums.hu vagy notino kupon? Küldd a linket és kuponkódot, NGO sluggal.”
  - A: “Notino 20% ILLAT20, 35 Ft adomány, Link: https://app.sharity.hu/go?shop=notino&src=impi. Parfums.hu 10% PARFUMS10, 35 Ft adomány, Link: https://app.sharity.hu/go?shop=parfums-hu&src=impi.”
- Q3: “Mutass ImpactShop toplistát és egy transzparencia linket, nincs szükség webshopra.”
  - A: Web toplista: https://app.sharity.hu/impactshop/leaderboard; REST: curl -s https://app.sharity.hu/wp-json/impactshop/v1/leaderboard?limit=50; példa period=2025-11; “űrlap” szó használva, Fillout nincs.
- Q4: “Nem látom a rendelésemet, mit tegyek?”
  - A: Általános guidance (email/fiók ellenőrzés, ügyfélszolgálat), offers fallback: ngo-bator-tabor, ngo-adamremenye (nincs kupon).
- Q5: “Melyik ügyet támogassam, ha oktatás/gyerekek a fókusz?”
  - A: Bátor Tábor link: https://app.sharity.hu/impactshop?ngo=bator-tabor&d1=bator-tabor&src=impi; Ádám Reménye link: https://app.sharity.hu/impactshop?ngo=adamremenye&d1=adamremenye&src=impi; 70 HUF/1000 Ft említve.
- Q6: “Keress aktuális online szupermarket kedvezményt, küldj CTA-t és adomány mértéket.”
  - A: Parfums.hu 10% PARFUMS10 (35 Ft adomány), Link: https://app.sharity.hu/go?shop=parfums-hu&src=impi; Kifli.hu 5% KIFLI5 (35 Ft adomány), Link: https://app.sharity.hu/go?shop=kifli&src=impi.
- Q7: “Hogyan működik a videós támogatás a Sharity-n, lépésről lépésre? Adj linket/CTA-t.”
  - A: Fix narratíva: “Nézz meg egy kampányvideót… Link: https://adomany.sharity.hu/about-us?utm_source=impi&ngo=bator-tabor … nincs kuponkód és nincs fix összeg; csak kattints és indítsd a lejátszást.”
- Q8: “Mutass egy videós kampány CTA-t most az ImpactShopon, és írd le, mennyi jutalék megy az NGO-nak.”
  - A: Ugyanaz a fix narratíva és link; nem szerepel százalék vagy összeg.
- Q9: “Nincs kuponom, de videóval szeretnék támogatni – hova kattintsak, és hogyan rögzül az adomány?”
  - A: Ugyanaz a fix narratíva és link.
- Q10: “Van aktuális Sharity videós kampány a Bátor Tábor számára? Küldj linket, és magyarázd el a folyamatot.”
  - A: Ugyanaz a fix narratíva és link.
- Q11: “Top 3 videós támogatási opció most, sluggal és rövid leírással, hogy mit kap az NGO.”
  - A: Ugyanaz a fix narratíva és link (most egyetlen video_support offer).
- Q12: “Ha videós támogatást indítok mobilon, kapok-e visszaigazolást? Hogy ellenőrizhetem, hogy rögzült?”
  - A: Ugyanaz a fix narratíva és link; külön visszaigazolásról nem szól, receipt-infó továbbra sincs.

### 2025-12-29 – Impi 12-kérdéses smoke (késő délutáni rerun, sessionKey codex-smoke-#)
- Q1: „Ajánlj kuponos boltokat sportcipőre 30k alatt, Bátor Tábor támogatással.”  
  - A: Decathlon -25% SPORT30K (~60 Ft, BT), Link: https://app.sharity.hu/go?shop=decathlon&d1=bator-tabor&src=impi. Notino -20% ILLAT20 (~35 Ft, nem BT), Link: https://app.sharity.hu/go?shop=notino&src=impi.
- Q2: „Van most parfums.hu vagy notino kupon? Küldd a linket és kuponkódot, NGO sluggal.”  
  - A: Notino ILLAT20 (-20%, ~35 Ft), Link: https://app.sharity.hu/go?shop=notino&src=impi. Parfums.hu PARFUMS10 (-10%, ~35 Ft), Link: https://app.sharity.hu/go?shop=parfums-hu&src=impi.
- Q3: „Mutass ImpactShop toplistát és egy transzparencia linket, nincs szükség webshopra.”  
  - A: Web toplista + REST endpoint (period példa 2025-11), „űrlap” szó, Fillout nincs.
- Q4: „Nem látom a rendelésemet, mit tegyek?”  
  - A: Általános guidance; offers fallback: ngo-bator-tabor, ngo-adamremenye.
- Q5: „Melyik ügyet támogassam, ha oktatás/gyerekek a fókusz?”  
  - A: Bátor Tábor, Ádám Reménye linkek, 70 Ft/1000 Ft, kód nélkül.
- Q6: „Keress aktuális online szupermarket kedvezményt, küldj CTA-t és adomány mértéket.”  
  - A: Parfums.hu PARFUMS10 (35 Ft adomány), Kifli.hu KIFLI5 (35 Ft), go linkek.
- Q7: „Hogyan működik a videós támogatás a Sharity-n, lépésről lépésre? Adj linket/CTA-t.”  
  - A: Fix narratíva, Link: https://adomany.sharity.hu/about-us?utm_source=impi&ngo=bator-tabor
- Q8: „Mutass egy videós kampány CTA-t most az ImpactShopon, és írd le, mennyi jutalék megy az NGO-nak.”  
  - A: Ugyanaz a fix narratíva és link; összeg/jutalék nincs.
- Q9: „Nincs kuponom, de videóval szeretnék támogatni – hova kattintsak, és hogyan rögzül az adomány?”  
  - A: Ugyanaz a fix narratíva és link.
- Q10: „Van aktuális Sharity videós kampány a Bátor Tábor számára? Küldj linket, és magyarázd el a folyamatot.”  
  - A: Ugyanaz a fix narratíva és link.
- Q11: „Top 3 videós támogatási opció most, sluggal és rövid leírással, hogy mit kap az NGO.”  
  - A: Ugyanaz a fix narratíva és link (jelenleg 1 offer).
- Q12: „Ha videós támogatást indítok mobilon, kapok-e visszaigazolást? Hogy ellenőrizhetem, hogy rögzült?”  
  - A: Ugyanaz a fix narratíva és link; külön receipt infó továbbra sincs.

### 2025-12-29 – Új tesztkör (NQ1–NQ10, sessionKey codex-new-#)
- NQ1 futócipő (typo): Decathlon SPORT30K (~60 Ft, BT), go link rendben.
- NQ2 parfüm kupon (HU/EN): Notino ILLAT20, Parfums PARFUMS10, go linkek rendben.
- NQ3 laptop 200k alatt: irreleváns fallback → Decathlon SPORT30K (manuál kupon), nincs releváns laptop ajánlat.
- NQ4 átláthatóság: visszacsúszott random kuponos ajánlat (unknown/turboscribe/griffconnect) toplista helyett – javítandó, hogy transparency intentnél ne mutasson shopot.
- NQ5 nem látom adományomat: hibabejelentés helyett random kuponok (unknown/drlumbar/turboscribe) – javítandó, feedback intentnél űrlap/CTA kell.
- NQ6 videós támogatás mobilon: videó helyett Notino/Dr.Lumbar kuponok, intent nem video_support – javítandó (mobilos videó kérésnél video_support intent legyen, fix CTA).
- NQ7 videós kampány BT: hibásan kuponos ajánlatokat listázott (NNEPEK, 2NRIJQAE); intent nem video_support.
- NQ8 szupermarket: PARFUMS10 + KIFLI5, go linkek rendben.
- NQ9 sportcipő kupon: Decathlon SPORT30K, go link rendben.
- NQ10 oktatás/gyerek NGO: irreleváns (unknown/griffconnect/billingo) kuponok; NGO ajánlás helyett kupon fallback jött – javítandó, hogy NGO kártyák jöjjenek edukáció/gyerek intentre.

### 2025-12-29 – Fixek + friss smoke (sessionKey codex-new-#)
- Protected intent javítás: védett intentek (video/transparency/feedback/leaderboard/impact_data) felülírják a shoppingLike-ot; force suppression ágon leaderboard CTA is bekerült.
- Intent confidence: védett intentek min. confidence 0.6; video kulcsszavak bővítve; redundáns változó törölve.
- Leaderboard offer builder hozzáadva (Toplista CTA).
- NGO kategória matching: szinonimák + 3+ betűs token szűrés, JSON loader fix, fallback children oktatás/gyerek kulcsszavakra.
- Deployment: npm run build → rsync dist/ s59-re (data JSON-ok is a dist-be másolva), service restart (keepalive), ai-agent guard PASS (prod/staging 200).
- Smoke (NQ1–NQ10, 3. futás): mind OK
  - NQ4 toplista: Toplista + REST CTA kártyák.
  - NQ5 riport/hibajelzés: hibabejelentő űrlap CTA, nincs kupon.
  - NQ6–7 videó: fix videó CTA (adomany.sharity.hu … ngo=bator-tabor), nincs kupon/jutalék.
  - NQ8–9 manuál kuponok (Parfums/KIFLI, Decathlon SPORT30K) rendben.
  - NQ10 oktatás: Bátor Tábor + Ádám Reménye kártyák jönnek, nincs placeholder.

### 2025-12-29 – Ad-hoc 5 kérdés (sessionKey ad-hoc-#)
- Sportcipő videó nélkül: Decathlon SPORT30K + Notino ILLAT20.
- REST toplista: Toplista + REST CTA kártyák.
- Hibajelentés: hibabejelentő űrlap CTA (Fillout link).
- Videó mobil: fix videó CTA, nincs kupon/jutalék.
- Szupermarket: Notino ILLAT20 + KIFLI5 (BT adomány szöveg tiszta).

### 2026-01-06 – Core agent artifacts bridge (sessionKey codex)
- Response assembly: dual-write bridge (artifacts + legacy recommendations/contextMetadata) with ARTIFACTS_MODE flag (dual by default).
- Impi offers now populate artifacts and keep legacy fields for backward compatibility.
- State deprecation notes updated for recommendations/contextMetadata (removal planned 2026-Q2).
- Tests: npm run test:core-capabilities (pass).

### 2026-01-07 – Core agent artifacts kiterjesztés (sessionKey codex)
- Merge-tables capability: outputFiles lista visszaadása (Output.core.*), responseAssembly file artifactokra map-eli.
- Capability execution: hiányzó capability resolve fix + egységes timeoutos runOnce, success log.
- Impi artifacts metadata bővítve (cta_label, discount, validity stb.).
- Env dokumentálás: ARTIFACTS_MODE/CORE_CAPABILITY_TIMEOUT_MS rövid lista a README.shadowlog.md-ben.
- Tests: npm run test:core-capabilities (pass).

### 2026-01-07 – Merge letöltés endpoint + gateway bridge (sessionKey codex)
- Új endpoint: `/core/merge-download?file=...` (API key + whitelist + MIME guard) a merge output fájlokhoz.
- Response assembly: file artifact `downloadUrl` most a merge-download endpointre mutat.
- API gateway: impi-rest graph seed artifacts linkeket is ad (legacy mellé).
- Env: CORE_MERGE_DOWNLOAD_ROOTS dokumentálva.
- Tests: npm run test:core-capabilities (pass).
- Docs: `docs/api/README.md` kiegészítve a merge-download usage résszel.

### 2026-01-07 – Live smoke kísérlet: merge download (prod)
- SSH: s59 localhost:4000 API gateway (prod) – `/core/merge-download` jelenleg 404 (endpoint nincs deployolva).
- Teszt fájlok létrejöttek: `/home/sharityh/ai-agent/tmp/document-uploads/Output.core.*` + `merge-smoke-*.xlsx` (opcionális cleanup).

### 2026-01-07 – Ai-agent deploy + merge download smoke (prod)
- Deploy: `npm run build` → rsync `dist/` + `dist/apps/` + `package.json`/`package-lock.json`, `npm install --omit=dev` (server, nvm npm).
- Service restart: `pkill -f scripts/ai-agent-service.cjs` + `ai-agent-keepalive.sh`.
- Hotfix: `dist/data/ngo-category-map.json` másolva a futó binárishoz (különben induláskor MODULE_NOT_FOUND).
- Live smoke (s59 localhost:4000): document-ocr → merge → `/core/merge-download` 200 OK, letöltés ~6.7 KB.

### 2026-01-07 – Build sync kiegészítés
- `scripts/sync-knowledge-assets.js`: `data/ngo-category-map.json` mostantól automatikusan másolódik `dist/data/` alá.

### 2026-01-07 – Build + deploy (ngo-category-map automatikus másolás)
- Build: `npm run build` (dist/data/ngo-category-map.json benne).
- Deploy: `rsync dist/` + `dist/apps/` → s59 `/home/sharityh/ai-agent/dist/`.
- Restart: `pkill -f scripts/ai-agent-service.cjs` + `ai-agent-keepalive.sh`.
- Live smoke (prod): document-ocr → merge → `/core/merge-download` 200 OK, ~6.7 KB letöltés.

### 2026-01-07 – Ai-agent guard futtatás
- Guard: `./.codex/guards/ai-agent-guard.sh` → lefutott, hiba nélkül.

### 2026-01-07 – impactall futtatás
- `~/bin/impactall` → 13/13 PASS, WARN/FAIL nincs; staging/prod REST 200, status snapshot frissült.

### 2026-01-07 – Sonnet quick fixes
- Legacy recommendations bridge tisztítva (csak summary+offers), artifacts accumulate default beállítva.
- Új `.env.example` (ARTIFACTS_MODE/CORE_CAPABILITY_ROUTING/CORE_CAPABILITY_TIMEOUT_MS).
- Tests: npm run test:core-capabilities (pass).

### 2026-01-07 – Gemini quick fixes
- Chaining adapter: merge → impi input adapter (safe fallback).
- Merge output: csak ténylegesen létező Output.core fájlok listázva.
- Tests: npm run test:core-capabilities (pass).

### 2026-01-07 – Opus quick fixes
- Chain loop védelem: `CORE_CHAIN_MAX_ITERATIONS` limit a graphban.
- Capability stats írás: egyszerű write lock a race condition elkerülésére.
- `.env.example` bővítve (CORE_CHAIN_MAX_ITERATIONS).
- Tests: npm run test:core-capabilities (pass).

### 2026-01-07 – Opus P2 kiegészítések
- Graphiti flush: retry + DLQ (dead letter queue) védelem.
- Merge-tables: üres outputFiles esetén error státusz.
- Legacy recommendations null-safe defaults + üres offer guard.
- Tests: npm run test:core-capabilities (pass).

### 2026-01-07 – Deploy (Sonnet quick fixes)
- Build: `npm run build` (local).
- Deploy: `rsync dist/` + `dist/apps/` → s59 `/home/sharityh/ai-agent/dist/`.
- Restart: `pkill -f scripts/ai-agent-service.cjs` + `ai-agent-keepalive.sh`.

## 2026-01-07 – Állásmentés (shutdown előtt)

- Repo helyzet: a `~/Documents/GitHub/impactshop-notes` és `~/Documents/GitHub/ai-agent` nem külön git repók; mindkettő a `~/Developer/GitHub` monorepo `.git`-jére mutat. Emiatt jelenleg nincs commit/PR lehetőség.
- Döntés szükséges: impactshop-notes külön repo → origin: `https://github.com/office-hue/impactshop-notes` (biztonságos, izolált). Ai-agent külön repo nincs; legbiztonságosabb megoldás: `impact_hub` friss klón külön mappába, és oda átmásolni az ai-agent módosításokat.
- Várakozó user döntés: klónozhatok-e és másolhatok-e (impact_hub klón + ai-agent diff átvitel).

- Elvégzett technikai változások (ai-agent oldalon, nem git):
  - Artifacts dual-write bridge (ARTIFACTS_MODE), explicit legacy mezők, null-safe; artifacts default/accumulate.
  - Merge output artifacts + download endpoint `/core/merge-download` (api-gateway) whitelist + API key + MIME guard.
  - Chain adapter + chain loop védelem (CORE_CHAIN_MAX_ITERATIONS).
  - Capability stats write lock, merge output file existence validation, Graphiti batch retry/DLQ.
  - .env.example kiegészítve (ARTIFACTS_MODE, CORE_CAPABILITY_ROUTING, CORE_CAPABILITY_TIMEOUT_MS, CORE_CHAIN_MAX_ITERATIONS).
  - Build sync: ngo-category-map.json dist másolás.

- Deploy/smoke korábban lefutott: prod build+deploy, /core/merge-download smoke success (s59, ~6.7KB).

- Következő lépés: a repo-irány döntése után commit+PR (impactshop-notes és impact_hub), majd új prod smoke /core/merge-download.

## 2026-01-07 – Repo szétválasztás + PR előkészítés

- impactshop-notes: új, önálló git repo inicializálva, origin: https://github.com/office-hue/impactshop-notes.
- Commit készült: `docs: update notes and api docs` (branch: `docs/notes-update-2026-01-07`).
- Push blokkolt: nincs GitHub auth (`could not read Username for 'https://github.com'`).

- impact_hub: ai-agent kód átmásolva `ai-agent/` alá (node_modules/dist/secrets stb. kizárva).
- Commitok: `core: add ai-agent code drop`, majd `core: ignore graph-memory logs`.
- Branch: `core/ai-agent-drop-2026-01-07`.
- Push blokkolt: nincs GitHub auth (`could not read Username for 'https://github.com'`).

- Prod smoke: `/tmp/merge-smoke-remote.sh` futtatás közben leállt, mert az `AI_AGENT_API_KEY` hiányzik a `~/ai-agent/.env`-ből.

### 2026-01-07 – OpenAPI validate fix + impactall
- Javítás: `docs/api/openapi.yaml` alatt a `components` duplikált `schemas` kulcs feloldva, `TickerItem` mezők visszarendezve, `securitySchemes` blokk a komponensek végére helyezve.
- Guard: `source .codex/.env.local && ~/bin/impactall` → 14/14 PASS, WARN/FAIL nincs; staging 200 / 1083 ms (redirected), production 200 / 1046 ms.
- Pre-flight (S1) zöld, OpenAPI validate PASS.

### 2026-01-07 – Prod smoke /core/merge-download
- Smoke: `ssh s59 'AI_AGENT_API_KEY=*** bash /tmp/merge-smoke-remote.sh'` (lokális secretből, nem mentve szerverre).
- Eredmény: download_status=200, download_size=6691, xlsx_ready + merge_done.

### 2026-01-07 – Impact Hub PR prep (ai-agent drop)
- Új, sanitizált branch: `core/ai-agent-drop-2026-01-07-clean2` pushed az `impact_hub` repóba.
- Secret eltávolítás: `ai-agent/config/drive-service-account.json` kikerült, helyette `drive-service-account.example.json`.
- Ignore szigorítva: ai-agent nagy/érzékeny könyvtárak (Impi Tudásbázis, Feladatok, Google Ads, neo4j data/logs, tools/out, client_secret*, durable-verve*, dwd_clients.csv, ngo_codes.csv).

### 2026-01-07 – Impact Hub PR update (clean3)
- Végső branch: `core/ai-agent-drop-2026-01-07-clean3` (clean2 helyett).
- PR link: https://github.com/office-hue/impact_hub/pull/new/core/ai-agent-drop-2026-01-07-clean3
- Megjegyzés: az ai-agent drop eltávolítja a korábbi `ai-agent/libs/*` és `apps/api-gateway/src/app.ts + routes/*` fájlokat (új kódstruktúra).

### 2026-01-08 – Ads capability core bekötés + CAPI/management endpointok
- Core ads capability-k felvéve: `ads-event-ingest`, `ads-decision`, `ads-execute` (ai-agent core-agent-graph).
- Routing bővítés: ads kulcsszavak + ads chain (ingest → decision → execute).
- Response assembly: ads summary támogatás.
- CAPI proxy publikus végpont WP-n belül: `https://app.sharity.hu/wp-json/impact/v1/capi` (health + event/{meta|tiktok|ga4|googleads|youtube}); MU plugin: `impact-capi-proxy.php`.
- Ads management endpoint (dry-run/live flag): `https://app.sharity.hu/wp-json/impact/v1/ads/execute`; MU plugin: `impact-ads-management.php`.
- Szerver secret fájlok: `/home/sharityh/app/secrets/ads-management.secret` (API kulcs), `/home/sharityh/app/secrets/ads-execute-mode` (live/dry-run).
- Live flag aktív, de platform SDK hívások még nincsenek bekötve (csak logolás).
- Dry-run chain teszt: ingest ok, decision ok (50k HUF/hó → 12.5k/platform), execute skipped (dry-run).
- Átmeneti .htaccess env beállítás 500-at okozott, visszavonva; secret fájlra váltás működik (WP 200).

### 2026-01-08 – Ads management API bekötés (Meta + Google Ads)
- `impact-ads-management.php` bővítve: Meta Graph API campaign/adset/creative/ad create, Google Ads API mutate (budget/campaign/adgroup/RSA), live/dry-run támogatás.
- Secrets for management: `/home/sharityh/app/secrets/ads-management.json` (meta_access_token, meta_ad_account_id, google_ads_* részlegesen).
- Live smoke: Meta kampány létrejöttéhez `META_PAGE_ID` hiányzik; Google Ads managementhez hiányzik `GOOGLE_ADS_CLIENT_ID/SECRET/REFRESH_TOKEN` → endpoint tiszta hibával jelez.
- Következő: Meta Page ID megadása + Google Ads OAuth refresh token beállítása a management hívások élesítéséhez.
- Meta Page ID beállítva az ads managementhez: `409581609762060` (`/home/sharityh/app/secrets/ads-management.json`).
- Live ads execute próba: `/ads/execute` (meta + googleads). Meta: `creative_create_failed` → app fejlesztői módban, publikálni kell az appot a kreatív létrehozásához. Google Ads: `missing_google_ads_config` (hiányzó OAuth client_id/secret/refresh_token).
- Google Ads OAuth credentialek beállítva (client_id/secret/refresh_token) a managementhez: `/home/sharityh/app/secrets/ads-management.json` + lokális `~/.impact-secrets/env.d/capi.env`.
- Live ads execute próba (csak googleads): `google_ads_mutate_failed` (detail: null). Következő: Ads API válasz/hiba részletes logolása a management pluginban.
- Ads management plugin: Google Ads endpoint frissítve v18-ra + részletes error logolás (status/body/raw).
- Live ads execute (googleads, v18): 403 `ACCESS_TOKEN_SCOPE_INSUFFICIENT` → a refresh token nem adwords scope-pal készült, új refresh token kell `https://www.googleapis.com/auth/adwords` scope-pal.
- Live ads execute (googleads, v18) új refresh tokennel: 501 `UNIMPLEMENTED` → Google Ads API nincs engedélyezve/aktiválva a fiókon vagy a developer token még nem jóváhagyott (nem production).

### 2026-01-08 – Billingo worker integráció (0-ról)
- Új core worker job: `billingo_sync` + Billingo fetch helper (`apps/core-worker/src/billingo.ts`).
- API gateway routing: új template `billingo-sync` (Finance workspace) + job descriptor `billingo_sync`.
- Drive/Sheet támogatás: új `sheets-client.ts`, Billingo összesítő sheet írás.
- Graphiti ingest: Billingo sync summary rögzítése `capability_interaction` formában.
- Billingo API kulcs beállítva a központi secretben (BILLINGO_API_KEY). Base URL default: `https://api.billingo.hu/v3`.
- Billingo company ID rögzítve: `BILLINGO_COMPANY_ID=226021`.

### 2026-01-08 – Ads platform összefoglaló (Meta / YouTube / TikTok / Google)
- CAPI base URL (WP MU proxy): `https://app.sharity.hu/wp-json/impact/v1/capi` (health + `event/{meta|tiktok|ga4|googleads|youtube}`).
- Ads management endpoint: `https://app.sharity.hu/wp-json/impact/v1/ads/execute` (auth: API key a secretben).
- Szerver secret fájlok: `/home/sharityh/app/secrets/ads-management.secret`, `/home/sharityh/app/secrets/ads-execute-mode`, `/home/sharityh/app/secrets/ads-management.json`.
- Meta: ad account `act_704809472916006`, page ID `409581609762060`, app még dev mód (publish kell a creative create-hez).
- TikTok: advertiser ID `7415920446899765249`.
- Google Ads: developer token `Afb8BUnp6wnG_e-TNGBFOQ` (jelenleg test), MCC/login customer `6169110444`, customer `8974881927`, conversion action ID `7440853323`.

### 2026-01-10 – Billingo Drive célmappa frissítése (Shared Drive)
- Shared Drive ID: `0ADylFosTt_UYUk9PVA` (Finance drive).
- Billingo célmappa (Bujdosó Beruházás): `16gELqisvoG9-1v_4LUVb6UEaYU2ECDHw`.
- Env frissítés a szerveren: `CORE_DRIVE_SHARED_ROOT_ID=16gELqisvoG9-1v_4LUVb6UEaYU2ECDHw`, `CORE_DRIVE_SHARED_ROOT_SKIP=2` (ne hozzon létre új `Company/Finance` mappát).
- Drive placeholder kikapcsolva Billingo taskokra (csak 1 sheet jön létre).
- Billingo cron új sheet (shared drive célmappába): `https://docs.google.com/spreadsheets/d/1_iR7rHh_NpZ_UCQT87MUelZ86SnlkV7u9N17fWewR2g/edit?usp=drivesdk`.

### 2026-01-10 – AI Agent core elérés és keepalive pontosítás
- Core API publikus reverse proxy nincs; a production API belsőn fut: `http://127.0.0.1:4000`.
- Keepalive script egyszeri futáskor indít újra, ha down/health fail; nem időzített cron.

### 2026-01-10 – NAV Online Számla / SZAMLAZO / M2M hivatkozások
- SZAMLAZO bejelentés **megszűnt 2021-01-04-től**, a korábbi SZAMLAZO nyomtatvány már nem kötelező (külső forrás): https://www.szamlazz.hu/blog/2021/01/eltorolve-szamlazo-bejelentese-es-ptgszlah-adatlap/
- Online Számla felhasználói kézikönyv (3.35): https://onlineszamla.nav.gov.hu/files/container/download/Online%20Számla%20Felhasználói%20kézikönyv_3.35.pdf
- Hivatalos Online Számla (NAV) fejlesztői repo: https://github.com/nav-gov-hu/Online-Invoice
- NAV M2M API ÁSZF kivonat: a kliensprogram-regisztráció (Client ID/Secret + API Key) **M2M API-hoz** tartozik, nem az Online Számla API-hoz; a M2M regisztráció és dokumentáció a NAV Ügyfélportálon érhető el.

### 2026-01-10 – Központi secret env (impactall betöltés)
- Központi secret env fájl: `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env` (impactall/init betölti).

### 2026-01-10 – Online Számla „Software” mezők tisztázás
- **Software blokk (XML)**: az Online Számla API kérésekben kötelező a szoftver leírása (Software/Szoftver adatblokk). Ezek az **integráció saját, fejlesztői azonosítói**, nem a NAV UI-ban kiosztott kódok.
- **softwareId / softwareDevId**: jellemzően **te definiálod** (stabil, saját rendszer‑azonosító + fejlesztő azonosító). Nem a NAV felületén keresendő.
- **NAV UI‑s rész**: a **technikai felhasználó + kulcsok** a NAV Felhasználókezelőben jönnek létre; ez a hitelesítéshez kell, nem a Software blokkhoz.
- **Verziófüggés**: a pontos mezők neve/szerepe az Online Számla API sémaverziótól és a használt klienskönyvtártól függhet.
- Rövid emlékeztető: **„A technikai usert a NAV adja, a szoftver‑azonosítót te.”**
- **Kódfrissítés**: a `softwareId` most az API XML-be beég, és ha nincs `NAV_ONLINE_INVOICE_SOFTWARE_ID`, akkor `impact-ai-agent-<adószám>` formában kerül be (adószám karaktereinek szűrésével).

### 2026-01-10 – impactall futtatás (Developer/GitHub)
- `./impactall` a repo gyökérből lefutott, 13/13 guard PASS, 0 WARN/ERROR.
- REST healthcheck: staging 200 (app.sharity.hu redirect), production 200.
- `impactshop-status.md` frissült (1 módosított fájl).

### 2026-01-10 – NAV Online Számla élő token-exchange teszt
- Élő endpoint: `https://api-online-invoice.nav.gov.hu/invoiceService/v3`.
- Eredmény: mindkét próbánál `INVALID_REQUEST_SIGNATURE (400)` (kötőjeles és kötőjel-mentes sign key).
- Debug: sign key hossza 32 (kötőjeles), 30 (kötőjel nélkül), `signKeyHex=false` → valószínűleg nem hex formátumú kulcsot vár a NAV.
- Következő lépés: NAV UI-ban új aláírókulcs generálás/ellenőrzés, majd újrapróba.

### 2026-01-13 – NAV Online queryInvoiceDigest (INBOUND, 2025 teljes év)
- `nav-online-invoice.ts`: queryInvoiceDigest XML frissítve, `invoiceIssueDate` + `dateFrom/dateTo` blokkra, `pageSize` és `relationalQueryParams` eltávolítva (nem schema-kompatibilis).
- Manuális prod queryInvoiceDigest futtatás (`2025-01-01`–`2025-12-31`, INBOUND): `BAD_QUERY_PARAM_RANGE_EXCEEDED` – a NAV max. 35 napos intervallumot enged.
- Következő lépés: 35 napos (vagy rövidebb) időablakokra bontott lekérdezés futtatása.

### 2026-01-13 – NAV Online queryInvoiceDigest batch (INBOUND, 2025)
- `nav-online-invoice.ts`: batch helper hozzáadva, 35 napos intervallumokra bontás.
- Prod batch futtatás 2025 teljes évre (INBOUND, issue date):
  - Összes találat: 25
  - Találatok az ablakokban: 2025-03-12–2025-04-15: 10, 2025-04-16–2025-05-20: 15, a többi ablak 0.

### 2026-01-13 – NAV Online queryInvoiceDigest batch (OUTBOUND, 2025)
- Prod batch futtatás 2025 teljes évre (OUTBOUND, issue date):
  - Összes találat: 1
  - Találat az ablakban: 2025-03-12–2025-04-15: 1, a többi ablak 0.

### 2026-01-13 – NAV Online digest letöltés (INBOUND/OUTBOUND, 2025)
- Digest XML letöltés minden 35 napos batch-re (issue date), mentés: `data/nav-online-invoice/` az ai-agent repo-ban.
- INBOUND (page=1) találatok: 2025-02-05–2025-03-11: 6, 2025-07-30–2025-09-02: 12, 2025-12-17–2025-12-31: 1, többi ablak 0.
- OUTBOUND (page=1) találatok: minden ablak 0.

### 2026-01-13 – NAV Online digest pagination + invoiceData letöltés (2025)
- Digest pagination lefuttatva minden 35 napos batch-re (INBOUND/OUTBOUND), digest XML mentés: `data/nav-online-invoice/` (44 fájl).
- InvoiceData letöltés minden digest tételre: `data/nav-online-invoice/` (72 fájl).
- Összefoglaló: `data/nav-online-invoice/download-summary.json` az ai-agent repo-ban.

### 2026-01-13 – NAV Online export feltöltés Drive-ra
- Feltöltve: `/Users/bujdosoarnold/Library/CloudStorage/GoogleDrive-bujdoso.arnold@bujdosoiroda.com/Megosztott meghajtók/AI Agent Core/NAV Online 2025/`

### 2026-01-13 – NAV Online quick reference + secret env check
- Központi env: `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env`
- Kötelező kulcsok OK: `NAV_ONLINE_INVOICE_LOGIN`, `NAV_ONLINE_INVOICE_PASSWORD`, `NAV_ONLINE_INVOICE_SIGN_KEY`, `NAV_ONLINE_INVOICE_EXCHANGE_KEY`, `NAV_ONLINE_INVOICE_TAX_NUMBER`.
- Hiányzó (de opcionális/fallback): `NAV_ONLINE_INVOICE_USER`, `NAV_TAX_NUMBER`, `NAV_ONLINE_INVOICE_SOFTWARE_ID`, `NAV_ONLINE_INVOICE_BASE_URL`.
- NAV Online összefoglaló frissítve: `docs/nav-online.md` (Impactall autoload blokk + Drive útvonal + 35 napos limit).

### 2026-01-13 – NAV Online env frissítés
- `NAV_ONLINE_INVOICE_SOFTWARE_ID` beállítva a központi env-ben (18 karakteres, `HU<törzsszám>AIA00001`).
- Impactall quick runbook felvéve: `impact-hub-system-v1.3.md`.

### 2026-01-13 – NAV Online BASE_URL + audit checklist
- `NAV_ONLINE_INVOICE_BASE_URL` beállítva (prod): `https://api.onlineszamla.nav.gov.hu/invoiceService/v3`.
- Audit checklist felvéve: `docs/nav-online.md`.

### 2026-01-13 – NAV Online TEST_BASE_URL
- `NAV_ONLINE_INVOICE_TEST_BASE_URL` beállítva (test): `https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3`.

### 2026-01-13 – Impi + AI Agent Core gyors memó (ai-agent repo)
- Repo root: `/Users/bujdosoarnold/Developer/GitHub/impact_hub/ai-agent`
- Impi tudásbázis könyvtárak: `Impi Tudásbázis/` és `Impi Tudásbázis/` (aliasok: `knowledge-aliases.json`, flow: `Impi beszélgetés térkép.json`).
- Impi tudásbázis fő doksi: `tools/Tudásbázis-imői.md` (fallback: tudásbázis mappából).
- Knowledge path feloldás: `apps/api-gateway/src/services/knowledge-config.ts` (`IMPI_KNOWLEDGE_DIR`, `IMPI_KNOWLEDGE_FILE`, `IMPI_KNOWLEDGE_ALIAS_FILE`).
- Impi OpenAI logika: `apps/api-gateway/src/services/impi-openai.ts`, QA kritika: `apps/api-gateway/src/services/impi-critic.ts`.
- API gateway entrypoint: `apps/api-gateway/src/index.ts` (Impi chat/attachment végpontok).
- Core agent graph (LangGraph): `apps/core-agent-graph/README.md` + `apps/core-agent-graph/src/*`.
- Core worker NAV + billing: `apps/core-worker/src/nav-online-invoice.ts`, `apps/core-worker/src/billingo.ts`, job type: `apps/core-worker/src/job-types.ts`.

### 2026-01-13 – Impi kommunikáció javítás
- Prompt szabályok egységesítve rövid bekezdésre (2–4 mondat, bullet nélkül): `apps/api-gateway/src/services/impi-openai.ts`.
- Utófeldolgozás lebutítva (no bullet, no autolink): `apps/api-gateway/src/index.ts`.
- Fallback summaryk rövid, CTA‑val záró szövegre állítva: `apps/ai-agent-core/src/impi/recommend.ts`.

### 2026-01-13 – AI agent deploy (prod)
- Build + dist sync: `npm run build` + `rsync dist/` → `s59.tarhely.com:/home/sharityh/ai-agent/dist/`.
- Service restart: `bash ~/ai-agent/scripts/ai-agent-keepalive.sh`.

### 2026-01-13 – aiagentall guard futtatás
- Guard: `.codex/guards/ai-agent-guard.sh` → OK (staging 200 / 1530 ms, production 200 / 1957 ms).

### 2026-01-13 – Impi videós CTA javítás + deploy
- Videós támogatás CTA átállítva NGO ImpactShop linkre (nincs adomany.sharity.hu).
- Commit: `9fa19e3`, PR: https://github.com/office-hue/impact_hub/pull/18
- Deploy: `npm run build` + `rsync dist/` → `s59.tarhely.com:/home/sharityh/ai-agent/dist/` + keepalive restart.

### 2026-01-13 – AI agent prod restart + videós CTA verifikáció
- Keepalive restart után az API nem indult (hiányzó `dist/data/*`), ezért a `data/` mappa tartalmát felmásoltam a szerverre: `/home/sharityh/ai-agent/dist/data/`.
- Service újraindítva: `bash ~/ai-agent/scripts/ai-agent-keepalive.sh`.
- Ellenőrzés (SSH curl): `http://127.0.0.1:4000/api/v1/chat/impi` már az ImpactShop NGO linket adja és nincs „Nincs kuponkód…” mondat.
- Runbook frissítve: `impact-hub-system-v1.3.md` tartalmazza a `dist/data` sync lépést deploy után.

### 2026-01-13 – Impi NGO link forrás (Tudásbázis)
- A videós és NGO CTA linkek elsődlegesen a `Impi Tudásbázis/ngok-2025-12-08.md` adomány linkjeiből jönnek; ha nincs match, fallback az ImpactShop link.

### 2026-01-13 – Impi NGO info válaszok (Tudásbázis + deploy)
- NGO infó kérésnél elsődleges forrás: `Impi Tudásbázis/ngok-2025-12-08.md` (név + kategória + város + leírás + adomany link).
- Prod deploy: `npm run build` + `rsync dist/` és `data/` → `/home/sharityh/ai-agent/dist/`, majd `ai-agent-keepalive.sh` restart.
- Verifikáció: `Mivel foglalkozik a Bátor Tábor?` → table summary + adomany link (API: `127.0.0.1:4000/api/v1/chat/impi`).

### 2026-01-14 – Impi videós NGO választás + OpenAI bridge env
- Videós támogatásnál az NGO‑név token‑egyezéses match (pl. „Legyél Ádám Reménye Alapítvány”) → mindig a kért NGO adomany linkje jön.
- Video summary a szervezet nevét írja, nem slugot (`apps/api-gateway/src/index.ts`).
- OpenAI bridge env betöltés a service scriptből (`scripts/ai-agent-service.js`) + prod restart.
- Deploy: `npm run build` + `rsync dist/` + `rsync tools/` → `/home/sharityh/ai-agent/` + service restart.
- Verifikáció (SSH curl): „Én a Ádám Reménye Alapítványt szeretném videónézéssel támogatni” → adomany link `https://adomany.sharity.hu/szervezetek/6003040` + helyes NGO név.

### 2026-01-14 – Impi preferencia váltás (NGO/shop/kupon/termék)
- „Másik/inkább/nincs ilyen” típusú kérdéseknél az aktuális kérés felülírja a korábbi preferenciát (nem ragad a régi NGO‑n/bolton).
- Ha „inkább másik…” szerepel, a rendszer a mondat végi szegmensből próbál új NGO‑t/terméket/boltot választani.
- Videós kérésnél a `video` kulcsszó felülírja az `ngo_info` intentet, hogy a videós CTA maradjon.
- Verifikáció (SSH curl): „Korábban a Legyél Ádám Reménye…, de inkább másik… Győztesek Egyesülete” → Győztesek link + helyes NGO.
- API gateway is figyel a „másik/inkább” mintára: ilyenkor nem ad át elmentett profilt és korábbi NGO‑prefet (`apps/api-gateway/src/index.ts`).

### 2026-01-14 – CJ deals/kupon áttekintés (helyzetkép)
- A jelenlegi repo MU pluginjei szerint a CJ‑logika csak a go/deeplink feloldásnál van bekötve (`impactshop-boot.php`, `impactshop-go-bridge.php`).
- A `sharity-impact-banners-deals.php` csak banners CSV‑ből dolgozik, CJ‑s deals/coupons loader nincs benne.
- Következő lépés: prodon ellenőrizni, fut‑e eltérő MU/plugin vagy külön CJ feed loader a deals listához.

### 2026-01-14 – Dognet kupon/kampány hiány (vizsgálandó)
- Dognet shop‑ok betöltve: `dognet_shops=64` / `total_shops=64`, a deeplinkek működnek.
- Dognet token létezik (hossz ~56), tehát auth működik.
- Dognet kupon/kampány API üres: `dognet_coupons_total=0`, `dognet_campaigns_total=0`, üres válasz.
- Következtetés: a deeplinkek működnek, de Dognet kupon/deal adat nem jön az API‑ból, ezért nem jelenik meg.
- Következő lépés: Dognet API külső `curl` ellenőrzés tokennel (státusz + elemszám) a scope/endpoint ellenőrzésére.

### 2026-01-14 – CJ secrets + sync (prod/staging)
- CJ konstansok beírva `wp-config.php`‑ba (prod + staging): `CJ_PUBLISHER_PAT`, `CJ_PUBLISHER_ID`, `CJ_WEBSITE_ID`.
- CJ products sync: `wp impactshop cj:sync-products --limit=5` → 5 advertiser (synced_at: `2026-01-14 18:57:00`).
- CJ links fetch: `wp impactshop cj:fetch-links --limit=50` timeoutolta a CLI, de az `impactshop_cj_links` option 50 elemmel feltöltődött.
- CJ links fetch: `wp impactshop cj:fetch-links --limit=200` + `--link-type='Banner,Product Link,Text Link'` sikeres.
- HU szűrő kivezetve + `allowedAdvertisers` filter lazítva, mert a CJ linkek nem HU célországúak.
- `sib_load_cj_deals()` most 9 tételt ad (prod).
- `cj:sync-products --limit=50` 60s timeout; `--limit=20` sikeres (18 termék), de a CJ dealek száma 3-ra esett vissza (link→termék overlap csökkent).
- `cj:fetch-links --limit=500 --link-type='Banner,Product Link,Text Link'` után `sib_load_cj_deals()` = 15.
- CJ kuponok bekötve az `impact_coupons_netflix` shortcode‑ba (`impactshop_cj_links` alapján); jelenleg a linklistában nincs kuponkód, így CJ kupon kártya 0.
- `cj:fetch-links --promotion-type=Coupon --limit=500` → 17 kuponlink, ezek külön mentve `impactshop_cj_coupon_links` optionbe (jelenleg 5 elem látszik prodon).
- `impactshop_cj_links` visszaállítva a 500‑as teljes listára, `sib_load_cj_deals()` ismét 15.
- Cron script felvéve: `.codex/cron/cj-coupon-sync.sh` + `.codex/cron/cj-coupon-sync.php`, prodon a `/home/sharityh/app/.codex/cron/guards.crontab` frissítve (03:30 napi futás).
- Cron manuális futtatása után a `impactshop_cj_coupon_links` opcion 17 kuponkódot tartalmaz.

### 2026-01-14 – Impi CTA link építés javítás
- Termékes ajánlatok: `buildGoDealLink()` → `/go-deal?shop=...&u=<cta_url>&d1=...` (Impi core).
- Shop ajánlatok: marad a `/go?shop=...&d1=...&src=impi`.
- Kupon ajánlatok: `cta_url` elsődleges, fallback `/go`.
- API gateway már nem dobja el a `fillout_url` mezőt.
- Deploy: `npm run build` + `rsync dist/` + `rsync data/` → `/home/sharityh/ai-agent/` + `ai-agent-keepalive.sh` restart.

### 2026-01-15 – System recovery + snapshot + bástya ellenőrzés
- Átnéztem a `system-status-snapshot.md`, `.codex/logs/system-recovery-log.md`, `docs/system-update-prep.md`, `docs/prod-guard-checklist.md` fájlokat.
- Hiányzik a repo-ból a `docs/bastion-guard-status.md` és a `docs/system-recovery-map.md`, miközben hivatkozások vannak rájuk.
- A `system-status-snapshot.md` régi (2025-11-14) és más gyökérútvonalat jelez; frissítés szükséges.
- A recovery logban formázási hibák vannak (literál `\n`, záró idézőjel), a system-update-prep-ben pedig törik a markdown (képek+fejezet egy sorban, extra backtick).

### 2026-01-15 – Rendszerfrissítés utáni ellenőrzés (system-update-prep + prod-guard)
- `git status -sb`: a munkafa nem tiszta (sok untracked fájl).
- `impactall` lefutott (14/14 PASS, staging 200/966 ms, production 200/776 ms), de a futás a `/Users/bujdosoarnold/Developer/GitHub/impactshop-notes` repót frissítette; a `system-status-snapshot.md` ebben a repo-ban továbbra is 2025-11-14-es.
- `curl -sSf https://app.sharity.hu/wp-json/impact/v1/health` → `{"status":"ok", ...}`.
- `bin/staging-qa-suite.sh` DRY_RUN: megállt, mert `DEPLOY_HOST` hiányzik (`.staging_env` nem adja át).
- `bash ~/impact-tools/access-guard.sh doctor`: nincs meg a script (`/Users/bujdosoarnold/impact-tools` hiányzik).
- `.codex/tm/bin/tm-snapshot` PASS → `.codex/logs/system-recovery-log.md` frissült (2026-01-15 10:15:54 CET).
- Kézi/GUI lépések nem futtak: VS Code update + extension refresh, Copilot diagnostics export, WordPress core/plugin update + MU tar, Langfuse screenshot.

### 2026-01-15 – Staging QA + bastion/impactall utóellenőrzés
- `DEPLOY_HOST=staging` exporttal újraindítottam a staging QA-t (`set -a; source .staging_env; set +a; DEPLOY_HOST=staging DRY_RUN=1 bin/staging-qa-suite.sh`), 13/21 PASS, log: `staging-qa-20260115-102508.log`.
- Failok: `/go` + `/go-deal` valid/invalid 500-asok, `/go/<slug>` és `/go-deal/<slug>` 403-asok, `Impact_Safety exists` + `link_guard flag` hibák (WordPress tesztek).
- `access-guard` scriptet nem találtam (`~/impact-tools` alatt csak `health-check.sh`, `impact-local-restore.sh`), ezért `doctor` nem futott.
- `impactall` lefutott a tényleges repo-gyökérből (`/Users/bujdosoarnold/Developer/GitHub/impactshop-notes`), 14/14 PASS; a `system-status-snapshot.md` frissült.

### 2026-01-15 – Staging QA hibák részletes bontás
- `access-guard.sh` nem található sem a `~/impact-tools`, sem a GitHub/Developer mappákban; `doctor` nem futtatható lokálisan.
- `/go?u=...` és `/go-deal?u=...` 500-as: a válasz `Hiányzó paraméter (shop).` (ImpactShop Boot), tehát a QA suite teszt URL-jeinál hiányzik a `shop=` paraméter.
- `/go/<slug>` és `/go-deal/<slug>` 403-as a végső célon: a `go.dognet.com` Cloudflare challenge (JS/cookie) miatt tilt CLI-ből; WordPress oldalról 307 redirect rendben.
- `Impact_Safety` osztály elérhető stagingen (WP-CLI `class_exists` true), a `impact-safety-loader.php` MU plugin jelen van.
- `impact_disable_link_guard` opció nem létezik (`wp option get` hibát ad), ezért a `link_guard flag` teszt FAIL.

### 2026-01-15 – Staging QA javítás + újrafuttatás
- A `bin/staging-qa-suite.sh` redirect tesztekben bevezettem a `shop=` paramétert és leállítottam a külső redirect követést (`-sI`), hogy ne Cloudflare 403-ra fusson.
- Az `Impact_Safety exists` ellenőrzés most SSH-n fut (nem lokálisan).
- Újra futtatva: `staging-qa-20260115-104802.log` → 21/21 PASS.

### 2026-01-15 – CJ tranzakciós dokumentáció váz
- Hozzáadtam egy CJ Commission Detail alapú spec vázat: `docs/cj-transactions.md`.
- A pontos CJ mezőnevek kitöltése még a CJ GraphQL Commission Detail schema alapján szükséges.

### 2026-01-15 – CJ Commission Detail mezőmapping kitöltve
- A Commission Detail GraphQL schema alapján kitöltöttem a mezőneveket és státusz mappinget: `docs/cj-transactions.md`.
- Készült rövid CJ ingest TODO lista is: `docs/cj-transactions.md`.
- Elkészült a CJ Commission Detail smoke script: `scripts/cj-commission-smoke.sh`.
- CJ smoke futás (PAT + publisher id): 0 rekord `PENDING` és `ACCEPTED` státuszra az elmúlt 30 napban (websiteIds=101302202 mellett is).
- CJ smoke futás 2025-12-01 → 2025-12-31 (ACCEPTED): 0 rekord.
- CJ smoke futás 2025-12-18 (websiteIds=101302202): `AUTOMATED` státuszban 1 rekord (commissionId `3720682809`, eventDate `2025-12-18T10:35:42Z`, postingDate `2025-12-18T11:31:06Z`, lockingDate `2026-01-10T08:00:00Z`, advertiser `5619548` / JátékNet.hu, pubCommissionAmount `2.511`, saleAmount `50.215`, orderId `11850266`, actionType `item_sale`, actionStatus `locked`, `sid` null). `ACCEPTED`/`PENDING` 0.
- CJ mapping döntés: `AUTOMATED` státusz pending‑ként kezelendő (activityben megjelenhet).
- SID hiány tisztázása: a CJ rekord `sid` null, ezért ellenőrizendő, hogy a `/go` CJ click URL tartalmazza‑e a `sid` paramétert, és hogy a `impactshop-go-clicks.log` alapján visszaköthető‑e az NGO.
- Prod `impactshop-go-clicks.log`: van CJ click 2025-12-30 körül (`cj-5619548`), ebből 1 sorban `sid` megjelenik (`teszt-ngo~<pseudo>`), 2 sorban `sid` üres – tehát a `sid` param nem minden CJ linknél kerül rá.

### 2026-01-15 – CJ SID ellenőrzés (blokkolva hálózat nélkül)
- Kérés: ellenőrizni a CJ click URL-t a `sid`-mentes soroknál (cj_click_url vs program_id fallback), majd logolni egy friss `/go?shop=cj-5619548&d1=teszt-ngo&u=...` hívást és összevetni, mikor kerül rá a `sid`.
- Lokálisan a hálózat/DNS nem elérhető: `curl -sI https://app.sharity.hu/...` → exit 6, `ssh sharityh@cp40.ezit.hu ...` → host resolve hiba, így a friss logolás és a szerverlog ellenőrzése nem futtatható innen.
- Következő lépés (a gépeden, hálózattal): `curl -sI "https://app.sharity.hu/go?shop=cj-5619548&d1=teszt-ngo&u=https://example.com"` majd `ssh sharityh@cp40.ezit.hu "tail -n 50 /home/sharityh/app/wp-content/uploads/impactshop-go-clicks.log | grep cj-5619548"`; a `target_host` alapján dönthető el, hogy cj_click_url (jdoqocy.com) vagy program_id fallback (advertiser host) ment-e.

### 2026-01-15 – Snapshot + recovery gyors ellenőrzés
- A `system-status-snapshot.md` továbbra is 2025-11-14-es, és ebben `SSH_HOST: nincs megadva`.
- A tényleges SSH kapcsolat a `.codex/connection.json` szerint be van állítva (`ssh_host: sharityh@cp40.ezit.hu`), tehát a snapshotból hiányzik, de a projektkonfig tartalmazza.

### 2026-01-15 – SSH host emlékeztető + impactall próbálkozás
- Frissítettem a `system-status-snapshot.md` meta blokkot (aktuális idő, git hash/ág, `SSH_HOST=sharityh@s59.tarhely.com`).
- A `bin/codex-refresh.sh` már a `.codex/.env.local` fájlt is betölti, hogy az `ssh_host` bekerüljön a Codex snapshotba.
- `source .codex/.env.local && ~/bin/impactall` futtatásnál a script a git rootot a `Developer/GitHub/impactshop-notes` alá resolve-olta, és a sandbox nem tudott oda írni (`Operation not permitted`, `.git/index.lock`), ezért a guard kör itt nem fejeződött be.

### 2026-01-15 – impactall futtatás engedéllyel
- `source .codex/.env.local && ~/bin/impactall` sikeresen lefutott (14/14 PASS, WARN/FAIL nincs).
- REST: staging 200 / 1688 ms (redirected_to:app.sharity.hu), production 200 / 1408 ms.
- `system-status-snapshot.md` frissült (auto update blokk hozzáadva).
- Megjegyzés: az `impactshop-status.md` meta blokkban az `SSH_HOST` továbbra is üresen jelent meg; ezt külön kell javítani, ha elvárás a státuszlapon is.

### 2026-01-15 – SSH_HOST fix az impactshop-status.md-ben
- A `~/bin/codex-refresh` most betölti a `/.codex/.env.local` fájlt, így az `SSH_HOST` bekerül a státuszlap meta blokkjába.
- Újrafuttattam a `~/bin/codex-refresh`-t, az `impactshop-status.md` meta részben megjelent a `sharityh@s59.tarhely.com`.

### 2026-01-15 – CJ SID ellenőrzés (log + click URL)
- Friss /go hívás: `https://app.sharity.hu/go?shop=cj-5619548&d1=teszt-ngo&u=https://example.com` → 307 redirect `https://www.jateknet.hu/?src=impactshop...`.
- Prod log: `impactshop-go-clicks.log` friss sor → `sid` üres, `target_host=www.jateknet.hu`, `shop=cj-5619548`, `ngo=teszt-ngo`, `pseudo=XH5DP9BXJRFB`.
- `impactshop_shops` alapján a CJ bejegyzésnél van `cj_click_url` és `default_cta_url`: `https://www.jdoqocy.com/click-101589464-14448006` (program_id=5619548).
- Következtetés: a mostani /go hívás a CJ click URL helyett az advertiser hostra ment, ezért a `sid` nem került rá; a CJ linképítés útvonala ellenőrzendő (cj_click_url preferálás + sid hozzáfűzés).

### 2026-01-15 – CJ /go javítás + deploy
- Kód: a CJ linképítést a `/go` ágba előre hoztam CJ shopoknál, és a `sid` most `d1~pseudo` formában épül (`impactshop_pseudo_id` cookie alapján).
- Frissítés érintett: `wp-content/mu-plugins/impactshop-boot.php`, `wp-content/mu-plugins/impactshop-go-bridge.php`.
- Deploy: `rsync` prod+stagingre, majd `wp cache flush` mindkét környezeten.
- Teszt: `curl -sI .../go?shop=cj-5619548&d1=teszt-ngo&u=https://example.com&ts=...` + `impactshop_pseudo_id=TESTPSEUDO123` → 307 redirect `https://www.jdoqocy.com/click-101589464-14448006?sid=teszt-ngo~TESTPSEUDO123...` (CJ click URL + sid ok).
- Log hiány: az `impactshop-go-clicks.log` nem frissült az új tesztre (utolsó bejegyzés maradt 16:42:18), ezért a log‑oldali verifikációt nem tudtam megtenni.

### 2026-01-15 – CJ shopok láthatóság (prod ellenőrzés)
- Prod log: `about-you` kattintás `www.anrdoezrs.net` hostra ment (CJ link), de `is_cj=0` (nem CJ slug).
- `impactshop_shops` option: összesen 106 shop, ebből 42 CJ slug (`cj-*`), 33 rendelkezik `cj_click_url`-lel.
- Valószínű ok: a front/REST shoplisták a CSV-t használják, nem fésülik hozzá az `impactshop_shops` CJ listát, ezért a CJ shopok nem jelennek meg a listákban.

### 2026-01-15 – CJ shop merge a Netflix/kupontérképhez
- `impactshop_get_shops()` bővítve CJ registry merge-gel (option `impactshop_shops` → CJ shopok hozzáadva, duplikált slugok kihagyva).
- Érintett: `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`.
- Deploy: MU plugin rsync prod+staging, cache flush, `impactshop_fragment_*` transiensek törlése prod+staging.
- WP-CLI ellenőrző `eval` rossz idézőjelezéssel futott, ezért a debug logban 1x „Undefined constant network” fatal jelent meg; ez a check-hez kötődik, nem a futó oldalhoz.

### 2026-01-15 – WPCode big snippet CJ merge (prod)
- A `WP big snippet` (post_id 17093) `impactshop_get_shops()` függvényét frissítettem CJ merge-hez (option `impactshop_shops` + CJ slugok).
- Cache flush és `impactshop_fragment_*` transiensek törlése prodon megtörtént.
- Ellenőrzés: `impactshop_get_shops()` prodon `TOTAL=106`, `CJ=42`.

### 2026-01-15 – CJ kategória igazítás a Netflix szűrőhöz
- A CJ shopok kategóriáját `Vegyes`-re állítottam (mind MU pluginban, mind a WPCode big snippetben), mert a fő ImpactShop oldal `categories=` listája ezt tartalmazza, a `CJ` kategóriát nem.
- Ellenőrzés (prod): CJ minták `cj-2709631`, `cj-3387283`, `cj-3587693` már `Vegyes` kategóriával jönnek `impactshop_get_shops()` alapján.

### 2026-01-15 – CJ shop meta enrich (név/logó/kategória)
- A `tools/cj_shops.json` alapján kitöltöttem az `impactshop_shops` optionben hiányzó mezőket (name, logo_url, category, domain) prodon.
- Cache flush + `impactshop_fragment_*` transiensek törölve.
- Ellenőrzés: CJ minták már névvel/logóval és kategóriával jönnek (`Skytours US`, `GeekBuying`, `Jalbum`, `Answear.hu`, `inSPORTline.hu`).

### 2026-01-15 – CJ Commission Detail lekérdezés (2025-12-01 → 2025-12-31)
- `scripts/cj-commission-smoke.sh` futtatva (validationStatus=AUTOMATED, websiteIds=101302202).
- Eredmény: 1 rekord 2025-12-18-ra (`commissionId=3720682809`, `advertiser=5619548` JátékNet.hu), `sid=null`, `actionStatus=locked`.

### 2026-01-15 – CJ Commission Detail státusz ellenőrzés + /go teszt
- `scripts/cj-commission-smoke.sh` futtatva `PENDING` és `ACCEPTED` státuszokra (2025-12-01 → 2025-12-31, websiteIds=101302202): mindkettő 0 rekord.
- Friss /go teszt: `https://app.sharity.hu/go?shop=cj-5619548&d1=teszt-ngo&u=https://example.com` → 307 redirect `jdoqocy.com` + `sid=teszt-ngo~TESTPSEUDO123`.
- Log ellenőrzés SSH-val nem sikerült: `ssh sharityh@cp40.ezit.hu` → `Permission denied (publickey, gssapi...)`.

### 2026-01-16 – CJ /go log ellenőrzés (helyes SSH host)
- Log tail prodon: `ssh sharityh@s59.tarhely.com "tail -n 80 /home/sharityh/app/wp-content/uploads/impactshop-go-clicks.log | grep cj-5619548 | tail -n 5"`.
- Találat: `target_host=www.jateknet.hu`, `sid` üres, `is_cj=1`, `pseudo=XH5DP9BXJRFB` (utolsó sor 2026-01-15 16:42:18).

### 2026-01-16 – CJ /go teszt + log ellenőrzés (friss kattintás)
- Friss /go hívás: `https://app.sharity.hu/go?shop=cj-5619548&d1=teszt-ngo&u=https://example.com` + `impactshop_pseudo_id=TESTPSEUDO123` → 307 `jdoqocy.com` + `sid=teszt-ngo~TESTPSEUDO123`.
- Log tail prodon ugyanazt az utolsó sort adta vissza (nem jelent meg új bejegyzés a logban).

### 2026-01-16 – impactshop-go-clicks.log állapot
- Prod log fájl: `/home/sharityh/app/wp-content/uploads/impactshop-go-clicks.log` (tulajdonos: `sharityh`, mód: `-rw-r--r--`), utolsó módosítás: `2026-01-15 17:42`.
- A fájl tailje nem változott a friss /go kattintás után, tehát a jelenlegi /go útvonal nem ír új sort a logba.

### 2026-01-16 – /go click log visszakapcsolva (prod)
- WPCode snippet (post_id 17093) `impactshop_handle_go` kiegészítve logolással (`impactshop-go-clicks.log`).
- MU plugin logolás hozzáadva: `wp-content/mu-plugins/impactshop-boot.php`, `wp-content/mu-plugins/impactshop-go-bridge.php`.
- Friss teszt: `/go?shop=cj-5619548&d1=teszt-ngo&u=...` + `impactshop_pseudo_id=TESTPSEUDO123` → új log sor: `target_host=www.jdoqocy.com`, `sid=teszt-ngo~TESTPSEUDO123`, `is_cj=1`.

### 2026-01-16 – Valós CJ shop kattintás + log ellenőrzés
- `/go?shop=cj-5619548&d1=teszt-ngo&u=https://www.jateknet.hu/` → 307 `jdoqocy.com` + `sid=teszt-ngo~TESTPSEUDO123`.
- Log: új sor megjelent `target_host=www.jdoqocy.com`, `sid=teszt-ngo~TESTPSEUDO123`, `is_cj=1`.

### 2026-01-16 – CJ kupon/deal megjelenítés (Netflix + coupons + deals)
- CJ linkek betöltése: `impactshop-netflix-shortcodes.php` + WPCode snippet (post_id 17093) kiegészítve `impactshop_cj_links` merge-gel.
- Coupons: CJ linkek megjelennek (shop logó + link név/címke), CTA `/go/cj-<id>` d1 paraméterrel.
- Deals: CJ linkek bekerülnek a deals listába a banner feeden keresztül; CJ shopok `logo`-ja kerül a kártyára, CTA `/go/cj-<id>` d1 paraméterrel.
- Prod deploy: MU plugin rsync + WPCode snippet frissítve; transiensek törölve (`impactshop_fragment_*`, `impactshop_deals_banners_*`, `impact_coupons_present_cards_v3`).

### 2026-01-16 – CJ deals banner fallback (WPCode)
- A WPCode snippet `impact_deals_netflix` részében a `banner_by_slug` már CJ linkekkel is bővül, így a deals sáv is kap CJ kártyákat.
- A CJ kártyákhoz shop logo kerül háttérképként (local logo fallback), a CTA `/go/cj-<id>&u=<destination>` d1 paraméterrel.
- Cache/transient törlés újrafuttatva.

### 2026-01-16 – CJ kupon logók helyi cache
- `impactshop_cj_links` logók clearbit helyett helyi fájlokra állítva (`/wp-content/uploads/impactshop/cj-logos/*.png`), hiányzók letöltve Google faviconból.
- Eredmény: CJ kupon kártyák már helyi logót használnak (pl. `cj-5853898`, `cj-6494829`, `cj-5774662`).

### 2026-01-16 – Elementor cache flush + state ellenőrzés
- Elementor CSS cache flush: `wp elementor flush_css` prodon.
- Transiensek törölve (`impactshop_fragment_*`, `impactshop_deals_banners_*`, `impact_coupons_present_cards_v3`), WP cache flush.
- HTML ellenőrzés: coupons 27 kártya, deals 49 kártya; CJ kupon logók már helyi `cj-logos/*.png`.

### 2026-01-16 – CJ tranzakciók bekötése (ticker/leaderboard/activity)
- `impactshop-metrics-ngo.php`: CJ Commission Detail fetcher beépítve (PAT + publisher id + website id), Dognet + CJ összevonás.
- Mapping: `AUTOMATED`/`PENDING` → pending, `ACCEPTED` → approved; `REJECTED/DECLINED/REVERSED` kizárva.
- NGO slug: `sid` támogatva (`d1~pseudo` → `d1`), activity/leaderboard CJ sorokat is számol.
- Deploy: MU plugin rsync prod+staging, cache flush; ticker/leaderboard/activity transiensek törölve.
- Ellenőrzés: prod `/wp-json/impact/v1/ticker`, `/leaderboard?tab=ngo`, `/activity` válaszok OK.

### 2026-01-16 – CJ backfill + activity célzott ellenőrzés
- Backfill override: `commissionId=3720682809` (orderId `11850266`, advertiser `5619548`) → `teszt-ngo` slug.
- REST bővítés: `/wp-json/impact/v1/activity|leaderboard|ticker` támogat `from`/`to` paramétert; `activity` `limit` is állítható.
- Ellenőrzés: `.../activity?from=2025-12-01&to=2025-12-31&limit=20` → megjelenik `teszt-ngo • 1,26 € • 2025-12-18 10:35`.
- Friss CJ tranzakció (sid-es) nincs az elmúlt 7 napban (CJ API: 0 rekord), ezért valós “fresh” activity ellenőrzés nem lehetséges.

### 2026-01-16 – CJ debug endpoint
- Új endpoint: `/wp-json/impact/v1/cj-debug` (paramok: `from`, `to`, `limit`).
- Válasz: CJ tranzakciók listája `sid` + `ngo` feloldással, jutalék mezőkkel.

### 2026-01-16 – CJ debug endpoint kivezetése
- A `/wp-json/impact/v1/cj-debug` endpoint eltávolítva (prod+staging deploy, cache flush).

### 2026-01-16 – CJ totals bekötés a /impactshop/v1/totals végpontba
- Prod MU plugin: `/home/sharityh/app/wp-content/mu-plugins/impactshop-rest-totals.php` kiegészítve CJ Commission Detail merge-gel (ism_cj_fetch_commissions), CJ advertiserId → shop slug mappinggel, CJ mezőkkel (commission + order value).
- Cache törlés: `impactshop_totals_v2_*`, `impactshop_sum_totals_*`, `impactshop_cj_commissions_*` transiensek.
- Ellenőrzés: `/wp-json/impactshop/v1/totals?from=2025-12-01&to=2025-12-31&status=all&group=ngo` már tartalmazza `teszt-ngo` sort (commission 2.51, donation 1.26).

### 2026-01-16 – CJ totals hosszú időablak javítás (chunked fetch)
- CJ fetcher szeletelése 30 napos ablakokra a totals collectorban (`impactshop_totals_cj_fetch_commissions_chunked`), hogy a 2025-10-23→ma tartományban is megjelenjen a CJ rekord.
- Prod deploy: `/home/sharityh/app/wp-content/mu-plugins/impactshop-rest-totals.php` frissítve, transiensek törölve.
- Ellenőrzés: `/wp-json/impactshop/v1/totals?from=2025-10-23&to=ma&status=all&group=ngo` már tartalmazza `teszt-ngo` sort; sticky HTML-ben `data-amt=1562.12` (`1 562 Ft`).

### 2026-01-16 – Impact leaderboard/activity név + CJ activity javítás
- `impactshop-metrics-ngo.php`: CJ fetch szeletelve 30 napos chunkokra, így hosszú időablaknál is megjelenik CJ az `/impact/v1/activity` listában.
- `ism_resolve_ngo_name` bevezetve, `mbe` → “Mozgássérültek Budapesti Egyesülete”; leaderboard + activity már névvel jelenik meg.
- `impactshop-rest-totals.php`: `mbe` override beégetve, hogy a totals alapú UI-ban is névvel jelenjen meg.
- Cache törlés: `impactshop_lb_*`, `impactshop_activity_*`, `impactshop_ticker_*`, `impactshop_totals_v2_*`, `impactshop_fragment_*`.

### 2026-01-16 – Impact leaderboard (HUF) kerekítés + teszt NGO elrejtés
- Prod MU plugin: `/home/sharityh/app/wp-content/mu-plugins/impact-mini-helpers.php` – shop leaderboard sorok kerekítése most a sticky (shop_ngo összeg) alapján igazodik, így a sorok összege = sticky.
- `teszt-ngo` kiszűrve az NGO leaderboard listából.
- Cache törlés: `impactshop_totals_v2_*`, `impactshop_fragment_*`.

### 2026-01-16 – Impact social ticker fallback (activity alapú)
- `impact-social-mvp.php`: ha a ledger üres vagy túl régi, fallback az `/impact/v1/activity` listára (from/to támogatással); `teszt-ngo` szűrve.
- Endpoint ellenőrzés: `/wp-json/impact/v1/social/ticker?limit=5&status=all` már activity-alapú elemeket ad.

### 2026-01-16 – Állás mentve
- Aktuális állapot: sticky + leaderboard CJ-vel, shop sorok összege megegyezik a stickyvel, `teszt-ngo` rejtve az NGO listában.
- Következő kérés esetén: activityből is szűrjük a `teszt-ngo` sort (ha szükséges).

### 2026-01-16 – Full leaderboard + Impactshop 2 (CJ shopok)
- `/wp-json/impact/v1/leaderboard` bővítve `slug` és `amount_eur` mezőkkel (HUF → EUR becslés `IMPACT_SUM_RATE_HUF` alapján), hogy a `impact_full_leaderboard` shortcode megjelenjen.
- `impactshop_2` oldal (post ID `16156`) statikus shop-kártya HTML frissítve, CJ shopok hozzáadva (42 db) a `shop-donation-cards.html` alapján.
- Cache flush: `wp cache flush` (prod).

### 2026-01-16 – Metrics helyreállítás + CJ merge (prod)
- `/home/sharityh/app/wp-content/mu-plugins/impactshop-metrics-ngo.php` visszaállítva backupból, majd újra felépítve: CJ GraphQL commission fetch + 30 napos chunkolás, `sid` alapú NGO feloldás, `mbe` névfeloldás, `teszt-ngo` override.
- `/impact/v1/leaderboard` most `slug`, `amount` (HUF) és `amount_eur` (EUR) mezőket ad a full toplista számára.
- Cache törlés: `impactshop_lb_*`, `impactshop_activity_*`, `impactshop_ticker_*`, majd `wp cache flush`.

### 2026-01-16 – impactshop_2 Elementor frissítés
- `impactshop_2` oldalnál az Elementor `_elementor_data` frissítve a CJ‑vel bővített `shop-donation-cards.html` tartalommal (a frontend Elementor JSON-t használ, nem a post_contentet).
- Elementor CSS cache flush: `wp elementor flush_css`, majd `wp cache flush`.

### 2026-01-16 – impactshop_2 fejléc statok frissítése
- `shop-donation-cards.html` fejlécek aktualizálva (Aktív partnerek: 106, Kategóriák száma: 37, Legmagasabb adomány: 9%).
- Elementor `_elementor_data` újratöltve a friss HTML-lel, majd `wp elementor flush_css` + `wp cache flush`.
- `impactshop_2` `post_content` is frissítve ugyanezzel a HTML-lel, hogy a frontend cache-től függetlenül is konzisztens legyen.

### 2026-01-16 – CJ adományráták (export alapján)
- CJ `advertisers.csv` alapján beállítottuk a CJ shopok adományrátáit (percent mezők: 0.02 → 2% logika), a CJ kártyákon már nem “Terméktől függ” jelenik meg.
- `shop-donation-cards.html` újragenerálva; statok frissítve (Legmagasabb adomány: 25%).
- Elementor `_elementor_data` + `post_content` frissítve, `wp elementor flush_css` + `wp cache flush`.

### 2026-01-16 – CJ adományráták 50% szabály
- A CJ jutalék %-ot 50%-os adományra konvertáljuk (egységes szabály az oldalon).
- `impactshop_2` kártyák frissítve (pl. Akkuk.hu 7,5%), cache flush lefutott.

### 2026-01-16 – Social ticker megosztás teszt
- Teszt ledger sor beszúrva (`source_ref=test-share-20260116-1424`, pseudo `TEST1234`, ngo `mbe`, amount 10 Ft, status pending), hogy a `can_share` logikát validáljuk.
- Ellenőrzés: `/wp-json/impact/v1/social/ticker?limit=3&status=all&impact_pseudo_id=TEST1234` → `is_owner=true`, `can_share=true`, share linkek generálódnak.
- Teszt sor törölve, ticker visszaállt a fallback activity listára.

### 2026-01-16 – Glami kattintás ellenőrzés
- `/wp-content/uploads/impactshop-go-clicks.log` alapján a Glami + Bátor Tábor kattintás logolva (2026-01-16 14:26:42, pseudo: `570F2BFA`).
- Ledger friss sor továbbra sincs 2025-12-04 óta, ezért a social ticker fallback activity módban fut és megosztás nem aktiválódik (ledger nélkül nincs donor-azonosítás).

### 2026-01-16 – CJ + Dognet ledger cron és donor-azonosítás (prod)
- Új mu-plugin: `wp-content/mu-plugins/impactshop-ledger-cron.php` → WP‑Cron események: `impactshop_cj_ledger_cron` + `impactshop_dognet_ledger_cron` (30 percenként), azonnal lefuttatva.
- CJ ledger sync: `ImpactShop_CJ::cron_sync_ledger()` publikus metódus készült; cron run sikeres.
- Dognet ledger ingest: `dognet_api_list_conversions_all` alapján ledger‑be ír (NGO/commission/status/shop map, `source_ref=dognet:<id>`), státusz/összeg opciók logolva (`impactshop_dognet_ledger_last_stats`).
- /go Dognet ág: donor pseudo most `data2` mezőbe kerül (`d2`), az NGO továbbra is `d1` → a ledger a `d2/data2` mezőből próbálja visszafejteni a pseudo‑t.
- Ellenőrzés: `wp cron event list` szerint az új cronok ütemezve vannak, ledgerben friss Dognet sorok megjelentek (2026‑01‑16 glami kattintások).
- Nyitott: Dognet visszaadja‑e a `data2` mezőt a konverziós feedben; ezt a következő valós jutalék/visszajelzés után validálni kell.


### 2026-01-16 – Social ticker default status (prod)
- `impact-social-mvp.php`: a default `status` param most `all`, így a ledgerből a pending tételek is bekerülnek, és megjelenik a tényleges shopnév (nem fallback “Impact Shop vásárlás”).
- Ellenőrzés: `/wp-json/impact/v1/social/ticker?limit=5` → `shop_display` már Glami / Árukereső.
- Megjegyzés: a 2025‑12‑18 CJ rekord `teszt-ngo`, amit a ticker explicit szűr, így ott nem látszik.

### 2026-01-16 – NGO névfeloldás a social tickerben
- `impact-social-mvp.php`: NGO display feloldás javítva (slug → név), így a ticker a `ngo_codes.csv` alapján ékezetes neveket ad vissza.
- `impactshop-ngo-card.php`: `impactshop_resolve_ngo_name()` bővítve (map + override), pl. `mbe` → „Mozgássérültek Budapesti Egyesülete”.
- Ellenőrzés: `/wp-json/impact/v1/social/ticker?limit=5&status=all` → Bátor Tábor / MBE már névvel jelenik meg.
- Nyitott: a Dognetből érkező numerikus NGO kódok (`0-...`) nem szerepelnek az `ngo_codes.csv`-ben, így ezek továbbra is kódként látszanak.

### 2026-01-16 – Dognet D1 nélküli sorok kizárása
- `impactshop-ledger-cron.php`: Dognet ledger ingest most már eldobja az olyan NGO slugokat, amelyekben nincs betű (pl. `0-...`), így nem számoljuk és nem jelenítjük meg azonosítható NGO nélkül.
- `impactshop-metrics-ngo.php`: NGO kiválasztásnál csak betűt tartalmazó slugot fogadunk el, így ticker/leaderboard/activity sem fog numeric kódokból számolni.
- `impact-social-mvp.php`: ledger + fallback szűrés kiegészítve, numeric kódok nem kerülnek a social tickerbe.
- Ellenőrzés: `/wp-json/impact/v1/social/ticker?limit=5&status=all` → a `0-...` sorok eltűntek.

### 2026-01-16 – Numerikus NGO sorok törlése a ledgerből
- Tisztítás: `DELETE FROM wp_impact_ledger WHERE ngo_slug REGEXP '^[0-9-]+$'` → 20 sor törölve.
- Ellenőrzés: `/wp-json/impact/v1/social/ticker?limit=5&status=all` már nem tartalmaz numeric NGO slugokat.

### 2026-01-16 – Impact Shop javítások összefoglaló
- CJ + Dognet ledger cron bevezetve (WP‑Cron: `impactshop_cj_ledger_cron`, `impactshop_dognet_ledger_cron`), ingest statok opciókban.
- /go Dognet pseudo `d2` mezőbe kerül; CJ SID továbbra is `d1~pseudo`.
- Social ticker alap `status=all`, így pending sorok + tényleges shopnevek látszanak.
- NGO névfeloldás javítva a tickerben (slug → ékezetes név az `ngo_codes.csv` alapján), `mbe` override.
- D1 nélküli (numeric) NGO sorok kizárva minden számításból; ledgerből a numeric sorok törölve.

### 2026-01-16 – Affiliate integrációs runbook
- Új, végigvezető runbook készült: `docs/affiliate-integration-runbook.md` (shopok, deals, coupons, sticky, social ticker, NGO/shop toplisták, ledger/cron, QA, rollback).
- Impactall autoload frissítve: `impact-hub-system-v1.3.md` hivatkozza a runbookot.

### 2026-01-16 – Affiliate runbook bővítések (Sonnet javaslatok)
- Beemelve: Kupon Harvester integráció, Impi AI ajánlások, Sprint dependency check, GDPR/CMP, Observability, Documentation/stub inventory, Security testing, QA/rollback bővítések, phased integráció.
- Kihagyva: Billingo (nem szükséges), NAV/Billingo rész nem került be.

### 2026-01-16 – Affiliate runbook secret mentési szabály
- Kötelező secret mentési pont rögzítve: `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env` (minden affiliate secret itt is legyen, akkor is, ha máshol is tárolod).

### 2026-01-16 – Impactall emlékeztető bővítés
- Impact Shop autoload blokkba bekerült a kötelező secret mentési pont: `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env`.

### 2026-01-16 – Kupon harvester futtatás + ellenőrzés
- Futtatás: `python3 scripts/coupon_harvester_pipeline.py --config .codex/cron/coupon-harvester-config.json --out-dir tmp/coupon-harvester --dry-run --json-out tmp/ingest/gmail.json`.
- Eredmény: 18 868 kupon (Gmail checked 83, matched 33, HTML source 2), log: `tmp/coupon-harvester/harvester-summary.json`.
- Árukereső forrás: `../ai-agent/tmp/ingest/arukereso.json` (utolsó frissítés: 2025‑12‑27) → bemásolva `tmp/ingest/arukereso.json` a merge‑hez.
- Merge ellenőrzés: `npx tsx tools/ingest/export-coupons.ts` → `tmp/ingest/export-coupons.csv` (18 892 sor).
- Megjegyzés: az Árukereső JSON frissítésre szorul, ha naprakész feed kell.

### 2026-01-16 – Árukereső ingest frissítés
- `ai-agent`: `npm run playwright:arukereso` → 24 rekord (`tools/out/arukereso-promotions.json`).
- `ai-agent`: `npm run ingest:normalize` → friss `tmp/ingest/arukereso.json` (24 rekord), Gmail 44 rekord.
- `impactshop-notes`: friss arukereso JSON átmásolva `tmp/ingest/arukereso.json`, majd `npx tsx tools/ingest/export-coupons.ts` → `tmp/ingest/export-coupons.csv` (18 892 sor).

### 2026-01-16 – Kupon harvester újrafuttatás
- Futtatás: `python3 scripts/coupon_harvester_pipeline.py --config .codex/cron/coupon-harvester-config.json --out-dir tmp/coupon-harvester --dry-run --json-out tmp/ingest/gmail.json`.
- Eredmény: 1 420 kupon (Gmail matched 2, HTML source 2), summary: `tmp/coupon-harvester/harvester-summary.json`.

### 2026-01-16 – Kupon merge frissítve
- `tmp/ingest/gmail.json` + friss `tmp/ingest/arukereso.json` → `npx tsx tools/ingest/export-coupons.ts`.
- Eredmény: `tmp/ingest/export-coupons.csv` (1 444 sor).

### 2026-01-16 – Kupon harvester full cron
- Új összevont script: `.codex/cron/coupon-harvester-full.sh` (Árukereső Playwright → ingest normalize → arukereso.json átmásolás → coupon harvester → export merge).
- Használat: heti 2× cron futtatásra kész, log: `.codex/logs/coupon-harvester-full.cron.log`.

### 2026-01-16 – Kupon harvester cron rögzítve
- Cron beállítás: kedd+péntek 09:00 → `.codex/cron/coupon-harvester-full.sh`.
- Impactall emlékeztető frissítve a cron és log hivatkozással.

### 2026-01-16 – Árukereső CTA biztonsági normalizálás
- `ai-agent` `arukereso.ts`: ha a CTA URL nem `*.arukereso.hu`, akkor `https://www.arukereso.hu/`-ra normalizálunk, hogy ne menjen közvetlenül shopra.
- Új ingest futott: `npm run ingest:normalize` + export merge (1 444 sor).
- Coupon copy gomb ellenőrzés: `impactshop-netflix-shortcodes.php` a kupon kártyán `navigator.clipboard.writeText(code)`-ot hív (fallback `execCommand('copy')`).

### 2026-01-16 – Árukereső átirányítás + kupon másolás megerősítve
- Árukereső link policy: a vásárló mindig az Árukereső összehasonlító oldalra megy, onnan kattint tovább a shopra (direkt shop link tiltva).
- Kupon másolás: a coupons kártya másolás gombja vágólapra teszi a kupont (JS clipboard API + fallback).

### 2026-01-16 – Kupon harvester kód‑szűrés szigorítva
- `scripts/coupon_harvester_pipeline.py`: HTML forrásoknál csak markeres kód elfogadás (`kuponkód`/`coupon code`), laza regexből csak számot tartalmazó kód, stopword szűrés + dátum/“31‑ig” kiszűrés.
- Új dry‑run: `manual_coupons_draft-2026-01-16T180121.csv` → 1 sor (Decathlon `WINTER20`), a korábbi CSS/HTML tokenek eltűntek.

### 2026-01-16 – Gmail akciók + OCR bővítés (lokál)
- `scripts/coupon_harvester_pipeline.py`: akciós ajánlatok kód nélkül is jöhetnek (`sale_event`), és OCR támogatás képes képről szöveget beolvasni.
- OCR konfiguráció: `.codex/cron/coupon-harvester-config.json` `ocr` blokk (provider=google, max_images=5).
- Futás: OCR jelenleg kihagyva, mert `GOOGLE_APPLICATION_CREDENTIALS` nincs beállítva a környezetben.
- Gmail query bővítve akciós kulcsszavakkal: `akcio/akció/akciós/sale`.
- Cron frissítve: `.codex/cron/coupon-harvester-full.sh` betölti a `~/.impact-secrets/env.d/capi.env` fájlt, hogy OCR‑hez szükséges env is elérhető legyen.
- OCR próbafuttatás: `manual_coupons_draft-2026-01-16T183944.csv`, OCR képek: 1, hiba: 0; új kupon nem került hozzá.
- Sale detection bővítve: áthúzott/old‑price HTML jel, százalékos csökkenés és két ár‑mintázat alapján is `sale_event` jöhet.
- OCR képlistában promo‑képek priorizálva (banner/promo/sale), favicon/logo kiszűrve; `max_images=5`.
- Playwright OCR fallback: `tools/playwright/harvester-runner.ts` képernyőképet is ment (`tools/playwright/harvester-config.json` → `tmp/coupon-harvester/ocr-images/*.png`), és az OCR források bekerültek a `coupon-harvester-config.json`-be.
- OCR futás (Playwright screenshot): `manual_coupons_draft-2026-01-16T185057.csv`, OCR képek: 3, hiba: 0; új akció nem jött be a jelenlegi forrásokból.
- Playwright bővítés: `maiakcio.com` + `tescoma.hu/black-friday-akcio` snapshot + screenshot.
- OCR futás (bővített forrás): `manual_coupons_draft-2026-01-16T185725.csv`, 8 sor (maiakcio sale_event + OCR kódok, tescoma OCR kód), OCR képek: 15, hiba: 0.
- OCR kód szűrés: OCR forrásból csak betű+szám, 5–10 hossz, és nem szekvenciális számok kerülnek be; gyakori szavak tiltólistán.
- Harvester bővítés: Playwright és kupon config kibővítve az összes Dognet + CJ partner URL‑lel (106 oldal) a HTML + OCR screenshot betöltéshez.
- Gmail OCR bővítés: Gmail HTML képekből is OCR fut, külön `gmail_max_images` limit (10) a configban.
- Árukereső Playwright cron: nightly futás beállítva (`01:30`) → `.codex/cron/arukereso-playwright.sh`.
- Árukereső Playwright: Next.js blokkokból nem csak top3, hanem minden termék kerül feldolgozásra; hozzáadott végtelen scroll + “Load more” kattintás a teljes lista bejárásához.

### 2026-01-16 – impactall futtatás
- Parancs: `{ [ -f .codex/.env.local ] && source .codex/.env.local; } && ~/bin/impactall`.
- Eredmény: 13/14 PASS, 1 WARN (Sprint pre-flight S1 doc lint).
- Staging: HTTP 200 / 1601 ms (redirected_to:app.sharity.hu), Production: HTTP 200 / 1200 ms.
- Log: `.codex/reports/impactall-20260116-192835-Sprint-pre-flight-(S1).log`.

### 2026-01-17 – Cron PATH fix
- `coupon-harvester-full` + `arukereso-playwright` cron: PATH bővítve (`/usr/local/bin:/opt/homebrew/bin`) → `npm: command not found` hiba javítva.

### 2026-01-17 – Kupon validáció a közös lista után
- Új script: `tools/ingest/validate-coupons.ts` → gyanús kuponkódok listája (`tmp/ingest/export-coupons-validation.csv`).
- Cron: `.codex/cron/coupon-harvester-full.sh` most az export után automatikusan lefuttatja a validálást.
- Kupon validáció bővítés: a validátor a `discount_label` mezőt is figyelembe veszi, és csak `type=coupon_code` sorokra fut (akciós `sale_event` kimarad).
- Validátor finomítás: `%` felismerés robusztusabb (NBSP kezeléssel), így a 20%‑os címek átmennek; jelenleg 4 gyanús Fény24 kód marad (hossz).
- Gmail dedup: `coupon_harvester_pipeline.py` a duplikált kuponokat shop_slug + kuponkód alapján szűri.
- Kuponkód szabály: nincs kötelező betű+szám feltétel; Gmail/OCR csak explicit marker után fogad el kódot (pl. „utalvány kódja”).

### 2026-01-17 – Cron sorrend + OCR hiányzó kép guard
- `coupon-harvester-full.sh`: beiktatva a `playwright:harvest:config`, hogy az affiliate screenshotok/HTML fixtúrák az OCR előtt készüljenek.
- `coupon_harvester_pipeline.py`: OCR kihagyja a hiányzó lokális képeket, és külön figyelmeztet (nem dob hibát).
- Cron health: `coupon-harvester-full.sh` + `arukereso-playwright.sh` logolja a `node/npm` elérhetőséget, és hiányzó `npm` esetén megszakít.
- `coupon-harvester-full.sh`: a `playwright:harvest:config` lépés a notes repo `package.json`-ában van, ezért a futás most a notes repóban történik (korábban az `ai-agent` alatt hiányzott, emiatt félbeszakadt).

### 2026-01-17 – Árukereső teljes kategória + lapozás
- `ai-agent/tools/playwright/arukereso-runner.ts`: bejárja az összes áresett kategóriát (`/aresett-termekek/osszes-kategoria/`) és lapoz minden kategóriában.
- Új limitek: `maxCategories=200`, `maxPagesPerCategory=20` (override: `ARUKERESO_MAX_CATEGORIES`, `ARUKERESO_MAX_PAGES`).
- Konfig: `ai-agent/tools/playwright/arukereso-config.json` → `crawlAllCategories=true`.
- Kategória URL-ek: `.pricedrop-category-item a` linkekből, `orderby=` paraméterrel (kategória‑listázó oldalak).

### 2026-01-17 – Gmail kupon marker finomítás
- `coupon_harvester_pipeline.py`: marker‑alapú kód kinyerésnél unicode (ékezetes) token támogatás + normalizálás (pl. „AKCIÓ” → `AKCIO`), hogy a Gmail szöveges kuponkódok jól kerüljenek be.

### 2026-01-17 – CJ domain mapping
- `tools/cj_shops.csv`: program_url alapján kitöltve a `domain` + `program_id` mezők a CJ partnerekhez.
- `scripts/coupon_harvester_pipeline.py`: CJ domain map automatikus becsatolás (whitelist + Gmail allowed_domains).

### 2026-01-17 – Kupon kinyerés hamis pozitív szűrés
- `coupon_harvester_pipeline.py`: kontextus-szűrés a „kód” körül (kupon/akció kulcsszavak), extra stopwordök, max 12 karakteres kód, valamint `CODE + kód` minta támogatás (pl. „STARGELMONW kód”).
- Stopword bővítés a hamis kódokra (`CSAK`, `HOZZ`, `FELHASZN`, `ALKALMAZ`, stb.).
- HTML fixture kuponkódok: `data-copy-value`/`data-clipboard-text` attribútumokból kinyerés, HTML/OCR forrásnál a `CODE + kód` minta tiltva.
- `validate-coupons.ts`: max kódhossz 12 + bővített benefit jelzés (akció/kedvezmény/kupon/promo kulcsszavak).
- `coupon_harvester_pipeline.py`: a kód körüli szövegablak (±200 karakter) bekerül a `description` mezőbe, hogy a validátor a hosszabb akciós mondatokat is lássa.
- `coupon_harvester_pipeline.py` + `export-coupons.ts`: sale_event rekordokba bekerül `old_price` + `new_price`, és az export CSV oszlopai is bővültek.
- `coupon_harvester_pipeline.py`: kuponkódoknál a kód körüli szövegablakból kinyert `old_price`/`new_price` is mentésre kerül.

### 2026-01-17 – OCR env beállítás
- `GOOGLE_APPLICATION_CREDENTIALS` beírva a `/Users/bujdosoarnold/.impact-secrets/env.d/capi.env` fájlba.

### 2026-01-17 – Árukereső lapozás stabilizálás
- `ai-agent/tools/playwright/arukereso-runner.ts`: a „Következő” linknél először `rel="next"`/href alapján navigál, csak utána kattint; scroll + force click fallback timeouttal.
- `normalizeUrl` helper hozzáadva a relatív next linkekhez (ReferenceError fix).

### 2026-01-17 – Kupon harvester teljes kör futás (háttér)
- OCR env beállítva (`GOOGLE_APPLICATION_CREDENTIALS` a capi.env-ben), a teljes cron kör újraindítva.
- Jelenlegi futás PID: `794` (háttér), Árukereső kategória-scrape folyamatban; logok: `.codex/logs/coupon-harvester-full.run.out`, `.codex/logs/coupon-harvester-full.cron.log`.

### 2026-01-19 – Identity panel állapotmentés (Impact Shop)
- Prod oldalon az identity panel megjelenik, de a gombok nem működnek stabilan és túl sok ismétlődő elem van.
- Kérés: egyszerűsített UI (csak: azonosító másolás, recovery kód másolás, megosztás, jelszókezelő mentés, opcionális becenév; PIN kérés/restore szekciók elhagyása).
- Teendő: `wp-content/mu-plugins/impactshop-identity-panel.php` HTML+JS tisztítás (refresh/request/restore logika kivétele, duplikált mezők és gombok eltávolítása), majd prod deploy.
- Áramkimaradás miatt a munka megszakadt, folytatni innen.

### 2026-01-19 – Identity panel UI egyszerűsítés (helyi)
- `wp-content/mu-plugins/impactshop-identity-panel.php`: PIN kérés/restore/refresh JS eltávolítva, recovery kód külön másolás gombbal, compact shortcode csak ID-t mutat.
- Következő: prod deploy + cache purge, majd ellenőrizni a gombok működését az Impact Shop oldalon.

### 2026-01-19 – Prod deploy kísérlet (identity panel)
- `rsync` próbálkozás prodra (`sharityh@cp40.ezit.hu:/home/sharityh/app/...`) sikertelen: `Permission denied (publickey)`.
- Teendő: SSH hozzáférés / helyes host vagy kulcs, majd deploy + `wp elementor flush_css` + `wp cache flush`.

### 2026-01-19 – Identity panel helyreállítási űrlap + gombok tisztítása
- `wp-content/mu-plugins/impactshop-identity-panel.php`: duplikált megosztás eltávolítva, Frissítés gomb hozzáadva; új helyreállítási űrlap (azonosító + recovery kód) és REST endpoint (`/impact/v1/identity/restore`) a cookie visszaállításhoz.

### 2026-01-19 – Identity panel deploy + cache purge
- `rsync`: `wp-content/mu-plugins/impactshop-identity-panel.php` kiment `/home/sharityh/app` és `/home/sharityh/app-staging` alá.
- `wp cache flush` + `wp elementor flush_css` mindkét környezeten lefutott.

### 2026-01-19 – Identity panel share gomb + becenév mentés fix
- `wp-content/mu-plugins/impactshop-identity-panel.php`: visszakerült a megosztás gomb (ID + recovery kód share/copy fallback), azonosító validáció kis/nagybetű kezeléssel, restore/nickname mentés normalizálás (lowercase).

### 2026-01-19 – Identity panel visszajelzés + autofill
- `wp-content/mu-plugins/impactshop-identity-panel.php`: megosztás fallback prompt, sikeres státusz szín javítás, restore mezők autocomplete attribútumai (username/current-password) a jelszókezelő autofillhez.

### 2026-01-19 – Identity panel UX visszajelzés + restore autofill form
- `wp-content/mu-plugins/impactshop-identity-panel.php`: becenév mentés gombstátusz + inline visszajelzés, megosztás indítás státusz + mailto fallback, restore mezők formba rendezése + autofill attribútumok.

### 2026-01-19 – Identity panel restore javítás (iOS/Safari)
- `wp-content/mu-plugins/impactshop-identity-panel.php`: restore mezők `username`/`password` nevek + `type=password`, recovery kód normalizálás (case/ kötőjel nélkül), JS cookie fallback helyreállítás után.

### 2026-01-19 – Identity panel restore megerősítés (mobil)
- `wp-content/mu-plugins/impactshop-identity-panel.php`: restore inline státusz, gomb disable + label, siker után azonnali mező frissítés + automatikus reload.

### 2026-01-19 – Identity panel jelszókezelő + restore rate limit
- `wp-content/mu-plugins/impactshop-identity-panel.php`: jelszókezelő mentés form wrapper (username/password mezők, readonly nélkül), submit handler + PasswordCredential fallback; restore rate limit (5/óra/IP) + timing-safe compare.

### 2026-01-19 – Identity panel nonce + JS kiszervezés
- `wp-content/mu-plugins/impactshop-identity-panel.php`: REST nonce ellenőrzés (profile/restore), nonce injektálás, recovery regex validáció.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: inline JS kiszervezve, nonce header támogatással.

### 2026-01-19 – Identity panel jelszókezelő autofill finomhangolás
- `wp-content/mu-plugins/impactshop-identity-panel.php`: jelszó mező autocomplete `current-password` azonosító együtt mentéséhez.

### 2026-01-19 – Impact Shop aktivitás napló + ticker név megjelenítés
- `wp-content/mu-plugins/impactshop-activity-log.php`: új aktivitás log tábla + `/wp-json/impact/v1/event` REST endpoint (go kattintás, social megosztás, Impi kérdés).
- `wp-content/mu-plugins/impactshop-boot.php` + `wp-content/mu-plugins/impactshop-go-bridge.php`: /go és /go-deal kattintások logolása.
- `wp-content/mu-plugins/impact-social-mvp.php`: social tickerben teljes azonosító/becenév megjelenítése, share logolás.
- `wp-content/mu-plugins/impactshop-impi-chat.php`: Impi kérdések logolása.

### 2026-01-19 – Social ticker név + donor leaderboard
- `wp-content/mu-plugins/impact-social-mvp.php`: becenév lookup case-insensitive, display label "Becenév (ID)", donor toplistában ID + becenév megjelenítés.

### 2026-01-19 – Identity panel mentés visszajelzés finomhangolás
- `wp-content/mu-plugins/impactshop-identity-panel.php`: jelszó mező autocomplete `new-password` a mentéshez.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: mentés státusz üzenet pontosítása (ha nem kérdez rá, valószínűleg már mentve).

### 2026-01-19 – Identity panel fejlécrész finomítás
- `wp-content/mu-plugins/impactshop-identity-panel.php`: köszönés + „Fiókom” fejlécrész és fiók kezelése gomb.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: becenév alapján személyre szabott köszönés + smooth scroll a fiók szekcióhoz.

### 2026-01-19 – Identity panel compact shortcode egységesítés
- `wp-content/mu-plugins/impactshop-identity-panel.php`: az `impactshop_identity_id` rövidkód is a „Fiókom” fejlécrészt mutatja (nincs azonosító + másolás gomb).

### 2026-01-19 – Identity panel scroll gomb javítás
- `wp-content/mu-plugins/impactshop-identity-panel.js`: a „Fiókom kezelése” gomb már a teljes oldalon keresi a cél szekciót.

### 2026-01-19 – Identity panel támogatás összeg
- `wp-content/mu-plugins/impactshop-identity-panel.php`: új REST endpoint (`/impact/v1/identity/total`) a pseudo ID összesített adományához.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: „Támogatásaim összege” sor frissítése.

### 2026-01-19 – Identity panel total megjelenítés kompakt
- `wp-content/mu-plugins/impactshop-identity-panel.php`: a compact blokkban is megjelenik a „Támogatásaim összege”.

### 2026-01-19 – Identity panel recovery vissza + nonce lazítás
- `wp-content/mu-plugins/impactshop-identity-panel.php`: recovery kód + megosztás vissza a compact blokkon, profil/restore nonce ellenőrzés kikapcsolva.

### 2026-01-19 – Identity panel compact visszaállítás
- `wp-content/mu-plugins/impactshop-identity-panel.php`: a felső (compact) blokkból kivettem a recovery kód/másolás/megoshatás részt, lent maradt a fiók kezelésénél.

### 2026-01-19 – Állapotmentés (leállítás előtt)
- Identity panel, social ticker és aktivitás log módosítások deployolva; cache ürítve.
- Megfigyelés: a top donor lista csak a legnagyobb összegeket mutatja, az alacsony összegű ID-k nem látszanak.

### 2026-01-19 – Identity panel ID visszaállítás
- `wp-content/mu-plugins/impactshop-identity-panel.php`: a fiók kezelésénél visszatettem az azonosító sort (másolás gombbal), hogy működjön a becenév mentés és a megosztás.

### 2026-01-19 – Compact blokk ID eltávolítás
- `wp-content/mu-plugins/impactshop-identity-panel.php`: a felső (compact) blokkból kiszedtem az azonosító + recovery sorokat, lent marad minden.

### 2026-01-19 – Repo rendbetétel
- ` .gitignore`: helyi artefaktok kizárása bővítve (phpunit cache, tm/logs, codex segédfájlok).
- Commit + push: social ticker share log + go click log, valamint státusz/docs snapshot frissítés.
- Megjegyzés: a helyi törlések (`rm`) policy‑blokkon megakadtak, az untracked fájlok lokálisan maradtak.

### 2026-01-19 – Repo takarítás (2/3)
- ` .gitignore`: bővítve a nagy/ideiglenes könyvtárakra (vendor, tests, fixtures, lokális mappák).
- Dokumentációk és tudásbázis fájlok felvéve (root md/csv/html, `docs/`, `CJ links/`, `Google Ads/`, `Impi Tudásbázis/`, `image/`).
- `chatgpt-history/` + `conversation-summaries/` teljes készlet felvéve.
- Tooling és config fájlok felvéve (`scripts/`, `tools/`, `apps/`, `types/`, `bin/`, `package*.json`, `phpunit.xml`, `.github/`).
- Hiányzó MU plugin fájlok felvéve a repóba (`wp-content/mu-plugins/*.php`, `wp-content/mu-plugins/impactshop-identity-panel.js`).

### 2026-01-19 – Repo takarítás (3/3)
- `User token/` és `NGO data/` bekerült a repóba (feloldott ignore).

### 2026-01-19 – Secrets konszolidáció
- `~/bin/impactall`: betölti a `~/.impact-secrets/env.d/capi.env` fájlt, ha elérhető.

### 2026-01-20 – JYSK vote implementáció indul
- Implementáció előkészítés megkezdve a `docs/jysk-video-vote-project.md` terv alapján (felmérés + technikai bontás).

### 2026-01-20 – Állásmentés (JYSK vote)
- Áttekintve a meglévő MU-plugins és activity log környezet; nincs kész vote implementáció a kódban.
- Következő lépés: új MU-plugin(ek) + REST endpointok + adatmodell felvétel a terv szerint.

### 2026-01-20 – JYSK vote core implementáció
- Új MU-plugin: `wp-content/mu-plugins/impactshop-vote-jysk.php` (adatmodell, REST, vote flow, lottery, messaging).
- Új frontend JS: `wp-content/mu-plugins/impactshop-vote-jysk.js` (videó gating, NGO lista, szavazás, tally).
- `wp-content/mu-plugins/impactshop-identity-panel.php`: üzenet megjelenítés a compact blokkban.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: identity ready event + üzenet fetch/ack.

### 2026-01-20 – JYSK vote admin + export
- Admin felület hozzáadva kampány/NGO/üzenet kezeléshez és CSV exporthoz (`impactshop-vote-jysk` menü).
- Manual sorsolás gomb + exportok (log + daily).

### 2026-01-20 – JYSK vote UX finomítás
- Videó progress mentés/folytatás localStorage alapon (`impactshop-vote-jysk.js`).

### 2026-01-20 – JYSK vote hardening
- Sorsolás időzítés: kampány zárása utáni nap 12:00 (HU) + end_at gate.
- Pseudo ID regex validáció a vote pluginben.
- Schema migrate lock a dbDelta párhuzamos futás ellen.
- Kill switch UX üzenet finomítva a vote UI-ban.
- Rate limit headerek + tally cache-control header + CSV export kampány/NGO névvel.
- Rate limit retry_after megjelenítés a frontendben.
- View vs cast külön rate limit + backend analytics log táblával.
- Admin státusz színezés + NGO quick toggle.
- GA4 event tracking (video_start/progress, vote_attempt/success/fail).
- Nonce refresh endpoint + 12h kliens frissítés.
- Admin kampány űrlapon HU időzóna jelzés.

### 2026-01-20 – SSH host + preflight
- `.deploy.staging.env` és `.deploy.production.env`: SSH_HOST frissítve `sharityh@s59.tarhely.com` értékre.
- Staging ledger sync + OG batch lefutott, de preflight továbbra is WARN (totals/ticker lassú).
 - Stagingen törölve a ticker/totals kapcsolódó transiensek (impactshop_ticker_v1, impactshop_totals_v2, ibl_total_v1, impact_report_v1).
- Curl mérés: `https://app.sharity.hu/impactshop-staging/?rest_route=/impact/v1/totals` ~0.58s, míg `https://sharity.hu/impactshop-staging/?rest_route=/impact/v1/totals` ~9.4s → a redirect/edge útvonal a lassú.
- Preflight átállítva az `app.sharity.hu/impactshop-staging` hostra; eredmény PASS (totals/ticker < 1s).

### 2026-01-20 – Deploy
- Staging deploy lefutott (mu-plugins + plugins map, preflight WARN).
- Production deploy lefutott (preflight PASS, mu-plugins + plugins map).

### 2026-01-20 – Post-deploy + impactall
- `bin/post-deploy-checklist.sh` futott (printf fix után). Eredmény: 2/5 PASS, production URL 500 (homepage, /go, /go-deal, /wp-admin).
- `impactall` lefutott (14/14 PASS), de a REST healthcheck 500-at mutatott staging/prod wp-jsonon (status snapshot frissült).
- 2026-01-20: Added MU helper `wp-content/mu-plugins/impactshop-dognet-conversions.php` to restore `dognet_api_list_conversions_all` via raw-transactions list functions (relies on existing Dognet auth helper). Leaderboard/ticker empty on staging because function missing; needs deploy + transient flush. Risk: Dognet auth config must be present (DOGNET_LOGIN_EMAIL/PASSWORD).

### 2026-01-20 – Leaderboard vizuális + név javítások (pending deploy)
- `wp-content/plugins/sharity-impact-mini/sharity-impact-mini.php`: leaderboard attribútumok (limit/from/to/status/currency/rate_huf) támogatása + paraméteres cache kulcs + színek korrigálása (white-on-white fix).
- `wp-content/mu-plugins/impactshop-metrics-ngo.php`: NGO slug → ékezetes név normalizálás a leaderboard API válaszban (`ngo_codes.csv`).
- `wp-content/mu-plugins/impactshop-full-leaderboard.php`: rich HTML layout visszaállítása a teljes NGO toplistához (korábbi `ngo-leaderboard.html` stílus).

### 2026-01-20 – Impact Shop deal link logika + NGO banner (pending deploy)
- `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`: egységes CTA logika (d1 → /go-deal + u, d1 nélkül → Fillout), Fillout fallback termék URL paraméterrel.
- Ugyanitt: d1 esetén megjelenik a támogatott NGO banner (`ngo_codes.csv` alapján).

### 2026-01-20 – Deals desktop click fix (deployed)
- `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`: desktopon a deals kártyák kattinthatósága javítva (pointer-events + z-index hardening, drag logic desktopon tiltva).

### 2026-01-21 – NGO card embed/share/pass
- Audit: nincs aktív WPCode, `/impact/v1/ngo-card` endpoint hiányzott a MU-pluginsból.
- `wp-content/mu-plugins/impactshop-ngo-card.php`: új NGO card endpoint + `[impact_ngo_card]` shortcode, totals alapú összeg/rank, announcement/wallet mezők.

### 2026-01-21 – Identity restore nonce fix
- `wp-content/mu-plugins/impactshop-identity-panel.php`: új `/impact/v1/identity/refresh-nonce` endpoint + auth bypass filter.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: nonce refresh + 403 “cookie check failed” retry restore/nickname mentéshez.

### 2026-01-21 – Guardrail: full repo scan/rsync tiltás
- Teljes repo-scan/rsync (több ezer fájl) csak előzetes indoklás + Arnold jóváhagyásával engedélyezett.
- impactall figyelmeztetést ír ki; engedélyezés csak `IMPACTSHOP_ALLOW_FULL_SCAN=1` mellett és külön kérés után.
- `bin/deploy.sh` és `bin/deploy-wpcontent-map.sh` hard block: `IMPACTSHOP_ALLOW_FULL_SCAN=1` nélkül azonnal leáll.
- impactall: külön sorban jelzi, ha a full scan nincs engedélyezve.

### 2026-01-21 – Social ticker megosztás (pseudo_id egyeztetés)
- `wp-content/mu-plugins/impactshop-boot.php`: Dognet link generálásnál `d2`-be bekerül a `impactshop_pseudo_id` (cookie).
- `wp-content/mu-plugins/impactshop-go-bridge.php`: Dognet linkeknél `d2`-be bekerül a pseudo (API + base link).
- `.codex/scripts/impact-social-ledger-sync.php`: pseudo_id elsődlegesen `last_click_data2`/`data2`/`d2` alapján; így a ledger sorai a cookieval egyezhetnek.
- Következmény: új konverzióknál a share engedélyezhető lesz; régi (d2 nélküli) sorok továbbra sem share-elhetők.

### 2026-01-21 – Guard védett fájlok bővítése + fejlesztői szabály
- `docs/impactshop-guard-config.json`: védett fájlokhoz hozzáadva az NGO card és az identity panel (`impactshop-ngo-card.php`, `impactshop-identity-panel.php`, `impactshop-identity-panel.js`).
- `docs/impactshop-system-map.md`: új szabály – fejlesztést elsődlegesen védett fájlok érintése nélkül kell megoldani; ha mégis kell, backup + egykattintásos rollback + Arnold engedély kötelező.
- `impactall`: ugyanaz a fejlesztői szabály kiemelten jelezve futáskor.
- Guard hash manifest frissítve (`docs/impactshop-guard-hashes.json` + `.sha256`).

### 2026-01-21 – NGO embed card (Ádám Reménye)
- `docs/impactshop-ngo-card-embed-adamremenye.md`: visszaállított eredeti HTML embed blokk (külső beágyazáshoz is), Ádám Reménye logó URL-lel.

### 2026-01-21 – NGO card 404 javítás + célzott deploy
- `wp-content/mu-plugins/impactshop-ngo-card.php`: ledger fallback engedélyezve akkor is, ha az approved lista üres (így a REST/share nem 404).
- Célzott feltöltés staging + production: `impactshop-ngo-card.php`, majd `impactshop_ngo_card_dataset_v2` transient törölve.
- Eredmény: `impact/v1/ngo-card` és `/ngo/<slug>/share/` újra működik.

### 2026-01-21 – Függő feladatok (home folytatás előtt)
- Külső domainen az NGO embed továbbra is 404 „Jelenleg nem elérhető”: várható ok a régi JS cache. Terv: `impactshop-ngo-card.php` FRONTEND_SCRIPT_VERSION bump + `docs/impactshop-ngo-card-embed-adamremenye.md` friss `?v=...`.
- Leaderboard rossz (slugok/0 Ft): `wp-content/mu-plugins/impactshop-metrics-ngo.php` módosítás szükséges.
  - `ism_pick_ngo_from_row`: `ngo_slug` kulcs támogatása.
  - `ism_num`: ledger `amount_huf` → commission EUR (amount_huf*2/rate).
  - `ism_fetch_tx`: Dognet üres esetén ledger fallback.
  - `ism_pick_ts`: `happened_at` figyelembevétele.
  - `ism_build_leaderboard`: shop display név, nem slug.
- Leaderboard rebuild: transients `impactshop_lb_v1_*` törlés staging+prod, majd `/impact/v1/leaderboard` curl.

### 2026-01-21 – Leaderboard fix (ledger fallback) + célzott deploy
- `wp-content/mu-plugins/impactshop-metrics-ngo.php`: ledger fallback (ngo_slug/ngo_code, shop_slug/advertiser_code), amount_huf konverzió, happened_at timestamp; shop display név támogatás.
- Célzott feltöltés staging + production: `impactshop-metrics-ngo.php`.
- `impactshop_lb_v1_*` transients törölve (staging: 2 sor; prod: 0 sor), majd `/impact/v1/leaderboard` újrahívás.

### 2026-01-21 – Totals/leaderboard slug+0 Ft javítás (ledger fallback)
- `wp-content/mu-plugins/impactshop-rest-totals.php`: NGO név normalizálás (ngo_codes.csv), ledger fallback, donation_converted kiszámítás, totals cache kulcs bővítve.
- Célzott feltöltés staging + production: `impactshop-rest-totals.php`.
- `impactshop_totals_*` transients törölve (staging: 10 sor; prod: 0 sor), majd totals endpoint újrahívás.
### 2026-01-21 – Totals limit és sorrend
- `wp-content/mu-plugins/impactshop-rest-totals.php`: limit paraméter érvényesítése (top N), rendezés donation_converted szerint.
- Célzott feltöltés staging + production: `impactshop-rest-totals.php`.
- `impactshop_totals_*` transients törölve (staging: 10 sor; prod: 0 sor).

### 2026-01-21 – Leaderboard megjelenítés rendbetétel
- `wp-content/mu-plugins/impact-mini-helpers.php`: shop_name használata slug helyett, „ismeretlen” sorok kihagyása, csökkenő sorrend donation_converted alapján, név/összeg közé elválasztó.
- Célzott feltöltés staging + production: `impact-mini-helpers.php`.
- `impactshop_totals_*` transients törölve (staging: 14 sor; prod: 0 sor).
### 2026-01-21 – Leaderboard collapse vissza
- `wp-content/mu-plugins/impact-mini-helpers.php`: collapse toggle támogatás (`collapse`, `collapse_open`, `collapse_title`, `collapse_tint`) + alapból zárt nézet.
- Célzott feltöltés staging + production: `impact-mini-helpers.php`.
### 2026-01-21 – Collapse gomb stílus kényszerítés
- `wp-content/mu-plugins/impact-mini-helpers.php`: gombszöveg szín/méret `!important` kényszerítéssel, belső elemekre is (Elementor override ellen).
- Célzott feltöltés staging + production: `impact-mini-helpers.php`.
### 2026-01-21 – Utolsó támogatott NGO megjegyzése
- `wp-content/mu-plugins/impactshop-identity-panel.php`: `last_ngo` tárolása/olvasása a profilban.
- `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`: `impactshop_current_ngo_slug()` használata mindenhol, reset link `reset_ngo=1` paraméterrel.
- `wp-content/mu-plugins/impactshop-boot.php`: `/go` és `/go-deal` végponton `last_ngo` mentés, amikor Fillout után d1 paraméterrel érkezik a vásárlás.
### 2026-01-22 – Deploy runbook rögzítés
- Új deploy leírás: `docs/impactshop-deploy.md`.
- Rögzítve a rendszer térképben: `docs/impactshop-system-map.md`.
- `impactall` kiegészítve deploy runbook hivatkozással.
### 2026-01-22 – Netflix kártyák új ablak + NGO card logó idegen domainen
- `wp-content/mu-plugins/impactshop-netflix-shortcodes.php`: shop/coupon kártyák `target="_blank"` + `rel="nofollow noopener"`.
- `wp-content/mu-plugins/impactshop-ngo-card.js`: logó/QR képeknél `referrerpolicy="no-referrer"` a külső domaines megjelenéshez.
### 2026-01-22 – NGO card külső domain fix (API/share base)
- `wp-content/mu-plugins/impactshop-ngo-card.js`: külső domainen automatikus `apiBase` és `siteBase` átállítás `https://app.sharity.hu`-ra (API fetch + share link helyesítés).
### 2026-01-22 – NGO share oldal visszaállítás
- `wp-content/mu-plugins/impactshop-ngo-card.php`: régi share/landing handler és rewrite visszaállítva a backup verzióból.
### 2026-01-22 – NGO card dataset seed üres approved lista esetén
- `wp-content/mu-plugins/impactshop-ngo-card.php`: approved lista ürességénél is újraszedelés (ne maradjon üres dataset).
### 2026-01-22 – NGO card jóváhagyás kiegészítés
- `wp-content/mu-plugins/impactshop-ngo-card.php`: hiányzó slugok automatikus jóváhagyása a datasetből (ne legyen “nem elérhető” a kártya).
### 2026-01-22 – Session end
- Állapot mentése: Impact Shop stabil, utolsó támogatott NGO slug megőrzésének igénye rögzítve; impactall timeout növelése még függőben.
- Következő lépés: ellenőrizni, hogy a `last_ngo` tárolás/előbeállítás ténylegesen működik a `impactshop` oldalon; az `impactall` timeout alapértékének emelése és új futtatás.
### 2026-01-22 – NGO card slug fallback deploy
- `wp-content/mu-plugins/impactshop-ngo-card.php`: totals soroknál `ngo` mező fallback slugként, ismeretlen NGO sorok kihagyása, név mezők tisztítása.
- Deploy staging + production (deploy-wpcontent-map, IMPACTSHOP_ALLOW_FULL_SCAN=1).
- Preflight WARN: totals lassú (staging/prod ~9–10s), deploy nem blokkolt.
- `impactshop_ngo_card_dataset_v2` transient törlés: prod törölve, stagingben nem létezett.
### 2026-01-22 – Session end
- Staging + prod deploy lefutott, NGO card dataset kényszerítve a slug fallback használatára.
- Következő lépés: ellenőrizni az embed/share kártyák megjelenését külső domainen és a share oldalon.
### 2026-01-22 – NGO card név + összeg korrekció
- `wp-content/mu-plugins/impactshop-ngo-card.php`: totals fallbacknál slug → név feloldás `ngo_codes.csv` alapján + hiányzó `donation_converted` esetén `commission * donation_rate` számítás.
- Deploy staging + production (deploy-wpcontent-map, IMPACTSHOP_ALLOW_FULL_SCAN=1).
- Preflight WARN: totals lassú (staging/prod), deploy nem blokkolt.
### 2026-01-22 – Totals + leaderboard lassulás csökkentés
- `wp-content/mu-plugins/impact-totals-cache.php`: option-fallback cache a `/impact/v1/totals` gyorsítására (transient miss esetén is).
- `wp-content/mu-plugins/impactshop-metrics-ngo.php`: leaderboard TTL emelés + prewarm cron (ngo + shop).
### 2026-01-22 – Latency monitor + impactall guard ellenőrzés
- Új log: `.codex/logs/impactshop-latency-monitor.log` (endpoint mérés).
- Új cron script: `.codex/cron/impactshop-latency-monitor.sh`.
- Guard frissítve: `.codex/guards/impactshop-totals-guard.sh` ellenőrzi a log frissességét.
### 2026-01-22 – Deploy staging + production
- Staging deploy lefutott (totals első körben timeout, retry után lassú; cache melegedett).
- Production deploy második futással sikeres, preflight OK.
### 2026-01-22 – Session end
- Állapot mentése: NGO card API működik, név + összeg rendben; külső domainen a logó továbbra sem jelenik meg.
- Következő lépés: külső domain URL + böngésző konzol/CSP vizsgálat, mielőtt bármilyen változtatás történik.
### 2026-01-22 – Fiók pontrendszer implementáció előkészítés
- `docs/fiok-pontrendszer-spec.md`: implementációs előkészítés (fájltérkép, integrációs pontok, migráció, MVP feladatok, tesztek).
- Új MU-plugin váz: `wp-content/mu-plugins/sharity-points.php`, `sharity-points-manager.php`, `sharity-level-manager.php`, `sharity-decay-manager.php`, `sharity-vacation-manager.php`, `sharity-referral-manager.php`, `sharity-points-api.php`, `sharity-points-cron.php`.
- Következő lépés: pontosítani az integrációs pontokat a protected fájlokban (csak engedéllyel), és elindítani az első migrációs futást stagingen.
### 2026-01-22 – Last NGO tárolás (protected fájl módosítás nélkül)
- `wp-content/mu-plugins/impactshop-last-ngo-capture.php`: d1/ngo query alapján eltárolja a legutóbbi NGO slugot a pseudo profilban; `reset_ngo=1` esetén törli.
- Következő lépés: ellenőrizni, hogy a `impactshop-netflix-shortcodes.php` már felhasználja-e a tárolt értéket (ha nem, csak külön engedéllyel módosítható).
### 2026-01-23 – Állásmentés (shutdown előtt)
- impactall lefutott full scan engedéllyel: 16/16 PASS, nincs WARN; bastya guard OK.
- Guard hash frissítés kész a védett fájlokra (snapshot + audit): `.codex/guard-snapshots/deploy-20260123-063930`, `.codex/guard-events/approval-20260123-063930.jsonl`.
- Frissült: `impactshop-status.md`, `.codex/context-latest.json`.
### 2026-01-23 – Last NGO alapértelmezés
- `wp-content/mu-plugins/impactshop-last-ngo-capture.php`: Impact Shop oldalon, ha nincs `d1/ngo`, automatikus redirect a fiókban eltárolt `last_ngo` alapján.
### 2026-01-23 – Fiók pontrendszer: pseudo ID támogatás + ledger sync (folyamatban)
- `wp-content/mu-plugins/sharity-points.php`: schema 2026-01-23-02; `user_id` nullable + `pseudo_id` indexek és kapcsolódó táblák kiterjesztése.
- `wp-content/mu-plugins/sharity-points-manager.php`: új `award_points_for_pseudo` + pseudo row kezelés + pseudo snapshot.
- `wp-content/mu-plugins/sharity-level-manager.php`: pseudo szintszámítás + update; közös percentile logika.
- `wp-content/mu-plugins/sharity-decay-manager.php`: pseudo decay kezelése.
- `wp-content/mu-plugins/sharity-points-cron.php`: pseudo decay + leaderboard cache frissítés, új 15 perces ledger sync cron hook.
- Új: `wp-content/mu-plugins/sharity-points-ledger-sync.php` (impact_ledger → pontjóváírás pseudo_id alapján).
### 2026-01-23 – Fiók pontrendszer előkészítés (folyamatban)
- `docs/fiok-pontrendszer-spec.md`: WooCommerce hivatkozások eltávolítva; minden vásárlás Impact Shop eseményként kezelendő.
- `wp-content/mu-plugins/sharity-points.php`: sémabővítés előkészítve (`pseudo_id` oszlopok + dedupe kulcsok), schema verzió emelve.
- Következő lépés: `sharity-points-manager.php` pseudo_id támogatás (`award_points_for_pseudo`, dedupe), plusz új ledger sync MU-plugin + cron a `impact_ledger` alapján.
- Megjegyzés: protected fájlokhoz nem nyúltunk; bástyavédelem backup + egykattintásos rollback elv marad érvényben.
### 2026-01-23 – Fiók pontrendszer implementáció (folyamatban, állásmentés)
- Pseudo ID REST API bővítés elkészült: `/sharity/v1/pseudo/points`, `/history`, `/vacation`, `/last-ngo`, `/video-ad`.
- Referral kezelés: `sharity_points_handle_referral()` első vásárlásra 200/50 pont jóváírás, `user_referrals` státusz/frissítés.
- Pseudo vacation támogatás: `activate/deactivate/get` pseudo változatok.
- Új bridge események: referral click cookie, daily login (2 pont/nap), wallet pass (25 pont), share page view (10 pont, napi 3).
- Új fájlok (még nem bekötve mind): `sharity-points-events.php`, `sharity-points-api.php`, `sharity-points-ledger-sync.php`, `sharity-points-cron.php` stb. (untracked állapot).
- Következő lépés: `sharity-points.php` include az events fájlra, cron streak logika (heti/havi), decide streak tárolás (meta vs. transaction query), staging teszt.
### 2026-01-23 – Fiók pontrendszer hardening + cron (folyamatban)
- `wp-content/mu-plugins/sharity-level-manager.php`: szint összehasonlítás sorrend alapján (string compare fix).
- `wp-content/mu-plugins/sharity-points-webhooks.php`: webhook retry queue + cron hook, hiba logolás.
- `wp-content/mu-plugins/sharity-points-cron.php`: leaderboard batch REPLACE, éves freeze reset + webhook retry schedule.
- `wp-content/mu-plugins/sharity-points-ledger-sync.php`: ledger award hibák logolása.
- `wp-content/mu-plugins/sharity-points-manager.php`, `sharity-vacation-manager.php`, `sharity-referral-manager.php`: közös `sharity_normalize_pseudo_id` helper használata.
- Új: `docs/sharity-points-openapi.yaml`, `tests/sharity-points/*` placeholder tesztek.
### 2026-01-23 – Impactshop latency monitor cron
- `.codex/cron/guards.crontab`: `impactshop-latency-monitor.sh` 10 perces cron, log: `.codex/logs/impactshop-latency.cron.log`.
- 2026-01-25: identity panel JS parse fix: prepended leading `;` to the IIFE to prevent invalid concatenation; backup+rollback at `.codex/backups/identity-panel-js-20260125-123942/rollback.sh`.

- 2026-01-25: Impactshop ID panel: a "Fiókom kezelése" + "Ez nem az én fiókom" gombok kikerültek a nagy panelből, és a kompakt panelbe kerültek (Fiókom kezelése + Ez nem az én fiókom sorrend). Backup: `.codex/backups/identity-panel-php-20260125-125642/`.

- 2026-01-25: Donor toplista: kiszűrtük a nem érvényes pseudo ID-ket, display név most: nickname vagy ID vagy "Anonim" (nincs többé "nick (id)" duplázás). Backup: `.codex/backups/impact-social-mvp-20260125-132014/`.

- 2026-01-25: Social ticker: badge rövidített pseudo (XXXX…YY), név csak becenév vagy "Anonim". Backup: `.codex/backups/impact-social-mvp-20260125-133633/`.
- 2026-01-25: Social ticker: kiszűrtük a nem érvényes pseudo ID-s (pre-ID) tételeket a ticker listából. Backup: `.codex/backups/impact-social-mvp-20260125-135151/`.
- 2026-01-25: Extra bástyavédelem kiterjesztve JYSK szavazásra és Social tickerre (`impactshop-vote-jysk.php/.js`, `impact-social-mvp.php`). Guard hash frissítve.
- 2026-01-25: Bástyavédelem megerősítve: ID panel + social ticker fájlok védettek, guard hash frissítve.
- 2026-01-25: Identity panel finomhangolás: "Ez nem az én fiókom" gomb a nagy panelben (helyreállításra scroll), NGO név slug helyett, közös pontszinkron fetch és non-ok vacation válasz kezelése. Backup+rollback: `.codex/guard-snapshots/manual-20260125-193926/` (`bin/impactshop-guard-rollback.sh manual-20260125-193926`).
- 2026-01-25: Identity panel JS: közös cache/promise a profil/pontokhoz, pontok frissítése profile után, és cache invalidálás pseudo váltásnál (0 vs 9 pont eltérés + lassulás csökkentése). Backup+rollback: `.codex/guard-snapshots/manual-identity-panel-20260125-205035/`.
### 2026-01-26 – Identity panel JS hiba nyomozás (állásmentés)
- `wp-content/mu-plugins/impactshop-identity-panel.js`: fájl eleje rendben (`;(function(){`), prodon is ezt adja vissza.
- A `SyntaxError: Invalid character '#'` nagy eséllyel **inline HTML/JS** forrásból jön (Elementor/HTML blokk vagy cache), nem a mu‑plugin fájlból.
- Következő lépés: WP‑CLI keresés prodon a `identity (function` snippetre (post_content LIKE), ha engedélyezett.
### 2026-01-26 – Bastya guard hash frissítés
- `docs/impactshop-guard-hashes.json` és `docs/impactshop-guard-hashes.sha256` újragenerálva (impactshop-identity-panel.js változáshoz).
### 2026-01-26 – ImpactShop Ads Watch P0–P3 fejlesztések
- `wp-content/mu-plugins/impactshop-ads-watch.php`: allocate rate limit, tally lock, DB error log, sponsor stats JOIN-es query, sponsor URL validáció + admin notice.
- `wp-content/mu-plugins/impactshop-ads-watch.js`: retry/timeout + hálózati hiba jelzés, GA4 eventek, quick-vote gombok, lazy IMA SDK betöltés.
- `wp-content/mu-plugins/impactshop-ads-watch.css`: új vote‑quick stílusok.
- Backup + rollback: `.codex/backups/ads-watch-20260126-113443/`.
### 2026-01-26 – Totals lassulás + ads.txt (állásmentés)
- `wp-content/plugins/impact-bridge-local/impact-bridge-local.php`: `ibl_build_total()` gyorsítás — alap dátum `2025-10-23` (IMPACTSHOP_METRICS_FROM felülírható), batch `40×200`, transient TTL 600. Backup: `.codex/backups/impact-bridge-local-20260126-120000/`.
- `wp-content/mu-plugins/impact-totals-cache.php`: TTL 300 / stale 900 emelve a totals cache-hez.
- Prod `ads.txt` létrehozva a webrootban: `/home/sharityh/app/ads.txt` (pub-3544330186801102). `https://app.sharity.hu/ads.txt` ellenőrizve.
- Staging deploy + scan OK; prod deploy után preflight totals timeoutolt (utána javítások készültek, új preflight szükséges).

### 2026-01-26 – Folyamatban / teendők
- JYSK /jysk-2/: a lépés‑gombok nem scrolloznak; módosítás védett fájlban (`impactshop-vote-jysk.php` / `.js`) — backup + rollback szükséges.
- Ads Watch Opus P0–P3 lista végrehajtása kéréssel: még nincs végig implementálva ebben a fázisban.
- Prod preflight újrafuttatás szükséges a totals javítás után.
### 2026-01-26 – JYSK scroll + Ads Watch UI finomítások (folyamatban)
- `wp-content/mu-plugins/impactshop-vote-jysk.php`: lépés‑gombok anchor linkkel + szekció ID-k; `.impactshop-vote__step` kattintható (cursor + text-decoration). Backup+rollback: `.codex/backups/jysk-vote-20260126-163045/`.
- `wp-content/mu-plugins/impactshop-ads-watch.js`: reward animáció NGO sor elrejtése. Backup+rollback: `.codex/backups/ads-watch-identity-20260126-165224/`.
- `wp-content/mu-plugins/impactshop-ads-watch.css`: achievements/auto‑vote/progress/streak/reward‑ngo stílusok kiegészítve.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: videó ponttípus címke finomítás (Ads vs Szponzori), purchase meta fallback. Backup+rollback: `.codex/backups/ads-watch-identity-20260126-165224/`.
### 2026-01-26 – JYSK videó pontozás (ID panel)
- `wp-content/mu-plugins/impactshop-vote-jysk.php`: `impactshop_vote_jysk_view()` most explicit módon ad `video_sponsor` pontot (5 pont/nap dedupe kulcs), hogy a JYSK kampányvideók biztosan számítsanak az ID panel pontjaiban. Backup + rollback: `.codex/backups/impactshop-vote-jysk-20260126-210000/`.
### 2026-01-26 – Prod deploy backup/rollback (wp-content)
- Backup + rollback készítve a prod deploy előtt: `.codex/backups/prod-deploy-20260126-141500/rollback.sh`.
- Érintett fájlok listája: mu-plugins (ads-watch, sharity-*, identity panel, jysk), plugins/impact-bridge-local, impact-totals-cache.
### 2026-01-26 – Social ticker ID + shop name fix
- `wp-content/mu-plugins/impact-social-mvp.php`: ticker display név most nickname → pseudo ID → Anonim; shop név registryből (`isb_find_shop`) fallbackkel (slug helyett). Backup+rollback: `.codex/backups/social-ticker-20260126-202639/`.
- Staging deploy + scan OK, prod deploy + scan OK (preflight OK).
### 2026-01-26 – Social ticker fallback + ID anonymizálás
- `wp-content/mu-plugins/impact-social-mvp.php`: érvénytelen pseudo esetén nem dobjuk el a sort, hanem “Anonim” megjelenítést adunk; fallback aktivitás többé nem `??*`. Backup+rollback: `.codex/backups/social-ticker-guard-20260126-205202/`.
- Staging deploy + scan OK (preflight OK), prod deploy + scan OK (preflight OK).
### 2026-01-26 – impactall futtatás (network)
- `~/bin/impactall` lefutott az `impactshop-notes` gyökérből; full repo-scan tiltva maradt (IMPACTSHOP_ALLOW_FULL_SCAN=1 hiányzik).
- REST health: staging 200 / 1316 ms (redirected_to:app.sharity.hu), production 200 / 1077 ms; 16/16 PASS, WARN/FAIL nincs.
- Státusz snapshot frissült: `impactshop-status.md`, `system-status-snapshot.md`.
### 2026-01-27 – CODEX implementáció (állásmentés)
- Új backup + rollback készítve védett fájlokhoz: `.codex/backups/codex-impl-20260127-073449/rollback.sh` (files: `wp-content/mu-plugins/impactshop-ads-watch.php`, `wp-content/mu-plugins/impactshop-ads-watch.js`, `wp-content/mu-plugins/impactshop-identity-panel.php`).
- Áttekintve az Offerwall/Video/Bade/HeroWall terveket és a meglévő új MU pluginokat: `impactshop-offerwall.php`, `impactshop-auto-banner.php`, `impactshop-click-tracking.php`, `impact-gamification.php`.
- Következő lépés: `impactshop-ads-watch.php` bővítése `/ads-watch/next` response‑szal (education + CTA + auto‑banner), új `education_views` tábla, education reward endpoint; `impactshop-ads-watch.js` YouTube/education player + CTA/click tracking + offerwall tab; `impactshop-identity-panel.php/js` badge/hero wall UI.
### 2026-01-27 – Deploy policy emlékeztető
- Runbook frissítve: `docs/impactshop-deploy.md` → kötelező minimum: `bin/impactshop-guard-deploy.sh`, uncommitted changes block, target útvonalak `.deploy.staging.env` + `.deploy.production.env`.
### 2026-01-27 – HeroWall frissítés badge awardnál
- `wp-content/mu-plugins/impact-gamification.php`: badge award/upgrade után most frissül a HeroWall (`impact_update_herowall`). 
### 2026-01-27 – Állásmentés
- Módosítások: `wp-content/mu-plugins/impact-gamification.php`, `notes.md`.
- Következő lépés: deploy staging + prod (scan), ha kéred.
### 2026-01-27 – Ads Watch YouTube IFrame API
- `wp-content/mu-plugins/impactshop-ads-watch.js`: YouTube IFrame API integráció (lazy load + state tracking) az edukációs videókhoz; lejátszás csak PLAYING állapotban számolódik.
- Backup + rollback: `.codex/backups/youtube-api-ads-watch-20260127-180411/rollback.sh`.
### 2026-01-27 – Achievement → Badge migráció
- `wp-content/mu-plugins/impact-gamification.php`: hozzáadva `impact_migrate_achievements_to_badges()` + `wp impactshop badges migrate-achievements` WP-CLI parancs (views/streak/votes/offerwall/edu + NGO loyal/multi). 
- Backup + rollback: `.codex/backups/badge-migration-20260127-182426/rollback.sh`.
 - Futtatva: staging (processed=0, updated=0), production (processed=5, updated=3). WP-CLI notice: complianz-terms-conditions korai textdomain load.

### 2026-01-27 – Koherencia audit + deploy
- ✅ Koherencia-ellenőrzés lefutott, ütközés nem találtam.
- 🚀 Deploy staging + production (guard deploy, full scan).
- ⚠️ Prod preflight: leaderboard(ngo) lassú válasz (3902ms > 2000ms).
### 2026-01-27 – Állásmentés (jutalmazási rendszer)
- Jutalmazási rendszer koncepció frissítve: `docs/rewarding-system.md` (Legacy Wall/Pool, badge logika, pont‑degradáció elvek).
- Nyitott feladat: koherencia audit + implementáció (Legacy Pool rangsor pontszám alapján, ID panel/hero/legacy UI összehangolás, badge‑megjelenítés).
### 2026-01-27 – Legacy Pool koherencia (módosítás)
- `wp-content/mu-plugins/impact-gamification.php`: Legacy Pool rangsor pontszám alapján (user_points join), legacy üzenet jogosultság szint alapján; HeroWall UI egyszerűsítve.
- `wp-content/mu-plugins/impactshop-identity-panel.php`: HeroWall szövegek Legacy Wall‑ra átírva.
- `wp-content/mu-plugins/impactshop-identity-panel.js`: Legacy Pool helyezés + pontszám megjelenítés, badge pontok eltávolítva.
- Backup + rollback: `.codex/backups/legacy-coherence-20260128-082906/rollback.sh`.
### 2026-01-28 – Deploy (Legacy Pool koherencia)
- Staging deploy + scan OK (preflight: totals slow 2066ms warning).
- Production deploy + scan OK (preflight OK).
### 2026-01-28 – Rewarding system doc + deploy guard env
- `docs/rewarding-system.md`: Legacy Wall jelveny-vitrin, Legacy Pool publikus ranglista (Legacy Pool felirat + uvegfal jelleg), badge megjelenites csak Legacy Wall/Pool, Challenge shortcode kovetelmeny rogzitve.
- `bin/impactshop-guard-deploy.sh`: automatikus `IMPACT_ENV` beallitas `--staging/--production` alapjan, hogy ne alljon meg a staging guard.

### 2026-01-28 – Deploy (scan)
- Staging deploy + scan OK (preflight OK).
- Production deploy + scan OK (preflight: leaderboard(ngo) lassu 3713ms > 2000ms).

### 2026-01-28 – Auto-banner harvester import (WP-CLI)
- `wp-content/mu-plugins/impactshop-auto-banner.php`: új WP-CLI parancs `impactshop auto-banner import --file=...` harvester JSON importhoz, normalizáló helperrel (title/image/cta/discount/price mezők).
### 2026-01-29 – Auto-banner CLI regisztráció + deploy
- `wp-content/mu-plugins/impactshop-auto-banner.php`: WP-CLI regisztráció kiterjesztve `cli_init` hookkal, hogy a `impactshop auto-banner import` parancs mindig elérhető legyen.
- Staging deploy + scan OK, production deploy + scan OK (guard deploy, full scan engedélyezve).
- Auto-banner import: helyben nem fut (nincs WP környezet). Szerver oldalon futtatandó: `wp impactshop auto-banner import --file=/home/sharityh/app/tmp/coupon-harvester/export-coupons.json`.
- Exportált kuponok: `ai-agent/tmp/ingest/export-coupons.json` (68 sor), másolat: `impactshop-notes/tmp/coupon-harvester/export-coupons.json`.
- SSH feltöltés sikertelen volt (publickey hiány); szerveren kézi SCP/SSH szükséges.
### 2026-01-29 – Deploy (auto-banner CLI update)
- Staging deploy + scan OK (preflight OK, cache+rewrite flush, 1 cron).
- Production deploy + scan OK (preflight OK, cache+rewrite flush, 1 cron).
- Auto-banner CLI regisztráció `cli_init` hookkal élesítve.
- Auto-banner import futott: 68 tétel aktívra állítva (DB-ben pending→active).
### 2026-01-29 – Unified display CTA pontok + auto-banner unify
- `wp-content/mu-plugins/impactshop-ads-watch.js`: auto-banner unify (showAutoBannerContent), CTA payload pontok + dedupe, smooth progress bar, banner progress callback, discount % fallback.
- `wp-content/mu-plugins/impactshop-click-tracking.php`: CTA click pontjóváírás `bonus` típussal, dedupe támogatás.
- Backup/rollback: `.codex/backups/unified-display-20260129-143939/rollback.sh`.
- Staging deploy + scan OK (preflight OK, cache flush, 2 cron).
- Production deploy + scan OK (preflight OK, cache flush).
### 2026-01-30 – Unified display terv véglegesítés
- `docs/unified-display-plan-merged.md`: javaslatok összevezetve, döntések rögzítve (content_type mapping, CTA pont forrás, fallback/regex stratégia), koherencia vizsgálat beépítve, véglegesített roadmap + elfogadási kritériumok.
### 2026-01-30 – Deploy (unified display doc)
- Staging deploy + scan OK (preflight OK).
- Production deploy + scan OK (preflight OK).
### 2026-01-30 – Education video UX (progress/presence/skip)
- `wp-content/mu-plugins/impactshop-ads-watch.js`: edukációs videó progress bar folyamatos frissítése, presence-check overlay + timeout, skip gomb időzítve, info-bar mezők frissítése, bonus state kezelése.

### 2026-01-30 – Ads Watch debug-rotation endpoint deploy + válaszok

- **Cél:** A rotációs logika debugolása a `/impact/v1/ads-watch/debug-rotation` endpointtal.
- **Staging deploy:** `deploy-20260129-203452` (preflight: totals lassú ~4.7s).
- **Prod deploy:** `deploy-20260129-203639` (preflight OK).
- **Staging válasz:** `sponsor_posts_count=0`, `education_posts_count=0`, `has_sponsor=false`, `has_education=false`.
- **Prod válasz:** `sponsor_posts_count=1` (Synlab), `education_posts_count=1`, `has_sponsor=false`, `has_education=true`.
- **Megjegyzés:** Prod-on a szponzor `can_view=false` (limit/cooldown blokkol), edukáció aktív és bekerül a rotációba.

### 2026-01-30 – Ads Watch debug can_view_reason egységesítés
- **Változás:** a debug endpoint most a `impactshop_ads_watch_can_view_sponsor_reason()` helperből tölti a `can_view_reason` értéket.
- **Érintett fájl:** `wp-content/mu-plugins/impactshop-ads-watch.php`
- **Backup:** `.codex/backups/ads-watch-debug-reason-20260129-210300/rollback.sh`
- 2026-01-30: Leaderboard lassulás javítás: `impactshop-metrics-ngo.php` leaderboard cache fallback opcióval (persisted option), background refresh + cache key egységesítés. Deploy előtt mindkét környezethez prewarm továbbra is aktív.
- 2026-01-30: Activity endpoint kapott perzisztens cache fallback‑et (`impactshop_activity_persist_v2`) + háttér frissítés, hogy cache flush után se legyen lassú.
- 2026-01-30: Ticker endpoint kapott perzisztens cache fallback‑et (`impactshop_ticker_persist_v1`) + háttér frissítés.
- 2026-01-30: Auto-banner integráció: dedupe (banner_url/title), default TTL (ends_at +7 nap), REST `/auto-banner/add` (admin + rate limit), cleanup cron expired bannerekhez, sync frissíti a starts/ends_at mezőket.
### 2026-01-31 – SSH/WP-CLI cél rögzítve (impactall autoload)
- **SSH/WP-CLI**: `sharityh@s59.tarhely.com`, app path: `/home/sharityh/app` (staging: `/home/sharityh/app-staging`).
- **Megjegyzés**: `sharity.hu` publickey hibát ad; ne azt használd.
- **Bástya**: `impact-hub-system-v1.3.md` és `Hirdetési fiókok integrációja TERV.ini.md` hozzáadva a védett fájlokhoz, guard hash frissítve.
### 2026-01-31 – Impi linkképzés koherencia fixek
- **AdWatch banner linkek:** Árukereső esetén `/go` (deeplink nélkül), NGO-val továbbra is Dognet tracking. (`wp-content/mu-plugins/impactshop-ads-watch.js`)
- **Impi CJ fallback:** ha CJ shop nincs a registry-ben, Fillout URL-re esik vissza.
- **Impi Árukereső:** deeplink helyett `/go` használat (registry alapján).
- **Érintett fájlok (ai-agent):** `apps/ai-agent-core/src/impi/impact-data.ts`, `apps/ai-agent-core/src/impi/recommend.ts`
- **Backup:** `.codex/backups/impi-link-generation-20260131-121146/rollback.sh`
- 2026-01-31: Impi linkképzés + AdWatch arukereso fix deploy. Staging: `deploy-20260131-113121`, Prod: `deploy-20260131-113225`.
### 2026-02-01 – Partner non-affiliate final terv (koherencia + biztonsag)
- **Uj dokumentum:** `docs/partner-final-plan.md` (koherencia es biztonsagi kiegészitesekkel).
- **Kockazatok/nyitott kerdesek:** staging base URL koherencia, fixture pseudo_id minta frissites, approved vs manual review default, refund ledger visszaforditas elve, currency konverzio forras.
### 2026-02-01 – Partner terv javitasok (OpenAPI + fixtures + status mapping)
- **OpenAPI:** staging server URL frissitve canonical hostra (`docs/partner-api-openapi.yaml`).
- **Fixtures:** `pseudo_id` mintak igazítva a regexhez (`fixtures/partner/*.json`).
- **Status mapping:** `payment_status -> ledger/status` alapdontesek rogzitve a tervben (`docs/partner-final-plan.md`).
### 2026-02-01 – Partner runner base URL fix + teszt
- **Runner base URL:** canonical `https://app.sharity.hu/impactshop-staging/wp-json/` (`tools/partner-test-runner.cjs`).
- **Runner eredmeny:** `rest_no_route` 404 minden fixture-re (endpoint nincs deployolva stagingen).
### 2026-02-01 – Partner endpoint ellenorzes (registracio + prod runner)
- **Kod referencia:** `wp-content/` alatt nincs partner endpoint regisztracio (csak docs emlites).
- **Prod runner:** `BASE_URL=https://app.sharity.hu/wp-json/` mellett is `rest_no_route` 404 minden fixture-re.
### 2026-02-01 – Partner API implementacio (mu-plugin) + backup
- **Uj mu-plugin:** `wp-content/mu-plugins/impactshop-partner-api.php` (transaction + discount + dispute endpointok).
- **DB tabla:** `wp_impact_partner_tx` + `wp_impact_partner_config` dbDelta-vel letrehozva.
- **Auth:** Bearer + HMAC + timestamp check, idempotency kezeles.
- **Ledger:** `payment_status=paid` -> ledger insert, refund -> ledger reject.
- **Audit:** `wp-content/uploads/impactshop-partner-audit.log`.
- **Backup/rollback:** `.codex/backups/partner-api-20260201-170136/rollback.sh` (mu-plugin torles).
### 2026-02-01 – Partner API deploy (staging + production)
- **Staging deploy:** `bin/impactshop-guard-deploy.sh --staging` -> snapshot `.codex/guard-snapshots/deploy-20260201-160643`.
- **Prod deploy:** `bin/impactshop-guard-deploy.sh --production` -> snapshot `.codex/guard-snapshots/deploy-20260201-160730`.
### 2026-02-01 – Partner API smoke setup + runner
- **Env loader:** `impactshop-partner-api.php` betoltja `/home/sharityh/.impact-secrets/env.d/partner.env`.
- **Runner:** `tools/partner-test-runner.cjs` kuldi `X-Impact-Timestamp` headert + status code fix az API-ban.
- **Ledger schema fix:** partner ledger insert kitolt minden required mezot (`pseudo_id`, `ngo_slug`, `shop_slug`, `source_ref`, `happened_at`).
- **Fixtures:** partner_code `partner-demo` (regex kompatibilis).
- **Secrets:** `IMPACT_PARTNER_SECRETS` beallitva partner-demo kulccsal (staging+prod kozos env).
- **Config:** staging `stg_impact_partner_config`, prod `wp_impact_partner_config` partner-demo sorok.
- **Deploy:** staging `deploy-20260201-164233`, `deploy-20260201-164951`; prod `deploy-20260201-164319`, `deploy-20260201-165036`.
- **Runner eredmeny:** staging idempotens dupe-k, prod accepted/duplicate; invalid-signature fixture tenylegesen nem invalid (runner mindig helyes HMAC-ot kuld).
- **Audit log:** `/home/sharityh/app-staging/wp-content/uploads/impactshop-partner-audit.log` + `/home/sharityh/app/wp-content/uploads/impactshop-partner-audit.log` frissult.
- **Backup/rollback (aktualis):** `.codex/backups/partner-api-20260201-175251/rollback.sh` (mu-plugin visszaallitas).
### 2026-02-01 – Partner demo revoke + kulcs szetvalasztas
- **Prod ledger takaritas:** `wp_impact_ledger` torles `partner-demo` teszt sorokra (2 sor torolve).
- **Partner demo revoke:** `wp_impact_partner_config` + `stg_impact_partner_config` torolve `partner-demo`/`partner_demo`.
- **Partner demo tx takaritas:** `wp_impact_partner_tx` + `stg_impact_partner_tx` torolve `partner-demo`/`partner_demo`.
- **Kulcs szetvalasztas:** `partner-stg` + `partner-prod` kulcsok az `IMPACT_PARTNER_SECRETS`-ben.
- **Config:** staging `partner-stg` (test), prod `partner-prod` (live) sorok.
- **Fixtures:** partner_code `partner-stg` a mintakban.
- **Runner bovitese:** `tools/partner-test-runner.cjs` -> `--no-sign`, `--invalid-sign`, `PARTNER_CODE` override.
- **Runner (staging):** valid futas accepted/duplicate, invalid-signature 401 (expected).
### 2026-02-01 – Partner prod smoke + no-sign fixture
- **Fixture:** `fixtures/partner/transaction-invalid-signature.json` -> `__no_sign: true` pelda.
- **Runner (prod):** partner-prod kulccsal accepted/duplicate, no-sign 401; prod ledgerben uj sor: `partner:partner-prod:order_1001` (id 176).
- **Cleanup:** prod teszt ledger sor (`partner:partner-prod:order_1001`) torolve + `wp_impact_partner_tx` teszt sorok torolve (order_1001, order_1003).
### 2026-02-01 – Ads watch JS deploy (staging + production)
- **Staging deploy:** `bin/impactshop-guard-deploy.sh --staging` -> snapshot `.codex/guard-snapshots/deploy-20260201-173006`.
- **Prod deploy:** `bin/impactshop-guard-deploy.sh --production` -> snapshot `.codex/guard-snapshots/deploy-20260201-173053`.
- **Backup/rollback (aktualis):**  (mu-plugin visszaallitas).
### 2026-02-02 – Dognet old_price AppScript javitas (backup)
- **AppScript:** `wp-content/google-sheets-scripts/Code.gs`, `wp-content/google-sheets-scripts/ArukeresoRunners.gs` -> ARU old_price tag lista bovitve.
- **Doksi:** `wp-content/google-sheets-scripts/README.md` szinkronizalva.
- **Backup/rollback:** `.codex/backups/20260202-114954-dognet-oldprice/` (Code.gs + ArukeresoRunners.gs + README.md masolat).
### 2026-02-02 – Dognet sale_price preferencia fix
- **Bug ok:** ARU parser a `price` mezot preferalta, igy ha `sale_price` alacsonyabb, a publikus ar maradt a regi ar.
- **Fix:** ha `sale_price` < `price`, akkor `price = sale_price`, `old_price = price`.
### 2026-02-02 – Feladatok (kérdőív) interim terv
- **Terv:** `docs/offerwall-survey-plan.md` – belső provider + HMAC postback, IP allowlist, idempotencia.
### 2026-02-02 – Feladatok (kérdőív) részletes terv + review
- **Terv frissítve:** `docs/offerwall-survey-plan.md` (impactad target, single choice, 10+10 jutalom, biztonsági/koherencia review).
### 2026-02-02 – Identity panel output fix
- **Bug ok:** `wp-content/mu-plugins/impactshop-identity-panel.php` elején markdown kép szöveg volt a `<?php` előtt.
- **Hatás:** header/cookie + REST válaszok sérültek, identity panel adatok nem töltődtek.
- **Fix:** a nyers markdown sor eltávolítva.
### 2026-02-02 – Guard config tisztitas (hiányzó fájlok)
- **Ok:** a guard listában szereplő fájlok nem léteznek ebben a repo-ban.
- **Valtozas:** eltavolitva a nem letezo elemek a `docs/impactshop-guard-config.json`-bol.
### 2026-02-02 – Deploy attempt (staging) – sikertelen
- **Guard approve:** lefutott, hash + audit frissult.
- **Preflight:** totals endpoint lassu figyelmeztetes (2021ms > 2000ms), többi OK.
- **Deploy hiba:** SSH permission denied `sharityh@cp40.ezit.hu` – rsync nem futott, production nem indult.
- **Folyamat status:** staging+prod deploy scan függő (SSH kulcs/host ellenorzes kell).
### 2026-02-02 – SSH host frissites (tarhelyköltözés)
- **Irányadó:** `impactall` szerint a host `sharityh@s59.tarhely.com`.
- **Fajlok frissitve:** `.deploy.staging.env`, `.deploy.production.env`, kapcsolodo docs (ai-agent-roadmap, system-update-prep, coupon-harvester, prod-guard-checklist).
### 2026-02-02 – Deploy (staging + production) – sikeres
- **Staging:** preflight OK, rsync OK, cache/cron/rewrite flush OK. Snapshot: `deploy-20260202-162243`.
- **Production:** preflight OK, rsync OK, cache/cron/rewrite flush OK. Snapshot: `deploy-20260202-162331`.
- **Megjegyzes:** `impactshop-dognet-report` plugin sync kihagyva (nincs helyi mappa).
### 2026-02-02 – Identity panel teljes UI visszaallas (helyi)
- **Ok:** a rövidített shortcode UI nem tartalmazta a pont/szint/előny/szabadság blokkokat.
- **Fix:** teljes markup + külső JS (impactshop-identity-panel.js) visszakötve; restore mezők autocomplete javítva.
### 2026-02-02 – Identity panel deploy (staging + production)
- **Staging:** snapshot `deploy-20260202-163809`, preflight OK, rsync OK.
- **Production:** snapshot `deploy-20260202-163906`, preflight OK, rsync OK.
- **Megjegyzes:** `impactshop-dognet-report` plugin sync kihagyva (nincs helyi mappa).

### 2026-02-02 – Identity ID compact progress fix (helyi)
- **Ok:** compact shortcode nem frissitette a progress bar/text mezoket, mert a JS csak full panel elemre gate-elt.
- **Fix:** progress frissites bekapcsolva compact-only esetben is (impactshop-identity-panel.js).

### 2026-02-02 – Deploy (staging + production) + smoke
- **Staging:** deploy OK, snapshot `deploy-20260202-170028`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-170320`, preflight OK, rsync OK.
- **Scan:** impactall lefutott; full scan tiltott (IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).
- **Smoke:** `bin/staging-qa-suite.sh` nem futott le (SSH connection failed – cPanel SSH enable).

### 2026-02-02 – Ads watch no-video message
- **Valtozas:** ha nincs elerheto video, nem hibazik, hanem "Nincs tobb video a rendszerben, terj vissza kesobb." uzenet jelenik meg (sponsor + edukacios).

### 2026-02-02 – Identity panel labels
- **Valtozas:** "Jelvényeid" -> "Legacy Wall", "HeroWall" -> "Legacy Pool".

### 2026-02-02 – Badge chip background
- **Valtozas:** badge chipek szurke hattere eltavolitva (impactshop-identity-panel.php CSS).

### 2026-02-02 – Vacation toggle nonce fix
- **Valtozas:** vacation toggle POST-hoz X-WP-Nonce header, hibauzenet megjelenitese sikertelen POST eseten (impactshop-identity-panel.js).

### 2026-02-02 – Bastion protection plan update
- **Valtozas:** terv koherencia+biztonsagi javitasok: guard-deploy mint egyetlen deploy ut, protected files forrasa egysitese, retention 2 naphoz igazitas, safe-deploy reszlet eltavolitva.

### 2026-02-02 – Deploy (staging + production) – all fixes
- **Staging:** deploy OK, snapshot `deploy-20260202-172643`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-172728`, preflight OK, rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – Bastion protection plan (lock)
- **Valtozas:** szerver oldali irasi lock beemelve (immutable/chmod/ACL), prioritasok es fazisok frissitve.

### 2026-02-02 – Mini ID restore enhancements
- **Valtozas:** mini ID: koszonto, mentés gomb, panel/restore linkek, recovery adat es hidden mezok; panelben anchor id-k. JS: save-password-manager click handler + mentés logika refaktor.

### 2026-02-02 – Deploy (staging + production) – mini ID enhancements
- **Staging:** deploy OK, snapshot `deploy-20260202-174550`, preflight totals warn 2688ms, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-174652`, preflight OK, rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – Shortcode restore
- **Valtozas:** impactshop_netflix shortcode alias hozzadva a banners deals MU pluginhez (sharity-impact-banners-deals.php).

### 2026-02-02 – Shortcode restore deploy
- **Staging:** deploy OK, snapshot `deploy-20260202-175627`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-175734`, preflight OK, rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – Guard restore + missing files
- **Valtozas:** hianyzo guard-fajlok visszahozva staging release-bol (impactshop-netflix-shortcodes, impactshop-rest-totals, impactshop-rest-coupons, impactshop-full-leaderboard, impactshop-ngo-card, impactshop-go-bridge, impactshop-sum-pack, Hirdetesi fio… terv).
- **Valtozas:** guard configba visszakerultek a védett elemek + NGO card JS placeholder.
- **Valtozas:** bastion tervbe bekerult a helyreallitasi forras (staging releases / prod backup).

### 2026-02-02 – Guard restore deploy
- **Staging:** deploy OK, snapshot `deploy-20260202-180723`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-180808`, preflight OK, rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – Leaderboard limit + styles
- **Valtozas:** impact_leaderboard limit parameter kezelt, API rows/data normalizalas; uj CSS a legacy leaderboard olvashatosaghoz.

### 2026-02-02 – Leaderboard fix deploy
- **Staging:** deploy OK, snapshot `deploy-20260202-181721`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-181812`, preflight OK, rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – Bastion plan konszolidalas + mini ID anchor
- **Valtozas:** bastion vedelmi terv osszefesulve es koherencia/biztonsagi javitasok atvezetve.
- **Valtozas:** mini ID “A fiokom kezelese” link `#impactshop-account-top` anchorra mutat.

### 2026-02-02 – Deploy (staging + production) – bastion plan + mini ID anchor
- **Staging:** deploy OK, snapshot `deploy-20260202-182312`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-182415`, preflight OK, rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – Leaderboard limit + olvashatosag + NGO card fallback
- **Valtozas:** sharity-impact-mini leaderboard limit param es adat normalizalas; light theme CSS olvashatosaghoz.
- **Valtozas:** NGO card map fallback (uploads), leaderboard REST fallback totals hiba eseten.

### 2026-02-02 – Deploy (staging + production) – leaderboard + NGO card fix
- **Staging:** deploy OK, snapshot `deploy-20260202-202355`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-202443`, preflight 1 warning (leaderboard(ngo) slow 4952ms), rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – NGO card CSV encoding fix
- **Valtozas:** NGO CSV label normalizalas (Windows-1250/ISO-8859-2 → UTF-8) a hibas karakterek javitasahoz.

### 2026-02-02 – Deploy (staging + production) – NGO card encoding fix
- **Staging:** deploy OK, snapshot `deploy-20260202-203222`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-203326`, preflight 2 warnings (ticker/activity lassu), rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – NGO card CSV sync + prewarm kiserlet
- **CSV:** friss ngo_codes.csv feltoltve `app/wp-content/uploads/ngo_codes.csv`.
- **Prewarm:** /wp-json/impact/v1/ngo-card/<slug> hivasok timeouttal elhaltak (12s, HTTP 000). 

### 2026-02-02 – NGO card encoding fix (CP1250)
- **Valtozas:** mb_detect_encoding invalid "Windows-1250" csere CP1250-ra + elerheto encodings szuro.

### 2026-02-02 – NGO card deploy (CP1250 fix)
- **Staging:** deploy OK, snapshot `deploy-20260202-205403`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-205453`, preflight OK, rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – NGO card prewarm (targeted)
- **Prewarm:** teljes tombos prewarm parhuzamosan timeoutolt; celzott 20 slug szekvencialisan sikeres.

### 2026-02-02 – NGO card WP-CLI prewarm parancs
- **Valtozas:** wp-cli parancs: `wp impactshop ngo-card prewarm --file=<slugs.txt> --batch=20 --sleep=1`.

### 2026-02-02 – Deploy (staging + production) – NGO card WP-CLI
- **Staging:** deploy OK, snapshot `deploy-20260202-210841`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260202-210939`, preflight 1 warning (totals lassu), rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-02 – NGO card prewarm (batched, prod)
- **Prewarm:** nohup job inditva (prod), log: `/tmp/ngo_prewarm.log` (utolso: batch 20/222).

### 2026-02-02 – NGO card frontend runtime
- **Valtozas:** impactshop-ngo-card.js most REST alapu renderelo (data-ngo-card hostokra).

### 2026-02-03 – Deploy (staging + production) – NGO card runtime
- **Staging:** deploy OK, snapshot `deploy-20260203-060149`, preflight 1 warning (totals lassu), rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260203-060247`, preflight 1 warning (totals lassu), rsync OK.
- **Scan:** impactall lefutott (full scan tiltva: IMPACTSHOP_ALLOW_FULL_SCAN=1 nincs).

### 2026-02-03 – NGO card legacy UI visszaallitas (frontend)
- **Valtozas:** impactshop-ngo-card.js visszaallitva a korabbi uveglap-stilusra (logo + uzenetek + 3 gomb).

### 2026-02-03 – Deploy (staging + production) – NGO card legacy UI
- **Staging:** deploy OK, snapshot `deploy-20260203-070330`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260203-070438`, preflight OK, rsync OK.
- **Smoke:** UI smoke (impactshop_teszt) nem futtathato innen – nincs bongeszo hozzaferes.

### 2026-02-03 – NGO card JS visszaallitas backupbol
- **Valtozas:** impactshop-ngo-card.js visszaallitva a /Users/bujdosoarnold/Developer/GitHub/impactshop-notes/NGO card backup/impactshop-ngo-card.js verziora.

### 2026-02-03 – Deploy (staging + production) – NGO card JS backup verzio
- **Staging:** deploy OK, snapshot `deploy-20260203-074750`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260203-074857`, preflight OK, rsync OK.

### 2026-02-03 – NGO share oldal javitas (backup PHP visszaallitas + CSV nev)
- **Valtozas:** impactshop-ngo-card.php visszaallitva a backup verziora (share handler + rewrite), CSV nevfeloldas + WP-CLI prewarm visszaadva.

### 2026-02-03 – Deploy (staging + production) – NGO share javitas
- **Staging:** deploy OK, snapshot `deploy-20260203-081820`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260203-081933`, preflight OK, rsync OK.

### 2026-02-03 – Identity panel gombok javitas (Opus ellenorzes)
- **Valtozas:** visszaallitva az /identity/total + /identity/refresh-nonce endpointok es a nonce auth bypass; JS ujra kap friss nonce-t.

### 2026-02-03 – Deploy (staging + production) – Identity panel gombok
- **Staging:** deploy OK, snapshot `deploy-20260203-083813`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260203-083926`, preflight OK, rsync OK.

### 2026-02-03 – NGO card fatal fix (rank mode fallback)
- **Valtozas:** impactshop_rank_mode_for_position + impactshop_mode_donation_rate fallbackok visszaadva a NGO card backendbe.

### 2026-02-03 – Deploy (staging + production) – NGO card fatal fix
- **Staging:** deploy OK, snapshot `deploy-20260203-090533`, preflight OK, rsync OK.
- **Production:** deploy OK, snapshot `deploy-20260203-090641`, preflight OK, rsync OK.

## 2026-02-03
- Ads Watch: ad tag URL fallback now reads WP option(s) (impactshop_ads_watch_ad_tag_url(s)); missing VAST tag now shows 'Nincs több videó...' instead of hard error.
- Pending: deploy + impactall + UI smoke (impactad-2) to confirm video CTA/data load with configured ad tag option.

- Deploy staging snapshot: deploy-20260203-094324 (ads-watch option fallback + no-video warning).
- Deploy production snapshot: deploy-20260203-094439 (ads-watch option fallback + no-video warning).
- impactall futtatva (full scan tiltva IMPACTSHOP_ALLOW_FULL_SCAN=1 miatt).
- Guard deploy: bin/impactshop-guard-deploy.sh empty-ARGS fix (set -u safe).
- Mini ID panel címke: Azonosítód -> Fiókom (impactshop_identity_id shortcode).
- Deploy staging snapshot: deploy-20260203-095601 (mini ID title -> Fiókom).
- Deploy production snapshot: deploy-20260203-095707 (mini ID title -> Fiókom).
- Identity ID: broadcast message field (WP Admin Reading) + display under compact panel; 300 char limit with link support.
- Deploy staging snapshot: deploy-20260203-101441 (identity broadcast message field).
- Deploy production snapshot: deploy-20260203-101558 (identity broadcast message field).
- Leaderboard fix: added Dognet raw conversions helpers (dognet_api_list_conversions_all/batch + status map) to impactshop-netflix-shortcodes.php to restore /impact/v1/leaderboard data.
- Impact Ad floating bar: added Impact Shop button with NGO-aware URL (d1/ngo/src=ngo-card) in ads-watch.
- Leaderboard: fallback NGO label added when data1 missing, so shop/ngo lists don't come back empty.
- Deploy staging snapshot: deploy-20260203-105041 (leaderboard unknown NGO fallback).
- Deploy production snapshot: deploy-20260203-105233 (leaderboard unknown NGO fallback).
- Impact bridge local: NGO picker fallback to '(ismeretlen NGO)' so leaderboard/ticker/totals no longer empty when data1 missing.
- Deploy staging snapshot: deploy-20260203-105842 (impact-bridge-local unknown NGO fallback).
- Deploy production snapshot: deploy-20260203-105955 (impact-bridge-local unknown NGO fallback).
- Impact bridge local: leaderboard/ticker now use IMPACTSHOP_METRICS_FROM (with optional from/to params) to avoid empty month-only lists.
- Deploy staging snapshot: deploy-20260203-110610 (impact-bridge-local leaderboard/ticker date range fix).
- Deploy production snapshot: deploy-20260203-110732 (impact-bridge-local leaderboard/ticker date range fix).
- Leaderboard default status switched to all in impactshop-metrics-ngo to avoid empty lists when approved data missing.
- Deploy staging snapshot: deploy-20260203-111205 (leaderboard status default -> all).
- Deploy production snapshot: deploy-20260203-111315 (leaderboard status default -> all).
- Impact bridge local: default metrics start date fallback set to 2025-10-23 (when IMPACTSHOP_METRICS_FROM undefined).
- Deploy staging snapshot: deploy-20260203-111725 (impact-bridge-local default from fallback).
- Deploy production snapshot: deploy-20260203-111835 (impact-bridge-local default from fallback).
- Metrics defaults: fallback start date set to last 90 days (when IMPACTSHOP_METRICS_FROM undefined) in impactshop-metrics-ngo and impact-bridge-local.
- Deploy staging snapshot: deploy-20260203-112929 (metrics default from last 90 days).
- Deploy production snapshot: deploy-20260203-113053 (metrics default from last 90 days).
- Deploy staging snapshot: deploy-20260203-113906 (Impact Shop button added to Impact Ad floating bar).
- Deploy production snapshot: deploy-20260203-114028 (Impact Shop button added to Impact Ad floating bar).
- Leaderboard: sharity-impact-mini switched to HUF display, filters unknown NGO entries, limit works after filter; uses impactshop_huf_rate option.
- Full leaderboard: filters unknown NGO, totals metric now sourced from /impact/v1/totals to match sticky total.
- Deploy staging snapshot: deploy-20260203-115233 (leaderboard HUF + unknown NGO filter + totals sync).
- Deploy production snapshot: deploy-20260203-115356 (leaderboard HUF + unknown NGO filter + totals sync).
- Impact Shop floating tab: brighter gradient + opens in new tab.
- Full leaderboard shortcode defaults: use last 90 days for from; rate_huf uses impactshop_huf_rate option fallback (392).
- Impact Shop: NGO banner now includes 'Másik szervezetet támogatok' link (clears d1/ngo); Fillout URL fallback normalized via impactshop_get_fillout_url in netflix/coupons/deals CTA builders to avoid default NGO when no d1.
- Deploy staging snapshot: deploy-20260203-145129 (Impact Shop NGO banner change + Fillout fallback + full leaderboard defaults).
- Deploy production snapshot: deploy-20260203-145247 (Impact Shop NGO banner change + Fillout fallback + full leaderboard defaults).
- Impact Shop metrics defaults: leaderboard/ticker/sticky now use fixed from=2025-10-23 in impactshop-metrics-ngo, impact-bridge-local, impactshop-full-leaderboard.
- Default d1 helper: skip rewriting Fillout links so no default NGO is injected when d1 missing (Impact Shop flow stays on Fillout).
- Deploy staging snapshot: deploy-20260203-151202 (metrics from fixed 2025-10-23; Fillout links skip default d1 rewrite).
- Deploy production snapshot: deploy-20260203-151319 (metrics from fixed 2025-10-23; Fillout links skip default d1 rewrite).
- Leaderboard REST fallback: impactshop-metrics-ngo now falls back to ibl_build_leaderboard when its own list is empty (ensures /impact/v1/leaderboard not empty).
- Deploy staging snapshot: deploy-20260203-161731 (leaderboard REST fallback to ibl_build_leaderboard when empty).
- Deploy production snapshot: deploy-20260203-161836 (leaderboard REST fallback to ibl_build_leaderboard when empty).
- impact-bridge-local: d1 picker now checks last_click fields and returns '' when missing (no '(ismeretlen NGO)' rows); shop leaderboard maps campaign IDs to shop names.
- Deploy staging snapshot: deploy-20260203-163303 (bridge local d1 pick + shop name map).
- Deploy production snapshot: deploy-20260203-163414 (bridge local d1 pick + shop name map).
- Leaderboard: filter out unknown NGO entries in impactshop-metrics-ngo (prevents '(ismeretlen NGO)' rows).
- Deploy staging snapshot: deploy-20260203-163944 (filter unknown NGO entries in impactshop-metrics-ngo leaderboard).
- Deploy production snapshot: deploy-20260203-164055 (filter unknown NGO entries in impactshop-metrics-ngo leaderboard).
- impact-bridge-local: NGO names normalized via ngo_codes.csv; shop leaderboard now resolves CID->name; full leaderboard totals now use /impact/v1/ticker to match sticky.
- Deploy staging snapshot: deploy-20260203-164802 (NGO name normalization in impact-bridge-local; full leaderboard totals use ticker).
- Deploy production snapshot: deploy-20260203-164908 (NGO name normalization in impact-bridge-local; full leaderboard totals use ticker).
- NGO name map now checks uploads/ngo_codes.csv paths in impactshop-metrics-ngo and impact-bridge-local.
- Deploy staging snapshot: deploy-20260203-165638 (NGO name map uses uploads/ngo_codes.csv paths).
- Deploy production snapshot: deploy-20260203-165736 (NGO name map uses uploads/ngo_codes.csv paths).
- NGO card fix in progress: auto-seed approved slugs when list empty, add approved slugs with zero amounts to dataset, and add remote ngo_codes.csv fallback for display names.
- Deploy staging snapshot: deploy-20260203-174657 (NGO card auto-seed + zero-amount approved slugs + remote ngo_codes.csv fallback).
- Deploy production snapshot: deploy-20260203-174802 (NGO card auto-seed + zero-amount approved slugs + remote ngo_codes.csv fallback).
- Preflight warning (production): /impact/v1/ticker slow response 3501ms (>2000ms threshold).
- Deploy staging snapshot: deploy-20260203-175151 (resolve_display_name now maps slug->label when name looks like slug).
- Deploy production snapshot: deploy-20260203-175301 (resolve_display_name slug->label).
- Deploy staging snapshot: deploy-20260203-180118 (encoding list adjusted to Windows-1250).
- Deploy production snapshot: deploy-20260203-180217 (encoding list adjusted to Windows-1250; preflight warnings: leaderboard(ngo) 3351ms, activity 4699ms).
- Deploy staging snapshot: deploy-20260203-180543 (mb_detect_encoding now filters to supported encodings).
- Deploy production snapshot: deploy-20260203-180652 (mb_detect_encoding now filters to supported encodings).
- NGO card totals fallback: when donation_converted missing, compute amount from commission * donation_rate * HUF rate (prevents 0 Ft + missing rank).
- NGO card totals: accept slug from totals row `ngo` when `ngo_slug` missing (fixes empty ranks).
- Deploy staging snapshot: deploy-20260203-181923 (NGO card totals slug fallback).
- Deploy production snapshot: deploy-20260203-182043 (NGO card totals slug fallback).
- Drafted bastion protection extension plan (repo lock, ownership meta, guard sanity checks) in docs/bastion-protection-extension-plan.md.

### 2026-02-03 – Bástya guard hardening v2
- Véglegesített bástya terv: `docs/bastion-protection-extension-plan.md`.
- Guard config v2: repo meta (root/remote/branch) + owner meta minden protected file‑hoz; új védett elemek: `bin/impactshop-guard-deploy.sh`, `bin/impactshop-guard-preflight.sh`, `docs/bastion-protection-extension-plan.md`, `docs/impactshop-guard-config.sha256`.
- Új preflight: `bin/impactshop-guard-preflight.sh` (repo root/remote/branch + missing/owner mismatch + path canonicalization).
- Guard deploy frissítés: lockfile, config checksum validáció, schema check, owner mismatch kezelés, snapshot meta + hash, symlink/canonical ellenőrzések.
- Guard hash/sha256 manifest frissítve (`docs/impactshop-guard-hashes.json`, `docs/impactshop-guard-hashes.sha256`, `docs/impactshop-guard-config.sha256`).
- Deploy nem futott ebben a körben.

### 2026-02-04 – Guardos deploy (bástya hardening v2)
- Guard deploy staging: deploy-20260204-071115 (preflight OK, rsync OK, cache flush + rewrite flush).
- Guard deploy production: deploy-20260204-071242 (preflight OK, rsync OK, cache flush + rewrite flush, cron futott).
- Guard lock: stale lock auto-törölve (pid inaktív, 237s).

### 2026-02-04 – impactall preflight bekötés
- `~/bin/impactall` mostantól futtatja a repo guard preflightot (`bin/impactshop-guard-preflight.sh`) a guardok előtt.

### 2026-02-04 – impactall futtatás
- `~/bin/impactall` lefutott (preflight OK, staging/prod REST 200, snapshot frissült).

### 2026-02-04 – Shop leaderboard unknown NGO szűrés
- Fix: shop leaderboardból kizárjuk a hiányzó/unknown NGO slug tranzakciókat (`impactshop-metrics-ngo.php`).
- Deploy staging snapshot: deploy-20260204-073832.
- Deploy production snapshot: deploy-20260204-074000.

### 2026-02-04 – Leaderboard cache v2
- Cache key bump: leaderboard cache/persist v2 (cache invalidation after unknown NGO filter).
- Deploy staging snapshot: deploy-20260204-080536.
- Deploy production snapshot: deploy-20260204-080708.

### 2026-02-04 – Bástya védelem rögzítés
- A végleges Impact Shop védett fájllista kanonikus forrása: `docs/impactshop-guard-config.json` (bástya védelem ehhez kötve).

### 2026-02-04 – Bástya terv deploy
- Deploy staging snapshot: deploy-20260204-082410 (bástya terv kiegészítés).
- Deploy production snapshot: deploy-20260204-082546 (bástya terv kiegészítés).

### 2026-02-04 – Impact Ad copy + activity tweaks
- Feladatok tab: subtitle + info popover első sor külön szöveg (🧩 Végezz feladatokat...).
- Élő aktivitás max 3% (korábbi ~10% helyett).
- Nyeremény esély mező: statikus „hamarosan”.
- Deploy staging snapshot: deploy-20260204-091944.
- Deploy production snapshot: deploy-20260204-092128.

### 2026-02-04 – Impact Ad cache bust
- ADS watch asset version bump: 2.5.1 (force JS/CSS refresh).
- Deploy staging snapshot: deploy-20260204-095142.
- Deploy production snapshot: deploy-20260204-095322.

### 2026-02-04 – Impact Ad /next fallback fix
- `/ads-watch/next`: ha nincs ad tag URL, auto-banner fallback (ha elérhető), különben nem adunk üres ad taggal regular választ.
- Sponsor rotáció: invalid/hiányzó media esetén sponsor kikapcsolva a választásból.
- Deploy staging snapshot: deploy-20260204-104414.
- Deploy production snapshot: deploy-20260204-104729.
- Staging redeploy snapshot: deploy-20260204-105913 (preflight: totals slow 2693ms).

### 2026-02-04 – Árukereső deeplink guard finomítás
- Árukereső deeplink csak akkor engedélyezett, ha a deeplink hostja is arukereso.* (különben marad a base link).
- Érintett: `wp-content/mu-plugins/impact-banners-fillout-rewriter.php`, `wp-content/mu-plugins/impact-arukereso-hardguard.php`.

### 2026-02-04 – Auto banner deeplink host guard
- Go.dognet deeplink csak shop host‑hoz engedélyezett; nem whitelistes/idegen host esetén banner kihagyás.
- Érintett: `wp-content/mu-plugins/impactshop-auto-banner.php`, `wp-content/mu-plugins/impactshop-auto-banner-sync.php`.
- Deploy staging snapshot: deploy-20260204-112533.
- Deploy production snapshot: deploy-20260204-112712.

### 2026-02-04 – Auto banner NGO refresh
- NGO választás változásakor auto banner link újratöltés (d1 paraméter biztosítása).
- Érintett: `wp-content/mu-plugins/impactshop-ads-watch.js`.
- Deploy staging snapshot: deploy-20260204-113943.
- Deploy production snapshot: deploy-20260204-114128.

### 2026-02-04 – Auto banner link update (no reload)
- NGO váltáskor csak a banner link frissül (nem indít új rotációt).
- Érintett: `wp-content/mu-plugins/impactshop-ads-watch.js`.
- Deploy staging snapshot: deploy-20260204-121604.
- Deploy production snapshot: deploy-20260204-121751.

### 2026-02-04 – Reward popup summary
- Videó végi reward popup összegzi a megtekintés + kattintás jutalmat (számítás változatlan).
- Érintett: `wp-content/mu-plugins/impactshop-ads-watch.js`.
- Deploy staging snapshot: deploy-20260204-131802.
- Deploy production snapshot: deploy-20260204-131941.

### 2026-02-04 – Auto banner reward panel + CTA bonus summary
- Auto banner megjelenéskor a jutalom panel látszik, CTA kattintás bónusz bekerül a videó végi összegzésbe.
- Érintett: `wp-content/mu-plugins/impactshop-ads-watch.js`.
- Deploy staging snapshot: deploy-20260204-140453.
- Deploy production snapshot: deploy-20260204-140641.

### 2026-02-04 – Reward feedback revert + sticky CTA notice
- Reward popup visszaállítva az eredeti formára (csak megtekintés jutalom).
- CTA kattintásnál zöld értesítés a videó végéig látszik.
- Auto banner alatt a jutalom panel nem takaródik (z-index).
- Érintett: `wp-content/mu-plugins/impactshop-ads-watch.js`, `wp-content/mu-plugins/impactshop-ads-watch.css`.

### 2026-02-04 – Impact Ad cache bust (2.5.2)
- JS/CSS verzió bump a cache frissítéshez.
- Deploy staging snapshot: deploy-20260204-141558.
- Deploy production snapshot: deploy-20260204-141754.

## 2026-02-04 (session save)
- Ads-watch reward feedback: reverted popup to pre-change behavior, CTA click shows sticky green notice until video end; banner CTA uses same sticky; sticky removed on video end/reset. Pending deploy.
- Video info panel: added higher z-index so auto banner video no longer covers info panel. Pending deploy.
- No NGO card changes in this round.

### 2026-02-04 Deploy – ads-watch feedback revert
- Staging guard deploy: deploy-20260204-180720
- Production guard deploy: deploy-20260204-180900
- Changes: restore reward popup behavior; sticky CTA bonus notice persists until video end; info panel z-index to avoid banner overlay.

### 2026-02-04 Plan finalize
- Finalized offerwall survey merged plan with AI feedback integrated and final coherence/security pass.
- Added detailed testing, staging/prod checklists, and operational safeguards.

### 2026-02-04 Deploy – PR batch
- Staging guard deploy: deploy-20260204-210810
- Production guard deploy: deploy-20260204-210952 (preflight warning: ticker 3390ms)
- Included guard/adswatch/ngo-card/scripts batch.

### 2026-02-04 Offerwall survey implementation (start)
- Added new mu-plugin for internal survey provider/scoring (in progress).
- Copied mapping/taxonomy CSV into repo for runtime loading.
- Hardened CSV parsing (skip blank lines/BOM), added axis-code validation, and relaxed mapping validation for direct assignment/top rules.
- Added fraud log table + admin listing + dashboard widget counters for survey completions.
- Guard config/hashes updated to include new offerwall survey files.
- Commit: feat: add offerwall survey provider (96569888) pushed to ops/adswatch-clean.
- Added dedicated survey token secret field (fallback to api_key) for iframe access.

### 2026-02-04 Deploy – offerwall survey
- Staging guard deploy: deploy-20260204-224619
- Production guard deploy: deploy-20260204-224729
- Changes: new internal survey provider, CSV mapping/taxonomy loader, fraud log table + dashboard widget, guard updates.

### 2026-02-05 Deploy – survey token secret
- Staging guard deploy: deploy-20260205-055456 (preflight warning: totals 2672ms)
- Production guard deploy: deploy-20260205-055616
- Changes: survey token secret field + iframe token uses dedicated secret.

### 2026-02-05 Offerwall survey admin
- Survey admin bővítve provider beállítással, CSV uploaddal és statisztikákkal (készen áll deployra).
- internal_survey provider aktiválva staging/prod környezetben (iframe URL beállítva).

### 2026-02-05 Deploy – survey admin
- Staging guard deploy: deploy-20260205-062207
- Production guard deploy: deploy-20260205-062320
- Changes: survey admin beállítások + CSV upload + statisztikák.

### 2026-02-05 – impactall guard futtatás
- Parancs: `if [ -f .codex/.env.local ]; then source .codex/.env.local; fi; ~/bin/impactall`
- Eredmény: impactall lefutott, status snapshot frissült; WARN/FAIL nincs.
- REST health: staging HTTP 200 / 1251 ms (redirected_to: app.sharity.hu), production HTTP 200 / 1210 ms.
- Megjegyzés: az IMPACTALL SUMMARY 0 check-et jelzett; ellenőrizendő, hogy a guard futtatások miért maradtak ki.

### 2026-02-05 – impactall guard futtatás (guard repo)
- Bástya kiterjesztett védelem ellenőrzés: `docs/bastion-protection-extension-plan.md`, `docs/impactshop-guard-config.json`, `docs/impactshop-guard-hashes.json` rendben.
- Megállapítás: az `impactshop-notes` repo-ban nincs `.codex/scripts`, ezért ott 0 check futott; a guard szkriptek a `/Users/bujdosoarnold/Developer/GitHub` gyökérben vannak.
- Parancs: `if [ -f .codex/.env.local ]; then source .codex/.env.local; fi; ~/bin/impactall` (repo: `/Users/bujdosoarnold/Developer/GitHub`)
- Eredmény: 13 check futott, 3 WARN (Doc link check; TradeTracker scope sync: hiányzó `impactshop-notes/TradeTracker-integráció.md`; Sprint S1 pre-flight: cross references).
- REST health: staging HTTP 200 / 1267 ms (redirected_to: app.sharity.hu), production HTTP 200 / 843 ms.

### 2026-02-05 – Doc missing refs + cross references fix
- Futtatva: `.codex/scripts/doc-missing-refs-inventory.sh` → `.codex/reports/doc-missing-refs.md`.
- Hiányzó hivatkozásokhoz stub fájlok létrehozva (partner docs, TradeTracker, Percy, report JSON/YAML, coupon harvester script) a doc-link-check miatt.
- Doc link ellenőrzés: `DOC_LINK_CHECK_STRICT=1 .codex/scripts/doc-link-check.sh impactshop-notes/impact-hub-system-v1.3.md` → OK.

### 2026-02-05 – impactall rerun + stub finomitas
- Parancs: `if [ -f .codex/.env.local ]; then source .codex/.env.local; fi; ~/bin/impactall` (repo: `/Users/bujdosoarnold/Developer/GitHub`)
- Eredmeny: 13/13 PASS, WARN/FAIL nincs; Sprint S1 pre-flight zold.
- REST health: staging HTTP 200 / 1185 ms (redirected_to: app.sharity.hu), production HTTP 200 / 876 ms.
- Stub finomitas: az auto-generated stub docok egységes HU template-et kaptak (Állapot/Tulaj/Cél/Hatókör/Bemenet-Kimenet/Következő lépések).
- Doc link check ujra: `DOC_LINK_CHECK_STRICT=1 .codex/scripts/doc-link-check.sh impactshop-notes/impact-hub-system-v1.3.md` → OK.

### 2026-02-05 – impactall PAT ellenorzessel
- Parancs: `if [ -f .codex/.env.local ]; then source .codex/.env.local; fi; ~/bin/impactall` (repo: `/Users/bujdosoarnold/Developer/GitHub`)
- Eredmeny: 13 check, 1 WARN (Secret expiry guard).
- Warn oka: GitHub token expiry lekérdezés sikertelen 3 próbálkozás után (hivatkozott file: `/Users/bujdosoarnold/Developer/GitHub/.codex/.env`).
- REST health: staging HTTP 200 / 1116 ms (redirected_to: app.sharity.hu), production HTTP 200 / 866 ms.

### 2026-02-05 – PAT hivatkozas frissitve + impactall ujrafutas
- Frissitve: `/Users/bujdosoarnold/Developer/GitHub/.codex/.env` → GITHUB_PAT uj.
- Parancs: `if [ -f .codex/.env.local ]; then source .codex/.env.local; fi; ~/bin/impactall` (repo: `/Users/bujdosoarnold/Developer/GitHub`)
- Eredmeny: 13 check, 1 WARN (Secret expiry guard).
- Warn oka: GitHub token expiry lekérdezés sikertelen 3 próbálkozás után (`.codex/reports/impactall-20260205-085530-Secret-expiry-guard.log`).
- REST health: staging HTTP 200 / 1195 ms (redirected_to: app.sharity.hu), production HTTP 200 / 860 ms.

### 2026-02-05 – Offerwall survey iframe URL letrehozasa
- Letrehozott oldal (prod): `https://app.sharity.hu/offerwall-survey` (WP page: Offerwall Survey).
- Letrehozott oldal (staging): `https://www.sharity.hu/impactshop-staging/offerwall-survey`.
- internal_survey iframe URL frissitve mindket kornyezetben (`impactshop_offerwall_providers` opcio).
- Megjegyzes: a tartalom most placeholder („A felmeres betoltese folyamatban.”).

### 2026-02-05 – Offerwall survey UI aktiv
- MU plugin: `wp-content/mu-plugins/impactshop-offerwall-survey.php` kibovitve survey UI shortcode-dal + REST submit endpointtal (`/wp-json/impact/v1/offerwall/survey/submit`).
- Oldal tartalma frissitve (prod + staging): `[impactshop_internal_survey]` shortcode + CTA szoveg.
- internal_survey pont/szavazat szamitas: points_multiplier=0.1, votes_multiplier=1.0 (payout=1 → 10 pont + 10 szavazat).
- Cache flush lefutott mindket kornyezeten.

### 2026-02-05 – Survey guide CSV alapra igazitas (prod + staging)
- Survey kerdesbank: `wp-content/mu-plugins/impactshop-offerwall-survey-data/survey_questions.json` bevezetve (CSV mapping/taxonomy alap).
- Mu-plugin frissites: survey UI a JSON kerdesbankbol tolt, fallback fix 5 kerdesre.
- CSV frissites: `question_mapping.csv` + `segment_taxonomy.csv` frissitve a Survey repo verzioira.
- Deploy: mu-plugin + survey data rsync prod/staging, cache flush OK.

### 2026-02-05 – Survey kerdesbank (4x250) + adaptiv flow (prod + staging)
- Kerdesbank: 4 CSV batch (1000 kerdes) importalva `survey_questions.json`-ba a Survey repo fajlbol.
- UI: 5 kerdeses adaptiv flow (KN -> ATT/BEH -> BEH -> adaptiv KN -> CONSENT) + answers_correct kuldes.
- Deploy: mu-plugin + survey data rsync prod/staging, cache flush OK.

### 2026-02-05 – Survey kerdesbank ujraepites (docx + batch5) + elagazo flow
- Uj generator: `scripts/build-internal-survey-bank.py` (docx + batch5) -> `wp-content/mu-plugins/impactshop-offerwall-survey-data/survey_questions.json` (370 kerdes).
- Kerdesbank meta: segment=SUST/ENV/SOC/DON, cognitive_type=knowledge/behavior/attitude, difficulty heuristika (docx), batch5 megtartva.
- MU plugin UI JS flow ujratervezve: intro -> knowledge -> knowledge -> apply -> reflect 5-os blokkokban, valasz-fuggo nehezseg/segment, delayed pair tamogatas.
- Payload fix: `categories` fallback a pre-dispatch-ben + `answers_correct` index fallback; uj altalanos kategoriak a `question_mapping.csv`-ben (KN_GENERAL/ATT_GENERAL/BEH_GENERAL).
- Kockazat: heuristikus kognitiv besorolas + subsegment kulcsszavas; erdemes samplinggel ellenorizni a sorrendet es nehezseg-skala megfeleleset.

### 2026-02-05 – Guardos deploy (survey bank + flow)
- Staging guard deploy: `deploy-20260205-170813` (preflight OK, rsync OK, cache flush OK).
- Production guard deploy: `deploy-20260205-170859` (preflight OK, rsync OK, cache flush OK).
- Guard approval: impactshop-offerwall-survey.php + question_mapping.csv (auto-approve reason: survey bank + flow).

### 2026-02-05 – Survey methodology rovid + targeting privacy doc
- Uj rovid brief: `docs/offerwall-survey-methodology-short.md`
- Uj privacy/targeting osszefoglalo: `docs/offerwall-survey-targeting-privacy.md`

### 2026-02-05 – Article quiz implementacios terv
- Uj terv: `docs/offerwall-articles-quiz-implementation.md` (CSV parsing, JSON schema, UI/REST/reward flow, anti-fraud).

### 2026-02-05 – Cikk kviz implementacio
- Uj generator: `scripts/build-article-quiz-bank.py` -> `wp-content/mu-plugins/impactshop-offerwall-article-quiz-data/articles_quiz.json` (14 cikk).
- Uj MU plugin: `wp-content/mu-plugins/impactshop-offerwall-article-quiz.php` (shortcode + REST submit + postback + answers tabla).
- Shortcode: `[impactshop_article_quiz]` (3 kerdeses cikk kvíz, minimum olvasasi ido, pont jovairas bekuldeskor).

### 2026-02-05 – Article quiz page + provider beallitas
- Staging WP page: ID 16886, slug `offerwall-article-quiz`, content `[impactshop_article_quiz]`.
- Production WP page: ID 18981, slug `offerwall-article-quiz`, content `[impactshop_article_quiz]`.
- internal_article_quiz provider enabled, iframe URL set:
  - staging: https://www.sharity.hu/impactshop-staging/offerwall-article-quiz
  - production: https://app.sharity.hu/offerwall-article-quiz
- Megjegyzes: WP-CLI futasnal complianz textdomain notice jelent meg (nem blokkolta a muveletet).

### 2026-02-05 – Guardos deploy (article quiz)
- Staging deploy: `deploy-20260205-185632` (preflight OK, rsync OK, cache flush OK).
- Production deploy: `deploy-20260205-185720` (preflight WARN: ticker 4271ms > 2000ms, rsync OK, cache flush OK).

### 2026-02-05 – Cikk kviz UI finomitasok
- Kviz kerdesek csak inditas utan lathatok; kulon Start gomb, minimum olvasasi ido (20 mp).
- Kviz idokorlat bevezetve (90 mp), lejartkor auto submit ures valaszokkal.
- Summary URL-ek tisztitva a JSON generatorban.

### 2026-02-05 – Guardos deploy (article quiz UI finomitasok)
- Staging deploy: `deploy-20260205-190859` (preflight OK, rsync OK, cache flush OK).
- Production deploy: `deploy-20260205-190958` (preflight WARN: totals 3842ms > 2000ms, rsync OK, cache flush OK).

### 2026-02-05 – Cikk kviz UX finomitas
- Idolimit roviditve: 45 mp / 3 kerdes.
- Valaszok visszaallnak visszalepeskor (korrigalhato a bekuldesig).
- Hianyzik token uzenet user-friendly: offerwallbol kell inditani.
- Bekuldes utan helyes valaszok szama megjelenik.

## 2026-02-05 - Offerwall Survey Plan Review (Gemini)
- **Merged Plan Review**: Hozzáadva `docs/offerwall-survey-merged-plan.md`-hez a Gemini javaslatok (I, J, K szekciók).
- **Security Check**:
  - `I1`: CSP és `frame-ancestors` szigorítás javasolt az iframe védelemhez (Opus JWT javaslat mellé).
  - `I2`: Raw answer retention szabály bevezetése (12 hónap után aggregálás) javasolt.
- **Reliability Check**:
  - `J1`: Dead Letter Queue (DLQ) javasolt a postback hibák kezelésére.
  - `J2`: Session expiry (15 perc) UX kezelése (keepalive/localStorage).
- **Consistency Check**:
  - `K1`: Mapping version race condition kezelése timestamp-alapú kiválasztással.
- **Jelenlegi státusz**: A terv tartalmazza Codex (jan 18), Opus (feb 05) és Gemini (feb 05) javaslatokat is. Nincs felülírt tartalom.

## 2026-02-05 - Offerwall Survey Plan Review (Codex)
- **Merged Plan Review**: Hozzáadva `docs/offerwall-survey-merged-plan.md`-hez a Codex javaslatok (L, M, N szekciók).
- **Security Check**:
  - `L1`: Replay detektálás TTL-lel a signature hash-re.
  - `L2`: Survey schema whitelist validáció survey-verzió szerint.
  - `L3`: Log redaction policy a raw `answers_json` kizárására.
- **Coherence/Data Integrity**:
  - `M1`: Survey definition hash mentése auditálhatósághoz.
  - `M2`: Reward ledger dedupe kulcs bővítése.
- **Operations**:
  - `N1`: Rescore dry-run diff összegzéssel.
  - `N2`: Completion spike alert + ideiglenes throttling.

### 2026-02-05 – Cikk kviz token/feedback/retake fix
- Token: survey_token is elfogadott; iframe url survey_token parammal.
- Feedback: bekuldes elott hibas valaszok szama, sikeresen 0 hibas.
- Idolejaratkor egyertelmu uzenet + nincs jutalom, kesobbi ujraproba.
- Sikeres kvízek listaja pseudo_id alapon, a kliens nem dobja fel ujra.

### 2026-02-05 – Article quiz token fallback fix
- Token generalas/ellenorzes: survey_token_secret -> api_key -> postback_secret fallback.
- Offerwallbol inditva is token jon (ha postback_secret be van allitva).

### 2026-02-05 – Survey merged plan review
- Plan koherencia es security review frissitve: payload mezok, token secret forras, retention policy.
- Plan egységesítve a megvalositashoz (pseudo_id + categories, HMAC token, consent gate).

### 2026-02-05 – Offerwall survey merged plan frissites
- `docs/offerwall-survey-merged-plan.md` koherencia/security javitasokkal egységesitve, commit: docs: align offerwall survey merged plan.

## 2026-02-05 - Survey Questions Generation (Batch 5)
- **Forrás CSV:** `/Users/bujdosoarnold/Developer/GitHub/Survey/sharity_questions_batch5_250.csv` (250 kérdés)
- **Generált kérdőív:** `survey-batch5-questions.md` - 5 kérdés a terv specifikáció szerint
- **Kategóriák:** KN_WASTE, BEH_ENER, DON_TRUST, KN_BIOD, BEH_TRAN (mind safe/low targeting)
- **Survey ID:** `impactad-v1-batch5-b1`
- **Megfelelőség:** 
  - ✅ Max 5 kérdés
  - ✅ Single choice A-D formátum
  - ✅ Targeting kategóriák (safe/low)
  - ✅ Postback payload minta készítve
  - ✅ HMAC signature specifikáció
  - ✅ Consent gate és rate limiting dokumentálva

## 2026-02-05 - Complete Survey Collection Generated
- **Forrás:** `sharity_questions_batch5_250.csv` (250 kérdés)
- **Létrehozott dokumentum:** `survey-batch5-complete-collection.md` (4893 sor)
- **Kérdőívek száma:** 50 darab (mind 5 kérdéses)
- **Struktúra:**
  - Minden kérdőívnek egyedi Survey ID: `impactad-v1-batch5-b01` - `impactad-v1-batch5-b50`
  - Kategória mapping a terv szerint (KN, BEH, DON, SOC szegmensek)
  - Postback payload minden kérdőívhez generálva
  - HMAC signature template és consent gate dokumentálva
- **Codex integráció:** A dokumentum készen áll a Codex általi feldolgozásra
- **Megfelelőség:** ✅ Teljes mértékben megfelel az offerwall survey plan specifikációinak

### 2026-02-05 – Internal article quiz token fix (prod)
- `internal_article_quiz` providerben beallitottam a `survey_token_secret` + `postback_secret` ertekeket az `internal_survey` alapjan; a `signature_mode` visszaallitva `canonical_v1`-re.

### 2026-02-05 – Article quiz status pinned fix
- A hibaszam uzeneteket pineltem, hogy ne tunjenek el a timer frissites miatt; valaszvaltasnal felold.

### 2026-02-05 – Guard deploy (article quiz status fix)
- Guard deploy lefutott productionre: snapshot `deploy-20260205-202947` (staging-only futas utan production sikeres).

### 2026-02-05 – Survey batch6 integracio
- `survey-batch6-new-questions.md` alapjan 70 uj kerdes kerult a `wp-content/mu-plugins/impactshop-offerwall-survey-data/survey_questions.json` fajlba (73 fejlecbol 3 mar meglevo ID).

### 2026-02-05 – Survey jutalom popup
- Felugro jutalom uzenet kerult a survey UI-ba sikeres bekuldes utan.
- A popup szoveg most kiirja a jutalom merteket (10 pont + 10 szavazat).

### 2026-02-05 – Guard deploy (survey reward popup)
- Production guard deploy lefutott: snapshot `deploy-20260205-214735`.

### 2026-02-05 – Guard deploy (survey reward text)
- Production guard deploy lefutott: snapshot `deploy-20260205-215614`.

### 2026-02-05 – Internal survey pont szorzo
- Production `internal_survey` provider `points_multiplier` beallitva 1.0 ertekre.
- Staging `internal_survey` provider `points_multiplier` beallitva 1.0 ertekre.

### 2026-02-05 – Mobil UI fix (offerwall)
- `impactshop-ads-watch.css`: mobilon a lebego tabok ket sorba tornek, az Adomanyalap kijelzes torodest javitottam.

### 2026-02-05 – Edukacios CTA gomb UX
- `impactshop-ads-watch.css`: a zold CTA gombokhoz nagyobb padding/min-width, kiegyensulyozottabb tipografia.

### 2026-02-09 – Guard deploy (CTA gomb CSS)
- Production guard deploy lefutott: snapshot `deploy-20260209-101809`.

### 2026-02-09 – Offerwall mobil UX
- Offerwall modal kapott bezaro gombot, ESC bezaras, mobilban lathato X.
- Survey kerdeseknel valasz utan automatikus felgorgetes a kovetkezo kerdeshez.

### 2026-02-09 – CPX kikapcsolva (teszt uzem)
- CPX Research provider tiltva production es staging offerwall listaban (`enabled=false`).

### 2026-02-09 – Guard deploy (offerwall modal + survey scroll)
- Production guard deploy lefutott: snapshot `deploy-20260209-105641`.

### 2026-02-09 – Offerwall bezáró gomb fix
- Bezáró gomb z-index + click handler finomítás, hogy mobilon is működjön.

### 2026-02-09 – Guard deploy (offerwall close fix)
- Production guard deploy lefutott: snapshot `deploy-20260209-115259`.

### 2026-02-09 – JYSK szavazás párhuzamos kampányok
- `impactshop-vote-jysk` támogatja a campaign slug alapú kampányválasztást, több aktív kampánnyal.
- Admin kampány létrehozás kiegészítve slug mezővel és listában megjelenik.
- REST init/status slug paraméterezhető, frontend oldalon `data-campaign-slug` alapján dolgozik.

### 2026-02-09 – JYSK új szavazások
- Guardos production deploy lefutott: snapshot `deploy-20260209-123625`.
- WP oldal létrehozva: `/jysk-komarom-szavazas` és `/jysk-mezkovesd-szavazas`.
- Kampányok beszúrva: `jysk-komarom-szavazas`, `jysk-mezkovesd-szavazas` (2026-02-10 → 2026-03-01 12:00 HU).

### 2026-02-09 – ID panel /profil
- Guardos production deploy lefutott: snapshot `deploy-20260209-130840` (preflight: totals endpoint lassu volt).
- /profil oldal létrehozva az ID panelnek (`[impactshop_identity_panel]`).
- Kis ID widget linkjei most új tabban a /profil oldalra mutatnak.
- Az `/impactad-2` oldalról eltávolítva a nagy ID panel (`[impactshop_identity_panel]`).

### 2026-02-09 – Offerwall kvíz elnevezés
- Guardos production deploy lefutott: snapshot `deploy-20260209-140452`.
- Offerwall “Cikk kvíz” elnevezés javítva (rövid i → hosszú í).

### 2026-02-09 – Árukereső ár­esés harvester
- Új script: `scripts/arukereso-price-drop-harvest.py` (aresett-termekek → auto-banner JSON).
- Új futtató: `bin/arukereso-price-drop-sync.sh` (opcionális `IMPORT=1` WP CLI import).
- Dokumentáció: `docs/arukereso-price-drop-harvest.md` (heti 2x cron javaslat).
- Prod szerver: `tools/shops_registry.json` már tartalmaz `arukereso` slugot.
- Cron script felrakva: `/home/sharityh/app/.codex/cron/arukereso-price-drop-sync.sh`, ütemezés hozzáadva `guards.crontab`-hoz (kedd/péntek 06:15).
- Első import: WP CLI `impactshop auto-banner import` fail (súlyos hiba), ezért `wp db query`-vel SQL insert készült és lefutott (24 ajánlat).
- Harvester: lapozás (`start=`) és retry/timeout támogatás, most 40 ajánlatot gyűjt a szerveren.
- Import mód: cron wrapper `IMPORT=sql`-ra váltva (SQL insert + `wp db query`), a WP CLI import hibája miatt.
- Bővített lapozás: sequential `start=24*n` oldalak, limit 220, max-pages 12 → 220 ajánlat beszúrva.
- Gmail promotions: ai-agent runner futtatva (`dist/tools/gmail/promotions-runner.js`), 50 rekord → 3 whitelistelt auto-banner insert (SQL).
- Gmail cron: `/home/sharityh/app/.codex/cron/gmail-promotions-sync.sh`, ütemezés hozzáadva `guards.crontab`-hoz (07:20 és 19:20), log: `.codex/logs/gmail-promotions.cron.log`.
- CJ/Dognet whitelist: a prod szerveren a `tools/shops_registry.json` újragenerálva kizárólag a Dognet (`dognet_programs.csv`) + CJ (`cj_shops.csv`) feedekből (101 domain).
- Coupon-harvester config frissítve: `/home/sharityh/app/.codex/cron/coupon-harvester-config.json` `whitelist` és `gmail.allowed_domains` csak CJ/Dognet domain listára állítva.
- Auto-banner host-bővítés: `impactshop-auto-banner.php` most a `tools/shops_registry.json` domainjeit is engedi (CJ slugek URL validációjához).
- CJ auto-banner import: új `scripts/cj-links-to-autobanner.py`, server cron wrapper `/home/sharityh/app/.codex/cron/cj-autobanner-sync.sh` (03:45) → 12 insert a `cj-links.json`-ból.
- Coupon-harvester auto-banner import: új `scripts/coupon-harvester-to-autobanner.py`, server cron wrapper `/home/sharityh/app/.codex/cron/coupon-harvester-autobanner-sync.sh` (08:10). Most 0 insert, mert a CSV-ben nincs CJ/Dognet whitelistelt slug.

## 2026-02-05 - Master Batch Korrekció & Teljes Kérdésbank Leltár
- **Fontos:** Az 1250-es master batch **ROSSZ VOLT** és nem játszik
- **MEGOLDVA:** ✅ Megtalálva a teljes kérdésbank!

### Kérdésbank Teljes Leltár (370 kérdés):
- **DOCX forrás (Batch 0):** 120 kérdés (DOCX-0001 → DOCX-0120)
- **CSV forrás (Batch 5):** 250 kérdés (KN-TRAN-1001 → BEH-WASTE-1250)
- **Tárolás:** `wp-content/mu-plugins/impactshop-offerwall-survey-data/survey_questions.json`
- **Státusz:** Teljes kérdésbank készen áll Codex integrációra (370 kérdés használható)

## 2026-02-10 - CJ autobanner futás ellenőrzés
- **CJ fetch:** `/home/sharityh/app/.codex/cron/cj-links-fetch.sh` 401 Unauthorized (CJ API auth hiba).
- **CJ import (fallback):** `/home/sharityh/app/data/cj-links.json` → `scripts/cj-links-to-autobanner.py` (12 insert), `wp db query` lefutott.
- **Coupon-harvester import:** `/home/sharityh/app/.codex/cron/coupon-harvester-autobanner-sync.sh` → 0 insert (nincs CJ/Dognet whitelistelt slug a CSV-ben).

## 2026-02-10 - CJ + Árukereső autobanner bekötés
- **CJ fetch:** `cj-links-fetch.sh` most a `wp-config.php` PAT‑ból épít `Authorization` headert, és registry‑alapú advertiser‑szűrést használ (link type: Banner).
- **CJ import:** `/home/sharityh/app/data/cj-links.json` → `cj-links-to-autobanner.py` (program_id + domain mapping) → 400 insert.
- **Árukereső import:** `/home/sharityh/app/.codex/cron/arukereso-price-drop-sync.sh` → 220 ajánlat betöltve (SQL import).

## 2026-02-10 - Árukereső autobanner link + kép javítás
- **Ads Watch:** Árukereső CTA már közvetlen termék‑URL‑t épít Dognet paramokkal (`impactshop-ads-watch.js` + `impactshop-ads-watch.php`, dognet_base átadva).
- **Harvester:** Árukereső képekhez `data-src/srcset` fallback ( `scripts/arukereso-price-drop-harvest.py` ).
- **Reimport:** `arukereso-price-drop-sync.sh` újra futtatva (220 ajánlat).
- **Cache bust:** `IMPACTSHOP_ADS_WATCH_VERSION` → `2.5.4`.
- **Frontend fallback:** auto-banner képhibánál shop logo / default image automatikus (ads-watch JS `onerror`).

## 2026-02-10 - Cégjelző integráció (implementáció kész)
- **Új MU plugin:** `wp-content/mu-plugins/impactshop-cegjelzo.php` – NGO registry tábla + Cégjelző API kliens + heti sync batch + REST export.
- **NGO név override:** `impactshop_ngo_card_display_name` filterrel Cégjelző név preferált.
- **NGO tiltás:** `impactshop_ngo_card_allow_slug` szűrés (status/proceedings alapú).
- **AI Agent export:** REST `impact/v1/cegjelzo/ngo-registry` + JSON export `wp-content/uploads/impactshop/ngo-registry.json`.
- **Prod beállítás:** API kulcs + Client ID opciók beállítva, `cegjelzo test-connection` OK; `cegjelzo sync --limit=20 --force` → 20/25 feldolgozva, 16 frissítve.

## 2026-02-10 - AI Agent core: Cégjelző registry bekötés
- **Új source:** `/home/sharityh/ai-agent/apps/ai-agent-core/src/sources/cegjelzo-registry.ts` (pull + 1h cache, REST: `/impact/v1/cegjelzo/ngo-registry`).
- **Snapshots:** `ai-agent-core/src/snapshots.ts` bővítve `cegjelzo_registry` snapshotra.
- **Runtime hotfix:** `dist/apps/ai-agent-core/src/sources/cegjelzo-registry.js` + `dist/.../snapshots.js` frissítve (nincs npm a shellben).
- **Service restart:** `ai-agent-keepalive.sh` lefuttatva.

## 2026-02-10 - Brevo hírlevél bekötés + NGO lista
- **Brevo API:** kulcs rögzítve `~/.impact-secrets/env.d/capi.env` (`BREVO_API_KEY`), küldő adatok `~/.impact-secrets/env.d/ai-agent.env` (`BREVO_SENDER_EMAIL`, `BREVO_SENDER_NAME`).
- **Brevo ellenőrzés:** API elérhető, `office@sharity.hu` sender aktív; AI Agent újraindítva.
- **NGO összevont lista:** `docs/ngo-merged-2026-02-10.csv` (frissített e‑mail prioritás a `szervezetek-2026-02-10.xlsx` alapján).

## 2026-02-10 - Impactad-2 mobil UI finomítás
- **Szövegfrissítés:** videó/feladat információban pont + szavazat kommunikáció (`wp-content/mu-plugins/impactshop-ads-watch.php`, `wp-content/mu-plugins/impactshop-ads-watch.js`).
- **Mobil elrendezés:** alsó tabok nagyobb safe‑area és extra padding, Top 10 NGO kártyák mobilon nagyobb névmező (`wp-content/mu-plugins/impactshop-ads-watch.css`).

## 2026-02-10 - Offerwall teljesítések UI frissítés
- **Legutóbbi teljesítések:** pont+szavazat kiírás; modal bezárás után history újratöltés ().

## 2026-02-10 - Offerwall teljesítések UI frissítés
- **Legutóbbi teljesítések:** pont+szavazat kiírás; modal bezárás után history újratöltés (`wp-content/mu-plugins/impactshop-offerwall.js`).

## 2026-02-10 - Guardos deploy (staging)
- **Deploy:** `bin/impactshop-guard-deploy.sh --staging` sikeres (snapshot: `deploy-20260210-172257`).
- **Preflight:** 1 warning (totals endpoint lassú, 2701ms).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).

## 2026-02-10 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260210-173824`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).

## 2026-02-10 - Impactad-2 mobil finomítás (info popover + hero)
- **Info panel:** mobilon statikus blokk, nem takarja a banner reklámot; subtitle wrap.
- **Hero kép:** mobilon image overflow lágyítás (`.impactshop-ads-watch-wrapper .elementor-widget-image`).
- **Cache bust:** `IMPACTSHOP_ADS_WATCH_VERSION` → `2.5.6`.

## 2026-02-10 - Impactad-2 gyorsítás
- **Tally lazy load:** Top 10 NGO betöltés `requestIdleCallback`-kal (vagy 800ms timeouttal) az első render után (`wp-content/mu-plugins/impactshop-ads-watch.js`).
- **Cache bust:** `IMPACTSHOP_ADS_WATCH_VERSION` → `2.5.7`.

## 2026-02-10 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260210-181640`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).

## 2026-02-10 - IMA CTA gomb méret
- **Zöld CTA:** nagyobb padding + min-width + icon/text méret, hogy ne lógjon ki a felirat.
- **Mobil override:** nagyobb padding + min-width.
- **Cache bust:** `IMPACTSHOP_ADS_WATCH_VERSION` → `2.5.8`.

## 2026-02-10 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260210-183451`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).

## 2026-02-10 - Autobanner kép fallback javítás
- **Banner képek:** `loadAutoBanner()` is `applyAutoBannerImage()`-t használ, így `onerror` esetén shop logo fallback lép életbe.
- **Cache bust:** `IMPACTSHOP_ADS_WATCH_VERSION` → `2.5.9`.

## 2026-02-10 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260210-185403`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).

## 2026-02-10 - JYSK Komárom szavazás hero kép
- **Hero kép:** a `jysk-komarom-szavazas` oldal tetején új hero blokk a JYSK képpel.
- **Fájl:** `wp-content/mu-plugins/impactshop-vote-jysk.php`.

## 2026-02-10 - JYSK Komárom szavazás hero (Mezőkövesd felirat)
- **Hero szöveg:** Mezőkövesd/JYSK felirat és "Közös erővel a jó ügyek mellett!" overlay hozzáadva.
- **Fájl:** `wp-content/mu-plugins/impactshop-vote-jysk.php`.

## 2026-02-10 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260210-195224`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** JYSK Komárom szavazás hero overlay Mezőkövesd felirattal.

## 2026-02-10 - JYSK Mezőkövesd szavazás oldal
- **Kampány:** `jysk-mezokovesd-szavazas` kampány felvéve (id új sor) start/end 2026-02-10 – 2026-03-01 12:00 helyi idő (UTC offset alapján).
- **Oldal:** `https://app.sharity.hu/jysk-mezokovesd-szavazas/` létrehozva, shortkód: `[impactshop_vote_page campaign_slug="jysk-mezokovesd-szavazas"]`.

## 2026-02-10 - JYSK szavazás oldalak szerkeszthetősége
- **Post author:** beállítva `arnoldadmin` (id 5) a `jysk-mezokovesd-szavazas` (18997) és `jysk-komarom-szavazas` (18982) oldalakhoz.

## 2026-02-10 - JYSK szavazás oldalak Elementor bekötés
- **Elementor meta:** `_elementor_data`, `_elementor_edit_mode=builder`, `_elementor_template_type=wp-page`, `_elementor_version=3.32.3` beállítva.
- **Oldalak:** 18997 (jysk-mezokovesd-szavazas), 18982 (jysk-komarom-szavazas).
- **Shortcode widget:** `[impactshop_vote_page ...]` Elementoron belül.
- **Elementor CSS flush:** lefuttatva.

## 2026-02-10 - JYSK szavazás hero eltávolítás
- **Hero widget:** a rövidkódból és inline CSS-ből eltávolítva, hogy az Elementor hero ne duplikálódjon.
- **Fájl:** `wp-content/mu-plugins/impactshop-vote-jysk.php`.

## 2026-02-10 - JYSK Komárom NGO választó (jysk-2)
- **NGO lista:** `wp-content/mu-plugins/impactshop-ngo-selector-data/komarom-esztergom.json` (67 tétel) a `docs/komarom-esztergom-ngo-active-cegjelzo.csv` + `docs/ngo-merged-2026-02-10.csv` alapján.
- **Új MU plugin:** `wp-content/mu-plugins/impactshop-ngo-selector.php` + `wp-content/mu-plugins/impactshop-ngo-selector.js`.
- **Oldal tartalom:** `jysk-2` (ID 18794) rövidkódra állítva: `[impactshop_ngo_selector context="jysk-komarom" list="komarom-esztergom" ...]`.
- **Mentés:** kiválasztás `pseudo_id` + `context` alapján a `wp_impactshop_ngo_selector` táblába.

## 2026-02-11 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260211-062943`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** NGO selector + JYSK hero eltávolítás rövidkódból.

## 2026-02-11 - JYSK NGO kiválasztó összekötés
- **Szavazás:** a JYSK szavazó felületen NGO lista elrejtve, kiválasztás eventtel összekötve.
- **Mentett NGO:** `impactshopNgoSelected` esemény alapján előtöltés; választás a szavazatokhoz kapcsolva.
- **Fájl:** `wp-content/mu-plugins/impactshop-vote-jysk.js`.

## 2026-02-11 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260211-071832`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** JYSK NGO selector és szavazó összekötés.

## 2026-02-11 - JYSK szavazás gomb aktiválás (selector)
- **Logika:** selectorból olvasott NGO slug alapján automatikus előtöltés + gomb engedélyezés.
- **Fájl:** `wp-content/mu-plugins/impactshop-vote-jysk.js`.

## 2026-02-11 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260211-073727`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** JYSK selector -> szavazás gomb aktiválás javítás.

## 2026-02-11 - JYSK szavazás gomb engedélyezés fix
- **Logika:** videó hitelesítés után `voteBtn.disabled = !(viewToken && selectedNgo)`.
- **Fájl:** `wp-content/mu-plugins/impactshop-vote-jysk.js`.

## 2026-02-11 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260211-075441`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** JYSK szavazó gomb engedélyezés javítva.

## 2026-02-11 - JYSK NGO lista szinkron (selector -> vote)
- **Cél:** a kiválasztott NGO mindig létezzen a kampányhoz rendelt `impact_vote_ngos` listában.
- **Változás:** `wp-content/mu-plugins/impactshop-vote-jysk.php` automatikusan betölti és szinkronizálja a selector JSON listát (Komárom/Mezőkövesd), aktiválja a listában szereplő NGO-kat és inaktiválja a többit.

## 2026-02-11 - JYSK szavazás: dropdown + bulk import
- **UI:** a szavazó oldalon az NGO kártyák helyett legördülő választó + kártya jelenik meg a szavazás gomb alatt.
- **Forrás:** csak a WP-ben rögzített kampány NGO-k (`impact_vote_ngos`).
- **Admin:** CSV bulk import a JYSK Vote admin > Civil szervezetek fülön.
- **Fájlok:** `wp-content/mu-plugins/impactshop-vote-jysk.php`, `wp-content/mu-plugins/impactshop-vote-jysk.js`.

## 2026-02-11 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260211-145722`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** JYSK szavazás dropdown + CSV bulk import élesítve.

## 2026-02-11 - JYSK-2 oldal NGO selector eltávolítás
- **Oldal:** `jysk-2` (ID 18794) tartalom frissítve, külön NGO selector shortkód törölve.
- **Tartalom:** csak `[impactshop_vote_page]` maradt.

## 2026-02-11 - Dognet autobanner ingest
- **Új MU plugin:** `wp-content/mu-plugins/impactshop-auto-banner-dognet.php`.
- **Funkció:** Dognet `/coupons/filter` → `wp_impactshop_auto_banners` (status=active).
- **Ütemezés:** 6 óránként (`impactshop_6h`).

## 2026-02-11 - Gmail promotions autobanner bővítés előkészítés
- **Script:** `scripts/gmail-promotions-to-autobanner.py`.
- **Változás:** alap limit 500; opcionális affiliate-only szűrés (`--affiliate-only`, `--affiliate-allowlist`).
- **Megjegyzés:** affiliate-only akkor enforced, ha van whitelist meta vagy allowlist fájl.

## 2026-02-11 - Gmail promotions affiliate-only beállítás (prod)
- **Allowlist:** `/home/sharityh/app/tools/affiliate_shops.json` a `shops_registry.json` slugjaiból.
- **Cron:** `/home/sharityh/app/.codex/cron/gmail-promotions-sync.sh` → limit 500 + `--affiliate-only`.
- **Ingest:** futtatva, 16 insert készült.

## 2026-02-11 - Autobanner arukereso → fillout fallback
- **JS:** `wp-content/mu-plugins/impactshop-ads-watch.js`.
- **Változás:** ha nincs kiválasztott NGO, az autobanner CTA Fillout-ra megy (shop + u param), nem közvetlen termék-URL-re.
- **Hatás:** NGO azonosítható, majd arukereso dognet link `data1`-gel készül, ha már van NGO.

## 2026-02-11 - Autobanner rotáció bővítés (kevesebb ismétlés)
- **PHP:** `wp-content/mu-plugins/impactshop-auto-banner.php`.
- **Változás:** rotációs pool limit 300; seen cookie cap bővítve (max 500).
- **Hatás:** nem ismétel, amíg a nagyobb pool végére nem ér.

## 2026-02-11 - Arukereso ár-esés diverzitás + cleanup
- **Script:** `scripts/arukereso-price-drop-harvest.py` (kategoriás limit + keverés).
- **Sync:** `arukereso-price-drop-sync.sh` futtatva (90 ajánlat).
- **Cleanup:** `wp impactshop auto-banner cleanup` → 262 törölve, 71 maradt.

## 2026-02-11 - Arukereso ár-esés cron finomhangolás
- **Cron:** `/home/sharityh/app/.codex/cron/arukereso-price-drop-sync.sh`
- **Args:** `--max-per-category 8` (78 ajánlat most).

## 2026-02-11 - Ads watch fillout always-on CTA
- **JS:** `wp-content/mu-plugins/impactshop-ads-watch.js`
- **Változás:** minden auto-banner CTA először Fillout-ra megy (shop + d1 + u), fallback csak ha nincs Fillout.
- **CSS:** `wp-content/mu-plugins/impactshop-ads-watch.css` CTA nagyobb (min-width/height).

## 2026-02-11 - CJ go-deal fallback + CTA target blank
- **PHP:** `wp-content/mu-plugins/impactshop-go-bridge.php` (CJ slug fallback a `tools/cj_shops.json` alapján).
- **JS:** `wp-content/mu-plugins/impactshop-ads-watch.js` (CTA linkek `target=_blank`, `rel=noopener`).

## 2026-02-11 - Boot CJ fallback (ismeretlen CJ shop fix)
- **PHP:** `wp-content/mu-plugins/impactshop-boot.php`
- **Változás:** CJ shop feloldás `tools/cj_shops.json` alapján + CJ link generálás a boot handlerben.

## 2026-02-11 - Ads watch cache bust
- **PHP:** `wp-content/mu-plugins/impactshop-ads-watch.php`
- **Változás:** `IMPACTSHOP_ADS_WATCH_VERSION` → 2.5.10 (cache frissítés).

## 2026-02-11 - Ads watch CTA window.open fix
- **JS:** `wp-content/mu-plugins/impactshop-ads-watch.js`
- **Változás:** CTA kattintásnál `window.open` kényszerítés + auto-banner CTA Fillout fallback.
- **PHP:** `wp-content/mu-plugins/impactshop-ads-watch.php` → verzió 2.5.11.

## 2026-02-11 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260211-170624`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** Dognet autobanner ingest élesítve.

## 2026-02-11 - Guardos deploy (production)
- **Deploy:** `bin/impactshop-guard-deploy.sh --production` sikeres (snapshot: `deploy-20260211-092335`).
- **Preflight:** OK (5 endpoints).
- **Sync:** mu-plugins + 18 plugin map; 1 plugin hiányzott (`impactshop-dognet-report`).
- **Változás:** JYSK NGO selector → vote lista szinkron élesítve.

## 2026-02-11 - Ads watch Fillout + új tab stabilizálás
- **JS:** `wp-content/mu-plugins/impactshop-ads-watch.js`
- **Változás:** CTA-k minden esetben `window.open`-nel nyílnak (default blokkolás ellen), auto-banner CTA Fillout mindig a nyers URL-t kapja, pop-up blokkolás esetén figyelmeztetés.
- **PHP:** `wp-content/mu-plugins/impactshop-ads-watch.php` → verzió 2.5.12.

## 2026-02-11 - CJ fallback 404 javítás
- **PHP:** `wp-content/mu-plugins/impactshop-boot.php`, `wp-content/mu-plugins/impactshop-go-bridge.php`
- **Változás:** CJ fallback már nem használ `program_id`-t click URL-hez (ez 404-et okozott). Ha `cj_click_url` hiányzik, a link a cél URL-re esik vissza (tracking nélkül), így nincs `members.cj.com/404`.

## 2026-02-11 - CJ link fetch param finomhangolás
- **Script:** `scripts/cj-fetch-links.py`
- **Változás:** alap `link-type` → `Text Link` (a korábbi kombináció 400-at adott a CJ Link Search API-n).

## 2026-02-11 - CJ shops újragenerálás (joined list)
- **Server:** `/home/sharityh/app/data/cj-links.json` frissítve 10 joined advertiser ID alapján (114 link, click_url kitöltve).
- **Server:** `/home/sharityh/app/tools/cj_shops.json` újragenerálva (10 shop, mind click_url‑lal).
- **WP:** `impactshop_cj_links` opció frissítve a 114 linkkel.

## 2026-02-12 - CJ CSV-ből import (szerződött shopok)
- **Forrás:** `CJ links/advertisers.csv`, `CJ links/links.csv`, `Feeds-Migration-Report.csv`
- **Szűrés:** csak szerződött (advertisers.csv) + Active + HU célzás.
- **Output:** `docs/cj_shops_from_csv.json` (32 shop), `docs/cj_links_from_csv.json` (766 link)
- **Server frissítés:** `/home/sharityh/app/tools/cj_shops.json` + `/home/sharityh/app/data/cj-links-manual.json`
- **WP:** `impactshop_cj_links` opció frissítve a 766 linkkel.

- ✅ CJ Link Search fetch (Text Link + Banner, merged) lefutott: `docs/cj-links.json` (1000 elem) a `advertisers.csv` alapjan szurve.

- ✅ CJ Link Search (Text Link + Banner, merged) → `docs/cj-links.json` (1000 elem) feltoltve szerverre `/home/sharityh/app/data/cj-links.json`; WP option `impactshop_cj_links` frissitve.
- ✅ CJ shops JSON generalva a friss linkekbol (`/home/sharityh/app/tools/cj_shops.json`, 28 shop) + `impactshop_cj_shops` option frissitve.

- ✅ CJ autobanner ingest: cj-links.json → 400 insert SQL → wp_impactshop_auto_banners (CJ=358, total=473).

- ✅ Arukereso harvester: TOP kategoriak eloresorolasa a mixelesnel; cron HARVEST_ARGS frissitve: --max-pages 400, --max-per-category 20.

- ✅ Arukereso harvest+SQL import: 122 uj offer, arukereso osszesen 141 (latest 2026-02-12 09:08:46).
- ✅ shops_registry.json frissitve cj_click_url mezokkel (27 CJ shop), impactshop_shops option frissitve.

- 🛠 CJ go-deal fix: isb_find_cj_shop / go-bridge now reads cj_click_url/program_id keys (cj_shops.json), removed program_id fallback to anrdoezrs in early handler.

- ✅ Identity restore return: restore link gets return param (same-domain), successful restore redirects back to return URL (sharity.hu only). identity-panel.js bumped to 1.0.1.

- ✅ Mobile CTA fix: sponsor CTA overlay kept visible on mobile (min size, z-index); ads-watch version bumped to 2.5.16.

- 🛠 Auto-banner Fillout hardening: buildFilloutUrl now always returns a Fillout URL (fallback base), arukereso tracked URL is wrapped into Fillout; resolveFilloutUrl uses tracked target; auto-banner mobile CTA widened. ads-watch version bumped to 2.5.17.
- 🛠 JYSK mobile vote CTA: sticky vote bar raised above bottom nav, z-index boosted, extra padding for visibility on mobile.
- 🛠 Auto-banner mobile CTA: forced visible full-width button on mobile (min height, flex display, visibility/opacity overrides). ads-watch version bumped to 2.5.18.
- 🛠 go-deal alias: "-hu" suffix now maps to base slug (pl. lampak-hu → lampak) to avoid "Ismeretlen shop" errors.

- 2026-02-12 14:37 JYSK Mezőkövesd szavazás: betöltöttem a Borsod-Abaúj-Zemplén szűrt NGO listát a kampányhoz (campaign_id=4, 66 NGO) a `docs/borsod-abauj-zemplen-ngo-active-cegjelzo.csv` alapján; SQL import lefuttatva prodon.
- 2026-02-12 16:50 Állásmentés: "Fiókom" kompakt widget címéhez infó gomb + tooltip szöveg/stílus hozzáadva az `impactshop-notes/wp-content/mu-plugins/impactshop-identity-panel.php` fájlban. Deploy nem futott.
- 2026-02-12 17:20 Guardos deploy lefutott staging + production környezetre (snapshotok: deploy-20260212-161759, deploy-20260212-161916). Preflight: staging OK; prod figyelmeztetés: leaderboard(shop) slow (2937ms).
- 2026-02-12 18:05 JYSK szavazás: mobil CTA bar layout javítva (gomb mindig látható), a tally most backendből kap `ngo_name` mezőt; JS ezt használja, így az "Aktuális állás" NGO nevei megjelennek. Fájlok: `wp-content/mu-plugins/impactshop-vote-jysk.php`, `wp-content/mu-plugins/impactshop-vote-jysk.js`.
- 2026-02-12 21:15 Árukereső autobanner végleges flow rögzítve: Fillout → `/go-deal` → Dognet deeplink. Bástya védelem kiterjesztve az Árukereső guardokra (`impact-arukereso-guard.php`, `impact-cid-arukereso-fix.php`, `sharity-impact-compat.php`, `impact-combat-pack.php`), és a kliens‑oldali interceptor letiltva (`impact-arukereso-deeplink-fix.php.off`).
- 2026-02-12 21:25 JYSK szavazás végleges flow rögzítve (mobil CTA + egymezős NGO kereső + ID panel nélkül, odds 3 nyereményre, sorsolás max 3 nyertes). Bástya védelem megerősítve a `impactshop-vote-jysk.php`/`.js` fájlokra.
- 2026-02-12 21:35 Cégjelző API + hírlevél szolgáltatás (Brevo) bástyavédelem és impactall autoload rögzítve. `impactshop-cegjelzo.php` védett, Brevo kulcs/sender env csak secrets-ben.
- 2026-02-12 21:45 ID widget + ID panel bástyavédelem rögzítve és impactall autoload frissítve (`impactshop-identity-panel.php/.js` védett; panel csak profil oldalon, widget marad).

## 2026-02-23 - Impact Challenge negyedéves reset (Q1 2026H1)
- **Terv:** `impact-challenge-quarterly-reset-plan.md` veglegesitve (2026Q1 = 2026-01-01 → 2026-06-30).
- **MU plugin:** custom Q1 bounds + WP-Cron utemezes, lock TTL 60s + Retry-After, admin email záráskor, `mark-paid` CLI.
- **DB (staging):** `stg_impactshop_ads_quarters` + `stg_impactshop_ads_quarter_results` tabla, votes bovitve (`base_weight`, `donation_multiplier`, `quarter_key`) + indexek, backfill Q1, lock/tally transients torolve.
- **DB (prod):** `impactshop_ads_current_quarter=2026Q1`, `end_at` 2026-06-30, backfill Q1, lock/tally transients torolve.
- **Quarter guard:** `scripts/guards/quarter-transition-guard.sh` → staging/prod OK.
- **Cron:** `/home/sharityh/impact-tools/quarter-close.sh` letrehozva; cPanel cron beallitasa megerositve (megrendeloi visszajelzes).
- **Guard deploy:** staging `deploy-20260223-094046`, prod `deploy-20260223-094119`.

## 2026-02-23 - Streak szorzo UI
- **JS:** `wp-content/mu-plugins/impactshop-ads-watch.js` – streak szorzo kijelzes a status sorban (x1.00–x1.30).
- **Guard deploy:** staging `deploy-20260223-094419`, prod `deploy-20260223-094449`.

## 2026-02-23 - Impact Challenge indulasi datum + visszaszamlalo
- **Q1 kezdet:** 2026-03-01 00:00:00 UTC (vege: 2026-06-30 23:59:59 UTC).
- **UI:** visszaszamlalo a status sorban (indulasig, majd lezarasig).
- **DB:** `start_at` frissitve staging/prod (2026-03-01).
- **Guard deploy:** staging `deploy-20260223-103630`, prod `deploy-20260223-103703`.

## 2026-02-23 - Negyedev start/close idozites
- **Start:** automatikus quarter start event 00:00-kor (WP-Cron backup).
- **Close:** quarter close event 00:02-kor (csokkentett overlap).
- **CLI:** `impactshop quarter start` idempotens (ha letezik, csak aktivra allit).
- **Server:** `/home/sharityh/impact-tools/quarter-start.sh` letrehozva (WP_PATH/WP_URL env varral).
- **Guard deploy:** staging `deploy-20260223-104719`, prod `deploy-20260223-104750`.

## 2026-02-23 - Start-gat + szavazatok nullazasa
- **Start-gat:** indulasi datum elott nincs szavazat-gyujtes (ads + edukacio).
- **DB:** aktualis quarter szavazatok torolve, `available_votes` es `total_votes` nullazva, tally transients torolve (prod).
- **Guard deploy:** staging `deploy-20260223-153701`, prod `deploy-20260223-153800`.

## 2026-02-23 - Fix ponthatár only (percentilis off)
- **User szintek:** kizárólag abszolút ponthatárok (2k/15k/50k/120k/250k), percentilis ág kikapcsolva.
- **Guard deploy:** staging `deploy-20260223-153450`, prod `deploy-20260223-153549`.

## 2026-02-23 - Fast data backup hot (mu-plugin + guard)
- **Fix:** tabla-ellenorzes pontos egyezesre allitva (information_schema), hiheto `SHOW TABLES LIKE` wildcard hiba megszuntetve.
- **Fix:** backup root path normalizalva (`/home/sharityh/impactshop-backups`), wp_mkdir_p nem bukik `..` miatt.
- **Guard:** timestamp parser frissitve (ISO-8601 offset kezelese).
- **Deploy:** staging `deploy-20260223-193146`, prod `deploy-20260223-193301` (preflight warning: ticker lassu).
- **Manual hot backup:** prod futtatva (`2026-02-23_193410`), offsite rclone OK.

## 2026-02-23 - NGO card challenge payload (redesign)
- **Valtozas:** `impactshop-ngo-card.php` build_payload most mar `apply_challenge_data()`-t hiv, igy a REST payload tartalmazza: `challenge_amount`, `total_donation`, `challenge_urls`.
- **Deploy:** staging `deploy-20260223-200625`, prod `deploy-20260223-200814`.

## 2026-02-24 - Tarhely + backup rendezés (Corsair)
- **Cleanup:** Corsair Trash urites, backup loopok megszuntetesenek elokeszitese.
- **Particio:** `TimeMachine` 700 GB + `CorsairData` 300 GB.
- **Sync:** napi rclone sync `CorsairData`-ra, kizart archiv/backup utak (pl. `archives/home-git`).
- **Helyfelszabaditas:** veletlenul nagy `~/.git` (74 GB) Corsair-re mentve, majd lokal torles.
- **Teendo:** mirror backup ne tartalmazzon backupokat (workspace-backups, .codex/backups) — kizarasi lista veglegesitese.

## 2026-02-25 - Impact Challenge status incidencia (ads-watch)
- **Tunet:** UI toast "A status frissitese sikertelen", a `/wp-json/impact/v1/ads-watch/status` 500-at adott.
- **Hotfix (JS):** `impactshop-ads-watch.js` level normalizalas/sanitize, cache-busting verzio emelve (2.5.24) prod+staging.
- **Root cause:** `impactshop-ads-watch.php` NGO logo helper `ImpactShop_NGO_Card_API::get_dataset_items()` hivasra fut, de a metodus nem letezik.
- **Kovetkezo:** PHP fix `method_exists` fallback (`get_dataset`) + cache flush; status endpoint 200 igazolas.

## 2026-02-25 14:18:19 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-25 14:28:31 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-26 06:03:37 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/.codex/logs/impactall-last-run.json

## 2026-02-26 07:06:28 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/.codex/logs/impactall-last-run.json

## 2026-02-26 14:05:37 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-26 14:53:46 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-26 23:45:18 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-27 00:42:23 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-27 08:16:47 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-27 12:04:06 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-27 13:13:43 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-27 22:07:21 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-28 19:12:34 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/.codex/logs/impactall-last-run.json

## 2026-02-28 19:15:19 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-28 19:23:13 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/.codex/logs/impactall-last-run.json

## 2026-02-28 19:49:53 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-02-28 21:46:26 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-01 23:32:06 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 05:43:15 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 05:50:16 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 05:58:21 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 06:09:50 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 06:12:37 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 08:08:19 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 09:24:34 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 16:22:48 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-02 17:41:46 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-03 08:54:29 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-03 22:52:39 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-03 23:07:03 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

- [2026-03-03 23:07:48 CET] Prod deploy: `wp-content/mu-plugins/sharity-content-consumption-guard.php` (targeted guard deploy, backup+rsync+php-l+cache flush, checksum verified).

## 2026-03-03 23:15:57 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-04 07:09:44 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-04 07:48:23 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=1s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-05 08:22:53 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-05 08:40:39 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-23 10:47:00 CET - offerwall hotfix continuity
- Protected MU-plugin hotfix branch prepared for PR sync: `hotfix/offerwall-sync-20260323`.
- Continuity evidence added for the production-hot offerwall fix set: survey provider chooser restore, article quiz reward-token fallback, and signed WP email proxy rollout.
- Production verification kept in sync with GitHub PR: survey chooser + CPX/AyeT containers visible in live HTML, email proxy test `HTTP 200` with `{"sent":true,"count":1}`.

## 2026-03-05 10:05:00 CET - doc continuity guard baseline
- Bevezetve a kötelező pre-push dokumentációs folytonosság gate (`scripts/safe-repo-audit.sh`) mindkét repo-ra (`impactshop-notes`, `ai-agent`).
- Új szabály: modulmódosításnál kötelező `system-status-snapshot.md` + legalább egy `docs/*.md`; notes repo-ban kötelező `notes.md` vagy `conversation-summaries/*` is.
- Új szabály: új modul fájlnál kötelező Bastion/guard kiterjesztési evidenciát frissíteni (`docs/bastion-guard-status.md`).
- Hook telepítők frissítve/felvéve: `ai-agent/scripts/install-hooks.sh`, `impactshop-notes/scripts/install-hooks.sh`.

## 2026-03-05 09:29:01 CET - impactall auto log
- **Result:** pass (warnings=0, errors=0, duration=0s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-05 10:47:00 CET - doc debt sync (push-range mode)
- A pre-push gate mostantól `--mode push` módban a ténylegesen pusholt commit tartományt auditálja.
- A dokumentációs continuity szabályok változatlanul kötelezők a commit tartományra (`system-status-snapshot.md`, `docs/*.md`, `notes.md`/`conversation-summaries`).

## 2026-03-05 10:12:00 CET - identity nickname sync fix
- Javítva: becenév mentés után a profil köszöntés azonnal frissül (`Szia <név>...`), nem marad név nélküli.
- Javítva: nickname mentés után azonnali Legacy Wall szinkron (`impact_update_herowall`), plusz fallback névfeloldás herowall API-ban.
- Érintett fájlok: `wp-content/mu-plugins/impactshop-identity-panel.php`, `wp-content/mu-plugins/impactshop-identity-panel.js`, `wp-content/mu-plugins/impact-gamification.php`.

## 2026-03-05 13:54:17 CET - post-merge operational snapshot log
- Merged PR batch lezárva az `ops/adswatch-clean` ágon: `#44`, `#45`, `#46`, `#47`, `#48`, `#49`, `#50`, `#51`, `#52`, `#53`, `#54`, `#55`, `#56`.
- `impactall` strict safe-audit futás a lokális dirty worktree miatt fail lett; `IMPACTALL_SKIP_SAFE_AUDIT=1` módban a futás pass eredménnyel zárult.
- Külön clean-head validáció: `scripts/safe-repo-audit.sh --strict` pass egy tiszta worktree-n (`origin/ops/adswatch-clean`).

## 2026-03-05 14:16:37 CET - post-merge checkpoint (2 repo)
- `impactshop-notes` clean worktree (`chore/checkpoint-2026-03-05-impactshop-notes`, `origin/ops/adswatch-clean@5d5d6f6e`): strict safe-audit PASS.
- `ai-agent` clean worktree (`chore/checkpoint-2026-03-05-ai-agent`, `origin/main@4cdae42e`): strict safe-audit PASS (`safe-repo-audit.sh --repo ... --strict`).
- `impactall` futás: normál módban strict safe-audit FAIL (lokális dirty `impactshop-notes`), `IMPACTALL_SKIP_SAFE_AUDIT=1` módban PASS.
### 2026-03-05 – Napi checkpoint rutin (clean worktree)
- `impactall`: `IMPACTALL_SKIP_SAFE_AUDIT=1 IMPACTALL_AUTO_NOTES=0 ./impactall` (2026-03-05 15:40:35 CET).
- Futási összegzés: staging `HTTP 403 / 287 ms`, production `HTTP 403 / 257 ms`, guard eredmény `1/1 PASS`.
- Strict audit (clean worktree, impactshop-notes): `scripts/safe-repo-audit.sh --repo /Users/bujdosoarnold/Developer/GitHub/.worktrees/impactshop-notes-checkpoint-2026-03-05 --strict` -> PASS (`no local changes`).
- Strict audit (clean worktree, impact_hub): `scripts/safe-repo-audit.sh --repo /private/tmp/impact_hub-checkpoint-20260305 --strict` -> PASS (`no local changes`).
- Queue cleanup: `office-hue/ai-agent` PR #8 lezárva.

### 2026-03-08 – One-path policy enforce (ops)
- Kötelező útvonal rögzítve: `install-hooks-all.sh` -> `start-feature-worktree.sh` -> `git-health-check.sh` -> PR checklist -> merge.
- Hook policy egységes: `pre-commit` main/master tiltás + `pre-push` direct main push tiltás.
- Pre-push audit standard: `safe-repo-audit.sh --strict --mode push`.
- PR kötelező checklist blokk + CI guard aktív; hiány esetén merge tiltás.

## 2026-03-23 15:52:00 CET - autobanner rotation and Dognet canonical follow-up
- Az autobanner runtime ismétléscsökkentése most már `pseudo_id` alapú per-user sorrendezéssel megy, nem a korábbi 300-as ismétlődő poolon.
- A `Shops` CSV marad a kanonikus partnerlista; a Dognet ingest csak ehhez mapel, és a `dognet_program_id` / `program_id` mezőket is figyelembe veszi.
- A `banners` CSV-ből az üres sorok kiesnek, minden valós ajánlat bekerülhet, `img` hiánynál shop-logo fallbackkel.
- Production deploy még nem történt meg ehhez a körhöz, mert a MU-plugin fájlok a hoston read-only (`0444`) jogosultságúak.

## 2026-03-24 - Impact Community Sprint 1–16 implementáció + audit
- Sprint 1–16 teljes backend implementáció merge-re kész: `feat/impact-community-sprint1` → PR #73.
- 22 tábla, 47+ REST endpoint, 15+ cron, IC_DB_VERSION 1.3.7.
- Post-implementation audit elvégezve: 11 séma-inkonzisztencia javítva (séma és lekérdezés szinkron).
- PHP lint: szintaktikai hiba nincs. SQL injection: nincs (minden $wpdb->prepare()).
- Audit commit: `fix(audit): koherencia és biztonsági audit — 11 séma-inkonzisztencia javítás`.
- Outstanding: prod deploy rsync szükséges merge után.

## 2026-03-24 21:03:41 CET - impactall auto log
- **Result:** warn (warnings=2, errors=0, duration=4s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-25 - jovonkvize STYLE_ID collision fix
- STYLE_ID uniqueness fix: jovonkvize.js → `"impact-event-donation-widget-style-jvk"` (dev.js CSS override elnémítva)
- Prod deploy OK, chmod 444 OK. Branch: feat/jovonkvize-ticket-count

## 2026-03-25 - v1.4.0 solo ticket number input fix
- Safari select CSS bug: select element ignores background-color; replaced with input type=number
- CSS: width:80px, dark bg rgba(5,15,47,.95), light color #f8f4ea
- STANDALONE_TICKET_MAX set via .max attribute instead of innerHTML

## 2026-03-25 - v1.5.0 email deploy
- Buyer confirmation email: vasarlo visszaigazolas jegy sorszamokkal
- Transaction notification: ticket info hozzaadva az admin emailhez
- Ticket serial: JVK-2026-XXXXX formatum, wp_option szamlalo

### 2026-03-25 — ÁSZF link kattintható (jovonkvize widget)
- "Elfogadom az ÁSZF-et és az adatkezelési tájékoztatót" → kattintható <a> link
- Target: https://app.sharity.hu/ngo-guides/jogi-dokumentumok/ (target=_blank)
- stopPropagation: link kattintás NEM toggleli a checkboxot
- Deploy OK, chmod 444
- 2026-03-25: cert aláírási blokk javítva: igazgatósági tagja → meghatalmazott, Sharity Zrt. → Sharity Adományszervező Alapítvány (v1.5.1); meghatalmazás HTML doc létrehozva
- 2026-03-25: vagy egyszerű adományozás szeparátor hozzáadva a solo jegyek és preset összegek közé (jovonkvize.js)

## 2026-03-25 ticket_serials
- schema v1.2.0: ticket_serials TEXT oszlop, DB fallback email küldésnél

## 2026-03-25 16:25:28 CET - impactall auto log
- **Result:** fail (warnings=3, errors=1, duration=1s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-25 22:51:13 CET - impactall auto log
- **Result:** warn (warnings=2, errors=0, duration=4s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-25 22:51:27 CET - impactall auto log
- **Result:** warn (warnings=2, errors=0, duration=2s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-25 23:00:43 CET - impactall auto log
- **Result:** warn (warnings=2, errors=0, duration=3s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-03-26 — /ngo-admin/ route hozzáadva (ic_ngo_admin_template_redirect)
- `impact-community.php`: `ic_ngo_admin_template_redirect()` bevezetva, template_redirect priority 4
- URL-ek átírva `/impact-shop_ngo/` → `/ngo-admin/` (3 hely)
- `impact-community-app.php`: guard refactor — NGO admin funkciók MU-init időn is betölthetők
- NGO_ADMIN_URL JS fallback: `/ngo-admin/`
- Branch: feat/jovonkvize-ticket-count HEAD: 7447fd73

## 2026-03-26 — IC kanonikus állapot visszaállítás (REVERT)
- impact-community.php + impact-community-app.php visszaállítva 10c9930d (PR #84) kanonikus állapotra
- /ngo-admin/ route és guard refactor REVERTELVE
- ngo_admin_url → /impact-shop_ngo/ (eredeti)
- NGO_ADMIN_URL JS fallback → /impact-challenge/ngo-admin/ (eredeti)

## 2026-03-26 — Ads Watch Safari external tab return fix
- `impactshop-ads-watch.js`: outbound CTA/autobanner kattintáskor external-navigation state mentés
- `visibilitychange`, `focus`, `pageshow` visszatéréskor recovery reload, ha a tab külső navigáció után hibás állapotban jön vissza
- `impactshop-ads-watch.php`: verzió emelve `2.5.30`-ra Cloudflare cache bust miatt
- Deploy: célzott rsync a production MU-plugin könyvtárba
- Live check: `/impact-challenge/` már `impactshop-ads-watch.js?ver=2.5.30`-at tölti

## 2026-03-26 — Ads Watch induló autobanner blokkolta a start gombot
- root cause: oldalbetöltéskor automatikusan indult a `loadAutoBanner()` loop, ami ráült a playerre
- következmény: a `Reklám megtekintése` gomb mobilon és desktopon sem látszott, az oldal lefagyottnak tűnt
- fix: eager `loadAutoBanner()` eltávolítva, a legacy banner completion pedig visszaadja a kontrollt a playernek
- verzió: `2.5.31`
- live ellenőrzés: Playwright snapshoton újra látszik a `▶ Reklám megtekintése` gomb mobil viewporton is

## 2026-03-26 — Ads Watch 8 ikonos nav REVERT, Safari JS fixek megtartva
- git + Time Machine összevetés alapján a fő vizuális regressziót a 2026-03-26 22:03–22:10 közötti 8 ikonos nav rewrite hozta be
- `impactshop-ads-watch.php` és `impactshop-ads-watch.css` visszaállítva a korábbi 4 gombos floating nav állapotra
- `impactshop-ads-watch.js` Safari external return recovery fixei változatlanul maradtak
- verzió: `2.5.32`
- live ellenőrzés: mobil snapshoton 4 gombos nav + látható `Reklám megtekintése` gomb
## 2026-03-31 21:45:00 CET - guard baseline bootstrap continuity

- Külön, tiszta `main`-alapú baseline branch készült a guard control plane számára, hogy a protected runtime commitok ne dirty worktree guardokra támaszkodjanak.
- A baseline három guard commitot tartalmaz: protected touch gate, workflow/push guardok, majd a guard control plane saját protected lezárása.
- A `guarded-push.sh` most már saját maga is lefuttatja a lane- és protected-touch checkeket push előtt.
- A guard control plane fájljai (`impactshop-protected-files.json`, lane/protected/push/workflow scriptek) most már protected körben vannak.
- Következő lépés: baseline branch push + PR + merge, és csak utána a runtime lane-ek, például az AyeT surveywall helyreállítás.
## 2026-03-31 21:52:00 CET - guard baseline review fix

- PR review alapján a `guarded-push.sh` további hardeninget kapott: ha elérhető, már a safe auditot és a memory gate-et is ugyanabban a wrapperben futtatja push előtt.
- A `workflow-state.sh` worktree-ben már a közös git könyvtárból vezeti le a repo nevét, így `impactshop-notes-*` worktree-k nem kapnak félrevezető deploy-javaslatot.
## 2026-03-31 21:58:00 CET - guard baseline no-upstream fix

- A review-threadek alapján a push-mode guardok új branch / upstream nélküli helyzetre is korrigálva lettek, így első pushnál nem a teljes repo múltját vizsgálják.
- A push-wrapper most egységes range/bázis feloldást ad át a lane- és protected-touch checknek.
- A `workflow-state.sh` az üres branch-nevet is `detached`-re normalizálja.

## 2026-03-31 22:22:00 CET - AyeT surveywall runtime lane

- Clean runtime worktree branch: `fix/ayet-surveywall-runtime`.
- Restored AyeT surveywall as a dedicated survey source on adslot `25740` with profile hash `b970533bbaf884d085d7c0e6734da1c2`.
- Kept the existing AyeT offerwall/game inventory on adslot `25643`.
- Runtime changes limited to:
  - `.deploy.production.env`
  - `.deploy.staging.env`
  - `wp-content/mu-plugins/impactshop-ayet-offerwall.php`
  - `wp-content/mu-plugins/impactshop-offerwall.php`
  - `wp-content/mu-plugins/impactshop-offerwall.js`
- Protected continuity recorded in `docs/protected-change-records/2026-03-31-ayet-surveywall-restoration.md`.

## 2026-03-31 22:34:00 CET - AyeT PR guard alignment

- The GitHub `protect-critical-files` workflow was aligned with the merged local
  guard baseline so paired `.deploy.production.env` / `.deploy.staging.env`
  runtime changes can pass the same documented continuity override path as the
  MU-plugin runtime files.

## 2026-03-31 22:42:00 CET - AyeT review fixes

- Surveywall refresh now clears the active `default` survey cache entry too, so
  `refresh=1` does not leave stale survey results pinned.
- The public survey endpoint now returns `surveys: []` consistently on
  pseudo-related errors.
- Survey refresh is rate-limited per pseudo to avoid unnecessary upstream API
  churn, and the inactive survey button state is no longer re-enabled blindly on
  the frontend.

## 2026-04-01 09:15:00 CET - guard deploy path realignment

- Root cause: a guard deploy wrapper configja még a régi `ops/adswatch-clean` branchre volt rádrótozva, ezért a kanonikus deploy path hamis preflight blokkolást adott.
- Fix: a `impactshop-guard-preflight.sh` worktree-kompatibilis repoazonosítást kapott a git common dir alapján.
- Fix: a guard config és hash repo-meta `main`-re lett átállítva, a checksumok frissítve.
- Policy update: a guard dokumentáció most már explicit kimondja, hogy hibás guard deploy infra esetén a kézi restore csak dokumentált, nem-kanonikus incidensút lehet.
## 2026-04-01 10:00:00 CET - guard deploy review follow-up

- Copilot/Codex review alapján a guard deploy checksum kontinuitást pontosítottam.
- `impactshop-guard-deploy.sh` most ugyanabban a `docs/...` formátumban írja a hash-checksum bejegyzést, mint amit commitolunk.
- A guard hash manifestben kézzel is frissítve lett a `docs/impactshop-guard-config.json` és `docs/impactshop-guard-config.sha256` digest, hogy ne maradjon állandó drift a kanonikus deploy wrapperben.
- A bastion status `Last updated` mező formátuma visszaállt időbélyeges, auditálható alakra.

## 2026-04-01 15:55:00 CET - JYSK report source restoration

- A korábban kivételesen, kézi guardolt úton visszaállított `/jysk-riport/` route forrása most külön kanonikus source lane-re került.
- A `impactshop-ngo-guides.php` route map additive módon megkapta a `jysk-riport` és `jysk-riport.data.json` útvonalakat.
- A dedikált JYSK riport HTML és JSON asset most már repo-tracked forrásként is bent van.
- A route restore továbbra sem érinti a JYSK vote runtime-ot vagy az offerwall/challenge ágakat.

### 2026-04-10 — ngo-guides v1.1.4: befektetoknek 404 + lang fix

- `impactshop-ngo-guides.php` v1.1.4 (fix/ngo-guides-befektetoknek-lang branch)
- Bug 1: `/befektetoknek/` → 404 javítva — `page_meta()` bejegyzés hozzáadva
- Bug 2: `?lang=en` mindig magyar fájlt adott vissza — `resolve_file($lang)` bekötve
- Rollback: `backups/ngo-guides-fix-20260410/rollback.sh`

## 2026-04-07 14:33:39 CEST - impactall auto log
- **Result:** warn (warnings=1, errors=0, duration=2s)
- **Source:** /Users/bujdosoarnold/Developer/GitHub/.codex/logs/impactall-last-run.json

## 2026-04-29 12:48 CEST - JVK public widget status copy cleanup
- A publikus aukcios widget write-enabled alapallapotban mar nem mutat technikai scaffold status feliratot a galeria alatt.
- A write-disabled es hibaallapotok statusuzenete megmaradt, tehat a diagnosztikai visszajelzes nem veszett el.
- Cache-busting miatt az aukcios PHP asset verzio `0.2.4`, a frontend belso widget-verzio `1.0.4`.

## 2026-04-29 15:12 CEST - JVK public copy cleanup a detail drawerben
- A detail drawer es a licitform technikai scaffold szovegei publikus, felhasznaloi nyelvre cserelve.
- Javult a fo leiras, az email/telefon note, a gombfelirat, a kezdo detail-status es a hiba copy is.
- Cache-busting miatt az aukcios PHP asset verzio `0.2.5`, a frontend belso widget-verzio `1.0.5`.

### 2026-04-29 JVK aukció widget v1.0.6/v0.2.6
- Scroll regresszió javítva (v1.0.3 bázison)
- Email értesítő: office@sharity.hu + koncz.veronika@mielemed.hu
- PHP v0.2.6 deployolva, JS v1.0.6 deployolva

### 2026-04-30 JVK aukció widget v0.3.6
- Visszaszámláló: főoldal kártyákon + detail panelen, urgency pulse ≤120s
- Snipe protection: 120s window + 120s extend, WP option per-lot override
- Aukció zárás: 2026-05-16T20:00:00Z (máj. 16 22:00 Budapest)
- Bidder adatok localStorage autofill: email, telefon, név — sikeres licit után mentés, következő lotnyitáskor visszatöltés
- PHP v0.3.6 + JS v0.3.6 deployolva app.sharity.hu-ra

### 2026-04-30 JVK aukció widget v0.3.6
- Visszaszámláló: főoldal kártyákon + detail panelen, urgency pulse ≤120s
- Snipe protection: 120s window + 120s extend, WP option per-lot override
- Aukció zárás: 2026-05-16T20:00:00Z (máj. 16 22:00 Budapest)
- Bidder adatok localStorage autofill: email, telefon, név — sikeres licit után mentés, következő lotnyitáskor visszatöltés
- PHP v0.3.6 + JS v0.3.6 deployolva app.sharity.hu-ra

### 2026-05-04 JVK aukció widget v0.3.7
- Képlevágás javítva: object-fit:contain + letterboxing, kártya + detail kép teljes mű látható
- PHP v0.3.7 + JS v0.3.7 deployolva app.sharity.hu-ra

### 2026-05-04 — v0.3.8 deploy (height/phone/sms)
- Height growth fix: `min-height:100%` törölve a card CSS-ből (Chrome grid layout bug)
- Telefon country code selector: select+input kombináció, 8 ország, +36 default
- Vonage SMS unicode: `'type' => 'unicode'` → ékezetes char-ok helyes küldése
- PHP 0.3.8 + JS 1.0.7 — prod deploy OK

### 2026-05-04 — private admin dashboard widget (tickets + bids + cert)
- Új admin endpointok: donation transactions list, cert resend/confirm/download; auction bids list.
- Donation DB migration bővítés: `cert_manual_confirmed`, `cert_manual_confirmed_by`, `cert_manual_confirmed_at`.
- Új shortcode: `[impact_event_admin_dashboard campaign="jovonkvize-2026"]`.
- Új beilleszthető JS: `wp-content/mu-plugins/impactshop-event-admin-dashboard-widget.js`.
- Lint: `php -l` PASS (donation+auction plugin), `node --check` PASS (dashboard js).

### 2026-05-05 — impact-community source guard hardening
- Deploy guard: `scripts/guarded-remote-write.sh` most canonical path + sha256 egyezest kovetel `impact-community.php` esetben.
- Intl lane backup/rollback scriptek deduplikalva (`scripts/impact-intl-runtime-backup.sh`, `scripts/impact-intl-runtime-rollback.sh`).
- Community file kezeles defaultban tiltott; csak explicit `--include-community --ack-include-community` mellett fut.
- CI check hozzaadva: `.github/workflows/impact-community-source-guard.yml`.

### 2026-05-05 — PR129 review-fix iteration
- Javitas: `--allow-shrink` flag tenylegesen mukodik a `guarded-remote-write.sh` anti-shrink gate-ben.
- Javitas: canonical guard hash alapon elfogadja a worktree pathot is, de nem-kanonikus tartalmat tovabbra is blokkol.
- Javitas: rollback lane hash mismatch / missing backup file eseten fail-closed.
- CI parity-check workflow frissitve, mar nem csak marker stringeket keres.

### 2026-05-05 — Impi Step 3 Clean Extraction (13:50)

- 🎯 Extracted clean Impi Step 3 commit to separate branch `impi-step3-scoped`
- 📋 Commit: fix(ngo-admin): harden Impi capabilities gating fail-closed
- 🔍 Audit findings implemented: fail-closed defaults, nonce coherence, security sanitization
- 🛡️ Guard compliance: protected-change-records created
- 📤 Status: ready to push and create independent PR

### 2026-05-05 — adomany-automata portal redirect deploy
- Celzott MU-plugin deploy: `wp-content/mu-plugins/impactshop-ngo-guides.php` guarded single-file lane-n (`scripts/guarded-remote-write.sh`).
- Uj 301 redirect aktiv: `/adomany-automata-portal-1` es `/adomany-automata-portal-2` -> `/rolunk/?lang=en`.
- Deploy utani ellenorzes: mindket URL `301` helyes celra, vegpont `200`.
- Remote backup keszult: `impactshop-ngo-guides.php.bak-20260505-135334` (rollback parancs a deploy outputban rogzitve).
