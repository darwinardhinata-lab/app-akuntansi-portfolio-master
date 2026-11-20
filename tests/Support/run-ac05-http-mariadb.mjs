import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { join, resolve } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const [evidenceArg, mysqlBin, php] = process.argv.slice(2);
assert.ok(evidenceArg && mysqlBin && php, 'Pass a new evidence directory, MariaDB bin directory, and PHP executable');
const evidence = resolve(evidenceArg);
mkdirSync(evidence);
const data = join(evidence, 'db');
const root = process.cwd();
const executable = name => join(mysqlBin, `${name}.exe`);
const children = new Set();
const manifest = {};
const port = async () => {
  const s = createServer();
  await new Promise(r => s.listen(0, '127.0.0.1', r));
  const p = s.address().port;
  await new Promise(r => s.close(r));
  return p;
};
const dbPort = await port();
const client = ['--no-defaults', '-h127.0.0.1', `-P${dbPort}`, '-uroot', '--batch'];
const sql = text => execFileSync(executable('mysql'), client, { input: text, encoding: 'utf8', timeout: 8000, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
function start(file, args, env) {
  const child = spawn(file, args, { env, stdio: 'ignore', windowsHide: true });
  children.add(child);
  child.once('error', error => { child.launchError = error; });
  return child;
}
async function stop(child) {
  if (child.exitCode !== null || child.signalCode !== null || child.launchError) return;
  const exited = new Promise(r => child.once('exit', r));
  assert.ok(child.kill('SIGKILL'));
  await Promise.race([exited, delay(5000, undefined, { ref: false }).then(() => { throw new Error('Owned worker did not exit'); })]);
}
async function until(check, message) {
  for (let i = 0; i < 120; i++) {
    const result = await check();
    if (result) return result;
    await delay(200);
  }
  throw new Error(message);
}
const secret = () => randomBytes(16).toString('hex');
const dbPassword = secret(), loginPassword = secret();
const env = { ...process.env, APP_ENV:'testing', APP_DEBUG:'false', APP_KEY:`base64:${randomBytes(32).toString('base64')}`,
  DB_CONNECTION:'mysql', DB_URL:'', DB_HOST:'127.0.0.1', DB_PORT:String(dbPort), DB_SOCKET:'', DB_USERNAME:'ac05_app', DB_PASSWORD:dbPassword,
  AC05_DATADIR:data, AC05_LOGIN_PASSWORD:loginPassword, SESSION_DRIVER:'file', SESSION_COOKIE:'ac05_disposable', CACHE_STORE:'array',
  QUEUE_CONNECTION:'sync', PAYMENT_PLAN_V33_ENABLED:'true', PAYMENT_PLAN_V33_BANK_WORKFLOW:'true', PAYMENT_PLAN_V33_AP_ALLOCATIONS:'true',
  PAYMENT_PLAN_V33_ERP_BANK_BALANCE:'true', PAYMENT_PLAN_V33_RESERVATIONS:'true', PAYMENT_PLAN_V33_POSTING:'true', PLATFORM_POSTING_INTENTS_ENABLED:'true',
  LARAVEL_STORAGE_PATH:join(evidence,'storage'), APP_CONFIG_CACHE:join(evidence,'no-cache.php'), BCRYPT_ROUNDS:'4' };
function fixture(mode) {
  return execFileSync(php,['tests/Support/ac05-http-fixture.php', mode], { cwd: root, env, encoding:'utf8', timeout:120000, windowsHide:true });
}
async function httpServer(mode, marker) {
  const p = await port();
  const base = `http://127.0.0.1:${p}`;
  mkdirSync(join(env.LARAVEL_STORAGE_PATH,'framework','sessions'),{recursive:true});
  mkdirSync(join(env.LARAVEL_STORAGE_PATH,'framework','views'),{recursive:true});
  const child = start(php,['-S',`127.0.0.1:${p}`,'-t','public','tests/Support/ac05-http-router.php'],{...env,APP_URL:base,AC05_KILL_MODE:mode,AC05_MARKER:marker});
  await until(async () => {
    if (child.launchError || child.exitCode !== null) throw new Error(`PHP server exited: ${child.exitCode}`);
    try { return (await fetch(`${base}/login`, {signal:AbortSignal.timeout(1500)})).ok; } catch { return false; }
  }, 'PHP server not ready');
  return { base, child };
}
async function session(base) {
  const cookies = new Map();
  const request = async (path, options = {}) => {
    const response = await fetch(base+path,{...options,redirect:'manual',signal:AbortSignal.timeout(30000),headers:{...options.headers,Cookie:[...cookies.values()].join('; ')}});
    for (const cookie of response.headers.getSetCookie()) {
      const pair = cookie.split(';',1)[0]; cookies.set(pair.split('=',1)[0],pair);
    }
    return response;
  };
  const login = await request('/login');
  assert.equal(login.status,200);
  const token = (await login.text()).match(/name="_token" value="([^"]+)"/)?.[1];
  assert.ok(token,'login CSRF token missing');
  const logged = await request('/login',{method:'POST',body:new URLSearchParams({_token:token,email:'accountant@example.test',password:loginPassword})});
  assert.equal(logged.status,302);
  await logged.text();
  const csrf = cookies.get('XSRF-TOKEN');
  assert.ok(csrf,'session CSRF cookie missing');
  const auth = await request('/payment-plan/api/data',{headers:{Accept:'application/json'}});
  assert.equal(auth.status,200);
  assert.deepEqual((await auth.json()).actor.roles,['accountant']);
  return body => request('/payment-plan/api/action',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-XSRF-TOKEN':decodeURIComponent(csrf.slice(csrf.indexOf('=')+1))},body:JSON.stringify(body)});
}
try {
  for (const path of ['framework/sessions','framework/views','framework/cache','logs','app/private']) {
    mkdirSync(join(env.LARAVEL_STORAGE_PATH,path),{recursive:true});
  }
  writeFileSync(join(evidence,'init.log'),execFileSync(executable('mysql_install_db'),[`--datadir=${data}`,`--port=${dbPort}`],{encoding:'utf8',timeout:90000,windowsHide:true}));
  const database = start(executable('mysqld'),['--no-defaults',`--datadir=${data}`,`--port=${dbPort}`,'--bind-address=127.0.0.1','--skip-name-resolve',`--log-error=${join(evidence,'maria.log')}`],process.env);
  await until(() => { if(database.exitCode!==null || database.launchError) throw new Error('MariaDB exited before readiness'); try { return sql('SELECT 1;'); } catch { return false; } }, 'MariaDB not ready');
  sql(`CREATE USER 'ac05_app'@'127.0.0.1' IDENTIFIED BY '${dbPassword}';`);
  for (const mode of ['before','after']) {
    env.DB_DATABASE = `ac05_${mode}`;
    sql(`CREATE DATABASE ${env.DB_DATABASE}; GRANT ALL ON ${env.DB_DATABASE}.* TO 'ac05_app'@'127.0.0.1';`);
    writeFileSync(join(evidence,`${mode}-migrate.log`), fixture('migrate'));
    fixture('setup');
    sql(`REVOKE ALL PRIVILEGES ON ${env.DB_DATABASE}.* FROM 'ac05_app'@'127.0.0.1'; GRANT SELECT,INSERT,UPDATE,DELETE ON ${env.DB_DATABASE}.* TO 'ac05_app'@'127.0.0.1';`);
    const seen = new Set();
    const save = label => {
      const output = fixture('snapshot');
      let value;
      try { value = JSON.parse(output); }
      catch { throw new Error(`Snapshot failed: ${output.replaceAll(dbPassword,'[redacted]').replaceAll(loginPassword,'[redacted]')}`); }
      assert.ok(!seen.has(value.connection)); seen.add(value.connection);
      assert.deepEqual(value.engines,[{engine:'InnoDB'}]);
      writeFileSync(join(evidence,`${mode}-${label}.json`),JSON.stringify(value,null,2));
      return value.state;
    };
    const baseline = save('paid');
    assert.equal(baseline.request.status,'paid'); assert.equal(baseline.residual,'100.25');
    assert.equal(baseline.bank.available,'399.75'); assert.equal(baseline.bank.unposted_actual_debits,'100.25');
    assert.equal(baseline.reservation.status,'consumed'); assert.equal(baseline.allocation.status,'consumed');
    assert.equal(baseline.event.journal_id,null); assert.equal(baseline.ack,null); assert.equal(baseline.intent,null);
    const marker = join(evidence,`${mode}-kill.json`);
    const server = await httpServer(mode,marker);
    const post = await session(server.base);
    const payload = {id:baseline.request.id,version:baseline.request.version,mutationId:'ac05-http-post',action:'post_external',input:{checked:true,reference:'AC05-ERP'}};
    let settled = false;
    const pending = post(payload).then(r=>({status:r.status}),e=>({transport:e.cause?.code ?? e.name})).finally(()=>{ settled=true; });
    const parked = await until(()=>{ if(settled) throw new Error('HTTP completed before kill: '+mode); return existsSync(marker) && JSON.parse(readFileSync(marker,'utf8')); },'Missing kill marker');
    assert.equal(parked.pid,server.child.pid); assert.equal(parked.level,mode==='before'?1:0);
    await stop(server.child);
    const interrupted = await pending;
    assert.equal(interrupted.status,undefined); assert.ok(['UND_ERR_SOCKET','ECONNRESET'].includes(interrupted.transport));
    const killed = save('killed'); assert.ok(!seen.has(parked.connection));
    if(mode==='before') assert.deepEqual(killed,baseline);
    const fresh = await httpServer('none',marker);
    const retry = await session(fresh.base);
    const result = await retry(payload); assert.equal(result.status,200);
    const json = await result.json(); const journal = json.request.journal.journalId;
    const posted = save('posted');
    if(mode==='after') assert.deepEqual(posted,killed);
    assert.deepEqual(posted.allocation,baseline.allocation); assert.deepEqual(posted.reservation,baseline.reservation);
    assert.deepEqual(posted.bill,baseline.bill); assert.equal(posted.residual,baseline.residual);
    assert.equal(posted.bank.available,baseline.bank.available); assert.equal(posted.bank.ledger,'399.75');
    assert.equal(posted.bank.unposted_actual_debits,'0.00');
    assert.deepEqual({...posted.event,journal_id:null},baseline.event);
    assert.equal(posted.event.journal_id,journal); assert.equal(posted.intent.journal_id,journal); assert.ok(posted.intent.sealed_at);
    assert.equal(posted.ack.mutation_id,payload.mutationId); assert.equal(posted.ack.version,baseline.request.version+1);
    assert.equal(posted.request.version,posted.ack.version); assert.equal(posted.request.status,'posted');
    assert.equal(JSON.parse(posted.ack.result).request.journal.journalId,journal);
    assert.equal(posted.journals.length,baseline.journals.length+1);
    assert.deepEqual(posted.lines.filter(line=>line.journal_id===journal).map(line=>[String(line.account_code),line.position,String(line.amount)]).sort(),[['111003','KREDIT','100.25'],['211001','DEBET','100.25']]);
    const conflict = await retry({...payload,input:{...payload.input,base:'100.26'}});
    assert.equal(conflict.status,409); assert.equal((await conflict.json()).error,'mutation_conflict');
    assert.deepEqual(save('conflict'),posted);
    assert.equal(fixture('intent-conflict').trim(),'intent_amount_conflict');
    assert.deepEqual(save('intent-conflict'),posted);
    await stop(fresh.child);
    manifest[mode] = {parked, interrupted, retry:result.status, conflict:conflict.status, intentAmountConflict:true, journal, readers:[...seen]};
    console.log(mode,JSON.stringify(manifest[mode]));
  }
} finally {
  for (const child of [...children].reverse()) await stop(child);
  for (const path of [data,env.LARAVEL_STORAGE_PATH]) if (existsSync(path)) rmSync(path,{recursive:true,force:true});
  manifest.cleanup = {databaseRemoved:!existsSync(data), storageRemoved:!existsSync(env.LARAVEL_STORAGE_PATH), processes:[...children].map(child=>({pid:child.pid,exit:child.exitCode,signal:child.signalCode}))};
  writeFileSync(join(evidence,'manifest.json'),JSON.stringify(manifest,null,2));
}