#!/usr/bin/env python3
"""Checkpoint-A source contracts for profile summary, cache and ad boundaries."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PHP = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()
JS = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.js").read_text()
ADS = (ROOT / "wp-content/mu-plugins/impactshop-adsense-head.php").read_text()


def section(source: str, start: str, end: str) -> str:
    left = source.index(start)
    right = source.index(end, left)
    return source[left:right]


assert "'votes_available'" in section(PHP, "function impactshop_identity_profile_get", "function impactshop_identity_profile_update")
assert "'identity_state'" in section(PHP, "function impactshop_identity_profile_get", "function impactshop_identity_profile_update")
assert all(state in PHP for state in (
    "binding_pending",
    "active",
    "legacy_read_only",
    "invalid_or_revoked",
    "unavailable",
))

votes = section(PHP, "function impactshop_identity_profile_votes_available", "function impactshop_identity_profile_response_headers")
assert "SELECT available_votes" in votes
assert "INSERT" not in votes.upper()
assert "UPDATE" not in votes.upper()
assert "max(0, (int) $raw)" in votes

assert "impactshop_identity_is_profile_route" in ADS
assert "impactshop_identity_profile_home_path" in PHP
assert "impactshop_identity_profile_reveal_cookie_path" in PHP
assert "impactshop_identity_profile_route_path() !== '/profil/belepesi-kod'" in PHP
assert "googlesitekit_adsense_enabled" in PHP
assert "impactshop_identity_profile_remove_site_kit_adsense_tag" in PHP
assert "impactshop_identity_profile_remove_site_kit_adsense_tag();" in PHP
assert "PHP_INT_MAX" in PHP
assert "Google\\\\Site_Kit\\\\Modules\\\\AdSense" in PHP
assert "wp_dequeue_script" in PHP
assert "elementor/frontend/widget/before_render" in PHP
assert "set_should_render(false)" in PHP
assert "impactshop_identity_profile_settings_contain_adsense_signature" in PHP
assert "pagead2.googlesyndication.com" in PHP
assert "adsbygoogle" in PHP
assert "<script" in PHP
assert "<ins" in PHP
assert "preg_match" in PHP
assert "get_settings_for_display" in PHP
assert "$widget_name === 'html'" in PHP
assert "'secure' => true" in PHP
assert "Cache-Control', 'private, no-store, no-cache" in PHP
assert "rest_post_dispatch" in PHP
assert "$owner_issued = impactshop_identity_owner_issue($pseudo_id);" in PHP
assert "if ($owner_issued)" in PHP
assert "'Nincs becenév'" in PHP
assert 'data-role="nickname-display"' in PHP
assert 'data-role="votes-available"' in PHP
assert "Belépés meglévő fiókba" in PHP
assert "másik ellenőrzött azonosító nélkül nem állítható helyre" in PHP
assert "impactshop-account-top" in PHP
assert "renderNickname" in JS
assert "identity_state" in JS
assert "votes_available" in JS
assert "Nincs becenév" in JS

print("impactshop profile summary checkpoint A: PASS")
