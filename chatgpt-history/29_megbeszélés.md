Átugrás a tartalomra
Ezt mondtad:
Kellenek megosztasi lehetosegek a leheto legtobb kozossegi oldalra. Kerdes mi keruljon be a megosztasba
A ChatGPT ezt mondta:
A megosztási lehetőségekhez azt érdemes belőni, hogy a felhasználó barátai azonnal lássák, miről szól az ImpactShop és miért érdemes kattintani. A legtöbb közösségi oldal (Facebook, Messenger, Instagram, WhatsApp, Twitter/X, LinkedIn, Pinterest, Reddit, Viber, Telegram stb.) az Open Graph (OG) és/vagy Twitter Card metaadatokat használja a link előnézethez.
Ezekből minimumra szükség lesz:

Cím (og:title)
Rövid, figyelemfelkeltő üzenet: pl.
„Vásárlás = támogatás 💜 Nézd meg a Sharity ImpactShop-ot!”
Leírás (og:description)
1–2 mondatban magyarázza el a lényeget:
„Minden online vásárlással nonprofit szervezeteket támogatsz – neked nem kerül pluszba.”
Kép (og:image)
Minimum 1200×630 px, kontrasztos, logó + szlogen.
(Érdemes verzió: Hero banner „Vásárlás = támogatás”, konfetti, Sharity logóval.)
URL (og:url)
Maga az /impactshop link UTM paraméterekkel (pl. utm_source=share&utm_medium=social).
Kiegészítés platformokra:
Twitter/X → twitter:card=summary_large_image ugyanazzal a címmel, leírással, képpel.
WhatsApp, Messenger → automatikusan az OG metaadatokat húzza.
Pinterest → a képet fontos optimalizálni (függőleges 2:3 arány).
LinkedIn → szintén az OG-t veszi át.
Ha bővebb integráció kell, akkor a kattintás után lehet mutatni a konkrét shop/akció linkjét, pl.
Ha valaki egy adott bolt oldaláról oszt, a megosztásban ne az általános ImpactShop főcím menjen, hanem:
„Támogasd a [Bátor Tábor] vásárlásoddal! Vásárolj a [Shop neve]-n, és adomány lesz belőle!”
Ehhez az adott shop kártya neve + logo + várható adomány % is bekerülhet a metaadatba.
Így két szintű share stratégiád lehet:
Általános ImpactShop oldal – branding, „vásárlás = támogatás” üzenettel.
Konkrét shop/akció – testreszabott OG title/desc/image az adott partnerrel.
Ez egyben ad SEO előnyt is, mert minden shop kap külön landing + külön OG.
Szeretnéd, hogy készítsek neked egy konkrét meta kódrészletet (OG + Twitter Card) az általános ImpactShop főoldalhoz, amit be tudsz tenni a <head>-be?

Ezt mondtad:
Az a kerdes pontosan mi a cel. Termeszetesen leadet hozni de jo lenne ha tudnank profikokat is epiteni a latogatokrol. Igy mar a megosztas sokkal informativabb foleg ha az illeto tobbszor visszazeter tobbfele aktivitast vegez. Akkor mar erdekesek az ismerosei. Foleg ha tudjuk jutalmazni azt ha valaki hoz egy vevot. Errol mit gondolsz?
A ChatGPT ezt mondta:
Akkor tulajdonképpen két réteget akarsz:
Lead / awareness szint – minél több ember lássa, hogy létezik az ImpactShop, és azonnal értse: „ha innen vásárolok, akkor adományozok.”
Referral / ambassador szint – nyomon tudd követni, ki hozott új vásárlót vagy visszatérő felhasználót, és ezért tudj jutalmazni.
Ez nem csak kommunikáció, hanem architektúra kérdés is.
Hogyan lehet ezt kerekre húzni?
Megosztás paraméterekkel: amikor valaki megosztja az ImpactShop-ot, kap egy saját azonosítót (pl. amb=xyz123). Ez már benne van a rendszeredben (Dognet d1, amb paraméterek).
Multi-touch tracking: ha az ismerőse később kattint, akkor a paraméter megmarad, és minden vásárláshoz hozzá tudod rendelni, ki volt az „ajánló”.
Visszatérő aktivitás: ha ugyanaz a user többféle shopból vásárol vagy többször visszatér, az is hozzáíródhat az ambassador számlájához.
Gamification: toplista („legjobb ajánló héten/hónapban”), badge-ek, extra jutalom – ez közösségépítő hatású.
Mi kerüljön a megosztásba?
Nem elég az általános szlogen, kell egy egyéni, személyes hívószó:
Title: „[Név] ajánlása: vásárlás = támogatás 💜”
Desc: „Én már innen vásárolok, mert minden rendelés adományt jelent. Csatlakozz te is!”
URL: ...?amb=@usercode → ebből lesz referral.
Ez sokkal erősebb, mint a pusztán szervezeti branding.
Miért izgalmas?
Ez a modell közelít a network fundraisinghez: nem csak az a fontos, hogy valaki vásárol, hanem hogy képes-e maga köré közösséget építeni. Ez a közösség-építő, „influencer-lite” logika sokkal jobban rezonál a fiatalokkal.
Az egyensúly itt a kulcs:

Ne legyen MLM szagú (nem arról van szó, hogy pénzt adsz az ajánlónak), hanem badge, nyilvános köszönet, extra NGO támogatás, játékosítás.
Legyen transzparens: mindenki lássa, hogy ki mennyit „mozgatott meg”.
Ezáltal a megosztás nem csak egyszeri leadgenerátor, hanem hosszú távú reputációs eszköz a felhasználónak is.
Gondolkozhatunk abban, hogy a meglévő Dognet amb paraméter mellé készítünk egy Referral Hub-ot, ahol minden felhasználó láthatja: „Eddig ennyit gyűjtöttem, ennyi embert hoztam.”

Így a megosztás célja háromszoros lesz:

Lehetőséget ad az adományozásra.
Profilálja és köti a látogatót (visszatérő aktivitás, személyes statisztika).
Közösségi státuszt épít („én vagyok az, aki a legtöbb embert mozgatta meg a Bátor Táborért”).
Ez valójában már egy growth loop: mindenki, aki vásárol, új meghívóvá válik → új vásárlások → új adományok → új meghívók.
Kérdés, hogy szeretnél-e ebbe irányba elmozdulni (Referral Hub + ambassador toplista), vagy inkább csak egyszerűbb „megosztás = egyedi azonosító” szinten maradjunk első körben?

Ezt mondtad:
Nekem a referral hub szimpi
A ChatGPT ezt mondta:
Ez jó irány! A Referral Hub pont az a köztes szint, ami egyszerre átlátható, játékos és erősíti a közösséget. Olyan lenne, mint egy személyes „impact dashboard”, ahol az ember látja:
Összes adomány, amit közvetve ő generált (azaz az ő linkjén keresztül jött vásárlásokból).
Hány vásárlást hozott → egyszerű activity counter.
Top NGO-k, akik az ő linkje által kaptak támogatást.
Badge-ek / mérföldkövek: pl. „Első vásárló hozva 🎉”, „10 vásárlás után”, „100 € támogatás generálva”.
Leaderboard (heti/havi országos vagy NGO-nkénti) → motivál a megosztásra.
Hogyan lehet összerakni a meglévő rendszerből?
Technikai alap
Már most is ott az amb paraméter a linkekben. Ezt a Dognet API riportban tudod rögzíteni.
A REST API-d (impact bridge) bővíthető úgy, hogy az amb mező alapján szűrve is tudjon kimutatást adni.
User nézet
Létrehozol egy /impact-hub oldalt (pl. Elementor sablon).
Shortcode: [impact_referral_hub amb="felhasznalo_kodja"]
Ez a shortcode lehúzza a tranzakciókat a Dognet API-ból → csoportosítva és összesítve jeleníti meg.
Játékosítás
Badge-eket könnyen lehet CSS ikonokkal, vagy egyszerű SVG overlay-ekkel.
Leaderboardot ugyanúgy, ahogy most NGO/shop toplistát építesz, csak amb szinten.
Megosztás integráció
Referral Hub-ban mindig ott a gomb: „Oszd meg a saját támogatói linkedet!”
Ez generál egy ?amb=XYZ linket OG meta adattal.
Jutalmazás
Nem pénzbeli, hanem presztízs + extra támogatás. Például:
Ha eléri a 10 vásárlót, a Sharity bedob +5 €-t az általa választott NGO-nak.
Ha havi top 3-ba kerül, kap egy kiemelt posztot Sharity social felületein.
Ez olyan, mintha az ImpactShop mellé adsz egy mini közösségi játékot. Nem MLM, mert senki nem pénzt kap, hanem látható hatást és elismerést.
Ez a hub egyébként vissza is fordítható marketingre: „Mutasd meg a saját Impact Hubodat a barátaidnak!” – vagyis maga a hub is shareelhető, így a growth loop önfenntartóvá válik.

Szeretnéd, hogy összerakjak egy konkrét vázlatot (REST endpoint + shortcode) a Referral Hub első változatához, ami a mostani riport rendszeredre épül?

Ezt mondtad:
Igen
A ChatGPT ezt mondta:

Gondolkozás
Zseniális — akkor csináljunk egy „Referral Hub” MVP-t, ami az amb ajánlókód alapján összesít, leaderboardot ad, és ad egy „Saját megosztási link” dobozt is. Az alábbi egyben bemásolható MU-plugin (vagy snippet). A mostani Dognet-API rétegre épít (auth + raw-transactions + last_id görgetés + meglévő segédfüggvények), tehát együtt fut az aktuális rendszereddel.
Mit tud?
REST: /wp-json/impactshop/v1/referrals?amb=KOD&from=YYYY-MM-DD&to=YYYY-MM-DD
Shortcode: [impact_referral_hub amb="auto" from="2025-09-01" to=""]
– amb="auto": URL-ből veszi az amb-t, különben a megadott kódot használja.
– Mutat összes hozott vásárlást, jutalékot (→ adomány), shop és NGO bontást, mini toplistát, és ad egy másolható saját megosztási linket.
<?php
/**
 * Sharity Impact – Referral Hub (MVP)
 * Funkciók:
 *  - Ambassador (amb) kimutatások Dognet RAW TRANSACTIONS alapján
 *  - REST: /wp-json/impactshop/v1/referrals
 *  - Shortcode: [impact_referral_hub amb="auto|KOD" from="YYYY-MM-DD" to="YYYY-MM-DD"]
 *
 * Függ: dognet_api_list_conversions_all(), impactshop_build_campaign_map()
 * Meglévő alapok: Dognet Publisher API (auth, raw-transactions + last_id), linkgenerálás:contentReference[oaicite:2]{index=2}:contentReference[oaicite:3]{index=3}
 */

/* ==================== AMB kinyerése tranzakciós sorból ==================== */

/**
 * Okos 'amb' kiválasztó: data2 / ref2 / sub_id2 / amb / URL / JSON stb.
 * Analóg a meglévő impactshop_pick_ngo_from_row() logikával, csak az ajánlókódra (amb) fókuszál.
 */
if (!function_exists('impactshop_pick_amb_from_row')) {
  function impactshop_pick_amb_from_row($row){
    // 1) jelölt mezők (gyakori elnevezések)
    $cands = [];
    foreach (['d2','data2','ref2','sub_id2','sub2','amb','referrer','ref','affiliate','affiliate_id','inviter'] as $k) {
      if (isset($row[$k]) && !is_array($row[$k])) {
        $v = trim((string)$row[$k]);
        if ($v !== '') $cands[] = $v;
      }
    }
    // néha az amb bekerül UTM-be / querybe / JSON-be
    foreach (['utm_content','utm_campaign'] as $k) {
      if (isset($row[$k]) && !is_array($row[$k])) {
        $v = trim((string)$row[$k]);
        if ($v !== '') $cands[] = $v;
      }
    }
    if (!$cands) return '';

    $is_slug_like = function($v){
      return (bool)(preg_match('~^[a-z0-9._-]{3,}$~i', $v) && preg_match('~[a-z]~i', $v));
    };

    $pick_from_query = function($q){
      parse_str($q, $p);
      foreach (['amb','ref','referrer','inviter','u'] as $kk) {
        if (!empty($p[$kk]) && is_string($p[$kk])) return trim($p[$kk]);
      }
      return '';
    };
    $pick_from_json = function($s){
      $j = json_decode($s, true);
      if (!is_array($j)) return '';
      foreach (['amb','ref','referrer','inviter','data2','ref2','sub2'] as $kk) {
        if (!empty($j[$kk]) && is_string($j[$kk])) return trim($j[$kk]);
      }
      return '';
    };

    // 2) közvetlen szép kód
    foreach ($cands as $v) if ($is_slug_like($v)) return $v;

    // 3) URL / querystring
    foreach ($cands as $v) {
      if (stripos($v,'http://')===0 || stripos($v,'https://')===0) {
        $qs = parse_url($v, PHP_URL_QUERY);
        if ($qs) { $z = $pick_from_query($qs); if ($is_slug_like($z)) return $z; }
      }
      if (strpos($v,'=')!==false && strpos($v,'&')!==false) {
        $z = $pick_from_query($v); if ($is_slug_like($z)) return $z;
      }
    }

    // 4) JSON-gyanús tartalom
    foreach ($cands as $v) {
      $t = trim($v);
      if ($t !== '' && ($t[0]==='{' || $t[0]==='[')) {
        $z = $pick_from_json($t); if ($is_slug_like($z)) return $z;
      }
    }

    // 5) fallback: az első „nem ronda” érték
    foreach ($cands as $v) {
      $t = trim($v);
      if ($t !== '') return $t;
    }
    return '';
  }
}

/* ==================== Aggregálás amb szinten ==================== */

/**
 * Teljes begyűjtés (Dognet RAW TRANSACTIONS) a meglévő last_id görgetős hívásokkal,
 * majd összesítés ambassador (amb) szerint. Ld. korábbi batch/all gyűjtés és status map:contentReference[oaicite:4]{index=4}:contentReference[oaicite:5]{index=5}.
 *
 * @return array ['rows'=>[...], 'meta'=>[...], 'leaderboard'=>[...]]
 */
if (!function_exists('impactshop_aggregate_referrals')) {
  function impactshop_aggregate_referrals($from, $to, $filterAmb = '', $status = 'approved') {
    $cache_key = sprintf('impactshop_referrals_%s_%s_%s_%s',
      $from, $to, strtolower(trim($status)), md5(strtolower(trim($filterAmb)))
    );
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;

    if (!function_exists('dognet_api_list_conversions_all')) {
      return ['rows'=>[], 'leaderboard'=>[], 'meta'=>['error'=>'Hiányzó függvény: dognet_api_list_conversions_all']];
    }

    $res = dognet_api_list_conversions_all($from, $to, $status, 80, 200);
    if (isset($res['error']) && is_wp_error($res['error'])) {
      return ['rows'=>[], 'leaderboard'=>[], 'meta'=>['error'=>$res['error']->get_error_message()]];
    }
    $items = $res['items'] ?? [];

    $maps = function_exists('impactshop_build_campaign_map') ? impactshop_build_campaign_map() : ['by_cid'=>[]];
    $by_cid = $maps['by_cid'];

    $rowsByAmb = [];   // amb => összesített számok
    $lbByAmb   = [];   // leaderboard alap (commission szerint)
    $grand = ['orders'=>0, 'commission'=>0.0];

    foreach ($items as $it) {
      // kampány → shop azonosítás
      $cid = 0;
      foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
        if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
      }
      $shopSlug='(ismeretlen shop)'; $shopName='(ismeretlen shop)';
      if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

      // NGO (d1/data1) – a meglévő „okos” kiválasztód használjuk, ha elérhető
      $ngo = function_exists('impactshop_pick_ngo_from_row')
            ? impactshop_pick_ngo_from_row($it)
            : (isset($it['d1']) ? (string)$it['d1'] : '');

      // Ambassador (amb/data2/ref2…)
      $amb = impactshop_pick_amb_from_row($it);
      if ($filterAmb !== '' && strtolower($amb) !== strtolower($filterAmb)) continue;

      // Jutalék
      $comm = 0.0;
      foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) {
        if (isset($it[$k]) && is_numeric($it[$k])) { $comm = (float)$it[$k]; break; }
      }

      // Sor összegzése amb szinten
      if (!isset($rowsByAmb[$amb])) {
        $rowsByAmb[$amb] = [
          'amb' => $amb ?: '(nincs)',
          'orders' => 0,
          'commission' => 0.0,
          'ngos'  => [],   // ngo => commission
          'shops' => [],   // shopSlug => commission
        ];
      }
      $rowsByAmb[$amb]['orders'] += 1;
      $rowsByAmb[$amb]['commission'] += $comm;
      $rowsByAmb[$amb]['ngos'][$ngo] = ($rowsByAmb[$amb]['ngos'][$ngo] ?? 0) + $comm;
      $rowsByAmb[$amb]['shops'][$shopSlug] = ($rowsByAmb[$amb]['shops'][$shopSlug] ?? 0) + $comm;

      // Leaderboard input
      $lbByAmb[$amb] = ($lbByAmb[$amb] ?? 0) + $comm;

      $grand['orders'] += 1;
      $grand['commission'] += $comm;
    }

    // Leaderboard: TOP N amb jutalék szerint
    arsort($lbByAmb, SORT_NUMERIC);
    $leaderboard = [];
    foreach ($lbByAmb as $amb=>$amt) {
      $leaderboard[] = ['amb'=>$amb ?: '(nincs)', 'commission'=>$amt];
    }

    // Ha konkrét amb-ra szűrünk, tegyük a „rows” tömbbe csak azt, egyben bontásokkal
    $rows = [];
    if ($filterAmb !== '') {
      $a = $rowsByAmb[$filterAmb] ?? null;
      if ($a) {
        // rendezett bontások
        arsort($a['ngos'], SORT_NUMERIC);
        arsort($a['shops'], SORT_NUMERIC);
        $rows[] = $a;
      }
    } else {
      // összes ambassador sor, jutalék szerint csökkenő
      $rows = array_values($rowsByAmb);
      usort($rows, function($x,$y){
        $d = $y['commission'] <=> $x['commission'];
        return $d !== 0 ? $d : strcasecmp($x['amb'],$y['amb']);
      });
      // rendezett al-bontások
      foreach ($rows as &$r) { arsort($r['ngos'], SORT_NUMERIC); arsort($r['shops'], SORT_NUMERIC); }
      unset($r);
    }

    $out = [
      'rows' => $rows,
      'leaderboard' => array_slice($leaderboard, 0, 10),
      'meta' => ['from'=>$from,'to'=>$to,'status'=>$status,'filterAmb'=>$filterAmb,'grand'=>$grand,'generated_at'=>current_time('mysql')]
    ];

    $ttl = 15 * MINUTE_IN_SECONDS; // igazodik a mostani cache policy-hoz:contentReference[oaicite:6]{index=6}
    set_transient($cache_key, $out, $ttl);
    return $out;
  }
}

/* ==================== REST: /impactshop/v1/referrals ==================== */

add_action('rest_api_init', function(){
  register_rest_route('impactshop/v1', '/referrals', [
    'methods'  => 'GET',
    'callback' => function(WP_REST_Request $req){
      $from   = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
      $to     = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
      $status = sanitize_text_field($req->get_param('status') ?: 'approved');
      $amb    = sanitize_text_field($req->get_param('amb')    ?: '');
      $data   = impactshop_aggregate_referrals($from, $to, $amb, $status);
      if (!empty($data['meta']['error'])) {
        return new WP_Error('dognet_err', 'Dognet API hiba: '.$data['meta']['error'], ['status'=>502]);
      }
      return rest_ensure_response($data);
    },
    'permission_callback' => '__return_true'
  ]);
});

/* ==================== Shortcode: [impact_referral_hub] ==================== */

if (!function_exists('impact_referral_hub_shortcode')) {
  function impact_referral_hub_shortcode($atts){
    $a = shortcode_atts([
      'amb'   => 'auto',             // 'auto' => URL ?amb=...; különben fix kód
      'from'  => date('Y-m-01'),
      'to'    => date('Y-m-d'),
      'status'=> 'approved',         // approved|pending|rejected|all
      'rate_huf' => '392.5',         // opcionális HUF konverzió kijelzéshez
    ], $atts, 'impact_referral_hub');

    $amb = $a['amb'];
    if ($amb === 'auto') {
      $amb = isset($_GET['amb']) ? sanitize_text_field($_GET['amb']) : '';
    }
    $ambDisp = $amb ?: '(nincs)';

    // Lekérés
    $data = impactshop_aggregate_referrals($a['from'], $a['to'], $amb, $a['status']);
    if (!empty($data['meta']['error'])) {
      return '<div style="color:#b00">Hiba: '.esc_html($data['meta']['error']).'</div>';
    }

    $eur = function($n){ return number_format((float)$n, 2, ',', ' ').' €'; };
    $huf = function($n,$rate){ return number_format((float)$n*(float)$rate, 0, ',', ' ').' Ft'; };

    $rows = $data['rows'];
    $lb   = $data['leaderboard'];
    $grand= $data['meta']['grand'];
    $rate = (float)$a['rate_huf'];

    // Saját megosztási link (az /impactshop oldalra célozva, de viheti a kategós landingre is)
    $shareUrl = add_query_arg(['amb'=>$amb ?: ''], home_url('/impactshop'));
    // biztosítsuk az UTM-eket (branding egységesítés)
    $shareUrl = add_query_arg(['utm_source'=>'share','utm_medium'=>'social','utm_campaign'=>'impact_referral'], $shareUrl);

    ob_start(); ?>
    <div class="impact-refhub" style="font:14px/1.5 system-ui;display:grid;gap:16px">
      <div class="card" style="padding:12px;border:1px solid #e6e8ea;border-radius:10px">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between">
          <div>
            <div style="font-weight:700">Saját támogatói linked</div>
            <div style="font-size:12px;color:#666">Ezzel a linkkel hozott vásárlások azonosíthatók (amb: <code><?php echo esc_html($ambDisp); ?></code>)</div>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="text" value="<?php echo esc_attr($shareUrl); ?>" readonly
                   style="width:min(58vw,520px);padding:6px 8px;border:1px solid #ddd;border-radius:6px">
            <button class="button copy-share" style="padding:6px 10px;border:1px solid #222;border-radius:6px;background:#111;color:#fff;cursor:pointer">
              Másolás
            </button>
          </div>
        </div>
      </div>

      <div class="card" style="padding:12px;border:1px solid #e6e8ea;border-radius:10px">
        <div style="margin-bottom:8px">
          <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> |
          <b>Státusz:</b> <?php echo esc_html($a['status']); ?>
        </div>
        <?php if (!$rows): ?>
          <div style="color:#666">Még nincs aktivitás ehhez az ajánlókódhoz.</div>
        <?php else:
          $me = $rows[0]; // filterAmb miatt egyetlen sor
          $donation = $me['commission'] * 0.5; // 50% adomány szabály – projekt fix【7: Business/data rules】 
        ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <div style="background:#fafafa;border:1px solid #eee;border-radius:10px;padding:12px">
              <div style="opacity:.7;font-size:12px">Hozott vásárlások</div>
              <div style="font-size:24px;font-weight:800"><?php echo number_format($me['orders'],0,',',' '); ?></div>
            </div>
            <div style="background:#fafafa;border:1px solid #eee;border-radius:10px;padding:12px">
              <div style="opacity:.7;font-size:12px">Összes jutalék (EUR)</div>
              <div style="font-size:24px;font-weight:800"><?php echo $eur($me['commission']); ?></div>
              <div style="opacity:.7;font-size:12px;margin-top:2px">≈ <?php echo esc_html($huf($me['commission'],$rate)); ?></div>
            </div>
            <div style="background:#fafafa;border:1px solid #eee;border-radius:10px;padding:12px">
              <div style="opacity:.7;font-size:12px">Becsült adomány (50%)</div>
              <div style="font-size:24px;font-weight:800"><?php echo $eur($donation); ?></div>
              <div style="opacity:.7;font-size:12px;margin-top:2px">≈ <?php echo esc_html($huf($donation,$rate)); ?></div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:12px">
            <div>
              <h4 style="margin:0 0 8px 0">Top NGO-k (a te linkeden)</h4>
              <div style="border:1px solid #eee;border-radius:10px;overflow:hidden">
                <table style="width:100%;border-collapse:separate;border-spacing:0">
                  <thead><tr style="background:#f6f7f8">
                    <th style="text-align:left;padding:8px 10px">Szervezet</th>
                    <th style="text-align:right;padding:8px 10px">Jutalék</th>
                  </tr></thead>
                  <tbody>
                  <?php $i=0; foreach ($me['ngos'] as $ngo=>$amt): $i++; if ($i>10) break; ?>
                    <tr>
                      <td style="padding:8px 10px"><?php echo esc_html($ngo); ?></td>
                      <td style="padding:8px 10px;text-align:right"><?php echo $eur($amt); ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div>
              <h4 style="margin:0 0 8px 0">Top webshopok (a te linkeden)</h4>
              <div style="border:1px solid #eee;border-radius:10px;overflow:hidden">
                <table style="width:100%;border-collapse:separate;border-spacing:0">
                  <thead><tr style="background:#f6f7f8">
                    <th style="text-align:left;padding:8px 10px">Webshop</th>
                    <th style="text-align:right;padding:8px 10px">Jutalék</th>
                  </tr></thead>
                  <tbody>
                  <?php $i=0; foreach ($me['shops'] as $shop=>$amt): $i++; if ($i>10) break; ?>
                    <tr>
                      <td style="padding:8px 10px"><?php echo esc_html($shop); ?></td>
                      <td style="padding:8px 10px;text-align:right"><?php echo $eur($amt); ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="card" style="padding:12px;border:1px solid #e6e8ea;border-radius:10px">
        <h4 style="margin:0 0 8px 0">Közösségi toplista (összes ajánló – ugyanebben az időszakban)</h4>
        <?php if (!$lb): ?>
          <div style="color:#666">Még üres a toplista.</div>
        <?php else: ?>
          <ol style="margin:0;padding-left:18px">
            <?php foreach ($lb as $i=>$row): ?>
              <li style="margin:4px 0;display:flex;justify-content:space-between;gap:12px">
                <span><?php echo esc_html($row['amb']); ?></span>
                <b><?php echo $eur($row['commission']); ?></b>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
        <div style="opacity:.6;font-size:12px;margin-top:6px">Forrás: Dognet RAW TRANSACTIONS (Publisher API):contentReference[oaicite:7]{index=7} · Generálva: <?php echo esc_html($data['meta']['generated_at']); ?></div>
      </div>
    </div>
    <script>
      (function(){
        var btn = document.currentScript.previousElementSibling.querySelector('.copy-share');
        if(!btn) return;
        btn.addEventListener('click', function(){
          var input = btn.parentNode.querySelector('input[type="text"]');
          if(!input) return;
          input.select(); input.setSelectionRange(0, 99999);
          try { document.execCommand('copy'); btn.textContent = 'Másolva ✓'; setTimeout(function(){ btn.textContent='Másolás'; }, 1500); } catch(e){}
        });
      })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_referral_hub','impact_referral_hub_shortcode');
}

/* ==================== Megjegyzések ====================
 - A Dognet API „raw-transactions/filter” végpontja adja a szükséges adatokat; a 24h-s token login és Bearer használat a doksi szerint megy:contentReference[oaicite:8]{index=8}.
 - A mostani kódban a last_id görgetés, státusz szűrés, és cache TTL illeszkedik a meglévő riport mechanikához:contentReference[oaicite:9]{index=9}.
*/
Mi legyen a következő lépés?
OG + Twitter Card sablon a személyes megosztáshoz: egy kicsi dinamikus „/share” route, ami az amb-ból képet + címet generál (OG image), hogy a preview személyes legyen („[Pisti] is támogat – csatlakozz!”).
Badge-logika: 1., 10., 25. hozott vásárlásnál jelvény + extra adomány-trigger.
Havi/héti resetelt leaderboard: from/to alapból „aktuális hónap”, a Hubon egy váltó „héten / hónap / összes”.
Ha szeretnéd, adok hozzá egy OG-meta kódsablont és egy pici /?share=amb handler-t, ami az előnézethez generált képet szolgálja (felirat + Sharity logó).
Ezt mondtad:
Ok
A ChatGPT ezt mondta:
Szuper 🙂 akkor most már van egy működő Referral Hub MVP-d, amit be tudsz illeszteni a mostani ImpactShop rendszeredbe.
Ez a verzió:

REST végpontot ad (/wp-json/impactshop/v1/referrals?amb=...)
Rövidkódot ad ([impact_referral_hub amb="auto"]) → ezt be tudod tenni pl. egy új oldalra /impact-hub sluggal.
Mutatja a saját linket, a hozott vásárlások számát, a jutalékot (és abból számolt adományt), valamint a top NGO- és shop-bontásokat.
Ad egy közös toplistát is a legerősebb ajánlókról.
Innen a következő lépések:
OG/Twitter meta sablon → hogy a megosztott link preview is személyre szabott legyen (amb alapján).
Badge rendszer → egyszerű if feltételek alapján: „első vásárlás”, „10 vásárlás” stb.
Időszakos leaderboard → külön tab heti / havi bontásra.
Extra motiváció → pl. ha elér egy küszöböt, a Sharity bedob +5 €-t az NGO-jának.
Ha szeretnéd, a következő körben írok egy OG-meta és preview kép generáló mini handler-t, ami /?share=AMBKOD URL-ből adja az Open Graph adatokat és egy felirat+logó képet (így a Facebook/WhatsApp preview személyes lesz).
Kérsz ilyet most?




A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
