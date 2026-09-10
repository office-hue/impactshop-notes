#!/usr/bin/env python3
"""Checkpoint B static policy/inventory contract."""

from pathlib import Path
import json
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
POLICY = (ROOT / "wp-content/mu-plugins/000-impactshop-owner-policy.php").read_text()
IDENTITY = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()
ACTION_BAR = (ROOT / "wp-content/mu-plugins/impactshop-action-bar.php").read_text()

assert "token_get_all" in (ROOT / "scripts/impactshop-owner-policy-inventory.php").read_text()
assert "IMPACTSHOP_OWNER_POLICY_VERSION = 3" in POLICY
assert "rest_request_before_callbacks" in POLICY
assert "impactshop_owner_policy_runtime_self_test" in POLICY
assert "impactshop_owner_policy_safe_disable_set(true)" in POLICY
assert "service_auth" in POLICY and "admin_capability" in POLICY
assert "explicitly_public_non_identity/read_only" in POLICY
assert "access_code_exchange" in POLICY
assert "owner_or_service_auth" in POLICY
assert "pre_auth_intent" in POLICY
assert "impactshop_owner_policy_callback_manifest" in POLICY
assert "impactshop_owner_policy_callback_matches" in POLICY
assert "impactshop_owner_policy_runtime_condition_enabled" in POLICY
assert "impactshop_ads_watch_debug_enabled" in POLICY
runtime_self_test = POLICY[POLICY.index("function impactshop_owner_policy_runtime_self_test"):]
assert "if (!$condition_enabled && !isset($routes[$route]))" in runtime_self_test
assert "impactshop_owner_policy_callback_matches" in runtime_self_test
assert "registered_callbacks" in (ROOT / "scripts/impactshop-owner-policy-inventory.php").read_text()
assert "callback_mismatches" in (ROOT / "scripts/impactshop-owner-policy-inventory.php").read_text()
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
assert "ENGINE=InnoDB" in IDENTITY
assert "SHOW TABLE STATUS" in IDENTITY
assert "impactshop_identity_request_same_origin" in IDENTITY
assert "HTTP_X_FORWARDED_HOST" not in IDENTITY
assert "app.sharity.hu" not in IDENTITY
activation = IDENTITY[IDENTITY.index("function impactshop_identity_owner_activate_pending"):IDENTITY.index("function impactshop_identity_owner_issue")]
assert activation.index("START TRANSACTION") < activation.index("FOR UPDATE") < activation.index("COMMIT")
assert "supersedes_grant_hash" in activation
assert "SELECT grant_hash, revoked_at" in activation
assert "prior_readback" in activation
issue = IDENTITY[IDENTITY.index("function impactshop_identity_owner_issue"):IDENTITY.index("function impactshop_identity_owner_set_pending_cookie")]
assert "START TRANSACTION" in issue and "supersedes_grant_hash" in issue
assert "UPDATE {$table} SET revoked_at" not in issue, "superseded grant must remain active until pending activation"
assert "ROLLBACK" in activation
assert "home_url('/profil/#impactshop-account-top')" in ACTION_BAR or "home_url('/profil/#impactshop-account-top')" in IDENTITY
assert "#impactshop-account'" in ACTION_BAR  # legacy hash resolver remains
assert "GET /impact/v1/identity/total' => ['policy' => 'owner_required'" in POLICY
assert "GET /impact/v1/ngo-selector/get' => ['policy' => 'owner_required'" in POLICY
assert POLICY.count("'GET /impact/v1/identity/total' => ['policy'") == 1
assert POLICY.count("'GET /impact/v1/ngo-selector/get' => ['policy'") == 1

proc = subprocess.run(
    ["php", str(ROOT / "scripts/impactshop-owner-policy-inventory.php")],
    cwd=ROOT,
    check=False,
    capture_output=True,
    text=True,
)
assert proc.returncode == 0, proc.stdout + proc.stderr


def inventory_fixture() -> Path:
    fixture = Path(tempfile.mkdtemp(prefix="impactshop-owner-inventory-"))
    (fixture / "scripts").mkdir(parents=True)
    (fixture / "wp-content/mu-plugins").mkdir(parents=True)
    shutil.copy2(ROOT / "scripts/impactshop-owner-policy-inventory.php", fixture / "scripts")
    for relative in [
        "000-impactshop-owner-policy.php",
        "impactshop-identity-panel.php",
        "impactshop-ngo-selector.php",
        "impactshop-saved-offers.php",
        "impactshop-pwa-push.php",
        "impactshop-click-tracking.php",
        "impactshop-ads-watch.php",
        "impactshop-vote-jysk.php",
        "sharity-points-api.php",
        "sharity-points-events.php",
        "impactshop-vb2026-ngo-catalog.php",
    ]:
        shutil.copy2(ROOT / "wp-content/mu-plugins" / relative, fixture / "wp-content/mu-plugins" / relative)
    return fixture


fixture = inventory_fixture()
try:
    identity_path = fixture / "wp-content/mu-plugins/impactshop-identity-panel.php"
    identity_source = identity_path.read_text()
    identity_path.write_text(identity_source.replace(
        "'callback'            => 'impactshop_identity_profile_get'",
        "'callback'            => 'impactshop_identity_profile_get_drift'",
        1,
    ))
    drift = subprocess.run(
        ["php", str(fixture / "scripts/impactshop-owner-policy-inventory.php")],
        cwd=fixture,
        check=False,
        capture_output=True,
        text=True,
    )
    drift_payload = json.loads(drift.stdout)
    assert drift.returncode != 0
    assert "GET /impact/v1/identity/profile" in drift_payload["callback_mismatches"]
finally:
    shutil.rmtree(fixture)

fixture = inventory_fixture()
try:
    policy_path = fixture / "wp-content/mu-plugins/000-impactshop-owner-policy.php"
    policy_source = policy_path.read_text()
    policy_path.write_text(policy_source.replace(
        "'GET /impact/v1/identity/profile' => $public('impactshop-identity-panel.php')",
        "'GET /impact/v1/identity/total' => $public('impactshop-identity-panel.php')",
        1,
    ))
    duplicate = subprocess.run(
        ["php", str(fixture / "scripts/impactshop-owner-policy-inventory.php")],
        cwd=fixture,
        check=False,
        capture_output=True,
        text=True,
    )
    duplicate_payload = json.loads(duplicate.stdout)
    assert duplicate.returncode != 0
    assert "GET /impact/v1/identity/total" in duplicate_payload["duplicate_policy_routes"]
finally:
    shutil.rmtree(fixture)

print("impactshop owner-policy inventory: PASS")
