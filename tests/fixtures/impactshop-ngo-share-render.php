<?php
// CLI-only WP rendering fixture; no WordPress database or network.
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$url = $argv[1] ?? 'https://app.sharity.hu/profil/';
$parts = parse_url($url);
$_SERVER['HTTP_HOST'] = $parts['host'] ?? '';
$_SERVER['REQUEST_URI'] = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
parse_str($parts['query'] ?? '', $_GET);
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function wp_doing_cron(): bool { return false; }
function home_url(string $path = ''): string { return 'https://app.sharity.hu' . $path; }
function add_action(...$args): void {}
function sanitize_key(string $value): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr(string $value): string { return esc_html($value); }
function esc_url(string $value): string { return esc_html($value); }
function wp_kses_post(string $value): string { return $value; }
function add_query_arg($key, $value, ?string $url = null): string {
    if (is_array($key)) { foreach ($key as $k => $v) { $value = add_query_arg($k, $v, $value); } return $value; }
    $url ??= $_SERVER['REQUEST_URI'];
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);
    if ($value === false) { unset($query[$key]); } else { $query[$key] = $value; }
    $prefix = isset($parts['host']) ? ($parts['scheme'] ?? 'https') . '://' . $parts['host'] : '';
    return $prefix . ($parts['path'] ?? '/') . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}
function remove_query_arg(string $key, ?string $url = null): string { return add_query_arg($key, false, $url); }

// Render the actual private share template. Only WP infrastructure is stubbed.
set_error_handler(static function($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('WPMU_PLUGIN_URL', 'https://app.sharity.hu/wp-content/mu-plugins');
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function apply_filters($hook, $value, ...$args) { return $value; }
function trailingslashit($s) { return rtrim($s, '/') . '/'; }
function sanitize_title($s) { return sanitize_key($s); }
function __($s, ...$args) { return $s; }
function esc_html_e($s, ...$args) { echo esc_html($s); }
function esc_textarea($s) { return esc_html($s); }
function esc_url_raw($s) { return $s; }
function esc_js($s) { return addslashes($s); }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function wp_strip_all_tags($s) { return strip_tags($s); }
function nocache_headers() {}
$_COOKIE = json_decode($argv[3] ?? '{}', true);
if (($argv[4] ?? '') !== 'no-bar') require dirname(__DIR__, 2) . '/wp-content/mu-plugins/impactshop-action-bar.php';
require dirname(__DIR__, 2) . '/wp-content/mu-plugins/impactshop-ngo-card.php';
$item = ['slug'=>'fixture-ngo', 'name'=>$argv[2] ?? 'Fixture NGO', 'amount'=>['formatted'=>'12 345 Ft'], 'rank'=>3, 'badge_status'=>['label'=>'Original badge', 'key'=>'base']];
(new ReflectionMethod(ImpactShop_NGO_Card_API::class, 'render_share_template'))->invoke(null, $item);
