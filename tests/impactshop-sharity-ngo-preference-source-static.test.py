#!/usr/bin/env python3
"""Static contract checks for the disabled Sharity NGO preference adapter."""

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP_PATH = ROOT / "wp-content/mu-plugins/impactshop-sharity-ngo-preference-source.php.off"
SOURCE = PHP_PATH.read_text()

assert PHP_PATH.name.endswith(".php.off")
assert "SHARITY_NGO_PREF_SOURCE_VERSION = 'v1'" in SOURCE
assert "register_activation_hook" not in SOURCE
assert "dbDelta" not in SOURCE
assert "wp_remote_" not in SOURCE
assert "curl_" not in SOURCE
assert "impactshop_ngo_selector" not in SOURCE
assert "impactshop_identity_request_same_origin" not in SOURCE
assert "impactshop_identity_owner_authorized" in SOURCE
assert "error_log(" not in SOURCE
assert "SHARITY_NGO_PREF_CACHE_CONTROL = 'private, no-store'" in SOURCE
assert "'path' => '/identity/web-session/authorize'" in SOURCE
assert "'path' => '/ngo-preferences/{scope_key}'" in SOURCE
assert "sharity_ngo_pref_catalog_revision" in SOURCE
assert "sharity_ngo_pref_subject_key" in SOURCE
assert "sharity_ngo_pref_redeem_code" in SOURCE
assert "sharity_ngo_pref_cas_update" in SOURCE
assert "policy_allow_user_selection" in SOURCE
assert "campaign" not in SOURCE.lower()

print("sharity NGO preference source static: PASS")
