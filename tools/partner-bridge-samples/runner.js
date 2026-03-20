const crypto = require('crypto');

const args = new Set(process.argv.slice(2));
const dryRun = args.has('--dry-run') || !args.has('--send');

const config = {
  apiBase: process.env.IMPACT_API_BASE || 'https://app.sharity.hu/wp-json/impact/v1/partner/transaction',
  partnerCode: process.env.IMPACT_PARTNER_CODE || 'shopify-abc',
  partnerKey: process.env.IMPACT_PARTNER_KEY || 'YOUR_PARTNER_API_KEY',
  partnerSecret: process.env.IMPACT_PARTNER_SECRET || 'YOUR_HMAC_SECRET'
};

function buildPayload() {
  return {
    partner_code: config.partnerCode,
    event_id: 'order_123456',
    event_type: 'purchase',
    pseudo_id: 'ab12cd34ef56',
    ngo_code: 'bator-tabor',
    amount_gross: 19990,
    currency: 'HUF',
    timestamp: new Date().toISOString(),
    payment_status: 'paid',
    discount_tier: 'gold',
    discount_rate: 0.16,
    partner_max_discount: 0.2,
    amount_net: 16792
  };
}

function signRequest(method, path, body, timestamp, secret) {
  const baseString = `${method}\n${path}\n${body}\n${timestamp}`;
  const hmac = crypto.createHmac('sha256', secret).update(baseString).digest('hex');
  return `sha256=${hmac}`;
}

async function main() {
  const payload = buildPayload();
  const body = JSON.stringify(payload);
  const url = new URL(config.apiBase);
  const timestamp = Date.now().toString();
  const signature = signRequest('POST', url.pathname, body, timestamp, config.partnerSecret);
  const idempotencyKey = crypto.randomUUID();

  if (dryRun) {
    console.log('Dry-run payload:', payload);
    console.log('Signature:', signature);
    console.log('Idempotency-Key:', idempotencyKey);
    return;
  }

  const res = await fetch(config.apiBase, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${config.partnerKey}`,
      'Content-Type': 'application/json',
      'X-Impact-Signature': signature,
      'X-Impact-Timestamp': timestamp,
      'Idempotency-Key': idempotencyKey
    },
    body
  });

  const text = await res.text();
  console.log('Status:', res.status);
  console.log('Response:', text);
}

main().catch((err) => {
  console.error('Runner error:', err);
  process.exit(1);
});
