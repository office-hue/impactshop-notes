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
function add_query_arg(string $key, $value, ?string $url = null): string {
    $url ??= $_SERVER['REQUEST_URI'];
    $parts = parse_url($url);
    parse_str($parts['query'] ?? '', $query);
    if ($value === false) { unset($query[$key]); } else { $query[$key] = $value; }
    $prefix = isset($parts['host']) ? ($parts['scheme'] ?? 'https') . '://' . $parts['host'] : '';
    return $prefix . ($parts['path'] ?? '/') . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}
function remove_query_arg(string $key, ?string $url = null): string { return add_query_arg($key, false, $url); }
require $argv[2] ?? dirname(__DIR__, 2) . '/wp-content/mu-plugins/impactshop-action-bar.php';
impactshop_action_bar_render();
