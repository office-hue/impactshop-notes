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
define('SIB_EMPTY_COUNT_OPTION', 'sib_banners_empty_count');
define('SIB_LAST_KICK_OPTION', 'sib_banners_last_kick_ts');

// Apps Script webhook feloldása (ENV vagy WP option)
function sib_gas_webhook_url(){
    $opt = get_option('sib_gas_webhook_url');
    if (is_string($opt) && $opt !== '') return $opt;
    $env = getenv('SIB_GAS_WEBHOOK_URL');
    return is_string($env) ? $env : '';
}

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
                update_option(SIB_EMPTY_COUNT_OPTION, 0, false);
            } else {
                // Üres: számláló növelés + Apps Script kick (ha engedélyezett)
                $n = (int)get_option(SIB_EMPTY_COUNT_OPTION, 0) + 1;
                update_option(SIB_EMPTY_COUNT_OPTION, $n, false);
                if ($n >= 2){
                    $url = sib_gas_webhook_url();
                    $last = (int)get_option(SIB_LAST_KICK_OPTION, 0);
                    if ($url && (time() - $last) > 10*60){ // 10 percenként max. egy kick
                        $res = wp_remote_get($url, [ 'timeout' => 5, 'redirection' => 2, 'sslverify' => false, 'headers' => [ 'User-Agent' => 'ImpactBannersKick/1.0' ] ]);
                        update_option(SIB_LAST_KICK_OPTION, time(), false);
                        if (is_wp_error($res)){
                            error_log('sib_banners_refresh: GAS kick error: ' . $res->get_error_message());
                        } else {
                            error_log('sib_banners_refresh: GAS kick called');
                        }
                    }
                }
            }
        } catch (\Throwable $e){}
    }
    // Opcionális melegítés – gyors, rövid timeout
    $url = home_url('/impactshop/');
    wp_remote_get($url, [ 'timeout' => 1.5, 'redirection' => 1, 'sslverify' => false, 'headers' => ['User-Agent' => 'ImpactBannersMaint/1.0'] ]);

    // Ha üres állapotot észlelünk (n>=1), ütemezzünk egy gyors egyszeri próbát 60s múlva
    $n = (int)get_option(SIB_EMPTY_COUNT_OPTION, 0);
    if ($n >= 1){
        if (!wp_next_scheduled('sib_banners_refresh')){
            wp_schedule_single_event(time() + 60, 'sib_banners_refresh');
        }
    }
});

// Manuális trigger: ?sib_refresh=1 (csak adminnak)
add_action('init', function(){
    if (!isset($_GET['sib_refresh'])) return;
    if (!current_user_can('manage_options')) return;
    do_action('sib_banners_refresh');
    wp_die('OK: sib_banners_refresh lefutott');
});

// Manuális Apps Script indítás: ?sib_kick=1 (csak admin), ha van webhook URL beállítva
add_action('init', function(){
    if (!isset($_GET['sib_kick'])) return;
    if (!current_user_can('manage_options')) return;
    $url = sib_gas_webhook_url();
    if (!$url) wp_die('Nincs SIB_GAS_WEBHOOK_URL beállítva');
    $res = wp_remote_get($url, [ 'timeout' => 5, 'redirection' => 2, 'sslverify' => false ]);
    if (is_wp_error($res)){
        wp_die('Kick hiba: ' . esc_html($res->get_error_message()));
    }
    update_option(SIB_LAST_KICK_OPTION, time(), false);
    wp_die('OK: GAS kick elküldve');
});
