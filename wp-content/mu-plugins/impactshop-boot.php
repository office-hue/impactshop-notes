<?php
/**
 * Plugin Name: ImpactShop Boot (redirect + shops CSV + Dognet link)
 * Description: /go és /go-deal végpontok, CSV shop meta, Dognet link generálás. Deeplink tisztítás Árukereső-hibához.
 * Version: 1.3.2
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);
if (!defined('IMPACTSHOP_CACHE_TTL'))  define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');

/* ============================ CSV SEGÉD ============================ */

function isb_settings() {
  return [
    'shops_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    'cache_ttl'     => IMPACTSHOP_CACHE_TTL,
  ];
}
function isb_slugify_header($s){
  $s = trim(mb_strtolower($s,'UTF-8'));
  $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i']);
  return trim(preg_replace('~[^a-z0-9]+~u','_',$s), '_');
}
function isb_fetch_csv_assoc($url,$key,$ttl){
  $c = get_transient($key); if($c!==false) return $c;
  $r = wp_remote_get($url, ['timeout'=>20]); if(is_wp_error($r)) return [];
  $b = wp_remote_retrieve_body($r); if(!$b) return [];
  if(substr($b,0,3)==="\xEF\xBB\xBF") $b=substr($b,3);
  $lines = preg_split("/\r\n|\n|\r/",$b); if(!$lines) return [];
  $delim = (substr_count($lines[0],';')>substr_count($lines[0],','))?';':',';
  $headers = array_map('isb_slugify_header', str_getcsv($lines[0],$delim));
  $rows=[];
  for($i=1;$i<count($lines);$i++){
    if($lines[$i]==='') continue;
    $cols=str_getcsv($lines[$i],$delim);
    $row=[]; foreach($headers as $ix=>$k){ $row[$k]=isset($cols[$ix])?trim($cols[$ix]):''; }
    if(implode('',$row)!=='') $rows[]=$row;
  }
  set_transient($key,$rows,$ttl); return $rows;
}
function isb_get_shops(){
  $rows=isb_fetch_csv_assoc(isb_settings()['shops_csv_url'],'impactshop_csv_shops',isb_settings()['cache_ttl']);
  $out=[];
  foreach($rows as $r){
    $name=$r['name']??($r['nev']??''); $slug=$r['shop_slug']??($r['slug']??($r['go_slug']??'')); if(!$name||!$slug) continue;
    $out[]=[
      'name'=>$name,
      'shop_slug'=>$slug,
      'dognet_base'=>$r['dognet_base']??'',
      'deeplink_param'=>($r['pdognet_deeplink_param']??($r['dognet_deeplink_param']??'url'))?:'url',
      'product_url'=>$r['product_url']??($r['homepage']??''),
    ];
  }
  return $out;
}
function isb_find_shop($slug){
  $slug=trim(strtolower($slug));
  foreach(isb_get_shops() as $s){ if(strtolower($s['shop_slug'])===$slug) return $s; }
  return null;
}

/* ====================== DOGNET API (SAJÁT NÉVVEL) ====================== */

function isb__dognet_try_login_once($endpoint,$payload,$headers){
  $resp=wp_remote_post($endpoint,['timeout'=>25,'headers'=>$headers,'body'=>$payload,'redirection'=>3]);
  if(is_wp_error($resp)) return ['ok'=>false,'why'=>$resp->get_error_message()];
  $code=wp_remote_retrieve_response_code($resp);
  $body=wp_remote_retrieve_body($resp); $j=json_decode($body,true); $tok='';
  if(is_array($j)){
    foreach(['token','access_token','data','result'] as $k){
      if($k==='data'||$k==='result'){
        if(!empty($j[$k]['token'])){$tok=$j[$k]['token'];break;}
        if(!empty($j[$k]['access_token'])){$tok=$j[$k]['access_token'];break;}
      } elseif(!empty($j[$k])&&is_string($j[$k])){$tok=$j[$k];break;}
    }
  }
  return ($code>=200&&$code<300&&$tok)?['ok'=>true,'token'=>$tok]:['ok'=>false,'why'=>'HTTP '.$code];
}
function isb_dognet_get_token($force=false){
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key='dognet_api_token_cache_v1';
  if(!$force){ $t=get_transient($key); if($t) return $t; }
  $ep=rtrim(DOGNET_API_BASE,'/').'/auth/login';
  $r=isb__dognet_try_login_once($ep, wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]), ['Content-Type'=>'application/json','Accept'=>'application/json']);
  if(!empty($r['ok'])){ set_transient($key,$r['token'],DOGNET_TOKEN_TTL); return $r['token']; }
  $r=isb__dognet_try_login_once($ep, http_build_query(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]), ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']);
  if(!empty($r['ok'])){ set_transient($key,$r['token'],DOGNET_TOKEN_TTL); return $r['token']; }
  return '';
}
function isb_dognet_api_request($method,$path,$body=null){
  $tok=isb_dognet_get_token(false); if(!$tok) return new WP_Error('no_token','Dognet token nincs');
  $url=(stripos($path,'http')===0)?$path:rtrim(DOGNET_API_BASE,'/').$path;
  $args=['timeout'=>25,'headers'=>['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json','Accept'=>'application/json'],'method'=>$method];
  if($body!==null) $args['body']=wp_json_encode($body);
  $r=wp_remote_request($url,$args); if(is_wp_error($r)) return $r;
  $code=wp_remote_retrieve_response_code($r); $j=json_decode(wp_remote_retrieve_body($r),true);
  if($code==401){ delete_transient('dognet_api_token_cache_v1'); $tok=isb_dognet_get_token(true); if(!$tok) return new WP_Error('no_token','401 + token refresh fail'); $args['headers']['Authorization']='Bearer '.$tok; $r=wp_remote_request($url,$args); if(is_wp_error($r)) return $r; $code=wp_remote_retrieve_response_code($r); $j=json_decode(wp_remote_retrieve_body($r),true); }
  if($code<200||$code>=300) return new WP_Error('api_error','Dognet API '.$code,['resp'=>$j]);
  return $j;
}
function isb_dognet_extract_campaign_id_from_base($dognet_base){
  if(!$dognet_base) return 0; $qs=parse_url($dognet_base,PHP_URL_QUERY); parse_str($qs,$p); return isset($p['cid'])?intval($p['cid']):0;
}
function isb_dognet_api_pick_ad_channel_id(){ return DOGNET_AD_CHANNEL_ID ?: 0; }
function isb_dognet_api_generate_link($campaign_id,$deeplink='',$d1='',$d2=''){
  $ad=isb_dognet_api_pick_ad_channel_id(); if(!$ad) return new WP_Error('no_channel','Nincs ad_channel');
  $body=['ad_channel_id'=>$ad,'campaign_id'=>intval($campaign_id),'url_type'=>3];
  if($deeplink) $body['url']=$deeplink;
  if($d1) $body['data1']=$d1; if($d2) $body['data2']=$d2;
  $j=isb_dognet_api_request('POST','/campaigns/links/generate',$body);
  if(is_wp_error($j)) return $j;
  foreach(['url','short_url','full_url'] as $k){ if(!empty($j[$k])) return $j[$k]; if(!empty($j['data'][$k])) return $j['data'][$k]; }
  return new WP_Error('bad_api','Ismeretlen API válasz');
}

/* ===================== DEEPLINK TISZTÍTÁS (Árukereső fix) ===================== */

function isb_clean_deeplink($u){
  if(!$u) return $u;
  $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
  $u = str_replace(['&amp;','amp%3B','%26amp%3B'], '&', $u);
  if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $u)) {
    $tmp = base64_decode($u, true);
    if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $u = $tmp;
  }
  $u = preg_replace_callback('~%25([0-9A-F]{2})~i', fn($m)=>'%'.$m[1], $u);
  $kill = ['a_bid','a_aid','a_cid','chan','data1','ref','refid'];
  $p = parse_url($u);
  if (!empty($p['query'])) {
    parse_str($p['query'], $qs);
    foreach($kill as $k){ unset($qs[$k]); }
    $u = (isset($p['scheme'])?$p['scheme'].'://':'')
       . ($p['host']??'')
       . ($p['path']??'')
       . ($qs ? ('?'.http_build_query($qs)) : '')
       . (isset($p['fragment']) ? '#'.$p['fragment'] : '');
  }
  return $u;
}

/* ===================== REDIRECT HANDLER ===================== */

function isb_q($k,$def=''){ return isset($_GET[$k])?sanitize_text_field($_GET[$k]):$def; }
function isb_error($msg){ status_header(400); wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'.esc_html($msg).'</div>','ImpactShop Boot'); }
function isb_redirect_with_propagation($url,$amb,$src){
  $add=[]; if($amb&&strpos($url,'amb=')===false)$add['amb']=$amb; if($src&&strpos($url,'src=')===false)$add['src']=$src;
  if(strpos($url,'utm_source=')===false)$add['utm_source']='sharity';
  if(strpos($url,'utm_medium=')===false)$add['utm_medium']='impactshop';
  if($add) $url.=(strpos($url,'?')===false?'?':'&').http_build_query($add);
  wp_redirect($url,307); exit;
}

function isb_handle_go($is_deal){
  $shop = isb_q('shop'); if(!$shop){ $shop=get_query_var('impactshop_slug'); }
  $ngo  = isb_q('d1'); $u=isb_q('u');
  $amb  = isb_q('amb'); $src=isb_q('src')?:'impactshop';
  if(!$shop||!$ngo) isb_error('Hiányzó paraméter (shop/d1).');

  $row = isb_find_shop($shop); if(!$row) isb_error('Ismeretlen shop: '.esc_html($shop));

  $targetUrl = $is_deal ? ($u ?: ($row['product_url'] ?? '')) : '';
  if ($targetUrl) $targetUrl = isb_clean_deeplink($targetUrl);

  $final = null; $cid = isb_dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
  if ($cid){
    $api = isb_dognet_api_generate_link($cid, $targetUrl, $ngo, '');
    if(!is_wp_error($api) && $api) $final = $api;
  }
  if(!$final){
    $base = $row['dognet_base'] ?? '';
    if($base){
      $params = ['d1'=>$ngo];
      if(!empty($targetUrl)){
        $dlParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
        $params[$dlParam] = $targetUrl;
      }
      $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($params);
    }
  }
  if(!$final) isb_error('Nem sikerült a partner linket előállítani.');
  if (function_exists('impactshop_log_event')) {
    $parts = parse_url($final);
    $targetHost = $parts['host'] ?? '';
    $pseudo = isset($_COOKIE['impactshop_pseudo_id']) ? sanitize_text_field(wp_unslash($_COOKIE['impactshop_pseudo_id'])) : '';
    impactshop_log_event('go_click', [
      'event_source' => $is_deal ? 'go_deal' : 'go',
      'ngo_slug' => $ngo,
      'shop_slug' => $shop,
      'network' => 'dognet',
      'meta' => [
        'target_host' => $targetHost,
        'final_url' => $final,
        'src' => $src,
      ],
      'pseudo_id' => $pseudo,
    ]);
  }
  isb_redirect_with_propagation($final,$amb,$src);
}

/* ===================== REWRITE / HOOKOK – csak ha nincs nagy snippet ===================== */

add_action('init', function(){
  // ha a big snippet már kezeli, a Boot nem állít be saját routingot
  if (function_exists('impactshop_handle_go')) return;

  add_rewrite_rule('^go/([^/]+)/?$','index.php?impactshop_go=1&impactshop_slug=$matches[1]','top');
  add_rewrite_rule('^go/?$','index.php?impactshop_go=1','top');
  add_rewrite_rule('^go-deal/([^/]+)/?$','index.php?impactshop_deal=1&impactshop_slug=$matches[1]','top');
  add_rewrite_rule('^go-deal/?$','index.php?impactshop_deal=1','top');

  add_filter('query_vars', function($vars){ $vars[]='impactshop_go'; $vars[]='impactshop_deal'; $vars[]='impactshop_slug'; return $vars; });

  add_action('template_redirect', function(){
    if(get_query_var('impactshop_go')){ isb_handle_go(false); exit; }
    if(get_query_var('impactshop_deal')){ isb_handle_go(true); exit; }
  });
});

/* ===================== KÉZI FLUSH (opcionális) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) flush_rewrite_rules();
});
