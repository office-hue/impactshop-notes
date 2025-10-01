<?php
/**
 * MU: Impact Compat Pack (safe with the big snippet)
 * - Dognet deeplink guard (Arukereso/Heureka/…)
 * - Fallback shortcodes: impact_ticker / impact_leaderboard / impact_activity
 * - NO-UNKNOWN shortcodes: impactshop_report_no_unknown / impactshop_rows_no_unknown
 *   + impact_leaderboard_no_unknown / impact_activity_no_unknown
 *
 * Ez a csomag NEM módosítja és nem írja felül az összevont snippetet.
 */

if (!defined('ABSPATH')) exit;

/* ------------------------------------------------------------
 * 0) KÖZÖS SEGÉDEK (ütközésbiztos)
 * ----------------------------------------------------------*/

if (!defined('IMPACT_API_BASE_HOST')) define('IMPACT_API_BASE_HOST','https://app.sharity.hu');

if (!function_exists('ims_fetch_json')) {
  function ims_fetch_json($path, $timeout = 15){
    $url = rtrim(IMPACT_API_BASE_HOST,'/').$path;
    $res = wp_remote_get($url, ['timeout'=>$timeout, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($res)) return null;
    $code = wp_remote_retrieve_response_code($res);
    if ($code < 200 || $code >= 300) return null;
    return json_decode(wp_remote_retrieve_body($res), true);
  }
}

if (!function_exists('ims_is_unknown_value')) {
  function ims_is_unknown_value($v){
    $t = trim(strval($v));
    if ($t === '') return true;
    $unk = [
      '(ismeretlen)','(ismeretlen shop)','(nincs d1)','ismeretlen',
    ];
    if (in_array(mb_strtolower($t,'UTF-8'), array_map(fn($x)=>mb_strtolower($x,'UTF-8'), $unk), true)) return true;
    // tisztán numerikus "NGO" is legyen szűrve
    if (preg_match('~^\d+(?:[.,]\d+)?$~', $t)) return true;
    return false;
  }
}

/* ------------------------------------------------------------
 * 1) DOGNET DEEPLINK GUARD (Árukereső + társai)
 *    Semmit nem ír felül: csak a wp_redirect-re teszünk szűrőt.
 * ----------------------------------------------------------*/

function ims_get_bad_deeplink_hosts(){
  $list = [
    'arukereso.hu','arukereso.sk','arukereso.cz',
    'heureka.sk','heureka.cz',
    'pazaruvaj.com','ceneo.pl','prisjakt.nu','prisjakt.no','pricerunner.com'
  ];
  return apply_filters('ims_bad_deeplink_hosts', $list);
}

if (!function_exists('ims_deeplink_normalize')) {
  function ims_deeplink_normalize($u){
    if (!$u) return $u;
    $u = html_entity_decode($u, ENT_QUOTES, 'UTF-8');
    $u = str_replace(['&amp;','amp%3B','%26amp%3B'], '&', $u);
    if (preg_match('~^[A-Za-z0-9+/]+={0,2}$~', $u)) {
      $tmp = base64_decode($u, true);
      if ($tmp !== false && preg_match('~^https?://~i',$tmp)) $u = $tmp;
    }
    $u = preg_replace_callback('~%25([0-9A-F]{2})~i', fn($m)=>'%'.$m[1], $u);
    return $u;
  }
}

function ims_dognet_deeplink_guard($location, $status){
  if (is_admin()) return $location;

  $host = parse_url($location, PHP_URL_HOST);
  if (!$host) return $location;

  // Dognet domain?
  $is_dognet = (stripos($host,'dognet') !== false);
  if (!$is_dognet) return $location;

  $query = parse_url($location, PHP_URL_QUERY);
  if (!$query) return $location;

  parse_str($query, $qs);
  if (!$qs) return $location;

  $dlKey = '';
  foreach (['url','custom_url','deeplink','u'] as $k) {
    if (!empty($qs[$k]) && is_string($qs[$k])) { $dlKey=$k; break; }
  }
  if ($dlKey==='') return $location;

  $dl = ims_deeplink_normalize($qs[$dlKey]);
  $dhost = parse_url($dl, PHP_URL_HOST);
  if (!$dhost) return $location;

  foreach (ims_get_bad_deeplink_hosts() as $bad) {
    if (strcasecmp($dhost,$bad)===0 || preg_match('~\.'.preg_quote($bad,'~').'$~i',$dhost)) {
      unset($qs[$dlKey]); // dobjuk a deeplinket → kampány alap URL
      $scheme = parse_url($location, PHP_URL_SCHEME);
      $path   = parse_url($location, PHP_URL_PATH);
      $frag   = parse_url($location, PHP_URL_FRAGMENT);
      return ($scheme?$scheme.'://':'').$host.($path?:'').($qs?('?'.http_build_query($qs)):'').($frag?'#'.$frag:'');
    }
  }
  return $location;
}
add_filter('wp_redirect','ims_dognet_deeplink_guard',10,2);

/* ------------------------------------------------------------
 * 2) FALLBACK RÖVIDKÓDOK – CSAK HA MÁSHOL NINCSENEK
 * ----------------------------------------------------------*/
add_action('init', function(){
  if (function_exists('ims_ticker')      && !shortcode_exists('impact_ticker'))      add_shortcode('impact_ticker','ims_ticker');
  if (function_exists('ims_leaderboard') && !shortcode_exists('impact_leaderboard')) add_shortcode('impact_leaderboard','ims_leaderboard');
  if (function_exists('ims_activity')    && !shortcode_exists('impact_activity'))    add_shortcode('impact_activity','ims_activity');

  if (!shortcode_exists('impact_ticker')) {
    add_shortcode('impact_ticker', function(){
      $j = ims_fetch_json('/wp-json/impact/v1/ticker');
      $fmt = fn($n)=>number_format((float)$n,2,',',' ').' €';
      if (!$j || !isset($j['total'])) return '<div class="kpis card"><div class="kpi"><div class="label">Összegyűjtve</div><div class="value">—</div></div><div class="kpi"><div class="label">Ma</div><div class="value">—</div></div></div>';
      return '<div class="kpis card">'
           .   '<div class="kpi"><div class="label">Összegyűjtve</div><div class="value">'.$fmt($j['total']).'</div><div class="sub">Jóváhagyott adomány</div></div>'
           .   '<div class="kpi"><div class="label">Ma</div><div class="value">'.$fmt($j['today'] ?? 0).'</div><div class="sub">Mai adomány</div></div>'
           . '</div>';
    });
  }

  if (!shortcode_exists('impact_leaderboard')) {
    add_shortcode('impact_leaderboard', function($atts){
      $a = shortcode_atts(['tab'=>'ngo'], $atts);
      $tab = ($a['tab']==='shop') ? 'shop' : 'ngo';
      $j = ims_fetch_json('/wp-json/impact/v1/leaderboard?tab='.$tab);
      if (!$j || !is_array($j) || !count($j)) return '<div class="card" style="padding:12px">Nincs adat.</div>';
      $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
      foreach ($j as $i=>$row) {
        $name = esc_html($row['name'] ?? '—');
        $amt  = number_format((float)($row['amount'] ?? 0), 2, ',', ' ') . ' €';
        $out .= '<li style="display:flex;gap:8px;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">'.
                '<span style="opacity:.7">'.($i+1).'.</span><span style="flex:1">'.$name.'</span><strong>'.$amt.'</strong></li>';
      }
      return $out.'</ul></div>';
    });
  }

  if (!shortcode_exists('impact_activity')) {
    add_shortcode('impact_activity', function(){
      $j = ims_fetch_json('/wp-json/impact/v1/activity');
      if (!$j || !is_array($j) || !count($j)) return '<div class="card" style="padding:12px">Még nincs friss aktivitás.</div>';
      $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
      foreach ($j as $row) {
        $txt = esc_html($row['text'] ?? ''); if ($txt==='') continue;
        $out .= '<li style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,.08)">'.$txt.'</li>';
      }
      return $out.'</ul></div>';
    });
  }
});

/* ------------------------------------------------------------
 * 3) NO-UNKNOWN RÖVIDKÓDOK
 *    (A nagy snippet meglévő függvényeire támaszkodunk, de nem írjuk felül őket.)
 * ----------------------------------------------------------*/

/**
 * Report – ismeretlen(ek) kizárva
 * Paraméterek: from, to, status, group (shop_ngo|ngo|shop), ngo (opcionális)
 */
add_shortcode('impactshop_report_no_unknown', function($atts){
  $a = shortcode_atts([
    'from'=>date('Y-m-01'),
    'to'  =>date('Y-m-d'),
    'status'=>'approved',
    'group'=>'shop_ngo',
    'ngo'  =>'',
  ], $atts, 'impactshop_report_no_unknown');

  if (!function_exists('impactshop_aggregate_conversions'))
    return '<div style="color:#b00">Hiányzó függvény: impactshop_aggregate_conversions</div>';

  $data = impactshop_aggregate_conversions($a['from'],$a['to'],$a['status'],$a['group'],$a['ngo']);
  if (!empty($data['meta']['error']))
    return '<div style="color:#b00">Dognet API hiba: '.esc_html($data['meta']['error']).'</div>';

  // Szűrés – távolítsuk el az ismeretleneket nézetfüggően
  $rows = array_values(array_filter($data['rows'], function($r) use ($a){
    if ($a['group']==='ngo')  return !ims_is_unknown_value($r['ngo'] ?? '');
    if ($a['group']==='shop') return !ims_is_unknown_value($r['shop_name'] ?? '');
    // shop_ngo
    return !ims_is_unknown_value($r['ngo'] ?? '') && !ims_is_unknown_value($r['shop_name'] ?? '');
  }));

  // Összesítő újraszámolás
  $grand = ['orders'=>0,'order_value'=>0.0,'commission'=>0.0];
  foreach ($rows as $r){ $grand['orders'] += (int)$r['orders']; $grand['order_value'] += (float)$r['order_value']; $grand['commission'] += (float)$r['commission']; }
  $eur = fn($n)=>number_format((float)$n,2,',',' ').' €';

  ob_start(); ?>
  <div class="impactshop-report" style="font:14px/1.5 system-ui">
    <div style="margin:8px 0 12px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> |
      <b>Bontás:</b> <?php echo esc_html($a['group']); ?>
      <?php if (!empty($a['ngo'])): ?> | <b>NGO:</b> <?php echo esc_html($a['ngo']); ?> <?php endif; ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead>
          <tr style="background:#f6f7f8">
            <?php if ($a['group']==='ngo'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Szervezet (data1)</th>
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
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Szervezet (data1)</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Rendelések</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Kosárérték</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="5" style="padding:10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 8px;color:#666">Nincs adat (ismeretlenek kiszűrve).</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <?php if ($a['group']==='ngo'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['ngo']); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
              <?php elseif ($a['group']==='shop'): ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html(($r['shop_name'] ?? '').' ('.($r['shop_slug'] ?? '').')'); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
              <?php else: ?>
                <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($r['shop_name'] ?? ''); ?></td>
                <td style="padding:8px 10px"><?php echo esc_html($r['ngo'] ?? ''); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo number_format($r['orders'],0,',',' '); ?></td>
                <td style="text-align:right;padding:8px 10px"><?php echo $eur($r['order_value']); ?></td>
                <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($r['commission']); ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot>
          <tr style="background:#fbfbfc">
            <?php if ($a['group']==='ngo' || $a['group']==='shop'): ?>
              <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo number_format($grand['orders'],0,',',' '); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo $eur($grand['order_value']); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo $eur($grand['commission']); ?></th>
            <?php else: ?>
              <th colspan="3" style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 0 8px">Összesen</th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0"><?php echo $eur($grand['order_value']); ?></th>
              <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 0"><?php echo $eur($grand['commission']); ?></th>
            <?php endif; ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <div style="color:#777;margin-top:8px;font-size:12px">Ismeretlen értékek kizárva • Forrás: Dognet API</div>
  </div>
  <?php
  return ob_get_clean();
});

/**
 * Rows – ismeretlen(ek) kizárva
 * Paraméterek: from, to, status
 */
add_shortcode('impactshop_rows_no_unknown', function($atts){
  $a = shortcode_atts([
    'from'=>date('Y-m-01'),
    'to'  =>date('Y-m-d'),
    'status'=>'approved',
  ], $atts, 'impactshop_rows_no_unknown');

  if (!function_exists('dognet_api_list_conversions_all'))
    return '<div style="color:#b00">Hiányzó függvény: dognet_api_list_conversions_all</div>';

  $res = dognet_api_list_conversions_all($a['from'],$a['to'],$a['status'],80,200);
  if (isset($res['error']) && is_wp_error($res['error']))
    return '<div style="color:#b00">Dognet API hiba: '.esc_html($res['error']->get_error_message()).'</div>';
  $items = $res['items'] ?? [];

  $maps = function_exists('impactshop_build_campaign_map') ? impactshop_build_campaign_map() : ['by_cid'=>[]];
  $by_cid = $maps['by_cid'];

  $eur = fn($n)=>number_format((float)$n,2,',',' ').' €';
  $sum=0.0; $rows=0;

  ob_start(); ?>
  <div class="impactshop-rows" style="font:14px/1.5 system-ui">
    <div style="margin:6px 0 10px 0">
      <b>Időszak:</b> <?php echo esc_html($a['from'].' → '.$a['to']); ?> |
      <b>Státusz:</b> <?php echo esc_html($a['status']); ?> |
      <b>Csatorna:</b> <?php echo defined('DOGNET_AD_CHANNEL_ID') ? intval(DOGNET_AD_CHANNEL_ID) : 0; ?>
    </div>
    <div style="overflow:auto">
      <table style="border-collapse:separate;border-spacing:0;width:100%;min-width:680px">
        <thead>
          <tr style="background:#f6f7f8">
            <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:8px 0 0 0">Dátum</th>
            <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Webshop</th>
            <th style="text-align:left;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0">Szervezet (data1)</th>
            <th style="text-align:right;padding:8px 10px;border:1px solid #e6e8ea;border-bottom:0;border-radius:0 8px 0 0">Jutalék</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it):
            // kampány → shop azonosítás
            $cid=0;
            foreach (['campaign_id','campaignId','cid','campaign'] as $k){
              if (isset($it[$k])) { $cid = is_array($it[$k]) ? intval($it[$k]['id'] ?? 0) : intval($it[$k]); break; }
            }
            $shopSlug='(ismeretlen)'; $shopName='(ismeretlen)';
            if ($cid && isset($by_cid[$cid])) { $shopSlug=$by_cid[$cid]['slug']; $shopName=$by_cid[$cid]['name']; }

            // NGO kinyerése – ha van snippetes smart-pick, használjuk
            if (function_exists('sharity_rows_smart_pick_ngo')) {
              $ngo = sharity_rows_smart_pick_ngo($it);
            } else {
              // helyi, egyszerű fallback
              $ngo = '';
              foreach (['d1','data1','ref1','ngo','ngo_name'] as $kk) { if (!empty($it[$kk])) { $ngo=trim((string)$it[$kk]); break; } }
            }

            // ismeretlenek szűrése
            if (ims_is_unknown_value($ngo) || ims_is_unknown_value($shopName)) continue;

            // jutalék
            $comm=0.0;
            foreach (['publisher_commission','commission','payout','publisherPayout','commission_publisher'] as $kk){
              if (isset($it[$kk]) && is_numeric($it[$kk])) { $comm=(float)$it[$kk]; break; }
            }
            $sum += $comm; $rows++;

            // dátum
            $dt='';
            foreach (['created_at','createdAt','created','time','datetime'] as $kk){ if (!empty($it[$kk])) { $dt=(string)$it[$kk]; break; } }
          ?>
            <tr>
              <td style="padding:8px 10px;border-left:1px solid #e6e8ea"><?php echo esc_html($dt ?: '—'); ?></td>
              <td style="padding:8px 10px"><?php echo esc_html($shopName.' ('.$shopSlug.')'); ?></td>
              <td style="padding:8px 10px"><?php echo esc_html($ngo); ?></td>
              <td style="text-align:right;padding:8px 10px;border-right:1px solid #e6e8ea"><?php echo $eur($comm); ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($rows===0): ?>
            <tr><td colspan="4" style="padding:10px;border:1px solid #e6e8ea;border-top:0;border-radius:0 0 8px 8px;color:#666">Nincs adat (ismeretlenek kiszűrve).</td></tr>
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
    <div style="color:#777;margin-top:8px;font-size:12px">Ismeretlen értékek kizárva • Forrás: Dognet API</div>
  </div>
  <?php
  return ob_get_clean();
});

/**
 * Leaderboard – no-unknown
 *   [impact_leaderboard_no_unknown tab="ngo|shop"]
 */
add_shortcode('impact_leaderboard_no_unknown', function($atts){
  $a = shortcode_atts(['tab'=>'ngo'], $atts);
  $tab = ($a['tab']==='shop') ? 'shop' : 'ngo';
  $j = ims_fetch_json('/wp-json/impact/v1/leaderboard?tab='.$tab);
  if (!$j || !is_array($j) || !count($j)) return '<div class="card" style="padding:12px">Nincs adat.</div>';
  $j = array_values(array_filter($j, fn($r)=>!ims_is_unknown_value($r['name'] ?? '')));
  if (!$j) return '<div class="card" style="padding:12px">Nincs adat (ismeretlenek kiszűrve).</div>';

  $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
  foreach ($j as $i=>$row) {
    $name = esc_html($row['name'] ?? '—');
    $amt  = number_format((float)($row['amount'] ?? 0), 2, ',', ' ') . ' €';
    $out .= '<li style="display:flex;gap:8px;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.08)">'.
            '<span style="opacity:.7">'.($i+1).'.</span><span style="flex:1">'.$name.'</span><strong>'.$amt.'</strong></li>';
  }
  return $out.'</ul></div>';
});

/**
 * Activity – no-unknown
 */
add_shortcode('impact_activity_no_unknown', function(){
  $j = ims_fetch_json('/wp-json/impact/v1/activity');
  if (!$j || !is_array($j) || !count($j)) return '<div class="card" style="padding:12px">Még nincs friss aktivitás.</div>';
  $j = array_values(array_filter($j, fn($r)=>!ims_is_unknown_value($r['text'] ?? '')));
  if (!$j) return '<div class="card" style="padding:12px">Még nincs friss aktivitás (ismeretlenek kiszűrve).</div>';
  $out = '<div class="card" style="padding:12px"><ul class="impact-list" style="list-style:none;padding:0;margin:0">';
  foreach ($j as $row) {
    $txt = esc_html($row['text'] ?? ''); if ($txt==='') continue;
    $out .= '<li style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,.08)">'.$txt.'</li>';
  }
  return $out.'</ul></div>';
});