# Aktív WordPress Snippetek

## Hogyan használd ezt a fájlt:
- Másold ide minden aktív snippet kódját
- Jelöld meg, melyik aktív (.php), melyik inaktív (.off)
- Add meg a snippet nevét és rövid leírását

---

## 1. Dognet token ok - Copy 
**Státusz**: ✅ Aktív  
**Fájl**: 
**Leírás**: egy átfogó működést biztosító snippet
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
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 26081);
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

// ⬇️ CSERE: okosabb NGO (d1) kinyerés – dobja a numerikus/JSON zajt, URL-ből/queryből is olvas
function impactshop_pick_ngo_from_row($row){
// potenciális mezők (közvetlen és "last click" aliasok is)
$candKeys = [
  'd1','data1','ref1','sub_id','subid','sub_id1','ngo','ngo_name',
  'last_click_data1','last_click_d1','last_click_subid','lc_data1','lc_d1','lc_subid'
];

$cands = [];
foreach ($candKeys as $k) {
  if (isset($row[$k]) && !is_array($row[$k])) {
    $v = trim((string)$row[$k]);
    if ($v !== '') $cands[] = $v;
  }
}
// ha van külön beágyazott last_click tömb
if (!empty($row['last_click']) && is_array($row['last_click'])) {
  foreach (['data1','d1','subid','sub_id1','sub_id'] as $k) {
    if (!empty($row['last_click'][$k]) && is_string($row['last_click'][$k])) {
      $cands[] = trim($row['last_click'][$k]);
    }
  }
}
  if (!$cands) return '(ismeretlen)';

  // kis segédek
  $is_numericish = function($v){
    // tisztán szám vagy szám.tizedes → "rossz" NGO
    return (bool)preg_match('~^\d+(?:\.\d+)?$~', $v);
  };
  $is_slug_like = function($v){
    // "emberi" slug: betű/szám, pont, aláhúzás, kötőjel; min. 3 hossz; tartalmazzon legalább egy betűt
    return (bool)(preg_match('~^[a-z0-9._-]{3,}$~i', $v) && preg_match('~[a-z]~i', $v));
  };
  $pick_from_query = function($q){
    parse_str($q, $p);
    foreach (['d1','ngo','org','utm_term'] as $kk) {
      if (!empty($p[$kk]) && is_string($p[$kk])) return trim($p[$kk]);
    }
    return '';
  };
  $pick_from_json = function($s){
    $j = json_decode($s, true);
    if (!is_array($j)) return '';
    foreach (['d1','ngo','org','data1','ref1'] as $kk) {
      if (!empty($j[$kk]) && is_string($j[$kk])) return trim($j[$kk]);
    }
    return '';
  };

  // 2) közvetlen, szép slug
  foreach ($cands as $v) {
    if ($is_slug_like($v)) return $v;
  }

  // 3) URL vagy querystring eset
  foreach ($cands as $v) {
    // URL?
    if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) {
      $qs = parse_url($v, PHP_URL_QUERY);
      if ($qs) {
        $z = $pick_from_query($qs);
        if ($is_slug_like($z)) return $z;
      }
    }
    // "a=b&c=d" jellegű query?
    if (strpos($v,'=') !== false && strpos($v,'&') !== false) {
      $z = $pick_from_query($v);
      if ($is_slug_like($z)) return $z;
    }
  }

  // 4) JSON-gyanús szövegben kulcsok keresése
  foreach ($cands as $v) {
    $t = trim($v);
    if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
      $z = $pick_from_json($t);
      if ($is_slug_like($z)) return $z;
    }
  }

  // 5) bármi nem numerikus → még mindig jobb, mint a nyers szám
  foreach ($cands as $v) {
    if (!$is_numericish($v)) return $v;
  }

  // 6) végső fallback
  return '(ismeretlen)';
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

// === PATCH: impactshop_report – pénznem 2 tizedesre formázva ===
function impactshop_shortcode_report($atts) {
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',           // approved|pending|rejected|all
    'group'  => 'shop_ngo',           // shop_ngo|ngo|shop
    'ngo'    => '',                   // opcionális: csak adott NGO (data1)
  ], $atts);

  $data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group'],$a['ngo']);
  if (!empty($data['meta']['error'])) {
    return '<div style="color:#b00">Dognet API hiba: '. esc_html($data['meta']['error']) .'</div>';
  }
  $rows = $data['rows']; $grand = $data['meta']['grand'];

  // segéd: pénz formázó (2 tizedes, HU elválasztók)
  $eur = function($n){ return number_format((float)$n, 2, ',', ' ') . ' €'; };

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
                <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
              <?php elseif ($a['group']==='shop'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name'].' ('.$r['shop_slug'].')'); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
              <?php else: ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo $eur($grand['order_value']); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo $eur($grand['commission']); ?></th>
            <?php elseif ($a['group']==='shop'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo $eur($grand['order_value']); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo $eur($grand['commission']); ?></th>
            <?php else: ?>
              <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo $eur($grand['order_value']); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo $eur($grand['commission']); ?></th>
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

// === SHARITY PATCH: impactshop_rows javított megjelenítés ===
// CÉL: az NGO mező mindig emberi név/slug legyen (ne maradjon "0.0545" jellegű szám)

if (!function_exists('sharity_rows_smart_pick_ngo')) {
  function sharity_rows_smart_pick_ngo($row){
   // potenciális mezők (közvetlen és "last click" aliasok is)
$candKeys = [
  'd1','data1','ref1','sub_id','subid','sub_id1','ngo','ngo_name',
  'last_click_data1','last_click_d1','last_click_subid','lc_data1','lc_d1','lc_subid'
];

$cands = [];
foreach ($candKeys as $k) {
  if (isset($row[$k]) && !is_array($row[$k])) {
    $v = trim((string)$row[$k]);
    if ($v !== '') $cands[] = $v;
  }
}
// ha van külön beágyazott last_click tömb
if (!empty($row['last_click']) && is_array($row['last_click'])) {
  foreach (['data1','d1','subid','sub_id1','sub_id'] as $k) {
    if (!empty($row['last_click'][$k]) && is_string($row['last_click'][$k])) {
      $cands[] = trim($row['last_click'][$k]);
    }
  }
}
    if (!$cands) return '(ismeretlen)';

    $is_numericish = function($v){ return (bool)preg_match('~^\d+(?:\.\d+)?$~', $v); };
    $is_slug_like  = function($v){ return (bool)(preg_match('~^[a-z0-9._-]{3,}$~i', $v) && preg_match('~[a-z]~i', $v)); };

    $pick_from_query = function($q){
      parse_str($q, $p);
      foreach (['d1','ngo','org','utm_term'] as $kk) {
        if (!empty($p[$kk]) && is_string($p[$kk])) return trim($p[$kk]);
      }
      return '';
    };
    $pick_from_json = function($s){
      $j = json_decode($s, true);
      if (!is_array($j)) return '';
      foreach (['d1','ngo','org','data1','ref1'] as $kk) {
        if (!empty($j[$kk]) && is_string($j[$kk])) return trim($j[$kk]);
      }
      return '';
    };

    // 2) szép, közvetlen slug
    foreach ($cands as $v) if ($is_slug_like($v)) return $v;

    // 3) URL vagy querystring
    foreach ($cands as $v) {
      if (stripos($v, 'http://') === 0 || stripos($v, 'https://') === 0) {
        $qs = parse_url($v, PHP_URL_QUERY);
        if ($qs) { $z = $pick_from_query($qs); if ($is_slug_like($z)) return $z; }
      }
      if (strpos($v,'=') !== false && strpos($v,'&') !== false) {
        $z = $pick_from_query($v); if ($is_slug_like($z)) return $z;
      }
    }

    // 4) JSON-gyanús szöveg
    foreach ($cands as $v) {
      $t = trim($v);
      if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
        $z = $pick_from_json($t); if ($is_slug_like($z)) return $z;
      }
    }

    // 5) bármi nem numerikus jobb, mint a nyers szám
    foreach ($cands as $v) if (!$is_numericish($v)) return $v;

    return '(ismeretlen)';
  }
}

if (!function_exists('sharity_rows_pick_date')) {
  function sharity_rows_pick_date($row){
    foreach (['created_at','createdAt','created','time','datetime'] as $k) {
      if (!empty($row[$k])) return (string)$row[$k];
    }
    return '';
  }
}

if (!function_exists('sharity_rows_shortcode')) {
  function sharity_rows_shortcode($atts){
    // Attribútumok: from/to/status
    $a = shortcode_atts([
      'from'   => date('Y-m-01'),
      'to'     => date('Y-m-d'),
      'status' => 'approved', // approved|pending|rejected|all
    ], $atts, 'impactshop_rows');

    // Teljes lista lekérése (a meglévő, stabil függvényeidre támaszkodva)
    if (!function_exists('dognet_api_list_conversions_all')) {
      return '<div style="color:#b00">Hiányzó függvény: dognet_api_list_conversions_all</div>';
    }
    $res = dognet_api_list_conversions_all($a['from'], $a['to'], $a['status'], 80, 200);
    if (isset($res['error']) && is_wp_error($res['error'])) {
      return '<div style="color:#b00">Dognet API hiba: '.esc_html($res['error']->get_error_message()).'</div>';
    }
    $items = $res['items'] ?? [];

    // Kampány (cid) → shop név/slug tábla
    $maps = function_exists('impactshop_build_campaign_map') ? impactshop_build_campaign_map() : ['by_cid'=>[]];
    $by_cid = $maps['by_cid'];

    // Táblázat építése
    ob_start(); ?>
    <div class="impactshop-rows" style="font:14px/1.5 system-ui">
      <div style="margin:6px 0 10px 0">
        <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?>  |
        <b>Státusz:</b> <?php echo esc_html($a['status']); ?>  |
        <b>Csatorna:</b> <?php echo defined('DOGNET_AD_CHANNEL_ID') ? intval(DOGNET_AD_CHANNEL_ID) : 0; ?>
      </div>
      <div style="overflow:auto">
        <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
          <thead>
            <tr style="background:#f6f7f8">
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Dátum</th>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Webshop</th>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Szervezet (data1)</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sum = 0.0; $rows = 0;
            foreach ($items as $it) {
              // kampány ID kifésülése
              $cid = 0;
              foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
                if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
              }
              $shopSlug='(ismeretlen)'; $shopName='(ismeretlen)';
              if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

              // NGO okos kiválasztása
              $ngo = sharity_rows_smart_pick_ngo($it);

              // Jutalék
              $comm = 0.0;
              foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) {
                if (isset($it[$k]) && is_numeric($it[$k])) { $comm = (float)$it[$k]; break; }
              }
              $sum += $comm; $rows++;

              // Dátum
              $dt = sharity_rows_pick_date($it);
              ?>
              <tr>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($dt ?: '—'); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($shopName.' ('.$shopSlug.')'); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($ngo); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo number_format($comm, 2, ',', ' '); ?> €</td>
              </tr>
            <?php } ?>
            <?php if ($rows === 0): ?>
              <tr><td colspan="4" style="padding:10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 8px;color:#666">Nincs adat az adott szűrésre.</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr style="background:#fbfbfc">
              <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen (sorok: <?php echo (int)$rows; ?>)</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo number_format($sum, 2, ',', ' '); ?> €</th>
            </tr>
          </tfoot>
        </table>
      </div>
      <div style="color:#777;margin-top:8px;font-size:12px">Forrás: Dognet API · Felső dátum EXKLUZÍV · Csatorna ID: <?php echo defined('DOGNET_AD_CHANNEL_ID') ? intval(DOGNET_AD_CHANNEL_ID) : 0; ?></div>
    </div>
    <?php
    return ob_get_clean();
  }

  // FONTOS: ezzel felülírjuk a régi [impactshop_rows] megjelenítőt
  add_shortcode('impactshop_rows', 'sharity_rows_shortcode');
}
---

## 2. Dognet API – Diagnosztika
**Státusz**: ✅ Aktív   
**Fájl**:   
**Leírás**: Leírás

```php
<?php
// Dognet Diagnosztika – Admin oldal (WP HTTP API-val, cURL nélkül)
if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', function () {
    add_management_page('Dognet Diagnosztika', 'Dognet Diagnosztika', 'manage_options', 'dognet-diag', 'dognet_diag_render');
});

function dognet_diag_render() {
    if (!current_user_can('manage_options')) { return; }

    // Helper: WP HTTP API POST JSON
    $http_post_json = function($url, $body, $headers = []) {
        $args = [
            'method'  => 'POST',
            'timeout' => 20,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
            'body'    => wp_json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $res = wp_remote_post($url, $args);
        if (is_wp_error($res)) {
            return ['http_code' => 0, 'body' => null, 'error' => $res->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);
        return ['http_code' => $code, 'raw' => $raw, 'json' => $json, 'error' => null];
    };

    $api = 'https://api.app.dognet.com/api/v1'; // Publisher API base (auth, campaigns, links) – lásd doksi :contentReference[oaicite:1]{index=1}
    $email       = isset($_POST['dognet_email'])      ? sanitize_text_field($_POST['dognet_email'])      : get_option('dognet_diag_email', '');
    $password    = isset($_POST['dognet_password'])   ? sanitize_text_field($_POST['dognet_password'])   : get_option('dognet_diag_password', '');
    $ad_channel  = isset($_POST['dognet_ad_channel']) ? intval($_POST['dognet_ad_channel'])               : intval(get_option('dognet_diag_ad_channel', 0));
    $campaign_id = isset($_POST['dognet_campaign'])   ? intval($_POST['dognet_campaign'])                 : intval(get_option('dognet_diag_campaign', 0));

    if (isset($_POST['dognet_save'])) {
        check_admin_referer('dognet_diag');
        update_option('dognet_diag_email', $email);
        update_option('dognet_diag_password', $password);
        update_option('dognet_diag_ad_channel', $ad_channel);
        update_option('dognet_diag_campaign', $campaign_id);
        echo '<div class="updated"><p>Beállítások mentve.</p></div>';
    }

    echo '<div class="wrap"><h1>Dognet Diagnosztika</h1>';
    echo '<p>Ez az oldal a Dognet Publisher API segítségével ellenőrzi a kampány-jóváhagyást és a linkgenerálást (deeplink + base). Végpontok: <code>/auth/login</code>, <code>/campaigns/mine/filter</code>, <code>/campaigns/links/generate</code>. :contentReference[oaicite:2]{index=2}</p>';

    // Űrlap
    echo '<form method="post">';
    wp_nonce_field('dognet_diag');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th scope="row">Dognet e-mail</th><td><input class="regular-text" name="dognet_email" value="'.esc_attr($email).'"></td></tr>';
    echo '<tr><th scope="row">Dognet jelszó</th><td><input class="regular-text" type="password" name="dognet_password" value="'.esc_attr($password).'"></td></tr>';
    echo '<tr><th scope="row">Ad Channel ID</th><td><input class="regular-text" name="dognet_ad_channel" value="'.esc_attr($ad_channel).'"><br><small>Számozott csatorna-ID (nem CHID). Pl. 26081.</small></td></tr>';
    echo '<tr><th scope="row">Campaign ID (teszt)</th><td><input class="regular-text" name="dognet_campaign" value="'.esc_attr($campaign_id).'"><br><small>A go.dognet <code>cid</code> értéke. Pl. Vision Express: 223.</small></td></tr>';
    echo '</tbody></table>';
    echo '<p><button class="button button-primary" name="dognet_save" value="1">Mentés</button> ';
    echo '<button class="button" name="dognet_run" value="1">Diagnosztika futtatása</button></p>';
    echo '</form>';

    // Futás
    if (!empty($_POST['dognet_run'])) {
        if (empty($email) || empty($password)) {
            echo '<div class="error"><p>Adj meg Dognet belépőt.</p></div></div>';
            return;
        }
        if (empty($ad_channel) || empty($campaign_id)) {
            echo '<div class="error"><p>Adj meg Ad Channel ID-t és Campaign ID-t.</p></div></div>';
            return;
        }

        // 1) AUTH
        $auth = $http_post_json("$api/auth/login", ['email'=>$email, 'password'=>$password]);
        dognet_diag_block('1) AUTH', $auth);
        if ($auth['http_code'] < 200 || $auth['http_code'] >= 300 || empty($auth['json']['token'])) {
            echo '<div class="error"><p>Auth hiba – ellenőrizd az adatokat, vagy nézd meg a választ.</p></div></div>';
            return;
        }
        $token = $auth['json']['token'];
        $H = ['Authorization' => 'Bearer '.$token];

        // 2) Approved kampányok az adott ad channelhez
        $mine = $http_post_json("$api/campaigns/mine/filter", [
            'filter' => [
                ['ad_channel_id' => ['eq' => $ad_channel]],
                ['ad_channel_in_campaign_status' => ['eq' => 1]] // 1=approved (doksi) :contentReference[oaicite:3]{index=3}
            ],
            'per-page' => 200
        ], $H);
        dognet_diag_block('2) campaigns/mine/filter – approved kampányok', $mine, function($json){
            if (!is_array($json) || !isset($json['data'])) return null;
            $out = [];
            foreach ($json['data'] as $c) {
                $out[] = [
                    'id'   => $c['id']   ?? null,
                    'name' => $c['name'] ?? null,
                ];
            }
            return $out;
        });

        // 3) links/generate – DEEPLINKKEL
        $gen_dl = $http_post_json("$api/campaigns/links/generate", [
            'ad_channel_id' => $ad_channel,
            'campaign_id'   => $campaign_id,
            'url'           => home_url('/'),
            'data1'         => 'bator-tabor-alapitvany',
            'url_type'      => 3
        ], $H);
        dognet_diag_block('3) links/generate (DEEPLINK)', $gen_dl);

        // 4) links/generate – BASE (deeplink nélkül)
        $gen_base = $http_post_json("$api/campaigns/links/generate", [
            'ad_channel_id' => $ad_channel,
            'campaign_id'   => $campaign_id,
            'data1'         => 'bator-tabor-alapitvany',
            'url_type'      => 3
        ], $H);
        dognet_diag_block('4) links/generate (BASE)', $gen_base);

        // 5) Összefoglaló – kinyert URL-ek
        $pick = function($res){
            if (!is_array($res) || empty($res['json'])) return null;
            $j = $res['json'];
            $get = function($arr, $path){
                foreach (explode('.', $path) as $k) { if (!isset($arr[$k])) return null; $arr = $arr[$k]; }
                return $arr;
            };
            foreach (['link','url','generated_link','data.link','data.url'] as $p) {
                $v = $get($j, $p);
                if (is_string($v) && strpos($v,'http')===0) return $v;
            }
            if (isset($j['chid']) && isset($j['url'])) {
                return 'https://go.dognet.com/?chid='.$j['chid'].'&url='.rawurlencode($j['url']);
            }
            return null;
        };
        $url_dl   = $pick($gen_dl);
        $url_base = $pick($gen_base);
        echo '<h2>5) Összefoglaló</h2>';
        echo '<p><strong>Deeplinkes URL:</strong> '.($url_dl ? '<a href="'.esc_url($url_dl).'" target="_blank">'.esc_html($url_dl).'</a>' : '<em>nincs</em>').'</p>';
        echo '<p><strong>Base URL:</strong> '.($url_base ? '<a href="'.esc_url($url_base).'" target="_blank">'.esc_html($url_base).'</a>' : '<em>nincs</em>').'</p>';
    }

    echo '</div>';
}

function dognet_diag_block($title, $res, $summarizer = null) {
    echo '<h2>'.esc_html($title).'</h2>';
    echo '<p>HTTP kód: <code>'.esc_html($res['http_code']).'</code>'.($res['error']? ' – <span style="color:#d00">'.esc_html($res['error']).'</span>' : '').'</p>';
    if ($summarizer && isset($res['json'])) {
        $sum = call_user_func($summarizer, $res['json']);
        if ($sum !== null) {
            echo '<h4>Kivonat:</h4><pre style="background:#111;color:#eee;padding:12px;border-radius:8px">'.esc_html(wp_json_encode($sum, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)).'</pre>';
        }
    }
    $payload = isset($res['json']) ? wp_json_encode($res['json'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : $res['raw'];
    echo '<details><summary>Nyers válasz</summary><pre style="background:#111;color:#eee;padding:12px;border-radius:8px;white-space:pre-wrap">'.esc_html(is_string($payload)?$payload:print_r($payload, true)).'</pre></details>';
}



## 3. Sharity Impact UI – rövidkód biztosíték
**Státusz**: ✅ Aktív   
**Fájl**:   
**Leírás**: Leírás

```php
<?php
// Sharity Impact UI – rövidkód biztosíték (alias + minimál implementáció)
if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST','https://app.sharity.hu');

add_action('init', function(){
  // 1) Ha a mini "ims_*" néven adta őket, aliasoljuk a megszokott nevekre
  if (function_exists('ims_ticker')      && !shortcode_exists('impact_ticker'))      add_shortcode('impact_ticker','ims_ticker');
  if (function_exists('ims_leaderboard') && !shortcode_exists('impact_leaderboard')) add_shortcode('impact_leaderboard','ims_leaderboard');
  if (function_exists('ims_activity')    && !shortcode_exists('impact_activity'))    add_shortcode('impact_activity','ims_activity');

  // 2) Ha még így sincs meg, adjunk minimál implementációt a három rövidkódhoz
  if (!shortcode_exists('impact_ticker')) add_shortcode('impact_ticker', function($atts){
    $j = sharity_impact_fetch('/wp-json/impact/v1/ticker');
    if (!$j || !isset($j['total'])) return '<div class="kpis card"><div class="kpi"><div class="label">Összegyűjtve</div><div class="value">—</div></div><div class="kpi"><div class="label">Ma</div><div class="value">—</div></div></div>';
    $fmt = function($n){ return number_format((float)$n, 2, ',', ' ') . ' €'; };
    return '<div class="kpis card">'
         .   '<div class="kpi"><div class="label">Összegyűjtve</div><div class="value">'.$fmt($j['total']).'</div><div class="sub">Jóváhagyott adomány</div></div>'
         .   '<div class="kpi"><div class="label">Ma</div><div class="value">'.$fmt($j['today'] ?? 0).'</div><div class="sub">Mai adomány</div></div>'
         . '</div>';
  });

  if (!shortcode_exists('impact_leaderboard')) add_shortcode('impact_leaderboard', function($atts){
    $a = shortcode_atts(['tab'=>'ngo'], $atts);
    $tab = ($a['tab']==='shop') ? 'shop' : 'ngo';
    $j = sharity_impact_fetch('/wp-json/impact/v1/leaderboard?tab='.$tab);
    if (!$j || !is_array($j) || !count($j)) return '<div class="card" style="padding:12px">Nincs adat.</div>';
    $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
    foreach ($j as $i=>$row) {
      $name = esc_html($row['name'] ?? '—');
      $amt  = number_format((float)($row['amount'] ?? 0), 2, ',', ' ') . ' €';
      $out .= '<li style="display:flex;gap:8px;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">'
           .   '<span style="opacity:.7">'.($i+1).'.</span> <span style="flex:1">'.$name.'</span>'
           .   '<strong>'.$amt.'</strong>'
           . '</li>';
    }
    return $out.'</ul></div>';
  });

  if (!shortcode_exists('impact_activity')) add_shortcode('impact_activity', function(){
    $j = sharity_impact_fetch('/wp-json/impact/v1/activity');
    if (!$j || !is_array($j) || !count($j)) return '<div class="card" style="padding:12px">Még nincs friss aktivitás.</div>';
    $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
    foreach ($j as $row) {
      $txt = esc_html($row['text'] ?? ''); if ($txt==='') continue;
      $out .= '<li style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,.08)">'.$txt.'</li>';
    }
    return $out.'</ul></div>';
  });
});

function sharity_impact_fetch($path){
  $url = rtrim(IMPACT_API_BASE_HOST,'/').$path;
  $res = wp_remote_get($url, ['timeout'=>15, 'headers'=>['Accept'=>'application/json']]);
  if (is_wp_error($res)) return null;
  $code = wp_remote_retrieve_response_code($res);
  if ($code < 200 || $code >= 300) return null;
  $body = wp_remote_retrieve_body($res);
  return json_decode($body, true);
}


## 4. Netflix cards
**Státusz**: ✅ Aktív   
**Fájl**:   
**Leírás**: Leírás

```php
// == ImpactShop – Netflix-sáv (EGY sor + felül kategóriaszűrő) – auto-scroll + GenZ 3D tilt ==
// Rövidkód:
// [impactshop_netflix categories="Tech,Divat,Sport" show_all="1" arrows="1" card_w="150" card_h="110" gap="16"
//                     max_items="0" shuffle="0" featured_only="0" new_days="14" deals_badge="1" ga4="1"
//                     autoplay="1" interval="3000"]

if (!function_exists('impactshop_shortcode_netflix')) {
  function impactshop_shortcode_netflix($atts) {
    $a = shortcode_atts([
      'categories'    => '',
      'show_all'      => '1',
      'arrows'        => '1',
      'card_w'        => '150',
      'card_h'        => '110',
      'gap'           => '16',
      'max_items'     => '0',
      'shuffle'       => '0',
      'featured_only' => '0',
      'new_days'      => '14',
      'deals_badge'   => '1',
      'ga4'           => '1',
      // ÚJ
      'autoplay'      => '1',
      'interval'      => '3000',
    ], $atts, 'impactshop_netflix');

    if (!function_exists('impactshop_get_shops')) return '<div>Hiányzó függvény: impactshop_get_shops</div>';
    $shops = impactshop_get_shops();
    if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

    // featured_only
    if ($a['featured_only'] === '1') {
      $shops = array_values(array_filter($shops, function($s){
        $v = strtolower(trim((string)($s['featured'] ?? '0')));
        return ($v === '1' || $v === 'true' || $v === 'yes');
      }));
    }

    // kategóriák
    $bycat = [];
    foreach ($shops as $s) {
      $cat = trim($s['category'] ?? 'Egyéb'); if ($cat==='') $cat='Egyéb';
      $bycat[$cat][] = $s;
    }
    if (trim($a['categories'])!=='') {
      $wanted = array_values(array_filter(array_map('trim', explode(',', $a['categories']))));
      $ord = []; foreach ($wanted as $w) if (isset($bycat[$w])) $ord[$w] = $bycat[$w];
      $bycat = $ord;
    } else {
      ksort($bycat, SORT_NATURAL|SORT_FLAG_CASE);
    }

    $cats = array_keys($bycat);
    $activeCat = ($a['show_all']==='1') ? '__ALL__' : ($cats[0] ?? '__ALL__');

    // CTA segédek
    $d1  = function_exists('impactshop_q') ? impactshop_q('d1') : (isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '');
    $amb = function_exists('impactshop_q') ? impactshop_q('amb') : (isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '');
    $src = function_exists('impactshop_q') ? impactshop_q('src') : (isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop');
    $fillout = function_exists('impactshop_settings') ? (impactshop_settings()['fillout_url'] ?? '') : '';

    // "Új" jelzés
    $nowTs = time(); $newDays = max(1, intval($a['new_days']));
    $is_new = function($s) use($nowTs,$newDays){
      if (!empty($s['is_new']) || !empty($s['new'])) {
        $v = strtolower(trim((string)($s['is_new'] ?? $s['new'])));
        if ($v==='1' || $v==='true' || $v==='yes') return true;
      }
      if (!empty($s['added_at'])) {
        $ts = strtotime($s['added_at'].' 00:00:00');
        if ($ts && ($nowTs - $ts) <= ($newDays*86400)) return true;
      }
      return false;
    };

    // Kupon/akció API – jelenlét jelzés
    $cid_has_deal = [];
    if ($a['deals_badge'] === '1' && function_exists('dognet_api_request')) {
      $cache_key = 'impactshop_coupons_present_simple';
      $cid_has_deal = get_transient($cache_key);
      if ($cid_has_deal === false) {
        $cid_has_deal = [];
        $ad_id = defined('DOGNET_AD_CHANNEL_ID') ? intval(DOGNET_AD_CHANNEL_ID) : 0;
        $body  = ['filter'=>['validity'=>['eq'=>'present']], 'per-page'=>500];
        if ($ad_id) $body['ad_channel_id'] = $ad_id;
        $resp = dognet_api_request('POST','/coupons/filter', $body);
        $items = [];
        if (!is_wp_error($resp)) {
          if (isset($resp['data']) && is_array($resp['data'])) $items = $resp['data'];
          elseif (isset($resp['items']) && is_array($resp['items'])) $items = $resp['items'];
        }
        foreach ($items as $it) {
          $cid = 0;
          foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
            if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
          }
          if ($cid) $cid_has_deal[$cid] = true;
        }
        set_transient($cache_key, $cid_has_deal, 10 * MINUTE_IN_SECONDS);
      }
    }

    // Banners fallback kategóriára
    $banners_map = [];
    if ($a['deals_badge'] === '1' && function_exists('impactshop_get_banners')) {
      foreach (impactshop_get_banners() as $b) {
        $cat = trim($b['category'] ?? '');
        if ($cat !== '') $banners_map[$cat] = true;
      }
    }

    // allShops lista
    $allShops = [];
    foreach ($bycat as $cat=>$arr) { foreach ($arr as $s) { $allShops[] = $s + ['__cat'=>$cat]; } }
    if ($a['shuffle']==='1') shuffle($allShops);

    $uid   = 'ifx-'.substr(md5(json_encode([$a, array_keys($bycat)]).microtime(true)),0,8);
    $cardW = max(120, intval($a['card_w']));
    $cardH = max(80,  intval($a['card_h']));
    $gap   = max(8,   intval($a['gap']));
    $max   = max(0,   intval($a['max_items']));
    $autoplay = ($a['autoplay']==='1');
    $interval = max(1000, intval($a['interval']));

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{ --gap: <?php echo $gap; ?>px; --w: <?php echo $cardW; ?>px; --h: <?php echo $cardH; ?>px; --accent:#8b5cf6; }
      .<?php echo $uid; ?> .ifx-cats{display:flex;gap:10px;align-items:center;overflow-x:auto;padding:6px 2px 12px;margin-bottom:8px;scrollbar-width:none}
      .<?php echo $uid; ?> .ifx-cats::-webkit-scrollbar{display:none}
      .<?php echo $uid; ?> .ifx-pill{flex:0 0 auto;padding:9px 14px;border-radius:999px;font:700 13px/1 system-ui;
        background:linear-gradient(180deg,rgba(255,255,255,.85),rgba(255,255,255,.7));border:1px solid rgba(0,0,0,.08);color:#0b1220;
        box-shadow:0 4px 14px rgba(2,6,23,.07);cursor:pointer;user-select:none;transition:.15s}
      .<?php echo $uid; ?> .ifx-pill:hover{transform:translateY(-1px)}
      .<?php echo $uid; ?> .ifx-pill.active{background:linear-gradient(180deg,#fff,rgba(255,255,255,.85));border-color:rgba(0,0,0,.12);box-shadow:0 6px 18px rgba(2,6,23,.09)}

      .<?php echo $uid; ?> .ifx-track-wrap{position:relative;perspective:1000px}
      .<?php echo $uid; ?> .ifx-track{display:flex;gap:var(--gap);overflow-x:auto;scroll-snap-type:x mandatory;padding:8px 4px;scrollbar-width:none}
      .<?php echo $uid; ?> .ifx-track::-webkit-scrollbar{display:none}

      .<?php echo $uid; ?> .ifx-card{flex:0 0 auto;width:var(--w);scroll-snap-align:start;position:relative;will-change:transform}
      .<?php echo $uid; ?> .ifx-card a{
        display:block;border-radius:18px;padding:10px;text-decoration:none;color:inherit;
        background:linear-gradient(160deg, rgba(255,255,255,.70), rgba(255,255,255,.55));
        border:1px solid rgba(255,255,255,.65);
        box-shadow:
          0 18px 38px rgba(2,6,23,.12),
          inset 0 1px 0 rgba(255,255,255,.6),
          inset 0 -1px 0 rgba(2,6,23,.04);
        backdrop-filter:saturate(180%) blur(8px);
        transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
        transform-style:preserve-3d;
      }
      .<?php echo $uid; ?> .ifx-card a:hover{
        transform:translateY(-4px) rotateX(var(--rx,0deg)) rotateY(var(--ry,0deg));
        box-shadow:0 26px 44px rgba(2,6,23,.16);
        background:linear-gradient(160deg, rgba(255,255,255,.78), rgba(255,255,255,.62));
      }

      .<?php echo $uid; ?> .ifx-logo-box{
        width:100%;height:var(--h);display:flex;align-items:center;justify-content:center;border-radius:14px;overflow:hidden;
        background:radial-gradient(120% 120% at 10% 0%, rgba(139,92,246,.16), transparent 60%), rgba(255,255,255,.45);
        border:1px solid rgba(0,0,0,.06);
      }
      .<?php echo $uid; ?> .ifx-logo{max-width:100%;max-height:100%;object-fit:contain;display:block;filter:drop-shadow(0 1px 2px rgba(0,0,0,.12))}
      .<?php echo $uid; ?> .ifx-name{margin-top:8px;font:800 13px/1.25 system-ui;color:#0b1220;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-align:center}

      .<?php echo $uid; ?> .ifx-badges{position:absolute;top:8px;left:8px;display:flex;gap:6px;z-index:2}
      .<?php echo $uid; ?> .ifx-badge{padding:4px 7px;border-radius:9px;font:900 10px/1 system-ui;color:#fff;background:#ef4444;border:1px solid #b91c1c;
        box-shadow:0 4px 10px rgba(239,68,68,.25)}
      .<?php echo $uid; ?> .ifx-badge.new {background:#86efac;color:#065f46;border-color:#34d399}

      .<?php echo $uid; ?> .ifx-arrow{position:absolute;top:50%;transform:translateY(-50%);width:38px;height:38px;border-radius:999px;border:1px solid rgba(0,0,0,.12);
        background:linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.75));color:#0b1220;display:flex;align-items:center;justify-content:center;cursor:pointer;
        user-select:none;z-index:2;transition:transform .15s, box-shadow .15s;box-shadow:0 8px 18px rgba(2,6,23,.12);backdrop-filter:saturate(180%) blur(6px)}
      .<?php echo $uid; ?> .ifx-arrow:hover{transform:translateY(-50%) scale(1.07)}
      .<?php echo $uid; ?> .ifx-arrow.prev{left:-6px}
      .<?php echo $uid; ?> .ifx-arrow.next{right:-6px}
      @media (max-width:780px){ .<?php echo $uid; ?> .ifx-arrow{display:none} }

      @media (prefers-reduced-motion:reduce){
        .<?php echo $uid; ?> .ifx-card a, .<?php echo $uid; ?> .ifx-arrow{transition:none}
      }
    </style>

    <div class="<?php echo $uid; ?> impactshop-netflix" data-autoplay="<?php echo esc_attr($autoplay ? '1':'0'); ?>" data-interval="<?php echo esc_attr($interval); ?>">
      <div class="ifx-cats" role="tablist">
        <?php if ($a['show_all']==='1'): ?>
          <div class="ifx-pill <?php echo ($activeCat==='__ALL__')?'active':''; ?>" data-cat="__ALL__">Összes</div>
        <?php endif; ?>
        <?php foreach ($bycat as $cat => $_): ?>
          <div class="ifx-pill <?php echo ($activeCat===$cat)?'active':''; ?>" data-cat="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></div>
        <?php endforeach; ?>
      </div>

      <div class="ifx-track-wrap">
        <?php if ($a['arrows']==='1'): ?>
          <div class="ifx-arrow prev" aria-label="Vissza">‹</div>
          <div class="ifx-arrow next" aria-label="Előre">›</div>
        <?php endif; ?>
        <div class="ifx-track" data-active="<?php echo esc_attr($activeCat); ?>">
          <?php
          $render = function($S) use ($d1,$amb,$src,$fillout,$is_new,$a,$cid_has_deal,$banners_map){
            $out = '';
            $max = max(0, intval($a['max_items']));
            $i = 0;
            foreach ($S as $s) {
              if ($max>0 && $i>=$max) break; $i++;

              $cta = $d1
                ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src ?: 'impactshop'], home_url('/go/'. rawurlencode($s['shop_slug'])))
                : add_query_arg(['shop'=>$s['shop_slug'],'amb'=>$amb], $fillout);

              $img  = $s['logo'] ?? '';
              $name = $s['name'] ?? $s['shop_slug'];
              $cat  = $s['__cat'] ?? ($s['category'] ?? '');
              $cid  = function_exists('dognet_extract_campaign_id_from_base') ? dognet_extract_campaign_id_from_base($s['dognet_base'] ?? '') : 0;

              $hasDeal = (!empty($cid) && isset($cid_has_deal[$cid])) || ($cat && isset($banners_map[$cat]));
              $isNew = $is_new($s);

              $out .= '<div class="ifx-card" data-cat="'.esc_attr($cat).'">';
              $out .=   '<a href="'.esc_url($cta).'" aria-label="'.esc_attr($name).'">';
              $badges = '';
              if ($hasDeal) $badges .= '<span class="ifx-badge">Kupon</span>';
              if ($isNew)   $badges .= '<span class="ifx-badge new">Új</span>';
              if ($badges)  $out .= '<div class="ifx-badges">'.$badges.'</div>';
              $out .=     '<div class="ifx-logo-box">';
              if ($img) { $out .= '<img class="ifx-logo" src="'.esc_url($img).'" alt="'.esc_attr($name).'" loading="lazy">'; }
              else      { $out .= '<div style="color:#666;font:600 12px/1 system-ui">Nincs kép</div>'; }
              $out .=     '</div>';
              $out .=     '<div class="ifx-name">'.esc_html($name).'</div>';
              $out .=   '</a>';
              $out .= '</div>';
            }
            return $out;
          };

          $list = ($activeCat==='__ALL__') ? $allShops : array_map(function($x){ $x['__cat']=$x['category']??''; return $x; }, $bycat[$activeCat] ?? []);
          if ($a['shuffle']==='1' && $activeCat!=='__ALL__') shuffle($list);
          echo $render($list);
          ?>
        </div>
      </div>
    </div>

    <script>
      (function(){
        var root  = document.currentScript.previousElementSibling;
        if(!root) return;
        var track = root.querySelector('.ifx-track');
        var prev  = root.querySelector('.ifx-arrow.prev');
        var next  = root.querySelector('.ifx-arrow.next');
        var pills = root.querySelectorAll('.ifx-pill');
        var autoplay = root.getAttribute('data-autoplay')==='1';
        var interval = parseInt(root.getAttribute('data-interval')||'3000',10);
        if(isNaN(interval) || interval<1000) interval=3000;

        function scrollByAmount(dir){
          var w = track.clientWidth || 320;
          track.scrollBy({ left: dir * Math.max(240, Math.round(w*0.85)), behavior:'smooth' });
        }
        if (prev) prev.addEventListener('click', function(){ stopAuto(); scrollByAmount(-1); startAutoSoon(); });
        if (next) next.addEventListener('click', function(){ stopAuto(); scrollByAmount( 1); startAutoSoon(); });

        function setActive(cat){
          track.setAttribute('data-active', cat);
          pills.forEach(function(p){ p.classList.toggle('active', p.getAttribute('data-cat')===cat); });
          var cards = track.querySelectorAll('.ifx-card');
          cards.forEach(function(card){
            var cc = card.getAttribute('data-cat') || '';
            var show = (cat==='__ALL__') || (cc===cat);
            card.style.display = show ? '' : 'none';
          });
          track.scrollTo({left:0, behavior:'auto'});
        }
        pills.forEach(function(p){ p.addEventListener('click', function(){ stopAuto(); setActive(p.getAttribute('data-cat')); startAutoSoon(); }); });

        // Auto-scroll (kupon-sáv jelleg)
        var timer=null, hover=false;
        function step(){
          if(hover) return;
          if(track.scrollWidth <= track.clientWidth+4){ return; } // nincs mit scrollozni
          var maxLeft = track.scrollWidth - track.clientWidth;
          var atEnd = (track.scrollLeft >= maxLeft - 8);
          if(atEnd){
            // finom visszaugrás elejére
            track.scrollTo({left:0, behavior:'smooth'});
          }else{
            scrollByAmount(+1);
          }
        }
        function startAuto(){
          if(!autoplay || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
          if(timer) return;
          timer = setInterval(step, interval);
        }
        function stopAuto(){ if(timer){ clearInterval(timer); timer=null; } }
        function startAutoSoon(){ setTimeout(startAuto, 600); }

        track.addEventListener('mouseenter', function(){ hover=true; stopAuto(); });
        track.addEventListener('mouseleave', function(){ hover=false; startAutoSoon(); });
        track.addEventListener('touchstart', function(){ hover=true; stopAuto(); }, {passive:true});
        track.addEventListener('touchend',   function(){ hover=false; startAutoSoon(); }, {passive:true});
        document.addEventListener('visibilitychange', function(){ if(document.hidden) stopAuto(); else startAutoSoon(); });

        // 3D tilt – könnyű effekt (egérrel)
        track.addEventListener('mousemove', function(e){
          var card = e.target.closest('.ifx-card a'); if(!card) return;
          var r = card.getBoundingClientRect();
          var cx = (e.clientX - r.left) / r.width - .5;
          var cy = (e.clientY - r.top)  / r.height - .5;
          card.style.setProperty('--rx', (-cy*6).toFixed(2)+'deg');
          card.style.setProperty('--ry', ( cx*6).toFixed(2)+'deg');
        }, {passive:true});
        track.addEventListener('mouseleave', function(){
          track.querySelectorAll('.ifx-card a').forEach(function(a){ a.style.removeProperty('--rx'); a.style.removeProperty('--ry'); });
        });

        // indulás
        startAuto();
      })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impactshop_netflix','impactshop_shortcode_netflix');
}


## 5. kuponok snippet
**Státusz**: ✅ Aktív   
**Fájl**:   
**Leírás**: Leírás

```php
<?php
// == Impact Coupons – Netflix-sáv (kupon-központú, visszaszámlálóval, autoscroll, GA4 view/click/copy) ==
// Rövidkód: [impact_coupons_netflix autoplay="1" interval="3000" arrows="1" card_w="320" logo_h="48" gap="18" max_items="0" show_code="1" show_expiry="1"]
// Függ: dognet_api_request(), impactshop_get_shops(), impactshop_settings(), impactshop_q(), (opcionális) dognet_extract_campaign_id_from_base()

if (!function_exists('impact_coupons_netflix_shortcode')) {
  function impact_coupons_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'autoplay'    => '1',     // 1 = automatikus léptetés
      'interval'    => '3000',  // ms
      'arrows'      => '1',     // 1 = bal/jobb nyilak
      'card_w'      => '320',   // kártya szélesség (px)
      'logo_h'      => '48',    // logó doboz magasság (px)
      'gap'         => '18',    // kártya-köz (px)
      'max_items'   => '0',     // 0 = nincs limit
      'show_code'   => '1',     // 1 = kuponkód megjelenítése
      'show_expiry' => '1',     // 1 = visszaszámláló / lejárati info megjelenítése
    ], $atts, 'impact_coupons_netflix');

    if (!function_exists('dognet_api_request'))  return '<div>Hiányzó függvény: dognet_api_request</div>';
    if (!function_exists('impactshop_get_shops')) return '<div>Hiányzó függvény: impactshop_get_shops</div>';

    // -- 1) Aktív kuponok (validity=present) + cache --
    $cache_key = 'impact_coupons_present_cards_v3';
    $coupons = get_transient($cache_key);
    if ($coupons === false) {
      $ad_id = defined('DOGNET_AD_CHANNEL_ID') ? intval(DOGNET_AD_CHANNEL_ID) : 0;
      $body  = ['filter'=>['validity'=>['eq'=>'present']], 'per-page'=>500];
      if ($ad_id) $body['ad_channel_id'] = $ad_id;

      $resp = dognet_api_request('POST','/coupons/filter',$body);
      $items = [];
      if (!is_wp_error($resp)) {
        if (isset($resp['data'])  && is_array($resp['data']))  $items = $resp['data'];
        if (isset($resp['items']) && is_array($resp['items'])) $items = $resp['items'];
      }
      $coupons = $items;
      set_transient($cache_key, $coupons, 10 * MINUTE_IN_SECONDS);
    }
    if (!$coupons || !is_array($coupons)) return '<div>Jelenleg nincs aktív kupon.</div>';

    // -- 2) Shop mapping (campaign_id → shop)
    $shops = impactshop_get_shops(); // [{shop_slug, name, logo, category, dognet_base, ...}]
    $cid_to_shop = [];
    $extract_cid = function($base) {
      if (function_exists('dognet_extract_campaign_id_from_base')) {
        return intval(dognet_extract_campaign_id_from_base($base ?: ''));
      }
      if ($base && preg_match('~[?&]cid=(\d+)~', $base, $m)) return intval($m[1]);
      return 0;
    };
    foreach ($shops as $s) {
      $cid = $extract_cid($s['dognet_base'] ?? '');
      if ($cid) $cid_to_shop[$cid] = $s;
    }

    // -- 3) Kupon-kártyák előkészítése (lejárat, címkék) --
    $cards = [];
    foreach ($coupons as $it) {
      // kampány ID
      $cid = 0;
      foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
        if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
      }
      if (!$cid || !isset($cid_to_shop[$cid])) continue;
      $shop = $cid_to_shop[$cid];

      // Fő kedvezmény: prefer % > amount > title
      $pct  = null; $amt = null; $cur = '';
      foreach (['percent','discount_percent','discount_pct'] as $k) if (isset($it[$k]) && is_numeric($it[$k])) { $pct = floatval($it[$k]); break; }
      foreach (['amount','discount_amount','value_off'] as $k)     if (isset($it[$k]) && is_numeric($it[$k])) { $amt = floatval($it[$k]); break; }
      foreach (['currency','cur'] as $k)                           if (!empty($it[$k])) { $cur = strtoupper(trim($it[$k])); break; }
      $title = ''; foreach (['title','name','label','description'] as $k) if (!empty($it[$k])) { $title = trim($it[$k]); break; }
      $code  = ''; foreach (['code','coupon','coupon_code'] as $k)        if (!empty($it[$k])) { $code  = trim($it[$k]); break; }

      $primary = '';
      if ($pct !== null && $pct > 0)     { $primary = '−'.rtrim(rtrim(number_format($pct,2,'.',''),'0'),'.').'%'; }
      elseif ($amt !== null && $amt > 0) { $primary = '−'.rtrim(rtrim(number_format($amt,2,'.',''),'0'),'.').' '.($cur ?: '€'); }
      elseif ($title)                    { $primary = $title; }
      else                               { $primary = 'Akció'; }

      // Érvényesség vége (több lehetséges kulcs)
      $expiry = '';
      foreach (['valid_to','end_at','date_to','expires_at','validTo','endAt','expiresAt'] as $k) {
        if (!empty($it[$k])) { $expiry = trim($it[$k]); break; }
      }
      // Normalizálás ISO-ra (ha csak dátum, tegyünk hozzá éjféli időt)
      if ($expiry && preg_match('~^\d{4}-\d{2}-\d{2}$~', $expiry)) $expiry .= 'T23:59:59';

      // CTA
      $d1  = function_exists('impactshop_q') ? impactshop_q('d1') : (isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '');
      $amb = function_exists('impactshop_q') ? impactshop_q('amb') : (isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '');
      $src = function_exists('impactshop_q') ? impactshop_q('src') : (isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop');
      $fillout = function_exists('impactshop_settings') ? (impactshop_settings()['fillout_url'] ?? '') : '';
      $cta = $d1
        ? add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src ?: 'impactshop'], home_url('/go/'. rawurlencode($shop['shop_slug'])))
        : add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);

      $cards[] = [
        'cid'       => $cid,
        'shop_slug' => $shop['shop_slug'],
        'shop_name' => $shop['name'] ?? $shop['shop_slug'],
        'shop_logo' => $shop['logo'] ?? '',
        'category'  => $shop['category'] ?? '',
        'primary'   => $primary,
        'title'     => $title,
        'code'      => $code,
        'expiry'    => $expiry,   // ISO (ha ismert)
        'cta'       => $cta,
      ];
    }
    if (!$cards) return '<div>Jelenleg nincs megjeleníthető kupon.</div>';

    $max = max(0, intval($a['max_items']));
    if ($max > 0) $cards = array_slice($cards, 0, $max);

    // Render
    $uid   = 'icn-'.substr(md5(json_encode([$a, count($cards)]).microtime(true)),0,8);
    $cardW = max(240, intval($a['card_w']));
    $logoH = max(36,  intval($a['logo_h']));
    $gap   = max(8,   intval($a['gap']));
    $interval = max(1200, intval($a['interval']));
    $show_expiry = ($a['show_expiry'] === '1');

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?> { --w: <?php echo $cardW; ?>px; --gap: <?php echo $gap; ?>px; --logoH: <?php echo $logoH; ?>px; }
      .<?php echo $uid; ?> .icn-wrap { position:relative; }
      .<?php echo $uid; ?> .icn-track {
        display:flex; gap:var(--gap); overflow-x:auto; scroll-snap-type:x mandatory; padding:10px 4px;
        scrollbar-width:none;
      }
      .<?php echo $uid; ?> .icn-track::-webkit-scrollbar { display:none; }

      .<?php echo $uid; ?> .icn-card { flex:0 0 auto; width:var(--w); scroll-snap-align:start; position:relative; }
      .<?php echo $uid; ?> .icn-card a {
        display:block; border-radius:16px; padding:12px; text-decoration:none;
        background:rgba(255,255,255,.72); border:1px solid rgba(0,0,0,.08);
        backdrop-filter:saturate(180%) blur(10px);
        transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        box-shadow:0 8px 24px rgba(0,0,0,.08); color:#111;
      }
      .<?php echo $uid; ?> .icn-card a:hover { transform:translateY(-2px) scale(1.02); box-shadow:0 12px 30px rgba(0,0,0,.12); border-color:rgba(0,0,0,.12); }

      .<?php echo $uid; ?> .icn-head { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
      .<?php echo $uid; ?> .icn-logoBox {
        width:calc(var(--logoH) * 1.7); height:var(--logoH); border-radius:10px; background:rgba(255,255,255,.6);
        display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid rgba(0,0,0,.06);
      }
      .<?php echo $uid; ?> .icn-logo { max-width:100%; max-height:100%; object-fit:contain; display:block; }
      .<?php echo $uid; ?> .icn-shop { font:600 14px/1.2 system-ui; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

      .<?php echo $uid; ?> .icn-primary { font:800 26px/1.1 system-ui; margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .<?php echo $uid; ?> .icn-title   { font:600 13px/1.25 system-ui; color:#333; max-height:2.6em; overflow:hidden; }

      .<?php echo $uid; ?> .icn-actions { margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
      .<?php echo $uid; ?> .icn-code    { font:700 12px/1 system-ui; padding:6px 8px; border-radius:10px; background:#111; color:#fff; letter-spacing:0.04em; }
      .<?php echo $uid; ?> .icn-copy    { font:700 12px/1 system-ui; padding:6px 10px; border-radius:10px; border:1px solid rgba(0,0,0,.15); background:#fff; cursor:pointer; }
      .<?php echo $uid; ?> .icn-copy:active { transform: translateY(1px); }

      /* Countdown sáv */
      .<?php echo $uid; ?> .icn-expiry { margin-top:8px; font:600 12px/1.2 system-ui; color:#333; display:flex; align-items:center; gap:8px; }
      .<?php echo $uid; ?> .icn-countdown { font:800 12px/1 system-ui; padding:4px 6px; border-radius:8px; background:rgba(255,165,0,.18); color:#7a3d00; border:1px solid rgba(122,61,0,.18); }
      .<?php echo $uid; ?> .icn-countdown.urg-24h { background:rgba(220,0,0,.14); color:#a00000; border-color:rgba(160,0,0,.25); }
      .<?php echo $uid; ?> .icn-countdown.done { opacity:.6; text-decoration:line-through; }

      /* Nyilak */
      .<?php echo $uid; ?> .icn-arrow {
        position:absolute; top:50%; transform:translateY(-50%); width:36px; height:36px; border-radius:999px;
        border:1px solid rgba(0,0,0,.15); background:rgba(255,255,255,.9); color:#111; display:flex; align-items:center; justify-content:center;
        cursor:pointer; user-select:none; z-index:2; transition:transform .15s, box-shadow .15s; box-shadow:0 6px 16px rgba(0,0,0,.10);
      }
      .<?php echo $uid; ?> .icn-arrow:hover { transform:translateY(-50%) scale(1.06); }
      .<?php echo $uid; ?> .icn-arrow.prev { left:-6px; }
      .<?php echo $uid; ?> .icn-arrow.next { right:-6px; }
      @media (max-width:780px){ .<?php echo $uid; ?> .icn-arrow{ display:none; } }
    </style>

    <div class="<?php echo $uid; ?> impact-coupons-netflix" data-show-expiry="<?php echo $show_expiry ? '1' : '0'; ?>">
      <div class="icn-wrap">
        <?php if ($a['arrows']==='1'): ?>
          <div class="icn-arrow prev" aria-hidden="true" title="Vissza">‹</div>
          <div class="icn-arrow next" aria-hidden="true" title="Előre">›</div>
        <?php endif; ?>

        <div class="icn-track">
          <?php foreach ($cards as $c): ?>
            <div class="icn-card"<?php echo $c['expiry'] ? ' data-expiry="'.esc_attr($c['expiry']).'"' : ''; ?>>
              <a href="<?php echo esc_url($c['cta']); ?>"
                 aria-label="<?php echo esc_attr($c['shop_name'].' – kupon'); ?>"
                 data-event="coupon_click"
                 data-shop-slug="<?php echo esc_attr($c['shop_slug']); ?>"
                 data-shop-name="<?php echo esc_attr($c['shop_name']); ?>"
                 data-coupon-primary="<?php echo esc_attr($c['primary']); ?>"
                 data-coupon-code="<?php echo esc_attr($c['code']); ?>">
                <div class="icn-head">
                  <div class="icn-logoBox">
                    <?php if (!empty($c['shop_logo'])): ?>
                      <img class="icn-logo" src="<?php echo esc_url($c['shop_logo']); ?>" alt="<?php echo esc_attr($c['shop_name']); ?>" loading="lazy" decoding="async">
                    <?php else: ?>
                      <span style="font:600 12px system-ui;color:#666">Logo</span>
                    <?php endif; ?>
                  </div>
                  <div class="icn-shop" title="<?php echo esc_attr($c['shop_name']); ?>"><?php echo esc_html($c['shop_name']); ?></div>
                </div>

                <div class="icn-primary"><?php echo esc_html($c['primary']); ?></div>
                <?php if (!empty($c['title'])): ?>
                  <div class="icn-title"><?php echo esc_html($c['title']); ?></div>
                <?php endif; ?>

                <?php if ($a['show_code']==='1' && !empty($c['code'])): ?>
                  <div class="icn-actions">
                    <span class="icn-code"><?php echo esc_html($c['code']); ?></span>
                    <button type="button" class="icn-copy"
                            data-event="coupon_copy"
                            data-shop-slug="<?php echo esc_attr($c['shop_slug']); ?>"
                            data-shop-name="<?php echo esc_attr($c['shop_name']); ?>"
                            data-coupon-primary="<?php echo esc_attr($c['primary']); ?>"
                            data-coupon-code="<?php echo esc_attr($c['code']); ?>">
                      Másolás
                    </button>
                  </div>
                <?php endif; ?>

                <?php if ($show_expiry): ?>
                  <div class="icn-expiry">
                    <span class="icn-countdown" aria-live="polite"></span>
                  </div>
                <?php endif; ?>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <script>
      (function(){
        var root  = document.currentScript.previousElementSibling;
        if(!root) return;
        var track = root.querySelector('.icn-track');
        var prev  = root.querySelector('.icn-arrow.prev');
        var next  = root.querySelector('.icn-arrow.next');
        var showExpiry = root.getAttribute('data-show-expiry') === '1';

        function scrollByAmount(dir){
          var w = track.clientWidth || 320;
          track.scrollBy({ left: dir * Math.max(280, Math.round(w*0.85)), behavior:'smooth' });
        }
        if (prev) prev.addEventListener('click', function(){ scrollByAmount(-1); });
        if (next) next.addEventListener('click', function(){ scrollByAmount( 1); });

        // Autoplay
        var autoplay = <?php echo ($a['autoplay']==='1' ? 'true' : 'false'); ?>;
        var interval = <?php echo intval($interval); ?>;
        var timer = null, paused = false;
        function tick(){
          if (paused) return;
          var maxScroll = track.scrollWidth - track.clientWidth;
          if (track.scrollLeft >= maxScroll - 10) track.scrollTo({ left:0, behavior:'auto' });
          else scrollByAmount(1);
        }
        function start(){ if (autoplay && !timer) timer = setInterval(tick, interval); }
        function stop(){ if (timer) { clearInterval(timer); timer = null; } }
        root.addEventListener('mouseenter', function(){ paused = true; });
        root.addEventListener('mouseleave', function(){ paused = false; });
        ['wheel','touchstart','mousedown','keydown'].forEach(function(ev){
          track.addEventListener(ev, function(){ stop(); setTimeout(start, interval*2); }, {passive:true});
        });
        start();

        // GA4 (view / click / copy)
        if (!window.dataLayer) window.dataLayer = [];

        // coupon_click a kártya-kattintásra
        track.addEventListener('click', function(ev){
          var a = ev.target.closest('a[data-event="coupon_click"]');
          if(!a) return;
          try {
            var q = new URLSearchParams(location.search);
            window.dataLayer.push({
              event: 'coupon_click',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              coupon_primary: a.getAttribute('data-coupon-primary') || '',
              coupon_code: a.getAttribute('data-coupon-code') || '',
              ngo:  q.get('d1')  || '',
              amb:  q.get('amb') || '',
              src:  q.get('src') || 'impactshop'
            });
          } catch(e){}
        }, true);

        // coupon_copy a másolás-gombra
        track.addEventListener('click', function(ev){
          var btn = ev.target.closest('button.icn-copy[data-event="coupon_copy"]');
          if (!btn) return;
          var code = btn.getAttribute('data-coupon-code') || '';
          if (!code) return;

          // Vágólap
          var copied = false;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function(){ copied = true; }).catch(function(){});
          }
          if (!copied) {
            var ta = document.createElement('textarea');
            ta.value = code; document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); } catch(e){}
            document.body.removeChild(ta);
          }

          // GA4 push
          try {
            var q = new URLSearchParams(location.search);
            window.dataLayer.push({
              event: 'coupon_copy',
              shop_slug: btn.getAttribute('data-shop-slug') || '',
              shop_name: btn.getAttribute('data-shop-name') || '',
              coupon_primary: btn.getAttribute('data-coupon-primary') || '',
              coupon_code: code,
              ngo:  q.get('d1')  || '',
              amb:  q.get('amb') || '',
              src:  q.get('src') || 'impactshop'
            });
          } catch(e){}
        }, true);

        // coupon_view – akkor lőjük, amikor a kártya először látszik (IntersectionObserver)
        var seen = new WeakSet();
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(ent){
            if (!ent.isIntersecting) return;
            var card = ent.target;
            if (seen.has(card)) return;
            seen.add(card);
            var a = card.querySelector('a[data-event="coupon_click"]');
            if (!a) return;

            var bucket = (function(){
              var exp = card.getAttribute('data-expiry');
              if (!exp) return 'unknown';
              var left = (new Date(exp).getTime()) - Date.now();
              if (left <= 0) return 'expired';
              var d = left / (24*3600*1000);
              if (d > 7) return '>7d';
              if (d > 1) return '1–7d';
              return '<24h';
            })();

            try {
              var q = new URLSearchParams(location.search);
              window.dataLayer.push({
                event: 'coupon_view',
                shop_slug: a.getAttribute('data-shop-slug') || '',
                shop_name: a.getAttribute('data-shop-name') || '',
                coupon_primary: a.getAttribute('data-coupon-primary') || '',
                coupon_code: a.getAttribute('data-coupon-code') || '',
                time_left_bucket: bucket,
                ngo:  q.get('d1')  || '',
                amb:  q.get('amb') || '',
                src:  q.get('src') || 'impactshop'
              });
            } catch(e){}
          });
        }, { root: track, rootMargin: '0px 20px 0px 0px', threshold: 0.5 });

        Array.prototype.forEach.call(track.querySelectorAll('.icn-card'), function(card){ io.observe(card); });

        // COUNTDOWN: frissítés 1 mp-enként, <24h piros, lejáratkor elrejt
        if (showExpiry) {
          function fmt2(n){ n = Math.floor(Math.abs(n)); return (n<10?'0':'')+n; }
          function fmtDate(dt){
            // YYYY-MM-DD HH:MM (helyi idő – Europe/Budapest)
            return dt.getFullYear()+'-'+fmt2(dt.getMonth()+1)+'-'+fmt2(dt.getDate())+' '+fmt2(dt.getHours())+':'+fmt2(dt.getMinutes());
          }
          function tickCountdown(){
            var cards = track.querySelectorAll('.icn-card');
            cards.forEach(function(card){
              var exp = card.getAttribute('data-expiry');
              var cd  = card.querySelector('.icn-countdown');
              if (!cd) return;
              if (!exp) {
                cd.textContent = 'Lejárat: folyamatos';
                cd.classList.remove('urg-24h','done');
                return;
              }
              var t = new Date(exp).getTime();
              if (isNaN(t)) {
                cd.textContent = 'Lejár: '+exp;
                cd.classList.remove('urg-24h','done');
                return;
              }
              var left = t - Date.now();
              if (left <= 0) {
                cd.textContent = 'Lejárt';
                cd.classList.add('done');
                // Rejtés, hogy ne maradjon "szellemkupon"
                card.style.display = 'none';
                return;
              }
              var days = Math.floor(left / (24*3600*1000));
              var rem = left % (24*3600*1000);
              var hrs  = Math.floor(rem / (3600*1000));
              rem     %= (3600*1000);
              var mins = Math.floor(rem / (60*1000));
              var secs = Math.floor((rem % (60*1000)) / 1000);

              if (days >= 7) {
                cd.textContent = 'Lejár: '+fmtDate(new Date(t));
                cd.classList.remove('urg-24h','done');
              } else if (days >= 1) {
                cd.textContent = 'Lejár: '+days+' nap';
                cd.classList.remove('urg-24h','done');
              } else {
                cd.textContent = 'Lejár: '+fmt2(hrs)+':'+fmt2(mins)+':'+fmt2(secs);
                cd.classList.toggle('urg-24h', true);
                cd.classList.remove('done');
              }
            });
          }
          tickCountdown();
          setInterval(tickCountdown, 1000);
        }
      })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_coupons_netflix','impact_coupons_netflix_shortcode');
}


## 6. kereső
**Státusz**: ✅ Aktív   
**Fájl**:   
**Leírás**: Leírás

```php
<?php
// Shortcode: [impactshop_search placeholder="Webshop vagy termék…" ngo="all" tabs="0" minlen="1" shops_endpoint="/wp-json/impactshop/v1/shops" shops_all_query="?all=1"]
if (!defined('ABSPATH')) exit;

if (!function_exists('impactshop_search_shortcode')) {
  function impactshop_search_shortcode($atts) {
    $a = shortcode_atts([
      'placeholder'     => 'Keresés…',
      'ngo'             => 'all',
      'tabs'            => '0',                  // most csak webshop fül
      'minlen'          => '1',
      'shops_endpoint'  => '/wp-json/impactshop/v1/shops',
      'shops_all_query' => '?all=1',             // ha nem kell, add meg így: shops_all_query=""
      'offers_endpoint' => '/wp-json/impactshop/v1/offers', // későbbre
    ], $atts, 'impactshop_search');

    $uid        = 'isrch_'.substr(md5(json_encode([$a, microtime(true)])),0,8);
    $shops_api  = esc_url( home_url( $a['shops_endpoint'] ) );
    $shops_allq = trim($a['shops_all_query']);
    $ngo        = esc_attr($a['ngo']);
    $minlen     = max(0, (int)$a['minlen']);

    ob_start(); ?>
<style>
  .impactsearch{--bg:#fff;--ink:#0f172a;--muted:#64748b;--br:#e5e7eb;--pri:#7c3aed;--pri2:#6366f1;--shadow:0 12px 40px rgba(2,6,23,.08);
    font:600 15px/1.35 Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--ink)}
  .impactsearch .bar{display:flex;align-items:center;gap:10px;background:var(--bg);border:1px solid var(--br);border-radius:16px;padding:12px 14px;box-shadow:var(--shadow)}
  .impactsearch .bar input{flex:1;border:0;outline:0;font:600 16px/1.2 inherit;color:var(--ink)}
  .impactsearch .btn{border:1px solid var(--br);padding:10px 14px;border-radius:12px;background:linear-gradient(180deg,#fff,#f8fafc);cursor:pointer;font:800 13px/1}
  .impactsearch .btn:active{transform:translateY(1px)}
  .impactsearch .clear{border:0;background:transparent;cursor:pointer;color:var(--muted);display:none}
  .impactsearch .grid{display:grid;gap:10px;margin-top:12px}
  @media(min-width:720px){.impactsearch .grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(min-width:1024px){.impactsearch .grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
  .impactsearch .card{display:flex;gap:12px;align-items:center;border:1px solid var(--br);background:#fff;border-radius:14px;padding:12px;transition:transform .12s, box-shadow .12s}
  .impactsearch .card:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(2,6,23,.08)}
  .impactsearch .logo{width:44px;height:44px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;overflow:hidden}
  .impactsearch .logo img{max-width:100%;max-height:100%;object-fit:contain}
  .impactsearch .title{font:800 15px/1.2 inherit;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .impactsearch .sub{font:600 12px/1.2 inherit;color:var(--muted)}
  .impactsearch .pill{margin-left:auto; font:900 12px/1; color:#111827; background:#f8fafc; border:1px solid var(--br); padding:6px 10px; border-radius:10px}
  .impactsearch .empty{color:var(--muted);padding:10px 2px}
  .impactsearch .spinner{width:18px;height:18px;border:2px solid #e5e7eb;border-top-color:var(--pri);border-radius:50%;animation:spin .8s linear infinite;margin-left:6px}
  @keyframes spin{to{transform:rotate(360deg)}}
  .impactsearch .row-active{outline:2px solid #c7d2fe}
  .impactsearch .hint{color:var(--muted);font:600 12px;margin-top:8px}
</style>

<div id="<?php echo esc_attr($uid); ?>" class="impactsearch"
     data-shops="<?php echo $shops_api; ?>"
     data-shopsq="<?php echo esc_attr($shops_allq); ?>"
     data-ngo="<?php echo $ngo; ?>"
     data-minlen="<?php echo (int)$minlen; ?>">
  <div class="bar">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 21l-4.2-4.2M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" stroke="#64748b" stroke-width="2" stroke-linecap="round"/></svg>
    <input type="search" placeholder="<?php echo esc_attr($a['placeholder']); ?>" aria-label="Keresés" />
    <div class="spinner" data-spin style="display:none"></div>
    <button class="clear" title="Törlés" aria-label="Törlés">✕</button>
    <button class="btn" data-submit>Keresés</button>
  </div>

  <div class="grid" data-grid></div>
  <div class="empty" data-empty style="display:none">Nincs találat. Próbáld: „alza”, „decathlon”, „játék”, „szemüveg”…</div>
  <div class="hint">Tipp: gépelés közben is keres. Nyilak: ↑/↓, megnyitás: Enter.</div>
</div>

<script>
(function(){
  const root = document.getElementById(<?php echo json_encode($uid); ?>);
  if(!root) return;
  const shopsAPI = root.getAttribute('data-shops');
  const shopsQ   = root.getAttribute('data-shopsq') || '';
  const ngo      = root.getAttribute('data-ngo') || 'all';
  const minlen   = parseInt(root.getAttribute('data-minlen')||'1',10);

  const input = root.querySelector('input[type=search]');
  const btnSubmit = root.querySelector('[data-submit]');
  const btnClear  = root.querySelector('.clear');
  const spinner   = root.querySelector('[data-spin]');
  const grid      = root.querySelector('[data-grid]');
  const emptyBox  = root.querySelector('[data-empty]');

  // kis segédek
  const ga4 = (e,p)=>{ try{ window.gtag && window.gtag('event', e, p||{});}catch(_){} };
  const debounce=(fn,ms)=>{ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms);} };
  const esc = s=>String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));
  const norm = s=>{ try{ return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }catch(_){ return (s||'').toString().toLowerCase(); } };

  function goShop(slug){ return '/go/'+encodeURIComponent(slug)+'?d1='+encodeURIComponent(ngo); }

  // ----- Teljes shop-lista letöltése (többlapos is lehet) -----
  let SHOPS = []; let focusIndex = -1;

  async function fetchJSON(url){
    const r = await fetch(url, {credentials:'same-origin'}); if(!r.ok) throw new Error('HTTP '+r.status);
    try { return await r.json(); } catch(_) { return []; }
  }
  function normalizeList(j){
    // lehet [] vagy {rows:[]} vagy {data:[]}
    if (Array.isArray(j)) return j;
    if (j && Array.isArray(j.rows)) return j.rows;
    if (j && Array.isArray(j.data)) return j.data;
    return [];
  }
  async function loadAllShops(){
    spinner.style.display='inline-block';
    let all = [];
    try{
      // 1) próbáld all=1-gyel (ha üres: üres string lesz, így duplázás nincs)
      const base = shopsAPI + (shopsQ || '');
      let url = base, page = 1, guard = 0;
      while (url && guard++ < 20) {
        const j  = await fetchJSON(url);
        const arr= normalizeList(j);
        all = all.concat(arr);
        // pagináció támogatás:
        if (j && j.next) { url = j.next; }
        else if (arr && arr.length && /[?&]page=/.test(url)) { url = url.replace(/([?&]page=)(\d+)/, (_,p,n)=> p + (parseInt(n,10)+1) ); }
        else if (guard===1 && !shopsQ) { // ha nem volt all=1 és nincs lapozás, megpróbáljuk page=2-t
          url = url + (url.includes('?')?'&':'?') + 'page=2';
        } else { url = null; }
      }
    }catch(e){ /* lenyeljük, marad all=[] */ }
    // duplikátumok kiszűrése slug alapján
    const seen = new Set(); const out=[];
    for (const r of all){
      const slug = (r.shop_slug||r.slug||r.id||'').toString();
      if(!slug || seen.has(slug)) continue;
      seen.add(slug); out.push(r);
    }
    SHOPS = out;
    spinner.style.display='none';
  }

  // ----- Kliens oldali fuzzy keresés -----
  function scoreShop(r, qn){
    const name = norm(r.shop_name || r.name || '');
    const slug = norm(r.shop_slug || '');
    const cat  = norm(r.category || '');
    // prefix boost, rész-találat, token találat
    function s(str){
      if(!qn) return 0;
      if (str.startsWith(qn)) return 1.0;
      const i = str.indexOf(qn); if (i>=0) return 0.7 - Math.min(i,30)/100;
      // token-rész egyezés
      const qs = qn.split(/\s+/).filter(Boolean), ss=str.split(/\s+/);
      let hit=0; qs.forEach(t=>{ if(ss.some(w=>w.indexOf(t)>=0)) hit++; });
      return hit? 0.4 + hit/qs.length*0.4 : 0;
    }
    return Math.max(s(name), s(slug), s(cat));
  }

  function renderList(rows){
    grid.innerHTML=''; emptyBox.style.display='none'; focusIndex=-1;
    if(!rows.length){ emptyBox.style.display='block'; return; }
    for (const r of rows){
      const a = document.createElement('a'); a.className='card'; a.href=goShop(r.shop_slug||r.slug||'');
      a.innerHTML = `
        <div class="logo">${ r.logo_url? `<img src="${esc(r.logo_url)}" alt="">` : '' }</div>
        <div class="meta">
          <div class="title">${esc(r.shop_name || r.name || r.shop_slug || 'Webshop')}</div>
          ${ r.category ? `<div class="sub">${esc(r.category)}</div>` : '' }
        </div>
        <span class="pill">Megnyitás</span>`;
      a.addEventListener('click', ()=>ga4('search_result_click',{scope:'shop',query:input.value||'',shop_slug:r.shop_slug||'',shop_name:r.shop_name||r.name||''}));
      grid.appendChild(a);
    }
  }

  function runSearch(){
    const q = (input.value||'').trim();
    btnClear.style.display = q ? 'inline-block' : 'none';
    if(q.length < minlen){ renderList([]); return; }
    const qn = norm(q);
    // rangsorolt fuzzy
    const list = SHOPS.map(r=>({r,sc:scoreShop(r, qn)})).filter(x=>x.sc>0.35).sort((a,b)=>b.sc-a.sc).map(x=>x.r).slice(0,60);
    renderList(list);
  }
  const debRun = debounce(runSearch, 180);

  // interakciók
  input.addEventListener('input', debRun);
  btnSubmit.addEventListener('click', ()=>{ ga4('search_submit',{query:input.value||'',scope:'shop'}); runSearch(); });
  btnClear.addEventListener('click', ()=>{ input.value=''; btnClear.style.display='none'; renderList([]); input.focus(); });

  // billentyű navigáció
  input.addEventListener('keydown', (e)=>{
    const rows = Array.from(grid.querySelectorAll('a.card'));
    if(!rows.length) return;
    if(e.key==='ArrowDown'){ e.preventDefault(); focusIndex=Math.min(rows.length-1, focusIndex+1); setFocus(rows); }
    if(e.key==='ArrowUp'){ e.preventDefault(); focusIndex=Math.max(0, focusIndex-1); setFocus(rows); }
    if(e.key==='Enter' && focusIndex>=0){ e.preventDefault(); rows[focusIndex].click(); }
  });
  function setFocus(rows){
    rows.forEach(r=>r.classList.remove('row-active'));
    if(rows[focusIndex]){ rows[focusIndex].classList.add('row-active'); rows[focusIndex].scrollIntoView({block:'nearest'}); }
  }

  // indulás: teljes shop lista behúzása, majd keresés
  (async function init(){
    await loadAllShops();
    // ha az inputban már van szöveg (pl. böngésző automatikus kitöltés), keressünk rá
    if (input.value) runSearch();
  })();
})();
</script>
<?php
    return ob_get_clean();
  }
  add_shortcode('impactshop_search', 'impactshop_search_shortcode');
}


## 7. NGO TOP lista
**Státusz**: ✅ Aktív   
**Fájl**:   
**Leírás**: Leírás

```php
<?php
/**
 * Sharity – NGO toplista és egyedi NGO kártya (rolling intervallummal, animációval)
 * Shortcode-ok:
 *  - [impact_ngo_top  from="" to="" status="approved" limit="10" rate_huf="392" currency="HUF" exclude_unknown="1" title="Top NGO-k"]
 *  - [impact_ngo_card ngo=""   label="" from="" to="" status="approved" rate_huf="392" currency="HUF" accent="#7c3aed"]
 *
 * Logika (belső): adomány = commission * 0.5; opcionális HUF konverzió fix Ft/€ árfolyammal.
 * Ha "to" üres vagy "auto": kezdőtől MA-ig (rolling).
 */

if (!defined('ABSPATH')) exit;

/* ---------- Közös segédek ---------- */
if (!function_exists('s_slugify_hu')) {
  function s_slugify_hu($s){
    $s = wp_strip_all_tags((string)$s);
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    if (function_exists('iconv')) { $t=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); if($t!==false) $s=$t; }
    $s = strtolower(preg_replace('~[^a-z0-9]+~','-',$s));
    return trim($s,'-');
  }
}
if (!function_exists('s_is_unknown')) {
  function s_is_unknown($name){
    $n = strtolower(trim((string)$name));
    return ($n === '' || preg_match('~\b(ismeretlen|unknown)\b~i', $n));
  }
}
if (!function_exists('s_fetch_totals')) {
  function s_fetch_totals($args){
    $def = [
      'from'   => date('Y-m-01'),
      'to'     => date('Y-m-d'),
      'status' => 'approved',
      'group'  => 'ngo',
    ];
    $q = array_merge($def, $args);
    $url = add_query_arg($q, home_url('/wp-json/impactshop/v1/totals'));
    $key = 'ngo_tot_'.md5($url);
    if (($cached = get_transient($key)) !== false) return $cached;

    $resp = wp_remote_get($url, ['timeout'=>12, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($resp)) return ['rows'=>[], 'meta'=>[], '_error'=>$resp->get_error_message()];
    $code = wp_remote_retrieve_response_code($resp);
    if ($code<200 || $code>=300) return ['rows'=>[], 'meta'=>[], '_error'=>'HTTP '.$code];
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data)) return ['rows'=>[], 'meta'=>[], '_error'=>'JSON'];
    set_transient($key, $data, 180); // 3 perc
    return $data;
  }
}
if (!function_exists('s_fmt_money')) {
  function s_fmt_money($v, $currency='HUF'){
    if (strtoupper($currency)==='HUF') return number_format((float)$v, 0, '.', ' ').' Ft';
    return '€ '.number_format((float)$v, 2, ',', ' ');
  }
}

/* ---------- [impact_ngo_top] – NGO toplista ---------- */
if (!function_exists('impact_ngo_top_shortcode')) {
  function impact_ngo_top_shortcode($atts){
    $a = shortcode_atts([
      'from'            => date('Y-m-01'),
      'to'              => '',             // üres = ma-ig
      'status'          => 'approved',
      'limit'           => '10',
      'rate_huf'        => '392',       // Ft/€
      'currency'        => 'HUF',         // HUF|EUR
      'exclude_unknown' => '1',
      'title'           => 'Top NGO-k',
      'accent'          => '#22c55e',     // neon effektek színe
      'refresh'         => '60',          // mp – opcionális autófriss vizuál
    ], $atts, 'impact_ngo_top');

    $limit     = max(1, (int)$a['limit']);
    $rate_huf  = (float)$a['rate_huf'];
    $currency  = strtoupper(trim($a['currency']?:'HUF'));
    $no_unk    = ($a['exclude_unknown']==='1');
    $to        = (trim((string)$a['to'])!=='') ? $a['to'] : date('Y-m-d');
    $accent    = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#22c55e';
    $refresh   = max(0, (int)$a['refresh']);

    $data = s_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'ngo']);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];

    $list = [];
    foreach ($rows as $r){
      $name = $r['ngo'] ?? $r['ngo_name'] ?? '';
      if ($no_unk && s_is_unknown($name)) continue;
      $slug = $r['ngo_slug'] ?? s_slugify_hu($name);
      $don_eur = ((float)($r['commission'] ?? 0)) * 0.5;
      $amt = ($currency==='HUF') ? ($don_eur * $rate_huf) : $don_eur;
      if ($amt <= 0) continue;
      $list[] = ['slug'=>$slug, 'name'=>($name?:$slug), 'amt'=>$amt];
    }
    usort($list, fn($x,$y)=> ($y['amt'] <=> $x['amt']) ?: strcasecmp($x['name'],$y['name']));
    $list = array_slice($list, 0, $limit);

    $uid = 'ngotop_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{--ink:#0f172a;--muted:#64748b;--br:#e5e7eb;--glow:<?php echo esc_html($accent); ?>; color:var(--ink);
        font:600 14px/1.35 Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
      .<?php echo $uid; ?> .hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;color:#475569}
      .<?php echo $uid; ?> .title{font:900 16px/1.1 Inter}
      .<?php echo $uid; ?> .rng{font:600 12px/1.2 Inter;color:var(--muted)}
      .<?php echo $uid; ?> ol{margin:0;padding:0;list-style:none;display:grid;gap:10px}
      .<?php echo $uid; ?> .row{position:relative;display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--br);
        border-radius:14px;background:linear-gradient(180deg,#fff,#fafafa);box-shadow:0 12px 28px rgba(2,6,23,.06);overflow:hidden}
      .<?php echo $uid; ?> .row::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(34,197,94,.12),transparent);
        transform:translateX(-100%);opacity:0;pointer-events:none}
      .<?php echo $uid; ?> .row.bump::after{animation:sweep 750ms ease}
      @keyframes sweep{0%{transform:translateX(-120%);opacity:0}40%{opacity:1}100%{transform:translateX(120%);opacity:0}}
      .<?php echo $uid; ?> .rank{width:28px;height:28px;border-radius:8px;background:#eef2ff;color:#3730a3;display:grid;place-items:center;font:900 12px/1 Inter}
      .<?php echo $uid; ?> .name{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font:800 14px/1.2 Inter}
      .<?php echo $uid; ?> .amt{font:900 14px/1 Inter}
      .<?php echo $uid; ?> .amt .num{display:inline-block;min-width:6ch;text-align:right}
      .<?php echo $uid; ?> .amt.bump{filter:drop-shadow(0 0 8px var(--glow))}
    </style>
    <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
      <div class="hdr">
        <div class="title"><?php echo esc_html($a['title']); ?></div>
        <div class="rng"><?php echo esc_html($a['from'].' → '.$to); ?></div>
      </div>
      <?php if (!$list): ?>
        <div style="color:#64748b">Nincs megjeleníthető adat.</div>
      <?php else: ?>
        <ol>
          <?php foreach ($list as $i=>$it): ?>
            <li class="row" data-amt="<?php echo esc_attr(number_format($it['amt'], 2, '.', '')); ?>">
              <div class="rank"><?php echo $i+1; ?></div>
              <div class="name"><?php echo esc_html($it['name']); ?></div>
              <div class="amt"><span class="num"><?php echo esc_html(s_fmt_money($it['amt'],$currency)); ?></span></div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const rows = Array.from(root.querySelectorAll('.row'));
      // count-up anim növekedéskor
      function parseNum(s){ return parseFloat(String(s).replace(/[^\d.]/g,''))||0; }
      function animateCount(el, oldV, newV, fmt){
        if(newV<=oldV){ el.textContent = fmt(newV); return; }
        const dur=700, t0=performance.now();
        function step(t){
          const k=Math.min(1,(t-t0)/dur);
          const val = oldV + (newV-oldV)*(0.5-0.5*Math.cos(Math.PI*k)); // cos-ease
          el.textContent = fmt(val);
          if(k<1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      }
      function fmtMoney(v){
        // a megjelenített pénznem már be van formázva szerveren; itt csak ezresre kerekítünk gyorsan
        const n = Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');
        // próbáljuk kiírni a mértékegységet a DOM alapján (szerveres stringből)
        const tail = (root.textContent.indexOf('€')>-1) ? ' €' : ' Ft';
        return n + tail;
      }
      // initial cache
      rows.forEach(r=> r._last = parseNum(r.getAttribute('data-amt')||'0'));
      // opcionális periódikus friss vizuál (pl. ha cache lejár)
      const refresh = parseInt(root.getAttribute('data-refresh')||'0',10);
      if(refresh>0){
        setInterval(()=> {
          rows.forEach(r=>{
            const cur = parseNum(r.getAttribute('data-amt')||'0');
            // demo: tegyünk úgy, mintha nőtt volna? – élesben itt újrarender után fut le
            // Itt csak az effekt: ha nőtt, „bump”
            if (cur > r._last) {
              const numEl = r.querySelector('.amt .num');
              r.classList.add('bump'); r.querySelector('.amt').classList.add('bump');
              animateCount(numEl, r._last, cur, fmtMoney);
              setTimeout(()=>{ r.classList.remove('bump'); r.querySelector('.amt').classList.remove('bump'); }, 800);
              r._last = cur;
            }
          });
        }, refresh*1000);
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_ngo_top', 'impact_ngo_top_shortcode');
}

/* ---------- [impact_ngo_card] – Egy NGO kártya ---------- */
if (!function_exists('impact_ngo_card_shortcode')) {
  function impact_ngo_card_shortcode($atts){
    $a = shortcode_atts([
      'ngo'      => '',
      'label'    => '',            // megjelenítendő név (pl. "Bátor Tábor Alapítvány")
      'from'     => date('Y-m-01'),
      'to'       => '',            // üres = ma-ig
      'status'   => 'approved',
      'rate_huf' => '392',
      'currency' => 'HUF',
      'accent'   => '#7c3aed',
      'refresh'  => '45',          // mp – opcionális vizuális figyelés
    ], $atts, 'impact_ngo_card');

    $ngo_req   = trim((string)$a['ngo']);
    $ngo_slug  = s_slugify_hu($ngo_req);
    $to        = (trim((string)$a['to'])!=='') ? $a['to'] : date('Y-m-d');
    $rate_huf  = (float)$a['rate_huf'];
    $currency  = strtoupper(trim($a['currency']?:'HUF'));
    $accent    = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#7c3aed';
    $refresh   = max(0, (int)$a['refresh']);

    $data = s_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'ngo','ngo'=>$ngo_req]);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];

    $sum_comm = 0.0; $ngo_name = '';
    foreach ($rows as $r){
      $name = $r['ngo'] ?? $r['ngo_name'] ?? '';
      if (s_is_unknown($name)) continue;
      $slug = $r['ngo_slug'] ?? s_slugify_hu($name);
      if ($ngo_slug && strtolower($slug)!==strtolower($ngo_slug)) continue;
      $sum_comm += (float)($r['commission'] ?? 0);
      $ngo_name = $ngo_name ?: $name;
    }
    $don_eur = $sum_comm * 0.5;
    $amt = ($currency==='HUF') ? ($don_eur * $rate_huf) : $don_eur;

    $uid = 'ngocard_'.substr(md5(json_encode([$a, microtime(true)])),0,8);
    $display = ($a['label']!=='') ? $a['label'] : ($ngo_name ?: $ngo_req);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{--accent:<?php echo esc_html($accent); ?>;--ink:#0f172a;--muted:#64748b;--br:#e5e7eb;
        font:600 14px/1.35 Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--ink)}
      .<?php echo $uid; ?> .card{position:relative;padding:18px;border:1px solid var(--br);border-radius:18px;
        background:radial-gradient(1000px 600px at 10% 0%, color-mix(in srgb, var(--accent) 25%, transparent), transparent 40%),
                    linear-gradient(180deg,#fff,#fafafa);
        box-shadow:0 14px 40px rgba(2,6,23,.08); overflow:hidden}
      .<?php echo $uid; ?> .sweep{position:absolute;inset:0;pointer-events:none;background:linear-gradient(120deg, transparent, color-mix(in srgb, var(--accent) 25%, transparent), transparent);
        transform:translateX(-120%); opacity:0}
      .<?php echo $uid; ?> .card.bump .sweep{animation:cardSweep 800ms ease}
      @keyframes cardSweep{0%{transform:translateX(-140%);opacity:0}40%{opacity:1}100%{transform:translateX(140%);opacity:0}}
      .<?php echo $uid; ?> .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
      .<?php echo $uid; ?> .name{font:900 18px/1.2 Inter}
      .<?php echo $uid; ?> .rng{font:600 12px/1.2 Inter;color:var(--muted)}
      .<?php echo $uid; ?> .amount{font:900 clamp(28px,6vw,40px)/1.08 Inter;color:#0b1220;text-shadow:0 2px 14px color-mix(in srgb, var(--accent) 25%, transparent)}
      .<?php echo $uid; ?> .amount .num{display:inline-block; min-width:8ch; text-align:right}
      .<?php echo $uid; ?> .badge{margin-top:6px;display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid color-mix(in srgb, var(--accent) 45%, #e5e7eb);
        background:color-mix(in srgb, var(--accent) 10%, #fff); color:#0b1220;font:800 11px/1 Inter;letter-spacing:.04em}
    </style>
    <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
      <div class="card" data-amt="<?php echo esc_attr(number_format($amt, 2, '.', '')); ?>">
        <div class="sweep"></div>
        <div class="head">
          <div class="name"><?php echo esc_html($display ?: 'NGO'); ?></div>
          <div class="rng"><?php echo esc_html($a['from'].' → '.$to); ?></div>
        </div>
        <div class="amount"><span class="num"><?php echo esc_html(s_fmt_money($amt, $currency)); ?></span></div>
        <div class="badge"><?php echo esc_html($currency); ?> · Árfolyam: <?php echo number_format((float)$a['rate_huf'], 2, '.', ' '); ?> Ft/€</div>
      </div>
    </div>
    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const card = root.querySelector('.card');
      const numEl= root.querySelector('.amount .num');
      function parseNum(s){ return parseFloat(String(s).replace(/[^\d.]/g,''))||0; }
      function fmtMoney(v){
        const txt = root.textContent;
        const isEur = txt.indexOf('€')>-1;
        if(isEur){ return '€ '+(parseFloat(v).toFixed(2).replace('.',',')); }
        // HUF – kerekítve, ezres tagolással
        const n = Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');
        return n + ' Ft';
      }
      let last = parseNum(card.getAttribute('data-amt')||'0');
      function animateTo(newV){
        if(newV<=last){ numEl.textContent = fmtMoney(newV); last=newV; return; }
        const dur=900, t0=performance.now();
        card.classList.add('bump');
        function step(t){
          const k=Math.min(1,(t-t0)/dur);
          const val = last + (newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
          numEl.textContent = fmtMoney(val);
          if(k<1) requestAnimationFrame(step); else {
            setTimeout(()=>card.classList.remove('bump'), 100);
            last=newV;
          }
        }
        requestAnimationFrame(step);
      }
      // opcionális vizuális figyelés (ha a szerver újrarender után növelné az értéket)
      const refresh = parseInt(root.getAttribute('data-refresh')||'0',10);
      if(refresh>0){
        setInterval(()=>{
          const cur = parseNum(card.getAttribute('data-amt')||'0');
          if(cur>last) animateTo(cur);
        }, refresh*1000);
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_ngo_card', 'impact_ngo_card_shortcode');
}

---

## Template további snippetekhez:

## 8. Adományszámláló HUF UX/UI B verzió
**Státusz**: ✅  
**Fájl**: fájlnév  
**Leírás**: leírás

```php
<?php
/**
 * Sharity – Summa adomány számláló (UI + mini) + diagnosztika
 * Shortcode-ok:
 *  - [impact_sum_counter from="" to="" status="all" currency="HUF" rate_huf="392"
 *                        exclude_unknown="1" unknown_scope="ngo"
 *                        title="Összegyűjtve" refresh="60" accent="#10b981"]
 *  - [impact_sum_mini from="" to="" status="all" currency="HUF" rate_huf="392"
 *                     exclude_unknown="1" unknown_scope="ngo" refresh="60"]
 *  - [impact_sum_diag from="" to="" status="all" rate_huf="392"
 *                     exclude_unknown="1" unknown_scope="ngo"]
 */

if (!defined('ABSPATH')) exit;

/* ===== segédek ===== */
function _ims_today(){ return date('Y-m-d'); }
function _ims_from_default(){ return date('Y-m-01'); }

function _ims_is_unknown($s){
  $n = strtolower(trim((string)$s));
  return ($n==='' || strpos($n,'ismeretlen')!==false || strpos($n,'unknown')!==false);
}

function _ims_fetch_totals($args){
  $def = ['from'=>_ims_from_default(),'to'=>_ims_today(),'status'=>'all','group'=>'shop_ngo'];
  $q = array_merge($def, $args);
  $url = add_query_arg($q, home_url('/wp-json/impactshop/v1/totals'));
  $key = 'ims_tot_'.md5($url);
  if (($c=get_transient($key))!==false) return $c;
  $r = wp_remote_get($url, ['timeout'=>12,'headers'=>['Accept'=>'application/json']]);
  if (is_wp_error($r)) return ['_error'=>$r->get_error_message()];
  $code = wp_remote_retrieve_response_code($r);
  if ($code<200 || $code>=300) return ['_error'=>'HTTP '.$code];
  $data = json_decode(wp_remote_retrieve_body($r), true);
  if (!is_array($data)) return ['_error'=>'JSON parse'];
  set_transient($key, $data, 120);
  return $data;
}

function _ims_commission_with_unknown($data, $exclude, $scope){
  $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
  if (!$exclude) return (float)($data['meta']['grand']['commission'] ?? 0);
  $sum=0.0;
  foreach ($rows as $r){
    $ngo  = $r['ngo']  ?? $r['ngo_name']  ?? '';
    $shop = $r['shop'] ?? $r['shop_name'] ?? $r['shop_slug'] ?? '';
    $drop = ($scope === 'ngo')
            ? _ims_is_unknown($ngo)
            : ( ($scope === 'shop')
                ? _ims_is_unknown($shop)
                : (_ims_is_unknown($ngo) || _ims_is_unknown($shop))
              );
    if ($drop) continue;
    $sum += (float)($r['commission'] ?? 0);
  }
  if ($sum===0.0 && !$rows) $sum=(float)($data['meta']['grand']['commission'] ?? 0);
  return $sum;
}

function _ims_fmt_money($v, $currency='HUF'){
  if (strtoupper($currency)==='HUF') return number_format((float)$v, 0, '.', ' ').' Ft';
  return '€ '.number_format((float)$v, 2, ',', ' ');
}

/* ===== [impact_sum_mini] – HERO / kontrasztos mini számláló ===== */
add_shortcode('impact_sum_mini', function($atts){
  $a = shortcode_atts([
    'from'=>_ims_from_default(),'to'=>'','status'=>'all',
    'currency'=>'HUF','rate_huf'=>'392',
    'exclude_unknown'=>'1','unknown_scope'=>'ngo',
    'refresh'=>'60','accent'=>'#7c3aed','label'=>''
  ], $atts, 'impact_sum_mini');

  $to=(trim($a['to'])!=='')?$a['to']:_ims_today();
  $rate=(float)$a['rate_huf'];
  $currency=strtoupper(trim($a['currency']));
  $excl=($a['exclude_unknown']==='1');
  $scope=in_array($a['unknown_scope'],['ngo','shop','both'],true)?$a['unknown_scope']:'ngo';
  $refresh=max(0,(int)$a['refresh']);
  $accent=preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i',$a['accent'])?$a['accent']:'#7c3aed';

  $data=_ims_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'shop_ngo']);
  if(isset($data['_error'])) return '<div class="impact-box impact-error">Mini hiba: '.esc_html($data['_error']).'</div>';

  $commission=_ims_commission_with_unknown($data,$excl,$scope);
  $don_eur=$commission*0.5;
  $amount=($currency==='HUF')?($don_eur*$rate):$don_eur;

  $uid='immini_'.substr(md5(json_encode([$a,microtime(true)])),0,8);

  ob_start(); ?>
  <style>
    .<?php echo $uid; ?>{
      --accent: <?php echo esc_html($accent); ?>;
      --glass: rgba(255,255,255,.18);
      --border: rgba(255,255,255,.25);
      --shadow: rgba(2,6,23,.35);
      font-family: Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      text-align:center;
    }
    .<?php echo $uid; ?> .wrap{
      display:inline-block; min-width: min(90vw, 1120px);
      padding: 18px 28px; border-radius: 22px;
      background:
        radial-gradient(1100px 600px at 10% -20%, color-mix(in srgb, var(--accent) 35%, transparent), transparent 50%),
        linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
      backdrop-filter: blur(14px);
      border: 1px solid var(--border);
      box-shadow: 0 28px 60px var(--shadow), inset 0 0 0 1px rgba(255,255,255,.06);
    }
    .<?php echo $uid; ?> .label{
      display:block; margin-bottom:2px; letter-spacing:.08em; text-transform:uppercase;
      font-weight:800; font-size:12px; color:#e5e7eb; text-shadow:0 1px 0 rgba(0,0,0,.25);
    }
    .<?php echo $uid; ?> .num{
      font-weight: 900;
      font-size: clamp(40px, 12vw, 88px);
      line-height: 1.02;
      color:#ffffff;
      text-shadow:
        0 2px 18px color-mix(in srgb, var(--accent) 32%, transparent),
        0 6px 30px rgba(0,0,0,.35);
    }
    .<?php echo $uid; ?> .rng{
      margin-top:6px; font-weight:700; font-size:13px; color:#e2e8f0;
      text-shadow: 0 1px 0 rgba(0,0,0,.3);
      opacity:.95;
    }
    /* finom sweep villanás növekedéskor */
    .<?php echo $uid; ?> .wrap.bump::after{
      content:""; position:absolute; inset:0; pointer-events:none;
      background: linear-gradient(120deg, transparent,
                 color-mix(in srgb, var(--accent) 40%, transparent), transparent);
      animation: <?php echo $uid; ?>_sweep 850ms ease;
      border-radius:22px;
    }
    @keyframes <?php echo $uid; ?>_sweep {
      0% { transform: translateX(-140%); opacity:0 }
      40%{ opacity:1 }
      100%{ transform: translateX(140%); opacity:0 }
    }
  </style>
  <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
    <div class="wrap" style="position:relative">
      <?php if ($a['label']!==''): ?>
        <span class="label"><?php echo esc_html($a['label']); ?></span>
      <?php endif; ?>
      <div class="num" data-amt="<?php echo esc_attr(number_format($amount, 2, '.', '')); ?>">
        <?php echo esc_html(_ims_fmt_money($amount,$currency)); ?>
      </div>
      <div class="rng"><?php echo esc_html($a['from'].' → '.$to); ?></div>
    </div>
  </div>
  <script>
  (function(){
    const root=document.currentScript.previousElementSibling;if(!root)return;
    const box=root.querySelector('.wrap'); const num=root.querySelector('.num');
    const refresh=parseInt(root.getAttribute('data-refresh')||'0',10);
    function parseNum(s){return parseFloat(String(s).replace(/[^\d.]/g,''))||0;}
    function fmt(v){
      const isHUF=root.textContent.indexOf('€')===-1;
      if(isHUF){return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ')+' Ft';}
      const n=(Math.round(v*100)/100).toFixed(2).replace('.',','); return '€ '+n;
    }
    let last=parseNum(num.getAttribute('data-amt')||num.textContent);
    function animateTo(newV){
      if(newV<=last){ num.textContent=fmt(newV); last=newV; return; }
      const dur=1000, t0=performance.now(); box.classList.add('bump');
      function step(t){
        const k=Math.min(1,(t-t0)/dur);
        const val=last+(newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
        num.textContent=fmt(val);
        if(k<1) requestAnimationFrame(step); else { last=newV; setTimeout(()=>box.classList.remove('bump'),120); }
      }
      requestAnimationFrame(step);
    }
    if(refresh>0){
      setInterval(()=>{
        const cur=parseNum(num.getAttribute('data-amt')||num.textContent);
        if(cur>last) animateTo(cur);
      }, refresh*1000);
    }
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ===== [impact_sum_counter] – nagy verzió (skin: default|glass) ===== */
add_shortcode('impact_sum_counter', function($atts){
  $a = shortcode_atts([
    'from'           => _ims_from_default(),
    'to'             => '',
    'status'         => 'all',
    'currency'       => 'HUF',
    'rate_huf'       => '392',
    'title'          => 'Összegyűjtve',
    'exclude_unknown'=> '1',
    'unknown_scope'  => 'ngo',
    'refresh'        => '60',
    'accent'         => '#7c3aed',
    'skin'           => 'glass',   // ÚJ: 'glass' = a kért klasszikus design
    'badge'          => '1',       // ÚJ: jobb felső árfolyam badge megjelenítése
    'badge_prefix'   => 'Árfolyam',     // ÚJ: badge elé írt kis jelzés (üresre állítható)
  ], $atts, 'impact_sum_counter');

  $to       = (trim($a['to'])!=='') ? $a['to'] : _ims_today();
  $rate     = (float)$a['rate_huf'];
  $currency = strtoupper(trim($a['currency']));
  $excl     = ($a['exclude_unknown']==='1');
  $scope    = in_array($a['unknown_scope'], ['ngo','shop','both'], true) ? $a['unknown_scope'] : 'ngo';
  $refresh  = max(0, (int)$a['refresh']);
  $accent   = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#7c3aed';
  $skin     = ($a['skin']==='glass') ? 'glass' : 'default';
  $show_badge = ($a['badge']==='1');

  // adat
  $data = _ims_fetch_totals(['from'=>$a['from'], 'to'=>$to, 'status'=>$a['status'], 'group'=>'shop_ngo']);
  if (isset($data['_error'])) return '<div class="impact-box impact-error">Összeg hiba: '.esc_html($data['_error']).'</div>';
  $commission = _ims_commission_with_unknown($data, $excl, $scope);
  $don_eur = $commission * 0.5;
  $amount  = ($currency==='HUF') ? ($don_eur * $rate) : $don_eur;

  $uid = 'imsum_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

  // ===== UI =====
  ob_start(); ?>
  <style>
    .<?php echo $uid; ?>{font:600 14px/1.35 Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}

    <?php if ($skin==='glass'): /* ————— a kért klasszikus, nagy kártya ————— */ ?>
      .<?php echo $uid; ?> .wrap{
        position:relative; padding:34px 40px; border-radius:36px;
        background:
          radial-gradient(1200px 680px at 8% -20%, color-mix(in srgb, <?php echo $accent; ?> 28%, transparent), transparent 55%),
          linear-gradient(135deg, #5b46a5 0%, #5d75b3 40%, #4aa08f 100%);
        color:#fff;
        box-shadow:
          0 28px 60px rgba(2,6,23,.25),
          inset 0 1px 0 rgba(255,255,255,.25);
      }
      .<?php echo $uid; ?> .title{
        font:800 13px/1 Inter; letter-spacing:.10em; text-transform:uppercase; opacity:.9; margin-bottom:8px; text-shadow:0 1px 0 rgba(0,0,0,.25)
      }
      .<?php echo $uid; ?> .value{
        font:900 clamp(44px,7.8vw,92px)/1.02 Inter; text-shadow:0 6px 30px rgba(0,0,0,.35)
      }
      .<?php echo $uid; ?> .value .cur{font:900 clamp(18px,3vw,32px)/1.05 Inter; margin-left:.35em; opacity:.9}
      .<?php echo $uid; ?> .rng{
        margin-top:18px; font:700 14px/1.2 Inter; opacity:.9; text-shadow:0 1px 0 rgba(0,0,0,.25)
      }
      /* jobb felső badge */
      .<?php echo $uid; ?> .badge{
        position:absolute; top:14px; right:14px;
        background:linear-gradient(180deg, rgba(255,255,255,.65), rgba(255,255,255,.45));
        color:#0b1220; border-radius:999px; padding:8px 14px; font:800 12px/1 Inter;
        border:1px solid rgba(255,255,255,.6); box-shadow:0 6px 20px rgba(0,0,0,.18)
      }
      /* finom fénycsík növekedéskor */
      .<?php echo $uid; ?> .wrap.bump::after{
        content:""; position:absolute; inset:0; pointer-events:none; border-radius:36px;
        background: linear-gradient(120deg, transparent, rgba(255,255,255,.30), transparent);
        animation: <?php echo $uid; ?>_sweep 850ms ease;
      }
      @keyframes <?php echo $uid; ?>_sweep { 0%{transform:translateX(-140%);opacity:0} 40%{opacity:1} 100%{transform:translateX(140%);opacity:0} }

    <?php else: /* ————— a jelenlegi “default” bőr ————— */ ?>
      .<?php echo $uid; ?>{color:#0b1220}
      .<?php echo $uid; ?> .wrap{
        position:relative; padding:18px 20px; border:1px solid #e5e7eb; border-radius:20px;
        background: radial-gradient(900px 520px at 6% -10%, color-mix(in srgb, <?php echo $accent; ?> 16%, transparent), transparent 45%),
                    linear-gradient(180deg,#fff,#f9fafb);
        box-shadow:0 18px 48px rgba(2,6,23,.10); overflow:hidden
      }
      .<?php echo $uid; ?> .title{font:900 12px/1 Inter;color:#475569;letter-spacing:.08em;text-transform:uppercase}
      .<?php echo $uid; ?> .value{margin-top:6px;font:900 clamp(28px,7vw,46px)/1.06 Inter;color:#0b1220}
      .<?php echo $uid; ?> .value .cur{font:900 clamp(14px,2.6vw,26px)/1.05 Inter;margin-left:.35em;color:#0b1220}
      .<?php echo $uid; ?> .rng{margin-top:6px;font:600 12px/1.2 Inter;color:#64748b}
      .<?php echo $uid; ?> .badge{display:none}
      .<?php echo $uid; ?> .wrap.bump::after{
        content:""; position:absolute; inset:0; pointer-events:none; border-radius:20px;
        background: linear-gradient(120deg, transparent, color-mix(in srgb, <?php echo $accent; ?> 28%, transparent), transparent);
        animation: <?php echo $uid; ?>_sweep 850ms ease;
      }
      @keyframes <?php echo $uid; ?>_sweep { 0%{transform:translateX(-140%);opacity:0} 40%{opacity:1} 100%{transform:translateX(140%);opacity:0} }
    <?php endif; ?>
  </style>

  <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
    <div class="wrap" data-amt="<?php echo esc_attr(number_format($amount, 2, '.', '')); ?>">
      <?php if ($show_badge && $currency==='HUF'): ?>
        <div class="badge"><?php echo esc_html(trim(($a['badge_prefix']?:''). ' '. number_format($rate,2,'.',' ').' Ft/€ - '.date('Y.m.d'))); ?></div>
      <?php endif; ?>
      <div class="title"><?php echo esc_html($a['title']); ?></div>
      <div class="value">
        <span class="num"><?php echo number_format(($currency==='HUF')? round($amount) : $amount, ($currency==='HUF')?0:2, $currency==='HUF'?'.':',',' '); ?></span>
        <span class="cur"><?php echo ($currency==='HUF') ? 'Ft' : '€'; ?></span>
      </div>
      <div class="rng"><?php echo esc_html($a['from'].' óta'.($to!==_ims_today() ? ' → '.$to : '')); ?></div>
    </div>
  </div>

  <script>
  (function(){
    const root=document.currentScript.previousElementSibling;if(!root)return;
    const box=root.querySelector('.wrap');const num=root.querySelector('.num');
    const cur=root.querySelector('.cur');const refresh=parseInt(root.getAttribute('data-refresh')||'0',10);
    function parseNum(s){return parseFloat(String(s).replace(/[^\d.]/g,''))||0;}
    function fmt(v){
      const isHUF=cur.textContent.trim()==='Ft';
      if(isHUF){return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');}
      const n=(Math.round(v*100)/100).toFixed(2).replace('.',',');return n;
    }
    let last=parseNum(box.getAttribute('data-amt')||num.textContent);
    function animateTo(newV){if(newV<=last){num.textContent=fmt(newV);last=newV;return;}
      const dur=900,t0=performance.now();box.classList.add('bump');
      function step(t){const k=Math.min(1,(t-t0)/dur);const val=last+(newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
        num.textContent=fmt(val);if(k<1)requestAnimationFrame(step);else{last=newV;setTimeout(()=>box.classList.remove('bump'),120);}}
      requestAnimationFrame(step);}
    if(refresh>0){setInterval(()=>{const curV=parseNum(box.getAttribute('data-amt')||num.textContent);if(curV>last)animateTo(curV);},refresh*1000);}
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ===== [impact_sum_sticky] – fixen alul ragadó mini számláló ===== */
add_shortcode('impact_sum_sticky', function($atts){
  $a = shortcode_atts([
    'from'=>_ims_from_default(),'to'=>'','status'=>'all',
    'currency'=>'HUF','rate_huf'=>'392',
    'exclude_unknown'=>'1','unknown_scope'=>'ngo',
    'label'=>'Összegyűjtve','accent'=>'#7c3aed',
    'cta_text'=>'','cta_href'=>'',
    'show_on'=>'all',              // all|mobile|desktop
    'refresh'=>'60',               // mp (vizuális)
    'dismiss_days'=>'3'            // hány napra rejtsük el bezárás után
  ], $atts, 'impact_sum_sticky');

  $to      = (trim($a['to'])!=='') ? $a['to'] : _ims_today();
  $rate    = (float)$a['rate_huf'];
  $currency= strtoupper(trim($a['currency']));
  $excl    = ($a['exclude_unknown']==='1');
  $scope   = in_array($a['unknown_scope'],['ngo','shop','both'],true)?$a['unknown_scope']:'ngo';
  $refresh = max(0,(int)$a['refresh']);
  $accent  = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i',$a['accent'])?$a['accent']:'#7c3aed';
  $showOn  = in_array($a['show_on'],['all','mobile','desktop'],true)?$a['show_on']:'all';
  $dismissDays = max(1,(int)$a['dismiss_days']);

  $data = _ims_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'shop_ngo']);
  if(isset($data['_error'])) return ''; // ha gond van, ne zavarjuk a UI-t

  $commission = _ims_commission_with_unknown($data,$excl,$scope);
  $don_eur = $commission * 0.5;
  $amount  = ($currency==='HUF') ? ($don_eur * $rate) : $don_eur;

  $uid = 'imsticky_'.substr(md5(json_encode([$a,microtime(true)])),0,8);

  ob_start(); ?>
  <style>
    .<?php echo $uid; ?>-bar{position:fixed;z-index:9999;left:12px;right:12px;
      bottom: max(12px, env(safe-area-inset-bottom)); display:flex; justify-content:center; pointer-events:none}
    .<?php echo $uid; ?>{
      --accent: <?php echo esc_html($accent); ?>;
      --glass: rgba(16,18,27,.55); --glass2: rgba(255,255,255,.08);
      --border: rgba(255,255,255,.20); --shadow: rgba(2,6,23,.55);
      font-family: Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      color:#fff; pointer-events:auto;
      min-width:min(94vw,1100px); max-width:1100px; width:100%;
      display:flex; gap:12px; align-items:center;
      padding:12px 16px; border-radius:16px;
      background:
        radial-gradient(900px 520px at 8% -10%, color-mix(in srgb, var(--accent) 24%, transparent), transparent 50%),
        linear-gradient(180deg, var(--glass), rgba(16,18,27,.40));
      backdrop-filter: blur(12px);
      border: 1px solid var(--border);
      box-shadow: 0 18px 48px var(--shadow), inset 0 0 0 1px var(--glass2);
    }
    .<?php echo $uid; ?> .label{font:800 11px/1 Inter;letter-spacing:.08em;text-transform:uppercase;opacity:.9}
    .<?php echo $uid; ?> .num{font:900 clamp(22px,6vw,36px)/1.05 Inter;
      text-shadow:0 2px 16px color-mix(in srgb, var(--accent) 35%, transparent)}
    .<?php echo $uid; ?> .rng{font:700 12px/1.2 Inter;opacity:.8; white-space:nowrap}
    .<?php echo $uid; ?> .sp{flex:1}
    .<?php echo $uid; ?> .cta{
      display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
      background: color-mix(in srgb, var(--accent) 22%, #ffffff1a);
      border:1px solid color-mix(in srgb, var(--accent) 45%, #ffffff20);
      color:#fff;text-decoration:none;font:800 12px/1 Inter
    }
    .<?php echo $uid; ?> .cta:hover{filter:brightness(1.07)}
    .<?php echo $uid; ?> .close{
      width:34px;height:34px;border-radius:10px;border:1px solid #ffffff2e;background:#ffffff1a;color:#fff;display:grid;place-items:center;
      font-weight:900; cursor:pointer
    }
    /* sweep anim növekedéskor */
    .<?php echo $uid; ?>.bump::after{
      content:""; position:absolute; inset:0; border-radius:16px; pointer-events:none;
      background: linear-gradient(120deg, transparent, color-mix(in srgb, var(--accent) 40%, transparent), transparent);
      animation: <?php echo $uid; ?>_sweep 900ms ease;
    }
    @keyframes <?php echo $uid; ?>_sweep {
      0% { transform: translateX(-140%); opacity:0 }
      40%{ opacity:1 } 100%{ transform: translateX(140%); opacity:0 }
    }
    /* megjelenítési szabályok */
    <?php if($showOn==='mobile'): ?>
      @media (min-width: 768px){ .<?php echo $uid; ?>-bar{display:none} }
    <?php elseif($showOn==='desktop'): ?>
      @media (max-width: 767.98px){ .<?php echo $uid; ?>-bar{display:none} }
    <?php endif; ?>
    @media (prefers-reduced-motion: reduce){
      .<?php echo $uid; ?>.bump::after{animation:none}
    }
  </style>
  <div class="<?php echo $uid; ?>-bar" aria-live="polite">
    <div class="<?php echo $uid; ?>" role="region" aria-label="Összegyűjtött adomány">
      <div class="label"><?php echo esc_html($a['label']); ?></div>
      <div class="num" data-amt="<?php echo esc_attr(number_format($amount,2,'.','')); ?>">
        <?php echo esc_html(_ims_fmt_money($amount,$currency)); ?>
      </div>
      <div class="rng">· <?php echo esc_html($a['from'].' → '.$to); ?></div>
      <div class="sp"></div>
      <?php if($a['cta_text'] && $a['cta_href']): ?>
        <a class="cta" href="<?php echo esc_url($a['cta_href']); ?>" rel="noopener">
          <?php echo esc_html($a['cta_text']); ?>
        </a>
      <?php endif; ?>
      <button class="close" type="button" aria-label="Sáv bezárása">×</button>
    </div>
  </div>
  <script>
  (function(){
    const bar=document.querySelector('.<?php echo $uid; ?>-bar');
    const box=bar.querySelector('.<?php echo $uid; ?>');
    const num=bar.querySelector('.num');
    const btn=bar.querySelector('.close');
    const refresh=<?php echo (int)$refresh; ?>;
    const key='impactStickyDismissed';
    const ttlDays=<?php echo (int)$dismissDays; ?>;

    try{
      const raw=localStorage.getItem(key);
      if(raw){
        const until=Number(raw); if(!isNaN(until) && Date.now()<until){ bar.style.display='none'; return; }
      }
    }catch(e){}

    function parseNum(s){return parseFloat(String(s).replace(/[^\d.]/g,''))||0;}
    function fmt(v){
      const isHUF=bar.textContent.indexOf('€')===-1;
      if(isHUF){return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ')+' Ft';}
      const n=(Math.round(v*100)/100).toFixed(2).replace('.',','); return '€ '+n;
    }
    let last=parseNum(num.getAttribute('data-amt')||num.textContent);
    function animateTo(newV){
      if(newV<=last){ num.textContent=fmt(newV); last=newV; return; }
      const dur=900,t0=performance.now(); box.classList.add('bump');
      function step(t){ const k=Math.min(1,(t-t0)/dur);
        const val=last+(newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
        num.textContent=fmt(val);
        if(k<1) requestAnimationFrame(step); else { last=newV; setTimeout(()=>box.classList.remove('bump'),120); }
      }
      requestAnimationFrame(step);
    }
    if(refresh>0){
      setInterval(()=>{ const cur=parseNum(num.getAttribute('data-amt')||num.textContent);
        if(cur>last) animateTo(cur);
      }, refresh*1000);
    }
    btn.addEventListener('click', ()=>{
      bar.style.display='none';
      try{
        const until = Date.now() + (ttlDays*24*60*60*1000);
        localStorage.setItem(key, String(until));
      }catch(e){}
    }, {passive:true});
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ===== [impact_sum_diag] ===== */
add_shortcode('impact_sum_diag', function($atts){
  $a=shortcode_atts(['from'=>_ims_from_default(),'to'=>'','status'=>'all','rate_huf'=>'392','exclude_unknown'=>'1','unknown_scope'=>'ngo'],$atts,'impact_sum_diag');
  $to=(trim($a['to'])!=='')?$a['to']:_ims_today();$rate=(float)$a['rate_huf'];
  $excl=($a['exclude_unknown']==='1');$scope=in_array($a['unknown_scope'],['ngo','shop','both'],true)?$a['unknown_scope']:'ngo';
  $data=_ims_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'shop_ngo']);
  if(isset($data['_error']))return '<div style="padding:10px;border:1px solid #fca5a5;border-radius:10px;background:#fff1f2">Diag hiba: '.esc_html($data['_error']).'</div>';
  $comm_all=(float)($data['meta']['grand']['commission']??0);$comm_fx=_ims_commission_with_unknown($data,$excl,$scope);
  $don_all_eur=$comm_all*0.5;$don_fx_eur=$comm_fx*0.5;
  ob_start();?>
  <div style="font:600 13px/1.35 system-ui;border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff">
    <div><b>Diag (<?php echo esc_html($a['from'].' → '.$to); ?> · status=<?php echo esc_html($a['status']); ?>)</b></div>
    <div>grand.commission (minden): <?php echo number_format($comm_all,2,',',' '); ?> €</div>
    <div>commission (no-unknown: <?php echo esc_html($scope); ?>): <?php echo number_format($comm_fx,2,',',' '); ?> €</div>
    <div>donation all: <?php echo number_format($don_all_eur,2,',',' '); ?> € · HUF: <?php echo number_format($don_all_eur*$rate,0,'.',' '); ?> Ft</div>
    <div>donation no-unknown: <?php echo number_format($don_fx_eur,2,',',' '); ?> € · HUF: <?php echo number_format($don_fx_eur*$rate,0,'.',' '); ?> Ft</div>
  </div>
  <?php return ob_get_clean();
});

## 9. Adományszámláló HUF UX/UI B verzió
**Státusz**: ✅  
**Fájl**: fájlnév  
**Leírás**: leírás

```php
<?php
/**
 * Sharity – Summa adomány számláló (UI + mini) + diagnosztika
 * Shortcode-ok:
 *  - [impact_sum_counter from="" to="" status="all" currency="HUF" rate_huf="392"
 *                        exclude_unknown="1" unknown_scope="ngo"
 *                        title="Összegyűjtve" refresh="60" accent="#10b981"]
 *  - [impact_sum_mini from="" to="" status="all" currency="HUF" rate_huf="392"
 *                     exclude_unknown="1" unknown_scope="ngo" refresh="60"]
 *  - [impact_sum_diag from="" to="" status="all" rate_huf="392"
 *                     exclude_unknown="1" unknown_scope="ngo"]
 */

if (!defined('ABSPATH')) exit;

/* ===== segédek ===== */
function _ims_today(){ return date('Y-m-d'); }
function _ims_from_default(){ return date('Y-m-01'); }

function _ims_is_unknown($s){
  $n = strtolower(trim((string)$s));
  return ($n==='' || strpos($n,'ismeretlen')!==false || strpos($n,'unknown')!==false);
}

function _ims_fetch_totals($args){
  $def = ['from'=>_ims_from_default(),'to'=>_ims_today(),'status'=>'all','group'=>'shop_ngo'];
  $q = array_merge($def, $args);
  $url = add_query_arg($q, home_url('/wp-json/impactshop/v1/totals'));
  $key = 'ims_tot_'.md5($url);
  if (($c=get_transient($key))!==false) return $c;
  $r = wp_remote_get($url, ['timeout'=>12,'headers'=>['Accept'=>'application/json']]);
  if (is_wp_error($r)) return ['_error'=>$r->get_error_message()];
  $code = wp_remote_retrieve_response_code($r);
  if ($code<200 || $code>=300) return ['_error'=>'HTTP '.$code];
  $data = json_decode(wp_remote_retrieve_body($r), true);
  if (!is_array($data)) return ['_error'=>'JSON parse'];
  set_transient($key, $data, 120);
  return $data;
}

function _ims_commission_with_unknown($data, $exclude, $scope){
  $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
  if (!$exclude) return (float)($data['meta']['grand']['commission'] ?? 0);
  $sum=0.0;
  foreach ($rows as $r){
    $ngo  = $r['ngo']  ?? $r['ngo_name']  ?? '';
    $shop = $r['shop'] ?? $r['shop_name'] ?? $r['shop_slug'] ?? '';
    $drop = ($scope === 'ngo')
            ? _ims_is_unknown($ngo)
            : ( ($scope === 'shop')
                ? _ims_is_unknown($shop)
                : (_ims_is_unknown($ngo) || _ims_is_unknown($shop))
              );
    if ($drop) continue;
    $sum += (float)($r['commission'] ?? 0);
  }
  if ($sum===0.0 && !$rows) $sum=(float)($data['meta']['grand']['commission'] ?? 0);
  return $sum;
}

function _ims_fmt_money($v, $currency='HUF'){
  if (strtoupper($currency)==='HUF') return number_format((float)$v, 0, '.', ' ').' Ft';
  return '€ '.number_format((float)$v, 2, ',', ' ');
}

/* ===== [impact_sum_mini] – HERO / kontrasztos mini számláló ===== */
add_shortcode('impact_sum_mini', function($atts){
  $a = shortcode_atts([
    'from'=>_ims_from_default(),'to'=>'','status'=>'all',
    'currency'=>'HUF','rate_huf'=>'392',
    'exclude_unknown'=>'1','unknown_scope'=>'ngo',
    'refresh'=>'60','accent'=>'#7c3aed','label'=>''
  ], $atts, 'impact_sum_mini');

  $to=(trim($a['to'])!=='')?$a['to']:_ims_today();
  $rate=(float)$a['rate_huf'];
  $currency=strtoupper(trim($a['currency']));
  $excl=($a['exclude_unknown']==='1');
  $scope=in_array($a['unknown_scope'],['ngo','shop','both'],true)?$a['unknown_scope']:'ngo';
  $refresh=max(0,(int)$a['refresh']);
  $accent=preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i',$a['accent'])?$a['accent']:'#7c3aed';

  $data=_ims_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'shop_ngo']);
  if(isset($data['_error'])) return '<div class="impact-box impact-error">Mini hiba: '.esc_html($data['_error']).'</div>';

  $commission=_ims_commission_with_unknown($data,$excl,$scope);
  $don_eur=$commission*0.5;
  $amount=($currency==='HUF')?($don_eur*$rate):$don_eur;

  $uid='immini_'.substr(md5(json_encode([$a,microtime(true)])),0,8);

  ob_start(); ?>
  <style>
    .<?php echo $uid; ?>{
      --accent: <?php echo esc_html($accent); ?>;
      --glass: rgba(255,255,255,.18);
      --border: rgba(255,255,255,.25);
      --shadow: rgba(2,6,23,.35);
      font-family: Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      text-align:center;
    }
    .<?php echo $uid; ?> .wrap{
      display:inline-block; min-width: min(90vw, 1120px);
      padding: 18px 28px; border-radius: 22px;
      background:
        radial-gradient(1100px 600px at 10% -20%, color-mix(in srgb, var(--accent) 35%, transparent), transparent 50%),
        linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
      backdrop-filter: blur(14px);
      border: 1px solid var(--border);
      box-shadow: 0 28px 60px var(--shadow), inset 0 0 0 1px rgba(255,255,255,.06);
    }
    .<?php echo $uid; ?> .label{
      display:block; margin-bottom:2px; letter-spacing:.08em; text-transform:uppercase;
      font-weight:800; font-size:12px; color:#e5e7eb; text-shadow:0 1px 0 rgba(0,0,0,.25);
    }
    .<?php echo $uid; ?> .num{
      font-weight: 900;
      font-size: clamp(40px, 12vw, 88px);
      line-height: 1.02;
      color:#ffffff;
      text-shadow:
        0 2px 18px color-mix(in srgb, var(--accent) 32%, transparent),
        0 6px 30px rgba(0,0,0,.35);
    }
    .<?php echo $uid; ?> .rng{
      margin-top:6px; font-weight:700; font-size:13px; color:#e2e8f0;
      text-shadow: 0 1px 0 rgba(0,0,0,.3);
      opacity:.95;
    }
    /* finom sweep villanás növekedéskor */
    .<?php echo $uid; ?> .wrap.bump::after{
      content:""; position:absolute; inset:0; pointer-events:none;
      background: linear-gradient(120deg, transparent,
                 color-mix(in srgb, var(--accent) 40%, transparent), transparent);
      animation: <?php echo $uid; ?>_sweep 850ms ease;
      border-radius:22px;
    }
    @keyframes <?php echo $uid; ?>_sweep {
      0% { transform: translateX(-140%); opacity:0 }
      40%{ opacity:1 }
      100%{ transform: translateX(140%); opacity:0 }
    }
  </style>
  <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
    <div class="wrap" style="position:relative">
      <?php if ($a['label']!==''): ?>
        <span class="label"><?php echo esc_html($a['label']); ?></span>
      <?php endif; ?>
      <div class="num" data-amt="<?php echo esc_attr(number_format($amount, 2, '.', '')); ?>">
        <?php echo esc_html(_ims_fmt_money($amount,$currency)); ?>
      </div>
      <div class="rng"><?php echo esc_html($a['from'].' → '.$to); ?></div>
    </div>
  </div>
  <script>
  (function(){
    const root=document.currentScript.previousElementSibling;if(!root)return;
    const box=root.querySelector('.wrap'); const num=root.querySelector('.num');
    const refresh=parseInt(root.getAttribute('data-refresh')||'0',10);
    function parseNum(s){return parseFloat(String(s).replace(/[^\d.]/g,''))||0;}
    function fmt(v){
      const isHUF=root.textContent.indexOf('€')===-1;
      if(isHUF){return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ')+' Ft';}
      const n=(Math.round(v*100)/100).toFixed(2).replace('.',','); return '€ '+n;
    }
    let last=parseNum(num.getAttribute('data-amt')||num.textContent);
    function animateTo(newV){
      if(newV<=last){ num.textContent=fmt(newV); last=newV; return; }
      const dur=1000, t0=performance.now(); box.classList.add('bump');
      function step(t){
        const k=Math.min(1,(t-t0)/dur);
        const val=last+(newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
        num.textContent=fmt(val);
        if(k<1) requestAnimationFrame(step); else { last=newV; setTimeout(()=>box.classList.remove('bump'),120); }
      }
      requestAnimationFrame(step);
    }
    if(refresh>0){
      setInterval(()=>{
        const cur=parseNum(num.getAttribute('data-amt')||num.textContent);
        if(cur>last) animateTo(cur);
      }, refresh*1000);
    }
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ===== [impact_sum_counter] – nagy verzió (skin: default|glass) ===== */
add_shortcode('impact_sum_counter', function($atts){
  $a = shortcode_atts([
    'from'           => _ims_from_default(),
    'to'             => '',
    'status'         => 'all',
    'currency'       => 'HUF',
    'rate_huf'       => '392',
    'title'          => 'Összegyűjtve',
    'exclude_unknown'=> '1',
    'unknown_scope'  => 'ngo',
    'refresh'        => '60',
    'accent'         => '#7c3aed',
    'skin'           => 'glass',   // ÚJ: 'glass' = a kért klasszikus design
    'badge'          => '1',       // ÚJ: jobb felső árfolyam badge megjelenítése
    'badge_prefix'   => 'Árfolyam',     // ÚJ: badge elé írt kis jelzés (üresre állítható)
  ], $atts, 'impact_sum_counter');

  $to       = (trim($a['to'])!=='') ? $a['to'] : _ims_today();
  $rate     = (float)$a['rate_huf'];
  $currency = strtoupper(trim($a['currency']));
  $excl     = ($a['exclude_unknown']==='1');
  $scope    = in_array($a['unknown_scope'], ['ngo','shop','both'], true) ? $a['unknown_scope'] : 'ngo';
  $refresh  = max(0, (int)$a['refresh']);
  $accent   = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#7c3aed';
  $skin     = ($a['skin']==='glass') ? 'glass' : 'default';
  $show_badge = ($a['badge']==='1');

  // adat
  $data = _ims_fetch_totals(['from'=>$a['from'], 'to'=>$to, 'status'=>$a['status'], 'group'=>'shop_ngo']);
  if (isset($data['_error'])) return '<div class="impact-box impact-error">Összeg hiba: '.esc_html($data['_error']).'</div>';
  $commission = _ims_commission_with_unknown($data, $excl, $scope);
  $don_eur = $commission * 0.5;
  $amount  = ($currency==='HUF') ? ($don_eur * $rate) : $don_eur;

  $uid = 'imsum_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

  // ===== UI =====
  ob_start(); ?>
  <style>
    .<?php echo $uid; ?>{font:600 14px/1.35 Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}

    <?php if ($skin==='glass'): /* ————— a kért klasszikus, nagy kártya ————— */ ?>
      .<?php echo $uid; ?> .wrap{
        position:relative; padding:34px 40px; border-radius:36px;
        background:
          radial-gradient(1200px 680px at 8% -20%, color-mix(in srgb, <?php echo $accent; ?> 28%, transparent), transparent 55%),
          linear-gradient(135deg, #5b46a5 0%, #5d75b3 40%, #4aa08f 100%);
        color:#fff;
        box-shadow:
          0 28px 60px rgba(2,6,23,.25),
          inset 0 1px 0 rgba(255,255,255,.25);
      }
      .<?php echo $uid; ?> .title{
        font:800 13px/1 Inter; letter-spacing:.10em; text-transform:uppercase; opacity:.9; margin-bottom:8px; text-shadow:0 1px 0 rgba(0,0,0,.25)
      }
      .<?php echo $uid; ?> .value{
        font:900 clamp(44px,7.8vw,92px)/1.02 Inter; text-shadow:0 6px 30px rgba(0,0,0,.35)
      }
      .<?php echo $uid; ?> .value .cur{font:900 clamp(18px,3vw,32px)/1.05 Inter; margin-left:.35em; opacity:.9}
      .<?php echo $uid; ?> .rng{
        margin-top:18px; font:700 14px/1.2 Inter; opacity:.9; text-shadow:0 1px 0 rgba(0,0,0,.25)
      }
      /* jobb felső badge */
      .<?php echo $uid; ?> .badge{
        position:absolute; top:14px; right:14px;
        background:linear-gradient(180deg, rgba(255,255,255,.65), rgba(255,255,255,.45));
        color:#0b1220; border-radius:999px; padding:8px 14px; font:800 12px/1 Inter;
        border:1px solid rgba(255,255,255,.6); box-shadow:0 6px 20px rgba(0,0,0,.18)
      }
      /* finom fénycsík növekedéskor */
      .<?php echo $uid; ?> .wrap.bump::after{
        content:""; position:absolute; inset:0; pointer-events:none; border-radius:36px;
        background: linear-gradient(120deg, transparent, rgba(255,255,255,.30), transparent);
        animation: <?php echo $uid; ?>_sweep 850ms ease;
      }
      @keyframes <?php echo $uid; ?>_sweep { 0%{transform:translateX(-140%);opacity:0} 40%{opacity:1} 100%{transform:translateX(140%);opacity:0} }

    <?php else: /* ————— a jelenlegi “default” bőr ————— */ ?>
      .<?php echo $uid; ?>{color:#0b1220}
      .<?php echo $uid; ?> .wrap{
        position:relative; padding:18px 20px; border:1px solid #e5e7eb; border-radius:20px;
        background: radial-gradient(900px 520px at 6% -10%, color-mix(in srgb, <?php echo $accent; ?> 16%, transparent), transparent 45%),
                    linear-gradient(180deg,#fff,#f9fafb);
        box-shadow:0 18px 48px rgba(2,6,23,.10); overflow:hidden
      }
      .<?php echo $uid; ?> .title{font:900 12px/1 Inter;color:#475569;letter-spacing:.08em;text-transform:uppercase}
      .<?php echo $uid; ?> .value{margin-top:6px;font:900 clamp(28px,7vw,46px)/1.06 Inter;color:#0b1220}
      .<?php echo $uid; ?> .value .cur{font:900 clamp(14px,2.6vw,26px)/1.05 Inter;margin-left:.35em;color:#0b1220}
      .<?php echo $uid; ?> .rng{margin-top:6px;font:600 12px/1.2 Inter;color:#64748b}
      .<?php echo $uid; ?> .badge{display:none}
      .<?php echo $uid; ?> .wrap.bump::after{
        content:""; position:absolute; inset:0; pointer-events:none; border-radius:20px;
        background: linear-gradient(120deg, transparent, color-mix(in srgb, <?php echo $accent; ?> 28%, transparent), transparent);
        animation: <?php echo $uid; ?>_sweep 850ms ease;
      }
      @keyframes <?php echo $uid; ?>_sweep { 0%{transform:translateX(-140%);opacity:0} 40%{opacity:1} 100%{transform:translateX(140%);opacity:0} }
    <?php endif; ?>
  </style>

  <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
    <div class="wrap" data-amt="<?php echo esc_attr(number_format($amount, 2, '.', '')); ?>">
      <?php if ($show_badge && $currency==='HUF'): ?>
        <div class="badge"><?php echo esc_html(trim(($a['badge_prefix']?:''). ' '. number_format($rate,2,'.',' ').' Ft/€ - '.date('Y.m.d'))); ?></div>
      <?php endif; ?>
      <div class="title"><?php echo esc_html($a['title']); ?></div>
      <div class="value">
        <span class="num"><?php echo number_format(($currency==='HUF')? round($amount) : $amount, ($currency==='HUF')?0:2, $currency==='HUF'?'.':',',' '); ?></span>
        <span class="cur"><?php echo ($currency==='HUF') ? 'Ft' : '€'; ?></span>
      </div>
      <div class="rng"><?php echo esc_html($a['from'].' óta'.($to!==_ims_today() ? ' → '.$to : '')); ?></div>
    </div>
  </div>

  <script>
  (function(){
    const root=document.currentScript.previousElementSibling;if(!root)return;
    const box=root.querySelector('.wrap');const num=root.querySelector('.num');
    const cur=root.querySelector('.cur');const refresh=parseInt(root.getAttribute('data-refresh')||'0',10);
    function parseNum(s){return parseFloat(String(s).replace(/[^\d.]/g,''))||0;}
    function fmt(v){
      const isHUF=cur.textContent.trim()==='Ft';
      if(isHUF){return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');}
      const n=(Math.round(v*100)/100).toFixed(2).replace('.',',');return n;
    }
    let last=parseNum(box.getAttribute('data-amt')||num.textContent);
    function animateTo(newV){if(newV<=last){num.textContent=fmt(newV);last=newV;return;}
      const dur=900,t0=performance.now();box.classList.add('bump');
      function step(t){const k=Math.min(1,(t-t0)/dur);const val=last+(newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
        num.textContent=fmt(val);if(k<1)requestAnimationFrame(step);else{last=newV;setTimeout(()=>box.classList.remove('bump'),120);}}
      requestAnimationFrame(step);}
    if(refresh>0){setInterval(()=>{const curV=parseNum(box.getAttribute('data-amt')||num.textContent);if(curV>last)animateTo(curV);},refresh*1000);}
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ===== [impact_sum_sticky] – fixen alul ragadó mini számláló ===== */
add_shortcode('impact_sum_sticky', function($atts){
  $a = shortcode_atts([
    'from'=>_ims_from_default(),'to'=>'','status'=>'all',
    'currency'=>'HUF','rate_huf'=>'392',
    'exclude_unknown'=>'1','unknown_scope'=>'ngo',
    'label'=>'Összegyűjtve','accent'=>'#7c3aed',
    'cta_text'=>'','cta_href'=>'',
    'show_on'=>'all',              // all|mobile|desktop
    'refresh'=>'60',               // mp (vizuális)
    'dismiss_days'=>'3'            // hány napra rejtsük el bezárás után
  ], $atts, 'impact_sum_sticky');

  $to      = (trim($a['to'])!=='') ? $a['to'] : _ims_today();
  $rate    = (float)$a['rate_huf'];
  $currency= strtoupper(trim($a['currency']));
  $excl    = ($a['exclude_unknown']==='1');
  $scope   = in_array($a['unknown_scope'],['ngo','shop','both'],true)?$a['unknown_scope']:'ngo';
  $refresh = max(0,(int)$a['refresh']);
  $accent  = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i',$a['accent'])?$a['accent']:'#7c3aed';
  $showOn  = in_array($a['show_on'],['all','mobile','desktop'],true)?$a['show_on']:'all';
  $dismissDays = max(1,(int)$a['dismiss_days']);

  $data = _ims_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'shop_ngo']);
  if(isset($data['_error'])) return ''; // ha gond van, ne zavarjuk a UI-t

  $commission = _ims_commission_with_unknown($data,$excl,$scope);
  $don_eur = $commission * 0.5;
  $amount  = ($currency==='HUF') ? ($don_eur * $rate) : $don_eur;

  $uid = 'imsticky_'.substr(md5(json_encode([$a,microtime(true)])),0,8);

  ob_start(); ?>
  <style>
    .<?php echo $uid; ?>-bar{position:fixed;z-index:9999;left:12px;right:12px;
      bottom: max(12px, env(safe-area-inset-bottom)); display:flex; justify-content:center; pointer-events:none}
    .<?php echo $uid; ?>{
      --accent: <?php echo esc_html($accent); ?>;
      --glass: rgba(16,18,27,.55); --glass2: rgba(255,255,255,.08);
      --border: rgba(255,255,255,.20); --shadow: rgba(2,6,23,.55);
      font-family: Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
      color:#fff; pointer-events:auto;
      min-width:min(94vw,1100px); max-width:1100px; width:100%;
      display:flex; gap:12px; align-items:center;
      padding:12px 16px; border-radius:16px;
      background:
        radial-gradient(900px 520px at 8% -10%, color-mix(in srgb, var(--accent) 24%, transparent), transparent 50%),
        linear-gradient(180deg, var(--glass), rgba(16,18,27,.40));
      backdrop-filter: blur(12px);
      border: 1px solid var(--border);
      box-shadow: 0 18px 48px var(--shadow), inset 0 0 0 1px var(--glass2);
    }
    .<?php echo $uid; ?> .label{font:800 11px/1 Inter;letter-spacing:.08em;text-transform:uppercase;opacity:.9}
    .<?php echo $uid; ?> .num{font:900 clamp(22px,6vw,36px)/1.05 Inter;
      text-shadow:0 2px 16px color-mix(in srgb, var(--accent) 35%, transparent)}
    .<?php echo $uid; ?> .rng{font:700 12px/1.2 Inter;opacity:.8; white-space:nowrap}
    .<?php echo $uid; ?> .sp{flex:1}
    .<?php echo $uid; ?> .cta{
      display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
      background: color-mix(in srgb, var(--accent) 22%, #ffffff1a);
      border:1px solid color-mix(in srgb, var(--accent) 45%, #ffffff20);
      color:#fff;text-decoration:none;font:800 12px/1 Inter
    }
    .<?php echo $uid; ?> .cta:hover{filter:brightness(1.07)}
    .<?php echo $uid; ?> .close{
      width:34px;height:34px;border-radius:10px;border:1px solid #ffffff2e;background:#ffffff1a;color:#fff;display:grid;place-items:center;
      font-weight:900; cursor:pointer
    }
    /* sweep anim növekedéskor */
    .<?php echo $uid; ?>.bump::after{
      content:""; position:absolute; inset:0; border-radius:16px; pointer-events:none;
      background: linear-gradient(120deg, transparent, color-mix(in srgb, var(--accent) 40%, transparent), transparent);
      animation: <?php echo $uid; ?>_sweep 900ms ease;
    }
    @keyframes <?php echo $uid; ?>_sweep {
      0% { transform: translateX(-140%); opacity:0 }
      40%{ opacity:1 } 100%{ transform: translateX(140%); opacity:0 }
    }
    /* megjelenítési szabályok */
    <?php if($showOn==='mobile'): ?>
      @media (min-width: 768px){ .<?php echo $uid; ?>-bar{display:none} }
    <?php elseif($showOn==='desktop'): ?>
      @media (max-width: 767.98px){ .<?php echo $uid; ?>-bar{display:none} }
    <?php endif; ?>
    @media (prefers-reduced-motion: reduce){
      .<?php echo $uid; ?>.bump::after{animation:none}
    }
  </style>
  <div class="<?php echo $uid; ?>-bar" aria-live="polite">
    <div class="<?php echo $uid; ?>" role="region" aria-label="Összegyűjtött adomány">
      <div class="label"><?php echo esc_html($a['label']); ?></div>
      <div class="num" data-amt="<?php echo esc_attr(number_format($amount,2,'.','')); ?>">
        <?php echo esc_html(_ims_fmt_money($amount,$currency)); ?>
      </div>
      <div class="rng">· <?php echo esc_html($a['from'].' → '.$to); ?></div>
      <div class="sp"></div>
      <?php if($a['cta_text'] && $a['cta_href']): ?>
        <a class="cta" href="<?php echo esc_url($a['cta_href']); ?>" rel="noopener">
          <?php echo esc_html($a['cta_text']); ?>
        </a>
      <?php endif; ?>
      <button class="close" type="button" aria-label="Sáv bezárása">×</button>
    </div>
  </div>
  <script>
  (function(){
    const bar=document.querySelector('.<?php echo $uid; ?>-bar');
    const box=bar.querySelector('.<?php echo $uid; ?>');
    const num=bar.querySelector('.num');
    const btn=bar.querySelector('.close');
    const refresh=<?php echo (int)$refresh; ?>;
    const key='impactStickyDismissed';
    const ttlDays=<?php echo (int)$dismissDays; ?>;

    try{
      const raw=localStorage.getItem(key);
      if(raw){
        const until=Number(raw); if(!isNaN(until) && Date.now()<until){ bar.style.display='none'; return; }
      }
    }catch(e){}

    function parseNum(s){return parseFloat(String(s).replace(/[^\d.]/g,''))||0;}
    function fmt(v){
      const isHUF=bar.textContent.indexOf('€')===-1;
      if(isHUF){return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ')+' Ft';}
      const n=(Math.round(v*100)/100).toFixed(2).replace('.',','); return '€ '+n;
    }
    let last=parseNum(num.getAttribute('data-amt')||num.textContent);
    function animateTo(newV){
      if(newV<=last){ num.textContent=fmt(newV); last=newV; return; }
      const dur=900,t0=performance.now(); box.classList.add('bump');
      function step(t){ const k=Math.min(1,(t-t0)/dur);
        const val=last+(newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
        num.textContent=fmt(val);
        if(k<1) requestAnimationFrame(step); else { last=newV; setTimeout(()=>box.classList.remove('bump'),120); }
      }
      requestAnimationFrame(step);
    }
    if(refresh>0){
      setInterval(()=>{ const cur=parseNum(num.getAttribute('data-amt')||num.textContent);
        if(cur>last) animateTo(cur);
      }, refresh*1000);
    }
    btn.addEventListener('click', ()=>{
      bar.style.display='none';
      try{
        const until = Date.now() + (ttlDays*24*60*60*1000);
        localStorage.setItem(key, String(until));
      }catch(e){}
    }, {passive:true});
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ===== [impact_sum_diag] ===== */
add_shortcode('impact_sum_diag', function($atts){
  $a=shortcode_atts(['from'=>_ims_from_default(),'to'=>'','status'=>'all','rate_huf'=>'392','exclude_unknown'=>'1','unknown_scope'=>'ngo'],$atts,'impact_sum_diag');
  $to=(trim($a['to'])!=='')?$a['to']:_ims_today();$rate=(float)$a['rate_huf'];
  $excl=($a['exclude_unknown']==='1');$scope=in_array($a['unknown_scope'],['ngo','shop','both'],true)?$a['unknown_scope']:'ngo';
  $data=_ims_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'shop_ngo']);
  if(isset($data['_error']))return '<div style="padding:10px;border:1px solid #fca5a5;border-radius:10px;background:#fff1f2">Diag hiba: '.esc_html($data['_error']).'</div>';
  $comm_all=(float)($data['meta']['grand']['commission']??0);$comm_fx=_ims_commission_with_unknown($data,$excl,$scope);
  $don_all_eur=$comm_all*0.5;$don_fx_eur=$comm_fx*0.5;
  ob_start();?>
  <div style="font:600 13px/1.35 system-ui;border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff">
    <div><b>Diag (<?php echo esc_html($a['from'].' → '.$to); ?> · status=<?php echo esc_html($a['status']); ?>)</b></div>
    <div>grand.commission (minden): <?php echo number_format($comm_all,2,',',' '); ?> €</div>
    <div>commission (no-unknown: <?php echo esc_html($scope); ?>): <?php echo number_format($comm_fx,2,',',' '); ?> €</div>
    <div>donation all: <?php echo number_format($don_all_eur,2,',',' '); ?> € · HUF: <?php echo number_format($don_all_eur*$rate,0,'.',' '); ?> Ft</div>
    <div>donation no-unknown: <?php echo number_format($don_fx_eur,2,',',' '); ?> € · HUF: <?php echo number_format($don_fx_eur*$rate,0,'.',' '); ?> Ft</div>
  </div>
  <?php return ob_get_clean();
});

## 10. SHop TOP leaderboard HUF
**Státusz**: ✅  
**Fájl**: fájlnév  
**Leírás**: leírás

```php
<?php
// == ImpactShop – "adatoto" toplista (forrás: [impactshop_rows_no_unknown])
// Logika: EUR-ban összegezünk (jutalék 50%), majd EGYSZER szorozzuk HUF-fal és kerekítünk.
// Rövidkód:
//   [adatoto from="YYYY-MM-DD" to="YYYY-MM-DD" status="all"
//            max_height="520" show_medals="1" compact="0"
//            title="Webshop toplista (Adomány)" rate_huf="392.5"]
//
// Megjegyzés: a forrás rövidkód HTML tábláját parsoljuk; a "Jutalék" oszlopból dolgozunk.

if (!function_exists('impactshop_adatoto_shortcode')) {

  // opcionális: egységes alapárfolyam a rendszer többi részével
  if (!function_exists('_ims_rate_huf_default')) {
    function _ims_rate_huf_default(){
      return defined('IMPACT_SUM_RATE_HUF') ? (float)IMPACT_SUM_RATE_HUF : 392.5;
    }
  }

  function impactshop_adatoto_shortcode($atts){
    $a = shortcode_atts([
      'from'        => '',
      'to'          => '',
      'status'      => 'approved',
      'max_height'  => '520',
      'show_medals' => '1',
      'compact'     => '0',
      'title'       => 'Webshop toplista (Adomány)',
      'rate_huf'    => strval(_ims_rate_huf_default()),
    ], $atts, 'adatoto');

    $rate = max(1.0, (float)$a['rate_huf']); // Ft / €

    // 1) Forrás-shortcode lefuttatása (EUR-os táblázat)
    $src = '[impactshop_rows_no_unknown'
         . ($a['from']   ? ' from="'.esc_attr($a['from']).'"'   : '')
         . ($a['to']     ? ' to="'.esc_attr($a['to']).'"'       : '')
         . ($a['status'] ? ' status="'.esc_attr($a['status']).'"' : '')
         . ']';
    $html = do_shortcode($src);
    if (!$html) return '<div>Nincs adat a toplistához.</div>';

    // 2) Oszlopindexek megtalálása
    $shopKeys = ['webshop','shop','bolt','partner'];
    $amtKeys  = ['jutalék','commission','összeg','amount','revenue','payout'];

    $rows = [];
    $shopIdx = null; $amtIdx = null;

    if (preg_match_all('~<tr\b[^>]*>([\s\S]*?)</tr>~i', $html, $m_tr)) {
      foreach ($m_tr[1] as $i => $rowHtml) {
        // header
        if ($i === 0 && preg_match_all('~<th\b[^>]*>([\s\S]*?)</th>~i', $rowHtml, $m_th) && count($m_th[1]) >= 2) {
          $headers = array_map(fn($x)=>mb_strtolower(trim(wp_strip_all_tags($x))), $m_th[1]);
          foreach ($headers as $idx=>$label) {
            foreach ($shopKeys as $k) if (mb_stripos($label,$k)!==false) { $shopIdx=$idx; break; }
            foreach ($amtKeys  as $k) if (mb_stripos($label,$k)!==false) { $amtIdx = $amtIdx ?? $idx; }
          }
          continue;
        }
        // data row
        if (preg_match_all('~<td\b[^>]*>([\s\S]*?)</td>~i', $rowHtml, $m_td) && count($m_td[1]) >= 2) {
          $cells = $m_td[1];
          $si = ($shopIdx === null) ? min(1, count($cells)-1) : $shopIdx;
          $ai = ($amtIdx  === null) ? (count($cells)-1)        : $amtIdx;

          $shopCell = $cells[$si] ?? '';
          $amtCell  = $cells[$ai] ?? '';

          $href = '';
          if ($shopCell && preg_match('~<a\b[^>]*href=["\']([^"\']+)["\']~i', $shopCell, $m_href)) {
            $href = html_entity_decode($m_href[1], ENT_QUOTES);
          }
          $shopName = trim(preg_replace('~\s+~',' ', wp_strip_all_tags($shopCell)));
          if ($shopName === '') continue;

          // 3) EUR szöveg → szám
          $amtTxt = trim(preg_replace('~\s+~',' ', wp_strip_all_tags($amtCell)));
          $norm = str_replace(['€',' '],'', str_replace(',','.', $amtTxt));
          $eur  = is_numeric($norm) ? (float)$norm : 0.0;

          // 4) ADOMÁNY EUR-ban (50%), HUF-ot majd a végén
          $don_eur = $eur * 0.5;

          $rows[] = [
            'shop'    => $shopName,
            'href'    => $href,
            'val_eur' => $don_eur, // EUR
          ];
        }
      }
    }
    if (!$rows) return '<div>Nincs adat a toplistához.</div>';

    // 5) Összegzés EUR-ban → HUF konverzió a végén
    $byShop = [];
    foreach ($rows as $r) {
      $k = $r['shop'];
      if (!isset($byShop[$k])) $byShop[$k] = ['shop'=>$k, 'eur'=>0.0, 'href'=>$r['href']];
      $byShop[$k]['eur'] += $r['val_eur'];
      if (!$byShop[$k]['href'] && $r['href']) $byShop[$k]['href'] = $r['href'];
    }

    $list = array_values($byShop);
    usort($list, fn($a,$b)=> $b['eur'] <=> $a['eur']);

    $uid = 'adatoto-'.substr(md5($src.microtime(true)),0,8);
    $mh  = max(280, intval($a['max_height']));
    $show_medals = ($a['show_medals'] === '1');
    $compact = ($a['compact'] === '1');
    $title = trim($a['title']);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?> { --mh: <?php echo $mh; ?>px; --pad: 14px; --gap: 10px; --rad: 14px; }
      <?php if($compact): ?> .<?php echo $uid; ?> { --pad: 12px; --gap: 8px; --rad: 12px; } <?php endif; ?>

      .<?php echo $uid; ?> .at-wrap {
        border-radius: var(--rad);
        background: rgba(255,255,255,.7);
        border: 1px solid rgba(0,0,0,.08);
        box-shadow: 0 8px 24px rgba(0,0,0,.06);
        backdrop-filter: saturate(180%) blur(10px);
        overflow: hidden;
      }
      .<?php echo $uid; ?> .at-head {
        display:flex; align-items:center; justify-content:center;
        padding: 12px var(--pad);
        border-bottom: 1px solid rgba(0,0,0,.06);
        font: 700 <?php echo $compact?'14px':'16px'; ?>/1.2 system-ui; color:#111;
      }
      .<?php echo $uid; ?> .at-list {
        max-height: var(--mh);
        overflow-y: auto;
        padding: 6px 0;
        scrollbar-width: thin;
      }
      .<?php echo $uid; ?> .at-row {
        display:grid;
        grid-template-columns: 36px 1fr auto;
        align-items:center;
        gap: var(--gap);
        padding: 10px var(--pad);
        transition: background .12s ease;
      }
      .<?php echo $uid; ?> .at-row:hover { background: rgba(0,0,0,.04); }

      .<?php echo $uid; ?> .at-rank {
        font: 800 <?php echo $compact?'12px':'13px'; ?>/1 system-ui; color:#555; text-align:center;
      }
      .<?php echo $uid; ?> .at-rank.medal-1 { color:#d39c00; }
      .<?php echo $uid; ?> .at-rank.medal-2 { color:#8a8a8a; }
      .<?php echo $uid; ?> .at-rank.medal-3 { color:#b87333; }

      .<?php echo $uid; ?> .at-shop  { font: 600 <?php echo $compact?'13px':'14px'; ?>/1.2 system-ui; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .<?php echo $uid; ?> .at-amt   { font: 800 <?php echo $compact?'13px':'14px'; ?>/1.2 system-ui; color:#111; text-align:right; white-space:nowrap; margin-left:8px; }
      .<?php echo $uid; ?> .at-row a { text-decoration:none; color:inherit; display:contents; }
    </style>

    <div class="<?php echo $uid; ?> adatoto-toplist">
      <div class="at-wrap">
        <?php if ($title !== ''): ?>
          <div class="at-head">
            <?php echo esc_html($title); ?>
          </div>
        <?php endif; ?>
        <div class="at-list">
          <?php
          $rank = 0;
          foreach ($list as $item) {
            $rank++;
            $medal = '';
            if ($show_medals) {
              if     ($rank===1) $medal='medal-1';
              elseif ($rank===2) $medal='medal-2';
              elseif ($rank===3) $medal='medal-3';
            }

            // HUF konverzió: egyszer, a végén → egyezik a counter/NGO logikával
            $huf = round($item['eur'] * $rate);
            $amt = number_format($huf, 0, '.', ' ').' Ft';

            echo '<div class="at-row">';
            if (!empty($item['href'])) {
              echo '<a href="'.esc_url($item['href']).'"'.
                   '   data-event="shop_rank_click"'.
                   '   data-rank="'.intval($rank).'"'.
                   '   data-shop-name="'.esc_attr($item['shop']).'"'.
                   '   data-amount="'.esc_attr($amt).'"'.
                   '>';
            }
            echo '  <div class="at-rank '.esc_attr($medal).'">'.intval($rank).'</div>';
            echo '  <div class="at-shop">'.esc_html($item['shop']).'</div>';
            echo '  <div class="at-amt">'.$amt.'</div>';
            if (!empty($item['href'])) echo '</a>';
            echo '</div>';
          }
          ?>
        </div>
      </div>
    </div>

    <script>
      (function(){
        var root = document.currentScript.previousElementSibling;
        if(!root) return;
        var list = root.querySelector('.at-list');

        if (!window.dataLayer) window.dataLayer = [];
        list.addEventListener('click', function(ev){
          var a = ev.target.closest('a[data-event="shop_rank_click"]');
          if(!a) return;
          try {
            var q = new URLSearchParams(location.search);
            window.dataLayer.push({
              event: 'shop_rank_click',
              rank: parseInt(a.getAttribute('data-rank')||'0',10),
              shop_name: a.getAttribute('data-shop-name') || '',
              amount: a.getAttribute('data-amount') || '',   // HUF formátum
              href: a.getAttribute('href') || '',
              ngo:  q.get('d1')  || '',
              amb:  q.get('amb') || '',
              src:  q.get('src') || 'impactshop'
            });
          } catch(e){}
        }, true);
      })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('adatoto', 'impactshop_adatoto_shortcode');
}


 * 
## 11. social feed
**Státusz**: ✅  
**Fájl**: fájlnév  
**Leírás**: leírás

```php
<?php
/**
 * Impact Microfeed – Gen Z frissítés: tabos UI (Chat / Adományok / Facebook),
 * HUF konverzió és természetes nyelvű aktivitás-szöveg.
 * Shortcode:
 *   [impact_microfeed show_form="1" show_fb="1" fb_page="https://www.facebook.com/YourPage" max_items="60" rate_huf="392.5"]
 */

if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST','https://app.sharity.hu');

if (!function_exists('sharity_impact_fetch')) {
  function sharity_impact_fetch($path){
    $url = rtrim(IMPACT_API_BASE_HOST,'/').$path;
    $res = wp_remote_get($url, ['timeout'=>15, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;
    return json_decode(wp_remote_retrieve_body($res), true);
  }
}

if (!defined('IMPACT_MICROFEED_OPT')) define('IMPACT_MICROFEED_OPT', 'impact_microfeed_msgs_v1');
if (!defined('IMPACT_MICROFEED_MAX')) define('IMPACT_MICROFEED_MAX', 300);

/* ---------- REST: üzenetek ---------- */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/microfeed',[
    'methods'=>'GET',
    'callback'=>function(WP_REST_Request $req){
      $max = max(1, min(IMPACT_MICROFEED_MAX, intval($req->get_param('max') ?: 60)));
      $all = get_option(IMPACT_MICROFEED_OPT, []);
      if (!is_array($all)) $all = [];
      $out = array_slice(array_reverse($all), 0, $max);
      return rest_ensure_response(['items'=>$out,'count'=>count($out)]);
    },
    'permission_callback'=>'__return_true'
  ]);

  register_rest_route('impact/v1','/microfeed',[
    'methods'=>'POST',
    'callback'=>'impact_microfeed_post_message',
    'permission_callback'=>function(){ return is_user_logged_in() || wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest'); }
  ]);
});

function impact_microfeed_post_message(WP_REST_Request $req){
  $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  $key = 'impact_microfeed_rl_'.md5($ip);
  if (get_transient($key)) {
    return new WP_Error('ratelimit','Túl gyorsan küldesz. Próbáld újra kicsit később.',['status'=>429]);
  }
  set_transient($key, 1, 60);

  $name = trim(wp_strip_all_tags((string)$req->get_param('name')));
  $text = trim((string)$req->get_param('text'));
  $hp   = trim((string)$req->get_param('company')); // honeypot
  if ($hp !== '') return new WP_Error('spam','Hiba.',['status'=>400]);
  if ($name==='' || $text==='') return new WP_Error('badreq','Név és üzenet kötelező.',['status'=>400]);

  $name = mb_substr($name, 0, 60);
  $text = wp_kses($text, []);
  $text = preg_replace('/\s+/', ' ', $text);
  $text = mb_substr($text, 0, 280);

  $row = ['ts'=>current_time('mysql'),'name'=>$name,'text'=>$text];
  $all = get_option(IMPACT_MICROFEED_OPT, []);
  if (!is_array($all)) $all = [];
  $all[] = $row;

  $maxStored = max(1, min(IMPACT_MICROFEED_MAX, intval(get_option('impact_microfeed_max_items', 300))));
  if (count($all) > $maxStored) $all = array_slice($all, -$maxStored);
  update_option(IMPACT_MICROFEED_OPT, $all, false);

  return rest_ensure_response(['ok'=>true,'item'=>$row]);
}

/* ---------- Shortcode ---------- */
add_shortcode('impact_microfeed', function($atts){
  $a = shortcode_atts([
    'show_form' => '1',
    'show_fb'   => '1',
    'fb_page'   => '',
    'max_items' => '60',
    'title'     => 'Közösségi fal',
    'rate_huf'  => '392.5',   // EUR→HUF becsült árfolyam
  ], $atts, 'impact_microfeed');

  $nonce = wp_create_nonce('wp_rest');
  $api_base   = esc_url_raw(rest_url('impact/v1/microfeed'));
  $activityEP = esc_url_raw(rtrim(IMPACT_API_BASE_HOST,'/').'/wp-json/impact/v1/activity');
  $rate_huf   = floatval($a['rate_huf']);

  ob_start(); ?>
  <style>
    .imf-wrap{--ring:#e5e7eb;--card:#ffffff;--ink:#0b0f19;--muted:#667085;--accent:#7c3aed;--bg:#f8fafc}
    @media (prefers-color-scheme: dark){
      .imf-wrap{--ring:#1f2937;--card:#0b0f19;--ink:#e5e7eb;--muted:#9aa4b2;--accent:#8b5cf6;--bg:#04070d}
    }
    .imf-wrap{background:var(--bg);padding:8px;border-radius:16px}
    .imf-tabs{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
    .imf-tab{cursor:pointer;padding:8px 12px;border:1px solid var(--ring);border-radius:999px;font-weight:700}
    .imf-tab.active{background:linear-gradient(135deg,var(--accent),#22c55e);color:white;border-color:transparent;box-shadow:0 6px 20px rgba(124,58,237,.25)}
    .imf-grid{display:grid;gap:14px}
    .imf-card{background:var(--card);border:1px solid var(--ring);border-radius:16px;padding:12px;box-shadow:0 10px 30px rgba(2,6,23,.04)}
    .imf-card h3{margin:0 0 10px 0;font:700 18px/1.2 system-ui;color:var(--ink)}
    .imf-list{display:grid;gap:10px;max-height:420px;overflow:auto;padding-right:4px}
    .imf-item{display:flex;gap:10px;align-items:flex-start}
    .imf-avatar{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--accent),#22c55e);color:white;font-weight:800;display:flex;align-items:center;justify-content:center;flex:0 0 40px;filter:saturate(1.1)}
    .imf-meta{font-size:12px;color:var(--muted)}
    .imf-msg{font-size:14px;margin-top:2px;color:var(--ink)}
    .imf-form{display:grid;gap:8px}
    .imf-form input,.imf-form textarea{width:100%;padding:12px;border:1px solid var(--ring);border-radius:12px;background:var(--card);color:var(--ink)}
    .imf-form button{padding:12px 16px;border-radius:12px;border:0;background:linear-gradient(135deg,var(--accent),#22c55e);color:#fff;font-weight:800;cursor:pointer;letter-spacing:.2px}
    .imf-activity ul{margin:0;padding-left:1rem;display:grid;gap:.4rem}
    .imf-note{font-size:12px;color:var(--muted);margin-top:6px}
    .imf-hidden{display:none}
    .imf-fb .fb-page{width:100%}
  </style>

  <div class="imf-wrap"
       data-api="<?php echo esc_attr($api_base); ?>"
       data-activity="<?php echo esc_attr($activityEP); ?>"
       data-nonce="<?php echo esc_attr($nonce); ?>"
       data-max="<?php echo intval($a['max_items']); ?>"
       data-rate="<?php echo esc_attr($rate_huf); ?>">
    <div class="imf-tabs">
      <div class="imf-tab active" data-tab="chat">Chat</div>
      <div class="imf-tab" data-tab="donations">Adományok</div>
      <?php if ($a['show_fb']==='1' && $a['fb_page']): ?>
        <div class="imf-tab" data-tab="facebook">Facebook</div>
      <?php endif; ?>
    </div>

    <div class="imf-grid">
      <!-- CHAT -->
      <div class="imf-card" data-panel="chat">
        <h3><?php echo esc_html($a['title']); ?></h3>
        <div class="imf-list" id="imf-list" aria-live="polite"></div>
        <?php if ($a['show_form']==='1'): ?>
        <form class="imf-form" id="imf-form">
          <input type="text" name="name" placeholder="Neved" maxlength="60" required>
          <textarea name="text" rows="3" placeholder="Írj valamit (max 280 karakter)..." maxlength="280" required></textarea>
          <input type="text" name="company" value="" style="display:none">
          <button type="submit">Küldés</button>
          <div id="imf-status" class="imf-note"></div>
        </form>
        <?php endif; ?>
      </div>

      <!-- ADOMÁNYOK -->
      <div class="imf-card imf-hidden" data-panel="donations">
        <h3>Friss adományok</h3>
        <ul id="imf-activity"></ul>
        <div class="imf-note">Automatikus frissítés 30 mp-enként</div>
      </div>

      <!-- FACEBOOK -->
      <?php if ($a['show_fb']==='1' && $a['fb_page']): ?>
      <div class="imf-card imf-fb imf-hidden" data-panel="facebook">
        <h3>Facebook</h3>
        <div id="fb-root"></div>
        <div class="fb-page"
             data-href="<?php echo esc_attr($a['fb_page']); ?>"
             data-tabs="timeline"
             data-width="1000"
             data-height=""
             data-small-header="false"
             data-adapt-container-width="true"
             data-hide-cover="false"
             data-show-facepile="true"></div>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/hu_HU/sdk.js#xfbml=1&version=v17.0"></script>
        <div class="imf-note">Ha itt nem látszik semmi: ellenőrizd a Page URL-t, és hogy nincs életkor/ország korlátozás, illetve a sütikezelő engedte-e a marketing cookie-kat.</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  (function(){
    var root = document.currentScript.previousElementSibling;
    var api  = root.getAttribute('data-api');
    var act  = root.getAttribute('data-activity');
    var max  = parseInt(root.getAttribute('data-max')||'60',10);
    var rate = parseFloat(root.getAttribute('data-rate')||'392.5');
    var nonce= root.getAttribute('data-nonce');

    var list = root.querySelector('#imf-list');
    var form = root.querySelector('#imf-form');
    var status = root.querySelector('#imf-status');
    var ulAct = root.querySelector('#imf-activity');

    // Tabs
    root.querySelectorAll('.imf-tab').forEach(function(tab){
      tab.addEventListener('click', function(){
        root.querySelectorAll('.imf-tab').forEach(t=>t.classList.remove('active'));
        tab.classList.add('active');
        var id = tab.getAttribute('data-tab');
        root.querySelectorAll('[data-panel]').forEach(p=>{
          p.classList.toggle('imf-hidden', p.getAttribute('data-panel')!==id);
        });
      });
    });

    function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
    function initials(n){var p=(n||'').trim().split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase();return p||'😊';}
    function titleize(slug){
      return (slug||'').replace(/[-_]+/g,' ').replace(/\b\w/g, c=>c.toUpperCase()).trim();
    }
    function eurToHuf(eur){var v = Math.round((parseFloat(eur||0)||0)*rate); return v.toLocaleString('hu-HU');}

    async function loadChat(){
      try{
        let r = await fetch(api + '?max=' + encodeURIComponent(max), {headers:{'Accept':'application/json'}});
        if(!r.ok) throw 0;
        let j = await r.json();
        list.innerHTML = (j.items||[]).map(it=>{
          return '<div class="imf-item">'
               +   '<div class="imf-avatar">'+esc(initials(it.name||''))+'</div>'
               +   '<div class="imf-body"><div class="imf-meta">'+esc(it.name||'Ismeretlen')+' • '+esc(it.ts||'')+'</div>'
               +   '<div class="imf-msg">'+esc(it.text||'')+'</div></div>'
               + '</div>';
        }).join('') || '<div class="imf-note">Még nincs üzenet.</div>';
      }catch(e){
        list.innerHTML = '<div style="color:#dc2626">Hiba a chat betöltésekor.</div>';
      }
    }

    // A /activity jelenleg olyan sorokat ad, mint: "mbe • 0,08 € • 2025-09-19 10:51"
    // Ezt feldolgozzuk természetes magyar mondattá és HUF-ra váltjuk.
    function renderActivityRow(txt){
      // próbáljuk kibontani a slug • összeg € • dátum mintát
      var m = (txt||'').match(/^\s*([^•]+?)\s*•\s*([\d.,]+)\s*€\s*•\s*(.+)\s*$/);
      if(!m){
        // fallback: nyers szöveg
        return '<li>'+esc(txt)+'</li>';
      }
      var ngoSlug = m[1].trim();
      var eur = m[2].replace(',', '.');
      var when = m[3].trim();
      var huf = eurToHuf(eur);
      var ngoName = titleize(ngoSlug);
      var sentence = 'Egy vásárlással a(z) '+ngoName+' civil szervezetet '+huf+' forinttal támogatták. ('+esc(when)+')';
      return '<li>'+esc(sentence)+'</li>';
    }

    async function loadActivity(){
      try{
        let r = await fetch(act, {headers:{'Accept':'application/json'}, cache:'no-store'});
        if(!r.ok) throw 0;
        let j = await r.json();
        if(!Array.isArray(j)) j = [];
        ulAct.innerHTML = j.map(row => {
          var t = (typeof row==='string') ? row : (row.text || JSON.stringify(row));
          return renderActivityRow(t);
        }).join('') || '<li class="imf-note">Még nincs friss aktivitás.</li>';
      }catch(e){
        ulAct.innerHTML = '<li style="color:#dc2626">Hiba az aktivitás betöltésekor.</li>';
      }
    }

    if (form){
      form.addEventListener('submit', async function(ev){
        ev.preventDefault();
        status.textContent = 'Küldés…';
        let fd = new FormData(form);
        try{
          let r = await fetch(api, {
            method:'POST',
            headers:{'X-WP-Nonce': nonce},
            body: fd
          });
          let ok = r.ok;
          let j = await r.json().catch(()=>({}));
          if(!ok) throw (j && j.message) || 'Ismeretlen hiba';
          form.reset();
          status.textContent = 'Elmentve.';
          loadChat();
          setTimeout(()=>status.textContent='',1500);
        }catch(err){
          status.textContent = 'Hiba: '+(err && err.toString ? err.toString() : 'ismeretlen');
        }
      });
    }

    loadChat(); loadActivity();
    setInterval(loadActivity, 30000);
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* ---------- Admin: max tárolt üzenetek ---------- */
add_action('admin_init', function(){
  register_setting('reading','impact_microfeed_max_items',['type'=>'integer','default'=>300]);
  add_settings_field('impact_microfeed_max_items','Impact Microfeed – max üzenet (tárolt)','impact_microfeed_max_items_cb','reading');
});
function impact_microfeed_max_items_cb(){
  $v = intval(get_option('impact_microfeed_max_items',300));
  echo '<input type="number" min="10" max="'.IMPACT_MICROFEED_MAX.'" name="impact_microfeed_max_items" value="'.esc_attr($v).'"> <small>Mentett chat bejegyzések felső határa</small>';
}

## 12. Akciók javított
**Státusz**: ✅  
**Fájl**: fájlnév  
**Leírás**: leírás

```php
<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - LINK: REST deeplink-et preferál (ha nincs, url), banner felülír, ha terméklink-gyanús
 * - ÁR: ha REST nem ad, bannerből pótoljuk
 * - Vezérlés: swipe/drag + NYILAK + görgő/trackpad + billentyű
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'     => '12',
      'autoplay'  => '1',
      'interval'  => '3000',
      'direction' => 'right',
      'ga4'       => '1',
    ], $atts, 'impact_deals_netflix');

    $limit    = max(1, (int)$a['limit']);
    $autoplay = $a['autoplay'] === '1';
    $interval = max(700, (int)$a['interval']);
    $dir_sign = (strtolower($a['direction']) === 'left') ? -1 : +1;

    /* ===== segédek ===== */
    $norm_url = function($u){
      $u = trim((string)$u); if ($u==='') return '';
      $p = wp_parse_url($u); if(!$p) return $u;
      $sch = strtolower($p['scheme'] ?? ''); $host = strtolower(preg_replace('~^www\.~','', $p['host'] ?? ''));
      $path = isset($p['path']) ? rtrim($p['path'], '/') : '';
      $qry  = isset($p['query']) ? $p['query'] : '';
      return ($sch && $host) ? ($sch.'://'.$host.$path.($qry?('?'.$qry):'')) : $u;
    };
    $has_u_param = function($u){
      $p = wp_parse_url((string)$u); if(!$p) return false;
      $q = $p['query'] ?? '';
      $path = $p['path'] ?? '';
      // /go-deal és /go-deal/ VAGY akár go-deal végén perjel nélkül is
      $hasGo = (bool)preg_match('~/(go-deal)(?:/|$)~i', $path);
      // többféle paramnév: u, url, to, target, dest, redirect, r
      $hasDeepParam = (bool)preg_match('~(?:^|&)(u|url|to|target|dest|redirect|r)=~i', $q);
      return $hasGo || $hasDeepParam;
    };
    $path_depth = function($u){
      $p = wp_parse_url((string)$u); $path = $p['path'] ?? '';
      if ($path === '' || $path === '/') return 0;
      $parts = array_values(array_filter(explode('/', $path), fn($x)=>$x!==''));
      return count($parts);
    };

    /* ===== 1) Banner feed (ár + alternatív produkt link) – index shop_slug → fallback slug ===== */
    $banner_by_slug = []; // kulcs: shop_slug (lower)
    if (function_exists('do_shortcode')) {
      $json = do_shortcode('[impactshop_deals_banners limit="'.intval($limit*3).'" force="1" json="1"]');
      $rows = json_decode(trim(wp_strip_all_tags($json)), true);
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $slug = strtolower((string)($r['shop_slug'] ?? $r['slug'] ?? '')); if(!$slug) continue;
          $banner_by_slug[$slug] = [
            'href'      => (string)($r['href'] ?? ''),
            'price'     => trim((string)($r['price'] ?? '')),
            'old_price' => trim((string)($r['old_price'] ?? '')),
            'img'       => (string)($r['img'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'pct'       => (int)($r['pct'] ?? 0),
            'shop_name' => (string)($r['shop_name'] ?? ''),
          ];
        }
      }
    }
    if (!$banner_by_slug && function_exists('sib_load_banners')) {
      try {
        foreach ((array)sib_load_banners() as $r) {
          $slug = strtolower((string)($r['shop_slug'] ?? $r['slug'] ?? '')); if(!$slug) continue;
          $banner_by_slug[$slug] = [
            'href'      => (string)($r['href'] ?? ''),
            'price'     => trim((string)($r['price'] ?? '')),
            'old_price' => trim((string)($r['old_price'] ?? '')),
            'img'       => (string)($r['img'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'pct'       => (int)($r['pct'] ?? 0),
            'shop_name' => (string)($r['shop_name'] ?? ''),
          ];
        }
      } catch(\Throwable $e){}
    }

    /* ===== 2) REST – ez adja az alap kártyalistát (DEEPLINK-et preferáljuk!) ===== */
    $items = [];
    $rest_rows = [];
    foreach ([home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
              home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit)] as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      if ($rows){ $rest_rows = $rows; break; }
    }

    foreach ($rest_rows as $r) {
      $slug  = strtolower((string)($r['shop_slug'] ?? $r['shop'] ?? ''));
      // <-- 1) JAVÍTÁS: deeplink legyen elsődleges!
      $hrefR = (string)($r['deeplink'] ?? $r['url'] ?? '');
      $price = trim((string)($r['price'] ?? ''));
      $opric = trim((string)($r['old_price'] ?? ''));
      $b = $banner_by_slug[$slug] ?? null;

      // ár pótlás bannerből
      if ($price==='')  $price = $b['price'] ?? '';
      if ($opric==='')  $opric = $b['old_price'] ?? '';

      // LINK VÁLASZTÁS: ha banner terméklinkes (has_u_param), banner nyer;
      // ha REST "főoldal-gyanús" (sekély path), és van banner link, banner nyer.
      $href = $hrefR;
      if (!empty($b['href'])) {
        if ($has_u_param($b['href'])) {
          $href = $b['href']; // terméklink-gyanús
        } else if (!$has_u_param($hrefR) && $path_depth($hrefR) <= 1) {
          $href = $b['href'];
        }
      }

      $items[] = [
        'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
        'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ($b['title'] ?? '')),
        'percent'   => (float)($r['percent'] ?? $r['discount'] ?? ($b['pct'] ?? 0)),
        'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ($b['img'] ?? '')),
        'shop_slug' => $slug,
        'shop_name' => (string)($r['shop_name'] ?? ($b['shop_name'] ?? '')),
        'url'       => $href,     // végső link
        'price'     => $price,
        'old_price' => $opric,
        'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
      ];
    }

    // ha REST üres volt → közvetlenül a bannereket tesszük ki
    if (!$items && $banner_by_slug) {
      foreach ($banner_by_slug as $slug=>$b) {
        if (!$b['href'] || !$b['img']) continue;
        $items[] = [
          'id'        => '',
          'title'     => $b['title'],
          'percent'   => (int)$b['pct'],
          'image'     => $b['img'],
          'shop_slug' => $slug,
          'shop_name' => $b['shop_name'],
          'url'       => $b['href'],
          'price'     => $b['price'],
          'old_price' => $b['old_price'],
          'currency'  => '',
        ];
        if (count($items) >= $limit) break;
      }
    }

    // szűrés + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --gap:14px; --cardW:280px; --cardH:168px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0b1220; position:relative
      }
      .<?php echo $uid; ?> .rail{
        display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 48px;
        cursor:grab; user-select:none; -webkit-user-drag:none; touch-action:pan-x pan-y pinch-zoom;
      }
      .<?php echo $uid; ?> .card{
        position:relative; flex:0 0 var(--cardW); height:var(--cardH); border-radius:var(--radius);
        border:1px solid rgba(0,0,0,.08); background:#0b1220; box-shadow:var(--shadow); overflow:hidden
      }
      .<?php echo $uid; ?> .media{ position:absolute; inset:0; overflow:hidden; border-radius:inherit; background:#0b1220 }
      .<?php echo $uid; ?> .media::before{
        content:""; position:absolute; inset:0;
        background:
          radial-gradient(120% 100% at 50% 50%, rgba(255,255,255,.06), rgba(0,0,0,.0)),
          linear-gradient(to bottom, rgba(0,0,0,.0) 55%, rgba(0,0,0,.36) 100%);
        pointer-events:none;
      }
      .<?php echo $uid; ?> .media img{
        width:100%; height:100%; object-fit:contain; object-position:center; display:block;
        filter: drop-shadow(0 6px 20px rgba(0,0,0,.25));
      }
      .<?php echo $uid; ?> .badge{
        position:absolute; left:10px; top:10px; padding:6px 10px;
        background:linear-gradient(135deg,#ef4444,#f59e0b); color:#fff; font-weight:900; font-size:12px;
        border-radius:999px; letter-spacing:.03em; z-index:3
      }
      .<?php echo $uid; ?> .shop{
        position:absolute; left:10px; top:44px; padding:5px 8px; font-weight:800; font-size:11px;
        border-radius:10px; background:rgba(255,255,255,.96); color:#0f172a; z-index:3; max-width:64%;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis
      }
      .<?php echo $uid; ?> .label{
        position:absolute; left:12px; bottom:12px; right:116px; color:#fff; font-weight:800; font-size:13px;
        text-shadow:0 2px 10px rgba(0,0,0,.6); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; z-index:2
      }
      .<?php echo $uid; ?> .price{
        position:absolute; right:10px; bottom:10px; z-index:4; padding:8px 12px;
        border-radius:12px; background:rgba(255,255,255,.98); color:#0f172a; font-weight:900; font-size:14px;
        border:1px solid rgba(0,0,0,.08); box-shadow:0 6px 18px rgba(2,6,23,.22)
      }
      .<?php echo $uid; ?> .nav{ position:absolute; top:50%; transform:translateY(-50%); width:38px; height:38px; border-radius:999px;
        background:#fff; box-shadow:0 8px 22px rgba(2,6,23,.15); display:grid; place-items:center; cursor:pointer; border:1px solid #e5e7eb; z-index:5 }
      .<?php echo $uid; ?> .nav:hover{ transform:translateY(-50%) scale(1.05) }
      .<?php echo $uid; ?> .prev{ left:8px } .<?php echo $uid; ?> .next{ right:8px }
      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }
      @media (max-width:860px){
        .<?php echo $uid; ?>{ --cardW: 78vw; --cardH: 45vw; }
        .<?php echo $uid; ?> .rail{ padding:4px 44px }
      }
    </style>

    <div class="<?php echo $uid; ?>" tabindex="0">
      <button class="nav prev" aria-label="Vissza" data-prev>‹</button>
      <button class="nav next" aria-label="Előre" data-next>›</button>

      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim((string)($d['title'] ?? ''));
          $pct   = !empty($d['percent']) ? (int)round((float)$d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']); // végső link (deeplink preferálva)
          $pstr  = trim((string)($d['price'] ?? ''));
          $showLabel = (!$pstr) && ($title !== '') && (strcasecmp($title,'akció') !== 0);
        ?>
          <a class="card" href="<?php echo $href; ?>" target="_blank" rel="nofollow sponsored noopener"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($shop); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($pstr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title ?: ($shop ?: 'Ajánlat')); ?>" loading="lazy" decoding="async" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <?php if ($showLabel): ?><div class="label" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></div><?php endif; ?>
            <?php if ($pstr): ?><div class="price"><?php echo esc_html($pstr); ?></div><?php endif; ?>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const rail = root.querySelector('[data-rail]');
      const prev = root.querySelector('[data-prev]');
      const next = root.querySelector('[data-next]');
      const cards= Array.from(rail.querySelectorAll('.card'));
      const autoplay = <?php echo $autoplay ? 'true' : 'false'; ?>;
      const interval = <?php echo (int)$interval; ?>;
      const dirSign  = <?php echo $dir_sign; ?>;

      function gap(){ const cs = getComputedStyle(rail); return parseFloat(cs.columnGap||cs.gap||'14')||14; }
      function step(){ const c = cards[0]; if(!c) return 300; const r = c.getBoundingClientRect(); return r.width + gap(); }
      function atRight(){ return rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2; }
      function atLeft(){ return rail.scrollLeft <= 0; }
      function wrapIfNeeded(){
        if (dirSign > 0 && atRight()) rail.scrollLeft = 0;
        else if (dirSign < 0 && atLeft()) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      }
      function scrollStep(sign){
        wrapIfNeeded();
        rail.scrollBy({ left: sign*step(), behavior: 'smooth' });
        setTimeout(wrapIfNeeded, 80);
      }
      requestAnimationFrame(()=>{ if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0); });

      // SWIPE/DRAG + inercia
      let isDown=false, sx=0, sl=0, moved=0, vx=0, raf=null, lastX=0, lastT=0;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; moved=0; vx=0; sx=e.clientX; sl=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.setPointerCapture(e.pointerId); rail.style.cursor='grabbing'; if (raf) cancelAnimationFrame(raf), raf=null;
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const now=performance.now(), dt=now-lastT||1, dx=e.clientX - sx;
        moved=Math.max(moved, Math.abs(dx)); rail.scrollLeft = sl - dx;
        vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now;
      });
      function pointerEnd(e){
        if(!isDown) return; isDown=false; rail.style.cursor='grab';
        try{ rail.releasePointerCapture(e.pointerId); }catch(_){}
        let v = Math.max(-1.5, Math.min(1.5, vx)) * 24;
        function tick(){ if (Math.abs(v) < 0.1) return; rail.scrollLeft -= v; v *= 0.92; raf = requestAnimationFrame(tick); }
        raf = requestAnimationFrame(tick);
      }
      rail.addEventListener('pointerup', pointerEnd);
      rail.addEventListener('pointercancel', pointerEnd);

      // Kattintás csak ha nem húzott (küszöb 14px)
      rail.addEventListener('click', (e)=>{ if(moved > 14){ e.preventDefault(); e.stopPropagation(); } }, true);

      // Görgő/trackpad: Y→X kényelmi görgetés
      rail.addEventListener('wheel', (e)=>{
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
        rail.scrollLeft += e.deltaY; e.preventDefault();
      }, { passive:false });

      // Nyilak + billentyű
      prev && prev.addEventListener('click', ()=> scrollStep(-1));
      next && next.addEventListener('click', ()=> scrollStep(+1));
      root.addEventListener('keydown', (e)=>{ if (e.key === 'ArrowRight'){ e.preventDefault(); scrollStep(+1); } else if (e.key === 'ArrowLeft'){ e.preventDefault(); scrollStep(-1); } });

      // Autoplay
      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      rail.addEventListener('pointerdown', stop);
      rail.addEventListener('pointerup', start);
      start();
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}

