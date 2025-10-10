<?php
/**
 * Plugin Name: Dognet PAP Publisher Connector
 * Description: Publisher (affiliate) integráció a Dognet/PAP API-hoz: bannerek és linkek lehívása, SubID (data1,data2) és csatorna (chan) automatikus fűzése.
 * Version: 0.3.0
 * Author: Your Team
 */

if (!defined('ABSPATH')) exit;

final class Dognet_PAP_Publisher {
    const OPT = 'dognet_pap_pub';
    private $o;

    public function __construct() {
        $this->o = get_option(self::OPT, [
            'base_url' => 'https://login.dognet.sk', // PAP/PAN szerver gyökér (nem az új REST!)
            'username' => '',
            'password' => '',
            // lekérdezési param nevek (GET-ből), első nem üres nyer
            'q_data1'  => 'd1,data1,ngo,ngocode',
            'q_data2'  => 'd2,data2,amb,channel',
            'q_chan'   => 'chan,adchannel',
        ]);
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_init', [$this,'register']);
        add_shortcode('dognet_banners', [$this,'sc_banners']);
        add_shortcode('dognet_link',    [$this,'sc_link']);
    }
    public static function instance(){ static $i; return $i?:$i=new self(); }

    /* --------- Admin --------- */
    public function menu() {
        add_options_page('Dognet PAP (Publisher)', 'Dognet PAP (Publisher)', 'manage_options', 'dognet-pap-publisher', [$this,'render']);
    }
    public function register() {
        register_setting(self::OPT, self::OPT, function($in){
            return [
                'base_url' => esc_url_raw($in['base_url'] ?? $this->o['base_url']),
                'username' => sanitize_text_field($in['username'] ?? ''),
                'password' => $in['password'] ?? '',
                'q_data1'  => sanitize_text_field($in['q_data1'] ?? $this->o['q_data1']),
                'q_data2'  => sanitize_text_field($in['q_data2'] ?? $this->o['q_data2']),
                'q_chan'   => sanitize_text_field($in['q_chan']  ?? $this->o['q_chan']),
            ];
        });
    }
    public function render() {
        if (!current_user_can('manage_options')) return;
        $o = $this->o;
        ?>
        <div class="wrap">
            <h1>Dognet PAP (Publisher) – beállítások</h1>
            <p>A bővítmény a <code>PapApi.class.php</code> fájlt használja. Töltsd le a PAP API csomagot a merchant panelből (Tools → Integration → API Integration), és másold ide: <code>wp-content/plugins/dognet-pap-publisher/includes/PapApi.class.php</code>.</p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPT); ?>
                <table class="form-table">
                    <tr><th>Base URL (PAP)</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[base_url]" value="<?php echo esc_attr($o['base_url']); ?>" placeholder="https://login.dognet.sk"></td></tr>
                    <tr><th>Affiliate e‑mail</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[username]" value="<?php echo esc_attr($o['username']); ?>"></td></tr>
                    <tr><th>Jelszó</th><td><input type="password" class="regular-text" name="<?php echo self::OPT; ?>[password]" value="<?php echo esc_attr($o['password']); ?>"></td></tr>
                    <tr><th>Query → data1</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[q_data1]" value="<?php echo esc_attr($o['q_data1']); ?>"><br><small>Vesszővel: mely GET paraméterekből próbáljon data1‑et olvasni (pl. d1,data1,ngo)</small></td></tr>
                    <tr><th>Query → data2</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[q_data2]" value="<?php echo esc_attr($o['q_data2']); ?>"></td></tr>
                    <tr><th>Query → chan</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[q_chan]"  value="<?php echo esc_attr($o['q_chan']); ?>"><br><small>Dognet 2.0 csatornákhoz: <code>chan</code> (kötelezően ajánlott).</small></td></tr>
                </table>
                <?php submit_button('Mentés'); ?>
            </form>
            <p><em>Megjegyzés:</em> a Dognet 2.0 Merchant REST dokumentuma nem szükséges a publisher integrációhoz; a jelen bővítmény a PAP API‑t használja.</p>
        </div>
        <?php
    }

    /* --------- PAP session --------- */
    private function pap_api_path(){
        return plugin_dir_path(__FILE__).'includes/PapApi.class.php';
    }
    private function session() {
        $path = $this->pap_api_path();
        if (!file_exists($path)) return new WP_Error('pap_missing', 'Hiányzik a PapApi.class.php az includes könyvtárból.');
        require_once $path;
        $server = rtrim($this->o['base_url'], '/') . '/scripts/server.php';
        try {
            $s = new Pap_Api_Session($server);
            if (!$s->login($this->o['username'], $this->o['password'])) {
                return new WP_Error('pap_login', 'PAP bejelentkezés sikertelen: '.$s->getMessage());
            }
            return $s;
        } catch (Exception $e) {
            return new WP_Error('pap_exc', $e->getMessage());
        }
    }

    /* --------- Helpers --------- */
    private function pick_from_query($csv){
        $keys = array_map('trim', explode(',', $csv));
        foreach ($keys as $k) {
            if (isset($_GET[$k]) && $_GET[$k] !== '') {
                return sanitize_text_field(wp_unslash($_GET[$k]));
            }
        }
        return '';
    }
    private function add_params_to_href($html, $params) {
        if (!$html) return $html;
        return preg_replace_callback('/href=([\'"])([^\'"]+)\1/i', function($m) use ($params){
            $u = $this->append_params($m[2], $params);
            return 'href="'.esc_url($u).'"';
        }, $html);
    }
    private function append_params($url, $new){
        $parts = wp_parse_url($url);
        $q = [];
        if (!empty($parts['query'])) parse_str($parts['query'], $q);
        foreach ($new as $k=>$v){ if($v!=='' && $v!==null) $q[$k]=$v; }
        $parts['query'] = http_build_query($q);
        $built = (isset($parts['scheme'])?$parts['scheme'].'://':'').
                 ($parts['host']??'').
                 (isset($parts['port'])?':'.$parts['port']:'').
                 ($parts['path']??'').
                 (!empty($parts['query'])?('?'.$parts['query']):'').
                 (isset($parts['fragment'])?('#'.$parts['fragment']):'');
        return $built;
    }

    /* --------- Shortcodes --------- */

    // [dognet_banners types="I,T" limit="8" d1="" d2="" chan="" read_query="1"]
    public function sc_banners($atts){
        $a = shortcode_atts([
            'types'      => '',   // 'I' (image), 'T' (text), 'H' (HTML), stb.
            'limit'      => 8,
            'd1'         => '',   // SubID1 (NGO kód)
            'd2'         => '',   // SubID2 (ambassador/csatorna)
            'chan'       => '',   // PAP ad channel kód
            'read_query' => '1',  // GET-ből felülírhatja a d1/d2/chan-t
        ], $atts, 'dognet_banners');

        if ($a['read_query'] === '1') {
            $a['d1']   = $this->pick_from_query($this->o['q_data1']) ?: $a['d1'];
            $a['d2']   = $this->pick_from_query($this->o['q_data2']) ?: $a['d2'];
            $a['chan'] = $this->pick_from_query($this->o['q_chan'])  ?: $a['chan'];
        }

        $cache_key = 'dognet_pap_banners_'.md5(json_encode($a));
        if ($html = get_transient($cache_key)) return $html;

        $s = $this->session();
        if (is_wp_error($s)) return '<div class="notice notice-error">'.esc_html($s->get_error_message()).'</div>';

        try {
            // Bannerek lekérése affiliate nézetben
            $req = new Gpf_Rpc_GridRequest("Pap_Affiliates_Promo_BannersGrid", "getRows", $s);
            $req->setLimit(0, (int)$a['limit']);
            if (!empty($a['types'])) $req->addFilter('rtype', Gpf_Data_Filter::IN, strtoupper($a['types']));
            $req->addParam('columns', new Gpf_Rpc_Array([['bannercode'],['name'],['rtype']]));
            $req->sendNow();
            $rs = $req->getGrid()->getRecordset();

            $out = '<div class="dognet-pap-banners">';
            foreach ($rs as $r) {
                $code = $r->get('bannercode'); // PAP előállított HTML
                $code = $this->add_params_to_href($code, array_filter([
                    'chan'  => $a['chan'],
                    'data1' => $a['d1'],
                    'data2' => $a['d2'],
                ], fn($v)=>$v!==''));
                $out .= '<div class="dognet-pap-banner">'.$code.'</div>';
            }
            $out .= '</div>';

            set_transient($cache_key, $out, HOUR_IN_SECONDS);
            return $out;
        } catch (Exception $e) {
            return '<div class="notice notice-error">'.esc_html($e->getMessage()).'</div>';
        }
    }

    // [dognet_link text="Megnézem" url="" d1="" d2="" chan="" a_bid=""]
    public function sc_link($atts){
        $a = shortcode_atts([
            'text' => 'Vásárlás',
            'url'  => '',     // (opcionális) deeplink a kereskedő oldalára
            'd1'   => '',
            'd2'   => '',
            'chan' => '',
            'a_bid'=> '',     // (opcionális) banner ID erőltetése
            'read_query' => '1',
        ], $atts, 'dognet_link');

        if ($a['read_query'] === '1') {
            $a['d1']   = $this->pick_from_query($this->o['q_data1']) ?: $a['d1'];
            $a['d2']   = $this->pick_from_query($this->o['q_data2']) ?: $a['d2'];
            $a['chan'] = $this->pick_from_query($this->o['q_chan'])  ?: $a['chan'];
        }

        $s = $this->session();
        if (is_wp_error($s)) return '<div class="notice notice-error">'.esc_html($s->get_error_message()).'</div>';

        try {
            // Kérünk egy szöveges link-mintát, hogy legyen kiinduló href
            $req = new Gpf_Rpc_GridRequest("Pap_Affiliates_Promo_BannersGrid", "getRows", $s);
            $req->setLimit(0, 1);
            $req->addFilter('rtype', Gpf_Data_Filter::EQUALS, 'T'); // text link
            $req->addParam('columns', new Gpf_Rpc_Array([['bannercode']]));
            $req->sendNow();

            $code = $req->getGrid()->getRecordset()->get(0)->get('bannercode');
            if (!preg_match('/href=[\'"]([^\'"]+)[\'"]/', $code, $m)) {
                return '<a>'.esc_html($a['text']).'</a>';
            }
            $href = $m[1];
            $href = $this->append_params($href, array_filter([
                'a_bid' => $a['a_bid'] ?: null,
                'chan'  => $a['chan'] ?: null,
                'data1' => $a['d1']   ?: null,
                'data2' => $a['d2']   ?: null,
            ], fn($v)=>$v!==null));

            if (!empty($a['url'])) {
                // PAP-nál gyakori a 'desturl' deeplink param
                $href = $this->append_params($href, ['desturl' => $a['url']]);
            }

            return '<a href="'.esc_url($href).'">'.esc_html($a['text']).'</a>';
        } catch (Exception $e) {
            return '<div class="notice notice-error">'.esc_html($e->getMessage()).'</div>';
        }
    }
}
Dognet_PAP_Publisher::instance();
