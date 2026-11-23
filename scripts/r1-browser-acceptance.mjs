#!/usr/bin/env node
import { mkdir, rename, rm, writeFile } from 'node:fs/promises';
import { join } from 'node:path';

const dir = process.env.R1_BROWSER_EVIDENCE_DIR || 'docs/integration';
const report = join(dir, 'r1-browser-acceptance-report.md');
const temporary = `${report}.${process.pid}.tmp`;
const items = [], screenshots = [];
const uuidPattern = /^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i;
const required = ['R1_STAGING_URL', 'R1_UAT_USER', 'R1_UAT_PASS', 'R1_BANK_REQUEST_UUID'];
const [url, user, password, fixture] = required.map(key => process.env[key]);
const timeout = 10_000;
let complete = false;
let browser;

function record(status, message, detail = '') {
  const safe = String(detail).replaceAll(/\s+/g, ' ').trim();
  items.push({ status, message, detail: safe });
  console.log(`${status} ${message}${safe ? ` — ${safe}` : ''}`);
}

function requireThat(condition, message) {
  if (!condition) throw new Error(message);
}

async function check(name, run) {
  try {
    await run();
    record('PASS', name);
    return true;
  } catch (error) {
    record('FAIL', name, error.message);
    return false;
  }
}

async function capture(page, name) {
  const file = `screenshots/${name}.png`;
  await page.screenshot({ path: join(dir, file), fullPage: true, timeout });
  screenshots.push(file);
}

async function journey(base, expected) {
  const app = route => new URL(route, base);
  const bankUrl = app('erp-panel/bank-payments');
  const { chromium } = await import('playwright');
  record('INFO', 'Browser launch');
  browser = await chromium.launch({ headless: true, timeout, executablePath: process.env.R1_BROWSER_EXECUTABLE || undefined });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  context.setDefaultTimeout(timeout);
  context.setDefaultNavigationTimeout(timeout);
  const page = await context.newPage();
  const goto = async (tab, target, exact = true) => {
    const response = await tab.goto(target.href, { waitUntil: 'domcontentloaded' });
    requireThat(response?.ok(), `Navigation failed: HTTP ${response?.status() ?? 'unknown'}`);
    requireThat(new URL(tab.url()).origin === base.origin, 'Unexpected navigation origin');
    if (exact) requireThat(new URL(tab.url()).pathname === target.pathname, 'Unexpected navigation path');
    return response;
  };
  if (!await check('Authenticated bank payments workspace', async () => {
    await goto(page, app('login'));
    await capture(page, '01-login-page');
    const email = page.locator('input[name="email"], input[name="username"]');
    const form = email.locator('xpath=ancestor::form[1]');
    const action = await form.evaluate(node => node.action);
    requireThat(new URL(action).origin === base.origin, 'Unexpected login form origin');
    await email.fill(user);
    const input = form.locator('input[name="password"]');
    await input.fill(password);
    await Promise.all([
      page.waitForURL(next => next.origin === base.origin && next.pathname !== app('login').pathname, { waitUntil: 'domcontentloaded' }),
      input.press('Enter'),
    ]);
    await goto(page, bankUrl);
    await page.locator('#bankWorkspace').waitFor({ state: 'visible' });
  })) return;

  let transaction;
  const selected = await check('Bank inbox and selected transaction', async () => {
    const workspace = page.locator('#bankWorkspace[aria-busy="false"]');
    await workspace.waitFor({ state: 'visible' });
    const inbox = page.locator('#bankInbox');
    await inbox.selectOption('all');
    await workspace.waitFor({ state: 'visible' });
    requireThat(!await page.locator('#bankMessage.bank-error').isVisible(), 'Bank workspace reported an error');
    const row = page.locator(`#bankRequests button[data-bank-request-id="${expected}" i]`);
    const doc = await row.getAttribute('data-bank-doc');
    requireThat(doc, 'Missing document number on fixture row');
    await row.click();
    const detail = page.locator('#bankAction #selectedBankDoc');
    await detail.waitFor({ state: 'visible' });
    const id = (await detail.getAttribute('data-bank-request-id'))?.toLowerCase();
    requireThat(id === expected && await detail.getAttribute('data-bank-doc') === doc
      && (await detail.innerText()).startsWith(`${doc} — `), 'Selected detail does not match fixture');
    const print = page.locator('#printBank');
    await print.waitFor({ state: 'visible' });
    const href = await print.getAttribute('href');
    requireThat(href, 'Missing print link');
    const target = new URL(href, page.url());
    const expectedPath = app(`payment-plan/bank/${id}/print`).pathname;
    requireThat(target.origin === base.origin && target.pathname.toLowerCase() === expectedPath.toLowerCase()
      && !target.search && !target.hash && !target.username && !target.password, 'Print link does not match selected UUID');
    transaction = { id, doc, target };
    await capture(page, '03-bank-payments-desktop');
  });

  if (selected) await check('Print identity', async () => {
    const printed = await context.newPage();
    try {
      const response = await goto(printed, transaction.target);
      const body = printed.locator('body');
      const id = (await body.getAttribute('data-bank-request-id'))?.toLowerCase();
      const doc = await body.getAttribute('data-bank-doc');
      requireThat(id === transaction.id && doc === transaction.doc, `Print identity mismatch (HTTP ${response.status()})`);
      await printed.locator('h1').waitFor({ state: 'visible' });
      requireThat((await printed.locator('h1').innerText()).endsWith(` — ${transaction.doc}`), 'Print heading does not match selected document');
      await capture(printed, '06-bank-print');
    } finally {
      await printed.close();
    }
  });
  else record('FAIL', 'Print identity', 'Cannot verify print without a selected transaction');

  await check('AP reconciliation queue and proposal controls', async () => {
    await goto(page, app('erp-panel/ap-reconciliation'));
    for (const selector of ['input[wire\\:model\\.live\\.debounce\\.400ms="invoiceSearch"]', 'select[wire\\:model\\.live="proposal.purchase_bill_id"]', 'form[wire\\:submit="propose"] input[wire\\:model="proposal.amount"]', 'form[wire\\:submit="propose"] input[wire\\:model="proposal.reason"]']) {
      await page.locator(selector).waitFor({ state: 'visible' });
    }
    await capture(page, '04-ap-reconciliation-desktop');
  });

  await check('Mobile bank inbox at 390px', async () => {
    await page.setViewportSize({ width: 390, height: 844 });
    await goto(page, bankUrl);
    await page.locator('#bankWorkspace[aria-busy="false"] #bankInbox').waitFor({ state: 'visible' });
    requireThat(!await page.locator('#bankMessage.bank-error').isVisible(), 'Mobile inbox reported an error');
    await capture(page, '05-bank-payments-mobile');
  });
  complete = true;
}

try {
  await mkdir(dir, { recursive: true });
  // Never leave a previous PASS report at the canonical path if this run aborts.
  await rm(report, { force: true });
  const missing = required.filter(key => !process.env[key]?.trim());
  if (missing.length) {
    record('FAIL', 'Configuration', `Missing required configuration: ${missing.join(', ')}`);
  } else if (await check('Configuration', async () => {
    const base = new URL(url);
    requireThat(['http:', 'https:'].includes(base.protocol) && !base.username && !base.password && !base.search && !base.hash, 'Use an HTTP(S) application base URL without credentials, query or fragment');
    requireThat(uuidPattern.test(fixture), 'R1_BANK_REQUEST_UUID must be a UUID of an authorized test fixture');
  })) {
    const base = new URL(url);
    base.pathname = `${base.pathname.replace(/\/+$/, '')}/`;
    await journey(base, fixture.toLowerCase());
  }
} catch (error) {
  record('FAIL', 'Harness execution', error.message);
} finally {
  if (browser) await check('Browser cleanup', () => browser.close());
  const count = status => items.filter(item => item.status === status).length;
  const passed = count('PASS'), failed = count('FAIL'), skipped = count('SKIP');
  const outcome = complete && !failed ? 'PASS' : 'FAIL';
  process.exitCode = 1;
  try {
    await writeFile(temporary, [
      '# R1 Browser Acceptance Report', '', `Generated: ${new Date().toISOString()}`,
      `Outcome: ${outcome}`, `Journey complete: ${complete ? 'yes' : 'no'}`,
      `Passed: ${passed}`, `Failed: ${failed}`, `Skipped: ${skipped}`, '', '## Results', '',
      ...items.map(({ status, message, detail }) => `- ${status}: ${message}${detail ? ` — ${detail}` : ''}`),
      '', '## Screenshots', ...screenshots.map(file => `- \`${file}\``), '',
      'Technical harness evidence only; not Finance UAT or release sign-off.', '',
    ].join('\n'), { flag: 'wx' });
    await rename(temporary, report);
    console.log(`Outcome: ${outcome}; passed=${passed}; failed=${failed}; skipped=${skipped}; report=${report}`);
    if (outcome === 'PASS') process.exitCode = 0;
  } catch (error) {
    console.error(`FAIL report persistence — ${error.message}`);
    await rm(temporary, { force: true }).catch(() => {});
  }
}