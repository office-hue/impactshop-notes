#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

const rootDir = process.cwd();
const fixturesDir = path.join(rootDir, 'fixtures', 'partner');

const requiredDocs = [
  'docs/partner-postman-collection.md',
  'docs/partner-postman-collection.json',
  'docs/partner-master-checklist.md',
  'docs/partner-db-schema.md',
  'docs/partner-config-storage.md',
  'docs/partner-auth-secrets.md',
  'docs/partner-reconciliation-job.md',
  'docs/partner-dashboard-wireframes.md',
  'docs/partner-webhook-test-env.md',
  'docs/partner-monitoring-kpi.md',
  'docs/partner-dispute-policy.md',
  'docs/partner-data-retention.md',
  'docs/partner-admin-ui-draft.md',
  'docs/partner-admin-permissions.md',
  'docs/partner-reconcile-export-spec.md',
  'docs/partner-audit-event-list.md',
  'docs/partner-webhook-retry-spec.md',
  'docs/partner-sla-onepager.md',
  'docs/partner-config-validation.md',
  'docs/partner-api-error-catalog.md',
];

function checkFileExists(relativePath) {
  const fullPath = path.join(rootDir, relativePath);
  return fs.existsSync(fullPath);
}

function validateFixtures() {
  if (!fs.existsSync(fixturesDir)) {
    console.error(`Missing fixtures directory: ${fixturesDir}`);
    return false;
  }

  const files = fs.readdirSync(fixturesDir).filter((file) => file.endsWith('.json'));
  if (!files.length) {
    console.error('No fixture JSON files found.');
    return false;
  }

  let allValid = true;
  for (const file of files) {
    const filePath = path.join(fixturesDir, file);
    try {
      const raw = fs.readFileSync(filePath, 'utf8');
      JSON.parse(raw);
    } catch (error) {
      console.error(`Invalid JSON: ${file} -> ${error.message}`);
      allValid = false;
    }
  }

  return allValid;
}

function run() {
  console.log('Partner QA checks');

  let ok = true;
  const missingDocs = requiredDocs.filter((doc) => !checkFileExists(doc));
  if (missingDocs.length) {
    console.error('Missing docs:');
    missingDocs.forEach((doc) => console.error(`- ${doc}`));
    ok = false;
  } else {
    console.log('Docs: OK');
  }

  if (!validateFixtures()) {
    ok = false;
  } else {
    console.log('Fixtures: OK');
  }

  process.exit(ok ? 0 : 1);
}

run();
