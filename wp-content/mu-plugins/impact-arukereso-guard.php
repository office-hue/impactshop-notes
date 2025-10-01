<?php
/**
 * Plugin Name: Impact – Árukereső deeplink guard (standalone MU)
 * Description: Minden kérést megtisztít; ha a ?u (deeplink) Árukereső hostra mutat, a paramétert kiüríti, így a Dognet BASE linkre esik vissza (nincs "Custom URL host" hiba).
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/** belső: base64 gyanú */
function ia_guard_is_b64($s){ return (bool)preg_match('~^[A-Za-z0-9+/]+={0,2}$~', (string)$s); }

/** belső: deeplink tisztítás (entity → &, %25XX → %XX, zajos affil paramok eldobása) */
function ia_guard_clean($u){
  if (!$u) return $u;

  // &amp; → &
  $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
  $u = str_replace(['&amp;','amp%3B','%26amp%3B'], '&', $u);

  // base64-elt URL kinyitása
  if (ia_guard_is_b64($u)) {
    $tmp = base64_decode($u, true);
    if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $u = $tmp;
  }

  // dupla %-kódolás visszafejtése
  $u = preg_replace_callback('~%25([0-9A-F]{2})~i', fn($m)=>'%'.$m[1], $u);

  // affil/utm zaj lecsupaszítása (a Dognet érzékeny lehet rá)
  $kill = ['a_bid','a_aid','a_cid','chan','data1','ref','refid','utm_source','utm_medium','utm_campaign','utm_term'];
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

/**
 * FŐ LOGIKA: nagyon korán fut, és HA van ?u=…, akkor
 *  - megtisztítja
 *  - ha a host arukereso.* → teljesen KIÜRÍTI (így a nagy snippet BASE linket használ)
 */
add_action('init', function(){
  if (!isset($_GET['u']) || !is_string($_GET['u'])) return;

  $clean = ia_guard_clean($_GET['u']);
  $host  = parse_url($clean, PHP_URL_HOST);
  $is_arukereso = $host ? (bool)preg_match('~(^|\.)arukereso\.[a-z.]+$~i', $host) : false;

  // Árukereső deeplink tiltás: üresre állítjuk
  $_GET['u'] = $is_arukereso ? '' : $clean;
}, 1); // priority 1 → minden más (és a nagy snippet) előtt lefut