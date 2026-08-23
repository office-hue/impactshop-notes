# 2026-08-23 VB2026 autobanner canonical affiliate bind

## Summary and approval

The operator explicitly approved cross-repository/worktree work and requested that the live VB2026 autobanner use the new canonical Sharity Shopping route without asking for a second NGO choice. This change admits one exact new source, `vb2026-autobanner`, to the already deployed opaque SAT1 affiliate lifecycle.

## Protected files touched and affected functions

- `wp-content/mu-plugins/impactshop-boot.php`: `isb_handle_go` unknown-shop privacy branch and exact source activation branch.
- `wp-content/mu-plugins/impactshop-sharity-affiliate-runtime.php`: context validation and exact source persistence.
- `scripts/sharity-affiliate-runtime-bastion-guard.sh`: maximum source-gate invariants.
- `tests/sharity-affiliate-runtime-bastion.test.sh`: tamper proof for broad source admission.

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
