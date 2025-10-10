<?php
/**
 * Plugin Name: Impact Mini Shortcodes (LOCAL only)
 * Description: Dognet-mentes ticker / toplista / aktivitás, a helyi /wp-json/impactshop/v1/totals végpontra építve.
 * Version:     1.0.0
 * Author:      Sharity
 */
if (!defined('ABSPATH')) exit;

/* =========================
 * BELSŐ BEÁLLÍTÁSOK
 * ========================= */
if (!defined('IMS_CACHE_TTL_TICKER'))      define('IMS_CACHE_TTL_TICKER', 180); // mp
if (!defined('IMS_CACHE_TTL_LEADERBOARD')) define('IMS_CACHE_TTL_LEADERBOARD', 300);
if (!defined('IMS_CACHE_TTL_ACTIVITY'))    define('IMS_CACHE_TTL_ACTIVITY', 180);

/** Havi időszak (alapértelmezés) */
function ims_month_from() { return date('Y-m-01'); }
function ims_today()      { return date('Y-m-d'); }

/** Helyi totals endpoint URL-építő */
function ims_totals_url($args = []) {
  $base = home_url('/wp-json/impactshop/v1/totals');
  $def = [
    'from'   => ims_month_from(),
    'to'     => ims_today(),
    'status' => 'approved',         // csak jóváhagyott a biztos
    'group'  => 'shop_ngo',         // alap bontás
  ];
  $q = array_merge($def, $args);
  return add_query_arg($q, $base);
}

/** HTTP GET JSON + rövid cache */
function ims_http_get_json_cached($url, $cache_key, $ttl) {
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $resp = wp_remote_get($url, ['timeout' => 15, 'headers' => ['Accept' => 'application/json']]);
  if (is_wp_error($resp)) return ['error' => $resp->get_error_message()];

  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);
  $json = json_decode($body, true);
  if ($code !== 200 || !is_array($json)) return ['error' => 'HTTP '.$code];

  set_transient($cache_key, $json, $ttl);
  return $json;
}

/** Biztonságos float + adomány (=50%) */
function ims_num($v) { return is_numeric($v) ? (float)$v : 0.0; }
function ims_donation_from_commission($commission) { return ims_num($commission) * 0.5; }

/* =========================
 * [ims_ticker]
 * ========================= */
add_shortcode('ims_ticker', function($atts){
  $a = shortcode_atts([
    'from' => ims_month_from(),
    'to'   => ims_today(),
  ], $atts, 'ims_ticker');

  // Havi összes (approved)
  $url_month = ims_totals_url(['from'=>$a['from'], 'to'=>$a['to'], 'status'=>'approved', 'group'=>'shop_ngo']);
  $data_month = ims_http_get_json_cached($url_month, 'ims_ticker_month_'.md5($url_month), IMS_CACHE_TTL_TICKER);
  if (isset($data_month['error'])) return '<div class="ims-error">Ticker hiba: '.esc_html($data_month['error']).'</div>';

  $grand = $data_month['meta']['grand'] ?? ['commission'=>0, 'order_value'=>0, 'orders'=>0];
  $donation_total = ims_donation_from_commission($grand['commission'] ?? 0);

  // Mai nap (approved)
  $today = ims_today();
  $url_today = ims_totals_url(['from'=>$today, 'to'=>$today, 'status'=>'approved', 'group'=>'shop_ngo']);
  $data_today = ims_http_get_json_cached($url_today, 'ims_ticker_today_'.md5($url_today), IMS_CACHE_TTL_TICKER);
  $grand_today = $data_today['meta']['grand'] ?? ['commission'=>0];
  $donation_today = ims_donation_from_commission($grand_today['commission'] ?? 0);

  ob_start(); ?>
  <div class="ims-ticker" style="font:14px/1.5 system-ui">
    <div><b>Összegyűjtve (<?php echo esc_html($a['from'].' → '.$a['to']); ?>):</b>
      <span><?php echo number_format_i18n($donation_total, 2); ?> €</span>
    </div>
    <div><b>Ma:</b> <span><?php echo number_format_i18n($donation_today, 2); ?> €</span></div>
  </div>
  <?php
  return ob_get_clean();
});

/* =========================
 * [ims_leaderboard tab="ngo|shop" limit="10"]
 * ========================= */
add_shortcode('ims_leaderboard', function($atts){
  $a = shortcode_atts([
    'tab'   => 'ngo',               // ngo|shop
    'limit' => '10',
    'from'  => ims_month_from(),
    'to'    => ims_today(),
  ], $atts, 'ims_leaderboard');

  $group = ($a['tab']==='shop') ? 'shop' : 'ngo';
  $url = ims_totals_url(['from'=>$a['from'], 'to'=>$a['to'], 'status'=>'approved', 'group'=>$group]);
  $data = ims_http_get_json_cached($url, 'ims_lb_'.md5($url), IMS_CACHE_TTL_LEADERBOARD);
  if (isset($data['error'])) return '<div class="ims-error">Leaderboard hiba: '.esc_html($data['error']).'</div>';

  $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
  // adomány = commission 50% (EUR-ban – a riportban így jön vissza)
  $list = [];
  foreach ($rows as $r) {
    $name = ($group==='ngo') ? ($r['ngo'] ?? '—') : ($r['shop_name'] ?? $r['shop_slug'] ?? '—');
    $don  = ims_donation_from_commission($r['commission'] ?? 0);
    $list[] = ['name'=>$name, 'donation'=>$don];
  }
  usort($list, fn($x,$y)=> ($y['donation'] <=> $x['donation']) ?: strcasecmp($x['name'],$y['name']));
  $list = array_slice($list, 0, max(1, (int)$a['limit']));

  ob_start(); ?>
  <div class="ims-leaderboard" style="font:14px/1.5 system-ui">
    <div style="margin-bottom:6px;color:#666">
      Időszak: <b><?php echo esc_html($a['from'].' → '.$a['to']); ?></b> ·
      Top <?php echo (int)$a['limit']; ?> (<?php echo esc_html($group==='ngo'?'szervezetek':'webshopok'); ?>)
    </div>
    <ol style="margin:0;padding-left:22px">
      <?php foreach ($list as $it): ?>
        <li>
          <span><?php echo esc_html($it['name']); ?></span>
          <span style="float:right"><?php echo number_format_i18n($it['donation'], 2); ?> €</span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php
  return ob_get_clean();
});

/* =========================
 * [ims_activity days="3" limit="10"]
 * Megjegyzés: nincs nyers tranzakció végpont publikusban, ezért
 * napokra összegzett adományból készít “feed”-szerű sorokat.
 * ========================= */
add_shortcode('ims_activity', function($atts){
  $a = shortcode_atts([
    'days'  => '3',
    'limit' => '10',
  ], $atts, 'ims_activity');

  $days  = max(1, min(14, (int)$a['days']));
  $limit = max(1, min(50, (int)$a['limit']));

  $items = [];
  for ($i=0; $i<$days; $i++) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $url = ims_totals_url(['from'=>$day, 'to'=>$day, 'status'=>'approved', 'group'=>'ngo']);
    $data = ims_http_get_json_cached($url, 'ims_act_'.md5($url), IMS_CACHE_TTL_ACTIVITY);
    if (isset($data['error'])) continue;
    $rows = $data['rows'] ?? [];
    // vegyük a nap 2 legnagyobb NGO-ját, hogy legyen “mozgás”
    usort($rows, fn($x,$y)=> ($y['commission'] <=> $x['commission']));
    $rows = array_slice($rows, 0, 2);
    foreach ($rows as $r) {
      $don = ims_donation_from_commission($r['commission'] ?? 0);
      if ($don <= 0) continue;
      $items[] = [
        'when' => $day,
        'text' => sprintf('%s napon %s – %s € adomány', date_i18n('Y.m.d', strtotime($day)), $r['ngo'] ?? 'egy szervezet', number_format_i18n($don, 2)),
        'don'  => $don,
      ];
    }
  }
  // legnagyobb adományok elől, vágjuk limitre
  usort($items, fn($a,$b)=> ($b['don'] <=> $a['don']) ?: strcmp($b['when'],$a['when']));
  $items = array_slice($items, 0, $limit);

  ob_start(); ?>
  <div class="ims-activity" style="font:14px/1.5 system-ui">
    <ul style="margin:0;padding-left:18px">
      <?php if (!$items): ?>
        <li style="color:#777">Még nincs megjeleníthető aktivitás.</li>
      <?php else: foreach ($items as $row): ?>
        <li><?php echo esc_html($row['text']); ?></li>
      <?php endforeach; endif; ?>
    </ul>
  </div>
 
 add_action('init', function(){
  if (function_exists('ims_ticker'))       add_shortcode('impact_ticker', 'ims_ticker');
  if (function_exists('ims_leaderboard'))  add_shortcode('impact_leaderboard', 'ims_leaderboard');
  if (function_exists('ims_activity'))     add_shortcode('impact_activity', 'ims_activity');
});

add_action('init', function(){
  if (function_exists('ims_ticker'))       add_shortcode('impact_ticker', 'ims_ticker');
  if (function_exists('ims_leaderboard'))  add_shortcode('impact_leaderboard', 'ims_leaderboard');
  if (function_exists('ims_activity'))     add_shortcode('impact_activity', 'ims_activity');
});

  <?php
  return ob_get_clean();
});