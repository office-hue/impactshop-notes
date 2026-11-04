import assert from 'node:assert/strict';
import {test,before,after} from 'node:test';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
import {readFileSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const require=createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT,'package.json'));
const {chromium}=require('playwright');
const overlay=readFileSync(resolve(root,'wp-content/mu-plugins/impactshop-identity-panel-intl-overlay.js'),'utf8');
let browser;
before(async()=>{browser=await chromium.launch({channel:'chrome',headless:true,proxy:{server:'http://127.0.0.1:9'}});});
after(async()=>{await browser?.close();});
async function fixture(language='hu',scenario='normal',{blocked=false,width=375,source=null,legacy=true}={}) {
 const context=await browser.newContext({viewport:{width,height:950},serviceWorkers:'block'});
 await context.addInitScript(({blocked})=>{
  window.copied=[];
  Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async s=>{window.copied.push(s);}}});
  document.execCommand=()=>true;
  // Existing copy buttons sit inside links; keep the fixture on-page to inspect listeners.
  document.addEventListener('click',event=>{if(event.target.closest('.icn-copy'))event.preventDefault();});
  if(blocked){Object.defineProperty(window,'localStorage',{get(){throw Error('blocked');}});Object.defineProperty(Document.prototype,'cookie',{configurable:true,get(){throw Error('blocked');},set(){throw Error('blocked');}});}
 },{blocked});
 const page=await context.newPage(), errors=[],escaped=[],requests=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());requests.push(req.method());
  if(url.origin==='https://app.sharity.hu'&&url.pathname==='/impactshop/') {
   const body=execFileSync('php',[resolve(root,'tests/fixtures/impactshop-netflix-render.php'),root,scenario,...(source?[source]:[])],{encoding:'utf8'});
   return route.fulfill({contentType:'text/html; charset=utf-8',body:body.replace('</body>',(legacy?'<script src="/fixture/legacy.js"></script>':'')+'</body>')});
  }
  if(url.pathname==='/fixture/legacy.js')return route.fulfill({contentType:'application/javascript',body:overlay});
  if(url.host==='image.invalid')return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>'});
  escaped.push(req.url());return route.abort();
 });
 await page.goto('https://app.sharity.hu/impactshop/?ui_lang='+language+'&ui_currency=GBP');
 if(!source)await page.waitForFunction(()=>typeof window.impactshopNetflixText==='function');
 return {page,context,errors,escaped,requests};
}
async function finish(f){assert.deepEqual(f.errors,[]);assert.deepEqual(f.escaped,[]);assert.ok(f.requests.every(x=>x==='GET'));await f.context.close();}
async function stableData(page){return page.evaluate(()=>Array.from(document.querySelectorAll('.ifx-card a,.rail a,.icn-card a,.icn-copy'),el=>({tag:el.tagName,href:el.getAttribute('href'),data:Array.from(el.attributes).filter(a=>a.name.startsWith('data-')&&!a.name.startsWith('data-sharity-')).map(a=>[a.name,a.value])})));}
const words={hu:['Összes','Másolás','Lejárat: folyamatos'],en:['All','Copy','Expires: ongoing'],de:['Alle','Kopieren','Gültig: fortlaufend'],fr:['Tout','Copier','Expiration : en continu']};
test('real PHP render: four languages, original partner/product/price/code and GET-only behavior',async()=>{
 for(const [language,[all,copy,forever]] of Object.entries(words)){
  const f=await fixture(language,'normal',{width:language==='en'?1280:language==='de'?375:320});const p=f.page;
  await p.waitForFunction(text=>document.querySelector('.ifx-pill[data-cat="__ALL__"]').textContent===text,all);
  assert.equal(await p.locator('.icn-copy').first().textContent(),copy);
  assert.equal(await p.locator('.icn-countdown').first().textContent(),forever);
  assert.deepEqual((await p.locator('.ifx-name').allTextContents()).sort(),['Másolás','Összes'].sort());
  assert.equal(await p.locator('.rail .shop').textContent(),'AKCIÓ');
  assert.equal(await p.locator('.rail .price').textContent(),'1 234 Ft');
  assert.equal(await p.locator('.icn-code').first().textContent(),'Másolás');
  assert.equal(await p.locator('.icn-title').first().textContent(),'Lejárt');
  assert.equal(await p.locator('.icn-card').last().isVisible(),false);
  assert.equal(await p.locator('.icn-copy').first().evaluate(node=>node.scrollWidth<=node.clientWidth),true,'copy label fits');
  if(process.env.NETFLIX_SCREENSHOT_DIR && ['de','fr'].includes(language))await p.screenshot({path:resolve(process.env.NETFLIX_SCREENSHOT_DIR,language+'.png'),fullPage:true});
  await finish(f);
 }
});
test('live repeated choices and blocked storage preserve nodes, filters, click listeners and coupon data',async()=>{
 const f=await fixture('hu','normal',{blocked:true}),p=f.page;const initial=await stableData(p);
 await p.evaluate(()=>{window.originalCoupon=document.querySelector('.icn-copy');window.clickCount=0;window.originalCoupon.addEventListener('click',()=>window.clickCount++);});
 for(const language of ['de','fr','en','hu']){
  await p.evaluate(lang=>{document.querySelector('.sharity-action-bar').lang=lang;},language);
  await p.waitForFunction(text=>document.querySelector('.icn-copy').textContent===text,words[language][1]);
  assert.deepEqual(await stableData(p),initial);
  assert.equal(await p.evaluate(()=>document.querySelector('.icn-copy')===window.originalCoupon),true);
  assert.equal(await p.locator('.icn-countdown').first().textContent(),words[language][2]);
 }
 await p.locator('.ifx-pill[data-cat="Divat"]').click();
 assert.equal(await p.locator('.ifx-track').getAttribute('data-active'),'Divat');
 await p.locator('.icn-copy').first().click();
 assert.deepEqual(await p.evaluate(()=>window.copied),['Másolás']);
 assert.equal(await p.evaluate(()=>window.clickCount),1);
 assert.equal(await p.evaluate(()=>window.dataLayer.filter(x=>x.event==='coupon_copy').length),1);
 await finish(f);
});
test('late dock and existing cached HTML adopt the language without business requests',async()=>{
 const f=await fixture('fr','late'),p=f.page;
 assert.equal(await p.locator('.icn-copy').first().textContent(),'Copier');
 await p.evaluate(()=>{const bar=document.createElement('nav');bar.className='sharity-action-bar';bar.lang='de';document.body.appendChild(bar);});
 await p.waitForFunction(()=>document.querySelector('.icn-copy').textContent==='Kopieren');
 await finish(f);
 const cached=await fixture('en','cached');assert.equal(await cached.page.locator('.icn-copy').first().textContent(),'Copy');await finish(cached);
});
test('empty and unavailable states are translated and custom category remains source text',async()=>{
 for(const scenario of ['empty','unavailable']){const f=await fixture('de',scenario);assert.ok((await f.page.locator('[data-sharity-netflix-copy]').allTextContents()).some(t=>scenario==='empty'?t.includes('Keine Angebote'):t.includes('verfügbar')));await finish(f);}
 const f=await fixture('en','custom');assert.equal(await f.page.locator('.ifx-pill[data-cat="Egyéb"]').textContent(),'Egyéb');await finish(f);
});
test('locale patch preserves original provider-request payloads and business DOM data',async()=>{
 const original=process.env.NETFLIX_BASELINE_FILE;assert.ok(original,'set NETFLIX_BASELINE_FILE to the original source');
 const a=await fixture('hu','normal',{source:original,legacy:false}),b=await fixture('hu','normal',{legacy:false});
 assert.deepEqual(await stableData(b.page),await stableData(a.page));
 assert.deepEqual(await b.page.evaluate(()=>fixtureRequests),await a.page.evaluate(()=>fixtureRequests));
 await finish(a);await finish(b);
});

test('own missing-field labels translate while raw CJ names, titles, codes and tracking values remain original',async()=>{
 const f=await fixture('de','fallback'),p=f.page;
 assert.deepEqual(await p.locator('.icn-primary').allTextContents(),['ANGEBOT','Gutschein']);
 assert.deepEqual(await p.locator('.icn-copy').evaluateAll(nodes=>nodes.map(n=>n.dataset.couponPrimary)),['Akció','Kupon']);
 assert.equal(await p.locator('.icn-shop').last().textContent(),'Kupon');
 assert.equal(await p.locator('.icn-title').last().textContent(),'Akció');
 assert.equal(await p.locator('.icn-code').last().textContent(),'COUPON');
 await finish(f);
});
