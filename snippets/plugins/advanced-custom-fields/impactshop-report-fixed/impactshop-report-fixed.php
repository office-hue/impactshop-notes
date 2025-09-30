<?php
/**
 * Plugin Name: ImpactShop Report – Fixed
 * Description: Javított [impactshop_report] aggregálás: status-aliasok, értelmes NGO (data1) felismerés, nullás sorok szűrése, adomány = jutalék 50%.
 * Version: 1.0.1
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ---- Státusz aliasok ---- */
function isrf_map_status($s){
  $s = strtolower(trim($s));
  $map = [
    'minden'=>'all','osszes'=>'all','összes'=>'all',
    'jóváhagyott'=>'approved','jovahagyott'=>'approved',
    'folyamatban'=>'pending','elutasított'=>'rejected','elutasitott'=>'rejected',
  ];
  return $map[$s] ?? $s;
}

/* ---- Rekurzív értékkereső (bármelyik kulcsban lehet a data1) ---- */
function isrf_walk_values($arr, callable $cb){
  if (is_array($arr)) {
    foreach ($arr as $k=>$v) {
      if (is_array($v)) isrf_walk_values($v,$cb);
      else $cb($k,$v);
    }
  }
}

/* ---- NGO kiválasztás: slug-szerű előnyben; SOHA ne legyen csupán szám/float ---- */
function isrf_pick_ngo_from_row($row){
  $cands = [];
  $keys  = ['data1','d1','ref1','sub_id','subid','sub_id1','ngo','affiliate_subid','aff_sub'];
  isrf_walk_values($row, function($k,$v) use (&$cands,$keys){
    if (!in_array(strtolower((string)$k), $keys, true)) return;
    if (is_array($v)) return;
    $v = trim((string)$v);
    if ($v !== '') $cands[] = $v;
  });
  // 1) slug-szerű (betű is legyen benne)
  foreach ($cands as $v) {
    if (preg_match('~^[a-z0-9._-]{3,}$~i',$v) && preg_match('~[a-z]~i',$v)) return strtolower($v);
  }
  // 2) bármi nem-szám
  foreach ($cands as $v) {
    if (!preg_match('~^\d+(?:[.,]\d+)?$~',$v)) return $v;
  }
  return '(nincs d1)';
}

/* ---- NGO-szűrés: rekurzívan keressük az egyezést a teljes sorban ---- */
function isrf_row_has_ngo($row, $wanted){
  if (!$wanted) return true;
  $wanted = strtolower(trim($wanted));
  $hit = false;
  $keys = ['data1','d1','ref1','sub_id','subid','sub_id1','ngo','affiliate_subid','aff_sub'];
  isrf_walk_values($row, function($k,$v) use(&$hit,$wanted,$keys){
    if ($hit) return;
    if (!in_array(strtolower((string)$k), $keys, true)) return;
    if (is_array($v)) return;
    $vv = strtolower(trim((string)$v));
    if ($vv === $wanted) $hit = true;
  });
  return $hit;
}

/* ---- Normalizálás ---- */
function isrf_norm_row($row){
  $out = [
    'campaign_id' => 0,
    'status'      => strtolower(trim((string)($row['status'] ?? $row['rstatus'] ?? ''))),
    'currency'    => strtoupper(trim((string)($row['currency'] ?? $row['cur'] ?? ''))),
    'order_value' => 0.0,
    'commission'  => 0.0,
    'ngo'         => isrf_pick_ngo_from_row($row),
  ];
  foreach (['campaign_id','campaignId','cid','campaign'] as $k){
    if (isset($row[$k])) { $out['campaign_id'] = intval(is_array($row[$k])?($row[$k]['id']??0):$row[$k]); break; }
  }
  foreach (['order_value','sale_amount','amount','price','orderAmount','total'] as $k){
    if (isset($row[$k]) && is_numeric($row[$k])) { $out['order_value'] = floatval($row[$k]); break; }
  }
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k){
    if (isset($row[$k]) && is_numeric($row[$k])) { $out['commission'] = floatval($row[$k]); break; }
  }
  return $out;
}

/* ---- Shortcode override ---- */
add_action('init', function(){
  if (!function_exists('dognet_api_list_conversions_all') || !function_exists('impactshop_build_campaign_map')) return;

  remove_shortcode('impactshop_report');
  add_shortcode('impactshop_report', function($atts){
    $a = shortcode_atts([
      'from'=>date('Y-m-01'), 'to'=>date('Y-m-d'),
      'status'=>'all', 'group'=>'shop_ngo', 'ngo'=>'',
    ], $atts, 'impactshop_report');

    $status = isrf_map_status($a['status']);
    if (!in_array($status,['approved','pending','rejected','all'],true)) $status='all';
    $group  = in_array($a['group'],['shop_ngo','ngo','shop'],true) ? $a['group'] : 'shop_ngo';
    $ngoWanted = strtolower(trim((string)$a['ngo']));

    $res = dognet_api_list_conversions_all($a['from'],$a['to'],$status,80,200);
    if (isset($res['error']) && is_wp_error($res['error'])) {
      return '<div style="color:#b00">Dognet API hiba: '.esc_html($res['error']->get_error_message()).'</div>';
    }
    $items = $res['items'] ?? [];

    $maps = impactshop_build_campaign_map(); $byCid = $maps['by_cid'];

    $rows=[]; $grand=['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
    foreach ($items as $it){
      // NGO filter – rekurzívan
      if ($ngoWanted && !isrf_row_has_ngo($it,$ngoWanted)) continue;

      $x = isrf_norm_row($it);
      // üres tranzakciók kiszűrése
      if ($x['order_value'] <= 0 && $x['commission'] <= 0) continue;

      $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
      if (!empty($x['campaign_id']) && isset($byCid[$x['campaign_id']])) {
        $shopSlug = $byCid[$x['campaign_id']]['slug'];
        $shopName = $byCid[$x['campaign_id']]['name'];
      }

      if ($group==='ngo'){
        $key=$x['ngo'];
        if (!isset($rows[$key])) $rows[$key]=['ngo'=>$x['ngo'],'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'shops'=>[]];
        $rows[$key]['orders']+=1; $rows[$key]['order_value']+=$x['order_value']; $rows[$key]['commission']+=$x['commission'];
        $rows[$key]['shops'][$shopSlug] = ($rows[$key]['shops'][$shopSlug]??0) + $x['commission'];
      } elseif ($group==='shop'){
        $key=$shopSlug;
        if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'ngos'=>[]];
        $rows[$key]['orders']+=1; $rows[$key]['order_value']+=$x['order_value']; $rows[$key]['commission']+=$x['commission'];
        $rows[$key]['ngos'][$x['ngo']] = ($rows[$key]['ngos'][$x['ngo']]??0) + $x['commission'];
      } else {
        $key=$shopSlug.'||'.$x['ngo'];
        if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'ngo'=>$x['ngo'],'orders'=>0,'order_value'=>0.0,'commission'=>0.0];
        $rows[$key]['orders']+=1; $rows[$key]['order_value']+=$x['order_value']; $rows[$key]['commission']+=$x['commission'];
      }

      $grand['orders']+=1; $grand['order_value']+=$x['order_value']; $grand['commission']+=$x['commission'];
    }

    $rows = array_values($rows);
    usort($rows,function($a,$b){
      $d = ($b['commission']??0) <=> ($a['commission']??0);
      return $d !== 0 ? $d : strcasecmp(($a['shop_name'] ?? $a['ngo'] ?? ''), ($b['shop_name'] ?? $b['ngo'] ?? ''));
    });

    $donation = $grand['commission']/2;

    ob_start(); ?>
    <div class="impactshop-report" style="font:14px/1.5 system-ui">
      <div style="margin:8px 0 12px 0">
        <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> &nbsp; |
        <b>Státusz:</b> <?php echo esc_html($status); ?> &nbsp; |
        <b>Bontás:</b> <?php echo esc_html($group); ?><?php if ($ngoWanted): ?> &nbsp; | <b>NGO:</b> <?php echo esc_html($ngoWanted); ?><?php endif; ?>
      </div>
      <div style="overflow:auto">
        <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
          <thead>
            <tr style="background:#f6f7f8">
              <?php if ($group==='ngo'): ?>
                <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Szervezet (data1)</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
              <?php elseif ($group==='shop'): ?>
                <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Webshop</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
              <?php else: ?>
                <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Webshop</th>
                <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Szervezet (data1)</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="5" style="padding:10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 8px;color:#666">Nincs adat az adott szűrésre.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <?php if ($group==='ngo'): ?>
                  <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['ngo']); ?></td>
                  <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                  <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],2,',',' '); ?></td>
                  <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],2,',',' '); ?></td>
                <?php elseif ($group==='shop'): ?>
                  <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name'].' ('.$r['shop_slug'].')'); ?></td>
                  <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                  <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],2,',',' '); ?></td>
                  <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],2,',',' '); ?></td>
                <?php else: ?>
                  <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
                  <td style="padding:8px 10px"><?php echo esc_html($r['ngo']); ?></td>
                  <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                  <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],2,',',' '); ?></td>
                  <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],2,',',' '); ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr style="background:#fbfbfc">
              <?php if ($group==='ngo'): ?>
                <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],2,',',' '); ?></th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],2,',',' '); ?></th>
              <?php elseif ($group==='shop'): ?>
                <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],2,',',' '); ?></th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],2,',',' '); ?></th>
              <?php else: ?>
                <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],2,',',' '); ?></th>
                <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],2,',',' '); ?></th>
              <?php endif; ?>
            </tr>
          </tfoot>
        </table>
      </div>
      <div style="color:#777;margin-top:8px;font-size:12px">
        Frissítve: <?php echo esc_html( date_i18n('Y-m-d H:i:s') ); ?> · Forrás: Dognet API
        · <b>Adomány (50%): <?php echo number_format($grand['commission']/2,2,',',' '); ?></b>
      </div>
    </div>
    <?php
    return ob_get_clean();
  });
});