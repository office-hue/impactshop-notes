/** ImpactShop – Árukereső-only Runner (namespaced, no globals collision)
 *  Séma: <products><product>…</product></products> (Árukereső kötelezők: name, product_url, image_url, price, …)  */
(function (GLOBAL) {
  'use strict';

  // ---- KONFIG (privát) ----
  const C = {
    SHEET_SHOPS:       'Shops',
    SHEET_BANNERS:     'Banners',
    SHEET_BANNERS_TMP: 'Banners_tmp',
    PS_KEY_CURSOR:     'impactshop_patrol_cursor_ARU_v2',
    FILLOUT_BASE:      'https://form.fillout.com/t/eM61RLkz6jus',
    SHOPS_PER_RUN:     20,
    MAX_RUN_MS:        220000,
    PREFLIGHT_MS:      6000,
    PER_FEED_MS:       22000,
    SLEEP_BETWEEN:     120,
    PREFLIGHT_SKIP:    [/(:\/\/)[^.]*dognet\./i, /\/go-deal(\?|$)/i]
  };

  // ---- UTILS ----
  function log(m){ try{console.log(m);}catch(_){ } }
  function slugify(s){ return (''+s).trim().toLowerCase().replace(/\s+/g,'_'); }
  function fmtFt(n){ if (!isFinite(n)) return ''; const i=Math.round(+n); return String(i).replace(/\B(?=(\d{3})+(?!\d))/g,' ')+' Ft'; }
  function num(x){
    if (x==null) return NaN;
    let s = String(x).replace(/\s+/g,'').replace(/[^\d.,-]/g,'');
    const c = s.lastIndexOf(','), d = s.lastIndexOf('.');
    if (c>-1 && d>-1) s = (c>d) ? s.replace(/\./g,'').replace(',', '.') : s.replace(/,/g,'');
    else s = s.replace(',', '.');
    const n = parseFloat(s); return isFinite(n) ? n : NaN;
  }
  function fillout(shop, productUrl){
    const u = Utilities.base64Encode(productUrl);
    return C.FILLOUT_BASE + '?shop=' + encodeURIComponent(shop) + '&u=' + encodeURIComponent(u);
  }
  function preflight(url){
    for (var i=0;i<C.PREFLIGHT_SKIP.length;i++){
      if (C.PREFLIGHT_SKIP[i].test(url)) return true;
    }
    const t0 = Date.now();
    try{
      const r = UrlFetchApp.fetch(url, {method:'head', muteHttpExceptions:true, followRedirects:true, validateHttpsCertificates:true});
      const c = r.getResponseCode(); if (c>=200 && c<400) return true;
    }catch(_){}
    if (Date.now()-t0 > C.PREFLIGHT_MS) return false;
    try{
      const r = UrlFetchApp.fetch(url, {headers:{'Range':'bytes=0-2047'}, muteHttpExceptions:true, followRedirects:true, validateHttpsCertificates:true});
      const c = r.getResponseCode(); return c>=200 && c<500;
    }catch(_){ return false; }
  }

  // ---- XML sanitizers ----
  function sanitize(xml){
    xml = xml.replace(/&(?!#\d+;|#x[a-fA-F0-9]+;|amp;|lt;|gt;|quot;|apos;)/g, '&amp;');
    xml = xml.replace(/<!DOCTYPE[\s\S]*?\[[\s\S]*?\]>/gi,'');
    xml = xml.replace(/<!DOCTYPE[^>]*>/gi,'');
    xml = xml.replace(/<!ENTITY[\s\S]*?>/gi,'');
    xml = xml.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F]/g,'');
    xml = xml.replace(/&(?!amp;|lt;|gt;|quot;|apos;)([a-zA-Z][\w-]*;)/g,'');
    return xml;
  }
  function clamp(xml){
    xml = xml.replace(/<!\[CDATA\[([\s\S]*?)\]\]>/g, (m, body) => body.length>95000 ? '<![CDATA['+body.slice(0,95000)+']]>' : m);
    xml = xml.replace(/<(description|long_description|longdesc|desc)>([\s\S]*?)<\/\1>/gi,
      (m, tag, body) => body.length>95000 ? `<${tag}>${body.slice(0,95000)}</${tag}>` : m);
    xml = xml.replace(/(\s[\w:-]+=")([^"]{95000,})(")/g, (m,a,b,c)=> a+b.slice(0,95000)+c);
    return xml;
  }
  function fixClosers(xml){
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
    if (changed){
      const closers = (xml.match(/<\/ProductURL>/gi)||[]).length + (xml.match(/<\/product_url>/gi)||[]).length;
      log(`DIAG ARU: ProductURL closers normalized, total ~${closers}`);
    }
    return xml;
  }

  // ---- Sheets helpers ----
  function ensureSheets(){
    const ss = SpreadsheetApp.getActive();
    const shB = ss.getSheetByName(C.SHEET_BANNERS)     || ss.insertSheet(C.SHEET_BANNERS);
    const shT = ss.getSheetByName(C.SHEET_BANNERS_TMP) || ss.insertSheet(C.SHEET_BANNERS_TMP);
    if (shB.getLastRow()===0) shB.getRange(1,1,1,5).setValues([['slug','img','href','label','category']]);
    if (shT.getLastRow()===0) shT.getRange(1,1,1,5).setValues([['slug','img','href','label','category']]);
    return {shB, shT};
  }
  function writeTmp(shT, rowIdx, rec){
    const hasOp = isFinite(rec.old_price) && rec.old_price>0 && isFinite(rec.price);
    const p  = isFinite(rec.price) ? fmtFt(rec.price) : '';
    const op = hasOp ? fmtFt(rec.old_price) : '';
    const title = rec.title || 'Ajánlat';
    const label = (p && op) ? `${title} — ${p} (régi: ${op})` : (p ? `${title} — ${p}` : title);
    const href  = fillout(rec.shop, rec.url);
    shT.getRange(rowIdx,1,1,5).setValues([[rec.shop, rec.img || '', href, label, rec.cat || 'Akciók']]);
  }

  // ---- Árukereső mezők ----
  const ARU_TITLE = ['name','title','productname'];
  const ARU_URL   = ['product_url','producturl','url','link','ProductURL'];
  const ARU_IMG   = ['image_url','imageurl','imgurl','image','picture','image_urle'];
  const ARU_CAT   = ['category','categorytext','category_name','cat'];
  const ARU_PRICE     = ['price','price_vat','gross_price'];
  const ARU_OLDPRICE  = ['old_price','price_before','original_price','list_price'];
  const ARU_SALEPRICE = ['sale_price','special_price','promo_price'];
  const ARU_AVAIL     = ['basket_disabled','availability','in_stock'];

  function childText(el, name){ const ch = el.getChild(name); return ch ? (ch.getText()||'').trim() : ''; }
  function childNum(el, names){ for (const nm of names){ const t=childText(el,nm), n=num(t); if (isFinite(n)) return n; } return NaN; }
  function childFirst(el, names){ for (const nm of names){ const t=childText(el,nm); if (t) return t; } return ''; }

  function pickOne(prodEl, fallbackCat){
    const title = childFirst(prodEl, ARU_TITLE);
    const url   = childFirst(prodEl, ARU_URL);
    let   img   = childFirst(prodEl, ARU_IMG);
    if (!img){
      const raw = (prodEl.getText()||'');
      const m = raw.match(/https?:\/\/[^\s"'<>]+?\.(?:jpg|jpeg|png|webp)/i);
      if (m) img = m[0];
    }
    const cat   = childFirst(prodEl, ARU_CAT) || fallbackCat || 'Akciók';

    const p  = childNum(prodEl, ARU_PRICE);
    let op   = childNum(prodEl, ARU_OLDPRICE);
    const sp = childNum(prodEl, ARU_SALEPRICE);

    if (!isFinite(op) && isFinite(sp) && isFinite(p) && sp<p) op = p;
    let price = isFinite(p) ? p : (isFinite(sp) ? sp : (isFinite(op) ? op : NaN));

    const avail = (childFirst(prodEl, ARU_AVAIL)||'').toLowerCase();
    const out = (avail.includes('out of stock') || avail==='1' || avail==='true');

    let discount = 0;
    if (isFinite(op) && isFinite(price) && op>0 && price<op) discount = (op-price)/op;
    else if (isFinite(sp) && isFinite(p) && p>0 && sp<p)     discount = (p-sp)/p;

    let keys = 0; if (url) keys++; if (title) keys++; if (img) keys++;
    if (keys<2) return null;

    const score = (discount>0 ? 10+discount : (isFinite(op)?3:1)) + (img?0.5:0) + (title?0.3:0) + Math.random()*0.01;
    return { title, url, img, cat, price, old_price: op, discount, out, score };
  }

  // ---- CHUNK parser ----
  function parseChunked(xml, shopSlug, fallbackCat, timeBudgetMs){
    const t0 = Date.now();
    try{
      const mRoot = xml.match(/<\s*([A-Za-z_][\w:.-]*)\b[^>]*>/);
      if (mRoot){ log(`DIAG-ROOT ${shopSlug}: <${mRoot[1]}>`); }
    }catch(_){}

    xml = fixClosers(sanitize(clamp(xml)));

    const re = /<\s*(?:\w+:)?product\b[\s\S]*?<\/\s*(?:\w+:)?product\s*>/gi;
    const chunks = xml.match(re) || [];
    if (!chunks.length){
      log(`DIAG ${shopSlug}: ARU chunks=0`);
      return null;
    }

    let best=null, bestScore=-1e9, kept=0, seen=0;
    for (let i=0; i<chunks.length; i++){
      if (Date.now()-t0 > timeBudgetMs) break;
      const chunk = `<?xml version="1.0" encoding="UTF-8"?><products>${chunks[i]}</products>`;
      try{
        const doc = XmlService.parse(chunk);
        const prodEl = doc.getRootElement().getChildren()[0];
        if (!prodEl) continue;
        seen++;
        const f = pickOne(prodEl, fallbackCat);
        if (!f || f.out) continue;
        kept++;
        if (f.score>bestScore){ bestScore=f.score; best={shop:shopSlug, ...f}; }
      }catch(e){ continue; }
    }
    log(`DIAG ${shopSlug}: ARU chunk-seen=${seen} kept=${kept}`);
    return best || null;
  }

  // ---- PUBLIC: RESET ----
  function RESET_ARU(){
    PropertiesService.getScriptProperties().setProperty(C.PS_KEY_CURSOR,'1');
    log('RESET_ARU ok');
  }

  // ---- PUBLIC: RUN ----
  function RUN_ARU(){
    const t0 = Date.now();
    const ss = SpreadsheetApp.getActive();
    const shShops = ss.getSheetByName(C.SHEET_SHOPS);
    if (!shShops) throw new Error('Hiányzik a Shops sheet.');
    const {shB, shT} = ensureSheets();

    const data = shShops.getDataRange().getValues();
    if (!data || data.length<2) return;
    const head = data[0].map(slugify);
    const col = n => head.indexOf(n);

    const cSlug = col('shop_slug'), cFeed = col('deals_feed'), cCat = col('category');
    if (cSlug<0 || cFeed<0) throw new Error('A Shops lapon kell shop_slug és deals_feed oszlop.');

    const idxs = [];
    for (let r=1; r<data.length; r++){
      const slug = (data[r][cSlug]||'').toString().trim().toLowerCase();
      if (slug === 'arukereso') idxs.push(r);
    }
    if (!idxs.length){ log('Nincs arukereso sor.'); return; }

    const ps = PropertiesService.getScriptProperties();
    let cursor = parseInt(ps.getProperty(C.PS_KEY_CURSOR) || '0', 10);
    if (!isFinite(cursor) || cursor<0) cursor = 0;

    let wrote = 0, processed = 0;

    for (let k=0; k<idxs.length; k++){
      if (processed >= C.SHOPS_PER_RUN) break;
      if (Date.now() - t0 > C.MAX_RUN_MS) { log('TIME-GUARD ARU: vége, folyt. következő körben'); break; }

      const pos = (cursor + k) % idxs.length;
      const r = idxs[pos];
      const row = data[r];

      const slug = (row[cSlug]||'').toString().trim();
      const feed = (row[cFeed]||'').toString().trim();
      const cat  = cCat>=0 ? (row[cCat]||'').toString().trim() : '';
      if (!slug || !feed) { continue; }

      log('START ARU ' + slug + ` (row ${r+1})`);
      const tStart = Date.now();
      try{
        if (!preflight(feed)) throw new Error('PREFLIGHT_TIMEOUT');

        const resp = UrlFetchApp.fetch(feed, {
          muteHttpExceptions:true, followRedirects:true, validateHttpsCertificates:true,
          headers:{'Accept':'application/xml, text/xml, */*'}
        });
        const code = resp.getResponseCode();
        if (code<200 || code>=300) throw new Error('HTTP '+code);

        function decode(r){
          try{ return r.getContentText(); }catch(_){}
          try{ return r.getContentText('UTF-8'); }catch(_){}
          try{ return r.getContentText('ISO-8859-2'); }catch(_){}
          try{ return r.getContentText('windows-1250'); }catch(_){}
          return r.getContentText();
        }
        let xml = decode(resp);

        const remain = Math.max(6000, C.PER_FEED_MS - (Date.now()-tStart));
        const picked = parseChunked(xml, slug, cat, remain);
        if (!picked) throw new Error('NINCS_KINYERHETO_TETEL');

        const targetRow = 1 + r;
        writeTmp(shT, targetRow, picked);
        wrote++; processed++;
        log(`OK ARU ${slug} (${Date.now()-tStart} ms)`);
      }catch(e){
        const targetRow = 1 + r;
        const href = C.FILLOUT_BASE + '?shop=' + encodeURIComponent(slug) + '&u=' + encodeURIComponent(Utilities.base64Encode(feed));
        shT.getRange(targetRow,1,1,5).setValues([[slug,'',href,`Hiba: ${slug} – ${String(e.message).slice(0,180)}`,cat||'' ]]);
        processed++;
        log(`ERR ARU ${slug}: ${e && e.message ? e.message : e}`);
      }

      Utilities.sleep(C.SLEEP_BETWEEN);
    }

    const all = shT.getDataRange().getValues();
    if (all && all.length>=1){
      shB.clearContents();
      shB.getRange(1,1,all.length, Math.min(5, all[0].length)).setValues(all);
    }

    cursor = (cursor + processed) % idxs.length;
    ps.setProperty(C.PS_KEY_CURSOR, String(cursor));

    log(`DONE ARU run: processed=${processed}, wrote=${wrote}, nextIndex=${cursor}, totalAruRows=${idxs.length}`);
  }

  // export (csak 2 globális név)
  GLOBAL['impactshop巡_RUN_ARU'] = RUN_ARU;
  GLOBAL['impactshop巡_RESET_ARU'] = RESET_ARU;

})(this);