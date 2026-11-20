#!/usr/bin/env node
import { spawnSync } from 'node:child_process';
import { mkdirSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { randomBytes } from 'node:crypto';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const report = resolve(process.env.R1_STORAGE_REPORT || 'docs/integration/r1-storage-validation-report.md');
const temp = `${report}.${process.pid}.tmp`;
const key = `base64:${randomBytes(32).toString('base64')}`;
let output = '', passed = false;
process.exitCode = 1;
try {
  mkdirSync(dirname(report), { recursive: true });
  rmSync(report, { force: true });
  const result = spawnSync(process.env.R1_STORAGE_PHP || 'php', [
    'vendor/bin/phpunit', '--configuration', 'phpunit.xml', '--colors=never', '--filter', 'PaymentPlanAttachmentLifecycleTest',
  ], {
    cwd: root, encoding: 'utf8', timeout: 120_000, windowsHide: true,
    env: { ...process.env, APP_ENV: 'testing', APP_KEY: key, DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '', R1_STORAGE_REAL_LOCAL: '1' },
  });
  output = [result.stdout, result.stderr, result.error?.message].filter(Boolean).join('\n').replaceAll(key, '[redacted]');
  passed = result.status === 0 && /OK \([1-9]\d* tests?, [1-9]\d* assertions?\)/.test(output);
  if (!passed) output += `\nRequired lifecycle regression failed or was not executed (child exit: ${result.status ?? 'unavailable'}).`;
  writeFileSync(temp, `# R1 AC07 storage validation\n\nOutcome: ${passed ? 'PASS' : 'FAIL'}\n\nScope: local disposable private filesystem and in-memory SQLite; **not staging acceptance**.\n\n${output.trim().split(/\r?\n/).map(line => `    ${line}`).join('\n')}\n`, { flag: 'wx' });
  renameSync(temp, report);
  console.log(`AC07 local lifecycle: ${passed ? 'PASS' : 'FAIL'} — report: ${report}`);
  if (passed) process.exitCode = 0;
} catch (error) {
  console.error(`AC07 validation/report failed: ${error.message}`);
} finally {
  rmSync(temp, { force: true });
}