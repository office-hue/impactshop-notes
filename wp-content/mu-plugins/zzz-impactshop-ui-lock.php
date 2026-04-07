<?php
/**
 * Plugin Name: ImpactShop UI Lock Guard
 * Description: Stabilitási guard az Impact Challenge UI-hoz (régi floating tabs tiltása + fallback action bar).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function sharity_ui_lock_should_render(): bool {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return false;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_starts_with($uri, '/wp-login.php') || str_starts_with($uri, '/wp-json/')) {
        return false;
    }
    return true;
}

add_action('wp_head', function () {
    if (!sharity_ui_lock_should_render()) {
        return;
    }
    echo '<style>.ads-watch-floating-tabs{display:none!important;}</style>';
}, 9999);

add_action('wp_footer', function () {
    if (!sharity_ui_lock_should_render()) {
        return;
    }
    if (function_exists('impactshop_action_bar_render')) {
        return; // primary action bar exists
    }

    $base = esc_url(home_url('/impact-challenge/'));
    $shop = esc_url(home_url('/impactshop/'));

    echo '<style>'
       . '.sharity-action-bar-fallback{position:fixed;left:50%;transform:translateX(-50%);bottom:10px;z-index:10030;width:min(980px,calc(100vw - 18px));display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;background:rgba(15,23,42,.96);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:6px}'
       . '.sharity-action-bar-fallback a{display:flex;align-items:center;justify-content:center;gap:6px;color:#fff;text-decoration:none;font-weight:700;font-size:13px;background:rgba(30,64,175,.45);padding:10px 8px;border-radius:10px}'
       . '@media(max-width:768px){.sharity-action-bar-fallback{grid-template-columns:repeat(4,minmax(0,1fr));width:calc(100vw - 10px);bottom:6px}.sharity-action-bar-fallback a{font-size:12px;padding:10px 6px}}'
       . 'body{padding-bottom:86px}'
       . '</style>';

    echo '<nav class="sharity-action-bar-fallback" aria-label="UI lock fallback">'
       . '<a href="' . $base . '#ads-watch-video">🎬 Videó</a>'
       . '<a href="' . $base . '#impactshop-offerwall">🎁 Feladatok</a>'
       . '<a href="' . $shop . '">🛍️ Impact Shop</a>'
       . '<a href="' . $base . '#impactshop-ads-watch">📊 Pontok</a>'
       . '</nav>';
}, 9999);
