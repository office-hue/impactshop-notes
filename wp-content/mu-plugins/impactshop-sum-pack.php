<?php
/**
 * Plugin Name: ImpactShop Sum Pack
 * Description: Provides the Impact sum/leader widgets (mini, sticky, diag) without relying on WPCode.
 * Author: ImpactShop
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('IMPACTSHOP_SUM_DEFAULT_FROM')) {
    define('IMPACTSHOP_SUM_DEFAULT_FROM', '2025-10-23');
}
if (!defined('IMPACTSHOP_SUM_DEFAULT_RATE_HUF')) {
    define('IMPACTSHOP_SUM_DEFAULT_RATE_HUF', 392);
}
if (!defined('IMPACTSHOP_SUM_DEFAULT_DONATION_RATE')) {
    define('IMPACTSHOP_SUM_DEFAULT_DONATION_RATE', 0.5);
}

/* --------------------------------------------------------------------------
 * Helpers
 * ----------------------------------------------------------------------- */
if (!function_exists('impactshop_sum_today')) {
    function impactshop_sum_today(): string
    {
        return current_time('Y-m-d');
    }
}

if (!function_exists('impactshop_sum_from_default')) {
    function impactshop_sum_from_default(): string
    {
        return IMPACTSHOP_SUM_DEFAULT_FROM;
    }
}

if (!function_exists('impactshop_sum_totals_url')) {
    function impactshop_sum_totals_url(array $args = []): string
    {
        $defaults = [
            'from'   => impactshop_sum_from_default(),
            'to'     => impactshop_sum_today(),
            'status' => 'all',
            'group'  => 'shop_ngo',
        ];
        $query = array_merge($defaults, $args);
        $base  = defined('IMPACT_API_BASE_HOST')
            ? rtrim(IMPACT_API_BASE_HOST, '/')
            : rtrim(home_url(), '/');
        return add_query_arg($query, $base . '/wp-json/impactshop/v1/totals');
    }
}

if (!function_exists('impactshop_sum_fetch_totals')) {
    function impactshop_sum_fetch_totals(array $args = [])
    {
        $url   = impactshop_sum_totals_url($args);
        $cache = 'impactshop_sum_totals_' . md5($url);
        $hit   = get_transient($cache);
        if ($hit !== false) {
            return $hit;
        }

        $resp = wp_remote_get($url, ['timeout' => 12, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($resp)) {
            return ['_error' => $resp->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            return ['_error' => 'HTTP ' . $code];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) {
            return ['_error' => 'JSON parse'];
        }

        set_transient($cache, $body, 120);
        return $body;
    }
}

if (!function_exists('impactshop_sum_is_unknown')) {
    function impactshop_sum_is_unknown($value): bool
    {
        $value = strtolower(trim((string)$value));
        return ($value === '' || strpos($value, 'ismeretlen') !== false || strpos($value, 'unknown') !== false);
    }
}

if (!function_exists('impactshop_sum_commission_filtered')) {
    function impactshop_sum_commission_filtered(array $data, bool $excludeUnknown, string $scope): float
    {
        $number = static fn($raw): float => is_numeric($raw) && is_finite((float)$raw) ? (float)$raw : NAN;
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        if (!$excludeUnknown) {
            return $number($data['meta']['grand']['commission'] ?? 0);
        }

        $sum = 0.0;
        foreach ($rows as $row) {
            $ngo  = $row['ngo'] ?? $row['ngo_name'] ?? '';
            $shop = $row['shop'] ?? $row['shop_name'] ?? $row['shop_slug'] ?? '';
            $drop = ($scope === 'ngo')
                ? impactshop_sum_is_unknown($ngo)
                : (($scope === 'shop')
                    ? impactshop_sum_is_unknown($shop)
                    : (impactshop_sum_is_unknown($ngo) || impactshop_sum_is_unknown($shop)));
            if ($drop) {
                continue;
            }
            $sum += $number($row['commission'] ?? 0);
        }

        if ($sum === 0.0 && !$rows) {
            $sum = $number($data['meta']['grand']['commission'] ?? 0);
        }
        return $sum;
    }
}

if (!function_exists('impactshop_sum_money')) {
    function impactshop_sum_money(float $value, string $currency): string
    {
        $currency = strtoupper($currency);
        if ($currency === 'HUF') {
            return number_format($value, 0, '.', ' ') . ' Ft';
        }
        return '€ ' . number_format($value, 2, ',', ' ');
    }
}

if (!function_exists('impactshop_sum_amount')) {
    function impactshop_sum_amount(float $commission, float $donationRate, string $currency, float $rateHuf): float
    {
        $donation = max(0, $commission) * $donationRate;
        return (strtoupper($currency) === 'HUF') ? $donation * $rateHuf : $donation;
    }
}

/** Display-only EUR estimate; the legacy source is floating point, not a ledger. */
function impactshop_sum_estimate_markup(float $amount, string $currency): string
{
    $GLOBALS['impactshop_sum_display_used'] = true;
    $valid = is_finite($amount) && $amount >= 0;
    $raw = $valid ? number_format($amount, 2, '.', '') : '';
    return '<div class="sum-money" data-sum-money data-display-currency="' . esc_attr($currency) . '">'
        . '<div class="num" data-amt="' . esc_attr($raw) . '" data-currency="EUR">'
        . ($valid ? esc_html(impactshop_sum_money($amount, 'EUR')) : '—') . '</div>'
        . '<small data-sum-converted hidden></small>'
        . '<small data-sum-note>Becslés, nem végleges adomány.</small></div>';
}

function impactshop_sum_display_runtime(): void
{
    if (empty($GLOBALS['impactshop_sum_display_used'])) { return; }
    $fx = function_exists('sharity_support_fx_payload') && defined('SHARITY_SUPPORT_FX_CACHE_KEY')
        ? sharity_support_fx_payload(get_option(SHARITY_SUPPORT_FX_CACHE_KEY)) : ['status' => 'unavailable'];
    ?>
    <style>
      [data-sum-money]{min-width:0;overflow-wrap:anywhere}
      [data-sum-money] small{display:block;font:600 11px/1.35 system-ui;margin-top:4px}
      [data-sum-money] [hidden]{display:none}
      [data-impact-sum-widget="mini"] [data-sum-money]{color:#fff}
      [data-impact-sum-widget="sticky"]>[role="region"]{box-sizing:border-box;min-width:0;flex-wrap:wrap}
      [data-impact-sum-widget="sticky"] .label{line-height:1.3;overflow-wrap:anywhere}
      [data-impact-sum-widget="sticky"] .close{flex-shrink:0}
      [data-impact-sum-widget="mini"] .wrap{box-sizing:border-box;min-width:0;max-width:100%;width:min(90vw,1120px)}
    </style>
    <script id="impactshop-sum-display">
    (function(){
      'use strict';
      if(window.impactshopSumDisplay) return;
      var fx=<?php echo wp_json_encode($fx, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
      var locales={hu:'hu-HU',en:'en-US',de:'de-DE',fr:'fr-FR'}, currencies=['HUF','EUR','USD','GBP'];
      var copy={
        hu:{label:'Várható adomány',note:'Becslés, nem végleges adomány.',missing:'Az átváltás most nem érhető el.',invalid:'Az összeg nem érhető el.',error:'Az összeg nem tölthető be.',close:'Sáv bezárása'},
        en:{label:'Estimated donation',note:'An estimate, not a final donation.',missing:'Conversion is currently unavailable.',invalid:'Amount unavailable.',error:'The amount could not be loaded.',close:'Dismiss bar'},
        de:{label:'Voraussichtliche Spende',note:'Eine Schätzung, kein endgültiger Spendenbetrag.',missing:'Umrechnung derzeit nicht verfügbar.',invalid:'Betrag nicht verfügbar.',error:'Der Betrag konnte nicht geladen werden.',close:'Leiste schließen'},
        fr:{label:'Don estimé',note:'Une estimation, pas un don définitif.',missing:'Conversion indisponible pour le moment.',invalid:'Montant indisponible.',error:'Impossible de charger le montant.',close:'Fermer la barre'}
      };
      function preferences(){
        var stored={},cookie={};
        try{stored=JSON.parse(localStorage.getItem('sharity_ui_preferences_v1'))||{};}catch(_){}
        try{['language','currency'].forEach(function(k){var name='sharity_ui_'+k+'=',values=document.cookie.split(';').map(function(s){return s.trim();}).filter(function(s){return s.indexOf(name)===0;});if(values.length===1)cookie[k]=decodeURIComponent(values[0].slice(name.length));});}catch(_){}
        var languages=['hu','en','de','fr'];
        var lang=languages.includes(cookie.language)?cookie.language:(languages.includes(stored.language)?stored.language:'hu');
        var cur=currencies.includes(cookie.currency)?cookie.currency:(currencies.includes(stored.displayCurrency)?stored.displayCurrency:'HUF');
        var q=new URLSearchParams(location.search),legacy=q.getAll('lang'),l=q.getAll('ui_lang'),c=q.getAll('ui_currency');
        if(!languages.includes(cookie.language)&&!languages.includes(stored.language)&&legacy.length===1&&['hu','en'].includes(legacy[0]))lang=legacy[0];
        if(l.length===1&&languages.includes(l[0]))lang=l[0];
        if(c.length===1&&currencies.includes(c[0]))cur=c[0];
        var dock=document.querySelector('.sharity-action-bar'),select=document.getElementById('sharity-slc-currency');
        if(dock&&languages.includes(dock.lang))lang=dock.lang;
        if(select&&currencies.includes(select.value))cur=select.value;
        return {language:lang,currency:cur};
      }
      function decimal(raw){
        if(typeof raw!=='string'||raw.length>41||!/^\d+(?:\.\d{1,6})?$/.test(raw))return null;
        var parts=raw.split('.');return {n:BigInt(parts.join('')),d:10n**BigInt((parts[1]||'').length)};
      }
      function round(n,d){return (2n*n+d)/(2n*d);}
      function money(minor,currency,language){
        return new Intl.NumberFormat(locales[language],{style:'currency',currency:currency,minimumFractionDigits:2,maximumFractionDigits:2})
          .formatToParts(minor/100n).map(function(p){return p.type==='fraction'?(minor%100n).toString().padStart(2,'0'):p.value;}).join('');
      }
      function rates(){
        if(fx.status!=='current')return null;
        var age=Date.now()-Date.parse(fx.fetched_at);
        var today=new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Budapest',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
        var days=(Date.parse(today+'T00:00:00Z')-Date.parse(fx.quote_date+'T00:00:00Z'))/86400000;
        if(!Number.isFinite(age)||age<0||age>36*3600000||!Number.isFinite(days)||days<0||days>7)return null;
        var out={HUF:{n:1n,d:1n}};
        for(var cur of ['EUR','USD','GBP']){
          var q=fx.quotes&&fx.quotes[cur],rate=q&&decimal(q.rate);
          if(!rate||rate.n===0n||!/^10{0,6}$/.test(q.unit))return null;
          out[cur]={n:rate.n,d:rate.d*BigInt(q.unit)};
        }
        return out;
      }
      function render(){
        var pref=preferences(),t=copy[pref.language],r=rates();
        document.querySelectorAll('[data-impact-sum-widget]').forEach(function(root){
          root.lang=pref.language;
          root.querySelectorAll('[data-sum-copy]').forEach(function(node){
            var value=t[node.dataset.sumCopy];if(!value)return;
            if(node.dataset.sumTarget==='aria-label')node.setAttribute('aria-label',value);else node.textContent=value;
          });
          var ownLabel=root.querySelector('[data-sum-copy="label"]'),region=root.querySelector('[role="region"]');
          if(ownLabel&&region)region.setAttribute('aria-label',t.label);
          root.querySelectorAll('[data-sum-money]').forEach(function(box){
            var num=box.querySelector('.num'),amount=decimal(num.dataset.amt),converted=box.querySelector('[data-sum-converted]'),note=box.querySelector('[data-sum-note]');
            converted.hidden=true;converted.textContent='';
            if(!amount){num.textContent='—';note.textContent=t.invalid;return;}
            num.textContent=money(round(amount.n*100n,amount.d),'EUR',pref.language);
            note.textContent=t.note;
            if(pref.currency==='EUR')return;
            if(!r){note.textContent+=' '+t.missing;return;}
            var eur=r.EUR,target=r[pref.currency];
            var minor=round(amount.n*eur.n*target.d*100n,amount.d*eur.d*target.n);
            converted.textContent='≈ '+money(minor,pref.currency,pref.language)+' · MNB '+fx.quote_date;
            converted.hidden=false;
          });
        });
      }
      // Keep this bar above the existing dock and preference button, including long labels.
      function positionSticky(){
        var bottom=12;
        document.querySelectorAll('.sharity-action-bar,#sharity-slc-btn').forEach(function(node){
          var rect=node.getBoundingClientRect();
          if(rect.width&&rect.height&&getComputedStyle(node).visibility!=='hidden')bottom=Math.max(bottom,innerHeight-rect.top+12);
        });
        document.querySelectorAll('[data-impact-sum-widget="sticky"]').forEach(function(node){node.style.bottom='max('+bottom+'px, env(safe-area-inset-bottom))';});
      }
      var dockResize=typeof ResizeObserver==='function'?new ResizeObserver(positionSticky):null;
      window.addEventListener('resize',positionSticky);
      window.impactshopSumDisplay=render;
      var observed=new WeakSet(),queued=false;
      function bind(){
        document.querySelectorAll('.sharity-action-bar').forEach(function(bar){if(!observed.has(bar)){observed.add(bar);new MutationObserver(render).observe(bar,{attributes:true,attributeFilter:['lang']});}});
        document.querySelectorAll('[data-sum-money] .num').forEach(function(num){if(!observed.has(num)){observed.add(num);new MutationObserver(render).observe(num,{attributes:true,attributeFilter:['data-amt']});}});
        document.querySelectorAll('.sharity-action-bar,#sharity-slc-btn').forEach(function(node){if(dockResize)dockResize.observe(node);});
        render();
        positionSticky();
      }
      document.addEventListener('change',function(event){if(event.target.id==='sharity-slc-currency')render();});
      new MutationObserver(function(records){
        if(queued||!records.some(function(record){return Array.from(record.addedNodes).some(function(node){return node.nodeType===1&&(node.matches('.sharity-action-bar,#sharity-slc-btn,[data-impact-sum-widget]')||node.querySelector('.sharity-action-bar,#sharity-slc-btn,[data-impact-sum-widget]'));});}))return;
        queued=true;requestAnimationFrame(function(){queued=false;bind();});
      }).observe(document.body,{childList:true,subtree:true});
      bind();
      // Expiry of a cached page must remove a now-stale indicative conversion.
      if(fx.status==='current'){
        var quoteEnd=Date.parse(fx.quote_date+'T00:00:00Z')+8*86400000;
        // Budapest midnight: the UTC midnight on that date is 01:00 or 02:00 locally.
        var offset=Number(new Intl.DateTimeFormat('en-GB',{timeZone:'Europe/Budapest',hour:'numeric',hourCycle:'h23'}).format(new Date(quoteEnd)))*3600000;
        var expiry=Math.min(Date.parse(fx.fetched_at)+36*3600000,quoteEnd-offset)-Date.now();
        if(expiry>0)setTimeout(render,expiry+1);
      }
    })();
    </script>
    <?php
}
add_action('wp_footer', 'impactshop_sum_display_runtime', 22);


/* --------------------------------------------------------------------------
 * Shortcodes
 * ----------------------------------------------------------------------- */
function impactshop_sum_register_shortcodes() {
remove_shortcode('impact_sum_mini');
remove_shortcode('impact_sum_sticky');
remove_shortcode('impact_sum_diag');
remove_shortcode('impact_sum_counter');
add_shortcode('impact_sum_mini', function ($atts) {
    $GLOBALS['impactshop_sum_display_used'] = true;
    $a = shortcode_atts([
        'from'            => impactshop_sum_from_default(),
        'to'              => '',
        'status'          => 'all',
        'currency'        => 'HUF',
        'rate_huf'        => (string)IMPACTSHOP_SUM_DEFAULT_RATE_HUF,
        'donation_rate'   => (string)IMPACTSHOP_SUM_DEFAULT_DONATION_RATE,
        'exclude_unknown' => '1',
        'unknown_scope'   => 'ngo',
        'refresh'         => '60',
        'accent'          => '#7c3aed',
        'label'           => '',
    ], $atts, 'impact_sum_mini');

    $to           = (trim($a['to']) !== '') ? $a['to'] : impactshop_sum_today();
    $currency     = strtoupper(trim($a['currency']));
    $rateHuf      = max(1, (float)$a['rate_huf']);
    $donationRate = is_numeric($a['donation_rate']) ? max(0, min(1, (float)$a['donation_rate'])) : NAN;
    $exclude      = ($a['exclude_unknown'] === '1');
    $scope        = in_array($a['unknown_scope'], ['ngo', 'shop', 'both'], true) ? $a['unknown_scope'] : 'ngo';
    $refresh      = max(0, (int)$a['refresh']);
    $accent       = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#7c3aed';

    $data = impactshop_sum_fetch_totals([
        'from'   => $a['from'],
        'to'     => $to,
        'status' => $a['status'],
        'group'  => 'shop_ngo',
    ]);
    if (isset($data['_error'])) {
        return '<div data-sharity-preferences-managed data-impact-sum-widget="mini" class="impact-box impact-error"><span data-sum-copy="error">Az összeg nem tölthető be.</span></div>';
    }

    $commission = impactshop_sum_commission_filtered($data, $exclude, $scope);
    $amount = impactshop_sum_amount($commission, $donationRate, 'EUR', $rateHuf);

    $uid = 'impact_sum_mini_' . substr(md5(json_encode([$a, microtime(true)])), 0, 8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{
        --accent: <?php echo esc_html($accent); ?>;
        --glass: rgba(255,255,255,.18);
        --border: rgba(255,255,255,.25);
        --shadow: rgba(2,6,23,.35);
        font-family: Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        text-align:center;
      }
      .<?php echo $uid; ?> .wrap{
        display:inline-block; min-width: min(90vw, 1120px);
        padding: 18px 28px; border-radius: 22px;
        background:
          radial-gradient(1100px 600px at 10% -20%, color-mix(in srgb, var(--accent) 35%, transparent), transparent 50%),
          linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
        backdrop-filter: blur(14px);
        border: 1px solid var(--border);
        box-shadow: 0 28px 60px var(--shadow), inset 0 0 0 1px rgba(255,255,255,.06);
      }
      .<?php echo $uid; ?> .label{
        display:block; margin-bottom:4px; letter-spacing:.08em; text-transform:uppercase;
        font-weight:800; font-size:12px; color:#e5e7eb; text-shadow:0 1px 0 rgba(0,0,0,.25);
      }
      .<?php echo $uid; ?> .num{
        font-weight: 900;
        font-size: clamp(40px, 12vw, 88px);
        line-height: 1.02;
        color:#ffffff;
        text-shadow:
          0 2px 18px color-mix(in srgb, var(--accent) 32%, transparent),
          0 6px 30px rgba(0,0,0,.35);
      }
      .<?php echo $uid; ?> .rng{
        margin-top:6px; font-weight:700; font-size:13px; color:#e2e8f0;
        text-shadow: 0 1px 0 rgba(0,0,0,.3);
        opacity:.95;
      }
    </style>
    <div class="<?php echo $uid; ?>" data-sharity-preferences-managed data-impact-sum-widget="mini">
      <div class="wrap">
        <?php if ($a['label']) : ?>
          <span class="label"><?php echo esc_html($a['label']); ?></span>
        <?php endif; ?>
        <?php echo impactshop_sum_estimate_markup($amount, $currency); ?>
        <div class="rng"><?php echo esc_html($a['from'] . ' → ' . $to); ?></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('impact_sum_sticky', function ($atts) {
    $GLOBALS['impactshop_sum_display_used'] = true;
    $a = shortcode_atts([
        'from'            => impactshop_sum_from_default(),
        'to'              => '',
        'status'          => 'all',
        'currency'        => 'HUF',
        'rate_huf'        => (string)IMPACTSHOP_SUM_DEFAULT_RATE_HUF,
        'donation_rate'   => (string)IMPACTSHOP_SUM_DEFAULT_DONATION_RATE,
        'exclude_unknown' => '1',
        'unknown_scope'   => 'ngo',
        'label'           => '',
        'accent'          => '#7c3aed',
        'cta_text'        => '',
        'cta_href'        => '',
        'show_on'         => 'all',
        'refresh'         => '60',
        'dismiss_days'    => '3',
    ], $atts, 'impact_sum_sticky');

    $to           = (trim($a['to']) !== '') ? $a['to'] : impactshop_sum_today();
    $currency     = strtoupper(trim($a['currency']));
    $rateHuf      = max(1, (float)$a['rate_huf']);
    $donationRate = is_numeric($a['donation_rate']) ? max(0, min(1, (float)$a['donation_rate'])) : NAN;
    $exclude      = ($a['exclude_unknown'] === '1');
    $scope        = in_array($a['unknown_scope'], ['ngo', 'shop', 'both'], true) ? $a['unknown_scope'] : 'ngo';
    $refresh      = max(0, (int)$a['refresh']);
    $accent       = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#7c3aed';
    $showOn       = in_array($a['show_on'], ['all', 'mobile', 'desktop'], true) ? $a['show_on'] : 'all';
    $dismissDays  = max(1, (int)$a['dismiss_days']);

    $data = impactshop_sum_fetch_totals([
        'from'   => $a['from'],
        'to'     => $to,
        'status' => $a['status'],
        'group'  => 'shop_ngo',
    ]);
    if (isset($data['_error'])) {
        return '';
    }

    $commission = impactshop_sum_commission_filtered($data, $exclude, $scope);
    $amount = impactshop_sum_amount($commission, $donationRate, 'EUR', $rateHuf);

    $uid = 'impact_sum_sticky_' . substr(md5(json_encode([$a, microtime(true)])), 0, 8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>-bar{position:fixed;z-index:9999;left:12px;right:12px;
        bottom: max(12px, env(safe-area-inset-bottom)); display:flex; justify-content:center; pointer-events:none}
      .<?php echo $uid; ?>{
        --accent: <?php echo esc_html($accent); ?>;
        --glass: rgba(16,18,27,.55); --glass2: rgba(255,255,255,.08);
        --border: rgba(255,255,255,.20); --shadow: rgba(2,6,23,.55);
        font-family: Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
        color:#fff; pointer-events:auto;
        min-width:min(94vw,1100px); max-width:1100px; width:100%;
        display:flex; gap:12px; align-items:center;
        padding:12px 16px; border-radius:16px;
        background:
          radial-gradient(900px 520px at 8% -10%, color-mix(in srgb, var(--accent) 24%, transparent), transparent 50%),
          linear-gradient(180deg, rgba(16,18,27,.90), rgba(16,18,27,.70));
        backdrop-filter: blur(12px);
        border: 1px solid var(--border);
        box-shadow: 0 18px 48px var(--shadow), inset 0 0 0 1px var(--glass2);
      }
      .<?php echo $uid; ?> .label{font:800 11px/1 Inter;letter-spacing:.08em;text-transform:uppercase;opacity:.9}
      .<?php echo $uid; ?> .num{font:900 clamp(22px,6vw,36px)/1.05 Inter;
        text-shadow:0 2px 16px color-mix(in srgb, var(--accent) 35%, transparent)}
      .<?php echo $uid; ?> .rng{font:700 12px/1.2 Inter;opacity:.8; white-space:nowrap}
      .<?php echo $uid; ?> .sp{flex:1}
      .<?php echo $uid; ?> .cta{
        display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;
        background: color-mix(in srgb, var(--accent) 22%, #ffffff1a);
        border:1px solid color-mix(in srgb, var(--accent) 45%, #ffffff20);
        color:#fff;text-decoration:none;font:800 12px/1 Inter
      }
      .<?php echo $uid; ?> .close{
        width:34px;height:34px;border-radius:10px;border:1px solid #ffffff2e;background:#ffffff1a;color:#fff;display:grid;place-items:center;
        font-weight:900; cursor:pointer
      }
      <?php if ($showOn === 'mobile'): ?>
        @media (min-width: 768px){ .<?php echo $uid; ?>-bar{display:none} }
      <?php elseif ($showOn === 'desktop'): ?>
        @media (max-width: 767.98px){ .<?php echo $uid; ?>-bar{display:none} }
      <?php endif; ?>
    </style>
    <div class="<?php echo $uid; ?>-bar" aria-live="polite" data-sharity-preferences-managed data-impact-sum-widget="sticky">
      <div class="<?php echo $uid; ?>" role="region" aria-label="<?php echo esc_attr($a['label'] ?: 'Várható adomány'); ?>">
        <div class="label" <?php if ($a['label'] === ''): ?>data-sum-copy="label"<?php endif; ?>><?php echo esc_html($a['label'] ?: 'Várható adomány'); ?></div>
        <?php echo impactshop_sum_estimate_markup($amount, $currency); ?>
        <div class="rng">· <?php echo esc_html($a['from'] . ' → ' . $to); ?></div>
        <div class="sp"></div>
        <?php if ($a['cta_text'] && $a['cta_href']) : ?>
          <a class="cta" href="<?php echo esc_url($a['cta_href']); ?>" target="_blank" rel="noopener">
            <?php echo esc_html($a['cta_text']); ?>
          </a>
        <?php endif; ?>
        <button class="close" type="button" data-sum-copy="close" data-sum-target="aria-label" aria-label="Sáv bezárása">×</button>
      </div>
    </div>
    <script>
      (function(){
        const bar=document.querySelector('.<?php echo $uid; ?>-bar');
        if(!bar) return;
        const box=bar.querySelector('.<?php echo $uid; ?>');
        const num=bar.querySelector('.num');
        const btn=bar.querySelector('.close');
        const refresh=<?php echo (int)$refresh; ?>;
        const key='impactSumStickyDismissed';
        const ttlDays=<?php echo (int)$dismissDays; ?>;
        try{
          const raw=localStorage.getItem(key);
          if(raw){
            const until=Number(raw); if(!isNaN(until) && Date.now()<until){ bar.style.display='none'; return; }
          }
        }catch(e){}
        // The live estimate remains EUR; never derive money from translated text.
        function parseNum(s){const value=Number(s);return s!==''&&Number.isFinite(value)&&value>=0?value:NaN;}
        function fmt(v){
          const lang=bar.lang||'hu';
          return new Intl.NumberFormat({hu:'hu-HU',en:'en-US',de:'de-DE',fr:'fr-FR'}[lang]||'hu-HU', {style:'currency',currency:'EUR'}).format(v);
        }
        let last=parseNum(num.getAttribute('data-amt'));
        function animateTo(newV){
          if(newV<=last){ num.textContent=fmt(newV); last=newV; return; }
          const dur=900,t0=performance.now(); box.classList.add('bump');
          function step(t){ const k=Math.min(1,(t-t0)/dur);
            const val=last+(newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
            num.textContent=fmt(val);
            if(k<1) requestAnimationFrame(step); else { last=newV; setTimeout(()=>box.classList.remove('bump'),120); }
          }
          requestAnimationFrame(step);
        }
        if(refresh>0){
          setInterval(()=>{ const cur=parseNum(num.getAttribute('data-amt'));
            if(cur>last) animateTo(cur);
          }, refresh*1000);
        }
        btn.addEventListener('click', ()=>{
          bar.style.display='none';
          try{
            const until = Date.now() + (ttlDays*24*60*60*1000);
            localStorage.setItem(key, String(until));
          }catch(e){}
        }, {passive:true});
      })();
    </script>
    <?php
    return ob_get_clean();
});

add_shortcode('impact_sum_diag', function ($atts) {
    $GLOBALS['impactshop_sum_display_used'] = true;
    $a = shortcode_atts([
        'from'            => impactshop_sum_from_default(),
        'to'              => '',
        'status'          => 'all',
        'rate_huf'        => (string)IMPACTSHOP_SUM_DEFAULT_RATE_HUF,
        'donation_rate'   => (string)IMPACTSHOP_SUM_DEFAULT_DONATION_RATE,
        'exclude_unknown' => '1',
        'unknown_scope'   => 'ngo',
    ], $atts, 'impact_sum_diag');

    $to           = (trim($a['to']) !== '') ? $a['to'] : impactshop_sum_today();
    $rateHuf      = max(1, (float)$a['rate_huf']);
    $donationRate = is_numeric($a['donation_rate']) ? max(0, min(1, (float)$a['donation_rate'])) : NAN;
    $exclude      = ($a['exclude_unknown'] === '1');
    $scope        = in_array($a['unknown_scope'], ['ngo', 'shop', 'both'], true) ? $a['unknown_scope'] : 'ngo';

    $data = impactshop_sum_fetch_totals([
        'from'   => $a['from'],
        'to'     => $to,
        'status' => $a['status'],
        'group'  => 'shop_ngo',
    ]);
    if (isset($data['_error'])) {
        return '<div style="padding:10px;border:1px solid #fca5a5;border-radius:10px;background:#fff1f2">Diag hiba: ' . esc_html($data['_error']) . '</div>';
    }

    $commAll = (float)($data['meta']['grand']['commission'] ?? 0);
    $commFx  = impactshop_sum_commission_filtered($data, $exclude, $scope);
    $donAll  = $commAll * $donationRate;
    $donFx   = $commFx * $donationRate;

    ob_start(); ?>
    <div data-sharity-preferences-managed data-impact-sum-widget="diag" style="font:600 13px/1.35 system-ui;border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff">
      <div><b>Diag (<?php echo esc_html($a['from'] . ' → ' . $to); ?> · status=<?php echo esc_html($a['status']); ?>)</b></div>
      <div>grand.commission: € <?php echo number_format($commAll, 2, ',', ' '); ?></div>
      <div>commission (filtered): € <?php echo number_format($commFx, 2, ',', ' '); ?></div>
      <div>donation all (estimate): <?php echo impactshop_sum_estimate_markup($donAll, 'HUF'); ?></div>
      <div>donation filtered (estimate): <?php echo impactshop_sum_estimate_markup($donFx, 'HUF'); ?></div>
    </div>
    <?php
    return ob_get_clean();
});
}
add_action('plugins_loaded', 'impactshop_sum_register_shortcodes', 99);
add_action('init', 'impactshop_sum_register_shortcodes', 99);
