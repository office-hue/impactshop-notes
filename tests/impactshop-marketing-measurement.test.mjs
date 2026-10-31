// Reuses an installed jsdom through MARKETING_TEST_DEPENDENCIES_ROOT; never installs or accesses the network.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { test } from 'node:test';
import { spawn } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { request } from 'node:http';
const require = createRequire(resolve(process.env.MARKETING_TEST_DEPENDENCIES_ROOT, 'package.json'));
const { JSDOM } = require('jsdom');
const source = readFileSync(new URL('../wp-content/mu-plugins/impactshop-marketing-measurement.js', import.meta.url), 'utf8');
function fixture(path = '/rolunk/', { failure = false, pending = false, origin = 'https://app.sharity.hu' } = {}) {
  const dom = new JSDOM('<!doctype html><html><body><p>Original</p></body></html>', { url: origin + path, runScripts: 'outside-only' });
  const calls = []; const window = dom.window;
  window.fetch = (url, options) => { calls.push({ url, options }); return pending ? new Promise(() => {}) : failure ? Promise.reject(new Error('offline')) : Promise.resolve({ ok: true }); };
  window.eval(source);
  const click = label => { const button = [...window.document.querySelectorAll('button')].find(button => button.textContent === label); assert.ok(button, label); button.click(); };
  return { dom, window, calls, click };
}

test('separate consent counts current page once; query/hash and repeat injection do not count', () => {
  const { dom, window, calls, click } = fixture();
  try {
    assert.equal(calls.length, 0); click('Elfogadom'); assert.equal(calls.length, 1);
    assert.deepEqual(JSON.parse(calls[0].options.body), { schemaVersion: 2, routeId: 'sharity-app.about' });
    assert.equal(calls[0].url, '/wp-json/impactshop-marketing/v1/events');
    assert.equal(calls[0].options.credentials, 'same-origin');
    assert.equal(calls[0].options.referrerPolicy, 'no-referrer');
    window.history.pushState({}, '', '/rolunk/?private=secret#part'); window.eval(source);
    assert.equal(calls.length, 1);
    assert.equal(window.document.cookie, '__Host-impactshop-measurement-consent=granted-v2');
    window.history.pushState({}, '', '/ngo-guides/impact-shop/'); assert.equal(calls.length, 2);
    assert.equal(JSON.parse(calls[1].options.body).routeId, 'sharity-app.ngo-guides');
    window.history.pushState({}, '', '/jysk-riport/?print=1'); assert.equal(calls.length, 2);
    assert.equal(window.document.getElementById('impactshop-measurement-choice').hidden, true);
  } finally { dom.window.close(); }
});

test('withdrawal aborts all outstanding counts and stops further sends', () => {
  const { dom, window, calls, click } = fixture('/impactshop/', { pending: true });
  try {
    click('Elfogadom'); window.history.pushState({}, '', '/cegeknek/'); assert.equal(calls.length, 2);
    click('Napi mérés kikapcsolása'); assert.ok(calls.every(call => call.options.signal.aborted));
    window.history.pushState({}, '', '/befektetoknek/'); assert.equal(calls.length, 2);
    assert.equal(window.document.cookie, '__Host-impactshop-measurement-consent=denied-v2');
  } finally { dom.window.close(); }
});

test('failed sends never retry; new real path may count once', async () => {
  const { dom, window, calls, click } = fixture('/rolunk/', { failure: true });
  try {
    click('Elfogadom'); await new Promise(resolve => setImmediate(resolve));
    window.history.replaceState({}, '', '/rolunk/#again'); assert.equal(calls.length, 1);
    window.history.pushState({}, '', '/cegeknek/'); await new Promise(resolve => setImmediate(resolve)); assert.equal(calls.length, 2);
  } finally { dom.window.close(); }
});

test('foreign origin cannot mount consent or send a request', () => {
  const { dom, calls } = fixture('/rolunk/', { origin: 'https://other.invalid' });
  try { assert.equal(dom.window.document.querySelector('aside'), null); assert.equal(calls.length, 0); } finally { dom.window.close(); }
});


test('real local PHP headers and echo+exit inject approved guide HTML only', async () => {
  const reservation = createServer(); reservation.listen(0, '127.0.0.1'); await once(reservation, 'listening');
  const port = reservation.address().port; await new Promise(resolve => reservation.close(resolve));
  const server = spawn('php', ['-S', `127.0.0.1:${port}`, 'tests/impactshop-marketing-measurement.test.php'], { stdio: ['ignore', 'ignore', 'pipe'] });
  let logs = ''; server.stderr.on('data', chunk => { logs += chunk.toString(); });
  try {
    for (let attempt = 0; !logs.includes('Development Server') && attempt < 30; attempt++) await new Promise(resolve => setTimeout(resolve, 10));
    assert.match(logs, /Development Server/);
    for (const [path, inject] of [['/ngo-guides/impact-shop/', true], ['/rolunk/', true], ['/impactshop/?preview=true', false], ['/jysk-riport/?print=1', false], ['/rolunk/?json=1', false], ['/rolunk/?error=1', false]]) {
      const html = await new Promise((resolve, reject) => {
        request({ hostname: '127.0.0.1', port, path, headers: { Host: 'app.sharity.hu' } }, response => {
          let body = ''; response.on('data', chunk => { body += chunk; }); response.on('end', () => resolve(body));
        }).on('error', reject).end();
      });
      assert.equal(html.includes('impactshop-marketing-script'), inject, path);
      assert.equal(html.includes('fixture-server-only'), false);
    }
  } finally { server.kill(); await once(server, 'exit'); }
});
