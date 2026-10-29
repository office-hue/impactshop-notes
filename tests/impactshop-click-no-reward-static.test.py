#!/usr/bin/env python3
"""CTA reward removal retains analytics and redirect entrypoints."""
from pathlib import Path
root = Path(__file__).resolve().parents[1]
mu = root / 'wp-content/mu-plugins'
click = (mu / 'impactshop-click-tracking.php').read_text()
watch = (mu / 'impactshop-ads-watch.js').read_text()
bundle = (mu / 'impactshop-ads-watch-ui-cta-bundle.js').read_text()
for forbidden in ('award_points', 'add_votes', 'points_hint', 'fallback_dedupe'):
    assert forbidden not in click, forbidden
start = watch.index('function sendCtaTracking')
end = watch.index('function onAdsManagerLoaded', start)
for forbidden in ('points', 'votes', 'dedupe_key', 'award', 'reward'):
    assert forbidden not in watch[start:end], forbidden
for forbidden in ('buildCtaDedupe', 'ctaBonus', 'pendingCtaFallbackReward', 'applyCtaTrackingReward'):
    assert forbidden not in watch, forbidden
assert 'awarded_points' not in bundle and 'awarded_votes' not in bundle
assert "'/wp-json/impact/v1/tracking/cta-click'" in watch
assert "window.open(href, '_blank', 'noopener')" in watch
assert 'function onAdClick' in watch
assert "'clickReward: '+5" not in watch
print('impactshop click no-reward static contract: PASS')
