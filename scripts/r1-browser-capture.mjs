// Visual-only acceptance using HTML captured by authenticated HTTP tests.
// API is replayed, not a live authenticated browser workflow. No production access.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import {spawn} from 'node:child_process';
const dir=process.env.R1_UI_CAPTURE;
if(!dir) throw new Error('Set R1_UI_CAPTURE to disposable HTTP capture directory');
const server=http.createServer((req,res)=>{
 let file;
 if(req.url.startsWith('/livewire/livewire.js'))file=path.join(process.cwd(),'vendor/livewire/livewire/dist/livewire.js');
 else if(req.url.startsWith('/payment-plan/api/attachment/'))file=path.join(dir,'photo.png');
 else if(req.url.startsWith('/payment-plan/api/data'))file=path.join(dir,'data.json');
 else if(req.url.startsWith('/payment-plan/api/purchase-bills')){res.setHeader('Content-Type','application/json');return res.end('{"items":[],"total":0,"lastPage":1}');}
 else if(req.url==='/print')file=path.join(dir,'print.html');
 else if(req.url==='/')file=path.join(dir,'screen.html');
 else file=path.join(process.cwd(),'public',new URL(req.url,'http://localhost').pathname);
 if(!fs.existsSync(file)){res.statusCode=404;return res.end();}
 res.setHeader('Content-Type',file.endsWith('.png')?'image/png':file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':file.endsWith('.json')?'application/json':'text/html');
 let content=fs.readFileSync(file);
 // Laravel disables automatic Livewire script injection in unit-test rendering.
 if(file.endsWith('screen.html'))content=Buffer.from(content.toString().replace('</body>','<script src="/livewire/livewire.js" data-csrf="visual-replay-only" data-update-uri="/livewire/update"></script></body>'));
 if(file.endsWith('.html')) content=Buffer.from(content.toString().replaceAll('http:\\/\\/localhost\\/',`http:\\/\\/127.0.0.1:${server.address().port}\\/`).replaceAll(/https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\//g,`http://127.0.0.1:${server.address().port}/`));
 res.end(content);
});
await new Promise(r=>server.listen(0,'127.0.0.1',r));
const edge=process.env.R1_EDGE||'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser=spawn(edge,['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=19341',`--user-data-dir=${path.join(dir,'edge-profile')}`,'about:blank'],{stdio:'ignore'});
let ws;let seq=0;const pending=new Map(),errors=[];
try{
 let targets;
 for(let n=0;n<60;n++){try{targets=await(await fetch('http://127.0.0.1:19341/json')).json();break;}catch{await new Promise(r=>setTimeout(r,250));}}
 if(!targets)throw new Error('Edge debugging unavailable');
 ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise(r=>ws.addEventListener('open',r,{once:true}));
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(new Error(JSON.stringify(m.error))):p.resolve(m.result);}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++seq;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
 await send('Page.enable');await send('Runtime.enable');
 const evaluate=async expression=>(await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result.value;
 const report={boundary:'Real Edge on HTTP-test rendered HTML; replayed data API. NOT live end-to-end browser acceptance.',viewports:[]};
 for(const width of [1440,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<600});
  await send('Page.navigate',{url:`http://127.0.0.1:${server.address().port}/`});
  await new Promise(r=>setTimeout(r,1800));
  const state=await evaluate(`(()=>{document.querySelector('#bankInbox').value='all';document.querySelector('#bankInbox').dispatchEvent(new Event('change'));return !!document.querySelector('#newBankRequest');})()`);
  await new Promise(r=>setTimeout(r,400));
  await evaluate(`document.querySelector('#bankRequests button')?.click();document.querySelector('#addBankLine').click()`);
  const checks=await evaluate(`({width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,requestVisible:!!document.querySelector('#bankRequests button'),detailVisible:!document.querySelector('#bankAction').hidden,opacity:getComputedStyle(document.querySelector('.fi-main-ctn')).opacity,itemAdded:document.querySelectorAll('#bankLines fieldset').length,message:document.querySelector('#bankMessage').textContent})`);
  const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(dir,`desktop-${width}.png`),Buffer.from(shot.data,'base64'));
  report.viewports.push({width,rendered:state,...checks});
 }
 await send('Page.navigate',{url:`http://127.0.0.1:${server.address().port}/print`});await new Promise(r=>setTimeout(r,600));
 const pdf=await send('Page.printToPDF',{printBackground:true,preferCSSPageSize:true});fs.writeFileSync(path.join(dir,'payment.pdf'),Buffer.from(pdf.data,'base64'));
 report.print=await evaluate(`({items:document.querySelectorAll('table').length,photos:document.images.length})`);report.errors=errors;
 fs.writeFileSync(path.join(dir,'browser-report.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));
 if(errors.length||report.viewports.some(v=>v.opacity==='0'||v.overflow||!v.requestVisible||!v.detailVisible||v.itemAdded!==1))process.exitCode=1;
}finally{ws?.close();browser.kill();server.close();}