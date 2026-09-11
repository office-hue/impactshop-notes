from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
ACTION_BAR = (ROOT / "wp-content/mu-plugins/impactshop-action-bar.php").read_text()
IDENTITY_PANEL = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()


assert '<nav class="sharity-action-bar" aria-label="Sharity gyorsműveletek" data-sharity-quick-dock="true">' in ACTION_BAR
NAV = ACTION_BAR.split(
    '<nav class="sharity-action-bar" aria-label="Sharity gyorsműveletek" data-sharity-quick-dock="true">',
    1,
)[1].split("</nav>", 1)[0]
actions = (
    "shopping-assistant",
    "offerwall",
    "messages-upcoming",
    "ngo-card",
    "tip-game",
    "commitments",
    "account",
    "community",
)
for action in actions:
    assert f'data-bar="{action}"' in NAV
positions = [NAV.index(f'data-bar="{action}"') for action in actions]
assert positions == sorted(positions)
for label in (
    "Vásárlási Segéd",
    "Feladatok adományokért",
    "Üzenetek",
    "Hamarosan",
    "NGO Card",
    "Tippjáték",
    "Vállalások",
    "Profil",
    "Közösség",
):
    assert label in ACTION_BAR
for retired_label in (">Videó<", ">Impact Shop<", ">Adományozok<", ">Pontok<"):
    assert retired_label not in ACTION_BAR
assert 'sharity-profile-dock' not in ACTION_BAR
assert "home_url('/profil/#impactshop-account-top')" in ACTION_BAR
assert "'https://sharity.hu/vasarlasi-seged'" in ACTION_BAR
assert "'https://sharity.hu/offerwall'" in ACTION_BAR
assert "'https://sharity.hu/ngo-kartyak'" in ACTION_BAR
assert "'https://factlens.eu/factlens/vb-prod/'" in ACTION_BAR
assert "'https://sharity.hu/vallalasok'" in ACTION_BAR
assert "'https://sharity.hu/hatas-korok'" in ACTION_BAR
assert 'disabled aria-disabled="true"' in ACTION_BAR
assert '<svg viewBox="0 0 24 24">' in ACTION_BAR
assert 'grid-template-columns: repeat(4, 1fr)' in ACTION_BAR
assert 'width: min(720px, calc(100vw - 16px))' in ACTION_BAR
assert 'background: #fff7d9' in ACTION_BAR
assert 'background: #f4ffd7' in ACTION_BAR
assert 'background: #fff0f3' in ACTION_BAR
assert 'background: #ede5ff' in ACTION_BAR
assert 'id="impactshop-signin"' in IDENTITY_PANEL
assert 'impactshop-profile-shell' in IDENTITY_PANEL
assert 'impactshop-profile-intro' in IDENTITY_PANEL
assert 'Sharity · Human Touch' in IDENTITY_PANEL
assert '#dfd2ff' in IDENTITY_PANEL
assert '#c9ff3d' in IDENTITY_PANEL
assert '#f6c4d8' in IDENTITY_PANEL
