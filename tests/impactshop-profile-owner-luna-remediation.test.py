#!/usr/bin/env python3
"""Hermetic source contracts for the Luna owner-policy remediation."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
IDENTITY = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()
POLICY = (ROOT / "wp-content/mu-plugins/000-impactshop-owner-policy.php").read_text()
VB = (ROOT / "wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php").read_text()
JS = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.js").read_text()


def section(source: str, start: str, end: str) -> str:
    left = source.index(start)
    right = source.index(end, left)
    return source[left:right]


issue = section(IDENTITY, "function impactshop_identity_owner_issue", "function impactshop_identity_owner_set_pending_cookie")
pending_cookie = section(IDENTITY, "function impactshop_identity_owner_set_pending_cookie", "function impactshop_identity_owner_compensate_pending")
restore = section(IDENTITY, "function impactshop_identity_profile_restore", "function impactshop_identity_code_generate")
activation = section(IDENTITY, "function impactshop_identity_owner_activate_pending", "function impactshop_identity_owner_issue")
renew = section(IDENTITY, "function impactshop_identity_owner_renew_coupled", "function impactshop_identity_owner_authorized")

assert "$_COOKIE['__Host-impactshop_owner']" not in pending_cookie
assert "impactshop_owner_issued_this_request" in issue
assert "binding_pending" in section(IDENTITY, "function impactshop_identity_profile_state", "function impactshop_identity_profile_votes_available")
assert "supersedes_grant_hash = impactshop_identity_owner_current_valid_grant_hash()" in restore
assert "impactshop_identity_owner_revoke_current();" not in restore
assert "impactshop_identity_queue_binding_cookies" in activation
assert activation.index("impactshop_identity_queue_binding_cookies") < activation.index("START TRANSACTION")
assert "prior_readback" in activation and "COMMIT" in activation
assert "impactshop_identity_queue_binding_cookies" in renew
assert renew.index("impactshop_identity_queue_binding_cookies") < renew.index("START TRANSACTION")
assert "SHOW TABLE STATUS" in IDENTITY and "ENGINE=InnoDB" in IDENTITY
assert "SHOW TABLE STATUS WHERE Name = %s" in IDENTITY
assert "Do not let dbDelta perform an implicit MyISAM-to-InnoDB conversion" in IDENTITY
assert "impactshop_identity_expire_binding_cookies" in activation
assert "impactshop_identity_expire_binding_cookies" in renew

assert "'GET /impact/v1/identity/total' => ['policy' => 'owner_required'" in POLICY
assert "'GET /impact/v1/ngo-selector/get' => ['policy' => 'owner_required'" in POLICY
assert "'POST /impact/v1/vb2026/select-ngo' => ['policy' => 'owner_or_service_auth'" in POLICY
assert "'POST /impact/v1/vb2026/selection-intent' => ['policy' => 'pre_auth_intent'" in POLICY
assert "'POST /impact/v1/vb2026/selection-intent/complete' => ['policy' => 'owner_or_service_auth'" in POLICY
assert "impactshop_owner_policy_callback_manifest" in POLICY
assert "impactshop_owner_policy_callback_matches" in POLICY
assert "impactshop_owner_policy_runtime_condition_enabled" in POLICY
assert "impactshop_ads_watch_debug_enabled" in POLICY
assert "foreach (impactshop_owner_policy_registry() as $pattern => $entry)" in POLICY

assert "function impactshop_vb2026_service_request_authorized" in VB
assert "hash_equals($expected, $provided)" in VB
assert "function impactshop_vb2026_owner_or_service_authorized" in VB
assert "impactshop_vb2026_browser_write_allowed" in VB
assert "The validated service header is the sole target principal" in VB
assert "if ($header !== '')" in VB
assert "malformed/invalid bearer must not fall back to a browser cookie" in VB
resolve_pseudo = section(VB, "function impactshop_vb2026_resolve_request_pseudo", "function impactshop_vb2026_browser_write_allowed")
assert resolve_pseudo.index("if ($header !== '')") < resolve_pseudo.index("$pseudo = impactshop_vb2026_get_pseudo_id()")
assert "if (!$allowServiceAuth || !preg_match" in resolve_pseudo
assert "'pseudo_id' => $headerPseudo" in resolve_pseudo
assert resolve_pseudo.count("'service_auth' => false") >= 2
assert "function isIdentityActive" in JS
assert 'if (!isIdentityActive())' in JS
assert 'btn.disabled = !isIdentityActive();' in JS
assert 'awardCredentialsSave' in JS and 'if (!isIdentityActive() || !pseudo' in JS
assert 'vacationToggle.disabled = !isIdentityActive();' in JS
assert 'pushSection.hidden = true;' in JS
assert 'impactshop_identity_restore_or_expire_pseudo_cookie' in IDENTITY
assert IDENTITY.count('impactshop_identity_restore_or_expire_pseudo_cookie') >= 3
assert "document.cookie = \"impactshop_pseudo_id" not in JS
assert "invalidateProfileCache" in JS
assert "    refreshPseudo();" not in JS
restore_handler = JS[JS.index('restBase + "/identity/restore"'):JS.index('const saveNicknameBtn')]
assert "pseudoDisplay.textContent = pseudo" not in restore_handler
assert "emitIdentityReady(pseudo)" not in restore_handler
assert "['active', 'legacy_read_only', 'invalid_or_revoked']" in IDENTITY
assert "['active', 'legacy_read_only']" in IDENTITY

print("impactshop profile owner Luna remediation: PASS")
