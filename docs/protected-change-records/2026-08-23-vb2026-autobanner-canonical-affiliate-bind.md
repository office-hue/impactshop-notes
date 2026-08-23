# 2026-08-23 VB2026 autobanner canonical affiliate bind

## Summary and approval

The operator explicitly approved cross-repository/worktree work and requested that the live VB2026 autobanner use the new canonical Sharity Shopping route without asking for a second NGO choice. This change admits one exact new source, `vb2026-autobanner`, to the already deployed opaque SAT1 affiliate lifecycle.

## Protected files touched and affected functions

- `wp-content/mu-plugins/impactshop-boot.php`: `isb_handle_go` unknown-shop privacy branch and exact source activation branch.
- `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`: context validation and exact source persistence.
- `scripts/sharity-affiliate-runtime-bastion-guard.sh`: maximum source-gate invariants.
- `tests/sharity-affiliate-runtime-bastion.test.sh`: tamper proof for broad source admission.
- `docs/bastion-guard-status.md`: release-only bastion evidence and deployed
  checksums; no runtime policy change.

The exact VB source uses existing prepare, mark-redirected, Dognet generation, click log redaction, correlation and retention functions. Shopping Assistant and every non-admitted legacy source remain behaviorally unchanged.

## Coherence, risk and security decision

- The VB gateway supplies the NGO slug from the already selected canonical VB profile. ImpactShop does not select or write an NGO.
- The provider receives opaque SAT1 as Dognet Data 1 and blank raw pseudo/data5. Local storage retains HMAC subject, NGO and exact source placement.
- Exact `vb2026-autobanner` is required instead of pretending the click came from `shopping-assistant`; this keeps future commission reporting truthful.
- The live provider remains Dognet-only. CJ and future adapters are not activated.
- No purchase, commission, donation, reward, points, vote or settlement truth is written.
- Unknown, approximate or third source values are not admitted to SAT1.
- No schema or scheduler change is required. Existing 45-day retention and central watchdog supervision remain authoritative.

## Backup and rollback

Before any live protected-file release, record exact current SHA-256, owner and mode for both PHP targets and create the canonical release-engine backup. Deploy only exact reviewed main with compare-and-swap, staged PHP lint and atomic apply.

Rollback order:

1. disable `impactshop_sharity_affiliate_runtime_enabled` to exact string `0`;
2. preserve the intent table and cleanup cron;
3. restore the two exact protected files from the release backup with expected-deployed SHA checks;
4. verify ordinary Shopping Assistant and legacy redirect behavior.

## Required validation and smoke checklist

- PHP lint, runtime lifecycle, maximum bastion/tamper, strict repo audit and `git diff --check`.
- Confirm a VB2026 user with an existing NGO choice sees no new chooser.
- Click one human canary autobanner and confirm the exact product page opens.
- Confirm Dognet Data 1 has SAT1 shape and no raw pseudo/data5.
- Internally correlate SAT1 to the expected NGO and `source_placement=vb2026-autobanner`; do not treat this as purchase or commission proof.
- Confirm an ordinary Shopping Assistant product click still works.
- Confirm an unknown source cannot enter the SAT1 lifecycle.
- Confirm the frozen `/factlens/vb/` surface and autobanner upstream were not changed.

Automated tests must not generate live affiliate clicks.

## Production release evidence

- Source PR `#182` merged as `4ab348480ead24a6a3cbbd2136bee7b08a179bae`.
- Runtime release `20260823T092444Z-4ab348480ead-17e8ae00` changed exact
  before SHA `4347dded2ad009b5fe793836b57bbb163f3ffe94e55c0ed6dedeff93e0ef4859`
  to deployed SHA `0c49b041cc81865cc0190807cf62180863c0a82818d0ef4f4fac7c4e713d92e4`.
- Boot release `20260823T092538Z-4ab348480ead-13d6733f` changed exact before
  SHA `e05a538fe4fdc5ca7af4220e03e3924cd4090f0d2ca5adf7c2f355cad545ba06`
  to deployed SHA `845d284f8869eb18b131de935e21a27702f8cf324299d519004dc1090f44c67a`.
- Both release manifests report phase `deployed`, exact current SHA and
  executable rollback truth. Both PHP targets are owned by `sharityh`, mode
  `0444`; the `mu-plugins` parent is `0555`; remote PHP lint passed.
- The existing central watchdog was rebound, with crontab backup, to a clean
  exact-main runtime. Postactivation admission is `ADMITTED`: option/schema,
  table, one cleanup hook, next-run and affiliate watchdog signal are coherent.
  Global unrelated watchdog findings remain visible warnings.
- No automated affiliate click was made. The final product-deeplink/SAT1/NGO
  correlation remains one explicit human canary.

Rollback remains disable-first. Runtime rollback uses release
`20260823T092444Z-4ab348480ead-17e8ae00` plus deployed SHA `0c49b041...d92e4`;
boot rollback uses release `20260823T092538Z-4ab348480ead-13d6733f` plus
deployed SHA `845d284f...c67a`.
