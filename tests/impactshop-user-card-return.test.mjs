// Real full-source browser fixture. All requests are fulfilled locally, never continued.
// Reuse an existing Playwright installation: USER_CARD_TEST_DEPENDENCIES_ROOT=<repo> node --test <this file>.
import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT || root, 'package.json'));
const { chromium } = require('playwright');
const file = 'wp-content/mu-plugins/impactshop-identity-panel.js';
const current = readFileSync(resolve(root, file), 'utf8');
const baseline = execFileSync('git', ['show', '77c4abbf1b7d45ce9e01eadb102e44554dbdcd15:' + file], { cwd: root, encoding: 'utf8' });
const target = 'https://sharity.hu/api/sharity/session/start?return_intent=sajat-sharity&profile_return=1';
const entry = 'https://app.sharity.hu/profil/?bridge_target=restore&return=' + encodeURIComponent('https://sharity.hu/sajat-sharity');
const fixture = `<!doctype html><div class="impactshop-identity-panel" data-rest-base="/wp-json/impact/v1">
  <p data-role="status"></p><p data-role="pseudo-display"></p><p data-role="recovery-display">ABCD1234EFGH</p>
  <input data-role="restore-pseudo" value="abcdef123456"><input data-role="restore-recovery" value="ABCD1234EFGH">
  <button data-role="restore-submit">Restore</button><p data-role="restore-status"></p>
  <form data-role="save-form"><input data-role="save-username"><input data-role="save-password"><input data-role="save-return"><button>Save</button></form>
</div>`;
let browser;
before(async () => { browser = await chromium.launch({ channel: 'chrome', headless: true }); });
after(async () => { await browser?.close(); });

async function replay(source, url, { action = 'restore', denied = false } = {}) {
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  const calls = [];
  const errors = [];
  const navigations = [];
  page.on('pageerror', (error) => errors.push(error.message));
  let loaded = false;
  await context.route('**/*', async (route) => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    calls.push({ url: request.url(), method: request.method() });
    if (request.isNavigationRequest()) {
      if (loaded && request.frame() === page.mainFrame()) navigations.push(request.url());
      await route.fulfill({ contentType: 'text/html', body: loaded ? '<p>Mock destination</p>' : fixture });
      return;
    }
    let body = {};
    let status = 200;
    if (path.endsWith('/identity/profile')) body = { pseudo_id: 'abcdef123456', identity_state: 'active' };
    if (path.endsWith('/identity/restore') && denied) { status = 403; body = { message: 'Fixture rejected credentials' }; }
    // Includes /pseudo/points/earn: mocked and counted, never sent to production.
    await route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
  });
  try {
    await page.goto(url);
    loaded = true;
    await page.addScriptTag({ content: source });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await page.waitForFunction(() => document.querySelector('[data-role=pseudo-display]').textContent === 'abcdef123456');
    if (action === 'restore') await page.locator('[data-role=restore-submit]').click();
    else await page.locator('[data-role=save-form]').evaluate((form) => form.requestSubmit());
    let hiddenReturn;
    if (action === 'save') hiddenReturn = await page.locator('[data-role=save-return]').inputValue();
    if (denied) {
      await page.waitForFunction(() => document.querySelector('[data-role=restore-status]').textContent === 'Fixture rejected credentials');
      // The real navigation is delayed 900ms; prove the rejected request doesn't schedule it.
      await page.waitForTimeout(1050);
    } else {
      await page.waitForURL((result) => result.href !== url, { timeout: 4000 });
    }
    assert.deepEqual(errors, []);
    return { url: page.url(), calls, navigations, hiddenReturn };
  } finally { await context.close(); }
}

test('successful restore starts one fresh User Card handoff', async () => {
  const result = await replay(current, entry);
  assert.equal(result.url, target);
  assert.equal(result.navigations.length, 1);
  assert.equal(result.calls.filter((call) => call.url.includes('/identity/restore')).length, 1);
  assert.equal(result.calls.filter((call) => call.url.includes('/points/earn')).length, 0);
});

test('failed restore stays on the profile with an explicit error', async () => {
  const result = await replay(current, entry, { denied: true });
  assert.equal(result.url, entry);
  assert.deepEqual(result.navigations, []);
});

test('account-save changes only outer navigation, not the password-manager form', async () => {
  const url = entry.replace('bridge_target=restore', 'bridge_target=account');
  const old = await replay(baseline, url, { action: 'save' });
  const result = await replay(current, url, { action: 'save' });
  assert.equal(result.url, target);
  assert.equal(result.hiddenReturn, old.hiddenReturn);
  assert.equal(new URL(result.hiddenReturn).searchParams.get('impactshop_profile_return_complete'), '1');
  assert.equal(result.calls.filter((call) => call.url.includes('/points/earn')).length, 1);
});

const otherUrls = [
  entry.replace(encodeURIComponent('https://sharity.hu/sajat-sharity'), encodeURIComponent('https://sharity.hu/vallalasok')),
  entry.replace(encodeURIComponent('https://sharity.hu/sajat-sharity'), encodeURIComponent('https://sharity.hu/hatas-korok')),
  'https://app.sharity.hu/profil/?bridge_target=restore&bridge_context=fixture-signed-context',
  entry + '&selection_intent=fixture-intent',
  entry + '&selection_intent=',
  entry + '&return=' + encodeURIComponent('https://sharity.hu/sajat-sharity'),
  entry.replace('app.sharity.hu', 'app-staging.sharity.hu'),
  entry.replace('/profil/', '/other/'),
  entry.replace('https://app.', 'http://app.'),
  ...['https://evil.example/sajat-sharity', 'https://sharity.hu.evil.example/sajat-sharity',
    'https://user@sharity.hu/sajat-sharity', 'https://sharity.hu:443/sajat-sharity',
    'https://sharity.hu/sajat-sharity?x=1', 'https://sharity.hu/sajat-sharity#x', '/sajat-sharity']
    .map((value) => 'https://app.sharity.hu/profil/?bridge_target=restore&return=' + encodeURIComponent(value)),
];
for (const url of otherUrls) {
  test('preserves existing non-User-Card navigation: ' + url, async () => {
    const old = await replay(baseline, url);
    const result = await replay(current, url);
    assert.equal(result.url, old.url);
    assert.notEqual(result.url, target);
  });
}
