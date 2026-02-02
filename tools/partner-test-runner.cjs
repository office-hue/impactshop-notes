#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const https = require('https');
const crypto = require('crypto');

const args = process.argv.slice(2);
const isDryRun = args.includes('--dry-run');
const fixturesDir = process.env.FIXTURES_DIR || path.join(process.cwd(), 'fixtures', 'partner');
const baseUrl = process.env.BASE_URL || 'https://app.sharity.hu/impactshop-staging/wp-json/';
const apiKey = process.env.PARTNER_API_KEY || '';
const hmacSecret = process.env.HMAC_SECRET || '';
const partnerCodeOverride = process.env.PARTNER_CODE || '';

if (!fs.existsSync(fixturesDir)) {
  console.error(`Fixtures directory not found: ${fixturesDir}`);
  process.exit(1);
}

const fixtureFiles = fs.readdirSync(fixturesDir)
  .filter((file) => file.endsWith('.json'))
  .sort();

if (!fixtureFiles.length) {
  console.error(`No JSON fixtures found in ${fixturesDir}`);
  process.exit(1);
}

const postUrl = new URL('impact/v1/partner/transaction', baseUrl);

function signPayload(payloadRaw) {
  if (!hmacSecret) {
    return '';
  }
  return crypto.createHmac('sha256', hmacSecret).update(payloadRaw).digest('hex');
}

function sendRequest(payloadRaw, idempotencyKey, signature) {
  return new Promise((resolve, reject) => {
    const timestamp = Date.now().toString();
    const options = {
      method: 'POST',
      hostname: postUrl.hostname,
      port: postUrl.port || 443,
      path: postUrl.pathname,
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payloadRaw),
        'Idempotency-Key': idempotencyKey,
        'X-Impact-Timestamp': timestamp,
      }
    };

    if (apiKey) {
      options.headers.Authorization = `Bearer ${apiKey}`;
    }

    if (signature) {
      options.headers['X-Impact-Signature'] = `sha256=${signature}`;
    }

    const request = https.request(options, (response) => {
      let data = '';
      response.on('data', (chunk) => { data += chunk; });
      response.on('end', () => {
        resolve({ statusCode: response.statusCode, body: data });
      });
    });

    request.on('error', reject);
    request.write(payloadRaw);
    request.end();
  });
}

(async () => {
  console.log(`Target: ${postUrl.toString()}`);
  console.log(`Fixtures: ${fixturesDir}`);
  console.log(`Dry run: ${isDryRun ? 'yes' : 'no'}`);

  const forceNoSign = args.includes('--no-sign');
  const forceInvalidSign = args.includes('--invalid-sign');

  for (const fileName of fixtureFiles) {
    const filePath = path.join(fixturesDir, fileName);
    const payloadOriginal = fs.readFileSync(filePath, 'utf8');
    let payloadRaw = payloadOriginal;
    let payloadJson = null;
    let noSign = forceNoSign;
    let invalidSign = forceInvalidSign;

    try {
      payloadJson = JSON.parse(payloadRaw);
    } catch (error) {
      console.error(`Invalid JSON in ${fileName}: ${error.message}`);
      process.exitCode = 1;
      continue;
    }

    if (payloadJson && typeof payloadJson === 'object') {
      if (partnerCodeOverride) {
        payloadJson.partner_code = partnerCodeOverride;
      }
      if (payloadJson.__no_sign === true) {
        noSign = true;
        delete payloadJson.__no_sign;
      }
      if (payloadJson.__invalid_signature === true) {
        invalidSign = true;
        delete payloadJson.__invalid_signature;
      }
      payloadRaw = JSON.stringify(payloadJson);
    }

    if (!invalidSign && fileName.includes('invalid-signature')) {
      invalidSign = true;
    }

    const idempotencyKey = fileName.replace('.json', '');
    const signature = noSign ? '' : (invalidSign ? '0'.repeat(64) : signPayload(payloadRaw));

    console.log(`\n→ ${fileName}`);
    console.log(`  Idempotency-Key: ${idempotencyKey}`);
    console.log(`  Signature: ${signature || '(empty)'}`);

    if (isDryRun) {
      continue;
    }

    try {
      const result = await sendRequest(payloadRaw, idempotencyKey, signature);
      console.log(`  Status: ${result.statusCode}`);
      if (result.body) {
        console.log(`  Body: ${result.body}`);
      }
    } catch (error) {
      console.error(`  Error: ${error.message}`);
      process.exitCode = 1;
    }
  }
})();
