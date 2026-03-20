#!/usr/bin/env node

const crypto = require('crypto');

const [secret, payload] = process.argv.slice(2);

if (!secret || !payload) {
  console.error('Usage: hmac-sign <secret> <payload-json>');
  process.exit(1);
}

try {
  const payloadString = typeof payload === 'string' ? payload : JSON.stringify(payload);
  const signature = crypto.createHmac('sha256', secret).update(payloadString).digest('hex');
  console.log(signature);
} catch (error) {
  console.error(`Error: ${error.message}`);
  process.exit(1);
}
