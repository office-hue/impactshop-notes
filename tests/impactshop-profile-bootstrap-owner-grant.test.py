#!/usr/bin/env python3
"""Hermetic contracts for first-request owner-grant bootstrap ordering."""

from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[1]
BOOT = (ROOT / "wp-content/mu-plugins/impactshop-boot.php").read_text()
IDENTITY = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()


def section(source: str, start: str, end: str) -> str:
    left = source.index(start)
    right = source.index(end, left)
    return source[left:right]


bootstrap = section(
    IDENTITY,
    "function impactshop_identity_maybe_bootstrap_owner_binding",
    "function impactshop_identity_maybe_install_owner_grants",
)

# The storage installer and bootstrap must precede impactshop-boot's priority-1
# compatibility callback. This models the real MU-plugin load/registration
# order without requiring a WordPress or database runtime.
assert "add_action('init', 'impactshop_identity_maybe_install_owner_grants', 0);" in IDENTITY
assert "add_action('init', 'impactshop_identity_maybe_bootstrap_owner_binding', 0);" in IDENTITY
assert re.search(r"add_action\('init', 'impactshop_identity_ensure_pseudo_cookie', 1\);", BOOT)

# Ordinary HTML only: REST, wp-json and query identity overrides remain out
# of the new issuance path, while legacy cookie names remain compatible.
assert "impactshop_identity_should_touch_cookie()" in bootstrap
assert "$_GET['impact_pseudo_id']" in bootstrap
assert "$_COOKIE['impactshop_pseudo_id']" in bootstrap
assert "$_COOKIE['impact_pseudo_id']" in bootstrap
assert "$_COOKIE['impact_pseudo']" in bootstrap
assert "if ($query_pseudo !== '')" in bootstrap

# One compensated lifecycle: issue exactly once, queue pseudo then owner, and
# compensate/expire on either cookie failure. No owner token is copied into
# request cookies or response bodies.
assert bootstrap.count("impactshop_identity_owner_issue($pseudo_id)") == 1
assert "impactshop_identity_profile_set_cookie($pseudo_id)" in bootstrap
assert "impactshop_identity_owner_set_pending_cookie()" in bootstrap
assert "impactshop_identity_owner_compensate_pending()" in bootstrap
assert "impactshop_identity_restore_or_expire_pseudo_cookie('', $pseudo_id)" in bootstrap
assert "$_COOKIE['__Host-impactshop_owner']" not in bootstrap
assert "$_COOKIE['impactshop_pseudo_id'] = $pseudo_id" in bootstrap
assert "impactshop_identity_bootstrap_block_legacy" in bootstrap
assert "impactshop_identity_bootstrap_block_legacy" in BOOT

resolve = section(
    IDENTITY,
    "function impactshop_identity_profile_resolve",
    "function impactshop_identity_current_url",
)
profile_get = section(
    IDENTITY,
    "function impactshop_identity_profile_get",
    "function impactshop_identity_profile_update",
)
assert "impactshop_identity_bootstrap_block_legacy" in resolve
assert "impactshop_identity_bootstrap_block_legacy" in profile_get
assert "'identity_state'    => 'unavailable'" in resolve
assert "'identity_state'    => 'unavailable'" in profile_get

# The legacy callback remains present as a compatibility fallback; with the
# in-memory pseudo set above it cannot issue a duplicate first-request grant.
assert "function impactshop_identity_ensure_pseudo_cookie" in BOOT
assert "impactshop_identity_set_pseudo_cookie($generated)" in BOOT
assert "impactshop_owner_issued_this_request" in IDENTITY

print("impactshop profile bootstrap owner-grant: PASS")
