<?php
/**
 * ImpactShop PROBE (MU) — nyers diagnosztika
 * Shortcode: [impactshop_probe]
 * Megmutatja: aktív backend függvények, csatorna, dátumablak,
 *             Bridge Local nyers sorok száma, A/P szűrés számai,
 *             data1 felismert érték (Fillout -> data1/d1, v. ibl__pick_d1()).
 */

if (!defined('ABSPATH')) exit;

add_shortcode('impactshop_probe', function($atts){
  $from = date('Y-m-01');
  $to   = date('Y-m-d');
  $chan = defined('DOGNET_AD_CHANNEL_ID') ? DOGNET_AD_CHANNEL_ID : '(nincs definiálva)';
  $has_ibl_fetch = function_exists('ibl_fetch_transactions') ? 'igen' : 'nem';
  $has_pick_d1   = function_exists('ibl__pick_d1') ? 'igen' : 'nem';
  $has_report    = function_exists('impactshop_report_query') ? 'igen' : 'nem';

  // próbáljunk lekérni pár oldalt a Bridge-ből
  $rows = [];
  $err  = '';
  if (function_exists('ibl_fetch_transactions')) {
    $res = ibl_fetch_transactions($from, $to, 'all', 5, 100); // gyors próba
    if (is_wp_error($res)) { $err = $res->get_error_message(); }
    else { $rows = (array)$res; }
  } elseif (function_exists('impactshop_report_query')) {
    try {
      $rows = impactshop_report_query([
        'from'=>$from, 'to'=>$to, 'ad_channel'=>'26081', 'raw'=>true, 'limit'=>500
      ]);
      $rows = is_array($rows) ? $rows : [];
    } catch (\Throwable $e) { $err = $e->getMessage(); }
  } else {
    $err = 'Nincs elérhető backend (Bridge Local vagy Report MVP).';
  }

  // számlálók
  $tot = count($rows);
  $ap  = 0;   // approved+pending
  $d1c = 0;   // data1 felismert sorok (akár Fillout->data1/d1, akár ibl__pick_d1)
  $samples = [];

  foreach ($rows as $r) {
    $status = strtolower(trim($r['status'] ?? $r['rstatus'] ?? ''));
    if ($status!=='approved' && $status!=='pending') continue;
    $ap++;

    // 1) ha van explicit data1/d1/ngo mező → azt használjuk
    $d1 = '';
    foreach (['data1','d1','ngo','ngo_slug','ngo_name'] as $k){
      if (!empty($r[$k]) && is_string($r[$k])) { $d1 = trim((string)$r[$k]); break; }
    }
    // 2) különben próbáljuk a Bridge helperét
    if ($d1==='' && function_exists('ibl__pick_d1')) {
      $d1 = trim((string)ibl__pick_d1($r));
    }

    if ($d1!=='') $d1c++;

    if (count($samples) < 8) {
      $samples[] = [
        'created' => (string)($r['created_at'] ?? $r['created'] ?? $r['date'] ?? ''),
        'shop'    => (string)($r['shop_name'] ?? $r['shop'] ?? $r['program'] ?? ''),
        'status'  => $status,
        'commission' => (string)($r['publisher_commission'] ?? $r['commission'] ?? $r['payout'] ?? ''),
        'data1_seen' => $d1 !== '' ? $d1 : '(nincs)',
      ];
    }
  }

  // HTML
  ob_start();
  ?>
  <div style="font:14px/1.5 system-ui; padding:.5rem; border:1px solid #eee; border-radius:12px;">
    <div><strong>Dátumablak:</strong> <?=esc_html($from)?> → <?=esc_html($to)?></div>
    <div><strong>Csatorna (DOGNET_AD_CHANNEL_ID):</strong> <?=esc_html((string)$chan)?></div>
    <div><strong>Bridge Local elérhető (ibl_fetch_transactions):</strong> <?=$has_ibl_fetch?></div>
    <div><strong>Bridge d1-kivonat (ibl__pick_d1):</strong> <?=$has_pick_d1?></div>
    <div><strong>Report MVP elérhető:</strong> <?=$has_report?></div>
    <?php if ($err): ?>
      <div style="color:#b00"><strong>Hiba:</strong> <?=esc_html($err)?></div>
    <?php endif; ?>
    <hr>
    <div><strong>Nyers sorok összesen:</strong> <?=$tot?></div>
    <div><strong>Approved + Pending a nyersben:</strong> <?=$ap?></div>
    <div><strong>Data1 felismerve (Fillout v. ibl__pick_d1):</strong> <?=$d1c?></div>
    <hr>
    <div><strong>Minták (max 8):</strong></div>
    <table style="width:100%; border-collapse:collapse;">
      <tr><th style="text-align:left;border-bottom:1px solid #eee;">Dátum</th>
          <th style="text-align:left;border-bottom:1px solid #eee;">Shop</th>
          <th style="text-align:left;border-bottom:1px solid #eee;">Státusz</th>
          <th style="text-align:left;border-bottom:1px solid #eee;">Jutalék</th>
          <th style="text-align:left;border-bottom:1px solid #eee;">data1 (felismert)</th></tr>
      <?php foreach($samples as $s): ?>
        <tr>
          <td style="border-bottom:1px solid #f4f4f4;"><?=esc_html($s['created'])?></td>
          <td style="border-bottom:1px solid #f4f4f4;"><?=esc_html($s['shop'])?></td>
          <td style="border-bottom:1px solid #f4f4f4;"><?=esc_html($s['status'])?></td>
          <td style="border-bottom:1px solid #f4f4f4;"><?=esc_html($s['commission'])?></td>
          <td style="border-bottom:1px solid #f4f4f4;"><?=esc_html($s['data1_seen'])?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php
  return ob_get_clean();
});