<?php
/*
 * 003 Impact Banners Maintenance
 * - "Last good" bannerek fallback tárolása/használata (12 óra)
 * - Időzített frissítés: 3 percenként meghívja a bannerek betöltést
 * - Opcionális melegítés az /impactshop/ oldalra
 */
if (!defined('ABSPATH')) exit;

define('SIB_LAST_GOOD_OPTION', 'sib_last_good_banners');
define('SIB_LAST_GOOD_TTL', 12 * 60 * 60); // 12 óra

// Segéd: mentsük az utolsó jó bannereket
function sib_store_last_good(array $rows){
    if (!$rows) return;
    update_option(SIB_LAST_GOOD_OPTION, [ 'rows' => array_values($rows), 'ts' => time() ], false);
}

// Segéd: töltsük vissza, ha 12 órán belüli
function sib_load_last_good(){
    $d = get_option(SIB_LAST_GOOD_OPTION);
    if (is_array($d) && !empty($d['rows'])){
        $ts = (int)($d['ts'] ?? 0);
        if ($ts && (time() - $ts) <= SIB_LAST_GOOD_TTL){
            return (array)$d['rows'];
        }
    }
    return [];
}

// Időzítő: 3 perces ütemezés
add_filter('cron_schedules', function($s){
    if (!isset($s['impact_3m'])){
        $s['impact_3m'] = [ 'interval' => 180, 'display' => 'Impact 3 minutes' ];
    }
    return $s;
});

add_action('init', function(){
    if (!wp_next_scheduled('sib_banners_refresh')){
        wp_schedule_event(time() + 60, 'impact_3m', 'sib_banners_refresh');
    }
});

// Frissítés: meghívja a sib_load_banners()-t ha elérhető; eltárolja last-good-ot, és melegít
add_action('sib_banners_refresh', function(){
    if (function_exists('sib_load_banners')){
        try{
            $rows = (array)sib_load_banners();
            if ($rows){
                sib_store_last_good($rows);
            }
        } catch (\Throwable $e){}
    }
    // Opcionális melegítés – gyors, rövid timeout
    $url = home_url('/impactshop/');
    wp_remote_get($url, [ 'timeout' => 1.5, 'redirection' => 1, 'sslverify' => false, 'headers' => ['User-Agent' => 'ImpactBannersMaint/1.0'] ]);
});

// Manuális trigger: ?sib_refresh=1 (csak adminnak)
add_action('init', function(){
    if (!isset($_GET['sib_refresh'])) return;
    if (!current_user_can('manage_options')) return;
    do_action('sib_banners_refresh');
    wp_die('OK: sib_banners_refresh lefutott');
});

