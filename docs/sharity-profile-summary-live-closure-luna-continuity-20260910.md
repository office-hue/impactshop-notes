# Sharity profile summary live closure — Luna checkpoints A–B

## Production closure — 2026-09-11

The Sharity profile package is merged and user-visible in production from
`origin/main@e9d9c934fb7bfce4ec9bb2af0586f19a8d9782f6`. PR #206 removed the exact
Site Kit AdSense producer; production evidence then identified the remaining
stored producers as Elementor `text-editor` widgets. PR #207 replaced the
non-functional setter path with Elementor's canonical
`elementor/frontend/widget/should_render` filter.

The final immutable PHP source SHA-256 is
`72980c402f05666c73f4a7b1f69df68d4ad54bf45bf7ecdd6b333b40f0f54cbc`.
It was applied by exact-file CAS to staging release
`20260911T071010Z-e9d9c934fb7b-0eb783ee` and production release
`20260911T071058Z-e9d9c934fb7b-20804a79`, both mode `0444`. Production
rollback is bound to the latter release ID and deployed SHA. The verified
database backup from the parent production package remains
`/home/sharityh/impactshop_backups/sharity-profile/app-20260911T060000Z.sql`
with its checked checksum sidecar; this presentation-only follow-up made no
database change.

Final browser acceptance: staging and production returned status `200`, one
profile panel, zero AdSense markers and zero horizontal overflow. Production
CLS was `0.006905` desktop and `0.070579` mobile; the canonical sign-in label,
profile anchor, keyboard focus outline and owner/pseudo cookie flags passed.
Two isolated production cookie jars both reached `active`, received distinct
pseudo IDs, and returned `private, no-store, no-cache`, `Vary: Cookie` and
Cloudflare `DYNAMIC`, with zero AdSense markers.

Terra source QA passed. Codex Security scans
`24bf9053-5b81-4e61-bafb-1ee6ee0d425b` and
`2af6d6af-7f20-4745-8b8d-01a1d6ae5951` completed with zero reportable
findings. Cron/watchdog changes were not required. Status:
`production-accepted`; deterministic provider worker inventory is unavailable
on S59, so acceptance is bound to exact CAS readback plus repeated public
origin responses, not a worker-count claim.

## Luna follow-up — canonical Elementor render filter

Production evidence showed the remaining AdSense widgets are Elementor
`text-editor` instances. The profile suppression now uses Elementor's
`elementor/frontend/widget/should_render` boolean filter with the widget as
the second argument, returning false only for exact AdSense widget names or
`html`/`text-editor` settings containing an executable script, `ins`, or
AdSense-host URL marker. The ineffective `before_render` setter path is gone.

The hermetic contract covers the boolean filter, text-editor producer,
benign/explanatory text-editor and control-route behavior. No auth, data/DB,
cookie, shared dependency, provider or live runtime state changes.

## Luna follow-up — AdSense hook order and marker precision

The profile suppression now removes the exact Site Kit AdSense
`register_tag` callback immediately during the early suppression phase, before
normal `template_redirect` callbacks can execute, and keeps the late registry
scan as a catch-up for late loaders. Generic Elementor `html` settings now
require an actual script, `ins`, or AdSense-host URL marker; explanatory or
code-sample text alone is not suppressed.

The hook-order contract proves the exact Site Kit callback is absent before
dispatch and therefore its `register_tag` method is not called. The source-only
follow-up changes no auth, data/DB/cookie, shared dependency, provider or live
runtime state.

## Luna follow-up — AdSense producer suppression

Production acceptance found seven remaining AdSense markers and desktop
overflow/CLS on `/profil/`, while cache, cookie and identity isolation passed.
The profile suppression now queues a late `template_redirect` registry scan and
removes only the exact `Google\\Site_Kit\\Modules\\AdSense::register_tag`
object callback after Site Kit registers it. Elementor suppression keeps the
exact AdSense widget names and additionally disables only the generic `html`
widget when its render settings recursively contain
`pagead2.googlesyndication.com` or `adsbygoogle`.

The hermetic producer contract covers the late callback, static callback near
miss, root/control routes, nested settings, benign HTML and non-HTML near miss.
No output-buffer rewrite, auth/data/DB/cookie/shared-dependency or provider
change is included. This is source-only; live remeasurement and publication
remain separate gates.

## Luna follow-up — hidden push reservation specificity

The exact shared Playwright simulation measured desktop CLS `0.041389` and
mobile CLS `0.137899`; the remaining mobile shift was the hidden push block
computed as `display:none` despite its profile rule, then expanding to `181px`.
The narrow profile/data-role selector now uses `display:block !important` with
`visibility:hidden`, preserving the HTML hidden attribute and existing JS/auth
semantics while reserving the measured responsive space.

A service-worker-blocked Playwright response simulation verified the exact
profile-scoped rule at first render: desktop CLS `0.006540`, mobile CLS
`0.012649`, horizontal overflow `0`, and hidden push reservation
`157px`/`181px`. Codex Security diff scan
`1611d230-c83f-41e1-aa1a-ff94fd277adc` completed with zero reportable
findings. Current `origin/main@94e442d410356fa4b89a8ccc779b36b9e2ca4dec`
was merged while preserving exact source tree
`6016843d08862d6f840f6f907937dae0906fe0c8`.

The focused CLS contract, syntax, profile regressions, guard hashes,
protected-touch and diff checks pass. The mobile `<0.1` target remains a
staging remeasurement gate; no live state changed.

## Luna follow-up — responsive async block reservations

The exact staging timeline after early enqueue measured mobile CLS `0.2506`,
desktop CLS `0.0413` and zero overflow. The remaining shifts were bounded to
the push block (`181px` mobile / `157px` desktop), points block (`301px` /
`265px`), votes summary (`139px`) and history (`75px`). Profile-only CSS now
reserves those responsive dimensions; the hidden push state uses the same
display/visibility reservation as its visible state. The previous compact and
last-NGO reservations remain. No auth, JS, global CSS or shared dependency
changed.

The exact responsive CLS contract, syntax, profile regressions, guard hashes,
protected-touch and diff checks pass. The `<0.1` mobile target remains a
staging remeasurement gate; desktop is already below target in the supplied
measurement.

## Luna follow-up — early profile asset enqueue

The first CLS reservation checkpoint reduced but did not close the staging
Playwright shift: mobile `0.2585`, desktop `0.2704`, with the largest desktop
shift at `664ms` attributed to the identity card/page content. The profile
shortcode was registering/enqueuing its inline stylesheet only while rendering
page content, after `wp_head`. The existing `wp_enqueue_scripts` registration
now enqueues the already-registered profile assets only when the canonical
profile route is active. The three profile-only min-height reservations remain
because they address the later async data blocks and do not add global CSS.

The early-enqueue contract, syntax, profile regressions, guard hashes,
protected-touch and diff checks pass. The `<0.1` desktop/mobile CLS target and
zero-overflow claim remain staging remeasurement gates; no live state changed.

## Luna follow-up — profile-scoped layout stability

The staging Playwright sample measured desktop CLS `0.064` and mobile CLS
`0.326`; the shifts came from async identity/points blocks changing height
after the first paint. The profile-only inline CSS now reserves the measured
points/compact-summary and last-NGO space while keeping those blocks visually
hidden until data arrives. No global layout or shared dependency is changed.

The focused CLS contract, PHP lint, JavaScript syntax, existing profile
contracts, guard hash verification and diff check pass. This remains
source-only; the mobile target `<0.1` is not claimed until the corrected
source is deployed and the staging Playwright measurement is rerun.

## Sol blocker correction — environment-aware profile route family

The staging UI gate found a real hosting difference: `app.sharity.hu` serves
the staging WordPress installation below `/impactshop-staging`, while
production serves the same profile family at the host root. The shared profile
route classifier now strips only the exact `home_url('/')` path segment before
matching `/profil` and its children. Query strings remain irrelevant and
near-miss prefixes remain outside the profile boundary. The one-time
`/profil/belepesi-kod` check and its HttpOnly reveal-cookie path use the same
canonical environment-path helper, so root and subdirectory installs retain
the same first-party and cache/ad suppression guarantees.

Focused hermetic root/subdirectory, query, near-miss and reveal-cookie-path
tests pass. This is a source-only correction; no staging page, database,
provider, cache, cron/watchdog or production state was changed by this
checkpoint.

## Sol staging source apply — API accepted, profile fixture blocked

The new Sol/high resume preserved the exact merge identity
`220432b1f9d1ca15e6095d03b4f8cce1cf288ecd` / tree
`fd81686a4b4fb0bb774ff39e97b79ad9266c02fd`. From the clean detached Mini
release worktree, the two previously previewed staging files were applied once
through the exact-CAS guard while safe-disable remained enabled:

- `sharity-profile-stg-20260910T232500Z-resume-boot` deployed
  `impactshop-boot.php` at SHA-256 `f7cf5099d80e515e76e2bd0d5bb8b6e0de0c3455d48efa31a265bb994c9e5f27`;
- `sharity-profile-stg-20260910T232500Z-resume-identity` deployed
  `impactshop-identity-panel.php` at SHA-256 `bec0ec93d264a171968008a00ff734e4329b9be4b0b766880e5f72b2c0b4aa38`.

Both manifests are `deployed`, exact readback matches, both targets are mode
`0444`, the runtime owner-policy self-test is true, and safe-disable was cleared
to `0` only after those postconditions passed. The staging API acceptance then
proved query and pretty REST pending-to-active persistence for two distinct
cookie jars, legacy read-only/no-votes isolation, policy v3, owner/pseudo cookie
flags and private/no-store/Vary cache headers.

The first UI-route request stopped the acceptance wave because the canonical
staging URL `https://app.sharity.hu/impactshop-staging/profil/` returns `404`.
Read-only WordPress inventory confirms that staging has no `profil` page record;
production has published page ID `18984`. This is a staging content fixture/data
gap, not a rewrite or merged-source failure. No production write, page copy,
provider action, cache flush, cron or watchdog action followed. The staging
grant inventory observed after the test/diagnostic requests was two active and
three pending rows; it is diagnostic state, not UI acceptance evidence.

Independent Terra/high QA therefore records `BLOCK` for full staging acceptance.
The next package remains Sol/high and must explicitly authorize and create or
copy the minimal staging `profil` page fixture before rerunning only the failed
UI/ad/layout and mutation-isolation checks. Production remains blocked until
that acceptance passes and the S59 LSAPI worker drain/recycle proof is available.

## Sol release resume — source merged, staging fail-closed stop

On 2026-09-11 the bootstrap/quota follow-up was published by the single
guarded source lane and squash-merged as PR #201. The exact merge is
`220432b1f9d1ca15e6095d03b4f8cce1cf288ecd`; its tree
`fd81686a4b4fb0bb774ff39e97b79ad9266c02fd` exactly matches the tested
candidate tree. All six required GitHub checks passed. The Mini release
worktree was cleanly repinned detached to that exact `origin/main` identity.

Both staging exact-file previews then passed the repo guard, bastion manifest,
four HTTP preflight endpoints and no-write rsync comparison. The staging
database reported `REPEATABLE-READ` and an empty grant table. The subsequent
mutating command enabled `impactshop_owner_policy_safe_disable=1`, then stopped
locally on the Mini because nested shell expansion attempted to resolve the
WP-CLI readback before the S59 hop. Exact-CAS prepare/apply did not start: the
two planned release IDs are absent and both target SHA-256 values remain at
their preimages (`impactshop-boot.php` `cccc3f4147c0d849a4d53bf1567150c94e1493afda88686b6709d89f2136b56f`,
`impactshop-identity-panel.php` `54f21494d8e2e08afde2e7d02269002629a57b3ce21d922bf634d0285ae92e67`).
The staging state is deliberately left fail-closed; no retry or automatic
safe-disable clear was performed in the same live wave.

Production was not mutated. Its two inspected files remain mode `0444` at
their pre-existing hashes, and the owner-policy safe-disable option is absent.
The next package is a Sol/high release resume: re-read exact source/target
identity and `safe_disable=1`, then use separate non-nested S59 commands for
the two admitted CAS applies and postconditions. Production remains gated by
successful staging acceptance and provider-proven LSAPI drain/recycle.

## Checkpoint B — owner-grant and policy closure

### Terra blocker follow-up — conditional debug and active UI principal

The runtime registry treats `GET /impact/v1/ads-watch/debug-rotation` as
conditional: an absent route is permitted only while
`impactshop_ads_watch_debug_enabled()` is false; if registered, its exact
method/callback tuple is still required. Profile UI pseudo mutations remain
disabled unless `identityState === 'active'`, including vacation
status/setup/finally, last-NGO reset, push refresh/click and credential-save
award. Failed initial/restore owner-cookie issuance restores the prior valid
pseudo cookie or expires a new one, with safe-disable on header restoration
failure. VB2026 service requests bind the validated `x-sharity-pseudo-id` as
the sole principal; mixed cookie A/B and invalid-bearer requests cannot fall
back to the browser cookie.

The read-side `GET /impact/v1/vb2026/my-ngo-selection` now uses the same
explicit `owner_or_service_auth` central mode as its native callback, so a
valid bearer-only service read is admitted while browser-owner, mixed-principal
and invalid-bearer cases remain fail-closed.

The same clean worktree/branch continued to checkpoint B. The grant table is
an idempotent v2 `dbDelta` migration with an explicit InnoDB requirement and
`pending|active` state,
`activated_at`, `supersedes_grant_hash` and lifecycle indexes. New grants are
256-bit `random_bytes(32)` values stored only as HMAC-SHA256 hashes, remain
pending for ten minutes, and activate only on a later GET with both cookies.
Activation, superseded-grant revocation and readback use one captured UTC
clock and a transaction; both replacement cookie headers are queued and
verified first, then active renewal couples the owner and pseudo cookies for
365 days. Commit/readback failures expire queued replacement cookies and
safe-disable the policy. A newly issued token is never copied into `$_COOKIE`,
so same-request responses remain `binding_pending`.

`000-impactshop-owner-policy.php` is the first-loaded central policy layer.
Its machine-readable registry covers the exact current pseudo-backed mutator
and private-read route set (`owner_required`), the restore exchange
(`access_code_exchange`), admin and service classifications, and explicit
public/read-only exclusions. Existing endpoint nonce, webhook, service and
admin checks remain authoritative. Runtime introspection checks route, method,
callback and policy tuples for every registry entry; DB/policy failure or
safe-disable denies owner mutation. VB2026 uses explicit pre-auth intent and
owner-or-existing-Bearer-service modes; native endpoint controls remain
authoritative. The token_get_all inventory performs an exact two-way
source/registry/callback comparison and rejects unclassified routes, duplicate
classifications and callback drift; its negative tamper fixtures prove both
fail-closed paths. No cron/watchdog was added.

The action-bar account target now uses `home_url('/profil/#impactshop-account-top')`;
the legacy `#impactshop-account` resolver remains for old links. Exact-origin
checks compare scheme, host and effective port against `home_url`; forwarded
host headers and cross-host Sharity aliases are not trusted.

Checkpoint B remediation evidence: PHP lint, JS syntax, A static contracts,
owner-policy inventory, conditional runtime and mixed-principal behavior fixtures,
Luna owner remediation contracts and `git diff --check` PASS. No provider, SSH, database execution,
OPcache, deployment, push, PR or merge was performed. Remaining gates are
staging backup/schema/browser/API E2E and runtime/provider worker proof.

Date: 2026-09-10
Plan ID: `sharity-profile-summary-live-closure-20260910`
Worktree: `impactshop-notes-feat-sharity-profile-summary-live-closure-luna-20260910`
Branch: `feat/sharity-profile-summary-live-closure-luna-20260910`
Base: `d39349a3dedad8ebda597c2d531fd2e498268990`

## Follow-up — first-request owner bootstrap correction

The dedicated Luna follow-up worktree `fix/sharity-profile-bootstrap-owner-grant-luna-20260910`
closes the remaining init-order gap without changing the owner-grant contract:
storage installation and new-browser binding run at init priority 0, before the
legacy priority-1 boot callback. A normal no-source HTML request issues exactly
one pending grant and queues both cookies; the next request performs activation.
Query `impact_pseudo_id`, REST/wp-json, and existing legacy cookie sources retain
their compatibility paths. On storage, random, cookie/header, readback or
compensation failure a request-local block prevents legacy pseudo fallback and
profile output stays `unavailable`.

Follow-up evidence: `tests/impactshop-profile-bootstrap-owner-grant.test.py`,
PHP lint for both changed MU plugins, existing profile/static/remediation,
owner-policy inventory/runtime checks and `git diff --check` PASS. This remains
source-only; staging schema/browser/API E2E, publication and production
acceptance are not performed.

## Checkpoint C — REST predicate and bounded pending-grant quota

The complete source-bearing candidate starts at
`origin/main@dd0a19eecfdeb021ed312b5a836f14ff64af0a6e` and ends at
`f3a5fb3e27e840de07c14c570eeef7bf789718b7` with tree
`9505d904e68bb5c49ddaa1c5aa3e4a72a2c25fd5`; the governance-only follow-up
`6cf300bb547da540e0107ada9d015fd11499520f` is separate. The follow-up keeps the runtime scope to the two identity MU
plugins. The shared cookie-touch predicate rejects non-empty scalar
`rest_route` query dispatch and pretty REST paths under subdirectories, while
the earlier `impact_pseudo_id` query compatibility branch remains first.
This rejection applies only to the shared init/HTML bootstrap. The public
`GET /impact/v1/identity/profile` handler intentionally owns exactly one
pending issuance when no cookie exists and uses the same common prune/quota
issuer; REST is not a zero-issuance path.
Common owner issuance now prunes only expired pending rows in the indexed
state/expiry range, at most 64 per transaction. Automatic public issuance
locks up to 257 eligible rows and refuses at 256, committing successful prune
work without creating a token, cookie or request marker. Recovery restore
passes an explicit quota bypass but still uses the prune transaction and
supersession path. The bounded lock/read accounts for overlapping concurrent
issuers under normal InnoDB isolation; a non-default isolation level remains a
staging verification gate, so this is not an absolute cross-isolation ceiling
claim. No IP/header trust or scheduled cleanup is added.

Checkpoint C evidence: executable cookie-touch and owner-grant quota fixtures,
the existing bootstrap/profile/remediation/inventory/runtime suites, PHP lint
and `git diff --check` PASS. Guard hashes, companion digest, protected source
admission and continuity evidence are refreshed in this checkpoint. Live
staging schema/browser/API acceptance, publication and production acceptance
remain pending.

## Protected source admission

Operator approval reference:
`operator-approval:sharity-profile-summary-live-closure-20260910`.
The aggregate protected path set and forward-safe rollback contract are in
`docs/protected-change-records/2026-09-10-sharity-profile-summary-live-closure.md`.

## Remediation checkpoint

- Same-request pending activation was removed: server cookies are the sole
  browser authority and issued-this-request state stays `binding_pending`.
- `binding_pending` and `unavailable` responses expose no profile values;
  `invalid_or_revoked` exposes only the pseudo ID, while active and legacy
  read-only visibility follows the approved state model.
- Restore captures only a current valid grant hash as
  `supersedes_grant_hash`; the prior grant remains active until later
  transactional activation succeeds.
- The canonical policy manifest now carries exact callback identities and
  source/policy tuples, including negative callback/method drift checks.
- VB2026 selection-intent remains pre-auth; select-ngo and completion accept a
  valid owner browser binding or the existing exact Bearer service path.
- Grant mutation requires actual InnoDB readback. MyISAM/unknown/missing
  storage is safe-disabled without implicit conversion; live staging migration
  remains an operator prerequisite.

## Scope completed

- Full and compact identity panels expose nickname/fallback, pseudo ID, level/
  points summary and current spendable-votes state.
- `GET /impact/v1/identity/profile` has additive `votes_available` and
  `identity_state` fields. The vote read is a direct, current-pseudo,
  SELECT-only query; malformed, missing and negative values normalize to zero.
- The profile route family has a shared path classifier, private no-store
  response/page headers and `Vary: Cookie`. Repository AdSense, known Site Kit
  AdSense paths and exact/configured Elementor AdSense widgets are suppressed
  at their producers; no rendered HTML is rewritten.
- Failed owner/pseudo cookie issuance returns `unavailable` without exposing a
  newly generated pseudo. Non-active states keep mutation controls disabled in
  the profile UI. The central owner registry now covers the complete inspected
  pseudo-backed route set; unrelated service routes remain under their native
  authentication.

## Coherence and risk record

Affected route and hooks: identity profile REST GET/POST, profile/compact
shortcodes, profile `template_redirect` headers, REST post-dispatch headers,
AdSense `wp_head`, Site Kit filters and Elementor widget `should_render`.

Unaffected: vote/points business logic, cross-host SSO, provider/runtime
deployment, cron and watchdog.

Primary residual risks are unverified WordPress/Site Kit hook names in the live
environment, Elementor widget-name drift, and the existing cross-module
pseudo-mutator set awaiting checkpoint B. These require staging browser/API
E2E; this commit has no live authority.

## Evidence

- `php -l` identity and AdSense MU-plugins: PASS.
- `node --check wp-content/mu-plugins/impactshop-identity-panel.js`: PASS.
- `python3 tests/impactshop-identity-profile-v2-static.test.py`: PASS.
- `python3 tests/impactshop-profile-summary-checkpoint-a.test.py`: PASS.
- owner-policy duplicate and callback-drift tamper fixtures: PASS.
- `git diff --check`: PASS.
- Worktree-local marker and task-start decision: identity match, `allowed`.
- The shared active-worktree continuity pointer was owned by a parallel chat,
  so its single-active pointer check was not overwritten in this checkpoint.
- No push, PR, merge, staging, production, remote write, schema execution or
  provider operation.
- The complete protected candidate is bound by
  `docs/protected-change-records/2026-09-10-sharity-profile-summary-live-closure.md`;
  checkpoint-specific records remain narrower historical evidence.

## Manual UI handoff after staging

1. Open `/profil/` with two different cookie jars and verify no shared profile
   HTML or REST payload, no Google ad script/slot/widget, and stable layout.
2. Verify full and compact panels show nickname fallback, pseudo, level/points,
   and the canonical `/profil/#impactshop-account-top` action.
3. Verify active, legacy read-only, expired/revoked and unavailable states.
4. Verify a negative/malformed/missing vote row renders zero without creating a
   row, and a different pseudo's balance is never returned.
## 2026-09-11 Human Touch profile dock follow-up

The legacy eight-item floating action bar is retired. Every eligible app page
now renders one compact Human Touch profile dock with two unambiguous actions:
the account summary opens `/profil/#impactshop-account-top`, while existing
account sign-in opens `/profil/#impactshop-signin`. The summary reads the
current cookie-bound profile and points endpoints and displays nickname or
profile fallback, pseudo ID, points and spendable votes. Non-active identity
states show `belépés szükséges` instead of a vote balance.

Source scope is limited to the action-bar renderer, the explicit sign-in
anchor, its focused smoke/static test, protected hashes and continuity. No
schema, owner-grant policy, cron, watchdog or shared dependency change is
included. Plan ID remains `sharity-profile-summary-live-closure-20260910`.

Production closure: PR #210 merged as `94e25a9acbb9`. Exact CAS releases
`20260911T081100Z-94e25a9acbb9-actionbar` and
`20260911T081200Z-94e25a9acbb9-identity` deployed both PHP files at mode
`0444`. Live DOM verification found the new dock, both exact anchors and no
rendered legacy action bar. Desktop/mobile browser evidence reported zero
horizontal overflow. The full outer WordPress page redesign remains a
separate presentation package; this follow-up closes only the global floating
control replacement.

## 2026-09-11 Human Touch profile shell follow-up

The full profile shortcode now has a profile-only Human Touch shell: a soft
background treatment, a teal intro panel and a responsive card width. The
existing identity, points, votes, restore and owner-grant behavior is
unchanged. This is the next bounded presentation checkpoint after the global
floating dock; the outer Elementor header/footer remains intentionally
untouched.

Production closure: PR #211 squash-merged as `05b3d1478371`. Exact-main CAS
release `20260911T082700Z-05b3d147-profile-shell` deployed the identity panel
at SHA-256 `d2cc8c83f7707a756f3aa2edb3b48dd63bbec7acc9cd06f04ef0199b92e9aaea`
with mode `0444`. Five-endpoint production preflight and desktop/mobile live
QA passed: HTTP 200, Human Touch shell and dock present, canonical anchors,
pseudo/nickname/votes visible, no horizontal overflow and no profile ad
markers. The outer Elementor header/footer remains outside this package.

## 2026-09-11 Terra QA closure

Independent QA reconfirmed the published `origin/main@05b3d1478371` identity
and the production identity-panel SHA-256/mode (`d2cc8c83…`, `0444`). A fresh
live response contained the Human Touch shell and intro; the unchanged
source/runtime browser evidence was reused for the injected dock, canonical
anchors, profile data, zero overflow and zero profile ad markers. No blocking
regression was found. Details: `docs/sharity-profile-human-touch-terra-qa-20260911.md`.

### Product acceptance correction

Operator visual review rejected the prior shell/dock as the wrong product
surface. The dock is only a profile/sign-in panel, whereas the required global
control is the original eight-action floating bar. The live Human Touch
reference uses outlined purple/lime/coral components rather than the deployed
teal glass treatment. The next bounded Luna package must restore the eight
action contracts, remove the dock and recompose the profile in that component
grammar; no identity or owner-grant behavior may change.

## 2026-09-11 Luna corrective source checkpoint

The corrective source package restores the real eight-action `.sharity-action-bar`
markup (`video`, `tasks`, `shop`, `donate`, `account`, `ngo`, `message`,
`stats`) and removes the two-cell profile dock. The profile shell, cards,
inputs, buttons and vote panel now use the live Human Touch light canvas with
dark outlines and purple/lime/coral accents. PHP lint, focused static/profile/
policy tests, guard hash verification and diff-check pass. New source hashes
are recorded in the protected change record; no push, PR, deployment or runtime
change has occurred. Terra visual QA is required before any Sol release gate.

## 2026-09-11 Terra corrective source QA

Source QA accepts the corrected action contract: all eight handler-backed
global actions are present and the obsolete dock contract is absent. The
profile token contract contains the reviewed Human Touch light/dark-outline/
purple/lime/coral system. The local tests and hash verification are PASS.
Production deliberately remains on the earlier CAS hashes, so no old browser
evidence is reused for this changed UI. The next package must be Sol/high for
one source publication plus exact-file release; fresh desktop/mobile visual QA
is then mandatory.

## 2026-09-11 corrective production closure

PR #212 squash-merged as `fb77e62df4c`; the exact-main tree equals the tested
candidate tree `74aa9935da47…`. CAS releases
`20260911T091000Z-fb77e62d-actionbar` and
`20260911T091100Z-fb77e62d-identity` deployed the two protected PHP files at
hashes `d7682cd8…` and `30c24dca…`, both mode `0444`. Live browser QA found all
eight actions on home, Impact Challenge, Impact Shop and Profile, no dock, and
a zero-overflow/zero-ad-marker Human Touch profile on desktop and mobile. A
read-only follow-up showed the action bar fully inside the viewport and did not
reproduce the initial carousel-page overflow sample. No DB, schema, cron,
watchdog or shared dependency change was made.

## 2026-09-11 canonical quick-actions correction

Operator acceptance rejected the released legacy action set even after its
visual restyling. The required control is the canonical `sharity.hu` 2x4
`Sharity gyorsműveletek` component, not the old app action contract.

The bounded correction replaces only the action-bar markup and presentation
with the canonical order, labels, destinations and inline SVG icon geometry:
Vásárlási Segéd, Feladatok adományokért, disabled Üzenetek/Hamarosan, NGO Card,
Tippjáték, Vállalások, Profil and Közösség. The profile target remains the
environment-local `/profil/#impactshop-account-top`; the other enabled targets
match the public Human Touch component. The old Videó/Impact Shop/Adományozok/
Pontok labels and emoji icons are absent.

Source SHA-256 for `impactshop-action-bar.php` is
`f05af5bf0c6112e78bc701637217e8ad3ffabba3ac4843f955856146e15fabfd`.
PHP lint, owner-policy inventory, the focused static contract and diff-check
pass. Identity, grants, database, cron, watchdog and shared dependencies are
unchanged. Publication and live visual acceptance remain pending.
