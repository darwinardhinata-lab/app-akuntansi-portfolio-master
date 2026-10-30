#!/usr/bin/env node
/**
 * r1-browser-acceptance.mjs
 * R1 Browser Acceptance Test Harness
 *
 * Connects to staging URL and verifies R1 Finance Payment UI renders correctly.
 * Gracefully exits if staging is not available.
 *
 * Usage:
 *   R1_STAGING_URL=https://staging.example.com \
 *   R1_UAT_USER=uat_user \
 *   R1_UAT_PASS=uat_pass \
 *   node scripts/r1-browser-acceptance.mjs
 */

import { chromium } from 'playwright';

const STAGING_URL = process.env.R1_STAGING_URL;
const UAT_USER = process.env.R1_UAT_USER;
const UAT_PASS = process.env.R1_UAT_PASS;

const RESULTS = { passed: 0, failed: 0, skipped: 0, items: [] };

function log(status, message, detail = '') {
  const prefix = status === 'PASS' ? '✓' : status === 'FAIL' ? '✗' : '⊝';
  RESULTS.items.push({ status, message, detail });
  if (status === 'PASS') RESULTS.passed++;
  else if (status === 'FAIL') RESULTS.failed++;
  else RESULTS.skipped++;
  console.log(`${prefix} ${message}${detail ? ` — ${detail}` : ''}`);
}

async function captureScreenshot(page, name) {
  const dir = 'docs/integration/screenshots';
  const fs = await import('fs');
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: `${dir}/${name}.png`, fullPage: true });
}

async function main() {
  console.log('=== R1 Browser Acceptance Test Harness ===\n');

  if (!STAGING_URL || !UAT_USER || !UAT_PASS) {
    log('SKIP', 'STAGING NOT AVAILABLE', 'Run with R1_STAGING_URL, R1_UAT_USER, R1_UAT_PASS when environment is ready');
    generateReport();
    process.exit(0);
  }

  console.log(`Staging URL: ${STAGING_URL}\n`);

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  try {
    log('INFO', 'Navigating to staging login...');
    await page.goto(STAGING_URL, { waitUntil: 'networkidle' });
    await captureScreenshot(page, '01-login-page');

    const userInput = page.locator('input[name="email"], input[name="username"]').first();
    const passInput = page.locator('input[name="password"]').first();
    if ((await userInput.count()) > 0 && (await passInput.count()) > 0) {
      await userInput.fill(UAT_USER);
      await passInput.fill(UAT_PASS);
      await page.keyboard.press('Enter');
      await page.waitForURL('**/erp-panel/**', { timeout: 15000 });
    }
    await captureScreenshot(page, '02-after-login');
    // 2. Navigate to /erp-panel/bank-payments
    log('INFO', 'Navigating to /erp-panel/bank-payments...');
    await page.goto(`${STAGING_URL}/erp-panel/bank-payments`, { waitUntil: 'networkidle' });
    await captureScreenshot(page, '03-bank-payments-desktop');

    const inboxVisible = await page.locator('text=Role inbox, text=Awaiting my action, text=All authorized transactions').first().isVisible();
    log(inboxVisible ? 'PASS' : 'FAIL', 'Bank payments inbox renders', inboxVisible ? '' : 'inbox not found');

    const roleFilter = page.locator('select, [role="combobox"]').first();
    if ((await roleFilter.count()) > 0) {
      await roleFilter.click();
      log('PASS', 'Role filter is interactive');
    } else {
      log('FAIL', 'Role filter not found');
    }

    const firstRow = page.locator('tr, [wire\\:id]').first();
    if ((await firstRow.count()) > 0) {
      await firstRow.click();
      await page.waitForTimeout(1000);
      const detailPanel = page.locator('text=Detail, text=Payment, text=Amount').first();
      log(await detailPanel.isVisible() ? 'PASS' : 'FAIL', 'Detail panel opens on row click');
    } else {
      log('SKIP', 'No rows to click for detail panel test');
    }

    // 3. Navigate to /erp-panel/ap-reconciliation
    log('INFO', 'Navigating to /erp-panel/ap-reconciliation...');
    await page.goto(`${STAGING_URL}/erp-panel/ap-reconciliation`, { waitUntil: 'networkidle' });
    await captureScreenshot(page, '04-ap-reconciliation-desktop');

    const queueVisible = await page.locator('text=Search invoice, text=Select a document').first().isVisible();
    log(queueVisible ? 'PASS' : 'FAIL', 'AP reconciliation queue renders', queueVisible ? '' : 'queue not found');

    const uploadButton = page.locator('input[type="file"], text=Upload').first();
    log(await uploadButton.isVisible() ? 'PASS' : 'FAIL', 'Upload button visible');

    const versionCheck = page.locator('text=Version, text=v').first();
    log(await versionCheck.isVisible() ? 'PASS' : 'FAIL', 'Version check present');

    // 4. Test print endpoint
    log('INFO', 'Testing print endpoint...');
    const printUrl = `${STAGING_URL}/payment-plan/bank/1/print`;
    const printResponse = await page.request.get(printUrl);
    log(printResponse.ok() ? 'PASS' : 'FAIL', `Print endpoint responds (${printUrl})`, `status=${printResponse.status()}`);

    // 5. Mobile viewport test
    log('INFO', 'Running mobile viewport test (390px)...');
    const mobilePage = await context.newPage();
    await mobilePage.setViewportSize({ width: 390, height: 844 });
    await mobilePage.goto(`${STAGING_URL}/erp-panel/bank-payments`, { waitUntil: 'networkidle' });
    await captureScreenshot(mobilePage, '05-bank-payments-mobile');

    const mobileInbox = await mobilePage.locator('text=Role inbox, text=Awaiting my action').first().isVisible();
    log(mobileInbox ? 'PASS' : 'FAIL', 'Mobile: inbox renders at 390px width');

    await mobilePage.close();

  } catch (error) {
    log('FAIL', 'Test execution error', error.message);
  } finally {
    await browser.close();
  }

  generateReport();
}
function generateReport() {
  const fs = require('fs');
  const dir = 'docs/integration';
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

  const lines = [
    '# R1 Browser Acceptance Report',
    '',
    `Generated: ${new Date().toISOString()}`,
    `Staging URL: ${STAGING_URL || 'NOT CONFIGURED'}`,
    '',
    '## Summary',
    `- Passed: ${RESULTS.passed}`,
    `- Failed: ${RESULTS.failed}`,
    `- Skipped: ${RESULTS.skipped}`,
    '',
    '## Results',
    '',
  ];

  for (const item of RESULTS.items) {
    lines.push(`- [${item.status === 'PASS' ? 'x' : ' '}] ${item.message}${item.detail ? ` — ${item.detail}` : ''}`);
  }

  lines.push('', '## Screenshots', '- `docs/integration/screenshots/01-login-page.png`', '- `docs/integration/screenshots/02-after-login.png`', '- `docs/integration/screenshots/03-bank-payments-desktop.png`', '- `docs/integration/screenshots/04-ap-reconciliation-desktop.png`', '- `docs/integration/screenshots/05-bank-payments-mobile.png`');

  fs.writeFileSync(`${dir}/r1-browser-acceptance-report.md`, lines.join('\n'));
  console.log(`\nReport written to ${dir}/r1-browser-acceptance-report.md`);
}

main().catch((error) => {
  console.error('Fatal error:', error.message);
  generateReport();
  process.exit(1);
});