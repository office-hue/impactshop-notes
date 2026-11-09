<?php
// CLI-only: real shortcode markup; no WP hooks, cookies, database or network.
declare(strict_types=1);
ob_start();
require __DIR__ . '/impactshop-action-bar-render.php';
$action_bar = ob_get_clean();
function add_filter(...$args): void {}
function add_shortcode(...$args): void {}
function wp_enqueue_style(...$args): void {}
function wp_enqueue_script(...$args): void {}
function esc_url_raw(string $value): string { return $value; }
function rest_url(string $path): string { return 'https://app.sharity.hu/wp-json/' . $path; }
function wp_generate_password(...$args): string { return 'fixture'; }
function is_ssl(): bool { return true; }
function sanitize_text_field(string $value): string { return $value; }
function wp_unslash(string $value): string { return $value; }
function wp_kses_bad_protocol(string $value, array $protocols): string { return $value; }
function get_option($key, $default = false) { return $default; }
function wp_kses($value, $allowed) { return $value; }
function apply_filters($hook, $value) { return $value; }
function site_url($path = '') { return home_url($path); }
function wp_parse_url($url, $part = -1) { return parse_url($url, $part); }
function untrailingslashit($value) { return rtrim($value, '/'); }
$GLOBALS['impactshop_identity_bootstrap_block_legacy'] = true;
$source = dirname(__DIR__, 2) . '/wp-content/mu-plugins/impactshop-identity-panel.php';
require $source;
// Reuse the exact existing component CSS, without invoking registration hooks.
preg_match('/\$css = <<<CSS\n(.*?)\nCSS;/s', file_get_contents($source), $css);
echo '<style>' . ($css[1] ?? '') . '</style>';
echo getenv('PROFILE_COPY_COMPACT') === '1' ? impactshop_identity_id_shortcode() : impactshop_identity_panel_shortcode();
echo $action_bar;
