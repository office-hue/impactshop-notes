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

/** Shop name by Dognet campaign id */
function ism_shop_name_from_cid($cid){
  static $map = null;
  $cid = intval($cid);
  if ($map === null) {
    $map = [];
    if (function_exists('impactshop_get_shops') && function_exists('dognet_extract_campaign_id_from_base')) {
      foreach ((array)impactshop_get_shops() as $shop) {
        $base = $shop['dognet_base'] ?? '';
        $scid = $base ? dognet_extract_campaign_id_from_base($base) : 0;
        if ($scid) {
          $map[$scid] = $shop['name'] ?? ('cid '.$scid);
        }
      }
    }
  }
  return $map[$cid] ?? ('cid '.$cid);
}

/** NGO név map (slug -> label) */
function ism_get_ngo_name_map(){
  static $map = null;
  if ($map !== null) return $map;
  $map = [];
  $path = trailingslashit(ABSPATH) . 'ngo_codes.csv';
  if (!file_exists($path)) return $map;
  $handle = fopen($path, 'r');
  if ($handle === false) return $map;
  $row = 0;
  while (($data = fgetcsv($handle)) !== false) {
    $row++;
    if ($row === 1) {
      continue;
    }
    $label = isset($data[0]) ? trim((string)$data[0]) : '';
    $slug = isset($data[1]) ? sanitize_title($data[1]) : '';
    if ($label !== '' && $slug !== '') {
      $map[$slug] = $label;
    }
  }
  fclose($handle);
  return $map;
}

/** NGO slug -> ékezetes név */
function ism_normalize_ngo_name($name){
  $name = trim((string)$name);
  if ($name === '' || $name === '—') return $name;
  $map = ism_get_ngo_name_map();
  $slug = sanitize_title($name);
  if ($slug && isset($map[$slug])) return $map[$slug];
  $fallback = str_replace(['-', '_'], ' ', $name);
  if (function_exists('mb_convert_case')) {
    return mb_convert_case($fallback, MB_CASE_TITLE, 'UTF-8');
  }
  return ucwords($fallback);
}

function ism_metrics_cache_key($from, $to, $status): string {
  return 'impactshop_metrics_raw_' . md5($from . '|' . $to . '|' . $status);
}

function ism_leaderboard_cache_key($tab, $from, $to, $status): string {
  return 'impactshop_lb_v1_' . md5($tab . '|' . $from . '|' . $to . '|' . $status);
}

function ism_leaderboard_persist_key($tab, $from, $to, $status): string {
  return 'impactshop_lb_persist_' . md5($tab . '|' . $from . '|' . $to . '|' . $status);
}

function ism_refresh_tx_cache($from, $to, $status): array {
  if (!function_exists('dognet_api_list_conversions_all')) return [];
  $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return ['error' => $res['error']];
  }
  $items = $res['items'] ?? [];
  $cache = ism_metrics_cache_key($from, $to, $status);
  set_transient($cache, $items, IMPACTSHOP_METRICS_RAW_TTL);
  set_transient($cache . '_stale', $items, IMPACTSHOP_METRICS_RAW_STALE_TTL);
  return $items;
}

function ism_schedule_refresh_tx_cache($from, $to, $status): void {
  $lock = 'impactshop_metrics_raw_lock_' . md5($from . '|' . $to . '|' . $status);
  if (get_transient($lock)) return;
  set_transient($lock, 1, IMPACTSHOP_METRICS_RAW_LOCK_TTL);
  wp_schedule_single_event(time() + 1, 'impactshop_metrics_refresh_raw', [$from, $to, $status]);
}

add_action('impactshop_metrics_refresh_raw', function ($from, $to, $status): void {
  ism_refresh_tx_cache($from, $to, $status);
  $lock = 'impactshop_metrics_raw_lock_' . md5($from . '|' . $to . '|' . $status);
  delete_transient($lock);
}, 10, 3);

/** RAW tranzakciók */
function ism_fetch_tx($from,$to,$status='all'){
  if (!function_exists('dognet_api_list_conversions_all')) return [];
  $cache = ism_metrics_cache_key($from, $to, $status);
  $cached = get_transient($cache);
  if (is_array($cached)) return $cached;
  $stale = get_transient($cache . '_stale');
  if (is_array($stale)) {
    ism_schedule_refresh_tx_cache($from, $to, $status);
    return $stale;
  }
  $fresh = ism_refresh_tx_cache($from, $to, $status);
  if (isset($fresh['error'])) {
    return $stale ?: [];
  }
  return $fresh;
}

if (!defined('IMPACTSHOP_LEADERBOARD_TTL')) {
  define('IMPACTSHOP_LEADERBOARD_TTL', 3600);
}

if (!defined('IMPACTSHOP_LEADERBOARD_PREWARM_INTERVAL')) {
  define('IMPACTSHOP_LEADERBOARD_PREWARM_INTERVAL', 1800);
}

if (!defined('IMPACTSHOP_METRICS_RAW_TTL')) {
  define('IMPACTSHOP_METRICS_RAW_TTL', 3600);
}

if (!defined('IMPACTSHOP_METRICS_RAW_STALE_TTL')) {
  define('IMPACTSHOP_METRICS_RAW_STALE_TTL', 21600);
}

if (!defined('IMPACTSHOP_METRICS_RAW_LOCK_TTL')) {
  define('IMPACTSHOP_METRICS_RAW_LOCK_TTL', 60);
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
if (!defined('IMPACTSHOP_TICKER_TTL')) {
  define('IMPACTSHOP_TICKER_TTL', 180);
}
if (!defined('IMPACTSHOP_TICKER_PERSIST_REFRESH_INTERVAL')) {
  define('IMPACTSHOP_TICKER_PERSIST_REFRESH_INTERVAL', 900);
}
if (!defined('IMPACTSHOP_TICKER_PERSIST_LOCK_TTL')) {
  define('IMPACTSHOP_TICKER_PERSIST_LOCK_TTL', 120);
}

function ism_compute_ticker($from, $to, $today): array {
  $rows = ism_fetch_tx($from, $to, 'all');
  $sum = 0.0;
  $todaySum = 0.0;
  foreach($rows as $r){
    $ngo=ism_pick_ngo_from_row($r); if($ngo==='') continue;
    if (ism_is_rejected($r)) continue;
    $c=ism_num($r); $sum += $c;
    $dt=substr((string)($r['created_at']??$r['created']??''),0,10);
    if ($dt===$today) $todaySum += $c;
  }
  return [
    'total' => $sum * 0.5,
    'today' => $todaySum * 0.5,
    'generated_at' => current_time('mysql')
  ];
}

function ism_schedule_refresh_ticker_persist($from, $to): void {
  $lock = 'impactshop_ticker_persist_lock';
  if (get_transient($lock)) return;
  set_transient($lock, 1, IMPACTSHOP_TICKER_PERSIST_LOCK_TTL);
  wp_schedule_single_event(time() + 1, 'impactshop_ticker_refresh_persist', [$from, $to]);
}

add_action('impactshop_ticker_refresh_persist', function ($from, $to): void {
  $today = date('Y-m-d');
  $out = ism_compute_ticker($from, $to, $today);
  set_transient('impactshop_ticker_v1', $out, IMPACTSHOP_TICKER_TTL);
  update_option('impactshop_ticker_persist_v1', ['data' => $out, 'generated_at' => current_time('mysql')], false);
  delete_transient('impactshop_ticker_persist_lock');
}, 10, 2);

function ism_build_ticker(){
  $cache='impactshop_ticker_v1';
  $c=get_transient($cache);
  if($c!==false) return $c;
  $persist_key = 'impactshop_ticker_persist_v1';
  $persist = get_option($persist_key);
  if (is_array($persist)) {
    $data = isset($persist['data']) && is_array($persist['data']) ? $persist['data'] : $persist;
    if (is_array($data)) {
      $from=defined('IMPACTSHOP_METRICS_FROM') ? IMPACTSHOP_METRICS_FROM : '2025-10-23';
      $to=date('Y-m-d');
      set_transient($cache, $data, IMPACTSHOP_TICKER_TTL);
      $generated = $persist['generated_at'] ?? $data['generated_at'] ?? null;
      $age = $generated ? (time() - strtotime((string)$generated)) : IMPACTSHOP_TICKER_PERSIST_REFRESH_INTERVAL + 1;
      if ($age > IMPACTSHOP_TICKER_PERSIST_REFRESH_INTERVAL) {
        ism_schedule_refresh_ticker_persist($from, $to);
      } else {
        ism_schedule_refresh_tx_cache($from, $to, 'all');
      }
      return $data;
    }
  }
  $from=defined('IMPACTSHOP_METRICS_FROM') ? IMPACTSHOP_METRICS_FROM : '2025-10-23';
  $to=date('Y-m-d'); $today=date('Y-m-d');
  $out = ism_compute_ticker($from, $to, $today);
  set_transient($cache, $out, IMPACTSHOP_TICKER_TTL);
  update_option($persist_key, ['data' => $out, 'generated_at' => current_time('mysql')], false);
  return $out;
}
add_action('rest_api_init',function(){
  register_rest_route('impact/v1','/ticker',[
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ return rest_ensure_response(ism_build_ticker()); }
  ]);
});

/* ======================= LEADERBOARD ======================= */
function ism_build_leaderboard($tab='ngo',$from='',$to='',$status='all'){
  $from = $from ?: (defined('IMPACTSHOP_METRICS_FROM') ? IMPACTSHOP_METRICS_FROM : '2025-10-23');
  $to = $to ?: date('Y-m-d');
  $status = $status ?: 'approved';
  $cache = ism_leaderboard_cache_key($tab, $from, $to, $status);
  $c = get_transient($cache);
  if ($c !== false) return $c;
  $persist_key = ism_leaderboard_persist_key($tab, $from, $to, $status);
  $persist = get_option($persist_key);
  if (is_array($persist)) {
    $data = isset($persist['data']) && is_array($persist['data']) ? $persist['data'] : $persist;
    if (is_array($data)) {
      // Background refresh while serving last-known-good data.
      ism_schedule_refresh_tx_cache($from, $to, $status);
      return $data;
    }
  }
  $rows = ism_fetch_tx($from, $to, $status);

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
      $name=$cid?ism_shop_name_from_cid($cid):'(ismeretlen shop)';
      $map[$name]=($map[$name]??0)+$don;
    }
  }
  $out=[]; foreach($map as $name=>$amt){ $out[]=['name'=>$name,'amount'=>$amt]; }
  if ($tab === 'ngo') {
    $out = array_map(function($row){
      $row['name'] = ism_normalize_ngo_name($row['name'] ?? '');
      return $row;
    }, $out);
  }
  usort($out, fn($a,$b)=>($b['amount']<=>$a['amount'])?:strcasecmp($a['name'],$b['name']));
  set_transient($cache, $out, IMPACTSHOP_LEADERBOARD_TTL);
  update_option($persist_key, ['data' => $out, 'generated_at' => current_time('mysql')], false);
  return $out;
}
add_action('rest_api_init',function(){
  register_rest_route('impact/v1','/leaderboard',[
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      $tab = sanitize_text_field($req->get_param('tab') ?: 'ngo');
      $from = sanitize_text_field($req->get_param('from') ?: '');
      $to = sanitize_text_field($req->get_param('to') ?: '');
      $status = sanitize_text_field($req->get_param('status') ?: 'approved');
      return rest_ensure_response(ism_build_leaderboard($tab,$from,$to,$status));
    }
  ]);
});

add_filter('cron_schedules', function (array $schedules): array {
  if (!isset($schedules['impactshop_leaderboard_prewarm'])) {
    $schedules['impactshop_leaderboard_prewarm'] = [
      'interval' => IMPACTSHOP_LEADERBOARD_PREWARM_INTERVAL,
      'display'  => 'ImpactShop leaderboard prewarm',
    ];
  }
  return $schedules;
});

add_action('init', function (): void {
  if (!wp_next_scheduled('impactshop_leaderboard_prewarm')) {
    wp_schedule_event(time() + 30, 'impactshop_leaderboard_prewarm', 'impactshop_leaderboard_prewarm');
  }
});

add_action('impactshop_leaderboard_prewarm', function (): void {
  foreach (['ngo','shop'] as $tab) {
    ism_build_leaderboard($tab,'','','approved');
  }
});

/* ======================= ACTIVITY ======================= */
function ism_build_activity(){
  $cache='impactshop_activity_v2';
  $c=get_transient($cache);
  if($c!==false) return $c;
  $persist_key = 'impactshop_activity_persist_v2';
  $persist = get_option($persist_key);
  if (is_array($persist)) {
    $data = isset($persist['data']) && is_array($persist['data']) ? $persist['data'] : $persist;
    if (is_array($data)) {
      ism_schedule_refresh_tx_cache(
        date('Y-m-d',strtotime('-14 days')),
        date('Y-m-d'),
        'all'
      );
      return $data;
    }
  }
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
  update_option($persist_key, ['data' => $out, 'generated_at' => current_time('mysql')], false);
  return $out;
}
add_action('rest_api_init',function(){
  register_rest_route('impact/v1','/activity',[
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(){ return rest_ensure_response(ism_build_activity()); }
  ]);
});
