<?php
/**
 * MU: Sharity Impact – compat pack (no conflicts)
 * - Árukereső deeplink védelem: Árukereső hostnál nem küld deeplinket (Custom URL host error fix)
 * - u param tisztítás: entity -> &, base64 kibontás, %25XX -> %XX, zajos affil paramok dobása
 * - Leaderboard shop-név "szépítés" (cid/slug -> emberi név)
 * - "no unknown" rövidkódok: impact_leaderboard_no_unknown, impact_report_no_unknown, impactshop_rows_no_unknown
 *
 * Nem nyúl az összevont snippethez; csak kiegészít és elő-tisztít.
 */

if (!defined('ABSPATH')) { exit; }

/* ------------------ ÁLTALÁNOS SEGÉDEK ------------------ */

/** slug-szerű? */
if (!function_exists('ims2_is_slug_like')) {
  function ims2_is_slug_like($v){
    $v = (string)$v;
    return (bool)(preg_match('~^[a-z0-9._-]{3,}$~i', $v) && preg_match('~[a-z]~i', $v));
  }
}

/** "ismeretlen" detektálás reportoknál/listáknál */
if (!function_exists('ims2_is_unknown_value')) {
  function ims2_is_unknown_value($v){
    $t = trim(mb_strtolower((string)$v, 'UTF-8'));
    return ($t==='' || $t==='(ismeretlen)' || $t==='(ismeretlen shop)' || $t==='(nincs d1)');
  }
}

/** Shop-név normalizáló: "ad cid 987" vagy "987" -> kampányból név, slug -> név */
if (!function_exists('ims2_pretty_shop_name')) {
  function ims2_pretty_shop_name($raw){
    $name = trim((string)$raw);
    if ($name === '') return $name;

    // A) "ad cid 987" vagy csak szám
    if (preg_match('~^ad\s*cid\s*(\d+)$~i', $name, $m) || preg_match('~^\d+$~', $name, $m)) {
      $cid = intval($m[1] ?? $name);
      if ($cid && function_exists('impactshop_build_campaign_map')) {
        $maps = impactshop_build_campaign_map();
        if (!empty($maps['by_cid'][$cid]['name'])) return $maps['by_cid'][$cid]['name'];
      }
    }

    // B) slug -> név
    if (ims2_is_slug_like($name) && function_exists('impactshop_get_shops')) {
      foreach (impactshop_get_shops() as $s) {
        if (strcasecmp($s['shop_slug'] ?? '', $name) === 0) return $s['name'] ?? $name;
      }
    }

    return $name;
  }
}

/* ------------------ DEEPLINK TISZTÍTÁS + ÁRUKERESŐ FIX ------------------ */

/** u param tisztítás (nem ír vissza az adatbázisba – csak futásidőben a kérést rendezzük) */
if (!function_exists('ims2_clean_deeplink')) {
  function ims2_clean_deeplink($u){
    if (!$u) return $u;

    // 1) HTML entity -> &
    $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
    $u = str_replace(['&amp;','amp%3B','%26amp%3B'], '&', $u);

    // 2) base64-gyanú: ha kibomlik és http(s), használjuk azt
    if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $u)) {
      $tmp = base64_decode($u, true);
      if ($tmp !== false && preg_match('~^https?://~i', $tmp)) $u = $tmp;
    }

    // 3) dupla %-kódolás visszafejtése
    $u = preg_replace_callback('~%25([0-9A-F]{2})~i', fn($m)=>'%'.$m[1], $u);

    // 4) zajos affiliate paramok kidobása
    $kill = ['a_bid','a_aid','a_cid','chan','data1','ref','refid','utm_term','utm_medium','utm_source'];
    $p = parse_url($u);
    if (!empty($p['query'])) {
      parse_str($p['query'], $qs);
      foreach($kill as $k){ unset($qs[$k]); }
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
 * Árukereső védelem:
 * Ha az érkező deeplink hostja Árukereső (arukereso.hu / .sk / .cz, stb.), akkor TILTSUK le a deeplink továbbítását,
 * mert a Dognet sok kampánynál nem enged "custom URL host"-ot -> "Custom URL host does not match..." hiba.
 *
 * Megoldás: go-deal alatt a $_GET['u']-t kitisztítjuk, és ha Árukereső host, akkor teljesen KIÜRÍTJÜK,
 * így a snippet a kampány BASE linkjét használja d1-gyel (ez a biztos).
 *
 * Fontos: csak futásidőben módosítjuk a szuperglobálist; nem írunk át más plugint/kódot.
 */
add_action('init', function(){
  // csak fronton érdekes
  if (is_admin()) return;

  // csak akkor piszkáljunk bele, ha a snippet deal-útvonala érkezhet
  // (ezek query varok lesznek a snippetben; itt GET alapján döntünk)
  if (!isset($_GET['impactshop_deal']) && stripos($_SERVER['REQUEST_URI'] ?? '', '/go-deal') === false) {
    // nem deal – azért az 'u' paramot akkor is érdemes tisztítani, ha van
    if (isset($_GET['u']) && is_string($_GET['u'])) {
      $_GET['u'] = ims2_clean_deeplink($_GET['u']);
    }
    return;
  }

  if (!isset($_GET['u']) || !is_string($_GET['u'])) return;

  $clean = ims2_clean_deeplink($_GET['u']);
  $host  = parse_url($clean, PHP_URL_HOST);
  $is_arukereso = false;
  if ($host) {
    $h = mb_strtolower($host, 'UTF-8');
    // több ország: arukereso.hu / .sk / .cz / .ro stb. + aldomének
    $is_arukereso = (bool)preg_match('~(^|\.)arukereso\.[a-z.]+$~i', $h);
  }

  if ($is_arukereso) {
    // kulcs: blankoljuk – a snippet így nem küld deeplinket, csak BASE linket generál d1-gyel → nincs Dognet hiba
    $_GET['u'] = '';
  } else {
    $_GET['u'] = $clean;
  }
}, 1); // nagyon korán fusson, mielőtt a snippet olvasná az inputot


/* ------------------ RÖVIDKÓDOK: FALLBACK + NO UNKNOWN ------------------ */

/**
 * Ha a mini-shortcodes/legacy nincs betöltve, adjunk fallbackot.
 * Ha betöltve van, NEM írjuk felül – csak a "no_unknown" extra kódokat regisztráljuk.
 */

/* Ticker/Leaderboard/Activity aliasok, ha esetleg a mini plugin ims_* neveivel érkeznek */
add_action('init', function(){
  if (function_exists('ims_ticker')      && !shortcode_exists('impact_ticker'))      add_shortcode('impact_ticker','ims_ticker');
  if (function_exists('ims_leaderboard') && !shortcode_exists('impact_leaderboard')) add_shortcode('impact_leaderboard','ims_leaderboard');
  if (function_exists('ims_activity')    && !shortcode_exists('impact_activity'))    add_shortcode('impact_activity','ims_activity');
}, 5);

/* Egyszerű REST fetch helper (lokális Impact Bridge / vagy app.sharity.hu) */
if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST', 'https://app.sharity.hu');
if (!function_exists('ims2_fetch_json')) {
  function ims2_fetch_json($path){
    $url = rtrim(IMPACT_API_BASE_HOST,'/').$path;
    $res = wp_remote_get($url, ['timeout'=>15, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;
    $body = wp_remote_retrieve_body($res);
    return json_decode($body, true);
  }
}

/* impact_leaderboard – FALLBACK csak ha nincs */
if (!shortcode_exists('impact_leaderboard')) {
  add_shortcode('impact_leaderboard', function($atts){
    $a = shortcode_atts(['tab'=>'ngo'], $atts);
    $tab = ($a['tab']==='shop') ? 'shop' : 'ngo';
    $j = ims2_fetch_json('/wp-json/impact/v1/leaderboard?tab='.$tab);
    if (!$j || !is_array($j) || !count($j)) return '<div class="card" style="padding:12px">Nincs adat.</div>';

    $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
    foreach ($j as $i=>$row) {
      $rawName = $row['name'] ?? '—';
      $pretty  = ($tab==='shop') ? ims2_pretty_shop_name($rawName) : $rawName;
      $name = esc_html($pretty);
      $amt  = number_format((float)($row['amount'] ?? 0), 2, ',', ' ') . ' €';
      $out .= '<li style="display:flex;gap:8px;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">'.
              '<span style="opacity:.7">'.($i+1).'.</span><span style="flex:1">'.$name.'</span><strong>'.$amt.'</strong></li>';
    }
    return $out.'</ul></div>';
  });
}

/* impact_leaderboard_no_unknown – MINDIG regisztráljuk (nem ütközik semmivel) */
add_shortcode('impact_leaderboard_no_unknown', function($atts){
  $a = shortcode_atts(['tab'=>'ngo'], $atts);
  $tab = ($a['tab']==='shop') ? 'shop' : 'ngo';
  $j = ims2_fetch_json('/wp-json/impact/v1/leaderboard?tab='.$tab);
  if (!$j || !is_array($j)) $j = [];

  // szűrés
  $filtered = [];
  foreach ($j as $row) {
    $rawName = $row['name'] ?? '';
    $pretty  = ($tab==='shop') ? ims2_pretty_shop_name($rawName) : $rawName;
    if (!ims2_is_unknown_value($pretty) && $pretty !== '') {
      $row['name'] = $pretty;
      $filtered[] = $row;
    }
  }

  if (!$filtered) return '<div class="card" style="padding:12px">Nincs adat.</div>';

  $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
  foreach ($filtered as $i=>$row) {
    $name = esc_html($row['name']);
    $amt  = number_format((float)($row['amount'] ?? 0), 2, ',', ' ') . ' €';
    $out .= '<li style="display:flex;gap:8px;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">'.
            '<span style="opacity:.7">'.($i+1).'.</span><span style="flex:1">'.$name.'</span><strong>'.$amt.'</strong></li>';
  }
  return $out.'</ul></div>';
});

/* impact_report_no_unknown – az összevont snippet reportjára támaszkodik (REST) */
add_shortcode('impact_report_no_unknown', function($atts){
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',
    'group'  => 'shop_ngo', // shop_ngo|ngo|shop
    'ngo'    => '',
  ], $atts);

  // a nagy snippet által regisztrált REST:
  $qs = http_build_query([
    'from'=>$a['from'],'to'=>$a['to'],'status'=>$a['status'],'group'=>$a['group'],'ngo'=>$a['ngo']
  ]);
  $data = ims2_fetch_json('/wp-json/impactshop/v1/totals?'.$qs);
  if (!$data || empty($data['rows'])) return '<div class="card" style="padding:12px">Nincs adat.</div>';

  // szűrés: "ismeretlen" sorok kukázása
  $rows = array_values(array_filter($data['rows'], function($r) use ($a){
    if ($a['group']==='ngo')   return !ims2_is_unknown_value($r['ngo'] ?? '');
    if ($a['group']==='shop')  return !ims2_is_unknown_value($r['shop_name'] ?? '');
    // shop_ngo
    return !ims2_is_unknown_value($r['ngo'] ?? '') && !ims2_is_unknown_value($r['shop_name'] ?? '');
  }));

  // formázás: ugyanaz a tábla, egyszerűsített fejléc + 2 tizedes €
  $eur = function($n){ return number_format((float)$n, 2, ',', ' ') . ' €'; };

  ob_start(); ?>
  <div class="impact-report card" style="padding:12px;font:14px/1.5 system-ui">
    <div style="margin:6px 0 10px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> |
      <b>Bontás:</b> <?php echo esc_html($a['group']); ?>
      <?php if (!empty($a['ngo'])): ?> | <b>NGO:</b> <?php echo esc_html($a['ngo']); ?> <?php endif; ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead><tr style="background:#f6f7f8">
          <?php if ($a['group']==='ngo'): ?>
            <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Szervezet</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
          <?php elseif ($a['group']==='shop'): ?>
            <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Webshop</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
          <?php else: ?>
            <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Webshop</th>
            <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Szervezet</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
          <?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="5" style="padding:10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 8px;color:#666">Nincs adat az adott szűrésre.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <?php if ($a['group']==='ngo'): ?>
              <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['ngo']); ?></td>
              <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
              <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
              <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
            <?php elseif ($a['group']==='shop'): ?>
              <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
              <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
              <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
              <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
            <?php else: ?>
              <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name']); ?></td>
              <td style="padding:8px 10px"><?php echo esc_html($r['ngo']); ?></td>
              <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
              <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
              <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php
  return ob_get_clean();
});

/* impactshop_rows_no_unknown – a nagy snippet fetch-ére építve (raw rows) */
add_shortcode('impactshop_rows_no_unknown', function($atts){
  $a = shortcode_atts([
    'from'   => date('Y-m-01'),
    'to'     => date('Y-m-d'),
    'status' => 'approved',
  ], $atts);

  // teljes lista a meglévő belső függvénnyel
  if (!function_exists('dognet_api_list_conversions_all')) {
    return '<div style="color:#b00">Hiányzó függvény: dognet_api_list_conversions_all</div>';
  }
  $res = dognet_api_list_conversions_all($a['from'], $a['to'], $a['status'], 80, 200);
  if (isset($res['error']) && is_wp_error($res['error'])) {
    return '<div style="color:#b00">Dognet API hiba: '.esc_html($res['error']->get_error_message()).'</div>';
  }
  $items = $res['items'] ?? [];

  // map cid -> shop
  $maps = function_exists('impactshop_build_campaign_map') ? impactshop_build_campaign_map() : ['by_cid'=>[]];
  $by_cid = $maps['by_cid'];

  $eur = function($n){ return number_format((float)$n, 2, ',', ' ') . ' €'; };

  ob_start(); ?>
  <div class="impactshop-rows card" style="padding:12px;font:14px/1.5 system-ui">
    <div style="margin:6px 0 10px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?>  |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead><tr style="background:#f6f7f8">
          <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Dátum</th>
          <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Webshop</th>
          <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Szervezet</th>
          <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
        </tr></thead>
        <tbody>
        <?php
        $sum = 0.0; $rows = 0;
        foreach ($items as $it) {
          // shop
          $cid = 0;
          foreach (['campaign_id','campaignId','cid','campaign'] as $k) {
            if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
          }
          $shopName='(ismeretlen shop)'; $shopSlug='(ismeretlen shop)';
          if ($cid && isset($by_cid[$cid])) { $shopSlug = $by_cid[$cid]['slug']; $shopName = $by_cid[$cid]['name']; }

          // ngo (okos pick – a snippet definícióját használjuk, ha megvan)
          if (function_exists('sharity_rows_smart_pick_ngo')) {
            $ngo = sharity_rows_smart_pick_ngo($it);
          } elseif (function_exists('impactshop_pick_ngo_from_row')) {
            $ngo = impactshop_pick_ngo_from_row($it);
          } else {
            $ngo = trim((string)($it['d1'] ?? $it['data1'] ?? ''));
          }

          // SZŰRÉS: unknown sorok kihagyása
          if (ims2_is_unknown_value($ngo) || ims2_is_unknown_value($shopName)) continue;

          // commission
          $comm = 0.0;
          foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $k) {
            if (isset($it[$k]) && is_numeric($it[$k])) { $comm = (float)$it[$k]; break; }
          }
          $sum += $comm; $rows++;

          // date
          $dt = '';
          foreach (['created_at','createdAt','created','time','datetime'] as $k) {
            if (!empty($it[$k])) { $dt = (string)$it[$k]; break; }
          }
          ?>
          <tr>
            <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($dt ?: '—'); ?></td>
            <td style="padding:8px 10px"><?php echo esc_html($shopName); ?></td>
            <td style="padding:8px 10px"><?php echo esc_html($ngo); ?></td>
            <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($comm); ?></td>
          </tr>
        <?php } ?>
        <?php if ($rows === 0): ?>
          <tr><td colspan="4" style="padding:10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 8px;color:#666">Nincs adat az adott szűrésre.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen (sorok: <?php echo (int)$rows; ?>)</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo $eur($sum); ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <?php
  return ob_get_clean();
});