#!/usr/bin/env node
// WHAT: NormalizedAdMetric mock fetcher (no external API calls)
// WHY: Demo/teszt célra: stdout-ra ír egy mintatömböt schema_version=v1 mezőkkel.
// HOW: node scripts/ads-fetch-mock.js --platform meta --since 2025-12-01 --until 2025-12-01

const args = process.argv.slice(2);
function getArg(name) {
  const idx = args.indexOf(`--${name}`);
  if (idx !== -1 && idx + 1 < args.length) return args[idx + 1];
  return null;
}

const platform = getArg('platform') || 'meta';
const since = getArg('since') || new Date().toISOString().slice(0, 10);
const until = getArg('until') || since;

function daysBetween(start, end) {
  const s = new Date(start);
  const e = new Date(end);
  const out = [];
  for (let d = new Date(s); d <= e; d.setDate(d.getDate() + 1)) {
    out.push(d.toISOString().slice(0, 10));
  }
  return out;
}

const dates = daysBetween(since, until);
const metrics = [];
const campaigns = ['camp-1', 'camp-2', 'camp-3'];
const ads = ['ad-1', 'ad-2', 'ad-3', 'ad-4'];
const ngoPool = ['allatvedok', 'kornyezetvedok', null];
const advPool = [`${platform}-adv`, `${platform}-adv2`];

dates.forEach((date) => {
  campaigns.forEach((camp, ci) => {
    ads.forEach((ad, ai) => {
      const ngo_code = Math.random() < 0.5 ? ngoPool[Math.floor(Math.random() * ngoPool.length)] : null;
      const advertiser_code = advPool[Math.floor(Math.random() * advPool.length)];
      const capVal = Math.random() < 0.25 ? 50000 : null;
      metrics.push({
        schema_version: 'v1',
        platform,
        account_id: `${platform}-acct`,
        campaign_id: `${camp}-${date}`,
        ad_id: `${ad}-${date}`,
        date,
        views: 50 + ci * 20 + ai * 5,
        clicks: 5 + ai,
        spend: 1000 + ci * 200 + ai * 50,
        est_donation: 800 + ci * 150 + ai * 30,
        ledger_source: 'view',
        ngo_code,
        advertiser_code,
        cap: capVal,
        meta: { sample: true },
      });
    });
  });
});

console.log(JSON.stringify({ metrics, platform, since, until }, null, 2));
