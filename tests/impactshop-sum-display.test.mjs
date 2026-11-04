// Real PHP source + real cache validator/parser, synthetic totals/rates; network intercepted.
import assert from 'node:assert/strict';
import {before,after,test} from 'node:test';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
import {readFileSync,mkdirSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const require=createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT,'package.json'));
const {chromium}=require('playwright');
const overlay=readFileSync(resolve(root,'wp-content/mu-plugins/impactshop-identity-panel-intl-overlay.js'),'utf8');
const locales={hu:'hu-HU',en:'en-US',de:'de-DE',fr:'fr-FR'};
const labels={hu:'Várható adomány',en:'Estimated donation',de:'Voraussichtliche Spende',fr:'Don estimé'};
const amount={HUF:20000,USD:62.5,GBP:40};
let browser;
before(async()=>{browser=await chromium.launch({channel:'chrome',headless:true,proxy:{server:'http://127.0.0.1:9'}});});
after(async()=>{await browser?.close();});
async function fixture(lang='hu',currency='HUF',scenario='normal',{blocked=false,source=null,width=375,clock=false}={}) {
 const context=await browser.newContext({viewport:{width,height:width<=320?568:width<=375?812:900},serviceWorkers:'block'});
 if(blocked)await context.addInitScript(()=>{
  Object.defineProperty(window,'localStorage',{get(){throw Error('blocked');}});
  Object.defineProperty(Document.prototype,'cookie',{configurable:true,get(){throw Error('blocked');},set(){throw Error('blocked');}});
 });
 const page=await context.newPage(),errors=[],escaped=[],methods=[];
 page.setDefaultTimeout(5000);
 if(clock)await page.clock.install({time:new Date()});
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url()); methods.push(req.method());
  if(url.pathname==='/fixture/legacy.js')return route.fulfill({contentType:'application/javascript',body:overlay});
  if(url.origin==='https://app.sharity.hu'&&url.pathname==='/impactshop/'){
   const html=execFileSync('php',[resolve(root,'tests/fixtures/impactshop-sum-render.php'),root,scenario,...(source?[source]:[])],{encoding:'utf8'});
   return route.fulfill({contentType:'text/html; charset=utf-8',body:html.replace('</body>','<script src="/fixture/legacy.js"></script></body>')});
  }
  escaped.push(req.url());return route.abort();
 });
 await page.goto('https://app.sharity.hu/impactshop/?ui_lang='+lang+'&ui_currency='+currency);
 if(!source)await page.waitForFunction(()=>typeof window.impactshopSumDisplay==='function');
 return {page,context,errors,escaped,methods};
}
const formatted=(v,c,l)=>new Intl.NumberFormat(locales[l],{style:'currency',currency:c,minimumFractionDigits:2,maximumFractionDigits:2}).format(v);
async function finish(f){assert.deepEqual(f.errors,[]);assert.deepEqual(f.escaped,[]);assert.ok(f.methods.every(m=>m==='GET'));await f.context.close();}
test('all16 choices show original EUR plus accurate dated reference amount and four-language estimate meaning',async()=>{
 for(const lang of Object.keys(locales))for(const cur of ['HUF','EUR','USD','GBP']){
  const f=await fixture(lang,cur,'normal',{width:lang==='fr'?320:lang==='en'?1280:375}),p=f.page;
  assert.equal(await p.locator('[data-impact-sum-widget="sticky"] .label').textContent(),labels[lang]);
  assert.deepEqual(await p.locator('[data-sum-money] .num').allTextContents(),[formatted(50,'EUR',lang),formatted(50,'EUR',lang)]);
  if(cur==='EUR')assert.equal(await p.locator('[data-sum-converted]:visible').count(),0);
  else for(const text of await p.locator('[data-sum-converted]').allTextContents())assert.ok(text.startsWith('≈ '+formatted(amount[cur],cur,lang)+' · MNB '),text);
  assert.equal((await p.evaluate(()=>fixtureRequests)).length,1,'existing totals cache reused');
  await p.waitForFunction(()=>{
   const bar=document.querySelector('[data-impact-sum-widget="sticky"]').getBoundingClientRect();
   const dock=document.querySelector('.sharity-action-bar').getBoundingClientRect();
   return bar.bottom<=dock.top;
  });
  assert.ok(await p.evaluate(()=>{
   const bar=document.querySelector('[data-impact-sum-widget="sticky"] [role="region"]').getBoundingClientRect();
   return bar.top>=0&&bar.left>=0&&bar.right<=innerWidth;
  }),'sticky fits the viewport');
  assert.ok((await p.locator('[data-slc-help]').textContent()).includes('≈'));
  if(process.env.SUM_SCREENSHOT_DIR&&cur==='HUF'&&lang!=='hu'){
   mkdirSync(process.env.SUM_SCREENSHOT_DIR,{recursive:true});
   await p.screenshot({path:resolve(process.env.SUM_SCREENSHOT_DIR,lang+'.png')});
  }
  await finish(f);
 }
});
test('stale, old, partial, missing, malformed or absent FX module never uses static392 or rate_huf',async()=>{
 for(const scenario of ['stale','old-quote','incomplete','missing','bad-rate','no-fx-module']){
  const f=await fixture('en','HUF',scenario);
  assert.equal(await f.page.locator('[data-sum-converted]:visible').count(),0);
  assert.deepEqual(await f.page.locator('[data-sum-money] .num').allTextContents(),[formatted(50,'EUR','en'),formatted(50,'EUR','en')]);
  assert.ok((await f.page.locator('[data-sum-note]').first().textContent()).includes('unavailable'));
  await finish(f);
 }
});
test('per100 rate units, zero, negative clamp and invalid amount preserve meaning',async()=>{
 for(const [scenario,expected] of [['unit','≈ '+formatted(20000,'HUF','en')],['zero','≈ '+formatted(0,'HUF','en')],['negative','≈ '+formatted(0,'HUF','en')]]){
  const f=await fixture('en','HUF',scenario);
  assert.ok((await f.page.locator('[data-sum-converted]').first().textContent()).startsWith(expected));await finish(f);
 }
 const f=await fixture('en','HUF','invalid');
 assert.deepEqual(await f.page.locator('[data-sum-money] .num').allTextContents(),['—','—']);
 assert.equal(await f.page.locator('[data-sum-converted]:visible').count(),0);await finish(f);
});
test('native selection with blocked storage, repeated language changes, CTA and dismiss listener remain usable',async()=>{
 const f=await fixture('hu','HUF','normal',{blocked:true}),p=f.page;
 const href=await p.locator('[data-impact-sum-widget="sticky"] .cta').getAttribute('href');
 await p.locator('#sharity-slc-btn').click();await p.locator('#sharity-slc-currency').selectOption('USD');
 for(const lang of ['de','fr','en']){
  await p.evaluate(lang=>{document.querySelector('.sharity-action-bar').lang=lang;},lang);
  await p.waitForFunction(text=>document.querySelector('[data-impact-sum-widget="sticky"] .label').textContent===text,labels[lang]);
  assert.ok((await p.locator('[data-sum-converted]').first().textContent()).startsWith('≈ '+formatted(62.5,'USD',lang)));
 }
 assert.equal(await p.locator('[data-impact-sum-widget="sticky"] .cta').getAttribute('href'),href);
 assert.equal(await p.locator('[data-impact-sum-widget="sticky"] .cta').textContent().then(s=>s.trim()),'Adomány 123 Ft');
 await p.keyboard.press('Escape');await p.locator('[data-impact-sum-widget="sticky"] .close').click();
 assert.equal(await p.locator('[data-impact-sum-widget="sticky"]').isVisible(),false);await finish(f);
});
test('late dock, custom label, stale cached page and valid zero after data update',async()=>{
 const f=await fixture('fr','GBP','late'),p=f.page;
 await p.evaluate(()=>{document.body.insertAdjacentHTML('beforeend','<nav class="sharity-action-bar" lang="de"></nav><select id="sharity-slc-currency"><option value="USD" selected>USD</option></select>');});
 await p.waitForFunction(()=>document.querySelector('[data-impact-sum-widget="sticky"] .label').textContent==='Voraussichtliche Spende');
 await p.locator('[data-sum-money] .num').first().evaluate(node=>node.dataset.amt='0.00');
 await p.waitForFunction(()=>document.querySelector('[data-sum-money] .num').textContent.includes('0,00'));
 await p.evaluate(()=>{Date.now=()=>new Date().getTime()+40*3600000;window.impactshopSumDisplay();});
 assert.equal(await p.locator('[data-sum-converted]:visible').count(),0);await finish(f);
 const custom=await fixture('en','EUR','custom');
 assert.equal(await custom.page.locator('[data-impact-sum-widget="sticky"] .label').textContent(),'Vásárlásból gyűjtött adományok összege');await finish(custom);
});
test('dismiss TTL persists on reload, error is localized and diagnostic uses current reference only',async()=>{
 const f=await fixture('en','HUF'),p=f.page;await p.locator('[data-impact-sum-widget="sticky"] .close').click();
 const ttl=await p.evaluate(()=>Number(localStorage.getItem('impactSumStickyDismissed'))-Date.now());assert.ok(ttl>2.99*86400000&&ttl<=3*86400000);
 await p.reload();assert.equal(await p.locator('[data-impact-sum-widget="sticky"]').isVisible(),false);await finish(f);
 const error=await fixture('en','EUR','error');assert.equal(await error.page.locator('[data-sum-copy="error"]').textContent(),'The amount could not be loaded.');await finish(error);
 const diag=await fixture('en','HUF','diag');assert.equal(await diag.page.locator('[data-impact-sum-widget="diag"] [data-sum-money]').count(),2);assert.ok(!(await diag.page.locator('[data-impact-sum-widget="diag"]').textContent()).includes('392'));await finish(diag);
});

test('an already open page drops conversion when the seven-day quote expires before cache age',async()=>{
 const f=await fixture('en','HUF','last-quote-day',{clock:true});
 assert.equal(await f.page.locator('[data-sum-converted]:visible').count(),2);
 await f.page.clock.fastForward(24*3600000);
 assert.equal(await f.page.locator('[data-sum-converted]:visible').count(),0);
 await finish(f);
});
test('existing totals request and CTA are identical to the original source',async()=>{
 const original=process.env.SUM_BASELINE_FILE;
 assert.ok(original,'SUM_BASELINE_FILE must name the pre-change Sum Pack source');
 const baseline=await fixture('hu','HUF','normal',{source:original});
 const current=await fixture('hu','HUF');
 assert.deepEqual(await current.page.evaluate(()=>fixtureRequests),await baseline.page.evaluate(()=>fixtureRequests));
 const baselineHref=await baseline.page.locator('a.cta').getAttribute('href');
 assert.equal(await current.page.locator('a.cta').getAttribute('href'),baselineHref);
 await finish(baseline);await finish(current);
});
