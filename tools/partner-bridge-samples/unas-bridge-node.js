const crypto = require('crypto');
const http = require('http');

const config = {
  apiBase: process.env.IMPACT_API_BASE || 'https://app.sharity.hu/wp-json/impact/v1/partner/transaction',
  partnerKey: process.env.IMPACT_PARTNER_KEY || 'YOUR_PARTNER_API_KEY',
  partnerSecret: process.env.IMPACT_PARTNER_SECRET || 'YOUR_HMAC_SECRET'
};

function signRequest(method, path, body, timestamp, secret) {
  const baseString = `${method}\n${path}\n${body}\n${timestamp}`;
  const hmac = crypto.createHmac('sha256', secret).update(baseString).digest('hex');
  return `sha256=${hmac}`;
}

function forwardToImpactShop(payload) {
  const body = JSON.stringify(payload);
  const url = new URL(config.apiBase);
  const timestamp = Date.now().toString();
  const signature = signRequest('POST', url.pathname, body, timestamp, config.partnerSecret);

  return fetch(config.apiBase, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${config.partnerKey}`,
      'Content-Type': 'application/json',
      'X-Impact-Signature': signature,
      'X-Impact-Timestamp': timestamp,
      'Idempotency-Key': crypto.randomUUID()
    },
    body
  });
}

const server = http.createServer(async (req, res) => {
  if (req.method !== 'POST') {
    res.writeHead(405);
    return res.end('Method Not Allowed');
  }

  let data = '';
  req.on('data', (chunk) => (data += chunk));
  req.on('end', async () => {
    const payload = JSON.parse(data);
    const response = await forwardToImpactShop(payload);
    const text = await response.text();
    res.writeHead(response.status, { 'Content-Type': 'application/json' });
    res.end(text);
  });
});

server.listen(8787, () => {
  console.log('UNAS bridge listening on http://localhost:8787');
});
