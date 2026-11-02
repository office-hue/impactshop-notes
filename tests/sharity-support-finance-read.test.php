<?php
/** Isolated WP/DB transport fixtures. Actual MNB caller/parser; no network/DB. */
declare(strict_types=1);
$mode = $argv[1] ?? 'validation';
$options = $actions = $routes = $requests = $transients = $cacheDeletes = [];
$writeFails = false;
$checks = 0;
function check($condition, string $message): void {
    $GLOBALS['checks']++;
    if (!$condition) { throw new RuntimeException($message); }
}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function add_option($key, $value, $deprecated = '', $autoload = null) {
    check($autoload === false, 'attempt is not autoloaded');
    if (array_key_exists($key, $GLOBALS['options'])) { return false; }
    $GLOBALS['options'][$key] = $value; return true;
}
function update_option($key, $value, $autoload = null) {
    check($autoload === false, 'cache is not autoloaded');
    if ($GLOBALS['writeFails']) { return false; }
    $GLOBALS['options'][$key] = $value; return true;
}
function wp_cache_delete($key, $group) { $GLOBALS['cacheDeletes'][] = [$key, $group]; }
function add_action($hook, $callback) { $GLOBALS['actions'][$hook][] = $callback; }
function register_rest_route($namespace, $path, $args) { $GLOBALS['routes'][$namespace.$path] = $args; }
function __return_true() { return true; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function is_wp_error($r) { return false; }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = [$url, $args];
    check(str_starts_with($url, 'https://www.mnb.hu/arfolyam-tablazat?query&'), 'official HTTPS only');
    check($args === ['timeout'=>10, 'redirection'=>0, 'sslverify'=>true], 'bounded verified transport');
    if ($GLOBALS['mode'] === 'failure') { return ['status'=>429, 'body'=>'']; }
    if ($GLOBALS['mode'] === 'exception') { throw new RuntimeException('fixture transport failure'); }
    parse_str(parse_url($url, PHP_URL_QUERY), $query); $currency = $query['devizaSelected'];
    $rates = ['EUR'=>'400,50', 'USD'=>'370,25', 'GBP'=>'470,75'];
    $date = new DateTimeImmutable('yesterday', new DateTimeZone('Europe/Budapest'));
    if ($GLOBALS['mode'] === 'mixed' && $currency === 'USD') { $date = $date->modify('-1 day'); }
    $months = ['január','február','március','április','május','június','július','augusztus','szeptember','október','november','december'];
    $label = $date->format('Y. ') . $months[(int)$date->format('n')-1] . $date->format(' j.');
    $body = '<table class="pricetable"><thead><tr><th></th><th>'.$currency.'</th></tr><tr><th></th><th>fixture</th></tr><tr><th></th><th>1</th></tr></thead><tbody><tr><td>'.$label.'</td><td>'.$rates[$currency].'</td></tr></tbody></table>';
    return ['status'=>200, 'body'=>$body];
}
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $data, public int $status) {}
    public function header($key, $value) { $this->headers[$key] = $value; }
}
class FxFixtureDb {
    public string $options = 'wp_options';
    public bool $race = false;
    public bool $fail = false;
    public function prepare($sql, ...$args) { return [$sql, $args]; }
    public function get_var($query) {
        check(str_contains($query[0], 'SELECT option_value FROM wp_options WHERE option_name = %s'), 'exact option DB read');
        return $GLOBALS['options'][$query[1][0]] ?? null;
    }
    public function query($query) {
        check(str_contains($query[0], 'WHERE option_name = %s AND option_value = %s'), 'CAS retains exact old value');
        [$today, $key, $old] = $query[1];
        if ($this->fail) { return false; }
        if ($this->race) { $GLOBALS['options'][$key] = $today; }
        if (($GLOBALS['options'][$key] ?? null) !== $old) { return 0; }
        $GLOBALS['options'][$key] = $today; return 1;
    }
}
$wpdb = new FxFixtureDb();
if ($mode !== 'missing') { require __DIR__.'/../wp-content/mu-plugins/sharity-points-money.php'; }
require __DIR__.'/../wp-content/mu-plugins/sharity-support-finance-read.php';
check($options === [] && $requests === [], 'module load neither writes options nor networks');
// No wp_schedule_event stub: any automatic scheduling is a fatal test failure.
foreach ($actions['rest_api_init'] as $callback) { $callback(); }
$route = $routes['sharity-finance/v1/fx-quotes'];
check($route['methods'] === 'GET' && $route['permission_callback'](), 'public GET only');
check(sharity_support_fx_refresh() === ['status'=>'skipped','reason'=>'disabled'], 'default OFF');
check($route['callback']()->data['status'] === 'unavailable' && $requests === [], 'cold GET never fetches');

if ($mode !== 'validation') {
    $options[SHARITY_SUPPORT_FX_ENABLED_KEY] = '1';
    $oldCache = ['untouched'=>'previous cache'];
    $options[SHARITY_SUPPORT_FX_CACHE_KEY] = $oldCache;
    $writeFails = $mode === 'write-failure';
    $result = $actions['sharity_support_fx_refresh'][0]();
    if ($mode === 'success') {
        check($result['status'] === 'current' && count($requests) === 3, 'three requests publish one valid bundle');
        $options[SHARITY_SUPPORT_FX_ENABLED_KEY] = '0';
        $response = $route['callback']();
        check($response->data['status'] === 'current' && count($requests) === 3, 'fresh read while refresh OFF; no fetch');
        check($response->headers['Cache-Control'] === 'no-store', 'no intermediary freshness extension');
        check($response->data['quotes'] === ['EUR'=>['rate'=>'400.50','unit'=>'1'],'USD'=>['rate'=>'370.25','unit'=>'1'],'GBP'=>['rate'=>'470.75','unit'=>'1']], 'exact provider strings, HUF implicit');
        $oldCache = $options[SHARITY_SUPPORT_FX_CACHE_KEY];
    } else {
        $reason = ['failure'=>'source_failed','exception'=>'source_failed','mixed'=>'quotes_invalid','missing'=>'source_missing','write-failure'=>'cache_write_unconfirmed'][$mode];
        check($result === ['status'=>'unavailable','reason'=>$reason], 'explicit failed refresh reason');
        check($options[SHARITY_SUPPORT_FX_CACHE_KEY] === $oldCache, 'failure keeps previous cache unchanged');
    }
    $options[SHARITY_SUPPORT_FX_ENABLED_KEY] = '1';
    $count = count($requests); sharity_support_fx_refresh();
    check(count($requests) === $count && $count <= 3, 'success/failure consume day; no second request batch');
    check($options[SHARITY_SUPPORT_FX_CACHE_KEY] === $oldCache, 'no repeated timestamp publication');
    echo "PASS FX refresh $mode: $checks assertions\n"; exit(0);
}

$now = strtotime('2026-11-01T12:00:00Z');
$saved = ['fetched_at'=>gmdate('Y-m-d\TH:i:s\Z', $now), 'quotes'=>[]];
foreach (['EUR','USD','GBP'] as $currency) { $saved['quotes'][$currency] = ['success'=>true,'currency'=>$currency,'date'=>'2026-10-30','rate'=>'400.50','unit'=>'1']; }
check(sharity_support_fx_payload($saved,$now)['status'] === 'current', 'Friday quote valid on Sunday');
foreach ([36*3600=>true, 36*3600+1=>false, -1=>false] as $age=>$valid) {
    $s=$saved; $s['fetched_at']=gmdate('Y-m-d\TH:i:s\Z',$now-$age);
    check((sharity_support_fx_payload($s,$now)['status']==='current')===$valid, 'exact retrieval age '.$age);
}
foreach (['2026-10-25'=>true,'2026-10-24'=>false,'2026-11-02'=>false,'2026-02-30'=>false,'garbage'=>false] as $date=>$valid) {
    $s=$saved; foreach ($s['quotes'] as &$q) { $q['date']=$date; } unset($q);
    check((sharity_support_fx_payload($s,$now)['status']==='current')===$valid, 'calendar date '.$date);
}
foreach ([null,[],false,'2026-11-01T12:00:01Z','2026-11-01T12:60:00Z','2026-11-01T12:00:00+00:00'] as $stamp) {
    $s=$saved; $s['fetched_at']=$stamp; check(sharity_support_fx_payload($s,$now)['status']==='unavailable','strict timestamp');
}
foreach (['-1','0','0.00','1e3','01',' 1','1\n',400.5,400,null,[]] as $rate) {
    $s=$saved; $s['quotes']['EUR']['rate']=$rate; check(sharity_support_fx_payload($s,$now)['status']==='unavailable','strict positive string rate');
}
foreach (['0','2','1.0','10000000',100,null] as $unit) {
    $s=$saved; $s['quotes']['EUR']['unit']=$unit; check(sharity_support_fx_payload($s,$now)['status']==='unavailable','invalid unit');
}
$s=$saved; $s['quotes']['EUR']['unit']='100'; check(sharity_support_fx_payload($s,$now)['quotes']['EUR']['unit']==='100','unit100 retained');
$s=$saved; $s['quotes']['USD']['date']='2026-10-29'; check(sharity_support_fx_payload($s,$now)['status']==='unavailable','mixed dates');
$s=$saved; $s['quotes']['USD']['currency']='EUR'; check(sharity_support_fx_payload($s,$now)['status']==='unavailable','currency mismatch');
$s=$saved; unset($s['quotes']['GBP']); check(sharity_support_fx_payload($s,$now)['status']==='unavailable','incomplete bundle');
$s=$saved; $s['private']='must not leak'; $s['quotes']['EUR']['secret']='must not leak'; check(!str_contains(json_encode(sharity_support_fx_payload($s,$now)),'must not leak'),'whitelisted response');
$s=$saved; $s['fetched_at']='2026-10-31T12:00:00Z'; foreach($s['quotes'] as &$q){$q['date']='2026-11-01';} unset($q);
check(sharity_support_fx_payload($s,$now)['status']==='unavailable','quote cannot postdate retrieval');

check(sharity_support_fx_claim_day('2026-11-01'), 'initial day insert');
check(!sharity_support_fx_claim_day('2026-11-01'), 'duplicate day rejected');
check(sharity_support_fx_claim_day('2026-11-02'), 'new day CAS');
$wpdb->race=true; check(!sharity_support_fx_claim_day('2026-11-03'), 'concurrent CAS loser rejected');
$wpdb->race=false; $wpdb->fail=true; check(!sharity_support_fx_claim_day('2026-11-04'), 'DB CAS error no provider permission');
check(count($options)===1, 'one bounded attempt option');
check($requests===[], 'all GET and validation paths no network');
echo "PASS FX cache validation/CAS: $checks assertions\n";
foreach (['success','failure','exception','mixed','missing','write-failure'] as $case) {
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($case),$exit);
    check($exit===0,'isolated refresh '.$case);
}
