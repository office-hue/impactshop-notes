<?php
declare(strict_types=1);

// Isolated actual panel function. No WordPress bootstrap, network or live DB.
const TEST_SECRET = 'replace_test_only_profile_return'; // Public synthetic HMAC input, never a credential.
const TEST_COOKIE = '__Host-impact_profile_return_ctx';
const TEST_CARD = 'https://sharity.hu/api/sharity/session/start?return_intent=sajat-sharity&profile_return=1';
const TEST_FACTLENS = 'https://factlens.eu/factlens/vb-prod/';
const TEST_ENTRY = 'https://app.sharity.hu/profil/?bridge_target=restore&impactshop_profile_return_complete=1&return=https%3A%2F%2Fsharity.hu%2Fsajat-sharity';

function run_request(array $input): array
{
    $process = proc_open([PHP_BINARY, __FILE__, '--request'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || $err !== '') {
        throw new RuntimeException('Fixture failed: ' . $err . $out);
    }
    return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
}

if (($argv[1] ?? '') !== '--request') {
    $cases = [
        ['no cookie', [], TEST_CARD],
        ['valid', ['cookie' => 'signed', 'scenario' => 'valid'], TEST_FACTLENS],
        ['account', ['url' => str_replace('restore', 'account', TEST_ENTRY)], TEST_CARD],
        ['missing resolver', ['scenario' => 'missing-resolver'], null],
        ['resolver custom result', ['scenario' => 'custom'], TEST_FACTLENS],
    ];
    foreach (['invalid', 'empty', 'array', 'expired', 'consumed', 'missing-row', 'wrong-target', 'wrong-browser', 'wrong-agent', 'consume-race'] as $scenario) {
        $cases[] = [$scenario, ['cookie' => 'signed', 'scenario' => $scenario], 'native'];
    }
    foreach (['&return=https%3A%2F%2Fsharity.hu%2Fsajat-sharity', '&return[]=bad', '&selection_intent=', '&selection_intent[]=x', '&prctx=prctx_fixture', '&prctx=', '&prctx[]=bad', '&bridge_target=restore', '&impactshop_profile_return_complete=1'] as $suffix) {
        $cases[] = [$suffix, ['url' => TEST_ENTRY . $suffix, 'scenario' => 'missing-row'], 'native'];
    }
    foreach (['https://evil.example/', 'https://sharity.hu:443/sajat-sharity', 'https://user@sharity.hu/sajat-sharity', 'https://sharity.hu/sajat-sharity?x=1', 'https://sharity.hu/sajat-sharity#x', '/sajat-sharity'] as $value) {
        $cases[] = [$value, ['url' => preg_replace('/&return=.*/', '&return=' . rawurlencode($value), TEST_ENTRY)], 'native'];
    }
    $cases[] = ['array before scalar', ['url' => str_replace('&return=', '&return[]=x&return=', TEST_ENTRY)], 'native'];
    $cases[] = ['encoded duplicate key', ['url' => TEST_ENTRY . '&%72eturn=https%3A%2F%2Fsharity.hu%2Fsajat-sharity'], 'native'];
    $cases[] = ['foreign host', ['home' => 'https://app-staging.sharity.hu'], 'native'];
    $cases[] = ['non HTTPS', ['ssl' => false], 'native'];
    $cases[] = ['POST', ['method' => 'POST'], 'native'];
    $cases[] = ['wrong path', ['url' => str_replace('/profil/', '/other/', TEST_ENTRY)], 'native'];
    // The installed bridge also supports a query-context lane. Exercise it only
    // when that exact source was supplied; never treat the older source as parity.
    $bridgeSource = getenv('FACTLENS_BRIDGE_SOURCE');
    if ($bridgeSource && str_contains((string) file_get_contents($bridgeSource), 'function read_profile_return_context_id_from_request')) {
        $cases[] = ['query valid', ['url' => TEST_ENTRY . '&prctx=prctx_fixture'], TEST_FACTLENS];
        foreach (['expired', 'consumed', 'wrong-agent', 'wrong-target', 'consume-race'] as $scenario) {
            $cases[] = ['query ' . $scenario, ['url' => TEST_ENTRY . '&prctx=prctx_fixture', 'scenario' => $scenario], 'native'];
        }
    }
    foreach ($cases as [$name, $input, $expected]) {
        $result = run_request($input);
        $native = ($input['home'] ?? 'https://app.sharity.hu') . '/profil/#impactshop-restore-title';
        $wanted = $expected === 'native' ? $native : $expected;
        if ($result['target'] !== $wanted) {
            throw new RuntimeException($name . ': wrong target ' . json_encode($result));
        }
        $calls = ($input['scenario'] ?? '') === 'missing-resolver' ? 0 : 1;
        if (count($result['resolverCalls']) !== $calls) {
            throw new RuntimeException($name . ': resolver invocation count');
        }
        if ($calls && $result['resolverCalls'][0][1] !== (($name === 'account') ? 'https://app.sharity.hu/profil/' : $native)) {
            throw new RuntimeException($name . ': changed native fallback');
        }
        if ($result['realResolver'] && $result['consumes'] !== (in_array($name, ['valid', 'query valid'], true) ? 1 : 0)) {
            throw new RuntimeException($name . ': unexpected consumption');
        }
        $expectedWarnings = ($name === '&prctx[]=bad' && $bridgeSource &&
            str_contains((string) file_get_contents($bridgeSource), 'function read_profile_return_context_id_from_request')) ? 1 : 0;
        if (count($result['resolverWarnings']) !== $expectedWarnings) {
            throw new RuntimeException($name . ': unexpected resolver warning');
        }
    }
    echo count($cases) . " completion cases PASS; canonical resolver: " . (getenv('FACTLENS_BRIDGE_SOURCE') ? 'tested' : 'NOT RUN') . "\n";
    exit;
}

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$scenario = $input['scenario'] ?? 'valid';
$url = parse_url($input['url'] ?? TEST_ENTRY);
$_SERVER = ['REQUEST_URI' => ($url['path'] ?? '/') . '?' . ($url['query'] ?? ''), 'QUERY_STRING' => $url['query'] ?? '', 'REQUEST_METHOD' => $input['method'] ?? 'GET', 'HTTP_USER_AGENT' => 'fixture-agent'];
parse_str($_SERVER['QUERY_STRING'], $_GET);
$_REQUEST = $_GET;
$_COOKIE = [];
if (array_key_exists('cookie', $input)) {
    $_COOKIE[TEST_COOKIE] = $input['cookie'] === 'signed'
        ? 'fixture-id.fixture-nonce.' . hash_hmac('sha256', 'fixture-id|fixture-nonce', TEST_SECRET)
        : $input['cookie'];
    if ($scenario === 'empty') $_COOKIE[TEST_COOKIE] = '';
    if ($scenario === 'invalid') $_COOKIE[TEST_COOKIE] = 'malformed';
    if ($scenario === 'array') $_COOKIE[TEST_COOKIE] = ['malformed'];
}
$resolverCalls = [];
$consumes = 0;
$row = ['id' => 'fixture-id', 'status' => $scenario === 'consumed' ? 'consumed' : 'issued', 'expires_at' => gmdate('Y-m-d H:i:s', time() + ($scenario === 'expired' ? -30 : 300)), 'ui_target' => $scenario === 'wrong-target' ? 'impactshop_account' : 'impactshop_restore', 'browser_binding_hash' => hash('sha256', $scenario === 'wrong-browser' ? 'wrong' : 'fixture-nonce'), 'user_agent_hash' => hash('sha256', $scenario === 'wrong-agent' ? 'wrong' : 'fixture-agent'), 'return_lane' => 'factlens_vb2026'];
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function add_action(...$args): void {}
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function is_admin(): bool { return false; }
function is_ssl(): bool { return $GLOBALS['input']['ssl'] ?? true; }
function home_url(string $path = '/'): string { return ($GLOBALS['input']['home'] ?? 'https://app.sharity.hu') . $path; }
function wp_parse_url(string $url, int $component = -1) { return parse_url($url, $component); }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value): string { return trim((string) $value); }
function apply_filters($tag, $value, ...$args) { return $value; }
function get_option($key, $default = '') { return $key === 'impactshop_profile_return_signing_secret' ? TEST_SECRET : $default; }
function current_time(...$args): string { return gmdate('Y-m-d H:i:s'); }
final class FixtureRedirect extends RuntimeException {
    public function __construct(public string $target, public int $status) { parent::__construct('fixture redirect'); }
}
function wp_redirect($url, $status = 302): void { throw new FixtureRedirect($url, $status); }
function wp_safe_redirect($url, $status = 302): void { throw new FixtureRedirect($url, $status); }
final class FixtureDatabase {
    public string $prefix = 'fixture_';
    public function prepare($sql, ...$args): array { return [$sql, $args]; }
    public function get_row($query, $format) { return $GLOBALS['scenario'] === 'missing-row' ? null : $GLOBALS['row']; }
    public function update($table, $values, ...$args): int { $GLOBALS['row'] = array_merge($GLOBALS['row'], $values); return 1; }
    public function query($query): int {
        [$sql, $args] = $query;
        if (str_contains($sql, 'consumed_at')) {
            if ($GLOBALS['scenario'] === 'consume-race' || $GLOBALS['row']['status'] !== 'issued') return 0;
            $GLOBALS['row']['status'] = 'consumed';
            $GLOBALS['consumes']++;
            return 1;
        }
        if (str_contains($sql, 'expires_at') && strtotime($GLOBALS['row']['expires_at']) < time()) $GLOBALS['row']['status'] = 'expired';
        return 0;
    }
}
$wpdb = new FixtureDatabase();
$bridge = getenv('FACTLENS_BRIDGE_SOURCE');
if ($bridge && !is_file($bridge)) throw new RuntimeException('Canonical bridge source missing');
$resolverWarnings = [];
set_error_handler(function ($level, $message, $file) use ($bridge, &$resolverWarnings): bool {
    // Existing deployed bridge casts prctx[] to string. Record this exact legacy
    // diagnostic instead of calling it warning-free or changing bridge semantics.
    if ($bridge && realpath($file) === realpath($bridge) && $level === E_WARNING &&
        $message === 'Array to string conversion' && is_array($_GET['prctx'] ?? null)) {
        $resolverWarnings[] = $message;
        return true;
    }
    return false;
});
if ($scenario !== 'missing-resolver') {
    function impactshop_factlens_profile_return_target(string $target, string $fallback = ''): string {
        $GLOBALS['resolverCalls'][] = [$target, $fallback];
        if ($GLOBALS['scenario'] === 'custom') return TEST_FACTLENS;
        if ($GLOBALS['bridge']) return ImpactShop_Factlens_Identity_Bridge::resolve_profile_return_after_success($target, $fallback);
        $cookie = $_COOKIE[TEST_COOKIE] ?? null;
        if (is_string($cookie) && str_starts_with($cookie, 'fixture-id.') && $GLOBALS['scenario'] === 'valid') return TEST_FACTLENS;
        // Simulate a resolver clearing its input too: the panel must retain its
        // pre-call presence decision, not inspect the cookie after resolution.
        unset($_COOKIE[TEST_COOKIE]);
        return $fallback;
    }
    if ($bridge) require $bridge;
}
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-identity-panel.php';
$result = ['target' => null, 'status' => null];
try { impactshop_identity_maybe_complete_profile_return(); }
catch (FixtureRedirect $redirect) { $result = ['target' => $redirect->target, 'status' => $redirect->status]; }
echo json_encode($result + ['resolverCalls' => $resolverCalls, 'realResolver' => (bool) $bridge, 'consumes' => $consumes, 'resolverWarnings' => $resolverWarnings], JSON_THROW_ON_ERROR);
