<?php

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_PWA_VERSION = '2026.02.17.1';

add_action('wp_head', 'impactshop_pwa_head', 1);
add_action('wp_enqueue_scripts', 'impactshop_pwa_assets');
add_action('wp_footer', 'impactshop_pwa_footer', 20);
add_filter('body_class', 'impactshop_pwa_body_class');
add_action('rest_api_init', 'impactshop_pwa_register_status');

function impactshop_pwa_is_enabled(): bool
{
    return !is_admin() && !wp_doing_ajax();
}

function impactshop_pwa_should_hide_bnav(): bool
{
    if (!impactshop_pwa_is_enabled()) {
        return true;
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/impactad-2') === 0) {
        return true;
    }
    if (strpos($uri, 'pwa_no_bnav=1') !== false) {
        return true;
    }
    return false;
}

function impactshop_pwa_body_class(array $classes): array
{
    if (!impactshop_pwa_is_enabled()) {
        return $classes;
    }
    $classes[] = 'pwa-enabled';
    if (impactshop_pwa_should_hide_bnav()) {
        $classes[] = 'pwa-no-bnav';
    } else {
        $classes[] = 'pwa-has-bnav';
    }
    return $classes;
}

function impactshop_pwa_head(): void
{
    if (!impactshop_pwa_is_enabled()) {
        return;
    }
    $manifest = esc_url('/manifest.json?v=' . rawurlencode(IMPACTSHOP_PWA_VERSION));
    echo '<meta name="theme-color" content="#3366ff">';
    echo '<meta name="apple-mobile-web-app-capable" content="yes">';
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
    echo '<meta name="apple-mobile-web-app-title" content="Sharity">';
    echo '<link rel="manifest" href="' . $manifest . '">';
    echo '<link rel="apple-touch-icon" href="/wp-content/uploads/pwa-icons/icon-180x180.png">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>'; 
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
}

function impactshop_pwa_assets(): void
{
    if (!impactshop_pwa_is_enabled()) {
        return;
    }

    wp_register_style('impactshop-pwa', false, [], IMPACTSHOP_PWA_VERSION);
    wp_enqueue_style('impactshop-pwa');

    $css = '.pwa-has-bnav{--pwa-bnav-height:64px;padding-bottom:calc(var(--pwa-bnav-height) + env(safe-area-inset-bottom,0px));}'
        . '.pwa-no-bnav{padding-bottom:0;}'
        . '.impactshop-bnav{position:fixed;left:0;right:0;bottom:0;z-index:9998;background:rgba(15,23,42,0.96);color:#fff;display:flex;justify-content:space-around;align-items:center;height:64px;padding-bottom:env(safe-area-inset-bottom,0px);padding-right:72px;backdrop-filter:blur(10px);}'
        . '.impactshop-bnav a{color:#fff;text-decoration:none;font-size:12px;font-weight:600;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:60px;}'
        . '.impactshop-bnav a span{font-size:18px;}'
        . '@media (min-width:992px){.impactshop-bnav{display:none}.pwa-has-bnav{padding-bottom:0}}'
        . '.pwa-has-bnav .ads-watch-floating-tabs{bottom:calc(60px + var(--pwa-bnav-height) + env(safe-area-inset-bottom,0px));}'
        . 'button, .impactshop-bnav a{min-height:44px;}'
        . 'html{-webkit-tap-highlight-color:rgba(0,0,0,0);}';
    wp_add_inline_style('impactshop-pwa', $css);

    wp_register_script('impactshop-pwa', '', [], IMPACTSHOP_PWA_VERSION, true);
    wp_enqueue_script('impactshop-pwa');
    $sw_url = esc_url('/sw.js?v=' . rawurlencode(IMPACTSHOP_PWA_VERSION));
    $js = "(function(){\n";
    $js .= "var swUrl='" . $sw_url . "';\n";
    $js .= "if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register(swUrl).catch(function(){});});}\n";
    $js .= "var deferredPrompt=null;var banner=null;var reopenBtn=null;\n";
    $js .= "function isIOS(){return /iphone|ipad|ipod/i.test(navigator.userAgent);}\n";
    $js .= "function isSafari(){var ua=navigator.userAgent;var isSafari=/safari/i.test(ua);var isChrome=/crios|chrome|edg/i.test(ua);return isSafari && !isChrome;}\n";
    $js .= "function isFirefox(){return /firefox/i.test(navigator.userAgent);}\n";
    $js .= "function isStandalone(){return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;}\n";
    $js .= "function getBanner(){if(!banner){banner=document.getElementById('impactshop-pwa-install');}return banner;}\n";
    $js .= "function getReopen(){if(!reopenBtn){reopenBtn=document.getElementById('impactshop-pwa-reopen');}return reopenBtn;}\n";
    $js .= "function showBanner(){if(isStandalone()){return;}var el=getBanner();if(!el){return;}el.hidden=false;var reopen=getReopen();if(reopen){reopen.hidden=true;}}\n";
    $js .= "function hideBanner(){var el=getBanner();if(!el){return;}el.hidden=true;}\n";
    $js .= "function showReopen(){if(isStandalone()){return;}var reopen=getReopen();if(reopen){reopen.hidden=false;}}\n";
    $js .= "function applyBannerMode(){var el=getBanner();if(!el){return;}var instructions=el.querySelector('[data-role=pwa-instructions]');var installBtn=el.querySelector('[data-role=pwa-install]');if(!instructions){return;}if(isIOS()){instructions.textContent='iOS: Megosztas -> Fokepernyohoz adas.';if(installBtn){installBtn.style.display='none';}return;}if(isSafari()){instructions.textContent='Safari (macOS): Fajl -> Hozzaadas a Dockhoz.';if(installBtn){installBtn.style.display='none';}return;}if(isFirefox()){instructions.textContent='Firefox: Menu -> Telepites / Fokepernyohoz adas.';if(installBtn){installBtn.style.display='none';}}}\n";
    $js .= "window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferredPrompt=e;try{if(localStorage.getItem('pwa_install_dismissed')==='1'){return;}}catch(err){}showBanner();});\n";
    $js .= "document.addEventListener('click',function(e){var target=e.target;if(!target){return;}var action=target.closest?target.closest('[data-role]'):target;var role=action&&action.getAttribute?action.getAttribute('data-role'):'';if(role==='pwa-install'){e.preventDefault();if(!deferredPrompt){return;}deferredPrompt.prompt();deferredPrompt.userChoice.finally(function(){deferredPrompt=null;hideBanner();});}if(role==='pwa-dismiss'){e.preventDefault();try{localStorage.setItem('pwa_install_dismissed','1');}catch(err){}hideBanner();showReopen();}if(role==='pwa-reopen'){e.preventDefault();try{localStorage.removeItem('pwa_install_dismissed');}catch(err){}showBanner();}});\n";
    $js .= "window.addEventListener('load',function(){applyBannerMode();if(isStandalone()){hideBanner();var reopen=getReopen();if(reopen){reopen.hidden=true;}return;}var dismissed=false;try{dismissed=localStorage.getItem('pwa_install_dismissed')==='1';}catch(err){}if(dismissed){showReopen();return;}if(isIOS()||isSafari()||isFirefox()){showBanner();}});\n";
    $js .= "})();";
    wp_add_inline_script('impactshop-pwa', $js);
}

function impactshop_pwa_footer(): void
{
    if (!impactshop_pwa_is_enabled()) {
        return;
    }
    $install = '<div id="impactshop-pwa-install" class="impactshop-pwa-install" hidden>'
        . '<div class="impactshop-pwa-install__card">'
        . '<strong>Telepitsd a Sharityt</strong>'
        . '<p>Gyorsabb inditas, offline fallback, app-szeru elmeny.</p>'
        . '<p data-role="pwa-instructions">Telepites a bongeszobol.</p>'
        . '<div class="impactshop-pwa-install__actions">'
        . '<a href="#" data-role="pwa-install">Telepites</a>'
        . '<a href="#" data-role="pwa-dismiss">Kesobb</a>'
        . '</div>'
        . '</div></div>';

    $reopen = '<button id="impactshop-pwa-reopen" class="impactshop-pwa-reopen" data-role="pwa-reopen" hidden>Telepites</button>';

    $install_css = '<style>'
        . '.impactshop-pwa-install[hidden]{display:none !important;}'
        . '.impactshop-pwa-reopen[hidden]{display:none !important;}'
        . '.impactshop-pwa-install{position:fixed;left:0;right:0;bottom:calc(64px + env(safe-area-inset-bottom,0px));padding:12px;display:flex;justify-content:center;z-index:9999;}'
        . '.impactshop-pwa-install__card{max-width:420px;width:100%;background:#0f172a;color:#fff;border-radius:16px;padding:14px 16px;box-shadow:0 12px 30px rgba(0,0,0,0.3);}'
        . '.impactshop-pwa-install__card p{margin:6px 0 12px;color:#cbd5f5;font-size:12px;}'
        . '.impactshop-pwa-install__actions{display:flex;gap:12px;}'
        . '.impactshop-pwa-install__actions a{color:#fff;font-weight:600;text-decoration:none;font-size:12px;}'
        . '.impactshop-pwa-reopen{position:fixed;left:16px;bottom:calc(120px + env(safe-area-inset-bottom,0px));background:#0f172a;color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:999px;padding:8px 12px;font-size:12px;font-weight:600;z-index:9999;cursor:pointer;box-shadow:0 10px 24px rgba(15,23,42,0.3);} '
        . '.pwa-no-bnav .impactshop-pwa-reopen{bottom:20px;}'
        . '@media (min-width:992px){.impactshop-pwa-install{bottom:20px;}.impactshop-pwa-reopen{bottom:24px;}}'
        . '</style>';

    echo $install_css;
    echo $install;
    echo $reopen;

    if (!impactshop_pwa_should_hide_bnav()) {
        $home = esc_url(site_url('/'));
        $activity = esc_url(site_url('/impactad-2/'));
        $shop = esc_url(site_url('/impactshop/'));
        echo '<nav class="impactshop-bnav" aria-label="Primary">'
            . '<a href="' . $home . '"><span>🏠</span>Kezdo</a>'
            . '<a href="' . $activity . '"><span>🎬</span>Aktivitas</a>'
            . '<a href="' . $shop . '"><span>🛍️</span>Shop</a>'
            . '</nav>';
    }
}

function impactshop_pwa_register_status(): void
{
    register_rest_route('impact/v1', '/pwa-status', [
        'methods' => 'GET',
        'callback' => function () {
            return new WP_REST_Response([
                'status' => 'OK',
                'version' => IMPACTSHOP_PWA_VERSION,
                'sw_url' => '/sw.js?v=' . IMPACTSHOP_PWA_VERSION,
                'manifest' => '/manifest.json?v=' . IMPACTSHOP_PWA_VERSION,
                'offline' => '/offline.html',
                'timestamp' => gmdate('c'),
            ], 200);
        },
        'permission_callback' => '__return_true',
    ]);
}
