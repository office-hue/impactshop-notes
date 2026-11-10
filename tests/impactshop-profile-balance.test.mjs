// Full-source browser fixture for the profile's canonical balance display.
// Every request is fulfilled locally; the unreachable proxy prevents escape.
import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const require = createRequire(resolve(process.env.USER_CARD_TEST_DEPENDENCIES_ROOT || root, 'package.json'));
const { chromium } = require('playwright');
const source = readFileSync(resolve(root, 'wp-content/mu-plugins/impactshop-identity-panel.js'), 'utf8');
let browser;
before(async () => { browser = await chromium.launch({ channel: 'chrome', headless: true,
  proxy: { server: 'http://127.0.0.1:9' } }); });
after(async () => { await browser?.close(); });

async function display(profile) {
  const context = await browser.newContext({ serviceWorkers: 'block' });
  const page = await context.newPage();
  const errors = [];
  const methods = [];
  page.on('pageerror', error => errors.push(error.message));
  await context.route('**/*', async route => {
    methods.push(route.request().method());
    if (route.request().isNavigationRequest()) {
      await route.fulfill({ contentType: 'text/html', body: `<!doctype html><meta charset="utf-8">
        <div class="impactshop-identity-panel" data-rest-base="/wp-json/impact/v1">
          <p data-role="status"></p><p data-role="pseudo-display"></p>
          <p data-role="votes-available">OLD VALUE</p><p data-role="votes-state"></p>
          <input data-role="nickname-input" disabled>
        </div>` });
      return;
    }
    const body = new URL(route.request().url()).pathname.endsWith('/identity/profile') ? profile : {};
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify(body) });
  });
  try {
    await page.goto('https://app.sharity.hu/profil/');
    await page.addScriptTag({ content: source });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await page.waitForFunction(() => document.querySelector('[data-role=pseudo-display]').textContent !== '');
    const result = {
      value: await page.locator('[data-role=votes-available]').textContent(),
      state: await page.locator('[data-role=votes-state]').textContent(),
      nicknameDisabled: await page.locator('[data-role=nickname-input]').isDisabled(),
    };
    assert.deepEqual(errors, []);
    assert.ok(methods.every(method => method === 'GET'), 'display fixture must not trigger a mutation');
    return result;
  } finally {
    await context.close();
  }
}

for (const value of [0, 53, Number.MAX_SAFE_INTEGER]) {
  test('active shared balance displays ' + value, async () => {
    const actual = await display({ pseudo_id: 'abcdef123456', identity_state: 'active', votes_available: value });
    assert.equal(actual.value, value.toLocaleString('hu-HU'));
    assert.equal(actual.state, 'A jelenlegi, elkölthető egyenleged.');
    assert.equal(actual.nicknameDisabled, false);
  });
}
for (const value of [null, undefined, true, false, '', '53', -1, 1.5, Number.MAX_SAFE_INTEGER + 1]) {
  test('invalid active balance remains unavailable: ' + String(value), async () => {
    const actual = await display({ pseudo_id: 'abcdef123456', identity_state: 'active', votes_available: value });
    assert.equal(actual.value, 'Átmenetileg nem elérhető');
    assert.equal(actual.state, 'Az egyenleg most nem érhető el. Próbáld újra később.');
    assert.equal(actual.nicknameDisabled, false, 'wallet outage must not change identity authority');
  });
}
for (const [identity_state, expected] of [
  ['legacy_read_only', 'Belépés szükséges'],
  ['invalid_or_revoked', 'Kapcsolat lejárt'],
  ['binding_pending', 'Kapcsolás folyamatban…'],
  ['unavailable', 'Átmenetileg nem elérhető'],
]) {
  test('identity state ' + identity_state + ' keeps its meaning', async () => {
    const actual = await display({ pseudo_id: 'abcdef123456', identity_state, votes_available: 163 });
    assert.equal(actual.value, expected);
    assert.equal(actual.nicknameDisabled, true);
  });
}
test('separate profile contexts cannot reuse another profile balance', async () => {
  const first = await display({ pseudo_id: 'abcdef123456', identity_state: 'active', votes_available: 53 });
  const second = await display({ pseudo_id: 'zyxwvu654321', identity_state: 'active', votes_available: 7 });
  assert.equal(first.value, '53');
  assert.equal(second.value, '7');
});
