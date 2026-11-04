<?php
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$root = $argv[1]; $scenario = $argv[2] ?? 'normal';
$shortcodes = []; $requests = []; $transients = [];
function add_action(...$args) {}
function add_filter(...$args) {}
function add_shortcode($tag, $callback) { $GLOBALS['shortcodes'][$tag] = $callback; }
function remove_shortcode($tag) { unset($GLOBALS['shortcodes'][$tag]); }
function shortcode_atts($defaults, $atts, ...$rest) { return array_merge($defaults, (array)$atts); }
function current_time($format) { return gmdate($format); }
function home_url($path = '') { return 'https://app.sharity.hu'.$path; }
function add_query_arg($args, $url = null, $extra = null) {
    if (!is_array($args)) { $args=[$args=>$url]; $url=$extra ?? '/impactshop/'; }
    return $url.(str_contains($url,'?')?'&':'?').http_build_query($args);
}
function remove_query_arg($key, $url = null) { return $url ?? '/impactshop/'; }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_js($value) { return $value; }
function wp_kses_post($value) { return $value; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function sanitize_text_field($value) { return strip_tags($value); }
function wp_parse_url($value) { return parse_url($value); }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function is_front_page() { return false; }
function is_wp_error($value) { return false; }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, $ttl) { $GLOBALS['transients'][$key] = $value; }
function get_option($key, $default = false) { return $key === 'sharity_support_fx_quotes_v1' ? $GLOBALS['fxSaved'] : $default; }
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = ['method'=>'GET','url'=>$url,'args'=>$args];
    $scenario=$GLOBALS['scenario'];
    $value = $scenario === 'invalid' ? 'invalid' : ($scenario === 'zero' ? 0 : ($scenario === 'negative' ? -100 : 100));
    return ['body'=>json_encode(['rows'=>[['ngo'=>'fixture-ngo','shop'=>'fixture-shop','commission'=>$value],['ngo'=>'unknown','commission'=>20]],'meta'=>['grand'=>['commission'=>120]]]),'status'=>$scenario === 'error' ? 500 : 200];
}
function wp_remote_retrieve_response_code($value) { return $value['status']; }
function wp_remote_retrieve_body($value) { return $value['body']; }

$date = (new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');
$fxSaved = ['fetched_at'=>gmdate('Y-m-d\TH:i:s\Z'), 'quotes'=>[]];
foreach (['EUR'=>'400.00','USD'=>'320.00','GBP'=>'500.00'] as $currency=>$rate) {
    $fxSaved['quotes'][$currency]=['success'=>true,'currency'=>$currency,'rate'=>$rate,'unit'=>'1','date'=>$date];
}
if ($scenario === 'unit') foreach ($fxSaved['quotes'] as &$q) { $q['rate']=(string)((int)$q['rate']*100); $q['unit']='100'; } unset($q);
if ($scenario === 'stale') $fxSaved['fetched_at']=gmdate('Y-m-d\TH:i:s\Z',time()-37*3600);
if ($scenario === 'last-quote-day') foreach ($fxSaved['quotes'] as &$q) $q['date']=(new DateTimeImmutable($date))->modify('-7 days')->format('Y-m-d'); unset($q);
if ($scenario === 'old-quote') foreach ($fxSaved['quotes'] as &$q) $q['date']=gmdate('Y-m-d',time()-9*86400); unset($q);
if ($scenario === 'incomplete') unset($fxSaved['quotes']['GBP']);
if ($scenario === 'missing') $fxSaved=false;
if ($scenario === 'bad-rate') $fxSaved['quotes']['EUR']['rate']='400,00';
if ($scenario !== 'no-fx-module') {
    require $root.'/wp-content/mu-plugins/sharity-points-money.php';
    require $root.'/wp-content/mu-plugins/sharity-support-finance-read.php';
}
require $argv[3] ?? $root.'/wp-content/mu-plugins/impactshop-sum-pack.php';
impactshop_sum_register_shortcodes();
ob_start();
$params=['currency'=>'HUF','rate_huf'=>'999','refresh'=>'0'];
if ($scenario === 'custom') $params['label']='Vásárlásból gyűjtött adományok összege';
echo $shortcodes['impact_sum_mini']($params);
echo $shortcodes['impact_sum_sticky']($params+['cta_text'=>'Adomány 123 Ft','cta_href'=>'https://merchant.invalid/?a=1&a=2&x=%2F#keep']);
if ($scenario === 'diag') echo $shortcodes['impact_sum_diag']($params);
if ($scenario !== 'late') { require $root.'/wp-content/mu-plugins/impactshop-action-bar.php'; impactshop_action_bar_render(); }
if (function_exists('impactshop_sum_display_runtime')) impactshop_sum_display_runtime();
$body=ob_get_clean();
echo '<!doctype html><html><body style="background:#444;margin:8px">'.$body.'<script>window.fixtureRequests='.json_encode($requests).';</script></body></html>';
