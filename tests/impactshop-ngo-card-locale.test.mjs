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
async function fixture({language='hu',lateBar=false,deferred=false,status=200,message='',data=provider,denied=false,missing=false}={}) {
 const context=await browser.newContext({viewport:{width:375,height:850}});
 const page=await context.newPage();const errors=[],escaped=[],requests=[];let apiCalls=0,release;
 const held=new Promise(resolve=>{release=resolve;});
 const bar=execFileSync('php',[resolve(root,'tests/fixtures/impactshop-action-bar-render.php'),base+'?ui_lang='+language+'&ui_currency=GBP'],{encoding:'utf8'});
 page.on('pageerror',e=>errors.push(e.message));
 await context.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());requests.push(req.method());
  if(url.pathname==='/fixture/ngo.js')return route.fulfill({contentType:'application/javascript; charset=utf-8',body:runtime});
  if(url.pathname.startsWith('/wp-json/impact/v1/ngo-card/')) {
   apiCalls++;if(deferred)await held;
   return route.fulfill({status,contentType:'application/json',headers:{'x-cache-status':'HIT'},body:JSON.stringify(status===200?data:{message})});
  }
  if(req.isNavigationRequest()&&url.origin==='https://app.sharity.hu')return route.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><script>window.dataLayer=[];window.impactConsent=${JSON.stringify(denied?'denied':'granted')};</script>${variants.map(variant=>`<section id="${variant}" data-ngo-card="${missing?'':'fixture-ngo'}" variant="${variant}"></section>`).join('')}${lateBar?'':bar}<script src="/fixture/ngo.js"></script></body></html>`});
  if(req.resourceType()==='image')return route.fulfill({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"/>'});
  if(url.hostname==='fonts.googleapis.com')return route.fulfill({contentType:'text/css',body:''});
  escaped.push(req.url());return route.abort();
 });
 await page.goto(base,{waitUntil:'domcontentloaded'});
 if(!deferred)await page.waitForFunction(()=>[...document.querySelectorAll('[data-ngo-card]')].every(el=>!el.querySelector('.impact-ngo-card--loading')));
 return {page,context,errors,escaped,requests,release,apiCalls:()=>apiCalls};
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
 const expected={hu:['Összesen','Összeg','Összegyűjtve','Feladatok'],en:['Total','Amount','Raised','Tasks'],de:['Gesamt','Betrag','Gesammelt','Aufgaben'],fr:['Total','Montant','Collectés','Missions']};
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
 assert.equal(await f.page.locator('#widget .impact-ngo-card__widget-amount span').textContent(),'Collectés');
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
 assert.equal(await f.page.locator('#widget .impact-ngo-card__widget-amount span').textContent(),'Collectés');
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
