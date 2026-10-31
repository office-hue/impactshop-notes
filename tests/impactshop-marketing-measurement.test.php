<?php
// Isolated WordPress fixture: wp_remote_post is a recording stub, never a network call.
define('ABSPATH', __DIR__);
$GLOBALS['hooks'] = [];
$GLOBALS['requests'] = [];
$GLOBALS['upstream'] = ['code' => 202, 'body' => '{"accepted":true}'];
function add_action($hook, $callback, $priority = 10) { $GLOBALS['hooks'][$hook][] = [$callback, $priority]; }
function register_rest_route($namespace, $route, $config) { $GLOBALS['registered'] = [$namespace, $route, $config]; }
function wp_json_encode($value) { return json_encode($value); }
function esc_url($url) { return htmlspecialchars($url, ENT_QUOTES); }
function wp_remote_post($url, $args) { $GLOBALS['requests'][] = [$url, $args]; return $GLOBALS['upstream']; }
function is_wp_error($response) { return $response === 'error'; }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
class WP_REST_Request {
    public function __construct(public string $body, public array $headers = []) {}
    public function get_body() { return $this->body; }
    public function get_header($key) { return $this->headers[$key] ?? ''; }
}
class WP_REST_Response {
    public function __construct(public array $data, public int $status, public array $headers) {}
}
require __DIR__ . '/../wp-content/mu-plugins/impactshop-marketing-measurement.php';
if (PHP_SAPI === 'cli-server') {
    putenv('MARKETING_SHARITY_APP_MEASUREMENT_ENABLED=1');
    putenv('MARKETING_MEASUREMENT_GATEWAY_URL=https://sharity-legal-production.up.railway.app');
    putenv('MARKETING_SHARITY_APP_MEASUREMENT_BFF_TOKEN=fixture-server-only');
    http_response_code(isset($_GET['error']) ? 404 : 200);
    header('Content-Type: ' . (isset($_GET['json']) ? 'application/json' : 'text/html; charset=UTF-8'));
    ($GLOBALS['hooks']['template_redirect'][0][0])();
    echo isset($_GET['json']) ? '{"fixture":true}' : '<!doctype html><html><body><p>Virtual guide fixture</p></body></html>';
    exit;
}
$count = 0;
function check($condition, string $label): void { global $count; ++$count; if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); } }
function request(array $overrides = [], ?string $body = null): WP_REST_Request {
    return new WP_REST_Request($body ?? '{"schemaVersion":2,"routeId":"sharity-app.about"}', array_merge(['origin' => 'https://app.sharity.hu', 'sec-fetch-site' => 'same-origin', 'content-type' => 'application/json'], $overrides));
}
foreach (['MARKETING_SHARITY_APP_MEASUREMENT_ENABLED', 'MARKETING_MEASUREMENT_GATEWAY_URL', 'MARKETING_SHARITY_APP_MEASUREMENT_BFF_TOKEN'] as $key) putenv($key);
$_SERVER = ['HTTP_HOST' => 'app.sharity.hu', 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/rolunk/'];
$_COOKIE = ['__Host-impactshop-measurement-consent' => 'granted-v2'];
check(impactshop_marketing_measurement_collect(request())->status === 503, 'default off');
putenv('MARKETING_SHARITY_APP_MEASUREMENT_ENABLED=1');
putenv('MARKETING_MEASUREMENT_GATEWAY_URL=https://sharity-legal-production.up.railway.app');
putenv('MARKETING_SHARITY_APP_MEASUREMENT_BFF_TOKEN=fixture-server-only');
foreach (['', 'https://app.sharity.hu/', 'https://app.sharity.hu.evil.example', 'https://sharity.hu'] as $origin) check(impactshop_marketing_measurement_collect(request(['origin' => $origin]))->status === 403, 'exact origin');
check(impactshop_marketing_measurement_collect(request(['sec-fetch-site' => 'same-site']))->status === 403, 'same origin fetch site');
$_COOKIE = []; check(impactshop_marketing_measurement_collect(request())->status === 403, 'consent required');
$_COOKIE['__Host-impactshop-measurement-consent'] = 'granted'; check(impactshop_marketing_measurement_collect(request())->status === 403, 'old consent invalid');
$_COOKIE['__Host-impactshop-measurement-consent'] = 'granted-v2';
check(impactshop_marketing_measurement_collect(request([], str_repeat(' ', 2049)))->status === 413, 'body bound');
foreach (['{}', 'null', '{bad', '{"schemaVersion":2,"routeId":"sharity.home"}', '{"schemaVersion":2,"routeId":"sharity-app.about","session":"secret"}', '{"schemaVersion":"2","routeId":"sharity-app.about"}'] as $bad) check(impactshop_marketing_measurement_collect(request([], $bad))->status === 400, 'strict payload');
check(impactshop_marketing_measurement_collect(request(['content-type' => 'text/plain']))->status === 400, 'json only');
check(count($GLOBALS['requests']) === 0, 'denied requests never forwarded');
$result = impactshop_marketing_measurement_collect(request(['cookie' => 'secret', 'referer' => 'https://app.sharity.hu/?private=1']));
check($result->status === 202 && $result->data === ['accepted' => true], 'accepted response');
[$url, $args] = $GLOBALS['requests'][0];
check($url === 'https://sharity-legal-production.up.railway.app/api/marketing/measurement/events', 'fixed upstream');
check($args['headers'] === ['Content-Type' => 'application/json', 'x-marketing-origin' => 'https://app.sharity.hu', 'x-marketing-bff-token' => 'fixture-server-only'], 'allowlisted rebuilt headers');
check($args['body'] === '{"schemaVersion":2,"routeId":"sharity-app.about"}' && $args['redirection'] === 0 && $args['timeout'] <= 10, 'minimal payload no redirects');
foreach (['error', ['code' => 302, 'body' => 'secret'], ['code' => 202, 'body' => '{"accepted":false,"secret":"private"}']] as $failure) {
    $GLOBALS['upstream'] = $failure; $before = count($GLOBALS['requests']);
    $result = impactshop_marketing_measurement_collect(request());
    check($result->status === 503 && $result->data === ['accepted' => false], 'safe upstream failure');
    check(count($GLOBALS['requests']) === $before + 1, 'no retry');
}
putenv('MARKETING_MEASUREMENT_GATEWAY_URL=https://other.invalid');
check(!impactshop_marketing_measurement_enabled(), 'noncanonical target disables');
putenv('MARKETING_MEASUREMENT_GATEWAY_URL=https://sharity-legal-production.up.railway.app');
foreach (['/impactshop/', '/rolunk/', '/cegeknek/', '/befektetoknek/', '/ngo-guides/', '/ngo-guides/impact-shop/', '/ngo-guides/impact-challenge/', '/ngo-guides/ngo-card/'] as $path) check(impactshop_marketing_measurement_route($path) !== null, 'public route allowed');
foreach (['/jysk-riport/', '/jysk-riport.data.json', '/ngo-guides/jogi-dokumentumok/', '/profil/', '/go/', '/ngo-guides/other/', '/rolunk////'] as $path) check(impactshop_marketing_measurement_route($path) === null, 'protected route excluded');
$html = '<!doctype html><html><body><p>Existing guide</p></body></html>';
$injected = impactshop_marketing_measurement_html($html, 'text/html; charset=UTF-8', 200);
check(substr_count($injected, 'impactshop-marketing-script') === 1 && str_contains($injected, '<p>Existing guide</p>'), 'additive full HTML injection');
check(impactshop_marketing_measurement_html($injected, 'text/html', 200) === $injected, 'no duplicate script');
check(!str_contains($injected, 'fixture-server-only'), 'secret never injected');
foreach ([['application/json', 200], ['text/html', 404], ['text/html', 302]] as [$type, $status]) check(impactshop_marketing_measurement_html($html, $type, $status) === $html, 'non-HTML/errors unchanged');
check(impactshop_marketing_measurement_html('{"data":1}', 'application/json', 200) === '{"data":1}', 'JSON unchanged');
check($GLOBALS['hooks']['template_redirect'][0][1] === 0, 'injection registered before exiting guides');
$level = ob_get_level(); ($GLOBALS['hooks']['template_redirect'][0][0])(); check(ob_get_level() === $level + 1, 'eligible guide starts output capture'); ob_end_clean();
$_SERVER['REQUEST_URI'] = '/jysk-riport/?print=1'; ($GLOBALS['hooks']['template_redirect'][0][0])(); check(ob_get_level() === $level, 'JYSK does not start capture');
($GLOBALS['hooks']['rest_api_init'][0][0])();
check($GLOBALS['registered'][0] === 'impactshop-marketing/v1' && $GLOBALS['registered'][2]['methods'] === 'POST', 'fixed POST route');
echo "$count checks passed\n";
