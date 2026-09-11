from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
ACTION_BAR = (ROOT / "wp-content/mu-plugins/impactshop-action-bar.php").read_text()
IDENTITY_PANEL = (ROOT / "wp-content/mu-plugins/impactshop-identity-panel.php").read_text()


assert '<nav class="sharity-profile-dock"' in ACTION_BAR
assert '<nav class="sharity-action-bar"' not in ACTION_BAR
assert "home_url('/profil/#impactshop-account-top')" in ACTION_BAR
assert "home_url('/profil/#impactshop-signin')" in ACTION_BAR
assert 'data-profile-dock-name' in ACTION_BAR
assert 'data-profile-dock-meta' in ACTION_BAR
assert "identity/profile" in ACTION_BAR
assert "pseudo/points" in ACTION_BAR
assert "votes_available" in ACTION_BAR
assert "identity_state === 'active'" in ACTION_BAR
assert "Belépés" in ACTION_BAR
assert "meglévő fiókba" in ACTION_BAR
assert 'id="impactshop-signin"' in IDENTITY_PANEL
assert 'impactshop-profile-shell' in IDENTITY_PANEL
assert 'impactshop-profile-intro' in IDENTITY_PANEL
assert 'Sharity · Human Touch' in IDENTITY_PANEL
