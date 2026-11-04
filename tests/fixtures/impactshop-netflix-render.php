<?php
// Synthetic WP fixture; actual shortcode source, no real credentials/network/DB.
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
foreach (['DOGNET_LOGIN_EMAIL','DOGNET_LOGIN_PASSWORD','DOGNET_API_TOKEN'] as $key) { putenv($key . '=fixture-only'); }
$root = $argv[1];
$scenario = $argv[2] ?? 'normal';
$_GET = ['d1'=>'fixture-ngo','amb'=>'a b','src'=>'fixture'];
$shortcodes = []; $actions = []; $transients = []; $requests = [];
function add_action($hook, $callback, $priority = 10, ...$args) { $GLOBALS['actions'][$hook][$priority][] = $callback; }
function add_filter(...$args) {}
function add_shortcode($tag, $callback) { $GLOBALS['shortcodes'][$tag] = $callback; }
function shortcode_atts($defaults, $atts, ...$args) { return array_merge($defaults, (array)$atts); }
function is_admin() { return false; }
function is_front_page() { return false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function home_url($path = '') { return 'https://app.sharity.hu'.$path; }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function wp_kses_post($value) { return $value; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_parse_url($value) { return parse_url($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function sanitize_text_field($value) { return strip_tags($value); }
function sanitize_title($value) { return strtolower(str_replace(' ','-', $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/','',strtolower($value)); }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function get_option($key, $default = false) {
    if ($GLOBALS['scenario'] === 'fallback' && $key === 'impactshop_cj_coupon_links') return [['advertiser_id'=>'42','advertiser_name'=>'Kupon','link_name'=>'Akció','coupon_code'=>'COUPON','logo_url'=>'https://image.invalid/logo.svg']];
    return $default;
}
function isb_cj_pick_link($id) { return true; }
function is_wp_error($value) { return false; }
function impactshop_q($key) { return $_GET[$key] ?? ''; }
function impactshop_settings() { return ['fillout_url'=>'https://example.invalid/form?return=a%20b%2f~']; }
function impactshop_get_shops() {
    if ($GLOBALS['scenario'] === 'empty') return [];
    return [
        ['shop_slug'=>'fixture-one','name'=>'Összes','logo'=>'','category'=>'Egyéb','dognet_base'=>'https://merchant.invalid/?cid=1','is_new'=>'1'],
        ['shop_slug'=>'fixture-two','name'=>'Másolás','logo'=>'','category'=>'Divat','dognet_base'=>'https://merchant.invalid/?cid=2'],
    ];
}
function impactshop_get_banners() { return [['category'=>'Egyéb']]; }
function do_shortcode($input) { return json_encode(fixture_deals()); }
function fixture_deals() {
    if ($GLOBALS['scenario'] === 'empty') return [];
    return [['shop_slug'=>'fixture-one','shop_name'=>'AKCIÓ','title'=>'Lejár: eredeti termék 123 Ft','price'=>'1 234 Ft','old_price'=>'2 000 Ft','image'=>'https://image.invalid/test.png','img'=>'https://image.invalid/test.png','url'=>'https://app.sharity.hu/go-deal/fixture-one?u=a%2Fb&x=1&x=2#keep','href'=>'https://app.sharity.hu/go-deal/fixture-one?u=a%2Fb&x=1&x=2#keep','percent'=>0]];
}
if ($scenario !== 'unavailable') { function sib_load_banners() { return fixture_deals(); } }
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = ['method'=>'GET','url'=>$url];
    return ['body'=>json_encode(['rows'=>fixture_deals()]),'response'=>['code'=>200]];
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function dognet_api_request($method, $url, $body = []) {
    $GLOBALS['requests'][] = ['method'=>$method,'url'=>$url,'body'=>$body];
    if ($GLOBALS['scenario'] === 'empty') return ['data'=>[]];
    if ($GLOBALS['scenario'] === 'fallback') return ['data'=>[['campaign_id'=>1,'code'=>'DEAL']]];
    return ['data'=>array_map(fn($expiry)=>['campaign_id'=>1,'code'=>'Másolás','title'=>'Lejárt','valid_to'=>$expiry], ['',gmdate('Y-m-d\TH:i:s\Z',time()+3*86400),gmdate('Y-m-d\TH:i:s\Z',time()+120),'2099-01-01','invalid-time','2000-01-01'])];
}
function add_query_arg($args, $url = null, $extra = null) {
    if (!is_array($args)) { $args = [$args=>$url]; $url = $extra ?? '/impactshop/'; }
    return $url.(str_contains($url,'?')?'&':'?').http_build_query($args,'','&',PHP_QUERY_RFC3986);
}
function remove_query_arg($key, $url = null) { return $url ?? '/impactshop/'; }
require $argv[3] ?? $root.'/wp-content/mu-plugins/impactshop-netflix-shortcodes.php';
ob_start();
foreach (['impactshop_netflix','impact_deals_netflix','impact_coupons_netflix'] as $tag) {
    $params=['autoplay'=>'0'];
    if ($scenario === 'custom') $params['categories']='Egyéb';
    $html = $shortcodes[$tag]($params);
    if ($scenario === 'cached') {
        $again = $shortcodes[$tag]($params);
        if ($again !== $html) throw new RuntimeException('cache did not preserve markup');
        $html = $again;
    }
    echo $html;
}
if ($scenario !== 'late') {
    require $root.'/wp-content/mu-plugins/impactshop-action-bar.php';
    impactshop_action_bar_render();
}
if (function_exists('impactshop_netflix_locale_runtime')) impactshop_netflix_locale_runtime();
$body=ob_get_clean();
echo '<!doctype html><html lang="hu"><body>'.$body.'<script>window.fixtureRequests='.json_encode($requests).';</script></body></html>';
