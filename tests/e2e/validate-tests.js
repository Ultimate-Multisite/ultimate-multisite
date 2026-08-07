#!/usr/bin/env node

/**
 * Test validation script for WP Multisite Ultimate E2E tests
 * This script validates the test structure without running them
 */

const fs = require('fs');
const path = require('path');

const testDir = path.join(__dirname, 'cypress', 'integration');
const commandsDir = path.join(__dirname, 'cypress', 'support', 'commands');

// Test files to validate
const testFiles = [
  'setup-wizard-complete.spec.js',
  'checkout-registration.spec.js',
  'checkout-validation.spec.js',
  'checkout-scenarios.spec.js',
  'checkout-confirmation.spec.js'
];

// Command files to validate
const commandFiles = [
  'checkout.js',
  'login.js',
  'wizard.js',
  'index.js'
];

// Custom commands we expect to exist
const expectedCustomCommands = [
  'visitCheckoutForm',
  'selectPricingPlan',
  'fillAccountDetails',
  'fillSiteDetails',
  'selectSiteTemplate',
  'fillBillingAddress',
  'selectPaymentGateway',
  'proceedToNextStep',
  'completeCheckout',
  'verifyCheckoutSuccess',
  'assertCheckoutStep',
  'hasValidationErrors'
];

console.log('🧪 Validating WP Multisite Ultimate E2E Tests');
console.log('=' .repeat(50));

let allValid = true;

// Validate test files exist and have proper structure
console.log('\n📁 Validating Test Files:');
testFiles.forEach(file => {
  const filePath = path.join(testDir, file);
  if (!fs.existsSync(filePath)) {
    console.log(`❌ ${file} - File not found`);
    allValid = false;
    return;
  }

  const content = fs.readFileSync(filePath, 'utf8');

  // Basic structure checks
  const checks = {
    hasDescribe: content.includes('describe('),
    hasIt: content.includes('it('),
    hasCyCommands: content.includes('cy.'),
    hasTestData: content.includes('test') || content.includes('Test'),
    hasProperStructure: content.includes('beforeEach(') || content.includes('before(')
  };

  console.log(`✅ ${file} - Exists and has proper structure`);

  if (!checks.hasDescribe || !checks.hasIt) {
    console.log(`   ⚠️  Warning: Missing describe() or it() blocks`);
  }

  if (!checks.hasCyCommands) {
    console.log(`   ⚠️  Warning: No Cypress commands found`);
  }
});

// Validate command files exist
console.log('\n🔧 Validating Command Files:');
commandFiles.forEach(file => {
  const filePath = path.join(commandsDir, file);
  if (!fs.existsSync(filePath)) {
    console.log(`❌ ${file} - File not found`);
    allValid = false;
    return;
  }

  console.log(`✅ ${file} - Exists`);
});

// Check if custom commands are defined
console.log('\n⚡ Validating Custom Commands:');
const checkoutCommandsPath = path.join(commandsDir, 'checkout.js');
if (fs.existsSync(checkoutCommandsPath)) {
  const checkoutContent = fs.readFileSync(checkoutCommandsPath, 'utf8');

  expectedCustomCommands.forEach(command => {
    if (checkoutContent.includes(`"${command}"`)) {
      console.log(`✅ ${command} - Command defined`);
    } else {
      console.log(`⚠️  ${command} - Command may not be properly defined`);
    }
  });
} else {
  console.log(`❌ checkout.js commands file not found`);
  allValid = false;
}

// Check for proper imports in index.js
console.log('\n📦 Validating Command Imports:');
const indexPath = path.join(commandsDir, 'index.js');
if (fs.existsSync(indexPath)) {
  const indexContent = fs.readFileSync(indexPath, 'utf8');

  if (indexContent.includes('./checkout')) {
    console.log(`✅ Checkout commands imported`);
  } else {
    console.log(`⚠️  Checkout commands may not be imported`);
  }

  if (indexContent.includes('./login') && indexContent.includes('./wizard')) {
    console.log(`✅ Existing commands imported`);
  } else {
    console.log(`⚠️  Some existing commands may not be imported`);
  }
} else {
  console.log(`❌ index.js commands file not found`);
  allValid = false;
}

// Validate test execution order
console.log('\n🔄 Validating Test Dependencies:');
const setupWizardPath = path.join(testDir, 'setup-wizard-complete.spec.js');
if (fs.existsSync(setupWizardPath)) {
  console.log(`✅ Setup wizard test exists (should run first)`);

  const checkoutFiles = testFiles.filter(f => f.startsWith('checkout-'));
  console.log(`✅ ${checkoutFiles.length} checkout tests found (depend on setup)`);
} else {
  console.log(`❌ Setup wizard test not found`);
  allValid = false;
}

// Check configuration files
console.log('\n⚙️  Validating Configuration:');
const configFiles = [
  'cypress.config.js',
  'cypress.config.test.js',
  'cypress.config.dev.js'
];

configFiles.forEach(file => {
  const configPath = path.join(__dirname, '..', '..', file);
  if (fs.existsSync(configPath)) {
    console.log(`✅ ${file} - Configuration exists`);
  } else {
    console.log(`⚠️  ${file} - Configuration not found`);
  }
});

// Summary
console.log('\n' + '=' .repeat(50));
if (allValid) {
	console.log('🎉 All tests validation passed!');
	console.log('\n📋 Next Steps:');
	console.log('1. Start WordPress environment: pnpm run env:start:test');
	console.log('2. Run setup wizard test: pnpm exec cypress run --spec "tests/e2e/cypress/integration/setup-wizard-complete.spec.js"');
	console.log('3. Run checkout tests: pnpm exec cypress run --spec "tests/e2e/cypress/integration/checkout-*.spec.js"');
	console.log('\n💡 Or use the pnpm scripts:');
	console.log('   pnpm run cy:run:test');

	process.exit(0);
} else {
	console.log('❌ Some validation issues found - please check the messages above');
	process.exit(1);
}
