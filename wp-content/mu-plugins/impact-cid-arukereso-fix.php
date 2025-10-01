<?php
/**
 * Plugin Name: Impact – CID & Árukereső Fix (standalone MU)
 * Description: Árukereső deeplink-hiba elkerülése (go-deal alatt a 'u' param kezelése) + CID→webshop név javítás KÜLÖN rövidkóddal.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ======================= ÁRUKERESŐ: deeplink tisztítás ======================= */

if (!function_exists('icaf_is_probably_base64')) {
  function icaf_is_probably_base64($s){ return (bool)preg_match('~^[A-Za-z0-9+/]+={0,2}$~', (string)$s); }
}
if (!function_exists('icaf_clean_deeplink')) {
  function icaf_clean_deeplink($u){
    if (!$u) return $u;
    $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
    $u = str_replace(['&amp;','amp%3B','%26amp%3B'], '&', $u);
    if (icaf_is_probably_base64($u)) {
      $tmp = base64_decode($u, true);
      if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $u = $tmp;
    }
    $u = preg_replace_callback('~%25([0-9A-F]{2})~i', fn($m)=>'%'.$m[1], $u);
    // csak a zajos affil paramokat szedjük ki
    $kill = ['a_bid','a_aid','a_cid','chan','data1','ref','refid'];
    $p = parse_url($u);
    if (!empty($p['query'])) {
      parse_str($p['query'], $qs);
      foreach ($kill as $k) unset($qs[$k]);
      $u = (isset($p['scheme'])?$p['scheme'].'://':'')
         . ($p['host']??'')
         . ($p['path']??'')
         . ($qs ? ('?'.http_build_query($qs)) : '')
         . (isset($p['fragment']) ? '#'.$p['fragment'] : '');
    }
    return $u;
  }
}

/**
 * init – nagyon korán: a bejövő GET['u'] megtisztítása.
 * Ha Árukereső hostot találunk, a 'u'-t kiürítjük → a nagy snippet BASE linket használ.
 */
add_action('init', function(){
  if (is_admin()) return;

  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $is_deal = (isset($_GET['impactshop_deal']) || stripos($uri, '/go-deal') !== false);

  if (!isset($_GET['u']) || !is_string($_GET['u'])) return;

  $clean = icaf_clean_deeplink($_GET['u']);
  $host  = parse_url($clean, PHP_URL_HOST);
  $is_arukereso = $host ? (bool)preg_match('~(^|\.)arukereso\.[a-z.]+$~i', $host) : false;

  if ($is_deal && $is_arukereso) {
    $_GET['u'] = '';        // teljes tiltás deeplinkre → nincs “Custom URL host” hiba
  } else {
    $_GET['u'] = $clean;    // egyébként csak tisztítunk
  }
}, 1);

/* ======================= CID → SHOP NÉV: külön rövidkód ======================= */

if (!function_exists('icaf_get_campaign_map')) {
  function icaf_get_campaign_map(){
    $by_cid = [];
    if (function_exists('impactshop_get_shops')) {
      foreach (impactshop_get_shops() as $s) {
        $base = $s['dognet_base'] ?? '';
        if (!$base) continue;
        $qs = parse_url($base, PHP_URL_QUERY);
        parse_str((string)$qs, $p);
        $cid = isset($p['cid']) ? intval($p['cid']) : 0;
        if ($cid) $by_cid[$cid] = ['name'=>$s['name'] ?? ('cid '.$cid), 'slug'=>$s['shop_slug'] ?? ''];
      }
    }
    return $by_cid;
  }
}

/**
 * ÚJ, nem ütköző shortcode:
 * [impact_leaderboard_shop_fix tab="shop|ngo"]
 * - shop fülön a "cid 987" tipusú neveket a CSV-ből vett webshopnévre cseréli
 * - NGO fülön csak átpasszolja az adatot
 */
add_shortcode('impact_leaderboard_shop_fix', function($atts){
  $a = shortcode_atts(['tab'=>'ngo'], $atts, 'impact_leaderboard_shop_fix');
  $tab = ($a['tab']==='shop') ? 'shop' : 'ngo';

  // A helyi Impact Bridge REST-je (nem módosítjuk)
  if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST','https://app.sharity.hu');
  $url = rtrim(IMPACT_API_BASE_HOST,'/').'/wp-json/impact/v1/leaderboard?tab='.$tab;
  $res = wp_remote_get($url, ['timeout'=>15, 'headers'=>['Accept'=>'application/json']]);
  if (is_wp_error($res) || (wp_remote_retrieve_response_code($res) < 200)) {
    return '<div class="card" style="padding:12px">Nincs adat.</div>';
  }
  $data = json_decode(wp_remote_retrieve_body($res), true);
  if (!is_array($data) || !count($data)) return '<div class="card" style="padding:12px">Nincs adat.</div>';

  if ($tab === 'shop') {
    $map = icaf_get_campaign_map();
    foreach ($data as &$row) {
      $name = (string)($row['name'] ?? '');
      if (preg_match('~^cid\s+(\d+)$~i', $name, $m)) {
        $cid = intval($m[1]);
        if (isset($map[$cid])) {
          $row['name'] = $map[$cid]['name'] . ( $map[$cid]['slug'] ? ' ('.$map[$cid]['slug'].')' : '' );
        }
      }
    }
    unset($row);
  }

  // egyszerű megjelenítés
  $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
  foreach ($data as $i=>$row) {
    $nm  = esc_html($row['name'] ?? '—');
    $amt = number_format((float)($row['amount'] ?? 0), 2, ',', ' ') . ' €';
    $out .= '<li style="display:flex;gap:8px;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">'
          .   '<span style="opacity:.7">'.($i+1).'.</span><span style="flex:1">'.$nm.'</span><strong>'.$amt.'</strong></li>';
  }
  return $out.'</ul></div>';
});