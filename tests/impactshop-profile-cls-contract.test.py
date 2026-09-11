#!/usr/bin/env python3
"""Contract checks for profile-scoped async layout reservation."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PHP = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()


def css_rule(selector: str, end: str) -> str:
    start = PHP.index(selector)
    return PHP[start:PHP.index(end, start)]


points = css_rule(
    ".impactshop-identity-points[data-role=points-section][hidden]",
    "\n",
)
compact = css_rule(
    ".impactshop-identity-compact[data-role=points-compact][hidden]",
    "\n",
)
last_ngo = css_rule(".impactshop-identity-lastngo", "\n")

assert "display: block" in points
assert "visibility: hidden" in points
assert "min-height: 250px" in points
assert "display: block" in compact
assert "visibility: hidden" in compact
assert "min-height: 72px" in compact
assert "min-height: 72px" in last_ngo

print("impactshop profile CLS contract: PASS")
