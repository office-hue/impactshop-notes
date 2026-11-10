<?php
// Offline WP boundary fixture: no database, HTTP, option or campaign writes.
define('ABSPATH', __DIR__);
define('IMPACTSHOP_VB2026_NGO_CAMPAIGN_KEY', 'vb2026');
class WP_REST_Request {
    public function __construct(private array $params = [], private string $method = 'GET', private string $route = '/impact/v1/ngo-catalog') {}
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function get_method() { return $this->method; }
    public function get_route() { return $this->route; }
}
class WP_REST_Response {
    public array $headers = [];
    public function __construct(public array $data = [], private int $status = 200) {}
    public function get_status() { return $this->status; }
    public function header($key, $value) { $this->headers[$key] = $value; }
}
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data) {}
}
class ImpactShop_NGO_Card_API {
    public static function get_approved_slug_records() { return $GLOBALS['approved']; }
}
$hook = null; $registered = []; $approved = []; $primary = []; $totalOverride = null; $queries = 0; $assertions = 0;
function add_filter($name, $callback, $priority, $args) {
    if ($name !== 'rest_request_after_callbacks' || $args !== 3) { throw new RuntimeException('Unexpected hook'); }
    $GLOBALS['hook'] = $callback;
}
function get_option($key, $default) {
    if ($key !== 'impactshop_ngo_card_directory_v1') { throw new RuntimeException('Unexpected option'); }
    return $GLOBALS['registered'];
}
function wp_strip_all_tags($value) { return strip_tags($value); }
function wp_parse_url($value) { return parse_url($value); }
function remove_accents($value) { return strtr($value, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ö'=>'O','Ő'=>'O','Ú'=>'U','Ü'=>'U','Ű'=>'U']); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function impactshop_vb2026_catalog_query($filters, $page, $perPage) {
    $GLOBALS['queries']++;
    if ($filters['campaign'] !== 'vb2026' || $filters['active_only'] !== true || $filters['featured_only'] !== false
        || $page !== 1 || $perPage !== 10000 || $filters['q'] !== '') { throw new RuntimeException('Unexpected primary query'); }
    return ['items' => $GLOBALS['primary'], 'pagination' => ['total_items' => $GLOBALS['totalOverride'] ?? count($GLOBALS['primary'])]];
}
require dirname(__DIR__) . '/wp-content/mu-plugins/impactshop-ngo-card-directory.php';
function check($ok, $message) {
    $GLOBALS['assertions']++;
    if (!$ok) { throw new RuntimeException($message); }
}
function call_directory(array $params = []) {
    return ($GLOBALS['hook'])(new WP_REST_Response(), [], new WP_REST_Request(['card_directory' => '1', 'active_only' => '1', 'featured_only' => '0'] + $params));
}
$original = new WP_REST_Response(['old' => true]);
foreach ([new WP_REST_Request(), new WP_REST_Request(['card_directory' => '0']),
    new WP_REST_Request(['card_directory' => '1'], 'POST'),
    new WP_REST_Request(['card_directory' => '1'], 'GET', '/impact/v1/vb2026/featured-ngos')] as $request) {
    check($hook($original, [], $request) === $original, 'non-Card routes are exactly unchanged');
}
check($queries === 0, 'ordinary VB/catalog path has no additional reads');
$error = new WP_Error('denied', 'denied', ['status' => 403]);
check($hook($error, [], new WP_REST_Request(['card_directory' => '1'])) === $error, 'preserve permission error');
$notFound = new WP_REST_Response([], 404);
check($hook($notFound, [], new WP_REST_Request(['card_directory' => '1'])) === $notFound, 'preserve failed original response');
$primary = [['ngo_id' => 28, 'slug' => 'existing', 'name' => 'Régi szervezet', 'is_active' => true, 'private_email' => 'private@example.test']];
$registered = [['slug' => 'new', 'name' => 'Új szervezet', 'is_active' => true, 'city' => 'Székesfehérvár',
    'short_mission' => 'Közösségi programok.', 'category_label' => 'Kultúra', 'website_url' => 'https://example.test/',
    'ngo_id' => 999, 'private_email' => 'private@example.test', 'bank_account' => 'private', 'amount' => 500]];
$approved = ['new' => ['name' => 'Új szervezet']];
$r = call_directory(['per_page' => 1]);
check($r instanceof WP_REST_Response && $r->get_status() === 200, 'complete merged response');
check($r->data['pagination'] === ['page' => 1, 'per_page' => 1, 'total_items' => 2, 'total_pages' => 2], 'pagination includes new Cards');
check($r->data['results'][0]['ngo_id'] === 28, 'canonical financial identity is preserved');
check(!isset($r->data['results'][0]['private_email']), 'private canonical fields excluded');
$r2 = call_directory(['per_page' => 1, 'page' => 2]);
$new = $r2->data['results'][0];
check($new['name'] === 'Új szervezet' && $new['website_url'] === 'https://example.test/', 'public metadata retained');
foreach (['ngo_id', 'private_email', 'bank_account', 'amount', 'is_featured_vb2026', 'allow_user_selection'] as $field) {
    check(!array_key_exists($field, $new), 'new Card cannot claim ' . $field);
}
check($r2->headers['Cache-Control'] === 'no-store, max-age=0', 'fresh directory response');
check(call_directory(['q' => 'kozossegi'])->data['pagination']['total_items'] === 1, 'accent-insensitive mission search');
check(call_directory(['city' => 'Székesfehérvár'])->data['pagination']['total_items'] === 1, 'city filter');
check(call_directory(['category' => 'Kultúra'])->data['pagination']['total_items'] === 1, 'category filter');
check(call_directory(['legal_status' => 'Közhasznú'])->data['pagination']['total_items'] === 0, 'no invented legal status');
check(call_directory(['per_page' => 200])->data['pagination']['per_page'] === 100, 'bounded pagination');
check(call_directory(['page' => 3, 'per_page' => 1])->data['results'] === [], 'no repeated last page');
foreach (['campaign' => 'other', 'active_only' => '0', 'featured_only' => '1'] as $field => $value) {
    $r = $hook($original, [], new WP_REST_Request(['card_directory' => '1', $field => $value]));
    check($r instanceof WP_Error && $r->data['status'] === 400, 'no campaign/active-scope expansion');
}
$approved = [];
check(call_directory()->data['pagination']['total_items'] === 1, 'unapproved Card remains hidden');
$approved = ['new' => ['name' => 'Másik szervezet']];
check(call_directory() instanceof WP_Error, 'mismatched native approval fails closed');
$approved = ['new' => ['name' => 'Új szervezet']];
$registered[0]['is_active'] = false;
check(call_directory()->data['pagination']['total_items'] === 1, 'inactive Card hidden');
$registered[0]['is_active'] = true;
$save = $registered;
foreach (['javascript:alert(1)', 'http://example.test', 'https://user:password@example.test', "https://example.test/\npath"] as $url) {
    $registered[0]['website_url'] = $url;
    check(call_directory() instanceof WP_Error, 'unsafe URL rejected');
}
$registered = $save;
$registered[] = $registered[0];
check(call_directory() instanceof WP_Error, 'duplicate supplemental slug rejected');
$registered = $save;
$primary[] = ['ngo_id' => 29, 'slug' => 'canonical-new', 'name' => 'Új szervezet', 'is_active' => true];
check(call_directory()->data['pagination']['total_items'] === 2, 'future canonical registration deduplicates by name');
$primary = [$primary[0], ['ngo_id' => 29, 'slug' => 'new', 'name' => 'Másik szervezet', 'is_active' => true]];
check(call_directory() instanceof WP_Error, 'canonical slug collision cannot be overwritten');
$primary = [$primary[0]];
$totalOverride = 2;
check(call_directory() instanceof WP_Error, 'partial primary source rejected');
$totalOverride = null;
$registered = 'bad';
check(call_directory() instanceof WP_Error, 'invalid option shape rejected');
$registered = $save;
$primary[0]['ngo_id'] = null;
check(call_directory() instanceof WP_Error, 'canonical ID not fabricated');
$registered = $save; $primary = [['ngo_id' => 28, 'slug' => 'existing', 'name' => 'Régi szervezet', 'is_active' => true, 'website_url' => 'http://example.test/']];
check(call_directory()->data['results'][0]['website_url'] === 'http://example.test/', 'existing canonical http URL preserved');
$registered = null;
check(call_directory() instanceof WP_Error, 'absent directory configuration is not a successful expanded source');
$registered = [];
check(call_directory()->data['pagination']['total_items'] === 1, 'explicit empty directory is valid');
$registered = $save; $approved = ['new' => ['name' => 'Új szervezet']];
$county = $hook($original, [], new WP_REST_Request(['card_directory' => '1', 'county' => 'Fejér']));
check($county instanceof WP_Error && $county->data['status'] === 400, 'unsupported county filter is rejected');
// Real base route returns200/empty results even beyond its own page count.
$overflowBase = new WP_REST_Response(['results' => [], 'pagination' => ['total_pages' => 1]], 200);
$overflow = $hook($overflowBase, [], new WP_REST_Request(['card_directory' => '1', 'page' => 2, 'per_page' => 1]));
check($overflow instanceof WP_REST_Response && $overflow->data['results'][0]['slug'] === 'new', 'combined last page reachable past native last page');
$primary = []; $registered = []; $approved = [];
check(call_directory() instanceof WP_Error, 'supplement cannot disguise an empty canonical source');
echo json_encode(['status' => 'fixture-pass', 'assertions' => $assertions, 'liveWrites' => false]) . "\n";
