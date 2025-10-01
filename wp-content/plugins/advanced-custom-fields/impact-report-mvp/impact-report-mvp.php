<?php
/**
 * Plugin Name: Impact Report MVP (Sharity)
 * Description: Paraméterezhető riport (shop×NGO / NGO / Shop) – REST + shortcode, 15 perces cache, CSV export. Stabil védősínekkel.
 * Version: 0.3.1
 * Author: Sharity
 */

if (!defined('ABSPATH')) exit;

/* ===== LOCAL CONFIG betöltése (kimenet elnyelése) ===== */
$__impact_local_cfg = WP_CONTENT_DIR . '/impact-bridge-local.php';
if (file_exists($__impact_local_cfg)) {
  ob_start();
  include_once $__impact_local_cfg;
  ob_end_clean();
}

/* ===== Alap konstansok (ütközésmentes) ===== */
if (!defined('DOGNET_API_BASE'))              define('DOGNET_API_BASE', 'https://api.app.dognet.com/api/v1');
if (!defined('DOGNET_TOKEN_TTL'))             define('DOGNET_TOKEN_TTL', 20 * HOUR_IN_SECONDS);
if (!defined('IMPACT_RPT_CACHE_MINUTES'))     define('IMPACT_RPT_CACHE_MINUTES', 15);
if (!defined('IMPACT_BRIDGE_TIMEOUT'))        define('IMPACT_BRIDGE_TIMEOUT', 25);
if (!defined('IMPACT_BRIDGE_USER_AGENT'))     define('IMPACT_BRIDGE_USER_AGENT','SharityImpactBridge/1.0');

/* ===== HTTP + Auth (védetten) ===== */
if (!function_exists('ir_http')):
function ir_http($method, $url, $body=null, $headers=[]) {
  try{
    $args = [
      'method' => $method,
      'timeout'=> IMPACT_BRIDGE_TIMEOUT,
      'headers'=> array_merge(['User-Agent'=>IMPACT_BRIDGE_USER_AGENT, 'Accept'=>'application/json'], $headers),
      'sslverify'=> true, 'redirection'=> 3,
    ];
    if ($body !== null) { $args['headers']['Content-Type']='application/json'; $args['body']=is_string($body)?$body:wp_json_encode($body); }
    $res = wp_remote_request($url,$args);
    if (is_wp_error($res)) return $res;
    $code = wp_remote_retrieve_response_code($res);
    $json = json_decode(wp_remote_retrieve_body($res), true);
    if ($code>=200 && $code<300) return $json;
    return new WP_Error('dognet_http','Dognet API hiba',['code'=>$code,'body'=>$json]);
  } catch (Throwable $e){
    return new WP_Error('http_throw', $e->getMessage());
  }
}
endif;

if (!function_exists('ir_token')):
function ir_token($force=false){
  try{
    $key='impact_rpt_tok_v1';
    if (!$force && ($t=get_transient($key))) return $t;
    $email = defined('DOGNET_LOGIN_EMAIL')    ? DOGNET_LOGIN_EMAIL    : getenv('DOGNET_LOGIN_EMAIL');
    $pass  = defined('DOGNET_LOGIN_PASSWORD') ? DOGNET_LOGIN_PASSWORD : getenv('DOGNET_LOGIN_PASSWORD');
    if (!$email || !$pass) return new WP_Error('cfg','Hiányzik DOGNET_LOGIN_EMAIL / DOGNET_LOGIN_PASSWORD a wp-content/impact-bridge-local.php-ban');
    $auth = ir_http('POST', rtrim(DOGNET_API_BASE,'/').'/auth/login', ['email'=>$email,'password'=>$pass]);
    if (is_wp_error($auth)) return $auth;
    $tok = $auth['token'] ?? ($auth['data']['token'] ?? ($auth['result']['token'] ?? ''));
    if (!$tok) return new WP_Error('auth','Auth sikertelen', ['resp'=>$auth]);
    set_transient($key,$tok, DOGNET_TOKEN_TTL);
    return $tok;
  } catch (Throwable $e){
    return new WP_Error('auth_throw', $e->getMessage());
  }
}
endif;

if (!function_exists('ir_api')):
function ir_api($path,$body=null){
  try{
    $tok = ir_token(false);
    if (is_wp_error($tok)) return $tok;
    $url = (stripos($path,'http')===0)? $path : rtrim(DOGNET_API_BASE,'/').$path;
    $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
    if (is_wp_error($res) && $res->get_error_code()==='dognet_http' && ($res->get_error_data()['code']??0)==401){
      delete_transient('impact_rpt_tok_v1');
      $tok = ir_token(true); if (is_wp_error($tok)) return $tok;
      $res = ir_http($body?'POST':'GET', $url, $body, ['Authorization'=>'Bearer '.$tok,'Content-Type'=>'application/json']);
    }
    return $res;
  } catch (Throwable $e){
    return new WP_Error('api_throw', $e->getMessage());
  }
}
endif;

/* ===== Segédek ===== */
if (!function_exists('ir_num')):
function ir_num($v){ return is_numeric($v)? floatval($v) : 0.0; }
endif;

if (!function_exists('ir_pick_d1')):
function ir_pick_d1($row){
  $cands = [
    'd1','d2','d3','d4','d5',
    'data1','data2','data3','data4','data5',
    'ref1','ref2','ref3',
    'sub_id','sub_id1','sub_id2','subid','subid1','subid2',
    'ngo','ngo_name','campaign_name','note','comment'
  ];
  foreach($cands as $k){
    if (!isset($row[$k]) || $row[$k]==='' || is_array($row[$k])) continue;
    $v = trim((string)$row[$k]);

    // "a=b&ngo=XY" jellegű értékek
    if (strpos($v,'=') !== false && strpos($v,' ') === false) {
      parse_str($v, $qs);
      foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
        if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
      }
    }

    // Teljes URL → query
    if (filter_var($v, FILTER_VALIDATE_URL)) {
      $parts = parse_url($v);
      if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        foreach (['ngo','ngo_name','d1','org','org_name','utm_term'] as $qq) {
          if (!empty($qs[$qq])) { $v = trim((string)$qs[$qq]); break; }
        }
      }
    }

    // Szűrés: üres, szám, JSON-szerű
    if ($v === '' || is_numeric($v)) continue;
    $fv = ltrim($v);
    if ((strlen($fv)>1) && (($fv[0]==='{') || ($fv[0]==='['))) continue;

    return $v;
  }
  return '';
}
endif;

/* ===== Adatok: raw-transactions/filter ===== */
if (!function_exists('ir_fetch_transactions')):
function ir_fetch_transactions($from,$to,$status='approved',$maxBatches=60,$perPage=200){
  try{
    $fromDt = $from.' 00:00:00';
    $toDt   = $to  .' 23:59:59';
    $filter = [
      ['created_at'=>['gte'=>$fromDt]],
      ['created_at'=>['lte'=>$toDt]],
    ];
    if (defined('DOGNET_AD_CHANNEL_ID') && DOGNET_AD_CHANNEL_ID){
      $filter[] = ['ad_channel_id'=> ['eq'=>(int)DOGNET_AD_CHANNEL_ID]];
    }
    $stat = strtolower(trim($status));
    if ($stat==='approved') $filter[]=['rstatus'=>['in'=>['A']]];
    elseif ($stat==='pending') $filter[]=['rstatus'=>['in'=>['P']]];
    elseif ($stat==='rejected') $filter[]=['rstatus'=>['in'=>['D']]];

    $items=[]; $lastId=null;
    for($i=0;$i<$maxBatches;$i++){
      $body=['per-page'=>$perPage,'filter'=>$filter]; if ($lastId!==null) $body['last_id']=intval($lastId);
      $resp = ir_api('/raw-transactions/filter', $body);
      if (is_wp_error($resp)) return $resp;
      $rows = $resp['data'] ?? ($resp['items'] ?? []);
      if (!$rows) break;
      $items = array_merge($items, $rows);
      $lastId = $resp['meta']['last_id'] ?? null;
      if ($lastId===null){
        foreach($rows as $r){ foreach(['id','transaction_id','tid'] as $k){ if(isset($r[$k]) && is_numeric($r[$k])) $lastId=max((int)$lastId,(int)$r[$k]); } }
        if ($lastId===null) break;
      }
    }
    return $items;
  } catch (Throwable $e){
    return new WP_Error('fetch_throw', $e->getMessage());
  }
}
endif;

/* ===== Aggregálás (group: shop_ngo | ngo | shop) ===== */
if (!function_exists('ir_aggregate')):
function ir_aggregate($rows,$group='shop_ngo'){
  $out=[]; $sumBasket=0; $sumComm=0; $sumDon=0;
  foreach($rows as $r){
    $basket = ir_num($r['order_value'] ?? ($r['basket_value'] ?? 0));
    $comm   = ir_num($r['publisher_commission'] ?? ($r['commission'] ?? ($r['payout'] ?? 0)));
    $don    = $comm * 0.5;

    $cid = 0; foreach(['campaign_id','campaignId','cid','campaign'] as $k){
      if(isset($r[$k])){ $v=$r[$k]; $cid=is_array($v)?intval($v['id']??0):intval($v); break; }
    }
    $shop = $cid ? ('cid '.$cid) : '(ismeretlen shop)';
    $ngo  = ir_pick_d1($r) ?: '(ismeretlen NGO)';

    $key = $group==='ngo'  ? $ngo : ($group==='shop' ? $shop : ($shop.' × '.$ngo));

    if (!isset($out[$key])) $out[$key]=['rows'=>0,'basket'=>0.0,'comm'=>0.0,'don'=>0.0];
    $out[$key]['rows']   += 1;
    $out[$key]['basket'] += $basket;
    $out[$key]['comm']   += $comm;
    $out[$key]['don']    += $don;

    $sumBasket += $basket; $sumComm += $comm; $sumDon += $don;
  }
  uasort($out, function($a,$b){
    $d = $b['don'] <=> $a['don'];
    if ($d!==0) return $d;
    return 0;
  });
  return ['groups'=>$out,'totals'=>['rows'=>array_sum(array_column($out,'rows')),'basket'=>$sumBasket,'comm'=>$sumComm,'don'=>$sumDon]];
}
endif;

/* ===== REST: /impact/v1/report ===== */
add_action('rest_api_init', function(){
  register_rest_route('impact/v1','/report', [
    'methods'=>'GET','permission_callback'=>'__return_true',
    'callback'=>function(WP_REST_Request $req){
      try{
        $from  = sanitize_text_field($req->get_param('from') ?: date('Y-m-01'));
        $to    = sanitize_text_field($req->get_param('to')   ?: date('Y-m-d'));
        $stat  = sanitize_text_field($req->get_param('status') ?: 'approved');
        $group = sanitize_text_field($req->get_param('group')  ?: 'shop_ngo');
        $ngo   = sanitize_text_field($req->get_param('ngo') ?: '');

        $cache_key = 'impact_report_v1:'.md5(json_encode([$from,$to,$stat,$group,$ngo]));
        if ($cached = get_transient($cache_key)) return $cached;

        $rows = ir_fetch_transactions($from,$to,$stat);
        if (is_wp_error($rows)) return new WP_Error('dognet_err','Dognet hiba: '.$rows->get_error_message(),['status'=>502]);

        if ($ngo){
          $rows = array_values(array_filter($rows, function($r) use($ngo){
            $d1 = ir_pick_d1($r);
            return $d1 && (sanitize_title($d1) === sanitize_title($ngo));
          }));
        }

        $agg = ir_aggregate($rows,$group);
        set_transient($cache_key, $agg, IMPACT_RPT_CACHE_MINUTES * MINUTE_IN_SECONDS);
        return $agg;
      } catch (Throwable $e){
        return new WP_Error('rpt_throw','Report kivétel: '.$e->getMessage(),['status'=>500]);
      }
    }
  ]);
});

/* ===== Shortcode: [impact_report] ===== */
add_shortcode('impact_report', function($atts){
  try{
    $q = wp_parse_args($_GET, [
      'from'=>date('Y-m-01'),
      'to'=>date('Y-m-d'),
      'status'=>'approved',
      'group'=>'shop_ngo',
      'ngo'=>'',
    ]);
    $api = esc_url_raw( add_query_arg([
      'from'=>$q['from'],'to'=>$q['to'],'status'=>$q['status'],'group'=>$q['group'],'ngo'=>$q['ngo']
    ], rest_url('impact/v1/report')) );

    $res = wp_remote_get($api, ['timeout'=>IMPACT_BRIDGE_TIMEOUT,'sslverify'=>true, 'headers'=>['User-Agent'=>IMPACT_BRIDGE_USER_AGENT]]);
    $body = is_wp_error($res) ? [] : json_decode(wp_remote_retrieve_body($res), true);
    $groups = $body['groups'] ?? [];
    $tot = $body['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

    ob_start(); ?>
    <div class="impact-report-wrap">
      <form class="ir-filters" method="get">
        <label>From <input type="date" name="from" value="<?php echo esc_attr($q['from']); ?>"></label>
        <label>To <input type="date" name="to" value="<?php echo esc_attr($q['to']); ?>"></label>
        <label>Status
          <select name="status">
            <?php foreach(['approved','pending','rejected','all'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php selected($q['status'],$s); ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Group
          <select name="group">
            <option value="shop_ngo" <?php selected($q['group'],'shop_ngo'); ?>>Shop × NGO</option>
            <option value="ngo" <?php selected($q['group'],'ngo'); ?>>NGO</option>
            <option value="shop" <?php selected($q['group'],'shop'); ?>>Shop</option>
          </select>
        </label>
        <label>NGO (slug)
          <input type="text" name="ngo" placeholder="pl. bator-tabor-alapitvany" value="<?php echo esc_attr($q['ngo']); ?>">
        </label>
        <button type="submit">Szűrés</button>
      </form>

      <div class="ir-table-wrap">
        <table class="ir-table">
          <thead>
            <tr>
              <th>Csoport</th>
              <th style="text-align:right">Kosárérték (€)</th>
              <th style="text-align:right">Jutalék (€)</th>
              <th style="text-align:right">Adomány (50%) (€)</th>
              <th style="text-align:right">Darab</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$groups): ?>
            <tr><td colspan="5" style="text-align:center;color:#9CA3AF">Nincs találat a megadott szűrőkre.</td></tr>
          <?php else:
            foreach($groups as $name=>$v): ?>
              <tr>
                <td><?php echo esc_html($name); ?></td>
                <td style="text-align:right"><?php echo number_format((float)$v['basket'], 2, ',', ' '); ?></td>
                <td style="text-align:right"><?php echo number_format((float)$v['comm'],   2, ',', ' '); ?></td>
                <td style="text-align:right"><strong><?php echo number_format((float)$v['don'],    2, ',', ' '); ?></strong></td>
                <td style="text-align:right"><?php echo (int)$v['rows']; ?></td>
              </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr>
              <th>Összesen</th>
              <th style="text-align:right"><?php echo number_format((float)$tot['basket'], 2, ',', ' '); ?></th>
              <th style="text-align:right"><?php echo number_format((float)$tot['comm'],   2, ',', ' '); ?></th>
              <th style="text-align:right"><?php echo number_format((float)$tot['don'],    2, ',', ' '); ?></th>
              <th style="text-align:right"><?php echo (int)$tot['rows']; ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
    <style>
      .impact-report-wrap{background:#111214;border:1px solid #1F2937;border-radius:16px;padding:12px}
      .impact-report-wrap .ir-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;align-items:end}
      .impact-report-wrap .ir-filters label{display:flex;flex-direction:column;font-size:.92rem;color:#E5E7EB}
      .impact-report-wrap .ir-filters input,.impact-report-wrap .ir-filters select{background:#0d0e11;color:#E5E7EB;border:1px solid #1F2937;border-radius:10px;padding:8px}
      .impact-report-wrap .ir-filters button{background:linear-gradient(90deg,#7C3AED,#06B6D4);color:#000;border:0;border-radius:999px;padding:8px 14px;font-weight:800}
      .impact-report-wrap .ir-table{width:100%;border-collapse:separate;border-spacing:0 6px;color:#E5E7EB}
      .impact-report-wrap .ir-table thead th{font-size:.9rem;color:#9CA3AF;border-bottom:1px solid #1F2937;padding:8px}
      .impact-report-wrap .ir-table tbody td{background:#0d0e11;border-top:1px solid #1F2937;border-bottom:1px solid #1F2937;padding:10px}
      .impact-report-wrap .ir-table tbody tr td:first-child{border-left:1px solid #1F2937;border-top-left-radius:10px;border-bottom-left-radius:10px}
      .impact-report-wrap .ir-table tbody tr td:last-child{border-right:1px solid #1F2937;border-top-right-radius:10px;border-bottom-right-radius:10px}
      .impact-report-wrap .ir-table tfoot th{border-top:1px solid #1F2937;padding:10px}
      @media(max-width:900px){.impact-report-wrap .ir-filters{flex-direction:column;align-items:stretch}}
    </style>
    <?php
    return ob_get_clean();
  } catch (Throwable $e){
    return '<div style="background:#3b0f0f;color:#fecaca;padding:12px;border-radius:8px">Riport render hiba: '.esc_html($e->getMessage()).'</div>';
  }
});

/* ===== CSV EXPORT + CACHE FLUSH – csak a MI végpontunkra ===== */
add_filter('rest_pre_echo_response', function($result, $server, $request){
  try{
    $route = method_exists($request,'get_route') ? $request->get_route() : '';
    if ($route !== '/impact/v1/report') return $result;

    // Cache flush (?impact_flush=1)
    if (isset($_GET['impact_flush']) && $_GET['impact_flush']=='1'){
      global $wpdb;
      if (isset($wpdb->options)) {
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_impact_report_v1:%' OR option_name LIKE '_transient_timeout_impact_report_v1:%'");
      }
    }

    // CSV export (&format=csv)
    if (strtolower((string)$request->get_param('format')) === 'csv'){
      $data = is_array($result) ? $result : [];
      $groups = $data['groups'] ?? [];
      $totals = $data['totals'] ?? ['rows'=>0,'basket'=>0,'comm'=>0,'don'=>0];

      $out = fopen('php://temp', 'r+');
      fputcsv($out, ['Csoport','Kosárérték (€)','Jutalék (€)','Adomány (50%) (€)','Darab'], ';');
      foreach($groups as $name=>$v){
        fputcsv($out, [
          $name,
          number_format((float)($v['basket'] ?? 0), 2, ',', ' '),
          number_format((float)($v['comm']   ?? 0), 2, ',', ' '),
          number_format((float)($v['don']    ?? 0), 2, ',', ' '),
          (int)($v['rows'] ?? 0),
        ], ';');
      }
      fputcsv($out, ['Összesen',
        number_format((float)($totals['basket'] ?? 0), 2, ',', ' '),
        number_format((float)($totals['comm']   ?? 0), 2, ',', ' '),
        number_format((float)($totals['don']    ?? 0), 2, ',', ' '),
        (int)($totals['rows'] ?? 0)
      ], ';');
      rewind($out);
      $csv = stream_get_contents($out);

      if (class_exists('WP_REST_Response')) {
        $resp = new WP_REST_Response($csv, 200);
        $resp->header('Content-Type', 'text/csv; charset=utf-8');
        $resp->header('Content-Disposition', 'attachment; filename="impact-report.csv"');
        return $resp;
      } else {
        // extrém eset: nincs REST osztály – vissza sima string
        return $csv;
      }
    }
    return $result;
  } catch (Throwable $e){
    // sose dőljön el REST miatt
    return $result;
  }
}, 10, 3);

/* ===== UI gombok – ne fussanak Elementor editorban ===== */
add_action('wp_footer', function(){
  if (is_admin()) return;
  if (defined('REST_REQUEST') && REST_REQUEST) return;
  if (wp_doing_ajax()) return;

  // Elementor editor / preview guard
  if (defined('ELEMENTOR_VERSION')) {
    try {
      if (class_exists('\Elementor\Plugin')) {
        $E = \Elementor\Plugin::$instance;
        if ( ($E->editor && $E->editor->is_edit_mode())
          || ($E->preview && method_exists($E->preview,'is_preview_mode') && $E->preview->is_preview_mode()) ) {
          return;
        }
      }
    } catch (Throwable $e) { return; }
  }

  if (!is_singular()) return; ?>
  <script>
  (function(){
    const wrap = document.querySelector('.impact-report-wrap');
    if(!wrap) return;
    function qs(name, def=''){ const u = new URL(window.location.href); return u.searchParams.get(name) || def; }
    const params = new URLSearchParams({
      from: qs('from','<?php echo esc_js(date('Y-m-01')); ?>'),
      to: qs('to','<?php echo esc_js(date('Y-m-d')); ?>'),
      status: qs('status','approved'),
      group: qs('group','shop_ngo'),
      ngo: qs('ngo',''),
      format: 'csv'
    });
    const exportUrl = '<?php echo esc_url_raw( rest_url('impact/v1/report') ); ?>' + '?' + params.toString();

    const bar = document.createElement('div');
    bar.style.display='flex'; bar.style.gap='10px'; bar.style.marginTop='10px';

    const btnExport = document.createElement('a');
    btnExport.textContent = 'Export CSV';
    btnExport.href = exportUrl;
    btnExport.style.background='linear-gradient(90deg,#7C3AED,#06B6D4)';
    btnExport.style.color='#000'; btnExport.style.padding='8px 14px';
    btnExport.style.borderRadius='999px'; btnExport.style.fontWeight='800'; btnExport.style.textDecoration='none';

    const btnFlush = document.createElement('a');
    const u = new URL(window.location.href);
    u.searchParams.set('impact_flush','1');
    btnFlush.textContent = 'Cache frissítése';
    btnFlush.href = u.toString();
    btnFlush.style.background='#0d0e11'; btnFlush.style.color='#E5E7EB';
    btnFlush.style.padding='8px 14px'; btnFlush.style.borderRadius='999px';
    btnFlush.style.border='1px solid #1F2937'; btnFlush.style.textDecoration='none';

    bar.append(btnExport, btnFlush);
    wrap.append(bar);
  })();
  </script>
<?php
});