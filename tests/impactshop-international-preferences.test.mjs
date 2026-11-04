// Actual PHP output and both existing overlays in Chrome. Every request is local/blocked.
// USER_CARD_TEST_DEPENDENCIES_ROOT=<existing package with playwright> node --test <file>
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
const key = 'sharity_ui_preferences_v1';
const base = 'https://app.sharity.hu/profil/';
const destinations = {
 'shopping-assistant': 'https://sharity.hu/vasarlasi-seged',
 offerwall: 'https://sharity.hu/offerwall', 'ngo-card': 'https://sharity.hu/ngo-kartyak',
 commitments: 'https://sharity.hu/vallalasok', community: 'https://sharity.hu/hatas-korok',
 'tip-game': 'https://factlens.eu/factlens/vb-prod/',
};
async function assertDock(page, language, currency) {
 for (const [id, href] of Object.entries(destinations))
  assert.equal(await page.locator(`[data-bar="${id}"]`).getAttribute('href'), `${href}?ui_lang=${language}&ui_currency=${currency}`);
 assert.equal(await page.locator('[data-bar=account]').getAttribute('href'), base+'#impactshop-account-top');
}
const overlay = readFileSync(resolve(root, 'wp-content/mu-plugins/impactshop-identity-panel-intl-overlay.js'), 'utf8');
const adsOverlay = readFileSync(resolve(root, 'wp-content/mu-plugins/impactshop-ads-watch-intl-overlay.js'), 'utf8');
function render(url, file) { return execFileSync('php', [resolve(root, 'tests/fixtures/impactshop-action-bar-render.php'), url, ...(file ? [file] : [])], { encoding: 'utf8' }); }
let browser;
before(async () => { browser = await chromium.launch({ channel: 'chrome', headless: true, proxy: { server: 'http://127.0.0.1:9' } }); });
after(async () => { await browser?.close(); });
async function fixture(url = base, { stored, cookies = [], blocked = false, allowOutbound = false, viewport = { width: 375, height: 850 } } = {}) {
 const context = await browser.newContext({ viewport });
 await context.addCookies(cookies.map(([name,value]) => ({ name, value, url: base })));
 await context.addInitScript(({ stored, blocked, key }) => {
  if (stored !== undefined && !sessionStorage.getItem('seeded')) { localStorage.setItem(key, stored); sessionStorage.setItem('seeded','1'); }
  localStorage.setItem('unrelated', 'preserve');
  history.replaceState({ fixture: 'unchanged' }, '', location.href);
  if (blocked) {
   Storage.prototype.getItem = Storage.prototype.setItem = function () { throw new Error('blocked'); };
   Object.defineProperty(document, 'cookie', { get() { throw new Error('blocked'); }, set() { throw new Error('blocked'); } });
   history.replaceState = function () { throw new Error('blocked'); };
  }
 }, { stored, blocked, key });
 const page = await context.newPage(); const errors = [], escaped = [], requests = [], outgoing = [];
 page.on('pageerror', e => errors.push(e.message));
 await context.route('**/*', async route => {
  requests.push(route.request().method());
  const target = new URL(route.request().url());
  if (allowOutbound && route.request().isNavigationRequest() && Object.values(destinations).includes(target.origin+target.pathname)) {
   outgoing.push(route.request().url());
   return route.fulfill({contentType:'text/html',body:'<!doctype html><title>Local receiver fixture</title>'});
  }
  const asset = new URL(route.request().url()).pathname;
  if (asset === '/fixture/identity-overlay.js' || asset === '/fixture/ads-overlay.js') return route.fulfill({contentType:'application/javascript; charset=utf-8',body:asset.includes('identity')?overlay:adsOverlay});
  if (route.request().isNavigationRequest() && new URL(route.request().url()).host === 'app.sharity.hu') {
   const currentUrl = route.request().url();
   const body = render(currentUrl);
   return route.fulfill({ contentType:'text/html; charset=utf-8', body:`<!doctype html><html lang="hu"><body><main><div class="impactshop-identity-panel"><p data-role="greeting">Profil</p><span id="money">1 234 Ft</span></div><div id="impactshop-ads-watch"><span class="pool-label">Adomány</span></div></main>${body}<script>window.impactshopAdsWatch={lang:'en',toggles:{currentFeatureEnabled:true},i18n:{actionBarLabel:'OLD BAR',actionBarProfile:'OLD PROFILE',donationPoolLabel:'Donation'}};</script><script src="/fixture/identity-overlay.js"></script><script src="/fixture/ads-overlay.js"></script></body></html>` });
  }
  escaped.push(route.request().url()); return route.abort();
 });
 await page.goto(url);
 return { page, context, errors, escaped, requests, outgoing };
}
const preference = page => page.evaluate(key => JSON.parse(localStorage.getItem(key)), key);
async function finish(f) { assert.deepEqual(f.errors, []); assert.deepEqual(f.escaped, []); assert.ok(f.requests.every(method=>method==='GET'), 'no business write request'); await f.context.close(); }

test('all 16 explicit independent choices round-trip through real PHP and both overlays', async () => {
 for (const language of ['hu','en','de','fr']) for (const displayCurrency of ['HUF','EUR','USD','GBP']) {
  const f = await fixture(base + `?lang=en&ui_lang=${language}&ui_currency=${displayCurrency}&return=a%20b%2f~&return=a+b#account`);
  assert.deepEqual(await preference(f.page), { language, displayCurrency });
  await assertDock(f.page, language, displayCurrency);
  assert.equal(await f.page.locator('.sharity-action-bar').getAttribute('lang'),language);
  assert.equal(await f.page.locator('.sharity-action-bar').getAttribute('aria-label') === 'OLD BAR',false);
  assert.equal(await f.page.locator('[data-bar=account] > span:last-child').textContent(), language==='en'?'Profile':'Profil');
  assert.equal(await f.page.locator('[data-role=greeting]').textContent(), 'Profile'); // Legacy page translation still works.
  assert.equal(await f.page.locator('#money').textContent(), '1 234 Ft');
  assert.ok(f.page.url().endsWith('?lang=en&return=a%20b%2f~&return=a+b#account'));
  assert.deepEqual(await f.page.evaluate(()=>history.state), {fixture:'unchanged'});
  assert.equal(await f.page.evaluate(()=>localStorage.getItem('unrelated')), 'preserve');
  assert.equal(await f.page.locator('[data-bar=messages-upcoming]').isDisabled(),true);
  await finish(f);
 }
});
test('fieldwise cookie/storage/legacy/query precedence, duplicate and encoded names', async () => {
 const cases = [
  ['?ui_lang=de&ui_lang=fr&ui_currency=GBP','{"language":"fr","displayCurrency":"USD"}',[['sharity_ui_language','en']],['en','GBP']],
  ['?%75i_lang=de&ui_currency=%47BP','{}',[],['de','GBP']],
  ['?ui_lang=de&%75i_lang=fr&ui_currency=gbp','{"language":"fr","displayCurrency":"USD"}',[['sharity_display_currency','EUR']],['fr','EUR']],
  ['?lang=en&country=us','{bad',[],['en','HUF']],
  ['?lang=en','{"language":"hu","displayCurrency":"GBP"}',[],['hu','GBP']],
  ['?lang=en&lang=hu','null',[],['hu','HUF']],
  ['', '[]', [['sharity_ui_language','%E0']], ['hu','HUF']],
 ];
 for(const [query,stored,cookies,expected] of cases) {
  const f=await fixture(base+query,{stored,cookies});
  assert.deepEqual(Object.values(await preference(f.page)),expected);
  await finish(f);
 }
});
test('DE/FR and currency update locally, persist reload; country stays unchanged and six outbound dock hrefs follow the selection', async () => {
 const f=await fixture(base+'?lang=en&country=us&ui_lang=de&ui_currency=EUR&bridge_target=restore&return=keep');
 const {page}=f;
 await assertDock(page,'de','EUR');
 await page.locator('#sharity-slc-btn').click();
 const country=await page.locator('[data-slc-country]').evaluateAll(as=>as.map(a=>a.getAttribute('href')));
 assert.ok(country.every(x=>!x.includes('ui_lang')&&!x.includes('ui_currency')));
 await page.locator('[data-slc-language=fr]').click();
 await page.locator('#sharity-slc-currency').selectOption('GBP');
 assert.deepEqual(await preference(page), {language:'fr',displayCurrency:'GBP'});
 await assertDock(page,'fr','GBP');
 assert.deepEqual(await page.locator('[data-slc-country]').evaluateAll(as=>as.map(a=>a.getAttribute('href'))),country);
 await page.reload(); assert.deepEqual(await preference(page),{language:'fr',displayCurrency:'GBP'});
 await assertDock(page,'fr','GBP');
 await finish(f);
});
test('HU/EN native page-switch links still navigate without resurrecting inbound preferences', async () => {
 const f=await fixture(base+'?ui_lang=de&ui_currency=GBP&country=us&return=keep');
 for(const language of ['en','hu']) {
  await f.page.locator('#sharity-slc-btn').click();
  await Promise.all([f.page.waitForURL(url=>language==='en'?url.searchParams.get('lang')==='en':!url.searchParams.has('lang')),f.page.locator(`[data-slc-language=${language}]`).click()]);
  assert.deepEqual(await preference(f.page),{language,displayCurrency:'GBP'});
  assert.equal(new URL(f.page.url()).searchParams.get('country'),'us');
  assert.equal(new URL(f.page.url()).searchParams.get('return'),'keep');
 }
 await finish(f);
});
test('blocked browser persistence/history does not break local choices or modal', async () => {
 const f=await fixture(base+'?ui_lang=de&ui_currency=EUR',{blocked:true});
 await f.page.locator('#sharity-slc-btn').click();
 await f.page.locator('[data-slc-language=fr]').click();
 await f.page.locator('#sharity-slc-currency').selectOption('GBP');
 assert.equal(await f.page.locator('#sharity-slc-btn').getAttribute('lang'),'fr');
 assert.equal(await f.page.locator('#sharity-slc-currency').inputValue(),'GBP');
 await assertDock(f.page,'fr','GBP');
 await f.page.keyboard.press('Escape');
 assert.equal(await f.page.locator('#sharity-slc-sheet').isVisible(),false);
 await finish(f);
});
test('modal has keyboard focus containment and fits mobile, landscape and desktop', async () => {
 for(const viewport of [{width:375,height:850},{width:667,height:375},{width:1280,height:900}]) {
  const f=await fixture(base+'?ui_lang=de&ui_currency=EUR',{viewport}); const {page}=f;
  assert.equal(await page.locator('#sharity-slc-sheet').isVisible(),false);
  await page.locator('#sharity-slc-btn').click();
  assert.equal(await page.locator('[data-slc-close]').evaluate(el=>el===document.activeElement),true);
  await page.keyboard.press('Shift+Tab');
  assert.equal(await page.locator('[data-slc-country=us]').evaluate(el=>el===document.activeElement),true);
  await page.keyboard.press('Tab');
  assert.equal(await page.locator('[data-slc-close]').evaluate(el=>el===document.activeElement),true);
  const geometry=await page.locator('#sharity-slc-sheet').evaluate(el=>({overflow:el.scrollWidth>el.clientWidth,top:el.getBoundingClientRect().top,selectHeight:el.querySelector('select').getBoundingClientRect().height}));
  assert.equal(geometry.overflow,false);assert.ok(geometry.top>=0);assert.ok(geometry.selectHeight>=44);
  if(process.env.SHARITY_INTL_SCREENSHOTS==='1') {mkdirSync(resolve(root,'output/playwright'),{recursive:true});await page.screenshot({path:resolve(root,`output/playwright/p2n-${viewport.width}.png`)});}
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('#sharity-slc-btn').evaluate(el=>el===document.activeElement),true);
  await finish(f);
 }
});
test('footer route exclusions remain in actual PHP renderer', () => {
 for(const url of ['https://adomany.sharity.hu/profil/','https://evil.invalid/profil/','https://app.sharity.hu/wp-login.php','https://app.sharity.hu/wp-json/impact/v1']) assert.equal(render(url),'');
});


test('outgoing handoff preserves raw attribution and fragment while replacing only own encoded/duplicate fields', async () => {
 const f=await fixture(base+'?ui_lang=de&ui_currency=EUR');
 const raw='return=a%20b%2f~&return=a+b&sat1=fixture%2B%2F%3D&campaign=x&&odd=%ZZ';
 await f.page.evaluate(({destinations,raw})=>{
  Object.entries(destinations).forEach(([id,url])=>document.querySelector(`[data-bar="${id}"]`).setAttribute('href',url+'?'+raw+'&ui_lang=hu&%75i_lang=en&ui_currency=USD&%75i_currency=GBP#shared_url=fixture%2F~'));
 },{destinations,raw});
 await f.page.locator('#sharity-slc-btn').click();
 await f.page.locator('[data-slc-language=fr]').click();
 for(const currency of ['GBP','HUF','GBP']) {
  await f.page.locator('#sharity-slc-currency').selectOption(currency);
  for(const [id,url] of Object.entries(destinations))
   assert.equal(await f.page.locator(`[data-bar="${id}"]`).getAttribute('href'),url+'?'+raw+`&ui_lang=fr&ui_currency=${currency}#shared_url=fixture%2F~`);
 }
 await finish(f);
});

test('nonallowlisted destinations and outside-dock links remain byte-identical', async () => {
 const f=await fixture(base+'?ui_lang=de&ui_currency=EUR');
 const hrefs=['//sharity.hu/offerwall','http://sharity.hu/offerwall','https://evil.sharity.hu/offerwall',
  'https://sharity.hu.evil.invalid/offerwall','https://user@sharity.hu/offerwall',
  'https://sharity.hu:443/offerwall','https://sharity.hu/offerwall/','https://sharity.hu/offerwall/extra',
  'https://sharity.hu/api/sharity/session/start?return=fixture','https://merchant.invalid/buy?sat1=fixture',
  'https://app.sharity.hu/go/fixture?d1=keep','https://adomany.sharity.hu/','/profil/#account','#account',
  'mailto:fixture@example.invalid','javascript:void(0)'];
 await f.page.evaluate(hrefs=>{
  const bar=document.querySelector('.sharity-action-bar');
  hrefs.forEach(href=>{const a=document.createElement('a');a.dataset.fixtureNegative='1';a.setAttribute('href',href);bar.appendChild(a);});
  const outside=document.createElement('a');outside.id='outside-dock';outside.href='https://sharity.hu/offerwall?ui_lang=hu';document.body.appendChild(outside);
 },hrefs);
 await f.page.locator('#sharity-slc-btn').click();
 await f.page.locator('#sharity-slc-currency').selectOption('GBP');
 assert.deepEqual(await f.page.locator('[data-fixture-negative]').evaluateAll(as=>as.map(a=>a.getAttribute('href'))),hrefs);
 assert.equal(await f.page.locator('#outside-dock').getAttribute('href'),'https://sharity.hu/offerwall?ui_lang=hu');
 await finish(f);
});

test('native dock click and new-tab href send updated preferences using GET only', async () => {
 for(const newTab of [false,true]) {
  const f=await fixture(base+'?ui_lang=de&ui_currency=EUR',{allowOutbound:true});
  await f.page.locator('#sharity-slc-btn').click();
  await f.page.locator('[data-slc-language=fr]').click();
  await f.page.locator('#sharity-slc-currency').selectOption('GBP');
  await f.page.keyboard.press('Escape');
  const link=f.page.locator('[data-bar=shopping-assistant]');
  const copied=await link.getAttribute('href');
  assert.equal(copied,destinations['shopping-assistant']+'?ui_lang=fr&ui_currency=GBP');
  if(newTab) {
   const tab=await f.context.newPage();await tab.goto(copied);
   assert.equal(tab.url(),copied);
  } else {
   await Promise.all([f.page.waitForURL(copied),link.click()]);
  }
  assert.deepEqual(f.outgoing,[copied]);
  await finish(f);
 }
});
