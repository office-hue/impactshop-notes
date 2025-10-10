Átugrás a tartalomra
Ezt mondtad:
API kiépítés: régional snipet: /**
 * Impact Shop – Shops & Banners + /go és /go-deal (Dognet) fallbackkal
 * Shortcode-ok:
 *  - [impactshop_scroller category="" inject_every="5" speed="30"]
 *  - [impactshop_catalog show_tabs="1" search="1" per_page="200"]
 *  - [impactshop_diag]
 */

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv',
    'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
    'cache_ttl'       => 15 * MINUTE_IN_SECONDS,
  ];
}

/** ===== CSV segédek ===== */

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

/** ===== Shops & Banners ===== */

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
    $name   = isset($r['name']) ? $r['name'] : (isset($r['nev']) ? $r['nev'] : '');
    $slug   = isset($r['shop_slug']) ? $r['shop_slug'] : (isset($r['slug']) ? $r['slug'] : (isset($r['go_slug']) ? $r['go_slug'] : ''));
    $cat    = isset($r['category']) ? $r['category'] : (isset($r['kategoria']) ? $r['kategoria'] : 'Egyéb');
    $logo   = isset($r['logo_url']) ? $r['logo_url'] : (isset($r['logo']) ? $r['logo'] : (isset($r['image']) ? $r['image'] : ''));
    if (!$name || !$slug) continue;
    $out[] = [
      'name'           => $name,
      'shop_slug'      => $slug,
      'category'       => $cat ? $cat : 'Egyéb',
      'logo'           => $logo,
      'dognet_base'    => isset($r['dognet_base']) ? $r['dognet_base'] : '',
      'deeplink_param' => (isset($r['pdognet_deeplink_param']) ? $r['pdognet_deeplink_param'] : (isset($r['dognet_deeplink_param']) ? $r['dognet_deeplink_param'] : 'url')) ?: 'url',
      'product_url'    => isset($r['product_url']) ? $r['product_url'] : (isset($r['homepage']) ? $r['homepage'] : ''),
      'commission_min' => isset($r['commission_min']) ? $r['commission_min'] : '',
      'commission_max' => isset($r['commission_max']) ? $r['commission_max'] : '',
      'deals_feed'     => isset($r['deals_feed']) ? $r['deals_feed'] : '',
    ];
  }
  return $out;
}

function impactshop_get_banners() {
  $rows = impactshop_get_banners_raw();
  $out = [];
  foreach ($rows as $r) {
    $img   = isset($r['img']) ? $r['img'] : (isset($r['image']) ? $r['image'] : (isset($r['banner']) ? $r['banner'] : (isset($r['kep']) ? $r['kep'] : '')));
    $href  = isset($r['href']) ? $r['href'] : (isset($r['url']) ? $r['url'] : (isset($r['link']) ? $r['link'] : ''));
    $label = isset($r['label']) ? $r['label'] : (isset($r['cimke']) ? $r['cimke'] : (isset($r['title']) ? $r['title'] : 'Banner'));
    $cat   = isset($r['category']) ? $r['category'] : (isset($r['kategoria']) ? $r['kategoria'] : '');
    if (!$img || !$href) continue;
    $out[] = ['img'=>$img,'href'=>$href,'label'=>$label,'category'=>$cat];
  }
  return $out;
}

/** ===== Közös ===== */

function impactshop_q($key, $def='') {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
}
function impactshop_find_shop($slug) {
  $slug = trim(strtolower($slug));
  $all = impactshop_get_shops();
  foreach ($all as $s) {
    if (strtolower($s['shop_slug']) === $slug) return $s;
  }
  return null;
}

/** ===== Redirect építés ===== */

function impactshop_build_dognet_url($shopRow, $ngo_code, $maybeUrl) {
  $base = trim(isset($shopRow['dognet_base']) ? $shopRow['dognet_base'] : '');
  if (!$base) return [null, 'Hiányzik a dognet_base a Shops CSV-ben.'];

  $params = [];
  if ($ngo_code) $params['d1'] = $ngo_code;

  $deeplinkParam = !empty($shopRow['deeplink_param']) ? $shopRow['deeplink_param'] : 'url';
  if (!empty($maybeUrl)) {
    $decoded = $maybeUrl;
    // ha valószínűleg base64 (csak A–Z,a–z,0–9,+,/ és max 2 '='):
    if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $maybeUrl)) {
      $tmp = base64_decode($maybeUrl, true);
      if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $decoded = $tmp;
    }
    $params[$deeplinkParam] = $decoded;
  }

  $glue = (strpos($base, '?') === false) ? '?' : '&';
  $url = $base . $glue . http_build_query($params);
  return [$url, null];
}

/** ===== /go és /go-deal endpointok ===== */

function impactshop_add_rewrites() {
  add_rewrite_rule('^go/?$', 'index.php?impactshop_go=1', 'top');
  add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[] = 'impactshop_go';
  $vars[] = 'impactshop_deal';
  return $vars;
}
add_filter('query_vars','impactshop_add_query_vars');

function impactshop_template_redirect() {
  if (get_query_var('impactshop_go')) {
    impactshop_handle_go(false);
    exit;
  }
  if (get_query_var('impactshop_deal')) {
    impactshop_handle_go(true);
    exit;
  }
}
add_action('template_redirect','impactshop_template_redirect');

function impactshop_handle_go($is_deal) {
  $shop = impactshop_q('shop');
  $ngo  = impactshop_q('d1');
  $u    = impactshop_q('u');
  $amb  = impactshop_q('amb');
  $src  = impactshop_q('src') ?: 'impactshop';

  if (!$shop || !$ngo) impactshop_error('Hiányzó paraméter (shop/d1).');

  $row = impactshop_find_shop($shop);
  if (!$row) impactshop_error('Ismeretlen shop: '.esc_html($shop));

  $targetUrl = '';
  if ($is_deal) {
    if ($u) {
      $targetUrl = $u;
    } else {
      $fallback = trim(isset($row['product_url']) ? $row['product_url'] : '');
      if ($fallback) {
        $targetUrl = $fallback;
      } else {
        list($redir,) = impactshop_build_dognet_url($row, $ngo, '');
        if ($redir) impactshop_redirect_with_propagation($redir, $amb, $src);
        impactshop_error('Hiányzik a deeplink és nincs fallback URL sem.');
      }
    }
  }

  list($dognetUrl, $err) = impactshop_build_dognet_url($row, $ngo, $targetUrl);
  if ($err || !$dognetUrl) impactshop_error($err ? $err : 'Ismeretlen hiba (Dognet URL).');

  impactshop_redirect_with_propagation($dognetUrl, $amb, $src);
}

function impactshop_redirect_with_propagation($url, $amb, $src) {
  $add = [];
  if ($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
  if ($src && strpos($url,'src=')===false) $add['src']=$src;
  if (strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
  if (strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
  if ($add) $url .= (strpos($url,'?')===false ? '?' : '&') . http_build_query($add);
  wp_redirect($url, 307);
  exit;
}

function impactshop_error($msg) {
  status_header(400);
  wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'WordPress › hiba');
}

/** ===== Shortcode: Scroller ===== */

function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts([
    'category'     => '',
    'inject_every' => 5,
    'speed'        => 30,
  ], $atts);

  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  if (!empty($a['category'])) {
    $catWanted = $a['category'];
    $tmp = [];
    foreach ($shops as $s) {
      if (strcasecmp($s['category'], $catWanted) === 0) $tmp[] = $s;
    }
    $shops = array_values($tmp);

    $tmp = [];
    foreach ($banners as $b) {
      if (empty($b['category']) || strcasecmp($b['category'], $catWanted) === 0) $tmp[] = $b;
    }
    $banners = array_values($tmp);
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
    .impactshop-item{display:inline-block;margin-right:20px}
  </style>
  <div class="impactshop-scroller">
    <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
      <?php foreach ($stream as $it): ?>
        <?php if ($it['type']==='shop'): $shop=$it['data'];
          if ($d1) {
            $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
          } else {
            $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
          } ?>
          <div class="impactshop-item">
            <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
              <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                   loading="lazy" decoding="async" style="height:60px;width:auto">
            </a>
          </div>
        <?php else: $b=$it['data']; ?>
          <div class="impactshop-item">
            <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
              <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>"
                   loading="lazy" decoding="async" style="height:60px;width:auto">
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

/** ===== Shortcode: Katalógus ===== */

function impactshop_shortcode_catalog($atts) {
  $a = shortcode_atts([
    'show_tabs' => '1',
    'search'    => '1',
    'per_page'  => '200',
  ], $atts);

  $shops = impactshop_get_shops();
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  usort($shops, function($x,$y){
    $c = strcasecmp($x['category'],$y['category']);
    return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
  });

  $cats = [];
  foreach ($shops as $s) { $cats[$s['category']] = true; }
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
        if ($d1) {
          $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
        } else {
          $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
        } ?>
        <div class="impactshop-card" data-cat="<?php echo esc_attr($shop['category']); ?>" style="text-align:center;">
          <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
            <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                 loading="lazy" decoding="async" style="height:60px;width:auto;max-width:100%">
          </a>
          <div style="font-size:14px;font-weight:600;margin-top:6px;"><?php echo esc_html($shop['name']); ?></div>
          <?php
          // Várható adomány % (jutalék 50%-a)
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

/** ===== Diagnosztika ===== */
function impactshop_shortcode_diag() {
  $shops = impactshop_get_shops();
  $out = '<div style="font:14px/1.5 system-ui"><h3>Impact Shop diagnosztika</h3>';
  $out .= '<p>Shops betöltve: <b>'.count($shops).'</b></p>';
  $miss = [];
  foreach ($shops as $s) {
    if (empty($s['dognet_base'])) $miss[] = $s['shop_slug'].' (dognet_base)';
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

/** ===== Rewrite flush kézzel: ?impactshop_refresh=1 ===== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});
A ChatGPT ezt mondta:

Befejezte a gondolkodást
Ezt mondtad:
új nippet: /**
 * Impact Shop – /go és /go-deal redirect API-s linkgenerálással (Dognet)
 * - Ha van API token (DOGNET_API_TOKEN), a linket az API-val generáljuk.
 * - Ha az API nem elérhető / hibázik, visszaesünk a korábbi go.dognet.com buildre.
 * - A Shop CSV-ben lévő dognet_base-ből automatikusan kinyerjük a campaign_id-t (cid).
 *
 * Beállítások:
 *  - define('DOGNET_API_TOKEN', 'PASTE_YOUR_BEARER_TOKEN_HERE');
 *  - define('DOGNET_AD_CHANNEL_ID', 0); // 0 = auto: első "Web Site" típusú csatorna
 */

if (!defined('DOGNET_API_TOKEN')) {
  define('DOGNET_API_TOKEN', ''); // <-- TÖLTSD KI (Bearer token)
}
if (!defined('DOGNET_AD_CHANNEL_ID')) {
  define('DOGNET_AD_CHANNEL_ID', 0); // 0 = auto-pick "Web Site"
}

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=328401803&single=true&output=csv',
    'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
    'cache_ttl'       => 15 * MINUTE_IN_SECONDS,
  ];
}

/* --------------------------- CSV segédek --------------------------- */

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

function impactshop_get_shops() {
  $rows = impactshop_fetch_csv_assoc(impactshop_settings()['shops_csv_url'], 'impactshop_csv_shops', impactshop_settings()['cache_ttl']);
  $out = [];
  foreach ($rows as $r) {
    $name   = $r['name']       ?? ($r['nev'] ?? '');
    $slug   = $r['shop_slug']  ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
    $cat    = $r['category']   ?? ($r['kategoria'] ?? 'Egyéb');
    $logo   = $r['logo_url']   ?? ($r['logo'] ?? ($r['image'] ?? ''));
    if (!$name || !$slug) continue;
    $out[] = [
      'name'   => $name,
      'shop_slug' => $slug,
      'category'  => $cat ?: 'Egyéb',
      'logo'      => $logo,
      'dognet_base'   => $r['dognet_base'] ?? '',
      'deeplink_param'=> ($r['pdognet_deeplink_param'] ?? ($r['dognet_deeplink_param'] ?? 'url')) ?: 'url',
      'product_url'   => $r['product_url'] ?? ($r['homepage'] ?? ''),
    ];
  }
  return $out;
}

function impactshop_find_shop($slug) {
  $slug = trim(strtolower($slug));
  foreach (impactshop_get_shops() as $s) {
    if (strtolower($s['shop_slug']) === $slug) return $s;
  }
  return null;
}

/* ---------------------- Dognet Publisher API ---------------------- */

function dognet_api_request($method, $path, $body = null) {
  if (!DOGNET_API_TOKEN) return new WP_Error('no_token','Nincs API token');
  $url  = 'https://api.app.dognet.com/api/v1' . $path;
  $args = [
    'timeout' => 20,
    'headers' => [
      'Authorization' => 'Bearer ' . DOGNET_API_TOKEN,
      'Content-Type'  => 'application/json',
    ],
    'method'  => $method,
  ];
  if ($body !== null) $args['body'] = wp_json_encode($body);
  $resp = wp_remote_request($url, $args);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code < 200 || $code >= 300) {
    return new WP_Error('api_error', 'Dognet API hiba: '.$code, ['resp'=>$json]);
  }
  return $json;
}

function dognet_api_pick_ad_channel_id() {
  if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
  $list = dognet_api_request('GET','/adchannels/');
  if (is_wp_error($list) || empty($list['data'])) return 0;
  // első "Web Site" típusú csatorna (ha nincs, vesszük az elsőt)
  foreach ($list['data'] as $c) {
    if (isset($c['type']) && stripos($c['type'],'web') !== false) return intval($c['id']);
  }
  return intval($list['data'][0]['id']);
}

function dognet_extract_campaign_id_from_base($dognet_base) {
  if (!$dognet_base) return 0;
  $qs = parse_url($dognet_base, PHP_URL_QUERY);
  parse_str($qs, $parts);
  return isset($parts['cid']) ? intval($parts['cid']) : 0;
}

/**
 * Generál egy affiliate linket a Dognet API-val.
 * - $campaign_id kötelező (cid – a Shop CSV dognet_base-ből kinyerjük)
 * - $deeplink opcionális (ha nincs, a kampány alapoldalára mutató link jön)
 * - $d1,$d2 opcionális (Dognet data1/data2)
 * Vissza: string URL vagy WP_Error
 */
function dognet_api_generate_link($campaign_id, $deeplink = '', $d1 = '', $d2 = '') {
  $ad_channel_id = dognet_api_pick_ad_channel_id();
  if (!$ad_channel_id) return new WP_Error('no_channel','Nincs elérhető ad_channel');

  $body = [
    'ad_channel_id' => $ad_channel_id,
    'campaign_id'   => intval($campaign_id),
    'url_type'      => 3, // simple
  ];
  if ($deeplink) $body['url']   = $deeplink;
  if ($d1)       $body['data1'] = $d1;
  if ($d2)       $body['data2'] = $d2;

  $json = dognet_api_request('POST','/links/generate', $body);
  if (is_wp_error($json)) return $json;

  // a dokumentáció alapján a generált link a "url" vagy "short_url"/"full_url" mezőben lehet
  foreach (['url','short_url','full_url'] as $k) {
    if (!empty($json[$k])) return $json[$k];
    if (!empty($json['data'][$k])) return $json['data'][$k];
  }
  return new WP_Error('bad_api','Ismeretlen API válasz', $json);
}

/* ------------------- Régi (fallback) URL-építés ------------------- */

function impactshop_build_legacy_go_url($shopRow, $ngo_code, $maybeUrl) {
  $base = trim($shopRow['dognet_base'] ?? '');
  if (!$base) return [null, 'Hiányzik a dognet_base a Shops CSV-ben.'];

  $params = [];
  if ($ngo_code) $params['d1'] = $ngo_code;

  $deeplinkParam = $shopRow['deeplink_param'] ?: 'url';
  if (!empty($maybeUrl)) {
    $decoded = $maybeUrl;
    // base64 elfogadása is
    if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $maybeUrl)) {
      $tmp = base64_decode($maybeUrl, true);
      if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $decoded = $tmp;
    }
    $params[$deeplinkParam] = $decoded;
  }

  $glue = (strpos($base, '?') === false) ? '?' : '&';
  $url = $base . $glue . http_build_query($params);
  return [$url, null];
}

/* -------------------------- Redirectek --------------------------- */

function impactshop_add_rewrites() {
  add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
  add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[] = 'impactshop_go';
  $vars[] = 'impactshop_deal';
  return $vars;
}
add_filter('query_vars','impactshop_add_query_vars');

add_action('template_redirect', function(){
  if (get_query_var('impactshop_go'))   { impactshop_handle_go(false); exit; }
  if (get_query_var('impactshop_deal')) { impactshop_handle_go(true);  exit; }
});

/**
 * Közös handler: /go (deeplink opcionális) és /go-deal (deeplink várható).
 * Logika:
 *  - shop + d1 kötelező
 *  - ha van API token és tudjuk a campaign_id-t → API-val generálunk linket
 *  - különben visszaesünk a régi go.dognet.com linképítésre
 *  - /go-deal esetén, ha nincs 'u', megpróbáljuk a CSV 'product_url' mezőt használni; ha az sincs, sima kampánylinket adunk vissza
 */
function impactshop_handle_go($is_deal) {
  $shop = isset($_GET['shop']) ? sanitize_text_field($_GET['shop']) : '';
  $ngo  = isset($_GET['d1'])   ? sanitize_text_field($_GET['d1'])   : '';
  $u    = isset($_GET['u'])    ? sanitize_text_field($_GET['u'])    : '';
  $amb  = isset($_GET['amb'])  ? sanitize_text_field($_GET['amb'])  : '';
  $src  = isset($_GET['src'])  ? sanitize_text_field($_GET['src'])  : 'impactshop';

  if (!$shop || !$ngo) impactshop_error('Hiányzó paraméter (shop/d1).');

  $row = impactshop_find_shop($shop);
  if (!$row) impactshop_error('Ismeretlen shop: '.esc_html($shop));

  // cél deeplink (ha /go-deal és nincs u: próbáljuk a CSV fallbacket)
  $targetUrl = '';
  if ($is_deal) {
    if ($u) {
      $targetUrl = $u;
    } else {
      $fallback = trim($row['product_url'] ?? '');
      if ($fallback) $targetUrl = $fallback;
    }
  }

  // Próbálkozzunk API-val
  $final = null;
  if (DOGNET_API_TOKEN) {
    // campaign_id a dognet_base cid-jéből
    $cid = dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
    if ($cid) {
      // ha 'u' base64, dekódoljuk
      $deeplink = $targetUrl;
      if ($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
        $tmp = base64_decode($deeplink, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
      }
      $api = dognet_api_generate_link($cid, $deeplink, $ngo, '');
      if (!is_wp_error($api) && $api) {
        $final = $api;
      }
    }
  }

  // Ha nincs API link, legacy build
  if (!$final) {
    list($legacy,) = impactshop_build_legacy_go_url($row, $ngo, $targetUrl);
    if ($legacy) $final = $legacy;
  }
  if (!$final) impactshop_error('Nem sikerült a partner linket előállítani.');

  // amb/src/utm kiküldése (ha még nincs a célnál)
  $add = [];
  if ($amb && strpos($final,'amb=')===false) $add['amb']=$amb;
  if ($src && strpos($final,'src=')===false) $add['src']=$src;
  if (strpos($final,'utm_source=')===false) $add['utm_source']='sharity';
  if (strpos($final,'utm_medium=')===false) $add['utm_medium']='impactshop';
  if ($add) $final .= (strpos($final,'?')===false ? '?' : '&') . http_build_query($add);

  wp_redirect($final, 307);
  exit;
}

function impactshop_error($msg) {
  status_header(400);
  wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'.esc_html($msg).'</div>', 'WordPress › hiba');
}
Ezt mondtad:
Feltöltött kép
Feltöltött kép
Feltöltött kép
Nézd át ebbe a chatben a jelenlegi beállításokat. Az új nippet nem jelenít meg semmit, csak a short code látszik. Emlékezz a projektben lévő csevegésekre hibákra megoldásokra. 
A ChatGPT ezt mondta:

Gondolkozás
Rendben, végigfutottam a most belőtt állapotot, és itt a lényeg:
Miért látszik csak a shortcode szöveg?
Az új snippet csak a /go és /go-deal redirect + Dognet API részét tartalmazza. Nem regisztrál shortocode-okat, és nincs benne a katalógus/scroller megjelenítő UI. Közben a régi snippetet kikapcsoltad (amit korábban kérdeztél is), abban voltak a
add_shortcode('impactshop_scroller', ...)
add_shortcode('impactshop_catalog', ...)
add_shortcode('impactshop_diag', ...)
Ha a shortcode definíciók nincsenek betöltve, a WordPress nem “érti” a kódot, ezért szó szerint kirakja a [impactshop_*] szöveget.
Mitől nem omlik össze semmi? – Ütközések
A régi és az új snippet ugyanazokat a függvényneveket is használja (pl. impactshop_settings, impactshop_fetch_csv_assoc, impactshop_find_shop stb.). Ha mindkettő aktív, redeclare fatal lehet; ha pedig csak az új él, akkor meg nincs UI.
Biztos megoldás, 2 út
A) Gyors és kockázatmentes (javasolt most)
Kapcsold vissza a régi snippetet (amelyikben a shortocode-ok és a UI vannak).
Hagyd aktívan az új „API-s redirect” snippetet is,
DE az új snippetben tedd a duplán definiált függvényeket feltételesre, hogy ne ütközzenek. Ez csak pár sor:
Az ÚJ snippetben minden ilyen definíció elé tedd:
if (!function_exists('impactshop_settings')) {
  function impactshop_settings() { /* ... a jelenlegi tartalmad ... */ }
}

if (!function_exists('impactshop_slugify_header')) {
  function impactshop_slugify_header($s) { /* ... */ }
}

if (!function_exists('impactshop_fetch_csv_assoc')) {
  function impactshop_fetch_csv_assoc($url, $cache_key, $ttl) { /* ... */ }
}

if (!function_exists('impactshop_get_shops')) {
  function impactshop_get_shops() { /* ... */ }
}

if (!function_exists('impactshop_find_shop')) {
  function impactshop_find_shop($slug) { /* ... */ }
}

if (!function_exists('impactshop_error')) {
  function impactshop_error($msg) { /* ... */ }
}
A rewrite-regisztrációra ugyanez:
if (!function_exists('impactshop_add_rewrites')) {
  function impactshop_add_rewrites() {
    add_rewrite_rule('^go/?$', 'index.php?impactshop_go=1', 'top');
    add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
  }
  add_action('init','impactshop_add_rewrites');
}

add_action('template_redirect', function(){
  if (get_query_var('impactshop_go'))   { impactshop_handle_go(false); exit; }
  if (get_query_var('impactshop_deal')) { impactshop_handle_go(true);  exit; }
});
Ezzel a régi snippet adja a UI + shortocode-okat, az új snippet adja a Dognet API-s linkgenerálást és a /go útvonalakat, ütközés nélkül.
Rewrite flush: egyszer nyisd meg admin nélkül:
https://app.sharity.hu/?impactshop_refresh=1
(benne van a régi snippetben ez a kézi flush hook)
B) Egyetlen „összevont” snippet (később)
Összefűzhetjük a kettőt egy fájlba (UI + API + redirect), és a duplikátumokat kiszedjük. Ez tisztább, de most több lépés, úgyhogy a fenti A) a gyors nyerő megoldás.
Fillout és CSV állapot – gyors ellenőrzőlista
A Shops táblában már van product_url, dognet_base, pdognet_deeplink_param=url — ez jó.
A Fillout redirect /go-deal-re megy, és átadja: shop, d1, u.
A u lehet plain URL is, nem kötelező base64 (a kód kezeli mindkettőt).
Ha u üres, /go-deal a CSV product_url mezőt fallbacknek használja (lásd a screenshotodon is megvan).
Mit fogsz látni a végén?
A shortcode-ok újra rendesen renderelődnek (kártyák, scroller).
Kattintáskor, ha a látogató már hozza a d1-et (NGO kód), a /go azonnal Dognet API-val (ha van token) vagy fallbackkel generál linket, és átadja az UTM-eket + amb/src-et is.
Ha nincs még d1, akkor a logó Fillout-ra visz, onnan pedig a /go-deal → Dognet.
Gyors hibalista (ha mégsem jó)
Shortcode szöveg látszik → a régi snippet ki van kapcsolva VAGY a shortocode-regisztráció nincs betöltve.
404 a /go /go-deal-re → rewrite flush hiányzik (nyisd meg a ?impactshop_refresh=1 URL-t).
Dognet API „no_token” → a DOGNET_API_TOKEN nincs kitöltve.
„Ismeretlen shop” → a shop_slug nem egyezik a CSV-ben szereplővel (kétszer ellenőrizd).
Ha szeretnéd, megírom a teljes „összevont” egy-snippet verziót is, de most a leggyorsabb az A) út: kapcsold vissza a régi snippetet, és az újban tedd be a function_exists guardokat. Utána frissíts egyet a ?impactshop_refresh=1-gyel és tesztelj egy Vision Express/REGIO/Árukereső kattintást.
Ezt mondtad:
B) Egyetlen „összevont” snippet (később)
lehet egy összevont snippet de külön töltöm fel és aktiválom, mert a régi biztosan működik. Kérem egyben. A kódokat mindig egyben kérem mert nem értek hozzá és egyetlen elgépelés terror t okozhat. Ezt jegyezd meg a jövőre nézve
A ChatGPT ezt mondta:
Mentett memória frissítve

Gondolkozás
érkezik az összevont, „minden egyben” snippet. úgy raktam össze, hogy:
tartalmazza a UI shortocode-okat (scroller, katalógus, diagnosztika),
kezeli a /go és /go-deal útvonalakat,
először a Dognet API-val generál linket (ha van token), különben visszaesik a régi go.dognet.com-os építésre,
függvény- és hook-guardokkal fut akkor is, ha a régi snippet párhuzamosan aktív (átmeneti időre), így nem lesz „cannot redeclare”.
Minimális teendő: a legelső soroknál töltsd ki a DOGNET_API_TOKEN-t (Bearer). Teszt után célszerű a régi snippetet kikapcsolni, hogy csak ez maradjon.
<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (UI + Redirect + Dognet API)
 * - Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag]
 * - Endpontok:    /go  és  /go-deal
 * - Linkgenerálás: Dognet Publisher API (ha van token) → fallback: go.dognet.com
 *
 * Beállítás:
 *   define('DOGNET_API_TOKEN', 'PASTE_YOUR_BEARER_TOKEN_HERE');
 *   define('DOGNET_AD_CHANNEL_ID', 0); // 0 = auto: első "Web Site" típusú csatorna
 *
 * Hasznos:
 *   Kézi rewrite flush:  https://app.sharity.hu/?impactshop_refresh=1  (admin bejelentkezve)
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_API_TOKEN'))    define('DOGNET_API_TOKEN', ''); // <-- TÖLTSD KI
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 0); // 0 = auto-pick Web Site

if (!function_exists('impactshop_settings')) {
  function impactshop_settings() {
    return [
      // Shops & Banners CSV (Google Sheets → Publish → CSV)
      'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
      'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=328401803&single=true&output=csv',
      'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
      'cache_ttl'       => 15 * MINUTE_IN_SECONDS,
    ];
  }
}

/* ============================ CSV SEGÉDEK ============================ */

if (!function_exists('impactshop_slugify_header')) {
  function impactshop_slugify_header($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
    $s = strtr($s, $map);
    $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
    return trim($s, '_');
  }
}

if (!function_exists('impactshop_fetch_csv_assoc')) {
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
}

/* ======================= SHOPS & BANNERS MODELL ====================== */

if (!function_exists('impactshop_get_shops_raw')) {
  function impactshop_get_shops_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
  }
}
if (!function_exists('impactshop_get_banners_raw')) {
  function impactshop_get_banners_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  }
}

if (!function_exists('impactshop_get_shops')) {
  function impactshop_get_shops() {
    $rows = impactshop_get_shops_raw();
    $out = [];
    foreach ($rows as $r) {
      $name   = $r['name']       ?? ($r['nev'] ?? '');
      $slug   = $r['shop_slug']  ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
      $cat    = $r['category']   ?? ($r['kategoria'] ?? 'Egyéb');
      $logo   = $r['logo_url']   ?? ($r['logo'] ?? ($r['image'] ?? ''));
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
}

if (!function_exists('impactshop_get_banners')) {
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
}

/* ========================= KÖZÖS SEGÉD ========================= */

if (!function_exists('impactshop_q')) {
  function impactshop_q($key, $def='') {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
  }
}
if (!function_exists('impactshop_find_shop')) {
  function impactshop_find_shop($slug) {
    $slug = trim(strtolower($slug));
    foreach (impactshop_get_shops() as $s) {
      if (strtolower($s['shop_slug']) === $slug) return $s;
    }
    return null;
  }
}
if (!function_exists('impactshop_error')) {
  function impactshop_error($msg) {
    status_header(400);
    wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'WordPress › hiba');
  }
}

/* ====================== DOGNET PUBLISHER API ====================== */

if (!function_exists('dognet_api_request')) {
  function dognet_api_request($method, $path, $body = null) {
    if (!DOGNET_API_TOKEN) return new WP_Error('no_token','Nincs Dognet API token (DOGNET_API_TOKEN).');
    $url  = 'https://api.app.dognet.com/api/v1' . $path;
    $args = [
      'timeout' => 20,
      'headers' => [
        'Authorization' => 'Bearer ' . DOGNET_API_TOKEN,
        'Content-Type'  => 'application/json',
      ],
      'method'  => $method,
    ];
    if ($body !== null) $args['body'] = wp_json_encode($body);
    $resp = wp_remote_request($url, $args);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300) {
      return new WP_Error('api_error', 'Dognet API hiba: '.$code, ['resp'=>$json]);
    }
    return $json;
  }
}

if (!function_exists('dognet_api_pick_ad_channel_id')) {
  function dognet_api_pick_ad_channel_id() {
    if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
    $list = dognet_api_request('GET','/adchannels/');
    if (is_wp_error($list) || empty($list['data'])) return 0;
    foreach ($list['data'] as $c) {
      if (isset($c['type']) && stripos($c['type'],'web') !== false) return intval($c['id']);
    }
    return intval($list['data'][0]['id']);
  }
}

if (!function_exists('dognet_extract_campaign_id_from_base')) {
  function dognet_extract_campaign_id_from_base($dognet_base) {
    if (!$dognet_base) return 0;
    $qs = parse_url($dognet_base, PHP_URL_QUERY);
    parse_str($qs, $parts);
    return isset($parts['cid']) ? intval($parts['cid']) : 0;
  }
}

/**
 * Generál egy affiliate linket a Dognet API-val.
 * - $campaign_id (cid) kötelező
 * - $deeplink opcionális
 * - $d1,$d2 opcionális
 * Vissza: string URL vagy WP_Error
 */
if (!function_exists('dognet_api_generate_link')) {
  function dognet_api_generate_link($campaign_id, $deeplink = '', $d1 = '', $d2 = '') {
    $ad_channel_id = dognet_api_pick_ad_channel_id();
    if (!$ad_channel_id) return new WP_Error('no_channel','Nincs elérhető ad_channel');

    $body = [
      'ad_channel_id' => intval($ad_channel_id),
      'campaign_id'   => intval($campaign_id),
      'url_type'      => 3, // simple
    ];
    if ($deeplink) $body['url']   = $deeplink;
    if ($d1)       $body['data1'] = $d1;
    if ($d2)       $body['data2'] = $d2;

    $json = dognet_api_request('POST','/links/generate', $body);
    if (is_wp_error($json)) return $json;

    foreach (['url','short_url','full_url'] as $k) {
      if (!empty($json[$k])) return $json[$k];
      if (!empty($json['data'][$k])) return $json['data'][$k];
    }
    return new WP_Error('bad_api','Ismeretlen API válasz', $json);
  }
}

/* ==================== LEGACY (go.dognet.com) BUILD =================== */

if (!function_exists('impactshop_build_legacy_go_url')) {
  function impactshop_build_legacy_go_url($shopRow, $ngo_code, $maybeUrl) {
    $base = trim($shopRow['dognet_base'] ?? '');
    if (!$base) return [null, 'Hiányzik a dognet_base a Shops CSV-ben.'];

    $params = [];
    if ($ngo_code) $params['d1'] = $ngo_code;

    $deeplinkParam = $shopRow['deeplink_param'] ?: 'url';
    if (!empty($maybeUrl)) {
      $decoded = $maybeUrl;
      if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $maybeUrl)) {
        $tmp = base64_decode($maybeUrl, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $decoded = $tmp;
      }
      $params[$deeplinkParam] = $decoded;
    }

    $glue = (strpos($base, '?') === false) ? '?' : '&';
    $url = $base . $glue . http_build_query($params);
    return [$url, null];
  }
}

/* ============================ REDIRECTEK ============================ */

if (!function_exists('impactshop_add_rewrites')) {
  function impactshop_add_rewrites() {
    add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
    add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
  }
  add_action('init','impactshop_add_rewrites');
}

if (!function_exists('impactshop_add_query_vars')) {
  function impactshop_add_query_vars($vars) {
    $vars[] = 'impactshop_go';
    $vars[] = 'impactshop_deal';
    return $vars;
  }
  add_filter('query_vars','impactshop_add_query_vars');
}

add_action('template_redirect', function(){
  if (get_query_var('impactshop_go'))   { impactshop_handle_go(false); exit; }
  if (get_query_var('impactshop_deal')) { impactshop_handle_go(true);  exit; }
});

if (!function_exists('impactshop_redirect_with_propagation')) {
  function impactshop_redirect_with_propagation($url, $amb, $src) {
    $add = [];
    if ($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
    if ($src && strpos($url,'src=')===false) $add['src']=$src;
    if (strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
    if (strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
    if ($add) $url .= (strpos($url,'?')===false ? '?' : '&') . http_build_query($add);
    wp_redirect($url, 307);
    exit;
  }
}

if (!function_exists('impactshop_handle_go')) {
  /**
   * /go és /go-deal közös kezelő
   * - shop + d1 kötelező
   * - ha van API token + campaign_id → API
   * - különben legacy go.dognet.com build
   * - /go-deal: ha nincs 'u', próbáljuk a CSV 'product_url'-t; ha az sincs, kampánylinket adunk
   */
  function impactshop_handle_go($is_deal) {
    $shop = impactshop_q('shop');
    $ngo  = impactshop_q('d1');
    $u    = impactshop_q('u');
    $amb  = impactshop_q('amb');
    $src  = impactshop_q('src') ?: 'impactshop';

    if (!$shop || !$ngo) impactshop_error('Hiányzó paraméter (shop/d1).');

    $row = impactshop_find_shop($shop);
    if (!$row) impactshop_error('Ismeretlen shop: '.esc_html($shop));

    // cél deeplink
    $targetUrl = '';
    if ($is_deal) {
      if ($u) {
        $targetUrl = $u;
      } else {
        $fallback = trim($row['product_url'] ?? '');
        if ($fallback) $targetUrl = $fallback;
      }
    }

    // API first
    $final = null;
    if (DOGNET_API_TOKEN) {
      $cid = dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
      if ($cid) {
        $deeplink = $targetUrl;
        if ($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
          $tmp = base64_decode($deeplink, true);
          if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
        }
        $api = dognet_api_generate_link($cid, $deeplink, $ngo, '');
        if (!is_wp_error($api) && $api) $final = $api;
      }
    }

    // Fallback
    if (!$final) {
      list($legacy,) = impactshop_build_legacy_go_url($row, $ngo, $targetUrl);
      if ($legacy) $final = $legacy;
    }
    if (!$final) impactshop_error('Nem sikerült a partner linket előállítani.');

    impactshop_redirect_with_propagation($final, $amb, $src);
  }
}

/* ============================ SHORTCODE-OK ============================ */
/** Scroller */
if (!shortcode_exists('impactshop_scroller')) {
  function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts([
      'category'     => '',
      'inject_every' => 5,
      'speed'        => 30,
    ], $atts);

    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners();
    if (!empty($a['category'])) {
      $catWanted = $a['category'];
      $shops = array_values(array_filter($shops, function($s) use ($catWanted){ return strcasecmp($s['category'],$catWanted)===0; }));
      $banners = array_values(array_filter($banners, function($b) use ($catWanted){ return empty($b['category']) || strcasecmp($b['category'],$catWanted)===0; }));
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
    $stream = array_merge($mixed, $mixed); // végtelenített

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
      .impactshop-item{display:inline-block;margin-right:20px}
    </style>
    <div class="impactshop-scroller">
      <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
        <?php foreach ($stream as $it): ?>
          <?php if ($it['type']==='shop'): $shop=$it['data'];
            if ($d1) {
              $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
            } else {
              $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
            } ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
              </a>
            </div>
          <?php else: $b=$it['data']; ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
                <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
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
}

/** Katalógus */
if (!shortcode_exists('impactshop_catalog')) {
  function impactshop_shortcode_catalog($atts) {
    $a = shortcode_atts([
      'show_tabs' => '1',
      'search'    => '1',
      'per_page'  => '200',
    ], $atts);

    $shops = impactshop_get_shops();
    if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

    usort($shops, function($x,$y){
      $c = strcasecmp($x['category'],$y['category']);
      return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
    });

    $cats = [];
    foreach ($shops as $s) { $cats[$s['category']] = true; }
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
          if ($d1) {
            $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
          } else {
            $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
          } ?>
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
}

/** Diagnosztika */
if (!shortcode_exists('impactshop_diag')) {
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
}

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});
Gyors telepítési jegyzet
DOGNET_API_TOKEN kitölt.
Snippet bekapcsol.
Egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1 (rewrite flush).
Oldalon a [impactshop_catalog] és/vagy [impactshop_scroller] újra renderel, kattintás → Fillout → /go-deal → Dognet API/fallback.
Ha minden oké, a régi snippetet kapcsold ki, hogy tiszta legyen a rendszer.
Folytatásként össze tudunk kötni egy „/go” hívás-loggolást (számláló, toplista), vagy ráköthetjük a nyereményjáték modulodra is — de előbb fussanak a kattintások hiba nélkül.
Ezt mondtad:
Mi az a DOGNET_API_TOKEN és hol találom?
A ChatGPT ezt mondta:

Befejezte a gondolkodást
Ezt mondtad:
Publisher API documentation.pdf
PDF
Mi az a DOGNET_API_TOKEN és hol találom?
A ChatGPT ezt mondta:
A DOGNET_API_TOKEN nem egy előre adott fix kulcs, hanem egy minden nap frissülő hozzáférési token, amit a Dognet Publisher API ad ki belépés után.
A dokumentáció szerint így tudod megszerezni:

Küldj egy POST kérést ide:
https://api.app.dognet.com/api/v1/auth/login
A body így néz ki:
{
  "email" : "a_dognetes_email_címed",
  "password" : "a_dognetes_jelszavad"
}
A válaszban lesz egy token.
Ez a DOGNET_API_TOKEN.
Példa header, ahogy használni kell a további API hívásokban:
Authorization: Bearer [token]
Fontos: a token 24 óráig érvényes, és minden API használatkor automatikusan megújul. Tehát ha naponta van aktivitás, nem jár le.
Hol találod?
Ha van Dognet publisher fiókod, a saját email/jelszó párosoddal bejelentkezve az API-ba így tudsz lekérni friss tokent.
Ha nem akarsz kézzel API-hívást csinálni, érdemes lesz egy kis PHP-kódot vagy Postman-t használni, ami beloginol és kiírja a tokent.
A snippetben a define('DOGNET_API_TOKEN', '...'); részbe ezt az értéket kell bemásolnod.
Szeretnéd, hogy írjak neked egy rövid PHP-snippetet (külön) ami a Dognet email+jelszó alapján automatikusan lekéri és kiírja a friss tokent, hogy onnan bemásolhasd a beállításba?
Ezt mondtad:
https://api.app.dognet.com/api/v1/auth/login
ezt hogy kell? a böngészőben nem csinál semmit.
A ChatGPT ezt mondta:

Gondolkozás
A böngésző „nem csinál semmit”, mert az a link egy POST végpont — sima kattintással (GET) nem hívódik meg. Tokenhez küldeni kell egy JSON-os POST-ot (email + jelszó), és a válaszban kapod a tokent, amit utána minden API-hívásnál Authorization: Bearer <token> fejlécként kell használni.
Mivel kényelmes „egyben kódot” kérsz, itt egy ideiglenes WP-snippet, ami egy adminnak elérhető segéd URL-t ad: beírod a Dognet e-mailedet/jelszavadat, megnyitod /?dognet_token=1, és kiírja a friss tokent. Másold be a snippetben lévő megjegyzés szerint a DOGNET_API_TOKEN helyére, majd TÖRÖLD vagy kapcsold ki ezt a segédet.

<?php
/**
 * Dognet token helper – ADMINNAK ideiglenesen!
 * Lépések:
 * 1) Töltsd ki alább a DOGNET_LOGIN_EMAIL és DOGNET_LOGIN_PASSWORD konstansokat.
 * 2) Aktiváld ezt a snippetet.
 * 3) Nyisd meg bejelentkezett adminként: https://app.sharity.hu/?dognet_token=1
 * 4) Másold ki a kiírt tokent és tedd be a fő snippetbe: define('DOGNET_API_TOKEN', 'ITT_A_TOKEN');
 * 5) Kapcsold KI vagy töröld ezt a segéd snippetet (ne maradjon élesben).
 */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'publisher@example.com'); // <-- IDE A DOGNET EMAIL
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', '********');              // <-- IDE A DOGNET JELSZÓ

add_action('init', function () {
  if (!isset($_GET['dognet_token'])) return;
  if (!is_user_logged_in() || !current_user_can('manage_options')) {
    status_header(403);
    wp_die('<div style="padding:20px;font:14px system-ui">Hozzáférés megtagadva (admin kell).</div>');
  }

  $url = 'https://api.app.dognet.com/api/v1/auth/login'; // POST + JSON body kell
  $body = [
    'email'    => DOGNET_LOGIN_EMAIL,
    'password' => DOGNET_LOGIN_PASSWORD,
  ];
  $resp = wp_remote_post($url, [
    'timeout' => 20,
    'headers' => ['Content-Type' => 'application/json'],
    'body'    => wp_json_encode($body),
  ]);

  if (is_wp_error($resp)) {
    status_header(500);
    wp_die('<pre style="padding:20px">'.esc_html($resp->get_error_message()).'</pre>');
  }

  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);

  if ($code < 200 || $code >= 300 || !is_array($json)) {
    status_header(500);
    wp_die('<pre style="padding:20px">Dognet API hiba ('.$code."):\n".esc_html(print_r($json,true)).'</pre>');
  }

  // A válaszban érkező token a további hívásokhoz kell: Authorization: Bearer <token>
  // (Token 24 óráig érvényes, és minden használatkor meghosszabbodik.)
  $token = $json['token'] ?? ($json['data']['token'] ?? '');
  if (!$token) {
    status_header(500);
    wp_die('<pre style="padding:20px">Nem találtam token mezőt a válaszban:'."\n".esc_html(print_r($json,true)).'</pre>');
  }

  $msg = <<<HTML
  <div style="padding:24px;font:14px/1.5 system-ui">
    <h2 style="margin:0 0 10px">Dognet API token</h2>
    <p><b>Másold be a fő Impact Shop snippetbe:</b></p>
    <pre style="background:#f6f6f6;padding:12px;border-radius:6px;overflow:auto">define('DOGNET_API_TOKEN', '<span style="color:#b00;">{$token}</span>');</pre>
    <p>Ezután <b>kapcsold ki</b> ezt a segéd-snippetet (biztonság!).</p>
  </div>
  HTML;

  wp_die($msg, 'Dognet token');
});
Mi történik a háttérben?
A snippet egy POST https://api.app.dognet.com/api/v1/auth/login kérést küld JSON-nal (email, password), a válaszban kapott token az API-token.
A token 24 óráig érvényes, és minden további API-hívás meghosszabbítja az érvényességét.
A további kéréseknél kötelező a fejléc: Authorization: Bearer <token>.
Ha inkább Postman/cURL útmutatót szeretnél, adok egy rövid „másold-be és fut” verziót is, de ezzel a fenti segéddel a WP-ben két kattintás az egész.
Ezt mondtad:
csináljuk ezt, mert az elején biztos nem lesz 24 óránként aktivitás és nem szeretném hogy leálljon.
A ChatGPT ezt mondta:

Gondolkozás
értem: legyen automata tokenkezelés, hogy ne álljon le 24 óránként. Adok egy EGYBEN snipetet, ami:
tárolja a Dognet e-mailt/jelszót a snippet elején,
automatikusan belép az API-ba és lekéri + cache-eli a tokent (WordPress transientben),
401 esetén újraloginol és újrapróbálja,
ha az API mégsem elérhető, automatikusan visszaesik a legacy go.dognet.com linkre,
benne van a UI (scroller, katalógus, diag) + /go, /go-deal redirectek.
Tedd be, töltsd ki felül az e-mail/jelszó mezőket, kapcsold be, majd egyszer látogasd meg /?impactshop_refresh=1.
<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (AUTO DOGNET TOKEN + UI + Redirect + Fallback)
 * - Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag]
 * - Endpontok:    /go  és  /go-deal
 * - Linkgenerálás: Dognet API (auto-login tokennel) → fallback: go.dognet.com
 *
 * Telepítés:
 *   1) Töltsd ki az alábbi DOGNET_LOGIN_EMAIL / DOGNET_LOGIN_PASSWORD értékeket.
 *   2) (Opció) Ha van fix tokened: töltsd ki a DOGNET_API_TOKEN-t. Ha üres, a kód API-loginból szerzi be.
 *   3) Aktiválás után egyszer: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

/** Ha FIX tokennel akarsz menni, ide teheted. Üresen hagyva AUTO-LOGIN lesz. */
if (!defined('DOGNET_API_TOKEN'))    define('DOGNET_API_TOKEN', ''); // pl. 'eyJhbGci
Ezt mondtad:
email: office@sharity.hu jelszó: cuXsuj-8wenbo-kimnac
A ChatGPT ezt mondta:
szuper — megcsináltam az egyben, „bedug-és-működik” snipetet.
Ez automatikusan beloginol a Dognet Publisher API-ba a megadott e-mail/jelszóval, cache-eli a tokent (WP transient), 401 esetén újraloginol és újrapróbálja, és ha bármi gáz van az API-val, visszaesik a régi go.dognet.com linképítésre.
Tartalmazza a UI shortocode-okat (scroller, katalógus, diag) és a /go, /go-deal útvonalakat is.
Teendő: másold be egy új snippetként, a kód elején már beírtam a megadott Dognet belépőt. Aktiválás után egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1.

<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (AUTO DOGNET TOKEN + UI + Redirect + Fallback)
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag]
 * Endpontok:    /go  és  /go-deal
 * Link:         Dognet API (auto-login tokennel) → fallback: go.dognet.com
 *
 * Telepítés:
 *  1) Aktiváld a snipetet.
 *  2) Egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1  (rewrite flush)
 *  3) Teszt: [impactshop_catalog] vagy [impactshop_scroller] az oldalon.
 *
 * Biztonsági megjegyzés: ez a snippet a WordPress-ben tárolja a Dognet belépőt.
 * Ha később szeretnéd „kínbiztosra”, át tudjuk tenni wp-config.php CONST-okba is.
 */

/* ============================== KONFIG ============================== */

/** DOGNET belépők – a kérésed alapján kitöltve */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL', 'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');

/** (Opcionális) Fix token – hagyd üresen, mert az AUTO-login kezeli és frissíti */
if (!defined('DOGNET_API_TOKEN')) define('DOGNET_API_TOKEN', ''); // ha üres → auto-login

/** Ha 0: automatikus kiválasztás az első "Web Site" típusú ad channel */
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 0);

/** Caching idők */
if (!defined('IMPACTSHOP_CACHE_TTL')) define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))     define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS); // token 24h, de biztonsági ráhagyással

/* ============================== BEÁLLÍTÁS ============================== */

if (!function_exists('impactshop_settings')) {
  function impactshop_settings() {
    return [
      'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
      'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=328401803&single=true&output=csv',
      'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
      'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
    ];
  }
}

/* ============================ CSV SEGÉDEK ============================ */

if (!function_exists('impactshop_slugify_header')) {
  function impactshop_slugify_header($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
    $s = strtr($s, $map);
    $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
    return trim($s, '_');
  }
}

if (!function_exists('impactshop_fetch_csv_assoc')) {
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
}

/* ======================= SHOPS & BANNERS MODELL ====================== */

if (!function_exists('impactshop_get_shops_raw')) {
  function impactshop_get_shops_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
  }
}
if (!function_exists('impactshop_get_banners_raw')) {
  function impactshop_get_banners_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  }
}

if (!function_exists('impactshop_get_shops')) {
  function impactshop_get_shops() {
    $rows = impactshop_get_shops_raw();
    $out = [];
    foreach ($rows as $r) {
      $name   = $r['name']       ?? ($r['nev'] ?? '');
      $slug   = $r['shop_slug']  ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
      $cat    = $r['category']   ?? ($r['kategoria'] ?? 'Egyéb');
      $logo   = $r['logo_url']   ?? ($r['logo'] ?? ($r['image'] ?? ''));
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
}

if (!function_exists('impactshop_get_banners')) {
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
}

/* ========================= KÖZÖS SEGÉD ========================= */

if (!function_exists('impactshop_q')) {
  function impactshop_q($key, $def='') {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
  }
}
if (!function_exists('impactshop_find_shop')) {
  function impactshop_find_shop($slug) {
    $slug = trim(strtolower($slug));
    foreach (impactshop_get_shops() as $s) {
      if (strtolower($s['shop_slug']) === $slug) return $s;
    }
    return null;
  }
}
if (!function_exists('impactshop_error')) {
  function impactshop_error($msg) {
    status_header(400);
    wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'WordPress › hiba');
  }
}

/* ====================== DOGNET PUBLISHER API ====================== */

if (!function_exists('dognet_token_key')) {
  function dognet_token_key() { return 'dognet_api_token_cache_v1'; }
}

if (!function_exists('dognet_get_token')) {
  /**
   * Token visszaadása:
   *  - ha DOGNET_API_TOKEN definiált → azt használja
   *  - különben transientből olvas; ha nincs/lejárt → login és mentés
   */
  function dognet_get_token($force_refresh = false) {
    if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;

    $key = dognet_token_key();
    if (!$force_refresh) {
      $tok = get_transient($key);
      if ($tok) return $tok;
    }
    // Login
    if (!DOGNET_LOGIN_EMAIL || !DOGNET_LOGIN_PASSWORD) return '';
    $url = 'https://api.app.dognet.com/api/v1/auth/login';
    $resp = wp_remote_post($url, [
      'timeout' => 20,
      'headers' => ['Content-Type' => 'application/json'],
      'body'    => wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]),
    ]);
    if (is_wp_error($resp)) return '';
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300 || !is_array($json)) return '';
    $token = $json['token'] ?? ($json['data']['token'] ?? '');
    if ($token) {
      set_transient($key, $token, DOGNET_TOKEN_TTL);
      return $token;
    }
    return '';
  }
}

if (!function_exists('dognet_api_request')) {
  /**
   * Általános Dognet API hívó – 401 esetén token frissítés és egyszeri retry.
   */
  function dognet_api_request($method, $path, $body = null) {
    $token = dognet_get_token(false);
    $url   = 'https://api.app.dognet.com/api/v1' . $path;

    $do_request = function($bearer) use ($method, $url, $body) {
      $args = [
        'timeout' => 20,
        'headers' => [
          'Authorization' => 'Bearer ' . $bearer,
          'Content-Type'  => 'application/json',
        ],
        'method'  => $method,
      ];
      if (!is_null($body)) $args['body'] = wp_json_encode($body);
      return wp_remote_request($url, $args);
    };

    $resp = $do_request($token);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);

    // 401 → token refresh + retry
    if ($code === 401) {
      $token = dognet_get_token(true);
      if (!$token) return new WP_Error('auth_failed','Dognet API auth sikertelen (401).');
      $resp = $do_request($token);
      if (is_wp_error($resp)) return $resp;
      $code = wp_remote_retrieve_response_code($resp);
    }

    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300) {
      return new WP_Error('api_error', 'Dognet API hiba: '.$code, ['resp'=>$json]);
    }
    return $json;
  }
}

if (!function_exists('dognet_api_pick_ad_channel_id')) {
  function dognet_api_pick_ad_channel_id() {
    if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
    $list = dognet_api_request('GET','/ad-channels');
    if (is_wp_error($list) || empty($list['data'])) return 0;
    foreach ($list['data'] as $c) {
      $type = isset($c['type']) ? strtolower((string)$c['type']) : '';
      if (strpos($type,'web') !== false) return intval($c['id']); // első Web Site
    }
    return intval($list['data'][0]['id']);
  }
}

if (!function_exists('dognet_extract_campaign_id_from_base')) {
  function dognet_extract_campaign_id_from_base($dognet_base) {
    if (!$dognet_base) return 0;
    $qs = parse_url($dognet_base, PHP_URL_QUERY);
    parse_str($qs, $parts);
    return isset($parts['cid']) ? intval($parts['cid']) : 0;
  }
}

if (!function_exists('dognet_api_generate_link')) {
  /**
   * Linkgenerálás a dokumentáció szerint:
   * POST /campaigns/links/generate
   * Body: ad_channel_id, campaign_id, url (opcionális), data1, data2, url_type (1-full, 2-standard, 3-simple)
   */
  function dognet_api_generate_link($campaign_id, $deeplink = '', $d1 = '', $d2 = '') {
    $ad_channel_id = dognet_api_pick_ad_channel_id();
    if (!$ad_channel_id) return new WP_Error('no_channel','Nincs elérhető ad_channel');

    $body = [
      'ad_channel_id' => intval($ad_channel_id),
      'campaign_id'   => intval($campaign_id),
      'url_type'      => 3, // simple
    ];
    if ($deeplink) $body['url']   = $deeplink;
    if ($d1)       $body['data1'] = $d1;
    if ($d2)       $body['data2'] = $d2;

    $json = dognet_api_request('POST','/campaigns/links/generate', $body);
    if (is_wp_error($json)) return $json;

    foreach (['url','short_url','full_url'] as $k) {
      if (!empty($json[$k])) return $json[$k];
      if (!empty($json['data'][$k])) return $json['data'][$k];
    }
    return new WP_Error('bad_api','Ismeretlen API válasz', $json);
  }
}

/* ==================== LEGACY (go.dognet.com) BUILD =================== */

if (!function_exists('impactshop_build_legacy_go_url')) {
  function impactshop_build_legacy_go_url($shopRow, $ngo_code, $maybeUrl) {
    $base = trim($shopRow['dognet_base'] ?? '');
    if (!$base) return [null, 'Hiányzik a dognet_base a Shops CSV-ben.'];

    $params = [];
    if ($ngo_code) $params['d1'] = $ngo_code;

    $deeplinkParam = $shopRow['deeplink_param'] ?: 'url';
    if (!empty($maybeUrl)) {
      $decoded = $maybeUrl;
      // base64 elfogadása
      if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $maybeUrl)) {
        $tmp = base64_decode($maybeUrl, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $decoded = $tmp;
      }
      $params[$deeplinkParam] = $decoded;
    }

    $glue = (strpos($base, '?') === false) ? '?' : '&';
    $url = $base . $glue . http_build_query($params);
    return [$url, null];
  }
}

/* ============================ REDIRECTEK ============================ */

if (!function_exists('impactshop_add_rewrites')) {
  function impactshop_add_rewrites() {
    add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
    add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
  }
  add_action('init','impactshop_add_rewrites');
}

if (!function_exists('impactshop_add_query_vars')) {
  function impactshop_add_query_vars($vars) {
    $vars[] = 'impactshop_go';
    $vars[] = 'impactshop_deal';
    return $vars;
  }
  add_filter('query_vars','impactshop_add_query_vars');
}

add_action('template_redirect', function(){
  if (get_query_var('impactshop_go'))   { impactshop_handle_go(false); exit; }
  if (get_query_var('impactshop_deal')) { impactshop_handle_go(true);  exit; }
});

if (!function_exists('impactshop_redirect_with_propagation')) {
  function impactshop_redirect_with_propagation($url, $amb, $src) {
    $add = [];
    if ($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
    if ($src && strpos($url,'src=')===false) $add['src']=$src;
    if (strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
    if (strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
    if ($add) $url .= (strpos($url,'?')===false ? '?' : '&') . http_build_query($add);
    wp_redirect($url, 307);
    exit;
  }
}

if (!function_exists('impactshop_handle_go')) {
  /**
   * /go és /go-deal közös kezelő
   * - shop + d1 kötelező
   * - ha van campaign_id a CSV base-ben → Dognet API
   * - különben legacy go.dognet.com build
   * - /go-deal: ha nincs 'u', próbáljuk a CSV 'product_url'-t; ha az sincs, kampánylinkre esünk vissza
   */
  function impactshop_handle_go($is_deal) {
    $shop = impactshop_q('shop');
    $ngo  = impactshop_q('d1');
    $u    = impactshop_q('u');
    $amb  = impactshop_q('amb');
    $src  = impactshop_q('src') ?: 'impactshop';

    if (!$shop || !$ngo) impactshop_error('Hiányzó paraméter (shop/d1).');

    $row = impactshop_find_shop($shop);
    if (!$row) impactshop_error('Ismeretlen shop: '.esc_html($shop));

    // cél deeplink
    $targetUrl = '';
    if ($is_deal) {
      if ($u) {
        $targetUrl = $u;
      } else {
        $fallback = trim($row['product_url'] ?? '');
        if ($fallback) $targetUrl = $fallback;
      }
    }

    // API first (csak ha a dognet_base-ből kinyerhető a cid)
    $final = null;
    $cid   = dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
    if ($cid) {
      $deeplink = $targetUrl;
      if ($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
        $tmp = base64_decode($deeplink, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
      }
      $api = dognet_api_generate_link($cid, $deeplink, $ngo, '');
      if (!is_wp_error($api) && $api) $final = $api;
    }

    // Fallback: legacy build
    if (!$final) {
      list($legacy,) = impactshop_build_legacy_go_url($row, $ngo, $targetUrl);
      if ($legacy) $final = $legacy;
    }
    if (!$final) impactshop_error('Nem sikerült a partner linket előállítani.');

    impactshop_redirect_with_propagation($final, $amb, $src);
  }
}

/* ============================ SHORTCODE-OK ============================ */
/** Scroller */
if (!shortcode_exists('impactshop_scroller')) {
  function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts(['category'=>'','inject_every'=>5,'speed'=>30], $atts);

    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners();
    if (!empty($a['category'])) {
      $catWanted = $a['category'];
      $shops = array_values(array_filter($shops, fn($s)=>strcasecmp($s['category'],$catWanted)===0));
      $banners = array_values(array_filter($banners, fn($b)=>empty($b['category'])||strcasecmp($b['category'],$catWanted)===0));
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
    $stream = array_merge($mixed, $mixed); // végtelenítés

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
      .impactshop-item{display:inline-block;margin-right:20px}
    </style>
    <div class="impactshop-scroller">
      <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
        <?php foreach ($stream as $it): ?>
          <?php if ($it['type']==='shop'): $shop=$it['data'];
            if ($d1) {
              $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
            } else {
              $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
            } ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
              </a>
            </div>
          <?php else: $b=$it['data']; ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
                <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
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
}

/** Katalógus */
if (!shortcode_exists('impactshop_catalog')) {
  function impactshop_shortcode_catalog($atts) {
    $a = shortcode_atts(['show_tabs'=>'1','search'=>'1','per_page'=>'200'], $atts);

    $shops = impactshop_get_shops();
    if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

    usort($shops, function($x,$y){
      $c = strcasecmp($x['category'],$y['category']);
      return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
    });

    $cats = [];
    foreach ($shops as $s) { $cats[$s['category']] = true; }
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
          if ($d1) {
            $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
          } else {
            $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
          } ?>
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
}

/** Diagnosztika */
if (!shortcode_exists('impactshop_diag')) {
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
}

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});
Ha fut, jöhet a „finomhangolás” (pl. tokent inkább wp-config konstansból, API-limit figyelés, kattintás-log a saját oldalon), vagy mehetünk a nyereményjáték modulra.
Ezt mondtad:
email: office@sharity.hu jelszó: cuXsuj-8wenbo-kimnac
A ChatGPT ezt mondta:
érkezik a teljes összevont snippet, ami a Dognet API-hoz automatikusan bejelentkezik a megadott adataiddal, mindig frissíti a tokent és felhasználja a linkgeneráláshoz. Így nem kell 24 óránként kézzel cserélni.
⚠️ Fontos: mivel benne van a Dognet fiókod jelszava, ezt a snippetet csak megbízható környezetben használd (WordPress admin), és később érdemes lesz inkább API token tárolással működtetni, nem plain jelszóval.

<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (AUTO DOGNET LOGIN + UI + Redirect + Fallback)
 * - Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag]
 * - Endpontok:    /go  és  /go-deal
 * - Linkgenerálás: Dognet API (auto-login tokennel) → fallback: go.dognet.com
 *
 * Használat:
 *   1) A DOGNET_LOGIN_EMAIL és DOGNET_LOGIN_PASSWORD értéket töltsd ki.
 *   2) Aktiválás után egyszer: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');

if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID',  0); // 0 = auto-pick Web Site

/* ==================== TOKEN KEZELÉS (AUTO LOGIN) ==================== */

function dognet_get_token() {
  $cached = get_transient('dognet_api_token');
  if ($cached) return $cached;

  $url = 'https://api.app.dognet.com/api/v1/auth/login';
  $body = [
    'email'    => DOGNET_LOGIN_EMAIL,
    'password' => DOGNET_LOGIN_PASSWORD,
  ];
  $resp = wp_remote_post($url, [
    'timeout' => 20,
    'headers' => ['Content-Type'=>'application/json'],
    'body'    => wp_json_encode($body),
  ]);
  if (is_wp_error($resp)) return '';
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code<200 || $code>=300 || !is_array($json)) return '';

  $token = $json['token'] ?? ($json['data']['token'] ?? '');
  if ($token) {
    // token 24 óráig jó → cache 20 órára
    set_transient('dognet_api_token', $token, 20*HOUR_IN_SECONDS);
  }
  return $token;
}

function dognet_api_request($method, $path, $body=null) {
  $token = dognet_get_token();
  if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

  $url = 'https://api.app.dognet.com/api/v1'.$path;
  $args = [
    'timeout'=>20,
    'headers'=>[
      'Authorization'=>'Bearer '.$token,
      'Content-Type'=>'application/json',
    ],
    'method'=>$method,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);
  $resp = wp_remote_request($url,$args);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp),true);
  if ($code<200 || $code>=300) {
    // ha lejárt a token, próbáljuk újraloginolni
    delete_transient('dognet_api_token');
    $token = dognet_get_token();
    if ($token) {
      return dognet_api_request($method,$path,$body);
    }
    return new WP_Error('api_error','Dognet API hiba '.$code);
  }
  return $json;
}

/* ==================== API SEGÉDEK ==================== */

function dognet_api_pick_ad_channel_id() {
  if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
  $list = dognet_api_request('GET','/ad-channels');
  if (is_wp_error($list) || empty($list['data'])) return 0;
  foreach ($list['data'] as $c) {
    if (isset($c['type']) && stripos($c['type'],'web')!==false) return intval($c['id']);
  }
  return intval($list['data'][0]['id']);
}

function dognet_extract_campaign_id_from_base($dognet_base) {
  if (!$dognet_base) return 0;
  $qs = parse_url($dognet_base, PHP_URL_QUERY);
  parse_str($qs,$parts);
  return isset($parts['cid']) ? intval($parts['cid']) : 0;
}

function dognet_api_generate_link($campaign_id,$deeplink='',$d1='',$d2='') {
  $ad_channel_id = dognet_api_pick_ad_channel_id();
  if (!$ad_channel_id) return new WP_Error('no_channel','Nincs ad_channel');
  $body = [
    'ad_channel_id'=>$ad_channel_id,
    'campaign_id'=>intval($campaign_id),
    'url_type'=>3,
  ];
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

/* === a további rész ugyanaz mint az előző összevont snippetben: CSV, shops, banners, shortocode-ok, /go handler stb. === */
Így fog működni:
Amikor először kell Dognet link, a snippet beloginol az API-ba a megadott e-mail/jelszó párossal.
A tokent cache-eli 20 órára, minden használatkor frissíti → nem jár le.
Ha mégis hibát kap (lejárt/érvénytelen), automatikusan újraloginol és újrapróbálja.
Ha semmiképp nem megy, legacy go.dognet.com linkre esik vissza, így nem áll le a rendszer.
Szeretnéd, hogy kiegészítsem neked a snippet teljes kódját egyben (CSV olvasás, shortocode-ok, redirect handler), vagy elég most csak a token-kezelés része, amit be kell illesztened a korábbi összevont snippet elejére?
Ezt mondtad:
Egyben kérem
A ChatGPT ezt mondta:
jön a teljes, EGYBEN összevont snippet. ez:
rendereli a UI-t ([impactshop_scroller], [impactshop_catalog], [impactshop_diag]);
kezeli a /go és /go-deal útvonalakat;
a Dognet Publisher API-ba automatikusan beloginol (a lent megadott e-mail/jelszóval), a tokent cache-eli és megújítja, 401-re újrapróbál;
ha az API nem elérhető, fallback a régi go.dognet.com URL-építésre;
ütközésbiztos: function_exists/shortcode_exists guardokkal fut akkor is, ha a régi snippet még aktív.
Telepítés: tedd be új snippetként, aktiváld, majd egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1.
<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (AUTO DOGNET TOKEN + UI + Redirect + Fallback)
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag]
 * Endpontok:    /go  és  /go-deal
 * Link:         Dognet API (auto-login, auto-refresh) → fallback: go.dognet.com
 *
 * Lépések:
 *  1) Hagyd benne a lenti DOGNET belépőket (vagy tedd wp-config-ba konstansként).
 *  2) Aktiválás után egyszer: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

/** DOGNET belépők – kérésre kitöltve */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');

/** (Opcionális) Fix token – üresen hagyva auto-login működik */
if (!defined('DOGNET_API_TOKEN')) define('DOGNET_API_TOKEN', ''); // ha üres → auto-login

/** Ha 0: automatikus kiválasztás az első "Web Site" jellegű ad channel */
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 0);

/** Cache idők */
if (!defined('IMPACTSHOP_CACHE_TTL')) define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))     define('DOGNET_TOKEN_TTL',     20 * HOUR_IN_SECONDS); // 24h helyett ráhagyás

/* ============================== BEÁLLÍTÁS ============================== */

if (!function_exists('impactshop_settings')) {
  function impactshop_settings() {
    return [
      'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
      'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=328401803&single=true&output=csv',
      'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
      'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
    ];
  }
}

/* ============================ CSV SEGÉDEK ============================ */

if (!function_exists('impactshop_slugify_header')) {
  function impactshop_slugify_header($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
    $s = strtr($s, $map);
    $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
    return trim($s, '_');
  }
}

if (!function_exists('impactshop_fetch_csv_assoc')) {
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
}

/* ======================= SHOPS & BANNERS MODELL ====================== */

if (!function_exists('impactshop_get_shops_raw')) {
  function impactshop_get_shops_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
  }
}
if (!function_exists('impactshop_get_banners_raw')) {
  function impactshop_get_banners_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  }
}

if (!function_exists('impactshop_get_shops')) {
  function impactshop_get_shops() {
    $rows = impactshop_get_shops_raw();
    $out = [];
    foreach ($rows as $r) {
      $name   = $r['name']       ?? ($r['nev'] ?? '');
      $slug   = $r['shop_slug']  ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
      $cat    = $r['category']   ?? ($r['kategoria'] ?? 'Egyéb');
      $logo   = $r['logo_url']   ?? ($r['logo'] ?? ($r['image'] ?? ''));
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
}

if (!function_exists('impactshop_get_banners')) {
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
}

/* ========================= KÖZÖS SEGÉD ========================= */

if (!function_exists('impactshop_q')) {
  function impactshop_q($key, $def='') {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
  }
}
if (!function_exists('impactshop_find_shop')) {
  function impactshop_find_shop($slug) {
    $slug = trim(strtolower($slug));
    foreach (impactshop_get_shops() as $s) {
      if (strtolower($s['shop_slug']) === $slug) return $s;
    }
    return null;
  }
}
if (!function_exists('impactshop_error')) {
  function impactshop_error($msg) {
    status_header(400);
    wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'WordPress › hiba');
  }
}

/* ====================== DOGNET PUBLISHER API ====================== */

if (!function_exists('dognet_token_key')) {
  function dognet_token_key() { return 'dognet_api_token_cache_v1'; }
}

if (!function_exists('dognet_get_token')) {
  /**
   * Token visszaadása:
   *  - ha DOGNET_API_TOKEN konstans adott → azt használja (felülír mindent)
   *  - különben transientből olvas; ha nincs/lejárt → login és mentés
   */
  function dognet_get_token($force_refresh = false) {
    if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;

    $key = dognet_token_key();
    if (!$force_refresh) {
      $tok = get_transient($key);
      if ($tok) return $tok;
    }
    // Login
    if (!DOGNET_LOGIN_EMAIL || !DOGNET_LOGIN_PASSWORD) return '';
    $url = 'https://api.app.dognet.com/api/v1/auth/login';
    $resp = wp_remote_post($url, [
      'timeout' => 20,
      'headers' => ['Content-Type' => 'application/json'],
      'body'    => wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]),
    ]);
    if (is_wp_error($resp)) return '';
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300 || !is_array($json)) return '';
    $token = $json['token'] ?? ($json['data']['token'] ?? '');
    if ($token) {
      set_transient($key, $token, DOGNET_TOKEN_TTL);
      return $token;
    }
    return '';
  }
}

if (!function_exists('dognet_api_request')) {
  /**
   * Általános Dognet API hívó – 401 esetén token frissítés és egyszeri retry.
   */
  function dognet_api_request($method, $path, $body = null) {
    $token = dognet_get_token(false);
    if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

    $url   = 'https://api.app.dognet.com/api/v1' . $path;

    $do_request = function($bearer) use ($method, $url, $body) {
      $args = [
        'timeout' => 20,
        'headers' => [
          'Authorization' => 'Bearer ' . $bearer,
          'Content-Type'  => 'application/json',
        ],
        'method'  => $method,
      ];
      if (!is_null($body)) $args['body'] = wp_json_encode($body);
      return wp_remote_request($url, $args);
    };

    $resp = $do_request($token);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);

    // 401 → token refresh + retry
    if ($code === 401) {
      $token = dognet_get_token(true);
      if (!$token) return new WP_Error('auth_failed','Dognet API auth sikertelen (401).');
      $resp = $do_request($token);
      if (is_wp_error($resp)) return $resp;
      $code = wp_remote_retrieve_response_code($resp);
    }

    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300) {
      return new WP_Error('api_error', 'Dognet API hiba: '.$code, ['resp'=>$json]);
    }
    return $json;
  }
}

if (!function_exists('dognet_api_pick_ad_channel_id')) {
  function dognet_api_pick_ad_channel_id() {
    if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
    $list = dognet_api_request('GET','/ad-channels');
    if (is_wp_error($list) || empty($list['data'])) return 0;
    foreach ($list['data'] as $c) {
      $type = isset($c['type']) ? strtolower((string)$c['type']) : '';
      if (strpos($type,'web') !== false) return intval($c['id']); // első Web Site
    }
    return intval($list['data'][0]['id']);
  }
}

if (!function_exists('dognet_extract_campaign_id_from_base')) {
  function dognet_extract_campaign_id_from_base($dognet_base) {
    if (!$dognet_base) return 0;
    $qs = parse_url($dognet_base, PHP_URL_QUERY);
    parse_str($qs, $parts);
    return isset($parts['cid']) ? intval($parts['cid']) : 0;
  }
}

if (!function_exists('dognet_api_generate_link')) {
  /**
   * Linkgenerálás:
   * POST /campaigns/links/generate
   * Body: ad_channel_id, campaign_id, url (opcionális), data1, data2, url_type (1=full,2=standard,3=simple)
   */
  function dognet_api_generate_link($campaign_id, $deeplink = '', $d1 = '', $d2 = '') {
    $ad_channel_id = dognet_api_pick_ad_channel_id();
    if (!$ad_channel_id) return new WP_Error('no_channel','Nincs elérhető ad_channel');

    $body = [
      'ad_channel_id' => intval($ad_channel_id),
      'campaign_id'   => intval($campaign_id),
      'url_type'      => 3, // simple
    ];
    if ($deeplink) $body['url']   = $deeplink;
    if ($d1)       $body['data1'] = $d1;
    if ($d2)       $body['data2'] = $d2;

    $json = dognet_api_request('POST','/campaigns/links/generate', $body);
    if (is_wp_error($json)) return $json;

    foreach (['url','short_url','full_url'] as $k) {
      if (!empty($json[$k])) return $json[$k];
      if (!empty($json['data'][$k])) return $json['data'][$k];
    }
    return new WP_Error('bad_api','Ismeretlen API válasz', $json);
  }
}

/* ==================== LEGACY (go.dognet.com) BUILD =================== */

if (!function_exists('impactshop_build_legacy_go_url')) {
  function impactshop_build_legacy_go_url($shopRow, $ngo_code, $maybeUrl) {
    $base = trim($shopRow['dognet_base'] ?? '');
    if (!$base) return [null, 'Hiányzik a dognet_base a Shops CSV-ben.'];

    $params = [];
    if ($ngo_code) $params['d1'] = $ngo_code;

    $deeplinkParam = $shopRow['deeplink_param'] ?: 'url';
    if (!empty($maybeUrl)) {
      $decoded = $maybeUrl;
      // base64 elfogadása
      if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $maybeUrl)) {
        $tmp = base64_decode($maybeUrl, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $decoded = $tmp;
      }
      $params[$deeplinkParam] = $decoded;
    }

    $glue = (strpos($base, '?') === false) ? '?' : '&';
    $url = $base . $glue . http_build_query($params);
    return [$url, null];
  }
}

/* ============================ REDIRECTEK ============================ */

if (!function_exists('impactshop_add_rewrites')) {
  function impactshop_add_rewrites() {
    add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
    add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
  }
  add_action('init','impactshop_add_rewrites');
}

if (!function_exists('impactshop_add_query_vars')) {
  function impactshop_add_query_vars($vars) {
    $vars[] = 'impactshop_go';
    $vars[] = 'impactshop_deal';
    return $vars;
  }
  add_filter('query_vars','impactshop_add_query_vars');
}

add_action('template_redirect', function(){
  if (get_query_var('impactshop_go'))   { impactshop_handle_go(false); exit; }
  if (get_query_var('impactshop_deal')) { impactshop_handle_go(true);  exit; }
});

if (!function_exists('impactshop_redirect_with_propagation')) {
  function impactshop_redirect_with_propagation($url, $amb, $src) {
    $add = [];
    if ($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
    if ($src && strpos($url,'src=')===false) $add['src']=$src;
    if (strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
    if (strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
    if ($add) $url .= (strpos($url,'?')===false ? '?' : '&') . http_build_query($add);
    wp_redirect($url, 307);
    exit;
  }
}

if (!function_exists('impactshop_handle_go')) {
  /**
   * /go és /go-deal közös kezelő
   * - shop + d1 kötelező
   * - ha van campaign_id a CSV base-ben → Dognet API
   * - különben legacy go.dognet.com build
   * - /go-deal: ha nincs 'u', próbáljuk a CSV 'product_url'-t; ha az sincs, kampánylinkre esünk vissza
   */
  function impactshop_handle_go($is_deal) {
    $shop = impactshop_q('shop');
    $ngo  = impactshop_q('d1');
    $u    = impactshop_q('u');
    $amb  = impactshop_q('amb');
    $src  = impactshop_q('src') ?: 'impactshop';

    if (!$shop || !$ngo) impactshop_error('Hiányzó paraméter (shop/d1).');

    $row = impactshop_find_shop($shop);
    if (!$row) impactshop_error('Ismeretlen shop: '.esc_html($shop));

    // cél deeplink
    $targetUrl = '';
    if ($is_deal) {
      if ($u) {
        $targetUrl = $u;
      } else {
        $fallback = trim($row['product_url'] ?? '');
        if ($fallback) $targetUrl = $fallback;
      }
    }

    // API first (ha a dognet_base-ből kinyerhető a cid)
    $final = null;
    $cid   = dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
    if ($cid) {
      $deeplink = $targetUrl;
      if ($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
        $tmp = base64_decode($deeplink, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
      }
      $api = dognet_api_generate_link($cid, $deeplink, $ngo, '');
      if (!is_wp_error($api) && $api) $final = $api;
    }

    // Fallback: legacy build
    if (!$final) {
      list($legacy,) = impactshop_build_legacy_go_url($row, $ngo, $targetUrl);
      if ($legacy) $final = $legacy;
    }
    if (!$final) impactshop_error('Nem sikerült a partner linket előállítani.');

    impactshop_redirect_with_propagation($final, $amb, $src);
  }
}

/* ============================ SHORTCODE-OK ============================ */
/** Scroller */
if (!shortcode_exists('impactshop_scroller')) {
  function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts([
      'category'     => '',
      'inject_every' => 5,
      'speed'        => 30,
    ], $atts);

    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners();
    if (!empty($a['category'])) {
      $catWanted = $a['category'];
      $shops = array_values(array_filter($shops, function($s) use ($catWanted){ return strcasecmp($s['category'],$catWanted)===0; }));
      $banners = array_values(array_filter($banners, function($b) use ($catWanted){ return empty($b['category']) || strcasecmp($b['category'],$catWanted)===0; }));
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
    $stream = array_merge($mixed, $mixed); // végtelenített

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
      .impactshop-item{display:inline-block;margin-right:20px}
    </style>
    <div class="impactshop-scroller">
      <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
        <?php foreach ($stream as $it): ?>
          <?php if ($it['type']==='shop'): $shop=$it['data'];
            if ($d1) {
              $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
            } else {
              $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
            } ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
              </a>
            </div>
          <?php else: $b=$it['data']; ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
                <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
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
}

/** Katalógus */
if (!shortcode_exists('impactshop_catalog')) {
  function impactshop_shortcode_catalog($atts) {
    $a = shortcode_atts([
      'show_tabs' => '1',
      'search'    => '1',
      'per_page'  => '200',
    ], $atts);

    $shops = impactshop_get_shops();
    if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

    usort($shops, function($x,$y){
      $c = strcasecmp($x['category'],$y['category']);
      return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
    });

    $cats = [];
    foreach ($shops as $s) { $cats[$s['category']] = true; }
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
          if ($d1) {
            $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
          } else {
            $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
          } ?>
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
}

/** Diagnosztika */
if (!shortcode_exists('impactshop_diag')) {
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
}

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});
ha kész, teszteld egy olyan shop kártyával, ahol a Shops CSV-ben a dognet_base tartalmaz cid-et (pl. ...?cid=223...). a kattintás menete: logó → ha van d1, azonnal /go → Dognet API link → átküldés (UTM + amb/src továbbmegy); ha nincs d1, a logó Fillout-ra visz, onnan /go-deal → Dognet API/fallback. következő körben csinálhatunk kattintás-logot és/ v. „nyereményjáték” összekötést.
Ezt mondtad:
Működik, de a Shop csv Banners mezője nem töltődik fel: /**
 * ImpactShop – Deals → Banners (TOP3 akció / shop) – flush fix
 * Forrás:  Shops!A:Z  (kötelező: shop_slug, deals_feed; ajánlott: category)
 * Cél:     Banners!A:D (img, href, label, category)
 * Kattintás: Fillout link (shop + base64(u)) → /go-deal a site-on
 */

const FILLOUT_BASE      = 'https://form.fillout.com/t/eM61RLkz6jus';
const SHEET_SHOPS       = 'Shops';
const SHEET_BANNERS     = 'Banners';
const TOP_N_PER_SHOP    = 3;      // kért TOP 3
const CONNECT_TIMEOUT_S = 30;
const READ_TIMEOUT_S    = 120;

/* -------- tipikus mezőnevek -------- */
const TITLE_TAGS = ['title','name','PRODUCTNAME','PRODUCT','ITEM_NAME'];
const URL_TAGS   = ['url','link','URL','ITEM_URL'];
const IMG_TAGS   = ['image','image_url','IMGURL','image_link','IMGURL_ALTERNATIVE'];
const CAT_TAGS   = ['category','CATEGORYTEXT','CATEGORY','DEPARTMENT','google_product_category'];
const PRICE_TAGS     = ['price','PRICE','price_vat','PRICE_VAT','our_price','sale_price','SALE_PRICE','final_price'];
const OLDPRICE_TAGS  = ['old_price','OLD_PRICE','price_before','base_price','original_price','ORIGINAL_PRICE','PRICE_BEFORE'];
const SALEPRICE_TAGS = ['sale_price','SALE_PRICE'];

/* -------- segédek -------- */
function impactshop_buildFilloutHref(shopSlug, productUrl) {
  const u = Utilities.base64Encode(productUrl);
  const params = shop=${encodeURIComponent(shopSlug)}&u=${encodeURIComponent(u)};
  return ${FILLOUT_BASE}?${params};
}

function sanitizeXml(xml) {
  xml = xml.replace(/<!DOCTYPE[\s\S]*?\[[\s\S]*?\]>/gi, '');
  xml = xml.replace(/<!DOCTYPE[^>]*>/gi, '');
  xml = xml.replace(/<!ENTITY[\s\S]*?>/gi, '');
  xml = xml.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, '');
  xml = xml.replace(/&(?!amp;|lt;|gt;|quot;|apos;)[a-zA-Z][\w-]*;/g, '');
  return xml;
}

function fetchText(url) {
  const needsEncoding = /[^\x20-\x7E]/.test(url) && !/%[0-9A-Fa-f]{2}/.test(url);
  const safeUrl = needsEncoding ? encodeURI(url) : url;
  const resp = UrlFetchApp.fetch(safeUrl, {
    muteHttpExceptions: true,
    followRedirects: true,
    validateHttpsCertificates: true,
    escaping: false,
    contentType: 'application/xml',
    connectTimeout: CONNECT_TIMEOUT_S * 1000,
    timeout: READ_TIMEOUT_S * 1000,
  });
  const code = resp.getResponseCode();
  if (code < 200 || code >= 300) throw new Error('HTTP ' + code + ' for ' + safeUrl);
  return resp.getContentText();
}

function num(x) {
  if (x == null) return NaN;
  let s = String(x).replace(/\s+/g, '').replace(/[^\d.,-]/g, '');
  const lastComma = s.lastIndexOf(','), lastDot = s.lastIndexOf('.');
  if (lastComma > -1 && lastDot > -1) {
    s = (lastComma > lastDot) ? s.replace(/\./g,'').replace(',', '.') : s.replace(/,/g,'');
  } else {
    s = s.replace(',', '.');
  }
  const n = parseFloat(s);
  return isFinite(n) ? n : NaN;
}

function pickText(el, names) {
  for (const n of names) {
    const c = el.getChild(n);
    if (c) {
      const t = (c.getText() || '').trim();
      if (t) return t;
    }
  }
  return '';
}
function pickNum(el, names) {
  const t = pickText(el, names);
  const n = num(t);
  return isFinite(n) ? n : NaN;
}

/* -------- XML → tárgyiasított tételek -------- */
function parseDealsFromXml(xmlText, shopSlug, fallbackCategory) {
  xmlText = sanitizeXml(xmlText);
  const doc  = XmlService.parse(xmlText);
  const root = doc.getRootElement();

  const ITEM_NAMES = ['product','item','offer','entry','SHOPITEM'];
  const items = [];
  (function walk(el) {
    if (ITEM_NAMES.includes(el.getName())) items.push(el);
    el.getChildren().forEach(walk);
  })(root);

  const out = [];
  for (const it of items) {
    const title = pickText(it, TITLE_TAGS);
    const url   = pickText(it, URL_TAGS);
    const img   = pickText(it, IMG_TAGS);
    const cat   = pickText(it, CAT_TAGS) || fallbackCategory || '';
    if (!title || !url || !img) continue;

    const p  = pickNum(it, PRICE_TAGS);
    let   op = pickNum(it, OLDPRICE_TAGS);
    const sp = pickNum(it, SALEPRICE_TAGS);

    if (!isFinite(op) && isFinite(sp) && isFinite(p) && sp < p) op = p;
    let price = p;
    if (!isFinite(price) && isFinite(sp)) price = sp;
    if (!isFinite(price) && isFinite(op)) { price = op; op = NaN; }

    let discount = 0;
    if (isFinite(op) && isFinite(price) && op > 0 && price < op) {
      discount = (op - price) / op;
    } else if (isFinite(sp) && isFinite(p) && p > 0 && sp < p) {
      discount = (p - sp) / p;
    }

    out.push({
      img,
      href: impactshop_buildFilloutHref(shopSlug, url),
      label: title.length > 90 ? (title.substring(0, 87) + '…') : title,
      category: cat,
      discount
    });
  }
  return out;
}

/* -------- főfüggvény – flush-sel -------- */
function impactshop_dealsToBanners() {
  const ss      = SpreadsheetApp.getActive();
  const shShops = ss.getSheetByName(SHEET_SHOPS);
  const shBann  = ss.getSheetByName(SHEET_BANNERS);
  if (!shShops || !shBann) throw new Error('Hiányzik a Shops vagy Banners sheet.');

  shBann.clearContents();
  shBann.getRange(1,1,1,4).setValues([['img','href','label','category']]);
  SpreadsheetApp.flush();

  const data = shShops.getDataRange().getValues();
  const head = data[0].map(h => (''+h).trim().toLowerCase().replace(/\s+/g,'_'));
  const col  = (name) => head.indexOf(name);

  const cSlug = col('shop_slug');
  const cFeed = col('deals_feed');
  const cCat  = col('category');
  if (cSlug < 0 || cFeed < 0) throw new Error('A Shops lapon kell shop_slug és deals_feed oszlop.');

  let writeRow = 2;

  for (let r = 1; r < data.length; r++) {
    const slug   = (data[r][cSlug] || '').toString().trim();
    const feed   = (data[r][cFeed] || '').toString().trim();
    const defCat = cCat >= 0 ? (data[r][cCat] || '').toString().trim() : '';
    if (!slug || !feed) continue;

    try {
      Logger.log(Letöltés: ${slug} → ${feed});
      const xml   = fetchText(feed);
      let items   = parseDealsFromXml(xml, slug, defCat);

      items.sort((a,b) => b.discount - a.discount);
      if (items.length > TOP_N_PER_SHOP) items = items.slice(0, TOP_N_PER_SHOP);
      if (!items.length) items = parseDealsFromXml(xml, slug, defCat).slice(0, TOP_N_PER_SHOP);

      const values = items.map(x => [
        x.img,
        x.href,
        x.label,
        x.category || defCat || 'Akciók'
      ]);

      if (values.length) {
        shBann.getRange(writeRow, 1, values.length, 4).setValues(values);
        SpreadsheetApp.flush(); // <-- azonnali írás
        writeRow += values.length;
      }
      Logger.log(Kész: ${slug} (felvett: ${values.length}));
    } catch (e) {
      Logger.log(Feed hiba: ${slug} → ${e.message});
    }
  }

  Logger.log(Futás vége. Banners sorok: ${writeRow - 2});
}
A ChatGPT ezt mondta:

Gondolkozás
értem: a Banners sheet nem telik meg → ezért a WordPress oldalon sincs mit kirakni a bannerekből. A te Apps Scripted alapvetően jó, de két tipikus falba ütközhet:
XML namespace: nagyon sok feed g:image_link, g:price stb. névterezett elemeket használ. A mostani getChild('image_link') ezt nem találja meg, ezért kimarad a cím/kép/link → nincs mit írni a Banners-be.
GET + Content-Type: néhány szerver fura, ha GET kéréshez Content-Type: application/xml fejlécet küldünk. Elhagyjuk és csak Accept-et adunk.
Lent adok egy EGYBEN cseredarabot (Apps Script), ami:
névtereket figyelmen kívül hagyva keres (localName alapján, kis/nagybetűtől függetlenül),
minden leszármazott között keres (nem csak közvetlen gyerek),
stabilabb URL-letöltőt használ,
marad a „TOP 3 akció/shop” logika, és ugyanúgy tölti a Banners!A:D-t,
rögtön flush-öl, hogy a publisholt CSV is frissüljön.
Tedd be a Script Editorba (Apps Script), felülírva a mostanit. Utána futtasd az impactshop_dealsToBanners() függvényt (és engedélyezd a jogosultságokat).
/**
 * ImpactShop – Deals → Banners (TOP3 akció / shop) – namespace-proof verzió
 * Forrás:  Shops!A:Z  (kötelező: shop_slug, deals_feed; ajánlott: category)
 * Cél:     Banners!A:D (img, href, label, category)
 * Kattintás: Fillout link (shop + base64(u)) → /go-deal a site-on
 */

const FILLOUT_BASE      = 'https://form.fillout.com/t/eM61RLkz6jus';
const SHEET_SHOPS       = 'Shops';
const SHEET_BANNERS     = 'Banners';
const TOP_N_PER_SHOP    = 3;
const CONNECT_TIMEOUT_S = 30;   // (UrlFetchApp nem támogat explicit timeoutot, itt csak dokumentáció)
const READ_TIMEOUT_S    = 120;  //  - az alábbi opciók ettől függetlenül működnek

/* -------- tipikus mezőnevek -------- */
const TITLE_TAGS = ['title','name','productname','product','item_name'];
const URL_TAGS   = ['url','link','item_url'];
const IMG_TAGS   = ['image','image_url','imgurl','image_link','imgurl_alternative'];
const CAT_TAGS   = ['category','categorytext','department','google_product_category'];
const PRICE_TAGS     = ['price','price_vat','our_price','sale_price','final_price'];
const OLDPRICE_TAGS  = ['old_price','price_before','base_price','original_price','price_before'];
const SALEPRICE_TAGS = ['sale_price'];

/* -------- segédek -------- */
function impactshop_buildFilloutHref(shopSlug, productUrl) {
  const u = Utilities.base64Encode(productUrl);
  const params = `shop=${encodeURIComponent(shopSlug)}&u=${encodeURIComponent(u)}`;
  return `${FILLOUT_BASE}?${params}`;
}

function sanitizeXml(xml) {
  xml = xml.replace(/<!DOCTYPE[\s\S]*?\[[\s\S]*?\]>/gi, '');
  xml = xml.replace(/<!DOCTYPE[^>]*>/gi, '');
  xml = xml.replace(/<!ENTITY[\s\S]*?>/gi, '');
  xml = xml.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g, '');
  xml = xml.replace(/&(?!amp;|lt;|gt;quot;|apos;)[a-zA-Z][\w-]*;/g, '');
  return xml;
}

function fetchText(url) {
  const needsEncoding = /[^\x20-\x7E]/.test(url) && !/%[0-9A-Fa-f]{2}/.test(url);
  const safeUrl = needsEncoding ? encodeURI(url) : url;
  const resp = UrlFetchApp.fetch(safeUrl, {
    muteHttpExceptions: true,
    followRedirects: true,
    validateHttpsCertificates: true,
    headers: { 'Accept': 'application/xml, text/xml, */*' },
    // NINCS Content-Type GET-hez
  });
  const code = resp.getResponseCode();
  if (code < 200 || code >= 300) throw new Error('HTTP ' + code + ' for ' + safeUrl);
  return resp.getContentText();
}

/* --- szám parse --- */
function num(x) {
  if (x == null) return NaN;
  let s = String(x).replace(/\s+/g, '').replace(/[^\d.,-]/g, '');
  const lastComma = s.lastIndexOf(','), lastDot = s.lastIndexOf('.');
  if (lastComma > -1 && lastDot > -1) {
    s = (lastComma > lastDot) ? s.replace(/\./g,'').replace(',', '.') : s.replace(/,/g,'');
  } else {
    s = s.replace(',', '.');
  }
  const n = parseFloat(s);
  return isFinite(n) ? n : NaN;
}

/* --- namespace-agnosztikus elemlista --- */
function childrenAll(el) {
  // összes közvetlen gyerek
  return el.getChildren();
}
function descendants(el, bucket) {
  bucket = bucket || [];
  const kids = el.getChildren();
  for (const k of kids) {
    bucket.push(k);
    descendants(k, bucket);
  }
  return bucket;
}

/* --- név független szövegkereső: localName match, case-insensitive, bárhol a leszármazottak között --- */
function pickTextNS(el, names) {
  const namesLC = names.map(n => String(n).toLowerCase());
  const all = descendants(el, []);
  for (const node of all) {
    const local = String(node.getName() || '').toLowerCase(); // namespace rész nélkül adja vissza a nevet
    if (namesLC.indexOf(local) !== -1) {
      const t = (node.getText() || '').trim();
      if (t) return t;
    }
  }
  // 2. esély: közvetlen gyerekek közt is megnézzük (ha a feed lapos)
  for (const name of namesLC) {
    const direct = el.getChild(name); // névteret nem adunk – néha így is működik
    if (direct) {
      const t = (direct.getText() || '').trim();
      if (t) return t;
    }
  }
  return '';
}
function pickNumNS(el, names) {
  const t = pickTextNS(el, names);
  const n = num(t);
  return isFinite(n) ? n : NaN;
}

/* -------- XML → tárgyiasított tételek -------- */
function parseDealsFromXml(xmlText, shopSlug, fallbackCategory) {
  xmlText = sanitizeXml(xmlText);
  const doc  = XmlService.parse(xmlText);
  const root = doc.getRootElement();

  // A legtöbb feed: SHOPITEM / item / offer / product / entry
  const ITEM_NAMES = ['product','item','offer','entry','shopitem'];
  const items = [];
  (function walk(el) {
    const nameLC = String(el.getName() || '').toLowerCase();
    if (ITEM_NAMES.indexOf(nameLC) !== -1) items.push(el);
    el.getChildren().forEach(walk);
  })(root);

  const out = [];
  for (const it of items) {
    const title = pickTextNS(it, TITLE_TAGS);
    const url   = pickTextNS(it, URL_TAGS);
    const img   = pickTextNS(it, IMG_TAGS);
    const cat   = pickTextNS(it, CAT_TAGS) || (fallbackCategory || '');
    if (!title || !url || !img) continue;

    const p  = pickNumNS(it, PRICE_TAGS);
    let   op = pickNumNS(it, OLDPRICE_TAGS);
    const sp = pickNumNS(it, SALEPRICE_TAGS);

    if (!isFinite(op) && isFinite(sp) && isFinite(p) && sp < p) op = p;
    let price = p;
    if (!isFinite(price) && isFinite(sp)) price = sp;
    if (!isFinite(price) && isFinite(op)) { price = op; op = NaN; }

    let discount = 0;
    if (isFinite(op) && isFinite(price) && op > 0 && price < op) {
      discount = (op - price) / op;
    } else if (isFinite(sp) && isFinite(p) && p > 0 && sp < p) {
      discount = (p - sp) / p;
    }

    out.push({
      img,
      href: impactshop_buildFilloutHref(shopSlug, url),
      label: title.length > 90 ? (title.substring(0, 87) + '…') : title,
      category: cat,
      discount
    });
  }
  return out;
}

/* -------- főfüggvény – flush-sel -------- */
function impactshop_dealsToBanners() {
  const ss      = SpreadsheetApp.getActive();
  const shShops = ss.getSheetByName(SHEET_SHOPS);
  const shBann  = ss.getSheetByName(SHEET_BANNERS);
  if (!shShops || !shBann) throw new Error('Hiányzik a Shops vagy Banners sheet.');

  // fejléc
  shBann.clearContents();
  shBann.getRange(1,1,1,4).setValues([['img','href','label','category']]);
  SpreadsheetApp.flush();

  const data = shShops.getDataRange().getValues();
  const head = data[0].map(h => (''+h).trim().toLowerCase().replace(/\s+/g,'_'));
  const col  = (name) => head.indexOf(name);

  const cSlug = col('shop_slug');
  const cFeed = col('deals_feed');
  const cCat  = col('category');
  if (cSlug < 0 || cFeed < 0) throw new Error('A Shops lapon kell shop_slug és deals_feed oszlop.');

  let writeRow = 2;

  for (let r = 1; r < data.length; r++) {
    const slug   = (data[r][cSlug] || '').toString().trim();
    const feed   = (data[r][cFeed] || '').toString().trim();
    const defCat = cCat >= 0 ? (data[r][cCat] || '').toString().trim() : '';
    if (!slug || !feed) continue;

    try {
      const xml   = fetchText(feed);
      let items   = parseDealsFromXml(xml, slug, defCat);

      // TOP N akció
      items.sort((a,b) => b.discount - a.discount);
      if (items.length > TOP_N_PER_SHOP) items = items.slice(0, TOP_N_PER_SHOP);
      if (!items.length) items = parseDealsFromXml(xml, slug, defCat).slice(0, TOP_N_PER_SHOP);

      const values = items.map(x => [
        x.img,
        x.href,
        x.label,
        x.category || defCat || 'Akciók'
      ]);

      if (values.length) {
        shBann.getRange(writeRow, 1, values.length, 4).setValues(values);
        SpreadsheetApp.flush();
        writeRow += values.length;
      }
    } catch (e) {
      // Írjunk hiba-”dummy”-t, hogy lássuk melyik shop bukott
      shBann.getRange(writeRow, 1, 1, 4).setValues([[
        '',
        impactshop_buildFilloutHref(slug, feed),
        `Hiba a feednél: ${slug} – ${String(e.message).slice(0,120)}`,
        defCat || ''
      ]]);
      SpreadsheetApp.flush();
      writeRow += 1;
    }
  }
}
Gyors ellenőrzőlista
Shops lapon legyen shop_slug, deals_feed (és jó, ha category is).
A feedek valós XML-t adjanak vissza (ha JSON vagy HTML, akkor értelemszerűen nem fog menni).
A Banners lap legyen publish-olva CSV-ként (File → Publish to the web), és a WordPress snippetben a banners_csv_url gid-je egyezzen a Banners sheet gid-jével.
A WP-ben a cache 15 perc; első teszt után várj egy kicsit vagy ürítsd a transienst (vagy hívd meg: ?impactshop_refresh=1 a rewrite miatt — a CSV cache külön 15 perc).
Ha így sem jön adat egy-egy feedből, bedobhatjuk ide a feed URL-t, és belenézek, melyik elemeket használja (névterek, mezőnevek), és kiterjesztem a keresési listát.
Ezt mondtad:
`banners_csv_url` gid-je egyezzen a Banners sheet gid-jével.
megfelel?: https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv
A ChatGPT ezt mondta:

Gondolkozás
Igen, ez a link jó lesz banners_csv_url-nak — feltéve, hogy a gid=328401803 tényleg a Banners sheetre mutat, és a CSV első sora ez a 4 fejléc:
img,href,label,category
Gyors ellenőrzés
Nyisd meg a linket böngészőben. Ha az első sorban a fenti fejlécek vannak, oké.
A WordPress snippetben legyen pontosan ez az URL az impactshop_settings()['banners_csv_url'] értéke.
Cache: nálunk 15 perc a CSV cache. Azonnali frissítéshez:
ideiglenesen tedd a végére: &v=1 (cache-buster), vagy
várj 15 percet.
Gyors funkcionális próba
[impactshop_scroller inject_every="1"] esetén a logók közé be kell csúszniuk a Banners képeknek.
Ha nem látszik, akkor a CSV első sora/mezőnevek valószínűleg nem stimmelnek, vagy a publikálás nincs bekapcsolva (File → Publish to the web → CSV).
Ezt mondtad:
`gid=328401803`
ezt honnan tudom?
A ChatGPT ezt mondta:
Arra vagy kíváncsi, hogy a linkben lévő gid=328401803 tényleg a Banners lapra mutat-e, és hogy jók-e a fejlécmezők. Így tudod biztosra venni:
1) A gid ellenőrzése a Google Sheets-ben
Nyisd meg a táblát böngészőben.
Kattints a Banners munkalap fülére.
Nézd meg a böngésző címsorát: a végén lesz valami ilyesmi:
.../edit#gid=328401803
Ha itt ugyanaz a szám, mint a publikált CSV linkben (gid=328401803), akkor a link a Banners lapot publikálja.
2) A publikált CSV tartalmának ellenőrzése
Nyisd meg az általad küldött CSV linket egy új fülön.
Az első sorban ennek kell állnia, ebben a sorrendben:
img,href,label,category
Ha ez látszik, a WordPress snippet fel fogja tudni dolgozni.
3) Ha nem stimmel a gid
A „Publish to the web / Közzététel a weben” ablakban válaszd a Banners sheetet, formátum: CSV.
A „Közzététel” után kapott linkben cseréld be a snippetbe az új URL-t (figyeld, hogy a gid megegyezzen a Banners fül gid-jével).
4) Programozott gyorsellenőrzés (ha Apps Script is fut nálad)
Tedd a Script Editorba és futtasd – kiírja minden lap nevét és gid-jét:
function listSheetGids() {
  const ss = SpreadsheetApp.getActive();
  ss.getSheets().forEach(sh => Logger.log(sh.getName() + ' -> gid: ' + sh.getSheetId()));
}
A naplóban (View → Logs) keresd a Banners -> gid: ... sort; ennek a számnak kell a linkben szerepelnie.
5) Cache tipp
A WP-ben a CSV-t 15 percig cache-eljük. Azonnali frissítéshez add a link végéhez egy „zajos” paramétert (pl. &v=2) vagy várj 10–15 percet.
Ha szeretnéd, átdobhatod a táblád megnyitási linkjét (nem kell szerkesztési jog), és megmondom, hogy a mostani gid pontosan melyik laphoz tartozik.

Ezt mondtad:
A WP-ben a CSV-t 15 percig cache-eljük. Azonnali frissítéshez add a link végéhez egy „zajos” paramétert (pl. `&v=2`) vagy várj 10–15 percet.
hogyan mihez?
A ChatGPT ezt mondta:

Gondolkozás
Jogos a “hogyan mihez?” — a „zajos paraméter” (cache-buster) a Google Sheets CSV link végére kerül, hogy a WP-ben tárolt 15 perces cache újra letöltse a fájlt.
1) Hová tedd a zajos paramétert?
A snippetben, itt (vagy ahol nálad van):
'banners_csv_url' => 'https://docs.google.com/.../pub?gid=328401803&single=true&output=csv',
Ebből csinálsz ilyet:
'banners_csv_url' => 'https://docs.google.com/.../pub?gid=328401803&single=true&output=csv&v=2',
Ugyanez mehet a shops_csv_url-ra is, ha azt is frissen akarod:
'shops_csv_url' => '...output=csv&v=2',
Tipp: ha legközelebb újra frissítenél, elég a v=3, v=4 stb. (bármilyen érték megfelel, a lényeg, hogy változzon).
2) Mikor használj ? és mikor &?
Ha az URL-ben már van ? és több paraméter (mint a Google CSV-nél), &v=2 kell a végére.
Ha nincs kérdőjel a linkben, ?v=2 kell.
3) Gyors „cache-ürítés” kód nélkül
A zajos paraméter pont ezt teszi: megkerüli a 15 perces WP-transienst, és azonnal újra letölteti a CSV-t.
4) (Opcionális) Egyklikkes flush kapcsoló
Ha inkább gombnyomásra törölnéd a WP-cache-t, tegyél be átmenetileg ezt a kis segédet a snippet aljára, majd hívd meg az URL-t:
add_action('init', function(){
  if (isset($_GET['impactshop_flush'])) {
    delete_transient('impactshop_csv_banners');
    delete_transient('impactshop_csv_shops');
    wp_die('<div style="padding:20px;font:14px system-ui">ImpactShop CSV cache törölve.</div>');
  }
});
Használat: nyisd meg
https://app.sharity.hu/?impactshop_flush=1
(Ezután törölheted ezt a segédet, mert csak egyszeri admin-eszköz.)
Röviden: a CSV link végére tedd a &v=2-t (vagy nagyobb számot), mentsd a snippetet, és azonnal látni fogod az új Banners tartalmat a scrollerben/katalógusban.
Ezt mondtad:
A WordPress snippetben legyen pontosan ez az URL az `impactshop_settings()['banners_csv_url']` értéke.
nem találok a snippetben impactshop_settings -t
A ChatGPT ezt mondta:

Gondolkozás
Valószínűleg nem az “ÖSSZEVONT” snippet fut nálad, hanem csak a redirectes (API-s) verzió — abban tényleg nincs impactshop_settings(). Két gyors megoldás közül bármelyik jó:
A) Az „ÖSSZEVONT” snippetet aktiváld
Ebben benne van a impactshop_settings() és a UI (scroller/katalógus) is. Akkor a banners_csv_url ott módosítható.
B) Ha marad a mostani snippet, tedd be ezt a rövid blokkot (EGYBEN bemásolható)
Tedd a snippet elejére (vagy bárhova, csak előbb fusson, mint ahol a bannereket hívod). Ütközést nem okoz, mert function_exists guard van.
/* ===== BEÁLLÍTÁS BLOKK (ha nincs impactshop_settings) ===== */
if (!function_exists('impactshop_settings')) {
  function impactshop_settings() {
    return [
      'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
      'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=2', // ← itt a Banners CSV (cache-busterrel)
      'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
      'cache_ttl'       => 15 * MINUTE_IN_SECONDS,
    ];
  }
}
/* =============================================== */
Hol fogja ezt használni?
A bannereket betöltő függvény(ek) valami ilyesmit hívnak:
impactshop_fetch_csv_assoc(impactshop_settings()['banners_csv_url'], 'impactshop_csv_banners', impactshop_settings()['cache_ttl']);
Ha nálad nincs meg ez a hívás, akkor a bannerek nem kerülnek sehonnan betöltésre. Ebben az esetben tedd be (vagy egészítsd ki) ezt a kettőt:
if (!function_exists('impactshop_get_banners_raw')) {
  function impactshop_get_banners_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  }
}
if (!function_exists('impactshop_get_banners')) {
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
}
Gyors tippek
Cache-buster: nyugodtan növeld a végén a &v=2 számot (&v=3, &v=4…), ha azonnal akarod újratöltetni a CSV-t.
Cache törlés (opció): ideiglenesen használhatod ezt is, majd nyisd meg /?impactshop_flush=1, aztán töröld a segédet:
add_action('init', function(){
  if (isset($_GET['impactshop_flush'])) {
    delete_transient('impactshop_csv_banners');
    delete_transient('impactshop_csv_shops');
    wp_die('<div style="padding:20px;font:14px system-ui">ImpactShop CSV cache törölve.</div>');
  }
});
GID ellenőrzés: a Banners fülön az URL végén ...#gid=328401803 legyen; ez egyezzen a publikált CSV link gid paraméterével.
Ha szeretnéd, ránézek a most aktív snippet teljes szövegére (ide másolhatod), és beillesztem a fenti blokkokat pontos helyre, hogy biztosan működjön a Banners betöltés.
Ezt mondtad:
ez a beállított nippet: /**
 * Impact Shop – ÖSSZEVONT SNIPPET (AUTO DOGNET TOKEN + UI + Redirect + Fallback)
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag]
 * Endpontok:    /go  és  /go-deal
 * Link:         Dognet API (auto-login, auto-refresh) → fallback: go.dognet.com
 *
 * Lépések:
 *  1) Hagyd benne a lenti DOGNET belépőket (vagy tedd wp-config-ba konstansként).
 *  2) Aktiválás után egyszer: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

/** DOGNET belépők – kérésre kitöltve */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');

/** (Opcionális) Fix token – üresen hagyva auto-login működik */
if (!defined('DOGNET_API_TOKEN')) define('DOGNET_API_TOKEN', ''); // ha üres → auto-login

/** Ha 0: automatikus kiválasztás az első "Web Site" jellegű ad channel */
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 0);

/** Cache idők */
if (!defined('IMPACTSHOP_CACHE_TTL')) define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))     define('DOGNET_TOKEN_TTL',     20 * HOUR_IN_SECONDS); // 24h helyett ráhagyás

/* ============================== BEÁLLÍTÁS ============================== */

if (!function_exists('impactshop_settings')) {
  function impactshop_settings() {
    return [
      'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
      'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=328401803&single=true&output=csv',
      'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
      'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
    ];
  }
}

/* ============================ CSV SEGÉDEK ============================ */

if (!function_exists('impactshop_slugify_header')) {
  function impactshop_slugify_header($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
    $s = strtr($s, $map);
    $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
    return trim($s, '_');
  }
}

if (!function_exists('impactshop_fetch_csv_assoc')) {
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
}

/* ======================= SHOPS & BANNERS MODELL ====================== */

if (!function_exists('impactshop_get_shops_raw')) {
  function impactshop_get_shops_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
  }
}
if (!function_exists('impactshop_get_banners_raw')) {
  function impactshop_get_banners_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  }
}

if (!function_exists('impactshop_get_shops')) {
  function impactshop_get_shops() {
    $rows = impactshop_get_shops_raw();
    $out = [];
    foreach ($rows as $r) {
      $name   = $r['name']       ?? ($r['nev'] ?? '');
      $slug   = $r['shop_slug']  ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
      $cat    = $r['category']   ?? ($r['kategoria'] ?? 'Egyéb');
      $logo   = $r['logo_url']   ?? ($r['logo'] ?? ($r['image'] ?? ''));
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
}

if (!function_exists('impactshop_get_banners')) {
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
}

/* ========================= KÖZÖS SEGÉD ========================= */

if (!function_exists('impactshop_q')) {
  function impactshop_q($key, $def='') {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
  }
}
if (!function_exists('impactshop_find_shop')) {
  function impactshop_find_shop($slug) {
    $slug = trim(strtolower($slug));
    foreach (impactshop_get_shops() as $s) {
      if (strtolower($s['shop_slug']) === $slug) return $s;
    }
    return null;
  }
}
if (!function_exists('impactshop_error')) {
  function impactshop_error($msg) {
    status_header(400);
    wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'WordPress › hiba');
  }
}

/* ====================== DOGNET PUBLISHER API ====================== */

if (!function_exists('dognet_token_key')) {
  function dognet_token_key() { return 'dognet_api_token_cache_v1'; }
}

if (!function_exists('dognet_get_token')) {
  /**
   * Token visszaadása:
   *  - ha DOGNET_API_TOKEN konstans adott → azt használja (felülír mindent)
   *  - különben transientből olvas; ha nincs/lejárt → login és mentés
   */
  function dognet_get_token($force_refresh = false) {
    if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;

    $key = dognet_token_key();
    if (!$force_refresh) {
      $tok = get_transient($key);
      if ($tok) return $tok;
    }
    // Login
    if (!DOGNET_LOGIN_EMAIL || !DOGNET_LOGIN_PASSWORD) return '';
    $url = 'https://api.app.dognet.com/api/v1/auth/login';
    $resp = wp_remote_post($url, [
      'timeout' => 20,
      'headers' => ['Content-Type' => 'application/json'],
      'body'    => wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]),
    ]);
    if (is_wp_error($resp)) return '';
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300 || !is_array($json)) return '';
    $token = $json['token'] ?? ($json['data']['token'] ?? '');
    if ($token) {
      set_transient($key, $token, DOGNET_TOKEN_TTL);
      return $token;
    }
    return '';
  }
}

if (!function_exists('dognet_api_request')) {
  /**
   * Általános Dognet API hívó – 401 esetén token frissítés és egyszeri retry.
   */
  function dognet_api_request($method, $path, $body = null) {
    $token = dognet_get_token(false);
    if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

    $url   = 'https://api.app.dognet.com/api/v1' . $path;

    $do_request = function($bearer) use ($method, $url, $body) {
      $args = [
        'timeout' => 20,
        'headers' => [
          'Authorization' => 'Bearer ' . $bearer,
          'Content-Type'  => 'application/json',
        ],
        'method'  => $method,
      ];
      if (!is_null($body)) $args['body'] = wp_json_encode($body);
      return wp_remote_request($url, $args);
    };

    $resp = $do_request($token);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);

    // 401 → token refresh + retry
    if ($code === 401) {
      $token = dognet_get_token(true);
      if (!$token) return new WP_Error('auth_failed','Dognet API auth sikertelen (401).');
      $resp = $do_request($token);
      if (is_wp_error($resp)) return $resp;
      $code = wp_remote_retrieve_response_code($resp);
    }

    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300) {
      return new WP_Error('api_error', 'Dognet API hiba: '.$code, ['resp'=>$json]);
    }
    return $json;
  }
}

if (!function_exists('dognet_api_pick_ad_channel_id')) {
  function dognet_api_pick_ad_channel_id() {
    if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
    $list = dognet_api_request('GET','/ad-channels');
    if (is_wp_error($list) || empty($list['data'])) return 0;
    foreach ($list['data'] as $c) {
      $type = isset($c['type']) ? strtolower((string)$c['type']) : '';
      if (strpos($type,'web') !== false) return intval($c['id']); // első Web Site
    }
    return intval($list['data'][0]['id']);
  }
}

if (!function_exists('dognet_extract_campaign_id_from_base')) {
  function dognet_extract_campaign_id_from_base($dognet_base) {
    if (!$dognet_base) return 0;
    $qs = parse_url($dognet_base, PHP_URL_QUERY);
    parse_str($qs, $parts);
    return isset($parts['cid']) ? intval($parts['cid']) : 0;
  }
}

if (!function_exists('dognet_api_generate_link')) {
  /**
   * Linkgenerálás:
   * POST /campaigns/links/generate
   * Body: ad_channel_id, campaign_id, url (opcionális), data1, data2, url_type (1=full,2=standard,3=simple)
   */
  function dognet_api_generate_link($campaign_id, $deeplink = '', $d1 = '', $d2 = '') {
    $ad_channel_id = dognet_api_pick_ad_channel_id();
    if (!$ad_channel_id) return new WP_Error('no_channel','Nincs elérhető ad_channel');

    $body = [
      'ad_channel_id' => intval($ad_channel_id),
      'campaign_id'   => intval($campaign_id),
      'url_type'      => 3, // simple
    ];
    if ($deeplink) $body['url']   = $deeplink;
    if ($d1)       $body['data1'] = $d1;
    if ($d2)       $body['data2'] = $d2;

    $json = dognet_api_request('POST','/campaigns/links/generate', $body);
    if (is_wp_error($json)) return $json;

    foreach (['url','short_url','full_url'] as $k) {
      if (!empty($json[$k])) return $json[$k];
      if (!empty($json['data'][$k])) return $json['data'][$k];
    }
    return new WP_Error('bad_api','Ismeretlen API válasz', $json);
  }
}

/* ==================== LEGACY (go.dognet.com) BUILD =================== */

if (!function_exists('impactshop_build_legacy_go_url')) {
  function impactshop_build_legacy_go_url($shopRow, $ngo_code, $maybeUrl) {
    $base = trim($shopRow['dognet_base'] ?? '');
    if (!$base) return [null, 'Hiányzik a dognet_base a Shops CSV-ben.'];

    $params = [];
    if ($ngo_code) $params['d1'] = $ngo_code;

    $deeplinkParam = $shopRow['deeplink_param'] ?: 'url';
    if (!empty($maybeUrl)) {
      $decoded = $maybeUrl;
      // base64 elfogadása
      if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $maybeUrl)) {
        $tmp = base64_decode($maybeUrl, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $decoded = $tmp;
      }
      $params[$deeplinkParam] = $decoded;
    }

    $glue = (strpos($base, '?') === false) ? '?' : '&';
    $url = $base . $glue . http_build_query($params);
    return [$url, null];
  }
}

/* ============================ REDIRECTEK ============================ */

if (!function_exists('impactshop_add_rewrites')) {
  function impactshop_add_rewrites() {
    add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
    add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
  }
  add_action('init','impactshop_add_rewrites');
}

if (!function_exists('impactshop_add_query_vars')) {
  function impactshop_add_query_vars($vars) {
    $vars[] = 'impactshop_go';
    $vars[] = 'impactshop_deal';
    return $vars;
  }
  add_filter('query_vars','impactshop_add_query_vars');
}

add_action('template_redirect', function(){
  if (get_query_var('impactshop_go'))   { impactshop_handle_go(false); exit; }
  if (get_query_var('impactshop_deal')) { impactshop_handle_go(true);  exit; }
});

if (!function_exists('impactshop_redirect_with_propagation')) {
  function impactshop_redirect_with_propagation($url, $amb, $src) {
    $add = [];
    if ($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
    if ($src && strpos($url,'src=')===false) $add['src']=$src;
    if (strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
    if (strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
    if ($add) $url .= (strpos($url,'?')===false ? '?' : '&') . http_build_query($add);
    wp_redirect($url, 307);
    exit;
  }
}

if (!function_exists('impactshop_handle_go')) {
  /**
   * /go és /go-deal közös kezelő
   * - shop + d1 kötelező
   * - ha van campaign_id a CSV base-ben → Dognet API
   * - különben legacy go.dognet.com build
   * - /go-deal: ha nincs 'u', próbáljuk a CSV 'product_url'-t; ha az sincs, kampánylinkre esünk vissza
   */
  function impactshop_handle_go($is_deal) {
    $shop = impactshop_q('shop');
    $ngo  = impactshop_q('d1');
    $u    = impactshop_q('u');
    $amb  = impactshop_q('amb');
    $src  = impactshop_q('src') ?: 'impactshop';

    if (!$shop || !$ngo) impactshop_error('Hiányzó paraméter (shop/d1).');

    $row = impactshop_find_shop($shop);
    if (!$row) impactshop_error('Ismeretlen shop: '.esc_html($shop));

    // cél deeplink
    $targetUrl = '';
    if ($is_deal) {
      if ($u) {
        $targetUrl = $u;
      } else {
        $fallback = trim($row['product_url'] ?? '');
        if ($fallback) $targetUrl = $fallback;
      }
    }

    // API first (ha a dognet_base-ből kinyerhető a cid)
    $final = null;
    $cid   = dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
    if ($cid) {
      $deeplink = $targetUrl;
      if ($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
        $tmp = base64_decode($deeplink, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
      }
      $api = dognet_api_generate_link($cid, $deeplink, $ngo, '');
      if (!is_wp_error($api) && $api) $final = $api;
    }

    // Fallback: legacy build
    if (!$final) {
      list($legacy,) = impactshop_build_legacy_go_url($row, $ngo, $targetUrl);
      if ($legacy) $final = $legacy;
    }
    if (!$final) impactshop_error('Nem sikerült a partner linket előállítani.');

    impactshop_redirect_with_propagation($final, $amb, $src);
  }
}

/* ============================ SHORTCODE-OK ============================ */
/** Scroller */
if (!shortcode_exists('impactshop_scroller')) {
  function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts([
      'category'     => '',
      'inject_every' => 5,
      'speed'        => 30,
    ], $atts);

    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners();
    if (!empty($a['category'])) {
      $catWanted = $a['category'];
      $shops = array_values(array_filter($shops, function($s) use ($catWanted){ return strcasecmp($s['category'],$catWanted)===0; }));
      $banners = array_values(array_filter($banners, function($b) use ($catWanted){ return empty($b['category']) || strcasecmp($b['category'],$catWanted)===0; }));
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
    $stream = array_merge($mixed, $mixed); // végtelenített

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
      .impactshop-item{display:inline-block;margin-right:20px}
    </style>
    <div class="impactshop-scroller">
      <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
        <?php foreach ($stream as $it): ?>
          <?php if ($it['type']==='shop'): $shop=$it['data'];
            if ($d1) {
              $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
            } else {
              $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
            } ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
              </a>
            </div>
          <?php else: $b=$it['data']; ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
                <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
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
}

/** Katalógus */
if (!shortcode_exists('impactshop_catalog')) {
  function impactshop_shortcode_catalog($atts) {
    $a = shortcode_atts([
      'show_tabs' => '1',
      'search'    => '1',
      'per_page'  => '200',
    ], $atts);

    $shops = impactshop_get_shops();
    if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

    usort($shops, function($x,$y){
      $c = strcasecmp($x['category'],$y['category']);
      return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
    });

    $cats = [];
    foreach ($shops as $s) { $cats[$s['category']] = true; }
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
          if ($d1) {
            $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
          } else {
            $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
          } ?>
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
}

/** Diagnosztika */
if (!shortcode_exists('impactshop_diag')) {
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
}

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});
A ChatGPT ezt mondta:

Gondolkozás
Megvan, miért „csak a shortcode látszik”. A most beállított snippetben nincs regisztrálva az [impactshop_scroller] shortcode – a kommentben szerepel ugyan, de a tényleges add_shortcode('impactshop_scroller', ...) blokk hiányzik. Emiatt a WordPress nem tudja, mit kell futtatni, és simán kiírja a szöveget.
Két gyors megoldás:

1) Használd a működő shortocode-okat
A snippetben most csak ezek vannak regisztrálva:
[impactshop_catalog]
[impactshop_diag]
Ha ezeket teszteled, rendesen meg kell jelenniük.
2) Tedd vissza a scroller shortcode-ot (egyben bemásolható)
Illeszd be ezt a blokkot a snippet végére, még a „REWRITE FLUSH” rész elé (vagy bárhová a helpers után). Nem ütközik semmivel – shortcode_exists guard van benne.
/** Scroller – PÓTLÁS */
if (!shortcode_exists('impactshop_scroller')) {
  function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts([
      'category'     => '',
      'inject_every' => 5,
      'speed'        => 30,
    ], $atts);

    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners();

    if (!empty($a['category'])) {
      $catWanted = $a['category'];
      $shops = array_values(array_filter($shops, function($s) use ($catWanted){ return strcasecmp($s['category'],$catWanted)===0; }));
      $banners = array_values(array_filter($banners, function($b) use ($catWanted){ return empty($b['category']) || strcasecmp($b['category'],$catWanted)===0; }));
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
    $stream = array_merge($mixed, $mixed); // végtelenített

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
      .impactshop-item{display:inline-block;margin-right:20px}
    </style>
    <div class="impactshop-scroller">
      <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
        <?php foreach ($stream as $it): ?>
          <?php if ($it['type']==='shop'): $shop=$it['data'];
            if ($d1) {
              $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
            } else {
              $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
            } ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
              </a>
            </div>
          <?php else: $b=$it['data']; ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
                <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
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
}
Még két apróság, ha a bannerek nem jönnek:
A snippetben a banners_csv_url most erre mutat:
https://docs.google.com/.../pub?gid=328401803&single=true&output=csv
Ez akkor jó, ha a Banners fül gid-je tényleg 328401803, és az első sor:
img,href,label,category
Cache-buster: ha azonnali frissítést akarsz, a fenti URL végére tegyél &v=2-t (később &v=3, stb.).
Ha beillesztetted a scroller-blokkot, az [impactshop_scroller] már rendesen renderelni fog. A következő lépésben szívesen rápingelek a Banners CSV-re is, ha még mindig nem jönnek képek.
Ezt mondtad:
hogy kell berakni a weboldalra az akciókat? shortcode-al?
A ChatGPT ezt mondta:

Gondolkozás
Igen — shortcode-dal. Így rakod ki az akciókat a weboldalra:
1) Hova írd be?
WordPress szerkesztőben:
Gutenberg: “Shortcode” blokk → írd be a kódot.
Classic editor: simán a tartalomba.
2) Melyik shortcode mit csinál?
Katalógus (rács) – partnerek listája, kártyákkal:
[impactshop_catalog]
Opciók:
show_tabs="1" – kategóriafülek (0/1)
search="1" – keresőmező (0/1)
per_page="200" – (most még mindent mutat)
category="Divat" – csak egy kategória (ha szeretnéd szűkíteni)
Scroller (futószalag) + akció bannerek – logók, közé beszúrva a Banners CSV elemei:
[impactshop_scroller inject_every="3" speed="25"]
Mit jelent:
inject_every="3" → minden 3. shop után jön 1 banner (akció).
speed="25" → az animáció teljes köre ~25 mp.
category="Divat" → csak adott kategória (a shopokra és a bannerekre is érvényes).
Diagnosztika – gyors ellenőrző nézet:
[impactshop_diag]
3) Hogyan lesznek valódi affiliate linkek?
A linkgenerálás a stránka URL-paramétereiből dolgozik:
Add hozzá az oldalad URL-jéhez az NGO kódot: ?d1=SAJATKOD
Opcionális: &amb=... (kampány/ambassador), &src=... (forrás)
Példa menülink:
/tamogasd/?d1=ZOLDISKOLA&amb=holloko&src=impactshop
Shop-kattintás: /go/{shop_slug} → Dognet API link (fallback a régi go.dognet.com).
Banner-kattintás: a Banners CSV-ben lévő Fillout linkre megy (benne a shop és a termék u base64), onnan /go-deal irányba fut tovább.
4) Ha nem látszik semmi / csak a [impactshop_scroller] szöveg:
Ez akkor van, ha a scroller shortcode nincs regisztrálva. A mostani snippetben benne kell lennie; ha mégsem, tedd be újra a scroller-blokkot (amit korábban küldtem). Addig is használd a [impactshop_catalog]-ot.
5) Ha nincsenek akció-bannerek a scrollerben:
Ellenőrizd, hogy a snippetben a banners_csv_url a Banners sheet CSV-jére mutat (és a fejléc az első sorban: img,href,label,category).
A WP 15 percig cache-el. Azonnali frissítéshez tegyél a CSV link végére egy változó paramétert: &v=2 (később &v=3, stb.).
Gyors start:
Hozz létre egy “Akciók és partnerek” oldalt.
Tedd bele:
[impactshop_scroller inject_every="2" speed="20"]
[impactshop_catalog show_tabs="1" search="1"]
A menüben erre az oldalra mutató linket állítsd így:
/akciok/?d1=AZ_NGO_KODOD
Következő lépésként szívesen adok egy mini „Akciók” landing-sablont is, ha külön oldalon szeretnéd a bannereket sűrűbbre venni.
Ezt mondtad:
3) Hogyan lesznek **valódi affiliate linkek**?
Mi az hogy hogyan? Fillout-tal, onnan vissza és tovább a dognetre, ugye ezt nem állítottad el?
A ChatGPT ezt mondta:

Gondolkozás
Igen, a folyamat pont úgy maradt, ahogy kitaláltuk: Fillout → vissza /go-deal → Dognet (API) → céloldal. Semmit nem állítottam el, csak stabilabb lett a linképítés (API + fallback) és az UTM/amb/src továbbadás.
Mit csinál most a snippet?
Shop-kártya kattintás:
Ha az oldal URL-jében van d1=... → azonnal megy a /go/{shop_slug}-ra (Dognet API-val építünk linket; ha gond van, legacy go.dognet.com).
Ha nincs d1 → Fillout-ra visz (fillout_url?shop={slug} + opcionálisan amb), ott megadod a szervezetet, majd a Fillout a végén visszairányít /go-deal-re.
Banner (akció) kattintás:
Mindig Fillout-
Ezt mondtad:
ok. De amit adtál kód csak a webshop kártyákat adja be scrollozva és kategorizálva nem ak akciókat
A ChatGPT ezt mondta:

Gondolkozás
Igazad van: a scroller most csak a shop kártyákat teszi ki, mert a snippetben lévő banners_csv_url nem arra a táblára mutat, amit te használsz — ezért üres a bannerek tömb, és nincs mit „befecskendezni”.
Mi a hiba?
A snippetben most ez van:
'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=328401803&single=true&output=csv',
Te viszont ezt a dokumentumot használod (és ezt küldted korábban):
https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv
Vagyis más a doc azonosító (…1vT5s4B… vs …1vR8ASr…), ezért a snippet egy üres / másik fájlra néz.
Mit kell módosítani? (EGY SOR)
Cseréld le a snippetben a banners_csv_url-t erre (tegyünk rá cache-bustert is, hogy azonnal frissüljön):
'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=2',
Frissítés (cache miatt)
Mentsd a snippetet.
Nyisd meg egyszer: https://app.sharity.hu/?impactshop_refresh=1
Ha mégsem látnád rögtön az akciókat a scrollerben, emeld a végén a &v=2-t &v=3-ra.
Gyors ellenőrzés
A Banners sheet első sora legyen pontosan:
img,href,label,category
Az [impactshop_scroller inject_every="3"]-nél minden 3. shop után be fognak csúszni a Banners sorokból az akcióképek.
Ha szeretnéd, adok egy kis diagnosztika-kiegészítést is, ami kiírja: „Shops: N, Banners: M (URL: …)”, és megmutat 1–2 mintasort — jó debuggoló, ha legközelebb gyanúsan üres.
Ezt mondtad:
ttps://app.sharity.hu/?impactshop\_refresh=1
hol nyissam meg? böngésző?
A ChatGPT ezt mondta:

Gondolkozás
Igen, böngészőben kell megnyitni — de fontos a módja, mert a kódban ez a feltétel van:
csak frontenden fut (nem wp-admin),
és csak akkor, ha be vagy jelentkezve admin jogosultsággal (current_user_can('manage_options')).
Lépések
Jelentkezz be WordPress-be adminnal.
Ugyanabban a böngészőben, frontendes URL-ként nyisd meg:
https://app.sharity.hu/?impactshop_refresh=1
Nem kell semmit kattintani; ez a hívás lefuttatja a rewrite flush-t.
Utána
A shortcode-ok (pl. [impactshop_scroller], [impactshop_catalog]) működni fognak a friss rewrite-okkal.
Ha a Banners CSV tartalma nem frissül azonnal, emeld a banners_csv_url végén a cache-buster számot (pl. &v=3), mentsd a snippetet, és frissítsd az oldalt.
Ezt mondtad:
megint eltűntek a webről a stopok scrollok kategóriák. Faszom ki van, minden beállítást ismersz mindenhol. Miért nem találod meg a megoldást? Lehet nincsenek akciók, de akkor a blogban nem ezt kellene visszakapni? Ha kell teszteljük le úgy hogy állítsd be hogy lehívsz 1-1 db terméket véletlenszerűen mindegyik kategóriából. Annak működnie kell, ha ez már jó, akkor megpróbáljuk újra az akciót
A ChatGPT ezt mondta:

Gondolkozás
Értem a frusztrációt. Csináljuk meg úgy, hogy akkor is legyen mit kirakni, ha épp nincs akció a Banners CSV-ben: ha a bannerek üresek, a snippet automatikusan legenerál 1–1 “teszt” bannert kategóriánként (véletlenül választott shopból). Így a scroller és a kategóriák nem tűnnek el, és a teljes folyamat ugyanúgy: Fillout → /go-deal → Dognet.
Tedd az alábbi EGYBEN “fix pack”-et a mostani snippet végére és mentsd. Nem ütközik semmivel, csak kiegészít.

<?php
/* ==========================================================
 * FALLBACK AKCIÓK, HA ÜRES A BANNERS CSV
 * - Ha a Banners CSV üres, automatikusan készítünk 1-1 bannert
 *   kategóriánként, véletlenszerűen választott shopból.
 * - Kikapcsolható / kényszeríthető URL-ből: ?impactshop_test=1
 * ========================================================== */

/** Segéd: csoportosítás kategóriánként és véletlen kiválasztás */
if (!function_exists('impactshop_pick_per_category')) {
  function impactshop_pick_per_category($shops) {
    $by = [];
    foreach ($shops as $s) {
      $cat = $s['category'] ?: 'Egyéb';
      $by[$cat][] = $s;
    }
    // Minden kategóriából 1 véletlen shop
    $picked = [];
    foreach ($by as $cat => $arr) {
      $s = $arr[array_rand($arr)];
      $picked[] = [$cat, $s];
    }
    return $picked;
  }
}

/** Segéd: Fillout / go-deal URL előállítása egy shophoz (u nélkül is jó) */
if (!function_exists('impactshop_build_deal_href')) {
  function impactshop_build_deal_href($shop) {
    $d1  = impactshop_q('d1');
    $amb = impactshop_q('amb');
    $src = impactshop_q('src') ?: 'impactshop';
    $fillout = impactshop_settings()['fillout_url'];

    // Ha d1 már megvan az oldal URL-jében, mehetünk egyből /go-deal-re (u nélkül is működik a handler)
    if ($d1) {
      $args = ['shop' => $shop['shop_slug'], 'd1'=>$d1, 'amb'=>$amb, 'src'=>$src];
      return add_query_arg($args, home_url('/go-deal'));
    }
    // Különben előbb Fillout, onnan térünk vissza /go-deal-re
    return add_query_arg(['shop' => $shop['shop_slug'], 'amb' => $amb], $fillout);
  }
}

/** Synthetic (teszt) bannerek gyártása 1–1 darab / kategória */
if (!function_exists('impactshop_make_synthetic_banners')) {
  function impactshop_make_synthetic_banners() {
    $out = [];
    $shops = impactshop_get_shops();
    if (!$shops) return $out;
    foreach (impactshop_pick_per_category($shops) as $pair) {
      list($cat, $s) = $pair;
      $out[] = [
        'img'      => $s['logo'],                       // shop logó mint banner-kép
        'href'     => impactshop_build_deal_href($s),   // Fillout → /go-deal
        'label'    => mb_substr('Ajánlatok – '.$s['name'], 0, 90),
        'category' => $cat,
      ];
    }
    return $out;
  }
}

/** Banners felülírás: ha üres a CSV, vagy ?impactshop_test=1, gyártunk fallbacket */
if (!function_exists('impactshop_get_banners_fallback_wrapper')) {
  function impactshop_get_banners_fallback_wrapper() {
    // 1) Meglévő CSV beolvasása
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

    $forceTest = isset($_GET['impactshop_test']) && $_GET['impactshop_test'] == '1';

    // 2) Ha nincs banner, vagy kényszerített teszt, gyártsunk szintetikus bannereket
    if ($forceTest || !$out) {
      $synthetic = impactshop_make_synthetic_banners();
      if ($synthetic) $out = $synthetic;
    }
    return $out;
  }
}

/** A scroller és a katalógus ezt használja innentől */
if (function_exists('impactshop_get_banners')) {
  // már definiált: vegyük át a helyét egy wrapperrel
  if (!function_exists('impactshop_get_banners__orig')) {
    function impactshop_get_banners__orig() { return []; } // védő
  }
  // "alias": a további hívások a fallbackes verziót kapják
  function impactshop_get_banners() { return impactshop_get_banners_fallback_wrapper(); }
}

/* ====================== DEBUG SHORTCODE – ÁLLAPOT ====================== */
/** [impactshop_debug] – gyors ellenőrzés: hány shop/banner, honnan tölti */
if (!shortcode_exists('impactshop_debug')) {
  function impactshop_shortcode_debug() {
    $s = impactshop_settings();
    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners(); // már fallback-képes
    ob_start();
    ?>
    <div style="font:14px/1.5 system-ui; padding:10px; border:1px solid #ddd; border-radius:8px;">
      <div><b>Shops:</b> <?php echo count($shops); ?> &nbsp;|&nbsp;
           <b>Banners:</b> <?php echo count($banners); ?> <?php echo isset($_GET['impactshop_test'])?'(TEST MODE)':''; ?></div>
      <div style="margin-top:6px"><b>shops_csv_url:</b> <code style="font-size:12px"><?php echo esc_html($s['shops_csv_url']); ?></code></div>
      <div style="margin-top:4px"><b>banners_csv_url:</b> <code style="font-size:12px"><?php
Ezt mondtad:
egyben kérem a snippetet mert elgépelem. Hihetetlen hogy nem tudod megjegyezni
A ChatGPT ezt mondta:

Gondolkozás
értem. itt a teljes, EGYBEN beilleszthető snippet – scroller + katalógus + diag, Dognet auto-login + API/fallback redirect, és akció-fallback: ha a Banners CSV üres vagy ?impactshop_test=1, akkor 1-1 „teszt” banner kerül ki véletlen shopból kategóriánként, hogy a scroller és a kategóriák biztosan megjelenjenek.
Használat röviden
Illeszd be új snippetként.
Adminnal nyisd meg: https://app.sharity.hu/?impactshop_refresh=1
Ha azonnali CSV-frissítést akarsz, csak emeld az &v= számot a banners_csv_url végén (pl. &v=3).
Teszt mód: bármely oldal URL-jéhez add hozzá: ?impactshop_test=1
<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (UI + Redirect + Dognet API + Fallback + Akció-fallback)
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag], [impactshop_debug]
 * Endpontok:    /go  és  /go-deal
 * Flow:         Shop-katt: /go → Dognet API (fallback: legacy) → céloldal
 *               Banner-katt: Fillout → /go-deal → Dognet API/legacy → céloldal
 *
 * Telepítés: aktiválás után egyszer nyisd meg adminként (frontend): /?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

/** DOGNET belépők – (kérésedre kitöltve) */
if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');

/** (Opcionális) Fix token – ha üres, auto-login működik */
if (!defined('DOGNET_API_TOKEN')) define('DOGNET_API_TOKEN', ''); // üres → auto-login

/** Ha 0: automatikus kiválasztás az első "Web Site" jellegű ad channel */
if (!defined('DOGNET_AD_CHANNEL_ID')) define('DOGNET_AD_CHANNEL_ID', 0);

/** Cache idők */
if (!defined('IMPACTSHOP_CACHE_TTL')) define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))     define('DOGNET_TOKEN_TTL',     20 * HOUR_IN_SECONDS); // 24h - ráhagyás

/* ============================== BEÁLLÍTÁS ============================== */

if (!function_exists('impactshop_settings')) {
  function impactshop_settings() {
    return [
      'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
      // ← EZT HASZNÁLJUK a Banners CSV-hez (helyes dokumentum-ID) + cache-buster
      'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=2',
      'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
      'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
    ];
  }
}

/* ============================ CSV SEGÉDEK ============================ */

if (!function_exists('impactshop_slugify_header')) {
  function impactshop_slugify_header($s) {
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'];
    $s = strtr($s, $map);
    $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
    return trim($s, '_');
  }
}

if (!function_exists('impactshop_fetch_csv_assoc')) {
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
}

/* ======================= SHOPS & BANNERS MODELL ====================== */

if (!function_exists('impactshop_get_shops_raw')) {
  function impactshop_get_shops_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
  }
}
if (!function_exists('impactshop_get_banners_raw')) {
  function impactshop_get_banners_raw() {
    $s = impactshop_settings();
    return impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  }
}

if (!function_exists('impactshop_get_shops')) {
  function impactshop_get_shops() {
    $rows = impactshop_get_shops_raw();
    $out = [];
    foreach ($rows as $r) {
      $name   = $r['name']       ?? ($r['nev'] ?? '');
      $slug   = $r['shop_slug']  ?? ($r['slug'] ?? ($r['go_slug'] ?? ''));
      $cat    = $r['category']   ?? ($r['kategoria'] ?? 'Egyéb');
      $logo   = $r['logo_url']   ?? ($r['logo'] ?? ($r['image'] ?? ''));
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
}

/* -------- Akció-fallback: ha nincs banner, gyártsunk teszt bannereket -------- */

if (!function_exists('impactshop_pick_per_category')) {
  function impactshop_pick_per_category($shops) {
    $by = [];
    foreach ($shops as $s) {
      $cat = $s['category'] ?: 'Egyéb';
      $by[$cat][] = $s;
    }
    $picked = [];
    foreach ($by as $cat => $arr) {
      $s = $arr[array_rand($arr)];
      $picked[] = [$cat, $s];
    }
    return $picked;
  }
}

if (!function_exists('impactshop_build_deal_href')) {
  function impactshop_build_deal_href($shop) {
    $d1  = isset($_GET['d1']) ? sanitize_text_field($_GET['d1']) : '';
    $amb = isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '';
    $src = isset($_GET['src']) ? sanitize_text_field($_GET['src']) : 'impactshop';
    $fillout = impactshop_settings()['fillout_url'];
    if ($d1) {
      $args = ['shop' => $shop['shop_slug'], 'd1'=>$d1, 'amb'=>$amb, 'src'=>$src];
      return add_query_arg($args, home_url('/go-deal'));
    }
    return add_query_arg(['shop' => $shop['shop_slug'], 'amb' => $amb], $fillout);
  }
}

if (!function_exists('impactshop_get_banners')) {
  function impactshop_get_banners() {
    // 1) Normál Banners CSV
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

    // 2) Fallback (teszt) – ha üres vagy ?impactshop_test=1
    $forceTest = isset($_GET['impactshop_test']) && $_GET['impactshop_test'] == '1';
    if ($forceTest || !$out) {
      $shops = impactshop_get_shops();
      foreach (impactshop_pick_per_category($shops) as $pair) {
        list($cat, $s) = $pair;
        $out[] = [
          'img'      => $s['logo'],
          'href'     => impactshop_build_deal_href($s),
          'label'    => mb_substr('Ajánlatok – '.$s['name'], 0, 90),
          'category' => $cat,
        ];
      }
    }
    return $out;
  }
}

/* ========================= KÖZÖS SEGÉD ========================= */

if (!function_exists('impactshop_q')) {
  function impactshop_q($key, $def='') {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : $def;
  }
}
if (!function_exists('impactshop_find_shop')) {
  function impactshop_find_shop($slug) {
    $slug = trim(strtolower($slug));
    foreach (impactshop_get_shops() as $s) {
      if (strtolower($s['shop_slug']) === $slug) return $s;
    }
    return null;
  }
}
if (!function_exists('impactshop_error')) {
  function impactshop_error($msg) {
    status_header(400);
    wp_die('<div style="padding:24px;font:16px/1.5 system-ui">'. esc_html($msg) .'</div>', 'WordPress › hiba');
  }
}

/* ====================== DOGNET PUBLISHER API ====================== */

if (!function_exists('dognet_token_key')) {
  function dognet_token_key() { return 'dognet_api_token_cache_v1'; }
}

if (!function_exists('dognet_get_token')) {
  function dognet_get_token($force_refresh = false) {
    if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;

    $key = dognet_token_key();
    if (!$force_refresh) {
      $tok = get_transient($key);
      if ($tok) return $tok;
    }
    if (!DOGNET_LOGIN_EMAIL || !DOGNET_LOGIN_PASSWORD) return '';
    $url = 'https://api.app.dognet.com/api/v1/auth/login';
    $resp = wp_remote_post($url, [
      'timeout' => 20,
      'headers' => ['Content-Type' => 'application/json'],
      'body'    => wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]),
    ]);
    if (is_wp_error($resp)) return '';
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300 || !is_array($json)) return '';
    $token = $json['token'] ?? ($json['data']['token'] ?? '');
    if ($token) {
      set_transient($key, $token, DOGNET_TOKEN_TTL);
      return $token;
    }
    return '';
  }
}

if (!function_exists('dognet_api_request')) {
  function dognet_api_request($method, $path, $body = null) {
    $token = dognet_get_token(false);
    if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');

    $url   = 'https://api.app.dognet.com/api/v1' . $path;

    $do_request = function($bearer) use ($method, $url, $body) {
      $args = [
        'timeout' => 20,
        'headers' => [
          'Authorization' => 'Bearer ' . $bearer,
          'Content-Type'  => 'application/json',
        ],
        'method'  => $method,
      ];
      if (!is_null($body)) $args['body'] = wp_json_encode($body);
      return wp_remote_request($url, $args);
    };

    $resp = $do_request($token);
    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);

    if ($code === 401) {
      $token = dognet_get_token(true);
      if (!$token) return new WP_Error('auth_failed','Dognet API auth sikertelen (401).');
      $resp = $do_request($token);
      if (is_wp_error($resp)) return $resp;
      $code = wp_remote_retrieve_response_code($resp);
    }

    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300) {
      return new WP_Error('api_error', 'Dognet API hiba: '.$code, ['resp'=>$json]);
    }
    return $json;
  }
}

if (!function_exists('dognet_api_pick_ad_channel_id')) {
  function dognet_api_pick_ad_channel_id() {
    if (DOGNET_AD_CHANNEL_ID) return DOGNET_AD_CHANNEL_ID;
    $list = dognet_api_request('GET','/ad-channels');
    if (is_wp_error($list) || empty($list['data'])) return 0;
    foreach ($list['data'] as $c) {
      $type = isset($c['type']) ? strtolower((string)$c['type']) : '';
      if (strpos($type,'web') !== false) return intval($c['id']);
    }
    return intval($list['data'][0]['id']);
  }
}

if (!function_exists('dognet_extract_campaign_id_from_base')) {
  function dognet_extract_campaign_id_from_base($dognet_base) {
    if (!$dognet_base) return 0;
    $qs = parse_url($dognet_base, PHP_URL_QUERY);
    parse_str($qs, $parts);
    return isset($parts['cid']) ? intval($parts['cid']) : 0;
  }
}

if (!function_exists('dognet_api_generate_link')) {
  function dognet_api_generate_link($campaign_id, $deeplink = '', $d1 = '', $d2 = '') {
    $ad_channel_id = dognet_api_pick_ad_channel_id();
    if (!$ad_channel_id) return new WP_Error('no_channel','Nincs elérhető ad_channel');

    $body = [
      'ad_channel_id' => intval($ad_channel_id),
      'campaign_id'   => intval($campaign_id),
      'url_type'      => 3, // simple
    ];
    if ($deeplink) $body['url']   = $deeplink;
    if ($d1)       $body['data1'] = $d1;
    if ($d2)       $body['data2'] = $d2;

    $json = dognet_api_request('POST','/campaigns/links/generate', $body);
    if (is_wp_error($json)) return $json;

    foreach (['url','short_url','full_url'] as $k) {
      if (!empty($json[$k])) return $json[$k];
      if (!empty($json['data'][$k])) return $json['data'][$k];
    }
    return new WP_Error('bad_api','Ismeretlen API válasz', $json);
  }
}

/* ==================== LEGACY (go.dognet.com) BUILD =================== */

if (!function_exists('impactshop_build_legacy_go_url')) {
  function impactshop_build_legacy_go_url($shopRow, $ngo_code, $maybeUrl) {
    $base = trim($shopRow['dognet_base'] ?? '');
    if (!$base) return [null, 'Hiányzik a dognet_base a Shops CSV-ben.'];

    $params = [];
    if ($ngo_code) $params['d1'] = $ngo_code;

    $deeplinkParam = $shopRow['deeplink_param'] ?: 'url';
    if (!empty($maybeUrl)) {
      $decoded = $maybeUrl;
      if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $maybeUrl)) {
        $tmp = base64_decode($maybeUrl, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $decoded = $tmp;
      }
      $params[$deeplinkParam] = $decoded;
    }

    $glue = (strpos($base, '?') === false) ? '?' : '&';
    $url = $base . $glue . http_build_query($params);
    return [$url, null];
  }
}

/* ============================ REDIRECTEK ============================ */

if (!function_exists('impactshop_add_rewrites')) {
  function impactshop_add_rewrites() {
    add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
    add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
  }
  add_action('init','impactshop_add_rewrites');
}

if (!function_exists('impactshop_add_query_vars')) {
  function impactshop_add_query_vars($vars) {
    $vars[] = 'impactshop_go';
    $vars[] = 'impactshop_deal';
    return $vars;
  }
  add_filter('query_vars','impactshop_add_query_vars');
}

add_action('template_redirect', function(){
  if (get_query_var('impactshop_go'))   { impactshop_handle_go(false); exit; }
  if (get_query_var('impactshop_deal')) { impactshop_handle_go(true);  exit; }
});

if (!function_exists('impactshop_redirect_with_propagation')) {
  function impactshop_redirect_with_propagation($url, $amb, $src) {
    $add = [];
    if ($amb && strpos($url,'amb=')===false) $add['amb']=$amb;
    if ($src && strpos($url,'src=')===false) $add['src']=$src;
    if (strpos($url,'utm_source=')===false) $add['utm_source']='sharity';
    if (strpos($url,'utm_medium=')===false) $add['utm_medium']='impactshop';
    if ($add) $url .= (strpos($url,'?')===false ? '?' : '&') . http_build_query($add);
    wp_redirect($url, 307);
    exit;
  }
}

if (!function_exists('impactshop_handle_go')) {
  function impactshop_handle_go($is_deal) {
    $shop = impactshop_q('shop');
    $ngo  = impactshop_q('d1');
    $u    = impactshop_q('u');
    $amb  = impactshop_q('amb');
    $src  = impactshop_q('src') ?: 'impactshop';

    if (!$shop || !$ngo) impactshop_error('Hiányzó paraméter (shop/d1).');
    $row = impactshop_find_shop($shop);
    if (!$row) impactshop_error('Ismeretlen shop: '.esc_html($shop));

    $targetUrl = '';
    if ($is_deal) {
      if ($u) {
        $targetUrl = $u;
      } else {
        $fallback = trim($row['product_url'] ?? '');
        if ($fallback) $targetUrl = $fallback;
      }
    }

    $final = null;
    $cid   = dognet_extract_campaign_id_from_base($row['dognet_base'] ?? '');
    if ($cid) {
      $deeplink = $targetUrl;
      if ($deeplink && preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $deeplink)) {
        $tmp = base64_decode($deeplink, true);
        if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $deeplink = $tmp;
      }
      $api = dognet_api_generate_link($cid, $deeplink, $ngo, '');
      if (!is_wp_error($api) && $api) $final = $api;
    }

    if (!$final) {
      list($legacy,) = impactshop_build_legacy_go_url($row, $ngo, $targetUrl);
      if ($legacy) $final = $legacy;
    }
    if (!$final) impactshop_error('Nem sikerült a partner linket előállítani.');

    impactshop_redirect_with_propagation($final, $amb, $src);
  }
}

/* ============================ SHORTCODE-OK ============================ */

/** Scroller – shopok + banner injektálás (akár fallback-ból is) */
if (!shortcode_exists('impactshop_scroller')) {
  function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts([
      'category'     => '',
      'inject_every' => 5,
      'speed'        => 30,
    ], $atts);

    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners();

    if (!empty($a['category'])) {
      $catWanted = $a['category'];
      $shops = array_values(array_filter($shops, function($s) use ($catWanted){ return strcasecmp($s['category'],$catWanted)===0; }));
      $banners = array_values(array_filter($banners, function($b) use ($catWanted){ return empty($b['category']) || strcasecmp($b['category'],$catWanted)===0; }));
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
    $stream = array_merge($mixed, $mixed); // végtelenített

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
      .impactshop-item{display:inline-block;margin-right:20px}
    </style>
    <div class="impactshop-scroller">
      <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
        <?php foreach ($stream as $it): ?>
          <?php if ($it['type']==='shop'): $shop=$it['data'];
            if ($d1) {
              $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
            } else {
              $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
            } ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
              </a>
            </div>
          <?php else: $b=$it['data']; ?>
            <div class="impactshop-item">
              <a href="<?php echo esc_url($b['href']); ?>" aria-label="<?php echo esc_attr($b['label']); ?>">
                <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['label']); ?>"
                     loading="lazy" decoding="async" style="height:60px;width:auto">
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
}

/** Katalógus */
if (!shortcode_exists('impactshop_catalog')) {
  function impactshop_shortcode_catalog($atts) {
    $a = shortcode_atts([
      'show_tabs' => '1',
      'search'    => '1',
      'per_page'  => '200',
    ], $atts);

    $shops = impactshop_get_shops();
    if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

    usort($shops, function($x,$y){
      $c = strcasecmp($x['category'],$y['category']);
      return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
    });

    $cats = [];
    foreach ($shops as $s) { $cats[$s['category']] = true; }
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
          if ($d1) {
            $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug'])));
          } else {
            $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout);
          } ?>
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
}

/** Diagnosztika */
if (!shortcode_exists('impactshop_diag')) {
  function impactshop_shortcode_diag() {
    $shops = impactshop_get_shops();
    $bann  = impactshop_get_banners();
    $s     = impactshop_settings();
    $out = '<div style="font:14px/1.5 system-ui"><h3>Impact Shop diagnosztika</h3>';
    $out .= '<p>Shops: <b>'.count($shops).'</b> &nbsp;|&nbsp; Banners: <b>'.count($bann).'</b></p>';
    $out .= '<p style="font-size:12px;margin-top:-6px">Banners CSV: <code>'.esc_html($s['banners_csv_url']).'</code></p>';
    $miss = [];
    foreach ($shops as $s1) {
      if (empty($s1['dognet_base']))    $miss[] = $s1['shop_slug'].' (dognet_base)';
      if (empty($s1['deeplink_param'])) $miss[] = $s1['shop_slug'].' (pdognet_deeplink_param)';
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
}

/** Debug – rövid állapotjelentés */
if (!shortcode_exists('impactshop_debug')) {
  function impactshop_shortcode_debug() {
    $s = impactshop_settings();
    $shops   = impactshop_get_shops();
    $banners = impactshop_get_banners();
    ob_start(); ?>
    <div style="font:14px/1.5 system-ui; padding:10px; border:1px solid #ddd; border-radius:8px;">
      <div><b>Shops:</b> <?php echo count($shops); ?> &nbsp;|&nbsp; <b>Banners:</b> <?php echo count($banners); ?> <?php echo isset($_GET['impactshop_test'])?'(TEST MODE)':''; ?></div>
      <div style="margin-top:6px"><b>shops_csv_url:</b> <code style="font-size:12px"><?php echo esc_html($s['shops_csv_url']); ?></code></div>
      <div style="margin-top:4px"><b>banners_csv_url:</b> <code style="font-size:12px"><?php echo esc_html($s['banners_csv_url']); ?></code></div>
      <?php if ($banners): $b=$banners[0]; ?>
        <div style="margin-top:8px"><b>Minta banner:</b> <code style="font-size:12px"><?php echo esc_html(json_encode($b, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); ?></code></div>
      <?php endif; ?>
    </div>
    <?php return ob_get_clean();
  }
  add_shortcode('impactshop_debug','impactshop_shortcode_debug');
}

/* ===================== REWRITE FLUSH (kézzel) ===================== */
add_action('init', function(){
  if (is_admin()) return;
  if (current_user_can('manage_options') && isset($_GET['impactshop_refresh'])) {
    flush_rewrite_rules();
  }
});
Tipp a próbaüzemhez
Tedd egy oldalra:
[impactshop_scroller inject_every="2" speed="20"]
[impactshop_catalog show_tabs="1" search="1"]
[impactshop_debug]
Ha üresek az akciók, tesztelj így: /?impactshop_test=1 – a scroller ilyenkor is mutatni fog bannereket kategóriánként. Ha ez rendben, akkor a következő körben visszaállhatsz a valódi Banners CSV-re.
Ezt mondtad:
Feltöltött kép
A ChatGPT ezt mondta:

Gondolkozás
Látom a képernyőn: a scroller és a katalógus él, a debug szerint Shops: 5 | Banners: 4 — ez most a teszt/fallback bannereket jelenti (a “Minta banner” is ilyen: Fillout-link + „Ajánlatok – 4home” címke). Tehát a „tűnnek-fel-tűnnek-el” jelenség helyett most már mindig lesznek bannerek, még akkor is, ha a Banners CSV üres.
Hogyan váltunk át ÉLES akciókra?
Banners CSV első sora maradjon így:
img,href,label,category
Adj hozzá 1 tesztsort kézzel (nem kell base64—mehet sima URL is, csak URL-kódold):
img: egy kép URL-je (pl. a shop logója)
href:
https://form.fillout.com/t/eM61RLkz6jus?shop=4home&u=https%3A%2F%2Fwww.4home.hu%2F
(Ugyanígy bármely shop_slug + termék URL)
label: pl. Őszi kiárusítás – 4home
category: a shop kategóriája (pl. Otthon)
Frissítés kényszerítése
A snippet cache-eli a CSV-t. A beállításban a banners_csv_url végén a &v= számot emeld (pl. &v=3), majd frissítsd az oldalt. A debug dobozban látni fogod, hogy Banners: … nőtt és a „Minta banner” már a CSV-ből jön.
Tesztmód ki-/bekapcsolása
KI: ne tedd az URL-be az impactshop_test=1 paramétert.
BE: ideiglenesen tegyél a végére ?impactshop_test=1 (vagy &impactshop_test=1). Ilyenkor akkor is lesz banner, ha a CSV üres.
Gyors ellenőrzőlista (ha mégis eltűnne valami)
A shortcode az oldalon: [impactshop_scroller inject_every="2" speed="20"] és alatta [impactshop_catalog].
impactshop_diag vagy impactshop_debug mutassa a számokat.
A banners_csv_url a jó dokumentumra mutasson (nálad az “1vR8ASr…” azonosítós), és a gid a Banners lap gid-je legyen.
Ha váltasz tartalmat, emeld a &v= értéket a CSV URL végén.
Ha küldesz egy konkrét, éles akciósort (img + href + label + category), azt azonnal be lehet szúrni a táblába, és meg fog jelenni a scrollerben a shopok közé injektálva. Utána rápróbálhatunk a teljes automatára is (Apps Script feed-szívás), de a fenti kézi sorral most rögtön látni fogod, hogy az élő akciók is szépen megjelennek.
Ezt mondtad:
`banners_csv_url` végén a `&v=` számot emeld (pl.
pontosan hol kell ezt?
A ChatGPT ezt mondta:
A &v= növelést a WordPress snippetben, az aktív ÖSSZEVONT kódban kell átírni – pont azon a soron, ahol a Banners CSV URL-je van.
Lépésről lépésre
WP admin → Code Snippets → nyisd meg az Impact Shop – ÖSSZEVONT… snippetet (amit most használsz).
Keresd meg az alábbi blokkot:
function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/.../output=csv',
    'banners_csv_url' => 'https://docs.google.com/.../output=csv&v=2', // ← EZ ITT
    'fillout_url'     => 'https://form.fillout.com/t/eM61RLkz6jus',
    'cache_ttl'       => IMPACTSHOP_CACHE_TTL,
  ];
}
Csak a végén a számot emeld: &v=2 → &v=3 (vagy nagyobb).
Save/Update a snippet, majd frissítsd az oldalt a böngészőben.
Ennyi. Ez megkerüli a 15 perces WP-cache-t, és azonnal újra letölti a Banners CSV-t.
(Ugyanezt megteheted a shops_csv_url-lal is, ha a Shops tartalmát akarod erőből frissíteni.)
Ezt mondtad:
Feltöltött kép
nem jelenik meg bannerként, csak így
A ChatGPT ezt mondta:

Gondolkozás
Értem mire gondolsz: a scroller most ugyanúgy 60px-es logóként rendereli a bannereket is, ezért “nem tűnik fel”, hogy azok akciók. A debug dobozban a JSON csak ellenőrzésre van — azt vedd le az oldalról (távolítsd el a [impactshop_debug] shortcode-ot), mert nem “banner”, csak diagnosztika.
Adok egy EGYBEN CSERÉLHETŐ scroller-függvényt, ami:

külön CSS-osztályt ad a bannereknek (.banner-item),
nagyobbra veszi őket (90–110px),
rátesz egy „AKCIÓ” jelvényt, hogy vizuálisan egyértelmű legyen.
Másold be változtatás nélkül, a meglévő impactshop_shortcode_scroller helyére.
/** Scroller – shopok + BANNEREK KIEMELVE (nagyobb kép + „AKCIÓ” címke) */
if (function_exists('remove_shortcode')) remove_shortcode('impactshop_scroller');
function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts([
    'category'     => '',
    'inject_every' => 5,
    'speed'        => 30,
  ], $atts);

  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  if (!empty($a['category'])) {
    $catWanted = $a['category'];
    $shops = array_values(array_filter($shops, function($s) use ($catWanted){ return strcasecmp($s['category'],$catWanted)===0; }));
    $banners = array_values(array_filter($banners, function($b) use ($catWanted){ return empty($b['category']) || strcasecmp($b['category'],$catWanted)===0; }));
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
  $stream = array_merge($mixed, $mixed); // végtelenített

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
    .impactshop-item{display:inline-block;margin-right:20px; position:relative}
    .impactshop-item.shop-item img{height:60px;width:auto}
    .impactshop-item.banner-item img{height:100px;width:auto; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,.08)}
    .impactshop-badge{position:absolute;top:-8px;left:-8px;background:#ff3366;color:#fff;font:600 11px/1.2 system-ui;padding:4px 6px;border-radius:6px;box-shadow:0 1px 6px rgba(0,0,0,.15)}
    @media (max-width:640px){
      .impactshop-item.banner-item img{height:90px}
    }
  </style>
  <div class="impactshop-scroller">
    <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
      <?php foreach ($stream as $it): ?>
        <?php if ($it['type']==='shop'): $shop=$it['data'];
          if ($d1) { $cta = add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src], home_url('/go/'. rawurlencode($shop['shop_slug']))); }
          else     { $cta = add_query_arg(['shop'=>$shop['shop_slug'],'amb'=>$amb], $fillout); } ?>
          <div class="impactshop-item shop-item">
            <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
              <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>" loading="lazy" decoding="async">
            </a>
          </div>
        <?php else: $b=$it['data']; ?>
          <div class="impactshop-item banner-item">
            <span class="impactshop-badge" aria-hidden="true">AKCIÓ</span>
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
add_shortcode('impactshop_scroller','impactshop_shortcode
Ezt mondtad:
egyben kérem a snippetet nem tudom beleollózni sem cserélni
A ChatGPT ezt mondta:
Ezt mondtad:
egyben kérem a snippetet nem tudom beleollózni sem cserélni
A ChatGPT ezt mondta:
Most már világos, mit szeretnél: egyetlen, teljes snippetet, amiben benne van minden (Dognet auto-login API, fallback, scroller + katalógus + diag, és a bannerek kiemelve nagyobb méretben, “AKCIÓ” jelvénnyel).
Itt a teljes kód egyben, így nem kell ollóznod vagy darabolnod:

<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (Dognet API auto-login + Redirect + UI + Banner highlight)
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag], [impactshop_debug]
 * Endpontok:    /go és /go-deal
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', ''); // üres → auto-login
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 0);
if (!defined('IMPACTSHOP_CACHE_TTL'))  define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);

/* ============================== BEÁLLÍTÁS ============================== */

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=3',
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

/* ====================== DOGNET API ====================== */

function dognet_get_token($force_refresh = false) {
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key = 'dognet_api_token_cache_v1';
  if (!$force_refresh) {
    $tok = get_transient($key);
    if ($tok) return $tok;
  }
  $url = 'https://api.app.dognet.com/api/v1/auth/login';
  $resp = wp_remote_post($url, [
    'timeout' => 20,
    'headers' => ['Content-Type' => 'application/json'],
    'body'    => wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]),
  ]);
  if (is_wp_error($resp)) return '';
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code < 200 || $code >= 300 || !is_array($json)) return '';
  $token = $json['token'] ?? ($json['data']['token'] ?? '');
  if ($token) {
    set_transient($key, $token, DOGNET_TOKEN_TTL);
    return $token;
  }
  return '';
}

function dognet_api_request($method, $path, $body=null) {
  $token = dognet_get_token(false);
  if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');
  $url = 'https://api.app.dognet.com/api/v1'.$path;
  $args = [
    'timeout'=>20,
    'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'],
    'method'=>$method,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);
  $resp = wp_remote_request($url,$args);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp),true);
  if ($code==401) {
    delete_transient('dognet_api_token_cache_v1');
    $token = dognet_get_token(true);
    if ($token) return dognet_api_request($method,$path,$body);
  }
  if ($code<200||$code>=300) return new WP_Error('api_error','Dognet API hiba '.$code,['resp'=>$json]);
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
  add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
  add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[]='impactshop_go';$vars[]='impactshop_deal';return $vars;
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
  $shop=impactshop_q('shop');$ngo=impactshop_q('d1');$u=impactshop_q('u');
  $amb=impactshop_q('amb');$src=impactshop_q('src')?:'impactshop';
  if(!$shop||!$ngo)impactshop_error('Hiányzó paraméter (shop/d1).');
  $row=impactshop_find_shop($shop);if(!$row)impactshop_error('Ismeretlen shop: '.esc_html($shop));
  $targetUrl='';if($is_deal){$targetUrl=$u?:($row['product_url']??'');}
  $final=null;$cid=dognet_extract_campaign_id_from_base($row['dognet_base']??'');
  if($cid){$deeplink=$targetUrl;if($deeplink&&preg_match('~^[A-Za-z0-9+/]+={0,2}$~',$deeplink)){
    $tmp=base64_decode($deeplink,true);if($tmp!==false&&preg_match('~^https?://~i',$tmp))$deeplink=$tmp;}
    $api=dognet_api_generate_link($cid,$deeplink,$ngo,'');if(!is_wp_error($api)&&$api)$final=$api;}
  if(!$final){$base=$row['dognet_base']??'';if($base){$glue=(strpos($base,'?')===false)?'?':'&';
    $final=$base.$glue.http_build_query(['d1'=>$ngo]);}}
  if(!$final)impactshop_error('Nem sikerült a linket előállítani.');
  impactshop_redirect_with_propagation($final,$amb,$src);
}

/* ==================== SHORTCODE-OK =================== */

function impactshop_shortcode_scroller($atts) {
  $a=shortcode_atts(['category'=>'','inject_every'=>5,'speed'=>30],$atts);
  $shops=impactshop_get_shops();$banners=impactshop_get_banners();
  $mixed=[];$bi=0;
  foreach($shops as $i=>$s){$mixed[]=['type'=>'shop','data'=>$s];
    if(($i+1)%intval($a['inject_every'])===0&&$banners){$mixed[]=['type'=>'banner','data'=>$banners[$bi%count($banners)]];$bi++;}}
  $stream=array_merge($mixed,$mixed);
  $d1=impactshop_q('d1');$amb=impactshop_q('amb');$src=impactshop_q('src')?:'impactshop';
  $fillout=impactshop_settings()['fillout_url'];
  ob_start();?>
  <style>
    .impactshop-scroller{overflow:hidden;width:100%;white-space:nowrap;position:relative}
    .impactshop-scroller-track{display:inline-block;white-space:nowrap;animation:impactshop-scroll linear infinite}
    @keyframes impactshop-scroll{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .impactshop-scroller:hover .impactshop-scroller-track{animation-play-state:paused}
    .impactshop-item{display:inline-block;margin-right:20px;position:relative}
    .impactshop-item.shop-item img{height:60px;width:auto}
    .impactshop-item.banner-item img{height:100px;width:auto;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.1)}
    .impactshop-badge{position:absolute;top:-8px;left:-8px;background:#ff3366;color:#fff;font:600 11px system-ui;padding:4px 6px;border-radius:6px}
  </style>
  <div class="impactshop-scroller">
    <div class="impactshop-scroller-track" style="animation-duration:<?php echo max(5,intval($a['speed'])); ?>s;">
      <?php foreach($stream as $it): if($it['type']==='shop'){$s=$it['data'];
        $cta=$d1?add_query_arg(['d1'=>$d1,'amb'=>$amb,'src'=>$src],home_url('/go/'.rawurlencode($s['shop_slug'])))
                :add_query_arg(['shop'=>$s['shop_slug'],'amb'=>$amb],$fillout);?>
        <div class="impactshop-item shop-item">
          <a href="<?php echo esc_url($cta); ?>"><img src="<?php echo esc_url($s['logo']); ?>" alt=""></a>
        </div>
      <?php } else {$b=$it['data'];?> 
        <div class="impactshop-item banner-item">
          <span class="impactshop-badge">
Ezt mondtad:
Még egyszer mert itt vmi félbeszakadt. Egyben cserélem a snippetet. Kérem a kódot egyben a megbeszélt módosításokkal
A ChatGPT ezt mondta:
Íme az EGYBEN cserélhető, teljes snippet a megbeszélt módosításokkal (Dognet auto-login + /go, /go-deal + scroller kiemelt bannerekkel + katalógus + diagnosztika + kézi debug). Másold be egyetlen darabként:
<?php
/**
 * Impact Shop – ÖSSZEVONT SNIPPET (Dognet API auto-login + Redirect + UI + Banner highlight)
 * Shortcode-ok: [impactshop_scroller], [impactshop_catalog], [impactshop_diag], [impactshop_debug]
 * Endpontok:    /go és /go-deal
 * Megjegyzés: aktiválás után egyszer nyisd meg: https://app.sharity.hu/?impactshop_refresh=1
 */

/* ============================== KONFIG ============================== */

if (!defined('DOGNET_LOGIN_EMAIL'))    define('DOGNET_LOGIN_EMAIL',    'office@sharity.hu');
if (!defined('DOGNET_LOGIN_PASSWORD')) define('DOGNET_LOGIN_PASSWORD', 'cuXsuj-8wenbo-kimnac');
if (!defined('DOGNET_API_TOKEN'))      define('DOGNET_API_TOKEN', ''); // üres → auto-login (24h token)
if (!defined('DOGNET_AD_CHANNEL_ID'))  define('DOGNET_AD_CHANNEL_ID', 0); // 0: első "Web Site" jellegű csatorna
if (!defined('IMPACTSHOP_CACHE_TTL'))  define('IMPACTSHOP_CACHE_TTL', 15 * MINUTE_IN_SECONDS);
if (!defined('DOGNET_TOKEN_TTL'))      define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS); // biztonsági ráhagyás

/* ============================== BEÁLLÍTÁS ============================== */

function impactshop_settings() {
  return [
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv',
    // Banners lap (gid=328401803) – a &v= számot emeld cache-törléshez
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=328401803&single=true&output=csv&v=3',
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

/* ====================== DOGNET API ====================== */

function dognet_get_token($force_refresh = false) {
  if (DOGNET_API_TOKEN) return DOGNET_API_TOKEN;
  $key = 'dognet_api_token_cache_v1';
  if (!$force_refresh) {
    $tok = get_transient($key);
    if ($tok) return $tok;
  }
  $resp = wp_remote_post('https://api.app.dognet.com/api/v1/auth/login', [
    'timeout' => 20,
    'headers' => ['Content-Type' => 'application/json'],
    'body'    => wp_json_encode(['email'=>DOGNET_LOGIN_EMAIL,'password'=>DOGNET_LOGIN_PASSWORD]),
  ]);
  if (is_wp_error($resp)) return '';
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp), true);
  if ($code < 200 || $code >= 300 || !is_array($json)) return '';
  $token = $json['token'] ?? ($json['data']['token'] ?? '');
  if ($token) {
    set_transient($key, $token, DOGNET_TOKEN_TTL);
    return $token;
  }
  return '';
}

function dognet_api_request($method, $path, $body=null) {
  $token = dognet_get_token(false);
  if (!$token) return new WP_Error('no_token','Dognet API token nem elérhető');
  $url = 'https://api.app.dognet.com/api/v1'.$path;
  $args = [
    'timeout'=>20,
    'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json'],
    'method'=>$method,
  ];
  if ($body!==null) $args['body']=wp_json_encode($body);
  $resp = wp_remote_request($url,$args);
  if (is_wp_error($resp)) return $resp;
  $code = wp_remote_retrieve_response_code($resp);
  $json = json_decode(wp_remote_retrieve_body($resp),true);
  if ($code==401) { // token lejárt → egyszeri frissítés
    delete_transient('dognet_api_token_cache_v1');
    $token = dognet_get_token(true);
    if ($token) return dognet_api_request($method,$path,$body);
  }
  if ($code<200||$code>=300) return new WP_Error('api_error','Dognet API hiba '.$code,['resp'=>$json]);
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
  add_rewrite_rule('^go/?$',      'index.php?impactshop_go=1',   'top');
  add_rewrite_rule('^go-deal/?$', 'index.php?impactshop_deal=1', 'top');
}
add_action('init','impactshop_add_rewrites');

function impactshop_add_query_vars($vars) {
  $vars[]='impactshop_go';$vars[]='impactshop_deal';return $vars;
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
  $shop=impactshop_q('shop');$ngo=impactshop_q('d1');$u=impactshop_q('u');
  $amb=impactshop_q('amb');$src=impactshop_q('src')?:'impactshop';
  if(!$shop||!$ngo)impactshop_error('Hiányzó paraméter (shop/d1).');
  $row=impactshop_find_shop($shop);if(!$row)impactshop_error('Ismeretlen shop: '.esc_html($shop));

  // /go-deal: cél deeplink (base64 elfogadott) – ha nincs, próbáljuk a CSV product_url-t
  $targetUrl='';
  if($is_deal){
    $targetUrl=$u?:($row['product_url']??'');
  }

  // API first (ha a dognet_base-ből kinyerhető a cid)
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

  // Fallback: legacy go.dognet.com (a dognet_base + d1 + opcionális deeplink_param)
  if(!$final){
    $base=$row['dognet_base']??'';
    if($base){
      $params=['d1'=>$ngo];
      if(!empty($targetUrl)){
        $deeplinkParam = !empty($row['deeplink_param']) ? $row['deeplink_param'] : 'url';
        $params[$deeplinkParam] = $targetUrl;
      }
      $final = $base . ((strpos($base,'?')===false)?'?':'&') . http_build_query($params);
    }
  }

  if(!$final)impactshop_error('Nem sikerült a partner linket előállítani.');
  impactshop_redirect_with_propagation($final,$amb,$src);
}

/* ==================== SHORTCODE-OK =================== */

/** Scroller – shopok + BANNEREK KIEMELVE (nagyobb kép + „AKCIÓ” címke) */
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
  $stream = array_merge($mixed, $mixed); // végtelenített

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

/** Egyszerű debug (opcionális) */
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

shops_csv_url: <?php echo esc_html($s['shops_csv_url']); ?>

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
Használat:
Akciós bannerek megjelenítése a scrollerben: [impactshop_scroller inject_every="3" speed="25"]
Katalógus: [impactshop_catalog]
Diagnosztika: [impactshop_diag]
Debug (ha kell, ideiglenesen): [impactshop_debug]
Ha a Banners CSV-t frissíted és azonnali megjelenést szeretnél, emeld a impactshop_settings() részben a banners_csv_url végén a &v= számot (pl. &v=4).



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
