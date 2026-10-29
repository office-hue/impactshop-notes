// Real full-source browser fixture. All requests are fulfilled locally, never continued.
// PHP redirect decisions are followed with a fresh JS navigation: Playwright does not
// re-route every HTTP redirect hop. An unreachable proxy also prevents network escape.
// Reuse an existing Playwright installation: USER_CARD_TEST_DEPENDENCIES_ROOT=<repo> node --test <this file>.
import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHmac } from 'node:crypto';

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
before(async () => { browser = await chromium.launch({ channel: 'chrome', headless: true,
  proxy: { server: 'http://127.0.0.1:9' } }); });
after(async () => { await browser?.close(); });

async function replay(source, url, { action = 'restore', denied = false, contextState, legacyPhp = false } = {}) {
  const context = await browser.newContext({ serviceWorkers: 'block' });
  if (contextState) {
    const cookie = contextState === 'valid'
      ? 'fixture-id.fixture-nonce.' + createHmac('sha256', 'replace_test_only_profile_return').update('fixture-id|fixture-nonce').digest('hex')
      : contextState === 'empty' ? '' : 'malformed';
    await context.addCookies([{ name: '__Host-impact_profile_return_ctx', value: cookie,
      url: 'https://app.sharity.hu/', secure: true, httpOnly: true, sameSite: 'Lax' }]);
  }
  const page = await context.newPage();
  const calls = [];
  const errors = [];
  const navigations = [];
  const resolutions = [];
  page.on('pageerror', (error) => errors.push(error.message));
  let loaded = false;
  await context.route('**/*', async (route) => {
    const request = route.request();
    const path = new URL(request.url()).pathname;
    calls.push({ url: request.url(), method: request.method(), mainFrame: request.frame() === page.mainFrame() });
    if (request.isNavigationRequest()) {
      if (loaded && request.frame() === page.mainFrame()) navigations.push(request.url());
      if (loaded && request.frame() === page.mainFrame() && new URL(request.url()).searchParams.has('impactshop_profile_return_complete')) {
        const cookies = await context.cookies(request.url());
        const cookie = cookies.find(item => item.name === '__Host-impact_profile_return_ctx');
        const phpUrl = new URL(request.url());
        if (legacyPhp) phpUrl.searchParams.delete('return');
        const result = JSON.parse(execFileSync('php', ['tests/impactshop-user-card-profile-return.test.php', '--request'], {
          cwd: root, encoding: 'utf8', input: JSON.stringify({ url: phpUrl.href, scenario: contextState || 'valid',
            ...(cookie ? { cookie: cookie.value } : {}) }),
        }));
        resolutions.push(result);
        if (result.target) {
          assert.equal(result.status, 303);
          await route.fulfill({ contentType: 'text/html', body:
            '<script>location.replace(' + JSON.stringify(result.target).replace(/</g, '\\u003c') + ')</script>' });
          return;
        }
      }
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
    assert.equal(await page.evaluate(() => document.cookie.includes('__Host-impact_profile_return_ctx')), false);
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
      await page.waitForURL((result) => result.href !== url && !result.searchParams.has('impactshop_profile_return_complete'), { timeout: 4000 });
    }
    assert.deepEqual(errors, []);
    return { url: page.url(), calls, navigations, hiddenReturn, resolutions };
  } finally { await context.close(); }
}

test('successful restore starts one fresh User Card handoff', async () => {
  const result = await replay(current, entry);
  assert.equal(result.url, target);
  assert.equal(result.navigations.length, 2);
  assert.equal(new URL(result.navigations[0]).searchParams.get('return'), 'https://sharity.hu/sajat-sharity');
  assert.equal(result.resolutions[0].resolverCalls.length, 1);
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
  const posts = result.calls.filter(call => call.method === 'POST' && !call.mainFrame && new URL(call.url).pathname === '/profil/');
  assert.equal(posts.length, 1);
  assert.equal(posts[0].url, url);
  assert.equal(new URL(posts[0].url).searchParams.has('impactshop_profile_return_complete'), false);
});

test('HttpOnly FactLens context wins over a conflicting User Card return through PHP', async () => {
  const result = await replay(current, entry, { contextState: 'valid' });
  assert.equal(result.url, 'https://factlens.eu/factlens/vb-prod/');
  assert.equal(result.navigations.length, 2);
  assert.equal(result.resolutions[0].resolverCalls.length, 1);
  assert.equal(result.resolutions[0].resolverCalls[0][1], 'https://app.sharity.hu/profil/#impactshop-restore-title');
  assert.equal(result.calls.some(call => call.url === target), false);
});
for (const contextState of ['invalid', 'empty']) {
  test('originally present ' + contextState + ' HttpOnly context retains native fallback', async () => {
    const result = await replay(current, entry, { contextState });
    assert.equal(result.url, 'https://app.sharity.hu/profil/#impactshop-restore-title');
    assert.equal(result.calls.some(call => call.url === target), false);
  });
}
test('new JS with legacy PHP fallback is safe but does not activate the correction', async () => {
  const result = await replay(current, entry, { legacyPhp: true });
  assert.equal(result.url, 'https://app.sharity.hu/profil/#impactshop-restore-title');
});

const otherUrls = [
  entry.replace(encodeURIComponent('https://sharity.hu/sajat-sharity'), encodeURIComponent('https://sharity.hu/vallalasok')),
  entry.replace(encodeURIComponent('https://sharity.hu/sajat-sharity'), encodeURIComponent('https://sharity.hu/hatas-korok')),
  'https://app.sharity.hu/profil/?bridge_target=restore&bridge_context=fixture-signed-context',
  entry + '&selection_intent=fixture-intent',
  entry + '&selection_intent=',
  entry + '&return=' + encodeURIComponent('https://sharity.hu/sajat-sharity'),
  entry + '&return[]=bad',
  entry + '&selection_intent[]=x',
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
