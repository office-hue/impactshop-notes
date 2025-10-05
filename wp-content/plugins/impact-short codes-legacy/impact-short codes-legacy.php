<?php
/**
 * Plugin Name: Impact Shortcodes Legacy (Sharity)
 * Description: Visszahozza a régi rövidkódokat: [impact_ticker], [impact_leaderboard], [impact_activity]. Egyszerű REST-proxy és transient cache.
 * Version:     1.0.0
 * Author:      Sharity
 */

if (!defined('ABSPATH')) { exit; }

/*
 * BEÁLLÍTÁS
 * - Ha a wp-config.php már definiálja az IMPACT_API_BASE-t, azt használjuk.
 * - Ha nincs, fallback: https://app.sharity.hu/api
 *   (Az endpointok ehhez képest /impact/... – ugyanúgy mint a régi működő verzióban.)
 */
if (!defined('IMPACT_API_BASE')) {
  define('IMPACT_API_BASE', 'https://app.sharity.hu/api');
}

/* ===== SEGÉD: BASE normalizálása (levágjuk az esetleges záró /-t) ===== */
if (!function_exists('impact_legacy__base')) {
  function impact_legacy__base() {
    $b = rtrim(IMPACT_API_BASE, '/');
    // ha véletlen valaki hostot adott (/wp-json nélkül), a régivel kompatibilis maradunk:
    // a plugin a továbbiakban mindig "/impact/..." utakat kér.
    return $b;
  }
}

/* ===== SEGÉD: GET JSON + transient cache ===== */
if (!function_exists('impact_legacy_http_get_json_cached')) {
  function impact_legacy_http_get_json_cached($path, $cache_key, $ttl = 180) {
    // külön kulcsprefix, hogy ne ütközzön az új pluginnal:
    $cache_key = 'impact_legacy_' . $cache_key;

    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $url  = impact_legacy__base() . '/' . ltrim($path, '/');
    $resp = wp_remote_get($url, [
      'timeout' => 10,
      'headers' => ['Accept'=>'application/json'],
    ]);

    if (is_wp_error($resp)) {
      return ['error'=>'Hálózati hiba', 'details'=>$resp->get_error_message(), 'url'=>$url];
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $data = json_decode($body, true);

    if ($code !== 200 || !is_array($data)) {
      return ['error'=>'API hiba', 'status'=>$code, 'body'=>$body, 'url'=>$url];
    }

    set_transient($cache_key, $data, $ttl);
    return $data;
  }
}

/* ===== Frontbarát hiba HTML ===== */
if (!function_exists('impact_legacy_friendly_error')) {
  function impact_legacy_friendly_error($msg = 'Jelenleg nem elérhető. Próbáld újra később!') {
    return '<div class="impact-error" role="status" aria-live="polite">' . esc_html($msg) . '</div>';
  }
}

/* ===== [impact_ticker] – GET /impact/ticker  { total, today } ===== */
if (!shortcode_exists('impact_ticker')) {
  add_shortcode('impact_ticker', function($atts){
    $ttl  = 180;
    $data = impact_legacy_http_get_json_cached('/impact/ticker', 'ticker_json', $ttl);
    if (isset($data['error'])) {
      return impact_legacy_friendly_error('Ticker: ' . $data['error']);
    }
    $total = isset($data['total']) ? floatval($data['total']) : 0;
    $today = isset($data['today']) ? floatval($data['today']) : 0;

    ob_start(); ?>
    <div class="impact-ticker" data-total="<?php echo esc_attr($total); ?>" data-today="<?php echo esc_attr($today); ?>">
      <div class="impact-ticker-row">
        <span class="label">Összegyűjtve</span>
        <span class="value" id="impact-total"><?php echo number_format_i18n($total, 0); ?> Ft</span>
      </div>
      <div class="impact-ticker-row">
        <span class="label">Ma</span>
        <span class="value" id="impact-today"><?php echo number_format_i18n($today, 0); ?> Ft</span>
      </div>
    </div>
    <script>document.dispatchEvent(new CustomEvent('impact:updated',{detail:{source:'ticker'}}));</script>
    <?php
    return ob_get_clean();
  });
}

/* ===== [impact_leaderboard tab="ngo|shop"] – GET /impact/leaderboard?tab=... ===== */
if (!shortcode_exists('impact_leaderboard')) {
  add_shortcode('impact_leaderboard', function($atts){
    $a   = shortcode_atts(['tab'=>'ngo'], $atts, 'impact_leaderboard');
    $tab = sanitize_text_field($a['tab']);
    $ttl = 300;

    $path = '/impact/leaderboard?tab=' . rawurlencode($tab);
    $data = impact_legacy_http_get_json_cached($path, 'leaderboard_' . $tab, $ttl);
    if (isset($data['error']) || !is_array($data)) {
      return impact_legacy_friendly_error('Leaderboard: nem elérhető.');
    }

    ob_start(); ?>
    <div class="impact-leaderboard" data-tab="<?php echo esc_attr($tab); ?>">
      <ol class="impact-leaderboard-list">
        <?php foreach ($data as $row): ?>
          <li>
            <span class="name"><?php echo esc_html($row['name'] ?? '—'); ?></span>
            <span class="amount"><?php echo number_format_i18n(floatval($row['amount'] ?? 0), 0); ?> Ft</span>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
    <script>document.dispatchEvent(new CustomEvent('impact:updated',{detail:{source:'leaderboard',tab:<?php echo json_encode($tab); ?>}}));</script>
    <?php
    return ob_get_clean();
  });
}

/* ===== [impact_activity] – GET /impact/activity  [ {text}, ... ] ===== */
if (!shortcode_exists('impact_activity')) {
  add_shortcode('impact_activity', function($atts){
    $ttl  = 120;
    $data = impact_legacy_http_get_json_cached('/impact/activity', 'activity', $ttl);
    if (isset($data['error']) || !is_array($data)) {
      return impact_legacy_friendly_error('Aktivitás: nem elérhető.');
    }

    ob_start(); ?>
    <div class="impact-activity">
      <ul>
        <?php foreach ($data as $row): ?>
          <li><?php echo esc_html($row['text'] ?? ''); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <script>document.dispatchEvent(new CustomEvent('impact:updated',{detail:{source:'activity'}}));</script>
    <?php
    return ob_get_clean();
  });
}

/* ===== Opcionális alap CSS betöltése ===== */
add_action('wp_enqueue_scripts', function(){
  wp_register_style('impact-shortcodes-legacy', plugins_url('impact-shortcodes-legacy.css', __FILE__), [], '1.0.0');
  wp_enqueue_style('impact-shortcodes-legacy');
});