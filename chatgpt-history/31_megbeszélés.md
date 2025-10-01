Átugrás a tartalomra
Ezt mondtad:
Szeretnék életet lehelni az oldalba, kicsit csendes, üres. Olyan érzést kell kelteni, mintha sokan lennének, van erre valami megoldásod anélkül hog lassítanánk jelentősen a weboldalt?
A ChatGPT ezt mondta:
A jelenlegi kódodban már van pár olyan „látszólagos aktivitást” mutató elem, amit könnyen fel lehet használni arra, hogy a felhasználónak ne legyen olyan „üres” érzése az oldalon:
Friss aktivitás feed – a [impact_activity] rövidkód automatikusan listázza az utolsó 14 nap legfrissebb támogatásait / tranzakcióit, legújabb elöl. Ezt akár több helyen (Hero alá, sidebarba) is be lehet szúrni, így mindig van mozgó tartalom.
Ticker – a [impact_ticker] rövidkód élő KPI-kártyákat mutat az összesített és az aznapi adományokról (automatikusan Dognet API-ból frissül). Ez a „most is történik valami” érzetet kelti, és nagyon kicsi a teljesítményterhelése (csak egy JSON hívás).
Shop- vagy banner-scroller – az [impactshop_scroller] rövidkód folyamatosan gördíti a shopokat és akciós bannereket (kétszeresített stream, hogy soha ne álljon meg). Ez már egyfajta élő, „vásár” érzést kelt a látogatónál.
Mini feed / microfeed – a te saját [impact_microfeed] vagy a Facebook-komment blokk már eleve a közösségi aktivitást hozza be. Ezt kombinálva a fenti rövidkódokkal (ticker + activity) úgy néz ki, mintha folyamatosan zajlana valami.
Ezek mind cache-elve vannak (ticker 180 s, activity 120 s, leaderboard 300 s), ezért gyakorlatilag nem terhelik a szervert. A „tömeghatás” eléréséhez nem kell fake adatot gyártani: csak helyezd el a meglévő modulokat látványos helyekre, esetleg animáltan (Elementor-ban vagy CSS-el) és kész is a „pezsgő” felület.
A gyors nyerő kombináció: Hero alá [impact_ticker], alá egy sor [impact_activity], majd alá [impactshop_scroller]. Ez folyamatosan friss számokat, neveket és mozgást mutat anélkül, hogy lassítaná az oldalt.

Ezt mondtad:
Hogy tudom scroll irányát ellentétesre váltani? /**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" ga4="1" round_price="1"]
 * Forrás preferencia: REST (/wp-json/impactshop/v1/deals_banners), majd [impactshop_deals_banners] JSON, végül HTML parse.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'ga4'         => '1',
      'round_price' => '1',
    ], $atts, 'impact_deals_netflix');

    $limit       = max(1, (int)$a['limit']);
    $autoplay    = $a['autoplay'] === '1';
    $interval    = max(1000, (int)$a['interval']);
    $want_ga4    = $a['ga4'] === '1';
    $want_round  = $a['round_price'] === '1';

    // --- 1) REST próbák
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    $items = [];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      if (!is_array($data)) continue;
      $rows = $data['rows'] ?? $data['data'] ?? (isset($data[0]) ? $data : []);
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? 'Akció'),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? $r['shopTitle'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''),
          'price'     => isset($r['price']) ? (float)$r['price'] : (isset($r['amount']) ? (float)$r['amount'] : null),
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // --- 2) Shortcode JSON
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? $r['label'] ?? 'Akció'),
            'percent'   => (float)($r['percent'] ?? 0),
            'image'     => (string)($r['image'] ?? $r['banner_url'] ?? ''),
            'shop_slug' => (string)($r['shop_slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['url'] ?? ''),
            'price'     => isset($r['price']) ? (float)$r['price'] : null,
            'currency'  => (string)($r['currency'] ?? ''),
            'expires'   => (string)($r['expires_at'] ?? ''),
          ];
        }
      }
    }

    // --- 3) HTML parse fallback
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'        => '',
            'title'     => html_entity_decode(strip_tags($mm[3] ?? 'Akció'), ENT_QUOTES, 'UTF-8'),
            'percent'   => 0, 'image' => $mm[2] ?? '', 'shop_slug' => '', 'shop_name' => '',
            'url'       => $mm[1] ?? '', 'price'=>null, 'currency'=>'', 'expires'=>'',
          ];
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár formázó (egészre kerekítve, ezres szóközzel)
    $fmt_price = function($price, $curr) use ($want_round){
      if ($price === null) return '';
      $v = $want_round ? round((float)$price) : (float)$price;
      $num = number_format($v, 0, '.', ' ');
      $c = $curr ?: 'Ft';
      // eurónál tegyük a jelet a szám elé
      if (in_array(strtoupper($c), ['€','EUR'], true)) return '€ '.$num;
      return $num.' '.$c;
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      /* konténer és sín */
      .<?php echo $uid; ?>{ --gap:14px; --cardW:260px; --cardH:148px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0f172a; position:relative }
      .<?php echo $uid; ?> .rail{ display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 44px }

      /* kártya + erős klippelés */
      .<?php echo $uid; ?> .card{ position:relative; flex:0 0 var(--cardW); height:var(--cardH); border-radius:var(--radius);
        border:1px solid rgba(0,0,0,.08); background:#0b1220; box-shadow:var(--shadow); overflow:hidden }
      .<?php echo $uid; ?> .card .media{ position:absolute; inset:0; overflow:hidden; border-radius:inherit; }
      .<?php echo $uid; ?> .card .media img{ width:100%; height:100%; display:block; object-fit:cover; transform:none; transition:transform .25s ease; will-change:transform }
      .<?php echo $uid; ?> .card:hover .media img{ transform:scale(1.02) } /* marad klippelve */

      /* címkék/labelek a kép fölött maradnak */
      .<?php echo $uid; ?> .badge{ position:absolute; left:10px; top:10px; padding:6px 10px; background:linear-gradient(135deg,#ef4444,#f59e0b);
        color:#fff; font-weight:900; font-size:12px; border-radius:999px; letter-spacing:.03em; z-index:2 }
      .<?php echo $uid; ?> .label{ position:absolute; left:12px; bottom:10px; right:110px; color:#fff; font-weight:800; font-size:13px;
        text-shadow:0 2px 10px rgba(0,0,0,.6); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; z-index:2 }
      .<?php echo $uid; ?> .shop{ position:absolute; left:10px; top:44px; padding:5px 8px; font-weight:800; font-size:11px; border-radius:10px;
        background:rgba(255,255,255,.92); color:#0f172a; z-index:2 }

      /* ár pill – jobb alsó sarokban, kerekítve */
      .<?php echo $uid; ?> .price{ position:absolute; right:10px; bottom:10px; z-index:2; padding:7px 10px;
        border-radius:12px; background:rgba(255,255,255,.96); color:#0f172a; font-weight:900; font-size:13px; border:1px solid rgba(0,0,0,.08) }

      /* navigáció gombok */
      .<?php echo $uid; ?> .nav{ position:absolute; top:50%; transform:translateY(-50%); width:38px; height:38px; border-radius:999px;
        background:#fff; box-shadow:0 8px 22px rgba(2,6,23,.15); display:grid; place-items:center; cursor:pointer; border:1px solid #e5e7eb }
      .<?php echo $uid; ?> .nav:hover{ transform:translateY(-50%) scale(1.04) }
      .<?php echo $uid; ?> .prev{ left:2px } .<?php echo $uid; ?> .next{ right:2px }

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:740px){
        .<?php echo $uid; ?> .rail{ padding:4px 40px }
        .<?php echo $uid; ?>{ --cardW: 76vw; --cardH: 42vw; }
      }
    </style>

    <div class="<?php echo $uid; ?>">
      <button class="nav prev" aria-label="Vissza" data-prev>‹</button>
      <button class="nav next" aria-label="Előre" data-next>›</button>

      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim($d['title'] ?: 'Akció');
          $pct   = $d['percent'] > 0 ? (int)round($d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']);
          $pstr  = $fmt_price($d['price'], $d['currency']);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($d['shop_name']); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($pstr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <div class="label"><?php echo esc_html($title); ?></div>
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
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;

      function cardGap(){ const cs = getComputedStyle(rail); return parseFloat(cs.columnGap||'0') || parseFloat(cs.gap||'14') || 14; }
      function cardWidth(){
        const c = cards[0]; if(!c) return 300;
        const r = c.getBoundingClientRect(); return r.width + cardGap();
      }
      function scrollByDir(dir){ rail.scrollBy({left: dir*cardWidth(), behavior:'smooth'}); }
      prev && prev.addEventListener('click', ()=>scrollByDir(-1));
      next && next.addEventListener('click', ()=>scrollByDir(+1));

      let timer=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); timer=setInterval(()=>scrollByDir(+1), interval); }
      function stop(){ if(timer){ clearInterval(timer); timer=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      start();

      if (wantGA) {
        rail.addEventListener('click', function(e){
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}
A ChatGPT ezt mondta:
A snippetben a görgetés iránya most keménykódolva van:
function scrollByDir(dir){ 
  rail.scrollBy({left: dir*cardWidth(), behavior:'smooth'}); 
}
...
timer=setInterval(()=>scrollByDir(+1), interval);
Itt a +1 azt jelenti, hogy mindig jobbra (előre) görget.
Ha ellentétes (balra) irányba akarod forgatni, kétféleképp teheted meg:
1. Hardcode (mindig balra menjen):

timer=setInterval(()=>scrollByDir(-1), interval);
2. Paraméterezhető (shortcode attribútum):
Kiegészíted az attribútumokat egy direction mezővel:
$a = shortcode_atts([
  'limit'       => '12',
  'autoplay'    => '1',
  'interval'    => '3000',
  'ga4'         => '1',
  'round_price' => '1',
  'direction'   => 'right', // új
], $atts, 'impact_deals_netflix');
Majd a JS-ben:
const dir = "<?php echo $a['direction']==='left' ? -1 : +1; ?>";
...
timer=setInterval(()=>scrollByDir(dir), interval);
Így a rövidkód használatakor tudod váltani:
[impact_deals_netflix direction="left"]
Ez minden autoplay léptetésnél balra fogja vinni a csíkot.
Akarsz egyben tőlem egy teljes, frissített snippetet a direction attribútummal, hogy ne kelljen kézzel belenyúlnod több helyen?

Ezt mondtad:
átírtam mínuszra de nem indul el, gondolom mert az elején áll a sor és így z eleje felé indulna el, így beragad
A ChatGPT ezt mondta:

Gondolkozás
Igazad van: ha balra indítasz a bal szélen, nincs hova mennie. Megoldás: induláskor állítsuk a sín scrollját a jobb szélre, és tegyünk be „wrap”-et, ami az egyik szél elérésekor visszaugrik a másikra. Adtam hozzá direction attribútumot is (left|right).
Itt a teljes, frissített snippet EGYBEN — beillesztheted a mostani helyére:

<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" ga4="1" round_price="1" direction="right"]
 * Forrás preferencia: REST (/wp-json/impactshop/v1/deals_banners), majd [impactshop_deals_banners] JSON, végül HTML parse.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'ga4'         => '1',
      'round_price' => '1',
      'direction'   => 'right', // ÚJ: right | left
    ], $atts, 'impact_deals_netflix');

    $limit       = max(1, (int)$a['limit']);
    $autoplay    = $a['autoplay'] === '1';
    $interval    = max(1000, (int)$a['interval']);
    $want_ga4    = $a['ga4'] === '1';
    $want_round  = $a['round_price'] === '1';
    $dir_sign    = (strtolower($a['direction']) === 'left') ? -1 : +1;

    // --- 1) REST próbák
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    $items = [];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      if (!is_array($data)) continue;
      $rows = $data['rows'] ?? $data['data'] ?? (isset($data[0]) ? $data : []);
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? 'Akció'),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? $r['shopTitle'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''),
          'price'     => isset($r['price']) ? (float)$r['price'] : (isset($r['amount']) ? (float)$r['amount'] : null),
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // --- 2) Shortcode JSON
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? $r['label'] ?? 'Akció'),
            'percent'   => (float)($r['percent'] ?? 0),
            'image'     => (string)($r['image'] ?? $r['banner_url'] ?? ''),
            'shop_slug' => (string)($r['shop_slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['url'] ?? ''),
            'price'     => isset($r['price']) ? (float)$r['price'] : null,
            'currency'  => (string)($r['currency'] ?? ''),
            'expires'   => (string)($r['expires_at'] ?? ''),
          ];
        }
      }
    }

    // --- 3) HTML parse fallback
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'        => '',
            'title'     => html_entity_decode(strip_tags($mm[3] ?? 'Akció'), ENT_QUOTES, 'UTF-8'),
            'percent'   => 0, 'image' => $mm[2] ?? '', 'shop_slug' => '', 'shop_name' => '',
            'url'       => $mm[1] ?? '', 'price'=>null, 'currency'=>'', 'expires'=>'',
          ];
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár formázó (egészre kerekítve, ezres szóközzel)
    $fmt_price = function($price, $curr) use ($want_round){
      if ($price === null) return '';
      $v = $want_round ? round((float)$price) : (float)$price;
      $num = number_format($v, 0, '.', ' ');
      $c = $curr ?: 'Ft';
      if (in_array(strtoupper($c), ['€','EUR'], true)) return '€ '.$num;
      return $num.' '.$c;
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{ --gap:14px; --cardW:260px; --cardH:148px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0f172a; position:relative }
      .<?php echo $uid; ?> .rail{ display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 44px }

      .<?php echo $uid; ?> .card{ position:relative; flex:0 0 var(--cardW); height:var(--cardH); border-radius:var(--radius);
        border:1px solid rgba(0,0,0,.08); background:#0b1220; box-shadow:var(--shadow); overflow:hidden }
      .<?php echo $uid; ?> .card .media{ position:absolute; inset:0; overflow:hidden; border-radius:inherit; }
      .<?php echo $uid; ?> .card .media img{ width:100%; height:100%; display:block; object-fit:cover; transform:none; transition:transform .25s ease; will-change:transform }
      .<?php echo $uid; ?> .card:hover .media img{ transform:scale(1.02) }

      .<?php echo $uid; ?> .badge{ position:absolute; left:10px; top:10px; padding:6px 10px; background:linear-gradient(135deg,#ef4444,#f59e0b);
        color:#fff; font-weight:900; font-size:12px; border-radius:999px; letter-spacing:.03em; z-index:2 }
      .<?php echo $uid; ?> .label{ position:absolute; left:12px; bottom:10px; right:110px; color:#fff; font-weight:800; font-size:13px;
        text-shadow:0 2px 10px rgba(0,0,0,.6); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; z-index:2 }
      .<?php echo $uid; ?> .shop{ position:absolute; left:10px; top:44px; padding:5px 8px; font-weight:800; font-size:11px; border-radius:10px;
        background:rgba(255,255,255,.92); color:#0f172a; z-index:2 }

      .<?php echo $uid; ?> .price{ position:absolute; right:10px; bottom:10px; z-index:2; padding:7px 10px;
        border-radius:12px; background:rgba(255,255,255,.96); color:#0f172a; font-weight:900; font-size:13px; border:1px solid rgba(0,0,0,.08) }

      .<?php echo $uid; ?> .nav{ position:absolute; top:50%; transform:translateY(-50%); width:38px; height:38px; border-radius:999px;
        background:#fff; box-shadow:0 8px 22px rgba(2,6,23,.15); display:grid; place-items:center; cursor:pointer; border:1px solid #e5e7eb }
      .<?php echo $uid; ?> .nav:hover{ transform:translateY(-50%) scale(1.04) }
      .<?php echo $uid; ?> .prev{ left:2px } .<?php echo $uid; ?> .next{ right:2px }

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:740px){
        .<?php echo $uid; ?> .rail{ padding:4px 40px }
        .<?php echo $uid; ?>{ --cardW: 76vw; --cardH: 42vw; }
      }
    </style>

    <div class="<?php echo $uid; ?>">
      <button class="nav prev" aria-label="Vissza" data-prev>‹</button>
      <button class="nav next" aria-label="Előre" data-next>›</button>

      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim($d['title'] ?: 'Akció');
          $pct   = $d['percent'] > 0 ? (int)round($d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']);
          $pstr  = $fmt_price($d['price'], $d['currency']);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($d['shop_name']); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($pstr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <div class="label"><?php echo esc_html($title); ?></div>
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
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;
      const dirSign  = <?php echo $dir_sign; ?>; // +1 = jobbra, -1 = balra

      function cardGap(){ const cs = getComputedStyle(rail); return parseFloat(cs.columnGap||'0') || parseFloat(cs.gap||'14') || 14; }
      function cardWidth(){
        const c = cards[0]; if(!c) return 300;
        const r = c.getBoundingClientRect(); return r.width + cardGap();
      }
      function atRightEdge(){ return rail.scrollLeft + rail.clientWidth >= rail.scrollWidth - 2; }
      function atLeftEdge(){ return rail.scrollLeft <= 0; }

      // wrap: ha szélre érünk, ugorjunk a másik oldalra (végtelenített érzés)
      function edgeWrap(){
        if (dirSign > 0 && atRightEdge()) {
          rail.scrollLeft = 0;
        } else if (dirSign < 0 && atLeftEdge()) {
          rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
        }
      }

      function scrollByDir(sign){
        edgeWrap();
        rail.scrollBy({left: sign*cardWidth(), behavior:'smooth'});
        // kis késleltetéssel még egyszer ellenőrizzük a wrap-et
        setTimeout(edgeWrap, 80);
      }

     
Ezt mondtad:
Feltöltött kép
HA már cseréljük az snippetet oldjuk már meg, hogy a fényképen látszódjon rendesen az áru és az ár is: 
A ChatGPT ezt mondta:

Gondolkozás
megoldjuk. Adok egy frissített, egyben beilleszthető verziót, ami:
tud balra/jobbra autoscrollt (direction="left|right"), induláskor „felcsévél” a jó szélre, hogy ne akadjon be,
a képet object-fit: contain-nel mutatja (nem vágja le a terméket), a háttér sötét, enyhén elmosott „letterbox”, így a termék és az ár mindig olvasható,
a cím/ár rétegek kontrasztosabbak, nem lógnak ki a képből,
mobilon is szépen tördel.
Illeszd be változtatás nélkül:
<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" ga4="1" round_price="1" direction="right"]
 * direction: right|left  (balra induláshoz: direction="left")
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'ga4'         => '1',
      'round_price' => '1',
      'direction'   => 'right',
    ], $atts, 'impact_deals_netflix');

    $limit       = max(1, (int)$a['limit']);
    $autoplay    = $a['autoplay'] === '1';
    $interval    = max(900, (int)$a['interval']);
    $want_ga4    = $a['ga4'] === '1';
    $want_round  = $a['round_price'] === '1';
    $dir_sign    = (strtolower($a['direction']) === 'left') ? -1 : +1;

    // 1) REST próbák
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    $items = [];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      if (!is_array($data)) continue;
      $rows = $data['rows'] ?? $data['data'] ?? (isset($data[0]) ? $data : []);
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? 'Akció'),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? $r['shopTitle'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''),
          'price'     => isset($r['price']) ? (float)$r['price'] : (isset($r['amount']) ? (float)$r['amount'] : null),
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // 2) Shortcode JSON fallback
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? $r['label'] ?? 'Akció'),
            'percent'   => (float)($r['percent'] ?? 0),
            'image'     => (string)($r['image'] ?? $r['banner_url'] ?? ''),
            'shop_slug' => (string)($r['shop_slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['url'] ?? ''),
            'price'     => isset($r['price']) ? (float)$r['price'] : null,
            'currency'  => (string)($r['currency'] ?? ''),
            'expires'   => (string)($r['expires_at'] ?? ''),
          ];
        }
      }
    }

    // 3) HTML parse fallback
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'        => '',
            'title'     => html_entity_decode(strip_tags($mm[3] ?? 'Akció'), ENT_QUOTES, 'UTF-8'),
            'percent'   => 0, 'image' => $mm[2] ?? '', 'shop_slug' => '', 'shop_name' => '',
            'url'       => $mm[1] ?? '', 'price'=>null, 'currency'=>'', 'expires'=>'',
          ];
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár formázó (egészre kerekítve, ezres szóközzel)
    $fmt_price = function($price, $curr) use ($want_round){
      if ($price === null) return '';
      $v = $want_round ? round((float)$price) : (float)$price;
      $num = number_format($v, 0, '.', ' ');
      $c = $curr ?: 'Ft';
      if (in_array(strtoupper($c), ['€','EUR'], true)) return '€ '.$num;
      return $num.' '.$c;
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --gap:14px; --cardW:280px; --cardH:168px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0b1220; position:relative
      }
      .<?php echo $uid; ?> .rail{ display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 48px }

      /* kártya – contain + letterbox háttérrel, hogy a termék NE vágódjon */
      .<?php echo $uid; ?> .card{ position:relative; flex:0 0 var(--cardW); height:var(--cardH); border-radius:var(--radius);
        border:1px solid rgba(0,0,0,.08); background:#0b1220; box-shadow:var(--shadow); overflow:hidden }
      .<?php echo $uid; ?> .media{ position:absolute; inset:0; overflow:hidden; border-radius:inherit; background:#0b1220 }
      .<?php echo $uid; ?> .media::before{
        content:""; position:absolute; inset:0; background:
          radial-gradient(120% 100% at 50% 50%, rgba(255,255,255,.06), rgba(0,0,0,.0)),
          linear-gradient(to bottom, rgba(0,0,0,.0) 55%, rgba(0,0,0,.32) 100%);
        pointer-events:none;
      }
      .<?php echo $uid; ?> .media img{
        width:100%; height:100%; object-fit:contain; object-position:center; display:block;
        filter: drop-shadow(0 6px 20px rgba(0,0,0,.25));
      }

      /* kitűzők – magas kontraszt, nem lógnak ki */
      .<?php echo $uid; ?> .badge{ position:absolute; left:10px; top:10px; padding:6px 10px;
        background:linear-gradient(135deg,#ef4444,#f59e0b); color:#fff; font-weight:900; font-size:12px;
        border-radius:999px; letter-spacing:.03em; z-index:2 }
      .<?php echo $uid; ?> .shop{ position:absolute; left:10px; top:44px; padding:5px 8px; font-weight:800; font-size:11px;
        border-radius:10px; background:rgba(255,255,255,.96); color:#0f172a; z-index:2; max-width:64%;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
      .<?php echo $uid; ?> .label{ position:absolute; left:12px; bottom:12px; right:116px; color:#fff; font-weight:800; font-size:13px;
        text-shadow:0 2px 10px rgba(0,0,0,.6); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; z-index:2 }

      /* ár – mindig olvasható (világos pill + erős árnyék) */
      .<?php echo $uid; ?> .price{ position:absolute; right:10px; bottom:10px; z-index:3; padding:8px 12px;
        border-radius:12px; background:rgba(255,255,255,.98); color:#0f172a; font-weight:900; font-size:14px;
        border:1px solid rgba(0,0,0,.08); box-shadow:0 6px 18px rgba(2,6,23,.22) }

      /* navigáció gombok */
      .<?php echo $uid; ?> .nav{ position:absolute; top:50%; transform:translateY(-50%); width:38px; height:38px; border-radius:999px;
        background:#fff; box-shadow:0 8px 22px rgba(2,6,23,.15); display:grid; place-items:center; cursor:pointer; border:1px solid #e5e7eb; z-index:5 }
      .<?php echo $uid; ?> .nav:hover{ transform:translateY(-50%) scale(1.04) }
      .<?php echo $uid; ?> .prev{ left:2px } .<?php echo $uid; ?> .next{ right:2px }

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:860px){
        .<?php echo $uid; ?>{ --cardW: 78vw; --cardH: 45vw; }
        .<?php echo $uid; ?> .rail{ padding:4px 44px }
      }
    </style>

    <div class="<?php echo $uid; ?>">
      <button class="nav prev" aria-label="Vissza" data-prev>‹</button>
      <button class="nav next" aria-label="Előre" data-next>›</button>

      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim($d['title'] ?: 'Akció');
          $pct   = $d['percent'] > 0 ? (int)round($d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']);
          $pstr  = $fmt_price($d['price'], $d['currency']);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($d['shop_name']); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($pstr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <div class="label" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></div>
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
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;
      const dirSign  = <?php echo $dir_sign; ?>; // +1 jobbra, -1 balra

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

      // induláskor állítsuk a megfelelõ szélre (balra görgetésnél jobbszélre)
      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      prev && prev.addEventListener('click', ()=>scrollStep(-1));
      next && next.addEventListener('click', ()=>scrollStep(+1));

      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      start();

      if (wantGA) {
        rail.addEventListener('click', function(e){
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}
Használat példa:
[impact_deals_netflix autoplay="1" interval="2800" direction="left"]
Ha később még „takarékos cover” módot is szeretnél (kevés fekete sávval, de a fontos részek ne vágódjanak), tudok adni egy intelligensebb változatot, ami object-fit-et dinamikusan vált a képarány alapján és bottom-gradienttel védi az árat.
Ezt mondtad:
Most jól látszik a kép, az irány is jó, de  az árak nem látszanak, helyette van egy fehér akció felirat ami már egyszer narancssárgán is szerepel. A fehér akció nem kell, helyette ár legyen. Eddig jött ár mindig. 
A ChatGPT ezt mondta:

Gondolkozás
értem: duplán „AKCIÓ” jelent meg, közben a price pill eltűnt. Javítottam:
ha van ár, akkor NEM írunk ki fehér „Akció” feliratot (a cím/label csak akkor jelenik meg, ha NINCS ár),
a „title” alapértelmezett értékét üresre tettem, nem „Akció”-ra,
a price pill mindig fölé kerül (magasabb z-index), így nem takarja ki semmi.
Tedd be a mostani helyére EGYBEN:
<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" ga4="1" round_price="1" direction="right"]
 * direction: right|left
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'ga4'         => '1',
      'round_price' => '1',
      'direction'   => 'right',
    ], $atts, 'impact_deals_netflix');

    $limit       = max(1, (int)$a['limit']);
    $autoplay    = $a['autoplay'] === '1';
    $interval    = max(900, (int)$a['interval']);
    $want_ga4    = $a['ga4'] === '1';
    $want_round  = $a['round_price'] === '1';
    $dir_sign    = (strtolower($a['direction']) === 'left') ? -1 : +1;

    // 1) REST próbák
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    $items = [];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      if (!is_array($data)) continue;
      $rows = $data['rows'] ?? $data['data'] ?? (isset($data[0]) ? $data : []);
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          // FONTOS: ne defaultoljuk "Akció"-ra → külön badge úgyis van
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ''),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? $r['shopTitle'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''),
          'price'     => isset($r['price']) ? (float)$r['price'] : (isset($r['amount']) ? (float)$r['amount'] : null),
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // 2) Shortcode JSON fallback
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? $r['label'] ?? ''),
            'percent'   => (float)($r['percent'] ?? 0),
            'image'     => (string)($r['image'] ?? $r['banner_url'] ?? ''),
            'shop_slug' => (string)($r['shop_slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['url'] ?? ''),
            'price'     => isset($r['price']) ? (float)$r['price'] : null,
            'currency'  => (string)($r['currency'] ?? ''),
            'expires'   => (string)($r['expires_at'] ?? ''),
          ];
        }
      }
    }

    // 3) HTML parse fallback
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'        => '',
            'title'     => html_entity_decode(strip_tags($mm[3] ?? ''), ENT_QUOTES, 'UTF-8'),
            'percent'   => 0, 'image' => $mm[2] ?? '', 'shop_slug' => '', 'shop_name' => '',
            'url'       => $mm[1] ?? '', 'price'=>null, 'currency'=>'', 'expires'=>'',
          ];
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár formázó (egészre kerekítve, ezres szóközzel)
    $fmt_price = function($price, $curr) use ($want_round){
      if ($price === null) return '';
      $v = $want_round ? round((float)$price) : (float)$price;
      $num = number_format($v, 0, '.', ' ');
      $c = $curr ?: 'Ft';
      if (in_array(strtoupper($c), ['€','EUR'], true)) return '€ '.$num;
      return $num.' '.$c;
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --gap:14px; --cardW:280px; --cardH:168px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0b1220; position:relative
      }
      .<?php echo $uid; ?> .rail{ display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 48px }

      .<?php echo $uid; ?> .card{ position:relative; flex:0 0 var(--cardW); height:var(--cardH); border-radius:var(--radius);
        border:1px solid rgba(0,0,0,.08); background:#0b1220; box-shadow:var(--shadow); overflow:hidden }
      .<?php echo $uid; ?> .media{ position:absolute; inset:0; overflow:hidden; border-radius:inherit; background:#0b1220 }
      .<?php echo $uid; ?> .media::before{
        content:""; position:absolute; inset:0; background:
          radial-gradient(120% 100% at 50% 50%, rgba(255,255,255,.06), rgba(0,0,0,.0)),
          linear-gradient(to bottom, rgba(0,0,0,.0) 55%, rgba(0,0,0,.32) 100%);
        pointer-events:none;
      }
      .<?php echo $uid; ?> .media img{
        width:100%; height:100%; object-fit:contain; object-position:center; display:block;
        filter: drop-shadow(0 6px 20px rgba(0,0,0,.25));
      }

      .<?php echo $uid; ?> .badge{ position:absolute; left:10px; top:10px; padding:6px 10px;
        background:linear-gradient(135deg,#ef4444,#f59e0b); color:#fff; font-weight:900; font-size:12px;
        border-radius:999px; letter-spacing:.03em; z-index:3 }
      .<?php echo $uid; ?> .shop{ position:absolute; left:10px; top:44px; padding:5px 8px; font-weight:800; font-size:11px;
        border-radius:10px; background:rgba(255,255,255,.96); color:#0f172a; z-index:3; max-width:64%;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis }

      /* cím: CSAK akkor, ha NINCS ár (különben felesleges fehér "Akció" lenne) */
      .<?php echo $uid; ?> .label{ position:absolute; left:12px; bottom:12px; right:116px; color:#fff; font-weight:800; font-size:13px;
        text-shadow:0 2px 10px rgba(0,0,0,.6); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; z-index:2 }

      /* ár mindent felülír olvashatóságban */
      .<?php echo $uid; ?> .price{ position:absolute; right:10px; bottom:10px; z-index:4; padding:8px 12px;
        border-radius:12px; background:rgba(255,255,255,.98); color:#0f172a; font-weight:900; font-size:14px;
        border:1px solid rgba(0,0,0,.08); box-shadow:0 6px 18px rgba(2,6,23,.22) }

      .<?php echo $uid; ?> .nav{ position:absolute; top:50%; transform:translateY(-50%); width:38px; height:38px; border-radius:999px;
        background:#fff; box-shadow:0 8px 22px rgba(2,6,23,.15); display:grid; place-items:center; cursor:pointer; border:1px solid #e5e7eb; z-index:5 }
      .<?php echo $uid; ?> .nav:hover{ transform:translateY(-50%) scale(1.04) }
      .<?php echo $uid; ?> .prev{ left:2px } .<?php echo $uid; ?> .next{ right:2px }

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:860px){
        .<?php echo $uid; ?>{ --cardW: 78vw; --cardH: 45vw; }
        .<?php echo $uid; ?> .rail{ padding:4px 44px }
      }
    </style>

    <div class="<?php echo $uid; ?>">
      <button class="nav prev" aria-label="Vissza" data-prev>‹</button>
      <button class="nav next" aria-label="Előre" data-next>›</button>

      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim($d['title'] ?: '');
          $pct   = $d['percent'] > 0 ? (int)round($d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']);
          $pstr  = $fmt_price($d['price'], $d['currency']); // ha van ár → ezt mutatjuk
          $showLabel = (!$pstr) && ($title !== '') && (strcasecmp($title,'akció') !== 0);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($d['shop_name']); ?>"
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
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;
      const dirSign  = <?php echo $dir_sign; ?>; // +1 jobbra, -1 balra

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

      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      prev && prev.addEventListener('click', ()=>scrollStep(-1));
      next && next.addEventListener('click', ()=>scrollStep(+1));

      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      start();

      if (wantGA) {
        rail.addEventListener('click', function(e){
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}
Használat változatlan, pl.:
[impact_deals_netflix autoplay="1" interval="2600" direction="left"]
Ha bárhol még felbukkan „Akció” fehér cím, az azt jelenti, hogy az adott elemnél nem jön ár a forrásból — ilyenkor most már csak a narancs jelvény marad, cím nem. Következő körben szívesen ráhúzok egy „ára van-e?” diagnosztikát is a feedre, hogy lásd, melyik partnernél hiányzik az ár mező.
Ezt mondtad:
Most eltűnt a fehér akció, de nincsenek árak. Ez a shortcode hozza az árakat, úgyhogy működnie kell:  [impactshop_deals_banners limit="12" force="1"] Talán ez a snippet szabályozza: <?php
/**
 * Sharity – Banners-based deals (MU) + diagnosztika
 * Shortcodes:
 *   [impactshop_deals_banners limit="12" category="" force="1"]
 *   (ha nincs más definíció): [impactshop_deals ...] is erre mutat
 *
 * Diagnosztika:
 *   https://app.sharity.hu/?impact_mini_probe=1
 *   → JSON: plugin aktív-e, bannerek száma, default_d1 mappa, ki kezeli az impactshop_deals rövidkódot
 *
 * UTF-8, BOM nélkül; NINCS záró "?>"
 */

if (!defined('SHARITY_BANNERS_CSV')) {
  define('SHARITY_BANNERS_CSV', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv');
}
if (!defined('SHARITY_BANNERS_TMP_CSV')) {
  define('SHARITY_BANNERS_TMP_CSV', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=470200079&single=true&output=csv');
}
if (!defined('SHARITY_SHOPS_PUB_CSV')) {
  define('SHARITY_SHOPS_PUB_CSV', 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv');
}
if (!defined('SHARITY_GLOBAL_DEFAULT_D1')) {
  define('SHARITY_GLOBAL_DEFAULT_D1', 'bator-tabor-alapitvany');
}

/* ===== segédek ===== */
function sib_slug($s){ return strtolower(preg_replace('~[^a-z0-9\-]+~','-', (string)$s)); }
function sib_b64url($s){ return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function sib_http_get($u,$t=7){ $r=wp_remote_get($u,['timeout'=>$t,'redirection'=>3]); return is_wp_error($r)?[0,'']:[(int)wp_remote_retrieve_response_code($r),(string)wp_remote_retrieve_body($r)]; }
function sib_host($u){ $p=wp_parse_url($u); return isset($p['host'])?strtolower($p['host']):''; }

/* Shops mini-térkép: [slug => ['site','d1']] */
function sib_shops_minimap(){
  static $C=null; if($C!==null) return $C;
  $map=[];
  if(function_exists('impactshop_get_shops')){
    try{
      foreach((array)impactshop_get_shops() as $r){
        $slug=sib_slug($r['shop_slug']??''); if(!$slug) continue;
        $map[$slug]=[
          'site'=>$r['site']??($r['url']??$r['website']??''),
          'd1'  =>sib_slug($r['default_d1']??''),
        ];
      }
    }catch(\Throwable $e){}
  }
  if(!$map){
    list($code,$csv)=sib_http_get(SHARITY_SHOPS_PUB_CSV,7);
    if($code===200 && $csv){
      $lines=preg_split("/\r\n|\r|\n/",trim($csv)); $hdr=[];
      foreach($lines as $i=>$ln){
        if($ln==='') continue; $cols=str_getcsv($ln);
        if(!$i){ $hdr=$cols; continue; }
        $rec=[]; foreach($cols as $j=>$v){ $k=$hdr[$j]??('c'.$j); $rec[trim($k)]=$v; }
        $slug=sib_slug($rec['shop_slug']??''); if(!$slug) continue;
        $map[$slug]=[
          'site'=>$rec['site']??$rec['url']??$rec['website']??'',
          'd1'  =>sib_slug($rec['default_d1']??''),
        ];
      }
    }
  }
  return $C=$map;
}
function sib_default_d1($slug){ $m=sib_shops_minimap(); $slug=sib_slug($slug); $d=$m[$slug]['d1']??''; return $d?:sib_slug(SHARITY_GLOBAL_DEFAULT_D1); }
function sib_site($slug){ $m=sib_shops_minimap(); $slug=sib_slug($slug); return $m[$slug]['site']??''; }

/* Banners betöltés */
function sib_load_banners(){
  list($c,$csv)=sib_http_get(SHARITY_BANNERS_CSV,7);
  if($c!==200 || !$csv){ list($c,$csv)=sib_http_get(SHARITY_BANNERS_TMP_CSV,7); }
  if($c!==200 || !$csv) return [];
  $lines=preg_split("/\r\n|\r|\n/",trim($csv)); $hdr=[]; $out=[];
  foreach($lines as $i=>$ln){
    if($ln==='') continue; $cols=str_getcsv($ln);
    if(!$i){ $hdr=$cols; continue; }
    $rec=[]; foreach($cols as $j=>$v){ $k=$hdr[$j]??('c'.$j); $rec[trim($k)]=$v; }
    $slug=sib_slug($rec['slug']??$rec['shop_slug']??''); if(!$slug) continue;
    $href=trim($rec['href']??''); if(!$href) continue;
    $label=$rec['label']??[];
    if(is_string($label)){ $d=json_decode($label,true); if(is_array($d)) $label=$d; }
    $out[]=[
      'slug'=>$slug,
      'href'=>$href,
      'title'=>$label['title']??($rec['title']??'Ajánlat'),
      'price'=>$label['price']??($rec['price']??''),
      'old_price'=>$label['old_price']??($rec['old_price']??''),
      'pct'=>(int)($label['discount_pct']??($rec['discount_pct']??0)),
      'category'=>$rec['category']??'',
      'img'=>$rec['img']??($rec['image']??''),
    ];
  }
  return $out;
}

/* Linképítés – kezeli a Fillout href-et is (u= base64 terméklink) */
function sib_build_deal_link($slug,$href,$force=true){
  $slug=sib_slug($slug);
  $d1=sib_default_d1($slug);

  // Fillout host?
  if (strpos(sib_host($href),'fillout.com')!==false){
    $p=wp_parse_url($href); $q=[]; if(!empty($p['query'])) parse_str($p['query'],$q);
    if(!empty($q['shop'])) $slug=sib_slug($q['shop']);
    if(!empty($q['u'])){
      $b64=strtr($q['u'],'-_','+/'); $pad=strlen($b64)%4; if($pad)$b64.=str_repeat('=',4-$pad);
      $prod=base64_decode($b64)?:'';
      if($prod!==''){
        return add_query_arg(['d1'=>$d1,'src'=>'impactshop','u'=>sib_b64url($prod)], home_url('/go-deal/'.rawurlencode($slug)));
      }
    }
    // nincs u → sima /go
    return add_query_arg(['d1'=>$d1,'src'=>'impactshop'], home_url('/go/'.rawurlencode($slug)));
  }

  // Direkt terméklink
  if($force){
    return add_query_arg(['d1'=>$d1,'src'=>'impactshop','u'=>sib_b64url($href)], home_url('/go-deal/'.rawurlencode($slug)));
  } else {
    // óvatos mód: csak akkor deeplink, ha egyezik a host a shop site-tal
    $site=sib_site($slug);
    $h1=ltrim(preg_replace('~^www\.~','', sib_host($href)));
    $h2=ltrim(preg_replace('~^www\.~','', sib_host($site)));
    $ok=($h1&&$h2)&&($h1===$h2 || (substr($h1,-strlen($h2)-1)==='.'.$h2));
    if($ok){
      return add_query_arg(['d1'=>$d1,'src'=>'impactshop','u'=>sib_b64url($href)], home_url('/go-deal/'.rawurlencode($slug)));
    }
    return add_query_arg(['d1'=>$d1,'src'=>'impactshop'], home_url('/go/'.rawurlencode($slug)));
  }
}

/* ===== Shortcode: Banners deals ===== */
function sib_sc_deals($atts){
  $a=shortcode_atts(['limit'=>'12','category'=>'','force'=>'1'],$atts,'impactshop_deals_banners');
  $force=$a['force']==='1';
  $items=sib_load_banners();
  if(!$items) return '<div>Jelenleg nincs megjeleníthető ajánlat.</div>';

  $catf=array_filter(array_map('trim', preg_split('~[|,]~',(string)$a['category'])));
  if($catf){
    $items=array_filter($items,function($it)use($catf){
      $hay=' '.strtolower((string)$it['category']).' ';
      foreach($catf as $n){ if($n!=='' && str_contains($hay, strtolower($n))) return true; }
      return false;
    });
  }
  $items=array_slice(array_values($items),0,max(1,(int)$a['limit']));
  $cards=[];
  foreach($items as $it){
    $go=sib_build_deal_link($it['slug'],$it['href'],$force);
    $img=$it['img']?'<img src="'.esc_url($it['img']).'" alt="">':'<div class="ph"></div>';
    $old=$it['old_price']?'<s class="old">'.esc_html($it['old_price']).'</s>':'';
    $pr =$it['price']?'<span class="price">'.esc_html($it['price']).'</span>':'';
    $pct=(int)$it['pct']>0?'<span class="badge">-'.(int)$it['pct'].'%</span>':'';
    $cat=$it['category']?'<div class="cat">'.esc_html($it['category']).'</div>':'';
    $cards[]='<a class="imx-deal" href="'.esc_url($go).'" target="_blank" rel="nofollow sponsored noopener">'
           .   '<div class="thumb">'.$img.'</div>'
           .   '<div class="body"><div class="title">'.esc_html($it['title']).'</div>'
           .   '<div class="row">'.$old.$pr.$pct.'</div>'.$cat.'</div>'
           . '</a>';
  }
  $css='<style>.imx-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
  .imx-deal{display:flex;gap:.75rem;padding:.75rem;border:1px solid #eee;border-radius:14px;text-decoration:none;color:inherit;background:#fff;transition:transform .12s,box-shadow .12s}
  .imx-deal:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.06)}
  .imx-deal .thumb{flex:0 0 auto}.imx-deal img,.imx-deal .ph{width:88px;height:88px;object-fit:cover;border-radius:10px;background:#f7f7f7;display:block}
  .imx-deal .title{font-weight:600;line-height:1.25;margin-bottom:.25rem}.imx-deal .row{display:flex;align-items:center;gap:.5rem}
  .imx-deal .old{opacity:.6;text-decoration:line-through}.imx-deal .badge{font-size:.85em;padding:.15rem .4rem;border-radius:6px;background:#ffe8e8}
  .imx-deal .cat{font-size:.8em;opacity:.7;margin-top:.25rem}</style>';
  return $css.'<div class="imx-grid">'.implode('', $cards).'</div>';
}
add_shortcode('impactshop_deals_banners','sib_sc_deals');

/* Ha nincs más definíció, regisztráljuk az "impactshop_deals" taget is erre */
add_action('init', function(){
  global $shortcode_tags;
  if (empty($shortcode_tags['impactshop_deals'])) {
    add_shortcode('impactshop_deals','sib_sc_deals');
  }
}, 1);

/* ==== Diagnosztika: ?impact_mini_probe=1 → JSON ==== */
add_action('init', function(){
  if (!isset($_GET['impact_mini_probe'])) return;
  header('Content-Type: application/json; charset=utf-8');
  global $shortcode_tags;
  $who = isset($shortcode_tags['impactshop_deals'])
          ? (is_array($shortcode_tags['impactshop_deals']) ? 'callable array' : (is_string($shortcode_tags['impactshop_deals'])?$shortcode_tags['impactshop_deals']:'callable'))
          : 'none';
  $b = sib_load_banners();
  $shops = sib_shops_minimap();
  $sample = isset($b[0]) ? [
    'slug'=>$b[0]['slug'],
    'href'=>$b[0]['href'],
    'built_link'=> sib_build_deal_link($b[0]['slug'],$b[0]['href'], true),
    'default_d1'=> sib_default_d1($b[0]['slug']),
  ] : null;
  echo wp_json_encode([
    'plugin' => 'sharity-impact-banners-deals (MU)',
    'impactshop_deals_owner' => $who,
    'banners_count' => count($b),
    'shops_count' => count($shops),
    'sample' => $sample,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
});
A ChatGPT ezt mondta:

Gondolkozás
igazad van: a te MU snippetje (sib_load_banners()) hozza a szöveges árakat (pl. „9 990 Ft”), míg az én kártyám számot és külön pénznemet várt. Emiatt, ha a REST nem adott árat, nálam „üres” lett.
Javítás: a kártya most közvetlenül meghívja a sib_load_banners()-t (ha elérhető), és kezeli a szöveges price / old_price mezőket. Ha van szöveges ár, azt írjuk ki; ha csak szám van, formázzuk. A fehér „Akció” felirat továbbra sem jelenik meg, az ár pill mindent felülír. Irányváltás maradt.

Illeszd be EGYBEN a mostani helyére:

<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" ga4="1" round_price="1" direction="right"]
 * direction: right|left
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'ga4'         => '1',
      'round_price' => '1',
      'direction'   => 'right',
    ], $atts, 'impact_deals_netflix');

    $limit       = max(1, (int)$a['limit']);
    $autoplay    = $a['autoplay'] === '1';
    $interval
Ezt mondtad:
Ha már cseréljük az árak miatt az egész snippetet oldjuk meg ezt is: a scrollozó kártyákat csak nyíllal lehet jobbra balra mozgatni, nem túl kényelmes. Inkább térjünk át arra hogy swipe-al is mozgathassuk, mint a Netflix shop és kupon kártyákat. Nyílra sincs így szükség.
A ChatGPT ezt mondta:

Gondolkozás
jön a „Netflix-szerű” verzió: nyilak kikerülnek, helyette swipe/drag (mobil + egér) mozgatja a sort, közben megmarad az autoplay és a bal/jobb irány is. Az árakat közvetlenül a te MU-snippetedből (sib_load_banners()) is kiolvassuk (szöveges price/old_price), így biztosan megjelennek.
Illeszd be EGYBEN a mostani helyére:

<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - Swipe/drag vezérlés (mobil + egér), nyilak nélkül
 * - Árak: ha elérhető, közvetlenül a sib_load_banners() price/old_price mezőiből
 * - direction: right|left (balra induláshoz: direction="left")
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'direction'   => 'right',
      'ga4'         => '1',
    ], $atts, 'impact_deals_netflix');

    $limit     = max(1, (int)$a['limit']);
    $autoplay  = $a['autoplay'] === '1';
    $interval  = max(800, (int)$a['interval']);
    $want_ga4  = $a['ga4'] === '1';
    $dir_sign  = (strtolower($a['direction']) === 'left') ? -1 : +1;

    // ---------- 1) Adatforrások: először saját REST, majd sib_load_banners(), végül [impactshop_deals_banners] HTML/JSON ----------
    $items = [];

    // 1/a REST
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ''),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? $r['shopTitle'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''),
          'price'     => isset($r['price']) ? (string)$r['price'] : '',   // lehet string is
          'old_price' => isset($r['old_price']) ? (string)$r['old_price'] : '',
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // 1/b MU helper: sib_load_banners() – EZ hozza a szöveges árakat
    if (!$items && function_exists('sib_load_banners')) {
      try {
        $rows = sib_load_banners();
        foreach ((array)$rows as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => (string)($r['slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['href'] ?? ''),
            'price'     => (string)($r['price'] ?? ''),       // pl. "9 990 Ft"
            'old_price' => (string)($r['old_price'] ?? ''),   // pl. "12 990 Ft"
            'currency'  => '',
            'expires'   => '',
          ];
        }
      } catch (\Throwable $e) {}
    }

    // 1/c Shortcode fallback (JSON → majd HTML)
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => (string)($r['slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['href'] ?? ''),
            'price'     => (string)($r['price'] ?? ''),
            'old_price' => (string)($r['old_price'] ?? ''),
            'currency'  => '',
            'expires'   => '',
          ];
        }
      }
      if (!$items) {
        $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
        if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
          foreach ($m as $mm) {
            $items[] = [
              'id'=> '', 'title'=>'', 'percent'=>0,
              'image'=> (string)($mm[2] ?? ''), 'shop_slug'=>'', 'shop_name'=>'',
              'url'=> (string)($mm[1] ?? ''), 'price'=>'', 'old_price'=>'', 'currency'=>'', 'expires'=>'',
            ];
          }
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár megjelenítő: ha kapunk szöveges árat, azt használjuk, különben kísérlet számra → "Ft"
    $format_price = function($priceStr, $curr) {
      $s = trim((string)$priceStr);
      if ($s !== '') return $s;
      // nincs szöveg → semmi
      return '';
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --gap:14px; --cardW:280px; --cardH:168px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0b1220; position:relative
      }
      .<?php echo $uid; ?> .rail{
        display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 0;
        cursor:grab; user-select:none; -webkit-user-drag:none; touch-action:pan-y;
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

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:860px){
        .<?php echo $uid; ?>{ --cardW: 78vw; --cardH: 45vw; }
      }
    </style>

    <div class="<?php echo $uid; ?>">
      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim((string)($d['title'] ?? ''));
          $pct   = !empty($d['percent']) ? (int)round((float)$d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']);
          $priceStr = $format_price($d['price'] ?? '', $d['currency'] ?? '');
          $showLabel = (!$priceStr) && ($title !== '') && (strcasecmp($title,'akció') !== 0);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($d['shop_name']); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($priceStr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title ?: ($shop ?: 'Ajánlat')); ?>" loading="lazy" decoding="async" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <?php if ($showLabel): ?><div class="label" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></div><?php endif; ?>
            <?php if ($priceStr): ?><div class="price"><?php echo esc_html($priceStr); ?></div><?php endif; ?>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const rail = root.querySelector('[data-rail]');
      const cards= Array.from(rail.querySelectorAll('.card'));
      const autoplay = <?php echo $autoplay ? 'true' : 'false'; ?>;
      const interval = <?php echo (int)$interval; ?>;
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;
      const dirSign  = <?php echo $dir_sign; ?>; // +1 jobbra, -1 balra

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

      // induláskor: ha balra megyünk, ugorjunk a jobbszélre
      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      // --- Swipe / Drag vezérlés (pointer events) ---
      let isDown=false, startX=0, startLeft=0, moved=false, lastX=0, lastT=0, vx=0;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; moved=false; rail.setPointerCapture(e.pointerId);
        startX=e.clientX; startLeft=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.style.cursor='grabbing';
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const dx = e.clientX - startX;
        if (Math.abs(dx) > 3) moved=true;
        rail.scrollLeft = startLeft - dx;
        const now = performance.now();
        const dt = now - lastT; if (dt>0){ vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now; }
      });
      rail.addEventListener('pointerup', (e)=>{
        if(!isDown) return; isDown=false; rail.releasePointerCapture(e.pointerId);
        rail.style.cursor='grab';
        // tehetünk rá kis inerciát
        const momentum = Math.max(-2, Math.min(2, vx)) * 260; // px/s jellegű
        if (momentum !== 0) {
          rail.scrollBy({ left: -momentum, behavior: 'smooth' });
        }
      });
      // ha húzás történt, blokkoljuk a kártya kattintást
      rail.addEventListener('click', (e)=>{ if(moved){ e.preventDefault(); e.stopPropagation(); } }, true);

      // --- Autoplay ---
      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      rail.addEventListener('pointerdown', stop);
      rail.addEventListener('pointerup', start);
      start();

      // GA4 click esemény
      if (wantGA) {
        rail.addEventListener('click', function(e){
          if (moved) return; // swipe esetén ne lőjünk
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}
Használat:
[impact_deals_netflix autoplay="1" interval="2600" direction="left"]
Ha finomhangolnád a swipe „tehetetlenségét”, a momentum értéket állítsd (nagyobb = hosszabb csúszás).

Ezt mondtad:
Most elmentek a linkek, minden csak a webshop főoldalára visz. Ez a módosítás előtt működött. Azonkívül nagyon akad a swipe, desktopon mivel nincs nyíl nem is lehet mozgatni. Itt még jó volt a termékeink, de nem látszott az ár: /**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" ga4="1" round_price="1"]
 * Forrás preferencia: REST (/wp-json/impactshop/v1/deals_banners), majd [impactshop_deals_banners] JSON, végül HTML parse.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'ga4'         => '1',
      'round_price' => '1',
    ], $atts, 'impact_deals_netflix');

    $limit       = max(1, (int)$a['limit']);
    $autoplay    = $a['autoplay'] === '1';
    $interval    = max(1000, (int)$a['interval']);
    $want_ga4    = $a['ga4'] === '1';
    $want_round  = $a['round_price'] === '1';

    // --- 1) REST próbák
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    $items = [];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      if (!is_array($data)) continue;
      $rows = $data['rows'] ?? $data['data'] ?? (isset($data[0]) ? $data : []);
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? 'Akció'),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? $r['shopTitle'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''),
          'price'     => isset($r['price']) ? (float)$r['price'] : (isset($r['amount']) ? (float)$r['amount'] : null),
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // --- 2) Shortcode JSON
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? $r['label'] ?? 'Akció'),
            'percent'   => (float)($r['percent'] ?? 0),
            'image'     => (string)($r['image'] ?? $r['banner_url'] ?? ''),
            'shop_slug' => (string)($r['shop_slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['url'] ?? ''),
            'price'     => isset($r['price']) ? (float)$r['price'] : null,
            'currency'  => (string)($r['currency'] ?? ''),
            'expires'   => (string)($r['expires_at'] ?? ''),
          ];
        }
      }
    }

    // --- 3) HTML parse fallback
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'        => '',
            'title'     => html_entity_decode(strip_tags($mm[3] ?? 'Akció'), ENT_QUOTES, 'UTF-8'),
            'percent'   => 0, 'image' => $mm[2] ?? '', 'shop_slug' => '', 'shop_name' => '',
            'url'       => $mm[1] ?? '', 'price'=>null, 'currency'=>'', 'expires'=>'',
          ];
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár formázó (egészre kerekítve, ezres szóközzel)
    $fmt_price = function($price, $curr) use ($want_round){
      if ($price === null) return '';
      $v = $want_round ? round((float)$price) : (float)$price;
      $num = number_format($v, 0, '.', ' ');
      $c = $curr ?: 'Ft';
      // eurónál tegyük a jelet a szám elé
      if (in_array(strtoupper($c), ['€','EUR'], true)) return '€ '.$num;
      return $num.' '.$c;
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      /* konténer és sín */
      .<?php echo $uid; ?>{ --gap:14px; --cardW:260px; --cardH:148px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0f172a; position:relative }
      .<?php echo $uid; ?> .rail{ display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 44px }

      /* kártya + erős klippelés */
      .<?php echo $uid; ?> .card{ position:relative; flex:0 0 var(--cardW); height:var(--cardH); border-radius:var(--radius);
        border:1px solid rgba(0,0,0,.08); background:#0b1220; box-shadow:var(--shadow); overflow:hidden }
      .<?php echo $uid; ?> .card .media{ position:absolute; inset:0; overflow:hidden; border-radius:inherit; }
      .<?php echo $uid; ?> .card .media img{ width:100%; height:100%; display:block; object-fit:cover; transform:none; transition:transform .25s ease; will-change:transform }
      .<?php echo $uid; ?> .card:hover .media img{ transform:scale(1.02) } /* marad klippelve */

      /* címkék/labelek a kép fölött maradnak */
      .<?php echo $uid; ?> .badge{ position:absolute; left:10px; top:10px; padding:6px 10px; background:linear-gradient(135deg,#ef4444,#f59e0b);
        color:#fff; font-weight:900; font-size:12px; border-radius:999px; letter-spacing:.03em; z-index:2 }
      .<?php echo $uid; ?> .label{ position:absolute; left:12px; bottom:10px; right:110px; color:#fff; font-weight:800; font-size:13px;
        text-shadow:0 2px 10px rgba(0,0,0,.6); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; z-index:2 }
      .<?php echo $uid; ?> .shop{ position:absolute; left:10px; top:44px; padding:5px 8px; font-weight:800; font-size:11px; border-radius:10px;
        background:rgba(255,255,255,.92); color:#0f172a; z-index:2 }

      /* ár pill – jobb alsó sarokban, kerekítve */
      .<?php echo $uid; ?> .price{ position:absolute; right:10px; bottom:10px; z-index:2; padding:7px 10px;
        border-radius:12px; background:rgba(255,255,255,.96); color:#0f172a; font-weight:900; font-size:13px; border:1px solid rgba(0,0,0,.08) }

      /* navigáció gombok */
      .<?php echo $uid; ?> .nav{ position:absolute; top:50%; transform:translateY(-50%); width:38px; height:38px; border-radius:999px;
        background:#fff; box-shadow:0 8px 22px rgba(2,6,23,.15); display:grid; place-items:center; cursor:pointer; border:1px solid #e5e7eb }
      .<?php echo $uid; ?> .nav:hover{ transform:translateY(-50%) scale(1.04) }
      .<?php echo $uid; ?> .prev{ left:2px } .<?php echo $uid; ?> .next{ right:2px }

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:740px){
        .<?php echo $uid; ?> .rail{ padding:4px 40px }
        .<?php echo $uid; ?>{ --cardW: 76vw; --cardH: 42vw; }
      }
    </style>

    <div class="<?php echo $uid; ?>">
      <button class="nav prev" aria-label="Vissza" data-prev>‹</button>
      <button class="nav next" aria-label="Előre" data-next>›</button>

      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim($d['title'] ?: 'Akció');
          $pct   = $d['percent'] > 0 ? (int)round($d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']);
          $pstr  = $fmt_price($d['price'], $d['currency']);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($d['shop_name']); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($pstr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <div class="label"><?php echo esc_html($title); ?></div>
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
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;

      function cardGap(){ const cs = getComputedStyle(rail); return parseFloat(cs.columnGap||'0') || parseFloat(cs.gap||'14') || 14; }
      function cardWidth(){
        const c = cards[0]; if(!c) return 300;
        const r = c.getBoundingClientRect(); return r.width + cardGap();
      }
      function scrollByDir(dir){ rail.scrollBy({left: dir*cardWidth(), behavior:'smooth'}); }
      prev && prev.addEventListener('click', ()=>scrollByDir(-1));
      next && next.addEventListener('click', ()=>scrollByDir(+1));

      let timer=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); timer=setInterval(()=>scrollByDir(+1), interval); }
      function stop(){ if(timer){ clearInterval(timer); timer=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      start();

      if (wantGA) {
        rail.addEventListener('click', function(e){
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}  Itt jó az ár, de a termékeink főoldalra visz, a webshop nevek slug-al látszanak, nem névvel, elvileg már megvan neked az átváltó csv file linkje:(https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv) itt pedig a snippet:  /**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - Swipe/drag vezérlés (mobil + egér), nyilak nélkül
 * - Árak: ha elérhető, közvetlenül a sib_load_banners() price/old_price mezőiből
 * - direction: right|left (balra induláshoz: direction="left")
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('impact_deals_netflix_shortcode')) {
  function impact_deals_netflix_shortcode($atts) {
    $a = shortcode_atts([
      'limit'       => '12',
      'autoplay'    => '1',
      'interval'    => '3000',
      'direction'   => 'right',
      'ga4'         => '1',
    ], $atts, 'impact_deals_netflix');

    $limit     = max(1, (int)$a['limit']);
    $autoplay  = $a['autoplay'] === '1';
    $interval  = max(800, (int)$a['interval']);
    $want_ga4  = $a['ga4'] === '1';
    $dir_sign  = (strtolower($a['direction']) === 'left') ? -1 : +1;

    // ---------- 1) Adatforrások: először saját REST, majd sib_load_banners(), végül [impactshop_deals_banners] HTML/JSON ----------
    $items = [];

    // 1/a REST
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ''),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? $r['shopTitle'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''),
          'price'     => isset($r['price']) ? (string)$r['price'] : '',   // lehet string is
          'old_price' => isset($r['old_price']) ? (string)$r['old_price'] : '',
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // 1/b MU helper: sib_load_banners() – EZ hozza a szöveges árakat
    if (!$items && function_exists('sib_load_banners')) {
      try {
        $rows = sib_load_banners();
        foreach ((array)$rows as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => (string)($r['slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['href'] ?? ''),
            'price'     => (string)($r['price'] ?? ''),       // pl. "9 990 Ft"
            'old_price' => (string)($r['old_price'] ?? ''),   // pl. "12 990 Ft"
            'currency'  => '',
            'expires'   => '',
          ];
        }
      } catch (\Throwable $e) {}
    }

    // 1/c Shortcode fallback (JSON → majd HTML)
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => (string)($r['slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['href'] ?? ''),
            'price'     => (string)($r['price'] ?? ''),
            'old_price' => (string)($r['old_price'] ?? ''),
            'currency'  => '',
            'expires'   => '',
          ];
        }
      }
      if (!$items) {
        $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
        if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
          foreach ($m as $mm) {
            $items[] = [
              'id'=> '', 'title'=>'', 'percent'=>0,
              'image'=> (string)($mm[2] ?? ''), 'shop_slug'=>'', 'shop_name'=>'',
              'url'=> (string)($mm[1] ?? ''), 'price'=>'', 'old_price'=>'', 'currency'=>'', 'expires'=>'',
            ];
          }
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár megjelenítő: ha kapunk szöveges árat, azt használjuk, különben kísérlet számra → "Ft"
    $format_price = function($priceStr, $curr) {
      $s = trim((string)$priceStr);
      if ($s !== '') return $s;
      // nincs szöveg → semmi
      return '';
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --gap:14px; --cardW:280px; --cardH:168px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0b1220; position:relative
      }
      .<?php echo $uid; ?> .rail{
        display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 0;
        cursor:grab; user-select:none; -webkit-user-drag:none; touch-action:pan-y;
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

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:860px){
        .<?php echo $uid; ?>{ --cardW: 78vw; --cardH: 45vw; }
      }
    </style>

    <div class="<?php echo $uid; ?>">
      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim((string)($d['title'] ?? ''));
          $pct   = !empty($d['percent']) ? (int)round((float)$d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']);
          $priceStr = $format_price($d['price'] ?? '', $d['currency'] ?? '');
          $showLabel = (!$priceStr) && ($title !== '') && (strcasecmp($title,'akció') !== 0);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($d['shop_name']); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($priceStr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title ?: ($shop ?: 'Ajánlat')); ?>" loading="lazy" decoding="async" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <?php if ($showLabel): ?><div class="label" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></div><?php endif; ?>
            <?php if ($priceStr): ?><div class="price"><?php echo esc_html($priceStr); ?></div><?php endif; ?>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const rail = root.querySelector('[data-rail]');
      const cards= Array.from(rail.querySelectorAll('.card'));
      const autoplay = <?php echo $autoplay ? 'true' : 'false'; ?>;
      const interval = <?php echo (int)$interval; ?>;
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;
      const dirSign  = <?php echo $dir_sign; ?>; // +1 jobbra, -1 balra

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

      // induláskor: ha balra megyünk, ugorjunk a jobbszélre
      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      // --- Swipe / Drag vezérlés (pointer events) ---
      let isDown=false, startX=0, startLeft=0, moved=false, lastX=0, lastT=0, vx=0;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; moved=false; rail.setPointerCapture(e.pointerId);
        startX=e.clientX; startLeft=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.style.cursor='grabbing';
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const dx = e.clientX - startX;
        if (Math.abs(dx) > 3) moved=true;
        rail.scrollLeft = startLeft - dx;
        const now = performance.now();
        const dt = now - lastT; if (dt>0){ vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now; }
      });
      rail.addEventListener('pointerup', (e)=>{
        if(!isDown) return; isDown=false; rail.releasePointerCapture(e.pointerId);
        rail.style.cursor='grab';
        // tehetünk rá kis inerciát
        const momentum = Math.max(-2, Math.min(2, vx)) * 260; // px/s jellegű
        if (momentum !== 0) {
          rail.scrollBy({ left: -momentum, behavior: 'smooth' });
        }
      });
      // ha húzás történt, blokkoljuk a kártya kattintást
      rail.addEventListener('click', (e)=>{ if(moved){ e.preventDefault(); e.stopPropagation(); } }, true);

      // --- Autoplay ---
      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      rail.addEventListener('pointerdown', stop);
      rail.addEventListener('pointerup', start);
      start();

      // GA4 click esemény
      if (wantGA) {
        rail.addEventListener('click', function(e){
          if (moved) return; // swipe esetén ne lőjünk
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}
A ChatGPT ezt mondta:

Gondolkozás
értem, csináljuk rendesen:
a linkek nem lesznek átírva (amit a forrás ad, azt tesszük a <a href>-be),
a swipe desktopon is kényelmes: egér-húzás, trackpad/egérgörgő vízszintes görgetés, billentyű (← →), tehetetlenség finomítva,
az árak megmaradnak (szöveges price/old_price támogatott),
a shop név a slug helyett a megadott CSV alapján jelenik meg (https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv – slug → név map, rugalmas fejlécfelismeréssel),
marad az autoplay + direction="left|right".
Illeszd be EGYBEN a mostani helyére:
<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - Swipe/drag (mobil + egér), görgő/trackpad vízszintes görgetés, billentyű (← →)
 * - Árak: szöveges árak támogatása (sib_load_banners / shortcode JSON)
 * - Shop-név: slug → név konvertálás CSV-ből
 * - direction: right|left
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
    $interval = max(800, (int)$a['interval']);
    $want_ga4 = $a['ga4'] === '1';
    $dir_sign = (strtolower($a['direction']) === 'left') ? -1 : +1;

    /* ---------- 0) Shop név térkép CSV-ből (slug -> name) ---------- */
    $shop_name_map = [];
    try {
      $csv_url = 'https://app.sharity.hu/wp-content/uploads/2025/09/ngo_codes.csv';
      $res = wp_remote_get($csv_url, ['timeout'=>6]);
      if (!is_wp_error($res) && (int)wp_remote_retrieve_response_code($res)===200) {
        $body = wp_remote_retrieve_body($res);
        if (is_string($body) && $body!=='') {
          $lines = preg_split("/\r\n|\r|\n/", trim($body));
          $hdr = [];
          foreach ($lines as $i=>$ln) {
            if ($ln==='') continue;
            $cols = str_getcsv($ln);
            if (!$i) { $hdr = array_map('strtolower',$cols); continue; }
            $row = [];
            foreach ($cols as $j=>$v) { $k = $hdr[$j] ?? ('c'.$j); $row[$k] = trim($v); }
            // Próbált kulcsok: slug | shop_slug | key  és name | title | shop_name
            $slug = strtolower(preg_replace('~[^a-z0-9\-]+~','-', $row['slug'] ?? $row['shop_slug'] ?? $row['key'] ?? ''));
            $name = $row['name'] ?? $row['title'] ?? $row['shop_name'] ?? '';
            if ($slug && $name) $shop_name_map[$slug] = $name;
          }
        }
      }
    } catch (\Throwable $e) {}

    /* ---------- 1) Adatforrások: REST → MU (sib_load_banners) → shortcode JSON/HTML ---------- */
    $items = [];

    // 1/a REST
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      foreach ($rows as $r) {
        $slug = (string)($r['shop_slug'] ?? $r['shop'] ?? '');
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ''),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => $slug,
          'shop_name' => $shop_name_map[strtolower($slug)] ?? (string)($r['shop_name'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''), // NEM piszkáljuk → így marad produkt link/ go-deal ha az jön
          'price'     => isset($r['price']) ? (string)$r['price'] : '',
          'old_price' => isset($r['old_price']) ? (string)$r['old_price'] : '',
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
          'expires'   => (string)($r['expires_at'] ?? $r['valid_to'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // 1/b MU helper
    if (!$items && function_exists('sib_load_banners')) {
      try {
        $rows = sib_load_banners();
        foreach ((array)$rows as $r) {
          $slug = (string)($r['slug'] ?? '');
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => $slug,
            'shop_name' => $shop_name_map[strtolower($slug)] ?? (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['href'] ?? ''),       // ez már /go-deal … ha a MU úgy építi → békén hagyjuk
            'price'     => (string)($r['price'] ?? ''),      // "9 990 Ft"
            'old_price' => (string)($r['old_price'] ?? ''),  // "12 990 Ft"
            'currency'  => '',
            'expires'   => '',
          ];
        }
      } catch (\Throwable $e) {}
    }

    // 1/c Shortcode fallback
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $slug = (string)($r['slug'] ?? '');
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => $slug,
            'shop_name' => $shop_name_map[strtolower($slug)] ?? (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['href'] ?? ''),
            'price'     => (string)($r['price'] ?? ''),
            'old_price' => (string)($r['old_price'] ?? ''),
            'currency'  => '',
            'expires'   => '',
          ];
        }
      }
      if (!$items) {
        $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
        if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
          foreach ($m as $mm) {
            $items[] = [
              'id'=> '', 'title'=>'', 'percent'=>0,
              'image'=> (string)($mm[2] ?? ''), 'shop_slug'=>'', 'shop_name'=>'',
              'url'=> (string)($mm[1] ?? ''), 'price'=>'', 'old_price'=>'', 'currency'=>'', 'expires'=>'',
            ];
          }
        }
      }
    }

    // tisztítás + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // ár formázó: ha szöveg jön, azt mutatjuk
    $format_price = function($priceStr, $curr) {
      $s = trim((string)$priceStr);
      if ($s !== '') return $s;
      return '';
    };

    $uid = 'ideals_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --gap:14px; --cardW:280px; --cardH:168px; --radius:18px; --shadow:0 14px 36px rgba(2,6,23,.18);
        font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:#0b1220; position:relative
      }
      .<?php echo $uid; ?> .rail{
        display:flex; gap:var(--gap); overflow:hidden; scroll-behavior:smooth; padding:4px 0;
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

      .<?php echo $uid; ?> .empty{ color:#64748b; padding:8px 0 }

      @media (max-width:860px){
        .<?php echo $uid; ?>{ --cardW: 78vw; --cardH: 45vw; }
      }
    </style>

    <div class="<?php echo $uid; ?>" tabindex="0">
      <div class="rail" data-rail>
        <?php if (!$items): ?>
          <div class="empty">Nincs megjeleníthető akció.</div>
        <?php else: foreach ($items as $d):
          $title = trim((string)($d['title'] ?? ''));
          $pct   = !empty($d['percent']) ? (int)round((float)$d['percent']) : 0;
          $shop  = $d['shop_name'] ?: $d['shop_slug'];
          $img   = esc_url($d['image']);
          $href  = esc_url($d['url']); // a forrás szerinti link – nem írjuk át
          $priceStr = $format_price($d['price'] ?? '', $d['currency'] ?? '');
          $showLabel = (!$priceStr) && ($title !== '') && (strcasecmp($title,'akció') !== 0);
        ?>
          <a class="card" href="<?php echo $href; ?>"
             data-deal-id="<?php echo esc_attr($d['id']); ?>"
             data-shop-slug="<?php echo esc_attr($d['shop_slug']); ?>"
             data-shop-name="<?php echo esc_attr($shop); ?>"
             data-label="<?php echo esc_attr($title); ?>"
             data-percent="<?php echo esc_attr($pct); ?>"
             data-price="<?php echo esc_attr($priceStr); ?>">
            <div class="media">
              <img src="<?php echo $img; ?>" alt="<?php echo esc_attr($title ?: ($shop ?: 'Ajánlat')); ?>" loading="lazy" decoding="async" />
            </div>
            <div class="badge"><?php echo $pct ? ('-'.intval($pct).'%') : 'AKCIÓ'; ?></div>
            <?php if ($shop): ?><div class="shop"><?php echo esc_html($shop); ?></div><?php endif; ?>
            <?php if ($showLabel): ?><div class="label" title="<?php echo esc_attr($title); ?>"><?php echo esc_html($title); ?></div><?php endif; ?>
            <?php if ($priceStr): ?><div class="price"><?php echo esc_html($priceStr); ?></div><?php endif; ?>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const rail = root.querySelector('[data-rail]');
      const cards= Array.from(rail.querySelectorAll('.card'));
      const autoplay = <?php echo $autoplay ? 'true' : 'false'; ?>;
      const interval = <?php echo (int)$interval; ?>;
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;
      const dirSign  = <?php echo $dir_sign; ?>; // +1 jobbra, -1 balra

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

      // induláskor: ha balra megyünk, ugorjunk a jobbszélre
      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      // --- Swipe / Drag (pointer events) – finom küszöbök, hogy a kattintás megmaradjon ---
      let isDown=false, startX=0, startLeft=0, movedPx=0, lastX=0, lastT=0, vx=0;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; movedPx=0; rail.setPointerCapture(e.pointerId);
        startX=e.clientX; startLeft=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.style.cursor='grabbing';
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const dx = e.clientX - startX;
        movedPx = Math.max(movedPx, Math.abs(dx));
        rail.scrollLeft = startLeft - dx;
        const now = performance.now(), dt = now - lastT;
        if (dt>0){ vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now; }
      });
      function endDrag(e){
        if(!isDown) return; isDown=false;
        try{ rail.releasePointerCapture(e.pointerId); }catch(_){}
        rail.style.cursor='grab';
        // tehetetlenség: finomabb, rövidebb – nem akadozik
        const momentum = Math.max(-1.2, Math.min(1.2, vx)) * 380;
        if (momentum) rail.scrollBy({ left: -momentum, behavior: 'smooth' });
      }
      rail.addEventListener('pointerup', endDrag);
      rail.addEventListener('pointercancel', endDrag);

      // csak akkor blokkoljuk a kattintást, ha VALÓBAN húzott (küszöb 10px)
      rail.addEventListener('click', (e)=>{ if(movedPx > 10){ e.preventDefault(); e.stopPropagation(); } }, true);

      // --- Görgő / trackpad: Y-t is fordítsuk át X-re, hogy desktopon kényelmes legyen ---
      rail.addEventListener('wheel', (e)=>{
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return; // natív vízszintes maradjon
        rail.scrollLeft += e.deltaY; // függőlegesből vízszintes
        e.preventDefault();
      }, { passive:false });

      // --- Billentyűk: ← → ---
      root.addEventListener('keydown', (e)=>{
        if (e.key === 'ArrowRight'){ e.preventDefault(); scrollStep(+1); }
        else if (e.key === 'ArrowLeft'){ e.preventDefault(); scrollStep(-1); }
      });

      // --- Autoplay ---
      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      rail.addEventListener('pointerdown', stop);
      rail.addEventListener('pointerup', start);
      start();

      // GA4 click
      if (wantGA) {
        rail.addEventListener('click', function(e){
          if (movedPx > 10) return; // swipe esetén ne lőjünk
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}
Ezzel:
a linkek nem torzulnak (ha a forrás /go-deal?...u=..., oda visz; ha produkt-URL, azt használjuk),
a swipe simább, desktopon görgővel és egérrel is kényelmes,
a shop név már nem slug, hanem a CSV szerinti név,
az árak megjelennek, ahol a forrás adja.
Ha szeretnéd, be tudok tenni egy name_map_url attribútumot is, hogy a jövőben könnyen tudd cserélni a CSV-t paraméterből.
Ezt mondtad:
hát semmi nem változott. A swipe szar, a link főoldalra visz. Kaptál két kibaszott snippetet, az egyikben (régi) jó a termékeink. Oldd meg. A swipe katasztrófa, miközben nettfix tök jó, nem értem miért nem lehet azt átvenni. Rakj a két oldalára nyilat, ha valakinek nem jó a swipe akkor használja azt. 
A ChatGPT ezt mondta:

Gondolkozás
Oké, összeraktam egy stabil „Netflix-szerű” verziót, ami:
Nem nyúl a linkhez (pontosan azt teszi a <a href>-be, amit a forrás ad). target="_blank"-kal nyit.
Swipe + nyilak együtt: mobilon/húzva sima, desktopon egérrel/trackpaddel/nyilakkal is kényelmes.
Kattintás nem vész el: csak akkor blokkoljuk, ha tényleg húztak (küszöb: 14px).
Autoplay + direction marad; szélnél wrap-el.
Ár: ha a forrás ad szöveges árat, azt mutatjuk (mint az MU / banners rövidkód).
Illeszd be EGYBEN a mostani helyére:
<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - Link: a forrás href-je változtatás nélkül (target="_blank")
 * - Vezérlés: swipe/drag + NYILAK (+ billentyű ← →, + görgő/trackpad vízszintes)
 * - Ár: szöveges price/old_price támogatott (banners/JSON)
 * - Irány: direction="right" | "left"
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
    $want_ga4 = $a['ga4'] === '1';
    $dir_sign = (strtolower($a['direction']) === 'left') ? -1 : +1;

    /* ---------- 1) Források: REST → banners JSON → banners HTML ---------- */
    $items = [];

    // 1/a REST
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      foreach ($rows as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
          'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ''),
          'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
          'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
          'shop_slug' => (string)($r['shop_slug'] ?? $r['shop'] ?? ''),
          'shop_name' => (string)($r['shop_name'] ?? ''),
          'url'       => (string)($r['url'] ?? $r['deeplink'] ?? ''), // NEM módosítjuk
          'price'     => (string)($r['price'] ?? ''),
          'old_price' => (string)($r['old_price'] ?? ''),
          'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
        ];
      }
      if ($items) break;
    }

    // 1/b Banners JSON (a te [impactshop_deals_banners json="1"] formátumod)
    if (!$items && function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => (string)($r['slug'] ?? ''),
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => (string)($r['href'] ?? ''),      // ez már /go-deal… lehet → békén hagyjuk
            'price'     => (string)($r['price'] ?? ''),     // "9 990 Ft"
            'old_price' => (string)($r['old_price'] ?? ''), // "12 990 Ft"
            'currency'  => '',
          ];
        }
      }
    }

    // 1/c Banners HTML parser (végső fallback)
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*>(?:.*?)</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'=> '', 'title'=>'', 'percent'=>0,
            'image'=> (string)($mm[2] ?? ''), 'shop_slug'=>'', 'shop_name'=>'',
            'url'=> (string)($mm[1] ?? ''), 'price'=>'', 'old_price'=>'', 'currency'=>'',
          ];
        }
      }
    }

    // Szűrés + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // Ár kiírás: szöveg preferált
    $fmt_price = function($priceStr, $curr) {
      $s = trim((string)$priceStr);
      if ($s !== '') return $s;
      return '';
    };

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
          $href  = esc_url($d['url']); // forrás szerinti link – változtatás nélkül
          $pstr  = $fmt_price($d['price'] ?? '', $d['currency'] ?? '');
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
      const wantGA   = <?php echo $want_ga4 ? 'true' : 'false'; ?>;
      const dirSign  = <?php echo $dir_sign; ?>; // +1 jobbra, -1 balra

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

      // induláskor: ha balra megyünk, ugorjunk a jobbszélre
      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      // --- SWIPE/DRAG + inercia (smooth, nem akad) ---
      let isDown=false, sx=0, sl=0, moved=0, vx=0, raf=null, lastX=0, lastT=0;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; moved=0; vx=0; sx=e.clientX; sl=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.setPointerCapture(e.pointerId); rail.style.cursor='grabbing';
        if (raf) cancelAnimationFrame(raf), raf=null;
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const now=performance.now(), dt=now-lastT||1;
        const dx=e.clientX - sx; moved=Math.max(moved, Math.abs(dx));
        rail.scrollLeft = sl - dx;
        vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now;
      });
      function pointerEnd(e){
        if(!isDown) return; isDown=false; rail.style.cursor='grab';
        try{ rail.releasePointerCapture(e.pointerId); }catch(_){}
        // egyszerű inercia: fokozatos csillapítás rAF-fel
        let v = Math.max(-1.5, Math.min(1.5, vx)) * 24; // px per frame kb.
        function tick(){
          if (Math.abs(v) < 0.1) { raf=null; return; }
          rail.scrollLeft -= v;
          v *= 0.92;
          raf = requestAnimationFrame(tick);
        }
        raf = requestAnimationFrame(tick);
      }
      rail.addEventListener('pointerup', pointerEnd);
      rail.addEventListener('pointercancel', pointerEnd);

      // Kattintás csak ha nem húzott (küszöb 14px)
      rail.addEventListener('click', (e)=>{ if(moved > 14){ e.preventDefault(); e.stopPropagation(); } }, true);

      // Görgő/trackpad: Y→X, hogy desktopon kényelmes legyen
      rail.addEventListener('wheel', (e)=>{
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return; // natív X maradjon
        rail.scrollLeft += e.deltaY;
        e.preventDefault();
      }, { passive:false });

      // Nyilak
      prev && prev.addEventListener('click', ()=> scrollStep(-1));
      next && next.addEventListener('click', ()=> scrollStep(+1));
      root.addEventListener('keydown', (e)=>{
        if (e.key === 'ArrowRight'){ e.preventDefault(); scrollStep(+1); }
        else if (e.key === 'ArrowLeft'){ e.preventDefault(); scrollStep(-1); }
      });

      // Autoplay
      let t=null;
      function start(){ if(!autoplay || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
      function stop(){ if(t){ clearInterval(t); t=null; } }
      rail.addEventListener('mouseenter', stop);
      rail.addEventListener('mouseleave', start);
      rail.addEventListener('pointerdown', stop);
      rail.addEventListener('pointerup', start);
      start();

      // GA4
      if (wantGA) {
        rail.addEventListener('click', function(e){
          if (moved > 14) return;
          const a = e.target.closest('.card'); if(!a) return;
          try {
            window.gtag && window.gtag('event', 'deal_click', {
              deal_id:   a.getAttribute('data-deal-id') || '',
              shop_slug: a.getAttribute('data-shop-slug') || '',
              shop_name: a.getAttribute('data-shop-name') || '',
              label:     a.getAttribute('data-label') || '',
              percent:   a.getAttribute('data-percent') || '',
              price:     a.getAttribute('data-price') || '',
            });
          } catch(_){}
        });
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_deals_netflix', 'impact_deals_netflix_shortcode');
}
Használat minta:
[impact_deals_netflix autoplay="1" interval="2600" direction="left"]
Ha most is főoldalra dob valamelyik kártya, az a forrás href ilyen értéke miatt lesz; itt direkt nem piszkáljuk. Ha szeretnéd, adok egy link_mode="raw|go" opciót, ami „RAW” módban mindig a terméklinket erőlteti, „GO” módban meg mindig a /go-deal-t — de előbb nézzük meg ezzel, hogy a swipe és a nyilak rendben vannak-e.
Ezt mondtad:
Most jó a link (egy-két kivétellel, a swipe is ok, de eltűntek az árak, pedig az meg előbb ok volt....
A ChatGPT ezt mondta:

Gondolkozás
Oké, a lényeg: az árak azért tűntek el, mert elsőnek a REST-ből vettük az adatot (ahol gyakran nincs ár), és mivel „break”-eltünk, már nem jutottunk el a bannerekhez (ahol van). Most:
Forrás-sorrend csere: először a saját impactshop_deals_banners json="1" (vagy MU), aztán REST, végül HTML.
Ár-kiegészítés: ha REST-ből jön kártya ár nélkül, akkor összefésüljük a banner-listával (URL és/vagy slug alapján), és pótoljuk a price / old_price mezőket.
Swipe + nyilak megmaradnak, desktopon is sima.
Linkekhez nem nyúlunk (ami a forrásban van, azt tesszük be).
Tedd be EGYBEN a mostani helyére:
<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - Forrás prioritás: BANNERS JSON/MU → REST → HTML
 * - REST-es tételeknél ár-pótlás banner-listából (URL/slug alapján)
 * - Swipe + NYILAK, smooth inercia, billentyű, görgő
 * - Linkekhez nem nyúlunk (target=_blank)
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
    $want_ga4 = $a['ga4'] === '1';
    $dir_sign = (strtolower($a['direction']) === 'left') ? -1 : +1;

    /* ========== 0) Segédek ========== */
    $norm_url = function($u){
      $u = trim((string)$u);
      if ($u === '') return '';
      // egyszerű normalizálás: töröljük a fölösleges trailing slasheket és #hash-t
      $parts = wp_parse_url($u);
      if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return $u;
      $path  = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
      $query = isset($parts['query']) ? $parts['query'] : '';
      $scheme= strtolower($parts['scheme']);
      $host  = strtolower(preg_replace('~^www\.~','', $parts['host']));
      $norm  = $scheme.'://'.$host.$path.($query?('?'.$query):'');
      return $norm;
    };

    /* ========== 1) BANNERS (JSON/MU) → elsődleges, mert itt jön az ár ========== */
    $banner_items = [];
    // 1/a: próbáljuk a JSON kimenetet a saját shortcode-odból
    if (function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit*3).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $slug = (string)($r['slug'] ?? '');
          $href = (string)($r['href'] ?? '');
          $banner_items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => $slug,
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => $href,
            'price'     => (string)($r['price'] ?? ''),     // pl. "9 990 Ft"
            'old_price' => (string)($r['old_price'] ?? ''), // pl. "12 990 Ft"
            'currency'  => '',
          ];
        }
      }
    }
    // 1/b: ha nincs JSON, próbáljuk a MU helper-t
    if (!$banner_items && function_exists('sib_load_banners')) {
      try {
        foreach ((array)sib_load_banners() as $r) {
          $slug = (string)($r['slug'] ?? '');
          $href = (string)($r['href'] ?? '');
          $banner_items[] = [
            'id'        => (string)($r['id'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
            'percent'   => (int)($r['pct'] ?? 0),
            'image'     => (string)($r['img'] ?? ''),
            'shop_slug' => $slug,
            'shop_name' => (string)($r['shop_name'] ?? ''),
            'url'       => $href,
            'price'     => (string)($r['price'] ?? ''),
            'old_price' => (string)($r['old_price'] ?? ''),
            'currency'  => '',
          ];
        }
      } catch (\Throwable $e) {}
    }

    // ár-index a pótláshoz (kulcs: norm_url + slug)
    $price_by_url  = [];
    $price_by_slug = [];
    foreach ($banner_items as $bi){
      if (!empty($bi['url']))  $price_by_url[$norm_url($bi['url'])] = [$bi['price'],$bi['old_price']];
      if (!empty($bi['shop_slug'])) $price_by_slug[strtolower($bi['shop_slug'])] = [$bi['price'],$bi['old_price']];
    }

    /* ========== 2) REST → kiegészítjük árakkal, ha hiányoznak ========== */
    $items = [];
    $rest_rows = [];
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      if ($rows){ $rest_rows = $rows; break; }
    }
    foreach ($rest_rows as $r) {
      $slug = (string)($r['shop_slug'] ?? $r['shop'] ?? '');
      $href = (string)($r['url'] ?? $r['deeplink'] ?? '');
      $p    = '';
      $op   = '';
      // 2/a ha REST nem ad árat → próbáljuk banner indexből
      if (!empty($href)) {
        $key = $norm_url($href);
        if (isset($price_by_url[$key])) { [$p,$op] = $price_by_url[$key]; }
      }
      if ($p === '' && $slug !== '') {
        $skey = strtolower($slug);
        if (isset($price_by_slug[$skey])) { [$p,$op] = $price_by_slug[$skey]; }
      }
      $items[] = [
        'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
        'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ''),
        'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
        'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
        'shop_slug' => $slug,
        'shop_name' => (string)($r['shop_name'] ?? ''),
        'url'       => $href,                // forrás szerinti link (RAW)
        'price'     => (string)($r['price'] ?? $p),     // REST price VAGY pótolt
        'old_price' => (string)($r['old_price'] ?? $op),
        'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
      ];
    }

    // 3) ha még mindig nincs semmi, használjuk közvetlenül a banner tételeket
    if (!$items && $banner_items) $items = $banner_items;

    // 4) HTML fallback, ha minden más elbukott
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'=> '', 'title'=>'', 'percent'=>0,
            'image'=> (string)($mm[2] ?? ''), 'shop_slug'=>'', 'shop_name'=>'',
            'url'=> (string)($mm[1] ?? ''), 'price'=>'', 'old_price'=>'', 'currency'=>'',
          ];
        }
      }
    }

    // Szűrés + limit
    $items = array_values(array_filter($items, fn($x)=> !empty($x['image']) && !empty($x['url'])));
    $items = array_slice($items, 0, $limit);

    // Ár: szöveg preferált; ha nincs, semmit nem írunk (nem teszünk oda „Akció”-t)
    $fmt_price = function($priceStr){ $s = trim((string)$priceStr); return $s !== '' ? $s : ''; };

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
          $href  = esc_url($d['url']); // forrás szerinti link
          $pstr  = $fmt_price($d['price'] ?? '');
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
      const dirSign  = <?php echo $dir_sign; ?>; // +1 jobbra, -1 balra

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

      // induláskor: ha balra megyünk, ugorjunk a jobbszélre
      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      // SWIPE/DRAG + inercia (stabil)
      let isDown=false, sx=0, sl=0, moved=0, vx=0, raf=null, lastX=0, lastT=0;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; moved=0; vx=0; sx=e.clientX; sl=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.setPointerCapture(e.pointerId); rail.style.cursor='grabbing';
        if (raf) cancelAnimationFrame(raf), raf=null;
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const now=performance.now(), dt=now-lastT||1;
        const dx=e.clientX - sx; moved=Math.max(moved, Math.abs(dx));
        rail.scrollLeft = sl - dx;
        vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now;
      });
      function pointerEnd(e){
        if(!isDown) return; isDown=false; rail.style.cursor='grab';
        try{ rail.releasePointerCapture(e.pointerId); }catch(_){}
        let v = Math.max(-1.5, Math.min(1.5, vx)) * 24;
        function tick(){
          if (Math.abs(v) < 0.1) { raf=null; return; }
          rail.scrollLeft -= v;
          v *= 0.92;
          raf = requestAnimationFrame(tick);
        }
        raf = requestAnimationFrame(tick);
      }
      rail.addEventListener('pointerup', pointerEnd);
      rail.addEventListener('pointercancel', pointerEnd);

      // Kattintás csak ha nem húzott (küszöb 14px)
      rail.addEventListener('click', (e)=>{ if(moved > 14){ e.preventDefault(); e.stopPropagation(); } }, true);

      // Görgő/trackpad: Y→X kényelmi görgetés
      rail.addEventListener('wheel', (e)=>{
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
        rail.scrollLeft += e.deltaY;
        e.preventDefault();
      }, { passive:false });

      // Nyilak
      prev && prev.addEventListener('click', ()=> scrollStep(-1));
      next && next.addEventListener('click', ()=> scrollStep(+1));
      root.addEventListener('keydown', (e)=>{
        if (e.key === 'ArrowRight'){ e.preventDefault(); scrollStep(+1); }
        else if (e.key === 'ArrowLeft'){ e.preventDefault(); scrollStep(-1); }
      });

      // Autoplay
      let t=null;
      function start(){ if(!<?php echo $autoplay ? 'true' : 'false'; ?> || cards.length<=1) return; stop(); t=setInterval(()=>scrollStep(dirSign), interval); }
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
Ez a verzió kifejezetten az árra optimalizál: a bannerekből indul (ahol biztos jön az ár), és ha REST-ből érkező elem „áratlan”, a banner-listából pótolja URL vagy slug alapján. Ha szeretnéd, be tudok tenni egy prefer_source="banners|rest" attribútumot is, de ezzel már stabilan látszani fognak az árak.
Ezt mondtad:
ne basszél már fel, most jó az ár, de rossz a link. Ne legyél gyökér, oldd meg!!!!!!!!
A ChatGPT ezt mondta:

Gondolkozás
Jogos a dühöd. Visszarakom a REST linket elsődlegesnek, csak az árat pótolom a banner feedből. Így megmaradnak a jó terméklinkek, és az árak is látszanak. A swipe marad sima, nyilak is vannak.
Illeszd be EGYBEN a mostani helyére:

<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - LINK: MINDIG a REST-ből (ha van). A banners csak ÁRat ad hozzá.
 * - Forrás-sorrend: REST (link + alapadat) → árpótlás BANNERS-ből → ha REST üres, akkor BANNERS-t teszünk be.
 * - Swipe + NYILAK + görgő/trackpad + billentyű
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
    $want_ga4 = $a['ga4'] === '1';
    $dir_sign = (strtolower($a['direction']) === 'left') ? -1 : +1;

    /* ===== Segédek ===== */
    $norm_url = function($u){
      $u = trim((string)$u);
      if ($u === '') return '';
      $p = wp_parse_url($u); if(!$p) return $u;
      $sch = strtolower($p['scheme'] ?? ''); $host = strtolower(preg_replace('~^www\.~','', $p['host'] ?? ''));
      $path = isset($p['path']) ? rtrim($p['path'], '/') : '';
      $qry  = isset($p['query']) ? $p['query'] : '';
      return ($sch && $host) ? ($sch.'://'.$host.$path.($qry?('?'.$qry):'')) : $u;
    };

    /* ===== 1) BANNERS – ÁRMAP (price/old_price csak pótolni!) ===== */
    $price_by_url = [];  // kulcs: norm_url(href)  → [price, old_price]
    $price_by_slug= [];  // kulcs: shop_slug       → [price, old_price]

    // próbáljuk a JSON kimenetet
    if (function_exists('do_shortcode')) {
      $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit*3).'" force="1" json="1"]');
      $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
      if (is_array($maybe)) {
        foreach ($maybe as $r) {
          $href = (string)($r['href'] ?? '');
          $slug = strtolower((string)($r['slug'] ?? ''));
          $pr   = trim((string)($r['price'] ?? ''));
          $opr  = trim((string)($r['old_price'] ?? ''));
          if ($href!=='') $price_by_url[$norm_url($href)] = [$pr,$opr];
          if ($slug!=='') $price_by_slug[$slug] = [$pr,$opr];
        }
      }
    }
    // ha nincs JSON, próbáljuk a MU helper-t
    if (!$price_by_url && function_exists('sib_load_banners')) {
      try {
        foreach ((array)sib_load_banners() as $r) {
          $href = (string)($r['href'] ?? '');
          $slug = strtolower((string)($r['slug'] ?? ''));
          $pr   = trim((string)($r['price'] ?? ''));
          $opr  = trim((string)($r['old_price'] ?? ''));
          if ($href!=='') $price_by_url[$norm_url($href)] = [$pr,$opr];
          if ($slug!=='') $price_by_slug[$slug] = [$pr,$opr];
        }
      } catch (\Throwable $e) {}
    }

    /* ===== 2) REST – EZ ADJA A LINKET, EZ AZ ALAP ===== */
    $items = [];
    $try_urls = [
      home_url('/wp-json/impactshop/v1/deals_banners?limit='.$limit),
      home_url('/wp-json/impactshop/v1/deals?type=banner&limit='.$limit),
    ];
    $rest_rows = [];
    foreach ($try_urls as $url) {
      $resp = wp_remote_get($url, ['timeout'=>8, 'headers'=>['Accept'=>'application/json']]);
      if (is_wp_error($resp)) continue;
      $code = (int) wp_remote_retrieve_response_code($resp);
      if ($code < 200 || $code >= 300) continue;
      $data = json_decode(wp_remote_retrieve_body($resp), true);
      $rows = is_array($data) ? ($data['rows'] ?? $data['data'] ?? (isset($data[0])?$data:[])) : [];
      if ($rows) { $rest_rows = $rows; break; }
    }

    foreach ($rest_rows as $r) {
      $slug = strtolower((string)($r['shop_slug'] ?? $r['shop'] ?? ''));
      $href = (string)($r['url'] ?? $r['deeplink'] ?? '');
      $pstr = trim((string)($r['price'] ?? ''));
      $ostr = trim((string)($r['old_price'] ?? ''));

      // Ár-pótlás BANNERS-ből, ha REST-ben nincs
      if ($pstr==='') {
        $key = $norm_url($href);
        if ($key && isset($price_by_url[$key])) { [$pstr,$ostr] = $price_by_url[$key]; }
        elseif ($slug && isset($price_by_slug[$slug])) { [$pstr,$ostr] = $price_by_slug[$slug]; }
      }

      $items[] = [
        'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
        'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ''),
        'percent'   => (float)($r['percent'] ?? $r['discount'] ?? 0),
        'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ''),
        'shop_slug' => $slug,
        'shop_name' => (string)($r['shop_name'] ?? ''), // ha külön CSV kell, ide könnyen be lehet húzni
        'url'       => (string)$href,                   // *** A REST LINKJE, NEM NYÚLUNK HOZZÁ ***
        'price'     => $pstr,
        'old_price' => $ostr,
        'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
      ];
    }

    /* ===== 3) Ha REST üres volt → közvetlenül a BANNERS-t tesszük ki (ott vannak linkek is) ===== */
    if (!$items && ($price_by_url || $price_by_slug)) {
      // Újra lekérjük a bannereket, hogy képeink/URL-eink is legyenek
      $banner_items = [];
      if (function_exists('do_shortcode')) {
        $json_try = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1" json="1"]');
        $maybe = json_decode(trim(wp_strip_all_tags($json_try)), true);
        if (is_array($maybe)) $banner_items = $maybe;
      }
      if (!$banner_items && function_exists('sib_load_banners')) {
        try { $banner_items = sib_load_banners(); } catch(\Throwable $e){}
      }
      foreach ((array)$banner_items as $r) {
        $items[] = [
          'id'        => (string)($r['id'] ?? ''),
          'title'     => (string)($r['title'] ?? ''),
          'percent'   => (int)($r['pct'] ?? 0),
          'image'     => (string)($r['img'] ?? ''),
          'shop_slug' => strtolower((string)($r['slug'] ?? '')),
          'shop_name' => (string)($r['shop_name'] ?? ''),
          'url'       => (string)($r['href'] ?? ''),   // banners saját linkje
          'price'     => trim((string)($r['price'] ?? '')),
          'old_price' => trim((string)($r['old_price'] ?? '')),
          'currency'  => '',
        ];
      }
    }

    /* ===== 4) HTML fallback utolsó esélynek ===== */
    if (!$items && function_exists('do_shortcode')) {
      $html = do_shortcode('[impactshop_deals_banners limit="'.intval($limit).'" force="1"]');
      if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>.*?<img\s[^>]*src="([^"]+)"[^>]*>.*?</a>~is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
          $items[] = [
            'id'=> '', 'title'=>'', 'percent'=>0,
            'image'=> (string)($mm[2] ?? ''), 'shop_slug'=>'', 'shop_name'=>'',
            'url'=> (string)($mm[1] ?? ''), 'price'=>'', 'old_price'=>'', 'currency'=>'',
          ];
        }
      }
    }

    /* ===== 5) Szűrés + limit ===== */
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
          $href  = esc_url($d['url']);                 // REST link marad!
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

      requestAnimationFrame(function(){
        if (dirSign < 0) rail.scrollLeft = Math.max(rail.scrollWidth - rail.clientWidth, 0);
      });

      // SWIPE/DRAG + inercia
      let isDown=false, sx=0, sl=0, moved=0, vx=0, raf=null, lastX=0, lastT=0;
      rail.addEventListener('pointerdown', (e)=>{
        isDown=true; moved=0; vx=0; sx=e.clientX; sl=rail.scrollLeft; lastX=e.clientX; lastT=performance.now();
        rail.setPointerCapture(e.pointerId); rail.style.cursor='grabbing';
        if (raf) cancelAnimationFrame(raf), raf=null;
      });
      rail.addEventListener('pointermove', (e)=>{
        if(!isDown) return;
        const now=performance.now(), dt=now-lastT||1;
        const dx=e.clientX - sx; moved=Math.max(moved, Math.abs(dx));
        rail.scrollLeft = sl - dx;
        vx = (e.clientX - lastX)/dt; lastX=e.clientX; lastT=now;
      });
      function pointerEnd(e){
        if(!isDown) return; isDown=false; rail.style.cursor='grab';
        try{ rail.releasePointerCapture(e.pointerId); }catch(_){}
        let v = Math.max(-1.5, Math.min(1.5, vx)) * 24;
        function tick(){
          if (Math.abs(v) < 0.1) { raf=null; return; }
          rail.scrollLeft -= v; v *= 0.92; raf = requestAnimationFrame(tick);
        }
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

      // Nyilak
      prev && prev.addEventListener('click', ()=> scrollStep(-1));
      next && next.addEventListener('click', ()=> scrollStep(+1));
      root.addEventListener('keydown', (e)=>{
        if (e.key === 'ArrowRight'){ e.preventDefault(); scrollStep(+1); }
        else if (e.key === 'ArrowLeft'){ e.preventDefault(); scrollStep(-1); }
      });

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
Ha maradt olyan kártya, ahol REST is főoldalra mutat, arra külön tudok adni link_mode opciót (RAW/GO) vagy egy per-partner szabályt (slug→deeplink-erőltetés). De most ezzel a verzióval a link REST szerint, az ár pedig banners szerint jelenik meg.
Ezt mondtad:
A link még mindig nem jó, a többi ok. Link a termékre vigyen. Kettővel ezelőtt ment, most miért nem?
A ChatGPT ezt mondta:

Gondolkozás
Megcsináltam úgy, hogy a link mindig a termékre menjen: a REST adatok maradnak alapnak (így a „két kivétel” se romlik el), de ha van hozzá banner-link, akkor azt választjuk elsőbbséggel, főleg ha /go-deal vagy ?u= paramos (produkt link). Ha a REST csak a főoldalra mutatna (sekély útvonal), a banner-linkre váltunk. Az ár továbbra is a bannerből pótlódik, ha a REST nem adja. Swipe + nyilak maradnak.
Tedd be EGYBEN:

<?php
/**
 * Shortcode: [impact_deals_netflix limit="12" autoplay="1" interval="3000" direction="right" ga4="1"]
 * - LINK: REST az alap, de ha a banner /go-deal vagy ?u= paramos (produkt), akkor azt használjuk
 *         + ha a REST „főoldal gyanús” (sekély path), bannerrel felülírjuk
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
    $want_ga4 = $a['ga4'] === '1';
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
      if (!empty($p['query']) && strpos($p['query'],'u=') !== false) return true;
      if (!empty($p['path']) && strpos($p['path'], '/go-deal/') !== false) return true;
      return false;
    };
    $path_depth = function($u){
      $p = wp_parse_url((string)$u); $path = $p['path'] ?? '';
      if ($path === '' || $path === '/') return 0;
      $parts = array_values(array_filter(explode('/', $path), fn($x)=>$x!==''));
      return count($parts);
    };

    /* ===== 1) Banner feed (ár + alternatív produkt link) – indexek ===== */
    $banner_by_slug = []; // slug => ['href','price','old_price','img','title','pct','shop_name']
    if (function_exists('do_shortcode')) {
      $json = do_shortcode('[impactshop_deals_banners limit="'.intval($limit*3).'" force="1" json="1"]');
      $rows = json_decode(trim(wp_strip_all_tags($json)), true);
      if (is_array($rows)) {
        foreach ($rows as $r) {
          $slug = strtolower((string)($r['slug'] ?? '')); if(!$slug) continue;
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
          $slug = strtolower((string)($r['slug'] ?? '')); if(!$slug) continue;
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

    /* ===== 2) REST – ez adja az alap kártyalistát ===== */
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
      $hrefR = (string)($r['url'] ?? $r['deeplink'] ?? '');
      $price = trim((string)($r['price'] ?? ''));
      $opric = trim((string)($r['old_price'] ?? ''));
      $b = $banner_by_slug[$slug] ?? null;

      // ár pótlás bannerből
      if ($price==='')  $price = $b['price'] ?? '';
      if ($opric==='')  $opric = $b['old_price'] ?? '';

      // LINK VÁLASZTÁS: ha banner produkt-linkes (/go-deal vagy ?u=), azt használjuk;
      // ha REST „főoldal gyanús” (sekély útvonal), akkor is bannert választunk, ha elérhető.
      $href = $hrefR;
      if (!empty($b['href'])) {
        if ($has_u_param($b['href'])) {
          $href = $b['href']; // produkt
        } else if (!$has_u_param($hrefR)) {
          if ($path_depth($hrefR) <= 1) { // főoldal-érzet
            $href = $b['href'];
          }
        }
      }

      $items[] = [
        'id'        => (string)($r['id'] ?? $r['deal_id'] ?? ''),
        'title'     => (string)($r['title'] ?? $r['label'] ?? $r['name'] ?? ($b['title'] ?? '')),
        'percent'   => (float)($r['percent'] ?? $r['discount'] ?? ($b['pct'] ?? 0)),
        'image'     => (string)($r['image'] ?? $r['banner_url'] ?? $r['img'] ?? ($b['img'] ?? '')),
        'shop_slug' => $slug,
        'shop_name' => (string)($r['shop_name'] ?? ($b['shop_name'] ?? '')),
        'url'       => $href,     // <- végső link
        'price'     => $price,
        'old_price' => $opric,
        'currency'  => (string)($r['currency'] ?? $r['curr'] ?? ''),
      ];
    }

    // ha REST üres volt → dobjuk be közvetlenül a bannereket
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
          $href  = esc_url($d['url']); // végső, terméklinkre preferált
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
      rail.addEventListener('click', (e)=>{ if(moved > 14){ e.preventDefault(); e.stopPropagation(); } }, true);
      rail.addEventListener('wheel', (e)=>{ if (Math.abs(e.deltaX) <= Math.abs(e.deltaY)) { rail.scrollLeft += e.deltaY; e.preventDefault(); } }, { passive:false });

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
Ha marad olyan partner, ahol még így is főoldalra esik a REST, miközben a banner ad produkt-linket, ebben a verzióban már a banneré nyer (mert ?u=//go-deal/ alapján előrébb soroljuk). Ha látsz még makacs példát, írd meg a shop slugját — ráteszek explicit szabályt, hogy mindig a banner-link menjen.
Ezt mondtad:
Még mindig nem jók a linkek. Megkérdeztem a Data Analyst-et: Röviden: a végső href sokszor a REST url mezőjéből jön (ami gyakran csak a shop főoldalára mutató partnerlink), miközben a valódi terméklink a deeplink mezőben lenne. Ráadásul a bannereket slug alapján indexeled, de az itemeknél shop_slug-gal keresel – emiatt a “banner felülírja a főoldalt” logika gyakran nem is fut le. Végül a has_u_param() túl szűken ellenőriz (pl. /go-deal perjel nélkül nem meccsel).

Tedd meg ezt a 3 apró módosítást, és a kártyák termékoldalra fognak vinni:

---

### 1) REST linkválasztás: **a deeplink legyen az első**

php
// EDDIG:
$hrefR = (string)($r['url'] ?? $r['deeplink'] ?? '');

// HELYETTE:
$hrefR = (string)($r['deeplink'] ?? $r['url'] ?? '');


A legtöbb feedben a deeplink a termékoldal, az url pedig gyakran csak shop-szintű.

---

### 2) Banner indexelés: **shop_slug alapján (fallback: slug)**

A két helyen, ahol a bannereket feltöltöd (do_shortcode és sib_load_banners ágon), cseréld az indexelést:

php
// EDDIG:
$slug = strtolower((string)($r['slug'] ?? ''));

// HELYETTE (robosztusabb):
$slug = strtolower((string)($r['shop_slug'] ?? $r['slug'] ?? ''));


Így amikor később:

php
$slug  = strtolower((string)($r['shop_slug'] ?? $r['shop'] ?? ''));
$b = $banner_by_slug[$slug] ?? null;


— valós eséllyel találsz egyezést, és a banner (ha terméklinkes) tényleg felül tudja írni a shop-főoldalra mutató REST linket.

---

### 3) has_u_param() bővítése (go-deal perjel nélkül, alternatív paramok)

php
$has_u_param = function($u){
  $p = wp_parse_url((string)$u); if(!$p) return false;
  $q = $p['query'] ?? '';
  $path = $p['path'] ?? '';

  // /go-deal vagy /go-deal/ is jó
  $hasGo = (bool)preg_match('~/(go-deal)(/|$)~i', $path);

  // u= mellett engedjünk url=/to=/target=/dest=/redirect=/r= neveket is
  $hasDeepParam = (bool)preg_match('~(?:^|&)(u|url|to|target|dest|redirect|r)=~i', $q);

  return $hasGo || $hasDeepParam;
};


---

### (Opcionális) apró finomítás

Ha biztosra mennél a “főoldal-gyanús” felismerésnél:

* használd a már megírt, de most nem használt $norm_url()-t az összehasonlítások előtt,
* és gondold meg, hogy path_depth<=1 kategória esetén **mindig** a banner-linket válaszd, ha az “produkt-gyanús” (has_u_param vagy path_depth>=2).

---

### Miért ettől javul meg?

* A legtöbb hálózatban a **terméklink = deeplink**, a **shop főoldal = url**. Most fordítva preferáltad.
* A banner-matching slug szerint elcsúszhat a shop_slug-os lookuphoz képest, így a felülíró logika nem futott.
* A /go-deal és a különböző param-nevek eddig könnyen “kimentek a rostán”, ezért nem ismerted fel, hogy a banner valójában terméklinket ad.

Ha ezt a három cserét megteszed, a kártyákból nyíló linkeknek termékoldalakra kell vinniük, nem a webshop főoldalára. Következő lépésként érdemes a renderelt HTML-ben ránézni egy-két kártya href-jére (jobbklikk → link címének másolása), hogy tényleg deeplink-et látsz-e, és hogy a path_depth > 1 vagy van-e u=/url= param. Arra figyelj, hogy ő nem ismeri az előzményeket, ha egy az egyben ezt teszem amit leírt, akkor eltűnik az egész akció scroll. Fontold meg a javaslatokat és kérek egy egyben csere snippetet. 
A ChatGPT ezt mondta:
igazad van — itt a teljes, egyben cserélhető snippet a három konkrét javítással, a meglévő swipe+nyilak kóddal változatlanul.
A módosítások benne vannak:
REST link: mostantól a deeplink az elsődleges, csak ha az nincs, esik vissza url-re.
Banner index: shop_slug alapján indexel, slug csak fallback.
Terméklink-detektálás: a has_u_param() bővítve (/go-deal perjellel és anélkül, több param-névvel).
Illeszd be EGYBEN:
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
Ha most is találsz „makacs” kártyát, másold ki a kártya href-jét és a shop_slug-ját — rá tudok tenni per-partner kivételt (pl. mindig bannert válasszon, vagy mindig deeplinket preferáljon).



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
