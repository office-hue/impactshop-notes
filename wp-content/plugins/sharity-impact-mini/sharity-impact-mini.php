<?php
/**
 * Plugin Name: Sharity Impact – MINI UI Shortcodes
 * Description: Ticker, Leaderboard, Activity – kanonikus /wp-json/impact/v1/... hívásokkal, rövid cache-sel, baráti hibákkal. Nem nyúl wp-config-hoz.
 * Version:     1.0.0
 * Author:      Sharity
 */

if (!defined('ABSPATH')) { exit; }

class Sharity_Impact_Mini {
    const VERSION = '1.0.0';
    // Csak host (kanonikus): minden végül /wp-json/impact/v1/...-ra mutat
    const DEFAULT_API_HOST = 'https://app.sharity.hu';

    private static $instance = null;
    private $api_host;

    public static function instance() {
        return self::$instance ?: self::$instance = new self();
    }

    private function __construct() {
        // Alap host felülírható define()-nal (IMPACT_API_BASE_HOST), de NEM kell wp-config-ot piszkálni
        $this->api_host = defined('IMPACT_API_BASE_HOST') ? rtrim(IMPACT_API_BASE_HOST, '/') : self::DEFAULT_API_HOST;

        add_action('init', [$this, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    private function endpoint($path) {
        $path = ltrim($path, '/');
        // Garantáltan pontosan egyszer legyen /wp-json/impact/v1/...
        return $this->api_host . '/wp-json/impact/v1/' . $path;
    }

    private function get_json($endpoint, $cache_ttl, $cache_key_extra = '') {
        $cache_key = 'impact_minicache_' . md5($endpoint . '|' . $cache_key_extra);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $resp = wp_remote_get($endpoint, [
            'timeout' => 8,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($resp)) {
            return $this->error_payload('Hálózati hiba', $resp->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        if ($code < 200 || $code >= 300) {
            return $this->error_payload('API hiba', 'HTTP ' . $code . ' – ' . wp_strip_all_tags($body));
        }

        $data = json_decode($body, true);
        if ($data === null) {
            return $this->error_payload('JSON hiba', 'A válasz nem értelmezhető JSON.');
        }

        // Cache csak sikeres JSON esetén
        set_transient($cache_key, $data, $cache_ttl);
        return $data;
    }

    private function error_payload($title, $msg) {
        return [
            '_error' => true,
            'title'  => $title,
            'msg'    => $msg,
        ];
    }

    private function render_error_box($title, $msg) {
        $title = esc_html($title);
        $msg   = esc_html($msg);
        return '<div class="impact-box impact-error"><strong>' . $title . ':</strong> ' . $msg . '</div>';
    }

    public function enqueue_assets() {
        // Alap stílus – dark theme + színtokenek + kártyák + mini animációk
        $css = "
.impact-wrap{
  --impact-bg:#F8FAFC; --impact-fg:#0F172A;
  --impact-purple:#7C3AED; --impact-cyan:#06B6D4; --impact-orange:#F97316; --impact-lime:#22C55E;
  --impact-muted:#64748B; --impact-card-bg:rgba(255,255,255,.92);
  --impact-border:rgba(15,23,42,.08); --impact-shadow:0 10px 24px rgba(15,23,42,.08);
  color:var(--impact-fg);font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif
}
.impact-wrap.impact-dark{
  --impact-bg:#0A0A0B; --impact-fg:#F8FAFC;
  --impact-muted:#94A3B8; --impact-card-bg:rgba(255,255,255,.06);
  --impact-border:rgba(255,255,255,.08); --impact-shadow:0 8px 24px rgba(0,0,0,.35);
}
.impact-grid{display:grid;gap:12px}
.impact-row{display:flex;gap:12px;flex-wrap:wrap}
.impact-card{background:var(--impact-card-bg);backdrop-filter:blur(10px);border:1px solid var(--impact-border);
  border-radius:14px;padding:14px;box-shadow:var(--impact-shadow);transition:transform .2s ease, box-shadow .2s ease}
.impact-card:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(15,23,42,.12)}
.impact-kpi{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.impact-kpi .kpi{padding:18px;border-radius:14px;background:linear-gradient(135deg, rgba(124,58,237,.18), rgba(6,182,212,.18))}
.kpi .label{font-size:12px;color:var(--impact-muted);letter-spacing:.08em;text-transform:uppercase}
.kpi .value{font-size:28px;font-weight:700;line-height:1.1;margin-top:6px}
.kpi .sub{font-size:12px;color:var(--impact-muted);margin-top:4px}
.impact-list{list-style:none;margin:0;padding:0}
.impact-list li{padding:10px 12px;border-bottom:1px dashed rgba(255,255,255,.08)}
.impact-tabs{display:flex;gap:8px;margin-bottom:10px}
.impact-tab{cursor:pointer;border:1px solid rgba(255,255,255,.12);padding:8px 10px;border-radius:12px;font-size:13px}
.impact-tab.active{background:rgba(124,58,237,.25);border-color:rgba(124,58,237,.55)}
.impact-error{background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.4);color:#FFD7BA;padding:12px;border-radius:12px}
.impact-muted{color:var(--impact-muted)}
@media (max-width:720px){.impact-kpi{grid-template-columns:1fr}}
";
        wp_register_style('impact-mini-style', false, [], self::VERSION);
        wp_add_inline_style('impact-mini-style', $css);
        wp_enqueue_style('impact-mini-style');

        // Kis JS: tab-váltás (leaderboard), frissítés-kijelzés + konfetti trigger
        $js = "
document.addEventListener('click', function(e){
  var tab = e.target.closest('[data-impact-tab]');
  if(!tab) return;
  var root = tab.closest('[data-impact-lb]');
  root.querySelectorAll('[data-impact-tab]').forEach(t=>t.classList.remove('active'));
  tab.classList.add('active');
  var want = tab.getAttribute('data-impact-tab');
  root.querySelectorAll('[data-impact-panel]').forEach(p => {
    p.style.display = (p.getAttribute('data-impact-panel')===want)?'block':'none';
  });
});
document.addEventListener('impact:updated', function(){
  // Ide később mehet valódi konfetti; most lightweight villanás:
  const flash = document.createElement('div');
  flash.style.position='fixed'; flash.style.inset='0'; flash.style.pointerEvents='none';
  flash.style.background='radial-gradient(circle at 50% 50%, rgba(34,197,94,.25), transparent 60%)';
  flash.style.transition='opacity .6s ease'; document.body.appendChild(flash);
  requestAnimationFrame(()=>{ flash.style.opacity='0'; setTimeout(()=>flash.remove(), 650); });
});
";
        wp_register_script('impact-mini-js', false, [], self::VERSION, true);
        wp_add_inline_script('impact-mini-js', $js);
        wp_enqueue_script('impact-mini-js');
    }

    public function register_shortcodes() {
        add_shortcode('impact_ticker',     [$this, 'sc_ticker']);
        add_shortcode('impact_leaderboard',[$this, 'sc_leaderboard']);
        add_shortcode('impact_activity',   [$this, 'sc_activity']);
        add_shortcode('impact_local_flush',[$this, 'sc_flush']);
        add_shortcode('impact_diag',       [$this, 'sc_diag']);
    }

    /** [impact_ticker] */
    public function sc_ticker($atts) {
        $data = $this->get_json($this->endpoint('ticker'), 180, 'ticker');
        if (!empty($data['_error'])) return $this->render_error_box($data['title'], $data['msg']);

        $total = isset($data['total']) ? $data['total'] : 0;
        $today = isset($data['today']) ? $data['today'] : 0;
        $gen   = isset($data['generated_at']) ? $data['generated_at'] : '';

        $html  = '<div class="impact-wrap impact-kpi">';
        $html .=   '<div class="kpi impact-card">';
        $html .=     '<div class="label">Összegyűjtve</div>';
        $html .=     '<div class="value">€ ' . esc_html(number_format((float)$total, 2, ',', ' ')) . '</div>';
        $html .=     '<div class="sub impact-muted">A jóváhagyott jutalékok 50%-a (adomány)</div>';
        $html .=   '</div>';
        $html .=   '<div class="kpi impact-card">';
        $html .=     '<div class="label">Ma</div>';
        $html .=     '<div class="value">€ ' . esc_html(number_format((float)$today, 2, ',', ' ')) . '</div>';
        $html .=     '<div class="sub impact-muted">Frissítve: ' . esc_html($gen) . '</div>';
        $html .=   '</div>';
        $html .= '</div>';

        // Front jelzés a frissítésről (konfetti/animáció)
        $html .= '<script>document.dispatchEvent(new CustomEvent("impact:updated"));</script>';
        return $html;
    }

    /** [impact_leaderboard tab="ngo|shop"] */
    public function sc_leaderboard($atts) {
        $atts = shortcode_atts(['tab' => 'ngo'], $atts, 'impact_leaderboard');
        $tab  = in_array($atts['tab'], ['ngo','shop'], true) ? $atts['tab'] : 'ngo';

        $data_ngo  = $this->get_json($this->endpoint('leaderboard?tab=ngo'), 300, 'lb_ngo');
        $data_shop = $this->get_json($this->endpoint('leaderboard?tab=shop'), 300, 'lb_shop');

        $err = (isset($data_ngo['_error']) ? $data_ngo : (isset($data_shop['_error']) ? $data_shop : null));
        if ($err) return $this->render_error_box($err['title'], $err['msg']);

        $html  = '<div class="impact-wrap" data-impact-lb>';
        $html .=   '<div class="impact-tabs">';
        $html .=     '<button class="impact-tab'.($tab==='ngo'?' active':'').'" data-impact-tab="ngo">Szervezetek</button>';
        $html .=     '<button class="impact-tab'.($tab==='shop'?' active':'').'" data-impact-tab="shop">Webshopok</button>';
        $html .=   '</div>';

        $html .=   $this->render_lb_panel('ngo',  $data_ngo,  $tab==='ngo');
        $html .=   $this->render_lb_panel('shop', $data_shop, $tab==='shop');

        $html .= '</div>';
        return $html;
    }

    private function render_lb_panel($key, $list, $visible) {
        $style = $visible ? 'block' : 'none';
        $out  = '<div class="impact-card" data-impact-panel="'.$key.'" style="display:'.$style.'">';
        $out .= '<ol class="impact-list">';
        if (is_array($list)) {
            foreach ($list as $row) {
                $name = isset($row['name']) ? $row['name'] : '—';
                if ($key === 'ngo') {
                    $name = $this->normalize_ngo_name($name);
                }
                $amt  = isset($row['amount']) ? (float)$row['amount'] : 0.0;
                $out .= '<li><strong>'.esc_html($name).'</strong> — € '.esc_html(number_format($amt, 2, ',', ' ')).'</li>';
            }
        } else {
            $out .= '<li class="impact-muted">Nincs adat.</li>';
        }
        $out .= '</ol></div>';
        return $out;
    }

    private function normalize_ngo_name($name) {
        $name = trim((string)$name);
        if ($name === '' || $name === '—') {
            return $name;
        }
        $map = $this->get_ngo_name_map();
        $slug = sanitize_title($name);
        if ($slug && isset($map[$slug])) {
            return $map[$slug];
        }
        $fallback = str_replace(['-', '_'], ' ', $name);
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($fallback, MB_CASE_TITLE, 'UTF-8');
        }
        return ucwords($fallback);
    }

    private function get_ngo_name_map() {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $path = trailingslashit(ABSPATH) . 'ngo_codes.csv';
        if (!file_exists($path)) {
            return $map;
        }
        if (($handle = fopen($path, 'r')) === false) {
            return $map;
        }
        $row = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if ($row === 1) {
                continue;
            }
            $label = isset($data[0]) ? trim((string)$data[0]) : '';
            $slug = isset($data[1]) ? sanitize_title($data[1]) : '';
            if ($label !== '' && $slug !== '') {
                $map[$slug] = $label;
            }
        }
        fclose($handle);
        return $map;
    }

    /** [impact_activity] */
    public function sc_activity($atts) {
        $data = $this->get_json($this->endpoint('activity'), 120, 'activity');
        if (!empty($data['_error'])) return $this->render_error_box($data['title'], $data['msg']);

        $html  = '<div class="impact-wrap impact-card">';
        $html .=   '<ul class="impact-list">';
        if (is_array($data) && count($data)) {
            foreach ($data as $row) {
                $text = isset($row['text']) ? $row['text'] : '';
                $html .= '<li>'. esc_html($text) .'</li>';
            }
        } else {
            $html .= '<li class="impact-muted">Még nincsenek friss aktivitások.</li>';
        }
        $html .=   '</ul>';
        $html .= '</div>';
        return $html;
    }

    /** [impact_local_flush] – üríti a mini cache-t */
    public function sc_flush() {
        global $wpdb;
        $count = 0;
        // WordPress transients a wp_options-ban: option_name LIKE '_transient_impact_minicache_%'
        $like1 = $wpdb->esc_like('_transient_impact_minicache_') . '%';
        $like2 = $wpdb->esc_like('_transient_timeout_impact_minicache_') . '%';
        $rows1 = $wpdb->get_col( $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like1) );
        $rows2 = $wpdb->get_col( $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like2) );
        foreach (array_merge($rows1, $rows2) as $opt) { delete_option($opt); $count++; }
        return '<div class="impact-box impact-card">Cache törölve. Elemek: '.intval($count).'</div>';
    }

    /** [impact_diag] – 3 endpoint teszt, HTTP/JSON ellenőrzés */
    public function sc_diag() {
        $endpoints = [
            'ticker'     => $this->endpoint('ticker'),
            'leaderboard'=> $this->endpoint('leaderboard?tab=ngo'),
            'activity'   => $this->endpoint('activity'),
        ];
        $html = '<div class="impact-wrap impact-card"><strong>Impact DIAG</strong><ul class="impact-list">';
        foreach ($endpoints as $name => $url) {
            $resp = wp_remote_get($url, ['timeout'=>6, 'headers'=>['Accept'=>'application/json']]);
            if (is_wp_error($resp)) {
                $html .= '<li>'.esc_html($name).': HIBA – '.esc_html($resp->get_error_message()).'</li>';
                continue;
            }
            $code = wp_remote_retrieve_response_code($resp);
            $ok   = ($code>=200 && $code<300);
            $body = wp_remote_retrieve_body($resp);
            $is_json = (json_decode($body,true)!==null);
            $html .= '<li>'.esc_html($name).': HTTP '.$code.'; JSON '.($is_json?'OK':'HIBA').'</li>';
        }
        $html .= '</ul></div>';
        return $html;
    }
}

Sharity_Impact_Mini::instance();
