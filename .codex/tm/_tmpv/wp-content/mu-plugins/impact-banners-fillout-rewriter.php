<?php
/**
 * MU: Sharity Impact – Banners Fillout Rewriter (no conflicts)
 * - A Banners CSV-ből jövő href-ek Fillout URL-ek. Ha az oldal URL-jében van d1 (NGO),
 *   akkor a HTML-ben ezeket átírjuk:
 *     * shop=arukereso  → közvetlen Dognet Árukereső link (dognet_base + data1), NINCS deeplink
 *     * más shop        → helyi /go-deal/{shop}?d1=...&u=... (base64 termék URL átadással)
 *
 * Nem nyúl a nagy snippethez, nem ütközik más MU-val.
 */

if (!defined('ABSPATH')) { exit; }

define('IMPACT_FILLOUT_HOST', 'form.fillout.com');

/** kis segédek */
function ibfr_is_arukereso_host($h){
  return $h && preg_match('~(^|\.)arukereso\.[a-z.]+$~i', strtolower($h));
}
function ibfr_find_shop_row($slug){
  if (!function_exists('impactshop_find_shop')) return null;
  return impactshop_find_shop($slug);
}
function ibfr_build_arukereso_from_base($base, $ngo){
  if (!$base) return '';
  $p = parse_url($base);
  if (empty($p['scheme']) || empty($p['host'])) return '';
  parse_str($p['query'] ?? '', $qs);

  // biztosítsuk a Dognet kötelezőket érintetlenül; "url" DEEPLINKET TILTSUK
  unset($qs['url']);
  if (!empty($ngo)) $qs['data1'] = $ngo;
  if (empty($qs['utm_source']))   $qs['utm_source']   = 'dognet';
  if (empty($qs['utm_medium']))   $qs['utm_medium']   = 'cpc';
  if (empty($qs['utm_campaign'])) $qs['utm_campaign'] = 'HU';

  $path = $p['path'] ?? '/';
  return $p['scheme'].'://'.$p['host'].$path.'?'.http_build_query($qs);
}

/**
 * A teljes renderelt contentben a Fillout-hivatkozásokat átírjuk,
 * ha van d1 a kérésben.
 */
add_filter('the_content', function($content){
  if (is_admin()) return $content;

  // csak ha tényleg van d1 a kérésben
  $ngo = isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '';
  if ($ngo === '') return $content;

  // gyors ellenőrzés: van-e egyáltalán Fillout link
  if (stripos($content, IMPACT_FILLOUT_HOST) === false) return $content;

  // cseréljük ki az <a ... href="https://form.fillout.com/t/... ?shop=xxx&u=base64" ...> linkeket
  $content = preg_replace_callback(
    '~(<a\s+[^>]*?href=["\'])(https?://'.preg_quote(IMPACT_FILLOUT_HOST,'~').'/t/[^"\']+)(["\'][^>]*>)~i',
    function($m) use ($ngo){
      $prefix = $m[1];
      $url    = $m[2];
      $suffix = $m[3];

      // parse
      $parts = wp_parse_url($url);
      if (empty($parts['query'])) return $m[0];
      parse_str($parts['query'], $qs);
      $shop = isset($qs['shop']) ? sanitize_text_field($qs['shop']) : '';
      $u_b64= isset($qs['u'])    ? (string)$qs['u'] : '';

      if ($shop === '') return $m[0];

      // ha van hozzá shop-sorunk, megnézzük, Árukereső-e
      $row = ibfr_find_shop_row($shop);
      $final = '';

      if ($row) {
        $baseHost = parse_url($row['dognet_base'] ?? '', PHP_URL_HOST);
        $isAruBySlug = (stripos($shop, 'arukereso') !== false);
        $isAru = $isAruBySlug || ibfr_is_arukereso_host($baseHost);

        if ($isAru) {
          // ÁRUKERESŐ: közvetlen, deeplink NÉLKÜL, data1-el
          $final = ibfr_build_arukereso_from_base($row['dognet_base'] ?? '', $ngo);
        }
      }

      if ($final === '') {
        // nem Árukereső → átirányítjuk a helyi /go-deal/{shop}-ra, ugyanazzal a base64 u-val
        $go = home_url('/go-deal/'.rawurlencode($shop));
        $qs2 = ['d1'=>$ngo];
        if ($u_b64 !== '') $qs2['u'] = $u_b64;
        $final = add_query_arg($qs2, $go);
      }

      return $prefix . esc_url($final) . $suffix;
    },
    $content
  );

  return $content;
}, 12); // a shortcode-ok után fusson