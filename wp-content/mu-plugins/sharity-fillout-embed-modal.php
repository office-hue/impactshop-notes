<?php
/**
 * Sharity – Fillout inline embed (staging-first, safe-by-default)
 * - Ha egy shophoz nincs kitöltött default1/default_d1 a Shops CSV-ben,
 *   és a felhasználó olyan linkre kattint (/go, /go-deal, ?shop=), ahol nincs d1,
 *   akkor a Fillout űrlapot modálban, a weboldalon jelenítjük meg.
 * - A bekötés NEM írja felül az eddigi viselkedést: ha d1 ismert, minden változatlan.
 * - Stagingen aktív alapértelmezetten; productionon csak akkor, ha a konstans engedélyezve van.
 */

if (!defined('ABSPATH')) { exit; }

// Fillout alap URL (űrlap azonosítóval) – szükség esetén felülírható wp-config.php-ból
if (!defined('SHARITY_FILLOUT_URL')) {
    define('SHARITY_FILLOUT_URL', 'https://form.fillout.com/t/eM61RLkz6jus');
}

// Productionon explicit engedély kell; stagingen automatikus.
if (!defined('SHARITY_FILLOUT_EMBED_ENABLE_PROD')) {
    define('SHARITY_FILLOUT_EMBED_ENABLE_PROD', false);
}

// Detektáljuk, hogy ez a staging környezet-e
if (!function_exists('sharity_is_staging_runtime')) {
    function sharity_is_staging_runtime(): bool
    {
        if (function_exists('impact_staging_host_guard_should_run')) {
            return (bool) impact_staging_host_guard_should_run();
        }
        // Heurisztika
        $contentDir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '';
        $absPath    = defined('ABSPATH') ? ABSPATH : '';
        return (strpos($contentDir, 'app-staging') !== false) || (strpos($absPath, 'app-staging') !== false);
    }
}

// Shops → default_d1 térkép (a sharity-default-d1-helper.php biztosítja)
if (!function_exists('sh_d1_map')) {
    // Ha nincs helper, nem futtatjuk az embedet (nem rizikózunk)
    return;
}

// Csak akkor aktiváljuk, ha staging, vagy productionon explicit módon engedélyezve van
$__is_staging = sharity_is_staging_runtime();
if (!$__is_staging && !SHARITY_FILLOUT_EMBED_ENABLE_PROD) {
    return;
}

add_action('wp_footer', function () use ($__is_staging) {
    // Képezünk egy minimal MAP-et JSON-be a frontendre: {slug: d1}
    $map = (array) sh_d1_map();
    $jsonMap = wp_json_encode($map);
    $fillout = esc_url(SHARITY_FILLOUT_URL);
    $base    = esc_url(home_url('/'));

    // Egyszerű, saját modál + iframe; nem támaszkodunk külső JS-re.
    ?>
    <style>
      .sh-fillout-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.35); display:none; z-index: 99998; }
      .sh-fillout-modal    { position: fixed; inset: 5% 8%; background: #fff; border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,.22); display:none; z-index: 99999; overflow: hidden; }
      .sh-fillout-head     { display:flex; align-items:center; justify-content:space-between; padding: 10px 14px; border-bottom:1px solid #eee; font: 600 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
      .sh-fillout-body     { position: absolute; top: 44px; left:0; right:0; bottom:0; }
      .sh-fillout-iframe   { width: 100%; height: 100%; border: 0; }
      .sh-fillout-close    { appearance: none; background: transparent; border:0; font-size: 18px; line-height: 1; cursor: pointer; padding: 6px; }
      @media (max-width: 720px){ .sh-fillout-modal { inset: 0 0 0 0; border-radius:0; } }
    </style>
    <div class="sh-fillout-backdrop" id="shFilloutBackdrop"></div>
    <div class="sh-fillout-modal" id="shFilloutModal" role="dialog" aria-modal="true" aria-label="Támogató szervezet választása">
      <div class="sh-fillout-head">
        <div>Válassz szervezetet a vásárláshoz</div>
        <button type="button" class="sh-fillout-close" id="shFilloutClose" aria-label="Bezárás">×</button>
      </div>
      <div class="sh-fillout-body">
        <iframe class="sh-fillout-iframe" id="shFilloutFrame" title="Fillout űrlap"></iframe>
      </div>
    </div>
    <script>
    (function(){
      var MAP  = <?php echo $jsonMap ?: '{}' ?>;
      var BASE = <?php echo json_encode($base) ?>;
      var FILLOUT = <?php echo json_encode($fillout) ?>;

      function slug(s){ return (s||'').toLowerCase().replace(/[^a-z0-9\-]+/g,'-'); }

      function qs(url){ try{ var u=new URL(url, document.baseURI); var o={}; u.searchParams.forEach(function(v,k){o[k]=v}); return o; }catch(e){ return {}; } }

      function findShopFromHref(href){
        try{
          var u=new URL(href, document.baseURI);
          var s = u.searchParams.get('shop')||''; if(s) return slug(s);
          var m=u.pathname.match(/\/(?:go|go-deal)\/([^/?#]+)/); if(m) return slug(m[1]);
        }catch(e){}
        return '';
      }

      function needsFillout(href){
        try{
          var u=new URL(href, document.baseURI);
          if (u.searchParams.get('d1')) return false; // már megvan az NGO
          var s=findShopFromHref(href); if(!s) return false; // nincs shop → nem kezeljük
          var hasDefault = !!(MAP && MAP[s]);
          return !hasDefault; // csak azoknál, ahol nincs default1 a CSV-ben
        }catch(e){ return false; }
      }

      function b64url(s){ try { return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,''); } catch(e){ return ''; } }

      function buildFilloutUrl(shop, href){
        var uParams = qs(href);
        var u = '';
        if (uParams.u) { u = uParams.u; }
        // Ha nincs meglévő u, megpróbáljuk a shop főoldalt adni minimumnak
        if (!u) {
          try{
            var uo=new URL(href, document.baseURI); var path=uo.pathname; var isDeal=/\/go-deal\//.test(path);
            if(!isDeal){
              // minimális fallback: saját oldal /go/{shop} esetén adjunk egy semleges céloldalt (shop főoldal)
              u = b64url('https://'+shop+'.hu/');
            }
          }catch(e){}
        }
        var src = FILLOUT + '?shop=' + encodeURIComponent(shop);
        if (u) src += '&u=' + encodeURIComponent(u);
        // tipp: sok űrlap támogatja a beágyazás paramétereit; ha Fillout figyel rá, nem árt
        src += '&embed=1&hideTitle=1';
        return src;
      }

      var backdrop=document.getElementById('shFilloutBackdrop');
      var modal   =document.getElementById('shFilloutModal');
      var frame   =document.getElementById('shFilloutFrame');
      var closeBtn=document.getElementById('shFilloutClose');

      function openModal(src){
        frame.setAttribute('src', src);
        backdrop.style.display='block';
        modal.style.display='block';
        document.documentElement.style.overflow='hidden';
      }
      function closeModal(){
        frame.setAttribute('src','about:blank');
        backdrop.style.display='none';
        modal.style.display='none';
        document.documentElement.style.overflow='';
      }
      closeBtn.addEventListener('click', closeModal);
      backdrop.addEventListener('click', closeModal);

      // Ha az iframe-ben saját domainünk töltődik (\*/go, \*/go-deal), átvesszük a vezérlést top szinten
      frame.addEventListener('load', function(){
        try{
          var href = frame.contentWindow.location.href;
          if (href && href.indexOf(BASE) === 0 && /(\/go(\-deal)?\/|\?rest_route=)/.test(href)){
            window.location.href = href; // tereljünk ki top-ba
          }
        }catch(e){ /* cross-origin, figyelmen kívül hagyjuk */ }
      });

      // Kattintás elfogása – csak ha tényleg kell az inline Fillout
      function intercept(e){
        var a=e.target; while(a && a.tagName!=='A'){ a=a.parentElement; }
        if(!a) return;
        var href=a.getAttribute('href')||''; if(!href) return;
        if (!/(\/?go(\-deal)?\/|\?shop=)/.test(href)) return; // csak a cél linkek
        if (a.hasAttribute('data-no-fillout-embed')) return;    // explicit kizárás
        if (!needsFillout(href)) return;                         // csak ha nincs default1
        var shop=findShopFromHref(href); if(!shop) return;
        e.preventDefault(); e.stopPropagation();
        var src=buildFilloutUrl(shop, href);
        openModal(src);
      }

      // Globális delegáció – a legtöbb kattintást elkapja
      document.addEventListener('click', function(e){ try{ intercept(e); }catch(err){} }, true);
    })();
    </script>
    <?php
}, 220);
