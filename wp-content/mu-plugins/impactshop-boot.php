<?php
/**
 * Plugin Name: ImpactShop Boot (redirect + shops CSV + Dognet link)
 * Description: /go és /go-deal végpontok, CSV shop meta, Dognet link generálás. Deeplink tisztítás Árukereső-hibához.
 * Version: 1.3.2
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ========================== PSEUDO-ID COOKIE ========================== */

if (!function_exists('impactshop_identity_normalize_pseudo')) {
  function impactshop_identity_normalize_pseudo($value){
    if (!is_scalar($value)) return '';
    $clean = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string) $value));
    if ($clean === '') return '';
    return substr($clean, 0, 12);
  }
}

if (!function_exists('impactshop_identity_generate_pseudo')) {
  function impactshop_identity_generate_pseudo($length = 12){
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
      $idx = random_int(0, $max);
      $out .= $alphabet[$idx];
    }
    return $out;
  }
}

if (!function_exists('impactshop_identity_set_pseudo_cookie')) {
  function impactshop_identity_set_pseudo_cookie($pseudo){
    if ($pseudo === '' || headers_sent()) return;

    $expires = time() + (2 * YEAR_IN_SECONDS);
    $secure = is_ssl();

    if (PHP_VERSION_ID >= 70300) {
      setcookie('impactshop_pseudo_id', $pseudo, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
      ]);
    } else {
      setcookie('impactshop_pseudo_id', $pseudo, $expires, '/; samesite=Lax', '', $secure, false);
    }

    $_COOKIE['impactshop_pseudo_id'] = $pseudo;
  }
}

if (!function_exists('impactshop_identity_ensure_pseudo_cookie')) {
  function impactshop_identity_should_touch_cookie(){
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
      return false;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
      return false;
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = strtolower((string) parse_url($uri, PHP_URL_PATH));
    if ($path === '') {
      $path = '/';
    }

    if (str_starts_with($path, '/wp-login.php') || str_starts_with($path, '/wp-json/')) {
      return false;
    }

    return true;
  }

  function impactshop_identity_ensure_pseudo_cookie(){
    if ((defined('WP_CLI') && WP_CLI) || wp_doing_cron()) {
      return;
    }

    $queryPseudo = isset($_GET['impact_pseudo_id']) ? impactshop_identity_normalize_pseudo($_GET['impact_pseudo_id']) : '';
    if ($queryPseudo !== '') {
      impactshop_identity_set_pseudo_cookie($queryPseudo);
      return;
    }

    if (!impactshop_identity_should_touch_cookie()) {
      return;
    }

    $knownSources = [
      $_COOKIE['impactshop_pseudo_id'] ?? null,
      $_COOKIE['impact_pseudo_id'] ?? null,
      $_COOKIE['impact_pseudo'] ?? null,
    ];

    foreach ($knownSources as $source) {
      $normalized = impactshop_identity_normalize_pseudo($source ?? '');
      if ($normalized !== '') {
        if (($knownSources[0] ?? '') !== $normalized) {
          impactshop_identity_set_pseudo_cookie($normalized);
        }
        return;
      }
    }

    $generated = impactshop_identity_generate_pseudo();
    impactshop_identity_set_pseudo_cookie($generated);
  }
}

add_action('init', 'impactshop_identity_ensure_pseudo_cookie', 1);

/* ====================== AKTÍV NGO (FELHASZNÁLÓ VÁLASZTÁS) ====================== */

if (!defined('IMPACTSHOP_ACTIVE_NGO_COOKIE')) {
  define('IMPACTSHOP_ACTIVE_NGO_COOKIE', 'impactshop_active_ngo');
}

if (!function_exists('impactshop_active_ngo_cookie_domain')) {
  function impactshop_active_ngo_cookie_domain() {
    if (defined('IMPACTSHOP_ACTIVE_NGO_COOKIE_DOMAIN') && IMPACTSHOP_ACTIVE_NGO_COOKIE_DOMAIN !== '') {
      return IMPACTSHOP_ACTIVE_NGO_COOKIE_DOMAIN;
    }
    $host = parse_url(home_url('/'), PHP_URL_HOST);
    if (!$host) {
      return '';
    }
    return strtolower($host);
  }
}

if (!function_exists('impactshop_active_ngo_normalize')) {
  function impactshop_active_ngo_normalize($value) {
    if (!is_scalar($value)) {
      return '';
    }
    return sanitize_title($value);
  }
}

if (!function_exists('impactshop_active_ngo_slug')) {
  /**
   * Returns the current NGO slug inferred from the query/cookie.
   *
   * @param bool $allowCookie When true, falls back to the persisted cookie if query vars are missing.
   */
  function impactshop_active_ngo_slug($allowCookie = true) {
    static $cache = ['with' => null, 'no' => null];
    $key = $allowCookie ? 'with' : 'no';
    if ($cache[$key] !== null) {
      return $cache[$key];
    }

    $candidates = [];
    foreach (['impact_ngo_slug', 'ngo', 'd1'] as $param) {
      if (function_exists('get_query_var')) {
        $value = get_query_var($param);
        if (is_string($value) && $value !== '') {
          $candidates[] = $value;
          continue;
        }
      }
      if (!empty($_GET[$param])) {
        $candidates[] = $_GET[$param];
      }
    }

    if ($allowCookie && !empty($_COOKIE[IMPACTSHOP_ACTIVE_NGO_COOKIE])) {
      $candidates[] = $_COOKIE[IMPACTSHOP_ACTIVE_NGO_COOKIE];
    }

    foreach ($candidates as $candidate) {
      $normalized = impactshop_active_ngo_normalize($candidate);
      if ($normalized !== '') {
        return $cache[$key] = $normalized;
      }
    }

    return $cache[$key] = '';
  }
}

if (!function_exists('impactshop_active_ngo_set_cookie')) {
  function impactshop_active_ngo_set_cookie($slug) {
    $slug = impactshop_active_ngo_normalize($slug);
    if ($slug === '' || headers_sent()) {
      return;
    }

    $expires = time() + (14 * DAY_IN_SECONDS);
    $secure  = is_ssl();
    $domain  = impactshop_active_ngo_cookie_domain();
    $args = [
      'expires'  => $expires,
      'path'     => '/',
      'secure'   => $secure,
      'httponly' => false,
      'samesite' => 'Lax',
    ];
    if ($domain !== '') {
      $args['domain'] = $domain;
    }

    if (PHP_VERSION_ID >= 70300) {
      setcookie(IMPACTSHOP_ACTIVE_NGO_COOKIE, $slug, $args);
    } else {
      $legacyDomain = $domain !== '' ? $domain : '';
      setcookie(IMPACTSHOP_ACTIVE_NGO_COOKIE, $slug, $expires, '/; samesite=Lax', $legacyDomain, $secure, false);
    }

    $_COOKIE[IMPACTSHOP_ACTIVE_NGO_COOKIE] = $slug;
  }
}

if (!function_exists('impactshop_active_ngo_capture_from_request')) {
  function impactshop_active_ngo_capture_from_request() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
      return;
    }
    $slug = impactshop_active_ngo_slug(false);
    if ($slug === '') {
      return;
    }
    $current = impactshop_active_ngo_normalize($_COOKIE[IMPACTSHOP_ACTIVE_NGO_COOKIE] ?? '');
    if ($current === $slug) {
      return;
    }
    impactshop_active_ngo_set_cookie($slug);
  }
}

add_action('template_redirect', 'impactshop_active_ngo_capture_from_request', 2);

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
  $seen=[];
  foreach($rows as $r){
    $name=$r['name']??($r['nev']??''); $slug=$r['shop_slug']??($r['slug']??($r['go_slug']??'')); if(!$name||!$slug) continue;
    $defaultD1='';
    foreach (['default_d1','ngo_slug','ngo','default_ngo'] as $dKey) {
      if (!empty($r[$dKey]) && is_string($r[$dKey])) {
        $defaultD1 = sanitize_title($r[$dKey]);
        break;
      }
    }
    $seen[strtolower($slug)] = true;
    $out[]=[
      'name'=>$name,
      'shop_slug'=>$slug,
      'dognet_base'=>$r['dognet_base']??'',
      'deeplink_param'=>($r['pdognet_deeplink_param']??($r['dognet_deeplink_param']??'url'))?:'url',
      'product_url'=>$r['product_url']??($r['homepage']??''),
      'site'=>$r['product_url']??($r['homepage']??''),
      'default_d1'=>$defaultD1,
      'network_source'=>$r['network_source']??'',
    ];
  }
  $cj = get_option('impactshop_cj_shops', []);
  if (is_array($cj) && $cj) {
    foreach ($cj as $shop) {
      if (($shop['status'] ?? '') !== 'joined') {
        continue;
      }
      $slug = trim((string)($shop['slug'] ?? ''));
      if ($slug === '' || isset($seen[strtolower($slug)])) {
        continue;
      }
      $seen[strtolower($slug)] = true;
      $out[] = [
        'name'          => $shop['name'] ?? '',
        'shop_slug'     => $slug,
        'dognet_base'   => '',
        'deeplink_param'=> 'url',
        'product_url'   => $shop['program_url'] ?? '',
        'site'          => $shop['program_url'] ?? '',
        'default_d1'    => '',
        'network_source'=> 'cj',
        'cj_advertiser_id' => $shop['advertiser_id'] ?? '',
      ];
    }
  }
  if (function_exists('sib_cj_products_index')) {
    foreach (sib_cj_products_index() as $slug => $product) {
      $slug = sib_slug($slug);
      if ($slug === '' || isset($seen[strtolower($slug)])) {
        continue;
      }
      $url = $product['link'] ?? ($product['product_url'] ?? '');
      $seen[strtolower($slug)] = true;
      $out[] = [
        'name'          => $product['advertiser_name'] ?? $slug,
        'shop_slug'     => $slug,
        'dognet_base'   => '',
        'deeplink_param'=> 'url',
        'product_url'   => $url,
        'site'          => $url,
        'default_d1'    => '',
        'network_source'=> 'cj',
        'cj_advertiser_id' => $product['advertiser_id'] ?? '',
      ];
    }
  }
  return $out;
}
function isb_find_shop($slug){
  $slug=trim(strtolower($slug));
  foreach(isb_get_shops() as $s){ if(strtolower($s['shop_slug'])===$slug) return $s; }
  return null;
}

function isb_cj_links_index(){
  static $cache=null;
  if($cache!==null) return $cache;
  $cache=[];
  $links = get_option('impactshop_cj_links', []);
  if (is_array($links)) {
    foreach ($links as $link) {
      $id = trim((string)($link['advertiser_id'] ?? ''));
      if ($id === '') continue;
      $cache[$id][] = $link;
    }
  }
  return $cache;
}
function isb_cj_pick_link($advertiserId){
  $map = isb_cj_links_index();
  if (empty($map[$advertiserId])) return null;
  $best = null; $bestScore = -1;
  foreach ($map[$advertiserId] as $link) {
    $score = 0;
    if (!empty($link['coupon_code'])) $score += 5;
    if (!empty($link['is_coupon'])) $score += 2;
    if (!empty($link['price'])) $score += 3;
    if (!empty($link['promotion_type']) && strtolower($link['promotion_type']) !== 'n/a') $score += 1;
    if ($best === null || $score > $bestScore) {
      $best = $link;
      $bestScore = $score;
    }
  }
  return $best;
}
function isb_cj_generate_click_url($slug, $ngo, $pseudo, $targetUrl='', &$sidOut=null){
  if (stripos($slug, 'cj-') !== 0) return '';
  $advertiserId = preg_replace('~[^0-9]+~', '', substr($slug, 3));
  if ($advertiserId === '') return '';
  $link = isb_cj_pick_link($advertiserId);
  if (!$link) return '';
  $sidParts = [];
  $ngoSlug = sanitize_title($ngo ?: $slug);
  if ($ngoSlug !== '') $sidParts[] = $ngoSlug;
  if ($pseudo !== '') $sidParts[] = strtoupper(substr(preg_replace('~[^A-Za-z0-9]~','', $pseudo), 0, 12));
  $sid = $sidParts ? implode('~', $sidParts) : $slug;
  $sidOut = $sid;
  $url = $link['click_url'] ?? '';
  if ($url === '') $url = $link['destination'] ?? '';
  if ($url === '') return '';
  if (strpos($url, '{{SID}}') !== false) {
    $url = str_replace('{{SID}}', rawurlencode($sid), $url);
  } else {
    $url = add_query_arg('sid', $sid, $url);
  }
  if ($targetUrl !== '') {
    $url = add_query_arg('url', $targetUrl, $url);
  }
  return $url;
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
function isb_dognet_api_generate_link($campaign_id,$deeplink='',$d1='',$d2='',$pseudo=''){
  $ad=isb_dognet_api_pick_ad_channel_id(); if(!$ad) return new WP_Error('no_channel','Nincs ad_channel');
  $body=['ad_channel_id'=>$ad,'campaign_id'=>intval($campaign_id),'url_type'=>3];
  if($deeplink) $body['url']=$deeplink;
  if($d1) $body['data1']=$d1; if($d2) $body['data2']=$d2;
  if($pseudo) $body['data5']=$pseudo;
  $j=isb_dognet_api_request('POST','/campaigns/links/generate',$body);
  if(is_wp_error($j)) return $j;
  foreach(['url','short_url','full_url'] as $k){ if(!empty($j[$k])) return $j[$k]; if(!empty($j['data'][$k])) return $j['data'][$k]; }
  return new WP_Error('bad_api','Ismeretlen API válasz');
}

/* ===================== DEEPLINK TISZTÍTÁS (Árukereső fix) ===================== */

function isb_clean_deeplink($u){
  if(!$u) return $u;
  // Fix &#038; and bare #038; (browser-decoded from &#038;) BEFORE parse_url,
  // because # is treated as fragment separator, breaking query string.
  // &#038; → & (full entity), then leftover bare #038; → & (partial decode)
  $u = preg_replace('~&#0*38;~', '&', $u);
  $u = preg_replace('~(?<!&)#0*38;~', '&', $u);
  $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $u = str_replace(['&amp;','amp%3B','%26amp%3B','%23038%3B'], '&', $u);
  if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $u)) {
    $tmp = base64_decode($u, true);
    if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $u = $tmp;
  } elseif (preg_match('~^[A-Za-z0-9_-]+={0,2}$~', $u)) {
    $normalized = strtr($u, '-_', '+/');
    $padding = strlen($normalized) % 4;
    if ($padding !== 0) {
      $normalized .= str_repeat('=', 4 - $padding);
    }
    $tmp = base64_decode($normalized, true);
    if ($tmp !== false && preg_match('~^https?://~i', $tmp)) {
      $u = $tmp;
    }
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

// Allow affiliate domains for wp_safe_redirect
add_filter('allowed_redirect_hosts', function ($hosts) {
    $affiliate_hosts = [
        'api.app.dognet.com',
        'app.dognet.com',
        'www.dognet.com',
        'dognet.com',
        'www.anrdoezrs.net',       // CJ
        'www.dpbolvw.net',          // CJ
        'www.jdoqocy.com',          // CJ
        'www.kqzyfj.com',           // CJ
        'www.tkqlhce.com',          // CJ
    ];
    return array_merge($hosts, $affiliate_hosts);
});

function isb_q($k,$def=''){ return isset($_GET[$k])?sanitize_text_field($_GET[$k]):$def; }
function isb_error($msg){ status_header(400); wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'.esc_html($msg).'</div>','ImpactShop Boot'); }
function isb_redirect_with_propagation($url,$amb,$src){
  $add=[];
  if($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
  if($src && strpos($url,'src=')===false) $add['src']=$src;
  if(strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
  if(strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
  if($add) $url.=(strpos($url,'?')===false?'?':'&').http_build_query($add);
  wp_safe_redirect($url,307); exit;
}

function isb_resolve_default_d1($shopSlug){
  $shopSlug = sanitize_title($shopSlug);
  if($shopSlug==='') return '';

  if(function_exists('sib_default_d1')){
    $candidate = (string) sib_default_d1($shopSlug, true);
    if($candidate!==''){
      return sanitize_title($candidate);
    }
  }

  $row = isb_find_shop($shopSlug);
  if($row && !empty($row['default_d1'])){
    return sanitize_title($row['default_d1']);
  }

  return '';
}

function isb_log_go_click($data){
  $payload = [
    'ts'          => gmdate('c'),
    'shop'        => sanitize_title($data['shop'] ?? ''),
    'ngo'         => sanitize_title($data['ngo'] ?? ''),
    'sid'         => isset($data['sid']) ? substr((string)$data['sid'], 0, 120) : '',
    'is_cj'       => !empty($data['is_cj']) ? 1 : 0,
    'pseudo'      => isset($data['pseudo']) ? substr(preg_replace('~[^A-Za-z0-9]~', '', (string)$data['pseudo']), 0, 12) : '',
    'target_host' => sanitize_text_field($data['target_host'] ?? ''),
  ];
  $line = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
  if (!$line) {
    return;
  }
  $file = WP_CONTENT_DIR . '/uploads/impactshop-go-clicks.log';
  $dir = dirname($file);
  if (!is_dir($dir)) {
    wp_mkdir_p($dir);
  }
  @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

  if (function_exists('impactshop_analytics_log_event')) {
    $clid = '';
    foreach (['fbclid', 'gclid', 'ttclid', 'dclid'] as $key) {
      if (!empty($_COOKIE[$key])) {
        $clid = sanitize_text_field((string) $_COOKIE[$key]);
        break;
      }
    }
    $params = [
      'shop_slug' => $payload['shop'],
      'ngo_slug' => $payload['ngo'],
      'clid' => $clid,
      'fbp' => isset($_COOKIE['_fbp']) ? sanitize_text_field((string) $_COOKIE['_fbp']) : '',
      'fbc' => isset($_COOKIE['_fbclid']) ? sanitize_text_field((string) $_COOKIE['_fbclid']) : '',
    ];
    impactshop_analytics_log_event('go_click', $params);
  }
}

function isb_shop_from_referer(){
  if (empty($_SERVER['HTTP_REFERER'])) {
    return '';
  }
  $ref = wp_parse_url((string) $_SERVER['HTTP_REFERER']);
  if (empty($ref['query'])) {
    return '';
  }
  parse_str($ref['query'], $qs);
  if (!empty($qs['shop'])) {
    return sanitize_title($qs['shop']);
  }
  return '';
}

function isb_handle_go($is_deal){
  $shop = isb_q('shop'); if(!$shop){ $shop=get_query_var('impactshop_slug'); }
  $ngo  = isb_q('d1');
  if(!$ngo){ $ngo = isb_q('ngo'); }
  $u=isb_q('u');
  $amb  = isb_q('amb'); $src=isb_q('src')?:'impactshop';
  if(!$shop){
    $shop = isb_shop_from_referer();
  }
  if(!$shop){
    if($ngo){
      $target = add_query_arg(
        [
          'ngo' => sanitize_title($ngo),
          'missing_shop' => 1,
        ],
        home_url('/impactshop/')
      );
      wp_safe_redirect($target, 302);
      exit;
    }
    isb_error('Hiányzó paraméter (shop).');
  }

  if(!$ngo){
    $shopParam = isb_q('shop');
    $slugForDefault = $shop ?: $shopParam;
    $ngo = isb_resolve_default_d1($slugForDefault);
  }
  if(!$ngo) isb_error('Hiányzó paraméter (d1).');

  $row = isb_find_shop($shop);
  if (!$row && stripos($shop, 'cj-') === 0) {
    $advertiserId = preg_replace('~[^0-9]+~', '', substr($shop, 3));
    $link = $advertiserId ? isb_cj_pick_link($advertiserId) : null;
    if ($link) {
      $row = [
        'shop_slug' => $shop,
        'dognet_base' => '',
        'deeplink_param' => 'url',
        'product_url' => $link['destination'] ?? '',
        'network_source' => 'cj',
        'cj_advertiser_id' => $advertiserId,
      ];
      error_log(sprintf(
        'ISB-CJ-FALLBACK: shop=%s advertiser=%s',
        esc_html($shop),
        esc_html($advertiserId)
      ));
    }
  }
  if (!$row) {
    error_log(sprintf(
      'ISB-GO-ERROR: shop=%s referer=%s pseudo=%s ip=%s',
      esc_html($shop),
      esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
      sanitize_text_field($_COOKIE['impactshop_pseudo_id'] ?? ''),
      $_SERVER['REMOTE_ADDR'] ?? ''
    ));
    isb_error('Ismeretlen shop: '.esc_html($shop));
  }

  $pseudo = '';
  foreach (['impactshop_pseudo_id','impact_pseudo_id','impact_pseudo'] as $cookieKey) {
    if (!empty($_COOKIE[$cookieKey]) && is_string($_COOKIE[$cookieKey])) {
      $pseudo = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', $_COOKIE[$cookieKey]));
      if ($pseudo !== '') {
        $pseudo = substr($pseudo, 0, 12);
        break;
      }
    }
  }

  $targetUrl = $is_deal ? ($u ?: ($row['product_url'] ?? '')) : '';
  if ($targetUrl) {
    $targetUrl = isb_clean_deeplink($targetUrl);
    // HOTFIX: ha a $targetUrl egy Fillout link (dupla beágyazás), 
    // akkor bontsuk ki belőle az eredeti 'u' (base64) paramétert.
    if (strpos($targetUrl, 'form.fillout.com') !== false) {
      $parsed_q = parse_url($targetUrl, PHP_URL_QUERY);
      if ($parsed_q) {
        parse_str($parsed_q, $fq);
        if (!empty($fq['u'])) {
          $decoded_u = isb_clean_deeplink($fq['u']);
          if ($decoded_u && filter_var($decoded_u, FILTER_VALIDATE_URL)) {
            $targetUrl = $decoded_u;
          }
        }
      }
    }
  }

  $final = null;
  $sidForLog = '';

  // Ha a targetUrl már egy Dognet affiliate link, ne csomagoljuk újra —
  // a Dognet API elutasítja a go.dognet.com host-ot ("Custom URL host does not match").
  if ($targetUrl && preg_match('~(^|\.)dognet\.(com|sk|hu)$~i', parse_url($targetUrl, PHP_URL_HOST) ?: '')) {
    $sep = (strpos($targetUrl, '?') === false) ? '?' : '&';
    $extra = [];
    if ($ngo) $extra['d1'] = $ngo;
    if ($pseudo) $extra['data5'] = $pseudo;
    $final = $extra ? $targetUrl . $sep . http_build_query($extra) : $targetUrl;
  }

  $isCj = (stripos($shop, 'cj-') === 0) || (($row['network_source'] ?? '') === 'cj');
  if (!$final) {
    if ($isCj) {
      $final = isb_cj_generate_click_url($shop, $ngo, $pseudo, $targetUrl, $sidForLog);
      if (!$final && !empty($row['product_url'])) {
        $final = $row['product_url'];
      }
    } else {
      $cid = isb_dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
      if ($cid){
        $api = isb_dognet_api_generate_link($cid, $targetUrl, $ngo, '', $pseudo);
        if(!is_wp_error($api) && $api) $final = $api;
      }
      if(!$final){
        $base = $row['dognet_base'] ?? '';
        if($base){
          $params = ['d1'=>$ngo];
          if(!empty($targetUrl)){
            $dlParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
            // DOGNET elvárás: a kampány URL-t normál (URL-encoded) formában adjuk át, NEM base64-ben.
            $params[$dlParam] = $targetUrl;
          }
          if ($pseudo) {
            $params['data5'] = $pseudo;
          }
          $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($params);
        }
      }
    }
  }
  if(!$final) isb_error('Nem sikerült a partner linket előállítani.');
  isb_log_go_click([
    'shop' => $shop,
    'ngo' => $ngo,
    'sid' => $sidForLog,
    'is_cj' => $isCj,
    'pseudo' => $pseudo,
    'target_host' => parse_url($final, PHP_URL_HOST) ?? '',
  ]);
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
    $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('~^/kozossegi-kihivas/?$~', $path)) {
      $target = home_url('/cegeknek/');
      $query = $_SERVER['QUERY_STRING'] ?? '';
      if ($query !== '') {
        $target .= (strpos($target, '?') === false ? '?' : '&') . $query;
      }
      wp_safe_redirect($target, 301);
      exit;
    }
    if(get_query_var('impactshop_go')){ isb_handle_go(false); exit; }
    if(get_query_var('impactshop_deal')){ isb_handle_go(true); exit; }
    if(strpos($path, '/go-deal') === 0 && isset($_GET['shop'])){ isb_handle_go(true); exit; }
    if(strpos($path, '/go') === 0 && isset($_GET['shop'])){ isb_handle_go(false); exit; }

    $normalized = trim($path, '/');
    if ($normalized !== '') {
      $segments = array_values(array_filter(explode('/', $normalized), 'strlen'));
      $ngoIndex = array_search('ngo', array_map('strtolower', $segments), true);
      if ($ngoIndex !== false && isset($segments[$ngoIndex + 1])) {
        $slug = sanitize_title($segments[$ngoIndex + 1]);
        if ($slug !== '') {
          $prefix = $ngoIndex > 0 ? implode('/', array_slice($segments, 0, $ngoIndex)) : '';
          $target = '/' . ($prefix !== '' ? $prefix . '/' : '') . 'go/' . rawurlencode($slug);
          $query = $_SERVER['QUERY_STRING'] ?? '';
          if ($query !== '') {
            $target .= (strpos($target, '?') === false ? '?' : '&') . $query;
          }
          wp_safe_redirect($target, 301);
          exit;
        }
      }
    }
  });
});

/* ===================== KÉZI FLUSH (opcionális) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) flush_rewrite_rules();
});
