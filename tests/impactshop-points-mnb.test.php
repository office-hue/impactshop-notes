<?php
/** Exact approved policy and actual official MNB HTML fixture; no network. */
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-money.php';
function check($value, $message) { if (!$value) { throw new RuntimeException($message); } }
$fixture = file_get_contents(__DIR__ . '/fixtures/impactshop-mnb-usd-20261029.html');
check(sharity_points_money_affiliate_donation('1', '329.01')['points'] === 165, '50% is applied before the only rounding');
check(sharity_points_money_affiliate_donation('0.01', '100')['points'] === 1, 'Half HUF rounds up once');
check(sharity_points_money_affiliate_donation('1.01', '100')['points'] === 51, 'No preliminary commission rounding');
check(sharity_points_money_affiliate_donation('100', '209.01', '100')['points'] === 105, 'MNB quote unit100 is preserved');
foreach ([[1.01, '329.93'], ['1', 329.93], ['0', '329.93'], ['1', '0'], ['-1', '329.93']] as $input) {
    check(!sharity_points_money_affiliate_donation(...$input)['success'], 'No float/negative/zero fallback');
}
check(!sharity_points_money_affiliate_donation('1', '1', '3')['success'], 'Unsupported unit does not round during division');
$q = sharity_points_mnb_parse($fixture, 'USD', '2026-10-28');
check($q === ['success' => true, 'currency' => 'USD', 'date' => '2026-10-28', 'rate' => '329.93', 'unit' => '1'], 'Last quote at or before final day');
check(sharity_points_mnb_parse($fixture, 'USD', '2026-10-31')['date'] === '2026-10-29', 'Weekend uses actual preceding quote day');
check(!sharity_points_mnb_parse($fixture, 'EUR', '2026-10-29')['success'], 'Wrong currency rejected');
check(!sharity_points_mnb_parse(str_replace('329,93', '-1', $fixture), 'USD', '2026-10-29')['success'], 'Malformed quote rejected');
check(!sharity_points_mnb_parse(str_replace('október 28.', 'február 30.', $fixture), 'USD', '2026-10-29')['success'], 'Invalid calendar date rejected');
check(!sharity_points_mnb_parse('<html>Maintenance</html>', 'USD', '2026-10-29')['success'], 'Successful HTTP without a quote is not success');
check(!sharity_points_mnb_parse($fixture, 'USD', '2026-10-01')['success'], 'No quote before date is explicit');
$requests = []; $saved = [];
function get_transient($key) { return $GLOBALS['saved'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['saved'][$key] = $value; }
function is_wp_error($r) { return false; }
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = [$url, $args];
    return ['status' => str_contains($url, 'devizaSelected=USD') ? 200 : 429, 'body' => $GLOBALS['fixture']];
}
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
check(sharity_points_mnb_quote('USD', '2026-10-28T12:00:00Z')['rate'] === '329.93', 'Official form transport parsed');
check(sharity_points_mnb_quote('USD', '2026-10-28T15:00:00Z')['rate'] === '329.93' && count($requests) === 1, 'One request per day/currency');
check(str_starts_with($requests[0][0], 'https://www.mnb.hu/arfolyam-tablazat?query&'), 'Only official HTTPS form');
check($requests[0][1]['redirection'] === 0 && $requests[0][1]['sslverify'] === true, 'No unverified redirect/fallback');
check(!sharity_points_mnb_quote('EUR', '2026-10-28T12:00:00Z')['success'], '429 retained as failure');
check(!sharity_points_mnb_quote('EUR', '2026-10-28T12:00:00Z')['success'] && count($requests) === 2, 'No second request after429 in invocation');
check(!sharity_points_mnb_quote('USD', '2026-02-30T12:00:00Z')['success'], 'Invalid finalization date rejected');
check(!sharity_points_mnb_quote('USD', '2099-10-28T12:00:00Z')['success'], 'Future finalization cannot fetch a quote');
check(sharity_points_mnb_quote('HUF', '2026-10-28T12:00:00Z')['rate'] === '1' && count($requests) === 2, 'Already-HUF has no FX request');
echo "MNB exact affiliate policy fixtures passed\n";
