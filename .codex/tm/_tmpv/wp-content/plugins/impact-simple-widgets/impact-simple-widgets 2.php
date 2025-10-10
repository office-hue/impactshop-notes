<?php
/**
 * Plugin Name: Impact Simple Widgets (toplista + aktivitás, riport adatokból)
 * Description: Három shortcode: [impact_top], [impact_activity], [impact_sum]. Elsődlegesen a már létező riport-adatlekérést (impactshop_dognet_fetch_transactions) használja, így nem kell új API-útvonalakat babrálni.
 * Version:     1.0.0
 * Author:      Sharity
 */

if (!defined('ABSPATH')) exit;

/* =========================================================
 * 0) Apró utilok (NEM ütköznek más pluginekkel – isw_ prefix)
 * ========================================================= */
function isw_num($v){ return is_numeric($v) ? floatval($v) : 0.0; }
function isw_pick($row, $cands){
  foreach ($cands as $k){
    if (isset($row[$k]) && $row[$k] !== '' && !is_array($row[$k])) return (string)$row[$k];
  }
  return '';
}

/* =========================================================
 * 1) Adatforrás – először a riport plugin függvényét használjuk,
 *    ha van. Ha nincs, fallback: Dognet API (zárt névtérrel).
 * ========================================================= */
function isw_fetch_transactions(array $args){
  // Várt $args: from(YYYY-MM-DD), to(YYYY-MM-DD), status(pending|approved|rejected|all)
  $from = $args['from'] ?? date('Y-m-01');
  $to   = $args['to']   ?? date('Y-m-d');
  $status = strtolower($args['status'] ?? 'approved');

  // 1/a) Ha a riport plugin segédfüggvénye elérhető, azt használjuk
  if (function_exists('impactshop_dognet_fetch_transactions')) {
    $q = [
      'date_from' => $from,
      'date_to'   => $to,
    ];
    if (in_array($status, ['approved','pending','rejected'], true)) {
      $q['status'] = $status;
    }
    $resp = impactshop_dognet_fetch_transactions($q);
    if (is_array($resp) && isset($resp['items'])) return $resp['items'];
    return []; // hiba esetén üres
  }

  // 1/b) Fallback: közvetlen Dognet hívás (zárt, ütközésmentes)
  if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
  if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL','');
  if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD','');
  if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN','');
  if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);

  $tok = DOGNET_API_TOKEN;
  if (!$tok){
    $t = get_transient('isw_dognet_tok');
    if ($t){ $tok = $t; }
    else{
      // kétféle content-type-ot próbálunk
      $login_try = function($payload, $headers){
        $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
        $r = wp_remote_post($ep, ['timeout'=>20,'headers'=>$headers,'body'=>$payload]);
        if (is_wp_error($r)) return '';
        $code = wp_remote_retrieve_response_code($r);
        $j = json_decode(wp_remote_retrieve_body($r), true);
        if ($code<200 || $code>=300 || !is_array($j)) return '';
        foreach (['token','access_token','data','result'] as $k){
          if (($k==='data'||$k==='result') && !empty($j[$k]['token'])) return $j[$k]['token'];
          if (($k==='data'||$k==='result') && !empty($j[$k]['access_token'])) return $j[$k]['access_token'];
          if (!empty($j[$k]) && is_string($j[$k])) return $j[$k];
        }
        return '';
      };
      $tok = $login_try(wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]), ['Content-Type'=>'application/json','Accept'=>'application/json']);
      if (!$tok){
        $tok = $login_try(http_build_query(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]), ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']);
      }
      if ($tok){ set_transient('isw_dognet_tok', $tok, DOGNET_TOKEN_TTL); }
    }
  }

  if (!$tok) return [];

  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [ ['created_at'=>['gte'=>$fromDt]], ['created_at'=>['lte'=>$toDt]] ];
  if (in_array($status,['approved','pending','rejected'],true)){
    $map = ['approved'=>'A','pending'=>'P','rejected'=>'D'];
    $filter[] = ['rstatus'=>['in'=>[$map[$status]]]];
  }

  $items=[]; $lastId=null;
  for($i=0;$i<50;$i++){
    $body=['per-page'=>200,'filter'=>$filter]; if($lastId!==null) $body['last_id']=intval($lastId);
    $url = rtrim(DOGNET_API_BASE,'/').'/raw-transactions/filter';
    $r = wp_remote_post($url, [
      'timeout'=>25,
      'headers'=>['Authorization'=>'Bearer '.$tok,'Accept'=>'application/json','Content-Type'=>'application/json'],
      'body'=>wp_json_encode($body),
    ]);
    if (is_wp_error($r)) break;
    $code = wp_remote_retrieve_response_code($r);
    $j = json_decode(wp_remote_retrieve_body($r), true);
    if ($code===401){ delete_transient('isw_dognet_tok'); break; }
    if ($code<200 || $code>=300 || !is_array($j)) break;
    $rows = $j['data'] ?? ($j['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $j['meta']['last_id'] ?? null;
    if ($lastId===null) break;
  }
  return $items;
}

/* =========================================================
 * 2) Aggregálás (NGO / shop)
 * ========================================================= */
function isw_aggregate(array $rows, $tab='ngo'){
  $out=[];
  foreach ($rows as $r){
    // összeg: publisher_commission | commission | payout
    $amount = isw_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    if (!$amount) continue;

    if ($tab==='shop'){
      // kampány név/helyettesítő
      $cid = 0; $name = '(ismeretlen shop)';
      foreach (['campaign','program','merchant','shop'] as $k){
        if (!empty($r[$k])) { $name = is_array($r[$k]) ? ($r[$k]['name'] ?? $name) : $r[$k]; break; }
      }
    } else {
      // NGO = data1/subid variáció
      $name = isw_pick($r, ['data1','d1','subid','sub_id','sub_id1','ref1']) ?: '(ismeretlen)';
    }

    $out[$name] = ($out[$name] ?? 0) + $amount;
  }
  $rows=[];
  foreach ($out as $name=>$amount){ $rows[]=['name'=>$name,'amount'=>$amount]; }
  usort($rows, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));
  return $rows;
}

/* =========================================================
 * 3) Shortcode-k
 *    a) [impact_top tab="ngo|shop" from="YYYY-MM-DD" to="YYYY-MM-DD" status="approved|pending|rejected|all" limit="10"]
 *    b) [impact_activity from="..." to="..." limit="10" status="approved"]
 *    c) [impact_sum ngo="kód" shop="részlet" from="..." to="..." status="approved"]
 * ========================================================= */

/** a) Toplista */
add_shortcode('impact_top', function($atts){
  $a = shortcode_atts([
    'tab'    => 'ngo',
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',
    'limit'  => 10,
  ], $atts, 'impact_top');

  $rows = isw_fetch_transactions(['from'=>$a['from'],'to'=>$a['to'],'status'=>$a['status']]);
  $agg  = isw_aggregate($rows, $a['tab']);
  $lim  = max(1, intval($a['limit']));
  $agg  = array_slice($agg, 0, $lim);

  ob_start(); ?>
  <div class="impact-top" data-tab="<?php echo esc_attr($a['tab']); ?>">
    <ol>
      <?php foreach ($agg as $r): ?>
        <li><span class="name"><?php echo esc_html($r['name']); ?></span>
            <span class="amt"><?php echo esc_html(number_format($r['amount'], 2, ',', ' ')); ?> €</span></li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php
  return ob_get_clean();
});

/** b) Aktivitás – a megjegyzésed szerint az „élő” feed valójában az approved múltbeli tételekből készülhet; default: status=approved */
add_shortcode('impact_activity', function($atts){
  $a = shortcode_atts([
    'from'   => date('Y-m-d', strtotime('-7 days')),
    'to'     => date('Y-m-d'),
    'status' => 'approved',
    'limit'  => 10,
  ], $atts, 'impact_activity');

  $rows = isw_fetch_transactions(['from'=>$a['from'],'to'=>$a['to'],'status'=>$a['status']]);
  // legfrissebbek elöl
  usort($rows, function($x,$y){
    $dx = strtotime($x['created_at'] ?? ($x['created'] ?? ''));
    $dy = strtotime($y['created_at'] ?? ($y['created'] ?? ''));
    return $dy <=> $dx;
  });
  $rows = array_slice($rows, 0, max(1,intval($a['limit'])));

  ob_start(); ?>
  <div class="impact-activity">
    <ul>
      <?php foreach ($rows as $r):
        $ngo = isw_pick($r, ['data1','d1','subid','sub_id','sub_id1','ref1']) ?: 'egy szervezet';
        $amt = isw_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
        $dt  = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,16);
      ?>
        <li><?php echo esc_html($dt); ?> — <?php echo esc_html($ngo); ?> ~ <?php echo esc_html(number_format($amt, 2, ',', ' ')); ?> €</li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php
  return ob_get_clean();
});

/** c) Egy konkrét szűrés összegzése (pl. NGO=xy) */
add_shortcode('impact_sum', function($atts){
  $a = shortcode_atts([
    'ngo'    => '',    // data1/subid
    'shop'   => '',    // kampány/merchant szövegrész (contains)
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',
  ], $atts, 'impact_sum');

  $rows = isw_fetch_transactions(['from'=>$a['from'],'to'=>$a['to'],'status'=>$a['status']]);

  $sum = 0.0; $cnt=0;
  foreach ($rows as $r){
    if ($a['ngo']){
      $d1 = isw_pick($r, ['data1','d1','subid','sub_id','sub_id1','ref1']);
      if (strtolower(trim($d1)) !== strtolower(trim($a['ngo']))) continue;
    }
    if ($a['shop']){
      $shopTxt = '';
      foreach (['campaign','program','merchant','shop'] as $k){
        if (!empty($r[$k])) { $shopTxt = is_array($r[$k])?($r[$k]['name'] ?? ''):$r[$k]; break; }
      }
      if (stripos($shopTxt, $a['shop']) === false) continue;
    }
    $sum += isw_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $cnt++;
  }

  return '<span class="impact-sum" data-cnt="'.esc_attr($cnt).'">'.esc_html(number_format($sum, 2, ',', ' ')).' €</span>';
});

/* =========================================================
 * 4) Egyszerű CSS (opcionális)
 * ========================================================= */
add_action('wp_enqueue_scripts', function(){
  $css = '.impact-top ol{list-style:decimal;margin:0;padding-left:1.25rem}
          .impact-top li{display:flex;justify-content:space-between;padding:.25rem 0;border-bottom:1px solid #eee}
          .impact-activity ul{margin:0;padding-left:1rem}
          .impact-activity li{padding:.25rem 0;border-bottom:1px dashed #eee}';
  wp_register_style('impact-simple-widgets', false, [], '1.0.0');
  wp_add_inline_style('impact-simple-widgets', $css);
  wp_enqueue_style('impact-simple-widgets');
});