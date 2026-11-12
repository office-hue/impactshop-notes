// Execute the exact inline catalog functions with local boundaries; never contact WordPress.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../wp-content/mu-plugins/impactshop-vb2026-ngo-catalog.php', import.meta.url), 'utf8');
function section(start, end) {
  const begin = source.indexOf(start);
  assert.ok(begin >= 0, start);
  const finish = source.indexOf(end, begin);
  assert.ok(finish > begin, end);
  return source.slice(begin, finish);
}
const markup = section('      function escapeHtml(value)', '      function renderMySelection()');
const select = section('      async function selectNgo(ngoId)', "      root.addEventListener('click'");

test('only active NGOs with explicit permission for this campaign have a selectable action', () => {
  const render = runInNewContext(`${markup}; cardMarkup`, { campaign: 'vb2026' });
  const ngo = { ngo_id: 7, name: 'Synthetic <NGO>', campaign: 'vb2026', is_active: true, allow_user_selection: true };
  assert.match(render(ngo), /data-select-ngo="7"/);
  assert.match(render(ngo), /Synthetic &lt;NGO&gt;/);
  for (const override of [{ allow_user_selection: false }, { allow_user_selection: undefined }, { is_active: false }, { campaign: 'other' }, { campaign: undefined }]) {
    const html = render({ ...ngo, ...override });
    assert.doesNotMatch(html, /data-select-ngo=/);
    assert.match(html, /disabled>Ebben a kampányban most nem választható/);
  }
});

for (const status of [401, 409, 403, 422, 503]) {
  test(`selection status ${status} only falls back to login when authentication is required`, async () => {
    const calls = [], navigations = [], alerts = [];
    const choose = runInNewContext(`${select}; selectNgo`, {
      restBase: '/wp-json/impact/v1', campaign: 'vb2026', currentReturnTarget: 'catalog',
      fetchJson: async (url, options) => {
        calls.push({ url, body: JSON.parse(options.body) });
        if (url.endsWith('/select-ngo')) throw Object.assign(new Error('not allowed'), { status });
        return { auth_url: '/secure-auth' };
      },
      window: { location: { assign: (url) => navigations.push(url) } },
      alert: (message) => alerts.push(message), state: {}, renderMySelection() {},
    });
    await choose('7');
    assert.equal(calls[0].body.selected_sharity_ngo_id, 7);
    assert.equal(calls[0].body.contest_scope, 'vb2026');
    assert.equal(calls.length, status === 401 ? 2 : 1);
    assert.deepEqual(navigations, status === 401 ? ['/secure-auth'] : []);
    assert.deepEqual(alerts, status === 401 ? [] : ['not allowed']);
  });
}
