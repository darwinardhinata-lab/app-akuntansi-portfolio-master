import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const cli = fileURLToPath(new URL('./r1-browser-acceptance.mjs', import.meta.url));
const uuid = '404533da-f658-4f80-afc9-7e438ed4a529';
const other = '404533da-f658-4f80-afc9-7e438ed4a530';
const printPath = `/payment-plan/bank/${uuid}/print`;

async function run(t, config) {
  const cwd = await mkdtemp(join(tmpdir(), 'r1-ac10-'));
  t.after(() => rm(cwd, { recursive: true, force: true }));
  const env = { ...process.env };
  for (const key of Object.keys(env)) if (key.startsWith('R1_')) delete env[key];
  const child = spawn(process.execPath, [cli], {
    cwd, env: { ...env, ...config }, timeout: 90_000, stdio: ['ignore', 'pipe', 'pipe'],
  });
  let output = '';
  child.stdout.on('data', chunk => output += chunk);
  child.stderr.on('data', chunk => output += chunk);
  const code = await new Promise((resolve, reject) => {
    child.once('error', reject);
    child.once('close', resolve);
  });
  t.diagnostic(`CLI exit=${code}\n${output.trim()}`);
  assert.notEqual(code, null, 'CLI was terminated');
  return { code, report: await readFile(join(cwd, 'docs/integration/r1-browser-acceptance-report.md'), 'utf8') };
}

test('missing config writes FAIL before attempting browser launch', async t => {
  const { code, report } = await run(t, { R1_BROWSER_EXECUTABLE: 'intentionally-nonexistent-browser' });
  assert.notEqual(code, 0);
  assert.match(report, /Outcome: FAIL/);
  assert.match(report, /Missing required configuration: R1_STAGING_URL, R1_UAT_USER, R1_UAT_PASS, R1_BANK_REQUEST_UUID/);
  assert.doesNotMatch(report, /Browser launch/);
});

for (const mode of ['required-failure', 'wrong-print', 'valid']) {
  test(`disposable browser fixture: ${mode}`, { skip: !process.env.R1_BROWSER_EXECUTABLE }, async t => {
    const requests = [];
    const server = createServer((req, res) => {
      requests.push(req.url);
      res.setHeader('Content-Type', 'text/html; charset=utf-8');
      if (req.url === '/login' && req.method === 'GET') {
        return res.end('<form method="post"><input name="email"><input name="password"><button>Login</button></form>');
      }
      if (req.url === '/login' && req.method === 'POST') {
        res.writeHead(303, { Location: '/erp-panel/bank-payments', 'Set-Cookie': 'fixture=ok; Path=/; HttpOnly' });
        return res.end();
      }
      if (req.headers.cookie !== 'fixture=ok') { res.writeHead(403); return res.end(); }
      if (req.url === '/erp-panel/bank-payments') return res.end(`
        <main id="bankWorkspace" aria-busy="false"><select id="bankInbox" onchange="bankRequests.hidden = false"><option value="mine">Mine</option><option value="all">All</option></select>
        <div id="bankRequests" hidden><button data-bank-request-id="${uuid}" data-bank-doc="AC10-42" onclick="selectedBankDoc.textContent='AC10-42 — draft'; selectedBankDoc.dataset.bankRequestId=this.dataset.bankRequestId; selectedBankDoc.dataset.bankDoc=this.dataset.bankDoc; printBank.href='${printPath}'; bankAction.hidden=false">AC10-42</button></div>
        <div id="bankAction" hidden><h2 id="selectedBankDoc"></h2><a id="printBank">Print</a></div></main>`);
      if (req.url === '/erp-panel/ap-reconciliation') return res.end(mode === 'required-failure' ? '<p>Unavailable</p>' : '<input type="search" wire:model.live.debounce.400ms="invoiceSearch"><select wire:model.live="proposal.purchase_bill_id"><option>Choose</option></select><form wire:submit="propose"><input wire:model="proposal.amount"><input wire:model="proposal.reason"></form>');
      if (req.url === printPath) return res.end(`<body data-bank-request-id="${mode === 'wrong-print' ? other : uuid}" data-bank-doc="AC10-42"><h1>Bank payment — AC10-42</h1></body>`);
      res.writeHead(404); res.end();
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    t.after(() => new Promise(resolve => server.close(resolve)));
    const { code, report } = await run(t, {
      R1_STAGING_URL: `http://127.0.0.1:${server.address().port}`,
      R1_UAT_USER: 'disposable@example.test', R1_UAT_PASS: 'disposable',
      R1_BANK_REQUEST_UUID: uuid, R1_BROWSER_EXECUTABLE: process.env.R1_BROWSER_EXECUTABLE,
    });
    assert.equal(code, mode === 'valid' ? 0 : 1);
    assert.match(report, /Skipped: 0/);
    if (mode === 'required-failure') assert.match(report, /FAIL.*AP reconciliation queue/);
    else {
      assert.ok(requests.includes(printPath), 'must request the selected UUID');
      assert.ok(!requests.includes('/payment-plan/bank/1/print'));
      assert.match(report, mode === 'wrong-print' ? /FAIL.*Print identity.*identity mismatch.*HTTP 200/i : /PASS.*Print identity/);
    }
    assert.match(report, mode === 'valid' ? /Outcome: PASS/ : /Outcome: FAIL/);
  });
}