#!/usr/bin/env python3
"""Contract checks for profile-scoped async layout reservation."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PHP = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()


def css_rule(selector: str, end: str) -> str:
    start = PHP.index(selector)
    return PHP[start:PHP.index(end, start)]


register_assets = PHP[PHP.index("function impactshop_identity_panel_register_assets"):PHP.index("function impactshop_identity_panel_enqueue_assets")]
assert "if (impactshop_identity_is_profile_route())" in register_assets
assert "impactshop_identity_panel_enqueue_assets();" in register_assets
assert "add_action('wp_enqueue_scripts', 'impactshop_identity_panel_register_assets')" in PHP


points = css_rule(
    ".impactshop-identity-points[data-role=points-section][hidden]",
    "\n",
)
points_visible = css_rule(
    ".impactshop-identity-points[data-role=points-section] {",
    "\n",
)
compact = css_rule(
    ".impactshop-identity-compact[data-role=points-compact][hidden]",
    "\n",
)
push = css_rule(".impactshop-identity-push[data-role=push-section]", "\n")
push_hidden = css_rule(".impactshop-identity-push[data-role=push-section][hidden]", "\n")
votes = css_rule(".impactshop-identity-votes[data-role=votes-summary]", "\n")
history = css_rule(".impactshop-identity-history", "\n")
last_ngo = css_rule(".impactshop-identity-lastngo", "\n")

assert "min-height: 157px" in push
assert "box-sizing: border-box" in push
assert "display: block" in push_hidden
assert "visibility: hidden" in push_hidden
assert "min-height: 265px" in points_visible
assert "display: block" in points
assert "visibility: hidden" in points
assert "display: block" in compact
assert "visibility: hidden" in compact
assert "min-height: 72px" in compact
assert "min-height: 139px" in votes
assert "min-height: 75px" in history
assert "min-height: 72px" in last_ngo

mobile_css = PHP[PHP.index("@media (max-width: 640px)"):PHP.index("@media (prefers-contrast", PHP.index("@media (max-width: 640px)"))]
assert ".impactshop-identity-push[data-role=push-section] { min-height: 181px; }" in mobile_css
assert ".impactshop-identity-points[data-role=points-section] { min-height: 301px; }" in mobile_css

print("impactshop profile CLS contract: PASS")
