// Full PHP profile/actionbar/PWA + real identity controller; all requests intercepted locally.
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { before, after, test } from 'node:test';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT || root, 'package.json'));
const { chromium } = require('playwright');
const legacy = process.env.PROFILE_UI_LEGACY_ACTION_BAR;
assert.ok(legacy, 'Supply the verified production actionbar via PROFILE_UI_LEGACY_ACTION_BAR');
assert.equal(createHash('sha256').update(readFileSync(legacy)).digest('hex'),'f05af5bf0c6112e78bc701637217e8ad3ffabba3ac4843f955856146e15fabfd','Legacy fixture must match the verified production actionbar');
const base = 'https://app.sharity.hu/profil/';
const core = readFileSync(resolve(root,'wp-content/mu-plugins/impactshop-identity-panel.js'),'utf8');
const overlay = readFileSync(resolve(root,'wp-content/mu-plugins/impactshop-identity-panel-intl-overlay.js'),'utf8');
const suffix = '?bridge_target=account&return=https%3A%2F%2Fsharity.hu%2Fsajat-sharity&prctx=signed%2fvalue%20x&selection_intent=fixture-choice&country=de&opaque=a+b&opaque=a%20b#impactshop-account-top';
const render = (url, kind, disabled=false) => execFileSync('php',[resolve(root,'tests/fixtures/impactshop-profile-ui-render.php'),url,...(kind==='legacy'?[legacy]:[])],{encoding:'utf8',env:{...process.env,PROFILE_UI_DISABLE:disabled?'1':'0'}});
const phpCode = input => JSON.parse(execFileSync('php',[resolve(root,'tests/impactshop-profile-code-save.test.php'),'--request'],{encoding:'utf8',input:JSON.stringify(input)}));
const jyskSuffix = '?jysk_return=1&bridge_target=account&prctx=prctx_fixture&opaque=a+b&opaque=a%20b#impactshop-account-top';
let browser;
before(async()=>{browser=await chromium.launch({channel:'chrome',headless:true,proxy:{server:'http://127.0.0.1:9'}});});
after(async()=>{await browser?.close();});
async function fixture(kind,{url=base+suffix, width=390, standalone=false, blocked=false, disabled=false, restoreDenied=false, stored, cookies=[]}={}) {
 const context=await browser.newContext({viewport:{width,height:900},serviceWorkers:'block'});
 await context.addCookies(cookies.map(([name,value])=>({name,value,url:base})));
 await context.addInitScript(({standalone,blocked,stored})=>{
  history.replaceState({keep:'unaltered'},'',location.href);
  if(stored) localStorage.setItem('sharity_ui_preferences_v1',JSON.stringify(stored));
  if(standalone){const original=matchMedia;window.matchMedia=q=>q==='(display-mode: standalone)'?{matches:true,addEventListener(){},addListener(){}}:original(q);}
  if(blocked){Storage.prototype.getItem=Storage.prototype.setItem=function(){throw Error('blocked');};Object.defineProperty(document,'cookie',{get(){throw Error('blocked');},set(){throw Error('blocked');}});}
  window.credentialStores=[];
  window.PasswordCredential=class { constructor(data){Object.assign(this,data);} };
  Object.defineProperty(navigator,'credentials',{value:{store(credential){credentialStores.push(credential);return Promise.resolve(credential);}},configurable:true});
  window.installCalls=0; window.alerts=[]; window.alert=text=>alerts.push(text);
 },{standalone,blocked,stored});
 const page=await context.newPage();page.setDefaultTimeout(5000);const errors=[], posts=[], escapes=[], navigations=[], navigationRequests=[], phpResults=[];let generatedReturn;
 page.on('pageerror',e=>errors.push(e.message));
 await context.route('**/*',async route=>{
  const req=route.request(), u=new URL(req.url());
  if(req.isNavigationRequest()){
   navigations.push(req.url());
   navigationRequests.push({url:req.url(),method:req.method(),body:req.postData(),mainFrame:req.frame()===page.mainFrame()});
   if(req.method()==='POST'){
    const form=new URLSearchParams(req.postData());
    const result=phpCode({action:'save',url:form.get('impactshop_identity_return'),contract:form.get('impactshop_identity_code_save_contract')});
    phpResults.push(result);assert.equal(result.redirect[1],303);assert.deepEqual(result.writes,[]);
    // Playwright does not re-route all HTTP redirect hops: replay the real PHP decision as a fresh GET.
    return route.fulfill({contentType:'text/html',body:'<script>location.replace('+JSON.stringify(result.redirect[0]).replace(/</g,'\\u003c')+')</script>'});
   }
   if(u.pathname==='/profil/belepesi-kod/'){
    const result=phpCode({action:'reveal',url:generatedReturn});phpResults.push(result);
    return route.fulfill({contentType:'text/html',body:result.html});
   }
   if(navigations.length>1 || u.host!=='app.sharity.hu') return route.fulfill({contentType:'text/html',body:'<p>Mock return destination</p>'});
   const body=render(req.url(),kind,disabled);
   return route.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>${body}<script>window.impactshopIdentityPanel={restNonce:'fixture',codeSaveContract:'2'};</script><script src="/fixture/core.js"></script><script src="/fixture/overlay.js"></script></body></html>`});
  }
  if(u.pathname==='/fixture/core.js'||u.pathname==='/fixture/overlay.js')return route.fulfill({contentType:'application/javascript',body:u.pathname.includes('core')?core:overlay});
  if(u.host==='app.sharity.hu' && u.pathname.startsWith('/wp-json/')) {
   let body={},status=200;
   if(req.method()==='POST') posts.push({path:u.pathname,body:req.postDataJSON()});
   if(u.pathname.endsWith('/identity/profile')) body={pseudo_id:'abcdef123456',nickname:'Fixture nickname',identity_state:'active',votes_available:17};
   if(u.pathname.endsWith('/identity/code/generate')) {
    const result=phpCode({action:'generate',url:req.postDataJSON().profile_return_url});phpResults.push(result);
    assert.equal(result.status,200);generatedReturn=result.reveal.profile_return_url;
    body={reveal_url:base+'belepesi-kod/'};
   }
   if(u.pathname.endsWith('/identity/restore')) {body=restoreDenied?{message:'Fixture rejected credentials'}:{pseudo_id:'abcdef123456',recovery_code:'ABCD1234EFGH'};status=restoreDenied?403:200;}
   if(u.pathname.endsWith('/pseudo/vacation'))body={active:false,remaining_activations:2};
   return route.fulfill({status,contentType:'application/json',body:JSON.stringify(body)});
  }
  escapes.push(req.url());return route.abort();
 });
 await page.goto(url);
 await page.waitForFunction(()=>document.querySelector('[data-role=pseudo-display]').textContent.includes('abcdef123456'));
 await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
 return {context,page,errors,posts,escapes,navigations,navigationRequests,phpResults};
}
async function finish(f){assert.deepEqual(f.errors,[]);assert.deepEqual(f.escapes,[]);await f.context.close();}
async function switchAll(page){for(const lang of ['de','fr','en','hu'])await page.locator('#impactshop-profile-language').selectOption(lang);for(const currency of ['EUR','USD','GBP','HUF'])await page.locator('#impactshop-profile-strip select').nth(1).selectOption(currency);}

test('PHP exact host/path and execution-context gate',()=>{
 const file=resolve(root,'wp-content/mu-plugins/impactshop-profile-ui.php');
 const script=`define('ABSPATH','/');function add_action(...$x){}function is_admin(){return getenv('CTX')==='admin';}function wp_doing_ajax(){return getenv('CTX')==='ajax';}function wp_doing_cron(){return getenv('CTX')==='cron';}define('REST_REQUEST',getenv('CTX')==='rest');$_SERVER['HTTP_HOST']=getenv('HOST');$_SERVER['REQUEST_URI']=getenv('URI');require $argv[1];impactshop_profile_ui_render();`;
 for(const [host,path,ctx,expected] of [['app.sharity.hu','/profil','',true],['app.sharity.hu','/profil/?x=1','',true],['app.sharity.hu','/profil//','',false],['app.sharity.hu','/profil/child','',false],['app.sharity.hu','/impactshop/','',false],['sharity.hu','/profil/','',false],['app.sharity.hu:443','/profil/','',false],...['admin','ajax','cron','rest'].map(x=>['app.sharity.hu','/profil/',x,false])]){
  const output=execFileSync('php',['-r',script,file],{env:{...process.env,HOST:host,URI:path,CTX:ctx},encoding:'utf8'});assert.equal(output.includes('id="impactshop-profile-ui"'),expected,`${host}${path} ${ctx}`);
 }
});
for(const kind of ['legacy','managed']) {
 test(`${kind}: preferences preserve real identity nodes, requests, raw return and browser history`,async()=>{
  const f=await fixture(kind,{url:base+suffix.replace('?','?ui_lang=fr&ui_currency=GBP&')}); const {page}=f;
  assert.equal(await page.locator('#impactshop-profile-language').inputValue(),'fr');
  assert.equal(await page.locator('#impactshop-profile-strip select').nth(1).inputValue(),'GBP');
  assert.equal(page.url(),base+suffix);
  assert.equal(f.posts.filter(p=>p.path.endsWith('/selection-intent/complete')).length,1);
  const initialPosts=structuredClone(f.posts), historyLength=await page.evaluate(()=>history.length);
  await page.evaluate(()=>{window.savedPanel=document.querySelector('.impactshop-identity-panel');window.savedForm=savedPanel.querySelector('[data-role=save-form]');window.savedRestore=savedPanel.querySelector('[data-role=restore-submit]');window.clicked=0;savedRestore.addEventListener('fixture-probe',()=>clicked++);document.querySelector('[data-role=nickname-input]').value='User text';document.querySelector('[data-role=restore-recovery]').value='ABCD1234EFGH';window.returnBefore=document.querySelector('[data-role=save-return]').value;});
  await switchAll(page);
  await page.locator('#impactshop-profile-strip summary').click();assert.ok((await page.locator('#impactshop-profile-strip details p').textContent()).includes('elszámolást'));
  assert.equal(page.url(),base+suffix);assert.equal(f.navigations.length,1);assert.deepEqual(f.posts,initialPosts);
  assert.deepEqual(await page.evaluate(()=>history.state),{keep:'unaltered'});assert.equal(await page.evaluate(()=>history.length),historyLength);
  assert.equal(await page.locator('[data-role=nickname-input]').inputValue(),'User text');assert.equal(await page.locator('[data-role=restore-recovery]').inputValue(),'ABCD1234EFGH');
  assert.equal(await page.evaluate(()=>savedPanel===document.querySelector('.impactshop-identity-panel')&&savedForm===document.querySelector('[data-role=save-form]')&&savedRestore===document.querySelector('[data-role=restore-submit]')&&returnBefore===document.querySelector('[data-role=save-return]').value),true);
  assert.equal(await page.locator('.sharity-action-bar a:visible').count(),8);assert.equal(await page.locator('[data-bar=messages-upcoming]').count(),1);assert.equal(await page.locator('[data-bar=messages-upcoming]').isVisible(),false);
  assert.equal(await page.locator('[data-bar=my-causes]').getAttribute('href'),'https://sharity.hu/sajat-sharity');assert.equal(await page.locator('[data-bar=account]').getAttribute('href'),base+'#impactshop-account-top');
  assert.equal(await page.locator('#impactshop-profile-strip').evaluate(el=>el.nextElementSibling===window.savedPanel),true);
  assert.equal(await page.locator('#sharity-slc-btn').evaluate(el=>getComputedStyle(el).position),'static');
  // The original same-page profile handler keeps query bytes, and history Back still works.
  await page.evaluate(()=>history.pushState({probe:1},'',location.pathname+location.search+'#probe'));
  await page.locator('[data-bar=account]').click();assert.equal(page.url(),base+suffix);
  await page.goBack();assert.ok(page.url().endsWith('#probe'));await page.goForward();assert.equal(page.url(),base+suffix);
  await finish(f);
 });
 test(`${kind}: country sheet retains original handlers and visible focus target`,async()=>{
  const f=await fixture(kind);await f.page.locator('#sharity-slc-btn').click();
  const close=f.page.locator('#sharity-slc-sheet button').filter({hasText:'×'});
  assert.equal(await close.count(),1);await close.click();
  assert.equal(await f.page.evaluate(()=>document.activeElement.id),'sharity-slc-btn');
  await f.page.locator('#sharity-slc-btn').click();await f.page.keyboard.press('Shift+Tab');
  assert.equal(await f.page.evaluate(()=>document.activeElement.getBoundingClientRect().height>0),true);
  await f.page.keyboard.press('Escape');assert.equal(await f.page.evaluate(()=>document.activeElement.id),'sharity-slc-btn');await finish(f);
 });
 test(`${kind}: blocked optional preference storage still switches without navigation`,async()=>{
  const f=await fixture(kind,{blocked:true,url:base+'?ui_lang=de&ui_currency=EUR&return=a%2fb'});assert.equal(await f.page.locator('#impactshop-profile-language').inputValue(),'de');await switchAll(f.page);assert.equal(f.navigations.length,1);await finish(f);
 });
 test(`${kind}: PWA native prompt, collapse, consent and standalone ownership`,async()=>{
  const mobile=await fixture(kind);const {page}=mobile;
  for(const width of [320,390])for(const lang of ['hu','en','de','fr']){
   await page.setViewportSize({width,height:900});await page.locator('#impactshop-profile-language').selectOption(lang);
   const dock=await page.locator('.sharity-action-bar').boundingBox(), utility=await page.locator('#impactshop-mobile-utility').boundingBox();
   assert.ok(utility.y+utility.height<=dock.y,`${width}/${lang}: PWA overlaps dock ${JSON.stringify({dock,utility})}`);
  }
  await page.locator('#impactshop-profile-language').selectOption('hu');
  if(process.env.PROFILE_UI_SCREENSHOTS_DIR){mkdirSync(process.env.PROFILE_UI_SCREENSHOTS_DIR,{recursive:true});await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:resolve(process.env.PROFILE_UI_SCREENSHOTS_DIR,kind+'-mobile.png')});}
  await page.evaluate(()=>{const event=new Event('beforeinstallprompt',{cancelable:true});event.prompt=()=>{installCalls++;};event.userChoice=Promise.resolve({outcome:'dismissed'});window.dispatchEvent(event);});
  await page.locator('[data-role=pwa-mobile-install]').click();assert.equal(await page.evaluate(()=>installCalls),1);
  await page.locator('[data-role=pwa-mobile-toggle]').click();assert.equal(await page.locator('#impactshop-mobile-utility').getAttribute('data-state'),'collapsed');
  await page.locator('[data-role=pwa-mobile-toggle]').click();assert.equal(await page.locator('#impactshop-mobile-utility').getAttribute('data-state'),'open');
  assert.ok((await page.locator('[data-role=pwa-mobile-toggle]').boundingBox()).width>=44);
  await page.evaluate(()=>document.body.classList.add('sharity-consent-blocking'));assert.equal(await page.locator('#impactshop-mobile-utility').isVisible(),false);await finish(mobile);
  const desktop=await fixture(kind,{width:1280});if(process.env.PROFILE_UI_SCREENSHOTS_DIR){await desktop.page.evaluate(()=>scrollTo(0,0));await desktop.page.screenshot({path:resolve(process.env.PROFILE_UI_SCREENSHOTS_DIR,kind+'-desktop.png')});}assert.equal(await desktop.page.locator('#impactshop-pwa-install').isVisible(),true);assert.ok((await desktop.page.locator('#impactshop-pwa-install').boundingBox()).width>=44);await finish(desktop);
  const standalone=await fixture(kind,{standalone:true});for(const id of ['impactshop-pwa-install','impactshop-pwa-reopen','impactshop-mobile-utility'])assert.equal(await standalone.page.locator('#'+id).isVisible(),false);await finish(standalone);
 });
 for(const denied of [false,true])test(`${kind}: real restore ${denied?'rejection':'completion'} remains unchanged after UI choices`,async()=>{
  const f=await fixture(kind,{url:base+'?bridge_target=restore&return='+encodeURIComponent('https://sharity.hu/hatas-korok'),restoreDenied:denied});await switchAll(f.page);
  await f.page.locator('[data-role=restore-pseudo]').fill('abcdef123456');await f.page.locator('[data-role=restore-recovery]').fill('ABCD1234EFGH');await f.page.locator('[data-role=restore-submit]').click();
  if(denied){await f.page.waitForFunction(()=>document.querySelector('[data-role=restore-status]').textContent==='Fixture rejected credentials');await f.page.waitForTimeout(1050);assert.equal(f.navigations.length,1);}
  else {await f.page.waitForURL(u=>u.searchParams.has('impactshop_profile_return_complete'));assert.ok(f.page.url().includes(encodeURIComponent('https://sharity.hu/hatas-korok')));}
  const restores=f.posts.filter(p=>p.path.endsWith('/identity/restore'));assert.equal(restores.length,1);assert.equal(restores[0].body.pseudo_id,'abcdef123456');assert.equal(restores[0].body.recovery_code,'ABCD1234EFGH');await finish(f);
 });
}

for(const kind of ['legacy','managed']) {
 test(`${kind}: JYSK blank save preserves context without generation, credentials or navigation`,async()=>{
  const f=await fixture(kind,{url:base+jyskSuffix});await switchAll(f.page);
  assert.equal(f.page.url(),base+jyskSuffix);
  assert.equal(await f.page.locator('[data-role=jysk-return]').isVisible(),false); // Raw signed/opaque context is deliberately not the strict direct-link lane.
  assert.equal(await f.page.locator('[data-role=jysk-return]').count(),0);
  await f.page.locator('[data-role=save-password-manager]').click();
  assert.match(await f.page.locator('[data-role=status]').textContent(),/Add meg a már elmentett/);
  assert.deepEqual(f.posts,[]);assert.equal(f.navigations.length,1);
  assert.deepEqual(await f.page.evaluate(()=>credentialStores),[]);
  await finish(f);
  const exact=await fixture(kind,{url:base+'?jysk_return=1&bridge_target=account'});await switchAll(exact.page);
  assert.equal(await exact.page.locator('[data-role=jysk-return]').isVisible(),true);
  assert.equal(await exact.page.locator('[data-role=jysk-return]').getAttribute('href'),'https://jysk.sharity.hu/api/jysk/game/session/start');
  assert.deepEqual(exact.posts,[]);assert.equal(exact.navigations.length,1);await finish(exact);
 });
 test(`${kind}: current typed-code save uses one native POST and exact JYSK v2 PHP return`,async()=>{
  const results=[];
  for(const disabled of [true,false]) {
   const f=await fixture(kind,{disabled,url:base+jyskSuffix});if(!disabled)await switchAll(f.page);
   await f.page.locator('[data-role=save-password]').fill('ABCD-EFGH-IJKL');
   assert.deepEqual(await f.page.evaluate(()=>credentialStores),[]);
   await f.page.locator('[data-role=save-password-manager]').click();
   await f.page.waitForURL(base+jyskSuffix.replace('#','&impactshop_profile_return_complete=1#'));
   const native=f.navigationRequests.filter(r=>r.method==='POST');assert.equal(native.length,1);
   const form=new URLSearchParams(native[0].body);
   assert.equal(native[0].url,'https://app.sharity.hu/');assert.equal(native[0].mainFrame,true);
   assert.equal(form.get('impactshop_identity_return'),base+jyskSuffix);
   assert.equal(form.get('impactshop_identity_code_save_contract'),'2');
   assert.equal(form.get('username'),'abcdef123456');assert.equal(form.get('password'),'ABCD-EFGH-IJKL');
   assert.deepEqual(f.posts,[]);assert.equal(f.navigations.length,3);assert.equal(f.context.pages().length,1);
   results.push({native,php:f.phpResults,url:f.page.url()});await finish(f);
  }
  assert.deepEqual(results[1],results[0]);
 });
 test(`${kind}: explicit JYSK generation and actual one-time reveal preserve v2 save contract`,async()=>{
  const f=await fixture(kind,{url:base+jyskSuffix});await switchAll(f.page);
  f.page.once('dialog',d=>d.dismiss());await f.page.locator('[data-role=generate-code]').click();
  assert.deepEqual(f.posts,[]);assert.equal(f.navigations.length,1);
  f.page.once('dialog',d=>d.accept());await f.page.locator('[data-role=generate-code]').click();
  await f.page.waitForURL(base+'belepesi-kod/');
  assert.deepEqual(f.posts,[{path:'/wp-json/impact/v1/identity/code/generate',body:{code_save_contract:2,confirm_generation:true,profile_return_url:base+jyskSuffix}}]);
  assert.equal(f.context.pages().length,1);assert.equal(f.navigations.length,2);
  assert.equal(f.phpResults[0].writes.length,2);assert.equal(f.phpResults[1].consumed,1);
  assert.equal(await f.page.locator('[name=impactshop_identity_code_save_contract]').inputValue(),'2');
  const expected=base+jyskSuffix.replace('#','&impactshop_profile_return_complete=1#');
  assert.equal(await f.page.locator('[name=impactshop_identity_return]').inputValue(),expected);
  assert.equal(await f.page.locator('[name=username]').getAttribute('autocomplete'),'username');
  assert.equal(await f.page.locator('[name=password]').getAttribute('autocomplete'),'new-password');
  await f.page.getByRole('button',{name:'Mentés a jelszókezelőbe és tovább'}).click();
  await f.page.waitForURL(expected);
  const native=f.navigationRequests.filter(r=>r.method==='POST');assert.equal(native.length,1);
  assert.equal(new URLSearchParams(native[0].body).get('impactshop_identity_return'),expected);
  assert.equal(f.phpResults[2].redirect[0],expected);assert.deepEqual(f.phpResults[2].writes,[]);
  assert.equal(f.navigations.length,4);assert.equal(f.posts.length,1);await finish(f);
 });
}

for(const kind of ['legacy','managed'])test(`${kind}: legacy lang=en cannot override repeated explicit UI language choices`,async()=>{
 const f=await fixture(kind,{url:base+'?lang=en&return=a%2fb'});
 const labels={hu:['Nyelv','Deviza','Ország'],de:['Sprache','Anzeigewährung','Land'],fr:['Langue','Devise d’affichage','Pays']};
 for(const lang of ['hu','de','fr','hu']){
  await f.page.locator('#impactshop-profile-language').selectOption(lang);
  await f.page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));
  assert.deepEqual(await f.page.locator('#impactshop-profile-strip label > span').allTextContents(),labels[lang].slice(0,2));
  assert.equal(await f.page.locator('#sharity-slc-btn .sharity-slc__label').textContent(),labels[lang][2]);
 }
 assert.equal(f.page.url(),base+'?lang=en&return=a%2fb');await finish(f);
});
