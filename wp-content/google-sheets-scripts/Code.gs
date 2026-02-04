/**
 * ImpactShop – Patrol v6.7 (Promo-preferred + Fallback, JSON label payload)
 * Forrás:  Shops!A:Z  (min: shop_slug, deals_feed; ajánlott: category)
 * Cél:     Banners!     (slug, img, href, label(JSON), category)
 * Flow:    Feed → (Google/Atom → Árukereső normalizálás | GENERIC) → pick 1/bolt → Banners → web → Fillout → /go-deal
 */

const SHEET_SHOPS       = 'Shops';
const SHEET_BANNERS     = 'Banners';
const SHEET_BANNERS_TMP = 'Banners_tmp';
const PS_KEY_CURSOR     = 'impactshop_patrol_cursor_v6_7';

const FILLOUT_BASE      = 'https://form.fillout.com/t/eM61RLkz6jus';

// — opcionális: kihagyandó boltok —
const SKIP_SHOPS = ['decathlon'];

// futási korlátok — TIM GUARD és társai (nagyobb katalógushoz hangolva)
const SHOPS_PER_RUN     = 18;      // volt: 10  — több bolt / kör
const MAX_RUN_MS        = 300000;  // volt: 220000 — ~4m40s (6 perc limit alatt marad)
const PREFLIGHT_MS      = 4000;    // volt: 6000  — gyorsabb „él-e a feed" próba
const PER_FEED_MS       = 26000;   // volt: 22000 — több idő az egyes feedek parse-olására
const SLEEP_BETWEEN     = 60;      // volt: 120   — gyorsabb lépkedés a sorok között

// — Dognet/affi preflight SKIP —
const PREFLIGHT_SKIP = [
  /(^|:\/\/)[^.]*dognet\./i,
  /\/go-deal(\?|$)/i
];

// --- Promo preferencia kapcsolók ---
const PREFER_PROMO        = true;   // akció legyen előnyben, de ha nincs, jöhet bármely jó termék
const MIN_DISCOUNT        = 0.15;   // "értelmes" akció küszöb (15%)
const OLDPRICE_BONUS      = 2.0;    // extra pont, ha van old_price + price
const SMALL_RANDOM_JITTER = 0.01;   // enyhe véletlen, hogy ne mindig ugyanaz jöjjön

// kulcsszavak (case-insensitive)
const PROMO_KEYWORDS = [
  'akció', 'akcios', 'akciós', 'kedvezmény', 'leárazás', 'leárazott', 'kiárusítás',
  'outlet', 'sale', '%', 'black friday', 'bf', 'kupon', 'coupon', 'deal'
];

// — util —
function _log(m){ try{console.log(m);}catch(_){ } }
function _slugifyHeader(s){ return (''+s).trim().toLowerCase().replace(/\s+/g,'_'); }
function _fmtPrice(n){ if (!isFinite(n)) return ''; const i=Math.round(+n); return String(i).replace(/\B(?=(\d{3})+(?!\d))/g,' ')+' Ft'; }
function _num(x){
  if (x==null) return NaN;
  let s = String(x).replace(/\s+/g,'').replace(/[^\d.,-]/g,'');
  const c = s.lastIndexOf(','), d = s.lastIndexOf('.');
  if (c>-1 && d>-1) s = (c>d) ? s.replace(/\./g,'').replace(',', '.') : s.replace(/,/g,'');
  else s = s.replace(',', '.');
  const n = parseFloat(s); return isFinite(n) ? n : NaN;
}
function _buildFillout(shop, productUrl){
  const u = Utilities.base64Encode(productUrl);
  return FILLOUT_BASE + '?shop=' + encodeURIComponent(shop) + '&u=' + encodeURIComponent(u);
}
function _preflight(url){
  for (var i=0;i<PREFLIGHT_SKIP.length; i++){
    if (PREFLIGHT_SKIP[i].test(url)) return true;
  }
  const t0 = Date.now();
  try{
    const r = UrlFetchApp.fetch(url, {method:'head', muteHttpExceptions:true, followRedirects:true, validateHttpsCertificates:true});
    const c = r.getResponseCode(); if (c>=200 && c<400) return true;
  }catch(_){}
  if (Date.now()-t0 > PREFLIGHT_MS) return false;
  try{
    const r = UrlFetchApp.fetch(url, {headers:{'Range':'bytes=0-2047'}, muteHttpExceptions:true, followRedirects:true, validateHttpsCertificates:true});
    const c = r.getResponseCode(); return c>=200 && c<500;
  }catch(_){ return false; }
}

// — XML tisztítás —
const CLAMP_LIMIT = 95000;
function _sanitizeXml(xml){
  xml = xml.replace(/&(?!#\d+;|#x[a-fA-F0-9]+;|amp;|lt;|gt;|quot;|apos;)/g, '&amp;');
  xml = xml.replace(/<!DOCTYPE[\s\S]*?\[[\s\S]*?\]>/gi,'');
  xml = xml.replace(/<!DOCTYPE[^>]*>/gi,'');
  xml = xml.replace(/<!ENTITY[\s\S]*?>/gi,'');
  xml = xml.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g,'');
  xml = xml.replace(/&(?!amp;|lt;|gt;|quot;|apos;)([a-zA-Z][\w-]*;)/g,'');
  return xml;
}
function _clampHugeText(xml){
  xml = xml.replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, (m, body) => body.length>CLAMP_LIMIT ? '<![CDATA['+body.slice(0,CLAMP_LIMIT)+']]>' : m);
  xml = xml.replace(/<(description|long_description|longdesc|desc)>([\s\S]*?)<\/\1>/gi,
    (m, tag, body) => body.length>CLAMP_LIMIT ? `<${tag}>${body.slice(0,CLAMP_LIMIT)}</${tag}>` : m);
  xml = xml.replace(/(\s[\w:-]+=")([^"]{95000,})(")/g, (m,a,b,c)=> a+b.slice(0,95000)+c);
  return xml;
}
// Árukereső-specifikus: hiányzó </ProductURL> pótlás
function _fixArukereso(xml){
  let changed=false;
  const rules = [
    {open: /<\s*ProductURL\s*>\s*<!\[CDATA\[/gi, close: /<\/\s*ProductURL\s*>/gi, fix: (m) => m + ']]></ProductURL>'},
    {open: /<\s*ProductURL\s*>\s*(?!<)/gi,       close: /<\/\s*ProductURL\s*>/gi, fix: (m) => m + '</ProductURL>'},
    {open: /<\s*product_url\s*>\s*<!\[CDATA\[/gi, close: /<\/\s*product_url\s*>/gi, fix: (m) => m + ']]></product_url>'},
    {open: /<\s*product_url\s*>\s*(?!<)/gi,       close: /<\/\s*product_url\s*>/gi, fix: (m) => m + '</product_url>'},
  ];
  rules.forEach(rule => {
    xml = xml.replace(new RegExp(rule.open.source + '([\\s\\S]*?)', 'gi'), (m, body, off, whole) => {
      const tail = whole.slice(off + m.length);
      if (!rule.close.test(tail)) { changed = true; return rule.fix(m); }
      return m;
    });
  });
  if (changed) _log('ARU ProductURL closers normalized');
  return xml;
}

// — Sheets —
function _ensureSheets(){
  const ss = SpreadsheetApp.getActive();
  const shB = ss.getSheetByName(SHEET_BANNERS)     || ss.insertSheet(SHEET_BANNERS);
  const shT = ss.getSheetByName(SHEET_BANNERS_TMP) || ss.insertSheet(SHEET_BANNERS_TMP);
  if (shB.getLastRow()===0) shB.getRange(1,1,1,5).setValues([['slug','img','href','label','category']]);
  if (shT.getLastRow()===0) shT.getRange(1,1,1,5).setValues([['slug','img','href','label','category']]);
  return {shB, shT};
}

// — JSON-es label írása —
function _writeTmpRow(shT, rowIdx, rec){
  const hasOp = isFinite(rec.old_price) && rec.old_price>0 && isFinite(rec.price);
  const payload = {
    title: rec.title || 'Ajánlat',
    price_num: isFinite(rec.price) ? Math.round(rec.price) : null,
    old_price_num: hasOp ? Math.round(rec.old_price) : null,
    discount_pct: (isFinite(rec.discount) && rec.discount>0) ? Math.round(rec.discount*100) : 0,
    price: isFinite(rec.price) ? _fmtPrice(rec.price) : '',
    old_price: hasOp ? _fmtPrice(rec.old_price) : ''
  };
  const href = _buildFillout(rec.shop, rec.url);
  shT.getRange(rowIdx,1,1,5).setValues([
    [rec.shop, rec.img || '', href, JSON.stringify(payload), rec.cat || 'Akciók']
  ]);
}

// — Promo detektor + pontozás —
function _looksPromoByText(title, cat){
  const hay = ((title||'')+' '+(cat||'')).toLowerCase();
  return PROMO_KEYWORDS.some(k => hay.includes(k));
}
function _isPromo(f){
  const d = (isFinite(f.discount) ? f.discount : 0);
  const hasStrike = isFinite(f.old_price) && isFinite(f.price) && f.price < f.old_price;
  return (d >= MIN_DISCOUNT) || hasStrike || _looksPromoByText(f.title, f.cat);
}
function _scoreCandidate(f){
  const base = (isFinite(f.discount) && f.discount>0 ? 10+f.discount : (isFinite(f.old_price)?3:1));
  const extras = (f.img?0.5:0) + (f.title?0.3:0) + (Math.random()*SMALL_RANDOM_JITTER);
  const hasBoth = (isFinite(f.old_price) && isFinite(f.price));
  const bonus = hasBoth ? OLDPRICE_BONUS : 0;
  return base + extras + bonus;
}

// — Séma detektálás (ARUKERESO / GOOGLE_RSS / GENERIC) —
const FORCE_ARU = ['visionexpress','4home','regiojatek','arukereso','maiakcio'];
function _detectSchemaByHeuristics(xml, shopSlug){
  try{
    const doc  = XmlService.parse(xml);
    const root = doc.getRootElement();
    const tag  = String(root.getName()||'').toLowerCase();

    if (tag === 'products'){
      const hasProduct = /<\s*(?:\w+:)?product\b/i.test(xml);
      if (hasProduct) return 'ARUKERESO';
      return 'GENERIC';
    }
    if (tag === 'rss' || tag === 'feed') return 'GOOGLE_RSS';
  }catch(_){}

  if (/<\s*products\b/i.test(xml)){
    return /<\s*(?:\w+:)?product\b/i.test(xml) ? 'ARUKERESO' : 'GENERIC';
  }
  if (/<\s*(?:rss|feed)\b/i.test(xml)) return 'GOOGLE_RSS';

  const hasProduct  = /<\s*(?:\w+:)?product\b/i.test(xml);
  const looksGoogle = /\bg:(?:price|image_link|link)\b/i.test(xml);
  if (!hasProduct && looksGoogle) return 'GOOGLE_RSS';

  if (FORCE_ARU.indexOf((shopSlug||'').toLowerCase()) !== -1) return 'ARUKERESO';
  return 'GENERIC';
}

// — Árukereső (mély keresés, chunk-parse) —
const ARU_TITLE_TAGS = ['name','title','productname','Name','Title','ProductName'];
const ARU_URL_TAGS   = ['product_url','producturl','url','link','deeplink','ProductURL','ProductUrl','PRODUCTURL'];
const ARU_IMG_TAGS   = ['image_url','imageurl','imgurl','image','picture','image_urle','ImageURL','ImageUrl','images','image_link'];
const ARU_CAT_TAGS   = ['category','categorytext','category_name','cat','Category','CategoryText','Category_Name'];
const ARU_PRICE_TAGS     = ['price','price_vat','gross_price','Price','Gross_Price'];
const ARU_OLDPRICE_TAGS  = [
  'old_price','oldprice','price_old','price_before','price_before_discount','before_price',
  'original_price','originalprice','list_price','regular_price','compare_at_price',
  'msrp','rrp','retail_price','was_price',
  'Old_Price','Original_Price','List_Price'
];
const ARU_SALEPRICE_TAGS = ['sale_price','special_price','promo_price','Sale_Price','Special_Price','Promo_Price'];
const ARU_AVAIL_TAGS     = ['basket_disabled','availability','in_stock','Basket_Disabled','Availability','In_Stock'];

function _deepPickCI(el, names, nodeBudget){
  const want = names.map(s=>String(s).toLowerCase());
  const q=[el]; let seen=0;
  while(q.length && seen<nodeBudget){
    const cur=q.shift(); seen++;
    const kids = cur.getChildren();
    for (let i=0;i<kids.length;i++){
      const k = kids[i];
      const nm = String(k.getName()||'').toLowerCase();
      if (want.indexOf(nm)!==-1){
        const t=(k.getText()||'').trim();
        if (t) return t;
      }
      q.push(k);
    }
  }
  for (const nm of names){
    const ch = el.getChild(nm);
    if (ch){ const t=(ch.getText()||'').trim(); if (t) return t; }
  }
  return '';
}
function _deepPickNumCI(el, names, nodeBudget){
  const t=_deepPickCI(el,names,nodeBudget);
  const n=_num(t); return isFinite(n)?n:NaN;
}
function _grepUrlFromText(el){
  const raw = (el.getText()||'');
  const m = raw.match(/https?:\/\/[^\s"'<>]+/i);
  return m? m[0] : '';
}
function _grepImgFromText(el){
  const raw = (el.getText()||'');
  const m = raw.match(/https?:\/\/[^\s"'<>]+?\.(?:jpg|jpeg|png|webp)(?:\?[^\s"'<>]*)?/i);
  return m? m[0] : '';
}
function _pickArukeresoOneDeep(prodEl, fallbackCat){
  const title = _deepPickCI(prodEl, ARU_TITLE_TAGS, 600);
  let   url   = _deepPickCI(prodEl, ARU_URL_TAGS,   600) || _grepUrlFromText(prodEl);
  let   img   = _deepPickCI(prodEl, ARU_IMG_TAGS,   600);
  if (!img) img = _grepImgFromText(prodEl);
  if (!img){
    const imagesNode = prodEl.getChild('images') || prodEl.getChild('Images');
    if (imagesNode){
      const firstImg = _deepPickCI(imagesNode, ['image_url','imgurl','image','image_link','ImageURL'], 200);
      if (firstImg) img = firstImg;
    }
  }
  if (url) url = url.trim().split(/\s/)[0];
  const cat   = _deepPickCI(prodEl, ARU_CAT_TAGS, 300) || fallbackCat || 'Akciók';

  const p  = _deepPickNumCI(prodEl, ARU_PRICE_TAGS,     400);
  let   op = _deepPickNumCI(prodEl, ARU_OLDPRICE_TAGS,  300);
  const sp = _deepPickNumCI(prodEl, ARU_SALEPRICE_TAGS, 300);
  if (!isFinite(op) && isFinite(sp) && isFinite(p) && sp<p) op = p;
  let price = NaN;
  if (isFinite(sp) && isFinite(p) && sp<p) price = sp;
  else if (isFinite(p)) price = p;
  else if (isFinite(sp)) price = sp;
  else if (isFinite(op)) price = op;

  const avail = (_deepPickCI(prodEl, ARU_AVAIL_TAGS, 200)||'').toLowerCase();
  const out = (avail.includes('out of stock') || avail==='1' || avail==='true');

  let discount = 0;
  if (isFinite(op) && isFinite(price) && op>0 && price<op) discount = (op-price)/op;
  else if (isFinite(sp) && isFinite(p) && p>0 && sp<p)     discount = (p-sp)/p;

  let keys = 0; if (url) keys++; if (title) keys++; if (img) keys++;
  if (keys<2) return null;

  const f = { title, url, img, cat, price, old_price: op, discount, out };
  f.score = _scoreCandidate(f);
  return f;
}
function _parseArukereso(xml, shopSlug, fallbackCat, timeBudgetMs){
  const t0 = Date.now();
  let bestPromo=null, bestPromoScore=-1e9;
  let bestAny=null, bestAnyScore=-1e9;

  const re = /<\s*(?:\w+:)?product\b[\s\S]*?<\/\s*(?:\w+:)?product\s*>/gi;
  const chunks = xml.match(re) || [];
  let seen=0, keptPromo=0, keptAny=0;

  for (let i=0;i<chunks.length;i++){
    if (Date.now()-t0 > timeBudgetMs) break;
    const chunk = `<?xml version="1.0" encoding="UTF-8"?><products>${chunks[i]}</products>`;
    try{
      const doc = XmlService.parse(chunk);
      const prodEl = doc.getRootElement().getChildren()[0];
      if (!prodEl) continue;
      seen++;

      const f = _pickArukeresoOneDeep(prodEl, fallbackCat);
      if (!f || !f.url || f.out) continue;

      if (PREFER_PROMO && _isPromo(f)){
        if (f.score > bestPromoScore){ bestPromo = {shop:shopSlug, ...f}; bestPromoScore = f.score; }
        keptPromo++;
      }
      if (f.score > bestAnyScore){ bestAny = {shop:shopSlug, ...f}; bestAnyScore = f.score; keptAny++; }

    }catch(_){ /* nyeljük */ }
  }
  _log(`DIAG ${shopSlug}: ARU seen=${seen} keptPromo=${keptPromo} keptAny=${keptAny}`);
  return bestPromo || bestAny || null;
}

// — Google/Atom → Árukereső normalizáló —
function _normalizeGoogleLikeToArukeresoXML(xmlRaw){
  const CLAMP = 80000;
  let xml = xmlRaw
    .replace(/&(?!#\d+;|#x[a-fA-F0-9]+;|amp;|lt;|gt;|quot;|apos;)/g,'&amp;')
    .replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g,'')
    .replace(/<!DOCTYPE[\s\S]*?>/gi,'')
    .replace(/<!ENTITY[\s\S]*?>/gi,'');

  function _numStr(s){
    if (!s) return '';
    const t = String(s).replace(/[^\d.,-]/g,'').replace(/(\d)[\s](?=\d)/g,'$1');
    const c=t.lastIndexOf(','), d=t.lastIndexOf('.');
    let n=t; if (c>-1 && d>-1) n = (c>d)? t.replace(/\./g,'').replace(',', '.') : t.replace(/,/g,'');
    else n = t.replace(',', '.');
    const v = parseFloat(n);
    return isFinite(v) ? String(v) : '';
  }
  function _first(){ for (let i=0;i<arguments.length;i++){ const v=(arguments[i]||'').trim(); if (v) return v; } return ''; }

  let doc, rootName=''; 
  try { doc = XmlService.parse(xml); rootName = (doc.getRootElement().getName()||'').toLowerCase(); }
  catch(e){ return ''; }

  let items=[];
  if (rootName==='rss'){
    const ch = doc.getRootElement().getChild('channel');
    items = ch ? ch.getChildren('item') : [];
  }else if (rootName==='feed'){
    items = doc.getRootElement().getChildren('entry');
  }else{
    items = doc.getRootElement().getChildren('item');
    if (!items || !items.length) items = doc.getRootElement().getChildren('entry');
  }

  function g(el, local){
    try{
      const ns = el.getNamespace('g') || Namespace.getNamespace('g','http://base.google.com/ns/1.0');
      const c  = el.getChild(local, ns);
      return c ? (c.getText()||'').trim() : '';
    }catch(_){ return ''; }
  }
  function ch(el, name){ try{ const c=el.getChild(name); return c? (c.getText()||'').trim():''; }catch(_){ return ''; } }
  function attr(el,name){ try{ const a=el.getAttribute(name); return a? (a.getValue()||'').trim():''; }catch(_){ return ''; } }

  let out = ['<products>'];
  for (let i=0; i<items.length; i++){
    const it = items[i];

    const title = _first(ch(it,'title'), g(it,'title'));

    let url = _first(g(it,'link'), ch(it,'link'), attr(it,'href'));
    if (!url){
      const links = it.getChildren('link') || [];
      for (let k=0;k<links.length;k++){
        const L=links[k], rel=attr(L,'rel'); const href=attr(L,'href');
        const val = href || (L.getText()||'').trim();
        if (!rel || rel==='alternate'){ url = val; if (url) break; }
      }
    }
    if (url) url = url.trim().split(/\s/)[0];

    let img = _first(g(it,'image_link'), (function(){ const enc=it.getChild('enclosure'); return enc? attr(enc,'url'):''; })());
    if (!img){
      const kids = it.getChildren() || [];
      for (let k=0;k<kids.length;k++){
        const nm = String(kids[k].getName()||'').toLowerCase();
        if (nm==='content' || nm==='thumbnail'){ const u=attr(kids[k],'url'); if (u){ img=u; break; } }
      }
    }
    if (!img){
      const raw = (it.getText()||'');
      const m = raw.match(/https?:\/\/[^\s"'<>]+?\.(?:jpg|jpeg|png|webp)(?:\?[^\s"'<>]*)?/i);
      if (m) img = m[0];
    }

    const cat = _first(g(it,'product_type'), g(it,'google_product_category'), ch(it,'category'), 'Akciók');

    const price = _numStr(_first(g(it,'price'), ch(it,'price')));
    const sale  = _numStr(_first(g(it,'sale_price'), ch(it,'sale_price')));
    let   oldp  = _numStr(_first(g(it,'regular_price'), ch(it,'regular_price'), g(it,'compare_at_price'), ch(it,'compare_at_price')));
    if (!oldp && sale && price && parseFloat(sale)<parseFloat(price)) oldp = price;

    let avail = _first(g(it,'availability'), ch(it,'availability')).toLowerCase();
    if (avail.length > 40) avail = avail.slice(0,40);

    const keys = (url?1:0) + (title?1:0) + (img?1:0);
    if (keys < 2) continue;

    function esc(t){ return (''+t).replace(/[<&>]/g, m => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[m])); }
    out.push('<product>');
    out.push('<name><![CDATA[' + (title.length>CLAMP? title.slice(0,CLAMP): title) + ']]></name>');
    out.push('<product_url><![CDATA[' + (url.length>CLAMP? url.slice(0,CLAMP): url) + ']]></product_url>');
    if (img) out.push('<image_url><![CDATA[' + (img.length>CLAMP? img.slice(0,CLAMP): img) + ']]></image_url>');
    if (cat) out.push('<category><![CDATA[' + (cat.length>CLAMP? cat.slice(0,CLAMP): cat) + ']]></category>');
    if (price) out.push('<price>'+esc(price)+'</price>');
    if (oldp)  out.push('<old_price>'+esc(oldp)+'</old_price>');
    if (sale)  out.push('<sale_price>'+esc(sale)+'</sale_price>');
    if (avail) out.push('<availability><![CDATA['+avail+']]></availability>');
    out.push('</product>');
  }
  out.push('</products>');
  return out.join('');
}

// — GENERIC products parser (bármilyen <products> struktúrára) —
function _text(el){ try{ return (el.getText()||'').trim(); }catch(_){ return ''; } }
function _attr(el,n){ try{ const a=el.getAttribute(n); return a? (a.getValue()||'').trim() : ''; }catch(_){ return ''; } }
function _childFirstAny(el, names){
  for (const nm of names){
    const ch = el.getChild(nm);
    if (ch){
      const href=_attr(ch,'href')||_attr(ch,'url')||_attr(ch,'src');
      const t=_text(ch);
      if (href) return href;
      if (t)    return t;
    }
  }
  return '';
}
function _grepFirst(re, s){ const m=(s||'').match(re); return m? m[0] : ''; }

function _pickGenericOne(el, fallbackCat){
  const title = _childFirstAny(el, ['name','title','productname','headline']) || _grepFirst(/[^\n\r]{8,120}/, _text(el));
  let url = _childFirstAny(el, ['product_url','deeplink','link','url']) || _attr(el,'href') || _attr(el,'url') || _grepFirst(/https?:\/\/[^\s"'<>]+/i, _text(el));
  if (url) url = url.trim().split(/\s/)[0];
  let img = _childFirstAny(el, ['image_url','image_link','image','imgurl','picture','img']) || _grepFirst(/https?:\/\/[^\s"'<>]+?\.(?:jpg|jpeg|png|webp)(?:\?[^\s"'<>]*)?/i, _text(el));
  const cat = _childFirstAny(el, ['category','categorytext','product_type','google_product_category']) || fallbackCat || 'Akciók';

  function toNum(s){
    if (!s) return NaN;
    let t=String(s).replace(/[^\d.,-]/g,''); const c=t.lastIndexOf(','), d=t.lastIndexOf('.');
    t = (c>-1 && d>-1) ? ((c>d)? t.replace(/\./g,'').replace(',', '.') : t.replace(/,/g,'')) : t.replace(',', '.');
    const n=parseFloat(t); return isFinite(n)?n:NaN;
  }
  const price = toNum(_childFirstAny(el, ['price','price_vat','gross_price','g:price','regular_price','compare_at_price']));
  let   sale  = toNum(_childFirstAny(el, ['sale_price','promo_price','special_price','g:sale_price']));
  let   oldp  = toNum(_childFirstAny(el, [
    'old_price','oldprice','price_old','price_before','price_before_discount','before_price',
    'original_price','originalprice','list_price','regular_price','compare_at_price',
    'msrp','rrp','retail_price','was_price'
  ]));
  if (!isFinite(oldp) && isFinite(sale) && isFinite(price) && sale<price) oldp = price;

  const avail = (_childFirstAny(el, ['availability','in_stock'])||'').toLowerCase();
  const out = ['out of stock','not available','out_of_stock','sold out','0','false','no'].includes(avail);

  let keys=0; if (url) keys++; if (title) keys++; if (img) keys++;
  if (keys<2) return null;

  let discount=0, p=isFinite(price)?price:NaN;
  if (isFinite(oldp) && isFinite(p) && oldp>0 && p<oldp) discount=(oldp-p)/oldp;
  else if (isFinite(sale) && isFinite(price) && price>0 && sale<price) discount=(price-sale)/price;

  const f = { title, url, img, cat, price: p, old_price: oldp, discount, out };
  f.score = _scoreCandidate(f);
  return f;
}
function _parseGenericProducts(xml, shopSlug, fallbackCat, timeBudgetMs){
  const t0=Date.now();
  let bestPromo=null, bestPromoScore=-1e9;
  let bestAny=null, bestAnyScore=-1e9;
  let seen=0, keptPromo=0, keptAny=0;

  let doc; try{ doc=XmlService.parse(xml);}catch(_){ return null; }
  const root=doc.getRootElement();

  let candidates=[];
  const tagsets=['product','item','entry','offer','row','record'];
  for (const nm of tagsets){
    const xs=root.getChildren(nm); if (xs && xs.length) candidates = candidates.concat(xs);
  }
  if (!candidates.length) candidates = root.getChildren() || [];

  for (let i=0;i<candidates.length;i++){
    if (Date.now()-t0 > timeBudgetMs) break;
    seen++;
    try{
      const f=_pickGenericOne(candidates[i], fallbackCat);
      if (!f || !f.url || f.out) continue;

      if (PREFER_PROMO && _isPromo(f)){
        if (f.score > bestPromoScore){ bestPromo = {shop:shopSlug, ...f}; bestPromoScore = f.score; }
        keptPromo++;
      }
      if (f.score > bestAnyScore){ bestAny = {shop:shopSlug, ...f}; bestAnyScore = f.score; keptAny++; }

    }catch(_){}
  }
  _log(`DIAG ${shopSlug}: GENERIC seen=${seen} keptPromo=${keptPromo} keptAny=${keptAny}`);
  return bestPromo || bestAny || null;
}

// — fő kivonó —
function _pickOneFromXml(xml, shopSlug, fallbackCat, timeBudgetMs){
  try{
    const mRoot = xml.match(/<([A-Za-z_][\w:.-]*)\b[^>]*>/);
    if (mRoot){
      const rootTag = mRoot[1];
      const xmlns = (mRoot[0].match(/xmlns(?::\w+)?="[^"]+"/g)||[]).slice(0,4).join(' ');
      _log(`DIAG-ROOT ${shopSlug}: <${rootTag}> ${xmlns}`);
    }
  }catch(_){}

  let schema = _detectSchemaByHeuristics(xml, shopSlug);
  _log(`DIAG ${shopSlug}: SCHEMA=${schema}`);

  if (schema === 'ARUKERESO'){
    const best = _parseArukereso(xml, shopSlug, fallbackCat, timeBudgetMs);
    if (best && best.url){ try{ _preflight(best.url); }catch(_){ } return best; }
  } else if (schema === 'GOOGLE_RSS'){
    try{
      const norm=_normalizeGoogleLikeToArukeresoXML(xml);
      if (norm){
        const aruXml=_fixArukereso(_clampHugeText(_sanitizeXml(norm)));
        const best=_parseArukereso(aruXml, shopSlug, fallbackCat, timeBudgetMs);
        if (best && best.url){ try{ _preflight(best.url); }catch(_){ } return best; }
      }
    }catch(_){}
  } else { // GENERIC
    const best=_parseGenericProducts(_clampHugeText(_sanitizeXml(xml)), shopSlug, fallbackCat, timeBudgetMs);
    if (best && best.url){ try{ _preflight(best.url); }catch(_){ } return best; }
  }
  return null;
}

// — RESET + RUN —
function impactshop巡_RESET(){
  const ss = SpreadsheetApp.getActive();
  const {shB, shT} = _ensureSheets();
  shB.clearContents(); shT.clearContents();
  shB.getRange(1,1,1,5).setValues([['slug','img','href','label','category']]);
  shT.getRange(1,1,1,5).setValues([['slug','img','href','label','category']]);
  PropertiesService.getScriptProperties().setProperty(PS_KEY_CURSOR,'1');
  _log('RESET ok (v6.7 JSON)');
}

function impactshop巡_RUN(){
  const t0 = Date.now();
  const ss = SpreadsheetApp.getActive();
  const shShops = ss.getSheetByName(SHEET_SHOPS);
  if (!shShops) throw new Error('Hiányzik a Shops sheet.');
  const {shB, shT} = _ensureSheets();

  const data = shShops.getDataRange().getValues();
  if (!data || data.length<2) return;
  const head = data[0].map(_slugifyHeader);
  const col = n => head.indexOf(n);
  const cSlug = col('shop_slug'), cFeed = col('deals_feed'), cCat = col('category');
  if (cSlug<0 || cFeed<0) throw new Error('A Shops lapon kell shop_slug és deals_feed oszlop.');
  const nRows = data.length - 1;

  const ps = PropertiesService.getScriptProperties();
  let cursor = parseInt(ps.getProperty(PS_KEY_CURSOR) || '1', 10);
  if (!isFinite(cursor) || cursor<1) cursor = 1;

  let wrote = 0, processed = 0;

  for (let i=0; i<nRows; i++){
    if (processed >= SHOPS_PER_RUN) break;
    if (Date.now() - t0 > MAX_RUN_MS) { _log('TIME-GUARD: vége, folyt. következő körben'); break; }

    const r = cursor + i;
    if (r >= data.length) break;
    const row = data[r];

    const slug = (row[cSlug]||'').toString().trim();
    const feed = (row[cFeed]||'').toString().trim();
    const cat  = cCat>=0 ? (row[cCat]||'').toString().trim() : '';
    if (!slug || !feed) { continue; }
    if (SKIP_SHOPS.includes(slug.toLowerCase())) { _log('SKIP ' + slug); continue; }

    _log('START ' + slug);
    const tStart = Date.now();
    try{
      if (!_preflight(feed)) _log('WARN preflight timeout: ' + slug);

      const resp = UrlFetchApp.fetch(feed, {
        muteHttpExceptions:true, followRedirects:true, validateHttpsCertificates:true,
        headers:{'Accept':'application/xml, text/xml, application/atom+xml, */*'}
      });
      const code = resp.getResponseCode();
      if (code<200 || code>=300) throw new Error('HTTP '+code);

      function _decode(r){
        try{ return r.getContentText(); }catch(_){}
        try{ return r.getContentText('UTF-8'); }catch(_){}
        try{ return r.getContentText('ISO-8859-2'); }catch(_){}
        try{ return r.getContentText('windows-1250'); }catch(_){}
        return r.getContentText();
      }
      let xml = _decode(resp);

      // előtisztítás
      xml = _sanitizeXml(_clampHugeText(xml));
      if (slug.toLowerCase()==='arukereso' || /<\s*ProductURL\b/i.test(xml)) xml = _fixArukereso(xml);

      const remain = Math.max(6000, PER_FEED_MS - (Date.now()-tStart));
      const picked = _pickOneFromXml(xml, slug, cat, remain);
      if (!picked) throw new Error('NINCS_KINYERHETO_TETEL');

      const targetRow = 1 + r; // determinisztikus sor
      _writeTmpRow(shT, targetRow, picked);
      wrote++; processed++;
      _log(`OK ${slug} (${Date.now()-tStart} ms)`);
    }catch(e){
      const targetRow = 1 + r;
      const href = FILLOUT_BASE + '?shop=' + encodeURIComponent(slug) + '&u=' + encodeURIComponent(Utilities.base64Encode(feed));
      shT.getRange(targetRow,1,1,5).setValues([[slug,'',href,`{"error":"${String(e.message).slice(0,160)}"}`,cat||'' ]]);
      processed++;
      _log(`ERR ${slug}: ${e && e.message ? e.message : e}`);
    }

    Utilities.sleep(SLEEP_BETWEEN);
  }

  const all = shT.getDataRange().getValues();
  if (all && all.length>=1){
    shB.clearContents();
    shB.getRange(1,1,all.length, Math.min(5, all[0].length)).setValues(all);
  }

  cursor = cursor + processed;
  if (cursor >= data.length) cursor = 1;
  ps.setProperty(PS_KEY_CURSOR, String(cursor));

  _log(`DONE run v6.7 (JSON): processed=${processed}, wrote=${wrote}, next=${cursor}, totalRows=${(all.length-1)}`);
}
