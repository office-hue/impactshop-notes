Átugrás a tartalomra
Csevegési előzmények

Ezt mondtad:
Lehet az NGO card-ból olyan html kódot gyártani, amit bármely ngo betehet a saját oldalára? Természetesen rá kattintva eljön az app.sharity.hu -ra (egy általam rögzített baloldalra) Fontos, hogy lehessen hozzá képet és szöveget is tenni, mert így nagyon száraz. Legyen jó designos GEN Z-s. Ez a kártya (Bátor Táborra, de ugye bármelyik NGO-ra szeretnék gyártani ilyet. [impact_ngo_card ngo="mbe" label="Budapesti Mozgássérültek Egyesülete"  from="2025-09-01" to="" status="all" rate_huf="392.5" currency="HUF" accent="#7c3aed"] Ez a snippet tartalmazza, de ne írjunk bele, külön kezeld: /**
 * Sharity – NGO toplista és egyedi NGO kártya (rolling intervallummal, animációval)
 * Shortcode-ok:
 *  - [impact_ngo_top  from="" to="" status="approved" limit="10" rate_huf="392" currency="HUF" exclude_unknown="1" title="Top NGO-k"]
 *  - [impact_ngo_card ngo=""   label="" from="" to="" status="approved" rate_huf="392" currency="HUF" accent="#7c3aed"]
 *
 * Logika (belső): adomány = commission * 0.5; opcionális HUF konverzió fix Ft/€ árfolyammal.
 * Ha "to" üres vagy "auto": kezdőtől MA-ig (rolling).
 */

if (!defined('ABSPATH')) exit;

/* ---------- Közös segédek ---------- */
if (!function_exists('s_slugify_hu')) {
  function s_slugify_hu($s){
    $s = wp_strip_all_tags((string)$s);
    $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    if (function_exists('iconv')) { $t=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s); if($t!==false) $s=$t; }
    $s = strtolower(preg_replace('~[^a-z0-9]+~','-',$s));
    return trim($s,'-');
  }
}
if (!function_exists('s_is_unknown')) {
  function s_is_unknown($name){
    $n = strtolower(trim((string)$name));
    return ($n === '' || preg_match('~\b(ismeretlen|unknown)\b~i', $n));
  }
}
if (!function_exists('s_fetch_totals')) {
  function s_fetch_totals($args){
    $def = [
      'from'   => date('Y-m-01'),
      'to'     => date('Y-m-d'),
      'status' => 'approved',
      'group'  => 'ngo',
    ];
    $q = array_merge($def, $args);
    $url = add_query_arg($q, home_url('/wp-json/impactshop/v1/totals'));
    $key = 'ngo_tot_'.md5($url);
    if (($cached = get_transient($key)) !== false) return $cached;

    $resp = wp_remote_get($url, ['timeout'=>12, 'headers'=>['Accept'=>'application/json']]);
    if (is_wp_error($resp)) return ['rows'=>[], 'meta'=>[], '_error'=>$resp->get_error_message()];
    $code = wp_remote_retrieve_response_code($resp);
    if ($code<200 || $code>=300) return ['rows'=>[], 'meta'=>[], '_error'=>'HTTP '.$code];
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($data)) return ['rows'=>[], 'meta'=>[], '_error'=>'JSON'];
    set_transient($key, $data, 180); // 3 perc
    return $data;
  }
}
if (!function_exists('s_fmt_money')) {
  function s_fmt_money($v, $currency='HUF'){
    if (strtoupper($currency)==='HUF') return number_format((float)$v, 0, '.', ' ').' Ft';
    return '€ '.number_format((float)$v, 2, ',', ' ');
  }
}

/* ---------- [impact_ngo_top] – NGO toplista ---------- */
if (!function_exists('impact_ngo_top_shortcode')) {
  function impact_ngo_top_shortcode($atts){
    $a = shortcode_atts([
      'from'            => date('Y-m-01'),
      'to'              => '',             // üres = ma-ig
      'status'          => 'approved',
      'limit'           => '10',
      'rate_huf'        => '392',       // Ft/€
      'currency'        => 'HUF',         // HUF|EUR
      'exclude_unknown' => '1',
      'title'           => 'Top NGO-k',
      'accent'          => '#22c55e',     // neon effektek színe
      'refresh'         => '60',          // mp – opcionális autófriss vizuál
    ], $atts, 'impact_ngo_top');

    $limit     = max(1, (int)$a['limit']);
    $rate_huf  = (float)$a['rate_huf'];
    $currency  = strtoupper(trim($a['currency']?:'HUF'));
    $no_unk    = ($a['exclude_unknown']==='1');
    $to        = (trim((string)$a['to'])!=='') ? $a['to'] : date('Y-m-d');
    $accent    = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#22c55e';
    $refresh   = max(0, (int)$a['refresh']);

    $data = s_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'ngo']);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];

    $list = [];
    foreach ($rows as $r){
      $name = $r['ngo'] ?? $r['ngo_name'] ?? '';
      if ($no_unk && s_is_unknown($name)) continue;
      $slug = $r['ngo_slug'] ?? s_slugify_hu($name);
      $don_eur = ((float)($r['commission'] ?? 0)) * 0.5;
      $amt = ($currency==='HUF') ? ($don_eur * $rate_huf) : $don_eur;
      if ($amt <= 0) continue;
      $list[] = ['slug'=>$slug, 'name'=>($name?:$slug), 'amt'=>$amt];
    }
    usort($list, fn($x,$y)=> ($y['amt'] <=> $x['amt']) ?: strcasecmp($x['name'],$y['name']));
    $list = array_slice($list, 0, $limit);

    $uid = 'ngotop_'.substr(md5(json_encode([$a, microtime(true)])),0,8);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{--ink:#0f172a;--muted:#64748b;--br:#e5e7eb;--glow:<?php echo esc_html($accent); ?>; color:var(--ink);
        font:600 14px/1.35 Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
      .<?php echo $uid; ?> .hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;color:#475569}
      .<?php echo $uid; ?> .title{font:900 16px/1.1 Inter}
      .<?php echo $uid; ?> .rng{font:600 12px/1.2 Inter;color:var(--muted)}
      .<?php echo $uid; ?> ol{margin:0;padding:0;list-style:none;display:grid;gap:10px}
      .<?php echo $uid; ?> .row{position:relative;display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--br);
        border-radius:14px;background:linear-gradient(180deg,#fff,#fafafa);box-shadow:0 12px 28px rgba(2,6,23,.06);overflow:hidden}
      .<?php echo $uid; ?> .row::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(34,197,94,.12),transparent);
        transform:translateX(-100%);opacity:0;pointer-events:none}
      .<?php echo $uid; ?> .row.bump::after{animation:sweep 750ms ease}
      @keyframes sweep{0%{transform:translateX(-120%);opacity:0}40%{opacity:1}100%{transform:translateX(120%);opacity:0}}
      .<?php echo $uid; ?> .rank{width:28px;height:28px;border-radius:8px;background:#eef2ff;color:#3730a3;display:grid;place-items:center;font:900 12px/1 Inter}
      .<?php echo $uid; ?> .name{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font:800 14px/1.2 Inter}
      .<?php echo $uid; ?> .amt{font:900 14px/1 Inter}
      .<?php echo $uid; ?> .amt .num{display:inline-block;min-width:6ch;text-align:right}
      .<?php echo $uid; ?> .amt.bump{filter:drop-shadow(0 0 8px var(--glow))}
    </style>
    <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
      <div class="hdr">
        <div class="title"><?php echo esc_html($a['title']); ?></div>
        <div class="rng"><?php echo esc_html($a['from'].' → '.$to); ?></div>
      </div>
      <?php if (!$list): ?>
        <div style="color:#64748b">Nincs megjeleníthető adat.</div>
      <?php else: ?>
        <ol>
          <?php foreach ($list as $i=>$it): ?>
            <li class="row" data-amt="<?php echo esc_attr(number_format($it['amt'], 2, '.', '')); ?>">
              <div class="rank"><?php echo $i+1; ?></div>
              <div class="name"><?php echo esc_html($it['name']); ?></div>
              <div class="amt"><span class="num"><?php echo esc_html(s_fmt_money($it['amt'],$currency)); ?></span></div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const rows = Array.from(root.querySelectorAll('.row'));
      // count-up anim növekedéskor
      function parseNum(s){ return parseFloat(String(s).replace(/[^\d.]/g,''))||0; }
      function animateCount(el, oldV, newV, fmt){
        if(newV<=oldV){ el.textContent = fmt(newV); return; }
        const dur=700, t0=performance.now();
        function step(t){
          const k=Math.min(1,(t-t0)/dur);
          const val = oldV + (newV-oldV)*(0.5-0.5*Math.cos(Math.PI*k)); // cos-ease
          el.textContent = fmt(val);
          if(k<1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      }
      function fmtMoney(v){
        // a megjelenített pénznem már be van formázva szerveren; itt csak ezresre kerekítünk gyorsan
        const n = Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');
        // próbáljuk kiírni a mértékegységet a DOM alapján (szerveres stringből)
        const tail = (root.textContent.indexOf('€')>-1) ? ' €' : ' Ft';
        return n + tail;
      }
      // initial cache
      rows.forEach(r=> r._last = parseNum(r.getAttribute('data-amt')||'0'));
      // opcionális periódikus friss vizuál (pl. ha cache lejár)
      const refresh = parseInt(root.getAttribute('data-refresh')||'0',10);
      if(refresh>0){
        setInterval(()=> {
          rows.forEach(r=>{
            const cur = parseNum(r.getAttribute('data-amt')||'0');
            // demo: tegyünk úgy, mintha nőtt volna? – élesben itt újrarender után fut le
            // Itt csak az effekt: ha nőtt, „bump”
            if (cur > r._last) {
              const numEl = r.querySelector('.amt .num');
              r.classList.add('bump'); r.querySelector('.amt').classList.add('bump');
              animateCount(numEl, r._last, cur, fmtMoney);
              setTimeout(()=>{ r.classList.remove('bump'); r.querySelector('.amt').classList.remove('bump'); }, 800);
              r._last = cur;
            }
          });
        }, refresh*1000);
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_ngo_top', 'impact_ngo_top_shortcode');
}

/* ---------- [impact_ngo_card] – Egy NGO kártya ---------- */
if (!function_exists('impact_ngo_card_shortcode')) {
  function impact_ngo_card_shortcode($atts){
    $a = shortcode_atts([
      'ngo'      => '',
      'label'    => '',            // megjelenítendő név (pl. "Bátor Tábor Alapítvány")
      'from'     => date('Y-m-01'),
      'to'       => '',            // üres = ma-ig
      'status'   => 'approved',
      'rate_huf' => '392',
      'currency' => 'HUF',
      'accent'   => '#7c3aed',
      'refresh'  => '45',          // mp – opcionális vizuális figyelés
    ], $atts, 'impact_ngo_card');

    $ngo_req   = trim((string)$a['ngo']);
    $ngo_slug  = s_slugify_hu($ngo_req);
    $to        = (trim((string)$a['to'])!=='') ? $a['to'] : date('Y-m-d');
    $rate_huf  = (float)$a['rate_huf'];
    $currency  = strtoupper(trim($a['currency']?:'HUF'));
    $accent    = preg_match('~^#([0-9a-f]{3}|[0-9a-f]{6})$~i', $a['accent']) ? $a['accent'] : '#7c3aed';
    $refresh   = max(0, (int)$a['refresh']);

    $data = s_fetch_totals(['from'=>$a['from'],'to'=>$to,'status'=>$a['status'],'group'=>'ngo','ngo'=>$ngo_req]);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];

    $sum_comm = 0.0; $ngo_name = '';
    foreach ($rows as $r){
      $name = $r['ngo'] ?? $r['ngo_name'] ?? '';
      if (s_is_unknown($name)) continue;
      $slug = $r['ngo_slug'] ?? s_slugify_hu($name);
      if ($ngo_slug && strtolower($slug)!==strtolower($ngo_slug)) continue;
      $sum_comm += (float)($r['commission'] ?? 0);
      $ngo_name = $ngo_name ?: $name;
    }
    $don_eur = $sum_comm * 0.5;
    $amt = ($currency==='HUF') ? ($don_eur * $rate_huf) : $don_eur;

    $uid = 'ngocard_'.substr(md5(json_encode([$a, microtime(true)])),0,8);
    $display = ($a['label']!=='') ? $a['label'] : ($ngo_name ?: $ngo_req);

    ob_start(); ?>
    <style>
      .<?php echo $uid; ?>{--accent:<?php echo esc_html($accent); ?>;--ink:#0f172a;--muted:#64748b;--br:#e5e7eb;
        font:600 14px/1.35 Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:var(--ink)}
      .<?php echo $uid; ?> .card{position:relative;padding:18px;border:1px solid var(--br);border-radius:18px;
        background:radial-gradient(1000px 600px at 10% 0%, color-mix(in srgb, var(--accent) 25%, transparent), transparent 40%),
                    linear-gradient(180deg,#fff,#fafafa);
        box-shadow:0 14px 40px rgba(2,6,23,.08); overflow:hidden}
      .<?php echo $uid; ?> .sweep{position:absolute;inset:0;pointer-events:none;background:linear-gradient(120deg, transparent, color-mix(in srgb, var(--accent) 25%, transparent), transparent);
        transform:translateX(-120%); opacity:0}
      .<?php echo $uid; ?> .card.bump .sweep{animation:cardSweep 800ms ease}
      @keyframes cardSweep{0%{transform:translateX(-140%);opacity:0}40%{opacity:1}100%{transform:translateX(140%);opacity:0}}
      .<?php echo $uid; ?> .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
      .<?php echo $uid; ?> .name{font:900 18px/1.2 Inter}
      .<?php echo $uid; ?> .rng{font:600 12px/1.2 Inter;color:var(--muted)}
      .<?php echo $uid; ?> .amount{font:900 clamp(28px,6vw,40px)/1.08 Inter;color:#0b1220;text-shadow:0 2px 14px color-mix(in srgb, var(--accent) 25%, transparent)}
      .<?php echo $uid; ?> .amount .num{display:inline-block; min-width:8ch; text-align:right}
      .<?php echo $uid; ?> .badge{margin-top:6px;display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid color-mix(in srgb, var(--accent) 45%, #e5e7eb);
        background:color-mix(in srgb, var(--accent) 10%, #fff); color:#0b1220;font:800 11px/1 Inter;letter-spacing:.04em}
    </style>
    <div class="<?php echo $uid; ?>" data-refresh="<?php echo (int)$refresh; ?>">
      <div class="card" data-amt="<?php echo esc_attr(number_format($amt, 2, '.', '')); ?>">
        <div class="sweep"></div>
        <div class="head">
          <div class="name"><?php echo esc_html($display ?: 'NGO'); ?></div>
          <div class="rng"><?php echo esc_html($a['from'].' → '.$to); ?></div>
        </div>
        <div class="amount"><span class="num"><?php echo esc_html(s_fmt_money($amt, $currency)); ?></span></div>
        <div class="badge"><?php echo esc_html($currency); ?> · Árfolyam: <?php echo number_format((float)$a['rate_huf'], 2, '.', ' '); ?> Ft/€</div>
      </div>
    </div>
    <script>
    (function(){
      const root = document.currentScript.previousElementSibling;
      if(!root) return;
      const card = root.querySelector('.card');
      const numEl= root.querySelector('.amount .num');
      function parseNum(s){ return parseFloat(String(s).replace(/[^\d.]/g,''))||0; }
      function fmtMoney(v){
        const txt = root.textContent;
        const isEur = txt.indexOf('€')>-1;
        if(isEur){ return '€ '+(parseFloat(v).toFixed(2).replace('.',',')); }
        // HUF – kerekítve, ezres tagolással
        const n = Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ');
        return n + ' Ft';
      }
      let last = parseNum(card.getAttribute('data-amt')||'0');
      function animateTo(newV){
        if(newV<=last){ numEl.textContent = fmtMoney(newV); last=newV; return; }
        const dur=900, t0=performance.now();
        card.classList.add('bump');
        function step(t){
          const k=Math.min(1,(t-t0)/dur);
          const val = last + (newV-last)*(0.5-0.5*Math.cos(Math.PI*k));
          numEl.textContent = fmtMoney(val);
          if(k<1) requestAnimationFrame(step); else {
            setTimeout(()=>card.classList.remove('bump'), 100);
            last=newV;
          }
        }
        requestAnimationFrame(step);
      }
      // opcionális vizuális figyelés (ha a szerver újrarender után növelné az értéket)
      const refresh = parseInt(root.getAttribute('data-refresh')||'0',10);
      if(refresh>0){
        setInterval(()=>{
          const cur = parseNum(card.getAttribute('data-amt')||'0');
          if(cur>last) animateTo(cur);
        }, refresh*1000);
      }
    })();
    </script>
    <?php
    return ob_get_clean();
  }
  add_shortcode('impact_ngo_card', 'impact_ngo_card_shortcode');
}

Gondolkozás
A ChatGPT ezt mondta:
Igen — csinálunk egy egyetlen beilleszthető HTML-kódot, amit bármelyik NGO a saját oldalára tehet. Gen Z hangulat, kép + szöveg, nagy számok, neon akcentus, és kattintva átvisz az általad megadott app.sharity.hu oldalra, a megfelelő d1 (NGO-slug) paraméterrel.
Tedd be a következő EGY kódtömböt bárhová az NGO oldalán, majd töltsd ki a data-* attribútumokat (legalább a data-ngo, data-label, data-href, data-img mezőket). A kártya az élő összesítést a Sharity ImpactShop REST-ből kérdezi le (/wp-json/impactshop/v1/totals, ahol a donation = 0.5 × commission) és HUF-ra is át tud váltani fix árfolyammal. 

<!--
  Sharity NGO Card – beágyazható egykódsoros widget (Gen Z dizájn)
  HASZNÁLAT: a data-* mezőket töltsd ki.
  Kötelező: data-ngo (slug), data-label (név), data-href (Sharity oldalad), data-img (kép)
  Opcionális: data-text, data-accent, data-currency (HUF|EUR), data-rate-huf (pl. 392.5),
              data-from (YYYY-MM-DD), data-to (YYYY-MM-DD vagy üres=ma), data-status (approved|all),
              data-cta (gomb felirata), data-amb (ambassador kód)
-->
<div class="sharity-ngo-card-embed"
     data-ngo="mbe"
     data-label="Budapesti Mozgássérültek Egyesülete"
     data-img="https://example.org/logo-mbe.png"
     data-text="Minden vásárlás számít. Köszi, hogy támogatod a közösségünket! 🫶"
     data-href="https://app.sharity.hu/impactshop"
     data-accent="#7c3aed"
     data-currency="HUF"
     data-rate-huf="392.5"
     data-from="2025-09-01"
     data-to=""
     data-status="all"
     data-cta="Vásárlok és támogatok"
     data-amb=""
></div>
<script>
(() => {
  const host = document.currentScript.previousElementSibling;
  if (!host || !host.classList.contains('sharity-ngo-card-embed')) return;

  // ---- Beállítások a data-* mezőkből ----
  const ngo     = (host.dataset.ngo || '').trim();
  const label   = (host.dataset.label || ngo || 'Szervezet').trim();
  const img     = (host.dataset.img || '').trim();
  const text    = (host.dataset.text || '').trim();
  const hrefRaw = (host.dataset.href || 'https://app.sharity.hu/impactshop').trim();
  const amb     = (host.dataset.amb || '').trim();
  const accent  = (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(host.dataset.accent||'') ? host.dataset.accent : '#7c3aed');
  const cur     = ((host.dataset.currency || 'HUF').toUpperCase() === 'EUR') ? 'EUR' : 'HUF';
  const rateHUF = parseFloat(host.dataset.rateHuf || '392');
  const from    = (host.dataset.from || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10));
  const to      = (host.dataset.to || new Date().toISOString().slice(0,10));
  const status  = (host.dataset.status || 'approved').toLowerCase();
  const ctaLbl  = (host.dataset.cta || 'Vásárlok és támogatok').trim();

  // ---- CTA link építés (d1, amb, src, UTM) ----
  const addParams = (url, params) => {
    const u = new URL(url, document.baseURI);
    Object.entries(params).forEach(([k,v]) => { if (v) u.searchParams.set(k, v); });
    if (!u.searchParams.has('utm_source')) u.searchParams.set('utm_source', 'ngo-embed');
    if (!u.searchParams.has('utm_medium')) u.searchParams.set('utm_medium', 'widget');
    if (!u.searchParams.has('utm_campaign')) u.searchParams.set('utm_campaign', ngo || 'ngo');
    return u.toString();
  };
  const href = addParams(hrefRaw, { d1: ngo, amb: amb, src: 'ngo-embed' });

  // ---- Árformázás ----
  const fmtMoney = (v) => {
    if (cur === 'EUR') return '€ ' + (Number(v).toFixed(2).replace('.', ','));
    const n = Math.round(Number(v)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return n + ' Ft';
  };

  // ---- Árnyék-DOM + kezdeti markup ----
  const root = host.attachShadow({ mode: 'open' });
  root.innerHTML = `
    <style>
      :host{all:initial}
      .wrap{--accent:${accent};--ink:#0f172a;--muted:#64748b;--br:#e5e7eb;
        font:600 14px/1.35 Inter, system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:var(--ink)}
      .card{position:relative; display:grid; grid-template-columns: 72px 1fr; gap:14px;
        padding:16px; border:1px solid var(--br); border-radius:20px;
        background:
          radial-gradient(1000px 600px at 15% -10%, color-mix(in srgb, var(--accent) 24%, transparent), transparent 40%),
          linear-gradient(180deg,#fff,#fafafa);
        box-shadow: 0 16px 40px rgba(2,6,23,.08); overflow:hidden}
      .glow{position:absolute; inset:-40%; background:
        radial-gradient(600px 240px at 20% 0%, color-mix(in srgb, var(--accent) 12%, transparent), transparent 60%);
        pointer-events:none; filter:blur(6px)}
      .logo{width:72px; height:72px; border-radius:18px; object-fit:cover; background:#fff; border:1px solid #eef2ff}
      .head{display:flex; align-items:flex-start; justify-content:space-between; gap:10px}
      .name{font:900 18px/1.15 Inter}
      .rng{font:600 12px/1.2 Inter; color:var(--muted)}
      .txt{grid-column:1 / -1; margin-top:4px; color:#111827}
      .amt{grid-column:1 / -1; font:900 clamp(26px,6vw,40px)/1.05 Inter; letter-spacing:-.01em;
        color:#0b1220; text-shadow: 0 2px 14px color-mix(in srgb, var(--accent) 25%, transparent)}
      .amt .num{display:inline-block; min-width:8ch; text-align:right}
      .badge{display:inline-block; margin-top:6px; padding:6px 10px; border-radius:999px;
        border:1px solid color-mix(in srgb, var(--accent) 45%, #e5e7eb);
        background:color-mix(in srgb, var(--accent) 10%, #fff); color:#0b1220; font:800 11px/1 Inter; letter-spacing:.04em}
      .cta{grid-column:1 / -1; margin-top:10px; display:inline-flex; align-items:center; gap:8px;
        padding:12px 14px; border-radius:14px; font:800 14px/1 Inter; text-decoration:none; color:#fff;
        background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 60%, #111827));
        box-shadow: 0 10px 24px color-mix(in srgb, var(--accent) 30%, transparent)}
      .cta:hover{transform:translateY(-1px)}
      .cta:active{transform:translateY(0)}
      .cta .arr{display:inline-block; transition: transform .2s}
      .cta:hover .arr{transform: translateX(2px)}
      .sweep{position:absolute;inset:0;pointer-events:none;background:
        linear-gradient(120deg, transparent, color-mix(in srgb, var(--accent) 22%, transparent), transparent);
        transform:translateX(-120%); opacity:0}
      .bump .sweep{animation:cardSweep 820ms ease}
      @keyframes cardSweep{0%{transform:translateX(-140%);opacity:0}40%{opacity:1}100%{transform:translateX(140%);opacity:0}}
      .foot{grid-column:1 / -1; margin-top:6px; color:var(--muted); font:600 11px/1.2 Inter}
      @media (max-width:420px){ .card{grid-template-columns:56px 1fr}.logo{width:56px;height:56px} }
    </style>
    <div class="wrap">
      <div class="card">
        <div class="glow"></div>
        <div class="sweep"></div>
        <img class="logo" alt="" aria-hidden="true" src="${img || ''}" />
        <div class="head">
          <div class="name">${label ? escapeHtml(label) : 'NGO'}</div>
          <div class="rng">${from} → ${to}</div>
        </div>
        ${text ? `<div class="txt">${escapeHtml(text)}</div>` : ''}
        <div class="amt">Eddig gyűlt: <span class="num">—</span></div>
        <a class="cta" href="${href}" target="_top" rel="noopener">
          ${escapeHtml(ctaLbl)} <span class="arr">↗</span>
        </a>
        <div class="foot">${cur} · árfolyam: ${Number(rateHUF||0).toFixed(2)} Ft/€ · Forrás: Sharity ImpactShop</div>
      </div>
    </div>
  `;

  // Kis HTML escape segéd
  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  // ---- Adatlekérés az ImpactShop API-ból (group=ngo, ngo=slug) ----
  // Fontos: a /wp-json/impactshop/v1/totals végpont aggregation-t ad vissza; a donation = 0.5 × commission. :contentReference[oaicite:1]{index=1}
  const api = new URL('https://app.sharity.hu/wp-json/impactshop/v1/totals');
  api.searchParams.set('from', from);
  api.searchParams.set('to',   to);
  api.searchParams.set('status', status);
  api.searchParams.set('group', 'ngo');
  api.searchParams.set('ngo', ngo);

  const card = root.querySelector('.card');
  const numEl= root.querySelector('.amt .num');

  const animate = (oldV, newV) => {
    if (!(newV>oldV)) { numEl.textContent = fmtMoney(newV); return; }
    const dur=900, t0=performance.now();
    card.classList.add('bump');
    (function step(t){
      const k=Math.min(1,(t-t0)/dur);
      const val = oldV + (newV-oldV)*(0.5-0.5*Math.cos(Math.PI*k));
      numEl.textContent = fmtMoney(val);
      if (k<1) requestAnimationFrame(step); else setTimeout(()=>card.classList.remove('bump'), 100);
    })(t0);
  };

  // Kezdeti placeholder
  numEl.textContent = '—';

  fetch(api.toString(), {mode:'cors', credentials:'omit', headers:{'Accept':'application/json'}})
    .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
    .then(j => {
      // Várjuk: { rows: [...], meta: {...} } és a sorban commission mező. :contentReference[oaicite:2]{index=2}
      const rows = Array.isArray(j && j.rows) ? j.rows : [];
      let commission = 0;
      if (rows.length) {
        commission = Number(rows[0].commission || 0);
      }
      const donationEUR = commission * 0.5; // Biznisz-szabály: donation = 0.5 × commission. :contentReference[oaicite:3]{index=3}
      const shown = (cur === 'HUF') ? donationEUR * (rateHUF||0) : donationEUR;
      animate(0, shown);
    })
    .catch(_ => {
      // Ha CORS vagy hálózati hiba, marad a placeholder, de a gomb működik
      numEl.textContent = '—';
    });
})();
</script>
Pár megjegyzés (praktikus, nem kötelező):
A data-href az általad létrehozott Sharity-oldalra mutasson (pl. egy egyedi NGO-landing). A kód automatikusan hozzáadja: d1=<ngo_slug>, opcionálisan amb=<amb>, plusz forrás-UTM-eket. Ez a Sharity redirect/riport logikához igazodik. 
A kártya a /wp-json/impactshop/v1/totals aggregációs végpontot hívja (group=ngo&ngo=<slug>), és a feliratozás pénznemét a data-currency + data-rate-huf határozza meg. Ha a REST-hívás nem érhető el (CORS), a gomb akkor is működik, csak az összeg helyén „—” marad.
Ha a jövőben átnevezitek vagy mozgatjátok a REST-végpontot, csak az api URL-t kell a snippetben cserélni. A mini-UI shortcodos biztosítékhoz tartozó alapstílusok és aliasok továbbra is a Sharity-oldalon élnek. 
Következő lépésként érdemes egy mintát gyorsan publikálni egy NGO oldalán, és ránézni mobilon is. Ha kérsz, gyártok hozzá „mini-brand packot” (színséma-variációk + ikonok), illetve készítek egy iframe-es alternatívát is olyan CMS-ekhez, ahol a beágyazott <script> tiltott.
Ezt mondtad:
Feltöltött kép
Működik, csak jobb elrendezés kellene: 
A ChatGPT ezt mondta:

Gondolkozás
Nagyon jó — működik! Itt egy letisztultabb, “két hasábos” (desktopon), mobilon szépen egymás alá folyó változat. Ugyanazokat a data-* mezőket használja, mint az előző, csak szebben tagolt a fej + összeg + CTA. Egy kódtömb, bemásolod és kész.
<!--
  Sharity NGO Card – új, két hasábos elrendezés (Gen Z, responsív)
  Kötelező: data-ngo, data-label, data-href, data-img
  Opcionális: data-text, data-accent, data-currency(HUF|EUR), data-rate-huf, data-from, data-to, data-status, data-cta, data-amb
-->
<div class="sharity-ngo-card-embed"
     data-ngo="mbe"
     data-label="Budapesti Mozgássérültek Egyesülete"
     data-img="https://example.org/logo-mbe.png"
     data-text="Minden vásárlás számít. Köszi, hogy támogatod a közösségünket! 🫶"
     data-href="https://app.sharity.hu/impactshop-new"
     data-accent="#7c3aed"
     data-currency="HUF"
     data-rate-huf="392.5"
     data-from="2025-09-01"
     data-to=""
     data-status="all"
     data-cta="Vásárolok és támogatok"
     data-amb=""
></div>
<script>
(() => {
  const host = document.currentScript.previousElementSibling;
  if (!host || !host.classList.contains('sharity-ngo-card-embed')) return;

  // ---- Beállítások ----
  const ngo     = (host.dataset.ngo || '').trim();
  const label   = (host.dataset.label || ngo || 'Szervezet').trim();
  const img     = (host.dataset.img || '').trim();
  const text    = (host.dataset.text || '').trim();
  const hrefRaw = (host.dataset.href || 'https://app.sharity.hu/impactshop').trim();
  const amb     = (host.dataset.amb || '').trim();
  const accent  = (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(host.dataset.accent||'') ? host.dataset.accent : '#7c3aed');
  const cur     = ((host.dataset.currency || 'HUF').toUpperCase() === 'EUR') ? 'EUR' : 'HUF';
  const rateHUF = parseFloat(host.dataset.rateHuf || '392');
  const from    = (host.dataset.from || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10));
  const to      = (host.dataset.to || new Date().toISOString().slice(0,10));
  const status  = (host.dataset.status || 'approved').toLowerCase();
  const ctaLbl  = (host.dataset.cta || 'Vásárolok és támogatok').trim();

  // ---- CTA link építés (paraméterek) ----
  const addParams = (url, params) => {
    const u = new URL(url, document.baseURI);
    Object.entries(params).forEach(([k,v]) => { if (v) u.searchParams.set(k, v); });
    if (!u.searchParams.has('utm_source')) u.searchParams.set('utm_source', 'ngo-embed');
    if (!u.searchParams.has('utm_medium')) u.searchParams.set('utm_medium', 'widget');
    if (!u.searchParams.has('utm_campaign')) u.searchParams.set('utm_campaign', ngo || 'ngo');
    return u.toString();
  };
  const href = addParams(hrefRaw, { d1: ngo, amb: amb, src: 'ngo-embed' });

  // ---- Formázás ----
  const fmtMoney = (v) => {
    if (cur === 'EUR') return '€ ' + (Number(v).toFixed(2).replace('.', ','));
    const n = Math.round(Number(v)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return n + ' Ft';
  };
  const escapeHtml = s => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  // ---- Árnyék-DOM + új, két hasábos layout ----
  const root = host.attachShadow({ mode: 'open' });
  root.innerHTML = `
    <style>
      :host{all:initial}
      .wrap{
        --accent:${accent}; --ink:#0f172a; --muted:#64748b; --br:#e5e7eb;
        font:600 14px/1.35 Inter, system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:var(--ink)
      }
      .card{
        position:relative; padding:22px; border:1px solid var(--br); border-radius:22px;
        background:
          radial-gradient(900px 560px at 10% -10%, color-mix(in srgb, var(--accent) 18%, transparent), transparent 40%),
          linear-gradient(180deg,#fff,#fafafa);
        box-shadow:0 18px 44px rgba(2,6,23,.10); overflow:hidden;
        display:grid; gap:18px; grid-template-columns: 1fr; align-items:center;
      }
      @media (min-width:720px){
        .card{ grid-template-columns: 1.2fr .8fr; }
      }
      .glow{position:absolute; inset:-40%; pointer-events:none; filter:blur(6px);
        background: radial-gradient(540px 220px at 20% 0%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 60%);}
      .sweep{position:absolute; inset:0; pointer-events:none; opacity:0;
        background:linear-gradient(120deg, transparent, color-mix(in srgb, var(--accent) 22%, transparent), transparent);
        transform:translateX(-120%)}
      .bump .sweep{animation:cardSweep .82s ease}
      @keyframes cardSweep{0%{transform:translateX(-140%);opacity:0}40%{opacity:1}100%{transform:translateX(140%);opacity:0}}

      /* Bal hasáb: fej + leírás */
      .left{display:grid; grid-template-columns: 72px 1fr; gap:14px}
      .logo{width:72px; height:72px; border-radius:18px; object-fit:cover; background:#fff; border:1px solid #eef2ff}
      .meta{display:flex; align-items:flex-start; justify-content:space-between; gap:12px}
      .name{font:900 clamp(16px,2.2vw,22px)/1.15 Inter}
      .rng{font:600 12px/1.2 Inter; color:var(--muted); white-space:nowrap}
      .txt{grid-column:1 / -1; margin-top:2px; color:#111827}

      /* Jobb hasáb: nagy szám + gomb */
      .stat{display:grid; gap:12px; align-content:center; justify-items:start}
      .label{font:900 clamp(16px,2.2vw,22px)/1.1 ui-serif, Georgia, 'Times New Roman', serif; letter-spacing:.01em}
      .amt{font:900 clamp(30px,6.8vw,52px)/1.02 Inter; letter-spacing:-.01em; color:#0b1220;
           text-shadow:0 2px 16px color-mix(in srgb, var(--accent) 24%, transparent)}
      .amt .num{display:inline-block; min-width:8ch; text-align:right}

      .cta{display:inline-flex; align-items:center; gap:10px; padding:12px 16px; border-radius:14px;
           font:800 14px/1 Inter; text-decoration:none; color:#fff;
           background:linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 60%, #111827));
           box-shadow:0 12px 28px color-mix(in srgb, var(--accent) 30%, transparent)}
      .cta:hover{transform:translateY(-1px)}
      .cta:active{transform:translateY(0)}
      .cta .arr{transition:transform .2s}
      .cta:hover .arr{transform:translateX(2px)}
      .foot{color:var(--muted); font:600 11px/1.2 Inter}

      @media (max-width:719px){
        .left{grid-template-columns:56px 1fr}
        .logo{width:56px; height:56px}
        .stat{justify-items:stretch}
        .cta{justify-content:center}
      }
    </style>

    <div class="wrap">
      <div class="card">
        <div class="glow"></div>
        <div class="sweep"></div>

        <!-- Bal hasáb -->
        <div class="left">
          <img class="logo" alt="" aria-hidden="true" src="${img || ''}" />
          <div class="meta">
            <div class="name">${escapeHtml(label || 'NGO')}</div>
            <div class="rng">${from} → ${to}</div>
          </div>
          ${text ? `<div class="txt">${escapeHtml(text)}</div>` : ''}
        </div>

        <!-- Jobb hasáb -->
        <div class="stat">
          <div class="label">Eddig gyűlt</div>
          <div class="amt"><span class="num">—</span></div>
          <a class="cta" href="${href}" target="_top" rel="noopener">
            ${escapeHtml(ctaLbl)} <span class="arr">↗</span>
          </a>
          <div class="foot">${cur} · árfolyam: ${Number(rateHUF||0).toFixed(2)} Ft/€ · Forrás: Sharity ImpactShop</div>
        </div>

      </div>
    </div>
  `;

  // ---- API hívás ----
  const api = new URL('https://app.sharity.hu/wp-json/impactshop/v1/totals');
  api.searchParams.set('from', from);
  api.searchParams.set('to',   to);
  api.searchParams.set('status', status);
  api.searchParams.set('group', 'ngo');
  api.searchParams.set('ngo', ngo);

  const card = root.querySelector('.card');
  const numEl= root.querySelector('.amt .num');

  const animate = (oldV, newV) => {
    if (!(newV>oldV)) { numEl.textContent = fmtMoney(newV); return; }
    const dur=900, t0=performance.now();
    card.classList.add('bump');
    (function step(t){
      const k=Math.min(1,(t-t0)/dur);
      const val = oldV + (newV-oldV)*(0.5-0.5*Math.cos(Math.PI*k));
      numEl.textContent = fmtMoney(val);
      if (k<1) requestAnimationFrame(step); else setTimeout(()=>card.classList.remove('bump'), 100);
    })(t0);
  };

  numEl.textContent = '—';
  fetch(api.toString(), {mode:'cors', credentials:'omit', headers:{'Accept':'application/json'}})
    .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
    .then(j => {
      const rows = Array.isArray(j && j.rows) ? j.rows : [];
      let commission = 0;
      if (rows.length) commission = Number(rows[0].commission || 0);
      const donationEUR = commission * 0.5;
      const shown = (cur === 'HUF') ? donationEUR * (rateHUF||0) : donationEUR;
      animate(0, shown);
    })
    .catch(_ => { numEl.textContent = '—'; });
})();
</script>
Mi változott vizuálisan?
Két hasáb: balra logó + név + időszak + szöveg; jobbra a nagy összeg és alatta a CTA.
Nagyobb, tisztább “Eddig gyűlt” tipográfia; a gomb jobban kézre esik.
Ugyanaz a paraméterezés, így csak beilleszted a régi helyére.
Ha szeretnéd, csinálok “compact” és “dark” variánst is ugyanebben a mintában.
Ezt mondtad:
kérem ezeket a verziókat is
A ChatGPT ezt mondta:

Gondolkozás
Jön a „kompakt” és a „dark” variáns — ugyanazzal az egyetlen beágyazható kóddal. Csak a data-variant attribútumot állítod:
data-variant="compact" → egysoros, kicsi, logó + név + nagy összeg + gomb.
data-variant="dark" → sötét témájú, két hasábos.
data-variant="compact dark" (vagy dark compact) → sötét + kompakt együtt.
Az összes többi data-* mező változatlanul működik.
<!--
  Sharity NGO Card – univerzális beágyazható widget (default | compact | dark)
  VÁLTOZATOK:
    data-variant=""
    data-variant="compact"
    data-variant="dark"
    data-variant="compact dark"
-->
<div class="sharity-ngo-card-embed"
     data-variant="compact"  <!-- állítsd: "", "compact", "dark", "compact dark" -->
     data-ngo="mbe"
     data-label="Budapesti Mozgássérültek Egyesülete"
     data-img="https://example.org/logo-mbe.png"
     data-text="Minden vásárlás számít. Köszi, hogy támogatod a közösségünket! 🫶"
     data-href="https://app.sharity.hu/impactshop-new"
     data-accent="#7c3aed"
     data-currency="HUF"
     data-rate-huf="392.5"
     data-from="2025-09-01"
     data-to=""
     data-status="all"
     data-cta="Vásárolok és támogatok"
     data-amb=""
></div>
<script>
(() => {
  const host = document.currentScript.previousElementSibling;
  if (!host || !host.classList.contains('sharity-ngo-card-embed')) return;

  // ---- Beállítások ----
  const ngo     = (host.dataset.ngo || '').trim();
  const label   = (host.dataset.label || ngo || 'Szervezet').trim();
  const img     = (host.dataset.img || '').trim();
  const text    = (host.dataset.text || '').trim();
  const hrefRaw = (host.dataset.href || 'https://app.sharity.hu/impactshop').trim();
  const amb     = (host.dataset.amb || '').trim();
  const accent  = (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(host.dataset.accent||'') ? host.dataset.accent : '#7c3aed');
  const cur     = ((host.dataset.currency || 'HUF').toUpperCase() === 'EUR') ? 'EUR' : 'HUF';
  const rateHUF = parseFloat(host.dataset.rateHuf || '392');
  const from    = (host.dataset.from || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10));
  const to      = (host.dataset.to || new Date().toISOString().slice(0,10));
  const status  = (host.dataset.status || 'approved').toLowerCase();
  const ctaLbl  = (host.dataset.cta || 'Vásárolok és támogatok').trim();
  const variant = (host.dataset.variant || '').toLowerCase();
  const isDark = /\bdark\b/.test(variant);
  const isCompact = /\bcompact\b/.test(variant);

  // ---- CTA link építés ----
  const addParams = (url, params) => {
    const u = new URL(url, document.baseURI);
    Object.entries(params).forEach(([k,v]) => { if (v) u.searchParams.set(k, v); });
    if (!u.searchParams.has('utm_source')) u.searchParams.set('utm_source', 'ngo-embed');
    if (!u.searchParams.has('utm_medium')) u.searchParams.set('utm_medium', 'widget');
    if (!u.searchParams.has('utm_campaign')) u.searchParams.set('utm_campaign', ngo || 'ngo');
    return u.toString();
  };
  const href = addParams(hrefRaw, { d1: ngo, amb: amb, src: 'ngo-embed' });

  // ---- Formázás ----
  const fmtMoney = (v) => {
    if (cur === 'EUR') return '€ ' + (Number(v).toFixed(2).replace('.', ','));
    const n = Math.round(Number(v)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return n + ' Ft';
  };
  const escapeHtml = s => String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  // ---- Shadow DOM + layout ----
  const root = host.attachShadow({ mode: 'open' });
  root.innerHTML = `
    <style>
      :host{all:initial}
      .wrap{
        --accent:${accent};
        --ink:${isDark ? '#e5e7eb' : '#0f172a'};
        --ink-strong:${isDark ? '#f8fafc' : '#0b1220'};
        --muted:${isDark ? '#94a3b8' : '#64748b'};
        --br:${isDark ? '#334155' : '#e5e7eb'};
        --bg-top:${isDark ? '#0b1220' : '#ffffff'};
        --bg-bot:${isDark ? '#0b1220' : '#fafafa'};
        font:600 14px/1.35 Inter, system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:var(--ink)
      }
      .card{
        position:relative; padding:${isCompact ? '14px' : '22px'}; border:1px solid var(--br); border-radius:${isCompact ? '16px' : '22px'};
        background:
          radial-gradient(900px 560px at 10% -10%, color-mix(in srgb, var(--accent) ${isDark ? '22%' : '18%'}, transparent), transparent 40%),
          linear-gradient(180deg,var(--bg-top),var(--bg-bot));
        ${isDark ? 'box-shadow: 0 22px 48px rgba(0,0,0,.45);' : 'box-shadow:0 18px 44px rgba(2,6,23,.10);'}
        overflow:hidden; display:grid; gap:${isCompact ? '12px' : '18px'}; grid-template-columns: 1fr; align-items:center;
      }
      @media (min-width:720px){
        .card{ grid-template-columns: ${isCompact ? 'auto 1fr auto' : '1.2fr .8fr'}; }
      }
      .glow{position:absolute; inset:-40%; pointer-events:none; filter:blur(6px);
        background: radial-gradient(540px 220px at 20% 0%, color-mix(in srgb, var(--accent) ${isDark ? '18%' : '14%'}, transparent), transparent 60%);}
      .sweep{position:absolute; inset:0; pointer-events:none; opacity:0;
        background:linear-gradient(120deg, transparent, color-mix(in srgb, var(--accent) 22%, transparent), transparent);
        transform:translateX(-120%)}
      .bump .sweep{animation:cardSweep .82s ease}
      @keyframes cardSweep{0%{transform:translateX(-140%);opacity:0}40%{opacity:1}100%{transform:translateX(140%);opacity:0}}

      /* Bal szekció */
      .left{display:grid; grid-template-columns: ${isCompact ? '48px 1fr' : '72px 1fr'}; gap:${isCompact ? '10px' : '14px'}; align-items:center}
      .logo{width:${isCompact ? '48px' : '72px'}; height:${isCompact ? '48px' : '72px'};
            border-radius:${isCompact ? '12px' : '18px'}; object-fit:cover; background:${isDark ? '#0b1220' : '#fff'};
            border:1px solid ${isDark ? '#1f2937' : '#eef2ff'}}
      .meta{display:flex; align-items:flex-start; justify-content:space-between; gap:12px}
      .name{font:900 ${isCompact ? '16px' : 'clamp(16px,2.2vw,22px)'}/1.15 Inter; color:var(--ink-strong)}
      .rng{font:600 12px/1.2 Inter; color:var(--muted); white-space:nowrap; ${isCompact ? 'display:none' : ''}}
      .txt{grid-column:1 / -1; margin-top:2px; color:${isDark ? '#e2e8f0' : '#111827'}; ${isCompact ? 'display:none' : ''}}

      /* Jobb szekció */
      .stat{display:grid; gap:${isCompact ? '6px' : '12px'}; align-content:center; ${isCompact ? 'justify-items:end' : 'justify-items:start'}}
      .label{font:900 ${isCompact ? '12px' : 'clamp(16px,2.2vw,22px)'}/1.1 ui-serif, Georgia, 'Times New Roman', serif; letter-spacing:.01em; ${isCompact ? 'color:var(--muted)' : ''}}
      .amt{font:900 ${isCompact ? '24px' : 'clamp(30px,6.8vw,52px)'}/1.02 Inter; letter-spacing:-.01em; color:var(--ink-strong);
           text-shadow:${isDark ? '0 2px 18px color-mix(in srgb, var(--accent) 35%, transparent)' : '0 2px 16px color-mix(in srgb, var(--accent) 24%, transparent)'}}
      .amt .num{display:inline-block; min-width:${isCompact ? '6ch' : '8ch'}; text-align:right}

      .cta{display:inline-flex; align-items:center; gap:10px; padding:${isCompact ? '10px 12px' : '12px 16px'}; border-radius:${isCompact ? '12px' : '14px'};
           font:800 ${isCompact ? '13px' : '14px'}/1 Inter; text-decoration:none; color:#fff;
           background:linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 60%, ${isDark ? '#0b1220' : '#111827'}));
           box-shadow:${isDark ? '0 12px 28px rgba(0,0,0,.5)' : '0 12px 28px color-mix(in srgb, var(--accent) 30%, transparent)'}}
      .cta:hover{transform:translateY(-1px)}
      .cta:active{transform:translateY(0)}
      .cta .arr{transition:transform .2s}
      .cta:hover .arr{transform:translateX(2px)}
      .foot{color:var(--muted); font:600 11px/1.2 Inter; ${isCompact ? 'display:none' : ''}}

      @media (max-width:719px){
        .card{ grid-template-columns: 1fr; }
        .stat{justify-items:${isCompact ? 'stretch' : 'stretch'}}
        .cta{justify-content:center}
      }
    </style>

    <div class="wrap">
      <div class="card">
        <div class="glow"></div>
        <div class="sweep"></div>

        <!-- Bal oldal -->
        <div class="left">
          <img class="logo" alt="" aria-hidden="true" src="${img || ''}" />
          <div class="meta">
            <div class="name">${escapeHtml(label || 'NGO')}</div>
            <div class="rng">${from} → ${to}</div>
          </div>
          ${text ? `<div class="txt">${escapeHtml(text)}</div>` : ''}
        </div>

        <!-- Jobb oldal -->
        <div class="stat">
          <div class="label">${isCompact ? 'Összeg' : 'Eddig gyűlt'}</div>
          <div class="amt"><span class="num">—</span></div>
          <a class="cta" href="${href}" target="_top" rel="noopener">
            ${escapeHtml(ctaLbl)} <span class="arr">↗</span>
          </a>
          <div class="foot">${cur} · árfolyam: ${Number(rateHUF||0).toFixed(2)} Ft/€ · Forrás: Sharity ImpactShop</div>
        </div>

      </div>
    </div>
  `;

  // ---- API hívás ----
  const api = new URL('https://app.sharity.hu/wp-json/impactshop/v1/totals');
  api.searchParams.set('from', from);
  api.searchParams.set('to',   to);
  api.searchParams.set('status', status);
  api.searchParams.set('group', 'ngo');
  api.searchParams.set('ngo', ngo);

  const card = root.querySelector('.card');
  const numEl= root.querySelector('.amt .num');

  const animate = (oldV, newV) => {
    if (!(newV>oldV)) { numEl.textContent = fmtMoney(newV); return; }
    const dur=900, t0=performance.now();
    card.classList.add('bump');
    (function step(t){
      const k=Math.min(1,(t-t0)/dur);
      const val = oldV + (newV-oldV)*(0.5-0.5*Math.cos(Math.PI*k));
      numEl.textContent = fmtMoney(val);
      if (k<1) requestAnimationFrame(step); else setTimeout(()=>card.classList.remove('bump'), 100);
    })(t0);
  };

  numEl.textContent = '—';
  fetch(api.toString(), {mode:'cors', credentials:'omit', headers:{'Accept':'application/json'}})
    .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP '+r.status)))
    .then(j => {
      const rows = Array.isArray(j && j.rows) ? j.rows : [];
      let commission = 0;
      if (rows.length) commission = Number(rows[0].commission || 0);
      const donationEUR = commission * 0.5;
      const shown = (cur === 'HUF') ? donationEUR * (rateHUF||0) : donationEUR;
      animate(0, shown);
    })
    .catch(_ => { numEl.textContent = '—'; });
})();
</script>
Gyors tippek:
Kompakt: cseréld a data-variant értéket compact-ra (vagy mobil landingeknél compact dark-ra).
Dark: data-variant="dark" — jól mutat sötét háttéren vagy hero-sáv alatt.
Ugyanaz a beállításkészlet marad (kép, szöveg, árfolyam, időablak, CTA, UTM + d1/amb).
Ha szeretnél külön “ultra-compact” (csak összeg + nyíl) mikro-badget, tudok adni egy még kisebb változatot is.




A ChatGPT hibázhat. Az OpenAI nem használ Sharity munkaterületi adatokat a modelljei betanításához.
