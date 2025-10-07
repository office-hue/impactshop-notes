<?php
/*
 * 002 Impact Micro Cache (HTML)
 * - 120 mp-es mikrocache az /impactshop/ oldalra és rokon pathokra
 * - Csak GET, csak kijelentkezett felhasználóknak, query string nélkül
 * - Bypass: ?nocache=1 vagy ha nyomkövető paraméterek vannak (utm_, d1, amb, src)
 */
if (!defined('ABSPATH')) { exit; }

add_action('template_redirect', function() {
    if (is_user_logged_in()) return; // csak anonim
    if ('GET' !== $_SERVER['REQUEST_METHOD']) return;
    if (!empty($_GET)) return; // nincs query string

    $uri = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
    // Csak az impactshop oldal(ak)
    if (strpos($uri, '/impactshop') === false) return;

    // Bypass feltételek
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if (strpos($qs, 'nocache=1') !== false) return;
    foreach (['utm_', 'd1=', 'amb=', 'src='] as $mark) {
        if (strpos($qs, $mark) !== false) return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'local';
    $key = 'impact_html_' . md5($host . '|' . $uri);

    // HIT kiszolgálása
    $cached = get_transient($key);
    if (is_string($cached) && $cached !== '') {
        if (!headers_sent()) {
            header('X-Impact-MicroCache: HIT');
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo $cached;
        exit;
    }

    // MISS: puffer indítása és STORE a shutdown végén
    if (!defined('IMPACT_HTML_MICROCACHE_KEY')) {
        define('IMPACT_HTML_MICROCACHE_KEY', $key);
    }
    if (!defined('IMPACT_HTML_MICROCACHE_TTL')) {
        define('IMPACT_HTML_MICROCACHE_TTL', 120);
    }

    if (!headers_sent()) {
        header('X-Impact-MicroCache: MISS');
    }

    ob_start(function($buffer) {
        // Csak HTML-t mentsünk
        if (!is_string($buffer) || $buffer === '') return $buffer;
        $key = defined('IMPACT_HTML_MICROCACHE_KEY') ? IMPACT_HTML_MICROCACHE_KEY : '';
        $ttl = defined('IMPACT_HTML_MICROCACHE_TTL') ? IMPACT_HTML_MICROCACHE_TTL : 120;
        if ($key) {
            set_transient($key, $buffer, $ttl);
        }
        return $buffer;
    });
});


// ——— Mikrocache melegítő (WP-Cron) ———
add_filter('cron_schedules', function($schedules){
    if (!isset($schedules['impact_90s'])){
        $schedules['impact_90s'] = [ 'interval' => 90, 'display' => 'Impact 90 seconds' ];
    }
    return $schedules;
});

add_action('init', function(){
    if (!wp_next_scheduled('impact_microcache_warm')){
        wp_schedule_event(time() + 60, 'impact_90s', 'impact_microcache_warm');
    }
});

add_action('impact_microcache_warm', function(){
    // Loopback kérés az oldalra a cache töltéséhez
    $url = home_url('/impactshop/');
    wp_remote_get($url, [
        'timeout' => 1.5,
        'redirection' => 1,
        'sslverify' => false,
        'headers' => ['User-Agent' => 'ImpactMicroCacheWarmer/1.0']
    ]);
});
