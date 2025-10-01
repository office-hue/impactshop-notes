<?php
/**
 * Plugin Name: Impact Diag & Flush (Sharity)
 * Description: Diagnosztika és cache ürítés az Impact shortcodes-hoz, a fő plugin érintése nélkül.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

add_shortcode('impact_diag', function () {
  if (!defined('IMPACT_API_BASE')) define('IMPACT_API_BASE', 'https://app.sharity.hu/api');
  $tests = [
    'Ticker'      => '/impact/ticker',
    'Leaderboard' => '/impact/leaderboard?tab=ngo',
    'Activity'    => '/impact/activity',
  ];
  $out = [];
  $out[] = '<div style="padding:12px;border-radius:8px;background:#0b1220;color:#cde"><b>IMPACT_API_BASE</b>: '.esc_html(IMPACT_API_BASE).'</div>';
  foreach ($tests as $name => $path) {
    $url  = trailingslashit(IMPACT_API_BASE) . ltrim($path,'/');
    $resp = wp_remote_get($url, ['timeout'=>12, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($resp)) {
      $out[] = '<div style="padding:12px;margin-top:8px;border-radius:8px;background:#111827;color:#fca5a5"><b>'
             . esc_html($name).'</b>: WP_Error – '.esc_html($resp->get_error_message())
             . '<br>URL: <code>'.esc_html($url).'</code></div>';
      continue;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $snip = esc_html(mb_substr($body ?? '', 0, 280));
    $ok   = ($code===200 && json_decode($body, true)!==null);
    $bg   = $ok ? '#0f1f13' : '#1f2937';
    $col  = $ok ? '#22c55e' : '#fca5a5';
    $out[] = '<div style="padding:12px;margin-top:8px;border-radius:8px;background:'.$bg.';color:'.$col.'">'
           . '<b>'.esc_html($name).'</b>: HTTP '.intval($code)
           . '<br><span style="color:#9ca3af">URL: </span><code style="color:#cbd5e1">'.esc_html($url).'</code>'
           . '<br><code style="display:block;margin-top:6px;color:#cbd5e1">'.$snip.'</code></div>';
  }
  return implode('', $out);
});

add_shortcode('impact_flush', function () {
  // ismert kulcsok
  delete_transient('impact_ticker_json');
  delete_transient('impact_activity');
  // leaderboard_* törlés prefix alapján
  global $wpdb;
  $wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_impact_leaderboard_').'%',$wpdb->esc_like('_transient_timeout_impact_leaderboard_').'%'
  ));
  return '<div style="background:#0f1f13;color:#22C55E;padding:.75rem 1rem;border-radius:.5rem">Impact cache ürítve. Frissíts rá.</div>';
});
