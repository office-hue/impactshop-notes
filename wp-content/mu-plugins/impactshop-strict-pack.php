<?php
/**
 * ImpactShop – STRICT PACK (MU)
 * Zárt logika, ütközésmentes: ad_channel=26081 (ha nincs máshol definiálva),
 * státusz = approved + pending, CSAK data1-es sorok.
 * Shortcode-ok:
 *   [impact_ticker_strict]
 *   [impact_activity_strict]
 *   [impactshop_rows_strict from="YYYY-MM-DD" to="YYYY-MM-DD"]
 *
 * FONTOS: a data1-t NEM írjuk felül. Ha nincs, először ibl__pick_d1(),
 *         ha az sincs, csak akkor próbálunk default_d1-et a Shops-ból (helper).
 */

if (!defined('IMPACTSHOP_STRICT_CHANNEL')) define('IMPACTSHOP_STRICT_CHANNEL', 26081);
if (!defined('DOGNET_AD_CHANNEL_ID'))      define('DOGNET_AD_CHANNEL_ID', IMPACTSHOP_STRICT_CHANNEL);

/* ------------------- Segéd: NGO (data1) felkutatása ------------------- */
if (!function_exists('ims_pick_d1')) {
  function ims_pick_d1(array $row): string {
    // 1) ha a sorban van név szerint, azt használjuk (Fillout-ból érkező d1-et sosem írjuk felül)
    foreach (['data1','d1','ngo','ngo_slug','ngo_name'] as $k) {
      if (!empty($row[$k]) && is_string($row[$k])) {
        $v = trim((string)$row[$k]);
        if ($v !== '' && strtolower($v) !== '(ismeretlen)' && !is_numeric($v)) return $v;
      }
    }
    // 2) Bridge Local segéd – több-mezős okos kinyerés (URL/query/kv-pár): ez a fő
    if (function_exists('ibl__pick_d1')) {
      $v = trim((string)ibl__pick_d1($row));
      if ($v !== '' && strtolower($v) !== '(ismeretlen)' && !is_numeric($v)) return $v;
    }
    // 3) fallback: Shops default_d1 (ha van ilyen helper a rendszerben)
    $shopSlug = '';
    foreach (['shop_slug','shop','shop_name','program'] as $k) {
      if (!empty($row[$k]) && is_string($row[$k])) { $shopSlug = sanitize_title($row[$k]); break; }
    }
    // több néven is megpróbáljuk – bármelyik létezhet
    $candFns = [
      'sharity_default_d1_for_shop',
      'impactshop_default_d1_for_shop',
      'impact_get_default_d1_for_shop',
    ];
    foreach ($candFns as $fn) {
      if (function_exists($fn)) {
        try {
          $v = trim((string)call_user_func($fn, $shopSlug, $row));
          if ($v !== '' && strtolower($v) !== '(ismeretlen)' && !is_numeric($v)) return $v;
        } catch (\Throwable $e) {}
      }
    }
    // 4) nincs data1 → üres (a STRICT logika ilyen sorokat kihagy)
    return '';
  }
}

/* ------------------- Közös fetch: Bridge Local / Report MVP ------------------- */
function ims_strict_fetch($args){
  $defaults = [
    'from'       => date('Y-m-01'),
    'to'         => date('Y-m-d'),
    'only_d1'    => true,
    'limit'      => 2000,
  ];
  $a = array_merge($defaults, $args ?: []);

  $rows = [];

  // 1) Bridge Local natív – ez nálad elérhető (ibl_fetch_transactions)
  if (function_exists('ibl_fetch_transactions')) {
    try {
      // Bridge-ben a státusz "all" = A+P (D eleve kizárva),
      // csatorna-szűrést a DOGNET_AD_CHANNEL_ID konstans biztosítja (feljebb definiáltuk)
      $res = ibl_fetch_transactions($a['from'], $a['to'], 'all', 120, 250);
      $rows = is_wp_error($res) ? [] : (array)$res;
    } catch (\Throwable $e) { $rows = []; }
  }
  // 2) Report MVP nyers query – ha netán az érhető el
  elseif (function_exists('impactshop_report_query')) {
    try {
      $rows = impactshop_report_query([
        'from'       => $a['from'],
        'to'         => $a['to'],
        'ad_channel' => (string)IMPACTSHOP_STRICT_CHANNEL,
        'raw'        => true,
        'limit'      => (int)$a['limit'],
      ]);
      $rows = is_array($rows) ? $rows : [];
    } catch (\Throwable $e) { $rows = []; }
  }
  else {
    return ['rows'=>[], 'error'=>'STRICT: nincs elérhető backend (Bridge Local / Report MVP).'];
  }

  // Normalizálás + szűrés: csak A/P és csak data1-es
  $out = [];
  foreach ($rows as $r) {
    $status = strtolower(trim($r['status'] ?? $r['rstatus'] ?? ''));
    if ($status !== 'approved' && $status !== 'pending') continue;

    $d1 = ims_pick_d1($r);
    if ($d1 === '') continue;  // csak data1-es sor

    $comm = (float)str_replace([',','€',' '], ['.','',''], (string)($r['publisher_commission'] ?? $r['commission'] ?? $r['payout'] ?? 0));
    $dt   = (string)($r['created_at'] ?? $r['created'] ?? $r['date'] ?? '');

    $out[] = [
      'datetime'   => $dt,
      'shop'       => (string)($r['shop_name'] ?? $r['shop'] ?? $r['program'] ?? ''),
      'ngo'        => $d1,
      'status'     => $status,
      'commission' => $comm,
    ];
  }

  // Legújabb elöl
  usort($out, fn($a,$b)=> strcmp($b['datetime'], $a['datetime']));
  return ['rows'=>$out, 'error'=>null];
}

/* ------------------- Adomány képlet ------------------- */
function ims_donation_eur($c){ return round(0.5 * (float)$c, 2); }

/* ------------------- Shortcode: TICKER (hónap + ma) ------------------- */
add_shortcode('impact_ticker_strict', function(){
  $res = ims_strict_fetch(['from'=>date('Y-m-01'),'to'=>date('Y-m-d')]);
  if ($res['error']) return '<div>STRICT ticker hiba: '.esc_html($res['error']).'</div>';
  $total=0; $today=0; $ymd=date('Y-m-d');
  foreach($res['rows'] as $r){
    $d = ims_donation_eur($r['commission']); $total += $d;
    if (substr($r['datetime'],0,10)===$ymd) $today += $d;
  }
  return '<style>.ims-ticker{display:flex;gap:12px}.ims-card{flex:1;padding:14px;border-radius:12px;background:linear-gradient(90deg,#e7f0ff,#f4eefe)}.ims-card .k{opacity:.7;font-size:.9rem;margin-bottom:4px}.ims-card .v{font-size:1.6rem;font-weight:700}</style>'
    .'<div class="ims-ticker"><div class="ims-card"><div class="k">Összegyűjtve</div><div class="v">€ '.number_format($total,2,',',' ').'</div></div>'
    .'<div class="ims-card"><div class="k">Ma</div><div class="v">€ '.number_format($today,2,',',' ').'</div></div></div>';
});

/* ------------------- Shortcode: ACTIVITY (14 nap, max 10) ------------------- */
add_shortcode('impact_activity_strict', function(){
  $res = ims_strict_fetch(['from'=>date('Y-m-d', strtotime('-13 days')),'to'=>date('Y-m-d'),'limit'=>300]);
  if ($res['error']) return '<div>STRICT activity hiba: '.esc_html($res['error']).'</div>';
  $rows = array_slice($res['rows'], 0, 10);
  if (!$rows) return '<div>Még nincsenek friss aktivitások.</div>';
  $html=''; foreach($rows as $r){
    $html .= '<li><strong>'.esc_html($r['shop']).'</strong> → '.esc_html($r['ngo']).' • '
          . esc_html(substr($r['datetime'],0,16)).' • € '.number_format(ims_donation_eur($r['commission']),2,',',' ')
          . ' <span style="opacity:.6">'.esc_html($r['status']).'</span></li>';
  }
  return '<ul style="line-height:1.4;padding-left:18px">'.$html.'</ul>';
});

/* ------------------- Shortcode: ROWS (diagnosztika) ------------------- */
add_shortcode('impactshop_rows_strict', function($atts){
  $a = shortcode_atts(['from'=>date('Y-m-01'),'to'=>date('Y-m-d')], $atts);
  $res = ims_strict_fetch(['from'=>$a['from'],'to'=>$a['to'],'limit'=>2000]);
  if ($res['error']) return '<div>STRICT rows hiba: '.esc_html($res['error']).'</div>';
  $rows=$res['rows']; $sum=0; foreach($rows as $r){ $sum += ims_donation_eur($r['commission']); }
  $trs=''; foreach($rows as $r){
    $trs.='<tr><td>'.esc_html($r['datetime']).'</td>'
        .'<td>'.esc_html($r['shop']).'</td>'
        .'<td>'.esc_html($r['ngo']).'</td>'
        .'<td>'.esc_html($r['status']).'</td>'
        .'<td style="text-align:right">€ '.number_format((float)$r['commission'],2,',',' ').'</td>'
        .'<td style="text-align:right">€ '.number_format(ims_donation_eur($r['commission']),2,',',' ').'</td></tr>';
  }
  $trs.='<tr><th colspan="5" style="text-align:right">Összesen</th><th style="text-align:right">€ '.number_format($sum,2,',',' ').'</th></tr>';
  return '<style>.ims-table{width:100%;border-collapse:collapse}.ims-table th,.ims-table td{border:1px solid #eee;padding:6px}.ims-table th{background:#fafafa;text-align:left}</style>'
       . '<table class="ims-table"><tr><th>Dátum</th><th>Webshop</th><th>Szervezet (data1)</th><th>Státusz</th><th>Jutalék</th><th>Adomány (50%)</th></tr>'.$trs.'</table>';
});