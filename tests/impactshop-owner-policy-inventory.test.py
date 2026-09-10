#!/usr/bin/env python3
"""Checkpoint B static policy/inventory contract."""

from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
POLICY = (ROOT / "wp-content/mu-plugins/000-impactshop-owner-policy.php").read_text()
IDENTITY = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()
ACTION_BAR = (ROOT / "wp-content/mu-plugins/impactshop-action-bar.php").read_text()

assert "token_get_all" in (ROOT / "scripts/impactshop-owner-policy-inventory.php").read_text()
assert "IMPACTSHOP_OWNER_POLICY_VERSION = 2" in POLICY
assert "rest_request_before_callbacks" in POLICY
assert "impactshop_owner_policy_runtime_self_test" in POLICY
assert "impactshop_owner_policy_safe_disable_set(true)" in POLICY
assert "service_auth" in POLICY and "admin_capability" in POLICY
assert "explicitly_public_non_identity/read_only" in POLICY
assert "access_code_exchange" in POLICY
assert "POST /impact/v1/identity/restore' => ['policy' => 'access_code_exchange'" in POLICY

owner_routes = [
    "POST /impact/v1/identity/profile",
    "POST /impact/v1/identity/restore",
    "POST /impact/v1/identity/code/generate",
    "POST /impact/v1/ngo-selector/set",
    "POST /impact/v1/saved-offers/save",
    "POST /impact/v1/push/subscribe",
    "POST /impact/v1/push/unsubscribe",
    "POST /impact/v1/tracking/cta-click",
    "POST /impact/v1/ads-watch/view",
    "POST /impact/v1/ads-watch/education",
    "POST /impact/v1/ads-watch/allocate",
    "POST /impact/v1/ads-watch/set-ngo",
    "POST /impact/v1/ads-watch/set-auto-vote",
    "POST /impact/v1/vote/view",
    "POST /impact/v1/vote/cast",
    "POST /impact/v1/identity/message-read",
    "POST /sharity/v1/pseudo/points/earn",
    "POST /sharity/v1/pseudo/vacation",
    "POST /sharity/v1/pseudo/vacation/end",
    "POST /sharity/v1/pseudo/last-ngo",
    "POST /sharity/v1/pseudo/feedback",
    "POST /sharity/v1/pseudo/video-ad",
    "POST /impact/v1/vb2026/select-ngo",
    "POST /impact/v1/vb2026/selection-intent",
    "POST /impact/v1/vb2026/selection-intent/complete",
]
for route in owner_routes:
    assert f"'{route}'" in POLICY, route

assert "state varchar(16)" in IDENTITY
assert "activated_at datetime" in IDENTITY
assert "supersedes_grant_hash" in IDENTITY
assert "START TRANSACTION" in IDENTITY
assert "FOR UPDATE" in IDENTITY
assert "COMMIT" in IDENTITY
assert "ROLLBACK" in IDENTITY
assert "random_bytes(32)" in IDENTITY
assert "state = 'pending'" in IDENTITY
assert "state = 'active'" in IDENTITY
assert "impactshop_identity_owner_renew_coupled" in IDENTITY
assert "impactshop_identity_request_same_origin" in IDENTITY
assert "HTTP_X_FORWARDED_HOST" not in IDENTITY
assert "app.sharity.hu" not in IDENTITY
activation = IDENTITY[IDENTITY.index("function impactshop_identity_owner_activate_pending"):IDENTITY.index("function impactshop_identity_owner_issue")]
assert activation.index("START TRANSACTION") < activation.index("FOR UPDATE") < activation.index("COMMIT")
assert "supersedes_grant_hash" in activation
assert "ROLLBACK" in activation
assert "home_url('/profil/#impactshop-account-top')" in ACTION_BAR or "home_url('/profil/#impactshop-account-top')" in IDENTITY
assert "#impactshop-account'" in ACTION_BAR  # legacy hash resolver remains

proc = subprocess.run(
    ["php", str(ROOT / "scripts/impactshop-owner-policy-inventory.php")],
    cwd=ROOT,
    check=False,
    capture_output=True,
    text=True,
)
assert proc.returncode == 0, proc.stdout + proc.stderr
print("impactshop owner-policy inventory: PASS")
