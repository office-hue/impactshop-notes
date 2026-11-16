<?php
/** Offline public-contract fixtures. HTTP is always mocked; no credentials or database. */
define('ABSPATH', __DIR__);
define('WP_CLI', true);
class WP_Error { public function __construct(public string $message) {} }
class WP_CLI {
    public static array $commands = [];
    public static array $output = [];
    public static function add_command($name, $callback) { self::$commands[$name] = $callback; }
    public static function line($value) { self::$output[] = $value; }
    public static function error($value) { throw new RuntimeException($value); }
}
function add_action($hook, $callback) { $GLOBALS['hooks'][$hook][] = $callback; }
function wp_parse_url($value, $part) { return parse_url($value, $part); }
function home_url($path) { return $GLOBALS['home'] . $path; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_get($url, $args) {
    $GLOBALS['calls'][] = [$url, $args];
    check(count($GLOBALS['calls']) <= 2, 'More than two requests');
    check(str_starts_with($url, 'https://api.admitad.com/'), 'Fixed HTTPS endpoint');
    check($args['timeout'] === 8 && $args['redirection'] === 0 && $args['sslverify'] === true, 'Bounded verified transport');
    check($args['limit_response_size'] === 1048577, 'Detect oversized response rather than accepting truncated body');
    check($args['headers']['Authorization'] === 'Bearer ' . $GLOBALS['config']['access_token'], 'Bearer header');
    check(!str_contains($url, $GLOBALS['config']['access_token']), 'Secret must not be in URL');
    if (!$GLOBALS['queue']) { throw new RuntimeException('Unexpected network request'); }
    $response = array_shift($GLOBALS['queue']);
    if ($response instanceof Throwable) { throw $response; }
    return $response;
}
function wp_remote_retrieve_response_code($response) { return $response['status']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function get_option(...$args) { throw new RuntimeException('Unexpected option read'); }
function update_option(...$args) { throw new RuntimeException('Unexpected write'); }
function set_transient(...$args) { throw new RuntimeException('Unexpected retention'); }
require __DIR__ . '/../wp-content/mu-plugins/impactshop-admitad.php';
function check($condition, $label) { if (!$condition) { throw new RuntimeException($label); } }
function response($body, $status = 200) { return ['body' => is_string($body) ? $body : json_encode($body), 'status' => $status]; }
function site($changes = []) {
    return array_replace(['id' => 2888572, 'site_url' => 'https://app.sharity.hu/impactshop/', 'status' => 'active', 'validation_passed' => true], $changes);
}
function page($rows, $count = null, $limit = 100, $offset = 0) {
    return response(['results' => $rows, '_meta' => ['count' => $count ?? count($rows), 'limit' => $limit, 'offset' => $offset]]);
}
function action($changes = []) {
    return array_replace(['advcampaign_id' => 6, 'action_id' => 241, 'order_id' => '999', 'payment' => '70.00', 'cart' => '90.00',
        'status' => 'approved', 'currency' => 'USD', 'paid' => 0, 'processed' => 1,
        'action_date' => '2011-02-22 19:54:34', 'status_updated' => '2011-09-16 23:13:35', 'closing_date' => '2012-04-02',
        'subid' => 'sat1_' . str_repeat('A', 43)], $changes);
}
$config = ['enabled' => true, 'access_token' => 'fixture-private-token', 'approved_campaign_ids' => ['6']];
$input = ['campaign' => '6', 'from' => '01.11.2026 00:00:00', 'until' => '16.11.2026 23:59:59'];
function reset_case($responses) { $GLOBALS['calls'] = []; $GLOBALS['queue'] = $responses; WP_CLI::$output = []; }
function fails($expected, $fn, $requests = null) {
    try { $fn(); } catch (RuntimeException $e) {
        check($e->getMessage() === $expected, 'Expected ' . $expected . ', got ' . $e->getMessage());
        check(!str_contains($e->getMessage(), $GLOBALS['config']['access_token']), 'Secret leaked in error');
        if ($requests !== null) { check(count($GLOBALS['calls']) === $requests, 'Unexpected request count on error'); }
        return;
    }
    throw new RuntimeException('Expected failure ' . $expected);
}
check(isset(WP_CLI::$commands['impactshop admitad shadow']) && count(WP_CLI::$commands) === 1, 'One manual CLI only');
check($hooks === ['wp_head' => ['impactshop_admitad_ownership_meta']], 'Only ownership hook');
foreach ([['https://app.sharity.hu','/impactshop/',true], ['https://app.sharity.hu','/impactshop?x=1',true],
    ['https://app.sharity.hu','/impactshop/other',false], ['https://sharity.hu','/impactshop/',false],
    ['https://app.sharity.hu.example.org','/impactshop/',false]] as [$home,$path,$expected]) {
    $_SERVER['REQUEST_URI'] = $path;
    ob_start(); impactshop_admitad_ownership_meta(); $meta = ob_get_clean();
    check(($meta === '<meta name="mitgo-verification" content="348a9888-0449-4a2e-8dbb-122bd395fe0a" />' . "\n") === $expected, 'Ownership scope');
}
reset_case([]);
fails('admitad_shadow_disabled', fn() => ImpactShop_Admitad::shadow('sites'), 0);
fails('admitad_token_missing_or_invalid', fn() => ImpactShop_Admitad::shadow('sites', [], ['enabled' => true]), 0);
fails('admitad_campaign_not_approved', fn() => ImpactShop_Admitad::shadow('actions', array_replace($input, ['campaign' => '7']), $config), 0);
fails('admitad_input_invalid', fn() => ImpactShop_Admitad::shadow('programs', ['website' => '99'], $config), 0);
fails('admitad_integer_invalid', fn() => ImpactShop_Admitad::shadow('programs', ['limit' => 101], $config), 0);
fails('admitad_date_invalid', fn() => ImpactShop_Admitad::shadow('actions', array_replace($input, ['from' => '31.11.2026 00:00:00']), $config), 0);
fails('admitad_date_range_invalid', fn() => ImpactShop_Admitad::shadow('actions', array_replace($input, ['from' => '17.11.2026 00:00:00']), $config), 0);
foreach ([401,429,500,302] as $status) {
    reset_case([response('{"error":"fixture-private-token"}', $status)]);
    fails('admitad_http_' . $status, fn() => ImpactShop_Admitad::shadow('sites', [], $config), 1);
}
reset_case([new WP_Error('timeout fixture-private-token')]);
fails('admitad_transport_failed', fn() => ImpactShop_Admitad::shadow('sites', [], $config), 1);
reset_case([new RuntimeException('timeout fixture-private-token')]);
fails('admitad_transport_failed', fn() => ImpactShop_Admitad::shadow('sites', [], $config), 1);
reset_case([response(str_repeat('x', 1048577))]);
fails('admitad_response_too_large', fn() => ImpactShop_Admitad::shadow('sites', [], $config), 1);
reset_case([response('{bad json fixture-private-token')]);
fails('admitad_json_invalid', fn() => ImpactShop_Admitad::shadow('sites', [], $config), 1);
reset_case([response([site(['site_url' => 'https://app.sharity.hu/impactshop/?token=x'])])]);
fails('admitad_site_binding_mismatch', fn() => ImpactShop_Admitad::shadow('programs', [], $config), 1);
reset_case([response([site(['id' => 99])])]);
fails('admitad_site_not_found', fn() => ImpactShop_Admitad::shadow('sites', [], $config), 1);
reset_case([response([site(),site()])]);
fails('admitad_site_binding_mismatch', fn() => ImpactShop_Admitad::shadow('sites', [], $config), 1);
reset_case([response([site(['status' => 'new'])])]);
$result = ImpactShop_Admitad::shadow('sites', [], $config);
check($result['site']['status'] === 'new' && $result['outbound_enabled'] === false, 'Inactive site evidence is no authority');
$programs = [];
foreach (['active','pending','declined','disabled','future_status'] as $status) {
    $programs[] = ['id' => 6, 'site_url' => 'https://shop.example.com/', 'status' => 'active', 'connection_status' => $status,
        'gotolink' => 'https://secret-tracking.example/fixture-private-token', 'allow_deeplink' => true];
}
reset_case([response([site()]),page($programs)]);
$result = ImpactShop_Admitad::shadow('programs', [], $config);
check(count($result['rows']) === 5 && !str_contains(json_encode($result), 'gotolink'), 'Program evidence excludes tracking/feed secrets');
foreach ($result['rows'] as $program) { check($program['outbound_enabled'] === false, 'No program status authorizes outbound'); }
check(str_contains($calls[1][0], '/advcampaigns/website/2888572/?'), 'Own-site program endpoint');
reset_case([response([site()]),page([action()],2,1)]);
$result = ImpactShop_Admitad::shadow('actions', $input + ['limit' => 1], $config);
check(!$result['complete'] && $result['next_offset'] === 1 && count($calls) === 2, 'Explicit continuation, no automatic page three');
parse_str(parse_url($calls[1][0], PHP_URL_QUERY), $query);
check($query['website'] === '2888572' && $query['campaign'] === '6' && $query['total'] === '0'
    && $query['status_updated_start'] === $input['from'], 'Mandatory own-site/campaign and update-window filters');
$row = $result['rows'][0];
check($row['status'] === 'approved' && $row['paid'] === false && $row['processed'] === true, 'Approval, payment, processing remain separate');
check(!isset($row['kind']) && !isset($row['root_id']) && !isset($row['authority']) && !$result['economic_writes_enabled'], 'No economic event contract');
check($row['action_id'] === '241' && $row['order_id'] === '999' && $row['commission'] === '70.00' && $row['order_amount'] === '90.00', 'Order/action/commission evidence separated');
// Actual docs contain numeric money. Preserve all wire digits and trailing zeroes, never round through float.
$raw = '{"results":[{"advcampaign_id":6,"action_id":242,"order_id":"998","payment":12345678901234567890.123400,"cart":0.000000000000000001,"currency":"USD","status":"approved_but_stalled","paid":0,"processed":1}],"_meta":{"count":1,"limit":100,"offset":0}}';
reset_case([response([site()]),response($raw)]);
$row = ImpactShop_Admitad::shadow('actions', $input, $config)['rows'][0];
check($row['commission'] === '12345678901234567890.123400' && $row['order_amount'] === '0.000000000000000001', 'Lossless numeric lexemes');
check($row['status'] === 'approved_but_stalled' && !$row['paid'] && $row['processed'], 'Stalled is never paid');
foreach (['pending','declined','unknown_future'] as $status) {
    reset_case([response([site()]),page([action(['status' => $status, 'paid' => null, 'processed' => null, 'subid' => 'private-email@example.org'])])]);
    $row = ImpactShop_Admitad::shadow('actions', $input, $config)['rows'][0];
    check($row['status'] === $status && $row['paid'] === null && $row['subid'] === null, 'Unknown/missing evidence never implies payment or exposes arbitrary attribution');
}
foreach ([['advcampaign_id' => 7], ['website_id' => 9]] as $changes) {
    reset_case([response([site()]),page([action($changes)])]);
    fails('admitad_action_binding_mismatch', fn() => ImpactShop_Admitad::shadow('actions', $input, $config), 2);
}
foreach (['1e2','1,00','NaN'] as $money) {
    reset_case([response([site()]),page([action(['payment' => $money])])]);
    fails('admitad_decimal_invalid', fn() => ImpactShop_Admitad::shadow('actions', $input, $config), 2);
}
reset_case([response([site()]),page([action(['order_id' => 'fixture-private-token'])])]);
fails('admitad_response_contains_secret', fn() => ImpactShop_Admitad::shadow('actions', $input, $config), 2);
reset_case([response([site()]),page([],1)]);
fails('admitad_page_incomplete', fn() => ImpactShop_Admitad::shadow('programs', [], $config), 2);
reset_case([response([site()]),page([action()],1,2)]);
fails('admitad_page_invalid', fn() => ImpactShop_Admitad::shadow('actions', $input, $config), 2);
// Exercise the operator entry through the actual registered callback, no option or file writes.
putenv('IMPACTSHOP_ADMITAD_SHADOW_ENABLED=1');
putenv('IMPACTSHOP_ADMITAD_ACCESS_TOKEN=fixture-private-token');
putenv('IMPACTSHOP_ADMITAD_APPROVED_CAMPAIGN_IDS=6');
reset_case([response([site()]),page([action()])]);
WP_CLI::$commands['impactshop admitad shadow'](['actions'], $input);
check(count(WP_CLI::$output) === 1 && json_decode(WP_CLI::$output[0], true)['mode'] === 'read_only_shadow', 'Usable CLI JSON output');
check(!str_contains(WP_CLI::$output[0], 'fixture-private-token'), 'CLI secret redaction');
putenv('IMPACTSHOP_ADMITAD_SHADOW_ENABLED');
reset_case([]);
fails('admitad_shadow_disabled', fn() => ImpactShop_Admitad::cli_shadow(['sites'], []), 0);
echo "Admitad offline shadow fixtures passed\n";
