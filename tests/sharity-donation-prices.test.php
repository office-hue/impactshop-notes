<?php
declare(strict_types=1);
// Real money/cache validation, isolated WordPress option transport; no network.
$options = $actions = $routes = [];
$writes = 0;
$checks = 0;
function check($ok, string $message): void {
    $GLOBALS['checks']++;
    if (!$ok) { throw new RuntimeException($message); }
}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function add_option($key, $value, $deprecated = '', $autoload = null): bool {
    $GLOBALS['writes']++;
    check($autoload === false, 'no autoload');
    if (isset($GLOBALS['options'][$key])) { return false; }
    $GLOBALS['options'][$key] = $value;
    return true;
}
function add_action($hook, $callback, $priority = 10): void { $GLOBALS['actions'][$hook][$priority][] = $callback; }
function register_rest_route($ns, $path, $args): void { $GLOBALS['routes'][$ns . $path] = $args; }
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $data, public int $status = 200) {}
    public function header($key, $value): void { $this->headers[$key] = $value; }
}
require __DIR__ . '/../wp-content/mu-plugins/sharity-points-money.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-support-finance-read.php';
require __DIR__ . '/../wp-content/mu-plugins/sharity-ngo-donations.php';

function cache_at(int $now): array {
    $day = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Budapest'))->format('Y-m-d');
    $quotes = [];
    foreach (['EUR' => '400', 'USD' => '370', 'GBP' => '500'] as $currency => $rate) {
        $quotes[$currency] = ['success' => true, 'currency' => $currency, 'rate' => $rate, 'unit' => '1', 'date' => $day];
    }
    return ['fetched_at' => gmdate('Y-m-d\TH:i:s\Z', $now), 'quotes' => $quotes];
}

check($writes === 0, 'load does not write or schedule');
check(sharity_donation_publish_price_books()['status'] === 'disabled', 'default off');
$now = strtotime('2026-11-02T12:00:00Z');
$books = sharity_donation_read_price_books($now);
check(array_keys($books) === ['HUF'], 'HUF fixed book without FX');
check(array_column($books['HUF']['packages'], 'amount_minor') === ['50000','100000','250000','500000','1000000'], 'HUF minor units exactly');
check(array_column($books['HUF']['packages'], 'votes') === [500,1100,3000,6500,15000], 'bonus once, independent of levels');
check(sharity_donation_whole_price(1000, ['rate' => '400', 'unit' => '1']) === 3, 'half goes up');
check(sharity_donation_whole_price(500, ['rate' => '99999999', 'unit' => '1']) === 1, 'minimum one');
check(sharity_donation_whole_price(1000, ['rate' => '40000', 'unit' => '100']) === 3, 'rate per 100 units');
foreach (['0', '-4', '0.0000001', '1e2', '400,5', ' 400', '9999999999'] as $bad) {
    check(sharity_donation_whole_price(1000, ['rate' => $bad, 'unit' => '1']) === null, 'invalid/bounded rate');
}
check(sharity_donation_whole_price(10000, ['rate' => '0.000001', 'unit' => '1000000']) === null, 'representational overflow rejected');
$cache = cache_at($now);
$saved = sharity_donation_build_price_books($cache, $now);
check($saved !== null && array_keys($saved['books']) === ['EUR','USD','GBP'], 'all foreign currencies');
check(array_column($saved['books']['EUR']['packages'], 'amount_minor') === ['100','300','600','1300','2500'], 'whole EUR prices');
check($saved['books']['EUR']['valid_until'] === '2026-11-08T23:00:00Z', 'Budapest Monday boundary');
$options[SHARITY_DONATION_PRICES_ENABLED] = '1';
$key = 'sharity_donation_prices_2026-11-02';
$options[$key] = $saved;
check(count(sharity_donation_read_price_books($now + 6 * 86400)) === 4, 'weekly freeze outlives daily FX freshness');
check(count(sharity_donation_read_price_books(strtotime('2026-11-08T23:00:00Z'))) === 1, 'Monday rejects old book');
$options[$key]['books']['EUR']['packages'][0]['amount_minor'] = '9900';
check(count(sharity_donation_read_price_books($now)) === 1, 'tampered saved price rejected');
$options[$key] = $saved;
$cache['quotes']['USD']['date'] = '2026-11-01';
check(sharity_donation_build_price_books($cache, $now) === null, 'mixed date FX rejected');
$cache = cache_at($now - 37 * 3600);
check(sharity_donation_build_price_books($cache, $now) === null, 'stale fetch rejected');
check(sharity_donation_build_price_books(cache_at($now + 1), $now) === null, 'future fetch rejected');
foreach (['2026-03-23T12:00:00Z' => 167, '2026-10-19T12:00:00Z' => 169] as $date => $hours) {
    $week = sharity_donation_price_week(strtotime($date));
    check($week['end'] - $week['start'] === $hours * 3600, 'DST calendar week, not fixed 168h');
}

// Real publisher clock, repeated refresh freezes first validated source.
$current = time();
$currentKey = 'sharity_donation_prices_' . sharity_donation_price_week($current)['id'];
unset($options[$currentKey]);
$options[SHARITY_SUPPORT_FX_CACHE_KEY] = cache_at($current);
check(sharity_donation_publish_price_books()['status'] === 'published', 'explicit opt-in publish');
$first = $options[$currentKey];
$options[SHARITY_SUPPORT_FX_CACHE_KEY]['quotes']['EUR']['rate'] = '999';
check(sharity_donation_publish_price_books()['status'] === 'already_published' && $options[$currentKey] === $first, 'repeated cron cannot reprice');
$before = $writes;
foreach ($actions['rest_api_init'][10] as $callback) { $callback(); }
$route = $routes['sharity/v1/donations/packages'];
$response = $route['callback']();
check($route['methods'] === 'GET' && $response->headers['Cache-Control'] === 'no-store', 'public no-store prices only');
check($writes === $before && !isset($response->data['enabled']), 'GET never writes or claims payment available');
check(isset($actions['sharity_support_fx_refresh'][20]), 'publication follows existing refresh at priority20');
echo "Donation prices: {$checks} checks passed\n";
