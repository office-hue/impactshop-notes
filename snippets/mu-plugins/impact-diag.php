<?php
/**
 * Plugin Name: Impact Diag & Flush (v1)
 * Description: Diagnosztika + cache ürítés az Impact shortcodes-hoz, a helyes /impact/v1 végpontokkal.
 * Version:     1.1.0
 */

if (!defined('ABSPATH')) exit;

/* =========================
 * BASE csak HOST! (nincs /api, nincs /impact)
 * ========================= */
if (!defined('IMPACT_API_BASE')) {
  define('IMPACT_API_BASE', 'https://app.sharity.hu');
}

/* =========================
 * URL helper – pontosan 1× rakja rá az /impact/v1-et
 * ========================= */
if (!function_exists('impact_diag__build_url')) {
  function impact_diag__normalize_base_host() {
    $host = rtrim(IMPACT_API_BASE, '/');
    // ha véletlen maradt a végén /api, /api/v1, /impact, /impact/v1 → levágjuk
    return preg_replace('~/(api|impact)(/v1)?$~', '', $host);
  }
  function impact_diag__build_url($path) {
    $host = impact_diag__normalize_base_host();
    $p = '/' . ltrim($path, '/');
    // bármilyen duplikált impact(/v1) normalizálása egyre
    $p = preg_replace('~(/impact(/v1)?/)+~', '/impact/v1/', $p, 1);
    if (!preg_match('~^/impact/v1/~', $p)) {
      $p = '/impact/v1' . $p;
    }
    return $host . $p;
  }
}

/* =========================
 * [impact_diag] – 3 végpont teszt + rövid kivonat
 * ========================= */
add_shortcode('impact_diag', function () {
  $tests = [
    'Ticker'      => 'ticker',
    'Leaderboard' => 'leaderboard?tab=ngo',
    'Activity'    => 'activity',
  ];

  $out   = [];
  $base  = impact_diag__normalize_base_host();
  $out[] = '<div style="padding:12px;border-radius:8px;background:#0b1220;color:#cde"><b>IMPACT_API_BASE</b>: '
         . esc_html($base) . ' · <a style="color:#9f9" href="?impact_flush=1">cache flush</a></div>';

  foreach ($tests as $name => $rel) {
    $url  = impact_diag__build_url($rel);
    $resp = wp_remote_get($url, ['timeout'=>12, 'headers'=>['Accept'=>'application/json']]);

    if (is_wp_error($resp)) {
      $out[] = '<div style="padding:12px;margin-top:8px;border-radius:8px;background:#1f2937;color:#fca5a5">'
             . '<b>'.esc_html($name).'</b>: WP_Error – '.esc_html($resp->get_error_message())
             . '<br><span style="color:#9ca3af">URL: </span><code style="color:#cbd5e1">'.esc_html($url).'</code></div>';
      continue;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $is_json = json_decode($body, true) !== null;
    $snip = esc_html(mb_substr($body ?? '', 0, 280));

    $ok  = ($code === 200 && $is_json);
    $bg  = $ok ? '#0f1f13' : '#1f2937';
    $col = $ok ? '#22c55e' : '#fca5a5';

    $out[] = '<div style="padding:12px;margin-top:8px;border-radius:8px;background:'.$bg.';color:'.$col.'">'
           . '<b>'.esc_html($name).'</b>: HTTP '.intval($code).' · '.($is_json?'JSON':'nem JSON')
           . '<br><span style="color:#9ca3af">URL: </span><code style="color:#cbd5e1">'.esc_html($url).'</code>'
           . '<br><code style="display:block;margin-top:6px;color:#cbd5e1">'.$snip.'</code></div>';
  }

  // ha bármelyik nem 200/JSON, kis tipp
  if (stripos(implode('', $out), 'HTTP 200') === false) {
    $out[] = '<div style="margin-top:8px;padding:10px;border-radius:8px;background:#3b1d1d;color:#fee2e2">'
           . 'Nem jön 200+JSON válasz. Ellenőrizd, hogy a BASE csak host (pl. https://app.sharity.hu), és töröld a cache-t.</div>';
  }

  return implode('', $out);
});

/* =========================
 * [impact_flush] – Impact cache kulcsok törlése
 * ========================= */
add_shortcode('impact_flush', function () {
  delete_transient('impact_ticker_json');
  delete_transient('impact_activity');
  global $wpdb;
  $wpdb->query(
    $wpdb->prepare(
      "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
      $wpdb->esc_like('_transient_impact_leaderboard_').'%',$wpdb->esc_like('_transient_timeout_impact_leaderboard_').'%'
    )
  );
  return '<div style="background:#0f1f13;color:#22C55E;padding:.75rem 1rem;border-radius:.5rem">Impact cache ürítve. Frissíts rá.</div>';
});

/* =========================
 * Közvetlen cache-flush GET parammal (admin?)
 * ========================= */
add_action('init', function () {
  if (!is_admin() && isset($_GET['impact_flush'])) {
    echo do_shortcode('[impact_flush]');
    exit;
  }
});