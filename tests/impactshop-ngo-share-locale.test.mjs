import assert from 'node:assert/strict';
import { before, after, test } from 'node:test';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT || root, 'package.json'));
const { chromium } = require('playwright');
const runtime = readFileSync(resolve(root, 'wp-content/mu-plugins/impactshop-ngo-card.js'), 'utf8');
const base = 'https://app.sharity.hu/ngo/fixture-ngo/share/';
const name = 'Fixture </script><img src=x onerror=window.injected=1> NGO';
const provider = {slug:'fixture-ngo',name,amount:{formatted:'12 345 Ft'},rank:3,share_url:base,cta_url:'https://app.sharity.hu/go?d1=fixture-ngo&return=a%20b&return=a+b#keep'};
const render = (url, cookies={}, bar=true) => execFileSync('php',[resolve(root,'tests/fixtures/impactshop-ngo-share-render.php'),url,name,JSON.stringify(cookies),bar?'':'no-bar'],{encoding:'utf8'});
let browser;
before(async()=>{browser=await chromium.launch({channel:'chrome',headless:true,proxy:{server:'http://127.0.0.1:9'}});});
after(async()=>{await browser?.close();});
async function fixture(query='', {blocked=false, stored, bar=true, width=375}={}) {
 const context=await browser.newContext({viewport:{width,height:850}});
 await context.addInitScript(({blocked,stored})=>{
  window.shares=[];window.copies=[];window.pdf=[];window.failCopy=false;
  Object.defineProperty(navigator,'share',{value:async data=>window.shares.push({...data})});
  Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async text=>{if(window.failCopy)throw Error('denied');window.copies.push(text);}}});
  window.html2canvas=async()=>({width:400,height:500,toDataURL:()=>''});
  window.jspdf={jsPDF:class{internal={pageSize:{getWidth:()=>600,getHeight:()=>800}};addImage(){} setFontSize(){}setTextColor(){}getTextWidth(){return 100;}textWithLink(text,x,y,options){window.pdf.push({text,...options});}save(filename){window.pdf.push({filename});}}};
  if(stored)localStorage.setItem('sharity_ui_preferences_v1',JSON.stringify(stored));
  if(blocked){Storage.prototype.getItem=Storage.prototype.setItem=()=>{throw Error('blocked');};Object.defineProperty(document,'cookie',{get(){throw Error('blocked');},set(){throw Error('blocked');}});}
 },{blocked,stored});
 const page=await context.newPage();const errors=[],escaped=[],requests=[];let calls=0;
 page.on('pageerror',e=>errors.push(e.message));
 await context.route('**/*',async route=>{
  const req=route.request(),url=new URL(req.url());requests.push(req.method());
  if(req.isNavigationRequest()&&url.origin==='https://app.sharity.hu')return route.fulfill({contentType:'text/html; charset=utf-8',body:render(req.url(),{},bar)});
  if(url.pathname.endsWith('/impactshop-ngo-card.js'))return route.fulfill({contentType:'application/javascript',body:runtime});
  if(url.pathname.startsWith('/wp-json/impact/v1/ngo-card/')){calls++;return route.fulfill({contentType:'application/json',body:JSON.stringify(provider)});}
  if(req.resourceType()==='image')return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"/>'});
  if(url.hostname==='fonts.googleapis.com')return route.fulfill({contentType:'text/css',body:''});
  escaped.push(req.url());return route.abort();
 });
 await page.goto(base+query);await page.locator('.impact-ngo-card__title').waitFor();
 return {context,page,errors,escaped,requests,calls:()=>calls};
}
async function finish(f){assert.deepEqual(f.errors,[]);assert.deepEqual(f.escaped,[]);assert.ok(f.requests.every(m=>m==='GET'));assert.equal(await f.page.evaluate(()=>window.injected),undefined);await f.context.close();}
const cta=f=>f.page.locator('.impact-ngo-share__cta').getAttribute('href');
const labels={hu:['Tovább az Impact Shopba','Link másolása'],en:['Go to Impact Shop','Copy link'],de:['Zum Impact Shop','Link kopieren'],fr:['Accéder à Impact Shop','Copier le lien']};

test('actual PHP and runtime: all 16 independent choices, escaped NGO text, unchanged original amounts/CTA, no native share on load',async()=>{
 let original;
 for(const language of ['hu','en','de','fr'])for(const currency of ['HUF','EUR','USD','GBP']){
  const f=await fixture(`?ui_lang=${language}&ui_currency=${currency}`);
  assert.equal(await f.page.locator('html').getAttribute('lang'),language);
  assert.equal((await f.page.locator('.impact-ngo-share__cta').textContent()).trim(),labels[language][0]);
  assert.equal((await f.page.locator('#impact-ngo-share-copy').textContent()).trim(),labels[language][1]);
  assert.equal((await f.page.locator('.impact-ngo-share__amount strong').textContent()).trim(),'12 345 Ft');
  assert.equal(await f.page.locator('#ngo-share-title').textContent(),name);
  assert.equal(await f.page.locator('#ngo-share-title img').count(),0);
  original??=await cta(f);assert.equal(await cta(f),original);
  assert.equal(await f.page.locator('#sharity-slc-currency').inputValue(),currency);
  assert.deepEqual(await f.page.evaluate(()=>window.shares),[]);
  assert.equal(f.calls(),1);
  await finish(f);
 }
});
test('SSR language validates raw duplicate and encoded keys, cookie and legacy; missing action bar still renders',()=>{
 const cases=[['?ui_lang=de','de'],['?ui_lang=de&%75i_lang=fr','en'],['?ui_lang[]=de&lang=en','en'],['?ui_lang=DE&lang=hu','en'],['?%75i_lang=fr','fr']];
 for(const [q,expected]of cases)assert.ok(render(base+q,{sharity_ui_language:'en'},false).includes(`<html lang="${expected}">`));
 assert.ok(render(base+'?lang=en',{},false).includes('<html lang="en">'));
 assert.ok(render(base,{sharity_ui_language:['de']},false).includes('<html lang="hu">'));
});
test('live DE/FR selector with blocked storage updates copy, metadata, native sharing, PDF; original links and fetch count preserved',async()=>{
 const f=await fixture('?ui_lang=de&ui_currency=EUR',{blocked:true,width:320});const initial=await cta(f);
 await f.page.locator('#sharity-slc-btn').click();await f.page.locator('[data-slc-language=fr]').click();await f.page.locator('#sharity-slc-currency').selectOption('GBP');await f.page.keyboard.press('Escape');
 assert.equal(await f.page.locator('html').getAttribute('lang'),'fr');
 assert.equal((await f.page.locator('.impact-ngo-share__cta').textContent()).trim(),labels.fr[0]);
 await f.page.locator('#impact-ngo-share-trigger').click();
 assert.equal((await f.page.evaluate(()=>window.shares))[0].title,`Soutenez ${name} via Impact Shop`);
 await f.page.locator('#impact-ngo-share-copy-text').click();
 assert.ok((await f.page.evaluate(()=>window.copies))[0].startsWith('Je soutiens'));
 await f.page.locator('#impact-ngo-share-pdf').click();
 const pdf=await f.page.evaluate(()=>window.pdf);assert.equal(pdf[0].text,'Soutenir en ligne');assert.equal(pdf[0].url,initial);assert.equal(pdf[1].filename,'fixture-ngo-impactshop-kartya.pdf');
 assert.equal(await cta(f),initial);assert.equal(f.calls(),1);
 assert.equal(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 await finish(f);
});
test('clipboard rejection is truthful; repeated clicks and language changes restore current labels',async()=>{
 const f=await fixture('?ui_lang=de');await f.page.evaluate(()=>window.failCopy=true);
 await f.page.locator('#impact-ngo-share-copy').click();assert.equal(await f.page.locator('#impact-ngo-share-copy').textContent(),'Kopieren fehlgeschlagen');
 await f.page.locator('#impact-ngo-share-copy').click();
 await f.page.locator('#sharity-slc-btn').click();await f.page.locator('[data-slc-language=fr]').click();await f.page.keyboard.press('Escape');
 assert.equal((await f.page.locator('#impact-ngo-share-copy').textContent()).trim(),'Copier le lien');
 await f.page.waitForTimeout(2100);assert.equal((await f.page.locator('#impact-ngo-share-copy').textContent()).trim(),'Copier le lien');
 await f.page.locator('#impact-ngo-share-embed-copy').click();assert.equal(await f.page.locator('#impact-ngo-share-embed-copy').textContent(),'Échec de la copie');
 await finish(f);
});
test('saved language takes effect and standalone page remains usable without the action bar',async()=>{
 const f=await fixture('',{stored:{language:'fr',displayCurrency:'USD'}});assert.equal(await f.page.locator('html').getAttribute('lang'),'fr');await finish(f);
 const g=await fixture('?ui_lang=de',{bar:false,width:320});assert.equal(await g.page.locator('html').getAttribute('lang'),'de');assert.equal(await g.page.locator('#sharity-slc-btn').count(),0);await finish(g);
});


test('DE/FR preserve bounded layout at 320/375/1280, keyboard chooser and localized PDF failure',async()=>{
 for(const language of ['de','fr'])for(const width of [320,375,1280]){
  const f=await fixture('?ui_lang='+language,{width});
  assert.equal(await f.page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  await f.page.locator('#sharity-slc-btn').focus();await f.page.keyboard.press('Enter');
  assert.equal(await f.page.locator('#sharity-slc-sheet').isVisible(),true);await f.page.keyboard.press('Escape');
  assert.equal(await f.page.locator('#sharity-slc-sheet').isVisible(),false);
  assert.equal(await f.page.locator('[data-bar=shopping-assistant] > span:last-child').evaluate(el=>el.scrollWidth<=el.clientWidth),true);
  if(width===320){
   await f.page.screenshot({path:resolve(root,'tmp/sharity-internationalization/ngo-share-'+language+'-320.png'),fullPage:true});
   await f.page.evaluate(()=>{window.html2canvas=async()=>{throw Error('fixture PDF failure');};});
   await f.page.locator('#impact-ngo-share-pdf').click();
   assert.equal(await f.page.locator('#impact-ngo-share-pdf').textContent(),language==='de'?'PDF konnte nicht gespeichert werden':'Impossible d’enregistrer le PDF');
  }
  await finish(f);
 }
});
test('clipboard fallback success and failure; embed payload and share URL remain original',async()=>{
 const f=await fixture('?ui_lang=en');
 await f.page.locator('#impact-ngo-share-copy').click();assert.equal((await f.page.evaluate(()=>window.copies))[0],base);
 await f.page.locator('#impact-ngo-share-embed-copy').click();assert.equal((await f.page.evaluate(()=>window.copies))[1],await f.page.locator('#impact-ngo-share-embed-code').inputValue());
 await f.page.evaluate(()=>{Object.defineProperty(navigator,'clipboard',{configurable:true,value:undefined});document.execCommand=()=>false;});
 await f.page.locator('#impact-ngo-share-copy').click();assert.equal(await f.page.locator('#impact-ngo-share-copy').textContent(),'Copy failed');
 await f.page.evaluate(()=>{document.execCommand=()=>true;});
 await f.page.locator('#impact-ngo-share-copy').click();assert.equal(await f.page.locator('#impact-ngo-share-copy').textContent(),'Copied ✓');
 await finish(f);
});
