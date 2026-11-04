// Real NGO runtime + real PHP selector; all requests are local fixtures or blocked.
import assert from 'node:assert/strict';
import { before, after, test } from 'node:test';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const require=createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT||root,'package.json'));
const {chromium}=require('playwright');
const runtime=readFileSync(resolve(root,'wp-content/mu-plugins/impactshop-ngo-card.js'),'utf8');
const base='https://app.sharity.hu/profil/';
const variants=['full','compact','widget'];
const provider={slug:'fixture-ngo',name:'Shop $& <b>civil</b>',summary:'Részletek <b>original</b>',
 amount:{formatted:'1 234 Ft'},challenge_amount:{formatted:'234 Ft'},total_donation:{formatted:'1 468 Ft'},
 rank:4,supporters:12345,last_updated:'2026-10-31T12:00:00Z',badge_status:{key:'custom',label:'Támogatók'},
 announcement:{text:'Sharity hírek <img src=x onerror="window.injected=true">',url:'https://example.invalid/news?raw=a%20b%2f~&raw=a+b'},
 share_url:'https://example.invalid/share?raw=a%20b%2f~&raw=a+b',
 cta_url:'https://example.invalid/go?d1=fixture-ngo&sat1=fixture%2B%2F%3D',
 challenge_urls:{shop:'https://example.invalid/shop?raw=a%20b%2f~&raw=a+b',video:'https://example.invalid/video',offerwall:'https://example.invalid/offerwall',donate:'https://example.invalid/donate'}};
let browser;
before(async()=>{browser=await chromium.launch({channel:'chrome',headless:true,proxy:{server:'http://127.0.0.1:9'}});});
after(async()=>{await browser?.close();});
async function fixture({language='hu',lateBar=false,deferred=false,status=200,message='',data=provider,denied=false,missing=false,currency='GBP',fx=null,blocked=false,clock=false,width=375,storageBlocked=false,cookieCurrency=null,noQuery=false}={}) {
 const context=await browser.newContext({viewport:{width,height:850}});
 if(blocked)await context.addInitScript(()=>{Storage.prototype.getItem=Storage.prototype.setItem=()=>{throw Error('blocked');};Object.defineProperty(document,'cookie',{get(){throw Error('blocked');},set(){throw Error('blocked');}});});
 if(cookieCurrency!==null)await context.addCookies([{name:'sharity_display_currency',value:cookieCurrency,url:base}]);
 if(storageBlocked)await context.addInitScript(()=>{Storage.prototype.getItem=Storage.prototype.setItem=()=>{throw Error('blocked');};});
 const page=await context.newPage();page.setDefaultTimeout(5000);if(clock)await page.clock.install({time:new Date()});const errors=[],escaped=[],requests=[];let apiCalls=0,fxCalls=0,release;
 const held=new Promise(resolve=>{release=resolve;});
 const bar=execFileSync('php',[resolve(root,'tests/fixtures/impactshop-action-bar-render.php'),base+'?ui_lang='+language+'&ui_currency='+currency],{encoding:'utf8'});
 page.on('pageerror',e=>errors.push(e.message));
 await context.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());requests.push(req.method());
  if(url.pathname==='/fixture/ngo.js')return route.fulfill({contentType:'application/javascript; charset=utf-8',body:runtime});
  if(url.pathname==='/wp-json/sharity-finance/v1/fx-quotes'){fxCalls++;return route.fulfill({status:fx===null?404:200,contentType:'application/json',body:JSON.stringify(fx)});}
  if(url.pathname.startsWith('/wp-json/impact/v1/ngo-card/')) {
   apiCalls++;if(deferred)await held;
   return route.fulfill({status,contentType:'application/json',headers:{'x-cache-status':'HIT'},body:JSON.stringify(status===200?data:{message})});
  }
  if(req.isNavigationRequest()&&url.origin==='https://app.sharity.hu')return route.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><script>window.dataLayer=[];window.impactConsent=${JSON.stringify(denied?'denied':'granted')};</script>${variants.map(variant=>`<section id="${variant}" data-ngo-card="${missing?'':'fixture-ngo'}" variant="${variant}"></section>`).join('')}${lateBar?'':bar}<script src="/fixture/ngo.js"></script></body></html>`});
  if(req.resourceType()==='image')return route.fulfill({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"/>'});
  if(url.hostname==='fonts.googleapis.com')return route.fulfill({contentType:'text/css',body:''});
  escaped.push(req.url());return route.abort();
 });
 await page.goto(base+(noQuery?'':'?ui_lang='+language+'&ui_currency='+currency),{waitUntil:'domcontentloaded'});
 if(!deferred)await page.waitForFunction(()=>[...document.querySelectorAll('[data-ngo-card]')].every(el=>!el.querySelector('.impact-ngo-card--loading')));
 return {page,context,errors,escaped,requests,release,apiCalls:()=>apiCalls,fxCalls:()=>fxCalls};
}
async function language(f,value) {
 await f.page.evaluate(value=>{
  let bar=document.querySelector('.sharity-action-bar[data-sharity-preferences-managed]');
  if(!bar){bar=document.createElement('nav');bar.className='sharity-action-bar';bar.dataset.sharityPreferencesManaged='';document.body.appendChild(bar);}
  bar.lang=value;
 },value);
 const expected=['hu','en','de','fr'].includes(value)?value:'hu';
 await f.page.waitForFunction(expected=>[...document.querySelectorAll('[data-ngo-card]')].every(el=>el.lang===expected),expected);
}
async function finish(f){assert.deepEqual(f.errors,[]);assert.deepEqual(f.escaped,[]);assert.ok(f.requests.every(m=>m==='GET'));await f.context.close();}
const hrefs=f=>f.page.locator('[data-ngo-card] a').evaluateAll(as=>as.map(a=>a.getAttribute('href')));
const events=f=>f.page.evaluate(()=>window.dataLayer);

test('full compact widget follow all four languages and restore HU without changing provider data, money, URLs or views',async()=>{
 const f=await fixture();const initialLinks=await hrefs(f);
 assert.equal((await events(f)).filter(e=>e.event==='card_view').length,3);
 const expected={hu:['Összesen','Összeg','Becsült támogatás','Feladatok'],en:['Total','Amount','Estimated support','Tasks'],de:['Gesamt','Betrag','Geschätzte Unterstützung','Aufgaben'],fr:['Total','Montant','Soutien estimé','Missions']};
 for(const lang of ['en','de','fr','hu']) {
  await language(f,lang);const words=expected[lang];
  assert.equal(await f.page.locator('#full .impact-ngo-card__stats .impact-ngo-card__stat-label').last().textContent(),words[0]);
  assert.equal(await f.page.locator('#compact .impact-ngo-card__stat-label').first().textContent(),words[1]);
  assert.equal(await f.page.locator('#widget .impact-ngo-card__widget-amount span').textContent(),words[2]);
  assert.equal((await f.page.locator('#full [data-action=offerwall]').textContent()).trim(),words[3]);
  for(const variant of variants) {
   assert.equal(await f.page.locator(`#${variant} .impact-ngo-card__title`).textContent(),provider.name);
   assert.equal(await f.page.locator(`#${variant} .impact-ngo-card__badge`).textContent(),'Támogatók');
  }
  assert.deepEqual(await f.page.locator('#full .impact-ngo-card__stats strong').allTextContents(),['1 234 Ft','234 Ft','1 468 Ft']);
  assert.equal(await f.page.locator('#compact .impact-ngo-card__tagline').textContent(),provider.summary);
  assert.equal(await f.page.locator('#full .impact-ngo-card__announcement p').textContent(),provider.announcement.text);
  assert.equal(await f.page.locator('#full .impact-ngo-card__announcement p img').count(),0);
  assert.deepEqual(await hrefs(f),initialLinks);
  assert.equal((await events(f)).filter(e=>e.event==='card_view').length,3);
  assert.equal(f.apiCalls(),3);
 }
 await f.page.evaluate(()=>{
  document.querySelectorAll('[data-ngo-card] a').forEach(a=>a.addEventListener('click',e=>e.preventDefault()));
  document.querySelector('#full [data-action=offerwall]').click();
  document.querySelector('#widget .impact-ngo-card__widget-cta').click();
 });
 assert.equal((await events(f)).filter(e=>e.event==='challenge_bar_offerwall_click').length,1);
 assert.equal((await events(f)).filter(e=>e.event==='cta_click').length,1);
 assert.equal(await f.page.evaluate(()=>window.injected),undefined);
 await finish(f);
});

test('real PHP selector changes DE/FR locally and maintains consent-denied analytics',async()=>{
 const f=await fixture({language:'de',denied:true});
 await f.page.locator('#sharity-slc-btn').click();
 await f.page.locator('[data-slc-language=fr]').click();
 await f.page.waitForFunction(()=>document.getElementById('widget').lang==='fr');
 assert.equal(await f.page.locator('#widget .impact-ngo-card__widget-amount span').textContent(),'Soutien estimé');
 assert.equal(f.apiCalls(),3);assert.deepEqual(await events(f),[]);
 await f.page.keyboard.press('Escape');
 if(process.env.SHARITY_INTL_SCREENSHOTS==='1') {mkdirSync(resolve(root,'tmp'),{recursive:true});await f.page.screenshot({path:resolve(root,'tmp/p2u-ngo-fr.png'),fullPage:true});}
 await finish(f);
});

test('late selector and language changes while fetch is pending use latest locale without refetch',async()=>{
 const f=await fixture({lateBar:true,deferred:true});
 await f.page.waitForFunction(()=>document.querySelectorAll('.impact-ngo-card--loading').length===3);
 await language(f,'de');await language(f,'fr');f.release();
 await f.page.waitForFunction(()=>document.querySelectorAll('.impact-ngo-card--loading').length===0);
 assert.equal(await f.page.locator('#widget .impact-ngo-card__widget-amount span').textContent(),'Soutien estimé');
 assert.equal(f.apiCalls(),3);assert.equal((await events(f)).filter(e=>e.event==='card_view').length,3);
 await language(f,'hu');await finish(f);
});

test('late selector after hydration and unknown locale safely restore own copy and fallback tagline',async()=>{
 const f=await fixture({lateBar:true,data:{...provider,summary:null,cta_url:null,challenge_urls:{}}});
 const links=await hrefs(f);
 for(const lang of ['fr','de','toString','constructor','hu'])await language(f,lang);
 assert.equal(await f.page.locator('#full .impact-ngo-card__tagline').textContent(),`Segítsd a(z) ${provider.name} ügyét az Impact Shopban.`);
 assert.deepEqual(await hrefs(f),links);assert.equal(f.apiCalls(),3);await finish(f);
});

test('429 and 404 owned error copy updates repeatedly without refetch',async()=>{
 for(const status of [429,404]) {
  const f=await fixture({status});
  const titles=status===429?{en:'Too many requests',de:'Zu viele Anfragen',fr:'Trop de requêtes',hu:'Túl sok kérés'}:{en:'Currently unavailable',de:'Derzeit nicht verfügbar',fr:'Actuellement indisponible',hu:'Jelenleg nem elérhető'};
  for(const lang of ['en','de','fr','hu']) {
   await language(f,lang);
   assert.deepEqual(await f.page.locator('.impact-ngo-card__error-title').allTextContents(),Array(3).fill(titles[lang]));
  }
  assert.equal(f.apiCalls(),3);assert.deepEqual(await events(f),[]);await finish(f);
 }
});

test('custom provider error stays literal text on every language and cannot execute markup',async()=>{
 const message='Próbáld újra később. <img src=x onerror="window.injected=true">';
 const f=await fixture({status:500,message});
 for(const lang of ['en','de','fr','hu']) {
  await language(f,lang);
  assert.deepEqual(await f.page.locator('.impact-ngo-card__error-message').allTextContents(),Array(3).fill(message));
  assert.equal(await f.page.locator('.impact-ngo-card__error-message img').count(),0);
 }
 assert.equal(await f.page.evaluate(()=>window.injected),undefined);await finish(f);
});

test('missing slug localizes without an API request',async()=>{
 const f=await fixture({missing:true});
 await language(f,'en');assert.equal(await f.page.locator('#widget').textContent(),'NGO slug missing.');
 await language(f,'hu');assert.equal(await f.page.locator('#full').textContent(),'NGO slug hiányzik.');
 assert.equal(f.apiCalls(),0);await finish(f);
});

const datedFx=()=>({schema_version:'sharity-fx-quotes-v1',status:'current',base_currency:'HUF',fetched_at:new Date().toISOString().replace(/\.\d{3}Z$/,'Z'),quote_date:new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Budapest',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date()),quotes:{EUR:{rate:'400.00',unit:'1'},USD:{rate:'320.00',unit:'1'},GBP:{rate:'500.00',unit:'1'}}});
const structured={...provider,amount:{huf:1234,formatted:'1 234 Ft'},challenge_amount:{huf:234,formatted:'234 Ft'},total_donation:{huf:1468,formatted:'1 468 Ft'}};
test('all16 native choices preserve HUF and show dated reference amounts without more data/views',async()=>{
 const locales={hu:'hu-HU',en:'en-US',de:'de-DE',fr:'fr-FR'};
 for(const lang of Object.keys(locales)){
  const f=await fixture({language:lang,currency:'HUF',data:structured,fx:datedFx(),blocked:true,width:lang==='fr'?320:lang==='en'?1280:375});
  const links=await hrefs(f);assert.equal(f.fxCalls(),0);
  for(const currency of ['HUF','EUR','USD','GBP']){
   await f.page.locator('#sharity-slc-btn').click();await f.page.locator('#sharity-slc-currency').selectOption(currency);await f.page.keyboard.press('Escape');
   if(currency==='HUF')assert.equal(await f.page.locator('[data-ngo-converted]:visible').count(),0);
   else {
    await f.page.waitForFunction(()=>document.querySelectorAll('[data-ngo-converted]:not([hidden])').length===5);
    const value={EUR:3.09,USD:3.86,GBP:2.47}[currency];
    const expected=new Intl.NumberFormat(locales[lang],{style:'currency',currency}).format(value);
    assert.ok((await f.page.locator('#compact [data-ngo-converted]').textContent()).startsWith('≈ '+expected+' · MNB '));
   }
   assert.equal(await f.page.locator('#compact [data-ngo-original]').textContent(),new Intl.NumberFormat(locales[lang],{style:'currency',currency:'HUF',maximumFractionDigits:0}).format(1234));
   assert.deepEqual(await hrefs(f),links);
  }
  assert.equal(f.fxCalls(),1);assert.equal(f.apiCalls(),3);assert.equal((await events(f)).filter(e=>e.event==='card_view').length,3);
  assert.equal(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  if(process.env.SHARITY_INTL_SCREENSHOTS==='1'&&['de','fr'].includes(lang)){mkdirSync(resolve(root,'tmp'),{recursive:true});await f.page.screenshot({path:resolve(root,'tmp/ngo-reference-'+lang+'.png'),fullPage:true});}
  await finish(f);
 }
});
test('unit100 and zero, missing/invalid/unsafe structured HUF never becomes a parsed formatted amount',async()=>{
 const fx=datedFx();for(const q of Object.values(fx.quotes)){q.rate=String(Number(q.rate)*100);q.unit='100';}
 const f=await fixture({language:'en',currency:'EUR',fx,data:{...structured,amount:{huf:0,formatted:'bogus 123 Ft'}}});
 await f.page.waitForFunction(()=>document.querySelector('#compact [data-ngo-converted]')?.textContent.includes('0.00'));
 assert.equal(await f.page.locator('#compact [data-ngo-original]').textContent(),'HUF 0');await finish(f);
 for(const huf of [undefined,NaN,-1,0.1,Number.MAX_SAFE_INTEGER+1,'123 Ft']){
  const g=await fixture({fx,currency:'EUR',data:{...provider,amount:{huf,formatted:'123 Ft'}}});
  assert.equal(await g.page.locator('[data-ngo-converted]:visible').count(),0);assert.equal(g.fxCalls(),0);await finish(g);
 }
});
test('stale/missing/future/malformed FX retains original and performs only one cache GET',async()=>{
 const cases=[null,{...datedFx(),status:'unavailable'},{...datedFx(),fetched_at:new Date(Date.now()-37*3600000).toISOString().replace(/\.\d{3}Z$/,'Z')},{...datedFx(),quote_date:'2000-01-01'},{...datedFx(),quote_date:'2099-01-01'},{...datedFx(),quotes:{EUR:{rate:'400',unit:'1'}}},{...datedFx(),quotes:{...datedFx().quotes,EUR:{rate:'400,0',unit:'1'}}}];
 for(const fx of cases){
  const f=await fixture({language:'en',currency:'EUR',data:structured,fx});
  await f.page.waitForLoadState('networkidle');
  assert.equal(await f.page.locator('[data-ngo-converted]:visible').count(),0);assert.equal(f.fxCalls(),1);
  await language(f,'fr');assert.equal(f.fxCalls(),1);await finish(f);
 }
});
test('cached FX expires on an open page and late dock keeps data/view counts stable',async()=>{
 const fx=datedFx();fx.quote_date=new Date(Date.parse(fx.quote_date+'T00:00:00Z')-7*86400000).toISOString().slice(0,10);
 const f=await fixture({language:'en',currency:'EUR',data:structured,fx,clock:true});
 await f.page.waitForFunction(()=>document.querySelectorAll('[data-ngo-converted]:not([hidden])').length===5);
 await f.page.clock.fastForward(24*3600000);assert.equal(await f.page.locator('[data-ngo-converted]:visible').count(),0);await finish(f);
 const late=await fixture({data:structured,fx:datedFx(),lateBar:true});
 await late.page.evaluate(()=>document.body.insertAdjacentHTML('beforeend','<select id="sharity-slc-currency"><option value="USD" selected>USD</option></select>'));
 await language(late,'hu');await late.page.waitForFunction(()=>document.querySelectorAll('[data-ngo-converted]:not([hidden])').length===5);
 assert.equal(late.fxCalls(),1);assert.equal(late.apiCalls(),3);assert.equal((await events(late)).filter(e=>e.event==='card_view').length,3);await finish(late);
});

test('standalone cards read the shared currency cookie when storage is blocked and no dock or query is present',async()=>{
 for(const currency of ['EUR','USD','GBP']){
  const f=await fixture({lateBar:true,noQuery:true,storageBlocked:true,cookieCurrency:currency,data:structured,fx:datedFx()});
  await f.page.waitForFunction(()=>document.querySelectorAll('[data-ngo-converted]:not([hidden])').length===5);
  const value={EUR:3.09,USD:3.86,GBP:2.47}[currency];
  assert.ok((await f.page.locator('#compact [data-ngo-converted]').textContent()).startsWith('\u2248 '+new Intl.NumberFormat('hu-HU',{style:'currency',currency}).format(value)));
  assert.equal(f.fxCalls(),1);assert.equal(f.apiCalls(),3);
  await finish(f);
 }
});
