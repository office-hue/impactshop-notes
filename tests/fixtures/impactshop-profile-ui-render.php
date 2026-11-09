<?php
// Actual profile + actionbar + PWA output, no WordPress database or external requests.
declare(strict_types=1);
require __DIR__ . '/impactshop-profile-copy-render.php';
function wp_register_style(...$args): void {}
function wp_register_script(...$args): void {}
function wp_add_inline_style($handle, $css): void { echo '<style>' . $css . '</style>'; }
function wp_add_inline_script($handle, $js): void { echo '<script>' . $js . '</script>'; }
require dirname(__DIR__, 2) . '/wp-content/mu-plugins/impactshop-pwa.php';
impactshop_pwa_footer();
impactshop_pwa_assets();
require dirname(__DIR__, 2) . '/wp-content/mu-plugins/impactshop-profile-ui.php';
if (getenv('PROFILE_UI_DISABLE') !== '1') impactshop_profile_ui_render();
