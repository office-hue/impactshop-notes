<?php
/**
 * Plugin Name: Impact – Árukereső hard guard (deal-route interceptor)
 * Description: /go és /go-deal alatt, ha a shop Árukereső (vagy az u/product_url arukereso.*), a Dognet linket deeplink nélkül generálja (nincs "Custom URL host" hiba).
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ------------ segédek ------------ */

if (!function_exists('iahg_is_arukereso_host')) {
  function iahg_is_arukereso_host($host){
    if (!$host) return false;
    return (bool)preg_match('~(^|\.)arukereso\.[a-z.]+$~i', strtolower($host));
  }
}
if (!function_exists('iahg_clean')) {
  function iahg_clean($u){
    if (!$u) return $u;
    $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
    $u = str_replace(['&amp;','amp%3B','%26amp%3B'], '&', $u);
    if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $u)) {
      $tmp = base64_decode($u, true);
      if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $u = $tmp;
    }
    $u = preg_replace_callback('~%25([0-9A-F]{2})~i', fn($m)=>'%'.$m[1], $u);
    $kill = ['a_bid','a_aid','a_cid','chan','data1','ref','refid','utm_source','utm_medium','utm_campaign','utm_term'];
    $p = parse_url($u);
    if (!empty($p['query'])) {
      parse_str($p['query'], $qs);
      foreach($kill as $k) unset($qs[$k]);
      $u = (isset($p['scheme'])?$p['scheme'].'://':'')
         . ($p['host']??'')
         . ($p['path']??'')
         . ($qs ? ('?'.http_build_query($qs)) : '')
         . (isset($p['fragment']) ? '#'.$p['fragment'] : '');
    }
    return $u;
  }
}
if (!function_exists('iahg_redirect_with_propagation')) {
  function iahg_redirect_with_propagation($url,$amb,$src){
    $add=[];
    if ($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
    if ($src && strpos($url,'src=')===false) $add['src']=$src;
    if (strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
    if (strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
    if ($add) $url .= (strpos($url,'?')===false ? '?' : '&') . http_build_query($add);
    wp_redirect($url, 307);
    exit;
  }
}

/* 1) ha van ?u=…: tisztítsuk (általános) */
add_action('init', function(){
  if (isset($_GET['u']) && is_string($_GET['u'])) {
    $_GET['u'] = iahg_clean($_GET['u']);
  }
}, 1);

/* 2) Árukereső-specifikus elfogás a /go és /go-deal route előtt (priority 1) */
add_action('template_redirect', function(){
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $is_go   = ( get_query_var('impactshop_go')   || strpos($uri, '/go/')      !== false );
  $is_deal = ( get_query_var('impactshop_deal') || strpos($uri, '/go-deal/') !== false );
  if (!$is_go && !$is_deal) return;

  // shop slug feloldása (GET, query var, vagy URI alapján)
  $shop = isset($_GET['shop']) ? sanitize_text_field($_GET['shop']) : get_query_var('impactshop_slug');
  if (!$shop && preg_match('~/go(?:-deal)?/([^/?#]+)~', $uri, $m)) $shop = sanitize_text_field($m[1]);
  if (!$shop) return;

  $row = function_exists('impactshop_find_shop') ? impactshop_find_shop($shop) : null;
  $slug = strtolower($row['shop_slug'] ?? $shop);

  // arukereso detekt
  $is_aru = (strpos($slug, 'arukereso') !== false);
  if (!$is_aru) {
    $u_host = parse_url($_GET['u'] ?? '', PHP_URL_HOST);
    if (iahg_is_arukereso_host($u_host)) $is_aru = true;
  }
  if (!$is_aru && !empty($row['product_url'])) {
    $p_host = parse_url($row['product_url'], PHP_URL_HOST);
    if (iahg_is_arukereso_host($p_host)) $is_aru = true;
  }
  if (!$is_aru) return; // nem Árukereső → hagyjuk a fő snippetet dolgozni

  // kötelező paraméterek
  $ngo = isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '';
  if (!$ngo) return; // hagyjuk a fő snippetet hibázni a saját mechanizmusával

  $amb = isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '';
  $src = isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop';

  // Deeplink csak akkor, ha a host Árukereső (különben whitelistes hiba lehet)
  $deeplink = '';
  if (!empty($_GET['u'])) {
    $u_host = parse_url($_GET['u'], PHP_URL_HOST);
    if (iahg_is_arukereso_host($u_host)) $deeplink = $_GET['u'];
  }

  // Dognet-link generálás, deeplink csak Árukereső hostra
  $final = '';
  $cid = 0;

  if (!empty($row['dognet_base']) && function_exists('dognet_extract_campaign_id_from_base')) {
    $cid = dognet_extract_campaign_id_from_base($row['dognet_base']);
  }
  if ($cid && function_exists('dognet_api_generate_link')) {
    $api = dognet_api_generate_link($cid, $deeplink, $ngo, '');
    if (!is_wp_error($api) && $api) $final = $api;
  }
  if (!$final) {
    $base = $row['dognet_base'] ?? '';
    if (!$base) return; // nincs mit tenni, hagyjuk a fő snippetet
    $qs = ['d1' => $ngo];
    if ($deeplink !== '') $qs['url'] = $deeplink;
    $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($qs);
  }

  // UTM/amb/src propagáció és kilépés – így a fő snippet már nem fut le duplán
  iahg_redirect_with_propagation($final, $amb, $src);
}, 1);
