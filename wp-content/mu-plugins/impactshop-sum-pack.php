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
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        if (!$excludeUnknown) {
            return (float)($data['meta']['grand']['commission'] ?? 0);
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
            $sum += (float)($row['commission'] ?? 0);
        }

        if ($sum === 0.0 && !$rows) {
            $sum = (float)($data['meta']['grand']['commission'] ?? 0);
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

/* --------------------------------------------------------------------------
 * Shortcodes
 * ----------------------------------------------------------------------- */
function impactshop_sum_register_shortcodes() {
remove_shortcode('impact_sum_mini');
remove_shortcode('impact_sum_sticky');
remove_shortcode('impact_sum_diag');
remove_shortcode('impact_sum_counter');
add_shortcode('impact_sum_mini', function ($atts) {
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
    $donationRate = max(0, min(1, (float)$a['donation_rate']));
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
        return '<div class="impact-box impact-error">Mini hiba: ' . esc_html($data['_error']) . '</div>';
    }

    $commission = impactshop_sum_commission_filtered($data, $exclude, $scope);
    $amount     = impactshop_sum_amount($commission, $donationRate, $currency, $rateHuf);
    $display    = impactshop_sum_money($amount, $currency);

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
    <div class="<?php echo $uid; ?>">
      <div class="wrap">
        <?php if ($a['label']) : ?>
          <span class="label"><?php echo esc_html($a['label']); ?></span>
        <?php endif; ?>
        <div class="num" data-refresh="<?php echo esc_attr($refresh); ?>" data-currency="<?php echo esc_attr($currency); ?>">
          <?php echo esc_html($display); ?>
        </div>
        <div class="rng"><?php echo esc_html($a['from'] . ' → ' . $to); ?></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

add_shortcode('impact_sum_sticky', function ($atts) {
    $a = shortcode_atts([
        'from'            => impactshop_sum_from_default(),
        'to'              => '',
        'status'          => 'all',
        'currency'        => 'HUF',
        'rate_huf'        => (string)IMPACTSHOP_SUM_DEFAULT_RATE_HUF,
        'donation_rate'   => (string)IMPACTSHOP_SUM_DEFAULT_DONATION_RATE,
        'exclude_unknown' => '1',
        'unknown_scope'   => 'ngo',
        'label'           => 'Összegyűjtve',
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
    $donationRate = max(0, min(1, (float)$a['donation_rate']));
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
    $amount     = impactshop_sum_amount($commission, $donationRate, $currency, $rateHuf);
    $display    = impactshop_sum_money($amount, $currency);

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
    <div class="<?php echo $uid; ?>-bar" aria-live="polite">
      <div class="<?php echo $uid; ?>" role="region" aria-label="<?php echo esc_attr($a['label']); ?>">
        <div class="label"><?php echo esc_html($a['label']); ?></div>
        <div class="num" data-amt="<?php echo esc_attr(number_format($amount, 2, '.', '')); ?>" data-currency="<?php echo esc_attr($currency); ?>">
          <?php echo esc_html($display); ?>
        </div>
        <div class="rng">· <?php echo esc_html($a['from'] . ' → ' . $to); ?></div>
        <div class="sp"></div>
        <?php if ($a['cta_text'] && $a['cta_href']) : ?>
          <a class="cta" href="<?php echo esc_url($a['cta_href']); ?>" target="_blank" rel="noopener">
            <?php echo esc_html($a['cta_text']); ?>
          </a>
        <?php endif; ?>
        <button class="close" type="button" aria-label="Sáv bezárása">×</button>
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
        function parseNum(s){return parseFloat(String(s).replace(/[^\d.]/g,''))||0;}
        function fmt(v){
          const isHUF='<?php echo esc_js($currency); ?>'==='HUF';
          if(isHUF){return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ')+' Ft';}
          const n=(Math.round(v*100)/100).toFixed(2).replace('.',','); return '€ '+n;
        }
        let last=parseNum(num.getAttribute('data-amt')||num.textContent);
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
          setInterval(()=>{ const cur=parseNum(num.getAttribute('data-amt')||num.textContent);
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
    $donationRate = max(0, min(1, (float)$a['donation_rate']));
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
    <div style="font:600 13px/1.35 system-ui;border:1px solid #e5e7eb;border-radius:12px;padding:10px;background:#fff">
      <div><b>Diag (<?php echo esc_html($a['from'] . ' → ' . $to); ?> · status=<?php echo esc_html($a['status']); ?>)</b></div>
      <div>grand.commission: € <?php echo number_format($commAll, 2, ',', ' '); ?></div>
      <div>commission (filtered): € <?php echo number_format($commFx, 2, ',', ' '); ?></div>
      <div>donation all: € <?php echo number_format($donAll, 2, ',', ' '); ?> · HUF <?php echo number_format($donAll * $rateHuf, 0, '.', ' '); ?></div>
      <div>donation filtered: € <?php echo number_format($donFx, 2, ',', ' '); ?> · HUF <?php echo number_format($donFx * $rateHuf, 0, '.', ' '); ?></div>
    </div>
    <?php
    return ob_get_clean();
});
}
add_action('plugins_loaded', 'impactshop_sum_register_shortcodes', 99);
add_action('init', 'impactshop_sum_register_shortcodes', 99);
