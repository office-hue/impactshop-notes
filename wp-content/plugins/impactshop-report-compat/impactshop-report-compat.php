<?php
/**
 * Plugin Name:  ImpactShop Report Compat (channel + date + per-row debug)
 * Description:  Külön kompat réteg: csatorna fix (26081), exkluzív felső dátum, és tételes riport shortcode ([impactshop_rows ...]).
 * Version:      1.0.0
 * Author:       Sharity
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------------
 * 0) Biztonságos csatorna fix (ha még nincs beállítva máshol)
 * ------------------------------------------------------------------ */
if (!defined('DOGNET_AD_CHANNEL_ID')) {
  define('DOGNET_AD_CHANNEL_ID', 26081);
}

/* ------------------------------------------------------------------
 * 1) API host (ha nincs definiálva) – a stabil, működő publisher host
 * ------------------------------------------------------------------ */
if (!defined('DOGNET_API_BASE')) {
  define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
}

/* ------------------------------------------------------------------
 * 2) WP_HTTP_BLOCK_EXTERNAL esetén engedjük a Dognet hostot
 * ------------------------------------------------------------------ */
if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
  if (!defined('WP_ACCESSIBLE_HOSTS')) {
    define('WP_ACCESSIBLE_HOSTS', 'api.app.dognet.com');
  }
}

/* ------------------------------------------------------------------
 * 3) PONTOS időszűrés: a „to” nap felső határa exkluzív (Pdognet: Less than)
 *    A nagy snippet /raw-transactions/filter POST-ját itt finoman átírjuk:
 *    created_at.lte -> created_at.lt  ( +1 nap 00:00:00 )
 * ------------------------------------------------------------------ */
add_filter('http_request_args', function($args, $url){
  try {
    if (
      isset($args['method']) && strtoupper($args['method']) === 'POST' &&
      is_string($url) && strpos($url, '/raw-transactions/filter') !== false &&
      !empty($args['body'])
    ) {
      $body = json_decode($args['body'], true);
      if (json_last_error() === JSON_ERROR_NONE && is_array($body) && !empty($body['filter']) && is_array($body['filter'])) {
        $changed = false;
        foreach ($body['filter'] as &$cond) {
          if (isset($cond['created_at']) && is_array($cond['created_at']) && isset($cond['created_at']['lte'])) {
            $lte  = (string)$cond['created_at']['lte'];       // "YYYY-MM-DD HH:MM:SS"
            $date = substr($lte, 0, 10);                      // "YYYY-MM-DD"
            $next = date('Y-m-d', strtotime($date.' +1 day')).' 00:00:00';
            unset($cond['created_at']['lte']);
            $cond['created_at']['lt'] = $next;
            $changed = true;
          }
        }
        unset($cond);
        if ($changed) {
          $args['body'] = wp_json_encode($body);
        }
      }
    }
  } catch (\Throwable $e) {
    // csendben elnyeljük – legrosszabb esetben az eredeti kérés megy ki
  }
  return $args;
}, 20, 2);

/* ------------------------------------------------------------------
 * 4) Segédek – minimális normalizálás a tételes nézethez
 * ------------------------------------------------------------------ */
function isr__status_map($status){
  $s = strtolower(trim((string)$status));
  if ($s==='approved') return ['A'];
  if ($s==='pending' ) return ['P'];
  if ($s==='rejected') return ['D'];
  return []; // all
}
function isr__pick_data1($row){
  foreach (['d1','ref1','sub_id','subid','sub_id1','data1','Last click data1'] as $k){
    if (isset($row[$k]) && $row[$k] !== '' && !is_array($row[$k])) return (string)$row[$k];
  }
  return '';
}
function isr__extract_cid($row){
  foreach (['campaign_id','campaignId','cid','campaign'] as $k){
    if (isset($row[$k])) {
      $v = $row[$k];
      if (is_array($v) && isset($v['id'])) return intval($v['id']);
      return intval($v);
    }
  }
  return 0;
}
function isr__extract_created($row){
  foreach (['created_at','created','date'] as $k){
    if (!empty($row[$k])) return substr((string)$row[$k],0,19);
  }
  return '';
}
function isr__extract_commission($row){
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k){
    if (isset($row[$k]) && is_numeric($row[$k])) return (float)$row[$k];
  }
  return 0.0;
}
function isr__extract_currency($row){
  foreach (['currency','cur'] as $k){
    if (!empty($row[$k])) return strtoupper((string)$row[$k]);
  }
  return '';
}

/* ------------------------------------------------------------------
 * 5) Tételes riport shortcode: [impactshop_rows ...]
 *    Paramok: from, to, status=(all|pending|approved|rejected), ngo=""
 * ------------------------------------------------------------------ */
add_shortcode('impactshop_rows', function($atts){
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'pending',
    'ngo'    => '',
  ], $atts, 'impactshop_rows');

  // Időablak (exkluzív felső határ)
  $fromDt = $a['from'].' 00:00:00';
  $toNext = date('Y-m-d', strtotime($a['to'].' +1 day')).' 00:00:00';

  // Szűrők összeállítása
  $filter = [
    ['created_at' => ['gte' => $fromDt]],
    ['created_at' => ['lt'  => $toNext]],
  ];
  $rstat = isr__status_map($a['status']);
  if ($rstat) $filter[] = ['rstatus' => ['in' => $rstat]];
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID) {
    $filter[] = ['ad_channel_id' => ['eq' => intval(DOGNET_AD_CHANNEL_ID)]];
  }
  $ngo_filter = strtolower(trim($a['ngo']));

  // cid → shop name map (ha a nagy snippet elérhető, felhasználjuk)
  $shop_by_cid = [];
  if (function_exists('impactshop_build_campaign_map')) {
    $maps = impactshop_build_campaign_map();
    if (is_array($maps) && !empty($maps['by_cid'])) $shop_by_cid = $maps['by_cid'];
  }

  // Lapozás last_id-vel
  $items = []; $lastId = null; $batches=0;
  while ($batches < 80) {
    $body = ['per-page'=>200, 'filter'=>$filter];
    if ($lastId !== null) $body['last_id'] = intval($lastId);

    // a nagy snippet dognet_api_request() függvényét használjuk
    if (!function_exists('dognet_api_request')) {
      return '<div style="color:#b00">Hiányzik a Dognet API kliens (dognet_api_request).</div>';
    }
    $resp = dognet_api_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) {
      return '<div style="color:#b00">Dognet API hiba: '.esc_html($resp->get_error_message()).'</div>';
    }

    $rows = [];
    if (isset($resp['data']) && is_array($resp['data']))       $rows = $resp['data'];
    elseif (isset($resp['items']) && is_array($resp['items'])) $rows = $resp['items'];

    if (!$rows) break;
    $items = array_merge($items, $rows);

    $lastId = null;
    if (isset($resp['meta']['last_id'])) {
      $lastId = intval($resp['meta']['last_id']);
    } else {
      // fallback: legnagyobb id keresése
      foreach ($rows as $r) {
        foreach (['id','transaction_id','tid'] as $k) {
          if (isset($r[$k]) && is_numeric($r[$k])) {
            $lastId = max((int)$lastId, (int)$r[$k]);
          }
        }
      }
    }
    if ($lastId === null) break;
    $batches++;
  }

  // Normalizálás + opcionális NGO-szűrés
  $norm = [];
  foreach ($items as $r) {
    $cid  = isr__extract_cid($r);
    $created = isr__extract_created($r);
    $ngo  = isr__pick_data1($r);
    if ($ngo_filter && strtolower($ngo) !== $ngo_filter) continue;

    $comm = isr__extract_commission($r);
    $cur  = isr__extract_currency($r);
    $shop = '(ismeretlen shop)';
    if ($cid && isset($shop_by_cid[$cid])) $shop = $shop_by_cid[$cid]['name'].' ('.$shop_by_cid[$cid]['slug'].')';
    elseif ($cid) $shop = 'cid '.$cid;

    $norm[] = [
      'created'=>$created, 'shop'=>$shop, 'cid'=>$cid,
      'ngo'=>$ngo ?: '—', 'commission'=>$comm, 'currency'=>$cur
    ];
  }

  // Rendezés dátum szerint
  usort($norm, fn($a,$b)=> strcmp($a['created'],$b['created']));

  // Összesítő
  $sum = 0.0; foreach ($norm as $n) { $sum += $n['commission']; }

  ob_start(); ?>
  <div class="impactshop-rows" style="font:14px/1.5 system-ui">
    <div style="margin:6px 0 10px 0;color:#555">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?>
      &nbsp;|&nbsp; <b>Státusz:</b> <?php echo esc_html($a['status']); ?>
      <?php if ($ngo_filter): ?>&nbsp;|&nbsp;<b>NGO:</b> <?php echo esc_html($ngo_filter); ?><?php endif; ?>
      &nbsp;|&nbsp;<b>Csatorna:</b> <?php echo defined('DOGNET_AD_CHANNEL_ID')? intval(DOGNET_AD_CHANNEL_ID) : 0; ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:collapse;width:100%;min-width:760px">
        <thead>
          <tr style="background:#f6f7f8">
            <th style="text-align:left;padding:8px;border:1px solid #e6e8ea">Dátum</th>
            <th style="text-align:left;padding:8px;border:1px solid #e6e8ea">Webshop</th>
            <th style="text-align:left;padding:8px;border:1px solid #e6e8ea">Szervezet (data1)</th>
            <th style="text-align:right;padding:8px;border:1px solid #e6e8ea">Jutalék</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$norm): ?>
            <tr><td colspan="4" style="padding:10px;border:1px solid #e6e8ea;color:#666">Nincs tranzakció.</td></tr>
          <?php else: foreach ($norm as $n): ?>
            <tr>
              <td style="padding:8px;border:1px solid #e6e8ea"><?php echo esc_html($n['created']); ?></td>
              <td style="padding:8px;border:1px solid #e6e8ea"><?php echo esc_html($n['shop']); ?></td>
              <td style="padding:8px;border:1px solid #e6e8ea"><?php echo esc_html($n['ngo']); ?></td>
              <td style="padding:8px;border:1px solid #e6e8ea;text-align:right"><?php echo number_format($n['commission'], 2, ',', ' '); ?> <?php echo esc_html($n['currency'] ?: '€'); ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <th colspan="3" style="text-align:left;padding:8px;border:1px solid #e6e8ea">Összesen (sorok: <?php echo count($norm); ?>)</th>
            <th style="text-align:right;padding:8px;border:1px solid #e6e8ea"><?php echo number_format($sum, 2, ',', ' '); ?> €</th>
          </tr>
        </tfoot>
      </table>
    </div>
    <div style="margin-top:6px;color:#888;font-size:12px">
      Forrás: Dognet API · Felső dátum EXKLUZÍV (Less than) · Csatorna ID: <?php echo defined('DOGNET_AD_CHANNEL_ID')? intval(DOGNET_AD_CHANNEL_ID) : 0; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
});