<?php
/** Standalone offline suite: php tests/impactshop-coupon-feed.test.php */
define('ABSPATH', __DIR__ . '/');
define('WP_CLI', true);
class CouponCliHalt extends RuntimeException {}
class WP_CLI {
    static $callback; static $lines = [];
    static function add_command($name, $callback) { self::$callback = $callback; }
    static function line($line) { self::$lines[] = $line; }
    static function halt($code) { throw new CouponCliHalt('exit', $code); }
}
$GLOBALS['coupon_time'] = 1794902400;
$GLOBALS['coupon_clock'] = 0.0;
$GLOBALS['coupon_options'] = []; $GLOBALS['coupon_autoload'] = []; $GLOBALS['coupon_http'] = []; $GLOBALS['coupon_actions'] = []; $GLOBALS['coupon_cron'] = [];
function impactshop_coupon_now() { return $GLOBALS['coupon_time']; }
function impactshop_coupon_clock() { return $GLOBALS['coupon_clock']; }
class WP_Error {
    private $code; private $data;
    function __construct($code, $message = '', $data = null) { $this->code = $code; $this->data = $data; }
    function get_error_code() { return $this->code; }
    function get_error_data() { return $this->data; }
}
class WP_REST_Response {
    public $data; public $status; public $headers = [];
    function __construct($data, $status) { $this->data = $data; $this->status = $status; }
    function header($name, $value) { $this->headers[$name] = $value; }
}
class CouponRequest {
    private $params;
    function __construct($params) { $this->params = $params; }
    function get_query_params() { return $this->params; }
    function get_header($name) { return $GLOBALS['coupon_authorized'] ? 'Bearer test-service-auth' : ''; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['coupon_options']) ? $GLOBALS['coupon_options'][$name] : $default; }
function add_option($name, $value, $deprecated = '', $autoload = null) {
    if (array_key_exists($name, $GLOBALS['coupon_options'])) { return false; }
    $GLOBALS['coupon_options'][$name] = $value; $GLOBALS['coupon_autoload'][$name] = $autoload; return true;
}
function update_option($name, $value, $autoload = null) { $GLOBALS['coupon_options'][$name] = $value; $GLOBALS['coupon_autoload'][$name] = $autoload; return true; }
function get_transient($name) { return $GLOBALS['coupon_token'] ?? false; }
function maybe_serialize($v) { return is_array($v) || is_object($v) ? serialize($v) : $v; }
function wp_cache_delete($key, $group) {}
function wp_strip_all_tags($v) { return strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $v)); }
function add_action($name, $callback) { $GLOBALS['coupon_actions'][$name] = $callback; }
function register_rest_route($namespace, $path, $args) { $GLOBALS['coupon_route'] = [$namespace, $path, $args]; }
function wp_next_scheduled($hook) { return $GLOBALS['coupon_cron'][$hook] ?? false; }
function wp_schedule_event($time, $recurrence, $hook) { $GLOBALS['coupon_cron'][$hook] = $time; return true; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['coupon_cron'][$hook]); }
function wp_remote_post($url, $args) {
    $GLOBALS['coupon_http'][] = [$url, $args];
    return ($GLOBALS['coupon_responder'])($url, $args, count($GLOBALS['coupon_http']));
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
class CouponMockWpdb {
    public $options = 'wp_options';
    function prepare($query, ...$args) { return [$query, $args]; }
    function query($prepared) {
        [$query, $args] = $prepared;
        $delete = strpos($query, 'DELETE') === 0;
        if ($delete) { [$name, $old] = $args; } else { [$new, $name, $old] = $args; }
        if (!array_key_exists($name, $GLOBALS['coupon_options']) || maybe_serialize($GLOBALS['coupon_options'][$name]) !== $old) { return 0; }
        if ($delete) { unset($GLOBALS['coupon_options'][$name]); }
        else { $GLOBALS['coupon_options'][$name] = unserialize($new); $GLOBALS['coupon_autoload'][$name] = false; }
        return 1;
    }
}
$GLOBALS['wpdb'] = new CouponMockWpdb();
require __DIR__ . '/../wp-content/mu-plugins/impactshop-coupon-feed.php';
$assertions = 0;
function check($v, $message) { global $assertions; $assertions++; if (!$v) { throw new RuntimeException($message); } }
function eq($actual, $expected, $message) { check($actual === $expected, $message . ' [actual=' . json_encode($actual) . ']'); }
function binding($partner = 'fixture-shop') { return ['partnerKey' => $partner, 'providerKey' => 'dognet', 'programRef' => 'dognet:' . $partner]; }
function ack() { return ['acknowledged' => true, 'evidenceRef' => 'offline-fixture-only:not-live-qualification']; }
function policy() {
    $now = impactshop_coupon_now();
    return ['schemaVersion' => 1, 'revision' => 'fixture-1', 'bindings' => [array_merge(binding(), [
        'providerProgramId' => '223', 'siteOrChannelId' => '26081', 'publicationAllowed' => true,
        'attributionCompatibility' => 'compatible', 'checkedAt' => impactshop_coupon_iso($now - 60), 'expiresAt' => impactshop_coupon_iso($now + 86400), 'qualification' => ack(),
    ])], 'providers' => ['dognet' => ['qualification' => ack(), 'dateTimezone' => 'Europe/Budapest', 'nullStartMeansUnbounded' => true,
        'nullEndMeansOngoing' => true, 'kindMap' => ['1:1' => ['kind' => 'coupon', 'redeemMode' => 'code', 'applicability' => 'conditions_apply']]]]];
}
function row($id = 16179) {
    return ['id' => $id, 'original_id' => null, 'campaign_id' => 223, 'form' => 1, 'type' => 1, 'code' => 'FIXTURE10',
        'url' => 'https://private.invalid/tracking?secret=never-return', 'valid_from' => null, 'valid_to' => null,
        'discount_value' => '10', 'description' => '<b>10% off</b>', 'detailed_description' => 'Selected products; minimum basket applies.',
        'created_at' => '2026-11-01 12:00:00', 'updated_at' => '2026-11-01 12:00:00', 'countries' => ['HU']];
}
function response($rows, $page = 1, $last = 1, $total = null) {
    return ['response' => ['code' => 200], 'body' => json_encode(['data' => $rows,
        'meta' => ['current_page' => $page, 'last_page' => $last, 'per_page' => 100, 'total' => $total === null ? count($rows) : $total],
        'links' => ['next' => 'https://attacker.invalid/never-follow']])];
}
function reset_fixture($rows = null) {
    $GLOBALS['coupon_time'] = 1794902400; $GLOBALS['coupon_clock'] = 0.0;
    $GLOBALS['coupon_options'] = []; $GLOBALS['coupon_autoload'] = []; $GLOBALS['coupon_http'] = []; $GLOBALS['coupon_cron'] = [];
    $GLOBALS['coupon_token'] = 'fixture-secret-token'; $GLOBALS['coupon_authorized'] = true;
    unset($_SERVER['QUERY_STRING']);
    update_option('impactshop_coupon_policy_v1', policy(), false);
    update_option('impactshop_coupon_config_v1', ['schemaVersion' => 1, 'revision' => 'fixture-enabled', 'enabledProviders' => ['dognet']], false);
    $GLOBALS['coupon_responder'] = function () use ($rows) { return response($rows === null ? [row()] : $rows); };
}
function project() { return impactshop_coupon_project(binding(), impactshop_coupon_config(), impactshop_coupon_validate_policy(get_option('impactshop_coupon_policy_v1'))); }
function request($bindings = null, $params = null) {
    return impactshop_coupon_rest(new CouponRequest($params ?? ['bindings' => json_encode($bindings ?? [binding()])]));
}
// Loading only registers hooks: no option, HTTP, credential or scheduling mutation.
eq($GLOBALS['coupon_options'], [], 'load has no option side effects');
eq($GLOBALS['coupon_http'], [], 'load has no provider side effects');
eq($GLOBALS['coupon_cron'], [], 'load never schedules cron');
eq(impactshop_coupon_permission(new CouponRequest([]))->get_error_data()['status'], 503, 'missing auth checker fails 503');
if (!function_exists('impactshop_sharity_affiliate_service_authorized')) {
    function impactshop_sharity_affiliate_service_authorized($request) { return $GLOBALS['coupon_authorized']; }
}
reset_fixture();
$GLOBALS['coupon_authorized'] = false;
eq(request()->get_error_data()['status'], 401, 'service auth required');
eq(count($GLOBALS['coupon_http']), 0, 'auth failure no provider');
$GLOBALS['coupon_authorized'] = true;
foreach ([[], [binding(), binding()], [array_merge(binding(), ['siteOrChannelId' => 'other'])], [array_merge(binding(), ['partnerKey' => ''])], [array_merge(binding(), ['providerKey' => 'other'])], array_fill(0, 11, binding())] as $bad) {
    eq(request($bad)->get_error_data()['status'], 400, 'exact invalid binding rejected');
}
eq(request(null, ['bindings' => 'not-json'])->get_error_data()['status'], 400, 'invalid JSON rejected');
eq(request(null, ['bindings' => '[{"partnerKey":"fixture-shop","partnerKey":"fixture-shop","providerKey":"dognet","programRef":"dognet:fixture-shop"}]'])->get_error_data()['status'], 400, 'duplicate JSON key rejected');
$_SERVER['QUERY_STRING'] = 'bindings=x&bindings=y';
eq(request()->get_error_data()['status'], 400, 'duplicate encoded query rejected');
$_SERVER['QUERY_STRING'] = 'bindings=' . str_repeat('x', 8190);
eq(request()->get_error_data()['status'], 400, 'encoded query cap');
unset($_SERVER['QUERY_STRING']);
eq(request(null, ['bindings' => json_encode([binding()]), 'url' => 'https://input.invalid'])->get_error_data()['status'], 400, 'no client URL accepted');
$route = '/sharity/v1/shopping/coupon-offers';
$query = json_encode([binding()]);
foreach (['bindings=' . rawurlencode($query), 'rest_route=' . rawurlencode($route) . '&bindings=' . rawurlencode($query)] as $raw) {
    $_SERVER['QUERY_STRING'] = $raw;
    eq(request(null, ['rest_route' => $route, 'bindings' => $query])->status, 200, 'WordPress router parameter accepted with pretty or query transport');
}
foreach ([
    'rest_route=' . rawurlencode($route) . '&rest_route=' . rawurlencode($route) . '&bindings=' . rawurlencode($query),
    'rest_route=%2Fother&bindings=' . rawurlencode($query),
    'bindings=' . rawurlencode($query) . '&bindings=' . rawurlencode($query),
    'bindings=' . rawurlencode($query) . '&url=other',
] as $raw) {
    $_SERVER['QUERY_STRING'] = $raw;
    eq(request(null, ['rest_route' => $route, 'bindings' => $query])->get_error_data()['status'], 400, 'router compatibility preserves exact query and duplicate rejection');
}
unset($_SERVER['QUERY_STRING']);
eq(request(null, ['rest_route' => '/other', 'bindings' => $query])->get_error_data()['status'], 400, 'foreign parsed router path rejected');
eq(count($GLOBALS['coupon_http']), 0, 'router compatibility remains cache only');
reset_fixture();
update_option('impactshop_coupon_config_v1', ['schemaVersion' => 1, 'revision' => 'off', 'enabledProviders' => []], false);
eq(request()->data['items'][0]['benefits']['status'], 'not_checked', 'OFF is not_checked');
eq(count($GLOBALS['coupon_http']), 0, 'GET OFF zero upstream');
unset($GLOBALS['coupon_options']['impactshop_coupon_config_v1']);
eq(request()->data['items'][0]['benefits']['status'], 'not_checked', 'missing config defaults OFF');
update_option('impactshop_coupon_config_v1', 'invalid', false);
eq(request()->get_error_data()['status'], 503, 'malformed global config unavailable');
reset_fixture();
$r = impactshop_coupon_refresh('dognet');
eq($r['status'], 'complete', 'complete refresh');
eq(count($GLOBALS['coupon_http']), 1, 'one read, no retry');
[$url, $args] = $GLOBALS['coupon_http'][0];
eq($url, 'https://api.app.dognet.com/api/v1/coupons/filter?page=1', 'fixed endpoint');
eq($args['redirection'], 0, 'redirect prohibited');
eq($args['timeout'], 8, 'request timeout bounded');
eq($args['limit_response_size'], 1048577, 'response cap + sentinel byte');
eq(json_decode($args['body'], true), ['per-page' => 100, 'ad_channel_id' => 26081, 'filter' => [['validity' => ['eq' => 'present']]]], 'fixed read filter');
$p = project();
eq($p['status'], 'available', 'qualified ongoing row available');
eq($p['coverage'], 'complete', 'complete projection');
eq($p['offers'][0]['benefitText'], '10% off', 'HTML removed');
eq($p['offers'][0]['expiryKind'], 'ongoing', 'only explicit ongoing semantics accepted');
eq($p['offers'][0]['conditionsText'], row()['detailed_description'], 'conditions preserved');
eq($p['freshForMs'], 60000, 'maximum display freshness');
check((bool) preg_match('/^coupon_[a-f0-9]{32}$/', $p['offers'][0]['id']), 'opaque public ID');
eq(array_keys($p['offers'][0]), ['id','kind','code','benefitText','conditionsText','startsAt','endsAt','expiryKind','networkLabel','applicability'], 'offer public allowlist exact');
check(strpos(json_encode($p), 'never-return') === false && strpos(json_encode($p), '26081') === false, 'no URL/site leak');
$rest = request();
eq($rest->status, 200, 'feed success');
eq($rest->headers['Cache-Control'], 'no-store', 'feed no-store');
eq(count($rest->data['items']), 1, 'one result per binding');
eq(count($GLOBALS['coupon_http']), 1, 'all GET/project reads zero provider');
foreach ($GLOBALS['coupon_autoload'] as $name => $autoload) { eq($autoload, false, 'own option non-autoload ' . $name); }
$firstChecked = $p['checkedAt']; $GLOBALS['coupon_time'] += 30;
eq(project()['checkedAt'], $firstChecked, 'cache read retains original complete check time');
$GLOBALS['coupon_time'] += 7200;
eq(project()['reason'], 'stale', 'two hour snapshot expiration');
reset_fixture();
$multiline = row();
$multiline['description'] = "10%\t off\r\n selected   products";
$multiline['detailed_description'] = "Selected\tproducts;\r\nminimum  basket applies.\nCannot combine\twith other codes.";
$GLOBALS['coupon_responder'] = function () use ($multiline) { return response([$multiline]); };
impactshop_coupon_refresh('dognet');
$p = project();
eq($p['status'], 'available', 'multiline provider text remains a usable offer');
eq($p['offers'][0]['benefitText'], '10% off selected products', 'TAB CR LF and repeated benefit whitespace normalized');
eq($p['offers'][0]['conditionsText'], 'Selected products; minimum basket applies. Cannot combine with other codes.', 'all multiline conditions preserved without truncation');
check(!preg_match('/[\\x00-\\x1f\\x7f]/', $p['offers'][0]['conditionsText']), 'public conditions contain no reader-rejected controls');
reset_fixture([]);
eq(impactshop_coupon_refresh('dognet')['status'], 'complete', 'full empty source complete');
eq(project()['status'], 'none', 'complete zero may say none');
reset_fixture();
$b = row(); $b['valid_to'] = null;
$policy = policy(); $policy['providers']['dognet']['nullEndMeansOngoing'] = false; update_option('impactshop_coupon_policy_v1', $policy, false);
impactshop_coupon_refresh('dognet');
eq(project()['status'], 'unavailable', 'unknown null end never none or available');
reset_fixture();
$b = row(); $b['type'] = 987; $GLOBALS['coupon_responder'] = function () use ($b) { return response([$b]); };
impactshop_coupon_refresh('dognet');
eq(project()['coverage'], 'partial', 'unknown type cannot imply complete-none');
reset_fixture();
$policy = policy(); unset($policy['bindings'][0]['qualification']); update_option('impactshop_coupon_policy_v1', $policy, false);
check(is_wp_error(impactshop_coupon_set_enabled('dognet', true)), 'policy alone cannot qualify program rights');
eq(count($GLOBALS['coupon_http']), 0, 'missing qualification no upstream');
reset_fixture();
$policy = policy(); $policy['bindings'][0]['attributionCompatibility'] = 'unknown'; update_option('impactshop_coupon_policy_v1', $policy, false);
eq(project()['reason'], 'compatibility_unverified', 'unknown attribution withheld');
reset_fixture();
$policy = policy(); $policy['bindings'][0]['publicationAllowed'] = false; update_option('impactshop_coupon_policy_v1', $policy, false);
eq(project()['reason'], 'binding_unavailable', 'publication denied withheld');
reset_fixture();
impactshop_coupon_refresh('dognet');
$policy = policy(); $policy['revision'] = 'revoked-policy'; update_option('impactshop_coupon_policy_v1', $policy, false);
eq(project()['status'], 'unavailable', 'policy revision invalidates old snapshot');
reset_fixture();
$b = row(); $b['detailed_description'] = str_repeat('x', 1001); $GLOBALS['coupon_responder'] = function () use ($b) { return response([$b]); };
impactshop_coupon_refresh('dognet');
eq(project()['status'], 'unavailable', 'essential condition never truncated');
reset_fixture();
$b = row(); $b['detailed_description'] = 'Private https://tracking.invalid/?token=secret'; $GLOBALS['coupon_responder'] = function () use ($b) { return response([$b]); };
impactshop_coupon_refresh('dognet');
eq(project()['status'], 'unavailable', 'tracking URL in conditions withheld');
// Dates must use declared timezone, reject normalization and ambiguous DST.
eq(impactshop_coupon_dognet_date('2026-11-17 12:00:00', 'Europe/Budapest'), strtotime('2026-11-17 11:00:00 UTC'), 'declared timezone');
eq(impactshop_coupon_dognet_date('2026-11-17T12:00:00Z', 'Europe/Budapest'), strtotime('2026-11-17 12:00:00 UTC'), 'explicit UTC');
eq(impactshop_coupon_dognet_date('2026-02-30 12:00:00', 'UTC'), false, 'invalid calendar date rejected');
eq(impactshop_coupon_dognet_date('2026-10-25 02:30:00', 'Europe/Budapest'), false, 'ambiguous DST rejected');
eq(impactshop_coupon_dognet_date('2026-03-29 02:30:00', 'Europe/Budapest'), false, 'nonexistent DST rejected');
reset_fixture();
$rows = [];
foreach ([90, 20, 40, 10] as $i => $seconds) { $b = row(100 + $i); $b['valid_to'] = impactshop_coupon_iso(impactshop_coupon_now() + $seconds); $rows[] = $b; }
$GLOBALS['coupon_responder'] = function () use ($rows) { return response($rows); };
impactshop_coupon_refresh('dognet'); $p = project();
eq(count($p['offers']), 3, 'top three only'); eq($p['moreAvailable'], true, 'moreAvailable tracks full source');
eq($p['freshForMs'], 10000, 'soonest commercial boundary bounds projection');
eq($p['offers'][0]['endsAt'], impactshop_coupon_iso(impactshop_coupon_now() + 10), 'deterministic expiry order');
$GLOBALS['coupon_time'] += 10; $p = project();
eq(count($p['offers']), 3, 'reread full snapshot after earliest expiry'); eq($p['moreAvailable'], false, 'expired record not counted');
reset_fixture();
$b = row(); $b['valid_from'] = impactshop_coupon_iso(impactshop_coupon_now() + 5); $GLOBALS['coupon_responder'] = function () use ($b) { return response([$b]); };
impactshop_coupon_refresh('dognet');
eq(project()['status'], 'none', 'known future is not current'); eq(project()['freshForMs'], 5000, 'future transition bounds zero cache');
// Last failed or partial attempt makes last complete snapshot diagnostic-only.
foreach (['transport', 'oversize', 'partial', 'deadline', 'badmeta'] as $failure) {
    reset_fixture(); impactshop_coupon_refresh('dognet');
    $GLOBALS['coupon_http'] = [];
    $GLOBALS['coupon_responder'] = function () use ($failure) {
        if ($failure === 'transport') { return new WP_Error('secret-provider-diagnostic', 'TOKEN LEAK'); }
        if ($failure === 'oversize') { return ['response' => ['code' => 200], 'body' => str_repeat('a', 1048577)]; }
        if ($failure === 'partial') { return response([row()], 1, 2, 101); }
        if ($failure === 'deadline') { $GLOBALS['coupon_clock'] = 61; return response([row()]); }
        return response([row()], 1, 21, 2100);
    };
    check(is_wp_error(impactshop_coupon_refresh('dognet')), $failure . ' returns error');
    eq(project()['status'], 'unavailable', $failure . ' never uses old fallback');
    eq(count($GLOBALS['coupon_http']), 1, $failure . ' no retry');
    check(strpos(json_encode(impactshop_coupon_status()), 'TOKEN') === false, 'diagnostics redact external error');
}
reset_fixture();
$GLOBALS['coupon_token'] = false;
check(is_wp_error(impactshop_coupon_refresh('dognet')), 'no cached token fails');
eq(count($GLOBALS['coupon_http']), 0, 'no auth refresh');
reset_fixture();
$rows = []; for ($i = 1; $i <= 100; $i++) { $rows[] = row($i); }
$GLOBALS['coupon_responder'] = function ($url, $args, $call) use ($rows) { return $call === 1 ? response($rows, 1, 2, 101) : response([row(101)], 2, 2, 101); };
eq(impactshop_coupon_refresh('dognet')['status'], 'complete', 'complete bounded pagination');
eq(count($GLOBALS['coupon_http']), 2, 'correct page count');
eq($GLOBALS['coupon_http'][1][0], 'https://api.app.dognet.com/api/v1/coupons/filter?page=2', 'fixed next page, never next URL');
reset_fixture();
$GLOBALS['coupon_responder'] = function () { impactshop_coupon_set_enabled('dognet', false); return response([row()]); };
eq(impactshop_coupon_refresh('dognet')->get_error_code(), 'coupon_publication_fenced', 'disable during refresh fences late publication');
eq(project()['status'], 'not_checked', 'disabled projection OFF');
check(get_option('impactshop_coupon_snapshot_v1_dognet') === false, 'late result not published');
reset_fixture();
$GLOBALS['coupon_responder'] = function () { $p = get_option('impactshop_coupon_policy_v1'); $p['bindings'][0]['publicationAllowed'] = false; update_option('impactshop_coupon_policy_v1', $p, false); return response([row()]); };
eq(impactshop_coupon_refresh('dognet')->get_error_code(), 'coupon_publication_fenced', 'policy revoke during refresh fences publication');
reset_fixture();
$other = ['owner' => 'another-owner', 'expiresAt' => impactshop_coupon_now() + 180];
$GLOBALS['coupon_responder'] = function () use ($other) { update_option('impactshop_coupon_refresh_lock_v1_dognet', $other, false); return response([row()]); };
check(is_wp_error(impactshop_coupon_refresh('dognet')), 'lost lock cannot publish');
eq(get_option('impactshop_coupon_refresh_lock_v1_dognet'), $other, 'finally never deletes other owner lock');
reset_fixture();
update_option('impactshop_coupon_refresh_lock_v1_dognet', ['owner' => 'busy', 'expiresAt' => impactshop_coupon_now() + 1], false);
eq(impactshop_coupon_refresh('dognet')->get_error_code(), 'coupon_locked', 'active owner excludes refresh');
eq(count($GLOBALS['coupon_http']), 0, 'locked refresh zero provider');
$GLOBALS['coupon_time'] += 2;
eq(impactshop_coupon_refresh('dognet')['status'], 'complete', 'expired lock safely replaced');
reset_fixture();
eq(impactshop_coupon_set_enabled('dognet', true)->get_error_code(), 'coupon_fresh_pilot_required', 'enable requires actual fresh pilot');
impactshop_coupon_refresh('dognet');
$enabled = impactshop_coupon_set_enabled('dognet', true);
check(!is_wp_error($enabled), 'explicit qualified enable');
eq($enabled['ready'], false, 'enable is not ready until enabled refresh');
eq($enabled['refreshRequired'], true, 'config revision requires fresh enabled read');
eq(project()['status'], 'unavailable', 'enable never silently rebinds pilot snapshot');
impactshop_coupon_refresh('dognet');
eq(project()['status'], 'available', 'enabled refresh makes snapshot current');
check((bool) wp_next_scheduled('impactshop_coupon_refresh_v1'), 'only explicit enable schedules');
impactshop_coupon_set_enabled('dognet', false);
eq(wp_next_scheduled('impactshop_coupon_refresh_v1'), false, 'last disable clears schedule');
foreach (['cj', 'admitad', 'tradetracker'] as $provider) {
    check(is_wp_error(impactshop_coupon_refresh($provider)), $provider . ' no fake adapter');
    check(is_wp_error(impactshop_coupon_set_enabled($provider, true)), $provider . ' cannot enable unimplemented adapter');
    $b = binding(); $b['providerKey'] = $provider;
    eq(impactshop_coupon_project($b, impactshop_coupon_config(), policy())['status'], 'not_checked', $provider . ' remains not_checked');
}
// Failed enable/read state, precise binding and cache schema are independent gates.
reset_fixture(); impactshop_coupon_refresh('dognet');
$key = impactshop_coupon_binding_key(binding());
$snapshot = get_option('impactshop_coupon_snapshot_v1_dognet');
$snapshot['records'][$key][0]['endsAt'] = 'not-a-date';
update_option('impactshop_coupon_snapshot_v1_dognet', $snapshot, false);
eq(project()['status'], 'unavailable', 'malformed cached offer fails closed');
reset_fixture(); impactshop_coupon_refresh('dognet');
$foreign = binding('another-shop');
eq(impactshop_coupon_project($foreign, impactshop_coupon_config(), policy())['reason'], 'binding_unavailable', 'no cross-shop binding guess');
$policy = policy(); $policy['bindings'][0]['providerProgramId'] = '999'; update_option('impactshop_coupon_policy_v1', $policy, false);
impactshop_coupon_refresh('dognet');
eq(project()['status'], 'none', 'different program coupon never joins by merchant name');
reset_fixture(); impactshop_coupon_refresh('dognet');
$GLOBALS['coupon_time'] += 7200;
eq(impactshop_coupon_set_enabled('dognet', true)->get_error_code(), 'coupon_fresh_pilot_required', 'stale pilot cannot enable');
reset_fixture();
$policy = policy(); $policy['bindings'][0]['expiresAt'] = impactshop_coupon_iso(impactshop_coupon_now() + 7); update_option('impactshop_coupon_policy_v1', $policy, false);
impactshop_coupon_refresh('dognet');
eq(project()['freshForMs'], 7000, 'policy expiry bounds display');
$GLOBALS['coupon_time'] += 7;
eq(project()['status'], 'unavailable', 'expired program policy unavailable');
reset_fixture();
$GLOBALS['coupon_responder'] = function () { return ['response' => ['code' => 401], 'body' => 'private error']; };
check(is_wp_error(impactshop_coupon_refresh('dognet')), '401 fails');
eq(count($GLOBALS['coupon_http']), 1, '401 never retries or refreshes auth');
reset_fixture();
$rows = []; for ($i = 1; $i <= 100; $i++) { $rows[] = row($i); }
$GLOBALS['coupon_responder'] = function ($url, $args, $call) use ($rows) {
    if ($call === 1) { $GLOBALS['coupon_clock'] = 59.5; return response($rows, 1, 2, 101); }
    return response([row(101)], 2, 2, 101);
};
eq(impactshop_coupon_refresh('dognet')['status'], 'complete', 'remaining budget accepted');
eq($GLOBALS['coupon_http'][1][1]['timeout'], 0.5, 'later page timeout shrinks to remaining total budget');
reset_fixture();
$policy = policy();
for ($i = 1; $i <= 20; $i++) { $b = $policy['bindings'][0]; $b['partnerKey'] = 'fixture-' . $i; $b['programRef'] = 'dognet:fixture-' . $i; $b['siteOrChannelId'] = (string) (26081 + $i); $policy['bindings'][] = $b; }
update_option('impactshop_coupon_policy_v1', $policy, false);
$GLOBALS['coupon_responder'] = function () { return response([]); };
eq(impactshop_coupon_refresh('dognet')->get_error_code(), 'coupon_limits', 'whole refresh request cap across channels');
eq(count($GLOBALS['coupon_http']), 20, 'at most twenty provider calls');
reset_fixture();
$cli = WP_CLI::$callback; WP_CLI::$lines = [];
$cli(['status'], []);
eq(json_decode(WP_CLI::$lines[0], true)['schemaVersion'], 1, 'CLI status emits structured state');
try { $cli(['refresh'], ['provider' => 'cj']); check(false, 'failed CLI refresh must halt'); }
catch (CouponCliHalt $e) { eq($e->getCode(), 1, 'CLI refresh nonzero for incomplete provider'); }
$last = json_decode(end(WP_CLI::$lines), true);
eq($last['error'], 'coupon_adapter_unavailable', 'CLI error diagnostic is redacted code');
$GLOBALS['coupon_actions']['rest_api_init']();
eq($GLOBALS['coupon_route'][0], 'sharity/v1', 'route namespace');
eq($GLOBALS['coupon_route'][1], '/shopping/coupon-offers', 'route path');
eq($GLOBALS['coupon_route'][2]['methods'], 'GET', 'route GET only');


// CJ seam is absent until this point: the original missing-adapter assertions above remain real.
if (!class_exists('ImpactShop_CJ')) {
    class ImpactShop_CJ {
        static function read_coupon_page($type, $page, $timeout) {
            $GLOBALS['cj_reads'][] = [$type, $page, $timeout];
            return ($GLOBALS['cj_responder'])($type, $page, $timeout, count($GLOBALS['cj_reads']));
        }
    }
}
function cj_binding() { return ['partnerKey'=>'fixture-cj','providerKey'=>'cj','programRef'=>'cj:fixture-cj']; }
function cj_row($id = '800001', $type = 'Coupon') {
    return ['link_id'=>$id,'advertiser_id'=>'700001','relationship_status'=>'joined','link_type'=>'Text Link','promotion_type'=>$type,
        'coupon_code'=>'FIXTURE10','link_name'=>'10 percent off selected items','description'=>'Minimum basket applies.',
        'promotion_start'=>'2026-01-01 00:00:00.0','promotion_end'=>'2026-12-31 23:59:59.0',
        'click_url'=>'https://tracking.invalid/private','destination'=>'https://merchant.invalid/'];
}
function cj_page($rows = [], $total = null, $page = 1) {
    return ['page'=>$page,'total'=>$total??count($rows),'returned'=>count($rows),'rows'=>$rows,'websiteId'=>'900000','responseBytes'=>1000];
}
function cj_reset($rows = null) {
    reset_fixture(); $GLOBALS['cj_reads']=[];
    $policy=policy();
    $policy['bindings'][]=array_merge(cj_binding(),['providerProgramId'=>'700001','siteOrChannelId'=>'900000','publicationAllowed'=>true,
        'attributionCompatibility'=>'compatible','checkedAt'=>impactshop_coupon_iso(impactshop_coupon_now()-60),
        'expiresAt'=>impactshop_coupon_iso(impactshop_coupon_now()+86400),'qualification'=>ack()]);
    $policy['providers']['cj']=['qualification'=>ack(),'dateTimezone'=>'UTC','emptyStartMeansUnbounded'=>false,
        'emptyEndMeansOngoing'=>false,'ongoingEndMeansOngoing'=>false];
    update_option('impactshop_coupon_policy_v1',$policy,false);
    $GLOBALS['cj_responder']=function($type) use($rows){ return cj_page($type==='coupon'?($rows??[cj_row()]):[]); };
}
function cj_project() { return impactshop_coupon_project(cj_binding(),impactshop_coupon_config(),get_option('impactshop_coupon_policy_v1')); }
function cj_activate() {
    eq(impactshop_coupon_refresh('cj')['status'],'complete','CJ offline pilot complete');
    eq(impactshop_coupon_set_enabled('cj',true)['refreshRequired'],true,'CJ explicit enable invalidates snapshot');
    eq(cj_project()['status'],'unavailable','no old snapshot after enable');
    $GLOBALS['coupon_time']+=120;
    eq(impactshop_coupon_refresh('cj')['status'],'complete','CJ enabled refresh complete');
}
cj_reset();
eq(cj_project()['status'],'not_checked','CJ default OFF');
eq($GLOBALS['cj_reads'],[],'CJ OFF zero provider');
check(impactshop_coupon_status()['providers']['cj']['implemented'],'CJ seam availability is explicit');
cj_activate();
$benefits=cj_project();
eq($benefits['status'],'available','qualified CJ available');
eq($benefits['offers'][0]['networkLabel'],'CJ','CJ exact label');
eq($benefits['offers'][0]['startsAt'],'2026-01-01T00:00:00Z','CJ .0 date is UTC');
check(strpos(json_encode($benefits),'tracking.invalid')===false,'private CJ URL not projected');
$reads=count($GLOBALS['cj_reads']);
eq(request([cj_binding()])->data['items'][0]['benefits']['status'],'available','CJ actual private REST projection');
eq(count($GLOBALS['cj_reads']),$reads,'CJ REST zero upstream');
$before=get_option('impactshop_coupon_refresh_state_v1_cj');
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_rate_limit','CJ repeated manual refresh cooldown');
eq(get_option('impactshop_coupon_refresh_state_v1_cj'),$before,'cooldown preserves complete state');
eq(count($GLOBALS['cj_reads']),$reads,'cooldown zero provider');
$GLOBALS['coupon_authorized']=false;
eq(request([cj_binding()])->get_error_data()['status'],401,'CJ requires service auth');
eq(count($GLOBALS['cj_reads']),$reads,'CJ anon zero provider');
cj_reset([]); cj_activate();
eq(cj_project()['status'],'none','all three complete empty resources give genuine none');
eq(count($GLOBALS['cj_reads']),6,'exactly three resources per complete scan');
foreach (['emptyStartMeansUnbounded','emptyEndMeansOngoing','ongoingEndMeansOngoing'] as $flag) {
    cj_reset(); $p=get_option('impactshop_coupon_policy_v1');unset($p['providers']['cj'][$flag]);update_option('impactshop_coupon_policy_v1',$p,false);
    eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_qualification_missing','CJ requires explicit date meaning');
    eq($GLOBALS['cj_reads'],[],'unqualified date zero provider');
}
foreach ([['promotion_start',''],['promotion_end',''],['promotion_end','ongoing'],['promotion_end','2026-02-30 00:00:00.0'],
    ['promotion_start','2026-01-01 00:00:00.5'],['description',str_repeat('x',1001)],['coupon_code','']] as [$field,$value]) {
    $r=cj_row();$r[$field]=$value;cj_reset([$r]);
    eq(impactshop_coupon_refresh('cj')['withheldBindings'],1,'unknown/malformed CJ offer withheld');
    eq(impactshop_coupon_set_enabled('cj',true)->get_error_code(),'coupon_fresh_pilot_required','withheld CJ cannot activate');
}
cj_reset();$p=get_option('impactshop_coupon_policy_v1');$p['providers']['cj']['emptyStartMeansUnbounded']=true;
$p['providers']['cj']['ongoingEndMeansOngoing']=true;$map=$p['providers']['cj'];$b=$p['bindings'][1];$r=cj_row();$r['promotion_start']='';$r['promotion_end']='ongoing';
$o=impactshop_coupon_normalize_cj($r,$b,$map);eq($o['startsAt'],null,'qualified unbounded start');eq($o['expiryKind'],'ongoing','qualified explicit ongoing');
$r=cj_row();$r['advertiser_id']='other';eq(impactshop_coupon_normalize_cj($r,$b,$map),false,'CJ exact advertiser');
cj_reset();$GLOBALS['cj_responder']=function(){return array_merge(cj_page([cj_row()]),['websiteId'=>'900001']);};
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_scope_mismatch','CJ exact own PID');
cj_reset();$GLOBALS['cj_responder']=function(){throw new RuntimeException('coupon_auth');};
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_auth','CJ auth failure fixed code');
eq(count($GLOBALS['cj_reads']),1,'CJ auth never retries');
$GLOBALS['coupon_time']+=120;
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_auth','CJ auth remains suspended');
eq(count($GLOBALS['cj_reads']),1,'suspended CJ zero provider');
impactshop_coupon_set_enabled('cj',false);$GLOBALS['cj_responder']=function(){return cj_page();};
eq(impactshop_coupon_refresh('cj')['status'],'complete','operator config revision permits repaired auth pilot');
cj_reset();$GLOBALS['cj_responder']=function(){throw new RuntimeException('private provider message fixture-token');};
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_transport','unknown exception redacted');
cj_reset();$GLOBALS['cj_responder']=function(){return array_merge(cj_page(),['responseBytes'=>1048577]);};
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_limits','CJ aggregate byte cap');
cj_reset();$GLOBALS['cj_responder']=function(){ $GLOBALS['coupon_clock']=60;return cj_page(); };
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_limits','CJ late response rejected');
eq(count($GLOBALS['cj_reads']),1,'CJ deadline stops later resources');
cj_reset();$GLOBALS['cj_responder']=function($type,$page,$timeout,$call){if($call===1){$GLOBALS['coupon_clock']=59.5;}return cj_page();};
eq(impactshop_coupon_refresh('cj')['status'],'complete','CJ short remaining timeout accepted');
eq($GLOBALS['cj_reads'][1][2],0.5,'CJ remaining deadline applied to each request');
cj_reset();$GLOBALS['cj_responder']=function($type,$page){
    $rows=[];for($i=1;$i<=100;$i++){$rows[]=cj_row((string)(($page-1)*100+$i));}return cj_page($rows,2000,$page);
};
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_limits','CJ max20 includes all resource pages');
eq(count($GLOBALS['cj_reads']),20,'CJ no21st request');
cj_reset();$GLOBALS['cj_responder']=function($type,$page){
    if($page===2){return cj_page([cj_row('101')],102,2);} $rows=[];for($i=1;$i<=100;$i++){$rows[]=cj_row((string)$i);}return cj_page($rows,101,1);
};
check(is_wp_error(impactshop_coupon_refresh('cj')),'CJ changed total rejects partial scan');
cj_reset();$GLOBALS['cj_responder']=function($type,$page){
    if($page===2){return cj_page([cj_row('1')],101,2);} $rows=[];for($i=1;$i<=100;$i++){$rows[]=cj_row((string)$i);}return cj_page($rows,101,1);
};
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_schema_invalid','CJ cross-page duplicate rejects');
cj_reset();cj_activate();$GLOBALS['coupon_time']+=120;$GLOBALS['cj_responder']=function(){throw new RuntimeException('coupon_transport');};
check(is_wp_error(impactshop_coupon_refresh('cj')),'CJ refresh fails after good cache');
eq(cj_project()['status'],'unavailable','CJ failure never falls back to previous cache');
cj_reset();$p=get_option('impactshop_coupon_policy_v1');$p['bindings'][1]['attributionCompatibility']='unknown';update_option('impactshop_coupon_policy_v1',$p,false);
eq(impactshop_coupon_refresh('cj')->get_error_code(),'coupon_qualification_missing','CJ incompatible/no eligible scope stops');
eq($GLOBALS['cj_reads'],[],'CJ unknown compatibility no network');
cj_reset();impactshop_coupon_refresh('dognet');cj_activate();$GLOBALS['coupon_time']+=120;
$GLOBALS['coupon_actions']['impactshop_coupon_refresh_v1']();
eq(get_option('impactshop_coupon_refresh_state_v1_cj')['status'],'complete','existing hourly cron dispatches CJ');
eq(project()['status'],'available','Dognet stays available after combined cron');
check(!is_wp_error(impactshop_coupon_set_enabled('cj',false)),'CJ disable existing CLI operation');
eq(cj_project()['status'],'not_checked','disabled CJ not_checked');
echo "PASS $assertions assertions (offline; no DB/network)\n";
