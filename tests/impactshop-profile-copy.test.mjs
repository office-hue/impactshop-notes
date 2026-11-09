// Actual PHP profile/actionbar, existing overlay/core and installed Chrome; all HTTP local.
import assert from 'node:assert/strict';
import { before, after, test } from 'node:test';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT || root, 'package.json'));
const { chromium } = require('playwright');
const assets = Object.fromEntries(['overlay', 'core'].map(name => [name, readFileSync(resolve(root,
 'wp-content/mu-plugins/impactshop-identity-panel' + (name === 'overlay' ? '-intl-overlay' : '') + '.js'), 'utf8')]));
const headings = {hu:'Az impactod egy helyen', en:'Your impact in one place', de:'Dein Impact an einem Ort', fr:'Votre impact en un seul endroit'};
let browser;
before(async () => { browser = await chromium.launch({channel:'chrome',headless:true,proxy:{server:'http://127.0.0.1:9'}}); });
after(async () => { await browser?.close(); });
async function fixture(language, {legacy = '', core = false, noBar = false, onPost, apiData = {}, beforeLoad, profileData = {}, contract = "2", onProfile, compact = false, initialUrl, pointsData = {points_total:1234,level:{current:'basic'},benefits:{donation_multiplier:1.25,vote_weight_ad:7,discount_percent:12}}, vacationData = {active:false,remaining_activations:2}, viewport = {width:375,height:850}} = {}) {
 const context = await browser.newContext({viewport,serviceWorkers:'block'});
 if (beforeLoad) await context.addInitScript(beforeLoad);
 const page = await context.newPage(); const errors = [], escaped = [], posts = [], navigations = [], navigationRequests = [];
 page.on('pageerror', e => errors.push(e.message));
 await page.route('**/*', async route => {
  const request = route.request(), url = new URL(request.url());
  if (url.pathname === '/fixture/overlay.js' || url.pathname === '/fixture/core.js') {
   return route.fulfill({contentType:'application/javascript; charset=utf-8',body:assets[url.pathname.includes('overlay')?'overlay':'core']});
  }
  if (request.isNavigationRequest() && url.host === 'app.sharity.hu') {
   navigationRequests.push({url:request.url(),method:request.method(),body:request.postData()});
   if (url.pathname !== '/profil/' || url.searchParams.has('impactshop_profile_return_complete')) { navigations.push(request.url()); return route.fulfill({contentType:'text/html',body:'Mock destination'}); }
   let body = execFileSync('php',[resolve(root,'tests/fixtures/impactshop-profile-copy-render.php'),request.url()],{encoding:'utf8',env:{...process.env,PROFILE_COPY_COMPACT:compact?'1':'0'}});
   if (noBar) body = body.slice(0,body.indexOf('<nav class="sharity-action-bar"'));
   return route.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html lang="hu"><body>${body}
    <script>window.impactshopIdentityPanel={restNonce:'fixture',codeSaveContract:${JSON.stringify(contract)}};</script>
    ${core?'<script src="/fixture/core.js"></script>':''}<script src="/fixture/overlay.js"></script></body></html>`});
  }
  if (core && url.host === 'app.sharity.hu' && url.pathname.startsWith('/wp-json/')) {
   let body = {}, status = 200;
   if (request.method() === 'POST') {
    posts.push({path:url.pathname,body:request.postDataJSON()});
    if (onPost) return onPost(route, url);
   }
   if (url.pathname.endsWith('/identity/profile')) {
    if (request.method() === 'GET' && onProfile) return onProfile(route);
    body = {pseudo_id:'abcdef123456',nickname:'Profilod',identity_state:'active',votes_available:17,...profileData};
    if (request.method() === 'POST') {status=422;body={message:'Fixture rejection'};}
   }
   if (url.pathname.endsWith('/pseudo/referral')) body = {referral_code:'Mentés',url:'https://app.sharity.hu/?ref=Mentés'};
   if (url.pathname.endsWith('/pseudo/points')) body = pointsData;
   if (url.pathname.endsWith('/pseudo/vacation')) body = vacationData;
   if (Object.hasOwn(apiData,url.pathname)) body=apiData[url.pathname];
   return route.fulfill({status,contentType:'application/json',body:JSON.stringify(body)});
  }
  escaped.push(request.url()); return route.abort();
 });
 await page.goto(initialUrl || `https://app.sharity.hu/profil/?ui_lang=${language}&ui_currency=GBP${legacy?'&lang='+legacy:''}&return=a%20b%2f~#account`);
 if (!noBar && !compact) await page.waitForFunction(text=>document.querySelector('.impactshop-profile-intro h2')?.textContent===text,headings[language]);
 return {page,context,errors,escaped,posts,navigations,navigationRequests};
}
async function finish(f) { assert.deepEqual(f.errors,[]); assert.deepEqual(f.escaped,[]); await f.context.close(); }
async function settle(page) { await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))); }

test('code-save: JYSK return is visible only for an active owner and exact JYSK context', async()=>{
 for(const state of ['binding_pending','invalid_or_revoked','unavailable','active']) {
  const f=await fixture('hu',{core:true,noBar:true,initialUrl:'https://app.sharity.hu/profil/?jysk_return=1',profileData:{identity_state:state}});
  await active(f);
  const link=f.page.locator('[data-role=jysk-return]');
  assert.equal(await link.count(),1);
  assert.equal(await link.isVisible(),state==='active');
  if(state!=='active') assert.equal(await link.evaluate(e=>getComputedStyle(e).display),'none');
  assert.equal(await link.getAttribute('href'),'https://jysk.sharity.hu/api/jysk/game/session/start');
  assert.deepEqual(f.posts,[]);await finish(f);
 }
 const other=await fixture('hu',{core:true,noBar:true,initialUrl:'https://app.sharity.hu/profil/?jysk_return=1&return=https%3A%2F%2Fapp.sharity.hu%2Fhatas-korok%2F'});
 assert.equal(await other.page.locator('[data-role=jysk-return]').count(),0);await finish(other);
});

test('code-save: compact panel exposes saved-code field and non-destructive missing-code guidance',async()=>{
 const f=await fixture('hu',{core:true,compact:true});
 await f.page.waitForFunction(()=>!document.querySelector('[data-role=save-password-manager]').disabled);
 assert.equal(await f.page.locator('[data-role=save-password]').isVisible(),true);
 await f.page.locator('[data-role=save-password-manager]').click();
 assert.match(await f.page.locator('[data-role=status]').textContent(),/Add meg a már elmentett/);
 assert.equal(await f.page.locator('[data-role=manage-profile]').evaluate(e=>e===document.activeElement),true);
 assert.deepEqual(f.posts,[]);assert.deepEqual(f.navigations,[]);await finish(f);
});
test('code-save: blank save never generates, grants points or opens another page', async()=>{
 const f=await fixture('hu',{core:true});await active(f);
 let dialogs=0;f.page.on('dialog',async d=>{dialogs++;await d.dismiss();});
 await f.page.locator('[data-role=save-password-manager]').click();await settle(f.page);
 assert.deepEqual(f.posts,[]);assert.deepEqual(f.navigations,[]);assert.equal(dialogs,0);
 assert.match(await f.page.locator('[data-role=status]').textContent(),/Add meg a már elmentett/);
 assert.equal(await f.page.locator('[data-role=generate-code]').evaluate(e=>e===document.activeElement),true);
 await finish(f);
});
test('code-save: typed code submits exactly one native form with unmodified raw return',async()=>{
 const f=await fixture('hu',{core:true});await active(f);const original=f.page.url();
 await f.page.locator('[data-role=save-password]').fill('ABCD-EFGH-IJKL');
 await f.page.locator('[data-role=save-password-manager]').click();
 await f.page.waitForURL('https://app.sharity.hu/');
 const reqs=f.navigationRequests.filter(r=>r.method==='POST');assert.equal(reqs.length,1);
 const body=new URLSearchParams(reqs[0].body);
 assert.equal(body.get('impactshop_identity_return'),original);
 assert.equal(body.get('impactshop_identity_code_save_contract'),'2');
 assert.equal(body.get('username'),'abcdef123456');assert.equal(body.get('password'),'ABCD-EFGH-IJKL');
 assert.deepEqual(f.posts,[]);await finish(f);
});
test('code-save: explicit generation needs confirmation and requests only one reveal',async()=>{
 const f=await fixture('hu',{core:true,onPost:(route,url)=>{
  assert.equal(url.pathname,'/wp-json/impact/v1/identity/code/generate');
  return route.fulfill({contentType:'application/json',body:JSON.stringify({reveal_url:'https://app.sharity.hu/profil/belepesi-kod/'})});
 }});await active(f);const original=f.page.url();
 f.page.once('dialog',d=>d.dismiss());await f.page.locator('[data-role=generate-code]').click();assert.equal(f.posts.length,0);
 f.page.once('dialog',d=>d.accept());await f.page.locator('[data-role=generate-code]').click();
 await f.page.waitForURL('**/profil/belepesi-kod/');
 assert.equal(f.posts.length,1);assert.deepEqual(f.posts[0].body,{code_save_contract:2,confirm_generation:true,profile_return_url:original});
 assert.equal(f.navigationRequests.filter(r=>r.url.includes('/belepesi-kod/')).length,1);
 assert.equal(f.context.pages().length,1);await finish(f);
});
test('code-save: new client on old server asks to reload without write',async()=>{
 const f=await fixture('hu',{core:true,contract:null});await active(f);
 await f.page.locator('[data-role=generate-code]').click();
 assert.match(await f.page.locator('[data-role=status]').textContent(),/töltsd újra/);
 await f.page.locator('[data-role=save-password]').fill('ABCD-EFGH-IJKL');
 await f.page.locator('[data-role=save-password-manager]').click();await settle(f.page);
 assert.deepEqual(f.posts,[]);assert.deepEqual(f.navigations,[]);await finish(f);
});
test('code-save: refresh bypasses cached profile and does not report failed or pending success',async()=>{
 let reads=0, mode='active';
 const f=await fixture('hu',{core:true,onProfile:route=>{
  reads++;return route.fulfill({status:mode==='error'?503:200,contentType:'application/json',body:JSON.stringify({pseudo_id:'abcdef123456',identity_state:mode==='pending'?'binding_pending':'active',votes_available:0})});
 }});await active(f);const initial=reads;
 await f.page.locator('[data-role=refresh-profile]').click();
 await f.page.waitForFunction(()=>document.querySelector('[data-role=status]').textContent==='Profiladatok frissítve.');assert.equal(reads,initial+1);
 mode='error';await f.page.locator('[data-role=refresh-profile]').click();
 await f.page.waitForFunction(()=>document.querySelector('[data-role=status]').textContent.includes('Nem sikerült frissíteni'));
 mode='pending';await f.page.locator('[data-role=refresh-profile]').click();
 await f.page.waitForFunction(()=>!document.querySelector('[data-role=refresh-profile]').disabled);
 assert.equal(reads,initial+3);assert.notEqual(await f.page.locator('[data-role=status]').textContent(),'Profiladatok frissítve.');
 assert.deepEqual(f.posts,[]);await finish(f);
});

test('four languages with either legacy page language; original markup, fields, return and dynamic state preserved', async () => {
 for (const legacy of ['', 'en']) for (const language of Object.keys(headings)) {
  const f=await fixture(language,{legacy}); const {page}=f;
  assert.equal(await page.locator('.impactshop-identity-header > h3').textContent(),{hu:'Profilod',en:'Your profile',de:'Dein Profil',fr:'Votre profil'}[language]);
  assert.equal(await page.locator('[data-role=save-nickname]').textContent(),{hu:'Mentés',en:'Save',de:'Speichern',fr:'Enregistrer'}[language]);
  assert.equal(await page.locator('[data-role=restore-submit]').textContent(),{hu:'Belépés',en:'Sign in',de:'Anmelden',fr:'Se connecter'}[language]);
  assert.equal(await page.locator('[data-role=generate-code]').textContent(),{hu:'Új belépési kód készítése',en:'Create a new login code',de:'Neuen Anmeldecode erstellen',fr:'Créer un nouveau code de connexion'}[language]);
  assert.equal(await page.locator('#impactshop-restore-title button').count(),1);
  assert.equal(await page.locator('#impactshop-restore-title button').getAttribute('aria-describedby'),'impactshop-signin-help');
  assert.equal(await page.locator('[data-role=restore-recovery]').getAttribute('type'),'password');
  assert.equal(await page.locator('[data-role=restore-recovery]').getAttribute('autocomplete'),'current-password');
  assert.ok((await page.locator('[data-role=save-return]').inputValue()).includes('return=a%20b%2f~'));
  assert.equal(await page.locator('[data-role=points-info]').isVisible(),false);
  // Deliberately identical to dictionary keys, but these are data, not UI copy.
  await page.evaluate(()=>{
   for (const role of ['nickname-display','pseudo-display','referral-code','referral-link','account-message']) document.querySelector(`[data-role="${role}"]`).textContent='Profilod';
   document.querySelector('[data-role=nickname-input]').value='Értesítések';
   document.querySelector('[data-role=restore-recovery]').value='Mentés';
   const amount=document.createElement('span');amount.id='fixture-money';amount.textContent='1 234,56 Ft';document.querySelector('.impactshop-profile-shell').appendChild(amount);
  });
  await settle(page);
  for (const role of ['nickname-display','pseudo-display','referral-code','referral-link','account-message']) assert.equal(await page.locator(`[data-role=${role}]`).textContent(),'Profilod');
  assert.equal(await page.locator('[data-role=nickname-input]').inputValue(),'Értesítések');
  assert.equal(await page.locator('[data-role=restore-recovery]').inputValue(),'Mentés');
  assert.equal(await page.locator('#fixture-money').textContent(),'1 234,56 Ft');
  assert.equal(await page.locator('[data-role=points-total]').textContent(),{hu:'0 pont',en:'Points: 0',de:'Punkte: 0',fr:'Points : 0'}[language]);
  await finish(f);
 }
});
test('repeated local language changes preserve listeners, focus, values, hrefs and stable DOM; currency alone changes no copy', async () => {
 const f=await fixture('de'); const {page}=f;
 const result=await page.evaluate(async()=>{
  const root=document.querySelector('.impactshop-profile-shell'), bar=document.querySelector('.sharity-action-bar');
  const button=root.querySelector('[data-role=referral-copy]'), input=root.querySelector('[data-role=nickname-input]');
  let clicked=0;button.addEventListener('click',()=>clicked++);input.value='Mentés';input.focus();
  const hrefs=Array.from(document.querySelectorAll('a'),a=>a.getAttribute('href'));
  for (const language of ['fr','en','hu','de','fr']) {
   bar.lang=language;
   await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));
   if (root.querySelector('.impactshop-profile-intro h2').lang!==language) throw new Error('stale language');
  }
  button.click();
  return {clicked,sameButton:button===root.querySelector('[data-role=referral-copy]'),focus:document.activeElement===input,value:input.value,hrefs,after:Array.from(document.querySelectorAll('a'),a=>a.getAttribute('href'))};
 });
 assert.equal(result.clicked,1); assert.equal(result.sameButton,true);assert.equal(result.focus,true);assert.equal(result.value,'Mentés');assert.deepEqual(result.hrefs,result.after);
 await page.locator('#sharity-slc-btn').click(); await page.locator('[data-slc-language=fr]').click();
 await settle(page); const copy=await page.locator('.impactshop-profile-shell').innerText();
 await page.locator('#sharity-slc-currency').selectOption('EUR'); await settle(page);
 assert.equal(await page.locator('.impactshop-profile-shell').innerText(),copy);
 await page.keyboard.press('Escape');
 // No self-triggering mutation loop after settling.
 assert.equal(await page.evaluate(()=>new Promise(resolve=>{let n=0;const o=new MutationObserver(rs=>n+=rs.length);o.observe(document.querySelector('.impactshop-profile-shell'),{subtree:true,childList:true,characterData:true,attributes:true});setTimeout(()=>{o.disconnect();resolve(n);},100);})),0);
 await finish(f);
});
test('existing core handlers survive copy changes: info toggle, profile refresh and rejected nickname save', async () => {
 const f=await fixture('de',{core:true}); const {page}=f;
 await page.waitForFunction(()=>document.querySelector('[data-role=pseudo-display]').textContent==='abcdef123456');
 await page.locator('[data-role=referral-info-trigger]').focus();
 await page.keyboard.press('Enter');
 assert.equal(await page.locator('[data-role=referral-info]').isVisible(),true);
 await page.locator('[data-role=refresh-profile]').click();
 await page.locator('[data-role=nickname-input]').fill('Mentés');
 await page.locator('[data-role=save-nickname]').click();
 await page.waitForFunction(()=>document.querySelector('[data-role=nickname-status]').textContent==='Speichern fehlgeschlagen.');
 assert.equal(await page.locator('[data-role=save-nickname]').textContent(),'Speichern');
 assert.equal(await page.locator('[data-role=save-nickname]').isEnabled(),true);
 assert.ok(f.posts.some(p=>p.path.endsWith('/identity/profile')&&p.body.nickname==='Mentés'&&p.body.pseudo_id==='abcdef123456'));
 assert.equal(await page.locator('[data-role=nickname-display]').textContent(),'Profilod');
 await finish(f);
});
test('without the new selector legacy EN still works; later selector mounts are localized', async () => {
 const f=await fixture('de',{legacy:'en',noBar:true}); const {page}=f;
 assert.equal(await page.locator('.impactshop-identity-header > h3').textContent(),'Your profile');
 assert.equal(await page.locator('[data-role=save-username]').getAttribute('placeholder'),'ID');
 await page.evaluate(()=>{const nav=document.createElement('nav');nav.className='sharity-action-bar';nav.setAttribute('data-sharity-preferences-managed','');nav.lang='fr';document.body.appendChild(nav);});
 await page.waitForFunction(()=>document.querySelector('.impactshop-profile-intro h2').textContent==='Votre impact en un seul endroit');
 assert.equal(await page.locator('[data-role=nickname-input]').getAttribute('placeholder'),'p. ex. Anna');
 await finish(f);
});
test('unchanged profile design contains longer copy on mobile, landscape and desktop', async () => {
 for (const viewport of [{width:375,height:850},{width:667,height:375},{width:1280,height:900}]) {
  const f=await fixture('de',{viewport,core:true,profileData:{nickname:'Anna'}});const {page}=f;
  await page.waitForFunction(()=>document.querySelectorAll('[data-role=points-benefits] strong').length===3);
  await settle(page);
  const overflow=await page.locator('.impactshop-profile-shell').evaluate(root=>Array.from(root.querySelectorAll('[data-sharity-profile-copy], [data-sharity-dynamic-copy]')).filter(el=>el.clientWidth&&el.scrollWidth>el.clientWidth+1).map(el=>el.textContent));
  assert.deepEqual(overflow,[]);
  if (process.env.SHARITY_INTL_SCREENSHOTS==='1') {mkdirSync(resolve(root,'output/playwright'),{recursive:true});await page.screenshot({path:resolve(root,`output/playwright/p2q-${viewport.width}.png`),fullPage:true});}
  await finish(f);
 }
});

test('dynamic copy round-trips exact name/number strings while preserving strong nodes, controls and unknown messages', async () => {
 const f=await fixture('hu',{legacy:'en'});const {page}=f;
 const name='  Profilod! <img src=x> $& $1 $` {0}  ';
 const initial={
  greeting:`Szia ${name}! Üdvözöllek a Sharity oldalán.`,
  'votes-available':'1\u00a0234', 'votes-state':'A jelenlegi, elkölthető egyenleged.',
  'points-total':'-1\u202f234,50 pont', 'points-progress-text':'765,50 pont a Bronze szinthez',
  'vacation-status':'Vakáció aktív: 1 nap hátra.', 'vacation-toggle':'Kikapcsolás',
  'herowall-tier':'Legacy Pool helyezés: #17','herowall-points':'Pontszám: 9\u00a0876,5 pont'
 };
 await page.evaluate(initial=>{
  const root=document.querySelector('.impactshop-profile-shell');
  for(const [role,text] of Object.entries(initial)) root.querySelector(`[data-role="${role}"]`).textContent=text;
  const benefits=root.querySelector('[data-role=points-benefits]');
  benefits.innerHTML='<span>🎯 Adomány bónusz: <strong>+25%</strong></span><span>🗳️ Szavazati súly: <strong>×7</strong></span><span>🏷️ Max kedvezményből: <strong>12%</strong></span>';
  window.fixtureStrong=Array.from(benefits.querySelectorAll('strong'));
  window.fixtureStrongClicks=0;window.fixtureStrong[0].addEventListener('click',()=>window.fixtureStrongClicks++);
  const button=root.querySelector('[data-role=vacation-toggle]');button.disabled=true;button.dataset.mode='end';
  const unrelated=document.createElement('div');unrelated.className='impactshop-identity-panel--compact';unrelated.innerHTML='<span data-role="points-total">7 pont</span>';document.body.appendChild(unrelated);
 },initial);
 await settle(page);
 for(const language of ['de','fr','en','hu','fr','hu']) {
  await page.locator('.sharity-action-bar').evaluate((el,lang)=>{el.lang=lang;},language);await settle(page);
  const greeting=await page.locator('[data-role=greeting]').textContent();
  assert.ok(greeting.includes(name)); assert.equal(await page.locator('[data-role=greeting] img').count(),0);
  assert.equal(await page.locator('.impactshop-profile-shell [data-role=votes-available]').textContent(),initial['votes-available']);
  assert.ok((await page.locator('.impactshop-profile-shell [data-role=points-total]').textContent()).includes('-1\u202f234,50'));
  assert.ok((await page.locator('[data-role=points-progress-text]').textContent()).includes('765,50'));
  assert.ok((await page.locator('[data-role=points-progress-text]').textContent()).includes('Bronze'));
  assert.deepEqual(await page.locator('[data-role=points-benefits] strong').allTextContents(),['+25%','×7','12%']);
  assert.equal(await page.evaluate(()=>window.fixtureStrong.every((el,i)=>el===document.querySelectorAll('[data-role=points-benefits] strong')[i])),true);
  assert.equal(await page.locator('[data-role=vacation-toggle]').isDisabled(),true);
  assert.equal(await page.locator('[data-role=vacation-toggle]').getAttribute('data-mode'),'end');
  // The existing legacy EN layer may translate compact text; the new layer never owns it.
  assert.equal(await page.locator('.impactshop-identity-panel--compact [data-sharity-dynamic-copy]').count(),0);
 }
 for(const [role,text] of Object.entries(initial)) assert.equal(await page.locator(`.impactshop-profile-shell [data-role=${role}]`).textContent(),text);
 await page.evaluate(()=>window.fixtureStrong[0].click());assert.equal(await page.evaluate(()=>window.fixtureStrongClicks),1);
 assert.deepEqual(f.posts,[]);await finish(f);
});

test('async native rewrites, replaced nodes and unknown values do not resurrect cached data or retain a false language', async () => {
 const f=await fixture('fr',{legacy:'en'});const {page}=f;
 await page.locator('[data-role=points-total]').evaluate(el=>{el.textContent='999,75 pont';});await settle(page);
 assert.equal(await page.locator('[data-role=points-total]').textContent(),'Points : 999,75');
 await page.locator('[data-role=points-total]').evaluate(el=>{const n=document.createElement('span');n.dataset.role='points-total';n.textContent='777 pont';el.replaceWith(n);});await settle(page);
 assert.equal(await page.locator('[data-role=points-total]').textContent(),'Points : 777');
 const unknown='Profilod: 42 pont <unknown> $&\n';
 await page.locator('[data-role=points-total]').evaluate((el,text)=>{el.textContent=text;},unknown);await settle(page);
 assert.equal(await page.locator('[data-role=points-total]').getAttribute('lang'),null);
 for(const language of ['en','hu','de','fr']) {
  await page.locator('.sharity-action-bar').evaluate((el,lang)=>{el.lang=lang;},language);await settle(page);
  assert.equal(await page.locator('[data-role=points-total]').textContent(),unknown);
 }
 await page.locator('[data-role=points-progress-text]').evaluate(el=>{el.textContent='Legmagasabb szint elérve.';});await settle(page);
 assert.equal(await page.locator('[data-role=points-progress-text]').textContent(),'Niveau maximal atteint.');
 await page.locator('[data-role=herowall-tier]').evaluate(el=>{el.textContent='Legacy Pool helyezés: —';});await settle(page);
 assert.equal(await page.locator('[data-role=herowall-tier]').textContent(),'Position Legacy Pool : —');
 await finish(f);
});

test('real core identity states and vacation mode retain their permission/number behavior after language changes', async () => {
 const expectations={active:'17',legacy_read_only:'Anmeldung erforderlich',invalid_or_revoked:'Verbindung abgelaufen',binding_pending:'Verbindung wird hergestellt…',unavailable:'Vorübergehend nicht verfügbar'};
 for(const [state,available] of Object.entries(expectations)) {
  const f=await fixture('de',{core:true,profileData:{identity_state:state,nickname:'Anna! Mentés'},vacationData:{active:true,days_remaining:3}});const {page}=f;
  await page.waitForFunction(text=>document.querySelector('[data-role=votes-available]').textContent===text,available);
  if(state==='active') await page.waitForFunction(()=>document.querySelector('[data-role=vacation-toggle]').dataset.mode==='end');
  const baseline=await page.locator('[data-role=vacation-toggle]').evaluate(el=>({disabled:el.disabled,mode:el.dataset.mode}));
  assert.equal(baseline.disabled,state!=='active');
  const values=await page.locator('[data-role=points-benefits] strong').allTextContents();
  for(const language of ['fr','en','hu','de']) {
   await page.locator('.sharity-action-bar').evaluate((el,lang)=>{el.lang=lang;},language);await settle(page);
   assert.deepEqual(await page.locator('[data-role=vacation-toggle]').evaluate(el=>({disabled:el.disabled,mode:el.dataset.mode})),baseline);
   if(state==='active') assert.equal(await page.locator('[data-role=votes-available]').textContent(),'17');
   assert.deepEqual(await page.locator('[data-role=points-benefits] strong').allTextContents(),values);
  }
  assert.equal(await page.locator('[data-role=nickname-display]').textContent(),'Anna! Mentés');
  assert.ok((await page.locator('[data-role=greeting]').textContent()).includes('Anna! Mentés'));
  assert.deepEqual(f.posts,[]);await finish(f);
 }
});

test('late selector accepts legacy dynamic English without corrupting nickname parameters', async () => {
 const f=await fixture('de',{core:true,legacy:'en',noBar:true,profileData:{nickname:'Profilod! Mentés $&'}});const {page}=f;
 await page.waitForFunction(()=>document.querySelector('[data-role=greeting]').textContent==='Hi Profilod! Mentés $&! Welcome to Sharity.');
 // Wait for the legacy observer too, not just the asynchronous native HU write.
 await page.waitForFunction(()=>/^[0-9\s]+ points?$/.test(document.querySelector('[data-role=points-total]').textContent));
 const originalNumber=(await page.locator('[data-role=points-total]').textContent()).replace(/ points?$/,'');
 await page.evaluate(()=>{const nav=document.createElement('nav');nav.className='sharity-action-bar';nav.setAttribute('data-sharity-preferences-managed','');nav.lang='fr';document.body.appendChild(nav);});
 await page.waitForFunction(()=>document.querySelector('[data-role=greeting]').textContent==='Bonjour Profilod! Mentés $& ! Bienvenue sur Sharity.');
 assert.equal(await page.locator('[data-role=points-total]').textContent(),'Points : '+originalNumber);
 assert.ok((await page.locator('[data-role=points-progress-text]').textContent()).includes('Bronze'));
 assert.equal(await page.locator('[data-role=nickname-display]').textContent(),'Profilod! Mentés $&');
 await finish(f);
});

// Hold responses until the translated busy state has rendered: an immediate mock
// can miss the original text-dependent finally regression entirely.
function heldPost() {
 let release, seen;
 const pending = new Promise(r=>seen=r);
 const response = new Promise(r=>release=r);
 return {pending, release:(value)=>release(value), handler:async route=>{
  seen(); const value=await response;
  if (value.abort) return route.abort('failed');
  return route.fulfill({status:value.status??200,contentType:'application/json',body:JSON.stringify(value.body??{})});
 }};
}
async function language(page, value) {
 await page.locator('.sharity-action-bar').evaluate((bar,value)=>bar.lang=value,value);await settle(page);
}
async function active(f) {
 await f.page.waitForFunction(()=>document.querySelector('[data-role=pseudo-display]').textContent==='abcdef123456');await settle(f.page);
}
test('nickname save preserves request and success timer; rejected and network failure reset after pending language changes', async()=>{
 for (const outcome of ['success','rejected','network']) {
  const held=heldPost(), f=await fixture('de',{core:true,onPost:held.handler});const {page}=f;await active(f);
  await page.evaluate(()=>{window.nicknameEvents=[];window.addEventListener('impactshop:identity-nickname-updated',e=>window.nicknameEvents.push(e.detail));});
  await page.locator('[data-role=nickname-input]').fill('Profilod! $& <x>');
  await page.locator('[data-role=save-nickname]').click();await held.pending;await settle(page);
  assert.equal(await page.locator('[data-role=save-nickname]').textContent(),'Speichern…');
  assert.equal(await page.locator('[data-role=save-nickname]').isDisabled(),true);
  await language(page,'fr');
  assert.equal(await page.locator('[data-role=save-nickname]').textContent(),'Enregistrement…');
  assert.equal(f.posts.length,1);
  assert.deepEqual(f.posts[0].body,{pseudo_id:'abcdef123456',nickname:'Profilod! $& <x>'});
  held.release(outcome==='network'?{abort:true}:outcome==='rejected'?{status:422,body:{message:'Mentés… server detail <x> $&'}}:{body:{nickname:'Profilod! $& <x>'}});
  await page.waitForFunction(()=>!document.querySelector('[data-role=save-nickname]').disabled);await settle(page);
  if(outcome==='success') {
   assert.equal(await page.locator('[data-role=save-nickname]').textContent(),'Enregistré');
   assert.equal(await page.locator('[data-role=nickname-status]').textContent(),'Pseudo enregistré.');
   assert.deepEqual(await page.evaluate(()=>window.nicknameEvents),[{pseudo_id:'abcdef123456',nickname:'Profilod! $& <x>'}]);
   await language(page,'en');assert.equal(await page.locator('[data-role=save-nickname]').textContent(),'Saved');
   await page.waitForFunction(()=>document.querySelector('[data-role=save-nickname]').textContent==='Save');
  } else {
   assert.equal(await page.locator('[data-role=save-nickname]').textContent(),'Enregistrer');
   assert.equal(await page.locator('[data-role=nickname-status]').textContent(),outcome==='rejected'?"Échec de l'enregistrement.":"Une erreur s'est produite.");
   if(outcome==='rejected')assert.equal(await page.locator('[data-role=status]').textContent(),'Mentés… server detail <x> $&');
   assert.deepEqual(await page.evaluate(()=>window.nicknameEvents),[]);
  }
  assert.equal(f.posts.length,1);await finish(f);
 }
});
test('restore validates locally, preserves credentials and resets labels across rejection/network/success navigation', async()=>{
 for(const outcome of ['rejected','network','success']) {
  const held=heldPost(),f=await fixture('de',{core:true,onPost:held.handler});const {page}=f;await active(f);
  await page.locator('[data-role=restore-pseudo]').fill('bad');await page.locator('[data-role=restore-submit]').click();await settle(page);
  assert.equal(await page.locator('[data-role=restore-status]').textContent(),'Gib eine gültige Kennung ein.');assert.equal(f.posts.length,0);
  await page.locator('[data-role=restore-pseudo]').fill('ABCDEF123456');await page.locator('[data-role=restore-recovery]').fill('bad');
  await page.locator('[data-role=restore-submit]').click();await settle(page);
  assert.equal(await page.locator('[data-role=restore-status]').textContent(),'Ungültiges Format des Anmeldecodes.');assert.equal(f.posts.length,0);
  await page.locator('[data-role=restore-recovery]').fill('ABCD1234EFGH');await page.locator('[data-role=restore-submit]').click();await held.pending;await settle(page);
  assert.equal(await page.locator('[data-role=restore-submit]').isDisabled(),true);
  await language(page,'fr');assert.equal(await page.locator('[data-role=restore-submit]').textContent(),'Connexion…');
  assert.deepEqual(f.posts,[{path:'/wp-json/impact/v1/identity/restore',body:{pseudo_id:'abcdef123456',recovery_code:'ABCD1234EFGH'}}]);
  held.release(outcome==='network'?{abort:true}:outcome==='rejected'?{status:403,body:{message:'Belépés — server detail <x>'}}:{body:{}});
  await page.waitForFunction(()=>!document.querySelector('[data-role=restore-submit]').disabled);await settle(page);
  assert.equal(await page.locator('[data-role=restore-submit]').textContent(),'Se connecter');
  if(outcome==='success') {
   assert.equal(await page.locator('[data-role=restore-status]').textContent(),'Connexion réussie. Retour en cours…');
   await page.waitForURL(url=>url.searchParams.get('impactshop_profile_return_complete')==='1');assert.deepEqual(f.navigations,['https://app.sharity.hu/profil/?bridge_target=restore&impactshop_profile_return_complete=1']);
  } else {
   assert.equal(await page.locator('[data-role=restore-status]').textContent(),outcome==='network'?'Erreur de connexion.':'Belépés — server detail <x>');
   assert.equal(f.navigations.length,0);
  }
  assert.equal(f.posts.length,1);await finish(f);
 }
});
test('code reveal uses one same-window navigation; failed requests restore current language',async()=>{
 for(const outcome of ['success','rejected','network']) {
  const held=heldPost(),f=await fixture('de',{core:true,onPost:held.handler});const {page}=f;await active(f);
  const original=page.url();let confirmation='';page.once('dialog',async d=>{confirmation=d.message();await d.accept();});
  await page.locator('[data-role=generate-code]').click();await held.pending;await settle(page);
  assert.match(confirmation,/bisheriger Code wird ungültig/);
  assert.equal(await page.locator('[data-role=generate-code]').textContent(),'Code wird erstellt…');
  await language(page,'fr');assert.equal(await page.locator('[data-role=generate-code]').textContent(),'Génération du code…');
  const reveal='https://app.sharity.hu/mock-reveal?code=fixture';
  held.release(outcome==='network'?{abort:true}:outcome==='rejected'?{status:422,body:{}}:{body:{reveal_url:reveal}});
  if(outcome==='success'){await page.waitForURL(reveal);assert.deepEqual(f.navigations,[reveal]);assert.equal(f.context.pages().length,1);}
  else {
   await page.waitForFunction(()=>!document.querySelector('[data-role=generate-code]').disabled);await settle(page);
   assert.equal(await page.locator('[data-role=generate-code]').textContent(),'Créer un nouveau code de connexion');
   assert.equal(await page.locator('[data-role=status]').textContent(),'Échec de la création du code de connexion.');
  }
  assert.deepEqual(f.posts,[{path:'/wp-json/impact/v1/identity/code/generate',body:{code_save_contract:2,confirm_generation:true,profile_return_url:original}}]);await finish(f);
 }
});
test('shared status retains existing local messages across languages without partially translating server text',async()=>{
 const f=await fixture('en',{legacy:'en',core:true});const {page}=f;await active(f);
 assert.equal(await page.locator('[data-role=status]').textContent(),'Identity loaded.');
 for(const [lang,loaded,copy] of [['de','ID geladen.','Kopieren fehlgeschlagen.'],['fr','Identifiant chargé.','Échec de la copie.'],['hu','Azonosító betöltve.','Másolás sikertelen.'],['en','Identity loaded.','Copy failed.']]) {
  await page.locator('[data-role=status]').evaluate(el=>el.textContent='Azonosító betöltve.');await language(page,lang);
  assert.equal(await page.locator('[data-role=status]').textContent(),loaded);
  await page.locator('[data-role=status]').evaluate(el=>el.textContent='Másolás sikertelen.');await settle(page);
  assert.equal(await page.locator('[data-role=status]').textContent(),copy);
 }
 for(const value of ['Azonosító betöltve. custom server detail','Mentés… <x> $&','unknown\nline']) {
  await page.locator('[data-role=status]').evaluate((el,value)=>el.textContent=value,value);await language(page,'fr');await language(page,'en');
  assert.equal(await page.locator('[data-role=status]').textContent(),value);
 }
 assert.equal(f.posts.length,0);await finish(f);
});

const historyTypes=['purchase','first_purchase','shop_discovery','video_ad','video_sponsor','share','feedback','referral','referral_bonus','profile_complete','nickname','login_daily','streak_bonus','wallet_download','tombola','bonus','admin_adjustment','vacation_start','vacation_end','decay','unknown'];
test('native history rows keep exact dates/numbers/order, NGO names and handlers across languages and fresh renders',async()=>{
 const name='  Profilod! Mentés <img src=x> $& $1 {0}  ';
 const transactions=historyTypes.map((type,i)=>({type,points:i===3?5:i===0?-1234.5:7,created_at:i===1?null:'2026-10-31 12:00:00'}));
 const apiData={
  '/wp-json/sharity/v1/pseudo/points/history':{transactions},
  '/wp-json/sharity/v1/pseudo/last-ngo':{slug:'fixture-ngo'},
  '/wp-json/impact/v1/ngo-card/fixture-ngo':{name}
 };
 const f=await fixture('hu',{core:true,apiData,onPost:async route=>{
  apiData['/wp-json/sharity/v1/pseudo/last-ngo']={};
  return route.fulfill({contentType:'application/json',body:'{}'});
 }});const {page}=f;await active(f);
 await page.waitForFunction(()=>document.querySelector('[data-role=last-ngo] button')&&document.querySelectorAll('[data-role=points-history] li').length===21);
 const rows=page.locator('[data-role=points-history] li');const native=await rows.evaluateAll(rows=>rows.map(r=>[r.children[0].textContent,r.children[1].textContent]));
 await page.evaluate(()=>{window.ngoButton=document.querySelector('[data-role=last-ngo] button');window.historyNodes=Array.from(document.querySelectorAll('[data-role=points-history] li'));});
 for(const lang of ['en','de','fr','hu','fr']) {
  await language(page,lang);
  const current=await rows.evaluateAll(rows=>rows.map(r=>[r.children[0].textContent,r.children[1].textContent]));
  for(let i=0;i<current.length;i++) {
   assert.equal(current[i][0].split(' · ')[1],native[i][0].split(' · ')[1]);
   assert.ok(current[i][1].includes(native[i][1].replace(/ pont$/,'')));
   if(lang!=='hu') assert.notEqual(current[i][0],native[i][0]);
  }
  const prefix={hu:'Legutóbb ezt a szervezetet támogattad: ',en:'Most recently you supported: ',de:'Zuletzt hast du diese Organisation unterstützt: ',fr:'Dernièrement, vous avez soutenu : '}[lang];
  assert.equal(await page.locator('[data-role=last-ngo] > span').textContent(),prefix+name);
  assert.equal(await page.locator('[data-role=last-ngo] img').count(),0);
  assert.equal(await page.evaluate(()=>window.ngoButton===document.querySelector('[data-role=last-ngo] button')&&window.historyNodes.every((n,i)=>n===document.querySelectorAll('[data-role=points-history] li')[i])),true);
 }
 assert.equal(f.posts.length,0);
 // Re-render from changed native data, no saved translation can revive old rows.
 apiData['/wp-json/sharity/v1/pseudo/points/history']={transactions:[{type:'bonus',points:-9,created_at:null}]};
 await page.locator('[data-role=refresh-profile]').click();
 await page.waitForFunction(()=>document.querySelectorAll('[data-role=points-history] li').length===1);await settle(page);
 assert.equal(await rows.locator('span').first().textContent(),'Bonus');assert.equal(await rows.locator('span').last().textContent(),'Points : -9');
 await page.locator('[data-role=last-ngo] button').click();
 await page.waitForFunction(()=>document.querySelector('[data-role=last-ngo]').textContent==='Aucun choix d’ONG enregistré pour le moment.');
 assert.deepEqual(f.posts,[{path:'/wp-json/sharity/v1/pseudo/last-ngo',body:{slug:''}}]);await finish(f);
});
test('bounded list targets preserve unknown markup and exact formatted data; late selector protects NGO name under legacy English',async()=>{
 const f=await fixture('en',{legacy:'en',noBar:true,core:true,apiData:{
  '/wp-json/sharity/v1/pseudo/last-ngo':{slug:'fixture'},'/wp-json/impact/v1/ngo-card/fixture':{name:'Mentés Profilod $& {0}'}
 }});const {page}=f;await active(f);
 await page.waitForFunction(()=>document.querySelector('[data-role=last-ngo] button'));await settle(page);
 assert.equal(await page.locator('[data-role=last-ngo] > span').textContent(),'Most recently you supported: Mentés Profilod $& {0}');
 await page.evaluate(()=>{
  const list=document.querySelector('[data-role=points-history]');list.innerHTML='<li><span>Első vásárlás · opaque {0} $&</span><span>-1\u202f234,50 pont</span></li><li><span>Unknown label</span><span>USD 12</span></li><li><span><strong>Bónusz</strong></span><span>5 pont</span></li>';
  const other=document.createElement('ul');other.id='outside-history';other.innerHTML='<li><span>Bónusz · opaque</span><span>5 pont</span></li>';document.querySelector('.impactshop-profile-shell').appendChild(other);
  const bar=document.createElement('nav');bar.className='sharity-action-bar';bar.setAttribute('data-sharity-preferences-managed','');bar.lang='fr';document.body.appendChild(bar);
 });await settle(page);
 for(const lang of ['de','fr','hu','en']) {
  await language(page,lang);
  assert.ok((await page.locator('[data-role=points-history] li:first-child span:first-child').textContent()).endsWith(' · opaque {0} $&'));
  assert.ok((await page.locator('[data-role=points-history] li:first-child span:last-child').textContent()).includes('-1\u202f234,50'));
  assert.equal(await page.locator('[data-role=points-history] li:nth-child(2)').textContent(),'Unknown labelUSD 12');
  // Unrecognized nested markup is outside the new pass; no nodes are flattened.
  assert.equal(await page.locator('[data-role=points-history] strong').count(),1);
 }
 await finish(f);
});

function installPushMock(options={}) {
 const calls=window.pushCalls={permission:0,subscribe:0,unsubscribe:0};
 const subscription={toJSON:()=>({endpoint:'https://push.invalid/fixture',keys:{p256dh:'fixture',auth:'fixture'}}),unsubscribe:async()=>{calls.unsubscribe++;current=null;return true;}};
 let current=options.subscribed?subscription:null;
 Object.defineProperty(window,'Notification',{configurable:true,value:{permission:options.denied?'denied':'default',requestPermission:async()=>{calls.permission++;return 'granted';}}});
 Object.defineProperty(window,'PushManager',{configurable:true,value:function(){}});
 Object.defineProperty(navigator,'serviceWorker',{configurable:true,value:{get ready(){return options.noWorker?Promise.reject(new Error('fixture')):Promise.resolve({pushManager:{getSubscription:async()=>current,subscribe:async()=>{calls.subscribe++;current=subscription;return subscription;}}});}}});
}
const pushData={'/wp-json/impact/v1/push/public-key':{enabled:true,publicKey:'AQID'}};
const pushInit=options=>({content:`(${installPushMock.toString()})(${JSON.stringify(options)})`});
test('native push toggle remains permission-gated; language changes cause no permission or subscription writes',async()=>{
 const f=await fixture('de',{core:true,apiData:pushData,beforeLoad:pushInit({})});const {page}=f;await active(f);
 await page.waitForFunction(()=>document.querySelector('[data-role=push-toggle]').dataset.bound==='1');await settle(page);
 for(const lang of ['fr','hu','en','de']) await language(page,lang);
 assert.equal(await page.locator('[data-role=push-toggle]').textContent(),'Benachrichtigungen aktivieren');
 assert.deepEqual(await page.evaluate(()=>window.pushCalls),{permission:0,subscribe:0,unsubscribe:0});assert.equal(f.posts.length,0);
 await page.locator('[data-role=push-toggle]').click();
 await page.waitForFunction(()=>window.pushCalls.subscribe===1&&!document.querySelector('[data-role=push-toggle]').disabled);await settle(page);
 assert.equal(await page.locator('[data-role=push-toggle]').textContent(),'Benachrichtigungen deaktivieren');
 assert.equal(await page.locator('[data-role=push-status]').textContent(),'Push-Benachrichtigungen sind aktiv.');
 await language(page,'fr');await page.locator('[data-role=push-toggle]').click();
 await page.waitForFunction(()=>window.pushCalls.unsubscribe===1&&!document.querySelector('[data-role=push-toggle]').disabled);await settle(page);
 assert.equal(await page.locator('[data-role=push-toggle]').textContent(),'Activer les notifications');
 assert.deepEqual(await page.evaluate(()=>window.pushCalls),{permission:1,subscribe:1,unsubscribe:1});
 assert.deepEqual(f.posts.map(p=>p.path),['/wp-json/impact/v1/push/subscribe','/wp-json/impact/v1/push/unsubscribe']);
 assert.deepEqual(f.posts[0].body.subscription,f.posts[1].body.subscription);await finish(f);
});
test('push denied and absent worker states remain blocked/localized; inactive identity hides push without permission calls',async()=>{
 for(const state of ['denied','noWorker','inactive']) {
  const f=await fixture('fr',{core:true,apiData:pushData,beforeLoad:pushInit({denied:state==='denied',noWorker:state==='noWorker'}),profileData:state==='inactive'?{identity_state:'legacy_read_only'}:{}});const {page}=f;await active(f);
  if(state==='denied') {
   await page.waitForFunction(()=>document.querySelector('[data-role=push-status]').textContent==='Les notifications sont bloquées dans votre navigateur.');
   assert.equal(await page.locator('[data-role=push-toggle]').isDisabled(),true);
  } else if(state==='noWorker') await page.waitForFunction(()=>document.querySelector('[data-role=push-status]').textContent==='Les notifications push sont indisponibles.');
  else assert.equal(await page.locator('[data-role=push-section]').evaluate(el=>el.hidden),true);
  await language(page,'de');assert.equal(f.posts.length,0);assert.deepEqual(await page.evaluate(()=>window.pushCalls),{permission:0,subscribe:0,unsubscribe:0});await finish(f);
 }
});
test('populated push/history/NGO copy fits existing component layout at three viewport sizes',async()=>{
 for(const viewport of [{width:375,height:850},{width:667,height:375},{width:1280,height:900}]) {
  const f=await fixture('de',{core:true,viewport,beforeLoad:pushInit({subscribed:true}),apiData:{...pushData,
   '/wp-json/sharity/v1/pseudo/points/history':{transactions:[{type:'profile_complete',points:1234,created_at:'2026-10-31 12:00:00'},{type:'video_ad',points:5,created_at:'2026-10-30 12:00:00'}]},
   '/wp-json/sharity/v1/pseudo/last-ngo':{slug:'fixture'},'/wp-json/impact/v1/ngo-card/fixture':{name:'Fondation de soutien aux enfants'}
  }});const {page}=f;await active(f);
  await page.waitForFunction(()=>document.querySelector('[data-role=push-toggle]').dataset.bound==='1'&&document.querySelector('[data-role=last-ngo] button'));await settle(page);
  const overflow=await page.locator('.impactshop-profile-shell').evaluate(root=>Array.from(root.querySelectorAll('[data-sharity-dynamic-copy]')).filter(el=>el.clientWidth&&el.scrollWidth>el.clientWidth+1).map(el=>el.textContent));assert.deepEqual(overflow,[]);
  if(process.env.SHARITY_INTL_SCREENSHOTS==='1'){mkdirSync(resolve(root,'output/playwright'),{recursive:true});await page.screenshot({path:resolve(root,`output/playwright/p2r-${viewport.width}.png`),fullPage:true});}
  await finish(f);
 }
});

const badgeSeeds=Array.from(readFileSync(resolve(root,'wp-content/mu-plugins/impact-gamification.php'),'utf8').matchAll(/\['([^']+)', '([^']+)', '([^']+)', '([^']+)', '([^']+)'\]/g),m=>({badge_key:m[1],category:m[2],name_hu:m[3],description_hu:m[4],default_tier:m[5]}));
const badgeApi={
 '/wp-json/impact/v1/badges/available':{badges:badgeSeeds},
 '/wp-json/impact/v1/badges/user':{badges:badgeSeeds.map((s,i)=>({badge_key:s.badge_key,tier:s.default_tier,awarded_at:new Date(Date.UTC(2026,0,i+1)).toISOString()}))}
};
test('native badge grouping/order preserved; all 41 seed names and tooltips switch together without replacing nodes',async()=>{
 assert.equal(badgeSeeds.length,41);
 const baseline=await fixture('hu',{core:true,noBar:true,apiData:badgeApi});await active(baseline);
 await baseline.page.waitForFunction(()=>document.querySelector('[data-role=legacy-badges]').children.length>0);
 const expectedKeys=await baseline.page.locator('[data-role=legacy-badges] > span').evaluateAll(nodes=>nodes.map(n=>n.dataset.badgeKey));await finish(baseline);
 const f=await fixture('de',{core:true,legacy:'en',apiData:badgeApi});const {page}=f;await active(f);
 await page.waitForFunction(()=>document.querySelector('[data-role=legacy-badges] > span')?.lang==='de');
 assert.deepEqual(await page.locator('[data-role=legacy-badges] > span').evaluateAll(nodes=>nodes.map(n=>n.dataset.badgeKey)),expectedKeys);
 assert.equal(await page.locator('[data-badge-key=views_10000] > span:last-child').textContent(),'10000 Videos');
 assert.equal(await page.locator('[data-badge-key=views_10000]').getAttribute('title'),'10000 angesehene Videos.');
 if(process.env.SHARITY_INTL_SCREENSHOTS==='1') await page.screenshot({path:resolve(root,'tmp/p2s-badges-de.png'),fullPage:true});
 // Native grouping deliberately hides lesser tiers; exercise each catalog pair via equivalent leaf DOM.
 await page.evaluate(seeds=>{
  const list=document.querySelector('[data-role=legacy-badges]');list.replaceChildren();window.badgeNodes=[];window.badgeClicks=0;
  for(const seed of seeds){const b=document.createElement('span');b.className='impactshop-legacy-badge';b.dataset.badgeKey=seed.badge_key;b.title=seed.description_hu;
   const icon=document.createElement('span');icon.className='impactshop-legacy-badge-icon';icon.textContent='★';const name=document.createElement('span');name.textContent=seed.name_hu;b.append(icon,name);list.appendChild(b);window.badgeNodes.push([b,name,name.firstChild]);name.addEventListener('click',()=>window.badgeClicks++);
  }
 },badgeSeeds);
 for(const lang of ['fr','en','hu','de','fr','hu']){
  await language(page,lang);
  const actual=await page.locator('[data-role=legacy-badges] > span').evaluateAll(nodes=>nodes.map(b=>({key:b.dataset.badgeKey,name:b.lastElementChild.textContent,title:b.title,lang:b.lang})));
  actual.forEach((b,i)=>{assert.equal(b.key,badgeSeeds[i].badge_key);assert.equal(b.lang,lang);assert.ok(b.name&&b.title);if(lang==='hu'){assert.equal(b.name,badgeSeeds[i].name_hu);assert.equal(b.title,badgeSeeds[i].description_hu);}else{assert.notEqual(b.name,badgeSeeds[i].name_hu);assert.notEqual(b.title,badgeSeeds[i].description_hu);}});
 }
 assert.equal(await page.evaluate(()=>window.badgeNodes.every(([b,n,t])=>b.lastElementChild===n&&n.firstChild===t)),true);
 await page.locator('[data-badge-key=views_1] > span:last-child').click();assert.equal(await page.evaluate(()=>window.badgeClicks),1);
 // Native refresh replaces leaves, then the same language applies again.
 await language(page,'fr');await page.locator('[data-role=refresh-profile]').click();
 await page.waitForFunction(count=>document.querySelector('[data-role=legacy-badges]').children.length===count&&document.querySelector('[data-badge-key=views_10000]')?.title==='10000 vidéos visionnées.',expectedKeys.length);
 assert.deepEqual(await page.locator('[data-role=legacy-badges] > span').evaluateAll(nodes=>nodes.map(n=>n.dataset.badgeKey)),expectedKeys);
 await finish(f);
});
test('badge custom/edited/missing metadata remains verbatim with legacy EN and late selector; stale lang restored',async()=>{
 const f=await fixture('hu',{core:true,legacy:'en',noBar:true,apiData:badgeApi});const {page}=f;await active(f);
 await page.waitForFunction(()=>document.querySelector('[data-role=legacy-badges]').children.length>0);
 await page.evaluate(()=>{
  const list=document.querySelector('[data-role=legacy-badges]');list.replaceChildren();
  const cases=[['custom','Profilod','Mentés'],['views_10','10 videó','Mentés'],['views_100','Profilod','100 megtekintett videó.'],['views_1','Első videó','Első videó'],['__proto__','Profilod','Mentés'],['first_vote','Első szavazat','Első szavazat leadása.']];
  cases.forEach(([key,name,title])=>{const b=document.createElement('span');b.className='impactshop-legacy-badge';b.dataset.badgeKey=key;b.title=title;b.lang='hu';const icon=document.createElement('span'),label=document.createElement('span');label.textContent=name;b.append(icon,label);list.append(b);});
 });await settle(page);
 const snapshot=await page.locator('[data-role=legacy-badges] > span').evaluateAll(nodes=>nodes.slice(0,5).map(b=>b.outerHTML));
 assert.equal(await page.locator('[data-badge-key=custom]').textContent(),'Profilod');
 await page.evaluate(()=>{const nav=document.createElement('nav');nav.className='sharity-action-bar';nav.setAttribute('data-sharity-preferences-managed','');nav.lang='fr';document.body.append(nav);});
 for(const lang of ['fr','de','en','hu']){await language(page,lang);assert.deepEqual(await page.locator('[data-role=legacy-badges] > span').evaluateAll(nodes=>nodes.slice(0,5).map(b=>b.outerHTML)),snapshot);}
 await language(page,'fr');assert.equal(await page.locator('[data-badge-key=first_vote]').getAttribute('lang'),'fr');
 await page.locator('[data-badge-key=first_vote]').evaluate(b=>{b.lastElementChild.textContent='Profilod';b.title='Mentés';});await settle(page);
 assert.equal(await page.locator('[data-badge-key=first_vote]').getAttribute('lang'),'hu');
 await language(page,'en');assert.equal(await page.locator('[data-badge-key=first_vote]').textContent(),'Profilod');assert.equal(await page.locator('[data-badge-key=first_vote]').getAttribute('title'),'Mentés');
 // A later rich/custom label must also discard the old translation language without flattening it.
 await page.locator('[data-badge-key=first_vote]').evaluate(b=>{b.lastElementChild.textContent='Első szavazat';b.title='Első szavazat leadása.';});await language(page,'fr');
 await page.locator('[data-badge-key=first_vote] > span:last-child').evaluate(n=>{const strong=document.createElement('strong');strong.textContent='Profilod';n.replaceChildren(strong);});await settle(page);
 assert.equal(await page.locator('[data-badge-key=first_vote]').getAttribute('lang'),'hu');assert.equal(await page.locator('[data-badge-key=first_vote] strong').textContent(),'Profilod');
 await finish(f);
});
test('native account acknowledgment keeps one button, original message and exact read POST; broadcast sends none',async()=>{
 for(const type of ['targeted','broadcast']){
  const f=await fixture('de',{core:true,legacy:'en',apiData:{'/wp-json/impact/v1/identity/messages':{messages:[{id:123,type,content:'Profilod · Rendben · 1 234,56 Ft · $&'}]}}});const {page}=f;await active(f);
  await page.waitForFunction(()=>document.querySelector('[data-role=account-message] > button')?.textContent==='OK');
  await page.evaluate(()=>{window.originalAck=document.querySelector('[data-role=account-message] > button');window.originalMessage=window.originalAck.previousElementSibling;});
  for(const lang of ['fr','hu','en','de']){await language(page,lang);assert.equal(await page.locator('[data-role=account-message] > button').count(),1);assert.equal(await page.locator('[data-role=account-message] > button').textContent(),lang==='hu'?'Rendben':'OK');assert.equal(await page.locator('[data-role=account-message] > span').textContent(),'Profilod · Rendben · 1 234,56 Ft · $&');}
  assert.equal(await page.evaluate(()=>window.originalAck===document.querySelector('[data-role=account-message] > button')&&window.originalMessage===window.originalAck.previousElementSibling),true);
  assert.equal(f.posts.length,0);await page.locator('[data-role=account-message] > button').click();await settle(page);
  assert.equal(await page.locator('[data-role=account-message]').evaluate(el=>el.hidden),true);
  if(type==='targeted')assert.deepEqual(f.posts,[{path:'/wp-json/impact/v1/identity/message-read',body:{message_id:123}}]);else assert.deepEqual(f.posts,[]);
  await page.reload();await page.waitForFunction(()=>document.querySelector('[data-role=account-message] > button')?.textContent==='OK');
  await finish(f);
 }
});
