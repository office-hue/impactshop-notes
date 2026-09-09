#!/usr/bin/env python3
"""Static contract checks for the Sharity profile identity v2 package."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PHP = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()
JS = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.js").read_text()
ADS = (ROOT / "wp-content/mu-plugins/impactshop-adsense-head.php").read_text()


def section(source: str, start: str, end: str) -> str:
    left = source.index(start)
    right = source.index(end, left)
    return source[left:right]


profile_get = section(PHP, "function impactshop_identity_profile_get", "function impactshop_identity_profile_update")
assert "recovery_code" not in profile_get
assert "access_code_state" in profile_get
assert "identity/code/generate" in PHP
assert "impactshop_identity_render_code_page" in PHP
assert "__Host-impactshop_owner" in PHP
assert "impactshop_identity_owner_authorized" in PHP
assert "impactshop_identity_owner_issue" in PHP
assert "impactshop_identity_request_same_origin" in PHP
assert "if (!is_ssl())" in PHP
code_generate = section(PHP, "function impactshop_identity_code_generate", "function impactshop_identity_profile_cookie")
assert "impactshop_identity_owner_authorized($pseudo_id)" in code_generate
assert "impactshop_identity_request_same_origin()" in code_generate
restore = section(PHP, "function impactshop_identity_profile_restore", "function impactshop_identity_code_generate")
assert "impactshop_identity_owner_revoke_current();" in restore
assert restore.index("impactshop_identity_owner_revoke_current();") < restore.index("impactshop_identity_owner_issue($pseudo_id)")
assert "script-src 'none'" in PHP
assert "Cache-Control: private, no-store, max-age=0" in PHP
assert "wp_hash_password('sharity-access-v2|" in PHP
assert "wp_check_password('sharity-access-v2|" in PHP
assert "Hibás azonosító vagy belépési kód." in PHP
assert "Belépés meglévő fiókba" in PHP
assert 'data-role="generate-code"' in PHP
assert 'data-role=generate-code' in JS
assert "data.recovery_code" not in JS
assert "str_starts_with(untrailingslashit($path), '/profil/')" in ADS

print("impactshop identity profile v2 static: PASS")
