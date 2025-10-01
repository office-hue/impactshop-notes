<?php
/**
 * Plugin Name: ImpactShop Metrics (NGO only)
 * Description: Ticker, Leaderboard és Activity – csak akkor számol, ha VAN érvényes NGO slug (d1/data1/last_click.*). Rejected (D) kizárva. Adomány = 50% jutalék.
 * Version: 1.0.2
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ======== SAJÁT, EGYEDI NEVŰ SEGÉDEK (NINCS névütközés) ======== */

/** NGO slug pick – okosított + last_click mezők */
function ism_pick_ngo_from_row($row){
  $candKeys = [
    'd1','data1','ref1','sub_id','subid','sub_id1','ngo','ngo_name',
    'last_click_data1','last_click_d1','last_click_subid','lc_data1','lc_d1','lc_subid'
  ];
  $cands = [];
  foreach ($candKeys as $k){
    if (isset($row[$k]) && !is_array($row[$k])) {
      $v = trim((string)$row[$k]);
      if ($v !== '') $cands[] = $v;
    }
  }
  if (!empty($row['last_click']) && is_array($row['last_click'])) {
    foreach (['data1','d1','subid','sub_id1','sub_id'] as $k) {
      if (!empty($row['last_click'][$k]) && is_string($row['last_click'][$k])) {
        $cands[] = trim($row['last_click'][$k]);
      }
    }
  }
  if (!$cands) return '';

  $is_num  = fn($v)=> (bool)preg_match('~^\d+(?:[.,]\d+)?$~', $v);
  $is_slug = fn($v)=> (bool)(preg_match('~^[a-z0-9._-]{3,}$~i',$v) && preg_match('~[a-z]~i',$v));
  $from_q  = function($q){ parse_str($q,$p); foreach(['d1','ngo','org','utm_term'] as $kk){ if(!empty($p[$kk])&&is_string($p[$kk])) return trim($p[$kk]); } return ''; };
  $from_j  = function($s){ $j=json_decode($s,true); if(!is_array($j)) return ''; foreach(['d1','ngo','org','data1','ref1'] as $kk){ if(!empty($j[$kk])&&is_string($j[$kk])) return trim($j[$kk]); } return ''; };

  foreach ($cands as $v) if ($is_slug($v)) return sanitize_title($v);

  foreach ($cands as $v){
    $vv = trim($v);
    if (stripos($vv,'http://')===0 || stripos($vv,'https://')===0){
      $qs = parse_url($vv, PHP_URL_QUERY);
      if ($qs){ $z=$from_q($qs); if ($is_slug($z)) return sanitize_title($z); }
    }
    if (strpos($vv,'=')!==false && strpos($vv,'&')!==false){
      $z=$from_q($vv); if ($is_slug($z)) return sanitize_title($z);
    }
    if ($vv!=='' && ($vv[0]==='{' || $vv[0]==='[')){
      $z=$from_j($vv); if ($is_slug($z)) return sanitize_title($z);
    }
  }

  foreach ($cands as $v) if (!$is_num($v)) return sanitize_title($v);
  return '';
}

/** Jutalék kinyerő */
function ism_num($row){
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k){
    if (isset($row[$k]) && is_numeric($row[$k])) return (float)$row[$k];
  }
  return 0.0;
}

/** RAW tranzakciók */
function ism_fetch_tx($from,$to,$status='all'){
  if (!function_exists('dognet_api_list_conversions_all')) return [];
  $res = dognet_api_list_conversions_all($from,$to,$status,80,200);
  return $res['items'] ?? [];
}

/** Rejected ellenőrzés */
function ism_is_rejected($row){
  $st = strtolower(trim((string)($row['status'] ?? $row['rstatus'] ?? $row['state'] ?? '')));
  return ($st==='d' || $st==='rejected');
}

/** Dátum → timestamp */
function ism_pick_ts($row){
  foreach (['created_at','createdAt','created','time','datetime'] as $k){
    if (!empty($row[$k])) { $t = strtotime((string)$row[$k]); if ($t>0) return $t; }
  }
  return 0;
}

/* ======================= TICKER ======================= */
function ism_build_ticker(){
  $cache='impactshop_ticker_v1'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d'); $today=date('Y-m-d');
  $rows=ism_fetch_tx($from,$to,'all');

  $sum=0.0; $todaySum=0.0;
  foreach($rows as $r){
    $ngo=ism_pick_ngo_from_row($r); if($ngo==='') continue;
    if (ism_is_rejected($r)) continue;
    $c=ism_num($r); $sum += $c;
    $dt=substr((string)($r['created_at']??$r['created']??''),0,10);
    if ($dt===$today) $todaySum += $c;
  }
  $out=['total'=>$sum*0.5,'today'=>$todaySum*0.5,'generated_at'=>current_time('mysql')];
  set_transient($cache,$out,180);
  return $out;
}
add_action('rest_api_init',function(){
  register_rest_route('impact/v1','/ticker',[
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ return rest_ensure_response(ism_build_ticker()); }
  ]);
});

/* ======================= LEADERBOARD ======================= */
function ism_build_leaderboard($tab='ngo'){
  $cache='impactshop_lb_v1_'.sanitize_key($tab); $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-01'); $to=date('Y-m-d'); $rows=ism_fetch_tx($from,$to,'all');

  $map=[];
  foreach($rows as $r){
    $ngo=ism_pick_ngo_from_row($r); if($ngo==='') continue;
    if (ism_is_rejected($r)) continue;
    $don=ism_num($r)*0.5;

    if ($tab==='ngo'){
      $map[$ngo]=($map[$ngo]??0)+$don;
    } else {
      $cid=0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
        if(isset($r[$k])){ $cid=is_array($r[$k])?intval($r[$k]['id']??0):intval($r[$k]); break; }
      }
      $name=$cid?('cid '.$cid):'(ismeretlen shop)';
      $map[$name]=($map[$name]??0)+$don;
    }
  }
  $out=[]; foreach($map as $name=>$amt){ $out[]=['name'=>$name,'amount'=>$amt]; }
  usort($out, fn($a,$b)=>($b['amount']<=>$a['amount'])?:strcasecmp($a['name'],$b['name']));
  set_transient($cache,$out,300);
  return $out;
}
add_action('rest_api_init',function(){
  register_rest_route('impact/v1','/leaderboard',[
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      return rest_ensure_response(ism_build_leaderboard($tab));
    }
  ]);
});

/* ======================= ACTIVITY ======================= */
function ism_build_activity(){
  $cache='impactshop_activity_v2'; $c=get_transient($cache); if($c!==false) return $c;
  $from=date('Y-m-d',strtotime('-14 days')); $to=date('Y-m-d');
  $rows=ism_fetch_tx($from,$to,'all');

  $events=[];
  foreach($rows as $r){
    $ngo=ism_pick_ngo_from_row($r); if($ngo==='') continue;
    if (ism_is_rejected($r)) continue;
    $don=ism_num($r)*0.5;
    $ts =ism_pick_ts($r);
    $events[]=[
      'ts'=>$ts,
      'text'=>sprintf('%s • %s € • %s',
              $ngo,
              number_format($don,2,',',' '),
              ($ts? date_i18n('Y-m-d H:i',$ts) : '—')
            )
    ];
  }
  usort($events, fn($a,$b)=>($b['ts']<=>$a['ts'])?:strcmp($b['text'],$a['text']));
  $out = array_map(fn($e)=>['text'=>$e['text']], array_slice($events,0,10));
  set_transient($cache,$out,120);
  return $out;
}
add_action('rest_api_init',function(){
  register_rest_route('impact/v1','/activity',[
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ return rest_ensure_response(ism_build_activity()); }
  ]);
});