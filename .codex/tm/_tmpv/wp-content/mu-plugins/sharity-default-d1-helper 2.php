<?php
/**
 * Sharity – default_d1 helper (MU plugin)
 * - Front: átírja a ?shop=… és /go/… linkeket /go/{shop}?d1=… formára
 * - Back: nagyon korán pótolja a d1-et /go hívásnál
 * - Adatforrás: 1) impactshop_get_shops(), 2) Publish-to-web CSV
 * UTF-8 BOM nélkül; NINCS záró "?>"
 */

/** 1) ÁLLÍTSD BE a publikált Shops CSV URL-t (shop_slug + default_d1 oszlopok!) */
if (!defined('SHARITY_SHOPS_PUB_CSV')) {
  define('SHARITY_SHOPS_PUB_CSV', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv');
}

/** 2) (OPCIONÁLIS, de erősen ajánlott) Globális fallback NGO – ha egy shop nincs a táblában */
if (!defined('SHARITY_GLOBAL_DEFAULT_D1')) {
  define('SHARITY_GLOBAL_DEFAULT_D1', 'bator-tabor-alapitvany');  // ← ide írd a “site-wide” alap NGO slugot
}

/** ---- Segédek ---- */
function sh_d1_sanitize_slug($s){ return strtolower(preg_replace('~[^a-z0-9\-]+~','-', (string)$s)); }
function sh_d1_is_front(){ return !is_admin() && !wp_doing_ajax() && !wp_doing_cron(); }

/** Shops → [shop_slug => default_d1] (5 perces cache) */
function sh_d1_map(){
  $key='sharity_default_d1_map_v3';
  $m=get_transient($key);
  if(is_array($m)) return $m;
  $m=[];

  // 1) impactshop_get_shops() – ha elérhető
  if(function_exists('impactshop_get_shops')) {
    try{
      $rows=impactshop_get_shops();
      if(is_array($rows)) foreach($rows as $r){
        $slug = isset($r['shop_slug']) ? sh_d1_sanitize_slug($r['shop_slug']) : '';
        $d1   = isset($r['default_d1']) ? sh_d1_sanitize_slug($r['default_d1']) : '';
        if($slug && $d1) $m[$slug]=$d1;
      }
    }catch(\Throwable $e){}
  }

  // 2) CSV fallback (WP HTTP API)
  if(!$m && SHARITY_SHOPS_PUB_CSV){
    $res = wp_remote_get(SHARITY_SHOPS_PUB_CSV, ['timeout'=>5, 'redirection'=>3]);
    if(!is_wp_error($res)){
      $body = wp_remote_retrieve_body($res);
      if($body){
        $lines = preg_split("/\r\n|\r|\n/", trim($body));
        $header = [];
        foreach($lines as $i=>$ln){
          if($ln==='') continue;
          $cols = str_getcsv($ln);
          if(!$i){ $header=$cols; continue; }
          $rec=[];
          foreach($cols as $j=>$val){ $k=isset($header[$j])?trim($header[$j]):('c'.$j); $rec[$k]=$val; }
          $slug = isset($rec['shop_slug']) ? sh_d1_sanitize_slug($rec['shop_slug']) : '';
          $d1   = isset($rec['default_d1']) ? sh_d1_sanitize_slug($rec['default_d1']) : '';
          if($slug && $d1) $m[$slug]=$d1;
        }
      }
    }
  }

  set_transient($key,$m,5*MINUTE_IN_SECONDS);
  return $m;
}
function sh_d1_for($shop){
  $shop = sh_d1_sanitize_slug($shop);
  if(!$shop) return '';
  $m=sh_d1_map();
  if(isset($m[$shop])) return $m[$shop];
  $fallback = trim((string)SHARITY_GLOBAL_DEFAULT_D1);
  return $fallback ? sh_d1_sanitize_slug($fallback) : '';
}

/** ---- BACKEND vészfék: pótoljuk a d1-et nagyon korán ---- */
function sh_d1_maybe_redirect_backend(){
  if(!sh_d1_is_front()) return;
  $req = $_SERVER['REQUEST_URI'] ?? '';
  if(!$req) return;

  // /go/{slug} | /go-deal/{slug} | ?shop={slug}
  $shop = null;
  if(preg_match('~/(?:go|go-deal)/([^/?#]+)~', $req, $m)) $shop = $m[1];
  if(!$shop && isset($_GET['shop'])) $shop = (string)$_GET['shop'];
  if(!$shop || isset($_GET['d1'])) return;

  $d1 = sh_d1_for($shop);
  if(!$d1) return; // nincs mit pótolni

  // új URL ugyanide, kiegészítve d1-el + src default
  $scheme = is_ssl() ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'];
  $parts  = wp_parse_url("$scheme://$host$req");
  $q = [];
  if(!empty($parts['query'])) parse_str($parts['query'], $q);
  $q['d1'] = $d1;
  if(empty($q['src'])) $q['src']='impactshop';
  $path = $parts['path'] ?? '/';
  $new  = $scheme.'://'.$host.$path.($q?('?'.http_build_query($q)):'');
  wp_safe_redirect($new, 302);
  exit;
}
add_action('init', 'sh_d1_maybe_redirect_backend', 0);
add_action('template_redirect', 'sh_d1_maybe_redirect_backend', 0);

/** ---- FRONTEND átírás: ?shop=… → /go/{shop}?…  +  /go/{shop}?… d1 pótlás
 *  + MutationObserver, hogy Elementor/lazy load alatt is átírja az újonnan bekerült linkeket
 */
add_action('wp_footer', function(){
  if(!sh_d1_is_front()) return;
  $map = sh_d1_map(); // kell a JSON-hez; fallback-et a back-end úgyis megoldja
  $go_abs  = trailingslashit( home_url('/go/') );
  $jsonMap = wp_json_encode($map);
  ?>
  <script>
  (function(){
    var MAP   = <?php echo $jsonMap ?> || {};
    var GOABS = <?php echo json_encode($go_abs); ?>;

    function slug(s){ return (s||'').toLowerCase().replace(/[^a-z0-9\-]+/g,'-'); }
    function rewrite(a){
      try{
        var href=a.getAttribute('href'); if(!href) return;
        var url=new URL(href, document.baseURI);

        // 1) ?shop=… → /go/{shop}?… (mindig építsük újra a PATH-ot)
        var shop = slug(url.searchParams.get('shop')||'');
        if(shop){
          var d1=url.searchParams.get('d1')||MAP[shop]||'';
          var newUrl=new URL(GOABS+encodeURIComponent(shop), document.baseURI);
          url.searchParams.forEach(function(v,k){ newUrl.searchParams.set(k,v); });
          if(d1) newUrl.searchParams.set('d1', d1);
          if(!newUrl.searchParams.get('src')) newUrl.searchParams.set('src','impactshop');
          a.setAttribute('href', newUrl.toString());
          a.setAttribute('rel','noopener');
          return;
        }

        // 2) /go/{shop} abszolút vagy relatív – ha nincs d1, pótoljuk
        var isGo = (url.href.indexOf(GOABS)===0) || (url.pathname.indexOf('/go/')===0);
        if(isGo && !url.searchParams.get('d1')){
          var parts = url.pathname.split('/');
          var s = slug(parts.pop() || parts.pop()); // utolsó nem üres
          var d1 = MAP[s] || '';
          if(d1){ url.searchParams.set('d1', d1); }
          if(!url.searchParams.get('src')) url.searchParams.set('src','impactshop');
          a.setAttribute('href', url.toString());
          a.setAttribute('rel','noopener');
        }
      }catch(e){}
    }

    function scan(root){
      (root||document).querySelectorAll('a[href*="?shop="],a[href^="/go/"],a[href^="'+GOABS+'"],a[data-shop]')
        .forEach(rewrite);
    }

    // Első futás
    if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', function(){ scan(); }); }
    else { scan(); }

    // Figyeljük a DOM változását (Elementor widgetek betöltése után is működjön)
    var mo=new MutationObserver(function(ms){ for(var i=0;i<ms.length;i++){ for(var j=0;j<ms[i].addedNodes.length;j++){ var n=ms[i].addedNodes[j]; if(n.nodeType===1) scan(n); } } });
    mo.observe(document.documentElement,{childList:true,subtree:true});
  })();
  </script>
  <?php
}, 200);