// Reference-only oracle. Never execute payments or import operational data.
import { readFileSync, writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const [domainPath, fixturePath, projectionPath] = process.argv.slice(2);
if (!domainPath || !fixturePath) throw new Error('Provide reference domain.mjs and approval fixture paths.');
const fixture = JSON.parse(readFileSync(fixturePath, 'utf8'));
const NativeDate = Date;
const frozen = fixture.provenance.clock;
globalThis.Date = class extends NativeDate {
  constructor(...args) { super(...(args.length ? args : [frozen])); }
  static now() { return NativeDate.parse(frozen); }
};
try {
  const { transition, financials, weeklySnapshot } = await import(pathToFileURL(domainPath).href);
  let passed = 0;
  const projections = {};
  for (const c of fixture.cases) {
    const base = Object.values(fixture.actors).find(a => a.id === c.actor.id) ?? {};
    const actor = { ...base, ...c.actor };
    let actual;
    try {
      const result = transition(c.given, c.action, c.input, actor, c.allRequests, c.settings);
      projections[c.id] = result;
      actual = { status: result.status };
      if (c.expect.settlement) actual.settlement = Object.fromEntries(Object.keys(c.expect.settlement).map(k => [k, result.settlement[k]]));
      if (result.decisions.at(-1).at !== frozen) throw new Error('Injected reference clock mismatch');
    } catch (error) {
      if (!error.code) throw error;
      actual = { error: error.code, http: error.status };
    }
    if (JSON.stringify(actual) !== JSON.stringify(c.expect)) throw new Error(`${c.id}: ${JSON.stringify(actual)} != ${JSON.stringify(c.expect)}`);
    passed++;
  }
  for (const c of fixture.weekly_snapshot ?? []) {
    let actual;
    try {
      const result = weeklySnapshot(c.start, c.end, c.allRequests, c.settings);
      actual = Object.fromEntries(Object.keys(c.expect).map(k => [k, result[k]]));
    } catch (error) {
      if (!error.code) throw error;
      actual = { error: error.code };
    }
    if (JSON.stringify(actual) !== JSON.stringify(c.expect)) throw new Error(`Weekly mismatch: ${JSON.stringify(actual)}`);
    passed++;
  }
  for (const c of fixture.financials ?? []) {
    if (JSON.stringify(financials(c.input)) !== JSON.stringify(c.expect)) throw new Error('Financial mismatch');
    passed++;
  }
  if (projectionPath) writeFileSync(projectionPath, JSON.stringify({ provenance: fixture.provenance, projections }, null, 2));
  const total = fixture.cases.length + (fixture.weekly_snapshot?.length ?? 0) + (fixture.financials?.length ?? 0);
  console.log(`Reference legacy checks: ${passed}/${total} passed; not ERP API/database/concurrency acceptance.`);
} finally {
  globalThis.Date = NativeDate;
}