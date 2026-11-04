// Actual public widget markup + actual PHP chooser + actual overlay; no live writes.
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
const markup=readFileSync(resolve(root,'tests/fixtures/impactshop-shop-page.html'),'utf8');
const how={hu:'Hogyan működik?',en:'How does it work?',de:'Wie funktioniert es?',fr:'Comment ça marche ?'};
let browser;
before(async()=>{browser=await chromium.launch({channel:'chrome',headless:true,proxy:{server:'http://127.0.0.1:9'}});});
after(async()=>{await browser?.close();});
async function fixture({lang='hu',currency='HUF',legacy='',blocked=false,late=false,path='/impactshop/',width=375}={}){
 const url='https://app.sharity.hu'+path+'?ui_lang='+lang+'&ui_currency='+currency+(legacy?'&lang='+legacy:'');
 const bar=execFileSync('php',[resolve(root,'tests/fixtures/impactshop-action-bar-render.php'),url],{encoding:'utf8'});
 const context=await browser.newContext({viewport:{width,height:850},serviceWorkers:'block'});
 if(blocked)await context.addInitScript(()=>{
  Object.defineProperty(window,'localStorage',{get(){throw Error('blocked');}});
  Object.defineProperty(document,'cookie',{get(){throw Error('blocked');},set(){throw Error('blocked');}});
 });
 const p=await context.newPage(),errors=[],escaped=[],methods=[];p.setDefaultTimeout(5000);
 p.on('pageerror',e=>errors.push(e.message));
 await p.route('**/*',async route=>{
  methods.push(route.request().method());const u=new URL(route.request().url());
  if(u.pathname==='/fixture/overlay.js')return route.fulfill({contentType:'application/javascript',body:overlay});
  if(route.request().isNavigationRequest())return route.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html lang="hu"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>${late?'':markup}<div class="impactshop-identity-panel" data-sharity-preferences-managed><p>Profil</p></div><p id="unowned">Válassz boltot</p><section data-sharity-preferences-managed><h2 class="elementor-heading-title" id="merchant">Hogyan működik?</h2><p id="price">1 234 Ft · AKCIÓ · Shop $&amp;</p></section><a id="business" href="https://merchant.invalid/?raw=a%20b%2f~&raw=a+b#keep">Original merchant</a>${late?'':bar}<script>
 window.originalText=[];window.originalNodes=[];window.clicked=0;
 document.querySelectorAll('.elementor-16348 *').forEach(n=>window.originalNodes.push(n));
 if(document.querySelector('.elementor-16348')){
  const walker=document.createTreeWalker(document.querySelector('.elementor-16348'),NodeFilter.SHOW_TEXT);let n;while(n=walker.nextNode())window.originalText.push([n,n.nodeValue]);
  document.querySelector('[style="text-decoration: underline;"]').addEventListener('click',()=>window.clicked++);
 }
 </script><script src="/fixture/overlay.js"></script></body></html>`});
  escaped.push(route.request().url());return route.abort();
 });
 await p.goto(url);
 if(!late&&path==='/impactshop/')await p.waitForFunction(lang=>document.querySelector('[data-id="cff1f7c"]').lang===lang,lang);
 return {p,context,errors,escaped,methods,bar};
}
async function change(f,lang){await f.p.locator('.sharity-action-bar').evaluate((n,lang)=>n.lang=lang,lang);await f.p.waitForFunction(lang=>document.querySelector('[data-id="cff1f7c"]').lang===lang,lang);}
async function finish(f){assert.deepEqual(f.errors,[]);assert.deepEqual(f.escaped,[]);assert.ok(f.methods.every(m=>m==='GET'));await f.context.close();}
const heading=f=>f.p.locator('[data-id="cff1f7c"] .elementor-heading-title');
async function unchanged(f){
 assert.equal(await f.p.locator('#unowned').textContent(),'Válassz boltot');assert.equal(await f.p.locator('#merchant').textContent(),'Hogyan működik?');assert.equal(await f.p.locator('#price').textContent(),'1 234 Ft · AKCIÓ · Shop $&');
 assert.equal(await f.p.locator('#business').getAttribute('href'),'https://merchant.invalid/?raw=a%20b%2f~&raw=a+b#keep');
 assert.equal(await f.p.evaluate(()=>originalNodes.every(n=>n.isConnected)),true);
 assert.equal(await f.p.locator('[data-sharity-shop-estimate]').count(),1);
}
test('all16 choices localize the nine own widgets while preserving source markup and merchant data',async()=>{
 for(const lang of Object.keys(how))for(const currency of ['HUF','EUR','USD','GBP']){
  const f=await fixture({lang,currency,legacy:'en',blocked:true});
  assert.equal(await heading(f).textContent(),how[lang]);assert.equal(await f.p.locator('[data-sharity-shop-copy]').count(),9);
  assert.notEqual(await f.p.locator('[data-id="62ae0c2"] strong').last().textContent(),'');
  assert.equal(await f.p.locator('[style="text-decoration: underline;"]').count(),1);
  await unchanged(f);await finish(f);
 }
});
test('repeated switching restores exact HU text nodes and keeps inline click listeners and custom edits',async()=>{
 const f=await fixture();
 for(const lang of ['en','de','fr','hu','fr','hu']){await change(f,lang);assert.equal(await heading(f).textContent(),how[lang]);await unchanged(f);}
 assert.equal(await f.p.evaluate(()=>originalText.every(([node,value])=>node.nodeValue===value)),true);
 await f.p.locator('[style="text-decoration: underline;"]').click();assert.equal(await f.p.evaluate(()=>clicked),1);
 await f.p.locator('[data-id="556a678"] h1').evaluate(n=>n.textContent='Custom $20 label');
 await change(f,'de');assert.equal(await f.p.locator('[data-id="556a678"] h1').textContent(),'Custom $20 label');
 await finish(f);
});
test('late dock/widgets work, native DE/FR selection updates, and another route is untouched',async()=>{
 const f=await fixture({late:true,legacy:'en'});
 await f.p.evaluate(({markup,bar})=>{document.body.insertAdjacentHTML('afterbegin',markup);document.body.insertAdjacentHTML('beforeend',bar);document.querySelector('[data-id="9119697"] .elementor-icon-box-content').classList.remove('elementor-icon-box-content');document.querySelector('.sharity-action-bar').lang='fr';},{markup,bar:f.bar});
 await f.p.waitForFunction(()=>document.querySelector('[data-id="cff1f7c"]').lang==='fr');assert.equal(await heading(f).textContent(),how.fr);
 await change(f,'hu');await finish(f);
 const native=await fixture({lang:'de'});await native.p.locator('#sharity-slc-btn').click();await native.p.locator('[data-slc-language=fr]').click();await native.p.keyboard.press('Escape');
 await native.p.waitForFunction(()=>document.querySelector('[data-id="cff1f7c"]').lang==='fr');assert.equal(await heading(native).textContent(),how.fr);await finish(native);
 const other=await fixture({path:'/another-page/',lang:'fr'});assert.equal(await heading(other).textContent(),how.hu);assert.equal(await other.p.locator('[data-sharity-shop-copy]').count(),0);await finish(other);
});
test('known copy remains visible at mobile/desktop and doubled text size; full Elementor acceptance is separate',async()=>{
 for(const lang of ['de','fr'])for(const width of [320,375,1280]){
  const f=await fixture({lang,width});await f.p.evaluate(()=>document.body.style.fontSize='200%');
  assert.equal(await heading(f).isVisible(),true);assert.equal(await f.p.locator('[data-sharity-shop-estimate]').isVisible(),true);
  assert.equal(await f.p.locator('[data-id="62ae0c2"] [style="text-decoration: underline;"]').evaluate(n=>n.textContent.trim().length>0),true);
  if(process.env.SHARITY_INTL_SCREENSHOTS==='1'&&width===320){mkdirSync('/private/tmp/sharity-shop-copy-ui',{recursive:true});await f.p.screenshot({path:'/private/tmp/sharity-shop-copy-ui/'+lang+'.png',fullPage:true});}
  await finish(f);
 }
});
