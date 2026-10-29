<?php
/** Pure economics: no external services, database or live state. */
define('ABSPATH', __DIR__ . '/');
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_json_encode($value) { return json_encode($value); }
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-money.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-ledger-sync.php';
function check($ok, $message) { if (!$ok) { throw new RuntimeException($message); } }
$options = ['sharity_points_impactshop_cutover_utc' => '2026-10-29 10:00:00'];
$root = ['state' => 'pending', 'subject_digest' => 'bound', 'original_purchase_at' => '2026-10-29 10:00:00', 'awarded_points' => 0];
$event = ['reason' => '', 'kind' => 'award', 'authority_ref' => 'signed-policy-v1', 'points' => 100];
$r = sharity_points_impactshop_decide($root, $event, 20);
check($r['delta'] === 100 && $r['state'] === 'awarded', 'Equality at cutover eligible');
$root['state'] = 'awarded'; $root['awarded_points'] = 100; $event['kind'] = 'refund';
$r = sharity_points_impactshop_decide($root, $event, 20);
check($r['delta'] === -20 && $r['forgiven'] === 80 && $r['state'] === 'reversed', 'Only actual available points reversed');
$r = sharity_points_impactshop_decide($root, $event, 0);
check($r['delta'] === 0 && $r['forgiven'] === 100 && $r['state'] === 'reversed', 'Zero balance still closes refund');
$root['state'] = 'reversed';
check(sharity_points_impactshop_decide($root, $event, 500)['status'] === 'manual', 'Repeated correction does not recover forgiven points');
$root['state'] = 'pending'; $root['original_purchase_at'] = '2026-10-29 09:59:59';
check(sharity_points_impactshop_decide($root, $event, 500)['status'] === 'manual', 'Historical purchase never backfilled');
$root['original_purchase_at'] = null;
check(sharity_points_impactshop_decide($root, $event, 500)['status'] === 'manual', 'Unknown time not guessed');
$root['original_purchase_at'] = '2026-10-29 10:00:00'; $event['kind'] = 'pending';
check(sharity_points_impactshop_decide($root, $event, 0)['state'] === 'pending', 'Pending creates no award');
$root['state'] = 'manual'; $event['kind'] = 'award';
check(sharity_points_impactshop_decide($root, $event, 0)['state'] === 'manual', 'Manual root stays manual');
echo "Impact Shop state cases passed\n";
