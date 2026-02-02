#!/usr/bin/env node

const path = require('path');
const swaggerParser = require('@apidevtools/swagger-parser');

const specPath = process.argv[2] || path.join(process.cwd(), 'docs', 'partner-api-openapi.yaml');

(async () => {
  try {
    await swaggerParser.validate(specPath);
    console.log(`OpenAPI OK: ${specPath}`);
  } catch (error) {
    console.error(`OpenAPI invalid: ${specPath}`);
    console.error(error.message);
    process.exit(1);
  }
})();
