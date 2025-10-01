<?php
/**
 * Plugin Name: Impact – Árukereső Deeplink Fix (no conflicts)
 * Description: Árukereső banner/Fillout linkeknél kikerüli a /go(/-deal) útvonalat és közvetlenül a termékoldalra visz a Dognet paraméterekkel. Nem ütközik az összevont snippettel.
 * Version: 1.0.0
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* -------------------- SEGÉDEK (izolált prefix: iarfx_) -------------------- */

if (!function_exists('iarfx_is_arukereso_host')) {
  function iarfx_is_arukereso_host($host){
    if (!$host) return false;
    return (bool)preg_match('~(^|\.)arukereso\.[a-z.]+$~i', strtolower($host));
  }
}

if (!function_exists('iarfx_unEntity')) {
  function iarfx_unEntity($s){
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    $s = str_replace(['&amp;','amp%3B','%26amp%3B'], '&', $s);
    // %25XX -> %XX (dupla százalék-kódolás)
    $s = preg_replace_callback('~%25([0-9A-F]{2})~i', fn($m)=>'%'.$m[1], $s);
    return $s;
  }
}

if (!function_exists('iarfx_parse_query')) {
  function iarfx_parse_query($url){
    $out=[]; $qs = is_string($url) ? parse_url($url, PHP_URL_QUERY) : '';
    if ($qs) parse_str($qs, $out);
    return is_array($out) ? $out : [];
  }
}

if (!function_exists('iarfx_get_shop_by_slug')) {
  function iarfx_get_shop_by_slug($slug){
    if (!function_exists('impactshop_get_shops')) return null;
    $slug = strtolower(trim($slug));
    foreach (impactshop_get_shops() as $s) {
      if (strtolower($s['shop_slug'] ?? '') === $slug) return $s;
    }
    return null;
  }
}

/**
 * Dognet base → affil paramok (a_aid, a_cid, a_bid, chan, stb.)
 * A CSV-ben levő dognet_base-ből kinyerjük a query-t és csak a Dognet paramokat visszük tovább.
 */
if (!function_exists('iarfx_aff_params_from_dognet_base')) {
  function iarfx_aff_params_from_dognet_base($dognet_base){
    $q = iarfx_parse_query($dognet_base);
    // engedélyezett kulcsok (Dognet)
    $whitelist = ['a_aid','a_cid','a_bid','chan','chid','refid'];
    $out=[];
    foreach ($whitelist as $k) {
      if (!empty($q[$k])) $out[$k] = $q[$k];
    }
    // historikus: néha csak chid van → nevezzük át chan kulcsnak is, ha kell
    if (isset($out['chid']) && !isset($out['chan'])) $out['chan'] = $out['chid'];
    return $out;
  }
}

/**
 * Árukereső termék URL + Dognet paramok összefűzése.
 * - Ha nem arukereso host, visszaadjuk az eredetit.
 * - Ha arukereso host, hozzátoldjuk a dognet_base-ből kinyert paramokat + d1-et.
 */
if (!function_exists('iarfx_build_arukereso_tracked_url')) {
  function iarfx_build_arukereso_tracked_url($productUrl, $shopSlug, $d1){
    $u = iarfx_unEntity($productUrl);
    $host = parse_url($u, PHP_URL_HOST);
    if (!iarfx_is_arukereso_host($host)) return $u;

    $row = iarfx_get_shop_by_slug($shopSlug);
    if (!$row) return $u;
    $aff = iarfx_aff_params_from_dognet_base($row['dognet_base'] ?? '');

    // kötelező UTM-ek (Dognet ajánlás) – ha még nincsenek
    $baseQ = iarfx_parse_query($u);
    $defaults = [
      'utm_source' => 'dognet',
      'utm_medium' => 'cpc',
      'utm_campaign' => strtoupper(substr($host, -2)) // HU / SK / CZ stb. – nagyon egyszerű heurisztika
    ];
    foreach ($defaults as $k=>$v) {
      if (!isset($baseQ[$k])) $baseQ[$k] = $v;
    }

    // Dognet affil paramok
    foreach ($aff as $k=>$v) $baseQ[$k] = $v;

    // data1 (NGO) – HA az oldal URL-jében van d1=...
    $d1 = trim((string)$d1);
    if ($d1 !== '') $baseQ['data1'] = $d1;

    // összeépítés
    $scheme = parse_url($u, PHP_URL_SCHEME) ?: 'https';
    $path   = parse_url($u, PHP_URL_PATH)   ?: '';
    $frag   = parse_url($u, PHP_URL_FRAGMENT);
    $final  = $scheme.'://'.$host.$path.( $baseQ ? ('?'.http_build_query($baseQ)) : '' ).( $frag ? '#'.$frag : '' );
    return $final;
  }
}

/* -------------------- FRONTEND CLICK INTERCEPTOR -------------------- */

/**
 * Nem módosítjuk a snippet HTML-jét. Ehelyett a frontenden:
 * - figyeljük a kattintást MINDEN <a> elemre
 * - ha az href Fillout és shop=arukereso és van u= (base64 termék-URL), akkor:
 *     1) kibontjuk a termék-URL-t
 *     2) összeállítjuk a Dognet paraméterezett Árukereső termék-URL-t
 *     3) átirányítjuk oda (preventDefault)
 * Más linkeket nem érintünk.
 */
add_action('wp_footer', function(){
  // Shop meta előkészítés: csak az Árukereső sor kell (dognet_base kibányászáshoz)
  $aru = iarfx_get_shop_by_slug('arukereso');
  $aruDognetBase = is_array($aru) ? ($aru['dognet_base'] ?? '') : '';

  // Ha nincs arukereso a CSV-ben, nincs dolgunk
  if (!$aruDognetBase) return;

  // Fillout BASE (amit a Banners táblába ír a GAS)
  $fillout = 'https://form.fillout.com/t/eM61RLkz6jus';
  ?>
<script>
(function(){
  try{
    var FILLOUT = <?php echo json_encode($fillout); ?>;
    var ARU_AFF = <?php echo json_encode(iarfx_aff_params_from_dognet_base($aruDognetBase)); ?>;

    function isAruHost(h){
      return !!(h && /(^|\.)arukereso\.[a-z.]+$/i.test(String(h).toLowerCase()));
    }
    function parseQS(url){
      var q = {}, m = String(url).split('?')[1] || '';
      m = m.split('#')[0] || '';
      if (!m) return q;
      m.split('&').forEach(function(p){
        var kv = p.split('='), k=decodeURIComponent(kv[0]||''), v=decodeURIComponent(kv[1]||'');
        if (k) q[k]=v;
      });
      return q;
    }
    function base64Try(s){
      try{ return atob(s); }catch(_){ return ''; }
    }
    function unEntity(s){
      try{
        s = s.replace(/&(amp|#38);/gi,'&');
        s = s.replace(/%25([0-9A-F]{2})/gi, function(_,h){ return '%'+h; });
        return s;
      }catch(_){ return s; }
    }
    function buildAruTracked(productUrl, d1){
      var a = document.createElement('a');
      a.href = productUrl;
      if (!isAruHost(a.hostname)) return productUrl;

      var qs = parseQS(productUrl);
      if (!('utm_source' in qs)) qs.utm_source = 'dognet';
      if (!('utm_medium' in qs)) qs.utm_medium = 'cpc';
      if (!('utm_campaign' in qs)) {
        // nagyon egyszerű heurisztika: TLD alapján (hu/sk/cz/ro…)
        var tld = (a.hostname.split('.').pop()||'').toUpperCase();
        qs.utm_campaign = tld || 'HU';
      }

      // Dognet AFF paramok a CSV-ből (PHP-ból kaptuk)
      Object.keys(ARU_AFF||{}).forEach(function(k){
        qs[k] = ARU_AFF[k];
        if (k==='chid' && !qs['chan']) qs['chan'] = ARU_AFF[k];
      });

      // data1 (aktuális oldal URL-ből)
      if (d1) qs.data1 = d1;

      var base = a.protocol + '//' + a.host + a.pathname;
      var query = Object.keys(qs).map(function(k){
        return encodeURIComponent(k)+'='+encodeURIComponent(qs[k]);
      }).join('&');
      return base + (query ? '?'+query : '') + (a.hash || '');
    }

    // d1 az aktuális oldal URL-jéből (ha van)
    var pageQS = parseQS(location.href);
    var D1 = pageQS.d1 || '';

    document.addEventListener('click', function(ev){
      var el = ev.target;
      // <a> felé lépkedünk
      while (el && el.nodeType===1 && el.tagName!=='A') el = el.parentElement;
      if (!el || el.tagName!=='A') return;

      var href = el.getAttribute('href') || '';
      if (!href || href.indexOf(FILLOUT)!==0) return;

      // csak Árukereső: shop=arukereso kötelező
      var q = parseQS(href);
      if (!q.shop || q.shop.toLowerCase()!=='arukereso') return;

      var u = q.u || '';
      if (!u) return;

      // base64 bontás (a GAS így küldi)
      var decoded = base64Try(u);
      if (!decoded) decoded = u; // ha mégsem b64, próbáljuk meg raw-ként

      decoded = unEntity(decoded);

      // végső Árukereső tracking URL felépítése
      var finalUrl = buildAruTracked(decoded, D1);
      if (finalUrl && /^https?:\/\//i.test(finalUrl)) {
        ev.preventDefault();
        ev.stopPropagation();
        window.location.href = finalUrl;
      }
    }, true);
  }catch(_){}
})();
</script>
  <?php
}, 99);