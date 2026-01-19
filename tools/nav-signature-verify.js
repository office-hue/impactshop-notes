#!/usr/bin/env node
/**
 * NAV Online Invoice Signature Verification Tool
 * 
 * Összehasonlítja a mi signature generálásunkat a NAV hivatalos 
 * dokumentációból származó test vectorokkal.
 * 
 * Használat:
 *   node tools/nav-signature-verify.js
 */

import crypto from 'crypto';

// NAV Hivatalos példa a dokumentációból (3.0 API spec)
// Forrás: https://onlineszamla.nav.gov.hu/dokumentaciok
const NAV_TEST_VECTOR = {
  requestId: 'RID215215299717672548',
  timestamp: '2018-05-16T10:23:37.979Z',
  signKey: '8f8d50a9c366fff2c558', // Példa signing key
  expectedSignature: 'A51D3266421C8B462FE022256737DB0148907AECFD7E2E15A8AD856B9D95C8FCE9AFE0E33C12991FA8782CA1CB7CFF7F8F3A6C24A6A28E11FDF92CB9E7C449E5'
};

// Saját implementáció (nav-online-invoice.ts-ből)
function sha3_512(value, uppercase = true) {
  const digest = crypto.createHash('sha3-512').update(value, 'utf8').digest('hex');
  return uppercase ? digest.toUpperCase() : digest;
}

function buildRequestSignature(requestId, timestamp, signKey, uppercase = true) {
  const input = `${requestId}${timestamp}${signKey}`;
  console.log('\n🔍 Signature Input Details:');
  console.log(`   requestId: "${requestId}" (length: ${requestId.length})`);
  console.log(`   timestamp: "${timestamp}" (length: ${timestamp.length})`);
  console.log(`   signKey: "${signKey}" (length: ${signKey.length})`);
  console.log(`   concatenated: "${input}"`);
  console.log(`   concatenated length: ${input.length}`);
  
  return sha3_512(input, uppercase);
}

// Test 1: NAV hivatalos példa
console.log('═══════════════════════════════════════════════════════════');
console.log('TEST 1: NAV Hivatalos Példa (API 3.0 Dokumentáció)');
console.log('═══════════════════════════════════════════════════════════');

const ourSignature = buildRequestSignature(
  NAV_TEST_VECTOR.requestId,
  NAV_TEST_VECTOR.timestamp,
  NAV_TEST_VECTOR.signKey,
  true // UPPERCASE
);

console.log(`\n📋 Elvárt signature (NAV doc):`);
console.log(`   ${NAV_TEST_VECTOR.expectedSignature}`);
console.log(`\n📋 Generált signature (mi):`);
console.log(`   ${ourSignature}`);

const test1Pass = ourSignature === NAV_TEST_VECTOR.expectedSignature;
console.log(`\n✅ Eredmény: ${test1Pass ? 'PASS ✓' : 'FAIL ✗'}`);

if (!test1Pass) {
  console.log('\n⚠️  A signature nem egyezik! Lehetséges okok:');
  console.log('   1. Node.js SHA3-512 implementáció eltér');
  console.log('   2. Input string encoding probléma');
  console.log('   3. Case sensitivity (UPPERCASE vs lowercase)');
}

// Test 2: Lowercase signature
console.log('\n═══════════════════════════════════════════════════════════');
console.log('TEST 2: Lowercase Signature Teszt');
console.log('═══════════════════════════════════════════════════════════');

const ourSignatureLower = buildRequestSignature(
  NAV_TEST_VECTOR.requestId,
  NAV_TEST_VECTOR.timestamp,
  NAV_TEST_VECTOR.signKey,
  false // lowercase
);

console.log(`\n📋 Lowercase signature:`);
console.log(`   ${ourSignatureLower}`);
console.log(`\n📋 UPPERCASE signature:`);
console.log(`   ${ourSignature}`);

const test2Match = ourSignatureLower.toUpperCase() === ourSignature;
console.log(`\n✅ Case conversion OK: ${test2Match ? 'PASS ✓' : 'FAIL ✗'}`);

// Test 3: Sign key kötőjel teszt
console.log('\n═══════════════════════════════════════════════════════════');
console.log('TEST 3: Sign Key Kötőjel Preprocessing');
console.log('═══════════════════════════════════════════════════════════');

const signKeyWithDashes = '8f-8d50-a9c366fff2c558';
const signKeyStripped = signKeyWithDashes.replace(/[-\s]/g, '');

console.log(`\n📋 Eredeti sign key: "${signKeyWithDashes}"`);
console.log(`📋 Stripped sign key: "${signKeyStripped}"`);

const sigWithDashes = buildRequestSignature(
  NAV_TEST_VECTOR.requestId,
  NAV_TEST_VECTOR.timestamp,
  signKeyWithDashes,
  true
);

const sigWithoutDashes = buildRequestSignature(
  NAV_TEST_VECTOR.requestId,
  NAV_TEST_VECTOR.timestamp,
  signKeyStripped,
  true
);

console.log(`\n📋 Signature kötőjelekkel: ${sigWithDashes.substring(0, 32)}...`);
console.log(`📋 Signature kötőjelek nélkül: ${sigWithoutDashes.substring(0, 32)}...`);
console.log(`\n✅ Különbség: ${sigWithDashes !== sigWithoutDashes ? 'IGEN (kötőjelek számítanak!)' : 'NEM (ugyanaz)'}`);

// Test 4: Timestamp milliszekundum teszt
console.log('\n═══════════════════════════════════════════════════════════');
console.log('TEST 4: Timestamp Formátum (Milliszekundum)');
console.log('═══════════════════════════════════════════════════════════');

const timestampWithMs = '2018-05-16T10:23:37.979Z';
const timestampWithoutMs = '2018-05-16T10:23:37Z';

const sigWithMs = buildRequestSignature(
  NAV_TEST_VECTOR.requestId,
  timestampWithMs,
  NAV_TEST_VECTOR.signKey,
  true
);

const sigWithoutMs = buildRequestSignature(
  NAV_TEST_VECTOR.requestId,
  timestampWithoutMs,
  NAV_TEST_VECTOR.signKey,
  true
);

console.log(`\n📋 Timestamp milliszekundummal: ${timestampWithMs}`);
console.log(`📋 Timestamp milliszekundum nélkül: ${timestampWithoutMs}`);
console.log(`\n📋 Signature (ms-mal): ${sigWithMs.substring(0, 32)}...`);
console.log(`📋 Signature (ms nélkül): ${sigWithoutMs.substring(0, 32)}...`);
console.log(`\n✅ Különbség: ${sigWithMs !== sigWithoutMs ? 'IGEN (ms számít!)' : 'NEM (ugyanaz)'}`);

// Test 5: SHA3-512 library teszt
console.log('\n═══════════════════════════════════════════════════════════');
console.log('TEST 5: SHA3-512 Library Alapvető Teszt');
console.log('═══════════════════════════════════════════════════════════');

// Ismert test vector (NIST)
const testInput = 'abc';
const expectedHash = 'B751850B1A57168A5693CD924B6B096E08F621827444F70D884F5D0240D2712E10E116E9192AF3C91A7EC57647E3934057340B4CF408D5A56592F8274EEC53F0';

const actualHash = sha3_512(testInput, true);
console.log(`\n📋 Test input: "${testInput}"`);
console.log(`📋 Elvárt hash: ${expectedHash}`);
console.log(`📋 Kapott hash:  ${actualHash}`);

const test5Pass = actualHash === expectedHash;
console.log(`\n✅ SHA3-512 library: ${test5Pass ? 'PASS ✓ (Node.js crypto OK)' : 'FAIL ✗ (library probléma!)'}`);

// Összefoglaló
console.log('\n═══════════════════════════════════════════════════════════');
console.log('📊 ÖSSZEFOGLALÓ');
console.log('═══════════════════════════════════════════════════════════');
console.log(`✅ NAV példa egyezés: ${test1Pass ? 'PASS' : 'FAIL'}`);
console.log(`✅ Case conversion: ${test2Match ? 'OK' : 'FAIL'}`);
console.log(`✅ SHA3-512 library: ${test5Pass ? 'OK' : 'FAIL'}`);
console.log(`ℹ️  Kötőjelek számítanak: ${sigWithDashes !== sigWithoutDashes ? 'IGEN' : 'NEM'}`);
console.log(`ℹ️  Milliszekundum számít: ${sigWithMs !== sigWithoutMs ? 'IGEN' : 'NEM'}`);

console.log('\n═══════════════════════════════════════════════════════════');
console.log('💡 KÖVETKEZŐ LÉPÉSEK:');
console.log('═══════════════════════════════════════════════════════════');

if (!test1Pass) {
  console.log('❌ A NAV példa nem egyezik!');
  console.log('   → Ellenőrizd a NAV dokumentáció aktuális verzióját');
  console.log('   → Lehet, hogy a példa elavult');
}

if (!test5Pass) {
  console.log('❌ SHA3-512 library hiba!');
  console.log('   → Node.js verzió frissítése szükséges');
  console.log('   → Alternatív crypto library kipróbálása');
}

if (test1Pass && test5Pass) {
  console.log('✅ Az implementáció HELYES!');
  console.log('   → A probléma valószínűleg a sign key értékében van');
  console.log('   → Ellenőrizd: NAV_ONLINE_INVOICE_SIGN_KEY pontos értéke');
  console.log('   → Kötőjelek: strip vagy keep?');
  console.log('   → Láthatatlan karakterek (whitespace, BOM)?');
}

console.log('\n');
