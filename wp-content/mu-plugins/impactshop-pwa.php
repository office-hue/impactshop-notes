<?php

if (!defined('ABSPATH')) {
    exit;
}

const IMPACTSHOP_PWA_VERSION = '2026.03.31.5';

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
    if ($uri === '/' || $uri === '') {
        return true;
    }
    if (strpos($uri, '/impactad-2') === 0) {
        return true;
    }
    if (strpos($uri, '/impact-challenge') === 0) {
        return true;
    }
    if (strpos($uri, '/impactshop') === 0) {
        return true;
    }
    if (strpos($uri, '/profil') === 0) {
        return true;
    }
    if (strpos($uri, 'pwa_no_bnav=1') !== false) {
        return true;
    }
    return false;
}

function impactshop_pwa_should_hide_install_ui(): bool
{
    return !impactshop_pwa_is_enabled();
}

function impactshop_pwa_should_disable_sw(): bool
{
    if (!impactshop_pwa_is_enabled()) {
        return true;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if (strpos($uri, '/impact-challenge') === 0) {
        return true;
    }
    if (strpos($uri, '/impactad-2') === 0) {
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
    $should_disable_sw = impactshop_pwa_should_disable_sw();
    $js = "(function(){\n";
    $js .= "var swUrl='" . $sw_url . "';\n";
    if ($should_disable_sw) {
        $js .= "if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.getRegistrations().then(function(registrations){return Promise.all((registrations||[]).map(function(reg){return reg.unregister();}));}).catch(function(){});if('caches' in window){caches.keys().then(function(keys){return Promise.all((keys||[]).filter(function(key){var value=String(key||'');return value.indexOf('pwa-static-')===0 || value.indexOf('workbox-')===0;}).map(function(key){return caches.delete(key);}));}).catch(function(){});}});}\n";
    } else {
        $js .= "if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register(swUrl).catch(function(){});});}\n";
    }
    $js .= "var deferredPrompt=null;var banner=null;var reopenBtn=null;var mobileUtility=null;var mobileInstallBtn=null;var mobileA11yBtn=null;\n";
    $js .= "function isIOS(){return /iphone|ipad|ipod/i.test(navigator.userAgent);}\n";
    $js .= "function isSafari(){var ua=navigator.userAgent;var isSafari=/safari/i.test(ua);var isChrome=/crios|chrome|edg/i.test(ua);return isSafari && !isChrome;}\n";
    $js .= "function isFirefox(){return /firefox/i.test(navigator.userAgent);}\n";
    $js .= "function isStandalone(){return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;}\n";
    $js .= "function isMobileViewport(){return window.matchMedia && window.matchMedia('(max-width: 768px)').matches;}\n";
    $js .= "function getBanner(){if(!banner){banner=document.getElementById('impactshop-pwa-install');}return banner;}\n";
    $js .= "function getReopen(){if(!reopenBtn){reopenBtn=document.getElementById('impactshop-pwa-reopen');}return reopenBtn;}\n";
    $js .= "function getMobileUtility(){if(!mobileUtility){mobileUtility=document.getElementById('impactshop-mobile-utility');}return mobileUtility;}\n";
    $js .= "function getMobileInstall(){if(!mobileInstallBtn){mobileInstallBtn=document.querySelector('[data-role=\"pwa-mobile-install\"]');}return mobileInstallBtn;}\n";
    $js .= "function getMobileA11y(){if(!mobileA11yBtn){mobileA11yBtn=document.querySelector('[data-role=\"pwa-mobile-a11y\"]');}return mobileA11yBtn;}\n";
    $js .= "function showBanner(){if(isStandalone()){return;}var el=getBanner();if(!el){return;}el.hidden=false;var reopen=getReopen();if(reopen){reopen.hidden=true;}}\n";
    $js .= "function hideBanner(){var el=getBanner();if(!el){return;}el.hidden=true;}\n";
    $js .= "function showReopen(){if(isStandalone()){return;}var reopen=getReopen();if(reopen){reopen.hidden=false;}}\n";
    $js .= "function syncMobileUtilityToggle(){var utility=getMobileUtility();if(!utility){return;}var toggle=utility.querySelector('[data-role=\"pwa-mobile-toggle\"]');if(!toggle){return;}var collapsed=utility.dataset.state==='collapsed';toggle.textContent=collapsed?'›':'‹';toggle.setAttribute('aria-label',collapsed?'Eszközpanel megnyitása':'Eszközpanel elrejtése');}\n";
    $js .= "function setMobileUtilityCollapsed(collapsed){var utility=getMobileUtility();if(!utility){return;}utility.dataset.state=collapsed?'collapsed':'open';syncMobileUtilityToggle();try{localStorage.setItem('impactshop_pwa_mobile_utility_collapsed',collapsed?'1':'0');}catch(err){}}\n";
    $js .= "function syncMobileUtilityState(){var utility=getMobileUtility();if(!utility){return;}var collapsed=false;try{collapsed=localStorage.getItem('impactshop_pwa_mobile_utility_collapsed')==='1';}catch(err){}utility.hidden=isStandalone() || !isMobileViewport();utility.dataset.state=collapsed?'collapsed':'open';syncMobileUtilityToggle();}\n";
    $js .= "function getInstallGuide(){if(isIOS()){return 'iOS: Megosztás → Hozzáadás a Főképernyőhöz.';}if(isSafari()){return 'Safari (macOS): Fájl → Hozzáadás a Dockhoz.';}if(isFirefox()){return 'Firefox: Menü → Telepítés / Főképernyőhöz adás.';}if(deferredPrompt){return 'Telepítés';}return 'Használd a böngésző megosztás vagy telepítés menüjét.';}\n";
    $js .= "function applyBannerMode(){var installBtn=getBanner();var reopen=getReopen();var mobileInstall=getMobileInstall();var mobileA11y=getMobileA11y();var guide=getInstallGuide();if(installBtn){installBtn.title=guide;installBtn.setAttribute('aria-label',guide);}if(reopen){reopen.title=guide;reopen.setAttribute('aria-label',guide);}if(mobileInstall){mobileInstall.title=guide;mobileInstall.setAttribute('aria-label',guide);}if(mobileA11y){mobileA11y.title='Akadálymentesítés';mobileA11y.setAttribute('aria-label','Akadálymentesítés');}}\n";
    $js .= "function triggerInstall(){applyBannerMode();if(deferredPrompt){deferredPrompt.prompt();deferredPrompt.userChoice.finally(function(){deferredPrompt=null;applyBannerMode();});return;}window.alert(getInstallGuide());}\n";
    $js .= "function triggerElementorA11yAction(){try{if(window.elementorFrontend&&window.elementorFrontend.utils&&window.elementorFrontend.utils.urlActions&&typeof window.elementorFrontend.utils.urlActions.runAction==='function'){window.elementorFrontend.utils.urlActions.runAction('allyWidget:open');return true;}}catch(err){}return false;}\n";
    $js .= "function clickNativeA11yLauncher(){var selectors=['button[aria-label=\"Show Accessibility Preferences\"]','button[title=\"Show Accessibility Preferences\"]','[aria-label=\"Show Accessibility Preferences\"]'];for(var i=0;i<selectors.length;i+=1){var node=document.querySelector(selectors[i]);if(node&&typeof node.click==='function'){try{node.click();return true;}catch(err){}}}return false;}\n";
    $js .= "function toggleA11yWidget(attempt){var tries=typeof attempt==='number'?attempt:0;if(clickNativeA11yLauncher()){return true;}if(window.ea11yWidget&&window.ea11yWidget.widget&&typeof window.ea11yWidget.widget.open==='function'){try{return window.ea11yWidget.widget.isOpen()?window.ea11yWidget.widget.close():window.ea11yWidget.widget.open();}catch(err){}}try{var actions=window.elementorFrontend&&window.elementorFrontend.utils&&window.elementorFrontend.utils.urlActions&&window.elementorFrontend.utils.urlActions.actions;if(actions&&typeof actions['allyWidget:open']==='function'){actions['allyWidget:open']();return true;}}catch(err){}if(triggerElementorA11yAction()){return true;}if(tries<30){window.setTimeout(function(){toggleA11yWidget(tries+1);},150);return false;}window.alert('Az akadálymentesítés most nem tudott megnyílni. Frissítés után próbáld újra.');return false;}\n";
    $js .= "function hideNativeA11yLauncher(){if(!isMobileViewport()){return;}var selectors=['button[aria-label=\"Show Accessibility Preferences\"]','[aria-label=\"Show Accessibility Preferences\"]','iframe[src*=\"skynettechnologies\"]','[id*=\"skynettechnologies\"]','[class*=\"skynettechnologies\"]'];selectors.forEach(function(selector){document.querySelectorAll(selector).forEach(function(node){if(node&&node.id!=='impactshop-mobile-utility'){var rect=node.getBoundingClientRect?node.getBoundingClientRect():null;var style=window.getComputedStyle(node);if(selector.indexOf('Show Accessibility Preferences')!==-1 || (style.position==='fixed'&&rect&&rect.width<=120&&rect.height<=120)){node.style.position='fixed';node.style.left='-9999px';node.style.top='auto';node.style.opacity='0';node.style.pointerEvents='none';}}});});}\n";
    $js .= "window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferredPrompt=e;applyBannerMode();syncMobileUtilityState();try{if(localStorage.getItem('pwa_install_dismissed')==='1'&&!isMobileViewport()){return;}}catch(err){}if(!isMobileViewport()){showBanner();}});\n";
    $js .= "document.addEventListener('click',function(e){var target=e.target;if(!target){return;}var action=target.closest?target.closest('[data-role]'):target;var role=action&&action.getAttribute?action.getAttribute('data-role'):'';if(role==='pwa-install' || role==='pwa-reopen' || role==='pwa-mobile-install'){e.preventDefault();triggerInstall();return;}if(role==='pwa-mobile-a11y'){e.preventDefault();toggleA11yWidget();return;}if(role==='pwa-mobile-toggle'){e.preventDefault();var utility=getMobileUtility();if(!utility){return;}setMobileUtilityCollapsed(utility.dataset.state!=='collapsed');return;}if(role==='pwa-dismiss'){e.preventDefault();try{localStorage.setItem('pwa_install_dismissed','1');}catch(err){}hideBanner();showReopen();}});\n";
    $js .= "window.addEventListener('load',function(){applyBannerMode();syncMobileUtilityState();hideNativeA11yLauncher();if(isStandalone()){hideBanner();var reopen=getReopen();if(reopen){reopen.hidden=true;}var utility=getMobileUtility();if(utility){utility.hidden=true;}return;}var dismissed=false;try{dismissed=localStorage.getItem('pwa_install_dismissed')==='1';}catch(err){}if(isMobileViewport()){hideBanner();var reopenButton=getReopen();if(reopenButton){reopenButton.hidden=true;}syncMobileUtilityState();return;}if(dismissed){showReopen();return;}showBanner();});\n";
    $js .= "window.addEventListener('resize',function(){syncMobileUtilityState();hideNativeA11yLauncher();});\n";
    $js .= "window.setTimeout(hideNativeA11yLauncher,800);\n";
    $js .= "window.setTimeout(hideNativeA11yLauncher,2000);\n";
    $js .= "})();";
    wp_add_inline_script('impactshop-pwa', $js);
}

function impactshop_pwa_footer(): void
{
    if (!impactshop_pwa_is_enabled()) {
        return;
    }
    if (!impactshop_pwa_should_hide_install_ui()) {
        $install = '<a id="impactshop-pwa-install" class="impactshop-pwa-install" data-role="pwa-install" href="#" hidden aria-label="Sharity telepítése" title="Sharity telepítése">⬇</a>';

        $reopen = '<button id="impactshop-pwa-reopen" class="impactshop-pwa-reopen" data-role="pwa-reopen" hidden aria-label="Telepítés megnyitása">⬇</button>';

        $mobile_utility = '<div id="impactshop-mobile-utility" class="impactshop-mobile-utility" data-state="open" hidden>'
            . '<button type="button" class="impactshop-mobile-utility__toggle" data-role="pwa-mobile-toggle" aria-label="Eszközpanel elrejtése vagy megnyitása">‹</button>'
            . '<div class="impactshop-mobile-utility__panel">'
            . '<button type="button" class="impactshop-mobile-utility__button" data-role="pwa-mobile-install" aria-label="Sharity telepítése">⬇</button>'
            . '<button type="button" class="impactshop-mobile-utility__button" data-role="pwa-mobile-a11y" aria-label="Akadálymentesítés">♿</button>'
            . '</div>'
            . '</div>';

        $install_css = '<style>'
            . '.impactshop-pwa-install[hidden]{display:none !important;}'
            . '.impactshop-pwa-reopen[hidden]{display:none !important;}'
            . '.impactshop-pwa-install,.impactshop-pwa-reopen{position:fixed;left:16px;bottom:calc(var(--sharity-action-bar-height, 116px) + env(safe-area-inset-bottom,0px) + 24px);z-index:2147483646;display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:999px;background:#2563eb;color:#fff;text-decoration:none;font-size:22px;font-weight:800;border:0;cursor:pointer;box-shadow:0 10px 24px rgba(37,99,235,0.35);}'
            . 'body.sharity-consent-blocking .impactshop-pwa-install,body.sharity-consent-blocking .impactshop-pwa-reopen{display:none !important;}'
            . '.pwa-no-bnav .impactshop-pwa-install,.pwa-no-bnav .impactshop-pwa-reopen{bottom:20px;}'
            . '.impactshop-mobile-utility[hidden]{display:none !important;}'
            . '@media (max-width:768px){'
            . '.impactshop-pwa-install,.impactshop-pwa-reopen{display:none !important;}'
            . '.impactshop-mobile-utility{position:fixed;right:0;bottom:calc(var(--sharity-action-bar-height, 116px) + env(safe-area-inset-bottom,0px) + 10px);z-index:2147483646;display:flex;align-items:center;gap:0;}'
            . '.impactshop-mobile-utility__panel{display:flex;align-items:center;gap:8px;padding:8px 10px 8px 12px;border-radius:18px 0 0 18px;background:rgba(15,23,42,.94);box-shadow:0 16px 34px rgba(15,23,42,.25);backdrop-filter:blur(10px);max-width:160px;overflow:hidden;opacity:1;transition:max-width .22s ease,opacity .16s ease,padding .22s ease,margin .22s ease;}'
            . '.impactshop-mobile-utility__button,.impactshop-mobile-utility__toggle{display:inline-flex;align-items:center;justify-content:center;border:0;cursor:pointer;color:#fff;}'
            . '.impactshop-mobile-utility__button{width:44px;height:44px;border-radius:14px;background:rgba(30,41,59,.92);font-size:22px;font-weight:800;}'
            . '.impactshop-mobile-utility__toggle{width:26px;height:52px;border-radius:14px 0 0 14px;background:rgba(15,23,42,.94);font-size:22px;font-weight:800;box-shadow:0 16px 34px rgba(15,23,42,.25);}'
            . '.impactshop-mobile-utility[data-state=\"collapsed\"] .impactshop-mobile-utility__panel{max-width:0;opacity:0;pointer-events:none;padding:0;margin:0;}'
            . 'body.sharity-consent-blocking .impactshop-mobile-utility{display:none !important;}'
            . '}'
            . '@media (min-width:992px){.impactshop-pwa-install,.impactshop-pwa-reopen{left:20px;bottom:24px;}}'
            . '</style>';

        echo $install_css;
        echo $install;
        echo $reopen;
        echo $mobile_utility;
    }

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
