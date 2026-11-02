<?php
// Standalone WordPress boundary fixture; no database, HTTP or option writes.
define('ABSPATH', __DIR__);
class WP_REST_Server { public const READABLE = 'GET'; }
class WP_REST_Response
{
    public function __construct(public array $data, public int $status, public array $headers) {}
}
$stored = [];
$filter = null;
$actions = [];
$routes = [];
function add_action($hook, $callback) { $GLOBALS['actions'][$hook] = $callback; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['routes'][$namespace . $route] = $args; }
function get_option($key, $default) {
    if ($key !== 'impactshop_ngo_card_global_announcement') { throw new RuntimeException('wrong option'); }
    return $GLOBALS['stored'];
}
function apply_filters($hook, $value, $raw) {
    if ($hook !== 'impactshop_ngo_card_share_announcement') { throw new RuntimeException('wrong filter'); }
    return $GLOBALS['filter'] ? ($GLOBALS['filter'])($value, $raw) : $value;
}
function wp_strip_all_tags($value, $breaks) { return preg_replace('/[\r\n\t ]+/', ' ', strip_tags($value)); }
function wp_parse_url($value) { return parse_url($value); }
function esc_url_raw($value, $protocols) { return $value; }
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-ngo-card-news.php';
$assertions = 0;
function check($ok, $description) {
    $GLOBALS['assertions']++;
    if (!$ok) { throw new RuntimeException($description); }
}
$actions['rest_api_init']();
$route = $routes['impact/v1/ngo-card-news'];
check($route['methods'] === 'GET', 'read-only route');
check($route['permission_callback'] === '__return_true', 'public content only');
$response = ImpactShop_Ngo_Card_News::read();
check($response->data['announcement'] === null, 'empty option removes news');
check($response->headers['Cache-Control'] === 'no-store, max-age=0', 'no extended browser or CDN cache');
$stored = ['text' => '  <b>Friss hír</b>  ', 'url' => 'https://sharity.hu/hirek'];
check(ImpactShop_Ngo_Card_News::read()->data['announcement'] === ['text' => 'Friss hír', 'url' => 'https://sharity.hu/hirek'], 'reuse existing option');
$stored = ['text' => str_repeat('ő', 205)];
check(ImpactShop_Ngo_Card_News::read()->data['announcement']['text'] === str_repeat('ő', 200), 'Unicode length limit');
foreach (['javascript:alert(1)', 'http://sharity.hu', 'https://user:secret@sharity.hu', "https://sharity.hu/\nredirect"] as $bad) {
    $stored = ['text' => 'Hír', 'url' => $bad];
    check(ImpactShop_Ngo_Card_News::read()->data['announcement']['url'] === '', 'reject unsafe link');
}
$stored = ['text' => ['unexpected']];
check(ImpactShop_Ngo_Card_News::read()->data['announcement'] === null, 'non-text content is absent');
$filter = fn($value, $raw) => ['text' => '<b>Szűrt hír</b>', 'url' => 'javascript:alert(1)'];
check(ImpactShop_Ngo_Card_News::read()->data['announcement'] === ['text' => 'Szűrt hír', 'url' => ''], 'sanitize filter output');
$filter = fn($value, $raw) => [];
check(ImpactShop_Ngo_Card_News::read()->data['announcement'] === null, 'filter can remove news');
echo json_encode(['status' => 'fixture-pass', 'assertions' => $assertions, 'liveWrites' => false]) . "\n";
