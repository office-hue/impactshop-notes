# Impact Shop adomanypont selector — DEV governance terv

Status: `approved-draft-revised-after-terra-audit`. Ez a csomag kizarolag a
jovobeli Impact Shop adomanyaranyos globalis pontforras exact repo-local
selectorat kesziti elo. Termekkodot, altalanos DEV delivery policyt, runtime-ot
vagy kulso szolgaltatot nem modosit.

Plan ID: `impactshop-donation-points-selector-r1-20261014`

<!-- DEV-DELIVERY-V2-MANIFEST
{
  "schemaVersion": 3,
  "contractId": "dev-v4.plan",
  "planId": "impactshop-donation-points-selector-r1-20261014",
  "repo": "impactshop-notes",
  "selectorId": "dev-governance-source",
  "identity": {
    "taskId": "impactshop-donation-points-selector-r1-20261014",
    "branch": "feat/impactshop-donation-points-selector-r1-20261014",
    "codeBase": "e9bfb75a3b9e47e7f9a948597ca36b883c9e25a7",
    "policyCommit": "e9bfb75a3b9e47e7f9a948597ca36b883c9e25a7"
  },
  "decision": {
    "state": "approved",
    "summary": "Register an exact product-specific selector for the already audited Impact Shop donation-proportional global points package without changing general DEV or live authority."
  },
  "authorization": {
    "source": "task-request",
    "taskRequestDigest": "a87df38af466b5e95541a41c5700690b3da8253e8e649dd6f255dada4b3b8f54"
  },
  "scope": {
    "changeImpact": "protected",
    "riskTier": "high",
    "releaseProfile": "source-only",
    "changeAllowlist": [
      "config/dev-v4/task-selectors.v1.json",
      "config/dev-v4/stage-b-activation-policy.v1.json",
      "scripts/dev-v4-admission.mjs",
      "tests/dev-v4-admission.test.mjs",
      "tests/dev-v4-lifecycle-installed-cli.test.sh",
      "docs/dev-plans/impactshop-donation-points-selector-r1-2026-10-14.md",
      "docs/protected-change-records/2026-10-14-impactshop-donation-points-selector-r1.md",
      "docs/continuity/dev/2026-10-14-impactshop-donation-points-selector-r1.md",
      "docs/impactshop-governance-system-plan-2026-06-16.md",
      "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
      "docs/bastion-guard-status.md",
      "notes.md",
      "system-status-snapshot.md"
    ],
    "documentationTargets": [
      "docs/dev-plans/impactshop-donation-points-selector-r1-2026-10-14.md",
      "docs/protected-change-records/2026-10-14-impactshop-donation-points-selector-r1.md",
      "docs/continuity/dev/2026-10-14-impactshop-donation-points-selector-r1.md",
      "docs/impactshop-governance-system-plan-2026-06-16.md",
      "docs/impactshop-notes-doc-sync-map-2026-06-23.md",
      "docs/bastion-guard-status.md",
      "notes.md",
      "system-status-snapshot.md"
    ]
  },
  "validation": {
    "profileId": "affected",
    "recipePolicyCommit": "e9bfb75a3b9e47e7f9a948597ca36b883c9e25a7"
  }
}
DEV-DELIVERY-V2-MANIFEST -->

## 1. Cel, elfogadas es nem-celok

A csomag utan egy uj, exact base-owned selector letezik:
`impactshop-donation-points-source-r1`. Csak a korabban auditált
adomanyaranyos pontcsomag felsorolt termek-, teszt- es dokumentacios fajljait
engedheti irni. Egyetlen tovabbi `wp-content/`, `config/`, `scripts/`, `tests/`,
provider-, env-, deploy- vagy cron-owner fajl sem ferhet bele implicit modon.

Elfogadas:

1. a selector mind a lifecycle task selector, mind a Stage B admission
   policyben azonos exact szerzodessel szerepel;
2. lane `protected`, risk ceiling `high`, `requiresPlanId=true`;
3. capabilityk pontosan `php-runtime`, `git-readback`, `static-node-test`;
4. `writeSurfaceMatch=exact`, igy egy literal fajlnev suffix/prefix
   utkozese sem fer bele;
5. a pozitiv fixture tenyleges engedelyezett fajlvaltozast fogad el, az extra
   path es a `.php.attacker.php` prefix-utkozes blokkol;
6. a governance es a jovobeli product plan release profile-ja `source-only`;
7. a kesobbi push elotti aktiv source-publication operations preflight kulon
   kapu, nem plan authority es nem resze ennek a source csomagnak;
8. local/CI selector-policy parity es DocSync rögzitett;
9. nincs termekkod-, altalanos DEV policy-, provider-, build-, deploy-,
   adatbazis-, cron- vagy secret
   mutacio.

Nem cel az adomanypontok implementalasa, a regi termekterv atirasa a sibling
worktree-ben, live operations profil letrehozasa vagy barmilyen release.

## 2. Traceability

| Feltetel | Authority | Muvelet | Allapot/kockazat | Feloldo fajl/csomag | Verifikacio |
| --- | --- | --- | --- | --- | --- |
| Lifecycle identity | installed controller + Git | plan/bind | verified | jelen task | status/projection, exact HEAD/tree |
| Governance owner | `task-selectors.v1.json` | policy edit | verified | `dev-governance-source` | exclusive owner readback |
| Product selector | audited product plan | future source | missing hard gate | ket selector policy | exact deep equality tests |
| Retained controller profile | retained plan parser | governance bind | verified `source-only` | jelen manifest | manifest JSON/static check + adapter bind |
| Product file inventory | audited plan 6.1–6.6 | future source | verified planned inventory | selector `writeSurface` | literal list test, wildcard tiltás |
| PHP capability | repo capability contract | future source | future-boundary | base-owned capability receipt | fresh 15-minute receipt a product taskban |
| Runtime/SLO/cost | scope | governance source | not applicable | nincs runtime delta | zero provider/build/deploy assertions |
| DocSync | repo policy | source closeout | required | governance hub/map/record/continuity | targeted guard + diff check |
| Secretary context | controller context | plan checkpoint | degraded before bind | bind utan uj refresh | explicit readback, nincs recovery bypass |
| Handbook URL | GitHub link | guidance | unavailable/404 | installed package + repo authority | advisory, nem hamis PASS |

## 3. Exact product selector write surface

Az alabbi lista egyetlen exact inventory. Prefix, glob vagy konyvtar-szintu
engedely nincs.

### Termekforras

- `wp-content/mu-plugins/sharity-points.php`
- `wp-content/mu-plugins/sharity-points-money.php`
- `wp-content/mu-plugins/sharity-points-manager.php`
- `wp-content/mu-plugins/sharity-points-api.php`
- `wp-content/mu-plugins/sharity-points-impactshop-attribution.php`
- `wp-content/mu-plugins/sharity-points-ledger-sync.php`
- `wp-content/mu-plugins/sharity-points-cron.php`
- `wp-content/mu-plugins/sharity-vacation-manager.php`
- `wp-content/mu-plugins/sharity-decay-manager.php`
- `wp-content/mu-plugins/impactshop-cj.php`
- `wp-content/mu-plugins/impactshop-partner-api.php`
- `wp-content/mu-plugins/impactshop-click-tracking.php`
- `wp-content/mu-plugins/impactshop-ads-watch.js`
- `wp-content/mu-plugins/impactshop-ads-watch-ui-cta-bundle.js`

### Celzott tesztek

- `tests/sharity-points-money.test.php`
- `tests/sharity-points-manager-transaction.test.php`
- `tests/sharity-points-impactshop-attribution.test.php`
- `tests/sharity-points-key-rotation.test.php`
- `tests/sharity-points-impactshop-ledger-sync.test.php`
- `tests/sharity-points-cutover.test.php`
- `tests/sharity-points-direct-writer-inventory.test.py`
- `tests/impactshop-click-no-reward.test.php`
- `tests/impactshop-click-no-reward-static.test.py`
- `tests/impactshop-cj-donation-contract.test.php`
- `tests/impactshop-partner-donation-contract.test.php`
- `tests/impactshop-points-schema-migration.test.php`
- `tests/impactshop-points-release-contract.test.sh`

### Contract, guard es continuity

- `docs/rewarding-system.md`
- `docs/dev-plans/impactshop-donation-proportional-points-source-r1-2026-10-13.md`
- `docs/partner-api-openapi.yaml`
- `docs/partner-data-mapping.md`
- `docs/partner-postman-collection.json`
- `docs/partner-reconcile-export-spec.md`
- `docs/protected-change-records/2026-10-13-impactshop-donation-proportional-points-source-r1.md`
- `docs/security/incidents/2026-10-13-impactshop-committed-credential-wp0s.md`
- `docs/continuity/dev/2026-10-13-impactshop-donation-proportional-points-source-r1.md`
- `docs/impactshop-notes-doc-sync-map-2026-06-23.md`
- `docs/bastion-guard-status.md`
- `docs/impactshop-governance-system-plan-2026-06-16.md`
- `docs/impactshop-protected-files.json`
- `docs/impactshop-guard-config.json`
- `docs/impactshop-guard-config.sha256`
- `docs/impactshop-guard-hashes.json`
- `docs/impactshop-guard-hashes.sha256`
- `notes.md`
- `system-status-snapshot.md`

Kifejezetten tiltott implicit bovites: `impact-gamification.php`, NGO,
vote-purchase, offerwall, identity, `.github/`, deploy script, env, provider
configuration es barmely sibling path.

## 4. Fajlszintu implementacios csomag

### WP1 — selector policy

- `config/dev-v4/task-selectors.v1.json`: uj selector az exact lane,
  requires-plan, capability es write-surface szerzodessel. Az exclusive owner
  tablazat es a meglevo selectorok valtozatlanok.
- `config/dev-v4/stage-b-activation-policy.v1.json`: ugyanaz az exact selector,
  `riskCeiling=high` es `writeSurfaceMatch=exact`; minden globalis
  provider/build/deploy/runtime/secret flag valtozatlanul false.
- `scripts/dev-v4-admission.mjs`: az eddigi selectorok viselkedesenek
  megvaltoztatasa nelkul az explicit `writeSurfaceMatch=exact` opt-in szerzodes
  csak literal path-egyezest fogad el, es ehhez a selectorhoz a staged index
  utjait is hozzaadja a committed, unstaged es untracked leltarhoz. A
  directory-prefix szemantika mas selectoroknal valtozatlan marad.

### WP2 — tesztek

- `tests/dev-v4-admission.test.mjs`: a task-selector es Stage B selector exact
  deep equalityje, wildcard es broad-prefix tiltas, jelenlegi selectorok es
  globalis deny flag-ek regresszioja, merged immutable base pozitiv fixture,
  valamint missing-plan, candidate-self-registration, extra-path es
  untracked es staged-only file-prefix-collision negativ esetek. A pozitiv
  fixture tenylegesen letrehoz es stage-el egy engedelyezett termekfajlt. Ez a
  CI-ben tenylegesen futó parity teszt.
- `tests/dev-v4-lifecycle-installed-cli.test.sh`: a terv megengedte a lokalis
  controller-smoke boviteset, de a megvalositas nem modositja. Az installed
  controller aktualis engine-pinje nem determinisztikus selector-fixture; az
  exact merged-base, missing-plan es self-registration szerzodest ezert a
  hermetikus admission teszt bizonyitja. Ez a fajl nem resze a candidate diffnek.

### WP3 — governance es DocSync

- plan, protected change record es continuity rögziti az identityt, scope-ot,
  findingokat, rollbacket es kovetkezo product taskot;
- governance system plan, DocSync map, bastion status, notes es system snapshot
  ugyanazt a source-only igazsagot tukrozi;
- dokumentacio nem allit selector-aktivaciot merge elott, es merge utan sem
  allit termekdeployt.

## 5. Ellenorzes es audit

Csak erintett ellenorzesek:

1. manifest JSON/static ellenorzes, majd anchor commit es kizarolag a repo
   adapteren keresztuli `bind`; a wrapper altal nem exportalt validator nem
   resze a tamogatott vegrehajtasi utnak;
2. `node --test tests/dev-v4-admission.test.mjs`;
3. JSON parse a ket modositott policy fajlon;
5. selector inventory exact-set, missing-plan, candidate-self-registration,
   broad-prefix/extra-path es `.php.attacker.php` negativ fixture;
6. local/CI parity read-only vizsgalat: az MJS teszt a CI-ben fut; a valtozatlan
   shell lifecycle teszt local-only es nem candidate gate;
7. controller-selected affected checkek;
8. repo-local continuity/DocSync guard, ha van tamogatott executable;
9. `git diff --check`;
10. Terra/high correctness audit, majd vegleges Sol/high authority/security
    audit ugyanazon candidate SHA/tree-n.

Nem fut teljes WordPress suite, PHP termekteszt, provider readback, build vagy
deploy: ezekhez nincs erintett source path es nincs authority.

## 6. Hiba-, recovery- es rollback-szerzodes

- Ismeretlen selector/profile fail-closed.
- Candidate policy nem ad sajat maganak base authorityt; a termek task csak a
  governance merge utan, friss exact-main lifecycle-kent indulhat.
- Teszt- vagy review-finding javitasa utan csak az invalidalt ellenorzes fut ujra.
- Azonos failure ID ket sikertelen lokalis javitasa utan diagnozis es rovid
  repair plan kotelezo.
- Source rollback kizárólag normal revert PR, history rewrite nelkul.
- A selector visszavonasa nem rollbackeli es nem torli a termekadatot; ebben a
  csomagban termekadat eleve nem valtozik.

## 7. Commit, publication es terminalis allapot

Tervezett commit:

```text
feat(dev): register Impact Shop donation points selector

Plan-ID: impactshop-donation-points-selector-r1-20261014
```

Push/PR/merge elott kulon be kell tolteni az aktiv
`office-hue-dev-release-operations` skillt, friss requirements/prestate kell,
es maximum egy push, egy PR, egy squash merge engedett. A PR body exact
candidate SHA/tree-t, teszteket, authority-hatart, kockazatot es rollbacket
tartalmaz; a tenyleges GitHub body es required checkek visszaolvasandok.

Az engedelyezett vegallapot `selector-source-merged`. Ezutan uj exact-main
product lifecycle indulhat az `impactshop-donation-points-source-r1`
selectorral. A governance merge nem jelent termekforras-keszultseget,
release-ready, deployed, user-visible vagy production-accepted allapotot.

## 8. Vegigjarasi ellenorzes

1. A terv anchor commitja bemenet a governance bindhez; az adapteres bind
   ujraparszolja es identityhez koti a manifestet.
2. A bind ad irasi jogot csak a terv allowlistjere.
3. A ket policy ugyanazt az exact product inventoryt kapja.
4. A tesztek a policyt es fail-closed hatart igazoljak.
5. A dokumentacios egyseg ugyanazt az identityt es nem-live hatart rögziti.
6. Candidate-bound review utan johet csak source publication.
7. A merge SHA/tree lesz az uj product task immutable policy base-e.
8. Semelyik lepes nem fugg sajat maga altal letrehozott authoritytol, es nincs
   provider-, runtime- vagy adatmutacios kor.

## 9. Fuggetlen tervaudit

Terra/high, read-only audit: `REVISE`. A findingok e verzióban atvezetve:

- a nem szukseges altalanos release-profile registry es delivery-policy touch
  kikerult;
- a product selector inventoryja 14 termekforras + 13 teszt + 19
  dokumentacios/guard path exact halmaza;
- a CI-ben futó MJS parity teszt es a valtozatlan local-only lifecycle
  shellteszt szerepe elkulonult;
- bekerult a merged-base, missing-plan, candidate-self-registration es
  extra-path negativ szerzodes;
- a jovobeli product plan `source-only`; a publication operation preflight
  kulon authority boundary;
- a governance task a mar letezo `dev-governance-source` owner alatt fut, a
  product selector pedig csak merge utani exact-main base-bol hasznalhato, igy
  nincs circularis self-authority.

Sol/high candidate review: `REVISE` egy P1 findinggal. A shared admission
korabbi `p.startsWith(x)` szemantikaja a literal fajlbejegyzeseket is prefixkent
kezelte. A javitott terv ezert kizarolag az uj selector explicit
`writeSurfaceMatch=exact` opt-in viselkedeset es a hozza tartozo pozitiv/negativ
fixtureket engedi. A finding lezárásahoz uj frozen candidate es uj Sol/high
review kotelezo.

Az elso fix-verification eredmenye `still_vulnerable`: az exact matcher mar
helyesen mukodott, de a regi path-leltar nem tartalmazta a staged-only indexet.
A masodik javitas csak az opt-in exact selector leltarat egesziti ki
`git diff --cached --name-only` eredmennyel; a regresszios fixture kulon
bizonyitja a staged allowlisted pozitiv esetet, valamint ugyanazon suffix
untracked es staged-only blokkolasat. Uj frozen-tree verification kotelezo.

A masodik verify-fix a frozen
`57533da1aebe7f027983ba49b295e0b90a74e59e` tree-n `fixed` eredmenyt adott:
az exact-mode index-leltar, a literal egyezes es a legitim staged fajl
viselkedese egyutt bizonyitott. A finding lezart; ez nem ad push-, merge-,
release- vagy deployment-authorityt.
