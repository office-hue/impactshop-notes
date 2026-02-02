<?php
/**
 * Plugin Name: Impact Bridge Local (ticker/leaderboard/activity – WP JSON)
 * Description: Helyi REST végpontok az Impact UI-hoz. Dognetből számol: ticker/leaderboard/activity + összadomány (all-time / időszak).
 * Version: 1.3.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== HELYI CONFIG betöltés — BIZTONSÁGOS (nem engedünk kimenetet) ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (is_readable($__impact_local_cfg)) { ob_start(); include_once $__impact_local_cfg; ob_end_clean(); }

/* ===== VÉDŐSÍNEK / ALAP KONFIG ===== */
if (!defined('DOGNET_API_BASE'))          define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_LOGIN_EMAIL'))       define('DOGNET_LOGIN_EMAIL', getenv('DOGNET_LOGIN_EMAIL') ?: '');
if (!defined('DOGNET_LOGIN_PASSWORD'))    define('DOGNET_LOGIN_PASSWORD', getenv('DOGNET_LOGIN_PASSWORD') ?: '');
if (!defined('DOGNET_API_TOKEN'))         define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_TOKEN_TTL'))         define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS); // 24h alatt kényelmes
if (!defined('IMPACT_BRIDGE_USER_AGENT')) define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');
if (!defined('IMPACT_BRIDGE_TIMEOUT'))    define('IMPACT_BRIDGE_TIMEOUT', 25);

/* Kompat aliasok a régi nevekre */
if (!defined('DOGNET_EMAIL') && defined('DOGNET_LOGIN_EMAIL'))         define('DOGNET_EMAIL', DOGNET_LOGIN_EMAIL);
if (!defined('DOGNET_PASSWORD') && defined('DOGNET_LOGIN_PASSWORD'))   define('DOGNET_PASSWORD', DOGNET_LOGIN_PASSWORD);

/* ===== DOGNET auth + request ===== */
function ibl__dognet_try_login_once($payload, $headers){
  $ep = rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $headers = array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers);
  $resp = wp_remote_post($ep, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'headers'=>$headers,'body'=>$payload,'redirection'=>3,'sslverify'=>true]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);
  $j = json_decode($body, true); $tok = '';
  if (is_array($j)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k==='data' || $k==='result') {
        if (!empty($j[$k]['token']))        { $tok=$j[$k]['token']; break; }
        if (!empty($j[$k]['access_token'])) { $tok=$j[$k]['access_token']; break; }
      } elseif (!empty($j[$k]) && is_string($j[$k])) { $tok = $j[$k]; break; }
    }
  }
  return ($code>=200 && $code<300 && $tok) ? ['ok'=>true,'token'=>$tok] : ['ok'=>false,'why'=>'HTTP '.$code];
}

function ibl_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN; // fix token, ha van
  $key='ibl_dognet_tok_v1';
  if (!$force){ $t = get_transient($key); if ($t) return $t; }
  $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;
  if (!$email || !$pass) return '';

  $r = ibl__dognet_try_login_once(wp_json_encode(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/json']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  $r = ibl__dognet_try_login_once(http_build_query(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/x-www-form-urlencoded']);
  if (!empty($r['ok'])){ set_transient($key,$r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  return '';
}

function ibl_dognet_request($method,$path,$body=null){
  $tok = ibl_dognet_get_token(false);
  if (!$tok) return new WP_Error('no_token','Dognet token hiba – hiányzik az e-mail/jelszó a wp-content/impact-bridge-local.php-ban');

  $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
  $args = [
    'timeout'  => IMPACT_BRIDGE_TIMEOUT,
    'method'   => $method,
    'headers'  => [
      'Authorization' => 'Bearer '.$tok,
      'Accept'        => 'application/json',
      'Content-Type'  => 'application/json',
      'User-Agent'    => IMPACT_BRIDGE_USER_AGENT,
    ],
    'redirection'=>3, 'sslverify'=>true,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);

  $r = wp_remote_request($url,$args);
  if (is_wp_error($r)) return $r;
  $code = wp_remote_retrieve_response_code($r);
  $j    = json_decode(wp_remote_retrieve_body($r), true);

  if ($code==401){
    delete_transient('ibl_dognet_tok_v1');
    $tok=ibl_dognet_get_token(true);
    if(!$tok) return new WP_Error('no_token','401 + token refresh fail');
    $args['headers']['Authorization']='Bearer '.$tok;
    $r=wp_remote_request($url,$args);
    if (is_wp_error($r)) return $r;
    $code=wp_remote_retrieve_response_code($r);
    $j=json_decode(wp_remote_retrieve_body($r), true);
  }
  if ($code<200 || $code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}

/* ===== Segédek ===== */
function ibl__num($v){ return is_numeric($v)? floatval($v) : 0.0; }

/**
 * NGO kiválasztása (data1 / d1 / subid… / URL / key=value) — szám/JSON kidobása
 */
function ibl__pick_d1($row){
  $cands = ['d1','d2','data1','data2','ref1','sub_id','subid','sub_id1','ngo','ngo_name','note','comment'];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // key=value sorból kulcsok
    if (strpos($v,'=')!==false && strpos($v,' ')===false){
      parse_str($v,$qs);
      foreach(['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq){ if(!empty($qs[$qq])){ $v=trim((string)$qs[$qq]); break; } }
    }
    // teljes URL queryből
    if (filter_var($v, FILTER_VALIDATE_URL)){
      $parts=parse_url($v); if(!empty($parts['query'])){ parse_str($parts['query'],$qs);
        foreach(['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq){ if(!empty($qs[$qq])){ $v=trim((string)$qs[$qq]); break; } }
      }
    }

    if ($v==='' || is_numeric($v)) continue;
    $fv=ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue; // JSON-szerű
    return $v;
  }
  return '';
}

/* ===== Raw transactions / filter (last_id görgetés) ===== */
function ibl_fetch_transactions($from, $to, $status='all', $maxBatches=80, $perPage=200){
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';
  $filter = [ ['created_at'=>['gte'=>$fromDt]], ['created_at'=>['lte'=>$toDt]] ];

  // Ad channel, ha megadva
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
    $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
  }

  // Státusz – "all" = A + P (D kiszűrve már itt)
  $stat = strtolower(trim($status));
  if     ($stat === 'approved') $filter[]=['rstatus'=>['in'=>['A']]];
  elseif ($stat === 'pending')  $filter[]=['rstatus'=>['in'=>['P']]];
  elseif ($stat === 'rejected') $filter[]=['rstatus'=>['in'=>['D']]];
  else                          $filter[]=['rstatus'=>['in'=>['A','P']]]; // ALL

  $items=[]; $lastId=null;
  for($i=0;$i<$maxBatches;$i++){
    $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
    $resp = ibl_dognet_request('POST','/raw-transactions/filter',$body);
    if (is_wp_error($resp)) return $resp;
    $rows = $resp['data'] ?? ($resp['items'] ?? []);
    if (!$rows) break;
    $items = array_merge($items, $rows);
    $lastId = $resp['meta']['last_id'] ?? null;
    if ($lastId===null){
      foreach($rows as $r){
        foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); }
      }
      if ($lastId===null) break;
    }
  }
  return $items;
}

/* ===== Építők: ticker / leaderboard / activity / total ===== */

function ibl_build_ticker(){
  $cache='ibl_ticker_v2'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'all');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $total=0.0; $todaySum=0.0; $today=date('Y-m-d');
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r); if ($ngo==='') continue; // csak data1-es
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $total += $comm;
    $created = substr((string)($r['created_at'] ?? $r['created'] ?? ''),0,10);
    if ($created===$today) $todaySum += $comm;
  }
  $out=['total'=>$total*0.5, 'today'=>$todaySum*0.5, 'generated_at'=>current_time('mysql')];
  set_transient($cache,$out, 180); return $out;
}

function ibl_build_leaderboard($tab='ngo'){
  $cache='ibl_lb_v2_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'all');
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $map=[];
  if ($tab==='ngo'){
    foreach($rows as $r){
      $ngo  = ibl__pick_d1($r); if ($ngo==='') continue; // csak data1-es
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$ngo]=($map[$ngo]??0)+$don;
    }
  } else {
    foreach($rows as $r){
      $ngo = ibl__pick_d1($r); if ($ngo==='') continue;
      $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
        if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
      }
      $name = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
      $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
      $don  = $comm * 0.5;
      $map[$name]=($map[$name]??0)+$don;
    }
  }
  $out=[]; foreach($map as $name=>$amount){ $out[]=['name'=>$name,'amount'=>$amount]; }
  usort($out, fn($a,$b)=> ($b['amount']<=>$a['amount']) ?: strcasecmp($a['name'],$b['name']));
  set_transient($cache,$out, 300); return $out;
}

function ibl_build_activity(){
  $cache='ibl_activity_v2'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d', strtotime('-14 days')); $to=date('Y-m-d');
  $rows = ibl_fetch_transactions($from,$to,'all', 10, 100);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  // csak data1-es + legújabb elöl
  $rows = array_values(array_filter($rows, function($r){ return ibl__pick_d1($r) !== ''; }));
  usort($rows, function($a,$b){
    $da = strtotime($a['created_at'] ?? ($a['created'] ?? ''));
    $db = strtotime($b['created_at'] ?? ($b['created'] ?? ''));
    return $db <=> $da;
  });
  $rows = array_slice($rows,0,10);

  $out=[];
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r);
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don  = $comm * 0.5;
    $out[]=['text'=> sprintf('%s támogatást hozott ~%s € adományban', $ngo, number_format($don,2,',',' '))];
  }
  set_transient($cache,$out, 120); return $out;
}

/** Összadomány (all-time vagy időszakra) — csak data1, D kizárva, adomány=0.5×commission */
function ibl_build_total($from=null,$to=null){
  $default_from = defined('IMPACTSHOP_METRICS_FROM') ? IMPACTSHOP_METRICS_FROM : '2025-10-23';
  $from = $from ?: $default_from;
  $to   = $to   ?: date('Y-m-d');
  $ckey = 'ibl_total_v1_'.md5($from.'_'.$to);
  $c=get_transient($ckey); if($c!==false) return $c;

  $rows = ibl_fetch_transactions($from,$to,'all', 40, 200);
  if (is_wp_error($rows)) return ['error'=>$rows->get_error_message()];

  $sum=0.0;
  foreach($rows as $r){
    $ngo = ibl__pick_d1($r); if ($ngo==='') continue;
    $comm = ibl__num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $sum += $comm * 0.5;
  }
  $out = ['from'=>$from,'to'=>$to,'total'=>$sum,'generated_at'=>current_time('mysql')];
  set_transient($ckey,$out, 600); return $out;
}

/* ===== REST: /wp-json/impact/v1/{ticker|leaderboard|activity|total} ===== */
add_action('rest_api_init', function(){

  register_rest_route('impact/v1','/ticker', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_ticker(); return is_array($d)? rest_ensure_response($d) : new WP_Error('err','Hiba'); }
  ]);

  register_rest_route('impact/v1','/leaderboard', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $d=ibl_build_leaderboard($tab);
      if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
      return rest_ensure_response($d);
    }
  ]);

  register_rest_route('impact/v1','/activity', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ $d=ibl_build_activity(); if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]); return rest_ensure_response($d); }
  ]);

  // Összadomány: /impact/v1/total  (alias: /impact/v1/totals)
  $total_cb = function(WP_REST_Request $req){
    $from = sanitize_text_field($req->get_param('from') ?: '');
    $to   = sanitize_text_field($req->get_param('to')   ?: '');
    $d = ibl_build_total($from ?: null, $to ?: null);
    if (isset($d['error'])) return new WP_Error('dognet_err','Dognet hiba: '.$d['error'],['status'=>502]);
    return rest_ensure_response($d);
  };
  register_rest_route('impact/v1','/total',  ['methods'=>'GET','permission_callback'=>'__return_true','callback'=>$total_cb]);
  register_rest_route('impact/v1','/totals', ['methods'=>'GET','permission_callback'=>'__return_true','callback'=>$total_cb]);
});

/* ===== Shortcode-ok az összadományhoz ===== */
add_shortcode('impact_total', function($atts){
  $d = ibl_build_total(null, null);
  if (isset($d['error'])) return '<div style="color:#b00">Hiba: '.esc_html($d['error']).'</div>';
  return '<div class="impact-total" style="font:600 20px/1.4 system-ui">Összesített adomány: '
       . esc_html(number_format($d['total'],2,',',' ')) .' €</div>';
});

add_shortcode('impact_total_range', function($atts){
  $a = shortcode_atts(['from'=>'','to'=>''], $atts);
  $d = ibl_build_total($a['from'] ?: null, $a['to'] ?: null);
  if (isset($d['error'])) return '<div style="color:#b00">Hiba: '.esc_html($d['error']).'</div>';
  $label = ($a['from']||$a['to']) ? ('Időszak: '.esc_html(($a['from']?:'…').' → '.($a['to']?:'…')).' · ') : '';
  return '<div class="impact-total" style="font:600 20px/1.4 system-ui">'.$label.'Összesített adomány: '
       . esc_html(number_format($d['total'],2,',',' ')) .' €</div>';
});
