Átugrás a tartalomra
Ezt mondtad:
Ez az utolsó jól működő snippet, amiben sajnos a pdognet token nem jó. Ezt már megoldottad, de a javítottban szétesett minden, ezért most csak és kizárólag a token megoldásra fókuszálj. Egyben kérem javítottat: /**
 * Impact Shop – ÖSSZEVONT SNIPPET (Dognet API auto-login + Redirect + UI + Banner highlight)
 * + ELSZÁMOLÁSOK (konverziók riport: shortcode + REST)
 *
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag],
 *               [impactshop_debug], [impactshop_report]
 * Endpontok:    /go és /go-deal   (slugot is elfogad: /go/{shop_slug})
 * REST:         /wp-json/impactshop/v1/totals?from=YYYY-MM-DD&to=YYYY-MM-DD&status=approved|pending|rejected|all
 * Megjegyzés:   aktiválás után egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', ''); // üres → auto-login (24h token)
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 0); // 0: első "Web Site" jellegű csatorna
if (!defined('IMPACTSHOP_CACHE_TTL'))  define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS); // biztonsági ráhagyás
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');

/* ============================== BEÁLLÍTÁS ============================== */

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    // Banners lap (gid=328401803) – a &v= számot emeld cache-törléshez
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=4',
    'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
    'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
  ];
}

/* ============================ CSV SEGÉDEK ============================ */

function impactshop_slugify_header($s) {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
  $s = strtr($s, $map);
  $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
  return trim($s, '_');
}

function impactshop_fetch_csv_assoc($url, $cache_key, $ttl) {
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $resp = wp_remote_get($url, ['timeout'=>20]);
  if (is_wp_error($resp)) return [];
  $body = wp_remote_retrieve_body($resp);
  if (!$body) return [];

  if (substr($body,0,3) === "\xEF\xBB\xBF") $body = substr($body,3);
  $lines = preg_split("/\r\n|\n|\r/", $body);
  if (!$lines || count($lines) < 1) return [];

  $first = $lines[0];
  $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

  $headers_raw = str_getcsv($first, $delim);
  $headers = array_map('impactshop_slugify_header', $headers_raw);

  $rows = [];
  for ($i=1; $i<count($lines); $i++) {
    if ($lines[$i] === '' || $lines[$i] === false) continue;
    $cols = str_getcsv($lines[$i], $delim);
    if (count($cols) == 1 && $cols[0] === null) continue;
    $row = [];
    foreach ($headers as $idx=>$key) {
      $row[$key] = isset($cols[$idx]) ? trim($cols[$idx]) : '';
    }
    if (implode('', $row) === '') continue;
    $rows[] = $row;
  }

  set_transient($cache_key, $rows, $ttl);
  return $rows;
}

/* ======================= SHOPS & BANNERS ====================== */

function impactshop_get_shops_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
}
function impactshop_get_banners_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
}

function impactshop_get_shops() {
  $rows = impactshop_get_shops_raw();
  $out = [];
  foreach ($rows as $r) {
    $name   = $r['name'] ?? ($r['nev'] ?? '');
    $slug   = $r['shop_slug'] ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
    $cat    = $r['category'] ?? ($r['kategoria'] ?? 'Egyéb');
    $logo   = $r['logo_url'] ?? ($r['logo'] ?? ($r['image'] ?? ''));
    if (!$name || !$slug) continue;
    $out[] = [
      'name'           => $name,
      'shop_slug'      => $slug,
      'category'       => $cat ?: 'Egyéb',
      'logo'           => $logo,
      'dognet_base'    => $r['dognet_base'] ?? '',
      'deeplink_param' => ($r['pdognet_deeplink_param'] ?? ($r['dognet_deeplink_param'] ?? 'url')) ?: 'url',
      'product_url'    => $r['product_url'] ?? ($r['homepage'] ?? ''),
      'commission_min' => $r['commission_min'] ?? '',
      'commission_max' => $r['commission_max'] ?? '',
      'deals_feed'     => $r['deals_feed'] ?? '',
    ];
  }
  return $out;
}

function impactshop_get_banners() {
  $rows = impactshop_get_banners_raw();
  $out = [];
  foreach ($rows as $r) {
    $img   = $r['img'] ?? ($r['image'] ?? ($r['banner'] ?? ($r['kep'] ?? '')));
    $href  = $r['href'] ?? ($r['url']   ?? ($r['link']   ?? ''));
    $label = $r['label']?? ($r['cimke'] ?? ($r['title']  ?? 'Banner'));
    $cat   = $r['category'] ?? ($r['kategoria'] ?? '');
    if (!$img || !$href) continue;
    $out[] = ['img'=>$img,'href'=>$href,'label'=>$label,'category'=>$cat];
  }
  return $out;
}

/* ====================== KÖZÖS SEGÉDEK ====================== */

function impactshop_q($key, $def='') {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
}
function impactshop_find_shop($slug) {
  $slug = trim(strtolower($slug));
  foreach (impactshop_get_shops() as $s) {
    if (strtolower($s['shop_slug']) === $slug) return $s;
  }
  return null;
}
function impactshop_error($msg) {
  status_header(400);
  wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'ImpactShop hiba');
}

/* ====================== DOGNET API (robusztus login + request) ====================== */

/** Belső segéd: többféle login-meghívás, token-kinyeréssel. */
function impactshop__dognet_try_login_once($endpoint, $payload, $headers) {
  $resp = wp_remote_post($endpoint, [
    'timeout'     => 25,
    'headers'     => $headers,
    'body'        => $payload,
    'redirection' => 3,
  ]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>'wp_error: '.$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);

  $json = json_decode($body, true);
  $tok  = '';

  if (is_array($json)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k === 'data' || $k === 'result') {
        if (!empty($json[$k]['token']))         { $tok = $json[$k]['token']; break; }
        if (!empty($json[$k]['access_token']))  { $tok = $json[$k]['access_token']; break; }
      } elseif (!empty($json[$k]) && is_string($json[$k])) {
        $tok = $json[$k]; break;
      }
    }
  }
  if ($code >= 200 && $code < 300 && $tok) return ['ok'=>true,'token'=>$tok,'code'=>$code];
  return ['ok'=>false,'code'=>$code,'body'=>is_string($body)?substr($body,0,400):'(nincs törzs)'];
}

/** ROBUSZTUS token-szerzés: több végpont + JSON és form-encoded fallback. Cache: transient. */
function dognet_get_token($force_refresh = false) {
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;

  // Ha WP szinten tiltva a kimenő forgalom, futás közben engedélyezzük a Dognet hostot
  if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
    $allowed = defined('WP_ACCESSIBLE_HOSTS') ? WP_ACCESSIBLE_HOSTS : '';
    if (stripos($allowed, 'api.app.dognet.com') === false) {
      @define('WP_ACCESSIBLE_HOSTS', $allowed ? ($allowed.',api.app.dognet.com') : 'api.app.dognet.com');
    }
  }

  $key = 'dognet_api_token_cache_v1';
  if (!$force_refresh) {
    $tok = get_transient($key);
    if ($tok) return $tok;
  }

  $email = DOGNET_LOGIN_EMAIL;  $pass = DOGNET_LOGIN_PASSWORD;
  $endpoints = [
    DOGNET_API_BASE.'/auth/login',
    DOGNET_API_BASE.'/publisher/login',
    DOGNET_API_BASE.'/login',
  ];

  $payload_json = wp_json_encode(['email'=>$email,'password'=>$pass]);
  $payload_form = http_build_query(['email'=>$email,'password'=>$pass]);
  $headers_json = ['Content-Type'=>'application/json','Accept'=>'application/json'];
  $headers_form = ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json'];

  foreach ($endpoints as $ep) {
    $r = impactshop__dognet_try_login_once($ep, $payload_json, $headers_json);
    if (!empty($r['ok'])) { set_transient($key, $r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
    $r = impactshop__dognet_try_login_once($ep, $payload_form, $headers_form);
    if (!empty($r['ok'])) { set_transient($key, $r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  }
  return '';
}

/** API kérés egységesen – 401-nél egyszer megpróbál új tokennel ismételni. */
function dognet_api_request($method, $path, $body=null) {
  $token = dognet_get_token(false);
  if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

  $url = (stripos($path,'http')===0 ? $path : rtrim(DOGNET_API_BASE,'/').$path);
  $args = [
    'timeout' => 25,
    'headers' => ['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json','Accept'=>'application/json'],
    'method'  => $method,
  ];
  if ($body!==null) $args['body'] = wp_json_encode($body);

  $resp = wp_remote_request($url, $args);
  if (is_wp_error($resp)) return $resp;

  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);

  if ($code == 401) {
    delete_transient('dognet_api_token_cache_v1');
    $token = dognet_get_token(true);
    if (!$token) return new WP_Error('no_token','Dognet API token frissítés sikertelen (401)');
    $args['headers']['Authorization'] = 'Bearer '.$token;
    $resp = wp_remote_request($url, $args);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
  }

  if ($code < 200 || $code >= 300) return new WP_Error('api_error','Dognet API hiba '.$code,['resp'=>$json,'code'=>$code]);
  return $json;
}

function dognet_extract_campaign_id_from_base($dognet_base) {
  if (!$dognet_base) return 0;
  $qs = parse_url($dognet_base, PHP_URL_QUERY);
  parse_str($qs,$parts);
  return isset($parts['cid']) ? intval($parts['cid']) : 0;
}

function dognet_api_pick_ad_channel_id() {
  if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
  $list = dognet_api_request('GET','/ad-channels');
  if (is_wp_error($list) || empty($list['data'])) return 0;
  foreach ($list['data'] as $c) {
    if (isset($c['type']) && stripos($c['type'],'web')!==false) return intval($c['id']);
  }
  return intval($list['data'][0]['id']);
}

function dognet_api_generate_link($campaign_id,$deeplink='',$d1='',$d2='') {
  $ad_channel_id = dognet_api_pick_ad_channel_id();
  if (!$ad_channel_id) return new WP_Error('no_channel','Nincs ad_channel');
  $body = ['ad_channel_id'=>$ad_channel_id,'campaign_id'=>intval($campaign_id),'url_type'=>3];
  if ($deeplink) $body['url']=$deeplink;
  if ($d1) $body['data1']=$d1;
  if ($d2) $body['data2']=$d2;
  $json = dognet_api_request('POST','/campaigns/links/generate',$body);
  if (is_wp_error($json)) return $json;
  foreach(['url','short_url','full_url'] as $k){
    if(!empty($json[$k])) return $json[$k];
    if(!empty($json['data'][$k])) return $json['data'][$k];
  }
  return new WP_Error('bad_api','Ismeretlen API válasz');
}

/* ==================== REDIRECTEK =================== */

function impactshop_add_rewrites() {
  add_rewrite_rule('^go/([^/]+)/?$',      'index.php?impactshop_go=1&impactshop_slug=$matches[1]',   'top');
  add_rewrite_rule('^go/?$',              'index.php?impactshop_go=1',                                'top');
  add_rewrite_rule('^go-deal/([^/]+)/?$', 'index.php?impactshop_deal=1&impactshop_slug=$matches[1]', 'top');
  add_rewrite_rule('^go-deal/?$',         'index.php?impactshop_deal=1',                              'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[]='impactshop_go';
  $vars[]='impactshop_deal';
  $vars[]='impactshop_slug';
  return $vars;
}
add_filter('query_vars','impactshop_add_query_vars');

add_action('template_redirect',function(){
  if(get_query_var('impactshop_go')){impactshop_handle_go(false);exit;}
  if(get_query_var('impactshop_deal')){impactshop_handle_go(true);exit;}
});

function impactshop_redirect_with_propagation($url,$amb,$src) {
  $add=[];
  if($amb&&strpos($url,'amb=')===false)$add['amb']=$amb;
  if($src&&strpos($url,'src=')===false)$add['src']=$src;
  if(strpos($url,'utm_source=')===false)$add['utm_source']='sharity';
  if(strpos($url,'utm_medium=')===false)$add['utm_medium']='impactshop';
  if($add)$url.=(strpos($url,'?')===false?'?':'&').http_build_query($add);
  wp_redirect($url,307);exit;
}

function impactshop_handle_go($is_deal) {
  $shop=impactshop_q('shop'); if(!$shop){ $shop = get_query_var('impactshop_slug'); }
  $ngo=impactshop_q('d1');$u=impactshop_q('u');
  $amb=impactshop_q('amb');$src=impactshop_q('src')?:'impactshop';
  if(!$shop||!$ngo)impactshop_error('Hiányzó paraméter (shop/d1).');
  $row=impactshop_find_shop($shop);if(!$row)impactshop_error('Ismeretlen shop: '.esc_html($shop));

  $targetUrl='';
  if($is_deal){ $targetUrl=$u?:($row['product_url']??''); }

  $final=null;$cid=dognet_extract_campaign_id_from_base($row['dognet_base']??'');
  if($cid){
    $deeplink=$targetUrl;
    if($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~',$deeplink)){
      $tmp=base64_decode($deeplink,true);
      if($tmp!==false && preg_match('~^https?://~i',$tmp)) $deeplink=$tmp;
    }
    $api=dognet_api_generate_link($cid,$deeplink,$ngo,'');
    if(!is_wp_error($api) && $api) $final=$api;
  }

  if(!$final){
    $base=$row['dognet_base']??'';
    if($base){
      $params=['d1'=>$ngo];
      if(!empty($targetUrl)){
        $deeplink = $targetUrl;
        if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
          $tmp = base64_decode($deeplink, true);
          if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
        }
        $deeplinkParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
        $params[$deeplinkParam] = $deeplink;
      }
      $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($params);
    }
  }

  if(!$final)impactshop_error('Nem sikerült a partner linket előállítani.');
  impactshop_redirect_with_propagation($final,$amb,$src);
}

/* ==================== SHORTCODE-OK =================== */

/** Scroller */
function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts(['category'=>'','inject_every'=>5,'speed'=>30], $atts);
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  if (!empty($a['category'])) {
    $catWanted = $a['category'];
    $shops   = array_values(array_filter($shops,   fn($s)=> strcasecmp($s['category'],$catWanted)===0 ));
    $banners = array_values(array_filter($banners, fn($b)=> empty($b['category']) || strcasecmp($b['category'],$catWanted)===0 ));
  }
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  $injectEvery = max(1, intval($a['inject_every']));
  $mixed = []; $bi = 0;
  foreach ($shops as $i=>$s) {
    $mixed[] = ['type'=>'shop','data'=>$s];
    if (($i+1)%$injectEvery===0 && $banners) {
      $mixed[] = ['type'=>'banner','data'=>$banners[$bi % count($banners)]];
      $bi++;
    }
  }
  $stream = array_merge($mixed, $mixed);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <style>
    .impactshop-scroller{overflow:hidden;width:100%;white-space:nowrap;position:relative}
    .impactshop-scroller-track{display:inline-block;white-space:nowrap;animation:impactshop-scroll linear infinite}
    @keyframes impactshop-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .impactshop-scroller:hover .impactshop-scroller-track{animation-play-state:paused}
    .impactshop-item{display:inline-block;margin-right:20px;position:relative}
    .impactshop-item.shop-item img{height:60px;width:auto}
    .impactshop-item.banner-item img{height:100px;width:auto;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .impactshop-badge{position:absolute;top:-8px;left:-8px;background:#ff3366;color:#fff;font:600 11px/1 system-ui;padding:4px 6px;border-radius:6px}
    @media (max-width:640px){ .impactshop-item.banner-item img{height:90px} }
  </style>
  <div class="impactshop-scroller">
    <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
      <?php foreach ($stream as $it): ?>
        <?php if ($it['type']==='shop'): $s=$it['data'];
          $cta = $d1
            ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($s['shop_slug'])))
            : add_query_arg(['shop'=>$s['shop_slug'],'amb'=>$amb], $fillout); ?>
          <div class="impactshop-item shop-item">
            <a href="<?php echo esc_url($cta); ?>">
              <img src="<?php echo esc_url($s['logo']); ?>" alt="<?php echo esc_attr($s['name']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php else: $b=$it['data']; ?>
          <div class="impactshop-item banner-item">
            <span class="impactshop-badge">AKCIÓ</span>
            <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
              <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_scroller','impactshop_shortcode_scroller');

/** Katalógus */
function impactshop_shortcode_catalog($atts) {
  $a = shortcode_atts(['show_tabs'=>'1','search'=>'1','per_page'=>'200'], $atts);
  $shops = impactshop_get_shops();
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  usort($shops, function($x,$y){
    $c = strcasecmp($x['category'],$y['category']);
    return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
  });

  $cats = []; foreach ($shops as $s) { $cats[$s['category']] = true; }
  $categories = array_keys($cats);
  sort($categories, SORT_NATURAL|SORT_FLAG_CASE);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <div class="impactshop-catalog">
    <?php if ($a['show_tabs']==='1'): ?>
      <ul class="impactshop-tabs" style="list-style:none;padding:0;margin:0 0 12px 0;display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
        <?php foreach ($categories as $i=>$cat): ?>
          <li data-cat="<?php echo esc_attr($cat); ?>" class="<?php echo $i===0?'active':''; ?>"
              style="cursor:pointer;padding:8px 12px;border-radius:999px;background:#f3f3f3;font-weight:600;font-size:14px;">
            <?php echo esc_html($cat); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($a['search']==='1'): ?>
      <input type="text" class="impactshop-search" placeholder="Keresés…" aria-label="Keresés"
             style="width:100%;max-width:520px;margin:0 auto 10px auto;display:block;padding:8px;border:1px solid #ddd;border-radius:8px;">
    <?php endif; ?>

    <div class="impactshop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;align-items:start;">
      <?php foreach ($shops as $shop):
        $cta = $d1
          ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])))
          : add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout); ?>
        <div class="impactshop-card" data-cat="<?php echo esc_attr($shop['category']); ?>" style="text-align:center;">
          <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
            <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                 loading="lazy" decoding="async" style="height:60px;width:auto;max-width:100%">
          </a>
          <div style="font-size:14px;font-weight:600;margin-top:6px;"><?php echo esc_html($shop['name']); ?></div>
          <?php
          $min = trim($shop['commission_min']); $max = trim($shop['commission_max']);
          $minN = is_numeric(str_replace('%','',$min)) ? floatval(str_replace('%','',$min)) : null;
          $maxN = is_numeric(str_replace('%','',$max)) ? floatval(str_replace('%','',$max)) : null;
          if ($minN !== null || $maxN !== null) {
            if ($minN !== null && $maxN !== null && $minN !== $maxN) {
              $don = ($minN/2) .'–'. ($maxN/2).'%';
            } else {
              $v = ($minN !== null) ? $minN : $maxN;
              $don = ($v/2).'%';
            }
            echo '<div style="font-size:12px;color:#666;margin-top:2px;">Várható adomány: ~'. esc_html($don) .'</div>';
          }
          ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    if(!root) return;
    var tabs = root.querySelectorAll('.impactshop-tabs li');
    var search = root.querySelector('.impactshop-search');
    var cards = root.querySelectorAll('.impactshop-card');
    function apply() {
      var term = search ? (search.value || '').toLowerCase() : '';
      var active = root.querySelector('.impactshop-tabs li.active');
      var cat = active ? active.getAttribute('data-cat') : null;
      Array.prototype.forEach.call(cards, function(card){
        var okCat = !cat || card.getAttribute('data-cat') === cat;
        var nameEl = card.querySelector('div');
        var name = nameEl ? (nameEl.textContent || '').toLowerCase() : '';
        var okQ = !term || name.indexOf(term) !== -1;
        card.style.display = (okCat && okQ) ? '' : 'none';
      });
    }
    Array.prototype.forEach.call(tabs, function(tab){
      tab.addEventListener('click', function(){
        Array.prototype.forEach.call(tabs, function(t){ t.classList.remove('active'); });
        tab.classList.add('active');
        apply();
      });
    });
    if (search) search.addEventListener('input', apply);
    apply();
  })();
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_catalog','impactshop_shortcode_catalog');

/** Diagnosztika */
function impactshop_shortcode_diag() {
  $shops = impactshop_get_shops();
  $out = '<div style="font:14px/1.5 system-ui"><h3>Impact Shop diagnosztika</h3>';
  $out .= '<p>Shops betöltve: <b>'.count($shops).'</b></p>';
  $miss = [];
  foreach ($shops as $s) {
    if (empty($s['dognet_base']))    $miss[] = $s['shop_slug'].' (dognet_base)';
    if (empty($s['deeplink_param'])) $miss[] = $s['shop_slug'].' (pdognet_deeplink_param)';
  }
  if ($miss) {
    $out .= '<p style="color:#b00">Hiányzó mezők:<br>'.esc_html(implode(', ', $miss)).'</p>';
  } else {
    $out .= '<p style="color:#070">dognet_base + deeplink_param mindenhol rendben.</p>';
  }
  $out .= '</div>';
  return $out;
}
add_shortcode('impactshop_diag','impactshop_shortcode_diag');

/** Egyszerű debug */
function impactshop_shortcode_debug() {
  $s = impactshop_settings();
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();
  $demo = [
    'img'=> home_url('/wp-content/uploads/2025/09/log.jpeg'),
    'href'=> 'https://form.fillout.com/t/eM61RLkz6jus?shop=4home&u='.rawurlencode('https://www.4home.hu/'),
    'label'=> 'Ajánlatok – 4home', 'category'=> 'Otthon'
  ];
  ob_start(); ?>
  <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;padding:12px;border-radius:8px">
Shops: <?php echo count($shops); ?> | Banners: <?php echo count($banners); ?>

shops_csv_url:  <?php echo esc_html($s['shops_csv_url']); ?>

banners_csv_url: <?php echo esc_html($s['banners_csv_url']); ?>


Minta banner: <?php echo esc_html(json_encode($demo, JSON_UNESCAPED_UNICODE)); ?>

  </pre>
  <?php return ob_get_clean();
}
add_shortcode('impactshop_debug','impactshop_shortcode_debug');

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});

/* ================================================================== */
/* ==================  E L S Z Á M O L Á S O K  (ÚJ)  ================ */
/* ================================================================== */

/** Konverziók listázása — oldalanként */
function dognet_api_list_conversions_page($from, $to, $status='all', $page=1, $perPage=200) {
  $qs = ['from'=>$from,'to'=>$to,'page'=>max(1,intval($page)),'per_page'=>max(1,min(500,intval($perPage)))];
  if ($status && $status!=='all') $qs['status']=$status;
  $path = '/conversions?'. http_build_query($qs);
  return dognet_api_request('GET', $path);
}

/** Konverziók begyűjtése minden oldalról */
function dognet_api_list_conversions_all($from, $to, $status='all', $maxPages=80, $perPage=200) {
  $all=[]; $page=1;
  for (; $page<=$maxPages; $page++) {
    $resp = dognet_api_list_conversions_page($from,$to,$status,$page,$perPage);
    if (is_wp_error($resp)) return ['error'=>$resp];
    $items=[];
    if (isset($resp['data']['items']) && is_array($resp['data']['items'])) {
      $items=$resp['data']['items'];
      $cur=intval($resp['data']['current_page'] ?? $page);
      $tot=intval($resp['data']['total_pages'] ?? $page);
      $all=array_merge($all,$items);
      if ($cur >= $tot || empty($items)) break;
    } elseif (isset($resp['items']) && is_array($resp['items'])) {
      $items=$resp['items']; $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } elseif (isset($resp['data']) && is_array($resp['data'])) {
      $items=array_values(array_filter($resp['data'],'is_array'));
      $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } elseif (is_array($resp)) {
      $items=array_values(array_filter($resp,'is_array'));
      $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } else break;
  }
  return ['items'=>$all,'next_page'=>($page+1)];
}

/** Konverzió rekord normalizálása */
function impactshop_norm_conversion($row) {
  $out = ['campaign_id'=>0,'status'=>'','data1'=>'','order_value'=>0.0,'commission'=>0.0,'currency'=>''];
  foreach (['campaign_id','campaignId','cid','campaign'] as $k) if (isset($row[$k])) { $out['campaign_id']=intval(is_array($row[$k])?($row[$k]['id']??0):$row[$k]); break; }
  foreach (['status','state'] as $k) if (!empty($row[$k])) { $out['status']=strtolower(trim($row[$k])); break; }
  foreach (['data1','d1','ref1','subid','sub_id','sub_id1'] as $k) if (isset($row[$k])) { $out['data1']=trim((string)$row[$k]); break; }
  foreach (['currency','cur'] as $k) if (!empty($row[$k])) { $out['currency']=strtoupper(trim($row[$k])); break; }
  foreach (['order_value','sale_amount','amount','price','orderAmount','total'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['order_value']=floatval($row[$k]); break; }
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['commission']=floatval($row[$k]); break; }
  return $out;
}

/** cid↔shop map */
function impactshop_build_campaign_map() {
  $shops = impactshop_get_shops();
  $by_cid = []; $by_slug = [];
  foreach ($shops as $s) {
    $cid = dognet_extract_campaign_id_from_base($s['dognet_base'] ?? '');
    $slug = $s['shop_slug']; $name = $s['name'];
    if ($cid) { $by_cid[$cid] = ['slug'=>$slug, 'name'=>$name]; }
    if ($slug) { $by_slug[$slug] = $cid; }
  }
  return ['by_cid'=>$by_cid, 'by_slug'=>$by_slug];
}

/** Aggregálás shop×NGO/NGO/shop */
function impactshop_aggregate_conversions($from, $to, $status='approved', $group='shop_ngo') {
  $cache_key = sprintf('impactshop_totals_%s_%s_%s_%s', $from, $to, $status, $group);
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return ['rows'=>[], 'meta'=>['error'=>$res['error']->get_error_message()]];
  }
  $items = $res['items'] ?? [];
  $maps = impactshop_build_campaign_map();
  $by_cid = $maps['by_cid'];

  $rows = []; $grand = ['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
  foreach ($items as $it) {
    $x = impactshop_norm_conversion($it);
    $cid = $x['campaign_id'];
    $ngo = $x['data1'] ?: '(nincs d1)';
    $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
    if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

    if ($group === 'ngo') {
      $key=$ngo;
      if (!isset($rows[$key])) $rows[$key]=['ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'shops'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['shops'][$shopSlug] = ($rows[$key]['shops'][$shopSlug] ?? 0) + $x['commission'];
    } elseif ($group === 'shop') {
      $key=$shopSlug;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'ngos'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['ngos'][$ngo] = ($rows[$key]['ngos'][$ngo] ?? 0) + $x['commission'];
    } else {
      $key = $shopSlug.'||'.$ngo;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
    }

    $grand['orders'] += 1;
    $grand['order_value'] += $x['order_value'];
    $grand['commission']  += $x['commission'];
  }

  $rows = array_values($rows);
  usort($rows, function($a,$b){
    $da = $b['commission'] <=> $a['commission'];
    if ($da !== 0) return $da;
    return strcasecmp(($a['shop_name'] ?? $a['ngo'] ?? ''), ($b['shop_name'] ?? $b['ngo'] ?? ''));
  });

  $out = ['rows'=>$rows, 'meta'=>[
    'from'=>$from,'to'=>$to,'status'=>$status,'group'=>$group,
    'grand'=>$grand,'count'=>count($rows),'generated_at'=>current_time('mysql')
  ]];

  $ttl = impactshop_settings()['cache_ttl'] ?? (15 * MINUTE_IN_SECONDS);
  set_transient($cache_key, $out, $ttl);
  return $out;
}

/* ==================== REST – JSON ÖSSZESÍTÉS ==================== */

add_action('rest_api_init', function() {
  register_rest_route('impactshop/v1', '/totals', [
    'methods'  => 'GET',
    'callback' => function(WP_REST_Request $req){
      $from   = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to     = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $status = sanitize_text_field($req->get_param('status') ?: 'approved'); // alap: csak jóváhagyott
      $group  = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $data = impactshop_aggregate_conversions($from, $to, $status, $group);
      if (!empty($data['meta']['error'])) {
        return new WP_Error('dognet_err', 'Dognet API hiba: '.$data['meta']['error'], ['status'=>502]);
      }
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true'
  ]);
});

/* ==================== SHORTCODE – HTML RIPORT ==================== */

function impactshop_shortcode_report($atts) {
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',           // approved|pending|rejected|all
    'group'  => 'shop_ngo',           // shop_ngo|ngo|shop
  ], $atts);

  $data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group']);
  if (!empty($data['meta']['error'])) {
    return '<div style="color:#b00">Dognet API hiba: '. esc_html($data['meta']['error']) .'</div>';
  }
  $rows = $data['rows']; $grand = $data['meta']['grand'];

  ob_start(); ?>
  <div class="impactshop-report" style="font:14px/1.5 system-ui">
    <div style="margin:8px 0 12px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> &nbsp; |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> &nbsp; |
      <b>Bontás:</b> <?php echo esc_html($a['group']); ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead>
          <tr style="background:#f6f7f8">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Szervezet (data1)</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
            <?php elseif ($a['group']==='shop'): ?>
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
              <?php if ($a['group']==='ngo'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php elseif ($a['group']==='shop'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name'].' ('.$r['shop_slug'].')'); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php else: ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php elseif ($a['group']==='shop'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php else: ?>
              <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php endif; ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <div style="color:#777;margin-top:8px;font-size:12px">Frissítve: <?php echo esc_html($data['meta']['generated_at']); ?> · Forrás: Dognet API</div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_report','impactshop_shortcode_report');

/* ==================== Opcionális: auto-blokk „impactshop-report” oldalra ==================== */
add_filter('the_content', function($content){
  if (is_page() && function_exists('get_post') && ($p=get_post()) && is_string($p->post_name) && $p->post_name==='impactshop-report') {
    if (strpos($content,'[impactshop_report')===false) $content .= "\n\n[impactshop_report]";
  }
  return $content;
});

/* ==================== KÉZI DOGNET TOKEN REFRESH / DIAG ==================== */
/* Admin joggal hívható:
   - frissítés:  /?impactshop_token=refresh
   - részletes diag: /?impactshop_token=refresh&diag=1
*/
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_token']) && $_GET['impactshop_token']==='refresh') {
    delete_transient('dognet_api_token_cache_v1');

    $eps = [DOGNET_API_BASE.'/auth/login', DOGNET_API_BASE.'/publisher/login', DOGNET_API_BASE.'/login'];
    $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;

    $tries = [];
    foreach ($eps as $ep) {
      $r1 = impactshop__dognet_try_login_once($ep, wp_json_encode(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/json','Accept'=>'application/json']);
      $tries[] = ['ep'=>$ep,'type'=>'json','ok'=>!empty($r1['ok']),'code'=>$r1['code']??null,'why'=>$r1['why']??'','body'=>$r1['body']??'','token'=>isset($r1['token'])?('…'.substr($r1['token'],-8)):''];
      if (!empty($r1['ok'])) { set_transient('dognet_api_token_cache_v1', $r1['token'], DOGNET_TOKEN_TTL); if (!isset($_GET['diag'])) wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop'); }
      $r2 = impactshop__dognet_try_login_once($ep, http_build_query(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']);
      $tries[] = ['ep'=>$ep,'type'=>'form','ok'=>!empty($r2['ok']),'code'=>$r2['code']??null,'why'=>$r2['why']??'','body'=>$r2['body']??'','token'=>isset($r2['token'])?('…'.substr($r2['token'],-8)):''];
      if (!empty($r2['ok'])) { set_transient('dognet_api_token_cache_v1', $r2['token'], DOGNET_TOKEN_TTL); if (!isset($_GET['diag'])) wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop'); }
    }

    // DIAG mód: részletes jelentés
    $curl = function_exists('curl_version') ? curl_version() : null;
    $curl_ver = $curl ? ($curl['version'].' / SSL '.$curl['ssl_version']) : 'curl N/A';
    $ssl_loc = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : null;
    $ssl_file = $ssl_loc ? ($ssl_loc['default_cert_file'] ?? '') : '';

    $html = '<div style="padding:16px;font:14px/1.5 system-ui">';
    $html .= '<div style="color:#b00;font-weight:700">HIBA: nem sikerült új Dognet tokent kérni.</div>';
    $html .= '<div style="margin:10px 0 6px 0;color:#444">Próbálkozások:</div><ol>';
    foreach ($tries as $t) {
      $html .= '<li><code>'.esc_html($t['ep']).'</code> ['.esc_html($t['type']).'] → '
             . ( $t['ok'] ? '<span style="color:#070">OK</span> token '.$t['token']
                         : '<span style="color:#b00">HIBA</span> code='.esc_html((string)($t['code'] ?? $t['why'])) )
             . '</li>';
      if (!empty($_GET['diag']) && !empty($t['body'])) {
        $html .= '<pre style="background:#fafafa;border:1px solid #eee;padding:8px;border-radius:6px;white-space:pre-wrap">'
              . esc_html($t['body']) . '</pre>';
      }
    }
    $html .= '</ol>';
    $html .= '<div style="margin-top:8px;color:#666">HTTP transport: '.$curl_ver.' · CA file: '.esc_html($ssl_file).'</div>';
    if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
      $html .= '<div style="color:#b00">Figyelem: WP_HTTP_BLOCK_EXTERNAL aktív. A host whitelisten: api.app.dognet.com</div>';
    }
    $html .= '</div>';
    wp_die($html, 'ImpactShop');
  }
});
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (Dognet API auto-login + Redirect + UI + Banner highlight)
 * + ELSZÁMOLÁSOK (konverziók riport: shortcode + REST)
 *
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag],
 *               [impactshop_debug], [impactshop_report]
 * Endpontok:    /go és /go-deal   (slugot is elfogad: /go/{shop_slug})
 * REST:         /wp-json/impactshop/v1/totals?from=YYYY-MM-DD&to=YYYY-MM-DD&status=approved|pending|rejected|all
 * Megjegyzés:   aktiválás után egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');

/**
 * TOKEN FIX:
 * - Ha megadsz statikus tokent itt (csak a JOGKÓDOT, "Bearer " NÉLKÜL), akkor azt használjuk.
 * - Ha üres, akkor auto-login történik és 24h körüli tokent cache-elünk.
 */
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', ''); // pl.: 'eyJhbGciOiJIUzI1NiIs...'

if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 0); // 0: első "Web Site" jellegű csatorna
if (!defined('IMPACTSHOP_CACHE_TTL'))  define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS); // biztonsági ráhagyás
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');

/* ============================== BEÁLLÍTÁS ============================== */

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    // Banners lap (gid=328401803) – a &v= számot emeld cache-törléshez
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=4',
    'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
    'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
  ];
}

/* ============================ CSV SEGÉDEK ============================ */

function impactshop_slugify_header($s) {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
  $s = strtr($s, $map);
  $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
  return trim($s, '_');
}

function impactshop_fetch_csv_assoc($url, $cache_key, $ttl) {
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $resp = wp_remote_get($url, ['timeout'=>20]);
  if (is_wp_error($resp)) return [];
  $body = wp_remote_retrieve_body($resp);
  if (!$body) return [];

  if (substr($body,0,3) === "\xEF\xBB\xBF") $body = substr($body,3);
  $lines = preg_split("/\r\n|\n|\r/", $body);
  if (!$lines || count($lines) < 1) return [];

  $first = $lines[0];
  $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

  $headers_raw = str_getcsv($first, $delim);
  $headers = array_map('impactshop_slugify_header', $headers_raw);

  $rows = [];
  for ($i=1; $i<count($lines); $i++) {
    if ($lines[$i] === '' || $lines[$i] === false) continue;
    $cols = str_getcsv($lines[$i], $delim);
    if (count($cols) == 1 && $cols[0] === null) continue;
    $row = [];
    foreach ($headers as $idx=>$key) {
      $row[$key] = isset($cols[$idx]) ? trim($cols[$idx]) : '';
    }
    if (implode('', $row) === '') continue;
    $rows[] = $row;
  }

  set_transient($cache_key, $rows, $ttl);
  return $rows;
}

/* ======================= SHOPS & BANNERS ====================== */

function impactshop_get_shops_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
}
function impactshop_get_banners_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
}

function impactshop_get_shops() {
  $rows = impactshop_get_shops_raw();
  $out = [];
  foreach ($rows as $r) {
    $name   = $r['name'] ?? ($r['nev'] ?? '');
    $slug   = $r['shop_slug'] ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
    $cat    = $r['category'] ?? ($r['kategoria'] ?? 'Egyéb');
    $logo   = $r['logo_url'] ?? ($r['logo'] ?? ($r['image'] ?? ''));
    if (!$name || !$slug) continue;
    $out[] = [
      'name'           => $name,
      'shop_slug'      => $slug,
      'category'       => $cat ?: 'Egyéb',
      'logo'           => $logo,
      'dognet_base'    => $r['dognet_base'] ?? '',
      'deeplink_param' => ($r['pdognet_deeplink_param'] ?? ($r['dognet_deeplink_param'] ?? 'url')) ?: 'url',
      'product_url'    => $r['product_url'] ?? ($r['homepage'] ?? ''),
      'commission_min' => $r['commission_min'] ?? '',
      'commission_max' => $r['commission_max'] ?? '',
      'deals_feed'     => $r['deals_feed'] ?? '',
    ];
  }
  return $out;
}

function impactshop_get_banners() {
  $rows = impactshop_get_banners_raw();
  $out = [];
  foreach ($rows as $r) {
    $img   = $r['img'] ?? ($r['image'] ?? ($r['banner'] ?? ($r['kep'] ?? '')));
    $href  = $r['href'] ?? ($r['url']   ?? ($r['link']   ?? ''));
    $label = $r['label']?? ($r['cimke'] ?? ($r['title']  ?? 'Banner'));
    $cat   = $r['category'] ?? ($r['kategoria'] ?? '');
    if (!$img || !$href) continue;
    $out[] = ['img'=>$img,'href'=>$href,'label'=>$label,'category'=>$cat];
  }
  return $out;
}

/* ====================== KÖZÖS SEGÉDEK ====================== */

function impactshop_q($key, $def='') {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
}
function impactshop_find_shop($slug) {
  $slug = trim(strtolower($slug));
  foreach (impactshop_get_shops() as $s) {
    if (strtolower($s['shop_slug']) === $slug) return $s;
  }
  return null;
}
function impactshop_error($msg) {
  status_header(400);
  wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'ImpactShop hiba');
}

/* ====================== DOGNET API (TOKEN FIX) ====================== */

/** Normalizáljuk a tokent: levágjuk a felesleget, "Bearer " nélkül tárolunk/használunk. */
function dognet__normalize_token($tok) {
  $tok = trim((string)$tok);
  // idézőjelek/prefixek lecsupaszítása
  $tok = preg_replace('~^(?:"|\')?(?:Bearer\s+)?([A-Za-z0-9\-\._]+)(?:"|\')?$~', '$1', $tok);
  return $tok;
}

/** Belső segéd: többféle login-meghívás, token-kinyeréssel. */
function impactshop__dognet_try_login_once($endpoint, $payload, $headers) {
  $resp = wp_remote_post($endpoint, [
    'timeout'     => 25,
    'headers'     => $headers,
    'body'        => $payload,
    'redirection' => 3,
  ]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>'wp_error: '.$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);

  $json = json_decode($body, true);
  $tok  = '';

  if (is_array($json)) {
    // Lehetséges kulcsok (szélesített lista)
    $candidates = [
      'token','access_token','jwt','api_token',
      'data.token','data.access_token','data.jwt','result.token','result.access_token',
    ];
    foreach ($candidates as $ck) {
      $parts = explode('.', $ck);
      $val = $json;
      foreach ($parts as $p) {
        if (!is_array($val) || !array_key_exists($p, $val)) { $val = null; break; }
        $val = $val[$p];
      }
      if (is_string($val) && $val !== '') { $tok = $val; break; }
    }
  }

  if ($code >= 200 && $code < 300 && $tok) {
    return ['ok'=>true,'token'=>dognet__normalize_token($tok),'code'=>$code];
  }
  return ['ok'=>false,'code'=>$code,'body'=>is_string($body)?substr($body,0,400):'(nincs törzs)'];
}

/** ROBUSZTUS token-szerzés: először statikus (konstans), különben több végpont + JSON és form-encoded fallback. Cache: transient. */
function dognet_get_token($force_refresh = false) {
  // 1) Statikus token konfigurációból (ha megadva)
  if (DOGNET_API_TOKEN) {
    return dognet__normalize_token(DOGNET_API_TOKEN);
  }

  // 2) Ha WP szinten tiltva a kimenő forgalom, futás közben engedélyezzük a Dognet hostot
  if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
    $allowed = defined('WP_ACCESSIBLE_HOSTS') ? WP_ACCESSIBLE_HOSTS : '';
    if (stripos($allowed, 'api.app.dognet.com') === false) {
      @define('WP_ACCESSIBLE_HOSTS', $allowed ? ($allowed.',api.app.dognet.com') : 'api.app.dognet.com');
    }
  }

  $key = 'dognet_api_token_cache_v1';
  if (!$force_refresh) {
    $tok = get_transient($key);
    if ($tok) return dognet__normalize_token($tok);
  }

  $email = DOGNET_LOGIN_EMAIL;  $pass = DOGNET_LOGIN_PASSWORD;

  // Több lehetséges login végpont
  $endpoints = array_unique([
    DOGNET_API_BASE.'/auth/login',
    DOGNET_API_BASE.'/publisher/login',
    DOGNET_API_BASE.'/login',
    // kompat: ha az alap URL végén van /api/v1, próbáljuk a /auth/login-t a base nélkül is
    rtrim(str_replace('/api/v1','',DOGNET_API_BASE),'/').'/auth/login',
  ]);

  $payload_json = wp_json_encode(['email'=>$email,'password'=>$pass]);
  $payload_form = http_build_query(['email'=>$email,'password'=>$pass]);
  $headers_json = ['Content-Type'=>'application/json','Accept'=>'application/json'];
  $headers_form = ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json'];

  foreach ($endpoints as $ep) {
    $r = impactshop__dognet_try_login_once($ep, $payload_json, $headers_json);
    if (!empty($r['ok'])) { set_transient($key, $r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
    $r = impactshop__dognet_try_login_once($ep, $payload_form, $headers_form);
    if (!empty($r['ok'])) { set_transient($key, $r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  }
  return '';
}

/** API kérés egységesen – 401/403/419-nél egyszer megpróbál új tokennel ismételni. */
function dognet_api_request($method, $path, $body=null) {
  $token = dognet_get_token(false);
  if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

  $url = (stripos($path,'http')===0 ? $path : rtrim(DOGNET_API_BASE,'/').$path);
  $args = [
    'timeout' => 25,
    'headers' => ['Authorization'=>'Bearer '.dognet__normalize_token($token),'Content-Type'=>'application/json','Accept'=>'application/json'],
    'method'  => $method,
  ];
  if ($body!==null) $args['body'] = wp_json_encode($body);

  $resp = wp_remote_request($url, $args);
  if (is_wp_error($resp)) return $resp;

  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);

  // Ha a token lejárt/hibás: 401/403/419 → frissítünk és egyszer újrapróbáljuk
  if (in_array($code, [401,403,419], true)) {
    delete_transient('dognet_api_token_cache_v1');
    // statikus token esetén nincs mit frissíteni → azonnal hiba
    if (DOGNET_API_TOKEN) {
      return new WP_Error('no_token','Dognet API statikus token érvénytelen/lejárt (403/401). Kérlek frissítsd a DOGNET_API_TOKEN értéket.');
    }
    $token = dognet_get_token(true);
    if (!$token) return new WP_Error('no_token','Dognet API token frissítés sikertelen ('.$code.')');
    $args['headers']['Authorization'] = 'Bearer '.dognet__normalize_token($token);
    $resp = wp_remote_request($url, $args);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
  }

  if ($code < 200 || $code >= 300) return new WP_Error('api_error','Dognet API hiba '.$code,['resp'=>$json,'code'=>$code]);
  return $json;
}

function dognet_extract_campaign_id_from_base($dognet_base) {
  if (!$dognet_base) return 0;
  $qs = parse_url($dognet_base, PHP_URL_QUERY);
  parse_str($qs,$parts);
  return isset($parts['cid']) ? intval($parts['cid']) : 0;
}

function dognet_api_pick_ad_channel_id() {
  if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
  $list = dognet_api_request('GET','/ad-channels');
  if (is_wp_error($list) || empty($list['data'])) return 0;
  foreach ($list['data'] as $c) {
    if (isset($c['type']) && stripos($c['type'],'web')!==false) return intval($c['id']);
  }
  return intval($list['data'][0]['id']);
}

function dognet_api_generate_link($campaign_id,$deeplink='',$d1='',$d2='') {
  $ad_channel_id = dognet_api_pick_ad_channel_id();
  if (!$ad_channel_id) return new WP_Error('no_channel','Nincs ad_channel');
  $body = ['ad_channel_id'=>$ad_channel_id,'campaign_id'=>intval($campaign_id),'url_type'=>3];
  if ($deeplink) $body['url']=$deeplink;
  if ($d1) $body['data1']=$d1;
  if ($d2) $body['data2']=$d2;
  $json = dognet_api_request('POST','/campaigns/links/generate',$body);
  if (is_wp_error($json)) return $json;
  foreach(['url','short_url','full_url'] as $k){
    if(!empty($json[$k])) return $json[$k];
    if(!empty($json['data'][$k])) return $json['data'][$k];
  }
  return new WP_Error('bad_api','Ismeretlen API válasz');
}

/* ==================== REDIRECTEK =================== */

function impactshop_add_rewrites() {
  add_rewrite_rule('^go/([^/]+)/?$',      'index.php?impactshop_go=1&impactshop_slug=$matches[1]',   'top');
  add_rewrite_rule('^go/?$',              'index.php?impactshop_go=1',                                'top');
  add_rewrite_rule('^go-deal/([^/]+)/?$', 'index.php?impactshop_deal=1&impactshop_slug=$matches[1]', 'top');
  add_rewrite_rule('^go-deal/?$',         'index.php?impactshop_deal=1',                              'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[]='impactshop_go';
  $vars[]='impactshop_deal';
  $vars[]='impactshop_slug';
  return $vars;
}
add_filter('query_vars','impactshop_add_query_vars');

add_action('template_redirect',function(){
  if(get_query_var('impactshop_go')){impactshop_handle_go(false);exit;}
  if(get_query_var('impactshop_deal')){impactshop_handle_go(true);exit;}
});

function impactshop_redirect_with_propagation($url,$amb,$src) {
  $add=[];
  if($amb&&strpos($url,'amb=')===false)$add['amb']=$amb;
  if($src&&strpos($url,'src=')===false)$add['src']=$src;
  if(strpos($url,'utm_source=')===false)$add['utm_source']='sharity';
  if(strpos($url,'utm_medium=')===false)$add['utm_medium']='impactshop';
  if($add)$url.=(strpos($url,'?')===false?'?':'&').http_build_query($add);
  wp_redirect($url,307);exit;
}

function impactshop_handle_go($is_deal) {
  $shop=impactshop_q('shop'); if(!$shop){ $shop = get_query_var('impactshop_slug'); }
  $ngo=impactshop_q('d1');$u=impactshop_q('u');
  $amb=impactshop_q('amb');$src=impactshop_q('src')?:'impactshop';
  if(!$shop||!$ngo)impactshop_error('Hiányzó paraméter (shop/d1).');
  $row=impactshop_find_shop($shop);if(!$row)impactshop_error('Ismeretlen shop: '.esc_html($shop));

  $targetUrl='';
  if($is_deal){ $targetUrl=$u?:($row['product_url']??''); }

  $final=null;$cid=dognet_extract_campaign_id_from_base($row['dognet_base']??'');
  if($cid){
    $deeplink=$targetUrl;
    if($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~',$deeplink)){
      $tmp=base64_decode($deeplink,true);
      if($tmp!==false && preg_match('~^https?://~i',$tmp)) $deeplink=$tmp;
    }
    $api=dognet_api_generate_link($cid,$deeplink,$ngo,'');
    if(!is_wp_error($api) && $api) $final=$api;
  }

  if(!$final){
    $base=$row['dognet_base']??'';
    if($base){
      $params=['d1'=>$ngo];
      if(!empty($targetUrl)){
        $deeplink = $targetUrl;
        if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
          $tmp = base64_decode($deeplink, true);
          if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
        }
        $deeplinkParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
        $params[$deeplinkParam] = $deeplink;
      }
      $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($params);
    }
  }

  if(!$final)impactshop_error('Nem sikerült a partner linket előállítani.');
  impactshop_redirect_with_propagation($final,$amb,$src);
}

/* ==================== SHORTCODE-OK =================== */

/** Scroller */
function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts(['category'=>'','inject_every'=>5,'speed'=>30], $atts);
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  if (!empty($a['category'])) {
    $catWanted = $a['category'];
    $shops   = array_values(array_filter($shops,   fn($s)=> strcasecmp($s['category'],$catWanted)===0 ));
    $banners = array_values(array_filter($banners, fn($b)=> empty($b['category']) || strcasecmp($b['category'],$catWanted)===0 ));
  }
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  $injectEvery = max(1, intval($a['inject_every']));
  $mixed = []; $bi = 0;
  foreach ($shops as $i=>$s) {
    $mixed[] = ['type'=>'shop','data'=>$s];
    if (($i+1)%$injectEvery===0 && $banners) {
      $mixed[] = ['type'=>'banner','data'=>$banners[$bi % count($banners)]];
      $bi++;
    }
  }
  $stream = array_merge($mixed, $mixed);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <style>
    .impactshop-scroller{overflow:hidden;width:100%;white-space:nowrap;position:relative}
    .impactshop-scroller-track{display:inline-block;white-space:nowrap;animation:impactshop-scroll linear infinite}
    @keyframes impactshop-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .impactshop-scroller:hover .impactshop-scroller-track{animation-play-state:paused}
    .impactshop-item{display:inline-block;margin-right:20px;position:relative}
    .impactshop-item.shop-item img{height:60px;width:auto}
    .impactshop-item.banner-item img{height:100px;width:auto;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .impactshop-badge{position:absolute;top:-8px;left:-8px;background:#ff3366;color:#fff;font:600 11px/1 system-ui;padding:4px 6px;border-radius:6px}
    @media (max-width:640px){ .impactshop-item.banner-item img{height:90px} }
  </style>
  <div class="impactshop-scroller">
    <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
      <?php foreach ($stream as $it): ?>
        <?php if ($it['type']==='shop'): $s=$it['data'];
          $cta = $d1
            ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($s['shop_slug'])))
            : add_query_arg(['shop'=>$s['shop_slug'],'amb'=>$amb], $fillout); ?>
          <div class="impactshop-item shop-item">
            <a href="<?php echo esc_url($cta); ?>">
              <img src="<?php echo esc_url($s['logo']); ?>" alt="<?php echo esc_attr($s['name']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php else: $b=$it['data']; ?>
          <div class="impactshop-item banner-item">
            <span class="impactshop-badge">AKCIÓ</span>
            <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
              <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_scroller','impactshop_shortcode_scroller');

/** Katalógus */
function impactshop_shortcode_catalog($atts) {
  $a = shortcode_atts(['show_tabs'=>'1','search'=>'1','per_page'=>'200'], $atts);
  $shops = impactshop_get_shops();
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  usort($shops, function($x,$y){
    $c = strcasecmp($x['category'],$y['category']);
    return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
  });

  $cats = []; foreach ($shops as $s) { $cats[$s['category']] = true; }
  $categories = array_keys($cats);
  sort($categories, SORT_NATURAL|SORT_FLAG_CASE);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <div class="impactshop-catalog">
    <?php if ($a['show_tabs']==='1'): ?>
      <ul class="impactshop-tabs" style="list-style:none;padding:0;margin:0 0 12px 0;display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
        <?php foreach ($categories as $i=>$cat): ?>
          <li data-cat="<?php echo esc_attr($cat); ?>" class="<?php echo $i===0?'active':''; ?>"
              style="cursor:pointer;padding:8px 12px;border-radius:999px;background:#f3f3f3;font-weight:600;font-size:14px;">
            <?php echo esc_html($cat); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($a['search']==='1'): ?>
      <input type="text" class="impactshop-search" placeholder="Keresés…" aria-label="Keresés"
             style="width:100%;max-width:520px;margin:0 auto 10px auto;display:block;padding:8px;border:1px solid #ddd;border-radius:8px;">
    <?php endif; ?>

    <div class="impactshop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;align-items:start;">
      <?php foreach ($shops as $shop):
        $cta = $d1
          ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])))
          : add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout); ?>
        <div class="impactshop-card" data-cat="<?php echo esc_attr($shop['category']); ?>" style="text-align:center;">
          <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
            <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                 loading="lazy" decoding="async" style="height:60px;width:auto;max-width:100%">
          </a>
          <div style="font-size:14px;font-weight:600;margin-top:6px;"><?php echo esc_html($shop['name']); ?></div>
          <?php
          $min = trim($shop['commission_min']); $max = trim($shop['commission_max']);
          $minN = is_numeric(str_replace('%','',$min)) ? floatval(str_replace('%','',$min)) : null;
          $maxN = is_numeric(str_replace('%','',$max)) ? floatval(str_replace('%','',$max)) : null;
          if ($minN !== null || $maxN !== null) {
            if ($minN !== null && $maxN !== null && $minN !== $maxN) {
              $don = ($minN/2) .'–'. ($maxN/2).'%';
            } else {
              $v = ($minN !== null) ? $minN : $maxN;
              $don = ($v/2).'%';
            }
            echo '<div style="font-size:12px;color:#666;margin-top:2px;">Várható adomány: ~'. esc_html($don) .'</div>';
          }
          ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    if(!root) return;
    var tabs = root.querySelectorAll('.impactshop-tabs li');
    var search = root.querySelector('.impactshop-search');
    var cards = root.querySelectorAll('.impactshop-card');
    function apply() {
      var term = search ? (search.value || '').toLowerCase() : '';
      var active = root.querySelector('.impactshop-tabs li.active');
      var cat = active ? active.getAttribute('data-cat') : null;
      Array.prototype.forEach.call(cards, function(card){
        var okCat = !cat || card.getAttribute('data-cat') === cat;
        var nameEl = card.querySelector('div');
        var name = nameEl ? (nameEl.textContent || '').toLowerCase() : '';
        var okQ = !term || name.indexOf(term) !== -1;
        card.style.display = (okCat && okQ) ? '' : 'none';
      });
    }
    Array.prototype.forEach.call(tabs, function(tab){
      tab.addEventListener('click', function(){
        Array.prototype.forEach.call(tabs, function(t){ t.classList.remove('active'); });
        tab.classList.add('active');
        apply();
      });
    });
    if (search) search.addEventListener('input', apply);
    apply();
  })();
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_catalog','impactshop_shortcode_catalog');

/** Diagnosztika */
function impactshop_shortcode_diag() {
  $shops = impactshop_get_shops();
  $out = '<div style="font:14px/1.5 system-ui"><h3>Impact Shop diagnosztika</h3>';
  $out .= '<p>Shops betöltve: <b>'.count($shops).'</b></p>';
  $miss = [];
  foreach ($shops as $s) {
    if (empty($s['dognet_base']))    $miss[] = $s['shop_slug'].' (dognet_base)';
    if (empty($s['deeplink_param'])) $miss[] = $s['shop_slug'].' (pdognet_deeplink_param)';
  }
  if ($miss) {
    $out .= '<p style="color:#b00">Hiányzó mezők:<br>'.esc_html(implode(', ', $miss)).'</p>';
  } else {
    $out .= '<p style="color:#070">dognet_base + deeplink_param mindenhol rendben.</p>';
  }
  $out .= '</div>';
  return $out;
}
add_shortcode('impactshop_diag','impactshop_shortcode_diag');

/** Egyszerű debug */
function impactshop_shortcode_debug() {
  $s = impactshop_settings();
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();
  $demo = [
    'img'=> home_url('/wp-content/uploads/2025/09/log.jpeg'),
    'href'=> 'https://form.fillout.com/t/eM61RLkz6jus?shop=4home&u='.rawurlencode('https://www.4home.hu/'),
    'label'=> 'Ajánlatok – 4home', 'category'=> 'Otthon'
  ];
  ob_start(); ?>
  <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;padding:12px;border-radius:8px">
Shops: <?php echo count($shops); ?> | Banners: <?php echo count($banners); ?>

shops_csv_url:  <?php echo esc_html($s['shops_csv_url']); ?>

banners_csv_url: <?php echo esc_html($s['banners_csv_url']); ?>


Minta banner: <?php echo esc_html(json_encode($demo, JSON_UNESCAPED_UNICODE)); ?>

  </pre>
  <?php return ob_get_clean();
}
add_shortcode('impactshop_debug','impactshop_shortcode_debug');

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});

/* ================================================================== */
/* ==================  E L S Z Á M O L Á S O K  (ÚJ)  ================ */
/* ================================================================== */

/** Konverziók listázása — oldalanként */
function dognet_api_list_conversions_page($from, $to, $status='all', $page=1, $perPage=200) {
  $qs = ['from'=>$from,'to'=>$to,'page'=>max(1,intval($page)),'per_page'=>max(1,min(500,intval($perPage)))];
  if ($status && $status!=='all') $qs['status']=$status;
  $path = '/conversions?'. http_build_query($qs);
  return dognet_api_request('GET', $path);
}

/** Konverziók begyűjtése minden oldalról */
function dognet_api_list_conversions_all($from, $to, $status='all', $maxPages=80, $perPage=200) {
  $all=[]; $page=1;
  for (; $page<=$maxPages; $page++) {
    $resp = dognet_api_list_conversions_page($from,$to,$status,$page,$perPage);
    if (is_wp_error($resp)) return ['error'=>$resp];
    $items=[];
    if (isset($resp['data']['items']) && is_array($resp['data']['items'])) {
      $items=$resp['data']['items'];
      $cur=intval($resp['data']['current_page'] ?? $page);
      $tot=intval($resp['data']['total_pages'] ?? $page);
      $all=array_merge($all,$items);
      if ($cur >= $tot || empty($items)) break;
    } elseif (isset($resp['items']) && is_array($resp['items'])) {
      $items=$resp['items']; $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } elseif (isset($resp['data']) && is_array($resp['data'])) {
      $items=array_values(array_filter($resp['data'],'is_array'));
      $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } elseif (is_array($resp)) {
      $items=array_values(array_filter($resp,'is_array'));
      $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } else break;
  }
  return ['items'=>$all,'next_page'=>($page+1)];
}

/** Konverzió rekord normalizálása */
function impactshop_norm_conversion($row) {
  $out = ['campaign_id'=>0,'status'=>'','data1'=>'','order_value'=>0.0,'commission'=>0.0,'currency'=>''];
  foreach (['campaign_id','campaignId','cid','campaign'] as $k) if (isset($row[$k])) { $out['campaign_id']=intval(is_array($row[$k])?($row[$k]['id']??0):$row[$k]); break; }
  foreach (['status','state'] as $k) if (!empty($row[$k])) { $out['status']=strtolower(trim($row[$k])); break; }
  foreach (['data1','d1','ref1','subid','sub_id','sub_id1'] as $k) if (isset($row[$k])) { $out['data1']=trim((string)$row[$k]); break; }
  foreach (['currency','cur'] as $k) if (!empty($row[$k])) { $out['currency']=strtoupper(trim($row[$k])); break; }
  foreach (['order_value','sale_amount','amount','price','orderAmount','total'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['order_value']=floatval($row[$k]); break; }
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['commission']=floatval($row[$k]); break; }
  return $out;
}

/** cid↔shop map */
function impactshop_build_campaign_map() {
  $shops = impactshop_get_shops();
  $by_cid = []; $by_slug = [];
  foreach ($shops as $s) {
    $cid = dognet_extract_campaign_id_from_base($s['dognet_base'] ?? '');
    $slug = $s['shop_slug']; $name = $s['name'];
    if ($cid) { $by_cid[$cid] = ['slug'=>$slug, 'name'=>$name]; }
    if ($slug) { $by_slug[$slug] = $cid; }
  }
  return ['by_cid'=>$by_cid, 'by_slug'=>$by_slug];
}

/** Aggregálás shop×NGO/NGO/shop */
function impactshop_aggregate_conversions($from, $to, $status='approved', $group='shop_ngo') {
  $cache_key = sprintf('impactshop_totals_%s_%s_%s_%s', $from, $to, $status, $group);
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return ['rows'=>[], 'meta'=>['error'=>$res['error']->get_error_message()]];
  }
  $items = $res['items'] ?? [];
  $maps = impactshop_build_campaign_map();
  $by_cid = $maps['by_cid'];

  $rows = []; $grand = ['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
  foreach ($items as $it) {
    $x = impactshop_norm_conversion($it);
    $cid = $x['campaign_id'];
    $ngo = $x['data1'] ?: '(nincs d1)';
    $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
    if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

    if ($group === 'ngo') {
      $key=$ngo;
      if (!isset($rows[$key])) $rows[$key]=['ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'shops'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['shops'][$shopSlug] = ($rows[$key]['shops'][$shopSlug] ?? 0) + $x['commission'];
    } elseif ($group === 'shop') {
      $key=$shopSlug;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'ngos'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['ngos'][$ngo] = ($rows[$key]['ngos'][$ngo] ?? 0) + $x['commission'];
    } else {
      $key = $shopSlug.'||'.$ngo;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
    }

    $grand['orders'] += 1;
    $grand['order_value'] += $x['order_value'];
    $grand['commission']  += $x['commission'];
  }

  $rows = array_values($rows);
  usort($rows, function($a,$b){
    $da = $b['commission'] <=> $a['commission'];
    if ($da !== 0) return $da;
    return strcasecmp(($a['shop_name'] ?? $a['ngo'] ?? ''), ($b['shop_name'] ?? $b['ngo'] ?? ''));
  });

  $out = ['rows'=>$rows, 'meta'=>[
    'from'=>$from,'to'=>$to,'status'=>$status,'group'=>$group,
    'grand'=>$grand,'count'=>count($rows),'generated_at'=>current_time('mysql')
  ]];

  $ttl = impactshop_settings()['cache_ttl'] ?? (15 * MINUTE_IN_SECONDS);
  set_transient($cache_key, $out, $ttl);
  return $out;
}

/* ==================== REST – JSON ÖSSZESÍTÉS ==================== */

add_action('rest_api_init', function() {
  register_rest_route('impactshop/v1', '/totals', [
    'methods'  => 'GET',
    'callback' => function(WP_REST_Request $req){
      $from   = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to     = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $status = sanitize_text_field($req->get_param('status') ?: 'approved'); // alap: csak jóváhagyott
      $group  = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $data = impactshop_aggregate_conversions($from, $to, $status, $group);
      if (!empty($data['meta']['error'])) {
        return new WP_Error('dognet_err', 'Dognet API hiba: '.$data['meta']['error'], ['status'=>502]);
      }
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true'
  ]);
});

/* ==================== SHORTCODE – HTML RIPORT ==================== */

function impactshop_shortcode_report($atts) {
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',           // approved|pending|rejected|all
    'group'  => 'shop_ngo',           // shop_ngo|ngo|shop
  ], $atts);

  $data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group']);
  if (!empty($data['meta']['error'])) {
    return '<div style="color:#b00">Dognet API hiba: '. esc_html($data['meta']['error']) .'</div>';
  }
  $rows = $data['rows']; $grand = $data['meta']['grand'];

  ob_start(); ?>
  <div class="impactshop-report" style="font:14px/1.5 system-ui">
    <div style="margin:8px 0 12px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> &nbsp; |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> &nbsp; |
      <b>Bontás:</b> <?php echo esc_html($a['group']); ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead>
          <tr style="background:#f6f7f8">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Szervezet (data1)</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
            <?php elseif ($a['group']==='shop'): ?>
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
              <?php if ($a['group']==='ngo'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php elseif ($a['group']==='shop'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name'].' ('.$r['shop_slug'].')'); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php else: ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php elseif ($a['group']==='shop'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php else: ?>
              <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php endif; ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <div style="color:#777;margin-top:8px;font-size:12px">Frissítve: <?php echo esc_html($data['meta']['generated_at']); ?> · Forrás: Dognet API</div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_report','impactshop_shortcode_report');

/* ==================== Opcionális: auto-blokk „impactshop-report” oldalra ==================== */
add_filter('the_content', function($content){
  if (is_page() && function_exists('get_post') && ($p=get_post()) && is_string($p->post_name) && $p->post_name==='impactshop-report') {
    if (strpos($content,'[impactshop_report')===false) $content .= "\n\n[impactshop_report]";
  }
  return $content;
});

/* ==================== KÉZI DOGNET TOKEN REFRESH / DIAG ==================== */
/* Admin joggal hívható:
   - frissítés:  /?impactshop_token=refresh
   - részletes diag: /?impactshop_token=refresh&diag=1
*/
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_token']) && $_GET['impactshop_token']==='refresh') {
    delete_transient('dognet_api_token_cache_v1');

    $eps = array_unique([DOGNET_API_BASE.'/auth/login', DOGNET_API_BASE.'/publisher/login', DOGNET_API_BASE.'/login', rtrim(str_replace('/api/v1','',DOGNET_API_BASE),'/').'/auth/login']);
    $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;

    $tries = [];
    foreach ($eps as $ep) {
      $r1 = impactshop__dognet_try_login_once($ep, wp_json_encode(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/json','Accept'=>'application/json']);
      $tries[] = ['ep'=>$ep,'type'=>'json','ok'=>!empty($r1['ok']),'code'=>$r1['code']??null,'why'=>$r1['why']??'','body'=>$r1['body']??'','token'=>isset($r1['token'])?('…'.substr($r1['token'],-8)):''];
      if (!empty($r1['ok'])) { set_transient('dognet_api_token_cache_v1', $r1['token'], DOGNET_TOKEN_TTL); if (!isset($_GET['diag'])) wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop'); }
      $r2 = impactshop__dognet_try_login_once($ep, http_build_query(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']);
      $tries[] = ['ep'=>$ep,'type'=>'form','ok'=>!empty($r2['ok']),'code'=>$r2['code']??null,'why'=>$r2['why']??'','body'=>$r2['body']??'','token'=>isset($r2['token'])?('…'.substr($r2['token'],-8)):''];
      if (!empty($r2['ok'])) { set_transient('dognet_api_token_cache_v1', $r2['token'], DOGNET_TOKEN_TTL); if (!isset($_GET['diag'])) wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop'); }
    }

    // DIAG mód: részletes jelentés
    $curl = function_exists('curl_version') ? curl_version() : null;
    $curl_ver = $curl ? ($curl['version'].' / SSL '.$curl['ssl_version']) : 'curl N/A';
    $ssl_loc = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : null;
    $ssl_file = $ssl_loc ? ($ssl_loc['default_cert_file'] ?? '') : '';

    $html = '<div style="padding:16px;font:14px/1.5 system-ui">';
    $html .= '<div style="color:#b00;font-weight:700">HIBA: nem sikerült új Dognet tokent kérni.</div>';
    $html .= '<div style="margin:10px 0 6px 0;color:#444">Próbálkozások:</div><ol>';
    foreach ($tries as $t) {
      $html .= '<li><code>'.esc_html($t['ep']).'</code> ['.esc_html($t['type']).'] → '
             . ( $t['ok'] ? '<span style="color:#070">OK</span> token '.$t['token']
                         : '<span style="color:#b00">HIBA</span> code='.esc_html((string)($t['code'] ?? $t['why'])) )
             . '</li>';
      if (!empty($_GET['diag']) && !empty($t['body'])) {
        $html .= '<pre style="background:#fafafa;border:1px solid #eee;padding:8px;border-radius:6px;white-space:pre-wrap">'
              . esc_html($t['body']) . '</pre>';
      }
    }
    $html .= '</ol>';
    $html .= '<div style="margin-top:8px;color:#666">HTTP transport: '.$curl_ver.' · CA file: '.esc_html($ssl_file).'</div>';
    if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
      $html .= '<div style="color:#b00">Figyelem: WP_HTTP_BLOCK_EXTERNAL aktív. A host whitelisten: api.app.dognet.com</div>';
    }
    $html .= '</div>';
    wp_die($html, 'ImpactShop');
  }
});
Ezt mondtad:
Publisher API documentation.pdf
PDF
Ez nem nyert kezdd újra, de csak a tokenre koncentrálj: (egyben kérem a snippetet) HIBA: nem sikerült új Dognet tokent kérni.
Próbálkozások:
https://api.app.dognet.com/api/v1/auth/login [json] → OK token …685c7255
https://api.app.dognet.com/api/v1/auth/login [form] → OK token …f40f445f
https://api.app.dognet.com/api/v1/publisher/login [json] → HIBA code=403
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box;margin:0;padding:0}html{line-height:1.15;-webkit-text-size-adjus
https://api.app.dognet.com/api/v1/publisher/login [form] → HIBA code=403
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box;margin:0;padding:0}html{line-height:1.15;-webkit-text-size-adjus
https://api.app.dognet.com/api/v1/login [json] → HIBA code=403
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box;margin:0;padding:0}html{line-height:1.15;-webkit-text-size-adjus
https://api.app.dognet.com/api/v1/login [form] → HIBA code=403
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box;margin:0;padding:0}html{line-height:1.15;-webkit-text-size-adjus
https://api.app.dognet.com/auth/login [json] → HIBA code=403
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box;margin:0;padding:0}html{line-height:1.15;-webkit-text-size-adjus
https://api.app.dognet.com/auth/login [form] → HIBA code=403
<!DOCTYPE html><html lang="en-US"><head><title>Just a moment...</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge"><meta name="robots" content="noindex,nofollow"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box;margin:0;padding:0}html{line-height:1.15;-webkit-text-size-adjus
HTTP transport: 8.14.1 / SSL OpenSSL/1.1.1w · CA file: /opt/alt/openssl11/etc/pki/tls/cert.pem
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Az alábbi patch kizárólag a Dognet token kezelését cseréli le: az API dokumentáció szerint a helyes login végpont az /api/v1/auth/login (24h token, minden használatnál hosszabbodik). A kód csak ezt hívja (a többi, CF-s védelemmel 403-at dobó végpontot ignorálja), a tokent normalizálja (nincs benne „Bearer ”), cache-eli, lejáratnál/401-403-419 esetén egyszer automatikusan frissít. Illeszd be egyben, a meglévő token-függő részek helyére.
/* ====================== DOGNET TOKEN PATCH (ONLY) ====================== */
/* Drop-in csere: csak a tokenhez tartozó részeket definiálja/írja felül. */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
/* Ha statikus tokent akarsz használni, ide írd (csak a JWT, "Bearer " NÉLKÜL). Üres → auto-login. */
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', '');
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);

if (!function_exists('dognet__normalize_token')) {
  /** "Bearer " és idézőjelek lecsupaszítása; üresre biztonságos trim. */
  function dognet__normalize_token($tok) {
    $tok = trim((string)$tok);
    $tok = preg_replace('~^(?:"|\')?(?:Bearer\s+)?([A-Za-z0-9\-\._]+)(?:"|\')?$~', '$1', $tok);
    return $tok ?: '';
  }
}

if (!function_exists('impactshop__dognet_login_once')) {
  /** Csak a dokumentált végpontot hívjuk: /api/v1/auth/login  (JSON → fallback FORM) */
  function impactshop__dognet_login_once($email, $password) {
    $endpoint = rtrim(DOGNET_API_BASE,'/').'/auth/login';

    // JSON próbálkozás
    $resp = wp_remote_post($endpoint, [
      'timeout' => 25,
      'headers' => ['Content-Type'=>'application/json','Accept'=>'application/json'],
      'body'    => wp_json_encode(['email'=>$email,'password'=>$password]),
      'redirection' => 3,
    ]);
    if (!is_wp_error($resp)) {
      $code = wp_remote_retrieve_response_code($resp);
      $body = wp_remote_retrieve_body($resp);
      $json = json_decode($body, true);
      $tok  = '';
      if (is_array($json)) {
        foreach (['token','access_token','data.token','data.access_token'] as $ck) {
          $val = $json;
          foreach (explode('.', $ck) as $p) { if (!is_array($val) || !array_key_exists($p,$val)) { $val=null; break; } $val=$val[$p]; }
          if (is_string($val) && $val!=='') { $tok = $val; break; }
        }
      }
      if ($code>=200 && $code<300 && $tok) {
        return ['ok'=>true, 'token'=>dognet__normalize_token($tok)];
      }
    }

    // FORM fallback
    $resp = wp_remote_post($endpoint, [
      'timeout' => 25,
      'headers' => ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json'],
      'body'    => http_build_query(['email'=>$email,'password'=>$password]),
      'redirection' => 3,
    ]);
    if (!is_wp_error($resp)) {
      $code = wp_remote_retrieve_response_code($resp);
      $body = wp_remote_retrieve_body($resp);
      $json = json_decode($body, true);
      $tok  = '';
      if (is_array($json)) {
        foreach (['token','access_token','data.token','data.access_token'] as $ck) {
          $val = $json;
          foreach (explode('.', $ck) as $p) { if (!is_array($val) || !array_key_exists($p,$val)) { $val=null; break; } $val=$val[$p]; }
          if (is_string($val) && $val!=='') { $tok = $val; break; }
        }
      }
      if ($code>=200 && $code<300 && $tok) {
        return ['ok'=>true, 'token'=>dognet__normalize_token($tok)];
      }
    }

    return ['ok'=>false];
  }
}

if (!function_exists('dognet_get_token')) {
  /**
   * Token megszerzése:
   * 1) Ha van statikus DOGNET_API_TOKEN → azt használjuk (normalizálva).
   * 2) Különben transient cache ('dognet_api_token_cache_v2'), lejárat: DOGNET_TOKEN_TTL.
   * 3) Login kizárólag /api/v1/auth/login végpontra (doc szerint).
   */
  function dognet_get_token($force_refresh = false) {
    // statikus token preferált
    if (DOGNET_API_TOKEN) {
      return dognet__normalize_token(DOGNET_API_TOKEN);
    }

    // ha kimenő HTTP limit aktív, engedélyezzük a Dognet hostot
    if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
      $allowed = defined('WP_ACCESSIBLE_HOSTS') ? WP_ACCESSIBLE_HOSTS : '';
      if (stripos($allowed, 'api.app.dognet.com') === false) {
        @define('WP_ACCESSIBLE_HOSTS', $allowed ? ($allowed.',api.app.dognet.com') : 'api.app.dognet.com');
      }
    }

    $cache_key = 'dognet_api_token_cache_v2';
    if (!$force_refresh) {
      $tok = get_transient($cache_key);
      if ($tok) return dognet__normalize_token($tok);
    }

    $login = impactshop__dognet_login_once(DOGNET_LOGIN_EMAIL, DOGNET_LOGIN_PASSWORD);
    if (!empty($login['ok']) && !empty($login['token'])) {
      set_transient($cache_key, $login['token'], DOGNET_TOKEN_TTL);
      return $login['token'];
    }

    return '';
  }
}

if (!function_exists('dognet_api_request')) {
  /**
   * Egységes Dognet hívó – 401/403/419 esetén egyszer frissít tokennel.
   * Statikus tokennél hiba esetén NEM próbál újra (nincs mit frissíteni).
   */
  function dognet_api_request($method, $path, $body=null) {
    $token = dognet_get_token(false);
    if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

    $url = (stripos($path,'http')===0 ? $path : rtrim(DOGNET_API_BASE,'/').$path);
    $args = [
      'timeout' => 25,
      'headers' => [
        'Authorization' => 'Bearer '.dognet__normalize_token($token),
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json'
      ],
      'method'  => $method,
    ];
    if ($body!==null) $args['body'] = wp_json_encode($body);

    $resp = wp_remote_request($url, $args);
    if (is_wp_error($resp)) return $resp;

    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);

    if (in_array($code, [401,403,419], true)) {
      // statikus token esetén nincs újrapróba
      if (DOGNET_API_TOKEN) {
        return new WP_Error('no_token','Dognet API statikus token érvénytelen/lejárt ('.$code.'). Frissítsd a DOGNET_API_TOKEN értékét.');
      }
      delete_transient('dognet_api_token_cache_v2');
      $token = dognet_get_token(true);
      if (!$token) return new WP_Error('no_token','Dognet API token frissítés sikertelen ('.$code.')');
      $args['headers']['Authorization'] = 'Bearer '.dognet__normalize_token($token);
      $resp = wp_remote_request($url, $args);
      if (is_wp_error($resp)) return $resp;
      $code = wp_remote_retrieve_response_code($resp);
      $json = json_decode(wp_remote_retrieve_body($resp), true);
    }

    if ($code < 200 || $code >= 300) {
      return new WP_Error('api_error','Dognet API hiba '.$code, ['resp'=>$json,'code'=>$code]);
    }
    return $json;
  }
}

/* Opcionális: kézi refresh/diag – csak az auth/login végpontot teszteli. */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_token']) && $_GET['impactshop_token']==='refresh') {
    delete_transient('dognet_api_token_cache_v2');
    $login = impactshop__dognet_login_once(DOGNET_LOGIN_EMAIL, DOGNET_LOGIN_PASSWORD);
    if (!empty($login['ok'])) {
      set_transient('dognet_api_token_cache_v2', $login['token'], DOGNET_TOKEN_TTL);
      wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop');
    }
    wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#b00">HIBA: auth/login nem adott tokent.</div>', 'ImpactShop');
  }
});
/* ==================== END DOGNET TOKEN PATCH ==================== */
Forrás a login-végponthoz és a 24 órás tokenhez: 
Ezt mondtad:
Írtam, hogy egyben kérem az egész snippetet.
A ChatGPT ezt mondta:

Gondolkozás
Az alábbi az EGÉSZ snippet a javított, stabilizált Dognet token-kezeléssel (csak az auth/login végpontot használjuk, a tokent normalizáljuk, cache-eljük, és 401/403/419 esetén egyszer frissítünk). Forrás a login-végponthoz és a 24 órás tokenhez: 
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (Dognet API auto-login + Redirect + UI + Banner highlight)
 * + ELSZÁMOLÁSOK (konverziók riport: shortcode + REST)
 *
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag],
 *               [impactshop_debug], [impactshop_report]
 * Endpontok:    /go és /go-deal   (slugot is elfogad: /go/{shop_slug})
 * REST:         /wp-json/impactshop/v1/totals?from=YYYY-MM-DD&to=YYYY-MM-DD&status=approved|pending|rejected|all
 * Megjegyzés:   aktiválás után egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');

/**
 * TOKEN: ha megadsz statikus JWT-t itt (csak a kód, "Bearer " NÉLKÜL), azt használjuk.
 * Ha üres, auto-login történik az auth/login végponton és cache-eljük ~24 órára.
 */
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', '');

if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 0); // 0: első "Web Site" jellegű csatorna
if (!defined('IMPACTSHOP_CACHE_TTL'))  define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS); // biztonsági ráhagyás a 24h-hoz
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');

/* ============================== BEÁLLÍTÁS ============================== */

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    // Banners lap (gid=328401803) – a &v= számot emeld cache-törléshez
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=4',
    'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
    'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
  ];
}

/* ============================ CSV SEGÉDEK ============================ */

function impactshop_slugify_header($s) {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
  $s = strtr($s, $map);
  $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
  return trim($s, '_');
}

function impactshop_fetch_csv_assoc($url, $cache_key, $ttl) {
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $resp = wp_remote_get($url, ['timeout'=>20]);
  if (is_wp_error($resp)) return [];
  $body = wp_remote_retrieve_body($resp);
  if (!$body) return [];

  if (substr($body,0,3) === "\xEF\xBB\xBF") $body = substr($body,3);
  $lines = preg_split("/\r\n|\n|\r/", $body);
  if (!$lines || count($lines) < 1) return [];

  $first = $lines[0];
  $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

  $headers_raw = str_getcsv($first, $delim);
  $headers = array_map('impactshop_slugify_header', $headers_raw);

  $rows = [];
  for ($i=1; $i<count($lines); $i++) {
    if ($lines[$i] === '' || $lines[$i] === false) continue;
    $cols = str_getcsv($lines[$i], $delim);
    if (count($cols) == 1 && $cols[0] === null) continue;
    $row = [];
    foreach ($headers as $idx=>$key) {
      $row[$key] = isset($cols[$idx]) ? trim($cols[$idx]) : '';
    }
    if (implode('', $row) === '') continue;
    $rows[] = $row;
  }

  set_transient($cache_key, $rows, $ttl);
  return $rows;
}

/* ======================= SHOPS & BANNERS ====================== */

function impactshop_get_shops_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
}
function impactshop_get_banners_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
}

function impactshop_get_shops() {
  $rows = impactshop_get_shops_raw();
  $out = [];
  foreach ($rows as $r) {
    $name   = $r['name'] ?? ($r['nev'] ?? '');
    $slug   = $r['shop_slug'] ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
    $cat    = $r['category'] ?? ($r['kategoria'] ?? 'Egyéb');
    $logo   = $r['logo_url'] ?? ($r['logo'] ?? ($r['image'] ?? ''));
    if (!$name || !$slug) continue;
    $out[] = [
      'name'           => $name,
      'shop_slug'      => $slug,
      'category'       => $cat ?: 'Egyéb',
      'logo'           => $logo,
      'dognet_base'    => $r['dognet_base'] ?? '',
      'deeplink_param' => ($r['pdognet_deeplink_param'] ?? ($r['dognet_deeplink_param'] ?? 'url')) ?: 'url',
      'product_url'    => $r['product_url'] ?? ($r['homepage'] ?? ''),
      'commission_min' => $r['commission_min'] ?? '',
      'commission_max' => $r['commission_max'] ?? '',
      'deals_feed'     => $r['deals_feed'] ?? '',
    ];
  }
  return $out;
}

function impactshop_get_banners() {
  $rows = impactshop_get_banners_raw();
  $out = [];
  foreach ($rows as $r) {
    $img   = $r['img'] ?? ($r['image'] ?? ($r['banner'] ?? ($r['kep'] ?? '')));
    $href  = $r['href'] ?? ($r['url']   ?? ($r['link']   ?? ''));
    $label = $r['label']?? ($r['cimke'] ?? ($r['title']  ?? 'Banner'));
    $cat   = $r['category'] ?? ($r['kategoria'] ?? '');
    if (!$img || !$href) continue;
    $out[] = ['img'=>$img,'href'=>$href,'label'=>$label,'category'=>$cat];
  }
  return $out;
}

/* ====================== KÖZÖS SEGÉDEK ====================== */

function impactshop_q($key, $def='') {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
}
function impactshop_find_shop($slug) {
  $slug = trim(strtolower($slug));
  foreach (impactshop_get_shops() as $s) {
    if (strtolower($s['shop_slug']) === $slug) return $s;
  }
  return null;
}
function impactshop_error($msg) {
  status_header(400);
  wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'ImpactShop hiba');
}

/* ====================== DOGNET API (TOKEN-KEZELÉS – FIX) ====================== */

/** Normalizáljuk a tokent: idézőjelek/„Bearer ” lecsupaszítása. */
function dognet__normalize_token($tok) {
  $tok = trim((string)$tok);
  $tok = preg_replace('~^(?:"|\')?(?:Bearer\s+)?([A-Za-z0-9\-\._]+)(?:"|\')?$~', '$1', $tok);
  return $tok ?: '';
}

/** Csak a dokumentált végpontot hívjuk: /api/v1/auth/login (JSON → form fallback). */
function impactshop__dognet_login_once($email, $password) {
  $endpoint = rtrim(DOGNET_API_BASE,'/').'/auth/login';

  // JSON próbálkozás
  $resp = wp_remote_post($endpoint, [
    'timeout' => 25,
    'headers' => ['Content-Type'=>'application/json','Accept'=>'application/json'],
    'body'    => wp_json_encode(['email'=>$email,'password'=>$password]),
    'redirection' => 3,
  ]);
  if (!is_wp_error($resp)) {
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $json = json_decode($body, true);
    $tok  = '';
    if (is_array($json)) {
      foreach (['token','access_token','data.token','data.access_token'] as $ck) {
        $val = $json;
        foreach (explode('.', $ck) as $p) { if (!is_array($val) || !array_key_exists($p,$val)) { $val=null; break; } $val=$val[$p]; }
        if (is_string($val) && $val!=='') { $tok = $val; break; }
      }
    }
    if ($code>=200 && $code<300 && $tok) return ['ok'=>true, 'token'=>dognet__normalize_token($tok)];
  }

  // FORM fallback
  $resp = wp_remote_post($endpoint, [
    'timeout' => 25,
    'headers' => ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json'],
    'body'    => http_build_query(['email'=>$email,'password'=>$password]),
    'redirection' => 3,
  ]);
  if (!is_wp_error($resp)) {
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);
    $json = json_decode($body, true);
    $tok  = '';
    if (is_array($json)) {
      foreach (['token','access_token','data.token','data.access_token'] as $ck) {
        $val = $json;
        foreach (explode('.', $ck) as $p) { if (!is_array($val) || !array_key_exists($p,$val)) { $val=null; break; } $val=$val[$p]; }
        if (is_string($val) && $val!=='') { $tok = $val; break; }
      }
    }
    if ($code>=200 && $code<300 && $tok) return ['ok'=>true, 'token'=>dognet__normalize_token($tok)];
  }

  return ['ok'=>false];
}

/**
 * Token megszerzése:
 * 1) Ha van statikus DOGNET_API_TOKEN → azt használjuk (normalizálva).
 * 2) Különben transient cache ('dognet_api_token_cache_v2'), lejárat: DOGNET_TOKEN_TTL.
 * 3) Login kizárólag /api/v1/auth/login végpontra (doc szerint).
 */
function dognet_get_token($force_refresh = false) {
  if (DOGNET_API_TOKEN) return dognet__normalize_token(DOGNET_API_TOKEN);

  // Ha WP szinten tiltva a kimenő forgalom, engedélyezzük a Dognet hostot
  if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
    $allowed = defined('WP_ACCESSIBLE_HOSTS') ? WP_ACCESSIBLE_HOSTS : '';
    if (stripos($allowed, 'api.app.dognet.com') === false) {
      @define('WP_ACCESSIBLE_HOSTS', $allowed ? ($allowed.',api.app.dognet.com') : 'api.app.dognet.com');
    }
  }

  $cache_key = 'dognet_api_token_cache_v2';
  if (!$force_refresh) {
    $tok = get_transient($cache_key);
    if ($tok) return dognet__normalize_token($tok);
  }

  $login = impactshop__dognet_login_once(DOGNET_LOGIN_EMAIL, DOGNET_LOGIN_PASSWORD);
  if (!empty($login['ok']) && !empty($login['token'])) {
    set_transient($cache_key, $login['token'], DOGNET_TOKEN_TTL);
    return $login['token'];
  }

  return '';
}

/** API kérés egységesen – 401/403/419-nél egyszer megpróbál új tokennel ismételni. */
function dognet_api_request($method, $path, $body=null) {
  $token = dognet_get_token(false);
  if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

  $url = (stripos($path,'http')===0 ? $path : rtrim(DOGNET_API_BASE,'/').$path);
  $args = [
    'timeout' => 25,
    'headers' => ['Authorization'=>'Bearer '.dognet__normalize_token($token),'Content-Type'=>'application/json','Accept'=>'application/json'],
    'method'  => $method,
  ];
  if ($body!==null) $args['body'] = wp_json_encode($body);

  $resp = wp_remote_request($url, $args);
  if (is_wp_error($resp)) return $resp;

  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);

  if (in_array($code, [401,403,419], true)) {
    // statikus token esetén nincs mit frissíteni → azonnal hiba
    if (DOGNET_API_TOKEN) {
      return new WP_Error('no_token','Dognet API statikus token érvénytelen/lejárt ('.$code.'). Frissítsd a DOGNET_API_TOKEN értékét.');
    }
    delete_transient('dognet_api_token_cache_v2');
    $token = dognet_get_token(true);
    if (!$token) return new WP_Error('no_token','Dognet API token frissítés sikertelen ('.$code.')');
    $args['headers']['Authorization'] = 'Bearer '.dognet__normalize_token($token);
    $resp = wp_remote_request($url, $args);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
  }

  if ($code < 200 || $code >= 300) return new WP_Error('api_error','Dognet API hiba '.$code,['resp'=>$json,'code'=>$code]);
  return $json;
}

function dognet_extract_campaign_id_from_base($dognet_base) {
  if (!$dognet_base) return 0;
  $qs = parse_url($dognet_base, PHP_URL_QUERY);
  parse_str($qs,$parts);
  return isset($parts['cid']) ? intval($parts['cid']) : 0;
}

function dognet_api_pick_ad_channel_id() {
  if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
  $list = dognet_api_request('GET','/ad-channels');
  if (is_wp_error($list) || empty($list['data'])) return 0;
  foreach ($list['data'] as $c) {
    if (isset($c['type']) && stripos($c['type'],'web')!==false) return intval($c['id']);
  }
  return intval($list['data'][0]['id']);
}

function dognet_api_generate_link($campaign_id,$deeplink='',$d1='',$d2='') {
  $ad_channel_id = dognet_api_pick_ad_channel_id();
  if (!$ad_channel_id) return new WP_Error('no_channel','Nincs ad_channel');
  $body = ['ad_channel_id'=>$ad_channel_id,'campaign_id'=>intval($campaign_id),'url_type'=>3];
  if ($deeplink) $body['url']=$deeplink;
  if ($d1) $body['data1']=$d1;
  if ($d2) $body['data2']=$d2;
  $json = dognet_api_request('POST','/campaigns/links/generate',$body);
  if (is_wp_error($json)) return $json;
  foreach(['url','short_url','full_url'] as $k){
    if(!empty($json[$k])) return $json[$k];
    if(!empty($json['data'][$k])) return $json['data'][$k];
  }
  return new WP_Error('bad_api','Ismeretlen API válasz');
}

/* ==================== REDIRECTEK =================== */

function impactshop_add_rewrites() {
  add_rewrite_rule('^go/([^/]+)/?$',      'index.php?impactshop_go=1&impactshop_slug=$matches[1]',   'top');
  add_rewrite_rule('^go/?$',              'index.php?impactshop_go=1',                                'top');
  add_rewrite_rule('^go-deal/([^/]+)/?$', 'index.php?impactshop_deal=1&impactshop_slug=$matches[1]', 'top');
  add_rewrite_rule('^go-deal/?$',         'index.php?impactshop_deal=1',                              'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[]='impactshop_go';
  $vars[]='impactshop_deal';
  $vars[]='impactshop_slug';
  return $vars;
}
add_filter('query_vars','impactshop_add_query_vars');

add_action('template_redirect',function(){
  if(get_query_var('impactshop_go')){impactshop_handle_go(false);exit;}
  if(get_query_var('impactshop_deal')){impactshop_handle_go(true);exit;}
});

function impactshop_redirect_with_propagation($url,$amb,$src) {
  $add=[];
  if($amb&&strpos($url,'amb=')===false)$add['amb']=$amb;
  if($src&&strpos($url,'src=')===false)$add['src']=$src;
  if(strpos($url,'utm_source=')===false)$add['utm_source']='sharity';
  if(strpos($url,'utm_medium=')===false)$add['utm_medium']='impactshop';
  if($add)$url.=(strpos($url,'?')===false?'?':'&').http_build_query($add);
  wp_redirect($url,307);exit;
}

function impactshop_handle_go($is_deal) {
  $shop=impactshop_q('shop'); if(!$shop){ $shop = get_query_var('impactshop_slug'); }
  $ngo=impactshop_q('d1');$u=impactshop_q('u');
  $amb=impactshop_q('amb');$src=impactshop_q('src')?:'impactshop';
  if(!$shop||!$ngo)impactshop_error('Hiányzó paraméter (shop/d1).');
  $row=impactshop_find_shop($shop);if(!$row)impactshop_error('Ismeretlen shop: '.esc_html($shop));

  $targetUrl='';
  if($is_deal){ $targetUrl=$u?:($row['product_url']??''); }

  $final=null;$cid=dognet_extract_campaign_id_from_base($row['dognet_base']??'');
  if($cid){
    $deeplink=$targetUrl;
    if($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~',$deeplink)){
      $tmp=base64_decode($deeplink,true);
      if($tmp!==false && preg_match('~^https?://~i',$tmp)) $deeplink=$tmp;
    }
    $api=dognet_api_generate_link($cid,$deeplink,$ngo,'');
    if(!is_wp_error($api) && $api) $final=$api;
  }

  if(!$final){
    $base=$row['dognet_base']??'';
    if($base){
      $params=['d1'=>$ngo];
      if(!empty($targetUrl)){
        $deeplink = $targetUrl;
        if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
          $tmp = base64_decode($deeplink, true);
          if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
        }
        $deeplinkParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
        $params[$deeplinkParam] = $deeplink;
      }
      $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($params);
    }
  }

  if(!$final)impactshop_error('Nem sikerült a partner linket előállítani.');
  impactshop_redirect_with_propagation($final,$amb,$src);
}

/* ==================== SHORTCODE-OK =================== */

/** Scroller */
function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts(['category'=>'','inject_every'=>5,'speed'=>30], $atts);
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  if (!empty($a['category'])) {
    $catWanted = $a['category'];
    $shops   = array_values(array_filter($shops,   fn($s)=> strcasecmp($s['category'],$catWanted)===0 ));
    $banners = array_values(array_filter($banners, fn($b)=> empty($b['category']) || strcasecmp($b['category'],$catWanted)===0 ));
  }
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  $injectEvery = max(1, intval($a['inject_every']));
  $mixed = []; $bi = 0;
  foreach ($shops as $i=>$s) {
    $mixed[] = ['type'=>'shop','data'=>$s];
    if (($i+1)%$injectEvery===0 && $banners) {
      $mixed[] = ['type'=>'banner','data'=>$banners[$bi % count($banners)]];
      $bi++;
    }
  }
  $stream = array_merge($mixed, $mixed);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <style>
    .impactshop-scroller{overflow:hidden;width:100%;white-space:nowrap;position:relative}
    .impactshop-scroller-track{display:inline-block;white-space:nowrap;animation:impactshop-scroll linear infinite}
    @keyframes impactshop-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .impactshop-scroller:hover .impactshop-scroller-track{animation-play-state:paused}
    .impactshop-item{display:inline-block;margin-right:20px;position:relative}
    .impactshop-item.shop-item img{height:60px;width:auto}
    .impactshop-item.banner-item img{height:100px;width:auto;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .impactshop-badge{position:absolute;top:-8px;left:-8px;background:#ff3366;color:#fff;font:600 11px/1 system-ui;padding:4px 6px;border-radius:6px}
    @media (max-width:640px){ .impactshop-item.banner-item img{height:90px} }
  </style>
  <div class="impactshop-scroller">
    <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
      <?php foreach ($stream as $it): ?>
        <?php if ($it['type']==='shop'): $s=$it['data'];
          $cta = $d1
            ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($s['shop_slug'])))
            : add_query_arg(['shop'=>$s['shop_slug'],'amb'=>$amb], $fillout); ?>
          <div class="impactshop-item shop-item">
            <a href="<?php echo esc_url($cta); ?>">
              <img src="<?php echo esc_url($s['logo']); ?>" alt="<?php echo esc_attr($s['name']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php else: $b=$it['data']; ?>
          <div class="impactshop-item banner-item">
            <span class="impactshop-badge">AKCIÓ</span>
            <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
              <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_scroller','impactshop_shortcode_scroller');

/** Katalógus */
function impactshop_shortcode_catalog($atts) {
  $a = shortcode_atts(['show_tabs'=>'1','search'=>'1','per_page'=>'200'], $atts);
  $shops = impactshop_get_shops();
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  usort($shops, function($x,$y){
    $c = strcasecmp($x['category'],$y['category']);
    return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
  });

  $cats = []; foreach ($shops as $s) { $cats[$s['category']] = true; }
  $categories = array_keys($cats);
  sort($categories, SORT_NATURAL|SORT_FLAG_CASE);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <div class="impactshop-catalog">
    <?php if ($a['show_tabs']==='1'): ?>
      <ul class="impactshop-tabs" style="list-style:none;padding:0;margin:0 0 12px 0;display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
        <?php foreach ($categories as $i=>$cat): ?>
          <li data-cat="<?php echo esc_attr($cat); ?>" class="<?php echo $i===0?'active':''; ?>"
              style="cursor:pointer;padding:8px 12px;border-radius:999px;background:#f3f3f3;font-weight:600;font-size:14px;">
            <?php echo esc_html($cat); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($a['search']==='1'): ?>
      <input type="text" class="impactshop-search" placeholder="Keresés…" aria-label="Keresés"
             style="width:100%;max-width:520px;margin:0 auto 10px auto;display:block;padding:8px;border:1px solid #ddd;border-radius:8px;">
    <?php endif; ?>

    <div class="impactshop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;align-items:start;">
      <?php foreach ($shops as $shop):
        $cta = $d1
          ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])))
          : add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout); ?>
        <div class="impactshop-card" data-cat="<?php echo esc_attr($shop['category']); ?>" style="text-align:center;">
          <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
            <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                 loading="lazy" decoding="async" style="height:60px;width:auto;max-width:100%">
          </a>
          <div style="font-size:14px;font-weight:600;margin-top:6px;"><?php echo esc_html($shop['name']); ?></div>
          <?php
          $min = trim($shop['commission_min']); $max = trim($shop['commission_max']);
          $minN = is_numeric(str_replace('%','',$min)) ? floatval(str_replace('%','',$min)) : null;
          $maxN = is_numeric(str_replace('%','',$max)) ? floatval(str_replace('%','',$max)) : null;
          if ($minN !== null || $maxN !== null) {
            if ($minN !== null && $maxN !== null && $minN !== $maxN) {
              $don = ($minN/2) .'–'. ($maxN/2).'%';
            } else {
              $v = ($minN !== null) ? $minN : $maxN;
              $don = ($v/2).'%';
            }
            echo '<div style="font-size:12px;color:#666;margin-top:2px;">Várható adomány: ~'. esc_html($don) .'</div>';
          }
          ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    if(!root) return;
    var tabs = root.querySelectorAll('.impactshop-tabs li');
    var search = root.querySelector('.impactshop-search');
    var cards = root.querySelectorAll('.impactshop-card');
    function apply() {
      var term = search ? (search.value || '').toLowerCase() : '';
      var active = root.querySelector('.impactshop-tabs li.active');
      var cat = active ? active.getAttribute('data-cat') : null;
      Array.prototype.forEach.call(cards, function(card){
        var okCat = !cat || card.getAttribute('data-cat') === cat;
        var nameEl = card.querySelector('div');
        var name = nameEl ? (nameEl.textContent || '').toLowerCase() : '';
        var okQ = !term || name.indexOf(term) !== -1;
        card.style.display = (okCat && okQ) ? '' : 'none';
      });
    }
    Array.prototype.forEach.call(tabs, function(tab){
      tab.addEventListener('click', function(){
        Array.prototype.forEach.call(tabs, function(t){ t.classList.remove('active'); });
        tab.classList.add('active');
        apply();
      });
    });
    if (search) search.addEventListener('input', apply);
    apply();
  })();
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_catalog','impactshop_shortcode_catalog');

/** Diagnosztika */
function impactshop_shortcode_diag() {
  $shops = impactshop_get_shops();
  $out = '<div style="font:14px/1.5 system-ui"><h3>Impact Shop diagnosztika</h3>';
  $out .= '<p>Shops betöltve: <b>'.count($shops).'</b></p>';
  $miss = [];
  foreach ($shops as $s) {
    if (empty($s['dognet_base']))    $miss[] = $s['shop_slug'].' (dognet_base)';
    if (empty($s['deeplink_param'])) $miss[] = $s['shop_slug'].' (pdognet_deeplink_param)';
  }
  if ($miss) {
    $out .= '<p style="color:#b00">Hiányzó mezők:<br>'.esc_html(implode(', ', $miss)).'</p>';
  } else {
    $out .= '<p style="color:#070">dognet_base + deeplink_param mindenhol rendben.</p>';
  }
  $out .= '</div>';
  return $out;
}
add_shortcode('impactshop_diag','impactshop_shortcode_diag');

/** Egyszerű debug */
function impactshop_shortcode_debug() {
  $s = impactshop_settings();
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();
  $demo = [
    'img'=> home_url('/wp-content/uploads/2025/09/log.jpeg'),
    'href'=> 'https://form.fillout.com/t/eM61RLkz6jus?shop=4home&u='.rawurlencode('https://www.4home.hu/'),
    'label'=> 'Ajánlatok – 4home', 'category'=> 'Otthon'
  ];
  ob_start(); ?>
  <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;padding:12px;border-radius:8px">
Shops: <?php echo count($shops); ?> | Banners: <?php echo count($banners); ?>

shops_csv_url:  <?php echo esc_html($s['shops_csv_url']); ?>

banners_csv_url: <?php echo esc_html($s['banners_csv_url']); ?>


Minta banner: <?php echo esc_html(json_encode($demo, JSON_UNESCAPED_UNICODE)); ?>

  </pre>
  <?php return ob_get_clean();
}
add_shortcode('impactshop_debug','impactshop_shortcode_debug');

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});

/* ================================================================== */
/* ==================  E L S Z Á M O L Á S O K  (ÚJ)  ================ */
/* ================================================================== */

/** Konverziók listázása — oldalanként */
function dognet_api_list_conversions_page($from, $to, $status='all', $page=1, $perPage=200) {
  $qs = ['from'=>$from,'to'=>$to,'page'=>max(1,intval($page)),'per_page'=>max(1,min(500,intval($perPage)))];
  if ($status && $status!=='all') $qs['status']=$status;
  $path = '/conversions?'. http_build_query($qs);
  return dognet_api_request('GET', $path);
}

/** Konverziók begyűjtése minden oldalról */
function dognet_api_list_conversions_all($from, $to, $status='all', $maxPages=80, $perPage=200) {
  $all=[]; $page=1;
  for (; $page<=$maxPages; $page++) {
    $resp = dognet_api_list_conversions_page($from,$to,$status,$page,$perPage);
    if (is_wp_error($resp)) return ['error'=>$resp];
    $items=[];
    if (isset($resp['data']['items']) && is_array($resp['data']['items'])) {
      $items=$resp['data']['items'];
      $cur=intval($resp['data']['current_page'] ?? $page);
      $tot=intval($resp['data']['total_pages'] ?? $page);
      $all=array_merge($all,$items);
      if ($cur >= $tot || empty($items)) break;
    } elseif (isset($resp['items']) && is_array($resp['items'])) {
      $items=$resp['items']; $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } elseif (isset($resp['data']) && is_array($resp['data'])) {
      $items=array_values(array_filter($resp['data'],'is_array'));
      $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } elseif (is_array($resp)) {
      $items=array_values(array_filter($resp,'is_array'));
      $all=array_merge($all,$items);
      if (count($items) < $perPage) break;
    } else break;
  }
  return ['items'=>$all,'next_page'=>($page+1)];
}

/** Konverzió rekord normalizálása */
function impactshop_norm_conversion($row) {
  $out = ['campaign_id'=>0,'status'=>'','data1'=>'','order_value'=>0.0,'commission'=>0.0,'currency'=>''];
  foreach (['campaign_id','campaignId','cid','campaign'] as $k) if (isset($row[$k])) { $out['campaign_id']=intval(is_array($row[$k])?($row[$k]['id']??0):$row[$k]); break; }
  foreach (['status','state'] as $k) if (!empty($row[$k])) { $out['status']=strtolower(trim($row[$k])); break; }
  foreach (['data1','d1','ref1','subid','sub_id','sub_id1'] as $k) if (isset($row[$k])) { $out['data1']=trim((string)$row[$k]); break; }
  foreach (['currency','cur'] as $k) if (!empty($row[$k])) { $out['currency']=strtoupper(trim($row[$k])); break; }
  foreach (['order_value','sale_amount','amount','price','orderAmount','total'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['order_value']=floatval($row[$k]); break; }
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['commission']=floatval($row[$k]); break; }
  return $out;
}

/** cid↔shop map */
function impactshop_build_campaign_map() {
  $shops = impactshop_get_shops();
  $by_cid = []; $by_slug = [];
  foreach ($shops as $s) {
    $cid = dognet_extract_campaign_id_from_base($s['dognet_base'] ?? '');
    $slug = $s['shop_slug']; $name = $s['name'];
    if ($cid) { $by_cid[$cid] = ['slug'=>$slug, 'name'=>$name]; }
    if ($slug) { $by_slug[$slug] = $cid; }
  }
  return ['by_cid'=>$by_cid, 'by_slug'=>$by_slug];
}

/** Aggregálás shop×NGO/NGO/shop */
function impactshop_aggregate_conversions($from, $to, $status='approved', $group='shop_ngo') {
  $cache_key = sprintf('impactshop_totals_%s_%s_%s_%s', $from, $to, $status, $group);
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return ['rows'=>[], 'meta'=>['error'=>$res['error']->get_error_message()]];
  }
  $items = $res['items'] ?? [];
  $maps = impactshop_build_campaign_map();
  $by_cid = $maps['by_cid'];

  $rows = []; $grand = ['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
  foreach ($items as $it) {
    $x = impactshop_norm_conversion($it);
    $cid = $x['campaign_id'];
    $ngo = $x['data1'] ?: '(nincs d1)';
    $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
    if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

    if ($group === 'ngo') {
      $key=$ngo;
      if (!isset($rows[$key])) $rows[$key]=['ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'shops'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['shops'][$shopSlug] = ($rows[$key]['shops'][$shopSlug] ?? 0) + $x['commission'];
    } elseif ($group === 'shop') {
      $key=$shopSlug;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'ngos'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['ngos'][$ngo] = ($rows[$key]['ngos'][$ngo] ?? 0) + $x['commission'];
    } else {
      $key = $shopSlug.'||'.$ngo;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
    }

    $grand['orders'] += 1;
    $grand['order_value'] += $x['order_value'];
    $grand['commission']  += $x['commission'];
  }

  $rows = array_values($rows);
  usort($rows, function($a,$b){
    $da = $b['commission'] <=> $a['commission'];
    if ($da !== 0) return $da;
    return strcasecmp(($a['shop_name'] ?? $a['ngo'] ?? ''), ($b['shop_name'] ?? $b['ngo'] ?? ''));
  });

  $out = ['rows'=>$rows, 'meta'=>[
    'from'=>$from,'to'=>$to,'status'=>$status,'group'=>$group,
    'grand'=>$grand,'count'=>count($rows),'generated_at'=>current_time('mysql')
  ]];

  $ttl = impactshop_settings()['cache_ttl'] ?? (15 * MINUTE_IN_SECONDS);
  set_transient($cache_key, $out, $ttl);
  return $out;
}

/* ==================== REST – JSON ÖSSZESÍTÉS ==================== */

add_action('rest_api_init', function() {
  register_rest_route('impactshop/v1', '/totals', [
    'methods'  => 'GET',
    'callback' => function(WP_REST_Request $req){
      $from   = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to     = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $status = sanitize_text_field($req->get_param('status') ?: 'approved'); // alap: csak jóváhagyott
      $group  = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $data = impactshop_aggregate_conversions($from, $to, $status, $group);
      if (!empty($data['meta']['error'])) {
        return new WP_Error('dognet_err', 'Dognet API hiba: '.$data['meta']['error'], ['status'=>502]);
      }
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true'
  ]);
});

/* ==================== SHORTCODE – HTML RIPORT ==================== */

function impactshop_shortcode_report($atts) {
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',           // approved|pending|rejected|all
    'group'  => 'shop_ngo',           // shop_ngo|ngo|shop
  ], $atts);

  $data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group']);
  if (!empty($data['meta']['error'])) {
    return '<div style="color:#b00">Dognet API hiba: '. esc_html($data['meta']['error']) .'</div>';
  }
  $rows = $data['rows']; $grand = $data['meta']['grand'];

  ob_start(); ?>
  <div class="impactshop-report" style="font:14px/1.5 system-ui">
    <div style="margin:8px 0 12px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> &nbsp; |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> &nbsp; |
      <b>Bontás:</b> <?php echo esc_html($a['group']); ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead>
          <tr style="background:#f6f7f8">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Szervezet (data1)</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
            <?php elseif ($a['group']==='shop'): ?>
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
              <?php if ($a['group']==='ngo'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php elseif ($a['group']==='shop'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name'].' ('.$r['shop_slug'].')'); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php else: ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php elseif ($a['group']==='shop'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php else: ?>
              <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php endif; ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <div style="color:#777;margin-top:8px;font-size:12px">Frissítve: <?php echo esc_html($data['meta']['generated_at']); ?> · Forrás: Dognet API</div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_report','impactshop_shortcode_report');

/* ==================== Opcionális: auto-blokk „impactshop-report” oldalra ==================== */
add_filter('the_content', function($content){
  if (is_page() && function_exists('get_post') && ($p=get_post()) && is_string($p->post_name) && $p->post_name==='impactshop-report') {
    if (strpos($content,'[impactshop_report')===false) $content .= "\n\n[impactshop_report]";
  }
  return $content;
});

/* ==================== KÉZI DOGNET TOKEN REFRESH / DIAG ==================== */
/* Admin joggal hívható:
   - frissítés:  /?impactshop_token=refresh
   - hiba esetén üzenet: /?impactshop_token=refresh
   (KIZÁRÓLAG az auth/login végpontot teszteli.)
*/
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_token']) && $_GET['impactshop_token']==='refresh') {
    delete_transient('dognet_api_token_cache_v2');

    $login = impactshop__dognet_login_once(DOGNET_LOGIN_EMAIL, DOGNET_LOGIN_PASSWORD);
    if (!empty($login['ok'])) {
      set_transient('dognet_api_token_cache_v2', $login['token'], DOGNET_TOKEN_TTL);
      wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop');
    }

    // Részletezés nélkül (CF oldalak miatt rövid)
    wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#b00">HIBA: auth/login nem adott tokent.</div>', 'ImpactShop');
  }
});
Ezt mondtad:
teszteljük
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Oké, menjünk végig a gyors „füstteszten” – csak a tokenre fókuszálva. A mostani snippet az /api/v1/auth/login végponttal kér tokent, 24 órára érvényes és minden használattal hosszabbodik; az összes többi (403-as CF-oldalra futó) login-végpont ki van iktatva. 
Kézi tokenfrissítés (admin userrel)
Nyisd meg: https://app.sharity.hu/?impactshop_token=refresh
Elvárt: zöld „OK: új Dognet token cache-ben.”
Ha piros hiba, a Dognet nem adott vissza token mezőt (ritka), ilyenkor érdemes még egyszer rányomni pár másodperc múlva.
Ellenőrizd, hogy tényleg cache-elte
WP-CLI (ha van):
wp transient get dognet_api_token_cache_v2
Elvárt: egy rövid JWT-karakterlánc. (A snippet a Bearer előtagot automatikusan lecsupaszítja.)
Ha nincs WP-CLI: ideiglenesen tedd ki egy debug shortcode-ba:
echo substr( get_transient('dognet_api_token_cache_v2'), -12 );
Éles API-hívás a tokennel (automata újrapróbálással)
Hívd meg a belső riportot:
https://app.sharity.hu/wp-json/impactshop/v1/totals?from=2025-09-01&to=2025-09-20&status=approved&group=shop_ngo
Elvárt: HTTP 200 JSON (ha nincs adat, üres összesítés is rendben).
A háttérben a dognet_api_request() már az új tokent használja; 401/403/419 esetén egyszer automatikusan újraloginol.
Kényszerített „lejárat” teszt
Töröld a cache-t: wp transient delete dognet_api_token_cache_v2 (vagy /?impactshop_token=refresh újra).
Utána ismét hívd a /totals végpontot; elvárt: új token kerül a cache-be és 200-as válasz jön.
Statikus token út (csak ha akarod)
Ha a DOGNET_API_TOKEN konstansba bemásolsz egy JWT-t (csak a kódot, „Bearer ” nélkül), a rendszer azt fogja használni, és nem próbál automatikusan frissíteni. Hibás statikus tokennél tudatosan kapsz egy egyértelmű hibát.
Ha ez mind zöld, a token-flow fix. Következő körben lehet finomhangolni a raw-transactions lekérdezést last_id lapozásra a gyorsabb riportokért, de az már a tokenen túlmutat.
Ezt mondtad:
Feltöltött kép
{"code":"dognet_err","message":"Dognet API hiba: Dognet API hiba 404","data":{"status":502}}
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Szuper: a token OK, a hiba nem token, hanem végpont—mi még a nem létező /conversions-t hívtuk, a Dognet doksi szerint a jutalékok a /raw-transactions alatt érhetők el (szűrés: POST /raw-transactions/filter), lapozás pedig last_id-dal megy. Javítás = csak a konverziós lekérdezés részek cseréje.
Tedd be ezeket a függvényeket a helyükre (felülírva a régieket):

/** Konverziók begyűjtése – Dognet RAW TRANSACTIONS (status + dátum + last_id lapozás) */
function dognet__status_map($status){
  // approved|pending|rejected|all  →  A|P|D (Dognet rstatus)
  $status = strtolower(trim($status));
  if ($status==='approved') return ['A'];
  if ($status==='pending')  return ['P'];
  if ($status==='rejected') return ['D'];
  return []; // all → nincs filter
}

/** Egy "oldal" (batch) kérése last_id szerint */
function dognet_api_list_conversions_batch($from, $to, $status='all', $lastId=null, $perPage=200) {
  // Dátum-intervallum a doksi szerint datetime-alapon szűr (00:00:00 – 23:59:59)
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';

  $filter = [
    ['created_at' => ['gte' => $fromDt]],
    ['created_at' => ['lte' => $toDt]],
  ];
  $rstatus = dognet__status_map($status);
  if ($rstatus) $filter[] = ['rstatus' => ['in' => $rstatus]];

  $body = [
    'per-page' => max(1, min(1000, intval($perPage))),
    'filter'   => $filter,
  ];
  if ($lastId !== null) $body['last_id'] = intval($lastId);

  $resp = dognet_api_request('POST','/raw-transactions/filter',$body);
  if (is_wp_error($resp)) return ['error'=>$resp];

  // Válasz normalizálása (doksi: data + meta.last_id) – óvatos fallbackekkel
  $items = [];
  if (isset($resp['data']) && is_array($resp['data']))       $items = $resp['data'];
  elseif (isset($resp['items']) && is_array($resp['items'])) $items = $resp['items'];

  $nextLastId = null;
  if (isset($resp['meta']['last_id'])) {
    $nextLastId = intval($resp['meta']['last_id']);
  } elseif ($items) {
    // ha nincs meta.last_id, vegyük a legnagyobb id-t a rekordokból (óvatosan)
    $maxId = null;
    foreach ($items as $it) {
      foreach (['id','transaction_id','tid'] as $k) {
        if (isset($it[$k]) && is_numeric($it[$k])) { $maxId = max(intval($it[$k]), intval($maxId)); break; }
      }
    }
    if ($maxId !== null) $nextLastId = $maxId;
  }

  return ['items'=>$items, 'last_id'=>$nextLastId];
}

/** Teljes begyűjtés last_id görgetéssel */
function dognet_api_list_conversions_all($from, $to, $status='all', $maxBatches=200, $perPage=200) {
  $all=[]; $lastId=null;
  for ($i=0; $i<$maxBatches; $i++) {
    $batch = dognet_api_list_conversions_batch($from,$to,$status,$lastId,$perPage);
    if (isset($batch['error']) && is_wp_error($batch['error'])) return ['error'=>$batch['error']];
    $items = $batch['items'] ?? [];
    if (!$items) break;
    $all = array_merge($all, $items);
    $lastId = $batch['last_id'] ?? null;
    if ($lastId === null) break; // nincs további last_id → megállunk
  }
  return ['items'=>$all];
}
Mi változott?
/conversions → /raw-transactions/filter (doksi szerinti helyes végpont). 
Állapot-szűrés approved|pending|rejected → rstatus in ["A","P","D"]. 
Lapozás last_id-dal, ahogy le van írva, nem page/per_page-zel (gyors és 10k+ rekordnál is működik). 
Gyors ellenőrzés:
Hagyd a zöld „OK token” állapotot, ne frissíts újra.
Hívd meg újra:
/wp-json/impactshop/v1/totals?from=2025-09-01&to=2025-09-20&status=approved&group=shop_ngo
Várható: 200-as JSON (ha nincs adat, üres aggregáció is rendben).
Ha szeretnéd, a következő körben át tudom állítani az összegző cache TTL-t és be tudom építeni az ad_channel id szerinti szűrést is, hogy csak a releváns csatorna forgalma jöjjön.
Ezt mondtad:
nem tudom így kell e: https://app.sharity.hu//wp-json/impactshop/v1/totals?from=2025-09-01&to=2025-09-20&status=approved&group=shop_ngo : {"rows":[],"meta":{"from":"2025-09-01","to":"2025-09-20","status":"approved","group":"shop_ngo","grand":{"orders":0,"order_value":0,"commission":0},"count":0,"generated_at":"2025-09-20 06:54:24"}}
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igen, így kell hívni – az URL rendben van. A 200-as válasz üres tömbbel azt jelenti, hogy a megadott tartományban (2025-09-01 → 2025-09-20) nincs “approved” tranzakció a Publisher API szerint. A helyes végpont a /raw-transactions/filter, a státuszok pedig A|P|D (approved, pending, declined), és lapozás last_id-dal működik – ezekre épít a snippet. 
Gyors ellenőrzéshez próbáld ki szélesebb és lazább szűréssekkel:

…/totals?from=2025-08-01&to=2025-09-20&status=all&group=shop_ngo
vagy csak a legutóbbi hetet: …/totals?from=2025-09-13&to=2025-09-20&status=all
Ha ezek valamelyike már hoz adatot, a pipeline jó, csak a korábbi tartományban tényleg nincs “approved” tétel. (A Dognet dokumentáció is ezt a használatot írja: /raw-transactions + szűrők + last_id görgetés. )
Választható finomhangolás (1 sor)
Ha több csatornád van a fiókban, érdemes lehet csatorna szerint szűrni, hogy garantáltan a megfelelő adat jöjjön. Ehhez a már beépített DOGNET_AD_CHANNEL_ID konstansra támaszkodva tegyük be az ad-channel szűrőt (csak ha ≠0):
A dognet_api_list_conversions_batch() függvényben, ahol a $filter épül, illeszd be ezt a sort:

if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID) $filter[] = ['ad_channel_id' => ['eq' => intval(DOGNET_AD_CHANNEL_ID)]];
A Dognet filter-szintaxis pontosan ezt várja ad_channel_id-ra. 
Ha szeretnéd, adhatok egy mini diagnosztika endpointot is, ami nyersen visszaadja a raw-transactions/filter első néhány elemét ugyanazzal a szűréssel – csak szólj, és beépítem.

Ezt mondtad:
egy van, de ez pending, csak akkor írja ki, ha approved? 
Arukereso.hu
0.04 €
0.00 €
9c000c3874013357fb91739fd21addb3
1
9/18/2025, 8:34:09 AM
Sharity
Pending
bator-tabor-alapitvany
45961206
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igen: most csak az approved tételeket aggregáljuk, mert a snippet alapértelmezettje status="approved". A „Pending” konverziót emiatt nem látod a riportban. A Dognet API státuszai: A=approved, P=pending, D=declined — ezekre szűrünk a /raw-transactions/filter hívásban.
Hogyan lásd a pendinget is?
REST URL-ben: tedd status=all-ra (vagy pending-re):
…/wp-json/impactshop/v1/totals?from=2025-09-01&to=2025-09-20&status=all&group=shop_ngo
Shortcode-ban: add meg a státuszt:
[impactshop_report from="2025-09-01" to="2025-09-20" status="all" group="shop_ngo"]
Mit várj az értékektől?
Pending tételek megjelennek status=all vagy status=pending mellett, de a commission gyakran 0 marad, amíg jóvá nem hagyják — ez normális, a Dognet így adja vissza. Összesítéskor nálunk is 0-ként kerül be, és majd approved-ra váltva nő meg. 
Ha szeretnéd, be tudok rakni egy gyors “debug listát”, ami nyersen kilövi az első 10 pending tranzakciódat ugyanazzal a szűréssel, hogy vizuálisan is lásd, mit küld a Dognet.
Ezt mondtad:
Feltöltött kép
Feltöltött kép
működik, kell még tesztelni valamit, vagy így rendben vagyunk? az Apps Script minden google és arukereso xml-t lehív
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Igen, ez most rendben működik. A token stabil (zöld „OK”), az összesítés pedig már a helyes /raw-transactions/filter végpontra épül last_id-os görgetéssel és a megfelelő státuszkódokkal (A/P/D).
Itt egy rövid „átadás előtti” ellenőrzőlista — ha ez mind zöld, késznek tekinthető:

Státuszok: REST és shortcode alatt próbáld végig approved, pending, all. A pending soroknál a jutalék jellemzően 0 marad — ez API-viselkedés. 
Lapozás terhelés alatt: nagyobb időablakkal kérj le sok rekordot (pl. 2–3 hónap, status=all) és nézd meg, hogy nincs „lyuk” a görgetésben (rekordszám stabil, timeout nélkül). A snippet a doksi szerinti last_id-ot használja, ez a javasolt út. 
Csatornaszűrés (ha releváns): ha fix ad channel-t akarsz, tedd be a DOGNET_AD_CHANNEL_ID-t és szűrésként illesztve is ellenőrizd, hogy csak az a csatorna jelenik meg. 
Időzóna/pénznem: a login-válasz megadja a timezone/currency metát; nézd meg, hogy a „nap vége” szűrésed (23:59:59) nem vág le rekordot, és EUR környezetben helyesek a formázások.
Token-újrapróba: kényszeríts 401/403-at (transient törlés, majd gyors egymásutánban hívások). Várt viselkedés: egyszeri automata relogin és folytatás; statikus tokennél érthető hibaüzenet.
/go és /go-deal útvonalak: base64-es u= parammal és sima URL-lel is teszteld, hogy a deeplink-param neve a táblázatból helyesen kerül a linkre, valamint hogy amb/src/utm_* propagálódik.
Kampányazonosító: mintavételesen ellenőrizd, hogy a cid→shop map jól párosít (pl. Arukereso ↔ helyes cid).
Riport UI: „nincs adat” ágon korrekt üzenet jön-e; nagy adatmennyiségnél az összegző sorok (orders, commission) konzisztensen nőnek-e.
Ami már kipipálva látszik nálad:
Tokenlogin az /auth/login-ra → OK; 24h token, minden használat hosszabbítja.
Konverziók a /raw-transactions/filter alatt, rstatus szűrővel → OK.
status=all-lal megjönnek a pending sorok; az üres kosárérték/jutalék pendingnél várható.
Mellékszál: jelezted, hogy az Apps Script már lehívja a Google- és Árukereső-XML-eket — ez tökéletes, nem ütközik a Dognet rate limitjeivel (240 req/perc auth mellett bőven elfér). 
Ha a fenti checklist is zöld, én ezt élesre késznek tekintem. Következő értelmes lépés lehet egy apró „debug list” endpoint bekapcsolása (első 10 nyers tranzakció visszaadása a jelenlegi szűrőkkel) vagy a riport gyorsítótár TTL finomhangolása nagyobb forgalomra.

Ezt mondtad:
Feltöltött kép
Jól írtam be? [impactshop_report from="2025-09-01" to="2025-09-20" status="pending" group="shop_bator-tabor-alapitvany"] erre viszont rossz kimutatás jött, mert összesen 1 db ponding jutalék van, amit küldtem: Arukereso.hu
0.04 €
0.00 €
9c000c3874013357fb91739fd21addb3
1
9/18/2025, 8:34:09 AM
Sharity
Pending
bator-tabor-alapitvany
4596120 volt refresh token és flush
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Röviden: a shortcode-od nem „szűrt”, mert a group= NEM szűrő, hanem csak csoportosítási mód. Érvényes értékei: shop_ngo | ngo | shop. Emiatt a
group="shop_bator-tabor-alapitvany" egyszerűen „ismeretlen” volt → visszaesett a shop_ngo módra, és minden pending tételt kilistázott.
Két apró javítást adok, hogy pontosan arra az egy pendingre szűrj, és hogy a „Szervezet (data1)” tényleg a slugot (pl. bator-tabor-alapitvany) mutassa, ne valami numerikus mezőt:

bevezetünk egy opcionális NGO-szűrőt (ngo="…") a shortcode-hoz és a REST-hez,
a data1 kiválasztást okosítjuk: előnyben részesítjük a slug-szerű mezőket (d1, ref1, sub_id, subid), és csak végső esetben vesszük a numerikus data1-et.
Patch (csak az érintett részek)
Ezeket másold a snippetbe úgy, hogy felülírják a meglévő definíciókat.
/* --- 1) data1/NGO kiválasztás robusztusabbá tétele --- */
function impactshop_pick_ngo_from_row($row){
  $vals = [];
  foreach (['d1','ref1','sub_id','subid','sub_id1','data1'] as $k) {
    if (isset($row[$k]) && !is_array($row[$k])) {
      $v = trim((string)$row[$k]);
      if ($v !== '') $vals[] = $v;
    }
  }
  // 1) slug-szerű (van benne betű és csak a-z0-9-_.)
  foreach ($vals as $v) {
    if (preg_match('~[a-z]~i',$v) && preg_match('~^[a-z0-9._-]{3,}$~i',$v)) return $v;
  }
  // 2) bármi, ami nem tisztán szám (ne ragadjunk le a 0.04/0.123 mintáknál)
  foreach ($vals as $v) {
    if (!preg_match('~^\d+(?:\.\d+)?$~', $v)) return $v;
  }
  // 3) végső fallback: az első talált érték (akár szám is)
  return $vals[0] ?? '';
}

function impactshop_norm_conversion($row) {
  $out = ['campaign_id'=>0,'status'=>'','data1'=>'','order_value'=>0.0,'commission'=>0.0,'currency'=>''];
  foreach (['campaign_id','campaignId','cid','campaign'] as $k) if (isset($row[$k])) { $out['campaign_id']=intval(is_array($row[$k])?($row[$k]['id']??0):$row[$k]); break; }
  foreach (['status','state','rstatus'] as $k) if (!empty($row[$k])) { $out['status']=strtolower(trim($row[$k])); break; }
  $out['data1'] = impactshop_pick_ngo_from_row($row);
  foreach (['currency','cur'] as $k) if (!empty($row[$k])) { $out['currency']=strtoupper(trim($row[$k])); break; }
  foreach (['order_value','sale_amount','amount','price','orderAmount','total'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['order_value']=floatval($row[$k]); break; }
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['commission']=floatval($row[$k]); break; }
  return $out;
}

/* --- 2) Aggregálás + opcionális NGO-szűrő --- */
function impactshop_aggregate_conversions($from, $to, $status='approved', $group='shop_ngo', $filter_ngo='') {
  $cache_key = sprintf('impactshop_totals_%s_%s_%s_%s_%s', $from, $to, $status, $group, md5(strtolower($filter_ngo)));
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return ['rows'=>[], 'meta'=>['error'=>$res['error']->get_error_message()]];
  }
  $items = $res['items'] ?? [];
  $maps = impactshop_build_campaign_map();
  $by_cid = $maps['by_cid'];
  $ngo_filter = strtolower(trim($filter_ngo));

  $rows = []; $grand = ['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
  foreach ($items as $it) {
    $x = impactshop_norm_conversion($it);
    $cid = $x['campaign_id'];
    $ngo = $x['data1'] ?: '(nincs d1)';
    if ($ngo_filter && strtolower($ngo) !== $ngo_filter) continue; // <<— SZŰRŐ

    $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
    if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

    if ($group === 'ngo') {
      $key=$ngo;
      if (!isset($rows[$key])) $rows[$key]=['ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'shops'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['shops'][$shopSlug] = ($rows[$key]['shops'][$shopSlug] ?? 0) + $x['commission'];
    } elseif ($group === 'shop') {
      $key=$shopSlug;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'ngos'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['ngos'][$ngo] = ($rows[$key]['ngos'][$ngo] ?? 0) + $x['commission'];
    } else { // shop_ngo
      $key = $shopSlug.'||'.$ngo;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
    }

    $grand['orders'] += 1;
    $grand['order_value'] += $x['order_value'];
    $grand['commission']  += $x['commission'];
  }

  $rows = array_values($rows);
  usort($rows, function($a,$b){
    $da = $b['commission'] <=> $a['commission'];
    if ($da !== 0) return $da;
    return strcasecmp(($a['shop_name'] ?? $a['ngo'] ?? ''), ($b['shop_name'] ?? $b['ngo'] ?? ''));
  });

  $out = ['rows'=>$rows, 'meta'=>[
    'from'=>$from,'to'=>$to,'status'=>$status,'group'=>$group,'ngo'=>$filter_ngo,
    'grand'=>$grand,'count'=>count($rows),'generated_at'=>current_time('mysql')
  ]];

  $ttl = impactshop_settings()['cache_ttl'] ?? (15 * MINUTE_IN_SECONDS);
  set_transient($cache_key, $out, $ttl);
  return $out;
}

/* --- 3) REST: 'ngo' param átvétele --- */
add_action('rest_api_init', function() {
  register_rest_route('impactshop/v1', '/totals', [
    'methods'  => 'GET',
    'callback' => function(WP_REST_Request $req){
      $from   = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to     = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $status = sanitize_text_field($req->get_param('status') ?: 'approved');
      $group  = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $ngo    = sanitize_text_field($req->get_param('ngo')    ?: '');
      $data = impactshop_aggregate_conversions($from, $to, $status, $group, $ngo);
      if (!empty($data['meta']['error'])) {
        return new WP_Error('dognet_err', 'Dognet API hiba: '.$data['meta']['error'], ['status'=>502]);
      }
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true'
  ]);
});

/* --- 4) Shortcode: opcionális ngo="" attr --- */
function impactshop_shortcode_report($atts) {
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',       // approved|pending|rejected|all
    'group'  => 'shop_ngo',       // shop_ngo|ngo|shop
    'ngo'    => '',               // <<— ÚJ: opcionális szűrő
  ], $atts);

  $data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group'],$a['ngo']);
  if (!empty($data['meta']['error'])) {
    return '<div style="color:#b00">Dognet API hiba: '. esc_html($data['meta']['error']) .'</div>';
  }
  $rows = $data['rows']; $grand = $data['meta']['grand'];

  ob_start(); ?>
  <div class="impactshop-report" style="font:14px/1.5 system-ui">
    <div style="margin:8px 0 12px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> &nbsp; |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> &nbsp; |
      <b>Bontás:</b> <?php echo esc_html($a['group']); ?> 
      <?php if (!empty($a['ngo'])): ?> &nbsp; | <b>NGO:</b> <?php echo esc_html($a['ngo']); ?> <?php endif; ?>
    </div>
    <!-- … a táblázatos rész marad változatlan … -->
  <?php
  /* a függvény többi része változatlan */
Használat
Mostantól így szűrsz egy konkrét szervezetre:
Shortcode
[impactshop_report from="2025-09-01" to="2025-09-20" status="pending" group="shop_ngo" ngo="bator-tabor-alapitvany"]
REST
/wp-json/impactshop/v1/totals?from=2025-09-01&to=2025-09-20&status=pending&group=shop_ngo&ngo=bator-tabor-alapitvany
Így tényleg csak a Bátor Táborhoz tartozó pending tétel(ek) jelennek meg.
A Dognet oldaláról helyes: a jogosultság + token az auth/login-ról jön, a tranzakciók a raw-transactions/filter-en kérhetők, státuszok: A/P/D, és a „görgetés” last_id-dal történik.
Ha szeretnéd, be tudok tenni még egy shop= szűrőt is (shop slug alapján), de a fenti patch már megoldja a jelenlegi igényt.

Ezt mondtad:
hoca rakjam be? nem tudod ezt pontosan megjelölni? pl. a snippetben a sorok számozása segítsévéel stb
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Itt a sebészi pontosságú patch – sor/ankor alapján megjelölve, mit hova tegyél. A változtatások csak az NGO-szűrő és a data1 kiválasztás javítása; a token-flow-hoz nem nyúlunk. A Dognet oldalról mindezt a Publisher API dokumentáció támasztja alá: auth/login token (24h, használattal hosszabbodik) és a tranzakciók a raw-transactions/filter végponton, rstatus szűrővel és last_id-os görgetéssel.
1) ÚJ függvény: impactshop_pick_ngo_from_row
Hely: keresd meg a kommentet
/** Konverzió rekord normalizálása */
és KÖZVETLENÜL ELÉ szúrd be ezt a blokkot:
/* --- ROBUSZTUS NGO (data1) kiválasztó --- */
function impactshop_pick_ngo_from_row($row){
  $vals = [];
  foreach (['d1','ref1','sub_id','subid','sub_id1','data1'] as $k) {
    if (isset($row[$k]) && !is_array($row[$k])) {
      $v = trim((string)$row[$k]);
      if ($v !== '') $vals[] = $v;
    }
  }
  // 1) slug-szerű (betűt tartalmaz és csak a-z0-9._- karakterek)
  foreach ($vals as $v) {
    if (preg_match('~[a-z]~i',$v) && preg_match('~^[a-z0-9._-]{3,}$~i',$v)) return $v;
  }
  // 2) bármi, ami nem tisztán szám (ne ragadjunk le a 0.04/0.123 mintáknál)
  foreach ($vals as $v) {
    if (!preg_match('~^\d+(?:\.\d+)?$~', $v)) return $v;
  }
  // 3) végső fallback: az első talált érték
  return $vals[0] ?? '';
}
2) CSERE: impactshop_norm_conversion
Hely: ugyanitt, a fenti komment után találod a meglévő impactshop_norm_conversion-t. TELJESEN cseréld erre:
function impactshop_norm_conversion($row) {
  $out = ['campaign_id'=>0,'status'=>'','data1'=>'','order_value'=>0.0,'commission'=>0.0,'currency'=>''];
  foreach (['campaign_id','campaignId','cid','campaign'] as $k) if (isset($row[$k])) {
    $out['campaign_id']=intval(is_array($row[$k])?($row[$k]['id']??0):$row[$k]); break;
  }
  foreach (['status','state','rstatus'] as $k) if (!empty($row[$k])) {
    $out['status']=strtolower(trim($row[$k])); break;
  }
  $out['data1'] = impactshop_pick_ngo_from_row($row);
  foreach (['currency','cur'] as $k) if (!empty($row[$k])) { $out['currency']=strtoupper(trim($row[$k])); break; }
  foreach (['order_value','sale_amount','amount','price','orderAmount','total'] as $k)
    if (isset($row[$k])&&is_numeric($row[$k])) { $out['order_value']=floatval($row[$k]); break; }
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k)
    if (isset($row[$k])&&is_numeric($row[$k])) { $out['commission']=floatval($row[$k]); break; }
  return $out;
}
3) CSERE: impactshop_aggregate_conversions – NGO szűrő támogatása
Hely: keresd meg a kommentet
/** Aggregálás shop×NGO/NGO/shop */
és TELJESEN cseréld a függvényt erre (figyeld az új $filter_ngo paramétert és a cache-kulcsot):
function impactshop_aggregate_conversions($from, $to, $status='approved', $group='shop_ngo', $filter_ngo='') {
  $cache_key = sprintf('impactshop_totals_%s_%s_%s_%s_%s', $from, $to, $status, $group, md5(strtolower($filter_ngo)));
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return ['rows'=>[], 'meta'=>['error'=>$res['error']->get_error_message()]];
  }
  $items = $res['items'] ?? [];
  $maps = impactshop_build_campaign_map();
  $by_cid = $maps['by_cid'];
  $ngo_filter = strtolower(trim($filter_ngo));

  $rows = []; $grand = ['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
  foreach ($items as $it) {
    $x = impactshop_norm_conversion($it);
    $cid = $x['campaign_id'];
    $ngo = $x['data1'] ?: '(nincs d1)';
    if ($ngo_filter && strtolower($ngo) !== $ngo_filter) continue; // <<— SZŰRŐ

    $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
    if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

    if ($group === 'ngo') {
      $key=$ngo;
      if (!isset($rows[$key])) $rows[$key]=['ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'shops'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['shops'][$shopSlug] = ($rows[$key]['shops'][$shopSlug] ?? 0) + $x['commission'];
    } elseif ($group === 'shop') {
      $key=$shopSlug;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'ngos'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['ngos'][$ngo] = ($rows[$key]['ngos'][$ngo] ?? 0) + $x['commission'];
    } else {
      $key = $shopSlug.'||'.$ngo;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
    }

    $grand['orders'] += 1;
    $grand['order_value'] += $x['order_value'];
    $grand['commission']  += $x['commission'];
  }

  $rows = array_values($rows);
  usort($rows, function($a,$b){
    $da = $b['commission'] <=> $a['commission'];
    if ($da !== 0) return $da;
    return strcasecmp(($a['shop_name'] ?? $a['ngo'] ?? ''), ($b['shop_name'] ?? $b['ngo'] ?? ''));
  });

  $out = ['rows'=>$rows, 'meta'=>[
    'from'=>$from,'to'=>$to,'status'=>$status,'group'=>$group,'ngo'=>$filter_ngo,
    'grand'=>$grand,'count'=>count($rows),'generated_at'=>current_time('mysql')
  ]];

  $ttl = impactshop_settings()['cache_ttl'] ?? (15 * MINUTE_IN_SECONDS);
  set_transient($cache_key, $out, $ttl);
  return $out;
}
4) CSERE: REST /totals – vegye át az ngo paramot
Hely: keresd meg a blokkot, ami így kezdődik:
add_action('rest_api_init', function() { register_rest_route('impactshop/v1', '/totals', [...]
A callback függvényben cseréld ki a param-begyűjtést és a hívást az alábbira:
'callback' => function(WP_REST_Request $req){
  $from   = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
  $to     = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
  $status = sanitize_text_field($req->get_param('status') ?: 'approved');
  $group  = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
  $ngo    = sanitize_text_field($req->get_param('ngo')    ?: '');
  $data = impactshop_aggregate_conversions($from, $to, $status, $group, $ngo);
  if (!empty($data['meta']['error'])) {
    return new WP_Error('dognet_err', 'Dognet API hiba: '.$data['meta']['error'], ['status'=>502]);
  }
  return rest_ensure_response($data);
},
5) CSERE: [impactshop_report] shortcode – új ngo="" attribútum
Hely: keresd meg a function impactshop_shortcode_report($atts) kezdetű részt.
Két változás:
(A) Alapértelmezett attribútumok kibővítése – a shortcode_atts([...]) tömbbe tedd be:

'ngo'    => '',   // opcionális szűrő, pl. bator-tabor-alapitvany
(B) Aggregálás hívása – a impactshop_aggregate_conversions hívását cseréld erre:
$data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group'],$a['ngo']);
(C) (Opcionális) Fejlécben jelezd a szűrőt – a HTML fejlécrészben, ahol az „Időszak / Státusz / Bontás” kiírás van, told hozzá:
<?php if (!empty($a['ngo'])): ?> &nbsp; | <b>NGO:</b> <?php echo esc_html($a['ngo']); ?> <?php endif; ?>
Használat (példák)
Egy konkrét szervezet pending tranzakciói (táblában shop×ngo sorok, csak a megadott NGO):
[impactshop_report from="2025-09-01" to="2025-09-20" status="pending" group="shop_ngo" ngo="bator-tabor-alapitvany"]
Ugyanez REST-en:
/wp-json/impactshop/v1/totals?from=2025-09-01&to=2025-09-20&status=pending&group=shop_ngo&ngo=bator-tabor-alapitvany
Miért így? (2 mondat, doksi alapján)
Token: POST /api/v1/auth/login → 24 órás, minden hívással hosszabbodik; minden további kéréshez Authorization: Bearer kell. 
Tranzakciók: POST /api/v1/raw-transactions/filter + filter.rstatus in ["A","P","D"] + last_id lapozás; nagy adatmennyiségnél ez a javasolt módszer. 
Ha bárhol eltér a fájlod szerkezete, a fenti ankor-szövegek (kommentcímkék és függvénynevek) alapján keress rá és illeszd a blokkokat pontosan oda.
Ezt mondtad:
kérem egyben a snippetet, ezt túl sok hibalehetőség így
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Itt az egyben kért, mostani állapotra javított snippet. A Dognet token a dokumentációnak megfelelően az /api/v1/auth/login végpontról jön (24h, minden használattal hosszabbodik), a riportok pedig a /raw-transactions/filter végpontot használják rstatus szűréssel és last_id görgetéssel.
<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (Dognet API auto-login + Redirect + UI + Banner highlight)
 * + ELSZÁMOLÁSOK (konverziók riport: shortcode + REST)
 *
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag],
 *               [impactshop_debug], [impactshop_report]
 * Endpontok:    /go és /go-deal   (slugot is elfogad: /go/{shop_slug})
 * REST:         /wp-json/impactshop/v1/totals?from=YYYY-MM-DD&to=YYYY-MM-DD&status=approved|pending|rejected|all&group=shop_ngo|ngo|shop[&ngo=slug]
 * Megjegyzés:   aktiválás után egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'kudwyr-wavgaf-tYtzo2');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', ''); // üres → auto-login (24h token)
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 0); // 0: első "Web Site" jellegű csatorna
if (!defined('IMPACTSHOP_CACHE_TTL'))  define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS); // biztonsági ráhagyás
if (!defined('DOGNET_API_BASE'))       define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');

/* ============================== BEÁLLÍTÁS ============================== */

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    // Banners lap (gid=328401803) – a &v= számot emeld cache-törléshez
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=4',
    'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
    'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
  ];
}

/* ============================ CSV SEGÉDEK ============================ */

function impactshop_slugify_header($s) {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
  $s = strtr($s, $map);
  $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
  return trim($s, '_');
}

function impactshop_fetch_csv_assoc($url, $cache_key, $ttl) {
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $resp = wp_remote_get($url, ['timeout'=>20]);
  if (is_wp_error($resp)) return [];
  $body = wp_remote_retrieve_body($resp);
  if (!$body) return [];

  if (substr($body,0,3) === "\xEF\xBB\xBF") $body = substr($body,3);
  $lines = preg_split("/\r\n|\n|\r/", $body);
  if (!$lines || count($lines) < 1) return [];

  $first = $lines[0];
  $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

  $headers_raw = str_getcsv($first, $delim);
  $headers = array_map('impactshop_slugify_header', $headers_raw);

  $rows = [];
  for ($i=1; $i<count($lines); $i++) {
    if ($lines[$i] === '' || $lines[$i] === false) continue;
    $cols = str_getcsv($lines[$i], $delim);
    if (count($cols) == 1 && $cols[0] === null) continue;
    $row = [];
    foreach ($headers as $idx=>$key) {
      $row[$key] = isset($cols[$idx]) ? trim($cols[$idx]) : '';
    }
    if (implode('', $row) === '') continue;
    $rows[] = $row;
  }

  set_transient($cache_key, $rows, $ttl);
  return $rows;
}

/* ======================= SHOPS & BANNERS ====================== */

function impactshop_get_shops_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
}
function impactshop_get_banners_raw() {
  $s = impactshop_settings();
  return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
}

function impactshop_get_shops() {
  $rows = impactshop_get_shops_raw();
  $out = [];
  foreach ($rows as $r) {
    $name   = $r['name'] ?? ($r['nev'] ?? '');
    $slug   = $r['shop_slug'] ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
    $cat    = $r['category'] ?? ($r['kategoria'] ?? 'Egyéb');
    $logo   = $r['logo_url'] ?? ($r['logo'] ?? ($r['image'] ?? ''));
    if (!$name || !$slug) continue;
    $out[] = [
      'name'           => $name,
      'shop_slug'      => $slug,
      'category'       => $cat ?: 'Egyéb',
      'logo'           => $logo,
      'dognet_base'    => $r['dognet_base'] ?? '',
      'deeplink_param' => ($r['pdognet_deeplink_param'] ?? ($r['dognet_deeplink_param'] ?? 'url')) ?: 'url',
      'product_url'    => $r['product_url'] ?? ($r['homepage'] ?? ''),
      'commission_min' => $r['commission_min'] ?? '',
      'commission_max' => $r['commission_max'] ?? '',
      'deals_feed'     => $r['deals_feed'] ?? '',
    ];
  }
  return $out;
}

function impactshop_get_banners() {
  $rows = impactshop_get_banners_raw();
  $out = [];
  foreach ($rows as $r) {
    $img   = $r['img'] ?? ($r['image'] ?? ($r['banner'] ?? ($r['kep'] ?? '')));
    $href  = $r['href'] ?? ($r['url']   ?? ($r['link']   ?? ''));
    $label = $r['label']?? ($r['cimke'] ?? ($r['title']  ?? 'Banner'));
    $cat   = $r['category'] ?? ($r['kategoria'] ?? '');
    if (!$img || !$href) continue;
    $out[] = ['img'=>$img,'href'=>$href,'label'=>$label,'category'=>$cat];
  }
  return $out;
}

/* ====================== KÖZÖS SEGÉDEK ====================== */

function impactshop_q($key, $def='') {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
}
function impactshop_find_shop($slug) {
  $slug = trim(strtolower($slug));
  foreach (impactshop_get_shops() as $s) {
    if (strtolower($s['shop_slug']) === $slug) return $s;
  }
  return null;
}
function impactshop_error($msg) {
  status_header(400);
  wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'ImpactShop hiba');
}

/* ====================== DOGNET API (robusztus login + request) ====================== */

/** Belső segéd: többféle login-meghívás, token-kinyeréssel. */
function impactshop__dognet_try_login_once($endpoint, $payload, $headers) {
  $resp = wp_remote_post($endpoint, [
    'timeout'     => 25,
    'headers'     => $headers,
    'body'        => $payload,
    'redirection' => 3,
  ]);
  if (is_wp_error($resp)) return ['ok'=>false,'why'=>'wp_error: '.$resp->get_error_message()];
  $code = wp_remote_retrieve_response_code($resp);
  $body = wp_remote_retrieve_body($resp);

  $json = json_decode($body, true);
  $tok  = '';

  if (is_array($json)) {
    foreach (['token','access_token','data','result'] as $k) {
      if ($k === 'data' || $k === 'result') {
        if (!empty($json[$k]['token']))         { $tok = $json[$k]['token']; break; }
        if (!empty($json[$k]['access_token']))  { $tok = $json[$k]['access_token']; break; }
      } elseif (!empty($json[$k]) && is_string($json[$k])) {
        $tok = $json[$k]; break;
      }
    }
  }
  if ($code >= 200 && $code < 300 && $tok) return ['ok'=>true,'token'=>$tok,'code'=>$code];
  return ['ok'=>false,'code'=>$code,'body'=>is_string($body)?substr($body,0,400):'(nincs törzs)'];
}

/** ROBUSZTUS token-szerzés: CSAK /auth/login (JSON és form fallback). Cache: transient. */
function dognet_get_token($force_refresh = false) {
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;

  // Ha WP szinten tiltva a kimenő forgalom, futás közben engedélyezzük a Dognet hostot
  if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
    $allowed = defined('WP_ACCESSIBLE_HOSTS') ? WP_ACCESSIBLE_HOSTS : '';
    if (stripos($allowed, 'api.app.dognet.com') === false) {
      @define('WP_ACCESSIBLE_HOSTS', $allowed ? ($allowed.',api.app.dognet.com') : 'api.app.dognet.com');
    }
  }

  $key = 'dognet_api_token_cache_v1';
  if (!$force_refresh) {
    $tok = get_transient($key);
    if ($tok) return $tok;
  }

  $email = DOGNET_LOGIN_EMAIL;  $pass = DOGNET_LOGIN_PASSWORD;
  $endpoint = DOGNET_API_BASE.'/auth/login'; // hivatalos login végpont

  $payload_json = wp_json_encode(['email'=>$email,'password'=>$pass]);
  $payload_form = http_build_query(['email'=>$email,'password'=>$pass]);
  $headers_json = ['Content-Type'=>'application/json','Accept'=>'application/json'];
  $headers_form = ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json'];

  // JSON, majd form fallback ugyanarra az endpointra
  $r = impactshop__dognet_try_login_once($endpoint, $payload_json, $headers_json);
  if (!empty($r['ok'])) { set_transient($key, $r['token'], DOGNET_TOKEN_TTL); return $r['token']; }
  $r = impactshop__dognet_try_login_once($endpoint, $payload_form, $headers_form);
  if (!empty($r['ok'])) { set_transient($key, $r['token'], DOGNET_TOKEN_TTL); return $r['token']; }

  return '';
}

/** API kérés egységesen – 401-nél egyszer megpróbál új tokennel ismételni. */
function dognet_api_request($method, $path, $body=null) {
  $token = dognet_get_token(false);
  if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

  $url = (stripos($path,'http')===0 ? $path : rtrim(DOGNET_API_BASE,'/').$path);
  $args = [
    'timeout' => 25,
    'headers' => ['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json','Accept'=>'application/json'],
    'method'  => $method,
  ];
  if ($body!==null) $args['body'] = wp_json_encode($body);

  $resp = wp_remote_request($url, $args);
  if (is_wp_error($resp)) return $resp;

  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);

  if ($code == 401) {
    delete_transient('dognet_api_token_cache_v1');
    $token = dognet_get_token(true);
    if (!$token) return new WP_Error('no_token','Dognet API token frissítés sikertelen (401)');
    $args['headers']['Authorization'] = 'Bearer '.$token;
    $resp = wp_remote_request($url, $args);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
  }

  if ($code < 200 || $code >= 300) return new WP_Error('api_error','Dognet API hiba '.$code,['resp'=>$json,'code'=>$code]);
  return $json;
}

function dognet_extract_campaign_id_from_base($dognet_base) {
  if (!$dognet_base) return 0;
  $qs = parse_url($dognet_base, PHP_URL_QUERY);
  parse_str($qs,$parts);
  return isset($parts['cid']) ? intval($parts['cid']) : 0;
}

function dognet_api_pick_ad_channel_id() {
  if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
  $list = dognet_api_request('GET','/ad-channels');
  if (is_wp_error($list) || empty($list['data'])) return 0;
  foreach ($list['data'] as $c) {
    if (isset($c['type']) && stripos($c['type'],'web')!==false) return intval($c['id']);
  }
  return intval($list['data'][0]['id']);
}

function dognet_api_generate_link($campaign_id,$deeplink='',$d1='',$d2='') {
  $ad_channel_id = dognet_api_pick_ad_channel_id();
  if (!$ad_channel_id) return new WP_Error('no_channel','Nincs ad_channel');
  $body = ['ad_channel_id'=>$ad_channel_id,'campaign_id'=>intval($campaign_id),'url_type'=>3];
  if ($deeplink) $body['url']=$deeplink;
  if ($d1) $body['data1']=$d1;
  if ($d2) $body['data2']=$d2;
  $json = dognet_api_request('POST','/campaigns/links/generate',$body);
  if (is_wp_error($json)) return $json;
  foreach(['url','short_url','full_url'] as $k){
    if(!empty($json[$k])) return $json[$k];
    if(!empty($json['data'][$k])) return $json['data'][$k];
  }
  return new WP_Error('bad_api','Ismeretlen API válasz');
}

/* ==================== REDIRECTEK =================== */

function impactshop_add_rewrites() {
  add_rewrite_rule('^go/([^/]+)/?$',      'index.php?impactshop_go=1&impactshop_slug=$matches[1]',   'top');
  add_rewrite_rule('^go/?$',              'index.php?impactshop_go=1',                                'top');
  add_rewrite_rule('^go-deal/([^/]+)/?$', 'index.php?impactshop_deal=1&impactshop_slug=$matches[1]', 'top');
  add_rewrite_rule('^go-deal/?$',         'index.php?impactshop_deal=1',                              'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[]='impactshop_go';
  $vars[]='impactshop_deal';
  $vars[]='impactshop_slug';
  return $vars;
}
add_filter('query_vars','impactshop_add_query_vars');

add_action('template_redirect',function(){
  if(get_query_var('impactshop_go')){impactshop_handle_go(false);exit;}
  if(get_query_var('impactshop_deal')){impactshop_handle_go(true);exit;}
});

function impactshop_redirect_with_propagation($url,$amb,$src) {
  $add=[];
  if($amb&&strpos($url,'amb=')===false)$add['amb']=$amb;
  if($src&&strpos($url,'src=')===false)$add['src']=$src;
  if(strpos($url,'utm_source=')===false)$add['utm_source']='sharity';
  if(strpos($url,'utm_medium=')===false)$add['utm_medium']='impactshop';
  if($add)$url.=(strpos($url,'?')===false?'?':'&').http_build_query($add);
  wp_redirect($url,307);exit;
}

function impactshop_handle_go($is_deal) {
  $shop=impactshop_q('shop'); if(!$shop){ $shop = get_query_var('impactshop_slug'); }
  $ngo=impactshop_q('d1');$u=impactshop_q('u');
  $amb=impactshop_q('amb');$src=impactshop_q('src')?:'impactshop';
  if(!$shop||!$ngo)impactshop_error('Hiányzó paraméter (shop/d1).');
  $row=impactshop_find_shop($shop);if(!$row)impactshop_error('Ismeretlen shop: '.esc_html($shop));

  $targetUrl='';
  if($is_deal){ $targetUrl=$u?:($row['product_url']??''); }

  $final=null;$cid=dognet_extract_campaign_id_from_base($row['dognet_base']??'');
  if($cid){
    $deeplink=$targetUrl;
    if($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~',$deeplink)){
      $tmp=base64_decode($deeplink,true);
      if($tmp!==false && preg_match('~^https?://~i',$tmp)) $deeplink=$tmp;
    }
    $api=dognet_api_generate_link($cid,$deeplink,$ngo,'');
    if(!is_wp_error($api) && $api) $final=$api;
  }

  if(!$final){
    $base=$row['dognet_base']??'';
    if($base){
      $params=['d1'=>$ngo];
      if(!empty($targetUrl)){
        $deeplink = $targetUrl;
        if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
          $tmp = base64_decode($deeplink, true);
          if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
        }
        $deeplinkParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
        $params[$deeplinkParam] = $deeplink;
      }
      $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($params);
    }
  }

  if(!$final)impactshop_error('Nem sikerült a partner linket előállítani.');
  impactshop_redirect_with_propagation($final,$amb,$src);
}

/* ==================== SHORTCODE-OK =================== */

/** Scroller */
function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts(['category'=>'','inject_every'=>5,'speed'=>30], $atts);
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  if (!empty($a['category'])) {
    $catWanted = $a['category'];
    $shops   = array_values(array_filter($shops,   fn($s)=> strcasecmp($s['category'],$catWanted)===0 ));
    $banners = array_values(array_filter($banners, fn($b)=> empty($b['category']) || strcasecmp($b['category'],$catWanted)===0 ));
  }
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  $injectEvery = max(1, intval($a['inject_every']));
  $mixed = []; $bi = 0;
  foreach ($shops as $i=>$s) {
    $mixed[] = ['type'=>'shop','data'=>$s];
    if (($i+1)%$injectEvery===0 && $banners) {
      $mixed[] = ['type'=>'banner','data'=>$banners[$bi % count($banners)]];
      $bi++;
    }
  }
  $stream = array_merge($mixed, $mixed);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <style>
    .impactshop-scroller{overflow:hidden;width:100%;white-space:nowrap;position:relative}
    .impactshop-scroller-track{display:inline-block;white-space:nowrap;animation:impactshop-scroll linear infinite}
    @keyframes impactshop-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .impactshop-scroller:hover .impactshop-scroller-track{animation-play-state:paused}
    .impactshop-item{display:inline-block;margin-right:20px;position:relative}
    .impactshop-item.shop-item img{height:60px;width:auto}
    .impactshop-item.banner-item img{height:100px;width:auto;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .impactshop-badge{position:absolute;top:-8px;left:-8px;background:#ff3366;color:#fff;font:600 11px/1 system-ui;padding:4px 6px;border-radius:6px}
    @media (max-width:640px){ .impactshop-item.banner-item img{height:90px} }
  </style>
  <div class="impactshop-scroller">
    <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
      <?php foreach ($stream as $it): ?>
        <?php if ($it['type']==='shop'): $s=$it['data'];
          $cta = $d1
            ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($s['shop_slug'])))
            : add_query_arg(['shop'=>$s['shop_slug'],'amb'=>$amb], $fillout); ?>
          <div class="impactshop-item shop-item">
            <a href="<?php echo esc_url($cta); ?>">
              <img src="<?php echo esc_url($s['logo']); ?>" alt="<?php echo esc_attr($s['name']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php else: $b=$it['data']; ?>
          <div class="impactshop-item banner-item">
            <span class="impactshop-badge">AKCIÓ</span>
            <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
              <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_scroller','impactshop_shortcode_scroller');

/** Katalógus */
function impactshop_shortcode_catalog($atts) {
  $a = shortcode_atts(['show_tabs'=>'1','search'=>'1','per_page'=>'200'], $atts);
  $shops = impactshop_get_shops();
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  usort($shops, function($x,$y){
    $c = strcasecmp($x['category'],$y['category']);
    return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
  });

  $cats = []; foreach ($shops as $s) { $cats[$s['category']] = true; }
  $categories = array_keys($cats);
  sort($categories, SORT_NATURAL|SORT_FLAG_CASE);

  $d1  = impactshop_q('d1');
  $amb = impactshop_q('amb');
  $src = impactshop_q('src') ?: 'impactshop';
  $fillout = impactshop_settings()['fillout_url'];

  ob_start(); ?>
  <div class="impactshop-catalog">
    <?php if ($a['show_tabs']==='1'): ?>
      <ul class="impactshop-tabs" style="list-style:none;padding:0;margin:0 0 12px 0;display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
        <?php foreach ($categories as $i=>$cat): ?>
          <li data-cat="<?php echo esc_attr($cat); ?>" class="<?php echo $i===0?'active':''; ?>"
              style="cursor:pointer;padding:8px 12px;border-radius:999px;background:#f3f3f3;font-weight:600;font-size:14px;">
            <?php echo esc_html($cat); ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($a['search']==='1'): ?>
      <input type="text" class="impactshop-search" placeholder="Keresés…" aria-label="Keresés"
             style="width:100%;max-width:520px;margin:0 auto 10px auto;display:block;padding:8px;border:1px solid #ddd;border-radius:8px;">
    <?php endif; ?>

    <div class="impactshop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;align-items:start;">
      <?php foreach ($shops as $shop):
        $cta = $d1
          ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])))
          : add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout); ?>
        <div class="impactshop-card" data-cat="<?php echo esc_attr($shop['category']); ?>" style="text-align:center;">
          <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
            <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                 loading="lazy" decoding="async" style="height:60px;width:auto;max-width:100%">
          </a>
          <div style="font-size:14px;font-weight:600;margin-top:6px;"><?php echo esc_html($shop['name']); ?></div>
          <?php
          $min = trim($shop['commission_min']); $max = trim($shop['commission_max']);
          $minN = is_numeric(str_replace('%','',$min)) ? floatval(str_replace('%','',$min)) : null;
          $maxN = is_numeric(str_replace('%','',$max)) ? floatval(str_replace('%','',$max)) : null;
          if ($minN !== null || $maxN !== null) {
            if ($minN !== null && $maxN !== null && $minN !== $maxN) {
              $don = ($minN/2) .'–'. ($maxN/2).'%';
            } else {
              $v = ($minN !== null) ? $minN : $maxN;
              $don = ($v/2).'%';
            }
            echo '<div style="font-size:12px;color:#666;margin-top:2px;">Várható adomány: ~'. esc_html($don) .'</div>';
          }
          ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    if(!root) return;
    var tabs = root.querySelectorAll('.impactshop-tabs li');
    var search = root.querySelector('.impactshop-search');
    var cards = root.querySelectorAll('.impactshop-card');
    function apply() {
      var term = search ? (search.value || '').toLowerCase() : '';
      var active = root.querySelector('.impactshop-tabs li.active');
      var cat = active ? active.getAttribute('data-cat') : null;
      Array.prototype.forEach.call(cards, function(card){
        var okCat = !cat || card.getAttribute('data-cat') === cat;
        var nameEl = card.querySelector('div');
        var name = nameEl ? (nameEl.textContent || '').toLowerCase() : '';
        var okQ = !term || name.indexOf(term) !== -1;
        card.style.display = (okCat && okQ) ? '' : 'none';
      });
    }
    Array.prototype.forEach.call(tabs, function(tab){
      tab.addEventListener('click', function(){
        Array.prototype.forEach.call(tabs, function(t){ t.classList.remove('active'); });
        tab.classList.add('active');
        apply();
      });
    });
    if (search) search.addEventListener('input', apply);
    apply();
  })();
  </script>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_catalog','impactshop_shortcode_catalog');

/** Diagnosztika */
function impactshop_shortcode_diag() {
  $shops = impactshop_get_shops();
  $out = '<div style="font:14px/1.5 system-ui"><h3>Impact Shop diagnosztika</h3>';
  $out .= '<p>Shops betöltve: <b>'.count($shops).'</b></p>';
  $miss = [];
  foreach ($shops as $s) {
    if (empty($s['dognet_base']))    $miss[] = $s['shop_slug'].' (dognet_base)';
    if (empty($s['deeplink_param'])) $miss[] = $s['shop_slug'].' (pdognet_deeplink_param)';
  }
  if ($miss) {
    $out .= '<p style="color:#b00">Hiányzó mezők:<br>'.esc_html(implode(', ', $miss)).'</p>';
  } else {
    $out .= '<p style="color:#070">dognet_base + deeplink_param mindenhol rendben.</p>';
  }
  $out .= '</div>';
  return $out;
}
add_shortcode('impactshop_diag','impactshop_shortcode_diag');

/** Egyszerű debug */
function impactshop_shortcode_debug() {
  $s = impactshop_settings();
  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();
  $demo = [
    'img'=> home_url('/wp-content/uploads/2025/09/log.jpeg'),
    'href'=> 'https://form.fillout.com/t/eM61RLkz6jus?shop=4home&u='.rawurlencode('https://www.4home.hu/'),
    'label'=> 'Ajánlatok – 4home', 'category'=> 'Otthon'
  ];
  ob_start(); ?>
  <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;padding:12px;border-radius:8px">
Shops: <?php echo count($shops); ?> | Banners: <?php echo count($banners); ?>

shops_csv_url:  <?php echo esc_html($s['shops_csv_url']); ?>

banners_csv_url: <?php echo esc_html($s['banners_csv_url']); ?>


Minta banner: <?php echo esc_html(json_encode($demo, JSON_UNESCAPED_UNICODE)); ?>

  </pre>
  <?php return ob_get_clean();
}
add_shortcode('impactshop_debug','impactshop_shortcode_debug');

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});

/* ================================================================== */
/* ==================  E L S Z Á M O L Á S O K  (ÚJ)  ================ */
/* ================================================================== */

/** rstatus térkép: approved|pending|rejected|all → A|P|D */
function dognet__status_map($status){
  $s = strtolower(trim($status));
  if ($s==='approved') return ['A'];
  if ($s==='pending')  return ['P'];
  if ($s==='rejected') return ['D'];
  return []; // all → nincs filter
}

/** Egy batch kérése last_id szerint a RAW TRANSACTIONS-ból */
function dognet_api_list_conversions_batch($from, $to, $status='all', $lastId=null, $perPage=200) {
  $fromDt = $from.' 00:00:00';
  $toDt   = $to  .' 23:59:59';

  $filter = [
    ['created_at' => ['gte' => $fromDt]],
    ['created_at' => ['lte' => $toDt]],
  ];
  $rstatus = dognet__status_map($status);
  if ($rstatus) $filter[] = ['rstatus' => ['in' => $rstatus]];
  if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID) {
    $filter[] = ['ad_channel_id' => ['eq' => intval(DOGNET_AD_CHANNEL_ID)]];
  }

  $body = [
    'per-page' => max(1, min(1000, intval($perPage))),
    'filter'   => $filter,
  ];
  if ($lastId !== null) $body['last_id'] = intval($lastId);

  $resp = dognet_api_request('POST','/raw-transactions/filter',$body);
  if (is_wp_error($resp)) return ['error'=>$resp];

  $items = [];
  if (isset($resp['data']) && is_array($resp['data']))       $items = $resp['data'];
  elseif (isset($resp['items']) && is_array($resp['items'])) $items = $resp['items'];

  $nextLastId = null;
  if (isset($resp['meta']['last_id'])) {
    $nextLastId = intval($resp['meta']['last_id']);
  } elseif ($items) {
    $maxId = null;
    foreach ($items as $it) {
      foreach (['id','transaction_id','tid'] as $k) {
        if (isset($it[$k]) && is_numeric($it[$k])) { $maxId = max(intval($it[$k]), intval($maxId)); break; }
      }
    }
    if ($maxId !== null) $nextLastId = $maxId;
  }

  return ['items'=>$items, 'last_id'=>$nextLastId];
}

/** Teljes begyűjtés last_id görgetéssel */
function dognet_api_list_conversions_all($from, $to, $status='all', $maxBatches=200, $perPage=200) {
  $all=[]; $lastId=null;
  for ($i=0; $i<$maxBatches; $i++) {
    $batch = dognet_api_list_conversions_batch($from,$to,$status,$lastId,$perPage);
    if (isset($batch['error']) && is_wp_error($batch['error'])) return ['error'=>$batch['error']];
    $items = $batch['items'] ?? [];
    if (!$items) break;
    $all = array_merge($all, $items);
    $lastId = $batch['last_id'] ?? null;
    if ($lastId === null) break;
  }
  return ['items'=>$all];
}

/* --- ROBUSZTUS NGO (data1) kiválasztó --- */
function impactshop_pick_ngo_from_row($row){
  $vals = [];
  foreach (['d1','ref1','sub_id','subid','sub_id1','data1'] as $k) {
    if (isset($row[$k]) && !is_array($row[$k])) {
      $v = trim((string)$row[$k]);
      if ($v !== '') $vals[] = $v;
    }
  }
  foreach ($vals as $v) { // slug-szerű
    if (preg_match('~[a-z]~i',$v) && preg_match('~^[a-z0-9._-]{3,}$~i',$v)) return $v;
  }
  foreach ($vals as $v) { // bármi, ami nem tisztán szám
    if (!preg_match('~^\d+(?:\.\d+)?$~', $v)) return $v;
  }
  return $vals[0] ?? '';
}

/** Konverzió rekord normalizálása */
function impactshop_norm_conversion($row) {
  $out = ['campaign_id'=>0,'status'=>'','data1'=>'','order_value'=>0.0,'commission'=>0.0,'currency'=>''];
  foreach (['campaign_id','campaignId','cid','campaign'] as $k) if (isset($row[$k])) { $out['campaign_id']=intval(is_array($row[$k])?($row[$k]['id']??0):$row[$k]); break; }
  foreach (['status','state','rstatus'] as $k) if (!empty($row[$k])) { $out['status']=strtolower(trim($row[$k])); break; }
  $out['data1'] = impactshop_pick_ngo_from_row($row);
  foreach (['currency','cur'] as $k) if (!empty($row[$k])) { $out['currency']=strtoupper(trim($row[$k])); break; }
  foreach (['order_value','sale_amount','amount','price','orderAmount','total'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['order_value']=floatval($row[$k]); break; }
  foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) if (isset($row[$k])&&is_numeric($row[$k])) { $out['commission']=floatval($row[$k]); break; }
  return $out;
}

/** cid↔shop map */
function impactshop_build_campaign_map() {
  $shops = impactshop_get_shops();
  $by_cid = []; $by_slug = [];
  foreach ($shops as $s) {
    $cid = dognet_extract_campaign_id_from_base($s['dognet_base'] ?? '');
    $slug = $s['shop_slug']; $name = $s['name'];
    if ($cid) { $by_cid[$cid] = ['slug'=>$slug, 'name'=>$name]; }
    if ($slug) { $by_slug[$slug] = $cid; }
  }
  return ['by_cid'=>$by_cid, 'by_slug'=>$by_slug];
}

/** Aggregálás shop×NGO/NGO/shop + opcionális NGO-szűrő */
function impactshop_aggregate_conversions($from, $to, $status='approved', $group='shop_ngo', $filter_ngo='') {
  $cache_key = sprintf('impactshop_totals_%s_%s_%s_%s_%s', $from, $to, $status, $group, md5(strtolower($filter_ngo)));
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return ['rows'=>[], 'meta'=>['error'=>$res['error']->get_error_message()]];
  }
  $items = $res['items'] ?? [];
  $maps = impactshop_build_campaign_map();
  $by_cid = $maps['by_cid'];
  $ngo_filter = strtolower(trim($filter_ngo));

  $rows = []; $grand = ['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
  foreach ($items as $it) {
    $x = impactshop_norm_conversion($it);
    $cid = $x['campaign_id'];
    $ngo = $x['data1'] ?: '(nincs d1)';
    if ($ngo_filter && strtolower($ngo) !== $ngo_filter) continue;

    $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
    if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

    if ($group === 'ngo') {
      $key=$ngo;
      if (!isset($rows[$key])) $rows[$key]=['ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'shops'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['shops'][$shopSlug] = ($rows[$key]['shops'][$shopSlug] ?? 0) + $x['commission'];
    } elseif ($group === 'shop') {
      $key=$shopSlug;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'orders'=>0,'order_value'=>0.0,'commission'=>0.0,'ngos'=>[]];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
      $rows[$key]['ngos'][$ngo] = ($rows[$key]['ngos'][$ngo] ?? 0) + $x['commission'];
    } else {
      $key = $shopSlug.'||'.$ngo;
      if (!isset($rows[$key])) $rows[$key]=['shop_slug'=>$shopSlug,'shop_name'=>$shopName,'ngo'=>$ngo,'orders'=>0,'order_value'=>0.0,'commission'=>0.0];
      $rows[$key]['orders'] += 1;
      $rows[$key]['order_value'] += $x['order_value'];
      $rows[$key]['commission']  += $x['commission'];
    }

    $grand['orders'] += 1;
    $grand['order_value'] += $x['order_value'];
    $grand['commission']  += $x['commission'];
  }

  $rows = array_values($rows);
  usort($rows, function($a,$b){
    $da = $b['commission'] <=> $a['commission'];
    if ($da !== 0) return $da;
    return strcasecmp(($a['shop_name'] ?? $a['ngo'] ?? ''), ($b['shop_name'] ?? $b['ngo'] ?? ''));
  });

  $out = ['rows'=>$rows, 'meta'=>[
    'from'=>$from,'to'=>$to,'status'=>$status,'group'=>$group,'ngo'=>$filter_ngo,
    'grand'=>$grand,'count'=>count($rows),'generated_at'=>current_time('mysql')
  ]];

  $ttl = impactshop_settings()['cache_ttl'] ?? (15 * MINUTE_IN_SECONDS);
  set_transient($cache_key, $out, $ttl);
  return $out;
}

/* ==================== REST – JSON ÖSSZESÍTÉS ==================== */

add_action('rest_api_init', function() {
  register_rest_route('impactshop/v1', '/totals', [
    'methods'  => 'GET',
    'callback' => function(WP_REST_Request $req){
      $from   = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to     = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $status = sanitize_text_field($req->get_param('status') ?: 'approved'); // alap: csak jóváhagyott
      $group  = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
      $ngo    = sanitize_text_field($req->get_param('ngo')    ?: '');
      $data = impactshop_aggregate_conversions($from, $to, $status, $group, $ngo);
      if (!empty($data['meta']['error'])) {
        return new WP_Error('dognet_err', 'Dognet API hiba: '.$data['meta']['error'], ['status'=>502]);
      }
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true'
  ]);
});

/* ==================== SHORTCODE – HTML RIPORT ==================== */

function impactshop_shortcode_report($atts) {
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',           // approved|pending|rejected|all
    'group'  => 'shop_ngo',           // shop_ngo|ngo|shop
    'ngo'    => '',                   // opcionális: csak adott NGO (data1) sorai
  ], $atts);

  $data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group'],$a['ngo']);
  if (!empty($data['meta']['error'])) {
    return '<div style="color:#b00">Dognet API hiba: '. esc_html($data['meta']['error']) .'</div>';
  }
  $rows = $data['rows']; $grand = $data['meta']['grand'];

  ob_start(); ?>
  <div class="impactshop-report" style="font:14px/1.5 system-ui">
    <div style="margin:8px 0 12px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> &nbsp; |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> &nbsp; |
      <b>Bontás:</b> <?php echo esc_html($a['group']); ?>
      <?php if (!empty($a['ngo'])): ?> &nbsp; | <b>NGO:</b> <?php echo esc_html($a['ngo']); ?> <?php endif; ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead>
          <tr style="background:#f6f7f8">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Szervezet (data1)</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
            <?php elseif ($a['group']==='shop'): ?>
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
              <?php if ($a['group']==='ngo'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php elseif ($a['group']==='shop'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name'].' ('.$r['shop_slug'].')'); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php else: ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['order_value'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($r['commission'],0,',',' '); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php elseif ($a['group']==='shop'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php else: ?>
              <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['order_value'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($grand['commission'],0,',',' '); ?></th>
            <?php endif; ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <div style="color:#777;margin-top:8px;font-size:12px">Frissítve: <?php echo esc_html($data['meta']['generated_at']); ?> · Forrás: Dognet API</div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_report','impactshop_shortcode_report');

/* ==================== Opcionális: auto-blokk „impactshop-report” oldalra ==================== */
add_filter('the_content', function($content){
  if (is_page() && function_exists('get_post') && ($p=get_post()) && is_string($p->post_name) && $p->post_name==='impactshop-report') {
    if (strpos($content,'[impactshop_report')===false) $content .= "\n\n[impactshop_report]";
  }
  return $content;
});

/* ==================== KÉZI DOGNET TOKEN REFRESH / DIAG ==================== */
/* Admin joggal hívható:
   - frissítés:  /?impactshop_token=refresh
   - részletes diag: /?impactshop_token=refresh&diag=1
*/
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_token']) && $_GET['impactshop_token']==='refresh') {
    delete_transient('dognet_api_token_cache_v1');

    $ep = DOGNET_API_BASE.'/auth/login';
    $email = DOGNET_LOGIN_EMAIL; $pass = DOGNET_LOGIN_PASSWORD;

    $tries = [];
    $r1 = impactshop__dognet_try_login_once($ep, wp_json_encode(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/json','Accept'=>'application/json']);
    $tries[] = ['ep'=>$ep,'type'=>'json','ok'=>!empty($r1['ok']),'code'=>$r1['code']??null,'why'=>$r1['why']??'','body'=>$r1['body']??'','token'=>isset($r1['token'])?('…'.substr($r1['token'],-8)):''];
    if (!empty($r1['ok'])) { set_transient('dognet_api_token_cache_v1', $r1['token'], DOGNET_TOKEN_TTL); if (!isset($_GET['diag'])) wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop'); }

    $r2 = impactshop__dognet_try_login_once($ep, http_build_query(['email'=>$email,'password'=>$pass]), ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']);
    $tries[] = ['ep'=>$ep,'type'=>'form','ok'=>!empty($r2['ok']),'code'=>$r2['code']??null,'why'=>$r2['why']??'','body'=>$r2['body']??'','token'=>isset($r2['token'])?('…'.substr($r2['token'],-8)):''];
    if (!empty($r2['ok'])) { set_transient('dognet_api_token_cache_v1', $r2['token'], DOGNET_TOKEN_TTL); if (!isset($_GET['diag'])) wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop'); }

    // DIAG mód: részletes jelentés
    $curl = function_exists('curl_version') ? curl_version() : null;
    $curl_ver = $curl ? ($curl['version'].' / SSL '.$curl['ssl_version']) : 'curl N/A';
    $ssl_loc = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : null;
    $ssl_file = $ssl_loc ? ($ssl_loc['default_cert_file'] ?? '') : '';

    $html = '<div style="padding:16px;font:14px/1.5 system-ui">';
    $html .= '<div style="color:#b00;font-weight:700">HIBA: nem sikerült új Dognet tokent kérni.</div>';
    $html .= '<div style="margin:10px 0 6px 0;color:#444">Próbálkozások:</div><ol>';
    foreach ($tries as $t) {
      $html .= '<li><code>'.esc_html($t['ep']).'</code> ['.esc_html($t['type']).'] → '
             . ( $t['ok'] ? '<span style="color:#070">OK</span> token '.$t['token']
                         : '<span style="color:#b00">HIBA</span> code='.esc_html((string)($t['code'] ?? $t['why'])) )
             . '</li>';
      if (!empty($_GET['diag']) && !empty($t['body'])) {
        $html .= '<pre style="background:#fafafa;border:1px solid #eee;padding:8px;border-radius:6px;white-space:pre-wrap">'
              . esc_html($t['body']) . '</pre>';
      }
    }
    $html .= '</ol>';
    $html .= '<div style="margin-top:8px;color:#666">HTTP transport: '.$curl_ver.' · CA file: '.esc_html($ssl_file).'</div>';
    if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
      $html .= '<div style="color:#b00">Figyelem: WP_HTTP_BLOCK_EXTERNAL aktív. A host whitelisten: api.app.dognet.com</div>';
    }
    $html .= '</div>';
    wp_die($html, 'ImpactShop');
  }
});
Megjegyzés: a fenti kód a Dognet hivatalos Publisher API folyamát követi — POST /api/v1/auth/login (24h, használattal hosszabbodó token), tranzakciók POST /api/v1/raw-transactions/filter (szűrők + last_id görgetés) és igény szerint ad_channel_id/rstatus filterek. 
Ha bármit finomítanál (például külön shop= szűrő a riporthoz), szívesen belerakom ugyanebben a szerkezetben.

Ezt mondtad:
Feltöltött kép
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Jogos: a diagnosztikai képernyő akkor is piros „HIBA” fejlécet írt, ha közben sikerült a login. Az OK tokent listázta, de a fejléc fixen error volt. Íme a célzott javítás – csak a „KÉZI DOGNET TOKEN REFRESH / DIAG” blokkot cseréld erre. A működés:
ha bármely próbálkozás OK, zöld sikerfejlécet és részleteket mutat (diag módban is), és a token bekerül a cache-be;
ha egyik sem OK, marad a piros hiba.
(A login végpont továbbra is a hivatalos /api/v1/auth/login, 24 órás, használattal hosszabbodó tokennel. )
/* ==================== KÉZI DOGNET TOKEN REFRESH / DIAG ==================== */
/* Admin joggal hívható:
   - frissítés:  /?impactshop_token=refresh
   - részletes diag: /?impactshop_token=refresh&diag=1
*/
add_action('init', function(){
  if (is_admin()) return;
  if (!current_user_can('manage_options')) return;
  if (!isset($_GET['impactshop_token']) || $_GET['impactshop_token']!=='refresh') return;

  // töröljük a régi cache-t, hogy tényleg új kérés menjen
  delete_transient('dognet_api_token_cache_v1');

  $ep    = DOGNET_API_BASE.'/auth/login';
  $email = DOGNET_LOGIN_EMAIL;
  $pass  = DOGNET_LOGIN_PASSWORD;

  $tries = [];
  $any_ok = false;
  $last_ok_token = '';

  // 1) JSON
  $r1 = impactshop__dognet_try_login_once(
    $ep,
    wp_json_encode(['email'=>$email,'password'=>$pass]),
    ['Content-Type'=>'application/json','Accept'=>'application/json']
  );
  if (!empty($r1['ok'])) { $any_ok = true; $last_ok_token = $r1['token']; set_transient('dognet_api_token_cache_v1',$r1['token'], DOGNET_TOKEN_TTL); }
  $tries[] = ['ep'=>$ep,'type'=>'json','ok'=>!empty($r1['ok']),'code'=>$r1['code']??null,'why'=>$r1['why']??'','body'=>$r1['body']??'','token'=>isset($r1['token'])?('…'.substr($r1['token'],-8)):''];

  // 2) FORM (fallback)
  if (empty($r1['ok'])) {
    $r2 = impactshop__dognet_try_login_once(
      $ep,
      http_build_query(['email'=>$email,'password'=>$pass]),
      ['Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']
    );
    if (!empty($r2['ok'])) { $any_ok = true; $last_ok_token = $r2['token']; set_transient('dognet_api_token_cache_v1',$r2['token'], DOGNET_TOKEN_TTL); }
    $tries[] = ['ep'=>$ep,'type'=>'form','ok'=>!empty($r2['ok']),'code'=>$r2['code']??null,'why'=>$r2['why']??'','body'=>$r2['body']??'','token'=>isset($r2['token'])?('…'.substr($r2['token'],-8)):''];
  }

  // Ha nem kértek diagot és bármelyik sikeres: azonnali zöld üzenet és kilépés
  if ($any_ok && !isset($_GET['diag'])) {
    wp_die('<div style="padding:16px;font:14px/1.5 system-ui;color:#070">OK: új Dognet token cache-ben.</div>', 'ImpactShop');
  }

  // Innen: DIAG képernyő (vagy összes próbálkozás sikertelen)
  $curl    = function_exists('curl_version') ? curl_version() : null;
  $curl_ver= $curl ? ($curl['version'].' / SSL '.$curl['ssl_version']) : 'curl N/A';
  $ssl_loc = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : null;
  $ssl_file= $ssl_loc ? ($ssl_loc['default_cert_file'] ?? '') : '';

  $ok_headline = $any_ok
    ? '<div style="color:#070;font-weight:700">Siker: új Dognet token a cache-ben.</div>'
    : '<div style="color:#b00;font-weight:700">HIBA: nem sikerült új Dognet tokent kérni.</div>';

  $html = '<div style="padding:16px;font:14px/1.5 system-ui">'.$ok_headline
        . '<div style="margin:10px 0 6px 0;color:#444">Próbálkozások:</div><ol>';

  foreach ($tries as $i=>$t) {
    $html .= '<li><code>'.esc_html($t['ep']).'</code> ['.esc_html($t['type']).'] → '
           . ( $t['ok'] ? '<span style="color:#070">OK</span> token '.$t['token']
                        : '<span style="color:#b00">HIBA</span> code='.esc_html((string)($t['code'] ?? $t['why'])) )
           . '</li>';
    if (!empty($_GET['diag']) && !$t['ok'] && !empty($t['body'])) {
      $html .= '<pre style="background:#fafafa;border:1px solid #eee;padding:8px;border-radius:6px;white-space:pre-wrap">'
             . esc_html($t['body']) . '</pre>';
    }
  }
  $html .= '</ol>';
  $html .= '<div style="margin-top:8px;color:#666">HTTP transport: '.$curl_ver.' · CA file: '.esc_html($ssl_file).'</div>';
  if (defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL) {
    $html .= '<div style="color:#b00">Figyelem: WP_HTTP_BLOCK_EXTERNAL aktív. A host whitelisten: api.app.dognet.com</div>';
  }
  $html .= '</div>';

  wp_die($html, 'ImpactShop');
});
Ettől a refresh & diag oldal nem fog többé „HAMIS” hibát mutatni, ha a login tényleg sikerült. A többi (token-használat a kérésekhez, riportok) változatlanul működik a jelenlegi snippetben.
Ezt mondtad:
Feltöltött kép
Feltöltött kép
ez mit jelent? a kimutatás ugyanaz maradt, volt flash és token frissítés
A ChatGPT ezt mondta:

Gondolkozás

Gondolkozás
Röviden: a rendszer rendben kap új Dognet tokent, a riport pedig azért „ugyanaz”, mert
a shortcode-ot rosszul adtad meg (a group helyére került az NGO-slug), és
a Dognet API a pending tételeknél a kifizetendő jutalékot tipikusan 0-val adja vissza – ilyenkor csak a rendelés (és az order_value) látszik, a commission nem. Ez API-viselkedés, nem hiba.
Mit írj be pontosan?
A mostani:
[impactshop_report from="2025-09-01" to="2025-09-20" status="pending" group="shop_bator-tabor-alapitvany"]
helyett ezt használd (figyeld az ngo= paramétert, és a helyes group értéket):
[impactshop_report from="2025-09-01" to="2025-09-20" status="pending" group="shop_ngo" ngo="bator-tabor-alapitvany"]
Ettől csak a Bátor Táboros sorok maradnak. Pending státuszban a „Jutalék” így is 0 lesz (mert az API ezt küldi), de a „Rendelések” oszlopban meglesz az 1 db konverzió.
Miért piros még a „HIBA” fejléc a refresh oldalon?
Két ok szokott lenni:
a diag blokk régi változata maradt bent a fájlban, vagy
valamilyen cache (pl. full-page cache / OPCache) még a régi HTML-t szolgálja ki.
Ha a listában zöld „OK token” sorokat látsz, a token tényleg jó; a piros fejléc csak kozmetika. (A beépített diag-blokk frissített változata ezt már zöldre váltja, ha bármelyik próbálkozás sikeres.)
Ha szeretnéd a pending összegét is látni
A Dognet Publisher API hivatalosan: POST /api/v1/auth/login → token (24h, használattal hosszabbodik), adatok: POST /api/v1/raw-transactions/filter (szűrők + last_id scroll). Pendingnél a jutalék mező gyakran 0, tehát vagy:
nézd a „Kosárérték” oszlopot a pending időszakra, vagy
állíts status="all"-ra, így a jóváhagyott tételeknél a jutalék már megjelenik. 
Ha szeretnéd, adok egy mini patch-et, ami pending állapotban külön „várható jutalék” oszlopot jelenít meg (de ez becslés lesz, mert a hálózat nem minden esetben szolgáltatja).
Ezt mondtad:
Itt az egyben kért, mostani állapotra javított snippet. A Dognet token a dokumentációnak megfelelően az /api/v1/auth/login végpontról jön (24h, minden használattal hosszabbodik), a riportok pedig a /raw-transactions/filter végpontot használják rstatus szűréssel és last_id görgetéssel.
Ezt hova kellett felvinnem?
A ChatGPT ezt mondta:

Gondolkozás
Röviden: nem kell sehol “felvinni” az /auth/login-ot vagy a /raw-transactions/filter-t. Ezek az API-végpontok már a snippetben vannak bekötve; te csak a snippetet helyezed el a WordPressben, és a tetején lévő konstansokat (email/jelszó, opcionálisan ad channel ID) állítod. A végpontok és a rstatus/last_id logika a kódban hívódik meg háttérben a riporthoz. (Ezt írja a Dognet Publisher API doksi is: előbb POST /api/v1/auth/login → 24h token, utána a lekérdezések “Bearer” fejlécben mennek; a tranzakciókhoz POST /api/v1/raw-transactions/filter, akár last_id görgetéssel és rstatus = A|P|D szűrővel. )
Ha lépésről lépésre kell:

Hova tedd a snippetet?
Ideális: wp-content/mu-plugins/impactshop.php (must-use plugin, automatikusan betölt).
Alternatíva: a child theme functions.php végére (stabil, de frissítésnél figyelni kell).
Mit kell kitölteni a snippet elején?
DOGNET_LOGIN_EMAIL, DOGNET_LOGIN_PASSWORD – ezek kellenek a tokenhez.
DOGNET_AD_CHANNEL_ID – opcionális; ha 0, a kód automatikusan kiválasztja az első “Web Site” típusú csatornát az API-ból.
A két Google Sheet URL már be van drótozva; csak akkor módosítsd, ha változott a forrás.
Egyszeri inicializálás/teszt:
Nyisd meg: https://app.sharity.hu/?impactshop_refresh=1 (rewrite flush).
Token frissítés/diagnózis: https://app.sharity.hu/?impactshop_token=refresh (zöld „OK token” = minden rendben).
Használat (riport):
Shortcode pending + Bátor Tábor szűrővel:
[impactshop_report from="2025-09-01" to="2025-09-20" status="pending" group="shop_ngo" ngo="bator-tabor-alapitvany"]
(A group mező a bontást állítja; az ngo adja az egyetlen szervezet-szűrőt. Pendingnél az API gyakran 0 jutalékot küld – ez várható viselkedés. )
Ennyi. A Dognet végpontokhoz nem kell adminfelületen külön űrlap, „felvitel” vagy webhook – a snippet intézi a hitelesítést és a lekérést a dokumentációnak megfelelően.



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
