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
$GLOBALS['impactshop_identity_bootstrap_block_legacy'] = true;
$source = dirname(__DIR__, 2) . '/wp-content/mu-plugins/impactshop-identity-panel.php';
require $source;
// Reuse the exact existing component CSS, without invoking registration hooks.
preg_match('/\$css = <<<CSS\n(.*?)\nCSS;/s', file_get_contents($source), $css);
echo '<style>' . ($css[1] ?? '') . '</style>';
echo impactshop_identity_panel_shortcode();
echo $action_bar;
