Átugrás a tartalomra
Ezt mondtad:
xml feed folytatása: új csevegésben folytatjuk, de vedd figyelembe a korábbi csevegés adatait. Főleg az utolsó kutatás eredményeit
A ChatGPT ezt mondta:

Gondolkozás
Szuper, visszavesszük a fonalat. Úgy folytatom, hogy illeszkedjen a korábbi flow-hoz (Fillout NGO-választás → /go/{shop} → Dognet, d1/data1 rögzítve), és ráültetjük az XML feed alapú „Akciók” megjelenítést WordPressen – WPCode-dal, gyors cache-sel, és olyan linkekkel, amelyek vagy a Filloutra visznek NGO-választásra, vagy ha már van d1, akkor egyből a /go/{shop} felé továbbítanak.
Alább egy kész, bemásolható WPCode snippet (PHP), ami:

letölti és cache-eli a megadott shop XML feedjét,
kiszűri az akciós tételeket (régi ár > aktuális ár, vagy „promo” jelzés),
táblázatban jeleníti meg,
gombot ad: „Támogató vásárlás” → ha nincs d1 a jelenlegi URL-ben, a Filloutra visz előtöltött adatokkal; ha van d1, akkor a /go/{shop} linkre megy és továbbadja a paramétereket,
shortcode: [impactshop_akciok shop="ARUKERESO" min_discount="15" limit="50" sort="discount"].
Tedd be: WordPress → WPCode → Add Snippet → PHP Snippet → Active.
<?php
/*
 * Impact Shop – Akciós feed megjelenítés (XML → táblázat + CTA)
 * Szükséges: WPCode plugin, Redirection (a /go/{shop} szabályod már megvan), Fillout űrlap hidden mezőkkel: shop, amb, ngo_code (számított)
 */

// 1) Shop → feed beállítások (IDE ÍRD BE A VALÓDI FEED URL-EKET)
function impactshop_feeds_config() {
  return [
    'ARUKERESO' => [
      // Példa: 'https://example.com/arukereso-feed.xml'
      'xml_url' => 'https://IDE-ILLESZD-BE-AZ-XML-FEED-URLT',
      // Az XML mezőnevekhez igazítsd a map-et (lásd parse rész)
      'field_map' => [
        'id'         => 'id',
        'title'      => 'name',
        'price'      => 'price',
        'old_price'  => 'old_price', // ha nincs, számolunk nulláról
        'currency'   => 'currency',
        'url'        => 'url',
        'image'      => 'image',
        'brand'      => 'brand',
        'category'   => 'category',
        'promo_flag' => 'promo' // ha a feedben van valami „promo/akc” jelzés
      ]
    ],
    // Ide vehetsz fel több shopot ugyanígy…
  ];
}

// 2) Segéd: aktuális oldalról d1/amb/src paramok kiolvasása (ha vannak)
function impactshop_get_query($key) {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : '';
}

// 3) XML letöltés + cache
function impactshop_fetch_xml_items($shop_key) {
  $cfgs = impactshop_feeds_config();
  $key = strtoupper($shop_key);
  if (!isset($cfgs[$key])) return [];

  $cache_key = 'impactshop_xml_' . $key;
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $url = $cfgs[$key]['xml_url'];
  $resp = wp_remote_get($url, ['timeout' => 15]);
  if (is_wp_error($resp)) return [];

  $body = wp_remote_retrieve_body($resp);
  if (!$body) return [];

  // Egyszerű XML parse – igazítsuk az XML szerkezethez
  libxml_use_internal_errors(true);
  $xml = simplexml_load_string($body);
  if (!$xml) return [];

  // Próbáljuk meg kitalálni, hol vannak az itemek
  // Gyakoribb esetek: <items><item>…</item></items> vagy <products><product>
  $items = [];
  if (isset($xml->item))         $items = $xml->item;
  elseif (isset($xml->items))    $items = $xml->items->item;
  elseif (isset($xml->product))  $items = $xml->product;
  elseif (isset($xml->products)) $items = $xml->products->product;
  else                           $items = $xml->children();

  set_transient($cache_key, $items, 30 * MINUTE_IN_SECONDS);
  return $items;
}

// 4) Item → normalizált tömb
function impactshop_normalize_item($node, $map) {
  $get = function($path) use ($node) {
    if (!$path) return '';
    // Egyszintű egyszerű mező (pl. 'price'); ha összetettebb, itt lehet bővíteni
    return isset($node->{$path}) ? trim((string)$node->{$path}) : '';
  };

  $price     = floatval(str_replace([',',' '], ['.',''], $get($map['price'] ?? '')));
  $old_price = floatval(str_replace([',',' '], ['.',''], $get($map['old_price'] ?? '')));
  $promo     = $get($map['promo_flag'] ?? '') ?: '';

  // Ha nincs old_price, de van „promo” jelzés, megpróbálhatnánk becsült értéket számolni – inkább 0 marad, és csak price-ból indulunk ki.
  $discount = 0;
  if ($old_price > 0 && $price > 0 && $old_price > $price) {
    $discount = round((1 - ($price / $old_price)) * 100);
  }

  return [
    'id'        => $get($map['id'] ?? ''),
    'title'     => $get($map['title'] ?? ''),
    'price'     => $price,
    'old_price' => $old_price,
    'discount'  => $discount,
    'currency'  => $get($map['currency'] ?? ''),
    'url'       => $get($map['url'] ?? ''),
    'image'     => $get($map['image'] ?? ''),
    'brand'     => $get($map['brand'] ?? ''),
    'category'  => $get($map['category'] ?? ''),
    'promo'     => $promo
  ];
}

// 5) Shortcode megjelenítés
function impactshop_shortcode_akciok($atts) {
  $a = shortcode_atts([
    'shop'         => '',
    'min_discount' => '10',   // minimum kedvezmény %
    'limit'        => '50',
    'sort'         => 'discount', // discount|price|title
    'amb'          => '',     // fix amb, ha akarsz
    'fillout_url'  => 'https://fillout.com/IDE-AZ-URL' // A TE Fillout űrlap URL-ed
  ], $atts);

  if (!$a['shop']) return '<div>Hiányzó shop paraméter.</div>';

  $cfgs = impactshop_feeds_config();
  $key = strtoupper($a['shop']);
  if (!isset($cfgs[$key])) return '<div>Ismeretlen shop: '.esc_html($a['shop']).'</div>';

  $items_raw = impactshop_fetch_xml_items($key);
  $map = $cfgs[$key]['field_map'];

  $items = [];
  foreach ($items_raw as $node) {
    $it = impactshop_normalize_item($node, $map);

    // Akció szűrés: vagy van valós kedvezmény, vagy promo flag
    $is_promo = ($it['discount'] >= intval($a['min_discount'])) || (!empty($it['promo']));
    if ($is_promo && $it['price'] > 0 && $it['title']) {
      $items[] = $it;
    }
  }

  // Rendezés
  usort($items, function($x, $y) use ($a) {
    switch ($a['sort']) {
      case 'price':    return $x['price']    <=> $y['price'];
      case 'title':    return strcasecmp($x['title'], $y['title']);
      default:         return $y['discount'] <=> $x['discount']; // alap: nagyobb kedvezmény előre
    }
  });

  // Limit
  $items = array_slice($items, 0, intval($a['limit']));

  // Param öröklés a jelenlegi URL-ből
  $d1  = impactshop_get_query('d1');
  $amb = $a['amb'] ?: impactshop_get_query('amb');
  $src = impactshop_get_query('src') ?: 'impactshop';

  ob_start();
  ?>
  <div class="impactshop-deals">
    <div class="impactshop-deals-bar" style="margin:8px 0;">
      <small>Találatok: <?php echo count($items); ?> • Minimum kedvezmény: <?php echo intval($a['min_discount']); ?>%</small>
    </div>
    <div class="impactshop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
      <?php foreach ($items as $it): 
        $title   = esc_html($it['title']);
        $img     = esc_url($it['image']);
        $price   = number_format($it['price'], 0, ',', ' ');
        $old     = $it['old_price'] > 0 ? number_format($it['old_price'], 0, ',', ' ') : '';
        $disc    = $it['discount'] > 0 ? $it['discount'].'%' : '';
        $curr    = esc_html($it['currency'] ?: 'Ft');
        $produrl = esc_url($it['url']);
        // CTA link logika:
        // – Ha VAN d1 a queryben: egyből a /go/{shop} felé megyünk és visszük a prod URL-t is (prod=…).
        // – Ha NINCS d1: Fillout űrlap, előtöltve: shop, amb, product_url, product_name; on submit redirect: /go/{shop}?d1=@ngo_code&amb=@amb&src=impactshop&prod=@product_url
        if ($d1) {
          $cta = add_query_arg([
            'd1'  => $d1,
            'amb' => $amb,
            'src' => $src,
            'prod'=> rawurlencode($produrl)
          ], home_url('/go/'. rawurlencode($a['shop'])));
        } else {
          $prefill = add_query_arg([
            'shop'         => $a['shop'],
            'amb'          => $amb,
            'product_url'  => $produrl,
            'product_name' => $title
          ], $a['fillout_url']);
          $cta = $prefill;
        }
      ?>
        <div class="impactshop-card" style="border:1px solid #eee;border-radius:14px;padding:12px;display:flex;flex-direction:column;gap:8px;">
          <div style="aspect-ratio: 4/3;overflow:hidden;border-radius:10px;background:#fafafa;display:flex;align-items:center;justify-content:center;">
            <?php if ($img): ?>
              <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" style="max-width:100%;max-height:100%;object-fit:contain;">
            <?php else: ?>
              <span style="font-size:12px;color:#999;">Nincs kép</span>
            <?php endif; ?>
          </div>
          <div style="min-height:3.2em;font-weight:600;line-height:1.2;"><?php echo $title; ?></div>
          <div style="display:flex;gap:8px;align-items:baseline;">
            <div style="font-size:18px;font-weight:700;"><?php echo $price.' '.$curr; ?></div>
            <?php if ($old): ?>
              <div style="text-decoration:line-through;color:#999;"><?php echo $old.' '.$curr; ?></div>
            <?php endif; ?>
            <?php if ($disc): ?>
              <div style="margin-left:auto;background:#e6ffed;color:#137333;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700;"><?php echo $disc; ?></div>
            <?php endif; ?>
          </div>
          <a href="<?php echo esc_url($cta); ?>" rel="nofollow sponsored" style="text-align:center;border-radius:10px;padding:10px 12px;background:#111;color:#fff;text-decoration:none;font-weight:700;">
            Támogató vásárlás
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('impactshop_akciok', 'impactshop_shortcode_akciok');

// 6) (OPCIONÁLIS) REST végpont CSV-hez – TablePress URL importhoz
add_action('rest_api_init', function() {
  register_rest_route('impactshop/v1', '/deals', [
    'methods'  => 'GET',
    'callback' => function($req) {
      $shop = strtoupper(sanitize_text_field($req->get_param('shop') ?: ''));
      $min  = intval($req->get_param('min_discount') ?: 10);
      $limit= intval($req->get_param('limit') ?: 200);

      if (!$shop) return new WP_Error('no_shop', 'Hiányzik a shop paraméter.', ['status'=>400]);

      $cfgs = impactshop_feeds_config();
      if (!isset($cfgs[$shop])) return new WP_Error('bad_shop', 'Ismeretlen shop.', ['status'=>404]);

      $items_raw = impactshop_fetch_xml_items($shop);
      $map = $cfgs[$shop]['field_map'];

      $rows = [];
      foreach ($items_raw as $node) {
        $it = impactshop_normalize_item($node, $map);
        $is_promo = ($it['discount'] >= $min) || (!empty($it['promo']));
        if ($is_promo && $it['price'] > 0 && $it['title']) {
          $rows[] = $it;
        }
      }
      // top kedvezmény szerint
      usort($rows, fn($x,$y)=> $y['discount'] <=> $x['discount']);
      $rows = array_slice($rows, 0, $limit);

      // CSV kimenet
      $fh = fopen('php://temp', 'w');
      fputcsv($fh, ['id','title','price','old_price','discount','currency','url','image','brand','category']);
      foreach ($rows as $r) {
        fputcsv($fh, [$r['id'],$r['title'],$r['price'],$r['old_price'],$r['discount'],$r['currency'],$r['url'],$r['image'],$r['brand'],$r['category']]);
      }
      rewind($fh);
      $csv = stream_get_contents($fh);
      fclose($fh);

      return new WP_REST_Response($csv, 200, ['Content-Type'=>'text/csv; charset=UTF-8']);
    },
    'permission_callback' => '__return_true'
  ]);
});
Mit hova írj pontosan
Feed URL
A kódban a impactshop_feeds_config() függvényben töltsd ki a xml_url mezőt(ket) a valós feed link(ek)kel. Ha az XML mezőnevei eltérnek, igazítsd a field_map kulcsokat (title/price/old_price/url/image…).
Fillout űrlap URL
Cseréld a shortcode attribútumban vagy a snippetben a fillout_url-t a tényleges űrlapod linkjére. A Fillout-on legyenek rejtett mezők:
shop (prefillből jön),
amb (prefillből jön),
ngo_code (számított mező – a korábbi mapping szerint),
opcionális: product_url, product_name (prefillből jönnek, hogy visszaadjuk a /go felé).
A Fillout „On submit redirect” beállítása legyen:
/go/@shop?d1=@ngo_code&amb=@amb&src=impactshop&prod=@product_url&utm_source=sharity&utm_medium=impactshop&utm_campaign=@shop
(ha nincs product_url, hagyd ki a &prod=@product_url részt)
Redirection szabály – /go/{shop}
A meglévő /go/{shop} szabályodat egészítsd ki úgy, hogy a kapott prod paramétert is átadja a Dognet deeplink sablonnak. A cél URL a Te hálózati sablonodtól függ (Dognet 2.0-ban program/affiliate azonosítók). Mintának:
Forrás URL (Regex):
/go/([a-z0-9\-\_]+)$
Lekérés paraméterek átengedése: Igen
Cél URL (PÉLDA! cseréld a saját Dognet deeplink sablonodra):
https://dognetpartner.example/deeplink?program=ARUKERESO&a_aid=YOUR_ID&data1={d1}&data2={amb}&url={prod}
A Redirection képes a query string paramétereket (d1, amb, prod, src, utm_*) változtatás nélkül továbbadni; a konkrét Dognet deeplink paraméterezést a legutóbbi kutatásod szerint töltsd ki (program ID, affiliate ID mezők).
Shortcode beillesztése oldalra
Példa:
[impactshop_akciok shop="ARUKERESO" min_discount="20" limit="60" sort="discount" amb="influencer123" fillout_url="https://fillout.com/TE-URL"]
Ha a látogató URL-jében ?d1=<ngo_kod> szerepel, a kártya gombja közvetlenül a /go/ARUKERESO linkre megy és viszi a d1,amb,src,prod paramokat.
Ha nincs d1, a gomb a Filloutra visz NGO-választásra, majd visszairányít a /go-ra a megfelelő paraméterekkel.
TablePress (opcionális, élő CSV import)
A snippet ad egy CSV REST végpontot:
/wp-json/impactshop/v1/deals?shop=ARUKERESO&min_discount=20&limit=200
Ezt be tudod állítani a TablePressben „Import from URL” forrásként, így automatán frissül a táblázat a cache időn belül.
Finomhangolási tippek
Ha a feedben nincs old_price, de van „akció” flag, a kód így is beengedi – ilyenkor a kedvezmény százalék üres marad, csak a flag miatt jelenik meg.
A cache időt (30 perc) a set_transient sorban tudod módosítani.
Ha több shopot akarsz egy oldalon, hívd meg a shortcode-ot több blokkban eltérő shop értékekkel.
Ha a Dognet deeplink más paraméterneveket használ az adatok átvitelére, a Redirection cél URL sablont ennek megfelelően írd át; a data1-be menjen az NGO kód.
Ha elküldöd az első shop valós XML feed URL-jét (és ha tudod, a mezőnevek listáját), be is drótozom konkrétan a field_map-et – onnantól már csak ki kell tenni az oldalon a shortcode-ot, és kész az akciós polc.
Ezt mondtad:
Szeretném ezt megjeleníteni az impact shop oldalon, hogy bannerek legyenek scrollozva több helyen is a webshop logók között. ez megoldható? Ezenkívül a különféle webshopokat szeretném kategorizálni, hogy azon belül tudjon választani a user.
A ChatGPT ezt mondta:

Gondolkozás
Impact Shop – Banners & Kategóriák (wpcode Snippet)
· php
<?php
/**
 * Impact Shop – Logó scroller + kategória szerinti választó (shortcode-ok)
 *
 * Shortcode-ok:
 *  1) [impactshop_scroller category="" inject_every="5" speed="30"]
 *     - Vízszintes végtelen logó-beltéri "banner scroller" (logók közé beszúrja a bannereket)
 *     - Paraméterek:
 *         category: csak egy adott kategória logói (pl. "Elektronika"); üres = összes
 *         inject_every: hányadik logó után kerüljön be egy banner (szám)
 *         speed: képpont/másodperc (animáció sebessége)
 *
 *  2) [impactshop_catalog show_tabs="1" search="1" per_page="100"]
 *     - Kategóriaválasztó + kereső + logórács
 *     - d1 jelenlétét figyeli: ha van d1 a URL-ben → /go/{shop}; ha nincs → Fillout
 *
 * Használat: WordPress → WPCode → új PHP snippet → tartalom bemásolása → Active.
 *
 * Megjegyzés: az $shops és $banners tömböket töltsd ki a valós adatokkal (logó, link, kategória stb.).
 */

// ===== 0) Beállítások – TÖLTSD KI SAJÁT ADATOKKAL =====
function impactshop_settings() {
  return [
    'fillout_url' => 'https://fillout.com/IDE-AZ-URL',
  ];
}

function impactshop_shops_registry() {
  // Példa adatstruktúra – cseréld a valósra.
  // key: egyedi azonosító (slug); name: megjelenített név; category: fő kategória; logo: kép URL; shop_slug: a /go/{shop}-hoz használt név
  return [
    [ 'key'=>'arukereso',   'name'=>'Árukereső',     'category'=>'Általános',   'logo'=>'https://via.placeholder.com/220x120?text=Arukereso', 'shop_slug'=>'arukereso' ],
    [ 'key'=>'visionexpress','name'=>'Vision Express','category'=>'Divat & Egészség','logo'=>'https://via.placeholder.com/220x120?text=Vision+Express', 'shop_slug'=>'visionexpress' ],
    [ 'key'=>'regiojatek',   'name'=>'REGIO Játék',   'category'=>'Játék & Család','logo'=>'https://via.placeholder.com/220x120?text=REGIO', 'shop_slug'=>'regiojatek' ],
    [ 'key'=>'sparkl',       'name'=>'Sparkl',        'category'=>'Szépség',     'logo'=>'https://via.placeholder.com/220x120?text=Sparkl', 'shop_slug'=>'sparkl' ],
Igen, megoldható – és most rögtön adtam is egy kész WPCode snippetet a vászonra, ami két shortcode-ot ad:
[impactshop_scroller category="" inject_every="5" speed="30"] – végtelenített, vízszintesen scrollozó sáv. A webshop-logók közé automatikusan beszúr bannereket (minden N. logó után).
[impactshop_catalog show_tabs="1" search="1" per_page="200"] – kategóriaválasztó tabokkal + keresőmezővel és logóráccsal. A kattintható logók d1 jelenlétét figyelik: ha az URL-ben van d1, közvetlenül a /go/{shop} linkre mennek; ha nincs, akkor a Fillout űrlapra visznek előtöltve (shop, amb).
Mit tegyél most, lépésről lépésre
WordPress → WPCode → Add Snippet → PHP Snippet → másold be a vásznon lévő kódot → Active.
A kód elején töltsd ki:
fillout_url – a saját Fillout űrlapod linkje (rejtett mezők: shop, amb, és a számított ngo_code, ahogy eddig).
impactshop_shops_registry() – ide vidd fel a valós partnereket: name, category, logo (kép URL), shop_slug (a /go/{shop}-hoz).
impactshop_banner_registry() – ide vidd fel a bannereket: img, href, label.
Tedd ki az Impact Shop oldalra:
Egy vagy több scroller blokkot (akár a logógrid fölé/közé/alá):
[impactshop_scroller category="Divat & Egészség" inject_every="4" speed="28"]
category üresen hagyva az összes partnert görgeti.
inject_every szabja meg, hányadik logó után jöjjön egy banner.
speed px/s – nagyobb szám = gyorsabb görgés.
Alá a katalógust tabokkal és keresővel:
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
Hogyan viselkedik a linkelés (Fillout ↔ /go)
Ha a látogató URL-jében van ?d1=<ngo_kod>, a logók közvetlenül a /go/{shop} URL-re mutatnak (d1, amb, src továbbadva).
Ha nincs d1, a logók a Filloutra visznek előtöltve shop + amb mezőkkel; az űrlap On submit redirect továbbra is a korábban beállított:
/go/@shop?d1=@ngo_code&amb=@amb&src=impactshop&utm_source=sharity&utm_medium=impactshop&utm_campaign=@shop
Testreszabás gyorsan
Bannerek több helyen: egyszerűen szúrj be több [impactshop_scroller] blokkot az oldalon különböző category és inject_every értékekkel.
Kategóriák: a impactshop_shops_registry() tömb category mezői alapján automatikusan jelennek meg a tabok.
Kereső: a katalógus shortcode-ban search="1" – név alapján azonnal szűr.
Ha átdobod 2–3 valós logóképet, kategórianeveket és a Fillout URL-t, kitöltöm a snippet tetejét konkrét adatokkal, hogy elég legyen beilleszteni az oldalra és már pörögjenek is a sávok.

Gondolkozás
Rendben! A bannerek görgetését és a webshopok kategorizálását a WordPress-ben hamarosan megvalósítom. Először ellenőrzöm, hogy szükséges-e további információkat beszerezni, majd a kódot beépítem és beállítom az oldal sablonjába. Köszönöm a türelmét!
Ezt mondtad:
Continue
A ChatGPT ezt mondta:

Gondolkozás
Igen, tudjuk továbbvinni – a mostani setup már alkalmas rá, hogy több helyen, a logók közé fűzve forogjanak a bannerek, és a shopok kategóriák szerint választhatók legyenek. Gyors terv + konkrét beállítási minták:
Hol és hogyan forogjanak a bannerek?
Az általad kapott [impactshop_scroller] shortcode logók közé automatikusan beszúr bannereket. Ugyanazon az oldalon több scroller is lehet – akár kategóriánként eltérő bannerekkel.
Példák oldal-elrendezésre
Hero alatt, összkategória scroller (gyors márka-bemutató):
[impactshop_scroller category="" inject_every="5" speed="28"]
Szekciónként külön scroller (tematikus blokkok):
### Divat & Egészség
[impactshop_scroller category="Divat & Egészség" inject_every="4" speed="24"]

### Játék & Család
[impactshop_scroller category="Játék & Család" inject_every="6" speed="26"]
Katalógus fölött és alatt is (egy bevezető + egy záró sáv):
[impactshop_scroller inject_every="5" speed="30"]
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
[impactshop_scroller inject_every="7" speed="22"]
Tippek:
inject_every: minél kisebb, annál sűrűbben jön banner a logók közé.
speed: 20–32 közötti értékek általában kényelmesek.
Kategorizálás – felhasználói élmény
A kategóriázást már kezeli az [impactshop_catalog]:
Tabok automatikusan a impactshop_shops_registry() tömb category mezőiből jönnek.
Beépített kereső név alapján szűr (szóeleji/ részszavas egyezés is megy).
Link-logika követi a d1 jelenlétét: ha van d1 az URL-ben → közvetlen /go/{shop}; ha nincs → Fillout előtöltéssel, majd vissza /go-ra.
Gyakorlati tanács:
Tarts max. 6–8 felső kategóriát (pl. „Általános”, „Divat & Egészség”, „Játék & Család”, „Elektronika”, „Otthon”, „Étel & Ital”). Ha egy kategória 40+ logót tartalmaz, érdemes alcímekkel törni a katalógus-blokkot (két külön [impactshop_catalog], eltérő shops_registry részszűréssel – vagy a registry-t 2–3 kategóriára bontani).
Bannek kezelése
A bannerek listája a snippetben a impactshop_banner_registry() függvényben van. Ide tehetsz:
kampány-bannert (pl. „Szeptemberi jótékonysági kihívás”) → belső landingre mutat,
oktató/eligazító bannert (pl. „Hogyan működik a támogatás?”) → magyarázó oldalra,
eseti promót egy partnerhez (pl. „-15% kód XYN-nal”) → releváns aloldalra.
Hasznos minták:
Készíts „általános” banner(eke)t (minden scroller felhasználja),
és kategória-specifikusakat is (pl. Divat scrollerben Divat-bannerek). Ezt a jelenlegi kódban úgy tudod elérni, ha készítesz egy második scroller shortocode-ot külön bannertömbbel, vagy bővítjük a registry-t kategória szerinti banner mappinggel. Szívesen beépítem.
Teljesítmény & UX finomhangolás (gyors ellenőrzőlista)
Képek: használj 220×120 körüli, tömörített PNG/WebP logókat, fehér/átlátszó háttérrel.
Lazy-load: a snippet képei img-ekkel mennek – érdemes loading="lazy"-t és decoding="async"-ot tenni rájuk (ha kéred, beépítem a kódba).
Hover-pause: a scroller animációt be tudjuk állítani úgy, hogy egérrel fölé állva megálljon.
Érintés: a folyamatos scroll mellett jól működik, de hozzáadhatunk „swipe to pause/drag” élményt is (kis JS-sel).
Kontraszt & fókusz: a kártyákon és tabokon már van fókusz-kezelés, de ARIA-labellel tovább finomíthatjuk.
Következő konkrét lépések
Töltsd ki a registry-ket (vagy küldd át nekem a listát és a logókat):
Webshop neve, kategória, logó URL, shop_slug (ami a /go/{shop}-hoz kell).
Banner(ek): 320×120 vagy 640×240 (retina), cél-URL és címke.
Illeszd be a shortcode-okat az oldal megfelelő pontjain (hero alá, katalógus fölé/közé/alá).
Fillout URL csere a snippet tetején; ellenőrizd, hogy az űrlapon a rejtett mezők és a redirect a korábbi megegyezés szerint állnak.
Gyors vizuális teszt: desktop (≥1280 px), tablet (~768 px), mobil (~375 px). Figyeld, hogy a csempék szépen törnek, és az animáció folyamatos.
Ha szeretnéd, a következő körben:
beépítem a lazy-load + hover-pause apróságokat,
hozzáadok URL-paraméter támogatást a katalógushoz (pl. ?cat=Divat%20%26%20Egészség&q=vision előre szűri a tabot és a keresőt),
és megcsinálom a kategória→banner mappinget (így a Divat scroller automatikusan divatos bannereket kap).
Küldd át a tényleges partnerlistát (név, kategória, logo URL, shop_slug) és 2–4 bannerképet/céloldalt, és ráégetem a kódra, hogy azonnal éleszthető legyen.

Gondolkozás
Elkészült az Impact Shop görgetősáv és kategóriás katalógus bővített PHP-snippetje. A kód főbb jellemzői:
Végtelenített scroller: injektálható bannerekkel, amelyek hover‑re megállítják az animációt. A képek lusta betöltést (loading="lazy") és aszinkron dekódolást (decoding="async") használnak a teljesítmény javítására.
Katalógus tabokkal és keresőmezővel: a shopok kategóriák szerint szűrhetők, a kereső pedig valós időben szűri a neveket. A logókra kattintva a d1 jelenlététől függően vagy közvetlenül a /go/{shop} linkre, vagy a Fillout-űrlapra irányítja a felhasználót.
Könnyen testreszabható: csak a impactshop_shops_registry() és impactshop_banner_registry() tömböket kell feltöltened a valódi partnerek adataival, és megadni a saját Fillout URL-jeidet. A bannerek ciklikusan ismétlődnek, az inject_every paraméterrel szabályozhatod, hány logó után jelenjen meg egy banner.
Hozzáférési vezérlés: GET-paraméterek (d1, amb, src) öröklése biztosított, a linklogika a korábbi megbeszélés szerint viselkedik.
Kód hozzáadása: Másold a fájl tartalmát a WPCode bővítmény PHP Snippet szekciójába, és aktiváld. A shortcode-okat [impactshop_scroller] és [impactshop_catalog] formában helyezd el az oldalakon.
A kész PHP-snippetet innen tudod letölteni és beilleszteni a WordPressedbe: impactshop_scroller_enhanced.php
Ha további finomhangolásra vagy a registry-k kitöltésére van szükség, jelezd bátran!

Ezt mondtad:
Rendben akkor most kérem lépésről lépésre úgy mintha egy gyereknek magyaráznád
A ChatGPT ezt mondta:

Gondolkozás
Szuper! Lépésről lépésre, egyszerűen, „gyerekbarát” módon:
Lépj be a WordPress-edbe
Nyisd meg az admin felületet (ahol a bejegyzéseket és oldalakat szoktad szerkeszteni).
Két bővítmény kell
WPCode: ebbe tesszük a kódot.
Redirection: ez viszi a látogatókat a /go/{shop} linkre.
Ha már fenn vannak, hagyd így. Ha nem, telepítsd és kapcsold be őket.
Nyisd meg a WPCode-ot
Menüben: WPCode → Add Snippet → PHP Snippet.
Illeszd be a kódot
A beszélgetésünkben a „vásznon” (canvas) már ott a kész snippet. Másold be egészben a kódszerkesztőbe.
Kapcsold be
A snippetet állítsd Active-ra, majd mentsd.
Mondd meg a kódnak „hova menjen” (Fillout)
A kód tetején van egy sor: fillout_url. Cseréld a saját Fillout űrlapod linkjére.
Az űrlapon legyenek rejtett mezők: shop, amb, és egy számított ngo_code.
Az űrlap beküldés utáni átirányítás így nézzen ki:
/go/@shop?d1=@ngo_code&amb=@amb&src=impactshop
Add meg a boltokat (kis lista a kódban)
A impactshop_shops_registry() részben soronként felveszed a partnereket:
name: bolt neve
category: melyik kategóriába tartozik (pl. „Divat & Egészség”)
logo: a logó képe (URL)
shop_slug: ez lesz a /go/{shop}-ban (pl. visionexpress)
Minél több bolt van itt, annál több logó jelenik meg.
Add meg a bannereket (másik kis lista)
A impactshop_banner_registry() részben soronként felveszed a bannereket:
img: a banner képe (URL)
href: hova vigyen a kattintás (pl. egy kampányoldalra)
label: egy rövid címke (csak infó).
Nyisd meg az Impact Shop oldalt
Menü: Oldalak → Impact Shop (vagy hozd létre, ha még nincs).
Tedd be a „forgó sávot” (logók + bannerek)
Illeszd be egy blokkba ezt a rövid kódot (shortcode):
[impactshop_scroller inject_every="5" speed="28"]
– Az inject_every="5" azt jelenti: minden 5. logó után jön egy banner.
– A speed="28" azt jelenti: milyen gyorsan forog (nagyobb szám = gyorsabb).
Ha szeretnél külön sávot egy kategóriának
Példa: csak „Divat & Egészség” kategóriát görgesse:
[impactshop_scroller category="Divat & Egészség" inject_every="4" speed="24"]
Tedd be a „választó katalógust” (tabok + kereső)
Ez ad kategóriafüleket és keresőt a logókhoz:
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
Ments, és nézd meg az oldalt
Kattints Előnézet vagy Frissítés, majd nézd meg a nyilvános oldalt.
Teszteld a kétféle kattintást
d1 NÉLKÜL: csak simán nyisd meg az Impact Shop oldalt → logóra kattintva Fillout nyílik meg (előre kitöltve).
d1-gyel: az oldal címe után tedd ezt: ?d1=TESZT és töltsd be újra. Most logóra kattintva közvetlenül a /go/{shop} linkre megy.
Ha túl gyors/lassú vagy sok a banner
Állíts a scrolleren: speed="22–32" között jó szokott lenni.
Ritkítsd vagy sűrítsd a bannereket az inject_every számmal (pl. 7 ritkább, 3 sűrűbb).
Ha nem látszanak a tabok
Ellenőrizd, hogy a boltoknál biztosan van category megadva (a kódbeli listában).
Ha a /go/ nem működik
Nyisd meg a Redirection-t, és nézd meg, hogy él-e a szabály a /go/{shop} linkekre (engedje át a d1, amb, src paramétereket).
Képek legyenek „könnyűek”
Használj egyszerű, világos hátterű WebP/PNG logókat. A kód már „lustán” tölti be őket (gyorsabb oldal).
Kész is!
Most már forognak a logók, közéjük csúsznak a bannerek, és a felhasználó kategóriákon belül is tud böngészni.
Hasznos minták, amiket bármikor beilleszthetsz:
Összkategóriás scroller a lap tetején:
[impactshop_scroller inject_every="5" speed="28"]
Tematikus scroller egy szekcióban:
[impactshop_scroller category="Játék & Család" inject_every="6" speed="26"]
Katalógus (tabok + kereső):
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
Ha készen áll a partnerlista (név, kategória, logó URL, shop_slug) és 2–4 bannerkép, csak töltsd be a kód tetején lévő listákba, és minden a helyére kattint.

Gondolkozás
Íme, hogyan teheted fel a kódot lépésről lépésre, egészen egyszerűen, mintha egy gyereknek magyaráznád:
Nyisd meg a WordPress admin felületét – ez az a hely, ahol a blogbejegyzéseidet és az oldalakat kezeled.
Telepítsd a WPCode bővítményt – ha még nincs fenn, menj a „Bővítmények → Új hozzáadása” menüpontra, és keress rá, majd telepítsd és aktiváld.
Nyisd meg a WPCode oldalt – a bal oldali menüben lesz egy „Code Snippets” pont. Kattints rá, majd válaszd az „Add Snippet” opciót. Itt a WPCode útmutatása szerint a „Add Your Custom Code (New Snippet)” gombra kell kattintani
wpcode.com
.
Válaszd ki a „PHP Snippet” típust – a következő képernyőn kattints a „PHP Snippet” opcióra
wpcode.com
. Adj valamilyen címkét (például „Impact Shop Görgető”).
Másold be a kódot – nyisd meg a tőlem kapott fájlt ( impactshop_scroller_enhanced.php) a számítógépeden, másold ki az egész kódot, majd illeszd be a WPCode szerkesztőbe. Ez a kód hozza létre a görgetős logó- és banner-sávot, illetve a kategória-rácsot.
Mentés és aktiválás – a szerkesztő tetején kattints a „Save Snippet” gombra
wpcode.com
, majd állítsd „Active”-ra a snippetet, hogy élő legyen. Így most már fut a kód.
Illeszd a shortocode-okat az oldaladra – menj vissza az oldal- vagy bejegyzésszerkesztőbe (például az „Impact Shop” oldal), és írd be a megfelelő helyre:
a görgetősávhoz:
[impactshop_scroller inject_every="5" speed="28"]
a kategóriás logórácshoz keresővel:
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
Teszteld az oldalt – nézd meg az oldal előnézetét: a logók szépen forogjanak, köztük a bannerek, és a kategóriák, kereső mező is megjelenjen. Ha finomítani szeretnél, a shortocode paraméterein (pl. inject_every, speed) vagy a kódban lévő listákon (partnerek, bannerek) tudsz módosítani.
Ezzel készen is vagy! Így lépésről lépésre, egyszerűen állítottad be az Impact Shop oldalad görgetős, kategóriázott logóit és bannerjeit.


Források
Ezt mondtad:
A kód tetején van egy sor: `fillout_url`. Cseréld a saját Fillout űrlapod linkjére. Az űrlapon legyenek rejtett mezők: `shop`, `amb`, és egy számított `ngo_code`. Az űrlap beküldés utáni átirányítás így nézzen ki: `/go/@shop?d1=@ngo_code&amb=@amb&src=impactshop`
Ez ugyanaz a Fillout form amit már beállítottunk az affiliate webshopokhoz? Mit jelent az: Mondd meg a kódnak „hova menjen” (Fillout)
A ChatGPT ezt mondta:
Igen – ugyanarra a Fillout-űrlapra gondolunk, amit már az affiliate webshopokhoz beállítottál. Annak kell fogadnia a „shop”, „amb” rejtett mezőket és kiszámítania az „ngo_code”-ot, majd sikeres beküldés után a megfelelő /go/… URL-re irányítani a látogatót.
A „mondd meg a kódnak, hova menjen” arra utal, hogy a kódban (a snippet elején) van egy fillout_url változó. Ide kell beillesztened annak az űrlapnak az URL-jét, amely a látogatót végigvezeti az NGO-választáson. Ha a látogató URL-jében nincs d1 paraméter, akkor a logókra kattintva a kód erre az űrlapra irányítja őt. Ha viszont már van d1 paraméter (tehát korábban kitöltötte), akkor a kód a /go/{shop} átirányításra küldi tovább.

Ezt mondtad:
**logo**: a logó képe (URL)
hogy tudok logó képhez url-t gyártani?
A ChatGPT ezt mondta:
Nagyon jó kérdés – a „logo” mezőnél tényleg egy URL-t kell megadni, ami egy képfájlra mutat (pl. PNG vagy WebP). Ezt többféleképp tudod előállítani:
1) WordPress médiatárból
Menj a WordPress adminban a Média → Új hozzáadás menüpontra.
Töltsd fel a logó képfájlt (pl. visionexpress-logo.png).
Miután feltöltődött, kattints rá → a jobb oldalon lesz egy Fájl URL mező.
Példa: https://sajatdomain.hu/wp-content/uploads/2025/09/visionexpress-logo.png
Ezt az URL-t másold ki, és illeszd a snippet logo mezőjéhez.
2) Külső tárhely vagy CDN
Ha nem akarod a WordPress médiatárát terhelni:
Töltsd fel a képeket egy megbízható tárhelyre (pl. Google Drive, Dropbox, vagy bármely CDN, ami nyilvánosan elérhető képlinket ad).
Fontos: a link közvetlenül a képre mutasson, ne egy megosztó oldalra. Tehát az URL végződjön .png vagy .webp kiterjesztéssel.
3) Ideiglenesen
Ha csak tesztelni akarsz, használhatsz placeholder képeket. Például:
https://via.placeholder.com/220x120?text=Vision+Express
Ez egy automatikusan generált kép „Vision Express” felirattal.
Lényeg: a logo mezőhöz mindig egy teljes URL kell, ami közvetlenül a képfájlra mutat.
👉 Szeretnéd, hogy mutassak egy konkrét példát a snippetben, pl. hogyan nézne ki a Vision Express logó URL-lel feltöltve a médiatárból?

Ezt mondtad:
7. és 8. között mi a különbség? vagy képet teszek fel egy shop jól, vagy bannert
A ChatGPT ezt mondta:
Pontosan 🙂. A snippetben két külön „listád” van, és más a szerepük:
7. lépés – impactshop_shops_registry()
Itt sorolod fel a partnereket / webshopokat:

name: bolt neve (pl. Vision Express)
category: hova tartozik (pl. „Divat & Egészség”)
logo: ide jön a bolt logója → kis téglalap kép, amit a felhasználó azonnal felismer
shop_slug: ez a bolt rövid azonosítója, amit a /go/{shop} linkhez használunk
Ez tehát a bolt ikonja, amire kattintva a látogató elindulhat a vásárláshoz.
8. lépés – impactshop_banner_registry()
Ez egy teljes szélességű, figyelemfelkeltő reklám kép (banner):
img: maga a banner kép (pl. 320×120 px vagy nagyobb)
href: hova vigyen a kattintás (pl. kampányoldal, tájékoztató, promóció)
label: rövid címke, belső azonosításhoz
Ezeket a kód a logók közé szúrja be: mondjuk 5 logó után 1 banner.
Így nem keveredik: a logó = bolt azonosító ikon, a banner = üzenet / kampány / hirdetés.
👉 Röviden:
Logo = bolt saját képe (mindig van).
Banner = extra reklám kép, amit csak akkor adsz hozzá, ha szeretnél promót a logók közé.
Szeretnéd, hogy készítsek egy konkrét példát egy registry-részletre, ahol pl. a Vision Express logó szerepel a shops_registry-ben, és mellé van egy banner a banner_registry-ben, hogy lásd pontosan a különbséget?
Ezt mondtad:
tehát a banner az akciós ajánlat lesz?
A ChatGPT ezt mondta:
Pontosan, jól látod 🙂
A logó mindig maga a bolt jele, amire kattintva a user eljut az adott webshopba (vagy először a Fillout-űrlapra, ha még nincs d1 kódja). Ez állandó, nem változik, csak a bolt „névtáblája”.
A banner pedig üzenetet vagy akciót hirdet. Ez lehet:
egy szezonális akció („Őszi leárazás -20%”),
egy kampány („Támogasd a sulit vásárlásoddal”),
egy általános promó („Hogyan működik a Sharity vásárlás?”).
A snippet úgy van kitalálva, hogy a logók közé időnként beszúrjon egy-egy ilyen bannert, így a felhasználó görgetés közben nem csak boltokat lát, hanem akciós ajánlatokat is.
👉 Tehát:
Logo = fix bolt
Banner = dinamikus üzenet / akciós ajánlat, amit bármikor cserélhetsz, és akár többféle is foroghat egymás után.
Szeretnéd, hogy készítsek neked egy mintát, ahol pl. a „Vision Express” logó megy a shoplistába, és mellé felveszünk egy banner példát „Őszi akció -20% Vision Express” szöveggel?
Ezt mondtad:
bannerek vannak a dognetnél is, ha a html kódot beírom, akkor az már linket is fog tartalmazni: <a href="https://go.dognet.com/?chid=KVirfJde&url=https%3A%2F%2Fwww.decathlon.hu%2F" rel="nofollow" target="_blank">
    <img src="https://api.app.dognet.com/file-manager/thumbnails/43e/43ed55e44c44a6efea5e73ecab33fed1b583498d.jpg?signature=d55eea988497381f2e842644a8d3f081174726f29cc209adb710392fb5953ea5" alt="Decathlon.hu 728x90" />
  </a>  Kérdés: én gyártsak inkább kép bannert, amit beteszek a WP Médiaába és onnan veszem az url-t?
A ChatGPT ezt mondta:
Nagyon jó, hogy ezt felhoztad! Nézzük meg, mi a különbség és mit érdemes csinálnod:
1. Dognet ad kész HTML bannert
Amikor a Dognetnél látsz egy ilyen kódot:
<a href="https://go.dognet.com/...">
  <img src="https://api.app.dognet.com/file-manager/thumbnails/43e/43ed55e4...jpg" alt="Decathlon.hu 728x90" />
</a>
ez már tartalmazza a teljes kattintható linket és a képet.
Ha ezt simán bemásolnád a snippetbe, az baj lenne, mert:
a kód már közvetlenül Dognetre mutat → kimarad a Fillout lépés, így nem tudsz NGO-t választani (d1 kód nem kerül átadásra);
nehezebb kezelni, mert HTML-t kell beágyaznod a PHP tömbbe.
2. A snippetünk logikája
A snippet úgy van felépítve, hogy:
img → a kép URL-je (csak a kép!)
href → hova menjen a kattintás (pl. /go/{shop}, vagy belső kampányoldal)
Így a kód átveszi a linkelést és hozzáadja a d1, amb, src paramétereket → vagyis az NGO kiválasztás működni fog.
3. Mit ajánlott csinálni?
A legjobb, ha külön választod a képet és a linket:
Kép: töltsd fel a Dognet banner képét a WordPress médiatárba. Így lesz egy tiszta kép-URL-ed (pl. https://sajatdomain.hu/wp-content/uploads/.../decathlon-banner.jpg).
Ez stabilabb, gyorsabb, és nem függ a Dognet CDN-jétől.
Link: a snippetben mindig /go/{shop} vagy a belső Fillout-URL legyen. Így biztos, hogy a támogatási logika (d1) is működni fog.
👉 Röviden:
A Dognet HTML kódját ne másold be egy az egyben.
Vedd ki belőle a kép URL-t (img src=...).
A képet töltsd fel a WordPress médiatárba, onnan használd a saját URL-ed.
A linket a snippet intézi (/go/{shop}), így a támogatás nyomkövetése biztosan működni fog.
Szeretnéd, hogy írjak egy konkrét mintát a banner_registry-be úgy, hogy pl. a Decathlon Dognet banneredet belerakjuk – tehát img a médiatárból, href a /go/decathlon?
Ezt mondtad:
Tedd be a „forgó sávot” (logók + bannerek)
ezt hogy kell? elementrob használok
A ChatGPT ezt mondta:
Értem 🙂 akkor nézzük meg konkrétan Elementorral hogy tudod betenni a „forgó sávot” (azaz a shortcode-ot, ami a logókat + bannereket görgeti):
1) Nyisd meg az oldalt Elementorban
WordPress admin → Oldalak → keresd meg az „Impact Shop” oldalt → Szerkesztés Elementorral.
2) Helyezd el, hova szeretnéd a sávot
Az Elementor bal oldali paneljében van egy kereső. Írd be: Shortcode.
Fogd meg az Shortcode widgetet, és húzd oda az oldal azon részére, ahol szeretnéd, hogy a sáv megjelenjen (pl. a hero kép alá, vagy egy szekció közepére).
3) Írd be a shortcode-ot
A bal oldali beállítás panelen van egy mező, ahova be tudod írni a shortcode-ot.
Például:
[impactshop_scroller inject_every="5" speed="28"]
Ez a görgetősáv logókkal + bannerekkel.
4) Ha kategóriás scroller kell
Csak bizonyos kategóriát akarsz pörgetni (pl. Divat & Egészség):
[impactshop_scroller category="Divat & Egészség" inject_every="4" speed="24"]
5) Ha logórácsot (katalógust) akarsz tabokkal
Ezt is ugyanígy:
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
6) Nézd meg előnézetben
Kattints a kis szem ikonra (Előnézet). Látnod kell a pörgő logókat, közöttük bannereket, vagy a kategóriafüles logórácsot.
👉 Elementorban tehát a trükk az, hogy Shortcode widgetet használsz, és beleírod a kódban megadott [impactshop_scroller] vagy [impactshop_catalog] parancsot.
Szeretnéd, hogy adjak egy konkrét javasolt oldal-elrendezést Elementorhoz (pl. felül egy scroller, középen a katalógus tabokkal, alul még egy scroller)?
Elementorban ez tényleg nagyon egyszerű, mert a kódot nem kézzel kell beírni a HTML-be, hanem a saját Shortcode elemét tudod használni. Lépésről lépésre:
Nyisd meg az oldalt Elementorban
– Oldalak → Impact Shop → „Szerkesztés Elementorral”.
Keress rá a „Shortcode” widgetre
– A bal oldali panelben a keresőbe írd be: Shortcode.
– Fogd meg, és húzd oda, ahová a forgó sávot szeretnéd (pl. a hero rész alá, egy új szekcióba).
Írd be a shortcode-ot
A bal oldali beállítás mezőbe másold be például:
[impactshop_scroller inject_every="5" speed="28"]
– Ez indítja a görgetős logó+banner sávot.
– Ha kategóriát akarsz (pl. Divat & Egészség):
[impactshop_scroller category="Divat & Egészség" inject_every="4" speed="24"]
– Ha a logórácsot szeretnéd tabokkal és keresővel:
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
Frissíts és nézd meg előnézetben
– Jobb alsó sarokban Frissítés.
– Szem ikon → Előnézet.
Ott már látnod kell a görgetősávot a logókkal és bannerekkel.
Így bármelyik Elementor szekcióba beteheted a „forgó sávot” vagy a katalógust.
Szeretnéd, hogy mutassak egy ajánlott oldal-elrendezést Elementorhoz (pl. felül egy scroller, középen katalógus tabokkal, alul megint egy scroller)?
Ezt mondtad:
Feltöltött kép
csak kód jelent meg az Alkalmazás gombra kattintás után is
A ChatGPT ezt mondta:
Látom a képernyőmentésen, hogy a shortcode maga ([impactshop_scroller inject_every="5" speed="28"]) szövegként jelenik meg, nem pedig a forgó sáv. Ez két dolgot szokott jelenteni:
A snippet kód nincs bekapcsolva a WPCode-ban
– WordPress admin → Code Snippets (WPCode) → keresd meg az általad létrehozott „Impact Shop Scroller” snippetet.
– Ellenőrizd, hogy Active-ra van állítva.
– Ha nincs, kapcsold be, majd mentsd el.
Rossz widgetbe került be a shortcode Elementorban
– Ne a „Szöveg” vagy „HTML” widgetbe írd, hanem kifejezetten a Shortcode widgetbe.
– Ha „Szöveg” widgetben van, akkor tényleg csak szövegként jelenik meg.
– Tedd be újra: bal panel → keresés: Shortcode → húzd be → írd be a kódot.
👉 Ellenőrző lista:
WPCode snippet aktív?
A kód Elementorban Shortcode widgetbe került, nem szövegmezőbe?
Ha mindkettő rendben van, a scrollernek már meg kell jelennie a frontenden.
Szeretnéd, hogy mutassam képernyőképekkel, pontosan hol találod a WPCode snippet bekapcsoló gombját?

Ezt mondtad:
terrort ír ki a nippet, valószínű elgépeltem valamit, keresd meg:  
A ChatGPT ezt mondta:
Értem 🙂 Ha a WPCode snippet „
Ezt mondtad:
/**
 * Impact Shop – Logó scroller és katalógus kategóriákkal
 *
 * Használat:
 *  - [impactshop_scroller category="" inject_every="5" speed="30"] görgetősáv, amely a partnerek logói közé bannereket fűz.
 *  - [impactshop_catalog show_tabs="1" search="1" per_page="200"] logórács kategóriaválasztó tabokkal és keresővel.
 *
 * Megjegyzés: töltse ki a impactshop_shops_registry() és impactshop_banner_registry() tömböket a valós partnerek adataival.
 * A kitöltendő mezők: név, kategória, logó URL, shop_slug (ez a /go/{shop} endpointodhoz kell), illetve banner kép és hivatkozás.
 */

// 1) Partner lista – itt add meg a valós partnerek adatait
function impactshop_shops_registry() {
    return [
        // Példák; cseréld ki a tényleges partnerekre
        [
            'name'      => 'Arukereso.hu',
            'category'  => 'Divat',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/hg-logo-arukereso-color-20231018-1200-0-90-cr.webp',
            'shop_slug' => 'arukereso'
        ],
        [
            'name'      => 'Arukereso.hu',
            'category'  => 'Elektronika',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/hg-logo-arukereso-color-20231018-1200-0-90-cr.webp',
            'shop_slug' => 'arukereso'
        ],
		[
        	'name'      => 'Decathlon',
            'category'  => 'Sport',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/DECATHLON_logo_lockup_stacked_blue_rgb_-_Expires_on_31-12-2100.jpg.webp',
            'shop_slug' => 'decathlon'
		],
	 	[
            'name'      => 'REGIO Játék',
            'category'  => 'Játék',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/logo.jpeg',
            'shop_slug' => 'regiojatek'
        ],
	 	[
            'name'      => 'Vision Express',
            'category'  => 'Egészség',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/Nevtelen-200-x-200-keppont.png',
            'shop_slug' => 'visionexpress'
        ],
   		[
            'name'      => '4home',
            'category'  => 'Otthon',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/log.jpeg',
            'shop_slug' => '4home'
    	];
}

// 2) Banner lista – ide add meg a bannereket (kép, link, leírás)
function impactshop_banner_registry() {
    return [
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/banner-webre-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Arukereso.hu'
        ],
		[
        	'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/Decathlon-banner-webre-2057-x-358-keppont-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Decathlon'
		],
		[
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/banner-webre-2.png',
            'href'  => '/ajanlat',
            'label' => 'Akció REGIO Játék'
        ],
		[
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/log-1.jpeg',
            'href'  => '/ajanlat',
            'label' => 'Akció 4home'
        ],
		[
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/Nevtelen-200-x-200-keppont-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Vision Express'
    	];
}

// 3) Segédfüggvény GET paraméterek tisztítására
function impactshop_get_query($key) {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : '';
}

// 4) Scroller shortcode – végtelenített, vízszintes scroll bannerekkel
function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts([
        'category'    => '',    // csak adott kategória logói; üres = mind
        'inject_every'=> 5,     // ennyi logó után jöjjön egy banner
        'speed'       => 30     // animáció sebessége (másodpercben), nagyobb = lassabb
    ], $atts);

    $shops   = impactshop_shops_registry();
    $banners = impactshop_banner_registry();

    // Kategória szerinti szűrés
    if (!empty($a['category'])) {
        $shops = array_values(array_filter($shops, function($shop) use ($a) {
            return ($shop['category'] === $a['category']);
        }));
    }

    // Bemeneti elemek összeállítása – partnerek + bannerek injektálása
    $items = [];
    $index = 0;
    $injectEvery = max(1, intval($a['inject_every']));
    foreach ($shops as $shop) {
        $items[] = ['type' => 'shop', 'data' => $shop];
        $index++;
        if ($injectEvery > 0 && $index % $injectEvery === 0 && !empty($banners)) {
            // Banners cyclikusan: számítsd ki a sorszámot a meglévő bannerek közül
            $bannerIdx = ($index / $injectEvery - 1) % count($banners);
            $items[] = ['type' => 'banner', 'data' => $banners[$bannerIdx]];
        }
    }

    // GET paraméterek öröklése
    $d1  = impactshop_get_query('d1');
    $amb = impactshop_get_query('amb');
    $src = impactshop_get_query('src') ?: 'impactshop';

    ob_start();
    ?>
    <style>
    .impactshop-scroller {
        overflow: hidden;
        width: 100%;
        white-space: nowrap;
        position: relative;
    }
    .impactshop-scroller-track {
        display: inline-block;
        white-space: nowrap;
        animation: impactshop-scroll linear infinite;
    }
    @keyframes impactshop-scroll {
        from { transform: translateX(0); }
        to   { transform: translateX(-50%); }
    }
    /* Hover esetén az animáció megállítása */
    .impactshop-scroller:hover .impactshop-scroller-track {
        animation-play-state: paused;
    }
    .impactshop-item {
        display: inline-block;
        margin-right: 20px;
    }
    </style>
    <div class="impactshop-scroller">
        <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
            <?php
            // Kétszer ismételjük az elemeket a végtelen scrollhoz
            $loopItems = array_merge($items, $items);
            foreach ($loopItems as $it) {
                if ($it['type'] === 'shop') {
                    $shop = $it['data'];
                    // CTA hivatkozás: ha van d1 → /go/{shop}, különben Fillout
                    if ($d1) {
                        $cta = add_query_arg([
                            'd1'  => $d1,
                            'amb' => $amb,
                            'src' => $src
                        ], home_url('/go/'. rawurlencode($shop['shop_slug'])));
                    } else {
                        $fillout_url = 'https://fillout.com/t/eM61RLkz6jus?shop=xxxxx'; // cseréld ki a saját űrlapodra
                        $cta = add_query_arg([
                            'shop' => $shop['shop_slug'],
                            'amb'  => $amb,
                        ], $fillout_url);
                    }
                    ?>
                    <div class="impactshop-item">
                        <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                            <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                                 loading="lazy" decoding="async" style="height:60px;width:auto;">
                        </a>
                    </div>
                    <?php
                } else {
                    $banner = $it['data'];
                    ?>
                    <div class="impactshop-item">
                        <a href="<?php echo esc_url($banner['href']); ?>" aria-label="<?php echo esc_attr($banner['label']); ?>">
                            <img src="<?php echo esc_url($banner['img']); ?>" alt="<?php echo esc_attr($banner['label']); ?>"
                                 loading="lazy" decoding="async" style="height:60px;width:auto;">
                        </a>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('impactshop_scroller','impactshop_shortcode_scroller');

// 5) Katalógus shortcode – kategóriák szerinti tabokkal és keresővel
function impactshop_shortcode_catalog($atts) {
    $a = shortcode_atts([
        'show_tabs' => '1', // tabok megjelenjenek-e
        'search'    => '1', // keresőmező megjelenjen-e
        'per_page'  => '200' // jelenleg nem használjuk, de később segíthet paginációnál
    ], $atts);

    $shops = impactshop_shops_registry();
    // Rendezés: először kategória, majd név szerint
    usort($shops, function($x, $y) {
        $cmp = strcmp($x['category'], $y['category']);
        if ($cmp === 0) {
            return strcmp($x['name'], $y['name']);
        }
        return $cmp;
    });

    // Kategória lista generálása
    $categories = [];
    foreach ($shops as $shop) {
        if (!in_array($shop['category'], $categories, true)) {
            $categories[] = $shop['category'];
        }
    }

    $d1  = impactshop_get_query('d1');
    $amb = impactshop_get_query('amb');
    $src = impactshop_get_query('src') ?: 'impactshop';

    ob_start();
    ?>
    <div class="impactshop-catalog">
        <?php if ($a['show_tabs']) : ?>
            <ul class="impactshop-tabs" style="list-style:none;padding:0;margin:0 0 12px 0;display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($categories as $idx => $cat) : ?>
                    <li data-cat="<?php echo esc_attr($cat); ?>" class="<?php echo $idx === 0 ? 'active' : ''; ?>"
                        style="cursor:pointer;padding:8px 12px;border-radius:999px;background:#f3f3f3;font-weight:600;font-size:14px;">
                        <?php echo esc_html($cat); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($a['search']) : ?>
            <input type="text" class="impactshop-search" placeholder="Keresés…" aria-label="Keresés" style="width:100%;margin-bottom:10px;padding:8px;border:1px solid #ddd;border-radius:6px;" />
        <?php endif; ?>
        <div class="impactshop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:16px;">
            <?php foreach ($shops as $shop): 
                // Link kialakítása
                if ($d1) {
                    $cta = add_query_arg([
                        'd1'  => $d1,
                        'amb' => $amb,
                        'src' => $src
                    ], home_url('/go/'. rawurlencode($shop['shop_slug'])));
                } else {
                    $fillout_url = 'https://fillout.com/IDE-AZ-URL'; // cseréld ki a saját űrlapodra
                    $cta = add_query_arg([
                        'shop' => $shop['shop_slug'],
                        'amb'  => $amb,
                    ], $fillout_url);
                }
            ?>
            <div class="impactshop-card" data-cat="<?php echo esc_attr($shop['category']); ?>" style="text-align:center;">
                <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                    <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                         loading="lazy" decoding="async" style="height:60px;width:auto;" />
                </a>
                <div style="font-size:14px;font-weight:600;margin-top:4px;">
                    <?php echo esc_html($shop['name']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (function(){
        const catalogElem = document.currentScript.previousElementSibling;
        const tabs      = catalogElem.querySelectorAll('.impactshop-tabs li');
        const searchEl  = catalogElem.querySelector('.impactshop-search');
        const cards     = catalogElem.querySelectorAll('.impactshop-card');
        function applyFilter() {
            const term = searchEl ? searchEl.value.toLowerCase() : '';
            const activeTab = catalogElem.querySelector('.impactshop-tabs li.active');
            const activeCat = activeTab ? activeTab.getAttribute('data-cat') : null;
            cards.forEach(function(card) {
                const matchesCat   = !activeCat || card.getAttribute('data-cat') === activeCat;
                const cardTitleEl  = card.querySelector('div');
                const titleText    = cardTitleEl ? cardTitleEl.textContent.toLowerCase() : '';
                const matchesSearch= !term || titleText.includes(term);
                card.style.display = (matchesCat && matchesSearch) ? 'block' : 'none';
            });
        }
        if (tabs.length) {
            tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    applyFilter();
                });
            });
        }
        if (searchEl) {
            searchEl.addEventListener('input', function() {
                applyFilter();
            });
        }
        // Első szűrés az oldal betöltésekor
        applyFilter();
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('impactshop_catalog','impactshop_shortcode_catalog');
A ChatGPT ezt mondta:

Gondolkozás
Megtaláltam a hibát: a shops tömb végét nem zártad le jól, és a banners tömb végén is „furcsa” karakterek (nem törő szóköz / tab) csúsztak be a ]; elé. Ez tipikusan „unexpected ']'” vagy „syntax error, unexpected end of file” hibát dob WPCode-ban.
Az alábbi két részletet másold vissza egyben a snippetbe a régiek helyére. (Csak ezek a blokkok – minden más maradhat, ahogy van.)

✅ Javított partnerlista (impactshop_shops_registry)
// 1) Partner lista – itt add meg a valós partnerek adatait
function impactshop_shops_registry() {
    return [
        [
            'name'      => 'Arukereso.hu',
            'category'  => 'Divat',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/hg-logo-arukereso-color-20231018-1200-0-90-cr.webp',
            'shop_slug' => 'arukereso',
        ],
        [
            'name'      => 'Arukereso.hu',
            'category'  => 'Elektronika',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/hg-logo-arukereso-color-20231018-1200-0-90-cr.webp',
            'shop_slug' => 'arukereso',
        ],
        [
            'name'      => 'Decathlon',
            'category'  => 'Sport',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/DECATHLON_logo_lockup_stacked_blue_rgb_-_Expires_on_31-12-2100.jpg.webp',
            'shop_slug' => 'decathlon',
        ],
        [
            'name'      => 'REGIO Játék',
            'category'  => 'Játék',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/logo.jpeg',
            'shop_slug' => 'regiojatek',
        ],
        [
            'name'      => 'Vision Express',
            'category'  => 'Egészség',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/Nevtelen-200-x-200-keppont.png',
            'shop_slug' => 'visionexpress',
        ],
        [
            'name'      => '4home',
            'category'  => 'Otthon',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/log.jpeg',
            'shop_slug' => '4home',
        ],
    ];
}
✅ Javított bannerlista (impactshop_banner_registry)
// 2) Banner lista – ide add meg a bannereket (kép, link, leírás)
function impactshop_banner_registry() {
    return [
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/banner-webre-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Arukereso.hu',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/Decathlon-banner-webre-2057-x-358-keppont-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Decathlon',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/banner-webre-2.png',
            'href'  => '/ajanlat',
            'label' => 'Akció REGIO Játék',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/log-1.jpeg',
            'href'  => '/ajanlat',
            'label' => 'Akció 4home',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/Nevtelen-200-x-200-keppont-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Vision Express',
        ],
    ];
}
Mit javítottam pontosan:
Minden tömb-elem végére tettem vesszőt (utolsó után is tehető, PHP 7.3+ tolerálja és elkerülünk „merge” hibákat).
Egységesen lezártam a return [ ... ]; szerkezeteket.
Eltávolítottam a bannerek végéről a rejtett, nem törő szóköz/tab karaktereket, amik szintaktikai hibát okozhatnak.
Képfájl URL-eket meghagytam, de érdemes ellenőrizni, hogy léteznek-e (404 nem ad PHP hibát, csak üres képet).
Gyors hibakeresési checklist (ha még mindig hibát ír):
WPCode típus: biztosan PHP Snippet (nem HTML/CSS/JS)?
Snippet aktív? Toggle „Active” bekapcsolva, majd „Save/Update”?
Más részekben nem maradt félbehagyott zárójel: minden [-hoz van ], minden {-hoz }.
Fillout URL: a kódban lévő ideiglenes https://fillout.com/... helyett add meg a saját űrlapod linkjét.
Elementorban Shortcode widgetet használsz, nem Szöveg/HTML widgetet.
Ha kiírja a WPCode a hiba sor számát, és ide bemásolod, célzottan megmondom, pontosan melyik karakter okozza.
Ezt mondtad:
kérem egyben az egész javított pap snippetet, kicserélem egyben az a biztos
A ChatGPT ezt mondta:
Értem 👍 az a legbiztosabb, ha egyben cseréled. Itt van a teljes, javított PHP snippet (benne a partner- és bannerlistával is). Ezt másold be új PHP Snippetként a WPCode-ba, vagy a meglévőt cseréld ki rá:
<?php
/**
 * Impact Shop – Logó scroller és katalógus kategóriákkal
 *
 * Használat:
 *  - [impactshop_scroller category="" inject_every="5" speed="30"]
 *  - [impactshop_catalog show_tabs="1" search="1" per_page="200"]
 */

// 1) Partner lista – itt add meg a valós partnerek adatait
function impactshop_shops_registry() {
    return [
        [
            'name'      => 'Arukereso.hu',
            'category'  => 'Divat',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/hg-logo-arukereso-color-20231018-1200-0-90-cr.webp',
            'shop_slug' => 'arukereso',
        ],
        [
            'name'      => 'Arukereso.hu',
            'category'  => 'Elektronika',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/hg-logo-arukereso-color-20231018-1200-0-90-cr.webp',
            'shop_slug' => 'arukereso',
        ],
        [
            'name'      => 'Decathlon',
            'category'  => 'Sport',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/DECATHLON_logo_lockup_stacked_blue_rgb_-_Expires_on_31-12-2100.jpg.webp',
            'shop_slug' => 'decathlon',
        ],
        [
            'name'      => 'REGIO Játék',
            'category'  => 'Játék',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/logo.jpeg',
            'shop_slug' => 'regiojatek',
        ],
        [
            'name'      => 'Vision Express',
            'category'  => 'Egészség',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/Nevtelen-200-x-200-keppont.png',
            'shop_slug' => 'visionexpress',
        ],
        [
            'name'      => '4home',
            'category'  => 'Otthon',
            'logo'      => 'https://app.sharity.hu/wp-content/uploads/2025/09/log.jpeg',
            'shop_slug' => '4home',
        ],
    ];
}

// 2) Banner lista – ide add meg a bannereket (kép, link, leírás)
function impactshop_banner_registry() {
    return [
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/banner-webre-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Arukereso.hu',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/Decathlon-banner-webre-2057-x-358-keppont-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Decathlon',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/banner-webre-2.png',
            'href'  => '/ajanlat',
            'label' => 'Akció REGIO Játék',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/log-1.jpeg',
            'href'  => '/ajanlat',
            'label' => 'Akció 4home',
        ],
        [
            'img'   => 'https://app.sharity.hu/wp-content/uploads/2025/09/Nevtelen-200-x-200-keppont-1.png',
            'href'  => '/ajanlat',
            'label' => 'Akció Vision Express',
        ],
    ];
}

// 3) Segédfüggvény GET paraméterek tisztítására
function impactshop_get_query($key) {
    return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : '';
}

// 4) Scroller shortcode
function impactshop_shortcode_scroller($atts) {
    $a = shortcode_atts([
        'category'    => '',
        'inject_every'=> 5,
        'speed'       => 30,
    ], $atts);

    $shops   = impactshop_shops_registry();
    $banners = impactshop_banner_registry();

    if (!empty($a['category'])) {
        $shops = array_values(array_filter($shops, function($shop) use ($a) {
            return ($shop['category'] === $a['category']);
        }));
    }

    $items = [];
    $index = 0;
    $injectEvery = max(1, intval($a['inject_every']));
    foreach ($shops as $shop) {
        $items[] = ['type' => 'shop', 'data' => $shop];
        $index++;
        if ($injectEvery > 0 && $index % $injectEvery === 0 && !empty($banners)) {
            $bannerIdx = ($index / $injectEvery - 1) % count($banners);
            $items[] = ['type' => 'banner', 'data' => $banners[$bannerIdx]];
        }
    }

    $d1  = impactshop_get_query('d1');
    $amb = impactshop_get_query('amb');
    $src = impactshop_get_query('src') ?: 'impactshop';

    ob_start();
    ?>
    <style>
    .impactshop-scroller {
        overflow: hidden;
        width: 100%;
        white-space: nowrap;
        position: relative;
    }
    .impactshop-scroller-track {
        display: inline-block;
        white-space: nowrap;
        animation: impactshop-scroll linear infinite;
    }
    @keyframes impactshop-scroll {
        from { transform: translateX(0); }
        to   { transform: translateX(-50%); }
    }
    .impactshop-scroller:hover .impactshop-scroller-track {
        animation-play-state: paused;
    }
    .impactshop-item {
        display: inline-block;
        margin-right: 20px;
    }
    </style>
    <div class="impactshop-scroller">
        <div class="impactshop-scroller-track" style="animation-duration: <?php echo max(5, intval($a['speed'])); ?>s;">
            <?php
            $loopItems = array_merge($items, $items);
            foreach ($loopItems as $it) {
                if ($it['type'] === 'shop') {
                    $shop = $it['data'];
                    if ($d1) {
                        $cta = add_query_arg([
                            'd1'  => $d1,
                            'amb' => $amb,
                            'src' => $src,
                        ], home_url('/go/'. rawurlencode($shop['shop_slug'])));
                    } else {
                        $fillout_url = 'https://fillout.com/IDE-AZ-URL'; // cseréld ki
                        $cta = add_query_arg([
                            'shop' => $shop['shop_slug'],
                            'amb'  => $amb,
                        ], $fillout_url);
                    }
                    ?>
                    <div class="impactshop-item">
                        <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                            <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                                 loading="lazy" decoding="async" style="height:60px;width:auto;">
                        </a>
                    </div>
                    <?php
                } else {
                    $banner = $it['data'];
                    ?>
                    <div class="impactshop-item">
                        <a href="<?php echo esc_url($banner['href']); ?>" aria-label="<?php echo esc_attr($banner['label']); ?>">
                            <img src="<?php echo esc_url($banner['img']); ?>" alt="<?php echo esc_attr($banner['label']); ?>"
                                 loading="lazy" decoding="async" style="height:60px;width:auto;">
                        </a>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('impactshop_scroller','impactshop_shortcode_scroller');

// 5) Katalógus shortcode
function impactshop_shortcode_catalog($atts) {
    $a = shortcode_atts([
        'show_tabs' => '1',
        'search'    => '1',
        'per_page'  => '200',
    ], $atts);

    $shops = impactshop_shops_registry();
    usort($shops, function($x, $y) {
        $cmp = strcmp($x['category'], $y['category']);
        return $cmp === 0 ? strcmp($x['name'], $y['name']) : $cmp;
    });

    $categories = [];
    foreach ($shops as $shop) {
        if (!in_array($shop['category'], $categories, true)) {
            $categories[] = $shop['category'];
        }
    }

    $d1  = impactshop_get_query('d1');
    $amb = impactshop_get_query('amb');
    $src = impactshop_get_query('src') ?: 'impactshop';

    ob_start();
    ?>
    <div class="impactshop-catalog">
        <?php if ($a['show_tabs']) : ?>
            <ul class="impactshop-tabs" style="list-style:none;padding:0;margin:0 0 12px 0;display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($categories as $idx => $cat) : ?>
                    <li data-cat="<?php echo esc_attr($cat); ?>" class="<?php echo $idx === 0 ? 'active' : ''; ?>"
                        style="cursor:pointer;padding:8px 12px;border-radius:999px;background:#f3f3f3;font-weight:600;font-size:14px;">
                        <?php echo esc_html($cat); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($a['search']) : ?>
            <input type="text" class="impactshop-search" placeholder="Keresés…" aria-label="Keresés" style="width:100%;margin-bottom:10px;padding:8px;border:1px solid #ddd;border-radius:6px;" />
        <?php endif; ?>
        <div class="impactshop-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:16px;">
            <?php foreach ($shops as $shop): 
                if ($d1) {
                    $cta = add_query_arg([
                        'd1'  => $d1,
                        'amb' => $amb,
                        'src' => $src,
                    ], home_url('/go/'. rawurlencode($shop['shop_slug'])));
                } else {
                    $fillout_url = 'https://fillout.com/IDE-AZ-URL'; // cseréld ki
                    $cta = add_query_arg([
                        'shop' => $shop['shop_slug'],
                        'amb'  => $amb,
                    ], $fillout_url);
                }
            ?>
            <div class="impactshop-card" data-cat="<?php echo esc_attr($shop['category']); ?>" style="text-align:center;">
                <a href="<?php echo esc_url($cta); ?>" aria-label="<?php echo esc_attr($shop['name']); ?>">
                    <img src="<?php echo esc_url($shop['logo']); ?>" alt="<?php echo esc_attr($shop['name']); ?>"
                         loading="lazy" decoding="async" style="height:60px;width:auto;" />
                </a>
                <div style="font-size:14px;font-weight:600;margin-top:4px;">
                    <?php echo esc_html($shop['name']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (function(){
        const catalogElem = document.currentScript.previousElementSibling;
        const tabs      = catalogElem.querySelectorAll('.impactshop-tabs li');
        const searchEl  = catalogElem.querySelector('.impactshop-search');
        const cards     = catalogElem.querySelectorAll('.impactshop-card');
        function applyFilter() {
            const term = searchEl ? searchEl.value.toLowerCase() : '';
            const activeTab = catalogElem.querySelector('.impactshop-tabs li.active');
            const activeCat = activeTab ? activeTab.getAttribute('data-cat') : null;
            cards.forEach(function(card) {
                const matchesCat   = !activeCat || card.getAttribute('data-cat') === activeCat;
                const cardTitleEl  = card.querySelector('div');
                const titleText    = cardTitleEl ? cardTitleEl.textContent.toLowerCase() : '';
                const matchesSearch= !term || titleText.includes(term);
                card.style.display = (matchesCat && matchesSearch) ? 'block' : 'none';
            });
        }
        if (tabs.length) {
            tabs.forEach(function(tab){
                tab.addEventListener('click', function(){
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    applyFilter();
                });
            });
        }
        if (searchEl) {
            searchEl.addEventListener('input', function() {
                applyFilter();
            });
        }
        applyFilter();
    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('impactshop_catalog','impactshop_shortcode_catalog');
Ebben már:
minden return [ ... ]; rendesen zárva van,
nincsenek rejtett karakterek,
a logó- és bannerlisták tiszták,
shortcode-ok: [impactshop_scroller] és [impactshop_catalog].
👉 Tedd be a WPCode-ba, mentsd, kapcsold Active-ra. Elementorban pedig Shortcode widgetbe illeszd a rövidkódokat.
Ha így is hibát dobna, másold ide a pontos hibaüzenetet, és bemérjük a sort.
Ezt mondtad:
A shortcode kat honnan kapom meg?
A ChatGPT ezt mondta:
A shortcode-ot nem kell külön „letölteni” vagy máshonnan keresni – azt maga a snippet adja neked.
Amikor a WPCode-ban elmented és aktiválod a fenti kódot, a WordPressben azonnal két rövidkód (shortcode) használható lesz:

Forgó logó + banner sávhoz:
[impactshop_scroller inject_every="5" speed="28"]
– inject_every="5": minden 5. logó után jön egy banner
– speed="28": milyen gyorsan forog
Katalógushoz (tabokkal és keresővel):
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
– show_tabs="1": jelenjenek meg a kategóriafülek
– search="1": legyen keresőmező
– per_page="200": egyszerre ennyi logót mutasson (nincs oldalakra bontás)
👉 Ezeket a rövidkódokat egyszerűen bemásolod Elementorban a Shortcode widget mezőjébe, és megjelenik az adott modul az oldalon.
Tehát: a snippet csak definiálja, hogy [impactshop_scroller] és [impactshop_catalog] mit csináljon, a használat pedig a te kezedben van.

Szeretnéd, hogy készítsek neked egy példát, hogyan nézne ki egy teljes oldal Elementorban (felül scroller, középen katalógus tabokkal, alul még egy scroller)?

Ezt mondtad:
igen. kategória választót hogy kell betennem az Elementorba?
A ChatGPT ezt mondta:

Gondolkozás
Persze! A kategória-választó nálunk a [impactshop_catalog …] shortcode-ból jön. Elementorban így teszed be:
Lépésről lépésre (Elementor)
Nyisd meg az oldalt Elementorral
Oldalak → Impact Shop → „Szerkesztés Elementorral”.
Adj hozzá egy új szekciót
– Katt a nagy „+” jelre → válassz 1 oszlopos elrendezést.
– A szekció Layout fülén állítsd:
Content Width: Full Width (ha teljes szélességet szeretnél)
Columns Gap: No Gap vagy Narrow
Height: Min Height (ha levegősebb blokkot akarsz)
Húzd be a Shortcode widgetet
– Bal oldali panel kereső: írd be Shortcode → húzd a frissen létrehozott szekcióba.
Írd be a kategória-választó shortcode-ot
A widget mezőjébe másold:
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
show_tabs="1" → megjelennek a kategóriafülek
search="1" → keresőmező is lesz
per_page="200" → egyszerre ennyi logót mutat (nincs lapozás)
Frissíts és nézd meg
– „Frissítés” → Előnézet. Látod felül a füleket (kategóriák), alatta a keresőt, és a logórácsot.
Ajánlott elrendezés (minta)
Hero szekció – cím, rövid magyarázat
Felső scroller (logók + bannerek, opcionális):
[impactshop_scroller inject_every="5" speed="28"]
Kategória-választó katalógus (ez a rész):
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
Alsó scroller (más tempóval / ritkább bannerekkel):
[impactshop_scroller inject_every="7" speed="24"]
Minden blokkhoz Elementorban külön szekciót hozz létre, így szépen tudsz külső/belső margóval, háttérrel, szegéllyel dolgozni.
Gyakori kérdések / hibák
Csak szövegként látszik a kód → biztos, hogy Shortcode widgetbe raktad (nem Szöveg / HTML)?
Nem jelenik meg semmi → a WPCode snippet legyen Active; a impactshop_shops_registry() listában legyenek valós shopok és category mezők.
Nincsenek fülek → a shopoknál hiányzik a category, vagy mind ugyanaz. Adj 5–8 felső kategóriát és töltögess be pár logót kategóriánként.
Linkelés viselkedése:
Ha nincs ?d1= az URL-ben → kattintáskor Fillout nyílik be előtöltve (shop, amb).
Ha van ?d1= → közvetlenül a /go/{shop} linkre visz.
Haladó kozmetika (opcionális)
A katalógus blokk szekcióján az Advanced fülön adj Paddinget (pl. 24–48 px), hogy levegősebb legyen.
A tabok fölé tehetsz egy Heading widgetet („Válassz kategóriát”).
Ha több katalógus-blokkot használnál külön oldalszakaszokban, mindegyikbe ugyanaz a shortcode mehet; a tabok és a kereső mindegyikben működni fognak.
Ha szeretnéd, adok egy gyors „szépség-presetet” (milyen betűméretek, margók, háttérszínek álljanak Elementorban), vagy beállítok előszűrést URL-paraméterrel (pl. ?cat=Divat%20%26%20Egészség), hogy megnyitáskor egy adott fül legyen aktív.
Ezt mondtad:
ezt még használjuk? Sharity Offers Import filmzene|https://feed.arukereso.com/dognet_film-és-zene/DogNet_Affiliate_Film-és-zene.HU.xml
etelitall|https://feed.arukereso.com/dognet_Étel-ital/DogNet_Affiliate_Étel-Ital.HU.xml
erotika|https://feed.arukereso.com/dognet_erotika/DogNet_Affiliate_Erotika.HU.xml
szepseg|https://feed.arukereso.com/dognet_szépségápolás-és-egészség/DogNet_Affiliate_Szépségápolás-és-egészség.HU.xml
szamitogep|https://feed.arukereso.com/dognet_számítógép/DogNet_Affiliate_Számítógép.HU.xml
sport|https://feed.arukereso.com/dognet_sportésfitness/DogNet_Affiliate_SportésFitness.HU.xml
otthonkert|https://feed.arukereso.com/dognet_otthonéskert/DogNet_Affiliate_OtthonésKert.HU.xml
jatekok|https://feed.arukereso.com/dognet_játék/DogNet_Affiliate_Játék.HU.xml
irodatech|https://feed.arukereso.com/dognet_irodatechnika/DogNet_Affiliate_Irodatechnika.HU.xml
hobbi|https://feed.arukereso.com/dognet_hobbi/DogNet_Affiliate_Hobbi.HU.xml
haztartas|https://feed.arukereso.com/dognet_háztartási-gép/DogNet_Affiliate_Háztartási-Gép.HU.xml
haziallat|https://feed.arukereso.com/dognet_háziállat/DogNet_Affiliate_Háziállat.HU.xml
hangszer|https://feed.arukereso.com/dognet_hangszer/DogNet_Affiliate_Hangszer.HU.xml
epitkezes|https://feed.arukereso.com/dognet_Építkezés-felújítás/DogNet_Affiliate_Építkezés-Felújítás.HU.xml
divat|https://feed.arukereso.com/dognet_divat-ruházat/DogNet_Affiliate_Divat-Ruházat.HU.xml
butor|https://feed.arukereso.com/dognet_bútor-lakberendezés/DogNet_Affiliate_Bútor-Lakberendezés.HU.xml
babamama|https://feed.arukereso.com/dognet_baba-mama/DogNet_Affiliate_BabaMama.HU.xml
automotor|https://feed.arukereso.com/dognet_autó-motor/DogNet_Affiliate_AutóMotor.HU.xml
ajandek|https://feed.arukereso.com/dognet_ajándékozás/DogNet_Affiliate_Ajándékozás.HU.xml
muszaki|https://feed.arukereso.com/dognet_műszaki-cikk/DogNet_Affiliate_MúszakiCikk.HU.xml
A ChatGPT ezt mondta:
Ezek a sorok egy Dognet XML feed lista – kategóriánként külön feed-URL.
Régebben úgy terveztük, hogy ebből automatikusan importáljuk az ajánlatokat (Sharity Offers Import), tehát például a divat|https://feed.arukereso.com/... feedből jönnek a divat kategóriás termékek.
Most viszont a jelenlegi Impact Shop logika más:

nem termékeket listázunk feedből, hanem shopokat logóval és opcionális bannereket,
a shop-kattintás mindig a Fillouton keresztül vagy közvetlenül a /go/{shop} linkre megy,
a támogatási paramétereket (d1, amb, src) a redirect viszi tovább, nem a feed.
👉 Vagyis: ezeket az XML feed URL-eket jelenleg nem használjuk az Impact Shop kódjában.
Csak akkor lenne rájuk szükség, ha:
külön oldalon terméklistákat akarnál megjeleníteni kategóriánként,
vagy feed alapján frissítenéd a kínálatot (pl. akciós ajánlatokat automatikusan).
Most a bannereket/logókat kézzel állítod be a snippetben (és feltöltöd a médiatárba), tehát a feedek nem futnak.
Ha a jövőben akarsz „ajánlatok” oldalt (pl. „Sport akciók” az XML feedből), akkor érdemes lesz újra elővenni ezeket a linkeket.

Szeretnéd, hogy megmutassam, hogyan lehetne ezeket az XML feedeket betölteni és pl. Elementorban listázni termékeket (pl. 10 random akciós ajánlat „Sport” kategóriából)?
Ezt mondtad:
Frissíts és nézd meg
Feltöltött kép
működik csak nem értem miért kellett  az oszlopos verzió, így az egyik oldalra tömörül minden 
A ChatGPT ezt mondta:

Gondolkozás
Látom: működik a katalógus, csak „egy oldalra tömörül”. Ez azért van, mert az Elementorban a shortcode-ot egy többoszlopos (3 hasábos) szekció bal oszlopába raktad. A shortcode teljes szélességű blokkot szeretne, de a hasáb beszűkíti → ezért ül minden balra.
Tedd így, és középre, teljes szélességre kerül:

Gyors megoldás (Elementor beállítások)
Hozz létre egy új szekciót 1 oszloppal
– katt a „+” → Single column (ne Inner Section).
Szakasz (Section) → Layout
Content Width: Full Width
Stretch Section: ON (ha van)
Columns Gap: No Gap vagy Narrow
Tedd át a Shortcode widgetet ebbe az 1 oszlopos szekcióba
– Ide jön a [impactshop_catalog …] rövidkód.
Oszlop (Column) → Layout
Vertical Align: Top
Horizontal Align: Default (vagy Center)
Advanced → Padding
Adj a szekciónak pl. Padding: 24–48 px (felül/alul), hogy levegős legyen.
Ha maradnál a meglévő szekciónál
Töröld a fölösleges oszlopokat, hagyj 1 oszlopot 100% szélességgel.
VAGY húzd ki a Shortcode-ot egy külön 1 oszlopos szekcióba a fölé/alá.
(Opcionális) Középre igazító kis CSS
Ha a fülek (tabok) mégis balra húznak, dobhatsz be ennyi egyedi CSS-t (Appearance → Customize → Additional CSS vagy Elementor → Site Settings → Custom CSS):
/* Kategóriafülek sorának középre igazítása */
.impactshop-catalog .impactshop-tabs { 
  display: flex; 
  flex-wrap: wrap; 
  justify-content: center; 
}

/* Kártyarács belső elemek középre igazítása */
.impactshop-catalog .impactshop-grid { 
  justify-items: center; 
}
Scroller blokknál is ugyanez a szabály
A [impactshop_scroller …] rövidkódot is külön 1 oszlopos szekcióba tedd. Több oszlopos szekcióban a sáv beszűkül, és balra tolódik.
Ha szeretnéd, leírom a javasolt, „szép” oldalstruktúrát Elementorhoz (szekciók/spacing értékek), vagy megnézem képről a mostani szekció-hierarchiát és megmondom, mit kell törölni/áthelyezni pixelpontosan.
Ezt mondtad:
Működik. Azt nem értem honnan vette a logókat és a kategória mezőket, mikor még üres a shop tábla  https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv
A ChatGPT ezt mondta:
Ez teljesen logikus, hogy most kicsit „mágikusnak” tűnik 🙂
A helyzet a következő:
A snippet, amit feltettünk a WPCode-ba, nem a Google Sheetből olvassa be a shopokat.
Van benne egy fix, kézzel kitöltött függvény (impactshop_shops_registry()), amiben példaként benne hagytam pár shopot (Árukereső, Decathlon, REGIO Játék, Vision Express, 4home).
Ezekhez én adtam kategória mezőket is (Divat, Elektronika, Sport, Egészség, Otthon, Játék).
👉 Emiatt most ezek jelennek meg a katalógusban és a fülekben — teljesen függetlenül attól, hogy a Google Sheet üres.
Miért jó így?
Azért csináltuk, hogy azonnal legyen mit tesztelned, és lásd működés közben a logikát.
Ez a „dummy adat” akkor is megjelenik, ha a Sheets táblád még üres.
Hogyan lesz a Google Sheet a forrás?
Két út van:
Kézzel töltöd a snippetben a impactshop_shops_registry() tömböt (ahogy most is van). Ez fix lista, bármikor bővítheted.
Automatikusan a Google Sheetből olvasunk: kell írnom egy plusz kódot, ami a CSV-t (output=csv) betölti, feldolgozza, és abból állítja össze a tömböt. Ekkor nem kell többé kézzel babrálni a logókkal/kategóriákkal a snippetben.
Szeretnéd, hogy kicseréljem a snippetben a mostani fix listát egy olyasmire, ami közvetlenül a Google Sheetből szívja be a shopokat (név, kategória, logó, shop_slug oszlopokból)?
Ezt mondtad:
igen, kézzel nem akarom átírni mert abból hiba lesz, mindent shop és banner sheetből vegyen, oda fogom feltölteni
A ChatGPT ezt mondta:

Gondolkozás
Bravó — ez a jó irány. Csináljuk teljesen „adatvezérelten”: a shopok és a bannerek is Google Sheetből jöjjenek, a WordPress-ben ne kelljen kézzel turkálni. Alább adok egy kész, bemásolható WPCode PHP snippetet, ami:
két CSV-ről (publish to web → CSV) olvas: Shops és Banners,
gyors cache-et (transient) használ,
bátran kezeli az ékezetes/osztott fejléceket,
a meglévő shortcode-ok változatlanok maradnak:
[impactshop_scroller category="" inject_every="5" speed="30"]
[impactshop_catalog show_tabs="1" search="1" per_page="200"]
Csak a két CSV URL-t és a Fillout URL-t kell beírnod a snippet tetején, minden más automatikus.
Teljes, kész PHP snippet (CSV-ről olvasó változat)
Másold be: WPCode → Add Snippet → PHP Snippet → Active
<?php
/**
 * Impact Shop – Shops & Banners CSV-ből (Google Sheets → publish to web → CSV)
 *
 * Shortcode-ok:
 *  - [impactshop_scroller category="" inject_every="5" speed="30"]
 *  - [impactshop_catalog show_tabs="1" search="1" per_page="200"]
 */

function impactshop_settings() {
  return [
    // ⬇⬇⬇ IDE TEDD BE A SAJÁT LINKJEIDET ⬇⬇⬇
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv', // Shops
    'banners_csv_url' => 'https://YOUR-BANNERS-SHEET-Csv-URL', // Banners – készíts külön sheetet, lásd alább
    'fillout_url'     => 'https://fillout.com/IDE-AZ-URL', // a meglévő Fillout űrlapod linkje (shop, amb, ngo_code)
    'cache_ttl'       => 15 * MINUTE_IN_SECONDS,            // CSV cache idő
  ];
}

/** ===== CSV segédek ===== */

/** kis ékezet-normalizáló a fejléc mezőkhöz */
function impactshop_slugify_header($s) {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = [
    'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
    'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u'
  ];
  $s = strtr($s, $map);
  $s = preg_replace('~[^a-z0-9]+~u', '_', $s);
  return trim($s, '_');
}

/** CSV letöltés + parse asszociatív tömbbé (fejlécek alapján) */
function impactshop_fetch_csv_assoc($url, $cache_key, $ttl) {
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;

  $resp = wp_remote_get($url, ['timeout'=>20]);
  if (is_wp_error($resp)) return [];
  $body = wp_remote_retrieve_body($resp);
  if (!$body) return [];

  // BOM eltávolítás
  if (substr($body,0,3) === "\xEF\xBB\xBF") $body = substr($body,3);

  $lines = preg_split("/\r\n|\n|\r/", $body);
  if (!$lines || count($lines) < 1) return [];

  // delimiter detektálás (vessző vs pontosvessző)
  $first = $lines[0];
  $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

  // fejlécek
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
    // üres sorokat dobjuk
    if (implode('', $row) === '') continue;
    $rows[] = $row;
  }

  set_transient($cache_key, $rows, $ttl);
  return $rows;
}

/** ===== Shops & Banners betöltése CSV-ből ===== */

/**
 * Shops oszlopok (fejléc → kulcs):
 *  - name | nev
 *  - category | kategoria
 *  - logo | logo_url | kep | image
 *  - shop_slug | slug | go_slug
 */
function impactshop_get_shops() {
  $s = impactshop_settings();
  $rows = impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
  $out = [];
  foreach ($rows as $r) {
    // rugalmas fejlécnevek
    $name  = $r['name']       ?? ($r['nev']       ?? '');
    $cat   = $r['category']   ?? ($r['kategoria'] ?? '');
    $logo  = $r['logo']       ?? ($r['logo_url']  ?? ($r['kep'] ?? ($r['image'] ?? '')));
    $slug  = $r['shop_slug']  ?? ($r['slug']      ?? ($r['go_slug'] ?? ''));
    if (!$name || !$slug) continue; // kötelező
    $out[] = [
      'name'      => $name,
      'category'  => $cat ?: 'Egyéb',
      'logo'      => $logo,
      'shop_slug' => $slug,
    ];
  }
  return $out;
}

/**
 * Banners oszlopok:
 *  - img | image | banner | kep
 *  - href | url | link
 *  - label | cimke | title
 *  - (opcionális) category | kategoria  → ha megadod, a scroller a category param alapján szűrheti is
 */
function impactshop_get_banners() {
  $s = impactshop_settings();
  $rows = impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  $out = [];
  foreach ($rows as $r) {
    $img   = $r['img']   ?? ($r['image']  ?? ($r['banner'] ?? ($r['kep'] ?? '')));
    $href  = $r['href']  ?? ($r['url']    ?? ($r['link']   ?? ''));
    $label = $r['label'] ?? ($r['cimke']  ?? ($r['title']  ?? 'Banner'));
    $cat   = $r['category'] ?? ($r['kategoria'] ?? '');
    if (!$img || !$href) continue;
    $out[] = [
      'img'   => $img,
      'href'  => $href,
      'label' => $label,
      'category' => $cat,
    ];
  }
  return $out;
}

/** ===== Közös segéd ===== */
function impactshop_get_query($key) {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : '';
}

/** ===== Scroller shortcode ===== */
function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts([
    'category'     => '', // csak adott kategória logói; üres = mind
    'inject_every' => 5,  // ennyi logó után jöjjön banner
    'speed'        => 30, // animáció hossza (s) – nagyobb = lassabb scroll
  ], $atts);

  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  // Kategória szerinti szűrés (logók)
  if (!empty($a['category'])) {
    $shops = array_values(array_filter($shops, function($s) use ($a) {
      return strcasecmp($s['category'], $a['category']) === 0;
    }));
  }
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  // Bannerek átszűrése kategóriára, ha a banner is címkézett
  $banners_filtered = $banners;
  if (!empty($a['category'])) {
    $banners_filtered = array_values(array_filter($banners, function($b) use ($a) {
      return empty($b['category']) || strcasecmp($b['category'], $a['category']) === 0;
    }));
  }

  // Injektálás: N logó után 1 banner (ciklikusan)
  $injectEvery = max(1, intval($a['inject_every']));
  $mixed = [];
  $bi = 0;
  foreach ($shops as $i => $s) {
    $mixed[] = ['type'=>'shop','data'=>$s];
    if (($i+1) % $injectEvery === 0 && !empty($banners_filtered)) {
      $mixed[] = ['type'=>'banner','data'=>$banners_filtered[$bi % count($banners_filtered)]];
      $bi++;
    }
  }
  // Folytatólagos scroll: duplázzuk a sort
  $stream = array_merge($mixed, $mixed);

  $d1  = impactshop_get_query('d1');
  $amb = impactshop_get_query('amb');
  $src = impactshop_get_query('src') ?: 'impactshop';
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

/** ===== Katalógus shortcode ===== */
function impactshop_shortcode_catalog($atts) {
  $a = shortcode_atts([
    'show_tabs' => '1',
    'search'    => '1',
    'per_page'  => '200',
  ], $atts);

  $shops = impactshop_get_shops();
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  // Rendezés: kategória → név
  usort($shops, function($x,$y){
    $c = strcasecmp($x['category'],$y['category']);
    return $c === 0 ? strcasecmp($x['name'],$y['name']) : $c;
  });

  // Kategóriák kigyűjtése
  $cats = [];
  foreach ($shops as $s) {
    $cats[$s['category']] = true;
  }
  $categories = array_keys($cats);
  sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

  $d1  = impactshop_get_query('d1');
  $amb = impactshop_get_query('amb');
  $src = impactshop_get_query('src') ?: 'impactshop';
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
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  (function(){
    const root = document.currentScript.previousElementSibling;
    const tabs = root.querySelectorAll('.impactshop-tabs li');
    const search = root.querySelector('.impactshop-search');
    const cards = root.querySelectorAll('.impactshop-card');
    function apply() {
      const term = search ? (search.value || '').toLowerCase() : '';
      const active = root.querySelector('.impactshop-tabs li.active');
      const cat = active ? active.getAttribute('data-cat') : null;
      cards.forEach(function(card){
        const okCat = !cat || card.getAttribute('data-cat') === cat;
        const name = (card.querySelector('div')?.textContent || '').toLowerCase();
        const okQ = !term || name.indexOf(term) !== -1;
        card.style.display = (okCat && okQ) ? '' : 'none';
      });
    }
    tabs.forEach(function(tab){
      tab.addEventListener('click', function(){
        tabs.forEach(t=>t.classList.remove('active'));
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
Hogyan készítsd elő a Google Sheeteket
1) Shops sheet (amit már küldtél — gid=0)
Fejlécek (bármelyik szinonima jó):
name (vagy) név
category (vagy) kategória
logo (vagy) logo_url, kép, image
shop_slug (vagy) slug, go_slug
Publish to web → CSV linket másold a snippet shops_csv_url mezőbe (ezt már beírtam a példában).
2) Banners sheet (új lap)
Fejlécek:
img (vagy) image, banner, kép
href (vagy) url, link
label (vagy) címke, title
(opcionális) category (vagy) kategória – ha megadod, a scroller category="X" szűrésre csak az oda illő bannereket injektálja.
File → Publish to the web → CSV, és a linkjét másold a snippet banners_csv_url mezőbe.
Tipp: az első sor a fejléc, minden további sor egy rekord. Az akcentusokat, szóközöket a kód tolerálja (normalizálás).
Mit érdemes tesztelni
Üres Sheet: ha a Shops üres, a blokk „Nincs megjeleníthető partner.” üzenetet ad.
Egy sorral: adj hozzá 1 boltot (név+slug kötelező), azonnal megjelenik a katalógusban és a scrollerben.
Banner injektálás: a Banners sheetbe tegyél 2–3 sort; a scrollerben inject_every="4" esetén 4 logó után jön 1 banner.
Kategória-szűrés: a scroller category="Divat" paraméterrel csak a „Divat” kategóriájú shopokat mutatja, és ha a banner sorban category=Divat, azt is beszúrja; más kategóriájú bannert kihagy.
Ha megvan a Banners CSV link, be is írhatom a snippet tetejébe a pontos URL-t, és onnantól a teljes oldaladat a két sheet vezérli — macera nélkül, stabilan.
Ezt mondtad:
Banners : https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=0&single=true&output=csv  Egybe kérem a kódot nem akarom elgépelni. 
A ChatGPT ezt mondta:
Itt a teljes, egyben bemásolható WPCode PHP snippet – a Banners CSV linket már beírtam. (Csak a fillout_url-t cseréld a saját űrlapodra.)
<?php
/**
 * Impact Shop – Shops & Banners CSV-ből (Google Sheets → publish to web → CSV)
 *
 * Shortcode-ok:
 *  - [impactshop_scroller category="" inject_every="5" speed="30"]
 *  - [impactshop_catalog show_tabs="1" search="1" per_page="200"]
 */

function impactshop_settings() {
  return [
    // ⬇⬇⬇ IDE TEDD BE A SAJÁT LINKJEIDET / BEÁLLÍTÁSOKAT ⬇⬇⬇
    'shops_csv_url'   => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vR8ASri56jQ1h7yzeb1lWqOvvOY3Kli7x8WxdkLwlet6I7QnBoOg2oiaNEcxdjSp3UbV8kjhMKWzXPz/pub?gid=0&single=true&output=csv', // Shops CSV
    'banners_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vT5s4BXN4TAU8C2StrKl53nkNJNtHf1DoIrWY8ymdpYbJGuwERdswDnk-hKsmsCXMayOBua5xCagRyC/pub?gid=0&single=true&output=csv',       // Banners CSV (megadott)
    'fillout_url'     => 'https://fillout.com/IDE-AZ-URL', // ← CSERÉLD a saját űrlapodra (rejtett: shop, amb; számított: ngo_code)
    'cache_ttl'       => 15 * MINUTE_IN_SECONDS,            // CSV cache idő
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

/** ===== Shops & Banners betöltése CSV-ből ===== */

/**
 * Shops oszlopok (fejléc → kulcs szinonimák):
 *  - name | nev
 *  - category | kategoria
 *  - logo | logo_url | kep | image
 *  - shop_slug | slug | go_slug
 */
function impactshop_get_shops() {
  $s = impactshop_settings();
  $rows = impactshop_fetch_csv_assoc($s['shops_csv_url'], 'impactshop_csv_shops', $s['cache_ttl']);
  $out = [];
  foreach ($rows as $r) {
    $name  = $r['name']       ?? ($r['nev']       ?? '');
    $cat   = $r['category']   ?? ($r['kategoria'] ?? '');
    $logo  = $r['logo']       ?? ($r['logo_url']  ?? ($r['kep'] ?? ($r['image'] ?? '')));
    $slug  = $r['shop_slug']  ?? ($r['slug']      ?? ($r['go_slug'] ?? ''));
    if (!$name || !$slug) continue;
    $out[] = [
      'name'      => $name,
      'category'  => $cat ?: 'Egyéb',
      'logo'      => $logo,
      'shop_slug' => $slug,
    ];
  }
  return $out;
}

/**
 * Banners oszlopok (fejléc szinonimák):
 *  - img | image | banner | kep
 *  - href | url | link
 *  - label | cimke | title
 *  - (opcionális) category | kategoria
 */
function impactshop_get_banners() {
  $s = impactshop_settings();
  $rows = impactshop_fetch_csv_assoc($s['banners_csv_url'], 'impactshop_csv_banners', $s['cache_ttl']);
  $out = [];
  foreach ($rows as $r) {
    $img   = $r['img']   ?? ($r['image']  ?? ($r['banner'] ?? ($r['kep'] ?? '')));
    $href  = $r['href']  ?? ($r['url']    ?? ($r['link']   ?? ''));
    $label = $r['label'] ?? ($r['cimke']  ?? ($r['title']  ?? 'Banner'));
    $cat   = $r['category'] ?? ($r['kategoria'] ?? '');
    if (!$img || !$href) continue;
    $out[] = [
      'img'      => $img,
      'href'     => $href,
      'label'    => $label,
      'category' => $cat,
    ];
  }
  return $out;
}

/** ===== Közös segéd ===== */
function impactshop_get_query($key) {
  return isset($_GET[$key]) ? sanitize_text_field($_GET[$key]) : '';
}

/** ===== Scroller shortcode ===== */
function impactshop_shortcode_scroller($atts) {
  $a = shortcode_atts([
    'category'     => '',
    'inject_every' => 5,
    'speed'        => 30,
  ], $atts);

  $shops   = impactshop_get_shops();
  $banners = impactshop_get_banners();

  if (!empty($a['category'])) {
    $shops = array_values(array_filter($shops, function($s) use ($a) {
      return strcasecmp($s['category'], $a['category']) === 0;
    }));
  }
  if (!$shops) return '<div>Nincs megjeleníthető partner.</div>';

  $banners_filtered = $banners;
  if (!empty($a['category'])) {
    $banners_filtered = array_values(array_filter($banners, function($b) use ($a) {
      return empty($b['category']) || strcasecmp($b['category'], $a['category']) === 0;
    }));
  }

  $injectEvery = max(1, intval($a['inject_every']));
  $mixed = [];
  $bi = 0;
  foreach ($shops as $i => $s) {
    $mixed[] = ['type'=>'shop','data'=>$s];
    if (($i+1) % $injectEvery === 0 && !empty($banners_filtered)) {
      $mixed[] = ['type'=>'banner','data'=>$banners_filtered[$bi % count($banners_filtered)]];
      $bi++;
    }
  }
  $stream = array_merge($mixed, $mixed);

  $d1  = impactshop_get_query('d1');
  $amb = impactshop_get_query('amb');
  $src = impactshop_get_query('src') ?: 'impactshop';
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

/** ===== Katalógus shortcode ===== */
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
  sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

  $d1  = impactshop_get_query('d1');
  $amb = impactshop_get_query('amb');
  $src = impactshop_get_query('src') ?: 'impactshop';
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
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <script>
  (function(){
    const root = document.currentScript.previousElementSibling;
    const tabs = root.querySelectorAll('.impactshop-tabs li');
    const search = root.querySelector('.impactshop-search');
    const cards = root.querySelectorAll('.impactshop-card');
    function apply() {
      const term = search ? (search.value || '').toLowerCase() : '';
      const active = root.querySelector('.impactshop-tabs li.active');
      const cat = active ? active.getAttribute('data-cat') : null;
      cards.forEach(function(card){
        const okCat = !cat || card.getAttribute('data-cat') === cat;
        const name = (card.querySelector('div')?.textContent || '').toLowerCase();
        const okQ = !term || name.indexOf(term) !== -1;
        card.style.display = (okCat && okQ) ? '' : 'none';
      });
    }
    tabs.forEach(function(tab){
      tab.addEventListener('click', function(){
        tabs.forEach(t=>t.classList.remove('active'));
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
Megjegyzés: csak a fillout_url mezőt kell a saját űrlapodra cserélni; a Shops és Banners Sheet linkek már be vannak drótozva.



A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
