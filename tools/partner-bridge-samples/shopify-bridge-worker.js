export default {
  async fetch(request, env) {
    if (request.method !== 'POST') {
      return new Response('Method Not Allowed', { status: 405 });
    }

    const body = await request.text();
    const timestamp = Date.now().toString();
    const signature = await signRequest('POST', '/impact/v1/partner/transaction', body, timestamp, env.IMPACT_PARTNER_SECRET);

    const res = await fetch(env.IMPACT_API_BASE, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${env.IMPACT_PARTNER_KEY}`,
        'Content-Type': 'application/json',
        'X-Impact-Signature': signature,
        'X-Impact-Timestamp': timestamp,
        'Idempotency-Key': crypto.randomUUID()
      },
      body
    });

    return new Response(await res.text(), { status: res.status });
  }
};

async function signRequest(method, path, body, timestamp, secret) {
  const baseString = `${method}\n${path}\n${body}\n${timestamp}`;
  const key = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(secret),
    { name: 'HMAC', hash: 'SHA-256' },
    false,
    ['sign']
  );
  const signature = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(baseString));
  const hashArray = Array.from(new Uint8Array(signature));
  const hashHex = hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
  return `sha256=${hashHex}`;
}
