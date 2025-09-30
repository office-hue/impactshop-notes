<?php
/**
 * Plugin Name: Impact Local Tops (leaderboard + activity, a report adataiból)
 * Description: Két egyszerű shortcode a meglévő Impact Shop riport-lekérésre építve. Nincs külön API/bridge.
 * Version:     1.0.0
 * Author:      Sharity
 */

if (!defined('ABSPATH')) exit;

/**
 * Biztonság: kell a riport plugin fetchere. Enélkül nem futunk.
 */
function ilt__ensure_fetcher_exists() {
  return function_exists('impactshop_dognet_fetch_transactions');
}

/**
 * Alap lekérdezés: ugyanaz, mint a riportnál (date range + opcionális API státusz).
 * Vissza: normalizált tömb-elemeken belül legalább: shop, ngo(data1), commission, amount, status, date
 */
function ilt__fetch_rows($from, $to, $status = 'approved') {
  if (!ilt__ensure_fetcher_exists()) return ['items' => [], 'error' => 'Impact report fetcher hiányzik.'];

  $query = [
    'date_from' => $from,
    'date_to'   => $to,
  ];
  $st = strtolower($status);
  if (in_array($st, ['approved','pending','rejected'], true)) {
    $query['status'] = $st;
  }

  $resp = impactshop_dognet_fetch_transactions($query);
  $rows = $resp['items'] ?? [];
  if (!empty($resp['error'])) {
    return ['items' => [], 'error' => $resp['error']];
  }
  return ['items' => $rows, 'error' => null];
}

/**
 * Összegzés tab szerint (ngo|shop). commission 50%-a = adomány.
 */
function ilt__aggregate($rows, $tab = 'ngo') {
  $sum = []; // key => ['name'=>..., 'commission'=>float, 'orders'=>int]
  foreach ($rows as $r) {
    $key = ($tab === 'shop')
      ? strtolower($r['shop'] ?? 'ismeretlen')
      : strtolower($r['ngo']  ?? 'ismeretlen');

    $name = ($tab === 'shop')
      ? ($r['shop'] ?? 'ismeretlen')
      : (($r['ngo']  ?? '') ?: '(nincs d1)');

    if (!isset($sum[$key])) {
      $sum[$key] = ['name' => $name, 'commission' => 0.0, 'orders' => 0];
    }
    $sum[$key]['orders']     += 1;
    $sum[$key]['commission'] += floatval($r['commission'] ?? 0);
  }
  // rendezés jutalék szerint (desc)
  usort($sum, function($a,$b){
    return $b['commission'] <=> $a['commission'] ?: strcasecmp($a['name'],$b['name']);
  });
  return $sum;
}

/**
 * Shortcode: [impact_local_leader from="" to="" tab="ngo|shop" status="approved|pending|rejected|all" top="10"]
 */
add_shortcode('impact_local_leader', function($atts){
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'tab'    => 'ngo',
    'status' => 'approved',
    'top'    => 10,
  ], $atts, 'impact_local_leader');

  $tab    = (strtolower($a['tab']) === 'shop') ? 'shop' : 'ngo';
  $status = strtolower($a['status']);
  if (!in_array($status, ['approved','pending','rejected','all'], true)) $status = 'approved';

  $cache_key = 'ilt_leader_' . md5(json_encode([$a['from'],$a['to'],$tab,$status,(int)$a['top']]));
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $fetch = ilt__fetch_rows($a['from'], $a['to'], $status === 'all' ? '' : $status);
  if (!empty($fetch['error'])) {
    return '<div class="impact-error" style="color:#b91c1c">Hiba: '.esc_html($fetch['error']).'</div>';
  }

  $agg = ilt__aggregate($fetch['items'], $tab);
  $top = max(1, intval($a['top']));
  $slice = array_slice($agg, 0, $top);

  ob_start(); ?>
  <div class="impact-local-leader" data-tab="<?php echo esc_attr($tab); ?>">
    <div style="margin:8px 0;color:#555;font-size:14px">
      Időszak: <strong><?php echo esc_html($a['from']); ?></strong> → <strong><?php echo esc_html($a['to']); ?></strong>
      &nbsp;|&nbsp; Dimenzió: <strong><?php echo esc_html($tab); ?></strong>
      &nbsp;|&nbsp; Státusz: <strong><?php echo esc_html($status); ?></strong>
      &nbsp;|&nbsp; Megj.: adomány = jutalék 50%-a
    </div>
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:#f8fafc">
          <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb">Név</th>
          <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb">Rendelések</th>
          <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb">Jutalék (€)</th>
          <th style="text-align:right;padding:10px;border-bottom:1px solid #e5e7eb">Adomány 50% (€)</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($slice as $row): 
        $comm = floatval($row['commission']);
        $don  = $comm * 0.5; ?>
        <tr>
          <td style="padding:10px;border-bottom:1px solid #f1f5f9"><?php echo esc_html($row['name']); ?></td>
          <td style="padding:10px;text-align:right;border-bottom:1px solid #f1f5f9"><?php echo number_format_i18n($row['orders']); ?></td>
          <td style="padding:10px;text-align:right;border-bottom:1px solid #f1f5f9"><?php echo esc_html(number_format($comm, 2, ',', ' ')); ?></td>
          <td style="padding:10px;text-align:right;border-bottom:1px solid #f1f5f9"><?php echo esc_html(number_format($don, 2, ',', ' ')); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
  $html = ob_get_clean();
  set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);
  return $html;
});

/**
 * Shortcode: [impact_local_activity from="" to="" status="approved" limit="10"]
 * A legutolsó tranzakciók listája, 50% adománnyal (NGO/Shop megjelöléssel).
 */
add_shortcode('impact_local_activity', function($atts){
  $a = shortcode_atts([
    'from'  => date('Y-m-d', strtotime('-7 days')),
    'to'    => date('Y-m-d'),
    'status'=> 'approved',
    'limit' => 10,
  ], $atts, 'impact_local_activity');

  $status = strtolower($a['status']);
  if (!in_array($status, ['approved','pending','rejected','all'], true)) $status = 'approved';

  $cache_key = 'ilt_activity_' . md5(json_encode([$a['from'],$a['to'],$status,(int)$a['limit']]));
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $fetch = ilt__fetch_rows($a['from'], $a['to'], $status === 'all' ? '' : $status);
  if (!empty($fetch['error'])) {
    return '<div class="impact-error" style="color:#b91c1c">Hiba: '.esc_html($fetch['error']).'</div>';
  }

  $rows = $fetch['items'];
  usort($rows, function($x,$y){
    return strcmp($y['date'] ?? '', $x['date'] ?? '');
  });
  $rows = array_slice($rows, 0, max(1,intval($a['limit'])));

  ob_start(); ?>
  <div class="impact-local-activity">
    <ul style="list-style:none;padding:0;margin:0">
      <?php foreach ($rows as $r):
        $ngo  = trim($r['ngo'] ?? '') ?: '(nincs d1)';
        $shop = trim($r['shop'] ?? 'ismeretlen');
        $dt   = esc_html($r['date'] ?? '');
        $comm = floatval($r['commission'] ?? 0);
        $don  = $comm * 0.5; ?>
        <li style="padding:10px 0;border-bottom:1px solid #f1f5f9">
          <div style="font-weight:600"><?php echo esc_html($ngo); ?></div>
          <div style="font-size:13px;color:#64748b"><?php echo esc_html($dt); ?> · <?php echo esc_html($shop); ?></div>
          <div style="margin-top:4px">
            Jutalék: <strong><?php echo esc_html(number_format($comm,2,',',' ')); ?> €</strong>
            &nbsp;|&nbsp; Adomány (50%): <strong><?php echo esc_html(number_format($don,2,',',' ')); ?> €</strong>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php
  $html = ob_get_clean();
  set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);
  return $html;
});