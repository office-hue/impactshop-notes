from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
ACTION_BAR = (ROOT / "wp-content/mu-plugins/impactshop-action-bar.php").read_text()
IDENTITY_PANEL = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()


assert '<nav class="sharity-action-bar"' in ACTION_BAR
for action in ("video", "tasks", "shop", "donate", "account", "ngo", "message", "stats"):
    assert f'data-bar="{action}"' in ACTION_BAR
assert 'sharity-profile-dock' not in ACTION_BAR
assert "home_url('/profil/#impactshop-account-top')" in ACTION_BAR
assert 'background: #fffdf7' in ACTION_BAR
assert 'background: #c9ff3d' in ACTION_BAR
assert 'id="impactshop-signin"' in IDENTITY_PANEL
assert 'impactshop-profile-shell' in IDENTITY_PANEL
assert 'impactshop-profile-intro' in IDENTITY_PANEL
assert 'Sharity · Human Touch' in IDENTITY_PANEL
assert '#dfd2ff' in IDENTITY_PANEL
assert '#c9ff3d' in IDENTITY_PANEL
assert '#f6c4d8' in IDENTITY_PANEL
